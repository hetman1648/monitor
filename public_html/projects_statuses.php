<?php

	include ("./includes/common.php");
	include ("./includes/date_functions.php");
	CheckSecurity(2);

	if (getsessionparam("privilege_id") == 9) {
		header("Location: index.php");
		exit;
	}

	$action				= strtolower(GetParam("action"));
	$project_status_id	= GetParam("project_status_id");
	$parent_project_id	= GetParam("parent_project_id");
	$error_message		= "";

	$T = new iTemplate("./templates", array("page"=>"projects_statuses.html"));
	
	if ($action == "delete" && $project_status_id) {	
		$sql = "DELETE FROM projects_statuses WHERE project_status_id = ".ToSQL($project_status_id, "integer");
		$db->query($sql);
	}

	$T->set_var("parent_project_id", $parent_project_id);
	$T->set_var("project_status", "");
	
	$sql = " SELECT project_title, parent_project_id FROM projects WHERE project_id=".ToSQL($parent_project_id, "integer");
	$db->query($sql);
	if ($db->next_record()) {
		$T->set_var("project_title", $db->f("project_title"));
		$parent_parent_project_id = $db->f("parent_project_id");
		if ($parent_parent_project_id) {
			$parent_project_id = $parent_parent_project_id;
		}

			$sql = " SELECT project_status_id, status_desc, color, parent_project_id, is_completed ";
			$sql.= " FROM projects_statuses WHERE parent_project_id=".ToSQL($parent_project_id, "integer");
			$sql.= " ORDER BY status_order ASC ";
			$db->query($sql);
			while ($db->next_record()) {
				$T->set_var("status_desc", $db->f("status_desc"));
				$T->set_var("color", $db->f("color"));
				if ($db->f("is_completed")) {
					$T->set_var("completed", "Completed");
				} else {
					$T->set_var("completed", "In progress");
				}
				$T->set_var("project_status_id", $db->f("project_status_id"));				
				$T->parse("project_status", true);
			}			
	} else {
		$error_message = "Such project doesn't exist";
	}
	
	if (strlen($error_message)) {
		$T->set_var("error_message", $error_message);
		$T->parse("error", false);
	} else {
		$T->set_var("error", "");
	}
	
	$T->pparse("page", false);

?>