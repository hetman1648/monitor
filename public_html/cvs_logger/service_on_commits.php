<?php
	ini_set("display_errors", 0);

	
	include_once(dirname(__FILE__) . "/../db_mysql.inc");
	include_once(dirname(__FILE__) . "/../includes/db_connect.php");
	include_once(dirname(__FILE__) . "/../includes/common_functions.php");

	$db = new DB_Sql;
	$db->Database = DATABASE_NAME;
	$db->User     = DATABASE_USER;
	$db->Password = DATABASE_PASSWORD;
	$db->Host     = DATABASE_HOST;

	$cvs_login = $_ENV["USER"];	
	$argv = $_SERVER["argv"];

	if ($cvs_login && $argv && $argv[1] == "/home/cvs") {
		
		$cvs_path = $argv[2];
		$cvs_file = $argv[3];
		
		$tmp = explode('/', $cvs_path);
		$cvs_module = $tmp[0];
		
		$commited_date = date("Y-m-d H:i:s");
		
		$sql  = " UPDATE cvs_modules_log";
		$sql .= " SET commited=1, commited_date=" . ToSQL($commited_date, "text") . ",";
		$sql .= " commit_days=DATEDIFF(" . ToSQL($commited_date, "text") . ", started_date)";
		$sql .= " WHERE cvs_login="  . ToSQL($cvs_login, "text");
		$sql .= " AND cvs_module="   . ToSQL($cvs_module, "text");
		$sql .= " AND commited=0";
		$db->query($sql);			
			
		$sql  = " SELECT last_commited FROM cvs_commits_log";
		$sql .= " WHERE cvs_login=" . ToSQL($cvs_login, "text");
		$sql .= " AND cvs_module=" . ToSQL($cvs_module, "text");
		$db->query($sql);
		if ($db->next_record()) {
			
			$sql  = " UPDATE cvs_commits_log";
			$sql .= " SET last_commited=" . ToSQL(date("Y-m-d H:i:s"), "text");
			$sql .= " WHERE cvs_login=" . ToSQL($cvs_login, "text");
			$sql .= " AND cvs_module=" . ToSQL($cvs_module, "text");			
		} else {
			$sql  = " INSERT INTO cvs_commits_log";
			$sql .= " (cvs_login, cvs_module, last_commited) VALUES (";
			$sql .= ToSQL($cvs_login, "text") . ", ";
			$sql .= ToSQL($cvs_module, "text") . ", ";
			$sql .= ToSQL(date("Y-m-d H:i:s"), "text") . ")";
			
		}			
		$db->query($sql);
		
		$fp = fopen(dirname(__FILE__) . "/commits.service.log", "a+");
		fwrite($fp, "\n" . date("Y-m-d H:i:s"));
		fwrite($fp, "\n" . $cvs_login);
		fwrite($fp, "\n" . $cvs_module);
		fclose($fp);
	
	}
	
	

?>