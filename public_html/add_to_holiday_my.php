<?php

include ("./includes/common.php");
include ("./includes/date_functions.php");


if (getsessionparam("privilege_id") == 9) {
	header("Location: index.php");
	exit;
}

global $is_paid;

$T = new iTemplate("./templates", array("page"=>"add_to_holiday.html"));

CheckSecurity(1);

$manager_added_id = getsessionparam("UserID");

$add	= GetParam("add");
$action = GetParam("action");
$user_id= GetParam("user_id");
$days	= GetParam("days");
$notes	= GetParam("notes");
$holiday_id		= GetParam("holiday_id");
$holiday_title	= GetParam("holiday_title");
$holiday_date	= GetParam("holiday_date");
//if ($add)
//{
 // echo "for add";
$T->set_var("header_holiday", "Add Holiday");
//	$T->parse("add_new_button");
$T->set_var("delete_button", "");
// $T->set_var("edit_button", "");
$T->set_var("edit_add_button","Add");
$T->parse("edit_button");
$T->set_var("days", "");
$T->set_var("notes", "");
$T->parse("holiday");
$T->set_var("holiday_error","");
$T->set_var("people_list",    get_options("users WHERE is_deleted IS NULL AND is_viart=1 ORDER BY user_name","user_id","concat(first_name,' ',last_name) as user_name",$mes_user_id=0,"person"));

if ($action == "Cancel") {
	header("Location: holidays.php");
} elseif ($action == "Delete") {
	$sql = "DELETE FROM national_holidays WHERE holiday_id = ".ToSQL($holiday_id,"integer");
	$db->query($sql);
	header("Location: holidays.php");
} elseif ($action == "Edit") {
	// GET user_id
	$sql = "UPDATE national_holidays
			SET	holiday_title = ".ToSQL($holiday_title,"string").",
				holiday_date = ".ToSQL($holiday_date,"date")."
			WHERE holiday_id = ".ToSQL($holiday_id,"integer");
	$db->query($sql);
	header("Location: holidays.php");
} elseif ($action == "Add") {
	$date_added = date('Y-m-d');
	$sql = "INSERT INTO holidays (user_id, days_number, date_added, notes, manager_added_id)
			VALUES (".ToSQL($user_id,"integer").",
					".ToSQL($days,"string").",
					".ToSQL($date_added,"date").",
					".ToSQL($notes,"string").",
					".ToSQL($manager_added_id,"integer").")";
	$db->query($sql);
	header("Location: holidays.php");
}

$T->pparse("page");

?>