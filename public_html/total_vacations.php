<?php

include ("./includes/common.php");
include ("./includes/date_functions.php");

if (getsessionparam("privilege_id") == 9) {
	header("Location: index.php");
	exit;
}

$T = new iTemplate("./templates", array("page"=>"total_vacations.html"));
CheckSecurity(1);

$user_id = GetParam("user_id");

// USER STATS PARSE
//begin holidays summary
$today = getdate();
$today_year = $today["year"];
$nowday = date('Y-m-d');
$sql  = "SELECT CONCAT(first_name,'  ', last_name) AS user_name FROM users WHERE user_id = ".ToSQL($user_id, INTEGER);
$db->query($sql);
$db->next_record();
$T->set_var("user_name", $db->f("user_name"));

$sql = "SELECT date_added, days_number FROM holidays WHERE user_id = ".ToSQL($user_id, INTEGER);
$db->query($sql);
  if ($db->next_record())
  {
	  
		do
		{
			$T->set_var("date_added", $db->f("date_added"));
	  	$T->set_var("days_number", $db->f("days_number"));
	  	$T->parse("total_holidays", true);
		}	
		while ($db->next_record());
	}
	else
	{
		$T->set_var("date_added", "");
	  $T->set_var("days_number", "");	  
	}
//end holidays summary

// MAIN OUTPUT
$T->pparse("page", false);

?>