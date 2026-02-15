<?php

	include("./includes/date_functions.php");
	include("./includes/common.php");
	
	$t = new iTemplate($sAppPath);
	$t->set_file("main","task_to_clients.html");
	
	$show_project = GetParam("show_project");
	$operation = GetParam("operation");
	$show_tasks = GetParam("show_tasks");
	
	//define projects
	$projects = array();
	$projects[] = array("id" => 213, "name" => "Articles");
	$projects[] = array("id" => 224, "name" => "Video Distribution");
	$projects[] = array("id" => 222, "name" => "Video Production");
	$projects[] = array("id" => 59, "name" => "Paying Client Work");
	$projects[] = array("id" => 53, "name" => "Full Account");
	$projects[] = array("id" => 79, "name" => "Sayu Web Clients");
	$projects[] = array("id" => 116, "name" => "SEO Other");
	//$projects[] = array("id" => 999, "name" => "Others");
	
	$t->set_var("projects", showSelectProjects($projects));
	$t->set_var("show_project", $show_project);
	
	//echo $operation." - ".$show_tasks."-".$show_project; exit;
	//update
	if ($operation == "update" && strlen($show_tasks)>0)
	{
		$task_array = explode("||", $show_tasks);
		for($i=0; $i<sizeof($task_array); $i++)
		{
			$client_id = GetParam("client_id_".$task_array[$i]);
			$domain_id = GetParam("domain_id_".$task_array[$i]);
			$domain_url = GetParam("task_domain_".$task_array[$i]);
			
			$sql  = "UPDATE tasks SET client_id=".ToSQL($client_id,"integer");
			$sql .= ",task_domain_id=".ToSQL($domain_id,"integer");
			$sql .= ",task_domain_url=".ToSQL($domain_url,"text");
			$sql .= " WHERE task_id=".ToSQL($task_array[$i],"integer");
			$db->query($sql);
		}
	}
	
	///show tasks
	$sql  = "SELECT t.task_id,t.client_id,t.task_title, p.project_title, c.client_name, t.task_domain_url, t.task_domain_id";
	$sql .= " FROM tasks t LEFT JOIN projects p ON (p.project_id=t.project_id)";
	$sql .= " LEFT JOIN clients c ON (c.client_id=t.client_id) WHERE ";
	if ($show_project == 79) {
		$sql .= " p.parent_project_id=".ToSQL($show_project,"integer"); //show tasks of selected project
	}
	elseif ($show_project>0) {
		$sql .= " t.project_id=".ToSQL($show_project,"integer"); //show tasks of selected project
	}
	else {
		$sql .= " (t.project_id=213"; //Articles
		$sql .= " OR t.project_id=224"; //Video Distribution
		$sql .= " OR t.project_id=222"; //Video Production
		$sql .= " OR t.project_id=59"; //Paying Client Work
		$sql .= " OR t.project_id=53"; //Full Account
		$sql .= " OR p.parent_project_id=79"; //Sayu Web Clients
		$sql .= " OR t.project_id=236"; //Sayu math::one-off
		$sql .= " OR t.project_id=237"; //Sayu math::paying clients
		$sql .= " OR t.project_id=41"; //Sayu math::trials
		$sql .= " OR t.project_id=116)"; //SEO Other
	}
	$sql .= " AND (t.client_id is NULL OR t.client_id=0)";
	$sql .= " ORDER BY p.project_title, t.task_title LIMIT 10 ";
	$db->query($sql);
	if ($db->next_record()) {
		$t->set_var("tasks_records", "");
		$t->set_var("tasks_no_records", "");
		$show_tasks = "";
		do 
		{		
			$t->set_var("task_id", $db->f("task_id"));
			$t->set_var("task_title", $db->f("task_title"));
			$t->set_var("client_id", $db->f("client_id"));
			$t->set_var("task_client", $db->f("client_name"));
			$t->set_var("task_domain", $db->f("task_domain_url"));
			$t->set_var("domain_id", $db->f("task_domain_id"));
			$t->set_var("project_title", $db->f("project_title"));		
			$t->parse("task_domain_block", false);	
			$t->parse("task_client_block", false);	
			$t->parse("tasks_records", true);
			$show_tasks .= "||".$db->f("task_id");
		} while($db->next_record());
	}
	else $t->parse("tasks_no_records", false);	
	
	$t->set_var("show_tasks",$show_tasks);
	$t->pparse("main");
	
	function showSelectProjects($projects)
	{
		global $show_project;
		$res_str = "";
		foreach ($projects as $project)
		{
			$project_id = $project["id"];
			if ($show_project == $project_id) $selected = "selected"; else $selected = "";
			$res_str .= "<option $selected value=\"$project_id\">".$project["name"]."</option>";
		}
		return $res_str;
	}

?>