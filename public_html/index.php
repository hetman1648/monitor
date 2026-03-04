<?php

include("./includes/common.php");
include("./includes/date_functions.php");

$db2 = new DB_Sql;
$db2->Database = DATABASE_NAME;
$db2->User     = DATABASE_USER;
$db2->Password = DATABASE_PASSWORD;
$db2->Host     = DATABASE_HOST;

CheckSecurity(1);

/**
 * Escape task title for use inside a single-quoted JavaScript string (e.g. in onclick).
 * Prevents \u from being parsed as start of \uXXXX unicode escape.
 */
function escape_task_title_for_js($title) {
    $t = isset($title) ? $title : '';
    // Remove control chars that can cause "Invalid or unexpected token" in JS (except \n \r \t which we escape)
    $t = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $t);
    $t = addcslashes($t, "\\'\n\r\t");
    // Escape double quote for HTML attribute (onclick="...") so title with " doesn't break the attribute
    $t = str_replace('"', '&quot;', $t);
    return $t;
}

if (GetParam('close_project_id')){
    unset($_SESSION["session_perms"]['projects'][GetParam('close_project_id')]);
}

if (GetParam('open_project_id')){
    $_SESSION["session_perms"]['projects'][GetParam('open_project_id')]=1;
}

$session_user_id = GetSessionParam("UserID");
$task_id    = Getparam("task_id");
$task_ids   = Getparam("task_ids");
$action     = GetParam("action");
$hide_id    = GetParam("hide_id");
$hide_all   = GetParam("hide_all");
$sort       = GetParam("sort");
$completion = GetParam("completion");

if ($sort=='') {
    $sort=@$_SESSION["session_perms"]['sort'];
} else {
    $_SESSION["session_perms"]['sort']=$sort;
}

// Actions - get task title for flash messages
$task_title = 'Task';
if ($task_id && is_numeric($task_id) && in_array($action, array('close', 'start', 'stop'))) {
    $db->query("SELECT task_title FROM tasks WHERE task_id = " . ToSQL($task_id, "integer"));
    if ($db->next_record()) {
        $task_title = $db->f("task_title");
    }
}

switch ($action) {
    case "close":
        if ($task_id && is_numeric($task_id)) {
            // Set flash message BEFORE calling close_task (which can redirect/exit)
            $_SESSION['flash_message'] = array(
                'type' => 'success',
                'text' => 'Task "' . $task_title . '" has been closed.',
                'task_id' => $task_id
            );
            close_task($task_id, ""); // Empty return_page to prevent internal redirect
            header("Location: index.php");
            exit;
        } elseif (strlen($task_ids)) {
            close_tasks($task_ids, "index.php");
        }
        break;
    case "start":
        if ($task_id && is_numeric($task_id)) {
            // Set flash message BEFORE calling start_task
            $_SESSION['flash_message'] = array(
                'type' => 'success',
                'text' => 'Started working on "' . $task_title . '".'
            );
            start_task($task_id, $completion, ""); // Empty return_page to prevent internal redirect
            header("Location: index.php");
            exit;
        }
        break;
    case "stop":
        if ($task_id && is_numeric($task_id)) {
            // Set flash message BEFORE calling stop_task
            $_SESSION['flash_message'] = array(
                'type' => 'success',
                'text' => 'Stopped working on "' . $task_title . '" (' . intval($completion) . '% complete).',
                'task_id' => $task_id
            );
            stop_task($task_id, $completion, ""); // Empty return_page to prevent internal redirect
            header("Location: index.php");
            exit;
        }
        break;
    case "assign":
        if ($task_id && is_numeric($task_id)) {
            assign_to_myself_task($task_id);
        }
        break;
}

$sql = "SELECT * FROM lookup_users_privileges WHERE privilege_id = ".ToSQL(GetSessionParam("privilege_id"),"integer");
$db->query($sql);
$customer = false;
if ($db->next_record()) {
    $customer = $db->Record["PERM_OWN_TASKS_ONLY"];
}

// Get user settings
$view_all_tasks = has_permission("PERM_VIEW_ALL_TASKS");
$approve_vacations = ($session_user_id == 3);
$show_users_list = false;
$show_projects_list = false;
$sql = " SELECT show_users_list, show_projects_list FROM users WHERE user_id=".ToSQL($session_user_id, "integer");
$db->query($sql);
if ($db->next_record()) {
    $show_projects_list = $db->f("show_projects_list");
    $show_users_list = $db->f("show_users_list");
}

// Load pending vacation approvals
$pending_vacations = array();
if ($approve_vacations) {
    $sql2 = "SELECT d.*, u.first_name, u.last_name, r.reason_name 
             FROM days_off d 
             INNER JOIN users u ON u.user_id = d.user_id 
             LEFT JOIN reasons r ON r.reason_id = d.reason_id 
             WHERE d.is_approved='0' AND d.is_declined='0'
             ORDER BY d.start_date";
    $db2->query($sql2);
    while ($db2->next_record()) {
        $pending_vacations[] = array(
            'period_id' => $db2->f("period_id"),
            'user_name' => $db2->f("first_name") . ' ' . $db2->f("last_name"),
            'title' => $db2->f("period_title"),
            'reason' => $db2->f("reason_name"),
            'is_paid' => $db2->f("is_paid"),
            'start_date' => $db2->f("start_date"),
            'end_date' => $db2->f("end_date"),
            'total_days' => $db2->f("total_days"),
            'notes' => $db2->f("notes")
        );
    }
}

// Load current task (if any)
$current_task = null;
$sql = "SELECT t.*, p.project_title, lt.type_desc, ls.status_desc
        FROM tasks t
        INNER JOIN projects p ON p.project_id = t.project_id
        INNER JOIN lookup_task_types lt ON lt.type_id = t.task_type_id
        INNER JOIN lookup_tasks_statuses ls ON ls.status_id = t.task_status_id
        WHERE t.responsible_user_id = " . $session_user_id . "
        AND t.task_status_id = 1
        AND t.is_closed = 0
        LIMIT 1";
$db->query($sql);
if ($db->next_record()) {
    $current_task = array(
        'task_id' => $db->f("task_id"),
        'task_title' => $db->f("task_title"),
        'project_title' => $db->f("project_title"),
        'priority_id' => $db->f("priority_id"),
        'type_desc' => $db->f("type_desc"),
        'planed_date' => $db->f("planed_date"),
        'estimated_hours' => $db->f("estimated_hours"),
        'actual_hours' => to_hours($db->f("actual_hours")),
        'completion' => $db->f("completion"),
        'is_periodic' => ((int)$db->f("task_type_id") === 3) || $db->f("is_periodic")
    );
}

// Load projects with open tasks (only if they have tasks from the last 24 months)
$projects_list = array();
$sql = "SELECT p.project_id, p.project_title, COUNT(t.task_id) AS open_tasks
        FROM projects p
        INNER JOIN tasks t ON t.project_id = p.project_id
        WHERE t.is_closed = 0 AND t.is_wish = 0
        AND t.creation_date >= DATE_SUB(NOW(), INTERVAL 24 MONTH)
        GROUP BY p.project_id
        HAVING open_tasks > 0
        ORDER BY p.project_title";
$db->query($sql);
while ($db->next_record()) {
    $projects_list[] = array(
        'project_id' => $db->f("project_id"),
        'project_title' => $db->f("project_title"),
        'open_tasks' => $db->f("open_tasks")
    );
}

// Load MY projects (projects where current user has open tasks)
$my_projects_list = array();
$sql = "SELECT p.project_id, p.project_title, COUNT(t.task_id) AS my_tasks
        FROM projects p
        INNER JOIN tasks t ON t.project_id = p.project_id
        WHERE t.is_closed = 0 AND t.is_wish = 0
        AND t.responsible_user_id = " . ToSQL($session_user_id, "integer") . "
        GROUP BY p.project_id
        HAVING my_tasks > 0
        ORDER BY my_tasks DESC, p.project_title";
$db->query($sql);
while ($db->next_record()) {
    $my_projects_list[] = array(
        'project_id' => $db->f("project_id"),
        'project_title' => $db->f("project_title"),
        'open_tasks' => $db->f("my_tasks")
    );
}

// Load today's time log grouped by project
$today_time_log = array();
$today_total_hours = 0;

// Completed time reports for today
$sql = "SELECT p.project_id, p.project_title, SUM(tr.spent_hours) AS total_hours
        FROM time_report tr
        INNER JOIN tasks t ON t.task_id = tr.task_id
        INNER JOIN projects p ON p.project_id = t.project_id
        WHERE tr.user_id = " . ToSQL($session_user_id, "integer") . "
        AND DATE(tr.started_date) = CURDATE()
        GROUP BY p.project_id
        ORDER BY total_hours DESC";
$db->query($sql);
while ($db->next_record()) {
    $pid = $db->f("project_id");
    $hours = floatval($db->f("total_hours"));
    $today_time_log[$pid] = array(
        'project_id' => $pid,
        'project_title' => $db->f("project_title"),
        'hours' => $hours
    );
    $today_total_hours += $hours;
}

// Add time from currently running tasks (status_id = 1, started_time is set)
$sql = "SELECT t.task_id, t.project_id, p.project_title,
        UNIX_TIMESTAMP(t.started_time) AS started_stamp
        FROM tasks t
        INNER JOIN projects p ON p.project_id = t.project_id
        WHERE t.responsible_user_id = " . ToSQL($session_user_id, "integer") . "
        AND t.task_status_id = 1
        AND t.started_time IS NOT NULL
        AND t.is_closed = 0";
$db->query($sql);
$running_tasks_data = array();
while ($db->next_record()) {
    $pid = $db->f("project_id");
    $started_stamp = intval($db->f("started_stamp"));
    $running_hours = (time() - $started_stamp) / 3600;
    if ($running_hours < 0) $running_hours = 0;

    $running_tasks_data[] = array(
        'project_id' => $pid,
        'project_title' => $db->f("project_title"),
        'hours' => $running_hours
    );

    if (isset($today_time_log[$pid])) {
        $today_time_log[$pid]['hours'] += $running_hours;
        $today_time_log[$pid]['is_running'] = true;
    } else {
        $today_time_log[$pid] = array(
            'project_id' => $pid,
            'project_title' => $db->f("project_title"),
            'hours' => $running_hours,
            'is_running' => true
        );
    }
    $today_total_hours += $running_hours;
}

// Sort by hours descending
uasort($today_time_log, function($a, $b) {
    return ($b['hours'] > $a['hours']) ? 1 : (($b['hours'] < $a['hours']) ? -1 : 0);
});

// Load periodic tasks (task_type_id = 3)
$periodic_tasks = array();
$sql = "SELECT t.*, p.project_title, lt.type_desc, ls.status_desc
        FROM tasks t
        INNER JOIN projects p ON p.project_id = t.project_id
        INNER JOIN lookup_task_types lt ON lt.type_id = t.task_type_id
        INNER JOIN lookup_tasks_statuses ls ON ls.status_id = t.task_status_id
        WHERE t.responsible_user_id = " . $session_user_id . "
        AND t.is_closed = 0
        AND t.task_type_id = 3
        ORDER BY t.task_title";
$db->query($sql);
while ($db->next_record()) {
    $periodic_tasks[] = array(
        'task_id' => $db->f("task_id"),
        'task_title' => $db->f("task_title"),
        'status_id' => $db->f("task_status_id"),
        'actual_hours' => to_hours($db->f("actual_hours")),
        'completion' => $db->f("completion")
    );
}

// Load my tasks (non-periodic)
$my_tasks = array();
$sql = "SELECT t.*, p.project_title, lt.type_desc, ls.status_desc,
        DATE_FORMAT(t.creation_date, '%d %b %y') AS creation_date_fmt,
        DATE_FORMAT(t.date_reassigned, '%d %b') AS reassigned_date_fmt,
        DATE_FORMAT(t.planed_date, '%d %b') AS planed_date_fmt,
        COALESCE(t.modified_date, t.creation_date) AS last_modified_raw,
        DATE_FORMAT(COALESCE(t.modified_date, t.creation_date), '%d %b %y') AS last_modified_fmt
        FROM tasks t
        INNER JOIN projects p ON p.project_id = t.project_id
        INNER JOIN lookup_task_types lt ON lt.type_id = t.task_type_id
        INNER JOIN lookup_tasks_statuses ls ON ls.status_id = t.task_status_id
        WHERE t.responsible_user_id = " . $session_user_id . "
        AND t.is_closed = 0
        AND t.is_wish = 0
        AND t.task_type_id != 3
        ORDER BY
        CASE
            WHEN t.task_status_id IN (7, 5, 6, 0) THEN 0
            WHEN t.task_status_id IN (1, 11) THEN 1
            WHEN t.task_status_id IN (2, 8, 9, 10) THEN 2
            WHEN t.task_status_id IN (4, 3) THEN 3
            ELSE 4
        END,
        t.priority_id,
        t.project_id";
$db->query($sql);
while ($db->next_record()) {
    $my_tasks[] = array(
        'task_id' => $db->f("task_id"),
        'task_title' => $db->f("task_title"),
        'project_id' => $db->f("project_id"),
        'project_title' => $db->f("project_title"),
        'priority_id' => $db->f("priority_id"),
        'status_id' => $db->f("task_status_id"),
        'status_desc' => $db->f("status_desc"),
        'type_desc' => $db->f("type_desc"),
        'estimated_hours' => $db->f("estimated_hours"),
        'actual_hours' => to_hours($db->f("actual_hours")),
        'completion' => $db->f("completion"),
        'creation_date' => $db->f("creation_date_fmt"),
        'reassigned_date' => $db->f("reassigned_date_fmt"),
        'planed_date' => $db->f("planed_date_fmt"),
        'last_modified' => $db->f("last_modified_fmt"),
        'last_modified_raw' => $db->f("last_modified_raw"),
        'is_periodic' => $db->f("is_periodic"),
        'is_overdue' => ($db->f("planed_date") != '0000-00-00' && $db->f("planed_date") != '' && strtotime($db->f("planed_date")) < time())
    );
}

// Group my tasks by project for By Project view
$my_tasks_by_project = array();
foreach ($my_tasks as $t) {
    $pid = $t['project_id'];
    if (!isset($my_tasks_by_project[$pid])) {
        $my_tasks_by_project[$pid] = array('title' => $t['project_title'], 'id' => $pid, 'tasks' => array());
    }
    $my_tasks_by_project[$pid]['tasks'][] = $t;
}

// Group my tasks into kanban columns for By Status view
$status_kanban = array(
    'new' => array('title' => 'NEW', 'statuses' => array(7, 5, 6, 0), 'primary_status' => 7, 'color_class' => 'kb-col-new', 'tasks' => array()),
    'progress' => array('title' => 'IN PROGRESS', 'statuses' => array(1, 11), 'primary_status' => 1, 'color_class' => 'kb-col-progress', 'tasks' => array()),
    'hold' => array('title' => 'ON HOLD / WAITING', 'statuses' => array(2, 8, 9, 10), 'primary_status' => 2, 'color_class' => 'kb-col-hold', 'tasks' => array()),
    'done' => array('title' => 'DONE', 'statuses' => array(4, 3), 'primary_status' => 4, 'color_class' => 'kb-col-done', 'tasks' => array()),
);
foreach ($my_tasks as $t) {
    $placed = false;
    foreach ($status_kanban as $key => &$col) {
        if (in_array($t['status_id'], $col['statuses'])) {
            $col['tasks'][] = $t;
            $placed = true;
            break;
        }
    }
    unset($col);
    if (!$placed) {
        $status_kanban['new']['tasks'][] = $t;
    }
}

// Load birthdays
$birthdays = array();
$sql = "SELECT MONTH(birth_date) as bMon, DAYOFMONTH(birth_date) as bDay, CONCAT(first_name,' ',last_name) AS user_name FROM users WHERE is_deleted IS NULL";
$db->query($sql);
while ($db->next_record()) {
    if (($db->f("bMon") == date("m")) && ($db->f("bDay") == date("d"))) {
        $birthdays[] = $db->f("user_name");
    }
}

// Load today's national holidays
$todays_holidays = array();
$today = date('Y-m-d');

// Ukrainian holidays
$sql = "SELECT holiday_title FROM national_holidays WHERE holiday_date = " . ToSQL($today, "text");
$db->query($sql);
while ($db->next_record()) {
    $todays_holidays[] = array('name' => $db->f("holiday_title"), 'region' => 'Ukraine');
}

// English holidays
$sql = "SELECT holiday_title FROM english_holidays WHERE holiday_date = " . ToSQL($today, "text");
$db->query($sql);
while ($db->next_record()) {
    $todays_holidays[] = array('name' => $db->f("holiday_title"), 'region' => 'UK');
}

// Handle hiding upcoming holidays notification
$hide_holiday_id = GetParam("hide_holiday_id");
if ($hide_holiday_id) {
    // Store hidden holiday IDs in session
    if (!isset($_SESSION['hidden_holidays'])) {
        $_SESSION['hidden_holidays'] = array();
    }
    $_SESSION['hidden_holidays'][$hide_holiday_id] = true;
}

$hide_all_holidays = GetParam("hide_all_holidays");
if ($hide_all_holidays) {
    $_SESSION['hidden_holidays_until'] = date('Y-m-d', strtotime('+1 day'));
}

// Load upcoming people's holidays (vacations starting in next 7 days)
$upcoming_holidays = array();
$hidden_holidays = isset($_SESSION['hidden_holidays']) ? $_SESSION['hidden_holidays'] : array();
$hidden_until = isset($_SESSION['hidden_holidays_until']) ? $_SESSION['hidden_holidays_until'] : null;

// Reset hidden holidays if a new day
if ($hidden_until && $hidden_until <= $today) {
    $_SESSION['hidden_holidays'] = array();
    $_SESSION['hidden_holidays_until'] = null;
    $hidden_holidays = array();
    $hidden_until = null;
}

if (!$hidden_until) {
    $sql = "SELECT d.period_id, d.start_date, d.end_date, d.period_title, 
            CONCAT(u.first_name, ' ', u.last_name) AS user_name, r.reason_name
            FROM days_off d
            INNER JOIN users u ON u.user_id = d.user_id
            LEFT JOIN reasons r ON r.reason_id = d.reason_id
            WHERE d.start_date BETWEEN DATE(NOW()) AND DATE_ADD(DATE(NOW()), INTERVAL 7 DAY)
            AND d.is_approved = 1
            AND d.is_paid = 0
            ORDER BY d.start_date ASC";
    $db->query($sql);
    while ($db->next_record()) {
        $period_id = $db->f("period_id");
        if (!isset($hidden_holidays[$period_id])) {
            $upcoming_holidays[] = array(
                'period_id' => $period_id,
                'user_name' => $db->f("user_name"),
                'start_date' => date('j M', strtotime($db->f("start_date"))),
                'end_date' => date('j M', strtotime($db->f("end_date"))),
                'reason' => $db->f("reason_name") ?: $db->f("period_title"),
                'is_today' => ($db->f("start_date") == $today)
            );
        }
    }
}

// Load reminders
$reminders = array();
if ((GetSessionParam("privilege_id")==4) || (GetSessionParam("privilege_id")==3)) {
    if ($hide_id) {
        $sql_hide = 'UPDATE reminders SET is_shown=0 WHERE reminder_id='.ToSQL($hide_id,"integer");
        $db->query($sql_hide);
    }
    if ($hide_all) {
        $sql_hide = 'UPDATE reminders SET is_shown=0 WHERE user_id='.GetSessionParam('UserID');
        $db->query($sql_hide);
    }
    
    $db->query('SELECT @user_id := '.GetSessionParam("UserID"));
    $db->query('SELECT reminder_id, event FROM reminders WHERE user_id = @user_id AND is_shown=1 ORDER BY reminder_id DESC');
    while ($db->next_record()) {
        $reminders[] = array(
            'id' => $db->f("reminder_id"),
            'event' => $db->f("event")
        );
    }
}

// Load team members
$team_members = array();

// Get active tasks for each user
$active_tasks = array();
$sql = "SELECT u.user_id, t.task_title FROM users u 
        INNER JOIN tasks t ON (t.responsible_user_id = u.user_id AND t.is_closed = 0 AND t.is_wish = 0 AND t.task_status_id = 1) 
        ORDER BY u.user_id";
$db->query($sql);
while ($db->next_record()) {
    $active_tasks[$db->f("user_id")] = $db->f("task_title");
}

// Get users with their status
$sql = "SELECT CONCAT(u.first_name, ' ', u.last_name) AS user_name, u.user_id, u.is_viart,
        COUNT(t.task_id) AS opened_tasks,
        IF(MIN(t.task_status_id) = 1, 1, 0) AS is_online,
        r.reason_name,
        IF(dof.end_date, dof.end_date, 0) AS end_date
        FROM users u
        LEFT JOIN tasks t ON (t.responsible_user_id = u.user_id AND t.is_closed = 0 AND t.is_wish = 0)
        LEFT JOIN days_off dof ON (dof.user_id = u.user_id AND dof.start_date <= DATE(NOW()) AND dof.is_paid = 0 AND dof.end_date >= DATE(NOW()))
        LEFT JOIN reasons r ON (r.reason_id = dof.reason_id)
        WHERE u.is_deleted IS NULL
        GROUP BY u.user_id
        ORDER BY u.first_name, u.last_name";
$db->query($sql);
while ($db->next_record()) {
    $user_id = $db->f("user_id");
    $status = 'offline';
    $status_text = '';
    
    if ($db->f("is_online")) {
        $status = 'online';
        $status_text = 'online';
    } elseif ($db->f("end_date")) {
        $status = 'away';
        $status_text = $db->f("reason_name");
    }
    
    $active_task = isset($active_tasks[$user_id]) ? $active_tasks[$user_id] : '';
    
    $team_members[] = array(
        'user_id' => $user_id,
        'user_name' => $db->f("user_name"),
        'is_viart' => $db->f("is_viart"),
        'opened_tasks' => $db->f("opened_tasks"),
        'status' => $status,
        'status_text' => $status_text,
        'active_task' => $active_task
    );
}

$user_name = GetSessionParam("UserName");
$is_manager = is_manager($session_user_id);

// Priority colors
$priority_colors = array(
    1 => '#e53e3e', // High - red
    2 => '#dd6b20', // Medium-high - orange
    3 => '#d69e2e', // Medium - yellow
    4 => '#38a169', // Normal - green
    5 => '#718096'  // Low - gray
);

// Status colors
$status_colors = array(
    1 => '#38a169', // In Progress - green
    2 => '#718096', // On Hold - gray
    3 => '#3182ce', // Not Started - blue
    4 => '#805ad5', // Other
);

// Status options for context menu
$ctx_statuses = array();
$sql = "SELECT status_id, status_desc FROM lookup_tasks_statuses ORDER BY status_id";
$db->query($sql);
while ($db->next_record()) {
    $ctx_statuses[] = array('id' => (int)$db->f("status_id"), 'name' => $db->f("status_desc"));
}

// User options for context menu (all active users)
$ctx_users = array();
$sql = "SELECT DISTINCT u.user_id, CONCAT(u.first_name, ' ', u.last_name) AS user_name
        FROM users u
        INNER JOIN users_projects up ON u.user_id = up.user_id
        WHERE u.is_deleted IS NULL
        ORDER BY u.first_name, u.last_name";
$db->query($sql);
while ($db->next_record()) {
    $name = $db->f("user_name");
    if (!mb_check_encoding($name, 'UTF-8')) {
        $name = mb_convert_encoding($name, 'UTF-8', 'Windows-1252');
    }
    $ctx_users[] = array('id' => (int)$db->f("user_id"), 'name' => $name);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sayu Monitor</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);
            min-height: 100vh;
            color: #1a202c;
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 24px;
        }

        .dashboard-grid {
            display: block;
        }

        .sidebar {
            display: none;
        }

        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid #e2e8f0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-header.warning {
            background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
        }

        .card-header.success {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
        }

        .card-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: #fff;
        }

        .card-body {
            padding: 16px 20px;
        }

        .task-meta {
            display: flex;
            flex-direction: column;
            gap: 8px;
            font-size: 0.85rem;
            margin-bottom: 16px;
        }

        .task-meta-row {
            display: flex;
            justify-content: space-between;
        }

        .task-meta-label {
            color: #718096;
        }

        .task-meta-value {
            font-weight: 500;
            color: #2d3748;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border: none;
            font-family: inherit;
        }

        .btn-danger {
            background: #e53e3e;
            color: #fff;
            width: 100%;
        }

        .btn-danger:hover {
            background: #c53030;
        }

        .btn-success {
            background: #38a169;
            color: #fff;
        }

        .btn-success:hover {
            background: #2f855a;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #e2e8f0;
            color: #4a5568;
        }

        .btn-outline:hover {
            background: #f7fafc;
        }

        .reminder-item {
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }

        .reminder-item:last-child {
            border-bottom: none;
        }

        .reminder-text {
            font-size: 0.85rem;
            color: #4a5568;
        }

        .reminder-hide {
            font-size: 0.75rem;
            color: #667eea;
            text-decoration: none;
        }

        .birthday-banner {
            background: linear-gradient(135deg, #faf089 0%, #f6e05e 100%);
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .birthday-banner span {
            font-size: 0.9rem;
            color: #744210;
        }

        .holiday-banner {
            background: linear-gradient(135deg, #bee3f8 0%, #90cdf4 100%);
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .holiday-banner span {
            font-size: 0.9rem;
            color: #2c5282;
        }

        .holiday-banner .region {
            background: rgba(44, 82, 130, 0.2);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .upcoming-holidays {
            background: #fff;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border: 1px solid #e2e8f0;
        }

        .upcoming-holidays-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .upcoming-holidays-header h3 {
            font-size: 0.85rem;
            font-weight: 600;
            color: #4a5568;
        }

        .upcoming-holidays-header a {
            font-size: 0.75rem;
            color: #718096;
            text-decoration: none;
        }

        .upcoming-holidays-header a:hover {
            color: #4a5568;
        }

        .upcoming-holiday-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.85rem;
        }

        .upcoming-holiday-item:last-child {
            border-bottom: none;
        }

        .upcoming-holiday-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .upcoming-holiday-user {
            font-weight: 500;
            color: #2d3748;
        }

        .upcoming-holiday-dates {
            color: #718096;
            font-size: 0.8rem;
        }

        .upcoming-holiday-reason {
            background: #edf2f7;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            color: #4a5568;
        }

        .upcoming-holiday-today {
            background: #fed7d7;
            color: #c53030;
        }

        .upcoming-holiday-hide {
            color: #a0aec0;
            text-decoration: none;
            font-size: 0.8rem;
        }

        .upcoming-holiday-hide:hover {
            color: #718096;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        .data-table th {
            background: #f8fafc;
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            color: #4a5568;
            border-bottom: 2px solid #e2e8f0;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .data-table th.sortable {
            cursor: pointer;
            user-select: none;
            transition: background 0.15s;
        }

        .data-table th.sortable:hover {
            background: #edf2f7;
            color: #667eea;
        }

        .data-table th.sortable::after {
            content: '⇅';
            margin-left: 6px;
            opacity: 0.3;
            font-size: 0.7rem;
        }

        .data-table th.sortable.sort-asc::after {
            content: '↑';
            opacity: 1;
            color: #667eea;
        }

        .data-table th.sortable.sort-desc::after {
            content: '↓';
            opacity: 1;
            color: #667eea;
        }

        .data-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            color: #4a5568;
        }

        .data-table tr:hover {
            background: #f8fafc;
        }

        .data-table tr.clickable-row {
            cursor: pointer;
        }

        .data-table tr.clickable-row:hover {
            background: #edf2f7;
        }

        /* Unread / unseen task rows */
        .data-table tr.task-unread td {
            font-weight: 600;
        }
        .data-table tr.task-unread .task-title-cell {
            font-weight: 700;
        }
        .data-table tr.task-unread .task-title-cell::before {
            content: '';
            display: inline-block;
            width: 6px;
            height: 6px;
            background: #667eea;
            border-radius: 50%;
            margin-right: 6px;
            vertical-align: middle;
            flex-shrink: 0;
        }

        /* Unread kanban cards */
        .kb-card.task-unread {
            border-left: 3px solid #667eea;
        }
        .kb-card.task-unread .kb-card-title a {
            font-weight: 700;
        }

        .data-table a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }

        .data-table a:hover {
            color: #5a67d8;
            text-decoration: underline;
        }

        .priority-badge {
            display: inline-block;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            text-align: center;
            line-height: 24px;
            font-weight: 700;
            font-size: 0.75rem;
            color: #fff;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .status-in-progress {
            background: #c6f6d5;
            color: #276749;
        }

        .status-on-hold {
            background: #e2e8f0;
            color: #4a5568;
        }

        .status-not-started {
            background: #bee3f8;
            color: #2c5282;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .badge-pending {
            background: #e0e7ff;
            color: #3730a3;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .text-center {
            text-align: center !important;
        }

        .text-right {
            text-align: right !important;
        }

        .task-title-cell {
            max-width: 300px;
        }

        .task-title-cell a {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .task-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }

        .action-link {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            text-decoration: none;
            font-weight: 500;
        }

        .action-start {
            background: #c6f6d5;
            color: #276749;
        }

        .action-stop {
            background: #fed7d7;
            color: #c53030;
        }

        .action-close {
            background: #e2e8f0;
            color: #4a5568;
        }

        .btn-periodic {
            background: #f0fff4;
            border: 1px solid #9ae6b4;
            color: #276749;
            justify-content: flex-start;
            font-size: 0.85rem;
            padding: 10px 14px;
        }

        .btn-periodic:hover {
            background: #c6f6d5;
        }

        .btn-periodic-active {
            background: #276749;
            border: 1px solid #276749;
            color: #fff;
            justify-content: flex-start;
            font-size: 0.85rem;
            padding: 10px 14px;
        }

        .btn-periodic-active:hover {
            background: #22543d;
        }

        .periodic-time {
            margin-left: auto;
            font-weight: 600;
            font-size: 0.8rem;
            opacity: 0.9;
        }

        .top-row {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            margin-bottom: 20px;
        }

        .quick-section {
            display: flex;
            gap: 16px;
        }

        .quick-block {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border: 1px solid #e2e8f0;
            min-width: 180px;
        }

        .quick-block-header {
            padding: 10px 14px;
            background: #f8f9fa;
            border-bottom: 1px solid #e2e8f0;
            border-radius: 10px 10px 0 0;
            font-weight: 600;
            font-size: 0.85rem;
            color: #2d3748;
        }

        .quick-block-body {
            padding: 10px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .btn-quick {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: #f0fff4;
            border: 1px solid #9ae6b4;
            color: #276749;
            border-radius: 6px;
            font-size: 0.8rem;
            text-decoration: none;
            transition: all 0.15s;
        }

        .btn-quick:hover {
            background: #c6f6d5;
        }

        .btn-quick.active {
            background: #276749;
            border-color: #276749;
            color: #fff;
        }

        .btn-quick.active:hover {
            background: #22543d;
        }

        .quick-time {
            margin-left: auto;
            font-weight: 600;
            font-size: 0.75rem;
        }

        .btn-current-task {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            border: none;
            color: #fff;
            border-radius: 8px;
            font-size: 0.85rem;
            text-decoration: none;
            transition: all 0.2s;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(72, 187, 120, 0.3);
        }

        .btn-current-task:hover {
            background: linear-gradient(135deg, #38a169 0%, #2f855a 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(72, 187, 120, 0.4);
        }

        .btn-current-task .task-project {
            font-size: 0.7rem;
            opacity: 0.9;
            font-weight: 400;
        }

        .btn-current-task .task-info {
            display: flex;
            flex-direction: column;
            flex: 1;
            min-width: 0;
        }

        .btn-current-task .task-title {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .btn-current-task .task-time {
            font-weight: 700;
            font-size: 0.8rem;
            white-space: nowrap;
        }

        .btn-current-task .stop-icon {
            width: 18px;
            height: 18px;
            background: rgba(255,255,255,0.2);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            flex-shrink: 0;
        }

        /* Flash Messages */
        .flash-message {
            position: fixed;
            top: 70px;
            left: 50%;
            transform: translateX(-50%);
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateX(-50%) translateY(-20px); }
            to { opacity: 1; transform: translateX(-50%) translateY(0); }
        }

        .flash-success {
            background: #48bb78;
            color: #fff;
        }

        .flash-error {
            background: #f56565;
            color: #fff;
        }

        .flash-message a {
            color: #fff;
            text-decoration: underline;
            margin-left: 8px;
        }

        .flash-close {
            background: none;
            border: none;
            color: rgba(255,255,255,0.8);
            font-size: 1.2rem;
            cursor: pointer;
            padding: 0;
            line-height: 1;
        }

        .flash-close:hover {
            color: #fff;
        }

        /* Confirmation Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(2px);
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-box {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 400px;
            width: 90%;
            overflow: hidden;
            animation: modalSlideIn 0.2s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e2e8f0;
        }

        .modal-header.start {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: #fff;
        }

        .modal-header.stop {
            background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%);
            color: #fff;
        }

        .modal-header.close {
            background: linear-gradient(135deg, #718096 0%, #4a5568 100%);
            color: #fff;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .modal-body {
            padding: 24px;
        }

        .modal-task-name {
            font-weight: 600;
            color: #2d3748;
            font-size: 1rem;
            margin-bottom: 8px;
            word-break: break-word;
        }

        .modal-message {
            color: #718096;
            font-size: 0.9rem;
        }

        .modal-completion {
            margin-top: 16px;
            display: none;
        }

        .modal-completion.visible {
            display: block;
        }

        .modal-completion label {
            display: block;
            font-size: 0.85rem;
            font-weight: 500;
            color: #4a5568;
            margin-bottom: 8px;
        }

        .modal-completion input {
            width: 80px;
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.9rem;
            text-align: center;
        }

        .modal-completion input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .modal-completion span {
            margin-left: 6px;
            color: #718096;
            font-size: 0.9rem;
        }

        .modal-footer {
            padding: 16px 24px;
            background: #f8fafc;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .modal-btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.15s;
        }

        .modal-btn-cancel {
            background: #e2e8f0;
            color: #4a5568;
        }

        .modal-btn-cancel:hover {
            background: #cbd5e0;
        }

        .modal-btn-confirm {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
        }

        .modal-btn-confirm:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .modal-btn-confirm.start {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
        }

        .modal-btn-confirm.start:hover {
            box-shadow: 0 4px 12px rgba(72, 187, 120, 0.4);
        }

        .modal-btn-confirm.stop {
            background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%);
        }

        .modal-btn-confirm.stop:hover {
            box-shadow: 0 4px 12px rgba(245, 101, 101, 0.4);
        }

        .modal-btn-confirm.close {
            background: linear-gradient(135deg, #718096 0%, #4a5568 100%);
        }

        .modal-btn-confirm.close:hover {
            box-shadow: 0 4px 12px rgba(113, 128, 150, 0.4);
        }

        .team-blocks {
            display: flex;
            gap: 16px;
        }

        .team-block {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border: 1px solid #e2e8f0;
            min-width: 200px;
            max-width: 240px;
        }

        .team-block-header {
            padding: 10px 14px;
            background: #f8f9fa;
            border-bottom: 1px solid #e2e8f0;
            border-radius: 10px 10px 0 0;
            font-weight: 600;
            font-size: 0.85rem;
            color: #2d3748;
        }

        .project-tabs {
            display: flex;
            padding: 0;
            background: #f8f9fa;
            border-bottom: 1px solid #e2e8f0;
            border-radius: 10px 10px 0 0;
        }

        .project-tab {
            flex: 1;
            padding: 9px 10px;
            text-align: center;
            font-weight: 600;
            font-size: 0.78rem;
            color: #718096;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.15s;
            white-space: nowrap;
        }

        .project-tab:first-child {
            border-radius: 10px 0 0 0;
        }

        .project-tab:last-child {
            border-radius: 0 10px 0 0;
        }

        .project-tab:hover {
            color: #4a5568;
            background: #edf2f7;
        }

        .project-tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
            background: #fff;
        }

        .project-tab-count {
            color: #a0aec0;
            font-weight: 500;
            font-size: 0.7rem;
        }

        .project-tab.active .project-tab-count {
            color: #a3bffa;
        }

        .project-add-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            flex-shrink: 0;
            color: #a0aec0;
            text-decoration: none;
            transition: all 0.15s;
            border-left: 1px solid #e2e8f0;
        }

        .project-add-btn:hover {
            color: #667eea;
            background: #edf2f7;
            border-radius: 0 10px 0 0;
        }

        .project-tab-panel {
            display: none;
        }

        .project-tab-panel.active {
            display: block;
        }

        .project-add-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            flex-shrink: 0;
            color: #a0aec0;
            text-decoration: none;
            border-left: 1px solid #e2e8f0;
            transition: all 0.15s;
            border-radius: 0 10px 0 0;
        }

        .project-add-btn:hover {
            color: #667eea;
            background: #eef2ff;
        }

        .team-block-body {
            max-height: 200px;
            overflow-y: auto;
            padding: 6px;
        }

        .team-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 6px 10px;
            border-radius: 6px;
            text-decoration: none;
            color: #4a5568;
            font-size: 0.8rem;
            transition: background 0.15s;
        }

        .team-row:hover {
            background: #f1f5f9;
        }

        .team-row.online .team-name {
            font-weight: 600;
            color: #276749;
        }

        .team-row.away .team-name {
            color: #2b6cb0;
        }

        .team-row.offline .team-name {
            color: #718096;
        }

        .team-name {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 150px;
        }

        .team-tasks {
            color: #a0aec0;
            font-size: 0.75rem;
            margin-left: 6px;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #a0aec0;
        }

        .quick-links {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding: 16px 20px;
            border-top: 1px solid #e2e8f0;
        }

        .quick-links a {
            font-size: 0.8rem;
            color: #667eea;
            text-decoration: none;
        }

        .quick-links a:hover {
            text-decoration: underline;
        }

        .vacation-card {
            padding: 16px;
            background: #f8fafc;
            border-radius: 10px;
            margin-bottom: 12px;
        }

        .vacation-card:last-child {
            margin-bottom: 0;
        }

        .vacation-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .vacation-user {
            font-weight: 600;
            color: #2d3748;
        }

        .vacation-type {
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 4px;
            background: #667eea;
            color: #fff;
        }

        .vacation-details {
            font-size: 0.85rem;
            color: #4a5568;
            margin-bottom: 12px;
        }

        .vacation-dates {
            display: flex;
            gap: 16px;
            font-size: 0.8rem;
            color: #718096;
            margin-bottom: 12px;
        }

        .vacation-actions {
            display: flex;
            gap: 8px;
        }

        .scroll-table {
            overflow-x: auto;
        }

        /* ===================== RESPONSIVE ===================== */
        
        @media (max-width: 1024px) {
            .container {
                padding: 16px;
            }

            .top-row {
                gap: 16px;
            }

            .team-blocks {
                gap: 10px;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            .top-row {
                flex-direction: column;
                gap: 12px;
            }

            .quick-section {
                flex-direction: row;
                gap: 10px;
                overflow-x: auto;
            }

            .quick-block {
                min-width: 160px;
                flex-shrink: 0;
            }

            .section-header h2 {
                font-size: 1rem;
            }

            .data-table th,
            .data-table td {
                padding: 8px 10px;
                font-size: 0.8rem;
            }

            /* Hide less important columns on tablet */
            .col-modified,
            .col-completion,
            .col-created {
                display: none;
            }

            .task-title-cell {
                max-width: 200px;
            }

            .team-blocks {
                flex-wrap: nowrap;
                overflow-x: auto;
                padding-bottom: 8px;
            }

            .projects-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)) !important;
                gap: 6px !important;
            }

            .kb-board { padding: 12px; gap: 12px; }
            .kb-column { min-width: 240px; }
        }

        @media (max-width: 480px) {
            .container {
                padding: 6px;
            }

            .top-row {
                gap: 8px;
                margin-bottom: 12px;
            }

            .quick-section {
                gap: 8px;
            }

            .quick-block {
                min-width: 140px;
            }

            .quick-block-header {
                padding: 8px 10px;
                font-size: 0.8rem;
            }

            .quick-block-body {
                padding: 6px;
            }

            /* Hide most columns on phone - show only Task, Status, Actions */
            .col-project,
            .col-priority,
            .col-actual,
            .col-modified,
            .col-completion,
            .col-created,
            .col-deadline {
                display: none;
            }

            .data-table th,
            .data-table td {
                padding: 8px 6px;
                font-size: 0.8rem;
            }

            .task-title-cell {
                max-width: none;
                white-space: normal;
            }

            .task-title-cell a {
                white-space: normal;
            }

            /* Compact status badge */
            .status-badge {
                font-size: 0.65rem;
                padding: 2px 6px;
            }

            /* Icon-only action buttons */
            .action-link {
                font-size: 0;
                padding: 4px 8px;
            }

            .action-link::before {
                font-size: 16px;
            }

            .action-link.action-start::before {
                content: '▶';
            }

            .action-link.action-stop::before {
                content: '⏹';
            }

            .action-link.action-close::before {
                content: '✓';
            }

            .task-actions {
                gap: 4px;
            }

            .section-header h2 {
                font-size: 0.95rem;
            }

            /* Quick links row */
            .quick-links {
                flex-wrap: wrap;
                gap: 8px;
                font-size: 0.8rem;
            }

            .team-blocks {
                gap: 8px;
            }

            .team-block {
                min-width: 160px;
                max-width: 180px;
            }

            .projects-grid {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)) !important;
                gap: 4px !important;
            }

            .project-card {
                padding: 6px 8px !important;
                font-size: 0.75rem !important;
            }

            .team-tasks {
                display: none;
            }
        }

        /* Context Menu */
        .context-menu {
            position: fixed;
            z-index: 10000;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12), 0 2px 8px rgba(0,0,0,0.06);
            min-width: 200px;
            padding: 6px 0;
            display: none;
            font-size: 0.85rem;
            font-family: inherit;
        }

        .context-menu.show {
            display: block;
        }

        .context-menu-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 16px;
            cursor: pointer;
            color: #4a5568;
            transition: background 0.1s, color 0.1s;
            white-space: nowrap;
        }

        .context-menu-item:hover {
            background: #f7fafc;
            color: #2d3748;
        }

        .context-menu-item .ctx-icon {
            width: 16px;
            text-align: center;
            flex-shrink: 0;
            font-size: 0.9em;
        }

        .context-menu-item.danger {
            color: #e53e3e;
        }

        .context-menu-item.danger:hover {
            background: #fff5f5;
        }

        .context-menu-item.success {
            color: #38a169;
        }

        .context-menu-item.success:hover {
            background: #f0fff4;
        }

        .context-menu-separator {
            height: 1px;
            background: #e2e8f0;
            margin: 4px 0;
        }

        .context-menu-submenu {
            position: relative;
        }

        .context-menu-submenu > .context-menu-item::after {
            content: '›';
            margin-left: auto;
            font-size: 1.1em;
            color: #a0aec0;
        }

        .context-menu-submenu-items {
            display: none;
            position: fixed;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12), 0 2px 8px rgba(0,0,0,0.06);
            min-width: 160px;
            padding: 6px 0;
        }

        .context-menu-submenu-items.show {
            display: block;
        }

        .context-menu-item.active-status {
            color: #667eea;
            font-weight: 600;
        }

        /* View toggle */
        .view-toggle { display: flex; gap: 2px; background: rgba(255,255,255,0.2); border-radius: 6px; padding: 2px; }
        .view-toggle-btn { padding: 4px 12px; border-radius: 5px; border: none; background: transparent; color: rgba(255,255,255,0.7); font-size: 0.72rem; font-weight: 500; cursor: pointer; font-family: inherit; transition: all 0.15s; display: flex; align-items: center; gap: 4px; }
        .view-toggle-btn:hover { color: #fff; }
        .view-toggle-btn.active { background: rgba(255,255,255,0.95); color: #4a5568; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .view-toggle-btn svg { width: 12px; height: 12px; }
        .my-tasks-list-view, .my-tasks-cards-view, .my-tasks-status-view { display: none; }
        .my-tasks-list-view.active, .my-tasks-cards-view.active, .my-tasks-status-view.active { display: block; }

        .tl-time { font-size: 0.78rem; font-weight: 600; color: #4a5568; white-space: nowrap; }
        .tl-time.tl-running { color: #38a169; }
        .overdue-text { color: #e53e3e; font-weight: 600; }
        /* Kanban board for By Status view */
        .kb-board { display: flex; gap: 16px; padding: 16px; overflow-x: auto; min-height: 300px; align-items: flex-start; }
        .kb-column { min-width: 260px; max-width: 300px; flex: 1 0 260px; background: #f4f5f7; border-radius: 12px; display: flex; flex-direction: column; max-height: calc(100vh - 300px); }
        .kb-column-header { padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; border-radius: 12px 12px 0 0; }
        .kb-column-title { font-weight: 700; font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .kb-column-count { font-size: 0.72rem; font-weight: 600; padding: 2px 8px; border-radius: 10px; background: rgba(255,255,255,0.5); }
        .kb-cards { padding: 8px 10px 12px; overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 8px; min-height: 60px; }
        .kb-card { background: #fff; border-radius: 8px; padding: 12px 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); cursor: grab; transition: box-shadow 0.15s, transform 0.15s; border-left: 3px solid transparent; }
        .kb-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.12); transform: translateY(-1px); }
        .kb-card:active { cursor: grabbing; }
        .kb-card.overdue { border-left-color: #e53e3e; }
        .kb-card-title { font-weight: 600; font-size: 0.85rem; color: #2d3748; margin-bottom: 8px; line-height: 1.35; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        .kb-card-title a { color: inherit; text-decoration: none; }
        .kb-card-title a:hover { color: #667eea; }
        .kb-card-meta { display: flex; flex-wrap: wrap; gap: 6px 12px; font-size: 0.75rem; color: #718096; }
        .kb-card-meta-item { display: flex; align-items: center; gap: 3px; }
        .kb-card-meta-item svg { opacity: 0.6; }
        .kb-card-project { color: #667eea; font-weight: 500; }
        .kb-card-footer { display: flex; justify-content: flex-end; align-items: center; margin-top: 8px; }
        .kb-empty { text-align: center; padding: 24px 12px; color: #a0aec0; font-size: 0.8rem; font-style: italic; }

        /* Project columns (By Project): title as link, no uppercase */
        .my-tasks-cards-view .kb-column-title { text-transform: none; letter-spacing: 0; text-decoration: none; }
        .my-tasks-cards-view .kb-column-title:hover { color: #667eea; }
        html.dark-mode .my-tasks-cards-view .kb-column-title:hover { color: #90cdf4; }

        /* Column colors */
        .kb-col-new .kb-column-header { background: #e2e8f0; }
        .kb-col-new .kb-column-title { color: #4a5568; }
        .kb-col-progress .kb-column-header { background: #c6f6d5; }
        .kb-col-progress .kb-column-title { color: #276749; }
        .kb-col-hold .kb-column-header { background: #feebc8; }
        .kb-col-hold .kb-column-title { color: #c05621; }
        .kb-col-done .kb-column-header { background: #bee3f8; }
        .kb-col-done .kb-column-title { color: #2b6cb0; }

        /* Drag and drop */
        .kb-card.dragging { opacity: 0.4; transform: rotate(2deg); }
        .kb-column.drag-over .kb-cards { background: rgba(102,126,234,0.08); border-radius: 0 0 12px 12px; }
        .kb-column.drag-over .kb-column-header { box-shadow: 0 0 0 2px #667eea inset; }
        .kb-drop-indicator { height: 4px; min-height: 4px; background: #667eea; border-radius: 4px; margin: 6px 0; flex-shrink: 0; box-shadow: 0 0 0 1px rgba(102,126,234,0.4); pointer-events: none; }
        .kb-card-just-moved { animation: kb-card-highlight 2s ease-out; }
        @keyframes kb-card-highlight {
            0% { box-shadow: 0 0 0 3px #48bb78, 0 4px 12px rgba(0,0,0,0.12); }
            50% { box-shadow: 0 0 0 4px rgba(72,187,120,0.6), 0 6px 20px rgba(72,187,120,0.25); }
            100% { box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        }
        html.dark-mode .kb-card-just-moved { animation: kb-card-highlight-dark 2s ease-out; }
        @keyframes kb-card-highlight-dark {
            0% { box-shadow: 0 0 0 3px #48bb78, 0 4px 12px rgba(0,0,0,0.5); }
            50% { box-shadow: 0 0 0 4px rgba(72,187,120,0.5), 0 6px 20px rgba(72,187,120,0.2); }
            100% { box-shadow: 0 1px 3px rgba(0,0,0,0.5); }
        }
        .kb-toast { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); background: #48bb78; color: #fff; padding: 10px 24px; border-radius: 8px; font-size: 0.9rem; font-weight: 500; z-index: 99999; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .kb-toast.error { background: #e53e3e; }

        /* Add a card */
        .kb-add-area { padding: 4px 10px 10px; }
        .kb-add-btn { background: none; border: none; color: #718096; font-size: 0.82rem; cursor: pointer; padding: 8px 6px; width: 100%; text-align: left; border-radius: 6px; transition: background 0.1s, color 0.1s; font-family: inherit; }
        .kb-add-btn:hover { background: rgba(0,0,0,0.04); color: #4a5568; }
        .kb-add-form { display: flex; flex-direction: column; gap: 6px; }
        .kb-add-input { width: 100%; padding: 8px 10px; border: 1px solid #e2e8f0; border-radius: 6px; font-family: inherit; font-size: 0.85rem; resize: none; min-height: 54px; box-sizing: border-box; }
        .kb-add-input:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }
        .kb-add-project { padding: 6px 8px; border: 1px solid #e2e8f0; border-radius: 6px; font-family: inherit; font-size: 0.8rem; background: #fff; }
        .kb-add-project:focus { outline: none; border-color: #667eea; }
        .kb-add-actions { display: flex; align-items: center; gap: 6px; }
        .kb-add-submit { padding: 6px 14px; background: #667eea; color: #fff; border: none; border-radius: 6px; font-size: 0.82rem; font-weight: 600; cursor: pointer; font-family: inherit; transition: background 0.15s; }
        .kb-add-submit:hover { background: #5a67d8; }
        .kb-add-cancel { background: none; border: none; color: #a0aec0; font-size: 1.3rem; cursor: pointer; padding: 2px 8px; line-height: 1; border-radius: 4px; }
        .kb-add-cancel:hover { color: #718096; background: rgba(0,0,0,0.04); }

        /* Drop message modal */
        .kb-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 10010; display: none; align-items: center; justify-content: center; }
        .kb-modal-overlay.active { display: flex; }
        .kb-modal { background: #fff; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); max-width: 600px; width: 94%; overflow: hidden; }
        .kb-modal-header { padding: 16px 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; display: flex; justify-content: space-between; align-items: center; }
        .kb-modal-header h3 { margin: 0; font-size: 1rem; font-weight: 600; }
        .kb-modal-close { background: none; border: none; color: rgba(255,255,255,0.7); font-size: 1.3rem; cursor: pointer; padding: 2px 6px; }
        .kb-modal-close:hover { color: #fff; }
        .kb-modal-body { padding: 20px; max-height: calc(100vh - 200px); overflow-y: auto; }
        .kb-modal-task { font-weight: 600; color: #2d3748; margin-bottom: 4px; }
        .kb-modal-info { font-size: 0.82rem; color: #718096; margin-bottom: 14px; }
        .kb-modal-fields { display: flex; gap: 12px; margin-bottom: 14px; flex-wrap: wrap; align-items: flex-end; }
        .kb-modal-field { display: flex; flex-direction: column; gap: 4px; flex: 1; min-width: 120px; position: relative; }
        .kb-modal-field label { font-size: 0.75rem; font-weight: 600; color: #718096; text-transform: uppercase; letter-spacing: 0.3px; }
        .kb-modal-field-short { flex: 0 0 100px; min-width: 90px; }

        /* Custom JS select */
        .kb-select { position: relative; }
        .kb-select-trigger { display: flex; align-items: center; justify-content: space-between; padding: 8px 10px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; cursor: pointer; font-size: 0.85rem; transition: all 0.2s; user-select: none; }
        .kb-select-trigger:hover { border-color: #cbd5e0; }
        .kb-select.open .kb-select-trigger { border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }
        .kb-select-trigger::after { content: ''; border: 4px solid transparent; border-top-color: #718096; margin-left: 8px; flex-shrink: 0; }
        .kb-select.open .kb-select-trigger::after { border-top-color: transparent; border-bottom-color: #718096; margin-top: -4px; }
        .kb-select-trigger span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .kb-select-dropdown { position: absolute; top: 100%; left: 0; right: 0; min-width: 200px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 4px 16px rgba(0,0,0,0.12); max-height: 240px; overflow-y: auto; z-index: 10020; display: none; margin-top: 4px; }
        .kb-select.open .kb-select-dropdown { display: block; }
        .kb-select-search { padding: 8px; border-bottom: 1px solid #e2e8f0; }
        .kb-select-search input { width: 100%; padding: 6px 8px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.8rem; font-family: inherit; box-sizing: border-box; }
        .kb-select-search input:focus { outline: none; border-color: #667eea; }
        .kb-select-options { max-height: 180px; overflow-y: auto; }
        .kb-select-option { padding: 8px 12px; cursor: pointer; font-size: 0.85rem; transition: background 0.1s; }
        .kb-select-option:hover { background: #f7fafc; }
        .kb-select-option.highlighted { background: #667eea; color: #fff; }
        .kb-select-option.selected { background: #edf2f7; font-weight: 500; }
        .kb-select-option.selected.highlighted { background: #667eea; color: #fff; }
        .kb-select-option.hidden { display: none; }
        .kb-completion-wrap { display: flex; align-items: center; gap: 4px; }
        .kb-completion-wrap input { width: 65px; padding: 8px 8px; border: 1px solid #e2e8f0; border-radius: 8px; font-family: inherit; font-size: 0.85rem; text-align: center; }
        .kb-completion-wrap input:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }
        .kb-completion-wrap span { font-size: 0.85rem; color: #718096; font-weight: 500; }
        .kb-modal-textarea { width: 100%; min-height: 150px; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-family: inherit; font-size: 0.9rem; resize: vertical; box-sizing: border-box; }
        .kb-modal-textarea:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }
        .kb-modal-attachments { margin-top: 10px; }
        .kb-modal-dropzone { border: 2px dashed #cbd5e0; border-radius: 8px; padding: 12px; text-align: center; font-size: 0.82rem; color: #718096; transition: all 0.15s; cursor: pointer; }
        .kb-modal-dropzone:hover, .kb-modal-dropzone.drag-over { border-color: #667eea; background: #f0f4ff; }
        .kb-file-label { color: #667eea; cursor: pointer; font-weight: 500; }
        .kb-file-label:hover { text-decoration: underline; }
        .kb-modal-file-list { margin-top: 8px; max-height: 120px; overflow-y: auto; }
        .kb-file-item { display: flex; align-items: center; gap: 8px; padding: 6px 8px; border-radius: 6px; background: #f7fafc; margin-bottom: 4px; font-size: 0.8rem; }
        .kb-file-item .kb-fi-icon { width: 24px; height: 24px; background: #f1f5f9; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; flex-shrink: 0; }
        .kb-file-item .kb-fi-icon.image { background: #fef3c7; }
        .kb-file-item .kb-fi-info { flex: 1; min-width: 0; }
        .kb-file-item .kb-fi-name { font-weight: 500; color: #2d3748; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .kb-file-item .kb-fi-size { color: #a0aec0; font-size: 0.7rem; }
        .kb-file-item .kb-fi-preview { width: 28px; height: 28px; border-radius: 4px; object-fit: cover; flex-shrink: 0; }
        .kb-file-item .kb-fi-remove { color: #e53e3e; cursor: pointer; padding: 2px 4px; border-radius: 4px; transition: background 0.15s; font-size: 0.85rem; }
        .kb-file-item .kb-fi-remove:hover { background: #fed7d7; }
        .kb-modal-footer { padding: 14px 20px; display: flex; gap: 8px; justify-content: flex-end; border-top: 1px solid #f1f5f9; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }

        /* Bulk action bar */
        .bulk-bar { display:none; padding:10px 20px; background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); color:#fff; align-items:center; gap:12px; font-size:0.88rem; font-weight:500; border-radius:10px; margin:0 20px 12px; box-shadow:0 2px 10px rgba(102,126,234,0.3); animation:slideDown 0.2s ease; }
        .bulk-bar.show { display:flex; }
        .bulk-bar .bulk-count { background:rgba(255,255,255,0.2); padding:3px 10px; border-radius:12px; font-size:0.8rem; font-weight:700; }
        .bulk-bar .bulk-actions { display:flex; gap:8px; margin-left:auto; }
        .bulk-bar .bulk-btn { padding:6px 14px; border-radius:6px; border:1px solid rgba(255,255,255,0.3); background:rgba(255,255,255,0.1); color:#fff; font-size:0.82rem; font-weight:500; cursor:pointer; font-family:inherit; transition:all 0.15s; position:relative; }
        .bulk-bar .bulk-btn:hover { background:rgba(255,255,255,0.25); }
        .bulk-bar .bulk-btn.danger { border-color:rgba(255,100,100,0.4); }
        .bulk-bar .bulk-btn.danger:hover { background:rgba(255,100,100,0.2); }
        .bulk-bar .bulk-btn-cancel { background:none; border:none; color:rgba(255,255,255,0.7); cursor:pointer; font-size:0.82rem; font-family:inherit; padding:6px 10px; }
        .bulk-bar .bulk-btn-cancel:hover { color:#fff; }

        /* Bulk dropdown panel */
        .bulk-dropdown { display:none; position:absolute; bottom:calc(100% + 6px); left:0; background:#fff; border:1px solid #e2e8f0; border-radius:8px; box-shadow:0 8px 30px rgba(0,0,0,0.15); min-width:200px; max-height:300px; overflow-y:auto; padding:6px 0; z-index:100; }
        .bulk-dropdown.show { display:block; }
        .bulk-dropdown .bd-search { padding:6px 10px; }
        .bulk-dropdown .bd-search input { width:100%; padding:5px 8px; border:1px solid #e2e8f0; border-radius:5px; font-size:0.82rem; font-family:inherit; outline:none; }
        .bulk-dropdown .bd-search input:focus { border-color:#667eea; }
        .bulk-dropdown .bd-item { padding:6px 14px; font-size:0.82rem; color:#4a5568; cursor:pointer; transition:background 0.1s; }
        .bulk-dropdown .bd-item:hover, .bulk-dropdown .bd-item.highlighted { background:#edf2f7; }
        .bulk-dropdown .bd-item.current { color:#667eea; font-weight:600; }

        .data-table tr.selected-row { background:#eef2ff !important; }

        /* ==================== DARK MODE overrides for index.php ==================== */
        html.dark-mode .dashboard-grid .card { background: #161b22; }
        html.dark-mode .dashboard-grid .card-header { background: linear-gradient(135deg, #1c2333 0%, #161b22 100%); }

        /* View toggle */
        html.dark-mode .view-toggle { background: rgba(255,255,255,0.08); }
        html.dark-mode .view-toggle-btn { color: rgba(255,255,255,0.5); }
        html.dark-mode .view-toggle-btn:hover { color: #fff; }
        html.dark-mode .view-toggle-btn.active { background: rgba(255,255,255,0.15); color: #fff; }

        /* Kanban board (By Project & By Status share kb-* styles) */
        html.dark-mode .kb-column { background: #0d1117; }
        html.dark-mode .kb-column-count { background: rgba(0,0,0,0.3); color: #a0aec0; }
        html.dark-mode .kb-card { background: #161b22; box-shadow: 0 1px 3px rgba(0,0,0,0.5); }
        html.dark-mode .kb-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.6); }
        html.dark-mode .kb-card-title { color: #e2e8f0; }
        html.dark-mode .kb-card-title a { color: #e2e8f0; }
        html.dark-mode .kb-card-title a:hover { color: #90cdf4; }
        html.dark-mode .kb-card-meta { color: #a0aec0; }
        html.dark-mode .kb-card-project { color: #90cdf4; }
        html.dark-mode .kb-empty { color: #4a5568; }
        html.dark-mode .kb-col-new .kb-column-header { background: #1c2333; }
        html.dark-mode .kb-col-new .kb-column-title { color: #e2e8f0; }
        html.dark-mode .kb-col-progress .kb-column-header { background: #1a3a2a; }
        html.dark-mode .kb-col-progress .kb-column-title { color: #c6f6d5; }
        html.dark-mode .kb-col-hold .kb-column-header { background: #3a2a0a; }
        html.dark-mode .kb-col-hold .kb-column-title { color: #feebc8; }
        html.dark-mode .kb-col-done .kb-column-header { background: #172a45; }
        html.dark-mode .kb-col-done .kb-column-title { color: #bee3f8; }
        html.dark-mode .kb-column.drag-over .kb-cards { background: rgba(102,126,234,0.12); }
        html.dark-mode .kb-drop-indicator { background: #818cf8; box-shadow: 0 0 0 1px rgba(129,140,248,0.5); }
        html.dark-mode .kb-add-btn { color: #8b949e; }
        html.dark-mode .kb-add-btn:hover { background: rgba(255,255,255,0.05); color: #e2e8f0; }
        html.dark-mode .kb-add-input { background: #161b22; color: #e2e8f0; border-color: #2d333b; }
        html.dark-mode .kb-add-input:focus { border-color: #667eea; }
        html.dark-mode .kb-add-project { background: #161b22; color: #e2e8f0; border-color: #2d333b; }
        html.dark-mode .kb-add-project:focus { border-color: #667eea; }
        html.dark-mode .kb-add-cancel { color: #8b949e; }
        html.dark-mode .kb-add-cancel:hover { color: #e2e8f0; background: rgba(255,255,255,0.05); }
        html.dark-mode .kb-modal { background: #161b22; }
        html.dark-mode .kb-modal-task { color: #e2e8f0; }
        html.dark-mode .kb-modal-info { color: #8b949e; }
        html.dark-mode .kb-modal-field label { color: #8b949e; }
        html.dark-mode .kb-select-trigger { background: #1c2333; color: #e2e8f0; border-color: #2d333b; }
        html.dark-mode .kb-select-trigger:hover { border-color: #444c56; }
        html.dark-mode .kb-select-trigger::after { border-top-color: #8b949e; }
        html.dark-mode .kb-select.open .kb-select-trigger::after { border-top-color: transparent; border-bottom-color: #8b949e; }
        html.dark-mode .kb-select.open .kb-select-trigger { border-color: #667eea; }
        html.dark-mode .kb-select-dropdown { background: #161b22; border-color: #2d333b; box-shadow: 0 4px 20px rgba(0,0,0,0.6); }
        html.dark-mode .kb-select-search { border-bottom-color: #2d333b; }
        html.dark-mode .kb-select-search input { background: #1c2333; color: #e2e8f0; border-color: #2d333b; }
        html.dark-mode .kb-select-search input:focus { border-color: #667eea; }
        html.dark-mode .kb-select-option { color: #cbd5e0; }
        html.dark-mode .kb-select-option:hover { background: #1c2333; }
        html.dark-mode .kb-select-option.highlighted { background: #667eea; color: #fff; }
        html.dark-mode .kb-select-option.selected { background: #1c2333; color: #fff; }
        html.dark-mode .kb-completion-wrap input { background: #1c2333; color: #e2e8f0; border-color: #2d333b; }
        html.dark-mode .kb-completion-wrap input:focus { border-color: #667eea; }
        html.dark-mode .kb-completion-wrap span { color: #8b949e; }
        html.dark-mode .kb-modal-textarea { background: #1c2333; color: #e2e8f0; border-color: #2d333b; }
        html.dark-mode .kb-modal-textarea:focus { border-color: #667eea; }
        html.dark-mode .kb-modal-dropzone { background: #1c2333; border-color: #2d333b; color: #8b949e; }
        html.dark-mode .kb-modal-dropzone:hover, html.dark-mode .kb-modal-dropzone.drag-over { border-color: #667eea; background: rgba(102,126,234,0.08); }
        html.dark-mode .kb-file-item { background: #1c2333; }
        html.dark-mode .kb-file-item .kb-fi-icon { background: #2d333b; color: #8b949e; }
        html.dark-mode .kb-file-item .kb-fi-icon.image { background: #3a2a0a; }
        html.dark-mode .kb-file-item .kb-fi-name { color: #e2e8f0; }
        html.dark-mode .kb-modal-footer { border-top-color: #2d333b; }
        html.dark-mode .kb-modal-overlay { background: rgba(0,0,0,0.7); }

        /* Table rows */
        html.dark-mode .data-table tr.selected-row { background: #1c2333 !important; }
        html.dark-mode .clickable-row:hover td { background: #1c2333; }

        /* Unread tasks dark mode */
        html.dark-mode .data-table tr.task-unread .task-title-cell::before { background: #90cdf4; }
        html.dark-mode .kb-card.task-unread { border-left-color: #90cdf4; }

        /* Action buttons dark mode */
        html.dark-mode .action-start { background: #22543d; color: #9ae6b4; }
        html.dark-mode .action-stop { background: #742a2a; color: #feb2b2; }
        html.dark-mode .action-close { background: #2d3748; color: #a0aec0; }
        html.dark-mode .action-start:hover { background: #276749; color: #c6f6d5; }
        html.dark-mode .action-stop:hover { background: #9b2c2c; color: #fed7d7; }
        html.dark-mode .action-close:hover { background: #4a5568; color: #e2e8f0; }

        /* Quick Tasks / Currently Working */
        html.dark-mode .running-task { background: #161b22 !important; border-color: #2d333b !important; }
        html.dark-mode .running-task:hover { border-color: #90cdf4 !important; }

        /* Modal */
        html.dark-mode .modal-box { background: #161b22; color: #e2e8f0; }
        html.dark-mode .modal-header { background: #1c2333; }
        html.dark-mode .modal-header h3 { color: #e2e8f0; }
        html.dark-mode .modal-body { color: #cbd5e0; }
        html.dark-mode .modal-task-name { color: #e2e8f0; }
        html.dark-mode .modal-footer { background: #161b22; border-top: 1px solid #2d333b; }
        html.dark-mode .modal-btn-cancel { background: #1c2333; color: #e2e8f0; }
        html.dark-mode .modal-btn-cancel:hover { background: #2d333b; }
        html.dark-mode .modal-overlay { background: rgba(0,0,0,0.7); }

        /* Quick links */
        html.dark-mode .quick-links { border-top-color: #2d333b; }
        html.dark-mode .quick-links a { color: #90cdf4; }

        /* Empty state */
        html.dark-mode .empty-state p { color: #a0aec0; }

        /* Toast */
        html.dark-mode .bulk-toast { background: #161b22; color: #e2e8f0; box-shadow: 0 4px 20px rgba(0,0,0,0.6); }

        /* Quick blocks (Currently Working On, Quick Tasks, Quick Actions) */
        html.dark-mode .quick-block { background: #161b22; border-color: #2d333b; box-shadow: 0 2px 8px rgba(0,0,0,0.4); }
        html.dark-mode .quick-block-header { background: #1c2333; border-bottom-color: #2d333b; color: #e2e8f0; }
        html.dark-mode .btn-current-task { background: linear-gradient(135deg, #2d7d4a 0%, #276749 100%); color: #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.3); }
        html.dark-mode .btn-current-task:hover { background: linear-gradient(135deg, #276749 0%, #22543d 100%); }
        html.dark-mode .btn-current-task .task-title,
        html.dark-mode .btn-current-task .task-project,
        html.dark-mode .btn-current-task .task-time { color: #fff; opacity: 1; }
        html.dark-mode .btn-current-task .stop-icon { background: rgba(255,255,255,0.25); color: #fff; }
        html.dark-mode .btn-quick { background: #1c2333; color: #c6f6d5; border-color: #2d333b; }
        html.dark-mode .btn-quick:hover { background: #1a3a2a; }
        html.dark-mode .btn-quick.active { background: #276749; border-color: #276749; }
        html.dark-mode .btn-quick.active:hover { background: #22543d; }

        /* Team blocks (Today's Time, My Projects, Sayu Ukraine, etc.) */
        html.dark-mode .team-block { background: #161b22; border-color: #2d333b; box-shadow: 0 2px 8px rgba(0,0,0,0.4); }
        html.dark-mode .team-block-header { background: #1c2333; border-bottom-color: #2d333b; color: #e2e8f0; }
        html.dark-mode .team-row { color: #cbd5e0; }
        html.dark-mode .team-row:hover { background: #1c2333; }
        html.dark-mode .team-row.online .team-name { color: #68d391; }
        html.dark-mode .team-row.away .team-name { color: #90cdf4; }
        html.dark-mode .team-row.offline .team-name { color: #718096; }

        /* Project tabs inside team blocks */
        html.dark-mode .project-tabs { background: #1c2333; border-bottom-color: #2d333b; }
        html.dark-mode .project-tab { color: #8b949e; }
        html.dark-mode .project-tab:hover { color: #e2e8f0; background: #161b22; }
        html.dark-mode .project-tab.active { color: #90cdf4; border-bottom-color: #90cdf4; background: #161b22; }
        html.dark-mode .project-tab-count { color: #8b949e; }
        html.dark-mode .project-tab.active .project-tab-count { color: #a3bffa; }
        html.dark-mode .project-add-btn { color: #8b949e; border-left-color: #2d333b; }
        html.dark-mode .project-add-btn:hover { color: #90cdf4; background: #1c2333; }

        /* Vacation cards */
        html.dark-mode .vacation-card { background: #1c2333; }

        /* Time log items */
        html.dark-mode .tl-time { color: #a0aec0; }
        html.dark-mode .tl-time.tl-running { color: #68d391; }
    </style>
</head>
<body>
    <?php include("./includes/modern_header.php"); ?>

    <?php 
    // Display flash message if exists
    if (isset($_SESSION['flash_message'])): 
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
    ?>
    <div class="flash-message flash-<?php echo $flash['type']; ?>" id="flashMessage">
        <span class="flash-text">
            <?php echo htmlspecialchars($flash['text']); ?>
            <?php if (isset($flash['task_id'])): ?>
                <a href="edit_task.php?task_id=<?php echo $flash['task_id']; ?>">View Task</a>
            <?php endif; ?>
        </span>
        <button class="flash-close" onclick="document.getElementById('flashMessage').remove()">×</button>
    </div>
    <?php endif; ?>

    <div class="container">
        <?php if (!empty($birthdays)): ?>
        <div class="birthday-banner">
            <span>&#127874;</span>
            <span>Today is <?php echo htmlspecialchars(implode(', ', $birthdays)); ?>'s birthday!</span>
        </div>
        <?php endif; ?>

        <?php foreach ($todays_holidays as $holiday): ?>
        <div class="holiday-banner">
            <span>&#127881;</span>
            <span>Today is <strong><?php echo htmlspecialchars($holiday['name']); ?></strong></span>
            <span class="region"><?php echo htmlspecialchars($holiday['region']); ?></span>
        </div>
        <?php endforeach; ?>

        <?php if (!empty($upcoming_holidays)): ?>
        <div class="upcoming-holidays">
            <div class="upcoming-holidays-header">
                <h3>&#128197; Upcoming Time Off</h3>
                <a href="index.php?hide_all_holidays=1">Hide all</a>
            </div>
            <?php foreach ($upcoming_holidays as $holiday): ?>
            <div class="upcoming-holiday-item">
                <div class="upcoming-holiday-info">
                    <span class="upcoming-holiday-user"><?php echo htmlspecialchars($holiday['user_name']); ?></span>
                    <span class="upcoming-holiday-dates">
                        <?php if ($holiday['is_today']): ?>
                            <span class="upcoming-holiday-reason upcoming-holiday-today">Starting today</span>
                        <?php else: ?>
                            <?php echo $holiday['start_date']; ?> - <?php echo $holiday['end_date']; ?>
                        <?php endif; ?>
                    </span>
                    <?php if ($holiday['reason']): ?>
                    <span class="upcoming-holiday-reason"><?php echo htmlspecialchars($holiday['reason']); ?></span>
                    <?php endif; ?>
                </div>
                <a href="index.php?hide_holiday_id=<?php echo $holiday['period_id']; ?>" class="upcoming-holiday-hide" title="Hide">&#10005;</a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="dashboard-grid">
            <!-- Sidebar -->
            <div class="sidebar">

                <?php if (!empty($pending_vacations)): ?>
                <!-- Pending Vacations -->
                <div class="card">
                    <div class="card-header warning">
                        <span class="card-title">Pending Approvals (<?php echo count($pending_vacations); ?>)</span>
                    </div>
                    <div class="card-body">
                        <?php foreach ($pending_vacations as $vac): ?>
                        <div class="vacation-card">
                            <div class="vacation-header">
                                <span class="vacation-user"><?php echo htmlspecialchars($vac['user_name']); ?></span>
                                <span class="vacation-type"><?php echo $vac['is_paid'] ? 'Overwork' : 'Vacation'; ?></span>
                            </div>
                            <div class="vacation-details">
                                <a href="create_vacation.php?vacation_id=<?php echo $vac['period_id']; ?>"><?php echo htmlspecialchars($vac['title'] ?: 'No title'); ?></a>
                                <?php if ($vac['reason']): ?> - <?php echo htmlspecialchars($vac['reason']); ?><?php endif; ?>
                            </div>
                            <div class="vacation-dates">
                                <span><?php echo date('j M Y', strtotime($vac['start_date'])); ?> - <?php echo date('j M Y', strtotime($vac['end_date'])); ?></span>
                                <span><strong><?php echo $vac['total_days']; ?> days</strong></span>
                            </div>
                            <div class="vacation-actions">
                                <a href="approve_vacation.php?approve=1&period_id=<?php echo $vac['period_id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('Approve this request?')">Approve</a>
                                <a href="approve_vacation.php?decline=1&period_id=<?php echo $vac['period_id']; ?>" class="btn btn-sm btn-outline" onclick="return confirm('Decline this request?')">Decline</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($reminders)): ?>
                <!-- Reminders -->
                <div class="card">
                    <div class="card-header">
                        <span class="card-title">Reminders</span>
                    </div>
                    <div class="card-body">
                        <?php foreach ($reminders as $reminder): ?>
                        <div class="reminder-item">
                            <span class="reminder-text"><?php echo htmlspecialchars($reminder['event']); ?></span>
                            <a href="index.php?hide_id=<?php echo $reminder['id']; ?>" class="reminder-hide">Hide</a>
                        </div>
                        <?php endforeach; ?>
                        <div style="margin-top: 12px;">
                            <a href="index.php?hide_all=1" class="btn btn-sm btn-outline" style="width: 100%;">Hide All</a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>

            <!-- Main Content -->
            <div class="main-content">
                <!-- Top Row: Quick Tasks/Actions + Team Blocks -->
                <?php 
                $ukraine_team = array_filter($team_members, function($m) { return $m['is_viart'] == 1; });
                $uk_team = array_filter($team_members, function($m) { return $m['is_viart'] == 0; });
                ?>
                <div class="top-row">
                    <!-- Left: Quick Tasks & Actions -->
                    <div class="quick-section" id="quickSection">
                        <?php if ($current_task): ?>
                        <div class="quick-block" id="currentlyWorkingBlock">
                            <div class="quick-block-header">Currently Working On</div>
                            <div class="quick-block-body">
                                <a href="#" onclick="confirmTaskAction('stop', <?php echo $current_task['task_id']; ?>, '<?php echo escape_task_title_for_js($current_task['task_title']); ?>', <?php echo intval($current_task['completion']); ?>, <?php echo $current_task['is_periodic'] ? 'true' : 'false'; ?>); return false;" 
                                   class="btn-current-task">
                                    <span class="stop-icon">&#9632;</span>
                                    <span class="task-info">
                                        <span class="task-title" title="<?php echo htmlspecialchars($current_task['task_title']); ?>"><?php echo htmlspecialchars($current_task['task_title']); ?></span>
                                        <span class="task-project"><?php echo htmlspecialchars($current_task['project_title']); ?></span>
                                    </span>
                                    <span class="task-time" id="actual_time"><?php echo $current_task['actual_hours']; ?></span>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($periodic_tasks)): ?>
                        <div class="quick-block">
                            <div class="quick-block-header">Quick Tasks</div>
                            <div class="quick-block-body">
                                <?php foreach ($periodic_tasks as $pt): ?>
                                <?php $is_active = ($pt['status_id'] == 1); ?>
                                <a href="#" onclick="confirmTaskAction('<?php echo $is_active ? 'stop' : 'start'; ?>', <?php echo $pt['task_id']; ?>, '<?php echo escape_task_title_for_js($pt['task_title']); ?>', <?php echo intval($pt['completion']); ?>, true); return false;" 
                                   class="btn-quick <?php echo $is_active ? 'active' : ''; ?>">
                                    <?php echo $is_active ? '&#9632;' : '&#9654;'; ?>
                                    <?php echo htmlspecialchars($pt['task_title']); ?>
                                    <?php if ($is_active): ?><span class="quick-time"><?php echo $pt['actual_hours']; ?></span><?php endif; ?>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="quick-block">
                            <div class="quick-block-header">Quick Actions</div>
                            <div class="quick-block-body">
                                <a href="create_task.php" class="btn btn-primary btn-sm">+ New Task</a>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Time Log + Projects + Team Blocks -->
                    <div class="team-blocks">
                        <!-- Today's Time Log -->
                        <?php
                        $today_date = date('Y-m-d');
                        $today_year = date('Y');
                        $today_month = date('m');
                        $time_log_url = "my_stats.php?year_selected={$today_year}&month_selected={$today_month}&task_report=1&user_id={$session_user_id}&start_date={$today_date}&end_date={$today_date}";
                        ?>
                        <div class="team-block" id="timeLogBlock">
                            <div class="team-block-header" style="display:flex;justify-content:space-between;align-items:center;">
                                <a href="<?php echo $time_log_url; ?>" style="color:inherit;text-decoration:none;" title="View full time log details">Today's Time</a>
                                <?php
                                $total_h = floor($today_total_hours);
                                $total_m = round(($today_total_hours - $total_h) * 60);
                                $total_display = '';
                                if ($total_h > 0) $total_display .= $total_h . 'h';
                                if ($total_m > 0 || $total_h == 0) $total_display .= ($total_h > 0 ? ' ' : '') . $total_m . 'm';
                                ?>
                                <a href="<?php echo $time_log_url; ?>" style="font-size:0.78rem;color:#667eea;font-weight:700;text-decoration:none;" id="timeTotalDisplay" title="View details"><?php echo $total_display; ?></a>
                            </div>
                            <div class="team-block-body" style="max-height:240px;">
                                <?php if (!empty($today_time_log)): ?>
                                <?php foreach ($today_time_log as $tl): ?>
                                <?php
                                    $h = floor($tl['hours']);
                                    $m = round(($tl['hours'] - $h) * 60);
                                    $time_str = '';
                                    if ($h > 0) $time_str .= $h . 'h';
                                    if ($m > 0 || $h == 0) $time_str .= ($h > 0 ? ' ' : '') . $m . 'm';
                                    $is_running = !empty($tl['is_running']);
                                ?>
                                <a href="<?php echo $time_log_url; ?>" class="team-row" style="justify-content:space-between;">
                                    <span class="team-name" style="max-width:130px;"><?php echo htmlspecialchars($tl['project_title']); ?></span>
                                    <span class="tl-time <?php echo $is_running ? 'tl-running' : ''; ?>"><?php if ($is_running): ?><span style="display:inline-block;width:6px;height:6px;background:#38a169;border-radius:50%;margin-right:4px;animation:pulse 1.5s infinite;vertical-align:middle;"></span><?php endif; ?><?php echo $time_str; ?></span>
                                </a>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <a href="<?php echo $time_log_url; ?>" style="display:block;padding:12px;color:#a0aec0;font-size:0.8rem;text-align:center;text-decoration:none;">No time logged today</a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Projects Block with Tabs -->
                        <?php if (!empty($projects_list) || !empty($my_projects_list)): ?>
                        <div class="team-block" id="projectsBlock">
                            <div class="project-tabs">
                                <div class="project-tab active" data-tab="my-projects" onclick="switchProjectTab('my-projects')">
                                    My Projects <span class="project-tab-count"><?php echo count($my_projects_list); ?></span>
                                </div>
                                <div class="project-tab" data-tab="all-projects" onclick="switchProjectTab('all-projects')">
                                    Active <span class="project-tab-count"><?php echo count($projects_list); ?></span>
                                </div>
                                <a href="edit_project.php" class="project-add-btn" title="Create new project">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                </a>
                            </div>
                            <div class="project-tab-panel active" id="tab-my-projects">
                                <div class="team-block-body">
                                    <?php if (!empty($my_projects_list)): ?>
                                    <?php foreach ($my_projects_list as $project): ?>
                                    <a href="view_project_tasks.php?project_id=<?php echo $project['project_id']; ?>" class="team-row">
                                        <span class="team-name"><?php echo htmlspecialchars($project['project_title']); ?></span>
                                        <span class="team-tasks">(<?php echo $project['open_tasks']; ?>)</span>
                                    </a>
                                    <?php endforeach; ?>
                                    <?php else: ?>
                                    <div style="padding: 12px; color: #a0aec0; font-size: 0.8rem; text-align: center;">No projects</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="project-tab-panel" id="tab-all-projects">
                                <div class="team-block-body">
                                    <?php if (!empty($projects_list)): ?>
                                    <?php foreach ($projects_list as $project): ?>
                                    <a href="view_project_tasks.php?project_id=<?php echo $project['project_id']; ?>" class="team-row">
                                        <span class="team-name"><?php echo htmlspecialchars($project['project_title']); ?></span>
                                        <span class="team-tasks">(<?php echo $project['open_tasks']; ?>)</span>
                                    </a>
                                    <?php endforeach; ?>
                                    <?php else: ?>
                                    <div style="padding: 12px; color: #a0aec0; font-size: 0.8rem; text-align: center;">No active projects</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($ukraine_team)): ?>
                        <div class="team-block">
                            <div class="team-block-header">
                                <span>Sayu Ukraine (<?php echo count($ukraine_team); ?>)</span>
                            </div>
                            <div class="team-block-body">
                                <?php foreach ($ukraine_team as $member): ?>
                                <a href="report_people.php?report_user_id=<?php echo $member['user_id']; ?>" class="team-row <?php echo $member['status']; ?>">
                                    <span class="team-name"><?php echo htmlspecialchars($member['user_name']); ?></span>
                                    <span class="team-tasks">(<?php echo $member['opened_tasks']; ?>)</span>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($uk_team)): ?>
                        <div class="team-block">
                            <div class="team-block-header">
                                <span>Sayu UK (<?php echo count($uk_team); ?>)</span>
                            </div>
                            <div class="team-block-body">
                                <?php foreach ($uk_team as $member): ?>
                                <a href="report_people.php?report_user_id=<?php echo $member['user_id']; ?>" class="team-row <?php echo $member['status']; ?>">
                                    <span class="team-name"><?php echo htmlspecialchars($member['user_name']); ?></span>
                                    <span class="team-tasks">(<?php echo $member['opened_tasks']; ?>)</span>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- My Tasks -->
                <div class="card">
                    <div class="card-header">
                        <span class="card-title">My Tasks (<?php echo count($my_tasks); ?>)</span>
                        <?php if (!empty($my_tasks)): ?>
                        <div class="view-toggle" id="myTasksViewToggle">
                            <button class="view-toggle-btn active" data-view="list" onclick="switchMyTasksView('list')">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                                List
                            </button>
                            <button class="view-toggle-btn" data-view="cards" onclick="switchMyTasksView('cards')">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                                By Project
                            </button>
                            <button class="view-toggle-btn" data-view="byStatus" onclick="switchMyTasksView('byStatus')">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                                By Status
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($my_tasks)): ?>
                    <div class="my-tasks-list-view active" id="myTasksListView">
                    <div class="bulk-bar" id="bulkBar">
                        <span class="bulk-count" id="bulkCount">0</span> tasks selected
                        <div class="bulk-actions">
                            <button class="bulk-btn" id="bulkStatusBtn" onclick="toggleBulkDropdown('status')">Change Status</button>
                            <button class="bulk-btn" id="bulkReassignBtn" onclick="toggleBulkDropdown('reassign')">Reassign</button>
                            <button class="bulk-btn danger" id="bulkCloseBtn" onclick="bulkCloseTasks()">Close</button>
                            <button class="bulk-btn-cancel" onclick="clearSelection()">Clear</button>
                        </div>
                    </div>
                    <div class="scroll-table">
                        <table class="data-table" id="myTasksTable">
                            <thead>
                                <tr>
                                    <th style="width:32px;padding:12px 6px 12px 12px;"><input type="checkbox" id="selectAllTasks" onclick="toggleSelectAll(this)" style="width:16px;height:16px;accent-color:#667eea;cursor:pointer;"></th>
                                    <th class="sortable col-project" data-sort="text" data-col="1">Project</th>
                                    <th class="sortable" data-sort="text" data-col="2">Task</th>
                                    <th class="text-center sortable col-priority" data-sort="number" data-col="3">Pr</th>
                                    <th class="text-center sortable" data-sort="text" data-col="4">Status</th>
                                    <th class="text-center sortable col-actual" data-sort="time" data-col="5">Actual</th>
                                    <th class="text-center sortable col-modified" data-sort="date" data-col="6">Modified</th>
                                    <th class="text-center sortable col-completion" data-sort="number" data-col="7">%</th>
                                    <th class="text-center sortable col-created" data-sort="date" data-col="8">Created</th>
                                    <th class="text-center sortable col-deadline" data-sort="date" data-col="9">Deadline</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($my_tasks as $task): ?>
                                <tr class="clickable-row" onclick="window.location='edit_task.php?task_id=<?php echo $task['task_id']; ?>'" data-task-id="<?php echo $task['task_id']; ?>" data-task-title="<?php echo htmlspecialchars($task['task_title'], ENT_QUOTES); ?>" data-task-status="<?php echo $task['status_id']; ?>" data-task-completion="<?php echo intval($task['completion']); ?>" data-task-periodic="<?php echo $task['is_periodic'] ? '1' : '0'; ?>" data-project-id="<?php echo $task['project_id']; ?>" data-last-modified="<?php echo $task['last_modified_raw'] ?: ''; ?>">
                                    <td style="padding:12px 6px 12px 12px;" onclick="event.stopPropagation()"><input type="checkbox" class="task-check" value="<?php echo $task['task_id']; ?>" onclick="updateBulkBar()" style="width:16px;height:16px;accent-color:#667eea;cursor:pointer;"></td>
                                    <td class="col-project"><strong><?php echo htmlspecialchars($task['project_title']); ?></strong></td>
                                    <td class="task-title-cell">
                                        <?php echo htmlspecialchars($task['task_title']); ?>
                                    </td>
                                    <td class="text-center col-priority">
                                        <span class="priority-badge" style="background: <?php echo isset($priority_colors[$task['priority_id']]) ? $priority_colors[$task['priority_id']] : '#718096'; ?>">
                                            <?php echo $task['priority_id']; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php 
                                        $status_class = 'status-not-started';
                                        if ($task['status_id'] == 1) $status_class = 'status-in-progress';
                                        elseif ($task['status_id'] == 2) $status_class = 'status-on-hold';
                                        ?>
                                        <span class="status-badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($task['status_desc']); ?></span>
                                    </td>
                                    <td class="text-center col-actual"><?php echo $task['actual_hours']; ?></td>
                                    <td class="text-center col-modified"><?php echo $task['last_modified'] ?: '-'; ?></td>
                                    <td class="text-center col-completion"><?php echo $task['completion'] ? $task['completion'] . '%' : '-'; ?></td>
                                    <td class="text-center col-created"><?php echo $task['creation_date']; ?></td>
                                    <td class="text-center col-deadline"><?php echo $task['planed_date'] ?: '-'; ?></td>
                                    <td>
                                        <div class="task-actions" onclick="event.stopPropagation()">
                                            <?php if ($task['status_id'] == 1): ?>
                                            <a href="#" onclick="confirmTaskAction('stop', <?php echo $task['task_id']; ?>, '<?php echo escape_task_title_for_js($task['task_title']); ?>', <?php echo intval($task['completion']); ?>, <?php echo $task['is_periodic'] ? 'true' : 'false'; ?>); return false;" class="action-link action-stop">Stop</a>
                                            <?php else: ?>
                                            <a href="#" onclick="confirmTaskAction('start', <?php echo $task['task_id']; ?>, '<?php echo escape_task_title_for_js($task['task_title']); ?>', <?php echo intval($task['completion']); ?>, <?php echo $task['is_periodic'] ? 'true' : 'false'; ?>); return false;" class="action-link action-start">Start</a>
                                            <?php endif; ?>
                                            <a href="#" onclick="confirmTaskAction('close', <?php echo $task['task_id']; ?>, '<?php echo escape_task_title_for_js($task['task_title']); ?>', <?php echo intval($task['completion']); ?>); return false;" class="action-link action-close">Close</a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    </div><!-- /my-tasks-list-view -->

                    <div class="my-tasks-cards-view" id="myTasksCardsView">
                        <div class="kb-board">
                            <?php foreach ($my_tasks_by_project as $pid => $proj): ?>
                            <div class="kb-column kb-col-new" data-project-id="<?php echo $pid; ?>">
                                <div class="kb-column-header">
                                    <a href="view_project_tasks.php?project_id=<?php echo $pid; ?>" class="kb-column-title"><?php echo htmlspecialchars($proj['title']); ?></a>
                                    <span class="kb-column-count"><?php echo count($proj['tasks']); ?></span>
                                </div>
                                <div class="kb-cards">
                                    <?php foreach ($proj['tasks'] as $t):
                                        $sc = 'status-not-started';
                                        if ($t['status_id'] == 1) $sc = 'status-in-progress';
                                        elseif ($t['status_id'] == 2) $sc = 'status-on-hold';
                                        elseif ($t['status_id'] == 8) $sc = 'status-waiting';
                                        elseif ($t['status_id'] == 9) $sc = 'status-reassigned';
                                        elseif ($t['status_id'] == 10) $sc = 'status-bug';
                                    ?>
                                    <div class="kb-card <?php echo $t['is_overdue'] ? 'overdue' : ''; ?>" draggable="true" onclick="window.location='edit_task.php?task_id=<?php echo $t['task_id']; ?>'" data-task-id="<?php echo $t['task_id']; ?>" data-priority-id="<?php echo (int)$t['priority_id']; ?>" data-task-title="<?php echo htmlspecialchars($t['task_title'], ENT_QUOTES); ?>" data-task-status="<?php echo $t['status_id']; ?>" data-task-completion="<?php echo intval($t['completion']); ?>" data-task-periodic="<?php echo $t['is_periodic'] ? '1' : '0'; ?>" data-project-id="<?php echo $pid; ?>">
                                        <div class="kb-card-title">
                                            <a href="edit_task.php?task_id=<?php echo $t['task_id']; ?>" onclick="event.stopPropagation()"><?php echo htmlspecialchars($t['task_title']); ?></a>
                                        </div>
                                        <div class="kb-card-meta">
                                            <?php if ($t['actual_hours'] && $t['actual_hours'] !== '0'): ?>
                                            <span class="kb-card-meta-item">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                                <?php echo $t['actual_hours']; ?>
                                            </span>
                                            <?php endif; ?>
                                            <?php if ($t['completion'] > 0): ?>
                                            <span class="kb-card-meta-item">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                                                <?php echo $t['completion']; ?>%
                                            </span>
                                            <?php endif; ?>
                                            <?php if ($t['planed_date']): ?>
                                            <span class="kb-card-meta-item <?php echo $t['is_overdue'] ? 'overdue-text' : ''; ?>">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                                <?php echo $t['planed_date']; ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="kb-card-footer">
                                            <span class="status-badge <?php echo $sc; ?>" style="font-size:0.62rem;padding:1px 6px;"><?php echo htmlspecialchars($t['status_desc']); ?></span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div><!-- /my-tasks-cards-view -->

                    <div class="my-tasks-status-view" id="myTasksStatusView">
                        <div class="kb-board">
                            <?php foreach ($status_kanban as $col_key => $col): ?>
                            <div class="kb-column <?php echo $col['color_class']; ?>" data-col-key="<?php echo $col_key; ?>" data-status-id="<?php echo $col['primary_status']; ?>">
                                <div class="kb-column-header">
                                    <span class="kb-column-title"><?php echo htmlspecialchars($col['title']); ?></span>
                                    <span class="kb-column-count"><?php echo count($col['tasks']); ?></span>
                                </div>
                                <div class="kb-cards" data-col-key="<?php echo $col_key; ?>">
                                    <?php if (empty($col['tasks'])): ?>
                                    <div class="kb-empty">No tasks</div>
                                    <?php else: ?>
                                    <?php foreach ($col['tasks'] as $t):
                                        $sc = 'status-not-started';
                                        if ($t['status_id'] == 1) $sc = 'status-in-progress';
                                        elseif ($t['status_id'] == 2) $sc = 'status-on-hold';
                                        elseif ($t['status_id'] == 8) $sc = 'status-waiting';
                                        elseif ($t['status_id'] == 9) $sc = 'status-reassigned';
                                        elseif ($t['status_id'] == 10) $sc = 'status-bug';
                                    ?>
                                    <div class="kb-card <?php echo $t['is_overdue'] ? 'overdue' : ''; ?>" draggable="true" data-task-id="<?php echo $t['task_id']; ?>" data-priority-id="<?php echo (int)$t['priority_id']; ?>" data-task-title="<?php echo htmlspecialchars($t['task_title'], ENT_QUOTES); ?>" data-task-status="<?php echo $t['status_id']; ?>" data-task-completion="<?php echo intval($t['completion']); ?>" data-task-periodic="<?php echo $t['is_periodic'] ? '1' : '0'; ?>" data-project-id="<?php echo $t['project_id']; ?>" data-task-assign="<?php echo $session_user_id; ?>" data-last-modified="<?php echo $t['last_modified_raw'] ?: ''; ?>">
                                        <div class="kb-card-title">
                                            <a href="edit_task.php?task_id=<?php echo $t['task_id']; ?>" onclick="event.stopPropagation()"><?php echo htmlspecialchars($t['task_title']); ?></a>
                                        </div>
                                        <div class="kb-card-meta">
                                            <span class="kb-card-project"><?php echo htmlspecialchars($t['project_title']); ?></span>
                                            <?php if ($t['actual_hours'] && $t['actual_hours'] !== '0'): ?>
                                            <span class="kb-card-meta-item">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                                <?php echo $t['actual_hours']; ?>
                                            </span>
                                            <?php endif; ?>
                                            <?php if ($t['completion'] > 0): ?>
                                            <span class="kb-card-meta-item">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                                                <?php echo $t['completion']; ?>%
                                            </span>
                                            <?php endif; ?>
                                            <?php if ($t['planed_date']): ?>
                                            <span class="kb-card-meta-item <?php echo $t['is_overdue'] ? 'overdue-text' : ''; ?>">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                                <?php echo $t['planed_date']; ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="kb-card-footer">
                                            <span class="status-badge <?php echo $sc; ?>" style="font-size:0.62rem;padding:1px 6px;"><?php echo htmlspecialchars($t['status_desc']); ?></span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="kb-add-area" data-col-key="<?php echo $col_key; ?>" data-status-id="<?php echo $col['primary_status']; ?>">
                                    <button class="kb-add-btn" onclick="kbShowAddForm(this)">+ Add Task</button>
                                    <div class="kb-add-form" style="display:none">
                                        <textarea class="kb-add-input" placeholder="Enter a title for this card..." rows="2"></textarea>
                                        <select class="kb-add-project">
                                            <?php foreach ($my_projects_list as $p): ?>
                                            <option value="<?php echo $p['project_id']; ?>"><?php echo htmlspecialchars($p['project_title']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="kb-add-actions">
                                            <button class="kb-add-submit" onclick="kbSubmitCard(this)">Add Task</button>
                                            <button class="kb-add-cancel" onclick="kbCancelAdd(this)">&times;</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div><!-- /my-tasks-status-view -->

                    <div class="quick-links">
                        <a href="create_task.php">Add New Task</a>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <p>No tasks assigned to you</p>
                        <a href="create_task.php" class="btn btn-primary" style="margin-top: 16px;">Create Task</a>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <!-- Drop Message Modal -->
    <div id="kbDropModal" class="kb-modal-overlay">
        <div class="kb-modal">
            <div class="kb-modal-header">
                <h3>Move Task</h3>
                <button type="button" class="kb-modal-close" onclick="closeKbDropModal()">&times;</button>
            </div>
            <div class="kb-modal-body">
                <div class="kb-modal-task" id="kbDropTaskName"></div>
                <div class="kb-modal-info" id="kbDropInfo"></div>

                <div class="kb-modal-fields">
                    <div class="kb-modal-field">
                        <label>Assign to:</label>
                        <input type="hidden" id="kbDropAssign" value="<?php echo (int)$session_user_id; ?>">
                        <div class="kb-select" id="kbSelectAssign">
                            <div class="kb-select-trigger" tabindex="0">
                                <span><?php
                                    foreach ($ctx_users as $u) {
                                        if ($u['id'] == (int)$session_user_id) { echo htmlspecialchars($u['name']); break; }
                                    }
                                ?></span>
                            </div>
                            <div class="kb-select-dropdown">
                                <div class="kb-select-search"><input type="text" placeholder="Search..." class="kb-search-input"></div>
                                <div class="kb-select-options">
                                    <?php foreach ($ctx_users as $u): ?>
                                    <div class="kb-select-option<?php echo $u['id'] == (int)$session_user_id ? ' selected' : ''; ?>" data-value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?></div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="kb-modal-field">
                        <label>Status:</label>
                        <input type="hidden" id="kbDropStatus" value="">
                        <div class="kb-select" id="kbSelectStatus">
                            <div class="kb-select-trigger" tabindex="0">
                                <span></span>
                            </div>
                            <div class="kb-select-dropdown">
                                <div class="kb-select-search"><input type="text" placeholder="Search..." class="kb-search-input"></div>
                                <div class="kb-select-options">
                                    <?php foreach ($ctx_statuses as $s): ?>
                                    <div class="kb-select-option" data-value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="kb-modal-field kb-modal-field-short">
                        <label>Completion:</label>
                        <div class="kb-completion-wrap">
                            <input type="number" id="kbDropCompletion" min="0" max="100" value="0">
                            <span>%</span>
                        </div>
                    </div>
                </div>

                <textarea class="kb-modal-textarea" id="kbDropMessage" placeholder="Write a message... (paste or drop files here)"></textarea>

                <div class="kb-modal-attachments">
                    <div class="kb-modal-dropzone" id="kbDropzone">
                        📎 Drop files here or <label for="kbFileInput" class="kb-file-label">browse</label>
                        <input type="file" id="kbFileInput" multiple style="display:none;">
                    </div>
                    <div class="kb-modal-file-list" id="kbFileList"></div>
                </div>
            </div>
            <div class="kb-modal-footer">
                <button class="modal-btn modal-btn-cancel" onclick="closeKbDropModal()">Cancel</button>
                <button class="modal-btn modal-btn-confirm" id="kbDropSubmitBtn" onclick="executeKbDrop()">Move &amp; Send</button>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmModal" class="modal-overlay">
        <div class="modal-box">
            <div id="modalHeader" class="modal-header">
                <h3 id="modalTitle">Confirm Action</h3>
            </div>
            <div class="modal-body">
                <div id="modalTaskName" class="modal-task-name"></div>
                <div id="modalMessage" class="modal-message"></div>
                <div id="modalCompletion" class="modal-completion">
                    <label>Completion %</label>
                    <input type="number" id="completionInput" min="0" max="100" value="0"><span>%</span>
                </div>
            </div>
            <div class="modal-footer">
                <button class="modal-btn modal-btn-cancel" onclick="closeModal()">Cancel</button>
                <button id="modalConfirmBtn" class="modal-btn modal-btn-confirm" onclick="executeAction()">Confirm</button>
            </div>
        </div>
    </div>

    <script>
    // My Tasks view toggle (List / By Project / By Status)
    function switchMyTasksView(view) {
        document.querySelectorAll('#myTasksViewToggle .view-toggle-btn').forEach(function(btn) {
            btn.classList.toggle('active', btn.dataset.view === view);
        });
        var listView = document.getElementById('myTasksListView');
        var cardsView = document.getElementById('myTasksCardsView');
        var statusView = document.getElementById('myTasksStatusView');
        if (listView) listView.classList.toggle('active', view === 'list');
        if (cardsView) cardsView.classList.toggle('active', view === 'cards');
        if (statusView) statusView.classList.toggle('active', view === 'byStatus');
        try { localStorage.setItem('indexMyTasksView', view); } catch(e) {}
    }
    // Restore saved view
    (function() {
        try {
            var saved = localStorage.getItem('indexMyTasksView');
            if (saved === 'cards' || saved === 'byStatus') switchMyTasksView(saved);
        } catch(e) {}
    })();

    // ==================== Kanban Drag & Drop (By Status) ====================
    var kbDragTaskId = null;
    var kbDragNewStatus = null;
    var kbDragTargetCol = null;
    var kbDropInsertBefore = null; // node before which to insert card (preserves drop order)
    var kbDragCard = null;
    var kbSessionUserId = <?php echo (int)$session_user_id; ?>;
    window._kbPriorityDebug = true; // set false to disable console logs for priority save
    var kbColNames = { 'new': 'New', 'progress': 'In Progress', 'hold': 'On Hold / Waiting', 'done': 'Done' };
    var kbHash = Math.random().toString(16).substr(2, 8);
    var kbAttachments = [];
    var kbAttachmentId = 0;

    (function() {
        var boards = document.querySelectorAll('.kb-board');
        if (!boards.length) return;

        function attachDragHandlers(board) {
            board.addEventListener('dragstart', function(e) {
                var card = e.target.closest('.kb-card');
                if (!card) return;
                kbDragCard = card;
                card.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', card.dataset.taskId);
            });

            board.addEventListener('dragend', function(e) {
                var card = e.target.closest('.kb-card');
                if (card) card.classList.remove('dragging');
                board.querySelectorAll('.kb-column').forEach(function(c) { c.classList.remove('drag-over'); });
                board.querySelectorAll('.kb-drop-indicator').forEach(function(d) { d.remove(); });
                kbDragCard = null;
            });

            board.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                var col = e.target.closest('.kb-column');
                if (!col) return;
                board.querySelectorAll('.kb-column').forEach(function(c) { c.classList.toggle('drag-over', c === col); });

                // Show drop indicator at position where the card will be placed
                var cardsContainer = col.querySelector('.kb-cards');
                if (!cardsContainer || !kbDragCard) return;
                var cards = Array.from(cardsContainer.children).filter(function(el) { return el.classList.contains('kb-card') && el !== kbDragCard; });
                var rect = cardsContainer.getBoundingClientRect();
                var y = e.clientY;
                var insertBefore = null;
                for (var i = 0; i < cards.length; i++) {
                    var cr = cards[i].getBoundingClientRect();
                    var mid = cr.top + cr.height / 2;
                    if (y < mid) {
                        insertBefore = cards[i];
                        break;
                    }
                }
                board.querySelectorAll('.kb-drop-indicator').forEach(function(d) { d.remove(); });
                var indicator = document.createElement('div');
                indicator.className = 'kb-drop-indicator';
                if (insertBefore) {
                    cardsContainer.insertBefore(indicator, insertBefore);
                } else {
                    cardsContainer.appendChild(indicator);
                }
            });

            board.addEventListener('dragleave', function(e) {
                var col = e.target.closest('.kb-column');
                if (col && !col.contains(e.relatedTarget)) {
                    col.classList.remove('drag-over');
                }
            });

            board.addEventListener('drop', function(e) {
                e.preventDefault();
                var col = e.target.closest('.kb-column');
                if (!col || !kbDragCard) return;
                var cardsContainer = col.querySelector('.kb-cards');
                var indicator = cardsContainer ? cardsContainer.querySelector('.kb-drop-indicator') : null;
                kbDropInsertBefore = indicator ? indicator.nextElementSibling : null;

                board.querySelectorAll('.kb-column').forEach(function(c) { c.classList.remove('drag-over'); });
                board.querySelectorAll('.kb-drop-indicator').forEach(function(d) { d.remove(); });

                // By Project: move task to another project
                if (col.dataset.projectId !== undefined) {
                    var newProjectId = col.dataset.projectId;
                    var card = kbDragCard;
                    var oldProjectId = card.dataset.projectId;
                    if (newProjectId === oldProjectId) {
                        kbInsertCardAndAnimate(cardsContainer, card, kbDropInsertBefore);
                        kbDropInsertBefore = null;
                        kbSaveColumnPriorities(cardsContainer);
                        return;
                    }
                    var taskId = card.dataset.taskId;
                    var targetCol = col;
                    var targetBoard = board;
                    var insertBefore = kbDropInsertBefore;
                    fetch('ajax_responder.php?action=move_task_project&task_id=' + taskId + '&project_id=' + newProjectId, { credentials: 'include' })
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            if (data.success && data.changed) {
                                var cardsContainer = targetCol.querySelector('.kb-cards');
                                card.dataset.projectId = newProjectId;
                                kbInsertCardAndAnimate(cardsContainer, card, insertBefore);
                                targetBoard.querySelectorAll('.kb-column').forEach(function(c) {
                                    var countEl = c.querySelector('.kb-column-count');
                                    if (countEl) countEl.textContent = c.querySelectorAll('.kb-card').length;
                                });
                                kbSaveColumnPriorities(targetCol.querySelector('.kb-cards'));
                                showKbToast('Task moved to ' + (data.project_title || 'project'));
                            } else {
                                showKbToast(data.error || 'Failed to move task', true);
                            }
                            kbDropInsertBefore = null;
                        })
                        .catch(function() { showKbToast('Network error', true); kbDropInsertBefore = null; });
                    return;
                }

                // By Status: show drop modal
                var taskId = kbDragCard.dataset.taskId;
                var newStatusId = col.dataset.statusId;
                var oldStatusId = kbDragCard.dataset.taskStatus;
                var taskTitle = kbDragCard.dataset.taskTitle;
                var colKey = col.dataset.colKey;

                if (newStatusId === oldStatusId) {
                    kbInsertCardAndAnimate(cardsContainer, kbDragCard, kbDropInsertBefore);
                    kbDropInsertBefore = null;
                    kbSaveColumnPriorities(cardsContainer);
                    return;
                }

                kbDragTaskId = taskId;
                kbDragNewStatus = newStatusId;
                kbDragTargetCol = col;
                document.getElementById('kbDropTaskName').textContent = taskTitle;
                document.getElementById('kbDropInfo').textContent = 'Moving to: ' + (kbColNames[colKey] || colKey);
                document.getElementById('kbDropMessage').value = '';

                var statusSelect = document.getElementById('kbSelectStatus');
                if (statusSelect && statusSelect._pickByValue) statusSelect._pickByValue(newStatusId);
                document.getElementById('kbDropStatus').value = newStatusId;

                var currentAssign = kbDragCard.dataset.taskAssign || '';
                if (currentAssign) {
                    var assignSelect = document.getElementById('kbSelectAssign');
                    if (assignSelect && assignSelect._pickByValue) assignSelect._pickByValue(currentAssign);
                    document.getElementById('kbDropAssign').value = currentAssign;
                }
                document.getElementById('kbDropCompletion').value = kbDragCard.dataset.taskCompletion || 0;

                kbAttachments = [];
                kbAttachmentId = 0;
                document.getElementById('kbFileList').innerHTML = '';
                kbHash = Math.random().toString(16).substr(2, 8);

                document.getElementById('kbDropModal').classList.add('active');
                setTimeout(function() { document.getElementById('kbDropMessage').focus(); }, 100);
            });
        }

        boards.forEach(attachDragHandlers);
    })();

    function closeKbDropModal() {
        document.getElementById('kbDropModal').classList.remove('active');
        document.querySelectorAll('#kbDropModal .kb-select.open').forEach(function(s) { s.classList.remove('open'); });
        kbDragTaskId = null;
        kbDragNewStatus = null;
        kbDragTargetCol = null;
        kbDropInsertBefore = null;
    }

    function kbInsertCardAndAnimate(container, card, insertBeforeNode) {
        if (!container || !card) return;
        if (insertBeforeNode && insertBeforeNode.parentNode === container) {
            container.insertBefore(card, insertBeforeNode);
        } else {
            container.appendChild(card);
        }
        card.classList.add('kb-card-just-moved');
        setTimeout(function() { card.classList.remove('kb-card-just-moved'); }, 2000);
    }

    function kbSaveColumnPriorities(cardsContainer) {
        var debug = window._kbPriorityDebug;
        if (debug) console.log('[kbPriority] kbSaveColumnPriorities called', cardsContainer);
        if (!cardsContainer || typeof kbSessionUserId === 'undefined') {
            if (debug) console.log('[kbPriority] skip: no container or kbSessionUserId');
            return;
        }
        var board = cardsContainer.closest('.kb-board');
        if (!board) {
            if (debug) console.log('[kbPriority] skip: no .kb-board');
            return;
        }
        var columns = board.querySelectorAll('.kb-column');
        var orderedTaskIds = [];
        var perColumn = [];
        for (var c = 0; c < columns.length; c++) {
            var cc = columns[c].querySelector('.kb-cards');
            if (!cc) continue;
            var cards = cc.querySelectorAll('.kb-card');
            var colIds = [];
            for (var i = 0; i < cards.length; i++) {
                var tid = cards[i].getAttribute('data-task-id');
                if (tid) {
                    var idNum = parseInt(tid, 10);
                    orderedTaskIds.push(idNum);
                    colIds.push(idNum);
                }
            }
            perColumn.push({ colIndex: c, colKey: columns[c].getAttribute('data-col-key') || columns[c].getAttribute('data-project-id') || c, taskIds: colIds });
        }
        if (debug) {
            console.log('[kbPriority] orderedTaskIds (full order sent to server)', orderedTaskIds);
            console.log('[kbPriority] per column', perColumn);
        }
        if (orderedTaskIds.length === 0) {
            if (debug) console.log('[kbPriority] skip: no cards');
            return;
        }
        var priorities = orderedTaskIds.map(function(tid, i) { return { task_id: tid, priority: i + 1 }; });
        var body = 'action=save_task_priorities&user_id=' + kbSessionUserId + '&priorities=' + encodeURIComponent(JSON.stringify(priorities));
        if (debug) console.log('[kbPriority] POST priorities', { user_id: kbSessionUserId, priorities: priorities, bodyLength: body.length });
        fetch('ajax_responder.php', { method: 'POST', body: body, headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, credentials: 'include' })
            .then(function(r) {
                if (debug) console.log('[kbPriority] response status', r.status, r.url);
                return r.json();
            })
            .then(function(data) {
                if (debug) console.log('[kbPriority] response data', data);
                if (data.success && priorities.length) {
                    var map = {};
                    priorities.forEach(function(p) { map[p.task_id] = p.priority; });
                    board.querySelectorAll('.kb-card').forEach(function(card) {
                        var tid = parseInt(card.getAttribute('data-task-id'), 10);
                        if (map[tid] !== undefined) card.setAttribute('data-priority-id', String(map[tid]));
                    });
                }
            })
            .catch(function(err) {
                console.error('[kbPriority] fetch error', err);
            });
    }

    // Close drop modal on overlay click
    document.getElementById('kbDropModal').addEventListener('click', function(e) {
        if (e.target === this) closeKbDropModal();
    });

    // Keyboard shortcuts for drop modal
    document.addEventListener('keydown', function(e) {
        var modal = document.getElementById('kbDropModal');
        if (!modal.classList.contains('active')) return;
        if (e.key === 'Escape') {
            closeKbDropModal();
        }
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            executeKbDrop();
        }
    });

    // ==================== Kanban Modal Custom Selects ====================
    (function() {
        document.querySelectorAll('#kbDropModal .kb-select').forEach(function(select) {
            var trigger = select.querySelector('.kb-select-trigger');
            var dropdown = select.querySelector('.kb-select-dropdown');
            var options = select.querySelectorAll('.kb-select-option');
            var searchInput = select.querySelector('.kb-search-input');
            var hiddenInput = select.previousElementSibling;
            var highlightedIndex = -1;

            // Find the hidden input (it's before the kb-select div)
            if (!hiddenInput || hiddenInput.type !== 'hidden') {
                hiddenInput = select.parentElement.querySelector('input[type="hidden"]');
            }

            function getVisibleOptions() {
                return Array.from(options).filter(function(o) { return !o.classList.contains('hidden'); });
            }

            function updateHighlight(idx) {
                var visible = getVisibleOptions();
                options.forEach(function(o) { o.classList.remove('highlighted'); });
                if (idx >= 0 && idx < visible.length) {
                    highlightedIndex = idx;
                    visible[idx].classList.add('highlighted');
                    visible[idx].scrollIntoView({ block: 'nearest' });
                }
            }

            trigger.addEventListener('click', function(e) {
                e.stopPropagation();
                document.querySelectorAll('#kbDropModal .kb-select.open').forEach(function(other) {
                    if (other !== select) other.classList.remove('open');
                });
                select.classList.toggle('open');
                if (select.classList.contains('open')) {
                    highlightedIndex = -1;
                    if (searchInput) { searchInput.focus(); searchInput.value = ''; filterOpts(''); }
                    else trigger.focus();
                }
            });

            trigger.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    if (!select.classList.contains('open')) { trigger.click(); }
                    else {
                        var vis = getVisibleOptions();
                        if (highlightedIndex >= 0 && highlightedIndex < vis.length) pickOption(vis[highlightedIndex]);
                    }
                } else if (e.key === 'Escape') {
                    e.preventDefault(); select.classList.remove('open');
                } else if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (!select.classList.contains('open')) trigger.click();
                    else updateHighlight(Math.min(highlightedIndex + 1, getVisibleOptions().length - 1));
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (select.classList.contains('open')) updateHighlight(Math.max(highlightedIndex - 1, 0));
                }
            });

            options.forEach(function(opt) {
                opt.addEventListener('click', function(e) { e.stopPropagation(); pickOption(opt); });
            });

            function pickOption(opt) {
                options.forEach(function(o) { o.classList.remove('selected', 'highlighted'); });
                opt.classList.add('selected');
                trigger.querySelector('span').textContent = opt.textContent.trim();
                if (hiddenInput) hiddenInput.value = opt.dataset.value;
                select.classList.remove('open');
                trigger.focus();
            }

            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    filterOpts(this.value.toLowerCase());
                    var vis = getVisibleOptions();
                    if (vis.length > 0) updateHighlight(0); else highlightedIndex = -1;
                });
                searchInput.addEventListener('click', function(e) { e.stopPropagation(); });
                searchInput.addEventListener('keydown', function(e) {
                    var vis = getVisibleOptions();
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        if (highlightedIndex === -1 && vis.length > 0) updateHighlight(0);
                        else updateHighlight(Math.min(highlightedIndex + 1, vis.length - 1));
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault(); updateHighlight(Math.max(highlightedIndex - 1, 0));
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        if (highlightedIndex >= 0 && highlightedIndex < vis.length) pickOption(vis[highlightedIndex]);
                        else if (vis.length > 0) pickOption(vis[0]);
                    } else if (e.key === 'Escape') {
                        e.preventDefault(); select.classList.remove('open'); trigger.focus();
                    }
                });
            }

            function filterOpts(query) {
                options.forEach(function(opt) {
                    if (opt.textContent.toLowerCase().indexOf(query) !== -1) opt.classList.remove('hidden');
                    else opt.classList.add('hidden');
                });
            }

            // Expose a programmatic select method
            select._pickByValue = function(val) {
                var found = Array.from(options).find(function(o) { return o.dataset.value === String(val); });
                if (found) {
                    options.forEach(function(o) { o.classList.remove('selected'); });
                    found.classList.add('selected');
                    trigger.querySelector('span').textContent = found.textContent.trim();
                    if (hiddenInput) hiddenInput.value = val;
                }
            };
        });

        // Close all kb-selects when clicking outside
        document.addEventListener('click', function() {
            document.querySelectorAll('#kbDropModal .kb-select.open').forEach(function(s) { s.classList.remove('open'); });
        });
    })();

    // ==================== Kanban Modal File Attachments ====================
    (function() {
        var dropzone = document.getElementById('kbDropzone');
        var fileInput = document.getElementById('kbFileInput');
        var textarea = document.getElementById('kbDropMessage');
        if (!dropzone || !fileInput || !textarea) return;

        fileInput.addEventListener('change', function(e) {
            kbHandleFiles(e.target.files);
            this.value = '';
        });

        dropzone.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.add('drag-over');
        });
        dropzone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.remove('drag-over');
        });
        dropzone.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.remove('drag-over');
            kbHandleFiles(e.dataTransfer.files);
        });
        dropzone.addEventListener('click', function(e) {
            if (e.target.tagName !== 'LABEL' && e.target.tagName !== 'INPUT') {
                fileInput.click();
            }
        });

        // Drag & drop on textarea
        textarea.addEventListener('dragover', function(e) {
            e.preventDefault();
            dropzone.classList.add('drag-over');
        });
        textarea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            dropzone.classList.remove('drag-over');
        });
        textarea.addEventListener('drop', function(e) {
            if (e.dataTransfer.files.length > 0) {
                e.preventDefault();
                dropzone.classList.remove('drag-over');
                kbHandleFiles(e.dataTransfer.files);
            }
        });

        // Paste images in textarea
        textarea.addEventListener('paste', function(e) {
            var items = e.clipboardData.items;
            var files = [];
            for (var i = 0; i < items.length; i++) {
                if (items[i].kind === 'file') {
                    var file = items[i].getAsFile();
                    if (file) files.push(file);
                }
            }
            if (files.length > 0) {
                e.preventDefault();
                kbHandleFiles(files);
            }
        });
    })();

    function kbHandleFiles(files) {
        for (var i = 0; i < files.length; i++) {
            kbUploadFile(files[i]);
        }
    }

    function kbUploadFile(file) {
        var id = ++kbAttachmentId;
        var isImage = file.type.startsWith('image/');
        var list = document.getElementById('kbFileList');
        var textarea = document.getElementById('kbDropMessage');

        var item = document.createElement('div');
        item.className = 'kb-file-item';
        item.id = 'kb-att-' + id;

        var iconClass = isImage ? 'image' : '';
        var iconText = isImage ? '🖼️' : '📄';

        item.innerHTML =
            '<div class="kb-fi-icon ' + iconClass + '">' + iconText + '</div>' +
            '<div class="kb-fi-info">' +
                '<div class="kb-fi-name">' + kbEscapeHtml(file.name) + '</div>' +
                '<div class="kb-fi-size">Uploading...</div>' +
            '</div>' +
            '<span class="kb-fi-remove" onclick="kbRemoveAttachment(' + id + ')">✕</span>';

        if (isImage) {
            var reader = new FileReader();
            reader.onload = function(e) {
                var preview = document.createElement('img');
                preview.className = 'kb-fi-preview';
                preview.src = e.target.result;
                item.insertBefore(preview, item.querySelector('.kb-fi-remove'));
            };
            reader.readAsDataURL(file);
        }

        list.appendChild(item);

        var formData = new FormData();
        formData.append('file', file);
        formData.append('hash', kbHash);
        formData.append('action', 'upload_temp_attachment');

        fetch('ajax_responder.php', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                kbAttachments.push({ id: id, name: data.safe_name, original_name: file.name });
                var sizeEl = item.querySelector('.kb-fi-size');
                sizeEl.textContent = kbFormatSize(file.size);
                if (textarea.value && !textarea.value.endsWith('\n')) textarea.value += '\n';
                textarea.value += '[' + data.safe_name + ']';
            } else {
                item.querySelector('.kb-fi-size').textContent = 'Upload failed';
                item.style.background = '#fed7d7';
            }
        })
        .catch(function() {
            item.querySelector('.kb-fi-size').textContent = 'Upload failed';
            item.style.background = '#fed7d7';
        });
    }

    function kbRemoveAttachment(id) {
        var item = document.getElementById('kb-att-' + id);
        if (item) item.remove();
        var textarea = document.getElementById('kbDropMessage');
        var att = kbAttachments.find(function(a) { return a.id === id; });
        if (att) {
            var ref = '[' + att.name + ']';
            textarea.value = textarea.value.replace(ref, '').replace(/\n\n+/g, '\n\n').trim();
        }
        kbAttachments = kbAttachments.filter(function(a) { return a.id !== id; });
    }

    function kbFormatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function kbEscapeHtml(text) {
        var d = document.createElement('div');
        d.textContent = text;
        return d.innerHTML;
    }

    // ==================== Execute Drop (Move & Send) ====================
    function executeKbDrop() {
        if (!kbDragTaskId) return;
        var taskId = kbDragTaskId;
        var targetCol = kbDragTargetCol;
        var statusId = document.getElementById('kbDropStatus').value;
        var assignId = document.getElementById('kbDropAssign').value;
        var completion = document.getElementById('kbDropCompletion').value;
        var message = document.getElementById('kbDropMessage').value.trim();
        var card = document.querySelector('.kb-card[data-task-id="' + taskId + '"]');

        var submitBtn = document.getElementById('kbDropSubmitBtn');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending...';

        // Use add_task_message which handles status, assign, completion, and files
        var formData = new FormData();
        formData.append('action', 'add_task_message');
        formData.append('task_id', taskId);
        formData.append('message', message);
        formData.append('responsible_user_id', assignId);
        formData.append('task_status_id', statusId);
        formData.append('task_completion', completion);
        formData.append('hash', kbHash);

        fetch('ajax_responder.php', { method: 'POST', body: formData, credentials: 'include' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Move & Send';

            if (data.success) {
                var insertBefore = kbDropInsertBefore;
                closeKbDropModal();

                // Move card to target column — use the card in the SAME board as targetCol so we don't move the wrong card (e.g. from the other view) and leave a duplicate
                var board = targetCol.closest('.kb-board');
                var card = board ? board.querySelector('.kb-card[data-task-id="' + taskId + '"]') : document.querySelector('.kb-card[data-task-id="' + taskId + '"]');
                if (card && targetCol) {
                    var cardsContainer = targetCol.querySelector('.kb-cards');
                    var emptyEl = cardsContainer.querySelector('.kb-empty');
                    if (emptyEl) emptyEl.remove();
                    kbInsertCardAndAnimate(cardsContainer, card, insertBefore);
                    kbDropInsertBefore = null;
                    kbSaveColumnPriorities(cardsContainer);
                    card.dataset.taskStatus = statusId;

                    // Update status badge
                    var badge = card.querySelector('.status-badge');
                    if (badge && data.task && data.task.status_desc) {
                        badge.textContent = data.task.status_desc;
                        badge.className = 'status-badge';
                        if (statusId == 1 || statusId == 11) badge.classList.add('status-in-progress');
                        else if (statusId == 2 || statusId == 8 || statusId == 9 || statusId == 10) badge.classList.add('status-on-hold');
                        else badge.classList.add('status-not-started');
                        badge.style.fontSize = '0.62rem';
                        badge.style.padding = '1px 6px';
                    }

                    updateKbColumnCounts();
                }

                // Update "Currently Working On" block
                if (statusId == 1) {
                    // Task moved to In Progress — update the block
                    var cardTitle = card ? card.getAttribute('data-task-title') : '';
                    var cardProjectEl = card ? card.querySelector('.kb-card-project') : null;
                    var cardProject = cardProjectEl ? cardProjectEl.textContent.trim() : '';
                    var cardCompletion = completion || 0;
                    var cardPeriodic = card ? (card.getAttribute('data-task-periodic') === '1') : false;
                    updateCurrentlyWorkingOn(taskId, cardTitle, cardProject, cardCompletion, cardPeriodic);
                } else {
                    // If this task was in the "Currently Working On" block and is moved away from In Progress, remove it
                    var cwBlock = document.getElementById('currentlyWorkingBlock');
                    if (cwBlock) {
                        var cwLink = cwBlock.querySelector('.btn-current-task');
                        if (cwLink) {
                            var onclickStr = cwLink.getAttribute('onclick') || '';
                            if (onclickStr.indexOf(', ' + taskId + ',') !== -1) {
                                removeCurrentlyWorkingOn();
                            }
                        }
                    }
                }

                showKbToast('Task moved successfully');
            } else {
                showKbToast(data.error || 'Failed to move task', true);
            }
        })
        .catch(function() {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Move & Send';
            showKbToast('Network error', true);
        });
    }

    function updateKbColumnCounts() {
        document.querySelectorAll('.kb-column').forEach(function(col) {
            var cards = col.querySelectorAll('.kb-card');
            var countEl = col.querySelector('.kb-column-count');
            if (countEl) countEl.textContent = cards.length;
            var cardsContainer = col.querySelector('.kb-cards');
            var empty = cardsContainer.querySelector('.kb-empty');
            if (cards.length === 0 && !empty) {
                var emptyDiv = document.createElement('div');
                emptyDiv.className = 'kb-empty';
                emptyDiv.textContent = 'No tasks';
                cardsContainer.appendChild(emptyDiv);
            }
        });
    }

    // ==================== Update "Currently Working On" Block ====================
    var _cwTimerInterval = null;

    function updateCurrentlyWorkingOn(taskId, taskTitle, projectTitle, completion, isPeriodic) {
        var quickSection = document.getElementById('quickSection');
        if (!quickSection) return;

        // Clear any existing timer
        if (_cwTimerInterval) { clearInterval(_cwTimerInterval); _cwTimerInterval = null; }

        var block = document.getElementById('currentlyWorkingBlock');

        if (!block) {
            // Create the block - insert as the first child of quickSection
            block = document.createElement('div');
            block.className = 'quick-block';
            block.id = 'currentlyWorkingBlock';
            quickSection.insertBefore(block, quickSection.firstChild);
        }

        var periodicFlag = isPeriodic ? 'true' : 'false';
        // Escape for embedding in single-quoted JS string (prevents "literal not terminated" for newlines/quotes)
        var escapedTitle = ('' + taskTitle)
            .replace(/\\/g, '\\\\')
            .replace(/'/g, "\\'")
            .replace(/\r/g, '\\r')
            .replace(/\n/g, '\\n')
            .replace(/</g, '\\u003c')
            .replace(/>/g, '\\u003e');

        block.innerHTML =
            '<div class="quick-block-header">Currently Working On</div>' +
            '<div class="quick-block-body">' +
                '<a href="#" onclick="confirmTaskAction(\'stop\', ' + taskId + ', \'' + escapedTitle + '\', ' + parseInt(completion) + ', ' + periodicFlag + '); return false;" class="btn-current-task">' +
                    '<span class="stop-icon">&#9632;</span>' +
                    '<span class="task-info">' +
                        '<span class="task-title" title="' + kbEscapeHtml(taskTitle) + '">' + kbEscapeHtml(taskTitle) + '</span>' +
                        '<span class="task-project">' + kbEscapeHtml(projectTitle) + '</span>' +
                    '</span>' +
                    '<span class="task-time" id="actual_time">0:00</span>' +
                '</a>' +
            '</div>';

        // Start a live timer counting up from 0
        var startedAt = Date.now();
        function tickTimer() {
            var elapsed = Math.floor((Date.now() - startedAt) / 1000);
            var h = Math.floor(elapsed / 3600);
            var m = Math.floor((elapsed % 3600) / 60);
            var timeEl = document.getElementById('actual_time');
            if (timeEl) {
                timeEl.textContent = h + ':' + (m < 10 ? '0' : '') + m;
            }
        }
        _cwTimerInterval = setInterval(tickTimer, 15000); // update every 15s
    }

    function removeCurrentlyWorkingOn() {
        if (_cwTimerInterval) { clearInterval(_cwTimerInterval); _cwTimerInterval = null; }
        var block = document.getElementById('currentlyWorkingBlock');
        if (block) block.remove();
    }

    function showKbToast(msg, isError) {
        var t = document.createElement('div');
        t.className = 'kb-toast' + (isError ? ' error' : '');
        t.textContent = msg;
        document.body.appendChild(t);
        setTimeout(function() { t.style.opacity = '0'; t.style.transition = 'opacity 0.3s'; }, 2000);
        setTimeout(function() { t.remove(); }, 2500);
    }

    // ==================== Inline Add Card (By Status) ====================
    function kbShowAddForm(btn) {
        var area = btn.closest('.kb-add-area');
        btn.style.display = 'none';
        var form = area.querySelector('.kb-add-form');
        form.style.display = '';
        var input = form.querySelector('.kb-add-input');
        input.value = '';
        input.focus();
    }

    function kbCancelAdd(btn) {
        var area = btn.closest('.kb-add-area');
        area.querySelector('.kb-add-form').style.display = 'none';
        area.querySelector('.kb-add-btn').style.display = '';
    }

    function kbSubmitCard(btn) {
        var area = btn.closest('.kb-add-area');
        var input = area.querySelector('.kb-add-input');
        var projectSelect = area.querySelector('.kb-add-project');
        var title = input.value.trim();
        if (!title) { input.focus(); return; }

        var projectId = projectSelect.value;
        if (!projectId) { showKbToast('Please select a project', true); return; }

        var statusId = area.dataset.statusId || 7;
        var submitBtn = area.querySelector('.kb-add-submit');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Adding...';

        var formData = new FormData();
        formData.append('action', 'quick_add_task');
        formData.append('task_title', title);
        formData.append('project_id', projectId);
        formData.append('status_id', statusId);

        fetch('ajax_responder.php', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Add Task';
            if (data.success && data.task) {
                var t = data.task;
                var sc = 'status-not-started';
                if (t.status_id == 1) sc = 'status-in-progress';
                else if (t.status_id == 2 || t.status_id == 8) sc = 'status-on-hold';

                var card = document.createElement('div');
                card.className = 'kb-card';
                card.draggable = true;
                card.dataset.taskId = t.task_id;
                card.dataset.taskTitle = t.task_title;
                card.dataset.taskStatus = t.status_id;
                card.dataset.taskCompletion = 0;
                card.dataset.taskPeriodic = '0';
                card.dataset.projectId = t.project_id;
                card.dataset.taskAssign = kbSessionUserId;
                card.innerHTML =
                    '<div class="kb-card-title"><a href="edit_task.php?task_id=' + t.task_id + '" onclick="event.stopPropagation()">' + kbEscapeHtml(t.task_title) + '</a></div>' +
                    '<div class="kb-card-meta"><span class="kb-card-project">' + kbEscapeHtml(t.project_title) + '</span></div>' +
                    '<div class="kb-card-footer"><span class="status-badge ' + sc + '" style="font-size:0.62rem;padding:1px 6px;">' + kbEscapeHtml(t.status_desc) + '</span></div>';

                var col = area.closest('.kb-column');
                var cardsContainer = col.querySelector('.kb-cards');
                var emptyEl = cardsContainer.querySelector('.kb-empty');
                if (emptyEl) emptyEl.remove();
                cardsContainer.appendChild(card);

                // Update count
                var countEl = col.querySelector('.kb-column-count');
                if (countEl) countEl.textContent = cardsContainer.querySelectorAll('.kb-card').length;

                kbSaveColumnPriorities(cardsContainer);
                input.value = '';
                input.focus();
                card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                showKbToast('Card added');
            } else {
                showKbToast(data.error || 'Failed to add task', true);
            }
        })
        .catch(function() {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Add Task';
            showKbToast('Network error', true);
        });
    }

    // Handle Enter key in add-card textarea
    document.querySelectorAll('.kb-add-input').forEach(function(input) {
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                var submitBtn = this.closest('.kb-add-form').querySelector('.kb-add-submit');
                kbSubmitCard(submitBtn);
            }
            if (e.key === 'Escape') {
                var cancelBtn = this.closest('.kb-add-form').querySelector('.kb-add-cancel');
                kbCancelAdd(cancelBtn);
            }
        });
    });

    // Project tabs
    function switchProjectTab(tabName) {
        document.querySelectorAll('#projectsBlock .project-tab').forEach(function(t) {
            t.classList.toggle('active', t.getAttribute('data-tab') === tabName);
        });
        document.querySelectorAll('#projectsBlock .project-tab-panel').forEach(function(p) {
            p.classList.toggle('active', p.id === 'tab-' + tabName);
        });
    }

    var pendingAction = null;
    var pendingTaskId = null;

    function confirmTaskAction(action, taskId, taskTitle, currentCompletion, isPeriodic) {
        pendingAction = action;
        pendingTaskId = taskId;

        var titles = {
            'start': 'Start Task',
            'stop': 'Stop Task',
            'close': 'Close Task'
        };

        var messages = {
            'start': 'You will begin tracking time on this task.',
            'stop': 'Time tracking will be paused for this task.',
            'close': 'This task will be marked as complete. This action cannot be undone.'
        };

        var buttonLabels = {
            'start': 'Start Working',
            'stop': 'Stop Working',
            'close': 'Close Task'
        };

        document.getElementById('modalTitle').textContent = titles[action];
        document.getElementById('modalTaskName').textContent = taskTitle;
        document.getElementById('modalMessage').textContent = messages[action];
        
        var header = document.getElementById('modalHeader');
        header.className = 'modal-header ' + action;
        
        var confirmBtn = document.getElementById('modalConfirmBtn');
        confirmBtn.className = 'modal-btn modal-btn-confirm ' + action;
        confirmBtn.textContent = buttonLabels[action];

        // Show completion input for stop action (but not for periodic tasks)
        var completionDiv = document.getElementById('modalCompletion');
        var completionInput = document.getElementById('completionInput');
        var isPeriodicBool = (isPeriodic === true || isPeriodic === 'true');
        if (action === 'stop' && !isPeriodicBool) {
            completionDiv.classList.add('visible');
            completionInput.value = currentCompletion || 0;
            document.getElementById('confirmModal').classList.add('active');
            setTimeout(function() { completionInput.focus(); completionInput.select(); }, 100);
        } else {
            completionDiv.classList.remove('visible');
            document.getElementById('confirmModal').classList.add('active');
        }
    }

    function closeModal() {
        document.getElementById('confirmModal').classList.remove('active');
        pendingAction = null;
        pendingTaskId = null;
    }

    function executeAction() {
        if (pendingAction && pendingTaskId) {
            var url = 'index.php?action=' + pendingAction + '&task_id=' + pendingTaskId;
            if (pendingAction === 'stop') {
                var completion = document.getElementById('completionInput').value;
                url += '&completion=' + encodeURIComponent(completion);
            }
            window.location.href = url;
        }
    }

    // Close modal on overlay click
    document.getElementById('confirmModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });

    // Close modal on Escape key, submit on Enter
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
        if (e.key === 'Enter' && document.getElementById('confirmModal').classList.contains('active')) {
            e.preventDefault();
            executeAction();
        }
    });

    // Table sorting functionality (with localStorage persistence)
    function initTableSort(tableId) {
        var table = document.getElementById(tableId);
        if (!table) return;

        var storageKey = 'indexSort_' + tableId;
        var headers = table.querySelectorAll('th.sortable');

        headers.forEach(function(header) {
            header.addEventListener('click', function() {
                var col = parseInt(this.dataset.col);
                var sortType = this.dataset.sort;
                var isAsc = this.classList.contains('sort-asc');
                
                // Remove sort classes from all headers
                headers.forEach(function(h) {
                    h.classList.remove('sort-asc', 'sort-desc');
                });
                
                // Toggle sort direction
                var newDir = isAsc ? 'desc' : 'asc';
                this.classList.add('sort-' + newDir);
                
                var ascending = (newDir === 'asc');
                sortTable(table, col, sortType, ascending);

                // Save to localStorage
                try {
                    localStorage.setItem(storageKey, JSON.stringify({ col: col, sort: sortType, dir: newDir }));
                } catch(e) {}
            });
        });

        // Restore saved sort from localStorage
        try {
            var saved = localStorage.getItem(storageKey);
            if (saved) {
                saved = JSON.parse(saved);
                var targetHeader = table.querySelector('th.sortable[data-col="' + saved.col + '"]');
                if (targetHeader) {
                    headers.forEach(function(h) { h.classList.remove('sort-asc', 'sort-desc'); });
                    targetHeader.classList.add('sort-' + saved.dir);
                    sortTable(table, saved.col, saved.sort, saved.dir === 'asc');
                }
            }
        } catch(e) {}
    }

    function sortTable(table, col, sortType, asc) {
        var tbody = table.querySelector('tbody');
        var rows = Array.from(tbody.querySelectorAll('tr'));
        
        rows.sort(function(a, b) {
            var aVal = getCellValue(a.cells[col], sortType);
            var bVal = getCellValue(b.cells[col], sortType);
            
            if (aVal === null && bVal === null) return 0;
            if (aVal === null) return 1;
            if (bVal === null) return -1;
            
            var result;
            if (sortType === 'number' || sortType === 'time') {
                result = aVal - bVal;
            } else if (sortType === 'date') {
                result = aVal - bVal;
            } else {
                result = aVal.localeCompare(bVal);
            }
            
            return asc ? result : -result;
        });
        
        rows.forEach(function(row) {
            tbody.appendChild(row);
        });
    }

    function getCellValue(cell, sortType) {
        var text = cell.textContent.trim();
        
        if (text === '-' || text === '') return null;
        
        if (sortType === 'number') {
            // Extract number, remove % sign
            var num = parseFloat(text.replace('%', ''));
            return isNaN(num) ? null : num;
        }
        
        if (sortType === 'time') {
            // Parse time format like "2h 30m" or "45m" or "2.5h"
            var hours = 0;
            var hMatch = text.match(/(\d+(?:\.\d+)?)\s*h/);
            var mMatch = text.match(/(\d+)\s*m/);
            if (hMatch) hours += parseFloat(hMatch[1]);
            if (mMatch) hours += parseInt(mMatch[1]) / 60;
            return hours || null;
        }
        
        if (sortType === 'date') {
            // Parse date like "03 Feb 26" or "03 Feb"
            var months = {Jan:0,Feb:1,Mar:2,Apr:3,May:4,Jun:5,Jul:6,Aug:7,Sep:8,Oct:9,Nov:10,Dec:11};
            var parts = text.split(' ');
            if (parts.length >= 2) {
                var day = parseInt(parts[0]);
                var month = months[parts[1]];
                var year = parts[2] ? (2000 + parseInt(parts[2])) : new Date().getFullYear();
                return new Date(year, month, day).getTime();
            }
            return null;
        }
        
        return text.toLowerCase();
    }

    // Initialize sorting for the tasks table
    initTableSort('myTasksTable');

    // Auto-dismiss flash messages after 5 seconds
    var flashMessage = document.getElementById('flashMessage');
    if (flashMessage) {
        setTimeout(function() {
            flashMessage.style.animation = 'slideUp 0.3s ease forwards';
            setTimeout(function() { flashMessage.remove(); }, 300);
        }, 5000);
    }

    // ==================== Bulk Selection ====================
    function getSelectedTaskIds() {
        var ids = [];
        document.querySelectorAll('.task-check:checked').forEach(function(cb) {
            ids.push(cb.value);
        });
        return ids;
    }

    function updateBulkBar() {
        var ids = getSelectedTaskIds();
        var bar = document.getElementById('bulkBar');
        var countEl = document.getElementById('bulkCount');
        if (ids.length > 0) {
            bar.classList.add('show');
            countEl.textContent = ids.length;
        } else {
            bar.classList.remove('show');
            closeBulkDropdowns();
        }
        // Update select-all checkbox state
        var allChecks = document.querySelectorAll('.task-check');
        var selectAll = document.getElementById('selectAllTasks');
        if (selectAll) {
            selectAll.checked = allChecks.length > 0 && ids.length === allChecks.length;
            selectAll.indeterminate = ids.length > 0 && ids.length < allChecks.length;
        }
    }

    function toggleSelectAll(cb) {
        var checked = cb.checked;
        document.querySelectorAll('.task-check').forEach(function(c) { c.checked = checked; });
        updateBulkBar();
    }

    function clearSelection() {
        document.querySelectorAll('.task-check').forEach(function(c) { c.checked = false; });
        var selectAll = document.getElementById('selectAllTasks');
        if (selectAll) { selectAll.checked = false; selectAll.indeterminate = false; }
        updateBulkBar();
    }

    var _activeBulkDropdown = null;

    function closeBulkDropdowns() {
        document.querySelectorAll('.bulk-dropdown').forEach(function(d) { d.classList.remove('show'); });
        _activeBulkDropdown = null;
    }

    function toggleBulkDropdown(type) {
        var btn = (type === 'status') ? document.getElementById('bulkStatusBtn') : document.getElementById('bulkReassignBtn');
        // Check if dropdown already exists under this button
        var existing = btn.querySelector('.bulk-dropdown');
        if (existing && existing.classList.contains('show')) {
            closeBulkDropdowns();
            return;
        }
        closeBulkDropdowns();

        var dd = document.createElement('div');
        dd.className = 'bulk-dropdown show';

        var html = '<div class="bd-search"><input type="text" placeholder="Search..." autocomplete="off" id="bdSearch_' + type + '"></div>';
        if (type === 'status') {
            var statusOpts = <?php echo json_encode($ctx_statuses) ?: '[]'; ?>;
            for (var i = 0; i < statusOpts.length; i++) {
                html += '<div class="bd-item" data-action="bulk-status" data-value="' + statusOpts[i].id + '">' + statusOpts[i].name + '</div>';
            }
        } else {
            var userOpts = <?php echo json_encode($ctx_users) ?: '[]'; ?>;
            for (var i = 0; i < userOpts.length; i++) {
                html += '<div class="bd-item" data-action="bulk-reassign" data-value="' + userOpts[i].id + '">' + userOpts[i].name + '</div>';
            }
        }
        dd.innerHTML = html;
        btn.style.position = 'relative';
        btn.appendChild(dd);
        _activeBulkDropdown = dd;

        // Search filter
        var search = dd.querySelector('input');
        search.addEventListener('input', function() {
            var q = this.value.toLowerCase();
            dd.querySelectorAll('.bd-item').forEach(function(item) {
                item.style.display = item.textContent.toLowerCase().indexOf(q) >= 0 ? '' : 'none';
            });
        });
        search.addEventListener('click', function(e) { e.stopPropagation(); });
        setTimeout(function() { search.focus(); }, 30);

        // Click handler
        dd.addEventListener('click', function(e) {
            e.stopPropagation();
            var item = e.target.closest('.bd-item');
            if (!item) return;
            var action = item.dataset.action;
            var value = item.dataset.value;
            var ids = getSelectedTaskIds();
            if (!ids.length) return;
            closeBulkDropdowns();

            if (action === 'bulk-status') {
                bulkChangeStatus(ids, value, item.textContent.trim());
            } else if (action === 'bulk-reassign') {
                bulkReassign(ids, value, item.textContent.trim());
            }
        });
    }

    function bulkChangeStatus(ids, statusId, statusName) {
        var done = 0, fail = 0;
        var total = ids.length;
        showBulkToast('Changing status for ' + total + ' task(s)...');
        ids.forEach(function(tid) {
            fetch('ajax_responder.php?action=kanban_move_task&task_id=' + tid + '&new_status_id=' + statusId)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        done++;
                        // Update badge in place
                        var row = document.querySelector('tr[data-task-id="' + tid + '"]');
                        if (row) {
                            row.dataset.taskStatus = statusId;
                            var badge = row.querySelector('.status-badge');
                            if (badge) badge.textContent = data.new_status_name || statusName;
                        }
                    } else { fail++; }
                    if (done + fail === total) {
                        showBulkToast('Status changed for ' + done + ' task(s)' + (fail ? ', ' + fail + ' failed' : ''));
                        clearSelection();
                    }
                })
                .catch(function() { fail++; if (done + fail === total) { showBulkToast(done + ' changed, ' + fail + ' failed', true); clearSelection(); } });
        });
    }

    function bulkReassign(ids, userId, userName) {
        var done = 0, fail = 0;
        var total = ids.length;
        showBulkToast('Reassigning ' + total + ' task(s)...');
        ids.forEach(function(tid) {
            fetch('ajax_responder.php?action=reassign_task&task_id=' + tid + '&new_user_id=' + userId)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        done++;
                        fadeOutTask(tid);
                    } else { fail++; }
                    if (done + fail === total) {
                        showBulkToast('Reassigned ' + done + ' task(s)' + (fail ? ', ' + fail + ' failed' : ''));
                        clearSelection();
                    }
                })
                .catch(function() { fail++; if (done + fail === total) { showBulkToast(done + ' reassigned, ' + fail + ' failed', true); clearSelection(); } });
        });
    }

    function fadeOutTask(taskId) {
        var row = document.querySelector('tr[data-task-id="' + taskId + '"]');
        if (row) {
            row.style.transition = 'opacity 0.4s, transform 0.4s';
            row.style.opacity = '0';
            row.style.transform = 'translateX(30px)';
            setTimeout(function() { row.remove(); }, 420);
        }
        var card = document.querySelector('.kb-card[data-task-id="' + taskId + '"]');
        if (card) {
            card.style.transition = 'opacity 0.4s, transform 0.4s, max-height 0.4s';
            card.style.opacity = '0';
            card.style.transform = 'translateX(30px)';
            card.style.overflow = 'hidden';
            setTimeout(function() { card.style.maxHeight = '0'; card.style.padding = '0'; card.style.margin = '0'; setTimeout(function() { card.remove(); }, 300); }, 400);
        }
    }

    function bulkCloseTasks() {
        var ids = getSelectedTaskIds();
        if (!ids.length) return;
        if (!confirm('Close ' + ids.length + ' task(s)?')) return;
        closeBulkDropdowns();
        fetch('ajax_responder.php?action=close_task&task_ids=' + ids.join(','))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    showBulkToast(data.count + ' task(s) closed');
                    ids.forEach(function(tid) { fadeOutTask(tid); });
                    clearSelection();
                } else {
                    showBulkToast(data.error || 'Failed', true);
                }
            })
            .catch(function() { showBulkToast('Network error', true); });
    }

    function showBulkToast(msg, isError) {
        var t = document.createElement('div');
        t.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);color:#fff;padding:10px 20px;border-radius:8px;font-size:0.85rem;z-index:10001;transition:opacity 0.3s;pointer-events:none;box-shadow:0 4px 12px rgba(0,0,0,0.15);';
        t.style.background = isError ? '#e53e3e' : 'linear-gradient(135deg,#667eea 0%,#764ba2 100%)';
        t.textContent = msg;
        document.body.appendChild(t);
        setTimeout(function() { t.style.opacity = '0'; }, 2500);
        setTimeout(function() { t.remove(); }, 3000);
    }

    // Close bulk dropdowns on outside click
    document.addEventListener('click', function() { closeBulkDropdowns(); });

    // ==================== Context Menu ====================
    (function() {
        var ctxStatusOptions = <?php echo json_encode($ctx_statuses) ?: '[]'; ?>;
        var ctxUserOptions = <?php echo json_encode($ctx_users) ?: '[]'; ?>;

        var ctxTaskId = null;
        var ctxTaskTitle = null;
        var ctxTaskStatus = null;
        var ctxTaskCompletion = null;
        var ctxTaskPeriodic = false;
        var ctxProjectId = null;
        var activeSubmenu = null;
        var submenuLocked = false;

        // Build menu HTML
        var menu = document.createElement('div');
        menu.className = 'context-menu';
        menu.id = 'taskContextMenu';
        document.body.appendChild(menu);

        // Shared submenu panel
        var submenuPanel = document.createElement('div');
        submenuPanel.className = 'context-menu-submenu-items';
        submenuPanel.id = 'ctxSubmenuPanel';
        document.body.appendChild(submenuPanel);

        function escapeHtml(s) {
            var d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        function buildMenu() {
            var isRunning = (ctxTaskStatus === '1');
            var selectedIds = getSelectedTaskIds();
            // If right-clicked task is in selection, treat as bulk; otherwise single task
            var isBulk = selectedIds.length > 1 && selectedIds.indexOf(ctxTaskId) !== -1;
            var bulkCount = isBulk ? selectedIds.length : 0;
            var bulkLabel = bulkCount ? ' (' + bulkCount + ')' : '';

            var html = '';
            // Task title header
            if (isBulk) {
                html += '<div style="padding:8px 16px 6px;font-size:0.78rem;font-weight:600;color:#667eea;border-bottom:1px solid #f1f5f9;margin-bottom:4px;">' + bulkCount + ' tasks selected</div>';
            } else {
                html += '<div style="padding:8px 16px 6px;font-size:0.78rem;font-weight:600;color:#2d3748;border-bottom:1px solid #f1f5f9;margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:280px;" title="' + escapeHtml(ctxTaskTitle) + '">' + escapeHtml(ctxTaskTitle) + '</div>';
            }
            if (!isBulk) {
                html += '<div class="context-menu-item" data-action="edit"><span class="ctx-icon">✏️</span> Edit Task</div>';
                html += '<div class="context-menu-item" data-action="open-new" style="color:#718096;font-size:0.82rem;"><span class="ctx-icon">↗</span> Open in New Tab</div>';
                html += '<div class="context-menu-separator"></div>';
                html += '<div class="context-menu-item ' + (isRunning ? 'danger' : 'success') + '" data-action="startstop"><span class="ctx-icon">' + (isRunning ? '⏹' : '▶') + '</span> ' + (isRunning ? 'Stop' : 'Start') + '</div>';
                html += '<div class="context-menu-separator"></div>';
            }
            html += '<div class="context-menu-item context-menu-has-sub" data-submenu="status"><span class="ctx-icon">📊</span> Change Status' + bulkLabel + ' <span style="margin-left:auto;color:#a0aec0">›</span></div>';
            html += '<div class="context-menu-item context-menu-has-sub" data-submenu="reassign"><span class="ctx-icon">👤</span> Reassign' + bulkLabel + ' <span style="margin-left:auto;color:#a0aec0">›</span></div>';
            html += '<div class="context-menu-separator"></div>';
            html += '<div class="context-menu-item danger" data-action="close"><span class="ctx-icon">✓</span> Close' + bulkLabel + '</div>';
            if (!isBulk) {
                html += '<div class="context-menu-separator"></div>';
                html += '<div class="context-menu-item context-menu-has-sub" data-submenu="copy"><span class="ctx-icon">📋</span> Copy <span style="margin-left:auto;color:#a0aec0">›</span></div>';
                html += '<div class="context-menu-item" data-action="new"><span class="ctx-icon">+</span> New Task</div>';
            }
            var listViewActive = document.querySelector('.my-tasks-list-view.active');
            if (listViewActive) {
                html += '<div class="context-menu-separator"></div>';
                html += '<div class="context-menu-item" data-action="mark-all-read"><span class="ctx-icon">🔵</span> Mark all as read</div>';
            }
            menu.innerHTML = html;
            // Store bulk state for action handlers
            menu._isBulk = isBulk;
            menu._bulkIds = isBulk ? selectedIds.slice() : [ctxTaskId];
        }

        function buildSubmenu(type) {
            var html = '';
            if (type === 'status') {
                html += '<div style="padding:4px 12px 6px;"><input type="text" id="ctxStatusSearch" placeholder="Search..." style="width:100%;padding:5px 8px;border:1px solid #e2e8f0;border-radius:5px;font-size:0.82rem;outline:none;font-family:inherit;" autocomplete="off"></div>';
                for (var i = 0; i < ctxStatusOptions.length; i++) {
                    var s = ctxStatusOptions[i];
                    var isCurrent = (String(s.id) === String(ctxTaskStatus));
                    html += '<div class="context-menu-item ctx-status-item' + (isCurrent ? ' active-status' : '') + '" data-action="set-status" data-value="' + s.id + '" style="font-size:0.8rem;padding:6px 14px;">';
                    html += (isCurrent ? '<span class="ctx-icon">✔</span> ' : '<span class="ctx-icon"></span> ');
                    html += escapeHtml(s.name) + '</div>';
                }
            } else if (type === 'reassign') {
                html += '<div style="padding:4px 12px 6px;"><input type="text" id="ctxUserSearch" placeholder="Search..." style="width:100%;padding:5px 8px;border:1px solid #e2e8f0;border-radius:5px;font-size:0.82rem;outline:none;font-family:inherit;" autocomplete="off"></div>';
                for (var j = 0; j < ctxUserOptions.length; j++) {
                    var u = ctxUserOptions[j];
                    html += '<div class="context-menu-item ctx-user-item" data-action="set-user" data-value="' + u.id + '" style="font-size:0.8rem;padding:6px 14px;"><span class="ctx-icon" style="font-size:0.75em;">👤</span> ' + escapeHtml(u.name) + '</div>';
                }
            } else if (type === 'copy') {
                html += '<div class="context-menu-item" data-action="copy-id"><span class="ctx-icon">#</span> Task ID</div>';
                html += '<div class="context-menu-item" data-action="copy-name"><span class="ctx-icon">T</span> Task Name</div>';
                html += '<div class="context-menu-item" data-action="copy-url"><span class="ctx-icon">🔗</span> Task URL</div>';
            }
            submenuPanel.innerHTML = html;
            if (type === 'reassign') {
                var searchInput = document.getElementById('ctxUserSearch');
                if (searchInput) {
                    var highlightIdx = -1;

                    function getVisibleItems() {
                        var all = submenuPanel.querySelectorAll('.ctx-user-item');
                        var visible = [];
                        for (var k = 0; k < all.length; k++) {
                            if (all[k].style.display !== 'none') visible.push(all[k]);
                        }
                        return visible;
                    }

                    function updateHighlight(items) {
                        for (var k = 0; k < items.length; k++) {
                            items[k].style.background = (k === highlightIdx) ? '#edf2f7' : '';
                        }
                        if (highlightIdx >= 0 && items[highlightIdx]) {
                            items[highlightIdx].scrollIntoView({ block: 'nearest' });
                        }
                    }

                    searchInput.addEventListener('input', function() {
                        var q = this.value.toLowerCase();
                        submenuPanel.querySelectorAll('.ctx-user-item').forEach(function(el) {
                            el.style.display = el.textContent.toLowerCase().indexOf(q) >= 0 ? '' : 'none';
                        });
                        highlightIdx = -1;
                        // Auto-highlight first visible if there's a query
                        if (q.length > 0) {
                            var vis = getVisibleItems();
                            if (vis.length > 0) { highlightIdx = 0; updateHighlight(vis); }
                        }
                    });

                    searchInput.addEventListener('keydown', function(e) {
                        e.stopPropagation();
                        var items = getVisibleItems();
                        if (e.key === 'ArrowDown') {
                            e.preventDefault();
                            highlightIdx = Math.min(highlightIdx + 1, items.length - 1);
                            updateHighlight(items);
                        } else if (e.key === 'ArrowUp') {
                            e.preventDefault();
                            highlightIdx = Math.max(highlightIdx - 1, 0);
                            updateHighlight(items);
                        } else if (e.key === 'Enter') {
                            e.preventDefault();
                            if (highlightIdx >= 0 && items[highlightIdx]) {
                                items[highlightIdx].click();
                            }
                        } else if (e.key === 'Escape') {
                            e.preventDefault();
                            hideAll();
                        }
                    });

                    // Keep submenu locked while interacting with search
                    searchInput.addEventListener('focus', function() { submenuLocked = true; clearTimeout(submenuHideTimer); });
                    searchInput.addEventListener('click', function(e) { e.stopPropagation(); submenuLocked = true; });
                    setTimeout(function() { searchInput.focus(); }, 50);
                }
            }
            if (type === 'status') {
                var statusSearch = document.getElementById('ctxStatusSearch');
                if (statusSearch) {
                    var sHighlightIdx = -1;

                    function getVisibleStatuses() {
                        var all = submenuPanel.querySelectorAll('.ctx-status-item');
                        var visible = [];
                        for (var k = 0; k < all.length; k++) {
                            if (all[k].style.display !== 'none') visible.push(all[k]);
                        }
                        return visible;
                    }

                    function updateStatusHighlight(items) {
                        for (var k = 0; k < items.length; k++) {
                            items[k].style.background = (k === sHighlightIdx) ? '#edf2f7' : '';
                        }
                        if (sHighlightIdx >= 0 && items[sHighlightIdx]) {
                            items[sHighlightIdx].scrollIntoView({ block: 'nearest' });
                        }
                    }

                    statusSearch.addEventListener('input', function() {
                        var q = this.value.toLowerCase();
                        submenuPanel.querySelectorAll('.ctx-status-item').forEach(function(el) {
                            el.style.display = el.textContent.toLowerCase().indexOf(q) >= 0 ? '' : 'none';
                        });
                        sHighlightIdx = -1;
                        if (q.length > 0) {
                            var vis = getVisibleStatuses();
                            if (vis.length > 0) { sHighlightIdx = 0; updateStatusHighlight(vis); }
                        }
                    });

                    statusSearch.addEventListener('keydown', function(e) {
                        e.stopPropagation();
                        var items = getVisibleStatuses();
                        if (e.key === 'ArrowDown') {
                            e.preventDefault();
                            sHighlightIdx = Math.min(sHighlightIdx + 1, items.length - 1);
                            updateStatusHighlight(items);
                        } else if (e.key === 'ArrowUp') {
                            e.preventDefault();
                            sHighlightIdx = Math.max(sHighlightIdx - 1, 0);
                            updateStatusHighlight(items);
                        } else if (e.key === 'Enter') {
                            e.preventDefault();
                            if (sHighlightIdx >= 0 && items[sHighlightIdx]) {
                                items[sHighlightIdx].click();
                            }
                        } else if (e.key === 'Escape') {
                            e.preventDefault();
                            hideAll();
                        }
                    });

                    statusSearch.addEventListener('focus', function() { submenuLocked = true; clearTimeout(submenuHideTimer); });
                    statusSearch.addEventListener('click', function(e) { e.stopPropagation(); submenuLocked = true; });
                    setTimeout(function() { statusSearch.focus(); }, 50);
                }
            }
        }

        function showSubmenu(triggerEl, type) {
            if (activeSubmenu === type) return;
            activeSubmenu = type;
            buildSubmenu(type);

            // Position offscreen to measure
            submenuPanel.style.left = '-9999px';
            submenuPanel.style.top = '-9999px';
            submenuPanel.classList.add('show');
            submenuPanel.style.maxHeight = '320px';
            submenuPanel.style.overflowY = 'auto';

            var subW = submenuPanel.offsetWidth;
            var subH = submenuPanel.offsetHeight;
            var trigRect = triggerEl.getBoundingClientRect();
            var menuRect = menu.getBoundingClientRect();

            // Try right side of menu
            var sx = menuRect.right + 2;
            if (sx + subW > window.innerWidth) {
                sx = menuRect.left - subW - 2;
            }
            if (sx < 0) sx = 4;

            var sy = trigRect.top;
            if (sy + subH > window.innerHeight) {
                sy = window.innerHeight - subH - 8;
            }
            if (sy < 0) sy = 4;

            submenuPanel.style.left = sx + 'px';
            submenuPanel.style.top = sy + 'px';
        }

        function hideSubmenu() {
            submenuPanel.classList.remove('show');
            activeSubmenu = null;
            submenuLocked = false;
        }

        function hideAll() {
            menu.classList.remove('show');
            hideSubmenu();
        }

        function showMenuAt(e) {
            e.preventDefault();
            e.stopPropagation();

            var el = e.target.closest('[data-task-id]');
            if (!el) return;

            ctxTaskId = el.dataset.taskId;
            ctxTaskTitle = el.dataset.taskTitle;
            ctxTaskStatus = el.dataset.taskStatus;
            ctxTaskCompletion = parseInt(el.dataset.taskCompletion) || 0;
            ctxTaskPeriodic = (el.dataset.taskPeriodic === '1');

            // Get project_id from data attribute (list rows) or kanban column (cards)
            ctxProjectId = el.dataset.projectId || null;
            if (!ctxProjectId) {
                var col = el.closest('.kb-column');
                if (col) {
                    var colLink = col.querySelector('.kb-column-header a');
                    if (colLink) {
                        var m = colLink.href.match(/project_id=(\d+)/);
                        if (m) ctxProjectId = m[1];
                    }
                }
            }

            buildMenu();
            hideSubmenu();

            var x = e.clientX;
            var y = e.clientY;
            menu.classList.add('show');

            var menuRect = menu.getBoundingClientRect();
            if (x + menuRect.width > window.innerWidth) x = window.innerWidth - menuRect.width - 8;
            if (y + menuRect.height > window.innerHeight) y = window.innerHeight - menuRect.height - 8;
            menu.style.left = x + 'px';
            menu.style.top = y + 'px';
        }

        // Attach right-click via event delegation (works for dynamically added cards too)
        document.addEventListener('contextmenu', function(e) {
            var el = e.target.closest('tr.clickable-row[data-task-id], .kb-card[data-task-id]');
            if (el) showMenuAt.call(el, e);
        });

        // Submenu hover handling
        var submenuHideTimer = null;

        menu.addEventListener('mouseover', function(e) {
            var hasSub = e.target.closest('.context-menu-has-sub');
            if (hasSub) {
                clearTimeout(submenuHideTimer);
                submenuLocked = false;
                showSubmenu(hasSub, hasSub.dataset.submenu);
            } else {
                // Only hide if not locked (user isn't typing/scrolling in submenu)
                if (!submenuLocked) {
                    submenuHideTimer = setTimeout(hideSubmenu, 300);
                }
            }
        });

        submenuPanel.addEventListener('mouseenter', function() {
            clearTimeout(submenuHideTimer);
            submenuLocked = true;
        });
        submenuPanel.addEventListener('mouseleave', function() {
            submenuLocked = false;
            submenuHideTimer = setTimeout(hideSubmenu, 400);
        });

        // Prevent scroll inside submenu from closing everything
        submenuPanel.addEventListener('scroll', function(e) {
            e.stopPropagation();
        }, true);

        // Prevent clicks inside submenu/menu from bubbling to document close handler
        submenuPanel.addEventListener('mousedown', function(e) {
            e.stopPropagation();
        });
        menu.addEventListener('mousedown', function(e) {
            e.stopPropagation();
        });

        // Close on outside click / Escape / scroll
        document.addEventListener('mousedown', function(e) {
            if (!menu.contains(e.target) && !submenuPanel.contains(e.target)) {
                hideAll();
            }
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') hideAll();
        });
        window.addEventListener('scroll', function(e) {
            // Don't close if scrolling inside the submenu panel
            if (submenuPanel.contains(e.target)) return;
            hideAll();
        }, true);

        // Handle menu item clicks (both main menu and submenu)
        function handleItemClick(e) {
            var item = e.target.closest('.context-menu-item');
            if (!item) return;
            var action = item.dataset.action;
            if (!action) return;

            e.stopPropagation();

            switch (action) {
                case 'edit':
                    hideAll();
                    window.location = 'edit_task.php?task_id=' + ctxTaskId + '&edit=1';
                    break;
                case 'open-new':
                    hideAll();
                    window.open('edit_task.php?task_id=' + ctxTaskId, '_blank');
                    break;
                case 'new':
                    hideAll();
                    window.location = 'create_task.php' + (ctxProjectId ? '?project_id=' + ctxProjectId : '');
                    break;
                case 'startstop':
                    hideAll();
                    var act = (ctxTaskStatus === '1') ? 'stop' : 'start';
                    confirmTaskAction(act, ctxTaskId, ctxTaskTitle, ctxTaskCompletion, ctxTaskPeriodic);
                    break;
                case 'close':
                    hideAll();
                    if (menu._isBulk) {
                        bulkCloseTasks();
                    } else {
                        confirmTaskAction('close', ctxTaskId, ctxTaskTitle, ctxTaskCompletion);
                    }
                    break;
                case 'set-status':
                    hideAll();
                    var statusId = item.dataset.value;
                    var statusName = item.textContent.trim();
                    if (menu._isBulk) {
                        bulkChangeStatus(menu._bulkIds.slice(), statusId, statusName);
                    } else if (ctxTaskId && statusId) {
                        fetch('ajax_responder.php?action=kanban_move_task&task_id=' + ctxTaskId + '&new_status_id=' + statusId)
                            .then(function(r) { return r.json(); })
                            .then(function(data) {
                                if (data.success) {
                                    showToast('Status changed to ' + escapeHtml(data.new_status_name || 'updated'));
                                    updateElementStatus(ctxTaskId, statusId, data.new_status_name);
                                } else {
                                    showToast(data.error || 'Failed to change status', true);
                                }
                            })
                            .catch(function() { showToast('Network error', true); });
                    }
                    break;
                case 'set-user':
                    hideAll();
                    var userId = item.dataset.value;
                    if (menu._isBulk) {
                        bulkReassign(menu._bulkIds.slice(), userId, item.textContent.trim());
                    } else {
                        var fadeTaskId = ctxTaskId;
                        if (fadeTaskId && userId) {
                            fetch('ajax_responder.php?action=reassign_task&task_id=' + fadeTaskId + '&new_user_id=' + userId)
                                .then(function(r) { return r.json(); })
                                .then(function(data) {
                                    if (data.success) {
                                        showToast('Reassigned to ' + escapeHtml(data.new_user_name));
                                        fadeOutTask(fadeTaskId);
                                    } else {
                                        showToast(data.error || 'Failed to reassign', true);
                                    }
                                })
                                .catch(function() { showToast('Network error', true); });
                        }
                    }
                    break;
                case 'copy-id':
                    hideAll();
                    copyToClipboard(ctxTaskId, 'Task ID copied');
                    break;
                case 'copy-name':
                    hideAll();
                    copyToClipboard(ctxTaskTitle, 'Task name copied');
                    break;
                case 'copy-url':
                    hideAll();
                    copyToClipboard(window.location.origin + '/edit_task.php?task_id=' + ctxTaskId, 'Task URL copied');
                    break;
                case 'mark-all-read':
                    hideAll();
                    if (typeof window.markTaskSeen !== 'function') break;
                    var now = new Date().toISOString();
                    document.querySelectorAll('tr.clickable-row[data-task-id]').forEach(function(row) {
                        var taskId = row.getAttribute('data-task-id');
                        var lastMod = row.getAttribute('data-last-modified') || now;
                        window.markTaskSeen(taskId, lastMod);
                        row.classList.remove('task-unread');
                    });
                    document.querySelectorAll('.kb-card[data-task-id]').forEach(function(card) {
                        var taskId = card.getAttribute('data-task-id');
                        var lastMod = card.getAttribute('data-last-modified') || now;
                        window.markTaskSeen(taskId, lastMod);
                        card.classList.remove('task-unread');
                    });
                    if (typeof showToast === 'function') showToast('All tasks marked as read');
                    break;
            }
        }

        menu.addEventListener('click', handleItemClick);
        submenuPanel.addEventListener('click', handleItemClick);

        function updateElementStatus(taskId, newStatusId, statusName) {
            // Update status badge in table row
            var row = document.querySelector('tr[data-task-id="' + taskId + '"]');
            if (row) {
                row.dataset.taskStatus = newStatusId;
                var badge = row.querySelector('.status-badge');
                if (badge) {
                    badge.textContent = statusName;
                    badge.className = 'status-badge';
                    if (newStatusId == 1) badge.classList.add('status-in-progress');
                    else if (newStatusId == 2) badge.classList.add('status-on-hold');
                    else badge.classList.add('status-not-started');
                }
            }
            // Update card status badge
            var card = document.querySelector('.kb-card[data-task-id="' + taskId + '"]');
            if (card) {
                card.dataset.taskStatus = newStatusId;
                var cbadge = card.querySelector('.status-badge');
                if (cbadge) {
                    cbadge.textContent = statusName;
                    cbadge.className = 'status-badge';
                    if (newStatusId == 1) cbadge.classList.add('status-in-progress');
                    else if (newStatusId == 2) cbadge.classList.add('status-on-hold');
                    else cbadge.classList.add('status-not-started');
                }
            }
        }

        function copyToClipboard(text, message) {
            navigator.clipboard.writeText(text).then(function() {
                showToast(message);
            }).catch(function() {
                var ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                showToast(message);
            });
        }

        function showToast(message, isError) {
            var toast = document.getElementById('ctxToast');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'ctxToast';
                toast.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);color:#fff;padding:10px 20px;border-radius:8px;font-size:0.85rem;z-index:10001;opacity:0;transition:opacity 0.2s;pointer-events:none;';
                document.body.appendChild(toast);
            }
            toast.style.background = isError ? '#e53e3e' : '#2d3748';
            toast.textContent = message;
            toast.style.opacity = '1';
            setTimeout(function() { toast.style.opacity = '0'; }, 2000);
        }
    })();

    // ==================== Unread / Unseen Task Tracking ====================
    (function() {
        var STORAGE_KEY = 'seenTasks';

        function getSeenTasks() {
            try {
                var data = localStorage.getItem(STORAGE_KEY);
                return data ? JSON.parse(data) : {};
            } catch(e) { return {}; }
        }

        function markTaskSeen(taskId, modifiedAt) {
            var seen = getSeenTasks();
            seen[taskId] = modifiedAt || new Date().toISOString();
            try { localStorage.setItem(STORAGE_KEY, JSON.stringify(seen)); } catch(e) {}
        }

        function isTaskUnseen(taskId, lastModified) {
            if (!lastModified) return false; // no messages = not "new"
            var seen = getSeenTasks();
            if (!seen[taskId]) return true; // never opened
            // Task was modified after it was last seen
            return lastModified > seen[taskId];
        }

        // Apply unread class to table rows
        document.querySelectorAll('tr.clickable-row[data-task-id]').forEach(function(row) {
            var taskId = row.getAttribute('data-task-id');
            var lastMod = row.getAttribute('data-last-modified');
            if (isTaskUnseen(taskId, lastMod)) {
                row.classList.add('task-unread');
            }
        });

        // Apply unread class to Kanban cards
        document.querySelectorAll('.kb-card[data-task-id]').forEach(function(card) {
            var taskId = card.getAttribute('data-task-id');
            var lastMod = card.getAttribute('data-last-modified');
            if (isTaskUnseen(taskId, lastMod)) {
                card.classList.add('task-unread');
            }
        });

        // Mark task as seen when clicking a row (before navigation)
        document.querySelectorAll('tr.clickable-row[data-task-id]').forEach(function(row) {
            row.addEventListener('click', function() {
                var taskId = this.getAttribute('data-task-id');
                var lastMod = this.getAttribute('data-last-modified');
                markTaskSeen(taskId, lastMod || new Date().toISOString());
            });
        });

        // Mark task as seen when clicking a Kanban card link
        document.querySelectorAll('.kb-card[data-task-id] .kb-card-title a').forEach(function(link) {
            link.addEventListener('click', function() {
                var card = this.closest('.kb-card');
                var taskId = card.getAttribute('data-task-id');
                var lastMod = card.getAttribute('data-last-modified');
                markTaskSeen(taskId, lastMod || new Date().toISOString());
            });
        });

        // Expose for use by other code (e.g., inline add card)
        window.markTaskSeen = markTaskSeen;

        // Cleanup: remove tasks from seen list that are no longer in the user's task list (prevent infinite growth)
        var currentTaskIds = {};
        document.querySelectorAll('[data-task-id]').forEach(function(el) {
            currentTaskIds[el.getAttribute('data-task-id')] = true;
        });
        var seen = getSeenTasks();
        var cleaned = false;
        for (var id in seen) {
            if (!currentTaskIds[id]) {
                delete seen[id];
                cleaned = true;
            }
        }
        if (cleaned) {
            try { localStorage.setItem(STORAGE_KEY, JSON.stringify(seen)); } catch(e) {}
        }
    })();
    </script>
    <style>
    @keyframes slideUp {
        from { opacity: 1; transform: translateX(-50%) translateY(0); }
        to { opacity: 0; transform: translateX(-50%) translateY(-20px); }
    }
    </style>
</body>
</html>
<?php
?>
