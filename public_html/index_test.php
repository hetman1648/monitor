<?php

include("./includes/common.php");
include("./includes/date_functions.php");

$db2 = new DB_Sql;
$db2->Database = DATABASE_NAME;
$db2->User     = DATABASE_USER;
$db2->Password = DATABASE_PASSWORD;
$db2->Host     = DATABASE_HOST;

CheckSecurity(1);

	$initial = getmicrotime();
	show_microtime("\n\nSTART");

if (GetParam('close_project_id')){
	unset($_SESSION["session_perms"]['projects'][GetParam('close_project_id')]);
}

if (GetParam('open_project_id')){
	$_SESSION["session_perms"]['projects'][GetParam('open_project_id')]=1;
}

//for csv file
$outlook_statuses = array("", "In Progress", "Deferred", "Not Started", "Completed", "Not Started", "Not Started", "Not Started", "Waiting on Someone Else", "Reassigned to Someone else","Found Bug");
$csv = "Subject,Start Date,Due Date,Reminder On/Off,Reminder Date,Reminder Time,Date Completed,% Complete,Total Work,Actual Work,";
$csv .= "Billing Information,Categories,Companies,Contacts,Mileage,Notes,Priority,Role,Schedule+ Priority,Sensitivity,Status\n";

$session_user_id = GetSessionParam("UserID");
$task_id	= Getparam("task_id");
$task_ids	= Getparam("task_ids");
$action		= GetParam("action");
$hide_id	= GetParam("hide_id");
$hide_all	= GetParam("hide_all");
$sort		= GetParam("sort");
$completion = GetParam("completion");

if ($sort=='') {
	$sort=@$_SESSION["session_perms"]['sort'];
} else {
	$_SESSION["session_perms"]['sort']=$sort;
}

//-- actions
	switch ($action)
	{
		case "close":
			if ($task_id && is_numeric($task_id)) {
				close_task($task_id);
			} elseif (strlen($task_ids)) {
				close_tasks($task_ids, "index.php");
			}
			break;
		case "start":
			if ($task_id && is_numeric($task_id)) {
				start_task($task_id, $completion);
			}
			break;
		case "stop":
			if ($task_id && is_numeric($task_id)) {
				stop_task($task_id, $completion);
			}
			break;
		case "assign":
			if ($task_id && is_numeric($task_id)) {
				assign_to_myself_task($task_id);
			}
			break;	
	}

$sql = "SELECT * FROM lookup_users_privileges WHERE privilege_id = ".ToSQL(GetSessionParam("privilege_id"),"integer");
$db->query($sql);
if ($db->next_record()) {
	$customer = $db->Record["PERM_OWN_TASKS_ONLY"];
}

		show_microtime("choose privileges");

//choose template
if ($customer) {
	$T = new iTemplate("./templates", array("page"=>"index_customer.html"));
} else {
	if (!has_permission("PERM_VIEW_ALL_TASKS")) {
		$T = new iTemplate("./templates",array("page"=>"index.html"));
	} else {
		$T = new iTemplate("./templates",array("page"=>"index.html"));
	}
}

$opened_tasks = array();
$T->set_var("user_name", GetSessionParam("UserName"));

if ($customer) {
	$sql = " SELECT *, t.creation_date AS cdate ";
	$sql.= " FROM	tasks AS t, projects AS p, lookup_tasks_statuses AS ls, lookup_task_types AS lt ";
	$sql.= " WHERE	t.created_person_id = ".$session_user_id."
					AND t.responsible_user_id <> ".$session_user_id."
					AND p.project_id = t.project_id
	 				AND ls.status_id = t.task_status_id
	 				AND lt.type_id = t.task_type_id";
	$db->query($sql);

	if ($db->next_record()) {
		do {
			$T->set_var("ctask_title", $db->Record["task_title"]);
			$T->set_var("cproject_title", $db->Record["project_title"]);
			$T->set_var("cstatus_desc", $db->Record["status_desc"]);
			$T->set_var("ctype_desc", $db->Record["type_desc"]);
			$T->set_var("cpriority_id", $db->Record["priority_id"]);
			$T->set_var("no_myctasks", "");
			$T->set_var("ccreation_date", norm_sql_date($db->Record["cdate"]));
			$T->set_var("cplaned_date", norm_sql_date($db->Record["planed_date"]));
			$T->set_var("ctask_id", $db->Record["task_id"]);
			$T->set_var("cestimated_title", $db->Record["estimated_title"]);
			$T->set_var("cestimated_hours", $db->Record["estimated_hours"]);
			$T->set_var("cactual_hours", to_hours($db->Record["actual_hours"]));
			$T->set_var("url_page","index.php");
			$T->parse("myctasks", true);
		} while ($db->next_record());
	} else {
		$T->set_var("myctasks", "");
	}

	
  	//-- my tasks
  	$sql = "SELECT *, t.creation_date AS cdate, t.planed_date AS pdate, DATE_FORMAT(t.creation_date, '%D %b %Y') AS creation_date, "
        . "DATE_FORMAT(t.planed_date, '%D %b %Y') AS planed_date, "
        . "UNIX_TIMESTAMP(t.creation_date) AS cdate_nix, UNIX_TIMESTAMP(t.planed_date) AS pdate_nix,"
        . "IF(t.task_status_id=2, 1, 0) AS sorter " //Tasks with status "On Hold" will be at the end of query
        . "FROM tasks AS t, projects AS p, lookup_task_types AS lt, lookup_tasks_statuses AS ls "
        . "WHERE t.project_id = p.project_id AND t.task_type_id = lt.type_id AND t.task_status_id = ls.status_id "
        . "AND t.is_wish = 0 AND t.responsible_user_id = " . $session_user_id
        . " AND t.is_closed = 0 ORDER BY sorter, t.priority_id, t.project_id ";

	$db->query($sql);
  	$project_name = "";
  	$k = true;
  	$is_working = false;

  	if ($db->next_record()) {
		do {
			$T->set_var($db->Record);
			if ($db->Record["cdate"] == "0000-00-00 00:00:00") $T->set_var("creation_date", "");
			if ($db->Record["pdate"] == "0000-00-00 00:00:00") $T->set_var("planed_date", "");
			if ($project_name != $db->Record["project_title"]) {
				$project_name = $db->Record["project_title"];
				$T->set_var("project_title", $project_name);
			} else {
				$T->set_var("project_title", "");
			}

		  	$T->set_var("estimated_title", $db->Record["estimated_title"]);
		  	$T->set_var("estimated_hours", $db->Record["estimated_hours"]);
		  	$T->set_var("task_title_slashed", addslashes(str_replace("\"", "", $db->Record["task_title"])));

		  	$actual_hours = to_hours($db->Record["actual_hours"]);

		  	//-- current task
		  	if ($db->Record["status_id"] == 1) {
		  		$T->set_var("operation_status","Stop");
		  		$is_working = true;
		  		$T->set_var("url_page","index.php");
		  		$T->parse("current_task",false);
		  	} else {
		  		$T->set_var("operation_status","Start");
	  		}

			if ($k) {
				$T->set_var("STATUS", $statuses_classes[$db->Record["status_id"]]);
			} else {
				$T->set_var("STATUS", $statuses_classes[$db->Record["status_id"]] . "2");
			}

			$k = !$k;
			$T->set_var("url_page","index.php");
			$T->parse("mytasks", true);		

			//write to csv
			$task_notes = str_replace("\r\n", " ", $db->f("task_desc"));
			$task_notes = str_replace("\n", "", $task_notes);
			$task_notes = str_replace("\"", "'", $task_notes);
			$csv .= $db->f("task_title") . ",\"" . date("Y-m-d", $db->f("cdate_nix")) . "\",\"" . date("Y-m-d", $db->f("pdate_nix"));
			$csv .= "\",FALSE,,,," . ($db->f("completion") ? "1" : "0") . ",0,0,,,,,,\"" . $task_notes . "\",Normal,,,Normal,";
			$csv .= (isset($outlook_statuses[$db->f("task_status_id")]) ? $outlook_statuses[$db->f("task_status_id")] : "") . "\n";
	   	} while ($db->next_record());

	    	$T->set_var("no_mytasks", "");
  	} else {
  		$T->set_var("mytasks", "");
  	}
  	
  	if (!$is_working) $T->set_var("current_task", "");
  	$T->parse("tasks_list",false);
  	$download = GetParam("download");
	if ($download == 1) {
		$filename = "tasks_" . date("Y-m-d") . ".csv";
		header("Content-Type: application/x-ms-download");
		header("Content-Length: " . strlen($csv));
		header("Content-Disposition: attachment; filename=" . $filename);
		header("Content-Transfer-Encoding: binary");
		header("Cache-Control: Public");
		header("Expires: 0");
		echo $csv;
		exit;
	}
} else {
	
//BEGIN - DEVELOPERS AND MANAGERS SHOULD HAVE IDENTICAL COLUMNS ALLOWED

	$view_all_tasks = has_permission("PERM_VIEW_ALL_TASKS");
	$approve_vacations = ($session_user_id == 3);
	$show_users_list = false;
	$show_projects_list = false;
	$sql = " SELECT show_users_list, show_projects_list ";
	$sql.= " FROM users WHERE user_id=".ToSQL($session_user_id, "integer");
	$db->query($sql);
	if ($db->next_record()) {
		$show_projects_list = $db->f("show_projects_list");
		$show_users_list = $db->f("show_users_list");
	}
	
	show_microtime("choose columns");	
	
	//begin approving vacations
	if ($approve_vacations)
	{
		$sql2 = "SELECT * from days_off where is_approved='0' AND is_declined='0'";
		$db2->query($sql2);
		if ($db2->next_record())
		{
			$T->set_var("no_approve_vacation", "");
			$T->parse("header_approve_vacation");
			do {
				$sql = "SELECT user_id, first_name, last_name FROM users";
				$db->query($sql);
				while ($db->next_record()) {
					if ($db->Record["user_id"] == $db2->Record["user_id"]) $user_name = $db->Record["first_name"]." ".$db->Record["last_name"];
				}
				$T->set_var("user_name", $user_name);
				$T->set_var("title", "<a href=create_vacation.php?vacation_id=".$db2->Record["period_id"].">".$db2->Record["period_title"]."</a>");

				$sql = "SELECT reason_id, reason_name FROM reasons";
				$db->query($sql);
				while ($db->next_record()) {
				     if ($db->Record["reason_id"] == $db2->Record["reason_id"]) $reason_type = $db->Record["reason_name"];
				}

				if ($db2->Record["is_paid"]==1) {
					$type = "Overwork";
				} else {
					$type = "Vacation";
				}
				$T->set_var("type", $type);
				$notes = $db2->Record["notes"];
				$period_id = $db2->Record["period_id"];
				//$T->set_var("notes", $notes);
				$T->set_var("period_id", $period_id);
				$T->set_var("reason", $reason_type);
				$T->set_var("vacation_start_date", norm_sql_date($db2->Record["start_date"]));
				$T->set_var("vacation_end_date", norm_sql_date($db2->Record["end_date"]));
				$T->set_var("vacation_total_days", $db2->Record["total_days"]);
				$T->parse("approve_vacation", true);
			} while ($db2->next_record());
			
			$T->parse("for_Artem");
		}
		else
		{
			$T->set_var("for_Artem", "");
		}
	} else {
		$T->set_var("for_Artem", "");
	}
	//end approving vacations
	
	show_microtime("approve vac");	
	
	if ($show_users_list) {
   		show_users_list("viart_reports","spotlight_reports", "viart_team_count", "yoonoo_team_count");
   		$T->parse("users_list_block", "false");
	} else {
		$T->set_var("users_list_block", "");
	}

	show_microtime("show users list");	
	
   	//projects/////////////////////////////////////////////////////
   	//first - parse cookies
   	$project_state_cookie = "m00";
   	$project_state = array();
   	if (isset($_COOKIE) && is_array($_COOKIE) && isset($_COOKIE["monitorprojectstate"])) {
   		$project_state_cookie = $_COOKIE["monitorprojectstate"];
   	} elseif (isset($HTTP_COOKIE_VARS) && is_array($HTTP_COOKIE_VARS) && isset($HTTP_COOKIE_VARS["monitorprojectstate"])) {
   		$project_state_cookie = $HTTP_COOKIE_VARS["monitorprojectstate"];
   	}
   	
   	if(substr($project_state_cookie,0,1)=="m") {
   		$project_state["my_projects"] = 1;
   		$T->set_var("act_my", "act");
   		$T->set_var("act_all", "");
   		$T->set_var("noborder_my", "noborder");
   		$T->set_var("noborder_all", "");
   	} else {
   		$project_state["my_projects"] = 0;
   		$T->set_var("act_my", "");
   		$T->set_var("act_all", "act");
   		$T->set_var("noborder_my", "");
   		$T->set_var("noborder_all", "noborder");
   	}
   	if(substr($project_state_cookie,1,1)=="1") {
   		$project_state["show_completed"] = 1;
   		$T->set_var("project_filter_cm_checked", "checked");
   	} else {
   		$project_state["show_completed"] = 0;
   		$T->set_var("project_filter_cm_checked", "");
   	}
   	if(substr($project_state_cookie,2,1)=="1") {
   		$project_state["show_closed"] = 1;
   		$T->set_var("project_filter_cl_checked", "checked");
   	} else {
   		$project_state["show_closed"] = 0;
   		$T->set_var("project_filter_cl_checked", "");
   	}
   	
   	$opened_branches = explode("_", substr($project_state_cookie,3));
   	foreach($opened_branches as $branch) {
   		if (strlen($branch)) {
   			$project_state[$branch] = "opened";
   		}
   	}
   	
	show_microtime("projects state");
  	
   	//////////////////////////
   	///then prepare my/closed/completed projects arrays
	
   	$sql = " SELECT p.project_id, p.is_closed, ps.is_completed, COUNT(up.user_id) AS project_user_id ";
	$sql.= " FROM projects p ";
	$sql.= " INNER JOIN projects subp ON (subp.parent_project_id=p.project_id OR subp.project_id=p.project_id) ";
	$sql.= " LEFT JOIN users_projects up ON (up.project_id=subp.project_id AND up.user_id=".ToSQL($session_user_id, "integer").") ";
	$sql.= " LEFT JOIN projects_statuses ps ON (ps.project_status_id=p.project_status_id) ";	
	$sql.= " WHERE p.parent_project_id IS NULL ";
    $sql.= " GROUP BY p.project_id ";
	$pp_user = array();
    $db->query($sql);
    if ($db->num_rows()) {
     	while ($db->next_record()) {
      		$T->set_var("project_elem", "pr".$db->f("project_id"));
       		if ($db->f("project_user_id")) {
       			$T->set_var("project_user_id", "1");
     			$pp_user[$db->f("project_id")] = 1;
       		} else {
       			$T->set_var("project_user_id", "0");
       			$pp_user[$db->f("project_id")] = 0;
       		}
       		
       		$T->set_var("closed_project", intval($db->f("is_closed")));
       		$T->set_var("completed_project", intval($db->f("is_completed")));       		
       		$T->parse("my_projects_array", true);
       		$T->parse("cl_projects_array", true);
       		$T->parse("cm_projects_array", true);
       	}
	} else {
		$T->set_var("my_projects_array", "");
		$T->set_var("cl_projects_array", "");
		$T->set_var("cm_projects_array", "");
	}
	
	show_microtime("parent projects arrays");
	
	//-- projects
	$sql = " SELECT p.*, ps.status_desc, ps.color, up.user_id AS project_user_id, ps.is_completed ";
	$sql.= " FROM projects AS p ";
	$sql.= " LEFT JOIN projects_statuses ps ON (p.project_status_id=ps.project_status_id) ";
	$sql.= " LEFT JOIN users_projects up ON (up.project_id=p.project_id AND up.user_id=".ToSQL($session_user_id, "integer").") ";
	$sql.= " WHERE p.parent_project_id IS NULL ";
	$sql.= " ORDER BY project_title ";
	$db->query($sql);
	
	if($show_projects_list && $db->next_record()) {
		do {
			$T->set_var('tasks_count', (int)$db->Record['tasks_count']);
			$T->set_var('project_title', $db->Record["project_title"]);
			$T->set_var('project_id', $db->Record["project_id"]);	
			$T->set_var('abs_project_id', $db->Record["project_id"]);
			
			if (!isset($project_state[$db->Record["project_id"]])) {
				$T->set_var('plus_display', 'inline');
				$T->set_var('minus_display', 'none');
			} else {
				$T->set_var('plus_display', 'none');
				$T->set_var('minus_display', 'inline');				
			}			
			
			$T->set_var('subproject', '');
			$T->set_var('status_desc', '');
			$T->set_var('color', 'black');
			if ($db->f("is_closed")) {
       			$T->set_var('color', "#D0D0D0");
      			$T->set_var("status_desc", "Closed");
      		}
			
			if ( (!$project_state["my_projects"] || (isset($pp_user[$db->f("project_id")]) && $pp_user[$db->f("project_id")])) 
				&& ($project_state["show_closed"] || !$db->Record["is_closed"])
				&& ($project_state["show_completed"] || !$db->Record["is_completed"])			
			) {
				$T->set_var('pr_display', '');
			} else {
				$T->set_var('pr_display', 'none');
			}
          	$T->set_var('tab', '');
           	$sql2 = 'SELECT COUNT(*) as count FROM projects WHERE parent_project_id='.$db->Record['project_id'];
           	$db2->query($sql2);
           	$db2->next_record();
           	$T->set_var('count', $db2->Record['count']);
			
           	if ($db2->Record['count'] == 0) {
				$T->set_var('white_color', 'white');
			} else {
				$T->set_var('white_color', 'black');
			}
			$T->parse("projects", true);
			
			show_microtime("projects :".$db->Record["project_title"]);
				
			$sql2 = " SELECT p.*, ps.*, up.user_id AS project_user_id ";
			$sql2.= " FROM projects AS p ";
			$sql2.= " LEFT JOIN projects_statuses ps ON (p.project_status_id=ps.project_status_id) ";
			$sql2.= " LEFT JOIN users_projects up ON (p.project_id=up.project_id AND up.user_id=".ToSQL($session_user_id, "integer").") ";
			$sql2.= " WHERE p.parent_project_id = ".ToSQL($db->Record['project_id'], "integer");
			$sql2.= " ORDER BY p.project_title ";
			$db2->query($sql2);
			$i=1;              	
			
			if ($db2->next_record()) {
				do {
					$T->set_var('tab', '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;');
					$T->set_var('plus', '&nbsp;&nbsp;&nbsp;');
					$T->set_var('project_title', $db2->Record['project_title']);
               		$T->set_var('project_id', $db->Record["project_id"]);
               		$T->set_var('abs_project_id', $db2->Record["project_id"]);
               		$T->set_var('subproject', '_'.$i);//db2->Record["project_id"]);
               		
               		$T->set_var('plus_display', 'none');
           			$T->set_var('minus_display', 'none');
           			$T->set_var('tasks_count', (int)$db2->Record['tasks_count']);
           			
					if ( isset($project_state[$db->f("project_id")])
						&& (!$project_state["my_projects"] || $db2->f("project_user_id")) 
						&& ($project_state["show_closed"] || !($db2->Record["is_closed"] || $db->f("is_closed")))
						&& ($project_state["show_completed"] || !$db2->Record["is_completed"])
					) {   			
          				$T->set_var('pr_display', '');
					} else {
						$T->set_var('pr_display', 'none');
					}
   		       		$T->set_var('status_desc', $db2->f("status_desc"));   		       		
   		       		if ($db2->f("is_closed")) {
   		       			$T->set_var('color', "#D0D0D0");
   		       			$T->set_var("status_desc", "Closed");
   		       		} elseif (strlen($db2->f("color"))) {
         				$T->set_var('color', $db2->f("color"));
   		       		} else {
   		       			$T->set_var('color', 'black');
   		       		}

       				if ($db2->f("project_user_id")) {
       					$project_user_id = "1";
       				} else {
       					$project_user_id = "0";
       				}
       				$project_elem = "pr".$db->Record['project_id'].'_'.$i;
       				$T->set_var("project_elem", $project_elem);
       				$T->set_var("project_user_id", $project_user_id);
       				
       				$T->set_var("completed_project", intval($db2->f("is_completed")));
       				$T->set_var("closed_project", intval($db2->f("is_closed")));
       				$T->parse("my_projects_array", true);
					$T->parse("cl_projects_array", true);
	       			$T->parse("cm_projects_array", true);
               		$T->parse("projects", true);
               		$i++;
           		} while ($db2->next_record());
           	}
  		} while ($db->next_record());
    	$T->set_var("no_projects", "");
   		
   		$T->parse("projects_list_block", false);
	} else {
		$T->set_var("projects", "");
   		$T->set_var("projects_list_block", "");
	}	

	show_microtime("projects - main query cycle");
	
}

  	$is_manager = is_manager($session_user_id);
  	$is_working = false;
	
	show_manager_notes("manager_notes", $session_user_id);
	show_lunches_block("lunch_message", $session_user_id);
	show_active_bugs("active_bugs_message", $session_user_id);
	show_documents("knowledge_base", "documents_dropdown", $session_user_id);
	GetTasksList($session_user_id, $is_manager, $sort, $csv, $is_working);

	show_microtime("all other blocks");
	
 	if (!$is_working) {
 		$T->set_var("current_task", "");
 	}	

	$download = GetParam("download");

	if($download == 1) {
		$filename = "tasks_" . date("Y-m-d") . ".csv";
		header("Content-Type: application/x-ms-download");
		header("Content-Length: " . strlen($csv));
		header("Content-Disposition: attachment; filename=" . $filename);
		header("Content-Transfer-Encoding: binary");
		header("Cache-Control: Public");
		header("Expires: 0");
		echo $csv;
		exit;
	}

  	$T->set_var("user_name",GetSessionParam("UserName"));
  	$T->set_var("session_user_id", $session_user_id);
	$T->set_var("sort", $sort);

  	////end
  	
	show_microtime("user_name");

$sql = "SELECT MONTH(birth_date) as bMon, DAYOFMONTH(birth_date) as bDay, CONCAT(first_name,' ',last_name) AS user_name FROM users WHERE is_deleted IS NULL";
$db->query($sql);

$bds="";
while ($db->next_record())
{
	if (($db->Record["bMon"] == date("m")) and ($db->Record["bDay"] == date("d")))
	{
		$bds .= "<img src=\"images/bestwishes.gif\"> Today is a birthday of " .$db->f("user_name"). "!<br>";
	}
}

	show_microtime("birthday");

    //create list of reminders for this person

    $reminders = '';
    if ((GetSessionParam("privilege_id")==4) || (GetSessionParam("privilege_id")==3)){

        if ($hide_id){//if ($_REQUEST['hide_id']){
            $sql_hide='UPDATE reminders SET is_shown=0 WHERE reminder_id='.ToSQL($hide_id,"integer");//.GetParam('hide_id');
        	$db->query($sql_hide);

        }

        if ($hide_all){//if ($_REQUEST['hide_all']){
            $sql_hide='UPDATE reminders SET is_shown=0 WHERE user_id='.GetSessionParam('UserID');
        	$db->query($sql_hide);

        }

        $db->query('SELECT @user_id := '.GetSessionParam("UserID"));

		$sqlfile='reminders.sql';

		$f_sql=fread(fopen($sqlfile,'rt'),filesize($sqlfile));

		$f_sql = " INSERT IGNORE INTO reminders(event,user_id)
					SELECT CONCAT(first_name, ' ', last_name, ' has a birthday at ',DATE_FORMAT(birth_date,'%d %M')) as event, @user_id FROM users
					WHERE
						is_deleted IS NULL AND
						(((DAYOFYEAR(birth_date)-DAYOFYEAR(NOW()))<=3 AND
						(DAYOFYEAR(birth_date)-DAYOFYEAR(NOW()))>=0) OR
						(DAYOFYEAR(birth_date)-DAYOFYEAR(NOW())=5 AND DAYOFWEEK(NOW())=4) OR
						(DAYOFYEAR(birth_date)-DAYOFYEAR(NOW())IN(4,5) AND DAYOFWEEK(NOW())=5) OR
						(DAYOFYEAR(birth_date)-DAYOFYEAR(NOW())IN(3,4,5) AND DAYOFWEEK(NOW())=6))

					UNION

					SELECT
					IF(ROUND((TO_DAYS(NOW())-TO_DAYS(start_date))/365)=0,
					CONCAT('New person ',first_name, ' ', last_name,' starts working at ',DATE_FORMAT(start_date,'%d %M')),
					CONCAT(first_name, ' ', last_name, ' has ', ROUND((TO_DAYS(NOW())-TO_DAYS(start_date))/365), ' years of working at ',DATE_FORMAT(start_date,'%d %M'))), @user_id FROM users
					WHERE
						is_deleted IS NULL AND
						(((DAYOFYEAR(start_date)-DAYOFYEAR(NOW()))<=3 AND
						(DAYOFYEAR(start_date)-DAYOFYEAR(NOW()))>=0) OR
						(DAYOFYEAR(start_date)-DAYOFYEAR(NOW())=5 AND DAYOFWEEK(NOW())=4) OR
						(DAYOFYEAR(start_date)-DAYOFYEAR(NOW())IN(4,5) AND DAYOFWEEK(NOW())=5) OR
						(DAYOFYEAR(start_date)-DAYOFYEAR(NOW())IN(3,4,5) AND DAYOFWEEK(NOW())=6))


					UNION

					SELECT CONCAT(first_name, ' ', last_name, ' has 2 months of working at ',DATE_FORMAT(DATE_ADD(start_date,INTERVAL 2 MONTH),'%d %M')) as event, @user_id FROM users
					WHERE
						is_deleted IS NULL AND
						(((TO_DAYS(DATE_ADD(start_date,INTERVAL 2 MONTH))-TO_DAYS(NOW()))<=3 AND
						(TO_DAYS(DATE_ADD(start_date,INTERVAL 2 MONTH))-TO_DAYS(NOW()))>=0) OR
						(TO_DAYS(DATE_ADD(start_date,INTERVAL 2 MONTH))-TO_DAYS(NOW())=5 AND DAYOFWEEK(NOW())=4) OR
						((TO_DAYS(DATE_ADD(start_date,INTERVAL 2 MONTH))-TO_DAYS(NOW()))IN(4,5) AND DAYOFWEEK(NOW())=5) OR
						((TO_DAYS(DATE_ADD(start_date,INTERVAL 2 MONTH))-TO_DAYS(NOW()))IN(3,4,5) AND DAYOFWEEK(NOW())=6))


					UNION
					SELECT CONCAT(first_name, ' ', last_name, ' has a holiday (',(SELECT reason_name FROM reasons r WHERE r.reason_id = m.reason_id),') at ',DATE_FORMAT(m.max_start,'%d %M'), IF(m.max_start<>m.max_end,CONCAT(' till ', DATE_FORMAT(m.max_end,'%d %M')),'')) as event, @user_id
					FROM
						(
							SELECT
							start_date as max_start,
							end_date as max_end,
							user_id,
							reason_id
							FROM days_off d
							WHERE start_date=(SELECT MAX(start_date) FROM days_off WHERE user_id=d.user_id)
							ORDER BY user_id
						) as m,
						users as u
						WHERE
					    u.is_deleted IS NULL AND
						u.user_id=m.user_id AND
						(((TO_DAYS(m.max_start)-TO_DAYS(NOW()))<=5 AND
						(TO_DAYS(m.max_start)-TO_DAYS(NOW()))>=-1)
						OR
						(TO_DAYS(m.max_start)-TO_DAYS(NOW())=5 AND DAYOFWEEK(NOW())=4) OR
						(TO_DAYS(m.max_start)-TO_DAYS(NOW())IN(4,5) AND DAYOFWEEK(NOW())=5) OR
						(TO_DAYS(m.max_start)-TO_DAYS(NOW())IN(3,4,5) AND DAYOFWEEK(NOW())=6) OR
						(TO_DAYS(m.max_start)-TO_DAYS(NOW())IN(4,5,6) AND DAYOFWEEK(NOW())=7) OR
						(TO_DAYS(m.max_start)-TO_DAYS(NOW())IN(4,5,6,7) AND DAYOFWEEK(NOW())=8));"
					;//."DELETE FROM reminders WHERE event is NULL;";
        /**/
		foreach (explode(';',$f_sql) as $sql){

			$db->query($sql);
		}

 		$db->query('SELECT reminder_id, event FROM reminders WHERE user_id = @user_id AND is_shown=1 ORDER BY reminder_id DESC');

 		while($db->next_record($sql)){
 			$x = $db->Record["event"];
 			$reminders .= '<tr><td nowrap>'.$x.'</td><td valign=bottom><a href="index.php?hide_id='.$db->Record["reminder_id"].'"><font size=-3	 color=blue>Hide</font></a></td></tr>';
 		}

 		if ($reminders){
 			$reminders='<table style="border: 1px solid #dfcd10; background-color: #FCFFD5;">'.$reminders.'
 			<tr><td align=left colspan=2><a href="index.php?hide_all=hide_all"><font color=blue size=-2> Hide All</font></a></td></tr>
 			</table><br>';
 		}
 	}
 	
	show_microtime("reminders");
 	
 	
	$T->set_var("user_id", $session_user_id);
	$T->set_var("reminders", $reminders);
	$T->set_var("birth_days", $bds);
	
	$T->pparse("page");
	
function getmicrotime(){ 
    	list($usec, $sec) = explode(" ",microtime()); 
    	return ((float)$usec + (float)$sec); 
    } 

function show_microtime($event) {
	global $initial;  
    
    $from_start = ($initial ? (getmicrotime() - $initial) : 0);
	echo (number_format($from_start,4).": ".$event."\n<BR>");
}
	
?>