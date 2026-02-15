#!/usr/local/bin/php4 -q
<?php
	include_once("./db_mysql.inc");
	include_once("./includes/db_connect.php");
	include_once("./includes/common_functions.php");
	
	$db = new DB_Sql;
	$db->Database = DATABASE_NAME;
	$db->User     = DATABASE_USER;
	$db->Password = DATABASE_PASSWORD;
	$db->Host     = DATABASE_HOST;
	
	
	$sql  = " SELECT user_id, CONCAT(first_name, ' ', last_name) as user_name ";
	$sql .= " FROM users WHERE is_deleted IS NULL AND (is_viart=1 OR user_id=71) AND manager_id<1;";
	$db->query($sql);
	$manager_ids = array();
	while($db->next_record()) {
		$user_id = $db->Record["user_id"];
		$user_name = $db->Record["user_name"];
		$manager_ids[] = $user_id;
	}
	
	
	$sql = " SELECT user_id, CONCAT(first_name, ' ', last_name) as user_name ";
	$sql .= " FROM users WHERE is_deleted IS NULL AND is_viart=0 ";
	$db->query($sql);
	$english_ids = array();
	while($db->next_record()) {
		$user_id = $db->Record["user_id"];
		//$user_name = $db->Record["user_name"];
		$english_ids[] = $user_id;		
	}
	
	$events = array();
	
	$english_only_events = array();
	//birthday
	$sql  = " SELECT CONCAT(first_name, ' ', last_name, ' has a birthday at ',DATE_FORMAT(birth_date,'%d %M')) as event ";
	$sql .= " FROM users ";
	$sql .= " WHERE is_deleted IS NULL ";
	$sql .= " 	AND (((DAYOFYEAR(birth_date)-DAYOFYEAR(NOW()))<=3 AND ";
	$sql .= "			(DAYOFYEAR(birth_date)-DAYOFYEAR(NOW()))>=0) OR ";
	$sql .= "			(DAYOFYEAR(birth_date)-DAYOFYEAR(NOW())=5 AND DAYOFWEEK(NOW())=4) OR ";
	$sql .= "			(DAYOFYEAR(birth_date)-DAYOFYEAR(NOW())IN(4,5) AND DAYOFWEEK(NOW())=5) OR ";
	$sql .= "			(DAYOFYEAR(birth_date)-DAYOFYEAR(NOW())IN(3,4,5) AND DAYOFWEEK(NOW())=6)); ";
	$db->query($sql);
	while($db->next_record()) {
		$event = $db->Record["event"];
		$events[] = $event;
	}
	
	//starts working
/*	$sql  = "SELECT	IF(ROUND((TO_DAYS(NOW())-TO_DAYS(start_date))/365)=0 ";
	$sql .= ", CONCAT('New person ',first_name, ' ', last_name,' starts working at ',DATE_FORMAT(start_date,'%d %M')) ";
	$sql .= ", CONCAT(first_name, ' ', last_name, ' has ', ROUND((TO_DAYS(NOW())-TO_DAYS(start_date))/365), ' years of working at ',DATE_FORMAT(start_date,'%d %M')))as event ";
	$sql .= "FROM users ";
	$sql .= "WHERE is_deleted IS NULL ";
	$sql .= "	AND (((DAYOFYEAR(start_date)-DAYOFYEAR(NOW()))<=3 AND ";
	$sql .= "			(DAYOFYEAR(start_date)-DAYOFYEAR(NOW()))>=0) OR ";
	$sql .= "			(DAYOFYEAR(start_date)-DAYOFYEAR(NOW())=5 AND DAYOFWEEK(NOW())=4) OR ";
	$sql .= "			(DAYOFYEAR(start_date)-DAYOFYEAR(NOW())IN(4,5) AND DAYOFWEEK(NOW())=5) OR ";
	$sql .= "			(DAYOFYEAR(start_date)-DAYOFYEAR(NOW())IN(3,4,5) AND DAYOFWEEK(NOW())=6));";
	$db->query($sql);
	while($db->next_record()) {
		$event = $db->Record["event"];
		$events[] = $event;
	}
*/	
	//has 2 months of working
/*	$sql  = "SELECT CONCAT(first_name, ' ', last_name, ' has 2 months of working at ',DATE_FORMAT(DATE_ADD(start_date,INTERVAL 2 MONTH),'%d %M')) as event ";
	$sql .= "FROM users ";
	$sql .= "WHERE is_deleted IS NULL ";
	$sql .= "	AND (((TO_DAYS(DATE_ADD(start_date,INTERVAL 2 MONTH))-TO_DAYS(NOW()))<=3 AND ";
	$sql .= "			(TO_DAYS(DATE_ADD(start_date,INTERVAL 2 MONTH))-TO_DAYS(NOW()))>=0) OR ";
	$sql .= "			(TO_DAYS(DATE_ADD(start_date,INTERVAL 2 MONTH))-TO_DAYS(NOW())=5 AND DAYOFWEEK(NOW())=4) OR ";
	$sql .= "			((TO_DAYS(DATE_ADD(start_date,INTERVAL 2 MONTH))-TO_DAYS(NOW()))IN(4,5) AND DAYOFWEEK(NOW())=5) OR ";
	$sql .= "			((TO_DAYS(DATE_ADD(start_date,INTERVAL 2 MONTH))-TO_DAYS(NOW()))IN(3,4,5) AND DAYOFWEEK(NOW())=6)); ";
	$db->query($sql);
	while($db->next_record()) {
		$event = $db->Record["event"];
		$events[] = $event;
	}
*/	
	//has a holiday
	$sql  = " SELECT UNIX_TIMESTAMP(DATE(d.start_date)) as max_start, UNIX_TIMESTAMP(DATE(d.end_date)) as max_end ";
	$sql .= ", CONCAT(u.first_name, ' ', u.last_name) as user_name, r.reason_name as reason_name ";
	$sql .= " FROM ((days_off d ";
	$sql .= "	INNER JOIN users u ON (u.user_id = d.user_id)) ";
	$sql .= "	INNER JOIN reasons r ON (r.reason_id = d.reason_id)) ";
	$sql .= " WHERE d.start_date BETWEEN NOW() AND DATE_ADD(NOW(),INTERVAL 7 DAY);";
	$db->query($sql);
	while($db->next_record()) {
		$max_start = $db->Record["max_start"];
		$max_end = $db->Record["max_end"];
		$user_name = $db->Record["user_name"];
		$reason_name = $db->Record["reason_name"];
		$event = $user_name . " has a holiday (" . $reason_name . ") at " . date("d F",$max_start);
		if ($max_start != $max_end) {
			$event .= " till " . date("d F",$max_end);
		}
		$events[] = $event;
	}
	
	//national holidays
	
	$sql  = " SELECT CONCAT('Ukrainian National Holiday: ', holiday_title, ', ',DATE_FORMAT(holiday_date,'%d %M')) as event ";
	$sql.= " FROM national_holidays WHERE DATE_SUB(holiday_date, INTERVAL 8 DAY)<=NOW() AND holiday_date>=NOW() ";
	$db->query($sql);
	while($db->next_record()) {
		$event = $db->Record["event"];
		$english_only_events[] = $event;
	}
	
	$sql  = " SELECT CONCAT('English Holiday: ', holiday_title, ', ',DATE_FORMAT(holiday_date,'%d %M')) as event ";
	$sql.= " FROM english_holidays WHERE DATE_SUB(holiday_date, INTERVAL 8 DAY)<=NOW() AND holiday_date>=NOW() ";
	$db->query($sql);
	while($db->next_record()) {
		$event = $db->Record["event"];
		$events[] = $event;
	}	
	//end
	
	$sql_report = "";
	$sql_report_insert = "";
	$total_insert = 0;
	if (count($events) > 0) {
		foreach($manager_ids as $user_id) {
			foreach($events as $event) {
				$sql  = "SELECT * FROM reminders WHERE `event` LIKE " . ToSQL($event,"TEXT") . " ";
				$sql .= " AND `user_id`=" . ToSQL($user_id,"INTEGER");
				$sql .= " AND ( `added` BETWEEN DATE(DATE_ADD(NOW(),INTERVAL -7 DAY)) AND DATE(NOW()));";
				$sql_report .= $sql . "\r\n";
				$db->query($sql);
				if(!$db->next_record()) {
					$sql  = " INSERT IGNORE INTO reminders(`event`,`user_id`,`added`) ";
					$sql .= " VALUES(" . ToSQL($event,"TEXT") . "," . ToSQL($user_id,"INTEGER"). ",DATE(NOW()));";
					$sql_report_insert .= $sql . "\r\n";
					$db->query($sql);
					$total_insert++;
				}
			}
		}
	}
	
	if (count($english_only_events) > 0) {
		foreach($english_ids as $user_id) {
			foreach($english_only_events as $event) {
				$sql  = "SELECT * FROM reminders WHERE `event` LIKE " . ToSQL($event,"TEXT") . " ";
				$sql .= " AND `user_id`=" . ToSQL($user_id,"INTEGER");
				$sql .= " AND ( `added` BETWEEN DATE(DATE_ADD(NOW(),INTERVAL -7 DAY)) AND DATE(NOW()));";
				$sql_report .= $sql . "\r\n";
				$db->query($sql);
				if(!$db->next_record()) {
					$sql  = " INSERT IGNORE INTO reminders(`event`,`user_id`,`added`) ";
					$sql .= " VALUES(" . ToSQL($event,"TEXT") . "," . ToSQL($user_id,"INTEGER"). ",DATE(NOW()));";
					$sql_report_insert .= $sql . "\r\n";
					$db->query($sql);
					$total_insert++;
				}
			}
		}
	}	
	
	$mail_body = "";
	if (strlen($sql_report) == 0) {
		$mail_body = "No reminders!";
	} else if ($total_insert > 0) {
		$mail_body  = "Total insert records: " . $total_insert . "(" . count($events) . ")\r\n";
		$mail_body .= str_pad("",80,"=") . "\r\n";
		$mail_body .= implode("\r\n",$events) . "\r\n";
		$mail_body .= str_pad("",80,"=") . "\r\n";
		$mail_body .= $sql_report . "\r\n";
		$mail_body .= str_pad("",80,"=") . "\r\n";
		$mail_body .= $sql_report_insert;
		//@mail("artem@viart.com.ua","Viart Reminders",$mail_body);
	}

?>
