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
	$pg_id = array_var($argv, 4);
	$is_guest = array_var($argv, 5);
	$permissions_filename = array_var($argv, 6);
	$sys_permissions_filename = array_var($argv, 7);
	$mod_permissions_filename = array_var($argv, 8);
	$root_permissions_filename = array_var($argv, 9);
	$root_permissions_genid = array_var($argv, 10);
	
	$permissions = file_get_contents($permissions_filename);
	$sys_permissions = json_decode(file_get_contents($sys_permissions_filename), true);
	$mod_permissions = json_decode(file_get_contents($mod_permissions_filename), true);
	$root_permissions = json_decode(file_get_contents($root_permissions_filename), true);
	
	$perms = array(
		'permissions' => $permissions,
		'sys_perm' => $sys_permissions,
		'mod_perm' => $mod_permissions,
		'root_perm' => $root_permissions,
		'root_perm_genid' => $root_permissions_genid,
	);
	
	// save permissions
	try {
		DB::beginWork();
		$result = save_permissions($pg_id, $is_guest, $perms, true, false, false);
		DB::commit();
	} catch (Exception $e) {
		DB::rollback();
		throw $e;
	}
	
	// update sharing table
	try {
		// create flag for this $pg_id
		DB::beginWork();
		$flag = new SharingTableFlag();
		$flag->setPermissionGroupId($pg_id);
		$flag->setMemberId(0);
		$flag->setPermissionString($permissions);
		$flag->setExecutionDate(DateTimeValueLib::now());
		$flag->setCreatedById(logged_user()->getId());
		$flag->save();
		DB::commit();
		
		$root_permissions_sharing_table_add = array();
		$root_permissions_sharing_table_delete = array();
		
		foreach ($root_permissions as $name => $value) {
			if (str_starts_with($name, $rp_genid . 'rg_root_')) {
				$rp_ot = substr($name, strrpos($name, '_')+1);
				
				if (is_numeric($rp_ot) && $rp_ot > 0 && $value == 0) {
					$root_permissions_sharing_table_delete[] = $rp_ot;
				}
				if (!is_numeric($rp_ot) || $rp_ot <= 0 || $value < 1) continue;
				
				$root_permissions_sharing_table_add[] = $rp_ot;
			}
		}
		$rp_info = array('root_permissions_sharing_table_delete' => $root_permissions_sharing_table_delete, 'root_permissions_sharing_table_add' => $root_permissions_sharing_table_add);
		
		// update sharing table
		DB::beginWork();
		$sharingTablecontroller = new SharingTableController();
		$sharingTablecontroller->afterPermissionChanged($pg_id, json_decode($permissions), $rp_info);
		// delete flag
		$flag->delete();
		DB::commit();
		
	} catch (Exception $e) {
		DB::rollback();
		throw $e;
	}
	
	// fire hooks
	try {
		DB::beginWork();
		Hook::fire('after_save_contact_permissions', $pg_id, $pg_id);
		DB::commit();
	} catch (Exception $e) {
		DB::rollback();
		throw $e;
	}
	
	@unlink($permissions_filename);
	@unlink($sys_permissions_filename);
	@unlink($mod_permissions_filename);
	@unlink($root_permissions_filename);
	
} catch (Exception $e) {
	Logger::log("Error saving permissions: ".$e->getMessage()."\n".$e->getTraceAsString());
}