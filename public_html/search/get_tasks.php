<?php

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");

$operation  = GetParam("operation");
$keyword    = GetParam("search_keyword");
$project_id = GetParam("project_id");
$user       = GetParam("user");
$domain     = GetParam("domain");
$sort_order = GetParam("sort_order");
$tasks          = array();
$project_tasks  = array();
$project_title  = $project_id;
$user_id        = 0;
$limit          = 1000;
$sort_direction = GetParam("sort_direction");
if ($sort_direction) $_SESSION["session_sort_direction"] = $sort_direction;
if (!$sort_direction) $sort_direction = "DESC";
$sort_direction = ($sort_direction == "DESC") ? "DESC" : "ASC";

$sorters = array(
    "t.is_closed ASC,t.creation_date" => "Date Created",
    "t.is_closed"                     => "Is Closed",
    "user_name"                       => "Assigned To",
    "t.task_title"                    => "Task Name"
);

$cur_sort_order = "t.is_closed ASC,t.creation_date";
if (isset($sorters[$sort_order])) $cur_sort_order = $sort_order;

if ($project_id || strlen($keyword) || $user || $domain) {
    if ($project_id) {
        if (!is_numeric($project_id)) {
            $sql = "SELECT project_id FROM projects WHERE project_title='".addslashes($project_id)."'";
            $db->query($sql);
            $project_id = $db->next_record() ? $db->f("project_id") : "";
        }
    }

    if ($user) {
        if (!is_numeric($user)) {
            $first_name = "";
            $last_name  = $user;
            $parts = explode(" ",$user);
            if(is_array($parts)) {
                $first_name = $parts[0];
                $last_name =  (sizeof($parts) > 1) ? $parts[1] : "";
            };
            $sql = "SELECT user_id FROM users WHERE first_name='".addslashes($first_name)."'";
            $sql.= " AND last_name='".addslashes($last_name)."'";
            $db->query($sql);
            $user_id = $db->next_record() ? $db->f("user_id") : 0;
        }
    }

    // 1. find projects
    if ($project_id && is_numeric($project_id)) {
        $sql = "SELECT task_id FROM tasks WHERE project_id = $project_id LIMIT $limit";
        $db->query($sql);
        while($db->next_record()) {
            $project_tasks[] = $db->f("task_id");
        }
    }

    $domain_tasks = array();
    if ($domain) {
        $sql = "SELECT task_id FROM tasks WHERE task_domain_url='" . addslashes($domain). "'";
        $db->query($sql);
        while($db->next_record()) {
            $domain_tasks[] = $db->f("task_id");
        }
    }

    // 2. search in all tasks for task_title, task_description
    $sql = "SELECT task_id FROM tasks WHERE 1=1 ";
    if (strlen($keyword))       $sql.= " AND (task_title LIKE '%$keyword%' OR task_desc LIKE '%$keyword%') ";
    if (sizeof($project_tasks)) $sql.= " AND task_id IN (". join(",",$project_tasks). ")";
    if ($user_id)               $sql.= " AND responsible_user_id=".$user_id;
    if ($domain)                $sql.= " AND task_domain_url='" . addslashes($domain). "'";
    $db->query($sql);
    while($db->next_record()) {
        $tasks[] = $db->f("task_id");
    }

    // 3. search in messages
    if ($keyword) {
        $sql = "SELECT identity_id FROM messages WHERE 1=1 ";
        if (strlen($keyword)) {
            $sql .= " AND MATCH(message) AGAINST('\"$keyword\"' IN NATURAL LANGUAGE MODE)";
        }
        if (sizeof($project_tasks)) $sql.= " AND identity_id IN (". join(",",$project_tasks). ")";
        if (sizeof($domain_tasks))  $sql.= " AND identity_id IN (". join(",",$domain_tasks). ")";
        $db->query($sql);
        while($db->next_record()) {
            $tasks[] = $db->f("identity_id");
        }
    }
}
$tasks = array_unique($tasks);

// Build search terms display
$search_terms = array();
if ($project_title && $project_title != $project_id) $search_terms[] = $project_title;
if ($user) $search_terms[] = $user;
if ($domain) $search_terms[] = $domain;
if ($keyword) $search_terms[] = $keyword;
$search_display = htmlspecialchars(implode(', ', $search_terms));

if (!sizeof($tasks)) {
?>
<div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 10px; padding: 20px; color: #856404;">
    <strong>No Results Found</strong>
    <p style="margin: 6px 0 0; font-size: 0.9rem;">No tasks matched your search<?php if ($search_display) echo ' for <strong>' . $search_display . '</strong>'; ?>.</p>
</div>
<?php
    exit;
}
?>
<style>
    .results-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 16px;
    }
    .results-info {
        font-size: 0.9rem;
        color: #4a5568;
    }
    .results-info strong {
        color: #2d3748;
    }
    .results-count {
        display: inline-flex;
        align-items: center;
        background: #eef2ff;
        color: #667eea;
        font-weight: 600;
        font-size: 0.8rem;
        padding: 2px 8px;
        border-radius: 10px;
        margin-left: 4px;
    }
    .sort-controls {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .sort-controls label {
        font-size: 0.8rem;
        color: #718096;
        font-weight: 500;
        white-space: nowrap;
    }
    .sort-select {
        padding: 6px 10px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        font-size: 0.82rem;
        font-family: inherit;
        background: #fff;
        color: #2d3748;
        cursor: pointer;
    }
    .sort-select:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 2px rgba(102,126,234,0.15);
    }
    .sort-dir-btn {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 6px 10px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        font-size: 0.8rem;
        font-family: inherit;
        background: #fff;
        color: #4a5568;
        cursor: pointer;
        transition: all 0.15s;
        white-space: nowrap;
    }
    .sort-dir-btn:hover {
        border-color: #667eea;
        color: #667eea;
        background: #f7f8ff;
    }
    .sort-dir-btn svg {
        flex-shrink: 0;
    }
</style>

<div class="results-header">
    <div class="results-info">
        <?php if ($search_display): ?>
        Results for <strong><?php echo $search_display; ?></strong>
        <?php else: ?>
        All tasks
        <?php endif; ?>
        <span class="results-count"><?php echo number_format(sizeof($tasks)); ?></span>
    </div>
    <div class="sort-controls">
        <label>Sort by</label>
        <select class="sort-select" id="sortSelect" onchange="handleSortChange(this.value)">
            <?php foreach($sorters as $sort_field => $sort_title): ?>
            <option value="<?php echo htmlspecialchars($sort_field); ?>"<?php echo ($sort_field == $cur_sort_order) ? ' selected' : ''; ?>><?php echo htmlspecialchars($sort_title); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="button" class="sort-dir-btn" id="sortDirBtn" onclick="handleSortDirChange()" title="Toggle sort direction">
            <?php if ($sort_direction == 'DESC'): ?>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg> Newest
            <?php else: ?>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"/></svg> Oldest
            <?php endif; ?>
        </button>
    </div>
</div>

<script>
function handleSortChange(val) {
    globalSortOrder = val;
    get_tasks();
}
function handleSortDirChange() {
    globalSortDirection = (globalSortDirection === 'DESC') ? 'ASC' : 'DESC';
    get_tasks();
}
</script>

<table>
<colgroup>
    <col class="col-id">
    <col class="col-project">
    <col class="col-task">
    <col class="col-assigned">
    <col class="col-status">
    <col class="col-pct">
    <col class="col-hrs">
    <col class="col-created">
    <col class="col-closed">
</colgroup>
<thead>
    <tr>
        <th>ID</th>
        <th>Project</th>
        <th>Task Name</th>
        <th>Assigned To</th>
        <th>Status</th>
        <th>%</th>
        <th>Hrs</th>
        <th>Created</th>
        <th>Closed</th>
    </tr>
</thead>
<tbody>
<?php
    $sql = " SELECT p.project_title, t.task_id, t.completion, t.task_title, CONCAT(u.first_name, ' ', u.last_name) as user_name, ";
    $sql .= " t.priority_id, lts.status_desc, t.estimated_hours, t.actual_hours, t.creation_date, t.planed_date, ";
    $sql .= " t.is_closed, u.user_id, p.project_id, u.user_id, t.actual_hours, ";
    $sql .= " DATE_FORMAT(t.creation_date, '%d %b %Y') AS c_date, DATE_FORMAT(t.planed_date, '%d %b %Y') AS plan_date ";
    $sql .= " FROM tasks t, users u, projects p, lookup_tasks_statuses lts ";
    $sql .= " WHERE t.responsible_user_id=u.user_id AND t.project_id=p.project_id AND t.task_status_id=lts.status_id ";
    $sql .= " AND task_id IN (". join(",",$tasks). ")";
    $sql .= " ORDER BY ".$cur_sort_order." ".$sort_direction;
    $sql .= " LIMIT $limit";

    $db->query($sql);
    while ($db->next_record()) {
        $task_id       = $db->f("task_id");
        $pid           = $db->f("project_id");
        $task_title    = htmlspecialchars($db->f("task_title"));
        $proj_title    = htmlspecialchars($db->f("project_title"));
        $status        = htmlspecialchars($db->f("status_desc"));
        $actual_hours  = $db->f("actual_hours");
        $completion    = $db->f("completion");
        $uname         = htmlspecialchars($db->f("user_name"));
        $creation_date = $db->f("c_date");
        $is_closed     = $db->f("is_closed");
        $uid           = $db->f("user_id");
        $hours         = to_hours($db->f("actual_hours"), true);
        $comp_display  = $completion ? $completion . '%' : '';
        $opacity       = $is_closed ? " style='opacity:0.5'" : "";
        $closed_label  = $is_closed ? '<span style="color:#e53e3e;font-size:0.8em;">yes</span>' : '<span style="color:#a0aec0;font-size:0.8em;">no</span>';

        echo "<tr data-task-id='{$task_id}'{$opacity}>";
        echo "<td>{$task_id}</td>";
        echo "<td class='td-project' title='{$proj_title}'><a target='_blank' href='../edit_project.php?project_id={$pid}'>{$proj_title}</a></td>";
        echo "<td class='td-task' title='{$task_title}'><a target='_blank' href='../edit_task.php?task_id={$task_id}'>{$task_title}</a></td>";
        echo "<td><a target='_blank' href='../report_people.php?report_user_id={$uid}'>{$uname}</a></td>";
        echo "<td>{$status}</td>";
        echo "<td>{$comp_display}</td>";
        echo "<td>{$hours}</td>";
        echo "<td>{$creation_date}</td>";
        echo "<td>{$closed_label}</td>";
        echo "</tr>";
    }
?>
</tbody>
</table>
