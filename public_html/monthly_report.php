<?php

	include("./includes/date_functions.php");
	include("./includes/common.php");

	CheckSecurity(1);

	$person_selected	= GetParam("person_selected");
	$year_selected		= GetParam("year_selected");
	$month_selected		= GetParam("month_selected");
	$tab = GetParam("tab");	
	$sort = GetParam("sort");
	
	if (!$year_selected) {
		$year_selected = date("Y");
	}
	if (!$month_selected) {
		$month_selected = date("m");
		if ($month_selected>1) {
			$month_selected--;
			if ($month_selected<10) {
				$month_selected = "0".$month_selected;
			}
		} else {
			$month_selected = 12;
			$year_selected--;
		}
	}
	
	$grand_total_cost = 0;
	$grand_total_time = 0;

	$t = new iTemplate($sAppPath);
	$t->set_file("main","monthly_report.html");

	$t->set_var("months", GetMonthOptions($month_selected));
	$t->set_var("years", GetYearOptions(2004, date("Y"), $year_selected));
	$t->set_var("sort", $sort);
	$t->set_var("month_selected_value", $month_selected);
	$t->set_var("year_selected_value", $year_selected);
	
	if (!strlen($tab)) {
		$tab = "quot";
	}
	$t->set_var("current_tab", $tab);

		$sql = "SELECT ps.project_id, s.species, s.projects_price";
		$sql .= " FROM productivity_species s JOIN productivity_project_species ps";
		$sql .= " ON ps.species_id = s.species_id";
		$sql .= " WHERE s.projects_price != 0";

		$db->query($sql);
		$jobs_projects = array();
		$projects_cost = array();
		while ($db->next_record()) {
			$jobs_projects[$db->f("species")] = $db->f("project_id");
			$projects_cost[$db->f("project_id")] = $db->f("projects_price");
		}
	
		/* $jobs_projects = array(
			"Health Checks" => 38,
			"Check Tracking Viability" => 89,		
			"Trials" => 39,		
			"Paying Client Work" => 59,		
			"Full Account" => 53,		
			"One-Off" => 129,		
			"MSN" => 90,		
			"Yahoo" => 80,		
			"SEO Review" => 135,		
			"Premium video production" => 222,		
			"Video Distribution" => 224,
			"Standard video download" => 235,
			"Blogmesh" => 191
		);
		
		$projects_cost = array(
			38 => 7.5, 89 => 4.95, 39 => 30, 59 => 60, 53 => 120, 129=>120, 90=>30, 80=>30, 135=>15, 222=>60, 224=>30, 235=>7.5, 191 => 90
		); */
		$projects_ids = implode(", ", $jobs_projects);
	//////
	
	foreach($_POST as $key=>$value) {
		if (strpos($key, "hourly_charge_")!==false && $value>0) {
			$task_id = substr($key, 14);			
			$sql = "UPDATE tasks SET hourly_charge=15 WHERE task_id=".ToSQL($task_id, "number");
			$db->query($sql);
			$sql = "UPDATE tasks SET task_type_id=1 WHERE task_type_id=2 AND task_id=".ToSQL($task_id, "number");
			$db->query($sql);
		}
		
		if (strpos($key, "make_correction_")!==false && $value>0) {
			$task_id = substr($key, 16);
			$sql = "UPDATE tasks SET task_type_id=2, hourly_charge=NULL WHERE task_id=".ToSQL($task_id, "number");
			$db->query($sql);
		}
	}
	
	
		//Quotations summary
			$sqldata = "";
			
/*			if ($year_selected) { $sqldata .= " AND DATE_FORMAT(t.creation_date, '%Y')='$year_selected' ";}
			if ($month_selected) { $sqldata .= " AND DATE_FORMAT(t.creation_date, '%m')='$month_selected' ";}*/

			if ($year_selected) { $sqldata .= " AND DATE_FORMAT(m.message_date, '%Y')='$year_selected' ";}
			if ($month_selected) { $sqldata .= " AND DATE_FORMAT(m.message_date, '%m')='$month_selected' ";}
			
			
			$total_task_cost = 0;
			$total_estimated_hours = 0;
			$total_actual_hours = 0;
			
			$sql = " SELECT t.task_id, t.task_title, t.actual_hours,  SUM(st.actual_hours) AS quotation_actual_hours, ";
			$sql.= " t.estimated_hours, SUM(st.estimated_hours) AS quotation_estimated_hours, p.project_title, t.task_cost, ";
			$sql.= " DATE_FORMAT(m.message_date, '%e %b %y') AS date_invoiced, DATE_FORMAT(t.planed_date, '%e %b %y') AS date_deadline, ";
			$sql.= " IF ( ((m.message_date IS NOT NULL AND TO_DAYS(t.planed_date) < TO_DAYS(m.message_date)) OR (m.message_date IS NULL AND TO_DAYS(t.planed_date)< TO_DAYS(NOW())) ) ";
			$sql.= " ,1,0 ) AS deadlined, ";
			$sql.= " IF ( m.message_date IS NOT NULL AND TO_DAYS(t.planed_date) > TO_DAYS(m.message_date), 1, 0) AS greenlight ";
			$sql.= " FROM tasks t LEFT JOIN projects p ON (p.project_id=t.project_id) ";
			$sql.= " LEFT JOIN tasks st ON (st.parent_task_id = t.task_id AND st.task_type_id!=4) ";
			$sql.= " LEFT JOIN messages m ON (m.identity_id=t.task_id AND m.identity_type='task' AND m.status_id=25) ";
			$sql.= " WHERE t.task_type_id=4 ".$sqldata;
			$sql.= " AND t.task_status_id=25 ";//only invoiced
			$sql.= " GROUP BY t.task_id ";
			
			switch($sort) {
				case "task" :	$sql.= " ORDER BY t.task_title ASC ";break;
				case "actual" :	$sql.= " ORDER BY quotation_actual_hours DESC ";break;
				case "estimate" :	$sql.= " ORDER BY t.estimated_hours DESC ";break;
				case "cost" :	$sql.= " ORDER BY t.task_cost DESC, p.parent_project_id ASC, p.project_title ";break;
				case "deadline" :	$sql.= " ORDER BY t.planed_date DESC, p.parent_project_id ASC, p.project_title ";break;
				case "invoice" :	$sql.= " ORDER BY m.message_date DESC, p.parent_project_id ASC, p.project_title ";break;				
				default		:	$sql.= " ORDER BY p.parent_project_id, p.project_title ASC, t.creation_date ASC ";
			}
			
			$db->query($sql);
			if ($db->num_rows()) {
				while($db->next_record()) {
					$task_id = $db->f("task_id");
					$t->set_var("task_id", $task_id);
					$t->set_var("task_title", $db->f("task_title"));
					$t->set_var("project_title", $db->f("project_title"));
					$t->set_var("date_deadline", $db->f("date_deadline"));
					$t->set_var("date_invoiced", $db->f("date_invoiced"));
					$task_cost = intval($db->f("task_cost"));
					if ($task_cost > 0) {
						$t->set_var("task_cost", "$".number_format($task_cost,2));
					} else {
						$t->set_var("task_cost", "");
					}
					$actual_hours = $db->f("actual_hours") + $db->f("quotation_actual_hours");					
					$t->set_var("actual_hours", Hours2HoursMins($actual_hours));
					$estimated_hours = $db->f("estimated_hours"); // + $db->f("quotation_estimated_hours");
					
					if ($db->f("deadlined")) {
						$t->set_var("deadline_style", "color:red;");
					} elseif ($db->f("greenlight")) {
						$t->set_var("deadline_style", "color:green;");
					} else {
						$t->set_var("deadline_style", "");
					}
					
					if ($actual_hours > $estimated_hours) {
						$t->set_var("hours_style", "color:red;");
					} else {
						$t->set_var("hours_style", "color:green;");
					}
					

					
					$total_task_cost += $task_cost;
					$total_actual_hours += $actual_hours;
					$total_estimated_hours += $estimated_hours;

					if ($estimated_hours > 0) {
						$t->set_var("estimated_hours", Hours2HoursMins($estimated_hours));
					} else {
						$t->set_var("estimated_hours", "");
					}
					
					$t->parse("quotations_records", true);
				}

				if ($total_estimated_hours > 0) {
					$t->set_var("q_total_estimated_hours", Hours2HoursMins($total_estimated_hours));
				} else {
					$t->set_var("q_total_estimated_hours", "");
				}
				if ($total_actual_hours > 0) {
					$t->set_var("q_total_actual_hours", Hours2HoursMins($total_actual_hours));
					$grand_total_time += $total_actual_hours;
				} else {
					$t->set_var("q_total_actual_hours", "");
				}
				
				if ($total_task_cost > 0) {
					$t->set_var("q_total_task_cost", "$".number_format($total_task_cost,2));
					$grand_total_cost += $total_task_cost;
				} else {
					$t->set_var("q_total_task_cost", "");
				}
				
				
				
				$t->set_var("quotations_no_records", "");				
			} else {
				$t->set_var("q_total_task_cost", "");
				$t->set_var("q_total_actual_hours", "");
				$t->set_var("q_total_estimated_hours", "");
				$t->parse("quotations_no_records", false);
				$t->set_var("quotations_records", "");
			}
		$num_invoiced_quotations = $db->num_rows();
		$t->set_var("num_invoiced_quotations", $num_invoiced_quotations);
		$t->parse("quotations", false);
		
		//not invoiced quotations
//Quotations summary
			$sqldata = "";
			
			if ($year_selected) { $sqldata .= " AND DATE_FORMAT(m.message_date, '%Y')='$year_selected' ";}
			if ($month_selected) { $sqldata .= " AND DATE_FORMAT(m.message_date, '%m')='$month_selected' ";}
			
			$total_task_cost = 0;
			$total_estimated_hours = 0;
			$total_actual_hours = 0;
			
			$sql = " SELECT t.task_id, t.task_title, t.actual_hours,  SUM(st.actual_hours) AS quotation_actual_hours, ";
			$sql.= " t.estimated_hours, SUM(st.estimated_hours) AS quotation_estimated_hours, p.project_title, t.task_cost ";
			$sql.= " FROM tasks t LEFT JOIN projects p ON (p.project_id=t.project_id) ";
			$sql.= " LEFT JOIN tasks st ON (st.parent_task_id = t.task_id AND st.task_type_id!=4) ";
			$sql.= " LEFT JOIN messages m ON (m.identity_id=t.task_id AND m.identity_type='task') ";
			$sql.= " WHERE t.task_type_id=4 ".$sqldata;
			$sql.= " AND (t.task_status_id=19 OR t.task_status_id=4) AND (m.status_id=19 OR m.status_id=4) "; //25 is "invoiced", 19 is "job done";4 is "done"; t.is_closed=0 AND 
			
			$sql.= " GROUP BY t.task_id ";
			switch($sort) {
				case "task" :	$sql.= " ORDER BY t.task_title ASC ";break;
				case "actual" :	$sql.= " ORDER BY quotation_actual_hours DESC ";break;
				case "estimate" :	$sql.= " ORDER BY t.estimated_hours DESC ";break;
				case "cost" :	$sql.= " ORDER BY t.task_cost DESC, p.parent_project_id ASC, p.project_title ";break;
				case "deadline" :	$sql.= " ORDER BY t.planed_date DESC, p.parent_project_id ASC, p.project_title ";break;
				case "invoice" :	$sql.= " ORDER BY m.message_date DESC, p.parent_project_id ASC, p.project_title ";break;				
				default		:	$sql.= " ORDER BY p.parent_project_id, p.project_title ASC, t.creation_date ASC ";
			}			
			

			$db->query($sql);
			if ($db->num_rows()) {
				while($db->next_record()) {
					$task_id = $db->f("task_id");
					$t->set_var("task_id", $task_id);
					$t->set_var("task_title", $db->f("task_title"));
					$t->set_var("project_title", $db->f("project_title"));
					$task_cost = intval($db->f("task_cost"));
					if ($task_cost > 0) {
						$t->set_var("task_cost", "$".number_format($task_cost,2));
					} else {
						$t->set_var("task_cost", "");
					}
					$actual_hours = $db->f("actual_hours") + $db->f("quotation_actual_hours");					
					$t->set_var("actual_hours", Hours2HoursMins($actual_hours));
					$estimated_hours = $db->f("estimated_hours"); /* + $db->f("quotation_estimated_hours");*/
					
					$total_task_cost += $task_cost;
					$total_actual_hours += $actual_hours;
					$total_estimated_hours += $estimated_hours;
					
					if ($estimated_hours > 0) {
						$t->set_var("estimated_hours", Hours2HoursMins($estimated_hours));
					} else {
						$t->set_var("estimated_hours", "");
					}
					
					$t->parse("qinv_records", true);
				}

				if ($total_estimated_hours > 0) {
					$t->set_var("qinv_total_estimated_hours", Hours2HoursMins($total_estimated_hours));
				} else {
					$t->set_var("qinv_total_estimated_hours", "");
				}
				if ($total_actual_hours > 0) {
					$t->set_var("qinv_total_actual_hours", Hours2HoursMins($total_actual_hours));
//					$grand_total_time += $total_actual_hours;
				} else {
					$t->set_var("qinv_total_actual_hours", "");
				}
				if ($total_task_cost > 0) {
					$t->set_var("qinv_total_task_cost", "$".number_format($total_task_cost,2));
//					$grand_total_cost += $total_task_cost;
				} else {
					$t->set_var("qinv_total_task_cost", "");
				}
				
				
				
				$t->set_var("qinv_no_records", "");				
			} else {
				$t->set_var("qinv_total_estimated_hours", "");
				$t->set_var("qinv_total_actual_hours", "");
				$t->set_var("qinv_total_task_cost", "");
				$t->parse("qinv_no_records", false);
				$t->set_var("qinv_records", "");
			}
		$num_not_invoiced_quotations = $db->num_rows();
		$t->set_var("num_not_invoiced_quotations", $num_not_invoiced_quotations);
		$t->parse("qinvs", false);		
		
			
		// 	rated tasks
		$sqldata = "";
		$prev_task_id = 0;
		$people_list = "";
		$total_task_cost = 0;
		$total_actual_hours = 0;
		$task_cost = 0;
		$actual_hours = 0;
		$task_spent_hours = 0;
		
		$num_tasks_with_hourly = 0;
		if ($year_selected) { $sqldata .= " AND DATE_FORMAT(tr.started_date, '%Y')='$year_selected' ";}
		if ($month_selected) { $sqldata .= " AND DATE_FORMAT(tr.started_date, '%m')='$month_selected' ";}
		
			$sql = " SELECT t.task_id, t.task_title, t.actual_hours, t.estimated_hours, p.project_id, p.project_title ";
			$sql.= " , t.hourly_charge, t.task_cost, CONCAT(u.first_name, ' ', u.last_name) AS user_name, SUM(tr.spent_hours) AS user_spent_hours ";
			$sql.= " FROM tasks t LEFT JOIN projects p ON (p.project_id=t.project_id) ";
			$sql.= " LEFT JOIN time_report tr ON (tr.task_id = t.task_id) ";
			$sql.= " LEFT JOIN users u ON (u.user_id = tr.user_id) ";
			$sql.= " WHERE t.task_type_id!=4 ".$sqldata. " AND t.hourly_charge IS NOT NULL ";
			
			$sql.= " GROUP BY t.task_id, tr.user_id ";
			switch($sort) {
				case "task" :	$sql.= " ORDER BY t.task_title ASC ";break;
				case "actual" : case "cost" : $sql.= " ORDER BY t.actual_hours DESC ";break;
				default		:	$sql.= " ORDER BY p.parent_project_id, p.project_title ASC, t.creation_date ASC, user_name ASC ";
			}			
			
			$db->query($sql);
			if ($db->num_rows()) {
				$t->set_var("tasks_records", "");
				while($db->next_record()) {
					$task_id = $db->f("task_id");
					$hourly_charge = $db->f("hourly_charge");	
					if ($hourly_charge == 1) {
						$hourly_charge = 15;
					}
					$actual_hours = $db->f("actual_hours");					
					if ($prev_task_id!=$task_id && $prev_task_id>0) {
						
						$t->set_var("actual_hours", Hours2HoursMins($task_spent_hours));
						$t->set_var("task_cost", "$".number_format($task_spent_hours * $hourly_charge,2));
						$t->set_var("people_list", $people_list);
						$t->parse("tasks_records", true);
						$num_tasks_with_hourly++;
						$people_list = "";
						$total_task_cost += $task_spent_hours * $hourly_charge;
						$total_actual_hours += $task_spent_hours;
						$task_spent_hours = 0;
					}
					$t->set_var("task_id", $task_id);
					$t->set_var("task_title", $db->f("task_title"));
					$t->set_var("project_title", $db->f("project_title"));			
					//$t->set_var("actual_hours", Hours2HoursMins($actual_hours));				
					
					if (strlen($people_list)) {
						$people_list.= ", ";
					}
					$people_list.= $db->f("user_name");			
					$task_spent_hours  += $db->f("user_spent_hours");
					$prev_task_id = $task_id;					
				}				
				$t->set_var("task_cost", "$".number_format($task_spent_hours * $hourly_charge,2));
				$t->set_var("actual_hours", Hours2HoursMins($task_spent_hours));
				$t->set_var("people_list", $people_list);
				$t->parse("tasks_records", true);
				$num_tasks_with_hourly++;
				$total_task_cost += $task_spent_hours * $hourly_charge;

				$total_actual_hours += $task_spent_hours;
				if ($total_actual_hours > 0) {
					$t->set_var("tw_total_actual_hours", Hours2HoursMins($total_actual_hours));
					$grand_total_time += $total_actual_hours;
				} else {
					$t->set_var("tw_total_actual_hours", "");
				}
				if ($total_task_cost > 0) {
					$t->set_var("tw_total_task_cost", "$".number_format($total_task_cost,2));
					$grand_total_cost += $total_task_cost;
				} else {
					$t->set_var("tw_total_task_cost", "");
				}			
				
				$t->set_var("tasks_no_records", "");				
			} else {
				$t->set_var("tw_total_task_cost", "");
				$t->set_var("tw_total_actual_hours", "");
				$t->parse("tasks_no_records", false);
				$t->set_var("tasks_records", "");
			}	
			$t->set_var("num_tasks_with_hourly", $num_tasks_with_hourly);
			$t->parse("tasks_w_table", false);
			
// 	not rated tasks
		$sqldata = "";
		$prev_task_id = 0;
		$prev_project_title = 0;
		$people_list = "";
		$total_task_cost = 0;
		$total_actual_hours = 0;
		$num_tasks_without_hourly = 0;
		$actual_hours = 0;
		$task_spent_hours = 0;
		if ($year_selected) { $sqldata .= " AND DATE_FORMAT(tr.started_date, '%Y')='$year_selected' ";}
		if ($month_selected) { $sqldata .= " AND DATE_FORMAT(tr.started_date, '%m')='$month_selected' ";}
		
			$sql = " SELECT t.task_id, t.task_title, t.actual_hours, t.estimated_hours, p.project_id, p.project_title ";
			$sql.= " , t.hourly_charge, t.task_cost, CONCAT(u.first_name, ' ', u.last_name) AS user_name, SUM(tr.spent_hours) AS user_spent_hours, pt.task_id AS quotation_id ";
			$sql.= " FROM tasks t LEFT JOIN projects p ON (p.project_id=t.project_id) ";
			$sql.= " LEFT JOIN tasks pt ON (pt.task_id=t.parent_task_id AND pt.task_type_id=4) ";
			$sql.= " LEFT JOIN time_report tr ON (tr.task_id = t.task_id) ";
			$sql.= " LEFT JOIN users u ON (u.user_id = tr.user_id) ";
			$sql.= " WHERE t.task_type_id NOT IN (2,4) ".$sqldata. " AND t.hourly_charge IS NULL AND pt.task_id IS NULL AND (t.task_cost IS NULL OR t.task_cost=0) ";
			$sql.= " AND p.project_id NOT IN (113,213) AND p.project_id NOT IN (".$projects_ids.") AND p.parent_project_id NOT IN (19, 138) ";//not viart, viart web clients
			//not tickets and articles projects			
			$sql.= " GROUP BY t.task_id, tr.user_id ";
			switch($sort) {
				case "task" :	$sql.= " ORDER BY t.task_title ASC, t.creation_date ASC, user_name ASC ";break;
				case "actual" :	case "cost": $sql.= " ORDER BY t.actual_hours DESC ";break;
				default		:	$sql.= " ORDER BY p.parent_project_id, p.project_title ASC, t.task_title, t.creation_date ASC, user_name ASC ";
			}			
			
			$db->query($sql);
			if ($db->num_rows()) {
				$t->set_var("tasks_records", "");
				while($db->next_record()) {
					$task_id = $db->f("task_id");
					$hourly_charge = $db->f("hourly_charge");	
					$task_cost = round($db->f("task_cost"), 2);
					$actual_hours = $db->f("actual_hours");
					
					if ($prev_task_id!=$task_id && $prev_task_id>0) {
						$t->set_var("actual_hours", Hours2HoursMins($task_spent_hours));
						$t->set_var("people_list", $people_list);
						if ($project_title != strval($prev_project_title)) {
							$t->set_var("project_title", $project_title);	
						} else {
							$t->set_var("project_title", "");			
						}						
						$num_tasks_without_hourly++;
						$t->parse("nrtasks_records", true);
						$people_list = "";
						$total_task_cost += $task_cost;						
						$total_actual_hours += $task_spent_hours;						
						$prev_project_title = $project_title;	
						$task_spent_hours = 0;					
						
					}
					$t->set_var("task_id", $task_id);
					
					$task_title = $db->f("task_title");					
					if (strlen($task_title)>75) {
						$task_title = substr($task_title,0,72)."...";
					}
					
					$project_title = $db->f("project_title");
					if ($prev_project_title == "") {
						$t->set_var("project_title", $project_title);
					}
					
					$t->set_var("project_id", $db->f("project_id"));
					$t->set_var("task_title", $task_title);
					$task_spent_hours += $db->f("user_spent_hours");
									
					if ($task_cost>0) {
						$t->set_var("task_cost", "$".number_format($task_cost,2));
					} else {
						$t->set_var("task_cost", "");
					}
					if (strlen($people_list)) {
						$people_list.= ", ";
					}
					$people_list.= $db->f("user_name");			
					$prev_task_id = $task_id;				
					
				}
				if ($prev_project_title == $project_title) {
					$t->set_var("project_title", "");
				} else {
					$t->set_var("project_title", $project_title);
				}
				$t->set_var("actual_hours", Hours2HoursMins($task_spent_hours));
				$t->set_var("people_list", $people_list);
				$num_tasks_without_hourly++;
				$t->parse("nrtasks_records", true);
				$total_task_cost += $task_cost;
				$total_actual_hours += $task_spent_hours;				
				
				if ($total_actual_hours > 0) {
					$t->set_var("two_total_actual_hours", Hours2HoursMins($total_actual_hours));
					$grand_total_time += $total_actual_hours;
				} else {
					$t->set_var("two_total_actual_hours", "");
				}
				if ($total_task_cost > 0) {
					$t->set_var("two_total_task_cost", "$".$total_task_cost);
					$grand_total_cost += $total_task_cost;
				} else {
					$t->set_var("two_total_task_cost", "");
				}
				$t->set_var("nrtasks_no_records", "");				
			} else {
				$t->set_var("two_total_task_cost", "");
				$t->set_var("two_total_actual_hours", "");
				$t->parse("nrtasks_no_records", false);
				$t->set_var("nrtasks_records", "");
			}	
			$t->set_var("num_tasks_without_hourly", $num_tasks_without_hourly);
		
	$t->parse("tasks_wo_table", false);
	
	
// 	corrections
		$sqldata = "";
		$prev_task_id = 0;
		$prev_project_title = 0;
		$people_list = "";
		$total_task_cost = 0;
		$total_actual_hours = 0;
		$num_corrections = 0;
		$actual_hours = 0;
		$task_spent_hours = 0;
		if ($year_selected) { $sqldata .= " AND DATE_FORMAT(tr.started_date, '%Y')='$year_selected' ";}
		if ($month_selected) { $sqldata .= " AND DATE_FORMAT(tr.started_date, '%m')='$month_selected' ";}
		
			$sql = " SELECT t.task_id, t.task_title, t.actual_hours, t.estimated_hours, p.project_id, p.project_title ";
			$sql.= " , t.hourly_charge, t.task_cost, CONCAT(u.first_name, ' ', u.last_name) AS user_name, SUM(tr.spent_hours) AS user_spent_hours, pt.task_id AS quotation_id ";
			$sql.= " FROM tasks t LEFT JOIN projects p ON (p.project_id=t.project_id) ";
			$sql.= " LEFT JOIN tasks pt ON (pt.task_id=t.parent_task_id AND pt.task_type_id=4) ";
			$sql.= " LEFT JOIN time_report tr ON (tr.task_id = t.task_id) ";
			$sql.= " LEFT JOIN users u ON (u.user_id = tr.user_id) ";
			$sql.= " WHERE t.task_type_id=2 ".$sqldata. " AND t.hourly_charge IS NULL AND pt.task_id IS NULL AND (t.task_cost IS NULL OR t.task_cost=0) ";
			$sql.= " AND p.project_id NOT IN (113,213) AND p.project_id NOT IN (".$projects_ids.") AND p.parent_project_id NOT IN (19, 138) ";//not viart, viart web clients
			//not tickets and articles projects			
			$sql.= " GROUP BY t.task_id, tr.user_id ";
			switch($sort) {
				case "task" :	$sql.= " ORDER BY t.task_title ASC ";break;
				case "actual" :	case "cost": $sql.= " ORDER BY t.actual_hours DESC ";break;
				default		:	$sql.= " ORDER BY p.parent_project_id, p.project_title ASC, t.task_title, t.creation_date ASC, user_name ASC ";
			}			
			
			$db->query($sql);
			if ($db->num_rows()) {
				$t->set_var("corrections_records", "");
				while($db->next_record()) {
					$task_id = $db->f("task_id");
					$hourly_charge = $db->f("hourly_charge");	
					$task_cost = round($db->f("task_cost"), 2);
					$actual_hours = $db->f("actual_hours");
					
					if ($prev_task_id!=$task_id && $prev_task_id>0) {
						$t->set_var("actual_hours", Hours2HoursMins($task_spent_hours));
						$t->set_var("people_list", $people_list);
						if ($project_title != strval($prev_project_title)) {
							$t->set_var("project_title", $project_title);	
						} else {
							$t->set_var("project_title", "");			
						}						
						$num_corrections++;
						$t->parse("corrections_records", true);
						$people_list = "";
						$total_task_cost += $task_cost;						
						$total_actual_hours += $task_spent_hours;						
						$prev_project_title = $project_title;	
						$task_spent_hours = 0;					
						
					}
					$t->set_var("task_id", $task_id);
					
					$task_title = $db->f("task_title");					
					if (strlen($task_title)>75) {
						$task_title = substr($task_title,0,72)."...";
					}
					
					$project_title = $db->f("project_title");
					if ($prev_project_title == "") {
						$t->set_var("project_title", $project_title);
					}
					
					$t->set_var("project_id", $db->f("project_id"));
					$t->set_var("task_title", $task_title);
					$task_spent_hours += $db->f("user_spent_hours");
									
					if ($task_cost>0) {
						$t->set_var("task_cost", "$".$task_cost);
					} else {
						$t->set_var("task_cost", "");
					}
					if (strlen($people_list)) {
						$people_list.= ", ";
					}
					$people_list.= $db->f("user_name");			
					$prev_task_id = $task_id;				
					
				}
				if ($prev_project_title == $project_title) {
					$t->set_var("project_title", "");
				} else {
					$t->set_var("project_title", $project_title);
				}
			
				$t->set_var("actual_hours", Hours2HoursMins($task_spent_hours));
				$t->set_var("people_list", $people_list);
				$num_corrections++;
				$t->parse("corrections_records", true);
				$total_task_cost += $task_cost;
				$total_actual_hours += $actual_hours;				
				
				if ($total_actual_hours > 0) {
					$t->set_var("c_total_actual_hours", Hours2HoursMins($total_actual_hours));
					$grand_total_time += $total_actual_hours;
				} else {
					$t->set_var("c_total_actual_hours", "");
				}
				if ($total_task_cost > 0) {
					$t->set_var("c_total_task_cost", "$".$total_task_cost);
					$grand_total_cost += $total_task_cost;
				} else {
					$t->set_var("c_total_task_cost", "");
				}
				$t->set_var("corrections_no_records", "");				
			} else {
				$t->set_var("c_total_task_cost", "");
				$t->set_var("c_total_actual_hours", "");
				$t->parse("corrections_no_records", false);
				$t->set_var("corrections_records", "");
			}	
			$t->set_var("num_corrections", $num_corrections);
		
	$t->parse("tasks_c_table", false);	
	
	
// 	jobs;
		$sqldata = "";
		if ($year_selected) {  $sqldata .= " AND YEAR(t.creation_date)  = '$year_selected' ";}
		if ($month_selected) { $sqldata .= " AND MONTH(t.creation_date) = '$month_selected' ";}
		$projects_stats = array();
		$tasks_stats    = array();
		
		$total_jobs_number = 0 ;
		$total_job_cost = 0;
		$total_jobs_time = 0;
		
		$sql = " SELECT t.project_id, COUNT(t.task_id) AS jobs_number, SUM(t.actual_hours) AS jobs_time FROM tasks t ";
		$sql.= " WHERE t.task_type_id!=4 " . $sqldata . " AND t.project_id IN (" . $projects_ids . ") ";
		$sql.= " GROUP BY t.project_id ";
		
		$db->query($sql);
		while($db->next_record()) {
			$project_id  = $db->f("project_id");
			$jobs_number = $db->f("jobs_number");
			$jobs_cost   = $jobs_number * $projects_cost[$project_id];
			$projects_stats[$project_id] = array($jobs_number, $jobs_cost, $db->f("jobs_time"));
		}
		
		$sql = " SELECT t.project_id, t.task_id, t.actual_hours, t.task_cost, t.task_title, DATE_FORMAT(t.creation_date,'%e %b') AS date_created FROM tasks t ";
		$sql.= " WHERE t.task_type_id!=4 ".$sqldata." AND t.project_id IN (" . $projects_ids . ") ";
		$sql.= " ORDER BY t.project_id, t.actual_hours DESC, t.task_title ASC ";
		$db->query($sql);
		while($db->next_record()) {
			$project_id = $db->f("project_id");
			if (!isset($tasks_stats[$project_id])) {
				$tasks_stats[$project_id] = array();
			}
			$tasks_stats[$project_id][] = $db->Record;
		}
		
		foreach($jobs_projects as $job_type => $project_id) {
			$t->set_var("job_type", $job_type);
			$t->set_var("project_id", $project_id);
			if (isset($projects_stats[$project_id])) {
				$t->set_var("jobs_number", intval($projects_stats[$project_id][0]));
				$total_jobs_time +=floatval($projects_stats[$project_id][2]);
				$total_jobs_number +=intval($projects_stats[$project_id][0]);
				$total_job_cost +=floatval($projects_stats[$project_id][1]);
				$t->set_var("job_cost", "$".number_format(floatval($projects_stats[$project_id][1]),2));
				//$t->set_var("average_job_cost", "$".number_format(floatval($projects_stats[$project_id][1])/intval($projects_stats[$project_id][0]), 2));
			} else {
				$t->set_var("jobs_number", "0");
				$t->set_var("job_cost", "$0.00");				
				//$t->set_var("average_job_cost", "$0.00");	
			}
			
			if (isset($tasks_stats[$project_id]) && sizeof($tasks_stats[$project_id])) {
				foreach($tasks_stats[$project_id] as $task_data) {
					$t->set_var("task_id", $task_data["task_id"]);
					$total_jobs_time+=
					$t->set_var("task_actual_hours", Hours2HoursMins($task_data["actual_hours"]));
					$task_title = $task_data["task_title"];
					if (strlen($task_title)>55) {
						$task_title = substr($task_title,0,52)."...";
					}
					$t->set_var("task_title_short", $task_title);
					$t->set_var("task_date_created", $task_data["date_created"]);
					
					$t->parse("jobs_task_record", true);
				}
				
				$t->parse("jobs_tasks_records", false);
				$t->parse("tasks_roll", false);
			} else {
				$t->set_var("jobs_task_record", "");
				$t->set_var("jobs_tasks_records", "");
				$t->set_var("tasks_roll", "");
			}
			
			$t->parse("jobs_records", true);
			$t->set_var("jobs_task_record", "");
			$t->set_var("jobs_tasks_records", "");
		}
		
		$t->set_var("total_jobs_number", $total_jobs_number);
		$t->set_var("total_jobs_time", Hours2HoursMins($total_jobs_time));
		$t->set_var("total_jobs_cost", "$".number_format($total_job_cost,2));
		$t->set_var("jobs_no_records", "");				

		$grand_total_number = $num_invoiced_quotations + /* $num_not_invoiced_quotations + */ $num_tasks_with_hourly + $num_tasks_without_hourly + $num_corrections + $total_jobs_number;
		$t->set_var("grand_total_number", $grand_total_number);
		$t->set_var("grand_total_cost", "$".number_format($grand_total_cost + $total_job_cost,2));
		$t->set_var("grand_total_time", Hours2HoursMins($grand_total_time + $total_jobs_time));
		

$t->pparse("main");

?>