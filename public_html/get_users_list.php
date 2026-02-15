<?php
include_once ("includes/common.php");

if (!isset($_GET["Yu"])) exit;

$users_arr = array();
$sql = "SELECT user_id, email, first_name, last_name, is_viart, is_deleted, manager_id FROM users";
$db->query($sql);
while ($db->next_record()) {
	$user = array();
	$user['user_id'] = $db->f('user_id');
	$user['email'] = $db->f('email');
	$user['first_name'] = $db->f('first_name');
	$user['last_name'] = $db->f('last_name');
	$user['is_viart'] = $db->f('is_viart');
	$user['is_deleted'] = $db->f('is_deleted');
	$user['manager_id'] = $db->f('manager_id');
	$users_arr[] = $user;
}

$users_teams_arr = array();
$sql = "SELECT team_id, team_name, manager_id FROM users_teams";
$db->query($sql);
while ($db->next_record()) {
	$team = array();
	$team['team_id'] = $db->f('team_id');
	$team['team_name'] = $db->f('team_name');
	$team['manager_id'] = $db->f('manager_id');
	$users_teams_arr[] = $team;
}
//print_r(array($users_arr, $users_teams_arr));
echo serialize(array($users_arr, $users_teams_arr));

?>