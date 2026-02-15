<?php
	chdir("../");	
	include("./includes/common.php");
	include("./includes/productivity_functions.php");
	
	CheckSecurity(1);
	
	$t = new iTemplate($sAppPath);
	$t->set_file("main","block_productivity_viart.html");
	$t->set_var("team_title", "Summary for Sayu Web Developers Team");
	
	$db2 = new DB_Sql();
	$db2->Database = DATABASE_NAME;
	$db2->User     = DATABASE_USER;
	$db2->Password = DATABASE_PASSWORD;
	$db2->Host     = DATABASE_HOST;
	
	calculate_all(
		array(84, 34, 139), // team
		1500, // user thres
		0.2,  // user coeff
		3000, // team thres
		0.075,// team coeff
		1,    // custom work
		10,   // viart
		-1,   // sayu
		0,    // manuals
		array(20, 10, 0, -20), // day points
		80,   // vacations
		5    //bugs
	);
		
	$t->pparse("main");
?>
