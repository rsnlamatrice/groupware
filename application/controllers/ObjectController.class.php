<?php

/**
 * Controller that is responsible for handling objects linking related requests
 *
 * @version 1.0
 * @author Ilija Studen <ilija.studen@gmail.com>
 */
class ObjectController extends ApplicationController {

	function index(){
		$this->setLayout('html');

	}
	/**
	 * Construct the ObjectController
	 *
	 * @access public
	 * @param void
	 * @return ObjectController
	 */
	function __construct() {
		parent::__construct();
		prepare_company_website_controller($this, 'website');
	} // __construct

	function popup_member_chooser() {
		tpl_assign('content_object_type_id', array_var($_GET, 'obj_type'));
		tpl_assign('genid', array_var($_GET, 'genid'));
		tpl_assign('selected', array_var($_GET, 'selected'));
		$this->setLayout("html");
	}
	
	function add_subscribers(ContentDataObject $object) {
		if (logged_user()->isGuest()) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		}
		$log_info = "";
		$log_info_unsubscribe = "";
		$subscribers = array_var($_POST, 'subscribers');
		$subscribers_ids = array();
		
		if (is_array($subscribers)) {
			$user_ids = array();
			
			//add new subscribers
			foreach ($subscribers as $key => $checked) {
				$user_id = substr($key, 5);
				$subscribers_ids[] = $user_id;
				if ($checked == "checked" && !in_array($user_id, $object->getSubscriberIds())) {
					$user = Contacts::findById($user_id);
					if ($user instanceof Contact) {
						$object->subscribeUser($user);
						$log_info .= ($log_info == "" ? "" : ",") . $user->getId();
						$user_ids[] = $user_id;
					}
				}
			}
			
			//remove subscribers
			$subscribers_to_remove = array_diff($object->getSubscriberIds(), $subscribers_ids);
			
			foreach ($subscribers_to_remove as $subs_remove) {
				$user = Contacts::findById($subs_remove);
				if ($user instanceof Contact) {
					$object->unsubscribeUser($user);
					$log_info_unsubscribe .= ($log_info_unsubscribe == "" ? "" : ",") . $user->getId();
				}
			}
			
			Hook::fire ('after_add_subscribers', array('object' => $object, 'user_ids' => $user_ids), $null);
			
			if ($log_info != "") {
				ApplicationLogs::createLog($object, ApplicationLogs::ACTION_SUBSCRIBE, false, true, true, $log_info);
			}
			if ($log_info_unsubscribe != "") {
				ApplicationLogs::createLog($object, ApplicationLogs::ACTION_UNSUBSCRIBE, false, true, true, $log_info_unsubscribe);
			}
		}else{
			$subscribers_to_remove = $object->getSubscriberIds();
			foreach ($subscribers_to_remove as $user_id_remove) {
				$log_info_unsubscribe.= ($log_info_unsubscribe == "" ? "" : ",") . $user_id_remove;
			}
			
			$object->clearSubscriptions();
			
			if ($log_info_unsubscribe != "") {
				ApplicationLogs::createLog($object, ApplicationLogs::ACTION_UNSUBSCRIBE, false, true, true, $log_info_unsubscribe);
			}			
		}
	}
	
	function redraw_subscribers_list() {
		$object = Objects::findObject(array_var($_GET, 'id'));
		if (!$object) {
			ajx_current("empty");
			return;
		}
		tpl_assign('object', $object);
		$this->setLayout("html");
		$this->setTemplate("list_subscribers");
	}
	
	function add_subscribers_list() {
		if (logged_user()->isGuest()) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		}
		$genid = array_var($_GET,'genid');
		$obj_id = array_var($_GET,'obj_id');
		
		$object = Objects::findObject($obj_id);
		
		if (!isset($genid)) {
			$genid = gen_id();
		}
		$subscriberIds = array();
		if ($object->isNew()) {
			$subscriberIds[] = logged_user()->getId();
		} else {
			foreach ($object->getSubscribers() as $u) {
				$subscriberIds[] = $u->getId();
			}
		}
		if($object instanceof TemplateTask){
			$objectTypeId = ProjectTasks::instance()->getObjectTypeId();
		}else{
			$objectTypeId = $object->getObjectTypeId();
		}
		
		
		tpl_assign('object', $object);
		tpl_assign('objectTypeId', $objectTypeId);
		tpl_assign('subscriberIds', $subscriberIds);
		tpl_assign('genid', $genid);
	}
	
	function add_subscribers_from_object_view() {
		ajx_current("empty");
		$objectId = array_var($_GET, 'object_id');
		$object = Objects::findObject($objectId);
		$old_users = $object->getSubscriberIds();
		$this->add_subscribers($object);
		$users = $object->getSubscriberIds();
		$new = array();
		foreach ($users as $user) {
			if (!in_array($user, $old_users)) {
				$new[] = $user;
			}
		}
		ApplicationLogs::createLog($object, ApplicationLogs::ACTION_SUBSCRIBE, false, false, true, implode(",", $new));
		
		flash_success(lang('subscription modified successfully'));
	}
	
	function init_trash() {
		require_javascript("og/TrashCan.js");
		ajx_current("panel", "trashcan", null, null, true);
		ajx_replace(true);
	}
	
	function init_archivedobjs() {
		require_javascript("og/ArchivedObjects.js");
		ajx_current("panel", "archivedobjects", null, null, true);
		ajx_replace(true);
	}

	function render_add_subscribers() {
		$context = build_context_array(array_var($_GET, 'context', ''));
		$uids = array_var($_GET, 'users', '');
		$genid = array_var($_GET, 'genid', '');
		$otype = array_var($_GET, 'otype', '');
		$subscriberIds = explode(",", $uids);

		tpl_assign('object_type_id', $otype);
		tpl_assign('context', $context);
		tpl_assign('subscriberIds', $subscriberIds);
		tpl_assign('genid', $genid);
		$this->setLayout("html");
		$this->setTemplate("add_subscribers");
	}
	
	
	function add_to_members($object, $member_ids, $user = null, $check_allowed_members = true) {
		if (!$user instanceof Contact) $user = logged_user();
		
		// clean member_ids
		$tmp_mids = array();
		foreach ($member_ids as $mid) {
			if (!is_null($mid) && trim($mid) != "") $tmp_mids[] = $mid;
		}
		$member_ids = $tmp_mids;
		
		if ($user->isGuest()) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		}
		
		if (isset($_POST['trees_not_loaded']) && $_POST['trees_not_loaded'] > 0) return;
		
		$required_dimension_ids = array();
		$dimension_object_types = $object->getDimensionObjectTypes();
		foreach($dimension_object_types as $dot){
			if ($dot->getIsRequired()){
				$required_dimension_ids[] = $dot->getDimensionId();
			}
		}
		$required_dimensions = Dimensions::findAll(array("conditions" => "id IN (".implode(",",$required_dimension_ids).") OR is_required=1"));
		
		// If not entered members
		/*if (count($member_ids) <= 0){
			$throw_error = true;
			if (Plugins::instance()->isActivePlugin('core_dimensions')) {
				$personal_member = Members::findById($user->getPersonalMemberId());
				if ($personal_member instanceof Member) {
					$member_ids[] = $user->getPersonalMemberId();
				}
			}
		}*/
		
		if (count($member_ids) > 0) {
			$enteredMembers = Members::findAll(array('conditions' => 'id IN ('.implode(",", $member_ids).')'));
		} else {
			$enteredMembers = array();
		}
		
		$manageable_members = array();
		foreach ($enteredMembers as $ent_mem) {
			if ($ent_mem->getDimension()->getIsManageable() && $ent_mem->getDimension()->getDefinesPermissions()) $manageable_members[] = $ent_mem;
		}
		
		if ((!can_add($user, $check_allowed_members ? $object->getAllowedMembersToAdd($user, $manageable_members):$manageable_members, $object->getObjectTypeId()))
			&& !($object instanceof TemplateTask || $object instanceof TemplateMilestone)) {
			
			$dinfos = DB::executeAll("SELECT name, code, options FROM ".TABLE_PREFIX."dimensions WHERE is_manageable = 1");
			$dimension_names = array();
			foreach ($dinfos as $dinfo) {
				$dimension_names[] = json_decode($dinfo['options'])->useLangs ? lang($dinfo['code']) : $dinfo['name'];
			}
			throw new Exception(lang('must choose at least one member of', implode(', ', $dimension_names)));
			ajx_current("empty");
			return;
		}
		
		$object->removeFromMembers($user, $enteredMembers);
		/* @var $object ContentDataObject */
		$validMembers = $check_allowed_members ? $object->getAllowedMembersToAdd($user,$enteredMembers) : $enteredMembers;

		foreach($required_dimensions as $rdim){
			$exists = false;
			foreach ($validMembers as $m){
				if ($m->getDimensionId() == $rdim->getId()) {
					$exists = true;
					break;
				}
			}
			if (!$exists){
				throw new Exception(lang('must choose at least one member of',$rdim->getName()));
			}
		}
		
		$object->addToMembers($validMembers, true);
		
		Hook::fire ('after_add_to_members', $object, $validMembers);
		
		$object->addToSharingTable();
		
		// add timeslots to members
		if ($object->allowsTimeslots()) {
			$timeslots = $object->getTimeslots();
			foreach ($timeslots as $timeslot) {
				$ts_mids = ObjectMembers::getMemberIdsByObject($timeslot->getId());
				// if classified then reclassify
				if (count($ts_mids) > 0) {
					ObjectMembers::delete('`object_id` = ' . $timeslot->getId());
					if (count($validMembers) > 0) {
						$timeslot->addToMembers($validMembers);
						$timeslot->addToSharingTable();
					}
				}
			}
		}
		
		return $validMembers;
	}
	
	
	/**
	 * Adds the custom properties of an object into the database.
	 * 
	 * @param $object
	 * @return unknown_type
	 */
	function add_custom_properties($object) {
		if (logged_user()->isGuest()) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		}
		$obj_custom_properties = array_var($_POST, 'object_custom_properties');
		
		$customProps = CustomProperties::getAllCustomPropertiesByObjectType($object->getObjectTypeId());
		//Sets all boolean custom properties to 0. If any boolean properties are returned, they are subsequently set to 1.
		foreach($customProps as $cp){
			if($cp->getType() == 'boolean'){
				$custom_property_value = CustomPropertyValues::getCustomPropertyValue($object->getId(), $cp->getId());
				if(!$custom_property_value instanceof CustomPropertyValue){
					$custom_property_value = new CustomPropertyValue();
				}
				$custom_property_value->setObjectId($object->getId());
				$custom_property_value->setCustomPropertyId($cp->getId());
				$custom_property_value->setValue(0);
				$custom_property_value->save();
			}
		}
		if (is_array($obj_custom_properties)){
			
			foreach($obj_custom_properties as $id => $value){
				//Get the custom property
				$custom_property = null;
				foreach ($customProps as $cp){
					if ($cp->getId() == $id){
						$custom_property = $cp;
						break;
					}
				}
				
				if ($custom_property instanceof CustomProperty){
					// save dates in standard format "Y-m-d H:i:s", because the column type is string
					if ($custom_property->getType() == 'date') {
						if(is_array($value)){
							$newValues = array();
							foreach ($value as $val) {
								$dtv = DateTimeValueLib::dateFromFormatAndString(user_config_option('date_format'), $val);
								$newValues[] = $dtv->format("Y-m-d H:i:s");
							}
							$value = $newValues;
						} else {
							$dtv = DateTimeValueLib::dateFromFormatAndString(user_config_option('date_format'), $value);
							$value = $dtv->format("Y-m-d H:i:s");
						}
					}
					
					//Save multiple values
					if(is_array($value)){
						CustomPropertyValues::deleteCustomPropertyValues($object->getId(), $id);
						foreach($value as &$val){
							if (is_array($val)) {
								// CP type == table
								$str_val = '';
								foreach ($val as $col_val) {
									$col_val = str_replace("|", "\|", $col_val);
									$str_val .= ($str_val == '' ? '' : '|') . $col_val;
								}
								$val = $str_val;
							}
							if($val != ''){
								$custom_property_value = new CustomPropertyValue();
								$custom_property_value->setObjectId($object->getId());
								$custom_property_value->setCustomPropertyId($id);
								$custom_property_value->setValue($val);
								$custom_property_value->save();
							}
						}
					}else{
						if($custom_property->getType() == 'boolean'){
							$value = isset($value);
						}
						$cpv = CustomPropertyValues::getCustomPropertyValue($object->getId(), $id);
						if($cpv instanceof CustomPropertyValue){
							$custom_property_value = $cpv;
						} else 
							$custom_property_value = new CustomPropertyValue();
						$custom_property_value->setObjectId($object->getId());
						$custom_property_value->setCustomPropertyId($id);
						$custom_property_value->setValue($value);
						$custom_property_value->save();
					}
					
					//Add to searchable objects
					if ($object->isSearchable() && 
						($custom_property->getType() == 'text' || $custom_property->getType() == 'list' || $custom_property->getType() == 'numeric')){
						
						$name = $custom_property->getName();
						$searchable_object = SearchableObjects::findOne(array("conditions" => "`rel_object_id` = ".$object->getId()." AND `column_name` = '$name'"));
						if (!$searchable_object)
							$searchable_object = new SearchableObject();
						
						if (is_array($value))
							$value = implode(', ', $value);
							
						$searchable_object->setRelObjectId($object->getId());
						$searchable_object->setColumnName($name);
						$searchable_object->setContent($value);
						
						$searchable_object->save();
					}
				}
			}
		}
		
		//Save the key - value pair custom properties (object_properties table)
		$object->clearObjectProperties();
		$names = array_var($_POST, 'custom_prop_names');
		$values = array_var($_POST, 'custom_prop_values');
		if (!is_array($names)) return;
		for ($i=0; $i < count($names); $i++) {
			$name = trim($names[$i]);
			$value = trim($values[$i]);
			if ($name != '' && $value != '') {
				$property = new ObjectProperty();
				$property->setObject($object);
				$property->setPropertyName($name);
				$property->setPropertyValue($value);
				$property->save();
				if ($object->isSearchable()) {
					$object->addPropertyToSearchableObject($property);
				}
			}
		}

	}

	function add_reminders($object) {
		if (logged_user()->isGuest()) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		}
		$object->clearReminders(logged_user(), true);
		$typesC = array_var($_POST, 'reminder_type');
		if (!is_array($typesC)) return;
		$durationsC = array_var($_POST, 'reminder_duration');
		$duration_typesC = array_var($_POST, 'reminder_duration_type');
		$subscribersC = array_var($_POST, 'reminder_subscribers');
		foreach ($typesC as $context => $types) {
			$durations = $durationsC[$context];
			$duration_types = $duration_typesC[$context];
			$subscribers = $subscribersC[$context];
			for ($i=0; $i < count($types); $i++) {
				$type = $types[$i];
				$duration = $durations[$i];
				$duration_type = $duration_types[$i];
				$minutes = $duration * $duration_type;
				$reminder = new ObjectReminder();
				$reminder->setMinutesBefore($minutes);
				$reminder->setType($type);
				$reminder->setContext($context);
				$reminder->setObject($object);
				if (isset($subscribers[$i])) {
					$reminder->setUserId(0);
				} else {
					$reminder->setUser(logged_user());
				}
				$date = $object->getColumnValue($context);
				if ($date instanceof DateTimeValue) {
					$rdate = new DateTimeValue($date->getTimestamp() - $minutes * 60);
					$reminder->setDate($rdate);
				}
				$reminder->save();
			}
		}
	}
	
	function update_reminders($object, $reminders) {
		if (logged_user()->isGuest()) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		}		
		if($object->getObjectTypeName() == "task"){
			$new_date = $object->getDueDate();			
		}else if($object->getObjectTypeName() == "event"){
			$new_date = $object->getStart();
		}			
		foreach($reminders as $reminder){			
			$reminder->setDate($new_date);
			$reminder->save();			
		}		
	}
	

	// ---------------------------------------------------
	//  Link / Unlink
	// ---------------------------------------------------

	function redraw_linked_object_list() {
		$object = Objects::findObject(array_var($_GET, 'id'));
		if (!$object) {
			ajx_current("empty");
			return;
		}

		tpl_assign('linked_objects_object', $object);
		tpl_assign('shortDisplay', false);
		tpl_assign('enableAdding', true);
		tpl_assign('linked_objects', $object->getLinkedObjects());
		$this->setLayout("html");
		$this->setTemplate("list_linked_objects");
	}
	
	function link_object() {
		if (logged_user()->isGuest()) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		}
		ajx_current("empty");
		$object_id = get_id('object_id');
			
		$object = Objects::findObject($object_id);
		if(!($object instanceof ApplicationDataObject)) {
			flash_error(lang('no access permissions'));
			return;
		} // if
		if(!($object->canLinkObject(logged_user()))){
			flash_error(lang('no access permissions'));
			return;
		} // if
		$str_obj = array_var($_GET, 'objects');
		if ($str_obj == null) return;
		try {
			$err_message_list = '';
			DB::beginWork();
			$split = explode(",", $str_obj);
			$succ = 0; $err = 0; $permission_err = false; $object_dnx_err = false;
			foreach ($split as $objid) {
				if ($objid == $object_id){
					$err++;
					$err_message_list .= ' - ' . lang('error cannot link object to self') . "\n";
					continue;
				}
				$rel_object = Objects::findObject($objid);
				if (!($rel_object instanceof ApplicationDataObject)) {
					$err++;
					if (!$object_dnx_err)
						$err_message_list .= ' - ' . lang('object dnx') . "\n";
					$object_dnx_err = true;
					continue;
				} // if
				if (!($rel_object->canLinkObject(logged_user()))) {
					$err++;
					if (!$permission_err)
						$err_message_list .= ' - ' . lang('no access permissions') . "\n";
					$permission_err = true;
					continue;
				} // if
				try {
					$object->linkObject($rel_object);
					if (config_option('updateOnLinkedObjects')){
						$object->save();
						$rel_object->save();
					}
					if ($object instanceof ContentDataObject) {
						ApplicationLogs::createLog($object, ApplicationLogs::ACTION_LINK, false, null, true, $objid);
					}
					if ($rel_object instanceof ContentDataObject) {
						ApplicationLogs::createLog($rel_object, ApplicationLogs::ACTION_LINK, false, null, true, $object->getId());
					}
					$succ++;
				} catch(Exception $e){
					$err++;
				}
			}
			DB::commit();
			$message = "";
			if ($err > 0) {
				$message .= lang("error link object", $err) . "\n" . $err_message_list;
			}
			if ($succ > 0) {
				$message .= lang("success link objects", $succ) . "\n";
			}
			if ($succ == 0 && $err > 0) {
				flash_error($message);
				ajx_current("empty");
			} else if ($succ > 0) {
				flash_success($message);
				if (array_var($_GET, 'reload')) {
					ajx_current("reload");
				}
			}
		} catch (Exception $e) {
			DB::rollback();
			flash_error($e->getMessage());
			ajx_current("empty");
		}
	}

	/**
	 * Function called from other controllers when creating a new object an linking objects to it
	 *
	 * @param void
	 * @return null
	 */
	function link_to_new_object($the_object){
		if (logged_user()->isGuest()) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		}
		
		$objects = array_var($_POST, 'linked_objects');
		
		if (is_array($objects) && count($objects) > 0 && !$the_object->isNew() && !$the_object->canLinkObject(logged_user())) {
			flash_error(lang("user cannot link objects"));
			return;
		}
		
		$the_object->clearLinkedObjects();
		if (is_array($objects)) {
			$err = 0;
			foreach ($objects as $objid) {
				$split = explode(":", $objid);
				if ($split[0] == $the_object->getId()) continue;
				if(count($split) == 1){
					$object = Objects::findObject($split[0]);
				}else if (count($split) == 3 && $split[2] == 'isName'){
					$object = ProjectFiles::getByFilename($split[1]);
				} else continue;
				
				if ($object->canLinkObject(logged_user())) {
					$the_object->linkObject($object);
					if ($the_object instanceof ContentDataObject)
						ApplicationLogs::createLog($the_object, ApplicationLogs::ACTION_LINK,false,null,true, $object->getId());
					if ($object instanceof ContentDataObject)
						ApplicationLogs::createLog($object, ApplicationLogs::ACTION_LINK,false,null,true, $the_object->getId());
				} else {
					$err++;
				}
			}
			if ($err > 0) {
				flash_error(lang('some objects could not be linked', $err));
			}
		}
	}

	/**
	 * Unlink object from related object
	 *
	 * @param void
	 * @return null
	 */
	function unlink_from_object() { // ex detach_from_object() {
		if (logged_user()->isGuest()) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		}
		$object_id = get_id('object_id');
		$object1 = Objects::findObject($object_id);
		
		$dont_reload = array_var($_GET, 'dont_reload');
		if (array_var($_GET, 'rel_objects')) {
			$objects_to_unlink = explode(",", array_var($_GET, 'rel_objects'));
		} else {
			$objects_to_unlink = array(get_id('rel_object_id'));
		}
		try {
			DB::beginWork();
			$err = 0; $succ = 0;
			foreach ($objects_to_unlink as $rel_object_id) {
					
				$object2 = Objects::findObject($rel_object_id);
				if(!($object1 instanceof ApplicationDataObject)|| !($object2 instanceof ApplicationDataObject)) {
					flash_error(lang('object not found'));
					ajx_current("empty");
					return;
				} // if
					
				$linked_object = LinkedObjects::findById(array(
					'rel_object_id' => $object_id,
					'object_id' => $rel_object_id,
				)); // findById
				if(!($linked_object instanceof LinkedObject ))
				{ //search for reverse link
					$linked_object = LinkedObjects::findById(array(
						'rel_object_id' => $rel_object_id,
						'object_id' => $object_id,
					)); // findById
				}
		
				if(!($linked_object instanceof LinkedObject )) {
					$err++;
					continue;
				} // if
				
				$linked_object->delete();
				if (config_option('updateOnLinkedObjects')){
					$object1->save();
					$object2->save();
				}
				if ($object1 instanceof ContentDataObject)
					ApplicationLogs::createLog($object1, ApplicationLogs::ACTION_UNLINK, false, null, true, $object2->getId());
				if ($object2 instanceof ContentDataObject)
					ApplicationLogs::createLog($object2, ApplicationLogs::ACTION_UNLINK, false, null, true, $object1->getId());
				
				$succ++;
			}
			DB::commit();
			$message = "";
			if ($err > 0) {
				$message .= lang("error unlink object", $err) . "\n";
			}
			if ($succ > 0) {
				$message .= lang("success unlink object", $succ) . "\n";
			}
			if ($succ == 0 && $err > 0) {
				flash_error($message);
			} else if ($succ > 0) {
				flash_success($message);
			}
			
			flash_success(lang('success unlink object'));
			
			if ($dont_reload) ajx_current("empty");
			else ajx_current("reload");
		} catch(Exception $e) {
			flash_error(lang('error unlink object'));
			DB::rollback();
			ajx_current("empty");
		} // try
	} // unlink_from_object


	/**
	 * Show property list
	 *
	 * @param
	 * @return ObjectProperties
	 */
	function view_properties()
	{
		$manager_class = array_var($_GET, 'manager');
		$object_id = get_id('object_id');
		$obj = Objects::findObject ($object_id);

		if (!($obj instanceof ContentDataObject ))
		{
			flash_error(lang('object dnx'));
			ajx_current("empty");
			return;
		}
		$properties = ObjectProperties::getAllPropertiesByObject($obj);
		if(!($properties instanceof ObjectProperties ))
		{
			flash_error(lang('properties dnx'));
			ajx_current("empty");
			return;
		}
		tpl_assign('properties', $properties);
	} // view_properties
	
	function show_all_linked_objects() {
				
		require_javascript("og/LinkedObjectsManager.js");
		ajx_current("panel", "linkedobject", null, array(
			'linked_object' => array_var($_GET, 'linked_object'),
			'linked_object_name' => array_var($_GET, 'linked_object_name'),
			'linked_object_ico' => array_var($_GET, 'linked_object_ico'),
		));
		ajx_replace(true);
	}	

	/**
	 * Update, delete and add new properties
	 *
	 * @access public
	 * @param void
	 * @return null
	 */
	function update_properties() {
		if (logged_user()->isGuest()) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		}
		$this->setTemplate('add_properties');

		$manager_class = array_var($_GET, 'manager');
		$object_id = get_id('object_id');
		$obj = Objects::findObject ($object_id);
		if(!($obj instanceof ContentDataObject )) {
			flash_error(lang('object dnx'));
			ajx_current("empty");
			return;
		} // if

		if(! logged_user()->getCanManageProperties()) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		} // if

		$new_properties = array_var($_POST, 'new_properties');
		$update_properties = array_var($_POST, 'update_properties');
		$delete_properties = array_var($_POST, 'delete_properties');
		if(is_array(array_var($_POST, 'new_properties')) || is_array(array_var($_POST, 'update_properties'))) {

			try {
				DB::beginWork();
				//add new properties
				foreach ($new_properties as $prop) {
					$property = new ObjectProperty();
					$property->setFromAttributes($prop);
					$property->setRelObjectId($object_id);
					$property->save();
				}
				foreach ($update_properties as $prop) {
					$property = ObjectProperties::getProperty(array_var($prop,'id')); //ObjectProperties::getPropertyByName($obj, array_var($prop,'name'));
					$property->setPropertyValue(array_var($prop,'value'));
					$property->save();
				}
				foreach ($delete_properties as $prop)
				{
					$property = ObjectProperties::getProperty(array_var($prop,'id')); //ObjectProperties::getPropertyByName($obj, array_var($prop,'name'));
					$prop->delete();
				}
				tpl_assign('properties',ObjectProperties::getAllPropertiesByObject($obj));
				
				DB::commit();
				ApplicationLogs::createLog($obj, ApplicationLogs::ACTION_EDIT);
				
				flash_success(lang('success add properties'));
				$this->redirectToReferer($obj->getObjectUrl());
			} catch(Exception $e) {
				DB::rollback();
				flash_error($e->getMessage());
				ajx_current("empty");
			} //
		} // if
	} // update_properties

	function mark_as_read() {
		ajx_current('empty');
		$csvids = array_var($_GET, 'ids');
		$ids = explode(",", $csvids);
		$this->do_mark_as_read_unread_objects($ids, true);
	}
	
	function mark_as_unread() {
		ajx_current('empty');
		$csvids = array_var($_GET, 'ids');
		$ids = explode(",", $csvids);
		$this->do_mark_as_read_unread_objects($ids, false);
	}
	
	static function reloadPersonsDimension() {
		if (Plugins::instance()->isActivePlugin('core_dimensions')) {
			$person_dim = Dimensions::findByCode('feng_persons');
			if ($person_dim instanceof Dimension) {
				evt_add('reload dimension tree', $person_dim->getId());
			}
		}
	}

	function list_objects() {
		/* get query parameters */
		$filesPerPage = config_option('files_per_page');
		$start = array_var($_GET,'start') ? (integer)array_var($_GET,'start') : 0;
		$limit = array_var($_GET,'limit') ? array_var($_GET,'limit') : $filesPerPage;
		$order = array_var($_GET,'sort');
		$id_no_select = array_var($_GET,'id_no_select',"undefined");
		$ignore_context = (bool) array_var($_GET, 'ignore_context');
		$member_ids = json_decode(array_var($_GET, 'member_ids'));
		$extra_member_ids = json_decode(array_var($_GET, 'extra_member_ids'));
		
		$orderdir = array_var($_GET,'dir');
		if (!in_array(strtoupper($orderdir), array('ASC', 'DESC'))) $orderdir = 'ASC';
		
		if ($order == "dateUpdated") {
			$order = "updated_on";
		}elseif ($order == "dateArchived") {
			$order = "archived_on";
		}elseif ($order == "dateDeleted") {
			$order = "trashed_on";
		}elseif ($order == "name") {
			$order = "name";
		} else {
			$order = "";
			$orderdir = "";
		}
		
		$extra_list_params = array_var($_GET,'extra_list_params');
		$extra_list_params = json_decode($extra_list_params);
		
		$page = (integer) ($start / $limit) + 1;
		$hide_private = !logged_user()->isMemberOfOwnerCompany();
		
		$typeCSV = array_var($_GET, 'type');
		$types = null;
		if ($typeCSV) {
			$types = explode(",", $typeCSV);
		}
		$name_filter = mysql_real_escape_string( array_var($_GET, 'name') );
		$linked_obj_filter = array_var($_GET, 'linkedobject');
		$object_ids_filter = '';
		$show_all_linked_objects = false;
		if (!is_null($linked_obj_filter)) {
			$show_all_linked_objects = true;
			$linkedObject = Objects::findObject($linked_obj_filter);
			$objs = $linkedObject->getLinkedObjects();
			foreach ($objs as $obj) $object_ids_filter .= ($object_ids_filter == '' ? '' : ',') . $obj->getId();
		}
		
		$filters = array();
		if (!is_null($types)) $filters['types'] = $types;
		if (!is_null($name_filter)) $filters['name'] = $name_filter;
		if ($object_ids_filter != '') $filters['object_ids'] = $object_ids_filter;

		$user = array_var($_GET,'user');
		$trashed = array_var($_GET, 'trashed', false);
		$archived = array_var($_GET, 'archived', false);

		/* if there's an action to execute, do so */
		if (!$show_all_linked_objects){
			$linkedObject = null;
			if (array_var($_GET, 'action') == 'delete') {
				$ids = explode(',', array_var($_GET, 'objects'));

				$result = ContentDataObjects::listing(array(
					"extra_conditions" => " AND o.id IN (".implode(",",$ids).") ",
					"include_deleted" => true
				));

				$objects = $result->objects;

				$real_deleted_ids = array();
				list($succ, $err) = $this->do_delete_objects($objects, false, $real_deleted_ids);

				if ($err > 0) {
					flash_error(lang('error delete objects', $err));
				} else {
					Hook::fire('after_object_delete_permanently', $real_deleted_ids, $ignored);
					flash_success(lang('success delete objects', $succ));
				}
			} else if (array_var($_GET, 'action') == 'delete_permanently') {
				$ids = explode(',', array_var($_GET, 'objects'));

				$objects = Objects::instance()->findAll(array("conditions" => "id IN (".implode(",",$ids).")"));

				$real_deleted_ids = array();
				list($succ, $err) = $this->do_delete_objects($objects, true, $real_deleted_ids);

				if ($err > 0) {
					flash_error(lang('error delete objects', $err));
				}
				if ($succ > 0) {
					Hook::fire('after_object_delete_permanently', $real_deleted_ids, $ignored);
					flash_success(lang('success delete objects', $succ));
				}
			}else if (array_var($_GET, 'action') == 'markasread') {
				$ids = explode(',', array_var($_GET, 'objects'));
				list($succ, $err) = $this->do_mark_as_read_unread_objects($ids, true);

			}else if (array_var($_GET, 'action') == 'markasunread') {
				$ids = explode(',', array_var($_GET, 'objects'));
				list($succ, $err) = $this->do_mark_as_read_unread_objects($ids, false);

			}else if (array_var($_GET, 'action') == 'empty_trash_can') {

				$result = ContentDataObjects::listing(array(
					"select_columns" => array('id'),
					"raw_data" => true,
					"trashed" => true,
				));
				$objects = $result->objects;

				$real_deleted_ids = array();
				list($succ, $err) = $this->do_delete_objects($objects, true, $real_deleted_ids, true);
				if ($err > 0) {
					flash_error(lang('error delete objects', $err));
				}
				if ($succ > 0) {
					flash_success(lang('success delete objects', $succ));
				}
			} else if (array_var($_GET, 'action') == 'archive') {
				$ids = explode(',', array_var($_GET, 'objects'));
				list($succ, $err) = $this->do_archive_unarchive_objects($ids, 'archive');
				if ($err > 0) {
					flash_error(lang('error archive objects', $err));
				} else {
					flash_success(lang('success archive objects', $succ));
				}
			} else if (array_var($_GET, 'action') == 'unarchive') {
				$ids = explode(',', array_var($_GET, 'objects'));
				list($succ, $err) = $this->do_archive_unarchive_objects($ids, 'unarchive');
				if ($err > 0) {
					flash_error(lang('error unarchive objects', $err));
				} else {
					flash_success(lang('success unarchive objects', $succ));
				}
			}
			else if (array_var($_GET, 'action') == 'unclassify') {
				$ids = explode(',', array_var($_GET, 'objects'));
				$err = 0;
				$succ = 0;
				foreach ($ids as $id) {
					$split = explode(":", $id);
					$type = $split[0];
					if (Plugins::instance()->isActivePlugin('mail') && $type == 'MailContents') {
						$email = MailContents::findById($split[1]);
						if (isset($email) && !$email->isDeleted() && $email->canEdit(logged_user())){
							if (MailController::do_unclassify($email)) $succ++;
							else $err++;
						} else $err++;
					}
				}
				if ($err > 0) {
					flash_error(lang('error unclassify emails', $err));
				} else {
					flash_success(lang('success unclassify emails', $succ));
				}
			}
			else if (array_var($_GET, 'action') == 'restore') {
				$errorMessage = null;
				$ids = explode(',', array_var($_GET, 'objects'));
				$success = 0; $error = 0;
				foreach ($ids as $id) {
					$obj = Objects::findObject($id);
					if ($obj->canDelete(logged_user())) {
						try {
							$obj->untrash($errorMessage);

							if($obj->getObjectTypeId() == 11){
								$event = ProjectEvents::findById($obj->getId());
								if($event->getExtCalId() != ""){
									$this->created_event_google_calendar($obj,$event);
								}
							}

							ApplicationLogs::createLog($obj, ApplicationLogs::ACTION_UNTRASH);
							$success++;
						} catch (Exception $e) {
							$error++;
						}
					} else {
						$error++;
					}
				}
				if ($success > 0) {
					flash_success(lang("success untrash objects", $success));
				}
				if ($error > 0) {
					$errorString = is_null($errorMessage) ? lang("error untrash objects", $error) : $errorMessage;
					flash_error($errorString);
				}
			}
		}
		
		$filterName = array_var($_GET,'name');
		
		$template_object_names = "";
		$template_extra_condition = "true";
		
		$template_objects = false;
		
		if(in_array("template_task", array_var($filters, 'types', array())) || in_array("template_milestone", array_var($filters, 'types', array()))){
			$template_id = 0;
			$template_objects = true;
			if(isset($extra_list_params->template_id)){
				$template_id = $extra_list_params->template_id;
			}					
			$tmpl_task = TemplateTasks::findById(intval($id_no_select));
			if($tmpl_task instanceof TemplateTask){
				$template_extra_condition = "o.id IN (SELECT object_id from ".TABLE_PREFIX."template_tasks WHERE `template_id` IN (".$tmpl_task->getTemplateId().", 0) )";
			}else{
				$template_extra_condition = "o.id IN (SELECT object_id from ".TABLE_PREFIX."template_tasks WHERE `template_id` IN (".intval($template_id).", 0) )";
			}
		}else{
			$template_object_names = "AND name <> 'template_task' AND name <> 'template_milestone'" ;
		}
		$result = null;
		
		$context = active_context();

		$obj_type_types = array('content_object', 'dimension_object');
		if (array_var($_GET, 'include_comments')) $obj_type_types[] = 'comment';
		
		$type_condition = "";
		if ($types) {
			$type_condition = " AND name IN ('".implode("','",$types) ."')";  
		}
		
		$res = DB::executeAll("SELECT id from ".TABLE_PREFIX."object_types WHERE type IN ('". implode("','",$obj_type_types)."') ".$template_object_names." AND name <> 'file revision' $type_condition ");
		$type_ids = array();

		foreach ($res as $row){
			if (ObjectTypes::isListableObjectType($row['id']) ){
				$types_ids[] = $row['id'] ;
			}
		}

		//Hook::fire('list_objects_type_ids', null, $types_ids);
		$type_ids_csv = implode(',', $types_ids);
		$extra_conditions = array() ;
		$extra_conditions[] = "object_type_id in ($type_ids_csv)";
		$extra_conditions[] = $template_extra_condition;
		if ($name_filter) {
			$extra_conditions[] = "name LIKE '%$name_filter%'" ;
		}
		if ($id_no_select != "undefined") {
			$extra_conditions[] = "id <> '$id_no_select'" ;
		}
		if($object_ids_filter != ""){
			$extra_conditions[] = "id in ($object_ids_filter)";
		}
		
		if (!SystemPermissions::userHasSystemPermission(logged_user(), 'can_see_assigned_to_other_tasks')) {
			$extra_conditions[] = "IF((SELECT ot.name FROM ".TABLE_PREFIX."object_types ot WHERE ot.id=o.object_type_id)='task',
			 (SELECT t.assigned_to_contact_id FROM ".TABLE_PREFIX."project_tasks t WHERE t.object_id=o.id) = ".logged_user()->getId().",
			 true)";
		}
		
		// don't include tasks which have is_template=1
		$extra_conditions[] = "IF((SELECT ot.name FROM ".TABLE_PREFIX."object_types ot WHERE ot.id=o.object_type_id)='task',
			 (SELECT t.is_template FROM ".TABLE_PREFIX."project_tasks t WHERE t.object_id=o.id) = 0, true)";
		
		$only_count_result = array_var($_GET, 'only_result',false);
		$count_results = array_var($_GET, 'count_results',false);
		
		if($object_ids_filter == "" && $show_all_linked_objects){
			$pagination = array();
		}else{
			$pagination = ContentDataObjects::listing(array(
				"start" => $start,
				"limit" => $limit,
				"order" => $order,
				"order_dir" => $orderdir,
				"trashed" => $trashed,
				"archived" => $archived,
				"types" => $types,
				"extra_conditions" => " AND ".implode(" AND ", $extra_conditions),
				"ignore_context" => $ignore_context,
				"extra_member_ids" => $extra_member_ids,
				"count_results" => $count_results,
				"only_count_results" => $only_count_result,
				"template_objects" => $template_objects
			));
		}
		
		$result = $pagination->objects;
		$total_items = $pagination->total;
		
		if(!$result) $result = array();

		/* prepare response object */
		$info = array();

		foreach ($result as $obj /* @var $obj Object */) {
			$info_elem =  $obj->getArrayInfo($trashed, $archived);
			
			$instance = Objects::instance()->findObject($info_elem['object_id']);
			if (!$instance instanceof ContentDataObject) continue;
			$info_elem['url'] = $instance->getViewUrl();
			
			
			$info_elem['isRead'] = $instance->getIsRead(logged_user()->getId()) ;
			$info_elem['manager'] = get_class($instance->manager()) ;
			$info_elem['memPath'] = json_encode($instance->getMembersToDisplayPath());
			/* @var $instance Contact  */
			if ($instance instanceof  Contact /* @var $instance Contact  */ ) {
				if( $instance->isCompany() ) {
					$info_elem['icon'] = 'ico-company';
					$info_elem['type'] = 'company';
					
				}else{
					$info_elem['memPath'] = json_encode($instance->getUserType()?"":$instance->getMembersToDisplayPath());
				}
			} else if ($instance instanceof ProjectFile) {
				$info_elem['mimeType'] = $instance->getTypeString();
			}
			
			$info[] = $info_elem;
			
		}
		
		$listing = array(
			"totalCount" => $total_items,
			"start" => $start,
			"objects" => $info
		);
		
		
		ajx_extra_data($listing);
		tpl_assign("listing", $listing);
		
		if (isset($reload) && $reload) ajx_current("reload");
		else ajx_current("empty");
	}

	
	function view(){
		$id = array_var($_GET,'id');
		$obj = Objects::findObject($id);
		if(!($obj instanceof DataObject )) {
			flash_error(lang('object dnx'));
			ajx_current("empty");
			return;
		} // if

		if(! $obj->canView( logged_user())) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		} // if
			
		redirect_to($obj->getObjectUrl(),true);
	}

	function do_delete_objects($objects, $permanent = false, &$deleted_object_ids, $raw_data=false) {
		$err = 0; // count errors
		$succ = 0; // count files deleted
		foreach ($objects as $object) {
			try {
				$obj = Objects::findObject($raw_data ? $object['id'] : $object->getId());
				// do not delete users from here
				if ($obj instanceof Contact && $obj->isUser()) continue;
				
				if ($obj instanceof ContentDataObject && $obj->canDelete(logged_user())) {
					if ($permanent) {
						if (Plugins::instance()->isActivePlugin('mail') && $obj instanceof MailContent) {
							$obj->delete(false);
						} elseif (Plugins::instance()->isActivePlugin('income') && $obj instanceof IncomeInvoice) {
							$obj->delete(false);
						} else {
							$obj->delete();
							Members::delete(array("conditions"=>"object_id = ".$obj->getId()));
						}
						$deleted_object_ids[] = $obj->getId();
						ApplicationLogs::createLog($obj, ApplicationLogs::ACTION_DELETE);
						$succ++;
					} else if ($obj->isTrashable()) {
						$obj->trash();
						ApplicationLogs::createLog($obj, ApplicationLogs::ACTION_TRASH);
						$succ++;
					}
				}
			} catch(Exception $e) {
				$err ++;
			}
		}
		return array($succ, $err);
	}
	
	function do_archive_unarchive_objects($ids, $action='archive') {
		$err = 0; // count errors
		$succ = 0;
		foreach ($ids as $id) {
			try {
				if (trim($id)!=''){
					$obj = Objects::findObject($id);
					if (!$obj instanceof ApplicationDataObject) {
						$err ++;
						continue;
					}
					if ($obj->canEdit(logged_user())) {
						if ($action == 'archive') {
							$obj->archive();
							$succ++;
							ApplicationLogs::createLog($obj, null, ApplicationLogs::ACTION_ARCHIVE);
						} else if ($action == 'unarchive') {
							$obj->unarchive();
							$succ++;
							ApplicationLogs::createLog($obj, null, ApplicationLogs::ACTION_UNARCHIVE);
						}
					} else {
						$err ++;
					}
				}
			} catch(Exception $e) {
				$err ++;
			} // try
		}
		return array($succ, $err);
	}

	function do_mark_as_read_unread_objects($ids, $read) {
		$err = 0; // count errors
		$succ = 0; // count updated objects
		$ids_to_mark = array();
		
		foreach ($ids as $id) {
			try {
				$obj = Objects::findObject($id);
				
				if ($obj instanceof ContentDataObject && logged_user() instanceof Contact) {
					$ret = $obj->setIsRead(logged_user()->getId(), $read);
					if($ret){
						$ids_to_mark[] = $id;
					}
				}
				$succ++;
			} catch(Exception $e) {
				$err ++;
			} // try
		}
		
		Hook::fire('do_mark_as_read_unread_objects', $ids_to_mark, $read);
		return array($succ, $err);
	}
	
	function move() {
		/*	TODO implement again this function 
		if (logged_user()->isGuest()) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		}
		ajx_current("empty");
		$ids = array_var($_GET, 'ids');
		if (!$ids) return;
		$wsid = array_var($_GET, 'ws');
		$keep = array_var($_GET, 'keep', 1) == 1;
		$atts = array_var($_GET, 'atts', 0) == 1;
		$workspace = Projects::findById($wsid);
		if (!$workspace instanceof Project) {
			flash_error(lang('project dnx'));
			return;
		}
		$id_list = explode(",", $ids);
		$err = 0;
		$succ = 0;
		foreach ($id_list as $cid) {
			list($manager, $id) = explode(":", $cid);
			if (isset($maganer) && $maganer == 'Projects') continue;
			try {
				$obj = Objects::findObject($id);
				if ($obj instanceof ContentDataObject && $obj->canEdit(logged_user())) {
					if ($obj instanceof MailContent) {
						$conversation = MailContents::getMailsFromConversation($obj);
						$count = 0;
						foreach ($conversation as $conv_email) {
							$count += MailController::addEmailToWorkspace($conv_email->getId(), $workspace, $keep);
							if (array_var($_GET, 'atts') && $conv_email->getHasAttachments()) {
								MailUtilities::parseMail($conv_email->getContent(), $decoded, $parsedEmail, $warnings);
								$classification_data = array();
								for ($j=0; $j < count(array_var($parsedEmail, "Attachments", array())); $j++) {
									$classification_data["att_".$j] = true;		
								}
								MailController::classifyFile($classification_data, $conv_email, $parsedEmail, array($workspace), $keep, $tags);
							}
						}
						$succ++;
					} else {
						$remain = 0;
						if (!$keep || $obj instanceof ProjectTask || $obj instanceof ProjectMilestone) { // Tasks and Milestones can have only 1 workspace
							$removed = "";
							$ws = $obj->getWorkspaces();
							foreach ($ws as $w) {
								if (can_add(logged_user(), $w, get_class($obj->manager()))) {
									$obj->removeFromWorkspace($w);
									$removed .= $w->getId() . ",";
								} else {
									$remain++;
								}
							}
							$removed = substr($removed, 0, -1);
							$log_action = ApplicationLogs::ACTION_MOVE;
							$log_data = ($removed == "" ? "" : "from:$removed;") . "to:$wsid";
						} else {
							$log_action = ApplicationLogs::ACTION_COPY;
							$log_data = "to:$wsid";
						}
						if ($remain > 0 && ($obj instanceof ProjectTask || $obj instanceof ProjectMilestone)) {
							$err++;
						} else {
							$obj->addToWorkspace($workspace);
							ApplicationLogs::createLog($obj, $log_action, false, null, true, $log_data);
							$succ++;
						}
					}
				} else {
					$err++;
				}
			} catch (Exception $e) {
				$err++;
			}
		}
		if ($err > 0) {
			flash_error(lang("error move objects", $err));
		} else {
			flash_success(lang("success move objects", $succ));
		}*/
	}

	function view_history(){
		$id = array_var($_GET,'id');
		$obj = Objects::findObject($id);

		$isUser = $obj instanceof Contact && $obj->isUser() ? true : false;
		if(!($obj instanceof ApplicationDataObject )) {
			flash_error(lang('object dnx'));
			ajx_current("empty");
			return;
		} // if
		if($isUser && (logged_user()->getId() != $id && !logged_user()->isAdministrator())) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		} // if		
		if(!$isUser && !$obj->canView(logged_user())){
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		}
		
		// if logged user is guest don't show other users logs
		$extra_conditions = "";
		if (logged_user()->isGuest()) {
			$extra_conditions = " AND `created_by_id` = ".logged_user()->getId();
		}
		
		$logs = ApplicationLogs::getObjectLogs($obj, false, true, null, null, $extra_conditions);
		$logs_read = ApplicationReadLogs::getObjectLogs($obj, null, null, $extra_conditions);
		
		tpl_assign('object',$obj);
		tpl_assign('logs',$logs);
		tpl_assign('logs_read',$logs_read);
	}

	// ---------------------------------------------------
	//  Subscriptions
	// ---------------------------------------------------

	/**
	 * Subscribe to object
	 *
	 * @param void
	 * @return null
	 */
	function subscribe() {
		ajx_current("reload");

		$id = array_var($_GET,'id');
		$object = Objects::findObject($id);
		if(!($object instanceof ApplicationDataObject)) {
			flash_error(lang('message dnx'));
			return;
		} // if

		if(!$object->canView(logged_user())) {
			flash_error(lang('no access permissions'));
			return ;
		} // if

		try {
			$object->subscribeUser(logged_user());
			ApplicationLogs::createLog($object, ApplicationLogs::ACTION_SUBSCRIBE, false, true, true, logged_user()->getId());
			flash_success(lang('success subscribe to object'));
		} catch (Exception $e) {
			flash_error(lang('error subscribe to object'));
		}
	} // subscribe

	/**
	 * Unsubscribe from object
	 *
	 * @param void
	 * @return null
	 */
	function unsubscribe() {
		ajx_current("reload");

		$id = array_var($_GET,'id');
		$object = Objects::findObject($id);
		if(!($object instanceof ApplicationDataObject)) {
			flash_error(lang('message dnx'));
			return;
		} // if

		if(!$object->canView(logged_user())) {
			flash_error(lang('no access permissions'));
			return;
		} // if

		try {
			$object->unsubscribeUser(logged_user());
			ApplicationLogs::createLog($object,ApplicationLogs::ACTION_UNSUBSCRIBE, false, null, true, logged_user()->getId());
			flash_success(lang('success unsubscribe to object'));
		} catch (Exception $e) {
			flash_error(lang('error unsubscribe to object'));
		}
	} // unsubscribe

	function send_reminders() {
		ajx_current("empty");
		try {
			$sent = Notifier::sendReminders();
			flash_success("success sending reminders", $sent);
		} catch (Exception $e) {
			flash_error($e->getMessage());
		}
	}

	/**
	 * Properties are sent as POST name:values
	 *
	 */
	function save_properties() {
		if (logged_user()->isGuest()) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		}
		ajx_current("empty");
		$id = array_var($_GET,'id');
		$manager = array_var($_GET,'manager');
		$object = Objects::findObject($id);
		if (!$object->canEdit(logged_user())) {
			return ;
		}
		try {
			$count = 0;
			foreach ($_POST as $n => $v) {
				$object->setProperty($n, $v);
				$count++;
			}
		} catch (Exception $e) {

		}
	}

	function untrash() {
		if (logged_user()->isGuest()) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		}
		$object_id = get_id('object_id');
		$object = Objects::findObject($object_id);
		if ($object instanceof ApplicationDataObject && $object->canDelete(logged_user())) {
			try {
				$errorMessage = null;
				DB::beginWork();
				$object->untrash($errorMessage);
				DB::commit();
				ApplicationLogs::createLog($object, ApplicationLogs::ACTION_UNTRASH);
				flash_success(lang("success untrash object"));
				if ($object instanceof Contact) self::reloadPersonsDimension();
			} catch (Exception $e) {
				$errorString = is_null($errorMessage) ? lang("error untrash objects", $error) : $errorMessage;
				flash_error($errorString);
				DB::rollback();
			}
		} else {
			flash_error(lang("no access permissions"));
		}
		ajx_current("back");
	}

	function delete_permanently() {
		if (logged_user()->isGuest()) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		}
		$object_id = get_id('object_id');
		$dont_reload = array_var($_GET, 'dont_reload');
		$object = Objects::findObject($object_id);
		if (($object instanceof ContentDataObject && $object->canDelete(logged_user()) && (!$object instanceof Contact || !$object->isUser()))) {
			try {
				$errorMessage = null;
				DB::beginWork();
				$object->delete($errorMessage);
				flash_success(lang("success delete object"));
				Hook::fire('after_object_delete_permanently', array($object_id), $ignored);
				DB::commit();
				ApplicationLogs::createLog($object, ApplicationLogs::ACTION_DELETE);
			} catch (Exception $e) {
				DB::rollback();
				if (is_null($errorMessage)) Logger::log($e->getMessage());
				$errorString = is_null($errorMessage)? lang("error delete object") : $errorMessage;
				flash_error($errorString);
			}
		} else {
			flash_error(lang("no access permissions"));
		}
		
		if($dont_reload){
			ajx_current("empty");
		}else{		
			ajx_current("back");		
		}
	}

	function trash() {
		if (logged_user()->isGuest()) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		}
		ajx_current("empty");
		$csvids = array_var($_GET, 'ids');
		if (!$csvids && array_var($_GET, 'object_id')) {
			$csvids = array_var($_GET, 'object_id');
			ajx_current("back");
		}
		$ids = explode(",", $csvids);
		$count_persons = 0;
		$count = 0;
		$err = 0;
		$errorMessage = null;
		foreach ($ids as $id) {
			try {
				$object = Objects::findObject($id);
				if ($object instanceof ContentDataObject && $object->canDelete(logged_user())) {
					$object->trash();
					Hook::fire('after_object_trash', $object, $null );/*
					ApplicationLogs::createLog($object, ApplicationLogs::ACTION_TRASH);*/
					$count++;
					if ($object instanceof Contact) $count_persons++;
				} else {
					$err++;
				}
			} catch (Exception $e) {
				$err++;
			}
		}
		if ($err > 0) {
			$errorString = is_null($errorMessage)? lang("error delete objects", $err) : $errorMessage;
			flash_error($errorString);
		} else {
			flash_success(lang("success trash objects", $count));
			if ($count_persons > 0) self::reloadPersonsDimension();
			Hook::fire('after_object_controller_trash', array_var($_GET, 'ids', array_var($_GET, 'object_id')), $ignored);
		}
	}
	
	/**
	 * Clears old objects in trash according to config option days_on_trash
	 *
	 */
	function purge_trash() {
		if (logged_user()->isGuest()) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		}
		if (logged_user()->isGuest()) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		}
		ajx_current("empty");
		try {
			$deleted = Trash::purge_trash();
			flash_success("success purging trash", $deleted);
		} catch (Exception $e) {
			flash_error($e->getMessage());
		}
	}
	
	function archive() {
		ajx_current("empty");
		$csvids = array_var($_GET, 'ids');
		if (!$csvids && array_var($_GET, 'object_id')) {
			$csvids = array_var($_GET, 'object_id');
			ajx_current("back");
		}
		$ids = explode(",", $csvids);
		$count_persons = 0;
		$count = 0;
		$err = 0;
		foreach ($ids as $id) {
			try {
				$object = Objects::findObject($id);
				if ($object instanceof ContentDataObject && $object->canEdit(logged_user())) {
					$object->archive();
					ApplicationLogs::createLog($object, ApplicationLogs::ACTION_ARCHIVE);
					$count++;
					if ($object instanceof Contact) $count_persons++;
				} else {
					$err++;
				}
			} catch (Exception $e) {
				$err++;
			}
		}
		if ($err > 0) {
			flash_error(lang("error archive objects", $err));
		} else {
			flash_success(lang("success archive objects", $count));
			if ($count_persons > 0) self::reloadPersonsDimension();
		}
	}
	
	function unarchive() {
		if (logged_user()->isGuest()) {
			flash_error(lang('no access permissions'));
			ajx_current("empty");
			return;
		}
		$object_id = get_id('object_id');
		$object = Objects::findObject($object_id);
		if ($object instanceof ApplicationDataObject && $object->canEdit(logged_user())) {
			try {
				DB::beginWork();
				$object->unarchive();
				DB::commit();
				ApplicationLogs::createLog($object, ApplicationLogs::ACTION_UNARCHIVE);
				flash_success(lang("success unarchive objects", 1));
				if ($object instanceof Contact) self::reloadPersonsDimension();
			} catch (Exception $e) {
				DB::rollback();
				flash_error(lang("error unarchive objects", 1));
			}
		} else {
			flash_error(lang("no access permissions"));
		}
		ajx_current("back");
	}
	

	function popup_reminders() {
		ajx_current("empty");
		
		// extra data to send to interface
		$extra_data = array();
		
		// if no new popup reminders don't make useless queries
		if (GlobalCache::isAvailable()) {
			$check = GlobalCache::get('check_for_popup_reminders_'.logged_user()->getId(), $success);
			if ($success && $check == 0) return;
		}
		
		$reminders = ObjectReminders::getDueReminders("reminder_popup");
		$popups = array();
		foreach ($reminders as $reminder) {
			$context = $reminder->getContext();
			
			if(str_starts_with($context, "mails_in_outbox")){
				preg_match('!\d+!', $context, $matches);
				evt_add("popup", array(
				'title' => lang("mails_in_outbox reminder"),
				'message' => lang("mails_in_outbox reminder desc", $matches[0]),
				'type' => 'reminder',
				'sound' => 'info'
							));
				$reminder->delete();
				continue;
			}
			
			$object = $reminder->getObject();			
			$type = $object->getObjectTypeName();
			$date = $object->getColumnValue($reminder->getContext());
			if (!$date instanceof DateTimeValue) continue;
			if ($object->isTrashed()) {
				$reminder->delete();
				continue;
			}
			// convert time to the user's locale
			$timezone = logged_user()->getTimezone();
			if ($date->getTimestamp() + 5*60 < DateTimeValueLib::now()->getTimestamp()) {
				// don't show popups older than 5 minutes
				//$reminder->delete();
				//continue;
			}
			if ($reminder->getUserId() == 0) {
				if (!$object->isSubscriber(logged_user())) {
					// reminder for subscribers and user is not subscriber
					continue;
				}
			} else if ($reminder->getUserId() != logged_user()->getId()) {
				continue;
			}
			if ($context == "due_date" && $object instanceof ProjectTask) {
				if ($object->isCompleted()) {
					// don't show popups for completed tasks
					$reminder->delete();
					continue;
				}
			}
			
			$url = $object->getViewUrl();
			$link = '<a href="#" onclick="og.openLink(\''.$url.'\');return false;">'.clean($object->getObjectName()).'</a>';
			evt_add("popup", array(
				'title' => lang("$context $type reminder", clean($object->getObjectName())),
				'message' => lang("$context $type reminder desc", $link, format_datetime($date)),
				'type' => 'reminder',
				'sound' => 'info'
				));
			if ($reminder->getUserId() == 0) {
				// reminder is for all subscribers, so change it for one reminder per user (except logged_user)
				// otherwise if deleted it won't notify other subscribers and if not deleted it will keep notifying
				// logged user
				$subscribers = $object->getSubscribers();
				foreach ($subscribers as $subscriber) {
					if ($subscriber->getId() != logged_user()->getId()) {
						$new = new ObjectReminder();
						$new->setContext($reminder->getContext());
						$new->setDate($reminder->getDate());
						$new->setMinutesBefore($reminder->getMinutesBefore());
						$new->setObject($object);
						$new->setUser($subscriber);
						$new->setType($reminder->getType());
						$new->save();
					}
				}
			}
			$reminder->delete();
		}
		
		// popup reminders already checked for logged user
		if (GlobalCache::isAvailable()) {
			$today_next_reminders = ObjectReminders::findAll(array(
				'conditions' => array("`date` > ? AND `date` < ?", DateTimeValueLib::now(), DateTimeValueLib::now()->endOfDay()),
				'limit' => config_option('cron reminder limit', 100)
			));
			
			if (count($today_next_reminders) == 0) {
				GlobalCache::update('check_for_popup_reminders_'.logged_user()->getId(), 0, 60*30);
			}
		}
		
		// check for member modifications
		if (isset($_POST['dims_check_date'])) {
			$dims_check_date = new DateTimeValue($_POST['dims_check_date']);
			$dims_check_date_sql = $dims_check_date->toMySQL();
			$members_log_count = ApplicationLogs::instance()->count("member_id>0 AND created_on>'$dims_check_date_sql'");
			if ($members_log_count > 0) {
				$extra_data['reload_dims'] = 1;
			}
		}
		
		ajx_extra_data($extra_data);
	}

	function createMinimumUser($email, $compId) {
		$contact = Contacts::getByEmail($email);
		$posArr = strpos_utf($email, '@') === FALSE ? null : strpos($email, '@');
		$user_data = array(
			'username' => $email,
			'display_name' => $posArr != null ? substr_utf($email, 0, $posArr) : $email,
			'email' => $email,
			'contact_id' => isset($contact) ? $contact->getId() : null,
			'password_generator' => 'random',
			'timezone' => isset($contact) ? $contact->getTimezone() : 0,
			'create_contact' => !isset($contact),
			'company_id' => $compId,
			'send_email_notification' => true,
		); // array

		$user = null;
		$user = create_user($user_data, false, '');

		return $user;
	}

	function get_co_types() {
		$object_type = array_var($_GET, 'object_type', '');
		if($object_type != ''){
			$types = ProjectCoTypes::findAll(array("conditions" => "`object_manager` = ".DB::escape($object_type)));
			$co_types = array();
			foreach($types as $type){
				$t = array();
				$t['id'] = $type->getId();
				$t['name'] = $type->getName();
				$co_types[] = $t;
			}
			ajx_current("empty");
			ajx_extra_data(array("co_types" => $co_types));
		}
	}
	
	function re_render_custom_properties() {
		
		$object = Objects::findObject(array_var($_GET, 'id'));
		if (!$object) {
			// if id == 0 object is new, then a dummy object is created to render the properties.
			$object = new ProjectMessage();
		}
		
		$html = render_object_custom_properties($object, array_var($_GET, 'req'), array_var($_GET, 'co_type'));
		
		$scripts = array();
		$initag = "<script>";
		$endtag = "</script>";
		
		$pos = strpos($html, $initag);
		while ($pos !== FALSE) {
			$end_pos = strpos($html, $endtag, $pos);
			if ($end_pos === FALSE) break;
			$ini = $pos + strlen($initag);
			$sc = substr($html, $ini, $end_pos - $ini);
			if (!str_starts_with(trim($sc), "og.addTableCustomPropertyRow")) {// do not add repeated functions
				$scripts[] = $sc;
			}
			$pos = strpos($html, $initag, $end_pos);
		}
		foreach ($scripts as $sc) {
			$html = str_replace("$initag$sc$endtag", "", $html);
		}

		ajx_current("empty");
		ajx_extra_data(array("html" => $html, 'scripts' => implode("", $scripts)));
	}
        
        function created_event_google_calendar($object,$event){
            require_once 'Zend/Loader.php';

            Zend_Loader::loadClass('Zend_Gdata');
            Zend_Loader::loadClass('Zend_Gdata_AuthSub');
            Zend_Loader::loadClass('Zend_Gdata_ClientLogin');
            Zend_Loader::loadClass('Zend_Gdata_Calendar');
            
            $users = ExternalCalendarUsers::findByContactId();
            $calendar = ExternalCalendars::findById($event->getExtCalId());

            $user = $users->getAuthUser();
            $pass = $users->getAuthPass();
            $service = Zend_Gdata_Calendar::AUTH_SERVICE_NAME;

            $client = Zend_Gdata_ClientLogin::getHttpClient($user,$pass,$service);

            $calendarUrl = 'http://www.google.com/calendar/feeds/'.$calendar->getCalendarUser().'/private/full';

            $gdataCal = new Zend_Gdata_Calendar($client);
            $newEvent = $gdataCal->newEventEntry();

            $newEvent->title = $gdataCal->newTitle($event->getObjectName());
            $newEvent->content = $gdataCal->newContent($event->getDescription());

            $star_time = explode(" ",$event->getStart()->format("Y-m-d H:i:s"));
            $end_time = explode(" ",$event->getDuration()->format("Y-m-d H:i:s"));

            if($event->getTypeId() == 2){
                $when = $gdataCal->newWhen();
                $when->startTime = $star_time[0];
                $when->endTime = $end_time[0];
                $newEvent->when = array($when);
            }else{                                    
                $when = $gdataCal->newWhen();
                $when->startTime = $star_time[0]."T".$star_time[1].".000-00:00";
                $when->endTime = $end_time[0]."T".$end_time[1].".000-00:00";
                $newEvent->when = array($when);
            }   

            // insert event
            $createdEvent = $gdataCal->insertEvent($newEvent, $calendarUrl);
            
            $event_id = explode("/",$createdEvent->id->text);
            $special_id = end($event_id); 
            $event->setSpecialID($special_id);
            $event->setExtCalId($calendar->getId());
            $event->save();
        }

	function get_cusotm_property_columns() {
		$grouped = array();
		$cp_rows = DB::executeAll("SELECT cp.id, cp.name as cp_name, ot.name as obj_type FROM ".TABLE_PREFIX."custom_properties cp INNER JOIN ".TABLE_PREFIX."object_types ot on ot.id=cp.object_type_id ORDER BY ot.name");
		if (is_array($cp_rows)) {
			foreach ($cp_rows as $row) {
				if (!isset($grouped[$row['obj_type']])) $grouped[$row['obj_type']] = array();
				$grouped[$row['obj_type']][] = array('id' => $row['id'], 'name' => $row['cp_name']);
			}
		}
		ajx_current("empty");
		ajx_extra_data(array('properties' => $grouped));
	}
	
	//set user config option value
	function set_user_config_option_value() {
		ajx_current("empty");
		if(!logged_user() instanceof Contact) return;
		$name = array_var($_GET,'config_option_name');
		$value = array_var($_GET,'config_option_value');
		set_user_config_option($name, $value, logged_user()->getId());
	}
	
}