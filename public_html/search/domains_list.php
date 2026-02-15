<?php

$root_inc_path = "../";
include ("../includes/common.php");

$users = array(); //array with all users 
$sql = " select domain_url as domain FROM tasks_domains";
$db->query($sql);
while ($db->next_record()) {
	$users[] = $db->f("domain");
}
echo json_encode($users);
?>