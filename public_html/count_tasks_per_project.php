#!/usr/local/bin/php4 -q
<?php

include_once("./db_mysql.inc");
include_once("./includes/db_connect.php");
include_once("./includes/common_functions.php");

$db = new DB_Sql;
$db->Database = DATABASE_NAME;
$db->User     = DATABASE_USER;
$db->Password = DATABASE_PASSWORD;
$db->Host     = DATABASE_HOST;

$db2 = new DB_Sql;
$db2->Database = DATABASE_NAME;
$db2->User     = DATABASE_USER;
$db2->Password = DATABASE_PASSWORD;
$db2->Host     = DATABASE_HOST;

$db->query('SELECT tasks_count, project_id
			FROM (
				  SELECT IF(task_id IS NOT NULL, COUNT(t.task_id), 0)as tasks_count,
				  parent_project_id as project_id
				  FROM tasks t RIGHT JOIN projects p ON t.project_id = p.project_id
				  WHERE p.parent_project_id IS NOT NULL AND t.is_closed=0
				  GROUP BY p.parent_project_id
				  UNION
				  SELECT COUNT(t.task_id)as tasks_count, p.project_id as project_id
				  FROM tasks t RIGHT JOIN projects p ON t.project_id = p.project_id
				  WHERE t.is_closed=0 GROUP BY p.project_id) x
			GROUP BY project_id');

$sql2 = "UPDATE projects SET tasks_count=0";
$db2->query($sql2);

while ($db->next_record()){
	$sql2 ='UPDATE projects
			SET	tasks_count ='.$db->Record['tasks_count'].'
			WHERE project_id ='.$db->Record['project_id'];	
	$db2->query($sql2);

	//count_project_time($db->Record['project_id']);
}

?>
