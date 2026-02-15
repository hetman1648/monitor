<?php
	include ("./includes/common.php");
	if (!defined('API_KEY_TEAM_HOURS')) {
		$env_key = (function_exists('getenv') && getenv('MONITOR_API_KEY')) ? getenv('MONITOR_API_KEY') : '';
		define('API_KEY_TEAM_HOURS', $env_key !== '' ? $env_key : 'sayu_team_hours_8f3a2b1c9d4e5f6a7b8c9d0e1f2a3b4c');
	}
	$t = new iTemplate($sAppPath);
	$t->set_file("main", "ajax_responder.html");
	
	switch (GetParam("action")) {
		case "get_domains_list":
			get_domains_list(GetParam("domain"));
		break;
		case "get_clients_list":
			get_clients_list(GetParam("client"),(int) GetParam("task_id"));
		break;
		case "task_update_client":
			task_update_client((int) GetParam("client_id"),(int) GetParam("task_id"));
		break;
		case "get_tasks_list":
			get_tasks_list((int) GetParam("user_id"));
		break;
		case "add_task_message":
			ajax_add_task_message();
		break;
		case "get_subprojects":
			ajax_get_subprojects((int) GetParam("project_id"));
		break;
		case "get_task_messages":
			ajax_get_task_messages((int) GetParam("task_id"), (int) GetParam("offset"), (int) GetParam("limit"));
		break;
		case "kanban_move_task":
			ajax_kanban_move_task();
		break;
		case "add_project_member":
			ajax_add_project_member();
		break;
		case "remove_project_member":
			ajax_remove_project_member();
		break;
		case "close_task":
			ajax_close_task();
		break;
		case "get_project_users":
			ajax_get_project_users((int) GetParam("project_id"));
		break;
		case "upload_temp_attachment":
			ajax_upload_temp_attachment();
		break;
		case "reassign_task":
			ajax_reassign_task();
		break;
		case "quick_add_task":
			ajax_quick_add_task();
		break;
		case "search_clients":
			ajax_search_clients();
		break;
		case "search_domains":
			ajax_search_domains();
		break;
		case "get_team_hours":
			ajax_get_team_hours();
		break;
	}
		
	function ajax_search_domains() {
		global $db;
		header('Content-Type: application/json');
		$q = trim(GetParam("q"));
		$limit = 25;
		$out = array();
		if (strlen($q) < 1) {
			echo json_encode($out);
			return;
		}
		$q_like = ToSQL($q . "%", "text", false, true);
		$q_www = ToSQL("www." . $q . "%", "text", false, true);
		$seen = array();
		// Domains from tasks_domains (if table exists)
		@$db->query("SELECT d.domain_url, COALESCE(d.client_id, 0) AS client_id, COALESCE(c.client_name, '') AS client_name " .
			"FROM tasks_domains d " .
			"LEFT JOIN clients c ON c.client_id = d.client_id " .
			"WHERE (d.domain_url LIKE " . $q_like . " OR d.domain_url LIKE " . $q_www . ") " .
			"ORDER BY d.domain_url LIMIT " . (int)$limit);
		while ($db->next_record()) {
			$url = $db->f("domain_url");
			$key = strtolower(trim($url));
			if ($key !== '' && !isset($seen[$key])) {
				$seen[$key] = true;
				$out[] = array(
					"domain_url" => $url,
					"client_id" => (int) $db->f("client_id"),
					"client_name" => (string) $db->f("client_name")
				);
			}
		}
		// Domains from tasks (task_domain_url) - suggestions from existing tasks
		if (count($out) < $limit) {
			$sql2 = "SELECT DISTINCT t.task_domain_url AS domain_url, COALESCE(t.client_id, 0) AS client_id, COALESCE(c.client_name, '') AS client_name " .
				"FROM tasks t " .
				"LEFT JOIN clients c ON c.client_id = t.client_id " .
				"WHERE t.task_domain_url IS NOT NULL AND TRIM(t.task_domain_url) != '' " .
				"AND (t.task_domain_url LIKE " . $q_like . " OR t.task_domain_url LIKE " . $q_www . ") " .
				"ORDER BY t.task_domain_url LIMIT " . (int)($limit - count($out));
			$db->query($sql2);
			while ($db->next_record()) {
				$url = trim($db->f("domain_url"));
				$key = strtolower($url);
				if ($key !== '' && !isset($seen[$key])) {
					$seen[$key] = true;
					$out[] = array(
						"domain_url" => $url,
						"client_id" => (int) $db->f("client_id"),
						"client_name" => (string) $db->f("client_name")
					);
				}
			}
		}
		echo json_encode($out);
	}

	function ajax_get_team_hours() {
		global $db;
		while (ob_get_level()) ob_end_clean();
		header('Content-Type: application/json');

		// Allow either session auth or valid API key when not authenticated
		$api_key = isset($_GET['api_key']) ? $_GET['api_key'] : (isset($_POST['api_key']) ? $_POST['api_key'] : '');
		if (empty($api_key) && function_exists('getallheaders')) {
			$headers = getallheaders();
			foreach (array('X-API-Key', 'X-Api-Key') as $h) {
				if (!empty($headers[$h])) { $api_key = $headers[$h]; break; }
			}
		}
		if (empty($api_key) && !empty($_SERVER['HTTP_X_API_KEY'])) {
			$api_key = $_SERVER['HTTP_X_API_KEY'];
		}
		$valid_api = (defined('API_KEY_TEAM_HOURS') && API_KEY_TEAM_HOURS !== '' && $api_key === API_KEY_TEAM_HOURS);
		$has_session = isset($_SESSION['privilege_id']);
		if (!$has_session && !$valid_api) {
			http_response_code(401);
			echo json_encode(array('error' => 'Unauthorized', 'message' => 'Authentication required. Provide a session or valid api_key (query param or X-API-Key header).'));
			exit;
		}
		if ($has_session) {
			CheckSecurity(1);
		}

		$year = GetParam("year");
		$month = GetParam("month");
		$team = strtolower(trim(GetParam("team")));
		if (!$year || !$month) {
			echo json_encode(array('national_holidays' => 0, 'users' => array()));
			exit;
		}
		$year = preg_replace('/[^0-9]/', '', $year);
		$month = preg_replace('/[^0-9]/', '', $month);
		if (strlen($month) === 1) $month = '0' . $month;

		switch ($team) {
			case 'all':    $sqlteam = ''; break;
			case 'viart':  $sqlteam = ' AND u.is_viart=1 '; break;
			case 'yoonoo': $sqlteam = ' AND u.is_viart=0 '; break;
			default:       $sqlteam = ' AND u.is_viart=1 ';
		}

		$month_start = $year . '-' . $month . '-01';
		$month_end = date('Y-m-t', strtotime($month_start));

		// National holidays count (weekdays only)
		$national_holidays = 0;
		$sql = "SELECT holiday_date FROM national_holidays WHERE DATE_FORMAT(holiday_date, '%Y')=" . ToSQL($year, "text", false, true) . " AND DATE_FORMAT(holiday_date, '%m')=" . ToSQL($month, "text", false, true);
		$db->query($sql);
		while ($db->next_record()) {
			$hd = $db->f("holiday_date");
			$wd = date('w', strtotime($hd));
			if ($wd >= 1 && $wd <= 5) $national_holidays++;
		}

		// Users with total_hours and paid_holidays - fetch user IDs first to avoid nested queries overwriting result
		$users = array();
		$user_ids = array();
		$sql = "SELECT u.user_id FROM users u WHERE u.is_deleted IS NULL " . $sqlteam . " ORDER BY u.user_id";
		$db->query($sql);
		while ($db->next_record()) {
			$user_ids[] = (int) $db->f("user_id");
		}
		foreach ($user_ids as $uid) {
			$total_hours = 0;
			$sqlh = "SELECT SUM(tr.spent_hours) AS h FROM time_report tr WHERE tr.user_id=" . $uid . " AND tr.started_date>=" . ToSQL($month_start . " 00:00:00", "text", false, true) . " AND tr.started_date<=" . ToSQL($month_end . " 23:59:59", "text", false, true);
			$db->query($sqlh);
			if ($db->next_record() && $db->f("h") !== null) {
				$total_hours = round(floatval($db->f("h")), 1);
			}

			$paid_holidays = 0;
			$sqld = "SELECT start_date, end_date, total_days, is_paid, reason_id FROM days_off WHERE user_id=" . $uid . " AND start_date<=" . ToSQL($month_end, "text", false, true) . " AND end_date>=" . ToSQL($month_start, "text", false, true);
			$db->query($sqld);
			while ($db->next_record()) {
				$reason = (int) $db->f("reason_id");
				$is_paid = (int) $db->f("is_paid");
				if ($reason !== 1 && $reason !== 2 && $is_paid !== 1) continue;
				$sd = $db->f("start_date");
				$ed = $db->f("end_date");
				$sd_ts = strtotime($sd);
				$ed_ts = strtotime($ed);
				$ms_ts = strtotime($month_start);
				$me_ts = strtotime($month_end);
				$overlap_start = max($sd_ts, $ms_ts);
				$overlap_end = min($ed_ts, $me_ts);
				$overlap_days = max(0, floor(($overlap_end - $overlap_start) / 86400) + 1);
				$paid_holidays += $overlap_days;
			}

			$users[] = array(
				'user_id' => (string) $uid,
				'total_hours' => $total_hours,
				'paid_holidays' => $paid_holidays
			);
		}

		echo json_encode(array(
			'national_holidays' => $national_holidays,
			'users' => $users
		));
		exit;
	}

	function get_domains_list($domain) {
		global $db, $t;
		
		$domain = strtolower(trim(rtrim($domain)));
		if (strpos($domain, "www.") === 0) {
			$domain = substr($domain, 4);
		}
		$t->set_var("search_domain", $domain);
		
		$sql  = " SELECT d.domain_id, d.domain_url, c.client_id, c.client_name FROM tasks_domains AS d ";
		$sql .= " LEFT JOIN clients AS c ON c.client_id=d.client_id ";
		$sql .= " WHERE d.domain_url LIKE '" . ToSQL($domain, "text", false, false) . "%'";
		$sql .= " OR d.domain_url LIKE 'www." . ToSQL($domain, "text", false, false) . "%'";
		$sql .= " ORDER BY d.domain_url";
		$db->query($sql);
		if ($db->next_record()) {
			$t->set_var("no_domain", "");
			do {
				$t->set_var("domain_id",   $db->f("domain_id"));
				$t->set_var("domain_url",  $db->f("domain_url"));
				$t->set_var("client_id",   $db->f("client_id"));
				$t->set_var("client_name", $db->f("client_name"));
				$t->parse("domain");
			} while ($db->next_record());
		} else {
			$t->set_var("domain", "");
			$t->parse("no_domain");
		}
		$t->parse("domains");
		echo $t->get_var("domains");		
	}
	
	function get_clients_list($client, $task_id) {
		global $db, $t;
		
		$client = strtolower(trim(rtrim($client)));
		$t->set_var("search_client", $client);
		
		$sql  = " SELECT client_id, client_company FROM clients";
		$sql .= " WHERE client_name LIKE '%" . ToSQL($client, "text", false, false) . "%'";
		$sql .= " OR client_company LIKE '%" . ToSQL($client, "text", false, false) . "%'";
		$sql .= " OR client_email LIKE '%"   . ToSQL($client, "text", false, false) . "%'";
		if (is_numeric($client)) {
			$sql .= " OR client_id="    . ToSQL($client, "integer");
			$sql .= " OR sayu_user_id=" . ToSQL($client, "integer"); 
		}
		$sql .= " ORDER BY client_company";
		$db->query($sql);
		if ($db->next_record()) {
			$t->set_var("no_client", "");
			do {
				$client_id  = $db->f("client_id");
				$client_name = $db->f("client_company");
				if (strlen($client_name)>0)
				{
					$t->set_var("client_id",  $client_id);
					$t->set_var("task_id",  $task_id);
					$t->set_var("client_name", $client_name);
					$t->parse("client");
				}
			} while ($db->next_record());
		} else {
			$t->set_var("client", "");
			$t->parse("no_client");
		}
		$t->parse("clients");
		echo $t->get_var("clients");		
	}
	
	function ajax_search_clients() {
		global $db;
		
		header('Content-Type: application/json');
		
		$q = trim(GetParam("q"));
		$type = (int) GetParam("type");
		$limit = (int) GetParam("limit");
		if ($limit <= 0 || $limit > 100) $limit = 50;
		
		$type_filter = "";
		if ($type == 1) $type_filter = " AND c.is_viart_hosted=1";
		elseif ($type == 2) $type_filter = " AND c.client_type=2 AND c.is_active=1";
		
		$where = "1=1";
		if (strlen($q) > 0) {
			$esc = ToSQL("%" . $q . "%", "text", false, true);
			$text_match = "(c.client_name LIKE " . $esc .
				" OR c.client_company LIKE " . $esc .
				" OR c.client_email LIKE " . $esc;
			if (is_numeric($q)) {
				$text_match .= " OR c.client_id=" . ToSQL($q, "integer");
				$text_match .= " OR c.sayu_user_id=" . ToSQL($q, "integer");
			}
			$text_match .= ")";
			$site_match = "cs.web_address LIKE " . $esc;
			// Match by name/company/email (with type filter) OR by site/domain (return client regardless of type)
			$where .= " AND ((" . $text_match . $type_filter . ") OR (" . $site_match . "))";
		} else {
			$where .= $type_filter;
		}
		
		$sql = "SELECT c.client_id, c.sayu_user_id, c.client_name, c.client_company, c.client_email, " .
			" DATE_FORMAT(c.date_added, '%d %b %Y') as date_added, c.is_active, " .
			" REPLACE(GROUP_CONCAT(DISTINCT cs.web_address SEPARATOR ', '), 'http://', '') as sites " .
			" FROM clients c " .
			" LEFT JOIN clients_sites cs ON c.client_id=cs.client_id " .
			" WHERE " . $where . " GROUP BY c.client_id ORDER BY c.client_name LIMIT " . $limit;
		
		$db->query($sql);
		$clients = array();
		while ($db->next_record()) {
			$sites = $db->f("sites");
			if ($sites) {
				$sites = preg_replace('/https?:\/\//', '', $sites);
			} else {
				$sites = "";
			}
			$clients[] = array(
				"client_id" => $db->f("client_id"),
				"sayu_user_id" => $db->f("sayu_user_id"),
				"client_name" => $db->f("client_name"),
				"client_company" => $db->f("client_company"),
				"client_email" => $db->f("client_email"),
				"date_added" => $db->f("date_added"),
				"is_active" => (int) $db->f("is_active"),
				"sites" => $sites,
				"tags" => ""
			);
		}
		
		echo json_encode(array("success" => true, "clients" => $clients));
	}
	
	function task_update_client($client_id,$task_id)
	{
		global $db;
		
		$sql = "UPDATE tasks SET client_id=".ToSQL($client_id, "integer");
		$sql .= " WHERE task_id=".ToSQL($task_id, "integer");
		$db->query($sql);
	}
		
	function ajax_get_task_messages($task_id, $offset, $limit) {
		global $db;
		
		while (ob_get_level()) ob_end_clean();
		header('Content-Type: application/json');
		
		if (!$task_id) {
			echo json_encode(array("success" => false, "error" => "No task ID"));
			exit;
		}
		if ($limit <= 0 || $limit > 50) $limit = 20;
		if ($offset < 0) $offset = 0;
		
		// Get total count
		$sql = "SELECT COUNT(*) AS cnt FROM messages WHERE identity_type = 'task' AND identity_id = " . ToSQL($task_id, "integer");
		$db->query($sql);
		$total = $db->next_record() ? (int) $db->f("cnt") : 0;
		
		// Fetch messages
		$messages = array();
		$sql = "SELECT m.message_id, m.message_date, m.message, m.status_id, m.responsible_user_id,
				m.user_id, u.first_name, u.last_name,
				ru.first_name as r_first_name, ru.last_name as r_last_name,
				lts.status_desc, lts.status_caption,
				DATE_FORMAT(m.message_date, '%a %D %b %Y, %H:%i') as formatted_date
				FROM messages m
				LEFT JOIN users u ON m.user_id = u.user_id
				LEFT JOIN users ru ON m.responsible_user_id = ru.user_id
				LEFT JOIN lookup_tasks_statuses lts ON m.status_id = lts.status_id
				WHERE m.identity_type = 'task' AND m.identity_id = " . ToSQL($task_id, "integer") . "
				ORDER BY m.message_date DESC
				LIMIT " . $limit . " OFFSET " . $offset;
		$db->query($sql);
		while ($db->next_record()) {
			$msg_text = $db->f("message");
			if (!mb_check_encoding($msg_text, 'UTF-8')) {
				$msg_text = mb_convert_encoding($msg_text, 'UTF-8', 'Windows-1252');
			}
			$messages[] = array(
				"message_id" => $db->f("message_id"),
				"message_date" => $db->f("message_date"),
				"formatted_date" => $db->f("formatted_date"),
				"message" => $msg_text,
				"first_name" => $db->f("first_name"),
				"last_name" => $db->f("last_name"),
				"user_id" => $db->f("user_id"),
				"r_first_name" => $db->f("r_first_name"),
				"r_last_name" => $db->f("r_last_name"),
				"status_desc" => $db->f("status_desc"),
				"status_caption" => $db->f("status_caption"),
				"responsible_user_id" => $db->f("responsible_user_id")
			);
		}
		
		// Fetch attachments for these messages
		$message_attachments = array();
		if (!empty($messages)) {
			$msg_ids = array();
			foreach ($messages as $m) $msg_ids[] = (int) $m["message_id"];
			$sql = "SELECT attachment_id, identity_id, file_name 
					FROM attachments 
					WHERE identity_type = 'message' AND identity_id IN (" . implode(",", $msg_ids) . ")";
			$db->query($sql);
			while ($db->next_record()) {
				$mid = $db->f("identity_id");
				if (!isset($message_attachments[$mid])) $message_attachments[$mid] = array();
				$message_attachments[$mid][] = array(
					"attachment_id" => $db->f("attachment_id"),
					"file_name" => $db->f("file_name")
				);
			}
		}
		
		echo json_encode(array(
			"success" => true,
			"total" => $total,
			"offset" => $offset,
			"limit" => $limit,
			"messages" => $messages,
			"message_attachments" => $message_attachments
		));
		exit;
	}

	function ajax_get_subprojects($project_id) {
		global $db;
		
		while (ob_get_level()) ob_end_clean();
		header('Content-Type: application/json');
		
		$results = array();
		if ($project_id) {
			$sql = "SELECT project_id, project_title FROM projects WHERE parent_project_id = " . ToSQL($project_id, "integer") . " AND is_closed = 0 ORDER BY project_title";
			$db->query($sql);
			while ($db->next_record()) {
				$title = $db->f("project_title");
			if (!mb_check_encoding($title, 'UTF-8')) {
				$title = mb_convert_encoding($title, 'UTF-8', 'Windows-1252');
			}
				$results[] = array("id" => $db->f("project_id"), "title" => $title);
			}
		}
		echo json_encode($results);
		exit;
	}

	function ajax_upload_temp_attachment() {
		while (ob_get_level()) ob_end_clean();
		header('Content-Type: application/json');

		if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
			echo json_encode(array('success' => false, 'error' => 'No file uploaded or upload error'));
			exit;
		}

		$hash = isset($_POST['hash']) ? preg_replace('/[^a-f0-9]/i', '', $_POST['hash']) : '';
		if (!$hash) {
			echo json_encode(array('success' => false, 'error' => 'Missing hash'));
			exit;
		}

		$temp_path = 'temp_attachments/';
		if (!is_dir($temp_path)) {
			mkdir($temp_path, 0755, true);
		}

		$original_name = basename($_FILES['file']['name']);
		// Sanitise filename: keep alphanumeric, dots, dashes, underscores
		$safe_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $original_name);
		if (!$safe_name) $safe_name = 'file_' . time();
		// Avoid duplicate names for pasted images (browser often sends "image.png" for each)
		if (preg_match('/^image\.(png|jpe?g|gif|webp|bmp)$/i', $safe_name)) {
			$ext = strtolower(pathinfo($safe_name, PATHINFO_EXTENSION));
			$safe_name = 'image_' . date('Ymd_His') . '_' . substr(uniqid(), -4) . '.' . $ext;
		} elseif (preg_match('/^screenshot\.(png|jpe?g|gif|webp)$/i', $safe_name)) {
			$ext = strtolower(pathinfo($safe_name, PATHINFO_EXTENSION));
			$safe_name = 'screenshot_' . date('Ymd_His') . '_' . substr(uniqid(), -4) . '.' . $ext;
		} elseif (preg_match('/^paste\.(png|jpe?g|gif|webp)$/i', $safe_name)) {
			$ext = strtolower(pathinfo($safe_name, PATHINFO_EXTENSION));
			$safe_name = 'paste_' . date('Ymd_His') . '_' . substr(uniqid(), -4) . '.' . $ext;
		}

		$session_id = session_id();
		$dest_name = $session_id . $hash . $safe_name;
		$dest_path = $temp_path . $dest_name;

		if (move_uploaded_file($_FILES['file']['tmp_name'], $dest_path)) {
			echo json_encode(array(
				'success' => true,
				'filename' => $dest_name,
				'safe_name' => $safe_name,
				'original_name' => $original_name
			));
		} else {
			echo json_encode(array('success' => false, 'error' => 'Failed to save file'));
		}
		exit;
	}

	function ajax_get_project_users($project_id) {
		global $db;

		while (ob_get_level()) ob_end_clean();
		header('Content-Type: application/json');

		$results = array();
		if ($project_id) {
			// Get users assigned to this project or its parent project
			$sql = "SELECT DISTINCT u.user_id, CONCAT(u.first_name, ' ', u.last_name) AS user_name
					FROM users u
					INNER JOIN users_projects up ON u.user_id = up.user_id
					WHERE up.project_id = " . ToSQL($project_id, "integer") . "
					   OR up.project_id = (SELECT parent_project_id FROM projects WHERE project_id = " . ToSQL($project_id, "integer") . ")
					ORDER BY u.first_name, u.last_name";
			$db->query($sql);
			while ($db->next_record()) {
				$name = $db->f("user_name");
				if (!mb_check_encoding($name, 'UTF-8')) {
					$name = mb_convert_encoding($name, 'UTF-8', 'Windows-1252');
				}
				$results[] = array("id" => (int)$db->f("user_id"), "name" => $name);
			}
		}
		echo json_encode($results);
		exit;
	}

	function ajax_add_task_message() {
		global $db, $temp_path, $path;
		
		// Set attachment paths (required by attach_files() in tasks_functions.php)
		$temp_path = 'temp_attachments/';
		$path = 'attachments/message/';
		
		// Clean ALL output buffers
		while (ob_get_level()) ob_end_clean();
		
		// Register shutdown function to catch fatal errors
		register_shutdown_function(function() {
			$error = error_get_last();
			if ($error && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR))) {
				while (ob_get_level()) ob_end_clean();
				header('Content-Type: application/json');
				echo json_encode(array(
					"success" => false,
					"error" => "Fatal: " . $error['message'] . " in " . $error['file'] . ":" . $error['line']
				));
			}
		});
		
		header('Content-Type: application/json');
		
		$task_id = (int) GetParam("task_id");
		$message_text = GetParam("message");
		$responsible_user_id = (int) GetParam("responsible_user_id");
		$task_status_id = (int) GetParam("task_status_id");
		$task_completion = GetParam("task_completion");
		$hash = GetParam("hash");
		$session_user_id = GetSessionParam("UserID");
		
		if (!$task_id) {
			echo json_encode(array("success" => false, "error" => "No task ID provided"));
			exit;
		}
		
		// Buffer output from add_task_message to prevent stray output corrupting JSON
		ob_start();
		try {
			add_task_message(
				$task_id,
				$message_text,
				$session_user_id,
				$responsible_user_id,
				$task_status_id,
				0,
				$task_completion,
				"",
				$hash,
				1, 1, false, false, false
			);
		} catch (Exception $e) {
			// catch any throwable
		}
		$stray_output = ob_get_clean();
		
		// Fetch updated task info
		$task_data = null;
		$sql = "SELECT t.task_status_id, t.completion, t.responsible_user_id, 
				lts.status_desc, CONCAT(u.first_name, ' ', u.last_name) AS responsible_name
				FROM tasks t 
				LEFT JOIN lookup_tasks_statuses lts ON t.task_status_id = lts.status_id
				LEFT JOIN users u ON t.responsible_user_id = u.user_id
				WHERE t.task_id = " . ToSQL($task_id, "integer");
		$db->query($sql);
		if ($db->next_record()) {
			$task_data = array(
				"status_desc" => $db->f("status_desc"),
				"responsible_name" => $db->f("responsible_name"),
				"completion" => $db->f("completion")
			);
		}
		
		// Count total messages
		$sql = "SELECT COUNT(*) AS cnt FROM messages WHERE identity_type = 'task' AND identity_id = " . ToSQL($task_id, "integer");
		$db->query($sql);
		$total_messages = $db->next_record() ? (int) $db->f("cnt") : 0;
		
		// Fetch top 20 messages for the task
		$messages = array();
		$sql = "SELECT m.message_id, m.message_date, m.message, m.status_id, m.responsible_user_id,
				m.user_id, u.first_name, u.last_name,
				ru.first_name as r_first_name, ru.last_name as r_last_name,
				lts.status_desc, lts.status_caption,
				DATE_FORMAT(m.message_date, '%a %D %b %Y, %H:%i') as formatted_date
				FROM messages m
				LEFT JOIN users u ON m.user_id = u.user_id
				LEFT JOIN users ru ON m.responsible_user_id = ru.user_id
				LEFT JOIN lookup_tasks_statuses lts ON m.status_id = lts.status_id
				WHERE m.identity_type = 'task' AND m.identity_id = " . ToSQL($task_id, "integer") . "
				ORDER BY m.message_date DESC
				LIMIT 20";
		$db->query($sql);
		while ($db->next_record()) {
			$msg_text = $db->f("message");
			if (!mb_check_encoding($msg_text, 'UTF-8')) {
				$msg_text = mb_convert_encoding($msg_text, 'UTF-8', 'Windows-1252');
			}
			$messages[] = array(
				"message_id" => $db->f("message_id"),
				"message_date" => $db->f("message_date"),
				"formatted_date" => $db->f("formatted_date"),
				"message" => $msg_text,
				"first_name" => $db->f("first_name"),
				"last_name" => $db->f("last_name"),
				"user_id" => $db->f("user_id"),
				"r_first_name" => $db->f("r_first_name"),
				"r_last_name" => $db->f("r_last_name"),
				"status_desc" => $db->f("status_desc"),
				"status_caption" => $db->f("status_caption"),
				"responsible_user_id" => $db->f("responsible_user_id")
			);
		}
		
		// Fetch attachments for messages
		$message_attachments = array();
		if (!empty($messages)) {
			$msg_ids = array();
			foreach ($messages as $m) {
				$msg_ids[] = (int) $m["message_id"];
			}
			$sql = "SELECT attachment_id, identity_id, file_name 
					FROM attachments 
					WHERE identity_type = 'message' AND identity_id IN (" . implode(",", $msg_ids) . ")";
			$db->query($sql);
			while ($db->next_record()) {
				$mid = $db->f("identity_id");
				if (!isset($message_attachments[$mid])) {
					$message_attachments[$mid] = array();
				}
				$message_attachments[$mid][] = array(
					"attachment_id" => $db->f("attachment_id"),
					"file_name" => $db->f("file_name")
				);
			}
		}
		
		$response = array(
			"success" => true,
			"message_count" => $total_messages,
			"task" => $task_data,
			"messages" => $messages,
			"message_attachments" => $message_attachments
		);
		
		$json = json_encode($response);
		if ($json === false) {
			// Fallback: convert non-UTF8 strings in all messages (may contain Windows-1252 data)
			foreach ($response['messages'] as &$m) {
				if (!mb_check_encoding($m['message'], 'UTF-8')) {
					$m['message'] = mb_convert_encoding($m['message'], 'UTF-8', 'Windows-1252');
				}
				if (!mb_check_encoding($m['user_name'], 'UTF-8')) {
					$m['user_name'] = mb_convert_encoding($m['user_name'], 'UTF-8', 'Windows-1252');
				}
			}
			unset($m);
			$json = json_encode($response);
		}
		if ($json === false) {
			// Last resort: return success without messages
			echo json_encode(array("success" => true, "message_count" => count($messages), "task" => $task_data, "messages" => array(), "message_attachments" => array(), "encoding_error" => true));
		} else {
			echo $json;
		}
		exit;
	}

	function get_tasks_list($responsible_user_id) {
		global $db, $t;
			
		$statuses_classes = array("","InProgress","OnHold","Rejected","Done","Question","Answer","New","Waiting","Reassigned","Bug","Deadline",
		"BugResolved", "Documented", "ReadyToDocument", "New", "New", "New", "New", "New", "New", "New");

		$operation = GetParam("operation");
		$move_task_id    = 0;
		$move_task_index = -1;
		if ($operation) {
			if ($operation == "move") {
				$move_task_id = (int) GetParam("task_id");
				$move_dir     = GetParam("dir");
			}
		}
		
		// select tasks list
		$i = 0; $prev_priority_id = 0;
		$tasks = array();				
		$sql  = " SELECT t.*, ";
		$sql .= " IF(t.task_status_id=2, 1, 0) AS sorter, ";
		$sql .= " IF (TO_DAYS(t.planed_date) < TO_DAYS(now()) AND t.task_status_id!=4 AND t.task_type_id!=3,1,0 ) AS ifdeadlined, ";
		$sql .= " IF (TO_DAYS(t.planed_date) = TO_DAYS(now()) AND t.task_status_id!=4 AND t.task_type_id!=3,1,0 ) AS iftoday, ";  	
		$sql .= " p.project_title FROM (tasks t ";
		$sql .= " LEFT JOIN projects p ON p.project_id = t.project_id) ";
		$sql .= " WHERE t.responsible_user_id=" . ToSQL($responsible_user_id, "integer");
		$sql .= " AND t.is_closed=0 AND t.is_wish=0 AND t.task_type_id<>3 ";			
		$sql .= " ORDER BY sorter, t.priority_id";		
		$db->query($sql);
		while($db->next_record()) {
			$task_id = $db->f("task_id");
			if ($move_task_id == $task_id) {
				$move_task_index = $i;
			}
			$priority_id = $db->f("priority_id");
			if ($priority_id <= $prev_priority_id) {
				$priority_id = $prev_priority_id+1;
			}
			$prev_priority_id = $priority_id;
			
			$task_title = ToHTML($db->f("task_title"));
			$status_id  = $db->f("task_status_id");
			if ($db->f("iftoday")) {
				if ($status_id !=1) $style = "Today";
			} elseif ($db->f("ifdeadlined")) {
				$task_title = "<font color=\"red\"><b>" . $task_title . "</b></font>";
				if ($status_id !=1) $style = "Deadline";
			} else {
				$style     = $statuses_classes[$status_id];
				if ($i%2) {	$style .=2;	}
			}
			
			
			$tasks[$i] = array(
				"task_id"       => $task_id,
				"task_title"    => $task_title,
				"priority_id"   => $priority_id,
				"project_title" => ToHTML($db->f("project_title")),
				"style"         => $style
			);
			$i++;
		}
		
		if ($operation == "move" && $move_task_index >= 0) {
			$move_task_index_2 = (($move_dir < 0) ? 1 : -1) * 1 + $move_task_index;			
			if (isset($tasks[$move_task_index]) && isset($tasks[$move_task_index_2])) {				
				$tasks[$move_task_index]["style"] = $tasks[$move_task_index]["style"] . " DataRow3";								
				$tmp                              = $tasks[$move_task_index_2];
				$tasks[$move_task_index_2]        = $tasks[$move_task_index];
				$tasks[$move_task_index]          = $tmp;				
				$tasks[$move_task_index]["priority_id"]   = $tasks[$move_task_index_2]["priority_id"];
				$tasks[$move_task_index_2]["priority_id"] = $tmp["priority_id"];
				
				$tasks[$move_task_index]["style"] = $tasks[$move_task_index]["style"] . " DataRow3";
				
				foreach ($tasks AS $task) {
					$sql  = " UPDATE tasks SET priority_id=" . $task["priority_id"];
					$sql .= " WHERE task_id=" . $task["task_id"];	
					$db->query($sql);	
				}
			}
		}
		
		$i = 0;
		if ($tasks) {
			$t->set_var("no_task", "");
			$prev_project_title = "";
			foreach ($tasks AS $task) {
				$task_id       = $task["task_id"];
				$task_title    = $task["task_title"];
				$priority_id   = $task["priority_id"];
				$project_title = $task["project_title"];
				$style         = $task["style"];
				$t->set_var("task_id",       $task_id);
				$t->set_var("task_title",    $task_title);			
				$t->set_var("priority_id",   $priority_id);
				if ($project_title != $prev_project_title) {
					$prev_project_title = $project_title;
					$t->set_var("project_title", $project_title);
				} else {
					$t->set_var("project_title_cutted", "");
				}
				$t->set_var("task_class", $style);
				$t->parse("task");
				$i++;
			};
		} else {
			$t->set_var("task", "");
			$t->parse("no_task");
		}
		
		$t->parse("tasks");
		echo $t->get_var("tasks");
	}

	function ajax_reassign_task() {
		global $db;
		while (ob_get_level()) ob_end_clean();
		header('Content-Type: application/json');

		$task_id = (int) GetParam("task_id");
		$new_user_id = (int) GetParam("new_user_id");
		$session_user_id = GetSessionParam("UserID");

		if (!$task_id || !$new_user_id || !$session_user_id) {
			echo json_encode(array("success" => false, "error" => "Missing parameters"));
			exit;
		}

		// Get current task info
		$db->query("SELECT task_title, task_status_id, responsible_user_id FROM tasks WHERE task_id = " . ToSQL($task_id, "integer"));
		if (!$db->next_record()) {
			echo json_encode(array("success" => false, "error" => "Task not found"));
			exit;
		}
		$old_user_id = (int) $db->f("responsible_user_id");
		$task_status_id = (int) $db->f("task_status_id");

		// Get old and new user names
		$old_name = '';
		$new_name = '';
		$db->query("SELECT user_id, CONCAT(first_name, ' ', last_name) AS name FROM users WHERE user_id IN (" . ToSQL($old_user_id, "integer") . ", " . ToSQL($new_user_id, "integer") . ")");
		while ($db->next_record()) {
			if ((int)$db->f("user_id") === $old_user_id) $old_name = $db->f("name");
			if ((int)$db->f("user_id") === $new_user_id) $new_name = $db->f("name");
		}

		// Update
		$db->query("UPDATE tasks SET responsible_user_id = " . ToSQL($new_user_id, "integer") . ", modified_date = NOW() WHERE task_id = " . ToSQL($task_id, "integer"));

		// Log message
		$message = "Reassigned from \"" . $old_name . "\" to \"" . $new_name . "\" (via dashboard)";
		$db->query("INSERT INTO messages (message_date, user_id, identity_id, identity_type, status_id, responsible_user_id, message) VALUES (NOW(), " . ToSQL($session_user_id, "integer") . ", " . ToSQL($task_id, "integer") . ", 'task', " . ToSQL($task_status_id, "integer") . ", " . ToSQL($new_user_id, "integer") . ", " . ToSQL($message, "text") . ")");

		echo json_encode(array("success" => true, "task_id" => $task_id, "new_user_id" => $new_user_id, "new_user_name" => $new_name));
		exit;
	}

	function ajax_kanban_move_task() {
		global $db;
		header('Content-Type: application/json');

		$task_id = (int) GetParam("task_id");
		$new_status_id = (int) GetParam("new_status_id");
		$session_user_id = GetSessionParam("UserID");

		if (!$task_id || !$new_status_id || !$session_user_id) {
			echo json_encode(array("success" => false, "error" => "Missing parameters"));
			exit;
		}

		// Get current task info
		$db->query("SELECT task_title, task_status_id, responsible_user_id FROM tasks WHERE task_id = " . ToSQL($task_id, "integer"));
		if (!$db->next_record()) {
			echo json_encode(array("success" => false, "error" => "Task not found"));
			exit;
		}
		$task_title = $db->f("task_title");
		$old_status_id = (int) $db->f("task_status_id");
		$responsible_user_id = $db->f("responsible_user_id");

		if ($old_status_id === $new_status_id) {
			echo json_encode(array("success" => true, "changed" => false));
			exit;
		}

		// Get old and new status names
		$old_status_name = '';
		$new_status_name = '';
		$db->query("SELECT status_id, status_desc FROM lookup_tasks_statuses WHERE status_id IN (" . ToSQL($old_status_id, "integer") . ", " . ToSQL($new_status_id, "integer") . ")");
		while ($db->next_record()) {
			if ((int)$db->f("status_id") === $old_status_id) $old_status_name = $db->f("status_desc");
			if ((int)$db->f("status_id") === $new_status_id) $new_status_name = $db->f("status_desc");
		}

		// Update task status
		$db->query("UPDATE tasks SET task_status_id = " . ToSQL($new_status_id, "integer") . ", modified_date = NOW() WHERE task_id = " . ToSQL($task_id, "integer"));

		// Add a message to the task's messages log
		$custom_message = GetParam("message");
		if ($custom_message && trim($custom_message) !== '') {
			// User provided a custom message along with the status change
			$message = trim($custom_message) . "\n\n[Status changed from \"" . $old_status_name . "\" to \"" . $new_status_name . "\"]";
		} else {
			$message = "Status changed from \"" . $old_status_name . "\" to \"" . $new_status_name . "\" (via Kanban board)";
		}
		$db->query("INSERT INTO messages (message_date, user_id, identity_id, identity_type, status_id, responsible_user_id, message) VALUES (NOW(), " . ToSQL($session_user_id, "integer") . ", " . ToSQL($task_id, "integer") . ", 'task', " . ToSQL($new_status_id, "integer") . ", " . ToSQL($responsible_user_id, "integer") . ", " . ToSQL($message, "text") . ")");

		echo json_encode(array(
			"success" => true,
			"changed" => true,
			"task_id" => $task_id,
			"new_status_id" => $new_status_id,
			"new_status_name" => $new_status_name,
			"old_status_name" => $old_status_name
		));
		exit;
	}

	function ajax_add_project_member() {
		global $db;
		header('Content-Type: application/json');
		$project_id = (int) GetParam("project_id");
		$user_id = (int) GetParam("user_id");
		if (!$project_id || !$user_id) { echo json_encode(array("success" => false, "error" => "Missing parameters")); exit; }

		// Check if already a member
		$db->query("SELECT COUNT(*) AS cnt FROM users_projects WHERE project_id = " . ToSQL($project_id, "integer") . " AND user_id = " . ToSQL($user_id, "integer"));
		if ($db->next_record() && (int)$db->f("cnt") > 0) {
			echo json_encode(array("success" => false, "error" => "User is already a member"));
			exit;
		}

		$db->query("INSERT INTO users_projects (project_id, user_id) VALUES (" . ToSQL($project_id, "integer") . ", " . ToSQL($user_id, "integer") . ")");

		// Get user name
		$db->query("SELECT CONCAT(first_name, ' ', last_name) AS full_name FROM users WHERE user_id = " . ToSQL($user_id, "integer"));
		$name = $db->next_record() ? $db->f("full_name") : '';

		echo json_encode(array("success" => true, "user_id" => $user_id, "user_name" => $name));
		exit;
	}

	function ajax_remove_project_member() {
		global $db;
		header('Content-Type: application/json');
		$project_id = (int) GetParam("project_id");
		$user_id = (int) GetParam("user_id");
		if (!$project_id || !$user_id) { echo json_encode(array("success" => false, "error" => "Missing parameters")); exit; }

		$db->query("DELETE FROM users_projects WHERE project_id = " . ToSQL($project_id, "integer") . " AND user_id = " . ToSQL($user_id, "integer"));

		echo json_encode(array("success" => true, "user_id" => $user_id));
		exit;
	}

	function ajax_close_task() {
		global $db;
		header('Content-Type: application/json');

		$task_ids_raw = GetParam("task_ids");
		if (!$task_ids_raw) { echo json_encode(array("success" => false, "error" => "No task IDs")); exit; }

		$ids = array_filter(array_map('intval', explode(',', $task_ids_raw)));
		if (empty($ids)) { echo json_encode(array("success" => false, "error" => "Invalid task IDs")); exit; }

		$closed = array();
		foreach ($ids as $tid) {
			if ($tid > 0) {
				close_task($tid, "");
				$closed[] = $tid;
			}
		}

		echo json_encode(array("success" => true, "closed" => $closed, "count" => count($closed)));
		exit;
	}

	function ajax_quick_add_task() {
		global $db;
		header('Content-Type: application/json');

		$session_user_id = GetSessionParam("UserID");
		if (!$session_user_id) {
			echo json_encode(array("success" => false, "error" => "Not logged in"));
			exit;
		}

		$task_title = trim(GetParam("task_title"));
		$project_id = (int) GetParam("project_id");
		$status_id = (int) GetParam("status_id");

		if (!$task_title) {
			echo json_encode(array("success" => false, "error" => "Task title is required"));
			exit;
		}
		if (!$project_id) {
			echo json_encode(array("success" => false, "error" => "Project is required"));
			exit;
		}
		if (!$status_id) $status_id = 7; // default "not started"

		// Use the existing add_task function
		$task_id = add_task(
			$session_user_id,  // responsible_user_id
			2,                 // priority_id (normal)
			$status_id,        // task_status_id
			$project_id,       // project_id
			0,                 // client_id
			$task_title,       // task_title
			'',                // task_desc
			'',                // planed_date
			$session_user_id,  // created_user_id
			false,             // estimated_hours
			1,                 // task_type_id (development)
			false,             // attachment_hash
			false              // is_wish
		);

		if (!$task_id) {
			// add_task doesn't return, fetch last insert
			$db->query("SELECT LAST_INSERT_ID() AS lid");
			$task_id = $db->next_record() ? (int)$db->f("lid") : 0;
		}

		// Ensure no deadline is set for quick-added tasks
		if ($task_id) {
			$db->query("UPDATE tasks SET planed_date = NULL WHERE task_id = " . ToSQL($task_id, "integer") . " AND (planed_date IS NOT NULL AND planed_date != '0000-00-00')");
		}

		// Fetch the created task details
		$db->query("SELECT t.task_id, t.task_title, t.task_status_id, t.completion, t.project_id,
			p.project_title, ls.status_desc,
			CONCAT(u.first_name, ' ', u.last_name) AS responsible_name
			FROM tasks t
			INNER JOIN projects p ON p.project_id = t.project_id
			INNER JOIN lookup_tasks_statuses ls ON ls.status_id = t.task_status_id
			LEFT JOIN users u ON u.user_id = t.responsible_user_id
			WHERE t.task_id = " . ToSQL($task_id, "integer"));

		if ($db->next_record()) {
			$name = $db->f("responsible_name");
			$parts = explode(' ', trim($name));
			$initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));

			echo json_encode(array(
				"success" => true,
				"task" => array(
					"task_id" => (int)$db->f("task_id"),
					"task_title" => $db->f("task_title"),
					"project_id" => (int)$db->f("project_id"),
					"project_title" => $db->f("project_title"),
					"status_id" => (int)$db->f("task_status_id"),
					"status_desc" => $db->f("status_desc"),
					"completion" => (int)$db->f("completion"),
					"responsible_name" => $name,
					"initials" => $initials
				)
			));
		} else {
			echo json_encode(array("success" => true, "task" => array("task_id" => $task_id)));
		}
		exit;
	}
?>