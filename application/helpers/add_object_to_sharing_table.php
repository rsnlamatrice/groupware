<?php
chdir($argv[1]);
define("CONSOLE_MODE", true);
define('PUBLIC_FOLDER', 'public');
include "init.php";

session_commit(); // we don't need sessions
@set_time_limit(0); // don't limit execution of cron, if possible
ini_set('memory_limit', '1024M');

try {
	Env::useHelper('permissions');
	DB::beginWork();
	
	$user_id = array_var($argv, 2);
	$token = array_var($argv, 3);
	
	// log user in
	$user = Contacts::findById($user_id);
	if(!($user instanceof Contact) || !$user->isValidToken($token)) {
		die();
	}
	CompanyWebsite::instance()->setLoggedUser($user, false, false, false);
	
	$ids = array();
	
	// add object to sharing table
	$object_id = array_var($argv, 4);
	if (is_numeric($object_id)) {
		$ids[] = $object_id;
	} else {
		$ids = explode(',', $object_id);
	}
	foreach ($ids as $id) {
		$object = Objects::findObject($id);
		if ($object instanceof ContentDataObject) {
			$object->addToSharingTable();
		}
	}
	
	DB::commit();
} catch (Exception $e) {
	DB::rollback();
	Logger::log("Error saving permissions: ".$e->getMessage()."\n".$e->getTraceAsString());
}