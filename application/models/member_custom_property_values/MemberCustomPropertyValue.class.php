<?php

/**
 * MemberCustomPropertyValue class
 */
class MemberCustomPropertyValue extends BaseMemberCustomPropertyValue {

	/**
	 * Construct the object
	 *
	 * @param void
	 * @return null
	 */
	function __construct() {
		parent::__construct();
	} // __construct

	/**
	 * Validate before save
	 *
	 * @access public
	 * @param array $errors
	 * @return null
	 */
	function validate(&$errors) {
		$cp = MemberCustomProperties::getCustomProperty($this->getCustomPropertyId());
		if($cp instanceof MemberCustomProperty){
			if($cp->getIsRequired() && ($this->getValue() == '')){
				$errors[] = lang('custom property value required', $cp->getName());
			}
			if($cp->getType() == 'numeric'){
				if($cp->getIsMultipleValues()){
					foreach(explode(',', $this->getValue()) as $value){
						if($value != '' && !is_numeric($value)){
							$errors[] = lang('value must be numeric', $cp->getName());
						}
					}
				}else{
					if($this->getValue() != '' && !is_numeric($this->getValue())){
						$errors[] = lang('value must be numeric', $cp->getName());
					}
				}
			}
		}//if
	} // validate

	
	function format_value() {
		$formatted = "";
		
		$cp = MemberCustomProperties::getCustomProperty($this->getCustomPropertyId());
		if($cp instanceof MemberCustomProperty) {
			switch ($cp->getType()) {
				case 'text':
				case 'numeric':
				case 'memo':
					$formatted = $this->getValue();
					break;
				case 'contact':
					$c = Contacts::findById($this->getValue());
					$formatted = $c instanceof Contact ? clean($c->getObjectName()) : '';
					break;
				case 'boolean':
					$formatted = '<div class="db-ico ico-'. ($this->getValue() ? 'complete' : 'delete') .'">&nbsp;</div>';
					break;
				case 'date':
					$formatted = format_date($this->getValue());
					break;
				case 'list':
					$formatted = $this->getValue();
					break;
				case 'table':
					$formatted = $this->getValue();
					break;
				default:
					$formatted = $this->getValue();
			}
		}
		
		return $formatted;
	}

} // ObjectPropertyValue

?>