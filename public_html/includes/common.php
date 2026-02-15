<?php

	if (!isset($root_inc_path)) $root_inc_path= "./";
	include_once($root_inc_path."includes/template.php");
	include_once($root_inc_path."db_mysql.inc");
	include_once($root_inc_path."includes/db_connect.php");
	include_once($root_inc_path."includes/common_functions.php");
	include_once($root_inc_path."includes/tasks_functions.php");
	
	$sAppPath = "./templates";

	$intranet = 0;
	$CRLF = $intranet ? "\r\n" : "\n";

	// privileges
	define("PRIV_DEVELOPER", 1);
	define("PRIV_TESTER", 2);
	define("PRIV_ARCHITECT", 3);
	define("PRIV_PM", 4);
	define("PRIV_PARTNER", 5);
	define("PRIV_ADMIN", 6);
	define("PRIV_CUSTOMER", 9);

	// message types
	define("MSG_PROJECT_CREATED", 1);
	define("MSG_TASK_CREATED", 2);
	define("MSG_TASK_COMPLETED", 3);
	define("MSG_TASK_UPDATED", 4);
	define("MSG_MESSAGE_RECEIVED", 5);
	
	//status
	define("STATUS_IN_PROGRESS", 1);
	define("STATUS_ON_HOLD", 2);
	define("STATUS_REJECTED", 3);
	define("STATUS_DONE", 4);
	define("STATUS_QUESTION", 5);
	define("STATUS_ANSWER", 6);
	define("STATUS_NOT_STARTED", 7);
	define("STATUS_WAITING", 8);
	define("STATUS_REASSIGNED", 9);
	define("STATUS_FOUND_BUG", 10);
	define("STATUS_TESTED", 11);
	define("STATUS_BUG_RESOLVED", 12);	
	define("STATUS_DOCUMENTED", 13);
	define("STATUS_READY_TO_DOCUMENT", 14);	

	// Database
	$db = new DB_Sql();
	$db->Database = DATABASE_NAME;
	$db->User     = DATABASE_USER;
	$db->Password = DATABASE_PASSWORD;
	$db->Host     = DATABASE_HOST;

	$month = array(
		"01"=>"January",
		"02"=>"February",
		"03"=>"March",
		"04"=>"April",
		"05"=>"May",
		"06"=>"June",
		"07"=>"July",
		"08"=>"August",
		"09"=>"September",
		"10"=>"October",
		"11"=>"November",
		"12"=>"December"
	);
	
	$level_colors=array(
		"0" => "red",
		"1" => "blue",
		"2" => "black",
		"3" => "navy",
		"4" => "grey",
		"5" => "red",
		"6" => "green",
		"7" => "blue",
		"8" => "black"
	);
	
	// permissions
	$perms = array(	"PERM_USER_PROFILE" => "Edit Users Profiles",
		"PERM_CLOSE_TASKS"  => "Close Tasks",
		"PERM_VIEW_PROJECT_TASKS" => "View Project Tasks",
		"PERM_VIEW_ALL_TASKS" => "View All Tasks",
		"PERM_LOGIN_INTERNAL_MONITOR" => "Login in internal monitor"
	);
	
	$statuses_classes = array("","InProgress","OnHold","Rejected","Done","Question","Answer","New","Waiting","Reassigned","Bug","Deadline", "BugResolved", "Documented", "ReadyToDocument", "New", "New", "New", "New", "New", "New", "New", "New", "New", "New", "New");
	
	$permission_groups = array('ALL'=>"All", 'MANAGERS'=>"Managers", 'TEAM'=>"Team", 'OFFICE'=>"Office", 'OWNER'=>"Owner");

	$doc_path = "./documents/";
		
	ini_set('session.gc_maxlifetime', '21600');
	session_start();	 
	
?>