<?php
	include("./includes/date_functions.php");
	include("./includes/common.php");
	include_once("./includes/Lite.php");

	CheckSecurity(1);

	$db_sub = new DB_Sql();
	$db_sub->Database = DATABASE_NAME;
	$db_sub->User     = DATABASE_USER;
	$db_sub->Password = DATABASE_PASSWORD;
	$db_sub->Host     = DATABASE_HOST;
	$db_users = new DB_Sql();
	$db_users->Database = DATABASE_NAME;
	$db_users->User     = DATABASE_USER;
	$db_users->Password = DATABASE_PASSWORD;
	$db_users->Host     = DATABASE_HOST;

	$project_selected 	= (int)GetParam("project_selected");
	$period_selected	= GetParam("period_selected");
	$person_selected	= (int)GetParam("person_selected");
	$action			= GetParam("action");
	$start_date		= GetParam("start_date");
	$end_date		= GetParam("end_date");
	$submit			= GetParam("submit");
	$projects		= GetParam("projects");
	$sub_projects	= GetParam("sub_projects");
	//team select block
	$team = GetParam("team");
	$multiplier = GetParam("multiplier");	

	/**/
	$options = array(
		'cacheDir' => './cache/',
		'lifeTime' => 3600
	);
	$Cache_Lite = new Cache_Lite($options);
	if (file_exists($Cache_Lite->_cacheDir)) {
		echo "<!--\r\n Exist";
		echo "-->\r\n";
	} else {
		echo "<!--\r\nNot Found";
		echo "-->\r\n";
	}
	/**/
	
	$vs = ""; $as = ""; $ys = "";
	switch (strtolower($team)) {
	  case "all":	$sqlteam=""; $as="selected"; break;
	  case "viart":	$sqlteam=" AND u.is_viart=1 "; $vs="selected"; break;
	  case "yoonoo":$sqlteam=" AND u.is_viart=0 "; $ys="selected"; break;
	  default:	$sqlteam=" AND u.is_viart=1 "; $vs="selected"; $team="viart";
	}

	if (floatval($multiplier)==0 || floatval($multiplier)<=0 ) {
		$multiplier = 1;
	}
	
	$sqlproject = "";
	if ($projects>0) {
		$sqlproject .= " AND pp.project_id=" . ToSQL($projects,"integer");
	}
	$sqlsub_project = "";
	if ($sub_projects>0) {
		$sqlsub_project .= "AND p.project_id=" . ToSQL($sub_projects,"integer");
	}
	
	$t = new iTemplate($sAppPath);
	$t->set_file("main","projects_report_new.html");
	
	$filter_project = GetOptions("projects", "project_id", "project_title", $projects, "WHERE parent_project_id IS NULL AND is_closed=0");
	$t->set_var("filter_project",$filter_project);
	$t->set_var("filter_sub_project","");
	if ($projects>0) {
		$filter_sub_project = GetOptions("projects", "project_id", "project_title", $sub_projects, "WHERE parent_project_id=" . ToSQL($projects,"integer"));
		$t->set_var("filter_sub_project",$filter_sub_project);
	}
	
	$sql  =	" SELECT parent_project_id, project_id, project_title FROM projects ";
	$sql .=	" WHERE parent_project_id IS NOT NULL AND is_closed=0 ORDER BY parent_project_id, project_title";
   	$db->query($sql,__FILE__,__LINE__);
	$parent_id=0;
   	if ($db->num_rows()) {
		$i = 0;
   		while ($db->next_record()) {
    		$i++;
	  		$t->set_var("IDparent",$db->f(0));
	  		$t->set_var("IDchild",$db->f(1));
	  		if ($parent_id!=$db->f(0)) {
	  			$i = 1;
	  			$t->parse("SubProjectParent",false);
	  		} else {
	  			$t->set_var("SubProjectParent","");
	  		}
	  		$t->set_var("I", $i);
	  		$t->set_var("subproject_title",addslashes($db->f(2)));
	  		$t->parse("SubProjectArray",true);
	  		$parent_id=$db->f(0);
	  	}
	} else {
		$t->set_var("SubProjectArray","");
	}

	$t->set_var("periods", GetPeriodOptions($period_selected ? $period_selected : "this_week"));

	// make default this year and month for javascript
	$t->set_var("thisyear", date("Y")-2004+1);
	$t->set_var("thismonth", date("m"));
	$t->set_var("multiplier", $multiplier);
	$t->set_var("aselected",$as); $t->set_var("vselected",$vs); $t->set_var("yselected",$ys); $t->set_var("team_selected",$team);
	$t->set_var("period_selected", $period_selected);

	list($sdt,$edt)=get_start_end_period ($period_selected,$start_date,$end_date);

	$t->set_var("year_selected",  (isset($year_selected) && $year_selected>2000 ? $year_selected : date("Y")));
	$t->set_var("month_selected", (isset($month_selected) && $month_selected>0 ? $month_selected : date("m")));

	if ($submit) {
		if ($start_date && $end_date) {

			$sql = "SELECT CONCAT(first_name,' ',last_name) AS user_name FROM users WHERE user_id=".$person_selected;
			$db->query($sql);
			$db->next_record();
			$user_name=$db->f("user_name");

			$sql = "SELECT SUM(tr.spent_hours) AS count_hours, COUNT(DISTINCT tr.task_id) AS count_tasks, ";
			$sql.= "COUNT(DISTINCT(YEAR(tr.started_date)*372 + MONTH(tr.started_date)*31+DAYOFMONTH(tr.started_date))) AS working_days ";
			$sqlfrom = " FROM projects p,time_report tr,tasks t, users u ";
			$sqlwhere = " WHERE t.project_id = p.project_id AND t.task_id=tr.task_id AND tr.user_id = u.user_id $sqlteam ";
			$trlimits = "";
			if ($sdt) $trlimits .= " AND tr.started_date>='$sdt' ";
			if ($edt) $trlimits .= " AND tr.started_date<='$edt' ";
			$sqlwhere.=$trlimits;

			$sqlfromwhere=$sqlfrom.$sqlwhere;
			$sqlfwjoin = " FROM projects p LEFT JOIN projects subp ON (subp.parent_project_id=p.project_id OR subp.project_id=p.project_id),
								time_report tr,
								tasks t,
								users u
						   WHERE t.project_id=subp.project_id AND
						   		 t.task_id=tr.task_id AND
						   		 tr.user_id = u.user_id" . $sqlteam  . $trlimits;

			$sql.= $sqlfromwhere;
			if ($cache_data = unserialize($Cache_Lite->get($sql))) {
				$total_hours = $cache_data["count_hours"];
				$total_tasks = $cache_data["count_tasks"];
				$total_working_days = $cache_data["working_days"];
			} else {
				$db->query($sql);
				if ($db->next_record()) {
				  $total_hours = $db->Record["count_hours"];
				  $total_tasks = $db->Record["count_tasks"];
				  $total_working_days = $db->Record["working_days"];
				  $cache_data["count_hours"] = $db->Record["count_hours"];
				  $cache_data["count_tasks"] = $db->Record["count_tasks"];
				  $cache_data["working_days"] = $db->Record["working_days"];
				}
				$Cache_Lite->save(serialize($cache_data));
			}

			// count working days on project and for each person queries;
			$wd = array();
			$sql  = " SELECT t.project_id AS proj_id, ";
			$sql .= " COUNT(DISTINCT(YEAR(tr.started_date)*372 + MONTH(tr.started_date)*31+DAYOFMONTH(tr.started_date))) AS working_days";
			$sql .= " FROM time_report tr, tasks t, users u, (projects pp ";
			$sql .= " LEFT JOIN projects p ON (p.parent_project_id=pp.project_id OR p.project_id=pp.project_id))";
			$sql .= " WHERE t.task_id=tr.task_id AND t.project_id=p.project_id AND tr.user_id=u.user_id ".$sqlteam.$trlimits;
			$sql .= (strlen($sqlsub_project)?$sqlsub_project:$sqlproject);
			$sql .= " GROUP BY t.project_id ORDER BY t.project_id ";
			if ($cache_data = unserialize($Cache_Lite->get($sql))) {
				$wd = $cache_data;
			} else {
				$db->query($sql);
				while($db->next_record()) {
					$proj_id=(int)$db->Record["proj_id"];
					$wd[$proj_id]=(int)$db->Record["working_days"];
				}
				$Cache_Lite->save(serialize($wd));
			}
			
			// count working days for each person and project
			$wdp = array();
			$sql = "SELECT p.project_id AS proj_id, tr.user_id AS usr_id, ";
			$sql.= "COUNT(DISTINCT(YEAR(tr.started_date)*372 + MONTH(tr.started_date)*31+DAYOFMONTH(tr.started_date))) AS working_days ";
			$sql.= "FROM time_report tr, tasks t, users u, projects pp ";
			$sql.= "LEFT JOIN projects p ON (p.parent_project_id=pp.project_id OR p.project_id=pp.project_id) ";
			$sql.= "WHERE t.task_id=tr.task_id ";
			$sql.= "AND t.project_id=p.project_id ";//" AND p.parent_project_id IS NULL ";
			$sql.= "AND tr.user_id=u.user_id ".$sqlteam.$trlimits;
			$sql.= " GROUP BY p.project_id, tr.user_id ";
			if ($cache_data = unserialize($Cache_Lite->get($sql))) {
				$wdp = $cache_data;
			} else {
				$db->query($sql);
				while($db->next_record()) {
					$proj_id=(int)$db->Record["proj_id"];
					$usr_id =(int)$db->Record["usr_id"];
					$wdp[$proj_id][$usr_id]=(int)$db->Record["working_days"];
				}
				$Cache_Lite->save(serialize($wdp));
			}

			// count total working days for each person
			$wdu = array();
			$sql  = " SELECT tr.user_id AS usr_id, ";
			$sql .= " COUNT(DISTINCT(YEAR(tr.started_date)*372 + MONTH(tr.started_date)*31+DAYOFMONTH(tr.started_date))) AS working_days";
			$sql .= " FROM time_report tr, tasks t, users u, projects pp ";
			$sql .= " LEFT JOIN projects p ON (p.parent_project_id=pp.project_id OR p.project_id=pp.project_id) ";
			$sql .= " WHERE t.task_id=tr.task_id ";
			$sql .= " AND t.project_id=p.project_id AND tr.user_id=u.user_id".$sqlteam.$trlimits;
			$sql .= " GROUP BY tr.user_id ";
			if ($cache_data = unserialize($Cache_Lite->get($sql))) {
				$wdu = $cache_data;
			} else {
				$db->query($sql);
				while($db->next_record()) {
					$usr_id =(int)$db->Record["usr_id"];
					$wdu[$usr_id]=(int)$db->Record["working_days"];
				}
				$Cache_Lite->save(serialize($wdu));
			}

			// count average reaction time for each project
			$rh = array();
			$sql = "SELECT pid, AVG(reaction_hours) AS a_reaction_hours FROM ( ";
			$sql.= "SELECT p.project_id AS pid, ";
			$sql.= "(UNIX_TIMESTAMP(MAX(tr.report_date))-UNIX_TIMESTAMP(t.creation_date))/3600 AS reaction_hours ";
			$sql.= "FROM time_report tr, tasks t, users u, projects p ";
			$sql.= "LEFT JOIN projects subp ON (subp.parent_project_id = p.project_id OR subp.project_id=p.project_id) ";
			$sql.= "WHERE t.task_id = tr.task_id AND t.project_id = subp.project_id ";
			$sql.= "AND tr.user_id = u.user_id ".$sqlteam.$trlimits;
			$sql.= " AND t.task_status_id =4 GROUP BY p.project_id, tr.task_id ) AS tx GROUP BY pid";
			if ($cache_data = unserialize($Cache_Lite->get($sql))) {
				$rh = $cache_data;
			} else {
				$db->query($sql);
				while($db->next_record()) {
					$proj_id=(int)$db->Record["pid"];
					$rh[$proj_id]=(float)$db->Record["a_reaction_hours"];
				}
				$Cache_Lite->save(serialize($rh));
			}
			
			// count average reaction time for each project and each person
			$rhp = array();
			$sql = "SELECT pid, uid, AVG(reaction_hours) AS a_u_reaction_hours FROM ( ";
			$sql.= "SELECT p.project_id AS pid, u.user_id AS uid, ";
			$sql.= "(UNIX_TIMESTAMP(MAX(tr.report_date))-UNIX_TIMESTAMP(t.creation_date))/3600 AS reaction_hours ";
			$sql.= "FROM time_report tr, tasks t, users u, projects p ";
			$sql.= "LEFT JOIN projects subp ON (subp.parent_project_id = p.project_id OR subp.project_id=p.project_id) ";
			$sql.= "WHERE t.task_id = tr.task_id AND t.project_id = subp.project_id ";
			$sql.= "AND tr.user_id = u.user_id ".$sqlteam.$trlimits;
			$sql.= " AND t.task_status_id =4 GROUP BY pid, tr.task_id, u.user_id ) AS tx GROUP BY uid,pid";
			if ($cache_data = unserialize($Cache_Lite->get($sql))) {
				$rhp = $cache_data;
			} else {
				$db->query($sql);
				while($db->next_record()) {
					$proj_id=(int)$db->Record["pid"];
					$user_id=(int)$db->Record["uid"];
					$rhp[$proj_id][$user_id]=(float)$db->Record["a_u_reaction_hours"];
				}
				$Cache_Lite->save(serialize($rhp));
			}

			//MAIN query (parent projects)
			$sql = " SELECT pp.project_id, pp.project_title, IF (p.parent_project_id, p.parent_project_id, p.project_id) AS groupparent,";
			$sql.= " SUM(tr.spent_hours) AS count_hours, COUNT(DISTINCT tr.task_id) AS count_tasks ";
			$sql.= " FROM projects p, time_report tr, tasks t, users u, projects pp ";
			$sql.= " WHERE t.project_id = p.project_id AND IF (p.parent_project_id, p.parent_project_id, p.project_id)=pp.project_id ";
			$sql.= " AND tr.user_id=u.user_id ".$sqlteam." AND t.task_id = tr.task_id " . $sqlproject;
			$sql .= $trlimits;
			$sql.= " GROUP BY groupparent ";
			$sql.= " ORDER BY count_hours DESC";

			if ($cache_data = unserialize($Cache_Lite->get($sql))) {
				$records_result = $cache_data;
			} else {
				$records_result = array();
				$db->query($sql);
				while($db->next_record()){
					$records_result[] = $db->Record;
				}
				$Cache_Lite->save(serialize($records_result));
			}

			$t->set_var("project_report_title", "");

			if (sizeof($records_result))
			{
				$t->set_var("no_records", "");
				foreach($records_result as $record_result)
				{
					$project_title = $record_result["project_title"];
					$project_id  = $record_result["project_id"];
					$count_hours = $record_result["count_hours"];
					$count_tasks = $record_result["count_tasks"];
					
					$tasks_per_day = (isset($wd[$project_id]) && $wd[$project_id]>0) ? $count_tasks/$wd[$project_id] : 0;

					$t->set_var("project_title", $project_title);

					if ($project_id==$project_selected)
					{
					  $t->set_var("project_report_title", $project_title);
					  $t->set_var("ifselected", "Yellow");
					} else {
					  $t->set_var("ifselected", "");
					}

					$t->set_var("project_id", $project_id);
					$t->set_var("projects",$projects);
					$t->set_var("sub_projects",$sub_projects);
					$t->set_var("spent_hours", Hours2HoursMins($count_hours));
					$t->set_var("spent_hours_multiplied", number_format($count_hours * $multiplier, 2));
					$t->set_var("hours_percents", (($total_hours>0.0001) ? sprintf("%3.1f",$count_hours/$total_hours*100) : "0.0" ) );
					$t->set_var("tasks", $count_tasks);
					$t->set_var("time_per_task",$count_tasks>0 ? Hours2HoursMins($count_hours/$count_tasks) : "");
					$t->set_var("working_days", (isset($wd[$project_id]) && $wd[$project_id]>0 ? $wd[$project_id] : ""));
					$t->set_var("hours_per_day",(isset($wd[$project_id]) && $wd[$project_id]>0 ? Hours2HoursMins($count_hours/$wd[$project_id]) : ""));
					$t->set_var("tasks_per_day",sprintf("%3.1f",$tasks_per_day));
					$temp = "";
					if (array_key_exists($project_id,$rh)) { $temp = reaction_output($rh[$project_id]);}
					$t->set_var("reaction_time",$temp);
					$t->set_var("users_list",userlist($sqlfwjoin,$project_id,$start_date,$end_date,$period_selected,true));

					$sqlsub  = " SELECT p.project_id, p.project_title, IF (p.parent_project_id, p.parent_project_id, p.project_id) AS groupparent,";
					$sqlsub .= " SUM(tr.spent_hours) AS count_hours, COUNT(DISTINCT tr.task_id) AS count_tasks ";
					$sqlsub .= " FROM projects p, time_report tr, tasks t, users u ";
					$sqlsub .= " WHERE t.project_id = p.project_id AND tr.task_id=t.task_id ".$trlimits;
					$sqlsub .= " AND tr.user_id=u.user_id ".$sqlteam;
					$sqlsub .= " AND p.parent_project_id=$project_id ";
					$sqlsub .= $sqlsub_project;
					$sqlsub .= " GROUP BY p.project_id ORDER BY count_hours DESC";
					
					if ($cache_data = unserialize($Cache_Lite->get($sqlsub))) {
						$sub_records_result = $cache_data;
					} else {
						$sub_records_result = array();
						$db_sub->query($sqlsub);
						while($db_sub->next_record()){
							$sub_records_result[] = $db_sub->Record;
						}
						$Cache_Lite->save(serialize($sub_records_result));
					}

					$t->set_var("records","");
					foreach ($sub_records_result as $sub_record_result)
					{
						$sub_project_title = $sub_record_result["project_title"];
						$sub_project_id  = $sub_record_result["project_id"];
						$sub_count_hours = $sub_record_result["count_hours"];
						$sub_count_tasks = $sub_record_result["count_tasks"];
						$sub_tasks_per_day = $wd[$sub_project_id]>0 ? $sub_count_tasks/$wd[$sub_project_id] : 0;

						$t->set_var("sproject_title", $sub_project_title);

						if ($sub_project_id==$project_selected)
						{
						  $t->set_var("project_report_title", $sub_project_title);
						  $t->set_var("sifselected", "PYellow");
						} else {
						  $t->set_var("sifselected", "");
						}

						$t->set_var("sproject_id", $sub_project_id);
						$t->set_var("projects",$projects);
						$t->set_var("sub_projects",$sub_projects);
						$t->set_var("sspent_hours", Hours2HoursMins($sub_count_hours));
						$t->set_var("sspent_hours_multiplied", number_format($sub_count_hours * $multiplier, 2));
						$t->set_var("shours_percents", (($total_hours>0.0001) ? sprintf("%3.1f",$sub_count_hours/$total_hours*100) : "0.0" ) );
						$t->set_var("stasks", $sub_count_tasks);
						$t->set_var("stime_per_task",$sub_count_tasks>0 ? Hours2HoursMins($sub_count_hours/$sub_count_tasks) : "");
						$t->set_var("sworking_days",$wd[$sub_project_id]);
						$t->set_var("shours_per_day",$wd[$sub_project_id]>0 ? Hours2HoursMins($sub_count_hours/$wd[$sub_project_id]) : "");
						$t->set_var("stasks_per_day",sprintf("%3.1f",$sub_tasks_per_day));
						
						$sreaction_time = (isset($rh[$sub_project_id]) ? reaction_output($rh[$sub_project_id]) : "");
						
						$t->set_var("sreaction_time", $sreaction_time);
						$t->set_var("susers_list",userlist($sqlfwjoin,$sub_project_id,$start_date,$end_date,$period_selected,true));
						$t->parse("records", true);
					}

					$t->parse("records_parent", true);
				}
			}
			else
			{
				$t->set_var("records_header", "");
				$t->set_var("records_parent", "");
				$t->set_var("records", "");
				$t->set_var("records_footer", "");
				$t->parse("no_records", false);
			} //if ($db->next_record())

			//TOTAL TIME, TASKS
			$t->set_var("total_tasks",$total_tasks);
			$t->set_var("total_hours",Hours2HoursMins($total_hours));
			$t->set_var("total_hours_multiplied",number_format($total_hours*$multiplier,2));
			$t->set_var("total_working_days",$total_working_days);
			$t->set_var("mh_per_day",$total_working_days>0 ? Hours2HoursMins($total_hours/$total_working_days) : "");
			$t->set_var("av_tasks_per_day",$total_working_days>0 ? sprintf("%3.1f",$total_tasks/$total_working_days) : "");
			$t->set_var("av_time_per_task",$total_tasks>0 ? Hours2HoursMins($total_hours/$total_tasks) : "");

			$t->parse("result", false);
			////////////////////////////////////////////////////////////
			////////// developers report //////////////////////////////

			if (isset($person_selected) /*&& $person_selected=="all"*/ && isset($project_selected) && $project_selected>0 && is_int($project_selected) && ($action=="dev" || $action=="devtasks"))
			{
				$sql = "SELECT u.user_id AS uid, CONCAT(first_name,' ',last_name) AS user_name, SUM(tr.spent_hours) AS count_hours, COUNT(DISTINCT(tasks.task_id)) AS count_tasks ";
				$sql.= "FROM users u, tasks, time_report AS tr, projects AS p LEFT JOIN projects AS subp ON (subp.parent_project_id=p.project_id OR subp.project_id=p.project_id) ";
				$sql.= "WHERE p.project_id=$project_selected AND tr.user_id=u.user_id AND tasks.task_id=tr.task_id ";
				$sql.= "AND subp.project_id=tasks.project_id ".$sqlteam.$trlimits;
				$sql.= " GROUP BY u.user_id ORDER BY user_name ASC";

				if ($cache_data = unserialize($Cache_Lite->get($sql))) {
					$records_result = $cache_data;
				} else {
					$records_result = array();
					$db->query($sql);
					while($db->next_record()){
						$records_result[] = $db->Record;
					}
					$Cache_Lite->save(serialize($records_result));
				}

				$t->set_var("dev_project_title",$project_title);

				if (sizeof($records_result)) {
				
					$t->set_var("dev_no_records","");
					$t->set_var("dev_project_id",$project_selected);
					$t->parse("dev_header",false);
					foreach ($records_result as $record_result) {
						$dev_spent_hours = $record_result["count_hours"];
						$dev_count_tasks = $record_result["count_tasks"];
						$dev_user_id = $record_result["uid"];
						$t->set_var("dev_name",$record_result["user_name"]);
						$t->set_var("dev_user_id",$dev_user_id);
						$t->set_var("dev_spent_hours",Hours2HoursMins($dev_spent_hours));
						$t->set_var("dev_spent_hours_multiplied",number_format($dev_spent_hours*$multiplier,2)); 
						$t->set_var("dev_count_tasks",$dev_count_tasks);
						$t->set_var("dev_time_per_task",$dev_count_tasks>0 ? Hours2HoursMins($dev_spent_hours/$dev_count_tasks) : "");
						$t->set_var("dev_working_days",$wdp[$project_selected][$dev_user_id]);
						$t->set_var("dev_hours_per_day",$wdp[$project_selected][$dev_user_id]>0 ? Hours2HoursMins($dev_spent_hours/$wdp[$project_selected][$dev_user_id]) : "");
						$t->set_var("dev_tasks_per_day",$wdp[$project_selected][$dev_user_id]>0 ? sprintf("%3.1f",$dev_count_tasks/$wdp[$project_selected][$dev_user_id]) : "");
						$t->set_var("dev_reaction_time",isset($rhp[$project_selected][$dev_user_id]) ? reaction_output($rhp[$project_selected][$dev_user_id]) : "");
						$t->set_var("dev_selected", $person_selected==$dev_user_id ? "PYellow" : "");

						$t->parse("dev_records",true);
			    	}
				} else {
					$t->set_var("dev_header","");
					$t->set_var("dev_records","");
					$t->parse("dev_no_records",false);
				}

				$t->parse("dev_result",false);
			}
			else {
			   $t->set_var("dev_result","");
			}

			///////////////////////////////////////////////////////////
			//////////// check if person selected /////////////////////
			if (isset($person_selected) && $person_selected>0 && is_int($person_selected)) $ps=true; else $ps=false;
			//////////// PROJECTS REPORT FOR PERSON ///////////////////
			///////////////////////////////////////////////////////////
			if (isset($person_selected) && $person_selected>0 && is_int($person_selected) && $action=="projects")
			{
				$sql = "SELECT SUM(tr.spent_hours) AS count_hours, COUNT(DISTINCT tr.task_id) AS count_tasks ";
				$sql.= " FROM projects p, time_report tr, tasks t ";
				$sql.= " WHERE t.project_id = p.project_id AND t.task_id=tr.task_id AND tr.user_id = $person_selected ";
				$sql.= $trlimits;
				
				$db->query($sql);
				if ($db->next_record()) {
				  $person_total_hours = $db->Record["count_hours"];
				  $person_total_tasks = $db->Record["count_tasks"];
				  $person_av_time_per_task = $person_total_tasks>0 ? $person_total_hours/$person_total_tasks : 0;
				  $person_av_hours_per_day = 0;
				}

				$t->set_var("user_name",$user_name);
				$t->set_var("pperson_id",$person_selected);

				$sql = " SELECT pp.project_id, pp.project_title, IF (p.parent_project_id, p.parent_project_id, p.project_id) AS groupparent,";
				$sql.= " SUM(tr.spent_hours) AS count_hours, COUNT(DISTINCT tr.task_id) AS count_tasks ";
				$sql.= " FROM projects p, time_report tr, tasks t, projects pp ";
				$sql.= " WHERE t.project_id = p.project_id AND t.task_id = tr.task_id ".$trlimits;
				$sql.= " AND IF(p.parent_project_id, p.parent_project_id, p.project_id)=pp.project_id ";
				$sql.= " AND tr.user_id = $person_selected";
				$sql.= " GROUP BY groupparent ";
				$sql.= " ORDER BY count_hours DESC";

				$db->query($sql);

				if ($db->num_rows())
				{
					//$t->set_var("person_no_records", "");
					$t->parse("person_records_header", false);

					while($db->next_record())
					{
						$pproject_id  = $db->f("project_id");
						$pcount_hours = $db->f("count_hours");
						$pcount_tasks = $db->f("count_tasks");

						$t->set_var("pproject_title", $db->f("project_title"));

						if ($pproject_id==$project_selected)
						{
						  $t->set_var("project_report_title", $db->f("project_title"));
						  $t->set_var("pifselected", "Yellow");
						} else {
						  $t->set_var("pifselected", "");
						}

						$t->set_var("project_id", $pproject_id);
						$t->set_var("pspent_hours", Hours2HoursMins($pcount_hours));
						$t->set_var("pspent_hours_multiplied",number_format($pcount_hours*$multiplier,2));
						$t->set_var("phours_percents", (($person_total_hours>0.0001) ? sprintf("%3.1f",$pcount_hours/$person_total_hours*100) : "0.0" ) );
						$t->set_var("ptime_per_task", $pcount_tasks>0 ? Hours2HoursMins($pcount_hours/$pcount_tasks) : "");
						$t->set_var("pworking_days", $wdp[$pproject_id][$person_selected]);
						$t->set_var("phours_per_day",$wdp[$pproject_id][$person_selected]>0 ? Hours2HoursMins($pcount_hours/$wdp[$pproject_id][$person_selected]) : "");
						$t->set_var("ptasks_per_day",sprintf("%3.1f",$wdp[$pproject_id][$person_selected]>0 ? $pcount_tasks/$wdp[$pproject_id][$person_selected] : 0));
						$t->set_var("preaction_time",isset($rhp[$pproject_id][$person_selected]) ? reaction_output($rhp[$pproject_id][$person_selected]) : "");
						$t->set_var("ptasks", $pcount_tasks);

						$sqlsub = " SELECT p.project_id, p.project_title, IF (p.parent_project_id, p.parent_project_id, p.project_id) AS groupparent,";
						$sqlsub.= " SUM(tr.spent_hours) AS count_hours, COUNT(DISTINCT tr.task_id) AS count_tasks ";
						$sqlsub.= " FROM projects p, time_report tr, tasks t, users u ".$sqlwhere;
						$sqlsub.= " AND p.parent_project_id=$pproject_id ";
						$sqlsub.= " AND tr.user_id = $person_selected";
						$sqlsub.= " GROUP BY p.project_id ORDER BY count_hours DESC";
						$db_sub->query($sqlsub);

						$t->set_var("person_records","");
						while ($db_sub->next_record())
						{
							$sub_project_title = $db_sub->f("project_title");
							$sub_project_id  = $db_sub->f("project_id");
							$sub_count_hours = $db_sub->f("count_hours");
							$sub_count_tasks = $db_sub->f("count_tasks");

							$t->set_var("psproject_title", $sub_project_title);

							if ($sub_project_id==$project_selected)
							{
							  $t->set_var("project_report_title", $sub_project_title);
							  $t->set_var("psifselected", "PYellow");
							} else {
							  $t->set_var("psifselected", "");
							}

							$t->set_var("pproject_id", $sub_project_id);
							$t->set_var("psspent_hours", Hours2HoursMins($sub_count_hours));
							$t->set_var("psspent_hours_multiplied",number_format($sub_count_hours*$multiplier,2));
							$t->set_var("pshours_percents", (($person_total_hours>0.0001) ? sprintf("%3.1f",$sub_count_hours/$person_total_hours*100) : "0.0" ) );
							$t->set_var("pstime_per_task", $sub_count_tasks>0 ? Hours2HoursMins($sub_count_hours/$sub_count_tasks) : "");
							$t->set_var("psworking_days", $wdp[$sub_project_id][$person_selected]);

							$t->set_var("pshours_per_day",$wdp[$sub_project_id][$person_selected]>0 ? Hours2HoursMins($sub_count_hours/$wdp[$sub_project_id][$person_selected]) : "");
							$t->set_var("pstasks_per_day",sprintf("%3.1f",$wdp[$sub_project_id][$person_selected]>0 ? $sub_count_tasks/$wdp[$sub_project_id][$person_selected] : 0));
							$t->set_var("psreaction_time",isset($rhp[$sub_project_id][$person_selected]) ? reaction_output($rhp[$sub_project_id][$person_selected]) : "");

							$t->set_var("pstasks", $sub_count_tasks);
							$t->set_var("psusers_list",userlist($sqlfromwhere,$sub_project_id,$start_date,$end_date,$period_selected,false));
							$t->parse("person_records", true);
						}

						$t->parse("person_records_parent", true);
					}
				  $t->set_var("person_no_records","");
				  $t->set_var("person_total_hours",Hours2HoursMins($person_total_hours));
				  $t->set_var("person_total_hours_multiplied",number_format($person_total_hours*$multiplier,2));
				  $t->set_var("person_total_tasks",$person_total_tasks);
				  $t->set_var("person_av_time_per_task",Hours2HoursMins($person_av_time_per_task));
				  $t->set_var("person_ov_working_days", $wdu[$person_selected]);
				  $t->set_var("person_av_hours_per_day",$wdu[$person_selected] ? Hours2HoursMins($person_total_hours/$wdu[$person_selected]) : "");
				  $t->set_var("person_av_tasks_per_day",$wdu[$person_selected] ? sprintf("%3.1f",$person_total_tasks/$wdu[$person_selected]) : "");
				  $t->parse("person_records_footer",false);
				} else {
					$t->set_var("person_records_header", "");
					$t->set_var("person_records", "");
					$t->set_var("person_records_footer", "");
					$t->parse("person_no_records",false);
				}

			  $t->parse("result_person",true);
			}
			else
			{	//no person selected
				$t->set_var("result_person","");

			}

			///////////////////////////////////////////////////////////
			//////////// check if any project selected ////////////////
			///// TASKS REPORT ////////////////////////////////////////
			///////////////////////////////////////////////////////////
			if (isset($project_selected) && $project_selected>0 && is_int($project_selected) && ($action=="tasks" || $action=="projects" || $action=="devtasks")
			/*&& !(isset($person_selected)&&$person_selected=="all" )*/ )
			{
				$t->set_var("person_report", $ps ? ": ".$user_name : "");

/*			  $sql="SELECT t.task_id AS task_identifier, t.task_title, SUM(tr.spent_hours) AS count_hours, lts.status_desc AS status_description, first_name ";
			  $sql.= $sqlfrom.", lookup_tasks_statuses lts ";
			  $sql.=" LEFT JOIN projects subp ON (subp.parent_project_id=p.project_id OR subp.project_id=p.project_id) ";
			  $sql.=" WHERE t.project_id=subp.project_id ";
			  $sql.=" AND t.task_id=tr.task_id AND tr.user_id=u.user_id ".$sqlteam.$trlimits;
			  $sql.=" AND lts.status_id=t.task_status_id AND p.project_id=".$project_selected;
			  if ($ps) $sql.=" AND tr.user_id=$person_selected ";
			  $sql.= " GROUP BY t.task_id,p.project_id ORDER BY count_hours DESC";
*/


				$sql = " SELECT t.task_id AS task_identifier, t.task_title, SUM(tr.spent_hours) AS count_hours, lts.status_desc AS status_description, first_name ";
				$sql.= " FROM (projects p LEFT JOIN projects subp ON (subp.parent_project_id=p.project_id)) ";
				$sql.= " INNER JOIN tasks t ON (t.project_id = p.project_id) ";
				$sql.= " INNER JOIN time_report tr ON (t.task_id = tr.task_id ".$trlimits;
				if ($ps) { $sql.=" AND tr.user_id=$person_selected "; }
				$sql.= " ) ";
				$sql.= " INNER JOIN users u ON (tr.user_id=u.user_id ".$sqlteam.") ";
				$sql.= " INNER JOIN lookup_tasks_statuses lts ON (lts.status_id = t.task_status_id) ";
				$sql.= " WHERE p.project_id=".$project_selected;
				$sql.= " GROUP BY t.task_id,p.project_id ORDER BY count_hours DESC";

			  $db->query($sql);

			  if ($db->num_rows())
			  {
			  	$t->set_var("tasks_report_norecords", "");

			  	while($db->next_record())
			  	{
				    $t->set_var("task_title",$db->f("task_title"));
				    $t->set_var("task_spent_hours",trim(Hours2HoursMins($db->f("count_hours"))));
				    $t->set_var("task_spent_hours_multiplied",number_format($db->f("count_hours")*$multiplier,2)); 
				    $t->set_var("task_status",$db->f("status_description"));
				    $task_id=$db->f("task_identifier");

				    $t->set_var("task_id",$task_id);

				    $sql_users= "SELECT u.first_name AS user_name".$sqlfrom." ".$sqlwhere." AND tr.task_id=".$task_id." GROUP BY u.user_id ORDER BY user_name";

				    $task_dev_list="";
				    $db_users->query($sql_users);
				    if ($db_users->next_record())
				    {
				       $task_dev_list = "<NOBR>".$db_users->f("user_name")."</NOBR>";
				       while ($db_users->next_record()) $task_dev_list .= ", <NOBR>".$db_users->f("user_name")."</NOBR>";
				    }

				    $t->set_var("task_developers",$task_dev_list);

				    $t->parse("tasks_report_records", true);

				}
				$t->parse("result_tasks",false);
			  }
			  else
			  {	//no tasks for selected project
				$t->set_var("tasks_report_header", "");
				$t->set_var("tasks_report_records", "");
				$t->parse("result_tasks", false);
			  }
			}
			else
			{	//no project selected
				$t->set_var("result_tasks","");
			}
			///////////////////////////////////////////////////////////



		}
		else {
			$t->set_var("result", "");
			$t->set_var("result_person","");
			echo "<font style=\"font-size:12pt; color:#ff0000; font-weight:bold\"><center>Enter search criteria, please</center></font>";
		}//if ($year_selected && $month_selected)
	}
	else {
		$t->set_var("result", "");
		$t->set_var("dev_result", "");
		$t->set_var("result_person","");
		$t->set_var("result_tasks","");
	} // if ($submit)

	$t->pparse("main");

function userlist($sqlfromwhere,$project_id,$start_date,$end_date,$period_selected,$alllink=false,$team="viart")
{
	global $multiplier,$projects,$sub_projects;
	
	$db_users_f = new DB_Sql();
	$db_users_f->Database = DATABASE_NAME;
	$db_users_f->User     = DATABASE_USER;
	$db_users_f->Password = DATABASE_PASSWORD;
	$db_users_f->Host     = DATABASE_HOST;

	$users_list = "";
	$sql_users  = " SELECT DISTINCT(u.user_id), first_name AS user_name ".$sqlfromwhere;
	$sql_users .= " AND p.project_id=".$project_id." ORDER BY user_name";
	$db_users_f->query($sql_users);
	if ($db_users_f->next_record()) {
		//with links
		$users_list  = "<NOBR><a href='./projects_report_new.php?start_date=$start_date&end_date=$end_date";
		$users_list .= "&period_selected=$period_selected&project_selected=$project_id&person_selected=".($db_users_f->f("user_id"));
		$users_list .= "&projects=$projects&sub_projects=$sub_projects";
		$users_list .= "&action=projects&team=$team&multiplier=$multiplier&submit=+Filter+'>".$db_users_f->f("user_name")."</a></NOBR>";
		while ($db_users_f->next_record()) {
			$users_list .= ", <NOBR><a href='./projects_report_new.php?start_date=$start_date&end_date=$end_date";
			$users_list .= "&period_selected=$period_selected&project_selected=$project_id&person_selected=".($db_users_f->f("user_id"));
			$users_list .= "&projects=$projects&sub_projects=$sub_projects";
			$users_list .= "&action=projects&team=$team&multiplier=$multiplier&submit=+Filter+#projects_user'>".$db_users_f->f("user_name")."</a></NOBR>";
		}
	}

	return $users_list;
}

function get_start_end_period($period_selected,&$start_date,&$end_date)
{
	global $t;

	$current_date = va_time();
	$cyear = $current_date[0];
	$cmonth = $current_date[1];
	$cday = $current_date[2];

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

	if (!$period_selected) $period_selected="this_week";

	if (!$start_date && !$end_date) {
		switch ($period_selected) {
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
			case "this_week":
				$start_date = $this_week_start_date;
				$end_date = $this_week_end_date;
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

	$end_year  =@date("Y",$ed_ts);
	$start_year=@date("m",$ed_ts);
 	$t->set_var("current_year", $end_year);
	$t->set_var("current_month", $start_year);

	return array($sdt,$edt);
}

function reaction_output($reaction_hours)
{
  if ($reaction_hours>0)
  {
  	if ($reaction_hours<=24) $output = Hours2HoursMins($reaction_hours);
  	else $output = sprintf("%3.1f days",$reaction_hours/24);
  } else $output="";
  return $output;
}

?>