<?php
	include ("./includes/common.php");
	
	$t = new iTemplate($sAppPath);
	$t->set_file("main", "ajax_responder_clients.html");
	
	switch (GetParam("action")) {
		case "get_domains_list":
			get_domains_list(GetParam("domain"), (int) GetParam("task_id"));
		break;
		
		case "domain_selected":
			domain_selected((int) GetParam("domain_id"), GetParam("domain_url"), (int) GetParam("task_id"));
		break;
		
		case "get_clients_list":
			get_clients_list(GetParam("client"), (int) GetParam("task_id"));
		break;
		
		case "client_selected":
			client_selected((int) GetParam("client_id"), (int) GetParam("task_id"));
		break;
	}
		
	function get_domains_list($domain,$task_id) {
		global $db, $t;
		
		$domain = strtolower(trim(rtrim($domain)));
		if (strpos($domain, "www.") === 0) {
			$domain = substr($domain, 4);
		}
		$t->set_var("search_domain", $domain);
		
		$sql  = " SELECT domain_id, domain_url FROM tasks_domains";
		$sql .= " WHERE domain_url LIKE '" . ToSQL($domain, "text", false, false) . "%'";
		$sql .= " OR domain_url LIKE 'www." . ToSQL($domain, "text", false, false) . "%'";
		$sql .= " ORDER BY domain_url";
		$db->query($sql);
		if ($db->next_record()) {
			$t->set_var("no_domain", "");
			do {
				$domain_id  = $db->f("domain_id");
				$domain_url = $db->f("domain_url");
				$t->set_var("domain_id",  $domain_id);
				$t->set_var("task_id",  $task_id);
				$t->set_var("domain_url", $domain_url);
				$t->parse("domain");
			} while ($db->next_record());
		} else {
			$t->set_var("domain", "");
			$t->parse("no_domain");
		}
		$t->parse("domains");
		echo $t->get_var("domains");		
	}
	
	function domain_selected($domain_id,$domain_url,$task_id)
	{
		global $db;
		$response = "";
		$sql  = " SELECT c.client_id, c.client_company FROM tasks_domains td LEFT JOIN clients c ON (td.client_id=c.client_id)";
		$sql .= " WHERE td.domain_id=".ToSQL($domain_id, "integer");
		$db->query($sql);
		if ($db->next_record()) {
			$response .= $db->f("client_id");
			$response .= "||".$db->f("client_company");
		}
		
		echo $response;
	}
	
	function get_clients_list($client,$task_id=0) {
		global $db, $t;
		
		$client = strtolower(trim(rtrim($client)));
		$t->set_var("search_client", $client);
		
		$sql  = " SELECT client_id, client_company FROM clients";
		$sql .= " WHERE client_name LIKE '" . ToSQL($client, "text", false, false) . "%'";
		$sql .= " OR client_company LIKE '" . ToSQL($client, "text", false, false) . "%'";
		$sql .= " OR client_email LIKE '" . ToSQL($client, "text", false, false) . "%'";
		$sql .= " ORDER BY client_company";
		$db->query($sql);
		if ($db->next_record()) {
			$t->set_var("no_client", "");
			do {
				$client_id  = $db->f("client_id");
				$client_name = $db->f("client_company");
				$t->set_var("client_id",  $client_id);
				$t->set_var("task_id",  $task_id);
				$t->set_var("client_name", $client_name);
				$t->parse("client");
			} while ($db->next_record());
		} else {
			$t->set_var("client", "");
			$t->parse("no_client");
		}
		$t->parse("clients");
		echo $t->get_var("clients");		
	}
	
	function client_selected($client_id,$task_id)
	{
		global $db;
		$response = "";
		$sql  = " SELECT domain_id, domain_url FROM tasks_domains";
		$sql .= " WHERE client_id=".ToSQL($client_id, "integer");
		$db->query($sql);
		if ($db->next_record()) {
			$response .= $db->f("domain_url");
			$response .= "||".$db->f("domain_id");
		}
		
		echo $response;
	}
?>