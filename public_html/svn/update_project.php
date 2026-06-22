<?php

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");

$project_id = GetParam("project_id");
//sometimes javascript IDs contain extra characters (like "est","project"..)
//- we remove them to get project_id
$project_id = preg_replace("/[^0-9]/","",$project_id);

$xml_content = "";
$operation = GetParam("operation");
if ($project_id && is_numeric($project_id)) {

	$sql = "SELECT CONCAT(first_name, ' ',last_name) AS user_name, first_name, user_id FROM users ";
	$sql.= " WHERE is_deleted is null AND privilege_id=4 ORDER BY user_name";
	$users = array(); $users_first = array();
	$db->query($sql); 
	while ($db->next_record()) {
	    $users[$db->f("user_id")]       = $db->f("user_name");
	    $users_first[$db->f("user_id")] = $db->f("first_name");
	}

	


	$sql = "SELECT * FROM projects_statuses ORDER BY status_desc ";
	$statuses = array();
	$db->query($sql); 
	while ($db->next_record()) {
		$statuses[$db->f("project_status_id")] = $db->f("status_desc");
	}

	$sql = "SELECT project_title,responsible_user_id,project_status_id FROM projects WHERE project_id=".$project_id;
	$db->query($sql);
	$project_title = ""; $responsible_user_id = 0;
	if ($db->next_record()) {
		$cur_project_title       = $db->f("project_title");
		$cur_responsible_user_id = $db->f("responsible_user_id");
		$cur_project_status_id   = $db->f("project_status_id");
	} else die ("ERROR: no project found");

	//close project
	if ($operation == "close_project") {
		
		$sql = "UPDATE projects SET is_closed=1 ";
		$sql.= " WHERE project_id= ".$project_id;
		$db->query($sql);

		$note = "Project has been closed by ".$users[GetSessionParam("UserID")];

		$sql = " INSERT INTO project_notes (project_id, date_added,note,user_id ) ";
		$sql.= " VALUES ($project_id, NOW(), '".addslashes($note)."',". GetSessionParam("UserID"). ")";
		$db->query($sql);
	

		echo "Project '$project_title' has been closed";
		exit;
	}

	$fields = array("project_title","project_status_id","responsible_user_id");
	foreach ($fields as $field_name) {
		$fields[$field_name] = GetParam($field_name);
	}
	if ($operation == "add_note") {
		$note = trim(GetParam("note"));
		
		if (strlen($note)) {
			$sql = " INSERT INTO project_notes (project_id, date_added,note,user_id ) ";
			$sql.= " VALUES ($project_id, NOW(), '".addslashes($note)."',". GetSessionParam("UserID"). ")";
			$db->query($sql);
			echo "The note has been added";
		}
		exit;
	}


	if ($operation == "save_project") {
		$project_status_id   = $fields["project_status_id"];
		$project_title       = $fields["project_title"];
		$responsible_user_id = $fields["responsible_user_id"];
		
		$sql = "UPDATE projects SET project_status_id=".number_format($project_status_id,0);
		$sql.= " ,project_title='"      .addslashes($project_title)."'";
		$sql.= " ,responsible_user_id=" .number_format($responsible_user_id,0,"","");
		$sql.= " WHERE project_id= ".$project_id;
		$db->query($sql);

		$changes = "";
		if ($cur_project_title != $project_title) {
			$changes.= "Project title changed from '$cur_project_title' to '$project_title'";
		}
		if ($cur_responsible_user_id != $responsible_user_id) {
			$changes.= "User responsible for project changed from ".$users[$cur_responsible_user_id];
			$changes.= " to ".$users[$responsible_user_id];
		}

		if ($cur_project_status_id != $project_status_id) {
			$changes.= "Project status has been changed from ".$statuses[$cur_project_status_id];
			$changes.= " to ".$statuses[$project_status_id];
		}
		if (strlen($changes)) { 
			$sql = " INSERT INTO project_notes (project_id, date_added,note,user_id ) ";
			$sql.= " VALUES ($project_id, NOW(), '".addslashes($changes)."',". GetSessionParam("UserID"). ")";
			$db->query($sql);
		}
	
		echo "Project '". $project_title."' settings have been updated ";
	}

}
?>