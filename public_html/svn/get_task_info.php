<?php

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");



$task_id      = GetParam("task_id");
$is_all_tasks = GetParam("all_tasks");//parameter specifies if all available tasks are returned
//sometimes javascript IDs contain extra characters - we remove them to get task_id
$task_id = preg_replace("/[^0-9]/","",$task_id);
$xml_content = "";

if ($task_id && is_numeric($task_id)) {
	$fields = array("task_title"      => "",
		  			"task_status_id"  => "", 
		  			"completion"      => "",
		  			"estimated_hours" => "",
		  			"actual_hours"    => "",
		  			"dependent_id"    => ""
		  			);


	$sql = "SELECT ".join(",",array_keys($fields))." FROM tasks WHERE task_id=".$task_id;
	$db->query($sql); 


	if ($db->next_record()) {
		foreach ($fields as $field_name => $field_value) {
			# code...
			$field_value = $db->f($field_name);
			if ($field_name == "estimated_hours") $field_value = getHoursFormat($field_value);
			$fields[$field_name] = $field_value;
		}

		$sql = "SELECT * FROM lookup_tasks_statuses WHERE popularity>0 ORDER BY popularity DESC";
		$tasks_statuses = array();
		$db->query($sql); 
		while ($db->next_record()) {
		    $tasks_statuses[$db->f("status_id")] = $db->f("status_caption");
		}
		$fields["all_statuses"] = json_encode($tasks_statuses);

		if ($is_all_tasks) {
			//1. getting projects list for Sayu Web clients parent_id=79
			$projects = array();
			$sql = "SELECT project_id FROM projects WHERE is_closed=0 and parent_project_id=79 ORDER BY project_title";
			$db->query($sql);
			while ($db->next_record()) {
			    $projects[] = $db->f("project_id");
			}

			//2. adding other tasks that don't need to be allocated: done, on hold, new tasks, etc
			$tasks = array();
			$sql = "SELECT task_id, task_title  FROM tasks ";
			$sql.= " WHERE  is_closed=0 AND task_type_id=1 ";
			$sql.= " AND project_id IN (".join(",",$projects). ") ";
			if ($hide_done_tasks) $sql .= " AND task_status_id!=4 ";
			$sql.= " ORDER BY responsible_user_id,priority_id";
			$db->query($sql);
			while ($db->next_record()) {
				$task_title = $db->f("task_title");
				$task_title = str_replace('"', '', $task_title);
				$tasks[$db->f("task_id")] = htmlentities($task_title);
			}
			//3. getting sort order
			$tasks_projects = json_decode($_SESSION['session_tasks_projects'], true);
			$task_count     = 0; //the order variable - we use tasks_project to get a correct value for it
			foreach ($projects as $project_id) {
				if (isset($tasks_projects[$project_id])) {
            		foreach ($tasks_projects[$project_id] as $task_id) {  
            			$task_count++;
            			$tasks[$task_id] = $task_count . " ". $tasks[$task_id]. " (".$task_id . ")";
            		}
            	}              
  
			}

			$fields["all_tasks"] = json_encode($tasks,JSON_HEX_QUOT);
		}

		foreach ($fields as $field_name => $field_value) {
			$xml_content .= "<".$field_name.">";
			$xml_content .= $field_value;
			$xml_content .= "</".$field_name.">\n";

		}

	}



}

header("Content-type: text/xml");
?>
<task>
	<?php echo $xml_content; ?>
</task>