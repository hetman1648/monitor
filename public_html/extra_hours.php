<?php

	include_once("./includes/common.php");

	CheckSecurity(1);

	$task_id	= GetParam("task_id");
	$hours		= GetParam("hours");
	$mon		= GetParam("month")?GetParam("month"):date("m");
	$year		= GetParam("year")?GetParam("year"):date("Y");


    if ($task_id && $hours) {    	$time	= preg_replace("/[\.,:-]{1}/",".",$hours);
    	$hours	= number_format($time, 2, ".", "");
    	$hours	= floor($hours)+(($hours - floor($hours))*100)/60;
    	$hours	= number_format($hours, 2, ".", "");
    	$user_id = GetSessionParam("UserID");
    	$days_month = array_fill(1, date("t", mktime(0, 0, 0, $mon, 1, $year)),0);
    	$sql = "SELECT	holiday_date
    			FROM	national_holidays
    			WHERE	DATE_FORMAT(holiday_date, '%Y')=".ToSQL($year,"integer")."
    					AND DATE_FORMAT(holiday_date, '%m')=".ToSQL($mon,"integer");
		$db->query($sql,__FILE__,__LINE__);
		if  ($db->next_record()){
			do {
				$holiday_date = $db->f("holiday_date");
				$holiday_date_arr = explode("-",$holiday_date);
				$day_of_hol = (integer)$holiday_date_arr[2];
				$days_month[$day_of_hol] = 1;
			}
			while ($db->next_record());
		}
		while (true) {			foreach($days_month as $day => $stat) {
				if (date("w", mktime(0, 0, 0, $mon, $day, $year)) =="0" || date("w", mktime(0, 0, 0, $mon, $day, $year)) == "6") { $days_month[$day] = 1;}
				if (!$days_month[$day]) { break;}
			}
	    	$sql = "SELECT MAX(DATE_FORMAT(report_date,'%H.%i.%s')) as time_report FROM time_report WHERE DATE_FORMAT(report_date,'%Y-%m-%d')=".ToSQL(date("Y-m-d", mktime(0, 0, 0, $mon, $day, $year)),"date")." AND user_id=".ToSQL($user_id,"integer");
	    	$db->query($sql,__FILE__,__LINE__);
	    	$db->next_record();
	    	$time_report = $db->Record["time_report"];
	    	if ($time_report) { break;}
	    		else { $days_month[$day]=1;}
	    	if($day+1==sizeof($days_month)) {	    		$day = 1;
	    		$time_report = "10.25.34";
	    		break;	    	}
		}
    	list($report_hours, $report_minuts, $report_seconds) = split("[.]",$time_report);
    	$time_report_start	= mktime($report_hours, $report_minuts, $report_seconds+rand(5,60), $mon, $day, $year);
    	$time_report_stop	= $time_report_start + (int)($hours*3600);
    	//ToSQL(date("Y-m-d H:i:s",$time_report_start),"date").",
    	//ToSQL(date("Y-m-d H:i:s",$time_report_stop),"date").",
    	$sql = "INSERT INTO time_report
    			SET user_id	=".ToSQL($user_id,"integer").",
    				task_id	=".ToSQL($task_id,"integer").",
    				started_date	= '".date("Y-m-d H:i:s",$time_report_start)."',
    				report_date		= '".date("Y-m-d H:i:s",$time_report_stop)."',
    				spent_hours		= ".$hours.",
    				auto_stop =0";
    	$db->query($sql,__FILE__,__LINE__);
    	task_set_time_report_hours($task_id);    	
    	header("Location: edit_task.php?task_id=".$task_id);
    	exit();
    }

    //$T = new iTemplate("./templates",array("main"=>"extra_hours.html"));
    $T = new iTemplate($sAppPath);
    $T->set_file("main","extra_hours.html");

	$T->set_var("emonth",GetMonthOptions($mon));
	$sql = "SELECT MIN(DATE_FORMAT(started_date,'%Y')) AS years FROM time_report";
    $db->query($sql,__FILE__,__LINE__);
    $db->next_record();
    $T->set_var("eyear",GetYearOptions($db->Record["years"],date("Y"),$year));

    $T->parse("main",true);
    echo '<b id="b_id">';
	echo '<![CDATA[';
	echo $T->get_var("main");
	echo ']]>';
	echo '</b>';
?>