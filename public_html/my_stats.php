<?php

include_once("./includes/common.php");
include_once("./includes/date_functions.php");

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

$db4 = new DB_Sql;
$db4->Database = DATABASE_NAME;
$db4->User     = DATABASE_USER;
$db4->Password = DATABASE_PASSWORD;
$db4->Host     = DATABASE_HOST;

$db6 = new DB_Sql;
$db6->Database = DATABASE_NAME;
$db6->User     = DATABASE_USER;
$db6->Password = DATABASE_PASSWORD;
$db6->Host     = DATABASE_HOST;

$person_selected = GetParam("person_selected");
$year_selected = GetParam("year_selected");
$month_selected = GetParam("month_selected");
$task_report = GetParam("task_report");
$start_date = GetParam("start_date");
$end_date = GetParam("end_date");

$user_id = GetSessionParam("UserID");
if (!$year_selected) { $year_selected = date("Y"); }
if (!$month_selected) { $month_selected = date("m"); }

// Get user name
$user_name = "";
$sql = "SELECT CONCAT(first_name, ' ', last_name) AS user_name FROM users WHERE user_id = " . intval($user_id);
$db->query($sql);
if ($db->next_record()) {
    $user_name = $db->f("user_name");
}

// Calculate summary data
$summary_data = null;
$calendar_data = array();
$holidays_arr = array();
$holiday_dates = array();
$warnings = array();
$vacations = array();
$task_reports = array();
$bugs = array();
$inventory = array();
$holiday_summary = null;

if ($year_selected && $month_selected) {
    // Working days calculation
    $working_days_2 = 0;
    $nowday = getdate();
    $nowday_day = $nowday["mday"];
    $nowday_year = $nowday["year"];
    $nowday_mon = $nowday["mon"];
    $n_days = date("t", mktime(0, 0, 0, $month_selected, 1, $year_selected));
    
    if ($year_selected == $nowday_year && $month_selected == $nowday_mon) {
        for ($i = 1; $i <= $nowday_day; $i++) {
            $week_day = date("w", mktime(0, 0, 0, $nowday_mon, $i, $nowday_year));
            if ($week_day != 0 && $week_day != 6) { $working_days_2++; }
        }
    } else {
        for ($i = 1; $i <= $n_days; $i++) {
            $week_day = date("w", mktime(0, 0, 0, $month_selected, $i, $year_selected));
            if ($week_day != 0 && $week_day != 6) $working_days_2++;
        }
    }

    // Get holidays count
    $holiday_quant = 0;
    $sql6 = " SELECT COUNT(holiday_id) AS hq FROM national_holidays WHERE WEEKDAY(holiday_date)<=4";
    if ($year_selected) $sql6 .= " AND DATE_FORMAT(holiday_date, '%Y')='$year_selected' ";
    if ($month_selected) $sql6 .= " AND DATE_FORMAT(holiday_date, '%m')='$month_selected' ";
    if ($month_selected == $nowday_mon && $year_selected == $nowday_year) {
        $sql6 .= " AND holiday_date<CURDATE()";
    }
    $db6->query($sql6);
    if ($db6->next_record()) {
        $holiday_quant = $db6->f("hq");
    }

    // Get holiday dates
    $sql6 = " SELECT holiday_date FROM national_holidays WHERE WEEKDAY(holiday_date)<=4";
    if ($year_selected) $sql6 .= " AND DATE_FORMAT(holiday_date, '%Y')='$year_selected' ";
    if ($month_selected) $sql6 .= " AND DATE_FORMAT(holiday_date, '%m')='$month_selected' ";
    if ($month_selected == $nowday_mon && $year_selected == $nowday_year) {
        $sql6 .= " AND holiday_date<CURDATE()";
    }
    $db6->query($sql6);
    while ($db6->next_record()) {
        $holidays_arr[] = $db6->f("holiday_date");
    }

    // Get work days per person
    $sql6 = " SELECT CONCAT(u.first_name, ' ', u.last_name) AS user_name, ";
    $sql6 .= " COUNT(DISTINCT DATE_FORMAT(tr.started_date, '%Y %m %d')) AS work_days ";
    $sql6 .= " FROM time_report tr, users u";
    $sql6 .= " WHERE WEEKDAY(tr.started_date)<=4 AND tr.user_id=u.user_id ";
    if ($year_selected) $sql6 .= " AND DATE_FORMAT(tr.started_date, '%Y')='$year_selected' ";
    if ($month_selected) $sql6 .= " AND DATE_FORMAT(tr.started_date, '%m')='$month_selected' ";
    $sql6 .= " GROUP BY user_name ";
    $db6->query($sql6);
    $work_days = array();
    while ($db6->next_record()) {
        $work_days[$db6->f("user_name")] = $db6->f("work_days");
    }

    // Summary report for current user
    $sql6 = " SELECT CONCAT(u.first_name, ' ', u.last_name) AS user_name, u.user_id, ";
    $sql6 .= " SUM(tr.spent_hours) AS count_hours, COUNT(DISTINCT tr.task_id) AS count_tasks, ";
    $sql6 .= " COUNT(DISTINCT DATE_FORMAT(tr.started_date, '%Y %m %d')) AS working_days ";
    $sql6 .= " FROM time_report tr, users u ";
    $sql6 .= " WHERE tr.user_id='$user_id' AND tr.user_id=u.user_id ";
    if ($year_selected) $sql6 .= " AND DATE_FORMAT(tr.started_date, '%Y')='$year_selected' ";
    if ($month_selected) $sql6 .= " AND DATE_FORMAT(tr.started_date, '%m')='$month_selected' ";
    $sql6 .= " GROUP BY user_name ORDER BY user_name ";
    $db6->query($sql6);

    if ($db6->next_record()) {
        $count_hours = $db6->f("count_hours");
        $count_tasks = $db6->f("count_tasks");
        $working_days = $db6->f("working_days");
        $time_per_task = $count_tasks != 0 ? $count_hours / $count_tasks : 0;
        $hours_per_day = $working_days != 0 ? $count_hours / $working_days : 0;
        $days_off = $working_days - (isset($work_days[$user_name]) ? $work_days[$user_name] : 0);

        // Calculate averages
        $average_working_days = $working_days_2 - $holiday_quant;
        $average_hours = 8 * $average_working_days;

        // Get average tasks
        $sql_avg = " SELECT SUM(tr.spent_hours) AS total_hours, COUNT(DISTINCT tr.task_id) AS total_tasks ";
        $sql_avg .= " FROM time_report tr WHERE 1=1 ";
        if ($year_selected) $sql_avg .= " AND DATE_FORMAT(tr.started_date, '%Y')='$year_selected' ";
        if ($month_selected) $sql_avg .= " AND DATE_FORMAT(tr.started_date, '%m')='$month_selected' ";
        $db6->query($sql_avg);
        $avg_tasks = 0;
        $avg_time_per_task = 0;
        if ($db6->next_record()) {
            $total_users = count($work_days) > 0 ? count($work_days) : 1;
            $avg_tasks = round($db6->f("total_tasks") / $total_users, 0);
            $avg_time_per_task = $avg_tasks > 0 ? $db6->f("total_hours") / $total_users / $avg_tasks : 0;
        }

        $summary_data = array(
            'user_name' => $user_name,
            'spent_hours' => Hours2HoursMins($count_hours),
            'tasks' => $count_tasks,
            'time_per_task' => Hours2HoursMins($time_per_task),
            'hours_per_day' => Hours2HoursMins($hours_per_day),
            'working_days' => $working_days,
            'days_off' => $days_off,
            'average_hours' => $average_hours . ":00",
            'average_tasks' => $avg_tasks . " (avg)",
            'average_time_per_task' => Hours2HoursMins($avg_time_per_task) . " (avg)",
            'average_working_days' => $average_working_days,
            'average_hours_per_day' => "8:00",
            'average_days_off' => "-"
        );
    }

    // Calendar data
    $sql = " SELECT DAYOFMONTH(tr.started_date) AS day_of_month, SUM(tr.spent_hours) AS sum_hours ";
    $sql .= " FROM time_report tr WHERE tr.user_id='$user_id' ";
    $sql .= " AND DATE_FORMAT(tr.started_date, '%Y')='$year_selected' ";
    $sql .= " AND DATE_FORMAT(tr.started_date, '%m')='$month_selected' ";
    $sql .= " GROUP BY day_of_month ";
    $db->query($sql);

    $attended_dates = array();
    $spent_hours = array();
    while ($db->next_record()) {
        $attended_dates[$db->f("day_of_month")] = 1;
        $spent_hours[$db->f("day_of_month")] = $db->f("sum_hours");
    }

    // Get holiday dates for calendar
    $sql = " SELECT holiday_date FROM national_holidays ";
    $sql .= " WHERE DATE_FORMAT(holiday_date, '%Y')='$year_selected' ";
    $sql .= " AND DATE_FORMAT(holiday_date, '%m')='$month_selected' ";
    $db->query($sql);
    while ($db->next_record()) {
        $holiday_date = $db->f("holiday_date");
        $holiday_date_arr = explode("-", $holiday_date);
        $day_of_hol = (integer)$holiday_date_arr[2];
        $holiday_dates[$day_of_hol] = 1;
    }

    // Build calendar
    $n_days = date("t", mktime(0, 0, 0, $month_selected, 1, $year_selected));
    $first_day = date("w", mktime(0, 0, 0, $month_selected, 1, $year_selected));
    if ($first_day == 0) { $first_day = 7; }
    
    $_first_day = $first_day;
    $_n_days = $n_days - (8 - $_first_day);
    $_short_week = $_n_days % 7;
    $_full_week = $_n_days - $_short_week;
    $_total_weeks = 1 + $_full_week / 7 + ($_short_week ? 1 : 0);
    $n_weeks = $_total_weeks;

    $cur_day = 1;
    $month_hours = 0;
    
    for ($row = 1; $row <= $n_weeks; $row++) {
        $week_data = array('days' => array(), 'week_hours' => 0, 'w_hours_per_day' => 0);
        $week_hours = 0;
        $w_working_days = 0;
        
        for ($col = 1; $col <= 7; $col++) {
            $day_data = array('day' => '', 'color' => '#FAFAFA', 'link' => '', 'type' => 'empty');
            
            if (($row == 1 && $col >= $first_day) || ($row > 1 && $cur_day <= $n_days)) {
                $sd = date("Y-m-d", mktime(0, 0, 0, $month_selected, $cur_day, $year_selected));
                $day_data['day'] = $cur_day;
                $day_data['link'] = "my_stats.php?year_selected=$year_selected&month_selected=$month_selected&task_report=1&user_id=$user_id&start_date=$sd&end_date=$sd";
                
                if (isset($attended_dates[$cur_day]) && $attended_dates[$cur_day] == 1) {
                    if (isset($holiday_dates[$cur_day])) {
                        $day_data['color'] = '#fef3c7';
                        $day_data['type'] = 'holiday';
                    } else {
                        $day_data['color'] = '#dbeafe';
                        $day_data['type'] = 'worked';
                    }
                    $week_hours += $spent_hours[$cur_day];
                    $w_working_days++;
                } elseif (isset($holiday_dates[$cur_day])) {
                    $day_data['color'] = '#fef3c7';
                    $day_data['type'] = 'holiday';
                } elseif ($col > 5) {
                    $day_data['color'] = '#dcfce7';
                    $day_data['type'] = 'weekend';
                } else {
                    $day_data['type'] = 'empty';
                }
                $cur_day++;
            }
            $week_data['days'][] = $day_data;
        }
        
        $week_data['week_hours'] = Hours2HoursMins($week_hours);
        $week_data['w_hours_per_day'] = $w_working_days != 0 ? Hours2HoursMins($week_hours / $w_working_days) : "-";
        $month_hours += $week_hours;
        $calendar_data[] = $week_data;
    }

    // Warnings
    $sql2 = "SELECT * FROM warnings WHERE user_id = " . $user_id;
    $sql2 .= " AND DATE_FORMAT(date_added, '%Y')='$year_selected' ";
    $sql2 .= " AND DATE_FORMAT(date_added, '%m')='$month_selected' ";
    $db2->query($sql2);
    while ($db2->next_record()) {
        $sql3 = "SELECT CONCAT(first_name, ' ', last_name) AS user_name FROM users WHERE user_id = " . ToSQL($db2->Record["admin_user_id"], "int");
        $db3->query($sql3);
        $db3->next_record();
        $warnings[] = array(
            'date_added' => $db2->f("date_added") ? norm_sql_date($db2->f("date_added")) : '',
            'notes' => $db2->f("description"),
            'creator' => $db3->f("user_name")
        );
    }

    // Vacations - Get all for the selected year
    $sql4 = "SELECT do.*, u.first_name, u.last_name, r.reason_name ";
    $sql4 .= " FROM days_off do ";
    $sql4 .= " INNER JOIN users u ON u.user_id = do.user_id ";
    $sql4 .= " INNER JOIN reasons r ON r.reason_id = do.reason_id ";
    $sql4 .= " WHERE do.user_id = " . ToSQL($user_id, "integer");
    $sql4 .= " AND (DATE_FORMAT(do.start_date, '%Y')='$year_selected' OR DATE_FORMAT(do.end_date, '%Y')='$year_selected')";
    $sql4 .= " ORDER BY start_date DESC";
    $db4->query($sql4);
    while ($db4->next_record()) {
        // Determine status
        $is_approved = $db4->Record["is_approved"];
        $is_declined = $db4->Record["is_declined"];
        if ($is_declined) {
            $status = 'declined';
        } elseif ($is_approved) {
            $status = 'approved';
        } else {
            $status = 'pending';
        }
        
        $vacations[] = array(
            'period_id' => $db4->Record["period_id"],
            'period_title' => $db4->Record["period_title"],
            'reason_type' => $db4->Record["reason_name"],
            'start_date' => $db4->Record["start_date"] ? norm_sql_date($db4->Record["start_date"]) : '',
            'end_date' => $db4->Record["end_date"] ? norm_sql_date($db4->Record["end_date"]) : '',
            'total_days' => $db4->Record["total_days"],
            'status' => $status
        );
    }

    // Task Report
    if ($task_report) {
        $sd_ar = parse_date(array("YYYY", "-", "MM", "-", "DD"), $start_date, "Start Date");
        $sd_ts = mktime(0, 0, 0, $sd_ar[1], $sd_ar[2], $sd_ar[0]);
        $sdt = @date("Y-m-d 00:00:00", $sd_ts);
        
        $ed_ar = parse_date(array("YYYY", "-", "MM", "-", "DD"), $end_date, "End Date");
        $ed_ts = mktime(0, 0, 0, $ed_ar[1], $ed_ar[2], $ed_ar[0]);
        $edt = @date("Y-m-d 23:59:59", $ed_ts);

        $sql = " SELECT tr.report_id, DATE_FORMAT(tr.started_date, '%d %b %Y - %W') AS date, ";
        $sql .= " t.task_title, tr.task_id, tr.spent_hours, ";
        $sql .= " UNIX_TIMESTAMP(tr.started_date) as start_time_u, UNIX_TIMESTAMP(tr.report_date) as end_time_u, ";
        $sql .= " DATE_FORMAT(tr.started_date, '%d %b %Y') AS rdate ";
        $sql .= " FROM time_report tr, tasks t ";
        $sql .= " WHERE t.task_id=tr.task_id AND tr.user_id=$user_id ";
        $sql .= " AND tr.started_date>='$sdt' AND tr.started_date<='$edt' ";
        $sql .= " ORDER BY tr.started_date";
        $db->query($sql);

        $tmp_date = "";
        $day_hours = 0;
        while ($db->next_record()) {
            $date = $db->f("date");
            $start_time_u = $db->f("start_time_u");
            $end_time_u = $db->f("end_time_u");
            $start_time = date("H:i", $start_time_u);
            $end_time = date("H:i", $end_time_u);
            // If end time is on a different day, append the date
            if (date("Y-m-d", $start_time_u) !== date("Y-m-d", $end_time_u)) {
                $end_time .= ' (' . date("d M", $end_time_u) . ')';
            }
            $spent = $db->f("spent_hours");
            
            $is_new_day = ($tmp_date != $date && $tmp_date != "");
            
            if ($is_new_day) {
                $task_reports[] = array(
                    'type' => 'day_total',
                    'hours' => Hours2HoursMins($day_hours)
                );
                $day_hours = 0;
            }
            
            if ($tmp_date != $date) {
                $task_reports[] = array(
                    'type' => 'date_header',
                    'date' => $date
                );
            }
            
            $task_reports[] = array(
                'type' => 'task',
                'task_id' => $db->f("task_id"),
                'task_title' => $db->f("task_title"),
                'time_period' => $start_time . " - " . $end_time,
                'spent_hours' => Hours2HoursMins($spent)
            );
            
            $day_hours += $spent;
            $tmp_date = $date;
        }
        
        if ($day_hours > 0) {
            $task_reports[] = array(
                'type' => 'day_total',
                'hours' => Hours2HoursMins($day_hours)
            );
        }
    }

    // Bugs
    $sql = 'SELECT t.task_title, CONCAT(u2.first_name, \' \', u2.last_name) as creator,
            CONCAT(u3.first_name, \' \', u3.last_name) as closer,
            DATE_FORMAT(b.date_issued, \'%d %b %Y\') AS date_issued,
            DATE_FORMAT(b.date_resolved, \'%d %b %Y\') AS date_resolved,
            b.is_resolved, b.is_declined
            FROM bugs b JOIN users u ON b.user_id = u.user_id
            JOIN users u2 ON b.issued_user_id = u2.user_id 
            LEFT JOIN users u3 ON b.resolved_user_id = u3.user_id 
            JOIN tasks t ON t.task_id = b.task_id
            WHERE b.user_id = ' . $user_id;
    $sql .= " AND DATE_FORMAT(b.date_issued, '%Y') = '$year_selected'";
    $sql .= " AND DATE_FORMAT(b.date_issued, '%m') = '$month_selected'";
    $sql .= " ORDER BY b.date_issued DESC";
    $db2->query($sql);
    while ($db2->next_record()) {
        $bugs[] = array(
            'task_title' => $db2->f("task_title"),
            'creator' => $db2->f("creator"),
            'closer' => $db2->f("closer"),
            'date_issued' => $db2->f("date_issued"),
            'date_resolved' => $db2->f("date_resolved"),
            'is_resolved' => $db2->f("is_resolved"),
            'is_declined' => $db2->f("is_declined")
        );
    }
}

// Holiday Summary
$today = getdate();
$today_year = $today["year"];
$sql = "SELECT start_date, user_id, CONCAT(first_name, ' ', last_name) AS user_name FROM users WHERE is_viart=1 AND is_deleted IS NULL AND user_id=" . ToSQL($user_id, "integer");
$db->query($sql);
if ($db->next_record()) {
    $holiday_summary = array(
        'user_name' => $db->f("user_name"),
        'work_start' => $db->f("start_date"),
        'total_holidays' => 0,
        'used_holidays' => 0,
        'avail_holidays' => 0,
        'total_holidays_year' => 0,
        'used_holidays_year' => 0
    );
    
    $sql2 = "SELECT SUM(total_days) AS used_holidays FROM days_off WHERE reason_id = 1 AND is_paid=0 AND user_id=" . ToSQL($user_id, "integer");
    $db2->query($sql2);
    if ($db2->next_record()) {
        $holiday_summary['used_holidays'] = $db2->f("used_holidays") ?: 0;
    }
    
    $sql2 = "SELECT SUM(total_days) AS used_holidays FROM days_off WHERE reason_id = 1 AND is_paid=0 AND user_id=" . ToSQL($user_id, "integer");
    $sql2 .= " AND DATE_FORMAT(start_date, '%Y')='$today_year'";
    $db2->query($sql2);
    if ($db2->next_record()) {
        $holiday_summary['used_holidays_year'] = $db2->f("used_holidays") ?: 0;
    }
    
    $sql2 = "SELECT SUM(days_number) AS total_holidays FROM holidays WHERE user_id=" . ToSQL($user_id, "integer");
    $db2->query($sql2);
    if ($db2->next_record()) {
        $holiday_summary['total_holidays'] = floor($db2->f("total_holidays") ?: 0);
    }
    
    $sql2 = "SELECT SUM(days_number) AS total_holidays FROM holidays WHERE user_id=" . ToSQL($user_id, "integer");
    $sql2 .= " AND DATE_FORMAT(date_added, '%Y')='$today_year'";
    $db2->query($sql2);
    if ($db2->next_record()) {
        $holiday_summary['total_holidays_year'] = floor($db2->f("total_holidays") ?: 0);
    }
    
    $avail = $holiday_summary['total_holidays'] - $holiday_summary['used_holidays'];
    $holiday_summary['avail_holidays'] = min($avail, 31); // Cap at 31 days
    
    // Format start date
    if ($holiday_summary['work_start']) {
        $start_ts = strtotime($holiday_summary['work_start']);
        $holiday_summary['work_start_formatted'] = date('j M Y', $start_ts);
    } else {
        $holiday_summary['work_start_formatted'] = '-';
    }
}

// Inventory
$sql = "SELECT i.inventory_id, i.inventory_title, i.inventory_desc
        FROM inventory i LEFT JOIN inventory_users iu ON i.inventory_id=iu.inventory_id
        WHERE iu.user_id=" . ToSQL($user_id, "integer");
$db->query($sql);
while ($db->next_record()) {
    $inv_item = array(
        'id' => $db->Record["inventory_id"],
        'title' => $db->Record["inventory_title"],
        'description' => $db->Record["inventory_desc"],
        'properties' => array()
    );
    
    $sql2 = "SELECT inventory_property_name, inventory_property_desc, inventory_property_value
             FROM inventory_properties WHERE inventory_id=" . ToSQL($db->Record["inventory_id"], "integer");
    $db2->query($sql2);
    while ($db2->next_record()) {
        $inv_item['properties'][] = array(
            'name' => $db2->Record["inventory_property_name"],
            'description' => $db2->Record["inventory_property_desc"],
            'value' => $db2->Record["inventory_property_value"]
        );
    }
    
    $inventory[] = $inv_item;
}

$month_name = date("F", mktime(0, 0, 0, $month_selected, 1, $year_selected));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Stats - Control</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="site.css" type="text/css"/>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body.PageBODY {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f0f4f8;
            min-height: 100vh;
            color: #2d3748;
        }

        .stats-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 32px 24px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 4px;
        }

        .page-subtitle {
            color: #718096;
            font-size: 0.9rem;
        }

        .btn {
            padding: 10px 18px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #2c5aa0 0%, #1e3a5f 100%);
            color: white;
            box-shadow: 0 4px 14px rgba(44, 90, 160, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(44, 90, 160, 0.4);
            color: white;
            text-decoration: none;
        }

        .btn-secondary {
            background: white;
            border: 2px solid #e2e8f0;
            color: #4a5568;
        }

        .btn-secondary:hover {
            background: #f7fafc;
            border-color: #cbd5e0;
            text-decoration: none;
        }

        .quick-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* Filter bar */
        .filters-bar {
            background: white;
            border-radius: 10px;
            padding: 16px 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            display: flex;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-group label {
            font-weight: 500;
            color: #4a5568;
            font-size: 0.85rem;
        }

        .filter-select {
            padding: 10px 32px 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.85rem;
            font-family: inherit;
            background: #f8fafc url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23718096' d='M6 8L2 4h8z'/%3E%3C/svg%3E") no-repeat right 12px center;
            cursor: pointer;
            appearance: none;
            min-width: 140px;
        }

        .filter-select:focus {
            outline: none;
            border-color: #2c5aa0;
        }

        .filter-actions {
            display: flex;
            gap: 8px;
            margin-left: auto;
        }

        /* Cards */
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .card-header {
            background: #f7fafc;
            padding: 14px 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: #1a202c;
        }

        .card-body {
            padding: 20px;
        }

        /* Grid layout */
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 900px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Summary table */
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        .summary-table th,
        .summary-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #edf2f7;
        }

        .summary-table th {
            font-weight: 600;
            color: #4a5568;
            background: #f7fafc;
        }

        .summary-table .label-col {
            font-weight: 500;
            color: #4a5568;
            width: 140px;
        }

        .summary-table .value-col {
            font-weight: 600;
            color: #1a202c;
        }

        .summary-table .norm-col {
            color: #718096;
            font-size: 0.8rem;
        }

        /* Calendar */
        .calendar-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }

        .calendar-table th {
            padding: 10px 8px;
            text-align: center;
            font-weight: 600;
            color: white;
            background: linear-gradient(135deg, #2c5aa0 0%, #1e3a5f 100%);
        }

        .calendar-table td {
            padding: 8px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }

        .calendar-table .day-cell {
            width: 36px;
            height: 36px;
            font-weight: 500;
        }

        .calendar-table .day-cell a {
            color: #2d3748;
            text-decoration: none;
            display: block;
        }

        .calendar-table .day-cell a:hover {
            color: #2c5aa0;
        }

        .calendar-table .totals-col {
            font-size: 0.75rem;
            color: #4a5568;
        }

        .calendar-legend {
            margin-top: 12px;
            font-size: 0.75rem;
            color: #718096;
        }
        .calendar-legend .legend-swatch {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 2px;
            margin-right: 4px;
            vertical-align: middle;
        }
        .calendar-legend .legend-worked { background: #dbeafe; }
        .calendar-legend .legend-holiday { background: #fef3c7; margin-left: 12px; }
        .calendar-legend .legend-weekend { background: #dcfce7; margin-left: 12px; }

        /* Data tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        .data-table thead {
            background: #f7fafc;
        }

        .data-table th {
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            color: #4a5568;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            border-bottom: 2px solid #e2e8f0;
        }

        .data-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #edf2f7;
            vertical-align: middle;
        }

        .data-table tbody tr:hover {
            background: #f7fafc;
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        .data-table a {
            color: #2c5aa0;
            text-decoration: none;
        }

        .data-table a:hover {
            text-decoration: underline;
        }

        /* Status badges */
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .badge-success {
            background: #c6f6d5;
            color: #22543d;
        }

        .badge-danger {
            background: #fed7d7;
            color: #822727;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-pending {
            background: #e0e7ff;
            color: #3730a3;
        }

        .btn-edit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            background: #f1f5f9;
            color: #475569;
            text-decoration: none;
            font-size: 1rem;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }

        .btn-edit:hover {
            background: #e2e8f0;
            color: #1e293b;
        }

        /* Task report */
        .task-date-header {
            background: #f7fafc;
            padding: 10px 16px;
            font-weight: 600;
            color: #2d3748;
            border-bottom: 1px solid #e2e8f0;
        }

        .task-day-total {
            background: #ebf4ff;
            padding: 8px 16px;
            text-align: right;
            font-weight: 600;
            color: #2c5aa0;
            font-size: 0.85rem;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #718096;
        }

        .empty-state .icon {
            font-size: 2.5rem;
            margin-bottom: 12px;
            opacity: 0.5;
        }

        .empty-state p {
            font-size: 0.9rem;
        }

        /* Stat cards */
        .stat-card {
            background: #f7fafc;
            border-radius: 8px;
            padding: 16px;
            text-align: center;
        }

        .stat-card .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2c5aa0;
        }

        .stat-card .stat-label {
            font-size: 0.75rem;
            color: #718096;
            margin-top: 4px;
        }

        .stat-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        @media (max-width: 600px) {
            .stat-row {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Inventory */
        .inventory-item {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 12px;
            overflow: hidden;
        }

        .inventory-header {
            background: #f7fafc;
            padding: 12px 16px;
            font-weight: 600;
            color: #2d3748;
        }

        .inventory-body {
            padding: 12px 16px;
        }

        .inventory-property {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #edf2f7;
            font-size: 0.85rem;
        }

        .inventory-property:last-child {
            border-bottom: none;
        }

        .inventory-property .prop-name {
            color: #4a5568;
        }

        .inventory-property .prop-value {
            font-weight: 500;
            color: #2d3748;
        }

        /* Dark mode */
        html.dark-mode .page-title { color: #e2e8f0; }
        html.dark-mode .page-subtitle { color: #a0aec0; }
        html.dark-mode .filters-bar {
            background: #161b22;
            box-shadow: 0 1px 3px rgba(0,0,0,0.5);
            border: 1px solid #2d333b;
        }
        html.dark-mode .filter-group label { color: #cbd5e0; }
        html.dark-mode .filter-select {
            background: #1c2333 url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%238b949e' d='M6 8L2 4h8z'/%3E%3C/svg%3E") no-repeat right 12px center !important;
            color: #e2e8f0 !important;
            border-color: #2d333b !important;
        }
        html.dark-mode .filter-select:focus { border-color: #667eea !important; }
        html.dark-mode .btn-secondary {
            background: #1c2333;
            color: #e2e8f0;
            border-color: #2d333b;
        }
        html.dark-mode .btn-secondary:hover { background: #2d333b; }
        html.dark-mode .stat-card {
            background: #161b22;
            border: 1px solid #2d333b;
        }
        html.dark-mode .stat-card .stat-value { color: #90cdf4; }
        html.dark-mode .stat-card .stat-label { color: #8b949e; }
        html.dark-mode .card {
            background: #161b22;
            box-shadow: 0 1px 3px rgba(0,0,0,0.5);
            border: 1px solid #2d333b;
        }
        html.dark-mode .card-header {
            background: #1c2333;
            border-bottom-color: #2d333b;
        }
        html.dark-mode .card-title { color: #e2e8f0; }
        html.dark-mode .card-body { color: #e2e8f0; }
        html.dark-mode .summary-table th {
            background: #1c2333;
            color: #a0aec0;
            border-bottom-color: #2d333b;
        }
        html.dark-mode .summary-table td { border-bottom-color: #2d333b; }
        html.dark-mode .summary-table .label-col { color: #a0aec0; }
        html.dark-mode .summary-table .value-col { color: #e2e8f0; }
        html.dark-mode .summary-table .norm-col { color: #8b949e; }
        html.dark-mode .calendar-table td {
            border-color: #2d333b;
            background: #1c2333 !important;
        }
        html.dark-mode .calendar-table .day-worked {
            background: rgba(59, 130, 246, 0.35) !important;
        }
        html.dark-mode .calendar-table .day-worked a { color: #93c5fd !important; }
        html.dark-mode .calendar-table .day-holiday {
            background: rgba(245, 158, 11, 0.35) !important;
        }
        html.dark-mode .calendar-table .day-holiday a { color: #fde68a !important; }
        html.dark-mode .calendar-table .day-weekend {
            background: rgba(34, 197, 94, 0.25) !important;
        }
        html.dark-mode .calendar-table .day-weekend a { color: #86efac !important; }
        html.dark-mode .calendar-table .day-empty {
            background: #1c2333 !important;
        }
        html.dark-mode .calendar-legend { color: #8b949e; }
        html.dark-mode .calendar-legend .legend-worked { background: rgba(59, 130, 246, 0.5); }
        html.dark-mode .calendar-legend .legend-holiday { background: rgba(245, 158, 11, 0.5); }
        html.dark-mode .calendar-legend .legend-weekend { background: rgba(34, 197, 94, 0.35); }
        html.dark-mode .calendar-table .day-cell a { color: #e2e8f0; }
        html.dark-mode .calendar-table .day-cell a:hover { color: #90cdf4; }
        html.dark-mode .calendar-table .totals-col { color: #8b949e; }
        html.dark-mode .calendar-table .calendar-total-row td { color: #a0aec0 !important; }
        html.dark-mode .calendar-table .calendar-total-row .totals-col { color: #90cdf4 !important; font-weight: 700; }
        html.dark-mode .data-table thead { background: #1c2333; }
        html.dark-mode .data-table th {
            color: #a0aec0;
            border-bottom-color: #2d333b;
        }
        html.dark-mode .data-table td {
            color: #e2e8f0;
            border-bottom-color: #2d333b;
        }
        html.dark-mode .data-table tbody tr:hover { background: #1c2333; }
        html.dark-mode .data-table a { color: #90cdf4; }
        html.dark-mode .badge-success {
            background: rgba(34, 84, 61, 0.5);
            color: #9ae6b4;
        }
        html.dark-mode .badge-danger {
            background: rgba(130, 39, 39, 0.5);
            color: #feb2b2;
        }
        html.dark-mode .badge-warning {
            background: rgba(146, 64, 14, 0.4);
            color: #fde68a;
        }
        html.dark-mode .badge-pending {
            background: rgba(55, 48, 163, 0.3);
            color: #c3dafe;
        }
        html.dark-mode .btn-edit {
            background: #1c2333;
            color: #a0aec0;
        }
        html.dark-mode .btn-edit:hover {
            background: #2d333b;
            color: #e2e8f0;
        }
        html.dark-mode .task-date-header {
            background: #1c2333;
            color: #e2e8f0;
            border-bottom-color: #2d333b;
        }
        html.dark-mode .task-day-total {
            background: rgba(44, 90, 160, 0.2);
            color: #90cdf4;
        }
        html.dark-mode .empty-state { color: #8b949e; }
        html.dark-mode .inventory-item { border-color: #2d333b; }
        html.dark-mode .inventory-header {
            background: #1c2333;
            color: #e2e8f0;
        }
        html.dark-mode .inventory-body { color: #e2e8f0; }
        html.dark-mode .inventory-property { border-bottom-color: #2d333b; }
        html.dark-mode .inventory-property .prop-name { color: #a0aec0; }
        html.dark-mode .inventory-property .prop-value { color: #e2e8f0; }
    </style>
</head>
<body class="PageBODY">
    <?php include("./includes/modern_header.php"); ?>

<div class="stats-container">
    <div class="page-header">
        <div>
            <h1 class="page-title">My Stats</h1>
            <p class="page-subtitle"><?php echo htmlspecialchars($user_name); ?> - <?php echo $month_name; ?> <?php echo $year_selected; ?></p>
        </div>
        <div class="quick-actions">
            <button type="button" class="btn btn-primary" onclick="openHolidayModal(1)">Apply for Holiday</button>
        </div>
    </div>
    
    <form name="frmFilter" action="my_stats.php" method="get">
        <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
        <div class="filters-bar">
            <div class="filter-group">
                <label for="year_selected">Year:</label>
                <select name="year_selected" id="year_selected" class="filter-select">
                    <option value="">-- Year --</option>
                    <?php echo GetYearOptions(2004, date("Y"), $year_selected); ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="month_selected">Month:</label>
                <select name="month_selected" id="month_selected" class="filter-select">
                    <option value="">-- Month --</option>
                    <?php echo GetMonthOptions($month_selected); ?>
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Filter</button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('year_selected').value='';document.getElementById('month_selected').value='';">Clear</button>
            </div>
        </div>
    </form>

    <?php if ($summary_data): ?>
    <!-- Stats Overview -->
    <div class="stat-row">
        <div class="stat-card">
            <div class="stat-value"><?php echo $summary_data['spent_hours']; ?></div>
            <div class="stat-label">Hours Worked</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $summary_data['tasks']; ?></div>
            <div class="stat-label">Tasks Completed</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $summary_data['working_days']; ?></div>
            <div class="stat-label">Working Days</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $summary_data['hours_per_day']; ?></div>
            <div class="stat-label">Avg Hours/Day</div>
        </div>
    </div>

    <div class="stats-grid">
        <!-- Monthly Summary -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">Monthly Summary</span>
            </div>
            <div class="card-body">
                <table class="summary-table">
                    <tr>
                        <td class="label-col">Metric</td>
                        <td class="value-col">Actual</td>
                        <td class="norm-col">Target</td>
                    </tr>
                    <tr>
                        <td class="label-col">Hours Logged</td>
                        <td class="value-col"><?php echo $summary_data['spent_hours']; ?></td>
                        <td class="norm-col"><?php echo $summary_data['average_hours']; ?></td>
                    </tr>
                    <tr>
                        <td class="label-col">Tasks Completed</td>
                        <td class="value-col"><a href="./tasks_report.php?year_selected=<?php echo $year_selected; ?>&month_selected=<?php echo $month_selected; ?>&person_selected=<?php echo $user_id; ?>"><?php echo $summary_data['tasks']; ?></a></td>
                        <td class="norm-col"><?php echo $summary_data['average_tasks']; ?></td>
                    </tr>
                    <tr>
                        <td class="label-col">Avg Time/Task</td>
                        <td class="value-col"><?php echo $summary_data['time_per_task']; ?></td>
                        <td class="norm-col"><?php echo $summary_data['average_time_per_task']; ?></td>
                    </tr>
                    <tr>
                        <td class="label-col">Avg Hours/Day</td>
                        <td class="value-col"><?php echo $summary_data['hours_per_day']; ?></td>
                        <td class="norm-col"><?php echo $summary_data['average_hours_per_day']; ?></td>
                    </tr>
                    <tr>
                        <td class="label-col">Days Worked</td>
                        <td class="value-col"><?php echo $summary_data['working_days']; ?></td>
                        <td class="norm-col"><?php echo $summary_data['average_working_days']; ?></td>
                    </tr>
                    <tr>
                        <td class="label-col">Absences</td>
                        <td class="value-col"><?php echo $summary_data['days_off']; ?></td>
                        <td class="norm-col"><?php echo $summary_data['average_days_off']; ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Calendar -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">Attendance - <?php echo $month_name; ?> <?php echo $year_selected; ?></span>
            </div>
            <div class="card-body">
                <?php if (!empty($calendar_data)): ?>
                <table class="calendar-table">
                    <thead>
                        <tr>
                            <th>M</th>
                            <th>T</th>
                            <th>W</th>
                            <th>T</th>
                            <th>F</th>
                            <th>S</th>
                            <th>S</th>
                            <th>Hours</th>
                            <th>Avg</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($calendar_data as $week): ?>
                        <tr>
                            <?php foreach ($week['days'] as $day): ?>
                            <td class="day-cell day-<?php echo htmlspecialchars($day['type']); ?>" style="background-color: <?php echo $day['color']; ?>">
                                <?php if ($day['day']): ?>
                                    <a href="<?php echo $day['link']; ?>"><?php echo $day['day']; ?></a>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                            <td class="totals-col"><?php echo $week['week_hours']; ?></td>
                            <td class="totals-col"><?php echo $week['w_hours_per_day']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="calendar-total-row">
                            <td colspan="7" style="text-align: right; font-weight: 600; color: #4a5568;">Total:</td>
                            <td class="totals-col" style="font-weight: 700; color: #2c5aa0;"><?php echo Hours2HoursMins($month_hours); ?></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
                <div class="calendar-legend">
                    <span class="legend-swatch legend-worked"></span> Worked
                    <span class="legend-swatch legend-holiday"></span> Holiday
                    <span class="legend-swatch legend-weekend"></span> Weekend
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <div class="icon">&#128197;</div>
                    <p>No working days for this period</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <div class="icon">&#128202;</div>
                <p>No data for this period. Select a year and month to view your stats.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($holiday_summary): ?>
    <!-- Holiday Allowance -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Holiday Allowance</span>
            <a href="view_vacations.php?show_user_id=<?php echo $user_id; ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 0.8rem;">View All</a>
        </div>
        <div class="card-body">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Total Allowance</th>
                        <th>Days Used</th>
                        <th>Remaining</th>
                        <th>Earned <?php echo $today_year; ?></th>
                        <th>Used <?php echo $today_year; ?></th>
                        <th>Employment Start</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php echo $holiday_summary['total_holidays']; ?></td>
                        <td><?php echo $holiday_summary['used_holidays']; ?></td>
                        <td><strong><?php echo $holiday_summary['avail_holidays']; ?></strong><?php echo $holiday_summary['avail_holidays'] >= 31 ? ' <span style="color:#718096;font-size:0.75rem;">(max)</span>' : ''; ?></td>
                        <td><?php echo $holiday_summary['total_holidays_year']; ?></td>
                        <td><?php echo $holiday_summary['used_holidays_year']; ?></td>
                        <td><?php echo $holiday_summary['work_start_formatted']; ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Time Off Requests -->
    <div class="card" id="timeOffCard">
        <div class="card-header">
            <span class="card-title">Time Off This Year (<?php echo $year_selected; ?>)</span>
        </div>
        <?php if (!empty($vacations)): ?>
        <table class="data-table" id="timeOffTable">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Reason</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Days</th>
                    <th>Status</th>
                    <th style="width: 60px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vacations as $vac): ?>
                <tr data-period-id="<?php echo $vac['period_id']; ?>">
                    <td><?php echo htmlspecialchars($vac['period_title']); ?></td>
                    <td><?php echo htmlspecialchars($vac['reason_type']); ?></td>
                    <td><?php echo htmlspecialchars($vac['start_date']); ?></td>
                    <td><?php echo htmlspecialchars($vac['end_date']); ?></td>
                    <td><?php echo $vac['total_days']; ?></td>
                    <td>
                        <?php if ($vac['status'] == 'approved'): ?>
                            <span class="badge badge-success">Approved</span>
                        <?php elseif ($vac['status'] == 'declined'): ?>
                            <span class="badge badge-danger">Declined</span>
                        <?php else: ?>
                            <span class="badge badge-pending">Pending</span>
                        <?php endif; ?>
                    </td>
                    <td><button type="button" class="btn-edit" onclick="openHolidayModal(1, <?php echo $vac['period_id']; ?>)" title="Edit">&#9998;</button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="card-body" id="timeOffEmpty">
            <div class="empty-state">
                <p>No time off recorded this year</p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($task_report && !empty($task_reports)): ?>
    <!-- Time Log Details -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Time Log Details</span>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Task</th>
                    <th style="text-align: right;">Duration</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($task_reports as $item): ?>
                    <?php if ($item['type'] == 'date_header'): ?>
                    <tr>
                        <td colspan="3" class="task-date-header"><?php echo htmlspecialchars($item['date']); ?></td>
                    </tr>
                    <?php elseif ($item['type'] == 'day_total'): ?>
                    <tr>
                        <td colspan="3" class="task-day-total">Day Total: <?php echo $item['hours']; ?></td>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['time_period']); ?></td>
                        <td><a href="./edit_task.php?task_id=<?php echo $item['task_id']; ?>"><?php echo htmlspecialchars($item['task_title']); ?></a></td>
                        <td style="text-align: right;"><?php echo $item['spent_hours']; ?></td>
                    </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if (!empty($warnings)): ?>
    <!-- Warnings -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Warnings</span>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Issued By</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($warnings as $warning): ?>
                <tr>
                    <td><?php echo htmlspecialchars($warning['date_added']); ?></td>
                    <td><?php echo htmlspecialchars($warning['creator']); ?></td>
                    <td><?php echo htmlspecialchars($warning['notes']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Holiday Application Modal -->
<div class="modal-overlay" id="holidayModal">
    <div class="modal">
        <div class="modal-header">
            <h2 id="holidayModalTitle">Apply for Holiday</h2>
            <button class="modal-close" onclick="closeHolidayModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="holidayForm">
                <input type="hidden" id="holiday_type" name="holiday_type" value="1">
                <input type="hidden" id="holiday_period_id" name="period_id" value="">
                
                <div class="form-group">
                    <label for="holiday_title">Title <span class="required">*</span></label>
                    <input type="text" id="holiday_title" name="title" class="form-input" placeholder="e.g., Summer Vacation" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="holiday_start">Start Date <span class="required">*</span></label>
                        <input type="date" id="holiday_start" name="start_date" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="holiday_end">End Date <span class="required">*</span></label>
                        <input type="date" id="holiday_end" name="end_date" class="form-input" required>
                    </div>
                    <div class="form-group" style="max-width: 100px;">
                        <label for="holiday_days">Days</label>
                        <input type="number" id="holiday_days" name="total_days" class="form-input" readonly>
                    </div>
                </div>
                
                <div class="overlap-warning" id="overlapWarning" style="display: none;">
                    <div class="overlap-header">
                        <span class="overlap-icon">&#9888;</span>
                        <span>Note: Others on leave during this period</span>
                    </div>
                    <div class="overlap-list" id="overlapList"></div>
                    <div class="overlap-note">You can still submit your application.</div>
                </div>
                
                <div class="form-group" id="reasonGroup">
                    <label>Leave Type <span class="required">*</span></label>
                    <div class="custom-select" id="reasonSelect">
                        <div class="custom-select-trigger" id="reasonTrigger">
                            <span id="reasonText">Select reason...</span>
                            <span class="custom-select-arrow">&#9662;</span>
                        </div>
                        <div class="custom-select-options" id="reasonOptions">
                            <!-- Populated by JS -->
                        </div>
                    </div>
                    <input type="hidden" id="holiday_reason" name="reason_id" value="">
                </div>
                
                <div class="form-group sick-note-group" id="sickNoteGroup" style="display: none;">
                    <label class="checkbox-label">
                        <input type="checkbox" id="sick_note_checkbox">
                        <span class="checkbox-text">I have a sick note from the doctor</span>
                    </label>
                    <p class="field-hint">A medical certificate is required for illness-related leave.</p>
                </div>
                
                <div class="form-group">
                    <label for="holiday_notes">Notes</label>
                    <textarea id="holiday_notes" name="notes" class="form-input" rows="3" placeholder="Optional notes..."></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeHolidayModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="submitHolidayForm()" id="submitHolidayBtn">Submit Application</button>
        </div>
    </div>
</div>

<style>
    /* Modal styles */
    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .modal-overlay.active {
        display: flex;
    }

    .modal {
        background: white;
        border-radius: 10px;
        max-width: 550px;
        width: 100%;
        max-height: 90vh;
        overflow: hidden;
        box-shadow: 0 25px 80px rgba(0,0,0,0.3);
    }

    .modal-header {
        background: linear-gradient(135deg, #2c5aa0 0%, #1e3a5f 100%);
        color: white;
        padding: 18px 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-header h2 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 600;
    }

    .modal-close {
        background: none;
        border: none;
        color: white;
        font-size: 24px;
        cursor: pointer;
        opacity: 0.8;
        line-height: 1;
        padding: 0;
    }

    .modal-close:hover {
        opacity: 1;
    }

    .modal-body {
        padding: 24px;
        max-height: 60vh;
        overflow-y: auto;
    }

    .modal-footer {
        padding: 16px 24px;
        background: #f7fafc;
        border-top: 1px solid #e2e8f0;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    .form-group {
        margin-bottom: 16px;
    }

    .form-group label {
        display: block;
        margin-bottom: 6px;
        font-weight: 500;
        color: #4a5568;
        font-size: 0.85rem;
    }

    .form-group .required {
        color: #e53e3e;
    }

    .form-input {
        width: 100%;
        padding: 10px 12px;
        border: 2px solid #e2e8f0;
        border-radius: 6px;
        font-size: 0.9rem;
        font-family: inherit;
        transition: border-color 0.2s;
    }

    .form-input:focus {
        outline: none;
        border-color: #2c5aa0;
        box-shadow: 0 0 0 3px rgba(44, 90, 160, 0.1);
    }

    .form-input:read-only {
        background: #f7fafc;
        color: #4a5568;
    }

    .form-row {
        display: flex;
        gap: 12px;
    }

    .form-row .form-group {
        flex: 1;
    }

    textarea.form-input {
        resize: vertical;
        min-height: 80px;
    }

    .overlap-warning {
        background: #fef3c7;
        border: 1px solid #f59e0b;
        border-radius: 8px;
        padding: 12px 16px;
        margin-bottom: 16px;
    }

    .overlap-header {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 600;
        color: #92400e;
        font-size: 0.85rem;
        margin-bottom: 8px;
    }

    .overlap-icon {
        font-size: 1.1rem;
    }

    .overlap-list {
        font-size: 0.8rem;
        color: #78350f;
    }

    .overlap-item {
        padding: 6px 0;
        border-bottom: 1px solid rgba(245, 158, 11, 0.3);
    }

    .overlap-item:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }

    .overlap-item .user-name {
        font-weight: 600;
    }

    .overlap-item .dates {
        color: #a16207;
    }

    .overlap-note {
        margin-top: 8px;
        font-size: 0.75rem;
        color: #92400e;
        font-style: italic;
    }

    /* Custom Select */
    .custom-select {
        position: relative;
        width: 100%;
    }

    .custom-select-trigger {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 12px;
        border: 2px solid #e2e8f0;
        border-radius: 6px;
        background: white;
        cursor: pointer;
        font-size: 0.9rem;
        transition: border-color 0.2s;
    }

    .custom-select-trigger:hover {
        border-color: #cbd5e0;
    }

    .custom-select.open .custom-select-trigger {
        border-color: #2c5aa0;
        box-shadow: 0 0 0 3px rgba(44, 90, 160, 0.1);
    }

    .custom-select-arrow {
        color: #718096;
        font-size: 0.8rem;
        transition: transform 0.2s;
    }

    .custom-select.open .custom-select-arrow {
        transform: rotate(180deg);
    }

    .custom-select-options {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 2px solid #e2e8f0;
        border-radius: 6px;
        margin-top: 4px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.12);
        z-index: 100;
        display: none;
        max-height: 200px;
        overflow-y: auto;
    }

    .custom-select.open .custom-select-options {
        display: block;
    }

    .custom-select-option {
        padding: 10px 12px;
        cursor: pointer;
        font-size: 0.9rem;
        transition: background 0.1s;
    }

    .custom-select-option:hover {
        background: #f7fafc;
    }

    .custom-select-option.selected {
        background: #ebf4ff;
        color: #2c5aa0;
        font-weight: 500;
    }

    .custom-select-option.hidden {
        display: none;
    }

    /* Sick note checkbox */
    .sick-note-group {
        background: #fef3c7;
        border: 1px solid #f59e0b;
        border-radius: 8px;
        padding: 14px 16px;
    }

    .checkbox-label {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        cursor: pointer;
        font-size: 0.9rem;
        color: #78350f;
    }

    .checkbox-label input[type="checkbox"] {
        width: 18px;
        height: 18px;
        margin-top: 2px;
        accent-color: #2c5aa0;
        cursor: pointer;
    }

    .checkbox-text {
        font-weight: 500;
    }

    .field-hint {
        margin: 8px 0 0 28px;
        font-size: 0.8rem;
        color: #92400e;
    }
</style>

<script>
const currentUserId = <?php echo $user_id; ?>;
let reasonsLoaded = false;
let allReasons = [];
let selectedReasonId = null;
let illnessReasonId = null;
let vacationReasonId = null;

function openHolidayModal(type, periodId = null) {
    const modal = document.getElementById('holidayModal');
    const title = document.getElementById('holidayModalTitle');
    const typeInput = document.getElementById('holiday_type');
    const periodIdInput = document.getElementById('holiday_period_id');
    const reasonGroup = document.getElementById('reasonGroup');
    const submitBtn = document.querySelector('.modal-footer .btn-primary');
    
    typeInput.value = type;
    periodIdInput.value = periodId || '';
    
    // Reset form first
    document.getElementById('holidayForm').reset();
    document.getElementById('overlapWarning').style.display = 'none';
    document.getElementById('sickNoteGroup').style.display = 'none';
    document.getElementById('sick_note_checkbox').checked = false;
    
    if (periodId) {
        // Edit mode - load existing data
        title.textContent = 'Edit Time Off Request';
        submitBtn.textContent = 'Save Changes';
        reasonGroup.style.display = 'block';
        
        // Load vacation data
        fetch(`ajax_responder.php?action=get_vacation&period_id=${periodId}`)
            .then(r => r.json())
            .then(data => {
                if (data.success && data.vacation) {
                    const vac = data.vacation;
                    document.getElementById('holiday_title').value = vac.period_title || '';
                    document.getElementById('holiday_start').value = vac.start_date || '';
                    document.getElementById('holiday_end').value = vac.end_date || '';
                    document.getElementById('holiday_notes').value = vac.notes || '';
                    
                    // Load reasons then select the correct one
                    if (!reasonsLoaded) {
                        loadReasons(() => {
                            selectReason(parseInt(vac.reason_id));
                            calculateDays();
                        });
                    } else {
                        selectReason(parseInt(vac.reason_id));
                        calculateDays();
                    }
                }
            })
            .catch(err => console.error('Failed to load vacation:', err));
        
        modal.classList.add('active');
    } else {
        // Create mode
        if (type === 1) {
            title.textContent = 'Apply for Holiday';
            reasonGroup.style.display = 'block';
        } else {
            title.textContent = 'Apply for Overwork';
            reasonGroup.style.display = 'none';
        }
        submitBtn.textContent = 'Submit Application';
        
        // Set default dates to today
        const today = new Date();
        document.getElementById('holiday_start').value = formatDateForInput(today);
        document.getElementById('holiday_end').value = formatDateForInput(today);
        
        // Load reasons if not loaded
        if (!reasonsLoaded) {
            loadReasons();
        } else {
            // Reset to vacation default
            selectReason(vacationReasonId);
            updateIllnessVisibility();
        }
        
        // Calculate initial days
        calculateDays();
        
        modal.classList.add('active');
    }
}

function closeHolidayModal() {
    document.getElementById('holidayModal').classList.remove('active');
}

function formatDateForInput(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

function formatDateDisplay(dateStr) {
    const date = new Date(dateStr);
    const options = { day: 'numeric', month: 'short', year: 'numeric' };
    return date.toLocaleDateString('en-GB', options);
}

function loadReasons(callback = null) {
    fetch('ajax_responder.php?action=get_vacation_reasons')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                allReasons = data.reasons;
                
                // Find illness and vacation reason IDs
                allReasons.forEach(r => {
                    const nameLower = r.name.toLowerCase();
                    if (nameLower.includes('illness') || nameLower.includes('sick')) {
                        illnessReasonId = parseInt(r.id);
                    }
                    if (nameLower.includes('vacation') || nameLower.includes('holiday')) {
                        vacationReasonId = parseInt(r.id);
                    }
                });
                
                // Default to first if vacation not found
                if (!vacationReasonId && allReasons.length > 0) {
                    vacationReasonId = allReasons[0].id;
                }
                
                renderReasonOptions();
                reasonsLoaded = true;
                
                if (callback) {
                    callback();
                } else {
                    selectReason(vacationReasonId);
                    updateIllnessVisibility();
                }
            }
        })
        .catch(err => console.error('Failed to load reasons:', err));
}

function renderReasonOptions() {
    const optionsContainer = document.getElementById('reasonOptions');
    const totalDays = parseInt(document.getElementById('holiday_days').value) || 0;
    
    optionsContainer.innerHTML = allReasons.map(r => {
        const isIllness = r.id === illnessReasonId;
        const hideIllness = isIllness && totalDays <= 2;
        return `<div class="custom-select-option ${hideIllness ? 'hidden' : ''}" 
                     data-value="${r.id}" 
                     data-name="${escapeHtml(r.name)}"
                     data-illness="${isIllness}"
                     onclick="selectReason(${r.id})">${escapeHtml(r.name)}</div>`;
    }).join('');
}

function selectReason(reasonId) {
    if (!reasonId) return;
    
    reasonId = parseInt(reasonId);
    const reason = allReasons.find(r => parseInt(r.id) === reasonId);
    if (!reason) return;
    
    selectedReasonId = reasonId;
    document.getElementById('holiday_reason').value = reasonId;
    document.getElementById('reasonText').textContent = reason.name;
    
    // Update selected state in options
    document.querySelectorAll('.custom-select-option').forEach(opt => {
        opt.classList.toggle('selected', parseInt(opt.dataset.value) === reasonId);
    });
    
    // Show/hide sick note checkbox
    const isIllness = reasonId === illnessReasonId;
    document.getElementById('sickNoteGroup').style.display = isIllness ? 'block' : 'none';
    
    // Close dropdown
    closeReasonDropdown();
}

function updateIllnessVisibility() {
    const totalDays = parseInt(document.getElementById('holiday_days').value) || 0;
    const illnessOption = document.querySelector(`.custom-select-option[data-illness="true"]`);
    
    if (illnessOption) {
        if (totalDays <= 2) {
            illnessOption.classList.add('hidden');
            // If illness was selected, switch to vacation
            if (selectedReasonId === illnessReasonId) {
                selectReason(vacationReasonId);
            }
        } else {
            illnessOption.classList.remove('hidden');
        }
    }
}

function toggleReasonDropdown() {
    const customSelect = document.getElementById('reasonSelect');
    customSelect.classList.toggle('open');
}

function closeReasonDropdown() {
    document.getElementById('reasonSelect').classList.remove('open');
}

// Custom select trigger click
document.getElementById('reasonTrigger').addEventListener('click', function(e) {
    e.stopPropagation();
    toggleReasonDropdown();
});

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.custom-select')) {
        closeReasonDropdown();
    }
});

function calculateDays() {
    const startDate = document.getElementById('holiday_start').value;
    const endDate = document.getElementById('holiday_end').value;
    
    if (!startDate || !endDate) return;
    
    fetch(`ajax_responder.php?action=calculate_working_days&start_date=${startDate}&end_date=${endDate}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('holiday_days').value = data.working_days;
                // Update illness visibility based on number of days
                updateIllnessVisibility();
            } else {
                document.getElementById('holiday_days').value = '';
            }
        })
        .catch(err => {
            console.error('Failed to calculate days:', err);
            document.getElementById('holiday_days').value = '';
        });
    
    // Check for overlaps
    checkOverlaps();
}

function checkOverlaps() {
    const startDate = document.getElementById('holiday_start').value;
    const endDate = document.getElementById('holiday_end').value;
    const periodId = document.getElementById('holiday_period_id').value;
    
    if (!startDate || !endDate) return;
    
    // Exclude only the specific period being edited, not all user's periods
    let url = `ajax_responder.php?action=check_vacation_overlap&start_date=${startDate}&end_date=${endDate}`;
    if (periodId) {
        url += `&exclude_period_id=${periodId}`;
    }
    
    fetch(url)
        .then(r => r.json())
        .then(data => {
            const warningDiv = document.getElementById('overlapWarning');
            const listDiv = document.getElementById('overlapList');
            
            if (data.success && data.has_overlaps) {
                listDiv.innerHTML = data.overlaps.map(o => `
                    <div class="overlap-item">
                        <span class="user-name">${escapeHtml(o.user_name)}${o.user_id == currentUserId ? ' (you)' : ''}</span>
                        <span class="dates">${formatDateDisplay(o.start_date)} - ${formatDateDisplay(o.end_date)}</span>
                        ${o.period_title ? `<span> (${escapeHtml(o.period_title)})</span>` : ''}
                    </div>
                `).join('');
                warningDiv.style.display = 'block';
            } else {
                warningDiv.style.display = 'none';
            }
        })
        .catch(err => {
            console.error('Failed to check overlaps:', err);
            document.getElementById('overlapWarning').style.display = 'none';
        });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function submitHolidayForm() {
    const title = document.getElementById('holiday_title').value.trim();
    const startDate = document.getElementById('holiday_start').value;
    const endDate = document.getElementById('holiday_end').value;
    const totalDays = document.getElementById('holiday_days').value;
    const reasonId = document.getElementById('holiday_reason').value;
    const notes = document.getElementById('holiday_notes').value.trim();
    const holidayType = document.getElementById('holiday_type').value;
    const sickNoteChecked = document.getElementById('sick_note_checkbox').checked;
    const periodId = document.getElementById('holiday_period_id').value;
    const isEditMode = !!periodId;
    
    if (!title) {
        alert('Please enter a title for your application');
        document.getElementById('holiday_title').focus();
        return;
    }
    
    if (!startDate || !endDate) {
        alert('Please select start and end dates');
        return;
    }
    
    if (new Date(endDate) < new Date(startDate)) {
        alert('End date must be after start date');
        return;
    }
    
    // Check if reason is selected (for holiday type only)
    if (holidayType == 1 && !reasonId) {
        alert('Please select a reason type');
        return;
    }
    
    // Check sick note for illness
    if (parseInt(reasonId) === illnessReasonId && !sickNoteChecked) {
        alert('Please confirm that you have a sick note from the doctor');
        document.getElementById('sick_note_checkbox').focus();
        return;
    }
    
    const submitBtn = document.getElementById('submitHolidayBtn');
    submitBtn.disabled = true;
    submitBtn.textContent = isEditMode ? 'Saving...' : 'Submitting...';
    
    const payload = {
        user_id: currentUserId,
        title: title,
        start_date: startDate,
        end_date: endDate,
        total_days: parseInt(totalDays) || 0,
        reason_id: parseInt(reasonId) || 1,
        notes: notes,
        is_paid: holidayType == 2 ? 1 : 0,
        has_sick_note: sickNoteChecked
    };
    
    if (isEditMode) {
        payload.period_id = parseInt(periodId);
    }
    
    const action = isEditMode ? 'update_vacation' : 'apply_vacation';
    
    fetch(`ajax_responder.php?action=${action}`, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            closeHolidayModal();
            
            if (isEditMode) {
                showToast('Time off request updated successfully!');
                // Update the existing row in the table
                updateTimeOffEntry({
                    period_id: periodId,
                    title: title,
                    reason: getSelectedReasonName(),
                    start_date: formatDateDisplay(startDate),
                    end_date: formatDateDisplay(endDate),
                    total_days: parseInt(totalDays) || 0
                });
            } else {
                showToast('Holiday application submitted successfully!');
                // Add the new entry to the Time Off table
                addTimeOffEntry({
                    period_id: data.period_id,
                    title: title,
                    reason: getSelectedReasonName(),
                    start_date: formatDateDisplay(startDate),
                    end_date: formatDateDisplay(endDate),
                    total_days: parseInt(totalDays) || 0,
                    status: 'pending'
                });
            }
            
            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit Application';
        } else {
            alert(data.error || 'Failed to submit application');
            submitBtn.disabled = false;
            submitBtn.textContent = isEditMode ? 'Save Changes' : 'Submit Application';
        }
    })
    .catch(err => {
        console.error('Failed to submit:', err);
        alert('Failed to submit application. Please try again.');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Submit Application';
    });
}

function getSelectedReasonName() {
    const reason = allReasons.find(r => r.id === selectedReasonId);
    return reason ? reason.name : 'Vacation';
}

function addTimeOffEntry(entry) {
    const card = document.getElementById('timeOffCard');
    const emptyState = document.getElementById('timeOffEmpty');
    let table = document.getElementById('timeOffTable');
    
    // If there's no table yet (empty state), create one
    if (!table) {
        if (emptyState) {
            emptyState.remove();
        }
        
        const tableHtml = `
            <table class="data-table" id="timeOffTable">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Reason</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Days</th>
                        <th>Status</th>
                        <th style="width: 60px;"></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        `;
        card.insertAdjacentHTML('beforeend', tableHtml);
        table = document.getElementById('timeOffTable');
    }
    
    const tbody = table.querySelector('tbody');
    
    // Create new row
    const newRow = document.createElement('tr');
    newRow.setAttribute('data-period-id', entry.period_id);
    newRow.style.background = '#f0fdf4'; // Light green highlight for new entry
    newRow.innerHTML = `
        <td>${escapeHtml(entry.title)}</td>
        <td>${escapeHtml(entry.reason)}</td>
        <td>${entry.start_date}</td>
        <td>${entry.end_date}</td>
        <td>${entry.total_days}</td>
        <td><span class="badge badge-pending">Pending</span></td>
        <td><button type="button" class="btn-edit" onclick="openHolidayModal(1, ${entry.period_id})" title="Edit">&#9998;</button></td>
    `;
    
    // Insert at the top of the table
    if (tbody.firstChild) {
        tbody.insertBefore(newRow, tbody.firstChild);
    } else {
        tbody.appendChild(newRow);
    }
    
    // Scroll to the table
    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
    
    // Remove highlight after a few seconds
    setTimeout(() => {
        newRow.style.background = '';
    }, 3000);
}

function updateTimeOffEntry(entry) {
    const table = document.getElementById('timeOffTable');
    if (!table) return;
    
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(row => {
        if (row.getAttribute('data-period-id') == entry.period_id) {
            row.style.background = '#fef3c7'; // Yellow highlight for updated entry
            const cells = row.querySelectorAll('td');
            if (cells.length >= 5) {
                cells[0].textContent = entry.title;
                cells[1].textContent = entry.reason;
                cells[2].textContent = entry.start_date;
                cells[3].textContent = entry.end_date;
                cells[4].textContent = entry.total_days;
            }
            
            // Remove highlight after a few seconds
            setTimeout(() => {
                row.style.background = '';
            }, 3000);
        }
    });
}

function showToast(message) {
    // Create toast if it doesn't exist
    let toast = document.getElementById('toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'toast';
        toast.className = 'toast';
        document.body.appendChild(toast);
    }
    
    toast.textContent = message;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
}

// Date change event listeners
document.getElementById('holiday_start').addEventListener('change', function() {
    // Auto-set end date to match start date
    const endDateInput = document.getElementById('holiday_end');
    endDateInput.value = this.value;
    endDateInput.min = this.value; // End date can't be before start
    calculateDays();
});
document.getElementById('holiday_end').addEventListener('change', calculateDays);

// Close modal on escape key
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeHolidayModal();
});

// Close modal on overlay click
document.getElementById('holidayModal').addEventListener('click', e => {
    if (e.target.classList.contains('modal-overlay')) closeHolidayModal();
});
</script>

<style>
    .toast {
        position: fixed;
        bottom: 20px;
        right: 20px;
        padding: 14px 24px;
        border-radius: 8px;
        color: white;
        font-size: 0.9rem;
        font-weight: 500;
        z-index: 2000;
        transform: translateY(100px);
        opacity: 0;
        transition: all 0.3s;
        background: #48bb78;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .toast.show {
        transform: translateY(0);
        opacity: 1;
    }
</style>

</body>
</html>
