#!/usr/local/bin/php4 -q
<?php

  	include_once("./db_mysql.inc");
  	include_once("./includes/db_connect.php");
  	include_once("./includes/common_functions.php");
	include_once("./includes/viart_support.php");

	// privileges
	define("PRIV_DEVELOPER", 1);
	define("PRIV_TESTER", 2);
	define("PRIV_ARCHITECT", 3);
	define("PRIV_PM", 4);
	define("PRIV_PARTNER", 5);
	define("PRIV_ADMIN", 6);

	// message types
	define("MSG_PROJECT_CREATED", 1);
	define("MSG_TASK_CREATED", 2);
	define("MSG_TASK_COMPLETED", 3);
	define("MSG_TASK_UPDATED", 4);
	define("MSG_MESSAGE_RECEIVED", 5);	
	
	$max_ticket_message_id = 0;
	
	$db->query("SELECT MAX(last_ticket_message_id) FROM tasks ");
	if ($db->next_record()) {
		$max_ticket_message_id = $db->f(0);
	}

	$support = array();

	if ($viart_com_accessible) {
		log_write("Connected");
			
		$sql = " SELECT support_id, message_id, message_text, admin_id_assign_by, admin_id_assign_to ";
		$sql.= " FROM ".$table_prefix."support_messages ";
		$sql.= " WHERE (DATE_ADD(date_added, INTERVAL 200 MINUTE)>NOW() ";
/*		if ($max_ticket_message_id) {
			$sql.= " AND message_id>".$max_ticket_message_id;
		}*/
		$sql .= ") OR DATE_ADD(date_modified, INTERVAL 200 MINUTE)>NOW() ";
		$sql.= " ORDER BY date_modified ASC, date_added ASC, message_id ASC ";
		
		$db_viart_com->query($sql);
		
		while($db_viart_com->next_record()) {
			$support[] = array($db_viart_com->f("support_id"), $db_viart_com->f("message_id"), $db_viart_com->f("message_text"),
							 $db_viart_com->f("admin_id_assign_by"), $db_viart_com->f("admin_id_assign_to"));
		}
		
		foreach ($support as $support_message) {
			list($support_id, $message_id, $message_text, $admin_id_assign_by, $admin_id_assign_to) = $support_message;
			monitor_task($support_id, $message_id, $admin_id_assign_by, $admin_id_assign_to);			
		}
		
		$deferred_messages = array();
		$sql = " SELECT t.task_id, m.message_id, m.user_id, t.responsible_user_id, m.message ";
		$sql.= " FROM tasks t INNER JOIN messages m ON (t.task_id=m.identity_id AND m.identity_type='task') ";
		$sql.= " WHERE m.ticket_created=-1 ";
		$sql.= " ORDER BY m.message_date ASC ";
		$db->query($sql);
		while($db->next_record())
		{
			$deferred_messages[] = $db->Record;
		}
		
		if (sizeof($deferred_messages)) {
			foreach ($deferred_messages as $dm) {
				log_write("Deferred message found: task:".$dm["task_id"]."/".$dm["message_id"]." assign by: ".$dm["user_id"]." to ".$dm["responsible_user_id"]);
				add_viart_support_message($dm["task_id"], $dm["message_id"], $dm["user_id"], $dm["responsible_user_id"], $dm["message"]);			
			}
		}
		
		log_write("Disconnected");		
	} else {
		log_write("Connect Failed");
	}

?>