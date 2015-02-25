<?php
	require_javascript("og/modules/addContactForm.js");
	$currentDimension = current_dimension_id();
	if (!isset($contacts_for_combo)) $contacts_for_combo = null;
	/*
	  <form style="height:100%;background-color:white" action="<?php echo get_url("contact", "add_permissions_user", array("id" => $user->getId())) ?>" class="internalForm" onsubmit="javascript:og.ogPermPrepareSendData('<?php echo $genid ?>');return true;" method="POST">
</form>
	 */
?>

<div class="widget-persons widget">
	<?php 
		/*
		 * Title of the widget
		 */
	?>
	<div style="overflow: hidden;" class="widget-header" onclick="og.dashExpand('<?php echo $genid?>');">
		<?php echo (isset($widget_title)) ? $widget_title : lang("contacts");?>
		<input name="mids" type="hidden" value="<?php echo isset($mids) ? $mids : "" ?>" />
		<div class="dash-expander ico-dash-expanded" id="<?php echo $genid; ?>expander"></div>
	</div>
	
	<div class="widget-body" id="<?php echo $genid; ?>_widget_body">
		<?php 
		/*
		 * This section display contacts (max displayed = $limit)
		 */
		?>
		<ul>
		<?php 
		$row_cls = "";
		$i = 0;
		foreach ($contacts as $person): ?>
			<?php
			$i++;
			if ($i < $limit) :?>
				<li<?php echo ($row_cls == "" ? "" : " class='$row_cls'")?>>
					<div class="contact-avatar">
						<a href="<?php echo $person->getCardUrl() ?>" class="person" onclick="if (og.core_dimensions) og.core_dimensions.buildBeforeObjectViewAction(<?php echo $person->getId()?>, true);"><img src="<?php echo $person->getPictureUrl(); ?>" /></a>
					</div>
					
					<div class="contact-info">
						<a href="<?php echo $person->getCardUrl() ?>" class="person" onclick="if (og.core_dimensions) og.core_dimensions.buildBeforeObjectViewAction(<?php echo $person->getId()?>, true);"><?php echo clean($person->getObjectName()) ?></a>
						<div class="email"><?php echo $person->getEmailAddress(); ?></div> 
					</div>
					
					<div class="clear"></div>
				</li>
			<?php $row_cls = $row_cls == "" ? "dashAltRow" : ""; ?>
			<?php endif;?>
		<?php endforeach; ?>
		</ul>
		
		<?php 
		/*
		 * This section is for add permissions 
		 */
		?>
		<?php if ($render_add) :?>
			<?php if (count($contacts) > 0) :?>
				<div class="person-list-separator"></div>
			<?php endif; ?>
			<div style="margin-top:4px; margin-left:10px; margin-right:10px;">
				<?php 
				/*
				 * Add people button
				 */
				?> 
				<div style="float:left; width: 75%;">
					<button style="overflow: hidden; width:auto;" onclick="$('.add-person-form').slideToggle();$(this).hide();$('#add-person-form-hide').show();" id="add-person-form-show" class="add-first-btn">
						<img src="public/assets/themes/default/images/16x16/add.png"/>&nbsp;<?php echo ((isset($add_people_btn)) ? lang('add contact here') : lang('add contact'));?></button>
				</div>
							  
				<?php 
				/*
				 * View all container
				 */
				?> 
				<?php if (count($contacts) == $limit) :?>
					<div class="view-all-container" style="float:right; width: 25%; overflow: hidden; font-size: 14px; margin-top: 0.75em; height: 16px;">
						<a href="<?php echo get_url('contact', 'init')?>" ><?php echo lang("view all");?></a>
					</div>
					<div class="clear"></div>
				<?php endif;?>
			
			
				<div class="clear"></div>
				<div id="person-form-<?php echo $genid ?>" class="add-person-form" style="display:none;">
					 
					 <?php 
					/*
					 * Contact registered
					 */
					?>
					<div class="contact_registered">
				      <label class="checkbox">
				      <?php echo radio_field($genid.'_rg', true, array('id' => $genid.'contact_registered', 'onchange' => 'og.addContactTypeChanged(1, "'.$genid.'")', 'value' => '1'))?>
		    		  <?php echo lang('Registered Person')?>
		    		  </label>
				    </div>
				    
				    <?php 
					/*
					 * This section display the select box for select contact that are not in this dim
					 */
					?>
				    <div id="<?php echo $genid ?>registered-person-form" style="padding-bottom:4px;">
						
						<div style="margin-left:10px;">
							<?php 
								if($contacts_for_combo != null){
									$select_box_attrib = array('id'=>'permissions_users_select_box','style'=>'width:160px; margin-bottom: 5px;');
									echo user_select_box('permissions_users_select_box',null,$select_box_attrib,$contacts_for_combo);
								}
							?>
							<button class="add-permission-button">
								<img src="public/assets/themes/default/images/16x16/add.png">
								<?php echo lang('add')?>
							</button>
						</div>
						
						<div class="clear"></div>
						
						
					</div>
					
					 <?php 
					/*
					 * Contact non registered
					 */
					?>
					<div class="contact_registered">
				      <label class="checkbox">
				      <?php echo radio_field($genid.'_rg', false, array('id' => $genid.'contact_non_registered', 'onchange' => 'og.addContactTypeChanged(0, "'.$genid.'")', 'value' => '0'))?>
				      <?php echo lang('Non registered person (add one from scratch)') ?>
				      </label>
				    </div>
				    
					<div class="clear"></div>
					
					<?php 
					/*
					 * This section display the options for add a new user and give him permissions on this dim
					 */
					?>
					<div id="<?php echo $genid ?>non-registered-person-form" style="display:none;" class="non-registered-person-form">
						<h2 style="display:none;"><?php echo lang("Non registered person (add one from scratch)") ?></h2>
						<div class="field name">
							<label><?php echo lang('name')?></label>
							<input id="first_name" style="width: 292px;" type="text" class="add-person-field"/>
						</div>
						<div class="field email">
							<label><?php echo lang('email')?></label>
							<input id="email" style="width: 292px;" type="email" name="contact[email]"/>
						</div>
						<div class="clear"></div>
						
						<div id="company">
							<?php tpl_display(get_template_path("add_contact/access_data_company","contact")); ?>
						</div>
						
						<button class="add-person-button">
							<img src="public/assets/themes/default/images/16x16/add.png">
							<?php echo lang('add')?>
						</button>
						
						<a href='#' style="float: right;" class='internalLink ContactFormShowAll' ><b><?php echo lang('View and edit all details')?></b></a>
						
					</div>
					
					
				</div>
		</div>
		<?php endif;?>
		
	<div class="progress-mask"></div>
		
	</div>
</div>

<script>

	$(function(){
		
		$(".ContactFormShowAll").click(function(){
			var params = new Array();

			var value = $("#first_name").val();
			params["widget_name"] = value;
			var firstName = '';
			var surname = '';
			var nameParts = value.split(' ');
			if (nameParts && nameParts.length > 1) {
				for ( var i in nameParts ){
					if (i == "remove") continue;
					var word = $.trim(nameParts[i]);
					if (word ) {
						if (!firstName) {
							firstName = word;
						}else{
							surname += word + " ";	
						}		
					}	
				}	 
			}
			surname = $.trim(surname);
			if (firstName && surname) {
				params['widget_name'] = firstName,
				params['widget_surname'] = surname
			}	
				
			params["widget_email"] = $("#email").val();
			params["widget_company"] = $('select[name*="contact[user][company_id]"]').val();
			params["widget_is_user"] = $('input[name*="contact[user][create-user]"]').val();
			params["widget_user_type"] = $('select[name*="contact[user][type]"]').val();
			og.openLink(og.getUrl('contact', 'add'), {'post' : params});
		});
		
		$(".add-person-button").click(function(){
			var container = $(this).closest(".widget-body") ;
			container.closest(".widget-body").addClass("loading");

			var value = $(container).find("input.add-person-field").val();
			if (value) {
				
				var parent = 0 ;
				var create_user = ( container.find('input[name="contact[user][create-user]"]').is(':checked') ) ?'on':'' ;
				//var password = container.find('input[name="contact[user][password]"]').val();
				//var password_a =container.find('input[name="contact[user][password_a]"]').val();
				var mail = container.find('input[name="contact[email]"]').val();

				//check email
				if(!og.checkValidEmailAddress(mail)){
					container.closest(".widget-body").removeClass("loading");
					og.err("<?php echo lang('invalid email address')?>");
					return;
				}
				
				var user_type = container.find('select[name="contact[user][type]"] option:selected').val();
				var company_id = container.find('select[name="contact[user][company_id]"] option:selected').val();
				
				var postVars = {
					'member[object_type_id]': <?php echo ObjectTypes::findByName('person')->getId()?>,
					'member[name]': value,
					'member[parent_member_id]' : parent,
					'member[dimension_id]': <?php echo Dimensions::findByCode('feng_persons')->getId()?>,
					'contact[email]': mail,
					'contact[user][create-user]' : create_user,
					'contact[user][type]': user_type,
					'contact[user][company_id]': company_id
				};

				var firstName = '';
				var surname = '';
				var nameParts = value.split(' ');
				if (nameParts && nameParts.length > 1) {
					for ( var i in nameParts ){
						if (i == "remove") continue;
						var word = $.trim(nameParts[i]);
						if (word ) {
							if (!firstName) {
								firstName = word;
							}else{
								surname += word + " ";	
							}		
						}	
					}	 
				}
				surname = $.trim(surname);
				if (firstName && surname) {
					postVars['contact[first_name]'] = firstName,
					postVars['contact[surname]'] = surname
				}	

				var ajaxOptions = {
					post : postVars,
					callback : function() {
						Ext.getCmp('menu-panel').expand(true); //ensure dimensions panel is expanded
					}
				};	

				var url = og.getUrl('contact', 'quick_add', {quick:1});

				og.openLink(url, ajaxOptions);
			}else{
				og.err("<?php echo lang('error add name required', lang('person'))?>");
				$(container).find("input.add-person-field").focus();
				container.removeClass("loading");
			}	
			
		});

		$(".add-permission-button").click(function(){
			var container = $(this).closest(".widget-body") ;
			container.closest(".widget-body").addClass("loading");
			
			var value = $("#permissions_users_select_box").val();	
			var params = new Array();
			params["cid"] = $("#permissions_users_select_box").val();
			params["mid"] = $('input[name="mids"]').val();			
			og.openLink(og.getUrl('contact','add_permissions_user'), {'post' : params, 
				callback: function(success, data){
					container.closest(".widget-body").removeClass("loading");
					
					//remove user from combo
					$("#permissions_users_select_box option[value="+data.id+"]").remove(); 

					//if permissions_users_select_box is empty 
					var options = $("#permissions_users_select_box").children();
					if(options.length == 0){
						$(".contact_registered").hide();
						$("#<?php echo $genid ?>registered-person-form").hide();
						$("#<?php echo $genid ?>non-registered-person-form").show();
						$("#<?php echo $genid ?>non-registered-person-form").children("h2").show();
					}
					
					//add user to the widget list
					if(container.closest(".widget-body").children("ul").children("li").last().attr('class') == 'dashAltRow'){
						var contact_div = '<li>';
					}else{
						var contact_div = '<li class="dashAltRow">';
					}					
					contact_div +='<div class="contact-avatar">';
					contact_div +='<a href="'+data.card_url+'" class="person" onclick="if (og.core_dimensions) og.core_dimensions.buildBeforeObjectViewAction('+data.id+', true);"><img src="'+data.picture_url+'" /></a>';
					contact_div +='</div>';										
					contact_div +='<div class="contact-info">';
					contact_div +='<a href="'+data.card_url+'" class="person" onclick="if (og.core_dimensions) og.core_dimensions.buildBeforeObjectViewAction('+data.id+', true);">'+data.object_name+'</a>';
					contact_div +='<div class="email">'+data.email+'</div>'; 
					contact_div +='</div>';										
					contact_div +='<div class="clear"></div>';
					contact_div +='</li>';

					//remove first user of the widget list
					var contacts_displayed = container.closest(".widget-body").children("ul").children();
					
					if(contacts_displayed.length == <?php echo ($limit-1) ?>){
						container.closest(".widget-body").children("ul").children("li").first().remove();
					}
					
					container.closest(".widget-body").children("ul").append(contact_div);					
				}

				});
					
		});
		
		$(".add-person-field").keypress(function(e){
			if(e.keyCode == 13){
				$(".add-person-button").click();
     		}
		});						
	});
	
	$(document).ready(function() {
		var options = $("#permissions_users_select_box").children();
		if(options.length == 0){
			$(".contact_registered").hide();
			$("#<?php echo $genid ?>registered-person-form").hide();
			$("#<?php echo $genid ?>non-registered-person-form").show();
			$("#<?php echo $genid ?>non-registered-person-form").children("h2").show();
		}
		
	});

</script>
