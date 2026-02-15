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
	
$sql = " update projects set is_domain_required=1 where parent_project_id=79";
$db->query($sql);
?>
