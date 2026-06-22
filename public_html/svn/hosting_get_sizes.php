<?php
/*
	run this file from AJAX call to get list of DB size and images folder sizes from Sayu hosting
	@param: project
*/

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");

$project = GetParam("project");
if (!strlen($project)) die ("Please specify SVN project");

$command = "get_sizes.php?&project=".$project;
$res = get_page($svn_path. $command); 

echo $res;

