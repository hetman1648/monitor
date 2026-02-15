<?php
	include("./includes/date_functions.php");
	include("./includes/common.php");

	CheckSecurity(1);

	$db_users = new DB_Sql();
	$db_users->Database = DATABASE_NAME;
	$db_users->User     = DATABASE_USER;
	$db_users->Password = DATABASE_PASSWORD;
	$db_users->Host     = DATABASE_HOST;
							
	$project_selected = (int)GetParam("project_selected");
	$period_selected = GetParam("period_selected");
	//$action = GetParam("action");
	$start_date = GetParam("start_date");
	$end_date = GetParam("end_date");
	//$submit = GetParam("submit");	
	//team select block
	$team = GetParam("team");
	$sort = GetParam("sort");
	$tasks = GetParam("tasks");	

	$projects = array();

	$as = "";
	$vs = "";
	$ys = "";
	switch (strtolower($team))
	{
	  case "all":	$sqlteam=""; $as="selected"; break;
	  case "viart":	$sqlteam=" AND u.is_viart=1 "; $vs="selected"; break;
	  case "yoonoo":$sqlteam=" AND u.is_viart=0 "; $ys="selected"; break;
	  default:	$sqlteam=" AND u.is_viart=1 "; $vs="selected"; $team="viart";
	}
	
	$t = new iTemplate($sAppPath);
	$t->set_file("main","responses_report.html");

	$t->set_var("periods", GetPeriodOptions($period_selected ? $period_selected : "this_month"));

	// make default this year and month for javascript
	$t->set_var("thisyear", date("Y")-2004+1);
	$t->set_var("thismonth", date("m"));
	$t->set_var("aselected",$as); $t->set_var("vselected",$vs); $t->set_var("yselected",$ys);
	$t->set_var("team_selected",$team);
	$t->set_var("period_selected", $period_selected);
	$t->set_var("tasks_checked",$tasks ? "checked" : "");	

	list($sdt,$edt)=get_start_end_period($period_selected,$start_date,$end_date);
	
	$t->set_var("year_selected",  (isset($year_selected) && $year_selected>2000 ? $year_selected : date("Y")));
	$t->set_var("month_selected", (isset($month_selected) && $month_selected>0 ? $month_selected : date("m")));

	if ($start_date && $end_date)
	{
		$s_trlimits = "";
		if ($sdt) $s_trlimits .= " AND r.date_added>='$sdt' ";
		if ($edt) $s_trlimits .= " AND r.date_added<='$edt' ";				
		if ($team=="viart")  $s_team = " AND u.is_viart=1 ";
		if ($team=="yoonoo") $s_team = " AND u.is_viart=0 ";
		if ($project_selected && $project_selected>0) $s_project = " AND p.project_id=".(int)$project_selected;

		//
		$sql = " SELECT r.date_added, r.manager_id, r.task_id, r.response_time, t.task_title, r.is_assigned_to_myself ";
		$sql.= ", IF(r.is_assigned_to_myself IS NOT NULL,1,0) AS assign_data ";
		$sql.= " FROM responses r INNER JOIN tasks t ON (r.task_id=t.task_id) ";
		$sql.= " WHERE 1=1 ".$s_trlimits;
		$sql_order = " ORDER BY r.manager_id ASC, date_added ASC ";
		
		if ($tasks) {
			$t->set_var("colspan","6");
		} else {
			$t->set_var("colspan","5");
		}
		
		$sql_manager = " SELECT CONCAT(u.first_name,' ',u.last_name) AS user_name, u.user_id ";
		$sql_manager.= ", SUM(IF(q.is_assigned_to_myself=1,1,0)) AS assigned_myself ";
		$sql_manager.= ", SUM(IF(q.is_assigned_to_myself=0,1,0)) AS not_assigned_myself ";		
		$sql_manager.= ", COUNT(q.task_id) as tasks_count, AVG(q.response_time) AS avg_response_time ";
		$sql_manager.= " FROM users u INNER JOIN (".$sql.")q ON (u.user_id=q.manager_id) ";
		$sql_manager.= " WHERE 1=1 ".$sqlteam;
		$sql_manager.= " GROUP BY u.user_id ORDER BY u.first_name, u.last_name ";		
	
		$db_users->query($sql_manager);
		$t->set_var("average_","");
		if ($db_users->num_rows())
		{
			while ($db_users->next_record())
			{
				$manager_id = $db_users->f("user_id");
				$manager_name = $db_users->f("user_name");
				$avg_response_time = $db_users->f("avg_response_time");
				$tasks_count = $db_users->f("tasks_count");
				$a_myself = $db_users->f("assigned_myself");
				$a_not_myself = $db_users->f("not_assigned_myself");
			
				$t->set_var("manager_name", $manager_name);
				$t->set_var("avg_response_time", Hours2HoursMins($avg_response_time));
				$t->set_var("tasks_count", $tasks_count);
				$t->set_var("manager_records","");
				$t->set_var("assign_to_myself_tasks",intval($a_myself));
				if ($a_not_myself + $a_myself) {
					$t->set_var("assign_to_myself_percent",intval(round(100*$a_myself/($a_myself+$a_not_myself)))."%");
				} else {
					$t->set_var("assign_to_myself_percent","");
				}
				
				if ($tasks) {
					
					
				$db->query($sql." AND r.manager_id=".ToSQL($manager_id,"Number"));

				while ($db->next_record())
				{
			    	$date_added = $db->f("date_added");
			    	$manager_id = $db->f("manager_id");
		 		   	$task_id=  $db->f("task_id");
		    		$response_time = $db->f("response_time");
		    		$task_title = $db->f("task_title");
		    		$assign_data = $db->f("assign_data");
		    		$assign_to_myself = $db->f("is_assigned_to_myself");
		    		
		    		if ($assign_data) {
		    			if ($assign_to_myself) {
		    				$task_assign_desc = "Yes";
		    			} else {
		    				$task_assign_desc = "No";
		    			}
		    		} else {
		    			$task_assign_desc = "-";
		    		}
	
			    	$t->set_var("date_added", $date_added);
			    	$t->set_var("task_title", $task_title);
			    	$t->set_var("task_id", $task_id);
		    		$t->set_var("response_time", Hours2HoursMins($response_time));		    		
		    		$t->set_var("assign_to_myself_desc", $task_assign_desc);

		    		$t->parse("manager_records",true);
				}
					$t->parse("date_column_summary", false);
					$t->parse("date_column_header", false);
					$t->set_var("average_","");
				} else {
					$t->set_var("date_column_summary", "");
					$t->set_var("date_column_header", "");
					$t->set_var("average_","Average ");
				}
			
			$t->parse("manager_summary",true);
			
		}
			$t->set_var("no_records","");
		} else {			
			$t->set_var("manager_summary","");
			$t->parse("no_records", false);
		}
	
	$t->pparse("main");
	}
	
function userlist($sqlfromwhere,$project_id,$start_date,$end_date,$period_selected,$alllink=false)
{
	$db_users_f = new DB_Sql();
	$db_users_f->Database = DATABASE_NAME;
	$db_users_f->User     = DATABASE_USER;
	$db_users_f->Password = DATABASE_PASSWORD;
	$db_users_f->Host     = DATABASE_HOST;

	$sql_users = " SELECT DISTINCT(u.user_id), first_name AS user_name ".$sqlfromwhere;
	$sql_users.= " AND p.project_id=".$project_id." ORDER BY user_name";
	$db_users_f->query($sql_users);
	if ($db_users_f->next_record())
	{
	  //with links
	  $users_list =  "<NOBR>".$db_users_f->f("user_name")."</NOBR>";
	  while ($db_users_f->next_record())
	  $users_list.=", <NOBR>".$db_users_f->f("user_name")."</NOBR>";
	}
	
	return $users_list;
}

function get_start_end_period($period_selected,&$start_date,&$end_date)
{
	global $t;
	
	$current_date = va_time();
	$cyear = $current_date[0];
	$cmonth = $current_date[1];
	$cday = $current_date[2]; 
	
	$this_week_start_date = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday - date("w")+1, $cyear));
	$this_week_end_date   = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday, $cyear));
	$t->set_var("this_week_start", $this_week_start_date);
	$t->set_var("this_week_end",   $this_week_end_date);

	$last_week_start_date = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday - 6, $cyear));
	$last_week_end_date   = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday, $cyear));
	$t->set_var("last_week_start", $last_week_start_date);
	$t->set_var("last_week_end",   $last_week_end_date);

	$prev_week_start_date = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday - date("w")-6, $cyear));
	$prev_week_end_date   = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday - date("w"), $cyear));
	$t->set_var("prev_week_start", $prev_week_start_date);
	$t->set_var("prev_week_end",   $prev_week_end_date);

	$prev_month_start_date = date ("Y-m-d", mktime (0, 0, 0, $cmonth - 1, 1, $cyear));
	$prev_month_end_date   = date ("Y-m-t", mktime (0, 0, 0, $cmonth - 1, 1, $cyear));
	$t->set_var("prev_month_start", $prev_month_start_date);
	$t->set_var("prev_month_end",   $prev_month_end_date);

	$last_month_start_date = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday-30, $cyear));
	$last_month_end_date   = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday, $cyear));
	$t->set_var("last_month_start", $last_month_start_date);
	$t->set_var("last_month_end",   $last_month_end_date);

	$this_month_start_date = date ("Y-m-d", mktime (0, 0, 0, $cmonth, 1, $cyear));
	$this_month_end_date   = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday, $cyear));
	$t->set_var("this_month_start", $this_month_start_date);
	$t->set_var("this_month_end",   $this_month_end_date);
	
	$year_start_date = date ("Y-m-d", mktime (0, 0, 0, 1, 1, $cyear));
	$year_end_date   = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday, $cyear));
	$t->set_var("this_year_start", $year_start_date);
	$t->set_var("this_year_end",   $year_end_date);
	
	if (!$period_selected) $period_selected="this_month";
	
	if (!$start_date && !$end_date) {
		switch ($period_selected) {
			case "last_week":
				$start_date = $last_week_start_date;
				$end_date = $last_week_end_date;
				break;
			case "prev_week":
				$start_date = $prev_week_start_date;
				$end_date = $prev_week_end_date;
				break;
			case "this_month":
				$start_date = $this_month_start_date;
				$end_date = $this_month_end_date;
				break;
			case "last_month":
				$start_date = $last_month_start_date;
				$end_date = $last_month_end_date;
				break;
			case "prev_month":
				$start_date = $prev_month_start_date;
				$end_date = $prev_month_end_date;
				break;
			case "this_year":
				$start_date = $year_start_date;
				$end_date = $year_end_date;
				break;
			case "this_week":
				$start_date = $this_week_start_date;
				$end_date = $this_week_end_date;
				break;
		}
	}

	$sd = "";
	$ed = "";
	$sdt = "";
	$edt = "";
	if ($start_date) {
		$sd_ar = parse_date(array("YYYY", "-", "MM", "-", "DD"), $start_date, "Start Date");
		$sd_ts = mktime (0, 0, 0, $sd_ar[1], $sd_ar[2], $sd_ar[0]);
		$sdt_ts = mktime (0, 0, 0, $sd_ar[1], $sd_ar[2], $sd_ar[0]);
		$sd = @date("Y-m-d", $sd_ts);
		$sdt = @date("Y-m-d 00:00:00", $sd_ts);
	}
	if ($end_date) {
		$ed_ar = parse_date(array("YYYY", "-", "MM", "-", "DD"), $end_date, "End Date");
		$ed_ts = mktime (0, 0, 0, $ed_ar[1], $ed_ar[2], $ed_ar[0]);
		$ed = @date("Y-m-d", $ed_ts);
		$edt = @date("Y-m-d 23:59:59", $ed_ts);
 	}                                  
	
 	$t->set_var("start_date", $sd);
	$t->set_var("end_date", $ed);

	$end_year  =@date("Y",$ed_ts);
	$start_year=@date("m",$ed_ts);
 	$t->set_var("current_year", $end_year);
	$t->set_var("current_month", $start_year);
	
	return array($sdt,$edt);
}

?>