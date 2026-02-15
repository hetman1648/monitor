<?php

	include("./includes/date_functions.php");
	include("./includes/common.php");

	CheckSecurity(1);

	$team			= GetParam("team");
	$submit			= GetParam("submit");
	$projects		= GetParam("projects");
	$year_selected	= GetParam("year_selected");
	$month_selected	= GetParam("month_selected");
	$person_selected	= GetParam("person_selected");
	$group_by_people	= GetParam("group_by_people");
	$project_selected	= GetParam("project_selected");

	$as="";$vs="";$ys="";
	switch (strtolower($team)) {
	  case "all":	$sqlteam=""; $as="selected"; break;
	  case "viart":	$sqlteam=" AND u.is_viart=1 "; $vs="selected"; break;
	  case "yoonoo":$sqlteam=" AND u.is_viart=0 "; $ys="selected"; break;
	  default:	$sqlteam=" AND u.is_viart=1 "; $vs="selected"; $team="viart";
	}

	if (!$year_selected) {$year_selected = date("Y");}
	if (!$month_selected) {$month_selected = date("m");}

	$t = new iTemplate($sAppPath);
	$t->set_file("main", "project_plan.html");

	$t->set_var("aselected",$as); $t->set_var("vselected",$vs); $t->set_var("yselected",$ys); $t->set_var("team_selected",$team);
	$t->set_var("months", GetMonthOptions($month_selected));
	$t->set_var("years", GetYearOptions(2004, date("Y")+1, $year_selected));
	$t->set_var("gbpcheck", ($group_by_people ? "checked" : ""));
	$t->set_var("person_selected",$person_selected ? $person_selected : "0");

	//people list
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

	$t->set_var("ifProjName","");

	//projects list
	$people = "";
	$sql	= " SELECT project_id, project_title FROM projects WHERE is_closed=0 ORDER BY project_title ";
	$db->query($sql,__FILE__,__LINE__);
	while ($db->next_record())	{
		$project = $db->f("project_title");
		$project_nom = $db->f("project_id");
		if ($project_selected==$project_nom)
		{
		  $projects .= "<option selected value='$project_nom'>$project</option>";
		  $t->set_var("ifProjName",": ".$project);
		}
	    else $projects .= "<option value='$project_nom'>$project</option>";
	}
	$t->set_var("projects", $projects);

if ($submit)
{
	$t->parse("legend",false);

	//-- Number of days in month
	$n_days = date("t", mktime(0, 0, 0, $month_selected, 1, $year_selected));
	$t->set_var("num_cols", $n_days + 6);

	$sql = " SELECT CONCAT(u.first_name, ' ', u.last_name) AS user_name, u.user_id, t.task_title, tr.task_id, ";
	$sql .= " SUM(tr.spent_hours) AS sum_hours, DAYOFMONTH(tr.started_date) AS day_of_month ";
	$sql .= ", IF(DATE_FORMAT( t.planed_date, '%Y' ) = '".ToSQL($year_selected,"integer")."' AND DATE_FORMAT( t.planed_date, '%m' ) = '".ToSQL($month_selected,"integer")."', DATE_FORMAT( t.planed_date, '%d' ),0) AS planed_day ";
	$sql .= ", IF(t.planed_date>='2003-01-01' AND t.planed_date<=NOW() AND t.is_closed=0 AND t.task_status_id!=4,1,0) AS ifdeadline ";
	$sql .= " FROM tasks t, time_report tr, users u ";
	$sql .= " WHERE t.task_id=tr.task_id AND u.user_id=tr.user_id ".$sqlteam;
	if ($year_selected) $sql .= " AND DATE_FORMAT(tr.started_date, '%Y')='".ToSQL($year_selected,"integer")."'";
	if ($month_selected) $sql .= " AND DATE_FORMAT(tr.started_date, '%m')='".ToSQL($month_selected,"integer")."'";
	if ($person_selected) $sql .= " AND tr.user_id=".ToSQL($person_selected,"integer")." ";
	if ($project_selected) $sql .= " AND t.project_id=".ToSQL($project_selected,"integer")." ";
	$sql .= " GROUP BY user_name, task_title, day_of_month ";
	$sql .= " ORDER BY user_name, started_time";

	$db->query($sql,__FILE__,__LINE__);
	$numberoftasks=0;

	$hours_ar = array();
	$planed_day = array();
	$task_deadline = array();

	while ($db->next_record())
	{
		$user_id = $db->f("user_id");
		$task_id = $db->f("task_id");
		$day_of_month = $db->f("day_of_month");
		$sum_hours = $db->f("sum_hours");
		$planed_day[$user_id][$task_id] = $db->f("planed_day");
		$task_deadline[$user_id][$task_id] = $db->f("ifdeadline");
		$hours_ar[$user_id][$task_id][$day_of_month] = $sum_hours;
	}

//task estimates calculations

	$today=date("d");
	$c_year=date("Y");
	$c_month=date("m");
	$c_ym=$c_year*12+$c_month;
	$c_ym_s=$year_selected*12+$month_selected;

	//count hours each user has worked today
	$sql = "SELECT	user_id, SUM(spent_hours) AS sum_hours
			FROM	time_report
			WHERE	YEAR(started_date)=YEAR(now())
					AND MONTH(started_date)=MONTH(now())
					AND DAYOFMONTH(started_date)=DAYOFMONTH(now())
			GROUP BY user_id
			ORDER BY user_id";

	$db->query($sql,__FILE__,__LINE__);
	$workedtoday = array();
	while ($db->next_record()) {
		$user_id = (int)$db->f("user_id");
		$workedtoday[$user_id]=$db->f('sum_hours')/8;
	}

	$sql = " SELECT task_id, priority_id, responsible_user_id, estimated_hours, completion, ";
	$sql.= " task_type_id, task_status_id, actual_hours ";
	$sql.= " FROM tasks WHERE is_wish = 0 ";
	if ($person_selected) $sql.= " AND responsible_user_id = $person_selected ";
	$sql.= " AND is_closed = 0 AND task_status_id!=4 AND task_type_id!=3 ORDER BY priority_id";
	$db->query($sql,__FILE__,__LINE__);

	$curday = array();
	$curyear = array();
	$curmonth = array();

	for($i=0;$i<=100;$i++) {
		$curday[$i]  =$today;
		$curmonth[$i]=(int)date('m');
		$curyear[$i] =(int)date('Y');
	}

	$taskdays=array();
	$task_estimate_hours=array(); //array for estimations on planned tasks in future;

	while ($db->next_record()) {
		$task_id = $db->f("task_id");
		$user_id = $db->f("responsible_user_id");
		$taskdays[$user_id][$task_id]['priority'] = $db->f("priority_id");
		$estimated_hours = $db->f("estimated_hours");
		$actual_hours	 = $db->f("actual_hours");
		$completion		 = $db->f("completion");
		$estimated_days_left = $estimated_hours*(1-$completion/100)/8;
		$actual_days_left	 = $completion>1 ? $actual_hours*(100-$completion)/$completion/8 : 0;

		if (isset($estimated_hours) && $estimated_hours>0) { $taskdays[$user_id][$task_id]['estimated_days'] = $estimated_days_left;}
		if ($completion>1)	{
			$taskdays[$user_id][$task_id]['estimated_days'] = $actual_days_left;
			//echo "<BR>$task_id :: ".$taskdays[$user_id][$task_id]['estimated_days'];
		}
		//if ($actual_days_left>0 && $actual_days_left>0)
	}
	//calculate estimates
	foreach ($taskdays AS $user_id=>$taskdaysuser) {
		foreach ($taskdaysuser AS $id=>$taskday) {
			if (array_key_exists("estimated_days", $taskday) && $taskday["estimated_days"]>0) {
				$tded=$taskday['estimated_days'];
				do {
						//if($tded+$workedtoday[$user_id]<1) {
						if(array_key_exists($user_id,$workedtoday) && $tded+$workedtoday[$user_id]<1) {
							$task_estimate_hours[$user_id][$id][$curyear[$user_id]][$curmonth[$user_id]][$curday[$user_id]]=$tded;
							$workedtoday[$user_id]+=$tded;
							$tded=0;
						} else {
							//$tasktoday=1-$workedtoday[$user_id];
							if (array_key_exists($user_id,$workedtoday)) {
								$tasktoday=1-$workedtoday[$user_id];
							} else { $tasktoday=1;}
							$task_estimate_hours[$user_id][$id][$curyear[$user_id]][$curmonth[$user_id]][$curday[$user_id]]=$tasktoday;
							$tded-=$tasktoday;
							$workedtoday[$user_id]=0;
							$curday[$user_id]++;
							if (date('w',mktime(0,0,0,$curmonth[$user_id],$curday[$user_id],$curyear[$user_id]))==6) {$curday[$user_id]+=2;}

							if ($curday[$user_id]>date('t',mktime(0,0,0,$curmonth[$user_id],1,$curyear[$user_id]))) {
								$curday[$user_id]=$curday[$user_id]-date('t',mktime(0,0,0,$curmonth[$user_id],1,$curyear[$user_id]));
								$curmonth[$user_id]++;
								if ($curmonth[$user_id]>12) {
									$curmonth[$user_id]=1;
									$curyear[$user_id]++;
								}
							}
						}
					//}
				} while ($tded!=0);
			}

		}
	}

//	print_r($task_estimate_hours);
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	$sql = " SELECT user_name, user_id, task_title, task_id, task_status, SUM(sum_hours) AS spent_hours, estimated_hours, completion, ";
	$sql.= " started_time, priority_id AS priority, SUM(is_opened) AS opened, actual_hours, task_type_id, ifdeadline ";
	$sql.= " FROM ((";
	$sql .= " SELECT CONCAT(u.first_name, ' ', u.last_name) AS user_name, u.user_id, t.task_title, t.task_id, lt.status_desc AS task_status, ";
	$sql .= " SUM(tr.spent_hours) AS sum_hours, t.estimated_hours, t.completion, t.started_time, t.priority_id, 0 AS is_opened, t.actual_hours, t.task_type_id, ";
	$sql .= " IF(t.planed_date>='2003-01-01' AND t.planed_date<=NOW() AND t.is_closed=0 AND t.task_status_id!=4,1,0) AS ifdeadline ";
	$sql .= " FROM users u, lookup_tasks_statuses lt, tasks t, time_report tr ";
	$sql .= " WHERE tr.task_id=t.task_id AND lt.status_id=t.task_status_id AND u.user_id=tr.user_id ".$sqlteam;
	if ($person_selected) $sql .= " AND u.user_id=$person_selected ";
	if ($project_selected) $sql .= " AND t.project_id=$project_selected ";
	$sql .= " AND DATE_FORMAT(tr.started_date, '%Y')='$year_selected' AND DATE_FORMAT(tr.started_date, '%m')='$month_selected' ";
	$sql .= " GROUP BY u.user_id, t.task_id ";
	$sql .= ") UNION (";
	$sql .= "SELECT CONCAT(u.first_name, ' ', u.last_name) AS user_name, u.user_id, t.task_title, t.task_id, lt.status_desc AS task_status, ";
	$sql .= " NULL AS sum_hours, t.estimated_hours, t.completion, t.started_time, t.priority_id, 1 AS is_opened, t.actual_hours, t.task_type_id, ";
	$sql .= " IF(t.planed_date>='2003-01-01' AND t.planed_date<=NOW() AND t.is_closed=0 AND t.task_status_id!=4,1,0) AS ifdeadline ";
	$sql .= " FROM users u, lookup_tasks_statuses lt, tasks t ";
	$sql .= " WHERE lt.status_id=t.task_status_id AND u.user_id=t.responsible_user_id ".$sqlteam;
	if ($person_selected) $sql .= " AND u.user_id=$person_selected ";
	if ($project_selected) $sql .= " AND t.project_id=$project_selected ";
	$sql .= " AND t.is_closed=0 AND t.task_status_id!=4 ";
	$sql .= " GROUP BY u.user_id, t.task_id ";
	$sql .= ") ) AS united_table ";
	$sql .= " GROUP BY task_id, user_id ";
	if ($group_by_people) $sql.= " ORDER BY user_id, opened DESC, started_time, task_title "; else $sql.= " ORDER BY opened DESC, started_time, task_title ";

//	echo $sql."<BR><BR>";
	$db->query($sql,__FILE__,__LINE__);
	$cur_user = "";
	$i_day = 0;
	if ($db->next_record())
	{
		$t->set_var("no_records", "");
		for ($i_day=1; $i_day<=$n_days; $i_day++)
		{
			$t->set_var("i_day", $i_day);
			$t->parse("records_header_day", true);
		}
		$t->parse("records_header", false);
		do
		{
			$user_name = $db->f("user_name");
			$t->set_var("user_name", $user_name);

			if ($cur_user==$user_name || !$group_by_people) {
				$t->set_var("records_person", "");
			} else {
				$t->parse("records_person", false);
				$cur_user = $user_name;
			}
			if ($group_by_people) {$t->set_var("name_in_row","");}

			$user_id = $db->f("user_id");
			$task_id = $db->f("task_id");
			$task_title = $db->f("task_title");
			$sum_hours = $db->f("spent_hours");
			$completion = $db->f("completion");
			$opened = $db->f("opened");
			$est_hours = $db->f("estimated_hours");
			$actual_hours = $db->f("actual_hours");
			$status_desc = $db->f("task_status");
			$ifdeadline = $db->f("ifdeadline");
			$task_type = $db->f("task_type_id");
			$left_hours = $est_hours-$est_hours*$completion/100;
			$left_hours_act = ($completion>1 ? $actual_hours*(100-$completion)/$completion : 0);
			if ($left_hours_act>0) {$left_hours=$left_hours_act;}
			if ($status_desc=="done") {$left_hours=0;}
			$t->set_var("user_id", $user_id);
			$t->set_var("task_id", $task_id);
			if ($opened) {$t->set_var("task_title", "<b>".$task_title."</b>");}
				else {$t->set_var("task_title", $task_title);}
			$t->set_var("spent_hours", $sum_hours>0 ? Hours2HoursMins($sum_hours) : "&nbsp;");

			if ($left_hours && $opened) $t->set_var("left_hours", Hours2HoursMins($left_hours));else $t->set_var("left_hours","&nbsp;");
//			if ($task_type==3) $t->set_var
			$t->set_var("status_desc",$status_desc);//.(int)$opened);
			if ($completion && $task_type!=3)
			{
				if($status_desc!="done") {$t->set_var("completion", (int)$completion."%");}
			} else {$t->set_var("completion","&nbsp;");}
			if ($task_type==3) {$t->set_var("completion","periodic");}
			if ($status_desc=="done") {$t->set_var("completion", "100%");}

			$t->set_var("color","");
			$t->set_var("colorclass","");
			if($opened) {
				$t->set_var("color","green");
				$t->set_var("colorclass"," class='greenlink' ");
			}

			$records_days = "";
			for ($i_day=1; $i_day<=$n_days; $i_day++)
			{
				if (!isset($hours_ar[$user_id][$task_id][$i_day]) || !$hours_ar[$user_id][$task_id][$i_day])
				{
					$day_of_week = date("w", mktime(0, 0, 0, $month_selected, $i_day, $year_selected));
					//highlight Saturday and Sunday
					if ($day_of_week==0 || $day_of_week==6) {$day_class = "DayoffTD";}
						else {$day_class = "DataTD";}

					if (isset($task_estimate_hours[$user_id][$task_id][$year_selected][(int)$month_selected][$i_day]) &&
					          $task_estimate_hours[$user_id][$task_id][$year_selected][(int)$month_selected][$i_day]>0)
					{
						$day_class="DayPlanned";
					}
				}
				elseif ($hours_ar[$user_id][$task_id][$i_day] <= 2) { $day_class = "Spent1";}
				elseif ($hours_ar[$user_id][$task_id][$i_day] <= 4) { $day_class = "Spent2";}
				elseif ($hours_ar[$user_id][$task_id][$i_day] <= 6) { $day_class = "Spent3";}
				else {	$day_class = "Spent4";	}

				//display deadlines in red and planed days in green
				if ( array_key_exists($user_id,$planed_day) && array_key_exists($task_id,$planed_day[$user_id]) && $planed_day[$user_id][$task_id] && $planed_day[$user_id][$task_id]==$i_day) { $planed_task_html="<font color='red'><b>D</b></font>";}
					else {$planed_task_html="";}

				//display task row in red colours if it crosses deadline
				if($ifdeadline)
				{
		   			$t->set_var("color","red");
		   			$t->set_var("colorclass"," class='redlink' ");
				}
				$records_days .= "<td class=\"$day_class\" nowrap align='center'>$planed_task_html</td>";

				$t->set_var("records_days", $records_days);
			}

			$t->set_var("year_selected", $year_selected);
			$t->set_var("month_selected", $month_selected);
			if ($group_by_people) {	  $t->set_var("colspan","2"); $t->set_var("left_padding","15");	}
				else {  $t->set_var("colspan","1"); $t->set_var("left_padding","5");}

			$t->parse("records", true);

			$numberoftasks++;
		} while ($db->next_record());
	}

	if (!$numberoftasks)
	{
		//$t->parse("legend",false);
		$t->set_var("records_header", "");
		$t->set_var("records", "");
		$t->parse("no_records", false);
	}
	$t->parse("result", false);
} else {
		$t->set_var("legend","");
		$t->set_var("result","");
		$t->set_var("records","");
		//$t->parse("result",false);
}
	//echo ("TOTAL TASKS: ((".$numberoftasks."))");

	$t->pparse("main");
?>