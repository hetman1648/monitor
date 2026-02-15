<?php
include("./includes/date_functions.php");
include("./includes/common.php");

CheckSecurity(1);

$db2 = new DB_Sql;
$db2->Database = DATABASE_NAME;
$db2->User     = DATABASE_USER;
$db2->Password = DATABASE_PASSWORD;
$db2->Host     = DATABASE_HOST;

$db3 = new DB_Sql;
$db3->Database = DATABASE_NAME;
$db3->User     = DATABASE_USER;
$db3->Password = DATABASE_PASSWORD;
$db3->Host     = DATABASE_HOST;

$person_selected = GetParam("person_selected");
$year_selected   = GetParam("year_selected");
$month_selected  = GetParam("month_selected");
$team            = GetParam("team");
$multiplier      = GetParam("multiplier");
$total_viart     = GetParam("total_viart");
$submit          = GetParam("submit");

$holiday_dates = array_fill(1, 31, 0);

if (floatval($multiplier) == 0 || floatval($multiplier) <= 0) $multiplier = 1;
if (floatval($total_viart) == 0 || floatval($total_viart) <= 0) $total_viart = 1;
if (!$year_selected) $year_selected = date("Y");
if (!$month_selected) $month_selected = date("m");

$as = ""; $vs = ""; $ys = "";
switch (strtolower($team)) {
    case "all":    $sqlteam = ""; $as = "selected"; break;
    case "viart":  $sqlteam = " AND u.is_viart=1 "; $vs = "selected"; break;
    case "yoonoo": $sqlteam = " AND u.is_viart=0 "; $ys = "selected"; break;
    default:       $sqlteam = " AND u.is_viart=1 "; $vs = "selected"; $team = "viart";
}

// --- Planned days ---
$sql2 = " SELECT CONCAT(u.first_name, ' ', u.last_name) AS user_name, ";
$sql2 .= " COUNT(DISTINCT DATE_FORMAT(tr.started_date, '%Y %m %d')) AS work_days ";
$sql2 .= " FROM time_report tr, users u";
$sql2 .= " WHERE WEEKDAY(tr.started_date)<=4 AND tr.user_id=u.user_id ";
if ($year_selected) $sql2 .= " AND DATE_FORMAT(tr.started_date, '%Y')='$year_selected' ";
if ($month_selected) $sql2 .= " AND DATE_FORMAT(tr.started_date, '%m')='$month_selected' ";
$sql2 .= " GROUP BY user_name ";
$db2->query($sql2, __FILE__, __LINE__);
$work_days_global = array();
while ($db2->next_record()) {
    $work_days_global[$db2->f("user_name")] = $db2->f("work_days");
}
$working_days_planned = 0;

$sql2 = " SELECT CONCAT(u.first_name, ' ', u.last_name) AS user_name, u.user_id, ";
$sql2 .= " COUNT(DISTINCT DATE_FORMAT(tr.started_date, '%Y %m %d')) AS working_days ";
$sql2 .= " FROM time_report tr, users u WHERE tr.user_id=u.user_id ";
if ($year_selected) $sql2 .= " AND DATE_FORMAT(tr.started_date, '%Y')='$year_selected' ";
if ($month_selected) $sql2 .= " AND DATE_FORMAT(tr.started_date, '%m')='$month_selected' ";
$sql2 .= " GROUP BY user_name ORDER BY user_name ";
$db2->query($sql2, __FILE__, __LINE__);
if ($db2->next_record()) {
    do {
        $un = $db2->f("user_name");
        if (array_key_exists($un, $work_days_global)) {
            if ($working_days_planned <= $work_days_global[$un]) {
                $working_days_planned = $work_days_global[$un];
            }
        }
    } while ($db2->next_record());
}

$sql2 = "SELECT * FROM national_holidays";
if ($year_selected) $sql2 .= " WHERE DATE_FORMAT(holiday_date, '%Y')='$year_selected' ";
if ($month_selected) $sql2 .= " AND DATE_FORMAT(holiday_date, '%m')='$month_selected' ";
$db2->query($sql2, __FILE__, __LINE__);
if ($db2->next_record()) {
    do {
        $hol_date = $db2->Record["holiday_date"];
        $hol_date_arr = explode("-", $hol_date);
        $week_day = date("w", mktime(0, 0, 0, $hol_date_arr[1], $hol_date_arr[2], $hol_date_arr[0]));
        if (($week_day != 0) && ($week_day != 6)) {
            $working_days_planned--;
        }
    } while ($db2->next_record());
}

// --- Main data ---
$team_groups = array(); // array of team groups, each with 'summary' and 'members'
$has_data = false;

if ($submit && $year_selected && $month_selected) {
    $sqldata = "";
    $user_ids = array();
    $viart_users_time = array();
    $sayu_users_time = array();

    if ($year_selected) $sqldata .= " AND DATE_FORMAT(tr.started_date, '%Y')='$year_selected' ";
    if ($month_selected) $sqldata .= " AND DATE_FORMAT(tr.started_date, '%m')='$month_selected' ";

    $sql = " SELECT u.user_id FROM users u WHERE 1 " . $sqlteam . " ORDER BY u.user_id";
    $user_ids = db_once_query($sql, true);
    foreach ($user_ids as $k => $v_id) {
        $viart_users_time[$v_id] = 0;
        $sayu_users_time[$v_id] = 0;
    }
    $sqlviart = " WHERE pp.project_title like 'viart' ";
    $sqlsayu = " WHERE not pp.project_title like 'viart' ";

    $sqlM  = " SELECT SUM(tr.spent_hours) AS count_hours ";
    $sqlM .= " FROM ((((projects pp ";
    $sqlM .= " INNER JOIN projects p ON (IF (p.parent_project_id, p.parent_project_id, p.project_id)=pp.project_id)) ";
    $sqlM .= " INNER JOIN tasks t ON (t.project_id = p.project_id)) ";
    $sqlM .= " INNER JOIN time_report tr ON (t.task_id = tr.task_id " . $sqldata . ")) ";
    $sqlM .= " INNER JOIN users u ON (tr.user_id=u.user_id AND u.is_viart=1)) ";
    $viart_total_time = db_once_query($sqlM . $sqlviart, true);
    $sayu_total_time = db_once_query($sqlM . $sqlsayu, true);

    $sqlM  = " SELECT tr.user_id AS user_id, SUM(tr.spent_hours) AS count_hours ";
    $sqlM .= " FROM projects pp ";
    $sqlM .= " INNER JOIN projects p ON (IF (p.parent_project_id, p.parent_project_id, p.project_id)=pp.project_id) ";
    $sqlM .= " INNER JOIN tasks t ON (t.project_id = p.project_id) ";
    $sqlM .= " INNER JOIN time_report tr ON (t.task_id = tr.task_id " . $sqldata . ") ";
    $sqlM .= " INNER JOIN users u ON (tr.user_id=u.user_id AND u.is_viart=1) ";
    $sqlgroup = " GROUP BY tr.user_id ";
    $db->query($sqlM . $sqlviart . $sqlgroup, __FILE__, __LINE__);
    while ($db->next_record()) { $viart_users_time[$db->f(0)] = $db->f(1); }
    $db->query($sqlM . $sqlsayu . $sqlgroup, __FILE__, __LINE__);
    while ($db->next_record()) { $sayu_users_time[$db->f(0)] = $db->f(1); }

    $sql  = " SELECT CONCAT(u.first_name, ' ', u.last_name) AS user_name, ";
    $sql .= " COUNT(DISTINCT DATE_FORMAT(tr.started_date, '%Y %m %d')) AS work_days ";
    $sql .= " FROM time_report tr, users u";
    $sql .= " WHERE WEEKDAY(tr.started_date)<=4 AND tr.user_id=u.user_id " . $sqlteam . $sqldata;
    $sql .= " GROUP BY user_name ";
    $db->query($sql, __FILE__, __LINE__);
    $work_days = array();
    while ($db->next_record()) {
        $work_days[$db->f("user_name")] = $db->f("work_days");
    }

    $sql  = " SELECT u.user_id, CONCAT(mu.first_name, ' ', mu.last_name) AS manager_name, ";
    $sql .= " CONCAT(u.first_name, ' ', u.last_name) AS user_name, SUM(tr.spent_hours) AS count_hours, ut.team_name, ";
    $sql .= " COUNT(DISTINCT tr.task_id) AS count_tasks, COUNT(DISTINCT DATE_FORMAT(tr.started_date, '%Y %m %d')) AS working_days ";
    $sql .= " FROM (((users mu ";
    $sql .= " INNER JOIN users u ON (IF(u.manager_id>0,u.manager_id,u.user_id)=mu.user_id AND u.is_deleted is NULL)) ";
    $sql .= " INNER JOIN time_report tr ON (tr.user_id=u.user_id " . $sqldata . ")) ";
    $sql .= " LEFT JOIN users_teams ut ON (ut.manager_id=u.user_id)) ";
    $sql .= " WHERE 1 " . $sqlteam;
    $sql .= " GROUP BY user_name ORDER BY manager_name, u.manager_id, user_name ";
    $db->query($sql, __FILE__, __LINE__);

    // Collect all records into team groups
    $records = array();
    $all_user_records = array();

    if ($db->next_record()) {
        $has_data = true;
        do {
            $user_name_val = $db->f("user_name");
            $manager_name_val = $db->f("manager_name");
            $user_id_val = $db->f("user_id");
            $count_hours = $db->f("count_hours");
            $count_tasks = $db->f("count_tasks");
            $team_users = $db->f("team_name");

            $user_sayu_time = (isset($sayu_users_time[$user_id_val]) ? floatval($sayu_users_time[$user_id_val]) : 0) * $multiplier;
            if (isset($viart_total_time[0]) && $viart_total_time[0] > 0 && isset($viart_users_time[$user_id_val])) {
                $user_viart_time = floatval($viart_users_time[$user_id_val]) * ($total_viart / $viart_total_time[0]);
            } else {
                $user_viart_time = 0;
            }
            $user_total_time = $user_sayu_time + $user_viart_time;

            $time_per_task = ($count_tasks != 0) ? $count_hours / $count_tasks : 0;
            $working_days_val = $db->f("working_days");
            $hours_per_day = ($working_days_val != 0) ? $count_hours / $working_days_val : 0;
            $days_off = $working_days_val - (isset($work_days[$user_name_val]) ? $work_days[$user_name_val] : 0);

            // Warnings
            $warnings = 0;
            $sql2 = "SELECT COUNT(user_id) AS warnings_total FROM warnings WHERE user_id='$user_id_val'";
            if ($year_selected) $sql2 .= " AND DATE_FORMAT(date_added, '%Y')='$year_selected' ";
            if ($month_selected) $sql2 .= " AND DATE_FORMAT(date_added, '%m')='$month_selected' ";
            $db2->query($sql2, __FILE__, __LINE__);
            if ($db2->next_record()) $warnings = $db2->f("warnings_total");

            // Working holidays
            $working_holidays = 0;
            $sql2 = "SELECT holiday_date FROM national_holidays ";
            if ($year_selected) $sql2 .= " WHERE DATE_FORMAT(holiday_date, '%Y')='$year_selected' ";
            if ($month_selected) $sql2 .= " AND DATE_FORMAT(holiday_date, '%m')='$month_selected' ";
            $db2->query($sql2, __FILE__, __LINE__);
            if ($db2->next_record()) {
                do {
                    $hol_date = $db2->f("holiday_date");
                    $sql3 = "SELECT report_id FROM time_report WHERE user_id='$user_id_val' AND DATE_FORMAT(started_date, '%Y-%m-%d')='$hol_date'";
                    $db3->query($sql3, __FILE__, __LINE__);
                    if ($db3->next_record()) $working_holidays++;
                } while ($db2->next_record());
            }

            // Holidays total
            $holidays_total = 0;
            $total_paid = 0;
            $sql2 = "SELECT * FROM days_off WHERE user_id='$user_id_val'";
            if ($year_selected) $sql2 .= " AND DATE_FORMAT(start_date, '%Y')='$year_selected' ";
            if ($month_selected) $sql2 .= " AND DATE_FORMAT(start_date, '%m')='$month_selected' ";
            $db2->query($sql2, __FILE__, __LINE__);
            if ($db2->next_record()) {
                do {
                    $reason = $db2->f("reason_id");
                    $is_paid = $db2->f("is_paid");
                    $sd_val = $db2->f("start_date");
                    $sd_arr = explode("-", $sd_val);
                    $ed_val = $db2->f("end_date");
                    $ed_arr = explode("-", $ed_val);
                    if ($ed_arr[1] == $month_selected) {
                        $holidays_total += $db2->f("total_days");
                        if (($reason == 1) || ($reason == 2) || ($is_paid == 1)) {
                            $total_paid += $db2->f("total_days");
                        }
                    } else {
                        $nd = date("t", mktime(0, 0, 0, $month_selected, 1, $year_selected));
                        $holidays_total += ($nd - $sd_arr[2] + 1);
                        if (($reason == 1) || ($reason == 2) || ($is_paid == 1)) {
                            $total_paid += $nd - $sd_arr[2] + 1;
                        }
                    }
                } while ($db2->next_record());
            } else {
                $sql2 = "SELECT * FROM days_off WHERE user_id='$user_id_val'";
                if ($year_selected) $sql2 .= " AND DATE_FORMAT(end_date, '%Y')='$year_selected' ";
                if ($month_selected) $sql2 .= " AND DATE_FORMAT(end_date, '%m')='$month_selected' ";
                $holidays_total = 0;
                $db2->query($sql2, __FILE__, __LINE__);
                if ($db2->next_record()) {
                    do {
                        $reason = $db2->f("reason_id");
                        $is_paid = $db2->f("is_paid");
                        $ed_val = $db2->f("end_date");
                        $ed_arr = explode("-", $ed_val);
                        $holidays_total += $ed_arr[2];
                        if (($reason == 1) || ($reason == 2) || ($is_paid == 1))
                            $total_paid += $ed_arr[2];
                    } while ($db2->next_record());
                }
            }

            $record = array(
                'warnings' => $warnings,
                'holidays_total' => $holidays_total,
                'total_paid' => $working_days_val + $total_paid,
                'user_id' => $user_id_val,
                'user_name' => $user_name_val,
                'manager_name' => $manager_name_val,
                'spent_hours' => $count_hours,
                'tasks' => $count_tasks,
                'time_per_task' => $time_per_task,
                'hours_per_day' => $hours_per_day,
                'working_days' => $working_days_val,
                'days_off' => $days_off,
                'working_holidays' => $days_off + $working_holidays,
                'year_selected' => $year_selected,
                'month_selected' => $month_selected,
                'viart_projects' => $user_viart_time,
                'sayu_projects' => $user_sayu_time,
                'total_projects' => $user_total_time,
                'team_name' => $team_users,
                'is_manager' => ($manager_name_val == $user_name_val)
            );

            // Group by team
            if (isset($records[1]) && strlen($team_users)) {
                // Finalize current group
                $team_groups[] = build_team_group($records);
                $records = array();
                init_summary($records);
                if ($manager_name_val == $user_name_val) $records[0]["user_name"] = $team_users;
                accumulate_summary($records, $record);
                $records[] = $record;
            } else {
                if (!sizeof($records)) init_summary($records);
                if ($manager_name_val == $user_name_val) $records[0]["user_name"] = $team_users;
                accumulate_summary($records, $record);
                $records[] = $record;
            }
        } while ($db->next_record());
        // Finalize last group
        if (sizeof($records) > 0) {
            $team_groups[] = build_team_group($records);
        }
    }
}

// --- Calendar data ---
$cal_data = null;
$cal_user_name = '';
if ($submit && $year_selected && $month_selected && $person_selected && is_number($person_selected)) {
    $sql = " SELECT DAYOFMONTH(tr.started_date) AS day_of_month, SUM(tr.spent_hours) AS sum_hours, ";
    $sql .= " CONCAT(u.first_name, ' ', u.last_name) AS user_name ";
    $sql .= " FROM time_report tr, users u ";
    $sql .= " WHERE tr.user_id='$person_selected' AND tr.user_id=u.user_id ";
    $sql .= " AND DATE_FORMAT(tr.started_date, '%Y')='$year_selected' ";
    $sql .= " AND DATE_FORMAT(tr.started_date, '%m')='$month_selected' ";
    $sql .= " GROUP BY day_of_month ";
    $db->query($sql, __FILE__, __LINE__);

    $attended_dates = array();
    $spent_hours_cal = array();
    while ($db->next_record()) {
        $attended_dates[$db->f("day_of_month")] = 1;
        $spent_hours_cal[$db->f("day_of_month")] = $db->f("sum_hours");
        $cal_user_name = $db->f("user_name");
    }

    // Holidays for calendar
    $sql = " SELECT holiday_date FROM national_holidays WHERE DATE_FORMAT(holiday_date, '%Y')='$year_selected' AND DATE_FORMAT(holiday_date, '%m')='$month_selected' ";
    $db->query($sql, __FILE__, __LINE__);
    while ($db->next_record()) {
        $hd = $db->f("holiday_date");
        $hd_arr = explode("-", $hd);
        $holiday_dates[(integer)$hd_arr[2]] = 1;
    }

    $n_days = date("t", mktime(0, 0, 0, $month_selected, 1, $year_selected));
    $first_day = date("w", mktime(0, 0, 0, $month_selected, 1, $year_selected));
    if ($first_day == 0) $first_day = 7;

    $_first_day = $first_day;
    $_n_days_rem = $n_days - (8 - $_first_day);
    $_short_week = $_n_days_rem % 7;
    $_full_week = $_n_days_rem - $_short_week;
    $n_weeks = 1 + $_full_week / 7 + ($_short_week ? 1 : 0);

    $cal_weeks = array();
    $cur_day = 1;
    $week_hours = 0;
    $month_hours = 0;
    $w_working_days = 0;

    for ($row = 1; $row <= $n_weeks; $row++) {
        $week = array('days' => array(), 'week_hours' => 0, 'w_hours_per_day' => 0);
        for ($col = 1; $col <= 7; $col++) {
            $day = array('num' => '', 'color' => '#FAFAFA', 'link' => '');
            if (($row == 1 && $col >= $first_day) || ($row > 1 && $cur_day <= $n_days)) {
                $sd_cal = date("Y-m-d", mktime(0, 0, 0, $month_selected, $cur_day, $year_selected));
                $day['num'] = $cur_day;
                $day['link'] = "time_report.php?submit=1&person_selected=$person_selected&start_date=$sd_cal&end_date=$sd_cal";
                if (isset($attended_dates[$cur_day]) && $attended_dates[$cur_day] == 1) {
                    $day['color'] = $holiday_dates[$cur_day] ? '#EECF63' : '#7FC5F4';
                    $week_hours += $spent_hours_cal[$cur_day];
                    $w_working_days++;
                } elseif (isset($holiday_dates[$cur_day]) && $holiday_dates[$cur_day]) {
                    $day['color'] = '#EECF63';
                    $week['days'][] = $day;
                    $cur_day++;
                    continue;
                } elseif ($col > 5) {
                    $day['color'] = '#C5F4C5';
                } else {
                    $day['color'] = '#FAFAFA';
                }
                $cur_day++;
            }
            $week['days'][] = $day;
            if ($col == 7) {
                $week['week_hours'] = $week_hours;
                $week['w_hours_per_day'] = ($w_working_days != 0) ? $week_hours / $w_working_days : 0;
                $month_hours += $week_hours;
                $week_hours = 0;
                $w_working_days = 0;
            }
        }
        $cal_weeks[] = $week;
    }
    $cal_data = array('weeks' => $cal_weeks, 'month_hours' => $month_hours);
}

$month_name = date("F", mktime(0, 0, 0, $month_selected, 1, $year_selected));

// Helper functions
function init_summary(&$records) {
    $records[0] = array(
        'user_name' => '', 'warnings' => 0, 'holidays_total' => 0, 'total_paid' => 0,
        'spent_hours' => 0, 'tasks' => 0, 'time_per_task' => 0, 'hours_per_day' => 0,
        'working_days' => 0, 'days_off' => 0, 'working_holidays' => 0,
        'viart_projects' => 0, 'sayu_projects' => 0, 'total_projects' => 0,
        'year_selected' => 0, 'month_selected' => 0
    );
}

function accumulate_summary(&$records, $record) {
    $fields = array('warnings','holidays_total','total_paid','spent_hours','tasks',
                    'time_per_task','hours_per_day','working_days','days_off',
                    'working_holidays','viart_projects','sayu_projects','total_projects');
    foreach ($fields as $f) {
        $records[0][$f] += $record[$f];
    }
}

function build_team_group($records) {
    $summary = $records[0];
    $member_count = count($records) - 1;
    if ($member_count > 0) {
        $summary['time_per_task_avg'] = $summary['time_per_task'] / $member_count;
        $summary['hours_per_day_avg'] = $summary['hours_per_day'] / $member_count;
    } else {
        $summary['time_per_task_avg'] = 0;
        $summary['hours_per_day_avg'] = 0;
    }
    unset($records[0]);
    return array('summary' => $summary, 'members' => array_values($records));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Summary Report - Sayu Monitor</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh; color: #2d3748; font-size: 14px;
        }
        .container { max-width: 1500px; margin: 0 auto; padding: 24px; }
        .page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; flex-wrap: wrap; gap: 16px; }
        .page-title { font-size: 1.5rem; font-weight: 700; color: #2d3748; }
        .page-subtitle { color: #718096; font-size: 0.85rem; margin-top: 4px; }

        .filter-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); padding: 20px; margin-bottom: 20px; }
        .filter-form { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 4px; }
        .filter-group label { font-size: 0.75rem; font-weight: 600; color: #718096; text-transform: uppercase; letter-spacing: 0.5px; }
        .filter-group select, .filter-group input[type="text"] {
            padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;
            font-size: 0.85rem; font-family: inherit; background: #fff; min-width: 130px;
        }
        .filter-group select:focus, .filter-group input:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }
        .filter-actions { display: flex; gap: 8px; align-items: flex-end; }
        .btn { padding: 8px 16px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; cursor: pointer; border: none; font-family: inherit; transition: all 0.2s; }
        .btn-primary { background: #667eea; color: #fff; }
        .btn-primary:hover { background: #5a67d8; }
        .btn-secondary { background: #e2e8f0; color: #4a5568; }
        .btn-secondary:hover { background: #cbd5e0; }

        .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); overflow: hidden; margin-bottom: 20px; }
        .card-header { padding: 14px 20px; background: #f8f9fa; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .card-title { font-weight: 600; font-size: 1rem; color: #2d3748; }

        .scroll-table { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th {
            padding: 8px 10px; text-align: center; font-weight: 600; font-size: 0.7rem;
            text-transform: uppercase; letter-spacing: 0.3px; color: #718096;
            background: #f8f9fa; border-bottom: 2px solid #e2e8f0; white-space: nowrap;
        }
        .data-table th:first-child { text-align: left; padding-left: 16px; }
        .data-table td { padding: 7px 10px; border-bottom: 1px solid #f1f5f9; font-size: 0.82rem; text-align: center; white-space: nowrap; }
        .data-table td:first-child { text-align: left; padding-left: 16px; }
        .data-table td a { color: #667eea; text-decoration: none; }
        .data-table td a:hover { text-decoration: underline; }
        .data-table tr:hover td { background: #f8f9fc; }

        .team-row td {
            background: #eef2ff; font-weight: 700; font-style: italic;
            color: #4c51bf; padding: 10px 10px; border-bottom: 2px solid #667eea;
        }
        .manager-name { font-weight: 700; color: #2d3748; }
        .user-name { color: #4a5568; }

        .empty-state { text-align: center; padding: 40px; color: #718096; }

        /* Calendar */
        .calendar-card { margin-top: 10px; }
        .calendar-table { border-collapse: collapse; margin: 0 auto; }
        .calendar-table th { padding: 8px 12px; text-align: center; font-size: 0.75rem; font-weight: 600; color: #fff; background: #667eea; }
        .calendar-table td { padding: 8px 10px; text-align: center; font-size: 0.85rem; border: 1px solid #e2e8f0; min-width: 36px; }
        .calendar-table td a { color: #2d3748; text-decoration: none; font-weight: 500; }
        .calendar-table td a:hover { text-decoration: underline; color: #667eea; }
        .cal-total td { font-weight: 700; background: #f0f4ff; }

        @media (max-width: 768px) {
            .container { padding: 12px; }
            .filter-form { flex-direction: column; }
            .filter-group select, .filter-group input { min-width: 100%; }
            .page-title { font-size: 1.2rem; }
        }

        /* Dark mode – filter and report */
        html.dark-mode body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #e2e8f0;
        }
        html.dark-mode .filter-card {
            background: #1e293b; border-color: #334155; box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }
        html.dark-mode .filter-group label { color: #94a3b8; }
        html.dark-mode .filter-group select,
        html.dark-mode .filter-group input[type="text"] {
            background: #0f172a; border-color: #334155; color: #e2e8f0;
        }
        html.dark-mode .filter-group select:focus,
        html.dark-mode .filter-group input:focus {
            border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.25);
        }
        html.dark-mode .btn-secondary {
            background: #334155; color: #e2e8f0;
        }
        html.dark-mode .btn-secondary:hover {
            background: #475569;
        }
        html.dark-mode .card {
            background: #1e293b; border-color: #334155; box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        html.dark-mode .card-header {
            background: #0f172a; border-color: #334155;
        }
        html.dark-mode .card-title { color: #e2e8f0; }
        html.dark-mode .data-table th {
            background: #0f172a; border-color: #334155; color: #94a3b8;
        }
        html.dark-mode .data-table td {
            border-color: #334155; color: #cbd5e1;
        }
        html.dark-mode .data-table tr:hover td { background: #334155; }
        html.dark-mode .data-table td a { color: #818cf8; }
        html.dark-mode .data-table td a:hover { color: #a5b4fc; }
        html.dark-mode .team-row td {
            background: #312e81; color: #c7d2fe; border-color: #4338ca;
        }
        html.dark-mode .manager-name { color: #e2e8f0; }
        html.dark-mode .user-name { color: #cbd5e1; }
        html.dark-mode .page-title { color: #e2e8f0; }
        html.dark-mode .page-subtitle { color: #94a3b8; }
        html.dark-mode .calendar-table td { border-color: #334155; }
        html.dark-mode .calendar-table td a { color: #e2e8f0; }
        html.dark-mode .calendar-table td a:hover { color: #818cf8; }
        html.dark-mode .cal-total td { background: #334155; }
    </style>
</head>
<body>
    <?php include("./includes/modern_header.php"); ?>

    <div class="container">
        <div class="page-header">
            <div>
                <h1 class="page-title">Summary Report</h1>
                <p class="page-subtitle"><?php echo $month_name . ' ' . $year_selected; ?> &mdash; Planned working days: <?php echo $working_days_planned; ?></p>
            </div>
        </div>

        <div class="filter-card">
            <form name="frmFilter" action="summary_report.php" method="GET">
                <div class="filter-form">
                    <div class="filter-group">
                        <label>Year</label>
                        <select name="year_selected"><?php echo GetYearOptions(2004, date("Y"), $year_selected); ?></select>
                    </div>
                    <div class="filter-group">
                        <label>Month</label>
                        <select name="month_selected"><?php echo GetMonthOptions($month_selected); ?></select>
                    </div>
                    <div class="filter-group">
                        <label>Multiplier</label>
                        <input type="text" name="multiplier" value="<?php echo htmlspecialchars($multiplier); ?>" style="min-width:80px">
                    </div>
                    <div class="filter-group">
                        <label>Total ViArt</label>
                        <input type="text" name="total_viart" value="<?php echo htmlspecialchars($total_viart); ?>" style="min-width:80px">
                    </div>
                    <div class="filter-group">
                        <label>Team</label>
                        <select name="team">
                            <option value="all" <?php echo $as; ?>>All teams</option>
                            <option value="viart" <?php echo $vs; ?>>Sayu Ukraine</option>
                            <option value="yoonoo" <?php echo $ys; ?>>Sayu UK</option>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" name="submit" value="1" class="btn btn-primary">Filter</button>
                        <button type="button" class="btn btn-secondary" onclick="window.location='summary_report.php'">Clear</button>
                    </div>
                </div>
            </form>
        </div>

        <?php if ($submit && $year_selected && $month_selected): ?>
        <div class="card">
            <div class="card-header">
                <span class="card-title">Summary Report &mdash; <?php echo $month_name . ' ' . $year_selected; ?></span>
            </div>
            <?php if ($has_data && !empty($team_groups)): ?>
            <div class="scroll-table">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Person</th>
                            <th>Hours</th>
                            <th>Tasks</th>
                            <th title="Time per task">Time/task</th>
                            <th title="Hours per day">Hrs/day</th>
                            <th title="Working days">Working</th>
                            <th title="Working holidays">Wrk. hol.</th>
                            <th title="Warnings">Warn.</th>
                            <th title="Planned">Plan.</th>
                            <th title="Holidays">Hol.</th>
                            <th>Total Paid</th>
                            <th>ViArt</th>
                            <th>Sayu</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($team_groups as $group):
                        $s = $group['summary'];
                    ?>
                        <tr class="team-row">
                            <td><?php echo htmlspecialchars($s['user_name']); ?></td>
                            <td><?php echo Hours2HoursMins($s['spent_hours']); ?></td>
                            <td><?php echo $s['tasks']; ?></td>
                            <td><?php echo Hours2HoursMins($s['time_per_task_avg']); ?></td>
                            <td><?php echo Hours2HoursMins($s['hours_per_day_avg']); ?></td>
                            <td><?php echo $s['working_days']; ?></td>
                            <td><?php echo $s['working_holidays']; ?></td>
                            <td><?php echo $s['warnings']; ?></td>
                            <td></td>
                            <td><?php echo $s['holidays_total']; ?></td>
                            <td><?php echo $s['total_paid']; ?></td>
                            <td style="text-align:right"><?php echo number_format($s['viart_projects'], 2); ?></td>
                            <td style="text-align:right"><?php echo number_format($s['sayu_projects'], 2); ?></td>
                            <td style="text-align:right"><?php echo number_format($s['total_projects'], 2); ?></td>
                        </tr>
                        <?php foreach ($group['members'] as $m):
                            $link = "summary_report.php?year_selected=" . urlencode($m['year_selected']) .
                                    "&month_selected=" . urlencode($m['month_selected']) .
                                    "&submit=1&person_selected=" . urlencode($m['user_id']) .
                                    "&team=" . urlencode($team) .
                                    "&multiplier=" . urlencode($multiplier) .
                                    "&total_viart=" . urlencode($total_viart);
                            $tasks_link = "tasks_report.php?year_selected=" . urlencode($m['year_selected']) .
                                    "&month_selected=" . urlencode($m['month_selected']) .
                                    "&person_selected=" . urlencode($m['user_id']) .
                                    "&team=" . urlencode($team) . "&submit=1";
                        ?>
                        <tr>
                            <td><a href="<?php echo $link; ?>" class="<?php echo $m['is_manager'] ? 'manager-name' : 'user-name'; ?>"><?php echo htmlspecialchars($m['user_name']); ?></a></td>
                            <td><?php echo Hours2HoursMins($m['spent_hours']); ?></td>
                            <td><a href="<?php echo $tasks_link; ?>"><?php echo $m['tasks']; ?></a></td>
                            <td><?php echo Hours2HoursMins($m['time_per_task']); ?></td>
                            <td><?php echo Hours2HoursMins($m['hours_per_day']); ?></td>
                            <td><?php echo $m['working_days']; ?></td>
                            <td><?php echo $m['working_holidays']; ?></td>
                            <td><?php echo $m['warnings']; ?></td>
                            <td></td>
                            <td><?php echo $m['holidays_total']; ?></td>
                            <td><?php echo $m['total_paid']; ?></td>
                            <td style="text-align:right"><?php echo number_format($m['viart_projects'], 2); ?></td>
                            <td style="text-align:right"><?php echo number_format($m['sayu_projects'], 2); ?></td>
                            <td style="text-align:right"><?php echo number_format($m['total_projects'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state"><p>No data for this period</p></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($cal_data): ?>
        <div class="card calendar-card">
            <div class="card-header">
                <span class="card-title">Attendance details for <?php echo htmlspecialchars($cal_user_name); ?></span>
            </div>
            <div style="padding: 20px; overflow-x: auto;">
                <table class="calendar-table">
                    <thead>
                        <tr>
                            <th>M</th><th>T</th><th>W</th><th>T</th><th>F</th><th>S</th><th>S</th>
                            <th>Total hours</th><th>Hours/day</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($cal_data['weeks'] as $week): ?>
                        <tr>
                        <?php foreach ($week['days'] as $day): ?>
                            <td style="background-color:<?php echo $day['color']; ?>">
                                <?php if ($day['num'] && $day['link']): ?>
                                    <a href="<?php echo $day['link']; ?>"><?php echo $day['num']; ?></a>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                            <td style="background:#f8f9fa"><?php echo Hours2HoursMins($week['week_hours']); ?></td>
                            <td style="background:#f8f9fa"><?php echo Hours2HoursMins($week['w_hours_per_day']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                        <tr class="cal-total">
                            <td colspan="7" style="text-align:right">Total</td>
                            <td><strong><?php echo Hours2HoursMins($cal_data['month_hours']); ?></strong></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
