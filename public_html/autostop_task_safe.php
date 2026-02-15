#!/usr/local/bin/php4 -q
<?php
  	include_once("./db_mysql.inc");
  	include_once("./includes/db_connect.php");
  	include_once("./includes/common_functions.php");

  	$intranet = 0;
	$CRLF = $intranet ? "\r\n" : "\n";

	$db = new DB_Sql();
	$db->Database = DATABASE_NAME;
	$db->User     = DATABASE_USER;
	$db->Password = DATABASE_PASSWORD;
	$db->Host     = DATABASE_HOST;

	$db2 = new DB_Sql();
	$db2->Database = DATABASE_NAME;
	$db2->User     = DATABASE_USER;
	$db2->Password = DATABASE_PASSWORD;
	$db2->Host     = DATABASE_HOST;


	$script_path = "monitor.sayu.co.uk/";

	$cdate = getdate();
	$cmonth = $cdate['mon'];
	$cday = $cdate['mday'];
	$cyear = $cdate['year'];

    $sql = "SELECT t.task_id, t.task_title, t.started_time, t.responsible_user_id, ";
    $sql .= "CONCAT(u.first_name,' ',u.last_name) AS user_name, u.email, ";
    $sql .= "UNIX_TIMESTAMP(t.started_time) as started_stamp ";
    $sql .= "FROM tasks t, users u ";
    $sql .= "WHERE t.responsible_user_id=u.user_id AND t.task_status_id=1"; // AND t.responsible_user_id=15"; //<- Stop only Eugene
    //echo $sql."<br>";
	$today = date("Y-m-d");
    $message2 = "<html><head><title>Monitor: List of users who forgot to stop their tasks - $today</title></head>";
    $message2 .= "<body style=\"background-color: #FFFFFF; color: #000000; font-family: Tahoma,Verdana,Helvetica; font-size: 10pt\">";
    $message2 .= "<center><b>List of users who forgot to stop their tasks - $today</b></center><br>";
    $message2 .= "<table align='center' style=\"border-style: outset; border-width: 2\">";
    $message2 .= "<tr>";
    $message2 .= "<th align='center' style=\"background-color: #D0D0D0; border-style: inset; border-width: 0; font-size: 10pt\">&nbsp;Person name&nbsp;</th>";
    $message2 .= "<th align='center' style=\"background-color: #D0D0D0; border-style: inset; border-width: 0; font-size: 10pt\">&nbsp;Task title&nbsp;</th>";
    $message2 .= "<th align='center' colspan=2 style=\"background-color: #D0D0D0; border-style: inset; border-width: 0; font-size: 10pt\">&nbsp;Operations&nbsp;</th></tr>";
	$headers = "From: monitor@viart.com.ua" . $CRLF;
	$headers .= "Reply-To: monitor@viart.com.ua" . $CRLF;
	$headers .= "Content-Type: text/html";

    $db->query($sql);
    if ($db->next_record()) {
	    do {
	    	$task_id = $db->f("task_id");
	    	$task_title = $db->f("task_title");
	    	$user_id = $db->f("responsible_user_id");
	    	$user_name = $db->f("user_name");
	    	$user_email = $db->f("email");
	    	$started_time = $db->f("started_time");
	    	$started_stamp = intval($db->f("started_stamp"));
	    	$stamp_18h = mktime(18, 0, 0, $cmonth, $cday, $cyear);
	    	if ($started_stamp < $stamp_18h) {
	    		$spent_hours = ($stamp_18h - $started_stamp)/3600;
	    		$report_date = date("Y-m-d") . " 18:00:00";
    		}
    		else {
	    		$spent_hours = 1/60;
	    		$report_date = date("Y-m-d H:i:s", $started_stamp + 60);
    		}

/*
    		echo "started_stamp = " . $started_stamp . $CRLF . "<br>";
			echo "stamp_18h = " . $stamp_18h . $CRLF . "<br>";
			echo "started_time = " . $started_time . $CRLF . "<br>";
			echo "report_date = " . $report_date . $CRLF . "<br>";
			echo "spent_hours = " . $spent_hours . $CRLF . "<br>";
			print_r($cdate);
*/
	    	//-- Correct actually spent time
	    	$sql = "UPDATE tasks SET actual_hours = (actual_hours + $spent_hours), task_status_id=8 WHERE task_id=$task_id";
	    	echo "\n\n".$sql;
	    	//$db2->query($sql);

			//-- Write report about time spent for the task
	    	$sql = "INSERT INTO time_report (user_id, started_date, task_id, report_date, spent_hours, auto_stop) ";
	    	$sql .= "VALUES ($user_id, '$started_time', $task_id, '$report_date', $spent_hours, 1)";
	    	echo "\n".$sql;
	    	//$db2->query($sql);

	    	//$sql = "SELECT LAST_INSERT_ID() AS id";
	    	$sql = "SELECT MAX(report_id) AS id FROM time_report";
	    	$db2->query($sql);
	    	if ($db2->next_record()) $report_id = $db2->f("id");
	    	//printf("Last report_id = %s", $report_id);

	    	$message2 .= "<tr>";
	    	$message2 .= "<td style=\"background-color: #EAEAEA; border-style: inset; border-width: 0; font-size: 10pt\">&nbsp; $user_name&nbsp;</td>";
	    	$message2 .= "<td style=\"background-color: #EAEAEA; border-style: inset; border-width: 0; font-size: 10pt\">&nbsp;$task_title&nbsp;</td>";
	    	$message2 .= "<td style=\"background-color: #EAEAEA; border-style: inset; border-width: 0; font-size: 10pt\">&nbsp;<a href='http://".$script_path."edit_task.php?task_id=$task_id'>Edit/View</a>&nbsp;</td>";
	    	$message2 .= "<td style=\"background-color: #EAEAEA; border-style: inset; border-width: 0; font-size: 10pt\">&nbsp;<a href='http://".$script_path."set_tr_time.php?report_id=$report_id'>Set time</a>&nbsp;</td></tr>";

	    	//-- Send e-mail to the user who forgot to close task
	    	$to = $user_email;
	    	//$to = "eugene@viart.com.ua";
	    	$subj = "Monitor: Your task '$task_title' wasn't closed in time - $today";
	    	$message = "<html><head><title>Monitor: Your task '$task_title' wasn't closed in time - $today</title></head>";
	    	$message .= "<body>You forgot to stop your task. Please go to <a href='http://".$script_path."set_tr_time.php?report_id=$report_id'>this link</a> to set the right time.</body></html>";
	    	//@mail($to, $subj, $message, $headers);
	    } while ($db->next_record());

		//-- Send email to Artem and Vitaliy with list of people who forgot to close their tasks
		$to = "artem@viart.com.ua, vitaliy@viart.com.ua";
		//$to = "eugene@viart.com.ua";
		$subj = "Monitor: List of users who forgot to stop their tasks - $today";
		$message2 .= "</table></body></html>";
		
		echo "\n\n".$subj;
		//@mail($to, $subj, $message2, $headers);

		CountTimeProjects($task_id);
	} //if
?>