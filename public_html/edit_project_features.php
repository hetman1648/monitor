<?php
	include("./includes/common.php");
	
	$t = new iTemplate($sAppPath);
	$t->set_file("main", "edit_project_features.html");
	
	CheckSecurity(1);
	$operation   = GetParam("operation");
	
	global $statuses;
	$sql = " SELECT * FROM project_features_statuses";
	$db->query($sql);
	$statuses = array();
	while($db->next_record()) {
		$status_id    = $db->f("id");
		$status_title = $db->f("title");
		$statuses[$status_id] = $status_title;
	}
	
	if ($operation == "next_revision_status") {
		next_revision_status(GetParam("project_id"), GetParam("revision_id"), GetParam("feature_id"), GetParam("field_name"));
		exit;
	}
	
	$project_id  = GetParam("project_id");
	$return_page = GetParam("return_page");	
	$user_id     = GetSessionParam("UserID");
	
	$sql  = " SELECT project_title FROM projects ";
	$sql .= " WHERE project_id=" . ToSQL($project_id, "integer", false);
	$db->query($sql);
	if (!$db->next_record()) {
		header("Location:" . $return_page);
		exit;
	} else {
		$t->set_var("project_title", $db->f("project_title"));
	}
	
	$t->set_var("project_id", $project_id);
	$t->set_var("return_page", $return_page);
	
	$act_revision_id = 0;
	$act_feature_id = 0;
	if ($operation == "add_revision") {
		$title = GetParam("title");
		$sql  = " SELECT id FROM project_features_revisions";
		$sql .= " WHERE project_id=" . ToSQL($project_id, "integer", false);
		$sql .= " AND title=" . ToSQL($title, "text", false);
		$db->query($sql);
		if ($db->next_record()) {
			$act_revision_id = $db->f("id");
		} else {
			$sql  = " SELECT MAX(id) FROM project_features_revisions";
			$db->query($sql);
			$act_revision_id = $db->f(0);
			
			$sql  = " INSERT INTO project_features_revisions ";
			$sql .= " (id, project_id, title, creation_date, created_person_id) ";
			$sql .= " VALUES (" . ToSQL($id, "integer", false);
			$sql .= ", " . ToSQL($project_id, "integer", false);
			$sql .= ", " . ToSQL($title, "text", false);
			$sql .= ", NOW(), " . ToSQL($user_id, "integer", false) . ")";
			$db->query($sql);
		}
	} elseif ($operation == "add_feature") {
		$feature_title = GetParam("feature_title");
		$sql  = " SELECT id FROM project_features";
		$sql .= " WHERE project_id=" . ToSQL($project_id, "integer", false);
		$sql .= " AND title=" . ToSQL($feature_title, "text", false);
		$db->query($sql);
		if ($db->next_record()) {
			$act_feature_id = $db->f("id");
		} else {
			$sql  = " SELECT MAX(id) FROM project_features";
			$db->query($sql);
			$act_feature_id = $db->f(0);
	
			$sql  = " INSERT INTO project_features ";
			$sql .= " (id, project_id, title, status_id, task_id, in_cvs, on_test, on_live, ";
			$sql .= " description, howtotest, files, creation_date, created_person_id) ";
			$sql .= " VALUES (" . ToSQL($id, "integer", false);
			$sql .= ", " . ToSQL($project_id, "integer", false);
			$sql .= ", " . ToSQL($feature_title, "text", false);
			$sql .= ", " . ToSQL(GetParam("feature_status_id"), "integer", false);
			$sql .= ", " . ToSQL(GetParam("feature_task_id"), "integer", false);
			$sql .= ", " . ToSQL(GetParam("feature_in_cvs"), "integer", false);
			$sql .= ", " . ToSQL(GetParam("feature_on_test"), "integer", false);
			$sql .= ", " . ToSQL(GetParam("feature_on_live"), "integer", false);
			$sql .= ", " . ToSQL(GetParam("feature_description"), "text");
			$sql .= ", " . ToSQL(GetParam("feature_howtotest"), "text");
			$sql .= ", " . ToSQL(GetParam("feature_files"), "text");
			$sql .= ", NOW(), " . ToSQL($user_id, "integer", false) . ")";
			$db->query($sql);
		}
	}
	
	
	$sql  = " SELECT r.*, ";
	$sql .= " u.first_name, u.last_name FROM (project_features_revisions r";
	$sql .= " LEFT JOIN users u ON u.user_id=r.created_person_id) ";
	$sql .= " WHERE r.project_id=" . ToSQL($project_id, "integer", false);
	$sql .= " ORDER BY r.creation_date DESC";
	$db->query($sql);
	$revisions = array();
	if ($db->next_record()) {
		do {			
			$revision_id    = $db->f("id");
			$revision_title = $db->f("title");
			$creation_date  = $db->f("creation_date");
			$created_user   = $db->f("first_name") . " " . $db->f("last_name");
			$revisions[]    = $revision_id;
			
			$t->set_var("revision_id",    $revision_id);
			$t->set_var("revision_title", $revision_title);
			$t->set_var("creation_date",  $creation_date);
			$t->set_var("created_user",   $created_user);
			
			$t->parse("revisions_block");
			$t->parse("fr_title");
			
		} while ($db->next_record());
	} else {
		$t->set_var("revisions_block", "");
		$t->set_var("fr_title", "");
	}
	
	foreach ($statuses AS $status_id => $status_title) {
		$t->set_var("status_id", $status_id);
		$t->set_var("status_title", $status_title);
		$t->set_var("status_selected", "");
		$t->parse("status_option", true);
	}
	
	
	$sql  = " SELECT * FROM project_features_revisions_data";
	$sql .= " WHERE project_id=" . ToSQL($project_id, "integer", false);
	$db->query($sql);
	$revisions_data = array();
	while($db->next_record()) {
		$revision_id   = $db->f("revision_id");
		$feature_id    = $db->f("feature_id");
		$rev_status_id = $db->f("status_id");
		$rev_in_cvs    = $db->f("in_cvs");
		$rev_on_test   = $db->f("on_test");
		$rev_on_live   = $db->f("on_live");
		$rev_comments  = $db->f("comments");
		$revisions_data[$feature_id][$revision_id] =  array(
			"rev_status_id" => $rev_status_id,
			"rev_in_cvs"    => $rev_in_cvs,
			"rev_on_test"   => $rev_on_test,
			"rev_on_live"   => $rev_on_live,
			"rev_comments"  => $rev_comments
		);
	}
	
	$sql  = " SELECT f.*, ";
	$sql .= " u.first_name, u.last_name FROM (project_features f ";
	$sql .= " LEFT JOIN users u ON u.user_id=f.created_person_id) ";
	$sql .= " WHERE project_id=" . ToSQL($project_id, "integer", false);
	$sql .= " ORDER BY title ASC";
	$db->query($sql);
	if ($db->next_record()) {
		do {
			$feature_id        = $db->f("id");
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
			
			$t->set_var("fr_value", "");
			$t->set_var("fr_value_2", "");			
			$t->set_var("rev_class", "ColumnTD");
			if ($revisions) {
				foreach ($revisions AS $revision_id) {					
					$t->set_var("revision_id", $revision_id);
					if (isset($revisions_data[$feature_id][$revision_id])) {
						$rev_status_title = $statuses[$revisions_data[$feature_id][$revision_id]["rev_status_id"]];
						$t->set_var("rev_in_cvs",    $revisions_data[$feature_id][$revision_id]["rev_in_cvs"]? "in CVS" : "<strike>in CVS</strike>");
						$t->set_var("rev_on_test",   $revisions_data[$feature_id][$revision_id]["rev_on_test"]? "on Test" : "<strike>on Test</strike>");
						$t->set_var("rev_on_live",   $revisions_data[$feature_id][$revision_id]["rev_on_live"]? "on Site" : "<strike>on Site</strike>");
					} else {
						$rev_status_title = "Undefined";
						$t->set_var("rev_in_cvs", "?");
						$t->set_var("rev_on_test", "?");
						$t->set_var("rev_on_live", "?");
					}
					$t->set_var("rev_status_title", $rev_status_title);
					$t->parse("fr_value");
					$t->parse("fr_value_2");
					$t->set_var("rev_class", "DataTDE");				
				}
			}
			$t->parse("features_block");
		} while ($db->next_record());
	} else {
		$t->set_var("features_block", "");
	}
	
	$t->pparse("main");
		
	function next_revision_status($project_id, $revision_id, $feature_id, $field_name) {
		global $db, $statuses;
		if (in_array($field_name, array(
			"status_id", "in_cvs", "on_test", "on_live"
		))) {
			$sql  = " SELECT project_id, $field_name FROM project_features_revisions_data ";
			$sql .= " WHERE revision_id=" . ToSQL($revision_id, "integer", false);
			$sql .= " AND feature_id=" . ToSQL($feature_id, "integer", false);
			$db->query($sql);
			if ($db->next_record()) {
				if ($project_id != $db->f("project_id")) return false;
				$field_value = $db->f($field_name);
				if ($field_name == "status_id") {
					$field_value++;
					if ($field_value >= count($statuses)) {
						$field_value = 1;
					}
				} else {
					$field_value = $field_value ? 0 : 1;
				}
				$sql  = " UPDATE project_features_revisions_data ";
				$sql .= " SET  $field_name=" . ToSQL( $field_value, "integer", false);
				$sql .= " WHERE revision_id=" . ToSQL($revision_id, "integer", false);
				$sql .= " AND feature_id=" . ToSQL($feature_id, "integer", false);
				$db->query($sql);
			} else {
				$field_value = 1;
				$sql  = " INSERT INTO project_features_revisions_data ";
				$sql .= " (revision_id, feature_id, project_id, $field_name) ";
				$sql .= " VALUES (" . ToSQL($revision_id, "integer", false);
				$sql .= " , " . ToSQL($feature_id, "integer", false);
				$sql .= " , " . ToSQL($project_id, "integer", false);
				$sql .= " , " . ToSQL($field_value, "integer", false);
				$sql .= " )";
				$db->query($sql);
			}
			switch ($field_name) {
				case "status_id":
					echo $statuses[$field_value];
				break;
				case "in_cvs":
					echo  $field_value? "in CVS" : "<strike>in CVS</strike>";
				break;
				case "on_test":
					echo $field_value? "on Test" : "<strike>on Test</strike>";
				break;
				case "on_live":
					echo  $field_value? "on Site" : "<strike>on Site</strike>";
				break;
			}
		}
		return false;
	}
?>