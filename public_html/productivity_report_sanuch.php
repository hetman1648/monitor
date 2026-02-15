<?php
	define("DEBUG_TIME",0);
	define("POINTS",2);
	
	//type points
	define("POINT_PER_TIME",1);
	define("POINT_PER_TASK",2);
	
	//type bonus
	define("CUSTOM_BONUS_PERCENT",1);
	define("CUSTOM_BONUS_UTHRESOLD",2);
	define("CUSTOM_BONUS_TTHRESOLD",3);
	
	//type projects
	define("SPECIES_CUSTOME",1);
	define("SPECIES_VIART",2);
	define("SPECIES_SAYU",3);
	define("SPECIES_TICKET",4);
	define("SPECIES_MANUAL",5);
	
	include("./includes/date_functions.php");
	include("./includes/common.php");

	CheckSecurity(1);

	$year_selected		= GetParam("year_selected");
	$month_selected		= GetParam("month_selected");
	if (!$year_selected) $year_selected = date("Y");
	if (!$month_selected) $month_selected = date("m");
	
	$action = GetParam("action");
	$submit = GetParam("submit");	
	
	$initial = getmicrotime();
	show_microtime("\n\nSTART");
	
	$projects = array();

	$t = new iTemplate($sAppPath);
	$t->set_file("main","productivity_report.html");
	
	$t->set_var("months", GetMonthOptions($month_selected));
	$t->set_var("years", GetYearOptions(2004, date("Y"), $year_selected));
	$t->set_var("month_selected", $month_selected);
	$t->set_var("year_selected", $year_selected);
	
	//$ticket_project_id = 113;
	//$viart_parent_project_id = 19;
/*	$viart_office_project_id = 63;
	$viart_com_project_id = 62;*/
	//$custom_work_project_id = 66;
	//$sayu_web_clients_parent_project_id = 79;
	//$sayu_web_properties_parent_project_id = 170;
	//$viart_web_clients_parent_project_id = 138;
	//$manual_project_id = 67;
	
	$teams = array (
		8	=> array(35,72,120),
		10	=> array(24,93,94),
		7	=> array(16,84,66,10),
	);
	
	foreach ($teams as $team_id => $user_team) {
		$users = array();
		$users_ids = array();
		//get people
		foreach ($user_team as $user_id) {
			$sql = " SELECT user_id, CONCAT(first_name, ' ', last_name) AS user_name, manager_id FROM users u ";
			$sql.= " WHERE user_id = ". ToSQL($user_id, "number", false);
			$sql.= " ORDER BY manager_id, user_name ";
			$db->query($sql);
			while ($db->next_record()) {
				$user_id = $db->f("user_id");
				$users_ids[] = $user_id;
				$users[$user_id]["user_id"] = $user_id;
				if ($db->f("manager_id")<=0) {
					$users[$user_id]["is_manager"] = 1;
				} else {
					$users[$user_id]["is_manager"] = 0;
				}
				$users[$user_id]["name"] = $db->f("user_name");
				$users[$user_id]["tickets"] = 0;
				$users[$user_id]["tickets_delayed"] = 0;
				$users[$user_id]["tickets_points"] = 0;
				$users[$user_id]["tickets_delayed_points"] = 0;
				$users[$user_id]["points"] = 0;
				$users[$user_id]["bonus"] = 0;
			}
		}
		$bonus_array = array();
		$sql = "SELECT * FROM productivity_rule WHERE team_id=" . ToSQL($team_id,"integer",false);
		$db->query($sql,__FILE__,__LINE__);
		if ($db->next_record()) {
			$bonus_user_thresold = $db->f("bonus_user_thresold");
			$bonus_team_thresold = $db->f("bonus_team_thresold");
			$bonus_manager_coeff = $db->f("bonus_manager_coeff");
			$bonus_user_coeff = $db->f("bonus_user_coeff");
			$bonus_bug = $db->f("bonus_bug");
			$bonus_client = $db->f("bonus_client");
			$bonus_other = $db->f("bonus_other");
			
			$bonus_array["bonus_user_thresold"] = $bonus_user_thresold;
			$bonus_array["bonus_team_thresold"] = $bonus_team_thresold;
			$bonus_array["bonus_user_coeff"] = $bonus_user_coeff;
			$bonus_array["bonus_manager_coeff"] = $bonus_manager_coeff;
			$bonus_array["bonus_bug"] = $bonus_bug;
			$bonus_array["bonus_client"] = $bonus_client;
			$bonus_array["bonus_other"] = $bonus_other;
		}
		
		$custom_bonus = array();
		$i = 0;
		$sql = "SELECT * FROM productivity_custom_bonus WHERE team_id=" . ToSQL($team_id,"integer",false);
		$db->query($sql,__FILE__,__LINE__);
		while ($db->next_record()) {
			$species_id = $db->f("species_id");
			$bonus_name = $db->f("bonus_name");
			$bonus_manager_used = $db->f("bonus_manager_used");
			$bonus_user_used = $db->f("bonus_user_used");
			$bonus_type = $db->f("bonus_type");
			$bonus_value = $db->f("bonus_value");
			
			$custom_bonus[$i]["species_id"] = $species_id;
			$custom_bonus[$i]["bonus_name"] = $bonus_name;
			$custom_bonus[$i]["bonus_manager_used"] = $bonus_manager_used;
			$custom_bonus[$i]["bonus_user_used"] = $bonus_user_used;
			$custom_bonus[$i]["bonus_type"] = $bonus_type;
			$custom_bonus[$i]["bonus_value"] = $bonus_value;
			$i++;
		}
		
		$team_projects = array();
		/*/
		$sql  = " SELECT tp.project_id, IFNULL(tp.project_name,p.project_title) as project_name, tp.coefficient_type, tp.coefficient ";
		$sql .= " FROM (productivity_team_projects tp ";
		$sql .= "	INNER JOIN projects p ON p.project_id=tp.project_id)";
		$sql .= " WHERE team_id=" . ToSQL($team_id,"integer",false);
		$db->query($sql,__FILE__,__LINE__);
		while ($db->next_record()) {
			$project_id = $db->f("project_id");
			$project_name = $db->f("project_name");
			$coefficient_type = $db->f("coefficient_type");
			$coefficient = $db->f("coefficient");
			$team_projects[$project_id] = array($project_name, $coefficient_type, $coefficient);
		}
		/**/
		$sql  = " SELECT tp.species_id, tp.project_name, tp.coefficient_type, tp.coefficient ";
		$sql .= " FROM productivity_team_projects tp ";
		$sql .= " WHERE tp.team_id=" . ToSQL($team_id,"integer",false);
		$db->query($sql,__FILE__,__LINE__);
		while ($db->next_record()) {
			$species_id = $db->f("species_id");
			$project_name = $db->f("project_name");
			$coefficient_type = $db->f("coefficient_type");
			$coefficient = $db->f("coefficient");
			$team_projects[$species_id] = array($project_name, $coefficient_type, $coefficient);
		}
		/**/
		
		$sql = "SELECT team_name FROM users_teams WHERE team_id = " . ToSQL($team_id,"integer",false);
		$db->query($sql,__FILE__,__LINE__);
		$t->set_var("team_name","");
		if ($db->next_record()) {
			$t->set_var("team_name"," for " . $db->f("team_name"));
		}
		$t->set_var("custom_header","");
		$t->set_var("tickets_header","");
		$t->set_var("viart_web_clients_header","");
		$t->set_var("viart_header","");
		$t->set_var("sayu_header","");
		$t->set_var("manual_header","");
		
		//var_dump($team_projects);
		//var_dump($team_projects);echo"<br>\n\r";
		//var_dump(__LINE__);echo"<br>\n\r";exit;
	
		foreach($team_projects as $species_id => $projects) {
			switch($species_id) {
				case SPECIES_CUSTOME:
					$t->parse("viart_web_clients_header",true);
					break;
				case SPECIES_VIART:
					$t->parse("viart_header",true);
					break;
				case SPECIES_SAYU:
					$t->parse("sayu_header",true);
					break;
				case SPECIES_TICKET:
					$t->parse("tickets_header",true);
					break;
				case SPECIES_MANUAL:
					$t->parse("manual_header",true);
					break;
			}
		}
		$t->parse("custom_header",false);

		foreach($users_ids as $user_id) {
			//calculate working days and when they end
			//$users[$user_id]["working_days"] = array();
			$users[$user_id]["working_days"] = calculate_working_days($user_id,$year_selected,$month_selected);
			show_microtime("working days");
			
			foreach($team_projects as $species_id => $projects) {
				switch($species_id) {
					case SPECIES_CUSTOME:
						//select custom work			
						$users[$user_id]["viart_web_clients"] = select_custome_work($user_id,$year_selected,$month_selected);
						show_microtime("viart_web_clients");
						break;
					case SPECIES_VIART:
						//select viart hours
						$users[$user_id]["viart"] = select_viart_hours($user_id,$year_selected,$month_selected);
						show_microtime("viart");
						break;
					case SPECIES_SAYU:
						//select sayu hours
						$users[$user_id]["sayu"] = select_sayu_hours($user_id,$year_selected,$month_selected);
						show_microtime("sayu");
						break;
					case SPECIES_TICKET:
						if ($team_id == 8) {
							support_tickets_points($user_id,$year_selected,$month_selected);
							show_microtime("support tickets points");
						} else {
							//select tickets points
							viart_tickets_points($user_id,$year_selected,$month_selected);
							show_microtime("tickets points");
						}
						break;
					case SPECIES_MANUAL: 
						$users[$user_id]["manual"] = select_manual_hours($user_id,$year_selected,$month_selected);
						show_microtime("manual");
						break;
				}
			}

			//select bugs
			$users[$user_id]["bugs"] = select_bugs($user_id,$year_selected,$month_selected);
			show_microtime("bugs");

			$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month_selected, $year_selected);
			
			//select vacation
			$users[$user_id]["paid_days"] = select_vacation($user_id,$year_selected,$month_selected,$days_in_month);
			show_microtime("paid days");
			
			//show not rated tasks
			//$users[$user_id]["not_rated"] = array();
			$users[$user_id]["not_rated"] = show_not_rated_tasks($user_id,$year_selected,$month_selected);
			show_microtime("not_rated");
			
			//show tasks for other projects
			//$users[$user_id]["other_projects"] = array();
			$users[$user_id]["other_projects"] = show_tasks_for_other_projects($user_id,$year_selected,$month_selected);
			show_microtime("other projects");
		}
		
		
		
		$sum_points = $sum_bugs = $sum_viart = $sum_sayu = $sum_viart_web_clients = 0;
		$sum_tickets = $sum_tickets_delayed = $sum_tickets_points = $sum_tickets_delayed_points = $sum_paid_days = $sum_manual =0;
		
		foreach ($users as $user_id=>$user) {
			$points = 0;

			if (isset($user["viart_web_clients"])) { $points += $user["viart_web_clients"];}
			if (isset($user["viart"])) 		 { $points += $user["viart"];}
			if (isset($user["sayu"])) 		 { $points += $user["sayu"];}
			if (isset($user["bugs"]))		 { $points += $user["bugs"];}
			if (isset($user["paid_days"]))	 { $points += $user["paid_days"];}
			if (isset($user["tickets_points"])) { $points += $user["tickets_points"];}
			if (isset($user["tickets_delayed_points"])) { $points += $user["tickets_delayed_points"];}
			if (isset($user["manual"]))		 { $points += $user["manual"];}
			
			
			$users[$user_id]["points"] = $points;
			$sum_points	+= $points;
			if (isset($user["bugs"])) { $sum_bugs	+= $user["bugs"];}
			if (isset($user["viart"])) { $sum_viart	+= $user["viart"];}
			if (isset($user["sayu"])) { $sum_sayu	+= $user["sayu"];}
			if (isset($user["viart_web_clients"])) { $sum_viart_web_clients += $user["viart_web_clients"];}
			if (isset($user["tickets"])) { $sum_tickets += $user["tickets"];}
			if (isset($user["tickets_points"])) { $sum_tickets_delayed += $user["tickets_delayed"];}
			if (isset($user["tickets_points"])) { $sum_tickets_points += $user["tickets_points"];}
			if (isset($user["tickets_delayed_points"])) { $sum_tickets_delayed_points += $user["tickets_delayed_points"];}
			if (isset($user["paid_days"])) { $sum_paid_days += $user["paid_days"];}
			if (isset($user["manual"])) { $sum_paid_days += $user["manual"];}
		}
		
		/**/
		if (sizeof($custom_bonus)) { 
			custome_bonus_manager($users);
			custome_bonus_user($users);
			$sum_points = 0;
			foreach($users as $user_id=>$user) {
				$sum_points	+= $user["points"];
			}
		}
		/**/
		
		$t->set_var("custom_total","");
		$t->set_var("tickets_total","");
		$t->set_var("viart_web_clients_total","");
		$t->set_var("viart_total","");
		$t->set_var("sayu_total","");
		$t->set_var("manual_total","");
		
		foreach($team_projects as $species_id => $projects) {
			switch($species_id) {
				case SPECIES_CUSTOME:
					$t->set_var("sum_viart_web_clients", $sum_viart_web_clients);
					$t->parse("viart_web_clients_total",false);
					break;
				case SPECIES_VIART:
					$t->set_var("sum_viart", $sum_viart);
					$t->parse("viart_total",false);
					break;
				case SPECIES_SAYU:
					$t->set_var("sum_sayu", $sum_sayu);
					$t->parse("sayu_total",false);
					break;
				case SPECIES_TICKET:
					$t->set_var("sum_tickets", $sum_tickets);
					$t->set_var("sum_tickets_delayed", $sum_tickets_delayed);
					$t->set_var("sum_tickets_points", $sum_tickets_points);
					$t->set_var("sum_tickets_delayed_points", $sum_tickets_delayed_points);
					$t->parse("tickets_total",false);
					break;
				case SPECIES_MANUAL:
					$t->set_var("sum_manual", $sum_manual);
					$t->parse("manual_total",false);
					break;
			}
		}
		$t->parse("custom_total",false);
		$t->set_var("sum_points", $sum_points);
		$t->set_var("sum_bugs", $sum_bugs);
		$t->set_var("sum_paid_days", $sum_paid_days);
		
		//calculate bonus/team points
		/*/
		foreach ($users as $user_id=>$user) {
			if ($user["points"] >= $bonus_array["bonus_user_thresold"] && $user["is_manager"] == 0) {
				$users[$user_id]["bonus"] = ($user["points"] - $bonus_array["bonus_user_thresold"]) * $bonus_array["bonus_user_coeff"];
				if (isset($users[$user_id]["feedback_rating"])) {
					$users[$user_id]["bonus"] = $users[$user_id]["bonus"] * $users[$user_id]["feedback_rating"];
				}
			} elseif ($user["is_manager"] == 1 && $sum_points >= $bonus_array["bonus_team_thresold"]) {
				$users[$user_id]["bonus"] = ($sum_points - $bonus_array["bonus_team_thresold"]) * $bonus_array["bonus_manager_coeff"];
			}
		}
		/**/
		
		$t->set_var("people", "");
		foreach ($users_ids as $user_id) {
			//if (in_array($user_id, $show_users)) {
				
			$t->set_var("user_id", $user_id);
			$t->set_var("custom_body","");
			$t->set_var("tickets_block","");
			$t->set_var("viart_web_clients_block","");
			$t->set_var("viart_block","");
			$t->set_var("sayu_block","");
			$t->set_var("manual_block","");
			if (isset($users[$user_id]) && is_array($users[$user_id])) {
				$t->set_var("user_name", $users[$user_id]["name"]);
				foreach($team_projects as $species_id => $projects) {
					switch($species_id) {
						case SPECIES_CUSTOME:
							$t->set_var("viart_web_clients", $users[$user_id]["viart_web_clients"]);
							$t->parse("viart_web_clients_block",false);
							break;
						case SPECIES_VIART:
							$t->set_var("viart", $users[$user_id]["viart"]);
							$t->parse("viart_block",false);
							break;
						case SPECIES_SAYU:
							$t->set_var("sayu", $users[$user_id]["sayu"]);
							$t->parse("sayu_block",false);
							break;
						case SPECIES_TICKET:
							$t->set_var("tickets", $users[$user_id]["tickets"]);
							$t->set_var("tickets_delayed", $users[$user_id]["tickets_delayed"]);
							$t->set_var("tickets_points", $users[$user_id]["tickets_points"]);
							$t->set_var("tickets_delayed_points", $users[$user_id]["tickets_delayed_points"]);
							$t->parse("tickets_block",false);
							break;
						case SPECIES_MANUAL:
							$t->set_var("manual", $users[$user_id]["manual"]);
							$t->parse("manual_block",false);
							break;
					}
				}				
				
				$t->parse("custom_body",false);
				
				$t->set_var("bugs", $users[$user_id]["bugs"]);
				$t->set_var("points", $users[$user_id]["points"]);
				$t->set_var("bonus", number_format($users[$user_id]["bonus"],2));
				$t->set_var("paid_days", $users[$user_id]["paid_days"]);
				
				$t->set_var("not_rated_tasks", "");
				if (is_array($users[$user_id]["not_rated"]) && sizeof($users[$user_id]["not_rated"])) {
					foreach($users[$user_id]["not_rated"] as $task) {
						$t->set_var("task_id", $task["task_id"]);
						$t->set_var("task_title", $task["task_title"]);
						$t->set_var("spent_hours", Hours2HoursMins($task["task_spent_hours"]));
						$t->parse("not_rated_tasks", true);
					}
				}

				$t->set_var("other_projects", "");
				if (is_array($users[$user_id]["other_projects"]) && sizeof($users[$user_id]["other_projects"])) {
					foreach($users[$user_id]["other_projects"] as $task) {
						$t->set_var("task_id", $task["task_id"]);
						$t->set_var("task_title", $task["task_title"]);
						$t->set_var("spent_hours", Hours2HoursMins($task["task_spent_hours"]));
						$t->parse("other_projects", true);
					}
				}
			}
			$t->parse("people", true);
			//}
		}
		
		$sum_bonus = 0;
		foreach($users as $user) {
			$sum_bonus += $user["bonus"];
		}
		$t->set_var("sum_bonus", number_format($sum_bonus, 2));
		
		$t->parse("team_block",true);
	}
	
	$t->pparse("main");
	
	
/*
**	Functions
*/
//define("CUSTOM_BONUS_UTHRESOLD",2);
//define("CUSTOM_BONUS_TTHRESOLD",3);	
function custome_bonus_manager(&$users) {
	global $custom_bonus, $bonus_array, $team_projects;
	
	$manager_id = 0;
	foreach ($users as $user_id => $user) {
		if($user["is_manager"] == 1) {
			$manager_id = $user_id;
			break;
		}
	}
	if (!$manager_id) { return; }
	
	
	foreach($custom_bonus as $key => $bonus) {
		if (!$bonus["bonus_manager_used"]) { continue; }
		
		$species_id = $bonus["species_id"];
		if (!isset($team_projects[$species_id]) && $species_id != 0) { continue; }
		$project_name = "";
		if ($species_id != 0) {
			list($project_name) = $team_projects[$species_id];
			$project_name = strtolower(str_replace(" ","_",$project_name));
		}
		
		switch($bonus["bonus_type"]){
			case CUSTOM_BONUS_PERCENT: 
				foreach ($users as $user_id=>$user) {
					if($user["is_manager"] == 1) { continue; }
					if (strlen($project_name) && isset($user[$project_name])) {
						$users[$manager_id]["points"] += floor($user[$project_name] * $bonus["bonus_value"] / 100);
					}
				}
				break;
			case CUSTOM_BONUS_UTHRESOLD:
				if ($users[$manager_id]["points"] > $bonus_array["bonus_user_thresold"]) {
					$users[$manager_id]["bonus"] = ($users[$manager_id]["points"] - $bonus_array["bonus_user_thresold"]) * $bonus_array["bonus_user_coeff"];
				}
				break;
			case CUSTOM_BONUS_TTHRESOLD:
				$sum_points = 0;
				foreach($users as $user_id=>$user) {
					$sum_points	+= $user["points"];
				}
				if ($sum_points >= $bonus_array["bonus_team_thresold"]) {
					$users[$manager_id]["bonus"] = ($sum_points - $bonus_array["bonus_team_thresold"]) * $bonus_array["bonus_manager_coeff"];
				}
				break;
		}
	}

	return;
}
	
function custome_bonus_user(&$users) {
	global $custom_bonus, $bonus_array, $team_projects;
	
	foreach($custom_bonus as $key => $bonus) {
		if (!$bonus["bonus_user_used"]) { continue; }
		
		foreach ($users as $user_id => $user) {
			if($users[$user_id]["is_manager"]) { continue; }
			
			switch($bonus["bonus_type"]){
				case CUSTOM_BONUS_UTHRESOLD:
					if ($users[$user_id]["points"] > $bonus_array["bonus_user_thresold"]) {
						$users[$user_id]["bonus"] = ($users[$user_id]["points"] - $bonus_array["bonus_user_thresold"]) * $bonus_array["bonus_user_coeff"];
					}
					break;
			}
			
			if (isset($users[$user_id]["feedback_rating"])) {
				$users[$user_id]["bonus"] = $users[$user_id]["bonus"] * $users[$user_id]["feedback_rating"];
			}
		}
	}
}
	
function getmicrotime(){ 
   	list($usec, $sec) = explode(" ",microtime()); 
   	return ((float)$usec + (float)$sec); 
} 

function show_microtime($event) {
	global $initial;
	if (DEBUG_TIME) {
		$fp = @fopen("timing.txt","a");	    
	    
	    if ($fp) {
			$from_start = ($initial ? (getmicrotime() - $initial) : 0);
			fwrite($fp,number_format($from_start,4).": ".$event."\n");	
		    fclose($fp);
		}
	}
}
	
function datetime2time($datetime) {
	$datetime_arr = explode(" ",$datetime);
	if (is_array($datetime_arr) && sizeof($datetime_arr)==2) {
		$date = $datetime_arr[0];
		$time = $datetime_arr[1];
		return array($date, $time);
	} else {
		return array("","");
	}	
}

function datetime2array($datetime) {
	
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
		
	}	
	return array($year,$month,$day,$hour,$minut,$second);
}

function get_previous_day($report_days, $current_day)
{
	$arr = true;
	$report_day = "0000-00-00";
	$previous_day = $report_day;
	reset($report_days);
	while ($arr && $report_day<$current_day) {	
		if (isset($arr) && isset($arr["key"])) {
			$previous_day = $arr["key"];
		}
		$arr = each($report_days);
		$report_day = $arr["key"];
		$report_time = $arr["value"];
		$next_elem = $arr;
	}
	return $previous_day;
}

function sub_time($time, $sub) {
	$time_arr = explode(":", $time);
	$sub_arr = explode(":", $sub);
	$res_arr = $time_arr;
	if (is_array($time_arr) && is_array($sub_arr) && sizeof($time_arr)==3 && sizeof($sub_arr)==3) {
		$res_arr[2] = $res_arr[2] - $sub_arr[2];
		if ($res_arr[2]<0) {
			$res_arr[1]--;
			$res_arr[2]+=60;
		}
		$res_arr[1] = $res_arr[1] - $sub_arr[1];
		if ($res_arr[1]<0) {
			$res_arr[0]--;
			$res_arr[1]+=60;
		}
		
		$res_arr[0] = $res_arr[0] - $sub_arr[0];
		if ($res_arr[0]<0) {
			$res_arr[0] = 0;
		}		
	}
	
	foreach ($res_arr as $key=>$res_val) {
		if ($res_arr[$key]<10) {
			$res_arr[$key] = "0".$res_arr[$key];
		} elseif ($res_arr[$key]==0) {
			$res_arr[$key] == "00";
		}
	}
	return implode(":", $res_arr);
}

function get_project_ids($species) {
	global $db;
	
	$ids = array();
	if(strlen($species)) {
		$sql  = " SELECT ps.project_id as project_id";
		$sql .= " FROM (productivity_project_species ps ";
		$sql .= " NATURAL JOIN projects p) ";
		$sql .= " WHERE ps.species_id = " . ToSQL($species,"INTEGER");
		$db->query($sql,__FILE__,__LINE__);
		while($db->next_record()) {
			$ids[] = $db->f("project_id");
		}
	}
	
	return implode(",",$ids);
}

function select_custome_work($user_id,$year_selected,$month_selected) {
	global $db, $viart_web_clients_parent_project_id;
	
	$result = 0;
	
	$custome_ids = get_project_ids(SPECIES_CUSTOME);
	if (strlen($custome_ids)) {
		$sql = " SELECT SUM(x.user_task_points) FROM (";
		$sql.= " SELECT t.task_cost*SUM(tr.spent_hours)/t.actual_hours*t.completion/100 AS user_task_points ";
		$sql.= " FROM tasks t INNER JOIN time_report tr ON (tr.task_id=t.task_id) ";
		$sql.= " INNER JOIN projects p ON (t.project_id=p.project_id) ";
		$sql.= " WHERE tr.user_id=".ToSQL($user_id, "integer")." AND p.parent_project_id IN (" . $custome_ids . ") ";
		$sql.= " AND DATE_FORMAT(tr.report_date, '%Y')='$year_selected' AND DATE_FORMAT(tr.report_date, '%m')='$month_selected' ";
		$sql.= " GROUP BY t.task_id ";
		$sql.= ") AS x";
		$db->query($sql,__FILE__,__LINE__);
		
		if ($db->next_record()) {
			$result = round($db->f(0));
		}
	}
	
	return $result;
}

function select_viart_hours($user_id,$year_selected,$month_selected) {
	global $db, $viart_parent_project_id, $viart_web_clients_parent_project_id, $ticket_project_id;
	global $team_projects;
	
	$result = 0;
	$coefficient = $team_projects[SPECIES_VIART][POINTS];
	
	$viart_ids = get_project_ids(SPECIES_VIART);
	$custome_ids = get_project_ids(SPECIES_CUSTOME);
	$ticket_ids = get_project_ids(SPECIES_TICKET);
	if (strlen($viart_ids) && strlen($custome_ids) && strlen($ticket_ids)) {
		$sql = " SELECT SUM(tr.spent_hours) AS tasks_spent_hours ";
		$sql.= " FROM tasks t INNER JOIN time_report tr ON (tr.task_id=t.task_id) ";
		$sql.= " INNER JOIN projects p ON (p.project_id=t.project_id) ";
		$sql.= " WHERE tr.user_id=".ToSQL($user_id, "integer");
		$sql.= " AND (p.parent_project_id IN (" . $viart_ids . ") OR p.project_id IN (" . $viart_ids . ")) ";
		$sql.= " AND p.project_id NOT IN (" . $ticket_ids . ") AND p.parent_project_id NOT IN (" . $custome_ids . ") ";
		$sql.= " AND DATE_FORMAT(tr.report_date, '%Y')='$year_selected' AND DATE_FORMAT(tr.report_date, '%m')='$month_selected' ";
		$db->query($sql,__FILE__,__LINE__);
		
		if ($db->next_record()) {
			$result = round($db->f(0) * $coefficient);
		}
	}
	
	return $result;
}

function select_sayu_hours($user_id,$year_selected,$month_selected) {
	global $db, $sayu_web_clients_parent_project_id, $sayu_web_properties_parent_project_id;
	global $team_projects;
	
	$result = 0;
	$coefficient = $team_projects[SPECIES_SAYU][POINTS];
	
	$sayu_ids = get_project_ids(SPECIES_SAYU);
	if (strlen($sayu_ids)) {
		$sql = " SELECT SUM(tr.spent_hours) AS tasks_spent_hours ";
		$sql.= " FROM tasks t INNER JOIN time_report tr ON (tr.task_id=t.task_id) ";
		$sql.= " INNER JOIN projects p ON (p.project_id=t.project_id) ";
		$sql.= " WHERE tr.user_id=".ToSQL($user_id, "integer");
		//$sql.= " AND (p.parent_project_id=".ToSQL($sayu_web_clients_parent_project_id, "integer")." OR p.project_id=".ToSQL($sayu_web_clients_parent_project_id, "integer").") ";
		$sql.= " AND (p.parent_project_id IN (" . $sayu_ids . ") ";
		$sql.= " OR p.project_id IN (" . $sayu_ids . ") ) ";
		$sql.= " AND DATE_FORMAT(tr.report_date, '%Y')='$year_selected' AND DATE_FORMAT(tr.report_date, '%m')='$month_selected' ";
		$db->query($sql,__FILE__,__LINE__);
		
		if ($db->next_record()) {
			$result = round($db->f(0) * $coefficient);
		}
	}
	
	return $result;
}

function select_bugs($user_id,$year_selected,$month_selected) {
	global $db;
	global $bonus_array;
	
	$result = 0;
	$sql = " SELECT SUM(importance_level) FROM bugs ";
	$sql.= " WHERE user_id=".ToSQL($user_id, "integer");
	$sql.= " AND DATE_FORMAT(date_issued, '%Y')='$year_selected' AND DATE_FORMAT(date_issued, '%m')='$month_selected' ";
	$db->query($sql,__FILE__,__LINE__);
	
	$result = 0;
	if ($db->next_record()) {
		$result = round($db->f(0) * $bonus_array["bonus_bug"]);
	}
	
	return $result;
}

function select_vacation($user_id,$year_selected,$month_selected,$days_in_month) {
	global $db;
	global $bonus_array;
	
	$result = 0;
	
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
		$result = $db->f(0) * $bonus_array["bonus_other"];
	}
	
	return $result;
}

function show_not_rated_tasks($user_id,$year_selected,$month_selected) {
	global $db, $viart_web_clients_parent_project_id;
	
	$result = array();
	$custome_ids = get_project_ids(SPECIES_CUSTOME);
	if (strlen($custome_ids)) {
		$sql = " SELECT t.task_id, t.task_title, SUM(tr.spent_hours) AS task_spent_hours ";
		$sql.= " FROM tasks t ";
		$sql.= " INNER JOIN time_report tr ON (t.task_id = tr.task_id) ";
		$sql.= " INNER JOIN projects p ON (t.project_id=p.project_id) ";
		$sql.= " WHERE tr.user_id=".ToSQL($user_id, "integer")." AND p.parent_project_id IN (" . $custome_ids . ") ";
		$sql.= " AND DATE_FORMAT(tr.report_date, '%Y')='$year_selected' AND DATE_FORMAT(tr.report_date, '%m')='$month_selected' ";
		$sql.= " AND (t.task_cost IS NULL OR t.task_cost=0) ";
		$sql.= " GROUP BY t.task_id ORDER BY task_spent_hours DESC ";
		$db->query($sql,__FILE__,__LINE__);
		
		while($db->next_record()) {
			$result[] = $db->Record;
		}
	}
	
	return $result;
}

function show_tasks_for_other_projects($user_id,$year_selected,$month_selected) {
	global $db, $viart_parent_project_id, $viart_web_clients_parent_project_id;
	global $sayu_web_clients_parent_project_id,$sayu_web_properties_parent_project_id;
	
	$result = array();
	
	$viart_ids = get_project_ids(SPECIES_VIART);	
	$parent_project_ids = array(
			$viart_ids,
			get_project_ids(SPECIES_SAYU),
			get_project_ids(SPECIES_CUSTOME)
		);
		
	if (strlen($viart_ids)) {
		$sql = " SELECT t.task_id, t.task_title, SUM(tr.spent_hours) AS task_spent_hours ";
		$sql.= " FROM tasks t ";
		$sql.= " INNER JOIN time_report tr ON (t.task_id = tr.task_id) ";
		$sql.= " INNER JOIN projects p ON (p.project_id = t.project_id) ";
		$sql.= " WHERE tr.user_id=".ToSQL($user_id, "integer");
		$sql.= " AND DATE_FORMAT(tr.report_date, '%Y')='$year_selected' AND DATE_FORMAT(tr.report_date, '%m')='$month_selected' ";
		$sql.= " AND t.project_id NOT IN (" . $viart_ids . ") ";
		$sql.= " AND p.parent_project_id NOT IN (" . implode(",",$parent_project_ids) . ") ";
		$sql.= " GROUP BY t.task_id ORDER BY task_spent_hours DESC ";
		$db->query($sql,__FILE__,__LINE__);
		
		while($db->next_record()) {
			$result[] = $db->Record;
		}
	}
	
	return $result;
}

function calculate_working_days($user_id,$year_selected,$month_selected) {
	global $db;
	
	$result = array();
	$sql  = " SELECT tr.user_id, MAX(tr.report_date) AS workday_end ";
	$sql .= " FROM time_report tr ";
	$sql .= " WHERE DATE_ADD(tr.report_date, INTERVAL 3 MONTH)>'".$year_selected."-".$month_selected."-01' ";
	$sql .= " 	AND DATE_SUB(tr.report_date, INTERVAL 1 MONTH)<='".$year_selected."-".$month_selected."-01' ";
	$sql .= "	AND tr.user_id=" . ToSQL($user_id,"integer");
	$sql .= " GROUP BY tr.user_id, DAYOFYEAR(tr.report_date) ";
	$sql .= " ORDER BY tr.user_id, tr.report_date ";
	$db->query($sql,__FILE__,__LINE__);
	
	while($db->next_record()) {
		list($workday, $workday_time) = datetime2time($db->f("workday_end"));
		$result[$workday] = $workday_time;
	}
		
	return $result;
}

function viart_tickets_points($user_id,$year_selected,$month_selected) {
	global $db, $ticket_project_id;
	global $users, $team_projects;
	
	$coefficient_string = $team_projects[SPECIES_TICKET][POINTS];
	$coefficients = array();
	$coefficients = explode(";",$coefficient_string);
	$ticket_ids = get_project_ids(SPECIES_TICKET);
	
	if (strlen($ticket_ids)) {
		$sql  = " SELECT m.message_id, m.user_id, m.message_date, IF( pm.message_id IS NOT NULL ";
		$sql .= " , MAX( pm.message_date ) , t.creation_date ) AS previous_message_date ";
		$sql .= " FROM tasks t ";
		$sql .= " INNER JOIN messages m ON ( m.identity_id = t.task_id AND m.identity_type = 'task' AND m.user_id != m.responsible_user_id ";
		$sql .= " AND DATE_FORMAT( m.message_date, '%Y' ) = '".$year_selected."' ";
		$sql .= " AND DATE_FORMAT( m.message_date, '%m' ) = '".$month_selected."' AND m.user_id=".ToSQL($user_id, "integer").") ";
		$sql .= " LEFT JOIN messages pm ON ( pm.identity_type = 'task' AND pm.identity_id = t.task_id AND pm.responsible_user_id = m.user_id ";
		$sql .= " 	AND pm.user_id != m.user_id AND m.message_id > pm.message_id AND pm.user_reply IS NULL ) ";
		$sql .= " WHERE t.project_id IN (" . $ticket_ids . ") AND t.ticket_id IS NOT NULL ";
		$sql .= " GROUP BY m.user_id, t.task_id, m.message_id ";
		$sql .= " ORDER BY m.user_id ASC, t.task_id ASC, m.message_date ASC ";
		$db->query($sql,__FILE__,__LINE__);
		//var_dump($sql);echo"\r\n<br>";exit;
		
		while($db->next_record()) {
			list($message_day, $message_time) = datetime2time($db->f("message_date"));
			list($previous_message_day, $previous_message_time) = datetime2time($db->f("previous_message_date"));
			$delayed_days = 0;
			
			if ($previous_message_day != $message_day) {
				$i=0;
				$previous_day = $message_day;
				do {
					$previous_day = get_previous_day($users[$user_id]["working_days"], $previous_day);
					$delayed_days++;
					if (isset($users[$user_id]["working_days"][$previous_day])) {
						if (($previous_day == $previous_message_day 
							&& sub_time($users[$user_id]["working_days"][$previous_day], "01:00:00") < $previous_message_time)
							|| ($previous_day < $previous_message_day)) {
							$delayed_days--;
						}					
					}
				} while ($previous_day > $previous_message_day && $previous_day);
			}

			if ($delayed_days == 0) {
				$users[$user_id]["tickets"]++;
				$users[$user_id]["tickets_points"] += $coefficients[$delayed_days];
			} else {
				$users[$user_id]["tickets_delayed"]++;
				if ($delayed_days >= sizeof($coefficients)) {
					$delayed_days = sizeof($coefficients) - 1;
				}
				$users[$user_id]["tickets_delayed_points"] += $coefficients[$delayed_days];
			}
		}
	}
	
	return true;
}

function support_tickets_points($user_id,$year_selected,$month_selected) {
	global $db,$users,$team_projects,$ticket_project_id;
	
	$coefficient_string = $team_projects[SPECIES_TICKET][POINTS];
	$coefficients = array();
	$coefficients = explode(";",$coefficient_string);
	
	$sql  = " SELECT sr.type_bonus_id,  sr.count_bonus ";
	$sql .= " FROM (support_reports_sash sr ";
	$sql .= " 	INNER JOIN support_import_sash si ON si.import_id = sr.import_id) ";
	$sql .= " WHERE user_id = " . ToSQL($user_id,"number");
	$sql .= "	AND DATE_FORMAT(si.update_date,'%m')=" . ToSQL($month_selected,"string");
	$sql .= "	AND DATE_FORMAT(si.update_date,'%Y')=" . ToSQL($year_selected,"string");
	$db->query($sql,__FILE__,__LINE__);
	while($db->next_record()) {
		$type_bonus_id = $db->f("type_bonus_id") - 1;
		$count_bonus = $db->f("count_bonus");
		
		if ($type_bonus_id == 0) {
			$users[$user_id]["tickets"] += $count_bonus;
			$users[$user_id]["tickets_points"] += $count_bonus * $coefficients[$type_bonus_id];
		} else if ($type_bonus_id != 998) {
			$users[$user_id]["tickets_delayed"]+= $count_bonus;
			$users[$user_id]["tickets_delayed_points"] += $count_bonus * $coefficients[$type_bonus_id];
		} else {
			$users[$user_id]["feedback_rating"] = $count_bonus / 100;
		}
	}
	
	return true;
}

function select_manual_hours($user_id,$year_selected,$month_selected) {
	global $db, $manual_project_id,$viart_parent_project_id, $viart_web_clients_parent_project_id, $ticket_project_id;;
	global $team_projects;
	
	$result = 0;
	$coefficient = $team_projects[SPECIES_MANUAL][POINTS];
	
	$manual_ids = get_project_ids(SPECIES_MANUAL);
	$viart_ids = get_project_ids(SPECIES_VIART);
	$ticket_ids = get_project_ids(SPECIES_TICKET);
	
	$custome_ids = get_project_ids(SPECIES_CUSTOME);
	
	if (strlen($manual_ids) && strlen($viart_ids) && strlen($ticket_ids) && strlen($custome_ids)) {
		$sql = " SELECT SUM(tr.spent_hours) AS tasks_spent_hours ";
		$sql.= " FROM tasks t INNER JOIN time_report tr ON (tr.task_id=t.task_id) ";
		$sql.= " INNER JOIN projects p ON (p.project_id=t.project_id) ";
		$sql.= " WHERE tr.user_id=".ToSQL($user_id, "integer");
		$sql.= " AND (p.parent_project_id IN (" . $manual_ids . ") OR p.project_id IN (" . $manual_ids . ")) ";
		$sql.= " AND p.project_id NOT IN (" . implode(",",array($viart_ids,$ticket_ids)) . ") ";
		$sql.= " AND p.parent_project_id NOT IN (" . $custome_ids . ")";
		$sql.= " AND DATE_FORMAT(tr.report_date, '%Y')='$year_selected' AND DATE_FORMAT(tr.report_date, '%m')='$month_selected' ";
		$db->query($sql,__FILE__,__LINE__);
		
		if ($db->next_record()) {
			$result = round($db->f(0) * $coefficient);
		}
	}
	
	
	return $result;
}
?>