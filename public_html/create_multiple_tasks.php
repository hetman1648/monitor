<?php
define('FIELDS_NUM', 7);
define('MN_PATH2TASK_EDIT', "http://".getenv('SERVER_NAME').'/monitor/edit_task.php');
define('MN_PATH2INDEX', "http://".getenv('SERVER_NAME').'/');

include ("./includes/common.php");
$rows_number = 7;
$db1 = new DB_Sql();
$db1->Database = DATABASE_NAME;
$db1->User     = DATABASE_USER;
$db1->Password = DATABASE_PASSWORD;
$db1->Host     = DATABASE_HOST;

$errors = "";
$params = "";

CheckSecurity(1);
$session_now = session_id();

$temp_path  = "temp_attachments/";
$path 		= "attachments/task/";

header("Cache-Control: private"); 
header("Age: 699");


$sFileName = "create_multiple_tasks.php";
$sTemplateFileName = "create_multiple_tasks.html";

$T= new iTemplate($sAppPath, array("main" => $sTemplateFileName));
$T->set_var("FileName", $sFileName);

$sAction = GetParam("FormAction");

switch ($sAction) {
	case 'insert':
		// Get parameters to save
		$params = getParameters();
	//	var_dump($params); exit; 
		$errors = checkFields();

		if (empty($errors)) {
			saveTasks($params);
			returnBack();
		}
		else {
			// Return errors
			showErrors($errors);
		}
		break;
	case 'cancel':
		returnBack();
		break;
	default:
		$prefix = GetParam('prefix');
		SetSessionParam('create_multiple_tasks_prefix', $prefix);
		$default_project_id = GetParam('default_project_id');
}

if (!is_array($errors)) {
	$T->set_var('sFormErr', '');
}

showForm($params);
$T->parse("main", false);
echo $T->p("main");

/**
 * Change header and return browser to tasks list page
 *
 */
function returnBack() {
  $sActionFileName = "index.php?";
  $sActionFileName.= "task_status_id=".GetParam("trn_task_status_id")."&";
  $sActionFileName.= "project_id=".GetParam("trn_project_id")."&";
  $sActionFileName.= "priority_id=".GetParam("trn_priority_id")."&";
  $sActionFileName.= "task_type_id=".GetParam("trn_task_type_id")."&#tasks";

  header('Location: '.$sActionFileName);
}

/**
 * Read parameters and add it to template engine
 *
 */
function assignParameters() {
	global $T;
	$prefix = GetSessionParam('create_multiple_tasks_prefix');
	$project_id = GetParam('project_id');
	getProjects($project_id);
	
	$responsible_user_id = GetParam('responsible_user_id');
	getResponsiblePersons($responsible_user_id);
	

	$task_title = GetParam('task_title');
	$task_desc = GetParam('task_desc');
	$mcc = GetParam('mcc');
	$email = GetParam('email');
	if (is_array($task_title) && is_array($task_desc)) {
		foreach ($task_title as $i => $title) {
			$T->set_var('task_title', $title);
			$T->set_var('task_desc', $task_desc[$i]);
			
			$T->set_var('date_to_complete', GetParam('date_to_complete_' . $i));
			$T->set_var('i', $i);
			if (isset($project_id) && $project_id == 39)
			{
	  			$T->set_var('mcc', $mcc[$i]);
				$T->set_var('email', $email[$i]);
				$T->parse("for_trial", false);
	  			$T->set_var("trial_adds", "rowspan=3");
			}
			else
			{
			 	
				$T->set_var("for_trial", "");
	  			$T->set_var("trial_adds", ""); 
	  			$T->set_var("mcc", "");
	  			$T->set_var("email", "");
			}
			
			$T->parse("row", true);
		}
	} else {
		$time = time() + 86400;

		$date_to_complete = date("Y-m-d", $time);
		for($i = 0; $i < FIELDS_NUM; $i++) {
			$T->set_var('task_title', $prefix);
			$T->set_var('task_desc', '');
			$T->set_var('i', $i);
			$T->set_var('date_to_complete', $date_to_complete);
			if (isset($project_id) && $project_id == 39)
			{
	  			$T->parse("for_trial", false);
	  			$T->set_var("trial_adds", "rowspan=3");
	  			$T->set_var("mcc", "");
	  			$T->set_var("email", "");
			}
			else
			{
			 	
				$T->set_var("for_trial", "");
	  			$T->set_var("trial_adds", ""); 
	  			$T->set_var("mcc", "");
	  			$T->set_var("email", "");
			}
			$T->parse("row", true);
		}
	
	}
	$T->set_var('project_id', $project_id);
	$T->set_var('trn_task_status_id', GetParam('trn_task_status_id'));
	$T->set_var('trn_project_id', GetParam('trn_project_id'));
	$T->set_var('trn_priority_id', GetParam('trn_priority_id'));
	$T->set_var('trn_task_type_id', GetParam('trn_task_type_id'));
	$T->set_var('user_id', GetSessionParam('UserID'));	
}

function showForm() {
	assignParameters();
}

function saveTasks($params) {
	global $db;
	
	if (is_array($params['task_title'])) {
		foreach ($params['task_title'] as $i => $element) {
	  		if ($params['project_id'] == 39) {
	  			$description = "MCC:  ".$params['mcc'][$i]."\r\nEmail:  ".$params['email'][$i]."\r\n".$params['task_desc'][$i];
	  		} else {
	  			$description = $params['task_desc'][$i];
	  		}
   			add_task($params['responsible_user_id'], $params['priority_id'], $params['task_status_id'], $params['project_id'], 0,
						$params['task_title'][$i], $description, $params['planed_date'][$i], 
						$params['created_person_id'], false, $params['task_type_id'], $hash);
		}
	}
}

/**
 * Assign errors to template engine
 *
 * @param unknown_type $errors
 */
function showErrors($errors) {
	global $T;
	$error_str = '';
	foreach($errors as $error) {
		$error_str .= $error;
	}
	$T->set_var("sFormErr", $error_str);
}

function checkFields() {
	$prefix = GetSessionParam('create_multiple_tasks_prefix');
	// Check result of request parameters
	$errors = array();
	if (!strlen(GetParam("responsible_user_id"))) {
		$errors[] = 'The value in field <font color="red"><b>Responsible person</b></font> is required.<br>';
	}

	if (!strlen(GetParam("project_id"))) {
		$errors[] = 'The value in field <font color="red"><b>Project</b></font> is required.<br>';
	}
	$titles = GetParam('task_title');
	$descriptions = GetParam('task_desc');
	$emails = GetParam('email');
	$mccs = GetParam('mcc');
	
	if (is_array($titles)) {
		$counter = 0;
		foreach ($titles as $title) {
			if(strval($title) != $prefix) {
				$counter++;
			}
		}
		if ($counter == 0) {
			$errors[] = 'The value in one field <font color="red"><b>Title</b></font> at least is required except prefix \''.$prefix.'\'.<br>';
		}
	}
	else {
		$errors[] = 'The value in one field <font color="red"><b>Title</b></font> at least is required.<br>';
	}
	
	if (is_array($emails)) {
		$counter = 0;
		foreach ($emails as $email) {
			if(strval($email) != '') {
				$counter++;
			}
		}
		if ($counter == 0) {
			$errors[] = 'The value in one field <font color="red"><b>Email</b></font> at least is required.<br>';
		}
	}
	else {
		$errors[] = 'The value in one field <font color="red"><b>Email</b></font> at least is required.<br>';
	}

	if (is_array($mccs)) {
		$counter = 0;
		foreach ($mccs as $mcc) {
			if(strval($mcc) != '') {
				$counter++;
			}
		}
		if ($counter == 0) {
			$errors[] = 'The value in one field <font color="red"><b>MCC</b></font> at least is required.<br>';
		}
	}
	else {
		$errors[] = 'The value in one field <font color="red"><b>MCC</b></font> at least is required.<br>';
	}
/*
	if (is_array($descriptions)) {
		$counter = 0;
		foreach ($descriptions as $description) {
			if($description != '') {
				$counter++;
			}
		}
		if ($counter == 0) {
			$errors[] = 'The value in field <font color="red"><b>Description</b></font> at least is required.<br>';
		}
	}
	else{
		$errors[] = 'The value in field <font color="red"><b>Description</b></font> at least is required.<br>';
	}
//*/
	return $errors;
}

/**
 * Read parameters
 *
 * @return array
 */
function getParameters() {
	$prefix = GetSessionParam('create_multiple_tasks_prefix');
	$result = array();
	$project_id = stripslashes(GetParam("project_id"));
	$result['project_id'] = $project_id;
	
	$result['responsible_user_id'] = stripslashes(GetParam("responsible_user_id"));
	// Remove from array tasks with empty titles and descriptions
	$titles = GetParam('task_title');
	$descriptions = GetParam('task_desc');
	$mccs = GetParam('mcc');
	$emails = GetParam('email');
	
	$result['task_title'] = array();
	$result['task_desc'] = array();
	$result['mcc'] = array();
	$result['email'] = array();

	// Select tasks only with nonempty titles and descriptions
	for($i = 0; $i < FIELDS_NUM; $i++) {
		// If title starts from prefix mean it is empty
		$current_title = trim($titles[$i]);
		if ((strlen($titles[$i]) != 0) && ($current_title != $prefix)) {
			$result['task_title'][] = $titles[$i];
			$result['task_desc'][] = $descriptions[$i];
			if (isset($mccs[$i])) {
				$result['mcc'][] = $mccs[$i];
			} else {
				$result['mcc'][] = "";
			}
			if (isset($emails[$i])) {
				$result['email'][] = $emails[$i];
			} else {
				$result['email'][] = "";
			}
		}
	}

	// Set default values

	// Set date to last day of year
	for($i = 0; $i < FIELDS_NUM; $i++) {
		$result['planed_date'][$i] = GetParam("date_to_complete_" . $i);
	}
	// Not started status
	$result['task_status_id'] = '7';
	$result['created_person_id'] = GetSessionParam("UserID")>0 ? GetSessionParam("UserID") : (int)GetParam("user_id");
	$result['priority_id'] = '1';
	// Set task_type to 'New'
	$result['task_type_id'] = '1';
	$result['is_wish'] = '0';
	return $result;
}

/**
 * Get persons and assign to template engine
 *
 */
function getResponsiblePersons($selected_user_id = 0) {
	global $T;
	// Add first field with empty value
    $T->set_var("LBresponsible_user_id", "");
    $T->set_var("ID", "");
    $T->set_var("Value", "-please select-");
    $T->parse("LBresponsible_user_id", true);
    $dbresponsible_user_id = new DB_Sql();
    $dbresponsible_user_id->Database = DATABASE_NAME;
    $dbresponsible_user_id->User     = DATABASE_USER;
    $dbresponsible_user_id->Password = DATABASE_PASSWORD;
    $dbresponsible_user_id->Host     = DATABASE_HOST;

    
    $dbresponsible_user_id->query("SELECT user_id, concat(first_name,' ',last_name) FROM users WHERE is_viart = 1 AND is_deleted IS NULL ORDER BY 2");
    while($dbresponsible_user_id->next_record())
    {
      $T->set_var("ID", $dbresponsible_user_id->f(0));
      $T->set_var("Value", $dbresponsible_user_id->f(1));
      if($dbresponsible_user_id->f(0) == $selected_user_id)
        $T->set_var("Selected", "SELECTED" );
      else 
        $T->set_var("Selected", "");
      $T->parse("LBresponsible_user_id", true);
    }
}

/**
 * Set template values for projects to show.
 */
function getProjects($selected_project_id = 0) {
	global $T, $db;
	//$project_id = GetParam('project_id');
	// Add first field with empty value
    $T->set_var("LBproject_id", "");
    $T->set_var("ID", "");
    $T->set_var("Value", "");
    $T->parse("LBproject_id", true);
    $current_project_parent_id = -1;
    
    $sql = "SELECT project_id, project_title, parent_project_id FROM projects WHERE is_closed=0 order by 2";
    $db->query($sql);
    $options = array();
    while($db->next_record())
    {
    	if ($db->Record["project_title"])
    	{
    		$parent_project_id = intval($db->f("parent_project_id"));
    		$id = $db->f("project_id");
    		$project_title = $db->f("project_title");
    		$options[$parent_project_id][] = array($id, $project_title, "");

    		if ($parent_project_id > 0) {
    			if ($id == $selected_project_id) {
    				$current_project_parent_id = $parent_project_id;
    			}
    		}
    	}	
    }
    if (is_array($options[0])) {
    	foreach ($options[0] as $parent_project_id => $map) {
    		$T->set_var("ID", $map[0]);
    		$T->set_var("Value", $map[1]);
    		if ($map[0] == $selected_project_id || $map[0] == $current_project_parent_id) {
    			$T->set_var("Selected", "selected");
    		} else {
    			$T->set_var("Selected", "");
    		}
    		$T->parse("LBproject_id", true);
    	}
    }
    // Fullfil sub projects select
    if ($current_project_parent_id > 0 && is_array($options[$current_project_parent_id])) {
    	foreach ($options[$current_project_parent_id] as $project_map) {
    			$T->set_var("ID", $project_map[0]);
    			$T->set_var("Value", $project_map[1]);
    			if ($project_map[0] == $selected_project_id) {
    				$T->set_var("Selected", "selected");
    			} else {
    				$T->set_var("Selected", "");
    			}
    			$T->parse("LBsubproject_id", true);    		
    	}
    } else {
    	$T->set_var("LBsubproject_id", "");
    }

    $T->set_var("options_js", array2js($options, "options"));
}
?>