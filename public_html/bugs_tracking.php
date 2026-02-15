<?php

	include("./includes/date_functions.php");
	include("./includes/common.php");

	$T = new iTemplate($sAppPath);
	$T->set_file("main", "bugs_tracking.html");

	//$T->parse("records_header");
	$statuses_classes = array("","InProgress","OnHold","Rejected","Done","Question","Answer","New","Waiting","Reassigned","Bug","Deadline", "BugResolved");
	//filter
	$period		= GetParam("period");
	$start_date	= GetParam("start_date");
	$end_date	= GetParam("end_date");
	$project_id	= GetParam("project_id");
	$user_id	= GetParam("user_id");
	$submit		= GetParam("submit");
	$show_closed = GetParam("show_closed");
	$close		= GetParam("close");
	$bug_id		= GetParam("bug_id");


	$as="";$vs="";$ys="";

	if ($close || $bug_id) {
		$sql =  "UPDATE bugs
				SET	is_declined = 1,
					resolved_user_id=".GetSessionParam('UserID')."
				WHERE bug_id =".$bug_id;
		$db->query($sql,__FILE__,__LINE__);
	}

	if (!$period && !$submit) $period = 6;
	$T->set_var("period", $period);

	$current_date = va_time();

	$today_date = date ("Y-m-d");
	$T->set_var("today_date", $today_date);

	$cyear = $current_date[0]; $cmonth = $current_date[1]; $cday = $current_date[2];
	$yesterday_date = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday - 1, $cyear));
	$T->set_var("yesterday_date", $yesterday_date);

	$week_start_date = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday - 7, $cyear));
	$week_end_date   = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday - 1, $cyear));
	$T->set_var("week_start_date", $week_start_date);
	$T->set_var("week_end_date", $week_end_date);

	$month_start_date = date ("Y-m-d", mktime (0, 0, 0, $cmonth - 1, 1, $cyear));
	$month_end_date   = date ("Y-m-t", mktime (0, 0, 0, $cmonth - 1, 1, $cyear));
	$T->set_var("month_start_date", $month_start_date);
	$T->set_var("month_end_date", $month_end_date);

	$this_month_start = date ("Y-m-d", mktime (0, 0, 0, $cmonth, 1, $cyear));
	$this_month_end   = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday, $cyear));
	$T->set_var("this_month_start", $this_month_start);
	$T->set_var("this_month_end", $this_month_end);



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
			case 6:
				$start_date = false;
				$end_date = false;
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
	$T->set_var("start_date", $sd);
	$T->set_var("end_date", $ed);

	//make select list of projects

	$db2 = new DB_Sql;
	$db2->Database = DATABASE_NAME;
	$db2->User     = DATABASE_USER;
	$db2->Password = DATABASE_PASSWORD;
	$db2->Host     = DATABASE_HOST;

	$sql = "SELECT * FROM projects WHERE is_closed=0 AND parent_project_id IS NULL ORDER BY project_title";
	$db->query($sql,__FILE__,__LINE__);
	if($db->next_record()) {
	   	do {
	   		$T->set_var('project_title', $db->Record['project_title']);
			$T->set_var('project_id', $db->Record['project_id']);
			$T->set_var("selected", "");
			if ($db->Record["project_id"] == $project_id) {
				$T->set_var("selected", "selected");
			}

			$T->parse('projects', true);
			$sql2 = 'SELECT * FROM projects WHERE parent_project_id='.$db->Record['project_id'].' ORDER BY project_title';
	        $db2->query($sql2);
	        if ($db2->next_record()) {
	        	do {
	            	$T->set_var('project_title', "&nbsp;&nbsp;&nbsp;&nbsp;".$db2->Record['project_title']);
					$T->set_var('project_id', $db2->Record['project_id']);
					$T->set_var('selected', '');
					if ($db2->Record['project_id'] == $project_id)
					{
						$T->set_var('selected', 'selected');
					}
	                $T->parse('projects', true);
	            } while ($db2->next_record());
	        }
	    } while ($db->next_record());

		$T->set_var("no_projects", "");
	    //$T->parse("FormProjects", false);

	} else $T->set_var("projects", "");




	//end list of projects


	//filter end

	CheckSecurity(1);
	$sql1 = '
		SELECT t.task_title, CONCAT(u.first_name, \' \', u.last_name) as responsible_user,
		CONCAT(u2.first_name, \' \', u2.last_name) as creator,
		CONCAT(u3.first_name, \' \', u3.last_name) as closer,
		DATE_FORMAT(b.date_issued, \'%d %b %Y - %W\') AS date_issued,
		DATE_FORMAT(b.date_resolved, \'%d %b %Y - %W\') AS date_resolved,
		IF (is_resolved =1, \'Yes\', \'No\') as is_resolved,
		IF (is_declined =1, \'Yes\', \'No\') as is_declined,
		IF (is_declined =1, \'white\', IF (is_resolved = 1, \'008000\', \'FF9090\')) as color,
		t.task_status_id as status,
		t.task_id as task_id,
		b.bug_id as bug_id,
		\''.mysql_escape_string($preg = preg_replace('|&?close=close&bug_id=[[:digit:]]+|', '' ,getenv('QUERY_STRING'))).(((substr($preg, -1) == '?')|| $preg == '') ? '' : '&').'close=close&bug_id='.'\'  as query
		FROM bugs b JOIN users u ON b.user_id = u.user_id
		JOIN users u2 ON b.issued_user_id = u2.user_id LEFT JOIN
		users u3 ON b.resolved_user_id = u3.user_id JOIN tasks t ON t.task_id = b.task_id
		JOIN projects p ON p.project_id = t.project_id
		WHERE 1=1
	';


	if ($start_date){
		$sql1 .= ' AND b.date_issued >= \''.$start_date.'\'';
	}
	if ($end_date) {
		$sql1 .= ' AND b.date_issued <= \''.$end_date.'\'';
	}
	if (is_numeric($project_id)) {
		$sql1 .= ' AND (t.project_id = '.$project_id.' OR p.parent_project_id = '.$project_id.')';
	}
	if (!($show_closed == 'show_closed')) {
		$sql1 .= ' AND is_declined = 0 ';
	}
	if (is_numeric($user_id)) {
		$sql1 .= ' AND b.user_id = '.$user_id;
	}
	$sql1 .= ' ORDER BY b.date_issued DESC';


	$sql2 = 'SELECT \''.($start_date ? $start_date : 'NULL').'\' as start_date, \''.($end_date ? $end_date : 'NULL').'\' as end_date, '.($project_id ? $project_id : 'NULL').' as project_id, b.user_id as user_id, CONCAT(u.first_name, \' \', u.last_name) as user_name,
			 COUNT(b.bug_id) as total_bugs, SUM(b.importance_level) as total_importance_level
			 FROM users u JOIN bugs b ON u.user_id = b.user_id
			 JOIN tasks t ON b.task_id = t.task_id
			 JOIN projects p ON t.project_id = p.project_id
			 WHERE 1 = 1
			 ';


	$sql2 .= ' GROUP BY u.user_id
			   ORDER BY total_importance_level DESC
			 ';

	CreateTable($sql1, 'records1');

	CreateTable($sql2, 'records2');

	if ($show_closed) {
		$T->set_var('checked', 'checked');
		$T->set_var('show_closed', 'show_closed=show_closed');
	} else {
		$T->set_var('checked', '');
		$T->set_var('show_closed', 'show_closed=show_closed');
	}
	//if (GetParam('show_closed')) { $T->set_var('show_closed', 'show_closed=show_closed');}
	//	else { $T->set_var('show_closed', 'show_closed=show_closed');}
	$T->set_var("action", "bugs_tracking.php");
	$T->pparse("main");



function CreateTable($sql, $block_name)
{
	global $db;
	global $T;
    global $statuses_classes;

	$i = 1;
	$db->query($sql,__FILE__,__LINE__);
	if ($db->next_record()) {
		//fill $list array
		do {
			$list[] = $db->Record;
		} while ($db->next_record());
		foreach ($list as $row) {// make table row for this bug
			foreach ($row as $key => $item) {
				$T->set_var($key, $item == "NULL" ? '':$item);
			}
			if (isset($row['status'])) {
				$T->set_var('STATUS', $statuses_classes[$row['status']]);
			}
			if (isset($row['task_id'])) {
				$T->set_var('task_id', $row['task_id']);
				$i ++;
				$T->set_var('title_id', $i);
			}
			$T->parse($block_name, true);
		}
		$T->set_var("no_".$block_name, "");
	} else {
		$T->set_var($block_name, "");
		$T->parse("no_".$block_name, false);
	}
}
?>
