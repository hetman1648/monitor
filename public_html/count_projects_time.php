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

$db2->query('SELECT project_id FROM projects WHERE is_closed=0 AND parent_project_id =79');
while ($db2->next_record()){
	count_project_time($db2->Record['project_id']);
}

?>