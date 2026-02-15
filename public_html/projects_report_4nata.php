<?php
	include("./includes/common.php");
	
	$to_csv = GetParam("to_csv");
		
	$t = new iTemplate($sAppPath);
	if ($to_csv) {
		$t->set_file("main", "projects_report_4nata_csv.html");
	} else {
		$t->set_file("main", "projects_report_4nata.html");
	}
	
	CheckSecurity(1);

	$total_months = (int) GetParam("total_months");
	$team_id_selected = (int) GetParam("team_id_selected");
	if (!$team_id_selected) $team_id_selected = 2;
	$year_selected    = (int) GetParam("year_selected");
	$month_selected   = (int) GetParam("month_selected");
	$show_per_user    = (int) GetParam("show_per_user");
	$filter_task      = GetParam("filter_task");
	
	$project_ids = array();
	if (GetParam("project_id")) {
		foreach (GetParam("project_id") AS $project_id) {
			$project_ids[] = (int) $project_id;
		}
	}
	
	$submit = GetParam("submit");
	
	if (!$year_selected)  $year_selected = date("Y");
	if (!$total_months || $total_months > 24)   $total_months = 6;
	if (!$month_selected) $month_selected = date("m") - $total_months;
	
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

	$manager_id = 0;
	if ($team_id_selected) {
		$sql  = " SELECT manager_id FROM users_teams WHERE team_id=" . $team_id_selected;
		$db->query($sql);
		if ($db->next_record()) {
			$manager_id = $db->f("manager_id");
		}
	}
	
	if (!$to_csv) {
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
		if ($team_id_selected) {			
			$sql  =	" SELECT p.project_id, p.project_title FROM (((projects p ";
			$sql .= " INNER JOIN tasks t ON t.project_id=p.project_id) ";
			$sql .= " INNER JOIN time_report tr ON tr.task_id=t.task_id)";
			$sql .= " INNER JOIN users u ON u.user_id = tr.user_id) ";
			$sql .=	" WHERE p.is_closed=0 ";
			$sql .= " AND tr.spent_hours>0 ";
			$sql .= " AND (u.manager_id = $manager_id OR u.user_id = $manager_id) ";
			$sql .= " AND tr.started_date>='$start_date-01 00:00:00' ";
			$sql .= " AND tr.started_date<='$end_date-31 23:59:59' ";
			$sql .= " GROUP BY p.project_id";
			$sql .= " ORDER BY p.project_title";
		   	$db->query($sql);
		  	while ($db->next_record()) {
				$t->set_var("project_id",   $db->f("project_id"));
				$t->set_var("project_name", $db->f("project_title"));
				if (in_array($db->f("project_id"), $project_ids)) {
					$t->set_var("project_id_selected", "selected");
				} else {
					$t->set_var("project_id_selected", "");
				}		
				$t->parse("project_id_block",true);
			}
		} else {
			$t->set_var("project_id_block","");
		}
		
		$t->set_var("start_date",   $start_date);
		$t->set_var("end_date",     $end_date);
		$t->set_var("total_months", $total_months);
		$t->set_var("show_per_user_checked", $show_per_user ? "checked" : "");
		$t->set_var("filter_task",  $filter_task);
	} else {		
		$csv_filename = $start_date . "_". $end_date;
		if ($project_ids) {
			$csv_filename .= "_projects" . implode("_", $project_ids);
		}
		if ($filter_task) {
			$csv_filename .= "_" . str_replace(" ", "_", substr($filter_task, 0, 10));
		}
		if ($show_per_user) {
			$csv_filename .= "_with_users";
		}
		$csv_filename .= ".csv";
		header("Pragma: private");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: private", false);
		header("Content-Type: application/octet-stream");
		header("Content-Disposition: attachment; filename=" . $csv_filename ."");
		header("Content-Transfer-Encoding: binary");
	}
	
	if (strlen($submit) && $team_id_selected && $manager_id) {
		$t->set_var("total_columns", $users_count + 2);
			
		$sql  = " SELECT user_id, CONCAT(first_name,' ', last_name) as person ";
		$sql .= " FROM users ";
		$sql .= " WHERE (manager_id = $manager_id OR user_id = $manager_id) ";
		$sql .= " ORDER BY user_id";
		$db->query($sql);
			
		$users = array();	
		$t->set_var("user_col", "");
		$t->set_var("user_time_col", "");		
		while ($db->next_record()) {
			$users[] = $db->f("user_id");
			if ($show_per_user) {
				$t->set_var("person_name", $db->f("person"));
				$t->parse("user_col");
			}
		}
			
			
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
		if ($project_ids) {
			$sql .= " AND t.project_id IN (" . implode(",", $project_ids) . ")";				
		}
		if ($filter_task) {
			$sql .= " AND t.task_title LIKE '%" . ToSQL($filter_task, TEXT, false, false) . "%'";
		}
		$sql .= " GROUP BY tr.user_id, t.task_id ";
		$sql .= " ORDER BY t.task_id ";
		$db->query($sql);

		$tasks = array();
		if($db->next_record()) {
			do {
				$task_id = $db->f("task_id");
				$user_id = $db->f("user_id");
				$task_title = $db->f("task_title");
				$sum_hours  = $db->f("sum_hours");
				$tasks[$task_id]["title"] = $task_title;
				if (isset($tasks[$task_id]["users"][$user_id])) {
					$tasks[$task_id]["users"][$user_id] += $sum_hours;		
				} else {
					$tasks[$task_id]["users"][$user_id] = $sum_hours;
				}
			} while($db->next_record());
			
			foreach ($tasks AS $task_id => $task) {
				$t->set_var("task_id", $task_id);
				$t->set_var("task_title", $task["title"]);
				$total_hours = 0;
				foreach ($users AS $user_id) {
					if (isset($task["users"][$user_id])) {							
						$t->set_var("sum_hours", to_hours_hhmm($task["users"][$user_id]));
						$total_hours += $task["users"][$user_id];
					} else {
						$t->set_var("sum_hours", "");
					}
					if ($show_per_user) {
						$t->parse("user_time_col");
					}
				}
				$t->set_var("total_hours", to_hours_hhmm($total_hours));
					
				$t->parse("task_row");
				$t->set_var("user_time_col", "");
			}				
			
			$t->parse("records");
		} else {
			$t->set_var("records", "");				
		}						
	} else {
		$t->set_var("records", "");
	}
	
	$t->pparse("main");
	
	function to_hours_hhmm($float_hours) {
		$hours = floor($float_hours);
		$mins = round(($float_hours - $hours) * 60);
		if ($mins >= 60) {
			$hours++;
			$mins = $mins - 60;
		}
		if ($mins < 10) {
			$mins = "0" . $mins;
		}
		return $hours . ":" . $mins;
	}
?>