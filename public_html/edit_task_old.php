<?php

	ini_set('memory_limit', '36M');
	
include ("./includes/common.php");
include_once ("./includes/viart_support.php");

if (GetSessionParam("privilege_id") == 9)
{
	$sql = "SELECT * FROM tasks WHERE task_id =".$task_id." AND created_person_id = ".GetSessionParam("UserID");
	$db->query($sql);
	if (!$db->next_record())
	{
		header("Location: index.php");
		exit;
	}
}

$task_id				= GetParam("task_id");
$client_id				= GetParam("client_id");
$client_name			= "";
$client_time			= 0;
$action					= GetParam("action");
$message				= GetParam("message");
$task_title				= GetParam("task_title");
$task_domain		    = GetParam("task_domain");
$task_cost				= GetParam("task_cost");
$hourly_charge			= GetParam("hourly_charge");
$project_id				= GetParam("project_id");
$task_desc				= GetParam("task_desc");
$sub_project_id			= GetParam("sub_project_id");
$task_status_id			= GetParam("task_status_id");
$responsible_user_id	= GetParam("responsible_user_id");
$task_type_id			= GetParam("task_type_id");
$task_estimated_hours	= GetParam("task_estimated_hours");
$upd_uhours				= GetParam("upd_uhours");
$FormAction				= GetParam("FormAction");
$FormName				= GetParam("FormName");
$PK_task_id				= GetParam("PK_task_id");
$task_id				= GetParam("task_id");
$trn_task_status_id		= GetParam("trn_task_status_id");
$trn_project_id			= GetParam("trn_project_id");
$trn_priority_id		= GetParam("trn_priority_id");
$trn_task_type_id		= GetParam("trn_task_type_id");
$return_page			= GetParam("return_page");

$status_where = " WHERE status_id!=1 ";

/**/

if (!$task_id || !is_numeric($task_id))
{
	header("Location: index.php");
	exit;
}

if ($action == "start" && $task_id && is_numeric($task_id)) {
	start_task($task_id, GetParam("completion"), "edit_task.php?task_id=".$task_id);
}

if ($client_id !== "") {
	if ($client_id>0) {
		$sql = "SELECT  c.client_name,
						c.client_company,
						IF(c.client_type = 1,c.client_email,c.google_accounts_emails) AS client_email,
							sum(t.actual_hours) AS totaltime
					FROM clients c
						left join tasks as t on t.client_id=c.client_id
					WHERE c.client_id=".ToSQL($client_id,"integer")."
					GROUP BY t.task_id";
					
		$db->query($sql);
		$db->next_record();
		$client_time	= floatval($db->Record['totaltime']);
		$client_name	= $db->Record['client_name'];
		$client_company	= $db->Record['client_company'];
		$client_email	= $db->Record['client_email'];
		if ($client_email) {
			$temp = split("[;]",$client_email);
			if (strlen($temp[0])) {
				$client_email	= ($temp[0]?"<br>&nbsp;".$temp[0]:"");
				unset($temp);
			}
		}
		//$client_company	= ($db->Record['client_company']?"<br>&nbsp;".$db->Record['client_company']:"");
		$client_name .= $client_company . $client_email;
	} else {
		$client_time = 0;
		$client_name = "Nobody";
	}
} else {
	if ($task_id !== "") {
		$sql = 'SELECT t.client_id,
					cl.client_name,
					cl.client_company,
					IF(cl.client_type = 1,cl.client_email,cl.google_accounts_emails) AS client_email
					FROM tasks as t
						 left join clients as cl on cl.client_id=t.client_id
					WHERE t.task_id='.ToSQL($task_id,"integer");
		$db->query($sql);
		$db->next_record();
		$client_id		= intval($db->Record['client_id']);
		$client_name	= $db->Record['client_name'];
		$client_company	= $db->Record['client_company'];
		$client_email	= $db->Record['client_email'];
		if ($client_email) {
			$temp = split("[;]",$client_email);
			if (strlen($temp[0])) {
				$client_email	= ($temp[0]?"<br>&nbsp;".$temp[0]:"");
				unset($temp);
			}
		}
		//$client_company	= ($db->Record['client_company']?"<br>&nbsp;".$db->Record['client_company']:"");
		$client_name .= $client_company . $client_email;

		$sql = "SELECT SUM(actual_hours) as totaltime FROM tasks WHERE client_id=".ToSQL($client_id,"integer");
		$db->query($sql);
		$db->next_record();
		$client_time	= $db->Record['totaltime'];
	}
	else {			$client_id		= -1;
	$client_name	= "";
	$client_time	= 0;
	}
}

//subprojects;
$selected_project = (GetParam("sub_project_id")>0) ? GetParam("sub_project_id") : GetParam("project_id");

$cur_message_colors=array();

$session_now = session_id();

CheckSecurity(1);

$temp_path  = "temp_attachments/";
$path 		= "attachments/message/";
$task_path  = "attachments/task/";
header("Cache-Control: private");
header("Age: 699");

$sFileName = "edit_task.php";
$sTemplateFileName = "edit_task.html";
$T= new iTemplate($sAppPath,array("main"=>$sTemplateFileName));
$T->set_var("FileName", $sFileName);

if (strlen(GetParam("clientkeyword"))>0)
	$T->set_var("clientkeyword", trim(GetParam("clientkeyword")));
	else $T->set_var("clientkeyword", "");

$sFormErr = "";

$sAction = GetParam("FormAction");
$sForm   = GetParam("FormName");
$iDay 	 = GetParam("day");
$iMonth  = GetParam("month");
$iYear 	 = GetParam("year");
if (strlen($iYear) < 2) {
	$iYear = "0" . $iYear;
}

$cur_date = getdate(time());

// hash
$hash = GetParam("hash") ? GetParam("hash") : substr(md5(time()),0,8);

$T->set_var("this_client_id", $client_id);
$T->set_var("this_client_name", $client_name);
$T->set_var("this_client_company", $client_company);
$T->set_var("this_client_hours", to_hours($client_time));

update_priorities($task_id);

if (!isset($_POST["uhours_estimate"])) {
	$message_estimated_hours = false;	
} else {
	$message_estimated_hours = GetParam("uhours_estimate");
}
if (!isset($_POST["message_year"])) {
	$message_deadline = false;
} else {
	$message_year = GetParam("message_year");
	if ($message_year<2000) {
		$message_year+=2000;
	}
	$message_deadline = array("YEAR"=>GetParam("message_year"), "MONTH"=>GetParam("message_month"), "DAYOFMONTH"=>GetParam("message_day"));
	
}

if (!isset($_POST["task_cost"])) {
	$message_price = false;
} else {
	$message_price = GetParam("task_cost");
}

switch ($sForm)
{
	case "Form":
		FormAction($sAction);	break;
	case "MessageForm":
		add_task_message($task_id, $message, GetParam("trn_user_id"), $responsible_user_id, 
					GetParam("task_status_id"), GetParam("uhours"), $task_completion,
					stripslashes(GetParam("rp")), $hash, GetParam('importance_value'), GetParam('bug_status'), $message_estimated_hours, $message_deadline, $message_price);
	break;
}
Form_Show();
MessagesShow();

$sql = 'SELECT bug_id FROM bugs WHERE task_id = '.$task_id.' AND is_resolved=0';
$db->query($sql);
if ($db->next_record())
{          //exist resolve bug tab
	$T->set_var('report_display', 'none');
	$T->set_var('resolve_display', '');
	$T->set_var('report_d_id', 'xxx');
	$T->set_var('resolve_d_id', 'd3');
	$T->set_var('report_b_id', 'xxx');
	$T->set_var('resolve_b_id', 'b3');
	$T->set_var('bug_status', '12');
}
else
{
	$T->set_var('report_display', '');
	$T->set_var('resolve_display', 'none');
	$T->set_var('report_d_id', 'd3');
	$T->set_var('resolve_d_id', 'xxx');
	$T->set_var('report_b_id', 'b3');
	$T->set_var('resolve_b_id', 'xxx');
	$T->set_var('bug_status', '10');
}


$T->pparse("main", false);

//********************************************************************************

function FormAction($sAction) {
	global $db, $T, $sAction, $sForm, $sFormErr, $path, $temp_path, $session_now;
	global $iDay, $iMonth, $iYear, $task_id, $selected_project, $hash, $client_id;
	global $project_is_domain_required;

	$sParams = "";
	$sActionFileName = (@$_SERVER["HTTP_REFERER"] ? @$_SERVER["HTTP_REFERER"] : "index.php");
	$sActionFileName = stripslashes(GetParam("rp"));

	$bErr = false;

	if($sAction == "cancel"){
		header("Location: " . $sActionFileName);
	}

	$sql  = " SELECT project_title, is_domain_required ";
	$sql .= " FROM projects WHERE project_id = " . $selected_project;
	
	$db->query($sql);
	if ($db->next_record()) {
		$project_title              = $db->f("project_title");
		$project_is_domain_required = $db->f("is_domain_required");
	}
	
	if($sAction == "update" || $sAction == "delete") {
		$pPKtask_id = GetParam("PK_task_id");
	}

	if($sAction == "update") {
		$sFormErr = FormCheckFields();
		if(strlen($sFormErr) > 0) return;
	}
	$planed_date = to_mysql_date($iYear, $iMonth, $iDay);
			
	switch(strtolower($sAction)):

	case "update":
		//here be a function
		$priority_id_new = intval(GetParam("priority_id"));
		$priority_id_old = 0;
		$responsible_user_id = 0;
		$new_user_id=GetParam("responsible_user_id");
		$status_old=0;
		$status_new=intval(GetParam("task_status_id"));

		$sql = "SELECT priority_id, responsible_user_id, started_time, actual_hours, ((UNIX_TIMESTAMP(NOW())-UNIX_TIMESTAMP(started_time))/3600) as spent_hours, task_status_id FROM tasks WHERE task_id = " . $task_id;
		$db->query($sql);

		if ($db->next_record()) {
			$priority_id_old = $db->Record["priority_id"];
			$responsible_user_id = $db->Record["responsible_user_id"];
			$status_old = $db->Record["task_status_id"];
			$started_time = $db->Record["started_time"];
			$actual_hours = $db->Record["actual_hours"];
			$spent_hours = $db->Record["spent_hours"];
		}
		
		CountTimeProjects($task_id);	

		if ($status_old==1 && $status_new!=1) {			
			stop_task($task_id, false, "");
		} elseif ($status_old != 1 && $status_new == 1) {
			start_task($task_id, false, "");
		}

		//	check if user has active tasks;
		$sql="SELECT task_id FROM tasks WHERE responsible_user_id= $responsible_user_id AND is_wish = 0 AND task_status_id=1 AND is_closed = 0 AND task_id!=$task_id";
		$db->query($sql);
		if ($db->num_rows()) { $task_status_id=8;} 
		else { $task_status_id = GetParam("task_status_id");}

		update_task($pPKtask_id, array(
			"project_id"          => $selected_project,
			"client_id"           => $client_id, 
			"task_title"          => GetParam("task_title"),
			"task_domain_url"     => GetParam("task_domain"),
			"planed_date"         => $planed_date,
			"task_status_id"      => $task_status_id,
			"priority_id"         => $priority_id_new,
			"task_type_id"        => GetParam("task_type_id"),
			//"is_closed"           => 0,
			"responsible_user_id" => $new_user_id,
			"task_cost"           => GetParam("task_cost"),
			"hourly_charge"       => GetParam("hourly_charge")
		));
		

		update_estimate($task_id, GetParam("upd_uhours"));
		
		if ($priority_id_new!=$priority_id_old) {
			$sql = " UPDATE users SET priority_set_by = " . ToSQL(GetSessionParam("UserID"),"integer",false,false);
			$sql.= " WHERE user_id=".ToSQL($responsible_user_id, "integer", false);
			$db->query($sql);
		}
		
		$task_status = "";
		
		
		
		$sql = "SELECT lts.status_caption, t.is_closed FROM tasks t, lookup_tasks_statuses lts WHERE lts.status_id = t.task_status_id ";
		$sql .= " AND t.task_id = " . ToSQL($task_id, "Number");
		$db->query($sql);
		if ($db->next_record()) {
			$task_status = $db->f("status_caption");
			$is_closed = $db->f("is_closed");
		}
		
		$sql  = " SELECT CONCAT(first_name,' ',last_name)  AS responsible_user_name ";
		$sql .= " FROM users WHERE user_id=" . ToSQL($responsible_user_id, "Number");
		$db->query($sql);
		if ($db->next_record()) {
			$responsible_user_name = $db->f(0);
		}

		$tags = array ( "project_title" => $project_title,
			"task_title"    => GetParam("task_title"),
			"task_id"       => $task_id,
			"user_name" 	=> GetSessionParam("UserName"),
			"task_status"	=> $task_status,
			"responsible_user_id"   => $responsible_user_id,
			"responsible_user_name" => $responsible_user_name
		);
		if (!$is_closed) send_enotification(MSG_TASK_UPDATED, $tags);

		break;

	case "delete":
		delete_task($pPKtask_id);
		break;
	endswitch;
}

function Form_Show() {

	global $db, $T, $sAction, $sForm, $sFormErr, $task_path, $temp_path, $cur_date, $task_id, $selected_project;
	global $hash, $client_id, $permission_groups;

	$db1 = new DB_Sql();
	$db1->Database = DATABASE_NAME;
	$db1->User     = DATABASE_USER;
	$db1->Password = DATABASE_PASSWORD;
	$db1->Host     = DATABASE_HOST;
	
	$sql = "SELECT COUNT(task_id) AS max_priority,responsible_user_id FROM tasks WHERE is_closed=0 AND is_wish=0 GROUP BY responsible_user_id";

	$db->query($sql);

	$users_priorities = "";

	while($db->next_record())
	{
		$max_priority = $db->Record["max_priority"]+1;
		$r_user_id    = $db->Record["responsible_user_id"];
		if (!$max_priority) $max_priority = 1;
		$users_priorities .= "dicUsers[$r_user_id] = $max_priority;\n";
	}

	$T->set_var("rp", stripslashes(GetParam("rp")));
	$T->set_var("return_page", addslashes(@$_SERVER["HTTP_REFERER"]));

	$T->set_var("hash",strval($hash));
	$T->set_var("dicUsers", $users_priorities);

	//end for "set priorities" list

	$taskArr = "";
	$idArr = "";
	$statArr = "";
	$prArr = "";
	$today = date('Y-m-d');
	$statuses_classes = array("","InProgress","OnHold","Rejected","Done","Question","Answer","New","Waiting","Reassigned","Bug");

	$sql  = " SELECT GROUP_CONCAT(task_title ORDER BY priority_id  SEPARATOR ';' ) AS tt , ";
	$sql .= " GROUP_CONCAT(task_id ORDER BY priority_id  SEPARATOR ';') AS tasks_id, ";
	$sql .= " responsible_user_id, GROUP_CONCAT(task_status_id ORDER BY priority_id  SEPARATOR ';' ) AS statuses, ";
	$sql .= " GROUP_CONCAT(planed_date ORDER BY priority_id  SEPARATOR ';' ) AS planed, ";
	$sql .= " GROUP_CONCAT(project_id SEPARATOR ';' ) AS projects_ids, ";
	$sql .= " GROUP_CONCAT(task_type_id ORDER BY priority_id  SEPARATOR ';' ) AS types ";
	//	$sql .= " IF (TO_DAYS(planed_date) < TO_DAYS(now()) AND task_status_id!=4 AND task_type_id!=3,1,0 ) AS ifdeadlined,";
	//	$sql .= " IF (TO_DAYS(planed_date) = TO_DAYS(now()) AND task_status_id!=4 AND task_type_id!=3,1,0 ) AS iftoday";
	$sql .= " FROM tasks WHERE is_closed='0'AND is_wish='0' GROUP BY responsible_user_id";
	$db->query($sql);
	if ($db->next_record())
	{
		$tasks = "";
		$statuses = "";
		$types = "";
		do
		{
			$statuses = "";
			$r_id = $db->f("responsible_user_id");
			$tasks = $db->f("tt");
			$tasks_id = $db->f("tasks_id");
			$tasks = addslashes($tasks);
			$projects_ids = $db->f("projects_ids");
			$projects_arr = explode(";", $projects_ids);

			
			$projects = "";
			if ($projects_arr) {
				
				foreach($projects_arr as $project_key=>$project_value) {
					$sql = " SELECT project_title from projects WHERE project_id=".ToSQL($project_value,"Number");
					$db1->query($sql);
					$db1->next_record();
					$projects .= $db1->f("project_title") . ";";
				}
			}
				
			/*if ($projects_arr) {
				//$projects_arr = array_unique($projects_arr);
				$in = "";
				
	
				foreach($projects_arr as $project_key=>$project_value) {
					
					if ($project_value) {
						if ($in) $in .= ",";
						$in .= $project_value;
					}
				}
				$sql = " SELECT project_title from projects where project_id IN ($in)";
				$db1->query($sql);
				while ($db1->next_record()) {
					$projects .= $db1->f("project_title") . ";";
				}
			}*/
			
			$tasks = addslashes($tasks);
			$status_array = explode(";", $db->f("statuses"));
			$types_array = explode(";", $db->f("types"));
			$planed_array = explode(";", $db->f("planed"));

			for ($i=0; $i<sizeof($status_array); $i++)
			{
				$status = $status_array[$i];
				$type = @$types_array[$i];
				$planed_date = @$planed_array[$i];

				if ($planed_date < $today && $status != 1 && $type!=1 && $type!=3 && $type!=0)
				$statuses .= "Deadline;";
				elseif ($planed_date == $today && $status != 1 &&  $status != 4 && $type!=1 && $type!=3 && $type!=0)
				$statuses .= "Today;";
				else
				$statuses .= @$statuses_classes[$status].";";
			}
			$statArr .= "statArr[$r_id] = '".tojavascript($statuses)."';\n";
			$taskArr .= "taskArr[$r_id] = '".tojavascript($tasks)."';\n";
			$idArr .= "idArr[$r_id] = '".tojavascript($tasks_id)."';\n";
			$prArr .= "prArr[$r_id] = '".tojavascript($projects)."';\n";
		}
		while ($db->next_record());

		$T->set_var("statArr", $statArr);
		$T->set_var("taskArr", $taskArr);
		$T->set_var("idArr", $idArr);
		$T->set_var("prArr", $prArr);
		//		$T->parse("task_priority_block");
	}
	else
	{
		//	 	$T->set_var("task_priority_block", "");
	}
	//end for "set priorities" list

	$bPK					= true;
	$fldtask_id				= "";
	$fldproject_id			= "";
	$fldtask_title			= "";
	$fldtask_domain			= "";
	$fldtask_client			= "";
	$fldtask_cost			= "";
	$fldhourly_charge		= "";
	$fldtask_desc			= "";
	$fldplaned_date			= "";
	$fldtask_status_id		= "";
	$fldresponsible_user_id	= "";
	$fldpriority_id			= "";
	$fldtask_type_id		= "";
	$creator_id				= "";
	$fldcreator_user_id		= "";
	$fldproject_id			= stripslashes(GetParam("project_id"));

	$fldplaned_date=array();
	//if (!isset($cur_month) || !$cur_month)
	//-- if cur_month is not defined, we set it automatically to current
	$fldplaned_date["MONTH"]	= $cur_date["mon"];
	$fldplaned_date["YEAR"]		= substr($cur_date["year"],2,2);
	$fldplaned_date["DAY"]		= "";
	//-- transit parameters (in order to return to index.php with that parameters)
	if($sAction) //-- some error
	{
		$T->set_var(array(
		"trn_task_status_id"	=> GetParam("trn_task_status_id"),
		"trn_project_id"		=> GetParam("trn_project_id"),
		"trn_priority_id"		=> GetParam("trn_priority_id"),
		"trn_task_type_id"		=> GetParam("trn_task_type_id")
		));
	} else {
		$T->set_var(array(
		"trn_task_status_id"	=> GetParam("task_status_id"),
		"trn_project_id"		=> GetParam("project_id"),
		"trn_priority_id"		=> GetParam("priority_id"),
		"trn_task_type_id"		=> GetParam("task_type_id")
		));
	}
	$T->set_var("trn_user_id",GetSessionParam("UserID"));

	if ($sAction && $sForm == "Form")  //-- some error occur (insert or update)
	{
		$fldtask_id		= stripslashes(GetParam("task_id"));
		$fldtask_title	= stripslashes(GetParam("task_title"));
		$fldtask_domain	= stripslashes(GetParam("task_domain"));
		$fldtask_client	= stripslashes(GetParam("task_client"));
		$fldtask_cost	= stripslashes(GetParam("task_cost"));
		$fldhourly_charge = stripslashes(GetParam("hourly_charge"));
		$fldtask_desc	= stripslashes(GetParam("task_desc"));
		$fldcompletion	= stripslashes(GetParam("completion"));
		$fldplaned_date = stripslashes(GetParam("planed_date"));
		$fldplaned_date["MONTH"] = GetParam("month") ? stripslashes(GetParam("month")) : $cur_date["mon"];
		$fldplaned_date["YEAR"]  = GetParam("year")  ? stripslashes(GetParam("year"))  : substr($cur_date["year"],2,2);
		$fldplaned_date["DAY"]   = GetParam("day")   ? stripslashes(GetParam("day"))   : "";
		$fldtask_status_id		= stripslashes(GetParam("task_status_id"));
		$fldresponsible_user_id	= stripslashes(GetParam("responsible_user_id"));
		$fldpriority_id			= stripslashes(GetParam("priority_id"));
		$fldtask_type_id		= stripslashes(GetParam("task_type_id"));
		$ptask_id = GetParam("PK_task_id");
	} else {
		$ptask_id = GetParam("task_id");
	}

	if (strlen($ptask_id)) {
		$T->set_var("PK_task_id", $ptask_id);
	} else {
		$bPK = false;	
	}

	$sSQL = "SELECT * FROM tasks WHERE task_id=".ToSQL($task_id, "integer");

	$db->query($sSQL);
	$db->next_record();
	$mes_task_status_id = $db->Record["task_status_id"];
	$mes_user_id        = $db->Record["responsible_user_id"];
	$mes_task_type_id   = $db->Record["task_type_id"];

		
		//		    if ($mes_task_status_id == 1) $status_where = ""; else $status_where = " WHERE status_id!=1 ";
		$status_where = " WHERE status_id!=1 ";

		$T->set_var("task_status_select",$mes_task_status_id);

		//statuses & values for dynamic options changing
		$allvalues="";
		$allstatuses="";
		$quotationvalues = "";
		if ($mes_task_type_id == 4) {
			$status_where_type = "  ";
			$T->parse("quotations_tab", false);
			
			$T->parse("make_child_task_button", false);
			$T->set_var("duplicate_task_button", "");
		} else {
			$T->set_var("quotations_tab", "");
			$status_where_type = "AND usual=1 ";
			
			$T->set_var("make_child_task_button", "");
			$T->parse("duplicate_task_button", false);
		}
		
		$sql1 = "SELECT status_id FROM lookup_tasks_statuses $status_where $status_where_type ORDER BY sort_order";
		
		$db1->query($sql1);
		if ($db1->num_rows())
		{
			if ($db1->next_record())
			{
				$allvalues.=(int)$db1->f(0);
				while($db1->next_record()) $allvalues.=",".(int)$db1->f(0);
			}
		}
		
		$sqlm = "SELECT status_id FROM lookup_tasks_statuses ".$status_where." AND quotation=1 ORDER BY sort_order ";
		$db1->query($sqlm);
		while($db1->next_record()) {
			if (strlen($quotationvalues)) {
				$quotationvalues .= ",";				
			}
			$quotationvalues .= intval($db1->f("status_id"));			
		}

		//answer or question;
		if ($mes_task_status_id!=5) $T->set_var("question_or_answer","5"); else $T->set_var("question_or_answer","6");

		$sql1="SELECT status_desc FROM lookup_tasks_statuses WHERE 1=1 ".$status_where_type." ORDER BY status_id";
		$db1->query($sql1);
		if ($db1->num_rows())
		{
			if ($db1->next_record())
			{
				$allstatuses.="\"".$db1->f(0)."\"";
				while($db1->next_record()) $allstatuses.=",\"".$db1->f(0)."\"";
			}
		}
		
		$T->set_var("allvalues",$allvalues);
		$T->set_var("quotationvalues",$quotationvalues);
		$T->set_var("allstatuses",$allstatuses);

		//-end
		$T->set_var("statuses_list",  get_options("lookup_tasks_statuses $status_where $status_where_type ORDER BY sort_order","status_id","status_desc",9,"status"));
	
	if($bPK) //-- update but with error or just update
	{
		
		$fld_is_planned = 0;
		
		if ($sAction == "")
		{
			$fldtask_id = GetValue($db, "task_id");

			$fldtask_title			= GetValue($db, "task_title");
			$fldtask_domain			= GetValue($db, "task_domain_url");
			$fldtask_cost			= GetValue($db, "task_cost");
			$fldhourly_charge		= GetValue($db, "hourly_charge");
			$fldtask_desc			= GetValue($db, "task_desc");
			$fldtask_status_id		= GetValue($db, "task_status_id");
			$fldresponsible_user_id	= GetValue($db, "responsible_user_id");
			$fldcreator_user_id		= GetValue($db, "created_person_id");
			$fldpriority_id			= GetValue($db, "priority_id");
			$fldtask_type_id		= GetValue($db, "task_type_id");
			$fldcompletion			= GetValue($db, "completion");
			$fldplaned_date			= date_to_array(GetValue($db, "planed_date"));
			$fld_is_planned			= GetValue($db, "is_planned");

			//if day or year equals 00 set empty string
			foreach ($fldplaned_date AS $key=>$de) {$fldplaned_date[$key]=($de>0?$de:"");}

			//$cur_month=$fldplaned_date["month"];
			$creator_id = GetValue($db, "created_person_id");
		}

		//we need to check if this task has already been started
		if ($fldtask_status_id == 1) {
			 $sfldtask_title = addslashes($fldtask_title);
			 $btn = "<input type='button' class='btnStop' value='Stop Task' onClick=\"javascript:startTask($fldtask_id,'Stop','$sfldtask_title',true,$fldcompletion,'$sfldtask_title',0,'index.php')\">";
		    $T->set_var("start_button", $btn);		
		} else {
		    if ($fldresponsible_user_id == GetSessionParam("UserID") && !(is_manager($fldresponsible_user_id) && $fld_is_planned==0)) {
			$T->parse("start_button",false);
		    } elseif ($fldresponsible_user_id<>GetSessionParam("UserID") && GetSessionParam("privilege_id") >= PRIV_ARCHITECT) {
			$T->parse("start_button",false);
		    } else {
			$T->set_var("start_button","");
		    }
		}

		if (has_permission("PERM_CLOSE_TASKS") || $fldcreator_user_id==GetSessionParam("UserID")) {
			$T->parse("close_button");
		} else {
			$T->set_var("close_button", "");
		}

		if (GetSessionParam("privilege_id") < PRIV_ARCHITECT) {
			$T->set_var("FormEdit","");
		} else {
			$T->parse("FormEdit", false);
		}

		if (GetSessionParam("UserName") == "Artem Birzul" && $fldtask_id == 11699) {
			$T->parse("extra_button", false);
		} else { $T->set_var("extra_button","");}

		$T->parse("FormCancel", false);
		
		$T->set_var("responsibleUser", intval($db->f("responsible_user_id")));

	}

	//determine parent and sub-project
	$fldabs_project_id = $selected_project ? $selected_project : GetValue($db, "project_id");

	$query  = " SELECT p.project_id AS parent, subp.project_id AS child ";
 	$query .= ", IF(subp.project_id=".ToSQL($fldabs_project_id, "integer").", subp.project_url, p.project_url) as pproject_url ";
 	$query .= ", CONCAT('http://',REPLACE(cs.web_address,'http://','')) as web_address ";
 	$query .= " FROM ((projects p";
 	$query .= " LEFT JOIN projects AS subp ON (p.project_id = subp.parent_project_id)) ";
 	$query .= " LEFT JOIN clients_sites AS cs ON (IF(subp.client_id IS NULL,p.client_id,subp.client_id) = cs.client_id)) ";
 	$query .= " WHERE ((p.parent_project_id IS NULL AND p.project_id=".ToSQL($fldabs_project_id, "integer")." ) ";
 	$query .= " OR subp.project_id = ".ToSQL($fldabs_project_id,"integer").") ";

	$db1->query($query);

	$parent=$child=0;
	if ($db1->next_record())
	{
		$fldproject_id = $fldabs_project_id;
		$fldproject_id = $db1->f("parent");
		$parent  = $db1->f("parent");
		$child = $db1->f("child");
		if ($db1->f("child")==$fldabs_project_id) {
			$fldsubproject_id = $db1->f("child");		
		} else {		
			$fldsubproject_id = false;		
		}
		$fldproject_url = $db1->f("pproject_url");
		if (!$fldproject_id)
		{
			$fldproject_id = $fldsubproject_id;
			$fldsubproject_id = false;				
		}		
		if (!strlen($fldproject_url)) {			
 			$fldproject_url = $db1->f("pproject_url");
 			if (!strlen($fldproject_url)) {
 				$fldproject_url = $db1->f("web_address");
 			}
 		}
	}

	$T->set_var("task_id", ToHTML($fldtask_id));
	$T->set_var("LBproject_id", "");
	$T->set_var("ID", "");
	$T->set_var("Value", "");

	$T->parse("LBproject_id", true);
	
	$T->set_var("project_id", $fldproject_id);

	$sql = "SELECT project_id, project_title FROM projects WHERE parent_project_id IS NULL AND (is_closed=0 OR project_id IN (".ToSQL($parent, "integer").")) ORDER BY 2";
	$db->query($sql);
	while($db->next_record())
	{
		$T->set_var("ID", $db->f(0));
		$T->set_var("Value", $db->f(1));

		if ($db->f(0) == $fldproject_id) {
			$T->set_var("Selected", "SELECTED" );
		} else {
			$T->set_var("Selected", "");
		}
		$T->parse("LBproject_id", true);
	}

	//subprojects
	$sql = " SELECT parent_project_id, project_id, project_title FROM projects ";
	$sql.= " WHERE parent_project_id IS NOT NULL AND (is_closed=0 OR project_id IN (".ToSQL($child, "integer")."))";
	$sql.= " ORDER BY parent_project_id, project_title ";
	$db->query($sql);
	$parent_id=0;
	if ($db->num_rows()) {
		$i = 0;
		while ($db->next_record())
		{
			$i++;
			$T->set_var("IDparent",$db->f(0));
			$T->set_var("IDchild",$db->f(1));
			if ($parent_id!=$db->f(0))
			{
				$i = 1;
				$T->parse("SubProjectParent",false);
			} else {
				$T->set_var("SubProjectParent","");
			}
			$T->set_var("I", $i);
			$T->set_var("subproject_title",addslashes($db->f(2)));
			$T->parse("SubProjectArray",true);
			$parent_id=$db->f(0);
		}
	} else {
		$T->set_var("SubProjectArray","");
	}
	//set subproject selection
	if (isset($fldsubproject_id) && $fldsubproject_id>0) {
		$T->set_var("sub_project_id_value", $fldsubproject_id);
		$T->parse("set_sub_project", false);
	} else {
		$T->set_var("sub_project_id_value", "0");
		$T->set_var("set_sub_project", "");
	}

	if (!strlen($fldcompletion)) {$fldcompletion="0";}

	$db->query("SELECT t1.task_id, t1.task_title, t1.completion FROM tasks t1 WHERE t1.task_status_id=1 AND t1.responsible_user_id=
						(SELECT t2.responsible_user_id FROM tasks t2 WHERE t2.task_id=".$task_id.")");
	$active_task_title = "";
	if ($db->num_rows()>0){
		$db->next_record();
		$active_task_title = $db->Record["task_title"];
		$completion_javascript = $db->Record["completion"];
	}
	if (!isset($completion_javascript)) {
		$completion_javascript = $fldcompletion;
	}
	
	// echo "fldtask_desc: ".htmlspecialchars(stripslashes($fldtask_desc))." <br>";
	$T->set_var(array(
	"task_title"			=> ToHTML(stripslashes($fldtask_title)),
	"task_domain"			=> $fldtask_domain,
	"task_client"			=> $fldtask_client,
	"task_cost"				=> ($fldtask_cost>0 ? $fldtask_cost : ""),
	"hourly_charge"			=> ($fldhourly_charge>0 ? $fldhourly_charge : "15"),
	"hourly_charge_checked"	=> ($fldhourly_charge>0 ? "checked" : ""),
	"task_desc"				=> ToHTML(stripslashes($fldtask_desc)),
	"completion"			=> $fldcompletion,	
	"completion_javascript"	=> $completion_javascript,
	"active_task_title"		=> str_replace(array("'","\""), array("\'",""), $active_task_title),
	"is_periodic"			=> ($fldtask_type_id==3?"true":"false"),
	"url_page"				=> $_SERVER["PHP_SELF"]
	));
	if (!isset($fldproject_url)) {
		$fldproject_url = "";
	}
	$T->set_var("project_url",$fldproject_url);

 	if (strlen($fldproject_url)) {
 		$T->parse("project_url_block",false);
 		//$T->set_var("task_domain_block", "");
 	} else {
 		$T->set_var("project_url_block","");
 	}
 	$T->parse("task_domain_block", false);
 	
	$T->set_var("task_title_slashed", str_replace(array("'","\""), array("\'",""), ToHTML($fldtask_title)));
	$T->set_var($fldplaned_date);
	$T->set_var("LBtask_status_id", "");

	$status_where_sql = " WHERE 1=1 ";
	if ($mes_task_status_id!=1) {
		$status_where_sql = " WHERE status_id!=1 ";
	}
	
	get_select_options("lookup_tasks_statuses", "status_id", "status_desc", $fldtask_status_id, $status_where_sql. $status_where_type, "sort_order", "ID", "Value", "LBtask_status_id", false);
    get_select_options("lookup_task_types", "type_id", "type_desc", $fldtask_type_id, "", "2", "ID", "Value", "LBtask_type_id", false);
    
	$T->set_var("resp_user_id",$fldresponsible_user_id);
	//active project
	$active_project_id = (isset($fldsubproject_id) && $fldsubproject_id>0) ? $fldsubproject_id : $fldproject_id;
	
	get_users_list($active_project_id, $task_id, "LBresponsible_user_id", "ID", "Value", $fldresponsible_user_id);
	get_users_list($active_project_id, $task_id, "short_list_user", "ID", "Value", $fldresponsible_user_id);
	
	$T->set_var("priority_id", $fldpriority_id);

	// Show completion %
	$db->query("SELECT completion, estimated_hours FROM tasks WHERE task_id=".ToSQL($task_id, "number"));
	if ($db->next_record()) {
		$completion = $db->Record["completion"];
		$estimated_hours_value = $db->f("estimated_hours");
	}
	$T->set_var("estimated_hours_value", floatval($estimated_hours_value));
	if (isset($completion) && $completion>0 && $completion<=100) {
		$T->set_var("task_completion", $completion);		
	} else {
		$T->set_var("task_completion","");		
	}

	// Show task creator
	$db->query("SELECT first_name, last_name FROM users WHERE user_id = " . ToSQL($creator_id, "integer"));
	if ($db->next_record()) {
		$T->set_var("creator_name", $db->f(0) . " " . $db->f(1));
		$T->parse("creator_block", false);
	} else {
		$T->set_var("creator_block", "");
	}
	
	//calculate notes
	$total_notes = 0;
	$sql = " SELECT note_id, allow_view, allow_edit, author_id ";
	$sql.= " FROM notes WHERE task_id=".ToSQL($task_id, "integer");	
	$db->query($sql);
	$notes = array();
	while($db->next_record()) {
		$notes[] = $db->Record;		
	}	
	foreach ($notes as $note) {
		if (is_allowed(GetSessionParam("UserID"), $note["author_id"], get_set_array($note["allow_view"], $permission_groups))) {
			$total_notes++;
		}
	}
	
	
	$T->set_var("total_notes", intval($total_notes));
	//end notes

	$db->query("SELECT estimated_hours FROM tasks WHERE task_id=".$task_id);
	if ($db->next_record() && $db->f("estimated_hours"))
	{
		$estimated_hours = $db->f("estimated_hours");
		$estimated_hours_title = "";
		if ($estimated_hours)
		{
			if ($estimated_hours>0 && $estimated_hours<1) {
				$estimated_hours_title = round($estimated_hours*60)." min";
			} elseif ($estimated_hours==1) {
				$estimated_hours_title = "1 hour";
			} elseif ($estimated_hours>1 && $estimated_hours<16) {
				if (fmod($estimated_hours,1)) {
					$estimated_hours_title = sprintf("%1.2f hours",$estimated_hours);
				} else {
					$estimated_hours_title = floor($estimated_hours)." hours";
				}
			} elseif ($estimated_hours>=16) {
				$estimated_hours_title.= ( fmod($estimated_hours,8) ? sprintf("%1.2f days",$estimated_hours/8) : floor($estimated_hours/8)." days");
			}
		}
		$T->set_var("estimated_hours", $estimated_hours_title);
	} else {
		$T->set_var("estimated_hours", "");
	}
	// Show time report for the task
	if (strlen($ptask_id)) {

		$sql = "select * from (SELECT CONCAT(u.first_name, ' ', u.last_name) AS user_name, SUM(tr.spent_hours) AS time_spent ";
		$sql .= " FROM users u, time_report tr ";
		$sql .= " WHERE u.user_id = tr.user_id AND tr.task_id = " . ToSQL($ptask_id, "integer");
		$sql .= " GROUP BY tr.user_id ";
		$sql .= " ORDER BY user_name ASC) x
			UNION ";
		$sql .= "SELECT CONCAT('Total spent time') AS user_name, SUM(tr.spent_hours) AS time_spent ";
		$sql .= " FROM time_report tr ";
		$sql .= " WHERE tr.task_id = " . ToSQL($ptask_id, "integer");

		$db->query($sql);
		if ($db->next_record()) {
			do {
				$T->set_var("tr_name", $db->f("user_name"));
				$time_spent = $db->f("time_spent");
				$T->set_var("tr_time", $time_spent>0 ? to_hours($time_spent) : "0:00");
				$T->parse("tr_row", true);

			} while ($db->next_record());

			$T->parse("time_report", false);
		} else {
			$T->set_var("time_report", "");
		}

		$sql = 'SELECT CONCAT(u.first_name, \' \', u.last_name) as name,
		    		CONCAT(COUNT(b.bug_id), \' bugs (\', SUM(b.importance_level),\' points) \')  as bugs FROM bugs b, users u WHERE b.task_id = '.$task_id.' AND u.user_id = b.user_id GROUP BY u.user_id ORDER BY name;';
		$db->query($sql);

		if ($db->next_record()) {
			do {
				$T->set_var("tr_name", $db->Record["name"]);
				$T->set_var("tr_bugs", $db->Record["bugs"]);
				$T->parse("tr_bug_row", true);
			} while ($db->next_record());
			$T->parse("bug_report", false);
		} else {
			$T->set_var("bug_report", "");
		}
	}
	
//show subtasks
	
		$sql = "SELECT st.task_id, st.task_title, st.actual_hours, t.actual_hours AS main_actual_hours FROM tasks t INNER JOIN tasks st ON (st.parent_task_id=t.task_id) ";
		$sql .= " WHERE t.task_type_id=4 AND t.task_id=". ToSQL($ptask_id, "integer");
		$sql .= " ORDER BY st.task_title ASC ";

		$db->query($sql);
		
		$total_time_spent = 0;
		if ($db->next_record()) {
			do {
				$T->set_var("subtask_id", $db->f("task_id"));
				$T->set_var("subtask_name", $db->f("task_title"));
				$time_spent = $db->f("actual_hours");
				
				$total_time_spent += $time_spent;
				$T->set_var("subtask_time", $time_spent>0 ? to_hours($time_spent) : "0:00");
				$T->parse("subtasks_row", true);

			} while ($db->next_record());

			$total_time_spent+=$db->f("main_actual_hours");
			$T->set_var("total_time_spent", $total_time_spent>0 ? to_hours($total_time_spent) : "0:00");
			$T->parse("subtasks", false);
		} else {
			$T->set_var("subtasks", "");
		}
	//end show subtasks	

	//show parent tasks
	
	$parse_parenntask = false;
	
	$sql = "SELECT s.task_type_id AS sub_task_type_id, s.project_id AS parent_task_project_id, p.task_type_id AS parent_task_type_id, ";
	$sql.= " p.task_id AS parent_task_id, p.task_title AS parent_task_title, ss.task_id AS sub_sub_task_id ";
	$sql.= " FROM tasks s LEFT JOIN tasks p ON (s.parent_task_id=p.task_id) LEFT JOIN tasks ss ON (ss.parent_task_id=s.task_id) ";
	$sql.= " WHERE s.task_id = ".ToSQL($ptask_id, "integer");
	$db->query($sql);
	
	if ($db->next_record()) {
		$parent_task_id = $db->f("parent_task_id");
		$T->set_var("parenttask_project_id", $db->f("parent_task_project_id"));
		$sub_task_type_id = $db->f("sub_task_type_id");
		$parent_task_type_id = $db->f("parent_task_type_id");
		$sub_sub_task_id = $db->f("sub_sub_task_id");
		
		if ($parent_task_id && $sub_task_type_id!=4 && $parent_task_type_id==4) {
			//parent task exists
			$T->set_var("parenttask_name", $db->f("parent_task_title"));
			$T->set_var("parenttask_id", $db->f("parent_task_id"));
			$T->parse("parenttask_exists", false);
			$T->set_var("parenttask_not_exist", "");
			
			$parse_parenntask = true;
			
		} elseif ($sub_task_type_id!=4 && !$sub_sub_task_id) {
			//parent task doesnt exist
			$T->parse("parenttask_not_exist", false);
			$T->set_var("parenttask_exists", "");
			$parse_parenntask = true;
		}
	}
	if ($parse_parenntask) {
		$T->parse("parenttask", false);
	} else {
		$T->set_var("parenttask", "");
	}
	// end parent tasks
	

	if ($sFormErr == "") {
		$T->set_var("FormError", "");
	} else {
		$T->set_var("sFormErr", $sFormErr);
		$T->parse("FormError", false);
	}

	$T->set_var("MONTH" , get_month_options($fldplaned_date["MONTH"]));
	$attach_arr = array();

	$sql="SELECT * FROM attachments WHERE identity_type='task' AND identity_id=".($task_id ? $task_id : 0);
	$db->query($sql);
	$attach_count = 0;

	while ($db->next_record())
	{

		$cur_file = $task_path.strval($task_id)."_".$db->f("file_name");
		if (file_exists($cur_file))
		{
			$T->set_var("att_file",$cur_file);
			$T->set_var("att_file_name",$db->f("file_name"));

			$attach_count += 1;

			switch ($db->f("file_type"))
			{
				case "image":	$T->set_var("image_name","image.gif");		break;
				case "document":$T->set_var("image_name","document.gif");	break;
				case "archive":	$T->set_var("image_name","archive.gif");	break;
				default:	$T->set_var("image_name","attach.gif");
			}
			$T->parse("task_attachments",true);
		}
	}

	if ($attach_count != 0) {
		$T->set_var("task_attachments_header", "Task attachments:");
	} else {
		$T->set_var("task_attachments_header", "");
		$T->set_var("task_attachments", "");
	}
	if (is_manager(GetSessionParam("UserID"))) {
		$T->parse("edit_project_link", false);
	} else {
		$T->set_var("edit_project_link", "");
	}
	

	$T->parse("FormForm", false);
}

function MessagesShow()
{
	global $db, $T, $sAction, $task_id, $cur_message_colors, $level_colors, $path;

	$i=0;
	if ($task_id)
	{
		$T->set_var("last_message","");
		$T->set_var("message_shortcut","");
		$message_ids = "";
		$sql="SELECT message_id FROM messages WHERE identity_type='task' AND identity_id=".ToSQL($task_id, "Number");
		$db->query($sql);

		while ($db->next_record()) {

			if ($message_ids) $message_ids .=",";
			$message_ids .= $db->f("message_id");
		}
		if ($message_ids) {
			$attach_arr = array();
			$sql="SELECT * FROM attachments WHERE identity_type='message' AND identity_id IN($message_ids)";
			$db->query($sql);
			while ($db->next_record()) {
				$attach_arr[$db->f("attachment_id")]["file_name"]=$db->f("file_name");
				$attach_arr[$db->f("attachment_id")]["message_id"]=$db->f("identity_id");
				$attach_arr[$db->f("attachment_id")]["file_type"]=$db->f("attachment_type");
			}
		}		
		
		
		$sql="SELECT s.*,messages.*, users.*, r_users.first_name AS r_first_name, r_users.last_name AS r_last_name, r_users.email AS r_email, ".
		"DATE_FORMAT(message_date,'%a %D %b %Y, %H:%i') as message_date ".
		"FROM messages ".
		"LEFT JOIN users ON messages.user_id=users.user_id ".
		"LEFT JOIN users AS r_users ON messages.responsible_user_id=r_users.user_id ".
		"LEFT JOIN lookup_tasks_statuses AS s ON messages.status_id=s.status_id ".
		"WHERE messages.identity_type='task' and messages.identity_id=".$task_id." ".
		"ORDER BY messages.message_date DESC LIMIT 100";

		$db->query($sql);
		if ($db->next_record())
		{
			$T->set_var("last_message","\r\n\r\n>".  stripslashes(ToHTML(str_replace("\r\n","\r\n>",$db->f("message")))));
			do
			{
				$T->set_var("attachments","");
				$T->set_var($db->Record);
				$message_id   = $db->Record["message_id"];
				$attach_count = 0;
				foreach ($attach_arr as $id => $data)
				{
					$cur_file = $path.strval($message_id)."_".$data["file_name"];					
					
					if (($data["message_id"] == $message_id) && file_exists($cur_file)) {
						$T->set_var("cur_file",  $cur_file);
						$T->set_var("file_name", $data["file_name"]);
						$attach_count += 1;

						switch ($data["file_type"]){
							case "image": 	 $T->set_var("image_name","image.gif");  break;
							case "document": $T->set_var("image_name","document.gif"); break;
							case "archive":	 $T->set_var("image_name","archive.gif");  break;
							default:	     $T->set_var("image_name","attach.gif");
						}
						$T->parse("attachments",true);
					}
				}
				if ($attach_count != 0) {
					$T->set_var("attachments_header", "Attachments:");
				} else {
					$T->set_var("attachments_header", "");
					$T->set_var("attachments", "");
				}
				$T->set_var("r_first_name", $db->Record["r_first_name"]);
				$T->set_var("r_last_name",  $db->Record["r_last_name"]);
				$T->set_var("message", process_message(stripslashes($db->f("message")),$db->f("message_id"),"message"));
				$T->set_var("message_number",$i);
				if ($db->Record["status_id"]) {
					$T->set_var("message_status",$db->Record["status_caption"]);
				} else {
					$T->set_var("message_status","Message");
				}				
				if ($i<17) {
					$T->parse("message_shortcut");
				}
				$T->parse("single_message");
				$i++;

			}
			while ($db->next_record());
		}
		else
		{
			$T->set_var("single_message","");
			$T->set_var("last_message", "\r\n\r\n>". preg_replace("/\r\n/","\r\n>", stripslashes($T->get_var("task_desc"))));
		}
		$T->parse("messages_block");
	}
	else
	{
		$T->set_var("messages_block","");
	}
	$T->set_var("total_messages",$i);
}

function FormCheckFields() {

	global $sAction;
	global $iDay;
	global $iMonth;
	global $iYear;
	global $cur_date;
	global $project_is_domain_required;

	$sRes = "";
	$sCmp = "";

	if(!strlen(GetParam("upd_hours")) || !(GetParam("upd_hours")>0)) $sEst = "The value in field <font color=\"red\"><b>Estimated Time</b></font> is required.<br>"; else $sEst = "";
	if(strlen(GetParam("upd_hours")) && !is_number(GetParam("upd_uhours"))) $sEst .= "The value in field <font color=\"red\"><b>Estimated Time</b></font> is incorrect.<br>";

	if(!strlen(GetParam("task_title")))    $sRes .= "The value in field <font color=\"red\"><b>Title</b></font> is required.<br>";
	if(!strlen(GetParam("task_type_id")))  $sRes .= "The value in field <font color=\"red\"><b>Type</b></font> is required.<br>";
	if(!strlen(GetParam("project_id")))    $sRes .= "The value in field <font color=\"red\"><b>Project</b></font> is required.<br>";
	if(GetParam("sub_project_id")=="0")    $sRes .= "The value in field <font color=\"red\"><b>Sub-Project</b></font> is required.<br>";
	if(!strlen(GetParam("responsible_user_id"))) $sRes .= "The value in field <font color=\"red\"><b>Responsible person</b></font> is required.<br>";

	if (!strlen($iDay) && !$sEst)			    	$sCmp .= "The value of Day in field <font color=\"red\"><b>Date to complete</b></font> is required.<br>";
	if (!strlen($iYear) && !$sEst)			    	$sCmp .= "The value of Year in field <font color=\"red\"><b>Date to complete</b></font> is required.<br>";
	if(($sEst && $sCmp) || (!$sEst && !$sCmp)) $sRes.=$sEst.$sCmp;

	if(!is_number(GetParam("task_id")))		    	$sRes .= "The value in field <font color=\"red\"><b>task_id</b></font> is incorrect.<br>";
	if(!is_number(GetParam("project_id")))		    	$sRes .= "The value in field <font color=\"red\"><b>Project</b></font> is incorrect.<br>";
	if(!is_number(GetParam("task_status_id")))	    	$sRes .= "The value in field <font color=\"red\"><b>Status</b></font> is incorrect.<br>";
	if(!is_number(GetParam("responsible_user_id")))	    	$sRes .= "The value in field <font color=\"red\"><b>Responsible person</b></font> is incorrect.<br>";
	if(!is_number(GetParam("priority_id")))		    	$sRes .= "The value in field <font color=\"red\"><b>Priority</b></font> is incorrect.<br>";
	if(!is_number(GetParam("task_type_id")))		$sRes .= "The value in field <font color=\"red\"><b>Type</b></font> is incorrect.<br>";

	if (!is_number($iYear))				$sRes .= "The value of Year in field <font color=\"red\"><b>Date to complete</b></font> is incorrect.<br>";
	elseif (intval($iYear) > substr($cur_date["year"], 2, 2) + 10)
	{
		$sRes .= "The value of Year in field <font color=\"red\"><b>Date to complete</b></font> is incorrect.<br>";
		$iYear = substr($cur_date["year"], 2, 2);
	}
	//-- Number of day in month
	$n_days = date("t", mktime(0, 0, 0, $iMonth, 1, $iYear));

	if (strlen($iDay))
	{
		if (!is_number($iDay))		   			$sRes .= "The value of Day in field <font color=\"red\"><b>Date to complete</b></font> is incorrect.<br>";
		elseif (intval($iDay) < 1 || intval($iDay) > $n_days)	$sRes .= "The value of Day in field <font color=\"red\"><b>Date to complete</b></font> is incorrect.<br>";
	}

	if (strlen($iDay) && is_number($iDay) && is_number($iYear) && mktime(0, 0, 0, $iMonth, $iDay, $iYear) < mktime(0, 0, 0, date("m"), date("d"), date("Y"))) {
		$sRes .= "The value in field <font color=\"red\"><b>Date to complete</b></font> is in the past.<br>";
	}
	
	if ($project_is_domain_required && !GetParam("task_domain")) 
		$sRes .= "The value in field <font color=\"red\"><b>Domain</b></font> is required.<br>";

	return $sRes;
}

function import_sayu_domains($reload = false) {
	global $db;
	
	$db_sayu = new DB_Sql();
	$db_sayu->Database = SAYU_DATABASE_NAME;
	$db_sayu->User     = SAYU_DATABASE_USER;
	$db_sayu->Password = SAYU_DATABASE_PASSWORD;
	$db_sayu->Host     = SAYU_DATABASE_HOST;
	
	$db_sayu2 = new DB_Sql();
	$db_sayu2->Database = SAYU_DATABASE_NAME;
	$db_sayu2->User     = SAYU_DATABASE_USER;
	$db_sayu2->Password = SAYU_DATABASE_PASSWORD;
	$db_sayu2->Host     = SAYU_DATABASE_HOST;
	
	if ($reload) {
		$sql = " TRUNCATE TABLE tasks_domains";
		$db->query($sql);
		$sql = " UPDATE seo_index_domains SET is_exported_to_monitor=0";
		$db_sayu->query($sql);
	}
		
	$sql  = " SELECT id, domain FROM seo_index_domains";
	$sql .= " WHERE is_exported_to_monitor = 0";
	$db_sayu->query($sql);
	while ($db_sayu->next_record()) {
		$id     = $db_sayu->f("id");
		$domain = $db_sayu->f("domain");
		
		$sql = " INSERT INTO tasks_domains (domain_url) VALUES (" . ToSQL($domain, "text") . ")";
		$db->query($sql);
		
		$sql = " UPDATE seo_index_domains SET is_exported_to_monitor=1 WHERE id=" . $id;
		$db_sayu2->query($sql);
	}
}

?>