<?php
ini_set('memory_limit', '36M');

include("./includes/common.php");
include_once("./includes/viart_support.php");
include("./includes/date_functions.php");

// Ensure text is valid UTF-8 (converts from Windows-1252/ISO-8859-1 if needed)
function ensure_utf8($text) {
    if ($text === null || $text === '') return '';
    if (mb_check_encoding($text, 'UTF-8')) return $text;
    // Try Windows-1252 first (superset of ISO-8859-1, handles smart quotes etc.)
    return mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
}

// Convert URLs to clickable links
function linkify_urls($text) {
    $text = htmlspecialchars(ensure_utf8($text));
    // Match URLs (http, https, ftp)
    $pattern = '/(https?:\/\/|ftp:\/\/)[^\s<>\[\]"\']+/i';
    $replacement = '<a href="$0" target="_blank" rel="noopener noreferrer">$0</a>';
    return preg_replace($pattern, $replacement, $text);
}

// Linkify URLs in already-escaped HTML (no extra escaping)
function linkify_urls_safe($html) {
    $pattern = '/(https?:\/\/|ftp:\/\/)[^\s<>\[\]"\']+/i';
    return preg_replace($pattern, '<a href="$0" target="_blank" rel="noopener noreferrer">$0</a>', $html);
}

// Fix broken monitor task URLs (edittask.php?taskid= -> edit_task.php?task_id=) in message/desc HTML
function normalize_monitor_task_urls($html) {
    // Match edittask.php, edit_task.php, or edit task.php and normalize to edit_task.php
    $html = preg_replace('/edit\s*_?\s*task\.php/i', 'edit_task.php', $html);
    $html = preg_replace('/\btaskid=/i', 'task_id=', $html);
    return $html;
}

// Simple markdown to HTML (call on already htmlspecialchars'd text): ## ### ** * ``` code fences, pipe tables |
// URLs are protected with placeholders so they are not linkified in the middle of processing.
// Underscores are left as-is (no _italic_ or __bold__) so identifiers like include_nofollow and domain_name are preserved.
function simple_markdown_to_html($escaped_text) {
    $escaped_text = str_replace(["\r\n", "\r"], "\n", $escaped_text);

    // Extract code fences ``` ... ``` BEFORE URL replacement so code block content stays literal
    $code_blocks = array();
    $escaped_text = preg_replace_callback('/```(\w*)\n(.*?)```\n?/s', function ($m) use (&$code_blocks) {
        $lang = trim($m[1]);
        $content = $m[2];
        $cls = $lang !== '' ? ' class="msg-code lang-' . htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') . '"' : ' class="msg-code"';
        $code_blocks[] = '<div class="msg-code-wrap"><pre' . $cls . '><code>' . $content . '</code></pre><button type="button" class="copy-code-btn" title="Copy to clipboard" aria-label="Copy code"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></button></div>';
        return "\x01C" . (count($code_blocks) - 1) . "\x01\n";
    }, $escaped_text);

    $url_pattern = '/(https?:\/\/|ftp:\/\/)[^\s<>\[\]"\']+/i';
    $urls = array();
    $escaped_text = preg_replace_callback($url_pattern, function ($m) use (&$urls) {
        $urls[] = $m[0];
        return "\x01U" . (count($urls) - 1) . "\x01";
    }, $escaped_text);

    // Extract pipe tables (consecutive lines like | a | b |)
    $table_blocks = array();
    $escaped_text = preg_replace_callback('/(?:^\|.+\|\n)+/m', function ($m) use (&$table_blocks) {
        $raw = trim($m[0]);
        $rows = array();
        foreach (explode("\n", $raw) as $row) {
            $row = trim($row);
            if ($row === '') continue;
            $cells = array_map('trim', explode('|', $row));
            array_shift($cells);
            if (count($cells) && end($cells) === '') array_pop($cells);
            $rows[] = $cells;
        }
        $thead = null;
        $body = array();
        foreach ($rows as $cells) {
            $is_sep = (count($cells) > 0 && preg_match('/^[\s\-:]+$/', implode('', $cells)));
            if ($is_sep) continue;
            if ($thead === null) {
                $thead = $cells;
            } else {
                $body[] = $cells;
            }
        }
        if ($thead === null && count($body) > 0) {
            $thead = $body[0];
            $body = array_slice($body, 1);
        }
        $html = '<thead><tr>';
        foreach ($thead ?: array() as $c) { $html .= '<th>' . $c . '</th>'; }
        $html .= '</tr></thead><tbody>';
        foreach ($body as $cells) {
            $html .= '<tr>';
            foreach ($cells as $c) { $html .= '<td>' . $c . '</td>'; }
            $html .= '</tr>';
        }
        $html .= '</tbody>';
        $table_blocks[] = '<table class="msg-table">' . $html . '</table>';
        return "\x01T" . (count($table_blocks) - 1) . "\x01\n";
    }, $escaped_text);

    $lines = explode("\n", $escaped_text);
    $out = array();
    foreach ($lines as $line) {
        $t = $line;
        // Placeholder lines (code/table) — output as-is, no <br>
        if (preg_match('/^\x01[CT]\d+\x01$/', $t)) {
            $out[] = $t;
            continue;
        }
        // Headings at line start
        if (preg_match('/^###\s+(.*)$/', $t, $m)) {
            $t = '<h3 class="msg-heading msg-h3">' . $m[1] . '</h3>';
        } elseif (preg_match('/^##\s+(.*)$/', $t, $m)) {
            $t = '<h2 class="msg-heading msg-h2">' . $m[1] . '</h2>';
        } elseif (preg_match('/^#\s+(.*)$/', $t, $m)) {
            $t = '<h1 class="msg-heading msg-h1">' . $m[1] . '</h1>';
        } else {
            // Bold **text** and italic *text* only (underscores left as literal so e.g. include_nofollow stays)
            $t = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $t);
            $t = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $t);
            if (trim($t) === '') {
                $t = '<br>';
            } else {
                $t .= '<br>';
            }
        }
        $out[] = $t;
    }
    $result = implode("", $out);
    foreach ($urls as $i => $url) {
        $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $url = preg_replace('/edit\s*_?\s*task\.php/i', 'edit_task.php', $url);
        $url = preg_replace('/\btaskid=/i', 'task_id=', $url);
        $link = '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '</a>';
        $result = str_replace("\x01U" . $i . "\x01", $link, $result);
    }
    foreach ($code_blocks as $i => $html) {
        $result = str_replace("\x01C" . $i . "\x01", $html, $result);
    }
    foreach ($table_blocks as $i => $html) {
        $result = str_replace("\x01T" . $i . "\x01", $html, $result);
    }
    return $result;
}

// Expand [filename] refs in task description to img/link using task attachments; render simple markdown
function expand_task_desc_attachments($text, $task_id, $attachments) {
    $escaped = htmlspecialchars(ensure_utf8($text), ENT_QUOTES, 'UTF-8');
    $text = normalize_monitor_task_urls(simple_markdown_to_html($escaped));
    $base_url = 'attachments/task/' . (int)$task_id . '_';
    foreach ($attachments as $att) {
        $f = htmlspecialchars($att['file_name']);
        $url = $base_url . $f;
        $is_image = preg_match('/\.(jpg|jpeg|png|gif|webp|bmp)$/i', $att['file_name']);
        $replacement = $is_image
            ? '<img src="' . $url . '" alt="' . $f . '" style="max-width:100%;height:auto;border-radius:4px;">'
            : '<a href="' . $url . '" target="_blank" rel="noopener">[' . $f . ']</a>';
        $text = str_replace('[' . $att['file_name'] . ']', $replacement, $text);
    }
    return $text;
}

// Format message with quote styling and simple markdown (## ### ** *)
function format_message_with_quotes($text) {
    // Fix broken monitor task URLs in raw text so linkify produces correct hrefs
    $text = preg_replace('/edit\s*_?\s*task\.php/i', 'edit_task.php', $text);
    $text = preg_replace('/\btaskid=/i', 'task_id=', $text);
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $lines = explode("\n", $text);
    $result = '';
    
    foreach ($lines as $line) {
        // Count leading > characters on RAW text (before htmlspecialchars)
        $trimmed = ltrim($line);
        $quoteLevel = 0;
        while (strlen($trimmed) > 0 && $trimmed[0] === '>') {
            $quoteLevel++;
            $trimmed = ltrim(substr($trimmed, 1));
        }
        
        $raw = ($quoteLevel > 0) ? $trimmed : $line;
        $escaped = htmlspecialchars(ensure_utf8($raw), ENT_QUOTES, 'UTF-8');
        $with_md = simple_markdown_to_html($escaped);
        $with_links = normalize_monitor_task_urls($with_md);
        
        $level = min($quoteLevel, 5);
        if ($quoteLevel > 0) {
            $result .= '<span class="quote-line quote-level-' . $level . '">' . $with_links . '</span>';
        } else {
            $result .= $with_links;
        }
    }
    
    return str_replace(["\r\n", "\r", "\n"], "", $result);
}

// Get deadline status
function get_deadline_status($planed_date) {
    if (!$planed_date || $planed_date == '0000-00-00') {
        return array('class' => 'deadline-none', 'text' => 'No deadline', 'icon' => '📅');
    }
    
    $deadline = strtotime($planed_date);
    $today = strtotime(date('Y-m-d'));
    $diff_days = floor(($deadline - $today) / 86400);
    
    if ($diff_days < 0) {
        $days = abs($diff_days);
        return array(
            'class' => 'deadline-overdue',
            'text' => $days . ' day' . ($days != 1 ? 's' : '') . ' overdue',
            'icon' => '🚨'
        );
    } elseif ($diff_days == 0) {
        return array('class' => 'deadline-today', 'text' => 'Due today', 'icon' => '⚠️');
    } elseif ($diff_days <= 3) {
        return array(
            'class' => 'deadline-soon',
            'text' => $diff_days . ' day' . ($diff_days != 1 ? 's' : '') . ' left',
            'icon' => '⏰'
        );
    } else {
        return array(
            'class' => 'deadline-ok',
            'text' => $diff_days . ' days left',
            'icon' => '✓'
        );
    }
}

CheckSecurity(1);

$task_id = GetParam("task_id");

if (!$task_id || !is_numeric($task_id)) {
    header("Location: index.php");
    exit;
}

// Check permission for customers
if (GetSessionParam("privilege_id") == 9) {
    $sql = "SELECT * FROM tasks WHERE task_id = " . ToSQL($task_id, "integer") . " AND created_person_id = " . GetSessionParam("UserID");
    $db->query($sql);
    if (!$db->next_record()) {
        header("Location: index.php");
        exit;
    }
}

$session_user_id = GetSessionParam("UserID");
$action = GetParam("action");
$hash = GetParam("hash") ? GetParam("hash") : substr(md5(time()), 0, 8);
$desc_hash = substr(md5(uniqid('edit_desc', true)), 0, 8); // For description paste/drop attachments
$return_page = GetParam("rp") ? GetParam("rp") : "index.php";

// Handle actions (completion: intval so null/empty becomes 0 and start/stop always receive a valid value)
if ($action == "start") {
    start_task($task_id, intval(GetParam("completion")), "edit_task.php?task_id=" . $task_id);
}

if ($action == "stop") {
    stop_task($task_id, intval(GetParam("completion")), "edit_task.php?task_id=" . $task_id);
}

if ($action == "close") {
    close_task($task_id, "edit_task.php?task_id=" . $task_id);
}

if ($action == "reopen") {
    update_task($task_id, array("is_closed" => 0, "task_status_id" => 8)); // Reopen with "reassigned" status
    header("Location: edit_task.php?task_id=" . $task_id);
    exit;
}

// Handle message submission (for non-AJAX fallback)
if (GetParam("FormName") == "MessageForm") {
    $message = GetParam("message");
    $responsible_user_id = GetParam("responsible_user_id");
    $task_status_id = GetParam("task_status_id");
    $uhours = GetParam("uhours");
    $task_completion = GetParam("task_completion");
    
    add_task_message(
        $task_id, 
        $message, 
        GetParam("trn_user_id"), 
        $responsible_user_id,
        $task_status_id, 
        $uhours, 
        $task_completion,
        "edit_task.php?task_id=" . $task_id . "#messages", 
        $hash,
        GetParam('importance_value'),
        GetParam('bug_status'),
        false, false, false
    );
}

// Handle task update
if (GetParam("FormName") == "Form" && GetParam("FormAction") == "update") {
    $task_title = GetParam("task_title");
    $task_desc = GetParam("task_desc");
    $project_id = GetParam("sub_project_id") > 0 ? GetParam("sub_project_id") : GetParam("project_id");
    $task_status_id = GetParam("task_status_id");
    $responsible_user_id = GetParam("responsible_user_id");
    $task_type_id = GetParam("task_type_id");
    $task_domain = GetParam("task_domain");
    $client_id = GetParam("client_id");
    $completion = GetParam("completion");
    $estimated_hours = GetParam("task_estimated_hours");
    
    $iDay = GetParam("day");
    $iMonth = GetParam("month");
    $iYear = GetParam("year");
    if (strlen($iYear) < 4) {
        $iYear = "20" . $iYear;
    }
    $planed_date = $iYear . "-" . str_pad($iMonth, 2, "0", STR_PAD_LEFT) . "-" . str_pad($iDay, 2, "0", STR_PAD_LEFT);
    
    // Update task
    $sql = "UPDATE tasks SET 
        task_title = " . ToSQL($task_title, "text") . ",
        task_desc = " . ToSQL($task_desc, "text") . ",
        project_id = " . ToSQL($project_id, "integer") . ",
        task_status_id = " . ToSQL($task_status_id, "integer") . ",
        responsible_user_id = " . ToSQL($responsible_user_id, "integer") . ",
        task_type_id = " . ToSQL($task_type_id, "integer") . ",
        task_domain_url = " . ToSQL($task_domain, "text") . ",
        client_id = " . ToSQL($client_id, "integer") . ",
        completion = " . ToSQL($completion, "integer") . ",
        estimated_hours = " . ToSQL($estimated_hours, "number") . ",
        planed_date = " . ToSQL($planed_date, "text") . "
        WHERE task_id = " . ToSQL($task_id, "integer");
    $db->query($sql);

    // Attach any pasted/dropped files from description
    $desc_hash = GetParam("desc_hash");
    if ($desc_hash && function_exists('attach_files')) {
        $temp_path = "temp_attachments/";
        $path = "attachments/task/";
        $message_replaces = array();
        attach_files("task", $task_id, $desc_hash, $message_replaces);
    }

    $_SESSION['flash_message'] = array(
        'type' => 'success',
        'text' => 'Task updated successfully!'
    );

    header("Location: edit_task.php?task_id=" . $task_id);
    exit;
}

// Fetch task data
$sql = "SELECT t.*, 
        p.project_title, p.parent_project_id,
        pp.project_title as parent_project_title,
        u.first_name, u.last_name, u.email as user_email,
        cu.first_name as creator_first_name, cu.last_name as creator_last_name,
        s.status_desc, s.status_caption,
        tt.type_desc,
        c.client_name, c.client_company
        FROM tasks t
        LEFT JOIN projects p ON t.project_id = p.project_id
        LEFT JOIN projects pp ON p.parent_project_id = pp.project_id
        LEFT JOIN users u ON t.responsible_user_id = u.user_id
        LEFT JOIN users cu ON t.created_person_id = cu.user_id
        LEFT JOIN lookup_tasks_statuses s ON t.task_status_id = s.status_id
        LEFT JOIN lookup_task_types tt ON t.task_type_id = tt.type_id
        LEFT JOIN clients c ON t.client_id = c.client_id
        WHERE t.task_id = " . ToSQL($task_id, "integer");
$db->query($sql);
$task = $db->next_record() ? $db->Record : null;

if (!$task) {
    header("Location: index.php");
    exit;
}

// Ensure all string fields are valid UTF-8 (database may contain Windows-1252 encoded data)
foreach ($task as $key => $value) {
    if (is_string($value)) {
        $task[$key] = ensure_utf8($value);
    }
}

// Format dates
$planed_date = $task['planed_date'];
$creation_date = $task['creation_date'];

// Get parent project info
$parent_project_id = $task['parent_project_id'] ? $task['parent_project_id'] : $task['project_id'];
$sub_project_id = $task['parent_project_id'] ? $task['project_id'] : 0;

// Fetch messages
// Count total messages
$total_messages = 0;
$sql = "SELECT COUNT(*) AS cnt FROM messages WHERE identity_type = 'task' AND identity_id = " . ToSQL($task_id, "integer");
$db->query($sql);
if ($db->next_record()) $total_messages = (int) $db->f("cnt");

// Load initial batch of messages
$messages_per_page = 20;
$messages = array();
$sql = "SELECT m.*, 
        u.first_name, u.last_name, u.email,
        ru.first_name as r_first_name, ru.last_name as r_last_name,
        s.status_desc, s.status_caption,
        DATE_FORMAT(m.message_date, '%a %D %b %Y, %H:%i') as formatted_date
        FROM messages m
        LEFT JOIN users u ON m.user_id = u.user_id
        LEFT JOIN users ru ON m.responsible_user_id = ru.user_id
        LEFT JOIN lookup_tasks_statuses s ON m.status_id = s.status_id
        WHERE m.identity_type = 'task' AND m.identity_id = " . ToSQL($task_id, "integer") . "
        ORDER BY m.message_date DESC
        LIMIT " . $messages_per_page;
$db->query($sql);
while ($db->next_record()) {
    $record = $db->Record;
    if (!empty($record['message']) && is_string($record['message'])) {
        $record['message'] = preg_replace('/edit\s*_?\s*task\.php/i', 'edit_task.php', $record['message']);
        $record['message'] = preg_replace('/\btaskid=/i', 'task_id=', $record['message']);
    }
    $messages[] = $record;
}

// Get message attachments
$message_attachments = array();
if (!empty($messages)) {
    $message_ids = array_map(function($m) { return $m['message_id']; }, $messages);
    $sql = "SELECT * FROM attachments WHERE identity_type = 'message' AND identity_id IN (" . implode(",", $message_ids) . ")";
    $db->query($sql);
    while ($db->next_record()) {
        $message_attachments[$db->f("identity_id")][] = $db->Record;
    }
}

// Determine "Assign to" for new message
if (empty($messages)) {
    // No messages yet — default to the user who created the task
    $reply_to_user_id = $task['created_person_id'];
} else {
    // Has messages: last person who sent a message, unless that person is the current user — then keep current assignee
    $reply_to_user_id = isset($task['responsible_user_id']) ? $task['responsible_user_id'] : $task['created_person_id'];
    $last_sender_id = $messages[0]['user_id'];
    if ($last_sender_id != $session_user_id) {
        $reply_to_user_id = $last_sender_id;
    }
}

// Get task attachments
$task_attachments = array();
$sql = "SELECT * FROM attachments WHERE identity_type = 'task' AND identity_id = " . ToSQL($task_id, "integer");
$db->query($sql);
while ($db->next_record()) {
    $task_attachments[] = $db->Record;
}

// Fetch projects for edit mode
$projects = array();
$sql = "SELECT project_id, project_title FROM projects WHERE parent_project_id IS NULL AND is_closed = 0 ORDER BY project_title";
$db->query($sql);
while ($db->next_record()) {
    $projects[] = $db->Record;
}

// Fetch statuses
$statuses = array();
$sql = "SELECT * FROM lookup_tasks_statuses ORDER BY sort_order";
$db->query($sql);
while ($db->next_record()) {
    $statuses[] = $db->Record;
}

// Fetch task types
$task_types = array();
$sql = "SELECT * FROM lookup_task_types ORDER BY type_id";
$db->query($sql);
while ($db->next_record()) {
    $task_types[] = $db->Record;
}

// Fetch users for assignment
$users = array();
$sql = "SELECT user_id, first_name, last_name FROM users WHERE is_deleted IS NULL ORDER BY first_name, last_name";
$db->query($sql);
while ($db->next_record()) {
    $users[] = $db->Record;
}

// Fetch clients
$clients = array();
$sql = "SELECT client_id, client_name, client_company FROM clients ORDER BY client_name";
$db->query($sql);
while ($db->next_record()) {
    $clients[] = $db->Record;
}

// Is this task currently active (in progress)?
$is_active = ($task['task_status_id'] == 1);

// Check edit mode
$edit_mode = GetParam("edit") == "1";

// User name for header
$user_name = GetSessionParam("UserName");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($task['task_title']); ?> - Task #<?php echo $task_id; ?></title>
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
            line-height: 1.5;
            min-height: 100vh;
        }

        .container {
            max-width: 1300px;
            margin: 0 auto;
            padding: 20px;
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

        .flash-success { background: #48bb78; color: #fff; }
        .flash-error { background: #f56565; color: #fff; }
        .flash-close {
            background: none;
            border: none;
            color: rgba(255,255,255,0.8);
            font-size: 1.2rem;
            cursor: pointer;
        }

        /* Card Styles */
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h2 {
            font-size: 1.1rem;
            font-weight: 600;
        }

        .card-body {
            padding: 20px;
            overflow: visible;
        }

        /* Task Header */
        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            gap: 15px;
        }

        .task-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: #1a202c;
            flex: 1;
        }

        .task-id {
            color: #718096;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .task-id-wrapper {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            position: relative;
        }

        .copy-link-btn {
            opacity: 0;
            background: rgba(255,255,255,0.2);
            border: none;
            padding: 4px 6px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            color: #fff;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .task-id-wrapper:hover .copy-link-btn {
            opacity: 1;
        }

        .copy-link-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .copy-link-btn.copied {
            background: #48bb78;
        }

        .task-title-wrapper {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            position: relative;
        }

        .task-title-wrapper .task-title {
            margin: 0;
        }

        .copy-name-btn {
            opacity: 0;
            background: #e2e8f0;
            border: none;
            padding: 4px 6px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            color: #4a5568;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
        }

        .task-title-wrapper:hover .copy-name-btn {
            opacity: 1;
        }

        .copy-name-btn:hover {
            background: #cbd5e0;
        }

        .copy-name-btn.copied {
            background: #48bb78;
            color: #fff;
        }

        .task-actions {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }

        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-active { background: #c6f6d5; color: #276749; }
        .status-waiting { background: #feebc8; color: #c05621; }
        .status-done { background: #bee3f8; color: #2b6cb0; }
        .status-new { background: #e9d8fd; color: #6b46c1; }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
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

        .btn-success {
            background: #48bb78;
            color: #fff;
        }

        .btn-danger {
            background: #fc8181;
            color: #fff;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            background: #e2e8f0;
            color: #4a5568;
            text-decoration: none;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .back-btn:hover {
            background: #cbd5e0;
            color: #2d3748;
        }

        /* Task Meta Grid */
        .task-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 20px;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .meta-label {
            font-size: 0.75rem;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .meta-value {
            font-size: 0.9rem;
            color: #2d3748;
            font-weight: 500;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }

        .meta-value a {
            color: #667eea;
            text-decoration: none;
        }

        .meta-value a:hover {
            text-decoration: underline;
        }

        .meta-value .deadline-indicator {
            margin-left: 0;
        }

        /* Task Description */
        .task-description {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }

        .task-description h3 {
            font-size: 0.85rem;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }

        .task-description-content {
            font-size: 0.9rem;
            color: #4a5568;
            white-space: pre-wrap;
            background: #f7fafc;
            padding: 15px;
            border-radius: 8px;
            max-height: 200px;
            overflow-y: auto;
            cursor: zoom-in;
            transition: background 0.2s;
        }

        .task-description-content:hover {
            background: #edf2f7;
        }

        .task-description-content a,
        .task-modal-description a,
        .message-content a {
            color: #667eea;
            text-decoration: none;
            word-break: break-all;
        }

        .task-description-content a:hover,
        .task-modal-description a:hover,
        .message-content a:hover {
            text-decoration: underline;
        }
        .message-content .msg-heading { margin: 0.6em 0 0.25em; font-weight: 600; line-height: 1.3; }
        .message-content .msg-h1 { font-size: 1.1em; }
        .message-content .msg-h2 { font-size: 1.05em; }
        .message-content .msg-h3 { font-size: 1em; }
        .task-description-content .msg-heading { margin: 0.6em 0 0.25em; font-weight: 600; }
        .task-description-content .msg-h1 { font-size: 1.1em; }
        .task-description-content .msg-h2 { font-size: 1.05em; }
        .task-description-content .msg-h3 { font-size: 1em; }
        .message-content .msg-code, .task-description-content .msg-code {
            display: block; margin: 0.5em 0; padding: 10px 12px; background: #f1f5f9; border-radius: 6px; font-size: 0.85em;
            font-family: ui-monospace, monospace; overflow-x: auto; white-space: pre; border: 1px solid #e2e8f0;
        }
        .msg-code-wrap {
            position: relative;
            margin: 0.5em 0;
        }
        .msg-code-wrap .msg-code {
            margin: 0;
            padding-right: 40px;
        }
        .copy-code-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 32px;
            height: 32px;
            padding: 0;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: #fff;
            color: #718096;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.15s, color 0.15s, background 0.15s;
        }
        .msg-code-wrap:hover .copy-code-btn {
            opacity: 1;
        }
        .copy-code-btn:hover {
            background: #f1f5f9;
            color: #667eea;
        }
        .copy-code-btn.copied {
            color: #38a169;
        }
        .message-content .msg-table, .task-description-content .msg-table {
            border-collapse: collapse; margin: 0.5em 0; font-size: 0.9em; width: 100%;
        }
        .message-content .msg-table th, .message-content .msg-table td,
        .task-description-content .msg-table th, .task-description-content .msg-table td {
            border: 1px solid #e2e8f0; padding: 6px 10px; text-align: left;
        }
        .message-content .msg-table th, .task-description-content .msg-table th {
            background: #f1f5f9; font-weight: 600; text-align: left;
        }

        .task-description h3 {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .expand-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            padding: 4px 10px;
            border: none;
            background: #e2e8f0;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.75rem;
            color: #4a5568;
            transition: all 0.2s;
            font-weight: 500;
        }

        .expand-btn:hover {
            background: #667eea;
            color: #fff;
        }

        /* Task Detail Modal */
        .task-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }

        .task-modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .task-modal {
            background: #fff;
            border-radius: 12px;
            width: 90%;
            max-width: 800px;
            max-height: 85vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transform: scale(0.9);
            transition: transform 0.3s;
        }

        .task-modal-overlay.active .task-modal {
            transform: scale(1);
        }

        .task-modal-header {
            padding: 16px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .task-modal-header h3 {
            margin: 0;
            font-size: 1.1rem;
        }

        .task-modal-close {
            background: none;
            border: none;
            color: #fff;
            font-size: 1.5rem;
            cursor: pointer;
            opacity: 0.8;
            transition: opacity 0.2s;
        }

        .task-modal-close:hover {
            opacity: 1;
        }

        .task-modal-body {
            padding: 20px;
            overflow-y: auto;
            flex: 1;
        }

        .task-modal-section {
            margin-bottom: 20px;
        }

        .task-modal-section:last-child {
            margin-bottom: 0;
        }

        .task-modal-section h4 {
            font-size: 0.8rem;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }

        .task-modal-section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }

        .task-modal-actions {
            display: flex;
            gap: 8px;
        }

        .task-modal-btn {
            font-size: 0.8rem;
            padding: 4px 10px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            background: #f7fafc;
            color: #4a5568;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.2s, color 0.2s;
        }

        .task-modal-btn:hover {
            background: #edf2f7;
            color: #2d3748;
        }

        .task-modal-description {
            font-size: 0.95rem;
            color: #2d3748;
            white-space: pre-wrap;
            line-height: 1.7;
            background: #f7fafc;
            padding: 15px;
            border-radius: 8px;
        }

        .task-modal-attachments {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .task-modal-attachment {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 15px;
            background: #f7fafc;
            border-radius: 8px;
            text-decoration: none;
            color: #4a5568;
            transition: all 0.2s;
        }

        .task-modal-attachment:hover {
            background: #e2e8f0;
            color: #667eea;
        }

        .task-modal-attachment-icon {
            font-size: 1.2rem;
        }

        .task-modal-attachment-name {
            font-size: 0.85rem;
        }

        .task-modal-attachment-image {
            flex-direction: column;
            padding: 8px;
        }

        .task-modal-attachment-image img {
            max-width: 150px;
            max-height: 100px;
            border-radius: 6px;
            object-fit: cover;
        }

        /* Attachments */
        .attachments-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }

        .attachment-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: #edf2f7;
            border-radius: 6px;
            font-size: 0.8rem;
            color: #4a5568;
            text-decoration: none;
        }

        .attachment-item:hover {
            background: #e2e8f0;
        }

        .attachment-thumbnail {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            padding: 8px;
            background: #edf2f7;
            border-radius: 8px;
            text-decoration: none;
            color: #4a5568;
            transition: all 0.2s;
        }

        .attachment-thumbnail:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .attachment-thumbnail img {
            max-width: 120px;
            max-height: 80px;
            border-radius: 4px;
            object-fit: cover;
        }

        .attachment-thumbnail-name {
            font-size: 0.75rem;
            max-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .creator-name {
            color: #667eea;
        }

        /* Closed Task Banner */
        .closed-task-banner {
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

        .closed-task-banner .banner-content {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .closed-task-banner .banner-icon {
            font-size: 1.5rem;
        }

        .closed-task-banner .banner-text h3 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
        }

        .closed-task-banner .banner-text p {
            margin: 2px 0 0;
            font-size: 0.85rem;
            opacity: 0.9;
        }

        .closed-task-banner .btn-reopen {
            background: #fff;
            color: #4a5568;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .closed-task-banner .btn-reopen:hover {
            background: #f7fafc;
            transform: translateY(-1px);
        }

        /* AJAX Flash Messages */
        #ajaxFlashMessage {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 14px 20px;
            border-radius: 8px;
            color: #fff;
            font-weight: 500;
            font-size: 0.9rem;
            z-index: 10000;
            transform: translateX(120%);
            opacity: 0;
            visibility: hidden;
            transition: transform 0.3s ease, opacity 0.3s ease, visibility 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            max-width: 350px;
        }

        #ajaxFlashMessage.show {
            transform: translateX(0);
            opacity: 1;
            visibility: visible;
        }

        #ajaxFlashMessage.success {
            background: linear-gradient(135deg, #48bb78, #38a169);
        }

        #ajaxFlashMessage.error {
            background: linear-gradient(135deg, #f56565, #e53e3e);
        }

        #ajaxFlashMessage.info {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        .btn-primary:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .task-closed .task-title {
            opacity: 0.7;
        }

        .task-closed .status-badge {
            background: #718096 !important;
            color: #fff !important;
        }

        /* Confirmation Modal */
        .confirm-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 15000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }

        .confirm-modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .confirm-modal {
            background: #fff;
            border-radius: 12px;
            padding: 30px;
            max-width: 400px;
            width: 90%;
            text-align: center;
            transform: scale(0.9);
            transition: transform 0.3s;
        }

        .confirm-modal-overlay.active .confirm-modal {
            transform: scale(1);
        }

        .confirm-modal-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }

        .confirm-modal h3 {
            margin-bottom: 10px;
            color: #2d3748;
        }

        .confirm-modal p {
            color: #718096;
            margin-bottom: 8px;
        }

        .confirm-modal-task-title {
            font-weight: 600;
            color: #4a5568;
            background: #f7fafc;
            padding: 8px 12px;
            border-radius: 6px;
            display: inline-block;
            margin-bottom: 20px;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .confirm-modal-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        /* Lightbox Gallery */
        .lightbox-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.95);
            z-index: 20000;
            display: flex;
            flex-direction: column;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }

        .lightbox-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .lightbox-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            color: #fff;
        }

        .lightbox-title {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .lightbox-counter {
            font-size: 0.85rem;
            opacity: 0.7;
        }

        .lightbox-close {
            background: none;
            border: none;
            color: #fff;
            font-size: 2rem;
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.2s;
            padding: 0 10px;
        }

        .lightbox-close:hover {
            opacity: 1;
        }

        .lightbox-main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            padding: 0 60px;
            min-height: 0;
        }

        .lightbox-image-container {
            max-width: 100%;
            max-height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .lightbox-image {
            max-width: 100%;
            max-height: calc(100vh - 200px);
            object-fit: contain;
            border-radius: 4px;
        }

        .lightbox-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: #fff;
            font-size: 2rem;
            padding: 20px 15px;
            cursor: pointer;
            transition: background 0.2s;
            border-radius: 4px;
        }

        .lightbox-nav:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .lightbox-nav.prev {
            left: 10px;
        }

        .lightbox-nav.next {
            right: 10px;
        }

        .lightbox-nav:disabled {
            opacity: 0.3;
            cursor: default;
        }

        .lightbox-thumbnails {
            display: flex;
            justify-content: center;
            gap: 8px;
            padding: 15px 20px;
            overflow-x: auto;
            background: rgba(0, 0, 0, 0.5);
        }

        .lightbox-thumb {
            width: 60px;
            height: 45px;
            object-fit: cover;
            border-radius: 4px;
            cursor: pointer;
            opacity: 0.5;
            transition: all 0.2s;
            border: 2px solid transparent;
        }

        .lightbox-thumb:hover {
            opacity: 0.8;
        }

        .lightbox-thumb.active {
            opacity: 1;
            border-color: #667eea;
        }

        /* Progress Bar */
        .progress-bar {
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 8px;
            margin-left: auto;
            margin-right: auto;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #48bb78, #38a169);
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        /* Messages Section */
        .messages-section {
            margin-top: 20px;
        }

        .messages-card {
            overflow: visible;
        }

        .message-composer {
            background: #f7fafc;
            border-radius: 8px;
            padding: 15px;
            padding-bottom: 20px;
            margin-bottom: 20px;
            overflow: visible;
        }

        /* Message Attachments */
        .message-attachments-area {
            margin-top: 10px;
        }

        .message-dropzone {
            border: 2px dashed #cbd5e0;
            border-radius: 8px;
            padding: 12px;
            text-align: center;
            color: #718096;
            font-size: 0.85rem;
            transition: all 0.2s;
            cursor: pointer;
        }

        .message-dropzone:hover,
        .message-dropzone.drag-over {
            border-color: #667eea;
            background: #f0f4ff;
        }

        .message-dropzone .file-label {
            color: #667eea;
            cursor: pointer;
            font-weight: 500;
        }

        .message-dropzone .file-label:hover {
            text-decoration: underline;
        }

        .message-attachment-list {
            margin-top: 8px;
            max-height: 120px;
            overflow-y: auto;
        }

        .message-attachment-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            margin-bottom: 4px;
            font-size: 0.8rem;
        }

        .message-attachment-item .file-icon {
            width: 24px;
            height: 24px;
            background: #f1f5f9;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 0.7rem;
        }

        .message-attachment-item .file-icon.image {
            background: #fef3c7;
        }

        .message-attachment-item .file-info {
            flex: 1;
            min-width: 0;
        }

        .message-attachment-item .file-name {
            font-weight: 500;
            color: #2d3748;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .message-attachment-item .file-size {
            color: #a0aec0;
            font-size: 0.7rem;
        }

        .message-attachment-item .file-preview {
            width: 28px;
            height: 28px;
            border-radius: 4px;
            object-fit: cover;
            flex-shrink: 0;
        }

        .message-attachment-item .remove-file {
            color: #e53e3e;
            cursor: pointer;
            padding: 2px 4px;
            border-radius: 4px;
            transition: background 0.15s;
        }

        .message-attachment-item .remove-file:hover {
            background: #fed7d7;
        }

        .message-textarea {
            width: 100%;
            min-height: 100px;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.9rem;
            resize: vertical;
            transition: min-height 0.3s ease;
        }

        .message-textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            min-height: 350px;
        }

        /* Keep editor expanded when focus is in Status/Assign dropdowns (avoids layout shift closing dropdown) */
        .message-composer.expanded .message-textarea {
            min-height: 350px;
        }

        .message-options {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 12px;
            align-items: center;
            position: relative;
            z-index: 10;
        }

        .message-option {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .message-option label {
            font-size: 0.8rem;
            color: #718096;
        }

        .message-option select,
        .message-option input {
            padding: 6px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.85rem;
            font-family: inherit;
        }

        /* Custom Select Dropdown */
        .custom-select {
            position: relative;
            min-width: 160px;
        }

        /* Wider dropdown for user names */
        #userSelectMsg,
        #editUserSelect {
            min-width: 200px;
        }

        .custom-select-trigger {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 6px 10px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.2s;
        }

        .custom-select-trigger:hover {
            border-color: #cbd5e0;
        }

        .custom-select.open .custom-select-trigger {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .custom-select-trigger::after {
            content: '';
            border: 4px solid transparent;
            border-top-color: #718096;
            margin-left: 8px;
        }

        .custom-select.open .custom-select-trigger::after {
            border-top-color: transparent;
            border-bottom-color: #718096;
            margin-top: -4px;
        }

        .custom-select-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            min-width: 200px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            max-height: 200px;
            overflow-y: auto;
            z-index: 100;
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
            padding: 6px 8px;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            font-size: 0.8rem;
        }

        .custom-select-options {
            max-height: 150px;
            overflow-y: auto;
        }

        .custom-select-option {
            padding: 8px 12px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: background 0.15s;
        }

        .custom-select-option:hover {
            background: #f7fafc;
        }

        .custom-select-option.highlighted {
            background: #667eea;
            color: #fff;
        }

        .custom-select-option.selected {
            background: #667eea;
            color: #fff;
        }

        .custom-select-option.hidden {
            display: none;
        }

        .message-actions {
            display: flex;
            gap: 10px;
            margin-top: 12px;
        }

        /* Message List */
        .message-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .load-more-wrap {
            text-align: center;
            padding: 16px 0 4px;
        }

        .btn-load-more {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 28px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8f9fa;
            color: #4a5568;
            font-size: 0.85rem;
            font-weight: 500;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.15s;
        }

        .btn-load-more:hover {
            background: #eef2ff;
            border-color: #667eea;
            color: #667eea;
        }

        .btn-load-more:disabled {
            opacity: 0.5;
            cursor: wait;
        }

        .btn-load-more #loadMoreCount {
            font-weight: 400;
            color: #a0aec0;
            font-size: 0.8rem;
        }

        .message-item {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 15px;
            transition: all 0.2s;
        }

        .message-item:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }

        .message-item.message-highlight {
            animation: highlightPulse 3s ease-out;
            background: #fffbeb;
            border-color: #fbbf24;
        }

        @keyframes highlightPulse {
            0% { background: #fef3c7; border-color: #f59e0b; box-shadow: 0 0 15px rgba(245, 158, 11, 0.4); }
            50% { background: #fffbeb; border-color: #fbbf24; box-shadow: 0 0 8px rgba(245, 158, 11, 0.2); }
            100% { background: #fff; border-color: #e2e8f0; box-shadow: none; }
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .message-author {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .message-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .message-author-info {
            display: flex;
            flex-direction: column;
        }

        .message-author-name {
            font-weight: 600;
            color: #2d3748;
            font-size: 0.9rem;
        }

        .message-date {
            font-size: 0.75rem;
            color: #a0aec0;
        }

        .message-assigned {
            color: #667eea;
            font-weight: 500;
            margin-left: 6px;
        }

        .message-status {
            font-size: 0.75rem;
            padding: 3px 8px;
            border-radius: 4px;
            background: #e2e8f0;
            color: #4a5568;
        }

        .message-content {
            font-size: 0.9rem;
            color: #4a5568;
            line-height: 1.6;
        }

        /* Inline image references in messages */
        .message-inline-image {
            display: inline-block;
            max-width: 100%;
            margin: 6px 0;
            cursor: pointer;
            vertical-align: middle;
        }
        .message-inline-image img {
            max-width: 100%;
            max-height: 280px;
            width: auto;
            height: auto;
            object-fit: contain;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            transition: opacity 0.15s;
        }
        .message-inline-image img:hover {
            opacity: 0.9;
        }
        html.dark-mode .message-inline-image img {
            border-color: #2d333b;
        }

        /* Quote styling for messages */
        .quote-line {
            display: block;
            padding: 3px 0 3px 12px;
            border-left: 3px solid;
            margin: 0;
        }

        .quote-level-1 {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.08);
            color: #5a67d8;
        }

        .quote-level-2 {
            border-color: #9f7aea;
            background: rgba(159, 122, 234, 0.08);
            color: #805ad5;
            margin-left: 12px;
        }

        .quote-level-3 {
            border-color: #ed64a6;
            background: rgba(237, 100, 166, 0.08);
            color: #d53f8c;
            margin-left: 24px;
        }

        .quote-level-4 {
            border-color: #ed8936;
            background: rgba(237, 137, 54, 0.08);
            color: #c05621;
            margin-left: 36px;
        }

        .quote-level-5 {
            border-color: #718096;
            background: rgba(113, 128, 150, 0.08);
            color: #4a5568;
            margin-left: 48px;
        }

        .quote-line + br {
            display: none;
        }

        /* Deadline indicators */
        .deadline-indicator {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .deadline-overdue {
            background: #fed7d7;
            color: #c53030;
        }

        .deadline-today {
            background: #feebc8;
            color: #c05621;
        }

        .deadline-soon {
            background: #fefcbf;
            color: #975a16;
        }

        .deadline-ok {
            background: #c6f6d5;
            color: #276749;
        }

        .deadline-none {
            background: #e2e8f0;
            color: #718096;
        }

        .message-attachments {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #f0f0f0;
        }

        /* Edit Mode Form */
        .edit-form {
            display: none;
        }

        .edit-form.active {
            display: block;
        }

        .view-mode.hidden {
            display: none;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        .form-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #4a5568;
        }

        .form-control {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.9rem;
            font-family: inherit;
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

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        #editTaskDesc:focus {
            min-height: 280px;
        }

        .help-text { font-size: 0.8rem; color: #718096; margin-top: 6px; }
        .desc-attachment-area { margin-top: 12px; }
        .desc-attachment-area .attachment-dropzone {
            padding: 16px; text-align: center; background: #f8fafc; border: 2px dashed #e2e8f0; border-radius: 8px;
            color: #718096; font-size: 0.85rem; cursor: pointer; transition: all 0.2s;
        }
        .desc-attachment-area .attachment-dropzone:hover,
        .desc-attachment-area .attachment-dropzone.dragover { border-color: #667eea; background: #f0f4ff; color: #667eea; }
        .desc-attachment-area .attachment-dropzone svg { display: block; margin: 0 auto 8px; color: #a0aec0; }
        .desc-attachment-area .attachment-dropzone .file-label { color: #667eea; cursor: pointer; font-weight: 500; }
        .desc-attachment-area .attachment-list { max-height: 120px; overflow-y: auto; margin-top: 8px; }
        .desc-attachment-area .attachment-item {
            display: flex; align-items: center; gap: 8px; padding: 6px 10px; background: #fff; border: 1px solid #e2e8f0;
            border-radius: 6px; margin-bottom: 4px; font-size: 0.8rem;
        }
        .desc-attachment-area .attachment-item .file-icon { width: 24px; height: 24px; background: #f1f5f9; border-radius: 4px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 0.7rem; }
        .desc-attachment-area .attachment-item .file-icon.image { background: #fef3c7; }
        .desc-attachment-area .attachment-item .file-info { flex: 1; min-width: 0; }
        .desc-attachment-area .attachment-item .file-name { font-weight: 500; color: #2d3748; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .desc-attachment-area .attachment-item .file-size { color: #a0aec0; font-size: 0.7rem; }
        .desc-attachment-area .attachment-item .remove-file { color: #e53e3e; cursor: pointer; padding: 2px 4px; }
        html.dark-mode .desc-attachment-area .attachment-dropzone { background: #161b22; border-color: #2d333b; color: #8b949e; }
        html.dark-mode .desc-attachment-area .attachment-dropzone:hover,
        html.dark-mode .desc-attachment-area .attachment-dropzone.dragover { border-color: #667eea; background: #1c2333; }
        html.dark-mode .desc-attachment-area .attachment-item { background: #1c2333 !important; border-color: #2d333b; }
        html.dark-mode .desc-attachment-area .attachment-item .file-name { color: #e2e8f0; }
        html.dark-mode .desc-attachment-area .attachment-item .file-icon { background: #2d333b; }
        html.dark-mode .help-text { color: #8b949e; }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }

        /* Time display */
        .time-info {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            padding: 15px 20px;
            background: #f7fafc;
            border-radius: 8px;
            margin-top: 15px;
        }

        .time-block {
            text-align: center;
            flex: 1;
            min-width: 80px;
        }

        .time-value {
            font-size: 1.4rem;
            font-weight: 700;
            color: #2d3748;
        }

        .time-label {
            font-size: 0.7rem;
            color: #718096;
            text-transform: uppercase;
        }

        /* Quick Actions */
        .quick-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        /* ===================== RESPONSIVE ===================== */

        @media (max-width: 1024px) {
            .container {
                padding: 16px;
            }

            .task-meta {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            .task-header {
                flex-direction: column;
                gap: 8px;
            }

            .task-meta {
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .message-options {
                gap: 8px;
            }

            .message-option {
                flex-wrap: wrap;
            }

            .message-actions {
                flex-wrap: wrap;
                gap: 6px;
            }

            .card-header {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 8px;
            }

            .card-header > div {
                width: 100%;
            }

            .card-header > div:last-child {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
            }

            .edit-form .form-row {
                grid-template-columns: 1fr;
            }

            .custom-select-dropdown {
                min-width: 180px;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 6px;
            }

            .card {
                border-radius: 8px;
            }

            .card-body {
                padding: 12px;
            }

            .card-header {
                padding: 10px 12px;
            }

            /* Task title */
            .task-title-wrapper .task-title {
                font-size: 1.1rem;
            }

            /* Key indicators: stack on mobile */
            .task-meta {
                grid-template-columns: 1fr 1fr;
                gap: 6px;
            }

            .meta-item {
                padding: 6px 0;
            }

            .meta-label {
                font-size: 0.7rem;
            }

            .meta-value {
                font-size: 0.8rem;
            }

            /* Buttons: icon-only on phone */
            .btn-sm .btn-label {
                display: none;
            }

            .btn-sm {
                padding: 6px 10px;
                font-size: 0.85rem;
            }

            /* Message form */
            .message-options {
                flex-direction: column;
                gap: 6px;
                align-items: stretch;
            }

            .message-option {
                width: 100%;
            }

            .message-option label {
                min-width: 60px;
                font-size: 0.75rem;
            }

            .message-actions {
                flex-wrap: wrap;
            }

            .message-actions .btn {
                font-size: 0.8rem;
                padding: 6px 10px;
            }

            /* Description popup */
            .desc-popup-content {
                width: 95% !important;
                max-height: 80vh !important;
            }

            /* Back button */
            .back-btn {
                font-size: 0.8rem;
                padding: 4px 8px;
            }

            /* Quick links */
            .quick-links {
                flex-wrap: wrap;
                gap: 6px;
                font-size: 0.8rem;
            }

            /* Edit form */
            .edit-form .form-group label {
                font-size: 0.8rem;
            }

            .form-actions {
                flex-wrap: wrap;
                gap: 8px;
            }

            /* Message items */
            .message-item {
                padding: 10px;
            }

            .message-avatar {
                width: 28px;
                height: 28px;
                font-size: 0.65rem;
            }

            .message-author-name {
                font-size: 0.8rem;
            }

            .message-date {
                font-size: 0.7rem;
            }

            .message-content {
                font-size: 0.82rem;
            }

            /* Lightbox */
            .lightbox-content img {
                max-width: 95vw;
            }
        }

        /* ===================== LAYOUT TOGGLE ===================== */
        .layout-toggle {
            display: inline-flex;
            align-items: center;
            gap: 2px;
            background: rgba(255,255,255,0.15);
            border-radius: 6px;
            padding: 2px;
        }
        .layout-toggle-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 24px;
            border: none;
            background: transparent;
            color: rgba(255,255,255,0.6);
            cursor: pointer;
            border-radius: 4px;
            transition: all 0.15s;
            padding: 0;
        }
        .layout-toggle-btn:hover { color: #fff; background: rgba(255,255,255,0.1); }
        .layout-toggle-btn.active { background: rgba(255,255,255,0.25); color: #fff; }
        .layout-toggle-btn svg { width: 14px; height: 14px; }

        /* Side-by-side layout */
        .layout-wrap { display: flex; flex-direction: column; }
        .layout-wrap.side-by-side {
            flex-direction: row;
            gap: 20px;
            align-items: flex-start;
        }
        .layout-wrap.side-by-side > .card:first-child {
            flex: 0 0 420px;
            max-width: 420px;
            position: sticky;
            top: 60px;
            max-height: calc(100vh - 72px);
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #cbd5e0 transparent;
        }
        .layout-wrap.side-by-side > .card:first-child::-webkit-scrollbar { width: 5px; }
        .layout-wrap.side-by-side > .card:first-child::-webkit-scrollbar-track { background: transparent; }
        .layout-wrap.side-by-side > .card:first-child::-webkit-scrollbar-thumb { background: #cbd5e0; border-radius: 3px; }
        .layout-wrap.side-by-side > .card.messages-card {
            flex: 1;
            min-width: 0;
        }
        /* In side-by-side, make container wider */
        .container.wide-layout {
            max-width: 1800px;
        }

        /* Compact details in side-by-side mode */
        .layout-wrap.side-by-side .task-meta {
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .layout-wrap.side-by-side .task-meta .meta-item {
            padding: 8px 10px;
        }
        .layout-wrap.side-by-side .task-meta .meta-label {
            font-size: 0.62rem;
        }
        .layout-wrap.side-by-side .task-meta .meta-value {
            font-size: 0.78rem;
        }
        .layout-wrap.side-by-side .card-body {
            padding: 14px;
        }
        .layout-wrap.side-by-side .task-header {
            flex-direction: column;
            gap: 8px;
        }
        .layout-wrap.side-by-side .task-title {
            font-size: 1rem;
        }
        .layout-wrap.side-by-side .edit-form .form-row {
            grid-template-columns: 1fr;
        }
        .layout-wrap.side-by-side .task-description {
            font-size: 0.82rem;
        }
        .layout-wrap.side-by-side > .card:first-child .card-header {
            padding: 10px 14px;
            flex-wrap: wrap;
            gap: 6px;
        }
        .layout-wrap.side-by-side > .card:first-child .card-header h2 {
            font-size: 0.95rem;
        }
        .layout-wrap.side-by-side > .card:first-child .card-header .btn {
            padding: 4px 8px;
            font-size: 0.72rem;
        }

        /* Hide side-by-side on narrow screens */
        @media (max-width: 1100px) {
            .layout-wrap.side-by-side {
                flex-direction: column;
            }
            .layout-wrap.side-by-side > .card:first-child {
                flex: none;
                max-width: none;
                position: static;
                max-height: none;
                overflow-y: visible;
            }
            .container.wide-layout {
                max-width: 1300px;
            }
        }
        @media (max-width: 768px) {
            .layout-toggle { display: none; }
        }

        /* ==================== DARK MODE overrides for edit_task.php ==================== */
        /* Task meta */
        html.dark-mode .meta-value { color: #e2e8f0; }
        html.dark-mode .meta-label { color: #8b949e; }
        html.dark-mode .meta-value a { color: #90cdf4; }

        /* Time info stats box */
        html.dark-mode .time-info { background: #1c2333; }
        html.dark-mode .time-value { color: #e2e8f0; }
        html.dark-mode .time-label { color: #8b949e; }
        html.dark-mode .progress-bar { background: #2d333b; }

        /* Task description */
        html.dark-mode .task-description { border-top-color: #2d333b; }
        html.dark-mode .task-description h3 { color: #8b949e; }
        html.dark-mode .task-description-content { background: #1c2333; color: #cbd5e0; }
        html.dark-mode .task-description-content:hover { background: #252d3a; }
        html.dark-mode .expand-btn { background: #1c2333; color: #8b949e; }
        html.dark-mode .expand-btn:hover { background: #667eea; color: #fff; }

        /* Attachments */
        html.dark-mode .attachment-item { background: #1c2333; color: #cbd5e0; }
        html.dark-mode .attachment-item:hover { background: #252d3a; }
        html.dark-mode .attachment-thumbnail { border-color: #2d333b; }

        /* Message items */
        html.dark-mode .message-item { background: #161b22; border-color: #2d333b; }
        html.dark-mode .message-item:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.4); }
        html.dark-mode .message-author-name { color: #e2e8f0; }
        html.dark-mode .message-date { color: #8b949e; }
        html.dark-mode .message-content { color: #cbd5e0; }
        html.dark-mode .message-content .msg-code, html.dark-mode .task-description-content .msg-code {
            background: #1c2333; border-color: #2d333b; color: #e2e8f0;
        }
        html.dark-mode .copy-code-btn {
            background: #252d3d;
            border-color: #2d333b;
            color: #8b949e;
        }
        html.dark-mode .copy-code-btn:hover {
            background: #1c2333;
            color: #93c5fd;
        }
        html.dark-mode .message-content .msg-table th, html.dark-mode .message-content .msg-table td,
        html.dark-mode .task-description-content .msg-table th, html.dark-mode .task-description-content .msg-table td {
            border-color: #2d333b;
        }
        html.dark-mode .message-content .msg-table th, html.dark-mode .task-description-content .msg-table th {
            background: #252d3d; color: #e2e8f0; text-align: left;
        }
        html.dark-mode .message-status { background: #1c2333; color: #8b949e; }
        html.dark-mode .message-attachments { border-top-color: #2d333b; }

        /* Message composer */
        html.dark-mode .message-composer { background: #1c2333; }
        html.dark-mode .message-textarea { background: #161b22 !important; color: #e2e8f0 !important; border-color: #2d333b !important; }
        html.dark-mode .message-dropzone { background: #161b22; border-color: #2d333b; color: #8b949e; }
        html.dark-mode .message-dropzone:hover,
        html.dark-mode .message-dropzone.drag-over { background: #1c2333; border-color: #667eea; }
        html.dark-mode .message-dropzone .file-label { color: #90cdf4; }
        html.dark-mode .message-attachment-list { background: transparent; }
        html.dark-mode .message-attachment-item { background: #1c2333 !important; border-color: #2d333b; color: #cbd5e0; }
        html.dark-mode .message-attachment-item .file-icon { background: #2d333b; color: #8b949e; }
        html.dark-mode .message-attachment-item .file-icon.image { background: #3a2a0a; }
        html.dark-mode .message-attachment-item .file-info { color: inherit; }
        html.dark-mode .message-attachment-item .file-name { color: #e2e8f0; }
        html.dark-mode .message-attachment-item .file-size { color: #8b949e; }
        html.dark-mode .message-attachment-item .remove-file { color: #feb2b2; }
        html.dark-mode .message-attachment-item .remove-file:hover { background: rgba(254, 178, 178, 0.15); }

        /* Message quote styling */
        html.dark-mode .quote-level-1 { background: rgba(102, 126, 234, 0.12); color: #a3bffa; }
        html.dark-mode .quote-level-2 { background: rgba(159, 122, 234, 0.12); color: #d6bcfa; }
        html.dark-mode .quote-level-3 { background: rgba(237, 100, 166, 0.12); color: #fbb6ce; }
        html.dark-mode .quote-level-4 { background: rgba(237, 137, 54, 0.12); color: #fbd38d; }
        html.dark-mode .quote-level-5 { background: rgba(113, 128, 150, 0.12); color: #a0aec0; }

        /* Task modal */
        html.dark-mode .task-modal { background: #161b22; }
        html.dark-mode .task-modal-body { background: #161b22; color: #cbd5e0; }
        html.dark-mode .task-modal-section h4 { color: #8b949e; }
        html.dark-mode .task-modal-description { color: #cbd5e0; background: #0d1117; border-radius: 6px; padding: 12px 14px; }
        html.dark-mode .task-modal-description a { color: #90cdf4; }
        html.dark-mode .task-modal-btn { border-color: #30363d; background: #21262d; color: #c9d1d9; }
        html.dark-mode .task-modal-btn:hover { background: #30363d; color: #e6edf3; }
        html.dark-mode .task-modal-attachment { background: #1c2333; color: #cbd5e0; }
        html.dark-mode .task-modal-attachment:hover { background: #252d3a; color: #90cdf4; }
        html.dark-mode .task-modal-attachment-image { background: #1c2333; border-color: #2d333b; }
        html.dark-mode .task-modal-attachment-image img { border-color: #2d333b; }
        html.dark-mode .task-modal-overlay { background: rgba(0, 0, 0, 0.75); }

        /* Close Task confirmation modal */
        html.dark-mode .confirm-modal-overlay { background: rgba(0, 0, 0, 0.75); }
        html.dark-mode .confirm-modal { background: #161b22; border: 1px solid #2d333b; }
        html.dark-mode .confirm-modal-icon { color: #8b949e; }
        html.dark-mode .confirm-modal h3 { color: #e2e8f0; }
        html.dark-mode .confirm-modal p { color: #a0aec0; }
        html.dark-mode .confirm-modal-task-title { background: #1c2333; color: #e2e8f0; border: 1px solid #2d333b; }

        /* Message options & checkbox labels */
        html.dark-mode .message-options label { color: #8b949e; }

        /* Back button */
        html.dark-mode .back-btn { background: #1c2333; color: #cbd5e0; }
        html.dark-mode .back-btn:hover { background: #2d333b; color: #fff; }

        /* Action buttons in dark mode */
        html.dark-mode .btn-danger { background: #742a2a; color: #feb2b2; }
        html.dark-mode .btn-danger:hover { background: #9b2c2c; color: #fed7d7; }
        html.dark-mode .btn-success { background: #22543d; color: #9ae6b4; }
        html.dark-mode .btn-success:hover { background: #276749; color: #c6f6d5; }

        /* Highlight animation for dark mode */
        html.dark-mode .message-item.message-highlight { background: #2a2000; border-color: #92700c; }
    </style>
</head>
<body>
    <?php include("./includes/modern_header.php"); ?>

    <!-- AJAX Flash Message -->
    <div id="ajaxFlashMessage"></div>

    <?php 
    if (isset($_SESSION['flash_message'])): 
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
    ?>
    <div class="flash-message flash-<?php echo $flash['type']; ?>" id="flashMessage">
        <span><?php echo htmlspecialchars($flash['text']); ?></span>
        <button class="flash-close" onclick="document.getElementById('flashMessage').remove()">&times;</button>
    </div>
    <?php endif; ?>

    <div class="container" id="taskContainer">
        <div class="layout-wrap" id="layoutWrap">
        <!-- Task Details Card -->
        <div class="card" id="taskDetailsCard">
            <div class="card-header">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <a href="<?php
                        // Smart back: use referrer if it's from our own site (but not this same page), else fall back to index.php
                        $back_url = 'index.php';
                        if (!empty($_SERVER['HTTP_REFERER'])) {
                            $ref = $_SERVER['HTTP_REFERER'];
                            $host = $_SERVER['HTTP_HOST'];
                            // Only use referrer if it's from the same host and not the same edit_task page
                            if (strpos($ref, $host) !== false && strpos($ref, 'edit_task.php?task_id=' . $task_id) === false) {
                                $back_url = $ref;
                            }
                        }
                        echo htmlspecialchars($back_url);
                    ?>" class="back-btn" title="Go back">← Back</a>
                    <h2 class="task-id-wrapper">
                        Task #<?php echo $task_id; ?>
                        <button type="button" class="copy-link-btn" onclick="copyTaskLink()" title="Copy link to clipboard">
                            📋
                        </button>
                    </h2>
                </div>
                <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                    <div class="layout-toggle" title="Switch layout">
                        <button type="button" class="layout-toggle-btn active" data-layout="stacked" onclick="setLayout('stacked')" title="Stacked layout">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="8" rx="1"/><rect x="3" y="13" width="18" height="8" rx="1"/></svg>
                        </button>
                        <button type="button" class="layout-toggle-btn" data-layout="side" onclick="setLayout('side')" title="Side by side layout">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="8" height="18" rx="1"/><rect x="13" y="3" width="8" height="18" rx="1"/></svg>
                        </button>
                    </div>
                    <?php if ($is_active): ?>
                        <a href="edit_task.php?task_id=<?php echo $task_id; ?>&action=stop&completion=<?php echo intval($task['completion']); ?>" class="btn btn-danger btn-sm" title="Stop">
                            &#9632; <span class="btn-label">Stop</span>
                        </a>
                    <?php else: ?>
                        <a href="edit_task.php?task_id=<?php echo $task_id; ?>&action=start&completion=<?php echo intval($task['completion']); ?>" class="btn btn-success btn-sm" title="Start">
                            &#9654; <span class="btn-label">Start</span>
                        </a>
                    <?php endif; ?>
                    <?php if (!$edit_mode): ?>
                        <a href="edit_task.php?task_id=<?php echo $task_id; ?>&edit=1" class="btn btn-secondary btn-sm" title="Edit">✏️ <span class="btn-label">Edit</span></a>
                    <?php endif; ?>
                    <a href="create_task.php?task_id=<?php echo $task_id; ?>" class="btn btn-secondary btn-sm" title="Duplicate">📋 <span class="btn-label">Duplicate</span></a>
                    <?php if ($task['is_closed']): ?>
                    <a href="edit_task.php?task_id=<?php echo $task_id; ?>&action=reopen" class="btn btn-success btn-sm" title="Reopen">↻ <span class="btn-label">Reopen</span></a>
                    <?php else: ?>
                    <button type="button" class="btn btn-warning btn-sm" onclick="showCloseConfirm()" title="Close">✓ <span class="btn-label">Close</span></button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if ($task['is_closed']): ?>
                <div class="closed-task-banner">
                    <div class="banner-content">
                        <span class="banner-icon">✓</span>
                        <div class="banner-text">
                            <h3>This task is closed</h3>
                            <p>Closed<?php echo (!empty($task['close_date'])) ? ' on ' . date('D jS M Y', strtotime($task['close_date'])) : ''; ?></p>
                        </div>
                    </div>
                    <a href="edit_task.php?task_id=<?php echo $task_id; ?>&action=reopen" class="btn-reopen">↻ Reopen Task</a>
                </div>
                <?php endif; ?>

                <!-- View Mode -->
                <div class="view-mode <?php echo $edit_mode ? 'hidden' : ''; ?><?php echo $task['is_closed'] ? ' task-closed' : ''; ?>">
                    <div class="task-header">
                        <div>
                            <div class="task-title-wrapper">
                                <h1 class="task-title"><?php echo htmlspecialchars($task['task_title']); ?></h1>
                                <button type="button" class="copy-name-btn" onclick="copyTaskName()" title="Copy task name to clipboard">📋</button>
                            </div>
                            <span class="task-id">Created: <?php echo date('D jS M Y, H:i', strtotime($task['creation_date'])); ?> by <strong class="creator-name"><?php echo htmlspecialchars($task['creator_first_name'] . ' ' . $task['creator_last_name']); ?></strong></span>
                        </div>
                        <span class="status-badge status-<?php echo $is_active ? 'active' : ($task['task_status_id'] == 8 ? 'waiting' : 'new'); ?>">
                            <?php echo htmlspecialchars($task['status_desc']); ?>
                        </span>
                    </div>

                    <div class="task-meta">
                        <div class="meta-item">
                            <span class="meta-label">Project</span>
                            <span class="meta-value">
                                <?php if ($task['parent_project_title']): ?>
                                    <?php echo htmlspecialchars($task['parent_project_title']); ?> &rarr;
                                <?php endif; ?>
                                <a href="view_project_tasks.php?project_id=<?php echo $task['project_id']; ?>">
                                    <?php echo htmlspecialchars($task['project_title']); ?>
                                </a>
                            </span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Assigned To</span>
                            <span class="meta-value"><?php echo htmlspecialchars($task['first_name'] . ' ' . $task['last_name']); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Type</span>
                            <span class="meta-value"><?php echo htmlspecialchars($task['type_desc']); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Deadline</span>
                            <span class="meta-value">
                                <?php 
                                $deadline_status = get_deadline_status($planed_date);
                                $display_date = ($planed_date && $planed_date != '0000-00-00') ? date('j M Y', strtotime($planed_date)) : '';
                                if ($display_date) echo $display_date;
                                ?>
                                <span class="deadline-indicator <?php echo $deadline_status['class']; ?>">
                                    <?php echo $deadline_status['icon']; ?> <?php echo $deadline_status['text']; ?>
                                </span>
                            </span>
                        </div>
                        <?php if ($task['task_domain_url']): ?>
                        <div class="meta-item">
                            <span class="meta-label">Domain <a href="view_clients.php?q=<?php echo urlencode($task['task_domain_url']); ?>" target="_blank" title="Search clients by this domain" style="color: #a0aec0; text-decoration: none; vertical-align: middle;" onmouseover="this.style.color='#667eea'" onmouseout="this.style.color='#a0aec0'"><svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></a></span>
                            <span class="meta-value">
                                <a href="http://<?php echo htmlspecialchars($task['task_domain_url']); ?>" target="_blank"><?php echo htmlspecialchars($task['task_domain_url']); ?></a>
                            </span>
                        </div>
                        <?php endif; ?>
                        <?php if ($task['client_name']): ?>
                        <div class="meta-item">
                            <span class="meta-label">Client</span>
                            <span class="meta-value"><?php echo htmlspecialchars($task['client_name']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Time Info -->
                    <div class="time-info">
                        <div class="time-block">
                            <div class="time-value"><?php echo number_format($task['actual_hours'], 1); ?></div>
                            <div class="time-label">Hours Spent</div>
                        </div>
                        <div class="time-block">
                            <div class="time-value"><?php echo number_format($task['estimated_hours'], 1); ?></div>
                            <div class="time-label">Estimated</div>
                        </div>
                        <div class="time-block">
                            <div class="time-value"><?php echo intval($task['completion']); ?>%</div>
                            <div class="time-label">Complete</div>
                            <div class="progress-bar" style="width: 100px;">
                                <div class="progress-fill" style="width: <?php echo intval($task['completion']); ?>%"></div>
                            </div>
                        </div>
                        <div class="time-block">
                            <div class="time-value"><?php echo count($messages); ?></div>
                            <div class="time-label">Messages</div>
                        </div>
                        <div class="time-block">
                            <div class="time-value"><?php echo count($task_attachments); ?></div>
                            <div class="time-label">Attachments</div>
                        </div>
                    </div>

                    <?php if ($task['task_desc']): ?>
                    <div class="task-description">
                        <h3>
                            Description
                            <button type="button" class="expand-btn" onclick="openTaskModal()" title="View full description">
                                ⛶ Full View
                            </button>
                            <button type="button" class="expand-btn" onclick="copyDescription()" title="Copy description to clipboard">
                                📋 Copy
                            </button>
                        </h3>
                        <div class="task-description-content" onclick="if(event.target.tagName!=='A')openTaskModal()" title="Click to expand"><?php echo expand_task_desc_attachments($task['task_desc'], $task_id, $task_attachments); ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($task_attachments)): ?>
                    <div class="task-description">
                        <h3>Attachments</h3>
                        <div class="attachments-list">
                            <?php foreach ($task_attachments as $att): 
                                $ext = strtolower(pathinfo($att['file_name'], PATHINFO_EXTENSION));
                                $is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                $file_url = 'attachments/task/' . $task_id . '_' . htmlspecialchars($att['file_name']);
                            ?>
                            <?php if ($is_image): ?>
                            <a href="<?php echo $file_url; ?>" class="attachment-thumbnail gallery-image" 
                               data-gallery="task" data-name="<?php echo htmlspecialchars($att['file_name']); ?>"
                               onclick="openLightbox(this); return false;">
                                <img src="<?php echo $file_url; ?>" alt="<?php echo htmlspecialchars($att['file_name']); ?>">
                                <span class="attachment-thumbnail-name"><?php echo htmlspecialchars($att['file_name']); ?></span>
                            </a>
                            <?php else: ?>
                            <a href="<?php echo $file_url; ?>" class="attachment-item" target="_blank">
                                📎 <?php echo htmlspecialchars($att['file_name']); ?>
                            </a>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Task Detail Modal -->
                    <div class="task-modal-overlay" id="taskModal">
                        <div class="task-modal">
                            <div class="task-modal-header">
                                <h3>📋 <?php echo htmlspecialchars($task['task_title']); ?></h3>
                                <button type="button" class="task-modal-close" onclick="closeTaskModal()">&times;</button>
                            </div>
                            <div class="task-modal-body">
                                <?php if ($task['task_desc']): ?>
                                <div class="task-modal-section">
                                    <h4 class="task-modal-section-header">
                                        Description
                                        <span class="task-modal-actions">
                                            <button type="button" class="task-modal-btn" onclick="copyModalDescription()" title="Copy to clipboard">📋 Copy</button>
                                            <a href="edit_task.php?task_id=<?php echo (int)$task_id; ?>&edit=1#editTaskDesc" class="task-modal-btn" title="Edit description">✏️ Edit</a>
                                        </span>
                                    </h4>
                                    <div class="task-modal-description"><?php echo expand_task_desc_attachments($task['task_desc'], $task_id, $task_attachments); ?></div>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($task_attachments)): ?>
                                <div class="task-modal-section">
                                    <h4>Attachments (<?php echo count($task_attachments); ?>)</h4>
                                    <div class="task-modal-attachments">
                                        <?php foreach ($task_attachments as $att): 
                                            $ext = strtolower(pathinfo($att['file_name'], PATHINFO_EXTENSION));
                                            $is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                            $icon = '📄';
                                            if ($is_image) $icon = '🖼️';
                                            elseif (in_array($ext, ['pdf'])) $icon = '📕';
                                            elseif (in_array($ext, ['doc', 'docx'])) $icon = '📘';
                                            elseif (in_array($ext, ['xls', 'xlsx'])) $icon = '📗';
                                            elseif (in_array($ext, ['zip', 'rar', '7z'])) $icon = '📦';
                                            $file_url = 'attachments/task/' . $task_id . '_' . htmlspecialchars($att['file_name']);
                                        ?>
                                        <?php if ($is_image): ?>
                                        <a href="<?php echo $file_url; ?>" class="task-modal-attachment task-modal-attachment-image gallery-image" 
                                           data-gallery="modal" data-name="<?php echo htmlspecialchars($att['file_name']); ?>"
                                           onclick="openLightbox(this); return false;">
                                            <img src="<?php echo $file_url; ?>" alt="<?php echo htmlspecialchars($att['file_name']); ?>">
                                            <span class="task-modal-attachment-name"><?php echo htmlspecialchars($att['file_name']); ?></span>
                                        </a>
                                        <?php else: ?>
                                        <a href="<?php echo $file_url; ?>" class="task-modal-attachment" target="_blank">
                                            <span class="task-modal-attachment-icon"><?php echo $icon; ?></span>
                                            <span class="task-modal-attachment-name"><?php echo htmlspecialchars($att['file_name']); ?></span>
                                        </a>
                                        <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if (empty($task['task_desc']) && empty($task_attachments)): ?>
                                <p style="color: #718096; text-align: center; padding: 40px;">No description or attachments for this task.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Edit Mode -->
                <div class="edit-form <?php echo $edit_mode ? 'active' : ''; ?>">
                    <form method="POST" action="edit_task.php?task_id=<?php echo $task_id; ?>">
                        <input type="hidden" name="FormName" value="Form">
                        <input type="hidden" name="FormAction" value="update">
                        <input type="hidden" name="rp" value="<?php echo htmlspecialchars($return_page); ?>">
                        <input type="hidden" name="desc_hash" id="descHash" value="<?php echo htmlspecialchars($desc_hash); ?>">

                        <div class="form-row">
                            <div class="form-group full">
                                <label class="form-label">Title</label>
                                <input type="text" name="task_title" class="form-control" value="<?php echo htmlspecialchars($task['task_title']); ?>" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Project</label>
                                <select name="project_id" class="form-control" id="projectSelect">
                                    <option value="">Select Project</option>
                                    <?php foreach ($projects as $p): ?>
                                    <option value="<?php echo $p['project_id']; ?>" <?php echo $p['project_id'] == $parent_project_id ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($p['project_title']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Sub-Project</label>
                                <select name="sub_project_id" class="form-control" id="subProjectSelect">
                                    <option value="0">None</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Assigned To</label>
                                <input type="hidden" name="responsible_user_id" value="<?php echo $task['responsible_user_id']; ?>">
                                <div class="custom-select" id="editUserSelect">
                                    <div class="custom-select-trigger" tabindex="0">
                                        <span><?php 
                                            foreach ($users as $u) {
                                                if ($u['user_id'] == $task['responsible_user_id']) {
                                                    echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']);
                                                    break;
                                                }
                                            }
                                        ?></span>
                                    </div>
                                    <div class="custom-select-dropdown">
                                        <div class="custom-select-search">
                                            <input type="text" placeholder="Search..." class="select-search-input">
                                        </div>
                                        <div class="custom-select-options">
                                            <?php foreach ($users as $u): ?>
                                            <div class="custom-select-option <?php echo $u['user_id'] == $task['responsible_user_id'] ? 'selected' : ''; ?>" 
                                                 data-value="<?php echo $u['user_id']; ?>">
                                                <?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Status</label>
                                <input type="hidden" name="task_status_id" value="<?php echo $task['task_status_id']; ?>">
                                <div class="custom-select" id="editStatusSelect">
                                    <div class="custom-select-trigger" tabindex="0">
                                        <span><?php echo htmlspecialchars($task['status_desc']); ?></span>
                                    </div>
                                    <div class="custom-select-dropdown">
                                        <div class="custom-select-options">
                                            <?php foreach ($statuses as $s): ?>
                                            <div class="custom-select-option <?php echo $s['status_id'] == $task['task_status_id'] ? 'selected' : ''; ?>" 
                                                 data-value="<?php echo $s['status_id']; ?>">
                                                <?php echo htmlspecialchars($s['status_desc']); ?>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Type</label>
                                <input type="hidden" name="task_type_id" value="<?php echo $task['task_type_id']; ?>">
                                <div class="custom-select" id="editTypeSelect">
                                    <div class="custom-select-trigger" tabindex="0">
                                        <span><?php echo htmlspecialchars($task['type_desc']); ?></span>
                                    </div>
                                    <div class="custom-select-dropdown">
                                        <div class="custom-select-options">
                                            <?php foreach ($task_types as $t): ?>
                                            <div class="custom-select-option <?php echo $t['type_id'] == $task['task_type_id'] ? 'selected' : ''; ?>" 
                                                 data-value="<?php echo $t['type_id']; ?>">
                                                <?php echo htmlspecialchars($t['type_desc']); ?>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Deadline</label>
                                <?php 
                                $deadline_value = ($planed_date && $planed_date != '0000-00-00') ? date('Y-m-d', strtotime($planed_date)) : '';
                                ?>
                                <input type="date" id="deadlinePicker" class="form-control" 
                                       value="<?php echo $deadline_value; ?>"
                                       onchange="updateDeadlineFields(this.value)">
                                <!-- Hidden fields for form compatibility -->
                                <input type="hidden" name="day" id="deadlineDay" value="<?php echo $deadline_value ? date('d', strtotime($planed_date)) : ''; ?>">
                                <input type="hidden" name="month" id="deadlineMonth" value="<?php echo $deadline_value ? date('n', strtotime($planed_date)) : date('n'); ?>">
                                <input type="hidden" name="year" id="deadlineYear" value="<?php echo $deadline_value ? date('Y', strtotime($planed_date)) : date('Y'); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Completion %</label>
                                <input type="number" name="completion" class="form-control" min="0" max="100" 
                                       value="<?php echo intval($task['completion']); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Estimated Hours</label>
                                <input type="text" name="task_estimated_hours" class="form-control" 
                                       value="<?php echo $task['estimated_hours']; ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Domain</label>
                                <input type="text" name="task_domain" class="form-control" 
                                       value="<?php echo htmlspecialchars($task['task_domain_url']); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Client</label>
                                <input type="hidden" name="client_id" value="<?php echo $task['client_id']; ?>">
                                <div class="custom-select" id="editClientSelect">
                                    <div class="custom-select-trigger" tabindex="0">
                                        <span><?php 
                                            $client_name = 'No client';
                                            foreach ($clients as $c) {
                                                if ($c['client_id'] == $task['client_id']) {
                                                    $client_name = $c['client_name'];
                                                    break;
                                                }
                                            }
                                            echo htmlspecialchars($client_name);
                                        ?></span>
                                    </div>
                                    <div class="custom-select-dropdown">
                                        <div class="custom-select-search">
                                            <input type="text" placeholder="Search..." class="select-search-input">
                                        </div>
                                        <div class="custom-select-options">
                                            <div class="custom-select-option <?php echo empty($task['client_id']) ? 'selected' : ''; ?>" data-value="">
                                                No client
                                            </div>
                                            <?php foreach ($clients as $c): ?>
                                            <div class="custom-select-option <?php echo $c['client_id'] == $task['client_id'] ? 'selected' : ''; ?>" 
                                                 data-value="<?php echo $c['client_id']; ?>">
                                                <?php echo htmlspecialchars($c['client_name']); ?>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group full">
                                <label class="form-label">Description</label>
                                <textarea name="task_desc" id="editTaskDesc" class="form-control" rows="6" placeholder="Enter task description... (You can paste images here)"><?php echo htmlspecialchars(ensure_utf8($task['task_desc'])); ?></textarea>
                                <div class="help-text">Tip: Paste images (Ctrl+V) directly into description to attach them</div>
                                <div class="desc-attachment-area">
                                    <div class="attachment-dropzone" id="descDropzone">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                        </svg>
                                        <span>Drop files here or <label for="descFileInput" class="file-label">browse</label></span>
                                        <input type="file" id="descFileInput" multiple style="display: none;">
                                    </div>
                                    <div class="attachment-list" id="descAttachmentList"></div>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                            <a href="edit_task.php?task_id=<?php echo $task_id; ?>" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Messages Section -->
        <div class="card messages-card" id="messages">
            <div class="card-header">
                <h2>Messages (<?php echo $total_messages; ?>)</h2>
            </div>
            <div class="card-body">
                <!-- Message Composer -->
                <div class="message-composer">
                    <div id="messageComposerArea">
                        <input type="hidden" id="msg_task_id" value="<?php echo $task_id; ?>">
                        <input type="hidden" id="msg_trn_user_id" value="<?php echo $session_user_id; ?>">
                        <input type="hidden" id="msg_hash" value="<?php echo $hash; ?>">
                        
                        <textarea class="message-textarea" placeholder="Write a message... (paste or drop files here)" id="messageTextarea"></textarea>

                        <div class="message-attachments-area">
                            <div class="message-dropzone" id="messageDropzone">
                                📎 Drop files here or <label for="messageFileInput" class="file-label">browse</label>
                                <input type="file" id="messageFileInput" multiple style="display: none;">
                            </div>
                            <div class="message-attachment-list" id="messageAttachmentList"></div>
                        </div>

                        <div class="message-options">
                            <div class="message-option">
                                <label>Assign to:</label>
                                <input type="hidden" id="msg_responsible_user_id" value="<?php echo $reply_to_user_id; ?>">
                                <div class="custom-select" id="userSelectMsg">
                                    <div class="custom-select-trigger" tabindex="0">
                                        <span><?php 
                                            foreach ($users as $u) {
                                                if ($u['user_id'] == $reply_to_user_id) {
                                                    echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']);
                                                    break;
                                                }
                                            }
                                        ?></span>
                                    </div>
                                    <div class="custom-select-dropdown">
                                        <div class="custom-select-search">
                                            <input type="text" placeholder="Search..." class="select-search-input">
                                        </div>
                                        <div class="custom-select-options">
                                            <?php foreach ($users as $u): ?>
                                            <div class="custom-select-option <?php echo $u['user_id'] == $reply_to_user_id ? 'selected' : ''; ?>" 
                                                 data-value="<?php echo $u['user_id']; ?>">
                                                <?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="message-option">
                                <label>Status:</label>
                                <input type="hidden" id="msg_task_status_id" value="<?php echo $task['task_status_id']; ?>">
                                <div class="custom-select" id="statusSelectMsg">
                                    <div class="custom-select-trigger" tabindex="0">
                                        <span><?php echo htmlspecialchars($task['status_desc']); ?></span>
                                    </div>
                                    <div class="custom-select-dropdown">
                                        <div class="custom-select-search">
                                            <input type="text" placeholder="Search..." class="select-search-input">
                                        </div>
                                        <div class="custom-select-options">
                                            <?php foreach ($statuses as $s): 
                                                if ($s['status_id'] == 1 && $task['task_status_id'] != 1) continue;
                                            ?>
                                            <div class="custom-select-option <?php echo $s['status_id'] == $task['task_status_id'] ? 'selected' : ''; ?>" 
                                                 data-value="<?php echo $s['status_id']; ?>">
                                                <?php echo htmlspecialchars($s['status_desc']); ?>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="message-option">
                                <label>Completion:</label>
                                <input type="number" id="msg_task_completion" value="<?php echo intval($task['completion']); ?>" 
                                       min="0" max="100" style="width: 70px;">%
                            </div>
                        </div>

                        <div class="message-actions">
                            <button type="button" class="btn btn-primary" id="sendMessageBtn" onclick="submitMessageAjax();">Send Message</button>
                            <?php if (!empty($messages)): ?>
                            <button type="button" class="btn btn-secondary" onclick="quoteLastMessage();">Quote Last</button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-secondary" onclick="quoteOriginalTask();">Quote Original</button>
                            <button type="button" class="btn btn-secondary" id="copyMessageBtn" onclick="copyMessageToClipboard();">Copy</button>
                            <button type="button" class="btn btn-secondary" onclick="document.getElementById('messageTextarea').value = ''; autoQuoteApplied = true;">Clear</button>
                        </div>
                    </div>
                </div>

                <!-- Message List -->
                <div class="message-list">
                    <?php if (empty($messages)): ?>
                        <p style="text-align: center; color: #718096; padding: 40px;">No messages yet. Be the first to add one!</p>
                    <?php else: ?>
                        <?php foreach ($messages as $index => $msg): ?>
                        <div class="message-item" id="msg-<?php echo $index; ?>">
                            <div class="message-header">
                                <div class="message-author">
                                    <div class="message-avatar">
                                        <?php echo strtoupper(substr($msg['first_name'], 0, 1) . substr($msg['last_name'], 0, 1)); ?>
                                    </div>
                                    <div class="message-author-info">
                                        <span class="message-author-name"><?php echo htmlspecialchars($msg['first_name'] . ' ' . $msg['last_name']); ?></span>
                                        <span class="message-date">
                                            <?php echo $msg['formatted_date']; ?>
                                            <?php if ($msg['r_first_name']): ?>
                                            <span class="message-assigned">→ <?php echo htmlspecialchars($msg['r_first_name'] . ' ' . $msg['r_last_name']); ?></span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                                <?php if ($msg['status_caption']): ?>
                                <span class="message-status"><?php echo htmlspecialchars($msg['status_caption']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="message-content"><?php
                                $raw_msg = $msg['message'];
                                $placeholders = array();
                                if (isset($message_attachments[$msg['message_id']])) {
                                    foreach ($message_attachments[$msg['message_id']] as $att) {
                                        $bracket_ref = '[' . $att['file_name'] . ']';
                                        $placeholder = '{{INLINEIMG:' . $msg['message_id'] . ':' . $att['file_name'] . '}}';
                                        $raw_msg = str_replace($bracket_ref, $placeholder, $raw_msg);
                                        $placeholders[] = array('placeholder' => $placeholder, 'att' => $att);
                                    }
                                }
                                $msg_html = format_message_with_quotes($raw_msg);
                                foreach ($placeholders as $p) {
                                    $att = $p['att'];
                                    $att_url = 'attachments/message/' . $msg['message_id'] . '_' . htmlspecialchars($att['file_name']);
                                    $escaped_name = htmlspecialchars($att['file_name']);
                                    $att_ext = strtolower(pathinfo($att['file_name'], PATHINFO_EXTENSION));
                                    if (in_array($att_ext, array('jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'))) {
                                        $replacement = '<a href="' . $att_url . '" class="message-inline-image gallery-image" data-gallery="msg-' . $msg['message_id'] . '" data-name="' . $escaped_name . '" onclick="openLightbox(this); return false;"><img src="' . $att_url . '" alt="' . $escaped_name . '"></a>';
                                    } else {
                                        $replacement = '<a href="' . $att_url . '" class="message-attachment-link" target="_blank" rel="noopener">[' . $escaped_name . ']</a>';
                                    }
                                    $msg_html = str_replace($p['placeholder'], $replacement, $msg_html);
                                }
                                echo normalize_monitor_task_urls($msg_html);
                            ?></div>
                            
                            <?php if (isset($message_attachments[$msg['message_id']])): ?>
                            <div class="message-attachments">
                                <div class="attachments-list" data-gallery="msg-<?php echo $msg['message_id']; ?>">
                                    <?php foreach ($message_attachments[$msg['message_id']] as $att): 
                                        $ext = strtolower(pathinfo($att['file_name'], PATHINFO_EXTENSION));
                                        $is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                        $file_url = 'attachments/message/' . $msg['message_id'] . '_' . htmlspecialchars($att['file_name']);
                                    ?>
                                    <?php if ($is_image): ?>
                                    <a href="<?php echo $file_url; ?>" class="attachment-thumbnail gallery-image" 
                                       data-gallery="msg-<?php echo $msg['message_id']; ?>" data-name="<?php echo htmlspecialchars($att['file_name']); ?>"
                                       onclick="openLightbox(this); return false;">
                                        <img src="<?php echo $file_url; ?>" alt="<?php echo htmlspecialchars($att['file_name']); ?>">
                                        <span class="attachment-thumbnail-name"><?php echo htmlspecialchars($att['file_name']); ?></span>
                                    </a>
                                    <?php else: ?>
                                    <a href="<?php echo $file_url; ?>" class="attachment-item" target="_blank">
                                        📎 <?php echo htmlspecialchars($att['file_name']); ?>
                                    </a>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php if ($total_messages > $messages_per_page): ?>
                <div class="load-more-wrap" id="loadMoreWrap">
                    <button type="button" class="btn-load-more" id="loadMoreBtn" onclick="loadMoreMessages()">
                        Load More <span id="loadMoreCount">(<?php echo $total_messages - $messages_per_page; ?> remaining)</span>
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
        </div><!-- /layout-wrap -->
    </div>

    <script>
    // Fix broken monitor task URLs in message content (edittask.php?taskid= -> edit_task.php?task_id=)
    // Runs on existing and dynamically added content so links work regardless of caching/code path
    function fixMonitorTaskLinksInElement(el) {
        if (!el) return;
        var root = el.nodeType === 9 ? el.body : el;
        if (!root) return;
        var links = root.querySelectorAll ? root.querySelectorAll('.message-content a[href]') : [];
        for (var i = 0; i < links.length; i++) {
            var a = links[i];
            var h = a.getAttribute('href') || a.href || '';
            if (/edittask\.php/i.test(h) || /\btaskid=/i.test(h)) {
                var fixed = h.replace(/edit\s*_?\s*task\.php/gi, 'edit_task.php').replace(/\btaskid=/gi, 'task_id=');
                a.setAttribute('href', fixed);
                a.href = fixed;
                if (a.textContent && (a.textContent.indexOf('edittask') !== -1 || a.textContent.indexOf('taskid=') !== -1)) {
                    a.textContent = a.textContent.replace(/edit\s*_?\s*task\.php/gi, 'edit_task.php').replace(/\btaskid=/gi, 'task_id=');
                }
            }
        }
    }
    fixMonitorTaskLinksInElement(document);

    // Copy code block to clipboard
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.copy-code-btn');
        if (!btn) return;
        var wrap = btn.closest('.msg-code-wrap');
        var codeEl = wrap ? wrap.querySelector('pre code') : null;
        var text = codeEl ? codeEl.textContent : '';
        if (!text) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                btn.classList.add('copied');
                btn.setAttribute('title', 'Copied!');
                setTimeout(function() {
                    btn.classList.remove('copied');
                    btn.setAttribute('title', 'Copy to clipboard');
                }, 1500);
            });
        } else {
            var ta = document.createElement('textarea');
            ta.value = text;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            btn.classList.add('copied');
            btn.setAttribute('title', 'Copied!');
            setTimeout(function() {
                btn.classList.remove('copied');
                btn.setAttribute('title', 'Copy to clipboard');
            }, 1500);
        }
    });

    // ==================== Mark Task as Seen ====================
    (function() {
        var STORAGE_KEY = 'seenTasks';
        var taskId = '<?php echo intval($task_id); ?>';
        try {
            var seen = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
            seen[taskId] = new Date().toISOString();
            localStorage.setItem(STORAGE_KEY, JSON.stringify(seen));
        } catch(e) {}
    })();

    // ==================== Layout Toggle ====================
    function setLayout(mode) {
        var wrap = document.getElementById('layoutWrap');
        var container = document.getElementById('taskContainer');
        document.querySelectorAll('.layout-toggle-btn').forEach(function(b) {
            b.classList.toggle('active', b.dataset.layout === mode);
        });
        if (mode === 'side') {
            wrap.classList.add('side-by-side');
            container.classList.add('wide-layout');
        } else {
            wrap.classList.remove('side-by-side');
            container.classList.remove('wide-layout');
        }
        try { localStorage.setItem('editTaskLayout', mode); } catch(e) {}
    }
    // Restore saved layout
    (function() {
        try {
            var saved = localStorage.getItem('editTaskLayout');
            if (saved === 'side') setLayout('side');
        } catch(e) {}
    })();

    // Auto-dismiss flash messages
    var flashMessage = document.getElementById('flashMessage');
    if (flashMessage) {
        setTimeout(function() {
            flashMessage.style.opacity = '0';
            flashMessage.style.transform = 'translateX(-50%) translateY(-20px)';
            setTimeout(function() { flashMessage.remove(); }, 300);
        }, 5000);
    }

    // Load sub-projects when project changes
    document.getElementById('projectSelect')?.addEventListener('change', function() {
        var projectId = this.value;
        var subProjectSelect = document.getElementById('subProjectSelect');
        
        if (!projectId) {
            subProjectSelect.innerHTML = '<option value="0">None</option>';
            return;
        }

        fetch('ajax_responder.php?action=get_subprojects&project_id=' + projectId)
            .then(response => response.json())
            .then(data => {
                subProjectSelect.innerHTML = '<option value="0">None</option>';
                data.forEach(function(item) {
                    var option = document.createElement('option');
                    option.value = item.id;
                    option.textContent = item.title;
                    if (item.id == <?php echo $sub_project_id; ?>) {
                        option.selected = true;
                    }
                    subProjectSelect.appendChild(option);
                });
            });
    });

    // Trigger initial load if project is selected
    <?php if ($parent_project_id): ?>
    document.getElementById('projectSelect')?.dispatchEvent(new Event('change'));
    <?php endif; ?>

    // Keyboard shortcut for message submission (Ctrl+Enter or Cmd+Enter)
    document.getElementById('messageTextarea')?.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            submitMessageAjax();
        }
    });

    // Submit message with Enter or Cmd+Enter/Ctrl+Enter when focus is in other message form fields (Assign to, Status, Completion)
    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Enter') return;
        var active = document.activeElement;
        if (!active || !active.closest) return;
        var inComposer = active.closest('#messageComposerArea');
        if (!inComposer) return;
        if (active.id === 'messageTextarea') return; // textarea: plain Enter = new line; Ctrl+Enter handled above
        if (active.closest('.custom-select.open')) return; // dropdown open: let Enter select option
        e.preventDefault();
        submitMessageAjax();
    });

    // Keep message editor expanded when focus moves into composer; only collapse after message is submitted
    (function() {
        var area = document.getElementById('messageComposerArea');
        var composer = area ? area.closest('.message-composer') : null;
        if (!area || !composer) return;
        var DEBUG = false; // set _msgComposerDebug = true in console to enable
        function log() {
            if ((DEBUG || window._msgComposerDebug) && window.console && console.log) {
                console.log.apply(console, ['[MsgComposer]'].concat(Array.prototype.slice.call(arguments)));
            }
        }
        area.addEventListener('focusin', function(e) {
            composer.classList.add('expanded');
            log('focusin → add expanded', 'target:', e.target && e.target.id || e.target && e.target.className);
        });
        area.addEventListener('focusout', function(e) {
            var next = e.relatedTarget;
            var inside = next && area.contains(next);
            log('focusout →', inside ? 'stay expanded (focus inside)' : 'not removing expanded (only collapse on submit)', 'relatedTarget:', next && (next.id || next.className));
            // Do not remove .expanded here; only remove when message is submitted (see submitMessageAjax success)
        });
        window._msgComposerCollapse = function() {
            if (composer) composer.classList.remove('expanded');
            log('collapse (after submit)');
        };
        window._msgComposerDebug = false;
    })();

    // Auto-quote on focus: last message if messages exist, otherwise task description
    var messageCount = <?php echo count($messages); ?>;
    var autoQuoteApplied = false;
    
    document.getElementById('messageTextarea')?.addEventListener('focus', function() {
        // Only auto-quote if textarea is empty and hasn't been applied yet
        if (!autoQuoteApplied && this.value.trim() === '') {
            if (messageCount > 0 && lastMessageContent) {
                // Quote the last message
                quoteText(lastMessageContent);
                autoQuoteApplied = true;
            } else if (originalTaskDescription) {
                // Quote the task description for new tasks
                quoteText(originalTaskDescription);
                autoQuoteApplied = true;
            }
        }
    });

    // Custom Select Dropdown functionality
    document.querySelectorAll('.custom-select').forEach(function(select) {
        var trigger = select.querySelector('.custom-select-trigger');
        var dropdown = select.querySelector('.custom-select-dropdown');
        var options = select.querySelectorAll('.custom-select-option');
        var searchInput = select.querySelector('.select-search-input');
        var hiddenInput = select.previousElementSibling;
        var highlightedIndex = -1;
        
        // Find the hidden input (it's before the custom-select div)
        var parent = select.parentElement;
        hiddenInput = parent.querySelector('input[type="hidden"]');

        // Get visible options
        function getVisibleOptions() {
            return Array.from(options).filter(function(opt) {
                return !opt.classList.contains('hidden');
            });
        }

        // Update highlight
        function updateHighlight(newIndex) {
            var visible = getVisibleOptions();
            options.forEach(function(o) { o.classList.remove('highlighted'); });
            if (newIndex >= 0 && newIndex < visible.length) {
                highlightedIndex = newIndex;
                visible[newIndex].classList.add('highlighted');
                // Scroll into view
                visible[newIndex].scrollIntoView({ block: 'nearest' });
            }
        }

        // When focus is in the message textarea, first click on a trigger often only blurs the textarea (shrinks editor); open dropdown on mousedown so it opens in one click
        var messageTextareaEl = document.getElementById('messageTextarea');
        trigger.addEventListener('mousedown', function(e) {
            if (messageTextareaEl && document.activeElement === messageTextareaEl) {
                if (!select.classList.contains('open')) {
                    if (window._msgComposerDebug) console.log('[MsgComposer] trigger mousedown (from textarea) → open', select.id);
                    select._openOnMousedown = true;
                    document.querySelectorAll('.custom-select.open').forEach(function(other) {
                        if (other !== select) other.classList.remove('open');
                    });
                    select.classList.add('open');
                    highlightedIndex = -1;
                    var composer = select.closest('.message-composer');
                    if (composer) composer.classList.add('expanded');
                    if (searchInput) {
                        searchInput.focus();
                        searchInput.value = '';
                        filterOptions('');
                    } else {
                        trigger.focus();
                    }
                }
            }
        });

        // Toggle dropdown
        trigger.addEventListener('click', function(e) {
            e.stopPropagation();
            if (select._openOnMousedown) {
                if (window._msgComposerDebug) console.log('[MsgComposer] trigger click → skip (opened on mousedown)', select.id);
                select._openOnMousedown = false;
                return;
            }
            if (window._msgComposerDebug) console.log('[MsgComposer] trigger click → toggle', select.id, 'currently open:', select.classList.contains('open'));
            // Close other dropdowns
            document.querySelectorAll('.custom-select.open').forEach(function(other) {
                if (other !== select) other.classList.remove('open');
            });
            select.classList.toggle('open');
            if (select.classList.contains('open')) {
                highlightedIndex = -1;
                var composer = select.closest('.message-composer');
                if (composer) composer.classList.add('expanded');
                if (searchInput) {
                    searchInput.focus();
                    searchInput.value = '';
                    filterOptions('');
                } else {
                    trigger.focus();
                }
            }
        });

        // Keyboard navigation on trigger
        trigger.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                if (!select.classList.contains('open')) {
                    trigger.click();
                } else {
                    // Select highlighted option
                    var visible = getVisibleOptions();
                    if (highlightedIndex >= 0 && highlightedIndex < visible.length) {
                        selectOption(visible[highlightedIndex]);
                    }
                }
            } else if (e.key === 'Escape') {
                e.preventDefault();
                select.classList.remove('open');
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (!select.classList.contains('open')) {
                    trigger.click();
                } else {
                    var visible = getVisibleOptions();
                    updateHighlight(Math.min(highlightedIndex + 1, visible.length - 1));
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (select.classList.contains('open')) {
                    updateHighlight(Math.max(highlightedIndex - 1, 0));
                }
            }
        });

        // Option selection
        options.forEach(function(option) {
            option.addEventListener('click', function(e) {
                e.stopPropagation();
                if (window._msgComposerDebug) console.log('[MsgComposer] option click', select.id, 'value:', option.dataset.value, 'text:', (option.textContent || '').trim().substring(0, 30));
                selectOption(option);
            });
        });

        function selectOption(option) {
            if (window._msgComposerDebug) console.log('[MsgComposer] selectOption', select.id, 'value:', option.dataset.value);
            options.forEach(function(o) { o.classList.remove('selected', 'highlighted'); });
            option.classList.add('selected');
            trigger.querySelector('span').textContent = option.textContent.trim();
            if (hiddenInput) hiddenInput.value = option.dataset.value;
            select.classList.remove('open');
            trigger.focus();
        }

        // Search filtering
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                filterOptions(this.value.toLowerCase());
                // Auto-highlight first visible option when filtering
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
            // Keyboard navigation in search input
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
                    // Select highlighted option, or first visible if none highlighted
                    if (highlightedIndex >= 0 && highlightedIndex < visible.length) {
                        selectOption(visible[highlightedIndex]);
                    } else if (visible.length > 0) {
                        selectOption(visible[0]);
                    }
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    select.classList.remove('open');
                    trigger.focus();
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

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        var inside = e.target.closest('.custom-select');
        if (window._msgComposerDebug) console.log('[MsgComposer] document click →', inside ? 'inside custom-select, skip close' : 'close all dropdowns', 'target:', e.target.id || e.target.className);
        if (inside) return;
        document.querySelectorAll('.custom-select.open').forEach(function(select) {
            select.classList.remove('open');
        });
    });

    // Close dropdowns on Escape key (global)
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.custom-select.open').forEach(function(select) {
                select.classList.remove('open');
            });
        }
    });

    // Status/User change auto-updates
    // Status IDs: 4 = Done, 7 = New/Not started, 9 = Reassigned
    var STATUS_DONE = '4';
    var STATUS_NEW = '7';
    var STATUS_REASSIGNED = '9';
    
    // Store initial user assignment to detect changes
    var initialUserId = '<?php echo isset($task['responsible_user_id']) ? $task['responsible_user_id'] : ''; ?>';
    
    // Watch for status changes in message form
    var statusSelectMsg = document.getElementById('statusSelectMsg');
    if (statusSelectMsg) {
        var statusObserver = new MutationObserver(function(mutations) {
            var hiddenInput = document.getElementById('msg_task_status_id');
            if (hiddenInput) {
                var statusId = hiddenInput.value;
                var completionInput = document.getElementById('msg_task_completion');
                if (completionInput) {
                    if (statusId === STATUS_DONE) {
                        completionInput.value = 100;
                    } else if (statusId === STATUS_NEW) {
                        completionInput.value = 0;
                    }
                }
            }
        });
        statusObserver.observe(statusSelectMsg, { attributes: true, subtree: true, attributeFilter: ['class'] });
        
        // Also listen for hidden input changes
        var statusHiddenInput = document.getElementById('msg_task_status_id');
        if (statusHiddenInput) {
            var originalValue = statusHiddenInput.value;
            setInterval(function() {
                if (statusHiddenInput.value !== originalValue) {
                    originalValue = statusHiddenInput.value;
                    var completionInput = document.querySelector('input[name="task_completion"]');
                    if (completionInput) {
                        if (originalValue === STATUS_DONE) {
                            completionInput.value = 100;
                        } else if (originalValue === STATUS_NEW) {
                            completionInput.value = 0;
                        }
                    }
                }
            }, 100);
        }
    }
    
    // Watch for user changes in message form - set status to reassigned
    var userSelectMsg = document.getElementById('userSelectMsg');
    if (userSelectMsg) {
        var userHiddenInput = document.getElementById('msg_responsible_user_id');
        if (userHiddenInput) {
            var lastUserId = userHiddenInput.value;
            setInterval(function() {
                if (userHiddenInput.value !== lastUserId) {
                    lastUserId = userHiddenInput.value;
                    // User changed - set status to reassigned
                    if (lastUserId !== initialUserId) {
                        var statusHidden = document.getElementById('msg_task_status_id');
                        var statusTrigger = document.querySelector('#statusSelectMsg .custom-select-trigger span');
                        if (statusHidden && statusTrigger) {
                            statusHidden.value = STATUS_REASSIGNED;
                            // Update the display text
                            var reassignedOption = document.querySelector('#statusSelectMsg .custom-select-option[data-value="' + STATUS_REASSIGNED + '"]');
                            if (reassignedOption) {
                                statusTrigger.textContent = reassignedOption.textContent.trim();
                                // Update selected state
                                document.querySelectorAll('#statusSelectMsg .custom-select-option').forEach(function(opt) {
                                    opt.classList.remove('selected');
                                });
                                reassignedOption.classList.add('selected');
                            }
                        }
                    }
                }
            }, 100);
        }
    }

    // Task Detail Modal functions
    // Move modal to body so it escapes any stacking context (e.g. sticky sidebar)
    (function() {
        var modal = document.getElementById('taskModal');
        if (modal && modal.parentNode !== document.body) {
            document.body.appendChild(modal);
        }
    })();

    function openTaskModal() {
        document.getElementById('taskModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeTaskModal() {
        document.getElementById('taskModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    // Copy description to clipboard
    function copyDescription() {
        var descEl = document.querySelector('.task-description-content');
        if (!descEl) return;
        var text = descEl.innerText || descEl.textContent || '';
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                showCopyToast('Description copied to clipboard');
            }).catch(function() {
                fallbackCopy(text);
            });
        } else {
            fallbackCopy(text);
        }
    }

    function copyModalDescription() {
        var descEl = document.querySelector('#taskModal .task-modal-description');
        if (!descEl) return;
        var text = descEl.innerText || descEl.textContent || '';
        text = text.trim();
        if (!text) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                showCopyToast('Description copied to clipboard');
            }).catch(function() {
                fallbackCopy(text);
            });
        } else {
            fallbackCopy(text);
        }
    }

    function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
            showCopyToast('Description copied to clipboard');
        } catch(e) {
            showCopyToast('Failed to copy');
        }
        document.body.removeChild(ta);
    }

    function showCopyToast(message) {
        var existing = document.getElementById('copyToast');
        if (existing) existing.remove();
        var toast = document.createElement('div');
        toast.id = 'copyToast';
        toast.textContent = message;
        toast.style.cssText = 'position:fixed;bottom:30px;left:50%;transform:translateX(-50%);background:#48bb78;color:#fff;padding:10px 24px;border-radius:8px;font-size:0.9rem;font-weight:500;z-index:99999;box-shadow:0 4px 12px rgba(0,0,0,0.15);transition:opacity 0.3s;';
        document.body.appendChild(toast);
        setTimeout(function() {
            toast.style.opacity = '0';
            setTimeout(function() { toast.remove(); }, 300);
        }, 2000);
    }

    // Close modal on overlay click
    document.getElementById('taskModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeTaskModal();
        }
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.getElementById('taskModal')?.classList.contains('active')) {
            closeTaskModal();
        }
    });

    // Original task description for quoting
    <?php 
    $task_desc_for_js = isset($task['task_desc']) && $task['task_desc'] !== null ? ensure_utf8($task['task_desc']) : '';
    $task_desc_json = json_encode($task_desc_for_js);
    if ($task_desc_json === false) {
        $task_desc_json = '""'; // Fallback to empty string if encoding fails
    }
    ?>
    const originalTaskDescription = <?php echo $task_desc_json; ?>;
    
    // Last message content for quoting (if messages exist)
    <?php 
    $last_message_content = '';
    if (!empty($messages)) {
        // Get the last message (first in the array since sorted DESC)
        $last_msg = $messages[0];
        $last_message_content = isset($last_msg['message']) ? $last_msg['message'] : '';
    }
    $last_msg_json = json_encode($last_message_content);
    if ($last_msg_json === false) {
        $last_msg_json = '""';
    }
    ?>
    var lastMessageContent = <?php echo $last_msg_json; ?>;

    // Quote text into textarea
    function quoteText(text) {
        const textarea = document.getElementById('messageTextarea');
        if (!text) {
            return false;
        }
        
        // Format the text with > at the beginning of each line
        const quotedText = text
            .split('\n')
            .map(line => '> ' + line)
            .join('\n');
        
        // Two empty lines before the quote, cursor at the top
        textarea.value = '\n\n' + quotedText;
        
        // Use setTimeout to ensure cursor positioning after focus
        setTimeout(function() {
            textarea.focus();
            textarea.setSelectionRange(0, 0);
            // Scroll to top of textarea
            textarea.scrollTop = 0;
        }, 10);
        
        return true;
    }

    // Show flash message
    function showFlashMessage(message, type) {
        var flash = document.getElementById('ajaxFlashMessage');
        flash.textContent = message;
        flash.className = type + ' show';
        setTimeout(function() {
            flash.className = '';
        }, 4000);
    }

    // Submit message via AJAX
    function submitMessageAjax() {
        var submitBtn = document.getElementById('sendMessageBtn');
        var originalText = submitBtn.textContent;
        
        // Disable button and show loading state
        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending...';
        
        // Gather form data from individual elements
        var formData = new FormData();
        formData.append('action', 'add_task_message');
        formData.append('task_id', document.getElementById('msg_task_id').value);
        formData.append('message', document.getElementById('messageTextarea').value);
        formData.append('responsible_user_id', document.getElementById('msg_responsible_user_id').value);
        formData.append('task_status_id', document.getElementById('msg_task_status_id').value);
        formData.append('task_completion', document.getElementById('msg_task_completion').value);
        formData.append('hash', document.getElementById('msg_hash').value);
        
        fetch('ajax_responder.php', {
            method: 'POST',
            body: formData,
            credentials: 'include'
        })
        .then(function(response) { 
            return response.text().then(function(text) {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('JSON parse error:', text);
                    throw new Error('Invalid JSON response');
                }
            });
        })
        .then(function(data) {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
            
            if (data.success) {
                showFlashMessage('Message sent successfully!', 'success');

                if (window._msgComposerCollapse) window._msgComposerCollapse();

                // Clear textarea and reset quote flag
                document.getElementById('messageTextarea').value = '';
                autoQuoteApplied = false;
                
                // Clear attachments
                messageAttachments = [];
                document.getElementById('messageAttachmentList').innerHTML = '';
                
                // Update message count in header
                var msgHeader = document.querySelector('#messages .card-header h2');
                if (msgHeader) {
                    msgHeader.textContent = 'Messages (' + data.message_count + ')';
                }
                
                // Update task info display
                if (data.task) {
                    // Update status in view mode
                    document.querySelectorAll('.meta-item').forEach(function(item) {
                        var label = item.querySelector('.meta-label');
                        if (label && label.textContent.trim() === 'Status') {
                            var value = item.querySelector('.meta-value');
                            if (value) value.textContent = data.task.status_desc || '';
                        }
                        if (label && label.textContent.trim() === 'Assigned To') {
                            var value = item.querySelector('.meta-value');
                            if (value) value.textContent = data.task.responsible_name || '';
                        }
                        if (label && label.textContent.trim() === 'Complete') {
                            var value = item.querySelector('.meta-value');
                            if (value) {
                                var comp = data.task.completion || 0;
                                value.innerHTML = comp + '%<div class="progress-bar"><div class="progress-fill" style="width: ' + comp + '%;"></div></div>';
                            }
                        }
                    });
                }
                
                // Update message list with highlight on new message
                if (data.messages) {
                    updateMessageList(data.messages, data.message_attachments || {}, true);
                    // Reset pagination state
                    messagesLoaded = data.messages.length;
                    messagesTotal = data.message_count || messagesLoaded;
                    var loadMoreWrap = document.getElementById('loadMoreWrap');
                    if (messagesTotal > messagesLoaded) {
                        if (!loadMoreWrap) {
                            // Create it if it doesn't exist
                            var ml = document.querySelector('.message-list');
                            if (ml) {
                                var wrap = document.createElement('div');
                                wrap.className = 'load-more-wrap';
                                wrap.id = 'loadMoreWrap';
                                wrap.innerHTML = '<button type="button" class="btn-load-more" id="loadMoreBtn" onclick="loadMoreMessages()">Load More <span id="loadMoreCount">(' + (messagesTotal - messagesLoaded) + ' remaining)</span></button>';
                                ml.parentNode.insertBefore(wrap, ml.nextSibling);
                            }
                        } else {
                            loadMoreWrap.style.display = '';
                            var btn = document.getElementById('loadMoreBtn');
                            if (btn) btn.innerHTML = 'Load More <span id="loadMoreCount">(' + (messagesTotal - messagesLoaded) + ' remaining)</span>';
                        }
                    } else if (loadMoreWrap) {
                        loadMoreWrap.style.display = 'none';
                    }
                    // Scroll to the new message
                    var newMsg = document.querySelector('.message-list .message-item');
                    if (newMsg) {
                        newMsg.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
                
                // Update last message for quoting
                if (data.messages && data.messages.length > 0) {
                    lastMessageContent = data.messages[0].message || '';
                    messageCount = data.messages.length;
                    
                    // Show Quote Last button if it was hidden
                    var quoteLastBtn = document.querySelector('.message-actions button[onclick*="quoteLastMessage"]');
                    if (!quoteLastBtn) {
                        var quoteOrigBtn = document.querySelector('.message-actions button[onclick*="quoteOriginalTask"]');
                        if (quoteOrigBtn) {
                            var newBtn = document.createElement('button');
                            newBtn.type = 'button';
                            newBtn.className = 'btn btn-secondary';
                            newBtn.onclick = function() { quoteLastMessage(); };
                            newBtn.textContent = 'Quote Last';
                            quoteOrigBtn.parentNode.insertBefore(newBtn, quoteOrigBtn);
                        }
                    }
                    
                    // Auto-update "Assign to" to the last person who messaged only if that person is not the current user (otherwise leave current assignee)
                    var currentUid = document.getElementById('msg_trn_user_id') ? document.getElementById('msg_trn_user_id').value : '';
                    var replyToUserId = null;
                    if (data.messages.length > 0 && data.messages[0].user_id && data.messages[0].user_id != currentUid) {
                        replyToUserId = data.messages[0].user_id;
                    }
                    if (replyToUserId) {
                        var userHidden = document.getElementById('msg_responsible_user_id');
                        if (userHidden) userHidden.value = replyToUserId;
                        // Update the dropdown display
                        var userSelect = document.getElementById('userSelectMsg');
                        if (userSelect) {
                            var opts = userSelect.querySelectorAll('.custom-select-option');
                            for (var oi = 0; oi < opts.length; oi++) {
                                if (opts[oi].getAttribute('data-value') == replyToUserId) {
                                    opts[oi].classList.add('selected');
                                    var trigger = userSelect.querySelector('.custom-select-trigger span');
                                    if (trigger) trigger.textContent = opts[oi].textContent.trim();
                                } else {
                                    opts[oi].classList.remove('selected');
                                }
                            }
                        }
                        // Also update initialUserId so the reassignment detection works properly
                        initialUserId = replyToUserId.toString();
                    }
                }
                
                // Generate new hash for next message
                messageHashValue = Math.random().toString(16).substr(2, 8);
                document.getElementById('msg_hash').value = messageHashValue;
                
            } else {
                showFlashMessage(data.error || 'Failed to send message', 'error');
            }
        })
        .catch(function(error) {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
            showFlashMessage('Error: ' + error.message, 'error');
            console.error('Error:', error);
        });
        
        return false;
    }

    // Load More messages pagination
    var messagesLoaded = <?php echo count($messages); ?>;
    var messagesTotal = <?php echo $total_messages; ?>;
    var messagesPerPage = <?php echo $messages_per_page; ?>;
    
    function loadMoreMessages() {
        var btn = document.getElementById('loadMoreBtn');
        if (!btn) return;
        btn.disabled = true;
        btn.innerHTML = 'Loading...';
        
        var formData = new FormData();
        formData.append('action', 'get_task_messages');
        formData.append('task_id', '<?php echo $task_id; ?>');
        formData.append('offset', messagesLoaded);
        formData.append('limit', messagesPerPage);
        
        fetch('ajax_responder.php', { method: 'POST', body: formData, credentials: 'include' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success && data.messages && data.messages.length > 0) {
                var container = document.querySelector('.message-list');
                data.messages.forEach(function(msg) {
                    var div = buildMessageHTML(msg, data.message_attachments || {}, messagesLoaded);
                    container.insertAdjacentHTML('beforeend', div);
                    messagesLoaded++;
                });
                fixMonitorTaskLinksInElement(container);

                messagesTotal = data.total || messagesTotal;
                var remaining = messagesTotal - messagesLoaded;
                if (remaining <= 0) {
                    document.getElementById('loadMoreWrap').style.display = 'none';
                } else {
                    btn.disabled = false;
                    btn.innerHTML = 'Load More <span id="loadMoreCount">(' + remaining + ' remaining)</span>';
                }
            } else {
                document.getElementById('loadMoreWrap').style.display = 'none';
            }
        })
        .catch(function(err) {
            btn.disabled = false;
            btn.innerHTML = 'Load More (retry)';
            console.error('Load more error:', err);
        });
    }
    
    function buildMessageHTML(msg, attachments, index) {
        var firstName = msg.first_name || '';
        var lastName = msg.last_name || '';
        var initials = ((firstName.charAt(0) || '') + (lastName.charAt(0) || '')).toUpperCase() || '?';
        var assignedTo = msg.r_first_name ? ' <span class="message-assigned">→ ' + escapeHtml((msg.r_first_name || '') + ' ' + (msg.r_last_name || '')) + '</span>' : '';
        var statusBadge = msg.status_caption ? '<span class="message-status">' + escapeHtml(msg.status_caption) + '</span>' : '';
        var highlightClass = msg._highlight ? ' message-highlight' : '';
        
        var html = '<div class="message-item' + highlightClass + '" id="msg-' + index + '">';
        html += '<div class="message-header">';
        html += '<div class="message-author">';
        html += '<div class="message-avatar">' + initials + '</div>';
        html += '<div class="message-author-info">';
        html += '<span class="message-author-name">' + escapeHtml(firstName + ' ' + lastName) + '</span>';
        html += '<span class="message-date">' + (msg.formatted_date || '') + assignedTo + '</span>';
        html += '</div></div>';
        html += statusBadge;
        html += '</div>';
        var contentHtml;
        var rawMsg = msg.message || '';
        var placeholders = [];
        if (attachments && attachments[msg.message_id]) {
            attachments[msg.message_id].forEach(function(att) {
                var bracketRef = '[' + att.file_name + ']';
                var placeholder = '{{INLINEIMG:' + msg.message_id + ':' + att.file_name + '}}';
                rawMsg = rawMsg.split(bracketRef).join(placeholder);
                placeholders.push({ placeholder: placeholder, att: att });
            });
        }
        contentHtml = formatMessageContent(rawMsg);
        placeholders.forEach(function(p) {
            var att = p.att;
            var filePath = 'attachments/message/' + msg.message_id + '_' + att.file_name;
            var ext = att.file_name.split('.').pop().toLowerCase();
            var replacement;
            if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].indexOf(ext) !== -1) {
                replacement = '<a href="' + filePath + '" class="message-inline-image gallery-image" data-gallery="msg-' + msg.message_id + '" data-name="' + escapeHtml(att.file_name) + '" onclick="openLightbox(this); return false;"><img src="' + filePath + '" alt="' + escapeHtml(att.file_name) + '"></a>';
            } else {
                replacement = '<a href="' + filePath + '" class="message-attachment-link" target="_blank" rel="noopener">[' + escapeHtml(att.file_name) + ']</a>';
            }
            contentHtml = contentHtml.split(p.placeholder).join(replacement);
        });
        html += '<div class="message-content">' + contentHtml + '</div>';
        
        // Add attachments (thumbnails at bottom)
        if (attachments && attachments[msg.message_id]) {
            html += '<div class="message-attachments"><div class="attachments-list" data-gallery="msg-' + msg.message_id + '">';
            attachments[msg.message_id].forEach(function(att) {
                var ext = att.file_name.split('.').pop().toLowerCase();
                var isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].indexOf(ext) !== -1;
                var filePath = 'attachments/message/' + msg.message_id + '_' + att.file_name;
                if (isImage) {
                    html += '<a href="' + filePath + '" class="attachment-thumbnail gallery-image" data-gallery="msg-' + msg.message_id + '" onclick="openLightbox(this); return false;">';
                    html += '<img src="' + filePath + '" alt="' + escapeHtml(att.file_name) + '">';
                    html += '<span class="attachment-thumbnail-name">' + escapeHtml(att.file_name) + '</span></a>';
                } else {
                    html += '<a href="' + filePath + '" class="attachment-item" target="_blank">📎 ' + escapeHtml(att.file_name) + '</a>';
                }
            });
            html += '</div></div>';
        }
        
        html += '</div>';
        return html;
    }

    // Update message list dynamically (used after posting a new message)
    function updateMessageList(messages, attachments, highlightFirst) {
        var container = document.querySelector('.message-list');
        if (!container) return;
        
        if (!messages || messages.length === 0) {
            container.innerHTML = '<p style="text-align: center; color: #718096; padding: 40px;">No messages yet. Be the first to add one!</p>';
            return;
        }
        
        attachments = attachments || {};
        
        var html = '';
        messages.forEach(function(msg, index) {
            // Mark first message for highlight if requested
            if (highlightFirst && index === 0) msg._highlight = true;
            html += buildMessageHTML(msg, attachments, index);
        });
        
        container.innerHTML = html;
        fixMonitorTaskLinksInElement(container);

        // Remove highlight after 3 seconds
        if (highlightFirst) {
            setTimeout(function() {
                var highlighted = container.querySelector('.message-highlight');
                if (highlighted) {
                    highlighted.classList.remove('message-highlight');
                }
            }, 3000);
        }
    }

    // Format message content with quotes and simple markdown (## ### ** *)
    function formatMessageContent(text) {
        if (!text) return '';
        text = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
        var lines = text.split('\n');
        var html = '';
        
        function applySimpleMarkdown(escapedLine) {
            var urlPattern = /(https?:\/\/[^\s<]+)/g;
            var urls = [];
            var t = escapedLine.replace(urlPattern, function(m) {
                urls.push(m);
                return '\x01U' + (urls.length - 1) + '\x01';
            });
            if (/^###\s+/.test(t)) {
                t = '<h3 class="msg-heading msg-h3">' + t.replace(/^###\s+/, '') + '</h3>';
            } else if (/^##\s+/.test(t)) {
                t = '<h2 class="msg-heading msg-h2">' + t.replace(/^##\s+/, '') + '</h2>';
            } else if (/^#\s+/.test(t)) {
                t = '<h1 class="msg-heading msg-h1">' + t.replace(/^#\s+/, '') + '</h1>';
            } else {
                t = t.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
                t = t.replace(/\*(.+?)\*/g, '<em>$1</em>');
                t = t.replace(/__(.+?)__/g, '<strong>$1</strong>');
                t = t.replace(/_(.+?)_/g, '<em>$1</em>');
                t = t.replace(/^\s+|\s+$/g, '') === '' ? '<br>' : t + '<br>';
            }
            urls.forEach(function(url, i) {
                url = url.replace(/&amp;/g, '&');
                url = url.replace(/edit\s*_?\s*task\.php/gi, 'edit_task.php').replace(/\btaskid=/gi, 'task_id=');
                var link = '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener">' + escapeHtml(url) + '</a>';
                t = t.split('\x01U' + i + '\x01').join(link);
            });
            return t;
        }
        
        lines.forEach(function(line) {
            var trimmed = line.replace(/^\s+/, '');
            var level = 0;
            while (trimmed.length > 0 && trimmed.charAt(0) === '>') {
                level++;
                trimmed = trimmed.substring(1).replace(/^\s+/, '');
            }
            var raw = level > 0 ? trimmed : line;
            var escaped = escapeHtml(raw);
            var withLinks = normalizeMonitorTaskUrls(applySimpleMarkdown(escaped));
            var cappedLevel = Math.min(level, 5);
            if (level > 0) {
                html += '<span class="quote-line quote-level-' + cappedLevel + '">' + withLinks + '</span>';
            } else {
                html += withLinks;
            }
        });
        
        html = normalizeMonitorTaskUrls(html);
        return html;
    }

    // Convert URLs to clickable links
    function linkifyUrls(text) {
        var urlPattern = /(https?:\/\/[^\s<]+)/g;
        return text.replace(urlPattern, '<a href="$1" target="_blank" rel="noopener">$1</a>');
    }

    // Fix broken monitor task URLs (edittask.php?taskid= -> edit_task.php?task_id=)
    function normalizeMonitorTaskUrls(html) {
        return html.replace(/edit\s*_?\s*task\.php/gi, 'edit_task.php').replace(/\btaskid=/gi, 'task_id=');
    }

    // Update deadline hidden fields when date picker changes
    function updateDeadlineFields(dateValue) {
        if (dateValue) {
            const date = new Date(dateValue);
            document.getElementById('deadlineDay').value = String(date.getDate()).padStart(2, '0');
            document.getElementById('deadlineMonth').value = date.getMonth() + 1;
            document.getElementById('deadlineYear').value = date.getFullYear();
        } else {
            document.getElementById('deadlineDay').value = '';
            document.getElementById('deadlineMonth').value = new Date().getMonth() + 1;
            document.getElementById('deadlineYear').value = new Date().getFullYear();
        }
    }

    // ==================== Description editor: VS Code–like line shortcuts ====================
    (function() {
        function attachEditorShortcuts(el) {
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
                var mod = e.ctrlKey || e.metaKey;

                if (mod && e.key === 'l') {
                    e.preventDefault();
                    selectLine();
                    return;
                }
                if (mod && e.shiftKey && (e.key === 'K' || e.key === 'k')) {
                    e.preventDefault();
                    deleteLine();
                    return;
                }
                if (e.altKey && e.key === 'ArrowUp') {
                    e.preventDefault();
                    moveLine(-1);
                    return;
                }
                if (e.altKey && e.key === 'ArrowDown') {
                    e.preventDefault();
                    moveLine(1);
                    return;
                }
                if (e.shiftKey && e.altKey && e.key === 'ArrowUp') {
                    e.preventDefault();
                    copyLine(-1);
                    return;
                }
                if (e.shiftKey && e.altKey && e.key === 'ArrowDown') {
                    e.preventDefault();
                    copyLine(1);
                    return;
                }
            });
        }

        attachEditorShortcuts(document.getElementById('editTaskDesc'));
        attachEditorShortcuts(document.getElementById('messageTextarea'));
    })();

    // ==================== Description Attachments (Edit Form) ====================
    (function() {
        var descDropzone = document.getElementById('descDropzone');
        var descFileInput = document.getElementById('descFileInput');
        var descAttachmentList = document.getElementById('descAttachmentList');
        var editTaskDesc = document.getElementById('editTaskDesc');
        var descHashInput = document.getElementById('descHash');
        if (!descDropzone || !editTaskDesc || !descHashInput) return;

        var descAttachmentCounter = 0;

        function descEscapeHtml(text) {
            var d = document.createElement('div');
            d.textContent = text;
            return d.innerHTML;
        }
        function descFormatSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        }
        function descHandleFiles(files) {
            for (var i = 0; i < files.length; i++) descUploadFile(files[i]);
        }
        function descUploadFile(file) {
            var id = 'desc_file_' + (++descAttachmentCounter);
            var isImage = file.type.indexOf('image/') === 0;
            var item = document.createElement('div');
            item.className = 'attachment-item';
            item.id = id;
            item.innerHTML = '<div class="file-icon ' + (isImage ? 'image' : 'document') + '">' + (isImage ? '🖼' : '📄') + '</div>' +
                '<div class="file-info"><div class="file-name">' + descEscapeHtml(file.name) + '</div><div class="file-size">Uploading...</div></div>' +
                '<span class="remove-file" onclick="document.getElementById(\'' + id + '\').remove()">✕</span>';
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
            descAttachmentList.appendChild(item);

            var formData = new FormData();
            formData.append('file', file);
            formData.append('hash', descHashInput.value);
            formData.append('action', 'upload_temp_attachment');

            fetch('ajax_responder.php', { method: 'POST', body: formData })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        item.querySelector('.file-size').textContent = descFormatSize(file.size);
                        if (editTaskDesc.value && !editTaskDesc.value.endsWith('\n')) editTaskDesc.value += '\n';
                        editTaskDesc.value += '[' + data.safe_name + ']';
                    } else {
                        item.querySelector('.file-size').textContent = 'Upload failed';
                        item.style.background = '#fed7d7';
                    }
                })
                .catch(function() {
                    item.querySelector('.file-size').textContent = 'Upload failed';
                    item.style.background = '#fed7d7';
                });
        }

        descFileInput.addEventListener('change', function(e) {
            descHandleFiles(e.target.files);
            this.value = '';
        });
        descDropzone.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });
        descDropzone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
        });
        descDropzone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            descHandleFiles(e.dataTransfer.files);
        });
        descDropzone.addEventListener('click', function(e) {
            if (e.target.tagName !== 'LABEL' && e.target.tagName !== 'A') descFileInput.click();
        });

        document.addEventListener('paste', function(e) {
            if (!editTaskDesc || document.activeElement !== editTaskDesc) return;
            var items = e.clipboardData && e.clipboardData.items;
            if (!items) return;
            var files = [];
            for (var i = 0; i < items.length; i++) {
                if (items[i].kind === 'file') {
                    var file = items[i].getAsFile();
                    if (file) files.push(file);
                }
            }
            if (files.length > 0) {
                e.preventDefault();
                descHandleFiles(files);
            }
        });

        editTaskDesc.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && (e.key === 'u' || e.key === 'U')) {
                e.preventDefault();
                descFileInput.click();
            }
        });
    })();

    // Copy task link to clipboard
    function copyTaskLink() {
        const taskUrl = '<?php echo "https://" . $_SERVER['HTTP_HOST'] . "/edit_task.php?task_id=" . $task_id; ?>';
        navigator.clipboard.writeText(taskUrl).then(function() {
            const btn = document.querySelector('.copy-link-btn');
            btn.classList.add('copied');
            btn.innerHTML = '✓';
            setTimeout(function() {
                btn.classList.remove('copied');
                btn.innerHTML = '📋';
            }, 2000);
        }).catch(function(err) {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = taskUrl;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            
            const btn = document.querySelector('.copy-link-btn');
            btn.classList.add('copied');
            btn.innerHTML = '✓';
            setTimeout(function() {
                btn.classList.remove('copied');
                btn.innerHTML = '📋';
            }, 2000);
        });
    }

    // Copy task name to clipboard
    function copyTaskName() {
        <?php 
        $task_title_json = json_encode(isset($task['task_title']) ? $task['task_title'] : '');
        if ($task_title_json === false) $task_title_json = '""';
        ?>
        const taskName = <?php echo $task_title_json; ?>;
        navigator.clipboard.writeText(taskName).then(function() {
            const btn = document.querySelector('.copy-name-btn');
            btn.classList.add('copied');
            btn.innerHTML = '✓';
            setTimeout(function() {
                btn.classList.remove('copied');
                btn.innerHTML = '📋';
            }, 2000);
        }).catch(function(err) {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = taskName;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            
            const btn = document.querySelector('.copy-name-btn');
            btn.classList.add('copied');
            btn.innerHTML = '✓';
            setTimeout(function() {
                btn.classList.remove('copied');
                btn.innerHTML = '📋';
            }, 2000);
        });
    }

    // Quote original task description
    function quoteOriginalTask() {
        if (!quoteText(originalTaskDescription)) {
            alert('No task description to quote.');
        }
    }
    
    // Quote last message
    function quoteLastMessage() {
        if (!quoteText(lastMessageContent)) {
            alert('No message to quote.');
        }
    }

    // Copy entire message to clipboard
    function copyMessageToClipboard() {
        var textarea = document.getElementById('messageTextarea');
        if (!textarea) return;
        var text = textarea.value || '';
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                var btn = document.getElementById('copyMessageBtn');
                if (btn) {
                    var orig = btn.textContent;
                    btn.textContent = 'Copied!';
                    setTimeout(function() { btn.textContent = orig; }, 1500);
                }
            }).catch(function() {
                fallbackCopyToClipboard(text);
            });
        } else {
            fallbackCopyToClipboard(text);
        }
    }
    function fallbackCopyToClipboard(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
            var btn = document.getElementById('copyMessageBtn');
            if (btn) {
                var orig = btn.textContent;
                btn.textContent = 'Copied!';
                setTimeout(function() { btn.textContent = orig; }, 1500);
            }
        } catch (e) {}
        document.body.removeChild(ta);
    }

    // ==================== Message Attachments ====================
    var messageAttachments = [];
    var messageAttachmentId = 0;
    var messageHashValue = '<?php echo $hash; ?>';

    var messageDropzone = document.getElementById('messageDropzone');
    var messageFileInput = document.getElementById('messageFileInput');
    var messageAttachmentList = document.getElementById('messageAttachmentList');
    var messageTextarea = document.getElementById('messageTextarea');

    // File input change
    messageFileInput?.addEventListener('change', function(e) {
        handleMessageFiles(e.target.files);
        this.value = '';
    });

    // Drag and drop on dropzone
    messageDropzone?.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('drag-over');
    });

    messageDropzone?.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.classList.remove('drag-over');
    });

    messageDropzone?.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('drag-over');
        handleMessageFiles(e.dataTransfer.files);
    });

    // Click on dropzone to trigger file input
    messageDropzone?.addEventListener('click', function(e) {
        if (e.target.tagName !== 'LABEL' && e.target.tagName !== 'INPUT') {
            messageFileInput.click();
        }
    });

    // Drag and drop on textarea
    messageTextarea?.addEventListener('dragover', function(e) {
        e.preventDefault();
        messageDropzone.classList.add('drag-over');
    });

    messageTextarea?.addEventListener('dragleave', function(e) {
        e.preventDefault();
        messageDropzone.classList.remove('drag-over');
    });

    messageTextarea?.addEventListener('drop', function(e) {
        if (e.dataTransfer.files.length > 0) {
            e.preventDefault();
            messageDropzone.classList.remove('drag-over');
            handleMessageFiles(e.dataTransfer.files);
        }
    });

    messageTextarea?.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && (e.key === 'u' || e.key === 'U')) {
            e.preventDefault();
            if (messageFileInput) messageFileInput.click();
        }
    });

    // Paste on textarea
    messageTextarea?.addEventListener('paste', function(e) {
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
            handleMessageFiles(files);
        }
    });

    function handleMessageFiles(files) {
        for (var i = 0; i < files.length; i++) {
            uploadMessageFile(files[i]);
        }
    }

    function uploadMessageFile(file) {
        var id = ++messageAttachmentId;
        var isImage = file.type.startsWith('image/');
        
        // Create list item
        var item = document.createElement('div');
        item.className = 'message-attachment-item';
        item.id = 'msg-attachment-' + id;
        
        var iconClass = isImage ? 'image' : '';
        var iconText = isImage ? '🖼️' : '📄';
        
        item.innerHTML = 
            '<div class="file-icon ' + iconClass + '">' + iconText + '</div>' +
            '<div class="file-info">' +
                '<div class="file-name">' + escapeHtml(file.name) + '</div>' +
                '<div class="file-size">Uploading...</div>' +
            '</div>' +
            '<span class="remove-file" onclick="removeMessageAttachment(' + id + ')">✕</span>';
        
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
        
        messageAttachmentList.appendChild(item);
        
        // Upload file
        var formData = new FormData();
        formData.append('file', file);
        formData.append('hash', messageHashValue);
        formData.append('action', 'upload_temp_attachment');
        
        fetch('ajax_responder.php', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                messageAttachments.push({
                    id: id,
                    name: data.safe_name,
                    original_name: file.name
                });
                
                var sizeEl = item.querySelector('.file-size');
                sizeEl.textContent = formatMessageFileSize(file.size);
                
                // Add reference to message
                if (messageTextarea.value && !messageTextarea.value.endsWith('\n')) {
                    messageTextarea.value += '\n';
                }
                messageTextarea.value += '[' + data.safe_name + ']';
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

    function removeMessageAttachment(id) {
        var item = document.getElementById('msg-attachment-' + id);
        if (item) item.remove();
        
        // Find and remove the attachment reference from the message
        var att = messageAttachments.find(function(a) { return a.id === id; });
        if (att) {
            var ref = '[' + att.name + ']';
            messageTextarea.value = messageTextarea.value.replace(ref, '').replace(/\n\n+/g, '\n\n').trim();
        }
        
        messageAttachments = messageAttachments.filter(function(a) { return a.id !== id; });
    }

    function formatMessageFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ==================== Lightbox Gallery ====================
    var lightboxImages = [];
    var lightboxIndex = 0;

    function openLightbox(element) {
        var gallery = element.dataset.gallery;
        var images = document.querySelectorAll('.gallery-image[data-gallery="' + gallery + '"]');
        
        lightboxImages = [];
        images.forEach(function(img, index) {
            lightboxImages.push({
                src: img.href,
                name: img.dataset.name
            });
            if (img === element) {
                lightboxIndex = index;
            }
        });

        showLightbox();
    }

    function showLightbox() {
        var overlay = document.getElementById('lightboxOverlay');
        var image = document.getElementById('lightboxImage');
        var title = document.getElementById('lightboxTitle');
        var counter = document.getElementById('lightboxCounter');
        var thumbsContainer = document.getElementById('lightboxThumbs');

        if (lightboxImages.length === 0) return;

        // Update main image
        image.src = lightboxImages[lightboxIndex].src;
        title.textContent = lightboxImages[lightboxIndex].name;
        counter.textContent = (lightboxIndex + 1) + ' / ' + lightboxImages.length;

        // Update nav buttons
        document.getElementById('lightboxPrev').disabled = lightboxIndex === 0;
        document.getElementById('lightboxNext').disabled = lightboxIndex === lightboxImages.length - 1;

        // Update thumbnails
        thumbsContainer.innerHTML = '';
        lightboxImages.forEach(function(img, index) {
            var thumb = document.createElement('img');
            thumb.src = img.src;
            thumb.className = 'lightbox-thumb' + (index === lightboxIndex ? ' active' : '');
            thumb.onclick = function() {
                lightboxIndex = index;
                showLightbox();
            };
            thumbsContainer.appendChild(thumb);
        });

        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
        document.getElementById('lightboxOverlay').classList.remove('active');
        document.body.style.overflow = '';
    }

    function lightboxPrev() {
        if (lightboxIndex > 0) {
            lightboxIndex--;
            showLightbox();
        }
    }

    function lightboxNext() {
        if (lightboxIndex < lightboxImages.length - 1) {
            lightboxIndex++;
            showLightbox();
        }
    }

    // Keyboard navigation for lightbox
    document.addEventListener('keydown', function(e) {
        var overlay = document.getElementById('lightboxOverlay');
        if (!overlay.classList.contains('active')) return;

        switch(e.key) {
            case 'Escape':
                closeLightbox();
                break;
            case 'ArrowLeft':
                lightboxPrev();
                break;
            case 'ArrowRight':
                lightboxNext();
                break;
        }
    });

    // Close lightbox on overlay click
    document.getElementById('lightboxOverlay')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeLightbox();
        }
    });
    </script>

    <!-- Lightbox Gallery -->
    <div class="lightbox-overlay" id="lightboxOverlay">
        <div class="lightbox-header">
            <span class="lightbox-title" id="lightboxTitle"></span>
            <span class="lightbox-counter" id="lightboxCounter"></span>
            <button class="lightbox-close" onclick="closeLightbox()">&times;</button>
        </div>
        <div class="lightbox-main">
            <button class="lightbox-nav prev" id="lightboxPrev" onclick="lightboxPrev()">&#10094;</button>
            <div class="lightbox-image-container">
                <img src="" alt="" class="lightbox-image" id="lightboxImage">
            </div>
            <button class="lightbox-nav next" id="lightboxNext" onclick="lightboxNext()">&#10095;</button>
        </div>
        <div class="lightbox-thumbnails" id="lightboxThumbs"></div>
    </div>

    <!-- Close Task Confirmation Modal -->
    <div class="confirm-modal-overlay" id="closeConfirmModal">
        <div class="confirm-modal">
            <div class="confirm-modal-icon">✓</div>
            <h3>Close Task</h3>
            <p>Are you sure you want to close this task?</p>
            <p class="confirm-modal-task-title"><?php echo htmlspecialchars($task['task_title']); ?></p>
            <div class="confirm-modal-actions">
                <button type="button" class="btn btn-secondary" onclick="hideCloseConfirm()">Cancel</button>
                <a href="edit_task.php?task_id=<?php echo $task_id; ?>&action=close" class="btn btn-primary" id="confirmCloseBtn">Yes, Close Task</a>
            </div>
        </div>
    </div>

    <script>
    function showCloseConfirm() {
        document.getElementById('closeConfirmModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function hideCloseConfirm() {
        document.getElementById('closeConfirmModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    // Close modal on overlay click
    document.getElementById('closeConfirmModal').addEventListener('click', function(e) {
        if (e.target === this) {
            hideCloseConfirm();
        }
    });

    // Close modal on Escape key, confirm on Enter
    document.addEventListener('keydown', function(e) {
        if (document.getElementById('closeConfirmModal').classList.contains('active')) {
            if (e.key === 'Escape') {
                hideCloseConfirm();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('confirmCloseBtn').click();
            }
        }
    });
    </script>
</body>
</html>
