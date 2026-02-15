<?php

	include("./includes/date_functions.php");
	include("./includes/common.php");

	CheckSecurity(1);

	$period		= GetParam("period");
	$start_date	= GetParam("start_date");
	$end_date	= GetParam("end_date");
	$keyword	= GetParam("keyword");
	$closed		= GetParam("closed");
	$submit		= GetParam("submit");
	$person_selected	= GetParam("person_selected");
	$project_selected	= GetParam("project_selected");

	$t = new iTemplate($sAppPath);
	$t->set_file("main","search.html");

	$t->set_var("period", $period);


	$current_date = va_time();

	$today_date = date ("Y-m-d");
	$t->set_var("today_date", $today_date);

	$cyear = $current_date[0]; $cmonth = $current_date[1]; $cday = $current_date[2];
	$yesterday_date = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday - 1, $cyear));
	$t->set_var("yesterday_date", $yesterday_date);

	$week_start_date = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday - 7, $cyear));
	$week_end_date   = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday - 1, $cyear));
	$t->set_var("week_start_date", $week_start_date);
	$t->set_var("week_end_date", $week_end_date);

	$month_start_date = date ("Y-m-d", mktime (0, 0, 0, $cmonth - 1, 1, $cyear));
	$month_end_date   = date ("Y-m-t", mktime (0, 0, 0, $cmonth - 1, 1, $cyear));
	$t->set_var("month_start_date", $month_start_date);
	$t->set_var("month_end_date", $month_end_date);

	$this_month_start = date ("Y-m-d", mktime (0, 0, 0, $cmonth, 1, $cyear));
	$this_month_end   = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday - 1, $cyear));
	$t->set_var("this_month_start", $this_month_start);
	$t->set_var("this_month_end", $this_month_end);

	if (!$start_date && !$end_date) {
		switch ($period) {
			case 1:
				$start_date = $today_date;
				$end_date = $today_date;
				break;
			case 2:
				$start_date = $yesterday_date;
				$end_date = $yesterday_date;
				break;
			case 3:
				$start_date = $week_start_date;
				$end_date = $week_end_date;
				break;
			case 4:
				$start_date = $month_start_date;
				$end_date = $month_end_date;
				break;
			case 5:
				$start_date = $this_month_start;
				$end_date = $this_month_end;
				break;
		}
	}

	if ($start_date) {
		$sd_ar	= parse_date(array("YYYY", "-", "MM", "-", "DD"), $start_date, "Start Date");
		$sd_ts	= mktime (0, 0, 0, $sd_ar[1], $sd_ar[2], $sd_ar[0]);
		$sdt_ts = mktime (0, 0, 0, $sd_ar[1], $sd_ar[2], $sd_ar[0]);
		$sd		= @date ("Y-m-d", $sd_ts);
		$sdt	= @date ("Y-m-d 00:00:00", $sd_ts);
	}

	if ($end_date) {
		$ed_ar	= parse_date(array("YYYY", "-", "MM", "-", "DD"), $end_date, "End Date");
		$ed_ts	= mktime (0, 0, 0, $ed_ar[1], $ed_ar[2], $ed_ar[0]);
		$ed		= @date ("Y-m-d", $ed_ts);
		$edt	= @date ("Y-m-d 23:59:59", $ed_ts);
 	}

 	if (isset($sd)) {
 		$t->set_var("start_date", $sd);
 	} else {
 		$t->set_var("start_date", "");
 	}
 	if (isset($ed)) {
		$t->set_var("end_date", $ed);
	} else {
		$t->set_var("end_date", "");
	}
	$t->set_var("keyword", $keyword);
	if ($closed) { $t->set_var("checked", "checked");}
		else { $t->set_var("checked", "");}

 	$persons = "";
	$sql = " SELECT user_id, CONCAT(first_name,' ', last_name) as person FROM users ORDER BY person ";
	$db->query($sql,__FILE__,__LINE__);
	while ($db->next_record())	{
		$person = $db->f("person");
		$person_nom = $db->f("user_id");
		if ($person_selected==$person_nom) { $persons .= "<option selected value='$person_nom'>$person</option>";}
	    else { $persons .= "<option value='$person_nom'>$person</option>";}
	}
	$t->set_var("persons", $persons);
	$t->set_var("projects", GetOptions("projects", "project_id", "project_title", $project_selected));

	if ($submit) {
		if ($start_date || $end_date || $person_selected || $project_selected || $keyword || (!$closed)) {
			$sql = " SELECT p.project_title, t.task_id, t.task_title, CONCAT(u.first_name, ' ', u.last_name) as user_name, ";
			$sql .= " t.priority_id, lts.status_desc, t.estimated_hours, t.actual_hours, t.creation_date, t.planed_date, ";
			$sql .= " t.is_closed, u.user_id, ";
			$sql .= " DATE_FORMAT(t.creation_date, '%d %b %Y') AS creat_date, DATE_FORMAT(t.planed_date, '%d %b %Y') AS plan_date, ";
			$sql .= " DATE_FORMAT(t.creation_date, '%Y %m %d') AS datesort ";
			$sql .= " FROM tasks t, users u, projects p, lookup_tasks_statuses lts ";
			$sql .= " WHERE t.responsible_user_id=u.user_id AND t.project_id=p.project_id AND t.task_status_id=lts.status_id ";
			if (isset($sdt) && $sdt) { $sql .= " AND t.creation_date>='$sdt' ";}
			if (isset($edt) && $edt) { $sql .= " AND t.creation_date<='$edt' ";}
			if ($project_selected && is_number($project_selected)) { $sql .= " AND t.project_id='$project_selected' ";}
			if ($person_selected && is_number($person_selected)) { $sql .= " AND t.responsible_user_id='$person_selected' ";}
			if ($keyword) {
				$keywords = explode(" ", $keyword);
				$sql_t = "";
				$sql_d = "";
				foreach($keywords as $value) {
					if ($sql_t) { $sql_t .= " AND t.task_title LIKE '%$value%'";}
					else { $sql_t = "t.task_title LIKE '%$value%'";}
					if ($sql_d) { $sql_d .= " AND t.task_desc LIKE '%$value%'";}
					else { $sql_d = "t.task_desc LIKE '%$value%'";}
				}
				$sql .= " AND ((" . $sql_t . ") OR (" . $sql_d . "))";
			}
			if (!$closed) { $sql .= " AND t.is_closed=0 ";}
			$sql .= " ORDER BY project_title, user_name, datesort ";
			$db->query($sql,__FILE__,__LINE__);
			//echo $sql;


			if ($db->next_record()) {
				$t->set_var("no_records", "");
				do {
					$project_title	= $db->f("project_title");
					$complete_by	= $db->f("plan_date");
					$task_title	= $db->f("task_title");
					$task_id	= $db->f("task_id");
					$user_name	= $db->f("user_name");
					$user_id	= $db->f("user_id");
					$priority	= $db->f("priority_id");
					$status		= $db->f("status_desc");
					$estimate	= $db->f("estimated_hours");
					$actual		= $db->f("actual_hours");
					$created	= $db->f("creat_date");
					$is_closed	= $db->f("is_closed");

					$t->set_var("project_title", $project_title);
					$t->set_var("task_title", $task_title);
					$t->set_var("task_id", $task_id);
					$url = "<a href='report_people.php?report_user_id=".$user_id."'>".$user_name."</a>";
					$t->set_var("user_name", $url);
					$t->set_var("priority", $priority);
					$t->set_var("status", $status);
					$t->set_var("estimate", $estimate ? Hours2HoursMins($estimate) : "");
					$t->set_var("actual", Hours2HoursMins($actual));
					$t->set_var("created", $created);
					$t->set_var("complete_by", $complete_by);
					if ($is_closed) { $t->set_var("is_closed", "Yes");}
					else {$t->set_var("is_closed", "No");}

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
		}
		else {
			$t->set_var("result", "");
			echo "<font style=\"font-size:12pt; color:#ff0000; font-weight:bold\"><center>Enter search criteria, please</center></font>";
		}//if ($start_date || $end_date || $person_selected || $project_selected || $keyword || (!$closed))
	}
	else {
		$t->set_var("result", "");
	} // if ($submit)

	$t->pparse("main");
?>