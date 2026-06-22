<?php

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");



$user_id      = GetParam("user_id");
$sorted_ids   = GetParam("sorted_ids");//parameter specifies if all available tasks are returned
//sometimes javascript IDs contain extra characters - we remove them to get user_id
$user_id = preg_replace("/[^0-9]/","",$user_id);

if ($user_id && is_numeric($user_id)) {
	/*$sql = "UPDATE tasks SET priority_id=0 ";
	$sql.= " WHERE responsible_user_id=".$user_id. " AND is_closed=0 AND task_type_id!=3";
	$sql.= " ORDER BY priority_id";
	$db->query($sql);*/

	$ids = explode("&", $sorted_ids);
	$priority = 1;
	foreach ($ids as $id) {
		$id = str_replace("sort=", "", $id);
		// echo "id:$id\n";
		if (is_numeric($id)) {
			$sql = "UPDATE tasks SET priority_id=$priority WHERE task_id=$id";
			// echo $sql;
			$db->query($sql);
		}
		$priority++;
	}
}
?>