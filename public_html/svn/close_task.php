<?php

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");

$task_id = GetParam("task_id");
$task_id = str_replace("tsk"  , "", $task_id);
$task_id = str_replace("Close", "", $task_id);

if ($task_id && is_numeric($task_id)) {
	$sql = "UPDATE tasks SET is_closed=1 WHERE task_id=".$task_id;
	$db->query($sql); 

	$sql = "SELECT task_title FROM tasks WHERE task_id=".$task_id;
	$db->query($sql);
	$task_title = "";
	if ($db->next_record()) {
		$task_title = $db->f("task_title");
	}
}
?>
Task <b><?php echo $task_title; ?></b> has been closed.