<?php

	include ("./includes/common.php");
	include ("./includes/date_functions.php");
	CheckSecurity(2);

	if (getsessionparam("privilege_id") == 9) {
		header("Location: index.php");
		exit;
	}

	$action		= GetParam("action");
	$project_status_id	= GetParam("project_status_id");

	$parent_project_id	= GetParam("parent_project_id");	
	$status_desc = GetParam("status_desc");
	$status_order = GetParam("status_order");
	$color = GetParam("color");
	$is_completed = GetParam("is_completed");
	
	if (!GetParam("parent_project_id") && !GetParam("status_desc")) {
		$sql = "SELECT parent_project_id, status_desc, status_order, color, is_completed FROM projects_statuses WHERE project_status_id=".$project_status_id;
		$db->query($sql);
		if ($db->next_record()) {
			$parent_project_id = $db->f("parent_project_id");
			$status_desc = $db->f("status_desc");
			$status_order = $db->f("status_order");
			$color = $db->f("color");
			$is_completed = $db->f("is_completed");
		}		
	}	

	$T = new iTemplate("./templates", array("page"=>"edit_project_status.html"));
	
	if ($action == "Add") {	
		$sql = " INSERT INTO projects_statuses (parent_project_id, status_desc, status_order, color, is_completed) ";
		$sql.= " VALUES (".ToSQL($parent_project_id,"integer").",".ToSQL($status_desc,"text").",".ToSQL($status_order,"integer");
		$sql.= " ,".ToSQL($color,"text").",".ToSQL($is_completed,"integer",false).")";
		$db->query($sql);
	} elseif ($action == "Update") {
		$sql = "UPDATE projects_statuses SET parent_project_id = ".ToSQL($parent_project_id,"integer");
		$sql.= ", status_desc = ".ToSQL($status_desc,"text");
		$sql.= ", status_order = ".ToSQL($status_order,"integer");
		$sql.= ", color = ".ToSQL($color,"text");
		$sql.= ", is_completed = ".ToSQL($is_completed,"integer",false);
		$sql.= " WHERE project_status_id=".ToSQL($project_status_id, "integer");
		$db->query($sql);
	}
	
	if (strlen($action)) {
		header("Location: projects_statuses.php?parent_project_id=".$parent_project_id);
	}

	if ($project_status_id) {
		$T->set_var("action_value", "Update");
	} else {
		$T->set_var("action_value", "Add");
	}	
	$T->set_var("status_desc", $status_desc);
	$T->set_var("status_order", $status_order);
	$T->set_var("color", $color);
	$T->set_var("project_status_id", $project_status_id);
	if ($is_completed) {
		$T->set_var("is_completed_checked", "checked");
	} else {
		$T->set_var("is_completed_checked", "");
	}
	
	if ($parent_project_id) {
		$sql = " SELECT is_closed FROM projects WHERE project_id=".ToSQL($parent_project_id, "integer");
		$db->query($sql);
		if ($db->next_record() && $db->f("is_closed")==1) {
			$parent_project_closed = 1;
		} else {
			$parent_project_closed = 0;
		}
	}
	
	if ($parent_project_id && $parent_project_closed) {
		$project_where = " WHERE (parent_project_id IS NULL OR parent_project_id=0) ";
	} else {
		$project_where = " WHERE (parent_project_id IS NULL OR parent_project_id=0) AND is_closed=0 ";
	}
	
	get_select_options("projects", "project_id", "project_title", $parent_project_id, $project_where, "project_title", 
		"parent_project_id_value", "parent_project_id_description", "parent_project_id", false);	

	$T->pparse("page", false);

?>