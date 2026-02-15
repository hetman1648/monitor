<?php

chdir(dirname(__FILE__));

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

/*/
$db2->query('SHOW TABLES');
$sql2 = "
SELECT	c.id				AS viart_user_id,
		c.user_name			AS client_name,
		c.client_email		AS client_email,
		c.client_company	AS client_company,
		DATE(NOW())			AS date_added,
		2					AS client_type,
		''					AS web_address,
		'0'					AS is_active,
		IFNULL(cap.account_mcc,0) AS account_mcc,
		ga.google_id		AS google_id
FROM	clients c
		INNER JOIN clients_ppc_accounts cap ON c.id = cap.client_id
		INNER JOIN st_google_accounts ga ON c.id = ga.client_id
WHERE	c.is_disabled = 0
		AND cap.account_type = 1
GROUP BY ga.account_email, cap.username
ORDER BY cap.client_id";
$db2->query($sql2);

$db->query("DROP TABLE IF EXISTS tsayu_clients");

$sql = "
CREATE TEMPORARY TABLE tsayu_clients (
  viart_user_id int(11) default NULL,
  client_name varchar(255) default NULL,
  client_email varchar(255) default NULL,
  client_company varchar(100) NOT NULL default '',
  date_added date default NULL,
  client_type bigint(1) NOT NULL default '0',
  web_address varchar(255) NOT NULL default '',
  is_active int(4) NOT NULL default '0',
  account_mcc varchar(20) default NULL,
  google_id varchar(20) default NULL
)";

$db->query($sql,__FILE__,__LINE__);


while($db2->next_record()) {
	$sql= " INSERT INTO tsayu_clients
			SET	viart_user_id	=".$db2->Record['viart_user_id'].",
				client_name		='".mysql_escape_string($db2->Record['client_name'])."',
				client_email	='".$db2->Record['client_email']."',
				date_added		='".$db2->Record['date_added']."',
				client_company	='".mysql_escape_string($db2->Record['client_company'])."',
				client_type		=".$db2->Record['client_type'].",
				web_address		='".mysql_escape_string($db2->Record['web_address'])."',
				is_active		=".$db2->Record['is_active'].",
				account_mcc		='".$db2->Record['account_mcc']."',
				google_id		='".$db2->Record['google_id']."'";
	$db->query($sql,__FILE__,__LINE__);
}



$sql = "INSERT IGNORE INTO clients(
									viart_user_id,
									client_name,
									client_email,
									date_added,
									client_type,
									client_company,
									is_active,
									account_mcc,
									google_id)
							SELECT
								viart_user_id,
								client_name,
								client_email,
								date_added,
								client_type,
								client_company,
								is_active,
								account_mcc,
								google_id
							FROM
								tsayu_clients";
$db->query($sql,__FILE__,__LINE__);

$sql = "INSERT IGNORE INTO clients_sites(client_id, web_address)
			SELECT	c.client_id as client_id, tsc.web_address as web_address
			FROM	clients c
					JOIN tsayu_clients tsc ON
						(
							c.viart_user_id = tsc.viart_user_id AND
							c.client_name = tsc.client_name AND
							c.client_email = tsc.client_email AND
							c.client_type = tsc.client_type
						)
			";
$db->query($sql,__FILE__,__LINE__);
$db->query("DROP TABLE IF EXISTS tsayu_clients");



	/**/
	$dbo = new DB_Sql();
	$dbo->Database = DATABASE_NAME;
	$dbo->User     = DATABASE_USER;
	$dbo->Password = DATABASE_PASSWORD;
	$dbo->Host     = DATABASE_HOST;

    /**/
    $sayu_user_id = "";
    $sql = "SELECT sayu_user_id FROM clients WHERE client_type=2 AND NOT sayu_user_id is NULL ORDER BY sayu_user_id";
    $db->query($sql,__FILE__,__LINE__);
    while ($db->next_record()) { $sayu_user_id[] = $db->f("sayu_user_id");}
    $list_sayu_user_id = implode(",",$sayu_user_id);
    $sql = "SELECT	cap.client_id		AS sayu_user_id,
    				c.client_name		AS client_name,
    				c.client_email		AS client_email,
    				c.client_company	AS client_company,
    				IFNULL(cap.account_mcc,0) AS account_mcc,
    				REPLACE(REPLACE(GROUP_CONCAT(cap.account_id SEPARATOR ';'),'-',''),' ','')  AS google_id,
    				GROUP_CONCAT(cap.login_email SEPARATOR ';') AS google_accounts_emails
    		FROM	clients_ppc_accounts cap
    				INNER JOIN clients c ON c.id = cap.client_id
    		WHERE	cap.client_id IN (".$list_sayu_user_id.")
    		GROUP BY cap.client_id";
    $db2->query($sql,__FILE__,__LINE__);
    $update_old = false;
    $message = "Transaction date ".date("d-m-Y H:i:s")."\r\n";
    while ($db2->next_record()) {    	$sql = "SELECT * FROM clients WHERE sayu_user_id=".ToSQLO($db2->f("sayu_user_id"),"integer",false,false);
    	$db->query($sql,__FILE__,__LINE__);
    	$db->next_record();
    	if ((strcasecmp($db2->f("client_name"),$db->f("client_name")) !=0 && strlen($db2->f("client_name"))>1) ||
    		(strcasecmp($db2->f("client_email"),$db->f("client_email")) !=0 && strlen($db2->f("client_email"))>1) ||
    		(strcasecmp($db2->f("client_company"),$db->f("client_company")) !=0 && strlen($db2->f("client_company"))>1) ||
    		(strcasecmp($db2->f("account_mcc"),$db->f("account_mcc")) !=0 && strlen($db2->f("account_mcc"))>0) ||
    		(strcasecmp(trim($db2->f("google_id")),$db->f("google_id")) !=0 && strlen($db2->f("google_id"))>1) ||
    		(strcasecmp($db2->f("google_accounts_emails"),$db->f("google_accounts_emails")) !=0 && strlen($db2->f("google_accounts_emails"))>1) ) {    			$update_old = true;
    			$sql = "UPDATE clients
							SET	client_name		='".mysql_escape_string($db2->f("client_name"))."',
								client_email	='".mysql_escape_string($db2->f("client_email"))."',
								client_company	='".mysql_escape_string($db2->f("client_company"))."',
								account_mcc		=".ToSQLO($db2->f("account_mcc"),"string",true,true).",
								google_id		='".trim(ereg_replace("[- ]","",$db2->f("google_id")))."',
								google_accounts_emails =".ToSQLO($db2->f("google_accounts_emails"),"string",true,true)."
							WHERE	client_id=".ToSQLO($db->f("client_id"),"integer",false,false);
				$message .= $db->f("client_name")."(".$db->f("client_id").")[".$db->f("sayu_user_id")."]\r\n";
				/*/
				$message .= $db2->f("client_name")."\r\n";
				$message .= $db->f("client_name")."\r\n";
				$message .= strcasecmp($db2->f("client_name"),$db->f("client_name"))."\r\n";
				$message .= $db2->f("client_email")."\r\n";
				$message .= $db->f("client_email")."\r\n";
				$message .= strcasecmp($db2->f("client_email"),$db->f("client_email"))."\r\n";
				$message .= $db2->f("client_company")."\r\n";
				$message .= $db->f("client_company")."\r\n";
				$message .= strcasecmp($db2->f("client_company"),$db->f("client_company"))."\r\n";
				$message .= $db2->f("account_mcc")."\r\n";
				$message .= $db->f("account_mcc")."\r\n";
				$message .= strcasecmp($db2->f("account_mcc"),$db->f("account_mcc"))."\r\n";
				$message .= $db2->f("google_id")."\r\n";
				$message .= $db->f("google_id")."\r\n";
				$message .= strcasecmp($db2->f("google_id"),$db->f("google_id"))."\r\n";
				$message .= $db2->f("google_accounts_emails")."\r\n";
				$message .= $db->f("google_accounts_emails")."\r\n";
				$message .= strcasecmp($db2->f("google_accounts_emails"),$db->f("google_accounts_emails"))."\r\n\n";
				/**/
				$message .= $dbo->f("client_name")."(".$dbo->f("client_id").") [".$dbo->f("sayu_user_id")."]\n";
				$message .= "UPDATE clients \n";
				$message .= "SET	client_name		='".mysql_escape_string($dbo->f("client_name"))."', \n";
				$message .= "client_email	='".mysql_escape_string($dbo->f("client_email"))."', \n";
				$message .= "date_added		=".ToSQLO($dbo->f("date_added"),"date").", \n";
				$message .= "client_company	='".mysql_escape_string($dbo->f("client_company"))."', \n";
				$message .= "client_type	=".ToSQLO($dbo->f("client_type"),"integer",false,false).", \n";
				$message .= "is_active		=".ToSQLO($dbo->f("is_active"),"integer",false).", \n";
				$message .= "account_mcc	=".ToSQLO($dbo->f("account_mcc"),"string",true,true).", \n";
				$message .= "google_id		=".ToSQLO($dbo->f("google_id"),"string",true,true)."  \n";
				$message .= "user_id=".ToSQLO($sayu_user_id,"integer",false)."\r\n";
				/**/
				$db->query($sql,__FILE__,__LINE__);    		}    }
    /**/




    $sql = "SELECT max(sayu_user_id) as sayu_user_id FROM clients";
    $db->query($sql);
    $db->next_record();
    $max_id_sayu = $db->f("sayu_user_id");
	$sql = "SELECT	cap.client_id		AS sayu_user_id,
					c.client_name		AS client_name,
					c.client_email		AS client_email,
					c.client_company	AS client_company,
					DATE(NOW())			AS date_added,
					2					AS client_type,
					''					AS web_address,
					'0'					AS is_active,
					IFNULL(cap.account_mcc,0) AS account_mcc,
					GROUP_CONCAT(cap.account_id SEPARATOR ';') AS google_id,
					GROUP_CONCAT(cap.login_email SEPARATOR ';') AS google_accounts_emails,
					cap.id AS id
			FROM	clients_ppc_accounts cap
					INNER JOIN clients c ON c.id = cap.client_id
			WHERE	cap.account_type = 1
					AND cap.account_declined<>-1
					AND cap.client_id > ".ToSQLO($max_id_sayu,"integer",false,false)."
			GROUP BY cap.client_id
			ORDER BY cap.client_id";			
	$db2->query($sql,__FILE__,__LINE__);
	$update_new = false;
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
		$google_accounts_emails = $db2->Record["google_accounts_emails"];


		$client_email = preg_replace("/[a-z0-9-]*(\.)+[a-z0-9-]*@sayu.co.uk/","",$client_email);
		$emails  = split("[;]",$client_email);
		sort(array_unique($emails));
		$temp = array_shift($emails);
		if ($temp && strlen($temp) > 0 && $temp != "") { array_push($emails,$temp);}
		$googles = split ("[;]",$google_id);
		sort(array_unique($googles));
		$temp = array_shift($googles);
		if ($temp && strlen($temp) > 0) { array_push($googles,$temp);}
		$sql_email  = " 1 ";
		$sql_google = " 1 ";
		if (sizeof($emails) > 1) {
			$sql_email .= " (client_email LIKE '%".array_shift($emails)."%' ";
			foreach($emails as $k => $v) {
				$sql_email .= " OR client_email LIKE '%".$v."%' ";
			}
			$sql_email .= " ) ";
		} elseif (sizeof($emails) > 0) { $sql_email  = " client_email LIKE '%".$emails."%' ";}

		if (sizeof($googles) > 1) {
			$sql_google.= " ( google_id LIKE '%".array_shift($googles)."%' ";
			foreach ($googles as $k => $v) {
				$sql_google .= " google_id LIKE '%".$v."%' ";
			}
			$sql_google .= " ) ";
		} elseif (sizeof($googles) > 0) { $sql_google  = " client_email LIKE '%".$googles."%' ";}

		$sql = "SELECT * FROM clients WHERE client_type=2 AND (".$sql_email." OR ".$sql_google." OR client_name LIKE '".$client_name."' ) ";
		$db->query($sql);
		if ($db->num_rows() == 1) {
			$update_new = true;
			$db->next_record();
			$client_id = $db->f("client_id");
			$note = $db->f("notes");
			$note .= "\nClientID=".$sayu_user_id."\t\r\n";
			$message .= $dbo->f("client_name")."(".$dbo->f("client_id").")\r\n";
			$message .= "UPDATE clients\r\n SET	client_name		='".mysql_escape_string($dbo->f("client_name"))."', \r\n";
			$message .= "client_email	='".mysql_escape_string($dbo->f("client_email"))."',\r\n date_added		=".ToSQLO($dbo->f("date_added"),"date").",\r\n";
			$message .= "client_company	='".mysql_escape_string($dbo->f("client_company"))."',\r\n client_type		=".ToSQLO($dbo->f("client_type"),"integer",false,false).",\r\n";
			$message .= "is_active		=".ToSQLO($dbo->f("is_active"),"integer",false).",\r\n 	account_mcc		=".ToSQLO($dbo->f("account_mcc"),"string",true,true).",\r\n";
			$message .= "google_id		=".ToSQLO($dbo->f("google_id"),"string",true,true)." \r\n";
			$message .= "user_id=".ToSQLO($sayu_user_id,"integer",false)."\r\n";
			$sql = "UPDATE clients
							SET	client_name		='".mysql_escape_string($client_name)."',
								client_email	='".mysql_escape_string($dbo->f("client_email"))."',
								client_company	='".mysql_escape_string($client_company)."',
								client_type		=".ToSQLO($client_type,"integer",false,false).",
								is_active		=".ToSQLO($is_active,"integer",false).",
								account_mcc		=".ToSQLO($account_mcc,"string",true,true).",
								google_id		='".trim(ereg_replace("[- ]","",$db2->Record['google_id']))."',
								notes			=".ToSQLO($note,"string",true,true)."
							WHERE	client_id=".ToSQLO($client_id,"integer",false,false);
		}
		elseif ($db->num_rows() == 0) {
			$sql = "INSERT INTO clients
					SET sayu_user_id	=".ToSQLO($sayu_user_id,"integer",false).",
						client_name		=".ToSQLO($client_name,"string",true,true).",
						client_email	=".ToSQLO($client_email,"string",true,true).",
						date_added		=".ToSQLO($date_added,"date").",
						client_company	=".ToSQLO($client_company,"string",true,true).",
						client_type		=".ToSQLO($client_type,"integer",false).",
						is_active		=".ToSQLO($is_active,"integer",false).",
						account_mcc		=".ToSQLO($account_mcc,"string",true,true).",
						google_id		='".trim(ereg_replace("[- ]","",$db2->Record['google_id']))."'";
			$update_new = true;
			$message .= "$client_name [$sayu_user_id]\n";
			$message .= "INSERT INTO clients\n ";
			$message .= "SET sayu_user_id	=".ToSQLO($sayu_user_id,"integer",false).",\n ";
			$message .= "client_name	=".ToSQLO($client_name,"string",true,true).",\n ";
			$message .= "client_email	=".ToSQLO($client_email,"string",true,true).",\n ";
			$message .= "date_added		=".ToSQLO($date_added,"date").",\n ";
			$message .= "client_company	=".ToSQLO($client_company,"string",true,true).",\n ";
			$message .= "client_type	=".ToSQLO($client_type,"integer",false).",\n ";
			$message .= "is_active		=".ToSQLO($is_active,"integer",false).",\n ";
			$message .= "account_mcc	=".ToSQLO($account_mcc,"string",true,true).",\n ";
			$message .= "google_id		='".trim(ereg_replace("[- ]","",$db2->Record['google_id']))."'\r\n";
		}			
		else {}//many clients :(
		$db->query($sql,__FILE__,__LINE__);
	}

	/*drop TEMPORARY TABLE /
	$sql = "DROP TABLE IF EXISTS tsayu_clients_task;";
	$db->query($sql,__FILE__,__LINE__);
	$sql = "DROP TABLE IF EXISTS tsayu_clients;";
	$db->query($sql,__FILE__,__LINE__);
	/*/
	if ($update_new || $update_old) {
		$mail("sanuch@viart.com.ua","Update clients",$message,"monitor@viart.com.ua");
	}
	/**/

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
/**/

?>
