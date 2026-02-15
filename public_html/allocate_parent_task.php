<?php

	ini_set('memory_limit', '36M');
	
	include ("./includes/common.php");

	$sub_task_id			= GetParam("task_id");
	$project_id			= GetParam("project_id");
	$parent_task_id		= GetParam("parent_task_id");
	$allocate_url_id	= GetParam("allocate_url_id");
	$action = GetParam("action");
	
	$t = new iTemplate($sAppPath);
	$t->set_file("main", "allocate_parent_task.html");
	$t->set_var("project_id", $project_id);
	$t->set_var("sub_task_id", $sub_task_id);
	$t->set_var("allocate_url_id", $allocate_url_id);
	
	if ($action == "allocate" && $parent_task_id>0) {
		$sql = "UPDATE tasks SET parent_task_id= ".ToSQL($parent_task_id, "integer")." WHERE task_id=".ToSQL($sub_task_id, "integer");
		$db->query($sql);		
		$t->parse("close_window_script", false);
	} else {
		$t->set_var("close_window_script", "");
	}
	
	$sql = " SELECT p.task_id, p.task_title, p.is_closed, pr.project_title FROM tasks p INNER JOIN projects pr ON pr.project_id=p.project_id ";
	$sql.= " WHERE p.project_id=".ToSQL($project_id, "integer")." AND p.task_type_id=4 ";
	$sql.= " ORDER BY p.is_closed, p.task_title ";
	
	$db->query($sql);
	$i = 0;
	if ($db->num_rows()) {
	while($db->next_record()) {
		$t->set_var("task_id", $db->f("task_id"));
		if ($action == "allocate" && $parent_task_id == $db->f("task_id")) {
			$t->set_var("parent_task_id", $db->f("task_id"));
			$t->set_var("parent_task_title_slashed", str_replace(array("'","\""), array("\'",""), $db->f("task_title")) );
		}
		$t->set_var("task_title", $db->f("task_title"));
		$t->set_var("task_title_slashed", str_replace(array("'","\""), array("\'",""), $db->f("task_title")) );
		$t->set_var("project_title", $db->f("project_title"));
		if ($db->f("is_closed")) {
			$t->set_var("span_color", "grey");
		} else {
			$t->set_var("span_color", "navy");				
		}
		
		$t->set_var("title_id", ++$i);
		
		$t->parse("quotations", true);
		$t->set_var("no_quotations", "");
	}
	} else {
		
		$sql = "SELECT project_title FROM projects WHERE project_id=".ToSQL($project_id, "integer");
		$db->query($sql);
		$db->next_record();
		$t->set_var("project_title", $db->f("project_title"));
		$t->parse("no_quotations", true);
		$t->set_var("quotations", "");
	}
	$t->pparse("main");	
?>