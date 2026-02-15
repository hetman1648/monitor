<?php
// Task status defines
define('TS_IN_PROGRESS', 1);
define('TS_NOT_STARTED', 7);
// -------------------------
define('COMPONENT_DIR', dirname(__FILE__).'/includes/components/');
define('MN_PATH2INDEX', "http://".getenv('SERVER_NAME').'/');

$old_include_path = ini_get('include_path');
ini_set('include_path', $old_include_path.PATH_SEPARATOR.COMPONENT_DIR.'PEAR/');

require_once('HTML/QuickForm.php');
require_once('HTML/QuickForm/Renderer/QuickHtml.php');
require_once('HTML/QuickForm/select.php');
require_once('HTML/QuickForm/submit.php');
require_once('HTML/QuickForm/checkbox.php');
require_once('HTML/QuickForm/button.php');
require_once('HTML/QuickForm/hidden.php');
require_once('HTML/Table.php');

include ("./includes/common.php");
include_once ("./includes/viart_support.php");

$level_colors = array(
	"0" => "red",
	"1" => "blue",
	"2" => "black",
	"3" => "navy",
	"4" => "grey",
	"5" => "red",
	"6" => "green",
	"7" => "blue",
	"8" => "black"
);



$db1 = new DB_Sql();
$db1->Database = DATABASE_NAME;
$db1->User     = DATABASE_USER;
$db1->Password = DATABASE_PASSWORD;
$db1->Host     = DATABASE_HOST;

$sFileName = "reassign_multiple_tasks.php";
$sTemplateFileName = "reassign_multiple_tasks.html";

$T= new iTemplate($sAppPath, array("main" => $sTemplateFileName));
$T->set_var("FileName", $sFileName);
$T->set_var('report', '');

// Get users
$users = getUsers();

// ------------    Proceed form actions   --------------------
$sAction = GetParam("form_action");

switch ($sAction) {
	case 'insert':
		$parameters = getParameters();
		// If insert tasks successfully reported
		$task_num = reassignTasks($parameters);
		if ($task_num > 0) {
			$reportTable = new HTML_Table();
			$row = 0;
			$reportTable->setCellContents($row, 0, '<b>Reassignement Report:</b>');
			$reportTable->setCellAttributes($row, 0, array('class' => 'FieldCaptionTD'));
			$row++;
			if (isset($parameters['responsible_user_id']) && $users[$parameters['responsible_user_id']]) {
				$responsible_user_name = $users[$parameters['responsible_user_id']];
				$report_message = $task_num.' tasks successfully reassigned to '.$responsible_user_name;
				
				$reportTable->setCellContents($row, 0, $report_message);
				$reportTable->setCellAttributes(3, 1, array('class' => 'DataTD'));
				
				$T->set_var('report', $reportTable->toHtml());
			}
		}
		break;
	case 'cancel':
		returnBack();
		break;
}
// -----------------------------------------------------------
if (!isset($parameters)) {
	$parameters = array();
}

$formObj =& new HTML_QuickForm('reassign_form');
$formObj->setDefaults($parameters);
$renderer =& new HTML_QuickForm_Renderer_QuickHtml();

// --------    Responsible users element     ------------------
$usersSelect =& new HTML_QuickForm_select('responsible_user_id');

foreach ($users as $user_id => $username) {
	$usersSelect->addOption($username, $user_id);
}
$formObj->addElement($usersSelect);
// ------------------------------------------------------------

// --------    Responsible tasks element     ------------------
$task_list_checkboxes = array();
$user_id = GetSessionParam('UserID');
$tasks = getUserTasks($user_id);

$i = 0;
foreach ($tasks as $task_id => $task_title) {
	$task_list_checkboxes[] =& HTML_QuickForm::createElement('checkbox', $task_id, null, $task_title, array('id' => 'tasks_'.$i));
	$i++;
}
$formObj->addGroup($task_list_checkboxes, 'tasks_list', '', array('<br/>'));
$formObj->addGroupRule('tasks_list', 'Please check at least onetask', 'required', null, 1, 'client', true);
// ------------------------------------------------------------

// ------------------    Check all button    ------------------
$checkObj =& new HTML_QuickForm_checkbox('checkAll', null, 'Set/Unset All', array('onclick' => 'toggleAllTasks("tasks_list");'));
$formObj->addElement($checkObj);
// ------------------------------------------------------------

// ------------------    Submit button    ---------------------
$submitObj =& new HTML_QuickForm_submit('submit', 'Submit', array('onclick' => 'try { var myValidator = validate_; } catch(e) { return true; } return myValidator(this);'));
$formObj->addElement($submitObj);
// ------------------------------------------------------------
/*
// ------------------    Cancel button    ---------------------
$cancelObj =& new HTML_QuickForm_submit('cancel', 'Cancel', array('onclick' => "document.forms.reassign_form.form_action.value = 'cancel';"));
$formObj->addElement($cancelObj);
// ------------------------------------------------------------
//*/
// ------------------    Hidden elements   --------------------
// Use it for going throu task checkboxes array
$checkboxesNumObj = & new HTML_QuickForm_hidden('tasks_num', count($task_list_checkboxes), array('id' => 'tasks_num'));
$formObj->addElement($checkboxesNumObj);

$checkallCurretValueObj =& new HTML_QuickForm_hidden('checkall_current_value', 0, array('id' => 'checkall_current_value'));
$formObj->addElement($checkallCurretValueObj);

$actionObj =& new HTML_QuickForm_hidden('form_action', 'insert');
$formObj->addElement($actionObj);
// ------------------------------------------------------------

$formObj->accept($renderer);

$table = new HTML_Table();

$titleTable =& new HTML_Table(array('cellspacing' => '0', 'cellpadding' => '0', 'width' => '100%'));
$titleTable->setCellContents(0, 0, 'Reassign Multiple Tasks');
$titleTable->setCellAttributes(0, 0, array('class' => 'FormHeaderFONT', 'align' => 'center'));

$table->setCellContents(0, 0, $titleTable->toHtml());
$table->setCellAttributes(0, 0, array('class' => 'FormHeaderTD', 'colspan' => 2));

$table->setCellContents(1, 0, 'Reassign tasks to user *:');
$table->setCellContents(1, 1, $renderer->elementToHtml('responsible_user_id'));
$table->setCellAttributes(1, 0, array('class' => 'FieldCaptionTD'));
$table->setCellAttributes(1, 1, array('class' => 'DataTD'));

$table->setCellContents(2, 0, 'Tasks List *:');
$table->setCellContents(2, 1, $renderer->elementToHtml('tasks_list'));
$table->setCellAttributes(2, 0, array('class' => 'FieldCaptionTD', 'valign' => 'top'));
$table->setCellAttributes(2, 1, array('class' => 'DataTD'));

$table->setCellContents(3, 0, '');
$table->setCellContents(3, 1, $renderer->elementToHtml('checkAll'));
$table->setCellAttributes(3, 0, array('class' => 'FieldCaptionTD'));
$table->setCellAttributes(3, 1, array('class' => 'DataTD'));

$table->setCellContents(4, 0, "Please note, that you can't reassign active task, so you need to stop it first.");
$table->setCellAttributes(4, 0, array('class' => 'DataTD', 'colspan' => '2'));

$table->setCellContents(5, 0, $renderer->elementToHtml('submit')/*.$renderer->elementToHtml('cancel')*/);
$table->setCellAttributes(5, 0, array('align' => 'right', 'colspan' => 2));

$T->set_var('form',$renderer->toHtml($table->toHtml()));
$T->parse("main", false);
echo $T->p("main");

// *********************    FUNCTIONS   ***********************

/**
 * Change header and return browser to task list page
 *
 */
function returnBack() {
  $sActionFileName = MN_PATH2INDEX."index.php";
/*  
  $sActionFileName.= "task_status_id=".GetParam("trn_task_status_id")."&";
  $sActionFileName.= "project_id=".GetParam("trn_project_id")."&";
  $sActionFileName.= "priority_id=".GetParam("trn_priority_id")."&";
  $sActionFileName.= "task_type_id=".GetParam("trn_task_type_id")."&#tasks";
//*/
  header('Location: '.$sActionFileName);
}

/**
 * Reassign tasks to selected users
 *
 * @param arary $parameters
 * @return integer 0 if nothing added, or number of added tasks
 */
function reassignTasks($parameters) {

	global $db;
	
	$responsible_user_id = $parameters['responsible_user_id'];
	$task_ids = $parameters['task_ids'];
	
	// Generate string with task ids to reassign other user
	$tasks_arr = array();
	$identity_arr = array();
	if (is_array($task_ids)) {
		foreach ($task_ids as $task_id) {
			$tasks_arr[] = 'task_id='.$task_id;
			$identity_arr[] = 'identity_id='.$task_id;
		}
		$where_ids = implode(' OR ', $tasks_arr);
		$where_identity_ids = implode(' OR ', $identity_arr);
	}
	
	if (isset($responsible_user_id)) {
		if (is_array($task_ids) && !empty($task_ids)) {
			// Update tasks info
			
			foreach($task_ids as $r_task_id) {
				update_task($r_task_id, array("responsible_user_id"=>$responsible_user_id));
			}
			
			// Take messages, which are last,  for tasks and create new messages by 
			// changing responsible_user_id, time and user_id
			$insert_rows = array();
			foreach ($task_ids as $task_id) {
				$query = 
						'SELECT * FROM messages 
						WHERE identity_type=\'task\' 
						AND identity_id='.$task_id.' ORDER BY message_date desc LIMIT 1';
	
			    $db->query($query);
			    
			    $message = '';
			    
			    if($db->next_record()) {
			    	// Messages array, keys are task_ids
			    	$message = $db->f('message');
			    	$status_id = $db->f('status_id');
			    }
			    else{
			    	// Get task description if messages for task don't exist
			    	$sqlQuery = 'SELECT task_status_id, task_desc FROM tasks WHERE task_id = '.$task_id;
			    	$db->query($sqlQuery);
			    	if($db->next_record()) {
			    		$message = $db->f('task_desc');
			    	}
			    	// Set status_id, define as task status
			    	//$status_id = TS_NOT_STARTED;
			    	$status_id = $db->f('task_status_id');
			    }
			    // Add > symbol before each line.
			    $message_str = addslashes("\r\n\r\n>".preg_replace("/\r\n/","\r\n>",$message));
			    // Create insert row for taken message
			    $values_str =
							    "(".
							    "NOW()," .
							    ToSQL(GetSessionParam("UserID"), "Text") . "," .
							    "$task_id," .
							    "'task'," .
							    ToSQL($message_str, 'Text')."," .
							    $responsible_user_id."," .
							    $status_id.
							    ")";


				//$values_str = $insert_rows[$task_id];
		
				$sql_insert_messages = "INSERT INTO messages (" .
										"`message_date`," .
										"`user_id`," .
										"`identity_id`," .
										"`identity_type`," .
										"`message`,".
										"`responsible_user_id`," .
										"`status_id`) VALUES " .
										$values_str;
				$db->query($sql_insert_messages);

				// get new message index
				$sql = " SELECT LAST_INSERT_ID()";
				$db->query($sql);
				$db->next_record();
				$message_id = $db->f(0);
				// Send notification to administrative staff
			
				//-- extracting task information
				$sql = "SELECT t.created_person_id, t.task_title, p.project_title, ";
				$sql .= " t.responsible_user_id, lts.status_caption, ";
				$sql .= " CONCAT(u.first_name,' ',u.last_name) AS created_user_name, ";
				$sql .= " CONCAT(ur.first_name,' ',ur.last_name) AS responsible_user_name ";
				$sql .= " FROM tasks t ";
				$sql .= " LEFT JOIN projects p ON t.project_id = p.project_id ";
				$sql .= " LEFT JOIN users u ON t.created_person_id = u.user_id ";
				$sql .= " LEFT JOIN users ur ON t.responsible_user_id = ur.user_id ";
				$sql .= " LEFT JOIN lookup_tasks_statuses lts ON t.task_status_id = lts.status_id ";
				$sql .= " WHERE t.task_id = " . $task_id;
				$db->query($sql);
	
				if($db->next_record()) {
					//-- prepare parameters for the message
					$tags = array(
								"message"                	=> stripslashes(process_message($message, $message_id, "message")),
								"privilege_id"           	=> getSessionParam("privilege_id"),
								"task_id"                	=> $task_id,
								"task_title"             	=> $db->f("task_title"),
								"project_title"          	=> $db->f("project_title"),
								"responsible_user_id"    	=> $db->f("responsible_user_id"),
								"responsible_user_name"    	=> $db->f("responsible_user_name"),
								"user_name" 				=> GetSessionParam("UserName"),
								"task_status"				=> $db->f("status_caption")
					);
		    	}
		    	send_enotification(MSG_MESSAGE_RECEIVED, $tags);
	  		}
	  		
			add_viart_support_message($task_id, $message_id, GetSessionParam("UserID"), $responsible_user_id, $message_str);	  		
	  		
	    	return count($task_ids);
		}
	}
	return 0;
}

/**
 * Read submited form  parameters
 *
 * @return array
 */
function getParameters() {
	$params = array();
	$params['responsible_user_id'] = GetParam('responsible_user_id');
	$task_ids = GetParam('tasks_list');
	if (is_array($task_ids)) {
		foreach ($task_ids as $task_id => $is_selected) {
			if ($is_selected == true) {
				$params['task_ids'][] = $task_id;
			}
		}
	}
	return $params;
}

/**
 * Return user's tasks which are not in progress and unclosed
 *
 * @param integer $user_id
 * @return array
 */
function getUserTasks($user_id) {
	$tasks = array();
    $db = new DB_Sql();
    $db->Database = DATABASE_NAME;
    $db->User     = DATABASE_USER;
    $db->Password = DATABASE_PASSWORD;
    $db->Host     = DATABASE_HOST;

    $where = '';
    $where[] = ' is_closed = 0 ';
    $where[] = ' task_status_id <> '.TS_IN_PROGRESS.' ';
    if (intval($user_id) > 0) {
    	$where[] = ' responsible_user_id = '.$user_id.' ';
    }
    $where_str = implode('&&', $where);

    $sql = "SELECT *, t.creation_date AS cdate, t.planed_date AS pdate, DATE_FORMAT(t.creation_date, '%D %b %Y') AS creation_date, "
        . "DATE_FORMAT(t.planed_date, '%D %b %Y') AS planed_date, "
        . "UNIX_TIMESTAMP(t.creation_date) AS cdate_nix, UNIX_TIMESTAMP(t.planed_date) AS pdate_nix,"
        . "t.created_person_id AS tasks_created_person_id, "      // created_person_id from tasks;        
        . "IF(t.task_status_id=2, 1, 0) AS sorter " //Tasks with status "On Hold" will be at the end of query
        . "FROM tasks AS t, projects AS p, lookup_task_types AS lt, lookup_tasks_statuses AS ls "
        . "WHERE t.project_id = p.project_id AND t.task_type_id = lt.type_id AND t.task_status_id = ls.status_id "
        . "AND t.is_wish = 0 AND t.responsible_user_id = " . $user_id
        . " AND t.is_closed = 0 AND t.task_status_id!=1 ORDER BY sorter, t.priority_id, t.project_id ";
    $db->query($sql);
    while($db->next_record()) {
    	$task_title = $db->Record["task_title"];
    	$task_id = $db->Record["task_id"];
    	$tasks[$task_id] = $task_title;
    }
    
    return $tasks;
}

/**
 * Return viart users.
 *
 * @return array
 */
function getUsers() {
	$users = array();
    $dbresponsible_user_id = new DB_Sql();
    $dbresponsible_user_id->Database = DATABASE_NAME;
    $dbresponsible_user_id->User     = DATABASE_USER;
    $dbresponsible_user_id->Password = DATABASE_PASSWORD;
    $dbresponsible_user_id->Host     = DATABASE_HOST;

    
    $dbresponsible_user_id->query("SELECT user_id, concat(first_name,' ',last_name) FROM users WHERE is_viart = 1 AND is_deleted IS NULL ORDER BY 2");
    while($dbresponsible_user_id->next_record()) {
    	$users[$dbresponsible_user_id->f(0)] = $dbresponsible_user_id->f(1);
    }
    
    return $users;
}

?>