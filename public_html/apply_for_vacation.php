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

$user_id = GetSessionParam("UserID");

$T = new iTemplate("./templates", array("page"=>"apply_for_vacation.html"));
$type_of = GetParam("for");
if ($type_of == 1) 
{
  $T->set_var("header", "Apply for Holiday");
  	$is_paid = 0;
  	$T->set_var("for", $type_of);
} 

else if ($type_of == 2) 
{
  $T->set_var("header", "Apply for Overwork");
  	$is_paid = 1;
  	$T->set_var("for", $type_of);
} 

else { header("Location: index.php"); }

CheckSecurity(1);

if ($_POST["action"] == "Cancel") {
  header("Location: view_vacations.php");
}

if (($_POST["action"] == "Delete Vacation")) {
  //echo $_POST["period_id"];
  $sql = "DELETE FROM days_off WHERE period_id = ".$_POST["period_id"];
  $db->query($sql);
  header("Location: view_vacations.php");
}

if (($_POST["action"] == "Edit Vacation") || ($_POST["action"] == "Edit Paid Period")) {
  
  // GET user_id
 /* $sql = "SELECT first_name, last_name, user_id FROM users";
  $db->query($sql);    
  while ($db->next_record()) {
    if ($db->Record["first_name"]." ".$db->Record["last_name"] == $_POST["person_select"]) $user_id = $db->Record["user_id"];
  } 
 */
  // GET reason_id
  $sql = "SELECT reason_id, reason_name FROM reasons";
  $db->query($sql);  
  while ($db->next_record()) {
    if ($db->Record["reason_name"] == $_POST["reason_type_select"]) $reason_id = $db->Record["reason_id"];
   }
   
   if ($is_paid == 1) $reason_id = 1;
   
     
  $sql = "UPDATE days_off SET user_id = ".$user_id
  .", period_title = '".$_POST["vacation_title"]
  ."', notes = '".$_POST["notes"]
  ."', reason_id = ".$reason_id
  .", start_date = '".$_POST["start_date"]
  ."', end_date = '".$_POST["end_date"]
  ."', total_days = ".$_POST["total_days"]      
  ." WHERE period_id = ".$_POST["period_id"];
  $db->query($sql);
  header("Location: view_vacations.php");  
}

if ($_POST["action"] == "Apply"){
    
  // GET period_id
  $sql = "SELECT MAX(period_id) AS period_id FROM days_off";
  $db->query($sql);
  $db->next_record();
  $period_id = $db->Record["period_id"] + 1;
  
  // GET user_id
  $sql = "SELECT first_name, last_name, user_id FROM users WHERE is_deleted IS NULL ";
  $db->query($sql);    
  while ($db->next_record()) {
    if ($db->Record["first_name"]." ".$db->Record["last_name"] == $_POST["person_select"]) $user_id = $db->Record["user_id"];
  } 
   
  // GET reason_id
  $sql = "SELECT reason_id, reason_name FROM reasons";
  $db->query($sql);  
  while ($db->next_record()) {
    if ($db->Record["reason_name"] == $_POST["reason_type_select"]) $reason_id = $db->Record["reason_id"];
   } 
   
  if ($is_paid == 1) $reason_id = 1;
   
  if (!$_POST["total_days"]) $_POST["total_days"] = 0;
  
  $sql = "INSERT INTO days_off VALUES ("
  .$period_id
  .", ".$user_id
  .", '".$_POST["vacation_title"]
  ."', '".$_POST["notes"]
  ."', ".$reason_id
  .", '".$_POST["start_date"]
  ."', '".$_POST["end_date"]
  ."', ".$_POST["total_days"]
  .", ".$is_paid
  .", 0, 0"
  .")";  
  $db->query($sql);
  header("Location: view_vacations.php");
} 

if (isset($vacation_id)) {
  $b_text = "Delete Vacation";
  $e_text = "Edit Vacation";

  $sql = "SELECT * FROM days_off WHERE period_id =".$vacation_id;
  $db->query($sql);
  $db->next_record();  
  if ($db->Record["is_paid"] == 1) {
    $b_text = "Delete Paid Period";
    $e_text = "Edit Paid Period";    
  }
  
  $is_paid = $db->Record["is_paid"];
  
  $period_t = "Vacation";
  if ($db->Record["is_paid"] == 1) $period_t = "Paid Period";
  $T->set_var("period_t", $period_t);
    
  $T->set_var("action_value", $e_text);
  $T->set_var("title_o", $e_text);
  $T->set_var("delete_button", "<input type=\"submit\" name=\"action\" value=\"".$b_text."\">");
  //$T->set_var("delete_button", $b_text);
  $T->set_var("period_id", $vacation_id);  
  $sql = "SELECT * FROM days_off WHERE period_id =".$vacation_id;
  $db->query($sql);
  $db->next_record();
  $T->set_var("start_date", $db->Record["start_date"]);
  $T->set_var("end_date", $db->Record["end_date"]);  
  $T->set_var("total_days", $db->Record["total_days"]);  
  $T->set_var("notes", $db->Record["notes"]);    
  $T->set_var("vacation_title", $db->Record["period_title"]);
	$sql2 = "SELECT reason_name, reason_id FROM reasons";
	$db2->query($sql2);  
  if ($is_paid == 0) {
   while ($db2->next_record()) {
    if ($db2->Record["reason_id"] == $db->Record["reason_id"]) $reason_type_option .= "<option selected>".$db2->Record["reason_name"]."</option><br>";
    else $reason_type_option .= "<option>".$db2->Record["reason_name"]."</option><br>";
   }
  } else $reason_type_option = "<option selected>Paid Period</option>";
    
  $T->set_var("reason_type_option", $reason_type_option);  
  $sql2 = "SELECT first_name, last_name, user_id FROM users WHERE is_viart = 1 AND is_deleted IS NULL ";
  $db2->query($sql2);    
  while ($db2->next_record()) {
    if ($db2->Record["user_id"] == $db->Record["user_id"]) $person_option .= "<option selected>".$db2->Record["first_name"]." ".$db2->Record["last_name"]."</option><br>";
    else $person_option .= "<option>".$db2->Record["first_name"]." ".$db2->Record["last_name"]."</option><br>";
   }
  $T->set_var("person_option", $person_option);    
  $T->set_var("is_paid", $is_paid);
}

else {
  
  $sql = "SELECT first_name, last_name FROM users WHERE is_viart = 1 AND is_deleted IS NULL ";
  $db->query($sql);  
  
  while ($db->next_record()) {
    $person_option .= "<option>".$db->Record["first_name"]." ".$db->Record["last_name"]."</option><br>";
   }
   
  $T->set_var("person_option", $person_option);  
  $T->set_var("vacation_title", "");
  $T->set_var("notes", "");
  $sql = "SELECT reason_name FROM reasons";
  $db->query($sql);  
  
  if ($is_paid == 0) { while ($db->next_record()) {
    $reason_type_option .= "<option>".$db->Record["reason_name"]."</option><br>";
   } } else $reason_type_option = "<option selected>Paid Period</option>";
  $T->set_var("start_date", "");
  $T->set_var("end_date", "");
  $period_t = "Vacation";
  if ($is_paid == 1) $period_t = "Paid Period";
  $T->set_var("period_t", $period_t);    
  $T->set_var("reason_type_option", $reason_type_option);  
  $T->set_var("total_days", "");
  $T->set_var("delete_button", "");
  //if ($is_paid == 0) 
  $T->set_var("action_value", "Apply");
  //else $T->set_var("action_value", "Add Paid Period");
  $T->set_var("is_paid", $is_paid);  
  $e_text = "Create Vacation";
  if ($is_paid == 1) $e_text = "Create Paid Period";
  $T->set_var("title_o", $e_text);
}

$T->pparse("page", false);

?>