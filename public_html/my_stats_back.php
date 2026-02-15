<?php

include_once("./includes/common.php");
include_once("./includes/date_functions.php");

CheckSecurity(1);

$db2 = new DB_Sql;
$db2->Database = DATABASE_NAME;
$db2->User     = DATABASE_USER;
$db2->Password = DATABASE_PASSWORD;
$db2->Host     = DATABASE_HOST;

$db3 = new DB_Sql;
$db3->Database = DATABASE_NAME;
$db3->User     = DATABASE_USER;
$db3->Password = DATABASE_PASSWORD;
$db3->Host     = DATABASE_HOST;

$db4 = new DB_Sql;
$db4->Database = DATABASE_NAME;
$db4->User     = DATABASE_USER;
$db4->Password = DATABASE_PASSWORD;
$db4->Host     = DATABASE_HOST;

$db6 = new DB_Sql;
$db6->Database = DATABASE_NAME;
$db6->User     = DATABASE_USER;
$db6->Password = DATABASE_PASSWORD;
$db6->Host     = DATABASE_HOST;

$person_selected	= GetParam("person_selected");
$year_selected		= GetParam("year_selected");
$month_selected		= GetParam("month_selected");
$task_report		= GetParam("task_report");
$start_date			= GetParam("start_date");
$end_date			= GetParam("end_date");
//$sqlteam			= GetParam("sqlteam");

$user_id = GetSessionParam("UserID");;
if (!$year_selected) { $year_selected = date("Y");}
if (!$month_selected) {	$month_selected = date("m");}

$t = new iTemplate($sAppPath);
$t->set_file("main", "my_stats.html");

$t->set_var("user_id", $user_id);
$t->set_var("months", GetMonthOptions($month_selected));
$t->set_var("years", GetYearOptions(2004, date("Y"), $year_selected));
//$t->set_var("aselected", $as);
//$t->set_var("vselected", $vs);
//$t->set_var("yselected", $ys);

if ($year_selected && $month_selected)
{
	//summary report
	$working_days_2 = 0;
	$nowday = getdate();
	$nowday_day		= $nowday["mday"];
	$nowday_year	= $nowday["year"];
	$nowday_mon		= $nowday["mon"];
	$n_days = date("t", mktime(0, 0, 0, $month_selected, 1, $year_selected));
	if ($year_selected == $nowday_year && $month_selected == $nowday_mon) {
		for ($i=1; $i<= $nowday_day; $i++) {
			$week_day = date("w", mktime(0, 0, 0, $nowday_mon, $i, $nowday_year));
			if ($week_day != 0 && $week_day != 6) { $working_days_2++;}
		}
	} else {
		for ($i=1; $i<=$n_days; $i++) {
			//	echo $nowday_mon." - ".$i." - ".$nowday_year."<br>";
			$week_day = date("w", mktime(0, 0, 0, $month_selected, $i, $year_selected));
			if ($week_day != 0 && $week_day != 6) $working_days_2++;
			//	echo $i." - ".$week_day."<br>";
		}
	}
	$holiday_quant = 0;
	$holidays_arr = array();
	$sql6 = " SELECT COUNT(holiday_id) AS hq";
	$sql6 .= " FROM national_holidays";
	$sql6 .= " WHERE WEEKDAY(holiday_date)<=4";
	if ($year_selected) $sql6 .= " AND DATE_FORMAT(holiday_date, '%Y')='$year_selected' ";
	if ($month_selected) $sql6 .= " AND DATE_FORMAT(holiday_date, '%m')='$month_selected' ";
	if ($month_selected==$nowday_mon && $year_selected==$nowday_year) {
		$sql6 .= " AND holiday_date<CURDATE()";
	}
	$db6->query($sql6);
	if ($db6->next_record()) {
		$h=0;
		do {
			//echo  "qq".$db6->f("hq")."<br>";
			$holiday_quant = $db6->f("hq");
			$holidays_arr[$h] = $db6->f("holiday_date"); 
			$h++;
		} while ($db6->next_record());
	} else  { $holiday_quant=0;}
	
	$sql6 = " SELECT holiday_date FROM national_holidays WHERE WEEKDAY(holiday_date)<=4";
	if ($year_selected) $sql6 .= " AND DATE_FORMAT(holiday_date, '%Y')='$year_selected' ";
	if ($month_selected) $sql6 .= " AND DATE_FORMAT(holiday_date, '%m')='$month_selected' ";
	if ($month_selected==$nowday_mon && $year_selected==$nowday_year) {
		$sql6 .= " AND holiday_date<CURDATE()";
	}
	$db6->query($sql6);
	if ($db6->next_record()) {
		$h=0;
		do {
			$holidays_arr[$h] = $db6->f("holiday_date"); 
			$h++;
		} while ($db6->next_record());
	}

	$sql6 = " SELECT CONCAT(u.first_name, ' ', u.last_name) AS user_name, ";
	$sql6 .= " COUNT(DISTINCT DATE_FORMAT(tr.started_date, '%Y %m %d')) AS work_days ";
	$sql6 .= " FROM time_report tr, users u";
	//$sql6 .= " WHERE WEEKDAY(tr.started_date)<=4 AND tr.user_id='$user_id' AND tr.user_id=u.user_id ";
	$sql6 .= " WHERE WEEKDAY(tr.started_date)<=4 AND tr.user_id=u.user_id ";
	if ($year_selected) $sql6 .= " AND DATE_FORMAT(tr.started_date, '%Y')='$year_selected' ";
	if ($month_selected) $sql6 .= " AND DATE_FORMAT(tr.started_date, '%m')='$month_selected' ";
	$sql6 .= " GROUP BY user_name ";
	$db6->query($sql6);
	//-- Number of work days that person worked during month
	$work_days = array();
	while ($db6->next_record()) {
		$work_days[$db6->f("user_name")] = $db6->f("work_days");
	}

	$sql6 = " SELECT CONCAT(u.first_name, ' ', u.last_name) AS user_name, u.user_id, ";
	$sql6 .= " SUM(tr.spent_hours) AS count_hours, COUNT(DISTINCT tr.task_id) AS count_tasks, ";
	$sql6 .= " COUNT(DISTINCT DATE_FORMAT(tr.started_date, '%Y %m %d')) AS working_days ";
	$sql6 .= " FROM time_report tr, users u ";
	$sql6 .= " WHERE tr.user_id='$user_id' AND tr.user_id=u.user_id ";
	//$sql6 .= " WHERE tr.user_id=u.user_id ";
	if ($year_selected) $sql6 .= " AND DATE_FORMAT(tr.started_date, '%Y')='$year_selected' ";
	if ($month_selected) $sql6 .= " AND DATE_FORMAT(tr.started_date, '%m')='$month_selected' ";
	$sql6 .= " GROUP BY user_name ";
	$sql6 .= " ORDER BY user_name ";
	$db6->query($sql6);




	if ($db6->next_record()) {
		$t->set_var("no_sum_report", "");
		do {
			$user_name = $db6->f("user_name");
			//	$user_id = $db6->f("user_id");
			$count_hours = $db6->f("count_hours");
			$count_tasks = $db6->f("count_tasks");
			if ($count_tasks != 0) $time_per_task = $count_hours/$count_tasks; else $time_per_task = 0;
			//-- Number of days that person worked during month
			$working_days = $db6->f("working_days");
			//	echo "working_days".$working_days."<br>";
			//	echo "work_days[user_name]".$work_days[$user_name]."<br>";
			if ($working_days != 0) $hours_per_day = $count_hours/$working_days; else $hours_per_day = 0;
			$days_off = $db6->f("working_days") - $work_days[$user_name];

			$t->set_var("user_name", $user_name);
			$t->set_var("user_id", $user_id);
			$t->set_var("spent_hours", Hours2HoursMins($count_hours));
			$t->set_var("tasks", $count_tasks);
			$t->set_var("time_per_task", Hours2HoursMins($time_per_task));
			$t->set_var("hours_per_day", Hours2HoursMins($hours_per_day));
			$t->set_var("working_days", $working_days);
			$t->set_var("days_off", $days_off);
			$t->set_var("year_selected", $year_selected);
			$t->set_var("month_selected", $month_selected);

			//average values
			$working_days = 0;
			$i=0;
			$count_hours = 0;
			$count_tasks = 0;
			$days_off = 0;
			$days_off_max = 0;
			$sql6 = " SELECT CONCAT(u.first_name, ' ', u.last_name) AS user_name, u.user_id, ";
			$sql6 .= " SUM(tr.spent_hours) AS count_hours, COUNT(DISTINCT tr.task_id) AS count_tasks, ";
			$sql6 .= " COUNT(DISTINCT DATE_FORMAT(tr.started_date, '%Y %m %d')) AS working_days ";
			$sql6 .= " FROM time_report tr, users u ";
			$sql6 .= " WHERE tr.user_id=u.user_id ";
			if ($year_selected) $sql6 .= " AND DATE_FORMAT(tr.started_date, '%Y')='$year_selected' ";
			if ($month_selected) $sql6 .= " AND DATE_FORMAT(tr.started_date, '%m')='$month_selected' ";
			$sql6 .= " GROUP BY user_name ";
			$sql6 .= " ORDER BY user_name ";
			$db6->query($sql6);
			if ($db6->next_record())
			{
				do {
					$user_name = $db6->f("user_name");
					//	if ($working_days <=  $db6->f("working_days")) $working_days = $db6->f("working_days");
					if ($working_days <=  $work_days[$user_name]) $working_days = $work_days[$user_name];
					if ($days_off_max <= ($db6->f("working_days") - $work_days[$user_name]))
					$days_off_max = $db6->f("working_days") - $work_days[$user_name];
					//$working_days = $working_days + $db6->f("working_days");
					$count_hours = $count_hours + $db6->f("count_hours");
					$count_tasks = $count_tasks + $db6->f("count_tasks");
					$days_off = $days_off + ($db6->f("working_days") - $work_days[$user_name]);
					//	echo $user_name. "   ".$days_off."<br>";
					$i++;
				}
				while ($db6->next_record());
			}
			$user_days_off = 0;
			$sql6 = "SELECT * FROM days_off WHERE is_paid='0' AND user_id=".$user_id;
			$sql6 .= " AND (DATE_FORMAT(start_date, '%m')='$month_selected' ";
			$sql6 .= " OR DATE_FORMAT(end_date, '%m')='$month_selected')";
			$sql6 .= " AND DATE_FORMAT(end_date, '%Y')='$year_selected' AND reason_id!=4";
			$db6->query($sql6);
			//echo $sql6;
			if ($db6->next_record())
			{
					do 
					{
						$start_array = explode("-",$db6->f("start_date"));
						$end_array = explode("-",$db6->f("end_date"));
						
						$month_start = getdate(mktime(0,0,0,$start_array[1],$start_array[2],$start_array[0]));
						$month_end = getdate(mktime(0,0,0,$end_array[1],$end_array[2],$end_array[0]));
						
						if ($month_start["mon"]==$month_end["mon"])
						{
							$user_days_off += $db6->f("total_days");
						}
						else 
						{
							if ($month_start["mon"] == $month_selected)
							{
								$next_month = $month_start['mon']+1;
								$last_day = date("d", strtotime("-1 day", strtotime(date("Y-".$next_month."-01"))));
								//$user_days_off += $last_day - $month_start["day"];
								$begin_day = $month_start["mday"];
								while ($begin_day<=$last_day)
								{
									$this_date = getdate(mktime(0,0,0,$month_start["mon"],$begin_day,$month_start["year"]));
									$curr_date = date('Y-m-d',mktime(0,0,0,$month_start["mon"],$begin_day,$month_start["year"]));
									if ($this_date['wday']!='0' && $this_date['wday']!='6') 
									{
										$flag=0;
										foreach($holidays_arr as $holiday_day)
										{
											
											if($holiday_day == $curr_date)
											{
												$flag = 1;
												break;	
											}
										}
										
										if (!$flag) $user_days_off++;
									}
									$begin_day++;
								}
							}
							elseif ($month_end["mon"] == $month_selected)
							{
								$begin_day = 1;
								while ($begin_day<=$month_end['mday'])
								{
									$this_date = getdate(mktime(0,0,0,$month_end["mon"],$begin_day,$month_end["year"]));
									$curr_date = date('Y-m-d',mktime(0,0,0,$month_end["mon"],$begin_day,$month_end["year"]));
									//echo $this_date['wday']." - ".$this_date['mday']."<br>";
									if ($this_date['wday']!='0' && $this_date['wday']!='6') 
									{
										$flag=0;
										foreach($holidays_arr as $holiday_day)
										{
											
											if($holiday_day == $curr_date)
											{
												$flag = 1;
												break;	
											}
										}
										
										if (!$flag) $user_days_off++;
									}
									$begin_day++;
								}
							}
						}
					}
					while ($db6->next_record());
			}
			//echo $user_days_off;
			//$count_hours = $db6->f("count_hours");
			//$count_tasks = $db6->f("count_tasks");
			//$count_users = $db6->f("count_users");
			$average_hours = $count_hours/$i;
			$average_tasks = round($count_tasks/$i, 0);
			$average_time_per_task = $average_hours/$average_tasks;
			//$average_working_days = round($working_days/$i,0);
			//$average_hours_per_day = $average_hours/$average_working_days;
			$average_days_off = round($days_off/$i,0);


			//$average_working_days = $working_days - $holiday_quant;
			$average_working_days = $working_days_2 - $holiday_quant - $user_days_off;
			$average_hours = 8*$average_working_days;

			//$t->set_var("average_hours", Hours2HoursMins($average_hours));
			$t->set_var("average_hours", $average_hours . ":00");
			$t->set_var("average_tasks", $average_tasks . " (avg)");
			$t->set_var("average_time_per_task", Hours2HoursMins($average_time_per_task) . " (avg)");
			//$t->set_var("average_working_days", $average_working_days);
			$t->set_var("average_working_days", $average_working_days);
			//$t->set_var("average_hours_per_day", Hours2HoursMins($average_hours_per_day));
			$t->set_var("average_hours_per_day", "8:00");
			//$t->set_var("average_days_off", $average_days_off);
			$t->set_var("average_days_off", "-");

			$days_off = $db6->f("working_days") - $work_days[$user_name];

			$t->parse("sum_report", true);
		} while ($db6->next_record());
	}
	else
	{
		//$t->set_var("sum_report_header", "");
		$t->set_var("sum_report", "");
		$t->parse("no_sum_report", false);
	}

	// working days
	$sql = " SELECT DAYOFMONTH(tr.started_date) AS day_of_month, SUM(tr.spent_hours) AS sum_hours, ";
	$sql .= " CONCAT(u.first_name, ' ', u.last_name) AS user_name ";
	$sql .= " FROM time_report tr, users u ";
	$sql .= " WHERE tr.user_id='$user_id' AND tr.user_id=u.user_id ";
	$sql .= " AND DATE_FORMAT(tr.started_date, '%Y')='$year_selected' ";
	$sql .= " AND DATE_FORMAT(tr.started_date, '%m')='$month_selected' ";
	$sql .= " GROUP BY day_of_month ";
	$db->query($sql,__FILE__,__LINE__);


	//-- Days when person worked
	$attended_dates = array();
	$spent_hours = array();
	if ($db->next_record()) {
		do {
			$attended_dates[$db->f("day_of_month")] = 1;
			$spent_hours[$db->f("day_of_month")] = $db->f("sum_hours");
			$user_name = $db->f("user_name");
			$t->set_var("user_name", $user_name);
		}
		while ($db->next_record());


		// for holidays
		$sql = " SELECT holiday_date FROM national_holidays ";
		$sql .= " WHERE DATE_FORMAT(holiday_date, '%Y')='$year_selected' ";
		$sql .= " AND DATE_FORMAT(holiday_date, '%m')='$month_selected' ";
		$db->query($sql);
		if  ($db->next_record()){
			do
			{
				$holiday_date = $db->f("holiday_date");
				$holiday_date_arr = explode("-",$holiday_date);
				$day_of_hol = (integer)$holiday_date_arr[2];

				$holiday_dates[$day_of_hol] = 1;
			}
			while ($db->next_record());
		}
		// end for holidays


		//-- Number of day in month
		$n_days = date("t", mktime(0, 0, 0, $month_selected, 1, $year_selected));
		//-- First day of month 1..7 - Monday..Sunday
		$first_day = date("w", mktime(0, 0, 0, $month_selected, 1, $year_selected));
		if ($first_day == 0) { $first_day = 7;}
		//-- Number of weeks in month, even with only 1 day
		//$n_weeks = date("W", mktime(0, 0, 0, $month_selected, $n_days, $year_selected)) - date("W", mktime(0, 0, 0, $month_selected, 1, $year_selected)) + 1;
		$week_last_day = 1+(date("z", mktime(0, 0, 0, $month_selected, $n_days, $year_selected))+1)/7;
		$week_first_day = 1+(date("z", mktime(0, 0, 0, $month_selected, 1, $year_selected))+1)/7;
		$n_weeks = floor($week_last_day) - floor($week_first_day) + 1;
		

		if ($n_weeks < 0) { $n_weeks += date("W", mktime(0, 0, 0, $month_selected, 1, $year_selected));}

		$cur_day = 1;
		$week_hours = 0;
		$month_hours = 0;
		$w_working_days = 0;
		for ($row = 1; $row <= $n_weeks; $row++) {
			for ($col = 1; $col <= 7; $col++) {
				if (($row == 1 && $col >= $first_day) || ($row > 1 && $cur_day <=$n_days)) {
					$sd = date ("Y-m-d", mktime (0, 0, 0, $month_selected, $cur_day, $year_selected));
					$link = "<a href=\"my_stats.php?year_selected=$year_selected&month_selected=$month_selected&task_report=1&user_id=$user_id&start_date=$sd&end_date=$sd\">$cur_day</a>";
					$t->set_var("day".$col, $link);
					if (isset($attended_dates[$cur_day]) && $attended_dates[$cur_day] == 1) {
						if (isset($holiday_dates[$cur_day])) $t->set_var("day_color".$col, "#EECF63");
						else $t->set_var("day_color".$col, "#7FC5F4");
						$week_hours += $spent_hours[$cur_day];
						$w_working_days++;
					}
					elseif (isset($holiday_dates[$cur_day])) {$t->set_var("day_color".$col, "#EECF63"); $cur_day++; continue;}
					elseif ($col > 5) $t->set_var("day_color".$col, "#C5F4C5");
					else $t->set_var("day_color".$col, "#FAFAFA");
					$cur_day++;
				}
				else {
					$t->set_var("day".$col, "");
					$t->set_var("day_color".$col, "#FAFAFA");
				}
				if ($col == 7) {
					$t->set_var("week_hours", Hours2HoursMins($week_hours));
					$t->set_var("week_color", "#FAFAFA");
					if ($w_working_days != 0) $w_hours_per_day = $week_hours/$w_working_days; else $w_hours_per_day = 0;
					$t->set_var("w_hours_per_day", Hours2HoursMins($w_hours_per_day));
					$month_hours += $week_hours;
					$week_hours = 0;
					$w_working_days = 0;
				}
			}
			$t->parse("week", true);
			$t->set_var("month_hours", Hours2HoursMins($month_hours));
		}

		$t->parse("calendar", false);
		$t->set_var("no_working", "");
	}

	else {
		$t->set_var("calendar", "");
		$t->parse("no_working", false);
	}

	// warnings
	$sql2 = "SELECT * FROM warnings WHERE user_id = ".$user_id;
	$sql2 .= " AND DATE_FORMAT(date_added, '%Y')='$year_selected' ";
	$sql2 .= "AND DATE_FORMAT(date_added, '%m')='$month_selected' ";
	$db2->query($sql2);
	if ($db2->next_record()) {
		do {
			$date_added = ($db2->f("date_added")?norm_sql_date($db2->f("date_added")):$db2->f("date_added"));
			$notes = $db2->f("description");
			$sql3 = "SELECT CONCAT(first_name, ' ', last_name) AS user_name FROM users WHERE user_id = " . ToSQL($db2->Record["admin_user_id"],"int");
			$db3->query($sql3);
			$db3->next_record();
			$creator = $db3->f("user_name");

			$t->set_var("user_name", $user_name);
			$t->set_var("date_added", $date_added);
			$t->set_var("notes", $notes);
			$t->set_var("creator", $creator);
			$t->parse("warning_td", true);

		}
		while ($db2->next_record());
		$t->parse("warning_header",false);
		$t->set_var("no_warning","");
	}
	else {
		$t->parse("no_warning",false);
		$t->set_var("warning_td", "");
		$t->set_var("warning_header");
	}

	// vacations
	$sql4 = "SELECT do.*, u.first_name, u.last_name, r.reason_name ";
	$sql4 .= " FROM days_off do ";
	$sql4 .= " INNER JOIN users u ON u.user_id = do.user_id ";
	$sql4 .= " INNER JOIN reasons r ON r.reason_id = do.reason_id ";
	$sql4 .= " WHERE do.user_id = " . ToSQL($user_id, "integer");
	$sql4 .= " ORDER BY start_date";
	$db4->query($sql4);
	if ($db4->next_record()) {
		do {
			$user_name = $db4->Record["first_name"] . " " . $db4->Record["last_name"];
			$reason_type = $db4->Record["reason_name"];
			$t->set_var("user_name", $user_name);

			$t->set_var("period_title", "<a href=create_vacation.php?vacation_id=" . $db4->Record["period_id"] . ">" . $db4->Record["period_title"] . "</a>");
			$t->set_var("reason_type", $reason_type);
			$temp_start_date = ($db4->Record["start_date"]?norm_sql_date($db4->Record["start_date"]):$db4->Record["start_date"]);
			$temp_end_date = ($db4->Record["end_date"]?norm_sql_date($db4->Record["end_date"]):$db4->Record["end_date"]);
			$t->set_var("start_date", $temp_start_date);
			$t->set_var("end_date", $temp_end_date);
			$t->set_var("total_days", $db4->Record["total_days"]);
			$t->parse("vacation_td", true);
		} while ($db4->next_record());
	} else {
		$t->set_var("reason_type", "");
		$t->set_var("start_date", "");
		$t->set_var("end_date", "");
		$t->set_var("total_days", "");
		$t->set_var("period_title", "");
		$t->set_var("user_name", "No vacations on the selected period");
	}

	// task report
	if ($task_report)
	{
		//    	if ($start_date) {
		$sd_ar = parse_date(array("YYYY", "-", "MM", "-", "DD"), $start_date, "Start Date");
		$sd_ts = mktime (0, 0, 0, $sd_ar[1], $sd_ar[2], $sd_ar[0]);
		$sdt_ts = mktime (0, 0, 0, $sd_ar[1], $sd_ar[2], $sd_ar[0]);
		$sd = @date("Y-m-d", $sd_ts);
		$sdt = @date("Y-m-d 00:00:00", $sd_ts);
		//	}
		//if ($end_date) {
		$ed_ar = parse_date(array("YYYY", "-", "MM", "-", "DD"), $end_date, "End Date");
		$ed_ts = mktime (0, 0, 0, $ed_ar[1], $ed_ar[2], $ed_ar[0]);
		$ed = @date("Y-m-d", $ed_ts);
		$edt = @date("Y-m-d 23:59:59", $ed_ts);
		// 	}

		$sql = " SELECT CONCAT(u.first_name, ' ', u.last_name) as person, tr.started_date AS datesort, tr.report_date, ";
		$sql .= " DATE_FORMAT(tr.started_date, '%d %b %Y - %W') AS date, DATE_FORMAT(tr.report_date, '%d %b %Y - %W') AS rdate, ";
		$sql .= " t.task_title, tr.task_id, tr.spent_hours, ";
		$sql .= " UNIX_TIMESTAMP(tr.started_date) as start_time_u, UNIX_TIMESTAMP(tr.report_date) as end_time_u ";
		$sql .= " FROM users u, time_report tr, tasks t ";
		$sql .= " WHERE u.user_id=tr.user_id AND t.task_id=tr.task_id ";//.$sqlteam;
		$sql .= " AND tr.user_id=$user_id ";
		$sql .= " AND tr.started_date>='$sdt' ";
		$sql .= " AND tr.started_date<='$edt' ";
		$sql .= " ORDER BY person, datesort";
		$db->query($sql);

		if ($db->next_record()) {
			$t->set_var("no_task_report", "");
			$tmp_date = "";
			$tmp_person = "";
			$day_hours = 0;
			do {
				$date = $db->f("date");
				$rdate = $db->f("rdate");
				$start_time = date("H:i", $db->f("start_time_u"));
				if ($date == $rdate) {
					$end_time = date("H:i", $db->f("end_time_u"));
				}
				else $end_time = date("d M Y H:i", $db->f("end_time_u"));
				if (!$date && $rdate) $date = $rdate;
				$person = $db->f("person");
				$task_title = $db->f("task_title");
				$task_id = $db->f("task_id");
				$time_period = $start_time . " - " . $end_time;
				$spent_hours = $db->f("spent_hours");
				$mins = sprintf("%02d", round(($spent_hours - floor($spent_hours)) * 60));
				$hours = floor($spent_hours) . ":$mins ";

				if ($user_id) {
					if ($tmp_date!=$date and $tmp_date) {
						$day_hours = floor($day_hours) . ":" . sprintf("%02d", round(($day_hours - floor($day_hours)) * 60));
						$t->set_var("day_hours_html", "<tr><td colspan=3  align=\"right\">Total:</td><td align=\"center\"><font class=\"GridRecordHeader\"><b>$day_hours</b></font></td></tr>");
						$day_hours = 0;
					}

					else $t->set_var("day_hours_html", "");
					if ($tmp_date!=$date) {
						$t->set_var("date_html", "<tr height=10><td colspan=4></td></tr><tr><td colspan=4><font class=\"GridRecordHeader\"><b>$date</b></font></td></tr>");
					}
					else $t->set_var("date_html", "");

					$t->set_var("for_person", "for $person");
					$t->set_var("person", "");
					$t->set_var("task_title", $task_title);
					$t->set_var("task_id", $task_id);
					$t->set_var("time_period", $time_period);
					$t->set_var("spent_hours", $hours);

					$day_hours += $spent_hours;
					$tmp_date = $date;
					$tmp_person = $person;
				}
				else {
					if (($tmp_date!=$date and $tmp_date) or ($tmp_person != $person and $tmp_person)) {
						$day_hours = floor($day_hours) . ":" . sprintf("%02d", round(($day_hours - floor($day_hours)) * 60));
						$t->set_var("day_hours_html", "<tr><td colspan=3  align=\"right\">Total:</td><td align=\"center\"><font class=\"GridRecordHeader\"><b>$day_hours</b></font></td></tr>");
						$day_hours = 0;
					}
					else $t->set_var("day_hours_html", "");

					$t->set_var("for_person", "for all persons");
					if ($tmp_date!=$date) {
						$t->set_var("date_html", "<tr height=10><td colspan=4></td></tr><tr><td colspan=4><font class=\"GridRecordHeader\"><b>$date</b></font></td></tr>");
						$t->set_var("person", "<b>$person</b>");
					}
					else {
						$t->set_var("date_html","");
						if ($tmp_person!=$person) {
							$t->set_var("person", "<b>$person</b>");
						}
						else $t->set_var("person", "");
					}
					$t->set_var("task_title", $task_title);
					$t->set_var("task_id", $task_id);
					$t->set_var("time_period", $time_period);
					$t->set_var("spent_hours", $hours);

					$day_hours += $spent_hours;
					$tmp_date = $date;
					$tmp_person=$person;
				}
				$t->parse("task_report", true);
			} while ($db->next_record());
			$tmp_date = "";
			$tmp_person = "";
			$day_hours = floor($day_hours) . ":" . sprintf("%02d", round(($day_hours - floor($day_hours)) * 60));
			$t->set_var("day_hours_html2", "<tr><td colspan=2  align=\"right\">Total:</td><td align=\"center\"><font class=\"GridRecordHeader\"><b>$day_hours</b></font></td></tr>");
		}
		else
		{
			$t->set_var("for_person", "");
			$t->set_var("task_report_header", "");
			$t->set_var("task_report", "");
			$t->set_var("day_hours_html2", "");
			$t->parse("no_task_report", false);
		}
		$t->parse("result_task_report", false);
	}

	else
	{
		$t->set_var("result_task_report", "");
	}

} else {
	//$t->set_var("result", "");
}

//end holidays summary

//begin holidays summary
$today = getdate();
$today_year = $today["year"];
$nowday = date('Y-m-d');
$sql = "SELECT start_date, user_id, CONCAT(first_name, ' ', last_name) AS user_name FROM users WHERE is_viart=1 AND is_deleted IS NULL AND user_id=".ToSQL($user_id,"integer");
	$db->query($sql);
	$t->parse("header_holiday_summary");
				while ($db->next_record())
				{
				  	$t->set_var("hol_user_name", $db->f("user_name"));
				  	$t->set_var("show_user_id", $db->f("user_id"));
				  	$t->set_var("work_start", $db->f("start_date"));
						$sql2 = "SELECT SUM(total_days) AS used_holidays FROM days_off WHERE reason_id = 1 AND is_paid=0 AND user_id=".ToSQL($user_id,"integer");
						$db2->query($sql2);
						if ($db2->next_record()) {
						  $used_holidays = $db2->f("used_holidays");
							$t->set_var("used_holidays", $used_holidays);
						}

						$sql2 = "SELECT SUM(total_days) AS used_holidays FROM days_off WHERE reason_id = 1 AND is_paid=0 AND user_id=".ToSQL($user_id,"integer");
						$sql2 .= " AND DATE_FORMAT(start_date, '%Y')='$today_year'";
						$db2->query($sql2);
						if ($db2->next_record()) {
							$t->set_var("used_holidays_year", $db2->f("used_holidays"));
						}

						$sql2 = 	"SELECT SUM(days_number) AS total_holidays FROM holidays WHERE user_id=".ToSQL($user_id,"integer");
						$db2->query($sql2);
						if ($db2->next_record())
						{
						  $total_holidays = floor ($db2->f("total_holidays"));
							$t->set_var("total_holidays", $total_holidays);
						}

						$sql2 = "SELECT SUM(days_number) AS total_holidays FROM holidays WHERE user_id=".ToSQL($db->f("user_id"), "integer");
						$sql2 .= " AND DATE_FORMAT(date_added, '%Y')='$today_year'";
						$db2->query($sql2);
						if ($db2->next_record())
						{
							$t->set_var("total_holidays_year", floor($db2->f("total_holidays")));
						}

						$t->set_var("this_year",$today_year);
						$t->set_var("avail_holidays", $total_holidays - $used_holidays);
						$t->parse("holiday_summary", true);


				}

//end holidays summary


//begin bugs summary
function CreateTable($sql, $block_name)
{
	$db = &$GLOBALS['db2'];
	$T = &$GLOBALS['t'];

	$db->query($sql);

	if ($db->next_record())
	{

		//fill $list array
		do {

			$list[] = $db->Record;

		} while ($db->next_record());

		foreach ($list as $row)// make table row for this bug
		{
			foreach ($row as $key => $item)
			{

				$T->set_var($key, $item == "NULL" ? '':$item);


			}

			$T->parse($block_name, true);
		}

		$T->set_var("no_".$block_name, "");

	}

	else
	{
		$T->set_var($block_name, "");
		$T->parse("no_".$block_name, false);
	}
}

$sql = 'SELECT t.task_title,
		CONCAT(u2.first_name, \' \', u2.last_name) as creator,
		CONCAT(u3.first_name, \' \', u3.last_name) as closer,
		DATE_FORMAT(b.date_issued, \'%d %b %Y - %W\') AS date_issued,
		DATE_FORMAT(b.date_resolved, \'%d %b %Y - %W\') AS date_resolved,
		IF (is_resolved =1, \'Yes\', \'No\') as is_resolved,
		IF (is_declined =1, \'Yes\', \'No\') as is_declined,
		IF (is_declined =1, \'white\', IF (is_resolved = 1, \'008000\', \'FF9090\')) as color
		FROM bugs b JOIN users u ON b.user_id = u.user_id
		JOIN users u2 ON b.issued_user_id = u2.user_id LEFT JOIN
 		users u3 ON b.resolved_user_id = u3.user_id JOIN tasks t ON t.task_id = b.task_id
 		JOIN projects p ON p.project_id = t.project_id
 		WHERE b.user_id = '.$user_id.' ORDER BY b.date_issued DESC
';

CreateTable($sql, 'records1');




//end bugs summary

// begin inventory view
	$sql = "SELECT 	i.inventory_id,
					i.inventory_title AS inventories,
					i.inventory_desc AS description,
					NULL AS value
			FROM inventory i LEFT JOIN inventory_users iu ON i.inventory_id=iu.inventory_id
			WHERE iu.user_id=".ToSQL($user_id,"integer");
	$db->query($sql);
	if ($db->num_rows()>0) {
		while ($db->next_record()) {
			$t->set_var($db->Record);
			$t->set_var(array(	"color" => "DEE3E7",
								"class"	=> "DataTDP"));
			$t->parse("inventory_list", true);
			$t->set_var("no_inventory_list","");

			$sql2 = "SELECT CONCAT('&nbsp;&nbsp;&nbsp;&nbsp;',inventory_property_name) AS inventories,
							CONCAT('&nbsp;&nbsp;&nbsp;&nbsp;',inventory_property_desc) AS description,
							CONCAT('&nbsp;&nbsp;&nbsp;&nbsp;',inventory_property_value) AS value
					 FROM inventory_properties
					 WHERE inventory_id=".ToSQL($db->Record["inventory_id"],"integer");
            $db2->query($sql2);
            if ($db2->num_rows()>0) {
            	while ($db2->next_record()) {
	            	$t->set_var($db2->Record);
	                $t->set_var(array(	"color" => "EFEFEF",
										"class"	=> "DataTD"));
	            	$t->parse("inventory_list", true);
					$t->set_var("no_inventory_list","");
				}
            } else {
            	$t->set_var("inventory_list","");
            	$t->set_var("no_inventory_list","");
            }
		}
	} else {
		$t->set_var("inventory_list","");
		$t->parse("no_inventory_list", true);
	}
// end inventory view

$t->pparse("main");

?>