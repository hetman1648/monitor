<?php

include ("./includes/common.php");
include ("./includes/date_functions.php");

$db2 = new DB_Sql;
$db2->Database = DATABASE_NAME;
$db2->User     = DATABASE_USER;
$db2->Password = DATABASE_PASSWORD;
$db2->Host     = DATABASE_HOST;

if (getsessionparam("privilege_id") == 9) {
	header("Location: index.php");
	exit;
}

$T = new iTemplate("./templates", array("page"=>"holidays_templates.html"));

$sql = "SELECT * FROM holiday_template";
$db->query($sql);
if ($db->next_record())
{
  do 
  {
    
	$holiday_id = $db->Record["holiday_id"];
	$T->set_var("holiday_id", $holiday_id);
    $T->set_var("holiday_title", $db->Record["holiday_title"]);
    //echo $db->Record["holiday_date"]."<br>";
    $h_d = $db->Record["holiday_date"];
    $this_year = date('Y');
    $arr_h_d = explode("-",$h_d);
    if ($arr_h_d[1]!=0 && $arr_h_d[2]!=0){
    $holiday_date = date("jS F", mktime(0,0,0,$arr_h_d[1],$arr_h_d[2],0));
    //echo $holiday_date;
    //$T->set_var("holiday_date", $db->Record["holiday_date"]);
  	}
	else {
	  $holiday_date = "-";
	}
	$T->set_var("holiday_date", $holiday_date);
	    
	$T->parse("holiday_rows", true);
	}
	while ($db->next_record()); 
	$T->parse ("holiday_header");
	$T->set_var("holiday_error","");
	
} 
else 
{
 $T->set_var("holiday_rows", "");
 $T->set_var("holiday_header", ""); 
 $T->parse("holiday_error");
}

$T->pparse("page", false);

?>