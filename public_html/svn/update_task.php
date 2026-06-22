<?php

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");

$task_id = GetParam("task_id");
//sometimes javascript IDs contain extra characters (like "est","task"..)
//- we remove them to get task_id
$task_id = preg_replace("/[^0-9]/","",$task_id);

$xml_content = "";
$operation = GetParam("operation");
if ($task_id && is_numeric($task_id)) {
	$sql = "SELECT task_title,responsible_user_id FROM tasks WHERE task_id=".$task_id;
	$db->query($sql);
	$task_title = ""; $responsible_user_id = 0;
	if ($db->next_record()) {
		$task_title          = $db->f("task_title");
		$responsible_user_id = $db->f("responsible_user_id");
	}


	$fields = array("task_title","completion","estimated_hours","actual_hours","task_status_id");
	foreach ($fields as $field_name) {
		$fields[$field_name] = GetParam($field_name);
	}
	if ($operation == "save_estimate") {
		$estimated_hours = $fields["estimated_hours"];
		$estimated_hours = strToHours(trim($estimated_hours))."<br>";
		
		$sql = "UPDATE tasks SET estimated_hours=".number_format($estimated_hours,2);
		$sql.= " WHERE task_id= ".$task_id;
		
		$db->query($sql);
		echo "The estimated duration for the task '". $task_title."' has been updated to <b>".getHoursFormat($estimated_hours)."</b>";
	}


	if ($operation == "save_status") {
		$status     = $fields["task_status_id"];
		$completion = $fields["completion"];
		
		$sql = "UPDATE tasks SET task_status_id=".number_format($status,0);
		$sql.= " ,completion=".number_format($completion,0);
		$sql.= " WHERE task_id= ".$task_id;
		$db->query($sql);

		$fields = array("message_date"   => "NOW()",
				   "user_id"             => GetSessionParam("UserID"),
				   "responsible_user_id" => $responsible_user_id,
				   "status_id"			 => number_format($status,0),
				   "identity_id"         => $task_id
		);

		$sql = " INSERT INTO messages (" .join(",",array_keys($fields)) .") ";
		$sql.= " VALUES (".join(",",array_values($fields)).")";
		
		$db->query($sql);
		echo "The status/completion for the task '". $task_title."' has been updated ";
	}

	if ($operation == "link_tasks") {
		$dependent_task = GetParam("dependent_task");

		//unlink task if dependent_task is not provided
		if (!strlen($dependent_task)) {
			$sql = "SELECT dependent_id FROM tasks ";
			$sql.= " WHERE task_id=".number_format($task_id,0,"","");
			$db->query($sql);
			if ($db->next_record()) {
				$old_dependent_id = $db->f("dependent_id");

				$sql = "UPDATE tasks SET dependent_id=0 ";
				$sql.= " WHERE task_id=".number_format($task_id,0,"","");
				$db->query($sql);
				if ($old_dependent_id) echo "Task '$task_title' is UNLINKED";
			} else echo "ERROR: task $task_title is not found "; 
			exit;
		}

		//we need to find the task_id for provided text input
		// it could be in format: # Task Name (Task ID)
		//1. we try to find numeric value in brackets (Task ID)
		$pattern = "/(.*)\((\d+)\)(.*)/";
		$matches = array();
		$dependent_id   = 0;
		$depenent_found = false;
		preg_match_all($pattern, $dependent_task, $matches);
		if (isset($matches[2]) && is_array($matches[2])) {
			$dependent_id = $matches[2][0];
		}
		if ($dependent_id) {
			$sql = "SELECT task_id FROM tasks WHERE task_id=". number_format($dependent_id,0,"","");
			$db->query($sql);
			$depenent_found  = $db->next_record();
		}

		//2. if not found and the whole value is numeric then it could be 
		// either # or Task ID
		if (!$depenent_found && is_numeric($dependent_task)) {
			$sql = "SELECT task_id FROM tasks WHERE task_id=". number_format($dependent_id,0,"", "");
			$db->query($sql);
			if ($db->next_record()) {
				$depenent_found = true;
				$dependent_id   = $db->f("task_id");
			}
		}

		//3. it could be a task title - so we try to match using the task title
		if (!$depenent_found) {
			$sql = "SELECT task_id FROM tasks WHERE task_title='". addslashes($dependent_task) . "'";
			$db->query($sql);
			if ($db->next_record()) {
				$depenent_found = true;
				$dependent_id   = $db->f("task_id");
			}	
		}
		if ($depenent_found && $dependent_id) {
			if ($dependent_id != $task_id) {
				$sql = "UPDATE tasks SET dependent_id=".number_format($dependent_id,0,"","");
				$sql.= " WHERE task_id=".number_format($task_id,0,"","");
				$db->query($sql);
				echo "Task '$task_title' is linked with '$dependent_task'";
			} else echo "Error: can't link task to itself";
		} else echo "Error: can't find the task '$dependent_task'";

		//echo "dependent_id:$dependent_id <br>";
		//echo "depenent_found: $depenent_found";
	}
}
?>