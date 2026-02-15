<?php
	/**/
	if (isset($_SERVER["REMOTE_ADDR"]) && $_SERVER["REMOTE_ADDR"] != "62.149.0.96") {
		echo false;
		exit;
	}	
	/**/
	set_time_limit(300);
	
	define("DEBUG_TIME",1);
	define("SUPPORT_MESSAGE",0);
	define("SUPPORT_ADMINBY",1);
	define("SUPPORT_ADMINTO",2);
	define("SUPPORT_DATE",3);
	define("SUPPORT_UNIX",4);
	define("SUPPORT_TIME",5);
	define("SUPPORT_STATUS",6);
	define("SUPPORT_NOTANS",7);
	define("ANSWER_ADMIN_ID",0);
	define("ANSWER_TODAY",1);
	define("ANSWER_YESTERDAY",2);
	define("ANSWER_MORE",3);
	define("ANSWER_TIME",4);
	define("TICKET_STATUS_ANSWERED",8);
	define("TICKET_STATUS_REASSIGNED",9);
	define("HOURS_OFF",0);
	define("MINUTS_OFF",1);
	define("TWENTY_FOUR_HOURS",86400);
	define("TWELVE_HOURS",43200);
	define("ONE_HOUR",3600);
	define("ZERO_DAY",0);
	define("ONE_DAY",1);
	define("TWO_DAY",2);
	define("MORE_DAY",3);

	include_once("./includes/var_definition.php");
	include_once("./includes/constants.php");
	include_once("./includes/common_functions.php");
	include_once("./includes/va_functions.php");
	$language_code = get_language("messages.php");
	include_once("./messages/" . $language_code . "/messages.php");
	include_once("./includes/date_functions.php");
	include_once("./includes/db_$db_lib.php");
	
	// Database Initialize
	$db = new VA_SQL();
	$db->DBType      = $db_type;
	$db->DBDatabase  = $db_name;
	$db->DBHost      = $db_host;
	$db->DBPort      = $db_port;
	$db->DBUser      = $db_user;
	$db->DBPassword  = $db_password;
	$db->DBPersistent= $db_persistent;
	
	//$support_users_string = get_param("users");
	$report_array = array();
	/**/
	
	/**/
	
	//start debug param
	$initial = getmicrotime();
	$file_log = "./downloads/timing.txt";
	$fticket  = "./downloads/tickets.txt";
	if (file_exists($file_log)) { @unlink($file_log);}
	if (file_exists($fticket)) { @unlink($fticket);}
	//end debug param
	
	if ((isset($_SERVER["OS"]) && strtoupper(substr($_SERVER["OS"],0,3)) == "WIN") || 
		(isset($_SERVER["WINDIR"]) && strlen($_SERVER["WINDIR"]))) {
		/**/
		// debug parametrs
				
		$holidays_string = "";
		$temp = 'a:3:{i:21;a:29:{s:4:"0701";s:10:"1214922043";s:4:"0702";s:10:"1215015375";s:4:"0703";s:10:"1215102791";s:4:"0704";s:10:"1215185099";s:4:"0707";s:10:"1215450463";s:4:"0708";s:10:"1215539591";s:4:"0709";s:10:"1215621005";s:4:"0710";s:10:"1215710666";s:4:"0711";s:10:"1215790152";s:4:"0714";s:10:"1216055724";s:4:"0715";s:10:"1216140000";s:4:"0716";s:10:"1216222010";s:4:"0717";s:10:"1216316831";s:4:"0718";s:10:"1216395678";s:4:"0719";s:10:"1216493700";s:4:"0721";s:10:"1216659187";s:4:"0722";s:10:"1216744475";s:4:"0723";s:10:"1216837841";s:4:"0724";s:10:"1216921917";s:4:"0725";s:10:"1217003526";s:4:"0727";s:10:"1217159508";s:4:"0728";s:10:"1217265987";s:4:"0729";s:10:"1217354052";s:4:"0730";s:10:"1217441272";s:4:"0731";s:10:"1217537090";s:4:"0801";s:10:"1217607823";s:4:"0804";s:10:"1217868950";s:4:"0805";s:10:"1217954596";s:4:"0806";s:10:"1218017614";}i:32;a:21:{s:4:"0707";s:10:"1215448059";s:4:"0708";s:10:"1215530172";s:4:"0709";s:10:"1215615626";s:4:"0710";s:10:"1215707242";s:4:"0711";s:10:"1215793025";s:4:"0714";s:10:"1216050226";s:4:"0715";s:10:"1216135800";s:4:"0718";s:10:"1216397474";s:4:"0721";s:10:"1216656589";s:4:"0722";s:10:"1216742631";s:4:"0723";s:10:"1216839026";s:4:"0724";s:10:"1216917130";s:4:"0725";s:10:"1217001591";s:4:"0728";s:10:"1217263815";s:4:"0729";s:10:"1217344639";s:4:"0730";s:10:"1217427093";s:4:"0731";s:10:"1217516961";s:4:"0801";s:10:"1217606158";s:4:"0804";s:10:"1217863589";s:4:"0805";s:10:"1217934505";s:4:"0806";s:10:"1218037478";}i:34;a:24:{s:4:"0701";s:10:"1214925925";s:4:"0702";s:10:"1215015502";s:4:"0703";s:10:"1215100326";s:4:"0704";s:10:"1215186592";s:4:"0707";s:10:"1215444628";s:4:"0708";s:10:"1215532947";s:4:"0709";s:10:"1215620769";s:4:"0710";s:10:"1215702049";s:4:"0711";s:10:"1215790988";s:4:"0714";s:10:"1216048879";s:4:"0715";s:10:"1216136100";s:4:"0716";s:10:"1216220711";s:4:"0717";s:10:"1216310152";s:4:"0718";s:10:"1216397902";s:4:"0721";s:10:"1216657760";s:4:"0722";s:10:"1216742317";s:4:"0723";s:10:"1216831879";s:4:"0724";s:10:"1216914424";s:4:"0725";s:10:"1217002288";s:4:"0728";s:10:"1217258812";s:4:"0729";s:10:"1217345653";s:4:"0730";s:10:"1217436012";s:4:"0731";s:10:"1217519313";s:4:"0801";s:10:"1217591702";}}';
		$users_support_days = unserialize($temp);
		
		$selected_date = "1214859601";
		$local = "14400";
	} else {
		$holidays_string = get_param("holidays");
		$selected_date	= get_param("selected_date");
		$users_support_days = unserialize(get_param("users_support_days"));
		$local = get_param("local")?(int)get_param("local"):0;
		if (!is_array($users_support_days) || strlen($selected_date) == 0) {
			echo false;
			exit;
		}
	}
	
	if ($local) {
		$local = $local - (int)date("Z");
	}
	if (((int)$selected_date) == 0 || ((int)$selected_date) == date("Y")) {
		list($selected_year,$selected_month,$selected_day) = datetime2array($selected_date);
		$selected_date_unix = mktime(0,0,0,$selected_month,$selected_day,$selected_year);
	} else {
		//$selected_date_unix = $selected_date;
		$selected_date_unix = $selected_date + $local;
		$selected_year = date("Y",$selected_date_unix);
		$selected_month = date("m",$selected_date_unix);
		$selected_date = date("Y-m-d",$selected_date_unix);
		//echo date("Y-m-d H:s:i",$selected_date_unix);
	}
	/**/
	
	$support_users	= array();
	$support_users	= array_keys($users_support_days);
	$support_users_string = implode(",",$support_users);
	foreach($support_users as $user_id) {
		$report_array[$user_id] = array(0,0,0,0,"feedback_rating"=>100);
		$sql  = " SELECT AVG(fr.rating_cost) AS feedback_rating ";
		$sql .= " FROM (" . $table_prefix . "feedbacks f ";
		$sql .= "	INNER JOIN " . $table_prefix . "feedbacks_ratings fr ON (fr.rating_id=f.rating_id))";
		$sql .= " WHERE DATE_FORMAT(f.date_added,'%m') = " . $db->tosql($selected_month,INTEGER);
		$sql .= " 	AND DATE_FORMAT(f.date_added,'%Y') = " . $db->tosql($selected_year,INTEGER);
		$sql .= " 	AND f.admin_id = " . $db->tosql($user_id,INTEGER);
		$db->query($sql);
		if ($db->next_record()) {
			$rating = 100 * round($db->f("feedback_rating"),2);
			$report_array[$user_id]["feedback_rating"] = (int)$rating;
			if ($rating == 0) {
				unset($report_array[$user_id]["feedback_rating"]);
			}
		}
	}
	
	$holidays = array();
	if (strlen($holidays_string)) {
		$holidays = explode(",",$holidays_string);
	}
	
	show_microtime("Start script");
	if (sizeof($support_users)) {
		if (sizeof($support_users)>1) {
			$query_users = " IN (" . $support_users_string . ")";
		} else {
			$query_users = "=" . $db->tosql($support_users[0],INTEGER);
		}
		//$sql  = " SELECT support_id, MAX(message_id) AS message_id, admin_id_assign_by, date_added, UNIX_TIMESTAMP(date_added) AS unix_date ";
		$sql  = " SELECT support_id, message_id, admin_id_assign_by, date_added, UNIX_TIMESTAMP(date_added) AS unix_date ";
		$sql .= " FROM support_messages ";
		$sql .= " WHERE DATE_FORMAT(date_added,'%Y') = DATE_FORMAT('".$selected_date."','%Y') ";
		$sql .= "	AND DATE_FORMAT(date_added,'%m') = DATE_FORMAT('".$selected_date."','%m') ";
		$sql .= "	AND support_status_id=" . TICKET_STATUS_ANSWERED;
		$sql .= "	AND admin_id_assign_by" . $query_users;
		//$sql .= " GROUP BY  support_id ";
		$sql .= " ORDER BY support_id ASC ";
		$db->query($sql);
		$support_ids = array();
		//var_dump($sql);echo"\r\n<br>";exit;
		while($db->next_record()) {
			$support_id = $db->f("support_id");
			$message_id = $db->f("message_id");
			$admin_id_assign_by = $db->f("admin_id_assign_by");
			$date_added = $db->f("date_added");
			$unix_date	= $db->f("unix_date");
			$support_ids[] = array($support_id,$message_id,$admin_id_assign_by,$date_added,$unix_date);
		}
		show_microtime("Select last answered messages on month");
		foreach($support_ids as $support_key => $support_array) {
			$admin_id = $support_array[2];
			$report_array[$admin_id][ZERO_DAY]++;
		}
		//echo serialize($report_array);
		//exit;
		
		$total_tickets = 0;
		foreach($support_ids as $support_key => $support_array) {
			list($support_id,$message_id,$admin_id_assign_by,$date_added,$unix_date) = $support_array;
			/*/
			$sql  = " SELECT message_id, date_added, UNIX_TIMESTAMP(date_added) AS unix_date ";
			$sql .= " FROM " . $table_prefix . "support_messages ";
			$sql .= " WHERE support_id=" . $db->tosql($support_id,INTEGER);
			$sql .= " 	AND message_id<" . $db->tosql($message_id,INTEGER);
			$sql .= "	AND UNIX_TIMESTAMP(date_added) <= " . $db->tosql($selected_date_unix,INTEGER);
			$sql .= " ORDER BY message_id DESC";
			var_dump($sql);echo"\r\n<br>";exit;
			/**/
			//start search last question
			$sql  = " SELECT message_id, date_added, UNIX_TIMESTAMP(date_added) AS unix_date ";
			$sql .= " FROM " . $table_prefix . "support_messages ";
			$sql .= " WHERE support_id=" . $db->tosql($support_id,INTEGER);
			$sql .= " 	AND message_id<" . $db->tosql($message_id,INTEGER);
			$sql .= "	AND admin_id_assign_by is NULL AND admin_id_assign_to is NULL";
			$sql .= " ORDER BY message_id DESC";
			$db->query($sql);
			$last_quesion = array();
			if ($db->next_record()) {
				$client_masssge_id	= $db->f("message_id");
				$client_date_added	= $db->f("date_added");
				$client_unix_date	= $db->f("unix_date");
				$last_quesion = array($client_masssge_id,$client_date_added,$client_unix_date);
			} else {//if only one question as new ticket
				$sql  = " SELECT date_added, UNIX_TIMESTAMP(date_added) AS unix_date";
				$sql .= " FROM " . $table_prefix . "support ";
				$sql .= " WHERE support_id=" . $db->tosql($support_id,INTEGER);
				$db->query($sql);
				if ($db->next_record()) {
					$client_date_added	= $db->f("date_added");
					$client_unix_date	= $db->f("unix_date");
					$last_question = array(0,$client_date_added,$client_unix_date);
				}
			}
			show_microtime("search last question ($support_key)");
			//end search last question
			
			
			$search = true;
			/**/
			while($search) {
				$prev_question = array();
				//start search previous question
				if (sizeof($last_quesion) && $last_quesion[0] != 0) {//$last_quesion[0] == 0 when first question is ticket
					$sql  = " SELECT message_id, date_added, UNIX_TIMESTAMP(date_added) AS unix_date";
					$sql .= " FROM " . $table_prefix . "support_messages ";
					$sql .= " WHERE support_id=" . $db->tosql($support_id,INTEGER);
					$sql .= " 	AND message_id<" . $db->tosql($last_quesion[0],INTEGER);
					$sql .= "	AND admin_id_assign_by is NULL AND admin_id_assign_to is NULL";
					$sql .= " ORDER BY message_id DESC";
					$db->query($sql);
					if ($db->next_record()) {
						$client_masssge_id	= $db->f("message_id");
						$client_date_added	= $db->f("date_added");
						$client_unix_date	= $db->f("unix_date");
						$prev_question = array($client_masssge_id,$client_date_added,$client_unix_date);
					} else {
						$sql  = " SELECT date_added, UNIX_TIMESTAMP(date_added) AS unix_date";
						$sql .= " FROM " . $table_prefix . "support ";
						$sql .= " WHERE support_id=" . $db->tosql($support_id,INTEGER);
						$db->query($sql);
						if ($db->next_record()) {
							$client_date_added	= $db->f("date_added");
							$client_unix_date	= $db->f("unix_date");
							$prev_question = array(0,$client_date_added,$client_unix_date);
						}
					}
				}
				show_microtime("search previous question ($support_key)");
				//end search previous question
				
				//start search availability response between the two questions
				if (sizeof($prev_question)) {
					$sql  = " SELECT message_id, date_added, UNIX_TIMESTAMP(date_added) AS unix_date";
					$sql .= " FROM " . $table_prefix . "support_messages ";
					$sql .= " WHERE support_id=" . $db->tosql($support_id,INTEGER);
					$sql .= "	AND support_status_id=" . TICKET_STATUS_ANSWERED;
					if ($prev_question[0] != 0) {
						$sql .= " 	AND message_id BETWEEN " . $db->tosql($prev_question[0],INTEGER) . " AND " . $db->tosql($last_quesion[0],INTEGER);
					} else {
						$sql .= " 	AND message_id<" . $db->tosql($last_quesion[0],INTEGER);
					}
					$db->query($sql);
					if ($db->next_record()) {
						$search = false;
					} else {
						$last_quesion = $prev_question;
						if ($prev_question[0] == 0) {
							$search = false;
						}
					}
				} else {
					$search = false;
				}
				unset($prev_question);
				show_microtime("search availability response between the two questions ($support_key)");
				//end search availability response between the two questions
			}
			/***/
			
			/*/
			//start search previous question
			$prev_question = NULL;
			if (sizeof($last_quesion) && $last_quesion[0] != 0) {//$last_quesion[0] == 0 when first question is ticket
				$sql  = " SELECT message_id, date_added, UNIX_TIMESTAMP(date_added) AS unix_date";
				$sql .= " FROM " . $table_prefix . "support_messages ";
				$sql .= " WHERE support_id=" . $db->tosql($support_id,INTEGER);
				$sql .= " 	AND message_id<" . $db->tosql($last_quesion[0],INTEGER);
				$sql .= "	AND admin_id_assign_by is NULL AND admin_id_assign_to is NULL";
				$sql .= " ORDER BY message_id DESC";
				$db->query($sql);
				if ($db->next_record()) {
					$client_masssge_id	= $db->f("message_id");
					$client_date_added	= $db->f("date_added");
					$client_unix_date	= $db->f("unix_date");
					$prev_quesion = array($client_masssge_id,$client_date_added,$client_unix_date);
				} else {
					$sql  = " SELECT date_added, UNIX_TIMESTAMP(date_added) AS unix_date";
					$sql .= " FROM " . $table_prefix . "support ";
					$sql .= " WHERE support_id=" . $db->tosql($support_id,INTEGER);
					$db->query($sql);
					if ($db->next_record()) {
						$client_date_added	= $db->f("date_added");
						$client_unix_date	= $db->f("unix_date");
						$prev_question = array(0,$client_date_added,$client_unix_date);
					}
				}
			}
			show_microtime("search previous question ($support_key)");
			//end search previous question
			
			//start search availability response between the two questions
			if (sizeof($prev_question)) {
				$sql  = " SELECT message_id, date_added, UNIX_TIMESTAMP(date_added) AS unix_date";
				$sql .= " FROM " . $table_prefix . "support_messages ";
				$sql .= " WHERE support_id=" . $db->tosql($support_id,INTEGER);
				$sql .= "	AND support_status_id=" . TICKET_STATUS_ANSWERED;
				if ($prev_question[0] != 0) {
					$sql .= " 	AND message_id BETWEEN " . $db->tosql($prev_question[0],INTEGER) . " AND " . $db->tosql($last_quesion[0],INTEGER);
				} else {
					$sql .= " 	AND message_id<" . $db->tosql($last_quesion[0],INTEGER);
				}
				$db->query($sql);
				if ($db->next_record()) {
				} else {
					$last_quesion = $prev_question;
				}
			}
			unset($prev_question);
			show_microtime("search availability response between the two questions ($support_key)");
			//end search availability response between the two questions
			/**/
			
			//start fill messages array question->messages->answere
			//SUPPORT_MESSAGE 0
			//SUPPORT_ADMINBY 1
			//SUPPORT_ADMINTO 2
			//SUPPORT_DATE 3
			//SUPPORT_UNIX 4
			//SUPPORT_TIME 5
			//SUPPORT_STATUS 6
			//SUPPORT_NOTANS 7
			$admin_id_assign_to = 0;
			$timepermessage = 0;
			$support_status_id = 0;
			$is_notanswere = 0;
			$suppourt_messages = array();
			if (sizeof($last_quesion) == 0) {
				//if first quesions is  new ticket
				$suppourt_messages[] = array($support_id,$admin_id_assign_by,$admin_id_assign_to,$date_added,$unix_date,$timepermessage,$support_status_id,$is_notanswere);
			}
			$sql  = " SELECT message_id, IFNULL(admin_id_assign_by,0) AS admin_id_assign_by, IFNULL(admin_id_assign_to,0) AS admin_id_assign_to ";
			$sql .= ", date_added, UNIX_TIMESTAMP(date_added) AS unix_date, support_status_id ";
			$sql .= " FROM " . $table_prefix . "support_messages ";
			$sql .= " WHERE support_id=" . $db->tosql($support_id,INTEGER);
			//$sql .= "	AND support_status_id=" . TICKET_STATUS_ANSWERED;
			$sql .= "	AND message_id<=" . $db->tosql($message_id,INTEGER);
			if (isset($last_quesion[0]) && $last_quesion[0] != 0) {
				$sql .= "	AND message_id>=" . $db->tosql($last_quesion[0],INTEGER);
			}
			$sql .= " ORDER BY message_id";
			$db->query($sql);
			while($db->next_record()) {
				$t_message_id = $db->f("message_id");
				$t_admin_id_assign_by = $db->f("admin_id_assign_by");
				$t_admin_id_assign_to = $db->f("admin_id_assign_to");
				$t_date_added = $db->f("date_added");
				$t_unix_date = $db->f("unix_date");
				$t_support_status_id = $db->f("support_status_id");
				$suppourt_messages[] = array($t_message_id,$t_admin_id_assign_by,$t_admin_id_assign_to,$t_date_added,$t_unix_date,$timepermessage,$t_support_status_id,$is_notanswere);
			}
			show_microtime("fill messages array question->messages->answere ($support_key)");
			//end fill messages array question->messages->answere
			reset($suppourt_messages);
			//if ($support_id == 10489) { var_dump($suppourt_messages);echo"\r\n<br>";exit;}
			
			$timeonticket = 0;
			while (list($first_key,$support_array) = each($suppourt_messages)) {
				$first_message_id = $support_array[SUPPORT_MESSAGE];
				$first_unix_date = $support_array[SUPPORT_UNIX];
				$first_status_id = $support_array[SUPPORT_STATUS];
				$first_admin_by	= $support_array[SUPPORT_ADMINBY];
				$first_admin_to = $support_array[SUPPORT_ADMINTO];
				if ($first_status_id == TICKET_STATUS_ANSWERED) { continue;}
				/**/
				if (list($second_key,$support_array) = each($suppourt_messages)) {
					$second_message_id = $support_array[SUPPORT_MESSAGE];
					$second_unix_date = $support_array[SUPPORT_UNIX];
					$second_status_id = $support_array[SUPPORT_STATUS];
					$second_admin_by = $support_array[SUPPORT_ADMINBY];
					$second_admin_to = $support_array[SUPPORT_ADMINTO];
					debug_tickets($support_id . "\t" . implode("\t", $support_array));
					
					if (!($first_status_id == TICKET_STATUS_ANSWERED && $second_status_id == TICKET_STATUS_ANSWERED)) {
						//$date_diff = datediff($second_unix_date,$first_unix_date);
						//list($minut,$second) = explode(":",date("i:s",$second_unix_date-$first_unix_date));
						//$suppourt_messages[$second_key][SUPPORT_TIME] = $minut + round($second/60,2);
						//echo "$support_id\t$second_message_id\t" . date("Y-m-d",$first_unix_date) . "\t" . date("Y-m-d",$second_unix_date) . "\n";
						//if ($support_id == 17228) { echo "$support_id\t$first_message_id\t$second_message_id\t" . date("Y-m-d",$first_unix_date) . "\t" . date("Y-m-d",$second_unix_date) . "\n";}
						$suppourt_messages[$second_key][SUPPORT_TIME] = abs($second_unix_date - $first_unix_date);
						
						if (strlen($first_admin_by) && $second_admin_to == $admin_id_assign_by) {
							$timeonticket += $suppourt_messages[$second_key][SUPPORT_TIME];
						}
						
						if (((!strlen($first_admin_by) && !strlen($first_admin_to) && $first_message_id != $support_id) ||
							$first_message_id == $support_id) && 
							(!strlen($second_admin_by) && !strlen($second_admin_to))) { //if have two questions
							$suppourt_messages[$second_key][SUPPORT_NOTANS] = 1;
						}
						if ($suppourt_messages[$first_key][SUPPORT_NOTANS] == 1) {
							$suppourt_messages[$second_key][SUPPORT_TIME] += $suppourt_messages[$first_key][SUPPORT_TIME];
						}
						
						if ($second_status_id == TICKET_STATUS_ANSWERED && in_array($second_admin_by,$support_users)) {
							//echo "$support_id\t$first_message_id\t$second_message_id\t" . date("Y-m-d",$first_unix_date) . "\t" . date("Y-m-d",$second_unix_date) . "\n";
							
							if (date("n",$second_unix_date) != $selected_month) { continue;}
							
							if (strlen($first_admin_by) && !in_array($first_admin_by,$support_users)) {
								if (!isset($report_array[$second_admin_by]["reassign"])) {
									$report_array[$second_admin_by]["reassign"] = 0;
								}
								$report_array[$second_admin_by]["reassign"]++;
							} else {
								$income_unix_date = $unix_date;
								if (strlen($first_admin_by) && strlen($first_admin_to)) {//checked on reassign message
									if (in_array($first_admin_to,$support_users) && !in_array($first_admin_by,$support_users)) {
										$income_unix_date = $first_unix_date;
									}
								}
								/**/
								$hour_income = date("G",$income_unix_date);
								$day_income = date("j",$income_unix_date);
								$month_income = date("n",$income_unix_date);
								$hour_answer = date("G",$second_unix_date);
								$day_answer = date("j",$second_unix_date);
								$month_answer = date("n",$income_unix_date);
								$timeoff = false;
								$temp = 0;
								$admin_day = date("md",$income_unix_date);
								//$admin_day = $selected_month . date("d",$income_unix_date);
								if (isset($users_support_days[$second_admin_by][$admin_day])) {
									$users_support_days[$second_admin_by][$admin_day] += $local;
									if ($month_answer == $month_income) {
										if ($day_income != $day_answer) {
											if ($users_support_days[$second_admin_by][$admin_day] <= ($income_unix_date - ONE_HOUR)) {
												$timeoff = true;
											}
											$income_unix_date += TWELVE_HOURS;
											$day_income = date("j",$income_unix_date);
											while($day_income != $day_answer) {
												$admin_day = date("md",$income_unix_date);
												if (isset($users_support_days[$second_admin_by][$admin_day])) {
													$temp++;
												}
												$income_unix_date += TWENTY_FOUR_HOURS;
												$day_income = date("j",$income_unix_date);
											}
											if ($timeoff) { $temp--;}
										} else {
											$temp = ZERO_DAY;
										}
									}
								} else {
									if ($month_answer == $month_income) {// if tickets income in holidays
										while(!isset($users_support_days[$second_admin_by][$admin_day])) {
											$income_unix_date += TWENTY_FOUR_HOURS;
											$day_income = date("j",$income_unix_date);
											$admin_day = date("md",$income_unix_date);
										}
										while($day_income != $day_answer) {
											$admin_day = date("md",$income_unix_date);
											if (isset($users_support_days[$second_admin_by][$admin_day])) {
												$temp++;
											}
											$income_unix_date += TWENTY_FOUR_HOURS;
											$day_income = date("j",$income_unix_date);
										}
									} else {
										$temp=10;
									}
								}
								/**/

								//$second_admin_by
								//$unix_date
								//$selected_year,$selected_month
								//$temp = floor($suppourt_messages[$second_key][SUPPORT_TIME] / TWENTY_FOUR_HOURS);
								/*/
								$day_income = date("j",$first_unix_date);
								$day_answer = date("j",$second_unix_date);
								if ($day_income != $day_answer) {
									$hour_income = date("G",$first_unix_date);
									if (isset($users_support_days[$second_admin_by][$day_income])) {
										$users_support_days[$second_admin_by][$day_income] += $local;
										if ($users_support_days[$second_admin_by][$day_income] <= ($first_unix_date - ONE_HOUR) && $temp != 0) {
											$temp--;
										}
									}
								}
								/**/
								/*/
								$hour_income = date("G",$first_unix_date);
								$day_income = date("j",$first_unix_date);
								
								if (isset($users_support_days[$second_admin_by][$day_income])) {
									list($user_y,$user_m,$user_d,$user_h,$user_m,$user_s) = datetime2array($users_support_days[$second_admin_by][$day_income]);
									$time_off_unix = mktime($user_s,$user_m,$user_h,$selected_month,$day_income,$selected_year);
									if ($time_off_unix <= ($first_unix_date - ONE_HOUR)) {
										$temp--;
									}
								}
								/**/
								
								show_microtime(" answered ($support_id)");
								$total_tickets++;
								switch ($temp) {
									case ZERO_DAY : $report_array[$second_admin_by][ZERO_DAY]++; break;
									case ONE_DAY  : $report_array[$second_admin_by][ONE_DAY]++; break;
									case TWO_DAY  : $report_array[$second_admin_by][TWO_DAY]++; break;
									case MORE_DAY : $report_array[$second_admin_by][MORE_DAY]++; break;
									default	:$report_array[$second_admin_by][MORE_DAY]++;
								}
								
								/*/
								$fline = @file("./downloads/111_new.txt");
								if (sizeof($fline)) {
									$ftemp = @tmpfile();
									if ($ftemp) {
										foreach($fline as $line) {
											list($fadmin_id, $fsupport_id, $fmessage_id) = explode(",",$line);
											$string_temp = "";
											$fmessage_id = (int)trim($fmessage_id);
											if ($fmessage_id == $second_message_id) {
												$string_temp .= "\t" . date("Y-m-d",$first_unix_date) . "\t" . date("Y-m-d",$second_unix_date);
											}
											if (strlen($string_temp)) {
												$new_line = array($fadmin_id, $fsupport_id, $fmessage_id, $string_temp);
												@fwrite($ftemp,implode(",",$new_line) . "\n");
											} else {
												$new_line = array($fadmin_id, $fsupport_id, $fmessage_id);
											}
										}
										@fseek($ftemp, 0);
										$fresult = @fopen("./downloads/111_new.txt", "a");
										if ($fresult) {
											while (($buffer = @fgets($ftemp, 4096))) {
												@fwrite($fresult,$buffer);
											}
											@fclose($fresult);
										}
										
										@fclose($ftemp);
									}
								}
								/**/
								
								
								
								/*/
								TWENTY_FOUR_HOURS
								$spent_time = 0;
								
								
								
								$first_day = date("d",$first_unix_date);
								$first_month = date("m",$first_unix_date);
								$first_year = date("Y",$first_unix_date);
								
								$user_end_work = explode(":",$users_support_days[$second_admin_by][$first_day]);
								$user_time_off = mktime($user_end_work[HOURS_OFF]-1,$user_end_work[MINUTS_OFF],0,$first_month,$first_day,$first_year);
								
								
								$first_evening = mktime(23,59,59,$first_month,$first_day,$first_year);
								if ($first_unix_date > $user_time_off) {
									$first_evening = mktime(23,59,59,$first_month,$first_day+1,$first_year);
								}
								
								
								
								
								$ticket_day_off = date("d", $first_evening);
								$ticket_month_off = date("m", $first_evening);
								if ($ticket_month_off == $seleted_month) {
									
								}
								/**/
								//while(isset($users_support_days[$second_admin_by])) {}
							}
						} else if ($second_status_id == TICKET_STATUS_ANSWERED) {
							//$res = @fopen($fticket,"a");
							//fwrite($res,$support_id . "\t" . $first_admin_by. "\t" . $first_message_id . "\r\n");
							//fclose($res);
						}
					} else {
					}
				
					prev($suppourt_messages);
				}
			}
		}
	} else { // error,no users
	}
	
	echo serialize($report_array);
	//var_dump($report_array);
	exit;
	
	
	/*
	*	Functions
	*/
	function getmicrotime()
	{ 
    	list($usec, $sec) = explode(" ",microtime()); 
    	return ((float)$usec + (float)$sec); 
    } 
	
	function show_microtime($event)
	{
		global $initial,$file_log;
		if (DEBUG_TIME) {
			$from_start = ($initial ? (getmicrotime() - $initial) : 0);
			$fp = @fopen($file_log,"a");
		    if ($fp) {
				fwrite($fp,number_format($from_start,4).": ".$event."\n");	
				fclose($fp);
			} else {
				echo number_format($from_start,4).": ".$event."\n";
			}
		}
	}
	
	function datetime2array($datetime)
	{
		$day = ""; $month = ""; $year = "";
		$hour = ""; $minut = ""; $second = "";
		
		$datetime_arr = explode(" ",$datetime);
		if (is_array($datetime_arr) && sizeof($datetime_arr)==2) {
			$date = $datetime_arr[0];
			$time = $datetime_arr[1];
			
			$date_arr = explode("-",$date);
			if (is_array($date_arr) && sizeof($date_arr)==3) {
				$year	= $date_arr[0];
				$month	= $date_arr[1];
				$day	= $date_arr[2];
			}
			$time_arr = explode(":",$time);
			if (is_array($time_arr) && sizeof($time_arr)==3) {
				$hour	= $time_arr[0];
				$minut	= $time_arr[1];
				$second	= $time_arr[2];
			}
			
		} else {
			$date_arr = explode("-",$datetime);
			if (is_array($date_arr) && sizeof($date_arr)==3) {
				$year	= $date_arr[0];
				$month	= $date_arr[1];
				$day	= $date_arr[2];
			} else {
				$time_arr = explode(":",$datetime);
				if (is_array($time_arr) && sizeof($time_arr)==3) {
					$hour	= $time_arr[0];
					$minut	= $time_arr[1];
					$second	= $time_arr[2];
				}
			}
		}
		return array($year,$month,$day,$hour,$minut,$second);
	}
	
	function datediff($first=0,$second=0,$diff=0)
	{
		$result = array();
		$result_time = -1;
		if (strlen($first) && strlen($second) && $first>0 && $second>0) {
			$result_time = abs($first - $second);
		} else if (strlen($diff) && $diff != 0) {
			$result_time = $diff;
		}
		if ($result_time>-1) {
			//difference in seconds 
			$result["s"] = $result_time;
			//difference in minutes
			$result["m"] = floor($result_time/60);
			 //difference in hours
			$result["h"] = floor($result_time/3600);
			//difference in days 
			$result["d"] = floor($result_time/86400);
			//difference in weeks
			$result["w"] = floor($result_time/604800);
			if ($first>0 && $second>0) {
				//difference in years 
				$result["y"] = abs(date("Y",$first)-date("Y",$second));
				//difference in months 
				$monthBegin = (date("Y",$first)*12)+date("n",$first);
				$monthEnd = (date("Y",$second)*12)+date("n",$second);
				$result["m"] = $monthEnd - $monthBegin;
			}
		}
		
		return $result;
	}
	
	function debug_tickets($message) {
		global $fticket;
		
		$ftemp = @fopen($fticket,"a");
		if ($ftemp) {
			fwrite($ftemp, $message . "\n");
			fclose($ftemp);
		}
	}
	
?>
