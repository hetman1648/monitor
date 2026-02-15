<?php
	include("./includes/common.php");
	
	$t = new iTemplate($sAppPath);
	$t->set_file("main", "quarterly_report.html");
	
	CheckSecurity(1);

	$total_months = (int) GetParam("total_months");
	$team_id_selected = (int) GetParam("team_id_selected");
	$year_selected    = (int) GetParam("year_selected");
	$month_selected   = (int) GetParam("month_selected");
	$submit = GetParam("submit");
	if (!$year_selected)  $year_selected = date("Y");
	if (!$total_months || $total_months > 24)   $total_months = 3;
	if (!$month_selected) $month_selected = date("m") - $total_months - 1;
	
	$start_month = $month_selected;
	$start_year  = $year_selected;	
	$start_date = "$start_year-$start_month";
	
	$end_month =  $start_month + $total_months - 1;
	$end_year  =  $start_year;
	if ($end_month > 12) {
		$end_month = 1;
		$end_year++;
	}
	$end_date    = "$end_year-$end_month";
			
	$t->set_var("months", GetMonthOptions($month_selected));
	$t->set_var("years", GetYearOptions(2004, date("Y"), $year_selected));
	
	$sql  = " SELECT t.team_id, t.team_name, COUNT(u.user_id) AS users_count ";
	$sql .= " FROM (users_teams t ";
	$sql .= " LEFT JOIN users u ON (u.manager_id = t.manager_id OR u.user_id = t.manager_id))";
	$sql .= " WHERE (u.is_deleted IS NULL OR u.is_deleted =0) ";
	$sql .= " GROUP BY t.team_id ";
	$sql .= " ORDER BY t.team_name";
	$db->query($sql, __FILE__, __LINE__);
	while ($db->next_record()) {
		$users_count = $db->f("users_count");
		if ($users_count > 1) {
			$team_id = (int)$db->f("team_id");	
			$t->set_var("team_id", $team_id);
			$t->set_var("team_name", $db->f("team_name"));
			$t->set_var("users_count", $users_count);
			if ($team_id_selected == $team_id) {
				$t->set_var("team_id_selected", "selected");
			} else {
				$t->set_var("team_id_selected", "");
			}		
			$t->parse("team_id_block",true);
		}
	}
	
	$t->set_var("start_date", $start_date);
	$t->set_var("end_date",   $end_date);
	$t->set_var("total_months", $total_months);
	
	
	if (strlen($submit) && $team_id_selected) {
		$sql  = " SELECT manager_id FROM users_teams WHERE team_id=" . $team_id_selected;
		$db->query($sql);
		if ($db->next_record()) {
			$manager_id = $db->f("manager_id");
			$sql  = " SELECT tr.user_id, t.task_id, t.task_title,";
			$sql .= " CONCAT(u.first_name,' ', u.last_name) as person, ";
			$sql .= " SUM(tr.spent_hours) AS sum_hours ";
			$sql .= " FROM ((tasks t ";
			$sql .= " INNER JOIN time_report tr ON tr.task_id=t.task_id)";
			$sql .= " INNER JOIN users u ON u.user_id = tr.user_id) ";
			$sql .= " WHERE tr.spent_hours>0 ";
			$sql .= " AND (u.manager_id = $manager_id OR u.user_id = $manager_id) ";
			$sql .= " AND tr.started_date>='$start_date-01 00:00:00' ";
			$sql .= " AND tr.started_date<='$end_date-31 23:59:59' ";
			$sql .= " GROUP BY tr.user_id, t.task_id ";
			$sql .= " ORDER BY tr.user_id, sum_hours DESC";
			$db->query($sql);
			$prev_user_id = 0;
			if($db->next_record()) {
				do {
					$user_id = $db->f("user_id");
					if ($prev_user_id != $user_id) {
						if ($prev_user_id) {
							$t->parse("user_block");
							$t->set_var("task_row", "");				
						}
						$t->set_var("user_id", $user_id);
						$t->set_var("user", $db->f("person"));
						$prev_user_id = $user_id;
					}
					
					$t->set_var("task_id", $db->f("task_id"));
					$t->set_var("task_title", $db->f("task_title"));
					$t->set_var("sum_hours", to_hours($db->f("sum_hours")));
					$t->parse("task_row");
				} while($db->next_record());
				$t->parse("user_block");
				$t->parse("records");
			} else {
				$t->set_var("records", "");				
			}
		} else {
			$t->set_var("records", "");
		}				
	} else {
		$t->set_var("records", "");
	}
	
	$t->pparse("main");
?>