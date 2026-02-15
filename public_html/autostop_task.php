#!/usr/bin/php
<?php
/**
 * Auto-stop tasks that are still "In Progress" at end of day.
 * Cron: 59 23 * * * /usr/bin/php-cgi /mnt/drive2/vhosts/monitor.sayu.co.uk/public_html/autostop_task.php
 *
 * Fixes applied (Feb 2026):
 * - Cap spent_hours at 12h max to prevent runaway entries
 * - Use same-day 18:00 based on the task's started_time date (not script run date)
 * - Clear started_time on the task after stopping
 * - Use LAST_INSERT_ID() instead of MAX(report_id)
 * - Call CountTimeProjects() for each stopped task
 * - Updated email addresses
 */

include_once("./db_mysql.inc");
include_once("./includes/db_connect.php");
include_once("./includes/common_functions.php");

$CRLF = "\n";
$MAX_HOURS = 12; // Cap: no single auto-stop entry can exceed 12 hours

$db = new DB_Sql();
$db->Database = DATABASE_NAME;
$db->User     = DATABASE_USER;
$db->Password = DATABASE_PASSWORD;
$db->Host     = DATABASE_HOST;

$db2 = new DB_Sql();
$db2->Database = DATABASE_NAME;
$db2->User     = DATABASE_USER;
$db2->Password = DATABASE_PASSWORD;
$db2->Host     = DATABASE_HOST;

$script_path = "monitor.sayu.co.uk/";

$sql = "SELECT t.task_id, t.task_title, t.started_time, t.responsible_user_id, ";
$sql .= "CONCAT(u.first_name,' ',u.last_name) AS user_name, u.email, ";
$sql .= "UNIX_TIMESTAMP(t.started_time) as started_stamp ";
$sql .= "FROM tasks t, users u ";
$sql .= "WHERE t.responsible_user_id=u.user_id AND t.task_status_id=1";

$today = date("Y-m-d");

// Summary email header
$message2 = "<html><head><title>Monitor: Auto-stopped tasks - $today</title></head>";
$message2 .= "<body style=\"font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 14px; color: #333; background: #f5f5f5; padding: 20px;\">";
$message2 .= "<div style=\"max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);\">";
$message2 .= "<h2 style=\"margin: 0 0 16px; color: #2d3748;\">Auto-stopped tasks &mdash; $today</h2>";
$message2 .= "<table style=\"width: 100%; border-collapse: collapse;\">";
$message2 .= "<tr>";
$message2 .= "<th style=\"text-align: left; padding: 8px 12px; background: #f8fafc; border-bottom: 2px solid #e2e8f0; font-size: 12px; color: #718096; text-transform: uppercase;\">Person</th>";
$message2 .= "<th style=\"text-align: left; padding: 8px 12px; background: #f8fafc; border-bottom: 2px solid #e2e8f0; font-size: 12px; color: #718096; text-transform: uppercase;\">Task</th>";
$message2 .= "<th style=\"text-align: right; padding: 8px 12px; background: #f8fafc; border-bottom: 2px solid #e2e8f0; font-size: 12px; color: #718096; text-transform: uppercase;\">Hours</th>";
$message2 .= "<th style=\"text-align: center; padding: 8px 12px; background: #f8fafc; border-bottom: 2px solid #e2e8f0; font-size: 12px; color: #718096; text-transform: uppercase;\">Actions</th></tr>";

$headers = "From: monitor@sayu.co.uk" . $CRLF;
$headers .= "Reply-To: monitor@sayu.co.uk" . $CRLF;
$headers .= "Content-Type: text/html; charset=UTF-8";

$stopped_task_ids = array();
$stopped_count = 0;

$db->query($sql);
if ($db->next_record()) {
    do {
        $task_id = $db->f("task_id");
        $task_title = $db->f("task_title");
        $user_id = $db->f("responsible_user_id");
        $user_name = $db->f("user_name");
        $user_email = $db->f("email");
        $started_time = $db->f("started_time");
        $started_stamp = intval($db->f("started_stamp"));

        // Calculate 18:00 on the SAME DAY the task was started (not today)
        $started_day_18h = mktime(18, 0, 0, date("n", $started_stamp), date("j", $started_stamp), date("Y", $started_stamp));

        if ($started_stamp < $started_day_18h) {
            // Started before 18:00 on that day — cap at 18:00
            $spent_hours = ($started_day_18h - $started_stamp) / 3600;
            $report_date = date("Y-m-d", $started_stamp) . " 18:00:00";
        } else {
            // Started after 18:00 — minimal entry (1 minute)
            $spent_hours = 1 / 60;
            $report_date = date("Y-m-d H:i:s", $started_stamp + 60);
        }

        // Cap at maximum hours to prevent runaway entries
        if ($spent_hours > $MAX_HOURS) {
            $spent_hours = $MAX_HOURS;
            $report_date = date("Y-m-d H:i:s", $started_stamp + ($MAX_HOURS * 3600));
        }

        //-- Update task: add spent time, set status to Waiting (8), clear started_time
        $sql2 = "UPDATE tasks SET actual_hours = (actual_hours + " . doubleval($spent_hours) . "), task_status_id = 8, started_time = NULL WHERE task_id = " . intval($task_id);
        $db2->query($sql2);

        //-- Write time report
        $sql2 = "INSERT INTO time_report (user_id, started_date, task_id, report_date, spent_hours, auto_stop) ";
        $sql2 .= "VALUES (" . intval($user_id) . ", " . ToSQL($started_time, "text") . ", " . intval($task_id) . ", " . ToSQL($report_date, "text") . ", " . doubleval($spent_hours) . ", 1)";
        $db2->query($sql2);

        //-- Get the inserted report_id
        $sql2 = "SELECT LAST_INSERT_ID() AS id";
        $db2->query($sql2);
        $report_id = 0;
        if ($db2->next_record()) $report_id = $db2->f("id");

        //-- Recalculate project time totals for this task
        $stopped_task_ids[] = intval($task_id);

        $spent_display = sprintf("%d:%02d", floor($spent_hours), round(($spent_hours - floor($spent_hours)) * 60));
        $was_capped = ($spent_hours >= $MAX_HOURS) ? " &#9888;" : "";

        $message2 .= "<tr>";
        $message2 .= "<td style=\"padding: 8px 12px; border-bottom: 1px solid #f1f5f9;\">$user_name</td>";
        $message2 .= "<td style=\"padding: 8px 12px; border-bottom: 1px solid #f1f5f9;\"><a href='https://" . $script_path . "edit_task.php?task_id=$task_id' style=\"color: #667eea; text-decoration: none;\">$task_title</a></td>";
        $message2 .= "<td style=\"padding: 8px 12px; border-bottom: 1px solid #f1f5f9; text-align: right; font-weight: 600;\">$spent_display$was_capped</td>";
        $message2 .= "<td style=\"padding: 8px 12px; border-bottom: 1px solid #f1f5f9; text-align: center;\"><a href='https://" . $script_path . "set_tr_time.php?report_id=$report_id' style=\"color: #667eea; text-decoration: none;\">Set time</a></td></tr>";

        //-- Send e-mail to the user who forgot to stop
        if ($user_email) {
            $to = $user_email;
            $subj = "Monitor: Your task '$task_title' was auto-stopped - $today";
            $message = "<html><head><title>$subj</title></head>";
            $message .= "<body style=\"font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 14px; color: #333; padding: 20px;\">";
            $message .= "<p>Your task <strong>$task_title</strong> was automatically stopped because it was still running at end of day.</p>";
            $message .= "<p>Logged time: <strong>$spent_display</strong></p>";
            $message .= "<p><a href='https://" . $script_path . "set_tr_time.php?report_id=$report_id'>Click here to correct the time</a> | ";
            $message .= "<a href='https://" . $script_path . "edit_task.php?task_id=$task_id'>View task</a></p>";
            $message .= "</body></html>";
            @mail($to, $subj, $message, $headers);
        }

        $stopped_count++;
    } while ($db->next_record());

    // Recalculate project time totals for all affected tasks
    foreach ($stopped_task_ids as $tid) {
        CountTimeProjects($tid);
    }

    //-- Send summary email to admin
    $message2 .= "</table>";
    $message2 .= "<p style=\"margin-top: 16px; font-size: 12px; color: #a0aec0;\">$stopped_count task(s) auto-stopped. Max hours cap: {$MAX_HOURS}h.</p>";
    $message2 .= "</div></body></html>";

    $to = "artem.birzul@gmail.com";
    $subj = "Monitor: $stopped_count task(s) auto-stopped - $today";
    @mail($to, $subj, $message2, $headers);
} //if
?>
