<?php
include("./includes/date_functions.php");
include("./includes/common.php");

CheckSecurity(1);

$sort = GetParam("sort");
$close_tasks = GetParam("close_tasks");
$project_id = GetParam("project_id");
$action = GetParam("action");
$task_id = (int) GetParam("task_id");
$task_ids = GetParam("task_ids");
$completion = GetParam("completion");

if ((getsessionparam("privilege_id") == 9) || (!isset($project_id))) {
    header("Location: index.php");
    exit;
}

$session_user_id = GetSessionParam("UserID");
$user_name = GetSessionParam("UserName");

// Handle actions
$return_url = "view_project_tasks.php?project_id=" . $project_id . ($sort ? "&sort=" . urlencode($sort) : "");

switch ($action) {
    case "close":
        if ($task_id && is_numeric($task_id)) {
            $db->query("SELECT task_title FROM tasks WHERE task_id = " . ToSQL($task_id, "integer"));
            $tname = $db->next_record() ? $db->f("task_title") : '';
            $_SESSION['flash_message'] = array('type' => 'success', 'text' => 'Task "' . $tname . '" has been closed.');
            close_task($task_id, "");
            header("Location: " . $return_url);
            exit;
        } elseif (strlen($task_ids)) {
            // Bulk close
            $ids = array_filter(array_map('intval', explode(',', $task_ids)));
            $count = 0;
            foreach ($ids as $tid) {
                if ($tid > 0) { close_task($tid, ""); $count++; }
            }
            $_SESSION['flash_message'] = array('type' => 'success', 'text' => $count . ' task(s) closed.');
            header("Location: " . $return_url);
            exit;
        }
        break;
    case "change_status":
        $new_status = (int) GetParam("new_status");
        if ($new_status > 0 && strlen($task_ids)) {
            $ids = array_filter(array_map('intval', explode(',', $task_ids)));
            foreach ($ids as $tid) {
                $db->query("UPDATE tasks SET task_status_id=" . ToSQL($new_status, "integer") . " WHERE task_id=" . ToSQL($tid, "integer"));
            }
            $_SESSION['flash_message'] = array('type' => 'success', 'text' => count($ids) . ' task(s) status updated.');
            header("Location: " . $return_url);
            exit;
        }
        break;
    case "reassign":
        $new_user = (int) GetParam("new_user");
        if ($new_user > 0 && strlen($task_ids)) {
            $ids = array_filter(array_map('intval', explode(',', $task_ids)));
            foreach ($ids as $tid) {
                $db->query("UPDATE tasks SET responsible_user_id=" . ToSQL($new_user, "integer") . " WHERE task_id=" . ToSQL($tid, "integer"));
            }
            $_SESSION['flash_message'] = array('type' => 'success', 'text' => count($ids) . ' task(s) reassigned.');
            header("Location: " . $return_url);
            exit;
        }
        break;
}

// Get project details
$sql = "SELECT * FROM projects WHERE project_id = " . ToSQL($project_id, "integer");
$db->query($sql);
if (!$db->next_record()) {
    header("Location: index.php");
    exit;
}
$project = array(
    'project_id' => $db->f("project_id"),
    'project_title' => $db->f("project_title"),
    'parent_project_id' => $db->f("parent_project_id"),
    'project_status_id' => $db->f("project_status_id")
);

// Get project statuses for dropdown
$statuses = array();
$sql = "SELECT project_status_id, status_desc FROM projects_statuses 
        WHERE parent_project_id = " . ToSQL($project['parent_project_id'], "integer") . "
        ORDER BY status_order ASC";
$db->query($sql);
while ($db->next_record()) {
    $statuses[] = array(
        'id' => $db->f("project_status_id"),
        'name' => $db->f("status_desc")
    );
}

// Get assigned users for project info
$assigned_users = array();
$sql = "SELECT u.user_id, u.first_name, CONCAT(u.first_name, ' ', u.last_name) as user_name
        FROM users_projects up 
        LEFT JOIN users u ON up.user_id = u.user_id
        WHERE up.project_id = " . ToSQL($project_id, "integer") . "
        ORDER BY u.first_name";
$db->query($sql);
while ($db->next_record()) {
    $assigned_users[] = array('id' => $db->f("user_id"), 'name' => $db->f("user_name"), 'first_name' => $db->f("first_name"));
}

// All active users (for adding to project)
$all_active_users = array();
$sql = "SELECT user_id, CONCAT(first_name, ' ', last_name) AS full_name FROM users WHERE is_deleted IS NULL ORDER BY first_name";
$db->query($sql);
while ($db->next_record()) {
    $all_active_users[] = array('id' => $db->f("user_id"), 'name' => $db->f("full_name"));
}

// Get task statuses for bulk change (limited to common statuses)
$allowed_status_ids = array(7, 9, 1, 8, 4, 5, 6); // new, reassigned, in progress, waiting, done, question, answer
$task_statuses = array();
$sql = "SELECT status_id, status_desc FROM lookup_tasks_statuses WHERE status_id IN (" . implode(',', $allowed_status_ids) . ") ORDER BY FIELD(status_id, " . implode(',', $allowed_status_ids) . ")";
$db->query($sql);
while ($db->next_record()) {
    $task_statuses[] = array('id' => $db->f("status_id"), 'name' => $db->f("status_desc"));
}

// Get project members for reassign
$all_users = array();
$sql = "SELECT u.user_id, CONCAT(u.first_name, ' ', u.last_name) AS full_name 
        FROM users_projects up 
        LEFT JOIN users u ON up.user_id = u.user_id
        WHERE up.project_id = " . ToSQL($project_id, "integer") . "
        AND u.is_deleted IS NULL
        ORDER BY u.first_name";
$db->query($sql);
while ($db->next_record()) {
    $all_users[] = array('id' => $db->f("user_id"), 'name' => $db->f("full_name"));
}
// If project has a parent, also include parent project members
if ($project['parent_project_id']) {
    $existing_ids = array_map(function($u) { return $u['id']; }, $all_users);
    $sql = "SELECT u.user_id, CONCAT(u.first_name, ' ', u.last_name) AS full_name 
            FROM users_projects up 
            LEFT JOIN users u ON up.user_id = u.user_id
            WHERE up.project_id = " . ToSQL($project['parent_project_id'], "integer") . "
            AND u.is_deleted IS NULL
            ORDER BY u.first_name";
    $db->query($sql);
    while ($db->next_record()) {
        if (!in_array($db->f("user_id"), $existing_ids)) {
            $all_users[] = array('id' => $db->f("user_id"), 'name' => $db->f("full_name"));
        }
    }
    // Re-sort by name
    usort($all_users, function($a, $b) { return strcasecmp($a['name'], $b['name']); });
}

// Status classes for styling
$status_classes = array(
    1 => 'in-progress', 2 => 'on-hold', 3 => 'rejected', 4 => 'done',
    5 => 'question', 6 => 'answer', 7 => 'new', 8 => 'waiting',
    9 => 'reassigned', 10 => 'bug', 11 => 'deadline', 12 => 'bug-resolved',
    13 => 'ready-to-document', 14 => 'documented'
);

// Get tasks
$tasks = array();
$total_time = 0;

$sql = "SELECT t.*, ls.status_desc,
        CONCAT(u.first_name, ' ', u.last_name) AS responsible_name,
        CONCAT(c.first_name, ' ', c.last_name) AS created_name,
        u.user_id AS responsible_id,
        c.user_id AS created_id,
        DATE_FORMAT(t.creation_date, '%d %b %y') AS creation_date_fmt,
        DATE_FORMAT(t.planed_date, '%d %b %y') AS planed_date_fmt
        FROM tasks t
        LEFT JOIN users u ON u.user_id = t.responsible_user_id
        LEFT JOIN users c ON c.user_id = t.created_person_id
        LEFT JOIN lookup_tasks_statuses ls ON ls.status_id = t.task_status_id
        WHERE t.project_id = " . ToSQL($project_id, "integer");

switch ($sort) {
    case 'by_title':      $sql .= " ORDER BY t.task_title ASC"; break;
    case 'by_responsible': $sql .= " ORDER BY responsible_name ASC"; break;
    case 'by_created':    $sql .= " ORDER BY created_name ASC"; break;
    case 'by_hours':      $sql .= " ORDER BY t.actual_hours DESC"; break;
    case 'by_planed_date': $sql .= " ORDER BY t.planed_date DESC"; break;
    case 'by_completion': $sql .= " ORDER BY t.completion DESC"; break;
    case 'by_status':     $sql .= " ORDER BY t.task_status_id ASC"; break;
    default:              $sql .= " ORDER BY t.task_id DESC";
}

$db->query($sql);
while ($db->next_record()) {
    $is_overdue = ($db->f("planed_date") != '0000-00-00' && strtotime($db->f("planed_date")) < time());
    $tasks[] = array(
        'task_id' => $db->f("task_id"),
        'task_title' => $db->f("task_title"),
        'responsible_id' => $db->f("responsible_id"),
        'responsible_name' => $db->f("responsible_name"),
        'created_id' => $db->f("created_id"),
        'created_name' => $db->f("created_name"),
        'actual_hours' => to_hours($db->f("actual_hours")),
        'estimated_hours' => $db->f("estimated_hours"),
        'completion' => $db->f("completion"),
        'planed_date' => $db->f("planed_date_fmt"),
        'planed_date_raw' => $db->f("planed_date"),
        'creation_date' => $db->f("creation_date_fmt"),
        'status_id' => $db->f("task_status_id"),
        'status_desc' => $db->f("status_desc"),
        'is_closed' => $db->f("is_closed"),
        'is_periodic' => ($db->f("task_type_id") == 3),
        'is_overdue' => $is_overdue
    );
    $total_time += $db->f("actual_hours");
}

$total_time_formatted = to_hours($total_time);
$open_task_count = 0;
foreach ($tasks as $t) { if (!$t['is_closed']) $open_task_count++; }

// ==================== Timeline Data ====================
$task_ids_list = array();
foreach ($tasks as $t) { $task_ids_list[] = (int)$t['task_id']; }

$timeline_data = array(); // keyed by task_id
$timeline_min_date = date('Y-m-d'); // will be pushed back
$timeline_max_date = date('Y-m-d');

if (!empty($task_ids_list)) {
    $ids_sql = implode(',', $task_ids_list);

    // Initialize timeline_data for each task
    foreach ($tasks as $t) {
        $tid = (int)$t['task_id'];
        $timeline_data[$tid] = array(
            'task_id' => $tid,
            'title' => $t['task_title'],
            'responsible' => $t['responsible_name'],
            'status_id' => (int)$t['status_id'],
            'status_desc' => $t['status_desc'],
            'is_closed' => $t['is_closed'] ? 1 : 0,
            'is_periodic' => $t['is_periodic'] ? 1 : 0,
            'created' => '', // will fill from raw date
            'events' => array() // array of {date, type, hours?}
        );
    }

    // Get raw creation dates
    $db->query("SELECT task_id, DATE(creation_date) AS cdate, DATE_FORMAT(creation_date, '%Y-%m-%d') AS cd FROM tasks WHERE task_id IN ($ids_sql)");
    while ($db->next_record()) {
        $tid = (int)$db->f("task_id");
        $cd = $db->f("cd");
        if (isset($timeline_data[$tid])) {
            $timeline_data[$tid]['created'] = $cd;
            if ($cd && $cd !== '0000-00-00' && $cd < $timeline_min_date) $timeline_min_date = $cd;
        }
    }

    // Messages per task (grouped by date)
    $db->query("SELECT identity_id AS task_id, DATE(message_date) AS mdate, COUNT(*) AS cnt 
                FROM messages 
                WHERE identity_type = 'task' AND identity_id IN ($ids_sql)
                GROUP BY identity_id, DATE(message_date)
                ORDER BY mdate ASC");
    while ($db->next_record()) {
        $tid = (int)$db->f("task_id");
        $mdate = $db->f("mdate");
        $cnt = (int)$db->f("cnt");
        if (isset($timeline_data[$tid]) && $mdate && $mdate !== '0000-00-00') {
            $timeline_data[$tid]['events'][] = array('date' => $mdate, 'type' => 'message', 'count' => $cnt);
            if ($mdate < $timeline_min_date) $timeline_min_date = $mdate;
            if ($mdate > $timeline_max_date) $timeline_max_date = $mdate;
        }
    }

    // Time reports per task (grouped by date)
    $db->query("SELECT task_id, DATE(started_date) AS rdate, SUM(spent_hours) AS hours, COUNT(*) AS cnt
                FROM time_report 
                WHERE task_id IN ($ids_sql)
                GROUP BY task_id, DATE(started_date)
                ORDER BY rdate ASC");
    while ($db->next_record()) {
        $tid = (int)$db->f("task_id");
        $rdate = $db->f("rdate");
        $hours = round($db->f("hours"), 2);
        if (isset($timeline_data[$tid]) && $rdate && $rdate !== '0000-00-00') {
            $timeline_data[$tid]['events'][] = array('date' => $rdate, 'type' => 'time', 'hours' => $hours);
            if ($rdate < $timeline_min_date) $timeline_min_date = $rdate;
            if ($rdate > $timeline_max_date) $timeline_max_date = $rdate;
        }
    }

    // Also add task creation as an event
    foreach ($timeline_data as $tid => &$td) {
        if ($td['created'] && $td['created'] !== '0000-00-00') {
            $td['events'][] = array('date' => $td['created'], 'type' => 'created');
        }
    }
    unset($td);
}

// Enforce: max range is 12 months back from today
$twelve_months_ago = date('Y-m-d', strtotime('-12 months'));
if ($timeline_min_date < $twelve_months_ago) {
    $timeline_min_date = $twelve_months_ago;
}

// Extend max date to today if it's in the past
if ($timeline_max_date < date('Y-m-d')) {
    $timeline_max_date = date('Y-m-d');
}

// Helper: ensure utf-8 for json_encode on PHP 5.6
if (!function_exists('dash_utf8')) {
    function dash_utf8($val) {
        if (is_array($val)) { return array_map('dash_utf8', $val); }
        if (is_string($val)) {
            $enc = mb_detect_encoding($val, 'UTF-8, ISO-8859-1, Windows-1252', true);
            if ($enc && $enc !== 'UTF-8') { $val = mb_convert_encoding($val, 'UTF-8', $enc); }
        }
        return $val;
    }
}
$timeline_json = json_encode(dash_utf8(array_values($timeline_data)));
if (!$timeline_json) $timeline_json = '[]';

// ==================== Time Report Raw Data (for client-side filtering) ====================
$time_raw = array();
if (!empty($task_ids_list)) {
    $sql = "SELECT tr.spent_hours, DATE(tr.started_date) AS report_date,
                   tr.user_id, CONCAT(u.first_name, ' ', u.last_name) AS user_name,
                   tr.task_id, t.task_title, CONCAT(ru.first_name, ' ', ru.last_name) AS responsible_name
            FROM time_report tr
            LEFT JOIN users u ON u.user_id = tr.user_id
            LEFT JOIN tasks t ON t.task_id = tr.task_id
            LEFT JOIN users ru ON ru.user_id = t.responsible_user_id
            WHERE tr.task_id IN ($ids_sql)
            ORDER BY tr.started_date ASC";
    $db->query($sql);
    while ($db->next_record()) {
        $time_raw[] = array(
            'hours' => round($db->f("spent_hours"), 4),
            'date' => $db->f("report_date"),
            'uid' => (int)$db->f("user_id"),
            'uname' => $db->f("user_name"),
            'tid' => (int)$db->f("task_id"),
            'tname' => $db->f("task_title"),
            'resp' => $db->f("responsible_name")
        );
    }
}
$time_raw_utf8 = dash_utf8($time_raw);
$time_raw_json = json_encode($time_raw_utf8);
if ($time_raw_json === false || $time_raw_json === '' || $time_raw_json === null) $time_raw_json = '[]';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($project['project_title']); ?> - Tasks</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; color: #2d3748; }

        .container { max-width: 1600px; margin: 0 auto; padding: 24px; }
        .page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
        .page-header-left { flex: 1; min-width: 200px; }
        .page-header-right { display: flex; flex-direction: column; align-items: flex-end; gap: 8px; }
        .page-title { font-size: 1.75rem; font-weight: 700; color: #2d3748; }
        .project-title-wrap { display: inline-flex; align-items: center; gap: 8px; position: relative; }
        .project-switcher-btn { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; background: #edf2f7; border: 1px solid #e2e8f0; color: #4a5568; cursor: pointer; transition: all 0.15s; flex-shrink: 0; }
        .project-switcher-btn:hover { background: #e2e8f0; color: #667eea; border-color: #cbd5e0; }
        .project-switcher-btn svg { width: 16px; height: 16px; }
        .project-switcher-dropdown { position: absolute; left: 0; top: 100%; margin-top: 8px; width: 320px; max-height: 360px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 12px 40px rgba(0,0,0,0.15); z-index: 1000; display: none; overflow: hidden; }
        .project-switcher-dropdown.show { display: block; }
        .project-switcher-search { width: 100%; padding: 10px 14px; border: none; border-bottom: 1px solid #e2e8f0; font-size: 0.9rem; outline: none; font-family: inherit; }
        .project-switcher-list { max-height: 280px; overflow-y: auto; }
        .project-switcher-option { padding: 10px 14px; cursor: pointer; font-size: 0.9rem; color: #2d3748; transition: background 0.1s; border-bottom: 1px solid #f0f0f0; }
        .project-switcher-option:last-child { border-bottom: none; }
        .project-switcher-option:hover { background: #f7fafc; }
        .project-switcher-option.current { background: #ebf4ff; color: #667eea; font-weight: 500; }
        .project-switcher-option.highlighted { background: #ebf4ff; color: #667eea; }
        .project-switcher-empty { padding: 20px; text-align: center; color: #718096; font-size: 0.9rem; }
        html.dark-mode .project-switcher-btn { background: rgba(0,0,0,0.2); border-color: rgba(255,255,255,0.2); }
        html.dark-mode .project-switcher-btn:hover { background: rgba(0,0,0,0.35); }
        html.dark-mode .project-switcher-dropdown { background: #161b22; border-color: #2d333b; }
        html.dark-mode .project-switcher-search { background: #161b22; border-bottom-color: #2d333b; color: #e2e8f0; }
        html.dark-mode .project-switcher-search::placeholder { color: #6b7280; }
        html.dark-mode .project-switcher-option { color: #e2e8f0; border-bottom-color: #2d333b; }
        html.dark-mode .project-switcher-option:hover { background: #1c2333; }
        html.dark-mode .project-switcher-option.current,
        html.dark-mode .project-switcher-option.highlighted { background: rgba(102,126,234,0.2); color: #90cdf4; }
        html.dark-mode .project-switcher-empty { color: #8b949e; }
        .page-subtitle { color: #718096; font-size: 0.9rem; margin-top: 4px; }
        .page-actions { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
        .header-team { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; justify-content: flex-end; }

        .card { background: #fff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); overflow: hidden; margin-bottom: 24px; }
        .card-header { padding: 16px 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; justify-content: space-between; align-items: center; }
        .card-header.light { background: #f8f9fa; border-bottom: 1px solid #e2e8f0; }
        .card-header.light .card-title { color: #2d3748; }
        .card-title { font-weight: 600; font-size: 1rem; color: #fff; }
        .card-body { padding: 20px; }

        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
        .info-item { display: flex; flex-direction: column; gap: 4px; }
        .info-label { font-size: 0.8rem; color: #718096; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-value { font-weight: 500; color: #2d3748; }

        .filter-bar { display: flex; gap: 16px; align-items: center; flex-wrap: wrap; padding: 16px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
        .filter-item { display: flex; align-items: center; gap: 8px; }
        .filter-item label { font-size: 0.85rem; color: #4a5568; }
        .filter-item input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; }

        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 20px; border-radius: 10px; font-weight: 500; font-size: 0.9rem; text-decoration: none; cursor: pointer; transition: all 0.2s; border: none; font-family: inherit; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(102,126,234,0.4); }
        .btn-outline { background: #fff; border: 1px solid #e2e8f0; color: #4a5568; }
        .btn-outline:hover { background: #f8fafc; border-color: #cbd5e0; }
        .btn-sm { padding: 6px 12px; font-size: 0.8rem; }
        .btn-danger { background: #fed7d7; color: #c53030; }
        .btn-danger:hover { background: #feb2b2; }

        .assigned-users { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .assigned-user { display: inline-flex; align-items: center; gap: 6px; background: #edf2f7; padding: 4px 6px 4px 12px; border-radius: 20px; font-size: 0.8rem; color: #4a5568; transition: all 0.15s; }
        .assigned-user a { color: #4a5568; text-decoration: none; font-weight: 500; }
        .assigned-user a:hover { color: #667eea; }
        .assigned-user .remove-member { display: inline-flex; align-items: center; justify-content: center; width: 18px; height: 18px; border-radius: 50%; background: transparent; border: none; cursor: pointer; color: #a0aec0; font-size: 0.75rem; line-height: 1; transition: all 0.15s; padding: 0; }
        .assigned-user .remove-member:hover { background: #e53e3e; color: #fff; }
        .team-add-btn { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; background: #e2e8f0; border: 2px dashed #cbd5e0; cursor: pointer; color: #718096; font-size: 1rem; transition: all 0.15s; line-height: 1; }
        .team-add-btn:hover { background: #667eea; border-color: #667eea; color: #fff; }
        .team-add-dropdown { position: absolute; z-index: 100; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; box-shadow: 0 8px 30px rgba(0,0,0,0.12); width: 240px; max-height: 300px; overflow: hidden; display: none; }
        .team-add-dropdown.show { display: block; }
        .team-add-search { width: 100%; padding: 10px 14px; border: none; border-bottom: 1px solid #e2e8f0; font-size: 0.85rem; outline: none; font-family: inherit; }
        .team-add-list { max-height: 220px; overflow-y: auto; }
        .team-add-option { padding: 8px 14px; cursor: pointer; font-size: 0.85rem; color: #4a5568; transition: background 0.1s; }
        .team-add-option:hover { background: #f7fafc; color: #2d3748; }
        .team-add-option.disabled { color: #cbd5e0; cursor: default; }
        .team-add-option.disabled:hover { background: transparent; }

        /* Confirm popup */
        .confirm-overlay { position: fixed; inset: 0; z-index: 10000; background: rgba(0,0,0,0.35); display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.15s; }
        .confirm-overlay.show { opacity: 1; }
        .confirm-box { background: #fff; border-radius: 14px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); padding: 28px 32px 24px; width: 360px; max-width: 90vw; transform: translateY(12px) scale(0.97); transition: transform 0.15s; }
        .confirm-overlay.show .confirm-box { transform: translateY(0) scale(1); }
        .confirm-title { font-size: 1rem; font-weight: 600; color: #2d3748; margin-bottom: 8px; }
        .confirm-msg { font-size: 0.9rem; color: #718096; margin-bottom: 24px; line-height: 1.5; }
        .confirm-msg strong { color: #2d3748; }
        .confirm-actions { display: flex; justify-content: flex-end; gap: 10px; }
        .confirm-actions button { padding: 8px 20px; border-radius: 8px; font-size: 0.85rem; font-weight: 500; cursor: pointer; border: none; font-family: inherit; transition: all 0.15s; }
        .confirm-cancel { background: #edf2f7; color: #4a5568; }
        .confirm-cancel:hover { background: #e2e8f0; }
        .confirm-ok { background: #e53e3e; color: #fff; }
        .confirm-ok:hover { background: #c53030; }
        .confirm-hint { font-size: 0.7rem; color: #a0aec0; margin-top: 12px; text-align: right; }

        /* Deadline badges on kanban cards */
        .deadline-badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 6px; font-size: 0.72rem; font-weight: 500; }
        .deadline-met { background: #c6f6d5; color: #276749; }
        .deadline-met svg { opacity: 0.7; }
        .deadline-overdue { background: #fed7d7; color: #c53030; font-weight: 600; animation: pulse-deadline 2s ease-in-out infinite; }
        .deadline-upcoming { background: #fefcbf; color: #975a16; font-weight: 600; }
        .deadline-future { background: #edf2f7; color: #718096; }
        @keyframes pulse-deadline { 0%,100% { opacity: 1; } 50% { opacity: 0.7; } }

        /* Table */
        .scroll-table { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { padding: 10px 14px; text-align: left; font-weight: 600; font-size: 0.73rem; text-transform: uppercase; letter-spacing: 0.5px; color: #718096; background: #f8fafc; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
        .data-table th a { color: #718096; text-decoration: none; }
        .data-table th a:hover { color: #667eea; }
        .data-table td { padding: 10px 14px; border-bottom: 1px solid #f1f5f9; color: #4a5568; font-size: 0.88rem; }
        .data-table tr:hover td { background: #f8fafc; }
        .data-table tr.clickable-row { cursor: pointer; }
        .data-table tr.clickable-row:hover td { background: #edf2f7; }
        .data-table tr.overdue td { background: #fff5f5; }
        .data-table tr.overdue:hover td { background: #fed7d7; }
        .data-table tr.closed td { opacity: 0.55; }
        .data-table tr.selected td { background: #ebf4ff !important; }
        .text-center { text-align: center; }

        .task-title-cell { max-width: 340px; }
        .task-title-cell a { color: #2d3748; text-decoration: none; font-weight: 500; }
        .task-title-cell a:hover { color: #667eea; }

        .status-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 0.73rem; font-weight: 500; white-space: nowrap; }
        .status-in-progress { background: #c6f6d5; color: #276749; }
        .status-on-hold { background: #feebc8; color: #c05621; }
        .status-done { background: #bee3f8; color: #2b6cb0; }
        .status-new, .status-not-started { background: #e2e8f0; color: #4a5568; }
        .status-reassigned { background: #e9d8fd; color: #6b46c1; }
        .status-bug { background: #fed7d7; color: #c53030; }
        .status-bug-resolved { background: #c6f6d5; color: #276749; }
        .status-question { background: #faf089; color: #975a16; }
        .status-answer { background: #bee3f8; color: #2b6cb0; }
        .status-waiting { background: #feebc8; color: #c05621; }
        .status-deadline { background: #fed7d7; color: #c53030; }
        .status-rejected { background: #fed7d7; color: #c53030; }
        .status-ready-to-document { background: #bee3f8; color: #2b6cb0; }
        .status-documented { background: #c6f6d5; color: #276749; }

        .user-link { color: #667eea; text-decoration: none; }
        .user-link:hover { text-decoration: underline; }

        .completion-bar { width: 50px; height: 5px; background: #e2e8f0; border-radius: 3px; overflow: hidden; display: inline-block; vertical-align: middle; margin-right: 4px; }
        .completion-fill { height: 100%; background: linear-gradient(90deg, #48bb78, #38a169); border-radius: 3px; }

        .summary-row td { background: #f8fafc; font-weight: 600; border-top: 2px solid #e2e8f0; }

        .task-actions { display: flex; gap: 6px; justify-content: flex-end; }
        .action-link { padding: 3px 8px; border-radius: 4px; font-size: 0.73rem; text-decoration: none; font-weight: 500; cursor: pointer; border: none; font-family: inherit; }
        .action-close { background: #e2e8f0; color: #4a5568; }
        .action-close:hover { background: #cbd5e0; }

        .empty-state { text-align: center; padding: 60px 20px; color: #718096; }
        .empty-state p { font-size: 1.1rem; margin-bottom: 16px; }

        /* Checkbox */
        .row-checkbox { width: 16px; height: 16px; cursor: pointer; accent-color: #667eea; }
        .th-checkbox { width: 16px; height: 16px; cursor: pointer; accent-color: #667eea; }

        /* Bulk action bar */
        .bulk-bar { display: none; position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%); background: #2d3748; color: #fff; padding: 12px 24px; border-radius: 12px; box-shadow: 0 8px 30px rgba(0,0,0,0.25); z-index: 9000; font-size: 0.88rem; align-items: center; gap: 16px; white-space: nowrap; }
        .bulk-bar.show { display: flex; }
        .bulk-bar .bulk-count { font-weight: 700; color: #90cdf4; }
        .bulk-bar .bulk-btn { padding: 6px 14px; border-radius: 6px; font-size: 0.82rem; font-weight: 500; cursor: pointer; border: none; font-family: inherit; transition: all 0.15s; }
        .bulk-btn-close { background: #fc8181; color: #fff; }
        .bulk-btn-close:hover { background: #f56565; }
        .bulk-btn-status { background: #90cdf4; color: #2a4365; }
        .bulk-btn-status:hover { background: #63b3ed; }
        .bulk-btn-reassign { background: #d6bcfa; color: #44337a; }
        .bulk-btn-reassign:hover { background: #b794f4; }
        .bulk-btn-cancel { background: transparent; color: #a0aec0; border: 1px solid #4a5568; }
        .bulk-btn-cancel:hover { color: #fff; border-color: #718096; }

        /* Context Menu */
        .context-menu { position: fixed; z-index: 10000; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 8px 30px rgba(0,0,0,0.12), 0 2px 8px rgba(0,0,0,0.06); min-width: 220px; padding: 6px 0; display: none; font-size: 0.85rem; font-family: inherit; }
        .context-menu.show { display: block; }
        .context-menu-item { display: flex; align-items: center; gap: 10px; padding: 8px 16px; cursor: pointer; color: #4a5568; transition: background 0.1s, color 0.1s; white-space: nowrap; }
        .context-menu-item:hover { background: #f7fafc; color: #2d3748; }
        .context-menu-item .ctx-icon { width: 16px; text-align: center; flex-shrink: 0; font-size: 0.9em; }
        .context-menu-item.danger { color: #e53e3e; }
        .context-menu-item.danger:hover { background: #fff5f5; }
        .context-menu-item.success { color: #38a169; }
        .context-menu-item.success:hover { background: #f0fff4; }
        .context-menu-item.muted { color: #a0aec0; font-size: 0.78rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; cursor: default; padding: 6px 16px 2px; }
        .context-menu-item.muted:hover { background: transparent; color: #a0aec0; }
        .context-menu-separator { height: 1px; background: #e2e8f0; margin: 4px 0; }
        .context-menu-submenu { position: relative; }
        .context-menu-submenu > .context-menu-item::after { content: '\203A'; margin-left: auto; font-size: 1.1em; color: #a0aec0; }
        .context-menu-submenu-items { display: none; position: fixed; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 8px 30px rgba(0,0,0,0.12), 0 2px 8px rgba(0,0,0,0.06); min-width: 160px; padding: 6px 0; z-index: 10001; }
        .context-menu-submenu-items.show { display: block; }

        /* Modal */
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 10010; display: none; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal-box { background: #fff; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); max-width: 480px; width: 90%; overflow: hidden; }
        .modal-header { padding: 16px 20px; font-size: 1rem; font-weight: 600; }
        .modal-header.close { background: #fff5f5; color: #c53030; }
        .modal-header.status { background: #ebf8ff; color: #2b6cb0; }
        .modal-header.reassign { background: #faf5ff; color: #6b46c1; }
        .modal-body { padding: 20px; }
        .modal-body select { width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.9rem; font-family: inherit; margin-top: 8px; }
        .modal-body select:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }
        .modal-task-name { font-weight: 600; color: #2d3748; margin-bottom: 8px; }
        .modal-message { color: #718096; font-size: 0.9rem; }
        .modal-footer { padding: 16px 20px; display: flex; gap: 8px; justify-content: flex-end; border-top: 1px solid #f1f5f9; }
        .modal-btn { padding: 8px 18px; border-radius: 8px; font-size: 0.88rem; font-weight: 500; cursor: pointer; border: none; font-family: inherit; transition: all 0.15s; }
        .modal-btn-cancel { background: #e2e8f0; color: #4a5568; }
        .modal-btn-cancel:hover { background: #cbd5e0; }
        .modal-btn-confirm { background: #667eea; color: #fff; }
        .modal-btn-confirm:hover { background: #5a67d8; }
        .modal-btn-confirm.danger { background: #e53e3e; }
        .modal-btn-confirm.danger:hover { background: #c53030; }

        /* View Tabs */
        .header-filters { display: flex; align-items: center; gap: 16px; margin-left: auto; margin-right: 12px; }
        .filter-check { display: flex; align-items: center; gap: 6px; font-size: 0.8rem; color: #718096; cursor: pointer; user-select: none; white-space: nowrap; }
        .filter-check input[type="checkbox"] { width: 15px; height: 15px; cursor: pointer; accent-color: #667eea; }
        .kanban-column.col-hidden { display: none; }
        .view-tabs { display: flex; gap: 0; }
        .view-tab { padding: 8px 18px; font-size: 0.82rem; font-weight: 600; color: #718096; cursor: pointer; border: 1px solid #e2e8f0; background: #fff; transition: all 0.15s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .view-tab:first-child { border-radius: 8px 0 0 8px; }
        .view-tab:last-child { border-radius: 0 8px 8px 0; }
        .view-tab + .view-tab { border-left: none; }
        .view-tab:hover { background: #f7fafc; color: #4a5568; }
        .view-tab.active { background: #667eea; color: #fff; border-color: #667eea; }
        .view-tab svg { width: 14px; height: 14px; }
        .view-panel { display: none; }
        .view-panel.active { display: block; }

        /* Timeline View */
        .timeline-container { padding: 20px; }
        .timeline-toolbar { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
        .timeline-legend { display: flex; gap: 14px; margin-left: auto; font-size: 0.75rem; color: #718096; align-items: center; }
        .timeline-legend-item { display: flex; align-items: center; gap: 5px; }

        .tl-wrapper { position: relative; }
        .tl-header { display: flex; position: sticky; top: 0; z-index: 5; background: #fff; border-bottom: 2px solid #e2e8f0; }
        .tl-header-label { flex: 0 0 auto; padding: 10px 14px; font-weight: 600; font-size: 0.78rem; color: #718096; text-transform: uppercase; letter-spacing: 0.5px; border-right: 1px solid #e2e8f0; box-sizing: border-box; }
        .tl-header-dates { display: flex; position: relative; overflow: hidden; flex: 0 0 auto; }
        .tl-month { text-align: center; font-size: 0.72rem; font-weight: 600; color: #4a5568; padding: 8px 0; border-right: 1px solid #edf2f7; overflow: hidden; }

        .tl-body { position: relative; }
        .tl-row { display: flex; border-bottom: 1px solid #f0f0f0; min-height: 40px; transition: background 0.1s; }
        .tl-row:hover { background: #f7fafc; }
        .tl-row.closed .tl-task-name { color: #a0aec0; }
        .tl-row.closed .tl-task-user { color: #cbd5e0; }
        .tl-task-label { flex: 0 0 auto; padding: 6px 12px; display: flex; align-items: center; gap: 8px; border-right: 1px solid #e2e8f0; overflow: hidden; box-sizing: border-box; }
        .tl-task-name { font-size: 0.78rem; font-weight: 500; color: #2d3748; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; cursor: pointer; text-decoration: none; display: block; }
        .tl-task-name:hover { color: #667eea; }
        .tl-task-user { font-size: 0.65rem; color: #a0aec0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tl-task-status { flex: 0 0 auto; padding: 6px 8px; display: flex; align-items: center; justify-content: center; border-right: 1px solid #e2e8f0; box-sizing: border-box; }
        .tl-task-status .status-badge { font-size: 0.65rem; padding: 2px 8px; }
        .tl-chart { position: relative; overflow: hidden; flex: 0 0 auto; }

        .tl-grid-line { position: absolute; top: 0; bottom: 0; width: 1px; pointer-events: none; }
        .tl-grid-line.month-line { background: #edf2f7; }
        .tl-today-line { position: absolute; top: 0; bottom: 0; width: 2px; background: #e53e3e; z-index: 3; pointer-events: none; opacity: 0.6; }

        .tl-week-bar { transition: opacity 0.15s; }
        .tl-week-bar:hover { opacity: 1 !important; }

        .tl-tooltip { position: fixed; z-index: 10000; background: #2d3748; color: #fff; padding: 8px 12px; border-radius: 8px; font-size: 0.78rem; line-height: 1.4; pointer-events: none; max-width: 320px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); display: none; }
        .tl-tooltip.show { display: block; }

        .tl-no-data { text-align: center; padding: 60px 20px; color: #a0aec0; font-size: 0.9rem; }

        /* Time View */
        .time-view-container { padding: 24px; }
        .time-period-bar { display: flex; align-items: center; gap: 8px; margin-bottom: 20px; flex-wrap: wrap; }
        .tp-btn { padding: 5px 14px; border: 1px solid #e2e8f0; border-radius: 6px; background: #fff; color: #4a5568; font-size: 0.78rem; font-weight: 500; cursor: pointer; font-family: inherit; transition: all 0.15s; }
        .tp-btn:hover { background: #f7fafc; border-color: #cbd5e0; }
        .tp-btn.active { background: #667eea; color: #fff; border-color: #667eea; }
        .time-view-grid { display: grid; grid-template-columns: 380px 1fr; gap: 32px; align-items: start; }
        .time-section-title { font-size: 0.88rem; font-weight: 600; color: #4a5568; margin-bottom: 16px; }
        .time-chart-section { display: flex; flex-direction: column; align-items: center; }
        .time-chart-wrap { position: relative; width: 280px; height: 280px; margin: 0 auto; }
        .time-chart-wrap canvas { display: block; }
        .pie-legend { display: flex; flex-wrap: wrap; gap: 6px 16px; margin-top: 16px; justify-content: center; max-width: 360px; }
        .pie-legend-item { display: flex; align-items: center; gap: 6px; font-size: 0.78rem; color: #4a5568; cursor: default; padding: 4px 8px; border-radius: 6px; transition: background 0.1s; }
        .pie-legend-item:hover { background: #f7fafc; }
        .pie-legend-dot { width: 12px; height: 12px; border-radius: 3px; flex-shrink: 0; }
        .pie-legend-value { color: #718096; font-weight: 600; margin-left: 2px; }
        .pie-center-text { position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); text-align: center; pointer-events: none; }
        .pie-center-total { font-size: 1.4rem; font-weight: 700; color: #2d3748; line-height: 1.2; }
        .pie-center-label { font-size: 0.7rem; color: #a0aec0; text-transform: uppercase; letter-spacing: 0.5px; }

        .time-tasks-list { display: flex; flex-direction: column; gap: 0; }
        .time-task-row { display: flex; align-items: center; gap: 12px; padding: 10px 14px; border-bottom: 1px solid #f0f0f0; transition: background 0.1s; }
        .time-task-row:hover { background: #f7fafc; }
        .time-task-rank { width: 22px; height: 22px; border-radius: 50%; background: #edf2f7; color: #718096; font-size: 0.68rem; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .time-task-rank.top { background: #667eea; color: #fff; }
        .time-task-info { flex: 1; min-width: 0; }
        .time-task-title { font-size: 0.82rem; font-weight: 500; color: #2d3748; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-decoration: none; display: block; }
        .time-task-title:hover { color: #667eea; }
        .time-task-person { font-size: 0.68rem; color: #a0aec0; }
        .time-task-hours { font-size: 0.85rem; font-weight: 600; color: #4a5568; white-space: nowrap; flex-shrink: 0; }
        .time-task-bar-bg { width: 80px; height: 6px; background: #edf2f7; border-radius: 3px; flex-shrink: 0; overflow: hidden; }
        .time-task-bar-fill { height: 100%; border-radius: 3px; background: linear-gradient(90deg, #667eea, #764ba2); }

        @media (max-width: 800px) {
            .time-view-grid { grid-template-columns: 1fr; }
        }

        /* Kanban Board */
        .kanban-board { display: flex; gap: 16px; padding: 20px; overflow-x: auto; min-height: 400px; align-items: flex-start; }
        .kanban-column { min-width: 280px; max-width: 320px; flex: 1 0 280px; background: #f4f5f7; border-radius: 12px; display: flex; flex-direction: column; max-height: calc(100vh - 280px); }
        .kanban-column-header { padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 1; border-radius: 12px 12px 0 0; }
        .kanban-column-title { font-weight: 700; font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .kanban-column-count { font-size: 0.72rem; font-weight: 600; padding: 2px 8px; border-radius: 10px; background: rgba(255,255,255,0.5); }
        .kanban-cards { padding: 8px 10px 12px; overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 8px; }
        .kanban-card { background: #fff; border-radius: 8px; padding: 12px 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); cursor: pointer; transition: box-shadow 0.15s, transform 0.15s; border-left: 3px solid transparent; }
        .kanban-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.12); transform: translateY(-1px); }
        .kanban-card-title { font-weight: 600; font-size: 0.85rem; color: #2d3748; margin-bottom: 8px; line-height: 1.35; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        .kanban-card-title a { color: inherit; text-decoration: none; }
        .kanban-card-title a:hover { color: #667eea; }
        .kanban-card-meta { display: flex; flex-wrap: wrap; gap: 6px 12px; font-size: 0.75rem; color: #718096; }
        .kanban-card-meta-item { display: flex; align-items: center; gap: 4px; }
        .kanban-card-meta-item svg { width: 12px; height: 12px; opacity: 0.6; }
        .kanban-card-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 10px; padding-top: 8px; border-top: 1px solid #f0f0f0; }
        .kanban-card-avatar { width: 24px; height: 24px; border-radius: 50%; background: #667eea; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 700; }
        .kanban-card-badge { font-size: 0.68rem; padding: 2px 8px; border-radius: 10px; font-weight: 500; }
        .kanban-card.overdue { border-left-color: #e53e3e; }
        .kanban-card.closed-card { opacity: 0.5; }
        .kanban-card.kb-focus { outline: 2px solid #667eea; outline-offset: 1px; box-shadow: 0 0 0 4px rgba(102,126,234,0.18), 0 4px 12px rgba(0,0,0,0.10); transform: translateY(-1px); z-index: 2; position: relative; }
        .kb-hint { position: fixed; bottom: 16px; left: 50%; transform: translateX(-50%); background: rgba(45,55,72,0.92); color: #fff; padding: 8px 18px; border-radius: 10px; font-size: 0.75rem; display: flex; gap: 12px; align-items: center; z-index: 100; backdrop-filter: blur(4px); transition: opacity 0.3s; pointer-events: none; }
        .kb-hint kbd { display: inline-block; background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.25); border-radius: 4px; padding: 1px 6px; font-size: 0.7rem; font-family: inherit; min-width: 18px; text-align: center; }
        .kanban-card.card-removing { transition: all 0.35s ease; opacity: 0; transform: scale(0.92) translateY(-8px); max-height: 0; padding-top: 0; padding-bottom: 0; margin-top: 0; margin-bottom: 0; overflow: hidden; }

        /* Column colors */
        .kanban-col-new .kanban-column-header { background: #e2e8f0; }
        .kanban-col-new .kanban-column-title { color: #4a5568; }
        .kanban-col-progress .kanban-column-header { background: #c6f6d5; }
        .kanban-col-progress .kanban-column-title { color: #276749; }
        .kanban-col-hold .kanban-column-header { background: #feebc8; }
        .kanban-col-hold .kanban-column-title { color: #c05621; }
        .kanban-col-review .kanban-column-header { background: #e9d8fd; }
        .kanban-col-review .kanban-column-title { color: #6b46c1; }
        .kanban-col-done .kanban-column-header { background: #bee3f8; }
        .kanban-col-done .kanban-column-title { color: #2b6cb0; }
        .kanban-col-closed .kanban-column-header { background: #e2e8f0; }
        .kanban-col-closed .kanban-column-title { color: #a0aec0; }

        .kanban-empty { text-align: center; padding: 24px 12px; color: #a0aec0; font-size: 0.8rem; font-style: italic; }

        /* Add a card */
        .kanban-add-area { padding: 4px 10px 10px; }
        .kanban-add-btn { background: none; border: none; color: #718096; font-size: 0.82rem; cursor: pointer; padding: 8px 6px; width: 100%; text-align: left; border-radius: 6px; transition: background 0.1s, color 0.1s; font-family: inherit; }
        .kanban-add-btn:hover { background: rgba(0,0,0,0.04); color: #4a5568; }
        .kanban-add-form { display: flex; flex-direction: column; gap: 6px; }
        .kanban-add-input { width: 100%; padding: 8px 10px; border: 1px solid #e2e8f0; border-radius: 6px; font-family: inherit; font-size: 0.85rem; resize: none; min-height: 54px; box-sizing: border-box; }
        .kanban-add-input:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }
        .kanban-add-actions { display: flex; align-items: center; gap: 6px; }
        .kanban-add-submit { padding: 6px 14px; background: #667eea; color: #fff; border: none; border-radius: 6px; font-size: 0.82rem; font-weight: 600; cursor: pointer; font-family: inherit; transition: background 0.15s; }
        .kanban-add-submit:hover { background: #5a67d8; }
        .kanban-add-cancel { background: none; border: none; color: #a0aec0; font-size: 1.3rem; cursor: pointer; padding: 2px 8px; line-height: 1; border-radius: 4px; }
        .kanban-add-cancel:hover { color: #718096; background: rgba(0,0,0,0.04); }

        /* Inline rename */
        .kanban-card-title .rename-input,
        .task-title-cell .rename-input { width: 100%; padding: 6px 8px; border: 1px solid #667eea; border-radius: 6px; font-family: inherit; font-size: 0.85rem; font-weight: 600; box-sizing: border-box; resize: none; min-height: 60px; }
        .kanban-card-title .rename-input:focus,
        .task-title-cell .rename-input:focus { outline: none; box-shadow: 0 0 0 2px rgba(102,126,234,0.3); }
        html.dark-mode .kanban-card-title .rename-input,
        html.dark-mode .task-title-cell .rename-input { background: #1c2333; color: #e2e8f0; border-color: #667eea; }

        /* Drag and drop */
        .kanban-card.dragging { opacity: 0.4; transform: rotate(2deg); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
        .kanban-column.drag-over .kanban-cards { background: rgba(102,126,234,0.08); border-radius: 0 0 12px 12px; }
        .kanban-column.drag-over .kanban-column-header { box-shadow: 0 0 0 2px #667eea inset; }
        .kanban-card-drop-indicator { height: 3px; background: #667eea; border-radius: 3px; margin: 4px 0; transition: all 0.15s; }
        .kanban-card[draggable="true"] { cursor: grab; }
        .kanban-card[draggable="true"]:active { cursor: grabbing; }
        .kanban-toast { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); background: #48bb78; color: #fff; padding: 10px 24px; border-radius: 8px; font-size: 0.9rem; font-weight: 500; z-index: 99999; box-shadow: 0 4px 12px rgba(0,0,0,0.15); transition: opacity 0.3s; }
        .kanban-toast.error { background: #e53e3e; }

        /* Flash message */
        .flash-message { position: fixed; top: 80px; left: 50%; transform: translateX(-50%); background: #c6f6d5; color: #276749; padding: 12px 24px; border-radius: 10px; font-weight: 500; z-index: 10020; box-shadow: 0 4px 12px rgba(0,0,0,0.1); animation: flashIn 0.3s ease; }
        @keyframes flashIn { from { opacity: 0; transform: translateX(-50%) translateY(-10px); } to { opacity: 1; transform: translateX(-50%) translateY(0); } }

        @media (max-width: 768px) {
            .page-header { flex-direction: column; }
            .filter-bar { flex-direction: column; align-items: flex-start; }
            .bulk-bar { flex-wrap: wrap; bottom: 12px; padding: 10px 16px; gap: 10px; }
            .kanban-board { padding: 12px; gap: 12px; }
            .kanban-column { min-width: 260px; }
        }

        /* ==================== DARK MODE ==================== */
        html.dark-mode .btn-outline { background: #1c2333; border-color: #2d333b; color: #cbd5e0; }
        html.dark-mode .btn-outline:hover { background: #2d333b; border-color: #444c56; color: #fff; }
        html.dark-mode .view-tab { background: #1c2333; border-color: #2d333b; color: #8b949e; }
        html.dark-mode .view-tab:hover { background: #252d3a; color: #e2e8f0; }
        html.dark-mode .view-tab.active { background: #667eea; color: #fff; border-color: #667eea; }
        html.dark-mode .summary-row td { background: #1c2333; border-top-color: #2d333b; color: #e2e8f0; }
        html.dark-mode .filter-check { color: #cbd5e0; }
        html.dark-mode .assigned-user { background: #1c2333; color: #cbd5e0; }
        html.dark-mode .assigned-user a { color: #cbd5e0; }
        html.dark-mode .assigned-user a:hover { color: #90cdf4; }
        html.dark-mode .assigned-user .remove-member { color: #8b949e; }
        html.dark-mode .team-add-btn { background: #1c2333; border-color: #2d333b; color: #8b949e; }
        html.dark-mode .team-add-btn:hover { background: #667eea; border-color: #667eea; color: #fff; }
        html.dark-mode .team-add-dropdown { background: #161b22; border-color: #2d333b; box-shadow: 0 8px 30px rgba(0,0,0,0.6); }
        html.dark-mode .action-close { background: #1c2333; color: #cbd5e0; }
        html.dark-mode .action-close:hover { background: #2d333b; color: #fff; }
        html.dark-mode .data-table tr.overdue td { background: #2a1215; }
        html.dark-mode .data-table tr.overdue:hover td { background: #3b1a1e; }
        html.dark-mode .data-table tr.selected td { background: #172a45 !important; }
        /* Timeline */
        html.dark-mode .tl-header { background: #161b22; border-bottom-color: #2d333b; }
        html.dark-mode .tl-header-label { color: #8b949e; border-right-color: #2d333b; }
        html.dark-mode .tl-month { color: #8b949e; border-right-color: #2d333b; }
        html.dark-mode .tl-row { border-bottom-color: #2d333b; }
        html.dark-mode .tl-row:hover { background: #1c2333; }
        html.dark-mode .tl-task-label { border-right-color: #2d333b; }
        html.dark-mode .tl-task-name { color: #e2e8f0; }
        html.dark-mode .tl-task-user { color: #8b949e; }
        /* Add a card dark mode */
        html.dark-mode .kanban-add-btn { color: #8b949e; }
        html.dark-mode .kanban-add-btn:hover { background: rgba(255,255,255,0.05); color: #e2e8f0; }
        html.dark-mode .kanban-add-input { background: #161b22; color: #e2e8f0; border-color: #2d333b; }
        html.dark-mode .kanban-add-input:focus { border-color: #667eea; }
        html.dark-mode .kanban-add-cancel { color: #8b949e; }
        html.dark-mode .kanban-add-cancel:hover { color: #e2e8f0; background: rgba(255,255,255,0.05); }

        /* Dark mode - modals (Close Task, Change Status, Reassign) */
        html.dark-mode .modal-box { background: #161b22; box-shadow: 0 20px 60px rgba(0,0,0,0.6); border: 1px solid #2d333b; }
        html.dark-mode .modal-header.close { background: rgba(220, 53, 69, 0.25); color: #fca5a5; }
        html.dark-mode .modal-header.status { background: rgba(59, 130, 246, 0.2); color: #93c5fd; }
        html.dark-mode .modal-header.reassign { background: rgba(139, 92, 246, 0.2); color: #c4b5fd; }
        html.dark-mode .modal-body { background: #161b22; color: #e2e8f0; }
        html.dark-mode .modal-body select { background: #1c2333; color: #e2e8f0; border-color: #2d333b; }
        html.dark-mode .modal-body select:focus { border-color: #667eea; }
        html.dark-mode .modal-task-name { color: #e2e8f0; }
        html.dark-mode .modal-message { color: #a0aec0; }
        html.dark-mode .modal-footer { background: #161b22; border-top-color: #2d333b; }
        html.dark-mode .modal-btn-cancel { background: #1c2333; color: #cbd5e0; border: 1px solid #2d333b; }
        html.dark-mode .modal-btn-cancel:hover { background: #2d333b; color: #e2e8f0; }
        html.dark-mode .modal-btn-confirm.danger { background: #dc3545; }
        html.dark-mode .modal-btn-confirm.danger:hover { background: #c82333; }

        /* Dark mode - confirm overlay (custom confirmation popup) */
        html.dark-mode .confirm-box { background: #161b22; border: 1px solid #2d333b; }
        html.dark-mode .confirm-title { color: #e2e8f0; }
        html.dark-mode .confirm-msg { color: #a0aec0; }
        html.dark-mode .confirm-msg strong { color: #e2e8f0; }
        html.dark-mode .confirm-cancel { background: #1c2333; color: #cbd5e0; }
        html.dark-mode .confirm-cancel:hover { background: #2d333b; }
        html.dark-mode .confirm-hint { color: #8b949e; }
    </style>
</head>
<body>
    <?php include("./includes/modern_header.php"); ?>

    <?php if (isset($_SESSION['flash_message'])):
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
    ?>
    <div class="flash-message" id="flashMsg"><?php echo htmlspecialchars($flash['text']); ?></div>
    <script>setTimeout(function(){ var f=document.getElementById('flashMsg'); if(f) f.style.display='none'; }, 3000);</script>
    <?php endif; ?>

    <div class="container">
        <div class="page-header">
            <div class="page-header-left">
                <div class="project-title-wrap">
                    <h1 class="page-title"><?php echo htmlspecialchars($project['project_title']); ?></h1>
                    <button type="button" class="project-switcher-btn" onclick="toggleProjectSwitcher()" title="Switch project" id="projectSwitcherBtn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="17 1 21 5 17 9"/><path d="M3 5V4a2 2 0 0 1 2-2h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 19v1a2 2 0 0 1-2 2H5"/></svg>
                    </button>
                    <div class="project-switcher-dropdown" id="projectSwitcherDropdown">
                        <input type="text" class="project-switcher-search" id="projectSwitcherSearch" placeholder="Search projects..." autocomplete="off">
                        <div class="project-switcher-list" id="projectSwitcherList"></div>
                        <div class="project-switcher-empty" id="projectSwitcherEmpty" style="display:none;">No projects found</div>
                    </div>
                </div>
                <p class="page-subtitle"><?php echo $open_task_count; ?> task<?php echo $open_task_count != 1 ? 's' : ''; ?> &bull; Total time: <?php echo $total_time_formatted; ?></p>
            </div>
            <div class="page-header-right">
                <div class="page-actions">
                    <a href="edit_project.php?project_id=<?php echo $project_id; ?>" class="btn btn-outline btn-sm">Edit Project</a>
                    <a href="create_task.php?project_id=<?php echo $project_id; ?>" class="btn btn-primary btn-sm">+ New Task</a>
                </div>
                <div class="header-team" id="teamMembers">
                    <?php foreach ($assigned_users as $user): ?>
                    <span class="assigned-user" data-user-id="<?php echo $user['id']; ?>">
                        <a href="report_people.php?report_user_id=<?php echo $user['id']; ?>" title="<?php echo htmlspecialchars($user['name']); ?>"><?php echo htmlspecialchars($user['first_name']); ?></a>
                        <button class="remove-member" onclick="removeMember(<?php echo $user['id']; ?>, this)" title="Remove from project">&times;</button>
                    </span>
                    <?php endforeach; ?>
                    <span style="position:relative;display:inline-block;">
                        <button class="team-add-btn" onclick="toggleAddMember()" title="Add team member" id="addMemberBtn">+</button>
                        <div class="team-add-dropdown" id="addMemberDropdown">
                            <input type="text" class="team-add-search" id="addMemberSearch" placeholder="Search users..." oninput="filterAddMembers()">
                            <div class="team-add-list" id="addMemberList">
                                <?php foreach ($all_active_users as $u): ?>
                                <div class="team-add-option" data-user-id="<?php echo $u['id']; ?>" data-name="<?php echo htmlspecialchars($u['name'], ENT_QUOTES); ?>" onclick="addMember(<?php echo $u['id']; ?>, '<?php echo addslashes(htmlspecialchars($u['name'], ENT_QUOTES)); ?>', this)"><?php echo htmlspecialchars($u['name']); ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header light" style="flex-wrap:wrap;gap:12px;">
                <span class="card-title">Tasks</span>
                <div class="header-filters">
                    <label class="filter-check"><input type="checkbox" id="filterPeriodic" onchange="applyFilters()"> Show Periodic</label>
                    <label class="filter-check"><input type="checkbox" id="filterClosed" onchange="applyFilters()"> Show Closed</label>
                </div>
                <div class="view-tabs">
                    <a class="view-tab" data-view="list" onclick="switchView('list')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                        List
                    </a>
                    <a class="view-tab active" data-view="cards" onclick="switchView('cards')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                        Cards
                    </a>
                    <a class="view-tab" data-view="timeline" onclick="switchView('timeline')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="3" x2="3" y2="21"/><line x1="3" y1="21" x2="21" y2="21"/><rect x="6" y="8" width="5" height="3" rx="1"/><rect x="8" y="13" width="7" height="3" rx="1"/><rect x="5" y="3" width="10" height="3" rx="1"/></svg>
                        Timeline
                    </a>
                    <a class="view-tab" data-view="time" onclick="switchView('time')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        Time
                    </a>
                </div>
            </div>

            <!-- ==================== LIST VIEW ==================== -->
            <div class="view-panel" id="viewList">
            <?php if (!empty($tasks)): ?>
            <div class="scroll-table">
                <table class="data-table" id="tasksTable">
                    <thead>
                        <tr>
                            <th style="width:36px;" class="text-center"><input type="checkbox" class="th-checkbox" id="selectAll" title="Select all"></th>
                            <th><a href="?project_id=<?php echo $project_id; ?>&sort=by_title">Task</a></th>
                            <th><a href="?project_id=<?php echo $project_id; ?>&sort=by_responsible">Responsible</a></th>
                            <th><a href="?project_id=<?php echo $project_id; ?>&sort=by_created">Created By</a></th>
                            <th><a href="?project_id=<?php echo $project_id; ?>&sort=by_status">Status</a></th>
                            <th><a href="?project_id=<?php echo $project_id; ?>&sort=by_hours">Time</a></th>
                            <th><a href="?project_id=<?php echo $project_id; ?>&sort=by_completion">%</a></th>
                            <th><a href="?project_id=<?php echo $project_id; ?>&sort=by_planed_date">Deadline</a></th>
                            <th style="width:70px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasks as $task): ?>
                        <tr class="clickable-row <?php echo $task['is_overdue'] && !$task['is_closed'] ? 'overdue' : ''; ?> <?php echo $task['is_closed'] ? 'closed' : ''; ?>"
                            data-task-id="<?php echo $task['task_id']; ?>"
                            data-task-title="<?php echo htmlspecialchars($task['task_title'], ENT_QUOTES); ?>"
                            data-task-status="<?php echo $task['status_id']; ?>"
                            data-task-completion="<?php echo intval($task['completion']); ?>"
                            data-task-closed="<?php echo $task['is_closed']; ?>"
                            data-periodic="<?php echo $task['is_periodic'] ? '1' : '0'; ?>"
                            data-closed="<?php echo $task['is_closed'] ? '1' : '0'; ?>">
                            <td class="text-center" onclick="event.stopPropagation()">
                                <input type="checkbox" class="row-checkbox" value="<?php echo $task['task_id']; ?>">
                            </td>
                            <td class="task-title-cell">
                                <a href="edit_task.php?task_id=<?php echo $task['task_id']; ?>" onclick="event.stopPropagation()">
                                    <?php echo htmlspecialchars($task['task_title']); ?>
                                </a>
                            </td>
                            <td>
                                <?php if ($task['responsible_name']): ?>
                                <a href="report_people.php?report_user_id=<?php echo $task['responsible_id']; ?>" class="user-link" onclick="event.stopPropagation()">
                                    <?php echo htmlspecialchars($task['responsible_name']); ?>
                                </a>
                                <?php else: ?>-<?php endif; ?>
                            </td>
                            <td>
                                <?php if ($task['created_name']): ?>
                                <a href="report_people.php?report_user_id=<?php echo $task['created_id']; ?>" class="user-link" onclick="event.stopPropagation()">
                                    <?php echo htmlspecialchars($task['created_name']); ?>
                                </a>
                                <?php else: ?>-<?php endif; ?>
                            </td>
                            <td>
                                <?php $status_class = isset($status_classes[$task['status_id']]) ? 'status-' . $status_classes[$task['status_id']] : 'status-new'; ?>
                                <span class="status-badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($task['status_desc']); ?></span>
                            </td>
                            <td><?php echo $task['actual_hours']; ?></td>
                            <td>
                                <div class="completion-bar"><div class="completion-fill" style="width:<?php echo min(100, $task['completion']); ?>%"></div></div>
                                <?php echo $task['completion']; ?>%
                            </td>
                            <td><?php echo $task['planed_date'] ?: '-'; ?></td>
                            <td onclick="event.stopPropagation()">
                                <div class="task-actions">
                                    <?php if (!$task['is_closed']): ?>
                                    <a href="#" onclick="confirmClose(<?php echo $task['task_id']; ?>, '<?php echo addslashes($task['task_title']); ?>'); return false;" class="action-link action-close" title="Close task">Close</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="summary-row">
                            <td></td>
                            <td colspan="4"><strong>Total</strong></td>
                            <td><strong><?php echo $total_time_formatted; ?></strong></td>
                            <td colspan="3"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <p>No tasks found for this project</p>
                <a href="create_task.php?project_id=<?php echo $project_id; ?>" class="btn btn-primary">Create First Task</a>
            </div>
            <?php endif; ?>
            </div><!-- /viewList -->

            <!-- ==================== CARDS VIEW ==================== -->
            <div class="view-panel active" id="viewCards">
            <?php
            // Group tasks into Kanban columns
            $kanban_columns = array(
                'new' => array('title' => 'New', 'statuses' => array(7, 5, 6), 'primary_status' => 7, 'color_class' => 'kanban-col-new', 'tasks' => array()),
                'progress' => array('title' => 'In Progress', 'statuses' => array(1, 11), 'primary_status' => 1, 'color_class' => 'kanban-col-progress', 'tasks' => array()),
                'hold' => array('title' => 'On Hold / Waiting', 'statuses' => array(2, 8, 9), 'primary_status' => 2, 'color_class' => 'kanban-col-hold', 'tasks' => array()),
                'review' => array('title' => 'Review / Bugs', 'statuses' => array(10, 3, 12, 13, 14), 'primary_status' => 10, 'color_class' => 'kanban-col-review', 'tasks' => array()),
                'done' => array('title' => 'Done', 'statuses' => array(4), 'primary_status' => 4, 'color_class' => 'kanban-col-done', 'tasks' => array()),
                'closed' => array('title' => 'Closed', 'statuses' => array(), 'color_class' => 'kanban-col-closed', 'tasks' => array()),
            );

            foreach ($tasks as $task) {
                if ($task['is_closed']) {
                    $kanban_columns['closed']['tasks'][] = $task;
                    continue;
                }
                $placed = false;
                foreach ($kanban_columns as $key => &$col) {
                    if (in_array($task['status_id'], $col['statuses'])) {
                        $col['tasks'][] = $task;
                        $placed = true;
                        break;
                    }
                }
                unset($col);
                if (!$placed) {
                    $kanban_columns['new']['tasks'][] = $task;
                }
            }

            // Keep all columns visible (even when empty)
            $keep_always = array('new', 'progress', 'hold', 'review', 'done', 'closed');
            foreach ($kanban_columns as $key => $col) {
                if (empty($col['tasks']) && !in_array($key, $keep_always)) {
                    unset($kanban_columns[$key]);
                }
            }
            ?>
            <div class="kanban-board">
                <?php foreach ($kanban_columns as $col_key => $col): ?>
                <div class="kanban-column <?php echo $col['color_class']; ?>" data-col-key="<?php echo $col_key; ?>" data-status-id="<?php echo isset($col['primary_status']) ? $col['primary_status'] : ''; ?>">
                    <div class="kanban-column-header">
                        <span class="kanban-column-title"><?php echo htmlspecialchars($col['title']); ?></span>
                        <span class="kanban-column-count"><?php echo count($col['tasks']); ?></span>
                    </div>
                    <div class="kanban-cards" data-col-key="<?php echo $col_key; ?>">
                        <?php if (empty($col['tasks'])): ?>
                        <div class="kanban-empty">No tasks</div>
                        <?php else: ?>
                        <?php foreach ($col['tasks'] as $task):
                            $initials = '';
                            if ($task['responsible_name']) {
                                $parts = explode(' ', trim($task['responsible_name']));
                                $initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
                            }
                            $card_class = 'kanban-card';
                            if ($task['is_overdue'] && !$task['is_closed']) $card_class .= ' overdue';
                            if ($task['is_closed']) $card_class .= ' closed-card';
                            $status_class = isset($status_classes[$task['status_id']]) ? 'status-' . $status_classes[$task['status_id']] : 'status-new';
                        ?>
                        <div class="<?php echo $card_class; ?>" <?php echo $task['is_closed'] ? '' : 'draggable="true"'; ?> data-task-id="<?php echo $task['task_id']; ?>" data-task-title="<?php echo htmlspecialchars($task['task_title'], ENT_QUOTES); ?>" data-status-id="<?php echo $task['status_id']; ?>" data-periodic="<?php echo $task['is_periodic'] ? '1' : '0'; ?>" data-closed="<?php echo $task['is_closed'] ? '1' : '0'; ?>">
                            <div class="kanban-card-title">
                                <a href="edit_task.php?task_id=<?php echo $task['task_id']; ?>" onclick="event.stopPropagation()"><?php echo htmlspecialchars($task['task_title']); ?></a>
                            </div>
                            <div class="kanban-card-meta">
                                <?php if ($task['actual_hours'] && $task['actual_hours'] !== '0'): ?>
                                <span class="kanban-card-meta-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    <?php echo $task['actual_hours']; ?>
                                </span>
                                <?php endif; ?>
                                <?php if ($task['completion'] > 0): ?>
                                <span class="kanban-card-meta-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                                    <?php echo $task['completion']; ?>%
                                </span>
                                <?php endif; ?>
                                <?php if ($task['planed_date']): ?>
                                <?php
                                    $is_done = ($task['status_id'] == 4 || $task['is_closed']);
                                    $deadline_ts = strtotime($task['planed_date_raw']);
                                    $now_ts = time();
                                    $days_left = ($deadline_ts - $now_ts) / 86400;
                                    if ($is_done) {
                                        $dl_class = 'deadline-met';
                                    } elseif ($task['is_overdue']) {
                                        $dl_class = 'deadline-overdue';
                                    } elseif ($days_left <= 3) {
                                        $dl_class = 'deadline-upcoming';
                                    } else {
                                        $dl_class = 'deadline-future';
                                    }
                                ?>
                                <span class="deadline-badge <?php echo $dl_class; ?>">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                    <?php echo $task['planed_date']; ?>
                                    <?php if ($is_done): ?>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                    <?php endif; ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="kanban-card-footer">
                                <?php if ($initials): ?>
                                <div class="kanban-card-avatar" title="<?php echo htmlspecialchars($task['responsible_name']); ?>"><?php echo $initials; ?></div>
                                <?php else: ?>
                                <div></div>
                                <?php endif; ?>
                                <span class="kanban-card-badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($task['status_desc']); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="kanban-add-area" data-col-key="<?php echo $col_key; ?>" data-status-id="<?php echo isset($col['primary_status']) ? $col['primary_status'] : 7; ?>">
                        <button class="kanban-add-btn" onclick="kanbanShowAddForm(this)">+ Add Task</button>
                        <div class="kanban-add-form" style="display:none">
                            <textarea class="kanban-add-input" placeholder="Enter a title for this card..." rows="2"></textarea>
                            <div class="kanban-add-actions">
                                <button class="kanban-add-submit" onclick="kanbanSubmitCard(this)">Add Task</button>
                                <button class="kanban-add-cancel" onclick="kanbanCancelAdd(this)">&times;</button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            </div><!-- /viewCards -->

            <!-- ==================== TIMELINE VIEW ==================== -->
            <div class="view-panel" id="viewTimeline">
                <div class="timeline-container">
                    <div class="timeline-toolbar">
                        <div class="timeline-legend">
                            <span class="timeline-legend-item"><span style="width:7px;height:7px;background:#48bb78;transform:rotate(45deg);display:inline-block;"></span> Created</span>
                            <span class="timeline-legend-item"><span style="width:16px;height:6px;background:#4299e1;border-radius:3px;display:inline-block;"></span> Messages</span>
                            <span class="timeline-legend-item"><span style="width:16px;height:6px;background:#ed8936;border-radius:3px;display:inline-block;"></span> Time logged</span>
                            <span class="timeline-legend-item"><span style="width:16px;height:6px;background:linear-gradient(90deg,#ed8936,#4299e1);border-radius:3px;display:inline-block;"></span> Both</span>
                        </div>
                    </div>
                    <div id="timelineChart"></div>
                </div>
            </div><!-- /viewTimeline -->

            <!-- ==================== TIME VIEW ==================== -->
            <div class="view-panel" id="viewTime">
                <div class="time-view-container">
                    <div class="time-period-bar">
                        <span style="font-size:0.78rem;color:#718096;font-weight:500;">Period:</span>
                        <button class="tp-btn" data-period="1m" onclick="setTimePeriod('1m')">Last Month</button>
                        <button class="tp-btn" data-period="3m" onclick="setTimePeriod('3m')">3 Months</button>
                        <button class="tp-btn" data-period="6m" onclick="setTimePeriod('6m')">6 Months</button>
                        <button class="tp-btn active" data-period="12m" onclick="setTimePeriod('12m')">12 Months</button>
                        <button class="tp-btn" data-period="all" onclick="setTimePeriod('all')">All Time</button>
                    </div>
                    <div class="time-view-grid">
                        <div class="time-chart-section">
                            <h3 class="time-section-title">Time by Person</h3>
                            <div class="time-chart-wrap">
                                <canvas id="pieByUser" width="320" height="320"></canvas>
                            </div>
                            <div id="pieLegendUser" class="pie-legend"></div>
                        </div>
                        <div class="time-table-section">
                            <h3 class="time-section-title">Top Tasks by Time</h3>
                            <div id="timeTasksList" class="time-tasks-list"></div>
                        </div>
                    </div>
                </div>
            </div><!-- /viewTime -->
        </div>
    </div>

    <!-- Bulk Action Bar -->
    <div class="bulk-bar" id="bulkBar">
        <span><span class="bulk-count" id="bulkCount">0</span> selected</span>
        <button class="bulk-btn bulk-btn-close" onclick="bulkClose()">Close Tasks</button>
        <button class="bulk-btn bulk-btn-status" onclick="bulkChangeStatus()">Change Status</button>
        <button class="bulk-btn bulk-btn-reassign" onclick="bulkReassign()">Reassign</button>
        <button class="bulk-btn bulk-btn-cancel" onclick="clearSelection()">Cancel</button>
    </div>

    <!-- Close Confirmation Modal -->
    <div id="closeModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header close"><h3 id="closeModalTitle">Close Task</h3></div>
            <div class="modal-body">
                <div class="modal-task-name" id="closeModalTask"></div>
                <div class="modal-message" id="closeModalMsg">This task will be marked as closed.</div>
            </div>
            <div class="modal-footer">
                <button class="modal-btn modal-btn-cancel" onclick="hideModal('closeModal')">Cancel</button>
                <button class="modal-btn modal-btn-confirm danger" id="closeModalBtn" onclick="executeClose()">Close Task</button>
            </div>
        </div>
    </div>

    <!-- Status Change Modal -->
    <div id="statusModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header status"><h3 id="statusModalTitle">Change Status</h3></div>
            <div class="modal-body">
                <div class="modal-message">Select new status for the selected tasks:</div>
                <select id="statusSelect">
                    <option value="">-- Select Status --</option>
                    <?php foreach ($task_statuses as $ts): ?>
                    <option value="<?php echo $ts['id']; ?>"><?php echo htmlspecialchars($ts['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer">
                <button class="modal-btn modal-btn-cancel" onclick="hideModal('statusModal')">Cancel</button>
                <button class="modal-btn modal-btn-confirm" onclick="executeStatusChange()">Update Status</button>
            </div>
        </div>
    </div>

    <!-- Reassign Modal -->
    <div id="reassignModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header reassign"><h3 id="reassignModalTitle">Reassign Tasks</h3></div>
            <div class="modal-body">
                <div class="modal-message">Select the new responsible person:</div>
                <select id="reassignSelect">
                    <option value="">-- Select User --</option>
                    <?php foreach ($all_users as $u): ?>
                    <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer">
                <button class="modal-btn modal-btn-cancel" onclick="hideModal('reassignModal')">Cancel</button>
                <button class="modal-btn modal-btn-confirm" onclick="executeReassign()">Reassign</button>
            </div>
        </div>
    </div>

    <script>
    var projectId = <?php echo (int) $project_id; ?>;
    var currentSort = '<?php echo addslashes($sort); ?>';
    var baseUrl = 'view_project_tasks.php?project_id=' + projectId + (currentSort ? '&sort=' + currentSort : '');

    // ==================== Project Switcher ====================
    var projectSwitcherDebounce = null;
    var projectSwitcherHighlightedIndex = -1;

    function getProjectSwitcherOptions() {
        return Array.prototype.slice.call(document.querySelectorAll('#projectSwitcherList .project-switcher-option'));
    }

    function setProjectSwitcherHighlight(index) {
        var options = getProjectSwitcherOptions();
        projectSwitcherHighlightedIndex = index;
        options.forEach(function(opt, i) {
            opt.classList.toggle('highlighted', i === index);
        });
        if (index >= 0 && options[index]) {
            options[index].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    }

    function selectProjectSwitcherHighlighted() {
        var options = getProjectSwitcherOptions();
        if (projectSwitcherHighlightedIndex >= 0 && options[projectSwitcherHighlightedIndex]) {
            var id = parseInt(options[projectSwitcherHighlightedIndex].dataset.id, 10);
            if (id !== projectId) {
                var hash = location.hash || '#view-cards';
                window.location = 'view_project_tasks.php?project_id=' + id + (currentSort ? '&sort=' + currentSort : '') + hash;
            }
        }
    }

    function toggleProjectSwitcher() {
        var dd = document.getElementById('projectSwitcherDropdown');
        if (dd.classList.toggle('show')) {
            document.getElementById('projectSwitcherSearch').value = '';
            loadProjectSwitcher('');
            document.getElementById('projectSwitcherSearch').focus();
        }
    }
    function loadProjectSwitcher(query) {
        var list = document.getElementById('projectSwitcherList');
        var empty = document.getElementById('projectSwitcherEmpty');
        list.innerHTML = '<div class="project-switcher-empty">Loading...</div>';
        empty.style.display = 'none';
        projectSwitcherHighlightedIndex = -1;
        var formData = new FormData();
        formData.append('action', 'search_projects');
        formData.append('q', query);
        formData.append('limit', 50);
        fetch('ajax_responder.php', { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                list.innerHTML = '';
                if (data.success && data.projects && data.projects.length > 0) {
                    data.projects.forEach(function(p, idx) {
                        var opt = document.createElement('div');
                        opt.className = 'project-switcher-option' + (p.id === projectId ? ' current' : '');
                        opt.textContent = p.title;
                        opt.dataset.id = p.id;
                        opt.onclick = function() {
                            var id = parseInt(this.dataset.id, 10);
                            if (id !== projectId) {
                                var hash = location.hash || '#view-cards';
                                window.location = 'view_project_tasks.php?project_id=' + id + (currentSort ? '&sort=' + currentSort : '') + hash;
                            }
                        };
                        opt.addEventListener('mouseenter', function() {
                            setProjectSwitcherHighlight(getProjectSwitcherOptions().indexOf(this));
                        });
                        list.appendChild(opt);
                    });
                    setProjectSwitcherHighlight(0);
                } else {
                    empty.style.display = 'block';
                }
            })
            .catch(function() {
                list.innerHTML = '';
                empty.textContent = 'Error loading projects';
                empty.style.display = 'block';
            });
    }
    document.getElementById('projectSwitcherSearch').addEventListener('input', function() {
        clearTimeout(projectSwitcherDebounce);
        var q = this.value.trim();
        projectSwitcherDebounce = setTimeout(function() { loadProjectSwitcher(q); }, 200);
    });
    document.addEventListener('click', function(e) {
        var dd = document.getElementById('projectSwitcherDropdown');
        var btn = document.getElementById('projectSwitcherBtn');
        if (dd && dd.classList.contains('show') && !dd.contains(e.target) && !btn.contains(e.target)) {
            dd.classList.remove('show');
        }
    });
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            var dd = document.getElementById('projectSwitcherDropdown');
            if (dd && !dd.classList.contains('show')) {
                toggleProjectSwitcher();
            } else if (dd && dd.classList.contains('show')) {
                document.getElementById('projectSwitcherSearch').focus();
            }
        }
    });
    document.getElementById('projectSwitcherSearch').addEventListener('keydown', function(e) {
        var dd = document.getElementById('projectSwitcherDropdown');
        if (!dd || !dd.classList.contains('show')) return;
        if (e.key === 'Escape') {
            dd.classList.remove('show');
            return;
        }
        var options = getProjectSwitcherOptions();
        if (options.length === 0) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setProjectSwitcherHighlight((projectSwitcherHighlightedIndex + 1) % options.length);
            return;
        }
        if (e.key === 'ArrowUp') {
            e.preventDefault();
            setProjectSwitcherHighlight(projectSwitcherHighlightedIndex <= 0 ? options.length - 1 : projectSwitcherHighlightedIndex - 1);
            return;
        }
        if (e.key === 'Enter') {
            e.preventDefault();
            selectProjectSwitcherHighlighted();
        }
    });

    // ==================== Timeline Data (must be before restore) ====================
    var timelineRendered = false;
    var timelineData = <?php echo $timeline_json; ?>;
    var timelineMinDate = '<?php echo $timeline_min_date; ?>';
    var timelineMaxDate = '<?php echo $timeline_max_date; ?>';
    var serverToday = '<?php echo date("Y-m-d"); ?>';
    var statusClassMap = <?php echo json_encode($status_classes); ?>;
    var timeRaw = <?php echo $time_raw_json; ?>;
    var timeViewRendered = false;
    var currentTimePeriod = '12m';
    var pieColors = ['#667eea','#ed8936','#48bb78','#e53e3e','#9f7aea','#38b2ac','#ed64a6','#ecc94b','#4299e1','#fc8181','#68d391','#f6ad55','#b794f4','#76e4f7','#f687b3'];

    // ==================== View Tabs ====================
    var validViews = { list: 1, cards: 1, timeline: 1, time: 1 };

    function switchView(view, skipHash) {
        document.querySelectorAll('.view-tab').forEach(function(t) {
            t.classList.toggle('active', t.dataset.view === view);
        });
        document.querySelectorAll('.view-panel').forEach(function(p) {
            p.classList.toggle('active', p.id === 'view' + view.charAt(0).toUpperCase() + view.slice(1));
        });
        try { localStorage.setItem('projectTasksView_' + projectId, view); } catch(e) {}
        if (!skipHash) {
            history.replaceState(null, '', location.pathname + location.search + '#view-' + view);
        }
        if (view === 'timeline') {
            if (!timelineRendered) renderTimeline();
            applyFilters();
        }
        if (view === 'time' && !timeViewRendered) {
            renderTimeView();
        }
    }

    // Restore view from URL hash or localStorage (default is cards)
    (function() {
        var view = null;
        // Check hash first
        var hash = location.hash.replace('#', '');
        if (hash.indexOf('view-') === 0) {
            var v = hash.substring(5);
            if (validViews[v]) view = v;
        }
        // Fall back to localStorage
        if (!view) {
            try {
                var saved = localStorage.getItem('projectTasksView_' + projectId);
                if (saved && validViews[saved]) view = saved;
            } catch(e) {}
        }
        if (view && view !== 'cards') switchView(view);
        else if (view === 'cards') {
            // Update hash even for default view
            history.replaceState(null, '', location.pathname + location.search + '#view-cards');
        }
    })();

    // Handle browser back/forward with hash changes
    window.addEventListener('hashchange', function() {
        var hash = location.hash.replace('#', '');
        if (hash.indexOf('view-') === 0) {
            var v = hash.substring(5);
            if (validViews[v]) switchView(v, true);
        }
    });

    // ==================== Filters ====================
    function applyFilters() {
        var showPeriodic = document.getElementById('filterPeriodic').checked;
        var showClosed = document.getElementById('filterClosed').checked;

        // Save to localStorage
        try {
            localStorage.setItem('filterPeriodic_' + projectId, showPeriodic ? '1' : '0');
            localStorage.setItem('filterClosed_' + projectId, showClosed ? '1' : '0');
        } catch(e) {}

        // --- List view: show/hide table rows ---
        document.querySelectorAll('#tasksTable tbody tr.clickable-row').forEach(function(row) {
            var isPeriodic = row.dataset.periodic === '1';
            var isClosed = row.dataset.closed === '1';
            var hide = false;
            if (isPeriodic && !showPeriodic) hide = true;
            if (isClosed && !showClosed) hide = true;
            row.style.display = hide ? 'none' : '';
        });

        // --- Cards view: show/hide kanban cards ---
        document.querySelectorAll('.kanban-card').forEach(function(card) {
            var isPeriodic = card.dataset.periodic === '1';
            var isClosed = card.dataset.closed === '1';
            var hide = false;
            if (isPeriodic && !showPeriodic) hide = true;
            if (isClosed && !showClosed) hide = true;
            card.style.display = hide ? 'none' : '';
        });

        // --- Show/hide the Closed column ---
        var closedCol = document.querySelector('.kanban-column[data-col-key="closed"]');
        if (closedCol) {
            closedCol.classList.toggle('col-hidden', !showClosed);
        }

        // Update column counts (only count visible cards)
        document.querySelectorAll('.kanban-column').forEach(function(col) {
            var visibleCards = col.querySelectorAll('.kanban-card:not([style*="display: none"])');
            var countEl = col.querySelector('.kanban-column-count');
            if (countEl) countEl.textContent = visibleCards.length;
        });

        // --- Timeline view: show/hide rows ---
        document.querySelectorAll('#timelineChart .tl-row').forEach(function(row) {
            var isPeriodic = row.dataset.periodic === '1';
            var isClosed = row.dataset.closed === '1';
            var hide = false;
            if (isPeriodic && !showPeriodic) hide = true;
            if (isClosed && !showClosed) hide = true;
            row.style.display = hide ? 'none' : '';
        });
    }

    // Restore filter state from localStorage
    (function() {
        try {
            var sp = localStorage.getItem('filterPeriodic_' + projectId);
            var sc = localStorage.getItem('filterClosed_' + projectId);
            if (sp === '1') document.getElementById('filterPeriodic').checked = true;
            if (sc === '1') document.getElementById('filterClosed').checked = true;
        } catch(e) {}
        applyFilters();
    })();

    // ==================== Kanban Drag & Drop ====================
    (function() {
        var draggedCard = null;
        var sourceColumn = null;
        var dropIndicator = null;

        // Create reusable drop indicator
        dropIndicator = document.createElement('div');
        dropIndicator.className = 'kanban-card-drop-indicator';

        // Card click to navigate (since we removed onclick from the div)
        document.querySelectorAll('.kanban-card').forEach(function(card) {
            card.addEventListener('click', function(e) {
                // Only navigate if we didn't just finish dragging and the click target isn't a link
                if (e.target.tagName === 'A' || card.classList.contains('dragging')) return;
                var taskId = card.dataset.taskId;
                if (taskId) {
                    sessionStorage.setItem('kanban_focused_task', taskId);
                    window.location = 'edit_task.php?task_id=' + taskId;
                }
            });
        });

        // Drag start
        document.querySelectorAll('.kanban-card[draggable="true"]').forEach(function(card) {
            card.addEventListener('dragstart', function(e) {
                draggedCard = card;
                sourceColumn = card.closest('.kanban-column');
                card.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', card.dataset.taskId);
                // Slight delay so the browser snapshot doesn't include the dragging class
                setTimeout(function() { card.style.display = ''; }, 0);
            });

            card.addEventListener('dragend', function() {
                card.classList.remove('dragging');
                draggedCard = null;
                sourceColumn = null;
                // Clean up all drag-over states
                document.querySelectorAll('.kanban-column.drag-over').forEach(function(col) {
                    col.classList.remove('drag-over');
                });
                if (dropIndicator.parentNode) dropIndicator.parentNode.removeChild(dropIndicator);
            });
        });

        // Column drag events
        document.querySelectorAll('.kanban-cards').forEach(function(cardsContainer) {
            cardsContainer.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                var column = cardsContainer.closest('.kanban-column');
                column.classList.add('drag-over');

                // Position drop indicator
                var afterCard = getCardAfterDrag(cardsContainer, e.clientY);
                if (afterCard) {
                    cardsContainer.insertBefore(dropIndicator, afterCard);
                } else {
                    cardsContainer.appendChild(dropIndicator);
                }
            });

            cardsContainer.addEventListener('dragleave', function(e) {
                // Only remove if we actually left the container
                var column = cardsContainer.closest('.kanban-column');
                if (!column.contains(e.relatedTarget)) {
                    column.classList.remove('drag-over');
                    if (dropIndicator.parentNode === cardsContainer) {
                        cardsContainer.removeChild(dropIndicator);
                    }
                }
            });

            cardsContainer.addEventListener('drop', function(e) {
                e.preventDefault();
                var column = cardsContainer.closest('.kanban-column');
                column.classList.remove('drag-over');

                if (!draggedCard) return;

                var targetStatusId = column.dataset.statusId;
                var currentStatusId = draggedCard.dataset.statusId;
                var taskId = draggedCard.dataset.taskId;

                // Move card in DOM
                if (dropIndicator.parentNode === cardsContainer) {
                    cardsContainer.insertBefore(draggedCard, dropIndicator);
                } else {
                    cardsContainer.appendChild(draggedCard);
                }
                if (dropIndicator.parentNode) dropIndicator.parentNode.removeChild(dropIndicator);

                // Remove "No tasks" placeholder if present
                var emptyMsg = cardsContainer.querySelector('.kanban-empty');
                if (emptyMsg) emptyMsg.remove();

                // Update column counts
                updateColumnCounts();

                // If status actually changed, call AJAX
                if (targetStatusId && currentStatusId !== targetStatusId) {
                    draggedCard.dataset.statusId = targetStatusId;
                    moveTaskStatus(taskId, targetStatusId, draggedCard, currentStatusId, sourceColumn);
                }
            });
        });

        function getCardAfterDrag(container, y) {
            var cards = Array.prototype.slice.call(container.querySelectorAll('.kanban-card:not(.dragging)'));
            var result = null;
            var closestOffset = Number.NEGATIVE_INFINITY;
            cards.forEach(function(card) {
                var box = card.getBoundingClientRect();
                var offset = y - box.top - box.height / 2;
                if (offset < 0 && offset > closestOffset) {
                    closestOffset = offset;
                    result = card;
                }
            });
            return result;
        }

        function updateColumnCounts() {
            document.querySelectorAll('.kanban-column').forEach(function(col) {
                var count = col.querySelectorAll('.kanban-card').length;
                var countEl = col.querySelector('.kanban-column-count');
                if (countEl) countEl.textContent = count;
                // Add empty placeholder if no cards
                var cardsContainer = col.querySelector('.kanban-cards');
                var emptyEl = cardsContainer.querySelector('.kanban-empty');
                if (count === 0 && !emptyEl) {
                    var empty = document.createElement('div');
                    empty.className = 'kanban-empty';
                    empty.textContent = 'No tasks';
                    cardsContainer.appendChild(empty);
                }
            });
        }
        window.updateColumnCounts = updateColumnCounts;

        function moveTaskStatus(taskId, newStatusId, card, oldStatusId, oldColumn) {
            var formData = new FormData();
            formData.append('action', 'kanban_move_task');
            formData.append('task_id', taskId);
            formData.append('new_status_id', newStatusId);

            // Brief visual feedback on card
            card.style.transition = 'border-left-color 0.3s';
            card.style.borderLeftColor = '#667eea';

            fetch('ajax_responder.php', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    // Update the status badge on the card
                    var badge = card.querySelector('.kanban-card-badge');
                    if (badge && data.new_status_name) {
                        badge.textContent = data.new_status_name;
                    }
                    card.style.borderLeftColor = '#48bb78';
                    setTimeout(function() { card.style.borderLeftColor = ''; }, 1500);
                    showKanbanToast('Moved to "' + (data.new_status_name || 'new status') + '"');
                } else {
                    // Revert: move card back
                    revertCard(card, oldColumn, oldStatusId);
                    showKanbanToast(data.error || 'Failed to update status', true);
                }
            })
            .catch(function(err) {
                revertCard(card, oldColumn, oldStatusId);
                showKanbanToast('Network error - status not changed', true);
            });
        }

        function revertCard(card, oldColumn, oldStatusId) {
            if (oldColumn) {
                var oldCards = oldColumn.querySelector('.kanban-cards');
                var emptyMsg = oldCards.querySelector('.kanban-empty');
                if (emptyMsg) emptyMsg.remove();
                oldCards.appendChild(card);
                card.dataset.statusId = oldStatusId;
                updateColumnCounts();
            }
        }

        function showKanbanToast(message, isError) {
            var existing = document.getElementById('kanbanToast');
            if (existing) existing.remove();
            var toast = document.createElement('div');
            toast.id = 'kanbanToast';
            toast.className = 'kanban-toast' + (isError ? ' error' : '');
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(function() {
                toast.style.opacity = '0';
                setTimeout(function() { toast.remove(); }, 300);
            }, 2500);
        }
    })();

    // ==================== Row Click ====================
    document.querySelectorAll('tr.clickable-row').forEach(function(row) {
        row.addEventListener('click', function() {
            window.location = 'edit_task.php?task_id=' + this.dataset.taskId;
        });
    });

    // ==================== Close single task ====================
    var pendingCloseId = null;
    var pendingCloseIds = null;

    function confirmClose(taskId, taskTitle) {
        pendingCloseId = taskId;
        pendingCloseIds = null;
        document.getElementById('closeModalTitle').textContent = 'Close Task';
        document.getElementById('closeModalTask').textContent = taskTitle;
        document.getElementById('closeModalMsg').textContent = 'This task will be marked as closed.';
        document.getElementById('closeModalBtn').textContent = 'Close Task';
        showModal('closeModal');
    }

    function confirmBulkClose(ids) {
        pendingCloseId = null;
        pendingCloseIds = ids;
        document.getElementById('closeModalTitle').textContent = 'Close ' + ids.length + ' Task(s)';
        document.getElementById('closeModalTask').textContent = '';
        document.getElementById('closeModalMsg').textContent = 'Are you sure you want to close ' + ids.length + ' selected task(s)?';
        document.getElementById('closeModalBtn').textContent = 'Close ' + ids.length + ' Task(s)';
        showModal('closeModal');
    }

    function executeClose() {
        hideModal('closeModal');
        var ids = [];
        if (pendingCloseId) {
            ids = [pendingCloseId];
        } else if (pendingCloseIds) {
            ids = pendingCloseIds;
        }
        if (!ids.length) return;

        // If we're in Cards view, do AJAX close with animation
        var cardsPanel = document.getElementById('viewCards');
        if (cardsPanel && cardsPanel.classList.contains('active')) {
            fetch('ajax_responder.php?action=close_task&task_ids=' + ids.join(','))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        ids.forEach(function(tid) {
                            var card = document.querySelector('.kanban-card[data-task-id="' + tid + '"]');
                            if (card) removeCardWithAnimation(card);
                        });
                        showKanbanToast(data.count + ' task' + (data.count > 1 ? 's' : '') + ' closed');
                    } else {
                        showKanbanToast(data.error || 'Failed to close', true);
                    }
                })
                .catch(function() { showKanbanToast('Network error', true); });
        } else {
            // List view: fall back to page redirect
            if (pendingCloseId) {
                window.location = baseUrl + '&action=close&task_id=' + pendingCloseId;
            } else if (pendingCloseIds) {
                window.location = baseUrl + '&action=close&task_ids=' + pendingCloseIds.join(',');
            }
        }
    }

    function removeCardWithAnimation(card) {
        // Find next card to focus before removing
        var nextCard = getNextFocusableCard(card);
        // Record current height for smooth collapse
        card.style.maxHeight = card.offsetHeight + 'px';
        // Force reflow
        card.offsetHeight;
        card.classList.add('card-removing');
        setTimeout(function() {
            card.remove();
            updateColumnCounts();
            // Focus the next card if the removed card was focused
            if (typeof setKbFocus === 'function') setKbFocus(nextCard);
        }, 380);
    }

    function getNextFocusableCard(card) {
        // Try next sibling, then previous sibling, then first card in next column
        var next = card.nextElementSibling;
        while (next && !next.classList.contains('kanban-card')) next = next.nextElementSibling;
        if (next) return next;
        var prev = card.previousElementSibling;
        while (prev && !prev.classList.contains('kanban-card')) prev = prev.previousElementSibling;
        if (prev) return prev;
        // Try next column
        var col = card.closest('.kanban-column');
        var cols = Array.prototype.slice.call(document.querySelectorAll('.kanban-board .kanban-column'));
        var ci = cols.indexOf(col);
        for (var i = ci + 1; i < cols.length; i++) {
            var c = cols[i].querySelector('.kanban-card');
            if (c) return c;
        }
        for (var j = ci - 1; j >= 0; j--) {
            var c2 = cols[j].querySelector('.kanban-card');
            if (c2) return c2;
        }
        return null;
    }

    // ==================== Modals ====================
    function showModal(id) {
        document.getElementById(id).classList.add('active');
    }
    function hideModal(id) {
        document.getElementById(id).classList.remove('active');
    }
    // Close on overlay click
    document.querySelectorAll('.modal-overlay').forEach(function(m) {
        m.addEventListener('click', function(e) { if (e.target === this) hideModal(this.id); });
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.active').forEach(function(m) { hideModal(m.id); });
        }
        if (e.key === 'Enter') {
            var closeModal = document.getElementById('closeModal');
            if (closeModal && closeModal.classList.contains('active')) {
                e.preventDefault();
                e.stopImmediatePropagation();
                executeClose();
                return;
            }
        }
    });

    // ==================== Checkbox Selection ====================
    var selectAllCb = document.getElementById('selectAll');
    var rowCheckboxes = document.querySelectorAll('.row-checkbox');
    var bulkBar = document.getElementById('bulkBar');

    function getSelectedIds() {
        var ids = [];
        rowCheckboxes.forEach(function(cb) { if (cb.checked) ids.push(cb.value); });
        return ids;
    }

    function updateBulkBar() {
        var ids = getSelectedIds();
        var count = ids.length;
        document.getElementById('bulkCount').textContent = count;
        bulkBar.classList.toggle('show', count > 0);
        // Update row highlight
        rowCheckboxes.forEach(function(cb) {
            cb.closest('tr').classList.toggle('selected', cb.checked);
        });
        // Update selectAll state
        if (selectAllCb) {
            var total = rowCheckboxes.length;
            selectAllCb.checked = count > 0 && count === total;
            selectAllCb.indeterminate = count > 0 && count < total;
        }
    }

    if (selectAllCb) {
        selectAllCb.addEventListener('change', function() {
            rowCheckboxes.forEach(function(cb) { cb.checked = selectAllCb.checked; });
            updateBulkBar();
        });
    }
    rowCheckboxes.forEach(function(cb) {
        cb.addEventListener('change', updateBulkBar);
    });

    function clearSelection() {
        rowCheckboxes.forEach(function(cb) { cb.checked = false; });
        if (selectAllCb) selectAllCb.checked = false;
        updateBulkBar();
    }

    // ==================== Bulk Actions ====================
    function bulkClose() {
        var ids = getSelectedIds();
        if (ids.length === 0) return;
        confirmBulkClose(ids);
    }

    function bulkChangeStatus() {
        var ids = getSelectedIds();
        if (ids.length === 0) return;
        document.getElementById('statusModalTitle').textContent = 'Change Status (' + ids.length + ')';
        document.getElementById('statusSelect').value = '';
        showModal('statusModal');
    }

    function executeStatusChange() {
        var ids = getSelectedIds();
        var statusId = document.getElementById('statusSelect').value;
        if (!statusId || ids.length === 0) return;
        window.location = baseUrl + '&action=change_status&new_status=' + statusId + '&task_ids=' + ids.join(',');
    }

    function bulkReassign() {
        var ids = getSelectedIds();
        if (ids.length === 0) return;
        document.getElementById('reassignModalTitle').textContent = 'Reassign Tasks (' + ids.length + ')';
        document.getElementById('reassignSelect').value = '';
        showModal('reassignModal');
    }

    function executeReassign() {
        var ids = getSelectedIds();
        var userId = document.getElementById('reassignSelect').value;
        if (!userId || ids.length === 0) return;
        window.location = baseUrl + '&action=reassign&new_user=' + userId + '&task_ids=' + ids.join(',');
    }

    // ==================== Context Menu ====================
    (function() {
        var menu = document.createElement('div');
        menu.className = 'context-menu';
        menu.id = 'taskContextMenu';

        var statusOptions = <?php echo json_encode($task_statuses); ?>;
        var userOptions = <?php echo json_encode($all_users); ?>;

        // Track whether context was triggered from kanban card or list row
        var ctxSource = 'list'; // 'list' or 'kanban'

        function buildMenu() {
            var selectedIds = getSelectedIds();
            var count = selectedIds.length;
            var isSingle = (ctxSource === 'kanban' || count <= 1);
            var countLabel = count > 1 ? ' (' + count + ')' : '';
            var html = '';

            // Task name header
            if (isSingle && ctxTaskTitle) {
                var displayTitle = ctxTaskTitle.length > 50 ? ctxTaskTitle.substring(0, 50) + '...' : ctxTaskTitle;
                html += '<div class="context-menu-item muted" style="text-transform:none;letter-spacing:0;font-weight:700;color:#2d3748;font-size:0.82rem;padding:8px 16px 6px;line-height:1.3;">' + escapeHtml(displayTitle) + '</div>';
                html += '<div class="context-menu-separator"></div>';
            } else if (count > 1) {
                html += '<div class="context-menu-item muted" style="text-transform:none;letter-spacing:0;font-weight:700;color:#2d3748;font-size:0.82rem;padding:8px 16px 6px;">' + count + ' tasks selected</div>';
                html += '<div class="context-menu-separator"></div>';
            }

            // Open task (single task only)
            if (isSingle && ctxTaskId) {
                html += '<div class="context-menu-item" data-action="open"><span class="ctx-icon">🔗</span> Open Task</div>';
                html += '<div class="context-menu-item" data-action="open-new-tab"><span class="ctx-icon">↗</span> Open in New Tab</div>';
                html += '<div class="context-menu-item" data-action="rename"><span class="ctx-icon">✎</span> Rename Task</div>';
                html += '<div class="context-menu-separator"></div>';
            }

            html += '<div class="context-menu-item" data-action="new"><span class="ctx-icon">+</span> Add New Task</div>';
            html += '<div class="context-menu-separator"></div>';

            // Change Status submenu
            html += '<div class="context-menu-submenu" id="ctxStatusSubmenu">';
            html += '<div class="context-menu-item" data-action="status-parent"><span class="ctx-icon">⟳</span> Change Status' + countLabel + '</div>';
            html += '<div class="context-menu-submenu-items" id="ctxStatusItems">';
            statusOptions.forEach(function(s) {
                html += '<div class="context-menu-item" data-action="set-status" data-value="' + s.id + '"><span class="ctx-icon"></span>' + escapeHtml(s.name) + '</div>';
            });
            html += '</div></div>';

            // Reassign submenu
            html += '<div class="context-menu-submenu" id="ctxReassignSubmenu">';
            html += '<div class="context-menu-item" data-action="reassign-parent"><span class="ctx-icon">👤</span> Reassign' + countLabel + '</div>';
            html += '<div class="context-menu-submenu-items" id="ctxReassignItems">';
            userOptions.forEach(function(u) {
                html += '<div class="context-menu-item" data-action="set-user" data-value="' + u.id + '"><span class="ctx-icon"></span>' + escapeHtml(u.name) + '</div>';
            });
            html += '</div></div>';

            html += '<div class="context-menu-separator"></div>';

            // Close
            html += '<div class="context-menu-item danger" data-action="close"><span class="ctx-icon">✕</span> Close Task' + (count > 1 ? 's' : '') + countLabel + '</div>';

            html += '<div class="context-menu-separator"></div>';

            // Copy submenu
            html += '<div class="context-menu-submenu" id="ctxCopySubmenu">';
            html += '<div class="context-menu-item" data-action="copy-parent"><span class="ctx-icon">📋</span> Copy</div>';
            html += '<div class="context-menu-submenu-items" id="ctxCopyItems">';
            html += '<div class="context-menu-item" data-action="copy-url"><span class="ctx-icon">🔗</span> URL</div>';
            html += '<div class="context-menu-item" data-action="copy-name"><span class="ctx-icon">T</span> Task Name</div>';
            html += '<div class="context-menu-item" data-action="copy-id"><span class="ctx-icon">#</span> Task ID</div>';
            html += '</div></div>';

            return html;
        }

        document.body.appendChild(menu);

        var ctxTaskId = null;
        var ctxTaskTitle = null;

        function showMenuAt(e) {
            menu.innerHTML = buildMenu();
            menu.classList.add('show');

            // Position
            var x = e.clientX, y = e.clientY;
            menu.style.left = '-9999px';
            menu.style.top = '-9999px';
            // Force layout so we get correct size
            var mr = menu.getBoundingClientRect();
            if (x + mr.width > window.innerWidth) x = window.innerWidth - mr.width - 8;
            if (y + mr.height > window.innerHeight) y = window.innerHeight - mr.height - 8;
            if (x < 4) x = 4;
            if (y < 4) y = 4;
            menu.style.left = x + 'px';
            menu.style.top = y + 'px';

            attachSubmenuHandlers();
        }

        // Right-click handler for LIST view rows
        document.querySelectorAll('tr.clickable-row[data-task-id]').forEach(function(row) {
            row.addEventListener('contextmenu', function(e) {
                e.preventDefault();
                e.stopPropagation();

                ctxSource = 'list';
                ctxTaskId = this.dataset.taskId;
                ctxTaskTitle = this.dataset.taskTitle;

                // If the right-clicked row is not selected, select only it
                var cb = this.querySelector('.row-checkbox');
                var selectedIds = getSelectedIds();
                if (selectedIds.indexOf(ctxTaskId) === -1) {
                    clearSelection();
                    if (cb) { cb.checked = true; updateBulkBar(); }
                }

                showMenuAt(e);
            });
        });

        // Right-click handler for KANBAN cards
        document.querySelectorAll('.kanban-card[data-task-id]').forEach(function(card) {
            card.addEventListener('contextmenu', function(e) {
                e.preventDefault();
                e.stopPropagation();

                ctxSource = 'kanban';
                ctxTaskId = this.dataset.taskId;
                ctxTaskTitle = this.dataset.taskTitle;

                showMenuAt(e);
            });
        });

        function attachSubmenuHandlers() {
            menu.querySelectorAll('.context-menu-submenu').forEach(function(sub) {
                var items = sub.querySelector('.context-menu-submenu-items');
                sub.addEventListener('mouseenter', function() {
                    var pr = this.getBoundingClientRect();
                    items.style.left = '-9999px';
                    items.style.top = '-9999px';
                    items.classList.add('show');
                    var sw = items.offsetWidth;
                    var sh = items.offsetHeight;
                    var sx = pr.right + 2;
                    if (sx + sw > window.innerWidth) sx = pr.left - sw - 2;
                    if (sx < 0) sx = 4;
                    var sy = pr.top;
                    if (sy + sh > window.innerHeight) sy = window.innerHeight - sh - 8;
                    if (sy < 0) sy = 4;
                    items.style.left = sx + 'px';
                    items.style.top = sy + 'px';
                });
                sub.addEventListener('mouseleave', function() {
                    items.classList.remove('show');
                });
            });
        }

        // Close menu
        document.addEventListener('click', function() { closeCtxMenu(); });
        document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeCtxMenu(); });
        window.addEventListener('scroll', function() { closeCtxMenu(); });

        function closeCtxMenu() {
            menu.classList.remove('show');
            menu.querySelectorAll('.context-menu-submenu-items').forEach(function(s) { s.classList.remove('show'); });
        }

        // Menu item actions
        menu.addEventListener('click', function(e) {
            var item = e.target.closest('.context-menu-item');
            if (!item) return;
            var action = item.dataset.action;
            if (!action || action === 'copy-parent' || action === 'status-parent' || action === 'reassign-parent') return;

            e.stopPropagation();
            closeCtxMenu();

            // For kanban card actions, use single-task operations
            var isKanban = (ctxSource === 'kanban');
            var selectedIds = isKanban ? [ctxTaskId] : getSelectedIds();
            if (selectedIds.length === 0 && ctxTaskId) selectedIds = [ctxTaskId];

            switch (action) {
                case 'open':
                    if (ctxTaskId) window.location = 'edit_task.php?task_id=' + ctxTaskId;
                    break;
                case 'open-new-tab':
                    if (ctxTaskId) window.open('edit_task.php?task_id=' + ctxTaskId, '_blank');
                    break;
                case 'rename':
                    if (ctxTaskId) startInlineRename(ctxTaskId, ctxTaskTitle, ctxSource);
                    break;
                case 'new':
                    window.location = 'create_task.php?project_id=' + projectId;
                    break;
                case 'close':
                    if (selectedIds.length > 1) {
                        confirmBulkClose(selectedIds);
                    } else if (selectedIds.length === 1) {
                        confirmClose(selectedIds[0], ctxTaskTitle);
                    }
                    break;
                case 'set-status':
                    var val = item.dataset.value;
                    if (val && selectedIds.length > 0) {
                        if (isKanban) {
                            // Use AJAX for kanban so the board updates without reload
                            kanbanChangeStatus(ctxTaskId, val);
                        } else {
                            window.location = baseUrl + '&action=change_status&new_status=' + val + '&task_ids=' + selectedIds.join(',');
                        }
                    }
                    break;
                case 'set-user':
                    var val = item.dataset.value;
                    if (val && selectedIds.length > 0) {
                        window.location = baseUrl + '&action=reassign&new_user=' + val + '&task_ids=' + selectedIds.join(',');
                    }
                    break;
                case 'copy-url':
                    copyToClipboard(window.location.origin + '/edit_task.php?task_id=' + ctxTaskId, 'Task URL copied');
                    break;
                case 'copy-name':
                    copyToClipboard(ctxTaskTitle, 'Task name copied');
                    break;
                case 'copy-id':
                    copyToClipboard(ctxTaskId, 'Task ID copied');
                    break;
            }
        });

        // Kanban-specific: change status via AJAX and move card to correct column
        function kanbanChangeStatus(taskId, newStatusId) {
            var card = document.querySelector('.kanban-card[data-task-id="' + taskId + '"]');
            if (!card) return;
            var oldStatusId = card.dataset.statusId;

            // Find target column by checking which column contains this status
            var targetCol = null;
            document.querySelectorAll('.kanban-column[data-status-id]').forEach(function(col) {
                if (col.dataset.statusId === String(newStatusId)) targetCol = col;
            });
            // If no exact match, check if any column's statuses include it
            // (primary_status is just the first one, but the column holds multiple)
            <?php
            // Output a JS map of status_id => column key
            $status_to_col = array();
            foreach ($kanban_columns as $ckey => $cval) {
                foreach ($cval['statuses'] as $sid) {
                    $status_to_col[$sid] = $ckey;
                }
            }
            echo "var statusToCol = " . json_encode($status_to_col) . ";\n";
            ?>
            if (!targetCol) {
                var colKey = statusToCol[parseInt(newStatusId)];
                if (colKey) {
                    targetCol = document.querySelector('.kanban-column[data-col-key="' + colKey + '"]');
                }
            }

            var oldColumn = card.closest('.kanban-column');

            var formData = new FormData();
            formData.append('action', 'kanban_move_task');
            formData.append('task_id', taskId);
            formData.append('new_status_id', newStatusId);

            card.style.transition = 'border-left-color 0.3s';
            card.style.borderLeftColor = '#667eea';

            fetch('ajax_responder.php', { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success && data.changed) {
                    card.dataset.statusId = newStatusId;
                    // Update badge
                    var badge = card.querySelector('.kanban-card-badge');
                    if (badge && data.new_status_name) badge.textContent = data.new_status_name;
                    // Move card to target column
                    if (targetCol && targetCol !== oldColumn) {
                        var targetCards = targetCol.querySelector('.kanban-cards');
                        var emptyMsg = targetCards.querySelector('.kanban-empty');
                        if (emptyMsg) emptyMsg.remove();
                        targetCards.appendChild(card);
                        // Update counts
                        document.querySelectorAll('.kanban-column').forEach(function(col) {
                            var cnt = col.querySelectorAll('.kanban-card').length;
                            var cntEl = col.querySelector('.kanban-column-count');
                            if (cntEl) cntEl.textContent = cnt;
                            var cc = col.querySelector('.kanban-cards');
                            if (cnt === 0 && !cc.querySelector('.kanban-empty')) {
                                var em = document.createElement('div');
                                em.className = 'kanban-empty';
                                em.textContent = 'No tasks';
                                cc.appendChild(em);
                            }
                        });
                    }
                    card.style.borderLeftColor = '#48bb78';
                    setTimeout(function() { card.style.borderLeftColor = ''; }, 1500);
                    showKanbanToast2('Moved to "' + (data.new_status_name || 'new status') + '"');
                } else if (data.success && !data.changed) {
                    card.style.borderLeftColor = '';
                } else {
                    card.style.borderLeftColor = '';
                    showKanbanToast2(data.error || 'Failed to update', true);
                }
            })
            .catch(function() {
                card.style.borderLeftColor = '';
                showKanbanToast2('Network error', true);
            });
        }

        function showKanbanToast2(message, isError) {
            var existing = document.getElementById('kanbanToast');
            if (existing) existing.remove();
            var toast = document.createElement('div');
            toast.id = 'kanbanToast';
            toast.className = 'kanban-toast' + (isError ? ' error' : '');
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(function() {
                toast.style.opacity = '0';
                setTimeout(function() { toast.remove(); }, 300);
            }, 2500);
        }

        function startInlineRename(taskId, currentTitle, source) {
            var el, linkSel, isCard = (source === 'kanban');
            if (isCard) {
                el = document.querySelector('.kanban-card[data-task-id="' + taskId + '"] .kanban-card-title');
            } else {
                el = document.querySelector('tr[data-task-id="' + taskId + '"] .task-title-cell');
            }
            if (!el) return;

            var link = el.querySelector('a');
            var originalHtml = el.innerHTML;
            var input = document.createElement('textarea');
            input.className = 'rename-input';
            input.value = currentTitle || '';
            input.rows = 2;
            el.innerHTML = '';
            el.appendChild(input);
            input.focus();
            input.select();

            function restore(savedTitle) {
                if (savedTitle !== undefined) {
                    if (isCard && link) {
                        el.innerHTML = '<a href="edit_task.php?task_id=' + taskId + '" onclick="event.stopPropagation()">' + escapeHtml(savedTitle) + '</a>';
                    } else if (!isCard) {
                        el.innerHTML = '<a href="edit_task.php?task_id=' + taskId + '" onclick="event.stopPropagation()">' + escapeHtml(savedTitle) + '</a>';
                    }
                } else {
                    el.innerHTML = originalHtml;
                }
            }

            var saveInProgress = false;
            function save() {
                if (saveInProgress) return;
                var newTitle = input.value.trim();
                if (newTitle === currentTitle || !newTitle) {
                    restore();
                    return;
                }
                saveInProgress = true;
                var formData = new FormData();
                formData.append('action', 'rename_task');
                formData.append('task_id', taskId);
                formData.append('task_title', newTitle);
                fetch('ajax_responder.php', { method: 'POST', body: formData })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) {
                            restore(data.task_title);
                            var card = document.querySelector('.kanban-card[data-task-id="' + taskId + '"]');
                            if (card) card.dataset.taskTitle = data.task_title;
                            var row = document.querySelector('tr[data-task-id="' + taskId + '"]');
                            if (row) row.dataset.taskTitle = data.task_title;
                            showKanbanToast2('Task renamed');
                        } else {
                            saveInProgress = false;
                            restore();
                            showKanbanToast2(data.error || 'Rename failed', true);
                        }
                    })
                    .catch(function() {
                        saveInProgress = false;
                        restore();
                        showKanbanToast2('Network error', true);
                    });
            }

            input.addEventListener('blur', function() {
                setTimeout(save, 120);
            });
            input.addEventListener('keydown', function(e) {
                e.stopPropagation();
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    save();
                } else if (e.key === 'Escape') {
                    restore();
                }
            });
            input.addEventListener('click', function(e) { e.stopPropagation(); });
        }

        function copyToClipboard(text, message) {
            navigator.clipboard.writeText(text).then(function() {
                showToast(message);
            }).catch(function() {
                var ta = document.createElement('textarea');
                ta.value = text;
                ta.style.cssText = 'position:fixed;opacity:0';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                showToast(message);
            });
        }

        function showToast(message) {
            var toast = document.getElementById('copyToast');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'copyToast';
                toast.style.cssText = 'position:fixed;bottom:80px;left:50%;transform:translateX(-50%);background:#2d3748;color:#fff;padding:10px 20px;border-radius:8px;font-size:0.85rem;z-index:10020;opacity:0;transition:opacity 0.2s;pointer-events:none;';
                document.body.appendChild(toast);
            }
            toast.textContent = message;
            toast.style.opacity = '1';
            setTimeout(function() { toast.style.opacity = '0'; }, 1500);
        }

        // Expose a global function to bind context menu to dynamically added cards
        window.bindCardContextMenu = function(card) {
            card.addEventListener('contextmenu', function(e) {
                e.preventDefault();
                e.stopPropagation();
                ctxSource = 'kanban';
                ctxTaskId = this.dataset.taskId;
                ctxTaskTitle = this.dataset.taskTitle;
                showMenuAt(e);
            });
        };
    })();

    function escapeHtml(text) {
        if (!text && text !== 0) return '';
        var s = String(text);
        return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }

    /* ---- Team management ---- */
    var projectId = <?php echo (int)$project_id; ?>;

    function toggleAddMember() {
        var dd = document.getElementById('addMemberDropdown');
        dd.classList.toggle('show');
        if (dd.classList.contains('show')) {
            document.getElementById('addMemberSearch').value = '';
            filterAddMembers();
            document.getElementById('addMemberSearch').focus();
            refreshDisabledMembers();
        }
    }

    // Close dropdown on outside click
    document.addEventListener('click', function(e) {
        var dd = document.getElementById('addMemberDropdown');
        if (dd && dd.classList.contains('show') && !dd.contains(e.target) && e.target.id !== 'addMemberBtn') {
            dd.classList.remove('show');
        }
    });

    function filterAddMembers() {
        var q = document.getElementById('addMemberSearch').value.toLowerCase();
        var items = document.querySelectorAll('#addMemberList .team-add-option');
        items.forEach(function(item) {
            var name = item.getAttribute('data-name').toLowerCase();
            item.style.display = name.indexOf(q) !== -1 ? '' : 'none';
        });
    }

    function refreshDisabledMembers() {
        var current = [];
        document.querySelectorAll('#teamMembers .assigned-user').forEach(function(el) {
            current.push(parseInt(el.getAttribute('data-user-id')));
        });
        document.querySelectorAll('#addMemberList .team-add-option').forEach(function(opt) {
            var uid = parseInt(opt.getAttribute('data-user-id'));
            if (current.indexOf(uid) !== -1) {
                opt.classList.add('disabled');
            } else {
                opt.classList.remove('disabled');
            }
        });
    }

    function addMember(userId, userName, el) {
        if (el.classList.contains('disabled')) return;
        el.classList.add('disabled');
        fetch('ajax_responder.php?action=add_project_member&project_id=' + projectId + '&user_id=' + userId)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    var fullName = data.user_name || userName;
                    var firstName = fullName.split(' ')[0];
                    var span = document.createElement('span');
                    span.className = 'assigned-user';
                    span.setAttribute('data-user-id', userId);
                    span.innerHTML = '<a href="report_people.php?report_user_id=' + userId + '" title="' + escapeHtml(fullName) + '">' + escapeHtml(firstName) + '</a>' +
                        '<button class="remove-member" onclick="removeMember(' + userId + ', this)" title="Remove from project">&times;</button>';
                    var addWrapper = document.getElementById('addMemberBtn').parentElement;
                    document.getElementById('teamMembers').insertBefore(span, addWrapper);
                    showTeamToast(escapeHtml(name) + ' added to team');
                } else {
                    showTeamToast(data.error || 'Could not add user', true);
                }
                document.getElementById('addMemberDropdown').classList.remove('show');
            });
    }

    function removeMember(userId, btn) {
        var badge = btn.closest('.assigned-user');
        var userName = badge.querySelector('a').textContent;
        showConfirmPopup(
            'Remove Team Member',
            'Remove <strong>' + escapeHtml(userName) + '</strong> from this project?',
            function() {
                badge.style.opacity = '0.5';
                fetch('ajax_responder.php?action=remove_project_member&project_id=' + projectId + '&user_id=' + userId)
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) {
                            badge.remove();
                            showTeamToast(escapeHtml(userName) + ' removed from team');
                        } else {
                            badge.style.opacity = '1';
                            showTeamToast(data.error || 'Could not remove user', true);
                        }
                    });
            }
        );
    }

    /* Custom confirmation popup */
    function showConfirmPopup(title, message, onConfirm) {
        // Remove any existing
        var existing = document.querySelector('.confirm-overlay');
        if (existing) existing.remove();

        var overlay = document.createElement('div');
        overlay.className = 'confirm-overlay';
        overlay.innerHTML =
            '<div class="confirm-box">' +
                '<div class="confirm-title">' + title + '</div>' +
                '<div class="confirm-msg">' + message + '</div>' +
                '<div class="confirm-actions">' +
                    '<button class="confirm-cancel">Cancel</button>' +
                    '<button class="confirm-ok">Remove</button>' +
                '</div>' +
                '<div class="confirm-hint">Enter to confirm &middot; Esc to cancel</div>' +
            '</div>';
        document.body.appendChild(overlay);

        // Trigger animation
        requestAnimationFrame(function() { overlay.classList.add('show'); });

        var confirmBtn = overlay.querySelector('.confirm-ok');
        var cancelBtn = overlay.querySelector('.confirm-cancel');

        function close() {
            overlay.classList.remove('show');
            document.removeEventListener('keydown', onKey);
            setTimeout(function() { overlay.remove(); }, 150);
        }

        function doConfirm() {
            close();
            if (onConfirm) onConfirm();
        }

        function onKey(e) {
            if (e.key === 'Enter') { e.preventDefault(); doConfirm(); }
            if (e.key === 'Escape') { e.preventDefault(); close(); }
        }

        confirmBtn.addEventListener('click', doConfirm);
        cancelBtn.addEventListener('click', close);
        overlay.addEventListener('click', function(e) { if (e.target === overlay) close(); });
        document.addEventListener('keydown', onKey);
        confirmBtn.focus();
    }

    function showTeamToast(msg, isError) {
        var t = document.createElement('div');
        t.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:9999;padding:12px 20px;border-radius:10px;font-size:0.85rem;color:#fff;box-shadow:0 4px 12px rgba(0,0,0,0.15);transition:opacity 0.3s;';
        t.style.background = isError ? '#e53e3e' : 'linear-gradient(135deg,#667eea 0%,#764ba2 100%)';
        t.innerHTML = msg;
        document.body.appendChild(t);
        setTimeout(function(){ t.style.opacity='0'; }, 2000);
        setTimeout(function(){ t.remove(); }, 2500);
    }

    // ==================== Keyboard Navigation for Kanban ====================
    (function() {
        var focusedCard = null;
        var kbHint = null;

        function isCardsViewActive() {
            var panel = document.getElementById('viewCards');
            return panel && panel.classList.contains('active');
        }

        function getAllColumns() {
            return Array.prototype.slice.call(document.querySelectorAll('.kanban-board .kanban-column'));
        }

        function getCardsInColumn(col) {
            return Array.prototype.slice.call(col.querySelectorAll('.kanban-card'));
        }

        function setFocus(card) {
            if (focusedCard) focusedCard.classList.remove('kb-focus');
            focusedCard = card;
            if (card) {
                card.classList.add('kb-focus');
                card.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                showHint();
            } else {
                hideHint();
            }
        }
        // Expose globally so executeClose can use it
        window.setKbFocus = setFocus;

        function showHint() {
            if (kbHint) return;
            kbHint = document.createElement('div');
            kbHint.className = 'kb-hint';
            kbHint.innerHTML = '<kbd>&uarr;</kbd><kbd>&darr;</kbd> navigate ' +
                '<kbd>&lt;</kbd> left ' +
                '<kbd>&gt;</kbd> right ' +
                '<kbd>Enter</kbd> open ' +
                '<kbd>C</kbd> close ' +
                '<kbd>Esc</kbd> deselect';
            document.body.appendChild(kbHint);
        }

        function hideHint() {
            if (kbHint) { kbHint.remove(); kbHint = null; }
        }

        function getCardLocation(card) {
            var cols = getAllColumns();
            for (var ci = 0; ci < cols.length; ci++) {
                var cards = getCardsInColumn(cols[ci]);
                var idx = cards.indexOf(card);
                if (idx !== -1) return { colIdx: ci, cardIdx: idx, col: cols[ci], cards: cards };
            }
            return null;
        }

        function moveVertical(dir) {
            if (!focusedCard) {
                // Select first card in first non-empty column
                var cols = getAllColumns();
                for (var i = 0; i < cols.length; i++) {
                    var cards = getCardsInColumn(cols[i]);
                    if (cards.length) { setFocus(cards[0]); return; }
                }
                return;
            }
            var loc = getCardLocation(focusedCard);
            if (!loc) return;
            var next = loc.cardIdx + dir;
            if (next >= 0 && next < loc.cards.length) {
                setFocus(loc.cards[next]);
            }
        }

        function moveHorizontal(dir) {
            if (!focusedCard) return;
            var loc = getCardLocation(focusedCard);
            if (!loc) return;
            var cols = getAllColumns();
            var newColIdx = loc.colIdx + dir;
            if (newColIdx < 0 || newColIdx >= cols.length) return;

            var targetCol = cols[newColIdx];
            var targetStatusId = targetCol.dataset.statusId;
            if (!targetStatusId) {
                showKanbanToast('Cannot move to this column', true);
                return;
            }

            var card = focusedCard;
            var oldStatusId = card.dataset.statusId;
            var taskId = card.dataset.taskId;
            var oldColumn = loc.col;

            // Move card in DOM
            var targetCards = targetCol.querySelector('.kanban-cards');
            var emptyMsg = targetCards.querySelector('.kanban-empty');
            if (emptyMsg) emptyMsg.remove();
            targetCards.appendChild(card);
            card.dataset.statusId = targetStatusId;
            updateColumnCounts();

            // Scroll the card into view
            card.scrollIntoView({ block: 'nearest', behavior: 'smooth' });

            // AJAX update
            if (oldStatusId !== targetStatusId) {
                moveTaskStatus(taskId, targetStatusId, card, oldStatusId, oldColumn);
            }
        }

        function closeFocusedCard() {
            if (!focusedCard) return;
            var taskId = focusedCard.dataset.taskId;
            var taskTitle = focusedCard.dataset.taskTitle || '';
            confirmClose(taskId, taskTitle);
        }

        function openFocusedCard() {
            if (!focusedCard) return;
            var taskId = focusedCard.dataset.taskId;
            // Store the focused task ID so we can re-focus on return
            sessionStorage.setItem('kanban_focused_task', taskId);
            window.location = 'edit_task.php?task_id=' + taskId;
        }

        // Click on a card to focus it for keyboard nav
        document.addEventListener('click', function(e) {
            var card = e.target.closest('.kanban-card');
            if (card && isCardsViewActive()) {
                // Don't interfere with link clicks
                if (e.target.tagName === 'A' || e.target.closest('a')) return;
                setFocus(card);
            } else if (isCardsViewActive() && !e.target.closest('.kanban-card') && !e.target.closest('.confirm-overlay') && !e.target.closest('.modal-overlay') && !e.target.closest('.context-menu')) {
                setFocus(null);
            }
        });

        // Hover on a card to focus it for keyboard nav
        document.addEventListener('mouseover', function(e) {
            if (!isCardsViewActive()) return;
            var card = e.target.closest('.kanban-card');
            if (card && card !== focusedCard) {
                setFocus(card);
            }
        });

        document.addEventListener('keydown', function(e) {
            if (!isCardsViewActive()) return;
            // Don't interfere with inputs, modals, dropdowns
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return;
            if (document.querySelector('.confirm-overlay.show') || document.querySelector('.modal-overlay.active')) return;

            var key = e.key;

            switch(key) {
                case 'ArrowDown':
                    e.preventDefault();
                    moveVertical(1);
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    moveVertical(-1);
                    break;
                case 'ArrowRight':
                case '>':
                case '.':
                    if (key === 'ArrowRight' && !focusedCard) return; // allow normal scroll
                    if (focusedCard) {
                        e.preventDefault();
                        moveHorizontal(1);
                    }
                    break;
                case 'ArrowLeft':
                case '<':
                case ',':
                    if (key === 'ArrowLeft' && !focusedCard) return;
                    if (focusedCard) {
                        e.preventDefault();
                        moveHorizontal(-1);
                    }
                    break;
                case 'c':
                case 'C':
                    if (focusedCard) {
                        e.preventDefault();
                        closeFocusedCard();
                    }
                    break;
                case 'Enter':
                    if (focusedCard) {
                        e.preventDefault();
                        openFocusedCard();
                    }
                    break;
                case 'Escape':
                    if (focusedCard) {
                        e.preventDefault();
                        setFocus(null);
                    }
                    break;
            }
        });

        // Re-focus card after returning from a task page
        function restoreFocus() {
            var savedId = sessionStorage.getItem('kanban_focused_task');
            if (savedId) {
                var card = document.querySelector('.kanban-card[data-task-id="' + savedId + '"]');
                if (card && isCardsViewActive()) {
                    setFocus(card);
                }
                sessionStorage.removeItem('kanban_focused_task');
            }
        }
        restoreFocus();
    })();

    // ==================== Refresh task data on return from task page ====================
    (function() {
        // Only reload when returning from an edit_task page (navigated via Enter/click)
        var navigatedToTask = false;

        // Detect when we're navigating away to edit_task
        document.addEventListener('click', function(e) {
            var link = e.target.closest('a[href*="edit_task.php"]');
            if (link) navigatedToTask = true;
        });

        // Also set flag from keyboard open
        var origOpen = window.openFocusedCardFlag;
        window.addEventListener('beforeunload', function() {
            if (sessionStorage.getItem('kanban_focused_task')) {
                navigatedToTask = true;
            }
        });

        // Use pageshow to handle bfcache (back/forward)
        window.addEventListener('pageshow', function(e) {
            if (e.persisted) {
                // Page was restored from bfcache (back button)
                var savedTask = sessionStorage.getItem('kanban_focused_task');
                if (savedTask) {
                    window.location.reload();
                }
            }
        });

        document.addEventListener('visibilitychange', function() {
            if (!document.hidden && sessionStorage.getItem('kanban_focused_task')) {
                // We're coming back from a task page - reload to get fresh data
                window.location.reload();
            }
        });
    })();

    // ==================== Timeline ====================
    function renderTimeline() {
        timelineRendered = true;
        var container = document.getElementById('timelineChart');
        if (!container) return;

        if (!timelineData || timelineData.length === 0) {
            container.innerHTML = '<div class="tl-no-data">No activity data to display</div>';
            return;
        }

        // Filter out tasks with no real activity within the visible date range
        var rangeMin = timelineMinDate;
        var rangeMax = timelineMaxDate;
        var filtered = timelineData.filter(function(t) {
            var hasVisible = false;
            for (var k = 0; k < t.events.length; k++) {
                var ev = t.events[k];
                if (ev.type === 'created') continue; // skip creation-only
                if (!ev.date || ev.date === '0000-00-00') continue;
                if (ev.date >= rangeMin && ev.date <= rangeMax) { hasVisible = true; break; }
            }
            return hasVisible;
        });

        if (filtered.length === 0) {
            container.innerHTML = '<div class="tl-no-data">No activity data to display</div>';
            return;
        }

        // Sort tasks: open first (by first event date), then closed
        var sorted = filtered.slice().sort(function(a, b) {
            if (a.is_closed && !b.is_closed) return 1;
            if (!a.is_closed && b.is_closed) return -1;
            var aMin = getFirstDate(a), bMin = getFirstDate(b);
            if (aMin < bMin) return -1;
            if (aMin > bMin) return 1;
            return 0;
        });

        // Date range from PHP
        var dMin = parseDate(timelineMinDate);
        var dMax = parseDate(timelineMaxDate);
        // Small padding
        dMin = addDays(dMin, -3);
        dMax = addDays(dMax, 7);

        var totalDays = daysBetween(dMin, dMax);
        if (totalDays < 30) totalDays = 30;

        // Auto-fit: calculate dayWidth to fill the viewport
        var labelW = 220;
        var containerParent = container.closest('.timeline-container');
        var statusW = 90;
        var availableWidth = (containerParent ? containerParent.offsetWidth : 900) - labelW - statusW - 20;
        var dayWidth = Math.max(1, availableWidth / totalDays);
        var chartWidth = Math.round(totalDays * dayWidth);

        // Week size in pixels (for aggregated bars)
        var weekPx = Math.round(7 * dayWidth);

        // Build HTML
        var html = '<div class="tl-wrapper">';

        // ---- Header ----
        html += '<div class="tl-header">';
        html += '<div class="tl-header-label" style="width:' + labelW + 'px;">Task</div>';
        html += '<div class="tl-header-label" style="width:' + statusW + 'px;font-size:0.72rem;">Status</div>';
        html += '<div class="tl-header-dates" style="width:' + chartWidth + 'px;position:relative;">';

        // Month headers
        var cur = new Date(dMin.getFullYear(), dMin.getMonth(), 1);
        while (cur <= dMax) {
            var mStart = new Date(cur);
            var mEnd = new Date(cur.getFullYear(), cur.getMonth() + 1, 0);
            var startOff = Math.max(0, daysBetween(dMin, mStart));
            var endOff = Math.min(totalDays, daysBetween(dMin, mEnd) + 1);
            var mWidth = Math.round((endOff - startOff) * dayWidth);
            if (mWidth > 0) {
                var mNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                var mName = mNames[mStart.getMonth()] + (mWidth > 40 ? ' ' + mStart.getFullYear() : '');
                html += '<div class="tl-month" style="width:' + mWidth + 'px;">' + mName + '</div>';
            }
            cur = new Date(cur.getFullYear(), cur.getMonth() + 1, 1);
        }

        // Today label in header
        var todayDate = parseDate(serverToday);
        var todayOff = daysBetween(dMin, todayDate);
        if (todayOff >= 0 && todayOff <= totalDays) {
            var todayPx = Math.round(todayOff * dayWidth);
            html += '<div style="position:absolute;left:' + todayPx + 'px;top:0;bottom:0;width:2px;background:#e53e3e;z-index:6;"></div>';
            html += '<div style="position:absolute;left:' + todayPx + 'px;bottom:-14px;transform:translateX(-50%);font-size:0.62rem;color:#e53e3e;font-weight:600;white-space:nowrap;z-index:6;">Today</div>';
        }
        html += '</div></div>';

        // ---- Body ----
        html += '<div class="tl-body">';

        for (var i = 0; i < sorted.length; i++) {
            var task = sorted[i];
            var rowCls = task.is_closed ? 'tl-row closed' : 'tl-row';
            html += '<div class="' + rowCls + '" data-task-id="' + task.task_id + '" data-closed="' + (task.is_closed ? '1' : '0') + '" data-periodic="' + (task.is_periodic ? '1' : '0') + '">';

            // Label
            html += '<div class="tl-task-label" style="width:' + labelW + 'px;">';
            html += '<div style="min-width:0;flex:1;">';
            html += '<a class="tl-task-name" href="edit_task.php?task_id=' + task.task_id + '" title="' + escapeHtml(task.title) + '">' + escapeHtml(truncate(task.title, 30)) + '</a>';
            if (task.responsible) {
                html += '<div class="tl-task-user">' + escapeHtml(task.responsible) + '</div>';
            }
            html += '</div></div>';

            // Status
            html += '<div class="tl-task-status" style="width:' + statusW + 'px;">';
            if (task.is_closed) {
                html += '<span style="font-size:0.65rem;color:#a0aec0;font-weight:500;text-decoration:line-through;">closed</span>';
            } else {
                var sCls = statusClassMap[task.status_id] ? 'status-' + statusClassMap[task.status_id] : 'status-new';
                html += '<span class="status-badge ' + sCls + '">' + escapeHtml(task.status_desc || '') + '</span>';
            }
            html += '</div>';

            // Chart area
            html += '<div class="tl-chart" style="width:' + chartWidth + 'px;height:36px;">';

            // Aggregate events by week
            var weekBuckets = {}; // key = week start offset in days, value = {messages, hours, created}
            var allDates = [];
            for (var j = 0; j < task.events.length; j++) {
                var ev = task.events[j];
                if (!ev.date || ev.date === '0000-00-00') continue;
                var evDate = parseDate(ev.date);
                var dayOff = daysBetween(dMin, evDate);
                allDates.push(dayOff);
                var weekKey = Math.floor(dayOff / 7) * 7;
                if (!weekBuckets[weekKey]) weekBuckets[weekKey] = { messages: 0, hours: 0, created: false, minDay: dayOff, maxDay: dayOff };
                if (ev.type === 'message') weekBuckets[weekKey].messages += (ev.count || 1);
                if (ev.type === 'time') weekBuckets[weekKey].hours += (ev.hours || 0);
                if (ev.type === 'created') weekBuckets[weekKey].created = true;
                if (dayOff < weekBuckets[weekKey].minDay) weekBuckets[weekKey].minDay = dayOff;
                if (dayOff > weekBuckets[weekKey].maxDay) weekBuckets[weekKey].maxDay = dayOff;
            }

            // Draw activity span (thin background bar from first to last event)
            if (allDates.length > 0) {
                allDates.sort(function(a,b){return a-b;});
                var spanX1 = Math.round(allDates[0] * dayWidth);
                var spanX2 = Math.round(allDates[allDates.length - 1] * dayWidth);
                var spanW = Math.max(spanX2 - spanX1, 2);
                html += '<div style="position:absolute;top:50%;transform:translateY(-50%);left:' + spanX1 + 'px;width:' + spanW + 'px;height:3px;background:#e2e8f0;border-radius:2px;z-index:1;"></div>';
            }

            // Draw weekly aggregated bars
            var wKeys = Object.keys(weekBuckets);
            for (var w = 0; w < wKeys.length; w++) {
                var wk = weekBuckets[wKeys[w]];
                var wkStart = parseInt(wKeys[w]);
                var barX = Math.round(wkStart * dayWidth);
                var barW = Math.max(weekPx, 4);
                var tipParts = [];

                // Determine bar color based on what happened
                var barColor = '';
                var barHeight = 8;
                if (wk.hours > 0 && wk.messages > 0) {
                    barColor = 'linear-gradient(90deg, #ed8936, #4299e1)';
                    barHeight = 10;
                    tipParts.push(Math.round(wk.hours * 10) / 10 + 'h logged');
                    tipParts.push(wk.messages + ' msg');
                } else if (wk.hours > 0) {
                    barColor = '#ed8936';
                    barHeight = Math.min(12, 6 + Math.round(wk.hours));
                    tipParts.push(Math.round(wk.hours * 10) / 10 + 'h logged');
                } else if (wk.messages > 0) {
                    barColor = '#4299e1';
                    barHeight = Math.min(10, 6 + wk.messages);
                    tipParts.push(wk.messages + ' message' + (wk.messages > 1 ? 's' : ''));
                }
                if (wk.created) {
                    tipParts.push('Task created');
                }

                // Build tooltip with week range
                var wkStartDate = addDays(dMin, wkStart);
                var wkEndDate = addDays(dMin, wkStart + 6);
                var wkRange = formatDate(wkStartDate) + ' - ' + formatDate(wkEndDate);
                var tip = wkRange + ': ' + tipParts.join(', ');

                if (barColor) {
                    html += '<div class="tl-week-bar" style="position:absolute;top:50%;transform:translateY(-50%);left:' + barX + 'px;width:' + barW + 'px;height:' + barHeight + 'px;background:' + barColor + ';border-radius:3px;z-index:2;cursor:pointer;opacity:0.8;" data-tip="' + escapeHtml(tip) + '"></div>';
                }

                // Created marker (small green diamond)
                if (wk.created) {
                    var createdX = Math.round(wk.minDay * dayWidth);
                    html += '<div style="position:absolute;top:50%;left:' + createdX + 'px;transform:translate(-50%,-50%) rotate(45deg);width:7px;height:7px;background:#48bb78;border:1.5px solid #fff;z-index:5;cursor:pointer;" data-tip="Created: ' + escapeHtml(formatDate(addDays(dMin, wk.minDay))) + '"></div>';
                }
            }

            // Today line in this row
            if (todayOff >= 0 && todayOff <= totalDays) {
                html += '<div class="tl-today-line" style="left:' + Math.round(todayOff * dayWidth) + 'px;"></div>';
            }

            // Month grid lines in this row
            var gc = new Date(dMin.getFullYear(), dMin.getMonth() + 1, 1);
            while (gc <= dMax) {
                var gOff = Math.round(daysBetween(dMin, gc) * dayWidth);
                html += '<div class="tl-grid-line month-line" style="left:' + gOff + 'px;"></div>';
                gc = new Date(gc.getFullYear(), gc.getMonth() + 1, 1);
            }

            html += '</div>'; // /tl-chart
            html += '</div>'; // /tl-row
        }

        html += '</div>'; // /tl-body
        html += '</div>'; // /tl-wrapper
        container.innerHTML = html;

        // Tooltip handling (init once)
        if (!container._tlTipInit) {
            container._tlTipInit = true;
            var tip = document.getElementById('tlTooltip');
            if (!tip) {
                tip = document.createElement('div');
                tip.id = 'tlTooltip';
                tip.className = 'tl-tooltip';
                document.body.appendChild(tip);
            }
            container.addEventListener('mouseover', function(e) {
                var el = e.target.closest('[data-tip]');
                var t = document.getElementById('tlTooltip');
                if (el && el.dataset.tip && t) {
                    t.textContent = el.dataset.tip;
                    t.classList.add('show');
                }
            });
            container.addEventListener('mousemove', function(e) {
                var t = document.getElementById('tlTooltip');
                if (t && t.classList.contains('show')) {
                    t.style.left = (e.clientX + 12) + 'px';
                    t.style.top = (e.clientY - 30) + 'px';
                }
            });
            container.addEventListener('mouseout', function(e) {
                var el = e.target.closest('[data-tip]');
                var t = document.getElementById('tlTooltip');
                if (el && t) { t.classList.remove('show'); }
            });
        }
    }

    // Resize handler for timeline
    var tlResizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(tlResizeTimer);
        tlResizeTimer = setTimeout(function() {
            var panel = document.getElementById('viewTimeline');
            if (panel && panel.classList.contains('active')) {
                timelineRendered = false;
                renderTimeline();
                applyFilters();
            }
        }, 200);
    });

    // Timeline helpers
    function parseDate(s) {
        var parts = s.split('-');
        return new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
    }
    function daysBetween(a, b) {
        var msDay = 86400000;
        var utc1 = Date.UTC(a.getFullYear(), a.getMonth(), a.getDate());
        var utc2 = Date.UTC(b.getFullYear(), b.getMonth(), b.getDate());
        return Math.floor((utc2 - utc1) / msDay);
    }
    function addDays(d, n) {
        var r = new Date(d);
        r.setDate(r.getDate() + n);
        return r;
    }
    function formatDate(d) {
        var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        return d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
    }
    function getFirstDate(task) {
        var dates = task.events.map(function(e) { return e.date; }).filter(function(d) { return d && d !== '0000-00-00'; });
        dates.sort();
        return dates.length > 0 ? dates[0] : '9999-12-31';
    }
    function truncate(s, n) {
        if (!s) return '';
        return s.length > n ? s.substring(0, n) + '...' : s;
    }

    // ==================== Time View ====================

    function setTimePeriod(p) {
        currentTimePeriod = p;
        document.querySelectorAll('.tp-btn').forEach(function(b) {
            b.classList.toggle('active', b.dataset.period === p);
        });
        renderTimeView();
    }

    function getFilteredTimeData() {
        var cutoff = null;
        var today = parseDate(serverToday);
        if (currentTimePeriod === '1m') cutoff = addDays(today, -30);
        else if (currentTimePeriod === '3m') cutoff = addDays(today, -91);
        else if (currentTimePeriod === '6m') cutoff = addDays(today, -183);
        else if (currentTimePeriod === '12m') cutoff = addDays(today, -365);
        // 'all' => no cutoff

        var cutoffStr = cutoff ? formatISO(cutoff) : null;
        var filtered = [];
        for (var i = 0; i < timeRaw.length; i++) {
            var r = timeRaw[i];
            if (cutoffStr && r.date < cutoffStr) continue;
            filtered.push(r);
        }
        return filtered;
    }

    function aggregateByUser(records) {
        var map = {};
        for (var i = 0; i < records.length; i++) {
            var r = records[i];
            var key = r.uid;
            if (!map[key]) map[key] = { user_id: r.uid, name: r.uname, hours: 0, taskSet: {} };
            map[key].hours += r.hours;
            map[key].taskSet[r.tid] = true;
        }
        var arr = [];
        for (var k in map) {
            if (map.hasOwnProperty(k)) {
                var m = map[k];
                arr.push({ user_id: m.user_id, name: m.name, hours: Math.round(m.hours * 100) / 100, tasks: Object.keys(m.taskSet).length });
            }
        }
        arr.sort(function(a, b) { return b.hours - a.hours; });
        return arr;
    }

    function aggregateByTask(records) {
        var map = {};
        for (var i = 0; i < records.length; i++) {
            var r = records[i];
            var key = r.tid;
            if (!map[key]) map[key] = { task_id: r.tid, title: r.tname, hours: 0, workers: {} };
            map[key].hours += r.hours;
            // Track who actually logged time and how much
            if (r.uname) {
                if (!map[key].workers[r.uid]) map[key].workers[r.uid] = { name: r.uname, hours: 0 };
                map[key].workers[r.uid].hours += r.hours;
            }
        }
        var arr = [];
        for (var k in map) {
            if (map.hasOwnProperty(k)) {
                var m = map[k];
                // Build list of people who logged time, sorted by hours desc
                var wArr = [];
                for (var wk in m.workers) {
                    if (m.workers.hasOwnProperty(wk)) wArr.push(m.workers[wk]);
                }
                wArr.sort(function(a, b) { return b.hours - a.hours; });
                var names = wArr.map(function(w) { return w.name; });
                arr.push({ task_id: m.task_id, title: m.title, people: names.join(', '), hours: Math.round(m.hours * 100) / 100 });
            }
        }
        arr.sort(function(a, b) { return b.hours - a.hours; });
        return arr.slice(0, 15);
    }

    function formatISO(d) {
        var m = d.getMonth() + 1;
        var dd = d.getDate();
        return d.getFullYear() + '-' + (m < 10 ? '0' : '') + m + '-' + (dd < 10 ? '0' : '') + dd;
    }

    function renderTimeView() {
        timeViewRendered = true;
        try {
            var raw = (typeof timeRaw !== 'undefined' && Array.isArray(timeRaw)) ? timeRaw : [];
            if (raw.length === 0) {
                showNoTimeData();
                return;
            }
            var records = getFilteredTimeData();
            var byUser = aggregateByUser(records);
            var byTask = aggregateByTask(records);
            renderPieChart(byUser || []);
            renderTasksList(byTask || []);
        } catch(ex) {
            console.error('renderTimeView error:', ex);
            showNoTimeData();
        }
    }

    function showNoTimeData() {
        var wrap = document.querySelector('.time-chart-wrap');
        if (wrap) wrap.innerHTML = '<div style="text-align:center;padding:60px 20px;color:#a0aec0;">No time data for this period</div>';
        var el = document.getElementById('timeTasksList');
        if (el) el.innerHTML = '<div style="text-align:center;padding:24px;color:#a0aec0;font-size:0.85rem;">No time data</div>';
        var leg = document.getElementById('pieLegendUser');
        if (leg) leg.innerHTML = '';
    }

    function renderPieChart(data) {
        if (!Array.isArray(data)) data = [];
        var wrap = document.querySelector('.time-chart-wrap');
        if (!wrap) return;

        if (data.length === 0) {
            wrap.innerHTML = '<div style="text-align:center;padding:60px 20px;color:#a0aec0;">No time data for this period</div>';
            var leg0 = document.getElementById('pieLegendUser');
            if (leg0) leg0.innerHTML = '';
            return;
        }

        // Create canvas
        wrap.innerHTML = '<canvas id="pieByUser" width="320" height="320"></canvas>';
        var canvas = document.getElementById('pieByUser');
        if (!canvas || !canvas.getContext) return;
        var ctx = canvas.getContext('2d');

        var totalHours = 0;
        for (var i = 0; i < data.length; i++) totalHours += data[i].hours;
        var totalDays = Math.round(totalHours / 8 * 10) / 10;

        // Canvas setup for retina
        var size = 280;
        var dpr = window.devicePixelRatio || 1;
        canvas.width = size * dpr;
        canvas.height = size * dpr;
        canvas.style.width = size + 'px';
        canvas.style.height = size + 'px';
        ctx.scale(dpr, dpr);

        var cx = size / 2;
        var cy = size / 2;
        var outerR = size / 2 - 8;
        var innerR = outerR * 0.58;
        var startAngle = -Math.PI / 2;

        for (var i = 0; i < data.length; i++) {
            var slice = data[i].hours / totalHours;
            var endAngle = startAngle + slice * 2 * Math.PI;
            var color = pieColors[i % pieColors.length];

            ctx.beginPath();
            ctx.moveTo(cx + innerR * Math.cos(startAngle), cy + innerR * Math.sin(startAngle));
            ctx.arc(cx, cy, outerR, startAngle, endAngle);
            ctx.arc(cx, cy, innerR, endAngle, startAngle, true);
            ctx.closePath();
            ctx.fillStyle = color;
            ctx.fill();

            ctx.beginPath();
            ctx.moveTo(cx + innerR * Math.cos(endAngle), cy + innerR * Math.sin(endAngle));
            ctx.lineTo(cx + outerR * Math.cos(endAngle), cy + outerR * Math.sin(endAngle));
            ctx.strokeStyle = '#fff';
            ctx.lineWidth = 2;
            ctx.stroke();

            startAngle = endAngle;
        }

        // Center text
        var centerDiv = document.createElement('div');
        centerDiv.className = 'pie-center-text';
        centerDiv.innerHTML = '<div class="pie-center-total">' + totalDays + 'd</div><div class="pie-center-label">' + Math.round(totalHours) + ' hours</div>';
        wrap.appendChild(centerDiv);

        // Legend
        var legendEl = document.getElementById('pieLegendUser');
        if (legendEl) {
            var lhtml = '';
            for (var i = 0; i < data.length; i++) {
                var d = data[i];
                var days = Math.round(d.hours / 8 * 10) / 10;
                var pct = Math.round(d.hours / totalHours * 100);
                var color = pieColors[i % pieColors.length];
                lhtml += '<div class="pie-legend-item" title="' + escapeHtml(d.name) + ': ' + days + ' days (' + Math.round(d.hours) + 'h) across ' + d.tasks + ' task(s)">';
                lhtml += '<span class="pie-legend-dot" style="background:' + color + ';"></span>';
                lhtml += escapeHtml(d.name);
                lhtml += '<span class="pie-legend-value">' + days + 'd</span>';
                lhtml += '<span style="color:#cbd5e0;font-size:0.7rem;">(' + pct + '%)</span>';
                lhtml += '</div>';
            }
            legendEl.innerHTML = lhtml;
        }

        // Hover
        canvas.addEventListener('mousemove', function(e) {
            var rect = canvas.getBoundingClientRect();
            var mx = e.clientX - rect.left, my = e.clientY - rect.top;
            var dx = mx - cx, dy = my - cy;
            var dist = Math.sqrt(dx * dx + dy * dy);
            if (dist < innerR || dist > outerR) { canvas.style.cursor = 'default'; canvas.title = ''; return; }
            var angle = Math.atan2(dy, dx);
            if (angle < -Math.PI / 2) angle += 2 * Math.PI;
            var cum = -Math.PI / 2;
            for (var i = 0; i < data.length; i++) {
                var sa = (data[i].hours / totalHours) * 2 * Math.PI;
                if (angle >= cum && angle < cum + sa) {
                    var days = Math.round(data[i].hours / 8 * 10) / 10;
                    canvas.title = data[i].name + ': ' + days + ' days (' + Math.round(data[i].hours) + 'h)';
                    canvas.style.cursor = 'pointer';
                    return;
                }
                cum += sa;
            }
        });
    }

    function renderTasksList(data) {
        if (!Array.isArray(data)) data = [];
        var el = document.getElementById('timeTasksList');
        if (!el) return;
        if (data.length === 0) {
            el.innerHTML = '<div style="text-align:center;padding:24px;color:#a0aec0;font-size:0.85rem;">No time data for this period</div>';
            return;
        }

        var maxHours = data[0].hours;
        var html = '';
        for (var i = 0; i < data.length; i++) {
            var t = data[i];
            var days = Math.round(t.hours / 8 * 10) / 10;
            var pct = Math.round(t.hours / maxHours * 100);
            html += '<div class="time-task-row">';
            html += '<div class="time-task-rank' + (i < 3 ? ' top' : '') + '">' + (i + 1) + '</div>';
            html += '<div class="time-task-info">';
            html += '<a class="time-task-title" href="edit_task.php?task_id=' + t.task_id + '" title="' + escapeHtml(t.title) + '">' + escapeHtml(truncate(t.title, 50)) + '</a>';
            if (t.people) html += '<div class="time-task-person">' + escapeHtml(t.people) + '</div>';
            html += '</div>';
            html += '<div class="time-task-hours">' + days + 'd</div>';
            html += '<div class="time-task-bar-bg"><div class="time-task-bar-fill" style="width:' + pct + '%;"></div></div>';
            html += '</div>';
        }
        el.innerHTML = html;
    }

    // ==================== Inline Add Card ====================
    var kanbanProjectId = <?php echo (int)$project_id; ?>;

    function kanbanShowAddForm(btn) {
        var area = btn.closest('.kanban-add-area');
        btn.style.display = 'none';
        var form = area.querySelector('.kanban-add-form');
        form.style.display = '';
        var input = form.querySelector('.kanban-add-input');
        input.value = '';
        input.focus();
    }

    function kanbanCancelAdd(btn) {
        var area = btn.closest('.kanban-add-area');
        area.querySelector('.kanban-add-form').style.display = 'none';
        area.querySelector('.kanban-add-btn').style.display = '';
    }

    function kanbanSubmitCard(btn) {
        var area = btn.closest('.kanban-add-area');
        var input = area.querySelector('.kanban-add-input');
        var title = input.value.trim();
        if (!title) { input.focus(); return; }

        var statusId = area.dataset.statusId || 7;
        var colKey = area.dataset.colKey;
        var submitBtn = area.querySelector('.kanban-add-submit');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Adding...';

        var formData = new FormData();
        formData.append('action', 'quick_add_task');
        formData.append('task_title', title);
        formData.append('project_id', kanbanProjectId);
        formData.append('status_id', statusId);

        fetch('ajax_responder.php', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Add Task';
            if (data.success && data.task) {
                var t = data.task;
                // Build card HTML
                var card = document.createElement('div');
                card.className = 'kanban-card';
                card.draggable = true;
                card.dataset.taskId = t.task_id;
                card.dataset.taskTitle = t.task_title;
                card.dataset.statusId = t.status_id;
                card.dataset.periodic = '0';
                card.dataset.closed = '0';
                var statusClass = 'status-new';
                if (t.status_id == 1) statusClass = 'status-in-progress';
                else if (t.status_id == 2 || t.status_id == 8) statusClass = 'status-on-hold';
                card.innerHTML =
                    '<div class="kanban-card-title"><a href="edit_task.php?task_id=' + t.task_id + '" onclick="event.stopPropagation()">' + escapeHtml(t.task_title) + '</a></div>' +
                    '<div class="kanban-card-meta"></div>' +
                    '<div class="kanban-card-footer">' +
                        (t.initials ? '<div class="kanban-card-avatar" title="' + escapeHtml(t.responsible_name) + '">' + escapeHtml(t.initials) + '</div>' : '<div></div>') +
                        '<span class="kanban-card-badge ' + statusClass + '">' + escapeHtml(t.status_desc) + '</span>' +
                    '</div>';

                // Insert card in the column
                var col = area.closest('.kanban-column');
                var cardsContainer = col.querySelector('.kanban-cards');
                var emptyEl = cardsContainer.querySelector('.kanban-empty');
                if (emptyEl) emptyEl.remove();
                cardsContainer.appendChild(card);

                // Update count
                var countEl = col.querySelector('.kanban-column-count');
                if (countEl) countEl.textContent = cardsContainer.querySelectorAll('.kanban-card').length;

                // Bind context menu to new card
                if (typeof window.bindCardContextMenu === 'function') {
                    window.bindCardContextMenu(card);
                }

                // Clear input for next card
                input.value = '';
                input.focus();

                // Scroll to the new card
                card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

                showKanbanToast2('Task added');
            } else {
                showKanbanToast2(data.error || 'Failed to add card', true);
            }
        })
        .catch(function() {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Add Task';
            showKanbanToast2('Network error', true);
        });
    }

    // Handle Enter key in add-card textarea
    document.querySelectorAll('.kanban-add-input').forEach(function(input) {
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                var submitBtn = this.closest('.kanban-add-form').querySelector('.kanban-add-submit');
                kanbanSubmitCard(submitBtn);
            }
            if (e.key === 'Escape') {
                var cancelBtn = this.closest('.kanban-add-form').querySelector('.kanban-add-cancel');
                kanbanCancelAdd(cancelBtn);
            }
        });
    });

    </script>
</body>
</html>
