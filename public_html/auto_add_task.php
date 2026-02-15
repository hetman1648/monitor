<?php
/**
 * Script create new task from remote request.
 */
//http://localhost/monitor/auto_add_task.php?task_title=ViArt+Monitor+automated+task+creation&project=3&completion_date=2006-09-20&status=not+started&responsible_person=5&priority=4&type=new&description=not+too+long+description
include ("./includes/common.php");
define('COMPONENT_DIR', dirname(__FILE__).'/includes/components/');
define('AUTO_ADD_HELP_URI', 'https://monitor.sayu.co.uk/auto_add_task.php?help=1');
$old_include_path = ini_get('include_path');
ini_set('include_path', $old_include_path.PATH_SEPARATOR.COMPONENT_DIR.'PEAR/');
ini_set('include_path', $old_include_path.PATH_SEPARATOR.COMPONENT_DIR.'PEAR/');


define("ROBOTS_CHECK_PROJECT_ID", 135);

require_once('HTML/Table.php');
// If user want to view help show and exit
$isHelpRequired = GetParam('help');
if ($isHelpRequired != '') {
	showHelp();
}

// ---------------------------------------

$objs = array();
$objs['task_title'] = new Parameter('task_title', 'task_title');
$objs['project'] = new ProjectParameter('project_id', 'project');
$objs['description'] = new Parameter('task_desc', 'description');
$objs['task_domain_url'] = new Parameter('task_domain_url', 'domain_url', '', false);
$objs['status'] = new StatusParameter('task_status_id', 'status', 'not started');
$objs['responsible_person'] = new UserParameter('responsible_user_id', 'responsible_person');
$objs['completion_date'] = new DateParameter('planed_date', 'completion_date', '', false);
$objs['created_person'] = new UserParameter('created_person_id', 'created_person', 'New', false);
$objs['type'] = new TypeParameter('task_type_id', 'type', 'New', false);
$objs['priority'] = new PriorityParameter('priority_id', 'priority', 3, false);

// Get values
$errors = array();

foreach ($objs as $key => $obj) {
	$objs[$key]->getValue();
	if (!$objs[$key]->check()) {
		$errors[] = $objs[$key]->getError();
	}
}


//print_r($_GET);
//print_r($errors);
//exit;

$table = new HTML_Table();
// Tracking 
/*
$request_uri = $_SERVER["REQUEST_URI"];

if ($request_uri) {
	$sql = "INSERT INTO tasks_remote_creation_tracking (`request_uri`, `date`) ";
	$sql .= "VALUES (" . ToSQL($request_uri, TEXT). ", NOW())";
	$db->query($sql);
}
//*/
if (!empty($errors)) {
	// Show errors report
	$row = 0;
	$table->setCellContents($row, 0, 'Trying To Add New Task Failed.');
	$table->setCellAttributes($row, 0, array('class' => "FieldCaptionTD"));
	$row++;	
	$table->setCellContents($row, 0, 'Error report:');
	$table->setCellAttributes($row, 0, array('class' => "FieldCaptionTD"));
	$row++;	
	foreach ($errors as $error) {
		$table->setCellContents($row, 0, $error);
		$table->setCellAttributes($row, 0, array('class' => "DataTD"));
		$row++;
	}
	$table->setCellContents($row, 0, 'Get Help: '.'<a href="'.AUTO_ADD_HELP_URI.'">Help</a>');
	$table->setCellAttributes($row, 0, array('class' => "FieldCaptionTD"));
}
else{
	// Try to add new task
	$db_field_names = array();
	$values         = array();
	
	if ($objs['project']->request_value == ROBOTS_CHECK_PROJECT_ID) {
		$task_domain = trim(rtrim($objs['task_domain_url']->request_value));
		if ($task_domain) {				
			$task_domain2 = (strpos($task_domain, "www.") === 0) ? substr($task_domain, 4) : ("www." . $task_domain);				
			$robots = @file_get_contents("http://". $task_domain . "/robots.txt");
			if ($robots !== FALSE && strpos($robots, '<!DOCTYPE') === FALSE) {
				$objs['description']->request_value .= "\r\n\r\nROBOTS.TXT\r\n--------------\r\n" . addslashes($robots);
			} else {					
				$robots = @file_get_contents("http://" . $task_domain2 . "/robots.txt");
				if ($robots !== FALSE && strpos($robots, '<!DOCTYPE') === FALSE) {
					$objs['description']->request_value .= "\r\nROBOTS.TXT\r\n--------------\r\n" . addslashes($robots);
				}		
			}
			if (!$robots) {
				$objs['description']->request_value .= "\r\n NO ROBOTS.TXT \r\n";
			}
		}				
	}
	
	foreach ($objs as $key => $obj) {
		$db_field_names[] = '`'.$objs[$key]->db_name.'`';
		// Convert values taken from request to db values, if it needs 
		$objs[$key]->convertValue();
		//$values[] = ToSQL($objs[$key]->db_value, 'Text');
		$values[] = ToSQL(str_replace("[new_line]", "\n", $objs[$key]->db_value), 'Text');
		//$values[] = ToSQL(str_replace("'", "", $objs[$key]->db_value), 'Text');
		
	}
	
	$query_fields_str = implode(',', $db_field_names);
	$query_values_str = implode(',', $values);
	// Add default values
	$query_fields_str .= ',`creation_date`, `is_wish`';
	$query_values_str .= ',NOW(),0';

	$query = "INSERT INTO tasks (";
	$query .= $query_fields_str;
	$query .= ') VALUES (';
	$query .= $query_values_str;
	$query .= ')';
	// Send notification
	//-- determine last inserted id
	$db = new DB_Sql();
	$db->Database = DATABASE_NAME;
	$db->User     = DATABASE_USER;
	$db->Password = DATABASE_PASSWORD;
	$db->Host     = DATABASE_HOST;
	
	$db->query($query); //echo $query; exit;

	$sSQL = "SELECT LAST_INSERT_ID()";
	$db->query($sSQL);
	if ($db->next_record()) {
		$task_id = $db->f(0);
	}
	
	$sSQL = "SELECT project_title FROM projects WHERE project_id=".$objs['project']->db_value;
	$db->query($sSQL);
	if ($db->next_record()) {
		$project_title = $db->f(0);
	}
	
	$user_name = GetSessionParam("UserName");
	if (!$user_name) {
		$sSQL  = " SELECT CONCAT(first_name,' ',last_name) AS user_name ";
		$sSQL .= " FROM users WHERE user_id=".$objs['created_person']->db_value;
		$db->query($sSQL);
		if ($db->next_record()) {
			$user_name = $db->f(0);
		}
	}

	
	
	$sSQL  = " SELECT CONCAT(first_name,' ',last_name)  AS responsible_user_name ";
	$sSQL .= " FROM users WHERE user_id=".$objs['responsible_person']->db_value;
	$db->query($sSQL);
	if ($db->next_record()) {
		$responsible_user_name = $db->f(0);
	}


	$tags = array (
					"project_title"         => $project_title,
					"task_title"            => $objs['task_title']->db_value,
					"task_id"               => $task_id,
					"responsible_user_id"   => $objs['responsible_person']->db_value,
					"user_name"		        => $user_name,
					"responsible_user_name" => $responsible_user_name
	);
	send_enotification(MSG_TASK_CREATED, $tags);

	$row = 0;
	$table->setCellContents($row, 0, 'New Task Added Successfully.');
	$table->setCellAttributes($row, 0, array('class' => "FieldCaptionTD"));

}


$sFileName = "auto_add_task.php";
$sTemplateFileName = "auto_add_task.html";

$T = new iTemplate($sAppPath, array("main" => $sTemplateFileName));
$T->set_var("FileName", $sFileName);
$T->set_var("result_html", $table->toHtml());
$T->parse("main", false);
echo $T->p("main");

// ********************   Functions  ************************
/*
$objs['task_title'] = new Parameter('task_title', 'task_title', true);
$objs['project'] = new ProjectParameter('project_id', 'project', true);
$objs['description'] = new Parameter('task_desc', 'description', true);
$objs['status'] = new StatusParameter('task_status_id', 'status');
$objs['responsible_person'] = new UserParameter('responsible_user_id', 'responsible_person', true);
$objs['completion_date'] = new DateParameter('planed_date', 'completion_date');
$objs['created_person'] = new UserParameter('created_person_id', 'created_person', true);
$objs['type'] = new TypeParameter('task_type_id', 'type');
$objs['priority'] = new PriorityParameter('priority_id', 'priority');
//*/
/**
 * Show help to easy use of auto add task script
 *
 */
function showHelp() {
	$help_text = '
	<b>Create new task by remote request.</b>
	<i>Request parameters:</i>
	<i>task_title</i> is required.
	<i>project</i> is a project_id in monitor database, is required.
	<i>description</i> is optional.
	<i>domain_url</i> is optional.
	<i>status</i> is optional.	List: in progress, on hold, rejected, done, question, answer, not started, waiting.
	<i>responsible_person</i> is required and present monitor\'s user id.
	<i>completion_date</i> is optional. Proper date example: 2006-09-20.
	<i>created_person</i> is required, monitor\'s user id.
	<i>type</i> is optional. Types List: New, Correction, Periodic.
	<i>priority</i> is optional. Values: 1, 2, 3.
	
	Example:
	<a href="https://monitor.sayu.co.uk/auto_add_task.php?task_title=ViArt+Monitor+automated+task+creation&project=3&completion_date=2006-09-20&status=not+started&responsible_person=5&priority=3&type=new&description=not+too+long+description&created_person=3">Create New Task</a>
	
	To get monitor users list follow <a href="https://monitor.sayu.co.uk/get_csv.php?type=users_list">https://monitor.sayu.co.uk/get_csv.php?type=users_list</a>
	To get monitor projects list follow <a href="https://monitor.sayu.co.uk/get_csv.php?type=projects_list">https://monitor.sayu.co.uk/get_csv.php?type=projects_list</a>
	';
	
	echo nl2br($help_text);
	exit;
}

// ********************    Classes   ************************
/**
 * Parameter class. 
 *
 */
class Parameter {
	/**
	 * DB field name
	 *
	 * @var string
	 */
	var $db_name;
	/**
	 * DB field value
	 *
	 * @var string
	 */
	var $db_value;
	/**
	 * Name from request
	 *
	 * @var string
	 */
	var $request_name;
	/**
	 * Value of parameter from request
	 *
	 * @var string
	 */
	var $request_value;
	/**
	 * Set if field is is required for task creation
	 *
	 * @var boolean
	 */
	var $isObligatory;
	/**
	 * Error report, after checking
	 *
	 * @var string
	 */
	var $error;
	
	/**
	 * Default value
	 *
	 */
	var $default_value;
	
	function Parameter($db_name, $request_name, $default = '', $isObligatory = true) {
		$this->db_name = $db_name;
		$this->request_name = $request_name;
		$this->isObligatory = $isObligatory;
		$this->error = '';
		$this->db_value = '';
		$this->request_value = '';
		$this->default_value = $default;
	}
	
	/**
	 * Get value from request. If value is not specified, assign default value
	 *
	 */
	function getValue() {
		$value = GetParam($this->request_name);
		//echo $this->request_name."-".$value."\n";

		if ($value) {
			$this->request_value = $value;
		}
		else {
			$this->request_value = $this->default_value;
		}
	}
	
	/**
	 * Check if parameter is proper
	 *
	 * @return boolean
	 */
	function check() {
		if ($this->isObligatory && strlen(strval($this->request_value)) == 0) {
			$this->error = 'Parameter '.$this->request_name.' is required';
			return false;
		}
		return true;
	}
	
	/**
	 * Convert value taken from request to value proper for db
	 *
	 */
	function convertValue() {
		$this->db_value = $this->request_value;
	}
	
	/**
	 * Return error content
	 *
	 * @return string
	 */
	function getError() {
		return $this->error;
	}
}

/**
 * Class extends Parameter, present user parameter.
 *
 */
class UserParameter extends Parameter {
	/**
	 * Check if user with specified in request id exists
	 *
	 * @return boolean
	 */
	function check() {
		$result = false;
		if ($this->request_value != '') {
		    $dbuser_id = new DB_Sql();
		    $dbuser_id->Database = DATABASE_NAME;
		    $dbuser_id->User     = DATABASE_USER;
		    $dbuser_id->Password = DATABASE_PASSWORD;
		    $dbuser_id->Host     = DATABASE_HOST;
		
		    
		    $dbuser_id->query('SELECT COUNT(user_id) FROM users WHERE user_id = '.ToSQL($this->request_value, TEXT));
		    if ($dbuser_id->next_record()) {
			    if (intval($dbuser_id->f(0)) > 0) {
			    	$result = true;
			    }
		    }
		}
		elseif((!$this->isObligatory && $this->request_value == '')) { 
			$result = true;
		}	
		
		if (!$result) {
	    	$this->error = 'Parameter '.$this->request_name.' is required and has to containe proper monitor user id.';
		}
		return $result;
	}
}

/**
 * Class extends Parameter class and present project.
 *
 */
class ProjectParameter extends Parameter {
	/**
	 * Check if project with specified project id exists.
	 *
	 * @return unknown
	 */
	function check() {
		if ($this->request_value != '') {
		    $db = new DB_Sql();
		    $db->Database = DATABASE_NAME;
		    $db->User     = DATABASE_USER;
		    $db->Password = DATABASE_PASSWORD;
		    $db->Host     = DATABASE_HOST;
		
		    
		    $db->query("SELECT COUNT(project_id) FROM projects WHERE project_id = '".$this->request_value."'");
		    if($db->next_record()) {
		    	if (intval($db->f(0)) > 0) {
		    		$result = true;
		    	}
		    }
		}
		elseif((!$this->isObligatory && $this->request_value == '')) { 
			$result = true;
		}	
		
		if (!$result) {
	    	$this->error = 'Parameter '.$this->request_name.' is required and has to containe proper monitor project id.';
		}
		return $result;
	}
}

/**
 * Class extends Parameter class and present date.
 *
 */
class DateParameter extends Parameter {
	/**
	 * Check if specified date is proper
	 *
	 * @return boolean
	 */
	function check() {
		if ($this->request_value != '') {
			$str = $this->request_value;
			list($year, $month, $day) = explode('-', $str);
			// Check date
			if (!checkdate($month, $day, $year)) {
				$this->error = 'Parameter '.$this->request_name.' is not a proper date. Proper date example: 2006-09-20.';
				return false;
			}
		}
		elseif(($this->isObligatory && $this->request_value == '')) {
			$this->error = 'Parameter '.$this->request_name.' is required. Proper date example: 2006-09-20.';
			return false;
		}

		return true;
	}
}

/**
 * Class extends Parameter class and present task status 
 *
 */
class StatusParameter extends Parameter {
	/**
	 * Check if status exist. Also it convert string with status name to 
	 * its id and save in $this->db_value.
	 *
	 * @return boolean
	 */
	function check() {
		if ($this->request_value != '') {
		    $db = new DB_Sql();
		    $db->Database = DATABASE_NAME;
		    $db->User     = DATABASE_USER;
		    $db->Password = DATABASE_PASSWORD;
		    $db->Host     = DATABASE_HOST;
		
		    $db->query("SELECT status_id FROM lookup_tasks_statuses WHERE status_desc = '".$this->request_value."'");
		    if (!$db->next_record()) {
		    	$this->error = 'Parameter '.$this->request_name.' has to containe proper task status.';
		    	return false;
		    }
		    else{
		    	$this->db_value = $db->f(0);
		    	return true;
		    }
		}
		elseif((!$this->isObligatory && $this->request_value == '')) {
			return true;
		}
		return false;
	}
	
	/**
	 * Don't delete. It needs to remould method of parent class, 
	 * b/c $this->db_value defines in $this->check() method
	 *
	 */
	function convertValue() {
		
	}
}

/**
 * Class extends Parameter class and present task type
 *
 */
class TypeParameter extends Parameter {
	/**
	 * Check if task type exist. Also it convert string with type name to 
	 * its id and save in $this->db_value.
	 *
	 * @return boolean
	 */
	function check() {
		if ($this->request_value != '') {
		    $db = new DB_Sql();
		    $db->Database = DATABASE_NAME;
		    $db->User     = DATABASE_USER;
		    $db->Password = DATABASE_PASSWORD;
		    $db->Host     = DATABASE_HOST;
		
		    
		    $db->query("SELECT type_id FROM lookup_task_types WHERE type_desc = '".$this->request_value."' LIMIT 1");
		    if (!$db->next_record()) {
		    	$this->error = 'Parameter '.$this->request_name.' has to containe proper task type.';
		    	return false;
		    }
		    else{
		    	$this->db_value = $db->f(0);
		    	return true;
		    }
		}
		elseif((!$this->isObligatory && $this->request_value == '')) { 
			return true;
		}
		return false;
	}
	
	/**
	 * Don't delete. It needs to remould method of parent class, 
	 * b/c $this->db_value defines in $this->check() method
	 *
	 */
	function convertValue() {
		
	}
}
/**
 * Class extends Parameter class and present task priority/
 * Get decimal code of priority now. Chenge convertValue method 
 * and check method for other
 *
 */
class PriorityParameter extends Parameter {
	/**
	 * Check if task priority type exist.
	 *
	 * @return boolean
	 */
	function check() {
		if ($this->request_value != '') {
		    $db = new DB_Sql();
		    $db->Database = DATABASE_NAME;
		    $db->User     = DATABASE_USER;
		    $db->Password = DATABASE_PASSWORD;
		    $db->Host     = DATABASE_HOST;
		
		    
		    $db->query("SELECT * FROM lookup_priorities WHERE priority_id = '".$this->request_value."' LIMIT 1");
		    if (!$db->next_record()) {
		    	$this->error = 'Parameter '.$this->request_name.' has to containe proper priority id.';
		    	return false;
		    }
		    else{
		    	$this->db_value = $db->f('priority_id');
		    	return true;
		    }
		}
		elseif((!$this->isObligatory && $this->request_value == '')) { 
			return true;
		}
		return false;
	}
}
?>