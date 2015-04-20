<?php
$genid = gen_id();

$members = array();

$context = active_context();
if(isset($context)){
	foreach ($context as $selection) {
		if ($selection instanceof Member) {
			$members[] = $selection;
		}
	}	
}

if (count($members) > 0) {
	include_once 'template.php';
}
