<?php
	include("./includes/common.php");

	CheckSecurity(1);

	$enable = "DISABLED";
	$view_enable = "";
	$view_readonly = "";
	$err    = "";

	$manager_id	= GetParam("manager_id")?GetParam("manager_id"):GetSessionParam("UserID");
	$date		= GetParam("date_added");
	$user_id	= GetParam("user_name")?GetParam("user_name"):-1;
	$report_id	= GetParam("report_id");
	$operation	= strtolower(GetParam("operation"));
	$points		= GetParam("points");
	$mailsend	= GetParam("mailsend")=="ON"?true:false;
	$manager_name	= GetParam("manager_name")?GetParam("manager_name"):GetSessionParam("UserName");
	$morning_note	= GetParam("morning_note");
	$evening_note	= GetParam("evening_note");
	$points_array	= array(0 => "haven't done anything(0)",
							1 => "done something(1)",
							2 => "done most of the plan(2)",
							3 => "done as estimated(3)",
							4 => "done even more than estimated(4)",
							5 => "done twice and more then estimated(5)");

	$ret_page	= @$_SERVER["HTTP_REFERER"]?@$_SERVER["HTTP_REFERER"]:"managing_reports.php";

	if ($operation == "delete") {		$sql = "DELETE FROM managing_reports WHERE report_id=".ToSQL($report_id,"integer");
		$db->query($sql,__FILE__,__LINE__);		header("Location: managing_reports.php");		exit;	}
	elseif ($operation == "update") {		if (!$morning_note && !$evening_note) { $err .= "<b>Report notes</b> is required<br>";}
		if (!$manager_id || $manager_id == -1) { $err .= "<b>Manager name</b> is required<br>";}
		if (!$date) { $err .= "<b>Date</b> is required<br>";}
		if (!$user_id || $user_id == -1) { $err .= "<b>User name</b> is required<br>";}
		if (!$err){			$sql = "UPDATE managing_reports
					SET	manager_id	= ".ToSQL($manager_id,"integer").",
						user_id		= ".ToSQL($user_id,"integer").",
						date_added	= ".ToSQL($date,"date").",
						points		= ".ToSQL($points,"integer").",
						morning_notes = ".ToSQL($morning_note,"string").",
						evening_notes = ".ToSQL($evening_note,"string")."
					WHERE	report_id=".ToSQL($report_id,"integer");

			$db->query($sql,__FILE__,__LINE__);
			
			if ($mailsend && !$evening_note) {
				$sql = "SELECT	u.email AS user_email,
								mu.email AS manager_email
						FROM	users AS u
								LEFT JOIN users AS mu ON (mu.user_id=u.manager_id)
						WHERE u.user_id=".ToSQL($user_id,"integer");
				$db->query($sql,__FILE__,__LINE__);
				$db->next_record();
				$emailto	= $db->Record["user_email"];
				$emailfrom	= $db->Record["manager_email"];
				$headers = "From: ".$emailfrom."\n";
				$headers .= "Reply-To: ".$emailfrom."\n";
				$dt = split('[/.-]',$date);
				$subject	= "Daily plan from ".GetSessionParam("UserName")." - ".date("jS M Y",mktime(0,0,0,$dt[1],$dt[2],$dt[0]));
				@mail($emailto,$subject,$morning_note,$headers);
			}
			header("Location: managing_reports.php");
			exit;
		}
	}
	elseif ($operation == "add") {
		if (!$morning_note && !$evening_note) { $err .= "<b>Report notes</b> is required<br>";}
		if (!$manager_id || $manager_id == -1) { $err .= "<b>Manager name</b> is required<br>";}
		if (!$date) { $err .= "<b>Date</b> is required<br>";}
		if (!$user_id || $user_id == -1) { $err .= "<b>User name</b> is required<br>";}
		if (!$err){			$sql = "INSERT INTO managing_reports
					SET	manager_id	= ".ToSQL($manager_id,"integer").",
						user_id		= ".ToSQL($user_id,"integer").",
						date_added	= ".ToSQL($date,"date").",
						points		= ".ToSQL($points,"integer").",
						morning_notes = ".ToSQL($morning_note,"string").",
						evening_notes = ".ToSQL($evening_note,"string");
			$db->query($sql,__FILE__,__LINE__);

			if ($mailsend) {				$sql = "SELECT	u.email AS user_email,
								mu.email AS manager_email
						FROM	users AS u
								LEFT JOIN users AS mu ON (mu.user_id=u.manager_id)
						WHERE u.user_id=".ToSQL($user_id,"integer");
				$db->query($sql,__FILE__,__LINE__);
				$db->next_record();
				$emailto	= $db->Record["user_email"];
				$emailfrom	= $db->Record["manager_email"];
				$headers = "From: ".$emailfrom."\n";
				$headers .= "Reply-To: ".$emailfrom."\n";
				$dt = split('[/.-]',$date);
				$subject	= "Daily plan from ".GetSessionParam("UserName")." - ".date("jS M Y",mktime(0,0,0,$dt[1],$dt[2],$dt[0]));
				@mail($emailto,$subject,$morning_note,$headers);			}
			/**/
			header("Location: managing_reports.php");
			exit;
		}	}
	elseif ($report_id <> -1 && !$operation) {		$sql = "SELECT
					mr.report_id AS report_id,
					mr.user_id AS user_id,
					mr.manager_id AS manager_id,
					mr.points AS points,
					DATE(mr.date_added) as date_added,
					mr.morning_notes AS morning_note,
					mr.evening_notes AS evening_note,
					CONCAT(mu.first_name,' ',mu.last_name) AS manager_name
			FROM	managing_reports AS mr
					LEFT JOIN users AS mu ON (mu.user_id=mr.manager_id)
			WHERE	mr.report_id=".ToSQL($report_id,"integer");
			$db->query($sql,__FILE__,__LINE__);

			if ($db->num_rows()==0) {$err .= "No record fined<br>";}
			if (!$err) {				$db->next_record();
				$report_id	= $db->Record["report_id"];
				$user_id	= $db->Record["user_id"];
				$manager_id	= $db->Record["manager_id"];
				$points		= $db->Record["points"];
				$date		= $db->Record["date_added"];
				$manager_name	= $db->Record["manager_name"];
				$morning_note	= $db->Record["morning_note"];
				$evening_note	= $db->Record["evening_note"];
				if ( GetSessionParam("privilege_id") !=4 || GetSessionParam("UserID") != $manager_id) {					$view_enable = "DISABLED";
					$view_readonly = "READONLY";				}
				$operation	= "Update";
				$enable = "enable";			}	}
	elseif ($report_id == -1) {		if (GetSessionParam("privilege_id")<4) {			header("Location: managing_reports.php");
			exit;		}
		
		$date	= date("Y-m-d");
		$manager_id	= GetSessionParam("UserID");
		$manager_name = GetSessionParam("UserName");
		$points = 0;		$operation	= "Add";	}


    $T = new iTemplate("./templates",array("page"=>"managing_reports_edit.html"));
    if ($err) $T->set_var("err", $err); else $T->set_var("error", "");//$T->set_var("err",$err);
	$T->set_var("enable",$enable);

	$T->set_var(array(
		"morning_note"	=> $morning_note,//@$db->Record["inventory_title"],//@$inventory_title,
		"evening_note"	=> $evening_note,//@$db->Record["inventory_title"],//@$inventory_title,
		"date_added"	=> $date,//@$db->Record["inventory_desc"],//@$inventory_desc,
		"report_id"		=> ($report_id?$report_id:-1),//@$date_added
		"manager_id"	=> $manager_id,
		"manager_name"	=> $manager_name,
		"points"		=> $points,
		"points_list"	=> get_radiobutton_list($points_array,$points,$manager_id),
		"operation"		=> $operation,
		"view_enable"	=> $view_enable,
		"view_readonly"	=> $view_readonly,
		"urlback"		=> $ret_page
		));

	// blocks with tasks
	
	$task_number = 0;
	
	$in_progress_task_id = 0;
		
	$sql = " SELECT p.project_title, t.task_id, t.task_title, t.completion, t.planed_date AS pdate ";
	$sql.= ", ls.status_desc, lt.type_desc, t.task_status_id, t.estimated_hours ";
	$sql.= ", IF (TO_DAYS(t.planed_date) < TO_DAYS(now()) AND t.task_status_id!=4 AND t.task_type_id!=3,1,0 ) AS ifdeadlined ";
	$sql.= ", IF (TO_DAYS(t.planed_date) = TO_DAYS(now()) AND t.task_status_id!=4 AND t.task_type_id!=3,1,0 ) AS iftoday ";
	$sql.= " FROM tasks t ";
	$sql.= " INNER JOIN projects p ON (t.project_id = p.project_id) ";
	$sql.= " INNER JOIN lookup_task_types lt ON (t.task_type_id = lt.type_id) ";
	$sql.= " INNER JOIN lookup_tasks_statuses ls ON (t.task_status_id = ls.status_id) ";
	$sql.= " WHERE t.responsible_user_id = " . ToSQL($user_id, "number")." AND t.is_closed=0 AND t.is_wish=0 ";
	$sql.= " ORDER BY t.priority_id, t.project_id, t.task_id DESC ";
	$db->query($sql);
	
	if ($db->num_rows()) {
	while ($db->next_record()) {
		$task_number++;
		$project_title = $db->f("project_title");
		$task_title = $db->f("task_title");
		$completion = $db->f("completion");
		$status_desc = $db->f("status_desc");
		$task_type = $db->f("type_desc");
		$task_status_id = $db->f("task_status_id");
		$estimated_hours = $db->f("estimated_hours");
		
		if ($db->f("ifdeadlined")) {
			$T->set_var("option_color", "#C40000");
		} elseif ($db->f("iftoday")) {
			$T->set_var("option_color", "#C47200");
		} else {
			$T->set_var("option_color", "#000000");
		}
		
		if ($task_status_id==4) {
			$T->set_var("option_color", "#00C405");
		}
		
		if ($estimated_hours) {
			$estimate_text = "estimate: ".trim(to_hours($estimated_hours)).", ";
		} else {
			$estimate_text = "";
		}
		
		
		if ($task_status_id==1) {
			$in_progress_task = $db->Record;
			$in_progress_task_id = $db->f("task_id");
		}		
		if (strlen($task_title)>35) {
			$short_task_title = substr($task_title, 0, 33)."...";
		} else {
			$short_task_title = $task_title;
		}

		if (strlen($project_title)>13) {
			$short_project_title = substr($project_title, 0, 11)."...";
		} else {
			$short_project_title = $project_title;
		}		
		
		
		$list_name = $project_title.": ".$task_title;
		if (strtolower($task_type)!="periodic") {
			$list_desc = " (".intval($completion)."%, ".$estimate_text.$status_desc.")";
		} else {
			$list_desc = " (periodic)";
		}		
		
		$T->set_var("available_user_tasks_description", $task_number.". ".$short_project_title.": ".$short_task_title.$list_desc);
		$T->set_var("available_user_tasks_value", $task_number.". ".str_replace("\"", "''", $list_name));
		$T->parse("av_user_tasks",true);				   
	}
	} else {
		$T->set_var("av_user_tasks","");
	}
	
	if (!isset($date)) {
		$report_date = "NOW()";
	} else {
		$report_date = "'".$date."'";
	}
	
	$task_number = 0;
	$done_tasks = array();	
	$sql = " SELECT p.project_title, t.task_id, t.task_title, t.completion, t.task_status_id, t.planed_date AS pdate ";
	$sql.= ", ls.status_desc, lt.type_desc, SUM(tr.spent_hours) AS task_hours ";
	$sql.= ", IF (TO_DAYS(t.planed_date) < TO_DAYS(now()) AND t.task_status_id!=4 AND t.task_type_id!=3,1,0 ) AS ifdeadlined ";
	$sql.= ", IF (TO_DAYS(t.planed_date) = TO_DAYS(now()) AND t.task_status_id!=4 AND t.task_type_id!=3,1,0 ) AS iftoday ";
	$sql.= " FROM time_report tr ";
	$sql.= " INNER JOIN tasks t ON (tr.task_id=t.task_id) ";
	$sql.= " INNER JOIN projects p ON (t.project_id = p.project_id) ";
	$sql.= " INNER JOIN lookup_task_types lt ON (t.task_type_id = lt.type_id) ";
	$sql.= " INNER JOIN lookup_tasks_statuses ls ON (t.task_status_id = ls.status_id) ";
	$sql.= " WHERE tr.user_id = ".ToSQL($user_id, "number")." AND TO_DAYS(tr.started_date)=TO_DAYS(".$report_date.") ";
	$sql.= " GROUP BY t.task_id ";
	$sql.= " ORDER BY tr.started_date, t.priority_id, t.task_id DESC ";
	
	$db->query($sql);
	
	$in_progress_task_in_time_report = false;
	
	while ($db->next_record()) {
		$task_id = $db->f("task_id");		
		if ($task_id==$in_progress_task_id) {
			$in_progress_task_in_time_report = true;
		}
		$done_tasks[] = $db->Record;		
	}
	if (!$in_progress_task_in_time_report && isset($in_progress_task)) {
		$done_tasks[] = $in_progress_task;
	}
	
	if (sizeof($done_tasks)) {
		foreach($done_tasks as $task)
		{
			$task_number++;
			$project_title = $task["project_title"];
			$task_title = $task["task_title"];
			$completion = $task["completion"];
			$status_desc = $task["status_desc"];
			$task_type = $task["type_desc"];
			$task_status_id = $task["task_status_id"];
		
			if ($task["ifdeadlined"]) {
				$T->set_var("option_color", "#C40000");
			} elseif ($task["iftoday"]) {
				$T->set_var("option_color", "#C47200");
			} else {
				$T->set_var("option_color", "#000000");
			}
			if ($task_status_id==4) {
				$T->set_var("option_color", "#00C405");
			}
			
			if (isset($task["task_hours"]) && $task["task_hours"] ) {
				$task_hours = trim(Hours2HoursMins($task["task_hours"]));
			} else {
				$task_hours = false;
			}
			
		
			if (strlen($task_title)>35) {
				$short_task_title = substr($task_title, 0, 33)."...";
			} else {
				$short_task_title = $task_title;
			}		
			if (strlen($project_title)>13) {
				$short_project_title = substr($project_title, 0, 11)."...";
			} else {
				$short_project_title = $project_title;
			}				
			$list_name = $project_title.": ".$task_title;		
			$list_desc = " (";
			if ($task_hours) {
				$list_desc.= $task_hours.", ";
			}
		
			if (strtolower($task_type)!="periodic") {
				$list_desc .= intval($completion)."%, ".$status_desc.")";
			} else {
				$list_desc .= "periodic)";
			}
		
			$T->set_var("available_done_tasks_description", $task_number.". ".$short_project_title.": ".$short_task_title.$list_desc);
			$T->set_var("available_done_tasks_value", $task_number.". ".str_replace("\"", "''", $list_name.$list_desc));
			$T->parse("av_done_tasks",true);
		}
	} else {
		$T->set_var("av_done_tasks","");
	}
	
	
	$T->set_var("user_list", 	Get_Options("users WHERE is_deleted IS NULL AND manager_id=".ToSQL($manager_id,"integer")." ORDER BY user_name",
											"user_id",
											"CONCAT(first_name,' ',last_name) as user_name",
											/*"user_name",*/
											$user_id, ""
											));
											
	

    if ( GetSessionParam("privilege_id") !=4 || GetSessionParam("UserID") != $manager_id) {    	$T->set_var("control","");    }

	$T->pparse("page");

/**
*	Functions
**/
function get_radiobutton_list($points_array,$points,$manager_id)
{	$result = "";
	$view_enable = "";

    if ( GetSessionParam("privilege_id") !=4 || GetSessionParam("UserID") != $manager_id) { $view_enable = "DISABLED";}
	foreach($points_array as $key => $val){		$checked = "";		if ($key == $points) { $checked = "checked";}
		$result .= "&nbsp;&nbsp;&nbsp;<input name='radiobutton' type='radio'".$view_enable." value='".$key."' ".$checked." onClick='changePoints(".$key.");'>".$val."<br>";	}
	return $result;
}

?>