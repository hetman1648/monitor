<?php
/*
	Safety copy of days_off (vacation / illness / unpaid periods).
	The table was emptied by something outside Monitor on 30 Sep and again on 3-4 Oct 2026.

	Run from cron every 5 minutes (CLI only):
	  - mirrors days_off into days_off_copy, unless the live table looks wiped
	    (missing, empty, or 20+ rows fewer than the copy) - then the copy is kept, the
	    missing rows are put back from it (table recreated if dropped), WIPE is logged
	    and an alert is emailed
	  - once a day writes a JSON snapshot to ../days_off_snapshots/ (last 30 kept)
	  - logs rows / auto_increment / update_time each run to ../days_off_snapshots/watch.log

	Restore from the copy:
	  INSERT INTO days_off SELECT * FROM days_off_copy;   (when days_off is empty)
*/

if (php_sapi_name() != "cli") { exit; }

chdir(dirname(__FILE__));
include ("./includes/common.php");

$snap_dir  = dirname(__FILE__) . "/../days_off_snapshots";
$max_drop  = 20;
$keep_days = 30;
$alert_to  = "artem.birzul@gmail.com";

function bk_log($msg) {
	global $snap_dir;
	file_put_contents($snap_dir . "/watch.log", date("Y-m-d H:i:s") . " " . $msg . "\n", FILE_APPEND);
}

function bk_count($table) {
	global $db;
	$db->query("SELECT COUNT(*) AS n FROM " . $table);
	$db->next_record();
	return (int) $db->f("n");
}

function bk_state() {
	global $db;
	$db->query("SELECT AUTO_INCREMENT, UPDATE_TIME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'days_off'");
	if (!$db->next_record()) { return "rows=MISSING copy=" . bk_count("days_off_copy"); }
	$extra = " auto_inc=" . $db->f("AUTO_INCREMENT") . " updated=" . $db->f("UPDATE_TIME");
	return "rows=" . bk_count("days_off") . " copy=" . bk_count("days_off_copy") . $extra;
}

function bk_alert($subject, $body) {
	global $alert_to;
	@mail($alert_to, "[monitor] " . $subject, $body, "From: monitor@sayu.co.uk\r\nContent-type: text/plain; charset=utf-8");
}

if (!is_dir($snap_dir)) { mkdir($snap_dir, 0750, true); }

$db->query("SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'days_off'");
$db->next_record();
$table_exists = $db->f("n") > 0;

$db->query("SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'days_off_copy'");
$db->next_record();
if (!$db->f("n")) {
	if (!$table_exists) {
		bk_log("WIPE days_off missing and no days_off_copy to restore from");
		bk_alert("days_off table MISSING - no copy", "days_off is missing on " . gethostname() . " and there is no days_off_copy.\nRestore from the JSON snapshots in " . realpath($snap_dir) . " or the nightly dump.");
		exit(1);
	}
	$db->query("CREATE TABLE days_off_copy LIKE days_off");
}

$copy = bk_count("days_off_copy");
$live = $table_exists ? bk_count("days_off") : 0;
$state = bk_state();

if (!$table_exists || ToNumber($live) == 0 || $live < $copy - $max_drop) {
	$before = $state;
	if ($copy > 0) {
		if (!$table_exists) {
			$db->query("CREATE TABLE days_off LIKE days_off_copy");
		}
		// put back only the rows that are gone, so anything added since the copy is kept
		$db->query("INSERT INTO days_off SELECT c.* FROM days_off_copy c LEFT JOIN days_off d ON d.period_id = c.period_id WHERE d.period_id IS NULL");
		$after = bk_state();
		bk_log("WIPE " . $before . " - restored from copy: " . $after);
		bk_alert("days_off was wiped - restored", "The days_off check on " . gethostname() . " found the vacations table wiped at " . date("Y-m-d H:i:s") . ".\n\n"
			. "Before: " . $before . "\n"
			. "After restore from days_off_copy: " . $after . "\n\n"
			. ($table_exists ? "" : "The table had been DROPPED and was recreated.\n\n")
			. "The wipe happened within the last 5 minutes. Search the hosting-db binlog around that time for days_off to find who did it.\n"
			. "Log: " . realpath($snap_dir) . "/watch.log");
	} else {
		bk_log("WIPE " . $before . " - copy is empty, nothing to restore");
		bk_alert("days_off was wiped - NOT restored", "days_off looks wiped (" . $before . ") and days_off_copy is empty.\nRestore from the JSON snapshots in " . realpath($snap_dir) . " or the nightly dump.");
	}
	exit(1);
}

$db->query("DELETE FROM days_off_copy");
$db->query("INSERT INTO days_off_copy SELECT * FROM days_off");
bk_log("ok " . $state);

$snap_file = $snap_dir . "/days_off-" . date("Y-m-d") . ".json";
if (!file_exists($snap_file)) {
	$rows = array();
	$db->query("SELECT * FROM days_off ORDER BY period_id");
	while ($db->next_record()) {
		$row = array();
		foreach ($db->Record as $k => $v) {
			if (!is_int($k)) { $row[$k] = $v; }
		}
		$rows[] = $row;
	}
	file_put_contents($snap_file, json_encode($rows, JSON_PARTIAL_OUTPUT_ON_ERROR));
	foreach (glob($snap_dir . "/days_off-*.json") as $f) {
		if (filemtime($f) < time() - $keep_days * 86400) { unlink($f); }
	}
}
