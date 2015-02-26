<?php
require_javascript("og/CustomProperties.js");

$cps = CustomProperties::getAllCustomPropertiesByObjectType($_custom_properties_object->getObjectTypeId(), $co_type);
$ti = 0;

if (!isset($genid)) $genid = gen_id();
if (!isset($startTi)) $startTi = 10000;

if(count($cps) > 0){
	$print_table_functions = false;
	foreach($cps as $customProp){
		if(!isset($required) || ($required && ($customProp->getIsRequired() || $customProp->getVisibleByDefault())) || (!$required && !($customProp->getIsRequired() || $customProp->getVisibleByDefault()))){
			$ti++;
			$cpv = CustomPropertyValues::getCustomPropertyValue($_custom_properties_object->getId(), $customProp->getId());
			$default_value = $customProp->getDefaultValue();
			if($cpv instanceof CustomPropertyValue){
				$default_value = $cpv->getValue();
			}
			$name = 'object_custom_properties['.$customProp->getId().']';
			echo '<div style="margin-top:6px">';

			if ($customProp->getType() == 'boolean')
				echo checkbox_field($name, $default_value, array('tabindex' => $startTi + $ti, 'style' => 'margin-right:4px', 'id' => $genid . 'cp' . $customProp->getId()));

			echo label_tag(clean($customProp->getName()), $genid . 'cp' . $customProp->getId(), $customProp->getIsRequired(), array('style' => 'display:inline'), $customProp->getType() == 'boolean'?'':':');
			if ($customProp->getDescription() != ''){
				echo '<span class="desc"> - ' . clean($customProp->getDescription()) . '</span>';
			}
			echo '</div>';

			switch ($customProp->getType()) {
				case 'text':
				case 'numeric':
				case 'memo':
					if($customProp->getIsMultipleValues()){
						$numeric = ($customProp->getType() == "numeric");
						echo "<table><tr><td>";
						echo '<div id="listValues'.$customProp->getId().'" name="listValues'.$customProp->getId().'">';
						$isMemo = $customProp->getType() == 'memo';
						$count = 0;
						$fieldValues = CustomPropertyValues::getCustomPropertyValues($_custom_properties_object->getId(), $customProp->getId());
						if (!is_array($fieldValues) || count($fieldValues) == 0) {
							$def_cp_value = new CustomPropertyValue();
							$def_cp_value->setValue($default_value);
							$fieldValues = array($def_cp_value);
						}
						foreach($fieldValues as $value){
							$value = str_replace('|', ',', $value->getValue());
							if($value != ''){
								echo '<div id="value'.$count.'">';
								if($isMemo){
									echo textarea_field($name.'[]', $value, array('tabindex' => $startTi + $ti, 'id' => $name.'[]'));
								}else{
									echo text_field($name.'[]', $value, array('tabindex' => $startTi + $ti, 'id' => $name.'[]'));
								}
								echo '&nbsp;<a href="#" class="link-ico ico-delete" onclick="og.removeCPValue('.$customProp->getId().','.($count).','.($isMemo ? 1 : 0).')" ></a>';
								echo '</div>';
								$count++;
							}
						}
						echo '<div id="value'.$count.'">';
						if($customProp->getType() == 'memo'){
							echo textarea_field($name.'[]', '', array('tabindex' => $startTi + $ti, 'id' => $name.'[]'));
						}else{
							echo text_field($name.'[]', '', array('tabindex' => $startTi + $ti, 'id' => $name.'[]'));
						}
						echo '&nbsp;<a href="#" class="link-ico ico-add" onclick="og.addCPValue('.$customProp->getId().',\''.$isMemo.'\')">'.lang('add value').'</a><br/>';
						echo '</div>';
						echo '</div>';
						echo "</td></tr></table>";
						$include_script = true;
					} else {
						if($customProp->getType() == 'memo'){
							echo textarea_field($name, $default_value, array('tabindex' => $startTi + $ti, 'class' => 'short', 'id' => $genid . 'cp' . $customProp->getId()));
						}else{
							echo text_field($name, $default_value, array('tabindex' => $startTi + $ti, 'id' => $genid . 'cp' . $customProp->getId()));
						}
					}
					break;
				case 'boolean':
					break;
				case 'date':
					// dates from table are saved as a string in "Y-m-d H:i:s" format
					if($customProp->getIsMultipleValues()){
						$name .= '[]';
						$count = 0;
						$fieldValues = CustomPropertyValues::getCustomPropertyValues($_custom_properties_object->getId(), $customProp->getId());
						if (!is_array($fieldValues) || count($fieldValues) == 0) {
							$def_cp_value = new CustomPropertyValue();
							$def_cp_value->setValue($default_value);
							$fieldValues = array($def_cp_value);
						}
						echo '<table id="table'.$genid.$customProp->getId().'">';
						foreach($fieldValues as $val){
							$value = DateTimeValueLib::dateFromFormatAndString("Y-m-d H:i:s", $val->getValue());
							echo '<tr><td style="width:150px;">';
							echo pick_date_widget2($name, $value, null, $startTi + $ti, null, $genid . 'cp' . $customProp->getId());
							echo '</td><td>';
							echo '<a href="#" class="link-ico ico-delete" onclick="og.removeCPDateValue(\''.$genid.'\','.$customProp->getId().','.$count.')"></a>';
							echo '</td></tr>';
							$count++;
						}
						echo '</table>';
						echo '&nbsp;<a href="#" class="link-ico ico-add" onclick="og.addCPDateValue(\''.$genid.'\','.$customProp->getId().')">'.lang('add value').'</a><br/>';
					}else{
						if ($default_value == '') {
							$default_value = DateTimeValueLib::now()->advance(logged_user()->getTimezone()*3600, false)->toMySQL();
						}
						$value = DateTimeValueLib::dateFromFormatAndString("Y-m-d H:i:s", $default_value);
						echo pick_date_widget2($name, $value, null, $startTi + $ti, null, $genid . 'cp' . $customProp->getId());
					}
					break;
				case 'list':
					$options = array();
					if(!$customProp->getIsRequired()){
						$options[] = '<option value=""></option>';
					}
					$totalOptions = 0;
					$multValues = CustomPropertyValues::getCustomPropertyValues($_custom_properties_object->getId(), $customProp->getId());
					$toSelect = array();
					foreach ($multValues as $m){
						$toSelect[] = $m->getValue();
					}
					foreach(explode(',', $customProp->getValues()) as $value){
						$selected = ($value == $default_value) || ($customProp->getIsMultipleValues() && (in_array($value, explode(',', $default_value)))||in_array($value,$toSelect));
						$options[] = option_tag($value, $value);
						$totalOptions++;
					}
					
					$cp_id = $customProp->getId();
					$is_mult = $customProp->getIsMultipleValues() ? '1' : '0';
					echo select_box('aux_'.$name, $options, array('tabindex' => $startTi + $ti, 'style' => 'min-width:140px',
						'id' => $genid . 'cp' . $customProp->getId(), 'onchange' => "og.cp_list_selected(this, '$genid', '$name', $cp_id, $is_mult);"));
					
					echo '<div id="'.$genid.'cp_list_selected">';
					$i = 0;
					foreach ($toSelect as $value) {
						echo '<div style="width:200px;">'.$value
							.'&nbsp;<a href="#" onclick="og.cp_list_remove(this, \''.$genid.'\', '.$cp_id.');" class="db-ico coViewAction ico-delete" title="'.lang('remove').'">&nbsp;</a>'
							.'<input type="hidden" name="'.$name.'['.$i.']" value="'.clean($value).'" /></div>';
						$i++;
					}
					
					echo '<script>
						if (!og.cp_list_selected_index) og.cp_list_selected_index = [];
						if (!og.cp_list_selected_index['.$cp_id.']) og.cp_list_selected_index['.$cp_id.'] = [];
						og.cp_list_selected_index['.$cp_id.']["'.$genid.'"] = '.$i.';
						if (!og.cp_list_selected_values) og.cp_list_selected_values = [];
						if (!og.cp_list_selected_values['.$cp_id.']) og.cp_list_selected_values['.$cp_id.'] = [];
						og.cp_list_selected_values['.$cp_id.']["'.$genid.'"] = [];';
					foreach ($toSelect as $value) {
						echo "og.cp_list_selected_values[$cp_id]['$genid'].push('$value');";
					}
					echo '</script>';
					
					echo '</div>';
					
					$script = "<script>
						og.cp_list_remove = function(remove_link, genid, cp_id) {
						
							var inputs = remove_link.parentNode.getElementsByTagName('input');
							var input = inputs[0];
							var value = input.value;
							
							var tmp = [];
							for (var i=0; i<og.cp_list_selected_values[cp_id][genid].length; i++) {
								if (og.cp_list_selected_values[cp_id][genid][i] != value) {
									tmp.push(og.cp_list_selected_values[cp_id][genid][i]);
								}
							}
							og.cp_list_selected_values[cp_id][genid] = tmp;
							
							og.eventManager.fireEvent('after cp list change', [{
								cp_id: cp_id,
								genid: genid,
								values: og.cp_list_selected_values[cp_id][genid]
							}]);
							remove_link.parentNode.parentNode.removeChild(remove_link.parentNode);
							
							return false;
						}
						
						og.cp_list_selected = function(combo, genid, name, cp_id, is_multiple) {
							var i = og.cp_list_selected_index[cp_id][genid];
							var div = document.getElementById(genid + 'cp_list_selected');
							
							if (!og.cp_list_selected_values) og.cp_list_selected_values = [];
							if (!og.cp_list_selected_values[cp_id]) og.cp_list_selected_values[cp_id] = [];
							if (!og.cp_list_selected_values[cp_id][genid]) og.cp_list_selected_values[cp_id][genid] = [];
							
							var val = combo.options[combo.selectedIndex].value;
							if (val == '' || og.cp_list_selected_values[cp_id][genid].indexOf(val) >= 0) return;
							
							var html = '<div>'+ og.clean(val);
							html += '&nbsp;<a href=\"#\" onclick=\"og.cp_list_remove(this, \''+genid+'\', '+cp_id+');\" class=\"db-ico coViewAction ico-delete\">&nbsp;</a>';
							html += '<input type=\"hidden\" name=\"'+name+'['+i+']\" value=\"'+val+'\" /></div>';
							
							if (is_multiple) {
								div.innerHTML += html;
								og.cp_list_selected_index[cp_id][genid] = i + 1;
								og.cp_list_selected_values[cp_id][genid].push(val);
							} else {
								div.innerHTML = html;
								og.cp_list_selected_values[cp_id][genid] = [val];
							}
							
							og.eventManager.fireEvent('after cp list change', [{
								cp_id: cp_id,
								genid: genid,
								values: og.cp_list_selected_values[cp_id][genid]
							}]);
						}
					</script>";
					echo $script;
					
					break;
				case 'table':
					$columnNames = explode(',', $customProp->getValues());
					$cell_width = (600 / count($columnNames)) . "px";
					$html = '<div class="og-add-custom-properties"><table><tr>';
					foreach ($columnNames as $colName) {
						$html .= '<th style="width:'.$cell_width.';min-width:120px;">'.$colName.'</th>';
					}
					$ti += 1000;
					$html .= '</tr><tr>';
					$values = CustomPropertyValues::getCustomPropertyValues($_custom_properties_object->getId(), $customProp->getId());
					if (trim($default_value) != '' && (!is_array($values) || count($values) == 0)) {
						$def_cp_value = new CustomPropertyValue();
						$def_cp_value->setValue($default_value);
						$values = array($def_cp_value);
					}
					$rows = 0;
					if (is_array($values) && count($values) > 0) {
						foreach ($values as $val) {
							$col = 0;
							$values = str_replace("\|", "%%_PIPE_%%", $val->getValue());
							$exploded = explode("|", $values);
							foreach ($exploded as $v) {
								$v = str_replace("%%_PIPE_%%", "|", $v);
								$html .= '<td><input class="value" style="width:'.$cell_width.';min-width:120px;" name="'.$name."[$rows][$col]". '" value="'. clean($v) .'" tabindex="'.($startTi + $ti++).'"/></td>';
								$col++;
							}
							$html .= '<td><div class="ico ico-delete" style="width:16px;height:16px;cursor:pointer" onclick="og.removeTableCustomPropertyRow(this.parentNode.parentNode);return false;">&nbsp;</div></td>';
							$html .= '</tr><tr>';
							$rows++;
						}
					}
					$html .= '</tr></table>';
					$html .= '<a href="#" id="'.$genid.'-add-row-'.$customProp->getId().'" tabindex="'.($startTi + $ti + 50*count($columnNames)).'" onclick="og.addTableCustomPropertyRow(this.parentNode, true, null, '.count($columnNames).', '.($startTi + $ti).', '.$customProp->getId().');return false;">' . lang("add") . '</a></div>';
					if ($rows == 0) {
						// create first empty row
						$html .= '<script>if (!Ext.isIE) document.getElementById("'.$genid.'-add-row-'.$customProp->getId().'").onclick();</script>';
					}
					$ti += 50*count($columnNames);
					$print_table_functions = true;
					echo $html;
					break;
					
				case 'contact':
					$value = '0';
					$cp_value = CustomPropertyValues::getCustomPropertyValue($_custom_properties_object->getId(), $customProp->getId());
					if ($cp_value instanceof CustomPropertyValue && is_numeric($cp_value->getValue())) {
						$value = $cp_value->getValue();
						$contact = Contacts::findById($value);
					}
					
					Hook::fire('object_contact_cp_filters', array('cp' => $customProp, 'object' => $_custom_properties_object), $filters);
					if (is_array($filters) && count($filters) > 0) {
						$filters_str = '{';
						foreach ($filters as $k => $v) {
							if ($v == '') continue;
							$filters_str .= ($filters_str=='{' ? '' : ',') . "$k : $v";
						}
						$filters_str .= '}';
					} else {
						$filters_str = 'null';
					}
					
					$html = '<div id="'.$genid.'contacts_combo_container-cp'.$customProp->getId().'"></div>';
					$html .= '<script>'.
					'$(function(){
					  og.renderContactSelector({
						genid: "'.$genid.'",
						id: "cp'.$customProp->getId().'",
						name: "'.$name.'",
						render_to: "contacts_combo_container-cp'.$customProp->getId().'",
						selected: '.$value.',
						selected_name: "'.($contact instanceof Contact ? clean($contact->getName()) : '').'",
						filters: '.$filters_str.'
					  });
					});
					</script>';
					echo $html;
					break;
				default: break;
			}
		}
	}
}

?>