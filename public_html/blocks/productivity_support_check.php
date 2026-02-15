<?php
	chdir("../");	
	include("./includes/common.php");
	
	CheckSecurity(1);
	$__SUPPORT_TASKS = array(4841, 11395, 18110);
	
	$year_selected   = 2009;
	$month_selected  = 06;
	//$user_id         = 35;
	//$support_user_id = 21;
	
	$user_id         = 120;
	$support_user_id = 41;

	
	$viart_db = new DB_Sql();
	$viart_db->Database = VIART_COM_DATABASE_NAME;
	$viart_db->User     = VIART_COM_DATABASE_USER;
	$viart_db->Password = VIART_COM_DATABASE_PASSWORD;
	$viart_db->Host     = VIART_COM_DATABASE_HOST;
	
	$sql  = " SELECT t.task_id, t.task_title, tr.started_date, tr.report_date ";
	$sql .= " FROM ( tasks t ";
	$sql .= " INNER JOIN time_report tr ON tr.task_id=t.task_id) ";
	$sql .= " WHERE tr.user_id=" . $user_id;
	$sql .= " AND YEAR(tr.report_date) = " . $year_selected ;
	$sql .= " AND MONTH(tr.report_date) = " . $month_selected;
	$sql .= " LIMIT 0,100";
	$db->query($sql);
	
	echo "<table>";
	while ($db->next_record()) {
		$task_id      = $db->f("task_id"); // 4841
		$task_title   = $db->f("task_title");
		$started_date = $db->f("started_date");
		$report_date  = $db->f("report_date");	
					
		echo "<tr><td>$task_title</td><td>$started_date</td><td>$report_date</td></tr>";
		
		$sql  = " SELECT message_id, support_id, date_added ";
		$sql .= " FROM support_messages ";
		$sql .= " WHERE admin_id_assign_by= " . $support_user_id;
		$sql .= " AND (date_added BETWEEN DATE_SUB('$started_date', INTERVAL 2 HOUR) AND DATE_SUB('$report_date', INTERVAL 2 HOUR)) ";
		$sql .= " ORDER BY date_added";
		$viart_db->query($sql);
		if ($viart_db->next_record()) {
			if (in_array($task_id, $__SUPPORT_TASKS)) {
				$color = " style='color:green' ";
			} else {
				$color = " style='color:red' ";
			}
			echo "<tr><td colspan=3><table $color>";
			
			do {
				$support_id = $viart_db->f("support_id");
				$message_id = $viart_db->f("message_id");
				$date_added = $viart_db->f("date_added");
				echo "<tr><td><a href=http://www.viart.com/va/admin_support_reply.php?support_id=$support_id#$message_id>$support_id</a></td><td>$date_added</td></tr>";
				
			} while($viart_db->next_record());
			
			echo "</table></td></tr>";
		}
		
		
		
	}
	echo "</table>";
?>