<?php
	
	include_once("./includes/common.php");
	include_once("./includes/date_functions.php");
	
	if (getsessionparam("privilege_id") == 9) {
		header("Location: index.php");
		exit;
	}
	
	// SECOND DATABASE OBJECT
	$db2 = new DB_Sql;
	$db2->Database = DATABASE_NAME;
	$db2->User     = DATABASE_USER;
	$db2->Password = DATABASE_PASSWORD;
	$db2->Host     = DATABASE_HOST;
	
	global $is_paid;
	
	$T = new iTemplate("./templates", array("page"=>"testing_templates.html"));
	
	CheckSecurity(1);
	
	$template_id = GetParam("template_id");
	
	$sql = "SELECT * FROM testing_templates";
	$db->query($sql);
	if ($db->next_record())
	{
		$T->set_var("templates_error","");
		$T->parse("templates_header",false);
		do
		{
			$user_id = $db->Record["created_user_id"];
			$sql2 = "SELECT CONCAT(first_name,' ',last_name) AS user_name FROM users WHERE user_id='$user_id'";
			$db2->query($sql2);
			if ($db2->next_record())
			{
				$T->set_var("user_name",$db2->Record["user_name"] );
			}
			$T->set_var("template_id",$db->Record["template_id"]);
			$T->set_var("template_title_content",$db->Record["template_name"]);
			$T->set_var("template_title",$db->Record["template_name"]);
			$T->set_var("date_added",norm_sql_date($db->Record["date_added"]));
			if ($db->Record["date_updated"])
			{
				$user_id_up = $db->Record["updated_user_id"];
				$sql2 = "SELECT CONCAT(first_name,' ',last_name) AS user_name FROM users WHERE user_id='$user_id_up'";
				$db2->query($sql2);
				if ($db2->next_record())
				{
					$T->set_var("date_updated","last updated  ".norm_sql_date($db->Record["date_updated"])." by ". $db2->Record["user_name"]);
				}
	
			}
	
			else
			$T->set_var("date_updated","no updates");
			$T->set_var("template_description", $db->Record["template_description"]);
			$T->parse("templates",true);
			$T->parse("templates_content",true);
		}
		while ($db->next_record());
	}
	else
	{
		$T->parse("templates_error",false);
		$T->set_var("templates_header","");
		$T->set_var("templates","");
		$T->set_var("templates_content","");
	}
	
	$T->pparse("page");

?>