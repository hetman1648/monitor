<?php
include("./includes/common.php");
CheckSecurity(1);

$user_id = GetSessionParam("UserID");

// Helper: ensure strings are valid UTF-8 for json_encode (PHP 5.6 compat)
function dash_utf8($val) {
    if (is_array($val)) { return array_map('dash_utf8', $val); }
    if (is_string($val) && !mb_check_encoding($val, 'UTF-8')) {
        return mb_convert_encoding($val, 'UTF-8', 'Windows-1252');
    }
    return $val;
}

// Status classes for styling
$status_classes = array(
    1 => 'in-progress', 2 => 'on-hold', 3 => 'rejected', 4 => 'done',
    5 => 'question', 6 => 'answer', 7 => 'new', 8 => 'waiting',
    9 => 'reassigned', 10 => 'bug', 11 => 'deadline', 12 => 'bug-resolved',
    13 => 'ready-to-document', 14 => 'documented'
);

$priority_colors = array(1 => '#48bb78', 2 => '#38b2ac', 3 => '#4299e1', 4 => '#ecc94b', 5 => '#ed8936', 6 => '#fc8181', 7 => '#e53e3e');

// Load all open tasks across all projects the user has access to
$tasks = array();
$sql = "SELECT t.task_id, t.task_title, t.actual_hours, t.estimated_hours, t.completion,
        t.task_status_id, t.is_closed, t.task_type_id, t.priority_id,
        t.creation_date, t.planed_date, t.started_time,
        ls.status_desc,
        p.project_id, p.project_title, p.parent_project_id,
        pp.project_title AS parent_project_title,
        CONCAT(u.first_name, ' ', u.last_name) AS responsible_name,
        u.user_id AS responsible_id,
        DATE_FORMAT(t.creation_date, '%d %b %y') AS creation_date_fmt,
        DATE_FORMAT(t.planed_date, '%d %b %y') AS planed_date_fmt,
        DATE_FORMAT(t.planed_date, '%Y-%m-%d') AS planed_date_iso,
        DATE_FORMAT(t.creation_date, '%Y-%m-%d') AS creation_date_iso
        FROM tasks t
        LEFT JOIN users u ON u.user_id = t.responsible_user_id
        LEFT JOIN lookup_tasks_statuses ls ON ls.status_id = t.task_status_id
        LEFT JOIN projects p ON p.project_id = t.project_id
        LEFT JOIN projects pp ON pp.project_id = p.parent_project_id
        WHERE t.is_closed = 0
        AND t.task_type_id != 3
        AND (p.is_closed IS NULL OR p.is_closed = 0)
        AND t.project_id IN (
            SELECT up.project_id FROM users_projects up WHERE up.user_id = " . ToSQL($user_id, "integer") . "
        )
        ORDER BY p.project_title ASC, t.priority_id ASC, t.task_id DESC";
$db->query($sql);
while ($db->next_record()) {
    $is_overdue = ($db->f("planed_date") != '0000-00-00' && $db->f("planed_date") != '' && strtotime($db->f("planed_date")) < time());
    $tasks[] = array(
        'task_id' => $db->f("task_id"),
        'task_title' => dash_utf8($db->f("task_title")),
        'responsible_id' => $db->f("responsible_id"),
        'responsible_name' => dash_utf8($db->f("responsible_name")),
        'actual_hours' => $db->f("actual_hours"),
        'actual_hours_fmt' => to_hours($db->f("actual_hours")),
        'estimated_hours' => $db->f("estimated_hours"),
        'completion' => $db->f("completion"),
        'planed_date' => $db->f("planed_date_fmt"),
        'planed_date_iso' => $db->f("planed_date_iso"),
        'creation_date' => $db->f("creation_date_fmt"),
        'creation_date_iso' => $db->f("creation_date_iso"),
        'status_id' => $db->f("task_status_id"),
        'status_desc' => dash_utf8($db->f("status_desc")),
        'project_id' => $db->f("project_id"),
        'project_title' => dash_utf8($db->f("project_title")),
        'parent_project_title' => dash_utf8($db->f("parent_project_title")),
        'priority_id' => $db->f("priority_id"),
        'is_overdue' => $is_overdue,
        'is_closed' => $db->f("is_closed"),
    );
}

// Group by project
$by_project = array();
foreach ($tasks as $t) {
    $pid = $t['project_id'];
    if (!isset($by_project[$pid])) {
        $by_project[$pid] = array('title' => $t['project_title'], 'parent' => $t['parent_project_title'], 'id' => $pid, 'tasks' => array());
    }
    $by_project[$pid]['tasks'][] = $t;
}

// Group by status
$status_order = array(7 => 'New', 9 => 'Reassigned', 1 => 'In Progress', 11 => 'Deadline', 8 => 'Waiting', 2 => 'On Hold', 10 => 'Bug', 5 => 'Question', 6 => 'Answer', 4 => 'Done', 3 => 'Rejected');
$by_status = array();
foreach ($tasks as $t) {
    $sid = $t['status_id'];
    if (!isset($by_status[$sid])) {
        $by_status[$sid] = array('title' => $t['status_desc'], 'id' => $sid, 'tasks' => array());
    }
    $by_status[$sid]['tasks'][] = $t;
}
// Sort by status_order
$by_status_sorted = array();
foreach ($status_order as $sid => $sname) {
    if (isset($by_status[$sid])) $by_status_sorted[$sid] = $by_status[$sid];
}
foreach ($by_status as $sid => $data) {
    if (!isset($by_status_sorted[$sid])) $by_status_sorted[$sid] = $data;
}
$by_status = $by_status_sorted;

// Kanban columns for Status Board
$kanban_status_cols = array(
    'new' => array('title' => 'New', 'statuses' => array(7, 5, 6, 9), 'color' => '#e2e8f0', 'text' => '#4a5568', 'tasks' => array()),
    'progress' => array('title' => 'In Progress', 'statuses' => array(1, 11), 'color' => '#c6f6d5', 'text' => '#276749', 'tasks' => array()),
    'waiting' => array('title' => 'Waiting / Hold', 'statuses' => array(2, 8), 'color' => '#feebc8', 'text' => '#c05621', 'tasks' => array()),
    'review' => array('title' => 'Review / Bugs', 'statuses' => array(10, 3, 12, 13, 14), 'color' => '#e9d8fd', 'text' => '#6b46c1', 'tasks' => array()),
    'done' => array('title' => 'Done', 'statuses' => array(4), 'color' => '#bee3f8', 'text' => '#2b6cb0', 'tasks' => array()),
);
foreach ($tasks as $t) {
    $placed = false;
    foreach ($kanban_status_cols as &$col) {
        if (in_array($t['status_id'], $col['statuses'])) { $col['tasks'][] = $t; $placed = true; break; }
    }
    unset($col);
    if (!$placed) $kanban_status_cols['new']['tasks'][] = $t;
}

// Status options for context menu
$ctx_statuses = array();
foreach ($status_order as $sid => $sname) {
    $ctx_statuses[] = array('id' => $sid, 'name' => $sname);
}

// User options for context menu (all users assigned to any project the current user can see)
$ctx_users = array();
$sql = "SELECT DISTINCT u.user_id, CONCAT(u.first_name, ' ', u.last_name) AS user_name
        FROM users u
        INNER JOIN users_projects up ON u.user_id = up.user_id
        WHERE up.project_id IN (
            SELECT up2.project_id FROM users_projects up2 WHERE up2.user_id = " . ToSQL($user_id, "integer") . "
        )
        ORDER BY u.first_name, u.last_name";
$db->query($sql);
while ($db->next_record()) {
    $ctx_users[] = array('id' => (int)$db->f("user_id"), 'name' => dash_utf8($db->f("user_name")));
}

// Group by responsible user
$by_user = array();
foreach ($tasks as $t) {
    $uid = $t['responsible_id'] ?: 0;
    $uname = $t['responsible_name'] ?: 'Unassigned';
    if (!isset($by_user[$uid])) {
        $by_user[$uid] = array('name' => $uname, 'id' => $uid, 'tasks' => array());
    }
    $by_user[$uid]['tasks'][] = $t;
}
// Sort by user name
uasort($by_user, function($a, $b) { return strcasecmp($a['name'], $b['name']); });

// For Timeline/Gantt - JSON data
$gantt_tasks = array();
foreach ($tasks as $t) {
    if ($t['planed_date_iso'] && $t['planed_date_iso'] !== '0000-00-00') {
        $start = $t['creation_date_iso'] ?: date('Y-m-d');
        $end = $t['planed_date_iso'];
        $gantt_tasks[] = array(
            'id' => $t['task_id'],
            'title' => $t['task_title'],
            'project' => $t['project_title'],
            'project_id' => $t['project_id'],
            'start' => $start,
            'end' => $end,
            'completion' => (int)$t['completion'],
            'status' => $t['status_desc'],
            'status_id' => $t['status_id'],
            'responsible' => $t['responsible_name'],
            'is_overdue' => $t['is_overdue'],
            'priority' => $t['priority_id'],
        );
    }
}

// Time by project (monthly aggregation for period filtering)
$time_by_project_raw = array();
$sql = "SELECT p.project_id, p.project_title, pp.project_title AS parent_project_title,
               DATE_FORMAT(tr.started_date, '%Y-%m') AS ym,
               SUM(tr.spent_hours) AS hours
        FROM time_report tr
        JOIN tasks t ON t.task_id = tr.task_id
        JOIN projects p ON p.project_id = t.project_id
        LEFT JOIN projects pp ON pp.project_id = p.parent_project_id
        WHERE t.project_id IN (
            SELECT up.project_id FROM users_projects up WHERE up.user_id = " . ToSQL($user_id, "integer") . "
        )
        AND (p.is_closed IS NULL OR p.is_closed = 0)
        GROUP BY p.project_id, DATE_FORMAT(tr.started_date, '%Y-%m')
        ORDER BY p.project_title, ym";
$db->query($sql);
while ($db->next_record()) {
    $time_by_project_raw[] = array(
        'pid' => (int)$db->f("project_id"),
        'pname' => dash_utf8($db->f("project_title")),
        'parent' => dash_utf8($db->f("parent_project_title")),
        'ym' => $db->f("ym"),
        'hours' => round((float)$db->f("hours"), 2)
    );
}
$time_proj_json = json_encode(dash_utf8($time_by_project_raw));
if ($time_proj_json === false || $time_proj_json === '' || $time_proj_json === null) $time_proj_json = '[]';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks Dashboard - Sayu Monitor</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif; background: #f5f7fa; min-height: 100vh; color: #2d3748; }
        .container { max-width: 1600px; margin: 0 auto; padding: 24px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
        .page-title { font-size: 1.6rem; font-weight: 700; color: #2d3748; }
        .page-stats { font-size: 0.85rem; color: #718096; }

        /* Tabs */
        .dash-tabs { display: flex; gap: 0; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; flex-wrap: wrap; }
        .dash-tab { padding: 10px 22px; font-size: 0.85rem; font-weight: 600; color: #718096; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.15s; display: flex; align-items: center; gap: 6px; white-space: nowrap; }
        .dash-tab:hover { color: #4a5568; }
        .dash-tab.active { color: #667eea; border-bottom-color: #667eea; }
        .dash-tab .tab-count { background: #edf2f7; color: #718096; font-size: 0.7rem; padding: 1px 7px; border-radius: 10px; font-weight: 600; }
        .dash-tab.active .tab-count { background: #ebf4ff; color: #667eea; }
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }

        /* Shared card/table styles */
        .card { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); overflow: hidden; margin-bottom: 16px; }
        .card-header { padding: 14px 20px; display: flex; align-items: center; justify-content: space-between; cursor: pointer; transition: background 0.1s; }
        .card-header:hover { background: #f8fafc; }
        .card-header-title { font-weight: 600; font-size: 0.95rem; color: #2d3748; display: flex; align-items: center; gap: 8px; }
        .card-header-title .arrow { transition: transform 0.2s; color: #a0aec0; font-size: 0.7rem; }
        .card-header-title .arrow.open { transform: rotate(90deg); }
        .card-header-count { font-size: 0.75rem; color: #718096; background: #edf2f7; padding: 2px 10px; border-radius: 10px; }
        .card-header-right { display: flex; align-items: center; gap: 8px; }
        .btn-close-project { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 6px; border: 1px solid #e2e8f0; background: #fff; color: #718096; font-size: 0.7rem; font-weight: 500; cursor: pointer; transition: all 0.15s; font-family: inherit; white-space: nowrap; }
        .btn-close-project:hover { background: #fed7d7; color: #c53030; border-color: #feb2b2; }

        /* Confirm popup */
        .confirm-overlay { position: fixed; inset: 0; z-index: 10000; background: rgba(0,0,0,0.35); display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.15s; }
        .confirm-overlay.show { opacity: 1; }
        .confirm-box { background: #fff; border-radius: 14px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); padding: 28px 32px 24px; width: 380px; max-width: 90vw; transform: translateY(12px) scale(0.97); transition: transform 0.15s; }
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
        .dash-toast { position: fixed; bottom: 20px; right: 20px; z-index: 9999; padding: 12px 20px; border-radius: 10px; font-size: 0.85rem; color: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.15); transition: opacity 0.3s; background: linear-gradient(135deg,#667eea 0%,#764ba2 100%); }
        .card-body { padding: 0; }
        .card.collapsed .card-body { display: none; }

        /* Grouped list tables */
        .g-table { width: 100%; border-collapse: collapse; }
        .g-table th { padding: 8px 16px; text-align: left; font-weight: 600; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; color: #718096; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
        .g-table td { padding: 8px 16px; font-size: 0.85rem; border-bottom: 1px solid #f0f0f0; }
        .g-table tr:hover td { background: #f7fafc; }
        .g-table tr { cursor: pointer; }
        .g-table a { color: #2d3748; text-decoration: none; font-weight: 500; }
        .g-table a:hover { color: #667eea; }

        /* Status badges */
        .status-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 0.68rem; font-weight: 600; white-space: nowrap; }
        .status-new { background: #e2e8f0; color: #4a5568; }
        .status-in-progress { background: #c6f6d5; color: #276749; }
        .status-on-hold { background: #feebc8; color: #c05621; }
        .status-waiting { background: #feebc8; color: #c05621; }
        .status-done { background: #bee3f8; color: #2b6cb0; }
        .status-question { background: #fefcbf; color: #975a16; }
        .status-answer { background: #c6f6d5; color: #276749; }
        .status-reassigned { background: #fed7d7; color: #c53030; }
        .status-bug { background: #fed7d7; color: #c53030; }
        .status-deadline { background: #fed7d7; color: #c53030; }
        .status-rejected { background: #e2e8f0; color: #718096; }
        .status-bug-resolved { background: #c6f6d5; color: #276749; }

        /* Priority dot */
        .priority-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 4px; }

        /* Completion bar */
        .comp-bar { width: 50px; height: 5px; background: #edf2f7; border-radius: 3px; display: inline-block; vertical-align: middle; }
        .comp-bar-fill { height: 100%; border-radius: 3px; background: #667eea; }

        /* Overdue */
        .overdue-text { color: #e53e3e; font-weight: 600; }

        /* Kanban board */
        .kanban-board { display: flex; gap: 14px; overflow-x: auto; padding-bottom: 16px; min-height: 400px; }
        .kanban-col { min-width: 280px; max-width: 320px; flex: 1 0 280px; background: #f4f5f7; border-radius: 12px; display: flex; flex-direction: column; max-height: calc(100vh - 200px); }
        .kanban-col-header { padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; border-radius: 12px 12px 0 0; position: sticky; top: 0; z-index: 1; }
        .kanban-col-title { font-weight: 700; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .kanban-col-count { font-size: 0.7rem; font-weight: 600; padding: 2px 8px; border-radius: 10px; background: rgba(255,255,255,0.5); }
        .kanban-col-cards { padding: 8px 10px 12px; overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 8px; }
        .k-card { background: #fff; border-radius: 8px; padding: 10px 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); cursor: pointer; transition: box-shadow 0.15s, transform 0.15s; border-left: 3px solid transparent; }
        .k-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.12); transform: translateY(-1px); }
        .k-card.overdue { border-left-color: #e53e3e; }
        .k-card-title { font-weight: 600; font-size: 0.82rem; color: #2d3748; margin-bottom: 6px; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .k-card-title a { color: inherit; text-decoration: none; }
        .k-card-title a:hover { color: #667eea; }
        .k-card-meta { display: flex; flex-wrap: wrap; gap: 4px 10px; font-size: 0.72rem; color: #718096; }
        .k-card-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 8px; }
        .k-card-project { font-size: 0.68rem; color: #667eea; background: #ebf4ff; padding: 1px 8px; border-radius: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 180px; }
        .k-card-avatar { width: 22px; height: 22px; border-radius: 50%; background: #667eea; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.6rem; font-weight: 700; }
        .kanban-empty { text-align: center; padding: 30px 10px; color: #a0aec0; font-size: 0.8rem; }

        /* Timeline/Gantt */
        .gantt-container { overflow-x: auto; background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .gantt-header { display: flex; align-items: center; gap: 12px; padding: 14px 20px; border-bottom: 1px solid #e2e8f0; flex-wrap: wrap; }
        .gantt-controls { display: flex; gap: 8px; }
        .gantt-btn { padding: 6px 14px; border-radius: 6px; border: 1px solid #e2e8f0; background: #fff; cursor: pointer; font-size: 0.78rem; font-weight: 500; color: #4a5568; transition: all 0.15s; }
        .gantt-btn:hover { background: #f7fafc; }
        .gantt-btn.active { background: #667eea; color: #fff; border-color: #667eea; }
        .gantt-chart { position: relative; min-height: 300px; }
        .gantt-row { display: flex; align-items: center; border-bottom: 1px solid #f0f0f0; min-height: 32px; }
        .gantt-row:hover { background: #f7fafc; }
        .gantt-label { width: 260px; min-width: 260px; padding: 6px 14px; font-size: 0.78rem; font-weight: 500; color: #2d3748; border-right: 1px solid #e2e8f0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; cursor: pointer; }
        .gantt-label:hover { color: #667eea; }
        .gantt-label small { display: block; font-size: 0.66rem; color: #a0aec0; font-weight: 400; }
        .gantt-timeline { flex: 1; position: relative; height: 32px; }
        .gantt-bar { position: absolute; height: 18px; top: 7px; border-radius: 4px; min-width: 8px; cursor: pointer; transition: opacity 0.15s; }
        .gantt-bar:hover { opacity: 0.85; }
        .gantt-bar-fill { height: 100%; border-radius: 4px; background: rgba(255,255,255,0.3); }
        .gantt-bar-text { position: absolute; left: 8px; top: 2px; font-size: 0.62rem; color: #fff; font-weight: 600; white-space: nowrap; }
        .gantt-today { position: absolute; top: 0; bottom: 0; width: 2px; background: #e53e3e; z-index: 2; }
        .gantt-today::before { content: 'Today'; position: absolute; top: -18px; left: -16px; font-size: 0.6rem; color: #e53e3e; font-weight: 600; }
        .gantt-dates { display: flex; border-bottom: 1px solid #e2e8f0; background: #f8fafc; }
        .gantt-dates-label { width: 260px; min-width: 260px; padding: 6px 14px; font-size: 0.7rem; font-weight: 600; color: #718096; border-right: 1px solid #e2e8f0; }
        .gantt-dates-timeline { flex: 1; display: flex; position: relative; }
        .gantt-date-marker { font-size: 0.62rem; color: #a0aec0; padding: 4px 0; text-align: center; border-right: 1px solid #f0f0f0; }
        .gantt-project-header { padding: 8px 14px; background: #f8fafc; font-weight: 600; font-size: 0.8rem; color: #667eea; border-bottom: 1px solid #e2e8f0; }

        /* Search / filter */
        .search-box { display: flex; align-items: center; gap: 8px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 6px 12px; position: relative; }
        .search-box input { border: none; outline: none; font-size: 0.82rem; width: 180px; font-family: inherit; background: transparent; }
        .search-box svg { color: #a0aec0; flex-shrink: 0; }
        .search-clear { display: none; background: none; border: none; cursor: pointer; color: #a0aec0; padding: 0; font-size: 1.1rem; line-height: 1; transition: color 0.15s; }
        .search-clear:hover { color: #e53e3e; }
        .search-clear.visible { display: block; }

        /* Toolbar row */
        .dash-toolbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; flex-wrap: wrap; gap: 8px; }
        .toolbar-left { display: flex; align-items: center; gap: 6px; }
        .toolbar-btn { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 6px; border: 1px solid #e2e8f0; background: #fff; color: #718096; font-size: 0.72rem; font-weight: 500; cursor: pointer; transition: all 0.15s; font-family: inherit; }
        .toolbar-btn:hover { background: #edf2f7; color: #4a5568; border-color: #cbd5e0; }
        .toolbar-btn svg { width: 12px; height: 12px; }

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
        .context-menu-submenu-items { display: none; position: fixed; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 8px 30px rgba(0,0,0,0.12), 0 2px 8px rgba(0,0,0,0.06); min-width: 160px; padding: 6px 0; z-index: 10001; max-height: 300px; overflow-y: auto; }
        .context-menu-submenu-items.show { display: block; }

        /* Time view */
        .time-view-container { padding: 4px 0; }
        .time-period-bar { display: flex; align-items: center; gap: 6px; margin-bottom: 18px; flex-wrap: wrap; }
        .tp-btn { padding: 5px 14px; border-radius: 6px; border: 1px solid #e2e8f0; background: #fff; color: #718096; font-size: 0.75rem; font-weight: 500; cursor: pointer; transition: all 0.15s; font-family: inherit; }
        .tp-btn:hover { background: #edf2f7; color: #4a5568; border-color: #cbd5e0; }
        .tp-btn.active { background: #667eea; color: #fff; border-color: #667eea; }
        /* Parent project filter */
        .tp-filter-wrap { position: relative; margin-left: 10px; }
        .tp-filter-input { padding: 5px 12px; border-radius: 6px; border: 1px solid #e2e8f0; background: #fff; color: #4a5568; font-size: 0.75rem; font-weight: 500; font-family: inherit; width: 180px; outline: none; transition: border-color 0.15s; }
        .tp-filter-input:focus { border-color: #667eea; }
        .tp-filter-input::placeholder { color: #a0aec0; }
        .tp-filter-dd { display: none; position: absolute; top: 100%; left: 0; margin-top: 4px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 8px 30px rgba(0,0,0,0.12); width: 240px; max-height: 240px; overflow-y: auto; z-index: 50; }
        .tp-filter-dd.show { display: block; }
        .tp-filter-opt { padding: 7px 12px; font-size: 0.78rem; color: #4a5568; cursor: pointer; transition: background 0.1s; }
        .tp-filter-opt:hover, .tp-filter-opt.highlighted { background: #edf2f7; color: #2d3748; }
        .tp-filter-opt.selected { color: #667eea; font-weight: 600; }
        .tp-filter-clear { display: none; position: absolute; right: 6px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #a0aec0; cursor: pointer; font-size: 0.85rem; line-height: 1; padding: 2px 4px; }
        .tp-filter-clear:hover { color: #e53e3e; }
        .time-view-grid { display: grid; grid-template-columns: 340px 1fr; gap: 28px; align-items: start; }
        .time-chart-section { position: relative; }
        .time-section-title { font-size: 0.82rem; font-weight: 600; color: #4a5568; margin: 0 0 12px; }
        .time-chart-wrap { position: relative; width: 280px; height: 280px; margin: 0 auto; }
        .pie-center-text { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; pointer-events: none; }
        .pie-center-total { font-size: 1.6rem; font-weight: 700; color: #2d3748; line-height: 1.2; }
        .pie-center-label { font-size: 0.7rem; color: #a0aec0; }
        .pie-legend { margin-top: 14px; display: flex; flex-wrap: wrap; gap: 6px 14px; justify-content: center; }
        .pie-legend-item { display: flex; align-items: center; gap: 6px; font-size: 0.75rem; color: #4a5568; cursor: default; white-space: nowrap; }
        .pie-legend-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
        .pie-legend-value { font-weight: 600; color: #2d3748; margin-left: 2px; }
        .time-table-section { min-width: 0; }
        .time-projects-list { display: flex; flex-direction: column; gap: 4px; }
        .time-proj-row { display: flex; align-items: center; gap: 10px; padding: 7px 10px; border-radius: 6px; transition: background 0.12s; }
        .time-proj-row:hover { background: #f7fafc; }
        .time-proj-rank { width: 20px; font-size: 0.72rem; color: #a0aec0; text-align: right; flex-shrink: 0; }
        .time-proj-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
        .time-proj-name { flex: 1; min-width: 0; font-size: 0.82rem; color: #2d3748; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .time-proj-name a { color: inherit; text-decoration: none; }
        .time-proj-name a:hover { color: #667eea; text-decoration: underline; }
        .time-proj-parent { font-size: 0.68rem; color: #a0aec0; margin-left: 4px; }
        .time-proj-hours { font-size: 0.78rem; font-weight: 600; color: #4a5568; white-space: nowrap; flex-shrink: 0; }
        .time-proj-bar-wrap { width: 80px; height: 6px; background: #edf2f7; border-radius: 3px; overflow: hidden; flex-shrink: 0; }
        .time-proj-bar { height: 100%; border-radius: 3px; transition: width 0.3s; }

        @media (max-width: 900px) {
            .time-view-grid { grid-template-columns: 1fr; }
            .time-chart-wrap { margin: 0 auto 16px; }
        }

        @media (max-width: 768px) {
            .container { padding: 16px; }
            .kanban-col { min-width: 260px; max-width: 260px; }
            .gantt-label { width: 160px; min-width: 160px; }
        }

        /* Dark mode - project/status group headers */
        html.dark-mode .card-header { background: #1c2333; }
        html.dark-mode .card-header:hover { background: #252d3d; }
        html.dark-mode .card-header-title { color: #f0f4f8 !important; }
        html.dark-mode .card-header-title a { color: #93c5fd !important; }
        html.dark-mode .card-header-title a:hover { color: #60a5fa !important; }
        html.dark-mode .card-header-parent { color: #a0aec0 !important; }
        /* Dark mode - Project Board kanban column titles (project names) */
        html.dark-mode #tabProjectBoard .kanban-col-header { background: #1c2333 !important; }
        html.dark-mode #tabProjectBoard .kanban-col-title { color: #f0f4f8 !important; }
        html.dark-mode #tabProjectBoard .kanban-col-title a { color: #93c5fd !important; }
        html.dark-mode #tabProjectBoard .kanban-col-title a:hover { color: #60a5fa !important; }
        html.dark-mode #tabProjectBoard .kanban-col-count { background: rgba(255,255,255,0.15); color: #e2e8f0; }
        /* Dark mode - User Board kanban column titles */
        html.dark-mode #tabUserBoard .kanban-col-header { background: #1c2333 !important; }
        html.dark-mode #tabUserBoard .kanban-col-title { color: #f0f4f8 !important; }
        html.dark-mode #tabUserBoard .kanban-col-title a { color: #93c5fd !important; }
        html.dark-mode #tabUserBoard .kanban-col-count { background: rgba(255,255,255,0.15); color: #e2e8f0; }
        html.dark-mode .card-header-title .arrow { color: #8b949e; }
        html.dark-mode .card-header-count { background: rgba(255,255,255,0.12); color: #cbd5e0; }
        html.dark-mode .toolbar-btn { background: #1c2333; color: #cbd5e0; border-color: #2d333b; }
        html.dark-mode .toolbar-btn:hover { background: #2d333b; color: #e2e8f0; border-color: #4a5568; }
        html.dark-mode .btn-close-project { background: #2d333b; color: #a0aec0; border-color: #4a5568; }
        html.dark-mode .btn-close-project:hover { background: rgba(220, 53, 69, 0.25); color: #fca5a5; border-color: #dc3545; }

        /* Dark mode for parent filter */
        html.dark-mode .tp-filter-input { background: #1c2333; color: #e2e8f0; border-color: #2d333b; }
        html.dark-mode .tp-filter-input:focus { border-color: #667eea; }
        html.dark-mode .tp-filter-input::placeholder { color: #8b949e; }
        html.dark-mode .tp-filter-dd { background: #161b22; border-color: #2d333b; box-shadow: 0 8px 30px rgba(0,0,0,0.6); }
        html.dark-mode .tp-filter-opt { color: #cbd5e0; }
        html.dark-mode .tp-filter-opt:hover, html.dark-mode .tp-filter-opt.highlighted { background: #1c2333; color: #fff; }
        html.dark-mode .tp-filter-opt.selected { color: #90cdf4; }
        html.dark-mode .tp-filter-clear { color: #8b949e; }
        html.dark-mode .tp-filter-clear:hover { color: #fc8181; }
    </style>
</head>
<body>
    <?php include("./includes/modern_header.php"); ?>

    <div class="container">
        <div class="page-header">
            <div>
                <h1 class="page-title">Tasks Dashboard</h1>
                <span class="page-stats"><?php echo count($tasks); ?> open tasks across <?php echo count($by_project); ?> projects</span>
            </div>
            <div class="search-box">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                <input type="text" id="searchInput" placeholder="Filter by project..." autocomplete="off" oninput="filterTasks()">
                <button type="button" class="search-clear" id="searchClear" onclick="clearSearch()" title="Clear filter">&times;</button>
            </div>
        </div>

        <div class="dash-tabs">
            <div class="dash-tab active" data-tab="byProject" onclick="switchTab('byProject')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                By Project
                <span class="tab-count"><?php echo count($by_project); ?></span>
            </div>
            <div class="dash-tab" data-tab="byStatus" onclick="switchTab('byStatus')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 12l2 2 4-4"/></svg>
                By Status
                <span class="tab-count"><?php echo count($by_status); ?></span>
            </div>
            <div class="dash-tab" data-tab="statusBoard" onclick="switchTab('statusBoard')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                Status Board
            </div>
            <div class="dash-tab" data-tab="projectBoard" onclick="switchTab('projectBoard')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                Project Board
            </div>
            <div class="dash-tab" data-tab="byUser" onclick="switchTab('byUser')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                By User
                <span class="tab-count"><?php echo count($by_user); ?></span>
            </div>
            <div class="dash-tab" data-tab="userBoard" onclick="switchTab('userBoard')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                User Board
            </div>
            <div class="dash-tab" data-tab="timeline" onclick="switchTab('timeline')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                Timeline
                <span class="tab-count"><?php echo count($gantt_tasks); ?></span>
            </div>
            <div class="dash-tab" data-tab="time" onclick="switchTab('time')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Time
            </div>
        </div>

        <div class="dash-toolbar" id="dashToolbar">
            <div class="toolbar-left">
                <button class="toolbar-btn" onclick="collapseAll()" title="Collapse all groups">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="4 14 10 14 10 20"/><polyline points="20 10 14 10 14 4"/><line x1="14" y1="10" x2="21" y2="3"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
                    Collapse All
                </button>
                <button class="toolbar-btn" onclick="expandAll()" title="Expand all groups">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
                    Expand All
                </button>
            </div>
        </div>

        <!-- ==================== BY PROJECT ==================== -->
        <div class="tab-panel active" id="tabByProject">
            <?php foreach ($by_project as $proj): ?>
            <div class="card project-group" data-project="<?php echo htmlspecialchars($proj['title'], ENT_QUOTES); ?>">
                <div class="card-header" onclick="toggleGroup(this)">
                    <span class="card-header-title">
                        <span class="arrow open">&#9654;</span>
                        <a href="view_project_tasks.php?project_id=<?php echo $proj['id']; ?>" onclick="event.stopPropagation()" style="color:inherit;text-decoration:none;"><?php echo htmlspecialchars($proj['title']); ?></a>
                        <?php if ($proj['parent']): ?>
                        <span class="card-header-parent" style="font-size:0.75rem;color:#a0aec0;font-weight:400;">&mdash; <?php echo htmlspecialchars($proj['parent']); ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="card-header-right">
                        <span class="card-header-count"><?php echo count($proj['tasks']); ?> tasks</span>
                        <button class="btn-close-project" onclick="event.stopPropagation(); showCloseProjectConfirm(<?php echo $proj['id']; ?>, <?php echo htmlspecialchars(json_encode(dash_utf8($proj['title'])) ?: '""', ENT_QUOTES); ?>)" title="Close project">&#10005; Close</button>
                    </span>
                </div>
                <div class="card-body">
                    <table class="g-table">
                        <thead><tr><th>Task</th><th>Responsible</th><th>Status</th><th>Time</th><th>%</th><th>Deadline</th></tr></thead>
                        <tbody>
                        <?php foreach ($proj['tasks'] as $t):
                            $sc = isset($status_classes[$t['status_id']]) ? 'status-' . $status_classes[$t['status_id']] : 'status-new';
                        ?>
                        <tr class="task-row" onclick="window.location='edit_task.php?task_id=<?php echo $t['task_id']; ?>'" data-task-id="<?php echo $t['task_id']; ?>" data-title="<?php echo htmlspecialchars($t['task_title'], ENT_QUOTES); ?>" data-responsible="<?php echo htmlspecialchars($t['responsible_name'], ENT_QUOTES); ?>">
                            <td>
                                <span class="priority-dot" style="background:<?php echo isset($priority_colors[$t['priority_id']]) ? $priority_colors[$t['priority_id']] : '#718096'; ?>"></span>
                                <a href="edit_task.php?task_id=<?php echo $t['task_id']; ?>" onclick="event.stopPropagation()"><?php echo htmlspecialchars($t['task_title']); ?></a>
                            </td>
                            <td style="font-size:0.8rem;color:#718096;"><?php echo htmlspecialchars($t['responsible_name']); ?></td>
                            <td><span class="status-badge <?php echo $sc; ?>"><?php echo htmlspecialchars($t['status_desc']); ?></span></td>
                            <td style="font-size:0.8rem;"><?php echo $t['actual_hours_fmt']; ?></td>
                            <td><div class="comp-bar"><div class="comp-bar-fill" style="width:<?php echo $t['completion']; ?>%"></div></div> <span style="font-size:0.72rem;color:#718096;"><?php echo $t['completion']; ?>%</span></td>
                            <td class="<?php echo $t['is_overdue'] ? 'overdue-text' : ''; ?>" style="font-size:0.8rem;"><?php echo $t['planed_date'] ?: '-'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($by_project)): ?>
            <div style="text-align:center;padding:60px;color:#a0aec0;">No open tasks found.</div>
            <?php endif; ?>
        </div>

        <!-- ==================== BY STATUS ==================== -->
        <div class="tab-panel" id="tabByStatus">
            <?php foreach ($by_status as $sid => $group): ?>
            <?php $sc = isset($status_classes[$sid]) ? 'status-' . $status_classes[$sid] : 'status-new'; ?>
            <div class="card status-group">
                <div class="card-header" onclick="toggleGroup(this)">
                    <span class="card-header-title">
                        <span class="arrow open">&#9654;</span>
                        <span class="status-badge <?php echo $sc; ?>" style="font-size:0.78rem;"><?php echo htmlspecialchars($group['title']); ?></span>
                    </span>
                    <span class="card-header-count"><?php echo count($group['tasks']); ?> tasks</span>
                </div>
                <div class="card-body">
                    <table class="g-table">
                        <thead><tr><th>Task</th><th>Project</th><th>Responsible</th><th>Time</th><th>%</th><th>Deadline</th></tr></thead>
                        <tbody>
                        <?php foreach ($group['tasks'] as $t): ?>
                        <tr class="task-row" onclick="window.location='edit_task.php?task_id=<?php echo $t['task_id']; ?>'" data-task-id="<?php echo $t['task_id']; ?>" data-title="<?php echo htmlspecialchars($t['task_title'], ENT_QUOTES); ?>" data-responsible="<?php echo htmlspecialchars($t['responsible_name'], ENT_QUOTES); ?>">
                            <td>
                                <span class="priority-dot" style="background:<?php echo isset($priority_colors[$t['priority_id']]) ? $priority_colors[$t['priority_id']] : '#718096'; ?>"></span>
                                <a href="edit_task.php?task_id=<?php echo $t['task_id']; ?>" onclick="event.stopPropagation()"><?php echo htmlspecialchars($t['task_title']); ?></a>
                            </td>
                            <td style="font-size:0.8rem;"><a href="view_project_tasks.php?project_id=<?php echo $t['project_id']; ?>" onclick="event.stopPropagation()" style="color:#667eea;text-decoration:none;"><?php echo htmlspecialchars($t['project_title']); ?></a></td>
                            <td style="font-size:0.8rem;color:#718096;"><?php echo htmlspecialchars($t['responsible_name']); ?></td>
                            <td style="font-size:0.8rem;"><?php echo $t['actual_hours_fmt']; ?></td>
                            <td><div class="comp-bar"><div class="comp-bar-fill" style="width:<?php echo $t['completion']; ?>%"></div></div></td>
                            <td class="<?php echo $t['is_overdue'] ? 'overdue-text' : ''; ?>" style="font-size:0.8rem;"><?php echo $t['planed_date'] ?: '-'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ==================== STATUS BOARD ==================== -->
        <div class="tab-panel" id="tabStatusBoard">
            <div class="kanban-board">
                <?php foreach ($kanban_status_cols as $col): ?>
                <div class="kanban-col">
                    <div class="kanban-col-header" style="background:<?php echo $col['color']; ?>;">
                        <span class="kanban-col-title" style="color:<?php echo $col['text']; ?>;"><?php echo $col['title']; ?></span>
                        <span class="kanban-col-count"><?php echo count($col['tasks']); ?></span>
                    </div>
                    <div class="kanban-col-cards">
                        <?php if (empty($col['tasks'])): ?>
                        <div class="kanban-empty">No tasks</div>
                        <?php endif; ?>
                        <?php foreach ($col['tasks'] as $t):
                            $initials = '';
                            if ($t['responsible_name']) { $p = explode(' ', trim($t['responsible_name'])); $initials = strtoupper(substr($p[0],0,1) . (isset($p[1]) ? substr($p[1],0,1) : '')); }
                        ?>
                        <div class="k-card <?php echo $t['is_overdue'] ? 'overdue' : ''; ?>" onclick="window.location='edit_task.php?task_id=<?php echo $t['task_id']; ?>'" data-task-id="<?php echo $t['task_id']; ?>" data-title="<?php echo htmlspecialchars($t['task_title'], ENT_QUOTES); ?>" data-responsible="<?php echo htmlspecialchars($t['responsible_name'], ENT_QUOTES); ?>">
                            <div class="k-card-title"><a href="edit_task.php?task_id=<?php echo $t['task_id']; ?>" onclick="event.stopPropagation()"><?php echo htmlspecialchars($t['task_title']); ?></a></div>
                            <div class="k-card-meta">
                                <?php if ($t['actual_hours_fmt'] && $t['actual_hours_fmt'] !== '0'): ?><span><?php echo $t['actual_hours_fmt']; ?></span><?php endif; ?>
                                <?php if ($t['completion'] > 0): ?><span><?php echo $t['completion']; ?>%</span><?php endif; ?>
                                <?php if ($t['planed_date']): ?><span class="<?php echo $t['is_overdue'] ? 'overdue-text' : ''; ?>"><?php echo $t['planed_date']; ?></span><?php endif; ?>
                            </div>
                            <div class="k-card-footer">
                                <span class="k-card-project"><?php echo htmlspecialchars($t['project_title']); ?></span>
                                <?php if ($initials): ?><div class="k-card-avatar" title="<?php echo htmlspecialchars($t['responsible_name']); ?>"><?php echo $initials; ?></div><?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ==================== PROJECT BOARD ==================== -->
        <div class="tab-panel" id="tabProjectBoard">
            <div class="kanban-board">
                <?php foreach ($by_project as $proj): ?>
                <div class="kanban-col">
                    <div class="kanban-col-header" style="background: linear-gradient(135deg, #667eea22 0%, #764ba222 100%);">
                        <span class="kanban-col-title" style="color:#4a5568;"><a href="view_project_tasks.php?project_id=<?php echo $proj['id']; ?>" style="color:inherit;text-decoration:none;" onclick="event.stopPropagation()"><?php echo htmlspecialchars($proj['title']); ?></a></span>
                        <span class="kanban-col-count"><?php echo count($proj['tasks']); ?></span>
                    </div>
                    <div class="kanban-col-cards">
                        <?php if (empty($proj['tasks'])): ?>
                        <div class="kanban-empty">No tasks</div>
                        <?php endif; ?>
                        <?php foreach ($proj['tasks'] as $t):
                            $sc = isset($status_classes[$t['status_id']]) ? 'status-' . $status_classes[$t['status_id']] : 'status-new';
                            $initials = '';
                            if ($t['responsible_name']) { $p = explode(' ', trim($t['responsible_name'])); $initials = strtoupper(substr($p[0],0,1) . (isset($p[1]) ? substr($p[1],0,1) : '')); }
                        ?>
                        <div class="k-card <?php echo $t['is_overdue'] ? 'overdue' : ''; ?>" onclick="window.location='edit_task.php?task_id=<?php echo $t['task_id']; ?>'" data-task-id="<?php echo $t['task_id']; ?>" data-title="<?php echo htmlspecialchars($t['task_title'], ENT_QUOTES); ?>" data-responsible="<?php echo htmlspecialchars($t['responsible_name'], ENT_QUOTES); ?>">
                            <div class="k-card-title"><a href="edit_task.php?task_id=<?php echo $t['task_id']; ?>" onclick="event.stopPropagation()"><?php echo htmlspecialchars($t['task_title']); ?></a></div>
                            <div class="k-card-meta">
                                <?php if ($t['actual_hours_fmt'] && $t['actual_hours_fmt'] !== '0'): ?><span><?php echo $t['actual_hours_fmt']; ?></span><?php endif; ?>
                                <?php if ($t['completion'] > 0): ?><span><?php echo $t['completion']; ?>%</span><?php endif; ?>
                                <?php if ($t['planed_date']): ?><span class="<?php echo $t['is_overdue'] ? 'overdue-text' : ''; ?>"><?php echo $t['planed_date']; ?></span><?php endif; ?>
                            </div>
                            <div class="k-card-footer">
                                <span class="status-badge <?php echo $sc; ?>"><?php echo htmlspecialchars($t['status_desc']); ?></span>
                                <?php if ($initials): ?><div class="k-card-avatar" title="<?php echo htmlspecialchars($t['responsible_name']); ?>"><?php echo $initials; ?></div><?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ==================== BY USER ==================== -->
        <div class="tab-panel" id="tabByUser">
            <?php foreach ($by_user as $uid => $ugroup): ?>
            <div class="card user-group" data-user="<?php echo htmlspecialchars($ugroup['name'], ENT_QUOTES); ?>">
                <div class="card-header" onclick="toggleGroup(this)">
                    <span class="card-header-title">
                        <span class="arrow open">&#9654;</span>
                        <?php if ($uid): ?><a href="report_people.php?report_user_id=<?php echo $uid; ?>" onclick="event.stopPropagation()" style="color:inherit;text-decoration:none;"><?php echo htmlspecialchars($ugroup['name']); ?></a><?php else: ?><span style="color:#a0aec0;font-style:italic;">Unassigned</span><?php endif; ?>
                    </span>
                    <span class="card-header-count"><?php echo count($ugroup['tasks']); ?> tasks</span>
                </div>
                <div class="card-body">
                    <table class="g-table">
                        <thead><tr><th>Task</th><th>Project</th><th>Status</th><th>Time</th><th>%</th><th>Deadline</th></tr></thead>
                        <tbody>
                        <?php foreach ($ugroup['tasks'] as $t):
                            $sc = isset($status_classes[$t['status_id']]) ? 'status-' . $status_classes[$t['status_id']] : 'status-new';
                        ?>
                        <tr class="task-row" onclick="window.location='edit_task.php?task_id=<?php echo $t['task_id']; ?>'" data-task-id="<?php echo $t['task_id']; ?>" data-title="<?php echo htmlspecialchars($t['task_title'], ENT_QUOTES); ?>" data-responsible="<?php echo htmlspecialchars($t['responsible_name'], ENT_QUOTES); ?>">
                            <td>
                                <span class="priority-dot" style="background:<?php echo isset($priority_colors[$t['priority_id']]) ? $priority_colors[$t['priority_id']] : '#718096'; ?>"></span>
                                <a href="edit_task.php?task_id=<?php echo $t['task_id']; ?>" onclick="event.stopPropagation()"><?php echo htmlspecialchars($t['task_title']); ?></a>
                            </td>
                            <td style="font-size:0.8rem;"><a href="view_project_tasks.php?project_id=<?php echo $t['project_id']; ?>" onclick="event.stopPropagation()" style="color:#667eea;text-decoration:none;"><?php echo htmlspecialchars($t['project_title']); ?></a></td>
                            <td><span class="status-badge <?php echo $sc; ?>"><?php echo htmlspecialchars($t['status_desc']); ?></span></td>
                            <td style="font-size:0.8rem;"><?php echo $t['actual_hours_fmt']; ?></td>
                            <td><div class="comp-bar"><div class="comp-bar-fill" style="width:<?php echo $t['completion']; ?>%"></div></div></td>
                            <td class="<?php echo $t['is_overdue'] ? 'overdue-text' : ''; ?>" style="font-size:0.8rem;"><?php echo $t['planed_date'] ?: '-'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ==================== USER BOARD ==================== -->
        <div class="tab-panel" id="tabUserBoard">
            <div class="kanban-board">
                <?php foreach ($by_user as $uid => $ugroup): ?>
                <div class="kanban-col">
                    <div class="kanban-col-header" style="background: linear-gradient(135deg, #48bb7822 0%, #38b2ac22 100%);">
                        <span class="kanban-col-title" style="color:#4a5568;">
                            <?php if ($uid): ?><a href="report_people.php?report_user_id=<?php echo $uid; ?>" style="color:inherit;text-decoration:none;"><?php echo htmlspecialchars($ugroup['name']); ?></a><?php else: ?><span style="color:#a0aec0;font-style:italic;">Unassigned</span><?php endif; ?>
                        </span>
                        <span class="kanban-col-count"><?php echo count($ugroup['tasks']); ?></span>
                    </div>
                    <div class="kanban-col-cards">
                        <?php if (empty($ugroup['tasks'])): ?>
                        <div class="kanban-empty">No tasks</div>
                        <?php endif; ?>
                        <?php foreach ($ugroup['tasks'] as $t):
                            $sc = isset($status_classes[$t['status_id']]) ? 'status-' . $status_classes[$t['status_id']] : 'status-new';
                        ?>
                        <div class="k-card <?php echo $t['is_overdue'] ? 'overdue' : ''; ?>" onclick="window.location='edit_task.php?task_id=<?php echo $t['task_id']; ?>'" data-task-id="<?php echo $t['task_id']; ?>" data-title="<?php echo htmlspecialchars($t['task_title'], ENT_QUOTES); ?>" data-responsible="<?php echo htmlspecialchars($t['responsible_name'], ENT_QUOTES); ?>">
                            <div class="k-card-title"><a href="edit_task.php?task_id=<?php echo $t['task_id']; ?>" onclick="event.stopPropagation()"><?php echo htmlspecialchars($t['task_title']); ?></a></div>
                            <div class="k-card-meta">
                                <?php if ($t['actual_hours_fmt'] && $t['actual_hours_fmt'] !== '0'): ?><span><?php echo $t['actual_hours_fmt']; ?></span><?php endif; ?>
                                <?php if ($t['completion'] > 0): ?><span><?php echo $t['completion']; ?>%</span><?php endif; ?>
                                <?php if ($t['planed_date']): ?><span class="<?php echo $t['is_overdue'] ? 'overdue-text' : ''; ?>"><?php echo $t['planed_date']; ?></span><?php endif; ?>
                            </div>
                            <div class="k-card-footer">
                                <span class="k-card-project"><?php echo htmlspecialchars($t['project_title']); ?></span>
                                <span class="status-badge <?php echo $sc; ?>" style="font-size:0.6rem;padding:1px 6px;"><?php echo htmlspecialchars($t['status_desc']); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ==================== TIMELINE ==================== -->
        <div class="tab-panel" id="tabTimeline">
            <div class="gantt-container">
                <div class="gantt-header">
                    <span style="font-weight:600;font-size:0.9rem;">Timeline</span>
                    <div class="gantt-controls">
                        <button class="gantt-btn active" onclick="setGanttScale('month')">Month</button>
                        <button class="gantt-btn" onclick="setGanttScale('quarter')">Quarter</button>
                        <button class="gantt-btn" onclick="setGanttScale('year')">Year</button>
                    </div>
                    <span style="font-size:0.75rem;color:#a0aec0;">Only tasks with deadlines are shown</span>
                </div>
                <div id="ganttChart"></div>
            </div>
        </div>

        <!-- ==================== TIME VIEW ==================== -->
        <div class="tab-panel" id="tabTime">
            <div class="time-view-container">
                <div class="time-period-bar">
                    <span style="font-size:0.78rem;color:#718096;font-weight:500;">Period:</span>
                    <button class="tp-btn" data-period="1m" onclick="setDashTimePeriod('1m')">Last Month</button>
                    <button class="tp-btn" data-period="3m" onclick="setDashTimePeriod('3m')">3 Months</button>
                    <button class="tp-btn" data-period="6m" onclick="setDashTimePeriod('6m')">6 Months</button>
                    <button class="tp-btn active" data-period="12m" onclick="setDashTimePeriod('12m')">12 Months</button>
                    <button class="tp-btn" data-period="all" onclick="setDashTimePeriod('all')">All Time</button>
                    <div class="tp-filter-wrap" id="parentFilterWrap">
                        <input type="text" class="tp-filter-input" id="parentFilterInput" placeholder="Filter by parent project..." autocomplete="off">
                        <button class="tp-filter-clear" id="parentFilterClear" onclick="clearParentFilter()" title="Clear filter">&times;</button>
                        <div class="tp-filter-dd" id="parentFilterDD"></div>
                    </div>
                </div>
                <div class="time-view-grid">
                    <div class="time-chart-section">
                        <h3 class="time-section-title">Time by Project</h3>
                        <div class="time-chart-wrap" id="timePieWrap">
                        </div>
                        <div id="dashPieLegend" class="pie-legend"></div>
                    </div>
                    <div class="time-table-section">
                        <h3 class="time-section-title">All Projects</h3>
                        <div id="dashProjectsList" class="time-projects-list"></div>
                    </div>
                </div>
            </div>
        </div><!-- /tabTime -->
    </div>

    <script>
    // ==================== Hash helpers ====================
    // Hash format: #tab-byProject or #tab-byProject&filter=wood
    var _tabMap = {
        'project': 'byProject', 'status': 'byStatus', 'status-board': 'statusBoard',
        'project-board': 'projectBoard', 'user': 'byUser', 'user-board': 'userBoard', 'timeline': 'timeline', 'time': 'time'
    };
    var _tabMapReverse = {};
    (function() { for (var k in _tabMap) _tabMapReverse[_tabMap[k]] = k; })();

    function parseHash() {
        var h = location.hash.replace(/^#/, '');
        var parts = h.split('&');
        var result = { tab: '', filter: '' };
        parts.forEach(function(p) {
            if (p.indexOf('tab-') === 0) result.tab = p.substring(4);
            if (p.indexOf('filter=') === 0) result.filter = decodeURIComponent(p.substring(7));
        });
        // Map slug to tab key
        if (result.tab && _tabMap[result.tab]) result.tab = _tabMap[result.tab];
        return result;
    }

    function updateHash(tab, filter) {
        var slug = _tabMapReverse[tab] || tab;
        var h = 'tab-' + slug;
        if (filter) h += '&filter=' + encodeURIComponent(filter);
        history.replaceState(null, '', '#' + h);
    }

    // ==================== Tab switching ====================
    function switchTab(tab) {
        document.querySelectorAll('.dash-tab').forEach(function(t) {
            t.classList.toggle('active', t.dataset.tab === tab);
        });
        document.querySelectorAll('.tab-panel').forEach(function(p) {
            p.classList.toggle('active', p.id === 'tab' + tab.charAt(0).toUpperCase() + tab.slice(1));
        });
        try { localStorage.setItem('tasksDashTab', tab); } catch(e) {}
        var q = document.getElementById('searchInput').value;
        updateHash(tab, q);
        if (tab === 'timeline') renderGantt();
        if (tab === 'time' && !dashTimeRendered) renderDashTimeView();
        updateToolbar();
    }

    // Restore tab from hash or localStorage
    (function() {
        var h = parseHash();
        var tab = h.tab;
        if (!tab) {
            try { tab = localStorage.getItem('tasksDashTab'); } catch(e) {}
        }
        if (tab) switchTab(tab);
        if (h.filter) {
            document.getElementById('searchInput').value = h.filter;
            filterTasks();
        }
        updateToolbar();
    })();

    // Listen for back/forward navigation
    window.addEventListener('hashchange', function() {
        var h = parseHash();
        if (h.tab) {
            switchTab(h.tab);
            if (h.filter !== undefined) {
                document.getElementById('searchInput').value = h.filter;
                filterTasks();
            }
        }
    });

    // ==================== Collapse/expand groups ====================
    function toggleGroup(header) {
        var card = header.closest('.card');
        card.classList.toggle('collapsed');
        var arrow = header.querySelector('.arrow');
        if (arrow) arrow.classList.toggle('open');
    }

    function collapseAll() {
        document.querySelectorAll('.tab-panel.active .card.project-group, .tab-panel.active .card.status-group, .tab-panel.active .card.user-group').forEach(function(card) {
            card.classList.add('collapsed');
            var arrow = card.querySelector('.arrow');
            if (arrow) arrow.classList.remove('open');
        });
    }

    function expandAll() {
        document.querySelectorAll('.tab-panel.active .card.project-group, .tab-panel.active .card.status-group, .tab-panel.active .card.user-group').forEach(function(card) {
            card.classList.remove('collapsed');
            var arrow = card.querySelector('.arrow');
            if (arrow) arrow.classList.add('open');
        });
    }

    // Show/hide toolbar based on active tab (only for list views)
    function updateToolbar() {
        var toolbar = document.getElementById('dashToolbar');
        var activeTab = document.querySelector('.dash-tab.active');
        var tab = activeTab ? activeTab.dataset.tab : '';
        toolbar.style.display = (tab === 'byProject' || tab === 'byStatus' || tab === 'byUser') ? '' : 'none';
    }

    // ==================== Search / Filter ====================
    function filterTasks() {
        var q = document.getElementById('searchInput').value.toLowerCase();
        var clearBtn = document.getElementById('searchClear');
        clearBtn.className = 'search-clear' + (q ? ' visible' : '');

        // Update hash with current filter
        var activeTab = document.querySelector('.dash-tab.active');
        if (activeTab) updateHash(activeTab.dataset.tab, document.getElementById('searchInput').value);

        // Filter list rows
        document.querySelectorAll('.task-row').forEach(function(row) {
            var title = (row.dataset.title || '').toLowerCase();
            var resp = (row.dataset.responsible || '').toLowerCase();
            row.style.display = (!q || title.indexOf(q) !== -1 || resp.indexOf(q) !== -1) ? '' : 'none';
        });
        // Filter kanban cards
        document.querySelectorAll('.k-card').forEach(function(card) {
            var title = (card.dataset.title || '').toLowerCase();
            var resp = (card.dataset.responsible || '').toLowerCase();
            card.style.display = (!q || title.indexOf(q) !== -1 || resp.indexOf(q) !== -1) ? '' : 'none';
        });

        // Helper: filter collapsible groups by group name
        function filterGroups(selector, nameAttr) {
            document.querySelectorAll(selector).forEach(function(g) {
                var name = (g.getAttribute(nameAttr) || '').toLowerCase();
                if (!q) {
                    g.style.display = '';
                } else if (name.indexOf(q) !== -1) {
                    g.style.display = '';
                    g.querySelectorAll('.task-row').forEach(function(r) { r.style.display = ''; });
                } else {
                    var hasVisible = false;
                    g.querySelectorAll('.task-row').forEach(function(r) {
                        if (r.style.display !== 'none') hasVisible = true;
                    });
                    g.style.display = hasVisible ? '' : 'none';
                }
            });
        }
        filterGroups('.project-group', 'data-project');
        filterGroups('.user-group', 'data-user');

        // Filter kanban columns on project board
        document.querySelectorAll('#tabProjectBoard .kanban-col').forEach(function(col) {
            var link = col.querySelector('.kanban-col-title a');
            var colTitle = link ? link.textContent.toLowerCase() : '';
            col.style.display = (!q || colTitle.indexOf(q) !== -1) ? '' : 'none';
        });
        // Filter kanban columns on user board
        document.querySelectorAll('#tabUserBoard .kanban-col').forEach(function(col) {
            var link = col.querySelector('.kanban-col-title a, .kanban-col-title span');
            var colTitle = link ? link.textContent.toLowerCase() : '';
            col.style.display = (!q || colTitle.indexOf(q) !== -1) ? '' : 'none';
        });
    }

    function clearSearch() {
        var input = document.getElementById('searchInput');
        input.value = '';
        filterTasks();
        input.focus();
    }

    // ==================== Context Menu ====================
    (function() {
        var ctxStatusOptions = <?php echo json_encode(dash_utf8($ctx_statuses)) ?: '[]'; ?>;
        var ctxUserOptions = <?php echo json_encode(dash_utf8($ctx_users)) ?: '[]'; ?>;

        var menu = document.createElement('div');
        menu.className = 'context-menu';
        menu.id = 'dashContextMenu';
        document.body.appendChild(menu);

        var ctxTaskId = null;
        var ctxTaskTitle = null;

        function buildMenu() {
            var html = '';
            if (ctxTaskTitle) {
                var t = ctxTaskTitle.length > 50 ? ctxTaskTitle.substring(0, 50) + '...' : ctxTaskTitle;
                html += '<div class="context-menu-item muted" style="text-transform:none;letter-spacing:0;font-weight:700;color:#2d3748;font-size:0.82rem;padding:8px 16px 6px;line-height:1.3;">' + escapeHtml(t) + '</div>';
                html += '<div class="context-menu-separator"></div>';
            }
            html += '<div class="context-menu-item" data-action="open"><span class="ctx-icon">🔗</span> Open Task</div>';
            html += '<div class="context-menu-item" data-action="open-new-tab"><span class="ctx-icon">↗</span> Open in New Tab</div>';
            html += '<div class="context-menu-separator"></div>';

            // Change Status
            html += '<div class="context-menu-submenu" id="ctxStatusSub">';
            html += '<div class="context-menu-item"><span class="ctx-icon">⟳</span> Change Status</div>';
            html += '<div class="context-menu-submenu-items" id="ctxStatusItems">';
            ctxStatusOptions.forEach(function(s) {
                html += '<div class="context-menu-item" data-action="set-status" data-value="' + s.id + '"><span class="ctx-icon"></span>' + escapeHtml(s.name) + '</div>';
            });
            html += '</div></div>';

            // Reassign
            html += '<div class="context-menu-submenu" id="ctxReassignSub">';
            html += '<div class="context-menu-item"><span class="ctx-icon">👤</span> Reassign</div>';
            html += '<div class="context-menu-submenu-items" id="ctxReassignItems">';
            ctxUserOptions.forEach(function(u) {
                html += '<div class="context-menu-item" data-action="set-user" data-value="' + u.id + '"><span class="ctx-icon"></span>' + escapeHtml(u.name) + '</div>';
            });
            html += '</div></div>';

            html += '<div class="context-menu-separator"></div>';
            html += '<div class="context-menu-item danger" data-action="close-task"><span class="ctx-icon">✕</span> Close Task</div>';
            html += '<div class="context-menu-separator"></div>';

            // Copy
            html += '<div class="context-menu-submenu" id="ctxCopySub">';
            html += '<div class="context-menu-item"><span class="ctx-icon">📋</span> Copy</div>';
            html += '<div class="context-menu-submenu-items" id="ctxCopyItems">';
            html += '<div class="context-menu-item" data-action="copy-url"><span class="ctx-icon">🔗</span> URL</div>';
            html += '<div class="context-menu-item" data-action="copy-name"><span class="ctx-icon">T</span> Task Name</div>';
            html += '<div class="context-menu-item" data-action="copy-id"><span class="ctx-icon">#</span> Task ID</div>';
            html += '</div></div>';

            return html;
        }

        function showMenuAt(e) {
            menu.innerHTML = buildMenu();
            menu.classList.add('show');
            menu.style.left = '-9999px';
            menu.style.top = '-9999px';
            var mr = menu.getBoundingClientRect();
            var x = e.clientX, y = e.clientY;
            if (x + mr.width > window.innerWidth) x = window.innerWidth - mr.width - 8;
            if (y + mr.height > window.innerHeight) y = window.innerHeight - mr.height - 8;
            if (x < 4) x = 4;
            if (y < 4) y = 4;
            menu.style.left = x + 'px';
            menu.style.top = y + 'px';
            attachSubmenuHandlers();
        }

        function closeMenu() {
            menu.classList.remove('show');
            menu.querySelectorAll('.context-menu-submenu-items').forEach(function(s) { s.classList.remove('show'); });
        }

        function attachSubmenuHandlers() {
            menu.querySelectorAll('.context-menu-submenu').forEach(function(sub) {
                var items = sub.querySelector('.context-menu-submenu-items');
                sub.addEventListener('mouseenter', function() {
                    var pr = this.getBoundingClientRect();
                    items.style.left = '-9999px';
                    items.style.top = '-9999px';
                    items.classList.add('show');
                    var sw = items.offsetWidth, sh = items.offsetHeight;
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

        // Bind right-click on task rows and kanban cards
        document.addEventListener('contextmenu', function(e) {
            var row = e.target.closest('.task-row[data-task-id], .k-card[data-task-id]');
            if (!row) return;
            e.preventDefault();
            e.stopPropagation();
            ctxTaskId = row.dataset.taskId;
            ctxTaskTitle = row.dataset.title;
            showMenuAt(e);
        });

        // Close menu
        document.addEventListener('click', function() { closeMenu(); });
        document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeMenu(); });
        window.addEventListener('scroll', function() { closeMenu(); });

        // Actions
        menu.addEventListener('click', function(e) {
            var item = e.target.closest('.context-menu-item');
            if (!item || !item.dataset.action) return;
            e.stopPropagation();
            var action = item.dataset.action;
            closeMenu();

            switch (action) {
                case 'open':
                    if (ctxTaskId) window.location = 'edit_task.php?task_id=' + ctxTaskId;
                    break;
                case 'open-new-tab':
                    if (ctxTaskId) window.open('edit_task.php?task_id=' + ctxTaskId, '_blank');
                    break;
                case 'set-status':
                    var statusId = item.dataset.value;
                    if (ctxTaskId && statusId) {
                        fetch('ajax_responder.php?action=kanban_move_task&task_id=' + ctxTaskId + '&new_status_id=' + statusId)
                            .then(function(r) { return r.json(); })
                            .then(function(data) {
                                if (data.success) {
                                    showDashToast('Status changed to ' + escapeHtml(data.new_status_name || 'updated'));
                                    // Update badge in the row if visible
                                    updateRowStatus(ctxTaskId, statusId, data.new_status_name);
                                } else {
                                    showDashToast(data.error || 'Failed', true);
                                }
                            })
                            .catch(function() { showDashToast('Network error', true); });
                    }
                    break;
                case 'set-user':
                    var userId = item.dataset.value;
                    if (ctxTaskId && userId) {
                        fetch('ajax_responder.php?action=reassign_task&task_id=' + ctxTaskId + '&new_user_id=' + userId)
                            .then(function(r) { return r.json(); })
                            .then(function(data) {
                                if (data.success) {
                                    showDashToast('Reassigned to ' + escapeHtml(data.new_user_name));
                                    updateRowUser(ctxTaskId, data.new_user_name);
                                } else {
                                    showDashToast(data.error || 'Failed', true);
                                }
                            })
                            .catch(function() { showDashToast('Network error', true); });
                    }
                    break;
                case 'close-task':
                    if (ctxTaskId) {
                        showCloseTaskConfirm(ctxTaskId, ctxTaskTitle);
                    }
                    break;
                case 'copy-url':
                    if (ctxTaskId) copyToClipboard(location.origin + '/edit_task.php?task_id=' + ctxTaskId);
                    break;
                case 'copy-name':
                    if (ctxTaskTitle) copyToClipboard(ctxTaskTitle);
                    break;
                case 'copy-id':
                    if (ctxTaskId) copyToClipboard(ctxTaskId);
                    break;
            }
        });

        function updateRowStatus(taskId, statusId, statusName) {
            document.querySelectorAll('[data-task-id="' + taskId + '"] .status-badge').forEach(function(badge) {
                badge.textContent = statusName;
            });
        }

        function updateRowUser(taskId, userName) {
            document.querySelectorAll('.task-row[data-task-id="' + taskId + '"]').forEach(function(row) {
                row.dataset.responsible = userName;
                // Update responsible cell if exists (3rd column typically)
                var cells = row.querySelectorAll('td');
                cells.forEach(function(td) {
                    if (td.textContent.trim() && td.style.color === 'rgb(113, 128, 150)') {
                        td.textContent = userName;
                    }
                });
            });
            document.querySelectorAll('.k-card[data-task-id="' + taskId + '"]').forEach(function(card) {
                card.dataset.responsible = userName;
                var avatar = card.querySelector('.k-card-avatar');
                if (avatar) {
                    var parts = userName.split(' ');
                    avatar.textContent = (parts[0] ? parts[0][0] : '') + (parts[1] ? parts[1][0] : '');
                    avatar.textContent = avatar.textContent.toUpperCase();
                    avatar.title = userName;
                }
            });
        }

        function copyToClipboard(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function() { showDashToast('Copied!'); });
            } else {
                var ta = document.createElement('textarea');
                ta.value = text;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                showDashToast('Copied!');
            }
        }

        function showCloseTaskConfirm(taskId, taskTitle) {
            var existing = document.querySelector('.confirm-overlay');
            if (existing) existing.remove();

            var overlay = document.createElement('div');
            overlay.className = 'confirm-overlay';
            overlay.innerHTML =
                '<div class="confirm-box">' +
                    '<div class="confirm-title">Close Task</div>' +
                    '<div class="confirm-msg">Close <strong>' + escapeHtml(taskTitle || 'this task') + '</strong>?</div>' +
                    '<div class="confirm-actions">' +
                        '<button class="confirm-cancel">Cancel</button>' +
                        '<button class="confirm-ok">Close Task</button>' +
                    '</div>' +
                    '<div class="confirm-hint">Enter to confirm &middot; Esc to cancel</div>' +
                '</div>';
            document.body.appendChild(overlay);
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
                fetch('ajax_responder.php?action=close_task&task_ids=' + taskId)
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) {
                            // Remove from DOM with animation
                            document.querySelectorAll('[data-task-id="' + taskId + '"]').forEach(function(el) {
                                el.style.transition = 'all 0.3s ease';
                                el.style.opacity = '0';
                                el.style.maxHeight = el.offsetHeight + 'px';
                                setTimeout(function() { el.style.maxHeight = '0'; el.style.overflow = 'hidden'; el.style.padding = '0'; el.style.margin = '0'; }, 50);
                                setTimeout(function() { el.remove(); }, 350);
                            });
                            showDashToast('Task closed');
                        } else {
                            showDashToast(data.error || 'Failed', true);
                        }
                    })
                    .catch(function() { showDashToast('Network error', true); });
            }
            function onKey(e) {
                if (e.key === 'Enter') { e.preventDefault(); e.stopImmediatePropagation(); doConfirm(); }
                if (e.key === 'Escape') { e.preventDefault(); close(); }
            }

            confirmBtn.addEventListener('click', doConfirm);
            cancelBtn.addEventListener('click', close);
            overlay.addEventListener('click', function(e) { if (e.target === overlay) close(); });
            document.addEventListener('keydown', onKey);
            confirmBtn.focus();
        }
    })();

    // ==================== Gantt Chart ====================
    var ganttData = <?php echo json_encode(dash_utf8($gantt_tasks)) ?: '[]'; ?>;
    var ganttScale = 'month';

    function setGanttScale(scale) {
        ganttScale = scale;
        document.querySelectorAll('.gantt-btn').forEach(function(b) { b.classList.toggle('active', b.textContent.toLowerCase() === scale); });
        renderGantt();
    }

    function renderGantt() {
        var container = document.getElementById('ganttChart');
        if (!ganttData.length) {
            container.innerHTML = '<div style="text-align:center;padding:60px;color:#a0aec0;">No tasks with deadlines to display.</div>';
            return;
        }

        // Find date range
        var today = new Date();
        var minDate = new Date(today);
        var maxDate = new Date(today);
        minDate.setMonth(minDate.getMonth() - 1);

        ganttData.forEach(function(t) {
            var s = new Date(t.start);
            var e = new Date(t.end);
            if (s < minDate) minDate = new Date(s);
            if (e > maxDate) maxDate = new Date(e);
        });

        // Add padding
        minDate.setDate(minDate.getDate() - 7);
        maxDate.setDate(maxDate.getDate() + 14);

        var totalDays = Math.ceil((maxDate - minDate) / 86400000);
        var dayWidth;
        switch(ganttScale) {
            case 'month': dayWidth = 8; break;
            case 'quarter': dayWidth = 3; break;
            case 'year': dayWidth = 1.2; break;
            default: dayWidth = 8;
        }
        var timelineWidth = totalDays * dayWidth;

        // Build date markers
        var datesHtml = '<div class="gantt-dates"><div class="gantt-dates-label">Task</div><div class="gantt-dates-timeline" style="min-width:' + timelineWidth + 'px;">';
        var d = new Date(minDate);
        var lastMonth = -1;
        while (d <= maxDate) {
            var dayOfMonth = d.getDate();
            var month = d.getMonth();
            if (dayOfMonth === 1 || (ganttScale === 'month' && dayOfMonth % 7 === 1) || (ganttScale !== 'month' && dayOfMonth === 1)) {
                var label = d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
                if (dayOfMonth === 1) label = d.toLocaleDateString('en-GB', { month: 'short', year: '2-digit' });
                var left = Math.round(((d - minDate) / 86400000) * dayWidth);
                datesHtml += '<div class="gantt-date-marker" style="position:absolute;left:' + left + 'px;width:' + (7 * dayWidth) + 'px;">' + label + '</div>';
            }
            d.setDate(d.getDate() + 1);
        }
        datesHtml += '</div></div>';

        // Today line position
        var todayOffset = Math.round(((today - minDate) / 86400000) * dayWidth);

        // Group tasks by project
        var byProject = {};
        ganttData.forEach(function(t) {
            if (!byProject[t.project_id]) byProject[t.project_id] = { title: t.project, tasks: [] };
            byProject[t.project_id].tasks.push(t);
        });

        var statusColors = {
            7: '#a0aec0', 9: '#fc8181', 1: '#48bb78', 11: '#e53e3e', 8: '#ecc94b', 2: '#ed8936',
            10: '#fc8181', 5: '#ecc94b', 6: '#48bb78', 4: '#4299e1', 3: '#a0aec0'
        };

        var rowsHtml = '';
        Object.keys(byProject).forEach(function(pid) {
            var proj = byProject[pid];
            rowsHtml += '<div class="gantt-project-header">' + escapeHtml(proj.title) + '</div>';
            proj.tasks.forEach(function(t) {
                var start = new Date(t.start);
                var end = new Date(t.end);
                var leftPx = Math.round(((start - minDate) / 86400000) * dayWidth);
                var widthPx = Math.max(8, Math.round(((end - start) / 86400000) * dayWidth));
                var barColor = statusColors[t.status_id] || '#667eea';
                if (t.is_overdue) barColor = '#e53e3e';

                rowsHtml += '<div class="gantt-row">';
                rowsHtml += '<div class="gantt-label" onclick="window.location=\'edit_task.php?task_id=' + t.id + '\'" title="' + escapeHtml(t.title) + '">' + escapeHtml(t.title) + '<small>' + escapeHtml(t.responsible || '') + '</small></div>';
                rowsHtml += '<div class="gantt-timeline" style="min-width:' + timelineWidth + 'px;">';
                rowsHtml += '<div class="gantt-bar" style="left:' + leftPx + 'px;width:' + widthPx + 'px;background:' + barColor + ';" title="' + escapeHtml(t.title) + ' (' + t.start + ' → ' + t.end + ', ' + t.completion + '%)" onclick="window.location=\'edit_task.php?task_id=' + t.id + '\'">';
                if (t.completion > 0) {
                    rowsHtml += '<div class="gantt-bar-fill" style="width:' + t.completion + '%;"></div>';
                }
                rowsHtml += '</div>';
                rowsHtml += '<div class="gantt-today" style="left:' + todayOffset + 'px;"></div>';
                rowsHtml += '</div>';
                rowsHtml += '</div>';
            });
        });

        container.innerHTML = datesHtml + rowsHtml;
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    // ==================== Close Project ====================
    function showCloseProjectConfirm(projectId, projectTitle) {
        var existing = document.querySelector('.confirm-overlay');
        if (existing) existing.remove();

        var overlay = document.createElement('div');
        overlay.className = 'confirm-overlay';
        overlay.innerHTML =
            '<div class="confirm-box">' +
                '<div class="confirm-title">Close Project</div>' +
                '<div class="confirm-msg">Close <strong>' + escapeHtml(projectTitle) + '</strong>?<br>All subprojects will also be closed.</div>' +
                '<div class="confirm-actions">' +
                    '<button class="confirm-cancel">Cancel</button>' +
                    '<button class="confirm-ok">Close Project</button>' +
                '</div>' +
                '<div class="confirm-hint">Enter to confirm &middot; Esc to cancel</div>' +
            '</div>';
        document.body.appendChild(overlay);
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
            closeProject(projectId, projectTitle);
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

    function closeProject(projectId, projectTitle) {
        fetch('edit_project.php?project_id=' + projectId + '&ajax=1&FormAction=close_project')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    // Remove project cards/columns from all views
                    document.querySelectorAll('.project-group').forEach(function(el) {
                        var link = el.querySelector('a[href*="project_id=' + projectId + '"]');
                        if (link) {
                            el.style.transition = 'all 0.35s ease';
                            el.style.opacity = '0';
                            el.style.maxHeight = el.offsetHeight + 'px';
                            setTimeout(function() {
                                el.style.maxHeight = '0';
                                el.style.marginBottom = '0';
                                el.style.overflow = 'hidden';
                            }, 50);
                            setTimeout(function() { el.remove(); }, 400);
                        }
                    });
                    // Remove from kanban project board
                    document.querySelectorAll('#tabProjectBoard .kanban-col').forEach(function(col) {
                        var link = col.querySelector('a[href*="project_id=' + projectId + '"]');
                        if (link) {
                            col.style.transition = 'all 0.35s ease';
                            col.style.opacity = '0';
                            col.style.transform = 'scale(0.95)';
                            setTimeout(function() { col.remove(); }, 400);
                        }
                    });
                    showDashToast(escapeHtml(projectTitle) + ' closed');
                } else {
                    showDashToast(data.message || 'Failed to close project', true);
                }
            })
            .catch(function() { showDashToast('Network error', true); });
    }

    function showDashToast(msg, isError) {
        var t = document.createElement('div');
        t.className = 'dash-toast';
        if (isError) t.style.background = '#e53e3e';
        t.innerHTML = msg;
        document.body.appendChild(t);
        setTimeout(function() { t.style.opacity = '0'; }, 2500);
        setTimeout(function() { t.remove(); }, 3000);
    }

    // ==================== Time View (by Project) ====================
    var timeProjRaw = <?php echo $time_proj_json; ?>;
    var dashTimePeriod = '12m';
    var dashTimeRendered = false;
    var dashParentFilter = '';
    var dashPieColors = ['#667eea','#ed8936','#48bb78','#e53e3e','#9f7aea','#38b2ac','#ed64a6','#ecc94b','#4299e1','#fc8181','#68d391','#f6ad55','#b794f4','#76e4f7','#f687b3','#4fd1c5','#fc8181','#f6e05e','#63b3ed','#d53f8c'];

    // Build unique parent projects list
    var dashParentProjects = [];
    (function() {
        if (!Array.isArray(timeProjRaw)) return;
        var seen = {};
        for (var i = 0; i < timeProjRaw.length; i++) {
            var p = timeProjRaw[i].parent;
            if (p && !seen[p]) { seen[p] = 1; dashParentProjects.push(p); }
        }
        dashParentProjects.sort(function(a, b) { return a.toLowerCase().localeCompare(b.toLowerCase()); });
    })();

    // Parent project filter autosuggest
    (function() {
        var input = document.getElementById('parentFilterInput');
        var dd = document.getElementById('parentFilterDD');
        var clearBtn = document.getElementById('parentFilterClear');
        if (!input || !dd) return;
        var hlIdx = -1;

        function showOptions(filter) {
            var q = (filter || '').toLowerCase();
            var matches = [];
            for (var i = 0; i < dashParentProjects.length; i++) {
                if (!q || dashParentProjects[i].toLowerCase().indexOf(q) !== -1) {
                    matches.push(dashParentProjects[i]);
                }
            }
            if (matches.length === 0) { dd.classList.remove('show'); return; }
            var html = '';
            for (var i = 0; i < matches.length; i++) {
                var sel = (matches[i] === dashParentFilter) ? ' selected' : '';
                html += '<div class="tp-filter-opt' + sel + '" data-value="' + escapeHtml(matches[i]) + '">' + escapeHtml(matches[i]) + '</div>';
            }
            dd.innerHTML = html;
            dd.classList.add('show');
            hlIdx = -1;
        }

        input.addEventListener('focus', function() { showOptions(input.value); });
        input.addEventListener('input', function() { showOptions(input.value); });

        input.addEventListener('keydown', function(e) {
            var opts = dd.querySelectorAll('.tp-filter-opt');
            if (!dd.classList.contains('show') || opts.length === 0) return;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                hlIdx = Math.min(hlIdx + 1, opts.length - 1);
                opts.forEach(function(o, i) { o.classList.toggle('highlighted', i === hlIdx); });
                opts[hlIdx].scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                hlIdx = Math.max(hlIdx - 1, 0);
                opts.forEach(function(o, i) { o.classList.toggle('highlighted', i === hlIdx); });
                opts[hlIdx].scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (hlIdx >= 0 && hlIdx < opts.length) {
                    selectParent(opts[hlIdx].dataset.value);
                }
            } else if (e.key === 'Escape') {
                dd.classList.remove('show');
                input.blur();
            }
        });

        dd.addEventListener('click', function(e) {
            var opt = e.target.closest('.tp-filter-opt');
            if (opt) selectParent(opt.dataset.value);
        });

        document.addEventListener('click', function(e) {
            if (!e.target.closest('#parentFilterWrap')) dd.classList.remove('show');
        });

        function selectParent(val) {
            dashParentFilter = val;
            input.value = val;
            clearBtn.style.display = 'block';
            dd.classList.remove('show');
            renderDashTimeView();
        }
    })();

    function clearParentFilter() {
        dashParentFilter = '';
        var input = document.getElementById('parentFilterInput');
        var clearBtn = document.getElementById('parentFilterClear');
        if (input) input.value = '';
        if (clearBtn) clearBtn.style.display = 'none';
        renderDashTimeView();
    }

    function setDashTimePeriod(p) {
        dashTimePeriod = p;
        document.querySelectorAll('#tabTime .tp-btn').forEach(function(b) {
            b.classList.toggle('active', b.dataset.period === p);
        });
        renderDashTimeView();
    }

    function getDashFilteredTime() {
        if (!Array.isArray(timeProjRaw)) return [];
        var cutoff = null;
        var now = new Date();
        var y = now.getFullYear(), m = now.getMonth() + 1;
        if (dashTimePeriod === '1m') {
            var d = new Date(y, m - 2, 1);
            cutoff = d.getFullYear() + '-' + (d.getMonth() + 1 < 10 ? '0' : '') + (d.getMonth() + 1);
        } else if (dashTimePeriod === '3m') {
            var d = new Date(y, m - 4, 1);
            cutoff = d.getFullYear() + '-' + (d.getMonth() + 1 < 10 ? '0' : '') + (d.getMonth() + 1);
        } else if (dashTimePeriod === '6m') {
            var d = new Date(y, m - 7, 1);
            cutoff = d.getFullYear() + '-' + (d.getMonth() + 1 < 10 ? '0' : '') + (d.getMonth() + 1);
        } else if (dashTimePeriod === '12m') {
            var d = new Date(y - 1, m - 1, 1);
            cutoff = d.getFullYear() + '-' + (d.getMonth() + 1 < 10 ? '0' : '') + (d.getMonth() + 1);
        }
        // 'all' => no cutoff
        var filtered = [];
        for (var i = 0; i < timeProjRaw.length; i++) {
            var r = timeProjRaw[i];
            if (cutoff && r.ym < cutoff) continue;
            if (dashParentFilter && r.parent !== dashParentFilter) continue;
            filtered.push(r);
        }
        return filtered;
    }

    function aggregateByProject(records) {
        var map = {};
        for (var i = 0; i < records.length; i++) {
            var r = records[i];
            var key = r.pid;
            if (!map[key]) map[key] = { pid: r.pid, name: r.pname, parent: r.parent, hours: 0 };
            map[key].hours += r.hours;
        }
        var arr = [];
        for (var k in map) {
            if (map.hasOwnProperty(k)) {
                var m = map[k];
                arr.push({ pid: m.pid, name: m.name, parent: m.parent, hours: Math.round(m.hours * 100) / 100 });
            }
        }
        arr.sort(function(a, b) { return b.hours - a.hours; });
        return arr;
    }

    function renderDashTimeView() {
        dashTimeRendered = true;
        try {
            var records = getDashFilteredTime();
            var byProj = aggregateByProject(records);
            renderDashPieChart(byProj);
            renderDashProjectList(byProj);
        } catch(ex) {
            console.error('renderDashTimeView error:', ex);
            showDashNoTimeData();
        }
    }

    function showDashNoTimeData() {
        var wrap = document.getElementById('timePieWrap');
        if (wrap) wrap.innerHTML = '<div style="text-align:center;padding:60px 20px;color:#a0aec0;">No time data for this period</div>';
        var el = document.getElementById('dashProjectsList');
        if (el) el.innerHTML = '<div style="text-align:center;padding:24px;color:#a0aec0;font-size:0.85rem;">No time data</div>';
        var leg = document.getElementById('dashPieLegend');
        if (leg) leg.innerHTML = '';
    }

    function renderDashPieChart(data) {
        if (!Array.isArray(data)) data = [];
        var wrap = document.getElementById('timePieWrap');
        if (!wrap) return;

        if (data.length === 0) {
            wrap.innerHTML = '<div style="text-align:center;padding:60px 20px;color:#a0aec0;">No time data for this period</div>';
            var leg0 = document.getElementById('dashPieLegend');
            if (leg0) leg0.innerHTML = '';
            return;
        }

        // Limit pie chart to top 15 projects, aggregate rest into "Other"
        var pieData = data;
        var otherHours = 0;
        if (data.length > 15) {
            pieData = data.slice(0, 15);
            for (var i = 15; i < data.length; i++) otherHours += data[i].hours;
            if (otherHours > 0) pieData.push({ pid: 0, name: 'Other (' + (data.length - 15) + ')', parent: '', hours: Math.round(otherHours * 100) / 100 });
        }

        // Create canvas
        wrap.innerHTML = '<canvas id="dashPieByProject" width="340" height="340"></canvas>';
        var canvas = document.getElementById('dashPieByProject');
        if (!canvas || !canvas.getContext) return;
        var ctx = canvas.getContext('2d');

        var totalHours = 0;
        for (var i = 0; i < pieData.length; i++) totalHours += pieData[i].hours;
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
        var innerR = outerR * 0.55;
        var startAngle = -Math.PI / 2;

        // Draw slices
        for (var i = 0; i < pieData.length; i++) {
            var slice = pieData[i].hours / totalHours;
            var endAngle = startAngle + slice * 2 * Math.PI;
            var color = dashPieColors[i % dashPieColors.length];

            ctx.beginPath();
            ctx.moveTo(cx + innerR * Math.cos(startAngle), cy + innerR * Math.sin(startAngle));
            ctx.arc(cx, cy, outerR, startAngle, endAngle);
            ctx.arc(cx, cy, innerR, endAngle, startAngle, true);
            ctx.closePath();
            ctx.fillStyle = color;
            ctx.fill();

            // Separator line
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
        var legendEl = document.getElementById('dashPieLegend');
        if (legendEl) {
            var lhtml = '';
            for (var i = 0; i < pieData.length; i++) {
                var d = pieData[i];
                var days = Math.round(d.hours / 8 * 10) / 10;
                var pct = Math.round(d.hours / totalHours * 100);
                var color = dashPieColors[i % dashPieColors.length];
                lhtml += '<div class="pie-legend-item" title="' + escapeHtml(d.name) + ': ' + days + ' days (' + Math.round(d.hours) + 'h)">';
                lhtml += '<span class="pie-legend-dot" style="background:' + color + ';"></span>';
                lhtml += escapeHtml(d.name);
                lhtml += '<span class="pie-legend-value">' + days + 'd</span>';
                lhtml += '<span style="color:#cbd5e0;font-size:0.7rem;">(' + pct + '%)</span>';
                lhtml += '</div>';
            }
            legendEl.innerHTML = lhtml;
        }

        // Hover tooltip
        canvas.addEventListener('mousemove', function(e) {
            var rect = canvas.getBoundingClientRect();
            var mx = e.clientX - rect.left, my = e.clientY - rect.top;
            var dx = mx - cx, dy = my - cy;
            var dist = Math.sqrt(dx * dx + dy * dy);
            if (dist < innerR || dist > outerR) { canvas.style.cursor = 'default'; canvas.title = ''; return; }
            var angle = Math.atan2(dy, dx);
            if (angle < -Math.PI / 2) angle += 2 * Math.PI;
            var cum = -Math.PI / 2;
            for (var i = 0; i < pieData.length; i++) {
                var sa = (pieData[i].hours / totalHours) * 2 * Math.PI;
                if (angle >= cum && angle < cum + sa) {
                    var days = Math.round(pieData[i].hours / 8 * 10) / 10;
                    canvas.title = pieData[i].name + ': ' + days + ' days (' + Math.round(pieData[i].hours) + 'h)';
                    canvas.style.cursor = 'pointer';
                    return;
                }
                cum += sa;
            }
        });

        // Click to navigate to project
        canvas.addEventListener('click', function(e) {
            var rect = canvas.getBoundingClientRect();
            var mx = e.clientX - rect.left, my = e.clientY - rect.top;
            var dx = mx - cx, dy = my - cy;
            var dist = Math.sqrt(dx * dx + dy * dy);
            if (dist < innerR || dist > outerR) return;
            var angle = Math.atan2(dy, dx);
            if (angle < -Math.PI / 2) angle += 2 * Math.PI;
            var cum = -Math.PI / 2;
            for (var i = 0; i < pieData.length; i++) {
                var sa = (pieData[i].hours / totalHours) * 2 * Math.PI;
                if (angle >= cum && angle < cum + sa) {
                    if (pieData[i].pid > 0) {
                        window.location = 'view_project_tasks.php?project_id=' + pieData[i].pid + '#view-time';
                    }
                    return;
                }
                cum += sa;
            }
        });
    }

    function renderDashProjectList(data) {
        if (!Array.isArray(data)) data = [];
        var el = document.getElementById('dashProjectsList');
        if (!el) return;
        if (data.length === 0) {
            el.innerHTML = '<div style="text-align:center;padding:24px;color:#a0aec0;font-size:0.85rem;">No time data</div>';
            return;
        }
        var maxHours = data[0].hours;
        var html = '';
        for (var i = 0; i < data.length; i++) {
            var d = data[i];
            var days = Math.round(d.hours / 8 * 10) / 10;
            var pct = Math.round(d.hours / maxHours * 100);
            var color = dashPieColors[i % dashPieColors.length];
            html += '<div class="time-proj-row">';
            html += '<span class="time-proj-rank">' + (i + 1) + '</span>';
            html += '<span class="time-proj-dot" style="background:' + color + ';"></span>';
            html += '<span class="time-proj-name">';
            if (d.pid > 0) {
                html += '<a href="view_project_tasks.php?project_id=' + d.pid + '#view-time" title="' + escapeHtml(d.name) + '">' + escapeHtml(d.name) + '</a>';
            } else {
                html += escapeHtml(d.name);
            }
            if (d.parent) html += '<span class="time-proj-parent">(' + escapeHtml(d.parent) + ')</span>';
            html += '</span>';
            html += '<span class="time-proj-hours">' + days + 'd <small style="color:#a0aec0;font-weight:400;">(' + Math.round(d.hours) + 'h)</small></span>';
            html += '<span class="time-proj-bar-wrap"><span class="time-proj-bar" style="width:' + pct + '%;background:' + color + ';"></span></span>';
            html += '</div>';
        }
        el.innerHTML = html;
    }
    </script>
</body>
</html>
