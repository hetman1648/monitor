<?php

include_once("./includes/date_functions.php");
include_once("./includes/common.php");

CheckSecurity(1);


$db_sub = new DB_Sql();
$db_sub->Database = DATABASE_NAME;
$db_sub->User     = DATABASE_USER;
$db_sub->Password = DATABASE_PASSWORD;
$db_sub->Host     = DATABASE_HOST;

$db2 = new DB_Sql();
$db2->Database = DATABASE_NAME;
$db2->User     = DATABASE_USER;
$db2->Password = DATABASE_PASSWORD;
$db2->Host     = DATABASE_HOST;


$timestamp = time();
$current_year  = date("Y", $timestamp);
$current_month = date("m", $timestamp);
$current_day   = date("d", $timestamp);

$cost_year  = $current_year;
if ($current_month == 1) {
	$cost_year = $current_year - 1;
	$cost_month = 12;
} else {
	$cost_month = $current_month - 1;
	if ($cost_month < 10)
	$cost_month = "0" . $cost_month;
}

$cost_per_hour = 15;
$amount_gbp = 0;

//function mis_Tasks() {
	//global $cost_year, $cost_month, $db, $db2;
	
	$tasks = array();
	$sql = " SELECT t.task_cost, t.actual_hours, t.project_id, t.task_domain_url, c.sayu_user_id";
	$sql .= " FROM tasks t";
	$sql .= " LEFT JOIN clients c ON t.client_id = c.client_id";
	$sql .= " WHERE t.client_id > 0";
	$sql .= " AND YEAR(t.date_reassigned)='$cost_year'";
	$sql .= " AND MONTH(t.date_reassigned)='$cost_month'";
	$db->query($sql);
	while ($db->next_record())	{
	
		$client_id = $db->f("sayu_user_id");
		$project_id = $db->f("project_id");
		$task_cost = $db->f("task_cost");
		$actual_hours = $db->f("actual_hours");
		
		switch($project_id) {
												
			case 53: 			if($task_cost) $amount_gbp = $task_cost;
								else $amount_gbp = $actual_hours * $cost_per_hour;
								$cost_name = "Full Account";
								break;
			
			case 59: 			if($task_cost) $amount_gbp = $task_cost;
								else $amount_gbp = $actual_hours * $cost_per_hour;
								$cost_name = "Paying Client Work";
								break;
						
			case 135: 			if($task_cost) $amount_gbp = $task_cost;
								else $amount_gbp = $actual_hours * $cost_per_hour;
								$cost_name = "SEO Review";
								break;
			
			case 103: 			if($task_cost) $amount_gbp = $task_cost;
								else $amount_gbp = $actual_hours * $cost_per_hour;
								$cost_name = "Article/PR Distribution";
								break;
			
			case 213: 			if($task_cost) $amount_gbp = $task_cost;
								else $amount_gbp = $actual_hours * $cost_per_hour;
								$cost_name = "Article";
								break;
			
			case 233: 			if($task_cost) $amount_gbp = $task_cost;
								else $amount_gbp = $actual_hours * $cost_per_hour;
								$cost_name = "Digital Point Link Allocation";
								break;
			
			case 234: 			if($task_cost) $amount_gbp = $task_cost;
								else $amount_gbp = $actual_hours * $cost_per_hour;
								$cost_name = "Link Manager Import";
								break;
			
			case 267: 			if($task_cost) $amount_gbp = $task_cost;
								else $amount_gbp = $actual_hours * $cost_per_hour;
								$cost_name = "Profile Page";
								break;
			
			case 224: 			if($task_cost) $amount_gbp = $task_cost;
								else $amount_gbp = $actual_hours * $cost_per_hour;
								$cost_name = "Video Distribution";
								break;
			
			case 222: 			if($task_cost) $amount_gbp = $task_cost;
								else $amount_gbp = $actual_hours * $cost_per_hour;
								$cost_name = "Video Production";
								break;
			
			case 41: 			if($task_cost) $amount_gbp = $task_cost;
								else $amount_gbp = $actual_hours * $cost_per_hour;
								$cost_name = "Sayu math::trials";
								break;
			
			case 236: 			if($task_cost) $amount_gbp = $task_cost;
								else $amount_gbp = $actual_hours * $cost_per_hour;
								$cost_name = "Sayu math::one-off";
								break;
			
			case 237: 			if($task_cost) $amount_gbp = $task_cost;
								else $amount_gbp = $actual_hours * $cost_per_hour;
								$cost_name = "Sayu math::paying clients";
								break;
										
			default:			$amount_gbp = 0.00;
								$cost_name = "";
								break;		
		}
		
		$tasks[] = array($cost_name, $client_id, $amount_gbp);	
	}
	var_dump($tasks);
	
//	return $tasks;
//}

/*

function mis_PPC() {
	global $cost_year, $cost_month, $db, $db2;
	
	$tasks = array();
	$sql = " SELECT t.task_cost, t.actual_hours, t.project_id, t.task_domain_url, td.sayu_domain_id, td.domain_url, c.sayu_user_id";
	$sql .= " FROM tasks t";
	$sql .= " LEFT JOIN tasks_domains td ON t.client_id = td.client_id";
	$sql .= " LEFT JOIN clients c ON t.client_id = c.client_id";
	$sql .= " WHERE t.project_id = 37 AND t.client_id > 0";
	$sql .= " AND YEAR(t.date_reassigned)='$cost_year'";
	$sql .= " AND MONTH(t.date_reassigned)='$cost_month'";
	$db->query($sql);
	if ($db->next_record()) { 
		do {	
			$client_id = $db->f("sayu_user_id");
			$project_id = $db->f("project_id");
			$domain_id = $db->f("sayu_domain_id");
			$domain_name = $db->f("domain_url");
			$task_domain_name = $db->f("task_domain_url");
			$task_cost = $db->f("task_cost");
			$amount_gbp = $db->f("task_cost");
			$actual_hours = $db->f("actual_hours");
			
			if ((!$domain_id || !$domain_name) && $task_domain_name) {
				$domain_name = $task_domain_name;
				$sql2 = "SELECT sayu_domain_id FROM tasks_domains WHERE domain_url='$domain_name'";
				$db2->query($sql2);
				$db2->next_record();
				if (!$domain_id) $domain_id = $db2->f("sayu_domain_id");
			}
			
			if (!$amount_gbp) {
				switch($project_id) {
					case 37: 			$amount_gbp = $actual_hours * $cost_per_hour;
										$cost_name = "Sayu PPC Management";
										break;
											
					case 53: 			$amount_gbp = $actual_hours * $cost_per_hour;
										$cost_name = "Full Account";
										break;
									
					default:			$amount_gbp = 0.00;
										$cost_name = "";
										break;		
				}
			}
			
			$tasks[] = array( "cost_name" => $cost_name,
							  "client_id" => $client_id,  	
							  "domain_id" => $domain_id,  	
							  "domain_name" => $domain_name,  	
							  "amount_gbp" => $amount_gbp);
			
		} while ($db->next_record());
	}
	return $tasks;
}
*/
echo $cost_year;

?>