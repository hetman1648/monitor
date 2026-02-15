<?php
	include_once("./includes/date_functions.php");
	include_once("./includes/common.php");

	CheckSecurity(1);

	$db_sub = new DB_Sql();
	$db_sub->Database = DATABASE_NAME;
	$db_sub->User     = DATABASE_USER;
	$db_sub->Password = DATABASE_PASSWORD;
	$db_sub->Host     = DATABASE_HOST;

	$t = new iTemplate($sAppPath);
	$t->set_file("main", "clients_report.html");
	
	$records_per_page = GetParam("records_per_page");
	if (!$records_per_page) $records_per_page = 'all';
	
	$t->set_var($records_per_page."_selected", "selected");

	/**
	*	Filter. begin
	*/
	$period_selected	= GetParam("period_selected");
	$start_date			= GetParam("start_date");
	$end_date			= GetParam("end_date");
	$person_selected	= GetParam("person_selected");
	$project_selected	= GetParam("project_selected");
	$subproject_selected	= GetParam("subproject_selected");
	$submit				= GetParam("submit");
	$team				= GetParam("team");
	$client_type				= GetParam("client_type");
	$keyword			= GetParam("keyword");
	$googleid_search	= GetParam("googleid_search");
	$client_id			= GetParam("client_id");
	$page_num			= GetParam("page_num")?GetParam("page_num"):1;

	$t->set_var("googleid_search", $googleid_search);
	$t->set_var("keyword", $keyword);
	$t->set_var("person_selected", $person_selected);
	$t->set_var("project_selected", (int)$project_selected);
	$t->set_var("subproject_selected", (int)$subproject_selected);
	

	$as="";$vs="";$ys="";$ss="";
	$search ="&page_num=".$page_num;

/*  	$search .="&team=".$team;
	switch (strtolower($team)){
		case "all":		$sqlteam = ""; $as = "selected"; break;
		case "viart":	$sqlteam = " AND u.is_viart=1 "; $vs = "selected"; break;
		case "yoonoo":	$sqlteam = " AND u.is_viart=0 "; $ys = "selected"; break;
		default:		$sqlteam = " AND u.is_viart=1 "; $vs = "selected"; $team = "viart";
	}
  */
 
 	$search .="&client_type=".$client_type;
	switch (strtolower($client_type)){
		case "all":		$sqlclienttype = ""; $as = "selected"; break;
		case "viart":	$sqlclienttype = " AND c.is_viart=1 "; $vs = "selected"; break;
		case "sayu":	$sqlclienttype = " AND c.is_viart=0 "; $ss = "selected"; break;
		default:		$sqlclienttype = ""; $as = "selected"; break;
	}

 
	if (!$period_selected && !$submit) {$period_selected = "today";}
	$t->set_var("periods", GetPeriodOption($period_selected));

	$t->set_var("period", $period_selected);

	$t->set_var("aselected", $as);
	$t->set_var("vselected", $vs);
	$t->set_var("yselected", $ys);
	$t->set_var("sselected", $ss);
	$t->set_var("team_selected", $team);
	$t->set_var("client_type_selected", $client_type);

	$current_date = va_time();

	list($sdt,$edt)=get_start_end_period($period_selected,$start_date,$end_date);
	$t->set_var("period_selected", $period_selected);
    $search .="&period_selected=".$period_selected;


	$sqluser	= "";
	if ($person_selected) {
		$sqluser .= " AND u.user_id=".ToSQL($person_selected,"integer");
		$search .="&person_selected=".$person_selected;
	}

	$sqlkeyword	= "";
	if ($keyword) {
		if( strpos(strtolower($keyword),"%") === false) {
			$sqlkeyword .= " AND (LOWER(c.client_email) LIKE ".ToSQL("%".strtolower($keyword)."%","string")." ";
			$sqlkeyword .= " OR LOWER(c.client_name) LIKE ".ToSQL("%".strtolower($keyword)."%","string")." ";
			$sqlkeyword .= " OR c.client_company LIKE ".ToSQL("%".$keyword."%","string") . ")";
		} else {
			$sqlkeyword .= " AND (LOWER(c.client_email) LIKE ".ToSQL(strtolower($keyword),"string")." ";
			$sqlkeyword .= " OR LOWER(c.client_name) LIKE ".ToSQL(strtolower($keyword),"string")." ";
			$sqlkeyword .= " OR c.client_company LIKE ".ToSQL($keyword,"string") . ")";
		}
		$search .="&keyword=".$keyword;
	}

    $sqlproject = "";
    if ($project_selected) {
/*     	$sql = "SELECT GROUP_CONCAT(project_id SEPARATOR ', ') as projectid FROM projects WHERE project_id=".ToSQL($project_selected,"integer")." OR parent_project_id=".ToSQL($project_selected,"integer"); */


    	$sql = "SELECT GROUP_CONCAT(project_id SEPARATOR ', ') as projectid FROM projects WHERE project_id=".ToSQL($project_selected,"integer")." OR parent_project_id=".ToSQL($project_selected,"integer");
			
			if ($subproject_selected != 0) {
				$sql = "SELECT GROUP_CONCAT(project_id SEPARATOR ', ') as projectid FROM projects WHERE project_id=".ToSQL($subproject_selected,"integer");
			}
			
    	$db->query($sql,__FILE__,__LINE__);
    	$db->next_record();
    	$projects = $db->Record["projectid"];
    	//$sqlproject .= " AND t.project_id in ".ToSQL($project_selected,"integer")." AND prc.is_closed=0 ";
    	$sqlproject .= " AND t.project_id in (".$projects.") AND prc.is_closed=0 ";
    	$search .="&project_selected=".$project_selected;
			if ($subproject_selected != 0) 
				$search .="&subproject_selected=".$subproject_selected;
    }

    //$sqlsearch = $sqlteam.$sqluser.$sqlkeyword.$sqlproject;
    $sqlsearch = $sqlclienttype.$sqluser.$sqlkeyword.$sqlproject;

    if ($googleid_search) {
    	$googleid_search = preg_replace("/[-+ _]/","",$googleid_search);
    	if( strpos(strtolower($keyword),"%") === false) {
    		$sqlsearch .= " AND c.google_id LIKE ".ToSQL("%".$googleid_search."%","string");
    	}
    	else { $sqlsearch .= " AND c.google_id LIKE ".ToSQL($googleid_search,"string");}
    	$search .="&googleid_search=".$googleid_search;
    }
		
    $t->set_var("keyword",$keyword);
	$t->set_var("googleid_search",$googleid_search);

	$t->set_var("project_list", Get_Options("projects WHERE is_closed=0 AND parent_project_id IS NULL",
											"project_id",
											"project_title",
											"project_title",
											($project_selected ? $project_selected:-1)
											));

	$t->set_var("person_list", 	Get_Options("users WHERE is_viart=1 AND is_deleted IS NULL ORDER BY user_name",
											"user_id",
											"CONCAT(first_name,' ',last_name) as user_name",
											"user_name",
											($person_selected ? $person_selected:-1)
											));

	/**
	*        Filter. end
	*/


    /**
	*        Sorted. start
	*/


	$sort	= GetParam("sort");
	$order	= GetParam("order");
	$orders  = "0".$search;
	$t->set_var("cl_sort",$orders);
	$t->set_var("cn_sort",$orders);
	$t->set_var("ce_sort",$orders);
	$t->set_var("sh_sort",$orders);
	$t->set_var("sc_sort",$orders);
	$t->set_var("tc_sort",$orders);

	if ($sort){
		//cl_sort cn_sort ce_sort sh_sort sc_sort tc_sort
		$sortstr = "";
		$orders  = (int)!$order;
		$orders .= $search;
		switch (strtolower($sort)) {
			case "client_name":
					$sortstr .= " ORDER BY c.client_name";
					$t->set_var("cl_sort",$orders);
					break;
			case "company_name":
					$sortstr .= " ORDER BY c.client_company";
					$t->set_var("cn_sort",$orders);
					break;
			case "client_email":
					$sortstr .= " ORDER BY c.client_email";
					$t->set_var("ce_sort",$orders);
					break;
			case "spent_hours":
					$sortstr .= " ORDER BY total_time";
					$t->set_var("sh_sort",$orders);
					break;
			case "sayu_cost":
					$sortstr .= " ORDER BY sayu_cost";
					$t->set_var("sc_sort",$orders);
					break;
			case "tasks_count":
					$sortstr .= " ORDER BY task_count";
					$t->set_var("tc_sort",$orders);
					break;
			//default:
		}
		if (is_numeric($order)) {
			switch ($order) {
				case 0 : $sortstr .= " ASC "; break;
				case 1 : $sortstr .= " DESC "; break;
				default: $sortstr .= " ASC ";
			}
		}
	} else {
		$sortstr = " ORDER BY c.client_name ASC";
		$t->set_var("cl_sort","1".$search);
	}

	//$t->set_var(array(""=> "",));

	/**
	*        Sorted. end
	*/

	//if ($reverse) {$t->set_var("reverse", '');}
	// else {$t->set_var("reverse", '&reverse=reverse');}
	//$page_num = GetParam('page_num') ? GetParam('page_num') : 1;
	$t->set_var("client_task_view","");

	if ($sdt && $edt) {
		$sql = "SELECT	c.client_id AS
							client_id,
						c.client_name AS
							client_name,
						c.client_company AS
							company_name,
						c.client_email AS
							client_email,
						COUNT(DISTINCT tr.task_id) AS
							task_count,
						SUM(tr.spent_hours) AS
							total_time,
						ROUND(SUM(tr.spent_hours)*15, 2) As
							sayu_cost
				FROM	time_report tr
						RIGHT JOIN tasks t ON (tr.task_id=t.task_id AND DATE(tr.started_date) BETWEEN DATE('".$sdt."') AND DATE('".$edt."'))
						RIGHT JOIN clients c ON t.client_id=c.client_id
						LEFT JOIN projects prc ON prc.project_id=t.project_id
						LEFT JOIN projects prp ON prp.project_id=prc.parent_project_id
						LEFT JOIN users u ON tr.user_id=u.user_id
				WHERE	1
						".$sqlsearch."
						AND t.client_id <> 0
						AND t.client_id IS NOT NULL
				GROUP BY c.client_email 
				HAVING task_count > 0 "
				.$sortstr;
		
		$count_record = once_query("SELECT COUNT(*) FROM (".$sql.") qqq",true);
		
		$navigator = "";
		
		if ($records_per_page != 'all') {
			$page_count = (int)ceil($count_record/$records_per_page);
			$query_string = ((strtolower($_SERVER['QUERY_STRING'])<>"undefined" && strlen($_SERVER['QUERY_STRING'])>0)?$_SERVER['QUERY_STRING']:"");
			$navigator = getPageLinks($page_count, $records_per_page, $page_num, $search, $period_selected, $start_date, $end_date);
					
			$limit = " LIMIT ".($page_num - 1)*$records_per_page.", ".$records_per_page;
		} 
		
		if ($navigator) {
			$t->set_var("navigator_top",$navigator);
			$t->parse("navigation_top");
		} else { 
			$t->set_var("navigation_top","");
			$limit = "";
		}
		
		$sql .= $limit;
		$db->query($sql,__FILE__,__LINE__);
//echo $sql;		

		$t->set_var("ClientParent","");
		if ($db->num_rows()) {
			$t->set_var("no_records", "");
			$rowscolor = 0;
			$total_hours = 0.00;
			$total_tasks = 0;
			$total_cost = 0;

			while($db->next_record()){
				$sayu_cost = $db->Record["sayu_cost"];
				$total_time = floor($db->Record["total_time"]) . ":" . sprintf("%02d", round(($db->Record["total_time"] - floor($db->Record["total_time"])) * 60));
				$client_name = "";
				if ($db->Record["client_name"] <> "") {
					$client_name = (strlen($db->Record["client_name"])>27?substr($db->Record["client_name"],0,24)."&#8230;":$db->Record["client_name"]);
					
				} else { $client_name = "<i>noname</i>";}

				$total_hours += $db->Record["total_time"];
				$total_tasks += $db->Record["task_count"];
				$total_cost += $db->Record["sayu_cost"];
		        $t->set_var(array(	
									"client_name"			=> $client_name,
									"search"			=> $search,
									"client_email"			=> $db->Record["client_email"],
									"company_name"			=> $db->Record["company_name"],
									"cutted_client_email"	=> 	(strlen($db->Record["client_email"]) > 27) ? substr($db->Record["client_email"], 0, 24) . '&#8230;' : $db->Record["client_email"],
									
									"total_time"			=> $total_time,
									"sayu_cost"				=> $sayu_cost,
									"task_count"			=> $db->Record["task_count"],
									"client_id"				=> $db->Record["client_id"],
									"colorrow"				=> ($rowscolor++ % 2 == 1)?"DataRow2":"DataRow3"
                    		));				
               	
               	//$t->set_var("clients","");
				$t->parse("ClientParent",true);
			}
			$t->set_var("total_hours", to_hours($total_hours));
			$t->set_var("total_tasks", $total_tasks);
			$t->set_var("total_cost", number_format($total_cost, 2, '.', ','));
			$t->parse("totals");
		
		} else {
			$t->set_var("clients_header","");
			$t->set_var("clients","");
			$t->set_var("ClientParent","");
			$t->set_var("totals","");
			$t->parse("no_records", false);
		}
		
		if ($navigator) {
			$t->set_var("navigator_bottom",$navigator);
			$t->parse("navigation_bottom");
		} else { $t->set_var("navigation_bottom","");}
		
		$t->parse("result", false);
	}	
	if ($client_id) {
		$sql_sub = "SELECT	tr.report_id AS
								report_id,
							t.task_id AS
								task_id,
							SUM(tr.spent_hours) AS
								spent_hours,
							t.task_title AS
								task_title,
							CONCAT('<a href = \"edit_task.php?task_id={task_id}\">' ,REPLACE(IF ((CHAR_LENGTH(t.task_title)>27) AND  (t.task_title <> ''), CONCAT(SUBSTRING(t.task_title, 1, 24), '...'), t.task_title), ' ', '&nbsp;'),  '</a>') AS
								task,
							t.completion AS
								percent,
							GROUP_CONCAT(DISTINCT (u.first_name) SEPARATOR ', ') AS
								developers,
							COUNT(DISTINCT DATE(tr.report_date)) AS
								working_days,
							IFNULL(REPLACE(IF((CHAR_LENGTH(prc.project_title)>27) AND  (prc.project_title <> ''), CONCAT(SUBSTRING(prc.project_title, 1, 24), '...'), prc.project_title), ' ', '&nbsp;'),'') AS
								child_project,
							IFNULL(REPLACE(IF((CHAR_LENGTH(prp.project_title)>27) AND  (prp.project_title <> ''), CONCAT(SUBSTRING(prp.project_title, 1, 24), '...'), prp.project_title), ' ', '&nbsp;'),'') AS
								parent_project
					FROM	
							time_report tr
							RIGHT JOIN tasks t ON t.task_id=tr.task_id
							RIGHT JOIN clients c ON c.client_id=t.client_id
							LEFT JOIN users u ON u.user_id=tr.user_id
							LEFT JOIN projects prc ON prc.project_id=t.project_id
							LEFT JOIN projects prp ON prp.project_id=prc.parent_project_id
					WHERE	1
							".$sqlsearch."					
							AND t.client_id =".ToSQL($client_id,"integer")."
							AND DATE(tr.started_date) BETWEEN CAST('".$sdt."' AS DATE) AND CAST('".$edt."' AS DATE)
					GROUP BY tr.task_id
					ORDER BY tr.task_id";
		$db_sub->query($sql_sub,__FILE__,__LINE__);
		//echo $sql_sub;
		$rowscolor = 0;
		$client_name = once_query("SELECT client_name FROM clients WHERE client_id=".ToSQL($client_id,"integer"),true);
		$t->set_var("task_client_name",$client_name);
		while($db_sub->next_record()){
			//$spent_hours = floor($db_sub->Record["spent_hours"]) . ":" . sprintf("%02d", round(($db_sub->Record["spent_hours"] - floor($db_sub->Record["spent_hours"])) * 60));
			$t->set_var(array(	"spent_hours"	=> to_hours($db_sub->Record["spent_hours"]), 
								"percent"		=> $db_sub->Record["percent"],
								"task_title"	=> $db_sub->Record["task"],
								"working_days"	=> $db_sub->Record["working_days"],
								"developers"	=> $db_sub->Record["developers"],
								"task_id"		=> $db_sub->Record["task_id"],
								"colorrow"		=> ($rowscolor++ % 2 == 1)?"DataRow2":"DataRow3"
							));
			$project_title = "";
			if ($db_sub->Record["parent_project"]<>"") {
				$project_title = $db_sub->Record["parent_project"].($db_sub->Record["child_project"]?"<br>&nbsp;&nbsp;&nbsp;".$db_sub->Record["child_project"]:"");
			} else {
				$project_title = $db_sub->Record["child_project"];
			}
			$t->set_var("project_title",$project_title);
			$t->parse("task_view",true);
		}
		$t->parse("client_task_view");
	}
    /*/
    if ($submit) {
    	if ($sdt && $edt) {
    		$sql = "SELECT	c.client_id AS
    							client_id,
    						c.client_name AS
    							client_name,
    						c.client_company AS
    							company_name,
    						c.client_email AS
    							client_email,
    						CONCAT('<a href = \"mailto:{client_email}\" >' ,REPLACE(IF ((CHAR_LENGTH(c.client_email)>27) AND  (c.client_email <> ''), CONCAT(SUBSTRING(c.client_email, 1, 24), '...'), c.client_email), ' ', '&nbsp;'),  '</a>') AS
    							cutted_client_email,
    						COUNT(DISTINCT tr.task_id) AS
    							task_count,
    						SUM(tr.spent_hours) AS
    							total_time
					FROM	time_report tr
							RIGHT JOIN tasks t ON (tr.task_id=t.task_id AND DATE(tr.started_date) BETWEEN DATE('".$sdt."') AND DATE('".$edt."'))
							RIGHT JOIN clients c ON t.client_id=c.client_id
							LEFT JOIN projects prc ON prc.project_id=t.project_id
                            LEFT JOIN projects prp ON prp.project_id=prc.parent_project_id
							LEFT JOIN users u ON tr.user_id=u.user_id
					WHERE	1
							".$sqlsearch."
							AND t.client_id <> 0
							AND t.client_id IS NOT NULL
					GROUP BY c.client_email
					ORDER BY t.client_id";
			$db->query($sql,__FILE__,__LINE__);
			$t->set_var("ClientParent","");
			if ($db->num_rows()) {
				$t->set_var("no_records", "");
				while($db->next_record()){
					if ($db->Record["task_count"]==0) { continue;}
					$sayu_cost = round($db->Record["total_time"]*15);
                    $total_time = floor($db->Record["total_time"]) . ":" . sprintf("%02d", round(($db->Record["total_time"] - floor($db->Record["total_time"])) * 60));
					$client_name = "";
					if ($db->Record["client_name"] <> "") {
						$client_name = (strlen($db->Record["client_name"])>27?substr($db->Record["client_name"],1,24)."...":$db->Record["client_name"]);
						$client_name = "<b>".$client_name."</b>";
					} else { $client_name = "<i>noname</i>";}
					$client_name = "<a href = \"create_client.php?client_id={client_id}\" >".$client_name."</a>";
                    $t->set_var(array(	"client_name"			=> $client_name,//($db->Record["client_name"]<>"noname"?"<b>".$db->Record["client_name"]."</b>":"<i>".$db->Record["client_name"]."</i>"),
                    					"client_email"			=> $db->Record["client_email"],
                    					"company_name"			=> $db->Record["company_name"],
                    					"cutted_client_email"	=> $db->Record["cutted_client_email"],
                    					"total_time"			=> "<b>".$total_time."</b>",
                    					"sayu_cost"				=> "<b>".$sayu_cost."</b>",
                    					"task_count"			=> $db->Record["task_count"],
                    					"client_id"				=> $db->Record["client_id"]
                    					//"" => $db->Record[""]
                    			));

					$sql_sub = "SELECT	tr.report_id AS
											report_id,
										t.task_id AS
											task_id,
										SUM(tr.spent_hours) AS
											spent_hours,
										t.task_title AS
											task_title,
										CONCAT('<a href = \"edit_task.php?task_id={task_id}\">' ,REPLACE(IF ((CHAR_LENGTH(t.task_title)>27) AND  (t.task_title <> ''), CONCAT(SUBSTRING(t.task_title, 1, 24), '...'), t.task_title), ' ', '&nbsp;'),  '</a>') AS
											task,
										t.completion AS
											percent,
										GROUP_CONCAT(DISTINCT (u.first_name) SEPARATOR ', ') AS
											developers,
										COUNT(DISTINCT DATE(tr.report_date)) AS
											working_days,
										IFNULL(REPLACE(IF((CHAR_LENGTH(prc.project_title)>27) AND  (prc.project_title <> ''), CONCAT(SUBSTRING(prc.project_title, 1, 24), '...'), prc.project_title), ' ', '&nbsp;'),'') AS
											child_project,
										IFNULL(REPLACE(IF((CHAR_LENGTH(prp.project_title)>27) AND  (prp.project_title <> ''), CONCAT(SUBSTRING(prp.project_title, 1, 24), '...'), prp.project_title), ' ', '&nbsp;'),'') AS
											parent_project
								FROM	time_report tr
										RIGHT JOIN tasks t ON t.task_id=tr.task_id
										RIGHT JOIN clients c ON c.client_id=t.client_id
										LEFT JOIN users u ON u.user_id=tr.user_id
										LEFT JOIN projects prc ON prc.project_id=t.project_id
										LEFT JOIN projects prp ON prp.project_id=prc.parent_project_id
								WHERE	1
										".$sqlteam.$sqluser."
										AND DATE(tr.started_date) BETWEEN CAST('".$sdt."' AS DATE) AND CAST('".$edt."' AS DATE)
										AND t.client_id =".$db->Record["client_id"]."
								GROUP BY tr.task_id
								ORDER BY tr.task_id";
                	$db_sub->query($sql_sub,__FILE__,__LINE__);
                	$t->set_var("clients","");
                	while($db_sub->next_record()){
                		$spent_hours = floor($db_sub->Record["spent_hours"]) . ":" . sprintf("%02d", round(($db_sub->Record["spent_hours"] - floor($db_sub->Record["spent_hours"])) * 60));
                		$t->set_var(array(	"spent_hours"	=> $spent_hours,
                							"percent"		=> $db_sub->Record["percent"],
                							"task_title"	=> $db_sub->Record["task"],
                							"working_days"	=> $db_sub->Record["working_days"],
                							"developers"	=> $db_sub->Record["developers"],
                							"task_id"		=> $db_sub->Record["task_id"]
                						));
                		$project_title = "";
                		if ($db_sub->Record["parent_project"]<>"") {
                			$project_title = $db_sub->Record["parent_project"].($db_sub->Record["child_project"]?"<br>&nbsp;&nbsp;&nbsp;".$db_sub->Record["child_project"]:"");
                		}
                		else {
                			$project_title = $db_sub->Record["child_project"];
                		}
                		$t->set_var("project_title",$project_title);
                		$t->parse("clients",true);
					}
					$t->parse("ClientParent",true);
				}

			} else {
				$t->set_var("clients_header","");
				$t->set_var("clients","");
				$t->set_var("ClientParent","");
				$t->parse("no_records", false);
			}

			$t->parse("result", false);
		}
	} else {
		if ($sdt && $edt) {
    		$sql = "SELECT	c.client_id AS
    							client_id,
    						c.client_name AS
    							client_name,
    						c.client_company AS
    							company_name,
    						c.client_email AS
    							client_email,
    						CONCAT('<a href = \"mailto:{client_email}\" >' ,REPLACE(IF ((CHAR_LENGTH(c.client_email)>27) AND  (c.client_email <> ''), CONCAT(SUBSTRING(c.client_email, 1, 24), '...'), c.client_email), ' ', '&nbsp;'),  '</a>') AS
    							cutted_client_email,
    						COUNT(DISTINCT tr.task_id) AS
    							task_count,
    						SUM(tr.spent_hours) AS
    							total_time
					FROM	time_report tr
							RIGHT JOIN tasks t ON (tr.task_id=t.task_id AND DATE(tr.started_date) BETWEEN DATE('".$sdt."') AND DATE('".$edt."'))
							RIGHT JOIN clients c ON t.client_id=c.client_id
							LEFT JOIN projects prc ON prc.project_id=t.project_id
                            LEFT JOIN projects prp ON prp.project_id=prc.parent_project_id
							LEFT JOIN users u ON tr.user_id=u.user_id
					WHERE	1
							".$sqlsearch."
							AND t.client_id <> 0
							AND t.client_id IS NOT NULL
					GROUP BY c.client_email
					ORDER BY t.client_id";
			$db->query($sql,__FILE__,__LINE__);
			$t->set_var("ClientParent","");
			if ($db->num_rows()) {
				$t->set_var("no_records", "");
				while($db->next_record()){
					if ($db->Record["task_count"]==0) { continue;}
                    $total_time = floor($db->Record["total_time"]) . ":" . sprintf("%02d", round(($db->Record["total_time"] - floor($db->Record["total_time"])) * 60));
					$client_name = "";
					if ($db->Record["client_name"] <> "") {
						$client_name = (strlen($db->Record["client_name"])>27?substr($db->Record["client_name"],1,24)."...":$db->Record["client_name"]);
						$client_name = "<b>".$client_name."</b>";
					} else { $client_name = "<i>noname</i>";}
					$client_name = "<a href = \"create_client.php?client_id={client_id}\" >".$client_name."</a>";
                    $t->set_var(array(	"client_name"			=> $client_name,//($db->Record["client_name"]<>"noname"?"<b>".$db->Record["client_name"]."</b>":"<i>".$db->Record["client_name"]."</i>"),
                    					"client_email"			=> $db->Record["client_email"],
                    					"company_name"			=> $db->Record["company_name"],
                    					"cutted_client_email"	=> $db->Record["cutted_client_email"],
                    					"total_time"			=> "<b>".$total_time."</b>",
                    					"task_count"			=> $db->Record["task_count"],
                    					"client_id"				=> $db->Record["client_id"]
                    					//"" => $db->Record[""]
                    			));

					$sql_sub = "SELECT	tr.report_id AS
											report_id,
										t.task_id AS
											task_id,
										SUM(tr.spent_hours) AS
											spent_hours,
										t.task_title AS
											task_title,
										CONCAT('<a href = \"edit_task.php?task_id={task_id}\">' ,REPLACE(IF ((CHAR_LENGTH(t.task_title)>27) AND  (t.task_title <> ''), CONCAT(SUBSTRING(t.task_title, 1, 24), '...'), t.task_title), ' ', '&nbsp;'),  '</a>') AS
											task,
										t.completion AS
											percent,
										GROUP_CONCAT(DISTINCT (u.first_name) SEPARATOR ', ') AS
											developers,
										COUNT(DISTINCT DATE(tr.report_date)) AS
											working_days,
										IFNULL(REPLACE(IF((CHAR_LENGTH(prc.project_title)>27) AND  (prc.project_title <> ''), CONCAT(SUBSTRING(prc.project_title, 1, 24), '...'), prc.project_title), ' ', '&nbsp;'),'') AS
											child_project,
										IFNULL(REPLACE(IF((CHAR_LENGTH(prp.project_title)>27) AND  (prp.project_title <> ''), CONCAT(SUBSTRING(prp.project_title, 1, 24), '...'), prp.project_title), ' ', '&nbsp;'),'') AS
											parent_project
								FROM	time_report tr
										RIGHT JOIN tasks t ON t.task_id=tr.task_id
										RIGHT JOIN clients c ON c.client_id=t.client_id
										LEFT JOIN users u ON u.user_id=tr.user_id
										LEFT JOIN projects prc ON prc.project_id=t.project_id
										LEFT JOIN projects prp ON prp.project_id=prc.parent_project_id
								WHERE	1
										".$sqlteam.$sqluser."
										AND DATE(tr.started_date) BETWEEN CAST('".$sdt."' AS DATE) AND CAST('".$edt."' AS DATE)
										AND t.client_id =".$db->Record["client_id"]."
								GROUP BY tr.task_id
								ORDER BY tr.task_id
								LIMIT 0,1";
                	$db_sub->query($sql_sub,__FILE__,__LINE__);
                	$t->set_var("clients","");
                	while($db_sub->next_record()){
                		$spent_hours = floor($db_sub->Record["spent_hours"]) . ":" . sprintf("%02d", round(($db_sub->Record["spent_hours"] - floor($db_sub->Record["spent_hours"])) * 60));
                		$t->set_var(array(	"spent_hours"	=> $spent_hours,
                							"percent"		=> $db_sub->Record["percent"],
                							"task_title"	=> $db_sub->Record["task"],
                							"working_days"	=> $db_sub->Record["working_days"],
                							"developers"	=> $db_sub->Record["developers"],
                							"task_id"		=> $db_sub->Record["task_id"]
                						));
                		$project_title = "";
                		if ($db_sub->Record["parent_project"]<>"") {
                			$project_title = $db_sub->Record["parent_project"].($db_sub->Record["child_project"]?"<br>&nbsp;&nbsp;&nbsp;".$db_sub->Record["child_project"]:"");
                		}
                		else {
                			$project_title = $db_sub->Record["child_project"];
                		}
                		$t->set_var("project_title",$project_title);
                		$t->parse("clients",true);
					}
					$t->parse("ClientParent",true);
				}
                            //$t->parse("result", false);
			} else {				$t->set_var("clients_header","");
				$t->set_var("clients","");
				$t->set_var("ClientParent","");
				$t->parse("no_records", false);
			}

			$t->parse("result", false);
		//$t->set_var("result","");
		//$t->set_var("ClientParent","");
		//$t->parse("no_records", false);
    	}
    }
    /**/

	$t->set_var('display_person_tr', 'none');
	$t->set_var("action", 'clients_report.php');
	$t->pparse("main");

/**
* Functions
*/

function GetPeriodOption($period_selected)
{
	//$period_option=array("1","2","3","4","5","6","7","8","9");
	$period_option=array("today","yesterday","this_week","last_week","prev_week","this_month","last_month","prev_month","this_year");
	$period_titles=array("Today","Yesterday","This week","Last week (7 days)","Previous week","This month","Last month (30 days)","Previous month","This year");

	$res_str = "";
	for ($i = 0; $i < sizeof($period_option); $i++)	{
		if ($period_selected == $period_option[$i]) $selected = "selected"; else $selected = "";
		$res_str .= "<option $selected value=\"".$period_option[$i]."\">".$period_titles[$i]."</option>\n";
	}
	return $res_str;
}

function get_start_end_period($period_selected,&$start_date,&$end_date)
{
	global $t;

	$current_date = va_time();
	$cyear = $current_date[0];
	$cmonth = $current_date[1];
	$cday = $current_date[2];

	$today_date = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday, $cyear));
	$t->set_var("today_date", $today_date);

	$yesterday_date = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday - 1, $cyear));
	$t->set_var("yesterday_date", $yesterday_date);

	$this_week_start_date = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday - date("w")+1, $cyear));
	$this_week_end_date   = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday, $cyear));
	$t->set_var("this_week_start", $this_week_start_date);
	$t->set_var("this_week_end",   $this_week_end_date);

	$last_week_start_date = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday - 6, $cyear));
	$last_week_end_date   = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday, $cyear));
	$t->set_var("last_week_start", $last_week_start_date);
	$t->set_var("last_week_end",   $last_week_end_date);

	$prev_week_start_date = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday - date("w")-6, $cyear));
	$prev_week_end_date   = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday - date("w"), $cyear));
	$t->set_var("prev_week_start", $prev_week_start_date);
	$t->set_var("prev_week_end",   $prev_week_end_date);

	$prev_month_start_date = date ("Y-m-d", mktime (0, 0, 0, $cmonth - 1, 1, $cyear));
	$prev_month_end_date   = date ("Y-m-t", mktime (0, 0, 0, $cmonth - 1, 1, $cyear));
	$t->set_var("prev_month_start", $prev_month_start_date);
	$t->set_var("prev_month_end",   $prev_month_end_date);

	$last_month_start_date = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday-30, $cyear));
	$last_month_end_date   = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday, $cyear));
	$t->set_var("last_month_start", $last_month_start_date);
	$t->set_var("last_month_end",   $last_month_end_date);

	$this_month_start_date = date ("Y-m-d", mktime (0, 0, 0, $cmonth, 1, $cyear));
	$this_month_end_date   = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday, $cyear));
	$t->set_var("this_month_start", $this_month_start_date);
	$t->set_var("this_month_end",   $this_month_end_date);

	$year_start_date = date ("Y-m-d", mktime (0, 0, 0, 1, 1, $cyear));
	$year_end_date   = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday, $cyear));
	$t->set_var("this_year_start", $year_start_date);
	$t->set_var("this_year_end",   $year_end_date);

	if (!$period_selected) $period_selected="today";

	if (!$start_date && !$end_date) {
		switch ($period_selected) {
			case "today":
				$start_date = $today_date;
				$end_date = $today_date;
				break;
			case "yesterday":
				$start_date = $yesterday_date;
				$end_date = $yesterday_date;
				break;
			case "this_week":
				$start_date = $this_week_start_date;
				$end_date = $this_week_end_date;
				break;
			case "last_week":
				$start_date = $last_week_start_date;
				$end_date = $last_week_end_date;
				break;
			case "prev_week":
				$start_date = $prev_week_start_date;
				$end_date = $prev_week_end_date;
				break;
			case "this_month":
				$start_date = $this_month_start_date;
				$end_date = $this_month_end_date;
				break;
			case "last_month":
				$start_date = $last_month_start_date;
				$end_date = $last_month_end_date;
				break;
			case "prev_month":
				$start_date = $prev_month_start_date;
				$end_date = $prev_month_end_date;
				break;
			case "this_year":
				$start_date = $year_start_date;
				$end_date = $year_end_date;
				break;
			case "none":
				$start_date = $start_date;
				$end_date = $end_date;
				break;
		}
	}

	$sd = "";
	$ed = "";
	$sdt = "";
	$edt = "";
	if ($start_date) {
		$sd_ar = parse_date(array("YYYY", "-", "MM", "-", "DD"), $start_date, "Start Date");
		$sd_ts = mktime (0, 0, 0, $sd_ar[1], $sd_ar[2], $sd_ar[0]);
		$sdt_ts = mktime (0, 0, 0, $sd_ar[1], $sd_ar[2], $sd_ar[0]);
		$sd = @date("Y-m-d", $sd_ts);
		$sdt = @date("Y-m-d 00:00:00", $sd_ts);
	}
	if ($end_date) {
		$ed_ar = parse_date(array("YYYY", "-", "MM", "-", "DD"), $end_date, "End Date");
		$ed_ts = mktime (0, 0, 0, $ed_ar[1], $ed_ar[2], $ed_ar[0]);
		$ed = @date("Y-m-d", $ed_ts);
		$edt = @date("Y-m-d 23:59:59", $ed_ts);
 	}

 	$t->set_var("start_date", $sd);
	$t->set_var("end_date", $ed);
    /*
	$end_year  =@date("Y",$ed_ts);
	$start_year=@date("m",$ed_ts);
 	$t->set_var("current_year", $end_year);
	$t->set_var("current_month", $start_year);
    */
	return array($sdt,$edt);
}

function once_query($sql, $return_result = false) {
	global $db;
		
	$db_temp = new DB_Sql();
	$db_temp->Database   = $db->Database;
	$db_temp->User       = $db->User;
	$db_temp->Password   = $db->Password;
	$db_temp->Host       = $db->Host;

	$db_temp->query($sql);
	if (!$return_result){
		if ($db_temp->Error) {
			return $db_temp->Error;
		} else  {
			return "";
		}
	} else {
		if ($db_temp->next_record()) {
			return $db_temp->f(0);
		} else {
			return "";
		}
	}
	unset($db_temp);
}

function getPageLink($i)
{
	global $_SERVER;
	global $page_num, $records_per_page;
	global $return_special_hyperlinks;
	global $search;
	global $period_selected, $start_date, $end_date;

	$s = preg_replace('/&page_num=\d+/', '', $search);
	$s .= '&page_num='.$i;
	$s .= '&records_per_page='.$records_per_page;

	if (($start_date && $end_date) && ($period_selected == 'none'))
		$s .= "&start_date=$start_date&end_date=$end_date";

	if ($i == $page_num) {
		return $i;
	} elseif ($return_special_hyperlinks) {
		return '<a onclick="get_clients_table(\''.$s.'\')" href="#">'.$i.'</a>';
	} else {
		return '<a href=\'clients_report.php?'.$s.'\'>'.$i.'</a>';
	}

}

function getPageLinks($page_col, $records_per_page, $page_num, $query, $period_selected, $start_date, $end_date)
{
	if ($page_col == 1) return '';
	$html_result = '';

	if ($page_col < 20)
	{
		for ($i = 1; $i <= $page_col; $i++) $html_result .= ' '.getPageLink($i);
		return $html_result;
	}
	if ($page_num <= 8)
	{
		for ($i = 1; $i <= max($page_num + 2, 5); $i++) $html_result .= ' '.getPageLink($i);
		$html_result .= ' ... ';
		for ($i = $page_col - 4; $i <= $page_col; $i++) $html_result .= ' '.getPageLink($i);
	}
	elseif (($page_num > 8) && ($page_num <= $page_col - 8))
	{
		for ($i = 1; $i <= 5; $i++) $html_result .= ' '.getPageLink($i);
		$html_result .= ' ... ';
		for ($i = $page_num - 2; $i <= $page_num+2; $i++) $html_result .= ' '.getPageLink($i);
		$html_result .= ' ... ';
		for ($i = $page_col-4; $i <= $page_col; $i++) $html_result .= ' '.getPageLink($i);
	}
	elseif ($page_num > $page_col - 7)
	{
		for ($i = 1; $i <= 5; $i++) $html_result .= ' '.getPageLink($i);
		$html_result .= ' ... ';
		for ($i = $page_num - 2; $i <= $page_col; $i++) $html_result .= ' '.getPageLink($i);
	}
	
	return $html_result;
}
?>