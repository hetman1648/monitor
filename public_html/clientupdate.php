#!/usr/local/bin/php4 -q
<?php	
	chdir(dirname(__FILE__));
	set_error_handler("myErrorHandler");
	
	define("LIMIT_CHECK_USERS",100);
	define("CHARSET", "windows-1251");
	define("RECORD_NONE",0);
	define("RECORD_UPDATE",1);
	define("RECORD_INSERT",2);
	define("MAIL_TYPE_TEXT", 0);
	define("MAIL_TYPE_HTML", 1);
	define("MAIL_TYPE", MAIL_TYPE_HTML);
	
	define("ISDEBUGSQL", 0);
	define("ISDEBUGMESSAGE", 0);
	
	$is_admin_path = false;
	
	$root_folder_path = (isset($is_admin_path) && $is_admin_path) ? "../" : "./";
	
	
	
	include_once($root_folder_path . "includes/lib/nusoap/nusoap.php");
	include_once($root_folder_path . "db_mysql.inc");
	include_once($root_folder_path . "includes/db_connect.php");
  	include_once($root_folder_path . "includes/common_functions.php");
	
	$db = new DB_Sql();
	$db->Database	= DATABASE_NAME;
	$db->User		= DATABASE_USER;
	$db->Password	= DATABASE_PASSWORD;
	$db->Host		= DATABASE_HOST;
	
	$errorList	= array();
	$updateMessage = "";
	$insertMessage = "";
	$insertTotal = 0;
	$updateTotal = 0;
	
	$styles = array();
	$styles["DataRow1"] = "background-color: #EFEFEF; border-style: inset; border-width: 0; font-size: 10pt";
	$styles["DataRow2"] = "background-color: #DEE3E7; border-style: inset; border-width: 0; font-size: 10pt";
	$styles["DataRow3"] = "background-color: #D1D7C8; border-style: inset; border-width: 0; font-size: 10pt";
	
	$eol = GetEol();
	$changeFieldCount = 0;
	
	$checkFieldList = array("sayu_user_id", "client_name", "client_email", "client_company", "date_added", "client_type", "is_active", "account_mcc", "google_id", "google_accounts_emails");
	$fieldTypeList = array(
			"sayu_user_id"	=> "integer",
			"client_name"	=> "string",
			"client_email"	=> "string",
			"client_company"=> "string",
			"date_added"	=> "date",
			"client_type"	=> "integer",
			"is_active"		=> "integer",
			"account_mcc"	=> "integer",
			"google_id"		=> "string",
			"google_accounts_emails" => "string");
	
	/**/
	$client = new soapclient('https://www.sayu.co.uk/services/MonitorClientService?wsdl', true);
	/*/
	$client = new soapclient('http://localhost/sayu/services/MonitorClientService?wsdl', true); 
	/**/
	
	$err = $client->getError();
	
	if (strlen($err))
	{
		$message = $err . " [" . __LINE__ . "]";
		SendReport($message, "Create Client");
		return;
	}
	
	$sql = " SELECT sayu_user_id FROM clients WHERE client_type=2 AND NOT sayu_user_id is NULL ORDER BY sayu_user_id";	
	$db->query($sql,__FILE__,__LINE__);
	$sayuUserIds = array();
	while ($db->next_record())
	{
		$sayuUserIds[] = $db->f("sayu_user_id");
	}
	
	$checkPosition = 0;
	while(sizeof($sayuUserIds) >= ($checkPosition * LIMIT_CHECK_USERS))
	{
		$checkUsers = array();
		$checkUsers = array_slice($sayuUserIds, ($checkPosition * LIMIT_CHECK_USERS), LIMIT_CHECK_USERS);
		
		if (sizeof($checkUsers) > 0)
		{
			$resultCheck = $client->call(
					'GetOldClients',
					array('clientListId' => $checkUsers)
				);
				
			if ($client->fault)
			{
				$errorList[] = "Exception in SOAP server" . " [" . __LINE__ . "]";
			}
			else if (strlen($err = $client->getError()) > 0)
			{
				$errorList[] = $err . " [" . __LINE__ . "]";
				if (strlen($client->getDebug()) > 0 )
				{
					$errorList[] = $client->getDebug() . " [" . __LINE__ . "]";
				}
			}
			else if (CheckUserInfo($resultCheck, $checkUsers) === false)
			{
				$errorList[] = "Error check info for clients BETWEEN " . array_shift($checkUsers) . " AND " . array_pop($checkUsers) . " [" . __LINE__ . "]";
			}
		}
		$checkPosition++;
	}
	
	$checkPosition = 0;
	while(sizeof($sayuUserIds) >= ($checkPosition * LIMIT_CHECK_USERS))
	{
		$checkUsers = array();
		$checkUsers = array_slice($sayuUserIds, ($checkPosition * LIMIT_CHECK_USERS), LIMIT_CHECK_USERS);
		if (sizeof($checkUsers) > 0)
		{
			$resultSearch = $client->call(
					'GetCheckOldClients',
					array('clientListId' => $checkUsers)
				);
				
			if ($client->fault)
			{
				$errorList[] = "Exception in SOAP server" . " [" . __LINE__ . "]";
			}
			else if (strlen($err = $client->getError()) > 0)
			{
				$errorList[] = $err . " [" . __LINE__ . "]";
			}
			else if (NewUserInfo($resultSearch) === false)
			{
				$errorList[] = "Error check info for clients BETWEEN " . array_shift($checkUsers) . " AND " . array_pop($checkUsers) . " [" . __LINE__ . "]";
			}
		}
		$checkPosition++;
	}
	
	$sayuLastUserId = array_pop($sayuUserIds);
	
	$resultNewClients = $client->call(
			'GetNewClients',
			array('clientId' => $sayuLastUserId)
		);
	if ($client->fault)
	{
		$errorList[] = "Exception in SOAP server" . " [" . __LINE__ . "]";
	}
	else if (strlen($err = $client->getError()) > 0)
	{
		$errorList[] = $err . " [" . __LINE__ . "]";
	}
	else if (NewUserInfo($resultNewClients) === false)
	{
		$errorList[] = "Error insert new clients" . " [" . __LINE__ . "]";
	}
	
	if (sizeof($errorList) > 0)
	{
		SendReport(implode("<br>" . $eol,$errorList), "Error");
		
		//WriteLog(implode($eol,$errorList));
	}
	AppendUpdateMessageSend();
	AppendInsertMessageSend();
	echo date("H:i:s d-m-Y") . "\tFinish\r\n";
	
	exit;
	/*
	*	Functions
	*/
	
	function CheckUserAccountInfo($userInfoList)
	{
		global $db, $eol, $checkFieldList, $fieldTypeList;
		
		
	}
	
	function NewUserInfo($userInfoList)
	{
		global $db, $eol, $checkFieldList, $fieldTypeList, $styles;
		
		foreach($userInfoList as $userInfo)
		{
			//$userInfo['client_email'] = preg_replace("/[a-z0-9-]*(\.)+[a-z0-9-]*@sayu.co.uk/","",$userInfo['client_email']);
			$emailsCheck  = explode(";", $userInfo['client_email'] );
			sort(array_unique($emailsCheck));
			$temp = array_shift($emailsCheck);
			if ($temp && strlen($temp) > 0 && $temp != "") { array_push($emailsCheck,$temp); }
			
			$googlesCheck = explode(";",$userInfo['google_id']);
			sort(array_unique($googlesCheck));
			$temp = array_shift($googlesCheck);
			if ($temp && strlen($temp) > 0) { array_push($googlesCheck,$temp); }
			
			$googleAccountsEmailsCheck = explode(";",$userInfo['google_accounts_emails']);
			sort(array_unique($googleAccountsEmailsCheck));
			$temp = array_shift($googleAccountsEmailsCheck);
			if ($temp && strlen($temp) > 0 && $temp != "") { array_push($googleAccountsEmailsCheck,$temp); }
			
			/***/
			$clientId = 0;
			$sqlEmail = "";
			foreach($emailsCheck as $email)
			{
				if (strlen($sqlEmail) > 0)
				{
					$sqlEmail .= " OR ";
				}
				$sqlEmail .= "ccf.field_value LIKE '" . mysql_escape_string(trim($email)) . "'";
			}
			if (strlen($sqlEmail) > 0)
			{
				$sql  = " SELECT ccf.client_id, GROUP_CONCAT(ccf.field_value SEPARATOR ';') as client_email";
				$sql .= " FROM clients_custome_fileds ccf";
				$sql .= " INNER JOIN clients c ON (ccf.client_id=c.client_id AND c.client_company LIKE " . ToSQLO($userInfo['client_company'],"string",true,true) . ")";
				$sql .= " WHERE ccf.field_name='client_email' AND (" . $sqlEmail . ")";
				$sql .= " GROUP BY ccf.client_id;";
				
				if (ISDEBUGSQL)
				{
					$debug_message  = $sql;
					$debug_message .= $eol . "DEBUG: " . __FILE__ . "(line: " . __LINE__ . ")";
					SendReport($debug_message, "NewUserInfo > SQL " . GetTimeStam());
				}
				
				$db->query($sql,__FILE__,__LINE__);
			}
			
			if ($db->num_rows() == 0)
			{
				$sqlGoogle = "";
				foreach($googlesCheck as $google)
				{
					if (strlen($sqlGoogle) > 0)
					{
						$sqlGoogle .= " OR ";
					}
					if (strpos($google, "12345678") === FALSE)
					{
						$sqlGoogle .= "ccf.field_value LIKE '" . mysql_escape_string(trim($google)) . "'";
					}
				}
				if (strlen($sqlGoogle) > 0)
				{
					$sql  = " SELECT ccf.client_id, GROUP_CONCAT(ccf.field_value SEPARATOR ';') as google_id";
					$sql .= " FROM clients_custome_fileds ccf";
					$sql .= " INNER JOIN clients c ON (ccf.client_id=c.client_id AND c.client_company LIKE " . ToSQLO($userInfo['client_company'],"string",true,true) . ")";
					$sql .= " WHERE ccf.field_name='google_id' AND (" . $sqlGoogle . ")";
					$sql .= " GROUP BY ccf.client_id;";
					
					if (ISDEBUGSQL)
					{
						$debug_message  = $sql;
						$debug_message .= $eol . "DEBUG: " . __FILE__ . "(line: " . __LINE__ . ")";
						SendReport($debug_message, "NewUserInfo > SQL " . GetTimeStam());
					}
				
					$db->query($sql,__FILE__,__LINE__);
				}
			}
			
			if ($db->num_rows() == 0)
			{
				$sqlGAE = "";
				foreach($googleAccountsEmailsCheck as $gae)
				{
					if (strlen($sqlGAE) > 0)
					{
						$sqlGAE .= " OR ";
					}
					$sqlGAE .= "ccf.field_value LIKE '" . mysql_escape_string(trim($gae)) . "'";
				}
				if (strlen($sqlGAE) > 0)
				{
					$sql  = " SELECT ccf.client_id, GROUP_CONCAT(ccf.field_value SEPARATOR ';') as google_accounts_emails";
					$sql .= " FROM clients_custome_fileds ccf";
					$sql .= " INNER JOIN clients c ON (ccf.client_id=c.client_id AND c.client_company LIKE " . ToSQLO($userInfo['client_company'],"string",true,true) . ")";
					$sql .= " WHERE ccf.field_name='google_accounts_emails' AND (" . $sqlGAE . ")";
					$sql .= " GROUP BY ccf.client_id;";
					
					if (ISDEBUGSQL)
					{
						$debug_message  = $sql;
						$debug_message .= $eol . "DEBUG: " . __FILE__ . "(line: " . __LINE__ . ")";
						SendReport($debug_message, "NewUserInfo > SQL " . GetTimeStam());
					}
					$db->query($sql,__FILE__,__LINE__);
				}
			}
			
			//continue;
			
			$operation = RECORD_NONE;
			$monitorClientId = 0;
			if ($db->num_rows() == 1)
			{
				$monitorClientId = $db->f("client_id");
				
				$changeFieldList = array();
				foreach($checkFieldList as $checkField)
				{
					if (strcasecmp($db->f($checkField),$userInfo[$checkField]) != 0 && strtolower($userInfo[$checkField]) != "null"
						&& strlen($userInfo[$checkField]) > 0)
					{
						$changeFieldList[] = $checkField;
					}
				}
				
				if (sizeof($changeFieldList) > 0)
				{
					AppendUpdateMessageClient($userInfo['client_name'], $userInfo['sayu_user_id'], $monitorClientId, __LINE__);
					$sqlFieldSet = "";
					foreach($changeFieldList as $changeField)
					{
						if (strlen($sqlFieldSet) > 0)
						{
							$sqlFieldSet .= ", ";
						}
						$sqlFieldSet .= $changeField ."=" . ToSQLO($userInfo[$changeField], $fieldTypeList[$changeField]);
						
						AppendUpdateMessageField($changeField, $db->f($checkField), $userInfo[$changeField]);
					}
					
					SendReport(var_export($userInfo, true) . $eol . "line: " . __LINE__, "DEBUG");
					
					$note  = $db->f("notes");
					$note .= $eol . "SayuClientID=".$userInfo['sayu_user_id'].$eol;
					if (strlen($sqlFieldSet) > 0)
					{
						$sqlFieldSet .= ", ";
					}
					//$sqlFieldSet .= "notes=" . ToSQL($note,"string");
					
					$sql  = " UPDATE clients SET ";
					$sql .= $sqlFieldSet;
					$sql .= " WHERE client_id = " .  ToSQL($monitorClientId, "integer");
					$operation = RECORD_UPDATE;
				}
			}
			else if ($db->num_rows() == 0)
			{
				$sql = "INSERT INTO clients
						SET  sayu_user_id	=" . ToSQLO($userInfo['sayu_user_id'],"integer",false) . "
							,client_name	=" . ToSQLO($userInfo['client_name'],"string",true,true) . "
							,client_email	=" . ToSQLO($userInfo['client_email'],"string",true,true) . "
							,date_added		=" . ToSQLO($userInfo['date_added'],"date") . "
							,client_company	=" . ToSQLO($userInfo['client_company'],"string",true,true) . "
							,client_type	=" . ToSQLO($userInfo['client_type'],"integer",false) . "
							,is_active		=" . ToSQLO($userInfo['is_active'],"integer",false) . "
							,account_mcc	=" . ToSQLO($userInfo['account_mcc'],"string",true,true) . "
							,google_id		=" .ToSQLO($userInfo['google_id'],"string",true,true) . "
							,google_accounts_emails = ".ToSQLO($userInfo['google_accounts_emails'],"string",true,true);
				
				AppendInsertMessage($userInfo);
				$operation = RECORD_INSERT;
			}
			else
			{
				//many clients :(
				$duplicationFieldList = array("sayu_user_id", "client_name", "client_email", "client_company", "account_mcc", "google_id", "google_accounts_emails");
				$message  = "For " . $userInfo['client_name'] . " (sayu: " . $userInfo['sayu_user_id'] . ")";
				$message .= " find next records:" . $eol;
				$message .= "<table>";
				$message .= "<tr style='" . $styles["DataRow3"] . "'>";
				foreach($duplicationFieldList as $filed)
				{
					$message .= "<td>" . $filed . "</td>";
				}
				$message .= "</tr>";
				
				$message .= "<tr style='" . $styles["DataRow1"] . "'>";
				foreach($duplicationFieldList as $filed)
				{
					$message .= "<td>" . $userInfo[$filed] . "</td>";
				}
				$message .= "</tr>";
				
				$currentRow = 2;
				while($db->next_record())
				{
					$sytle = ($currentRow % 2)?$styles["DataRow1"]:$styles["DataRow2"];
					$message .= "<tr style='" . $sytle . "'>";
					foreach($duplicationFieldList as $filed)
					{
						$message .= "<td>" . $db->f($filed) . "</td>";
					}
					$message .= "</tr>";
					$currentRow++;
				}
				$message .= "<tr style='" . $styles["DataRow1"] . "'><td colspan='" . sizeof($duplicationFieldList) . "'>&nbsp;</td></tr>";
				$message .= "</table>";
				$message .= $eol . $eol . $sql . $eol . $eol;
				SendReport($message, "Many clients");
				$sql = "";
			}
			
			if (strlen($sql) > 0)
			{
				/**/
				$db->query($sql,__FILE__,__LINE__);
				if ($db->Errno != 0)
				{
					$error_message  = "Client <b>" . $userInfo['client_name']. "</b> (sayu: " . $userInfo['sayu_user_id'] . ")";
					if ($monitorClientId > 0)
					{
						$error_message .= " [monitor: " . $monitorClientId . "]" . "<br>" . $eol;
					}
					$error_message .= "<br>" . $eol;
					$error_message .= "<b>MySQL Error:</b> " . $db->Error . "<br>" . $eol;
					$error_message .= "<b>SQL Query:</b> " . $sql . "<br>" . $eol;
					SendReport($error_message, "Update Error[Insert]");
				}
				else if ($operation != RECORD_NONE)
				{
					if ($operation == RECORD_UPDATE && $monitorClientId > 0)
					{
						$sql = "DELETE FROM WHERE client_id=" . ToSQLO($monitorClientId,"integer",false);
						$db->query($sql,__FILE__,__LINE__);
					}
					else if ($operation == RECORD_INSERT)
					{
						$monitorClientId = $db->last_id();
					}
					
					if ($monitorClientId > 0)
					{
						$sql = "INSERT IGNORE INTO clients_custome_fileds(client_id, field_name, field_value) VALUES";
						$field = "client_email";
						foreach($emailsCheck as $value)
						{
							if (strlen(trim($value)) > 0)
							{
								$sqlValues  = "(";
								$sqlValues .= ToSQLO($monitorClientId,"integer",false);
								$sqlValues .= ", " . ToSQLO($field,"string",true,true);
								$sqlValues .= ", " . ToSQLO(trim($value),"string",true,true);
								$sqlValues .= ")";
								
								$db->query($sql . $sqlValues . ";",__FILE__,__LINE__);
							}
						}
						$field = "google_id";
						foreach($googlesCheck as $google)
						{
							if (strlen(trim($google)) > 0)
							{
								$sqlValues  = "(";
								$sqlValues .= ToSQLO($monitorClientId,"integer",false);
								$sqlValues .= ", " . ToSQLO($field,"string",true,true);
								$sqlValues .= ", " . ToSQLO(trim($value),"string",true,true);
								$sqlValues .= ")";
								
								$db->query($sql . $sqlValues . ";",__FILE__,__LINE__);
							}
						}
						$field = "google_accounts_emails";
						foreach($googleAccountsEmailsCheck as $value)
						{
							if (strlen(trim($value)) > 0)
							{
								$sqlValues  = "(";
								$sqlValues .= ToSQLO($monitorClientId,"integer",false);
								$sqlValues .= ", " . ToSQLO($field,"string",true,true);
								$sqlValues .= ", " . ToSQLO(trim($value),"string",true,true);
								$sqlValues .= ")";
								
								$db->query($sql . $sqlValues . ";",__FILE__,__LINE__);
							}
						}
					}
				}
				/**/
			}
			
		}
		
		return true;
	}
	
	function CheckUserInfo($userInfoList, $userIds)
	{
		global $db, $eol, $checkFieldList, $fieldTypeList;
		
		$sayuUserInfoList = array();
		$sql  = " SELECT sayu_user_id, client_name, client_email, client_company, date_added, client_type";
		$sql .= " , is_active, account_mcc, google_id, google_accounts_emails, notes";
		$sql .= " FROM clients";
		$sql .= " WHERE client_type=2 AND NOT sayu_user_id is NULL";
		$sql .= " 	AND sayu_user_id IN (" . implode(",", $userIds) . ")";
		$sql .= " ORDER BY sayu_user_id";
		
		if (ISDEBUGSQL)
		{
			$debug_message  = $sql;
			$debug_message .= $eol . "DEBUG: " . __FILE__ . "(line: " . __LINE__ . ")";
			SendReport($debug_message, "CheckUserInfo > SQL " . GetTimeStam());
		}
				
		$db->query($sql,__FILE__,__LINE__);
		while ($db->next_record())
		{
			$sayuUserInfo = array();
			$sayuUserInfo['sayu_user_id']	= $db->f("sayu_user_id");
			$sayuUserInfo['client_name']	= $db->f("client_name");
			$sayuUserInfo['client_email']	= $db->f("client_email");
			$sayuUserInfo['client_company']	= $db->f("client_company");
			$sayuUserInfo['date_added']		= $db->f("date_added");
			$sayuUserInfo['client_type']	= $db->f("client_type");
			$sayuUserInfo['is_active']		= $db->f("is_active");
			$sayuUserInfo['account_mcc']	= $db->f("account_mcc");
			$sayuUserInfo['google_id']		= $db->f("google_id");
			$sayuUserInfo['google_accounts_emails'] = $db->f("google_accounts_emails");
			$sayuUserInfo['notes']			= $db->f("notes");
			$sayuUserInfoList[$sayuUserInfo['sayu_user_id']] = $sayuUserInfo;
		}
		
		foreach($userInfoList as $userInfo)
		{
			//$userInfo['client_email'] = preg_replace("/[a-z0-9-]*(\.)+[a-z0-9-]*@sayu.co.uk/","",$userInfo['client_email']);
			$sayuUserInfo = array();
			$sayuUserInfo = $sayuUserInfoList[$userInfo['sayu_user_id']];
			$userInfo['client_email'] = SortString($userInfo['client_email']);
			$userInfo['google_accounts_emails'] = SortString($userInfo['google_accounts_emails']);
			$userInfo['google_id'] = SortString($userInfo['google_id'], ";", SORT_NUMERIC);
			
			$sayuUserInfo['client_email'] = SortString($sayuUserInfo['client_email']);
			$sayuUserInfo['google_accounts_emails'] = SortString($sayuUserInfo['google_accounts_emails']);
			$sayuUserInfo['google_id'] = SortString($sayuUserInfo['google_id'], ";", SORT_NUMERIC);
			
			$changeFieldList = array();
			foreach($checkFieldList as $checkField)
			{
				if (strcasecmp($sayuUserInfo[$checkField],$userInfo[$checkField]) != 0 && strtolower($userInfo[$checkField]) != "null"
					&& strlen($userInfo[$checkField]) > 0 && $checkField != 'date_added')
				{
					$changeFieldList[] = $checkField;
				}
			}
			
			if (sizeof($changeFieldList) > 0)
			{
				AppendUpdateMessageClient($userInfo['client_name'], $userInfo['sayu_user_id'],0,__LINE__);
				$sqlFieldSet = "";
				foreach($changeFieldList as $changeField)
				{
					if (strlen($sqlFieldSet) > 0)
					{
						$sqlFieldSet .= ", ";
					}
					$sqlFieldSet .= $changeField ."=" . ToSQLO($userInfo[$changeField], $fieldTypeList[$changeField]);
					AppendUpdateMessageField($changeField, $sayuUserInfo[$changeField], $userInfo[$changeField]);
				}
				SendReport(var_export($userInfo, true) . $eol . "line: " . __LINE__, "DEBUG");
				$sql  = " UPDATE clients SET ";
				$sql .= $sqlFieldSet;
				$sql .= " WHERE sayu_user_id = " .  ToSQL($userInfo['sayu_user_id'], "integer");
				$db->query($sql,__FILE__,__LINE__);
				if ($db->Errno != 0)
				{
					$error_message  = "Client <b>" . $userInfo['client_name']. "</b> (sayu: " . $userInfo['sayu_user_id'] . ")" . "<br>" . $eol;
					$error_message .= "<b>MySQL Error:</b> " . $db->Error . "<br>" . $eol;
					$error_message .= "<b>SQL Query:</b> " . $sql . "<br>" . $eol;
					SendReport($error_message, "Update Error");
				}
			}
		}
		
		return true;
	}
	
	function SortString($str, $delim=";", $flags=SORT_STRING)
	{
		$stringArray = explode($delim,$str);
		$stringArray = array_unique($stringArray);
		sort($stringArray, $flags);
		$temp = array_shift($stringArray);
		if ($temp && strlen($temp) > 0 && $temp != "") { array_unshift($stringArray,$temp); }
		
		return implode($delim,$stringArray);
	}
	
	function AppendUpdateMessageSend()
	{
		global $updateMessage, $updateTotal;
		
		if (strlen($updateMessage) > 0)
		{
			$message  = "<html><body>";
			$message .= "<table border='1'>";
			$message .= "<tr><td colspan='3'>";
			$message .= "Total update records: " . $updateTotal;
			$message .= "</td></tr>";
			$message .= "<tr><td colspan='3'>&nbsp;</td></tr>";
			$message .= $updateMessage;
			$message .= "</table>";
			$message .= "</body></html>";
			
			$updateMessage = $message;
			
			SendReport($updateMessage, "Update");
		}
	}
	
	function AppendUpdateMessageClient($clientName, $clientSayuId=0, $clientMonitorId=0, $line=0)
	{
		global $updateMessage, $styles, $changeFieldCount, $updateTotal;
		
		if ($changeFieldCount > 0)
		{
			$updateMessage .= "<tr><td colspan='3'>&nbsp;</td></tr>";
		}
		$changeFieldCount = 0;
		$updateMessage .= "<tr style='" . $styles["DataRow3"] . "'>";
		$updateMessage .= "<td colspan='3' height='30' valign='bottom'>" . $clientName;
		if ($clientSayuId > 0)
		{
			$updateMessage .= " (sayu: " . $clientSayuId . ")";
		}
		if ($clientMonitorId > 0)
		{
			$updateMessage .= " [monitor: " . $clientMonitorId . "]";
		}
		if ($line > 0)
		{
			$updateMessage .= " {line: " . $line . "}";
		}
		$updateMessage .= "</td>";
		$updateMessage .= "</tr>";
		
		$updateMessage .= "<tr style='" . $styles["DataRow3"] . "'>";
		$updateMessage .= "<td>Field name</td>";
		$updateMessage .= "<td>Old value</td>";
		$updateMessage .= "<td>New value</td>";
		$updateMessage .= "</tr>";
		
		$updateTotal++;
	}
	
	function AppendUpdateMessageField($field, $oldValue="", $newValue="")
	{
		global $updateMessage, $styles, $changeFieldCount;
		
		$changeFieldCount++;
		$style = ($changeFieldCount % 2)?$styles["DataRow1"]:$styles["DataRow2"];
		$updateMessage .= "<tr style='" . $style . "'>";
		$updateMessage .= "<td>" . $field . "</td>";
		$updateMessage .= "<td>" . $oldValue . "</td>";
		$updateMessage .= "<td>" . $newValue . "</td>";
		$updateMessage .= "</tr>";
	}
	
	function AppendInsertMessageSend()
	{
		global $insertMessage, $insertTotal;
		
		if (strlen($insertMessage) > 0)
		{
			$insertMessage = "Total insert records: " . $insertTotal . "<br>" . $insertMessage;
			SendReport($insertMessage, "Insert");
		}
	}
	
	function AppendInsertMessage($userInfo)
	{
		global $insertMessage, $styles, $insertTotal;
		
		$insertMessage .= "<table border='1'>";
		$insertMessage .= "<tr><td colspan='2' sytle='" .  $styles['DataRow3'] . "'>";
		$insertMessage .= $userInfo['client_name'] .  " [" . $userInfo['sayu_user_id'] . "]";
		$insertMessage .= "</td></tr>";
		unset($userInfo['client_name']);
		unset($userInfo['sayu_user_id']);
		
		$countRow = 0;
		foreach($userInfo as $name => $value)
		{
			$countRow++;
			$style = ($countRow % 2)?$styles["DataRow1"]:$styles["DataRow2"];
			$insertMessage .= "<tr style='" . $style . "'><td>";
			$insertMessage .= $name . "</td><td>" . $value;
			$insertMessage .= "</td></tr>";
		}		
		$insertMessage .= "</table><br>";
		
		$insertTotal++;
		
	}
	
	function SendReport($mes, $subj="", $attachments="")
	{
		global $errorList;
		
		$mail_to		= "sanuch@viart.com.ua";
		$mail_from		= "Monitor System <system.monitor@viart.com.ua>";
		//$mail_to    .= ", victor@viart.com";
		$mail_subject	= "MonitorUpdateClients" . ((strlen($subj)>0)?": ".$subj:"");
		$mail_body		= $mes;
		$mail_headers = array();
		$mail_headers["from"] = "Monitor System <system.monitor@viart.com.ua>";
		$mail_headers["mail_type"] = MAIL_TYPE;
		if (MAIL_TYPE == MAIL_TYPE_HTML)
		{
			$mail_body = @nl2br($mail_body);
		}
		
		$headers_string = EmailHeadersString($mail_headers);
		
		return @mail($mail_to, $mail_subject, $mail_body, $headers_string);
	}
	
	function GetEol()
	{
		if (strtoupper(substr(PHP_OS,0,3)=='WIN')) {
			$eol = "\r\n";
		} else if (strtoupper(substr(PHP_OS,0,3)=='MAC')) {
			$eol = "\r";
		} else {
			$eol = "\n";
		}
		return $eol;
	}
	
	function EmailHeadersString($mail_headers, $eol = "")
	{
		$headers_string  = "";

		if (!$eol) {
			$eol = GetEol();
		}
		if (!isset($mail_headers["Date"])) {
			if ($headers_string) { $headers_string .= $eol; }
			$headers_string .= "Date: " . date("r"); // RFC 2822 formatted date
		}
		foreach ($mail_headers as $header_type => $header_value) {
			if ($header_type == "to") {
				$header_type = "To";
				$header_value = str_replace(";", ",", $header_value);
			} elseif ($header_type == "from") {
				$header_type = "From";
			} elseif ($header_type == "cc") {
				$header_type  = "Cc";
				$header_value = str_replace(";", ",", $header_value);
			} elseif ($header_type == "bcc") {
				$header_type  = "Bcc";
				$header_value = str_replace(";", ",", $header_value);
			} elseif ($header_type == "reply_to") {
				$header_type  = "Reply-To";
			} elseif ($header_type == "return_path") {
				$header_type  = "Return-path";
			} elseif ($header_type == "mail_type") {
				if (isset($mail_headers["Content-Type"])) {
					$header_type = ""; $header_value = "";
				} else {
					$header_type  = "Content-Type";
					if ($header_value == 1 || $header_value == "text/html") {
						$header_value = "text/html;" . $eol;
					} else {
						$header_value = "text/plain;" . $eol;
					}
					$header_value .= "\tcharset=\"" . CHARSET . "\"";
				} 
			}
			if ($header_type && strlen($header_value)) {
				if ($headers_string) { $headers_string .= $eol; }
				$headers_string .= $header_type . ": " . $header_value;
			}
		}
		if (!isset($mail_headers["Message-ID"])) {
			if ($headers_string) { $headers_string .= $eol; }
			$server_name = isset($_SERVER["SERVER_NAME"]) ? $_SERVER["SERVER_NAME"] : "localhost";
			$message_id = uniqid(time().mt_rand());
			$headers_string .= "Message-ID: <".$message_id."@".$server_name.">"; // RFC 2822 formatted date
		}
		if (!isset($mail_headers["MIME-Version"])) {
			if ($headers_string) { $headers_string .= $eol; }
			$headers_string .= "MIME-Version: 1.0";
		}

		return $headers_string;
	}
	
	function WriteLog($message)
	{
		global $eol;
		
		$fileName = "c:\\servise.txt";
		$f = @fopen($fileName, "a+");
		if ($f)
		{
			@fwrite($f, $message . $eol);
			@fclose($f);
		}
	}
	
	function SmtpCheckResponse($socket, $check_code, &$error) 
	{
		$response = ""; $response_code = "";
		do {
			$line = fgets($socket, 512);
			if (preg_match("/^(\d{3})\s/", $line, $matches)) {
				$response_code = $matches[1];
			}
			$response .= $line;
		} while ($line !== false && !$response_code);

		if ($check_code == $response_code) {
			return $response;
		} else {
			if ($response) {
				$error = "Error while sending email. Server response: " . $response . "\n";
			} else {
				$error = "No response from mail server.\n";
			}
			return false;
		}
	}
	
	function GetSettingValue($settings_array, $setting_name, $default_value = "")
	{
		return (is_array($settings_array) && isset($settings_array[$setting_name]) && strlen($settings_array[$setting_name])) ? $settings_array[$setting_name] : $default_value;
	}

	function ToSQLO($value, $type, $use_null = true, $is_delimiters = true)
	{
		$type = strtolower($type);

		if ($value == "") {
			if ($use_null) {
				if ($is_delimiters) { return "''";}
				else { return "NULL";}
			} elseif ($type == "number" || $type == "integer" || $type == "float") {
				$value = 0;
			}
		}
		elseif ($type == "number") {return doubleval($value);}
		elseif ($type == "integer") {return intval($value);}
		elseif ($type == "date") {
			if (ereg("([0-9]{4})(-|\\|\/){1}([0-9]{1,2})(-|\\|\/){1}([0-9]{1,2})",$value,$t)){
				if (checkdate($t[3],$t[5],$t[1])) { $value = date("Y-m-d", mktime(0,0,0,$t[3],$t[5],$t[1]));}
					else { $value = "0000-00-00";}
			} else { $value = "0000-00-00";}
			$value = "'" . $value . "'";
		} else {
			$value = addslashes($value);
			/*/
			if(get_magic_quotes_gpc() == 0) {
				$value = str_replace("'", "''", $value);
					$value = str_replace("\\", "\\\\", $value);
			} else {
				$value = str_replace("\\'", "''", $value);
				$value = str_replace("\\\"", "\"", $value);
			}
			/**/
			$value = "'" . $value . "'";
		}
		
		return $value;
	}
	
	
	function myErrorHandler($errno, $errstr, $errfile, $errline, $errcontext="")
	{	global $eol;
	
		$message = "";
		$errtype = "Unknown";
		switch ($errno) {
			case E_USER_ERROR:
				$message .= "<b>My ERROR</b> [$errno] $errstr<br />" . $eol;
				$message .= "  Fatal error on line $errline in file $errfile";
				$errtype = "E_USER_ERROR";
				break;

			case E_USER_WARNING:
				$message .= "<b>My WARNING</b> [$errno] $errstr<br />" . $eol;
				$message .= "  Warning on line $errline in file $errfile";
				$errtype = "E_USER_WARNING";
				break;

			case E_USER_NOTICE:
				$message .= "<b>My NOTICE</b> [$errno] $errstr<br />" . $eol;
				$message .= "  Notice on line $errline in file $errfile";
				$errtype = "E_USER_NOTICE";
				break;

			default:
				$message .= "Unknown error type: [$errno] $errstr<br />" . $eol;
				$message .= "  Error on line $errline in file $errfile";
				break;
		}
		
		if (is_array($errcontext))
		{
			$message .= $eol . $eol . var_export($errcontext, true);
		}
		else
		{
			$message .= $eol . $eol . $errcontext;
		}
		
		SendReport($message, $errtype);
	}
	
	function GetTimeStam()
	{
		$time = microtime(true);
		$timeM = $time-floor($time);
		$timeM = substr($timeM,1);
		
		return date("YmdHis") . $timeM;
	}
	
	function var_export_($arr, $recurs=-1)
	{
		global $eol;
		
		$result = "";
		if (is_array($arr))
		{
			$result .= "array(" . sizeof($arr) . ") {" . $eol;
		}
		$recurs++;
		foreach($arr as $key => $value)
		{
			$result .= str_repeat("\t", $recurs);
			$result .= "'" . $key . "'";
			$result .= " => ";
				
			if (is_array($value))
			{
				$result .= var_export_($value, $recurs);
			}
			else
			{
				$result .= $value;
			}
			
			$result .= "," . $eol;
		}
		
		if (is_array($arr))
		{
			$result .= str_repeat("\t", $recurs) . "}";
		}
		
		return $result;
	}
?>