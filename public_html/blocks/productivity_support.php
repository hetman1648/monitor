<?php
	chdir("../");	
	include("./includes/common.php");
	include("./includes/productivity_functions.php");
	
	CheckSecurity(1);
	
	$db2 = new DB_Sql();
	$db2->Database = DATABASE_NAME;
	$db2->User     = DATABASE_USER;
	$db2->Password = DATABASE_PASSWORD;
	$db2->Host     = DATABASE_HOST;
	
	$t = new iTemplate($sAppPath);
	
	$t->set_file("main","block_productivity_viart.html");
	$t->set_var("team_title", "Summary for Viart Development Team");
	
	calculate_all(
		array(16, 10), // team
		1500, // user thres
		0.2,  // user coeff
		3000, // team thres
		0.075,// team coeff
		1,    // custom work
		10,   // viart
		15,   // sayu
		0,    // manuals
		array(20, 10, 0, -20), // day points
		80, // vacations
		5 //bugs
	);
	
	$t->parse("main");
	
	$t->set_file("main","block_productivity_viart.html");
	$t->set_var("team_title", "Summary for Viart Support Team");
		
	calculate_all(
		array(35,120), // team
		1500, // user thres
		0.2,  // user coeff
		3000, // team thres
		0.1,  // team coeff
		1,    // custom work
		10,   // viart
		10,   // sayu
		10,    // manuals
		true, // day points
		80, // vacations
		5, //bugs
		true // use special tickets
	);
	
	$t->pparse("main");
	
	
	function special_tickets_points(&$users, $user_id, $year_selected, $month_selected) {
		global $db;
						
		$sql  = " SELECT SUM(day_good) AS sum_day_good, SUM(day_good_points) AS sum_day_good_points, ";
		$sql .= " SUM(day_bad) AS sum_day_bad, SUM(day_bad_points) AS sum_day_bad_points FROM external_tickets_cache_tmp ";
		$sql .= " WHERE year = "  . $year_selected;
		$sql .= " AND month = "   . $month_selected;
		$sql .= " AND user_id = " . $user_id;
		$db->query($sql,__FILE__,__LINE__);
		if ($db->next_record()) {
			$users[$user_id]["tickets"]                 = $db->f("sum_day_good");
			$users[$user_id]["tickets_points"]          = $db->f("sum_day_good_points");
			$users[$user_id]["tickets_delayed"]         = $db->f("sum_day_bad");
			$users[$user_id]["tickets_delayed_points"]  = $db->f("sum_day_bad_points");
		}
	}
	
	function special_for_user_35 (&$users, $year_selected, $month_selected) {
		global $db;
		
		if ($users[35]["used_ids"]) {
			$users[35]["used_ids"] .= ",";
		}
		$users[35]["used_ids"] .= "18762,18832,6365,4406";
		
		$points = 0;
		
		$sql  = " SELECT COUNT(report_id) FROM time_report ";
		$sql .= " WHERE user_id=35 AND task_id=18762";
		$sql .= " AND YEAR(report_date) = " . $year_selected ;
		$sql .= " AND MONTH(report_date) = " . $month_selected;
		$db->query($sql,__FILE__,__LINE__);
		if ($db->next_record()) {
			$points += $db->f(0) * 10;
		}
		
		$sql  = " SELECT COUNT(report_id) FROM time_report ";
		$sql .= " WHERE user_id=35 AND task_id=18832";
		$sql .= " AND YEAR(report_date) = " . $year_selected ;
		$sql .= " AND MONTH(report_date) = " . $month_selected;
		$db->query($sql,__FILE__,__LINE__);
		if ($db->next_record()) {
			$points += $db->f(0) * 15;
		}
		
		$users[35]["manual_hours"] = 0;
		$sql  = " SELECT SUM(tr.spent_hours) AS tasks_spent_hours ";
		$sql .= " FROM ( tasks t ";
		$sql .= " INNER JOIN time_report tr ON tr.task_id=t.task_id) ";
		$sql .= " WHERE tr.user_id=35";
		$sql .= " AND YEAR(tr.report_date) = " . $year_selected ;
		$sql .= " AND MONTH(tr.report_date) = " . $month_selected;
		$sql .= " AND t.task_id=6365 ";
		$db->query($sql,__FILE__,__LINE__);
		if ($db->next_record()) {
			$users[35]["manual_hours"] += $db->f(0);
		}
		
		$sql  = " SELECT SUM(tr.spent_hours) AS tasks_spent_hours ";
		$sql .= " FROM ( tasks t ";
		$sql .= " INNER JOIN time_report tr ON tr.task_id=t.task_id) ";
		$sql .= " WHERE tr.user_id=35";
		$sql .= " AND YEAR(tr.report_date) = " . $year_selected ;
		$sql .= " AND MONTH(tr.report_date) = " . $month_selected;
		$sql .= " AND t.task_id=4406 ";
		$db->query($sql,__FILE__,__LINE__);
		if ($db->next_record()) {
			$users[35]["manual_hours"] += 2*$db->f(0);
		}
		
		
		return $points;
		
	}
?>