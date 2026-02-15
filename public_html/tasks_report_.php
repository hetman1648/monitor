<?php

	include("./includes/date_functions.php");
	include("./includes/common.php");
	
	CheckSecurity(1);
	
	$year_selected = GetParam("year_selected");
	$month_selected = GetParam("month_selected");
	$person_selected = GetParam("person_selected");
	$submit = GetParam("submit");	
	if (!$year_selected) $year_selected = date("Y");
	if (!$month_selected) $month_selected = date("m");
	$team = GetParam("team");
	
	switch (strtolower($team))
	{
	  case "all":	$sqlteam=""; $as="selected"; break;
	  case "viart":	$sqlteam=" AND u.is_viart=1 "; $vs="selected"; break;
	  case "yoonoo":$sqlteam=" AND u.is_viart=0 "; $ys="selected"; break;
	  default:	$sqlteam=" AND u.is_viart=1 "; $vs="selected"; $team="viart";
	}
	
	$t = new iTemplate($sAppPath);
	$t->set_file("main", "tasks_report.html");

	$t->set_var("months", GetMonthOptions($month_selected));
	$t->set_var("years", GetYearOptions(2004, date("Y"), $year_selected));
	$t->set_var("aselected",$as); $t->set_var("vselected",$vs); $t->set_var("yselected",$ys); $t->set_var("team_selected",$team);
	$t->set_var("person_selected",$person_selected ? $person_selected : "0");

	$people = "";
	$sql = "SELECT user_id, is_viart, CONCAT(first_name,' ', last_name) as person FROM users u WHERE is_deleted IS NULL ORDER BY person";
	$db->query($sql);
	$id=0;
	while ($db->next_record())
	{
		$t->set_var("ID",(int)$id++);
		$t->set_var("IDteam",(int)$db->f("is_viart"));
		$t->set_var("IDuser",(int)$db->f("user_id"));
		$t->set_var("user_name",$db->f("person"));
		$t->parse("PeopleArray",true);
	}
	$t->set_var("people", $people);
	
	//-- Number of days in month
	$n_days = date("t", mktime(0, 0, 0, $month_selected, 1, $year_selected));
	$t->set_var("num_cols", $n_days + 3);

	$sql = " SELECT CONCAT(u.first_name, ' ', u.last_name) AS user_name, u.user_id, t.task_title, tr.task_id, ";
	$sql .= " SUM(tr.spent_hours) AS sum_hours, DAYOFMONTH(tr.started_date) AS day_of_month ";
	$sql .= " FROM tasks t, time_report tr, users u ";
	$sql .= " WHERE tr.task_id=t.task_id AND tr.user_id=u.user_id ".$sqlteam;
	if ($year_selected) $sql .= " AND DATE_FORMAT(tr.started_date, '%Y')='$year_selected' ";
	if ($month_selected) $sql .= " AND DATE_FORMAT(tr.started_date, '%m')='$month_selected' ";
	if ($person_selected) $sql .= " AND tr.user_id=$person_selected ";
	$sql .= " GROUP BY u.user_id, t.task_id, day_of_month ";
	$sql .= " ORDER BY user_name, started_time";
	//echo $sql;
	$db->query($sql);
	
	$hours_ar = array();	
	while ($db->next_record()) {
		$user_id = $db->f("user_id");
		$task_id = $db->f("task_id");
		$day_of_month = $db->f("day_of_month");
		$sum_hours = $db->f("sum_hours");
		$hours_ar[$user_id][$task_id][$day_of_month] = $sum_hours;
	}
	
	$sql = " SELECT CONCAT(u.first_name, ' ', u.last_name) AS user_name, u.user_id, t.task_title, tr.task_id, ";
	$sql .= " SUM(tr.spent_hours) AS sum_hours ";
	$sql .= " FROM tasks t, time_report tr, users u ";
	$sql .= " WHERE tr.task_id=t.task_id AND tr.user_id=u.user_id ".$sqlteam;
	if ($year_selected) $sql .= " AND DATE_FORMAT(tr.started_date, '%Y')='$year_selected' ";
	if ($month_selected) $sql .= " AND DATE_FORMAT(tr.started_date, '%m')='$month_selected' ";
	if ($person_selected) $sql .= " AND tr.user_id=$person_selected ";
	$sql .= " GROUP BY u.user_id, t.task_id ";
	$sql .= " ORDER BY user_name, started_time";
	//echo $sql;
	$db->query($sql);

	$cur_user = "";
	$i_day = 0;
	if ($db->next_record()) {
		$t->set_var("no_records", "");
		for ($i_day=1; $i_day<=$n_days; $i_day++) {
			$t->set_var("i_day", $i_day);
			$t->parse("records_header_day", true);
		}
		$t->parse("records_header", false);
		do {
			$user_name = $db->f("user_name");
			$t->set_var("user_name", $user_name);
			if ($cur_user==$user_name) {
				$t->set_var("records_person", "");
			}
			else {
				$t->parse("records_person", false);
				$cur_user = $user_name;
			}
			$user_id = $db->f("user_id");
			$task_id = $db->f("task_id");
			$task_title = $db->f("task_title");
			$sum_hours = $db->f("sum_hours");

			$t->set_var("user_id", $user_id);
			$t->set_var("task_id", $task_id);
			$t->set_var("task_title", $task_title);
			$t->set_var("spent_hours", Hours2HoursMins($sum_hours));
			
			$records_days = "";
			for ($i_day=1; $i_day<=$n_days; $i_day++) {
				if (!isset($hours_ar[$user_id][$task_id][$i_day]) || !$hours_ar[$user_id][$task_id][$i_day]) {
					$day_of_week = date("w", mktime(0, 0, 0, $month_selected, $i_day, $year_selected));
					if ($day_of_week==0 || $day_of_week==6) $day_class = "DayoffTD"; //highlight Saturday and Sunday
					else $day_class = "DataTD";
				}
				elseif ($hours_ar[$user_id][$task_id][$i_day] <= 2) {
					$day_class = "Spent1";
				}
				elseif ($hours_ar[$user_id][$task_id][$i_day] <= 4) {
					$day_class = "Spent2";
				}
				elseif ($hours_ar[$user_id][$task_id][$i_day] <= 6) {
					$day_class = "Spent3";
				}
				else {
					$day_class = "Spent4";
				}
				$records_days .= "<td class=\"$day_class\" nowrap></td>";
				$t->set_var("records_days", $records_days);
			}
			
			$t->set_var("year_selected", $year_selected);
			$t->set_var("month_selected", $month_selected);
				
			$t->parse("records", true);
		} while ($db->next_record());
	}
	else
	{
		$t->set_var("records_header", "");
		$t->set_var("records", "");
		$t->parse("no_records", false);
	} //if ($db->next_record())

	$t->parse("result", false);
	
	$t->pparse("main");
?>