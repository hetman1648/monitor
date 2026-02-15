<?php
	include("./includes/date_functions.php");
	include("./includes/common.php");
	CheckSecurity(1);
	$t = new iTemplate($sAppPath);
	$t->set_file("main","notes.html");

	$task_id = GetParam("task_id");
	$session_user_id = GetSessionParam("UserID");	
	
	$user_allow_edit = true;
	$user_allow_view = true;
	$notes = array();
	$error = "";
	$notes_count = 0;

	if($task_id>0) {
	
		$sql = " SELECT CONCAT(u_created.first_name, ' ', u_created.last_name) AS created_user_name ";
		$sql.= " , CONCAT(u_modified.first_name, ' ', u_modified.last_name) AS modified_user_name ";
		$sql.= " , n.* ";
		$sql.= " FROM notes n ";
		$sql.= " LEFT JOIN users u_created ON (n.author_id=u_created.user_id) ";
		$sql.= " LEFT JOIN users u_modified ON (n.modified_by=u_modified.user_id) ";
		$sql.= " WHERE n.task_id=".ToSQL($task_id, "integer");	
		
		$db->query($sql);
		while ($db->next_record()) {
			$notes[] = $db->Record;
		}
		
		if (isset($notes) && sizeof($notes)) {
			foreach($notes as $note) {
				$user_allow_view = is_allowed($session_user_id, $note["author_id"], get_set_array($note["allow_view"], $permission_groups));
				if ($user_allow_view) {
					$note["note_description"] = nl2br($note["note_description"]);
					//select version file
					$t->set_var($note);
					if ($note["modified_user_name"]) {
						$t->parse("modified_row", false);
					} else {
						$t->set_var("modified_row", "");
					}
					$user_allow_edit = is_allowed($session_user_id, $note["author_id"], get_set_array($note["allow_edit"], $permission_groups));
					if ($user_allow_edit) {
						$t->parse("note_edit_link", false);
					} else {
						$t->set_var("note_edit_link", "");
					}
					$t->parse("notes", true);					
					$notes_count++;
				}
			}
		}
	} else {
		$error .= "<BR>Task ID is required!";
	}
	
	if ($error) {
    	$t->set_var("error_message",$error);
    	$t->parse("error", false);
    	$t->set_var("notes_table", "");
	} else {
    	$t->set_var("error","");
    	if (!$notes_count) {
    		$t->set_var("notes", "");
    	}
    	$t->parse("notes_table", false);
	}

	$t->pparse("main", false);
    
?>