<?php

	include("./includes/date_functions.php");
	include("./includes/common.php");

	CheckSecurity(1);

	$year_selected		= GetParam("year_selected");
	$month_selected		= GetParam("month_selected");
	if (!$year_selected)  $year_selected = date("Y");
	if (!$month_selected) $month_selected = date("m");
	
	$t = new iTemplate($sAppPath);
	$t->set_file("main", "productivity_report.html");
	
	$t->set_var("months", GetMonthOptions($month_selected));
	$t->set_var("years", GetYearOptions(2004, date("Y"), $year_selected));
	$t->set_var("month_selected", $month_selected);
	$t->set_var("year_selected", $year_selected);
			
	$t->pparse("main");
?>