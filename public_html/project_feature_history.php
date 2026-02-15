<?php
	include("./includes/common.php");
	
	$t = new iTemplate($sAppPath);
	$t->set_file("main", "project_feature_history.html");
	
	CheckSecurity(1);	
	
	$feature_id = GetParam("feature_id");
	$field_name = GetParam("field_name");
	$field_name = str_replace("feature_", "", $field_name);
	
	if (!(in_array($field_name, array(
			"title", "status_id", "task_id", "in_cvs", "on_test", "on_live", "description", "howtotest", "files"
	)))) {
		echo "Wrong Field Name"; exit;
	}
	
	$sql  = " SELECT f.title, f.creation_date, f.$field_name, ";
	$sql .= " u.first_name, u.last_name FROM (project_features f ";
	$sql .= " LEFT JOIN users u ON u.user_id=f.created_person_id) ";
	$sql .= " WHERE f.id=" . ToSQL($feature_id, "integer", false);
	$db->query($sql);
	if ($db->next_record()) {
		$feature_title = $db->f("title");
		$creation_date = $db->f("creation_date");
		$created_user  = $db->f("first_name") . " " . $db->f("last_name");
		$current_value = $db->f($field_name);
		
		$t->set_var("feature_title", $feature_title);
		$t->set_var("creation_date", $creation_date);
		$t->set_var("created_user",  $created_user);
		$t->set_var("current_value", $current_value);
		
		$sql  = " SELECT h.old_value, h.new_value, h.modified_date, ";
		$sql .= " u.first_name, u.last_name FROM (project_features_fields_history h ";
		$sql .= " LEFT JOIN users u ON u.user_id=h.modified_person_id) ";
		$sql .= " WHERE h.feature_id=" . ToSQL($feature_id, "integer", false); 
		$sql .= " AND h.field_name=" . ToSQL($field_name, "TEXT", false); 
		$sql .= " ORDER BY h.modified_date ASC ";
		$db->query($sql);
		if ($db->next_record()) {
			$old_value = $db->f("old_value");
			$t->set_var("init_value", $old_value);
			do {
				$modified_date = $db->f("modified_date");
				$modified_user = $db->f("first_name") . " " . $db->f("last_name");
				$t->set_var("modified_date", $modified_date);
				$t->set_var("modified_user", $modified_user);
				$old_value = $db->f("old_value");
				$new_value = $db->f("new_value");				
				$t->set_var("old_value", $old_value);
				$t->set_var("new_value", $new_value);				
				$t->parse("history_row");
			} while($db->next_record());
			$t->parse("history");
			$t->set_var("no_history", "");
		} else {
			$t->parse("no_history");
			$t->set_var("history", "");
		}
	} else {
		echo "No Such Feature"; exit;
	}
	
	$t->pparse("main");
?>