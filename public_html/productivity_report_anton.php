<?php
	include("./includes/date_functions.php");
	include("./includes/common.php");

	CheckSecurity(1);

	$year_selected		= GetParam("year_selected");
	$month_selected		= GetParam("month_selected");
	if (!$year_selected) $year_selected = date("Y");
	if (!$month_selected) $month_selected = date("m");
	
	$action = GetParam("action");
	$submit = GetParam("submit");	
	
	$initial = getmicrotime();
	show_microtime("\n\nSTART");
	
	$projects = array();

	$t = new iTemplate($sAppPath);
	$t->set_file("main","productivity_report.html");
	
	$t->set_var("months", GetMonthOptions($month_selected));
	$t->set_var("years", GetYearOptions(2004, date("Y"), $year_selected));
	$t->set_var("month_selected", $month_selected);
	$t->set_var("year_selected", $year_selected);
	
	$team_manager_id = 35; //ann
	$show_users = array(16,84,66,10,79);
	$show_users_sql = " AND u.user_id IN (16,84,66,10,79) ";
	$ticket_project_id = 113;
	$viart_parent_project_id = 19;
/*	$viart_office_project_id = 63;
	$viart_com_project_id = 62;*/
	$custom_work_project_id = 66;
	$sayu_web_clients_parent_project_id = 79;
	
	$k_viart_hours = 10; //coefficients
	$k_sayu_hours = 10;
	$k_ticket_0 = 10;
	$k_ticket_1 = 5;
	$k_ticket_2 = 0;
	$k_ticket_more = -10;
	$k_bug_importance = 5; //coefficient for bugs
	$k_paid_day = 80;
	
	$bonus_user_thresold = 1500;
	$bonus_team_thresold = 6000;
	$bonus_user_coeff = 0.2;
	$bonus_manager_coeff = 0.075;
	
	$users = array();
	$users_ids = array();
	//get people
	$sql = " SELECT user_id, CONCAT(first_name, ' ', last_name) AS user_name, manager_id FROM users u ";
	$sql.= " WHERE (manager_id=".ToSQL($team_manager_id, "integer")." OR user_id=".ToSQL($team_manager_id, "integer")." ) ".$show_users_sql;
	$sql.= " ORDER BY manager_id, user_name ";
	$db->query($sql);	
	while ($db->next_record()) {
		$user_id = $db->f("user_id");
		$users_ids[] = $user_id;
		$users[$user_id]["user_id"] = $user_id;
		if ($db->f("manager_id")<=0) {
			$users[$user_id]["is_manager"] = 1;
		} else {
			$users[$user_id]["is_manager"] = 0;
		}
		$users[$user_id]["name"] = $db->f("user_name");
		$users[$user_id]["tickets"] = 0;
		$users[$user_id]["tickets_delayed"] = 0;
		$users[$user_id]["tickets_points"] = 0;
		$users[$user_id]["tickets_delayed_points"] = 0;
		$users[$user_id]["points"] = 0;
		$users[$user_id]["bonus"] = 0;
	}
	
	foreach($users_ids as $user_id) {
		//select custom work
		$sql = " SELECT SUM(x.user_task_points) FROM (";
		$sql.= " SELECT t.task_cost*SUM(tr.spent_hours)/t.actual_hours*t.completion/100 AS user_task_points ";
		$sql.= " FROM tasks t INNER JOIN time_report tr ON (tr.task_id=t.task_id) ";
		$sql.= " WHERE tr.user_id=".ToSQL($user_id, "integer")." AND t.project_id=".ToSQL($custom_work_project_id, "integer");
		$sql.= " AND DATE_FORMAT(tr.report_date, '%Y')='$year_selected' AND DATE_FORMAT(tr.report_date, '%m')='$month_selected' ";
		$sql.= " GROUP BY t.task_id ";
		$sql.= ") AS x";
		
		$db->query($sql);
		if ($db->next_record()) {
			$users[$user_id]["custom_work"] = round($db->f(0));
		} else {
			$users[$user_id]["custom_work"] = 0;
		}
		show_microtime("custom work");
		
		//select viart hours
		$sql = " SELECT SUM(tr.spent_hours) AS tasks_spent_hours ";
		$sql.= " FROM tasks t INNER JOIN time_report tr ON (tr.task_id=t.task_id) ";
		$sql.= " INNER JOIN projects p ON (p.project_id=t.project_id) ";
		$sql.= " WHERE tr.user_id=".ToSQL($user_id, "integer");
		$sql.= " AND (p.parent_project_id=".ToSQL($viart_parent_project_id, "integer")." OR p.project_id=".ToSQL($viart_parent_project_id, "integer").") ";
		$sql.= " AND p.project_id NOT IN (".ToSQL($ticket_project_id, "integer").", ".ToSQL($custom_work_project_id, "integer").") ";
		$sql.= " AND DATE_FORMAT(tr.report_date, '%Y')='$year_selected' AND DATE_FORMAT(tr.report_date, '%m')='$month_selected' ";
		$db->query($sql);
		if ($db->next_record()) {
			$users[$user_id]["viart"] = round($db->f(0) * $k_viart_hours);
		} else {
			$users[$user_id]["viart"] = 0;
		}
		show_microtime("viart");

		//select sayu hours
		$sql = " SELECT SUM(tr.spent_hours) AS tasks_spent_hours ";
		$sql.= " FROM tasks t INNER JOIN time_report tr ON (tr.task_id=t.task_id) ";
		$sql.= " INNER JOIN projects p ON (p.project_id=t.project_id) ";
		$sql.= " WHERE tr.user_id=".ToSQL($user_id, "integer");
		$sql.= " AND (p.parent_project_id=".ToSQL($sayu_web_clients_parent_project_id, "integer")." OR p.project_id=".ToSQL($sayu_web_clients_parent_project_id, "integer").") ";
		$sql.= " AND DATE_FORMAT(tr.report_date, '%Y')='$year_selected' AND DATE_FORMAT(tr.report_date, '%m')='$month_selected' ";
		$db->query($sql);
		if ($db->next_record()) {
			$users[$user_id]["sayu"] = round($db->f(0) * $k_sayu_hours);
		} else {
			$users[$user_id]["sayu"] = 0;
		}
		show_microtime("sayu");
		
		//select bugs
		$sql = " SELECT SUM(importance_level) FROM bugs ";
		$sql.= " WHERE user_id=".ToSQL($user_id, "integer");
		$sql.= " AND DATE_FORMAT(date_issued, '%Y')='$year_selected' AND DATE_FORMAT(date_issued, '%m')='$month_selected' ";
		$db->query($sql);
		if ($db->next_record()) {
			$users[$user_id]["bugs"] = - round ( $db->f(0) * $k_bug_importance );
		} else {
			$users[$user_id]["bugs"] = 0;
		}		
		show_microtime("bugs");

		$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month_selected, $year_selected);
		
		$sql = " SELECT SUM(x.holiday_days) FROM (";
		$sql.= " SELECT ";
		$sql.= " DATEDIFF(IF(dof.end_date<='".$year_selected."-".$month_selected."-".$days_in_month."', dof.end_date, '".$year_selected."-".$month_selected."-".$days_in_month."'), IF(dof.start_date>='".$year_selected."-".$month_selected."-01',dof.start_date,'".$year_selected."-".$month_selected."-01'))+1 ";
		$sql.= " -IF(nh.holiday_id IS NOT NULL, COUNT(nh.holiday_id), 0) ";
		$sql.= " -FLOOR((DATEDIFF(IF(dof.end_date<='".$year_selected."-".$month_selected."-".$days_in_month."', dof.end_date, '".$year_selected."-".$month_selected."-".$days_in_month."'), IF(dof.start_date>='".$year_selected."-".$month_selected."-01',dof.start_date,'".$year_selected."-".$month_selected."-01'))+1)/7)*2 ";
		$sql.= " -IF ( MOD((DATEDIFF(IF(dof.end_date<='".$year_selected."-".$month_selected."-".$days_in_month."', dof.end_date, '".$year_selected."-".$month_selected."-".$days_in_month."'), IF(dof.start_date>='".$year_selected."-".$month_selected."-01',dof.start_date,'".$year_selected."-".$month_selected."-01'))+1),7) > 0 , ";
		$sql.= " IF (DAYOFWEEK(IF(dof.start_date>='".$year_selected."-".$month_selected."-01',dof.start_date,'".$year_selected."-".$month_selected."-01'))=7, ";
		$sql.= " IF( MOD((DATEDIFF(IF(dof.end_date<='".$year_selected."-".$month_selected."-".$days_in_month."', dof.end_date, '".$year_selected."-".$month_selected."-".$days_in_month."'), IF(dof.start_date>='".$year_selected."-".$month_selected."-01',dof.start_date,'".$year_selected."-".$month_selected."-01'))+1),7) >= 2, 2, 1), ";
		$sql.= " IF( DAYOFWEEK(IF(dof.start_date>='".$year_selected."-".$month_selected."-01',dof.start_date,'".$year_selected."-".$month_selected."-01'))=1, ";
		$sql.= " 1, ";
		$sql.= " IF( DAYOFWEEK(IF(dof.start_date>='".$year_selected."-".$month_selected."-01',dof.start_date,'".$year_selected."-".$month_selected."-01')) + ";
		$sql.= " MOD((DATEDIFF(IF(dof.end_date<='".$year_selected."-".$month_selected."-".$days_in_month."', dof.end_date, '".$year_selected."-".$month_selected."-".$days_in_month."'), ";
		$sql.= " IF(dof.start_date>='".$year_selected."-".$month_selected."-01',dof.start_date,'".$year_selected."-".$month_selected."-01'))+1),7) - 1 >= 7, ";
		$sql.= " IF (DAYOFWEEK(IF(dof.start_date>='".$year_selected."-".$month_selected."-01',dof.start_date,'".$year_selected."-".$month_selected."-01')) ";
		$sql.= " + MOD((DATEDIFF(IF(dof.end_date<='".$year_selected."-".$month_selected."-".$days_in_month."',dof.end_date, ";
		$sql.= " '".$year_selected."-".$month_selected."-".$days_in_month."'), IF(dof.start_date>='".$year_selected."-".$month_selected."-01',dof.start_date,'".$year_selected."-".$month_selected."-01'))+1),7) - 1 = 7,1,2), ";
		$sql.= " 0	)	)  ), 0) AS holiday_days ";
		$sql.= " FROM days_off dof ";
		$sql.= " LEFT JOIN national_holidays nh ON (nh.holiday_date>=dof.start_date AND nh.holiday_date<=dof.end_date AND DATE_FORMAT(nh.holiday_date, '%m')=".ToSQL($month_selected, "integer");
		$sql.= " AND DAYOFWEEK(nh.holiday_date) NOT IN (1,7)) ";
		$sql.= " WHERE dof.user_id=".ToSQL($user_id, "integer");
		$sql.= " AND DATE_FORMAT(dof.start_date, '%Y')*12+DATE_FORMAT(dof.start_date, '%m') <= ".ToSQL($year_selected*12+$month_selected, "integer");
		$sql.= " AND DATE_FORMAT(dof.end_date, '%Y')*12+DATE_FORMAT(dof.end_date, '%m') >= ".ToSQL($year_selected*12+$month_selected, "integer");
		$sql.= " AND (dof.reason_id IN (1,2) OR dof.is_paid=1) ";
		$sql.= " GROUP BY dof.period_id ";
		$sql.= ") AS x ";
		$db->query($sql);
		if ($db->next_record()) {
			$users[$user_id]["paid_days"] = $k_paid_day * $db->f(0);
		} else {
			$users[$user_id]["paid_days"] = 0;
		}
		show_microtime("paid days");
		
		//show not rated tasks
		$sql = " SELECT t.task_id, t.task_title, SUM(tr.spent_hours) AS task_spent_hours ";
		$sql.= " FROM tasks t ";
		$sql.= " INNER JOIN time_report tr ON (t.task_id = tr.task_id) ";
		$sql.= " WHERE tr.user_id=".ToSQL($user_id, "integer")." AND t.project_id=".ToSQL($custom_work_project_id, "integer");
		$sql.= " AND DATE_FORMAT(tr.report_date, '%Y')='$year_selected' AND DATE_FORMAT(tr.report_date, '%m')='$month_selected' ";
		$sql.= " AND (t.task_cost IS NULL OR t.task_cost=0) ";
		$sql.= " GROUP BY t.task_id ORDER BY task_spent_hours DESC ";
		//echo "<BR><BR>".$sql;
		$users[$user_id]["not_rated"] = array();
		$db->query($sql);
		while($db->next_record()) {
			$users[$user_id]["not_rated"][] = $db->Record;
		}
		show_microtime("not_rated");
		
		//show tasks for other projects
		$sql = " SELECT t.task_id, t.task_title, SUM(tr.spent_hours) AS task_spent_hours ";
		$sql.= " FROM tasks t ";
		$sql.= " INNER JOIN time_report tr ON (t.task_id = tr.task_id) ";
		$sql.= " INNER JOIN projects p ON (p.project_id = t.project_id) ";
		$sql.= " WHERE tr.user_id=".ToSQL($user_id, "integer");
		$sql.= " AND DATE_FORMAT(tr.report_date, '%Y')='$year_selected' AND DATE_FORMAT(tr.report_date, '%m')='$month_selected' ";
		$sql.= " AND t.project_id NOT IN (".ToSQL($viart_parent_project_id, "integer").",".ToSQL($custom_work_project_id, "integer").") ";
		$sql.= " AND p.parent_project_id NOT IN(".ToSQL($viart_parent_project_id, "integer");
		$sql.= ",".ToSQL($sayu_web_clients_parent_project_id, "integer").") ";
		$sql.= " GROUP BY t.task_id ORDER BY task_spent_hours DESC ";
		//echo "<BR><BR>".$sql;
		$users[$user_id]["other_projects"] = array();
		$db->query($sql);
		while($db->next_record()) {
			$users[$user_id]["other_projects"][] = $db->Record;
		}
		show_microtime("other projects");
	}
	
	//calculate working days and when they end
	$sql = " SELECT u.user_id, MAX(tr.report_date) AS workday_end ";
	$sql.= " FROM time_report tr ";
	$sql.= " INNER JOIN users u ON (tr.user_id=u.user_id AND (u.user_id=".ToSQL($team_manager_id, "integer")." OR u.manager_id=".ToSQL($team_manager_id, "integer").") ".$show_users_sql.") ";
	$sql.= " WHERE DATE_ADD(tr.report_date, INTERVAL 3 MONTH)>'".$year_selected."-".$month_selected."-01' ";
	$sql.= " 	AND DATE_SUB(tr.report_date, INTERVAL 1 MONTH)<='".$year_selected."-".$month_selected."-01' ";
	$sql.= " GROUP BY u.user_id, DAYOFYEAR(tr.report_date) ";
	$sql.= " ORDER BY u.user_id, tr.report_date ";
	$db->query($sql);
	while($db->next_record()) {
		$user_id = $db->f("user_id");
		list($workday, $workday_time) = datetime2time($db->f("workday_end"));
		$users[$user_id]["working_days"][$workday] = $workday_time;
	}
	
	$sql = " SELECT m.message_id, m.user_id, m.message_date, IF( pm.message_id IS NOT NULL , MAX( pm.message_date ) , t.creation_date ) AS previous_message_date ";
	$sql.= " FROM tasks t ";
	$sql.= " INNER JOIN messages m ON ( m.identity_id = t.task_id AND m.identity_type = 'task' AND m.user_id != m.responsible_user_id ";
	$sql.= " AND DATE_FORMAT( m.message_date, '%Y' ) = '".$year_selected."' ";
	$sql.= " AND DATE_FORMAT( m.message_date, '%m' ) = '".$month_selected."' ) ";
	$sql.= " INNER JOIN users u ON ( u.user_id = m.user_id AND (u.manager_id=".ToSQL($team_manager_id, "integer")." OR u.user_id=".ToSQL($team_manager_id, "integer").") ".$show_users_sql." ) ";
	$sql.= " LEFT JOIN messages pm ON ( pm.identity_type = 'task' AND pm.identity_id = t.task_id AND pm.responsible_user_id = m.user_id ";
	$sql.= " 	AND pm.user_id != m.user_id AND m.message_id > pm.message_id AND pm.user_reply IS NULL ) ";
	$sql.= " WHERE t.project_id =".ToSQL($ticket_project_id, "integer")." AND t.ticket_id IS NOT NULL ";
	$sql.= " GROUP BY u.user_id, t.task_id, m.message_id ";
	$sql.= " ORDER BY u.user_id ASC, t.task_id ASC, m.message_date ASC ";
	$db->query($sql);
	while($db->next_record()) {
		$user_id = $db->f("user_id");
		list($message_day, $message_time) = datetime2time($db->f("message_date"));
		list($previous_message_day, $previous_message_time) = datetime2time($db->f("previous_message_date"));
		$delayed_days = 0;
		
		if ($previous_message_day != $message_day) {
			$i=0;
			$previous_day = $message_day;
			do {
				$previous_day = get_previous_day($users[$user_id]["working_days"], $previous_day);
				$delayed_days++;
				if (isset($users[$user_id]["working_days"][$previous_day])) {
					if (($previous_day == $previous_message_day && sub_time($users[$user_id]["working_days"][$previous_day], "01:00:00") < $previous_message_time)
						|| ($previous_day < $previous_message_day)) {
						$delayed_days--;
					}					
				}
			} while ($previous_day > $previous_message_day && $previous_day);
		}
		
		if ($delayed_days == 0) {
			$users[$user_id]["tickets"]++;
			$users[$user_id]["tickets_points"] += $k_ticket_0;
		} else {
			$users[$user_id]["tickets_delayed"]++;
			if ($delayed_days == 1) {
				$users[$user_id]["tickets_delayed_points"] += $k_ticket_1;
			} elseif ($delayed_days == 2) {
				$users[$user_id]["tickets_delayed_points"] += $k_ticket_2;
			} else {
				$users[$user_id]["tickets_delayed_points"] += $k_ticket_more;
			}
		}
	}

	show_microtime("tickets");
	
	$sum_points = $sum_bugs = $sum_viart = $sum_sayu = $sum_custom_work = 0;
	$sum_tickets = $sum_tickets_delayed = $sum_tickets_points = $sum_tickets_delayed_points = $sum_paid_days = 0;
	
	foreach ($users as $user_id=>$user) {
		$points = $user["custom_work"] + $user["viart"] + $user["sayu"] + $user["bugs"] + $user["paid_days"] + $user["tickets_points"]  + $user["tickets_delayed_points"];
		$users[$user_id]["points"] = $points;
		$sum_points	+= $points;
		$sum_bugs	+= $user["bugs"];
		$sum_viart	+= $user["viart"];
		$sum_sayu	+= $user["sayu"];
		$sum_custom_work += $user["custom_work"];
		$sum_tickets += $user["tickets"];
		$sum_tickets_delayed += $user["tickets_delayed"];
		$sum_tickets_points += $user["tickets_points"];
		$sum_tickets_delayed_points += $user["tickets_delayed_points"];
		$sum_paid_days += $user["paid_days"];		
	}
	$t->set_var("sum_points", $sum_points);
	$t->set_var("sum_bugs", $sum_bugs);
	$t->set_var("sum_viart", $sum_viart);
	$t->set_var("sum_sayu", $sum_sayu);
	$t->set_var("sum_custom_work", $sum_custom_work);
	$t->set_var("sum_tickets", $sum_tickets);
	$t->set_var("sum_tickets_delayed", $sum_tickets_delayed);
	$t->set_var("sum_tickets_points", $sum_tickets_points);
	$t->set_var("sum_tickets_delayed_points", $sum_tickets_delayed_points);
	$t->set_var("sum_paid_days", $sum_paid_days);
	
	//calculate bonus/team points
	foreach ($users as $user_id=>$user) {
		if ($user["points"] > $bonus_user_thresold && $user["is_manager"] == 0) {
			
			$users[$user_id]["bonus"] = ($user["points"] - $bonus_user_thresold) * $bonus_user_coeff;
		} elseif ($user["is_manager"] == 1 && $sum_points > $bonus_team_thresold) {
			$users[$user_id]["bonus"] = ($sum_points - $bonus_team_thresold) * $bonus_manager_coeff;
		}
	}
	
	foreach ($users_ids as $user_id) {
		if (in_array($user_id, $show_users)) {
			
		$t->set_var("user_id", $user_id);
		if (isset($users[$user_id]) && is_array($users[$user_id])) {
			$t->set_var("user_name", $users[$user_id]["name"]);
			$t->set_var("viart", $users[$user_id]["viart"]);
			$t->set_var("sayu", $users[$user_id]["sayu"]);
			$t->set_var("custom_work", $users[$user_id]["custom_work"]);
			$t->set_var("tickets", $users[$user_id]["tickets"]);
			$t->set_var("tickets_delayed", $users[$user_id]["tickets_delayed"]);
			$t->set_var("tickets_points", $users[$user_id]["tickets_points"]);
			$t->set_var("tickets_delayed_points", $users[$user_id]["tickets_delayed_points"]);
			$t->set_var("bugs", $users[$user_id]["bugs"]);
			$t->set_var("points", $users[$user_id]["points"]);
			$t->set_var("bonus", number_format($users[$user_id]["bonus"],2));
			$t->set_var("paid_days", $users[$user_id]["paid_days"]);
			
			$t->set_var("not_rated_tasks", "");
			if (is_array($users[$user_id]["not_rated"]) && sizeof($users[$user_id]["not_rated"])) {
				foreach($users[$user_id]["not_rated"] as $task) {
					$t->set_var("task_id", $task["task_id"]);
					$t->set_var("task_title", $task["task_title"]);
					$t->set_var("spent_hours", Hours2HoursMins($task["task_spent_hours"]));
					$t->parse("not_rated_tasks", true);
				}
			}

			$t->set_var("other_projects", "");
			if (is_array($users[$user_id]["other_projects"]) && sizeof($users[$user_id]["other_projects"])) {
				foreach($users[$user_id]["other_projects"] as $task) {
					$t->set_var("task_id", $task["task_id"]);
					$t->set_var("task_title", $task["task_title"]);
					$t->set_var("spent_hours", Hours2HoursMins($task["task_spent_hours"]));
					$t->parse("other_projects", true);
				}
			}		
			
		}
		$t->parse("people", true);
		}
	}
	
	$sum_bonus = 0;
	foreach($users as $user) {
		$sum_bonus += $user["bonus"];
	}
	$t->set_var("sum_bonus", number_format($sum_bonus, 2));
	
	$t->pparse("main");
	
function getmicrotime(){ 
    	list($usec, $sec) = explode(" ",microtime()); 
    	return ((float)$usec + (float)$sec); 
    } 

function show_microtime($event) {
	global $initial;
/*    $fp = fopen("timing.txt","a");	    
    
    $from_start = ($initial ? (getmicrotime() - $initial) : 0);
	fwrite($fp,number_format($from_start,4).": ".$event."\n");	
    fclose($fp);*/
}
	
function datetime2time($datetime) {
	$datetime_arr = explode(" ",$datetime);
	if (is_array($datetime_arr) && sizeof($datetime_arr)==2) {
		$date = $datetime_arr[0];
		$time = $datetime_arr[1];
		return array($date, $time);
	} else {
		return array("","");
	}	
}

function get_previous_day($report_days, $current_day)
{
	$arr = true;
	$report_day = "0000-00-00";
	$previous_day = $report_day;
	reset($report_days);
	while ($arr && $report_day<$current_day) {	
		if (isset($arr) && isset($arr["key"])) {
			$previous_day = $arr["key"];
		}
		$arr = each($report_days);
		$report_day = $arr["key"];
		$report_time = $arr["value"];
		$next_elem = $arr;
	}
	return $previous_day;
}

function sub_time($time, $sub) {
	$time_arr = explode(":", $time);
	$sub_arr = explode(":", $sub);
	$res_arr = $time_arr;
	if (is_array($time_arr) && is_array($sub_arr) && sizeof($time_arr)==3 && sizeof($sub_arr)==3) {
		$res_arr[2] = $res_arr[2] - $sub_arr[2];
		if ($res_arr[2]<0) {
			$res_arr[1]--;
			$res_arr[2]+=60;
		}
		$res_arr[1] = $res_arr[1] - $sub_arr[1];
		if ($res_arr[1]<0) {
			$res_arr[0]--;
			$res_arr[1]+=60;
		}
		
		$res_arr[0] = $res_arr[0] - $sub_arr[0];
		if ($res_arr[0]<0) {
			$res_arr[0] = 0;
		}		
	}
	
	foreach ($res_arr as $key=>$res_val) {
		if ($res_arr[$key]<10) {
			$res_arr[$key] = "0".$res_arr[$key];
		} elseif ($res_arr[$key]==0) {
			$res_arr[$key] == "00";
		}
	}
	return implode(":", $res_arr);
}
?>