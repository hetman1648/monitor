<?php
include("./includes/common.php");
include("./includes/date_functions.php");

CheckSecurity(3);

if (getsessionparam("privilege_id") == 9) {
    header("Location: index.php");
    exit;
}

$session_user_id = GetSessionParam("UserID");
$user_name = GetSessionParam("UserName");

$task_id = GetParam("task_id");
$action = GetParam("action");
$is_viart = GetParam("is_viart");
$report_user_id = GetParam("report_user_id");
$sort = GetParam("sort");

$task_ids = GetParam("task_ids");
$return_url = "report_people.php?report_user_id=" . $report_user_id . ($sort ? "&sort=" . urlencode($sort) : "");

// Handle task actions
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
            $ids = array_filter(array_map('intval', explode(',', $task_ids)));
            $count = 0;
            foreach ($ids as $tid) { if ($tid > 0) { close_task($tid, ""); $count++; } }
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
    case "start":
        if ($task_id) {
            start_task($task_id);
            header("Location: " . $return_url);
            exit;
        }
        break;
    case "stop":
        if ($task_id) {
            stop_task($task_id);
            header("Location: " . $return_url);
            exit;
        }
        break;
}

// Get the user being reported on
$report_user = null;
if ($report_user_id) {
    $sql = "SELECT u.user_id, u.first_name, u.last_name, CONCAT(u.first_name, ' ', u.last_name) AS full_name,
            CONCAT(m.first_name, ' ', m.last_name) AS priority_set_by_name
            FROM users u
            LEFT JOIN users m ON m.user_id = u.priority_set_by
            WHERE u.user_id = " . ToSQL($report_user_id, "integer");
    $db->query($sql);
    if ($db->next_record()) {
        $report_user = array(
            'user_id' => $db->f("user_id"),
            'first_name' => $db->f("first_name"),
            'last_name' => $db->f("last_name"),
            'full_name' => $db->f("full_name"),
            'priority_set_by' => $db->f("priority_set_by_name")
        );
    }
}

if (!$report_user) {
    header("Location: index.php");
    exit;
}

// Status classes
$status_classes = array(
    1 => 'in-progress',
    2 => 'on-hold',
    3 => 'rejected',
    4 => 'done',
    5 => 'question',
    6 => 'answer',
    7 => 'new',
    8 => 'waiting',
    9 => 'reassigned',
    10 => 'bug',
    11 => 'deadline'
);

// Task statuses for context menu / bulk change (limited set)
$allowed_status_ids = array(7, 9, 1, 8, 4, 5, 6);
$task_statuses = array();
$sql = "SELECT status_id, status_desc FROM lookup_tasks_statuses WHERE status_id IN (" . implode(',', $allowed_status_ids) . ") ORDER BY FIELD(status_id, " . implode(',', $allowed_status_ids) . ")";
$db->query($sql);
while ($db->next_record()) {
    $task_statuses[] = array('id' => $db->f("status_id"), 'name' => $db->f("status_desc"));
}

// All active users for reassign
$all_users = array();
$sql = "SELECT user_id, CONCAT(first_name, ' ', last_name) AS full_name FROM users WHERE is_deleted IS NULL ORDER BY first_name";
$db->query($sql);
while ($db->next_record()) {
    $all_users[] = array('id' => $db->f("user_id"), 'name' => $db->f("full_name"));
}

// Priority colors
$priority_colors = array(
    0 => '#48bb78',
    1 => '#f56565',
    2 => '#ed8936',
    3 => '#ecc94b',
    4 => '#48bb78',
    5 => '#38b2ac'
);

// Get user's tasks
$tasks = array();
$total_estimated = 0;
$total_actual = 0;

$order_by = "t.priority_id ASC, t.task_id DESC";
if ($sort == 'project') $order_by = "p.project_title ASC, t.priority_id ASC";
if ($sort == 'status') $order_by = "t.task_status_id ASC, t.priority_id ASC";
if ($sort == 'deadline') $order_by = "t.planed_date ASC, t.priority_id ASC";
if ($sort == 'time') $order_by = "t.actual_hours DESC";

$sql = "SELECT t.*, p.project_title, lt.type_desc, ls.status_desc,
        DATE_FORMAT(t.creation_date, '%d %b %y') AS creation_date_fmt,
        DATE_FORMAT(t.planed_date, '%d %b %y') AS planed_date_fmt,
        IF(t.task_status_id = 1, t.actual_hours + (UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(t.started_time))/3600, t.actual_hours) AS actual_hours_live,
        IF(TO_DAYS(t.planed_date) < TO_DAYS(NOW()) AND t.task_status_id != 4 AND t.task_type_id != 3, 1, 0) AS is_overdue,
        IF(TO_DAYS(t.planed_date) = TO_DAYS(NOW()) AND t.task_status_id != 4, 1, 0) AS is_today
        FROM tasks t
        LEFT JOIN projects p ON p.project_id = t.project_id
        LEFT JOIN lookup_task_types lt ON lt.type_id = t.task_type_id
        LEFT JOIN lookup_tasks_statuses ls ON ls.status_id = t.task_status_id
        WHERE t.responsible_user_id = " . ToSQL($report_user_id, "integer") . "
        AND t.is_closed = 0
        AND t.is_wish = 0
        ORDER BY " . $order_by;

$db->query($sql);
while ($db->next_record()) {
    $actual = $db->f("actual_hours_live");
    $estimated = $db->f("estimated_hours");
    
    $tasks[] = array(
        'task_id' => $db->f("task_id"),
        'task_title' => $db->f("task_title"),
        'project_title' => $db->f("project_title"),
        'priority_id' => $db->f("priority_id"),
        'status_id' => $db->f("task_status_id"),
        'status_desc' => $db->f("status_desc"),
        'type_desc' => $db->f("type_desc"),
        'estimated_hours' => $estimated,
        'actual_hours' => to_hours($actual),
        'completion' => $db->f("completion"),
        'creation_date' => $db->f("creation_date_fmt"),
        'planed_date' => $db->f("planed_date_fmt"),
        'is_overdue' => $db->f("is_overdue"),
        'is_today' => $db->f("is_today"),
        'is_periodic' => ($db->f("task_type_id") == 3),
        'project_id' => $db->f("project_id")
    );
    
    $total_estimated += $estimated;
    $total_actual += $actual;
}

// Get today's time report
$today_tasks = array();
$today_project_summary = array();
$sql = "SELECT tr.*, t.task_title, p.project_title, p.project_id AS pid,
        DATE_FORMAT(tr.started_date, '%H:%i') AS start_time,
        DATE_FORMAT(tr.report_date, '%H:%i') AS end_time
        FROM time_report tr
        LEFT JOIN tasks t ON t.task_id = tr.task_id
        LEFT JOIN projects p ON p.project_id = t.project_id
        WHERE tr.user_id = " . ToSQL($report_user_id, "integer") . "
        AND DATE(tr.started_date) = CURDATE()
        ORDER BY tr.started_date DESC";
$db->query($sql);
$today_total = 0;
while ($db->next_record()) {
    $project_title = $db->f("project_title") ?: 'No Project';
    $spent = $db->f("spent_hours");
    
    $today_tasks[] = array(
        'task_id' => $db->f("task_id"),
        'task_title' => $db->f("task_title"),
        'project_title' => $project_title,
        'project_id' => $db->f("pid"),
        'start_time' => $db->f("start_time"),
        'end_time' => $db->f("end_time"),
        'spent_hours' => $spent
    );
    $today_total += $spent;
    
    // Accumulate by project
    if (!isset($today_project_summary[$project_title])) {
        $today_project_summary[$project_title] = 0;
    }
    $today_project_summary[$project_title] += $spent;
}
// Check for currently running task and add time since started
$running_task_time = 0;
$running_task_info = null;
$sql = "SELECT t.task_id, t.task_title, t.started_time, p.project_title, p.project_id AS pid,
        ((UNIX_TIMESTAMP(NOW())-UNIX_TIMESTAMP(t.started_time))/3600.0000) as running_hours
        FROM tasks t
        LEFT JOIN projects p ON p.project_id = t.project_id
        WHERE t.task_status_id = 1 
        AND t.responsible_user_id = " . ToSQL($report_user_id, "integer") . "
        AND DATE(t.started_time) = CURDATE()
        LIMIT 1";
$db->query($sql);
if ($db->next_record()) {
    $running_task_time = $db->f("running_hours");
    $running_task_info = array(
        'task_id' => $db->f("task_id"),
        'task_title' => $db->f("task_title"),
        'project_title' => $db->f("project_title") ? $db->f("project_title") : 'No Project',
        'project_id' => $db->f("pid"),
        'start_time' => date('H:i', strtotime($db->f("started_time"))),
        'running_hours' => $running_task_time
    );
    $today_total += $running_task_time;
    
    // Add to project summary
    $project_title = $running_task_info['project_title'];
    if (!isset($today_project_summary[$project_title])) {
        $today_project_summary[$project_title] = 0;
    }
    $today_project_summary[$project_title] += $running_task_time;
}

// Sort by hours descending
arsort($today_project_summary);

// Count projects user is involved with this week + assigned tasks
$projects_count = 0;
$sql = "SELECT COUNT(DISTINCT project_id) as cnt FROM (
            SELECT DISTINCT t.project_id FROM time_report tr
            INNER JOIN tasks t ON t.task_id = tr.task_id
            WHERE tr.user_id = " . ToSQL($report_user_id, "integer") . "
            AND tr.started_date >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
            AND t.project_id IS NOT NULL
            UNION
            SELECT DISTINCT project_id FROM tasks 
            WHERE responsible_user_id = " . ToSQL($report_user_id, "integer") . "
            AND is_closed = 0
            AND project_id IS NOT NULL
        ) AS p";
$db->query($sql);
if ($db->next_record()) {
    $projects_count = $db->f("cnt");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($report_user['full_name']); ?> - Tasks Report</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            color: #2d3748;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #2d3748;
        }

        .page-subtitle {
            color: #718096;
            font-size: 0.9rem;
            margin-top: 4px;
        }

        .page-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }

        .stat-label {
            font-size: 0.8rem;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2d3748;
            margin-top: 4px;
        }

        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 24px;
        }

        .card-header {
            padding: 16px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header.light {
            background: #f8f9fa;
            border-bottom: 1px solid #e2e8f0;
        }

        .card-header.light .card-title {
            color: #2d3748;
        }

        .card-title {
            font-weight: 600;
            font-size: 1rem;
            color: #fff;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.9rem;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
        }

        .btn-outline {
            background: #fff;
            border: 1px solid #e2e8f0;
            color: #4a5568;
        }

        .btn-outline:hover {
            background: #f8fafc;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }

        .scroll-table { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { padding: 6px 8px; text-align: left; font-weight: 600; font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.4px; color: #718096; background: #f8fafc; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
        .data-table th.sortable { cursor: pointer; user-select: none; transition: background 0.15s; }
        .data-table th.sortable:hover { background: #edf2f7; color: #667eea; }
        .data-table th.sortable::after { content: '⇅'; margin-left: 4px; opacity: 0.3; font-size: 0.65rem; }
        .data-table th.sortable.sort-asc::after { content: '↑'; opacity: 1; color: #667eea; }
        .data-table th.sortable.sort-desc::after { content: '↓'; opacity: 1; color: #667eea; }
        .data-table th a { color: #718096; text-decoration: none; }
        .data-table th a:hover { color: #667eea; }
        .data-table td { padding: 5px 8px; border-bottom: 1px solid #f1f5f9; color: #4a5568; font-size: 0.8rem; white-space: nowrap; }
        .data-table tr:hover td { background: #f8fafc; }
        .data-table tr.clickable-row { cursor: pointer; }
        .data-table tr.clickable-row:hover td { background: #edf2f7; }
        .data-table tr.overdue td { background: #fff5f5; }
        .data-table tr.overdue:hover td { background: #fed7d7; }
        .data-table tr.today td { background: #fffff0; }
        .data-table tr.today:hover td { background: #fefcbf; }
        .data-table tr.selected td { background: #ebf4ff !important; }
        .text-center { text-align: center !important; }

        .task-title-cell { max-width: 300px; white-space: normal; }
        .task-title-cell a { color: #2d3748; text-decoration: none; font-weight: 500; }
        .task-title-cell a:hover { color: #667eea; }
        .col-project { white-space: normal; max-width: 150px; }

        .priority-badge { display: inline-flex; align-items: center; justify-content: center; width: 20px; height: 20px; border-radius: 50%; color: #fff; font-size: 0.65rem; font-weight: 600; }

        .status-badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 0.68rem; font-weight: 500; white-space: nowrap; }
        .status-in-progress { background: #c6f6d5; color: #276749; }
        .status-on-hold { background: #feebc8; color: #c05621; }
        .status-done { background: #bee3f8; color: #2b6cb0; }
        .status-new, .status-not-started { background: #e2e8f0; color: #4a5568; }
        .status-reassigned { background: #e9d8fd; color: #6b46c1; }
        .status-question { background: #faf089; color: #975a16; }
        .status-bug { background: #fed7d7; color: #c53030; }
        .status-bug-resolved { background: #c6f6d5; color: #276749; }
        .status-rejected { background: #fed7d7; color: #c53030; }
        .status-ready-to-document { background: #bee3f8; color: #2b6cb0; }
        .status-documented { background: #c6f6d5; color: #276749; }

        .task-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }

        .action-link {
            padding: 3px 7px;
            border-radius: 4px;
            font-size: 0.7rem;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.15s;
        }

        .action-start { background: #c6f6d5; color: #276749; }
        .action-start:hover { background: #9ae6b4; }
        .action-stop { background: #fed7d7; color: #c53030; }
        .action-stop:hover { background: #feb2b2; }
        .action-close { background: #e2e8f0; color: #4a5568; }
        .action-close:hover { background: #cbd5e0; }

        .today-card {
            background: #fffff0;
            border: 1px solid #ecc94b;
        }

        .today-card .card-header {
            padding: 10px 16px;
        }

        .today-card .card-title {
            font-size: 0.85rem;
        }

        .today-card .data-table th {
            padding: 6px 10px;
            font-size: 0.7rem;
        }

        .today-card .data-table td {
            padding: 6px 10px;
            font-size: 0.8rem;
        }

        .today-card .data-table td a {
            color: inherit;
            text-decoration: none;
            transition: color 0.2s;
        }

        .today-card .data-table td a:hover {
            color: #667eea;
            text-decoration: underline;
        }

        .project-summary {
            padding: 10px 16px;
            background: #f8f9fa;
            border-top: 1px solid #e2e8f0;
            font-size: 0.8rem;
            color: #4a5568;
        }
        
        .project-summary .total-time {
            float: right;
            font-weight: 600;
            color: #2d3748;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #718096;
        }

        /* Drag and drop styles */
        .drag-handle {
            cursor: grab;
            color: #a0aec0;
            padding: 8px !important;
            width: 30px;
            text-align: center;
        }
        
        .drag-handle:hover {
            color: #4a5568;
        }
        
        .drag-handle:active {
            cursor: grabbing;
        }
        
        .draggable-row.dragging {
            opacity: 0.5;
            background: #e2e8f0;
        }
        
        .draggable-row.drag-over {
            border-top: 2px solid #4299e1;
        }
        
        .priority-set-by {
            font-size: 0.8rem;
            color: #718096;
            margin-left: auto;
        }
        
        .save-priorities-notice {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #4299e1;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: none;
            z-index: 1000;
        }
        
        .save-priorities-notice button {
            background: white;
            color: #4299e1;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            margin-left: 10px;
            cursor: pointer;
            font-weight: 500;
        }
        
        .save-priorities-notice button:hover {
            background: #f7fafc;
        }

        .report-links { display: flex; gap: 16px; flex-wrap: wrap; }
        .report-links a { color: #667eea; text-decoration: none; font-size: 0.85rem; }
        .report-links a:hover { text-decoration: underline; }

        /* View Tabs */
        .view-tabs { display: flex; gap: 4px; background: #e2e8f0; border-radius: 8px; padding: 3px; }
        .view-tab { display: flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 6px; font-size: 0.8rem; font-weight: 500; color: #718096; cursor: pointer; transition: all 0.2s; text-decoration: none; user-select: none; }
        .view-tab:hover { color: #4a5568; background: rgba(255,255,255,0.5); }
        .view-tab.active { background: #fff; color: #667eea; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .view-tab svg { width: 16px; height: 16px; }
        .view-panel { display: none; }
        .view-panel.active { display: block; }

        /* Checkboxes */
        .th-checkbox, .row-checkbox { width: 16px; height: 16px; cursor: pointer; accent-color: #667eea; }
        .data-table tr.selected { background: #ebf4ff !important; }

        /* Bulk Action Bar */
        .bulk-bar { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: #2d3748; color: #fff; padding: 12px 24px; border-radius: 14px; display: none; align-items: center; gap: 16px; z-index: 10010; box-shadow: 0 8px 30px rgba(0,0,0,0.2); font-size: 0.85rem; }
        .bulk-bar.show { display: flex; }
        .bulk-count { font-weight: 600; white-space: nowrap; }
        .bulk-btn { padding: 6px 14px; border-radius: 8px; border: none; cursor: pointer; font-size: 0.8rem; font-weight: 500; transition: all 0.15s; }
        .bulk-btn-close { background: #e53e3e; color: #fff; }
        .bulk-btn-close:hover { background: #c53030; }
        .bulk-btn-status { background: #667eea; color: #fff; }
        .bulk-btn-status:hover { background: #5a67d8; }
        .bulk-btn-reassign { background: #805ad5; color: #fff; }
        .bulk-btn-reassign:hover { background: #6b46c1; }
        .bulk-btn-cancel { background: #4a5568; color: #fff; }
        .bulk-btn-cancel:hover { background: #2d3748; }

        /* Modals */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 10020; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal-box { background: #fff; border-radius: 16px; width: 420px; max-width: 90vw; box-shadow: 0 20px 60px rgba(0,0,0,0.2); overflow: hidden; }
        .modal-header { padding: 16px 20px; color: #fff; font-weight: 600; font-size: 1rem; }
        .modal-header.close { background: linear-gradient(135deg, #e53e3e, #c53030); }
        .modal-header.status { background: linear-gradient(135deg, #667eea, #5a67d8); }
        .modal-header.reassign { background: linear-gradient(135deg, #805ad5, #6b46c1); }
        .modal-body { padding: 20px; }
        .modal-task-name { font-weight: 600; color: #2d3748; margin-bottom: 8px; }
        .modal-message { color: #718096; font-size: 0.9rem; margin-bottom: 16px; }
        .modal-footer { padding: 12px 20px; background: #f8fafc; display: flex; justify-content: flex-end; gap: 10px; }
        .modal-btn { padding: 8px 18px; border-radius: 8px; border: none; cursor: pointer; font-size: 0.85rem; font-weight: 500; }
        .modal-btn-cancel { background: #e2e8f0; color: #4a5568; }
        .modal-btn-confirm { background: #667eea; color: #fff; }
        .modal-btn-confirm.danger { background: #e53e3e; }

        /* Context Menu */
        .context-menu { position: fixed; z-index: 10000; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 8px 30px rgba(0,0,0,0.12), 0 2px 8px rgba(0,0,0,0.06); min-width: 220px; padding: 6px 0; display: none; font-size: 0.85rem; font-family: inherit; }
        .context-menu.show { display: block; }
        .context-menu-item { display: flex; align-items: center; gap: 10px; padding: 8px 16px; cursor: pointer; color: #4a5568; transition: background 0.1s, color 0.1s; white-space: nowrap; }
        .context-menu-item:hover { background: #f7fafc; color: #2d3748; }
        .context-menu-item .ctx-icon { width: 16px; text-align: center; flex-shrink: 0; font-size: 0.9em; }
        .context-menu-item.danger { color: #e53e3e; }
        .context-menu-item.danger:hover { background: #fff5f5; }
        .context-menu-item.muted { color: #a0aec0; font-size: 0.78rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; cursor: default; padding: 6px 16px 2px; }
        .context-menu-item.muted:hover { background: transparent; color: #a0aec0; }
        .context-menu-separator { height: 1px; background: #e2e8f0; margin: 4px 0; }
        .context-menu-submenu { position: relative; }
        .context-menu-submenu > .context-menu-item::after { content: '\203A'; margin-left: auto; font-size: 1.1em; color: #a0aec0; }
        .context-menu-submenu-items { display: none; position: fixed; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 8px 30px rgba(0,0,0,0.12); min-width: 160px; padding: 6px 0; z-index: 10001; max-height: 300px; overflow-y: auto; }
        .context-menu-submenu-items.show { display: block; }

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
        .kanban-card-badge { font-size: 0.68rem; padding: 2px 8px; border-radius: 10px; font-weight: 500; }
        .kanban-card-project { font-size: 0.72rem; color: #718096; font-weight: 500; max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .kanban-card.overdue { border-left-color: #e53e3e; }
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
        .kanban-empty { text-align: center; padding: 24px 12px; color: #a0aec0; font-size: 0.8rem; font-style: italic; }

        /* Drag and drop for Kanban */
        .kanban-card.dragging { opacity: 0.4; transform: rotate(2deg); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
        .kanban-column.drag-over .kanban-cards { background: rgba(102,126,234,0.08); border-radius: 0 0 12px 12px; }
        .kanban-column.drag-over .kanban-column-header { box-shadow: 0 0 0 2px #667eea inset; }
        .kanban-card-drop-indicator { height: 3px; background: #667eea; border-radius: 3px; margin: 4px 0; }
        .kanban-card[draggable="true"] { cursor: grab; }
        .kanban-card[draggable="true"]:active { cursor: grabbing; }

        /* Project Board columns */
        .proj-board .proj-col { min-width: 260px; max-width: 300px; flex: 1 0 260px; }
        .proj-board .kanban-column-title a:hover { text-decoration: underline; }
        .kanban-toast { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); background: #48bb78; color: #fff; padding: 10px 24px; border-radius: 8px; font-size: 0.9rem; font-weight: 500; z-index: 99999; box-shadow: 0 4px 12px rgba(0,0,0,0.15); transition: opacity 0.3s; }
        .kanban-toast.error { background: #e53e3e; }

        /* Flash message */
        .flash-message { position: fixed; top: 80px; left: 50%; transform: translateX(-50%); background: #c6f6d5; color: #276749; padding: 12px 24px; border-radius: 10px; font-weight: 500; z-index: 10020; box-shadow: 0 4px 12px rgba(0,0,0,0.1); animation: flashIn 0.3s ease; }
        @keyframes flashIn { from { opacity: 0; transform: translateX(-50%) translateY(-10px); } to { opacity: 1; transform: translateX(-50%) translateY(0); } }

        .status-answer { background: #c3dafe; color: #3c366b; }
        .status-waiting { background: #feebc8; color: #c05621; }
        .status-deadline { background: #fed7d7; color: #c53030; }

        .completion-bar { width: 40px; height: 5px; background: #e2e8f0; border-radius: 3px; display: inline-block; vertical-align: middle; margin-right: 4px; }
        .completion-fill { display: block; height: 100%; border-radius: 3px; background: #48bb78; }

        /* ===================== RESPONSIVE ===================== */

        @media (max-width: 1024px) {
            .container {
                padding: 16px;
            }

            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 12px;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            .page-header {
                flex-direction: column;
                gap: 10px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }

            .data-table th,
            .data-table td {
                padding: 5px 6px;
                font-size: 0.75rem;
            }

            /* Hide less important columns on tablet */
            .col-est,
            .col-completion {
                display: none;
            }

            .scroll-table {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .card-header {
                flex-wrap: wrap;
                gap: 8px;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 6px;
            }

            .page-header h1,
            .page-title {
                font-size: 1.2rem;
            }

            .page-subtitle {
                font-size: 0.8rem;
            }

            .report-links {
                gap: 10px;
            }

            .report-links a {
                font-size: 0.78rem;
            }

            .priority-set-by {
                font-size: 0.7rem;
                display: none;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }

            .stat-card {
                padding: 10px;
            }

            .stat-value {
                font-size: 1.2rem;
            }

            .stat-label {
                font-size: 0.7rem;
            }

            /* Hide columns on phone: show Task, Status, Actions only */
            .col-project,
            .col-priority,
            .col-actual,
            .col-est,
            .col-completion,
            .col-deadline,
            .col-drag {
                display: none;
            }

            .data-table th,
            .data-table td {
                padding: 6px 6px;
                font-size: 0.78rem;
            }

            /* Action buttons become icons */
            .action-link {
                font-size: 0;
                padding: 4px 8px;
            }

            .action-link.action-start::before {
                content: '▶';
                font-size: 14px;
            }

            .action-link.action-stop::before {
                content: '⏹';
                font-size: 14px;
            }

            .task-actions {
                gap: 4px;
            }

            /* Today's activity table */
            .today-card .data-table th,
            .today-card .data-table td {
                padding: 4px 6px;
                font-size: 0.75rem;
            }

            .today-card .col-start,
            .today-card .col-end {
                display: none;
            }

            .project-summary {
                font-size: 0.75rem;
                padding: 8px 10px;
            }

            .card {
                border-radius: 8px;
            }

            .card-header {
                padding: 10px 12px;
            }

            .card-body {
                padding: 10px;
            }
        }
        /* ==================== DARK MODE ==================== */
        html.dark-mode .stat-card { background: #161b22; box-shadow: 0 2px 8px rgba(0,0,0,0.3); }
        html.dark-mode .stat-label { color: #8b949e; }
        html.dark-mode .stat-value { color: #e2e8f0; }

        html.dark-mode .card { background: #161b22; box-shadow: 0 4px 20px rgba(0,0,0,0.4); }
        html.dark-mode .card-header.light { background: #1c2333; border-bottom-color: #2d333b; }
        html.dark-mode .card-header.light .card-title { color: #e2e8f0; }

        html.dark-mode .today-card { background: #1a1e2a; border-color: #8b7a2b; }
        html.dark-mode .today-card .card-header { background: #1c2333; }
        html.dark-mode .today-card .card-title { color: #ecc94b; }
        html.dark-mode .today-card .data-table th { background: #161b22; color: #8b949e; border-bottom-color: #2d333b; }
        html.dark-mode .today-card .data-table td { color: #cbd5e0; border-bottom-color: #2d333b; }
        html.dark-mode .today-card .data-table td a { color: #cbd5e0; }
        html.dark-mode .today-card .data-table td a:hover { color: #90cdf4; }
        html.dark-mode .today-card .data-table tr:hover td { background: #1c2333; }
        html.dark-mode .today-card .data-table tr[style*="background"] { background: #162415 !important; }

        html.dark-mode .project-summary { background: #1c2333; border-top-color: #2d333b; color: #8b949e; }
        html.dark-mode .project-summary .total-time { color: #e2e8f0; }

        html.dark-mode .data-table th { background: #161b22; color: #8b949e; border-bottom-color: #2d333b; }
        html.dark-mode .data-table th.sortable:hover { background: #1c2333; color: #90cdf4; }
        html.dark-mode .data-table td { color: #cbd5e0; border-bottom-color: #2d333b; }
        html.dark-mode .data-table tr:hover td { background: #1c2333; }
        html.dark-mode .data-table tr.clickable-row:hover td { background: #1c2333; }
        html.dark-mode .data-table tr.overdue td { background: #2a1215; }
        html.dark-mode .data-table tr.overdue:hover td { background: #3b1a1e; }
        html.dark-mode .data-table tr.today td { background: #2a2510; }
        html.dark-mode .data-table tr.today:hover td { background: #3b3415; }
        html.dark-mode .data-table tr.selected td { background: #172a45 !important; }
        html.dark-mode .task-title-cell a { color: #e2e8f0; }
        html.dark-mode .task-title-cell a:hover { color: #90cdf4; }

        html.dark-mode .page-title { color: #e2e8f0; }
        html.dark-mode .page-subtitle { color: #8b949e; }

        html.dark-mode .btn-outline { background: #1c2333; border-color: #2d333b; color: #cbd5e0; }
        html.dark-mode .btn-outline:hover { background: #2d333b; color: #fff; }

        html.dark-mode .action-close { background: #1c2333; color: #cbd5e0; }
        html.dark-mode .action-close:hover { background: #2d333b; color: #fff; }

        html.dark-mode .drag-handle { color: #4a5568; }
        html.dark-mode .drag-handle:hover { color: #8b949e; }
        html.dark-mode .draggable-row.dragging { background: #1c2333; }

        html.dark-mode .view-tabs { background: #1c2333; }
        html.dark-mode .view-tab { color: #8b949e; }
        html.dark-mode .view-tab:hover { color: #e2e8f0; background: rgba(255,255,255,0.05); }
        html.dark-mode .view-tab.active { background: #161b22; color: #90cdf4; box-shadow: 0 1px 3px rgba(0,0,0,0.3); }

        html.dark-mode .report-links a { color: #90cdf4; }

        html.dark-mode .empty-state { color: #8b949e; }
        html.dark-mode .priority-set-by { color: #8b949e; }

        html.dark-mode .completion-bar { background: #2d333b; }

        html.dark-mode .modal-box { background: #161b22; }
        html.dark-mode .modal-body { background: #161b22; }
        html.dark-mode .modal-task-name { color: #e2e8f0; }
        html.dark-mode .modal-message { color: #8b949e; }
        html.dark-mode .modal-footer { background: #1c2333; }
        html.dark-mode .modal-btn-cancel { background: #2d333b; color: #cbd5e0; }

        html.dark-mode .context-menu { background: #161b22; border-color: #2d333b; box-shadow: 0 8px 30px rgba(0,0,0,0.6); }
        html.dark-mode .context-menu-item { color: #cbd5e0; }
        html.dark-mode .context-menu-item:hover { background: #1c2333; color: #fff; }
        html.dark-mode .context-menu-item.danger { color: #fc8181; }
        html.dark-mode .context-menu-item.danger:hover { background: #2a1215; }
        html.dark-mode .context-menu-item.muted { color: #4a5568; }
        html.dark-mode .context-menu-separator { background: #2d333b; }
        html.dark-mode .context-menu-submenu-items { background: #161b22; border-color: #2d333b; box-shadow: 0 8px 30px rgba(0,0,0,0.6); }

        html.dark-mode .kanban-column { background: #0d1117; }
        html.dark-mode .kanban-column-count { background: rgba(0,0,0,0.3); color: #8b949e; }
        html.dark-mode .kanban-card { background: #161b22; box-shadow: 0 1px 3px rgba(0,0,0,0.5); }
        html.dark-mode .kanban-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.6); }
        html.dark-mode .kanban-card-title { color: #e2e8f0; }
        html.dark-mode .kanban-card-title a { color: #e2e8f0; }
        html.dark-mode .kanban-card-title a:hover { color: #90cdf4; }
        html.dark-mode .kanban-card-meta { color: #8b949e; }
        html.dark-mode .kanban-card-footer { border-top-color: #2d333b; }
        html.dark-mode .kanban-card-project { color: #8b949e; }
        html.dark-mode .kanban-empty { color: #4a5568; }
        html.dark-mode .kanban-col-new .kanban-column-header { background: #1c2333; }
        html.dark-mode .kanban-col-new .kanban-column-title { color: #e2e8f0; }
        html.dark-mode .kanban-col-progress .kanban-column-header { background: #1a3a2a; }
        html.dark-mode .kanban-col-progress .kanban-column-title { color: #c6f6d5; }
        html.dark-mode .kanban-col-hold .kanban-column-header { background: #3a2a0a; }
        html.dark-mode .kanban-col-hold .kanban-column-title { color: #feebc8; }
        html.dark-mode .kanban-col-review .kanban-column-header { background: #2a1a3a; }
        html.dark-mode .kanban-col-review .kanban-column-title { color: #e9d8fd; }
        html.dark-mode .kanban-col-done .kanban-column-header { background: #172a45; }
        html.dark-mode .kanban-col-done .kanban-column-title { color: #bee3f8; }
        html.dark-mode .kanban-column.drag-over .kanban-cards { background: rgba(102,126,234,0.12); }

        html.dark-mode .save-priorities-notice { background: #2b6cb0; }
    </style>
</head>
<body>
    <?php include("./includes/modern_header.php"); ?>

    <?php if (isset($_SESSION['flash_message'])): ?>
    <div class="flash-message"><?php echo $_SESSION['flash_message']['text']; ?></div>
    <script>setTimeout(function(){ var f=document.querySelector('.flash-message'); if(f) f.style.display='none'; }, 3000);</script>
    <?php unset($_SESSION['flash_message']); endif; ?>

    <div class="container">
        <div class="page-header">
            <div>
                <h1 class="page-title"><?php echo htmlspecialchars($report_user['full_name']); ?></h1>
                <p class="page-subtitle"><?php echo count($tasks); ?> open task<?php echo count($tasks) != 1 ? 's' : ''; ?></p>
            </div>
            <div class="page-actions">
                <div class="report-links">
                    <a href="time_report.php?period=1&submit=1&person_selected=<?php echo $report_user_id; ?>">Today's Report</a>
                    <a href="time_report.php?period=2&submit=1&person_selected=<?php echo $report_user_id; ?>">Yesterday</a>
                    <a href="time_report.php?period=3&submit=1&person_selected=<?php echo $report_user_id; ?>">Weekly</a>
                    <a href="time_report.php?period=5&submit=1&person_selected=<?php echo $report_user_id; ?>">Monthly</a>
                </div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Open Tasks</div>
                <div class="stat-value"><?php echo count($tasks); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Estimated</div>
                <div class="stat-value"><?php echo to_hours($total_estimated); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Projects</div>
                <div class="stat-value"><?php echo $projects_count; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Today's Work<?php if ($running_task_info): ?> <span style="font-size: 0.7rem; color: #48bb78;">●</span><?php endif; ?></div>
                <div class="stat-value"><?php echo to_hours($today_total); ?></div>
            </div>
        </div>

        <?php if (!empty($today_tasks) || $running_task_info): ?>
        <div class="card today-card">
            <div class="card-header light">
                <span class="card-title">Today's Activity</span>
            </div>
            <div class="scroll-table">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th>Task</th>
                            <th class="col-start">Start</th>
                            <th class="col-end">End</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($running_task_info): ?>
                        <tr style="background: #f0fff4;">
                            <td><strong><?php if ($running_task_info['project_id']): ?><a href="view_project_tasks.php?project_id=<?php echo $running_task_info['project_id']; ?>" style="color:inherit;text-decoration:none;" onmouseover="this.style.color='#667eea'" onmouseout="this.style.color='inherit'"><?php echo htmlspecialchars($running_task_info['project_title']); ?></a><?php else: ?><?php echo htmlspecialchars($running_task_info['project_title']); ?><?php endif; ?></strong></td>
                            <td><a href="edit_task.php?task_id=<?php echo $running_task_info['task_id']; ?>"><?php echo htmlspecialchars($running_task_info['task_title']); ?></a> <span style="color: #48bb78; font-size: 0.8rem;">● Running</span></td>
                            <td class="col-start"><?php echo $running_task_info['start_time']; ?></td>
                            <td class="col-end"><span style="color: #48bb78;">now</span></td>
                            <td><strong style="color: #48bb78;"><?php echo to_hours($running_task_info['running_hours']); ?></strong></td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach ($today_tasks as $tt): ?>
                        <tr>
                            <td><strong><?php if ($tt['project_id']): ?><a href="view_project_tasks.php?project_id=<?php echo $tt['project_id']; ?>" style="color:inherit;text-decoration:none;" onmouseover="this.style.color='#667eea'" onmouseout="this.style.color='inherit'"><?php echo htmlspecialchars($tt['project_title']); ?></a><?php else: ?><?php echo htmlspecialchars($tt['project_title']); ?><?php endif; ?></strong></td>
                            <td><a href="edit_task.php?task_id=<?php echo $tt['task_id']; ?>"><?php echo htmlspecialchars($tt['task_title']); ?></a></td>
                            <td class="col-start"><?php echo $tt['start_time']; ?></td>
                            <td class="col-end"><?php echo $tt['end_time'] ? $tt['end_time'] : '-'; ?></td>
                            <td><?php echo to_hours($tt['spent_hours']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (!empty($today_project_summary)): ?>
            <div class="project-summary">
                <?php 
                $summary_parts = array();
                foreach ($today_project_summary as $project => $hours) {
                    $summary_parts[] = '<strong>' . htmlspecialchars($project) . '</strong>: ' . to_hours($hours);
                }
                echo implode(', ', $summary_parts);
                ?>
                <span class="total-time">Total: <?php echo to_hours($today_total); ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="card" id="tasksCard">
            <div class="card-header light" id="tasksCardHeader" style="flex-wrap:wrap;gap:12px;">
                <span class="card-title">Tasks (<?php echo count($tasks); ?>)</span>
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                    <span class="priority-set-by" id="prioritySetBy"><?php if ($report_user['priority_set_by']): ?>Priorities set by: <?php echo htmlspecialchars($report_user['priority_set_by']); ?><?php endif; ?></span>
                    <div class="view-tabs">
                        <a class="view-tab" data-view="list" onclick="switchView('list')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                            List
                        </a>
                        <a class="view-tab active" data-view="cards" onclick="switchView('cards')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                            By Status
                        </a>
                        <a class="view-tab" data-view="projectBoard" onclick="switchView('projectBoard')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                            By Project
                        </a>
                    </div>
                </div>
            </div>

            <!-- ==================== LIST VIEW ==================== -->
            <div class="view-panel" id="viewList">
            <?php if (!empty($tasks)): ?>
            <div class="scroll-table">
                <table class="data-table" id="tasksTable">
                    <thead>
                        <tr>
                            <th style="width:30px;"><input type="checkbox" class="th-checkbox" id="selectAll"></th>
                            <th style="width:30px;" class="col-drag"></th>
                            <th class="sortable col-project" data-sort="text" data-col="2">Project</th>
                            <th class="sortable" data-sort="text" data-col="3">Task</th>
                            <th class="text-center sortable col-priority" data-sort="number" data-col="4">Pr</th>
                            <th class="text-center sortable" data-sort="text" data-col="5">Status</th>
                            <th class="sortable col-actual" data-sort="time" data-col="6">Actual</th>
                            <th class="text-center sortable col-est" data-sort="time" data-col="7">Est.</th>
                            <th class="text-center sortable col-completion" data-sort="number" data-col="8">%</th>
                            <th class="text-center sortable col-deadline" data-sort="date" data-col="9">Deadline</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="tasksTableBody">
                        <?php foreach ($tasks as $task): ?>
                        <tr class="clickable-row draggable-row <?php echo $task['is_overdue'] ? 'overdue' : ''; ?> <?php echo $task['is_today'] ? 'today' : ''; ?>" data-task-id="<?php echo $task['task_id']; ?>" data-task-title="<?php echo htmlspecialchars($task['task_title'], ENT_QUOTES); ?>">
                            <td onclick="event.stopPropagation()"><input type="checkbox" class="row-checkbox" value="<?php echo $task['task_id']; ?>"></td>
                            <td class="drag-handle col-drag" onclick="event.stopPropagation();">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="8" y1="6" x2="16" y2="6"></line>
                                    <line x1="8" y1="12" x2="16" y2="12"></line>
                                    <line x1="8" y1="18" x2="16" y2="18"></line>
                                </svg>
                            </td>
                            <td class="col-project"><strong><?php if ($task['project_id']): ?><a href="view_project_tasks.php?project_id=<?php echo $task['project_id']; ?>" style="color:inherit;text-decoration:none;" onmouseover="this.style.color='#667eea'" onmouseout="this.style.color='inherit'" onclick="event.stopPropagation()"><?php echo htmlspecialchars($task['project_title']); ?></a><?php else: ?><?php echo htmlspecialchars($task['project_title']); ?><?php endif; ?></strong></td>
                            <td>
                                <?php echo htmlspecialchars($task['task_title']); ?>
                                <?php if ($task['is_periodic']): ?><span style="color: #718096; font-size: 0.75rem;">(periodic)</span><?php endif; ?>
                            </td>
                            <td class="text-center col-priority">
                                <span class="priority-badge" style="background: <?php echo isset($priority_colors[$task['priority_id']]) ? $priority_colors[$task['priority_id']] : '#718096'; ?>">
                                    <?php echo $task['priority_id']; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php $status_class = isset($status_classes[$task['status_id']]) ? 'status-' . $status_classes[$task['status_id']] : 'status-new'; ?>
                                <span class="status-badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($task['status_desc']); ?></span>
                            </td>
                            <td class="col-actual"><?php echo $task['actual_hours']; ?></td>
                            <td class="text-center col-est"><?php echo $task['estimated_hours'] ?: '-'; ?></td>
                            <td class="text-center col-completion">
                                <?php
                                $comp = (isset($task['completion']) && $task['completion'] !== '' && is_numeric($task['completion'])) ? (int)$task['completion'] : null;
                                if ($comp !== null): ?>
                                <span class="completion-bar"><span class="completion-fill" style="width:<?php echo min($comp, 100); ?>%"></span></span>
                                <?php echo $comp; ?>%
                                <?php else: ?>-
                                <?php endif; ?>
                            </td>
                            <td class="text-center col-deadline"><?php echo $task['planed_date'] ?: '-'; ?></td>
                            <td onclick="event.stopPropagation()">
                                <div class="task-actions">
                                    <?php if ($task['status_id'] == 1): ?>
                                    <a href="report_people.php?action=stop&task_id=<?php echo $task['task_id']; ?>&report_user_id=<?php echo $report_user_id; ?>" class="action-link action-stop" onclick="return confirm('Stop this task?')">Stop</a>
                                    <?php else: ?>
                                    <a href="report_people.php?action=start&task_id=<?php echo $task['task_id']; ?>&report_user_id=<?php echo $report_user_id; ?>" class="action-link action-start" onclick="return confirm('Start this task?')">Start</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state"><p>No open tasks for this user</p></div>
            <?php endif; ?>
            </div><!-- /viewList -->

            <!-- ==================== CARDS VIEW ==================== -->
            <div class="view-panel active" id="viewCards">
            <?php
            $kanban_columns = array(
                'new' => array('title' => 'New', 'statuses' => array(7, 5, 6), 'primary_status' => 7, 'color_class' => 'kanban-col-new', 'tasks' => array()),
                'progress' => array('title' => 'In Progress', 'statuses' => array(1, 11), 'primary_status' => 1, 'color_class' => 'kanban-col-progress', 'tasks' => array()),
                'hold' => array('title' => 'On Hold / Waiting', 'statuses' => array(2, 8, 9), 'primary_status' => 2, 'color_class' => 'kanban-col-hold', 'tasks' => array()),
                'review' => array('title' => 'Review / Bugs', 'statuses' => array(10, 3, 12, 13, 14), 'primary_status' => 10, 'color_class' => 'kanban-col-review', 'tasks' => array()),
                'done' => array('title' => 'Done', 'statuses' => array(4), 'primary_status' => 4, 'color_class' => 'kanban-col-done', 'tasks' => array()),
            );
            foreach ($tasks as $task) {
                $placed = false;
                foreach ($kanban_columns as $key => &$col) {
                    if (in_array($task['status_id'], $col['statuses'])) { $col['tasks'][] = $task; $placed = true; break; }
                }
                unset($col);
                if (!$placed) $kanban_columns['new']['tasks'][] = $task;
            }
            $keep_always = array('new', 'progress', 'done');
            foreach ($kanban_columns as $key => $col) {
                if (empty($col['tasks']) && !in_array($key, $keep_always)) unset($kanban_columns[$key]);
            }
            // Build status-to-column map for JS
            $status_to_col = array();
            foreach ($kanban_columns as $ckey => $cval) {
                foreach ($cval['statuses'] as $sid) $status_to_col[$sid] = $ckey;
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
                            $card_class = 'kanban-card';
                            if ($task['is_overdue']) $card_class .= ' overdue';
                            $status_class = isset($status_classes[$task['status_id']]) ? 'status-' . $status_classes[$task['status_id']] : 'status-new';
                        ?>
                        <div class="<?php echo $card_class; ?>" draggable="true" data-task-id="<?php echo $task['task_id']; ?>" data-task-title="<?php echo htmlspecialchars($task['task_title'], ENT_QUOTES); ?>" data-status-id="<?php echo $task['status_id']; ?>">
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
                                <span class="kanban-card-meta-item" <?php echo $task['is_overdue'] ? 'style="color:#e53e3e;font-weight:600;"' : ''; ?>>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                    <?php echo $task['planed_date']; ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="kanban-card-footer">
                                <span class="kanban-card-project"><?php echo htmlspecialchars($task['project_title']); ?></span>
                                <span class="kanban-card-badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($task['status_desc']); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            </div><!-- /viewCards -->

            <!-- ==================== PROJECT BOARD VIEW ==================== -->
            <div class="view-panel" id="viewProjectBoard">
            <?php
            // Group tasks by project
            $proj_board = array();
            foreach ($tasks as $task) {
                $pid = $task['project_id'] ?: 0;
                $ptitle = $task['project_title'] ?: 'No Project';
                if (!isset($proj_board[$pid])) {
                    $proj_board[$pid] = array('title' => $ptitle, 'id' => $pid, 'tasks' => array());
                }
                $proj_board[$pid]['tasks'][] = $task;
            }
            // Sort by project name
            uasort($proj_board, function($a, $b) { return strcasecmp($a['title'], $b['title']); });
            // Cycle through colors for project columns
            $proj_colors = array(
                array('bg' => '#ebf4ff', 'text' => '#2b6cb0'),
                array('bg' => '#c6f6d5', 'text' => '#276749'),
                array('bg' => '#feebc8', 'text' => '#c05621'),
                array('bg' => '#e9d8fd', 'text' => '#6b46c1'),
                array('bg' => '#fed7d7', 'text' => '#c53030'),
                array('bg' => '#bee3f8', 'text' => '#2a4365'),
                array('bg' => '#c6f6d5', 'text' => '#22543d'),
                array('bg' => '#fefcbf', 'text' => '#975a16'),
                array('bg' => '#e2e8f0', 'text' => '#4a5568'),
                array('bg' => '#fed7e2', 'text' => '#97266d'),
            );
            $color_idx = 0;
            ?>
            <div class="kanban-board proj-board">
                <?php foreach ($proj_board as $pid => $proj):
                    $pc = $proj_colors[$color_idx % count($proj_colors)];
                    $color_idx++;
                ?>
                <div class="kanban-column proj-col" data-project-id="<?php echo $pid; ?>">
                    <div class="kanban-column-header" style="background:<?php echo $pc['bg']; ?>;">
                        <span class="kanban-column-title" style="color:<?php echo $pc['text']; ?>;text-transform:none;letter-spacing:0;font-size:0.82rem;">
                            <?php if ($pid): ?><a href="view_project_tasks.php?project_id=<?php echo $pid; ?>" style="color:inherit;text-decoration:none;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'"><?php echo htmlspecialchars($proj['title']); ?></a><?php else: ?><?php echo htmlspecialchars($proj['title']); ?><?php endif; ?>
                        </span>
                        <span class="kanban-column-count" style="color:<?php echo $pc['text']; ?>;"><?php echo count($proj['tasks']); ?></span>
                    </div>
                    <div class="kanban-cards">
                        <?php foreach ($proj['tasks'] as $task):
                            $card_class = 'kanban-card';
                            if ($task['is_overdue']) $card_class .= ' overdue';
                            $s_class = isset($status_classes[$task['status_id']]) ? 'status-' . $status_classes[$task['status_id']] : 'status-new';
                        ?>
                        <div class="<?php echo $card_class; ?>" data-task-id="<?php echo $task['task_id']; ?>" data-task-title="<?php echo htmlspecialchars($task['task_title'], ENT_QUOTES); ?>" data-status-id="<?php echo $task['status_id']; ?>">
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
                                <span class="kanban-card-meta-item" <?php echo $task['is_overdue'] ? 'style="color:#e53e3e;font-weight:600;"' : ''; ?>>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                    <?php echo $task['planed_date']; ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="kanban-card-footer">
                                <span class="kanban-card-badge <?php echo $s_class; ?>"><?php echo htmlspecialchars($task['status_desc']); ?></span>
                                <?php if ($task['priority_id'] && $task['priority_id'] <= 7): ?>
                                <span style="font-size:0.68rem;color:#718096;">P<?php echo $task['priority_id']; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            </div><!-- /viewProjectBoard -->
        </div>
    </div>

    <!-- Bulk Action Bar -->
    <div class="bulk-bar" id="bulkBar">
        <span class="bulk-count"><span id="bulkCount">0</span> selected</span>
        <button class="bulk-btn bulk-btn-close" onclick="bulkClose()">Close</button>
        <button class="bulk-btn bulk-btn-status" onclick="bulkChangeStatus()">Change Status</button>
        <button class="bulk-btn bulk-btn-reassign" onclick="bulkReassign()">Reassign</button>
        <button class="bulk-btn bulk-btn-cancel" onclick="clearSelection()">Cancel</button>
    </div>

    <!-- Close Modal -->
    <div class="modal-overlay" id="closeModal">
        <div class="modal-box">
            <div class="modal-header close" id="closeModalTitle">Close Task</div>
            <div class="modal-body">
                <p class="modal-task-name" id="closeModalTask"></p>
                <p class="modal-message" id="closeModalMsg">This task will be marked as closed.</p>
            </div>
            <div class="modal-footer">
                <button class="modal-btn modal-btn-cancel" onclick="hideModal('closeModal')">Cancel</button>
                <button class="modal-btn modal-btn-confirm danger" onclick="executeClose()">Close Task</button>
            </div>
        </div>
    </div>

    <!-- Status Change Modal -->
    <div class="modal-overlay" id="statusModal">
        <div class="modal-box">
            <div class="modal-header status">Change Status</div>
            <div class="modal-body">
                <p class="modal-message">Select new status:</p>
                <select id="statusSelect" style="width:100%;padding:10px;border:1px solid #e2e8f0;border-radius:8px;font-size:0.9rem;">
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
    <div class="modal-overlay" id="reassignModal">
        <div class="modal-box">
            <div class="modal-header reassign">Reassign Tasks</div>
            <div class="modal-body">
                <p class="modal-message">Select user to reassign to:</p>
                <select id="reassignSelect" style="width:100%;padding:10px;border:1px solid #e2e8f0;border-radius:8px;font-size:0.9rem;">
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
    
    <div class="save-priorities-notice" id="savePrioritiesNotice">
        Priorities changed
        <button onclick="savePriorities()">Save</button>
    </div>
    
    <script>
    var reportUserId = <?php echo $report_user_id; ?>;
    var baseUrl = 'report_people.php?report_user_id=' + reportUserId<?php echo $sort ? " + '&sort=" . addslashes($sort) . "'" : ""; ?>;

    // ==================== Priority Drag & Drop (List rows) ====================
    var draggedRow = null;
    var prioritiesChanged = false;

    document.addEventListener('DOMContentLoaded', function() {
        var tbody = document.getElementById('tasksTableBody');
        if (!tbody) return;
        var rows = tbody.querySelectorAll('.draggable-row');
        rows.forEach(function(row) {
            var handle = row.querySelector('.drag-handle');
            handle.addEventListener('mousedown', function() { row.draggable = true; });
            row.addEventListener('dragstart', function(e) { draggedRow = this; this.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; });
            row.addEventListener('dragend', function() { this.classList.remove('dragging'); this.draggable = false; draggedRow = null; document.querySelectorAll('.drag-over').forEach(function(el){ el.classList.remove('drag-over'); }); });
            row.addEventListener('dragover', function(e) { e.preventDefault(); if (this !== draggedRow) this.classList.add('drag-over'); });
            row.addEventListener('dragleave', function() { this.classList.remove('drag-over'); });
            row.addEventListener('drop', function(e) {
                e.preventDefault(); this.classList.remove('drag-over');
                if (draggedRow && this !== draggedRow) {
                    var allRows = Array.prototype.slice.call(tbody.querySelectorAll('.draggable-row'));
                    var di = allRows.indexOf(draggedRow), ti = allRows.indexOf(this);
                    if (di < ti) this.parentNode.insertBefore(draggedRow, this.nextSibling);
                    else this.parentNode.insertBefore(draggedRow, this);
                    prioritiesChanged = true; updatePriorityBadges();
                    document.getElementById('savePrioritiesNotice').style.display = 'block';
                }
            });
        });
    });

    function updatePriorityBadges() {
        var rows = document.querySelectorAll('#tasksTableBody .draggable-row');
        var colors = ['#e53e3e','#dd6b20','#d69e2e','#38a169','#3182ce','#805ad5'];
        rows.forEach(function(row, i) {
            var badge = row.querySelector('.priority-badge');
            if (badge) { badge.textContent = i+1; badge.style.background = colors[Math.min(i, colors.length-1)] || '#718096'; }
        });
    }

    function savePriorities() {
        var rows = document.querySelectorAll('#tasksTableBody .draggable-row');
        var tp = [];
        rows.forEach(function(row, i) { tp.push({task_id: row.dataset.taskId, priority: i+1}); });
        fetch('ajax_responder.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:'action=save_task_priorities&user_id='+reportUserId+'&priorities='+encodeURIComponent(JSON.stringify(tp))
        }).then(function(r){return r.json();}).then(function(d) {
            if (d.success) {
                document.getElementById('savePrioritiesNotice').style.display='none'; prioritiesChanged=false;
                var s=document.getElementById('prioritySetBy'); if(s&&d.set_by) s.textContent='Priorities set by: '+d.set_by;
                if (typeof showKanbanToast === 'function') showKanbanToast('Priorities saved successfully'); else alert('Priorities saved.');
            } else {
                if (typeof showKanbanToast === 'function') showKanbanToast('Failed: '+(d.error||'Unknown'), true); else alert('Failed: '+(d.error||'Unknown'));
            }
        }).catch(function(){
            if (typeof showKanbanToast === 'function') showKanbanToast('Failed to save', true); else alert('Failed to save');
        });
    }

    window.addEventListener('beforeunload', function(e) { if(prioritiesChanged){e.preventDefault();e.returnValue='';return '';} });

    // ==================== View Tabs ====================
    function switchView(view) {
        document.querySelectorAll('.view-tab').forEach(function(t) { t.classList.toggle('active', t.dataset.view === view); });
        document.querySelectorAll('.view-panel').forEach(function(p) { p.classList.toggle('active', p.id === 'view' + view.charAt(0).toUpperCase() + view.slice(1)); });
        try { localStorage.setItem('reportPeopleView_' + reportUserId, view); } catch(e) {}
    }
    (function() { try { var s = localStorage.getItem('reportPeopleView_' + reportUserId); if (s && s !== 'cards') switchView(s); } catch(e) {} })();

    // ==================== Row Click ====================
    document.querySelectorAll('tr.clickable-row[data-task-id]').forEach(function(row) {
        row.addEventListener('click', function(e) {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'A' || e.target.closest('.task-actions') || e.target.closest('.drag-handle')) return;
            window.location = 'edit_task.php?task_id=' + this.dataset.taskId;
        });
    });

    // ==================== Kanban Card Click ====================
    document.querySelectorAll('.kanban-card').forEach(function(card) {
        card.addEventListener('click', function(e) {
            if (e.target.tagName === 'A' || card.classList.contains('dragging')) return;
            var tid = card.dataset.taskId;
            if (tid) window.location = 'edit_task.php?task_id=' + tid;
        });
    });

    // ==================== Kanban Drag & Drop ====================
    (function() {
        var draggedCard = null, sourceColumn = null;
        var dropIndicator = document.createElement('div');
        dropIndicator.className = 'kanban-card-drop-indicator';

        document.querySelectorAll('.kanban-card[draggable="true"]').forEach(function(card) {
            card.addEventListener('dragstart', function(e) {
                draggedCard = card; sourceColumn = card.closest('.kanban-column');
                card.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', card.dataset.taskId);
            });
            card.addEventListener('dragend', function() {
                card.classList.remove('dragging'); draggedCard = null; sourceColumn = null;
                document.querySelectorAll('.kanban-column.drag-over').forEach(function(c){c.classList.remove('drag-over');});
                if (dropIndicator.parentNode) dropIndicator.parentNode.removeChild(dropIndicator);
            });
        });

        document.querySelectorAll('.kanban-cards').forEach(function(cc) {
            cc.addEventListener('dragover', function(e) {
                e.preventDefault(); e.dataTransfer.dropEffect = 'move';
                cc.closest('.kanban-column').classList.add('drag-over');
                var after = getAfter(cc, e.clientY);
                if (after) cc.insertBefore(dropIndicator, after); else cc.appendChild(dropIndicator);
            });
            cc.addEventListener('dragleave', function(e) {
                var col = cc.closest('.kanban-column');
                if (!col.contains(e.relatedTarget)) { col.classList.remove('drag-over'); if(dropIndicator.parentNode===cc) cc.removeChild(dropIndicator); }
            });
            cc.addEventListener('drop', function(e) {
                e.preventDefault();
                cc.closest('.kanban-column').classList.remove('drag-over');
                if (!draggedCard) return;
                var col = cc.closest('.kanban-column');
                var targetStatusId = col.dataset.statusId;
                var currentStatusId = draggedCard.dataset.statusId;
                if (dropIndicator.parentNode === cc) cc.insertBefore(draggedCard, dropIndicator);
                else cc.appendChild(draggedCard);
                if (dropIndicator.parentNode) dropIndicator.parentNode.removeChild(dropIndicator);
                var emptyMsg = cc.querySelector('.kanban-empty'); if (emptyMsg) emptyMsg.remove();
                updateColCounts();
                if (targetStatusId && currentStatusId !== targetStatusId) {
                    draggedCard.dataset.statusId = targetStatusId;
                    kanbanMoveAjax(draggedCard.dataset.taskId, targetStatusId, draggedCard, currentStatusId, sourceColumn);
                }
            });
        });

        function getAfter(container, y) {
            var cards = Array.prototype.slice.call(container.querySelectorAll('.kanban-card:not(.dragging)'));
            var result = null, closest = Number.NEGATIVE_INFINITY;
            cards.forEach(function(c) { var b=c.getBoundingClientRect(); var o=y-b.top-b.height/2; if(o<0&&o>closest){closest=o;result=c;} });
            return result;
        }
    })();

    function updateColCounts() {
        document.querySelectorAll('.kanban-column').forEach(function(col) {
            var cnt = col.querySelectorAll('.kanban-card').length;
            var el = col.querySelector('.kanban-column-count'); if(el) el.textContent = cnt;
            var cc = col.querySelector('.kanban-cards');
            if (cnt===0 && !cc.querySelector('.kanban-empty')) { var e=document.createElement('div'); e.className='kanban-empty'; e.textContent='No tasks'; cc.appendChild(e); }
        });
    }

    function kanbanMoveAjax(taskId, newStatusId, card, oldStatusId, oldColumn) {
        var fd = new FormData(); fd.append('action','kanban_move_task'); fd.append('task_id',taskId); fd.append('new_status_id',newStatusId);
        card.style.transition='border-left-color 0.3s'; card.style.borderLeftColor='#667eea';
        fetch('ajax_responder.php',{method:'POST',body:fd})
        .then(function(r){return r.json();}).then(function(d){
            if(d.success){
                var badge=card.querySelector('.kanban-card-badge'); if(badge&&d.new_status_name) badge.textContent=d.new_status_name;
                card.style.borderLeftColor='#48bb78'; setTimeout(function(){card.style.borderLeftColor='';},1500);
                showKanbanToast('Moved to "' + (d.new_status_name||'new status') + '"');
            } else { revertCard(card,oldColumn,oldStatusId); showKanbanToast(d.error||'Failed',true); }
        }).catch(function(){revertCard(card,oldColumn,oldStatusId);showKanbanToast('Network error',true);});
    }

    function revertCard(card,oldCol,oldSid) {
        if(oldCol){var oc=oldCol.querySelector('.kanban-cards');var e=oc.querySelector('.kanban-empty');if(e)e.remove();oc.appendChild(card);card.dataset.statusId=oldSid;updateColCounts();}
    }

    function showKanbanToast(msg,isErr) {
        var ex=document.getElementById('kanbanToast');if(ex)ex.remove();
        var t=document.createElement('div');t.id='kanbanToast';t.className='kanban-toast'+(isErr?' error':'');t.textContent=msg;
        document.body.appendChild(t);setTimeout(function(){t.style.opacity='0';setTimeout(function(){t.remove();},300);},2500);
    }

    // ==================== Checkbox Selection ====================
    function getSelectedIds() {
        var ids = [];
        document.querySelectorAll('.row-checkbox:checked').forEach(function(cb) { ids.push(cb.value); });
        return ids;
    }

    function updateBulkBar() {
        var ids = getSelectedIds();
        var bar = document.getElementById('bulkBar');
        document.getElementById('bulkCount').textContent = ids.length;
        if (ids.length > 0) bar.classList.add('show'); else bar.classList.remove('show');
        document.querySelectorAll('tr.clickable-row').forEach(function(r) {
            var cb = r.querySelector('.row-checkbox');
            r.classList.toggle('selected', cb && cb.checked);
        });
    }

    function clearSelection() {
        document.querySelectorAll('.row-checkbox').forEach(function(cb) { cb.checked = false; });
        var sa = document.getElementById('selectAll'); if(sa) sa.checked = false;
        updateBulkBar();
    }

    var selectAllCb = document.getElementById('selectAll');
    if (selectAllCb) {
        selectAllCb.addEventListener('change', function() {
            var checked = this.checked;
            document.querySelectorAll('.row-checkbox').forEach(function(cb) { cb.checked = checked; });
            updateBulkBar();
        });
    }
    document.querySelectorAll('.row-checkbox').forEach(function(cb) { cb.addEventListener('change', updateBulkBar); });

    // ==================== Modals ====================
    function showModal(id) { document.getElementById(id).classList.add('active'); }
    function hideModal(id) { document.getElementById(id).classList.remove('active'); }
    document.querySelectorAll('.modal-overlay').forEach(function(m) {
        m.addEventListener('click', function(e) { if(e.target===this) this.classList.remove('active'); });
    });
    document.addEventListener('keydown', function(e) { if(e.key==='Escape') document.querySelectorAll('.modal-overlay.active').forEach(function(m){m.classList.remove('active');}); });

    // ==================== Close Actions ====================
    var pendingCloseId = null, pendingCloseIds = null;
    function confirmClose(taskId, taskTitle) {
        pendingCloseId = taskId; pendingCloseIds = null;
        document.getElementById('closeModalTitle').textContent = 'Close Task';
        document.getElementById('closeModalTask').textContent = taskTitle;
        document.getElementById('closeModalMsg').textContent = 'This task will be marked as closed.';
        showModal('closeModal');
    }
    function confirmBulkClose(ids) {
        pendingCloseId = null; pendingCloseIds = ids;
        document.getElementById('closeModalTitle').textContent = 'Close ' + ids.length + ' Task(s)';
        document.getElementById('closeModalTask').textContent = '';
        document.getElementById('closeModalMsg').textContent = ids.length + ' task(s) will be closed.';
        showModal('closeModal');
    }
    function executeClose() {
        if (pendingCloseIds && pendingCloseIds.length) window.location = baseUrl + '&action=close&task_ids=' + pendingCloseIds.join(',');
        else if (pendingCloseId) window.location = baseUrl + '&action=close&task_id=' + pendingCloseId;
        hideModal('closeModal');
    }
    function bulkClose() { var ids = getSelectedIds(); if(ids.length) confirmBulkClose(ids); }
    function bulkChangeStatus() { document.getElementById('statusSelect').value = ''; showModal('statusModal'); }
    function executeStatusChange() {
        var ids = getSelectedIds(); var v = document.getElementById('statusSelect').value;
        if (!v || !ids.length) return;
        window.location = baseUrl + '&action=change_status&new_status=' + v + '&task_ids=' + ids.join(',');
    }
    function bulkReassign() { document.getElementById('reassignSelect').value = ''; showModal('reassignModal'); }
    function executeReassign() {
        var ids = getSelectedIds(); var v = document.getElementById('reassignSelect').value;
        if (!v || !ids.length) return;
        window.location = baseUrl + '&action=reassign&new_user=' + v + '&task_ids=' + ids.join(',');
    }

    // ==================== Context Menu ====================
    (function() {
        var menu = document.createElement('div'); menu.className = 'context-menu'; menu.id = 'taskContextMenu';
        var statusOptions = <?php echo json_encode($task_statuses); ?>;
        var userOptions = <?php echo json_encode($all_users); ?>;
        var statusToCol = <?php echo json_encode($status_to_col); ?>;
        var ctxSource = 'list', ctxTaskId = null, ctxTaskTitle = null;

        function buildMenu() {
            var selectedIds = getSelectedIds();
            var count = (ctxSource === 'kanban') ? 1 : selectedIds.length;
            var isSingle = (ctxSource === 'kanban' || count <= 1);
            var countLabel = count > 1 ? ' (' + count + ')' : '';
            var html = '';
            if (isSingle && ctxTaskTitle) {
                var dt = ctxTaskTitle.length > 50 ? ctxTaskTitle.substring(0,50)+'...' : ctxTaskTitle;
                html += '<div class="context-menu-item muted" style="text-transform:none;letter-spacing:0;font-weight:700;color:#2d3748;font-size:0.82rem;padding:8px 16px 6px;line-height:1.3;">' + escapeHtml(dt) + '</div>';
                html += '<div class="context-menu-separator"></div>';
            } else if (count > 1) {
                html += '<div class="context-menu-item muted" style="text-transform:none;letter-spacing:0;font-weight:700;color:#2d3748;font-size:0.82rem;padding:8px 16px 6px;">' + count + ' tasks selected</div>';
                html += '<div class="context-menu-separator"></div>';
            }
            if (isSingle && ctxTaskId) {
                html += '<div class="context-menu-item" data-action="open"><span class="ctx-icon">🔗</span> Open Task</div>';
                html += '<div class="context-menu-item" data-action="open-new-tab"><span class="ctx-icon">↗</span> Open in New Tab</div>';
                html += '<div class="context-menu-separator"></div>';
            }
            // Change Status submenu
            html += '<div class="context-menu-submenu" id="ctxStatusSubmenu">';
            html += '<div class="context-menu-item" data-action="status-parent"><span class="ctx-icon">⟳</span> Change Status' + countLabel + '</div>';
            html += '<div class="context-menu-submenu-items" id="ctxStatusItems">';
            statusOptions.forEach(function(s) { html += '<div class="context-menu-item" data-action="set-status" data-value="' + s.id + '"><span class="ctx-icon"></span>' + escapeHtml(s.name) + '</div>'; });
            html += '</div></div>';
            // Reassign submenu
            html += '<div class="context-menu-submenu" id="ctxReassignSubmenu">';
            html += '<div class="context-menu-item" data-action="reassign-parent"><span class="ctx-icon">👤</span> Reassign' + countLabel + '</div>';
            html += '<div class="context-menu-submenu-items" id="ctxReassignItems">';
            userOptions.forEach(function(u) { html += '<div class="context-menu-item" data-action="set-user" data-value="' + u.id + '"><span class="ctx-icon"></span>' + escapeHtml(u.name) + '</div>'; });
            html += '</div></div>';
            html += '<div class="context-menu-separator"></div>';
            html += '<div class="context-menu-item danger" data-action="close"><span class="ctx-icon">✕</span> Close Task' + (count>1?'s':'') + countLabel + '</div>';
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

        function showMenuAt(e) {
            menu.innerHTML = buildMenu(); menu.classList.add('show');
            menu.style.left='-9999px'; menu.style.top='-9999px';
            var mr = menu.getBoundingClientRect();
            var x = e.clientX, y = e.clientY;
            if (x+mr.width>window.innerWidth) x=window.innerWidth-mr.width-8;
            if (y+mr.height>window.innerHeight) y=window.innerHeight-mr.height-8;
            if (x<4) x=4; if (y<4) y=4;
            menu.style.left=x+'px'; menu.style.top=y+'px';
            attachSubmenuHandlers();
        }

        // Right-click on list rows
        document.querySelectorAll('tr.clickable-row[data-task-id]').forEach(function(row) {
            row.addEventListener('contextmenu', function(e) {
                e.preventDefault(); e.stopPropagation();
                ctxSource = 'list'; ctxTaskId = this.dataset.taskId; ctxTaskTitle = this.dataset.taskTitle;
                var cb = this.querySelector('.row-checkbox');
                var sel = getSelectedIds();
                if (sel.indexOf(ctxTaskId) === -1) { clearSelection(); if(cb){cb.checked=true;updateBulkBar();} }
                showMenuAt(e);
            });
        });

        // Right-click on kanban cards
        document.querySelectorAll('.kanban-card[data-task-id]').forEach(function(card) {
            card.addEventListener('contextmenu', function(e) {
                e.preventDefault(); e.stopPropagation();
                ctxSource = 'kanban'; ctxTaskId = this.dataset.taskId; ctxTaskTitle = this.dataset.taskTitle;
                showMenuAt(e);
            });
        });

        function attachSubmenuHandlers() {
            menu.querySelectorAll('.context-menu-submenu').forEach(function(sub) {
                var items = sub.querySelector('.context-menu-submenu-items');
                sub.addEventListener('mouseenter', function() {
                    var pr=this.getBoundingClientRect(); items.style.left='-9999px'; items.style.top='-9999px'; items.classList.add('show');
                    var sw=items.offsetWidth,sh=items.offsetHeight,sx=pr.right+2;
                    if(sx+sw>window.innerWidth) sx=pr.left-sw-2; if(sx<0) sx=4;
                    var sy=pr.top; if(sy+sh>window.innerHeight) sy=window.innerHeight-sh-8; if(sy<0) sy=4;
                    items.style.left=sx+'px'; items.style.top=sy+'px';
                });
                sub.addEventListener('mouseleave', function() { items.classList.remove('show'); });
            });
        }

        document.addEventListener('click', function() { closeCtx(); });
        document.addEventListener('keydown', function(e) { if(e.key==='Escape') closeCtx(); });
        window.addEventListener('scroll', function() { closeCtx(); });

        function closeCtx() { menu.classList.remove('show'); menu.querySelectorAll('.context-menu-submenu-items').forEach(function(s){s.classList.remove('show');}); }

        menu.addEventListener('click', function(e) {
            var item = e.target.closest('.context-menu-item'); if(!item) return;
            var action = item.dataset.action;
            if (!action||action==='copy-parent'||action==='status-parent'||action==='reassign-parent') return;
            e.stopPropagation(); closeCtx();
            var isKanban = (ctxSource === 'kanban');
            var selectedIds = isKanban ? [ctxTaskId] : getSelectedIds();
            if (selectedIds.length===0 && ctxTaskId) selectedIds = [ctxTaskId];

            switch(action) {
                case 'open': if(ctxTaskId) window.location='edit_task.php?task_id='+ctxTaskId; break;
                case 'open-new-tab': if(ctxTaskId) window.open('edit_task.php?task_id='+ctxTaskId,'_blank'); break;
                case 'close':
                    if(selectedIds.length>1) confirmBulkClose(selectedIds);
                    else if(selectedIds.length===1) confirmClose(selectedIds[0], ctxTaskTitle);
                    break;
                case 'set-status':
                    var val=item.dataset.value;
                    if(val && selectedIds.length>0) {
                        if(isKanban) { kanbanCtxChangeStatus(ctxTaskId, val); }
                        else { window.location=baseUrl+'&action=change_status&new_status='+val+'&task_ids='+selectedIds.join(','); }
                    }
                    break;
                case 'set-user':
                    var val=item.dataset.value;
                    if(val&&selectedIds.length>0) window.location=baseUrl+'&action=reassign&new_user='+val+'&task_ids='+selectedIds.join(',');
                    break;
                case 'copy-url': copyToClipboard(window.location.origin+'/edit_task.php?task_id='+ctxTaskId,'Task URL copied'); break;
                case 'copy-name': copyToClipboard(ctxTaskTitle,'Task name copied'); break;
                case 'copy-id': copyToClipboard(ctxTaskId,'Task ID copied'); break;
            }
        });

        function kanbanCtxChangeStatus(taskId, newStatusId) {
            var card = document.querySelector('.kanban-card[data-task-id="'+taskId+'"]'); if(!card) return;
            var oldStatusId = card.dataset.statusId, oldCol = card.closest('.kanban-column');
            var targetCol = null;
            document.querySelectorAll('.kanban-column[data-status-id]').forEach(function(c){ if(c.dataset.statusId===String(newStatusId)) targetCol=c; });
            if(!targetCol) { var ck=statusToCol[parseInt(newStatusId)]; if(ck) targetCol=document.querySelector('.kanban-column[data-col-key="'+ck+'"]'); }

            var fd=new FormData(); fd.append('action','kanban_move_task'); fd.append('task_id',taskId); fd.append('new_status_id',newStatusId);
            card.style.transition='border-left-color 0.3s'; card.style.borderLeftColor='#667eea';
            fetch('ajax_responder.php',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
                if(d.success&&d.changed){
                    card.dataset.statusId=newStatusId;
                    var badge=card.querySelector('.kanban-card-badge'); if(badge&&d.new_status_name) badge.textContent=d.new_status_name;
                    if(targetCol&&targetCol!==oldCol){
                        var tc=targetCol.querySelector('.kanban-cards');var em=tc.querySelector('.kanban-empty');if(em)em.remove();
                        tc.appendChild(card); updateColCounts();
                    }
                    card.style.borderLeftColor='#48bb78'; setTimeout(function(){card.style.borderLeftColor='';},1500);
                    showKanbanToast('Moved to "'+(d.new_status_name||'new status')+'"');
                } else { card.style.borderLeftColor=''; }
            }).catch(function(){card.style.borderLeftColor='';showKanbanToast('Network error',true);});
        }

        function copyToClipboard(text, message) {
            navigator.clipboard.writeText(text).then(function(){showToast(message);}).catch(function(){
                var ta=document.createElement('textarea');ta.value=text;ta.style.cssText='position:fixed;opacity:0';
                document.body.appendChild(ta);ta.select();document.execCommand('copy');document.body.removeChild(ta);showToast(message);
            });
        }
        function showToast(message) {
            var t=document.getElementById('copyToast');
            if(!t){t=document.createElement('div');t.id='copyToast';t.style.cssText='position:fixed;bottom:80px;left:50%;transform:translateX(-50%);background:#2d3748;color:#fff;padding:10px 20px;border-radius:8px;font-size:0.85rem;z-index:10020;opacity:0;transition:opacity 0.2s;pointer-events:none;';document.body.appendChild(t);}
            t.textContent=message;t.style.opacity='1';setTimeout(function(){t.style.opacity='0';},1500);
        }
    })();

    function escapeHtml(text) { var d=document.createElement('div');d.appendChild(document.createTextNode(text));return d.innerHTML; }

    // ==================== Table Sorting ====================
    function initTableSort(tableId) {
        var table = document.getElementById(tableId); if(!table) return;
        var headers = table.querySelectorAll('th.sortable');
        headers.forEach(function(h) {
            h.addEventListener('click', function() {
                var col=parseInt(this.dataset.col),st=this.dataset.sort,isAsc=this.classList.contains('sort-asc');
                headers.forEach(function(hh){hh.classList.remove('sort-asc','sort-desc');});
                this.classList.add(isAsc?'sort-desc':'sort-asc');
                sortTable(table,col,st,!isAsc);
            });
        });
    }
    function sortTable(table,col,sortType,asc) {
        var tbody=table.querySelector('tbody');var rows=Array.prototype.slice.call(tbody.querySelectorAll('tr'));
        rows.sort(function(a,b){
            var av=getCellValue(a.cells[col],sortType),bv=getCellValue(b.cells[col],sortType);
            if(av===null&&bv===null)return 0;if(av===null)return 1;if(bv===null)return -1;
            var r;if(sortType==='number'||sortType==='time')r=av-bv;else if(sortType==='date')r=av-bv;else r=av.localeCompare(bv);
            return asc?r:-r;
        });
        rows.forEach(function(r){tbody.appendChild(r);});
    }
    function getCellValue(cell,sortType) {
        var t=cell.textContent.trim();if(t==='-'||t==='')return null;
        if(sortType==='number'){var n=parseFloat(t.replace('%',''));return isNaN(n)?null:n;}
        if(sortType==='time'){var h=0,hm=t.match(/(\d+(?:\.\d+)?)\s*h/),mm=t.match(/(\d+)\s*m/);if(hm)h+=parseFloat(hm[1]);if(mm)h+=parseInt(mm[1])/60;return h||null;}
        if(sortType==='date'){var ms={Jan:0,Feb:1,Mar:2,Apr:3,May:4,Jun:5,Jul:6,Aug:7,Sep:8,Oct:9,Nov:10,Dec:11};var p=t.split(' ');if(p.length>=2){var d=parseInt(p[0]),m=ms[p[1]],y=p[2]?(2000+parseInt(p[2])):new Date().getFullYear();return new Date(y,m,d).getTime();}return null;}
        return t.toLowerCase();
    }
    initTableSort('tasksTable');
    </script>
</body>
</html>
