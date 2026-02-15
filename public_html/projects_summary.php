<?php
include("./includes/date_functions.php");
include("./includes/common.php");

CheckSecurity(1);

$filter_parent_project_id = (int) GetParam("filter_parent_project_id");

// Handle POST — save statuses
$post_vars = get_post_vars();
if (sizeof($post_vars)) {
    foreach ($post_vars as $key => $value) {
        if (strpos($key, "project_status_id_") === 0) {
            $project_id = (int) substr($key, 18);
            $sql = "UPDATE projects SET project_status_id=" . ToSQL($value, "integer", false) . " WHERE project_id=" . ToSQL($project_id, "number");
            $db->query($sql);
        }
    }
    $return_page = "projects_summary.php";
    if ($filter_parent_project_id) {
        $return_page .= "?filter_parent_project_id=" . $filter_parent_project_id;
    }
    header("Location: " . $return_page);
    exit;
}

// Load project statuses (for dropdown selects per sub-project)
$project_statuses = array();
$sql = " SELECT parent_project_id, project_status_id, status_desc, color ";
$sql .= " FROM projects_statuses ";
if ($filter_parent_project_id) {
    $sql .= " WHERE parent_project_id=" . ToSQL($filter_parent_project_id, "integer");
}
$sql .= " ORDER BY parent_project_id, status_order, status_desc, project_status_id ";
$db->query($sql);
while ($db->next_record()) {
    $parent_project_id = $db->f("parent_project_id");
    if (!isset($project_statuses[$parent_project_id])) {
        $project_statuses[$parent_project_id] = array();
        $project_statuses[$parent_project_id][0] = array("key" => "0", "value" => "-- select status --", "color" => "#718096");
    }
    $project_statuses[$parent_project_id][] = array(
        "key" => $db->f("project_status_id"),
        "value" => $db->f("status_desc"),
        "color" => $db->f("color")
    );
}

// Load parent projects for filter
$parent_projects = array();
$sql = "SELECT project_id, project_title FROM projects WHERE parent_project_id IS NULL AND is_closed=0 ORDER BY project_title";
$db->query($sql);
while ($db->next_record()) {
    $parent_projects[] = array("id" => $db->f("project_id"), "title" => $db->f("project_title"));
}

// Load project data
$sql = " SELECT parent.project_id,
                parent.project_title,
                parent.project_status_id,
                parent.is_closed,
                child.project_id AS c_id,
                child.project_title AS c_title,
                child.project_status_id AS c_status_id,
                child.is_closed AS c_closed,
                COUNT(DISTINCT(t.task_id)) AS tasks,
                ROUND(SUM(tr.spent_hours),2) AS hours,
                COUNT(DISTINCT(tr.user_id)) AS people,
                IF(child.is_closed=0 AND ps_exist.project_status_id IS NOT NULL, 1, 0) AS allow_select,
                IF(child.is_closed=0, child.project_status_id, 0) AS child_status_id,
                IF(child.is_closed=0, ps.status_desc, 'Closed') AS child_status_desc,
                IF(child.is_closed=0, IF(ps.color!='' AND ps.color IS NOT NULL, ps.color, '#2d3748'), '#a0aec0') AS child_color
        FROM projects AS parent
              INNER JOIN projects AS child ON (parent.project_id=child.parent_project_id)
              LEFT JOIN tasks t ON (child.project_id = t.project_id)
              LEFT JOIN time_report tr ON (t.task_id = tr.task_id)
              LEFT JOIN projects_statuses AS ps ON (ps.project_status_id=child.project_status_id)
              LEFT JOIN projects_statuses AS ps_exist ON (ps_exist.parent_project_id=parent.project_id)
        WHERE parent.parent_project_id IS NULL AND parent.is_closed=0 ";
if ($filter_parent_project_id > 0) {
    $sql .= " AND parent.project_id=" . ToSQL($filter_parent_project_id, "integer");
}
$sql .= " GROUP BY parent.project_id, child.project_id
          ORDER BY parent.is_closed,
                   parent.project_title,
                   child.is_closed,
                   child.project_title ";
$db->query($sql);

// Group results by parent project
$grouped = array();
$total_tasks = 0;
$total_people = 0;
$total_hours = 0;
while ($db->next_record()) {
    $pid = $db->f("project_id");
    if (!isset($grouped[$pid])) {
        $grouped[$pid] = array(
            "project_title" => $db->f("project_title"),
            "children" => array()
        );
    }
    $tasks = (int) $db->f("tasks");
    $people = (int) $db->f("people");
    $hours = (float) $db->f("hours");
    $total_tasks += $tasks;
    $total_people += $people;
    $total_hours += $hours;

    $grouped[$pid]["children"][] = array(
        "c_id" => $db->f("c_id"),
        "c_title" => $db->f("c_title"),
        "c_closed" => $db->f("c_closed"),
        "child_status_id" => $db->f("child_status_id"),
        "child_status_desc" => $db->f("child_status_desc"),
        "child_color" => $db->f("child_color"),
        "tasks" => $tasks,
        "people" => $people,
        "hours" => $hours,
        "allow_select" => $db->f("allow_select"),
        "parent_project_id" => $pid
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects Summary - Sayu Monitor</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; color: #2d3748; font-size: 14px; }
        .container { max-width: 1200px; margin: 0 auto; padding: 24px; }
        .page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; flex-wrap: wrap; gap: 16px; }
        .page-title { font-size: 1.5rem; font-weight: 700; color: #2d3748; }
        .page-subtitle { color: #718096; font-size: 0.85rem; margin-top: 4px; }

        .filter-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); padding: 20px; margin-bottom: 20px; }
        .filter-form { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 4px; }
        .filter-group label { font-size: 0.75rem; font-weight: 600; color: #718096; text-transform: uppercase; letter-spacing: 0.5px; }
        .filter-group select { padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.85rem; font-family: inherit; background: #fff; min-width: 220px; }
        .filter-group select:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }

        .btn { padding: 8px 16px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; cursor: pointer; border: none; font-family: inherit; transition: all 0.2s; }
        .btn-primary { background: #667eea; color: #fff; }
        .btn-primary:hover { background: #5a67d8; }
        .btn-secondary { background: #e2e8f0; color: #4a5568; }
        .btn-secondary:hover { background: #cbd5e0; }
        .btn-save { background: linear-gradient(135deg, #667eea, #764ba2); color: #fff; padding: 8px 20px; }
        .btn-save:hover { opacity: 0.9; }

        .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); overflow: hidden; margin-bottom: 20px; }
        .card-header { padding: 14px 20px; background: #f8f9fa; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .card-title { font-weight: 600; font-size: 1rem; color: #2d3748; }
        .card-subtitle { font-size: 0.82rem; color: #718096; margin-top: 2px; }
        .card-actions { display: flex; gap: 8px; }

        .scroll-table { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { padding: 8px 12px; text-align: center; font-weight: 600; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.3px; color: #718096; background: #f8f9fa; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
        .data-table th:first-child { text-align: left; padding-left: 20px; }
        .data-table td { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; font-size: 0.85rem; text-align: center; white-space: nowrap; }
        .data-table td:first-child { text-align: left; padding-left: 20px; }
        .data-table td a { color: #667eea; text-decoration: none; }
        .data-table td a:hover { text-decoration: underline; }
        .data-table tr:hover td { background: #f8f9fc; }

        .parent-row td { background: #f0f4ff; font-weight: 700; border-bottom: 1px solid #e2e8f0; padding-top: 12px; padding-bottom: 12px; }
        .parent-row td:first-child { padding-left: 20px; font-size: 0.9rem; color: #2d3748; }
        .child-row td:first-child { padding-left: 36px; }
        .closed-row td { opacity: 0.55; }

        .status-badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 0.78rem; font-weight: 500; border: 1px solid; }
        .status-select { padding: 5px 8px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.82rem; font-family: inherit; background: #fff; cursor: pointer; }
        .status-select:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }

        .edit-link { color: #667eea !important; font-size: 0.8rem; font-weight: 500; }
        .edit-link:hover { color: #5a67d8 !important; }

        .total-row td { font-weight: 700; background: #f0f4ff; border-top: 2px solid #667eea; }
        .empty-state { text-align: center; padding: 40px; color: #718096; }

        .stats-bar { display: flex; gap: 24px; padding: 0 20px 16px; flex-wrap: wrap; }
        .stat-item { display: flex; align-items: center; gap: 8px; }
        .stat-label { font-size: 0.75rem; color: #718096; text-transform: uppercase; font-weight: 600; }
        .stat-value { font-size: 1.1rem; font-weight: 700; color: #2d3748; }

        @media (max-width: 768px) {
            .container { padding: 12px; }
            .filter-form { flex-direction: column; }
            .filter-group select { min-width: 100%; }
            .page-title { font-size: 1.2rem; }
            .child-row td:first-child { padding-left: 24px; }
        }
    </style>
</head>
<body>
    <?php include("./includes/modern_header.php"); ?>

    <div class="container">
        <div class="page-header">
            <div>
                <h1 class="page-title">Projects Summary</h1>
                <p class="page-subtitle">Overview of all projects, sub-projects and their statuses</p>
            </div>
        </div>

        <div class="filter-card">
            <form name="Filter" action="projects_summary.php" method="GET">
                <div class="filter-form">
                    <div class="filter-group">
                        <label>Parent Project</label>
                        <select name="filter_parent_project_id" onchange="this.form.submit()">
                            <option value="0">-- All Projects --</option>
                            <?php foreach ($parent_projects as $pp): ?>
                            <option value="<?php echo $pp['id']; ?>"<?php echo ($pp['id'] == $filter_parent_project_id) ? ' selected' : ''; ?>><?php echo htmlspecialchars($pp['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>

        <?php if (!empty($grouped)): ?>
        <form name="StatusForm" action="projects_summary.php" method="POST">
            <input type="hidden" name="filter_parent_project_id" value="<?php echo $filter_parent_project_id; ?>">

            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Projects &amp; Sub-Projects</div>
                        <div class="card-subtitle"><?php echo count($grouped); ?> parent project<?php echo count($grouped) != 1 ? 's' : ''; ?></div>
                    </div>
                    <div class="card-actions">
                        <button type="submit" class="btn btn-save">Save Statuses</button>
                    </div>
                </div>

                <div class="stats-bar" style="padding-top: 14px;">
                    <div class="stat-item">
                        <span class="stat-label">Total Tasks</span>
                        <span class="stat-value"><?php echo number_format($total_tasks); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Total Hours</span>
                        <span class="stat-value"><?php echo number_format($total_hours, 1); ?></span>
                    </div>
                </div>

                <div class="scroll-table">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 35%;">Project</th>
                                <th style="width: 25%;">Status</th>
                                <th>Tasks</th>
                                <th>People</th>
                                <th>Hours</th>
                                <th style="width: 60px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($grouped as $pid => $parent): ?>
                            <tr class="parent-row">
                                <td colspan="6"><?php echo htmlspecialchars($parent['project_title']); ?></td>
                            </tr>
                            <?php foreach ($parent['children'] as $child): ?>
                            <tr class="child-row<?php echo $child['c_closed'] ? ' closed-row' : ''; ?>">
                                <td>
                                    <a href="search/?project_selected=<?php echo $child['c_id']; ?>&amp;person_selected=&amp;keyword=&amp;closed=on&amp;submit=+Filter+" style="color: <?php echo htmlspecialchars($child['child_color']); ?>;">
                                        <?php echo htmlspecialchars($child['c_title']); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if ($child['allow_select'] && isset($project_statuses[$child['parent_project_id']])): ?>
                                    <select name="project_status_id_<?php echo $child['c_id']; ?>" class="status-select">
                                        <?php foreach ($project_statuses[$child['parent_project_id']] as $ps): ?>
                                        <option value="<?php echo htmlspecialchars($ps['key']); ?>" style="color: <?php echo htmlspecialchars($ps['color']); ?>;"<?php echo ($ps['key'] == $child['child_status_id']) ? ' selected' : ''; ?>><?php echo htmlspecialchars($ps['value']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php else: ?>
                                    <span class="status-badge" style="color: <?php echo htmlspecialchars($child['child_color']); ?>; border-color: <?php echo htmlspecialchars($child['child_color']); ?>30;">
                                        <?php echo htmlspecialchars($child['child_status_desc']); ?>
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $child['tasks']; ?></td>
                                <td><?php echo $child['people']; ?></td>
                                <td><?php echo $child['hours'] ? number_format($child['hours'], 1) : '0'; ?></td>
                                <td><a href="edit_project.php?project_id=<?php echo $child['c_id']; ?>" class="edit-link">Edit</a></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div style="padding: 12px 20px; text-align: right;">
                    <button type="submit" class="btn btn-save">Save Statuses</button>
                </div>
            </div>
        </form>
        <?php else: ?>
        <div class="card">
            <div class="empty-state">No projects found matching the selected filter.</div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
