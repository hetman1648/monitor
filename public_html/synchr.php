<?php
	include_once("./includes/db_connect.php");
	include_once("./includes/common_functions.php");
	include_once("./includes/date_functions.php");
	include_once("./db_mysql.inc");

	$db = new DB_Sql();
	$db->Database = DATABASE_NAME;
	$db->User     = DATABASE_USER;
	$db->Password = DATABASE_PASSWORD;
	$db->Host     = DATABASE_HOST;

	$db2 = new DB_Sql();
	$db2->Database = 'sayu';
	$db2->User     = 'sayu';
	$db2->Password = 'cnfn=06';
	$db2->Host     = '62.149.0.96:3307';
	//$db2->Host     = '192.168.0.1:3309';
	
	$dbo = new DB_Sql();
	$dbo->Database = DATABASE_NAME;
	$dbo->User     = DATABASE_USER;
	$dbo->Password = DATABASE_PASSWORD;
	$dbo->Host     = DATABASE_HOST;
	
	$sayu_user_id = "";
    $sql = "SELECT sayu_user_id FROM clients WHERE client_type=2 AND NOT sayu_user_id is NULL ORDER BY sayu_user_id";
    $db->query($sql,__FILE__,__LINE__);
    while ($db->next_record()) { $sayu_user_id[] = $db->f("sayu_user_id");}
    $list_sayu_user_id = implode(",",$sayu_user_id);
    $sql = "SELECT	cap.client_id		AS sayu_user_id,
    				c.is_current_client	AS is_active,    				
    				GROUP_CONCAT(cap.login_email SEPARATOR ';') AS google_accounts_emails
    		FROM	clients_ppc_accounts cap
    				INNER JOIN clients c ON c.id = cap.client_id
    		WHERE	cap.client_id IN (".$list_sayu_user_id.")
    		GROUP BY cap.client_id";
    $db2->query($sql,__FILE__,__LINE__);
	while ($db2->next_record()) {
		$sayu_user_id	= $db2->Record['sayu_user_id'];
		$is_active		= ($db2->Record['is_active'] == -1?1:0);
		$google_accounts_emails = $db2->Record["google_accounts_emails"];
		
		$sql  = " UPDATE clients SET ";
		$sql .= " is_active=" . ToSQLO($is_active,"integer",false,false);
		$sql .= " ,google_accounts_emails=" . ToSQLO($google_accounts_emails,"string");
		$sql .= " WHERE sayu_user_id=" . ToSQLO($sayu_user_id,"integer",false,false);
		$db->query($sql,__FILE__,__LINE__);
	}
	echo "Finish at ".date("H:i:s")."<br>";
	
/*
** Function
*/
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