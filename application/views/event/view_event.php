<?php
$duration = $variables["duration"];
$desc = $variables["desc"];
$attendance = isset($variables["attendance"]) ? $variables["attendance"] : null;
$otherInvitationsTable = isset($variables["other_invitations"]) ? $variables["other_invitations"] : null;
/*ED150409*/
$permission_group_id = isset($variables["permission_group_id"]) ? $variables["permission_group_id"] : false;

if ($attendance != null) {
	echo '<br>' . $attendance;
}
?>
<br><b><?php echo lang('CAL_DURATION')?>:</b> <?php echo $duration?><br>
<?php if ($desc) { ?>
<fieldset>
<legend><?php echo lang('CAL_DESCRIPTION')?></legend>
<?php echo $desc; ?>
</fieldset>
<?php } ?>
<?php if ($otherInvitationsTable != null) { ?>
<fieldset>
<legend><?php echo lang('invitations') ?></legend>
<?php echo $otherInvitationsTable; ?>
</fieldset>
<?php } ?>
<?php
/*ED150409*/
if ($permission_group_id) { ?>
<fieldset>
<legend><?php echo lang('permission_group') ?></legend>
<?php 
	$permission_groups = ContactPermissionGroups::getPermissionGroupsByContact(logged_user()->getId());
	foreach($permission_groups as $permission)
		if($permission_group_id == $permission[0]) {
			echo $permission[1];
			break;
		}
	
?>
</fieldset>
<?php } ?>