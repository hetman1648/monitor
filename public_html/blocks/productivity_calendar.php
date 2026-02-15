<?php
	chdir("../");
	include("./includes/common.php");
	CheckSecurity(1);
	if (isset($_GET["old"])) {
		define(_TABLE_NAME_, "external_tickets_cache");
	} else {
		define(_TABLE_NAME_, "external_tickets_cache_tmp");
	}
	//$db->query("TRUNCATE TABLE external_tickets_cache_tmp;");
	//exit; 
	// external_tickets_cache - live table
	// external_tickets_cache_tmp - tmp live table
	$day_start = 1;
	$day_end   = 31;
	$use_cache = true;
	$debug     = false;

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
	
	$day_selected  = (int) GetParam("day_selected");
	if ($day_selected) {
		$day_start = $day_selected;
		if ($day_selected <= $day_end) {
			$day_end = $day_selected;
		}
	}
	if (GetParam("debug")) {
		$debug = true;
		$use_cache = false;
	}
		
	if ($debug) echo "init : " . time() . "<br/>";
	
	//points for support
	$__DAYS_POINTS = array(
		"ANSWERED_PROGRAMMERS" => array(2, 1, 0 , -2),
		"ANSWERED"             => array(5, 3, 1, 0 , -5),
		"ANSWERED_REASSIGNED"  => array(5, 3, 1, 1, 1, 1, 0),
		"REASSIGNED"           => array(1, 0.5, 0, -2),
		"ADDITIONAL"           => 5
	);
	$__SUPPORT_USERS = array(
		35=>21
	);
	
	if ($year_selected < 2008 || ($year_selected == 2008 && $month_selected <= 10)) {
		$__SUPPORT_USERS[82] = 34;
	} else {
		$__SUPPORT_USERS[120] = 41;
	}
	
	if ($year_selected < 2009 || ($year_selected == 2009 && $month_selected <= 10)) {
		$__SUPPORT_USERS[72] = 32;
	}
		
	$__SUPPORT_USERS_IDS = array(21, 32, 34, 41);
	$__SUPPORT_TASKS = array(4841, 11395, 18110, 20922);
		
	$__TIME_ZONE_PLUS = 2 * 60 * 60;
	
	$t = new iTemplate($sAppPath);
	$t->set_file("main","block_productivity_calendar.html");

	$viart_db = new DB_Sql();
	$viart_db->Database = VIART_COM_DATABASE_NAME;
	$viart_db->User     = VIART_COM_DATABASE_USER;
	$viart_db->Password = VIART_COM_DATABASE_PASSWORD;
	$viart_db->Host     = VIART_COM_DATABASE_HOST;
	
	$viart_db2 = new DB_Sql();
	$viart_db2->Database = VIART_COM_DATABASE_NAME;
	$viart_db2->User     = VIART_COM_DATABASE_USER;
	$viart_db2->Password = VIART_COM_DATABASE_PASSWORD;
	$viart_db2->Host     = VIART_COM_DATABASE_HOST;

	foreach($__SUPPORT_USERS as $user_id => $support_user_id) {
		$sql = " SELECT CONCAT(first_name, ' ', last_name) AS user_name FROM users ";
		$sql.= " WHERE user_id = ". ToSQL($user_id, "number", false);
		$db->query($sql);
		$user_name = " ";
		if ($db->next_record()) {
			$user_name = $db->f("user_name");
		}			
		$t->set_var("user_name", $user_name);
		$t->parse("user_tickets_header");
		$t->parse("user_tickets_header2");
	}
	
	$day_times = array();
	$is_today  = 0;
	if ($debug) echo "cycle start : " . time() . "<br/>";
	
	$users = array();
	foreach($__SUPPORT_USERS as $user_id => $support_user_id) {
		$users[$user_id]["day_good"] = 0;
		$users[$user_id]["day_good_points"] = 0;
		$users[$user_id]["day_bad"] = 0;
		$users[$user_id]["day_bad_points"] = 0;		
	}
	
	for ($day_selected = $day_start; $day_selected<=$day_end; $day_selected++) {
		if ($debug) echo "day $day_selected start : " . time() . "<br/>";
	
		$t->set_var("user_tickets_stat", "");
		$t->set_var("user_tickets_stat2", "");
		
		if ($day_end == $day_selected) {			
			$is_today = 1;
		}		
		$display_row = false;
						
		foreach($__SUPPORT_USERS as $user_id => $support_user_id) {

			$t->set_var("html_block", "");
							
			if ($debug) echo "user $user_id start : " . time() . "<br/>";
			$html = "";
			$continue = true;
			$day_good        = 0;
			$day_good_points = 0;
			$day_bad 	     = 0;
			$day_bad_points  = 0;
			$last_message_id = 0;
			$message_id      = 0;
			$support_id      = 0;
			
			$need_recount    = false;
			
			if ($use_cache) {
				$sql  = " SELECT * FROM " . _TABLE_NAME_;
				$sql .= " WHERE year=" . $year_selected;
				$sql .= " AND month=" . $month_selected;
				$sql .= " AND day=" . $day_selected;
				$sql .= " AND user_id=" . $user_id;
				$db->query($sql, __FILE__, __LINE__);
				if ($db->next_record()) {
					$need_recount    = $db->f("need_recount");	
					$day_good        = $db->f("day_good");
					$day_good_points = $db->f("day_good_points");
					$day_bad         = $db->f("day_bad");
					$day_bad_points  = $db->f("day_bad_points");
					$html            = $db->f("html");
					$last_message_id = $db->f("last_message_id");
					$last_support_id = $db->f("last_support_id");
					if (!$use_cache) {
						$need_recount = true;
					}
					if (!$need_recount) {
						$continue = false;
					}
				}
			}			
			
			if ($continue) {
				$day_end_time = get_day_time($user_id, $year_selected, $month_selected, $day_selected);

				if ($day_end_time || $is_today) {
					$day_times[$user_id][] = $day_end_time;
					
					if ($debug) echo "tickets start : " . time() . "<br/>";
					
					$date_str = $year_selected;					
					if ($month_selected >= 10) {
						$date_str .= "-$month_selected";
					} else {
						$date_str .= "-0$month_selected";
					}					
					if ($day_selected >= 10) {
						$date_str .= "-$day_selected";
					} else {
						$date_str .= "-0$day_selected";
					}
					
					$sql  = " SELECT message_id, support_id, support_status_id, date_added ";
					$sql .= " FROM " . $table_prefix . "support_messages ";
					$sql .= " WHERE admin_id_assign_by= " . $support_user_id;
					$sql .= " AND (date_added BETWEEN DATE_SUB('$date_str', INTERVAL 2 HOUR) AND '$date_str 22:00:00') ";
					$sql .= " AND admin_id_assign_to NOT IN(" . implode(",", $__SUPPORT_USERS_IDS) . ")";
					// we will count only  Answered and Reassigned (Internal) tickets
					$sql .= " AND support_status_id IN(8,9)";
					if ($last_message_id) {
						$sql .= " AND message_id > " . $last_message_id;
					}
					$sql .= " ORDER BY date_added";
					
					$viart_db->query($sql, __FILE__, __LINE__);			
					while($viart_db->next_record()) {
						$message_id        = $viart_db->f("message_id");						
						$support_id        = $viart_db->f("support_id");
						$support_status_id = $viart_db->f("support_status_id");						
						$date_added        = $viart_db->f("date_added");
						$time              = strtotime($date_added);
						if ($debug) echo "<br/>ticket $support_id / $message_id start : " . time() . "<br/>";			

						$sql  = " SELECT task_id FROM time_report ";
						$sql .= " WHERE user_id=" . $user_id;
						$sql .= " AND started_date < '" . date("Y-m-d H:i:s", $time + $__TIME_ZONE_PLUS + 5 * 60) . "'";
						$sql .= " AND report_date > '"  . date("Y-m-d H:i:s", $time + $__TIME_ZONE_PLUS - 5 * 60) . "'";
						$sql .= " AND task_id IN (" . implode("," , $__SUPPORT_TASKS) . ")";
						$db->query($sql);
						if ($db->next_record()) {
							$is_correct_task_turned = true;							
						} else {
							$sql  = " SELECT task_id FROM time_report ";
							$sql .= " WHERE user_id=" . $user_id;
							$sql .= " AND started_date < '" . date("Y-m-d H:i:s", $time + $__TIME_ZONE_PLUS) . "'";
							$sql .= " AND report_date > '"  . date("Y-m-d H:i:s", $time + $__TIME_ZONE_PLUS) . "'";
							$db->query($sql);
							if ($db->next_record()) {
								$is_correct_task_turned = false;
							} else {
								$is_correct_task_turned = true;
							}
						}						
						
						//	check previous tickets							
						$prev_date_added  = $date_added;
						$get_next_ticket  = false;
						$customer_reply   = false;
						$support_reassign = false;
						$support_answered = false;
						$programmers_reassign = false;

						$i = 0;					
						do {
							$i++;
							$sql  = " SELECT support_status_id, date_added, admin_id_assign_by, message_text, reply_to, reply_from ";
							$sql .= " FROM " . $table_prefix . "support_messages ";
							$sql .= " WHERE support_id=" . $support_id;
							$sql .= " AND date_added<" . ToSQL($prev_date_added, "string");
							$sql .= " AND (reply_from IS NULL OR reply_from='' OR reply_from NOT LIKE 'MAILER-DAEMON@www.viart.com')";
							$sql .= " ORDER BY date_added DESC";
							$sql .= " LIMIT 0,1";
							$viart_db2->query($sql, __FILE__, __LINE__); 
							if($viart_db2->next_record()) {
								$prev_support_status_id  = $viart_db2->f("support_status_id");
								$prev_date_added         = $viart_db2->f("date_added");
								$prev_admin_id_assign_by = $viart_db2->f("admin_id_assign_by");
								$prev_message_text       = $viart_db2->f("message_text");
								$prev_reply_to           = $viart_db2->f("reply_to");								
								$prev_reply_from         = $viart_db2->f("reply_from");
								if (($prev_support_status_id == 9) && in_array($prev_admin_id_assign_by, $__SUPPORT_USERS_IDS)) {
									// ignore all messages from the support team
									$support_reassign = true;
									$get_next_ticket  = true;
								} else if ($prev_support_status_id == 1 || $prev_support_status_id == 7) {
									// we have found New or Customer Reply ticket
									// lets look for the next ticket
									$prev_time   = strtotime($prev_date_added);
									$customer_reply  = true;
									$get_next_ticket = true;
								} elseif ($customer_reply) {
									$get_next_ticket = false;
								} else {
									$prev_time   = strtotime($prev_date_added);
									if ($support_reassign) {
										$get_next_ticket = false;
									}
									if (in_array($prev_admin_id_assign_by, $__SUPPORT_USERS_IDS)) {
										if ($prev_support_status_id == 8) {
											$support_answered = true;
											$get_next_ticket  = false;
										}
									} elseif ($prev_admin_id_assign_by > 0) {
										$programmers_reassign = true;
										$get_next_ticket = false;
									}														
								}
							} else {
								// get creation date as no tickets left
								$sql  = " SELECT date_added ";
								$sql .= " FROM " . $table_prefix . "support ";
								$sql .= " WHERE support_id=" . $support_id;
								$sql .= " AND date_added<" . ToSQL($prev_date_added, "string");
								$viart_db2->query($sql, __FILE__, __LINE__); 
								if($viart_db2->next_record()) {
									$prev_date_added = $viart_db2->f("date_added");
									$prev_time       = strtotime($prev_date_added);
								}
								break;
							}
						} while ($get_next_ticket && $i<10);
								
						if ($debug) {
							echo "support_status_id = $support_status_id<br/>";
							echo "prev_time = $prev_time";							 
							echo "<br/>customer_reply = "; 
							var_dump($customer_reply);
							echo "<br/>reply_to = "; 
							var_dump($prev_reply_to);
							echo "<br/>reply_from = "; 
							var_dump($prev_reply_from);
							echo "<br/>support_reassign = ";
							var_dump($support_reassign);
							echo "<br/>support_answered = ";
							var_dump($support_answered);
							echo "<br/>programmers_reassign = "; 
							var_dump($programmers_reassign);
							echo "<br/>prev_admin_id_assign_by = $prev_admin_id_assign_by<br/>";
							echo $prev_message_text ."<br/>";
						}
						if ($prev_time && $time - $prev_time > 60) {	
							$prev_time += $__TIME_ZONE_PLUS;
							$time      += $__TIME_ZONE_PLUS;
							$is_good   = false;
							if ($support_answered) {
								$points_type = "ADDITIONAL";
								$ticket_point = $__DAYS_POINTS[$points_type];
								$is_good      = true;
								$color = "blue";
								
								if ($debug) echo " type: $points_type points: $ticket_point<br/>";
														
							} else {								
								$minus_days = get_days($prev_time, $day_times[$user_id], $user_id, $year_selected, $month_selected, $day_selected);
								if ($debug) {
									var_dump($day_times[$user_id]);
									echo "<br/>";
								}
								if ($support_status_id == 8) {
									if ($customer_reply) {
										if ($support_reassign) {
											$points_type = "ANSWERED_REASSIGNED";
										} else {
											$points_type = "ANSWERED";
										}
									} elseif($programmers_reassign) {
										$points_type = "ANSWERED_PROGRAMMERS";
									} else {
										if ($support_reassign) {
											$points_type = "ANSWERED_REASSIGNED";
										} else {
											$points_type = "ANSWERED";
										}
									}
								} else {
									$points_type = "REASSIGNED";
								}
								
								if (($minus_days !== false) && isset($__DAYS_POINTS[$points_type][$minus_days])) {
									$ticket_point = $__DAYS_POINTS[$points_type][$minus_days];
								} else {
									$ticket_point = $__DAYS_POINTS[$points_type][count($__DAYS_POINTS[$points_type]) - 1];
								}
								
								if ($minus_days === 0) {
									$is_good = true;
									$color    = "green";
								} else {									
									$color    = "yellow";
									if (!$minus_days || $minus_days > count($__DAYS_POINTS[$points_type]) - 2) {
										$color = "red";
									}
								}
								
								if ($debug) echo " is_good: $is_good type: $points_type minus_days: $minus_days points: $ticket_point<br/>";
							}
							
							if (!$is_correct_task_turned) {
								// turned some other task
								$color .= " wrong-task";
							} elseif ($is_good) {
								$day_good_points += $ticket_point;
								$day_good++;
							} else {
								$day_bad_points += $ticket_point;
								$day_bad++;
							}
												
							$html .= "
							<tr class='$color'>
								<td>
									<a href=http://www.viart.com/va/admin_support_reply.php?support_id=$support_id#$message_id>$support_id</a>
								</td>
								<td>
									" . date("Y-m-d H:i:s", $time) . "
								</td>
								<td>
									" . date("Y-m-d H:i:s", $prev_time) . "
								</td>
								<td>
									$ticket_point
								</td>
							</tr>";
						}
						
						if ($debug) echo "ticket $support_id / $message_id end : " . time() . "<br/>";
								
					}
					
					// get created tickets
					$sql  = " SELECT support_id, date_added ";
					$sql .= " FROM " . $table_prefix . "support ";
					$sql .= " WHERE admin_id_added_by=" . ToSQL($support_user_id, "integer"); 
					$sql .= " AND YEAR(date_added)="  . $year_selected;
					$sql .= " AND MONTH(date_added)=" . $month_selected;
					$sql .= " AND DAY(date_added)="   . $day_selected;
					$sql .= " AND admin_id_assign_to NOT IN(" . implode(",", $__SUPPORT_USERS_IDS) . ")";
					if ($last_support_id) {
						$sql .= "AND support_id<" . ToSQL($last_support_id, "integer");
					}
					// we will count only  Answered and Reassigned (Internal) tickets
					$sql .= " AND support_status_id IN(8,9)";
					$viart_db->query($sql, __FILE__, __LINE__);
										
					while($viart_db->next_record()) {						
						$support_id = $viart_db->f("support_id");
						$date_added = $viart_db->f("date_added");
						$time = strtotime($date_added);
						$time += $__TIME_ZONE_PLUS;
						
						$day_good_points += $ticket_point;
						$day_good++;
						$color = "darkgreen";
						if ($debug) echo "ticket $support_id (created) : " . time() . "<br/>";
						
						$html .= "
							<tr class='$color'>
								<td>
									<a href=http://www.viart.com/va/admin_support_reply.php?support_id=$support_id>$support_id</a>
								</td>
								<td>
									" . date("Y-m-d H:i:s", $time) . "
								</td>
								<td>
									&nbsp;
								</td>
								<td>
									$ticket_point
								</td>
							</tr>";
						
					}
				}									
				if (($last_message_id < $message_id) || ($last_support_id < $support_id))	{
					$last_message_id = $message_id;
					$last_support_id = $support_id;
					$sql  = " DELETE FROM " . _TABLE_NAME_;
					$sql .= " WHERE year=" . $year_selected;
					$sql .= " AND month=" . $month_selected;
					$sql .= " AND day=" . $day_selected;
					$sql .= " AND user_id=" . $user_id;
					$db->query($sql, __FILE__, __LINE__);
					
					$sql  = " INSERT INTO " . _TABLE_NAME_;
					$sql .= " (year, month, day, user_id, day_good, day_good_points, day_bad, day_bad_points, html, need_recount, last_message_id, last_support_id) VALUES ( ";
					$sql .= ToSQL($year_selected, "integer") . ",";
					$sql .= ToSQL($month_selected, "integer") . ",";
					$sql .= ToSQL($day_selected, "integer") . ",";
					$sql .= ToSQL($user_id, "integer") . ",";
					$sql .= ToSQL($day_good, "integer", false) . ",";
					$sql .= ToSQL($day_good_points, "integer", false) . ",";
					$sql .= ToSQL($day_bad, "integer", false) . ",";
					$sql .= ToSQL($day_bad_points, "integer", false) . ",";
					$sql .= ToSQL(mysql_escape_string($html), "string") . ",";
					$sql .= ToSQL($is_today, "integer", false) . ",";
					$sql .= ToSQL($last_message_id, "integer", false) . ",";
					$sql .= ToSQL($last_support_id, "integer", false) . ")";
					
					$db->query($sql, __FILE__, __LINE__);
				}			
			}
			
			if ($html) {
				$t->set_var("day_good", $day_good);
				$t->set_var("day_good_points", $day_good_points);
				$t->set_var("day_bad", $day_bad);
				$t->set_var("day_bad_points", $day_bad_points);
				$t->set_var("html", $html);
				$t->parse("html_block");
				$display_row = true;
			} else {
				$t->set_var("day_good", "");
				$t->set_var("day_good_points", "");
				$t->set_var("day_bad", "");
				$t->set_var("day_bad_points", "");
			}
			
			$users[$user_id]["day_good"]         += $day_good;
			$users[$user_id]["day_good_points"]  += $day_good_points;
			$users[$user_id]["day_bad"]          += $day_bad;
			$users[$user_id]["day_bad_points"]   += $day_bad_points;	
			
			$t->parse("user_tickets_stat");
			$t->parse("user_tickets_stat2");
			if ($debug) echo "user $user_id end : " . time() . "<br/>";
		}
		$t->set_var("day_selected", $day_selected);
		if ($display_row) {
			$t->parse("users_tickets_stat", true);
		}
		if ($debug) echo "day $day_selected end : " . time() . "<br/>";
	}
	
	foreach($users as $user_id => $user) {
		foreach ($user AS $title => $value) {
			$t->set_var($title, $value);
		}
		$t->parse("user_tickets_footer");
	}

	$t->pparse("main");
	
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
				//echo "search index =  " . (count($day_times) - 1 - $minus_days) ."<br/>";
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
					//echo " get_day_time($user_id, $year, $month, $day) = " . $today . " = " . date("Y-m-d H:i:s", $today) . "<br/>";
					if ($today && $today >= $day_times[0]) {
						$today = false;
					}
				} while (!$today && $i <100);
				//echo " last = " . $day_times[0];
				if ($today) {
					array_unshift($day_times, $today);
				}
			}
			if ($prev_time > ($day_times[count($day_times) - 1 - $minus_days] - 60*60)) {
				return $minus_days - 1;
			}	
			$minus_days ++;
		} while ($minus_days < 10);
		return false;
	}
?>