<?php

/**
 * Return task info by task id
 *
 * @param integer $task_id
 * @return array
 */
function s_get_task_by_id($task_id) {
	global $db;	

	/*
	$task = array(
		'task_id' => $task_id
		, 'task_title' => 'test'
		, 'project_id' => 100
		, 'project_title' => 'title'
		, 'task_status_id' => 1
		, 'task_status_title' => 'done'
		, 'creation_date' => '2007-10-10'
		, 'planed_date' => '2007-11-10'
		, 'task_type_id' => 1
		, 'task_type_title' => 'New'
		, 'completion' => 50
		, 'actual' => 20.45
	);
 	*/

	$sql = "SELECT  t.task_id,
					t.task_title,
					p.project_id,
					p.project_title,
					t.task_status_id,
					ELT(t.task_status_id, 'InProgress',
										  'OnHold',
										  'Rejected',
										  'Done',
										  'Question',
										  'Answer',
										  'New',
										  'Waiting',
										  'Reassigned',
										  'Bug',
										  'Deadline',
										  'BugResolved') as task_status_title,
					cast(t.creation_date as DATE) as creation_date,
					cast(t.planed_date as DATE) as planed_date,
					t.task_type_id,
					ELT(task_type_id, 'New',
									  'Correction',
									  'Periodic') as task_type_title,
					t.completion,
					t.actual_hours AS actual
			FROM tasks t
				left join projects as p on p.project_id=t.project_id
			WHERE t.task_id=".ToSQL($task_id,"integer") . " ORDER BY priority_id";

	$db->query($sql);
	$db->next_record();

	$task = array(
		'task_id' => $db->Record["task_id"]
		, 'task_title' => $db->Record["task_title"]
		, 'project_id' => $db->Record["project_id"]
		, 'project_title' => $db->Record["project_title"]
		, 'task_status_id' => $db->Record["task_status_id"]
		, 'task_status_title' => $db->Record["task_status_title"]
		, 'creation_date' => $db->Record["creation_date"]
		, 'planed_date' => $db->Record["planed_date"]
		, 'task_type_id' => $db->Record["task_type_id"]
		, 'task_type_title' => $db->Record["task_type_title"]
		, 'completion' => $db->Record["completion"]
		, 'actual' => $db->Record["actual"]
	);

    //$task = $db->Record;
	return $task;
}

/**
 * Return array of task maps
 *
 * @param integer $client_id
 * @return array
 */
function s_get_tasks_by_client_id($sayu_client_id) {
	global $db;

	$client_id = 0;
	$sql = "SELECT client_id FROM clients WHERE is_viart = 0 AND sayu_user_id = " . ToSQL($sayu_client_id, "integer");
	$db->query($sql);
	if ($db->next_record()) {
		$client_id = $db->f(0);
	}
    /*
	$task1 = array(
		'task_id' => 1
		, 'task_title' => 'test'
		, 'project_id' => 100
		, 'project_title' => 'title'
		, 'task_status_id' => 1
		, 'task_status_title' => 'done'
		, 'creation_date' => '2007-10-10'
		, 'planed_date' => '2007-11-10'
		, 'task_type_id' => 1
		, 'task_type_title' => 'New'
		, 'completion' => 50
		, 'actual' => 20.45
	);

	$task2 = array(
		'task_id' => 2
		, 'task_title' => 'test'
		, 'project_id' => 100
		, 'project_title' => 'title'
		, 'task_status_id' => 1
		, 'task_status_title' => 'done'
		, 'creation_date' => '2007-10-10'
		, 'planed_date' => '2007-11-10'
		, 'task_type_id' => 1
		, 'task_type_title' => 'New'
		, 'completion' => 50
		, 'actual' => 20.45
	);

	return array($task1, $task2);
	*/
	$sql = "SELECT  t.task_id,
					t.task_title,
					p.project_id,
					p.project_title,
					t.task_status_id,
					ELT(t.task_status_id, 'InProgress',
										  'OnHold',
										  'Rejected',
										  'Done',
										  'Question',
										  'Answer',
										  'New',
										  'Waiting',
										  'Reassigned',
										  'Bug',
										  'Deadline',
										  'BugResolved') as task_status_title,
					cast(t.creation_date as DATE) as creation_date,
					cast(t.planed_date as DATE) as planed_date,
					t.task_type_id,
					ELT(task_type_id, 'New',
									  'Correction',
									  'Periodic') as task_type_title,
					t.completion,
					t.actual_hours AS actual
			FROM tasks t
				left join projects as p on p.project_id=t.project_id
			WHERE t.client_id=" . ToSQL($client_id, "integer") . "  AND t.is_closed = 0 ORDER BY priority_id";

	$db->query($sql);
	while($db->next_record()) {
		$task = array(
			'task_id' => $db->Record["task_id"]
			, 'task_title' => $db->Record["task_title"]
			, 'project_id' => $db->Record["project_id"]
			, 'project_title' => $db->Record["project_title"]
			, 'task_status_id' => $db->Record["task_status_id"]
			, 'task_status_title' => $db->Record["task_status_title"]
			, 'creation_date' => $db->Record["creation_date"]
			, 'planed_date' => $db->Record["planed_date"]
			, 'task_type_id' => $db->Record["task_type_id"]
			, 'task_type_title' => $db->Record["task_type_title"]
			, 'completion' => $db->Record["completion"]
			, 'actual' => $db->Record["actual"]
		);
		
		$tasks[] = $task;
	}

	return $tasks;
}

/**
 * Return array of task maps
 *
 * @param string $user_name
 * @return array
 */
function s_get_tasks_by_user_name($user_name) {
	global $db;

	// Get monitor user id by its name
	// Get opened tasks by user id

	/*
	$task1 = array(
		'task_id' => 1
		, 'task_title' => 'test'
		, 'project_id' => 100
		, 'project_title' => 'title'
		, 'task_status_id' => 1
		, 'task_status_title' => 'done'
		, 'creation_date' => '2007-10-10'
		, 'planed_date' => '2007-11-10'
		, 'task_type_id' => 1
		, 'task_type_title' => 'New'
		, 'completion' => 50
		, 'actual' => 20.45
	);

	$task2 = array(
		'task_id' => 2
		, 'task_title' => 'test'
		, 'project_id' => 100
		, 'project_title' => 'title'
		, 'task_status_id' => 1
		, 'task_status_title' => 'done'
		, 'creation_date' => '2007-10-10'
		, 'planed_date' => '2007-11-10'
		, 'task_type_id' => 1
		, 'task_type_title' => 'New'
		, 'completion' => 50
		, 'actual' => 20.45
	);

	return array($task1, $task2);
	*/

	$sql = "SELECT  t.task_id,
					t.task_title,
					p.project_id,
					p.project_title,
					t.task_status_id,
					ELT(t.task_status_id, 'InProgress',
										  'OnHold',
										  'Rejected',
										  'Done',
										  'Question',
										  'Answer',
										  'New',
										  'Waiting',
										  'Reassigned',
										  'Bug',
										  'Deadline',
										  'BugResolved') as task_status_title,
					cast(t.creation_date as DATE) as creation_date,
					cast(t.planed_date as DATE) as planed_date,
					t.task_type_id,
					ELT(task_type_id, 'New',
									  'Correction',
									  'Periodic') as task_type_title,
					t.completion,
					t.actual_hours AS actual
			FROM tasks t
				left join projects as p on p.project_id=t.project_id
				left join users as u on u.user_id=t.responsible_user_id
			WHERE CONCAT(u.first_name,' ',u.last_name) like '".$user_name."' AND t.is_closed = 0 ORDER BY priority_id";

	$db->query($sql);
	while($db->next_record()) {
		$task = array(
			'task_id' => $db->Record["task_id"]
			, 'task_title' => $db->Record["task_title"]
			, 'project_id' => $db->Record["project_id"]
			, 'project_title' => $db->Record["project_title"]
			, 'task_status_id' => $db->Record["task_status_id"]
			, 'task_status_title' => $db->Record["task_status_title"]
			, 'creation_date' => $db->Record["creation_date"]
			, 'planed_date' => $db->Record["planed_date"]
			, 'task_type_id' => $db->Record["task_type_id"]
			, 'task_type_title' => $db->Record["task_type_title"]
			, 'completion' => $db->Record["completion"]
			, 'actual' => $db->Record["actual"]
		);
		
		$tasks[] = $task;
	}

	return $tasks;
}

/**
 * Return array of task maps
 *
 * @param string $user_id
 * @return array
 */
function s_get_tasks_by_user_id($user_id) {
	global $db;

	// Get opened tasks by user id

	/*
	$task1 = array(
		'task_id' => 1
		, 'task_title' => 'test'
		, 'project_id' => 100
		, 'project_title' => 'title'
		, 'task_status_id' => 1
		, 'task_status_title' => 'done'
		, 'creation_date' => '2007-10-10'
		, 'planed_date' => '2007-11-10'
		, 'task_type_id' => 1
		, 'task_type_title' => 'New'
		, 'completion' => 50
		, 'actual' => 20.45
	);

	$task2 = array(
		'task_id' => 2
		, 'task_title' => 'test'
		, 'project_id' => 100
		, 'project_title' => 'title'
		, 'task_status_id' => 1
		, 'task_status_title' => 'done'
		, 'creation_date' => '2007-10-10'
		, 'planed_date' => '2007-11-10'
		, 'task_type_id' => 1
		, 'task_type_title' => 'New'
		, 'completion' => 50
		, 'actual' => 20.45
	);

	return array($task1, $task2);
	*/

	$sql = "SELECT  t.task_id,
					t.task_title,
					p.project_id,
					p.project_title,
					t.task_status_id,
					ELT(t.task_status_id, 'InProgress',
										  'OnHold',
										  'Rejected',
										  'Done',
										  'Question',
										  'Answer',
										  'New',
										  'Waiting',
										  'Reassigned',
										  'Bug',
										  'Deadline',
										  'BugResolved') as task_status_title,
					cast(t.creation_date as DATE) as creation_date,
					cast(t.planed_date as DATE) as planed_date,
					t.task_type_id,
					ELT(task_type_id, 'New',
									  'Correction',
									  'Periodic') as task_type_title,
					t.completion,
					t.actual_hours AS actual
			FROM tasks t
				left join projects as p on p.project_id=t.project_id
				left join users as u on u.user_id=t.responsible_user_id
			WHERE u.user_id=".ToSQL($user_id,"integer") . "  AND t.is_closed = 0 ORDER BY priority_id";

	$db->query($sql);
	while($db->next_record()) {
		$task = array(
			'task_id' => $db->Record["task_id"]
			, 'task_title' => $db->Record["task_title"]
			, 'project_id' => $db->Record["project_id"]
			, 'project_title' => $db->Record["project_title"]
			, 'task_status_id' => $db->Record["task_status_id"]
			, 'task_status_title' => $db->Record["task_status_title"]
			, 'creation_date' => $db->Record["creation_date"]
			, 'planed_date' => $db->Record["planed_date"]
			, 'task_type_id' => $db->Record["task_type_id"]
			, 'task_type_title' => $db->Record["task_type_title"]
			, 'completion' => $db->Record["completion"]
			, 'actual' => $db->Record["actual"]
		);
		
		$tasks[] = $task;
	}

	return $tasks;
}

?>