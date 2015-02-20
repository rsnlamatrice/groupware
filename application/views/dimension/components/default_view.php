
<div id="<?php echo $genid; ?>member-seleector-dim<?php echo $dimension_id?>" class="single-dimension-selector" <?php echo $is_ie ? 'style="max-width:350px;"' : ''?>>
		<div class="header x-accordion-hd" onclick="og.dashExpand('<?php echo $expgenid?>', 'selector-body-dim<?php echo $dimension_id ?>');">
			<?php echo $dimension_name?>
			<div id="<?php echo $expgenid; ?>expander" class="dash-expander ico-dash-expanded"></div>
		</div>
		<div class="selector-body" id="<?php echo $expgenid?>selector-body-dim<?php echo $dimension_id ?>">
			<div id="<?php echo $genid; ?>selected-members-dim<?php echo $dimension_id?>" class="selected-members">

	<?php
		$dimension_has_selection = false; 
		if (count($dimension_selected_members) > 0) : 
			$alt_cls = "";
			foreach ($dimension_selected_members as $selected_member) :
				$allowed_members = array_keys($members_dimension);
				if (count($allowed_members) > 0 && !in_array($selected_member->getId(), $allowed_members)) continue;
				$dimension_has_selection = true;
				?>
				<div class="selected-member-div <?php echo $alt_cls?>" id="<?php echo $genid?>selected-member<?php echo $selected_member->getId()?>">
					<div class="completePath">
					</div>
					<div class="selected-member-actions" <?php echo $is_ie ? 'style="display:inline;margin-left:40px;float:none;"' : ''?>>
						<a href="#" class="coViewAction ico-delete" title="<?php echo lang('remove relation')?>" onclick="member_selector.remove_relation(<?php echo $dimension_id?>,'<?php echo $genid?>', <?php echo $selected_member->getId()?>)"><?php echo lang('remove')?></a>
					</div>
				</div>
	<?php		$alt_cls = $alt_cls == "" ? "alt-row" : "";
				$sel_mem_ids[] = $selected_member->getId();
		 	endforeach; ?>
				<div class="separator"></div>
	<?php endif;?>
			</div>
			<?php $form_visible = $dimension['is_multiple'] || (!$dimension['is_multiple'] && !$dimension_has_selection); ?>
			<div id="<?php echo $genid; ?>add-member-form-dim<?php echo $dimension_id?>" class="add-member-form" style="display:<?php echo ($form_visible?'block':'none')?>;">
				<?php
				$combo_listeners = array(
					"select" => "function (combo, record, index) { member_selector.autocomplete_select($dimension_id, '$genid', combo, record, 1); }",
					"blur" => "function (combo) { var rec = combo.store.getAt(0); if (combo.getValue().trim() != '' && rec) { combo.select(0, true); combo.fireEvent('select', combo, rec, 0); } }"
				);
				$empty_text = array_var($options, 'empty_text', lang('add new relation ' . $dimension['dimension_code']));
				/* ED141209
				 * pour faire comme dans small_view
				* valeur définie dans time/index.php
				* */
				echo autocomplete_member_combo("member_autocomplete-dim".$dimension_id, $dimension_id, $autocomplete_options, 
					$empty_text, array('class' => 'member-name-input'
							   , 'current_module' => $current_module
							   , 'is_ajax' => true), true, $genid .'add-member-input-dim'. $dimension_id, $combo_listeners);
				?>
				<div class="clear"></div>
			</div>
		</div>
	</div>
<script> 
$(function() {
	<?php 
		//add bredcrumb foreach selected member
		foreach ($sel_mem_ids as $selected_member_id){
    			?> $("#<?php echo $genid?>selected-member<?php echo $selected_member_id?> .completePath").append(og.getCrumbHtmlWithoutLinks(<?php echo $selected_member_id?>, <?php echo $dimension_id?>, <?php echo "'$genid'"?>));
    	<?php }?>
});
</script>	