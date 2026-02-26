<?php

/* common tasks functions - from index.php and report_people.php */

	$statuses_classes = array("","InProgress","OnHold","Rejected","Done","Question","Answer","New","Waiting","Reassigned","Bug","Deadline",
	"BugResolved", "Documented", "ReadyToDocument", "New", "New", "New", "New", "New", "New", "New");
$csv = "Subject,Start Date,Due Date,Reminder On/Off,Reminder Date,Reminder Time,Date Completed,% Complete,Total Work,Actual Work,";
$csv .= "Billing Information,Categories,Companies,Contacts,Mileage,Notes,Priority,Role,Schedule+ Priority,Sensitivity,Status\n";

$points_array	= array(0 => "haven't done anything",
						1 => "done something",
						2 => "done most of the plan",
						3 => "done as estimated",
						4 => "done even more than estimated",
						5 => "done twice and more then estimated");

function show_lunches_block($block_name, $user_id)
{
	global $db, $T;
	
	/// lunch ordering block
	$sql = "SELECT view FROM users u NATURAL JOIN lunches_allocated_people l WHERE u.is_viart=1 AND l.user_id=".GetSessionParam("UserID");
	$db->query($sql);
	if ($db->num_rows() && $db->next_record() && $db->f("view")=="1")
	{
		//$db->query("SELECT id_menu, menu_date, DATE_FORMAT(NOW(),'%Y-%m-%d') FROM lunches_menu ".
		$sql = "SELECT	COUNT(id_menu) as menu_quant
				FROM	lunches_menu
				WHERE	((menu_date=DATE_FORMAT(NOW(),'%Y-%m-%d')
						AND DATE_FORMAT(NOW(),'%H')<15 )
						OR menu_date>DATE_FORMAT(NOW(),'%Y-%m-%d'))
						AND is_blocked=0
				ORDER BY menu_date ASC";
		$db->query($sql);
		if ($db->num_rows()) {
			$T->set_var("error_message","");
			$db->next_record();
			$menu_quant = $db->f("menu_quant");
			$sql = "SELECT	count(lo.id_order) as order_quant
					FROM	lunches_orders lo
							LEFT JOIN lunches_menu lm ON (lo.id_menu=lm.id_menu AND lm.is_blocked=0)
					WHERE	((lm.menu_date=DATE_FORMAT(NOW(),'%Y-%m-%d') AND DATE_FORMAT(NOW(),'%H')<13 ) OR lm.menu_date>DATE_FORMAT(NOW(),'%Y-%m-%d'))
							AND (lo.first_course_qty!=0 OR lo.second_course_qty!=0 OR lo.garnish_qty!=0 OR lo.salad_qty!=0)
							AND lo.user_id=".ToSQL($user_id,"integer");
			$db->query($sql);
			$db->next_record();
			if ($db->f("order_quant")==$menu_quant) {
				$T->set_var("order_lunch","");
			   	$T->parse("order_exists",false);
			} else {
  			   $T->parse("order_lunch",false);
			   $T->set_var("order_exists","");
			}
		} else {
		  	$T->set_var("order_lunch","");
		  	$T->set_var("order_exists","");
			$T->set_var("error_message","lunches not available. <a href='lunches_statistics.php'>statistics</a>");
		}
		
		$T->set_var("today_date", date("Y-m-d"));
		$T->parse("order_today", false);
		$T->parse($block_name,false);
		$parsed = true;
	} else {
		$T->set_var($block_name,"");
		$parsed = false;
	}
	
	return $parsed;

	//end lunch_ordering
}

function GetSQLOrder($sort)
{
	$sqlorder="";
	switch ($sort)
	{
	 	case "complete": $sqlorder = " ORDER BY t.planed_date, t.priority_id, t.project_id";break;
	 	case "title": $sqlorder = " ORDER BY t.task_title, t.priority_id, t.project_id";break;
	 	case "priority": $sqlorder = " ORDER BY sorter, t.priority_id, t.project_id";break;
	 	case "project": $sqlorder = " ORDER BY p.project_title, t.priority_id";break;
	 	case "status": $sqlorder = " ORDER BY ls.status_desc, t.priority_id";break;
	 	case "type": $sqlorder = " ORDER BY t.task_type_id, t.priority_id, t.project_id"; break;
	 	case "create": $sqlorder = " ORDER BY t.creation_date DESC, t.priority_id, t.project_id";break;
	 	case "modified": $sqlorder = " ORDER BY t.date_reassigned DESC, t.priority_id, t.project_id";break;
	 	default: $sqlorder = " ORDER BY sorter, t.priority_id, t.project_id";
	}
	
	return $sqlorder;

}

function GetTasksList($user_id, $is_manager, $sort, &$csv, &$is_working)
{
	global $db, $T, $statuses_classes;
	
	$T->set_var("my_tasks_title","My Tasks");
	$T->set_var("notes","");
	
	if (has_permission("PERM_CLOSE_TASKS")) {
		$T->set_var("close_title", "Close");
		$show_close_tasks = true;
		$is_close_disabled = false;
	} else {
		$T->set_var("close_title", "");
		$is_close_disabled = true;
		$show_close_tasks = false;		
	}

	// check started task id and title.
	// when someone starts another task first one will be closed, then this id value will be transferred to javascript
	// to prompt completion status of that first task, which will be closed.
	$sql = "SELECT task_id,task_title,task_type_id,completion FROM tasks WHERE task_status_id=1 AND is_closed=0 AND responsible_user_id=".ToSQL($user_id, "Number");
	$db->query($sql);
	if ($db->next_record())
	{
	  $T->set_var("active_task_title", addslashes(str_replace("\"", "", $db->Record["task_title"])));
	  //set is_periodic for javascript "start/stop task"
	  if ($db->Record["task_type_id"]!=3) $T->set_var("is_periodic","false"); else $T->set_var("is_periodic","true");
	  $completion_active=$db->Record["completion"];
	}
	else
	{
	  $T->set_var("active_task_title", "");
	  $T->set_var("is_periodic","false");
	  $completion_active=0;
	}			
	
	$sqlorder = GetSQLOrder($sort);
	//-- my tasks
  	$sql_tasks = " SELECT t.*, p.*, lt.*, ls.*, t.creation_date AS cdate, t.planed_date AS pdate, t.date_reassigned AS rdate ";
  	$sql_tasks.= ", DATE_FORMAT(t.creation_date,  '%e %b %y') AS creation_date ";
  	$sql_tasks.= ", DATE_FORMAT(t.planed_date,    '%e %b %y') AS planed_date ";
  	$sql_tasks.= ", DATE_FORMAT(t.date_reassigned,'%e %b %y') AS reassigned_date ";
	$sql_tasks.= ", IF( t.task_status_id =1, t.actual_hours +( UNIX_TIMESTAMP( NOW( ) ) - UNIX_TIMESTAMP( t.started_time ) )/3600.0000 , t.actual_hours ) AS actual_hours_live ";
  	$sql_tasks.= ", UNIX_TIMESTAMP(t.creation_date) AS cdate_nix, UNIX_TIMESTAMP(t.planed_date) AS pdate_nix ";
  	$sql_tasks.= ", t.created_person_id AS tasks_created_person_id ";      // created_person_id from tasks;
  	$sql_tasks.= ", IF (TO_DAYS(t.planed_date) < TO_DAYS(now()) AND t.task_status_id!=4 AND t.task_type_id!=3,1,0 ) AS ifdeadlined ";
  	$sql_tasks.= ", IF (TO_DAYS(t.planed_date) = TO_DAYS(now()) AND t.task_status_id!=4 AND t.task_type_id!=3,1,0 ) AS iftoday ";
  	$sql_tasks.= ", IF(t.task_status_id=2, 1, 0) AS sorter ";
  	$sql_tasks.= ", COUNT(n.note_id) AS total_notes ";
	$sql_tasks.= " FROM tasks t ";
	$sql_tasks.= " LEFT JOIN projects p ON (t.project_id = p.project_id) ";
	$sql_tasks.= " LEFT JOIN lookup_task_types lt ON (t.task_type_id = lt.type_id) ";
	$sql_tasks.= " LEFT JOIN lookup_tasks_statuses ls ON (t.task_status_id = ls.status_id) ";
	$sql_tasks.= " LEFT JOIN notes n ON (n.task_id=t.task_id) ";
	$sql_tasks.= " WHERE t.is_wish = 0 AND t.is_closed = 0 ";
	$sql_tasks.= " AND t.responsible_user_id = " . ToSQL($user_id, "Number");
	
	$sql = $sql_tasks;	
	
	if ($is_manager) {
		$sql.= " AND t.is_planned=0 ";
	}
	$sql.= " GROUP BY t.task_id ";
		
	$sql.= $sqlorder;

	$periodic_tasks_count=0;
	
	$db->query($sql);
  	$project_name = "";
  	$project_name_periodic = "";
  	$k = true;
  	$is_working = false;
  	$tasks_count = 0;
  	$task_index = 0;
  	
  	
  	$non_periodic_tasks_count = 0;

  	if ($db->next_record()) {
		do {
	      	$T->set_var($db->Record);

			if ($db->Record["cdate"] == "0000-00-00 00:00:00") $T->set_var("creation_date", "");
			if ($db->Record["pdate"] == "0000-00-00 00:00:00") $T->set_var("planed_date", "");
			if ($db->Record["pdate"] == "0000-00-00 00:00:00") $T->set_var("reassigned_date", "");

			if ($db->Record["task_type_id"]!=3)
			{				
				if ($project_name != $db->Record["project_title"]) {
		        	$project_name = $db->Record["project_title"];
		        	$T->set_var("project_title", short_title($project_name,20));
		      	} else {
		      		$T->set_var("project_title", "");
		      	}
			} else {
				if ($project_name_periodic != $db->Record["project_title"])
				{
					$project_name_periodic = $db->Record["project_title"];
		        	$T->set_var("project_title", short_title($project_name_periodic, 20));
				} else {
					$T->set_var("project_title", "");
				}
			}

			if ($k) {				
				$T->set_var("STATUS", $statuses_classes[$db->Record["status_id"]]);
			} else {
				$T->set_var("STATUS", $statuses_classes[$db->Record["status_id"]] . "2");
			}
			
			$T->set_var("task_title_xml", $db->Record["task_title"]);
			$T->set_var("project_title_xml", short_title($db->Record["project_title"], 20));
			
			//if planned date is today or earlier display row in red colours			
			if ($db->Record["ifdeadlined"])
			{
				$T->set_var("task_title","<font color=\"red\"><b>".$db->Record["task_title"]."</b></font>");
				if ($db->f("task_status_id")!=1) $T->set_var("STATUS","Deadline");
			}
			if ($db->Record["iftoday"])
			{
				$T->set_var("task_title",$db->Record["task_title"]);
				if ($db->f("task_status_id")!=1) $T->set_var("STATUS","Today");
			}

			$T->set_var("estimated_title", $db->Record["estimated_title"]);
		  	$db_estimated_hours=$db->Record["estimated_hours"];

		  	if ($db_estimated_hours>0) $T->set_var("estimated_hours", to_hours($db->Record["estimated_hours"]));
		  	else $T->set_var("estimated_hours", "");	  	
		  	

			$T->set_var("task_title_slashed", addslashes(str_replace("\"", "", $db->Record["task_title"])));
		  	$actual_hours = to_hours($db->Record["actual_hours"]);
			$T->set_var("actual_hours_live", $db->Record["actual_hours_live"]);
		  	$T->set_var("actual_hours", $actual_hours);
		  	
		  	if (($db->f("tasks_created_person_id") == $user_id || $show_close_tasks) && $db->Record["task_type_id"]!=3) {		  		
		  		$is_close_disabled = false;
		  		++$task_index;
				$T->set_var("task_index", $task_index);
				$T->parse("show_close_checkbox", false);
		  	} else {
		  		$T->set_var("show_close_checkbox", "");
		  	}
	      	if ($db->f("total_notes")>0) {
	      		$T->parse("total_notes_block", false);
	      	} else {
	      		$T->set_var("total_notes_block", "");
	      	}		  	


			//completion & time left
			$db_completion=$db->Record["completion"];
			$db_actual_hours=$db->Record["actual_hours"];

			if (isset($db_completion))
			{
				//active task
				if ($db->Record["status_id"] == 1) $T->set_var("completion_javascript",$db_completion);
			   	else $T->set_var("completion_javascript",($completion_active ? $completion_active : 0));
			  	$T->set_var("completion_percent",   $db_completion."%");
			  	$T->set_var("time_left_estimate", to_hours($db_estimated_hours - $db_estimated_hours*$db_completion/100,true));
			  	$T->set_var("time_left_actual", ($db_completion>5 ? to_hours($db_actual_hours*(100-$db_completion)/$db_completion,true) : ""));
			}
			else
			{
				$T->set_var("completion_javascript",($completion_active ? $completion_active : 0));
				$T->set_var("completion_percent","&nbsp;");
			  	$T->set_var("time_left_estimate", "");
			  	$T->set_var("time_left_actual", "");
			}
			//--end completion

			$k = !$k;

			if ($db->Record["status_id"] == 1)
			{
		  		$T->set_var("operation_status","Stop");
		  		$is_working = true;
		  		if ($db->Record["task_type_id"]==3) {
		  			$T->set_var("nonperiodic","");
		  		}
				$T->set_var("url_page","index.php");
				$T->set_var("active_task_id", $db->f("task_id"));
				$actual_live_hours = $db->f("actual_hours_live");
				$T->set_var("actual_hours", Hours2HoursMins($actual_live_hours));
				if ($actual_live_hours) {
					$T->set_var("actual_timestamp", $actual_live_hours*3600000);
				} else {
					$T->set_var("actual_timestamp", "0");
				}				
		  		$T->parse("current_task_block",false);
		  	} else {
			    $T->set_var("operation_status","Start");
			}

			//choose to parse periodic tasks or usual (new, correction) tasks;
		    if ($db->Record["task_type_id"]!=3)
			{
				$T->set_var("url_page","index.php");
				$tasks_count++;
				if ($is_manager) {
					$T->parse("unread_tasks", true);
					$T->set_var("mytasks", "");
				} else {
					$T->parse("mytasks", true);
					$T->set_var("unread_tasks", "");
				}
			}
			else
			{
				$periodic_tasks_count++;
				if ($db->Record["status_id"]!=1) $T->set_var("periodic_tasks_active",""); else $T->parse("periodic_tasks_active",false);
				$T->set_var("url_page","index.php");
				$T->parse("periodic_tasks", true);
			}
			
			if ($T->block_exists("task")) {				
				$T->parse("task", true);
			}
		  	//-- current task

			//write to csv
			$task_notes = str_replace("\r\n", " ", $db->f("task_desc"));
			$task_notes = str_replace("\n", "", $task_notes);
			$task_notes = str_replace("\"", "'", $task_notes);
			$csv .= $db->f("task_title") . ",\"" . ($db->f("cdate_nix")>0?date("Y-m-d", $db->f("cdate_nix")):0) . "\",\"" . ($db->f("pdate_nix")>0?date("Y-m-d", $db->f("pdate_nix")):0);
			$csv .= "\",FALSE,,,," . ($db->f("completion") ? "1" : "0") . ",0,0,,,,,,\"" . $task_notes . "\",Normal,,,Normal,";
			$csv .= (isset($outlook_statuses[$db->f("task_status_id")]) ? $outlook_statuses[$db->f("task_status_id")] : "") . "\n";
	   	} while ($db->next_record());
		$T->set_var("no_mytasks", "");
  	}

  	if (!$tasks_count) {
  		$T->set_var("mytasks", "");
  		if (!$is_manager) {
  			if ($periodic_tasks_count)
  			{
  				$T->set_var("no_mytasks", "");
  				$T->set_var("tasks_list", ""); 
  			} else {
  				$T->parse("no_mytasks", false);   				 				
  			}  			
  			$T->set_var("unread_tasks", "");
  		}
  	}

  	if ($periodic_tasks_count==0)
	{
		$T->set_var("periodic_tasks", "");
	}
  	
  	if ($is_manager) {
  		if ($tasks_count) {
  			$T->set_var("my_tasks_title","Unread Tasks");  			
  			$T->set_var("mytasks","");
  			$T->set_var("mtt","1");
  			$T->parse("tasks_list", true);  			
  		}  		

  		$T->set_var("my_tasks_title","My Tasks");
  		$T->set_var("mytasks","");
  		$T->set_var("unread_tasks","");
  		
  		// begin - usual tasks for managers

	  	$sql = $sql_tasks." AND t.is_planned=1 "." GROUP BY t.task_id ".$sqlorder;

		$db->query($sql);
		
  		$project_name = "";
  		$project_name_periodic = "";
  		$k = true;  		
  		$usual_tasks_count = 0;
	  	if ($db->next_record()) {
			do {
		      	$T->set_var($db->Record);
		      	
	
		      	if ($db->Record["cdate"] == "0000-00-00 00:00:00") $T->set_var("creation_date", "");
		      	if ($db->Record["pdate"] == "0000-00-00 00:00:00") $T->set_var("planed_date", "");

	    	  	if ($db->Record["task_type_id"]!=3)
				{		       
					if ($project_name != $db->Record["project_title"]) {
		        	$project_name = $db->Record["project_title"];
		        	$T->set_var("project_title", short_title($project_name, 20));
		      		} else $T->set_var("project_title", "");
		      	} else {
			       	if ($project_name_periodic != $db->Record["project_title"]) {
		        	$project_name_periodic = $db->Record["project_title"];
		        	$T->set_var("project_title", short_title($project_name_periodic, 20));
		      		} else $T->set_var("project_title", "");
				}

			if ($k) $T->set_var("STATUS", $statuses_classes[$db->Record["status_id"]]);
		      	else $T->set_var("STATUS", $statuses_classes[$db->Record["status_id"]] . "2");
		      	
			$T->set_var("task_title_xml", $db->Record["task_title"]);
			$T->set_var("project_title_xml", short_title($db->Record["project_title"], 20));

			//if planned date is today or earlier display row in red colours
			if ($db->Record["ifdeadlined"])
			{
				$T->set_var("task_title","<font color=\"red\"><b>".$db->Record["task_title"]."</b></font>");
				if ($db->f("task_status_id")!=1) $T->set_var("STATUS","Deadline");
			}
			if ($db->Record["iftoday"])
			{
				$T->set_var("task_title",$db->Record["task_title"]);
				if ($db->f("task_status_id")!=1) $T->set_var("STATUS","Today");
			}

			$T->set_var("estimated_title", $db->Record["estimated_title"]);
		  	$db_estimated_hours=$db->Record["estimated_hours"];

		  	if ($db_estimated_hours>0) {
		  		$T->set_var("estimated_hours", to_hours($db->Record["estimated_hours"]));
		  	} else {
		  		$T->set_var("estimated_hours", "");
		  	}

			$T->set_var("task_title_slashed", addslashes(str_replace("\"", "", $db->Record["task_title"])));
		  	$actual_hours = to_hours($db->Record["actual_hours"]);
			$T->set_var("actual_hours_live", $db->Record["actual_hours_live"]);
		  	$T->set_var("actual_hours", $actual_hours);		  	

		  	if ($db->Record["task_type_id"]!=3) {		  		
				$T->set_var("task_index", ++$task_index);				
				$T->parse("show_close_checkbox", false);
		  	} else {
		  		$T->set_var("show_close_checkbox", "");
		  	}
      		if ($db->f("total_notes")>0) {
      			$T->parse("total_notes_block", false);
      		} else {
	      		$T->set_var("total_notes_block", "");
	      	}	  	
		  	
			//completion & time left
			$db_completion=$db->Record["completion"];
			$db_actual_hours=$db->Record["actual_hours"];

			if (isset($db_completion))
			{
				//active task
				if ($db->Record["status_id"] == 1) $T->set_var("completion_javascript",$db_completion);
			   	else $T->set_var("completion_javascript",($completion_active ? $completion_active : 0));
			  	$T->set_var("completion_percent",   $db_completion."%");
			  	$T->set_var("time_left_estimate", to_hours($db_estimated_hours - $db_estimated_hours*$db_completion/100,true));
			  	$T->set_var("time_left_actual", ($db_completion>5 ? to_hours($db_actual_hours*(100-$db_completion)/$db_completion,true) : ""));
			}
			else
			{
				$T->set_var("completion_javascript",($completion_active ? $completion_active : 0));
				$T->set_var("completion_percent","&nbsp;");
			  	$T->set_var("time_left_estimate", "");
			  	$T->set_var("time_left_actual", "");
			}

			//--end completion

			$k = !$k;

			if ($db->Record["status_id"] == 1)
			{
		  		$T->set_var("operation_status","Stop");
		  		$is_working = true;
		  		if ($db->Record["task_type_id"]==3) {
		  			$T->set_var("nonperiodic","");
		  		}
				$T->set_var("url_page","index.php");
				$T->set_var("active_task_id", $db->f("task_id"));
				$actual_live_hours = $db->f("actual_hours_live");
				$T->set_var("actual_hours", Hours2HoursMins($actual_live_hours));
				if ($actual_live_hours) {
					$T->set_var("actual_timestamp", $actual_live_hours*3600000);
				} else {
					$T->set_var("actual_timestamp", "0");
				}
		  		$T->parse("current_task_block",false);
		  		
		  	} else {
			    $T->set_var("operation_status","Start");
			}

			//choose to parse periodic tasks or usual (new, correction) tasks;
		    if ($db->Record["task_type_id"]!=3)
			{
				$T->set_var("url_page","index.php");
				$usual_tasks_count++;
				$T->parse("mytasks", true);
			}
			else
			{
				$periodic_tasks_count++;
				if ($db->Record["status_id"]!=1) $T->set_var("periodic_tasks_active",""); else $T->parse("periodic_tasks_active",false);
				$T->set_var("url_page","index.php");
				$T->parse("periodic_tasks", true);
			}

		  	//-- current task
		 	if ($T->block_exists("task")) {
				$T->parse("task", true);
			}

				//write to csv
				$task_notes = str_replace("\r\n", " ", $db->f("task_desc"));
				$task_notes = str_replace("\n", "", $task_notes);
				$task_notes = str_replace("\"", "'", $task_notes);
				$csv .= $db->f("task_title") . ",\"" . ($db->f("cdate_nix")>0?date("Y-m-d", $db->f("cdate_nix")):0) . "\",\"" . ($db->f("pdate_nix")>0?date("Y-m-d", $db->f("pdate_nix")):0);
				$csv .= "\",FALSE,,,," . ($db->f("completion") ? "1" : "0") . ",0,0,,,,,,\"" . $task_notes . "\",Normal,,,Normal,";
				$csv .= (isset($outlook_statuses[$db->f("task_status_id")]) ? $outlook_statuses[$db->f("task_status_id")] : "") . "\n";
	   		} while ($db->next_record());
			$T->set_var("no_mytasks", "");
  		} else {   			
  			$T->parse("no_mytasks", false);
  			
			
  		}

  		if ($periodic_tasks_count==0)
		{
			$T->set_var("periodic_tasks", "");
		}
		
		if ($T->block_exists("task")) {
			$T->parse("task", true);
		}

		
  		if (!$usual_tasks_count && !$tasks_count) {
  			if ($T->block_exists("mytasks")) {
  				$T->set_var("mytasks", "");

  			}
  			if ($T->block_exists("unread_tasks")) {
  				$T->set_var("unread_tasks", "");
  			}
  			
  			if ($T->block_exists("no_mytasks")) {
  				$T->parse("no_mytasks", false);
  			}
  		}
		
  		if ($usual_tasks_count) {
  		
  			if ($tasks_count) {
  				$T->set_var("mtt","2");
  				$T->parse("tasks_list", true);  				
  			} else {
  				$T->set_var("mtt","2");
  				$T->parse("tasks_list", false);
  			}
  		} else {
  			$T->parse("tasks_list", false);
  		}
  		
  		
  		//end - usual tasks for managers
 	
  	} else {
 		$T->set_var("unread_tasks", "");  			  		
		
 		if ($tasks_count) {
 			$T->parse("tasks_list", false);
 		} else {
 			if ($periodic_tasks_count)
			{
				$T->set_var("no_mytasks", "");
				$T->set_var("tasks_list", "");  				
			} else {
				$T->parse("no_mytasks", false);  				
				$T->parse("tasks_list",false);
			}
 		}	
  	}
  	
  	if ($show_close_tasks || !$is_close_disabled) {
  		$T->parse("show_close_checkboxes", false);
  		$T->set_var("is_close_disabled", "");  		
  	} else {
  		$T->set_var("show_close_checkboxes", "");
  		$T->set_var("is_close_disabled", "disabled");
  	}
  	
  	$T->set_var("tasks_number", $task_index);
  	
  	if (!$is_working) {
 		$T->set_var("current_task_block", "");
  		$T->set_var("active_task_id", "0");
  		$T->set_var("actual_timestamp", "0");
  	}
}

function show_users_list($viart_block_name, $yoonoo_block_name, $viart_team_count, $yoonoo_team_count)
{
	global $db, $T;
	
	//select last 666 records from time report - optimize query to this table as select from all table
	//by date works slowly
	$begin_report_id = 0;
	$sql = "SELECT MAX(report_id) FROM time_report ";
	$db->query($sql);
	if ($db->next_record())
	{
		$begin_report_id = $db->f(0);
		if ($begin_report_id>666) {
			$begin_report_id-=666;
		}
	}
	//select people who worked today
	$worked_today = array();
	$sql = " SELECT user_id, IF(TO_DAYS(MAX(started_date))=TO_DAYS(NOW()),1,0) AS worked_today ";
	$sql.= " FROM time_report ";
	$sql.= " WHERE report_id>".ToSQL($begin_report_id, "integer");
	$sql.= " GROUP BY user_id ";
	$db->query($sql);
	while($db->next_record()) {
		if ($db->f("worked_today")) {
			$worked_today[$db->f("user_id")] = true;
		}
	}
	
	$active_tasks = array();
	$sql = " SELECT u.user_id, at.task_title FROM users u INNER JOIN tasks at ON (at.responsible_user_id=u.user_id AND at.is_closed=0 AND at.is_wish=0 AND at.task_status_id=1) ORDER BY u.user_id ";
	$db->query($sql);
	while ($db->next_record()) {
		$active_tasks[$db->f("user_id")] = $db->f("task_title");
	}
	
	$users_records = array();
	$manager_users = array();
	$user_ids = array();
	
	$viart_team_size = 0;
	$yoonoo_team_size = 0;
	//main select - making users table
	$sql = " SELECT CONCAT(u.first_name,' ',u.last_name) AS user_name, u.user_id, u.privilege_id, u.manager_id, u.is_viart ";
	$sql.= " , COUNT(t.task_id) AS opened_tasks ";
	$sql.= " , IF(MIN(t.task_status_id)=1,1,0) AS is_online ";
	$sql.= " , MIN( IF (t.task_status_id=4 OR t.task_type_id=3, 1, TO_DAYS(t.planed_date) - TO_DAYS(now())) )-0 AS pdys ";
	$sql.= " , IF(dn.note_id,1,0) as delay_note ";
	$sql.= " , IF(w.user_id,1,0) AS warning ";
	$sql.= " , r.reason_name as reason_name ";
	$sql.= " , IF(dof.end_date,dof.end_date,0) as end_date, dn.notes AS delay_notes, dof.notes AS off_notes, dof.period_title AS off_title ";
//	$sql.= " , at.task_title AS active_task_title ";
	$sql.= " FROM users u ";
	$sql.= " LEFT JOIN tasks t ON (t.responsible_user_id=u.user_id AND t.is_closed=0 AND t.is_wish=0) ";
	$sql.= " LEFT JOIN delay_notifications dn ON (dn.note_date = DATE(NOW()) AND dn.user_id =u.user_id) ";
	$sql.= " LEFT JOIN warnings w ON (DATE(w.date_added) = DATE(NOW()) AND w.user_id =u.user_id) ";
	$sql.= " LEFT JOIN days_off dof ON (dof.user_id=u.user_id AND dof.start_date <= DATE(NOW()) AND dof.is_paid=0 AND dof.end_date >= DATE(NOW())) ";
	$sql.= " LEFT JOIN reasons r ON (r.reason_id=dof.reason_id) ";
//	$sql.= " LEFT JOIN tasks at ON (at.responsible_user_id=u.user_id AND at.is_closed=0 AND at.is_wish=0 AND at.task_status_id=1) ";	
	$sql.= " WHERE u.is_deleted IS NULL ";
	$sql.= " GROUP BY u.user_id ";
	$sql.= " ORDER BY user_name	";
	
	$db->query($sql);
	
	$user_row = 0;
	while($db->next_record()) {		
		$user_id = $db->f("user_id");
		$manager_id = $db->f("manager_id");
		$users_records[$user_row] = $db->Record;
		$user_ids[$user_id] = $user_row;
		if ($manager_id>0) {
			$manager_users[$manager_id][$user_row] = true;
		}
		if (isset($worked_today[$user_id])) {
			$users_records[$user_row]["worked_today"] = true;
		} else {
			$users_records[$user_row]["worked_today"] = false;
		}		
		$user_row++;
	}

	foreach ($users_records as $user)
	{
		if ($user["privilege_id"]==3 || $user["privilege_id"]==4 || $user["privilege_id"]==5 || $user["is_viart"]==0)
		{
    		print_user_name("manager", $user, $active_tasks);
    		if ($user["is_viart"]==1) {    		
    			$T->parse($viart_block_name, true);
    			$viart_team_size++;
    			
    			if (isset($manager_users[$user["user_id"]]) && is_array($manager_users[$user["user_id"]])) {
    				foreach ($manager_users[$user["user_id"]] as $user_row=>$value) {
    					print_user_name("user", $users_records[$user_row], $active_tasks);
    					if ($users_records[$user_row]["is_viart"]==1) {
    						$T->parse($viart_block_name, true);
    						$viart_team_size++;
    					} else {
    						$T->parse($yoonoo_block_name, true);
    						$yoonoo_team_size++;
    					}
    				}
    			}
    			
    		} else {
    			$T->parse($yoonoo_block_name, true);
    			$yoonoo_team_size++;
    		}
		}
	}
	$T->set_var($viart_team_count, $viart_team_size);
	$T->set_var($yoonoo_team_count, $yoonoo_team_size);
}

function print_user_name($user_type, $user, $active_tasks)
{
	global $T;
	
	$user_name_in_list = $user["user_name"];
   	$spacer = "";
   	$user_name_sv = "";
   	$user_information = "";
   	
   	$T->set_var("v_styleperson",$user_type);
   	$T->set_var("s_styleperson",$user_type);   	
	if ($user["pdys"]<0 ) {
		$user_name_in_list = $spacer."<span>! </span>".$user_name_in_list;
	} else {
		$user_name_in_list = $spacer."<span style='visibility: hidden;'>! </span>".$user_name_in_list;
	}
    
	if ($user["is_online"]) {
		$user_name_sv ="<b>".$user_name_in_list."</b> <font color=\"green\">(online)</font>";
	} elseif ($user["worked_today"] == "1") {
		$user_name_sv = $user_name_in_list." <font color=\"red\">(offline)</font>";
	} elseif ($user["delay_note"]) {
		$user_name_sv = $user_name_in_list." <font color=\"#FE8181\">(delayed)</font>";
		$user_information = "<font color=\"#FE8181\">".trim($user["delay_notes"])."</font>";
	} elseif ($user["warning"]) {
		$user_name_sv = $user_name_in_list." <font color=\"red\"><b>(warning)</b></font>";
	} elseif ($user["end_date"]) {
		$norm = norm_sql_date($user["end_date"]);
		$end_date = substr($norm, 0, strlen($norm)-5);
		$user_name_sv = $user_name_in_list." <font color=blue>(". $user["reason_name"]. " till " . $end_date . ")</font>";
		if (strlen($user["off_notes"]) && trim(strtolower($user["off_notes"])) != trim(strtolower($user["off_title"]))) {
			$off_description = $user["off_title"] . " (" . $user["off_notes"]. ")";
		} else {
			$off_description = $user["off_title"];
		}
		$user_information = "<font color=\"blue\">".$off_description."</font>";
	} else {
		$user_name_sv = $user_name_in_list." <font color=\"red\">(offline)</font>";
	}
	
	if (isset($active_tasks[$user["user_id"]]) && strlen($active_tasks[$user["user_id"]])) {
		if (strlen($user_information)) {
			$user_information = "&nbsp;".$user_information;
		}
		$user_information.= "<font color=\"black\"><b>".htmlspecialchars($active_tasks[$user["user_id"]])."</b></font>".$user_information;
	}
	
	$T->set_var("user_information_div", "");
	$T->set_var("opened_tasks", $user["opened_tasks"]);
	$T->set_var("user_name",$user_name_sv);
	$T->set_var("user_id",$user["user_id"]);
	if (strlen($user_information)) {
		$T->set_var("user_information", $user_information);
		$T->parse("user_information_div", false);
	}	
}

function show_manager_notes($block_name, $user_id, $show_edit_link = false)
{
	global $db, $T, $points_array;
	// manager notes reports
	$sql = " SELECT mr.report_id, mr.manager_id, CONCAT(u.first_name,' ',u.last_name) AS manager_name ";
	$sql.= ", mr.morning_notes, mr.evening_notes, mr.points ";
	$sql.= " FROM managing_reports mr ";
	$sql.= " INNER JOIN users u ON (mr.manager_id=u.user_id) ";
	$sql.= " WHERE mr.user_id=".ToSQL($user_id, "integer");
	$sql.= " AND TO_DAYS(mr.date_added)=TO_DAYS(NOW()) ";
	$db->query($sql);
	if ($db->next_record()) {
		$morning_note = nl2br(htmlspecialchars($db->f("morning_notes")));
		$evening_note = nl2br(htmlspecialchars($db->f("evening_notes")));
		$points = $db->f("points");
		$manager_name = $db->f("manager_name");
		$T->set_var("report_id", $db->f("report_id"));
		if ($morning_note) {
			$T->set_var("manager_name", $manager_name);
			$T->set_var("morning_note", $morning_note);
			if ($show_edit_link) {
				$T->parse("edit_note_link", false);
			} else {
				$T->set_var("edit_note_link", "");
			}
			
			$T->parse("morning_note_row", false);
		} else {
			$T->set_var("morning_note_row", "");
		}
		
		if (isset($points_array[$points])) {
			$point_description = $points_array[$points];
		} else {
			$point_description = "";
		}
		if ($evening_note) {
			$T->set_var("point_description", $point_description);
			$T->set_var("evening_note", $evening_note);
			$T->parse("evening_note_row", false);
		} else {
			$T->set_var("evening_note_row", "");
		}
		
		$T->parse($block_name, false);
		$parsed = true;
	} else {
		$T->set_var($block_name, "");
		$parsed = false;
	}
	// end manager notes	
	return $parsed;
}

function show_today_tasks($block_name, $user_id)
{
	global $db, $T;
	
	$parsed = false;
	$task_number = 0;
	$in_progress_task_id = false;
	$in_progress_task_in_time_report = false;	
	
	$sql = " SELECT p.project_title, t.task_id, t.task_title, t.completion, t.planed_date AS pdate ";
	$sql.= ", ls.status_desc, lt.type_desc, t.task_status_id, t.estimated_hours ";
	$sql.= ", IF (TO_DAYS(t.planed_date) < TO_DAYS(now()) AND t.task_status_id!=4 AND t.task_type_id!=3,1,0 ) AS ifdeadlined ";
	$sql.= ", IF (TO_DAYS(t.planed_date) = TO_DAYS(now()) AND t.task_status_id!=4 AND t.task_type_id!=3,1,0 ) AS iftoday ";
	$sql.= " FROM tasks t ";
	$sql.= " INNER JOIN projects p ON (t.project_id = p.project_id) ";
	$sql.= " INNER JOIN lookup_task_types lt ON (t.task_type_id = lt.type_id) ";
	$sql.= " INNER JOIN lookup_tasks_statuses ls ON (t.task_status_id = ls.status_id) ";
	$sql.= " WHERE t.responsible_user_id = " . ToSQL($user_id, "number")." AND t.is_closed=0 AND t.is_wish=0 AND t.task_status_id=1 ";
	$sql.= " ORDER BY t.priority_id, t.project_id, t.task_id DESC ";
	$db->query($sql);
	while ($db->next_record()) {
		$task_number++;
		$in_progress_task = $db->Record;
		$in_progress_task_id = $db->f("task_id");
	}	
	
	$sql = " SELECT p.project_title, t.task_id, t.task_title, t.completion, t.task_status_id, t.planed_date AS pdate ";
	$sql.= ", ls.status_desc, lt.type_desc, SUM(tr.spent_hours) AS task_hours ";
	$sql.= ", IF (TO_DAYS(t.planed_date) < TO_DAYS(now()) AND t.task_status_id!=4 AND t.task_type_id!=3,1,0 ) AS ifdeadlined ";
	$sql.= ", IF (TO_DAYS(t.planed_date) = TO_DAYS(now()) AND t.task_status_id!=4 AND t.task_type_id!=3,1,0 ) AS iftoday ";
	$sql.= " FROM time_report tr ";
	$sql.= " INNER JOIN tasks t ON (tr.task_id=t.task_id) ";
	$sql.= " INNER JOIN projects p ON (t.project_id = p.project_id) ";
	$sql.= " INNER JOIN lookup_task_types lt ON (t.task_type_id = lt.type_id) ";
	$sql.= " INNER JOIN lookup_tasks_statuses ls ON (t.task_status_id = ls.status_id) ";
	$sql.= " WHERE tr.user_id = ".ToSQL($user_id, "number")." AND TO_DAYS(tr.started_date)=TO_DAYS(NOW()) ";
	$sql.= " GROUP BY t.task_id ";
	$sql.= " ORDER BY tr.started_date, t.priority_id, t.task_id DESC ";
	
	$db->query($sql);
	
	$done_tasks = array();
	while ($db->next_record()) {
		$task_id = $db->f("task_id");		
		if ($task_id==$in_progress_task_id) {
			$in_progress_task_in_time_report = true;
		}
		$done_tasks[] = $db->Record;		
	}	
	if (!$in_progress_task_in_time_report && isset($in_progress_task)) {
		$done_tasks[] = $in_progress_task;
	}
		
	if (sizeof($done_tasks)) {
		foreach($done_tasks as $task)
		{
			$task_number++;
			$T->set_var($task);			
			
			if (isset($task["task_hours"]) && $task["task_hours"] ) {
				$T->set_var("spent_hours",trim(Hours2HoursMins($task["task_hours"])));
			} else {
				$T->set_var("spent_hours","");
			}
			
			if (strtolower($task["type_desc"])!="periodic") {
				$T->set_var("completion", intval($task["completion"])."%");
			} else {
				$T->set_var("completion", "periodic");
			}
			$T->parse("task_row",true);
		}
	} else {
		$T->set_var("task_row","");
	}	
	
	if ($task_number) {	
		$T->parse($block_name, false);
		$parsed = true;
	} else {
		$T->set_var($block_name, "");
		$parsed = false;
	}
	return $parsed;
}

function show_active_bugs($block_name, $user_id)
{
	global $db, $T;
	
	$sql = 'SELECT COUNT(bug_id) as bugs_count FROM bugs WHERE user_id = '.ToSQL($user_id, "Text").' AND is_resolved = 0';
	$db->query($sql);
	$db->next_record();
	$bugs_count = &$db->Record['bugs_count'];
	if ($bugs_count)
	{
		$T->set_var($block_name, $bugs_count.' active bugs');
	} else {
		$T->set_var($block_name, '');
	}
}

function show_report_tasks_list($report_user_id, $is_viart, $rp)
{
	global $T, $db, $statuses_classes;
	
	$T->set_var("rp", $rp);
	$T->set_var("report_user_id", intval($report_user_id));
	$T->set_var("is_viart", intval($is_viart));
	if (!$is_viart) { $is_viart=0;}
	if ($report_user_id) {
		$where = " AND t.responsible_user_id=" . ToSQL($report_user_id,"integer",false,false);
		$T->set_var("page_title","Personal Report");

		show_manager_notes("manager_notes", $report_user_id, true);
		show_today_tasks("today_tasks", $report_user_id);
		
	} else {
		if ($is_viart) { $T->set_var("page_title","ViArt Team");}
		else           { $T->set_var("page_title","Spotight Team");}
	  	$where = " AND u.is_viart=" . ToSQL($is_viart,"integer",false,false);
	  	$T->set_var("manager_notes", "");
	  	$T->set_var("today_tasks", "");
	}

	//-- link to Time Spending Report
	if ($T->block_exists("timereport_link_user"))
	{
	if ($report_user_id) {
		//$T->set_var("timereport_link_all", "");
		$T->parse("timereport_link_user", false);
	} else {
		$T->set_var("timereport_link_user", "");
		//$T->parse("timereport_link_all", false);
	}
	}

	//time slots
	$now                    = time();
	$last_completition_date = $now;
	$time_slots             = array(); // array with date as key and available hours as value
	$time_slots_allocated   = array(); // array with date as key and array of allocated tasks as value
	for ($i = 0; $i < 60; $i++) { //allocate time for 60 days ahead starting from today
		$dta      = $now + $i * 24 * 60 * 60;
		$week_day = date('l',$dta);
		$time_slots[$dta] = 8; //inititally we add 8 hours for each day
		//then we remove weekends
		if (($week_day == "Sunday") || ($week_day == "Saturday")) $time_slots[$dta] = 0;
		//echo "formatted:".date('D jS M Y',$dta)."<br>";
	
		//then remove natinal holidays
		//and finally remove user's holidays
	}
	
	
	//-- tasks list
	$sql = "SELECT *, 
					t.responsible_user_id AS r_user_id, 
					u.user_id AS user_id,
					t.creation_date AS cdate, 
					t.planed_date AS pdate, 
					t.priority_id AS priority_id,					
					IF(mu.first_name IS NOT NULL,CONCAT(', priority was set by ',mu.first_name,' ',mu.last_name),'') AS manager_name,
					CONCAT(u.first_name,' ',u.last_name) AS user_name,
					DATE_FORMAT(t.creation_date, '%d %b %Y') AS creation_date, 
					DATE_FORMAT(t.planed_date, '%d %b %Y') AS planed_date, 
					IF (TO_DAYS(t.planed_date) < TO_DAYS(now()) AND t.task_status_id!=4 AND t.task_type_id!=3,1,0 ) AS ifdeadlined, 
					IF (TO_DAYS(t.planed_date) = TO_DAYS(now()) AND t.task_status_id!=4 AND t.task_type_id!=3,1,0 ) AS iftoday, 
					IF( t.task_status_id =1, t.actual_hours +( UNIX_TIMESTAMP( NOW( ) ) - UNIX_TIMESTAMP( t.started_time ) )/3600.0000 , t.actual_hours ) AS actual_hours_live,				
					
					IF(t.task_status_id=2, 1, 0) AS sorter /*Tasks with status 'On Hold' will be at the end of query*/
			FROM	(((((
					tasks t
					LEFT JOIN projects p ON (p.project_id=t.project_id))
					LEFT JOIN lookup_task_types lt ON (lt.type_id=t.task_type_id))
					LEFT JOIN lookup_tasks_statuses ls ON (ls.status_id=t.task_status_id))
					LEFT JOIN users u ON (u.user_id=t.responsible_user_id))
					LEFT JOIN users mu ON (mu.user_id=u.priority_set_by))
			WHERE	t.is_wish=0 
					AND t.is_closed=0
					" . $where . "
			ORDER BY t.responsible_user_id, sorter, t.priority_id ";
	$db->query($sql);		
	$project_name = ""; $k = true; $id = 1; $cur_user_id = 0; $user_number = 0;
	$count_users  = array();

	
	if ($db->next_record()) {
		do {
			$task_id = $db->f("task_id");
			$T->set_var($db->Record);
			if ($db->Record["cdate"] == "0000-00-00 00:00:00") { $T->set_var("creation_date","");}
			if ($db->Record["pdate"] == "0000-00-00 00:00:00") { $T->set_var("planed_date","");}

			$project_name = $db->Record["project_title"];
	    	$T->set_var("project_title",$project_name);
	    	$task_title_slashed = str_replace("\"","",str_replace("'", "", $db->Record["task_title"]));
	    	
	    	$T->set_var("task_title_slashed", $task_title_slashed);
	    	$T->set_var("task_title_xml",htmlspecialchars($db->Record["task_title"]));
	    	$T->set_var("completion_percent", ($db->Record["completion"] ? $db->Record["completion"]."%" : "&nbsp;"));

	  		$T->set_var("estimated_title",$db->Record["estimated_title"]);

			//NEW ESTIMATES OUTPUT//
			$estimated_hours = $db->Record["estimated_hours"];

			$estimated_hours_title = "";
			$estimated_days        = 0;
			if ($estimated_hours) {
				if ($estimated_hours>0 && $estimated_hours<1)  { $estimated_hours_title .= round($estimated_hours*60)." min";}
				if ($estimated_hours==1) { $estimated_hours_title .= "1 hour";}
				if ($estimated_hours>1 && $estimated_hours<16) {
					$estimated_hours_title .=( fmod($estimated_hours,1) ? sprintf("%1.2f hours",$estimated_hours) : floor($estimated_hours)." hours");
				}
				if ($estimated_hours>=16) {
					$estimated_hours_title.= ( fmod($estimated_hours,8) ? sprintf("%1.2f days",$estimated_hours/8) : floor($estimated_hours/8)." days");
				}
				if ($estimated_hours > 8) $estimated_days = $estimated_hours / 8;
			} else $estimated_hours = 1;

			//find the first available slot to allocate tasks
			$allocated_left = $estimated_hours;
			//echo "estimated_hours:$estimated_hours\n";
			foreach($time_slots as $dta => $available_hours) {
				if ($available_hours) {
					//echo "available_hours:$available_hours\n";
     				if ($allocated_left) {
						//echo "allocated_left:$allocated_left\n";
     					$arr = array();
     					if (isset($time_slots_allocated[$dta])) $arr = $time_slots_allocated[$dta];
     					if ($available_hours > $allocated_left) {
     						$time_slots[$dta]     = $available_hours - $allocated_left;
				    		$arr[$task_id]        = $allocated_left;
     						$allocated_left       = 0;
     					} else {
     						$time_slots[$dta] = 0;
     						$allocated_left   = $allocated_left - $available_hours;
     						$arr[$task_id]    = $available_hours;
     					}
     					$time_slots_allocated[$dta] = $arr;
     				} else break;
     			}	
			}
			//print_r ($time_slots);
			//exit;
			//print_r($time_slots_allocated);
			//exit;
			//completion_date is the last allocated date
			$completion_date = $now;
			foreach($time_slots_allocated as $dta => $slots) {
				foreach($slots as $allocated_task_id => $hours_allocated) {
					if ($allocated_task_id == $task_id) {
						$completion_date = (int) $dta;
					}
				}
			}
			//$completition_date = $last_completition_date + ceil($estimated_days * 24 * 60 * 60);
			//$last_completition_date = $completion_date;

	  		$T->set_var("estimated_hours_title",$estimated_hours_title);
	  		$T->set_var("completion_date"      ,date('D jS M Y',$completion_date));

			if ($k)		{ $T->set_var("STATUS",$statuses_classes[$db->Record["status_id"]] );}
				else	{ $T->set_var("STATUS",$statuses_classes[$db->Record["status_id"]]."2");}

			if ($db->Record["ifdeadlined"]) {
				$T->set_var("task_title","<font color=\"red\"><b>".$db->Record["task_title"]."</b></font>");
				if ($db->f("task_status_id")!=1) {
					if ($k) { $T->set_var("STATUS","Deadline");}
						else {$T->set_var("STATUS","Deadline2");}
				}
			}
			if ($db->Record["iftoday"]) {
  				$T->set_var("task_title",$db->Record["task_title"]);
				if ($db->f("task_status_id")!=1) {
					if ($k) $T->set_var("STATUS","Today"); else $T->set_var("STATUS","Today2");
				}
			}
			$T->set_var("actual_hours",to_hours($db->Record["actual_hours"]));
			
			if ($db->f("task_status_id")==1) {
				$T->set_var("a_id", intval($user_number));
				$T->set_var("active_task_id", intval($db->f("task_id")));
				$actual_live_hours = $db->f("actual_hours_live");
				$T->set_var("actual_hours", Hours2HoursMins($actual_live_hours));
				if ($actual_live_hours) {
					$T->set_var("actual_timestamp", $actual_live_hours*3600000);
				} else {
					$T->set_var("actual_timestamp", "0");
				}
				$T->parse("activeTaskRow", true);
				$user_number++;				
			}
			
						
			$k = !$k;			
			if ($cur_user_id == $db->Record["r_user_id"]) {
				$T->set_var("tasks_header","");
			} else {
				$cur_user_id = $db->Record["r_user_id"];
				$id = 1;				
				$T->parse("tasks_header",false);				
			}

			$T->set_var("id",$id);
			$id++;
			
			
			
			if (!array_key_exists($db->Record["r_user_id"],$count_users)) {$count_users[$db->Record["r_user_id"]] = 0;}
			$count_users[$db->Record["r_user_id"]]++;

			$completion=$db->Record["completion"];

			$T->set_var("time_left_estimate",($completion>0 && $completion<=100 ? to_hours($estimated_hours*(1-0.01*$completion),true) : "" ));
			$T->set_var("time_left_actual",($completion>5 && $completion<=100 ? to_hours((100-$completion)/$completion*($db->Record["actual_hours"] ),true) : "" ));

			
			$T->parse("tasks",true);
		}
	    while ($db->next_record());

	    $time_slot = $completion_date;
	    if ($time_slots[$completion_date] < 1) {
	    	foreach($time_slots as $dta => $hours_available) {
	    		if ($hours_available >= 1)  {
	    			$time_slot = $dta;
	    			break;
	    		}
	    	}
	    }
	    $T->set_var("time_slot",date('D jS M Y',$time_slot));
	    $T->set_var("totalRows",$id -1);
	    $T->set_var("no_mytasks","");
	} else {
		$T->set_var("tasks","");
	}
	
	if ($user_number==0) {
		$T->set_var("activeTaskRow","");
	}

	$dicUsers = "";
	foreach($count_users as $user_id=>$count) {		
		$dicUsers .= "dicUsers[$user_id] = $count;\n";
	}
	$T->set_var("dicUsers", $dicUsers);
	
  	$T->set_var("t_params", "");
  	$T->set_var("is_viart",  intval($is_viart) ? intval($is_viart) : "0");
  	$T->set_var("report_user_id", intval($report_user_id) ? intval($report_user_id) : "0");  
}

function show_documents($knowledge_base_block_name, $documents_block_name, $user_id) {
	global $db, $T, $permission_groups;
	
	$db->query("SELECT is_viart FROM users WHERE user_id=".ToSQL($user_id, "integer"));
	$db->next_record();
	$is_viart = $db->f("is_viart");
	
	if ($is_viart) {		
		if ($T->block_exists($documents_block_name)) {
			//get documents
			$sql = "SELECT doc_id, doc_name, author_id ";
			$sql.= ", IF (date_last_modified IS NOT NULL, DATE_FORMAT(date_last_modified, '%d %b %Y'),DATE_FORMAT(date_added, '%d %b %Y')) AS date_modified ";
			$sql.= ", allow_view, allow_edit FROM docs ORDER BY doc_id ASC";			
			$db->query($sql);
			$doc = array();
			$docs_here = false;
			
			while($db->next_record()) {
				$doc[] = $db->Record;
			}
			
			if(sizeof($doc)) {
				foreach ($doc as $d) {
					$doc_id = $d["doc_id"];
					$doc_name = $d["doc_name"];
					$author_id = $d["author_id"];
					$allow_edit_string = $d["allow_edit"];
					$allow_view_string = $d["allow_view"];
					$date_modified = $d["date_modified"];
					$user_allow_edit = is_allowed(GetSessionParam("UserID"), $author_id, get_set_array($allow_edit_string, $permission_groups));
					$user_allow_view = is_allowed(GetSessionParam("UserID"), $author_id, get_set_array($allow_view_string, $permission_groups));
					$T->set_var("doc_name", $doc_name);
					$T->set_var("doc_id", $doc_id);
					$T->set_var("date_modified", $date_modified);						
					if ($user_allow_view) {
						if ($user_allow_edit && $T->block_exists("doc_edit_link")) {
							$T->parse("doc_edit_link", false);
						} else {
							$T->set_var("doc_edit_link", "");
						}
						
						if ($T->block_exists("doc")) {
							$T->parse("doc",true);
							$docs_here = true;
						}				
					}				
				}
			}
			if (!$docs_here) {
				$T->set_var("doc", "");
			}
			
			$T->parse($documents_block_name, false);
		}
		if ($T->block_exists($knowledge_base_block_name)) {
			$T->parse($knowledge_base_block_name,false);
		}
	} else {
		$T->set_var($documents_block_name, "");
		$T->set_var($knowledge_base_block_name, "");
	}
}

function short_title($long_title, $max_length)
{
	if (strlen($long_title)>$max_length) {
		$short_title = substr($long_title, 0, $max_length-2)."...";
	} else {
		$short_title = $long_title;
	}
	return $short_title;
}


///////////////////////////////////////////////////////////////////////////////////////
// Tasks actions - all insert/update/delete actions with tasks should be stored here //
///////////////////////////////////////////////////////////////////////////////////////

function close_task($task_id, $return_page="index.php")
{
	global $db;
	
	if (function_exists('CountTimeProjects')) {
		CountTimeProjects($task_id);
	}
	if (function_exists('insert_responses')) {
		insert_responses($task_id, 0);
	}
	$sql = "UPDATE tasks SET is_closed=1 WHERE task_id=" . ToSQL($task_id,"integer");
	$db->query($sql);

	$sql = "SELECT responsible_user_id, task_status_id, started_time, task_title, priority_id, ".
	       " ((UNIX_TIMESTAMP(NOW())-UNIX_TIMESTAMP(started_time))/3600.0000) as spent_hours ".
	       " FROM tasks WHERE task_id=" . ToSQL($task_id,"integer",false,false);
	$db->query($sql);
	if ($db->next_record()){
		$responsible_user_id = $db->Record["responsible_user_id"];
		$task_title			= $db->Record["task_title"];
		$task_status_id = $db->Record["task_status_id"];
		$started_time	= $db->Record["started_time"];
		$spent_hours	= $db->Record["spent_hours"];
		$priority_id = $db->Record["priority_id"];

		@mail("artem@viart.com.ua","Monitor: Task '$task_title' closed -#id=$task_id","Task '$task_title' closed -#id=$task_id\nTask closed by ".GetSessionParam("UserName"),"From:monitor@viart.com.ua");
		// -- task was in progress, so we need to stop it first and set status waiting
		if ($task_status_id == 1) {
			task_add_hours($task_id, $spent_hours, $responsible_user_id, 8, false, false, $started_time, false);
		}
		
		//move priorities
		$count = 0;
		$sql  = "SELECT COUNT(*) AS c FROM tasks ";
		$sql .= " WHERE responsible_user_id = " . $responsible_user_id . " AND is_wish = 0 AND is_closed = 0 AND priority_id > " .ToSQL($priority_id,"integer");
		$db->query($sql);

		if ($db->next_record()) $count = $db->Record["c"];
		if ($count) {
			$sql  = "UPDATE tasks SET priority_id = priority_id - 1 ";
			$sql .= "WHERE responsible_user_id = " . ToSQL($responsible_user_id, "integer", false) . " AND is_wish = 0 AND is_closed = 0 AND priority_id > " . $priority_id;
			$db->query($sql);
		}		
	}

	if ($return_page) {
		header("Location: ".$return_page);
		exit;
	}
}

function close_tasks($tasks_ids, $return_page = "") {
	if (strlen($tasks_ids)) {
		$ids = explode(",", $tasks_ids);
		for($i = 0; $i < sizeof($ids); $i++) {
			close_task($ids[$i], "");
		}
	}
	
	if ($return_page) {
		header("Location: ".$return_page);
		exit;
	}
}


function start_task($task_id, $completion, $return_page = "index.php")
{
	global $db;

	$task_started = false;	
	
	$sql = " SELECT responsible_user_id FROM tasks WHERE task_id=".ToSQL($task_id, "integer");	
	$db->query($sql);
	if ($db->next_record()) {
		$task_user_id = $db->f("responsible_user_id");

		if ($task_user_id && $task_user_id==GetSessionParam("UserID")) {
			//-- first STOP started tasks
			$sql  = " SELECT t.task_id FROM tasks t WHERE t.task_status_id =1 AND t.is_wish =0 AND t.is_closed =0 ";
			$sql .= " AND t.responsible_user_id=".ToSQL($task_user_id, "integer", false);
			$db->query($sql);
			if($db->next_record()) {
				stop_task($db->f("task_id"), $completion, false);
			}

			//-- now START specified task
			
			$sql = "UPDATE tasks SET is_closed=0, task_status_id = 1, responsible_user_id=999, started_time = NOW(), modified_date = NOW() WHERE task_id = ".ToSQL($task_id, "integer");
	   		$db->query($sql);
	   		$sql = "UPDATE tasks SET responsible_user_id=" . $task_user_id . " WHERE task_id =".ToSQL($task_id, "integer");
	   		$db->query($sql);
   		
			//$sql = "UPDATE tasks SET task_status_id = 1, started_time = NOW() WHERE task_id = ".ToSQL($task_id, "integer");
   			$task_started = true;
		}
	}

   	if ($return_page) {
		header("Location: ".$return_page);
		exit;
   	} else {
   		return $task_started;
   	}
}

function stop_task($task_id, $completion, $return_page = "index.php", $auto_stop = 0, $auto_spent_hours = 0, $auto_report_date = 0, $stop_status_id = 8)					
{
	global $db;
	
	if (function_exists('CountTimeProjects')) {
		CountTimeProjects($task_id);
	}

	$sql  = " SELECT responsible_user_id, started_time, task_status_id ";
	$sql .= " ,((UNIX_TIMESTAMP(NOW())-UNIX_TIMESTAMP(started_time))/3600.0000) as spent_hours FROM tasks WHERE task_id = ".ToSQL($task_id, "integer");
	$db->query($sql);
	if ($db->next_record()) {
		$spent_hours  = $db->Record["spent_hours"];
		$user_id      = $db->Record["responsible_user_id"];
		$task_status_id = $db->Record["task_status_id"];
		$started_time = $db->Record["started_time"];
		if ($auto_stop) {
			$spent_hours = $auto_spent_hours;
			$report_date = $auto_report_date;
		} else {
			$report_date = false;
		}
		// Stop only runnig tasks
		if ($task_status_id == 1) {
			cvs_check_uploads($task_id, $started_time);
			update_task($task_id, array("completion" => $completion));
			task_add_hours($task_id, $spent_hours, $user_id, $stop_status_id, false, $auto_stop, $started_time, $report_date);			
		}
	}
	
	if ($return_page) {
		header("Location: ".$return_page);
		exit;
	}
}

function cvs_check_uploads($task_id, $started_time) {
	global $db;
	
	$cvs_module = "";
	$cvs_login  = "";
	$is_cvs_notification = "";
	$user_id = GetSessionParam("UserID");
	
	$sql  = " SELECT cvs_login, is_cvs_notification FROM users";
	$sql .= " WHERE user_id=" . $user_id;
	$sql .= " AND is_cvs_notification=1";
	$sql .= " AND cvs_login IS NOT NULL AND cvs_login NOT LIKE '' ";
	$db->query($sql);
	if ($db->next_record()){
		$cvs_login           = $db->f("cvs_login");
		$is_cvs_notification = $db->f("is_cvs_notification");
		
		$sql  = " SELECT p.cvs_module, pp.cvs_module FROM projects p ";
		$sql .= " INNER JOIN tasks t ON t.project_id=p.project_id";
		$sql .= " LEFT JOIN projects pp ON pp.project_id=p.parent_project_id";
		$sql .= " WHERE t.task_id =" .ToSQL($task_id, "integer", false);
		$db->query($sql);
		if ($db->next_record()){
			$cvs_module = $db->f(0);
			if (!$cvs_module) {
				$cvs_module = $db->f(1);
			}			
		}
	}
	if ($is_cvs_notification && $cvs_module && $cvs_login) {
		list ($started_date, $started_time) = explode(" ", $started_time);
		
		$sql  = " SELECT last_commited FROM cvs_commits_log ";
		$sql .= " WHERE cvs_login="  . ToSQL($cvs_login, "text");
		$sql .= " AND cvs_module="   . ToSQL($cvs_module, "text");
		$sql .= " AND last_commited>=" . ToSQL($started_date, "text");		
		$db->query($sql);
		if (!$db->next_record()) {		
			$sql  = " SELECT * FROM cvs_modules_log ";
			$sql .= " WHERE user_id="    . $user_id;
			$sql .= " AND cvs_login="    . ToSQL($cvs_login, "text");
			$sql .= " AND cvs_module="   . ToSQL($cvs_module, "text");
			$sql .= " AND started_date=" . ToSQL($started_date, "text");
			$db->query($sql);
			if (!$db->next_record()){
				$sql  = " INSERT INTO cvs_modules_log ";
				$sql .= " (user_id, cvs_login, cvs_module, started_date) VALUES (";
				$sql .= $user_id . ",";
				$sql .= ToSQL($cvs_login, "text") . ",";
				$sql .= ToSQL($cvs_module, "text") . ",";
				$sql .= ToSQL($started_date, "text") . ")";
				$db->query($sql);
			}
		}
	}
}

function assign_to_myself_task($task_id, $return_page = "index.php")
{
	global $db;
	
	insert_responses($task_id, 1);	
	$sql = "UPDATE tasks SET is_planned=1 WHERE task_id=".ToSQL($task_id, "integer", false);
	$db->query($sql);
	if ($return_page) {
		header("Location: ".$return_page);
		exit;
	}
}

function save_estimates($return_page)
{
	global $db;
	
	$tasks_priorities = array();
	$post_vars = get_post_vars();
	foreach($post_vars as $var_name=>$var_value)
	{
		$parts = split("_",$var_name);
		if ($parts[0] == "estimateuhours" && $parts[1])
		{
			$task_id  = $parts[1];
			$estimate = $var_value;
			if (!$estimate) $estimate = 0;
			else if ($estimate>0.01 && $estimate<10000)
			{
				update_estimate($task_id, $estimate);
			}
			$tasks_priorities[] = $task_id;
		}
	}
	$emails = "";
	
	send_tasks_message($tasks_priorities, "Monitor: your tasks estimates were changed", 
		"Your tasks estimates have been changed by " . GetSessionParam("UserName") . "\n\n".
		"Please visit http://www.viart.com.ua/monitor for details");

	if ($return_page) {
		header("Location: ".$return_page);
		exit;
	}
}

function update_estimate($task_id, $estimate)
{
	global $db;
	$sql = "UPDATE tasks SET estimated_hours=".ToSQL($estimate, "number")." WHERE task_id=".ToSQL($task_id, "integer");
	$db->query($sql);

	$sql = "INSERT INTO estimates (estimate_id,task_id,estimate_time,date_added,user_added) ".
	       " VALUES(0, ".ToSQL($task_id, "integer").", ".ToSQL($estimate, "number").", NOW(), ".ToSQL(GetSessionParam("UserID"), "number").")";
	$db->query($sql);
}

function save_priorities($return_page)
{
	$tasks_priorities = array();	
	$post_vars = get_post_vars();
	
	foreach($post_vars as $var_name=>$var_value) {			
		$parts = split("_",$var_name);
		$manager_id = GetSessionParam("UserID");
		if ($parts[0] == "priority" && $parts[1]) {				
			$task_id  = $parts[1];
			$priority = $var_value;
			$priority_new = GetParam($parts[0]."_".$parts[1]);
			$tasks_priorities[] = $task_id;
			set_task_priority($task_id, $priority);
		}
	}

	send_tasks_message($tasks_priorities, "Monitor: your priorities were changed", 
		"Your priorities have been changed by " . GetSessionParam("UserName") . "\n\n".
		"Please visit http://www.viart.com.ua/monitor for details");

	if ($return_page) {
		header("Location: ".$return_page);
		exit;
	}	
}

function send_tasks_message($tasks_array, $message_title, $message_body)
{
	global $db;
	if (sizeof($tasks_array)) {
		$emails = "";
		$sql = " SELECT	DISTINCT responsible_user_id, email ";
		$sql.= " FROM	tasks t, users u ";
		$sql.= " WHERE	t.responsible_user_id=u.user_id AND t.task_id IN (" . join(",",$tasks_array) . ")";
		$db->query($sql);
		while ($db->next_record())
		{
			if ($emails) $emails.= ",";
			$emails .= $db->Record["email"];
		}
		if ($emails) {
			@mail($emails, $message_title, $message_body);
		}
	}
}

function set_task_priority($task_id, $priority, $priority_set_by=true)
{
	global $db;
	
	$sql = "SELECT priority_id, responsible_user_id FROM tasks WHERE task_id = " . ToSQL($task_id,"integer",false,false);
	$db->query($sql);
	if ($db->next_record()) {
		$priority_old = $db->f("priority_id");
		$user_id_task = $db->f("responsible_user_id");
	
		$sql = "UPDATE tasks SET priority_id = ".ToSQL($priority,"integer")." WHERE task_id = ".ToSQL($task_id,"integer");
		$db->query($sql);		
				
		if ($priority_old != $priority && $priority_set_by) {
			$sql  = " UPDATE users SET priority_set_by = " . ToSQL(GetSessionParam("UserID"),"integer");
			$sql .= " WHERE user_id =" . ToSQL($user_id_task,"integer");
			$db->query($sql);
		}
	}
	
}

function task_set_time_report_hours($task_id)
{
	global $db;
	$sql = "UPDATE tasks t SET t.actual_hours=(SELECT SUM(tr.spent_hours) FROM time_report tr WHERE tr.task_id=".ToSQL($task_id,"integer").") WHERE t.task_id=".ToSQL($task_id,"integer");
	$db->query($sql);
	return $true;
}

function task_add_hours($task_id, $spent_hours, $user_id=false,
					$task_status_id=false, $report_id=false, $auto_stop=false,
					$start_datetime=false, $report_datetime=false)
{
	global $db;	
	$sql = " UPDATE tasks SET actual_hours = (actual_hours + ".ToSQL($spent_hours, "number").") ";	
	
	if ($task_status_id) {
		$sql.= ", task_status_id=".ToSQL($task_status_id, "integer", false);
	}
	$sql .= " WHERE task_id=".ToSQL($task_id, "integer");
	$db->query($sql);			

	if (!$report_id && $user_id && $start_datetime) {
		// update time report
		$sql  = "INSERT INTO time_report (user_id, started_date, task_id, report_date, spent_hours, auto_stop) VALUES (";
		$sql .= ToSQL($user_id, "integer");
		$sql .= ", ".ToSQL($start_datetime, "text");
		$sql .= ", ".ToSQL($task_id, "integer");
   		if ($report_datetime) {
   			$sql.= ", ".ToSQL($report_datetime, "text");
		} else {
			$sql.= ", NOW() ";
		}
		$sql .= ", ".ToSQL($spent_hours, "number");
		$sql .= ", ".ToSQL($auto_stop, "integer", false).")";		
		$db->query($sql);
	} elseif($report_id) {
		//-- Write report about time spent for the task
   		$sql = "UPDATE time_report SET ";
   		if ($report_datetime) {
   			$sql.= " report_date = ".ToSQL($report_datetime, "text").", ";
		} else {
			$sql.= " report_date = NOW(), ";
		}
		$sql.= " spent_hours = spent_hours + ".ToSQL($spent_hours,"number");
		$sql.= " WHERE report_id = ".ToSQL($report_id, "integer");
   		$db->query($sql);
	}
}

/**
 * Format message body for email: same as edit_task (quotes, markdown, linkify) with inline styles for email clients.
 */
function format_message_for_email($text) {
	if (trim($text) === '') {
		return '(no text)';
	}
	$url_pattern = '/(https?:\/\/|ftp:\/\/)[^\s<>\[\]"\']+/i';
	// Simple markdown on one line (already escaped); URLs protected so _ in URLs are not italic
	$markdown_line = function($line) use ($url_pattern) {
		$urls = array();
		$t = preg_replace_callback($url_pattern, function ($m) use (&$urls) {
			$urls[] = $m[0];
			return "\x01U" . (count($urls) - 1) . "\x01";
		}, $line);
		if (preg_match('/^###\s+(.*)$/', $t, $m)) {
			$t = '<h3 style="font-size:1em;margin:6px 0 4px;font-weight:bold;">' . $m[1] . '</h3>';
		} elseif (preg_match('/^##\s+(.*)$/', $t, $m)) {
			$t = '<h2 style="font-size:1.1em;margin:6px 0 4px;font-weight:bold;">' . $m[1] . '</h2>';
		} elseif (preg_match('/^#\s+(.*)$/', $t, $m)) {
			$t = '<h1 style="font-size:1.2em;margin:8px 0 4px;font-weight:bold;">' . $m[1] . '</h1>';
		} else {
			$t = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $t);
			$t = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $t);
			$t = preg_replace('/__(.+?)__/s', '<strong>$1</strong>', $t);
			$t = preg_replace('/_(.+?)_/s', '<em>$1</em>', $t);
			if (trim($t) === '') {
				$t = '<br>';
			} else {
				$t .= '<br>';
			}
		}
		foreach ($urls as $i => $url) {
			$url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
			$url = preg_replace('/edit\s*_?\s*task\.php/i', 'edit_task.php', $url);
			$url = preg_replace('/\btaskid=/i', 'task_id=', $url);
			$link = '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '</a>';
			$t = str_replace("\x01U" . $i . "\x01", $link, $t);
		}
		return $t;
	};

	$text = str_replace(["\r\n", "\r"], "\n", $text);
	$lines = explode("\n", $text);
	$result = '';
	$in_quote_block = false;
	// Inline quote block style: indentation + vertical bar like the app (image 2)
	$quote_block_style = 'margin:8px 0 4px 12px;padding:6px 0 6px 12px;border-left:3px solid #667eea;background-color:#eef2ff;';
	foreach ($lines as $line) {
		$trimmed = ltrim($line);
		$quoteLevel = 0;
		while (strlen($trimmed) > 0 && $trimmed[0] === '>') {
			$quoteLevel++;
			$trimmed = ltrim(substr($trimmed, 1));
		}
		$raw = ($quoteLevel > 0) ? $trimmed : $line;
		$escaped = htmlspecialchars($raw, ENT_QUOTES, 'UTF-8');
		$with_links = $markdown_line($escaped);
		if ($quoteLevel > 0) {
			if (!$in_quote_block) {
				$result .= '<div style="' . $quote_block_style . '">';
				$in_quote_block = true;
			}
			$result .= $with_links;
		} else {
			if ($in_quote_block) {
				$result .= '</div>';
				$in_quote_block = false;
			}
			$result .= $with_links;
		}
	}
	if ($in_quote_block) {
		$result .= '</div>';
	}
	return $result;
}

function add_task_message($task_id, $message, $user_assign_by, $user_assign_to, $task_status_id, $additional_hours, $completion,
			$return_page, $attachment_hash=false, $bug_importance_value=1, $bug_status=1, $estimated_hours=false, $deadline=false, $price=false)
{
	global $db, $temp_path, $path, $session_now;
	
	// Allow empty messages (for status updates, reassignments, or attachments only)
	if ($task_id)
	{
		//insert additional hours
		if ($additional_hours>0 && $additional_hours<1000)
		{
			$sql= "SELECT estimated_hours FROM tasks WHERE task_id=".ToSQL($task_id, "integer");
			$db->query($sql);
			if ($db->next_record()) {
				$new_estimated_hours = (float)$db->f("estimated_hours") + $additional_hours;
				
				$sql = "UPDATE tasks SET estimated_hours=".ToSQL($new_estimated_hours, "number");
				$sql.= " WHERE task_id=".ToSQL($task_id, "integer");
				$db->query($sql);

				$sql = " INSERT INTO estimates (task_id, estimate_time, date_added, user_added) VALUES ";
				$sql.= " (".ToSQL($task_id, "integer").", ".ToSQL($new_estimated_hours, "number").", NOW(), ".ToSQL(GetSessionParam("UserID"), "integer").")";
				$db->query($sql);
			}
		}

		if (GetSessionParam("UserID")) {
			$user_assign_by = GetSessionParam("UserID");
		}		
		
		if (function_exists('is_manager') && is_manager($user_assign_to) && $user_assign_to != $user_assign_by) {
			$db->query("UPDATE tasks SET is_planned=0 WHERE task_id=".ToSQL($task_id, "Number"));
		} else {
			$db->query("UPDATE tasks SET is_planned=1 WHERE task_id=".ToSQL($task_id, "Number"));
		}
		
		$sql = " INSERT INTO messages (message_date, user_id, identity_id, identity_type, status_id, responsible_user_id, message) ";
		$sql.= " VALUES (NOW()";
		$sql.= ", ".ToSQL($user_assign_by, "integer");
		$sql.= ", ".ToSQL($task_id, "integer").", 'task' ";
		$sql.= ", ".ToSQL($task_status_id, "integer", false);
		$sql.= ", ".ToSQL($user_assign_to, "integer", false);
		$sql.= ", ".ToSQL($message, "text").")";		
		$db->query($sql);
		
		$sql = " SELECT LAST_INSERT_ID() ";
		$db->query($sql);
		$db->next_record();
		$message_id = $db->f(0);
		
		// add bug
		if ($task_status_id == 10) {
			$sql = " INSERT INTO bugs SET task_id=".ToSQL($task_id, "integer");
			$sql.= ", message_id = ".ToSQL($message_id, "integer");
			$sql.= ", user_id = ".ToSQL($user_assign_to, "integer");
			$sql.= ", issued_user_id = ".ToSQL($user_assign_by, "integer");
			$sql.= ", date_issued = DATE(NOW()), is_resolved = 0, is_declined = 0 ";
			$sql.= ", importance_level = ".ToSQL($bug_importance_value, "number");
			$db->query($sql);
		} elseif ($task_status_id == 12) {
			if ($bug_status == 1) {
				$sql = "UPDATE bugs SET is_resolved = 1 WHERE is_resolved = 0 AND task_id = ".ToSQL($task_id, "number");
				$db->query($sql);
			} else {
				$sql = "DELETE FROM bugs WHERE is_resolved=0 AND task_id=".ToSQL($task_id, "number");
				$db->query($sql);
			}
		}
		
		if ($attachment_hash && function_exists('attach_files')) {
			$message_replaces = array();
			$attached_files = attach_files("message", $message_id, $attachment_hash, $message_replaces);
			foreach ($message_replaces as $search=>$replace) {
				$message = str_replace($search,$replace,$message);
			}
		}
		
		update_task($task_id, array("is_closed"=>0));

		if ($task_status_id == 4) {
			$completion = 100;
		}
				
		if (function_exists('add_viart_support_message')) {
			add_viart_support_message($task_id, $message_id, $user_assign_by, $user_assign_to, $message);
		}
		
		// sending the updated task data into Trello board
		if (function_exists('sendTaskUpdate2Trello')) {
			sendTaskUpdate2Trello();
		}
	
		$sql = "SELECT started_time,task_status_id,responsible_user_id,((UNIX_TIMESTAMP(NOW())-UNIX_TIMESTAMP(started_time))/3600.0000) as spent_hours FROM tasks WHERE task_id = " . ToSQL($task_id, "integer");
		$db->query($sql);

		if ($db->next_record())
		{
			$prev_status_id = $db->f("task_status_id");
			$spent_hours    = $db->f("spent_hours");
			$user_id        = $db->f("responsible_user_id");
			$started_time   = $db->f("started_time");
			if (!$started_time) {
				$started_time = "";
			}
		}		
		
		if ($prev_status_id == 1 && $task_status_id != 1) {
			stop_task($task_id, $completion, "");
			update_task($task_id, array("responsible_user_id"=>$user_assign_to));
		} elseif ($prev_status_id != 1 && $task_status_id == 1)	{
			update_task($task_id, array("responsible_user_id"=>$user_assign_to));
			start_task($task_id, $completion, "");
		} else {
			update_task($task_id, array("responsible_user_id"=>$user_assign_to));
		}

		if (function_exists('CountTimeProjects')) {
			CountTimeProjects($task_id);
		}

		
		$update_array = array("task_status_id"=>$task_status_id, "completion"=>$completion);
		if ($estimated_hours!=false) {
			$update_array["estimated_hours"] = $estimated_hours;
		}
		if (is_array($deadline) && sizeof($deadline)==3) {
			$update_array["planed_date"] = sprintf("%04d-%02d-%02d", $deadline["YEAR"],$deadline["MONTH"],$deadline["DAYOFMONTH"]);
		}
		if ($price!==false) {
			$update_array["task_cost"] = $price;
		}
		
		update_task($task_id, $update_array);
		
		//-- extracting task information
		$sql  = " SELECT t.created_person_id, t.task_title, p.project_title, ";
		$sql .= " t.responsible_user_id, lts.status_caption, ";
		$sql .= " CONCAT(u.first_name,' ',u.last_name) AS created_user_name, ";
		$sql .= " CONCAT(ur.first_name,' ',ur.last_name) AS responsible_user_name ";
		$sql .= " FROM tasks t ";
		$sql .= " LEFT JOIN projects p ON t.project_id = p.project_id ";
		$sql .= " LEFT JOIN users u ON t.created_person_id = u.user_id ";
		$sql .= " LEFT JOIN users ur ON t.responsible_user_id = ur.user_id ";
		$sql .= " LEFT JOIN lookup_tasks_statuses lts ON t.task_status_id = lts.status_id ";
		$sql .= " WHERE t.task_id = " . ToSQL($task_id, "integer");
		$db->query($sql);
		
		if($db->next_record())
		{
			//-- prepare parameters for the message (format like edit_task: quotes, markdown, linkify; inline styles for email)
			$message_quoted = format_message_for_email($message);
			$tags = array(
				"privilege_id"			=> getSessionParam("privilege_id"),
				"task_id"				=> $task_id,
				"task_title"			=> $db->f("task_title"),
				"project_title"			=> $db->f("project_title"),
				"responsible_user_id"	=> $db->f("responsible_user_id"),
				"responsible_user_name"	=> $db->f("responsible_user_name"),
				"user_name"				=> GetSessionParam("UserName"),
				"task_status"			=> $db->f("status_caption"),
				"message"				=> $message_quoted
			);
			if (function_exists('send_enotification')) {
				send_enotification(MSG_MESSAGE_RECEIVED, $tags);
			}
		}

		if (strlen($return_page)) {
			header("Location: " . $return_page);
		}
	}
}

function delete_task($task_id)
{
	global $db;
	$sql = "DELETE FROM tasks WHERE task_id=".ToSQL($task_id, "integer");
	$db->query($sql);
	$sql = "DELETE FROM messages WHERE identity_type='task' AND identity_id=" . ToSQL($task_id, "integer");
	$db->query($sql);
	$sql = "DELETE FROM task_recordings WHERE task_id=".ToSQL($task_id, "integer");
	$db->query($sql);
	return true;
}

function add_task($responsible_user_id, $priority_id, $task_status_id, $project_id, $client_id,
				$task_title, $task_desc, $planed_date, $created_user_id, $estimated_hours=false,
				$task_type_id, $attachment_hash, $is_wish=false)
{
	global $db, $temp_path, $path, $session_now;

	$message = $task_desc;
	//select is responsible user is a manager
	$is_manager = is_manager($responsible_user_id);
    //set is_planed 0 for such cases
	if ($created_user_id != $responsible_user_id && $is_manager) {
		$is_planned = "0";
	} else {
		$is_planned = "1";
	}
      	
	if($task_status_id == 1) {
		$started_time = "NOW()";
	} else {
		$started_time = "NULL";
	}
	
	if ($task_type_id==4) {
		$task_status_id = 15;
	}
	
	//$task_desc = addslashes($task_desc);

	$sql = " INSERT INTO tasks (project_id, client_id, task_title, task_desc, planed_date, creation_date, modified_date ";
	$sql.= " ,task_status_id, responsible_user_id, created_person_id, priority_id, estimated_hours, started_time ";
	$sql.= " ,is_planned, task_type_id, is_wish ";
	$sql.= " ) VALUES (";
	$sql.= ToSQL($project_id, "integer") . "," . ToSQL($client_id, "integer");
	$sql.= ", ".ToSQL($task_title, "Text")  . "," . ToSQL($task_desc, "Text");
	$sql.= ", ".ToSQL($planed_date, "Date")  . "," . "NOW(), NOW() ";
	$sql.= ", ".ToSQL($task_status_id, "integer", false)  . "," . ToSQL($responsible_user_id, "integer", false);
	$sql.= ", ".ToSQL($created_user_id, "integer" ,false)  . "," . ToSQL($priority_id, "integer");
	$sql.= ", ".ToSQL($estimated_hours, "Number");
	if($task_status_id == 1) {
		$sql.= ", NOW()";
	} else {
		$sql.= ", NULL ";
	}	
	$sql.= ", ".ToSQL($is_planned, "integer", false) . ", ".ToSQL($task_type_id, "integer", false);
	$sql.= ", ".ToSQL($is_wish, "integer", false).")";
	$db->query($sql);
	
	

	//-- determine last inserted id
	$sql = "SELECT LAST_INSERT_ID()";
	$db->query($sql);
	if ($db->next_record()) {
		$task_id = $db->f(0);
	}
	update_priorities($task_id);

	if ($estimated_hours) {
		$sql = "INSERT INTO estimates (estimate_id, task_id, estimate_time, date_added, user_added) ".
			" VALUES(0, $task_id, ".ToSQL($estimated_hours, "number")  . ", NOW(), ".ToSQL($created_user_id, "integer").")";
		$db->query($sql);
	}
	
	if ($attachment_hash) {
		$message_replaces = array();
		attach_files("task", $task_id, $attachment_hash, $message_replaces);
		foreach ($message_replaces as $search=>$replace) {
			$message = str_replace($search,$replace,$message);
		}
	}
	
	//-- determine last inserted id
	$sql = "SELECT project_title FROM projects WHERE project_id=".ToSQL($project_id, "integer");
	$db->query($sql);
	if ($db->next_record()) {
		$project_title = $db->f(0);
	}
	$sql = "SELECT CONCAT(first_name,' ',last_name) AS user_name FROM users WHERE user_id=".ToSQL($created_user_id, "integer");
	$db->query($sql);
	if ($db->next_record()) {
		$user_name = $db->f(0);
	} else {
		$user_name = "";
	}
	$sql  = " SELECT CONCAT(first_name,' ',last_name) AS user_name FROM users ";
	$sql .= " WHERE user_id=".ToSQL($responsible_user_id, "integer");
	$db->query($sql);
	if ($db->next_record()) {
		$responsible_user_name = $db->f(0);
	} else {
		$responsible_user_name = "";
	}
	$tags = array (
		"project_title"         => $project_title,
		"task_title"            => $task_title,
		"task_id"               => $task_id,
		"responsible_user_id"   => $responsible_user_id,
		"responsible_user_name" => $responsible_user_name,
		"user_name"		        => $user_name
	);
	send_enotification(MSG_TASK_CREATED, $tags);
	return $task_id;
}

function attach_files($identity_type, $identity_id, $hash, &$message_replaces) {
	global $db, $temp_path, $session_now, $path;
	
	if (!$session_now) $session_now = session_id();
	
	$message_replaces = array();
	$handle = opendir($temp_path);
	$i=0;
	if ($handle && $file = readdir($handle))
	{
		do {			
			$cur_file = substr($file, strlen($session_now)+8);
			if ($file != "." && $file != ".." &&
				 (strval($hash)==substr($file,strlen($session_now),8)
				 || (substr($file,strlen($session_now),8)=="00000000"
				  && $session_now==substr($file,0,strlen($session_now))))  ) 
			{
				copy($temp_path.$file, $path.$identity_id."_".$cur_file );
				unlink($temp_path.$file);
				$file_ext = substr(strrchr($cur_file, "."),1);
				switch ($file_ext) {
					case "gif": case "jpg": case "jpeg": case "bmp": case "png": $cur_type = "image"; break;
					case "doc": case "txt": case "pdf": case "htm": case "html": $cur_type = "document"; break;
					case "zip": case "rar": case "tar": case "tgz": $cur_type = "archive"; break;
					default: $cur_type = "other";
				}
				
				$sql = "INSERT INTO attachments (attachment_date, identity_id, identity_type, file_name, attachment_type) ";
				$sql.= " VALUES (NOW(), ".ToSQL($identity_id, "integer").", ".ToSQL($identity_type, "text").", ".ToSQL($cur_file, "text");
				$sql.= ",".ToSQL($cur_type, "text").")";
				$db->query($sql);
				
				if ($identity_type == "task") {
   					$full_path = $AbsoluteUri ='http'.'://'.$_SERVER["SERVER_NAME"].substr($_SERVER["REQUEST_URI"],0,strrpos($_SERVER["REQUEST_URI"],'/')+1).$path;
					$mes_file = $cur_file;
					$cur_file = $full_path.strval($identity_id)."_".$mes_file;
					if ($cur_type == "image") {
						$message_replaces["[".$mes_file."]"] = "<img src='$cur_file' border=0>";
					} else {
						$message_replaces["[".$mes_file."]"] = "<a href='$cur_file'>[$mes_file]</a>";
					}
				}
				$i++;
			}
			
		} while ($file = readdir($handle));
   		closedir($handle);
  	}
  	return $i;
}

function update_task($task_id, $update_vars)
{
	global $db, $temp_path, $path, $session_now;
	$task_updated = false;
	$update_sql   = array();
	
	if (isset($update_vars["task_domain_url"])) {
		$task_domain_url = strtolower(trim(rtrim($update_vars["task_domain_url"])));
		$tmp = explode("/", $task_domain_url);
		if (count($tmp) > 1) {
			if ($tmp[0] == "http:" || $tmp[0] == "ftp:" || $tmp[0] == "https:") {
				$task_domain_url = $tmp[1];
			} else {
				$task_domain_url = $tmp[0];
			}
		}
		if (strpos($task_domain_url, "www.") === 0) {
			$task_domain_url = substr($task_domain_url, 4);
		}
		
		$task_domain_id = 0;
		if ($task_domain_url) {
			$sql  = " SELECT domain_id FROM tasks_domains";
			$sql .= " WHERE domain_url = " . ToSQL($task_domain_url, "text");
			$sql .= " OR domain_url = " . ToSQL("www." . $task_domain_url, "text");
			$db->query($sql);
			if ($db->next_record()) {
				$task_domain_id = $db->f("domain_id");
			} else {
				$sql = " SELECT MAX(domain_id) FROM tasks_domains";
				$task_domain_id = get_db_value($sql) + 1;
				
				$sql = " INSERT INTO tasks_domains (domain_id, domain_url)";
				$sql .= " VALUES (" . $task_domain_id . "," . ToSQL($task_domain_url, "text") . ")";
				$db->query($sql);			
			}			
		}
		$update_vars["task_domain_id"]  = $task_domain_id;
		$update_vars["task_domain_url"] = $task_domain_url;
		

	}
	if(is_array($update_vars) && is_numeric($task_id) && $task_id>0) {		
		foreach ($update_vars as $name=>$value) {
			$type = "";
			if ($name == "completion" && is_numeric($value) && $value >= 0 && $value <= 100) {
				$type = "integer";				
			} elseif ($name == "is_closed") {
				if ($value == 0) {
					$type = "integer";
				} else {
					close_task($task_id, "");
				}
			} elseif ($name == "responsible_user_id") {
				$sql = "SELECT responsible_user_id FROM tasks WHERE task_id=".ToSQL($task_id, "integer");
				$db->query($sql);
				if ($db->next_record()) {
					$previous_user_id = $db->f("responsible_user_id");
					if ($value != $previous_user_id && $value>0) {
						if (is_manager($value) && $value!=GetSessionParam("UserID")) {
							$update_sql["is_planned"] = "is_planned = 0 ";
						} else {
							$update_sql["is_planned"] = "is_planned = 1 ";
						}
						/*
						$assign_to_myself = 0;
						if ($new_user_id == GetSessionParam("UserID")) {
							$assign_to_myself = 1;
						}*/
						insert_responses($task_id, 0);
						$update_sql["date_reassigned"] = "date_reassigned = NOW() ";
					}					
				}
				$type = "integer";
			} elseif ($name == "project_id" || $name == "priority_id" || $name == "task_type_id"
					|| $name == "task_status_id" || $name == "is_wish" || $name == "release_id" || $name == "client_id" || $name == "parent_task_id") {
				$type = "integer";
			} elseif ($name == "estimated_hours" || $name=="task_cost" || $name == "hourly_charge") {
				$type = "number";
			} elseif (($name == "task_title" && strlen($value)) || $name == "task_desc" || $name == "estimated_title" || $name == "task_domain_url") {
				$type = "text";
			} elseif ($name == "planed_date") {
				$type = "date";
			}
			
			if ($type) {
				if ($type=="integer") {
					$use_null = false;
				} else {
					$use_null = true;
				}
				$update_sql[$name] = $name." = ".ToSQL($value, $type, $use_null);
			}
		}		
		
		
		// Always update modified_date when task is modified
		$update_sql["modified_date"] = "modified_date = NOW()";

		if (sizeof($update_sql)) {
			$sql = "UPDATE tasks SET ".implode(", ", $update_sql)." WHERE task_id=".ToSQL($task_id, "number");
			$task_updated = $db->query($sql);
		}
	}
	return $task_updated;	
}


function sendTaskUpdate2Trello() {
	$endPoint    = "https://dev-tools.sayu.co.uk/api";
	$route       = "monitor-hook/update-from-monitor";

	$taskData = array();
	$taskData["taskId"]       =  "67390";
	$taskData["taskName"]     =  "Test from Artem card -sync 2";
	$taskData["taskDesc"]     =  "NOT this is a test note - it's more than that";
	$taskData["taskStatus"]   =  "7";
	$taskData["userAssigned"] =  "27";
	$taskData["userCreated"]  =  "3";

	$data = array();
	$data["action"]["type"]   = "taskUpdated";
	$data["action"]["date"]   = date("с");
	$data["action"]["data"]   = $taskData;

	$res = cUrlPostRequest($endPoint, $route, $data);
	return $res;
}
/*
{
    "action": {
        "data": {
          "taskId": "67390",
          "taskName": "Test from Artem card -sync 2",
          "taskDesc": "NOT this is a test note - it's more than that",
          "taskStatus": "7",
          "userAssigned": "27",
          "userCreated": "3"
        },
        "type": "taskUpdated",
        "date": "2020-05-12T13:23:02.846Z"
      }
  }
*/

function cUrlPostRequest($endpoint, $route, $data) {
    $method = "POST";
    $ch = curl_init();    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST , $method);

    $jsonData = json_encode($data);
    curl_setopt($ch, CURLOPT_URL, "$endpoint/{$route}");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json; charset=utf-8',
        'Content-Length: ' . strlen($jsonData),
    ]);

    $response = curl_exec($ch);

    if (curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) {
        return $response;
    }

    return json_decode($response, true);
}

?>