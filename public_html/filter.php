<?php
	include_once("./includes/date_functions.php");
	include_once("./includes/common.php");



	$period				= GetParam("period");
	$start_date			= GetParam("start_date");
	$end_date			= GetParam("end_date");
	$person_selected	= GetParam("person_selected");
	$submit				= GetParam("submit");
	$team				= GetParam("team");

    $as='';$vs='';$ys='';
    
	switch (strtolower($team))
	{
		case "all":		$sqlteam = ""; $as = "selected"; break;
		case "viart":	$sqlteam = " AND u.is_viart=1 "; $vs = "selected"; break;
		case "yoonoo":	$sqlteam = " AND u.is_viart=0 "; $ys = "selected"; break;
		default:		$sqlteam = " AND u.is_viart=1 "; $vs = "selected"; $team = "viart";
	}

	$periods = array(""=>"", "1"=>"Today", "2"=>"Yesterday", "3"=>"Last 7 Days", "4"=>"Last Month", "5"=>"This Month");
	
	if (!$period && !$submit) {
		$period = 1;
		if (isset($default_period)) {
			$period = $default_period;
		}
	}
	$t->set_var("period", $period);
	
	foreach ($periods as $value=>$description) {
		$t->set_var("period_value", $value);
		$t->set_var("period_description", $description);
		if ($value==$period) {
			$t->set_var("period_selected", "selected");
		} else {
			$t->set_var("period_selected", "");
		}
		if ($t->block_exists("period_options")) {
			$t->parse("period_options", true);
		}
	}

	$t->set_var("aselected", $as);
	$t->set_var("vselected", $vs);
	$t->set_var("yselected", $ys);
	$t->set_var("team_selected", $team);

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
	$this_month_end   = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday, $cyear));
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

	$t->set_var("person_selected", $person_selected ? $person_selected : "''");

	$people = "";
	$sql = "SELECT user_id, is_viart, CONCAT(first_name,' ', last_name) as person FROM users u WHERE is_deleted IS NULL ORDER BY person";
	$db->query($sql,__FILE__,__LINE__);
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


?>