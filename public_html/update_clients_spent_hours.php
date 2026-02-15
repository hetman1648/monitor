<?php
include("./includes/common.php");

$sql = "DELETE FROM clients_spent_hours";
$sql .= " WHERE TO_DAYS(date) = TO_DAYS(NOW())";
$db->query($sql);

$sql = "INSERT INTO clients_spent_hours";
$sql .= " (client_id, user_id, date, spent_hours) ";
$sql .= " (SELECT DISTINCT t.client_id, tr.user_id, DATE(report_date), sum(tr.spent_hours)";
$sql .= " FROM time_report tr INNER JOIN tasks t ON t.task_id = tr.task_id";
$sql .= " WHERE t.client_id > 0 AND TO_DAYS(report_date) = TO_DAYS(NOW()) - 1";
$sql .= " GROUP BY user_id, client_id)"; 
$db->query($sql);

/* fill from 2008-03-01 to 2008-05-20
for ($day = 1; $day <= 80; $day++) {
	$date = date("Y-m-d", mktime(0, 0, 0, 03, $day, 2008));
	fill($date);
}

function fill ($date) {
global $db;
	$sql = "INSERT INTO CLIENTS_SPENT_HOURS";
	$sql .= " (client_id, user_id, date, spent_hours) ";
	$sql .= " (SELECT DISTINCT t.client_id, tr.user_id, report_date, sum(tr.spent_hours)";
	$sql .= " FROM time_report tr INNER JOIN tasks t ON t.task_id = tr.task_id";
	$sql .= " WHERE t.client_id > 0 AND TO_DAYS(report_date) = TO_DAYS('".$date."')";
	$sql .= " GROUP BY user_id, client_id)";

	$db->query($sql);
}
 */
?>