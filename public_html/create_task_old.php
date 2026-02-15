<?php

include ("./includes/common.php");

$db1 = new DB_Sql();
$db1->Database = DATABASE_NAME;
$db1->User     = DATABASE_USER;
$db1->Password = DATABASE_PASSWORD;
$db1->Host     = DATABASE_HOST;

if (GetParam("client_id") !== '') $client_id = intval(GetParam('client_id'));
else
{
	if (GetParam('task_id') !== '')
	{
		$sql = 'SELECT client_id FROM tasks WHERE task_id = '.GetParam('task_id');
		$db->query($sql);
		$db->next_record();
		$client_id = intval($db->Record['client_id']);
	} else {
		$client_id = 0;		
	}
}

$cur_message_colors=array();

//project matching subprojects selection
$selected_project =  (GetParam("sub_project_id")>0) ? GetParam("sub_project_id") : GetParam("project_id");

CheckSecurity(1);

$session_now = session_id();
$temp_path  = "temp_attachments/";
$path 		= "attachments/task/";
header("Cache-Control: private");
header("Age: 699");

$sFileName = "create_task_old.php";
$sTemplateFileName = "create_task_old.html";

// hash
$hash = GetParam("hash") ? GetParam("hash") : substr(md5(time()),0,8);

$T= new iTemplate($sAppPath, array("main" => $sTemplateFileName));
$T->set_var("FileName", $sFileName);
$T->set_var("this_client_id", $client_id);

$T->set_var("type", GetParam("type"));
$T->set_var("parent_task_id", intval(GetParam("parent_task_id")));
$clients_array_string = '';
$sql = 'SELECT CONCAT(client_name, \'<br>\', client_email) as client_name,  client_id FROM clients';
$db->query($sql);
while($db->next_record())
{
	$clients_array_string .= 'clients_array['.$db->Record['client_id'].'] = \''.$db->Record['client_name'].'\';';
}
$T->set_var('clients_array', $clients_array_string);
$T->set_var("this_client_id", $client_id);
$sFormErr = "";

$sAction = GetParam("FormAction");
$sForm = GetParam("FormName");
$sEstim = GetParam("uhours");
$ehsql=($sEstim ? " estimated_hours=".(float)$sEstim : "");
$iDay 	= GetParam("day");
$iMonth = GetParam("month");
$iYear 	= GetParam("year");

if (strlen($iYear) < 2) $iYear = "0" . $iYear;
$cur_date = getdate(time());

if ($sForm=="Form") {
	FormAction($sAction);
}

$T->set_var("task_domain", GetParam("task_domain"));
$T->parse("task_domain_block", false);
	
Form_Show();
MessagesShow();
	

$T->parse("main", false);
echo $T->p("main");

function FormCheckFields($sWhere) {
  	global $sAction;
	global $iDay, $iMonth, $iYear, $cur_date, $selected_project;
	global $client_id, $project_is_domain_required;

	$sRes = "";
	$sCmp = "";

	if(!strlen(GetParam("uhours")) || !(GetParam("uhours")>0)) $sEst = "The value in field <font color=\"red\"><b>Estimated Time</b></font> is required.<br>"; else $sEst = "";
	if(strlen(GetParam("uhours")) && !is_number(GetParam("uhours"))) $sEst .= "The value in field <font color=\"red\"><b>Estimated Time</b></font> is incorrect.<br>";
  	if(!strlen(GetParam("task_title")))		$sRes .= "The value in field <font color=\"red\"><b>Title</b></font> is required.<br>";
   	if(!strlen(GetParam("task_type_id")))		$sRes .= "The value in field <font color=\"red\"><b>Type</b></font> is required.<br>";
  	if(!strlen(GetParam("project_id")))		$sRes .= "The value in field <font color=\"red\"><b>Project</b></font> is required.<br>";
  	if(!strlen(GetParam("responsible_user_id")) || !GetParam("responsible_user_id"))	$sRes .= "The value in field <font color=\"red\"><b>Responsible person</b></font> is required.<br>";
	if (!strlen($iDay))				$sCmp .= "The value of Day in field <font color=\"red\"><b>Date to complete</b></font> is required.<br>";
 	if (strlen($iYear)<2)				$sCmp .= "The value of Year in field <font color=\"red\"><b>Date to complete</b></font> is required.<br>";
 	if(!is_number(GetParam("task_id")))		$sRes .= "The value in field <font color=\"red\"><b>task_id</b></font> is incorrect.<br>";
  	if(!is_number(GetParam("project_id")))		$sRes .= "The value in field <font color=\"red\"><b>Project</b></font> is incorrect.<br>";
  	if(!is_number(GetParam("task_status_id")))	$sRes .= "The value in field <font color=\"red\"><b>Status</b></font> is incorrect.<br>";
  	if(!is_number(GetParam("responsible_user_id")))	$sRes .= "The value in field <font color=\"red\"><b>Responsible person</b></font> is incorrect.<br>";
  	if(!is_number(GetParam("priority_id")))		$sRes .= "The value in field <font color=\"red\"><b>Priority</b></font> is incorrect.<br>";
   	if(!is_number(GetParam("task_type_id")))	$sRes .= "The value in field <font color=\"red\"><b>Type</b></font> is incorrect.<br>";
	if (!is_number($iYear))			$sRes .= "The value of Year in field <font color=\"red\"><b>Date to complete</b></font> is incorrect.<br>";
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
	if (strlen($iDay) && is_number($iDay) && strlen($iYear) && is_number($iYear) && mktime(0, 0, 0, $iMonth, $iDay, $iYear) < mktime(0, 0, 0, date("m"), date("d"), date("Y"))) {
		$sRes .= "The value in field <font color=\"red\"><b>Date to complete</b></font> is in the past.<br>";
	}
	
	if ($project_is_domain_required && !GetParam("task_domain")) 
		$sRes .= "The value in field <font color=\"red\"><b>Domain</b></font> is required.<br>";

	return $sRes;
}

function FormAction($sAction)
{
	global $db, $T, $sAction, $ehsql, $sForm, $sFormErr, $sEstim, $path, $temp_path, $session_now;
	global $selected_project, $hash, $client_id;
	global $project_is_domain_required;
	
	$sParams = "";
	$sActionFileName = "index.php?";
	$sActionFileName.= "task_status_id=".GetParam("trn_task_status_id")."&";
	$sActionFileName.= "project_id=".GetParam("trn_project_id")."&";
	$sActionFileName.= "sub_project_id=".GetParam("trn_sub_project_id")."&";
	$sActionFileName.= "priority_id=".GetParam("trn_priority_id")."&";
	$sActionFileName.= "task_cost=".GetParam("trn_task_cost")."&";
	$sActionFileName.= "hourly_charge=".GetParam("trn_hourly_charge")."&";
	$sActionFileName.= "task_type_id=".GetParam("trn_task_type_id")."&#tasks";
	
	$sWhere = "";
	$bErr = false;

	$sql  = " SELECT is_domain_required ";
	$sql .= " FROM projects WHERE project_id = " . $selected_project;	
	$project_is_domain_required = get_db_value($sql);
	
	
	if($sAction == "cancel") {
		header("Location: " . $sActionFileName);
		exit;
	}
  	
  	if($sAction == "insert" || $sAction == "update") {
		$sFormErr = FormCheckFields($sWhere);
		if(strlen($sFormErr) > 0) {
			return;
		}
	}
  
	$planed_date = to_mysql_date(GetParam("year"),GetParam("month"),GetParam("day"));
	$message = GetParam("task_desc");
	
	switch(strtolower($sAction)) {

		case "insert":
      		$responsible_user_id = GetParam("responsible_user_id");
      		$priority_id         = GetParam("priority_id");
      		$task_status_id      = GetParam("task_status_id");
      		$task_cost      	 = GetParam("task_cost");
      		$hourly_charge     	 = GetParam("hourly_charge");
	
			$task_id = add_task($responsible_user_id, $priority_id, $task_status_id, $selected_project, $client_id,
							GetParam("task_title"), $message, $planed_date, 
							GetSessionParam("UserID"), $sEstim, GetParam("task_type_id"), $hash);
			
			update_task($task_id, array("task_cost"       => $task_cost));
			update_task($task_id, array("hourly_charge"   => $hourly_charge));
			update_task($task_id, array("parent_task_id"  => intval(GetParam("parent_task_id"))));
			update_task($task_id, array("task_domain_url" => GetParam("task_domain")));

		break;

    	case "delete":
    		delete_task($pPKtask_id);
    	break;
	}
	header("Location: " . $sActionFileName);
	exit;
}

function Form_Show()
{
	global $db, $db1, $T, $sAction, $sForm, $sFormErr, $cur_date, $selected_project, $hash, $client_id, $statuses_classes, $task_id;
	if (has_permission("PERM_VIEW_ALL_TASKS")) {
		$T->parse("show_tasks");
	} else {
		$T->set_var("show_tasks", "");
	}
	$sWhere = "";
	$bPK = true;
	$fldtask_id = "";
	$fldproject_id = "";
	$fldsubproject_id = "";
	$fldtask_title = "";
	$fldtask_desc = "";
	$fldplaned_date = ""; $fldtask_status_id = ""; $fldresponsible_user_id = ""; $fldpriority_id = "";
	$fldtask_cost = "";
	$fldhourly_charge = "";
	$fldtask_type_id = "";
	$fldproject_id = stripslashes(GetParam("project_id"));
	$fldsubproject_id = stripslashes(GetParam("sub_project_id"));
	$fldplaned_date=array();
	$fldplaned_date["MONTH"] = $cur_date["mon"]; $fldplaned_date["YEAR"] = substr($cur_date["year"],2,2);
	$fldplaned_date["DAY"] = $cur_date["mday"];
	$T->set_var("hash",strval($hash));
	
	//-- transit parameters (in order to return to index.php with that parameters

  if($sAction)
  {
     $T->set_var(array(
                "trn_task_status_id"=> GetParam("trn_task_status_id"),
                "trn_task_cost"=> GetParam("trn_task_cost"),
                "trn_hourly_charge"=> GetParam("trn_hourly_charge"),
                "trn_project_id" 	=> GetParam("trn_project_id"),
                "trn_sub_project_id" 	=> GetParam("trn_sub_project_id"),
                "trn_priority_id"	=> GetParam("trn_priority_id"),
                "trn_task_type_id"	=> GetParam("trn_task_type_id")
           ));
     if (GetParam("project_filter_my")) {
     	$T->set_var("project_filter_my_checked", "checked");
     } else {
     	$T->set_var("project_filter_my_checked", "");
     }
     if (GetParam("project_filter_in")) {
     	$T->set_var("project_filter_in_checked", "checked");
     } else {
     	$T->set_var("project_filter_in_checked", "");
     }
  } else {
     $T->set_var(array(
                "trn_task_status_id"	=> GetParam("task_status_id"),
                "trn_task_cost"=> GetParam("trn_task_cost"),
                "trn_hourly_charge"=> GetParam("trn_hourly_charge"),
                "trn_project_id"	=> GetParam("project_id"),
                "trn_sub_project_id"	=> GetParam("sub_project_id"),
                "trn_priority_id"	=> GetParam("priority_id"),
                "trn_task_type_id"	=> GetParam("task_type_id")
           ));
	$T->set_var("project_filter_my_checked", "checked");
	$T->set_var("project_filter_in_checked", "checked");
  }

	if ($sAction && $sForm == "Form") {
	    $fldtask_id = stripslashes(GetParam("task_id"));
	    $fldtask_title = stripslashes(GetParam("task_title"));
	    $fldtask_desc = stripslashes(GetParam("task_desc"));
	    $fldcompletion = stripslashes(GetParam("completion"));
	    $fldplaned_date = stripslashes(GetParam("planed_date"));
	    $fldplaned_date["MONTH"] = stripslashes(GetParam("month"));
		$fldplaned_date["YEAR"] = stripslashes(GetParam("year"));
		$fldplaned_date["DAY"] = stripslashes(GetParam("day"));;
		$fldtask_status_id = stripslashes(GetParam("task_status_id"));
		$fldtask_cost = stripslashes(GetParam("task_cost"));
		$fldhourly_charge = stripslashes(GetParam("hourly_charge"));
		$fldresponsible_user_id = stripslashes(GetParam("responsible_user_id"));
		$fldpriority_id = stripslashes(GetParam("priority_id"));
		$fldtask_type_id = stripslashes(GetParam("task_type_id"));
		$ptask_id = GetParam("PK_task_id");
	} else {
		$ptask_id = GetParam("task_id");
	}

	if(strlen($ptask_id))
	{
		$sWhere .= "task_id=" . ToSQL($ptask_id, "Number");
		$T->set_var("PK_task_id", $ptask_id);
	} else {
		$bPK = false;
	}
	
	$T->set_var("parent_task_id", intval($ptask_id));
	
	$sSQL = "SELECT * FROM tasks WHERE " . $sWhere;
	
	if($bPK && $sAction != "insert") //-- update but with error or just update
	{
		$db->query($sSQL);
		$db->next_record();
		$mes_task_status_id = $db->Record["task_status_id"];
		$mes_user_id = $db->Record["responsible_user_id"];
    	$T->set_var("statuses_list", get_options("lookup_tasks_statuses WHERE status_id!=1 AND usual=1 ORDER BY sort_order","status_id","status_desc",$mes_task_status_id,"status"));
    	
    	
		//$T->set_var("people_list",   get_options("users WHERE is_deleted IS NULL ","user_id","concat(first_name,' ',last_name)",$mes_user_id,"person"));

		if($sAction == "")
		{
			$fldtask_id = GetValue($db, "task_id");
			$fldproject_id = GetValue($db, "project_id");
			$fldtask_title = GetValue($db, "task_title");
			$fldtask_cost = GetValue($db, "task_cost");
			$fldhourly_charge = GetValue($db, "hourly_charge");
			
			if (GetParam("task_id"))
			{
				$sql_mes = "SELECT message FROM messages WHERE identity_id='$fldtask_id' ORDER BY message_date DESC";
				$db1->query($sql_mes);
	    		if ($db1->next_record())
		   		{
					$fldtask_desc = $db1->Record["message"];
				} else {
					$fldtask_desc = GetValue($db, "task_desc");
				}
			} else {
				$fldtask_desc = GetValue($db, "task_desc");
			}
      		$fldestimated_hours = GetValue($db, "estimated_hours");
      		$fldresponsible_user_id = GetValue($db, "responsible_user_id");
			$fldpriority_id = GetValue($db, "priority_id");
			
			$fldtask_type_id = GetValue($db, "task_type_id");
			if (GetParam("type")=="child") {
				$fldtask_type_id = 1;
			}
			
			if (GetParam("task_id"))
			{
				$fldcompletion = 0;
				$fldtask_status_id = 7;
			} else {
				$fldcompletion = GetValue($db, "completion");
				$fldtask_status_id = GetValue($db, "task_status_id");
			}
			$fldplaned_date = date_to_array(GetValue($db, "planed_date"));
			if (isset($fldplaned_date["month"])) {
				$cur_month = $fldplaned_date["month"];
			}
			
			$sql = "SELECT parent_project_id FROM projects WHERE project_id=".ToSQL($fldproject_id, "integer");
			$db->query($sql);
			if ($db->next_record() && $db->f("parent_project_id")>0) {
				$fldsubproject_id = $fldproject_id;
				$fldproject_id = $db->f("parent_project_id");
			}
		}
		$T->parse("FormInsert", false);
		/*$T->set_var("FormInsert", "");*/
		$T->parse("FormCancel", false);
	}
  	else //-- new or insert with error
	{
		if($sAction == "") $fldpriority_id= "2";
		$T->set_var("FormEdit", "");
		$T->parse("FormInsert", false);
		$T->parse("FormCancel", false);
	}
    $T->set_var("task_id", ToHTML($fldtask_id));
	
/* 	
	$T->set_var("LBproject_id", "");
    $T->set_var("ID", "");
    $T->set_var("Value", "");
	$T->parse("LBproject_id", true);
    $db->query("SELECT project_id, project_title FROM projects WHERE parent_project_id IS NULL AND is_closed=0 ORDER BY 2");
    while($db->next_record())
    {
      	$T->set_var("ID", $db->f(0));
      	$T->set_var("Value", $db->f(1));
      	if($db->f(0) == $fldproject_id)	$T->set_var("Selected", "SELECTED" );
      	else 				        	$T->set_var("Selected", "");
      	$T->parse("LBproject_id", true);
    }
    */
    //projects
    
    $user_parent_projects = array();
    $sql = " SELECT p.parent_project_id FROM projects p INNER JOIN users_projects up ON (p.project_id=up.project_id) ";
    $sql.= " WHERE p.is_closed=0 AND up.user_id=".ToSQL(GetSessionParam("UserID"), "integer")." AND p.parent_project_id IS NOT NULL";
    $sql.= " GROUP BY p.parent_project_id ";    
    $db->query($sql);
    while($db->next_record()) {
    	$user_parent_projects[$db->f("parent_project_id")] = true;
    }

    $sql = " SELECT p.project_id FROM projects p INNER JOIN users_projects up ON (p.project_id=up.project_id) ";
    $sql.= " WHERE p.is_closed=0 AND up.user_id=".ToSQL(GetSessionParam("UserID"), "integer")." AND p.parent_project_id IS NULL";
    $sql.= " GROUP BY p.project_id ";
    $db->query($sql);
    while($db->next_record()) {
    	$user_parent_projects[$db->f("project_id")] = true;
    }

    $T->set_var("project_id_selected", intval($fldproject_id));
    
	//projects
	$sql = " SELECT p.project_id, p.project_title, ps.is_completed ";
	$sql.= " FROM projects p ";
	$sql.= " LEFT JOIN projects_statuses ps ON (p.project_status_id = ps.project_status_id) ";
	$sql.= " WHERE p.parent_project_id IS NULL AND p.is_closed=0 ";
	$sql.= " GROUP BY p.project_id ";
	$sql.= " ORDER BY p.project_title ";
	
	$i=0;
   	$db->query($sql);
   	if ($db->num_rows())
   	{
   		while ($db->next_record())
    	{
	  		$T->set_var("ID",$db->f("project_id"));
			$i++;
	  		if ($db->f("is_completed")) {
	  			$T->set_var("project_in", "0");
	  		} else {
	  			$T->set_var("project_in", "1");
	  		}
	  		
	  		if (isset($user_parent_projects[$db->f("project_id")])) {
	  			$T->set_var("project_my", "1");
	  		} else {
	  			$T->set_var("project_my", "0");
	  		}
	  		
	  		$T->set_var("I", $i);
	  		$T->set_var("project_title",addslashes($db->f("project_title")));
	  		$T->parse("ProjectArray",true);	  		
	  	}
	} else $T->set_var("ProjectArray","");    
    
	//subprojects
	$sql = " SELECT p.parent_project_id, p.project_id, p.project_title, ps.is_completed, up.user_id ";
	$sql.= " FROM projects p ";
	$sql.= " LEFT JOIN users_projects up ON (p.project_id=up.project_id AND up.user_id=".ToSQL(GetSessionParam("UserID"), "integer").")";
	$sql.= " LEFT JOIN projects_statuses ps ON (p.project_status_id = ps.project_status_id) ";	
	$sql.= " WHERE p.parent_project_id IS NOT NULL AND (p.is_closed=0 OR p.is_closed is NULL) ORDER BY p.parent_project_id, p.project_title ";
   	$db->query($sql);
	$parent_id=0;
   	if ($db->num_rows())
   	{
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
	  		if ($db->f("is_completed")) {
	  			$T->set_var("subproject_in", "0");
	  		} else {
	  			$T->set_var("subproject_in", "1");
	  		}
	  		if ($db->f("user_id")) {
	  			$T->set_var("subproject_my", "1");
	  		} else {
	  			$T->set_var("subproject_my", "0");
	  		}
	  		
	  		$T->set_var("I", $i);
	  		$T->set_var("subproject_title",addslashes($db->f(2)));
	  		$T->parse("SubProjectArray",true);
	  		$parent_id=$db->f(0);
	  	}
	} else $T->set_var("SubProjectArray","");
	//set subproject selection
	$T->set_var("sub_project_id_value", (isset($fldsubproject_id) && $fldsubproject_id>0) ? $fldsubproject_id : "0");
	////////////////////////////////////////
    if (!isset($fldcompletion) || !strlen($fldcompletion)) $fldcompletion="0";
    $T->set_var(array("task_title" => ToHTML($fldtask_title),
                      "task_desc"  => ToHTML($fldtask_desc),
                      "completion" => $fldcompletion,
                      "task_cost" => $fldtask_cost,
                      "hourly_charge" => ($fldhourly_charge>0 ? $fldhourly_charge : "15")
                ));
    if ($fldhourly_charge>0) {
    	$T->set_var("hourly_charge_checked", "checked");
    } else {
    	$T->set_var("hourly_charge_checked", "");
    }
                
    $T->set_var($fldplaned_date);
    
    get_users_projects();
    
    
  
    get_select_options("lookup_tasks_statuses", "status_id", "status_desc", $fldtask_status_id, "WHERE status_id!=1 AND usual=1 ", "sort_order", "ID", "Value", "LBtask_status_id", false);    
    get_select_options("users", "user_id", "CONCAT(first_name,' ',last_name) as user_name", $fldresponsible_user_id, "WHERE is_deleted IS NULL", "user_name", "ID", "Value", "LBresponsible_user_id", array(""=>"-please select-"));

    $T->set_var("LBpriority_id", "");
	$sql = "SELECT COUNT(task_id)+1 AS max_priority,responsible_user_id FROM tasks WHERE is_closed=0 AND is_wish=0 GROUP BY responsible_user_id";
	$db->query($sql);
	$users_priorities = "";
    while($db->next_record())
    {
    	$max_priority = $db->Record["max_priority"];
    	$r_user_id    = $db->Record["responsible_user_id"];
    	if (!$max_priority) $max_priority = 1;
    	$users_priorities .= "dicUsers[$r_user_id] = $max_priority;\n";
    }
    $T->set_var("dicUsers",$users_priorities);

    $taskArr = "";
   	$idArr = "";
   	$statArr = "";
   	$prArr = "";
   	$today = date('Y-m-d');

	$sql = "SELECT GROUP_CONCAT(task_title ORDER BY priority_id  SEPARATOR ';' ) AS tt , GROUP_CONCAT(task_id ORDER BY priority_id  SEPARATOR ';') AS tasks_id, responsible_user_id, GROUP_CONCAT(task_status_id ORDER BY priority_id  SEPARATOR ';' ) AS statuses, ";
	$sql .= " GROUP_CONCAT(planed_date ORDER BY priority_id  SEPARATOR ';' ) AS planed, ";
	$sql .= " GROUP_CONCAT(project_id ORDER BY priority_id  SEPARATOR ';' ) AS projects_ids, ";
	$sql .= "GROUP_CONCAT(task_type_id ORDER BY priority_id  SEPARATOR ';' ) AS types ";
	$sql .= " FROM tasks WHERE is_closed='0'AND is_wish='0' GROUP BY responsible_user_id";
	$db->query($sql,__FILE__,__LINE__);
	if ($db->next_record())
	{
		$tasks = "";
		$statuses = "";
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
		    foreach ($projects_arr as $project)
		    {
		    	$sql1= "SELECT project_title from projects where project_id='$project'";//.ToSQL($project, "integer");
		    	$db1->query($sql1);
		    	$db1->next_record();
		    	$projects .= $db1->f("project_title").";";
		    }
			$tasks = addslashes($tasks);
		 	$status_array = explode(";", $db->f("statuses"));
		 	$planed_array = explode(";", $db->f("planed"));
			for ($i=0; $i<sizeof($status_array); $i++)
			{
			 	$status = $status_array[$i];
			 	$planed_date = @$planed_array[$i];
			 	if ($planed_date < $today && $status != 1 && $status != 4) {
			 		$statuses .= "Deadline;";
			 	} elseif ($planed_date == $today && $status != 1 && $status != 4) {
			 		$statuses .= "Today;";
			 	} elseif (isset($statuses_classes[$status])) {
					$statuses .= $statuses_classes[$status].";";
			 	} else {
			 		$statuses .= "_;";
			 	}
			}
			$statArr .= "statArr[$r_id] = '".tojavascript($statuses)."';\n";
			$taskArr .= "taskArr[$r_id] = '".tojavascript($tasks)."';\n";
		 	$idArr .= "idArr[$r_id] = '".tojavascript($tasks_id)."';\n";
		 	$prArr .= "prArr[$r_id] = '".tojavascript($projects)."';\n";
		} while ($db->next_record());

		$T->set_var("statArr", $statArr);
		$T->set_var("taskArr", $taskArr);
		$T->set_var("idArr", $idArr);
		$T->set_var("prArr", $prArr);
	}
	//task_type
	get_select_options("lookup_task_types", "type_id", "type_desc", $fldtask_type_id, "", "type_desc", "ID", "Value", "LBtask_type_id");
	//var_dump($fldtask_type_id);
	if($sFormErr == "") {
	    $T->set_var("FormError", "");
	} else {
		$T->set_var("sFormErr", $sFormErr);
		$T->parse("FormError", false);
	}

	$T->set_var("MONTH" , get_month_options($fldplaned_date["MONTH"]));
	$T->set_var("DAY" , (GetSessionParam("UserID")==15 ? $fldplaned_date["DAY"] : "" ) );
	
	

	if (is_manager(GetSessionParam("UserID"))) {
		$T->parse("edit_project_link", false);
	} else {
		$T->set_var("edit_project_link", "");
	}

	$T->parse("FormForm", false);
}

function MessagesShow()
{
	global $db, $T, $sAction, $task_id, $cur_message_colors, $level_colors;
	
	$i=0;
	if ($task_id)
	{
		$T->set_var("last_message","");
		$T->set_var("message_shortcut","");
		
		$sql="SELECT messages.*, users.*, r_users.first_name AS r_first_name, r_users.last_name AS r_last_name, ".
		"DATE_FORMAT(message_date,'%a %D %b %Y, %H:%i') as message_date ".
		"FROM messages ".
		"LEFT JOIN users ON messages.user_id=users.user_id ".
		"LEFT JOIN users AS r_users ON messages.responsible_user_id=r_users.user_id ".
		"WHERE messages.identity_type='task' and messages.identity_id=".$task_id." ".
		"ORDER BY messages.message_date DESC";
		$db->query($sql);

    	if ($db->next_record())
		{
			$T->set_var("last_message","\r\n\r\n>".preg_replace("/\r\n/","\r\n>",$db->f("message")));
			do
			{
				$T->set_var($db->Record);
				$T->set_var("message", process_message($db->f("message"),$task_id, "task"));
				$T->set_var("message_number",$i);
				$T->set_var("message", "111");
				if ($T->block_exists("message_shortcut")) {					
					$T->parse("message_shortcut");
				}
				if ($T->block_exists("single_message")) {
					$T->parse("single_message");
				}
				$i++;
			}
			while ($db->next_record());
		} else {
			$T->set_var("single_message","");
			$T->set_var("last_message","\r\n\r\n>".preg_replace("/\r\n/","\r\n>",$T->get_var("task_desc")));
		}
		if ($T->block_exists("messages_block")) {
			$T->parse("messages_block");
		}
	} else {
		$T->set_var("messages_block","");
	}
	$T->set_var("total_messages",$i);
}

?>