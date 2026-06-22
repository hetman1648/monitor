<?php

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");


	$sql = "SELECT project_id FROM projects WHERE is_closed=0 and parent_project_id=79 ";
	$db->query($sql);
	$projects = array();
	while ($db->next_record()) {
		$projects[] = $db->f($project_id);
	}

	foreach ($projects as $project_id) {
		count_project_time($project_id);
		# code...
	}


}
?>