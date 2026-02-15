<?php

	include_once("./includes/common_functions.php");
	
	$db = new DB_Sql();
	$db->Database = DATABASE_NAME;
	$db->User     = DATABASE_USER;
	$db->Password = DATABASE_PASSWORD;
	$db->Host     = DATABASE_HOST;	
	
	$db_viart_com = new DB_Sql();
	$db_viart_com->Database = VIART_COM_DATABASE_NAME;
	$db_viart_com->User     = VIART_COM_DATABASE_USER;
	$db_viart_com->Password = VIART_COM_DATABASE_PASSWORD;
	$db_viart_com->Host     = VIART_COM_DATABASE_HOST;	
	
	$viart_com_table_prefix = "";
	$viart_com_accessible = @mysql_connect($db_viart_com->Host, $db_viart_com->User, $db_viart_com->Password);
	$monitor_tickets_project_id = 113;
	$table_prefix = "";
	$eol = "\r\n";
	$log = false;
	
	$db->query("UPDATE users SET helpdesk_user_id=44 WHERE user_id=132");
	function add_viart_support_message(
		$monitor_task_id,
		$monitor_message_id, 
		$monitor_user_assign_by,
		$monitor_user_assign_to,
		$message_text
	) {
		global $db, $db_viart_com, $va_table_prefix, $viart_com_accessible, $log;

		$record_inserted = false;
		$support_id = 0;
		$admin_id_assign_by = 0;
		$admin_id_assign_to = 0;		
		
		$sql = "SELECT ticket_id FROM tasks WHERE task_id=".to_sql($monitor_task_id, "Number");
		$db->query($sql);
		if ($db->next_record()) {
			$support_id = $db->f("ticket_id");
		}
		if (!$support_id) {
			log_write('cant find support_id to for task ' . $monitor_task_id);
			return;
		}
		
		$sql = "SELECT helpdesk_user_id FROM users WHERE user_id=".to_sql($monitor_user_assign_by, "Number");
		$db->query($sql);
		if ($db->next_record()) {
			$admin_id_assign_by = $db->f("helpdesk_user_id");
		}		
		if (!$admin_id_assign_by) {
			log_write('cant find assign by for task ' . $monitor_task_id);
			return;
		}		
		
		$sql = "SELECT helpdesk_user_id, ticket_tasks FROM users WHERE user_id=".to_sql($monitor_user_assign_to, "Number");
		$db->query($sql);
		if ($db->next_record()) {
			$admin_id_assign_to = $db->f("helpdesk_user_id");
			$ticket_tasks = $db->f("ticket_tasks");
		}
		if (!$admin_id_assign_to) {
			log_write('cant find assign to for task ' . $monitor_task_id);
			return;
		}
		
		$message_text = stripslashes($message_text);
		
		if ($support_id && $admin_id_assign_by && $admin_id_assign_to)
		{
			if ($viart_com_accessible) {
			$sql = " SELECT dep_id FROM ".$va_table_prefix."support ";
			$sql.= " WHERE support_id=".to_sql($support_id, "Number", true, true, true);
			$db_viart_com->query($sql);
			if ($db_viart_com->next_record()) {
				$dep_id = $db_viart_com->f("dep_id");
			} else {
				$dep_id = 1;
			}
			
			$attachment_text = get_attachment_text('support_message', $monitor_message_id);
			$message_text    = $attachment_text.$message_text;
			
			$sql = "INSERT INTO ".$va_table_prefix."support_messages ";
			$sql.= " (support_id, dep_id, internal, support_status_id, admin_id_assign_by, admin_id_assign_to ";
			$sql.= ", message_text, date_added, is_user_reply, is_html, subject) VALUES ";
			$sql.= "( ".to_sql($support_id, "Number", true, true, true);
			$sql.= ", ".to_sql($dep_id, "Number", true, true, true);
			$sql.= ", 1, 9 ";
			$sql.= ", ".to_sql($admin_id_assign_by, "Number", true, true, true);
			$sql.= ", ".to_sql($admin_id_assign_to, "Number", true, true, true);
			$sql.= ", ".to_sql($message_text, "Text", true, true, true);
			$sql.= ", NOW(), 0, 0, 'Monitor: Reassigned Task') ";
			$record_inserted = $db_viart_com->query($sql);
			
			$message_id = 0;
			$db_viart_com->query("SELECT LAST_INSERT_ID() ");
			if ($db_viart_com->next_record()) {
				$message_id = $db_viart_com->f(0);
			}			
			
			$sql = " UPDATE tasks SET last_ticket_message_id=".to_sql($message_id, "Number");
			$sql.= " WHERE task_id=".to_sql($monitor_task_id, "Number");
			$db->query($sql);
			if (isset($ticket_tasks) && $ticket_tasks==0) {
				$db->query("UPDATE tasks SET is_closed=1 WHERE task_id=".to_sql($monitor_task_id, "Number"));				
			}
			
			$sql = "UPDATE ".$va_table_prefix."support SET ";
			$sql.= "  admin_id_assign_to=".to_sql($admin_id_assign_to, "Number", true, true, true);
			$sql.= ", admin_id_assign_by=".to_sql($admin_id_assign_by, "Number", true, true, true);
			$sql.= ", support_status_id=9 ";
			$sql.= ", date_modified=NOW() ";
			$sql.= " WHERE support_id=".to_sql($support_id, "Number", true, true, true);
			$db_viart_com->query($sql);
			
			log_write("viart.com: Support message (".$support_id."/".$message_id.") inserted for monitor task (".$monitor_task_id."/".$monitor_message_id.")", true);
			
			send_viart_message($message_id, $admin_id_assign_to, $message_text);
			//exit;
			
			$sql = " UPDATE messages SET ticket_created=1 WHERE message_id=".to_sql($monitor_message_id, "Number");
			$db->query($sql);			
			} else {
				$sql = "UPDATE messages SET ticket_created=-1 WHERE message_id=".to_sql($monitor_message_id, "Number");
				$db->query($sql);
			}
		}
		
		return $record_inserted;
		
	}
	
	function monitor_task($support_id, $message_id, $admin_id_assign_by, $admin_id_assign_to) {		
		global $db, $table_prefix, $log;
		
		// send task to a monitor:
		$monitor_task_id = false;
		$monitor_assign_by = false;
		$monitor_assign_to = false;
		$monitor_ticket_tasks = false;
		$last_ticket_message_id = false;
				
		$sql = "SELECT task_id, last_ticket_message_id FROM tasks WHERE ticket_id=".to_sql($support_id, "Number");
		$db->query($sql);
		if ($db->next_record()) {					
			$monitor_task_id = $db->f("task_id");
			$last_ticket_message_id = $db->f("last_ticket_message_id");
		}
		$sql = " SELECT user_id FROM users WHERE ";
		$sql.= " helpdesk_user_id=".to_sql($admin_id_assign_by, "Number");
		$db->query($sql);
		if ($db->next_record()) {					
			$monitor_assign_by = $db->f("user_id");
		}
		$sql = " SELECT user_id, ticket_tasks FROM users ";
		$sql.= " WHERE helpdesk_user_id=".to_sql($admin_id_assign_to, "Number");
		$db->query($sql);
		if ($db->next_record()) {
			$monitor_assign_to = $db->f("user_id");
			$monitor_ticket_tasks = $db->f("ticket_tasks");
		}
		
		//create message in the task if task already exists
		//or create task if the ticket has been reassigned 
		//not to support manager and not to a client		
		
		if (!$last_ticket_message_id || $last_ticket_message_id<$message_id) {
			//log_write("viart.com: new support message (".$support_id."/".$message_id.") monitor task: ".($monitor_task_id ? $monitor_task_id.", last ticket for this task: ".$last_ticket_message_id : "No"));
			if ($monitor_task_id) {
				add_monitor_task_message($support_id, $message_id, $monitor_task_id, $monitor_assign_by, $monitor_assign_to);
			} elseif($monitor_assign_to>0 && $monitor_ticket_tasks) {
				create_monitor_task($support_id, $message_id, $monitor_assign_by, $monitor_assign_to);
			}
		}
	}
	
	function create_monitor_task($support_id, $message_id, $monitor_assign_by, $monitor_assign_to)
	{
		global $db_viart_com, $db, $table_prefix, $monitor_tickets_project_id, $viart_com_accessible, $eol, $log;
		
		$record_inserted = false;
		$sql = " SELECT summary, user_email, description ";
		$sql.= " FROM ".$table_prefix."support ";
		$sql.= " WHERE support_id=".to_sql($support_id, "Number", true, true, true);
		$db_viart_com->query($sql);
		if ($db_viart_com->next_record() && $viart_com_accessible)
		{
			$ticket_title = $db_viart_com->f("summary");
			$ticket_message = $db_viart_com->f("description");
			$ticket_email = $db_viart_com->f("user_email");
			
			$task_title = $ticket_title;
			$viart_ticket = "Viart ticket: http://www.viart.com/va/admin_support_reply.php?support_id=".$support_id.$eol.$eol;
			
			$task_message = $viart_ticket.get_attachment_text('task',$support_id).make_task_text($support_id);
			
			$sql = " INSERT INTO tasks (project_id, task_title, task_desc, task_status_id, responsible_user_id, creation_date ";
			$sql.= " ,planed_date, priority_id, task_type_id, created_person_id, is_closed, client_id, is_planned, ticket_id ";
			$sql.= " ,last_ticket_message_id) ";
			$sql.= " VALUES (";
			$sql.= to_sql($monitor_tickets_project_id, "Number");
			$sql.= ", ".to_sql($task_title, "Text");
			$sql.= ", ".to_sql($task_message, "Text");
			$sql.= ", 7 ";
			$sql.= ", ".to_sql($monitor_assign_to, "Number");
			$sql.= ", NOW() ";
			$sql.= ", DATE_ADD(NOW(), INTERVAL 1 DAY) ";
			$sql.= ", 1 ";
			$sql.= ", 1 ";
			$sql.= ", ".to_sql($monitor_assign_by, "Number");
			$sql.= ", 0";
			$sql.= ", ".to_sql(get_monitor_client_id($ticket_email), "Number");
			$sql.= ", ".to_sql( (!is_monitor_manager($monitor_assign_to) || $monitor_assign_by==$monitor_assign_to), "Number", false);
			$sql.= ", ".to_sql($support_id, "Number");
			$sql.= ", ".to_sql($message_id, "Number");
			$sql.= ")";
			$record_inserted = $db->query($sql);
			
			$task_id = 0;
			$db->query("SELECT LAST_INSERT_ID() ");
			if ($db->next_record()) {
				$task_id = $db->f(0);
			}		

			send_message('task', $task_id, $monitor_assign_by);
		
			log_write("monitor: monitor task: ".$task_id." created for viart support message (".$support_id."/".$message_id.")");
		}		
		
		return $record_inserted;
	}
		
	function add_monitor_task_message($support_id, $message_id, $monitor_task_id, $monitor_assign_by=false, $monitor_assign_to=false)
	{
		global $db, $db_viart_com, $table_prefix, $monitor_tickets_project_id, $viart_com_accessible, $log;
		
		$record_inserted = false;
		if ($viart_com_accessible)
		{
			$sql = " SELECT s.user_email, sm.internal, sm.subject, sm.message_text, sm.is_user_reply ";
			$sql.= " FROM ".$table_prefix."support s ";
			$sql.= " INNER JOIN ".$table_prefix."support_messages sm ON (s.support_id=sm.support_id) ";
			$sql.= " WHERE s.support_id=".to_sql($support_id, "Number", true, true, true);
			$sql.= " AND sm.message_id=".to_sql($message_id, "Number", true, true, true);
			$db_viart_com->query($sql);
			
			while($db_viart_com->next_record())
			{
				$message_title = $db_viart_com->f("subject");
				$message_text = $db_viart_com->f("message_text");
				$message_email = $db_viart_com->f("user_email");
				$is_user_reply = $db_viart_com->f("user_reply");
				$internal = $db_viart_com->f("internal");
				$monitor_status_id = 9;
				
				$message_text = get_attachment_text('message', $message_id).$message_text;
				
				if (!$monitor_assign_to) {
					$is_user_reply = true;
					if ($monitor_assign_by) {
						$monitor_assign_to = $monitor_assign_by;
					} else {
						$sql = "SELECT responsible_user_id FROM tasks WHERE task_id=".to_sql($monitor_task_id, "Number");
						$db->query($sql);
						if ($db->next_record()) {
							$monitor_assign_to = $db->f("responsible_user_id");							
							if (!$monitor_assign_to) {
								$monitor_assign_to = 0;
							}
							$monitor_assign_by = $monitor_assign_to;
						}
					}
				} else {
					$is_user_reply = false;
				}
			
				$sql = " INSERT INTO messages ";
				$sql.= " (message_date, user_id, identity_id, identity_type, message, responsible_user_id, status_id, user_reply) ";
				$sql.= " VALUES (";
				$sql.= " NOW(), ".to_sql($monitor_assign_by, "Number");
				$sql.= ", ".to_sql($monitor_task_id, "Number");			
				$sql.= ", 'task', ".to_sql($message_text, "Text");
				$sql.= ", ".to_sql($monitor_assign_to, "Number", false);
				$sql.= ", ".to_sql($monitor_status_id, "Number");
				$sql.= ", ".to_sql($is_user_reply, "Number");
				$sql.= " )";
				
				$record_inserted = $db->query($sql);
				
				$monitor_message_id = 0;
				$db->query("SELECT LAST_INSERT_ID() ");
				if ($db->next_record()) {
					$monitor_message_id = $db->f(0);
				}	
				
				$close_sql = "";
				$sql = " SELECT ticket_tasks FROM users WHERE user_id=".to_sql($monitor_assign_to, "Number");
				$db->query($sql);
				if ($db->next_record())
				{
					$ticket_tasks = $db->f("ticket_tasks");
					
					if ($ticket_tasks) {
						$close_sql = ",is_closed=0 ";
					} else {
						$close_sql = ",is_closed=1 ";
					}
					
					if ($internal && !$is_user_reply) {
						$close_sql .= ",date_reassigned=NOW() ";
					}
					log_write("monitor: user_id:".$monitor_assign_to.", ticket_task:".$ticket_tasks." is_internal:".$internal." is_user_reply:".$is_user_reply." close_sql:".$close_sql);
				}
				
			
				calculate_monitor_time($monitor_task_id, $monitor_assign_by, $monitor_assign_to);
				
				$sql = " UPDATE tasks ";
				$sql.= " SET responsible_user_id=".to_sql($monitor_assign_to, "Number");
				$sql.= " ,last_ticket_message_id=".to_sql($message_id, "Number");
				$sql.= " ,is_planned=".to_sql((!is_monitor_manager($monitor_assign_to) || $monitor_assign_by==$monitor_assign_to), "Number", false);
				$sql.= " ,task_status_id=".to_sql($monitor_status_id, "Number");
				$sql.= $close_sql;
				$sql.= " WHERE task_id=".to_sql($monitor_task_id, "Number");					
				$db->query($sql);
								
				send_message('message', $monitor_message_id, $monitor_assign_by);
				
				log_write("monitor: monitor message (".$monitor_task_id."/".$monitor_message_id.") created for viart support message (".$support_id."/".$message_id.")");
			}
		}
		return $record_inserted;
	}	
	
	function get_monitor_client_id($email) {
		global $db;
		
		$client_id = 0;
		$sql = "SELECT client_id FROM clients WHERE client_email=".to_sql($email, "Text");
		$db->query($sql);
		if ($db->next_record())
		{
			$client_id = $db->f("client_id");
		}
		return $client_id;
	}
	
	function monitor_process_message($type, $message, $message_id)
	{
	  	global $db, $log;
	  	
		$level_colors=array(
			"0" => "red", "1" => "blue", "2" => "black", "3" => "navy", "4" => "grey",
			"5" => "red", "6" => "green", "7" => "blue", "8" => "black"
		);
	  	
	  	if ($type == "task") {
	  		$path = "attachments/task/";
	  	} else {
	  		$path = "attachments/message/";
	  	}
	  	
	  	$message = preg_replace("/</","&lt;",$message);
	  	$message = preg_replace("/!^>/","&gt;",$message);

	  	$sql="SELECT * FROM attachments WHERE identity_type=".to_sql($type, "Text", false)." AND identity_id=".$message_id;
	  	$db->query($sql);
	  	while ($db->next_record())
		{
	 		$AbsoluteUri ='http'.'://'.$_SERVER["SERVER_NAME"].substr($_SERVER["REQUEST_URI"],0,strrpos(str_replace($_SERVER["QUERY_STRING"],"",$_SERVER["REQUEST_URI"]),'/')+1).$path;
	 		$full_path = $AbsoluteUri;
			$mes_file = $db->Record["file_name"];
			$cur_file = $full_path.strval($message_id)."_".$mes_file;

			if ($db1->Record["attachment_type"] == "image")
			{
				$message = str_replace("[".$mes_file."]","<img src='$cur_file' border=0>",$message);
			}
			else
			{
				$message = str_replace("[".$mes_file."]","<a href='$cur_file'>[$mes_file]</a>",$message);
			}
		}
		
		$msg_strings = split("\r\n",$message);
		$message     = "";
		$last_level = 0;

		//-- find the maximum level
		$max_level = 0;
		foreach ($msg_strings as $string)
		{
			if (is_array($string))
			{
	  			$string = $string[0];
	  		}
	 		$cur_level = count_message_level($string);
			if($cur_level > $max_level) {
				$max_level = $cur_level;
			}
		}

		$cur_message_colors = array_slice($level_colors,8 - $max_level);
		$message.="<div style='color:".$cur_message_colors[0].";'>";

		//-- output each string
		foreach ($msg_strings as $string)
		{
			if (is_array($string)) $string = $string[0];
			$cur_level = count_message_level($string);
			$string    = preg_replace("/^>+/","",$string);
			if (!trim($string)) $string="&nbsp;";
			$level_diff = $last_level-$cur_level;
			if($level_diff>0) {
				$string = get_message_end_tags($level_diff).$string;
			} elseif ($level_diff<0) {
		      	$string = get_message_start_tags($last_level,$cur_level-$last_level, $cur_message_colors).$string;
			} else {
				$string = "<br>".$string;
			}
			$last_level = $cur_level;
			$message.=$string;
		}
	  	
		//-- add end tags
	  	$message.=get_message_end_tags($last_level)."</div>";	  	
	  	return $message;
	}
	
	function send_message($type, $id, $assign_by)
	{
		global $db, $log;
		
		$user_name = "";
		$sql = " SELECT CONCAT(u.first_name, ' ', u.last_name) AS user_name FROM users u WHERE u.user_id=".to_sql($assign_by, "Number");
		$db->query($sql);
		if ($db->next_record()) {
			$user_name = $db->f("user_name");
		}
		
		switch($type)
		{			
			case "task" :
			
			$sql  = " SELECT p.project_title, t.task_title, t.task_desc, ";
			$sql .= " ts.task_status_title, t.responsible_user_id, ";
			$sql .= " CONCAT(u.first_name,' ',u.last_name) AS created_user_name, ";
			$sql .= " CONCAT(ur.first_name,' ',ur.last_name) AS responsible_user_name ";
			$sql .= " FROM tasks t ";
			$sql .= " LEFT JOIN projects p ON t.project_id=p.project_id ";
			$sql .= " LEFT JOIN users u ON t.responsible_user_id=u.user_id ";
			$sql .= " LEFT JOIN users ur ON t.responsible_user_id = ur.user_id ";
			$sql .= " LEFT JOIN tasks_status ts ON t.task_status_id=ts.task_status_id ";
			$sql .= " WHERE t.task_id=".to_sql($id, "Number");
			$db->query($sql);
			if ($db->next_record()) {
				$project_title         = $db->f("project_title");
				$task_status_title     = $db->f("task_status_title");
				$task_title            = $db->f("task_title");
				$task_message          = $db->f("task_desc");
				$monitor_assign_to     = $db->f("responsible_user_id");
				$responsible_user_name = $db->f("responsible_user_name");

				$tags = array(
					"privilege_id" 			=> 1,
					"task_id" 				=> $id,
					"task_title" 			=> $task_title,
					"project_title"			=> $project_title,
					"responsible_user_id" 	=> $monitor_assign_to,
					"responsible_user_name" => $responsible_user_name,
					"user_name" 			=> $user_name,
					"task_status"			=> $task_status_title
				);
				send_enotification(MSG_TASK_CREATED, $tags);				
			}
			break;
			
			case "message" :
			
			$sql  = " SELECT p.project_title, t.task_title, t.task_desc, ";
			$sql .= " ts.task_status_title, t.responsible_user_id, m.message, ";
			$sql .= " CONCAT(u.first_name,' ',u.last_name) AS created_user_name, ";
			$sql .= " CONCAT(ur.first_name,' ',ur.last_name) AS responsible_user_name ";
			$sql .= " FROM messages m ";
			$sql .= " INNER JOIN tasks t ON m.identity_id=t.task_id ";
			$sql .= " LEFT JOIN projects p ON t.project_id=p.project_id ";
			$sql .= " LEFT JOIN users u ON t.created_person_id = u.user_id ";
			$sql .= " LEFT JOIN users ur ON t.responsible_user_id = ur.user_id ";
			$sql .= " LEFT JOIN tasks_status ts ON t.task_status_id=ts.task_status_id ";
			$sql .= " WHERE m.message_id=".to_sql($id, "Number");
			$db->query($sql);
			if ($db->next_record()) {
				$project_title = $db->f("project_title");
				$task_status_title = $db->f("task_status_title");
				$task_title = $db->f("task_title");
				$message = $db->f("message");
				$monitor_assign_to = $db->f("responsible_user_id");
				$responsible_user_name = $db->f("responsible_user_name");

				$tags = array(
					"message" 				=> stripslashes(monitor_process_message('message',$message,$id)),
					"privilege_id" 			=> 1,
					"task_id" 				=> $id,
					"task_title" 			=> $task_title,
					"TASK_TITLE" 			=> $task_title,
					"project_title"			=> $project_title,
					"PROJECT_TITLE"			=> $project_title,
					"responsible_user_id" 	=> $monitor_assign_to,
					"responsible_user_name" => $responsible_user_name,
					"user_name" 			=> $user_name,
					"task_status"			=> $task_status_title
				);
			
				send_enotification(MSG_MESSAGE_RECEIVED, $tags);				
			}
			break;			
		}
	}
	
	function get_attachment_text($type, $id)
	{
		global $table_prefix, $db_viart_com, $db, $eol, $log;
		
		$attachments = array();
		$attachment_text = "";
		
		if ($type== "support_message") {
			$sql = " SELECT attachment_id, identity_id, file_name FROM attachments ";
			$sql.= " WHERE identity_type='message' AND identity_id=".to_sql($id, "Number");			
			$db->query($sql);
			if ($db->num_rows()) {
				$attachment_text = "Attachment(s):\r\n";				
				while ($db->next_record())
				{
					$file_name = $db->f("file_name");
					$attachment_text.= "http://viart.com.ua/monitor/attachments/message/".$id."_".$file_name."\r\n";
				}
				$attachment_text.="\r\n";
			}
		} else {
			$sql = "";
			if ($type == "task") {
				$sql = " SELECT attachment_id, file_name FROM ".$table_prefix."support_attachments ";
				$sql.= " WHERE support_id=".to_sql($id, "Number", true, true, true)." ORDER BY support_id ASC, message_id ASC ";
			} elseif ($type=="message") {
				$sql = " SELECT attachment_id, file_name FROM ".$table_prefix."support_attachments ";
				$sql.= " WHERE message_id=".to_sql($id, "Number", true, true, true)." ORDER BY attachment_id ASC ";				
			}
			if ($sql) {
				$db_viart_com->query($sql);
				while($db_viart_com->next_record())
				{
					$attachments[] = array($db_viart_com->f("attachment_id"), $db_viart_com->f("file_name"));
				}
			
				if (sizeof($attachments)) {
					$attachment_text = "Attachment(s):\r\n";
					foreach ($attachments as $attachment) {
						list($attachment_id, $file_name) = $attachment;
			
						$attachment_text.= $file_name.":   http://www.viart.com/va/admin_support_attachment.php";
						$attachment_text.= "?atid=".$attachment_id .$eol;
					}				
					$attachment_text.="\r\n";
				}
			}
		}	
		
		return $attachment_text;
	}
	
	function send_viart_message($message_id, $admin_id_assign_to, $message_text)
	{
		global $table_prefix, $db_viart_com, $eol, $log;
		
		$sql = "SELECT email FROM ".$table_prefix."admins WHERE admin_id=" . to_sql($admin_id_assign_to, "Number", true, true, true);
		$db_viart_com->query($sql);
		if ($db_viart_com->next_record()) {
			$admin_email = $db_viart_com->f("email");
		} else {
			$admin_email = "";
		}
		
		$email_headers = array();
		$email_headers["from"] = "monitor@viart.com.ua";
		//$email_headers["cc"] = ;
		$email_headers["reply_to"] = "monitor@viart.com.ua";
		//$email_headers["return_path"] = ;
		$email_headers["mail_type"] = "";
		$subject = "Monitor: Reassigned Task";
		$message_text = get_attachment_text('support_message', $message_id).$message_text;
		
		if ($email_headers["mail_type"]) {
			$message_text = nl2br(htmlspecialchars($message_text));
		}
    
		$message_text = preg_replace("/\r\n|\r|\n/", $eol, $message_text);
		return @mail($admin_email, $subject, $admin_message, $email_headers);
	}
	
	function calculate_monitor_time($monitor_task_id, $monitor_assign_by, $monitor_assign_to)
	{
		global $db, $log;

		//if task was in progress -> stop it and insert record in time_report table;
		$task_status_id = 0;
		$sql = " SELECT started_time, task_status_id, responsible_user_id ";
		$sql.= ", ((UNIX_TIMESTAMP(NOW())-UNIX_TIMESTAMP(started_time))/3600) as spent_hours ";
		$sql.= " FROM tasks WHERE task_id=".to_sql($monitor_task_id, "Number");
		$db->query($sql);
		if ($db->next_record())
		{
			$task_status_id = $db->f("task_status_id");
			$responsible_user_id = $db->f("responsible_user_id");
			$started_time = $db->f("started_time");
			$spent_hours = $db->f("spent_hours");
		}
		
		if ($task_status_id == 1)
		{			
			// -- task was in progress and now it's stopped
			$sql = " INSERT INTO time_report (user_id,started_date,task_id,report_date,spent_hours) VALUES (";
			$sql.= to_sql($responsible_user_id, "Number");
			$sql.= ", ".to_sql($started_time, "Text");
			$sql.= ", ".to_sql($monitor_task_id, "Number");
			$sql.= ", NOW() ";
			$sql.= ", ".to_sql($spent_hours, "Number");
			$sql.= ")";
			$db->query($sql);
			
			$sql = " UPDATE tasks SET actual_hours = (".to_sql($spent_hours, "Number")." + actual_hours) ";
			$sql .= " WHERE task_id = ".to_sql($monitor_task_id, "Number");
			$db->query($sql);
		}		
	}
	
	function count_message_level($paragraph)
	{
		$level = 0;
		$pos   = 0;
		$ch    = substr($paragraph,$pos,1);

		while($ch == ">")
		{
			$level++;
			$pos++;
			$ch = substr($paragraph,$pos,1);
		}

		return $level;
	}	
	
	function get_message_end_tags($level_number)
	{
		$tags="";
		for($i=1;$i<=$level_number;$i++) $tags.="</div>\n";
		return $tags;
	}

	function get_message_start_tags($start_level,$level_number, $cur_message_colors)
	{
		$tags="";

		for($i=$start_level;$i<$start_level+$level_number;$i++)
		{
			$k=$i+1;
			if (array_key_exists($k,$cur_message_colors))
			{
				$tags.="<div style='".
                	"color:".$cur_message_colors[$k].";".
                	"margin-left:".(10)."pt".";".
                	"padding-left:10pt;".
                	"border-left-style:solid;".
                	"border-left-width:thin;"."'>\n";
			}
		}
		return $tags;
	}
	
	function make_task_text($support_id) {
		global $table_prefix, $db_viart_com, $eol, $log;
		
		$task_text = "";
		
		$messages = array();
		
		$sql = " SELECT date_added, summary, description ";
		$sql.= " FROM support WHERE support_id=".to_sql($support_id, "Text", true, true, true);
		$db_viart_com->query($sql);
		if ($db_viart_com->next_record()) {
			$support_date_added = $db_viart_com->f("date_added");
			$support_summary = $db_viart_com->f("summary");
			$support_description = $db_viart_com->f("description");
		}

		$sql = " SELECT date_added, message_text ";
		$sql.= " FROM support_messages WHERE support_id=".to_sql($support_id, "Text", true, true, true);
		$sql.= " ORDER BY date_added DESC LIMIT 3 ";
		$db_viart_com->query($sql);
		while ($db_viart_com->next_record()) {
			$message_date_added = $db_viart_com->f("date_added");
			$message_text = $db_viart_com->f("message_text");
			$messages[] = array($message_date_added, $message_text);
		}
		$messages[] = array($support_date_added, $support_description);
		/*
		$level = 0;
		foreach ($messages as $message) {
			if ($level<3) {
				if (strlen($task_text)) {
					$task_text.= $eol.$eol.$message[0].$eol;
				}
				$task_text.= $message[1];
		
				$level++;
			}
		}*/
		
		if(isset($messages[0][1])) {
			$task_text = $messages[0][1];
		} else {
			$task_text = $support_summary;
		}
		
		//simply the last!	
	
		return $task_text;
	}
	
	function to_sql($value, $type, $use_null = true, $is_delimiters = true, $is_viart_com = false)
	{
		$type = strtolower($type);		
		if ($value == "") {
			if ($use_null) { return "NULL";}
			elseif ($type == "number" || $type == "integer" || $type == "float") {
				$value = 0;
			}
		}
		elseif ($type == "number") {return doubleval($value);}
		elseif ($type == "integer") {return intval($value);}
		elseif ($type == "date") {			if (ereg("([0-9]{4})(-|\\|\/){1}([0-9]{1,2})(-|\\|\/){1}([0-9]{1,2})",$value,$t)){
				if (checkdate($t[3],$t[5],$t[1])) { $value = date("Y-m-d", mktime(0,0,0,$t[3],$t[5],$t[1]));}
					else { $value = "0000-00-00";}
			} else { $value = "0000-00-00";}
			$value = "'" . $value . "'";
		} else {
			$value = str_replace("'", "''", $value);
			$value = str_replace("\\", "\\\\", $value);
			$value = "'" . $value . "'";
		}
		return $value;
		
		//return ToSQL($value, $type, $use_null, $is_delimiters);
	}	
	
	function add_level_tags($message, $level)
	{
		$prefix = "";
		for ($i=0; $i<$level; $i++) {
			$prefix.=">";
		}
		
		$lines = split("\n", $message);
		
		foreach ($lines as $key=>$line) {
			$lines[$key] = $prefix.$line;
		}
		
		$message = implode("\n", $lines);
		
		return $message;
	}
	
	function is_monitor_manager($user_id)
	{
      	global $db, $log;
      	
		$is_manager = false;
      	$sql = " SELECT user_id FROM users WHERE user_id=".ToSQL($user_id, "Number");
      	$sql.= " AND privilege_id=4 AND (is_deleted=0 OR is_deleted IS NULL) AND is_viart=1 ";
      	$db->query($sql,__FILE__,__LINE__);
      	if($db->num_rows()) {
      		$is_manager = true;
      	}
		return $is_manager;
	}	
	
	function log_write($message, $break = false)
	{
		$log = @fopen("tickets_import.log","a");
		if ($log)
		{
			$log_message = date("Y-m-d H:i:s")." ".$message."\n";
			@fwrite($log, $log_message);
		}
		@fclose($log);
	}
?>