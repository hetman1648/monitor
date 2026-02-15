<?php
	include("./includes/date_functions.php");
	include("./includes/common.php");
	include("./includes/create_table.php");
	
	$sort = GetParam("sort");
	
	$viart_client = GetParam("viart_id");
	$sayu_client  = GetParam("sayu_id");
	$type_client = GetParam("client_type");
	$searchword = trim(GetParam("searchword"));
	$searchtype = GetParam("search_by")?GetParam("search_by"):false;
	
	$search_by = GetParam('search_by');
	$text = GetParam('text');
	$client_type = GetParam('client_type');
	
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");  // disable IE caching
	header("Last-Modified: " . gmdate( "D, d M Y H:i:s") . " GMT");
	header("Cache-Control: no-cache, must-revalidate");
	header("Pragma: no-cache");
	
	$T = new iTemplate($sAppPath);
	$T->set_file("main","load_clients.html");

	$reverse = GetParam("reverse");
	if ($reverse) $T->set_var("reverse", '');
	else $T->set_var("reverse", '&reverse=reverse');
	$type=1;
	$type = GetParam('show_table') != '' ? GetParam('show_table') : 3;

	$page_num = GetParam('page_num') ? GetParam('page_num') : 1;
	
	$T->set_var("m_client_id",$viart_client);
	$T->set_var("o_client_id",$sayu_client);
	$T->set_var("keyword",$searchword);

	$T->set_var("selected_1","");
	$T->set_var("selected_2","");
	$T->set_var("selected_0","");
	switch ($type_client) {
		case "1": $T->set_var("selected_1","SELECTED"); break;
		case "2": $T->set_var("selected_2","SELECTED"); break;
		case "0": $T->set_var("selected_0","SELECTED"); break;
		default : $T->set_var("selected_0","SELECTED");
	}
	
	$T->set_var("selected_client_name","");
	$T->set_var("selected_client_email","");
	$T->set_var("selected_client_company","");
	$T->set_var("selected_google_id","");
	$T->set_var("selected_all","");
	switch ($searchtype) {
		case "client_name": $T->set_var("selected_client_name","SELECTED"); break;
		case "client_email": $T->set_var("selected_client_email","SELECTED"); break;
		case "client_company": $T->set_var("selected_client_company","SELECTED"); break;
		case "client_google_id": $T->set_var("selected_google_id","SELECTED"); break;
		case "all": $T->set_var("selected_all","SELECTED"); break;
	}

	// SECOND DATABASE OBJECT
	//ALTER TABLE tasks ADD client_id INT(11) DEFAULT 0;


	$db2 = new DB_Sql;

	$db2->Database = DATABASE_NAME;
	$db2->User     = DATABASE_USER;
	$db2->Password = DATABASE_PASSWORD;
	$db2->Host     = DATABASE_HOST;
	$db2->connect();

	$sql = 'CREATE TABLE IF NOT EXISTS clients
		(
			client_id INT(11) AUTO_INCREMENT,
			PRIMARY KEY (client_id),
			sayu_user_id INT(11),
			client_name VARCHAR(255) NOT NULL,
			client_email VARCHAR(255),
			date_added DATETIME NOT NULL,
			is_viart TINYINT(2) DEFAULT \'0\',
			is_viart_hosted TINYINT(2) DEFAULT \'0\',
			notes TEXT
		)';
	$db->query($sql,__FILE__,__LINE__);

	$sql = 'SET @a := 0';
	$db->query($sql,__FILE__,__LINE__);

	$sql = '
		CREATE TABLE IF NOT EXISTS clients_sites
			(
				site_id INT(11) AUTO_INCREMENT PRIMARY KEY,
				client_id INT(11) NOT NULL,
				web_address VARCHAR(255) NOT NULL,
				admin_web_address VARCHAR(255),
				admin_web_site_login VARCHAR(40),
				admin_web_site_password VARCHAR(40),
				ftp_address VARCHAR(40),
				ftp_login VARCHAR(40),
				ftp_password VARCHAR(40),
				notes TEXT,
				date_added DATETIME NOT NULL,
				date_changed DATETIME NOT NULL
			)';
	$db->query($sql,__FILE__,__LINE__);
	$sqlsearch = '';

	$T->set_var('tab1_class', 'close');
	$T->set_var('tab2_class', 'close');
	$T->set_var('tab3_class', 'close');
	
	$T->set_var("viart_clients_block","");
	$T->set_var("sayu_clients_block","");
	$T->set_var("search_clients_block","");

	if ($type == 1) {
		$sql = '
			SELECT
				c.sayu_user_id as sayu_user_id,
				c.client_name as client_name,
				c.client_id as client_id,
				c.client_email as client_email,
				DATE(c.date_added) as date_added,
				IF(c.is_viart=1, \'Yes\', \'No\') as is_viart,
				IF(c.is_viart_hosted=1, \'Yes\', \'No\') as is_viart_hosted,
				REPLACE(GROUP_CONCAT(DISTINCT cs.web_address SEPARATOR \'<br>\'), \'http://\', \'\') as sites,
				CONCAT(client_name, \''.htmlspecialchars('<br>').'\', client_email) as client_name_email,
				IFNULL(SUM(t.actual_hours), \'0\') as hours
			FROM
				((clients c
				LEFT JOIN clients_sites cs ON (c.client_id = cs.client_id))
				LEFT JOIN tasks t ON (t.client_id = c.client_id))
			WHERE client_type=1
			GROUP BY c.client_id
			';

		$sql = CreateTable($sql, 'active_clients', 'db', 'T', $sort, $page_num, $reverse, 30, true);

		closetables('active_clients_display');
		$T->set_var('tab1_class', 'open');
		
		$T->parse("viart_clients_block");
	} elseif ($type == 2) {
		$T->set_var('client_id', 'qqq');
		$T->parse('active_clients', false);
		$sql = '
			SELECT
				c.client_id as client_id,
				IF((@a:=@a+1)%2 = 1, \'B0B0B0\', \'E0E0E0\') as color,
				c.sayu_user_id as sayu_user_id,
				c.client_name as client_name,
				c.client_email as client_email,
				DATE(c.date_added) as date_added,
				IF(is_active=1, \'Yes\', \'No\')as is_active,
				REPLACE(GROUP_CONCAT(DISTINCT cs.web_address SEPARATOR \'<br>\'), \'http://\', \'\') as sites,
				CONCAT(client_name, \''.htmlspecialchars('<br>').'\', client_email) as client_name_email,
				IFNULL(SUM(t.actual_hours), \'0\') as hours
			FROM
				clients c
				LEFT JOIN
				clients_sites cs
				ON (c.client_id = cs.client_id)
				LEFT JOIN tasks t
				ON (t.client_id = c.client_id)
			WHERE client_type=2 AND is_active=1
			GROUP BY c.client_id
			';

		$sql = CreateTable($sql, 'all_clients', 'db', 'T', $sort, $page_num, $reverse, 20, true);
		$T->set_var('tab2_class', 'open');
		closetables('all_clients_display');
	    $T->set_var('search_params', '');
	    $T->set_var('client_id', 'qqq');
		$T->parse("sayu_clients_block");
	} elseif ($type == 3) {
		/*/
		$T->set_var('client_id', 'qqq');
		$T->set_var('search_params', 'viart_id='.$viart_client.'&sayu_id='.$sayu_client.'&show_table=3'.'&search_by='.$searchtype.'&client_type='.$type_client.'&searchword='.$searchword);
        $sql_client = "";
		if ($type_client) {
			switch ($type_client) {
				case "1": $sql_client .= " AND c.client_type=1 "; break;
				case "2": $sql_client .= " AND c.client_type=2 ";break;
				case "0": break;
				default : $sql_client .= " AND c.client_type=1 ";
			}
			//$sql_client .= " AND 1 ";
		}
		if ($viart_client) { $sql_client .= " AND c.client_id = ".ToSQL($viart_client,"integer",false,false)." ";}
		elseif ($sayu_client) { $sql_client .= " AND c.sayu_user_id = ".ToSQL($sayu_client,"integer",false,false)." ";}

        $sql_word ="";
		if ($searchword) {
			switch ($searchtype) {
				case "client_name": $sql_word .=" AND c.client_name LIKE ".ToSQL("%".$searchword."%","string",true,true)." "; break;
				case "client_email": $sql_word .=" AND c.client_email LIKE ".ToSQL("%".$searchword."%","string",true,true)." "; break;
				case "client_company": $sql_word .=" AND c.client_company LIKE ".ToSQL("%".$searchword."%","string",true,true)." "; break;
				case "client_google_id": $sql_word .=" AND c.google_id LIKE ".ToSQL("%".$searchword."%","string",true,true)." "; break;
				case "all": $sql_word .=" AND (c.client_name LIKE ".ToSQL("%".$searchword."%","string",true,true)." OR
												c.client_email LIKE ".ToSQL("%".$searchword."%","string",true,true)." OR
												c.client_company LIKE ".ToSQL("%".$searchword."%","string",true,true)." OR
												c.google_id LIKE ".ToSQL("%".$searchword."%","string",true,true).") "; break;
			}
			//$sql_word .= " AND 1 ";
		}

        $sql_search = $sql_client.$sql_word;
		$sql = '
			SELECT
			    c.client_id as client_id,
				IF((@a:=@a+1)%2 = 1, \'B0B0B0\', \'E0E0E0\') as color,
				c.sayu_user_id as sayu_user_id,
				c.client_name as client_name,
				c.client_email as client_email,
				DATE(c.date_added) as date_added,
				IF(is_active=1, \'Yes\', \'No\')as is_active,
				REPLACE(GROUP_CONCAT(cs.web_address SEPARATOR \'<br>\'), \'http://\', \'\') as sites,
				(SELECT client_type FROM lookup_clients_types WHERE client_id = c.client_type) as client_type,
				CONCAT(client_name, \''.htmlspecialchars('<br>').'\', client_email) as client_name_email,
				IFNULL(SUM(t.actual_hours), \'0\') as hours
			FROM
				clients c
				LEFT JOIN
				clients_sites cs
				ON (c.client_id = cs.client_id)
				LEFT JOIN tasks t
				ON (t.client_id = c.client_id)
			WHERE 1=1 '.$sql_search.'
			GROUP BY c.client_id ';
		/**/
		
		$T->set_var('client_id', 'qqq');
		$T->parse('active_clients', false);
		$T->parse('all_clients', false);
		
		$T->set_var('search_params', 'viart_id='.$viart_client.'&sayu_id='.$sayu_client.'&show_table=3'.'&search_by='.$searchtype.'&client_type='.$type_client.'&searchword='.$searchword);
		//$T->set_var('search_params', '&search_by='.$search_by.'&text='.$text.'&client_type='.$client_type);
		$sql_client = "";
		if ($type_client) {
			switch ($type_client) {
				case "1": $sql_client .= " AND c.client_type=1 "; break;
				case "2": $sql_client .= " AND c.client_type=2 ";break;
				case "0": break;
				default : $sql_client .= " AND c.client_type=1 ";
			}
			//$sql_client .= " AND 1 ";
		}
		
		if ($viart_client) { $sql_client .= " AND c.client_id = ".ToSQL($viart_client,"integer",false,false)." ";}
		elseif ($sayu_client) { $sql_client .= " AND c.sayu_user_id = ".ToSQL($sayu_client,"integer",false,false)." ";}

        $sql_word ="";
		if ($searchword) {
			switch ($searchtype) {
				case "client_name": $sql_word .=" AND c.client_name LIKE ".ToSQL("%".$searchword."%","string",true,true)." "; break;
				case "client_email": $sql_word .=" AND (c.client_email LIKE ".ToSQL("%".$searchword."%","string",true,true)." ";
										$sql_word .=" OR c.google_accounts_emails LIKE ".ToSQL("%".$searchword."%","string",true,true).") "; break;
				case "client_company": $sql_word .=" AND c.client_company LIKE ".ToSQL("%".$searchword."%","string",true,true)." "; break;
				case "client_google_id": $sql_word .=" AND c.google_id LIKE ".ToSQL("%".$searchword."%","string",true,true)." "; break;
				case "all": $sql_word .=" AND (c.client_name LIKE ".ToSQL("%".$searchword."%","string",true,true)." OR
												c.client_email LIKE ".ToSQL("%".$searchword."%","string",true,true)." OR
												c.client_company LIKE ".ToSQL("%".$searchword."%","string",true,true)." OR
												c.google_id LIKE ".ToSQL("%".$searchword."%","string",true,true)." OR
												c.google_accounts_emails LIKE ".ToSQL("%".$searchword."%","string",true,true).") "; break;
			}
			//$sql_word .= " AND 1 ";
		}

        $sql_search = $sql_client.$sql_word;
		
		$sql = '
			SELECT
			    c.client_id as client_id,
				IF((@a:=@a+1)%2 = 1, \'B0B0B0\', \'E0E0E0\') as color,

				c.sayu_user_id as sayu_user_id,
				c.client_name as client_name,
				c.client_email as client_email,
				DATE(c.date_added) as date_added,
				IF(is_active=1, \'Yes\', \'No\')as is_active,
				REPLACE(GROUP_CONCAT(DISTINCT cs.web_address SEPARATOR \'<br>\'), \'http://\', \'\') as sites,
				(SELECT client_type FROM lookup_clients_types WHERE client_id = c.client_type) as client_type,
				CONCAT(client_name, \''.htmlspecialchars('<br>').'\', client_email) as client_name_email,
				IFNULL(SUM(t.actual_hours), \'0\') as hours
			FROM
				clients c
				LEFT JOIN
				clients_sites cs
				ON (c.client_id = cs.client_id)
				LEFT JOIN tasks t
				ON (t.client_id = c.client_id)
			WHERE 1=1 '.$sql_search.'
			GROUP BY c.client_id ';
		if ($sql_search == '') { $sql .= ' LIMIT 0, 10';}
		$T->set_var('search_clients_form_display', 'block');
		if ($search_by != '') {
			closetables('search_clients_display');
			$sql = CreateTable($sql, 'search_clients', 'db', 'T', $sort, $page_num, $reverse, 20, true);
		} else {
			closetables('');
		}
		/**/

		$T->set_var('search_clients_form_display', 'block');
		$T->set_var('tab3_class', 'open');
		$T->parse("search_clients_block");
	}

	if (GetParam('show_table')) $T->set_var('show_table', GetParam('show_table'));
	else $T->set_var('show_table','1');


	if (GetParam('text')) $T->set_var('text', GetParam('text'));
	else $T->set_var('text','');


	//echo '<br>'.$sql;
	if (GetParam('search_by') !== '') $T->set_var('selected_'.GetParam('search_by'), 'selected');
	if (GetParam('client_type') !== '') $T->set_var('selected_'.GetParam('client_type'), 'selected');
	$T->parse("main", true);
	echo '<clients>
	<b id="b_id">';
	echo '<![CDATA[';
	echo $T->get_var("main");
	echo ']]>';
	echo '</b>';
	$db->query($sql,__FILE__,__LINE__);

	while($db->next_record()) echo '<client_item><id>'.$db->Record['client_id'].'</id><name>'.$db->Record['client_name_email'].'</name><hours>'.$db->Record['hours'].'</hours></client_item>';
	echo '</clients>';
	
/*
*	Functions
*/	
	function closetables($showed_table_id)
	{
		global $T;
	    $T->set_var('active_clients_display', 'none');
		$T->set_var('all_clients_display', 'none');
		$T->set_var('search_clients_display', 'none');
		$T->set_var('search_clients_form_display', 'none');
		$T->set_var($showed_table_id, 'block');
	}
?>
