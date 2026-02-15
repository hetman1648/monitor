<?php
	include("./includes/common.php");
	include("./includes/date_functions.php");
	
	$user_id = GetParam("user_id");
	$sort = GetParam("sort");
	
	$T = new iTemplate("./templates",array("page"=>"index_tasks_list.xml"));
		
	show_users_list("viart_reports","spotlight_reports", "viart_team_count", "yoonoo_team_count");
	show_manager_notes("manager_notes", $user_id);
	show_lunches_block("lunch_message", $user_id);
	show_active_bugs("active_bugs_message", $user_id);

	$is_manager = is_manager(ToSQL($user_id, "Number"));
  	$is_working = false;
  	GetTasksList($user_id, $is_manager, $sort, $csv, $is_working);	
  	$T->set_var("user_name",GetSessionParam("UserName"));	
	$T->set_var("user_id", $user_id);
	
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); 
	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); 
	header("Cache-Control: no-store, no-cache, must-revalidate"); 
	header("Cache-Control: post-check=0, pre-check=0", false); 
	header("Pragma: no-cache");	
	$T->pparse("page");
?>