<?php

chdir(dirname(__FILE__));

include("./includes/common.php");
include("./includes/date_functions.php");  

$db2 = new DB_Sql;

$db2->Database = 'sayu';
$db2->User     = 'sayu';
$db2->Password = 'cnfn=06';
$db2->Host     = '62.149.0.96:3307';

$db2->query('SHOW TABLES');             
$sql2 = '
SELECT 
	sc.id  as 											 viart_user_id, 
	IF(sc.client_name IS NULL OR sc.client_name = \'\',
		 sc.user_name, sc.client_name) as 				 client_name, 
	IF(cpa.account_email IS NULL, \' \', cpa.account_email) as client_email, 
	DATE(NOW()) as 										 date_added, 
	sc.client_company as 								 client_company,
	2 as 												 client_type,
	sc.client_website as 								 web_address,
	-sc.is_current_client as 							 is_active
FROM 	
	clients sc
	JOIN 
    st_google_accounts cpa
	ON cpa.client_id = sc.id
GROUP BY cpa.account_email, sc.client_name';
$db2->query($sql2);

$db->query('DROP TABLE IF EXISTS tsayu_clients');

$sql = '
CREATE TEMPORARY TABLE tsayu_clients (
  viart_user_id int(11) NOT NULL default \'0\',
  client_name varchar(50) default NULL,
  client_email varchar(255) default NULL,
  date_added date default NULL,
  client_company varchar(100) NOT NULL default \'\',
  client_type bigint(1) NOT NULL default \'0\',
  web_address varchar(255) NOT NULL default \'\',
  is_active int(4) NOT NULL default \'0\'
)';

$db->query($sql);


while($db2->next_record()) 
{
	$sql= '
INSERT INTO 
	tsayu_clients
	(
		viart_user_id, 
		client_name, 
		client_email, 
		date_added, 
		client_company, 
		client_type, 
		web_address, 
		is_active
	)
VALUES
	( 
	'.$db2->Record['viart_user_id'].
	' , \''.mysql_escape_string($db2->Record['client_name']).
	'\' , \''.$db2->Record['client_email'].
	'\' , \''.$db2->Record['date_added'].
	'\' , \''.mysql_escape_string($db2->Record['client_company']).
	'\' , '.$db2->Record['client_type'].
	' , \''.mysql_escape_string($db2->Record['web_address']).
	'\' , '.$db2->Record['is_active'].'
	)';
	$db->query($sql);
};

$sql = '
INSERT IGNORE INTO 
	clients
	(
		viart_user_id, 
		client_name, 
		client_email, 
		date_added, 
		client_type, 
		client_company,
		is_active
	) 
SELECT 
	viart_user_id, 
	client_name, 
	client_email, 
	date_added, 
	client_type, 
	client_company,
	is_active
FROM 
	tsayu_clients';
$db->query($sql);

$sql = '
INSERT IGNORE INTO clients_sites(client_id, web_address)
SELECT c.client_id as client_id, tsc.web_address as web_address
FROM 
	clients c 
	JOIN 
	tsayu_clients tsc 
	ON 
	(
		c.viart_user_id = tsc.viart_user_id AND 
		c.client_name = tsc.client_name AND
		c.client_email = tsc.client_email AND
		c.client_type = tsc.client_type
	)
';
$db->query($sql);

	
?>
