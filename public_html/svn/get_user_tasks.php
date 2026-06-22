<?php

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");



$user_id      = GetParam("user_id");
//sometimes javascript IDs contain extra characters - we remove them to get task_id
$parts = explode("_",$user_id);
if ((!is_array($parts)) || (!isset($parts[1]))) die("Error: reading users tasks $user_id");
$user_id = $parts[1];
$user_id = preg_replace("/[^0-9]/","",$user_id);
$xml_content = "";

if ($user_id && is_numeric($user_id)) {

	$tasks_user_name = "";
	$sql = "SELECT CONCAT(first_name,' ', last_name) as user_name FROM users WHERE user_id=".$user_id;
	$db->query($sql);
	if ($db->next_record()) {
		$tasks_user_name = $db->f("user_name");
	}

	$sql = "SELECT * FROM lookup_tasks_statuses  ORDER BY popularity DESC";
	$tasks_statuses = array();
	$db->query($sql); 
	while ($db->next_record()) {
		$tasks_statuses[$db->f("status_id")] = ucwords($db->f("status_desc"));
	}

	// getting projects list for Sayu Web clients parent_id=79
	/*$projects = array();
	$sql = "SELECT project_id, project_title FROM projects WHERE is_closed=0 and parent_project_id=79 ORDER BY project_title";
	$db->query($sql);
	while ($db->next_record()) {
	    $projects[$db->f("project_id")] = $db->f("project_title");
	}*/

?>

<input type="hidden" id="hdnTasksUserName" value="<?php echo $tasks_user_name; ?>">
<table class="table table-condensed table-striped">
<thead>
	<th>#</th>
	<th>Project</th>
	<th>Task Name</th>
	<th>Status</th>
	<th>Est.</th>
	<th>Act.</th>
	<th>%</th>
</thead>
<tbody id="sortable">
<?php
	$sql = "SELECT * FROM tasks LEFT JOIN projects ON tasks.project_id=projects.project_id ";
	$sql.= " WHERE tasks.responsible_user_id=".$user_id. " AND tasks.is_closed=0 AND task_type_id!=3";
	$sql.= " ORDER BY priority_id";
	$db->query($sql); 
	$count = 0;
	while ($db->next_record()) {
		$count++;
		$task_title    = $db->f("task_title");
		$project_title = $db->f("project_title");
		$task_id       = $db->f("task_id");
		$actual_hours  = getHoursFormat($db->f("actual_hours"),false);
		$estimate      = getHoursFormat($db->f("estimated_hours"));
		$full_title    = $task_title;
		$full_title    = str_replace("'", "\'", $full_title);
		$completion    = $db->f("completion");
		if (!$completion) $completion = "0";

		if (strlen($task_title) > 44) $task_title = trim(substr($task_title, 0,44)) ."..";
               
		echo "<tr id='task_$task_id' style='white-space:nowrap;'>";
		echo "<td>$count</td>";
		echo "<td>$project_title</td>";
		echo "<td><a title='$full_title' href='../edit_task.php?task_id=$task_id' target='_new'>$task_title</a></td>";
		echo "<td>".$tasks_statuses[$db->f("task_status_id")]."</td>";
		echo "<td>$estimate</td>";
		echo "<td>$actual_hours</td>";
		echo "<td>$completion%</td>";
		echo "</tr>";

	}
}		
?>
</tbody>
</table>
