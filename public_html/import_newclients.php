#!/usr/bin/php5 -q
<?php	
	chdir(dirname(__FILE__));
	$is_admin_path = false;
	
	$root_folder_path = (isset($is_admin_path) && $is_admin_path) ? "../" : "./";
	include_once($root_folder_path . "db_mysql.inc");
	include_once($root_folder_path . "includes/db_connect.php");
  	include_once($root_folder_path . "includes/common_functions.php");
	
	$db = new DB_Sql();
	$db->Database	= DATABASE_NAME;
	$db->User		= DATABASE_USER;
	$db->Password	= DATABASE_PASSWORD;
	$db->Host		= DATABASE_HOST;
	
	
	ini_set("soap.wsdl_cache_enabled", "0"); // disabling WSDL cache for development, change in production version

	$wsdl_url = "http://soap.sayu.co.uk/v2/ClientsService?wsdl";
	
	$added_domains = array();	
	try{
		$params = array('encoding'=>'UTF-8');//, "proxy_host" => "viart.com.ua", "proxy_port" => 8080, 'proxy_login' => '', 'proxy_password' => '');
		$client = new SoapClient($wsdl_url, $params);
				
		$sql  = " SELECT MAX(sayu_user_id) as max_sayu_user_id FROM clients WHERE sayu_user_id IS NOT NULL";
		$sql .= " AND sayu_user_id!=0 AND is_viart=0";
		$db->query($sql);
		if($db->next_record()) {
			$filters = array(array('name' => 'min_client_id', 'value' => $db->f("max_sayu_user_id")));
			import_clients($client->getClientsList($filters));
		}		
				
		$response = $client->getDomainsList();
		$domains = $response;
		foreach ($domains AS $domain) {
			$domain["domain"] = trim(rtrim($domain["domain"]));
			if (in_array($domain['domain'], $added_domains)) {
				continue;
			}
			echo 'adding ' . $domain['domain'] . "\n";
			$sql  = " SELECT * FROM tasks_domains WHERE domain_url='" . $domain["domain"] . "'";
			$db->query($sql);
			if (!$db->next_record()) {
				$sayu_client_id = $domain["clientId"];
				$client_id      = 0;
				if ($sayu_client_id) {
					$sql  = " SELECT client_id FROM clients WHERE sayu_user_id=" . ToSQL($sayu_client_id, "integer");
					$db->query($sql);
					if ($db->next_record) {
						$client_id = $db->f('client_id');
					} else {
						echo 'loading client for ' . $domain['domain'] . "\n";
						$filters = array(array('name' => 'client_id', 'value' => $sayu_client_id));
						import_clients($client->getClientsList($filters));					
						continue;
					}
				}
				echo 'inserting ' . $domain['domain'] . "\n";
				$sql  = " INSERT INTO tasks_domains VALUES(NULL, ";
				$sql .= " '" . $domain["domain"] . "', ";
				$sql .= ToSQL($client_id, "integer") . ",";
				$sql .= ToSQL($domain["id"], "integer") . ")";
				$db->query($sql);
			}
		}
		
	} catch(Exception $e) {
		var_dump($e);
	}
	
	function import_clients($clients) {
		global $db, $added_domains;
		
		if (sizeof($clients))
		{
			foreach($clients as $client)
			{
				$client_name    = $client["name"];
				$sayu_client_id = $client["id"];
				$client_email   = $client["email"];
				$client_company = $client["company"];
				$client_website = $client[0]["website"];
				$is_active = $client["activity"] ? 1 : 0 ;
				
				$sql  = " SELECT * FROM clients WHERE sayu_user_id=" . ToSQL($sayu_client_id, "integer");
				$db->query($sql);
				if (!$db->next_record()) {
					$sql  = " INSERT INTO clients(client_id, sayu_user_id, client_name, client_email, client_company, is_active)";
					$sql .= " VALUES(NULL,";
					$sql .= ToSQL($sayu_client_id,"integer")  . ",";
					$sql .= "'" . addslashes($client_name)    . "',";
					$sql .= "'" . addslashes($client_email)   . "',";
					$sql .= "'" . addslashes($client_company) . "',";
					$sql .= ToSQL($is_active,"integer").")";
					$db->query($sql);
					
					$sql = "SELECT MAX(client_id) as max_client_id FROM clients";
					$db->query($sql);
					$db->next_record();
					$client_id = $db->f("max_client_id");
					
					//accounts
					$accounts = $client["accounts"];
					foreach ($accounts as $account)
					{
						$sql = " INSERT INTO clients_accounts VALUES (NULL,";
						$sql .= ToSQL($client_id,"integer").",";
						$sql .= "'".addslashes($account["type"])."',";
						$sql .= $account["outerId"].",0,";
						$sql .= "'".addslashes($account["name"])."',";
						$sql .= "'".addslashes($account["name2"])."',";
						$sql .= ToSQL($account["activity"] ? 1 : 0,"integer").")";
						$db->query($sql);
						
						$sql  =  "UPDATE clients SET google_id=".ToSQL($account["outerId"],"text");
						$sql .= " , google_accounts_emails='".addslashes($account["name"])."'";
						$sql .= " WHERE client_id=".ToSQL($client_id,"integer");
						$db->query($sql);
					}
				}
				
				$domains = $client["domains"];
				foreach ($domains as $domain) {
					$domain["domain"] = trim(rtrim($domain["domain"]));
					$added_domains[] = $domain["domain"];
					$sql  = " SELECT * FROM tasks_domains WHERE domain_url='" . $domain["domain"] . "'";
					$db->query($sql);
					if (!$db->next_record()) {
						$sql  = " INSERT INTO tasks_domains VALUES(NULL, ";
						$sql .= " '" . $domain["domain"] . "', ";
						$sql .= ToSQL($client_id, "integer") . ",";
						$sql .= ToSQL($domain["id"], "integer") . ")";
						$db->query($sql);
						echo 'inserting ' . $domain['domain'] . "\n";
					} else {
						if ($db->f('client_id') != $client_id) {
							$sql  = " UPDATE tasks_domains ";
							$sql .= " SET client_id=" . ToSQL($client_id, "integer");
							$sql .= " WHERE domain_url='" . $domain["domain"] . "'";
							$db->query($sql);
							echo 'updating ' . $domain['domain'] . "\n";
						}
					}
				}
			}
		}
	}
?>