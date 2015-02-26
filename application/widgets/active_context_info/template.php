<div class="widget-active-context-info widget">

	<div class="widget-header" onclick="og.dashExpand('<?php echo $genid?>');">
		<?php echo lang('active context info')?>
		<div class="dash-expander ico-dash-expanded" id="<?php echo $genid; ?>expander"></div>
	</div>
	
	<div class="widget-body" id="<?php echo $genid; ?>_widget_body" >
	<?php
		$first_member = true;
		foreach($members as $member) {
			$h2_style = $first_member ? 'padding-top:5px;' : '';
			$first_member = false;
			
			?><h2 style="<?php echo $h2_style?>"><span style="padding: 1px 0 3px 18px;" class="db-ico ico-unknown <?php echo $member->getIconClass()?>"><?php echo $member->getName()?></span></h2><?php
			
			$ret = null;
			Hook::fire("render_widget_member_information", $member, $ret);

			$ot = ObjectTypes::findById($member->getObjectTypeId());
			if ($ot->getName()=='project_folder' || $ot->getName()=='customer_folder') {
				$ot = ObjectTypes::findByName('folder');
			}
			
			$custom_properties = MemberCustomProperties::getAllMemberCustomPropertiesByObjectType($ot->getId());
			foreach ($custom_properties as $cp) {
				$cp_name = $cp->getName();
				$cp_values = MemberCustomPropertyValues::getMemberCustomPropertyValues($member->getId(), $cp->getId());
				$first = true;
				echo '<div class="cp-info"><span class="bold">'.$cp_name.': </span>';
				foreach ($cp_values as $cp_val) {
					if (!$first) echo ", ";
					$first = false;
					echo $cp_val->format_value();
				}
				echo '</div>';
			}
		} 
	?>
	</div>
</div>