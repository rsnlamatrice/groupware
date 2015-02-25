/**
 *  OverviewManager
 *
 */
og.OverviewManager = function() {

	var actions, moreActions;

	this.doNotRemove = true;
	this.needRefresh = false;

	if (!og.OverviewManager.store) {
		og.OverviewManager.store = new Ext.data.Store({
			proxy: new og.GooProxy({
				url: og.getUrl('object', 'list_objects')
			}),
			reader: new Ext.data.JsonReader({
				root: 'objects',
				totalProperty: 'totalCount',
				id: 'id',
				fields: [
					'name', 'object_id', 'type', 'ot_id', 'createdBy', 'createdById', 'dateCreated', 
					'updatedBy', 'updatedById', 'dateUpdated', 'icon', 'wsIds', 'manager', 'mimeType', 'url', 'ix', 'isRead', 'memPath'
				]
			}),
			remoteSort: true,
			listeners: {
				'load': function() {
					var d = this.reader.jsonData;
					if (d.totalCount == 0) {
						var sel_context_names = og.contextManager.getActiveContextNames();
						if (sel_context_names.length > 0) {
							this.fireEvent('messageToShow', lang("no objects message", lang("objects"), sel_context_names.join(', ')));
						} else {
							this.fireEvent('messageToShow', lang("no more objects message", lang("objects")));
						}
					} else {
						this.fireEvent('messageToShow', "");
					}
					var cmp = Ext.getCmp('overview-manager');
					if (cmp) {
						cmp.getView().focusRow(og.lastSelectedRow.overview+1);
						var sm = cmp.getSelectionModel();
						sm.clearSelections();
					}
										
					Ext.getCmp('overview-manager').reloadGridPagingToolbar('object','list_objects','overview-manager');
				}
			}
		});
		og.OverviewManager.store.setDefaultSort('dateUpdated', 'desc');
	}
	this.store = og.OverviewManager.store;
	this.store.addListener({messageToShow: {fn: this.showMessage, scope: this}});

	function renderDragHandle(value, p, r, ix) {
		return '<div class="img-grid-drag" title="' + lang('click to drag') + '" onmousedown="var sm = Ext.getCmp(\'overview-manager\').getSelectionModel();if (!sm.isSelected('+ix+')) sm.clearSelections();sm.selectRow('+ix+', true);"></div>';
	}
	
	var readClass = 'read-unread-' + Ext.id();
	var notReadable = {
		'Contacts': true,
		'Companies': true,
		'Comments': true,
		'ProjectFileRevisions': true
	};
	function renderIsRead(value, p, r){
		if (!notReadable[r.data.manager]) {
			var idr = Ext.id();
			var idu = Ext.id();
			var jsr = 'og.OverviewManager.store.getById(\'' + r.id + '\').data.isRead = true; Ext.select(\'.' + readClass + r.id + '\').removeClass(\'bold\'); Ext.get(\'' + idu + '\').setDisplayed(true); Ext.get(\'' + idr + '\').setDisplayed(false); og.openLink(og.getUrl(\'object\', \'mark_as_read\', {ids:\'' + r.data.manager + ':' + r.data.object_id + '\'}));'; 
			var jsu = 'og.OverviewManager.store.getById(\'' + r.id + '\').data.isRead = false; Ext.select(\'.' + readClass + r.id + '\').addClass(\'bold\'); Ext.get(\'' + idr + '\').setDisplayed(true); Ext.get(\'' + idu + '\').setDisplayed(false); og.openLink(og.getUrl(\'object\', \'mark_as_unread\', {ids:\'' + r.data.manager + ':' + r.data.object_id + '\'}));';
			return String.format(
				'<div id="{0}" title="{1}" class="db-ico ico-read" style="display:{2}" onclick="{3}"></div>' + 
				'<div id="{4}" title="{5}" class="db-ico ico-unread" style="display:{6}" onclick="{7}"></div>',
				idu, lang('mark as unread'), value ? 'block' : 'none', jsu, idr, lang('mark as read'), value ? 'none' : 'block', jsr
			);
		} else {
			return "";
		}
	}
	
	function renderName(value, p, r) {
		var viewUrl = r.data.url;
		
		var classes = readClass + r.id;
		if (!r.data.isRead && !notReadable[r.data.manager]) classes += " bold";
		
		var actions = '';
		var actionStyle= ' style="font-size:90%;color:#777777;padding-top:3px;padding-left:18px;background-repeat:no-repeat" ';
		if (r.data.type == 'webpage') {
			viewUrl = og.getUrl('webpage', 'view', {id:r.data.object_id});
			actions += String.format('<a class="list-action ico-open-link" href="{0}" target="_blank" title="{1}" ' + actionStyle + '> </a>',
					r.data.url.replace(/\"/g, escape("\"")).replace(/\'/g, escape("'")), lang('open link in new window', og.clean(value)));
		}
		actions = '<span>' + actions + '</span>';
	
		if (value.trim() == "") {
			var cleanvalue = lang("n/a");
		} else {
			var cleanvalue = og.clean(value);
		}
		
		mem_path = "";
		var mpath = Ext.util.JSON.decode(r.data.memPath);
		if (mpath) mem_path = og.getCrumbHtml(mpath);
		
		var name = String.format('<a style="font-size:120%" href="{1}" class="{2}" onclick="og.openLink(\'{1}\');return false;">{0}</a>', cleanvalue, viewUrl, classes) + mem_path;
		
		return name + actions;
	}

	function renderType(value, p, r){
		return String.format('<i>' + lang(value) + '</i>')
	}
	
	function renderIcon(value, p, r) {
		var classes = "db-ico ico-unknown " + r.data.icon;
		if (r.data.mimeType) {
			if (r.data.name.indexOf(".") >= 0) {
				var extension = r.data.name.substring(r.data.name.indexOf(".") + 1);
				classes += " ico-ext-" + extension;
			}
			var path = r.data.mimeType.replace(/\//ig, "-").split("-");
			var acc = "";
			for (var i=0; i < path.length; i++) {
				acc += path[i];
				classes += " ico-" + acc;
				acc += "-";
			}
		}
		return String.format('<div class="{0}" title="{1}"/>', classes, lang(r.data.type));
	}

	function renderUser(value, p, r) {
		if (r.data.updatedById) {
			var classes = readClass + r.id;
			if (!r.data.isRead && !notReadable[r.data.manager]) classes += " bold";
			
			return String.format('<a href="{1}" class="{2}" onclick="og.openLink(\'{1}\');return false;">{0}</a>', og.clean(value), og.getUrl('contact', 'card', {id: r.data.updatedById}), classes);
		} else if (value) {
			return og.clean(value);
		} else {
			return lang("n/a");
		}
	}

	function renderAuthor(value, p, r) {
		if (r.data.createdById) {
			return String.format('<a href="{1}" onclick="og.openLink(\'{1}\');return false;">{0}</a>', og.clean(value), og.getUrl('contact', 'card', {id: r.data.createdById}));
		} else if (value) {
			return og.clean(value);
		} else {
			return lang("n/a");
		}
	}

	function renderDate(value, p, r) {
		if (!value) {
			return "";
		}
		return value;
	}

	function getSelectedIds() {
		var selections = sm.getSelections();
		if (selections.length <= 0) {
			return '';
		} else {
			var ret = '';
			for (var i=0; i < selections.length; i++) {
				ret += "," + selections[i].data.object_id;
			}
			og.lastSelectedRow.overview = selections[selections.length-1].data.ix;
			return ret.substring(1);
		}
	}
	
	this.getSelectedIds = getSelectedIds;
	
	function getFirstSelectedId() {
		if (sm.hasSelection()) {
			return sm.getSelected().data.object_id;
		}
		return '';
	}

	var sm = new Ext.grid.CheckboxSelectionModel();
	sm.on('selectionchange',
		function() {
			var allUnread = true, allRead = true;
			var selections = sm.getSelections()
			for (var i=0; i < selections.length; i++) {
				if (selections[i].data.manager != 'Contacts' && selections[i].data.manager != 'Companies' && selections[i].data.manager != 'Comments' && selections[i].data.manager != 'ProjectFileRevisions') {
					if (selections[i].data.isRead){
						allUnread = false;
					} else {
						allRead = false;
					}
					if (!allUnread && !allRead) break;
				}
			}
		
			if (sm.getCount() <= 0) {
				actions.del.setDisabled(true);
				actions.more.setDisabled(true);
				actions.archive.setDisabled(true);
				markactions.markAsRead.setDisabled(true);
				markactions.markAsUnread.setDisabled(true);
			} else {
				actions.del.setDisabled(false);
				actions.more.setDisabled(false);
				actions.archive.setDisabled(false);
				if (sm.getSelected().data.mimeType == 'prsn') {
					moreActions.slideshow.setDisabled(false);
				} else {
					moreActions.slideshow.setDisabled(true);
				}
				if (sm.getSelected().data.type == 'file') {
					moreActions.download.setDisabled(false);
				} else {
					moreActions.download.setDisabled(true);
				}
				if (allUnread) {
					markactions.markAsUnread.setDisabled(true);
				} else {
					markactions.markAsUnread.setDisabled(false);
				}
				if (allRead) {
					markactions.markAsRead.setDisabled(true);
				} else {
					markactions.markAsRead.setDisabled(false);
				}
			}
		});
	var cm = new Ext.grid.ColumnModel([
		sm,{
			id: 'draghandle',
			header: '&nbsp;',
			width: 18,
        	renderer: renderDragHandle,
        	fixed:true,
        	resizable: false,
        	hideable:false,
        	menuDisabled: true
		},{
        	id: 'icon',
        	header: '&nbsp;',
        	dataIndex: 'icon',
        	width: 28,
        	renderer: renderIcon,
        	fixed:true,
        	resizable: false,
        	hideable:false,
        	menuDisabled: true
		},{
			id: 'isRead',
			header: '&nbsp;',
			dataIndex: 'isRead',
			width: 16,
        	renderer: renderIsRead,
        	fixed:true,
        	resizable: false,
        	hideable:false,
        	menuDisabled: true
        },{
			id: 'type',
			header: lang('type'),
			dataIndex: 'type',
			width: 80,
        	renderer: renderType,
        	fixed:false,
        	resizable: true,
        	hideable:true,
        	menuDisabled: true
		},{
			id: 'name',
			header: lang("name"),
			dataIndex: 'name',
			width: 300,
			renderer: renderName,
			sortable:true
        },{
        	id: 'user',
        	header: lang('user'),
        	dataIndex: 'updatedBy',
        	width: 120,
        	renderer: renderUser
        },{
			id: 'updatedOn',
			header: lang("last update"),
			dataIndex: 'dateUpdated',
			width: 80,
			renderer: renderDate,
			sortable:true
        },{
			id: 'createdOn',
			header: lang("created on"),
			dataIndex: 'dateCreated',
			width: 80,
			hidden: true,
			renderer: renderDate,
			sortable:true
		},{
			id: 'author',
			header: lang("author"),
			dataIndex: 'createdBy',
			width: 120,
			renderer: renderAuthor,
			hidden: true
		}]);
	cm.defaultSortable = false;
	
	markactions = {
		markAsRead: new Ext.Action({
			text: lang('mark as read'),
		    tooltip: lang('mark as read desc'),
		    iconCls: 'ico-mark-as-read',
			disabled: true,
			handler: function() {
				this.load({
					action: 'markasread',
					objects: getSelectedIds()
				});
				this.getSelectionModel().clearSelections();
			},
			scope: this
		}),
		
		markAsUnread: new Ext.Action({
			text: lang('mark as unread'),
            tooltip: lang('mark as unread desc'),
            iconCls: 'ico-mark-as-unread',
			disabled: true,
			handler: function() {
				this.load({
					action: 'markasunread',
					objects: getSelectedIds()
				});
				this.getSelectionModel().clearSelections();
			},
			scope: this
		})
	};

	moreActions = {
		download: new Ext.Action({
			text: lang('download'),
			iconCls: 'ico-download',
			handler: function(e) {
				var url = og.getUrl('files', 'download_file', {id: getFirstSelectedId()});
				window.open(url);
			}
		}),
		properties: new Ext.Action({
			text: lang('properties'),
			iconCls: 'ico-properties',
			handler: function(e) {
				var o = sm.getSelected();
				var url = og.getUrl('object', 'view', {id: o.data.object_id, manager: o.data.manager});
				og.openLink(url);
			}
		}),
		slideshow: new Ext.Action({
			text: lang('slideshow'),
			iconCls: 'ico-slideshow',
			handler: function(e) {
				og.slideshow(getFirstSelectedId());
			},
			disabled: true
		})
	}
	
	//alert(quickAdd);
	actions = {
		newCO: new og.QuickAdd({
			menu: quickAdd.menu 
		}) ,
		
		del: new Ext.Action({
			text: lang('move to trash'),
            tooltip: lang('move selected objects to trash'),
            iconCls: 'ico-trash',
			disabled: true,
			handler: function() {
				if (confirm(lang('confirm move to trash'))) {
					this.load({
						action: 'delete',
						objects: getSelectedIds()
					});
					this.getSelectionModel().clearSelections();
				}
			},
			scope: this
		}),
		archive: new Ext.Action({
			text: lang('archive'),
            tooltip: lang('archive selected object'),
            iconCls: 'ico-archive-obj',
			disabled: true,
			handler: function() {
				if (confirm(lang('confirm archive selected objects'))) {
					this.load({
						action: 'archive',
						objects: getSelectedIds()
					});
					this.getSelectionModel().clearSelections();
				}
			},
			scope: this
		}),
		more: new Ext.Action({
			text: lang('more'),
            tooltip: lang('more actions on first selected object'),
            iconCls: 'ico-more',
			disabled: true,
			menu: {items: [
				moreActions.download,
				moreActions.properties,
				moreActions.slideshow
			]}
		}),
		markAs: new Ext.Action({
			text: lang('mark as'),
			tooltip: lang('mark as desc'),
			menu: [
				markactions.markAsRead,
				markactions.markAsUnread
			]
		}),
		refresh: new Ext.Action({
			text: lang('refresh'),
            tooltip: lang('refresh desc'),
            iconCls: 'ico-refresh',
			handler: function() {
				this.load();
			},
			scope: this
		}),
		showAsDashboard: new Ext.Action({
			id: "view-as-dashboard",
			text: lang('view as dashboard'),
                        tooltip: lang('view as dashboard'),
                        iconCls: 'ico-view-as-dashboard',
			handler: function() {
				og.switchToDashboard();
			},
			scope: this
		})
    };
    
	og.OverviewManager.superclass.constructor.call(this, {
		enableDrag: true,
		ddGroup: 'MemberDD',
		store: this.store,
		layout: 'fit',
		autoExpandColumn: 'name',
		cm: cm,
		stateful: og.preferences['rememberGUIState'],
		stripeRows: true,
		closable: true,
		id: 'overview-manager',
		bbar: new og.CurrentPagingToolbar({
			pageSize: og.config['files_per_page'],
			store: this.store,
			displayInfo: true,
			displayMsg: lang('displaying objects of'),
			emptyMsg: lang("no objects to display")
		}),
		viewConfig: {
			forceFit: true
		},
		sm: sm,
		tbar:[
			actions.newCO,
			'-',
			actions.archive,
			actions.del,			
			'-',
			actions.more,
			actions.markAs,
			'->' ,
			actions.showAsDashboard
		],
		listeners: {
			'render': {
				fn: function() {
					this.innerMessage = document.createElement('div');
					this.innerMessage.className = 'inner-message';
					var msg = this.innerMessage;
					var elem = Ext.get(this.getEl());
					var scroller = elem.select('.x-grid3-scroller');
					scroller.each(function() {
						this.dom.appendChild(msg);
					});
				},
				scope: this
			}
		}
	});

};

Ext.extend(og.OverviewManager, Ext.grid.GridPanel, {
	load: function(params) {
		if (!params) params = {};
		if (typeof params.start == 'undefined') {
			//var start = (this.getBottomToolbar().getPageData().activePage - 1) * og.config['files_per_page'];
			var start = 0;
		} else {
			var start = 0;
		}
		Ext.apply(this.store.baseParams, {
		      context: og.contextManager.plainContext()			
		});
		this.store.load({
			params: Ext.applyIf(params, {
				start: start,
				limit: og.config['files_per_page']
			})
		});
		this.needRefresh = false;
	},
	
	activate: function() {
		if (this.needRefresh) {
			this.load({start: 0});
		}
	},
	
	reset: function() {
		this.load({start:0});
	},
	
	trashObjects: function() {
		if (confirm(lang('confirm move to trash'))) {
			this.load({
				action: 'delete',
				objects: this.getSelectedIds()
			});
			this.getSelectionModel().clearSelections();
		}
	},
	
	archiveObjects: function() {
		if (confirm(lang('confirm archive selected objects'))) {
			this.load({
				action: 'archive',
				objects: this.getSelectedIds()
			});
			this.getSelectionModel().clearSelections();
		}
	},
	
	showMessage: function(text) {
		if (this.innerMessage) {
			this.innerMessage.innerHTML = text;
		}
	}
});

Ext.reg("overview", og.OverviewManager);