<?php
include("./includes/date_functions.php");
include("./includes/common.php");

CheckSecurity(1);

if (getsessionparam("privilege_id") == 9) {
    header("Location: index.php");
    exit;
}

$current_user_id = (int) GetSessionParam("UserID");
// Only this admin may edit other people's time records from this page.
$can_edit_time_records = ($current_user_id === 3);

// Flash message for edit/delete feedback (survives PRG redirect).
$tr_flash = GetParam('tr_flash');
$tr_flash_msg = '';
if ($tr_flash === 'updated')      $tr_flash_msg = 'Time record updated.';
else if ($tr_flash === 'deleted') $tr_flash_msg = 'Time record deleted.';
else if ($tr_flash === 'invalid') $tr_flash_msg = 'Invalid input — nothing was changed.';

// --- Handle edit/delete actions (admin only). PRG pattern keeps the URL clean. ---
if ($can_edit_time_records && $_SERVER['REQUEST_METHOD'] === 'POST' && GetParam('tr_action')) {
    $tr_action = GetParam('tr_action');
    $edit_report_id = (int) GetParam('report_id');

    // Rebuild redirect URL preserving the existing filter.
    $preserved = array();
    foreach (array('period','start_date','end_date','team','person_selected','submit') as $k) {
        $v = GetParam($k);
        if ($v !== '' && $v !== null) $preserved[$k] = $v;
    }
    $redirect_to = function($flash) use ($preserved) {
        $preserved['tr_flash'] = $flash;
        header('Location: time_report.php?' . http_build_query($preserved));
        exit;
    };

    if ($edit_report_id > 0 && $tr_action === 'delete') {
        $sql = "DELETE FROM time_report WHERE report_id = " . ToSQL($edit_report_id, "integer");
        $db->query($sql, __FILE__, __LINE__);
        $redirect_to('deleted');
    }

    if ($edit_report_id > 0 && $tr_action === 'update_time') {
        $new_start_dt = GetParam('new_start_dt'); // "YYYY-MM-DDTHH:MM" (displayed/local time)
        $new_end_dt   = GetParam('new_end_dt');

        // Fetch the existing row so we know which shift (is_viart) to reapply.
        $sql = "SELECT u.is_viart FROM time_report tr JOIN users u ON u.user_id = tr.user_id "
             . "WHERE tr.report_id = " . ToSQL($edit_report_id, "integer");
        $db->query($sql, __FILE__, __LINE__);
        if ($db->next_record()) {
            $row_is_viart = (int) $db->f('is_viart');
            // Display subtracts 2h for non-viart to show local Ukraine time; reverse for storage.
            $shift_seconds = $row_is_viart ? 0 : 2 * 3600;
            $re_local = '/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})(?::(\d{2}))?$/';
            if (preg_match($re_local, $new_start_dt, $ms) && preg_match($re_local, $new_end_dt, $me)) {
                $ms_sec = isset($ms[6]) ? (int)$ms[6] : 0;
                $me_sec = isset($me[6]) ? (int)$me[6] : 0;
                $start_ts_display = mktime((int)$ms[4], (int)$ms[5], $ms_sec, (int)$ms[2], (int)$ms[3], (int)$ms[1]);
                $end_ts_display   = mktime((int)$me[4], (int)$me[5], $me_sec, (int)$me[2], (int)$me[3], (int)$me[1]);
                if ($end_ts_display > $start_ts_display) {
                    $start_ts = $start_ts_display + $shift_seconds;
                    $end_ts   = $end_ts_display   + $shift_seconds;
                    $spent_hours = ($end_ts - $start_ts) / 3600;
                    $sql  = "UPDATE time_report SET ";
                    $sql .= " started_date = " . ToSQL(date('Y-m-d H:i:s', $start_ts), "text") . ", ";
                    $sql .= " report_date  = " . ToSQL(date('Y-m-d H:i:s', $end_ts),   "text") . ", ";
                    $sql .= " spent_hours  = " . ToSQL($spent_hours, "number");
                    $sql .= " WHERE report_id = " . ToSQL($edit_report_id, "integer");
                    $db->query($sql, __FILE__, __LINE__);
                    $redirect_to('updated');
                }
            }
        }
        $redirect_to('invalid');
    }
}

$default_period = 1;

// --- Filter logic (replaces filter.php + iTemplate) ---
$period         = GetParam("period");
$start_date     = GetParam("start_date");
$end_date       = GetParam("end_date");
$person_selected = GetParam("person_selected");
$submit         = GetParam("submit");
$team           = GetParam("team");
$format         = GetParam("format");

$as=''; $vs=''; $ys='';
switch (strtolower($team)) {
    case "all":    $sqlteam = ""; $as = "selected"; break;
    case "viart":  $sqlteam = " AND u.is_viart=1 "; $vs = "selected"; break;
    case "yoonoo": $sqlteam = " AND u.is_viart=0 "; $ys = "selected"; break;
    default:       $sqlteam = " AND u.is_viart=1 "; $vs = "selected"; $team = "viart";
}

$periods = array(""=>"", "1"=>"Today", "2"=>"Yesterday", "3"=>"Last 7 Days", "4"=>"Last Month", "5"=>"This Month");

if (!$period && !$submit) {
    $period = $default_period;
}

$current_date = va_time();
$cyear = $current_date[0]; $cmonth = $current_date[1]; $cday = $current_date[2];

$today_date = date("Y-m-d");
$yesterday_date = date("Y-m-d", mktime(0, 0, 0, $cmonth, $cday - 1, $cyear));
$week_start_date = date("Y-m-d", mktime(0, 0, 0, $cmonth, $cday - 7, $cday));
$week_end_date = date("Y-m-d", mktime(0, 0, 0, $cmonth, $cday - 1, $cyear));
$month_start_date = date("Y-m-d", mktime(0, 0, 0, $cmonth - 1, 1, $cyear));
$month_end_date = date("Y-m-t", mktime(0, 0, 0, $cmonth - 1, 1, $cyear));
$this_month_start = date("Y-m-d", mktime(0, 0, 0, $cmonth, 1, $cyear));
$this_month_end = date("Y-m-d", mktime(0, 0, 0, $cmonth, $cday, $cyear));

if (!$start_date && !$end_date) {
    switch ($period) {
        case 1: $start_date = $today_date; $end_date = $today_date; break;
        case 2: $start_date = $yesterday_date; $end_date = $yesterday_date; break;
        case 3: $start_date = $week_start_date; $end_date = $week_end_date; break;
        case 4: $start_date = $month_start_date; $end_date = $month_end_date; break;
        case 5: $start_date = $this_month_start; $end_date = $this_month_end; break;
    }
}

$sd = ""; $ed = ""; $sdt = ""; $edt = "";
if ($start_date) {
    $sd_ar = parse_date(array("YYYY", "-", "MM", "-", "DD"), $start_date, "Start Date");
    $sd_ts = mktime(0, 0, 0, $sd_ar[1], $sd_ar[2], $sd_ar[0]);
    $sd = @date("Y-m-d", $sd_ts);
    $sdt = @date("Y-m-d 00:00:00", $sd_ts);
}
if ($end_date) {
    $ed_ar = parse_date(array("YYYY", "-", "MM", "-", "DD"), $end_date, "End Date");
    $ed_ts = mktime(0, 0, 0, $ed_ar[1], $ed_ar[2], $ed_ar[0]);
    $ed = @date("Y-m-d", $ed_ts);
    $edt = @date("Y-m-d 23:59:59", $ed_ts);
}

// Load people
$people_list = array();
$sql = "SELECT user_id, is_viart, CONCAT(first_name,' ', last_name) as person FROM users u WHERE is_deleted IS NULL ORDER BY person";
$db->query($sql, __FILE__, __LINE__);
while ($db->next_record()) {
    $people_list[] = array(
        'user_id' => $db->f("user_id"),
        'is_viart' => $db->f("is_viart"),
        'person' => $db->f("person")
    );
}

// --- Query data ---
$records = array();
$csv_rows = array();
$has_results = false;

if ($submit) {
    $sql  = " SELECT tr.report_id, CONCAT(u.first_name, ' ', u.last_name) as person, t.task_title, tr.task_id, tr.spent_hours, is_viart, ";
    $sql .= " DATE_FORMAT(tr.started_date, '%d %b %Y - %W') AS date, DATE_FORMAT(tr.report_date, '%d %b %Y - %W') AS rdate, ";
    $sql .= " task_domain_url, ";
    $sql .= " DATE_FORMAT(tr.started_date, '%d %b %Y') AS m_date, ";
    $sql .= " UNIX_TIMESTAMP(tr.started_date) as start_time_u, UNIX_TIMESTAMP(tr.report_date) as end_time_u ";
    $sql .= " FROM users u, time_report tr, tasks t ";
    $sql .= " WHERE u.user_id=tr.user_id AND t.task_id=tr.task_id ".$sqlteam;
    if ($person_selected) $sql .= " AND tr.user_id=" . ToSQL($person_selected, "integer");
    if ($sdt) $sql .= " AND tr.started_date>='$sdt' ";
    if ($edt) $sql .= " AND tr.started_date<='$edt' ";
    $sql .= " ORDER BY TO_DAYS(tr.started_date), person, tr.started_date";
    $db->query($sql, __FILE__, __LINE__);

    while ($db->next_record()) {
        $date = $db->f("date");
        $rdate = $db->f("rdate");
        $uk_shift = (!$db->f("is_viart")) * 2 * 60 * 60;
        $start_time = date("H:i", $db->f("start_time_u") - $uk_shift);
        if ($date == $rdate) {
            $end_time_str = date("H:i", $db->f("end_time_u") - $uk_shift);
        } else {
            $end_time_str = date("d M Y H:i", $db->f("end_time_u") - $uk_shift);
        }
        if (!$date && $rdate) $date = $rdate;

        $start_u_local = $db->f("start_time_u") - $uk_shift;
        $end_u_local   = $db->f("end_time_u")   - $uk_shift;
        $records[] = array(
            'report_id' => $db->f("report_id"),
            'date' => $date,
            'person' => $db->f("person"),
            'task_title' => $db->f("task_title"),
            'task_id' => $db->f("task_id"),
            'time_period' => $start_time . " - " . $end_time_str,
            'spent_hours' => $db->f("spent_hours"),
            'spent_hours_fmt' => Hours2HoursMins($db->f("spent_hours")),
            // Local (displayed) times for the inline edit modal, as "YYYY-MM-DDTHH:MM"
            'edit_start_dt' => date('Y-m-d\TH:i', $start_u_local),
            'edit_end_dt'   => date('Y-m-d\TH:i', $end_u_local)
        );

        $csv_rows[] = array(
            $db->f("m_date"),
            $db->f("person"),
            date("H:i", $db->f("start_time_u")),
            date("H:i", $db->f("end_time_u")),
            $db->f("task_domain_url"),
            $db->f("task_title"),
            $db->f("task_id"),
            $db->f("spent_hours")
        );
    }
    $has_results = !empty($records);
}

// --- CSV export ---
if ($format == "csv" && $submit) {
    header("Content-type: text/csv");
    header("Content-Disposition: attachment; filename=time_report.csv");
    header("Pragma: no-cache");
    header("Expires: 0");

    $header_row = array("Date", "User", "Start Time", "End Time", "Domain", "Task", "Task ID", "Spent Hours");
    $out = fopen('php://output', 'w');
    fputcsv($out, $header_row);
    foreach ($csv_rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

// --- Build grouped data for display ---
$grouped = array(); // array of date groups
if ($has_results) {
    $current_group = null;
    foreach ($records as $r) {
        $group_key = $r['date'] . '|' . ($person_selected ? '' : $r['person']);
        if ($current_group === null || $current_group['key'] !== $group_key) {
            if ($current_group !== null) {
                $grouped[] = $current_group;
            }
            $current_group = array(
                'key' => $group_key,
                'date' => $r['date'],
                'person' => $r['person'],
                'is_new_date' => ($current_group === null || $current_group['date'] !== $r['date']),
                'rows' => array(),
                'total_hours' => 0
            );
        }
        $current_group['rows'][] = $r;
        $current_group['total_hours'] += $r['spent_hours'];
    }
    if ($current_group !== null) {
        $grouped[] = $current_group;
    }
}

$person_label = $person_selected ? "for " . ($has_results ? htmlspecialchars($records[0]['person']) : "selected person") : "for all persons";
$total_all_hours = 0;
foreach ($records as $r) $total_all_hours += $r['spent_hours'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Time Report - Sayu Monitor</title>
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

        .container { max-width: 1200px; margin: 0 auto; padding: 24px; }

        .page-header {
            display: flex; justify-content: space-between; align-items: flex-start;
            margin-bottom: 20px; flex-wrap: wrap; gap: 16px;
        }
        .page-title { font-size: 1.5rem; font-weight: 700; color: #2d3748; }
        .page-subtitle { color: #718096; font-size: 0.85rem; margin-top: 4px; }

        .filter-card {
            background: #fff; border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            padding: 20px; margin-bottom: 20px;
        }
        .filter-form { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 4px; }
        .filter-group label {
            font-size: 0.75rem; font-weight: 600; color: #718096;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .filter-group select, .filter-group input[type="text"], .filter-group input[type="date"] {
            padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;
            font-size: 0.85rem; font-family: inherit; background: #fff;
            min-width: 150px; transition: border-color 0.2s;
        }
        .filter-group select:focus, .filter-group input:focus {
            outline: none; border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        .filter-actions { display: flex; gap: 8px; align-items: flex-end; }
        .btn {
            padding: 8px 16px; border-radius: 6px; font-size: 0.85rem;
            font-weight: 600; cursor: pointer; border: none; font-family: inherit;
            transition: all 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-primary { background: #667eea; color: #fff; }
        .btn-primary:hover { background: #5a67d8; }
        .btn-secondary { background: #e2e8f0; color: #4a5568; }
        .btn-secondary:hover { background: #cbd5e0; }
        .btn-sm { padding: 6px 12px; font-size: 0.8rem; }

        .card {
            background: #fff; border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08); overflow: hidden; margin-bottom: 20px;
        }
        .card-header {
            padding: 14px 20px; background: #f8f9fa; border-bottom: 1px solid #e2e8f0;
            display: flex; justify-content: space-between; align-items: center;
        }
        .card-title { font-weight: 600; font-size: 1rem; color: #2d3748; }

        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th {
            padding: 10px 14px; text-align: left; font-weight: 600; font-size: 0.75rem;
            text-transform: uppercase; letter-spacing: 0.5px; color: #718096;
            background: #f8f9fa; border-bottom: 2px solid #e2e8f0;
        }
        .data-table td {
            padding: 8px 14px; border-bottom: 1px solid #f1f5f9; font-size: 0.85rem;
        }
        .data-table td a { color: #667eea; text-decoration: none; }
        .data-table td a:hover { text-decoration: underline; }
        .data-table tr:hover td { background: #f8f9fc; }

        .date-header td {
            background: #f0f4ff; font-weight: 700; font-size: 0.9rem;
            color: #2d3748; padding: 10px 14px; border-bottom: 2px solid #667eea;
        }
        .person-header td {
            font-weight: 600; color: #4a5568; padding: 8px 14px;
            background: #fafbfc; border-bottom: 1px solid #e2e8f0;
        }
        .total-row td {
            font-weight: 700; background: #f8f9fa; border-top: 2px solid #e2e8f0;
            padding: 8px 14px;
        }
        .grand-total-row td {
            font-weight: 700; background: #eef2ff; border-top: 3px solid #667eea;
            padding: 10px 14px; font-size: 0.9rem;
        }

        .empty-state { text-align: center; padding: 40px; color: #718096; }

        .export-link { margin-left: auto; }
        .export-link a {
            color: #667eea; text-decoration: none; font-size: 0.8rem; font-weight: 600;
        }
        .export-link a:hover { text-decoration: underline; }

        /* Dark mode */
        html.dark-mode body { background: #0d1117; color: #cbd5e0; }
        html.dark-mode .page-title { color: #e2e8f0; }
        html.dark-mode .page-subtitle { color: #a0aec0; }
        html.dark-mode .filter-card {
            background: #161b22; border: 1px solid #2d333b;
            box-shadow: 0 2px 8px rgba(0,0,0,0.4);
        }
        html.dark-mode .filter-group label { color: #8b949e; }
        html.dark-mode .filter-group select,
        html.dark-mode .filter-group input[type="text"],
        html.dark-mode .filter-group input[type="date"] {
            background: #1c2333; border-color: #2d333b; color: #e2e8f0;
        }
        html.dark-mode .filter-group select:focus,
        html.dark-mode .filter-group input:focus {
            border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.2);
        }
        html.dark-mode .btn-secondary { background: #1c2333; color: #cbd5e0; border: 1px solid #2d333b; }
        html.dark-mode .btn-secondary:hover { background: #2d333b; color: #e2e8f0; }
        html.dark-mode .card {
            background: #161b22; border: 1px solid #2d333b;
            box-shadow: 0 4px 20px rgba(0,0,0,0.4);
        }
        html.dark-mode .card-header {
            background: #1c2333; border-bottom-color: #2d333b;
        }
        html.dark-mode .card-title { color: #e2e8f0; }
        html.dark-mode .data-table th {
            background: #1c2333; border-bottom-color: #2d333b; color: #8b949e;
        }
        html.dark-mode .data-table td {
            border-bottom-color: #2d333b; color: #e2e8f0;
        }
        html.dark-mode .data-table td a { color: #90cdf4; }
        html.dark-mode .data-table tr:hover td { background: #1c2333; }
        html.dark-mode .date-header td {
            background: rgba(102,126,234,0.15); border-bottom-color: #667eea; color: #e2e8f0;
        }
        html.dark-mode .person-header td {
            background: #1c2333; border-bottom-color: #2d333b; color: #cbd5e0;
        }
        html.dark-mode .total-row td {
            background: #1c2333; border-top-color: #2d333b; color: #cbd5e0;
        }
        html.dark-mode .grand-total-row td {
            background: rgba(102,126,234,0.2); border-top-color: #667eea; color: #e2e8f0;
        }
        html.dark-mode .empty-state { color: #8b949e; }
        html.dark-mode .export-link a { color: #90cdf4; }

        @media (max-width: 768px) {
            .container { padding: 12px; }
            .filter-form { flex-direction: column; }
            .filter-group select, .filter-group input { min-width: 100%; }
            .page-title { font-size: 1.2rem; }
            .data-table td, .data-table th { padding: 6px 8px; font-size: 0.8rem; }
        }

        /* Edit-time action */
        .tr-actions-cell { width: 1%; white-space: nowrap; text-align: center; }
        .tr-edit-btn {
            background: transparent; border: 1px solid transparent; border-radius: 6px;
            padding: 4px 6px; cursor: pointer; color: #667eea; line-height: 0;
            transition: all 0.15s;
        }
        .tr-edit-btn:hover { background: #edf2ff; border-color: #c3cfe2; color: #4c51bf; }
        html.dark-mode .tr-edit-btn { color: #93c5fd; }
        html.dark-mode .tr-edit-btn:hover { background: #1c2333; border-color: #2d333b; color: #bee3f8; }

        /* Flash banner */
        .tr-flash {
            padding: 10px 14px; border-radius: 8px; margin-bottom: 16px;
            font-size: 0.9rem; border: 1px solid transparent;
        }
        .tr-flash--updated { background: #f0fdf4; color: #166534; border-color: #86efac; }
        .tr-flash--deleted { background: #fff7ed; color: #9a3412; border-color: #fdba74; }
        .tr-flash--invalid { background: #fef2f2; color: #991b1b; border-color: #fecaca; }
        html.dark-mode .tr-flash--updated { background: #064e3b; color: #bbf7d0; border-color: #166534; }
        html.dark-mode .tr-flash--deleted { background: #7c2d12; color: #fed7aa; border-color: #9a3412; }
        html.dark-mode .tr-flash--invalid { background: #7f1d1d; color: #fecaca; border-color: #991b1b; }

        /* Edit-time modal */
        .tr-modal-backdrop {
            position: fixed; inset: 0; background: rgba(15,20,25,0.55);
            display: none; align-items: center; justify-content: center; z-index: 2000;
        }
        .tr-modal-backdrop.open { display: flex; }
        .tr-modal {
            background: #fff; border-radius: 12px; width: min(460px, 94vw);
            box-shadow: 0 20px 50px rgba(0,0,0,0.3); overflow: hidden;
        }
        .tr-modal-header {
            padding: 14px 18px; border-bottom: 1px solid #e2e8f0;
            display: flex; justify-content: space-between; align-items: center;
        }
        .tr-modal-header h3 { font-size: 1rem; margin: 0; color: #2d3748; font-weight: 600; }
        .tr-modal-close {
            background: none; border: none; font-size: 1.3rem; color: #718096;
            cursor: pointer; padding: 0 4px; line-height: 1;
        }
        .tr-modal-body { padding: 16px 18px; }
        .tr-modal-body .tr-meta {
            font-size: 0.8rem; color: #718096; margin-bottom: 14px;
            padding-bottom: 10px; border-bottom: 1px dashed #e2e8f0;
        }
        .tr-modal-body label {
            display: block; font-size: 0.75rem; font-weight: 600; color: #4a5568;
            text-transform: uppercase; letter-spacing: 0.4px; margin: 10px 0 4px;
        }
        .tr-modal-body input[type="datetime-local"] {
            width: 100%; padding: 8px 10px; border: 1px solid #e2e8f0;
            border-radius: 6px; font-family: inherit; font-size: 0.9rem;
        }
        .tr-modal-footer {
            padding: 12px 18px; border-top: 1px solid #e2e8f0; background: #f8fafc;
            display: flex; justify-content: space-between; align-items: center; gap: 8px;
        }
        .tr-btn-delete {
            background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca;
        }
        .tr-btn-delete:hover { background: #fecaca; }
        html.dark-mode .tr-modal { background: #161b22; }
        html.dark-mode .tr-modal-header { border-bottom-color: #2d333b; }
        html.dark-mode .tr-modal-header h3 { color: #e2e8f0; }
        html.dark-mode .tr-modal-close { color: #8b949e; }
        html.dark-mode .tr-modal-body .tr-meta { color: #8b949e; border-bottom-color: #2d333b; }
        html.dark-mode .tr-modal-body label { color: #cbd5e0; }
        html.dark-mode .tr-modal-body input[type="datetime-local"] {
            background: #1c2333; color: #e2e8f0; border-color: #2d333b;
            color-scheme: dark;
        }
        html.dark-mode .tr-modal-body input[type="datetime-local"]::-webkit-calendar-picker-indicator {
            filter: brightness(0) invert(1) opacity(0.92);
        }
        html.dark-mode .tr-modal-footer { background: #1c2333; border-top-color: #2d333b; }
        html.dark-mode .tr-btn-delete { background: #7f1d1d; color: #fecaca; border-color: #991b1b; }
        html.dark-mode .tr-btn-delete:hover { background: #991b1b; }
    </style>
</head>
<body>
    <?php include("./includes/modern_header.php"); ?>

    <div class="container">
        <div class="page-header">
            <div>
                <h1 class="page-title">Time Report</h1>
                <p class="page-subtitle">Time spending details <?php echo $submit ? htmlspecialchars($person_label) : ''; ?></p>
            </div>
        </div>

        <div class="filter-card">
            <form name="frmFilter" action="time_report.php" method="GET">
                <div class="filter-form">
                    <div class="filter-group">
                        <label>Period</label>
                        <select name="period" id="periodSelect" onchange="selectPeriod()">
                            <?php foreach ($periods as $val => $desc): ?>
                            <option value="<?php echo $val; ?>"<?php echo ($val == $period) ? ' selected' : ''; ?>><?php echo $desc ? $desc : '-- Custom --'; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Start Date</label>
                        <input type="text" name="start_date" id="startDate" value="<?php echo htmlspecialchars($sd); ?>" placeholder="YYYY-MM-DD" onchange="document.getElementById('periodSelect').selectedIndex=0">
                    </div>
                    <div class="filter-group">
                        <label>End Date</label>
                        <input type="text" name="end_date" id="endDate" value="<?php echo htmlspecialchars($ed); ?>" placeholder="YYYY-MM-DD" onchange="document.getElementById('periodSelect').selectedIndex=0">
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
                        <button type="button" class="btn btn-secondary" onclick="window.location='time_report.php'">Clear</button>
                    </div>
                </div>
            </form>
        </div>

        <?php if ($tr_flash_msg): ?>
        <div class="tr-flash tr-flash--<?php echo htmlspecialchars($tr_flash, ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars($tr_flash_msg); ?>
        </div>
        <?php endif; ?>

        <?php if ($submit): ?>
        <div class="card">
            <div class="card-header">
                <span class="card-title">Time Spending Report <?php echo htmlspecialchars($person_label); ?></span>
                <?php if ($has_results): ?>
                <span class="export-link">
                    <a href="time_report.php?<?php echo http_build_query(array('submit'=>1,'period'=>$period,'start_date'=>$sd,'end_date'=>$ed,'person_selected'=>$person_selected,'team'=>$team,'format'=>'csv')); ?>">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle;"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Export CSV
                    </a>
                </span>
                <?php endif; ?>
            </div>
            <?php if ($has_results): ?>
            <div style="overflow-x: auto;">
                <?php
                    $base_cols = $person_selected ? 3 : 4; // Time Period, Task, Hours (+ User if not filtered)
                    $total_cols = $base_cols + ($can_edit_time_records ? 1 : 0);
                    $daytotal_label_span = $total_cols - 1 - ($can_edit_time_records ? 1 : 0);
                ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <?php if (!$person_selected): ?><th>User</th><?php endif; ?>
                            <th>Time Period</th>
                            <th>Task</th>
                            <th style="text-align:center">Hours</th>
                            <?php if ($can_edit_time_records): ?><th style="text-align:center; width:1%">&nbsp;</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $prev_date = '';
                    $prev_person = '';
                    $day_hours = 0;
                    $first = true;

                    foreach ($records as $i => $r):
                        $is_new_date = ($r['date'] !== $prev_date);
                        $is_new_person = ($r['person'] !== $prev_person);

                        // Show day total before new date/person change
                        if (!$first && ($is_new_date || (!$person_selected && $is_new_person))):
                    ?>
                        <tr class="total-row">
                            <td colspan="<?php echo $daytotal_label_span; ?>" style="text-align:right">Day Total:</td>
                            <td style="text-align:center"><?php echo Hours2HoursMins($day_hours); ?></td>
                            <?php if ($can_edit_time_records): ?><td></td><?php endif; ?>
                        </tr>
                    <?php
                            $day_hours = 0;
                        endif;

                        if ($is_new_date):
                    ?>
                        <tr class="date-header">
                            <td colspan="<?php echo $total_cols; ?>"><?php echo htmlspecialchars($r['date']); ?></td>
                        </tr>
                    <?php
                        endif;

                        if (!$person_selected && $is_new_person):
                    ?>
                        <tr class="person-header">
                            <td colspan="<?php echo $total_cols; ?>"><?php echo htmlspecialchars($r['person']); ?></td>
                        </tr>
                    <?php endif; ?>

                        <tr>
                            <?php if (!$person_selected): ?><td></td><?php endif; ?>
                            <td style="white-space:nowrap"><?php echo htmlspecialchars($r['time_period']); ?></td>
                            <td><a href="edit_task.php?task_id=<?php echo $r['task_id']; ?>"><?php echo htmlspecialchars($r['task_title']); ?></a></td>
                            <td style="text-align:center"><?php echo $r['spent_hours_fmt']; ?></td>
                            <?php if ($can_edit_time_records): ?>
                            <td class="tr-actions-cell">
                                <button type="button" class="tr-edit-btn"
                                        title="Edit time record"
                                        data-report-id="<?php echo (int)$r['report_id']; ?>"
                                        data-start="<?php echo htmlspecialchars($r['edit_start_dt'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-end="<?php echo htmlspecialchars($r['edit_end_dt'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-task="<?php echo htmlspecialchars($r['task_title'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-person="<?php echo htmlspecialchars($r['person'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
                                </button>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php
                        $day_hours += $r['spent_hours'];
                        $prev_date = $r['date'];
                        $prev_person = $r['person'];
                        $first = false;
                    endforeach;

                    // Final day total
                    if (!$first):
                    ?>
                        <tr class="total-row">
                            <td colspan="<?php echo $daytotal_label_span; ?>" style="text-align:right">Day Total:</td>
                            <td style="text-align:center"><?php echo Hours2HoursMins($day_hours); ?></td>
                            <?php if ($can_edit_time_records): ?><td></td><?php endif; ?>
                        </tr>
                    <?php endif; ?>

                    <?php if ($total_all_hours && $person_selected): ?>
                        <tr class="grand-total-row">
                            <td colspan="<?php echo $daytotal_label_span; ?>" style="text-align:right">Total of all days:</td>
                            <td style="text-align:center"><?php echo Hours2HoursMins($total_all_hours); ?></td>
                            <?php if ($can_edit_time_records): ?><td></td><?php endif; ?>
                        </tr>
                    <?php endif; ?>
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

    <?php if ($can_edit_time_records): ?>
    <div class="tr-modal-backdrop" id="trEditBackdrop">
        <div class="tr-modal" role="dialog" aria-modal="true" aria-labelledby="trEditTitle">
            <div class="tr-modal-header">
                <h3 id="trEditTitle">Edit time record</h3>
                <button type="button" class="tr-modal-close" id="trEditClose" aria-label="Close">&times;</button>
            </div>
            <form method="POST" action="time_report.php<?php
                $qs = $_SERVER['QUERY_STRING']; echo $qs ? '?' . htmlspecialchars($qs, ENT_QUOTES, 'UTF-8') : '';
            ?>" id="trEditForm">
                <?php foreach (array('period','start_date','end_date','team','person_selected','submit') as $_k): ?>
                    <input type="hidden" name="<?php echo $_k; ?>" value="<?php echo htmlspecialchars(GetParam($_k) ?: '', ENT_QUOTES, 'UTF-8'); ?>">
                <?php endforeach; ?>
                <input type="hidden" name="report_id" id="trEditReportId" value="">
                <input type="hidden" name="tr_action" id="trEditAction" value="update_time">

                <div class="tr-modal-body">
                    <div class="tr-meta">
                        <div><strong id="trEditPerson"></strong></div>
                        <div id="trEditTask" style="margin-top:2px"></div>
                    </div>
                    <label for="trEditStart">Start</label>
                    <input type="datetime-local" name="new_start_dt" id="trEditStart" required>
                    <label for="trEditEnd">End</label>
                    <input type="datetime-local" name="new_end_dt" id="trEditEnd" required>
                </div>
                <div class="tr-modal-footer">
                    <button type="button" class="btn btn-sm tr-btn-delete" id="trEditDeleteBtn">Delete</button>
                    <div style="display:flex; gap:8px">
                        <button type="button" class="btn btn-secondary btn-sm" id="trEditCancel">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Save</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
    var allPeople = <?php echo json_encode($people_list); ?>;
    var selectedPerson = <?php echo json_encode($person_selected ? $person_selected : ""); ?>;

    // Date constants for period selector
    var datePeriods = {
        '1': ['<?php echo $today_date; ?>', '<?php echo $today_date; ?>'],
        '2': ['<?php echo $yesterday_date; ?>', '<?php echo $yesterday_date; ?>'],
        '3': ['<?php echo $week_start_date; ?>', '<?php echo $week_end_date; ?>'],
        '4': ['<?php echo $month_start_date; ?>', '<?php echo $month_end_date; ?>'],
        '5': ['<?php echo $this_month_start; ?>', '<?php echo $this_month_end; ?>']
    };

    function selectPeriod() {
        var val = document.getElementById('periodSelect').value;
        if (datePeriods[val]) {
            document.getElementById('startDate').value = datePeriods[val][0];
            document.getElementById('endDate').value = datePeriods[val][1];
        }
    }

    function filterPeople() {
        var teamVal = document.getElementById('teamSelect').value;
        var sel = document.getElementById('personSelect');
        sel.innerHTML = '<option value="">All people</option>';
        allPeople.forEach(function(p) {
            var show = (teamVal === 'all') ||
                       (teamVal === 'viart' && p.is_viart == 1) ||
                       (teamVal === 'yoonoo' && p.is_viart == 0);
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

    <?php if ($can_edit_time_records): ?>
    // --- Time-record edit modal ---
    (function () {
        var backdrop = document.getElementById('trEditBackdrop');
        var form     = document.getElementById('trEditForm');
        var fReport  = document.getElementById('trEditReportId');
        var fAction  = document.getElementById('trEditAction');
        var fStart   = document.getElementById('trEditStart');
        var fEnd     = document.getElementById('trEditEnd');
        var elPerson = document.getElementById('trEditPerson');
        var elTask   = document.getElementById('trEditTask');

        function openModal(btn) {
            fReport.value = btn.getAttribute('data-report-id') || '';
            fAction.value = 'update_time';
            fStart.value  = btn.getAttribute('data-start') || '';
            fEnd.value    = btn.getAttribute('data-end')   || '';
            elPerson.textContent = btn.getAttribute('data-person') || '';
            elTask.textContent   = btn.getAttribute('data-task')   || '';
            backdrop.classList.add('open');
            setTimeout(function(){ fStart.focus(); }, 50);
        }
        function closeModal() { backdrop.classList.remove('open'); }

        document.addEventListener('click', function (ev) {
            var btn = ev.target.closest && ev.target.closest('.tr-edit-btn');
            if (btn) { ev.preventDefault(); openModal(btn); }
        });
        document.getElementById('trEditClose').addEventListener('click', closeModal);
        document.getElementById('trEditCancel').addEventListener('click', closeModal);
        backdrop.addEventListener('click', function (ev) { if (ev.target === backdrop) closeModal(); });
        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape' && backdrop.classList.contains('open')) closeModal();
        });

        document.getElementById('trEditDeleteBtn').addEventListener('click', function () {
            if (!fReport.value) return;
            if (!confirm('Delete this time record? This cannot be undone.')) return;
            fAction.value = 'delete';
            form.submit();
        });
    })();
    <?php endif; ?>
    </script>
</body>
</html>
