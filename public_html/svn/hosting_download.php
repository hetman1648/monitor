<?php
/*
	run this file from AJAX call to initiate Vlad's deamon script download images folder and database from sayu hosting
//@param: project
//@param: is_db (int) - flag to copy DB
//@param: is_images (int) - flag to images folder
*/

//we use $svn_login  $svn_password from auth.php

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");

$project   = GetParam("project");
$is_db     = GetParam("is_db");
$is_images = GetParam("is_images");

if ($is_images == "false") $is_images = "0";
if ($is_images == "true")  $is_images = "1";
$is_db = ($is_db == "true") ? "1" : "0";

if (!strlen($project)) die ("Please specify SVN project");

$params = array("project"   => $project,
                "user_name" => $svn_login,
                "password"  => $svn_password,
                "is_images" => $is_images,
                "is_db"     => $is_db
	);
$params_strk = "";
foreach ($params as $param_name => $param_value) {
	$params_strk.= $param_name ."=".$param_value."&";
}
$command = "https://dsid.sayuconnect.com/index.php?".$params_strk;
//echo $command;
//mail("artem.birzul@gmail.com","testing hosting_download.php",$command);
$res = get_page($command); 

echo $res;


