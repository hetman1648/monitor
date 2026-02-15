<?php
	include("./includes/date_functions.php");
	include("./includes/common.php");

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
							
	$project_selected = (int)GetParam("project_selected");
	$period_selected = GetParam("period_selected");
	$person_selected = (int)GetParam("person_selected");
	$action = GetParam("action");
	$start_date = GetParam("start_date");
	$end_date = GetParam("end_date");
	$submit = GetParam("submit");	
	//team select block
	$team = GetParam("team");
	$sort = GetParam("sort");
	$est = GetParam("est");
	$top = GetParam("top");
	$cpl = GetParam("cpl");
	
	$as = "";
	$ys = "";
	$projects = array();
	
	switch (strtolower($team))
	{
	  case "all":	$sqlteam=""; $as="selected"; break;
	  case "viart":	$sqlteam=" AND u.is_viart=1 "; $vs="selected"; break;
	  case "yoonoo":$sqlteam=" AND u.is_viart=0 "; $ys="selected"; break;
	  default:	$sqlteam=" AND u.is_viart=1 "; $vs="selected"; $team="viart";
	}
	
	$t = new iTemplate($sAppPath);
	$t->set_file("main","estimates_report.html");

	$t->set_var("periods", GetPeriodOptions($period_selected ? $period_selected : "this_month"));

	// make default this year and month for javascript
	$t->set_var("thisyear", date("Y")-2004+1);
	$t->set_var("thismonth", date("m"));
	
	$t->set_var("aselected",$as); $t->set_var("vselected",$vs); $t->set_var("yselected",$ys); $t->set_var("team_selected",$team);
	
	$t->set_var("period_selected", $period_selected);
	$t->set_var("est_checked",$est ? "checked" : "");
	$t->set_var("top_checked",$top ? "checked" : "");
	$t->set_var("cpl_checked",$cpl ? "checked" : "");
	$t->set_var("est",$est ? "on" : "");
	$t->set_var("top",$top ? "on" : "");
	$t->set_var("cpl",$cpl ? "on" : "");
	$t->set_var("sort",$sort=="diff" ? "diff" : "ratio");

	list($sdt,$edt)=get_start_end_period ($period_selected,$start_date,$end_date);

	$t->set_var("year_selected",  (isset($year_selected) && $year_selected>2000 ? $year_selected : date("Y")));
	$t->set_var("month_selected", (isset($month_selected) && $month_selected>0 ? $month_selected : date("m")));

	//projects list
	$people = "";
	$sql = " SELECT project_id, project_title FROM projects WHERE is_closed=0 ORDER BY project_title ";
	$db->query($sql);
	while ($db->next_record())	{
		$project = $db->f("project_title");
		$project_nom = $db->f("project_id"); 
		if ($project_selected==$project_nom)
		{ 
		  $projects .= "<option selected value='$project_nom'>$project</option>";
		  $t->set_var("ifProjName",": ".$project);
		}  
	    else $projects .= "<option value='$project_nom'>$project</option>";
	}
	$t->set_var("projects", $projects);

	$t->set_var("person_selected",$person_selected ? $person_selected : "0");
	$t->set_var("project_selected",$project_selected ? $project_selected : "0");

	$people = "";
	$sql = "SELECT user_id, is_viart, CONCAT(first_name,' ', last_name) as person FROM users u WHERE is_deleted IS NULL ORDER BY person";
	$db->query($sql);
	$id=0;
	while ($db->next_record())
	{
		$t->set_var("ID",(int)$id++);
		$t->set_var("IDteam",(int)$db->f("is_viart"));
		$t->set_var("IDuser",(int)$db->f("user_id"));
		$t->set_var("user_name",$db->f("person"));
		$t->parse("PeopleArray",true);
	}
	$t->set_var("people", $people);

	if ($submit)
	{
		if ($start_date && $end_date)
		{
		  
		$s_trlimits = "";
		$s_team = "";
		$s_project = "";
		$s_completion = "";
		if ($sdt) { $s_trlimits .= " AND tr.started_date>='$sdt' ";}
		if ($edt) { $s_trlimits .= " AND tr.started_date<='$edt' ";}
		if ($person_selected && $person_selected>0) { $s_user = " AND u.user_id=".(int)$person_selected;}
		if ($team=="viart")  { $s_team = " AND u.is_viart=1 ";}
		if ($team=="yoonoo") { $s_team = " AND u.is_viart=0 ";}
		if ($project_selected && $project_selected>0) { $s_project = " AND p.project_id=".(int)$project_selected;}
		if ($cpl) { $s_completion = " AND (t.completion=100 OR t.task_status_id=4) ";}
  
		//people report
		$sql =  "SELECT user_id, user_name, diffc, COUNT(*) AS cnt, MAX(aediff) AS maxd, MIN(aediff) AS mind, SUM(aediff) AS sumd, SUM(actual_hours) AS suma, SUM(estimated_hours) AS sume, AVG(aediff) AS avgd, AVG(aeratio) AS avgr FROM (".
				"SELECT CONCAT(u.first_name,' ',u.last_name) AS user_name, u.user_id, t.task_id, project_title, task_title, actual_hours, estimated_hours, completion, ".
				" IF(estimated_hours>0, actual_hours - estimated_hours, NULL ) AS aediff, ".		
				" IF(estimated_hours>0, actual_hours/estimated_hours*100, NULL ) AS aeratio, ".		
				" IF (estimated_hours>0, IF (actual_hours>estimated_hours, 1, -1), 0) AS diffc ".
				"FROM tasks t, time_report tr, users u, projects p ".
				"WHERE t.task_id=tr.task_id AND task_type_id!=3 ".
				"AND u.user_id=tr.user_id AND p.project_id=t.project_id ".$s_trlimits.$s_team.$s_project.$s_completion.
				" GROUP BY t.task_id, u.user_id ORDER BY user_name, diffc DESC".
				" ) AS a GROUP BY user_id, diffc ORDER BY user_name ";	
				
		//	echo $sql;
			
		$db->query($sql);

		$p = array();
			
		if ($db->num_rows()) {
			$user_id=0;
			$sum_count_wo = 0;
			$count_over=0;
			$count_under=0;
			$count_wo=0;
			$max_over=0;
			$max_under=0;
			$ttl_over=0;
			$ttl_under=0;
			$avg_over=0;
			$avg_under=0;
			$avg_ratio=0;
			$ratio_over="";
			$ratio_under="";
			$suma = 0;
			$sume = 0;
			$sum_count_over = 0;
			$sum_count_over = 0;
			$sum_count_under = 0;
			while ($db->next_record()) {
				if ($user_id != $db->f("user_id") && $user_id>0) {
			    	$count_over=0;
					$count_under=0;
					$count_wo=0;
					$max_over=0;
					$max_under=0;
					$ttl_over=0;
					$ttl_under=0;
					$avg_over=0;
					$avg_under=0;
					$avg_ratio=0;
					$ratio_over="";
					$ratio_under="";
					$suma = 0;
					$sume = 0;
			    }

				$user_id=$db->f("user_id");
				$cat = $db->f("diffc");
				$cnt = $db->f("cnt");
				$maxd = $db->f("maxd");
				$mind = $db->f("mind");
				$sumd = $db->f("sumd");
				$avgd = $db->f("avgd");
		    
				if ($cat==1) {
					$count_over = $db->f("cnt");
					$max_over = $db->f("maxd");
					$ttl_over = $db->f("sumd");
					$avg_over = $db->f("avgd");
					$suma += $db->f("suma");
					$sume += $db->f("sume");
					$sum_count_over += $count_over;
					$ratio_over = $db->f("sume")>0 ? $db->f("suma")/$db->f("sume")*100-100 : "";
    			} elseif ($cat==-1) {
					$count_under = $db->f("cnt");
					$max_under = $db->f("mind");
					$ttl_under = $db->f("sumd");
					$avg_under = $db->f("avgd");
					$suma += $db->f("suma");
					$sume += $db->f("sume");
					$sum_count_under += $count_over;
					$ratio_under=$db->f("sume")>0 ? $db->f("suma")/$db->f("sume")*100-100 : "";
 				} elseif ($cat==0) {
					$count_wo = $db->f("cnt");
					if ($count_wo) { $sum_count_wo += $count_wo;}
				}

				$p[$user_id]["user_name"] = $db->f("user_name");
				$p[$user_id]["user_id"] = $db->f("user_id");
				$p[$user_id]["count_over"] =$count_over ? $count_over : "";
				$p[$user_id]["count_under"]=$count_under ? $count_under : "";
				$p[$user_id]["count_wo"] =  $count_wo ? $count_wo : "";
				$p[$user_id]["count_ttl"] = $count_over+$count_under+$count_wo;
				$p[$user_id]["max_over"] =  $count_over ? "+".Hours2HoursMins($max_over) : "";
				$p[$user_id]["max_under"] = $count_under ? "-".Hours2HoursMins(-$max_under) : "";
				$p[$user_id]["ttl_over"] =  $count_over ? "+".Hours2HoursMins($ttl_over) : "";
				$p[$user_id]["ttl_under"] = $count_under ? "-".Hours2HoursMins(-$ttl_under) : "";
				$p[$user_id]["avg_over"] =  $count_over ? "+".Hours2HoursMins($avg_over) : "";
				$p[$user_id]["avg_under"] = $count_under ? "-".Hours2HoursMins(-$avg_under) : "";
				$p[$user_id]["ratio_over"] =$count_over>0 ? sprintf("%d",$ratio_over)."%" : "";
				$p[$user_id]["ratio_under"]=$count_under>0 ? sprintf("%d",$ratio_under)."%" : "";

				$p[$user_id]["avg_ratio"] = $sume>0 ? sprintf("%d",$suma/$sume*100-100) : "";
				
			}
			//$t->parse("people_rec",true);
			$q = array();
			$i=0;
			foreach ($p AS $key=>$person) {
				$q[$i]["ratio"]=$person["avg_ratio"];
				$q[$i]["user_id"]=$key;
				$i++;
			}

			sort($q);
			  
			foreach ($q AS $qelem) {
  	
				$id=$qelem["user_id"];
				if ($p[$id]["count_over"]>0 || $p[$id]["count_under"]>0) {
			    	$t->set_var("user_name",  $p[$id]["user_name"]);
			    	$t->set_var("user_id",    $p[$id]["user_id"]);
			    	$t->set_var("count_over", $p[$id]["count_over"]. ($p[$id]["count_over"] ? " (".round($p[$id]["count_over"]/$p[$id]["count_ttl"]*100)."%)" : "" ));
			    	$t->set_var("count_under",$p[$id]["count_under"].($p[$id]["count_under"] ? " (".round($p[$id]["count_under"]/$p[$id]["count_ttl"]*100)."%)" : "" ));
			    	$t->set_var("count_wo",   $p[$id]["count_wo"].   ($p[$id]["count_wo"] ? " (".round($p[$id]["count_wo"]/$p[$id]["count_ttl"]*100)."%)" : "" ));
					$t->set_var("max_over",   $p[$id]["max_over"]);
					$t->set_var("max_under",  $p[$id]["max_under"]);
					$t->set_var("ttl_over",   $p[$id]["ttl_over"]);
					$t->set_var("ttl_under",  $p[$id]["ttl_under"]);
					$t->set_var("avg_over",   $p[$id]["avg_over"]);
					$t->set_var("avg_under",  $p[$id]["avg_under"]);
					$t->set_var("ratio_over",   $p[$id]["ratio_over"]);
					$t->set_var("ratio_under",  $p[$id]["ratio_under"]);
					$t->set_var("ratio_total",  strlen($p[$id]["avg_ratio"]) ? $p[$id]["avg_ratio"]."%" : "");
					$t->parse("people_rec",true);
				}
			}
			  
			$sql =  "SELECT diffc, COUNT(*) AS cnt, MAX(aediff) AS maxd, MIN(aediff) AS mind, SUM(aediff) AS sumd, SUM(actual_hours) AS suma, SUM(estimated_hours) AS sume, AVG(aediff) AS avgd, AVG(aeratio) AS avgr FROM (".
				"SELECT CONCAT(u.first_name,' ',u.last_name) AS user_name, u.user_id, t.task_id, project_title, task_title, actual_hours, estimated_hours, completion, ".
				" IF(estimated_hours>0, actual_hours - estimated_hours, NULL ) AS aediff, ".		
				" IF(estimated_hours>0, actual_hours/estimated_hours*100, NULL ) AS aeratio, ".		
				" IF (estimated_hours>0, IF (actual_hours>estimated_hours, 1, -1), 0) AS diffc ".
				"FROM tasks t, time_report tr, users u, projects p ".
				"WHERE t.task_id=tr.task_id AND task_type_id!=3 ".
				"AND u.user_id=tr.user_id AND p.project_id=t.project_id ".$s_trlimits.$s_team.$s_project.$s_completion.
				" GROUP BY t.task_id ORDER BY diffc".
				" ) AS a GROUP BY diffc ";			  
				
			  //echo $sql;
			  $db->query($sql);
			  $o_suma = 0;
			  $o_sume = 0;
			  
			while ($db->next_record()) {			    
				$cat = $db->f("diffc");
				switch ($cat) {
					case 1 : 
						$t->set_var("sum_count_over",$db->f("cnt") ? $db->f("cnt") : "");  
						$t->set_var("o_max_over",$db->f("cnt") ? "+".Hours2HoursMins($db->f("maxd")) : "");  
						$t->set_var("sum_ttl_over",$db->f("cnt") ? "+".Hours2HoursMins($db->f("sumd")) : "");  
						$t->set_var("o_avg_over",$db->f("cnt") ? "+".Hours2HoursMins($db->f("avgd")) : "");
						$t->set_var("o_ratio_over",$db->f("cnt") ? sprintf("%d",$db->f("suma")/$db->f("sume")*100-100)."%" : "");
					    $o_suma += $db->f("suma");
					    $o_sume += $db->f("sume");						
					break;
						
					case -1 : 
						$t->set_var("sum_count_under",$db->f("cnt") ? $db->f("cnt") : "");  
						$t->set_var("o_max_under",$db->f("cnt") ? "-".Hours2HoursMins(-$db->f("mind")) : "");  
						$t->set_var("sum_ttl_under",$db->f("cnt") ? "-".Hours2HoursMins(-$db->f("sumd")) : "");  
						$t->set_var("o_avg_under",$db->f("cnt") ? "-".Hours2HoursMins(-$db->f("avgd")) : "");  
						$t->set_var("o_ratio_under",$db->f("cnt") ? sprintf("%d",$db->f("suma")/$db->f("sume")*100-100)."%" : "");
					    $o_suma += $db->f("suma");
					    $o_sume += $db->f("sume");						
					break;	

					case 0 : 
						$t->set_var("sum_count_wo",$db->f("cnt") ? $db->f("cnt") : "");  
					break;	
			  	}
			}		  
			$t->set_var("o_ratio_total",$o_sume>0 ? sprintf("%d",$o_suma/$o_sume*100-100)."%" : "");		        
			  
			$t->parse("people_est",false);
		} else {
		  $t->set_var("people_est","");
		}
		
		
		//tasks report
		if ($est) $mt=2; else $mt=1;

		for ($type=0; $type<=$mt; $type++)
		{
				
			switch($type)
			{
				case 0: $typestr=" AND estimated_hours>0 AND actual_hours >  estimated_hours ";break;
				case 1: $typestr=" AND estimated_hours>0 AND actual_hours <= estimated_hours ";break;
				case 2: $typestr=" AND (estimated_hours=0 OR estimated_hours IS NULL) ";break;
			}
			
			if ($sort=="diff") $s_sort = "aediff "; else $s_sort = "aeratio ";
			$s_sort.=($type==0 ? " DESC" : " ASC");
		  
			$sql =  "SELECT t.task_id, project_title, task_title, actual_hours, estimated_hours, completion, status_desc, ".
				" IF(estimated_hours>0, actual_hours - estimated_hours, NULL ) AS aediff,  ".		
				" IF(estimated_hours>0, actual_hours/estimated_hours*100-100, NULL ) AS aeratio  ".		
				"FROM tasks t, time_report tr, lookup_tasks_statuses lt, users u, projects p ".
				"WHERE t.task_id=tr.task_id AND lt.status_id=t.task_status_id AND task_type_id!=3 ".
				"AND u.user_id=tr.user_id AND p.project_id=t.project_id ".$s_trlimits.$s_user.$s_team.$s_project.$typestr.$s_completion.
				" GROUP BY t.task_id ORDER BY ".$s_sort." , actual_hours DESC";

			if ($top) $sql.=" LIMIT 20 ";
			
			$db->query($sql);
			
			$av_act=0;
			$av_acte=0;
			$av_est=0;
			$av_aed=0;
			$i_est=0;
			$i_total=0;
			  			  
			if ($db->num_rows())
			{
			  	$t->set_var("tasks_report_norecords", "");

			  	while($db->next_record())
			  	{
				    $t->set_var("project_title",$db->f("project_title"));
				    $t->set_var("task_title",$db->f("task_title"));
				    $ah = $db->f("actual_hours");
				    $eh = $db->f("estimated_hours");
				    
   
				    $completion = $db->f("completion");
				    $status_desc = $db->f("status_desc");
				    if ($status_desc=="done") $completion = 100;
				    
				    $t->set_var("spent_actual",trim(Hours2HoursMins($ah)));
				    $t->set_var("spent_estimate",$eh>0 ? trim(Hours2HoursMins($eh)) : ""  );
				    $t->set_var("task_status",$db->f("status_desc"));
				    
				    $t->set_var("completion", isset($completion) ? $completion."%" : "" );
				    $task_id=$db->f("task_id");
				    $t->set_var("task_id",$task_id);
				    
				    $ae_diff = $db->f("aediff");
				    $ae_ratio = $db->f("aeratio");
				    $color="";
				    if ($ae_diff>0) $color="red";
				    if ($ae_diff<0) $color="blue";
				    $ae_diff_str="";
				    if ($ae_diff>=0 && $eh>0) $ae_diff_str="+".Hours2HoursMins($ae_diff); 
				    if ($ae_diff<0) $ae_diff_str="-".Hours2HoursMins(-$ae_diff);
				    
				    $t->set_var("ae_diff",$ae_diff_str);
				    $t->set_var("ae_ratio",$eh ? sprintf("%1d",$ae_ratio)."%" : "");
				    $t->set_var("color", $color);

				    $sql_users= "SELECT u.first_name AS user_name FROM users u, time_report tr WHERE u.user_id=tr.user_id AND tr.task_id=".$task_id." GROUP BY u.user_id ORDER BY user_name";
				    
				    $task_dev_list="";
				    $db_users->query($sql_users);
				    if ($db_users->next_record())
				    {
				       $task_dev_list = "<NOBR>".$db_users->f("user_name")."</NOBR>";
				       while ($db_users->next_record()) $task_dev_list .= ", <NOBR>".$db_users->f("user_name")."</NOBR>";
				    }
				    
				    $t->set_var("task_developers",$task_dev_list);

				    $t->parse("tasks_report_records", true);				    

				    $av_act+=$ah;
				    $av_est+=$eh;
				    $i_total+=1;
				    if ($eh>0)
				    {
					$av_acte+=$ah;
					$i_est+=1;
					$av_aed+=$ae_diff;
				    }	
				    
				}
				
				$t->set_var("av_spent_actual",$i_est>0 ? Hours2HoursMins($av_acte/$i_est) : ($i_total>0 ? Hours2HoursMins($av_act/$i_total) : ""));
				$t->set_var("av_spent_estimate",$i_est>0 ? Hours2HoursMins($av_est/$i_est) : "");
				
				$ae_diff_str="";
				if ($i_est>0)
				{
					if ($av_aed>=0) $ae_diff_str="+".Hours2HoursMins($av_aed/$i_est); 
					if ($av_aed<0) $ae_diff_str="-".Hours2HoursMins(-$av_aed/$i_est);
				}
			
				$t->set_var("av_ae_diff",$ae_diff_str);
				$t->set_var("av_ae_ratio",$av_est>0 ? sprintf("%d",$av_acte/$av_est*100-100)."%" : "");
				$t->parse("tasks_averages",false);
				
				
				$t->parse("result_tasks",true);
				
				$t->set_var("tasks_report_records","");
			}
			else
			{	//no tasks for selected project
				$t->set_var("tasks_report_header", "");
				$t->set_var("tasks_report_records", "");
				$t->set_var("tasks_averages", "");
				$t->parse("result_tasks", true);			  	
			}
			
			///////////////////////////////////////////////////////////
			$t->parse("result_t",false);			
		}//end for
		
		} 
		else {
			$t->set_var("result_t", "");
			$t->set_var("result_person","");
			echo "<font style=\"font-size:12pt; color:#ff0000; font-weight:bold\"><center>Enter search criteria, please</center></font>";
		}
	}
	else {
		$t->set_var("people_est", "");
		$t->set_var("result_t", "");
		$t->set_var("dev_result", "");
		$t->set_var("result_person","");
		$t->set_var("result_tasks","");
	} // if ($submit)
	
	$t->pparse("main");
	
function userlist($sqlfromwhere,$project_id,$start_date,$end_date,$period_selected,$alllink=false)
{
	$db_users_f = new DB_Sql();
	$db_users_f->Database = DATABASE_NAME;
	$db_users_f->User     = DATABASE_USER;
	$db_users_f->Password = DATABASE_PASSWORD;
	$db_users_f->Host     = DATABASE_HOST;

	$sql_users = " SELECT DISTINCT(u.user_id), first_name AS user_name ".$sqlfromwhere;
	$sql_users.= " AND p.project_id=".$project_id." ORDER BY user_name";
	$db_users_f->query($sql_users);
	if ($db_users_f->next_record())
	{
	  //with links
	  $users_list =  "<NOBR>".$db_users_f->f("user_name")."</NOBR>";
	  while ($db_users_f->next_record())
	  $users_list.=", <NOBR>".$db_users_f->f("user_name")."</NOBR>";
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

?>