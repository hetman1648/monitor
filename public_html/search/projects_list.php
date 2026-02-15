<?php

$root_inc_path = "../";
include ("../includes/common.php");

$projects = array(); //array with all tasks we've found
$sql = "SELECT project_id, project_title FROM projects";
$db->query($sql);
while ($db->next_record()) {
	$projects[] = $db->f("project_title");
}
echo json_encode($projects);
?>