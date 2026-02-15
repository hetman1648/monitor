<?php
	include("./includes/common.php");
		
	CheckSecurity(1);
	$operation   = GetParam("operation");
	
	$t = new iTemplate($sAppPath);
	$t->set_file("main", "project_feature.html");

	$feature_id = GetParam("feature_id");
	
	global $statuses;
	$sql = " SELECT * FROM project_features_statuses";
	$db->query($sql);
	$statuses = array();
	while($db->next_record()) {
		$status_id    = $db->f("id");
		$status_title = $db->f("title");
		$statuses[$status_id] = $status_title;
	}
	
	if ($operation == "save_feature_field") {
		save_feature_field($feature_id, GetParam("field_name"), GetParam("field_value"));		
		exit;
	}
	
	$sql  = " SELECT f.*, ";
	$sql .= " u.first_name, u.last_name FROM (project_features f ";
	$sql .= " LEFT JOIN users u ON u.user_id=f.created_person_id) ";
	$sql .= " WHERE id=" . ToSQL($feature_id, "integer", false);
	$db->query($sql);
	if ($db->next_record()) {
		$feature_title     = $db->f("title");
		$feature_status_id = $db->f("status_id");
		$feature_task_id   = $db->f("task_id");
			
		$feature_in_cvs    = $db->f("in_cvs");
		$feature_on_test   = $db->f("on_test");
		$feature_on_live   = $db->f("on_live");
			
		$feature_description = $db->f("description");
		$feature_howtotest   = $db->f("howtotest");
		$feature_files       = $db->f("files");
			
		$creation_date = $db->f("creation_date");			
		$created_user  = $db->f("first_name") . " " . $db->f("last_name");
						
		$t->set_var("feature_id",        $feature_id);
		$t->set_var("feature_title",     $feature_title);
		$t->set_var("feature_title_js",  substr(str_replace(array("\"", "'"), "", $feature_title), 0, 30));
		$t->set_var("feature_status",    $statuses[$feature_status_id]);
		$t->set_var("feature_task_id",   $feature_task_id);
			
		$t->set_var("feature_in_cvs",    $feature_in_cvs? "in CVS" : "<strike>in CVS</strike>");
		$t->set_var("feature_on_test",   $feature_on_test? "on Test" : "<strike>on Test</strike>");
		$t->set_var("feature_on_live",   $feature_on_live? "on Site" : "<strike>on Site</strike>");
			
		$t->set_var("feature_description", $feature_description);
		$t->set_var("feature_howtotest",   $feature_howtotest);
		$t->set_var("feature_files",       $feature_files);	
			
		$t->set_var("creation_date", $creation_date);
		$t->set_var("created_user",  $created_user);
	} else {
		echo "No Such Feature";
		exit;
	}
	
	$t->pparse("main");
	
	function save_feature_field($feature_id, $field_name, $field_value) {
		global $db;
		
		$user_id     = GetSessionParam("UserID");
		$field_name  = str_replace("feature_", "", $field_name);
		if (in_array($field_name, array(
			"title", "status_id", "task_id", "in_cvs", "on_test", "on_live", "description", "howtotest", "files"
		))) {
			$sql  = " SELECT $field_name FROM project_features";
			$sql .= " WHERE id=" . ToSQL($feature_id, "integer", false);
			$db->query($sql);
			if ($db->next_record()) {
				$old_value = $db->f(0);
				if (for_check_texts($old_value) == for_check_texts($field_value)) return false;
				$sql  = " UPDATE project_features SET $field_name =" . ToSQL($field_value, "TEXT");
				$sql .= " WHERE id=" . ToSQL($feature_id, "integer", false);
				$db->query($sql);
				
				$sql  = " INSERT INTO project_features_fields_history ";
				$sql .= " (feature_id, field_name, old_value, new_value, modified_date, modified_person_id )";
				$sql .= " VALUES (" . ToSQL($feature_id, "integer", false);
				$sql .= ", " . ToSQL($field_name, "text", false);
				$sql .= ", " . ToSQL($old_value, "text", false);
				$sql .= ", " . ToSQL($field_value, "text", false);
				$sql .= ", NOW(), " . ToSQL($user_id, "integer", false) . ")";
				$db->query($sql);
				return true;
			}
		}
		return false;
	}
	
	function for_check_texts($text) {
		return str_replace(array(" ", "\n", "\t"), "", strip_tags($text));
	}
?>