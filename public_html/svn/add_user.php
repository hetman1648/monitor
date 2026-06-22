<?php
/*
	run this file from AJAX call to create SVN user 
	@param: $new_user
	@param: $new_password
*/

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");

$new_user      = GetParam("new_user");
$new_password  = GetParam("new_password");
$svn_subdomain = GetParam("svn_subdomain");

if (!strlen($new_user))      die ("Please specify user");
if (!strlen($new_password))  die ("Please specify password");
if (!strlen($svn_subdomain)) die ("Please specify subdomain");


$command = "index.php?action=createuser&subdomain=".$svn_subdomain."&username=".$svn_login."&password=".$svn_password."&newuser=".$new_user."&newpassword=".$new_password."&usertype=developer";
//$command = "index.php?action=passwd&username=".$svn_login."&password=".$svn_password."&chusername=".$new_user."&chpassword=".$new_password."&usertype=developer";
//echo $command;
 // exit;
$res = get_page($svn_path. $command); 
echo $res;