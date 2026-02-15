<?php
ini_set('memory_limit', '32M');
include("./includes/date_functions.php");
include("./includes/common.php");

CheckSecurity(1);

$year_selected = GetParam("year_selected");
$month_selected = GetParam("month_selected");
$person_selected = GetParam("person_selected");
$submit = GetParam("submit");
if (!$year_selected) $year_selected = date("Y");
if (!$month_selected) $month_selected = date("m");
$team = GetParam("team");

$as="";$vs="";$ys="";
switch (strtolower($team)) {
    case "all":    $sqlteam=""; $as="selected"; break;
    case "viart":  $sqlteam=" AND u.is_viart=1 "; $vs="selected"; break;
    case "yoonoo": $sqlteam=" AND u.is_viart=0 "; $ys="selected"; break;
    default:       $sqlteam=" AND u.is_viart=1 "; $vs="selected"; $team="viart";
}

// Load people for filter
$people = array();
$sql = "SELECT user_id, is_viart, CONCAT(first_name,' ', last_name) as person FROM users u WHERE is_deleted IS NULL ORDER BY person";
$db->query($sql, __FILE__, __LINE__);
while ($db->next_record()) {
    $people[] = array(
        'user_id' => $db->f("user_id"),
        'is_viart' => $db->f("is_viart"),
        'person' => $db->f("person")
    );
}

// Number of days in month
$n_days = date("t", mktime(0, 0, 0, $month_selected, 1, $year_selected));

// Load data if submitted
$results = array();
$hours_ar = array();
if (strlen($submit)) {
    $sql  = " SELECT CONCAT(u.first_name, ' ', u.last_name) AS user_name, u.user_id, t.task_title, tr.task_id, ";
    $sql .= " SUM(tr.spent_hours) AS sum_hours, DAYOFMONTH(tr.started_date) AS day_of_month ";
    $sql .= " FROM tasks t, time_report tr, users u ";
    $sql .= " WHERE tr.task_id=t.task_id AND tr.user_id=u.user_id ".$sqlteam;
    if ($year_selected) $sql .= " AND DATE_FORMAT(tr.started_date, '%Y')='".addslashes($year_selected)."' ";
    if ($month_selected) $sql .= " AND DATE_FORMAT(tr.started_date, '%m')='".addslashes($month_selected)."' ";
    if ($person_selected) $sql .= " AND tr.user_id=".intval($person_selected)." ";
    $sql .= " GROUP BY u.user_id, t.task_id, day_of_month ";
    $sql .= " ORDER BY user_name, started_time";
    $db->query($sql, __FILE__, __LINE__);

    while ($db->next_record()) {
        $user_id = $db->f("user_id");
        $task_id = $db->f("task_id");
        $day_of_month = $db->f("day_of_month");
        $sum_hours = $db->f("sum_hours");
        $hours_ar[$user_id][$task_id][$day_of_month] = $sum_hours;
    }

    $sql  = " SELECT CONCAT(u.first_name, ' ', u.last_name) AS user_name, u.user_id, t.task_title, tr.task_id, ";
    $sql .= " SUM(tr.spent_hours) AS sum_hours ";
    $sql .= " FROM tasks t, time_report tr, users u ";
    $sql .= " WHERE tr.task_id=t.task_id AND tr.user_id=u.user_id ".$sqlteam;
    if ($year_selected) $sql .= " AND DATE_FORMAT(tr.started_date, '%Y')='".addslashes($year_selected)."' ";
    if ($month_selected) $sql .= " AND DATE_FORMAT(tr.started_date, '%m')='".addslashes($month_selected)."' ";
    if ($person_selected) $sql .= " AND tr.user_id=".intval($person_selected)." ";
    $sql .= " GROUP BY u.user_id, t.task_id ";
    $sql .= " ORDER BY user_name, started_time";
    $db->query($sql, __FILE__, __LINE__);

    while ($db->next_record()) {
        $results[] = array(
            'user_name' => $db->f("user_name"),
            'user_id' => $db->f("user_id"),
            'task_title' => $db->f("task_title"),
            'task_id' => $db->f("task_id"),
            'sum_hours' => $db->f("sum_hours")
        );
    }
}

$month_name = date("F", mktime(0, 0, 0, $month_selected, 1, $year_selected));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks Report - Sayu Monitor</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            color: #2d3748;
            font-size: 14px;
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 24px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2d3748;
        }

        .page-subtitle {
            color: #718096;
            font-size: 0.85rem;
            margin-top: 4px;
        }

        /* Filter Card */
        .filter-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            padding: 20px;
            margin-bottom: 20px;
        }

        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: flex-end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .filter-group label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-group select,
        .filter-group input[type="text"] {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.85rem;
            font-family: inherit;
            background: #fff;
            min-width: 160px;
            transition: border-color 0.2s;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 8px;
            align-items: flex-end;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
            font-family: inherit;
            transition: all 0.2s;
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

        /* Legend */
        .legend {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 20px;
        }

        .legend-title {
            font-size: 0.8rem;
            font-weight: 600;
            color: #718096;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.78rem;
            color: #4a5568;
        }

        .legend-color {
            width: 14px;
            height: 14px;
            border-radius: 3px;
            flex-shrink: 0;
        }

        /* Results Card */
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .card-header {
            padding: 14px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            font-weight: 600;
            font-size: 1rem;
            color: #2d3748;
        }

        .scroll-table {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            padding: 8px 6px;
            text-align: center;
            font-weight: 600;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #718096;
            background: #f8f9fa;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .data-table th.th-task {
            text-align: left;
            padding-left: 16px;
            min-width: 200px;
        }

        .data-table th.th-day {
            width: 22px;
            min-width: 22px;
            font-size: 0.65rem;
            padding: 6px 2px;
        }

        .data-table td {
            padding: 6px;
            border-bottom: 1px solid #f1f5f9;
            text-align: center;
            font-size: 0.8rem;
        }

        .data-table td.td-task {
            text-align: left;
            padding-left: 16px;
        }

        .data-table td.td-task a {
            color: #667eea;
            text-decoration: none;
        }

        .data-table td.td-task a:hover {
            text-decoration: underline;
        }

        .user-header td {
            background: #f0f4ff;
            font-weight: 700;
            font-size: 0.85rem;
            color: #2d3748;
            padding: 10px 16px;
            text-align: left;
            border-bottom: 2px solid #667eea;
        }

        /* Day cells */
        .day-empty { background: #fff; }
        .day-weekend { background: #f7fafc; }
        .day-s1 { background: #bee3f8; }
        .day-s2 { background: #63b3ed; }
        .day-s3 { background: #3182ce; }
        .day-s4 { background: #2c5282; }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #718096;
        }

        @media (max-width: 768px) {
            .container { padding: 12px; }
            .filter-form { flex-direction: column; }
            .filter-group select { min-width: 100%; }
            .page-title { font-size: 1.2rem; }
        }
    </style>
</head>
<body>
    <?php include("./includes/modern_header.php"); ?>

    <div class="container">
        <div class="page-header">
            <div>
                <h1 class="page-title">Tasks Report</h1>
                <p class="page-subtitle"><?php echo $month_name . ' ' . $year_selected; ?> &mdash; Hours per task per day</p>
            </div>
        </div>

        <div class="filter-card">
            <form name="frmFilter" action="tasks_report.php" method="GET">
                <div class="filter-form">
                    <div class="filter-group">
                        <label>Year</label>
                        <select name="year_selected">
                            <?php echo GetYearOptions(2004, date("Y"), $year_selected); ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Month</label>
                        <select name="month_selected">
                            <?php echo GetMonthOptions($month_selected); ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Team</label>
                        <select name="team" id="teamSelect" onchange="filterPeople()">
                            <option value="all" <?php echo $as; ?>>All teams</option>
                            <option value="viart" <?php echo $vs; ?>>Sayu Ukraine</option>
                            <option value="yoonoo" <?php echo $ys; ?>>Sayu UK</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Person</label>
                        <select name="person_selected" id="personSelect">
                            <option value="">All people</option>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" name="submit" value="1" class="btn btn-primary">Filter</button>
                        <button type="button" class="btn btn-secondary" onclick="window.location='tasks_report.php'">Clear</button>
                    </div>
                </div>
            </form>
        </div>

        <?php if (strlen($submit)): ?>

        <div class="legend">
            <span class="legend-title">Legend:</span>
            <span class="legend-item"><span class="legend-color day-s1"></span> &lt; 2h</span>
            <span class="legend-item"><span class="legend-color day-s2"></span> 2-4h</span>
            <span class="legend-item"><span class="legend-color day-s3"></span> 4-6h</span>
            <span class="legend-item"><span class="legend-color day-s4"></span> 6h+</span>
            <span class="legend-item"><span class="legend-color day-weekend"></span> Weekend</span>
        </div>

        <div class="card">
            <div class="card-header">
                <span class="card-title">Tasks Report &mdash; <?php echo $month_name . ' ' . htmlspecialchars($year_selected); ?></span>
            </div>
            <?php if (!empty($results)): ?>
            <div class="scroll-table">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="th-task">Task</th>
                            <th>Time</th>
                            <?php for ($d = 1; $d <= $n_days; $d++): ?>
                            <th class="th-day"><?php echo $d; ?></th>
                            <?php endfor; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $cur_user = "";
                        foreach ($results as $row):
                            $user_name = $row['user_name'];
                            $user_id = $row['user_id'];
                            $task_id = $row['task_id'];
                            $task_title = $row['task_title'];
                            $sum_hours = $row['sum_hours'];

                            if ($cur_user !== $user_name):
                                $cur_user = $user_name;
                        ?>
                        <tr class="user-header">
                            <td colspan="<?php echo $n_days + 2; ?>"><?php echo htmlspecialchars($user_name); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td class="td-task"><a href="edit_task.php?task_id=<?php echo $task_id; ?>"><?php echo htmlspecialchars($task_title); ?></a></td>
                            <td><?php echo Hours2HoursMins($sum_hours); ?></td>
                            <?php for ($d = 1; $d <= $n_days; $d++):
                                $h = isset($hours_ar[$user_id][$task_id][$d]) ? $hours_ar[$user_id][$task_id][$d] : 0;
                                if (!$h) {
                                    $dow = date("w", mktime(0, 0, 0, $month_selected, $d, $year_selected));
                                    $cls = ($dow == 0 || $dow == 6) ? 'day-weekend' : 'day-empty';
                                } elseif ($h <= 2) {
                                    $cls = 'day-s1';
                                } elseif ($h <= 4) {
                                    $cls = 'day-s2';
                                } elseif ($h <= 6) {
                                    $cls = 'day-s3';
                                } else {
                                    $cls = 'day-s4';
                                }
                            ?>
                            <td class="<?php echo $cls; ?>"></td>
                            <?php endfor; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <p>No data for this period</p>
            </div>
            <?php endif; ?>
        </div>

        <?php endif; ?>
    </div>

    <script>
    // People data for dynamic filtering
    var allPeople = <?php echo json_encode($people); ?>;
    var selectedPerson = <?php echo json_encode($person_selected ? $person_selected : ""); ?>;

    function filterPeople() {
        var teamVal = document.getElementById('teamSelect').value;
        var sel = document.getElementById('personSelect');
        sel.innerHTML = '<option value="">All people</option>';

        allPeople.forEach(function(p) {
            var show = false;
            if (teamVal === 'all') show = true;
            else if (teamVal === 'viart' && p.is_viart == 1) show = true;
            else if (teamVal === 'yoonoo' && p.is_viart == 0) show = true;

            if (show) {
                var opt = document.createElement('option');
                opt.value = p.user_id;
                opt.textContent = p.person;
                if (p.user_id == selectedPerson) opt.selected = true;
                sel.appendChild(opt);
            }
        });
    }

    filterPeople();
    </script>
</body>
</html>
