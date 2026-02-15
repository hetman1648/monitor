<?php
include("./includes/common.php");
include("./includes/date_functions.php");
if (!defined('INTEGER')) define('INTEGER', 'integer');

if (getsessionparam("privilege_id") == 9) {
    header("Location: index.php");
    exit;
}

$user_id = GetSessionParam("UserID");
$user_name = GetSessionParam("UserName");

// SECOND DATABASE OBJECT
$db2 = new DB_Sql;
$db2->Database = DATABASE_NAME;
$db2->User     = DATABASE_USER;
$db2->Password = DATABASE_PASSWORD;
$db2->Host     = DATABASE_HOST;

// THIRD DATABASE OBJECT
$db3 = new DB_Sql;
$db3->Database = DATABASE_NAME;
$db3->User     = DATABASE_USER;
$db3->Password = DATABASE_PASSWORD;
$db3->Host     = DATABASE_HOST;

CheckSecurity(1);

// FILTER PARSE
$search = "";
$show_user_id = GetParam("show_user_id");
$year_selected = GetParam("year_selected");
$filter_start_date = GetParam("start_date");
$filter_end_date = GetParam("end_date");
$userid = GetParam("iduser");
$action = GetParam("action");

if (!$filter_start_date) {
    $filter_start_date = date("Y-m-d");
}

if ($userid && !$show_user_id) {
    $show_user_id = $userid;
} elseif (!$userid && $show_user_id) {
    $userid = $show_user_id;
}

if ($filter_start_date == 'xxx') $filter_start_date = date('Y-m-00');
if (strlen($show_user_id) > 0 && $show_user_id != 0) $filter_start_date = "";

if ($year_selected) {
    $search .= " AND DATE_FORMAT(d.start_date, '%Y') = '" . $year_selected . "'";
}
if ($filter_start_date) {
    $search .= " AND d.start_date >= '" . $filter_start_date . "'";
}
if ($filter_end_date) {
    $search .= " AND d.start_date <= '" . $filter_end_date . "'";
}

$today_year = date("Y");
$current_year = $year_selected ? $year_selected : $today_year;

// Load National Holidays
$national_holidays = array();
$sql = "SELECT * FROM national_holidays WHERE YEAR(holiday_date)=" . ($year_selected ? ToSQL($year_selected, "NUMBER") : "YEAR(NOW())") . " ORDER BY holiday_date";
$db->query($sql);
while ($db->next_record()) {
    $national_holidays[] = array(
        'title' => $db->f("holiday_title"),
        'date' => $db->f("holiday_date")
    );
}

// Load English Holidays
$english_holidays = array();
$sql = "SELECT * FROM english_holidays WHERE YEAR(holiday_date)=" . ($year_selected ? ToSQL($year_selected, "NUMBER") : "YEAR(NOW())") . " ORDER BY holiday_date";
$db->query($sql);
while ($db->next_record()) {
    $english_holidays[] = array(
        'title' => $db->f("holiday_title"),
        'date' => $db->f("holiday_date")
    );
}

// Can edit holidays?
$can_edit_holidays = ($user_id == 3 || $user_id == 15);

// Load User Stats
$user_stats = array();
$total_vac = 0;
$total_pd = 0;
$total_ill = 0;

$sql = "SELECT user_id, concat(first_name,' ',last_name) as fullname FROM users WHERE is_viart = 1 AND is_deleted IS NULL ORDER BY first_name";
$db->query($sql);
while ($db->next_record()) {
    $uid = $db->f("user_id");
    $sql2 = "SELECT total_days, reason_id FROM days_off d WHERE is_paid = 0 AND user_id = " . $uid . $search;
    $db2->query($sql2);
    
    $vacation_i = 0;
    $not_paid_i = 0;
    $illness_i = 0;
    $total = 0;
    
    while ($db2->next_record()) {
        switch ($db2->f("reason_id")) {
            case 1:
                $vacation_i += $db2->f("total_days");
                $total += $db2->f("total_days");
                $total_vac += $db2->f("total_days");
                break;
            case 2:
                $not_paid_i += $db2->f("total_days");
                $total += $db2->f("total_days");
                $total_pd += $db2->f("total_days");
                break;
            case 3:
                $illness_i += $db2->f("total_days");
                $total += $db2->f("total_days");
                $total_ill += $db2->f("total_days");
                break;
        }
    }
    
    if ($total > 0) {
        $user_stats[] = array(
            'name' => $db->f("fullname"),
            'vacation' => $vacation_i,
            'not_paid' => $not_paid_i,
            'illness' => $illness_i,
            'total' => $total
        );
    }
}

// Load Holiday Summary
$holiday_summary = array();
$sql = "SELECT start_date, user_id, CONCAT(first_name, ' ', last_name) AS user_name FROM users WHERE is_viart=1 AND is_deleted IS NULL ORDER BY first_name";
$db->query($sql);
while ($db->next_record()) {
    $uid = $db->f("user_id");
    
    // Used holidays total
    $sql2 = "SELECT SUM(total_days) AS used_holidays FROM days_off WHERE reason_id = 1 AND is_paid=0 AND user_id=" . ToSQL($uid, INTEGER);
    $db2->query($sql2);
    $db2->next_record();
    $used_holidays = $db2->f("used_holidays") ?: 0;
    
    // Used holidays this year
    $sql2 = "SELECT SUM(total_days) AS used_holidays FROM days_off WHERE reason_id = 1 AND is_paid=0 AND user_id=" . ToSQL($uid, INTEGER);
    $sql2 .= " AND DATE_FORMAT(start_date, '%Y')='$today_year'";
    $db2->query($sql2);
    $db2->next_record();
    $used_holidays_year = $db2->f("used_holidays") ?: 0;
    
    // Total holidays allocated
    $sql2 = "SELECT SUM(days_number) AS total_holidays FROM holidays WHERE user_id=" . ToSQL($uid, INTEGER);
    $db2->query($sql2);
    $db2->next_record();
    $total_holidays = floor($db2->f("total_holidays") ?: 0);
    
    // Total holidays this year
    $sql2 = "SELECT SUM(days_number) AS total_holidays FROM holidays WHERE user_id=" . ToSQL($uid, INTEGER);
    $sql2 .= " AND DATE_FORMAT(date_added, '%Y')='$today_year'";
    $db2->query($sql2);
    $db2->next_record();
    $total_holidays_year = floor($db2->f("total_holidays") ?: 0);
    
    $avail = $total_holidays - $used_holidays;
    
    $holiday_summary[] = array(
        'user_id' => $uid,
        'name' => $db->f("user_name"),
        'total' => $total_holidays,
        'used' => $used_holidays,
        'available' => $avail,
        'total_year' => $total_holidays_year,
        'used_year' => $used_holidays_year,
        'start_date' => $db->f("start_date")
    );
}

// Load Days Off (time off requests)
$days_off = array();
if ($search || $action) {
    $sql2 = "SELECT d.*, u.first_name, u.last_name, r.reason_name 
             FROM days_off d 
             INNER JOIN users u ON u.user_id = d.user_id 
             LEFT JOIN reasons r ON r.reason_id = d.reason_id 
             WHERE d.is_paid = 0" . $search;
    if ($show_user_id != 0) {
        $sql2 .= " AND d.user_id=" . ToSQL($show_user_id, INTEGER);
    }
    $sql2 .= " ORDER BY d.start_date DESC";
    $db2->query($sql2);
    
    while ($db2->next_record()) {
        $is_approved = $db2->f("is_approved");
        $is_declined = $db2->f("is_declined");
        
        if ($is_declined == 1) {
            $status = 'declined';
        } elseif ($is_approved == 1) {
            $status = 'approved';
        } else {
            $status = 'pending';
        }
        
        $days_off[] = array(
            'period_id' => $db2->f("period_id"),
            'user_name' => $db2->f("first_name") . ' ' . $db2->f("last_name"),
            'title' => $db2->f("period_title") ?: 'No Title',
            'reason' => $db2->f("reason_name"),
            'start_date' => $db2->f("start_date"),
            'end_date' => $db2->f("end_date"),
            'total_days' => $db2->f("total_days"),
            'status' => $status
        );
    }
}

// Load Paid Days
$paid_days = array();
if ($search || $action) {
    $sql2 = "SELECT d.*, u.first_name, u.last_name, r.reason_name 
             FROM days_off d 
             INNER JOIN users u ON u.user_id = d.user_id 
             LEFT JOIN reasons r ON r.reason_id = d.reason_id 
             WHERE d.is_paid = 1" . $search;
    if ($show_user_id) {
        $sql2 .= " AND d.user_id=" . ToSQL($show_user_id, INTEGER);
    }
    $sql2 .= " ORDER BY d.start_date DESC";
    $db2->query($sql2);
    
    while ($db2->next_record()) {
        $is_approved = $db2->f("is_approved");
        $is_declined = $db2->f("is_declined");
        
        if ($is_declined == 1) {
            $status = 'declined';
        } elseif ($is_approved == 1) {
            $status = 'approved';
        } else {
            $status = 'pending';
        }
        
        $paid_days[] = array(
            'period_id' => $db2->f("period_id"),
            'user_name' => $db2->f("first_name") . ' ' . $db2->f("last_name"),
            'title' => $db2->f("period_title") ?: 'No Title',
            'reason' => $db2->f("reason_name"),
            'start_date' => $db2->f("start_date"),
            'end_date' => $db2->f("end_date"),
            'total_days' => $db2->f("total_days"),
            'status' => $status
        );
    }
}

// Year options
$year_options = array();
for ($y = date("Y"); $y >= 2004; $y--) {
    $year_options[] = $y;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Time Off Overview - Sayu Monitor</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);
            min-height: 100vh;
            color: #1a202c;
            padding: 0;
        }

        .container {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1a202c;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .back-link:hover {
            color: #5a67d8;
        }

        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: #fff;
        }

        .card-header .edit-link {
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .card-header .edit-link:hover {
            color: #fff;
        }

        .card-body {
            padding: 20px;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        @media (max-width: 1200px) {
            .grid-3 {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .grid-2, .grid-3 {
                grid-template-columns: 1fr;
            }
        }

        .filter-card {
            background: #fff;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 24px;
        }

        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: flex-end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .filter-group label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #4a5568;
        }

        .filter-input {
            padding: 10px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
            font-family: inherit;
            min-width: 150px;
        }

        .filter-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: none;
            font-family: inherit;
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
            background: #f7fafc;
            color: #4a5568;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #edf2f7;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        .data-table th {
            background: #f8fafc;
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            color: #4a5568;
            border-bottom: 2px solid #e2e8f0;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .data-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            color: #4a5568;
        }

        .data-table tr:hover {
            background: #f8fafc;
        }

        .data-table a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }

        .data-table a:hover {
            color: #5a67d8;
            text-decoration: underline;
        }

        .data-table .totals-row {
            background: #f8fafc;
            font-weight: 600;
        }

        .data-table .totals-row td {
            color: #e53e3e;
            border-top: 2px solid #e2e8f0;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-success {
            background: #c6f6d5;
            color: #276749;
        }

        .badge-danger {
            background: #fed7d7;
            color: #c53030;
        }

        .badge-pending {
            background: #e0e7ff;
            color: #3730a3;
        }

        .empty-state {
            text-align: center;
            padding: 30px;
            color: #a0aec0;
        }

        .holiday-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .holiday-item:last-child {
            border-bottom: none;
        }

        .holiday-name {
            font-weight: 500;
            color: #2d3748;
        }

        .holiday-date {
            color: #718096;
            font-size: 0.85rem;
        }

        .stats-value {
            font-weight: 700;
            color: #1a202c;
        }

        .stats-value.highlight {
            color: #667eea;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .compact-table {
            font-size: 0.8rem;
        }

        .compact-table th, .compact-table td {
            padding: 8px 12px;
        }

        .text-right {
            text-align: right !important;
        }

        .text-center {
            text-align: center;
        }

        .scroll-table {
            overflow-x: auto;
        }

        /* Dark mode */
        html.dark-mode .page-header h1 { color: #e2e8f0; }
        html.dark-mode .filter-card {
            background: #161b22;
            box-shadow: 0 4px 20px rgba(0,0,0,0.5);
            border: 1px solid #2d333b;
        }
        html.dark-mode .filter-group label { color: #cbd5e0; }
        html.dark-mode .filter-input {
            background: #1c2333 !important;
            color: #e2e8f0 !important;
            border-color: #2d333b !important;
        }
        html.dark-mode .filter-input:focus {
            border-color: #667eea !important;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }
        html.dark-mode .btn-secondary {
            background: #1c2333;
            color: #e2e8f0;
            border-color: #2d333b;
        }
        html.dark-mode .btn-secondary:hover { background: #2d333b; }
        html.dark-mode .card {
            background: #161b22;
            box-shadow: 0 4px 20px rgba(0,0,0,0.5);
            border: 1px solid #2d333b;
        }
        html.dark-mode .card-header {
            border-bottom-color: rgba(255,255,255,0.15);
        }
        html.dark-mode .card-body { color: #e2e8f0; }
        html.dark-mode .data-table th {
            background: #1c2333;
            color: #a0aec0;
            border-bottom-color: #2d333b;
        }
        html.dark-mode .data-table td {
            color: #e2e8f0;
            border-bottom-color: #2d333b;
        }
        html.dark-mode .data-table tr:hover { background: #1c2333; }
        html.dark-mode .data-table .totals-row {
            background: #1c2333;
            border-top-color: #2d333b;
        }
        html.dark-mode .data-table .totals-row td {
            color: #feb2b2;
        }
        html.dark-mode .data-table a { color: #90cdf4; }
        html.dark-mode .data-table a:hover { color: #63b3ed; }
        html.dark-mode .holiday-item { border-bottom-color: #2d333b; }
        html.dark-mode .holiday-name { color: #e2e8f0; }
        html.dark-mode .holiday-date { color: #a0aec0; }
        html.dark-mode .empty-state { color: #8b949e; }
        html.dark-mode .badge-success {
            background: rgba(34, 84, 61, 0.5);
            color: #9ae6b4;
        }
        html.dark-mode .badge-danger {
            background: rgba(130, 39, 39, 0.5);
            color: #feb2b2;
        }
        html.dark-mode .badge-pending {
            background: rgba(67, 56, 202, 0.3);
            color: #c3dafe;
        }
        html.dark-mode .section-title { color: #e2e8f0; }
        html.dark-mode .stats-value { color: #e2e8f0; }
        html.dark-mode .stats-value.highlight { color: #90cdf4; }
        html.dark-mode .back-link { color: #90cdf4; }
        html.dark-mode .back-link:hover { color: #63b3ed; }
    </style>
</head>
<body>
    <?php include("./includes/modern_header.php"); ?>

    <div class="container">
        <div class="page-header">
            <h1>Time Off Overview</h1>
        </div>

        <!-- Filter -->
        <div class="filter-card">
            <form name="frmFilter" method="POST" action="view_vacations.php" class="filter-form">
                <input type="hidden" name="iduser" value="<?php echo htmlspecialchars($userid); ?>">
                
                <div class="filter-group">
                    <label>Year</label>
                    <select name="year_selected" class="filter-input">
                        <option value="">All Years</option>
                        <?php foreach ($year_options as $y): ?>
                        <option value="<?php echo $y; ?>" <?php echo ($year_selected == $y) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date" class="filter-input" value="<?php echo htmlspecialchars($filter_start_date); ?>">
                </div>

                <div class="filter-group">
                    <label>End Date</label>
                    <input type="date" name="end_date" class="filter-input" value="<?php echo htmlspecialchars($filter_end_date); ?>">
                </div>

                <button type="submit" name="action" value="Filter" class="btn btn-primary">Apply Filter</button>
                <button type="button" class="btn btn-secondary" onclick="clearFilter()">Clear</button>
            </form>
        </div>

        <!-- Top Section: Holidays and Stats -->
        <div class="grid-3">
            <!-- Ukrainian Holidays -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title">Ukrainian Holidays (<?php echo $current_year; ?>)</span>
                    <?php if ($can_edit_holidays): ?>
                    <a href="holidays.php?type=ukrainian" class="edit-link">Edit</a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (!empty($national_holidays)): ?>
                        <?php foreach ($national_holidays as $h): ?>
                        <div class="holiday-item">
                            <span class="holiday-name"><?php echo htmlspecialchars($h['title']); ?></span>
                            <span class="holiday-date"><?php echo date('j M', strtotime($h['date'])); ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">No holidays found</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- English Holidays -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title">English Holidays (<?php echo $current_year; ?>)</span>
                    <?php if ($can_edit_holidays): ?>
                    <a href="holidays.php?type=english" class="edit-link">Edit</a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (!empty($english_holidays)): ?>
                        <?php foreach ($english_holidays as $h): ?>
                        <div class="holiday-item">
                            <span class="holiday-name"><?php echo htmlspecialchars($h['title']); ?></span>
                            <span class="holiday-date"><?php echo date('j M', strtotime($h['date'])); ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">No holidays found</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Absence Stats -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title">Absence Summary</span>
                </div>
                <div class="card-body" style="padding: 0;">
                    <?php if (!empty($user_stats)): ?>
                    <div class="scroll-table">
                        <table class="data-table compact-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th class="text-right">Vacation</th>
                                    <th class="text-right">Unpaid</th>
                                    <th class="text-right">Illness</th>
                                    <th class="text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($user_stats as $stat): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($stat['name']); ?></td>
                                    <td class="text-right"><?php echo $stat['vacation'] ?: '-'; ?></td>
                                    <td class="text-right"><?php echo $stat['not_paid'] ?: '-'; ?></td>
                                    <td class="text-right"><?php echo $stat['illness'] ?: '-'; ?></td>
                                    <td class="text-right"><strong><?php echo $stat['total']; ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="totals-row">
                                    <td>Total</td>
                                    <td class="text-right"><?php echo $total_vac; ?></td>
                                    <td class="text-right"><?php echo $total_pd; ?></td>
                                    <td class="text-right"><?php echo $total_ill; ?></td>
                                    <td class="text-right"><?php echo $total_vac + $total_pd + $total_ill; ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <div class="empty-state">No absences recorded</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Holiday Allowance Summary -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">Holiday Allowance Summary</span>
            </div>
            <div class="scroll-table">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th class="text-right">Total Allowance</th>
                            <th class="text-right">Days Used</th>
                            <th class="text-right">Remaining</th>
                            <th class="text-right">Earned <?php echo $today_year; ?></th>
                            <th class="text-right">Used <?php echo $today_year; ?></th>
                            <th>Start Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($holiday_summary as $hs): ?>
                        <?php 
                            $url_params = "show_user_id=" . $hs['user_id'];
                            if ($year_selected) $url_params .= "&year_selected=$year_selected";
                            else $url_params .= "&year_selected=$today_year";
                            if ($filter_start_date) $url_params .= "&start_date=$filter_start_date";
                            if ($filter_end_date) $url_params .= "&end_date=$filter_end_date";
                        ?>
                        <tr>
                            <td><a href="view_vacations.php?<?php echo $url_params; ?>"><?php echo htmlspecialchars($hs['name']); ?></a></td>
                            <td class="text-right"><?php echo $hs['total']; ?></td>
                            <td class="text-right"><?php echo $hs['used']; ?></td>
                            <td class="text-right"><strong class="stats-value highlight"><?php echo $hs['available']; ?></strong></td>
                            <td class="text-right"><?php echo $hs['total_year']; ?></td>
                            <td class="text-right"><?php echo $hs['used_year']; ?></td>
                            <td><?php echo $hs['start_date'] ? date('j M Y', strtotime($hs['start_date'])) : '-'; ?></td>
                            <td><a href="hours_compare.php?user_id=<?php echo $hs['user_id']; ?>">Details</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($search || $action): ?>
        <!-- Days Off Requests -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">Time Off Requests</span>
            </div>
            <?php if (!empty($days_off)): ?>
            <div class="scroll-table">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Description</th>
                            <th>Reason</th>
                            <th>From</th>
                            <th>To</th>
                            <th class="text-center">Days</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($days_off as $d): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($d['user_name']); ?></td>
                            <td><a href="create_vacation.php?vacation_id=<?php echo $d['period_id']; ?>"><?php echo htmlspecialchars($d['title']); ?></a></td>
                            <td><?php echo htmlspecialchars($d['reason']); ?></td>
                            <td><?php echo date('j M Y', strtotime($d['start_date'])); ?></td>
                            <td><?php echo date('j M Y', strtotime($d['end_date'])); ?></td>
                            <td class="text-center"><?php echo $d['total_days']; ?></td>
                            <td class="text-center">
                                <?php if ($d['status'] == 'approved'): ?>
                                    <span class="badge badge-success">Approved</span>
                                <?php elseif ($d['status'] == 'declined'): ?>
                                    <span class="badge badge-danger">Declined</span>
                                <?php else: ?>
                                    <span class="badge badge-pending">Pending</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="card-body">
                <div class="empty-state">No time off requests found for the selected period</div>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($paid_days)): ?>
        <!-- Paid Days -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">Paid Days (Overtime/Extra)</span>
            </div>
            <div class="scroll-table">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Description</th>
                            <th>From</th>
                            <th>To</th>
                            <th class="text-center">Days</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paid_days as $d): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($d['user_name']); ?></td>
                            <td><a href="create_vacation.php?vacation_id=<?php echo $d['period_id']; ?>"><?php echo htmlspecialchars($d['title']); ?></a></td>
                            <td><?php echo date('j M Y', strtotime($d['start_date'])); ?></td>
                            <td><?php echo date('j M Y', strtotime($d['end_date'])); ?></td>
                            <td class="text-center"><?php echo $d['total_days']; ?></td>
                            <td class="text-center">
                                <?php if ($d['status'] == 'approved'): ?>
                                    <span class="badge badge-success">Approved</span>
                                <?php elseif ($d['status'] == 'declined'): ?>
                                    <span class="badge badge-danger">Declined</span>
                                <?php else: ?>
                                    <span class="badge badge-pending">Pending</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>

    </div>

    <script>
    function clearFilter() {
        document.querySelector('select[name="year_selected"]').value = '';
        document.querySelector('input[name="start_date"]').value = '';
        document.querySelector('input[name="end_date"]').value = '';
        document.querySelector('input[name="iduser"]').value = '';
    }
    </script>
</body>
</html>
<?php
?>
