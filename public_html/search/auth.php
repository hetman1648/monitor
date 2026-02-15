<?
/*
include auth file for common 
*/

date_default_timezone_set("Europe/London");

$user_id = GetSessionParam("UserID");
if($user_id == "") {
	header("Location:../login.php");
	exit;
}

?>