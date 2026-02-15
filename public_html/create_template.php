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
	

	$T = new iTemplate("./templates", array("page"=>"create_template.html"));
	
	CheckSecurity(1);
	
	$user_id_creator = getsessionparam("UserID");
	$action = GetParam("action");
	$add = GetParam("add");	
	$template_id = GetParam("template_id");
	$template_title = GetParam("template_title");
	$template_description = GetParam("template_description");
	
	if (!$template_id && !$add) {
		header("Location: testing_templates.php");
		exit;
	}
	if ($add)
	{
		$T->set_var("header_template", "Add Template");
		$T->set_var("delete_button", "");
		$T->set_var("edit_add_button", "Add");
		$T->parse("edit_button");
		$T->set_var("template_title", "");
		$T->set_var("template_description", "");
		$T->parse("template");
		$T->set_var("template_error","");
	}
	else
	{
		$T->set_var("header_template", "Edit Template");
		$T->set_var("template_id", $template_id);
		$sql = "SELECT * FROM testing_templates WHERE template_id = " . ToSQL($template_id, "integer");
		$db->query($sql);
		if ($db->next_record())
		{
			$T->set_var("template_title", htmlspecialchars($db->f("template_name")));
			$T->set_var("template_description", htmlspecialchars($db->f("template_description")));
			$T->parse("template");
			$T->set_var("template_error","");
			$T->set_var("template_holiday", "Edit Template");
			$T->parse("delete_button");
			$T->set_var("edit_add_button", "Update");
			$T->parse("edit_button");
		} else {
			$T->set_var("template","");
			$T->parse("template_error");
		}
	}
	
	if ($action == "Cancel") {
		header("Location: testing_templates.php");
		exit;
	} 
	/*
	elseif (($action == "Delete")) {
		$sql = "DELETE FROM testing_templates WHERE template_id = " . ToSQL($template_id, "integer");
		$db->query($sql);
		header("Location: testing_templates.php");
		exit;
	}
	*/
	elseif (($action == "Update")) {
		$sql = "UPDATE testing_templates SET template_name = " . ToSQL($template_title, "text")
		. ", date_updated = NOW(), updated_user_id = " . ToSQL($user_id_creator, "integer")
		. ", template_description = " . ToSQL($template_description, "text")
		. " WHERE template_id = " . ToSQL($template_id, "integer");
		$db->query($sql);
		header("Location: testing_templates.php");
		exit;
	}
	elseif (($action == "Add")) {
		$template_title = $_POST["template_title"];
		$sql = "INSERT INTO testing_templates (template_id, template_name,template_description, date_added, created_user_id) "
		. "VALUES (NULL," . ToSQL($template_title, "text") . "," . ToSQL($template_description, "text") . ", NOW(), " . ToSQL($user_id_creator, "integer") . ")";
		$db->query($sql);
		header("Location: testing_templates.php");
		exit;
	}
	
	$T->pparse("page");
	
?>