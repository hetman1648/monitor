<?php

$root_inc_path = "../";
include ("../includes/common.php");

$users = array(); //array with all users 
$sql = "SELECT user_id, first_name, last_name FROM users";
$db->query($sql);
while ($db->next_record()) {
	$users[] = $db->f("first_name")." ".$db->f("last_name");
}
echo json_encode($users);
?>