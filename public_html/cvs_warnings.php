<?php
	include("./includes/common.php");
	include_once("./includes/date_functions.php");

	CheckSecurity(1);	
	
	$t = new iTemplate($sAppPath);
	$t->set_file("main", "cvs_warnings.html");
	
	if ($operation == "refuse") {		
		$module	    = GetParam("module");
		$user_id    = GetSessionParam("UserID");
		if ($module) {
			$sql  = " UPDATE cvs_modules_log ";
			$sql .= " SET commited=1, refused=1 ";
			$sql .= " WHERE user_id="  . $user_id;
			$sql .= " AND cvs_module=" . ToSQL($module, "text");
			$sql .= " AND commited=0";
			$db->query($sql);
		}	
	}
	
	$commited           = GetParam("commited");	
	$period_selected	= GetParam("period_selected");
	$start_date			= GetParam("start_date");
	$end_date			= GetParam("end_date");
	$person_selected	= GetParam("person_selected");
	$sort	            = GetParam("sort");
	$sortDir	        = GetParam("sortDir");
	
	$query_params = array(
		"commited"        => $commited,
		"period_selected" => $period_selected,
		"start_date"      => $start_date,
		"end_date"        => $end_date,
		"person_selected" => $person_selected
	);
	
	$filter_query_params = array(
		"sort"        => $sort,
		"sortDir"     => $sortDir	 
	);	
	
	$sortValues = array(
		"user"    => "CONCAT(user.first_name,' ',user.last_name)", 
		"module"  => "log.cvs_module", 
		"sd"      => "log.started_date",
		"cd"      => "log.commited_date",
		"project" => "project.project_title"
	);
	
	$sortKeys = array_keys($sortValues);
	foreach ($sortKeys As $key) {	
		$t->set_var($key . "_sortDir", "1");			
	}
	$t->set_var($sort . "_sortDir", ($sortDir) ? "0" : "1");
			
	if ($commited) {
		$t->set_var("commited_checked", "checked");
		$t->parse("commited_header");
	} else {
		$t->set_var("commited_checked", "");
		$t->set_var("commited_header", "");
		$t->set_var("commited_td", "");
	}
	
	list($sdt, $edt) = get_start_end_period ($period_selected, $start_date, $end_date);
	
	// statistics display
	$sql  = " SELECT COUNT(log.cvs_module) ";
	$sql .= " FROM cvs_modules_log log ";
	$sql .= " WHERE log.commited = 0";
	$db->query($sql);
	if ($db->next_record()) {
		$t->set_var("commit_days", "Never");
		$t->set_var("modules", $db->f(0));
		$t->parse("stat_warning");
		$t->set_var("colorrow", "Red");
	}
		
	$sql  = " SELECT log.commit_days, COUNT(log.cvs_module) ";
	$sql .= " FROM cvs_modules_log log ";
	$sql .= " WHERE log.commited = 1";
	$sql .= " GROUP BY log.commit_days ";
	$db->query($sql);
	while ($db->next_record()) {					
		$t->set_var("commit_days", $db->f("commit_days"));
		$t->set_var("modules", $db->f(1));
		$t->parse("stat_warning");
	}
	
	$sql  = " SELECT user.first_name, user.last_name, log.cvs_module, log.started_date, log.commited_date, ";
	$sql .= " project.project_title";
	$sql .= " FROM ((cvs_modules_log log ";
	$sql .= " LEFT JOIN users  user ON user.user_id=log.user_id)";
	$sql .= " LEFT JOIN projects  project ON project.cvs_module=log.cvs_module)";	
	$sql .= " WHERE (DATE(log.started_date) BETWEEN DATE_FORMAT('".$sdt."','%Y-%m-%d') AND DATE_FORMAT('".$edt."','%Y-%m-%d') ) ";
	if ($person_selected) {
		$sql .= " AND user.user_id=".ToSQL($person_selected,"integer")." ";
	}
	if (!$commited) {
		$sql .= " AND commited = 0";
	}
	if (isset($sortValues[$sort])) {
		$sql .= " ORDER BY " . $sortValues[$sort];
		if ($sortDir) {
			$sql .= " ASC ";
		} else {
			$sql .= " DESC ";
		}
	} else {
		$sql .= " ORDER BY started_date ";
	}
			
	$db->query($sql);
	$a = 0;
	if($db->next_record()) {
		$t->set_var("no_warnings", "");	
		do {
			$first_name    = $db->f("first_name");
			$last_name     = $db->f("last_name");
			$cvs_module    = $db->f("cvs_module");
			$started_date  = $db->f("started_date");
			$project_title = $db->f("project_title");
			$commited_date = $db->f("commited_date");
			
			$t->set_var("first_name",    $first_name);
			$t->set_var("last_name",     $last_name);
			$t->set_var("cvs_module",    $cvs_module);
			$t->set_var("started_date",  $started_date);
			$t->set_var("project_title", $project_title);
			$t->set_var("commited_date", $commited_date);	
			$t->set_var("colorrow",    	 (($a++)%2)?"DataRow2":"DataRow3");
			
			if ($commited) {
				$t->parse("commited_td", false);
			}
			$t->parse("warning", true);
		} while ($db->next_record());
	} else {
		$t->set_var("warning", "");
		$t->parse("no_warnings", true);
	}
	
	
	$t->set_var("periods", GetPeriodOption($period_selected));	
	$sql  = " users u WHERE is_deleted IS NULL ";
	$sql .= " AND is_cvs_notification=1";
	$sql .= " AND cvs_login IS NOT NULL AND cvs_login NOT LIKE '' ";	
	$t->set_var(
		"person_list", 	
		Get_Options($sql, "user_id", "CONCAT(first_name,' ',last_name) as user_name", ($person_selected ? $person_selected:-1), "user_name"
		)
	);
	
	$query_string = "";
	foreach ($query_params AS $key=>$value) {
		if ($query_string) $query_string .= "&";
		$query_string .= $key . "=" . $value;
	}
	$t->set_var("query_string", $query_string);
	
	$query_string = "";
	foreach ($filter_query_params AS $key=>$value) {
		if ($filter_query_string) $filter_query_string .= "&";
		$filter_query_string .= $key . "=" . $value;
	}
	$t->set_var("filter_query_string", $filter_query_string);
	
	$t->pparse("main");
	
	// from managing report
function GetPeriodOption($period_selected) {
	//$period_option=array("1","2","3","4","5","6","7","8","9");
	$period_option=array("today","yesterday","this_week","last_week","prev_week","this_month","last_month","prev_month","this_year");
	$period_titles=array("Today","Yesterday","This week","Last week (7 days)","Previous week","This month","Last month (30 days)","Previous month","This year");

	$res_str = "";
	for ($i = 0; $i < sizeof($period_option); $i++)	{
		if ($period_selected == $period_option[$i]) $selected = "selected"; else $selected = "";
		$res_str .= "<option $selected value=\"".$period_option[$i]."\">".$period_titles[$i]."</option>\n";
	}
	return $res_str;
}

function get_start_end_period($period_selected,&$start_date,&$end_date)
{
	global $t;

	$current_date = va_time();
	$cyear = $current_date[0];
	$cmonth = $current_date[1];
	$cday = $current_date[2];

	$today_date = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday, $cyear));
	$t->set_var("today_date", $today_date);

	$yesterday_date = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday - 1, $cyear));
	$t->set_var("yesterday_date", $yesterday_date);

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

	if (!$period_selected) $period_selected="today";

	if (!$start_date && !$end_date) {
		switch ($period_selected) {
			case "today":
				$start_date = $today_date;
				$end_date = $today_date;
				break;
			case "yesterday":
				$start_date = $yesterday_date;
				$end_date = $yesterday_date;
				break;
			case "this_week":
				$start_date = $this_week_start_date;
				$end_date = $this_week_end_date;
				break;
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
    /*
	$end_year  =@date("Y",$ed_ts);
	$start_year=@date("m",$ed_ts);
 	$t->set_var("current_year", $end_year);
	$t->set_var("current_month", $start_year);
    */
	return array($sdt,$edt);
}
?>