<?php 
$genid = gen_id();
if (!isset($default_configuration)) $default_configuration = false;
$form_url = $default_configuration ? get_url('config', 'configure_widgets_default_submit') : get_url("contact", "configure_widgets_submit");
$title = $default_configuration ? lang('default dashboard options') : lang('dashboard options');
?>
<div id="<?php echo $genid ?>adminContainer" class="adminGroups" style="height:100%;background-color:white">
<form method="post" action="<?php echo $form_url; ?>">
<div class="adminHeader">
  	<div class="adminTitle"><?php echo $title ?><div style="margin-left:50px;display:inline;"><?php echo submit_button(lang('save'))?></div></div>
</div>
<div class="adminSeparator"></div>
<div class="page widget-manager adminMainBlock">
	<div>
		<table style="border: 1px solid #D7E5F5;" id="<?php echo $genid?>top-widget-table">
			<tr><th colspan="3" style="text-align:center;border-bottom:1px solid #ADCCF0;"><?php echo lang('top')?></th></tr>
			<tr>
				<th><?php echo lang("title")?></th>
				<th style="text-align:right;"><?php echo lang("sec order")?></th>
				<th style="text-align:center;"><?php echo lang("section")?></th>
			</tr>
			<?php $altRow = 'altRow';
			foreach ( $widgets_info as $widget ) : 
				if ($widget['section'] != 'top') continue;
				$altRow = $altRow == '' ? 'altRow' : '';
			?>
			<tr class="<?php echo ($widget['section'] != 'none'?'enabled':'disabled')." $altRow"?>">
				<td><span style="padding:1px 0 3px 18px;" class="db-ico <?php echo $widget['icon']?>"></span><?php echo lang($widget['title'])?>
				<?php if (is_array(array_var($widget, 'options')) && count(array_var($widget, 'options')) > 0) {
						foreach ($widget['options'] as $option) {
				?><div style="padding-left:30px;padding-top:5px;"><?php 
					echo '- '.lang('widget_'.$option['widget'].'_'.$option['option']) . ": ";
					echo render_widget_option_input($option);
				?></div>
				<?php 	}
					} ?>
				</td>
				<td style="text-align:right;vertical-align:top;">
					<input type="number" name="widgets[<?php echo $widget['name']?>][order]" value="<?php echo $widget['order']?>" style="width:60px;text-align:right;"></input>
				</td>
				<td style="text-align:center;vertical-align:top;">
					<select name='widgets[<?php echo $widget['name']?>][section]' onchange="og.move_widget_table_row(this, '<?php echo $genid?>');">
						<option value="top" <?php echo $widget['section'] == 'top' ? 'selected=selected':''?>><?php echo lang('top')?></option>
						<option value="left" <?php echo $widget['section'] == 'left' ? 'selected=selected':''?>><?php echo lang('left')?></option>
						<option value="right" <?php echo $widget['section'] == 'right' ? 'selected=selected':''?>><?php echo lang('right')?></option>
						<option value="none" <?php echo $widget['section'] == 'none' ? 'selected=selected':''?>><?php echo lang('none')?></option>
					</select>
				</td>
			</tr>
			<?php endforeach;?>
		</table>
	</div><div class="left-section">
		<table style="border: 1px solid #D7E5F5;" id="<?php echo $genid?>left-widget-table">
			<tr><th colspan="3" style="text-align:center;border-bottom:1px solid #ADCCF0;"><?php echo lang('left')?></th></tr>
			<tr>
				<th><?php echo lang("title")?></th>
				<th style="text-align:right;"><?php echo lang("sec order")?></th>
				<th style="text-align:center;"><?php echo lang("section")?></th>
			</tr>
			<?php $altRow = 'altRow';
			foreach ( $widgets_info as $widget ) : 
				if ($widget['section'] != 'left') continue;
				$altRow = $altRow == '' ? 'altRow' : '';
			?>
			<tr class="<?php echo ($widget['section'] != 'none'?'enabled':'disabled')." $altRow"?>">
				<td><span style="padding:1px 0 3px 18px;" class="db-ico <?php echo $widget['icon']?>"></span><?php echo lang($widget['title'])?>
				<?php if (is_array(array_var($widget, 'options')) && count(array_var($widget, 'options')) > 0) {
						foreach ($widget['options'] as $option) {
				?><div style="padding-left:30px;padding-top:5px;"><?php 
					echo '- '.lang('widget_'.$option['widget'].'_'.$option['option']) . ": ";
					echo render_widget_option_input($option);
				?></div>
				<?php 	}
					} ?>
				</td>
				<td style="text-align:right;vertical-align:top;">
					<input type="number" name="widgets[<?php echo $widget['name']?>][order]" value="<?php echo $widget['order']?>" style="width:60px;text-align:right;"></input>
				</td>
				<td style="text-align:center;vertical-align:top;">
					<select name='widgets[<?php echo $widget['name']?>][section]' onchange="og.move_widget_table_row(this, '<?php echo $genid?>');">
						<option value="top" <?php echo $widget['section'] == 'top' ? 'selected=selected':''?>><?php echo lang('top')?></option>
						<option value="left" <?php echo $widget['section'] == 'left' ? 'selected=selected':''?>><?php echo lang('left')?></option>
						<option value="right" <?php echo $widget['section'] == 'right' ? 'selected=selected':''?>><?php echo lang('right')?></option>
						<option value="none" <?php echo $widget['section'] == 'none' ? 'selected=selected':''?>><?php echo lang('none')?></option>
					</select>
				</td>
			</tr>
			<?php endforeach;?>
		</table>
	</div><div class="right-section">
		<table style="border: 1px solid #D7E5F5;" id="<?php echo $genid?>right-widget-table">
			<tr><th colspan="3" style="text-align:center;border-bottom:1px solid #ADCCF0;"><?php echo lang('right')?></th></tr>
			<tr>
				<th><?php echo lang("title")?></th>
				<th style="text-align:right;"><?php echo lang("sec order")?></th>
				<th style="text-align:center;"><?php echo lang("section")?></th>
			</tr>
			<?php $altRow = 'altRow';
			foreach ( $widgets_info as $widget ) : 
				if ($widget['section'] != 'right') continue;
				$altRow = $altRow == '' ? 'altRow' : '';
			?>
			<tr class="<?php echo ($widget['section'] != 'none'?'enabled':'disabled')." $altRow"?>">
				<td><span style="padding:1px 0 3px 18px;" class="db-ico <?php echo $widget['icon']?>"></span><?php echo lang($widget['title'])?>
				<?php if (is_array(array_var($widget, 'options')) && count(array_var($widget, 'options')) > 0) {
						foreach ($widget['options'] as $option) {
				?><div style="padding-left:30px;padding-top:5px;"><?php 
					echo '- '.lang('widget_'.$option['widget'].'_'.$option['option']) . ": ";
					echo render_widget_option_input($option);
				?></div>
				<?php 	}
					} ?>
				</td>
				<td style="text-align:right;vertical-align:top;">
					<input type="number" name="widgets[<?php echo $widget['name']?>][order]" value="<?php echo $widget['order']?>" style="width:60px;text-align:right;"></input>
				</td>
				<td style="text-align:center;vertical-align:top;">
					<select name='widgets[<?php echo $widget['name']?>][section]' onchange="og.move_widget_table_row(this, '<?php echo $genid?>');">
						<option value="top" <?php echo $widget['section'] == 'top' ? 'selected=selected':''?>><?php echo lang('top')?></option>
						<option value="left" <?php echo $widget['section'] == 'left' ? 'selected=selected':''?>><?php echo lang('left')?></option>
						<option value="right" <?php echo $widget['section'] == 'right' ? 'selected=selected':''?>><?php echo lang('right')?></option>
						<option value="none" <?php echo $widget['section'] == 'none' ? 'selected=selected':''?>><?php echo lang('none')?></option>
					</select>
				</td>
			</tr>
			<?php endforeach;?>
		</table>
	</div><div class="clear"></div><div>
		<table style="border: 1px solid #D7E5F5;" id="<?php echo $genid?>none-widget-table">
			<tr><th colspan="3" style="text-align:center;border-bottom:1px solid #ADCCF0;"><?php echo trim(str_replace('--','',lang('none')))?></th></tr>
			<tr>
				<th><?php echo lang("title")?></th>
				<th style="text-align:right;"><?php echo lang("sec order")?></th>
				<th style="text-align:center;"><?php echo lang("section")?></th>
			</tr>
			<?php $altRow = 'altRow';
			foreach ( $widgets_info as $widget ) : 
				if ($widget['section'] != 'none') continue;
				$altRow = $altRow == '' ? 'altRow' : '';
			?>
			<tr class="<?php echo ($widget['section'] != 'none'?'enabled':'disabled')." $altRow"?>">
				<td><span style="padding:1px 0 3px 18px;" class="db-ico <?php echo $widget['icon']?>"></span><?php echo lang($widget['title'])?>
				<?php if (is_array(array_var($widget, 'options')) && count(array_var($widget, 'options')) > 0) {
						foreach ($widget['options'] as $option) {
				?><div style="padding-left:30px;padding-top:5px;"><?php 
					echo '- '.lang('widget_'.$option['widget'].'_'.$option['option']) . ": ";
					echo render_widget_option_input($option);
				?></div>
				<?php 	}
					} ?>
				</td>
				<td style="text-align:right;vertical-align:top;">
					<input type="number" name="widgets[<?php echo $widget['name']?>][order]" value="<?php echo $widget['order']?>" style="width:60px;text-align:right;"></input>
				</td>
				<td style="text-align:center;vertical-align:top;">
					<select name='widgets[<?php echo $widget['name']?>][section]' onchange="og.move_widget_table_row(this, '<?php echo $genid?>');">
						<option value="top" <?php echo $widget['section'] == 'top' ? 'selected=selected':''?>><?php echo lang('top')?></option>
						<option value="left" <?php echo $widget['section'] == 'left' ? 'selected=selected':''?>><?php echo lang('left')?></option>
						<option value="right" <?php echo $widget['section'] == 'right' ? 'selected=selected':''?>><?php echo lang('right')?></option>
						<option value="none" <?php echo $widget['section'] == 'none' ? 'selected=selected':''?>><?php echo lang('none')?></option>
					</select>
				</td>
			</tr>
			<?php endforeach;?>
		</table>
	</div>
		<?php echo submit_button(lang('save'))?>
</div>
</form>
</div>
<script>
og.move_widget_table_row = function(sel, genid) {
	var section = sel[sel.selectedIndex].value;
	var table = document.getElementById(genid + section+'-widget-table');

	var tr = sel.parentNode.parentNode;

	$("#"+table.id+" tr:last").after('<tr>'+tr.innerHTML+'</tr>');

	var new_tr = table.rows[table.rows.length-1];
	new_tr.deleteCell(2);
	if (table.rows.length % 2 == 0) new_tr.style.backgroundColor = '#F4F8F9';
	var c = new_tr.insertCell(2);
	c.style.textAlign = 'center';
	c.style.verticalAlign = 'top';
	c.appendChild(sel);

	tr.parentNode.deleteRow(tr.rowIndex);
	return true;
}
</script>