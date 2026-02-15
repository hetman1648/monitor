<?php
	include("./includes/date_functions.php");
	include("./includes/common.php");
	CheckSecurity(1);
	$T = new iTemplate($sAppPath);

	$action_value	= GetParam("action_value");
	$delete		= GetParam("delete");
	$site_id	= GetParam("site_id");
	$action		= GetParam("action");
	$client_id	= GetParam('client_id');
	$notes		= GetParam('notes');
	$person_select	= GetParam('person_select');
	$web_address	= GetParam('web_address');
	$ftp_address	= GetParam('ftp_address');
	$ftp_login		= GetParam('ftp_login');
	$ftp_password	= GetParam('ftp_password');
	$admin_web_address		= GetParam('admin_web_address');
	$admin_web_site_login	= GetParam('admin_web_site_login');
	$admin_web_site_password= GetParam('admin_web_site_password');

	$error = "";

	if ($action == "Cancel") {
		header("Location: view_clients.php");
	}

	if (GetParam('delete')){
		$sql = 'DELETE FROM clients_sites WHERE site_id = ' . ToSQL($site_id,"integer");
		$db->query($sql, __FILE__, __LINE__);
		header("Location: view_clients.php");
	}

	if ($action_value == "Cancel") {
		header("Location: view_clients.php" . $client_id);
	} elseif ($action_value=='Update Site'){
		if (!$web_address) { 
			$error .= "<b>Site URL</b> is required<br>";
		}
		if (!$error) {
			$sql = "UPDATE clients_sites
					SET	client_id				= ".ToSQL($person_select,"integer").",
						web_address				= ".ToSQL($web_address,"string").",
						admin_web_address		= ".ToSQL($admin_web_address,"string").",
						admin_web_site_login	= ".ToSQL($admin_web_site_login,"string").",
						admin_web_site_password = ".ToSQL($admin_web_site_password,"string").",
						ftp_address				= ".ToSQL($ftp_address,"string").",
						ftp_login 				= ".ToSQL($ftp_login,"string").",
						ftp_password			= ".ToSQL($ftp_password,"string").",
						date_changed			= NOW(),
						notes 					= ".ToSQL($notes,"string")."
					WHERE site_id = ".ToSQL($site_id,"integer");
			$db->query($sql,__FILE__,__LINE__);
			update_tags($site_id);
			header("Location: view_clients.php");
		}
	} elseif ($action_value == 'Add Site'){
		if (!$web_address) { $error .= "<b>Site URL</b> is required<br>";}
		if (!$error) {
			$db->query("SELECT MAX(site_id) FROM clients_sites");
			if ($db->next_record()) {
				$site_id = $db->f(0) + 1;
			} else {
				$site_id = 1;
			}
			$sql = "INSERT INTO clients_sites
					SET	site_id				    = ".ToSQL($site_id,"integer").",
						client_id				= ".ToSQL($person_select,"integer").",
						web_address				= ".ToSQL($web_address,"string").",
						admin_web_address		= ".ToSQL($admin_web_address,"string").",
						admin_web_site_login	= ".ToSQL($admin_web_site_login,"string").",
						admin_web_site_password = ".ToSQL($admin_web_site_password,"string").",
						ftp_address				= ".ToSQL($ftp_address,"string").",
						ftp_login 				= ".ToSQL($ftp_login,"string").",
						ftp_password			= ".ToSQL($ftp_password,"string").",
						date_added				= DATE(NOW()),
						date_changed			= DATE(NOW()),
						notes 					= ".ToSQL($notes,"string");			
			$db->query($sql,__FILE__,__LINE__);
			update_tags($site_id);
			header("Location: view_clients.php");
		}
	}

	$T->set_file("main","create_site.html");
	$tags = "";
	if ($site_id){
		$sql='SELECT *, DATE(date_added) as date_added, DATE(date_changed) as date_changed FROM clients_sites WHERE site_id = '.ToSQL($site_id,"integer");
		$db->query($sql,__FILE__,__LINE__);
		if ($db->next_record()){
			$list=$db->Record;
			$T->set_var(array(
					'web_address'		=> $db->Record['web_address'],
					'admin_web_address'	=> $db->Record['admin_web_address'],
					'admin_web_site_login'		=> $db->Record['admin_web_site_login'],
					'admin_web_site_password'	=> $db->Record['admin_web_site_password'],
					'ftp_address'	=> $db->Record['ftp_address'],
					'ftp_login'		=> $db->Record['ftp_login'],
					'ftp_password'	=> $db->Record['ftp_password'],
					'date_added'	=> $db->Record['date_added'],
					'date_changed'	=> $db->Record['date_changed'],
					'site_id'		=> $db->Record['site_id'],
					'notes'			=> $db->Record['notes'],
					'action_value'	=> 'Update Site',
					'delete_button'	=> '<input onclick="return confirm(\'Are you sure?\');" type=submit name="delete" value="Delete Site">'
			));

			$client_option = $list["client_id"];
		}
		
		$sql  = " SELECT t.title FROM clients_sites_tags st";
		$sql .= " INNER JOIN clients_tags t ON t.id=st.tag_id";
		$sql .= " WHERE st.site_id=" . ToSQL($site_id, "integer");
		$db->query($sql,__FILE__,__LINE__);
		if ($db->next_record()){
			$tags .= $db->f("title");
			while($db->next_record()){
				$tags .= ", " . $db->f("title");
			}
		}
	} else {
		$list = $db->Record;
		$T->set_var(array(
				'web_address'		=> $web_address,
	   			'admin_web_address'	=> $admin_web_address,
	   			'admin_web_site_login'		=> $admin_web_site_login,
	   			'admin_web_site_password'	=> $admin_web_site_password,
	   			'ftp_address'	=> $ftp_address,
	   			'ftp_login'		=> $ftp_login,
	   			'ftp_password'	=> $ftp_password,
	   			'date_added'	=> '',
	   			'date_changed'	=> '',
	   			'site_id'		=> '',
	   			'notes'			=> $notes,
				'action_value'	=> 'Add Site',
				'delete_button'	=> ''
		));
		
		$client_option = $person_select;
		if (!$person_select && $client_id) {
			$client_option = $client_id;
		}
	}

	$T->set_var("tags", $tags);	
	
	
	$fast_person_filter = GetParam('fast_person_filter');
	$T->set_var("fast_person_filter", $fast_person_filter);
	$person_where = " WHERE client_type = 1";
	if ($fast_person_filter) {
		$person_where .= " AND client_name LIKE '%" . $fast_person_filter . "%'";
	}
	
	$person_option = GetOptions("clients", "client_id", "client_name", $client_option, $person_where);
	$T->set_var("person_option", $person_option);
	if ($error) {
		$T->set_var("error_message",$error);
		$T->parse("error", false);
	}
	else { $T->set_var("error", "");}

	$T->pparse("main", false);
	
	function update_tags($site_id) {
		global $db;
		$sql  = " DELETE FROM clients_sites_tags";
		$sql .= " WHERE site_id=" . ToSQL($site_id,"integer");
		$db->query($sql);
		
		$tags = GetParam("tags");
		if ($tags) {
			$tags = explode(",", $tags);
			foreach ($tags AS $tag_title) {
				$tag_title  = rtrim(trim($tag_title));
				$sql  = " SELECT id FROM clients_tags";
				$sql .= " WHERE title=" . ToSQL($tag_title, "text");
				$db->query($sql);
				if ($db->next_record()) {
					$tag_id = $db->f(0);
				} else {
					$db->query("SELECT MAX(id) FROM clients_tags");
					if ($db->next_record()) {
						$tag_id = $db->f(0) + 1;
					} else {
						$tag_id = 1;
					}
					$sql  = " DELETE FROM clients_sites_tags";
					$sql .= " WHERE tag_id=" . ToSQL($tag_id,"integer");
					$db->query($sql);
		
					$sql  = " INSERT INTO clients_tags";
					$sql .= " SET id="  . ToSQL($tag_id, "integer");
					$sql .= " , title=" . ToSQL($tag_title, "text");
					$db->query($sql);
				}
				$sql  = " INSERT INTO clients_sites_tags";
				$sql .= " SET site_id=" . ToSQL($site_id, "integer");
				$sql .= " , tag_id="    . ToSQL($tag_id, "integer");
				$db->query($sql);
			}		
		}
	}
?>