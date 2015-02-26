<?php

  /**
  * ObjectMembers
  *
  * @author Diego Castiglioni <diego.castiglioni@fengoffice.com>
  */
  class ObjectMembers extends BaseObjectMembers {
    
    	
  		static function addObjectToMembers($object_id, $members_array){
  			
  			foreach ($members_array as $member){
  				$values = "(".$object_id.",".$member->getId().",0)";
  				DB::execute("INSERT INTO ".TABLE_PREFIX."object_members (object_id,member_id,is_optimization) VALUES $values ON DUPLICATE KEY UPDATE object_id=object_id");
  			}
  			
  			foreach ($members_array as $member){
  				$parents = $member->getAllParentMembersInHierarchy(false, false);
  				$stop = false;
  				foreach ($parents as $parent){
  					if (!$stop){
	  					$exists = self::findOne(array("conditions" => array("`object_id` = ? AND `member_id` = ? ", $object_id, $parent->getId())))!= null;
	  					if (!$exists){
	  						$values = "(".$object_id.",".$parent->getId().",1)";
  							DB::execute("INSERT INTO ".TABLE_PREFIX."object_members (object_id,member_id,is_optimization) VALUES $values ON DUPLICATE KEY UPDATE object_id=object_id");
	  					}
	  					else $stop = true;	
  					} 
  				}
  			}
  		}
  		
  		
		/**
		 * Removes the object from those members where the user can see the object(and its corresponding parents)
		 * 
		 */
  		static function removeObjectFromMembers(ContentDataObject $object, Contact $contact, $context_members, $members_to_remove = null){
  			
  			if (is_null($members_to_remove)) {
  				$member_ids = array_flat(DB::executeAll("SELECT member_id FROM ".TABLE_PREFIX."object_members WHERE object_id = " . $object->getId()));
  			} else {
  				$member_ids = $members_to_remove;
  			}
  			
  			foreach($member_ids as $id){
				
				$member = Members::findById($id);
				if (!$member instanceof Member) continue;
				
				//can write this object type in the member
				$can_write = $object->canAddToMember($contact, $member, $context_members);
				
				
				if ($can_write){
					$om = self::findById(array('object_id' => $object->getId(), 'member_id' => $id));
					if ($om instanceof ObjectMember) {
						$om->delete();
					}
					
					$stop = false;
					while ($member->getParentMember() != null && !$stop){
						$member = $member->getParentMember();
						$obj_member = ObjectMembers::findOne(array("conditions" => array("`object_id` = ? AND `member_id` = ? AND 
									`is_optimization` = 1", $object->getId(),$member->getId())));
						if (!is_null($obj_member)) {
							$obj_member->delete();
						}
						else $stop = true;
					}
				}
			}
  		}
  		
  		
  		static function getMemberIdsByObject($object_id){
  			if ($object_id) {
	  			$db_res = DB::execute("SELECT member_id FROM ".TABLE_PREFIX."object_members WHERE object_id = $object_id AND is_optimization = 0");
	  			$rows = $db_res->fetchAll();
  			} else {
  				return array();
  			}
  				
  			$member_ids = array();
  			if(count($rows) > 0){
  				foreach ($rows as $row){
  					$member_ids[] = $row['member_id'];
  				}
  			}
  			
  			return $member_ids;
  		}
  		
		/* ED150212
		 * Returns members of objects array.
		 *
		 * @param $object_ids : object ids we search members
		 * @param &$members_by_objects : returns array(object_id => $member))
		 * @param $root_member_ids : specify minimal depth of members
		 * @returns array($memberId => $member_properties) ordered by full path
		 */
  		static function getMembersByObjects($object_ids, &$members_by_objects = false, $root_member_ids = false){
  			if (is_array($object_ids) && count($object_ids)) {
				
				$sql = "SELECT m.*, r.object_id AS related_object_id
				FROM ".TABLE_PREFIX."object_members r
				JOIN ".TABLE_PREFIX."members m
					ON r.member_id = m.id
				".//WHERE r.object_id IN (" . substr(str_repeat(', ?', count($object_ids)), 1) . ")
				"
				WHERE r.object_id IN (" . implode(', ', $object_ids) . ")
				
				AND m.dimension_id = 1
				
				"//AND r.is_optimization = 0 "./* 0 : tous les membres, y compris ceux des invités. 1 : chemin complet des membres*/ 
				
				;
				$params = $object_ids;
				if(is_array($root_member_ids) && count($root_member_ids)){
					$sql .= " AND m.depth >= (
						SELECT MIN(depth)
						FROM ".TABLE_PREFIX."members
						"/*WHERE id IN (" . substr(str_repeat(', ?', count($root_member_ids)), 1) . ")*/
						." WHERE id IN (" . implode(', ', $root_member_ids) . ")
					)";
					$params = array_merge($params, $root_member_ids);
				}
				else
					$sql .= " AND m.depth > 1";
				
				$sql .= " ORDER BY m.depth, m.parent_member_id";
				//print_r("<pre>$sql</pre>");
	  			//var_dump($sql, $object_ids);
	  			$db_res = DB::execute($sql, $params);
	  			$rows = $db_res->fetchAll();
  			} else {
  				return array();
  			}
  				
  			if(!is_array($members_by_objects))
				$members_by_objects = array();
  			$members = array();
  			$rows_by_member = array();
  			if(count($rows) > 0){
				$parents = array();
  				$paths = array();
  				foreach ($rows as $row){
					if(!isset($rows_by_member[strval($row['id'])])){
						if(!isset($rows_by_member[strval($row['parent_member_id'])]))
							$row['path'] = $row['name'];
						else {
							$rows_by_member[strval($row['parent_member_id'])]['has_child'] = true;
							$row['path'] = $rows_by_member[strval($row['parent_member_id'])]['path'] . '/' . $row['name'];
						}
						$rows_by_member[strval($row['id'])] = $row;
					}
				}
				//sort by tree path
				function compare_members_by_path($a, $b){
				    if ($a['path'] == $b['path']) {
					return 0;
				    }
				    return ($a['path'] < $b['path']) ? -1 : 1;
				}
				usort($rows_by_member, 'compare_members_by_path');
								
  				foreach ($rows_by_member as $memberId => $row){
					  //if(!isset($members_by_objects[strval($row['related_object_id'])])){
					  //	$members_by_objects[strval($row['related_object_id'])] = array();
					  //}
					  if(!isset($members[strval($row['id'])])){
						  $member = new Member();
						  $member->loadFromRow($row);
						  $members[strval($row['id'])] = $member;
						  $member->setHasChild(isset($row['has_child']) && $row['has_child']);
					  }
					  //else
					  //	  $member = $members[strval($row['id'])];
					  //if(!isset($parents[strval($row['parent_member_id'])]))
					  //	  $parents[strval($row['parent_member_id'])] = array();
					  //$parents[strval($row['parent_member_id'])][] = $member;
					  //$members_by_objects[strval($row['related_object_id'])][strval($row['id'])] = $member;
				}
				// set member with max depth
				foreach ($rows as $row){
					
					  $member = $members[strval($row['id'])];
					  if(!isset($members_by_objects[strval($row['related_object_id'])]))
						  $members_by_objects[strval($row['related_object_id'])] = $member;
					  else if($member->getDepth() > $members_by_objects[strval($row['related_object_id'])]->getDepth())
	      					  $members_by_objects[strval($row['related_object_id'])] = $member;
				}
  			}
  			
  			return $members;
  		}
  		
  		
  		private $cached_object_members = array();
  		function getCachedObjectMembers($object_id, $all_object_ids = null) {
  			if (!isset($this->cached_object_members[$object_id])) {
  				if (is_array($all_object_ids) && count($all_object_ids) > 0) {
  					$obj_cond = "AND object_id IN (".implode(",", $all_object_ids).")";
  				} else {
  					$obj_cond = "AND object_id = $object_id";
  				}
  				$db_res = DB::execute("SELECT object_id, member_id FROM ".TABLE_PREFIX."object_members WHERE is_optimization = 0 $obj_cond");
  				$rows = $db_res->fetchAll();
  				foreach ($rows as $row) {
  					if (!isset($this->cached_object_members[$row['object_id']])) $this->cached_object_members[$row['object_id']] = array();
  					$this->cached_object_members[$row['object_id']][] = $row['member_id'];
  				}
  				
  				if (is_array($all_object_ids)) {
  					foreach ($all_object_ids as $oid) {
  						if (!isset($this->cached_object_members[$oid])) $this->cached_object_members[$oid] = array();
  					}
  				}
  			}
  			return array_var($this->cached_object_members, $object_id, array());
  		}
  		
  		
  		
	      static function getMembersByObject($object_id){
  			$ids = self::getMemberIdsByObject($object_id);
  			$members = Members::findAll(array("conditions" => "`id` IN (".implode(",", $ids).")"));
  			
  			return $members;				  
  		}
  		
  		
  		static function getMembersByObjectAndDimension($object_id, $dimension_id, $extra_conditions = "") {
  			$sql = "
  				SELECT m.* 
  				FROM ".TABLE_PREFIX."object_members om 
  				INNER JOIN ".TABLE_PREFIX."members m ON om.member_id = m.id 
  				WHERE 
  					dimension_id = '$dimension_id' AND 
  					om.object_id = '$object_id' 
  					$extra_conditions
  				ORDER BY m.name";
  			
  			$result = array();
  			$rows = DB::executeAll($sql);
  			if (!is_array($rows)) return $result;
  			
  			foreach ($rows as $row) {
  				$member = new Member();
  				$member->setFromAttributes($row);
  				$member->setId($row['id']);
  				$result[] = $member;
  			}
  			return $result;
  		}
     
  		
  } // ObjectMembers 

?>