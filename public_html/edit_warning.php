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

$T = new iTemplate("./templates", array("page"=>"edit_warning.html"));

CheckSecurity(2);

$user_id_creator = getsessionparam("UserID");

if ($_POST["action"] == "Cancel") {
  header("Location: view_warnings.php");
}

if (($_POST["action"] == "Delete")) {
  $sql = "DELETE FROM warnings WHERE warning_id = ".$_POST["warning_id"];
  $db->query($sql);
  header("Location: view_warnings.php");
}

if (($_POST["action"] == "Edit")) {
  
  // GET user_id
  $sql = "SELECT first_name, last_name, user_id FROM users";
  $db->query($sql);    
  while ($db->next_record()) {
    if ($db->Record["first_name"]." ".$db->Record["last_name"] == $_POST["person_warning"]) $user_id = $db->Record["user_id"];
  } 
    //echo "date".$_POST["start_date"];  
  $sql = "UPDATE warnings SET user_id = ".$user_id
  .", description = '".$_POST["notes"]
  ."', date_added = '".$_POST["start_date"]
  ."', admin_user_id = ".$user_id_creator
  ." WHERE warning_id = ".$_POST["warning_id"];
  $db->query($sql);
  header("Location: view_warnings.php");  
}

$warning_id = GetParam("warning_id");
//if (isset($vacation_id)) {
  //echo "222";
  $b_text = "Delete";
  $e_text = "Edit";

  $sql = "SELECT * FROM warnings WHERE warning_id =".$warning_id;
  $db->query($sql);
	if ($db->next_record())  {
	$T->parse("edit_warnings");
	$T->set_var("no_edit_warnings", "");  
 	$T->set_var("warning_id", $warning_id);
 	
	$notes = $db->f("description");
 	$T->set_var("notes", $notes);
 	
 	$date_added = $db->f("date_added"); 
 	$T->set_var("start_date", $date_added);
 	
  	$sql2 = "SELECT first_name, last_name, user_id FROM users WHERE is_viart = 1 AND is_deleted IS NULL ";
  	$db2->query($sql2);    
  	while ($db2->next_record()) {
    	if ($db2->Record["user_id"] == $db->Record["user_id"]) $person_option .= "<option selected>".$db2->Record["first_name"]." ".$db2->Record["last_name"]."</option><br>";
    		else $person_option .= "<option>".$db2->Record["first_name"]." ".$db2->Record["last_name"]."</option><br>";
   		}
  $T->set_var("person_option", $person_option);   
}

else {
  	$T->set_var("edit_warnings", "");
	$T->parse("no_edit_warnings");  
}

$T->pparse("page", false);

?>