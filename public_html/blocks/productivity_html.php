<?php
	chdir("../");	
	include("./includes/common.php");
	include("./includes/productivity_functions.php");
	
	CheckSecurity(1);
	
	$t = new iTemplate($sAppPath);
	$t->set_file("main","block_productivity_viart.html");
	$t->set_var("team_title", "Summary for HTML Design Team");
	
	$db2 = new DB_Sql();
	$db2->Database = DATABASE_NAME;
	$db2->User     = DATABASE_USER;
	$db2->Password = DATABASE_PASSWORD;
	$db2->Host     = DATABASE_HOST;
	
	calculate_all(
		array(24, 94), // team
		1500, // user thres
		0.2,  // user coeff
		0,    // team thres
		0.2,  // team coeff
		1,    // custom work
		10,   // viart
		-1,   // sayu
		0,    // manuals
		array(20, 10, 0, -20), // day points
		80, // vacations
		5, //bugs
		false, 
		1, 
		false //debug
	);
		
	$t->pparse("main");

	function special_team_bonus(&$users, $manager_id) {
		$manager_bonus = 0;
		foreach ($users AS $user) {
			$manager_bonus += ($user["custome_work"] + $user["viart"] + $user["sayu"] + $user["tickets_points"] + $user["tickets_delayed_points"]) * 0.1;
		
		}
		$manager_bonus = round($manager_bonus * 0.2, 2);
		return $manager_bonus;
	}
?>