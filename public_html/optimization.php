<?php
	include_once("./includes/common.php");

	$dbo = new DB_Sql();
	$dbo->Database = DATABASE_NAME;
	$dbo->User     = DATABASE_USER;
	$dbo->Password = DATABASE_PASSWORD;
	$dbo->Host     = DATABASE_HOST;

	$db2 = new DB_Sql();
	$db2->Database = 'sayu';
	$db2->User     = 'sayu';
	$db2->Password = 'cnfn=06';
	$db2->Host     = '62.149.0.96:3307';
	//$db2->Host     = '192.168.0.1:3309';

	$clients	= array();
	$tasks		= array();
	$sites		= array();
	$sayu		= array();

	clearoldclients ();
	/**/
	$sql = " ALTER TABLE `clients` CHANGE `viart_user_id` `sayu_user_id` INT( 11 ) NULL DEFAULT NULL  ";
	$db->query($sql,__FILE__,__LINE__);
	$sql = "ALTER TABLE `clients` DROP INDEX `clients`  ";
	$db->query($sql,__FILE__,__LINE__);
	$sql = " ALTER TABLE `clients` CHANGE `client_company` `client_company` VARCHAR( 255 ) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL  ";
	$db->query($sql,__FILE__,__LINE__);
	$sql = " ALTER TABLE `clients` CHANGE `google_id` `google_id` VARCHAR( 255 ) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL  ";
	$db->query($sql,__FILE__,__LINE__);
    /**/
	$sql = "SELECT	cap.client_id		AS sayu_user_id,
					c.user_name			AS client_name,
					c.client_email		AS client_email,
					c.client_company	AS client_company,
					DATE(NOW())			AS date_added,
					2					AS client_type,
					''					AS web_address,
					'0'					AS is_active,
					IFNULL(cap.account_mcc,0) AS account_mcc,
					GROUP_CONCAT(cap.account_id SEPARATOR ';') AS google_id,
					cap.id as id
			FROM	clients_ppc_accounts cap
					INNER JOIN clients c ON c.id = cap.client_id
			WHERE	cap.account_type = 1
					AND cap.account_declined<>-1
			GROUP BY cap.client_id
			ORDER BY cap.client_id";
	$db2->query($sql,__FILE__,__LINE__);
	while ($db2->next_record()) {
		$sayu_user_id	= $db2->Record['sayu_user_id'];
		$client_name	= $db2->Record['client_name'];
		$client_email	= $db2->Record['client_email'];
		$date_added		= $db2->Record['date_added'];
		$client_company	= $db2->Record['client_company'];
		$client_type	= $db2->Record['client_type'];
		$web_address	= $db2->Record['web_address'];
		$is_active		= $db2->Record['is_active'];
		$account_mcc	= $db2->Record['account_mcc'];
		$google_id		= trim(ereg_replace("[- ]","",$db2->Record['google_id']));

		$sql = "SELECT * FROM clients WHERE client_type=2 AND sayu_user_id=".ToSQLO($sayu_user_id,"integer",false,false);
		$dbo->query($sql,__FILE__,__LINE__);
		if ($dbo->num_rows()) {			$dbo->next_record();
			/**/
			if ($client_name	!= $dbo->f("client_name") ||
				$client_email	!= $dbo->f("client_email") ||
				$account_mcc	!= $dbo->f("account_mcc") ||
				$google_id		!= trim(ereg_replace("[- ]","",$dbo->Record['google_id']))) {					$sql = "UPDATE clients
							SET	client_name		='".mysql_escape_string($client_name)."',
								client_email	='".mysql_escape_string($client_email)."',
								date_added		=".ToSQLO($date_added,"date").",
								client_company	='".mysql_escape_string($client_company)."',
								client_type		=".ToSQLO($client_type,"integer",false,false).",
								is_active		=".ToSQLO($is_active,"integer",false).",
								account_mcc		=".ToSQLO($account_mcc,"string",true,true).",
								google_id		=".ToSQLO($google_id,"string",true,true)."
							WHERE	sayu_user_id=".ToSQLO($sayu_user_id,"integer",false);				}
			/**/		}
		else {
			$sql = "INSERT INTO clients
					SET sayu_user_id	=".ToSQLO($sayu_user_id,"integer",false).",
						client_name		='".mysql_escape_string($client_name)."',
						client_email	='".mysql_escape_string($client_email)."',
						date_added		=".ToSQLO($date_added,"date").",
						client_company	='".mysql_escape_string($client_company)."',
						client_type		=".ToSQLO($client_type,"integer",false).",
						is_active		=".ToSQLO($is_active,"integer",false).",
						account_mcc		=".ToSQLO($account_mcc,"string",true,true).",
						google_id		=".ToSQLO($google_id,"string",true,true);
		}
		$db->query($sql,__FILE__,__LINE__);

	}
	/**/
    foreach ($tasks as $k => $task_id) {    	if ($sayu[$k]) {    		$sql = "SELECT client_id FROM clients WHERE sayu_user_id=".ToSQLO($sayu[$k],"integer",false,false);
    		$db->query($sql);
    		$db->next_record();
    		if ($db->f("client_id")) {    			$client_id = $db->f("client_id");
    			$sql = "UPDATE tasks SET client_id=".ToSQLO($client_id,"integer",false,false)." WHERE task_id=".ToSQLO($task_id,"integer",false,false);
    			$db->query($sql);    		}    	}    }
    /**/

	/*drop TEMPORARY TABLE */
	$sql = "DROP TABLE IF EXISTS tsayu_clients_task;";
	$db->query($sql,__FILE__,__LINE__);
	$sql = "DROP TABLE IF EXISTS tsayu_clients;";
	$db->query($sql,__FILE__,__LINE__);
	/**/

function clearoldclients (){
	global $db,$clients,$tasks,$sites,$sayu;

	$sql = "SELECT	c.client_id,
					t.task_id,
					cs.site_id,
					c.viart_user_id
			FROM	clients c
					LEFT JOIN tasks t ON ( t.client_id = c.client_id )
					LEFT JOIN clients_sites cs ON ( cs.client_id = c.client_id )
			WHERE	c.client_type =2
					AND c.is_viart =0
					AND is_viart_hosted =0";
	$db->query($sql,__FILE__,__LINE__);

	while ($db->next_record()) {
		array_push($clients,$db->f("client_id"));
		array_push($tasks,$db->f("task_id"));
		array_push($sites,$db->f("site_id"));
		array_push($sayu,$db->f("viart_user_id"));
	}

	foreach ($tasks as $k => $id) {		if ($id) {
			$sql = "UPDATE tasks SET client_id = -1 WHERE task_id=".ToSQLO($id,"integer",false);
			$db->query($sql,__FILE__,__LINE__);
		}
	}

	foreach ($sites as $k => $id) {
		$sql = "DELETE FROM clients_sites WHERE site_id=".ToSQLO($id,"integer");
		$db->query($sql,__FILE__,__LINE__);
	}
	$sql = "DELETE FROM clients WHERE client_type=2";
	$db->query($sql,__FILE__,__LINE__);
	return true;
}

function ToSQLO($value, $type, $use_null = true, $is_delimiters = true)
{
	$type = strtolower($type);

	/**/
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
		if(get_magic_quotes_gpc() == 0) {
			$value = str_replace("'", "''", $value);
			$value = str_replace("\\", "\\\\", $value);
		} else {
			$value = str_replace("\\'", "''", $value);
			$value = str_replace("\\\"", "\"", $value);
		}
		//if ($is_delimiters) { $value = "'" . $value . "'";}
		$value = "'" . $value . "'";
	}
	/**/
	return $value;
}
?>