<?php
	include("./includes/date_functions.php");
	include("./includes/common.php");
	CheckSecurity(1);
	$t = new iTemplate($sAppPath);
	$t->set_file("main","document_edit.html");

	$doc_id = GetParam("doc_id");
	$operation = GetParam("operation");
	$session_user_id = GetSessionParam("UserID");	
	
	$doc_name = GetParam("doc_name");
	$doc_description = GetParam("doc_description");
	$doc_text = GetParam("doc_text");
	$file_name = GetParam("file_name");
	$hash = substr(md5(time()),0,8);	
	$error = "";
	$success_message = "";
	
	$handle = opendir($doc_path);
	
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
	$doc = array();
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
	
	if($doc_id>0) {
		$sql = "SELECT * FROM docs WHERE doc_id=".ToSQL($doc_id, 'integer');
		$db->query($sql);
		if ($db->next_record()) {
			$doc_id = $db->f("doc_id");
			$doc = $db->Record;
		} else {
			$error = "<BR>Such document doesn't exist";
		}
		
		if (isset($doc) && sizeof($doc)) {			
			$user_allow_view = is_allowed($session_user_id, $doc["author_id"], get_set_array($doc["allow_view"], $permission_groups));
			if (!$user_allow_view) {
				$error.="<BR>You are not allowed to view this document";
			}
			$user_allow_edit = is_allowed($session_user_id, $doc["author_id"], get_set_array($doc["allow_edit"], $permission_groups));
		}
	}
	
	if($operation=="update" || $operation=="insert") {
		//validate fields
		if (!strlen($doc_name)) {
			$error.="<BR>Document name is required";
		}
		if (!$user_file && $operation=="insert") {
			$error.="<BR>Document can not be blank";
		}
	}
	
	$allow_edit_string = get_set_string($allow_edit, $permission_groups);
	$allow_view_string = get_set_string($allow_view, $permission_groups);
	
	if (!$error) {		
		//store file
		$cur_file = "";
		
		if ($operation=="insert" || $operation=="update") {
			if ($user_file) {						
				$cur_file = $user_file_name;
				
				if (file_exists($user_file)) {
		    		if (!file_exists($doc_path.$hash)) {
					    if (copy($user_file, $doc_path.$hash)) {
			    			$success_message = "<br><font color='navy'>File <b>$cur_file</b> has been successfully uploaded.</font>";
			  				$user_file_size = filesize($doc_path.$hash);
			    		} else {
			    			$error .= "<BR>Errors during upload. Please upload again.";
						}
		    		} else {
		    			$error.= "<br>File <b>$cur_file</b> can't be created. Check permissions.";
		    		}
	    		} else {
	    			$error .= "<br>File <b>$cur_file</b> already exists. Please rename your file and upload it again.";
	    		}
			}
			
		}
		
		if (!$error) {
			//take some action
			if ($operation=="insert" && $user_file)
			{
					$sql = "INSERT INTO docs (doc_name, doc_description, file_name, user_file_name, allow_edit, allow_view, author_id, date_added, current_version) ";
					$sql.= " VALUES (".ToSQL($doc_name,"text");
					$sql.= ", ".ToSQL($doc_description, "text");
					$sql.= ", ".ToSQL($hash, "text");					
					$sql.= ", ".ToSQL($cur_file, "text");
					$sql.= ", ".ToSQL($allow_edit_string, "text");
					$sql.= ", ".ToSQL($allow_view_string, "text");
					$sql.= ", ".ToSQL($session_user_id, "number");
					$sql.= ", NOW(), 1) ";
					$db->query($sql);
			
					$db->query("SELECT LAST_INSERT_ID()");
					$db->next_record();
					$doc_id = $db->f(0);
			
					$sql = " INSERT INTO docs_versions (version_number, doc_id, modified_by, file_name, user_file_name, date_added) ";
					$sql.= " VALUES (1, ".ToSQL($doc_id, "number").", ".ToSQL($session_user_id, "number");
					$sql.= ", ".ToSQL($hash, "text").", ".ToSQL($cur_file, "text").", NOW() )";
					$db->query($sql);

					header("Location: document_edit.php?doc_id=".$doc_id);
					exit;					
			}
		
			if ($operation=="update" && $doc_id)
			{
				if ($user_file) {
					++$doc["current_version"];
				}
				
				$sql = "UPDATE docs SET ";
				$sql.= "  doc_name = ".ToSQL($doc_name,"text");
				$sql.= ", doc_description = ".ToSQL($doc_description, "text");
				if ($user_file) {
					$sql.= ", file_name = ".ToSQL($hash, "text");
					$sql.= ", user_file_name = ".ToSQL($cur_file, "text");
				}
				$sql.= ", allow_edit = ".ToSQL($allow_edit_string, "text");
				$sql.= ", allow_view = ".ToSQL($allow_view_string, "text");
				$sql.= ", date_last_modified=NOW() ";
				$sql.= ", current_version = ".ToSQL($doc["current_version"], "integer");
				$sql.= " WHERE doc_id=".ToSQL($doc_id, "number");
				$db->query($sql);
			
				if ($user_file) {
					$sql = " INSERT INTO docs_versions (version_number, doc_id, modified_by, file_name, user_file_name, date_added) ";
					$sql.= " VALUES (".ToSQL($doc["current_version"], "integer").", ".ToSQL($doc_id, "number");
					$sql.= ", ".ToSQL($session_user_id, "number");
					$sql.= ", ".ToSQL($hash, "text");
					$sql.= ", ".ToSQL($cur_file, "text");
					$sql.= ", NOW() )";
					$db->query($sql);
				}
				
				header("Location: document_edit.php?doc_id=".$doc_id);
				exit;
			}
		}
				
		if ($operation=="delete" && $doc_id)
		{
			// need to check permissions first;
			$sql = "DELETE FROM docs WHERE doc_id = ".ToSQL($doc_id,"integer");
			$db->query($sql);
			$sql = "DELETE FROM docs_versions WHERE doc_id = ".ToSQL($doc_id,"integer");
			$db->query($sql);
			header("Location: index.php");
			exit;
		}		
	}
	
	if (!$operation) {
		if (isset($doc) && sizeof($doc)) {			
			$doc_name = $doc["doc_name"];
			$doc_description = $doc["doc_description"];
			$doc_text = "";
			$file_name = $doc["file_name"];
			$allow_edit = get_set_array($doc["allow_edit"], $permission_groups);
			$allow_view = get_set_array($doc["allow_view"], $permission_groups);
		} else {
			//default values
			foreach($permission_groups as $key=>$value) {
				$allow_edit[$key] = true;
				$allow_view[$key] = true;
			}			
		}
	}
	
	$t->set_var("doc_name", $doc_name);
	$t->set_var("doc_description", $doc_description);
	$t->set_var("doc_text", $doc_text);
	$t->set_var("file_name", $file_name);
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
	
	if ($doc_id) {		
		$t->set_var("doc_id", $doc_id);
		$t->set_var("doc_user_file_name", $doc["user_file_name"]);
		//show document info
		$sql = " SELECT CONCAT(u.first_name, ' ', u.last_name) AS user_name, d.date_added, d.date_last_modified, d.current_version ";
		$sql.= " FROM docs d LEFT JOIN users u ON (d.author_id=u.user_id) ";
		$sql.= " WHERE d.doc_id=".ToSQL($doc_id, "integer");
		$db->query($sql);
		if ($db->next_record()) {
			$t->set_var("user_name", $db->f("user_name"));
			$t->set_var("date_added", $db->f("date_added"));
			$t->set_var("date_last_modified", $db->f("date_last_modified"));
			$t->set_var("current_version", $db->f("current_version"));
		}
		$t->set_var("operation", "update");
		$t->set_var("save_button", "Update");
		$t->set_var("query_doc_id", "?doc_id=".$doc_id);
		if ($user_allow_edit) {
			$t->set_var("disabled", "");
			$t->parse("save_button_block", false);
			$t->parse("delete_button_block", false);
		} else {
			$t->set_var("disabled", "disabled");
			$t->set_var("save_button_block", "");
			$t->set_var("delete_button_block", "");
		}
		$t->parse("last_version_link",false);
		
		if($doc["current_version"]>1) {
			$sql = "SELECT CONCAT(u.first_name, ' ', u.last_name) AS user_name, dv.date_added ";
			$sql.= ", dv.user_file_name, dv.version_number, dv.file_name ";
			$sql.= " FROM docs_versions dv LEFT JOIN users u ON (dv.modified_by=u.user_id) ";
			$sql.= " WHERE dv.doc_id=".ToSQL($doc_id, "integer");
			$sql.= " ORDER BY version_number ASC ";
			$db->query($sql);
			while ($db->next_record()) {
				$ver = $db->f("version_number");				
				$t->set_var("version_number", $ver);
				$t->set_var("version_user_name", $db->f("user_name"));
				$t->set_var("version_date_added", $db->f("date_added"));
				$t->set_var("version_user_file_name", $db->f("user_file_name"));				
				$t->parse("previous_version_row", true);
			}
			$t->parse("previous_versions", false);
			$t->parse("view_previous_versions_link", false);
		} else {
			$t->set_var("previous_versions", "");
			$t->set_var("view_previous_versions_link", "");
		}
		$t->parse("document_info", false);
	} else {
		//leave fields blank
		$t->set_var("document_info", false);
		$t->set_var("operation", "insert");
		$t->set_var("save_button", "Insert");
		$t->set_var("query_doc_id", "");
		$t->set_var("last_version_link", "");
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
    
    if ($user_allow_view) {
    	$t->parse("edit_document_form", false);
    } else {
    	$t->set_var("edit_document_form", "");
    }

	$t->pparse("main", false);
	
?>