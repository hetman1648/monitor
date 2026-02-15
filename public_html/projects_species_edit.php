<?php

	include("./includes/common.php");
	CheckSecurity(1);

	$t = new iTemplate($sAppPath);
	$t->set_file("main","projects_species_edit.html");

	$type_id = (int)GetParam("species_id");
	
	if ($type_id == 0) {
		header("location:projects_species.php");
		exit;
	}
	
	if (GetParam("update") != '') {
		$db->query("DELETE FROM productivity_project_species WHERE species_id = ".ToSQL($type_id, "integer"));
		
		if (GetParam("selected_projects") != '') {
			$selected_projects = explode(",", GetParam("selected_projects"));
			foreach ($selected_projects as $project_id) {
				$sql = "INSERT INTO productivity_project_species";
				$sql .= " (project_id, species_id)";
				$sql .= " VALUES (";
				$sql .= ToSQL($project_id, "integer").", ".ToSQL($type_id, "integer").")";
				$db->query($sql);
			}
		}
		header("location:projects_species.php");
		exit;
	}
	
	
	$t->set_var("type_id", $type_id);
	
	$sql = "SELECT species FROM productivity_species";
	$sql .= " WHERE species_id =".ToSQL($type_id, "integer");
	$db->query($sql);
	$db->next_record();
	$t->set_var("type_title", $db->f("species"));

	$selected_projects = array();

	$sql = "SELECT * FROM projects p";
	$sql .= " JOIN productivity_project_species ps ON ps.project_id = p.project_id";
	$sql .= " WHERE p.is_closed = 0 AND ps.species_id = ".ToSQL($type_id, "integer");
	$sql .= " ORDER BY project_title";
	$db->query($sql);
	
	if ($db->next_record()) {
		do {
			$selected_projects[] = $db->f("project_id");
			$t->set_var("project_id", $db->f("project_id"));
			$t->set_var("project_title", $db->f("project_title"));
			$t->parse("selected_projects");
		} while ($db->next_record());
	}	else {
		$t->set_var("selected_projects", "");
	}
	
	$sql = "SELECT * FROM projects";
	$sql .= " where is_closed = 0";
	if (count($selected_projects)) {
		$sql .= " AND project_id NOT IN (".implode(',', $selected_projects).")";
	}
	$sql .= " ORDER BY project_title";
	$db->query($sql);
	
	while ($db->next_record()) {
		$t->set_var("project_id", $db->f("project_id"));
		$t->set_var("project_title", $db->f("project_title"));
		$t->parse("all_projects");
	}

	$t->pparse("main");

	
?>