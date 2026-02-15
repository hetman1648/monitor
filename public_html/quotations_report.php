<?php

	include("./includes/date_functions.php");
	include("./includes/common.php");

	$t = new iTemplate($sAppPath);
	$t->set_file("main", "quotations_report.html");

	//filter
	$period		= GetParam("period");
	$start_date	= GetParam("start_date");
	$end_date	= GetParam("end_date");
	$project_id	= GetParam("project_id");
	$user_id	= GetParam("user_id");
	$submit		= GetParam("operation");
	$show_closed = GetParam("show_closed");
	$show_invoice = GetParam("show_invoice");
	$close		= GetParam("close");
	$task_id		= GetParam("task_id");	
	$sort = GetParam("sort");	
	$manager_id			= GetParam("user_id");
	$as="";$vs="";$ys="";

	if ($close || $task_id) {
		close_task($task_id, "");
	}

	if (!$submit && !$sort) {
		$period = 5;
	}

	$t->set_var("period", $period);

	$current_date = va_time();

	$today_date = date ("Y-m-d");
	$t->set_var("today_date", $today_date);

	$cyear = $current_date[0];
	$cmonth = $current_date[1];
	$cday = $current_date[2];
	
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

	$this_year_start = date ("Y-m-d", mktime (0, 0, 0, 1, 1, $cyear));
	$this_year_end   = date ("Y-m-d", mktime (0, 0, 0, 12, 31, $cyear));
	$t->set_var("this_year_start", $this_year_start);
	$t->set_var("this_year_end", $this_year_end);


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
				$start_date = $this_year_start;
				$end_date = $this_year_end;
				break;
			case 7:
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
	
	$t->set_var("start_date", $sd);
	$t->set_var("end_date", $ed);
	

	
	$t->set_var("user_id_selected", $manager_id);
	
	
	$t->set_var("project", $project_id);
	$t->set_var("show_closed", $show_closed);
	$t->set_var("show_invoice", $show_invoice);

	//make select list of projects

	$db2 = new DB_Sql;
	$db2->Database = DATABASE_NAME;
	$db2->User     = DATABASE_USER;
	$db2->Password = DATABASE_PASSWORD;
	$db2->Host     = DATABASE_HOST;

	$sql = "SELECT * FROM projects WHERE is_closed=0 AND parent_project_id IS NULL ORDER BY project_title";
	$db->query($sql);
	if($db->next_record()) {
	   	do {
	   		$t->set_var('project_title', $db->Record['project_title']);
			$t->set_var('project_id', $db->Record['project_id']);
			$t->set_var("selected", "");
			if ($db->Record["project_id"] == $project_id) {
				$t->set_var("selected", "selected");
			}

			$t->parse('projects', true);
			$sql2 = 'SELECT * FROM projects WHERE parent_project_id='.$db->Record['project_id'].' ORDER BY project_title';
	        $db2->query($sql2);
	        if ($db2->next_record()) {
	        	do {
	            	$t->set_var('project_title', "&nbsp;&nbsp;&nbsp;&nbsp;".$db2->Record['project_title']);
					$t->set_var('project_id', $db2->Record['project_id']);
					$t->set_var('selected', '');
					if ($db2->Record['project_id'] == $project_id)
					{
						$t->set_var('selected', 'selected');
					}
	                $t->parse('projects', true);
	            } while ($db2->next_record());
	        }
	    } while ($db->next_record());

		$t->set_var("no_projects", "");
	    //$t->parse("FormProjects", false);

	} else $t->set_var("projects", "");

	//end list of projects

	//begin list of managers
	$sql = " SELECT u.user_id, CONCAT(u.first_name, ' ', u.last_name) AS user_name ";
	$sql.= " FROM users u INNER JOIN tasks t ON (t.created_person_id=u.user_id AND t.task_type_id=4) ";
	$sql.= " GROUP BY u.user_id ORDER BY user_name";
	$db->query($sql);
	while ($db->next_record()) {
		$t->set_var('user_name', $db->Record['user_name']);
		$t->set_var('user_id', $db->Record['user_id']);
		$t->set_var("selected", "");
		if ($db->Record["user_id"] == $manager_id) {
			$t->set_var("selected", "selected");
		}

		$t->parse('users', true);
	} 

	CheckSecurity(1);

	
	$sql = 'SELECT
		t.task_title, CONCAT(u.first_name, \' \', u.last_name) as responsible_user,
		DATE_FORMAT(t.planed_date, \'%d %b %Y\') AS date_deadline,
		DATE_FORMAT(t.creation_date, \'%d %b %Y\') AS date_created,
		ts.status_desc as status_name,
		t.task_id as task_id, t.estimated_hours, p.project_title,
		IF (t.task_cost IS NOT NULL, CONCAT("$",ROUND(t.task_cost,0)), "") AS price, p.project_title, t.task_cost,	t.actual_hours, 		
		IF (TO_DAYS(t.planed_date) < TO_DAYS(now()) AND t.task_status_id NOT IN(4,19,25) AND t.is_closed=0,1,0 ) AS deadlined,
		
		st.task_title AS s_task_title,
		CONCAT(su.first_name, \' \', su.last_name) as s_responsible_user,
		DATE_FORMAT(st.planed_date, \'%d %b %Y\') AS s_date_deadline,
		DATE_FORMAT(st.creation_date, \'%d %b %Y\') AS s_date_created,
		sts.status_desc as s_status_name,
		st.task_id as sub_task_id,
		st.estimated_hours AS s_estimated_hours,
		sp.project_title AS s_project_title,
		IF (st.task_cost IS NOT NULL, CONCAT("$",ROUND(st.task_cost,0)), "") AS s_price,
		st.task_cost AS s_task_cost, st.actual_hours AS s_actual_hours,
		IF (TO_DAYS(st.planed_date) < TO_DAYS(now()) AND st.task_status_id!=4 AND st.task_type_id!=3 AND st.is_closed=0,1,0 ) AS s_deadlined
		';
	
$sql.= "	FROM tasks t INNER JOIN lookup_tasks_statuses ts ON (t.task_status_id = ts.status_id)
		LEFT JOIN users u ON t.responsible_user_id = u.user_id	
		LEFT JOIN projects p ON (p.project_id = t.project_id) 

		LEFT JOIN tasks st ON (st.parent_task_id = t.task_id AND st.task_type_id!=4)
		LEFT JOIN projects sp ON (sp.project_id = st.project_id)
		LEFT JOIN users su ON st.responsible_user_id = su.user_id
		LEFT JOIN lookup_tasks_statuses sts ON (st.task_status_id = sts.status_id)
";

//need to filter by subtasks creation date and deadline and closed subtasks//
$sql.= "	WHERE (t.task_type_id=4) ";


	if ($start_date){
		$sql .= ' AND (t.creation_date >= \''.$start_date.' 00:00:00\') ';
		
		//OR  t.planed_date >= \''.$start_date.' 00:00:00\' OR
			//	st.creation_date >= \''.$start_date.' 00:00:00\' OR  st.planed_date >= \''.$start_date.' 00:00:00\'
		
	}
	if ($end_date) {
		$sql .= ' AND (t.creation_date <= \''.$end_date.' 23:59:59\') ';
		
		//OR t.planed_date <= \''.$end_date.' 23:59:59\' OR 
		//st.creation_date <= \''.$end_date.' 23:59:59\' OR st.planed_date <= \''.$end_date.' 23:59:59\'
		//)';
	}
	if (is_numeric($project_id)) {
		$sql .= ' AND (t.project_id = '.$project_id.' OR p.parent_project_id = '.$project_id.')';
	}
	if (!($show_closed == 'show_closed')) {
		$sql .= ' AND (st.is_closed = 0 OR t.is_closed=0) ';
	}
	if (!($show_invoice == 'show_invoice')) {
		$sql .= ' AND (t.task_status_id != 25) ';
	}
	if (is_numeric($manager_id)) {
		$sql .= ' AND (t.created_person_id = '.$user_id.')';
	}
	
	
	$order_by = " p.project_title ";
	switch($sort) {
		case "project": 	$order_by = " p.project_title ";		break;
		case "task": 	$order_by = " t.task_title ";		break;
		case "person": 	$order_by = " responsible_user ";		break;
		case "status": 	$order_by = " status_name ";		break;
		case "estimate": 	$order_by = " t.estimated_hours DESC ";		break;
		case "price": 	$order_by = " t.task_cost DESC ";		break;
		case "created": 	$order_by = " t.creation_date ";		break;
		case "deadline": 	$order_by = " t.planed_date ";		break;
	}
	
	$sql .= ' ORDER BY '.$order_by." , t.task_id ";


/////

	$block_name = "records1";
	
	
	$i = 1;
	
	$estimate_total = 0;	// estimate of quotations
	$s_estimate_total = 0; //estimate of sub-tasks
	$price_total = 0;	//cost of quotations
	$s_price_total = 0; //cost of sub-tasks
	
	$parent_project = "";
	$previous_quotation = 0;
	$actual_hours = 0;
	$db->query($sql);
	if ($db->next_record()) {
		//fill $list array
		do {
			$list[] = $db->Record;
		} while ($db->next_record());
		
		
		
		foreach ($list as $row) {

			
			
			if ($previous_quotation!=$row["task_id"] && $previous_quotation>0) {

				$t->set_var("actual_hours", Hours2HoursMins($actual_hours));
				if ($actual_hours > $estimated_hours) {
					$t->set_var("actual_hours_style", "color:red;");
				} else {
					$t->set_var("actual_hours_style", "");
				}				
				$t->parse($block_name, true);			
				$t->set_var("task_records", "");
				$actual_hours = $row["actual_hours"];
				$estimated_hours = $row["estimated_hours"];							
			}			
			
			foreach ($row as $key => $item) {
				$t->set_var($key, $item == "NULL" ? '':$item);
			}			
			
			$parent_project = $row["project_title"];
			
	
			//parent
			if (isset($row['status'])) {
				$t->set_var('STATUS', $statuses_classes[$row['status']]);
			}
			
			if (isset($row['task_id'])) {
				$t->set_var('task_id', $row['task_id']);
				$i ++;
				$t->set_var('title_id', $i);
			}
			
			
			
			if (isset($row["estimated_hours"]) && floatval($row["estimated_hours"])>0  ) {
				
				$estimated_hours = floatval($row["estimated_hours"]);
				
				
				if ($previous_quotation!=$row["task_id"]) {
					$estimate_total+=$estimated_hours;
				}				
				
				if ($estimated_hours ==1 ) {
					$t->set_var("estimate", "1 hour");
				} else {
					$t->set_var("estimate", $estimated_hours." hours");
				}
			} else {
				$t->set_var("estimate", "");
			}			
			
			
			if (isset($row["task_cost"]) && floatval($row["task_cost"])>0 ) {
				if ($previous_quotation!=$row["task_id"]) {				
					$price_total+=$row["task_cost"];
				}
			}
			
			if (isset($row["deadlined"]) && $row["deadlined"]) {
				$t->set_var("deadline_style", "color:red;");
			} else {
				$t->set_var("deadline_style", "");
			}
			
			//sub
			
			if ($row["s_project_title"]==$parent_project) {
				$t->set_var("s_project_title", "");
			}
			
			if (isset($row['s_status'])) {
				$t->set_var('s_STATUS', $statuses_classes[$row['s_status']]);
			}
			
			if (isset($row['sub_task_id'])) {
				$t->set_var('s_task_id', $row['sub_task_id']);
				$i ++;
				$t->set_var('s_title_id', $i);
			}
			
			if (isset($row["s_actual_hours"])) {
				$t->set_var("s_actual_hours", Hours2HoursMins($row["s_actual_hours"]));
				$actual_hours +=$row["s_actual_hours"];
			}
			
			if (isset($row["s_estimated_hours"]) && floatval($row["s_estimated_hours"])>0  ) {
				
				$row["s_estimated_hours"] = floatval($row["s_estimated_hours"]);
				
				$s_estimate_total+=$row["s_estimated_hours"];
				
				if ($row["s_estimated_hours"] ==1 ) {
					$t->set_var("s_estimate", "1 hour");
				} else {
					$t->set_var("s_estimate", $row["s_estimated_hours"]." hours");
				}
			} else {
				$t->set_var("s_estimate", "");
			}
			if ($row["s_actual_hours"] > $row["s_estimated_hours"] && $row["s_estimated_hours"]>0) {
				$t->set_var("s_actual_hours_style", "color:red;");
			} else {
				$t->set_var("s_actual_hours_style", "");
			}
			
			
			if (isset($row["s_task_cost"]) && floatval($row["s_task_cost"])>0 ) {
				$s_price_total+=$row["s_task_cost"];
			}			
			
			if (isset($row["s_deadlined"]) && $row["s_deadlined"]) {
				$t->set_var("s_deadline_style", "color:red;");
			} else {
				$t->set_var("s_deadline_style", "");
			}
			
			
			if ($row["sub_task_id"]) {
				$t->parse("task_records", true);
			} else {
				$t->set_var("task_records", "");
			}
			
			$previous_quotation = $row["task_id"];
		}
		$t->set_var("actual_hours", Hours2HoursMins($actual_hours));
		if ($actual_hours > $estimated_hours) {			
			$t->set_var("actual_hours_style", "color:red;");
		} else {
			$t->set_var("actual_hours_style", "");
		}
		
		$t->parse($block_name, true);			
		
		$t->set_var("no_".$block_name, "");
	} else {
		$t->set_var($block_name, "");
		$t->parse("no_".$block_name, false);
	}
	
	$t->set_var("estimate_total", floatval($estimate_total)." hours");
	
	$t->set_var("price_total", "$".number_format(intval($price_total),0));


/////
	
	if ($show_closed) {
		$t->set_var('show_closed_checked', 'checked');
	} else {
		$t->set_var('show_closed_checked', '');
	}
	if ($show_invoice) {
		$t->set_var('show_invoice_checked', 'checked');
	} else {
		$t->set_var('show_invoice_checked', '');
	}

	$t->set_var("action", "quotations_report.php");
	$t->pparse("main");

?>
