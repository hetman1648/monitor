<?php
	include("./includes/date_functions.php");
	include("./includes/common.php");
	CheckSecurity(1);
	$t = new iTemplate($sAppPath);
	$t->set_file("main","note_edit.html");

	$note_id = GetParam("note_id");
	$task_id = GetParam("task_id");
	$operation = GetParam("operation");
	$session_user_id = GetSessionParam("UserID");	
	
	$note_title = GetParam("note_title");
	$note_description = GetParam("note_description");
	$error = "";
	$success_message = "";
	
	if ($operation == "cancel") {
		header("Location: index.php");
		exit;
	}
	
	$post_array = array();
	if (isset($HTTP_POST_VARS) && is_array($HTTP_POST_VARS)) {
		$post_array = $HTTP_POST_VARS;
	} elseif (isset($_POST) && is_array($_POST)) {
		$post_array = $_POST;
	}
	
	$allow_edit = array();
	$allow_view = array();
	$user_allow_edit = true;
	$user_allow_view = true;
	$note = array();
	foreach ($permission_groups as $key=>$group_name) {
		if (isset($post_array["allow_edit_".$key]) && $post_array["allow_edit_".$key]!="") {
			$allow_edit[$key] = true;
		} else {
			$allow_edit[$key] = false;
		}		
		if (isset($post_array["allow_view_".$key]) && $post_array["allow_view_".$key]!="") {
			$allow_view[$key] = true;
		} else {
			$allow_view[$key] = false;
		}		
	}
	
	if($note_id>0) {
		$sql = "SELECT * FROM notes WHERE note_id=".ToSQL($note_id, 'integer');
		$db->query($sql);
		if ($db->next_record()) {
			$note_id = $db->f("note_id");
			$note = $db->Record;
		} else {
			$error = "<BR>Requested note doesn't exist";
		}
		
		if (isset($note) && sizeof($note)) {
			$user_allow_view = is_allowed($session_user_id, $note["author_id"], get_set_array($note["allow_view"], $permission_groups));
			if (!$user_allow_view) {
				$error.="<BR>You are not allowed to view this note";
			}
			$user_allow_edit = is_allowed($session_user_id, $note["author_id"], get_set_array($note["allow_edit"], $permission_groups));
		}
	}
	
	if($operation=="update" || $operation=="insert") {
		//validate fields
		if (!strlen($note_title) && !strlen($note_description)) {
			$error.="<BR>Note title is required";
		}
	}
	
	$allow_edit_string = get_set_string($allow_edit, $permission_groups);
	$allow_view_string = get_set_string($allow_view, $permission_groups);
	
	if (!$error) {
			//take some action
			if ($operation=="insert" && $task_id>0)
			{
					$sql = "INSERT INTO notes (task_id, note_title, note_description, allow_edit, allow_view, author_id, date_added) ";
					$sql.= " VALUES (".ToSQL($task_id,"integer");					
					$sql.= ", ".ToSQL($note_title, "text");
					$sql.= ", ".ToSQL($note_description, "text");
					$sql.= ", ".ToSQL($allow_edit_string, "text");
					$sql.= ", ".ToSQL($allow_view_string, "text");
					$sql.= ", ".ToSQL($session_user_id, "number");
					$sql.= ", NOW()) ";
					$db->query($sql);
			
					$db->query("SELECT LAST_INSERT_ID()");
					$db->next_record();
					$note_id = $db->f(0);
			
					header("Location: note_edit.php?note_id=".$note_id);
					exit;
			}
		
			if ($operation=="update" && $note_id)
			{
				$sql = "UPDATE notes SET ";
				$sql.= "  note_title = ".ToSQL($note_title,"text");
				$sql.= ", note_description = ".ToSQL($note_description, "text");
				$sql.= ", allow_edit = ".ToSQL($allow_edit_string, "text");
				$sql.= ", allow_view = ".ToSQL($allow_view_string, "text");
				$sql.= ", date_last_modified=NOW() ";
				$sql.= ", modified_by= ".ToSQL($session_user_id, "integer");
				$sql.= " WHERE note_id=".ToSQL($note_id, "number");
				$db->query($sql);
			
				header("Location: note_edit.php?note_id=".$note_id);
				exit;
			}
		}
				
	if ($operation=="delete" && $note_id)
	{
		// need to check permissions first;
		$sql = "DELETE FROM notes WHERE note_id = ".ToSQL($note_id,"integer");
		$db->query($sql);
		header("Location: index.php");
		exit;
	}		
	
	if (!$operation) {
		if (isset($note) && sizeof($note)) {
			$note_title = $note["note_title"];
			$note_description = $note["note_description"];
			$task_id = $note["task_id"];
			$allow_edit = get_set_array($note["allow_edit"], $permission_groups);
			$allow_view = get_set_array($note["allow_view"], $permission_groups);
		} else {
			//default values
			foreach($permission_groups as $key=>$value) {
				$allow_edit[$key] = true;
				$allow_view[$key] = true;
			}
		}
	}
	
	if ($task_id) {
		$show_form = true;
		$sql = "SELECT task_id, task_title FROM tasks WHERE task_id=".ToSQL($task_id, "integer");
		$db->query($sql);
		$db->next_record();
		$t->set_var("task_title", $db->f("task_title"));
		$t->set_var("task_id", $db->f("task_id"));
	} else {
		$show_form = false;		
		$error.= "<BR>Task ID is required!";
	}
	
	$t->set_var("note_title", $note_title);
	$t->set_var("note_description", $note_description);
	foreach ($permission_groups as $key=>$group_name)
	{
		$t->set_var("group_description", $group_name);
		$t->set_var("group", $key);
		if (isset($allow_edit[$key]) && $allow_edit[$key]) {
			$t->set_var("group_checked", "checked");
		} else {
			$t->set_var("group_checked", "");
		}
		$t->parse("allow_edit_groups", true);
		if (isset($allow_view[$key]) && $allow_view[$key]) {
			$t->set_var("group_checked", "checked");
		} else {
			$t->set_var("group_checked", "");
		}
		$t->parse("allow_view_groups", true);
	}
	
	if ($note_id) {		
		$t->set_var("note_id", $note_id);
		$t->set_var("task_id", $task_id);
		//show note info
		$sql = " SELECT CONCAT(u_created.first_name, ' ', u_created.last_name) AS created_user_name ";
		$sql.= " , CONCAT(u_modified.first_name, ' ', u_modified.last_name) AS modified_user_name ";
		$sql.= " , n.date_added, n.date_last_modified ";
		$sql.= " FROM notes n ";
		$sql.= " LEFT JOIN users u_created ON (n.author_id=u_created.user_id) ";
		$sql.= " LEFT JOIN users u_modified ON (n.modified_by=u_modified.user_id) ";
		$sql.= " WHERE n.note_id=".ToSQL($note_id, "integer");
		$db->query($sql);
		if ($db->next_record()) {
			$t->set_var("created_user_name", $db->f("created_user_name"));
			$t->set_var("modified_user_name", $db->f("modified_user_name"));
			$t->set_var("date_added", $db->f("date_added"));
			$t->set_var("date_last_modified", $db->f("date_last_modified"));
		}
		$t->set_var("operation", "update");
		$t->set_var("save_button", "Update");
		$t->set_var("query_note_id", "?note_id=".$note_id);
		if ($user_allow_edit) {
			$t->set_var("disabled", "");
			$t->parse("save_button_block", false);
			$t->parse("delete_button_block", false);
		} else {
			$t->set_var("disabled", "disabled");
			$t->set_var("save_button_block", "");
			$t->set_var("delete_button_block", "");
		}
		$t->parse("note_info", false);
	} else {
		//leave fields blank
		$t->set_var("note_info", false);
		$t->set_var("operation", "insert");
		$t->set_var("save_button", "Insert");
		$t->set_var("query_note_id", "");
		$t->parse("save_button_block", false);
		$t->parse("delete_button_block", false);		
	}	
	
    if ($error) {
    	$t->set_var("error_message",$error);
    	$t->parse("error", false);
    	$t->set_var("success","");
	} else {
    	$t->set_var("error","");
    	if ($success_message) {
    		$t->set_var("success_message", $success_message);
    		$t->parse("success", false);
    	} else {
    		$t->set_var("success", "");
    	}
    }
    
    if ($user_allow_view && $show_form) {
    	$t->parse("edit_note_form", false);
    } else {
    	$t->set_var("edit_note_form", "");
    }

	$t->pparse("main", false);
	
?>