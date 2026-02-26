<?php
include("./includes/common.php");
CheckSecurity(1);

define("ROBOTS_CHECK_PROJECT_ID", 135);

$temp_path = "temp_attachments/";
$path = "attachments/task/";
header("Cache-Control: private");

$task_id = (int) GetParam('task_id');
$action = GetParam('action');
$user_id = GetSessionParam("UserID");
$hash = GetParam("hash") ? GetParam("hash") : substr(md5(time()), 0, 8);
$rp = GetParam("rp");
if (!$rp) {
    $rp = "index.php";
}

$errors = array();
$success = false;

// Handle form submission
if ($action == "insert") {
    $task_title = GetParam("task_title");
    $task_desc = GetParam("task_desc");
    $project_id = (int) GetParam("project_id");
    $sub_project_id = (int) GetParam("sub_project_id");
    $planned_date = array(
        "YEAR" => GetParam("year"),
        "MONTH" => (int) GetParam("month"),
        "DAY" => (int) GetParam("day")
    );
    $task_status_id = (int) GetParam("task_status_id");
    $responsible_user_id = (int) GetParam("responsible_user_id");
    $task_type_id = (int) GetParam("task_type_id");
    $estimated_hours = parseEstimatedHours(GetParam("task_estimated_time"));
    $task_cost = (float) GetParam("task_cost");
    $hourly_charge = (int) GetParam("hourly_charge");
    $client_id = (int) GetParam("client_id");
    $task_domain = GetParam("task_domain");
    $priority_id = (int) GetParam("priority_id");
    
    // Validation
    if (!$task_title) {
        $errors[] = "Title is required";
    }
    if (!$project_id) {
        $errors[] = "Project is required";
    }
    if (!$sub_project_id) {
        $errors[] = "Sub-Project is required";
    }
    if (!$planned_date["YEAR"] || strlen($planned_date["YEAR"]) > 2) {
        $errors[] = "Year is required (2 digits)";
        $planned_date["YEAR"] = date("y");
    }
    if (!$task_status_id) {
        $errors[] = "Status is required";
    }
    if (!$task_type_id) {
        $errors[] = "Type is required";
    }
    if (!$responsible_user_id) {
        $errors[] = "Responsible person is required";
    }
    // Allow empty or zero estimated time; only error when format is invalid (unparseable or negative)
    $param_estimated = trim(GetParam("task_estimated_time"));
    if (strlen($param_estimated) > 0) {
        if ($estimated_hours === false || $estimated_hours < 0) {
            $errors[] = "Estimated Time format is incorrect";
        }
    }
    if ($estimated_hours === false) {
        $estimated_hours = 0;
    }
    
    // Check if domain is required for project
    if ($sub_project_id) {
        $sql = "SELECT is_domain_required FROM projects WHERE project_id = " . $sub_project_id;
        $project_is_domain_required = get_db_value($sql);
        if ($project_is_domain_required && !$task_domain) {
            $errors[] = "Domain is required for this project";
        }
    }
    
    if (empty($errors)) {
        $parent_task_id = $task_id ? $task_id : 0;
        
        if (!$planned_date["DAY"]) {
            $planned_date = "";
        }
        
        // Handle robots.txt check for specific project
        if ($sub_project_id == ROBOTS_CHECK_PROJECT_ID && $task_domain) {
            $task_domain = trim($task_domain);
            $task_domain2 = (strpos($task_domain, "www.") === 0) ? substr($task_domain, 4) : ("www." . $task_domain);
            $robots = @file_get_contents("http://" . $task_domain . "/robots.txt");
            if ($robots !== FALSE && strpos($robots, '<!DOCTYPE') === FALSE) {
                $task_desc .= "\r\n\r\nROBOTS.TXT\r\n--------------\r\n" . $robots;
            } else {
                $robots = @file_get_contents("http://" . $task_domain2 . "/robots.txt");
                if ($robots !== FALSE && strpos($robots, '<!DOCTYPE') === FALSE) {
                    $task_desc .= "\r\nROBOTS.TXT\r\n--------------\r\n" . $robots;
                }
            }
        }
        
        $new_task_id = add_task(
            $responsible_user_id,
            $priority_id,
            $task_status_id,
            $sub_project_id,
            $client_id,
            $task_title,
            $task_desc,
            $planned_date,
            $user_id,
            $estimated_hours,
            $task_type_id,
            $hash
        );
        
        update_task($new_task_id, array(
            "task_cost" => $task_cost,
            "hourly_charge" => $hourly_charge,
            "parent_task_id" => $parent_task_id,
            "task_domain_url" => $task_domain,
        ));
        
        // Set flash message
        $_SESSION['flash_message'] = array(
            'type' => 'success',
            'text' => 'Task "' . $task_title . '" created successfully!',
            'task_id' => $new_task_id
        );
        
        header("Location: " . $rp);
        exit;
    }
} else {
    // Default values
    $task_title = "";
    $task_desc = "";
    $project_id = 0;
    $sub_project_id = 0;
    $responsible_user_id = 0;
    $task_type_id = 1;
    $task_status_id = 7;
    $estimated_hours = "";
    $task_cost = "";
    $hourly_charge = 0;
    $client_id = 0;
    $task_domain = "";
    $priority_id = 1;
    $planned_date = array(
        "YEAR" => date("y"),
        "MONTH" => date("m"),
        "DAY" => ""
    );
    
    // If cloning from existing task
    if ($task_id) {
        $sql = "SELECT * FROM tasks WHERE task_id = " . ToSQL($task_id, "integer");
        $db->query($sql);
        if ($db->next_record()) {
            $task_title = $db->f("task_title");
            $task_desc = $db->f("task_desc");
            $sub_project_id = $db->f("project_id");
            
            $planned_date_str = $db->f("planed_date");
            if (time() < strtotime($planned_date_str)) {
                $tmp = explode("-", $planned_date_str);
                $planned_date = array(
                    "YEAR" => substr($tmp[0], 2),
                    "MONTH" => $tmp[1],
                    "DAY" => $tmp[2]
                );
            }
            $responsible_user_id = $db->f("responsible_user_id");
            $db_estimated = $db->f("estimated_hours");
            $estimated_hours = ($db_estimated !== null && $db_estimated !== '' && (float)$db_estimated > 0) ? (float)$db_estimated : '';
            $task_cost = $db->f("task_cost");
            $hourly_charge = $db->f("hourly_charge");
            $client_id = $db->f("client_id");
            $task_domain = $db->f("task_domain_url");
            $priority_id = $db->f("priority_id") - 1;
            if ($priority_id <= 0) $priority_id = 1;
        }
        
        // Get parent project
        if ($sub_project_id) {
            $sql = "SELECT parent_project_id FROM projects WHERE project_id=" . ToSQL($sub_project_id, "integer");
            $db->query($sql);
            if ($db->next_record()) {
                $project_id = $db->f("parent_project_id");
            }
        }
    }
}

// If project_id passed via URL (e.g. from view_project_tasks), set sub_project_id
if (!$sub_project_id && !$task_id) {
    $url_project_id = (int) GetParam("project_id");
    if ($url_project_id) {
        // Check if this is a sub-project (has parent) or a parent project
        $sql = "SELECT project_id, parent_project_id FROM projects WHERE project_id = " . ToSQL($url_project_id, "integer");
        $db->query($sql);
        if ($db->next_record()) {
            if ($db->f("parent_project_id")) {
                // It's a sub-project
                $sub_project_id = $url_project_id;
                $project_id = (int) $db->f("parent_project_id");
            } else {
                // It's a parent project
                $project_id = $url_project_id;
            }
        }
    }
}

// Load all projects (parent projects)
$projects = array();
$sql = "SELECT DISTINCT p.project_id, p.project_title 
        FROM projects p 
        INNER JOIN projects sp ON sp.parent_project_id = p.project_id
        INNER JOIN users_projects up ON (p.project_id = up.project_id OR sp.project_id = up.project_id)
        WHERE p.parent_project_id IS NULL 
        AND ((p.is_closed IS NULL OR p.is_closed = 0)" . ($project_id ? " OR p.project_id = " . ToSQL($project_id, "integer") : "") . ")
        AND up.user_id = " . ToSQL($user_id, "integer") . "
        ORDER BY p.project_title";
$db->query($sql);
while ($db->next_record()) {
    $projects[] = array(
        'id' => $db->f("project_id"),
        'title' => $db->f("project_title")
    );
}

// Load sub-projects for selected project
$sub_projects = array();
if ($project_id) {
    $sql = "SELECT p.project_id, p.project_title 
            FROM projects p 
            INNER JOIN users_projects up ON p.project_id = up.project_id
            WHERE p.parent_project_id = " . ToSQL($project_id, "integer") . "
            AND ((p.is_closed IS NULL OR p.is_closed = 0)" . ($sub_project_id ? " OR p.project_id = " . ToSQL($sub_project_id, "integer") : "") . ")
            AND up.user_id = " . ToSQL($user_id, "integer") . "
            ORDER BY p.project_title";
    $db->query($sql);
    while ($db->next_record()) {
        $sub_projects[] = array(
            'id' => $db->f("project_id"),
            'title' => $db->f("project_title")
        );
    }
}

// Load users for selected sub-project
$project_users = array();
if ($sub_project_id) {
    $sql = "SELECT u.user_id, u.first_name, u.last_name 
            FROM users u 
            INNER JOIN users_projects up ON u.user_id = up.user_id
            WHERE up.project_id = " . ToSQL($sub_project_id, "integer") . "
            AND u.is_deleted IS NULL
            ORDER BY u.first_name, u.last_name";
    $db->query($sql);
    while ($db->next_record()) {
        $project_users[] = array(
            'id' => $db->f("user_id"),
            'name' => $db->f("first_name") . " " . $db->f("last_name")
        );
    }
}

// Load all users (for initial dropdown)
$all_users = array();
$sql = "SELECT user_id, first_name, last_name FROM users WHERE is_deleted IS NULL ORDER BY first_name, last_name";
$db->query($sql);
while ($db->next_record()) {
    $all_users[] = array(
        'id' => $db->f("user_id"),
        'name' => $db->f("first_name") . " " . $db->f("last_name")
    );
}

// Load task statuses
$statuses = array();
$sql = "SELECT status_id, status_desc FROM lookup_tasks_statuses WHERE status_id != 1 AND usual = 1 ORDER BY sort_order";
$db->query($sql);
while ($db->next_record()) {
    $statuses[] = array(
        'id' => $db->f("status_id"),
        'desc' => $db->f("status_desc")
    );
}

// Load task types
$types = array();
$sql = "SELECT type_id, type_desc FROM lookup_task_types ORDER BY type_desc";
$db->query($sql);
while ($db->next_record()) {
    $types[] = array(
        'id' => $db->f("type_id"),
        'desc' => $db->f("type_desc")
    );
}

// Load clients
$clients = array();
$sql = "SELECT client_id, client_name, client_company FROM clients ORDER BY client_name";
$db->query($sql);
while ($db->next_record()) {
    $name = $db->f("client_name");
    if ($db->f("client_company")) {
        $name .= " (" . $db->f("client_company") . ")";
    }
    $clients[] = array(
        'id' => $db->f("client_id"),
        'name' => $name
    );
}

// Get user name for header
$user_name = "";
$sql = "SELECT first_name, last_name FROM users WHERE user_id = " . ToSQL($user_id, "integer");
$db->query($sql);
if ($db->next_record()) {
    $user_name = $db->f("first_name") . " " . $db->f("last_name");
}

// Helper function
function parseEstimatedHours($estimated_hours) {
    $estimated_hours = str_replace(" ", "", $estimated_hours);
    $strpos_d = strpos($estimated_hours, "d");
    if ($strpos_d === false) {
        $estimated_hours = (float) str_replace(array("h", "hours", "hour"), "", $estimated_hours);
    } else {
        $before_d = substr($estimated_hours, 0, $strpos_d);
        $after_d = substr($estimated_hours, $strpos_d);
        if (strlen((float)$before_d) == strlen($before_d)) {
            $estimated_hours = $before_d * 8 + (float) str_replace(array("h", "hours", "hour", "days", "day"), "", $after_d);
        } else {
            $strpos_h = strpos($estimated_hours, "h");
            $before_h = substr($estimated_hours, 0, $strpos_h);
            $after_h = substr($estimated_hours, $strpos_h);
            if (strlen((float)$before_h) == strlen($before_h)) {
                $estimated_hours = $before_h + (float) 8 * str_replace(array("h", "hours", "hour", "days", "day"), "", $after_h);
            } else {
                return false;
            }
        }
    }
    return $estimated_hours;
}

$months = array(
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Task - Sayu Monitor</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f7fafc;
            min-height: 100vh;
            color: #4a5568;
            font-size: 14px;
            line-height: 1.4;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 16px;
        }

        .page-header {
            margin-bottom: 12px;
        }

        .page-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 4px;
        }

        .breadcrumb {
            color: #718096;
            font-size: 0.8rem;
        }

        .breadcrumb a {
            color: #667eea;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }

        .card-header {
            background: #f8fafc;
            padding: 12px 16px;
            color: #2d3748;
            border-bottom: 1px solid #e2e8f0;
        }

        .card-title {
            font-size: 0.95rem;
            font-weight: 600;
        }

        .card-body {
            padding: 16px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 12px;
        }

        .form-row.full {
            grid-template-columns: 1fr;
        }

        .form-row.three {
            grid-template-columns: 1fr 1fr 1fr;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        .form-label {
            display: block;
            font-weight: 500;
            color: #4a5568;
            margin-bottom: 4px;
            font-size: 0.8rem;
        }

        .form-label .required {
            color: #e53e3e;
        }

        .form-control {
            width: 100%;
            padding: 7px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.85rem;
            font-family: inherit;
            transition: all 0.2s;
            background: #fff;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        input[type="date"].form-control {
            cursor: pointer;
        }

        input[type="date"].form-control::-webkit-calendar-picker-indicator {
            cursor: pointer;
            opacity: 0.6;
            transition: opacity 0.2s;
        }

        input[type="date"].form-control::-webkit-calendar-picker-indicator:hover {
            opacity: 1;
        }

        .form-control::placeholder {
            color: #a0aec0;
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
            transition: min-height 0.3s ease;
        }

        textarea.form-control:focus {
            min-height: 250px;
        }

        select.form-control {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23718096'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 8px center;
            background-size: 14px;
            padding-right: 32px;
        }

        .date-inputs {
            display: flex;
            gap: 8px;
        }

        .date-inputs .form-control {
            flex: 1;
        }

        .date-inputs input[type="text"] {
            width: 50px;
            flex: none;
            text-align: center;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 7px 0;
        }

        .checkbox-group input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }

        .checkbox-group label {
            cursor: pointer;
            font-size: 0.85rem;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
            margin-top: 16px;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            font-family: inherit;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-primary {
            background: #667eea;
            color: #fff;
        }

        .btn-primary:hover {
            background: #5a67d8;
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }

        .btn-secondary:hover {
            background: #cbd5e0;
        }

        .alert {
            padding: 10px 14px;
            border-radius: 6px;
            margin-bottom: 12px;
            font-size: 0.85rem;
        }

        .alert-error {
            background: #fed7d7;
            color: #c53030;
            border: 1px solid #fc8181;
        }

        .alert-error ul {
            margin: 0;
            padding-left: 20px;
        }

        .help-text {
            font-size: 0.7rem;
            color: #a0aec0;
            margin-top: 2px;
        }

        /* Custom searchable dropdown styles */
        .custom-select-wrapper {
            position: relative;
        }

        .custom-select {
            position: relative;
        }

        .custom-select-trigger {
            width: 100%;
            padding: 7px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.85rem;
            background: #fff;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s;
        }

        .custom-select-trigger:hover {
            border-color: #cbd5e0;
        }

        .custom-select.open .custom-select-trigger {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .custom-select-trigger .placeholder {
            color: #a0aec0;
        }

        .custom-select-trigger svg {
            width: 14px;
            height: 14px;
            color: #718096;
            transition: transform 0.2s;
        }

        .custom-select.open .custom-select-trigger svg {
            transform: rotate(180deg);
        }

        .custom-select-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            z-index: 1000;
            display: none;
            margin-top: 4px;
            max-height: 300px;
            overflow: hidden;
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
            padding: 6px 8px;
            font-size: 0.8rem;
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.85rem;
            font-family: inherit;
        }

        .custom-select-search input:focus {
            outline: none;
            border-color: #667eea;
        }

        .custom-select-options {
            max-height: 200px;
            overflow-y: auto;
        }

        .custom-select-option {
            padding: 7px 10px;
            cursor: pointer;
            transition: background 0.15s;
            font-size: 0.85rem;
        }

        .custom-select-option:hover {
            background: #f7fafc;
        }

        .custom-select-option.selected {
            background: #ebf4ff;
            color: #667eea;
            font-weight: 500;
        }

        .custom-select-option.highlighted {
            background: #edf2f7;
        }

        .custom-select-option.hidden {
            display: none;
        }

        .custom-select-trigger:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
        }

        .custom-select-empty {
            padding: 14px;
            text-align: center;
            color: #a0aec0;
            font-size: 0.8rem;
        }

        /* Attachment styles */
        .attachment-area {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            overflow: hidden;
        }

        .attachment-dropzone {
            padding: 20px;
            text-align: center;
            background: #f8fafc;
            border-bottom: 1px dashed #e2e8f0;
            color: #718096;
            font-size: 0.85rem;
            transition: all 0.2s;
        }

        .attachment-dropzone.dragover {
            background: #ebf4ff;
            border-color: #667eea;
            color: #667eea;
        }

        .attachment-dropzone svg {
            display: block;
            margin: 0 auto 8px;
            color: #a0aec0;
        }

        .attachment-dropzone .file-label {
            color: #667eea;
            cursor: pointer;
            font-weight: 500;
        }

        .attachment-dropzone .file-label:hover {
            text-decoration: underline;
        }

        .attachment-list {
            max-height: 150px;
            overflow-y: auto;
        }

        .attachment-item {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.8rem;
            gap: 10px;
        }

        .attachment-item:last-child {
            border-bottom: none;
        }

        .attachment-item .file-icon {
            width: 28px;
            height: 28px;
            background: #f1f5f9;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .attachment-item .file-icon.image {
            background: #fef3c7;
            color: #d97706;
        }

        .attachment-item .file-icon.document {
            background: #dbeafe;
            color: #2563eb;
        }

        .attachment-item .file-info {
            flex: 1;
            min-width: 0;
        }

        .attachment-item .file-name {
            font-weight: 500;
            color: #2d3748;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .attachment-item .file-size {
            color: #a0aec0;
            font-size: 0.7rem;
        }

        .attachment-item .file-preview {
            width: 36px;
            height: 36px;
            border-radius: 4px;
            object-fit: cover;
            flex-shrink: 0;
        }

        .attachment-item .remove-file {
            color: #e53e3e;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: background 0.15s;
        }

        .attachment-item .remove-file:hover {
            background: #fed7d7;
        }

        /* Dark mode – attachments area */
        html.dark-mode .attachment-dropzone {
            background: #1c2333;
            border-bottom-color: #2d333b;
            color: #a0aec0;
        }
        html.dark-mode .attachment-dropzone.dragover {
            background: #252d3d;
            border-color: #667eea;
            color: #93c5fd;
        }
        html.dark-mode .attachment-dropzone svg {
            color: #8b949e;
        }
        html.dark-mode .attachment-dropzone .file-label {
            color: #93c5fd;
        }
        html.dark-mode .attachment-item {
            border-bottom-color: #2d333b;
        }
        html.dark-mode .attachment-item .file-icon {
            background: #2d333b;
            color: #a0aec0;
        }
        html.dark-mode .attachment-item .file-icon.image {
            background: #3d2e1f;
            color: #fbbf24;
        }
        html.dark-mode .attachment-item .file-icon.document {
            background: #1e2a3d;
            color: #93c5fd;
        }
        html.dark-mode .attachment-item .file-name {
            color: #e2e8f0;
        }
        html.dark-mode .attachment-item .file-size {
            color: #8b949e;
        }
        html.dark-mode .attachment-item .remove-file {
            color: #fca5a5;
        }
        html.dark-mode .attachment-item .remove-file:hover {
            background: rgba(220, 38, 38, 0.2);
        }

        /* Dark mode – project/search dropdowns */
        html.dark-mode .custom-select-dropdown {
            background: #161b22;
            border-color: #2d333b;
            box-shadow: 0 4px 12px rgba(0,0,0,0.5);
        }
        html.dark-mode .custom-select-search {
            border-bottom-color: #2d333b;
        }
        html.dark-mode .custom-select-search input {
            background: #1c2333;
            border-color: #2d333b;
            color: #e2e8f0;
        }
        html.dark-mode .custom-select-search input::placeholder {
            color: #8b949e;
        }
        html.dark-mode .custom-select-search input:focus {
            border-color: #667eea;
        }
        html.dark-mode .custom-select-option {
            color: #e2e8f0;
        }
        html.dark-mode .custom-select-option:hover {
            background: #1c2333;
        }
        html.dark-mode .custom-select-option.selected {
            background: rgba(102, 126, 234, 0.2);
            color: #93c5fd;
        }
        html.dark-mode .custom-select-option.highlighted {
            background: #252d3d;
            color: #e2e8f0;
        }
        html.dark-mode .custom-select-empty {
            color: #8b949e;
        }

        .paste-indicator {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(102, 126, 234, 0.95);
            color: #fff;
            padding: 16px 24px;
            border-radius: 8px;
            font-weight: 500;
            z-index: 9999;
            display: none;
        }

        /* Domain autocomplete */
        .autocomplete-wrapper {
            position: relative;
        }

        .autocomplete-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            z-index: 1000;
            max-height: 200px;
            overflow-y: auto;
            display: none;
            margin-top: 2px;
        }

        .autocomplete-dropdown.show {
            display: block;
        }

        .autocomplete-item {
            padding: 8px 12px;
            cursor: pointer;
            font-size: 0.85rem;
            border-bottom: 1px solid #f1f5f9;
        }

        .autocomplete-item:last-child {
            border-bottom: none;
        }

        .autocomplete-item:hover,
        .autocomplete-item.highlighted {
            background: #f7fafc;
        }

        .autocomplete-item .domain-url {
            font-weight: 500;
            color: #2d3748;
        }

        .autocomplete-item .client-name {
            font-size: 0.75rem;
            color: #718096;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-row.three {
                grid-template-columns: 1fr;
            }

            .container {
                padding: 12px;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include("./includes/modern_header.php"); ?>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title"><?php echo $task_id ? 'Create Child Task' : 'Create New Task'; ?></h1>
            <div class="breadcrumb">
                <a href="index.php">Dashboard</a> / Create Task
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <span class="card-title">Task Details</span>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <form method="POST" id="taskForm">
                    <input type="hidden" name="action" value="insert">
                    <input type="hidden" name="rp" value="<?php echo htmlspecialchars($rp); ?>">
                    <input type="hidden" name="task_id" value="<?php echo $task_id; ?>">
                    <input type="hidden" name="hash" value="<?php echo htmlspecialchars($hash); ?>">

                    <div class="form-row full">
                        <div class="form-group">
                            <label class="form-label">Title <span class="required">*</span></label>
                            <input type="text" name="task_title" class="form-control" value="<?php echo htmlspecialchars($task_title); ?>" placeholder="Enter task title" required autofocus autocomplete="off">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Project <span class="required">*</span></label>
                            <div class="custom-select" id="projectSelect" data-name="project_id" data-value="<?php echo $project_id; ?>">
                                <div class="custom-select-trigger">
                                    <span><?php 
                                        $selected_project = '';
                                        foreach ($projects as $p) {
                                            if ($p['id'] == $project_id) {
                                                $selected_project = htmlspecialchars($p['title']);
                                                break;
                                            }
                                        }
                                        echo $selected_project ?: '<span class="placeholder">Select a project</span>';
                                    ?></span>
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                                <div class="custom-select-dropdown">
                                    <div class="custom-select-search">
                                        <input type="text" placeholder="Search projects...">
                                    </div>
                                    <div class="custom-select-options">
                                        <?php foreach ($projects as $p): ?>
                                        <div class="custom-select-option<?php echo $p['id'] == $project_id ? ' selected' : ''; ?>" data-value="<?php echo $p['id']; ?>">
                                            <?php echo htmlspecialchars($p['title']); ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Sub-Project <span class="required">*</span></label>
                            <div class="custom-select" id="subProjectSelect" data-name="sub_project_id" data-value="<?php echo $sub_project_id; ?>">
                                <div class="custom-select-trigger">
                                    <span><?php 
                                        $selected_subproject = '';
                                        foreach ($sub_projects as $sp) {
                                            if ($sp['id'] == $sub_project_id) {
                                                $selected_subproject = htmlspecialchars($sp['title']);
                                                break;
                                            }
                                        }
                                        echo $selected_subproject ?: '<span class="placeholder">Select a sub-project</span>';
                                    ?></span>
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                                <div class="custom-select-dropdown">
                                    <div class="custom-select-search">
                                        <input type="text" placeholder="Search sub-projects...">
                                    </div>
                                    <div class="custom-select-options">
                                        <?php foreach ($sub_projects as $sp): ?>
                                        <div class="custom-select-option<?php echo $sp['id'] == $sub_project_id ? ' selected' : ''; ?>" data-value="<?php echo $sp['id']; ?>">
                                            <?php echo htmlspecialchars($sp['title']); ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="sub_project_id" value="<?php echo $sub_project_id; ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Responsible Person <span class="required">*</span></label>
                            <div class="custom-select" id="userSelect" data-name="responsible_user_id" data-value="<?php echo $responsible_user_id; ?>">
                                <div class="custom-select-trigger">
                                    <span><?php 
                                        $selected_user = '';
                                        $users_to_check = !empty($project_users) ? $project_users : $all_users;
                                        foreach ($users_to_check as $u) {
                                            if ($u['id'] == $responsible_user_id) {
                                                $selected_user = htmlspecialchars($u['name']);
                                                break;
                                            }
                                        }
                                        echo $selected_user ?: '<span class="placeholder">Select a person</span>';
                                    ?></span>
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                                <div class="custom-select-dropdown">
                                    <div class="custom-select-search">
                                        <input type="text" placeholder="Search users...">
                                    </div>
                                    <div class="custom-select-options">
                                        <?php 
                                        $users_to_show = !empty($project_users) ? $project_users : $all_users;
                                        foreach ($users_to_show as $u): ?>
                                        <div class="custom-select-option<?php echo $u['id'] == $responsible_user_id ? ' selected' : ''; ?>" data-value="<?php echo $u['id']; ?>">
                                            <?php echo htmlspecialchars($u['name']); ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="responsible_user_id" value="<?php echo $responsible_user_id; ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Priority</label>
                            <input type="number" name="priority_id" class="form-control" value="<?php echo $priority_id; ?>" min="1" max="99" placeholder="1">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Status <span class="required">*</span></label>
                            <div class="custom-select" id="statusSelect" data-name="task_status_id" data-value="<?php echo $task_status_id; ?>">
                                <div class="custom-select-trigger">
                                    <span><?php 
                                        $selected_status = '';
                                        foreach ($statuses as $s) {
                                            if ($s['id'] == $task_status_id) {
                                                $selected_status = htmlspecialchars($s['desc']);
                                                break;
                                            }
                                        }
                                        echo $selected_status ?: '<span class="placeholder">Select status</span>';
                                    ?></span>
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                                <div class="custom-select-dropdown">
                                    <div class="custom-select-search">
                                        <input type="text" placeholder="Search...">
                                    </div>
                                    <div class="custom-select-options">
                                        <?php foreach ($statuses as $s): ?>
                                        <div class="custom-select-option<?php echo $s['id'] == $task_status_id ? ' selected' : ''; ?>" data-value="<?php echo $s['id']; ?>">
                                            <?php echo htmlspecialchars($s['desc']); ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="task_status_id" value="<?php echo $task_status_id; ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Type <span class="required">*</span></label>
                            <div class="custom-select" id="typeSelect" data-name="task_type_id" data-value="<?php echo $task_type_id; ?>">
                                <div class="custom-select-trigger">
                                    <span><?php 
                                        $selected_type = '';
                                        foreach ($types as $t) {
                                            if ($t['id'] == $task_type_id) {
                                                $selected_type = htmlspecialchars($t['desc']);
                                                break;
                                            }
                                        }
                                        echo $selected_type ?: '<span class="placeholder">Select type</span>';
                                    ?></span>
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                                <div class="custom-select-dropdown">
                                    <div class="custom-select-search">
                                        <input type="text" placeholder="Search...">
                                    </div>
                                    <div class="custom-select-options">
                                        <?php foreach ($types as $t): ?>
                                        <div class="custom-select-option<?php echo $t['id'] == $task_type_id ? ' selected' : ''; ?>" data-value="<?php echo $t['id']; ?>">
                                            <?php echo htmlspecialchars($t['desc']); ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="task_type_id" value="<?php echo $task_type_id; ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Date to Complete</label>
                            <?php 
                            $deadline_value = '';
                            if (is_array($planned_date) && $planned_date['DAY'] && $planned_date['MONTH'] && $planned_date['YEAR']) {
                                $full_year = strlen($planned_date['YEAR']) == 2 ? '20' . $planned_date['YEAR'] : $planned_date['YEAR'];
                                $deadline_value = $full_year . '-' . str_pad($planned_date['MONTH'], 2, '0', STR_PAD_LEFT) . '-' . str_pad($planned_date['DAY'], 2, '0', STR_PAD_LEFT);
                            }
                            ?>
                            <input type="date" id="deadlinePicker" class="form-control" 
                                   value="<?php echo $deadline_value; ?>"
                                   onchange="updateDeadlineFields(this.value)">
                            <!-- Hidden fields for form compatibility -->
                            <input type="hidden" name="day" id="deadlineDay" value="<?php echo is_array($planned_date) ? $planned_date['DAY'] : ''; ?>">
                            <input type="hidden" name="month" id="deadlineMonth" value="<?php echo is_array($planned_date) ? $planned_date['MONTH'] : date('m'); ?>">
                            <input type="hidden" name="year" id="deadlineYear" value="<?php echo is_array($planned_date) ? $planned_date['YEAR'] : date('y'); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Estimated Time</label>
                            <input type="text" name="task_estimated_time" class="form-control" value="<?php echo $estimated_hours; ?>" placeholder="e.g., 2h, 3d, 1d 4h">
                            <div class="help-text">Formats: 2h, 3 hours, 2d, 1d 4h</div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Client</label>
                            <div class="custom-select" id="clientSelect" data-name="client_id" data-value="<?php echo $client_id; ?>">
                                <div class="custom-select-trigger">
                                    <span><?php 
                                        $selected_client = '';
                                        foreach ($clients as $c) {
                                            if ($c['id'] == $client_id) {
                                                $selected_client = htmlspecialchars($c['name']);
                                                break;
                                            }
                                        }
                                        echo $selected_client ?: '<span class="placeholder">No client</span>';
                                    ?></span>
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                                <div class="custom-select-dropdown">
                                    <div class="custom-select-search">
                                        <input type="text" placeholder="Search clients...">
                                    </div>
                                    <div class="custom-select-options">
                                        <div class="custom-select-option<?php echo !$client_id ? ' selected' : ''; ?>" data-value="0">No client</div>
                                        <?php foreach ($clients as $c): ?>
                                        <div class="custom-select-option<?php echo $c['id'] == $client_id ? ' selected' : ''; ?>" data-value="<?php echo $c['id']; ?>">
                                            <?php echo htmlspecialchars($c['name']); ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Domain <a href="#" id="domainSearchLink" target="_blank" title="Search clients by this domain" style="color: #718096; text-decoration: none; font-size: 0.9em; display: none;" onmouseover="this.style.color='#667eea'" onmouseout="this.style.color='#718096'">&#128269;</a></label>
                            <div class="autocomplete-wrapper">
                                <input type="text" name="task_domain" id="task_domain" class="form-control" value="<?php echo htmlspecialchars($task_domain); ?>" placeholder="example.com" autocomplete="off">
                                <div class="autocomplete-dropdown" id="domainDropdown"></div>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="task_cost" value="">
                    <input type="hidden" name="hourly_charge" value="0">

                    <div class="form-row full">
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea name="task_desc" id="task_desc" class="form-control" placeholder="Enter task description... (You can paste images here)"><?php echo htmlspecialchars($task_desc); ?></textarea>
                            <div class="help-text">Tip: Paste images (Ctrl+V) directly into description to attach them</div>
                        </div>
                    </div>

                    <div class="form-row full">
                        <div class="form-group">
                            <label class="form-label">Attachments</label>
                            <div class="attachment-area" id="attachmentArea">
                                <div class="attachment-dropzone" id="dropzone">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                    </svg>
                                    <span>Drop files here or <label for="fileInput" class="file-label">browse</label></span>
                                    <input type="file" id="fileInput" multiple style="display: none;">
                                </div>
                                <div class="attachment-list" id="attachmentList"></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="<?php echo htmlspecialchars($rp); ?>" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Create Task
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    // Update deadline hidden fields when date picker changes
    function updateDeadlineFields(dateValue) {
        if (dateValue) {
            const date = new Date(dateValue);
            document.getElementById('deadlineDay').value = String(date.getDate()).padStart(2, '0');
            document.getElementById('deadlineMonth').value = date.getMonth() + 1;
            // Use 2-digit year for compatibility
            document.getElementById('deadlineYear').value = String(date.getFullYear()).slice(-2);
        } else {
            document.getElementById('deadlineDay').value = '';
            document.getElementById('deadlineMonth').value = new Date().getMonth() + 1;
            document.getElementById('deadlineYear').value = String(new Date().getFullYear()).slice(-2);
        }
    }

    // Custom Select Dropdown functionality
    function initCustomSelect(select) {
        var trigger = select.querySelector('.custom-select-trigger');
        var searchInput = select.querySelector('.custom-select-search input');
        var hiddenInput = select.nextElementSibling;
        
        // Make trigger focusable with Tab
        trigger.setAttribute('tabindex', '0');
        
        // Open on focus/click
        trigger.addEventListener('click', function(e) {
            e.stopPropagation();
            openDropdown(select);
        });
        
        trigger.addEventListener('focus', function(e) {
            // Don't auto-open on tab, just allow keyboard interaction
        });
        
        // Keyboard on trigger (when closed)
        trigger.addEventListener('keydown', function(e) {
            if (!select.classList.contains('open')) {
                if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown') {
                    e.preventDefault();
                    openDropdown(select);
                }
            }
        });
        
        // Search functionality
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                filterOptions(select, this.value);
                // Reset highlight to first visible option
                highlightFirstVisible(select);
            });
            
            searchInput.addEventListener('click', function(e) {
                e.stopPropagation();
            });
            
            // Keyboard navigation in search input
            searchInput.addEventListener('keydown', function(e) {
                handleDropdownKeyboard(e, select);
            });
        }
        
        // Initial option binding
        bindOptionClicks(select);
    }
    
    function openDropdown(select) {
        var searchInput = select.querySelector('.custom-select-search input');
        
        // Close other dropdowns
        document.querySelectorAll('.custom-select.open').forEach(function(other) {
            if (other !== select) other.classList.remove('open');
        });
        
        select.classList.add('open');
        
        if (searchInput) {
            searchInput.value = '';
            filterOptions(select, '');
            setTimeout(function() { 
                searchInput.focus();
                highlightFirstVisible(select);
            }, 50);
        }
    }
    
    function closeDropdown(select, focusNext) {
        select.classList.remove('open');
        clearHighlight(select);
        
        if (focusNext) {
            // Move to next focusable element
            var allSelects = Array.from(document.querySelectorAll('.custom-select'));
            var currentIndex = allSelects.indexOf(select);
            if (currentIndex < allSelects.length - 1) {
                // Focus next custom select
                var nextTrigger = allSelects[currentIndex + 1].querySelector('.custom-select-trigger');
                setTimeout(function() { nextTrigger.focus(); }, 10);
            } else {
                // Focus next form field after dropdowns
                var nextField = document.querySelector('input[name="priority_id"]');
                if (nextField) setTimeout(function() { nextField.focus(); }, 10);
            }
        }
    }
    
    function handleDropdownKeyboard(e, select) {
        var options = getVisibleOptions(select);
        var highlighted = select.querySelector('.custom-select-option.highlighted');
        var currentIndex = highlighted ? Array.from(options).indexOf(highlighted) : -1;
        
        switch(e.key) {
            case 'ArrowDown':
                e.preventDefault();
                if (currentIndex < options.length - 1) {
                    setHighlight(select, options[currentIndex + 1]);
                } else if (options.length > 0) {
                    setHighlight(select, options[0]);
                }
                break;
                
            case 'ArrowUp':
                e.preventDefault();
                if (currentIndex > 0) {
                    setHighlight(select, options[currentIndex - 1]);
                } else if (options.length > 0) {
                    setHighlight(select, options[options.length - 1]);
                }
                break;
                
            case 'Enter':
                e.preventDefault();
                if (highlighted) {
                    selectOption(select, highlighted);
                } else if (options.length === 1) {
                    selectOption(select, options[0]);
                }
                break;
                
            case 'Tab':
                // Select current or first option, then move to next field
                if (highlighted) {
                    e.preventDefault();
                    selectOption(select, highlighted, true);
                } else if (options.length === 1) {
                    e.preventDefault();
                    selectOption(select, options[0], true);
                } else {
                    closeDropdown(select, false);
                }
                break;
                
            case 'Escape':
                e.preventDefault();
                closeDropdown(select, false);
                select.querySelector('.custom-select-trigger').focus();
                break;
        }
    }
    
    function getVisibleOptions(select) {
        return select.querySelectorAll('.custom-select-option:not(.hidden)');
    }
    
    function highlightFirstVisible(select) {
        clearHighlight(select);
        var options = getVisibleOptions(select);
        if (options.length > 0) {
            options[0].classList.add('highlighted');
            scrollOptionIntoView(options[0]);
        }
    }
    
    function setHighlight(select, option) {
        clearHighlight(select);
        option.classList.add('highlighted');
        scrollOptionIntoView(option);
    }
    
    function clearHighlight(select) {
        select.querySelectorAll('.custom-select-option.highlighted').forEach(function(opt) {
            opt.classList.remove('highlighted');
        });
    }
    
    function scrollOptionIntoView(option) {
        var container = option.closest('.custom-select-options');
        if (container) {
            var optionTop = option.offsetTop;
            var optionBottom = optionTop + option.offsetHeight;
            var containerTop = container.scrollTop;
            var containerBottom = containerTop + container.clientHeight;
            
            if (optionTop < containerTop) {
                container.scrollTop = optionTop;
            } else if (optionBottom > containerBottom) {
                container.scrollTop = optionBottom - container.clientHeight;
            }
        }
    }
    
    function selectOption(select, option, focusNext) {
        var trigger = select.querySelector('.custom-select-trigger');
        var searchInput = select.querySelector('.custom-select-search input');
        var hiddenInput = select.nextElementSibling;
        var value = option.dataset.value;
        var text = option.textContent.trim();
        
        // Update trigger text
        trigger.querySelector('span').textContent = text;
        trigger.querySelector('span').classList.remove('placeholder');
        
        // Update hidden input
        if (hiddenInput) {
            hiddenInput.value = value;
        }
        
        // Update selected state
        select.querySelectorAll('.custom-select-option').forEach(function(opt) { 
            opt.classList.remove('selected'); 
        });
        option.classList.add('selected');
        
        // Close dropdown
        closeDropdown(select, focusNext);
        
        // Clear search
        if (searchInput) searchInput.value = '';
        filterOptions(select, '');
        
        // Trigger change event for dependent dropdowns
        if (select.id === 'projectSelect') {
            loadSubProjects(value);
        } else if (select.id === 'subProjectSelect') {
            loadProjectUsers(value);
        } else if (select.id === 'userSelect') {
            // Populate task description when a person is selected (only if empty)
            var taskDesc = document.getElementById('task_desc');
            if (taskDesc && taskDesc.value.trim() === '') {
                var firstName = (text.split(/\s+/)[0] || text).trim();
                taskDesc.value = 'Hi ' + firstName + ',\n\n';
            }
        }
    }
    
    function filterOptions(select, query) {
        var options = select.querySelectorAll('.custom-select-option');
        query = query.toLowerCase();
        options.forEach(function(option) {
            var text = option.textContent.toLowerCase();
            if (text.includes(query)) {
                option.classList.remove('hidden');
            } else {
                option.classList.add('hidden');
            }
        });
    }
    
    function bindOptionClicks(select) {
        var options = select.querySelectorAll('.custom-select-option');
        
        options.forEach(function(option) {
            option.addEventListener('click', function(e) {
                e.stopPropagation();
                selectOption(select, this, false);
            });
            
            // Highlight on hover
            option.addEventListener('mouseenter', function() {
                setHighlight(select, this);
            });
        });
    }
    
    // Initialize all custom selects
    document.querySelectorAll('.custom-select').forEach(function(select) {
        initCustomSelect(select);
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function() {
        document.querySelectorAll('.custom-select.open').forEach(function(select) {
            select.classList.remove('open');
        });
    });
    
    // Load sub-projects when project changes
    function loadSubProjects(projectId) {
        if (!projectId) return;
        
        fetch('ajax_responder.php?action=get_subprojects&project_id=' + projectId)
            .then(response => response.json())
            .then(data => {
                var select = document.getElementById('subProjectSelect');
                var optionsContainer = select.querySelector('.custom-select-options');
                var trigger = select.querySelector('.custom-select-trigger span');
                var hiddenInput = document.querySelector('input[name="sub_project_id"]');
                
                // Clear and rebuild options
                optionsContainer.innerHTML = '';
                data.forEach(function(item) {
                    var option = document.createElement('div');
                    option.className = 'custom-select-option';
                    option.dataset.value = item.id;
                    option.textContent = item.title;
                    optionsContainer.appendChild(option);
                });
                
                // Reset selection
                trigger.innerHTML = '<span class="placeholder">Select a sub-project</span>';
                hiddenInput.value = '';
                
                // Rebind click handlers for new options
                bindOptionClicks(select);
                
                // Also reset user selection
                resetUserSelect();
            });
    }
    
    // Load users when sub-project changes
    function loadProjectUsers(subProjectId) {
        if (!subProjectId) return;
        
        fetch('ajax_responder.php?action=get_project_users&project_id=' + subProjectId)
            .then(response => response.json())
            .then(data => {
                var select = document.getElementById('userSelect');
                var optionsContainer = select.querySelector('.custom-select-options');
                var trigger = select.querySelector('.custom-select-trigger span');
                var hiddenInput = document.querySelector('input[name="responsible_user_id"]');
                
                // Clear and rebuild options
                optionsContainer.innerHTML = '';
                data.forEach(function(item) {
                    var option = document.createElement('div');
                    option.className = 'custom-select-option';
                    option.dataset.value = item.id;
                    option.textContent = item.name;
                    optionsContainer.appendChild(option);
                });
                
                // Reset selection
                trigger.innerHTML = '<span class="placeholder">Select a person</span>';
                hiddenInput.value = '';
                
                // Rebind click handlers for new options
                bindOptionClicks(select);
            });
    }
    
    function resetUserSelect() {
        var select = document.getElementById('userSelect');
        var trigger = select.querySelector('.custom-select-trigger span');
        var hiddenInput = document.querySelector('input[name="responsible_user_id"]');
        var optionsContainer = select.querySelector('.custom-select-options');
        
        trigger.innerHTML = '<span class="placeholder">Select a person</span>';
        hiddenInput.value = '';
        optionsContainer.innerHTML = '<div class="custom-select-empty">Select a sub-project first</div>';
    }
    
    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.custom-select.open').forEach(function(select) {
                select.classList.remove('open');
            });
        }
    });

    // ==================== File Attachment Handling ====================
    var attachments = [];
    var attachmentCounter = 0;
    var hashValue = document.querySelector('input[name="hash"]').value;
    
    var dropzone = document.getElementById('dropzone');
    var fileInput = document.getElementById('fileInput');
    var attachmentList = document.getElementById('attachmentList');
    var taskDesc = document.getElementById('task_desc');
    
    // File input change
    fileInput.addEventListener('change', function(e) {
        handleFiles(e.target.files);
        this.value = ''; // Reset input
    });
    
    // Drag and drop
    dropzone.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('dragover');
    });
    
    dropzone.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
    });
    
    dropzone.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
        handleFiles(e.dataTransfer.files);
    });
    
    // Paste into description or anywhere on page
    document.addEventListener('paste', function(e) {
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
            handleFiles(files);
        }
    });
    
    function handleFiles(files) {
        for (var i = 0; i < files.length; i++) {
            uploadFile(files[i]);
        }
    }
    
    function uploadFile(file) {
        var id = 'file_' + (++attachmentCounter);
        var isImage = file.type.startsWith('image/');
        
        // Create list item immediately
        var item = document.createElement('div');
        item.className = 'attachment-item';
        item.id = id;
        item.innerHTML = '<div class="file-icon ' + (isImage ? 'image' : 'document') + '">' +
            (isImage ? '🖼' : '📄') +
            '</div>' +
            '<div class="file-info">' +
            '<div class="file-name">' + escapeHtml(file.name) + '</div>' +
            '<div class="file-size">Uploading...</div>' +
            '</div>' +
            '<span class="remove-file" onclick="removeAttachment(\'' + id + '\')">✕</span>';
        
        // Add preview for images
        if (isImage) {
            var reader = new FileReader();
            reader.onload = function(e) {
                var preview = document.createElement('img');
                preview.className = 'file-preview';
                preview.src = e.target.result;
                item.insertBefore(preview, item.querySelector('.remove-file'));
            };
            reader.readAsDataURL(file);
        }
        
        attachmentList.appendChild(item);
        
        // Upload file
        var formData = new FormData();
        formData.append('file', file);
        formData.append('hash', hashValue);
        formData.append('action', 'upload_temp_attachment');
        
        fetch('ajax_responder.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                attachments.push({
                    id: id,
                    filename: data.filename,
                    original_name: file.name,
                    safe_name: data.safe_name
                });
                
                // Show server filename (unique when pasted, e.g. image_20250210_143022.png)
                var nameEl = item.querySelector('.file-name');
                if (nameEl && data.safe_name) nameEl.textContent = data.safe_name;
                // Update size display
                var sizeEl = item.querySelector('.file-size');
                sizeEl.textContent = formatFileSize(file.size);
                
                // Add reference to description - use safe_name to match attach_files() expectations
                var descEl = document.getElementById('task_desc');
                if (descEl.value && !descEl.value.endsWith('\n')) {
                    descEl.value += '\n';
                }
                descEl.value += '[' + data.safe_name + ']';
            } else {
                item.querySelector('.file-size').textContent = 'Upload failed';
                item.style.background = '#fed7d7';
            }
        })
        .catch(function(err) {
            item.querySelector('.file-size').textContent = 'Upload failed';
            item.style.background = '#fed7d7';
        });
    }
    
    function removeAttachment(id) {
        var item = document.getElementById(id);
        if (item) {
            item.remove();
            attachments = attachments.filter(function(a) { return a.id !== id; });
        }
    }
    
    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }
    
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ==================== Domain Autocomplete ====================
    var domainInput = document.getElementById('task_domain');
    var domainDropdown = document.getElementById('domainDropdown');
    var domainSearchLink = document.getElementById('domainSearchLink');
    var domainTimeout = null;
    var domainHighlightIndex = -1;

    // Update domain search icon visibility and href
    function updateDomainSearchLink() {
        var val = domainInput.value.trim();
        if (val.length > 0) {
            domainSearchLink.href = 'view_clients.php?q=' + encodeURIComponent(val);
            domainSearchLink.style.display = 'inline';
        } else {
            domainSearchLink.style.display = 'none';
        }
    }
    updateDomainSearchLink();
    
    domainInput.addEventListener('input', function() {
        updateDomainSearchLink();
        var query = this.value.trim();
        
        clearTimeout(domainTimeout);
        
        if (query.length < 2) {
            domainDropdown.classList.remove('show');
            return;
        }
        
        domainTimeout = setTimeout(function() {
            fetch('ajax_responder.php?action=search_domains&q=' + encodeURIComponent(query))
                .then(response => response.json())
                .then(data => {
                    if (data.length === 0) {
                        domainDropdown.classList.remove('show');
                        return;
                    }
                    
                    domainDropdown.innerHTML = '';
                    domainHighlightIndex = -1;
                    
                    data.forEach(function(item, index) {
                        var div = document.createElement('div');
                        div.className = 'autocomplete-item';
                        div.dataset.index = index;
                        div.dataset.domain = item.domain_url;
                        div.dataset.clientId = item.client_id || '';
                        div.dataset.clientName = item.client_name || '';
                        
                        var html = '<div class="domain-url">' + escapeHtml(item.domain_url) + '</div>';
                        if (item.client_name) {
                            html += '<div class="client-name">' + escapeHtml(item.client_name) + '</div>';
                        }
                        div.innerHTML = html;
                        
                        div.addEventListener('click', function() {
                            selectDomain(this);
                        });
                        
                        div.addEventListener('mouseenter', function() {
                            highlightDomainItem(parseInt(this.dataset.index));
                        });
                        
                        domainDropdown.appendChild(div);
                    });
                    
                    domainDropdown.classList.add('show');
                });
        }, 200);
    });
    
    domainInput.addEventListener('keydown', function(e) {
        if (!domainDropdown.classList.contains('show')) return;
        
        var items = domainDropdown.querySelectorAll('.autocomplete-item');
        
        switch(e.key) {
            case 'ArrowDown':
                e.preventDefault();
                highlightDomainItem(Math.min(domainHighlightIndex + 1, items.length - 1));
                break;
            case 'ArrowUp':
                e.preventDefault();
                highlightDomainItem(Math.max(domainHighlightIndex - 1, 0));
                break;
            case 'Enter':
                e.preventDefault();
                if (domainHighlightIndex >= 0 && items[domainHighlightIndex]) {
                    selectDomain(items[domainHighlightIndex]);
                }
                break;
            case 'Escape':
                domainDropdown.classList.remove('show');
                break;
            case 'Tab':
                if (domainHighlightIndex >= 0 && items[domainHighlightIndex]) {
                    selectDomain(items[domainHighlightIndex]);
                }
                domainDropdown.classList.remove('show');
                break;
        }
    });
    
    domainInput.addEventListener('blur', function() {
        setTimeout(function() {
            domainDropdown.classList.remove('show');
        }, 200);
    });
    
    function highlightDomainItem(index) {
        var items = domainDropdown.querySelectorAll('.autocomplete-item');
        items.forEach(function(item) { item.classList.remove('highlighted'); });
        
        if (index >= 0 && items[index]) {
            items[index].classList.add('highlighted');
            domainHighlightIndex = index;
        }
    }
    
    function selectDomain(item) {
        domainInput.value = item.dataset.domain;
        domainDropdown.classList.remove('show');
        updateDomainSearchLink();
        
        // If domain has a client, set the client
        if (item.dataset.clientId && item.dataset.clientId !== '0') {
            var clientSelect = document.getElementById('clientSelect');
            var clientHidden = document.querySelector('input[name="client_id"]');
            var clientTrigger = clientSelect.querySelector('.custom-select-trigger span');
            
            clientHidden.value = item.dataset.clientId;
            clientTrigger.textContent = item.dataset.clientName;
            clientTrigger.classList.remove('placeholder');
            
            // Update selected state in options
            clientSelect.querySelectorAll('.custom-select-option').forEach(function(opt) {
                opt.classList.remove('selected');
                if (opt.dataset.value === item.dataset.clientId) {
                    opt.classList.add('selected');
                }
            });
        }
    }

    // ==================== Description editor: VS Code–like line shortcuts ====================
    (function() {
        var el = document.getElementById('task_desc');
        if (!el) return;

        function getLines() {
            return el.value.split('\n');
        }
        function setLines(lines) {
            el.value = lines.join('\n');
        }
        function getLineIndex(pos) {
            return (el.value.substring(0, pos).match(/\n/g) || []).length;
        }
        function getLineRange(lineIndex) {
            var text = el.value;
            var lines = text.split('\n');
            if (lineIndex < 0 || lineIndex >= lines.length) return { start: 0, end: 0 };
            var start = 0;
            for (var i = 0; i < lineIndex; i++) start += lines[i].length + 1;
            var end = start + lines[lineIndex].length;
            return { start: start, end: end };
        }
        function selectLine() {
            var lineIndex = getLineIndex(el.selectionStart);
            var r = getLineRange(lineIndex);
            el.setSelectionRange(r.start, r.end);
            el.focus();
        }
        function deleteLine() {
            var lineIndex = getLineIndex(el.selectionStart);
            var lines = getLines();
            if (lines.length <= 1) {
                el.value = '';
                el.setSelectionRange(0, 0);
                return;
            }
            lines.splice(lineIndex, 1);
            setLines(lines);
            var r = getLineRange(Math.min(lineIndex, lines.length - 1));
            el.setSelectionRange(r.start, r.start);
            el.focus();
        }
        function moveLine(delta) {
            var lineIndex = getLineIndex(el.selectionStart);
            var lines = getLines();
            var newIndex = lineIndex + delta;
            if (newIndex < 0 || newIndex >= lines.length) return;
            var line = lines.splice(lineIndex, 1)[0];
            lines.splice(newIndex, 0, line);
            setLines(lines);
            var r = getLineRange(newIndex);
            el.setSelectionRange(r.start, r.end);
            el.focus();
        }
        function copyLine(delta) {
            var lineIndex = getLineIndex(el.selectionStart);
            var lines = getLines();
            var line = lines[lineIndex];
            var newIndex = lineIndex + delta;
            if (newIndex < 0 || newIndex > lines.length) return;
            lines.splice(newIndex, 0, line);
            setLines(lines);
            var r = getLineRange(newIndex);
            el.setSelectionRange(r.start, r.end);
            el.focus();
        }

        el.addEventListener('keydown', function(e) {
            var mod = e.ctrlKey || e.metaKey; // Ctrl on Windows/Linux, Cmd on Mac

            // Ctrl+L / Cmd+L: Select line
            if (mod && e.key === 'l') {
                e.preventDefault();
                selectLine();
                return;
            }
            // Ctrl+Shift+K / Cmd+Shift+K: Delete line
            if (mod && e.shiftKey && (e.key === 'K' || e.key === 'k')) {
                e.preventDefault();
                deleteLine();
                return;
            }
            // Alt+Up: Move line up
            if (e.altKey && e.key === 'ArrowUp') {
                e.preventDefault();
                moveLine(-1);
                return;
            }
            // Alt+Down: Move line down
            if (e.altKey && e.key === 'ArrowDown') {
                e.preventDefault();
                moveLine(1);
                return;
            }
            // Shift+Alt+Up: Copy line up
            if (e.shiftKey && e.altKey && e.key === 'ArrowUp') {
                e.preventDefault();
                copyLine(-1);
                return;
            }
            // Shift+Alt+Down: Copy line down
            if (e.shiftKey && e.altKey && e.key === 'ArrowDown') {
                e.preventDefault();
                copyLine(1);
                return;
            }
        });
    })();

    // ==================== Keyboard Shortcut: Submit form with Ctrl+Enter or Cmd+End ====================
    var taskDescTextarea = document.getElementById('task_desc');
    taskDescTextarea.addEventListener('keydown', function(e) {
        // Ctrl+Enter or Cmd+Enter
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('taskForm').submit();
        }
        // Cmd+End (Mac) or Ctrl+End (Windows/Linux)
        if ((e.ctrlKey || e.metaKey) && e.key === 'End') {
            e.preventDefault();
            document.getElementById('taskForm').submit();
        }
    });
    </script>
</body>
</html>
