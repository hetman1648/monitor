<?php
include ("./includes/common.php");
// 'type' parameters list, script answers for
$commands_list = array();
$commands_list['users_list'] = 'Return list of the monitor users.';
$commands_list['projects_list'] = 'Return projects in the monitor.';

$db1 = new DB_Sql();
$db1->Database = DATABASE_NAME;
$db1->User     = DATABASE_USER;
$db1->Password = DATABASE_PASSWORD;
$db1->Host     = DATABASE_HOST;

$type = GetParam('type');
switch($type) {
	case 'users_list':
		$users_list = getUsers();
		$titles = array();
		$lines = array();
		
		if (is_array($users_list)) {
			foreach ($users_list as $param_name => $params_arr) {
				foreach ($params_arr as $i => $value) {
					$lines[$i][] = $value;
				}
				$titles[] = $param_name;
			}
		}
		
		echoCSV($titles, $lines);
		break;
	case 'projects_list':
		$projects = getProjects();
		$titles = array();
		$lines = array();
		
		if (is_array($projects)) {
			foreach ($projects as $param_name => $params_arr) {
				foreach ($params_arr as $i => $value) {
					$lines[$i][] = $value;
				}
				$titles[] = $param_name;
			}
		}
		echoCSV($titles, $lines);
		break;
	default:
		echo "<b>Specify proper 'type' request parameter. See list below.</b>";
		echo "<br>";
		foreach ($commands_list as $command_name => $command_desc) {
			echo $command_name."&nbsp;-&nbsp;".$command_desc."<br>";
		}
}

function echoCSV($titles, $params = '') {
	//header('Content-Type: application/csv;');
	$str = implode(',', $titles);
	$str .= "\r\n";
	if (is_array($params)) {
		foreach ($params as $line) {
			$str .= implode(',', $line)."\r\n";
		}
	}
	echo $str;
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

    
    $dbresponsible_user_id->query("SELECT user_id, concat(first_name,' ',last_name) FROM users ORDER BY 2");
    while($dbresponsible_user_id->next_record()) {
    	$users['user_id'][] = $dbresponsible_user_id->f(0);
    	$users['user_name'][] = '"'.$dbresponsible_user_id->f(1).'"';
    }
    
    return $users;
}

function getProjects() {
	$projects = array();
    $dbprojects = new DB_Sql();
    $dbprojects->Database = DATABASE_NAME;
    $dbprojects->User     = DATABASE_USER;
    $dbprojects->Password = DATABASE_PASSWORD;
    $dbprojects->Host     = DATABASE_HOST;

    
    $dbprojects->query("SELECT project_id, project_title FROM projects ORDER BY 2");
    while($dbprojects->next_record()) {
    	$projects['project_id'][] = $dbprojects->f(0);
    	$projects['project_title'][] = '"'.$dbprojects->f(1).'"';
    }
    
    return $projects;
	
}

?>