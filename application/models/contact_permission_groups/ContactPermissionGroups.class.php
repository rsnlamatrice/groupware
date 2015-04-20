<?php

  /**
  * ContactPermissionGroups
  *
  * @author Diego Castiglioni <diego.castiglioni@fengoffice.com>
  */
  class ContactPermissionGroups extends BaseContactPermissionGroups {
    
  	private static $cache = array();
  	
  	static function getPermissionGroupIdsByContactCSV($contact_id, $ignore_context = true) {
 	
  		if (isset(self::$cache[$contact_id])) return self::$cache[$contact_id];
  		 
 		$pg_ids = array();
 		
 		$context_cond = $ignore_context ? "" : " AND p.is_context=0";
  		$res = DB::execute("SELECT cp.permission_group_id as pid FROM ".TABLE_PREFIX."contact_permission_groups cp
				   INNER JOIN ".TABLE_PREFIX."permission_groups p ON p.id=cp.permission_group_id
				   WHERE cp.contact_id=$contact_id $context_cond");
 		$rows = $res->fetchAll();
 		if (is_array($rows)) {
 			foreach ($rows as $pg) $pg_ids[] = $pg['pid'];
 		}
 		
 		$csv_pg_ids = '';
 		if ($pg_ids != null){
 			$csv_pg_ids = implode(',',$pg_ids);
 		}
 		
 		self::$cache[$contact_id] = $csv_pg_ids;
 		
 		return $csv_pg_ids;
 		
  	}
  	
  	
    static function getContextPermissionGroupIdsByContactCSV($contact_id) {
 		
    	$pg_ids = array();
 		$res = DB::execute("SELECT cp.permission_group_id as pid FROM ".TABLE_PREFIX."contact_permission_groups cp
				   INNER JOIN ".TABLE_PREFIX."permission_groups p ON p.id=cp.permission_group_id
				   WHERE cp.contact_id=$contact_id AND p.is_context=1");
 		$rows = $res->fetchAll();
 		if (is_array($rows)) {
 			foreach ($rows as $pg) $pg_ids[] = $pg['pid'];
 		}
 		
 		$csv_pg_ids = $pg_ids != null ? implode(',',$pg_ids) : 0;
 		
 		return $csv_pg_ids;
  	}
      
      /* ED150407
       * returns current user available groups 
       */
      static function getPermissionGroupsByContact($contact_id) {
 	    if (isset(self::$cache['arr_'.$contact_id]))
		  return self::$cache['arr_'.$contact_id];
	    $pg_ids = array();
	    $res = DB::execute("SELECT cp.permission_group_id as pid, p.contact_id, p.name, IF(p.contact_id = $contact_id, -1, 0) as is_you
			       FROM ".TABLE_PREFIX."contact_permission_groups cp
			       INNER JOIN ".TABLE_PREFIX."permission_groups p ON p.id=cp.permission_group_id
			       WHERE cp.contact_id=$contact_id AND p.is_context=0
			       ORDER BY is_you, name");
	    $rows = $res->fetchAll();
	    if (is_array($rows)) {
		    foreach ($rows as $pg)
			$pg_ids[] = array($pg['pid'], $pg['contact_id'] == $contact_id ? 'Vous seul' : $pg['name']);
	    }
	    $pg_ids[] = array(0,'(tous)');
	    
 	    self::$cache['arr_'.$contact_id] = $pg_ids;
		
	    return $pg_ids;
      }
      /* ED150407
       * returns group name
       */
      static function getPermissionGroupName($contact_id, $permission_group_id) {
	    $pg_ids = self::getPermissionGroupsByContact($contact_id);
 	    foreach ($pg_ids as $pg)
		  if($pg[0] == $permission_group_id)
			return $pg[1];
      }
    
  } // ContactPermissionGroups 

?>