<?php
chdir($argv[1]);
define("CONSOLE_MODE", true);
define('PUBLIC_FOLDER', 'public');
include "init.php";

session_commit(); // we don't need sessions
@set_time_limit(0); // don't limit execution of cron, if possible
ini_set('memory_limit', '2048M');


try {
	Env::useHelper('permissions');
	
	$user_id = array_var($argv, 2);
	$token = array_var($argv, 3);
	
	// log user in
	$user = Contacts::findById($user_id);
	if(!($user instanceof Contact) || !$user->isValidToken($token)) {
		throw new Exception("Cannot login with user $user_id and token '$token'");
	}

	CompanyWebsite::instance()->setLoggedUser($user, false, false, false);
		
	// save permissions
	$member_id = array_var($argv, 4);
	$permissions_filename = array_var($argv, 5);
	
	$permissions = file_get_contents($permissions_filename);
	
	$member = Members::findById($member_id);
	if ($member instanceof Member) {
		// transaction to save permission tables
		try {
			DB::beginWork();
			$result = save_member_permissions($member, $permissions, true, false, false);
			DB::commit();
		} catch (Exception $e) {
			DB::rollback();
			throw $e;
		}
		
		// transactions to update_sharing table
		$sharingTablecontroller = new SharingTableController();
		if (is_array(array_var($result, 'changed_pgs'))) {
			$perm_array = json_decode($permissions);
			foreach ($perm_array as $pa) {
				if (!$pa->m) $pa->m = $member->getId();
			}
			foreach (array_var($result, 'changed_pgs') as $pg_id) {
				try {
					// create flag for this $pg_id
					DB::beginWork();
					$flag = new SharingTableFlag();
					$flag->setPermissionGroupId($pg_id);
					$flag->setMemberId($member->getId());
					$flag->setPermissionString($permissions);
					$flag->setExecutionDate(DateTimeValueLib::now());
					$flag->setCreatedById(logged_user()->getId());
					$flag->save();
					DB::commit();
					
					// update sharing table
					DB::beginWork();
					$sharingTablecontroller->afterPermissionChanged($pg_id, $perm_array);
					// delete flag
					$flag->delete();
					DB::commit();
					
				} catch (Exception $e) {
					DB::rollback();
					throw $e;
				}
			}
		}
		
		// transaction for the hooks
		try {
			DB::beginWork();
			Hook::fire('after_save_member_permissions', array_var($result, 'member'), array_var($result, 'member'));
			DB::commit();
		} catch (Exception $e) {
			DB::rollback();
			throw $e;
		}
	}
	
	@unlink($permissions_filename);
	
} catch (Exception $e) {
	DB::rollback();
	Logger::log("Error saving permissions: ".$e->getMessage()."\n".$e->getTraceAsString());
}