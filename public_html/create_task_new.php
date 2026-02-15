<?php

	include ("./includes/common.php");

	CheckSecurity(1);

	$temp_path   = "temp_attachments/";
	$path 		 = "attachments/task/";
	header("Cache-Control: private");
	header("Age: 699");
	
	$T = new iTemplate($sAppPath);
	$T->set_file("main",    "create_task_new.html");

	$task_id = (int) GetParam('task_id');
	$action  = GetParam('action');
	$user_id = GetSessionParam("UserID");	
	$hash    = GetParam("hash") ? GetParam("hash") : substr(md5(time()), 0, 8);
	$rp      = GetParam("rp");
	if (!$rp) {
		$rp = "index.php";
	}
			
	$errors = "";
	
	if ($action) {		
		$task_title                 = GetParam("task_title");
		$task_desc                  = GetParam("task_desc");
		$project_id                 = (int) GetParam("hidden_project_id");
		if (!$project_id) $project_id = (int) GetParam("project_id");
		$sub_project_id            = (int) GetParam("hidden_sub_project_id");
		if (!$sub_project_id) $sub_project_id = (int) GetParam("sub_project_id");
		$project_filter_my          = (int) GetParam("project_filter_my");
		$project_filter_in_progress = (int) GetParam("project_filter_in_progress");
	
		$planned_date = array(
			"YEAR"  => GetParam("year"),
			"MONTH" => (int) GetParam("month"),
			"DAY"   => (int) GetParam("day")
		);
		
		$task_status_id      = (int) GetParam("task_status_id");		
		$responsible_user_id = (int) GetParam("hidden_responsible_user_id");
		if (!$responsible_user_id) $responsible_user_id = (int) GetParam("responsible_user_id");		
		$task_type_id        = (int) GetParam("task_type_id");
		$estimated_hours     = parseEstimatedHours(GetParam("task_estimated_time"));
		$task_cost           = (float) GetParam("task_cost");
		$hourly_charge       = (int) GetParam("hourly_charge");
		$client_id           = (int) GetParam("client_id");
		$task_domain         = GetParam("task_domain");
		$priority_id         = (int) GetParam("priority_id");
		if ($action == "get_projects_list") {
			showProjects($project_id, $sub_project_id, $project_filter_my, $project_filter_in_progress);
			echo $T->get_var("project_block");
			exit;
		} elseif ($action == "get_subprojects_list") {
			showSubProjects($project_id, $sub_project_id, $project_filter_my, $project_filter_in_progress);
			echo $T->get_var("sub_project_block");
			exit;
		} elseif ($action == "get_projectusers_list") {
			showProjectUsers($task_id, $responsible_user_id, $project_id, $sub_project_id);
			echo $T->get_var("responsible_user_block");
			exit;
		} elseif ($action == "get_estimated_hours") {
			echo $estimated_hours;
			exit;
		} elseif ($action == "cancel") {
			header("Location: " . $rp);
			exit;
		} elseif ($action == "insert") {
			if (beforeSaveTask()) {
				saveTask();
			}
		}
			
	} else{
		$task_title           = "";
		$task_desc            = "";	
		$project_id           = 0;
		$sub_project_id       = 0;	
		$total_messages       = 0;
		$responsible_user_id  = 0;
		$task_type_id         = 1;  // new
		$task_status_id       = 7;  // not started
		$estimated_hours      = "";
		$task_cost            = "";
		$hourly_charge        = 0;
		$client_id            = 0;
		$task_domain          = "";
		$priority_id          = 1;
		$planned_date = array(
			"YEAR"  => date("y"),
			"MONTH" => date("m"),
			"DAY"   => ""
		);
		$project_filter_my    = $project_filter_in_progress = 1;
	
		if ($task_id) {		
			// default values from db
			$sql = "SELECT * FROM tasks WHERE task_id = " . ToSQL($task_id, "integer");
			$db->query($sql);
			if ($db->next_record()) {
				$task_title     = $db->f("task_title");
				$task_desc      = $db->f("task_desc");
				$sub_project_id = $db->f("project_id");
				
				$planned_date_str = $db->f("planed_date");
				if (time() < strtotime($planned_date_str)) {
					$tmp = explode("-", $planned_date_str);
					$planned_date = array(
						"YEAR"  => $tmp[0],
						"MONTH" => $tmp[1],
						"DAY"   => $tmp[2]
					);
				} else {
					$planned_date = array(
						"YEAR"  => "00",
						"MONTH" => "",
						"DAY"   => ""
					);
				}
				$responsible_user_id = $db->f("responsible_user_id");
				$estimated_hours     = (float) $db->f("estimated_hours");
				$task_cost           = $db->f("task_cost");
				$hourly_charge       = $db->f("hourly_charge");
				$client_id   = $db->f("client_id");
				$task_domain = $db->f("task_domain");
				$priority_id = $db->f("priority_id") - 1;
				if ($priority_id <= 0) $priority_id = 1;
			}
			
			
			$sql  = " SELECT message FROM messages ";
			$sql .= " WHERE messages.identity_type='task' and messages.identity_id=" . ToSQL($task_id, "integer");
			$sql .= " ORDER BY message_date DESC LIMIT 0,1";
			$db->query($sql);
			if ($db->next_record()) {
				$task_desc = $db->f("message");
			}
			
			if ($sub_project_id) {
				$sql  = " SELECT parent_project_id FROM projects ";
				$sql .= " WHERE project_id=" . ToSQL($sub_project_id, "integer");
				$db->query($sql);
				if ($db->next_record()) {
					$project_id = $db->f("parent_project_id");
				}
			}
		}
		
		if (GetParam("type")=="child") {
			$task_type_id = 1;
		}
	}
	
	// display values
	$T->set_var("task_id", $task_id);
	$T->set_var("hash",    $hash);
	$T->set_var("rp",      $rp);
	
	$T->set_var("task_title", ToHTML($task_title));
	$T->set_var("task_desc",  ToHTML($task_desc));
	$T->set_var("project_filter_my_checked", $project_filter_my ? "checked" : "");
	$T->set_var("project_filter_in_progress_checked", $project_filter_in_progress ? "checked" : "");
	showProjects($project_id, $project_filter_my, $project_filter_in_progress);
    showSubProjects($project_id, $sub_project_id, $project_filter_my, $project_filter_in_progress);
	if (is_manager($user_id)) {
		$T->parse("edit_project_link", false);
	} else {
		$T->set_var("edit_project_link", "");
	}    
	$T->set_var("YEAR",  strlen($planned_date["YEAR"]) > 2 ? substr($planned_date["YEAR"], 2) : $planned_date["YEAR"]);
	$T->set_var("MONTH", get_month_options($planned_date["MONTH"]));
	$T->set_var("DAY",   $planned_date["DAY"] ? $planned_date["DAY"] : "");	
	get_select_options("lookup_tasks_statuses", "status_id", "status_desc", $task_status_id, "WHERE status_id!=1 AND usual=1 ", "sort_order",
		"task_status_id_value", "task_status_id_desc", "task_status_id", false);    
	showProjectUsers($task_id, $responsible_user_id, $project_id, $sub_project_id);
	
	get_select_options("lookup_task_types", "type_id", "type_desc", $task_type_id, "", "type_desc",
		"task_type_id_value", "task_type_id_desc", "task_type_id", false);
		
	$T->set_var("estimated_hours",       $estimated_hours ? $estimated_hours : "");
	$T->set_var("task_cost",             $task_cost ? $task_cost : "");
	$T->set_var("hourly_charge_checked", $hourly_charge ? "checked" : "");
	$T->set_var("client_id",   $client_id);
	showClient($client_id);
	$T->set_var("task_domain", $task_domain);
	$T->set_var("priority_id", $priority_id);
	
	
	$T->set_var("errors", $errors);	
	$T->pparse("main");
	
	function saveTask() {
		global $errors, $hash, $rp;		
		global $task_id, $task_title, $task_desc, $sub_project_id;
		global $planned_date, $task_status_id, $task_type_id, $responsible_user_id, $estimated_hours, $priority_id;
		global $task_domain, $task_cost, $hourly_charge, $parent_task_id;
		
		$parent_task_id = 0;
		
		$task_id = add_task(
			$responsible_user_id,
			$priority_id,
			$task_status_id,
			$sub_project_id,
			$client_id,
			$task_title,
			$task_desc,
			$planned_date, 
			$user_id,
			$estimated_hours,
			$task_type_id,
			$hash
		);
			
		update_task($task_id, array(
			"task_cost"       => $task_cost,
			"hourly_charge"   => $hourly_charge,
			"parent_task_id"  => $parent_task_id,
			"task_domain_url" => $task_domain,
			
		));
		
		header("Location: " . $rp);
		exit;
	}
	function beforeSaveTask() {
		global $errors;
		global $task_title, $project_id, $sub_project_id;
		global $planned_date, $task_status_id, $task_type_id, $responsible_user_id, $estimated_hours, $task_domain;
		
		$local_errors = "";
		$project_is_domain_required = false;
		
		if (!$task_title) {
			$local_errors .= "The value in field <font color=\"red\"><b>Title</b></font> is required.<br>";
		}
		if (!$project_id) {
			$local_errors .= "The value in field <font color=\"red\"><b>Project</b></font> is required.<br>";
		}
		if (!$sub_project_id) {
			$local_errors .= "The value in field <font color=\"red\"><b>Sub-Project</b></font> is required.<br>";
		} else {
			$sql  = " SELECT is_domain_required FROM projects WHERE project_id = " . $sub_project_id;	
			$project_is_domain_required = get_db_value($sql);
		}
		if (!$planned_date["YEAR"] || (strlen($planned_date["YEAR"]) > 2)) {
			$local_errors .= "The value of Year in field <font color=\"red\"><b>Date to complete</b></font> is required.<br>";
			$planned_date["YEAR"] = date("y");
		} elseif ((int) $planned_date["YEAR"] > date("y") + 10) {
			$local_errors .= "The value of Year in field <font color=\"red\"><b>Date to complete</b></font> is incorrect.<br>";
			$planned_date["YEAR"] = date("y");
		}
		if ($planned_date["DAY"] > date("t", mktime(0, 0, 0, $planned_date["MONTH"], 1, $planned_date["YEAR"]))) {
			$local_errors .= "The value of Day in field <font color=\"red\"><b>Date to complete</b></font> is incorrect.<br>";
		}		
		if (!$task_status_id) {
			$local_errors .= "The value in field <font color=\"red\"><b>Status</b></font> is required.<br>";
		}
		if (!$task_type_id) {
			$local_errors .= "The value in field <font color=\"red\"><b>Type</b></font> is required.<br>";
		}
		if (!$responsible_user_id) {
			$local_errors .= "The value in field <font color=\"red\"><b>Responsible person</b></font> is required.<br>";
		}
		if (strlen(GetParam("task_estimated_time")) && !($estimated_hours > 0)) {
			$local_errors .= "The value in field <font color=\"red\"><b>Estimated Time</b></font> is incorrect<br/>";
		}		
		if ($project_is_domain_required && !$task_domain) {
			$local_errors .= "The value in field <font color=\"red\"><b>Domain</b></font> is required.<br>";
		}		
		if ($local_errors) {
			$errors .= $local_errors;
			return false;
		} else {
			return true;
		}		
	}
	
	function showClient($client_id) {
		global $db, $T;
		if (!$client_id) {
			$T->set_var("client_desc", "Nobody");
			return false;
		}
		$sql  = " SELECT c.client_name, c.client_company, ";
		$sql .= " IF(c.client_type = 1,c.client_email,c.google_accounts_emails) AS client_email FROM clients c";
		$sql .= " WHERE c.client_id=" . ToSQL($client_id, "integer");
		$db->query($sql);
		if ($db->next_record()) {
			$client_name    = $db->f("client_name");
			$client_company = $db->f("client_company");
			$client_email   = $db->f("client_email");
			if ($client_company) {
				$client_name .= "<br>" . $client_company;
			}
			if ($client_email) {
				$tmp = explode(";", trim($client_email));
				if (strlen($tmp[0])) {
					$client_email = $tmp[0];
				}
				$client_name .= "<br>" . $client_email;
			}
		
			$T->set_var("client_desc", $client_name);
		} else {
			$T->set_var("client_desc", "Nobody");
			return false;
		}
	}
	function showProjects($project_id, $project_filter_my, $project_filter_in_progress) {
		global $db, $T, $user_id;
		$sql  = " SELECT p.project_id, p.project_title FROM (((projects p ";
		$sql .= " INNER JOIN projects sp ON sp.parent_project_id=p.project_id) ";
		if ($project_filter_my) {
			$sql .= " INNER JOIN users_projects up ON p.project_id=up.project_id) ";			
			$sql .= " INNER JOIN users_projects usp ON sp.project_id=usp.project_id) ";
		} else {
			$sql .= "))";
		}		
	    $sql .= " WHERE p.parent_project_id IS NULL AND sp.project_id IS NOT NULL";
	   	$sql .= " AND (p.is_closed IS NULL OR p.is_closed=0)";
	    if ($project_filter_my) {
		    $sql .= " AND (up.user_id=" . ToSQL($user_id, "integer");
	    	$sql .= " OR usp.user_id=" . ToSQL($user_id, "integer");
	    	if ($project_id)
	    		$sql .= " OR p.project_id= " . ToSQL($project_id, "integer");    	
	    	$sql .= " )";
	    }
	    $sql .= " GROUP BY p.project_title ";
	    $db->query($sql);
	    if($db->next_record()) {
	    	do {
		    	$show_project_id    = $db->f("project_id");
		    	$show_project_title = $db->f("project_title");
		    	$T->set_var("project_id_value",    $show_project_id);
		    	$T->set_var("project_id_desc", ToHTML($show_project_title));
		    	$T->set_var("project_id_selected", ($project_id == $show_project_id) ? "selected" : "");
		    	$T->parse("project_id");
	    	} while ($db->next_record());
	    	$T->parse("project_block");
	    } else {
	    	$T->set_var("project_block", "no projects");
	    }	   
	}
	
	function showSubProjects($project_id, $sub_project_id, $project_filter_my, $project_filter_in_progress) {
		global $db, $T, $user_id;
		if (!$project_id) {
			$T->set_var("sub_project_block", "please choose a project");
			return;
		}
		$sql  = " SELECT p.project_id, p.project_title FROM (projects p ";
		if ($project_filter_my) {
			$sql .= " INNER JOIN users_projects up ON p.project_id=up.project_id) ";
		} else {
			$sql .= ")";
		}
		$sql .= " WHERE p.parent_project_id=" . ToSQL($project_id, "integer");
		$sql .= " AND (p.is_closed IS NULL OR p.is_closed=0)";
	    if ($project_filter_my) {
		    $sql .= " AND (up.user_id=" . ToSQL($user_id, "integer");
		    if ($sub_project_id)
		    	$sql .= " OR p.project_id= " . ToSQL($sub_project_id, "integer");
		    $sql .= " )";
	    }
	    $sql .= " GROUP BY p.project_title ";
	    $db->query($sql);
	   	if($db->next_record()) {
	   		do {
		    	$show_project_id    = $db->f("project_id");
		    	$show_project_title = $db->f("project_title");
		    	$T->set_var("sub_project_id_value",    $show_project_id);
		    	$T->set_var("sub_project_id_desc", ToHTML($show_project_title));
		    	$T->set_var("sub_project_id_selected", ($sub_project_id == $show_project_id) ? "selected" : "");
		    	$T->parse("sub_project_id");
	   		} while ($db->next_record());
	    	$T->parse("sub_project_block");
	    } else {
	    	$T->set_var("sub_project_block", "no sub-projects");
	    }	   
	}
	
	function showProjectUsers($task_id, $responsible_user_id, $project_id, $sub_project_id) {
		global $db, $T;
		
		$sql  = " SELECT u.user_id, u.first_name, u.last_name FROM (users u ";
		$sql .= " INNER JOIN users_projects up ON u.user_id=up.user_id) ";
		$sql .= " WHERE up.project_id=" . ToSQL($sub_project_id, "integer");
		$sql .= " AND u.is_deleted IS NULL";
		$sql .= " ORDER BY u.first_name, u.last_name";
		$db->query($sql);
	    if ($db->next_record()) {
	    	do {
		    	$show_user_id    = $db->f("user_id");
		    	$show_first_name = $db->f("first_name");
		    	$show_last_name  = $db->f("last_name");
		    	$T->set_var("responsible_user_id_value",    $show_user_id);
		    	$T->set_var("responsible_user_id_desc", ToHTML($show_first_name . " " . $show_last_name));
		    	$T->set_var("responsible_user_id_selected", ($responsible_user_id == $show_user_id) ? "selected" : "");
		    	$T->parse("responsible_user_id");
	    	} while ($db->next_record());
	    	$T->parse("responsible_user_block");
	    } else {
	    	$T->set_var("responsible_user_block", "no users");
	    }
	}
	
	function parseEstimatedHours($estimated_hours) {
		$estimated_hours = str_replace(" ", "", $estimated_hours);
		$strpos_d = strpos($estimated_hours, "d");
		if ($strpos_d === false) {
			$estimated_hours = (float) str_replace(array("h", "hours", "hour"), "", $estimated_hours);
		} else {
			$before_d = substr($estimated_hours, 0, $strpos_d);
			$after_d  = substr($estimated_hours, $strpos_d);
			if (strlen((float)$before_d) == strlen($before_d)) {
				$estimated_hours = $before_d * 8 
					+ (float) str_replace(array("h", "hours", "hour", "days", "day"), "", $after_d);
			} else {
				$strpos_h = strpos($estimated_hours, "h");
				$before_h = substr($estimated_hours, 0, $strpos_h);
				$after_h  = substr($estimated_hours, $strpos_h);
				if (strlen((float)$before_h) == strlen($before_h)) {
					$estimated_hours = $before_h 
						+ (float) 8 * str_replace(array("h", "hours", "hour", "days", "day"), "", $after_h);
				} else {
					return false;
				}
			}
		}
		return $estimated_hours;
	}
	
?>