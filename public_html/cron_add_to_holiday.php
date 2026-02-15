<?php

// Change current folder for console script
	chdir(dirname(__FILE__));


include ("./includes/common.php");
include ("./includes/date_functions.php");

//  	include("./db_mysql.inc");
//  	include("./includes/db_connect.php");
	
/*if (getsessionparam("privilege_id") == 9) {
	header("Location: index.php");
	exit;
}*/

// SECOND DATABASE OBJECT
$db2 = new DB_Sql;
$db2->Database = DATABASE_NAME;
$db2->User     = DATABASE_USER;
$db2->Password = DATABASE_PASSWORD;
$db2->Host     = DATABASE_HOST;

global $is_paid;

//CheckSecurity(1);

$date_added = date('Y-m-d');
$today = getdate();
$today_mon = $today["mon"];
$today_year = $today["year"];
//DATE_FORMAT('0601', '%Y%m')

	//$sql = "SELECT user_id, last_name, PERIOD_DIFF(DATE_FORMAT(NOW(), '%Y%m'),DATE_FORMAT(start_date, '%Y%m')) AS differ, start_date FROM users WHERE is_viart = '1' AND is_deleted IS NULL";
	$sql = "SELECT user_id, last_name, PERIOD_DIFF(DATE_FORMAT(NOW(), '%Y%m'),DATE_FORMAT(start_date, '%Y%m')) AS differ, start_date FROM users WHERE is_viart = '1' AND is_deleted IS NULL AND start_date <= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
	$db->query($sql);
	if ($db->next_record())
	{
		do 
		{	
		   if ($db->f("differ") > 0)  
		   {
		   	$user_id = $db->f("user_id");
		  	$last_name = $db->f("last_name");
		 	$start_date = $db->f("start_date");
		  	$differ = $db->f("differ");
		 // echo "Name - ".$last_name." Diff - ".$differ."<br>";

		 	$days_additional = floor($differ/12);
		 	if ($days_additional > 5) $days_additional = 5;
		  	$days_number = 1.25 + $days_additional*0.084;
		   	
			$sql2 = "INSERT INTO holidays (user_id, days_number, date_added, notes, manager_added_id) VALUES ('$user_id', ".ToSQL($days_number, 'float').", '$date_added', 'Added by cron', '0')";
			$db2->query($sql2);  
			 echo "Name - ".$last_name." Diff - ".$differ." Days number - ".$days_number." Days add - ".$days_additional*0.084."<br>";
		   }
			
		}
	
	while ($db->next_record());
	}




function dateDiff($dformat, $endDate, $beginDate)
{
$date_parts1=explode($dformat, $beginDate);
$date_parts2=explode($dformat, $endDate);
$start_date=gregoriantojd($date_parts1[1], $date_parts1[2], $date_parts1[0]);
$end_date=gregoriantojd($date_parts2[1], $date_parts2[2], $date_parts2[0]);
return $end_date - $start_date;
}
?>