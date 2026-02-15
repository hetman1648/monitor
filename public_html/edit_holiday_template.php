<?php

include ("./includes/common.php");
include ("./includes/date_functions.php");

if (getsessionparam("privilege_id") == 9) {
	header("Location: index.php");
	exit;
}

// SECOND DATABASE OBJECT
$db2 = new DB_Sql;
$db2->Database = DATABASE_NAME;
$db2->User     = DATABASE_USER;
$db2->Password = DATABASE_PASSWORD;
$db2->Host     = DATABASE_HOST;

global $is_paid;

$T = new iTemplate("./templates", array("page"=>"edit_holiday_template.html"));

CheckSecurity(1);

$user_id_creator = getsessionparam("UserID");
$add = GetParam("add");
if ($add)
{
 // echo "for add";
  $T->set_var("header_holiday", "Add Holiday");
 //	$T->parse("add_new_button");
  $T->set_var("delete_button", "");
 // $T->set_var("edit_button", "");
   		$T->set_var("edit_add_button","Add");
  		$T->parse("edit_button");
  $T->set_var("holiday_title", "");
  $T->set_var("holiday_date", "");
  $T->parse("holiday");
  $T->set_var("holiday_error","");
}
else 
{
  	
 	$holiday_id = GetParam("holiday_id");
 	$T->set_var("holiday_id", $holiday_id);
 	$sql = "SELECT * FROM holiday_template WHERE holiday_id='$holiday_id'";
 	$db->query($sql);    
  	if ($db->next_record())
  	{
	    //echo "for edit yes";
		$T->set_var("holiday_title", $db->Record["holiday_title"] );
  		$T->set_var("holiday_date", $db->Record["holiday_date"]);
  		$T->parse("holiday");
  		$T->set_var("holiday_error","");
  		$T->set_var("header_holiday", "Edit Holiday"); 
	 	//$T->set_var("add_new_button","");
  		$T->parse("delete_button");
  		$T->set_var("edit_add_button","Edit");
  		$T->parse("edit_button");
	}
	else{
	  //	echo "for edit no";
	  	$T->set_var("holiday","");
		$T->parse("holiday_error");
	}
}

if ($_POST["action"] == "Cancel") {
  header("Location: holidays_templates.php");
}

if (($_POST["action"] == "Delete")) {
  $sql = "DELETE FROM holiday_template WHERE holiday_id = ".$_POST["holiday_id"];
  $db->query($sql);
  header("Location: holidays_templates.php");
}

if (($_POST["action"] == "Edit")) {
  
  // GET user_id
  $sql = "UPDATE holiday_template SET holiday_title = '".$_POST["holiday_title"]
  ."', holiday_date = '".$_POST["holiday_date"]
  ."' WHERE holiday_id = ".$_POST["holiday_id"];
  $db->query($sql);
  header("Location: holidays_templates.php");  
}

if (($_POST["action"] == "Add")) {
  //$sql = "SELECT MAX(holiday_id) AS max_holiday_id FROM national_holidays";
  //$db->query($sql);
  //$db->next_record();
  //$hol_id = $db->Record["max_holiday_id"] + 1;
  $hol_t = $_POST["holiday_title"];
  $hol_d = $_POST["holiday_date"];
  $sql = "INSERT INTO holiday_template (holiday_id, holiday_title, holiday_date) VALUES ('NULL',".ToSQL($hol_t, STRING).",'$hol_d')";
  $db->query($sql);
  header("Location: holidays_templates.php");  
}

$T->pparse("page");

?>