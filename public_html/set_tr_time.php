<?php

	include ("./includes/common.php");
	include ("./includes/date_functions.php");

	CheckSecurity(1);

	$user = GetSessionParam("UserName");
	$sTemplateFileName = "set_tr_time.html";
	$t = new iTemplate($sAppPath,array("main"=>$sTemplateFileName));
	$report_id = GetParam("report_id");
	$t->set_var("report_id", $report_id);
	
	$report_time = GetParam("report_time");
	$rt = "";

	if ($report_time) {
		//$rt_ar = parse_date(array("hh", ":", "mm", ":", "ss"), $report_time, "");
		$rt_ar = parse_date(array("h", ":", "m"), $report_time, "");
		//print_r($rt_ar);
		//-- We need only time, so the date may be any
		//$rt_ts = mktime($rt_ar[9], $rt_ar[4], $rt_ar[5], 1, 1, 2005);
		$rt_ts = mktime($rt_ar[9], $rt_ar[4], 0, 1, 1, 2005);
		$rt = @date ("H:i:s", $rt_ts);
		//echo "New report time: ".$rt;
	}

	if ($report_id && is_number($report_id))
	{		
		$sql = "SELECT CONCAT(u.first_name,' ',u.last_name) AS user_name, t.task_title, tr.task_id, tr.started_date, tr.report_date,  tr.spent_hours, ";
		$sql .= "DATE_FORMAT(tr.started_date, '%Y-%m-%d') as sd ";
		if ($rt) {
			$sql .= ", CONCAT(DATE_FORMAT(tr.started_date, '%Y-%m-%d'), ' $rt') as rd_new ";
			$sql .= ", ((UNIX_TIMESTAMP(CONCAT(DATE_FORMAT(tr.started_date, '%Y-%m-%d'), ' $rt'))-UNIX_TIMESTAMP(tr.started_date))/3600) as spent_hours_correct ";
		}
		$sql .= "FROM tasks t, time_report tr, users u ";
		$sql .= "WHERE tr.task_id=t.task_id AND tr.user_id=u.user_id AND tr.report_id=$report_id AND auto_stop=1 ";
				
		$db->query($sql);
		if ($db->next_record())	{
			$user_name   = $db->f("user_name");
			$task_title  = $db->f("task_title");
			$task_id     = $db->f("task_id");
			$spent_hours = $db->f("spent_hours");
			$report_date = $db->f("report_date");
			
			$t->set_var("task_id",      $task_id);
			$t->set_var("task_title",   $task_title);
			$t->set_var("started_date", $db->f("sd"));			
			
			if ($rt) {
				$rd_new = $db->f("rd_new");
				$spent_hours_correct = $db->f("spent_hours_correct");
				if( $spent_hours_correct < 0) {
					$spent_hours_correct = 0;
					$report_date = $db->f("started_date");
					$rd_new      = $db->f("started_date");
				}
				echo "<br>Started time: "    . $db->f("started_date");
				echo "<br>Report time: "     . $db->f("report_date");
				echo "<br>Report time new: " . $rd_new;
				echo "<br>Correct time: "    . Hours2HoursMins($spent_hours_correct);
				if ($rt && $spent_hours_correct > 0) {
		    		$sql = " UPDATE time_report SET ";
			   		$sql.= " report_date = " . ToSQL($rd_new, "text") . ", ";
					$sql.= " spent_hours = " . ToSQL($spent_hours_correct, "number");
					$sql.= " WHERE report_id = " . ToSQL($report_id, "integer");
			   		$db->query($sql);
		    		echo "<b><font color=#0000FF>Time was corrected!</font></b>";
					$to = "artem@viart.com.ua, vitaliy@viart.com.ua";
					$subj = "Monitor: Autostopped task's time corrected";
					$message = "<html><body>Report time of task <a href='http://monitor.sayu.co.uk/edit_task.php?task_id=$task_id'>$task_title</a> was corrected by $user.</body></html>";
					$headers = "From: monitor@viart.com.ua" . $CRLF;
					$headers .= "Reply-To: monitor@viart.com.ua" . $CRLF;
					$headers .= "Content-Type: text/html";
		    		mail($to,$subj,$message,$headers);
				}
				else {
					echo "<b><font color=#FF0000>Time wasn't corrected!</font></b>";
				} //if ($submit && $rt)
			}
		} else {
			$t->set_var("report_body", "");
		}		
	} else {
		$t->set_var("report_body", "");
	}

	$t->pparse("main");
?>