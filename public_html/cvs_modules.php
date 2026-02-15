<?php
	include("./includes/common.php");

	CheckSecurity(1);	
		
	$t = new iTemplate($sAppPath);
	$t->set_file("main", "cvs_modules.html");
	
	$sql  = " SELECT project_id, project_title, project_url, cvs_module";
	$sql .= " FROM projects";
	$sql .= " WHERE cvs_module IS NOT NULL";
	$sql .= " ORDER BY project_title";			
	$db->query($sql);
	$a = 0;
	while($db->next_record()) {
		$project_id    = $db->f("project_id");
		$project_title = $db->f("project_title");
		$project_url   = $db->f("project_url");
		$cvs_module    = $db->f("cvs_module");		
		$t->set_var("project_id",    $project_id);
		$t->set_var("project_title", $project_title);
		$t->set_var("project_url",   $project_url);
		$t->set_var("cvs_module",    $cvs_module);
		$t->set_var("colorrow",    	 (($a++)%2)?"DataRow2":"DataRow3");
		$t->parse("module", true);
	}
	
	$sql  = " SELECT project_id, project_title, project_url";
	$sql .= " FROM projects";
	$sql .= " WHERE cvs_module IS NULL";
	$sql .= " AND (parent_project_id = 79 OR parent_project_id = 170)";
	$sql .= " AND is_closed = 0 ";
	$sql .= " ORDER BY project_title";	
	$db->query($sql);
	$a = 0;	
	if ($db->next_record()) {
		do {
			$project_id    = $db->f("project_id");
			$project_title = $db->f("project_title");
			$project_url   = $db->f("project_url");
			$cvs_module    = $db->f("cvs_module");
			$t->set_var("project_id",    $project_id);
			$t->set_var("project_title", $project_title);
			$t->set_var("project_url",   $project_url);
			$t->set_var("colorrow",    	 (($a++)%2)?"DataRow2":"DataRow3");
			$t->parse("to_create_module", true);
		} while ($db->next_record());
		$t->parse("to_create_modules", true);
	}
	
	$t->pparse("main");
?>