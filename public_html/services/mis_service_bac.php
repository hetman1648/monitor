<?php
include_once("../includes/lib/nusoap/nusoap.php");
include_once("../includes/template.php");
include_once("../db_mysql.inc");
include_once("../includes/db_connect.php");
include_once("../includes/common_functions.php");
include_once("../includes/service_functions.php");

$db = new DB_Sql();
$db->Database = DATABASE_NAME;
$db->User     = DATABASE_USER;
$db->Password = DATABASE_PASSWORD;
$db->Host     = DATABASE_HOST;

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

$project_id = 0;

$server = new soap_server();
$_namespace = "http://www.viart.com.ua/monitor/services/MISService";
$server->configureWSDL("MISService", $_namespace);
$server->wsdl->schemaTargetNamespace = $_namespace;

$server->wsdl->addComplexType(
    'MISTask',
    'complexType',
    'struct',
    'all',
    '',
    array(
		'cost_name' => array('name' => 'cost_name', 'type' => 'xsd:string')
		, 'cost_group' => array('name' => 'cost_group', 'type' => 'xsd:string')
		, 'client_id' => array('name' => 'client_id', 'type' => 'xsd:int')
		, 'domain_id' => array('name' => 'domain_id', 'type' => 'xsd:int')
		, 'domain_name' => array('name' => 'domain_name', 'type' => 'xsd:string')
		, 'amount_gbp' => array('name' => 'amount_gbp', 'type' => 'xsd:float')
	)
); 


$server->wsdl->addComplexType(
	'MISTasksList',
	'complexType',
	'array',
	'',
	'SOAP-ENC:Array',
	array(),	
	array(
		array('ref' => 'SOAP-ENC:arrayType', 'wsdl:arrayType' => 'tns:MISTask[]')
	),
	'tns:MISTask'
);

$server->register(
	'misExport',
	array('cost_type' => 'xsd:string'),
    array('response' => 'tns:MISTasksList'),
	$_namespace
);


$HTTP_RAW_POST_DATA = isset($HTTP_RAW_POST_DATA) ? $HTTP_RAW_POST_DATA : '';
$server->service($HTTP_RAW_POST_DATA);

// Services functions
/**
 * Service. Return array of tasks by cost group on previos month
 *
 * @param string $cost_type
 * @return array
 */
function misExport($cost_type) {
	
	switch ($cost_type)
	{
		case 'SEO':				$response = mis_SEO();
								break;
								
		case 'PPC':				$response = mis_PPC();
								break;
								
		case 'Web':				$response = mis_Web();
								break;
								
		case 'Other':			$response = mis_Other();
								break;
		
		case 'SayuMath':		$response = mis_SayuMath();
								break;
								
		default:				$response = array();
								$response[0] = array('cost_name' => 'test'
													, 'client_id' => 1
													, 'domain_id' => 1
													, 'domain_name' => 'test domain'
													, 'amount_gbp' =>'100.00');
								break;
	}
	
    //return new soapval('return', 'ContestInfo', $retval, false, 'urn:MyURN');
    return $response;
}

function mis_SEO()
{
	global $cost_year, $cost_month, $db, $db2;
	
	$tasks = array();

	$sql  = "SELECT t.task_title, t.task_cost, c.sayu_user_id, t.project_id, td.sayu_domain_id, td.domain_url";
	$sql .= ",t.task_domain_url, t.task_cost";
	$sql .= " FROM tasks t LEFT JOIN tasks_domains td ON (t.client_id=td.client_id)";
	$sql .= " LEFT JOIN clients c ON (t.client_id=c.client_id)";
	$sql .= " WHERE (t.project_id=213 OR t.project_id=103 OR t.project_id=224 OR t.project_id=222)";
	$sql .= " AND YEAR(t.date_reassigned)='$cost_year'";
	$sql .= " AND MONTH(t.date_reassigned)='$cost_month'";
	$sql .= " AND ((t.task_status_id=4 OR t.completion=100) OR (t.task_status_id=9 AND responsible_user_id=38))";
	$sql .= " ORDER BY t.task_title";
	$db->query($sql);
	//echo $sql; exit;
	if ($db->next_record())
	{ 
		do 
		{	
			$client_id = $db->f("sayu_user_id");
			$project_id_seo = $db->f("project_id");
			$amount_gbp = $db->f("task_cost");
			$domain_id = $db->f("sayu_domain_id");
			$domain_name = $db->f("domain_url");
			$task_domain_name = $db->f("task_domain_url");
			$task_cost = $db->f("task_cost");
			
			//echo $project_id_seo."<br>";
			
			if ((!$domain_id || !$domain_name) && $task_domain_name)
			{
				$domain_name = $task_domain_name;
				$sql2 = "SELECT sayu_domain_id FROM tasks_domains WHERE domain_url='$domain_name'";
				$db2->query($sql2);
				$db2->next_record();
				if (!$domain_id) $domain_id = $db2->f("sayu_domain_id");
			}
			
			if (!$amount_gbp)
			{
				switch($project_id_seo)
				{
					case 213: 			if($task_cost) $amount_gbp = $task_cost;
											else $amount_gbp = 20.00;
										$cost_name = "Articles";
										break;
					
					case 103: 			if($task_cost) $amount_gbp = $task_cost;
											else $amount_gbp = 20.00;
										$cost_name = "Articles";
										break;
								
					case 224: 			$amount_gbp = 30.00;
										$cost_name = "Video Distribution";
										break;
								
					case 222: 			$amount_gbp = 60.00;
										$cost_name = "Video Production";
										break;
								
					default:			$amount_gbp = 0.00;
										$cost_name = "";
										break;		
				}
			}
			
			if (sizeof($cost_name)>0) {
			
				$tasks[] = array( 	"cost_name" => $cost_name,
									"cost_group" => "SEO",
								  	"client_id" => $client_id,  	
								  	"domain_id" => $domain_id,  	
								  	"domain_name" => $domain_name,  	
								  	"amount_gbp" => $amount_gbp);
			}
			
		} while ($db->next_record());
	}
	
	//var_dump($tasks);
	
	return $tasks;
}

function mis_PPC()
{
	global $cost_year, $cost_month, $db, $db2;
	
	$tasks = array();
	$sql  = " SELECT t.project_id, t.task_title, t.task_cost, c.sayu_user_id";
	$sql .= " FROM tasks t LEFT JOIN clients c ON (t.client_id=c.client_id)";
	$sql .= " WHERE (t.project_id=59 OR t.project_id=53)";
	$sql .= " AND YEAR(t.creation_date)='$cost_year'";
	$sql .= " AND MONTH(t.creation_date)='$cost_month'";
	$sql .= " AND (t.task_status_id=4 OR t.completion=100)";
	$sql .= " ORDER BY t.task_title";
	$db->query($sql);
	if ($db->next_record())
	{ 
		do 
		{	
			$client_id = $db->f("sayu_user_id");
			$project_id_PPC = $db->f("project_id");
			$amount_gbp = $db->f("task_cost");
			$domain_id = 0;
			$domain_name = "";
			
			if (!$amount_gbp)
			{
				switch($project_id_PPC)
				{
					case 59: 	$amount_gbp = 60.00;
								$cost_name = "Paying Client Work";
								break;
								
					case 53: 	$amount_gbp = 120.00;
								$cost_name = "Full Account";
								break;
								
					default:	$amount_gbp = 0.00;
								$cost_name = "";
								break;		
				}
			}
			
			$tasks[] = array( "cost_name" => $cost_name,
							  "cost_group" => "PPC",
							  "client_id" => $client_id,  	
							  "domain_id" => $domain_id,  	
							  "domain_name" => $domain_name,  	
							  "amount_gbp" => $amount_gbp);
			
		} while ($db->next_record());
	}
	
	//var_dump($tasks);
	
	return $tasks;
}


function mis_Web()
{
	global $cost_year, $cost_month, $db, $db2, $project_id;
	
	$projects = array();
	$tasks = array();
	
	$sql  = " SELECT p.project_id, sum(tr.spent_hours) as total_spent,";
	$sql .= " td.sayu_domain_id, td.domain_url, c.sayu_user_id";
	$sql .= ",t.task_domain_url";
	$sql .= " FROM (((time_report tr LEFT JOIN tasks t ON tr.task_id=t.task_id)";
	$sql .= " LEFT JOIN tasks_domains td ON t.client_id=td.client_id) ";
	$sql .= " LEFT JOIN projects p ON t.project_id=p.project_id) ";
	$sql .= " LEFT JOIN clients c ON t.client_id=c.client_id ";
	$sql .= " WHERE p.parent_project_id=79 AND task_type_id!=2";
	$sql .= " AND YEAR(tr.started_date)='$cost_year'";
	$sql .= " AND MONTH(tr.started_date)='$cost_month'";
	$sql .= " AND (t.task_cost IS NULL OR t.task_cost=0)";
	$sql .= " GROUP BY p.project_id";
	$db->query($sql);
	if ($db->next_record())
	{ 
		do 
		{	
			
			$domain_id = $db->f("sayu_domain_id");
			$domain_name = $db->f("domain_url");
			$task_domain_name = $db->f("task_domain_url");
					
			if ((!$domain_id || !$domain_name) && $task_domain_name) {
						
				$domain_name = $task_domain_name;
				$sql2 = "SELECT sayu_domain_id FROM tasks_domains WHERE domain_url='$domain_name'";
				$db2->query($sql2);
				$db2->next_record();
				if (!$domain_id) $domain_id = $db2->f("sayu_domain_id");
			}
					
					
			$projects[] = array("project_id" => $db->f("project_id"),
								"amount_gbp" => number_format($db->f("total_spent")*15,2),
							 	"cost_name" => "Web Services",
							  	"cost_group" => "Web Services",
							  	"client_id" => $db->f("sayu_user_id"),  	
							  	"domain_id" => $domain_id,  	
							  	"domain_name" => $domain_name);
			
		} while ($db->next_record());
	}
	
	$sql  = " SELECT p.project_id, t.task_cost,";
	$sql .= " td.sayu_domain_id, td.domain_url, c.sayu_user_id";
	$sql .= ",t.task_domain_url";
	$sql .= " FROM ((tasks t LEFT JOIN projects p ON t.project_id=p.project_id)";
	$sql .= " LEFT JOIN tasks_domains td ON t.client_id=td.client_id)";
	$sql .= " LEFT JOIN clients c ON t.client_id=c.client_id";
	$sql .= " WHERE t.is_closed=1 AND completion=100";
	$sql .= " AND task_cost>0 AND task_type_id=1 AND p.parent_project_id=79";
	$sql .= " AND YEAR(t.date_reassigned)='$cost_year'";
	$sql .= " AND MONTH(t.date_reassigned)='$cost_month'";
	$db->query($sql);
	if ($db->next_record())
	{
		do {
				$project_id = $db->f("project_id");
				$project_exist = array_filter($projects, "arr_filter_func");
				if (sizeof($project_exist)>0)
				{
					$project_keys = array_keys($project_exist);
					$project_key = $project_keys[0];
					$projects[$project_key]["amount_gbp"] += $db->f("task_cost"); 
				}
				else {
						
					$domain_id = $db->f("sayu_domain_id");
					$domain_name = $db->f("domain_url");
					$task_domain_name = $db->f("task_domain_url");
					
					if ((!$domain_id || !$domain_name) && $task_domain_name)
					{
						$domain_name = $task_domain_name;
						$sql2 = "SELECT sayu_domain_id FROM tasks_domains WHERE domain_url='$domain_name'";
						$db2->query($sql2);
						$db2->next_record();
						if (!$domain_id) $domain_id = $db2->f("sayu_domain_id");
					}
					
					$projects[] = array("project_id" => $project_id,
											"amount_gbp" => $db->f("task_cost"),
							 				"cost_name" => "Web Services",
							  				"cost_group" => "Web Services",
							  				"client_id" => $db->f("sayu_user_id"),  	
							  				"domain_id" => $domain_id,  	
							  				"domain_name" => $domain_name);
				}
		}  while ($db->next_record());
	}
	
	//var_dump($projects);
	
	return $projects;
}

function mis_Other()
{
	global $cost_year, $cost_month, $db , $db2;
	
	$tasks = array();
	$sql  = " SELECT t.hourly_charge, SUM(tr.spent_hours) AS user_spent_hours ";
	$sql .= " ,td.sayu_domain_id, td.domain_url, c.sayu_user_id";
	$sql .= ",t.task_domain_url";
	$sql .= " FROM tasks t LEFT JOIN projects p ON (p.project_id=t.project_id) ";	
	$sql .= " LEFT JOIN time_report tr ON (tr.task_id = t.task_id) ";
	$sql .= " LEFT JOIN tasks_domains td ON (t.client_id = td.client_id) ";
	$sql .= " LEFT JOIN clients c ON (t.client_id=c.client_id)";
	$sql .= " WHERE t.task_type_id!=4 AND t.hourly_charge IS NOT NULL ";
	$sql .= " AND YEAR(tr.started_date)='$cost_year'";
	$sql .= " AND MONTH(tr.started_date)='$cost_month'";
	$sql .= " AND p.parent_project_id!=79 AND t.client_id!=0";
	$sql .= " GROUP BY t.task_id, tr.user_id ";
	$db->query($sql);
	if ($db->next_record()) {
		do {
				$hourly_charge = $db->f("hourly_charge");	
				if ($hourly_charge == 1) {
						$hourly_charge = 15;
				}
				
				$domain_id = $db->f("sayu_domain_id");
				$domain_name = $db->f("domain_url");
				$task_domain_name = $db->f("task_domain_url");
					
				if ((!$domain_id || !$domain_name) && $task_domain_name)
				{
					$domain_name = $task_domain_name;
					$sql2 = "SELECT sayu_domain_id FROM tasks_domains WHERE domain_url='$domain_name'";
					$db2->query($sql2);
					$db2->next_record();
					if (!$domain_id) $domain_id = $db2->f("sayu_domain_id");
				}
				
				$tasks[] = array("cost_name" => "Other",
							 	 "cost_group" => "SEO",
							     "client_id" => $db->f("sayu_user_id"), 	
							     "domain_id" => $domain_id,  	
							     "domain_name" => $domain_name,  	
							     "amount_gbp" => number_format($db->f("user_spent_hours") * $hourly_charge,2));
							     
		} while ($db->next_record());
	} 
	
	return $tasks;
}

function mis_SayuMath()
{
	global $cost_year, $cost_month, $db , $db2;
	
	$tasks = array();
	$sql  = " SELECT t.task_id,t.task_title,t.hourly_charge, SUM(tr.spent_hours) AS user_spent_hours ";
	$sql .= " ,td.sayu_domain_id, td.domain_url, c.sayu_user_id";
	$sql .= ",t.task_domain_url, t.project_id";
	$sql .= " FROM tasks t LEFT JOIN projects p ON (p.project_id=t.project_id) ";	
	$sql .= " LEFT JOIN time_report tr ON (tr.task_id = t.task_id) ";
	$sql .= " LEFT JOIN tasks_domains td ON (t.client_id = td.client_id) ";
	$sql .= " LEFT JOIN clients c ON (t.client_id=c.client_id)";
	$sql .= " WHERE ((t.hourly_charge IS NOT NULL AND (t.project_id=236 OR t.project_id=41))";
	$sql .= " OR (t.project_id=129 OR t.project_id=39))";
	$sql .= " AND t.task_type_id!=4";
	$sql .= " AND YEAR(tr.started_date)='$cost_year'";
	$sql .= " AND MONTH(tr.started_date)='$cost_month'";
	$sql .= " GROUP BY t.task_id, tr.user_id ";
	
	$db->query($sql);
	if ($db->next_record()) {
		do {
				$hourly_charge = $db->f("hourly_charge");	
				if ($hourly_charge == 1) {
						$hourly_charge = 15;
				}
				
				
				
			//	echo $db->f("task_id")." | ".$db->f("task_title")."<br>";
				
				$domain_id = $db->f("sayu_domain_id");
				$domain_name = $db->f("domain_url");
				$task_domain_name = $db->f("task_domain_url");
				$project_id = $db->f("project_id");
				
				$amount_gbp = number_format($db->f("user_spent_hours") * $hourly_charge,2);
				
				if ($project_id == 236) $cost_name = "Sayu math::one-off";
					elseif ($project_id == 41) $cost_name = "Sayu math::trials";
						elseif ($project_id == 129) {
							
							$cost_name = "Sayu math::one-off";
							$amount_gbp = 120;
						} elseif ($project_id == 39) {
							
							$cost_name = "Sayu math::trials";
							$amount_gbp = 30;
						}
						else $cost_name = $project_id;
					
				if ((!$domain_id || !$domain_name) && $task_domain_name)
				{
					$domain_name = $task_domain_name;
					$sql2 = "SELECT sayu_domain_id FROM tasks_domains WHERE domain_url='$domain_name'";
					$db2->query($sql2);
					$db2->next_record();
					if (!$domain_id) $domain_id = $db2->f("sayu_domain_id");
				}
				
				$tasks[] = array("cost_name" => $cost_name,
							 	 "cost_group" => "PPC",
							     "client_id" => $db->f("sayu_user_id"), 	
							     "domain_id" => $domain_id,  	
							     "domain_name" => $domain_name,  	
							     "amount_gbp" => $amount_gbp);
							     
		} while ($db->next_record());
	} 
	
	//var_dump($tasks);
	
	return $tasks;
}

function arr_filter_func($arr)
{
	global $project_id;
	//mail('ultra.u@gmail.com', 'My Subject-'.$project_id, "test");
	return ($arr["project_id"] == $project_id);
}

//misExport("PPC");
?>