<?php

	chdir(dirname(__FILE__));
	if (!($argc)) die('Invalid call. Please run this script in command line.');
	include_once("./includes/common.php");
	include_once("./includes/date_functions.php");

	$is_holiday = false;
	$sql = 'SELECT * FROM national_holidays WHERE holiday_date=DATE(NOW());';
	$db->query($sql,__FILE__,__LINE__);
	if (($db->next_record()) || (date('D') == 'Sun') || (date('D') == 'Sat')) {
		$is_holiday = true;
	} else {
		$is_holiday = false;
	}

	if ($is_holiday) { die('Script was run on holiday!'); }

	$users = array();
	
	///who have delay notification
	$sql  = " SELECT dn.user_id AS user_id, count(cdn.user_id) AS cnt";
	$sql .= " FROM delay_notifications dn ";
	$sql .= " LEFT JOIN delay_notifications cdn ON (cdn.user_id=dn.user_id AND MONTH(cdn.note_date)=MONTH(NOW()) AND YEAR(cdn.note_date)=YEAR(NOW())) ";
	$sql .= " WHERE dn.note_date=DATE(NOW()) ";
	$sql .= " GROUP BY cdn.user_id;";
	$db->query($sql,__FILE__,__LINE__);
	while ($db->next_record())
	{
		$delay_user = $db->f("user_id");
		$delay_count = $db->f("cnt");
		if ($delay_count <= 5)
		{
			$users[] = $delay_user;
		}
	}
	
	///who still not have records in time_report today
	$sql = " SELECT user_id FROM time_report WHERE DATE(NOW())=DATE(started_date) AND TIME(started_date)<='09:05:00' GROUP BY user_id;";
	$db->query($sql,__FILE__,__LINE__);
	while ($db->next_record())
	{
		$user_id = $db->f("user_id");
		$users[] = $user_id;
	}
	
	///who have holidays
	$sql = " SELECT user_id FROM days_off WHERE DATE(NOW()) BETWEEN start_date AND end_date;";
	$db->query($sql,__FILE__,__LINE__);
	while ($db->next_record())
	{
		$user_id = $db->f("user_id");
		$users[] = $user_id;
	}
	
	///who work by some task(is online)
	$sql = " SELECT responsible_user_id AS user_id FROM tasks WHERE task_status_id = 1 AND is_wish = 0";
	$db->query($sql,__FILE__,__LINE__);
	while ($db->next_record())
	{
		$user_id = $db->f("user_id");
		$users[] = $user_id;
	}
	
	$user_ids = array();
	if (sizeof($users) > 0)
	{
		$sql  = " SELECT user_id FROM users";
		$sql .= " WHERE is_viart=1 AND is_flexible=0 AND is_deleted IS NULL";
		$sql .= " AND user_id NOT IN (" . implode(",", $users) . ")";
		$db->query($sql,__FILE__,__LINE__);
		while ($db->next_record())
		{
			$user_id = $db->f("user_id");
			$user_ids[] = $user_id;
		}
	}
	
	$head = "<html><body style='background-color: #FFFFFF; color: #000000; font: Tahoma; font-size: 12pt'>\n";
	$bottom = "</body></html>";
	
	$mail_subj = "Late warnings";
	$mail_head  = "From: monitor@viart.com.ua" . "\r\n";
	$mail_head .= "Content-type: text/html; charset=iso-8859-1" . "\r\n";

	$late = array();
	foreach($user_ids as $user_id)
	{
		$sql = "INSERT INTO warnings SET date_added = DATE(NOW()), description='Has been late after 11:00', user_id = " . ToSQL($user_id,"INTEGER");
		$db->query($sql,__FILE__,__LINE__);
		
		$sql  = "SELECT u.email,CONCAT(u.first_name, ' ', u.last_name) as name, COUNT(w.user_id) as how_times ";
		$sql .= " FROM (users u";
		$sql .= " LEFT JOIN warnings w ON (u.user_id=w.user_id AND MONTH(w.date_added) = MONTH(NOW()) AND YEAR(w.date_added)=YEAR(NOW()) AND w.description LIKE 'Has been late after 11:00'))";
		$sql .= " WHERE u.user_id=" . ToSQL($user_id,"NUMBER");
		$sql .= " GROUP BY u.user_id";
		
		$db->query($sql,__FILE__,__LINE__);
		if ($db->next_record())
		{
			$user_name = @$db->Record['name'];
			$how_times = @$db->Record['how_times'];
			$user_email = @$db->Record['email'];
			$late[] = array($user_name, $how_times);
			$current_letter = $head . "You have warned " . $how_times . "-th time during this month<br>\n" . $bottom;
			@mail($user_email, $mail_subj, $current_letter, $mail_head);
		}
	}
	
	$mail_head  = "From: monitor@viart.com.ua" . "\r\n";
	$mail_head .= "BCC: sanuch@viart.com.ua" . "\r\n";
	$mail_head .= "Content-type: text/html; charset=iso-8859-1" . "\r\n";
	
	if (sizeof($late) > 0)
	{
		$late_list  = $head . "\n";
		$late_list .= "Person who come late after 11:00 and count of late warnings for this mounth<br><font size='1'><br></font>\n";
		$late_list .= "<table border='1'><tr><td><b>Count</b></td><td><b>Person</b></td></tr>\n";
	
		usort($late,'custom_sort');
		foreach ($late as $v)
		{
			$late_list .= "<tr><td>" . $v[1] . "</td><td>" . $v[0] . "</td></tr>\n";
		}
		$late_list .= "</table>\n" . $bottom;
		
		//@mail('sanuch@viart.com.ua', $mail_subj, $late_list, $mail_head);
		@mail('artem@viart.com.ua', $mail_subj, $late_list, $mail_head);
	}
	
	exit;

	/*/
	//Old code
	while ($db->next_record())
	{
		$sql_add_warnings = 'INSERT INTO warnings
			SET date_added = DATE(NOW()), user_id = ' . $db->Record[0] . ', description=\'Has been late after 11:00\'
		';
		$dby->query($sql_add_warnings,__FILE__,__LINE__);

		$sql_late_names = 'SELECT email,CONCAT(first_name, \' \', last_name) as name, COUNT(users.user_id) as how_times
			FROM users,warnings WHERE users.user_id=' . $db->Record[0] . ' AND users.user_id=warnings.user_id
			AND warnings.description=\'Has been late after 11:00\' AND TO_DAYS(warnings.date_added)-TO_DAYS(NOW())+DAYOFMONTH(NOW())>0
			AND MONTH(warnings.date_added) = MONTH(NOW())
			GROUP BY users.user_id
		';
		$dby->query($sql_late_names,__FILE__,__LINE__);
		$dby->next_record();
		$late[] = array($dby->Record['name'], $dby->Record['how_times']);

		$current_letter = $head . 'You have warned ' . $dby->Record['how_times'] . '-th time during this month<br>' . $bottom;

		@mail($dby->Record['email'], 'Late warnings', $current_letter, 'From: monitor@viart.com.ua' . "\r\n" . 'Content-type: text/html; charset=iso-8859-1' . "\r\n");
	}

	usort($late,'custom_sort');
	foreach ($late as $v){
		$late_list .= '<tr><td>' . $v[1] . '</td><td>' . $v[0] . '</td></tr>';
	}
	$late_list .= '</table>' . $bottom;

	@mail('artem@viart.com.ua', 'Late warnings', $late_list, 'From: monitor@viart.com.ua' . "\r\n" . 'Content-type: text/html; charset=iso-8859-1' . "\r\n");
	/**/

	function custom_sort($a, $b){
		if ($a[1] < $b[1]) {
			return 1;
		} else {
			return 0;
		}
	}

?>