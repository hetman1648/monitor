<?
	include("./includes/common.php");
	CheckSecurity(1);

	if (getsessionparam("privilege_id") == 9) {
	header("Location: index.php");
	exit;
	}

	$statuses_classes = array("","InProgress","OnHold","Rejected","Done","Question","Answer","New","Waiting","Reassigned","Bug");

	$rp = GetParam("rp");

	//-- actions
	if ($action == "close" && $task_id && is_numeric($task_id))
	{		CountTimeProjects($task_id);

		$sql = "UPDATE tasks SET is_closed=1 WHERE task_id=$task_id";
		$db->query($sql);

		$sql = "SELECT responsible_user_id, task_status_id, started_time, task_title, ".
		       " ((UNIX_TIMESTAMP(NOW())-UNIX_TIMESTAMP(started_time))/3600) as spent_hours ".
		       " FROM tasks WHERE task_id=$task_id";
		$db->query($sql);
		if ($db->next_record())
		{
			$title = $db->Record["task_title"];
			$responsible_user_id = $db->Record["responsible_user_id"];
			$task_status_id = $db->Record["task_status_id"];
			$started_time = $db->Record["started_time"];
			$spent_hours = $db->Record["spent_hours"];

			@mail("artem@viart.com.ua","Monitor: Task '$title' closed -#id=$task_id","Task '$title' closed -#id=$task_id\nTask closed by ".GetSessionParam("UserName"),"From:monitor@viart.com.ua");

			if ($task_status_id==1)
			{
				$sql = "INSERT INTO time_report (user_id,started_date,task_id,report_date,spent_hours) VALUES (";
			    	$sql.= "$responsible_user_id,'$started_time',$task_id,NOW(),$spent_hours)";
			    	//echo $sql."<BR>";
	    			$db->query($sql);

				$sql = "UPDATE tasks SET actual_hours = (actual_hours + $spent_hours), task_status_id = 8 ";
		    		$sql .= " WHERE task_id = " . ToSQL($task_id, "integer");
		    		//echo $sql."<BR>";
		    		$db->query($sql);
			}
		}



	    header("Location: " . $rp);
	    exit;
	}

	if ($operation == "save_priorities")
	{
		$tasks_priorities = array();
		foreach($HTTP_POST_VARS as $var_name=>$var_value)
		{
			//echo "$var_name => $var_value<br>";
			$parts = split("_",$var_name);
			if ($parts[0] == "priority" && $parts[1])
			{
				$task_id  = $parts[1];
				$priority = $var_value;
				if (!$priority) $priority = "NULL";
				$sql = "UPDATE tasks SET priority_id=$priority WHERE task_id=$task_id";
				$db->query($sql);
				$tasks_priorities[] = $task_id;
				//echo $sql."<hr>";
			}
		}

		$emails = "";
		$sql = "SELECT DISTINCT responsible_user_id,email FROM tasks AS t,users AS u WHERE t.responsible_user_id=u.user_id AND t.task_id IN (" . join(",", $tasks_priorities) . ")";
		$db->query($sql);

		while ($db->next_record())
		{
			if ($emails) $emails.= ",";
			$emails .= $db->Record["email"];
		}
		//echo $emails . "<hr>";
		@mail($emails,"Monitor: your priorities were changed","Your priorities have been changed by " . GetSessionParam("UserName") . "\n\nPlease visit http://www.viart.com.ua/monitor for details");
		header("Location: report_people.php?is_viart=$is_viart&report_user_id=$report_user_id");
		exit;
	}

	//-- save_estimates
	if ($operation == "save_estimates")
	{
		$tasks_priorities = array();
		foreach($HTTP_POST_VARS as $var_name=>$var_value)
		{
			$parts = split("_",$var_name);
			if ($parts[0] == "estimateuhours" && $parts[1])
			{
				$task_id  = $parts[1];
				$estimate = $var_value;
				if (!$estimate) $estimate = 0;
				else if ($estimate>0.01 && $estimate<10000)
				{
					$sql = "UPDATE tasks SET estimated_hours=".(float)$estimate." WHERE task_id=$task_id";
					$db->query($sql);

					$sql = "INSERT INTO estimates (estimate_id,task_id,estimate_time,date_added,user_added) ".
					       " VALUES(0, $task_id, ".(float)$estimate.", NOW(), ".(int)GetSessionParam("UserID").")";
					$db->query($sql);
				}
				$tasks_priorities[] = $task_id;
			}
		}

		$emails = "";
		$sql = "SELECT DISTINCT responsible_user_id,email FROM tasks AS t,users AS u WHERE t.responsible_user_id=u.user_id AND t.task_id IN (" . join(",",$tasks_priorities) . ")";

		$db->query($sql);

		while ($db->next_record())
		{
			if ($emails) $emails.= ",";
			$emails .= $db->Record["email"];
		}

		@mail($emails,"Monitor: your tasks estimates were changed","Your tasks estimates have been changed by " . GetSessionParam("UserName") . "\n\nPlease visit http://www.viart.com.ua/monitor for details");

		header("Location: report_people.php?is_viart=$is_viart&report_user_id=$report_user_id");
		exit;
	}

	$T = new iTemplate("./templates",array("page"=>"report_people.html"));

	$T->set_var("rp", $_SERVER[REQUEST_URI]);
	if (!$is_viart) $is_viart=0;
	if ($report_user_id)
	{
		$where = " AND t.responsible_user_id=$report_user_id ";
		$T->set_var("page_title","Personal Report");
	}
	else
	{
		if ($is_viart) $T->set_var("page_title","ViArt Team");
		else           $T->set_var("page_title","Spotight Team");
	  	$where = " AND u.is_viart=$is_viart ";
	}

	//-- link to Time Spending Report
	if ($report_user_id) {
		$T->set_var("timereport_link_all", "");
		$T->parse("timereport_link_user", false);
	}
	else {
		$T->set_var("timereport_link_user", "");
		$T->parse("timereport_link_all", false);
	}

	//-- tasks list
	$sql = "SELECT *, t.responsible_user_id AS r_user_id, t.creation_date AS cdate, t.planed_date AS pdate, "
		. "DATE_FORMAT(t.creation_date, '%d %b %Y') AS creation_date, "
		. "DATE_FORMAT(t.planed_date, '%d %b %Y') AS planed_date, "
		. "IF (TO_DAYS(t.planed_date) < TO_DAYS(now()) AND t.task_status_id!=4 AND t.task_type_id!=3,1,0 ) AS ifdeadlined, "
		. "IF (TO_DAYS(t.planed_date) = TO_DAYS(now()) AND t.task_status_id!=4 AND t.task_type_id!=3,1,0 ) AS iftoday, "
		. "IF(t.task_status_id=2, 1, 0) AS sorter " //Tasks with status "On Hold" will be at the end of query
		. "FROM tasks AS t, projects AS p, lookup_task_types AS lt, lookup_tasks_statuses AS ls, users AS u "
		. "WHERE t.project_id=p.project_id AND t.task_type_id=lt.type_id AND t.task_status_id=ls.status_id "
		. "AND t.responsible_user_id=u.user_id AND t.is_wish = 0 "
		. $where
		. " AND t.is_closed=0 "
		. "ORDER BY t.responsible_user_id, sorter, t.priority_id ";

	$db->query($sql);
	$project_name = ""; $k = true; $id = 1; $cur_user_id = 0;
	$count_users  = array();

	if ($db->next_record())
	{
		do
		{
			$T->set_var($db->Record);
			if ($db->Record["cdate"] == "0000-00-00 00:00:00") $T->set_var("creation_date","");
			if ($db->Record["pdate"] == "0000-00-00 00:00:00") $T->set_var("planed_date","");

			$project_name = $db->Record["project_title"];
	    		$T->set_var("project_title",$project_name);
	    		$T->set_var("task_title_slashed",addslashes($db->Record["task_title"]));
	    		$T->set_var("completion_percent", ($db->Record["completion"] ? $db->Record["completion"]."%" : "&nbsp;"));

	  		$T->set_var("estimated_title",$db->Record["estimated_title"]);

			//NEW ESTIMATES OUTPUT//
			$estimated_hours = $db->Record["estimated_hours"];

			$estimated_hours_title = "";
			if ($estimated_hours) {
			  if ($estimated_hours<1)  $estimated_hours_title .= round($estimated_hours*60)." min";
			  if ($estimated_hours==1) $estimated_hours_title .= "1 hour";
			  if ($estimated_hours>1 && $estimated_hours<16)
			   $estimated_hours_title .=( fmod($estimated_hours,1) ? sprintf("%1.2f hours",$estimated_hours) : ($estimated_hours)." hours");
			  if ($estimated_hours>=16)
			   $estimated_hours_title.= ( fmod($estimated_hours,8) ? sprintf("%1.2f days",$estimated_hours/8) : ($estimated_hours/8)." days");
			}

	  		$T->set_var("estimated_hours_title",$estimated_hours_title);

			if ($k)	$T->set_var("STATUS",$statuses_classes[$db->Record["status_id"]]);
			else	$T->set_var("STATUS",$statuses_classes[$db->Record["status_id"]]."2");

			if ($db->Record["ifdeadlined"])
			{
			  $T->set_var("task_title","<font color=\"red\"><b>".$db->Record["task_title"]."</b></font>");
			  if ($db->f("task_status_id")!=1)
			  {
				if ($k) $T->set_var("STATUS","Deadline"); else $T->set_var("STATUS","Deadline2");
			  }
			}

			if ($db->Record["iftoday"])
			{
  				$T->set_var("task_title",$db->Record["task_title"]);
				if ($db->f("task_status_id")!=1)
				{
					if ($k) $T->set_var("STATUS","Today"); else $T->set_var("STATUS","Today2");
				}
			}

			$k = !$k;


			if ($cur_user_id == $db->Record["r_user_id"])
			{
				$T->set_var("tasks_header","");
			}
			else
			{
				$cur_user_id = $db->Record["r_user_id"];
				$id = 1;
				$T->parse("tasks_header",false);
			}

			$T->set_var("id",$id);
			$id++;
			$count_users[$db->Record["r_user_id"]]++;

			$completion=$db->Record["completion"];

			$T->set_var("time_left_estimate",($completion>0 && $completion<=100 ? to_hours($estimated_hours*(1-0.01*$completion),true) : "" ));
			$T->set_var("time_left_actual",($completion>5 && $completion<=100 ? to_hours((100-$completion)/$completion*($db->Record["actual_hours"] ),true) : "" ));

			$T->set_var("actual_hours",to_hours($db->Record["actual_hours"]));
			$T->parse("tasks",true);
		}
	    while ($db->next_record());

	    $T->set_var("totalRows",$id -1);
	    $T->set_var("no_mytasks","");
	}
	else
	{
		$T->set_var("tasks","");
	}

	$dicUsers = "";
	foreach($count_users as $user_id=>$count)
	{
		//$dicUsers .= "dicUsers.add($user_id,$count);\n";
		$dicUsers .= "dicUsers[$user_id] = $count;\n";
	}
	$T->set_var("dicUsers", $dicUsers);

  	$where = "";
  	if ($project_id         && is_numeric($project_id))        	$where .= " AND t.project_id=$project_id";
  	if ($task_status_id     && is_numeric($task_status_id))    	$where .= " AND t.task_status_id=$task_status_id";
  	if ($priority_id        && is_numeric($priority_id))       	$where .= " AND t.priority_id=$priority_id";
  	if ($task_type_id       && is_numeric($task_type_id))      	$where .= " AND t.task_type_id=$task_type_id";
  	if ($show_closed) 						$where .= " AND t.is_closed=1 ";
		  	else 						$where .= " AND t.is_closed=0 ";
  	if ($responsible_user_id && is_numeric($responsible_user_id))  	$where .= " AND t.responsible_user_id=$responsible_user_id";
			else						$where .= " AND t.responsible_user_id!=".GetSessionParam("UserID");

  	$T->set_var("user_name", GetSessionParam("UserName"));
  	$T->set_var("t_params", "");
  	$T->set_var("is_viart", $is_viart);
  	$T->set_var("report_user_id", $report_user_id);
  	$T->pparse("page");

function get_options($table,$field1,$field2,$selected_value,$caption)
{
    	global $db;
    	global $search_title;
    	global $t_params;

    	$list = "<OPTION value=''>-- select here --";
    	$sql = "SELECT $field1,$field2 FROM $table";
    	$db->query($sql);

    	while ($db->next_record()) {
      		if ($db->Record[$field1] == $selected_value) {
        		$list .= "<OPTION selected value='".$db->Record[$field1]."'>".$db->Record[$field2];
        		$search_title .= $caption . "-&lt;" . $db->Record[$field2] . "&gt; ";
      		}
      		else	$list .= "<OPTION value='".$db->Record[$field1]."'>".$db->Record[$field2];
    	}

    	if ($selected_value) {
      		if ($t_params) $t_params .= "&";
      		$t_params .= $field1."=".$selected_value;
    	}
    	return $list;
}
?>
