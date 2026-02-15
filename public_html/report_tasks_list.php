<?php
	include("./includes/common.php");
	include("./includes/date_functions.php");
	
	$rp = GetParam("rp");
	$report_user_id	= GetParam("report_user_id");
	$is_viart = GetParam("is_viart");
	$T = new iTemplate("./templates",array("page"=>"report_tasks_list.xml"));
	show_report_tasks_list($report_user_id, $is_viart, $rp);
  	$T->pparse("page");
?>