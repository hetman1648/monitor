<?php
include("./includes/common.php");

if (getsessionparam("privilege_id") == 9) {
    header("Location: index.php");
    exit;
}

CheckSecurity(1);

$action = GetParam("action");

if ($action == "recalculation") {
    CountTimeProjects();
    header("Location: users.php");
    exit;
}

$user_name = GetSessionParam("UserName");
$is_deleted = GetParam("is_deleted") ? "" : "AND is_deleted IS NULL";
$show_deleted = GetParam("is_deleted") ? true : false;

// Get users
$users = array();
$sql = "SELECT *, CONCAT(first_name,' ',last_name) as user_name 
        FROM users AS u, lookup_users_privileges AS p 
        WHERE u.privilege_id=p.privilege_id $is_deleted 
        ORDER BY user_name";
$db->query($sql);
while ($db->next_record()) {
    $users[] = array(
        'user_id' => $db->f("user_id"),
        'user_name' => $db->f("user_name"),
        'privilege_desc' => $db->f("privilege_desc"),
        'is_deleted' => $db->f("is_deleted")
    );
}

// Get project assignments count
$number_assigned = array();
$sql = "SELECT project_id, COUNT(*) as c FROM users_projects GROUP BY project_id";
$db->query($sql);
while ($db->next_record()) {
    $number_assigned[$db->f("project_id")] = $db->f("c");
}

// Get features count
$features_assigned = array();
$sql = "SELECT project_id, COUNT(*) as c FROM project_features GROUP BY project_id";
$db->query($sql);
while ($db->next_record()) {
    $features_assigned[$db->f("project_id")] = $db->f("c");
}

// Get projects with active tasks in last 24 months
$active_projects = array();
$sql = "SELECT DISTINCT project_id FROM tasks 
        WHERE is_closed = 0 
        AND creation_date >= DATE_SUB(NOW(), INTERVAL 24 MONTH)";
$db->query($sql);
while ($db->next_record()) {
    $active_projects[$db->f("project_id")] = true;
}

// Get projects with hierarchy
$projects = array();
$sql = "SELECT parent.project_id,
               parent.project_title,
               parent.project_status_id,
               parent.is_closed,
               child.project_id AS c_id,
               child.project_title AS c_title,
               child.project_status_id AS c_status_id,
               child.is_closed AS c_closed,
               ps.color
        FROM projects AS parent
        LEFT JOIN projects AS child ON (parent.project_id=child.parent_project_id)
        LEFT JOIN projects_statuses AS ps ON (child.project_status_id=ps.project_status_id)
        WHERE parent.parent_project_id IS NULL
        ORDER BY parent.is_closed, parent.project_title, child.is_closed, child.project_title";
$db->query($sql);
$lasttitle = "";
while ($db->next_record()) {
    $title = $db->f("project_title");
    if ($title) {
        if ($lasttitle != $title) {
            $id = $db->f("project_id");
            $projects[] = array(
                'id' => $id,
                'title' => $title,
                'is_closed' => $db->f("is_closed"),
                'is_child' => false,
                'color' => '',
                'people' => isset($number_assigned[$id]) ? $number_assigned[$id] : 0,
                'features' => isset($features_assigned[$id]) ? $features_assigned[$id] : 0,
                'has_active_tasks' => isset($active_projects[$id])
            );
        }
        if ($db->f("c_id")) {
            $cid = $db->f("c_id");
            $projects[] = array(
                'id' => $cid,
                'title' => $db->f("c_title"),
                'is_closed' => $db->f("c_closed"),
                'is_child' => true,
                'color' => $db->f("color"),
                'people' => isset($number_assigned[$cid]) ? $number_assigned[$cid] : 0,
                'features' => isset($features_assigned[$cid]) ? $features_assigned[$cid] : 0,
                'has_active_tasks' => isset($active_projects[$cid])
            );
        }
        $lasttitle = $title;
    }
}

// Get privileges
$privileges = array();
$sql = "SELECT * FROM lookup_users_privileges ORDER BY privilege_desc";
$db->query($sql);
while ($db->next_record()) {
    $privileges[] = array(
        'privilege_id' => $db->f("privilege_id"),
        'privilege_desc' => $db->f("privilege_desc")
    );
}

// Check permission for recalculation
$perm_user_profile = false;
$sql = "SELECT PERM_USER_PROFILE FROM lookup_users_privileges WHERE privilege_id = " . GetSessionParam("privilege_id");
$db->query($sql);
if ($db->next_record()) {
    $perm_user_profile = $db->f("PERM_USER_PROFILE");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance - Sayu Monitor</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
            color: #2d3748;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 24px;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 1.8rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 8px;
        }

        .page-header p {
            color: #718096;
            font-size: 0.95rem;
        }

        .grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
        }

        @media (max-width: 1200px) {
            .grid-3 {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 800px) {
            .grid-3 {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .card-header {
            padding: 16px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header.light {
            background: #f8f9fa;
            color: #2d3748;
            border-bottom: 1px solid #e2e8f0;
        }

        .card-title {
            font-weight: 600;
            font-size: 1rem;
        }

        .card-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
        }

        .card-header.light .card-badge {
            background: #667eea;
            color: white;
        }

        .card-body {
            padding: 0;
            max-height: 500px;
            overflow-y: auto;
        }

        .card-footer {
            padding: 16px 20px;
            background: #f8f9fa;
            border-top: 1px solid #e2e8f0;
        }

        .list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 20px;
            border-bottom: 1px solid #e2e8f0;
            transition: background 0.2s;
        }

        .list-item:hover {
            background: #f7fafc;
        }

        .list-item:last-child {
            border-bottom: none;
        }

        .list-item a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }

        .list-item a:hover {
            text-decoration: underline;
        }

        .list-item-meta {
            color: #718096;
            font-size: 0.85rem;
        }

        .project-item {
            display: grid;
            grid-template-columns: 1fr 60px 60px;
            gap: 10px;
            align-items: center;
            padding: 10px 20px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.9rem;
        }

        .project-item:hover {
            background: #f7fafc;
        }

        .project-item.child {
            padding-left: 36px;
        }

        .project-item.closed {
            opacity: 0.5;
        }

        .project-item a {
            color: #667eea;
            text-decoration: none;
        }

        .project-item a:hover {
            text-decoration: underline;
        }

        .project-item .count {
            text-align: center;
            color: #718096;
        }

        .project-item .count a {
            color: #667eea;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 16px;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: white;
            color: #4a5568;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #f7fafc;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }

        .actions-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .deleted-badge {
            background: #fed7d7;
            color: #c53030;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            margin-left: 8px;
        }

        .table-header {
            display: grid;
            grid-template-columns: 1fr 60px 60px;
            gap: 10px;
            padding: 10px 20px;
            background: #f8f9fa;
            border-bottom: 2px solid #e2e8f0;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #4a5568;
        }

        .table-header span:not(:first-child) {
            text-align: center;
        }

        .filter-controls {
            padding: 12px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .filter-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.85rem;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .filter-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .filter-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: #4a5568;
            cursor: pointer;
        }

        .filter-checkbox input {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: #667eea;
        }

        .project-item.hidden {
            display: none;
        }

        .no-results {
            padding: 20px;
            text-align: center;
            color: #718096;
            font-size: 0.9rem;
            display: none;
        }

        .no-results.visible {
            display: block;
        }

        /* Dark mode overrides */
        html.dark-mode .page-header h1 { color: #e2e8f0; }
        html.dark-mode .page-header p { color: #a0aec0; }
        html.dark-mode .filter-controls {
            background: #1c2333;
            border-bottom-color: #2d333b;
        }
        html.dark-mode .filter-input {
            background: #161b22 !important;
            color: #e2e8f0 !important;
            border-color: #2d333b !important;
        }
        html.dark-mode .filter-input::placeholder { color: #6b7280 !important; }
        html.dark-mode .filter-input:focus { border-color: #667eea !important; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2); }
        html.dark-mode .filter-checkbox { color: #cbd5e0; }
        html.dark-mode .filter-checkbox input[type="checkbox"] { accent-color: #667eea; }
        html.dark-mode .table-header {
            background: #1c2333;
            border-bottom-color: #2d333b;
            color: #a0aec0;
        }
        html.dark-mode .card-badge {
            background: rgba(255, 255, 255, 0.15);
            color: #e2e8f0;
        }
        html.dark-mode .card-footer {
            background: #1c2333;
            border-top-color: #2d333b;
        }
        html.dark-mode .list-item {
            border-bottom-color: #2d333b;
        }
        html.dark-mode .list-item:hover { background: #1c2333; }
        html.dark-mode .list-item a { color: #90cdf4; }
        html.dark-mode .list-item-meta { color: #8b949e; }
        html.dark-mode .project-item {
            border-bottom-color: #2d333b;
        }
        html.dark-mode .project-item:hover { background: #1c2333; }
        html.dark-mode .project-item a { color: #90cdf4; }
        html.dark-mode .project-item a[style*="color"] {
            filter: brightness(1.4) saturate(1.2);
        }
        html.dark-mode .project-item .count { color: #8b949e; }
        html.dark-mode .project-item .count a { color: #90cdf4; }
        html.dark-mode .no-results { color: #8b949e; }
        html.dark-mode .deleted-badge {
            background: rgba(220, 53, 69, 0.4);
            color: #fca5a5;
        }
    </style>
</head>
<body>
    <?php include("./includes/modern_header.php"); ?>

    <div class="container">
        <div class="page-header">
            <h1>Maintenance</h1>
            <p>Manage users, projects, and privilege groups</p>
        </div>

        <div class="grid-3">
            <!-- Users Card -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title">Users</span>
                    <span class="card-badge"><?php echo count($users); ?></span>
                </div>
                <div class="card-body">
                    <?php foreach ($users as $user): ?>
                    <div class="list-item">
                        <div>
                            <a href="user_profile.php?user_id=<?php echo $user['user_id']; ?>">
                                <?php echo htmlspecialchars($user['user_name']); ?>
                            </a>
                            <?php if ($user['is_deleted']): ?>
                            <span class="deleted-badge">Deleted</span>
                            <?php endif; ?>
                        </div>
                        <span class="list-item-meta"><?php echo htmlspecialchars($user['privilege_desc']); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="card-footer">
                    <div class="actions-row">
                        <a href="user_profile.php" class="btn btn-primary btn-sm">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            Add User
                        </a>
                        <?php if ($show_deleted): ?>
                        <a href="users.php" class="btn btn-secondary btn-sm">Hide Deleted</a>
                        <?php else: ?>
                        <a href="users.php?is_deleted=1" class="btn btn-secondary btn-sm">Show Deleted</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Projects Card -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title">Projects</span>
                    <span class="card-badge" id="projectCount"><?php echo count($projects); ?></span>
                </div>
                <div class="filter-controls">
                    <input type="text" class="filter-input" id="projectFilter" placeholder="Filter by project name...">
                    <label class="filter-checkbox">
                        <input type="checkbox" id="activeOnlyFilter" checked>
                        Show Active only (tasks within 24 months)
                    </label>
                </div>
                <div class="card-body" id="projectsBody">
                    <div class="table-header">
                        <span>Project</span>
                        <span>People</span>
                        <span>Features</span>
                    </div>
                    <?php foreach ($projects as $project): ?>
                    <div class="project-item <?php echo $project['is_child'] ? 'child' : ''; ?> <?php echo $project['is_closed'] ? 'closed' : ''; ?>" 
                         data-name="<?php echo htmlspecialchars(strtolower($project['title'])); ?>"
                         data-active="<?php echo $project['has_active_tasks'] ? '1' : '0'; ?>">
                        <a href="edit_project.php?project_id=<?php echo $project['id']; ?>&return_page=users.php" 
                           style="<?php echo $project['color'] ? 'color: ' . $project['color'] : ''; ?>">
                            <?php echo htmlspecialchars($project['title']); ?>
                        </a>
                        <span class="count"><?php echo $project['people']; ?></span>
                        <span class="count">
                            <?php if ($project['features'] > 0): ?>
                            <a href="edit_project_features.php?project_id=<?php echo $project['id']; ?>&return_page=users.php">
                                <?php echo $project['features']; ?>
                            </a>
                            <?php else: ?>
                            0
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                    <div class="no-results" id="noResults">No projects match your filter</div>
                </div>
                <div class="card-footer">
                    <div class="actions-row">
                        <a href="edit_project.php" class="btn btn-primary btn-sm">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            Add Project
                        </a>
                        <?php if ($perm_user_profile): ?>
                        <a href="users.php?action=recalculation" class="btn btn-secondary btn-sm">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="23 4 23 10 17 10"></polyline>
                                <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                            </svg>
                            Recalculate Hours
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Privileges Card -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title">Privilege Groups</span>
                    <span class="card-badge"><?php echo count($privileges); ?></span>
                </div>
                <div class="card-body">
                    <?php foreach ($privileges as $priv): ?>
                    <div class="list-item">
                        <a href="privilege.php?pid=<?php echo $priv['privilege_id']; ?>">
                            <?php echo htmlspecialchars($priv['privilege_desc']); ?>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="card-footer">
                    <a href="privilege.php" class="btn btn-primary btn-sm">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Add Privilege Group
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const projectFilter = document.getElementById('projectFilter');
        const activeOnlyFilter = document.getElementById('activeOnlyFilter');
        const projectsBody = document.getElementById('projectsBody');
        const projectCount = document.getElementById('projectCount');
        const noResults = document.getElementById('noResults');
        const projectItems = projectsBody.querySelectorAll('.project-item');

        function filterProjects() {
            const searchText = projectFilter.value.toLowerCase().trim();
            const activeOnly = activeOnlyFilter.checked;
            let visibleCount = 0;

            projectItems.forEach(function(item) {
                const name = item.getAttribute('data-name');
                const isActive = item.getAttribute('data-active') === '1';
                
                const matchesSearch = !searchText || name.includes(searchText);
                const matchesActive = !activeOnly || isActive;

                if (matchesSearch && matchesActive) {
                    item.classList.remove('hidden');
                    visibleCount++;
                } else {
                    item.classList.add('hidden');
                }
            });

            projectCount.textContent = visibleCount;
            
            if (visibleCount === 0) {
                noResults.classList.add('visible');
            } else {
                noResults.classList.remove('visible');
            }
        }

        projectFilter.addEventListener('input', filterProjects);
        activeOnlyFilter.addEventListener('change', filterProjects);

        // Apply initial filter (active only is checked by default)
        filterProjects();
    });
    </script>
</body>
</html>
