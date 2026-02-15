<?php
// Profiling - set to true to enable
$PROFILING_ENABLED = isset($_GET['debug']) && $_GET['debug'] == '1';
$profile_times = array();
$profile_start = microtime(true);

function profile_checkpoint($name) {
    global $PROFILING_ENABLED, $profile_times, $profile_start;
    if ($PROFILING_ENABLED) {
        $profile_times[$name] = round((microtime(true) - $profile_start) * 1000, 2);
    }
}

profile_checkpoint('start');

include_once("./includes/common.php");

profile_checkpoint('after_common_include');

header("Cache-Control: private");
header("Age: 699");

define("CHANGE_PERM", 4);

if (getsessionparam("privilege_id") == PRIV_CUSTOMER) {
    header("Location: index.php");
    exit;
}

CheckSecurity(2);

$project_id = GetParam("project_id");
$return_page = GetParam("return_page") ? GetParam("return_page") : "index.php";
$user_name = GetSessionParam("UserName");

// Handle AJAX requests
if (GetParam("ajax") == "1") {
    header('Content-Type: application/json');
    
    $action = GetParam("FormAction");
    $response = array('success' => false, 'message' => '');
    
    try {
        switch($action) {
            case "insert":
                $parent_id = GetParam("parent_project_id") > 0 ? ToSQL(GetParam("parent_project_id"), "Number") : "NULL";
                $sSQL = "INSERT INTO projects (
                    parent_project_id, project_title, emails_copy, project_desc, 
                    project_status_id, responsible_user_id, project_url, client_id, 
                    is_closed, is_domain_required, cvs_module
                ) VALUES (
                    $parent_id,
                    " . ToSQL(GetParam("project_title"), "Text") . ",
                    " . ToSQL(GetParam("emails_copy"), "Text") . ",
                    " . ToSQL(GetParam("project_desc"), "Text") . ",
                    " . ToSQL(GetParam("project_status_id"), "Number") . ",
                    " . ToSQL(GetParam("responsible_user_id"), "Number") . ",
                    " . ToSQL(GetParam("project_url"), "text") . ",
                    " . ToSQL(GetParam("client_id"), "Number") . ",
                    " . ToSQL(GetParam("is_closed"), "Number", false) . ",
                    " . ToSQL(GetParam("is_domain_required"), "Number", false) . ",
                    " . ToSQL(GetParam("cvs_module"), "text") . "
                )";
                $db->query($sSQL);
                
                $db->query("SELECT LAST_INSERT_ID()");
                $db->next_record();
                $new_id = $db->f(0);
                
                add_assigned_people($new_id);
                
                $response['success'] = true;
                $response['message'] = 'Project created successfully';
                $response['project_id'] = $new_id;
                break;
                
            case "update":
                $project_id = GetParam("PK_project_id");
                $parent_id = GetParam("parent_project_id") > 0 ? ToSQL(GetParam("parent_project_id"), "Number") : "NULL";
                
                $sSQL = "UPDATE projects SET 
                    project_title = " . ToSQL(GetParam("project_title"), "Text") . ",
                    emails_copy = " . ToSQL(GetParam("emails_copy"), "Text") . ",
                    project_desc = " . ToSQL(GetParam("project_desc"), "Text") . ",
                    project_url = " . ToSQL(GetParam("project_url"), "Text") . ",
                    client_id = " . ToSQL(GetParam("client_id"), "Number") . ",
                    is_closed = " . ToSQL(GetParam("is_closed"), "Number", false) . ",
                    is_domain_required = " . ToSQL(GetParam("is_domain_required"), "Number", false) . ",
                    project_status_id = " . ToSQL(GetParam("project_status_id"), "Number") . ",
                    cvs_module = " . ToSQL(GetParam("cvs_module"), "Text") . ",
                    parent_project_id = $parent_id,
                    responsible_user_id = " . ToSQL(GetParam("responsible_user_id"), "Number") . "
                WHERE project_id = " . ToSQL($project_id, "Number");
                $db->query($sSQL);
                
                add_assigned_people($project_id);
                
                $response['success'] = true;
                $response['message'] = 'Project updated successfully';
                break;
                
            case "delete":
                $project_id = GetParam("PK_project_id");
                $sSQL = "DELETE FROM projects WHERE project_id = " . ToSQL($project_id, "Number");
                $db->query($sSQL);
                $response['success'] = true;
                $response['message'] = 'Project deleted successfully';
                $response['redirect'] = $return_page;
                break;
                
            case "addsub":
                $parent_id = GetParam("project_id");
                $sSQL = "INSERT INTO projects (project_title, emails_copy, parent_project_id, project_status_id, responsible_user_id, project_url, client_id) 
                         VALUES (
                            " . ToSQL(GetParam("subproject_title"), "Text") . ",
                            " . ToSQL(GetParam("emails_copy"), "Text") . ",
                            " . ToSQL($parent_id, "Number") . ",
                            1,
                            " . ToSQL(GetParam("responsible_user_id"), "Number") . ",
                            " . ToSQL(GetParam("project_url"), "Text") . ",
                            " . ToSQL(GetParam("client_id"), "Number") . "
                         )";
                $db->query($sSQL);
                
                $db->query("SELECT LAST_INSERT_ID()");
                $db->next_record();
                $new_id = $db->f(0);
                add_assigned_people($new_id);
                
                $response['success'] = true;
                $response['message'] = 'Subproject added successfully';
                $response['project_id'] = $new_id;
                break;
                
            case "toggle_closed":
                $subproject_id = GetParam("subproject_id");
                $is_closed = GetParam("is_closed") ? 1 : 0;
                
                $sSQL = "UPDATE projects SET is_closed = " . ToSQL($is_closed, "Number") . " WHERE project_id = " . ToSQL($subproject_id, "Number");
                $db->query($sSQL);
                
                $response['success'] = true;
                $response['message'] = $is_closed ? 'Subproject closed' : 'Subproject reopened';
                break;

            case "close_project":
                $sSQL = "UPDATE projects SET is_closed = 1 WHERE project_id = " . ToSQL($project_id, "Number");
                $db->query($sSQL);
                // Also close all subprojects
                $sSQL = "UPDATE projects SET is_closed = 1 WHERE parent_project_id = " . ToSQL($project_id, "Number");
                $db->query($sSQL);
                $response['success'] = true;
                $response['message'] = 'Project closed';
                break;

            case "reopen_project":
                $sSQL = "UPDATE projects SET is_closed = 0 WHERE project_id = " . ToSQL($project_id, "Number");
                $db->query($sSQL);
                $response['success'] = true;
                $response['message'] = 'Project reopened';
                break;
        }
    } catch (Exception $e) {
        $response['success'] = false;
        $response['message'] = 'Error: ' . $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
}

profile_checkpoint('before_load_project');

// Load project data
$project = array();
$is_new = empty($project_id);

if (!$is_new) {
    // Skip expensive count_project_time() on page load - it's already stored in DB
    // Only recalculate when explicitly requested via ?recalc=1
    if (GetParam("recalc") == "1") {
        count_project_time($project_id);
    }
    
    profile_checkpoint('after_count_project_time');
    
    $sql = "SELECT p.*, c.client_name 
            FROM projects p 
            LEFT JOIN clients c ON c.client_id = p.client_id 
            WHERE p.project_id = " . ToSQL($project_id, "integer");
    $db->query($sql);
    if ($db->next_record()) {
        $project = array(
            'project_id' => $db->f("project_id"),
            'project_title' => $db->f("project_title"),
            'project_desc' => $db->f("project_desc"),
            'project_url' => $db->f("project_url"),
            'emails_copy' => $db->f("emails_copy"),
            'cvs_module' => $db->f("cvs_module"),
            'parent_project_id' => $db->f("parent_project_id"),
            'project_status_id' => $db->f("project_status_id"),
            'responsible_user_id' => $db->f("responsible_user_id"),
            'client_id' => $db->f("client_id"),
            'client_name' => $db->f("client_name"),
            'is_closed' => $db->f("is_closed"),
            'is_domain_required' => $db->f("is_domain_required"),
            'total_time' => $db->f("total_time")
        );
    }
}

profile_checkpoint('after_load_project');

// Get assigned users
$assigned = array();
if ($project_id) {
    $sql = "SELECT user_id, permissions FROM users_projects WHERE project_id=" . ToSQL($project_id, "integer", false);
    $db->query($sql);
    while ($db->next_record()) {
        $assigned[$db->f("user_id")] = $db->f("permissions");
    }
}

// Get users who worked on this project recently
$user_in_project = array();
if ($project_id) {
    // Task creators and assignees
    $sql = "SELECT DISTINCT t.created_person_id, t.responsible_user_id 
            FROM tasks t 
            WHERE t.project_id=" . ToSQL($project_id, "integer") . " 
            AND DATE_ADD(t.creation_date, INTERVAL 6 MONTH) >= NOW()";
    $db->query($sql);
    while ($db->next_record()) {
        $user_in_project[$db->f("created_person_id")] = 1;
        $user_in_project[$db->f("responsible_user_id")] = 1;
    }
    
    // Message authors
    $sql = "SELECT DISTINCT m.user_id, m.responsible_user_id 
            FROM tasks t 
            INNER JOIN messages m ON (t.task_id = m.identity_id AND m.identity_type='task') 
            WHERE t.project_id=" . ToSQL($project_id, "integer") . " 
            AND DATE_ADD(m.message_date, INTERVAL 6 MONTH) >= NOW()";
    $db->query($sql);
    while ($db->next_record()) {
        $user_in_project[$db->f("user_id")] = 1;
        $user_in_project[$db->f("responsible_user_id")] = 1;
    }
    
    // Time reporters
    $sql = "SELECT DISTINCT tr.user_id 
            FROM tasks t 
            INNER JOIN time_report tr ON t.task_id = tr.task_id 
            WHERE t.project_id=" . ToSQL($project_id, "integer") . " 
            AND DATE_ADD(tr.report_date, INTERVAL 6 MONTH) >= NOW()";
    $db->query($sql);
    while ($db->next_record()) {
        $user_in_project[$db->f("user_id")] = 1;
    }
}

profile_checkpoint('before_parent_projects');

// Get parent projects
$parent_projects = array();
$sql = "SELECT project_id, project_title FROM projects 
        WHERE parent_project_id IS NULL AND project_title != '' ";
if ($project_id > 0) {
    $sql .= " AND project_id != " . intval($project_id);
}
$sql .= " ORDER BY project_title";
$db->query($sql);
while ($db->next_record()) {
    $parent_projects[] = array(
        'id' => $db->f("project_id"),
        'title' => $db->f("project_title")
    );
}

profile_checkpoint('after_parent_projects');

profile_checkpoint('before_subprojects');

// Get subprojects count first (for performance)
$subprojects = array();
$subprojects_count = 0;
$is_parent = false;
if ($project_id) {
    // Get count first
    $db->query("SELECT COUNT(*) as cnt FROM projects WHERE parent_project_id=" . intval($project_id));
    if ($db->next_record()) {
        $subprojects_count = $db->f("cnt");
    }
    
    profile_checkpoint('after_subprojects_count');
    
    // Only load subprojects if reasonable count (< 500)
    if ($subprojects_count > 0 && $subprojects_count < 500) {
        $sql = "SELECT p.project_id, p.project_title, p.is_closed,
                       COUNT(t.task_id) as total_tasks,
                       SUM(CASE WHEN t.task_status_id NOT IN (4, 5) THEN 1 ELSE 0 END) as open_tasks
                FROM projects p
                LEFT JOIN tasks t ON t.project_id = p.project_id
                WHERE p.parent_project_id=" . intval($project_id) . "
                GROUP BY p.project_id, p.project_title, p.is_closed
                ORDER BY p.project_title";
        $db->query($sql);
        while ($db->next_record()) {
            $subprojects[] = array(
                'id' => $db->f("project_id"),
                'title' => $db->f("project_title"),
                'is_closed' => $db->f("is_closed"),
                'total_tasks' => intval($db->f("total_tasks")),
                'open_tasks' => intval($db->f("open_tasks"))
            );
        }
    }
    
    profile_checkpoint('after_subprojects_load');
    
    $db->query("SELECT IF(parent_project_id IS NULL, 1, 0) AS ifp FROM projects WHERE project_id=" . intval($project_id));
    if ($db->next_record()) {
        $is_parent = $db->f("ifp") == 1;
    }
}

profile_checkpoint('after_subprojects');

// Get project statuses
$status_project_id = !empty($project['parent_project_id']) ? $project['parent_project_id'] : $project_id;
$statuses = array();
if ($status_project_id) {
    $sql = "SELECT project_status_id, status_desc FROM projects_statuses 
            WHERE parent_project_id = " . ToSQL($status_project_id, "integer") . " 
            ORDER BY status_order ASC";
    $db->query($sql);
    while ($db->next_record()) {
        $statuses[] = array(
            'id' => $db->f("project_status_id"),
            'desc' => $db->f("status_desc")
        );
    }
}

// If no project-specific statuses, use default ones
if (empty($statuses)) {
    $statuses = array(
        array('id' => 1, 'desc' => 'Active'),
        array('id' => 2, 'desc' => 'On Hold'),
        array('id' => 3, 'desc' => 'Completed'),
        array('id' => 4, 'desc' => 'Cancelled')
    );
}

// Get responsible users (PMs)
$responsible_users = array();
$sql = "SELECT user_id, first_name, last_name FROM users 
        WHERE is_deleted IS NULL AND privilege_id = " . PRIV_PM . " 
        ORDER BY first_name";
$db->query($sql);
while ($db->next_record()) {
    $responsible_users[] = array(
        'id' => $db->f("user_id"),
        'name' => $db->f("first_name") . ' ' . $db->f("last_name")
    );
}

profile_checkpoint('before_teams');

// Get all users grouped by teams
$teams = array();
$sql = "SELECT t.manager_id, t.team_name, COUNT(u.user_id) AS users_count 
        FROM users_teams t 
        LEFT JOIN users u ON (u.manager_id = t.manager_id OR u.user_id = t.manager_id)
        WHERE (u.is_deleted IS NULL OR u.is_deleted = 0)
        GROUP BY t.team_id 
        ORDER BY t.team_name";
$db->query($sql);
while ($db->next_record()) {
    if ($db->f("users_count") > 0) {
        $manager_id = (int)$db->f("manager_id");
        $teams[$manager_id] = array('title' => $db->f("team_name"), 'users' => array());
    }
}

$sql = "SELECT user_id, manager_id, CONCAT(first_name, ' ', last_name) AS user_name 
        FROM users 
        WHERE (is_deleted IS NULL OR is_deleted = 0) AND is_viart = 1 
        ORDER BY user_name";
$db->query($sql);
while ($db->next_record()) {
    $uid = $db->f("user_id");
    $mid = $db->f("manager_id");
    if ($mid > 0 && isset($teams[$mid])) {
        $teams[$mid]['users'][$uid] = $db->f("user_name");
    }
    if (isset($teams[$uid])) {
        $teams[$uid]['users'][$uid] = $db->f("user_name");
    }
}

profile_checkpoint('after_teams');

// Get Sayu users (non-viart)
$sayu_users = array();
$sql = "SELECT user_id, CONCAT(first_name, ' ', last_name) as user_name 
        FROM users 
        WHERE (is_deleted IS NULL OR is_deleted = 0) AND (is_viart = 0 OR is_viart IS NULL) 
        ORDER BY user_name ASC";
$db->query($sql);
while ($db->next_record()) {
    $sayu_users[] = array(
        'id' => $db->f("user_id"),
        'name' => $db->f("user_name")
    );
}

profile_checkpoint('after_sayu_users');

// Get clients for dropdown
$clients = array();
$sql = "SELECT client_id, client_name FROM clients WHERE client_name IS NOT NULL AND client_name != '' ORDER BY client_name";
$db->query($sql);
while ($db->next_record()) {
    $clients[] = array(
        'id' => $db->f("client_id"),
        'name' => $db->f("client_name")
    );
}

profile_checkpoint('after_clients');

// Helper function
function add_assigned_people($project_id) {
    global $db;
    
    $post_array = $_POST;
    
    $sql = "DELETE FROM users_projects WHERE project_id = " . intval($project_id);
    $db->query($sql);
    
    foreach ($post_array as $key => $value) {
        if (strpos($key, 'assigned_') === 0) {
            $user_id = str_replace('assigned_', '', $key);
            if ($user_id && is_numeric($user_id)) {
                $permissions = 0;
                if (isset($post_array["steps_" . $user_id]) && $post_array["steps_" . $user_id]) {
                    $permissions += CHANGE_PERM;
                }
                $sql = "INSERT INTO users_projects (user_id, project_id, permissions) 
                        VALUES (" . intval($user_id) . ", " . intval($project_id) . ", " . intval($permissions) . ")";
                $db->query($sql);
            }
        }
    }
}

// Format total time
$total_time_formatted = '';
if (!empty($project['total_time'])) {
    $tt = $project['total_time'];
    $total_time_formatted = floor($tt) . ":" . sprintf("%02d", round(($tt - floor($tt)) * 60));
}

profile_checkpoint('before_html_output');

// Display profiling results if enabled
if ($PROFILING_ENABLED) {
    $total_time = round((microtime(true) - $profile_start) * 1000, 2);
    echo "<div style='background: #1a1a2e; color: #00ff88; font-family: monospace; padding: 15px; margin: 0; font-size: 12px; border-bottom: 2px solid #00ff88;'>";
    echo "<strong style='color: #ff6b6b;'>🔍 PROFILING (Total: {$total_time}ms)</strong><br><br>";
    echo "<table style='border-collapse: collapse; width: 100%; color: #eee;'>";
    echo "<tr style='background: #16213e;'><th style='text-align:left; padding: 5px; border: 1px solid #444;'>Checkpoint</th><th style='text-align:right; padding: 5px; border: 1px solid #444;'>Time (ms)</th><th style='text-align:right; padding: 5px; border: 1px solid #444;'>Delta (ms)</th></tr>";
    
    $prev = 0;
    foreach ($profile_times as $name => $time) {
        $delta = round($time - $prev, 2);
        $color = $delta > 100 ? '#ff6b6b' : ($delta > 50 ? '#feca57' : '#00ff88');
        echo "<tr><td style='padding: 5px; border: 1px solid #444;'>{$name}</td>";
        echo "<td style='text-align:right; padding: 5px; border: 1px solid #444;'>{$time}</td>";
        echo "<td style='text-align:right; padding: 5px; border: 1px solid #444; color: {$color};'><strong>+{$delta}</strong></td></tr>";
        $prev = $time;
    }
    echo "</table>";
    echo "<br><strong>Subprojects count:</strong> {$subprojects_count}";
    echo "</div>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_new ? 'New Project' : htmlspecialchars($project['project_title']); ?> - Project</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f5f7fa;
            color: #2d3748;
            line-height: 1.6;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 16px;
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }

        .page-header h2 {
            font-size: 1.2rem;
            font-weight: 600;
        }

        .back-btn {
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            font-size: 0.9rem;
            padding: 8px 16px;
            background: rgba(255,255,255,0.1);
            border-radius: 6px;
            transition: all 0.2s;
        }

        .back-btn:hover {
            background: rgba(255,255,255,0.2);
        }

        /* Card */
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .card-header {
            padding: 12px 16px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-header h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #2d3748;
        }

        .card-body {
            padding: 16px;
        }

        /* Form */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px 16px;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        .form-group.half {
            grid-column: span 2;
        }

        .form-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 4px;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.9rem;
            font-family: inherit;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        textarea.form-control {
            min-height: 80px;
            resize: vertical;
        }

        select.form-control {
            cursor: pointer;
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85rem;
        }

        .form-check input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }

        .form-check label {
            cursor: pointer;
        }

        .form-inline {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Custom Select */
        .custom-select-wrapper {
            position: relative;
        }

        .custom-select {
            position: relative;
        }

        .custom-select-trigger {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: #fff;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.9rem;
        }

        .custom-select-trigger::after {
            content: '▼';
            font-size: 0.7rem;
            color: #a0aec0;
        }

        .custom-select.open .custom-select-trigger::after {
            content: '▲';
        }

        .custom-select-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            min-width: 200px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            z-index: 1000;
            display: none;
            margin-top: 4px;
        }

        .custom-select.open .custom-select-dropdown {
            display: block;
        }

        .custom-select-search {
            padding: 8px;
            border-bottom: 1px solid #e2e8f0;
        }

        .custom-select-search input {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.9rem;
        }

        .custom-select-options {
            max-height: 200px;
            overflow-y: auto;
        }

        .custom-select-option {
            padding: 8px 12px;
            cursor: pointer;
            transition: background 0.15s;
            font-size: 0.85rem;
        }

        .custom-select-option:hover {
            background: #f7fafc;
        }

        .custom-select-option.highlighted {
            background: #667eea;
            color: #fff;
        }

        .custom-select-option.selected {
            background: #edf2f7;
            font-weight: 500;
        }

        .custom-select-option.hidden {
            display: none;
        }

        /* Buttons */
        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }

        .btn-secondary:hover {
            background: #cbd5e0;
        }

        .btn-danger {
            background: #fc8181;
            color: #fff;
        }

        .btn-danger:hover {
            background: #f56565;
        }

        .btn-success {
            background: #48bb78;
            color: #fff;
        }

        .btn-success:hover {
            background: #38a169;
        }

        .btn-warning {
            background: #ecc94b;
            color: #744210;
        }

        .btn-warning:hover {
            background: #d69e2e;
        }

        /* Closed project banner */
        .closed-project-banner {
            background: linear-gradient(135deg, #718096 0%, #4a5568 100%);
            color: #fff;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }
        .closed-project-banner .banner-content {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .closed-project-banner .banner-icon {
            font-size: 1.5rem;
        }
        .closed-project-banner .banner-text h3 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
        }
        .closed-project-banner .banner-text p {
            margin: 2px 0 0;
            font-size: 0.85rem;
            opacity: 0.9;
        }
        .closed-project-banner .btn-reopen {
            background: #fff;
            color: #4a5568;
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
            font-family: inherit;
        }
        .closed-project-banner .btn-reopen:hover {
            background: #f7fafc;
            transform: translateY(-1px);
        }

        /* Confirm popup */
        .confirm-overlay { position: fixed; inset: 0; z-index: 10000; background: rgba(0,0,0,0.35); display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.15s; }
        .confirm-overlay.show { opacity: 1; }
        .confirm-box { background: #fff; border-radius: 14px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); padding: 28px 32px 24px; width: 400px; max-width: 90vw; transform: translateY(12px) scale(0.97); transition: transform 0.15s; }
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

        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
            margin-top: 12px;
        }

        /* Users Section */
        .users-section {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        .users-column h4 {
            font-size: 0.85rem;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 8px;
            padding-bottom: 6px;
            border-bottom: 2px solid #e2e8f0;
        }

        .team-section {
            margin-bottom: 10px;
        }

        .team-name {
            font-size: 0.8rem;
            font-weight: 600;
            color: #667eea;
            margin-bottom: 4px;
        }

        .user-row {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 3px 0;
            font-size: 0.85rem;
        }

        .user-row input[type="checkbox"] {
            width: 14px;
            height: 14px;
        }

        .user-row .active-user {
            font-weight: 600;
            color: #2d3748;
        }

        .user-row .steps-checkbox {
            margin-left: 6px;
            font-size: 0.75rem;
            color: #718096;
        }

        /* Subprojects */
        .subprojects-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }

        .subprojects-search {
            flex: 1;
            max-width: 250px;
            padding: 6px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.85rem;
        }

        .subprojects-count {
            font-size: 0.8rem;
            color: #718096;
        }

        .subprojects-table-wrapper {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }

        .subprojects-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        .subprojects-table th {
            background: #f7fafc;
            padding: 8px 12px;
            text-align: left;
            font-weight: 600;
            color: #4a5568;
            border-bottom: 2px solid #e2e8f0;
            position: sticky;
            top: 0;
            z-index: 2;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .subprojects-table th:nth-child(2),
        .subprojects-table th:nth-child(3),
        .subprojects-table th:nth-child(4) {
            text-align: center;
            width: 80px;
        }

        .subprojects-table td {
            padding: 6px 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        .subprojects-table td:nth-child(2),
        .subprojects-table td:nth-child(3),
        .subprojects-table td:nth-child(4) {
            text-align: center;
        }

        .subprojects-table tr:hover {
            background: #f7fafc;
        }

        .subprojects-table tr.closed-project {
            opacity: 0.5;
            background: #f8f8f8;
        }

        .subprojects-table a {
            color: #4a5568;
            text-decoration: none;
        }

        .subprojects-table a:hover {
            color: #667eea;
        }

        .subprojects-table .close-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.75rem;
            transition: all 0.2s;
        }

        .subprojects-table .close-btn:hover {
            background: #fed7d7;
        }

        .subprojects-table .close-btn.reopen {
            color: #38a169;
        }

        .subprojects-table .close-btn.reopen:hover {
            background: #c6f6d5;
        }

        .subproject-row.hidden {
            display: none;
        }

        .add-subproject-form {
            display: flex;
            gap: 10px;
            align-items: center;
            background: #f0f4ff;
            border: 1px dashed #667eea;
            border-radius: 8px;
            padding: 10px 14px;
        }

        .add-subproject-form input {
            flex: 1;
            max-width: 300px;
        }

        .add-subproject-form label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #4a5568;
            white-space: nowrap;
        }

        /* Flash Messages */
        #flashMessage {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 14px 20px;
            border-radius: 8px;
            color: #fff;
            font-weight: 500;
            z-index: 10000;
            transform: translateX(120%);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            max-width: 350px;
        }

        #flashMessage.show {
            transform: translateX(0);
            opacity: 1;
            visibility: visible;
        }

        #flashMessage.success {
            background: linear-gradient(135deg, #48bb78, #38a169);
        }

        #flashMessage.error {
            background: linear-gradient(135deg, #f56565, #e53e3e);
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10000;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: #fff;
            border-radius: 12px;
            padding: 24px;
            max-width: 400px;
            width: 90%;
            text-align: center;
        }

        .modal-content h3 {
            margin-bottom: 12px;
            color: #2d3748;
        }

        .modal-content p {
            color: #718096;
            margin-bottom: 20px;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        /* Info row */
        .info-row {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 0;
            font-size: 0.9rem;
            color: #718096;
        }

        .info-row strong {
            color: #2d3748;
        }

        @media (max-width: 900px) {
            .form-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .form-group.half {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 600px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            .form-group.half {
                grid-column: 1;
            }
            .users-section {
                grid-template-columns: 1fr;
            }
            .form-actions {
                flex-direction: column;
            }
            .form-inline {
                flex-wrap: wrap;
            }
        }

        /* Dark mode - Subprojects and general */
        html.dark-mode .card-header h3,
        html.dark-mode .subprojects-count { color: #e2e8f0; }
        html.dark-mode .subprojects-search {
            background: #1c2333 !important;
            color: #e2e8f0 !important;
            border-color: #2d333b !important;
        }
        html.dark-mode .subprojects-search::placeholder { color: #6b7280; }
        html.dark-mode .subprojects-header label { color: #cbd5e0; }
        html.dark-mode .subprojects-table-wrapper {
            background: #1c2333;
            border-color: #2d333b;
        }
        html.dark-mode .subprojects-table th {
            background: #161b22 !important;
            color: #a0aec0 !important;
            border-bottom-color: #2d333b !important;
        }
        html.dark-mode .subprojects-table td {
            border-bottom-color: #2d333b;
            color: #e2e8f0;
        }
        html.dark-mode .subprojects-table tr:hover td {
            background: #252d3d;
        }
        html.dark-mode .subprojects-table tr.closed-project td {
            background: #161b22;
        }
        html.dark-mode .subprojects-table a {
            color: #90cdf4;
        }
        html.dark-mode .subprojects-table a:hover {
            color: #63b3ed;
        }
        html.dark-mode .subprojects-table .close-btn {
            color: #a0aec0;
        }
        html.dark-mode .subprojects-table .close-btn:hover {
            background: rgba(220, 53, 69, 0.3);
            color: #fca5a5;
        }
        html.dark-mode .subprojects-table .close-btn.reopen:hover {
            background: rgba(34, 197, 94, 0.3);
            color: #86efac;
        }
        html.dark-mode .add-subproject-form {
            background: rgba(102, 126, 234, 0.12);
            border-color: #4a5568;
        }
        html.dark-mode .add-subproject-form label { color: #a0aec0; }
        html.dark-mode .add-subproject-form input {
            background: #1c2333 !important;
            color: #e2e8f0 !important;
            border-color: #2d333b !important;
        }
        html.dark-mode .add-subproject-form input::placeholder { color: #6b7280; }
        html.dark-mode .card-body p a { color: #90cdf4; }
        html.dark-mode .card-body p a:hover { color: #63b3ed; }
    </style>
</head>
<body>
    <?php include("./includes/modern_header.php"); ?>

    <div id="flashMessage"></div>

    <div class="page-header">
        <div style="display: flex; align-items: center; gap: 15px;">
            <a href="<?php echo htmlspecialchars($return_page); ?>" class="back-btn">← Back</a>
            <h2><?php echo $is_new ? 'New Project' : 'Edit Project'; ?></h2>
        </div>
    </div>

    <div class="container">
        <?php if (!$is_new && $project['is_closed']): ?>
        <div class="closed-project-banner" id="closedBanner">
            <div class="banner-content">
                <span class="banner-icon">&#10003;</span>
                <div class="banner-text">
                    <h3>This project is closed</h3>
                    <p>No new tasks can be created in this project.</p>
                </div>
            </div>
            <button class="btn-reopen" onclick="reopenProject()">&#8635; Reopen Project</button>
        </div>
        <?php endif; ?>

        <form id="projectForm" method="POST">
            <input type="hidden" name="FormName" value="Form">
            <input type="hidden" name="FormAction" value="<?php echo $is_new ? 'insert' : 'update'; ?>">
            <input type="hidden" name="ajax" value="1">
            <input type="hidden" name="return_page" value="<?php echo htmlspecialchars($return_page); ?>">
            <input type="hidden" name="PK_project_id" value="<?php echo $project_id; ?>">
            <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
            <input type="hidden" name="client_id" id="client_id" value="<?php echo isset($project['client_id']) ? $project['client_id'] : 0; ?>">

            <div class="card">
                <div class="card-header">
                    <h3>Project Details</h3>
                </div>
                <div class="card-body">
                    <div class="form-grid">
                        <div class="form-group half">
                            <label class="form-label">Project Title *</label>
                            <input type="text" name="project_title" class="form-control" value="<?php echo htmlspecialchars(isset($project['project_title']) ? $project['project_title'] : ''); ?>" required autocomplete="off">
                        </div>

                        <?php if (empty($subprojects)): ?>
                        <div class="form-group">
                            <label class="form-label">Parent Project</label>
                            <?php 
                            $selected_parent = null;
                            foreach ($parent_projects as $pp) {
                                if (isset($project['parent_project_id']) && $project['parent_project_id'] == $pp['id']) {
                                    $selected_parent = $pp;
                                    break;
                                }
                            }
                            ?>
                            <input type="hidden" name="parent_project_id" id="parentProjectInput" value="<?php echo isset($project['parent_project_id']) ? $project['parent_project_id'] : 0; ?>">
                            <div class="custom-select">
                                <div class="custom-select-trigger" tabindex="0">
                                    <span><?php echo $selected_parent ? htmlspecialchars($selected_parent['title']) : 'None (top-level)'; ?></span>
                                </div>
                                <div class="custom-select-dropdown">
                                    <div class="custom-select-search">
                                        <input type="text" placeholder="Search..." class="select-search-input">
                                    </div>
                                    <div class="custom-select-options">
                                        <div class="custom-select-option <?php echo empty($project['parent_project_id']) ? 'selected' : ''; ?>" data-value="0">None (top-level)</div>
                                        <?php foreach ($parent_projects as $pp): ?>
                                        <div class="custom-select-option <?php echo (isset($project['parent_project_id']) && $project['parent_project_id'] == $pp['id']) ? 'selected' : ''; ?>" 
                                             data-value="<?php echo $pp['id']; ?>">
                                            <?php echo htmlspecialchars($pp['title']); ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="form-group"></div>
                        <?php endif; ?>

                        <div class="form-group half">
                            <label class="form-label">Project URL</label>
                            <input type="text" name="project_url" class="form-control" value="<?php echo htmlspecialchars(isset($project['project_url']) ? $project['project_url'] : ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Client</label>
                            <input type="hidden" name="client_id" id="clientIdInput" value="<?php echo isset($project['client_id']) ? $project['client_id'] : 0; ?>">
                            <div class="custom-select">
                                <div class="custom-select-trigger" tabindex="0">
                                    <span><?php echo !empty($project['client_name']) ? htmlspecialchars($project['client_name']) : 'No Client'; ?></span>
                                </div>
                                <div class="custom-select-dropdown">
                                    <div class="custom-select-search">
                                        <input type="text" placeholder="Search..." class="select-search-input">
                                    </div>
                                    <div class="custom-select-options">
                                        <div class="custom-select-option <?php echo empty($project['client_id']) ? 'selected' : ''; ?>" data-value="0">No Client</div>
                                        <?php foreach ($clients as $c): ?>
                                        <div class="custom-select-option <?php echo (isset($project['client_id']) && $project['client_id'] == $c['id']) ? 'selected' : ''; ?>" 
                                             data-value="<?php echo $c['id']; ?>">
                                            <?php echo htmlspecialchars($c['name']); ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Responsible</label>
                            <?php 
                            $selected_user = null;
                            foreach ($responsible_users as $ru) {
                                if (isset($project['responsible_user_id']) && $project['responsible_user_id'] == $ru['id']) {
                                    $selected_user = $ru;
                                    break;
                                }
                            }
                            ?>
                            <input type="hidden" name="responsible_user_id" id="responsibleUserInput" value="<?php echo isset($project['responsible_user_id']) ? $project['responsible_user_id'] : ''; ?>">
                            <div class="custom-select">
                                <div class="custom-select-trigger" tabindex="0">
                                    <span><?php echo $selected_user ? htmlspecialchars($selected_user['name']) : 'Select person...'; ?></span>
                                </div>
                                <div class="custom-select-dropdown">
                                    <div class="custom-select-search">
                                        <input type="text" placeholder="Search..." class="select-search-input">
                                    </div>
                                    <div class="custom-select-options">
                                        <?php foreach ($responsible_users as $ru): ?>
                                        <div class="custom-select-option <?php echo (isset($project['responsible_user_id']) && $project['responsible_user_id'] == $ru['id']) ? 'selected' : ''; ?>" 
                                             data-value="<?php echo $ru['id']; ?>">
                                            <?php echo htmlspecialchars($ru['name']); ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <?php 
                            $selected_status = null;
                            foreach ($statuses as $s) {
                                if (isset($project['project_status_id']) && $project['project_status_id'] == $s['id']) {
                                    $selected_status = $s;
                                    break;
                                }
                            }
                            ?>
                            <input type="hidden" name="project_status_id" id="statusInput" value="<?php echo isset($project['project_status_id']) ? $project['project_status_id'] : 0; ?>">
                            <div class="custom-select">
                                <div class="custom-select-trigger" tabindex="0">
                                    <span><?php echo $selected_status ? htmlspecialchars($selected_status['desc']) : 'Select status...'; ?></span>
                                </div>
                                <div class="custom-select-dropdown">
                                    <div class="custom-select-search">
                                        <input type="text" placeholder="Search..." class="select-search-input">
                                    </div>
                                    <div class="custom-select-options">
                                        <?php foreach ($statuses as $s): ?>
                                        <div class="custom-select-option <?php echo (isset($project['project_status_id']) && $project['project_status_id'] == $s['id']) ? 'selected' : ''; ?>" 
                                             data-value="<?php echo $s['id']; ?>">
                                            <?php echo htmlspecialchars($s['desc']); ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <?php if ($status_project_id): ?>
                            <a href="projects_statuses.php?parent_project_id=<?php echo $status_project_id; ?>" style="font-size: 0.75rem; color: #667eea;">Manage</a>
                            <?php endif; ?>
                        </div>

                        <div class="form-group full">
                            <label class="form-label">Description</label>
                            <textarea name="project_desc" class="form-control" rows="3"><?php echo htmlspecialchars(isset($project['project_desc']) ? $project['project_desc'] : ''); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">CVS Module</label>
                            <input type="text" name="cvs_module" id="cvs_module" class="form-control" value="<?php echo htmlspecialchars(isset($project['cvs_module']) ? $project['cvs_module'] : ''); ?>">
                        </div>

                        <div class="form-group half">
                            <label class="form-label">Email Copies</label>
                            <input type="text" name="emails_copy" class="form-control" value="<?php echo htmlspecialchars(isset($project['emails_copy']) ? $project['emails_copy'] : ''); ?>" placeholder="email1@example.com, email2@example.com">
                        </div>

                        <div class="form-group form-inline">
                            <div class="form-check">
                                <input type="checkbox" name="is_domain_required" id="is_domain_required" value="1" <?php echo !empty($project['is_domain_required']) ? 'checked' : ''; ?>>
                                <label for="is_domain_required">Domain Required</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" name="is_closed" id="is_closed" value="1" <?php echo !empty($project['is_closed']) ? 'checked' : ''; ?>>
                                <label for="is_closed">Closed</label>
                            </div>
                            <?php if (!$is_new && $total_time_formatted): ?>
                            <div class="info-row" style="margin-left: auto;">
                                <span>Time:</span>
                                <strong><?php echo $total_time_formatted; ?></strong>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!$is_new && $is_parent && $subprojects_count > 0): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Subprojects <span class="subprojects-count">(<?php echo $subprojects_count; ?>)</span></h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($subprojects)): ?>
                    <div class="subprojects-header">
                        <input type="text" class="subprojects-search" id="subprojectsSearch" placeholder="Filter subprojects..." autocomplete="off">
                        <label style="display: inline-flex; align-items: center; gap: 4px; font-size: 0.8rem; color: #4a5568; cursor: pointer; white-space: nowrap;">
                            <input type="checkbox" id="showOpenOnly" checked onchange="filterSubprojects()"> Show only opened
                        </label>
                    </div>
                    <div class="subprojects-table-wrapper">
                        <table class="subprojects-table">
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>Total</th>
                                    <th>Open</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="subprojectsList">
                                <?php foreach ($subprojects as $sp): ?>
                                <tr class="subproject-row <?php echo $sp['is_closed'] ? 'closed-project' : ''; ?>" data-id="<?php echo $sp['id']; ?>">
                                    <td>
                                        <a href="edit_project.php?project_id=<?php echo $sp['id']; ?>">
                                            <?php echo htmlspecialchars($sp['title']); ?>
                                            <?php if ($sp['is_closed']): ?><span style="color: #a0aec0; font-size: 0.75rem;">(closed)</span><?php endif; ?>
                                        </a>
                                    </td>
                                    <td><?php echo $sp['total_tasks']; ?></td>
                                    <td><?php echo $sp['open_tasks'] > 0 ? '<strong style="color: #e53e3e;">' . $sp['open_tasks'] . '</strong>' : '<span style="color: #38a169;">0</span>'; ?></td>
                                    <td>
                                        <?php if ($sp['is_closed']): ?>
                                        <button type="button" class="close-btn reopen" onclick="toggleSubprojectClosed(<?php echo $sp['id']; ?>, 0)" title="Reopen project">↩️</button>
                                        <?php else: ?>
                                        <button type="button" class="close-btn" onclick="toggleSubprojectClosed(<?php echo $sp['id']; ?>, 1)" title="Close project">❌</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p style="color: #718096; font-size: 0.9rem;">Too many subprojects to display (<?php echo $subprojects_count; ?>). <a href="view_project_tasks.php?project_id=<?php echo $project_id; ?>">View all in project tasks</a></p>
                    <?php endif; ?>
                    <div class="add-subproject-form" style="margin-top: 12px;">
                        <label>➕ Add:</label>
                        <input type="text" id="subproject_title" class="form-control" placeholder="Enter new subproject title..." autocomplete="off">
                        <button type="button" class="btn btn-primary" onclick="addSubproject()">Add Subproject</button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3>Assigned Users</h3>
                </div>
                <div class="card-body">
                    <div class="users-section">
                        <div class="users-column" id="ukraineColumn">
                            <h4><label style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer;"><input type="checkbox" id="toggleUkraine" onchange="toggleTeamUsers('ukraineColumn', this.checked)"> Sayu Ukraine</label></h4>
                            <?php foreach ($teams as $manager_id => $team): ?>
                            <?php if (!empty($team['users'])): ?>
                            <div class="team-section">
                                <div class="team-name"><?php echo htmlspecialchars(isset($team['title']) ? $team['title'] : 'Team'); ?></div>
                                <?php foreach ($team['users'] as $uid => $uname): 
                                    $is_active = isset($user_in_project[$uid]);
                                    $is_assigned = array_key_exists($uid, $assigned);
                                    $has_steps = $is_assigned && ($assigned[$uid] & CHANGE_PERM);
                                ?>
                                <div class="user-row">
                                    <input type="checkbox" class="user-assign-checkbox" name="assigned_<?php echo $uid; ?>" value="1" <?php echo $is_assigned ? 'checked' : ''; ?>>
                                    <span class="<?php echo $is_active ? 'active-user' : ''; ?>"><?php echo htmlspecialchars($uname); ?></span>
                                    <span class="steps-checkbox">
                                        <input type="checkbox" name="steps_<?php echo $uid; ?>" value="1" <?php echo $has_steps ? 'checked' : ''; ?>> steps
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>

                        <div class="users-column" id="ukColumn">
                            <h4><label style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer;"><input type="checkbox" id="toggleUK" onchange="toggleTeamUsers('ukColumn', this.checked)"> Sayu UK</label></h4>
                            <?php foreach ($sayu_users as $u): 
                                $is_active = isset($user_in_project[$u['id']]);
                                $is_assigned = array_key_exists($u['id'], $assigned);
                            ?>
                            <div class="user-row">
                                <input type="checkbox" class="user-assign-checkbox" name="assigned_<?php echo $u['id']; ?>" value="1" <?php echo $is_assigned ? 'checked' : ''; ?>>
                                <span class="<?php echo $is_active ? 'active-user' : ''; ?>"><?php echo $u['name']; ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="form-actions">
                        <?php if (!$is_new): ?>
                        <button type="button" class="btn btn-danger" onclick="confirmDelete()">Delete Project</button>
                        <?php if (!$project['is_closed']): ?>
                        <button type="button" class="btn btn-warning" onclick="showCloseProjectConfirm()">&#10003; Close Project</button>
                        <?php else: ?>
                        <button type="button" class="btn btn-success" onclick="reopenProject()">&#8635; Reopen Project</button>
                        <?php endif; ?>
                        <?php endif; ?>
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='<?php echo htmlspecialchars($return_page); ?>'">Cancel</button>
                        <button type="submit" class="btn btn-primary"><?php echo $is_new ? 'Create Project' : 'Save Changes'; ?></button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-content">
            <h3>Delete Project?</h3>
            <p>Are you sure you want to delete this project? This action cannot be undone.</p>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="deleteProject()" id="confirmDeleteBtn">Yes, Delete</button>
            </div>
        </div>
    </div>

    <!-- Generic Confirmation Modal -->
    <div class="modal-overlay" id="confirmModal">
        <div class="modal-content">
            <h3 id="confirmModalTitle">Confirm</h3>
            <p id="confirmModalMessage">Are you sure?</p>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeConfirmModal()">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmModalBtn" onclick="">Confirm</button>
            </div>
        </div>
    </div>

    <script>
    // Flash message
    function showFlash(message, type) {
        var flash = document.getElementById('flashMessage');
        flash.textContent = message;
        flash.className = type + ' show';
        setTimeout(function() {
            flash.className = '';
        }, 4000);
    }

    // Form submission via AJAX
    document.getElementById('projectForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        var form = this;
        var formData = new FormData(form);
        var submitBtn = form.querySelector('button[type="submit"]');
        var originalText = submitBtn.textContent;
        
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';
        
        fetch('edit_project.php', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
            
            if (data.success) {
                showFlash(data.message, 'success');
                if (data.project_id && !<?php echo $project_id ? $project_id : 0; ?>) {
                    // Redirect to edit page for new project
                    setTimeout(function() {
                        window.location.href = 'edit_project.php?project_id=' + data.project_id;
                    }, 1000);
                }
            } else {
                showFlash(data.message || 'Error saving project', 'error');
            }
        })
        .catch(function(error) {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
            showFlash('Error: ' + error.message, 'error');
        });
    });

    // Delete confirmation
    function confirmDelete() {
        document.getElementById('deleteModal').classList.add('active');
        setTimeout(function() {
            document.getElementById('confirmDeleteBtn').focus();
        }, 100);
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.remove('active');
    }

    function deleteProject() {
        var formData = new FormData();
        formData.append('ajax', '1');
        formData.append('FormAction', 'delete');
        formData.append('PK_project_id', '<?php echo $project_id; ?>');
        
        fetch('edit_project.php', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                showFlash(data.message, 'success');
                setTimeout(function() {
                    window.location.href = data.redirect || 'index.php';
                }, 1000);
            } else {
                showFlash(data.message || 'Error deleting project', 'error');
                closeDeleteModal();
            }
        })
        .catch(function(error) {
            showFlash('Error: ' + error.message, 'error');
            closeDeleteModal();
        });
    }

    // Toggle team users
    function toggleTeamUsers(columnId, checked) {
        var column = document.getElementById(columnId);
        if (column) {
            var checkboxes = column.querySelectorAll('.user-assign-checkbox');
            checkboxes.forEach(function(cb) {
                cb.checked = checked;
            });
        }
    }

    // Update team toggle state based on individual checkboxes
    function updateTeamToggle(columnId, toggleId) {
        var column = document.getElementById(columnId);
        var toggle = document.getElementById(toggleId);
        if (column && toggle) {
            var checkboxes = column.querySelectorAll('.user-assign-checkbox');
            var allChecked = true;
            var anyChecked = false;
            checkboxes.forEach(function(cb) {
                if (cb.checked) anyChecked = true;
                else allChecked = false;
            });
            toggle.checked = allChecked;
            toggle.indeterminate = anyChecked && !allChecked;
        }
    }

    // Listen for individual checkbox changes
    document.querySelectorAll('.user-assign-checkbox').forEach(function(cb) {
        cb.addEventListener('change', function() {
            var column = this.closest('.users-column');
            if (column) {
                var toggleId = column.id === 'ukraineColumn' ? 'toggleUkraine' : 'toggleUK';
                updateTeamToggle(column.id, toggleId);
            }
        });
    });

    // Initialize toggle states on page load
    updateTeamToggle('ukraineColumn', 'toggleUkraine');
    updateTeamToggle('ukColumn', 'toggleUK');

    // Subprojects filtering
    function filterSubprojects() {
        var search = document.getElementById('subprojectsSearch');
        var openOnly = document.getElementById('showOpenOnly');
        var query = search ? search.value.toLowerCase() : '';
        var hidesClosed = openOnly ? openOnly.checked : false;
        var rows = document.querySelectorAll('.subproject-row');
        rows.forEach(function(row) {
            var text = row.textContent.toLowerCase();
            var isClosed = row.classList.contains('closed-project');
            var matchesSearch = !query || text.includes(query);
            var matchesOpen = !hidesClosed || !isClosed;
            if (matchesSearch && matchesOpen) {
                row.classList.remove('hidden');
            } else {
                row.classList.add('hidden');
            }
        });
    }

    var subprojectsSearch = document.getElementById('subprojectsSearch');
    if (subprojectsSearch) {
        subprojectsSearch.addEventListener('input', filterSubprojects);
    }

    // Apply filter on page load
    filterSubprojects();

    // Generic confirm modal
    function showConfirmModal(title, message, btnText, btnClass, onConfirm) {
        document.getElementById('confirmModalTitle').textContent = title;
        document.getElementById('confirmModalMessage').textContent = message;
        var btn = document.getElementById('confirmModalBtn');
        btn.textContent = btnText;
        btn.className = 'btn ' + (btnClass || 'btn-primary');
        btn.onclick = function() {
            closeConfirmModal();
            onConfirm();
        };
        document.getElementById('confirmModal').classList.add('active');
        btn.focus();
    }

    function closeConfirmModal() {
        document.getElementById('confirmModal').classList.remove('active');
    }

    // Toggle subproject closed status
    function toggleSubprojectClosed(projectId, isClosed) {
        var action = isClosed ? 'close' : 'reopen';
        showConfirmModal(
            isClosed ? 'Close Subproject?' : 'Reopen Subproject?',
            'Are you sure you want to ' + action + ' this subproject?',
            isClosed ? 'Yes, Close' : 'Yes, Reopen',
            isClosed ? 'btn-danger' : 'btn-primary',
            function() { doToggleSubprojectClosed(projectId, isClosed); }
        );
    }

    function doToggleSubprojectClosed(projectId, isClosed) {
        var formData = new FormData();
        formData.append('ajax', '1');
        formData.append('FormAction', 'toggle_closed');
        formData.append('subproject_id', projectId);
        formData.append('is_closed', isClosed);
        
        fetch('edit_project.php', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                showFlash(data.message, 'success');
                // Update the row
                var row = document.querySelector('.subproject-row[data-id="' + projectId + '"]');
                if (row) {
                    if (isClosed) {
                        row.classList.add('closed-project');
                        row.querySelector('a').innerHTML += ' <span style="color: #a0aec0; font-size: 0.75rem;">(closed)</span>';
                        row.querySelector('td:last-child').innerHTML = '<button type="button" class="close-btn reopen" onclick="toggleSubprojectClosed(' + projectId + ', 0)" title="Reopen project">↩️</button>';
                    } else {
                        row.classList.remove('closed-project');
                        var link = row.querySelector('a');
                        link.innerHTML = link.innerHTML.replace(/ <span[^>]*>\(closed\)<\/span>/, '');
                        row.querySelector('td:last-child').innerHTML = '<button type="button" class="close-btn" onclick="toggleSubprojectClosed(' + projectId + ', 1)" title="Close project">❌</button>';
                    }
                }
            } else {
                showFlash(data.message || 'Error', 'error');
            }
        })
        .catch(function(err) {
            showFlash('Error: ' + err.message, 'error');
        });
    }

    // Add subproject
    function addSubproject() {
        var title = document.getElementById('subproject_title').value.trim();
        if (!title) {
            showFlash('Please enter a subproject title', 'error');
            return;
        }
        
        var formData = new FormData(document.getElementById('projectForm'));
        formData.set('FormAction', 'addsub');
        formData.set('subproject_title', title);
        
        fetch('edit_project.php', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                showFlash(data.message, 'success');
                setTimeout(function() {
                    window.location.reload();
                }, 1000);
            } else {
                showFlash(data.message || 'Error adding subproject', 'error');
            }
        })
        .catch(function(error) {
            showFlash('Error: ' + error.message, 'error');
        });
    }

    // Custom Select functionality
    document.querySelectorAll('.custom-select').forEach(function(select) {
        var trigger = select.querySelector('.custom-select-trigger');
        var dropdown = select.querySelector('.custom-select-dropdown');
        var options = select.querySelectorAll('.custom-select-option');
        var searchInput = select.querySelector('.select-search-input');
        var hiddenInput = select.parentElement.querySelector('input[type="hidden"]');
        var highlightedIndex = -1;

        function getVisibleOptions() {
            return Array.from(options).filter(function(opt) {
                return !opt.classList.contains('hidden');
            });
        }

        function updateHighlight(newIndex) {
            var visible = getVisibleOptions();
            options.forEach(function(o) { o.classList.remove('highlighted'); });
            if (newIndex >= 0 && newIndex < visible.length) {
                highlightedIndex = newIndex;
                visible[newIndex].classList.add('highlighted');
                visible[newIndex].scrollIntoView({ block: 'nearest' });
            }
        }

        trigger.addEventListener('click', function(e) {
            e.stopPropagation();
            document.querySelectorAll('.custom-select.open').forEach(function(other) {
                if (other !== select) other.classList.remove('open');
            });
            select.classList.toggle('open');
            if (select.classList.contains('open') && searchInput) {
                highlightedIndex = -1;
                searchInput.focus();
                searchInput.value = '';
                filterOptions('');
            }
        });

        options.forEach(function(option) {
            option.addEventListener('click', function(e) {
                e.stopPropagation();
                selectOption(option);
            });
        });

        function selectOption(option) {
            options.forEach(function(o) { o.classList.remove('selected', 'highlighted'); });
            option.classList.add('selected');
            trigger.querySelector('span').textContent = option.textContent.trim();
            if (hiddenInput) hiddenInput.value = option.dataset.value;
            select.classList.remove('open');
        }

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                filterOptions(this.value.toLowerCase());
                var visible = getVisibleOptions();
                if (visible.length > 0) {
                    updateHighlight(0);
                } else {
                    highlightedIndex = -1;
                }
            });
            
            searchInput.addEventListener('click', function(e) {
                e.stopPropagation();
            });
            
            searchInput.addEventListener('keydown', function(e) {
                var visible = getVisibleOptions();
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (highlightedIndex === -1 && visible.length > 0) {
                        updateHighlight(0);
                    } else {
                        updateHighlight(Math.min(highlightedIndex + 1, visible.length - 1));
                    }
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    updateHighlight(Math.max(highlightedIndex - 1, 0));
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (highlightedIndex >= 0 && highlightedIndex < visible.length) {
                        selectOption(visible[highlightedIndex]);
                    } else if (visible.length > 0) {
                        selectOption(visible[0]);
                    }
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    select.classList.remove('open');
                }
            });
        }

        function filterOptions(query) {
            options.forEach(function(option) {
                var text = option.textContent.toLowerCase();
                if (text.includes(query)) {
                    option.classList.remove('hidden');
                } else {
                    option.classList.add('hidden');
                }
            });
        }
    });

    // Close dropdowns on outside click
    document.addEventListener('click', function() {
        document.querySelectorAll('.custom-select.open').forEach(function(select) {
            select.classList.remove('open');
        });
    });

    // Close modal on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDeleteModal();
            closeConfirmModal();
            document.querySelectorAll('.custom-select.open').forEach(function(select) {
                select.classList.remove('open');
            });
        }
        // Enter on delete modal confirms deletion
        if (e.key === 'Enter' && document.getElementById('deleteModal').classList.contains('active')) {
            deleteProject();
        }
        // Enter on confirm modal confirms action
        if (e.key === 'Enter' && document.getElementById('confirmModal').classList.contains('active')) {
            document.getElementById('confirmModalBtn').click();
        }
    });

    // Close modals on overlay click
    document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === this) closeDeleteModal();
    });
    document.getElementById('confirmModal').addEventListener('click', function(e) {
        if (e.target === this) closeConfirmModal();
    });

    // ==================== Close / Reopen Project ====================
    function showCloseProjectConfirm() {
        var existing = document.querySelector('.confirm-overlay');
        if (existing) existing.remove();

        var projectTitle = <?php echo json_encode(isset($project['project_title']) ? $project['project_title'] : ''); ?>;
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
            closeProject();
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

    function closeProject() {
        fetch('edit_project.php?project_id=<?php echo $project_id; ?>&ajax=1&FormAction=close_project', {
            method: 'GET'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showFlash('Project closed successfully', 'success');
                setTimeout(function() { window.location.reload(); }, 800);
            } else {
                showFlash(data.message || 'Failed to close project', 'error');
            }
        })
        .catch(function() { showFlash('Network error', 'error'); });
    }

    function reopenProject() {
        fetch('edit_project.php?project_id=<?php echo $project_id; ?>&ajax=1&FormAction=reopen_project', {
            method: 'GET'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showFlash('Project reopened successfully', 'success');
                setTimeout(function() { window.location.reload(); }, 800);
            } else {
                showFlash(data.message || 'Failed to reopen project', 'error');
            }
        })
        .catch(function() { showFlash('Network error', 'error'); });
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text || ''));
        return div.innerHTML;
    }
    </script>
</body>
</html>
