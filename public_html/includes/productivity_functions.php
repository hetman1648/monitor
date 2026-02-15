<?php	
	//type projects
	define("SPECIES_CUSTOME", 1);
	define("SPECIES_VIART", 2);
	define("SPECIES_SAYU", 3);
	define("SPECIES_TICKET", 4);
	define("SPECIES_MANUAL", 5);
	
	define("CORRECTION_TYPE_ID", 2);
	define("SEO_CORRECTIONS_PROJECT_ID", 116);
	
	
	$_PROJECTS_WITH_FULL_COST = array(191);
	
	/*	
$sql  = " INSERT INTO productivity_project_species ";
	$sql .= " (project_id, species_id) VALUES (263, 3) ";
	$db->query($sql);
	
	$sql  = " INSERT INTO productivity_project_species ";
	$sql .= " (project_id, species_id) VALUES (64, 2) ";
	$db->query($sql);
*/
	function calculate_all ($__TEAM, $__USER_THRES, $__USER_COEFF, $__TEAM_THRES, $__TEAM_COEFF, 
		$__CUSTOME_WORK, $__VIART, $__SAYU, $__MANUALS, $__DAYS_POINTS, $__VACATIONS, $__BUGS, $__USE_STP_FUNCTION = false,  $_RATE = 1, $_DEBUG = false) {
		global $db, $t, $users;
		global $global_manager_bonus, $global_manager_bonus_desc;

		if (!isset($global_manager_bonus)) {
			$global_manager_bonus      = 0;
			$global_manager_bonus_desc = "";
		}
		
		$t->set_var("row", "");
		$year_selected  = (int) GetParam("year_selected");	
		if (!$year_selected) {
			$year_selected = date("Y");
		}
		$month_selected = (int) GetParam("month_selected");
		if (!$month_selected) {
			$month_selected = date("n");
		}	
		if ($year_selected == date("Y") && $month_selected == date("n")) {
			$day_end = date("j");
		}
	
		$t->set_var("tickets_cell", "");	
		$t->set_var("viart_cell", "");
		$t->set_var("sayu_cell", "");
		$t->set_var("manual_cell", "");
		$t->set_var("special_cell", "");
		$t->set_var("sayu_custome_work_table", "");
		$t->set_var("viart_custome_work_table", "");
		
		$sum_total_points = 0;
		$manager_id = 0;
		$users = array();
		foreach ($__TEAM AS $user_id) {			
			$sql = " SELECT CONCAT(first_name, ' ', last_name) AS user_name, is_flexible, manager_id FROM users u ";
			$sql.= " WHERE user_id = ". ToSQL($user_id, "number", false);
			$sql.= " ORDER BY manager_id";
			$db->query($sql);
			if($db->next_record()) {
				if ($db->f("manager_id")<=0) {
					$manager_id = $user_id;
				}
				$users[$user_id]["name"] = $db->f("user_name");
				$users[$user_id]["is_flexible"] = $db->f("is_flexible");
			}
			if ($_DEBUG) {
				echo 'processing user_id ' . $user_id . ' ' . $users[$user_id]["name"] . '<br/>';
			}
			
			$users[$user_id]["notes_text"] = "";
			$users[$user_id]["used_ids"]   = "";
			
			$users[$user_id]["paid_days"]    = round(select_vacation($user_id, $year_selected, $month_selected, $days_in_month) * $__VACATIONS, 2);
			$users[$user_id]["bugs"]         = round(select_bugs($user_id, $year_selected, $month_selected) * $__BUGS, 2);
			
			foreach ($users AS $user_id => $user) {
				$func = "special_for_user_" . $user_id;
				if (function_exists($func)) {
					$users[$user_id]["special"] = $func(&$users, $year_selected, $month_selected);
				}
			}
			
			if (isset($__CUSTOME_WORK) && $__CUSTOME_WORK) {
				$users[$user_id]["custome_work"] = 0;
				custome_work($users, $user_id, $year_selected, $month_selected);
			}
			if (isset($__VIART) && $__VIART) {
				if ($__VIART > 0) {
					$users[$user_id]["viart"]  = round(select_hours(SPECIES_VIART, $users, $user_id, $year_selected, $month_selected) * $__VIART, 2);
				} else {
					sayu_custome_work($users, $user_id, $year_selected, $month_selected, "viart", $_DEBUG);
				}
			}
			if (isset($__SAYU)) {				
				if ($__SAYU > 0) {
					if ($_DEBUG) {
						echo 'sayu points by hours<br/>';
					}
					$users[$user_id]["sayu"] = round(select_hours(SPECIES_SAYU, $users, $user_id, $year_selected, $month_selected) * $__SAYU, 2);
				} elseif ($__SAYU < 0) {
					if ($_DEBUG) {
						echo 'sayu points by projects<br/>';
					}
					sayu_custome_work($users, $user_id, $year_selected, $month_selected, "sayu", $_DEBUG);
				}
			}			
			if (isset($__MANUALS) && $__MANUALS) {
				if (isset($users[$user_id]["manual_hours"])) {
					$users[$user_id]["manual"] = round($users[$user_id]["manual_hours"] * $__MANUALS, 2);
				} else {
					$users[$user_id]["manual"] = round(select_hours(SPECIES_MANUAL, $users, $user_id, $year_selected, $month_selected) * $__MANUALS, 2);
				}
			}
			
			if (isset($__DAYS_POINTS) && $__DAYS_POINTS) {
				$users[$user_id]["tickets"] = 0;
				$users[$user_id]["tickets_points"] = 0;
				$users[$user_id]["tickets_delayed"] = 0;
				$users[$user_id]["tickets_delayed_points"] = 0;
				if (function_exists("special_tickets_points") && $__USE_STP_FUNCTION) {
					special_tickets_points($users, $user_id, $year_selected, $month_selected);
				} else {
					tickets_points($users, $user_id, $year_selected, $month_selected, $__DAYS_POINTS);
				}
			}
			
			other_work($users, $user_id, $year_selected, $month_selected);
									
			$users[$user_id]["total_points"] = 0;			
			$users[$user_id]["total_points"] += $users[$user_id]["tickets_points"];
			$users[$user_id]["total_points"] += $users[$user_id]["tickets_delayed_points"];
			$users[$user_id]["total_points"] += $users[$user_id]["paid_days"];
			$users[$user_id]["total_points"] += $users[$user_id]["bugs"];
			
			if (isset($users[$user_id]["custome_work"]))
				$users[$user_id]["total_points"] += $users[$user_id]["custome_work"];
				
			if (isset($users[$user_id]["viart"]))
				$users[$user_id]["total_points"] += $users[$user_id]["viart"];
							
			if (isset($users[$user_id]["sayu"]))			
				$users[$user_id]["total_points"] += $users[$user_id]["sayu"];
				
			if (isset($users[$user_id]["manual"]))
				$users[$user_id]["total_points"] += $users[$user_id]["manual"];
				
			if (isset($users[$user_id]["special"]))
				$users[$user_id]["total_points"] += $users[$user_id]["special"];
			
			$sum_total_points += $users[$user_id]["total_points"];
				
			
			$current_thres = $__USER_THRES;
			if ($users[$user_id]["is_flexible"]) {		
				$sql  = " SELECT SUM(spent_hours) AS total_spent_hours ";
				$sql .= " FROM time_report ";
				$sql .= " WHERE user_id=" . ToSQL($user_id, "integer");
				$sql .= " AND YEAR(report_date) = '$year_selected'";
				$sql .= " AND MONTH(report_date) = '$month_selected' ";
				$db->query($sql,__FILE__,__LINE__);	
				if ($db->next_record()) {
					$total_user_hours = $db->f(0);
					if ($total_user_hours < 160) {
						$current_thres    = $__USER_THRES * ($total_user_hours / 160);
					}
				}
			}
			$bonus = round( ($users[$user_id]["total_points"] - $current_thres) * $__USER_COEFF, 2);
			
			if ($bonus > 0) {
				$users[$user_id]["bonus"] = $bonus * $_RATE;
			} else {
				$users[$user_id]["bonus"] = "0.00";
			}
			$users[$user_id]["bonus_desc"] = "";
		}
		
		$t->set_var("manager_bonus_block", "");		
		$manager_bonus = 0;		
		if (function_exists("special_team_bonus")) {
			$manager_bonus = special_team_bonus($users, $manager_id) * $_RATE;
			if ($manager_id && $manager_bonus) {
				$users[$manager_id]["bonus_desc"] .= "<br/>" . $users[$manager_id]["bonus"] . " + " . $global_manager_bonus_desc . $manager_bonus;
				$users[$manager_id]["bonus"] += $global_manager_bonus + $manager_bonus;
			}
		} elseif ($sum_total_points > $__TEAM_THRES) {
			$manager_bonus = round ( ($sum_total_points - $__TEAM_THRES ) * $__TEAM_COEFF, 2) * $_RATE;
			if ($manager_id && $manager_bonus) {
				$users[$manager_id]["bonus_desc"] .= "<br/>" . $users[$manager_id]["bonus"] . " + " . $global_manager_bonus_desc . $manager_bonus;
				$users[$manager_id]["bonus"] += $global_manager_bonus + $manager_bonus;
			}
		}
		
		$global_manager_bonus      += $manager_bonus;
		$global_manager_bonus_desc .= $manager_bonus . " + ";
		
		if ($manager_bonus) {
			$t->set_var("manager_bonus", $manager_bonus);
			$t->parse("manager_bonus_block");
		}
	
		$sums = array();
		foreach ($users AS $user_id => $user) {
			$t->set_var("special_cell", "");
			$t->set_var("user_id", $user_id);
			foreach ($user AS $title => $value) {
				$t->set_var($title . "_cell", "");
				$t->set_var($title, $value);
				$t->parse($title . "_cell", false);
				if (isset($sums["sum_" . $title])) {
					$sums["sum_" . $title] += $value;
				} else {
					$sums["sum_" . $title] = $value;
				}
			}
			if (!$user["other_work"]) {
				$t->set_var("other_work_cell", "");
			}
			$t->set_var("notes_block", "");
			if (isset($user["notes_text"]) && $user["notes_text"]) {
				$t->parse("notes_block");
			}
			if (!$__DAYS_POINTS) {
				$t->set_var("tickets_delayed_points_cell", "");
			}
			$t->parse("row");
		}
				
		foreach ($sums AS $title => $value) {
			$t->set_var($title, $value);
		}
		
		if (isset($__VIART) && $__VIART) {
			$t->parse("viart_header", false);
			$t->parse("viart_footer", false);
		} else {
			$t->set_var("viart_header", "");			
			$t->set_var("viart_footer", "");
		}
			
		if ($__DAYS_POINTS) {
			$t->parse("tickets_header", false);
			$t->parse("tickets_footer", false);
		} else {
			$t->set_var("tickets_header", "");
			$t->set_var("tickets_footer", "");
		}
		
		if (isset($__SAYU) && $__SAYU) {
			$t->parse("sayu_header", false);
			$t->parse("sayu_footer", false);
		} else {
			$t->set_var("sayu_header", "");
			$t->set_var("sayu_footer", "");
		}

		if (isset($__MANUALS) && $__MANUALS) {
			$t->parse("manual_header", false);
			$t->parse("manual_footer", false);
		} else {
			$t->set_var("manual_header", "");
			$t->set_var("manual_footer", "");
		}
	}

	
	function other_work(&$users, $user_id, $year_selected, $month_selected) {
		global $db, $t;
		
		$sql  = " SELECT t.task_id, t.task_title, t.task_cost, SUM(tr.spent_hours) AS spent_hours, t.actual_hours, t.completion ";
		$sql .= " FROM (( tasks t ";
		$sql .= " INNER JOIN time_report tr ON tr.task_id = t.task_id) ";
		$sql .= " INNER JOIN projects p ON t.project_id = p.project_id) ";
		$sql .= " WHERE tr.user_id=" . ToSQL($user_id, "integer");
		if ($users[$user_id]["used_ids"]) {
			$sql .= " AND t.task_id NOT IN (" . $users[$user_id]["used_ids"] . ") ";
		}
		$sql .= " AND YEAR(tr.report_date) = '$year_selected'";
		$sql .= " AND MONTH(tr.report_date) = '$month_selected' ";
		$sql .= " GROUP BY t.task_id ";
		$db->query($sql,__FILE__,__LINE__);		
		
		$other_work = 0;
		if ($db->next_record()) {
			$t->set_var("other_work_row", "");
			do {
				$task_id      = $db->f("task_id");
				$task_title   = $db->f("task_title");
				$task_cost    = $db->f("task_cost");
				$spent_hours  = $db->f("spent_hours");
				$actual_hours = $db->f("actual_hours");
				$completion   = $db->f("completion");

				$t->set_var("task_id", $task_id);
				$t->set_var("task_title", $task_title);
				$t->set_var("task_cost", $task_cost);
				$t->set_var("spent_hours", Hours2HoursMins($spent_hours));
				$t->set_var("actual_hours", Hours2HoursMins($actual_hours));
				$t->set_var("completion", $completion);
				
				$t->parse("other_work_row");
				$other_work ++;
			} while($db->next_record());
			$t->set_var("user_id", $user_id);
			$t->set_var("name", $users[$user_id]["name"]);
			$t->parse("other_work_table");
		}
		$users[$user_id]["other_work"] = $other_work;
	}
	
	function sayu_custome_work(&$users, $user_id, $year_selected, $month_selected, $type = "sayu", $_DEBUG = false) {
		global $db, $db2, $t, $_PROJECTS_WITH_FULL_COST;
		
		if ($type == "viart") {
			$custome_ids = get_project_ids(SPECIES_VIART);
		} else {
			$custome_ids = get_project_ids(SPECIES_SAYU);
		}
		if ($_DEBUG) {
			echo  $type . ' custom projects ' . $custome_ids . '<br/>';
		}
		if (!$t->get_var($type . "_custome_work_row")) {
			$t->set_var($type . "_custome_work_table", "");
		}
		if (strlen($custome_ids)) {
			$sql  = " SELECT t.project_id, t.task_id, t.task_title, t.task_cost, SUM(tr.spent_hours) AS spent_hours, t.actual_hours, t.completion, t.task_type_id ";
			$sql .= " FROM (( tasks t ";
			$sql .= " INNER JOIN time_report tr ON tr.task_id = t.task_id) ";
			$sql .= " INNER JOIN projects p ON t.project_id = p.project_id) ";
			$sql .= " WHERE tr.user_id=" . ToSQL($user_id, "integer");
			$sql .= " AND p.parent_project_id IN (" . $custome_ids . ")";
			$sql .= " AND (t.task_type_id!=4) ";
			$sql .= " AND YEAR(tr.report_date) = '$year_selected'";
			$sql .= " AND MONTH(tr.report_date) = '$month_selected' ";
			if ($users[$user_id]["used_ids"]) {
				$sql .= " AND t.task_id NOT IN (" . $users[$user_id]["used_ids"] . ") ";
			}
			$sql .= " GROUP BY t.task_id ";
			$sql .= " ORDER BY spent_hours DESC";
			$db->query($sql,__FILE__,__LINE__);		
			
			$points = 0;			
			if ($db->next_record()) {
				$t->set_var($type . "_custome_work_row", "");
				$used_ids = array();
				do {
					$task_id      = $db->f("task_id");
					$used_ids[]   = $task_id;
					$task_title   = $db->f("task_title");
					$task_cost    = $db->f("task_cost");
					$spent_hours  = $db->f("spent_hours");
					$actual_hours = $db->f("actual_hours");
					$completion   = $db->f("completion");
					$task_type_id = $db->f("task_type_id");
					$project_id   = $db->f("project_id");
					$month_selected --;
					if ($month_selected == 0) {
						$year_selected --;
						$month_selected = 12;
					}
					if ($_DEBUG) {
						echo  'task processing ' . $task_id . ' ' . $task_title . ' project ' . $project_id . '<br/>';
					}
					if ($task_cost) {
						if (in_array($project_id, $_PROJECTS_WITH_FULL_COST)) {
							$user_task_points = $task_cost * $completion / 100;
						} else {
							$sql  = " SELECT MAX(completion) FROM time_report ";
							$sql .= " WHERE task_id = '$task_id'";
							$sql .= " AND YEAR(report_date) = '$year_selected'";
							$sql .= " AND MONTH(report_date) = '$month_selected'";
							$db2->query($sql,__FILE__,__LINE__);	
							$previous_completion = 0;
							if ($db2->next_record()) {
								$previous_completion = $db2->f(0);
							}
							
							if ($previous_completion) {
								$user_task_points = $task_cost * ($completion - $previous_completion) / 100;		
							} elseif ($spent_hours && $actual_hours) {
								if ($completion > 0) {
									$user_task_points = $task_cost * $completion / 100 * $spent_hours / $actual_hours;
								} else {
									$user_task_points = $task_cost * $spent_hours / $actual_hours;
								}
							} else {
								$user_task_points = 0;
							}
						}
					} elseif ($task_type_id == CORRECTION_TYPE_ID && $project_id != SEO_CORRECTIONS_PROJECT_ID) {
						if ($_DEBUG) {
							echo  'task is correction<br/>';
						}
						$user_task_points = 0;
					} else {
						if ($_DEBUG) {
							echo  'task is timescaled<br/>';
						}						
						$user_task_points = $spent_hours * 15;
						if ($project_id == SEO_CORRECTIONS_PROJECT_ID && $spent_hours < 1) 
							$user_task_points = 15;
					}
					
					$t->set_var("task_id", $task_id);
					$t->set_var("task_title", $task_title);
					$t->set_var("task_cost", $task_cost);
					$t->set_var("spent_hours", Hours2HoursMins($spent_hours));
					$t->set_var("actual_hours", Hours2HoursMins($actual_hours));
					$t->set_var("completion", $completion);
					$t->set_var("user_task_points", round($user_task_points, 2));
					$color = "";
					
					if (!$user_task_points) {
						$color = "blue";
					} elseif ($task_cost == 0) {
						$color = "yellow";
					} elseif ($actual_hours < $cost/50 + 1) {
						$color = "red";
					}
					
					$t->set_var("color", $color);
					$t->parse($type . "_custome_work_row");
					
					$points += $user_task_points;
				} while($db->next_record());
				$t->set_var("user_id", $user_id);
				$t->set_var("name", $users[$user_id]["name"]);
				$t->parse($type . "_custome_work_table");
				
				if ($used_ids) {
					if($users[$user_id]["used_ids"]) {
						$users[$user_id]["used_ids"] .= ",";
					}
					$users[$user_id]["used_ids"] .= implode(",", $used_ids);
				}
			}
			$users[$user_id][$type] = round($points, 2);
		}
	
		return 0;
	}
	
	function custome_work(&$users, $user_id, $year_selected, $month_selected) {
		global $db, $db2, $t;
		
		$custome_ids = get_project_ids(SPECIES_CUSTOME);
		if (!$t->get_var("custome_work_row")) {
			$t->set_var("custome_work_table", "");
		}
		if (strlen($custome_ids)) {
			$sql  = " SELECT t.task_id, t.task_title, t.task_cost, SUM(tr.spent_hours) AS spent_hours, t.actual_hours, t.completion, t.task_type_id ";
			$sql .= " FROM (( tasks t ";
			$sql .= " INNER JOIN time_report tr ON tr.task_id = t.task_id) ";
			$sql .= " INNER JOIN projects p ON t.project_id = p.project_id) ";
			$sql .= " WHERE tr.user_id=" . ToSQL($user_id, "integer");
			$sql .= " AND p.parent_project_id IN (" . $custome_ids . ")";
			$sql .= " AND (t.task_type_id!=4) ";
			$sql .= " AND YEAR(tr.report_date) = '$year_selected'";
			$sql .= " AND MONTH(tr.report_date) = '$month_selected' ";
			if ($users[$user_id]["used_ids"]) {
				$sql .= " AND t.task_id NOT IN (" . $users[$user_id]["used_ids"] . ") ";
			}
			$sql .= " GROUP BY t.task_id ";
			$db->query($sql,__FILE__,__LINE__);		
			
			$points = 0;			
			if ($db->next_record()) {
				$t->set_var("custome_work_row", "");
				$used_ids = array();
				do {
					$task_id      = $db->f("task_id");
					$used_ids[]   = $task_id;
					$task_title   = $db->f("task_title");
					$task_cost    = $db->f("task_cost");
					$spent_hours  = $db->f("spent_hours");
					$actual_hours = $db->f("actual_hours");
					$completion   = $db->f("completion");
					$task_type_id = $db->f("task_type_id");

					$month_selected --;
					if ($month_selected == 0) {
						$year_selected --;
						$month_selected = 12;
					}

					$sql  = " SELECT MAX(completion) FROM time_report ";
					$sql .= " WHERE task_id = '$task_id'";
					$sql .= " AND YEAR(report_date) = '$year_selected'";
					$sql .= " AND MONTH(report_date) = '$month_selected'";
					$db2->query($sql,__FILE__,__LINE__);	
					$previous_completion = 0;
					if ($db2->next_record()) {
						$previous_completion = $db2->f(0);
					}
					
					if ($task_type_id == CORRECTION_TYPE_ID) {
						$user_task_points = 0;
					} elseif ($previous_completion) {
						$user_task_points = $task_cost * ($completion - $previous_completion) / 100;				
					} elseif ($spent_hours && $actual_hours) {
						$user_task_points = $task_cost * $completion / 100 * $spent_hours / $actual_hours;
					} else {
						$user_task_points = 0;
					}
					
					$t->set_var("task_id", $task_id);
					$t->set_var("task_title", $task_title);
					$t->set_var("task_cost", $task_cost);
					$t->set_var("spent_hours", Hours2HoursMins($spent_hours));
					$t->set_var("actual_hours", Hours2HoursMins($actual_hours));
					$t->set_var("completion", $completion);
					$t->set_var("user_task_points", round($user_task_points, 2));
					$color = "";
					
					if (!$user_task_points) {
						$color = "blue";
					} elseif ($task_cost == 0) {
						$color = "yellow";
					} elseif ($actual_hours < $cost/50 + 1) {
						$color = "red";
					}
					
					$t->set_var("color", $color);
					$t->parse("custome_work_row");
					
					$points += $user_task_points;
				} while($db->next_record());
				$t->set_var("user_id", $user_id);
				$t->set_var("name", $users[$user_id]["name"]);
				$t->parse("custome_work_table");
				
				if ($used_ids) {
					if($users[$user_id]["used_ids"]) {
						$users[$user_id]["used_ids"] .= ",";
					}
					$users[$user_id]["used_ids"] .= implode(",", $used_ids);
				}
			}
			$users[$user_id]["custome_work"] = round($points, 2);
		}
	
		return 0;
	}
	
	function tickets_points(&$users, $user_id, $year_selected, $month_selected, $__DAYS_POINTS, $notes = false) {
		global $db, $db2;
		
		$ticket_ids = get_project_ids(SPECIES_TICKET);
		$day_times  = array();

		if (strlen($ticket_ids)) {
			$sql  = " SELECT t.task_id, m.message_id, m.user_id, m.message_date, IF( pm.message_id IS NOT NULL ";
			$sql .= " , MAX( pm.message_date ) , t.creation_date ) AS previous_message_date ";
			$sql .= " FROM (( tasks t ";
			$sql .= " INNER JOIN messages m ON ( ";
			$sql .= " m.identity_id = t.task_id AND m.identity_type = 'task' AND m.user_id != m.responsible_user_id ";
			$sql .= " AND YEAR(m.message_date) = '" . $year_selected . "' ";
			$sql .= " AND MONTH(m.message_date) = '" . $month_selected . "' ";
			$sql .= " AND m.user_id=" . ToSQL($user_id, "integer") . ")) ";
			$sql .= " LEFT JOIN messages pm ON ( ";
			$sql .= " pm.identity_type = 'task' AND pm.identity_id = t.task_id AND pm.responsible_user_id = m.user_id ";
			$sql .= " AND pm.user_id != m.user_id AND m.message_id > pm.message_id AND pm.user_reply IS NULL )) ";
			$sql .= " WHERE t.project_id IN (" . $ticket_ids . ") AND t.ticket_id IS NOT NULL ";
			$sql .= " GROUP BY m.user_id, t.task_id, m.message_id ";
			$sql .= " ORDER BY m.message_date ASC ";
			$db2->query($sql,__FILE__,__LINE__);
			
			$tickets_notes = "";
			$day = 1;
			$used_ids = array();
			while($db2->next_record()) {
				$task_id    = $db2->f("task_id");
				$message_id = $db2->f("message_id");
				$used_ids[] = $task_id;
				$date      = $db2->f("message_date");
				$prev_date = $db2->f("previous_message_date");
				$time      = strtotime($date);
				$prev_time = strtotime($prev_date);
				$day_selected = date("d", $time);

				while ($day <= $day_selected) {
					$today = get_day_time($user_id, $year_selected, $month_selected, $day);
					if ($today) {
						$day_times[] = $today;
					}
					$day++;
				}
				$minus_days = get_days($prev_time, $day_times, $user_id, $year_selected, $month_selected, $day_selected);
				
				if ($minus_days == 0) {
					$users[$user_id]["tickets"]++;
					$ticket_point = $__DAYS_POINTS[$minus_days];
					$users[$user_id]["tickets_points"] += $ticket_point;
					$color = 'green';
				} else {
					$users[$user_id]["tickets_delayed"]++;
					if (isset($__DAYS_POINTS[$minus_days])) {
						$ticket_point = $__DAYS_POINTS[$minus_days];
					} else {
						$ticket_point = $__DAYS_POINTS[count($__DAYS_POINTS) - 1];
					}
					$users[$user_id]["tickets_delayed_points"] += $ticket_point;
					$color = 'red';
				}				
				
				$tickets_notes .= "
					<li style='background-color:$color'>
						<a href='https://www.viart.com.ua/monitor/edit_task.php?task_id=$task_id'>$task_id</a>
						$date
					</li>";
			}
			
			if ($used_ids) {
				if($users[$user_id]["used_ids"]) {
					$users[$user_id]["used_ids"] .= ",";
				}
				$users[$user_id]["used_ids"] .= implode(",", $used_ids);
			}
			if ($tickets_notes && $notes) {
				$users[$user_id]["notes_text"] .= "<b>Tickets</b><ul>" . $tickets_notes . "</ul>";
			}
		}
	}
	
	function get_project_ids($species) {
		global $db;
		
		$ids = array();
		if(strlen($species)) {
			$sql  = " SELECT project_id";
			$sql .= " FROM productivity_project_species ps ";
			$sql .= " WHERE species_id = " . ToSQL($species, "INTEGER");
			$db->query($sql, __FILE__, __LINE__);
			while($db->next_record()) {
				$ids[] = $db->f("project_id");
			}
		}
			
		return implode(",", $ids);
	}
	
	function select_hours($species, &$users, $user_id, $year_selected, $month_selected) {
		global $db, $t;
		
		$ids = get_project_ids($species);
		
		if ($species == 2) {
			$title = "Viart";
		} elseif ($species == 3) {
			$title = "Sayu";
		} elseif ($species == 5) {
			$title = "Manual";
		} else {
			$title = "";
		}
		$t->set_var('title', $title);
		if ($ids) {
			$sql  = " SELECT t.task_id, t.task_title, SUM(tr.spent_hours) AS spent_hours, t.completion, t.actual_hours ";
			$sql .= " FROM (( tasks t ";
			$sql .= " INNER JOIN time_report tr ON tr.task_id=t.task_id) ";
			$sql .= " INNER JOIN projects p ON p.project_id=t.project_id) ";
			$sql .= " WHERE tr.user_id=" . $user_id;
			$sql .= " AND YEAR(tr.report_date) = " . $year_selected ;
			$sql .= " AND MONTH(tr.report_date) = " . $month_selected;
			$sql .= " AND ( p.parent_project_id IN (" . $ids . ") OR p.project_id IN (" . $ids . ") ) ";
			if ($users[$user_id]["used_ids"]) {
				$sql .= " AND t.task_id NOT IN (" . $users[$user_id]["used_ids"] . ") ";
			}
			if ($species == SPECIES_VIART) {
				$custome_ids = get_project_ids(SPECIES_CUSTOME);
				$ticket_ids  = get_project_ids(SPECIES_TICKET);
				$sql .= " AND p.parent_project_id NOT IN (" . $custome_ids . ") AND p.project_id NOT IN (" . $ticket_ids . ") ";
			}
			$sql .= " AND t.task_type_id!=" . CORRECTION_TYPE_ID;
			$sql .= " GROUP BY t.task_id";
								
			$db->query( $sql,__FILE__,__LINE__);
			$total_spent_hours = 0;
			$used_ids = array();
			$t->set_var("time_work_row", "");
			if ($db->next_record()) {						
				do {
					$task_id      = $db->f("task_id");
					$used_ids[]   = $task_id;
					$task_title   = $db->f("task_title");
					$task_cost    = $db->f("task_cost");
					$spent_hours  = $db->f("spent_hours");
					$actual_hours = $db->f("actual_hours");
					$completion   = $db->f("completion");					
					$total_spent_hours += $spent_hours;
					
					$t->set_var("task_id", $task_id);
					$t->set_var("task_title", $task_title);
					$t->set_var("task_cost", $task_cost);
					$t->set_var("spent_hours", Hours2HoursMins($spent_hours));
					$t->set_var("actual_hours", Hours2HoursMins($actual_hours));
					$t->set_var("completion", $completion);
					$t->parse("time_work_row");
				} while ($db->next_record());
				
				$t->set_var("user_id", $user_id);
				$t->set_var("sp_id", $species);
				$t->set_var("name", $users[$user_id]["name"]);
				$t->parse("time_work_table");			
			}
			if ($used_ids) {
				if($users[$user_id]["used_ids"]) {
					$users[$user_id]["used_ids"] .= ",";
				}
				$users[$user_id]["used_ids"] .= implode(", ", $used_ids);
			}
			return $total_spent_hours;		
		}
		return 0;
	}
	
	function select_vacation($user_id, $year_selected, $month_selected) {
		global $db;
		
		$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month_selected, $year_selected);
		
		$result = 0;
		
		//todo rewrite
		$sql = " SELECT SUM(x.holiday_days) FROM (";
		$sql.= " SELECT ";
		$sql.= " DATEDIFF(IF(dof.end_date<='".$year_selected."-".$month_selected."-".$days_in_month."', dof.end_date, '".$year_selected."-".$month_selected."-".$days_in_month."'), IF(dof.start_date>='".$year_selected."-".$month_selected."-01',dof.start_date,'".$year_selected."-".$month_selected."-01'))+1 ";
		$sql.= " -IF(nh.holiday_id IS NOT NULL, COUNT(nh.holiday_id), 0) ";
		$sql.= " -FLOOR((DATEDIFF(IF(dof.end_date<='".$year_selected."-".$month_selected."-".$days_in_month."', dof.end_date, '".$year_selected."-".$month_selected."-".$days_in_month."'), IF(dof.start_date>='".$year_selected."-".$month_selected."-01',dof.start_date,'".$year_selected."-".$month_selected."-01'))+1)/7)*2 ";
		$sql.= " -IF ( MOD((DATEDIFF(IF(dof.end_date<='".$year_selected."-".$month_selected."-".$days_in_month."', dof.end_date, '".$year_selected."-".$month_selected."-".$days_in_month."'), IF(dof.start_date>='".$year_selected."-".$month_selected."-01',dof.start_date,'".$year_selected."-".$month_selected."-01'))+1),7) > 0 , ";
		$sql.= " IF (DAYOFWEEK(IF(dof.start_date>='".$year_selected."-".$month_selected."-01',dof.start_date,'".$year_selected."-".$month_selected."-01'))=7, ";
		$sql.= " IF( MOD((DATEDIFF(IF(dof.end_date<='".$year_selected."-".$month_selected."-".$days_in_month."', dof.end_date, '".$year_selected."-".$month_selected."-".$days_in_month."'), IF(dof.start_date>='".$year_selected."-".$month_selected."-01',dof.start_date,'".$year_selected."-".$month_selected."-01'))+1),7) >= 2, 2, 1), ";
		$sql.= " IF( DAYOFWEEK(IF(dof.start_date>='".$year_selected."-".$month_selected."-01',dof.start_date,'".$year_selected."-".$month_selected."-01'))=1, ";
		$sql.= " 1, ";
		$sql.= " IF( DAYOFWEEK(IF(dof.start_date>='".$year_selected."-".$month_selected."-01',dof.start_date,'".$year_selected."-".$month_selected."-01')) + ";
		$sql.= " MOD((DATEDIFF(IF(dof.end_date<='".$year_selected."-".$month_selected."-".$days_in_month."', dof.end_date, '".$year_selected."-".$month_selected."-".$days_in_month."'), ";
		$sql.= " IF(dof.start_date>='".$year_selected."-".$month_selected."-01',dof.start_date,'".$year_selected."-".$month_selected."-01'))+1),7) - 1 >= 7, ";
		$sql.= " IF (DAYOFWEEK(IF(dof.start_date>='".$year_selected."-".$month_selected."-01',dof.start_date,'".$year_selected."-".$month_selected."-01')) ";
		$sql.= " + MOD((DATEDIFF(IF(dof.end_date<='".$year_selected."-".$month_selected."-".$days_in_month."',dof.end_date, ";
		$sql.= " '".$year_selected."-".$month_selected."-".$days_in_month."'), IF(dof.start_date>='".$year_selected."-".$month_selected."-01',dof.start_date,'".$year_selected."-".$month_selected."-01'))+1),7) - 1 = 7,1,2), ";
		$sql.= " 0	)	)  ), 0) AS holiday_days ";
		$sql.= " FROM days_off dof ";
		$sql.= " LEFT JOIN national_holidays nh ON (nh.holiday_date>=dof.start_date AND nh.holiday_date<=dof.end_date AND DATE_FORMAT(nh.holiday_date, '%m')=".ToSQL($month_selected, "integer");
		$sql.= " AND DAYOFWEEK(nh.holiday_date) NOT IN (1,7)) ";
		$sql.= " WHERE dof.user_id=".ToSQL($user_id, "integer");
		$sql.= " AND DATE_FORMAT(dof.start_date, '%Y')*12+DATE_FORMAT(dof.start_date, '%m') <= ".ToSQL($year_selected*12+$month_selected, "integer");
		$sql.= " AND DATE_FORMAT(dof.end_date, '%Y')*12+DATE_FORMAT(dof.end_date, '%m') >= ".ToSQL($year_selected*12+$month_selected, "integer");
		$sql.= " AND (dof.reason_id IN (1,2) OR dof.is_paid=1) ";
		$sql.= " GROUP BY dof.period_id ";
		$sql.= ") AS x ";
		$db->query($sql,__FILE__,__LINE__);
		
		if ($db->next_record()) {
			$result = $db->f(0);
		}
		
		return $result;
	}
	
	function select_bugs($user_id, $year_selected, $month_selected) {
		global $db;
		
		$sql  = " SELECT SUM(importance_level) FROM bugs ";
		$sql .= " WHERE user_id=" . ToSQL($user_id, "integer");
		$sql .= " AND YEAR(date_issued) = $year_selected AND MONTH(date_issued) = $month_selected ";
		$db->query($sql, __FILE__, __LINE__);
		if ($db->next_record()) {
			return $db->f(0);
		}
				
		return 0;
	}

	function get_day_time($user_id, $year, $month, $day) {
		global $db;
		
		$sql  = " SELECT MAX(report_date) AS max_time FROM time_report ";
		$sql .= " WHERE user_id=" . $user_id;
		$sql .= " AND YEAR(report_date)="  . $year;
		$sql .= " AND MONTH(report_date)=" . $month;
		$sql .= " AND DAY(report_date)="   . $day;
		$db->query($sql, __FILE__, __LINE__);
		
		if ($db->next_record()) {
			$max_time = $db->f("max_time");
			if ($max_time) {
				return strtotime($max_time);
			}
		}
		return false;
	}
	
	function get_days($prev_time, &$day_times, $user_id, $year, $month, $day) {
		$minus_days = 1;
		do {
			if (!isset($day_times[count($day_times) - 1 - $minus_days])) {
				$i = 0;
				do {
					$i++;
					$day--;
					if ($day < 0) {
						$day = 31;
						$month--;
					}
					if ($month < 0) {
						$month = 12;
						$year--;
					}
					$today = get_day_time($user_id, $year, $month, $day);
					if ($today && $today >= $day_times[0]) {
						$today = false;
					}
				} while (!$today && $i <100);
				if ($today) {
					array_unshift($day_times, $today);
				}
			}
			if ($prev_time > ($day_times[count($day_times) - 1 - $minus_days] - 60*60)) {
				return $minus_days - 1;
			}
			$minus_days ++;
		} while ($minus_days < 10);
		return 10;
	}
?>