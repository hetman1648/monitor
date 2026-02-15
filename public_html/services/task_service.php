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

$server = new soap_server();
$_namespace = "http://www.viart.com.ua/monitor/services/TaskService";
$server->configureWSDL("TaskService", $_namespace);
$server->wsdl->schemaTargetNamespace = $_namespace;
// WSDL Initialization
$server->wsdl->addComplexType(
	'Task',
	'complexType',
	'struct',
	'all',
	'',
	array(
		'task_id' => array('name' => 'task_id', 'type' => 'xsd:int')
		, 'task_title' => array('name' => 'task_title', 'type' => 'xsd:string')
		, 'project_id' => array('name' => 'project_id', 'type' => 'xsd:int')
		, 'project_title' => array('name' => 'project_title', 'type' => 'xsd:string')
		, 'task_status_id' => array('name' => 'task_status_id', 'type' => 'xsd:int')
		, 'task_status_title' => array('name' => 'task_status_title', 'type' => 'xsd:string')
		, 'creation_date' => array('name' => 'creation_date', 'type' => 'xsd:string')
		, 'planed_date' => array('name' => 'planed_date', 'type' => 'xsd:string')
		, 'task_type_id' => array('name' => 'task_type_id', 'type' => 'xsd:int')
		, 'task_type_title' => array('name' => 'task_type_title', 'type' => 'xsd:string')
		, 'completion' => array('name' => 'completion', 'type' => 'xsd:int')
		, 'actual' => array('name' => 'actual', 'type' => 'xsd:float')
	)
);
/*
$server->wsdl->addComplexType(
	'TaskStatus',
	'complexType',
	'string',
	'choice',
	'',
	array(
		'not started' => array('name' => 'not started', 'type' => 'xsd:string')
		, 'in progress' => array('name' => 'in progress', 'type' => 'xsd:string')
		, 'waiting' => array('name' => 'waiting', 'type' => 'xsd:string')
		, 'question' => array('name' => 'question', 'type' => 'xsd:string')
		, 'answer' => array('name' => 'answer', 'type' => 'xsd:string')
		, 'done' => array('name' => 'done', 'type' => 'xsd:string')
		, 'on hold' => array('name' => 'on hold', 'type' => 'xsd:string')
		, 'reassigned' => array('name' => 'reassigned', 'type' => 'xsd:string')
		, 'found bug' => array('name' => 'found bug', 'type' => 'xsd:string')
		, 'tested' => array('name' => 'tested', 'type' => 'xsd:string')
		, 'bug resolved' => array('name' => 'bug resolved', 'type' => 'xsd:string')
	)
);

$server->wsdl->addComplexType(
	'TaskFilter',
	'complexType',
	'array',
	'sequence',
	'',
	array(
		'status' => array('name' => 'status', 'type' => 'tns:sequence')
	)
);
//*/

$server->wsdl->addComplexType(
	'TasksList',
	'complexType',
	'array',
	'',
	'SOAP-ENC:Array',
	array(),	
	array(
		array('ref' => 'SOAP-ENC:arrayType', 'wsdl:arrayType' => 'tns:Task[]')
	),
	'tns:Task'
);

// Register services
$server->register(
	'getTasksByUser'
	, array('user_name' => 'xsd:string', 'order_by' => 'xsd:string')
	, array('tasks' => 'tns:TasksList')
	, $_namespace
);

$server->register(
	'getTask'
	, array('task_id' => 'xsd:int')
	, array('task' => 'tns:Task')
	, $_namespace
);

$server->register(
	'getTasksByClientId'
	, array('client_id' => 'xsd:int', 'order_by' => 'xsd:string')
	, array('tasks' => 'tns:TasksList')
	, $_namespace
);
/*
$server->register(
	'getTasksByUser'
	, array('user_name' => 'xsd:string', 'order_by' => 'xsd:string')
	, array('tasks' => 'tns:TasksList')
	, 'urn:Tasks'
	, 'urn:Tasks#task'
	, 'rpc'
	, 'encoded'
	, 'Specify monitor User Name'
);

$server->register(
	'getTask'
	, array('task_id' => 'xsd:int')
	, array('task' => 'tns:Task')
	, 'urn:Tasks'
	, 'urn:Tasks#task'
	, 'rpc'
	, 'encoded'
	, 'Specify task id'
);

$server->register(
	'getTasksByClientId'
	, array('client_id' => 'xsd:int', 'order_by' => 'xsd:string')
	, array('tasks' => 'tns:TasksList')
	, 'urn:Tasks'
	, 'urn:Tasks#task'
	, 'rpc'
	, 'encoded'
	, 'Specify task client id'
);
//*/
// ---------------------------------------
if (isset($HTTP_RAW_POST_DATA)) {
	$server->service($HTTP_RAW_POST_DATA);
} else {
	$server->service("");
}

// Services functions
/**
 * Service. Return task
 *
 * @param integer $input
 * @return array
 */
function getTask($input) {
	$task_id = intval($input);
	$task = s_get_task_by_id($task_id);

	return $task;
}

/**
 * Service. Return array of tasks by user name
 *
 * @param array $input
 * @return array
 */
function getTasksByUser($user_name, $order_by = "title") {
	$tasks = s_get_tasks_by_user_name($user_name);
	return $tasks;
}

/**
 * Service. Return list of tasks by client ID
 *
 * @param array $input
 * @return array
 */
function getTasksByClientId($client_id, $order_by = "title") {
	// init variables
	/*
	$client_id = 0;
	if (is_array($input)) {		
		if (isset($input["client_id"])) {
			$client_id = $input["client_id"];
		}
	}
	//*/
	$tasks = s_get_tasks_by_client_id($client_id);
		
	return $tasks;	
}

?>