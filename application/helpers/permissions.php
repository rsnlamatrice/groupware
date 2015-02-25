<?php

// Functions that check permissions
// Recomendation: Before changing this, talk with marcos.saiz@fengoffice.com

  	define('ACCESS_LEVEL_READ', 1);
  	define('ACCESS_LEVEL_WRITE', 2);
  	define('ACCESS_LEVEL_DELETE', 3);
  	  	
  	/**
  	 * Returns whether a user can manage security.
  	 *
  	 * @param Contact $user
  	 * @return boolean
  	 */
  	function can_manage_security(Contact $user){
		return SystemPermissions::userHasSystemPermission($user, 'can_manage_security');	
  	}
  	
	/**
  	 * Returns whether a user can manage contacts.
  	 *
  	 * @param Contact $user
  	 * @return boolean
  	 */
  	function can_manage_contacts(Contact $user, $include_groups = true){
  		$can_manage_contacts = false;
		$pg_ids = $user->getPermissionGroupIds();
		if (count($pg_ids) > 0) {
			$pgs = SystemPermissions::findAll(array('conditions' => 'permission_group_id IN ('.implode(',',$pg_ids).')'));
			foreach ($pgs as $pg) {
				if ($pg->getColumnValue('can_manage_contacts')) {
					$can_manage_contacts = true;
					break;
				}
			}
		}
		return $can_manage_contacts;
  	}
  	
  	
	/**
  	 * Returns whether a user can manage time.
  	 *
  	 * @param Contact $user
  	 * @return boolean
  	 */
  	function can_manage_time(Contact $user){
		return SystemPermissions::userHasSystemPermission($user, 'can_manage_time');
  	}
  	
  	/**
  	 * Returns whether a user can add mail accounts.
  	 *
  	 * @param Contact $user
  	 * @return boolean
  	 */
  	function can_add_mail_accounts(Contact $user){
		return SystemPermissions::userHasSystemPermission($user, 'can_add_mail_accounts');
  	}
  	
  	function can_manage_templates(Contact $user) {
		return SystemPermissions::userHasSystemPermission($user, 'can_manage_templates');
  	}
  	
  	function can_manage_dimensions(Contact $user) {
		return SystemPermissions::userHasSystemPermission($user, 'can_manage_dimensions');
  	}
  	function can_manage_dimension_members(Contact $user) {
		return SystemPermissions::userHasSystemPermission($user, 'can_manage_dimension_members');
  	}
  	function can_manage_tasks(Contact $user) {
		return SystemPermissions::userHasSystemPermission($user, 'can_manage_tasks');
  	}
  	function can_task_assignee(Contact $user) {
		return SystemPermissions::userHasSystemPermission($user, 'can_task_assignee');
  	}
  	function can_manage_billing(Contact $user) {
		return SystemPermissions::userHasSystemPermission($user, 'can_manage_billing');
  	}
  	function can_view_billing(Contact $user) {
		return SystemPermissions::userHasSystemPermission($user, 'can_view_billing');
  	}
  	
  	
  	function can_add_timeslots($user, $members) {
  		return (can_manage_time($user) || can_access_pgids($user->getPermissionGroupIds(), $members, Timeslots::instance()->getObjectTypeId(), ACCESS_LEVEL_WRITE));
  	}
  	
  	/**
  	 * Returns whether a user can manage configuration.
  	 *
  	 * @param Contact $user
  	 * @return boolean
  	 */
  	function can_manage_configuration(Contact $user){
		return SystemPermissions::userHasSystemPermission($user, 'can_manage_configuration');
  	}

  	
  	function can_manage_tabs(Contact $user){
		return $user->isAdminGroup();
  	}
  	
  	function can_manage_plugins(Contact $user){
		return $user->isAdminGroup();
  	}
  	

  	
	
	/**
	 * Return true if $user can add an object of type $object_type_id in $member. False otherwise.
	 *
	 * @param Contact $user
	 * @param Member $member
	 * @param array $context_members
	 * @param $object_type_id
	 * @return boolean
	 */
	function can_add_to_member(Contact $user, $member, $context_members, $object_type_id, $check_dimension = true){
		if(TemplateTasks::instance()->getObjectTypeId() == $object_type_id){
			$object_type_id = ProjectTasks::instance()->getObjectTypeId();
		}
		if(TemplateMilestones::instance()->getObjectTypeId() == $object_type_id){
			$object_type_id = ProjectMilestones::instance()->getObjectTypeId();
		}
		if (!$member instanceof Member && is_array($member) && isset($member['id'])) {
			$member = Members::findById($member['id']);
		}
		
		if ( $user->isGuest() || !$member || !$member->canContainObject($object_type_id)) {
			return false;	
		}
		try {
			
			$contact_pg_ids = ContactPermissionGroups::getPermissionGroupIdsByContactCSV($user->getId(),false);

			if ($check_dimension) $dimension = $member->getDimension();
			
			//dimension does not define permissions - user can freely add in all members
			if ($check_dimension && !$dimension->getDefinesPermissions()) return true;
			
			//dimension defines permissions and user has maximum level of permissions so can freely in all members
			if ($check_dimension && $dimension->hasAllowAllForContact($contact_pg_ids)) return true;
			
			//check
			if (ContactMemberPermissions::contactCanReadObjectTypeinMember($contact_pg_ids, $member->getId(), $object_type_id, true, false, $user)) {
				return true;
			}
			//check for context permissions that allow user to add in this member
			if ($context_members){
				$member_ids = array();
				foreach ($context_members as $member_obj) $member_ids[] = $member_obj->getId();
				$allowed_members = ContactMemberPermissions::getActiveContextPermissions($user,$object_type_id, $context_members, $member_ids, true);
				if (in_array($member, $allowed_members)) return true;
			}	
			
		}
		catch(Exception $e) {
			tpl_assign('error', $e);
			return false;
		}
		return false;
	}
	
	
	/**
	 * Return true if $user can add an object of type $object_type_id in $member. False otherwise.
	 *
	 * @param Contact $user
	 * @param array $context
	 * @param $object_type_id
	 * @return boolean
	 */
	function can_add(Contact $user, $context, $object_type_id, &$notAllowedMember = ''){
		if ($user->isGuest()) return false;
		$membersInContext = 0;
		$can_add = false;
		$required_dimensions_ids = DimensionObjectTypeContents::getRequiredDimensions($object_type_id);
		$dimensions_in_context = array();
		
		$no_required_dimensions = count($required_dimensions_ids) == 0; 
		
		foreach ($required_dimensions_ids as $id){
			$dimensions_in_context[$id]= false;
		}
		
		$contact_pg_ids = ContactPermissionGroups::getPermissionGroupIdsByContactCSV($user->getId(),false);
		if (is_array($context)) {
			foreach($context as $selection){
				$sel_dimension = $selection instanceof Dimension ? $selection : ($selection instanceof Member ? $selection->getDimension() : null);
				if ($sel_dimension instanceof Dimension && $sel_dimension->getOptions(1) && isset($sel_dimension->getOptions(1)->hidden) && $sel_dimension->getOptions(1)->hidden ) continue;
				//$can_add = false;
				if ($selection instanceof Member){
					$membersInContext++;
					if (can_add_to_member($user, $selection, $context, $object_type_id)){
						//if ($no_required_dimensions) return true;
						$dimension_id = $selection->getDimensionId();
						$can_add = true;
						$dimensions_in_context[$dimension_id]=true;
					}else{
						$notAllowedMember = $selection->getName();
						return false;
					}
				}
	
				// Revoke explicty permission
				if ($can_add && !$no_required_dimensions){
					foreach ($dimensions_in_context as $key=>$value){
						$dim = Dimensions::getDimensionById($key);
						if(!$value && $dim->getDefinesPermissions() && $dim->deniesAllForContact($contact_pg_ids)){
							$can_add = false;
						}
					}
				}
			}
		}
		
		// All dimensions in 'all'.
		// If The object has no required dimensions, and no dimensions are selected: check for contact_member_permissions with member_id=0
		if ($no_required_dimensions && $membersInContext == 0) {
			$mailot = ObjectTypes::findByName('mail');
			if ($mailot instanceof ObjectType && $mailot->getId() == $object_type_id) {
				$can_add = true;
			} else {
				$can_add = false;
				if (config_option('let_users_create_objects_in_root') && $contact_pg_ids != '' && ($user->isAdminGroup() || $user->isExecutive() || $user->isManager())) {
					$cmp = ContactMemberPermissions::findOne(array('conditions' => 'member_id=0 AND object_type_id='.$object_type_id.' AND permission_group_id IN ('.$contact_pg_ids.')'));
					$can_add = $cmp instanceof ContactMemberPermission && $cmp->getCanWrite();
				}
			}
		}
		
		// All dimensions in 'all'.
		// if there are required dimensions and no members selected then show correct error message.
		if (!$no_required_dimensions && $membersInContext == 0 && !$can_add) {
			$dim_names = array();
			$required_dimensions = Dimensions::findAll(array('conditions' => 'id IN ('.implode(',',$required_dimensions_ids).')'));
			foreach ($required_dimensions as $dim) {
				$dim_names[] = $dim->getName();
			}
			$notAllowedMember = "-- req dim --".implode(",",$dim_names);
		}
		
		return $can_add;
	}
	
	
	/**
	 * Return true is $user can read the $object. False otherwise.
	 *
	 * @param Contact $user
	 * @param Member $member
	 * @param array $context_members
	 * @param $object_type_id
	 * @return boolean
	 */
	function can_read(Contact $user, $members, $object_type_id){
		return can_access($user, $members, $object_type_id, ACCESS_LEVEL_READ);
	}
	
	/**
	 * Return true is $user can read the $object. False otherwise.
	 * Query executed in sharing table
	 *
	 * @param Contact $user
	 * @param $object_id
	 * @return boolean
	 */
	function can_read_sharing_table(Contact $user, $object_id) {
		if($user->isAdministrator()){
			return true;
		}
		$perm = SharingTables::instance()->findOne(array('conditions' => array("object_id=? AND group_id IN (SELECT permission_group_id FROM ".TABLE_PREFIX."contact_permission_groups WHERE contact_id = '".$user->getId()."')", $object_id)));
		return !is_null($perm);
	}
	
	/**
	 * Return true if $user can write the object of $object_type_id. False otherwise.
	 *
	 * @param Contact $user
	 * @param Member $member
	 * @param array $context_members
	 * @param $object_type_id
	 * @return boolean
	 */
	function can_write(Contact $user, $members, $object_type_id){
		if ($user->isGuest()) return false;
		return can_access($user, $members, $object_type_id, ACCESS_LEVEL_WRITE);
	}
	
	
		
	/**
	 * Return true is $user can delete an $object. False otherwise.
	 *
	 * @param Contact $user
	 * @param array $members
	 * @param $object_type_id
	 * @return boolean
	 */
	function can_delete(Contact $user, $members, $object_type_id){
		if ($user->isGuest()) return false;
		return can_access($user, $members, $object_type_id, ACCESS_LEVEL_DELETE);
	}
	
	
	/**
	 * Return true is $user can access an $object. False otherwise.
	 *
	 * @param Contact $user
	 * @param array $members
	 * @param $object_type_id
	 * @return boolean
	 */
	function can_access(Contact $user, $members, $object_type_id, $access_level){
		if($user->isAdministrator()){
			return true;
		}
		$write = $access_level == ACCESS_LEVEL_WRITE;
		$delete = $access_level == ACCESS_LEVEL_DELETE;
		
		if (($user->isGuest() && $access_level!= ACCESS_LEVEL_READ)) return false;
		
		try {
			$contact_pg_ids = ContactPermissionGroups::getPermissionGroupIdsByContactCSV($user->getId(),false);
			$allow_all_cache = array();
			$dimension_query_methods = array();

			// if no manageable member then check if user has permissions wihout classifying 
			$manageable_members = array();
			foreach ($members as $mem) {
				if ($mem instanceof Member && $mem->getDimension()->getIsManageable() && $mem->getDimension()->getDefinesPermissions()) $manageable_members[] = $mem->getId();
			}
			if (count($manageable_members) == 0) {
				$return = false;
				if (config_option('let_users_create_objects_in_root') && $contact_pg_ids != "" && ($user->isAdminGroup() || $user->isExecutive() || $user->isManager())) {
					$cond = $delete ? 'AND can_delete = 1' : ($write ? 'AND can_write = 1' : '');
					$cmp = ContactMemberPermissions::findOne(array('conditions' => "member_id=0 AND object_type_id=$object_type_id AND permission_group_id IN ($contact_pg_ids) $cond"));
					$return = $cmp instanceof ContactMemberPermission;
				}
				return $return;
			}
			
			$dimension_permissions = array();
			foreach($members as $k => $m){
				if (!$m instanceof Member) {
					unset($members[$k]);
					continue;
				}
				
				$dimension = $m->getDimension();
				if(!$dimension->getDefinesPermissions()){
					continue;
				}
				$dimension_id = $dimension->getId();
				if (!isset($dimension_permissions[$dimension_id])) {
					$dimension_permissions[$dimension_id]=false;
				}
										
				if (!$dimension_permissions[$dimension_id]){
					if ($m->canContainObject($object_type_id)){
						
						if (!isset($dimension_query_methods[$dimension->getId()])) {
							$dimension_query_methods[$dimension->getId()] = $dimension->getPermissionQueryMethod();
						}
						
						//dimension defines permissions and user has maximum level of permissions
						if (isset($allow_all_cache[$dimension_id])) {
							$allow_all = $allow_all_cache[$dimension_id];
						} else {
							$allow_all = $dimension->hasAllowAllForContact($contact_pg_ids);
							$allow_all_cache[$dimension_id] = $allow_all;
						}
						if ($allow_all) {
							$dimension_permissions[$dimension_id]=true;
						}
						
						//check individual members
						if (!$dimension_permissions[$dimension_id] && ContactMemberPermissions::contactCanReadObjectTypeinMember($contact_pg_ids, $m->getId(), $object_type_id, $write, $delete, $user)){
							$dimension_permissions[$dimension_id]=true;
						}
					} else {
						unset($dimension_permissions[$dimension_id]);
					}
				}
			}

			$allowed = true;
			// check that user has permissions in all mandatory query method dimensions
			$mandatory_count = 0;
			foreach ($dimension_query_methods as $dim_id => $qmethod) {
				if ($qmethod == DIMENSION_PERMISSION_QUERY_METHOD_MANDATORY) {
					$mandatory_count++;
					if (!array_var($dimension_permissions, $dim_id)) {
						// if one of the members belong to a mandatory dimension and user does not have permissions on it then return false
						return false;
					}
				}
			}
			
			// If no members in mandatory dimensions then check for not mandatory ones 
			if ($allowed && $mandatory_count == 0) {
				foreach ($dimension_query_methods as $dim_id => $qmethod) {
					if ($qmethod == DIMENSION_PERMISSION_QUERY_METHOD_NOT_MANDATORY) {
						if (array_var($dimension_permissions, $dim_id)) {
							// if has permissions over any member of a non mandatory dimension then return true
							return true;
						} else {
							$allowed = false;
						}
					}
				}
			}

			if ($allowed && count($dimension_permissions)) {
				return true;	
			}
			
			// Si hasta aca tienen perm en todas las dim, return true. Si hay alguna que no tiene perm sigo
			
			//Check Context Permissions
			$member_ids = array();
			foreach ($members as $member_obj) $member_ids[] = $member_obj->getId();
			$allowed_members = ContactMemberPermissions::getActiveContextPermissions($user, $object_type_id, $members, $member_ids, $write, $delete);
			$count=0;
			foreach($members as $m){
				$count++;
				if (!in_array($m->getId(), $allowed_members)) return false;
				else if ($count==count($members)) return true;
			}
			
		}
		catch(Exception $e) {
			tpl_assign('error', $e);
			return false;
		}
		return false;
	}
	
	
		
	
	/**
	 * Return true is $user can access an $object. False otherwise.
	 *
	 * @param Contact $user
	 * @param array $members
	 * @param $object_type_id
	 * @return boolean
	 */
	function can_access_pgids($permission_group_ids, $members, $object_type_id, $access_level){
		$write = $access_level == ACCESS_LEVEL_WRITE;
		$delete = $access_level == ACCESS_LEVEL_DELETE;
		
		try {
			$dimension_query_methods = array();
			$dimension_permissions = array();
			
			$dimension_info = array();
			foreach($members as $k => $m) {
				if (!$m instanceof Member) {
					unset($members[$k]);
					continue;
				}
				if (!isset($dimension_info[$m->getDimensionId()])) {
					$dimension_info[$m->getDimensionId()] = array('dim' => $m->getDimension(), 'members' => array($m->getId() => $m));
				} else {
					$dimension_info[$m->getDimensionId()]['members'][$m->getId()] = $m;
				}
			}
			
			foreach ($dimension_info as $did => $info) {
				$dimension = $info['dim'];
				if(!$dimension->getDefinesPermissions()){
					continue;
				}
				
				if (!isset($dimension_query_methods[$dimension->getId()])) {
					$dimension_query_methods[$dimension->getId()] = $dimension->getPermissionQueryMethod();
				}
				
				$dimension_id = $dimension->getId();
				$dimension_permissions[$dimension_id] = array();
				
				//dimension defines permissions and user has maximum level of permissions
				$dimension_permissions[$dimension_id] = array_merge($dimension_permissions[$dimension_id], $dimension->getPermissionGroupsAllowAll($permission_group_ids));
				
				//check
				$dimension_permissions[$dimension_id] = array_merge($dimension_permissions[$dimension_id], 
					ContactMemberPermissions::instance()->canAccessObjectTypeinMembersPermissionGroups($permission_group_ids, array_keys($info['members']), $object_type_id, $write, $delete));
			}
			
			
			$mandatory_dimension_ids = array();
			foreach ($dimension_query_methods as $dim_id => $qmethod) {
				if ($qmethod == DIMENSION_PERMISSION_QUERY_METHOD_MANDATORY) {
					$mandatory_dimension_ids[] = $dim_id;
				}
			}
			// if there are mandatory dimensions involved then intersect the allowed permission groups of each dimension
			if (count($mandatory_dimension_ids) > 0) {
				$first_mdid = array_pop($mandatory_dimension_ids);
				$pgs_accomplishing_mandatory = $dimension_permissions[$first_mdid];
				foreach ($mandatory_dimension_ids as $mdid) {
					$pgs_accomplishing_mandatory = array_intersect($pgs_accomplishing_mandatory, $dimension_permissions[$mdid]);
				}
				
				$all_permission_groups = array_unique($pgs_accomplishing_mandatory);
				
			} else {
				// No mandatory dimensions involved => return all allowed permission groups
				$other_pgs = array();
				foreach ($dimension_query_methods as $dim_id => $qmethod) {
					if ($qmethod == DIMENSION_PERMISSION_QUERY_METHOD_NOT_MANDATORY) {
						$other_pgs = array_merge($other_pgs, $dimension_permissions[$dim_id]);
					}
				}
				
				$all_permission_groups = array_unique($other_pgs);
			}
			
			
			return $all_permission_groups;
		}
		catch(Exception $e) {
			tpl_assign('error', $e);
			return array();
		}
		return array();
	}
	


	function permission_form_parameters($pg_id) {
		$member_permissions = array();		
		$dimensions = array();
		$dims = Dimensions::findAll();
		$members = array();
		$member_types = array();
		$allowed_object_types = array();
		$allowed_object_types_by_member_type[] = array();
		$root_permissions = array();
		
		foreach($dims as $dim) {
			if ($dim->getDefinesPermissions()) {
				$dimensions[] = $dim;
				$root_members = Members::findAll(array('conditions' => array('`dimension_id`=? AND `parent_member_id`=0', $dim->getId()), 'order' => '`name` ASC'));
				foreach ($root_members as $mem) {
					$members[$dim->getId()][] = $mem;
					$members[$dim->getId()] = array_merge($members[$dim->getId()], $mem->getAllChildrenSorted());
				}
				
				$allowed_object_types[$dim->getId()] = array();
				
				$dim_obj_types = $dim->getAllowedObjectTypeContents();
				foreach ($dim_obj_types as $dim_obj_type) {
					
					// To draw a row for each object type of the dimension
					if (!in_array($dim_obj_type->getContentObjectTypeId(), $allowed_object_types[$dim->getId()])) {
						$allowed_object_types[$dim->getId()][] = $dim_obj_type->getContentObjectTypeId();
					}
					
					// To enable or disable object types depending on the selected member
					if (!is_array(array_var($allowed_object_types_by_member_type, $dim_obj_type->getDimensionObjectTypeId()))) {
						$allowed_object_types_by_member_type[$dim_obj_type->getDimensionObjectTypeId()] = array();
					}
					$allowed_object_types_by_member_type[$dim_obj_type->getDimensionObjectTypeId()][] = $dim_obj_type->getContentObjectTypeId();
					
				}
				
				if ($dim->deniesAllForContact($pg_id)) {
					$cmp_count = ContactMemberPermissions::count("`permission_group_id` = $pg_id and member_id in (select m.id from ".TABLE_PREFIX."members m where m.dimension_id=".$dim->getId().")");
					if ($cmp_count > 0) {
						$dim->setContactDimensionPermission($pg_id, 'check');
					}
				}
				
				if ($dim->hasAllowAllForContact($pg_id)) {
					if (isset($members[$dim->getId()])) {
						foreach ($members[$dim->getId()] as $mem) {
							$member_permissions[$mem->getId()] = array();
							foreach ($dim_obj_types as $dim_obj_type) {
								if ($dim_obj_type->getDimensionObjectTypeId() == $mem->getObjectTypeId()) {
									$member_permissions[$mem->getId()][] = array(
										'o' => $dim_obj_type->getContentObjectTypeId(),
										'w' => 1,
										'd' => 1,
										'r' => 1
									);
								}
							}
						}
					}
				} else if (!$dim->deniesAllForContact($pg_id)) {
					if (isset($members[$dim->getId()])) {
						foreach ($members[$dim->getId()] as $mem) {
							$member_permissions[$mem->getId()] = array();
							$pgs = ContactMemberPermissions::findAll(array("conditions" => array("`permission_group_id` = ? AND `member_id` = ?", $pg_id, $mem->getId())));
							if (is_array($pgs)) {
								foreach ($pgs as $pg) {
									$member_permissions[$mem->getId()][] = array(
										'o' => $pg->getObjectTypeId(),
										'w' => $pg->getCanWrite(),
										'd' => $pg->getCanDelete(),
										'r' => 1
									);
								}
							}
						}
					}
				}
				
				if (isset($members[$dim->getId()])) {
					foreach($members[$dim->getId()] as $member) {
						$member_types[$member->getId()] = $member->getObjectTypeId();
					}
				}
			}
		}
		
		if (config_option('let_users_create_objects_in_root')) {
			$root_cmps = ContactMemberPermissions::findAll(array('conditions' => 'permission_group_id = '.$pg_id.' AND member_id = 0'));
			foreach ($root_cmps as $root_cmp) {
				$root_permissions[$root_cmp->getObjectTypeId()] = array('w' => $root_cmp->getCanWrite(), 'd' => $root_cmp->getCanDelete(), 'r' => 1);
			}
		}
		
		$all_object_types = ObjectTypes::findAll(array("conditions" => "`type` IN ('content_object', 'located') AND name <> 'template_task' AND name <> 'template_milestone'  AND `name` <> 'file revision'"));
		return array(
			'member_types' => $member_types,
			'allowed_object_types_by_member_type' => $allowed_object_types_by_member_type,
			'allowed_object_types' => $allowed_object_types,
			'all_object_types' => $all_object_types,
			'member_permissions' => $member_permissions,
			'dimensions' => $dimensions,
			'root_permissions' => $root_permissions,
		);
	}
	
	
	function save_permissions($pg_id, $is_guest = false, $permissions_data = null, $save_cmps = true, $update_sharing_table = true, $fire_hook = true) {
	
		if (is_null($permissions_data)) {
			
			// system permissions
			$sys_permissions_data = array_var($_POST, 'sys_perm');
			// module permissions
			$mod_permissions_data = array_var($_POST, 'mod_perm');
			// root permissions
			if ($rp_genid = array_var($_POST, 'root_perm_genid')) {
				$rp_permissions_data = array();
				foreach ($_POST as $name => $value) {
					if (str_starts_with($name, $rp_genid . 'rg_root_')) {
						$rp_permissions_data[$name] = $value;
					}
				}
			}
			// member permissions
			$permissionsString = array_var($_POST, 'permissions');
			
		} else {
			
			// system permissions
			$sys_permissions_data = array_var($permissions_data, 'sys_perm');
			// module permissions
			$mod_permissions_data = array_var($permissions_data, 'mod_perm');
			// root permissions
			$rp_genid = array_var($permissions_data, 'root_perm_genid');
			$rp_permissions_data = array_var($permissions_data, 'root_perm');
			// member permissions
			$permissionsString = array_var($permissions_data, 'permissions');
			
		}
		
		$changed_members = array();
				
		// save module permissions
		try {
			DB::beginWork();
			TabPanelPermissions::clearByPermissionGroup($pg_id);
			if (!is_null($mod_permissions_data) && is_array($mod_permissions_data)) {
				foreach($mod_permissions_data as $tab_id => $val) {
					$tpp = new TabPanelPermission();
					$tpp->setPermissionGroupId($pg_id);
					$tpp->setTabPanelId($tab_id);
					$tpp->save();
				}
			}
			DB::commit();
		} catch (Exception $e) {
			DB::rollback();
			Logger::log("Error saving module permissions for permission group $pg_id: ".$e->getMessage()."\n".$e->getTraceAsString());
			throw $e;
		}
		
		$root_permissions_sharing_table_delete = array();
		$root_permissions_sharing_table_add = array();
		if (logged_user() instanceof Contact && can_manage_security(logged_user()) && logged_user()->isAdminGroup()) {
			try {
				DB::beginWork();
				
				// save system permissions
				$system_permissions = SystemPermissions::findById($pg_id);
				if (!$system_permissions instanceof SystemPermission) {
					$system_permissions = new SystemPermission();
					$system_permissions->setPermissionGroupId($pg_id);
				}
				$system_permissions->setAllPermissions(false);
				$other_permissions = array();
				Hook::fire('add_user_permissions', $pg_id, $other_permissions);
				foreach ($other_permissions as $k => $v) {
					$system_permissions->setColumnValue($k, false);
				}
				
				$sys_permissions_data['can_task_assignee'] = !$is_guest;
				$system_permissions->setFromAttributes($sys_permissions_data);
				$system_permissions->setUseOnDuplicateKeyWhenInsert(true);
				$system_permissions->save();
				
				//object type root permissions
				if ($rp_genid) {
					ContactMemberPermissions::delete("permission_group_id = $pg_id AND member_id = 0");
					foreach ($rp_permissions_data as $name => $value) {
						if (str_starts_with($name, $rp_genid . 'rg_root_')) {
							$rp_ot = substr($name, strrpos($name, '_')+1);
							
							if (is_numeric($rp_ot) && $rp_ot > 0 && $value == 0) {
								$root_permissions_sharing_table_delete[] = $rp_ot;
							}
							if (!is_numeric($rp_ot) || $rp_ot <= 0 || $value < 1) continue;
							
							$root_permissions_sharing_table_add[] = $rp_ot;
							
							// save with member_id = 0
							$root_perm_cmp = new ContactMemberPermission();
							$root_perm_cmp->setPermissionGroupId($pg_id);
							$root_perm_cmp->setMemberId('0');
							$root_perm_cmp->setObjectTypeId($rp_ot);
							$root_perm_cmp->setCanWrite($value >= 2);
							$root_perm_cmp->setCanDelete($value >= 3);
							$root_perm_cmp->save();
						}
					}
				}
				DB::commit();
			} catch (Exception $e) {
				DB::rollback();
				Logger::log("Error saving system permissions for permission group $pg_id: ".$e->getMessage()."\n".$e->getTraceAsString());
				throw $e;
			}
		}
		//member permissions
		if ($permissionsString && $permissionsString != ''){
			$permissions = json_decode($permissionsString);
		}
		
		if (isset($permissions) && !is_null($permissions) && is_array($permissions)) {
			try {
				$allowed_members_ids= array();
				foreach ($permissions as $perm) {
					if (!isset($all_perm_deleted[$perm->m])) $all_perm_deleted[$perm->m] = true;
					$allowed_members_ids[$perm->m]=array();
					$allowed_members_ids[$perm->m]['pg']=$pg_id;
					if ($save_cmps) {
						$cmp = ContactMemberPermissions::findById(array('permission_group_id' => $pg_id, 'member_id' => $perm->m, 'object_type_id' => $perm->o));
						if (!$cmp instanceof ContactMemberPermission) {
							$cmp = new ContactMemberPermission();
							$cmp->setPermissionGroupId($pg_id);
							$cmp->setMemberId($perm->m);
							$cmp->setObjectTypeId($perm->o);
						}
						$cmp->setCanWrite($is_guest ? false : $perm->w);
						$cmp->setCanDelete($is_guest ? false : $perm->d);
					}
					if ($perm->r) {
						if(isset($allowed_members_ids[$perm->m]['w'])){
							if($allowed_members_ids[$perm->m]['w']!=1){
								$allowed_members_ids[$perm->m]['w'] = $is_guest ? false : $perm->w;
							}
						}else{
							$allowed_members_ids[$perm->m]['w'] = $is_guest ? false : $perm->w;
						}
						if(isset($allowed_members_ids[$perm->m]['d'])){
							if($allowed_members_ids[$perm->m]['d']!=1){
								$allowed_members_ids[$perm->m]['d'] = $is_guest ? false : $perm->d;
							}
						}else{
							$allowed_members_ids[$perm->m]['d'] = $is_guest ? false : $perm->d;
						}
						if ($save_cmps) $cmp->save();
						$all_perm_deleted[$perm->m] = false;
					} else {
						if ($save_cmps) $cmp->delete();
					}
					
					$changed_members[] = $perm->m;
				}
			} catch (Exception $e) {
				DB::rollback();
				Logger::log("Error saving member permissions for permission group $pg_id: ".$e->getMessage()."\n".$e->getTraceAsString());
				throw $e;
			}
			
			
			if ($update_sharing_table) {
				try {
					$sharingTablecontroller = new SharingTableController();
					$rp_info = array('root_permissions_sharing_table_delete' => $root_permissions_sharing_table_delete, 'root_permissions_sharing_table_add' => $root_permissions_sharing_table_add);
					$sharingTablecontroller->afterPermissionChanged($pg_id, $permissions, $rp_info);
				} catch (Exception $e) {
					Logger::log("Error saving permissions to sharing table for permission group $pg_id: ".$e->getMessage()."\n".$e->getTraceAsString());
					throw $e;
				}
			}
			
			/* cuando saco permisos sobre un tipo de objeto que no saque sobre todos los tipos
			foreach ($allowed_members_ids as $key=>$mids){
				$mbm=Members::findById($key);
				$root_cmp = ContactMemberPermissions::findById(array('permission_group_id' => $mids['pg'], 'member_id' => $key, 'object_type_id' => $mbm->getObjectTypeId()));
				if (!$root_cmp instanceof ContactMemberPermission) {
					$root_cmp = new ContactMemberPermission();
					$root_cmp->setPermissionGroupId($mids['pg']);
					$root_cmp->setMemberId($key);
					$root_cmp->setObjectTypeId($mbm->getObjectTypeId());
				}
				$root_cmp->setCanWrite(array_var($mids,'w'));
				$root_cmp->setCanDelete(array_var($mids,'d'));
				$root_cmp->save();
			}
			
			if (isset($all_perm_deleted)) {
				foreach ($all_perm_deleted as $mid => $pd) {
					if ($pd) {
						ContactMemberPermissions::instance()->delete("`permission_group_id` = $pg_id AND `member_id` = $mid");
					}
				}
			}*/
		}
		
		// set all permissions to read_only if user is guest
		if ($is_guest) {
			try {
				DB::beginWork();
				$all_saved_permissions = ContactMemberPermissions::findAll(array("conditions" => "`permission_group_id` = $pg_id"));
				foreach ($all_saved_permissions as $sp) {/* @var $sp ContactMemberPermission */
					if ($sp->getCanDelete() || $sp->getCanWrite()) {
						$sp->setCanDelete(false);
						$sp->setCanWrite(false);
						$sp->save();
					}
				}
				$cdps = ContactDimensionPermissions::findAll(array("conditions" => "`permission_type` = 'allow all'"));
				foreach ($cdps as $cdp) {
					$cdp->setPermissionType('check');
					$cdp->save();
				}
				DB::commit();
			} catch (Exception $e) {
				DB::rollback();
				Logger::log("Error setting guest user permissions to read_only for permission group $pg_id: ".$e->getMessage()."\n".$e->getTraceAsString());
				throw $e;
			}
		}
		
		// check the status of the changed dimensions to set 'allow_all', 'deny_all' or 'check'
		try {
			DB::beginWork();
			$dimensions = Dimensions::findAll(array("conditions" => array("`id` IN (SELECT DISTINCT `dimension_id` FROM ".Members::instance()->getTableName(true)." WHERE `id` IN (?))", $changed_members)));
			foreach ($dimensions as $dimension) {
				$mem_ids = $dimension->getAllMembers(true);
				if (count($mem_ids) == 0) $mem_ids[] = 0;
				
				$count = ContactMemberPermissions::count(array('conditions' => "`permission_group_id`=$pg_id AND `member_id` IN (".implode(",",$mem_ids).") AND `can_delete` = 0" ));
				if ($count > 0) {
					$dimension->setContactDimensionPermission($pg_id, 'check');
				} else {
					$count = ContactMemberPermissions::count(array('conditions' => "`permission_group_id`=$pg_id AND `member_id` IN (".implode(",",$mem_ids).")"));
					if ($count == 0) {
						$dimension->setContactDimensionPermission($pg_id, 'deny all');
					} else {
						$allow_all = true;
						$dim_obj_types = $dimension->getAllowedObjectTypeContents();
						$members = Members::findAll("`id` IN (".implode(",",$mem_ids).")");
						foreach ($dim_obj_types as $dim_obj_type) {
							
							$mem_ids_for_ot = array();
							foreach($members as $member) {
								if ($dim_obj_type->getDimensionObjectTypeId() == $member->getObjectTypeId()) $mem_ids_for_ot[] = $member->getId();
							}
							if (count($mem_ids_for_ot) == 0) $mem_ids_for_ot[] = 0;
							
							$count = ContactMemberPermissions::count(array('conditions' => "`permission_group_id`=$pg_id AND 
							`object_type_id` = ".$dim_obj_type->getContentObjectTypeId()." AND `can_delete` = 1 AND `member_id` IN (".implode(",",$mem_ids_for_ot).")"));
							
							if ($count != count($mem_ids_for_ot)) {
								$allow_all = false;
								break;
							}
						}
						if ($allow_all) {
							$dimension->setContactDimensionPermission($pg_id, 'allow all');
						} else {
							$dimension->setContactDimensionPermission($pg_id, 'check');
						}
					}
				}
			}
			DB::commit();
		} catch (Exception $e) {
			DB::rollback();
			Logger::log("Error setting dimension permissions for permission group $pg_id: ".$e->getMessage()."\n".$e->getTraceAsString());
			throw $e;
		}
		
		if ($fire_hook) {
			Hook::fire('after_save_contact_permissions', $pg_id, $pg_id);
		}
	}
	
	
	
	function permission_member_form_parameters($member = null, $dimension_id = null) {
		
		if ( $member ) {
			$dim = $member->getDimension();
		}elseif (array_var( $_REQUEST,'dim_id')) {
			$dim = Dimensions::getDimensionById(array_var( $_REQUEST,'dim_id'));
		}elseif (!is_null($dimension_id)) {
			$dim = Dimensions::getDimensionById($dimension_id);
		}
		
		if (!$dim instanceof Dimension) {
			Logger::log("Invalid dimension: " . ($member instanceof Member ? " for member ".$member->getId() : "request: ".print_r($_REQUEST, 1)));
			throw new Exception("Invalid dimension");
		}
		
		if (logged_user()->isMemberOfOwnerCompany()) {
			$companies = Contacts::findAll(array("conditions" => "is_company = 1 AND object_id IN (SELECT company_id FROM ".TABLE_PREFIX."contacts WHERE user_type>0 AND disabled=0)", 'order' => 'first_name'));
		} else {
			$companies = array(owner_company());
			if (logged_user()->getCompany() instanceof Contact) $companies[] = logged_user()->getCompany();
		}
		
		$allowed_object_types = array();
		$dim_obj_types = $dim->getAllowedObjectTypeContents();
		foreach ($dim_obj_types as $dim_obj_type) {
			// To draw a row for each object type of the dimension
			if ( !array_key_exists($dim_obj_type->getContentObjectTypeId(), $allowed_object_types) && (!$member || $dim_obj_type->getDimensionObjectTypeId() == $member->getObjectTypeId()) ) {
				$allowed_object_types[$dim_obj_type->getContentObjectTypeId()] = ObjectTypes::findById($dim_obj_type->getContentObjectTypeId());
				$allowed_object_types_json[] = $dim_obj_type->getContentObjectTypeId();
			}
		}
		
		$permission_groups = array();
		foreach ($companies as $company) {
			$users = $company->getUsersByCompany();
			foreach ($users as $u) $permission_groups[] = $u->getPermissionGroupId();
		}
		
		$no_company_users = Contacts::getAllUsers("AND `company_id` = 0", true);
		foreach ($no_company_users as $noc_user) {
			$permission_groups[] = $noc_user->getPermissionGroupId();
		}
		
		$non_personal_groups = PermissionGroups::getNonRolePermissionGroups();
		foreach ($non_personal_groups as $group) {
			$permission_groups[] = $group->getId();
		}
		
		foreach ($permission_groups as $pg_id) {
			if ($dim->hasAllowAllForContact($pg_id)) {
				$member_permissions[$pg_id] = array();
				foreach ($dim_obj_types as $dim_obj_type) {
					if ($member && $dim_obj_type->getDimensionObjectTypeId() == $member->getObjectTypeId()) {
						$member_permissions[$pg_id][] = array(
							'o' => $dim_obj_type->getContentObjectTypeId(),
							'w' => 1,
							'd' => 1,
							'r' => 1
						);
					}elseif(!$member){
						// WHEN CREATING a new member dont allow any user 
						$member_permissions[$pg_id][] = array(
							'o' => $dim_obj_type->getContentObjectTypeId(),
							'w' => 0,
							'd' => 0,
							'r' => 0
						);
					}
				}
			} else if (!$dim->deniesAllForContact($pg_id)) {
				$member_permissions[$pg_id] = array();
				if ($member) {
					$mpgs = ContactMemberPermissions::findAll(array("conditions" => array("`permission_group_id` = ? AND `member_id` = ?", $pg_id, $member->getId())));
					if (is_array($mpgs)) {
						foreach ($mpgs as $mpg) {
							$member_permissions[$mpg->getPermissionGroupId()][] = array(
								'o' => $mpg->getObjectTypeId(),
								'w' => $mpg->getCanWrite() ? 1 : 0,
								'd' => $mpg->getCanDelete() ? 1 : 0,
								'r' => 1
							);
						}
					}
				}
			}
		}
		
		return array(
			'member' => $member,
			'allowed_object_types' => $allowed_object_types,
			'allowed_object_types_json' => $allowed_object_types_json,
			'permission_groups' => $permission_groups,
			'member_permissions' => isset($member_permissions) ? $member_permissions : array(),
		);
	}
	
	function save_member_permissions($member, $permissionsString = null, $save_cmps = true, $update_sharing_table = true, $fire_hook = true) {
		@set_time_limit(0);
		ini_set('memory_limit', '1024M');

		if (!$member instanceof Member) return;
		if (is_null($permissionsString)) {
			$permissionsString = array_var($_POST, 'permissions');
		}
		if ($permissionsString && $permissionsString != ''){
			$permissions = json_decode($permissionsString);
		}
		
		$sharingTablecontroller = new SharingTableController();
		$changed_pgs = array();
		
		
		if (isset($permissions) && is_array($permissions)) {
			
			$allowed_pg_ids= array();
			foreach ($permissions as &$perm) {
				if ($save_cmps) {
					$cmp = ContactMemberPermissions::findById(array('permission_group_id' => $perm->pg, 'member_id' => $member->getId(), 'object_type_id' => $perm->o));
					if (!$cmp instanceof ContactMemberPermission) {
						$cmp = new ContactMemberPermission();
						$cmp->setPermissionGroupId($perm->pg);
						$cmp->setMemberId($member->getId());
						$cmp->setObjectTypeId($perm->o);
					}
					$cmp->setCanWrite($perm->w);
					$cmp->setCanDelete($perm->d);
				}
				if ($perm->r) {
					$allowed_pg_ids[$perm->pg]=array();
					if(isset($allowed_pg_ids[$perm->pg]['w'])){
						if(!$allowed_pg_ids[$perm->pg]['w']){
							$allowed_pg_ids[$perm->pg]['w']=$perm->w;
						}
					}else{
						$allowed_pg_ids[$perm->pg]['w']=$perm->w;
					}
					if(isset($allowed_pg_ids[$perm->pg]['d'])){
						if(!$allowed_pg_ids[$perm->pg]['d']){
							$allowed_pg_ids[$perm->pg]['d']=$perm->d;
						}
					}else{
						$allowed_pg_ids[$perm->pg]['d']=$perm->d;
					}

					if ($save_cmps) {
						$cmp->setUseOnDuplicateKeyWhenInsert(true);
						$cmp->save();
					}
				} else {
					if ($save_cmps) $cmp->delete();
				}
				
				$perm->m = $member->getId();
				$changed_pgs[$perm->pg] = $perm->pg;
			}
			
			foreach ($permissions as $p) {
				if (!$p->m) $p->m = $member->getId();
			}
			if ($update_sharing_table) {
				foreach ($changed_pgs as $pg_id) {
					$sharingTablecontroller->afterPermissionChanged($pg_id, $permissions);
				}
			}
			
			
			foreach ($allowed_pg_ids as $key=>$mids){
				$root_cmp = ContactMemberPermissions::findById(array('permission_group_id' => $key, 'member_id' => $member->getId(), 'object_type_id' => $member->getObjectTypeId()));
				if (!$root_cmp instanceof ContactMemberPermission) {
					$root_cmp = new ContactMemberPermission();
					$root_cmp->setPermissionGroupId($key);
					$root_cmp->setMemberId($member->getId());
					$root_cmp->setObjectTypeId($member->getObjectTypeId());
				}
				$root_cmp->setCanWrite($mids['w']==true ? 1 : 0);
				$root_cmp->setCanDelete($mids['d']==true ? 1 : 0);
				$root_cmp->save();
				
			}
		}
		
		// check the status of the dimension to set 'allow_all', 'deny_all' or 'check'
		$dimension = $member->getDimension();
		$mem_ids = $dimension->getAllMembers(true);
		if (count($mem_ids) == 0) $mem_ids[] = 0;
		
		foreach ($changed_pgs as $pg_id) {
			$count = ContactMemberPermissions::count(array('conditions' => "`permission_group_id`=$pg_id AND `member_id` IN (".implode(",",$mem_ids).") AND `can_delete` = 0" ));
			if ($count > 0) {
				$dimension->setContactDimensionPermission($pg_id, 'check');
			} else {
				$count = ContactMemberPermissions::count(array('conditions' => "`permission_group_id`=$pg_id AND `member_id` IN (".implode(",",$mem_ids).")"));
				if ($count == 0) {
					$dimension->setContactDimensionPermission($pg_id, 'deny all');
				} else {
					$allow_all = true;
					$dim_obj_types = $dimension->getAllowedObjectTypeContents();
					$members = Members::findAll("`id` IN (".implode(",",$mem_ids).")");
					foreach ($dim_obj_types as $dim_obj_type) {
						
						$mem_ids_for_ot = array();
						foreach($members as $member_aux) {
							if ($dim_obj_type->getDimensionObjectTypeId() == $member_aux->getObjectTypeId()) $mem_ids_for_ot[] = $member_aux->getId();
						}
						if (count($mem_ids_for_ot) == 0) $mem_ids_for_ot[] = 0;
						
						$count = ContactMemberPermissions::count(array('conditions' => "`permission_group_id`=$pg_id AND 
						`object_type_id` = ".$dim_obj_type->getContentObjectTypeId()." AND `can_delete` = 1 AND `member_id` IN (".implode(",",$mem_ids_for_ot).")"));
						
						if ($count != count($mem_ids_for_ot)) {
							$allow_all = false;
							break;
						}
					}
					if ($allow_all) {
						$dimension->setContactDimensionPermission($pg_id, 'allow all');
					} else {
						$dimension->setContactDimensionPermission($pg_id, 'check');
					}
				}
			}
		}
		
		if ($fire_hook) {
			Hook::fire('after_save_member_permissions', $member, $member);
		}
		
		return array('changed_pgs' => $changed_pgs, 'member' => $member);
	}

	/**
	 * Returns the users with permissions for the object type $object_type for the context $context
	 * 
	 * @param $object_type_id Object Type
	 * @param $context Context
	 * @param $access_level (ACCESS_LEVEL_READ, ACCESS_LEVEL_WRITE, ACCESS_LEVEL_DELETE)
	 * @param $extra_conditions Extra conditions to add to the users query
	 * @param $to_assign true if this function is called to fill the "assigned to" combobox when editing a task
	 */
	function allowed_users_in_context($object_type_id, $context = null, $access_level = ACCESS_LEVEL_READ, $extra_conditions = "", $for_tasks_filter = false) {
		$result = array();
		
		$members = array();
		if (isset($context) && is_array($context)) {
			foreach ($context as $selection) {
				if ($selection instanceof Member && $selection->getDimension()->getDefinesPermissions()) {
					$members[] = $selection;
				}
			}
		}
		$zero_members = false;
		if (count($members) == 0) {
			$zero_members = true;
			$logged_user_pgs = logged_user()->getPermissionGroupIds();
			if (count($logged_user_pgs) > 0) {
				$dimensions = Dimensions::getAllowedDimensions($object_type_id);
				foreach ($dimensions as $d) {
					$dim = Dimensions::getDimensionById(array_var($d, 'dimension_id'));
					if ($dim instanceof Dimension && $dim->getDefinesPermissions() && $dim->getCode() != 'feng_persons' && $dim->getCode() != 'feng_users') {
						if ($dim->hasAllowAllForContact(implode(",",$logged_user_pgs))) {
							$permission_conditions = "";
						} else {
							$permission_conditions = " AND EXISTS (SELECT cmp.permission_group_id FROM ".TABLE_PREFIX."contact_member_permissions cmp 
								WHERE cmp.permission_group_id IN (".implode(",",$logged_user_pgs).") AND cmp.member_id=".TABLE_PREFIX."members.id AND cmp.object_type_id=$object_type_id)";
						}
						$members = array_merge($members, $dim->getAllMembers(false, null, true, $permission_conditions));
					}
				}
			}
		}
		
		$all_permission_groups = array();
		$rows = DB::executeAll("SELECT DISTINCT permission_group_id FROM ".TABLE_PREFIX."contact_permission_groups");
		foreach ($rows as $row) {
			$all_permission_groups[] = $row['permission_group_id'];
		}
		
		if ($zero_members && $for_tasks_filter) {
			$allowed_permission_groups = get_user_pgs_with_permissions_in_my_members($object_type_id);
		} else {
			if ($zero_members && config_option('let_users_create_objects_in_root') && (logged_user()->isAdminGroup() || logged_user()->isExecutive() || logged_user()->isManager())) {
				$allowed_permission_groups = array_flat(DB::executeAll("SELECT permission_group_id FROM ".TABLE_PREFIX."contact_member_permissions WHERE member_id=0 AND object_type_id=$object_type_id"));
			} else {
				$allowed_permission_groups = can_access_pgids($all_permission_groups, $members, $object_type_id, $access_level);
			}
		}
		
		foreach ($allowed_permission_groups as $k => &$apg) {
			if (trim($apg) == '') unset($allowed_permission_groups[$k]);
		}
		if (count($allowed_permission_groups) > 0) {
			$isSuperAdmin = " OR user_type IN (SELECT id FROM ".TABLE_PREFIX."permission_groups WHERE type='roles' AND name = 'Super Administrator')";
			$result = Contacts::instance()->findAll(array(
				'conditions' => "disabled=0 AND (
											id IN (SELECT DISTINCT contact_id FROM ".TABLE_PREFIX."contact_permission_groups
											WHERE permission_group_id IN (".implode(",",$allowed_permission_groups).") 
											$isSuperAdmin
											)
								$extra_conditions)",
				'order' => 'name'));			
		}
		
		return $result;
	}
	

	
	function save_member_permissions_background($user, $member, $permissions) {
		if (!defined('DONT_SAVE_PERMISSIONS_IN_BACKGROUND')) define('DONT_SAVE_PERMISSIONS_IN_BACKGROUND', !is_exec_available());
		
		if (substr(php_uname(), 0, 7) == "Windows" || (defined('DONT_SAVE_PERMISSIONS_IN_BACKGROUND') && DONT_SAVE_PERMISSIONS_IN_BACKGROUND)){
			//pclose(popen("start /B ". $command, "r"));
			save_member_permissions($member, $permissions);
		} else {

			// populate permission groups
			$permissions_decoded = json_decode($permissions);
			
			$to_insert = array();
			$to_delete = array();
			if (is_array($permissions_decoded)) {
				foreach ($permissions_decoded as $perm) {
					if ($perm->r) {
						$to_insert[] = "('".$perm->pg."','".$member->getId()."','".$perm->o."','".$perm->d."','".$perm->w."')";
					} else {
						$to_delete[] = "(permission_group_id='".$perm->pg."' AND member_id='".$member->getId()."' AND object_type_id='".$perm->o."')";
					}
				}
				if (count($to_insert) > 0) {
					$values = implode(',', $to_insert);
					DB::execute("INSERT INTO ".TABLE_PREFIX."contact_member_permissions (permission_group_id,member_id,object_type_id,can_delete,can_write)
					 VALUES $values ON DUPLICATE KEY UPDATE member_id=member_id");
				}
				if (count($to_delete) > 0) {
					$where = implode(' OR ', $to_delete);
					DB::execute("DELETE FROM ".TABLE_PREFIX."contact_member_permissions WHERE $where;");
				}
			}
			// save permissions in background
			$perm_filename = ROOT ."/tmp/perm_".gen_id();
			file_put_contents($perm_filename, $permissions);
			
			$command = "nice -n19 php ". ROOT . "/application/helpers/save_member_permissions.php ".ROOT." ".$user->getId()." ".$user->getTwistedToken()." ".$member->getId()." $perm_filename";
			exec("$command > /dev/null &");
		}
	}
	
	
	
	function get_user_pgs_with_permissions_in_my_members($object_type_id, $user = null) {
		if ($object_type_id <= 0) {
			return array();
		}
		if (is_null($user) || !$user instanceof Contact || !$user->isUser()) {
			$user = logged_user();
		}
		
		$sql = "select distinct(cmp.permission_group_id) from ".TABLE_PREFIX."contact_member_permissions cmp
			inner join ".TABLE_PREFIX."permission_groups pg on pg.id=cmp.permission_group_id
			where pg.type in ('permission_groups','user_groups') and cmp.object_type_id='$object_type_id' and member_id in (
			  select distinct(cmp2.member_id) from ".TABLE_PREFIX."contact_member_permissions cmp2 where cmp2.permission_group_id in (
			    select cpg2.permission_group_id from ".TABLE_PREFIX."contact_permission_groups cpg2 where cpg2.contact_id=".$user->getId()."
			  )
			)";
		return array_flat(DB::executeAll($sql));
	}
	
	
	
	
	function save_user_permissions_background($user, $pg_id, $is_guest=false) {
		if (!defined('DONT_SAVE_PERMISSIONS_IN_BACKGROUND')) define('DONT_SAVE_PERMISSIONS_IN_BACKGROUND', !is_exec_available());
		
		// system permissions
		$sys_permissions_data = array_var($_POST, 'sys_perm');
		// module permissions
		$mod_permissions_data = array_var($_POST, 'mod_perm');
		// root permissions
		$rp_permissions_data = array();
		if ($rp_genid = array_var($_POST, 'root_perm_genid')) {
			foreach ($_POST as $name => $value) {
				if (str_starts_with($name, $rp_genid . 'rg_root_')) {
					$rp_permissions_data[$name] = $value;
				}
			}
		}
		// member permissions
		$permissionsString = array_var($_POST, 'permissions');
		
		
		
		if (substr(php_uname(), 0, 7) == "Windows" || (defined('DONT_SAVE_PERMISSIONS_IN_BACKGROUND') && DONT_SAVE_PERMISSIONS_IN_BACKGROUND)){
			//pclose(popen("start /B ". $command, "r"));
			save_permissions($pg_id, $is_guest);
		} else {

			// populate permission groups
			$permissions_decoded = json_decode($permissionsString);
			$to_insert = array();
			$to_delete = array();
			if (is_array($permissions_decoded)) {
				foreach ($permissions_decoded as $perm) {
					if ($perm->r) {
						$to_insert[] = "('".$pg_id."','".$perm->m."','".$perm->o."','".$perm->d."','".$perm->w."')";
					} else {
						$to_delete[] = "(permission_group_id='".$pg_id."' AND member_id='".$perm->m."' AND object_type_id='".$perm->o."')";
					}
				}
			}
			if (count($to_insert) > 0) {
				$values = implode(',', $to_insert);
				DB::execute("INSERT INTO ".TABLE_PREFIX."contact_member_permissions (permission_group_id,member_id,object_type_id,can_delete,can_write)
				 VALUES $values ON DUPLICATE KEY UPDATE member_id=member_id");
			}
			if (count($to_delete) > 0) {
				$where = implode(' OR ', $to_delete);
				DB::execute("DELETE FROM ".TABLE_PREFIX."contact_member_permissions WHERE $where;");
			}
			
			// save permissions in background
			$perm_filename = ROOT ."/tmp/uperm_".gen_id();
			file_put_contents($perm_filename, $permissionsString);
			
			$sys_filename = ROOT ."/tmp/sys_".gen_id();
			file_put_contents($sys_filename, json_encode($sys_permissions_data));
			
			$mod_filename = ROOT ."/tmp/mod_".gen_id();
			file_put_contents($mod_filename, json_encode($mod_permissions_data));
			
			$rp_filename = ROOT ."/tmp/rp_".gen_id();
			file_put_contents($rp_filename, json_encode($rp_permissions_data));
			
			$is_guest_str = $is_guest ? "1" : "0";
			$command = "nice -n19 php ". ROOT . "/application/helpers/save_user_permissions.php ".ROOT." ".$user->getId()." ".$user->getTwistedToken()." $pg_id $is_guest_str $perm_filename $sys_filename $mod_filename $rp_filename $rp_genid";
			exec("$command > /dev/null &");
		}
	}
	
	
	
	function add_object_to_sharing_table($object, $user) {
		if (!$object instanceof ContentDataObject) return;
		
		if (!defined('DONT_SAVE_PERMISSIONS_IN_BACKGROUND')) define('DONT_SAVE_PERMISSIONS_IN_BACKGROUND', !is_exec_available());
		
		if (substr(php_uname(), 0, 7) == "Windows" || (defined('DONT_SAVE_PERMISSIONS_IN_BACKGROUND') && DONT_SAVE_PERMISSIONS_IN_BACKGROUND)){
			$object->addToSharingTable();
		} else {
			$command = "nice -n19 php ". ROOT . "/application/helpers/add_object_to_sharing_table.php ".ROOT." ".$user->getId()." ".$user->getTwistedToken()." ".$object->getId();
			exec("$command > /dev/null &");
		}
	}
	
	function add_multilple_objects_to_sharing_table($ids_str, $user) {
		if (!defined('DONT_SAVE_PERMISSIONS_IN_BACKGROUND')) define('DONT_SAVE_PERMISSIONS_IN_BACKGROUND', !is_exec_available());
		
		if (substr(php_uname(), 0, 7) == "Windows" || (defined('DONT_SAVE_PERMISSIONS_IN_BACKGROUND') && DONT_SAVE_PERMISSIONS_IN_BACKGROUND)){
			$ids = explode(',', $ids_str);
			foreach ($ids as $id) {
				$object = Objects::instance()->findObject($id);
				if ($object instanceof ContentDataObject) {
					$object->addToSharingTable();
				}
			}
		} else {
			$command = "nice -n19 php ". ROOT . "/application/helpers/add_object_to_sharing_table.php ".ROOT." ".$user->getId()." ".$user->getTwistedToken()." ".$ids_str;
			exec("$command > /dev/null &");
		}
	}
	