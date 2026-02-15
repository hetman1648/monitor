#!/usr/bin/php5 -q
<?php	
	chdir(dirname(__FILE__));
	$is_admin_path = false;
	set_time_limit(300);
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
	
	$clients = array();
	try{
		$params = array('encoding'=>'UTF-8');//, "proxy_host" => "viart.com.ua", "proxy_port" => 8080, 'proxy_login' => '', 'proxy_password' => '');
		$client = new SoapClient($wsdl_url, $params);
		
		$sql  = "SELECT sayu_user_id, client_id FROM clients WHERE sayu_user_id IS NOT NULL";
		$sql .= " AND sayu_user_id!=0 AND is_viart=0";
		$db->query($sql);
		if($db->next_record())
		{
			do {
				
				$filters = array();
				$filters[] = array('name' => 'client_id', 'value' => $db->f("sayu_user_id"));
				$response = $client->getClientsList($filters);
				$temp_array = array("client_id" => $db->f("client_id"));
				$clients[] = array_merge($response,$temp_array);
			} while ($db->next_record());
		}
		
		if (sizeof($clients))
		{
			foreach($clients as $client)
			{
				print_r($client);
				$client_name = $client[0]["name"];
				$client_id = $client["client_id"];
				$sayu_client_id = $client[0]["id"];
				$client_email = $client[0]["email"];
				$client_company = $client[0]["company"];
				$client_website = $client[0]["website"];
				$is_active = $client[0]["activity"] ? 1 : 0 ;
				
				//accounts
				$accounts = $client[0]["accounts"];
				foreach ($accounts as $account)
				{
					/*$sql = " INSERT INTO clients_accounts VALUES (NULL,";
					$sql .= ToSQL($client_id,"integer").",";
					$sql .= "'".addslashes($account["type"])."',";
					$sql .= $account["outerId"].",0,";
					$sql .= "'".addslashes($account["name"])."',";
					$sql .= "'".addslashes($account["name2"])."',";
					$sql .= ToSQL($account["activity"] ? 1 : 0,"integer").")";
					$db->query($sql);*/
					
					$sql = " UPDATE clients_accounts SET ";
					$sql .= "account_type = '".addslashes($account["type"])."',";
					$sql .= "outer_account_id1 = ".$account["outerId"].",";
					$sql .= "account_name1 = '".addslashes($account["name"])."',";
					$sql .= "account_name2 = '".addslashes($account["name2"])."',";
					$sql .= "is_active = ".ToSQL($account["activity"] ? 1 : 0,"integer");
					$sql .= " WHERE client_id=".ToSQL($client_id,"integer");
					$db->query($sql);
					
					$sql  =  "UPDATE clients SET google_id=".ToSQL($account["outerId"],"text");
					$sql .= " , google_accounts_emails='".addslashes($account["name"])."'";
					$sql .= " WHERE client_id=".ToSQL($client_id,"integer");
					$db->query($sql);
				}
				
				//domains
				$domains = $client[0]["domains"];
				foreach ($domains as $domain)
				{
					$sql = "UPDATE tasks_domains SET client_id=".ToSQL($client_id,"integer").",";
					$sql.= " sayu_domain_id=".ToSQL($domain["id"],"integer");
					$sql.= " WHERE domain_url LIKE '%".trim($domain["domain"])."%'";
					$db->query($sql);
				}
				$sql  = "UPDATE clients SET ";
				$sql .= " client_name='".addslashes($client_name)."',";
				$sql .= " client_email='".addslashes($client_email)."',";
				$sql .= " client_company='".addslashes($client_company)."',";
				$sql .= " is_active=".ToSQL($is_active,"integer");
				$sql .= " WHERE client_id=".ToSQL($client_id,"integer");
				$db->query($sql);
			}
		}
		
	} catch(Exception $e) {
		var_dump($e);
	}
?>