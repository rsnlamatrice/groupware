<?php
Hook::register ( "workspaces" );

function workspaces_total_task_timeslots_group_by_criterias($args, &$ret) {
	$wdimension = Dimensions::findByCode ( 'workspaces' );
	$tdimension = Dimensions::findByCode ( 'tags' );
	$ret[] = array('val' => 'dim_'.$wdimension->getId(), 'name' => $wdimension->getName());
	$ret[] = array('val' => 'dim_'.$tdimension->getId(), 'name' => $tdimension->getName());
}


function workspaces_custom_reports_additional_columns($args, &$ret) {
	$dimensions = Dimensions::findAll ( array("conditions" => "code IN ('workspaces','tags')") );
	foreach ($dimensions as $dimension) {
		$doptions = $dimension->getOptions(true);
		
		if( $doptions && isset($doptions->useLangs) && $doptions->useLangs ) {
			$name = lang($dimension->getCode());
		} else {
			$name = $dimension->getName();
		}
		
		$ret[] =  array('id' => 'dim_'.$dimension->getId(), 'name' => $name, 'type' => DATA_TYPE_STRING);
	}
}

function workspaces_total_tasks_times_csv_columns($cols, &$cols) {
	$dimension = Dimensions::findByCode('workspaces');
	$cols[] = $dimension->getName();
}

function workspaces_total_tasks_times_csv_column_values($ts, &$new_values) {
	if (!is_array($new_values)) $new_values = array();
	$dimension = Dimensions::findByCode('workspaces');
	if ($ts instanceof Timeslot) {
		$members = $ts->getMembers();
		$str = "";
		foreach ($members as $m) {
			if ($m->getDimensionId() == $dimension->getId()) {
				$str .= ($str == "" ? "" : " - "). $m->getName();
			}
		}
		$new_values[] = $str;
	}
}

function workspaces_include_tasks_template($ignored, &$more_content_templates) {
	$more_content_templates[] = array(
		'template' => 'groupby',
		'controller' => 'task',
		'plugin' => 'workspaces'
	);
}


function workspaces_quickadd_extra_fields($parameters) {
	if (array_var($parameters, 'dimension_id') == Dimensions::findByCode("workspaces")->getId()) {
		$parent_member = Members::findById(array_var($parameters, 'parent_id'));
		if ($parent_member instanceof Member && $parent_member->getObjectId() > 0) {
			$dimension_object = Objects::findObject($parent_member->getObjectId());
			
			$fields = $dimension_object->manager()->getPublicColumns();
			$color_columns = array();
			foreach ($fields as $f) {
				if ($f['type'] == DATA_TYPE_WSCOLOR) {
					$color_columns[] = $f['col'];
				}
			}
			foreach ($color_columns as $col) {
				foreach ($fields as &$f) {
					if ($f['col'] == $col && $dimension_object->columnExists($col)) {
						$color_code = $dimension_object->getColumnValue($col);
						echo '<input type="hidden" name="dim_obj['.$col.']" value="'.$color_code.'" />';
					}
				}
			}
		}
	}
}

function workspaces_render_widget_member_information(Member $member, $ret=null) {
	$ws_ot = ObjectTypes::findByName('workspace');
	if ($member->getObjectTypeId() == $ws_ot->getId()) {
		
		$ws = Workspaces::getWorkspaceById($member->getObjectId());
		if ($ws instanceof Workspace && $ws->getDescription() != "" && $ws->getColumnValue('show_description_in_overview')) {
			echo '<div style="margin-bottom:5px;">'.escape_html_whitespace(convert_to_links(clean($ws->getDescription()))).'</div>';
		}
		
	}
}
