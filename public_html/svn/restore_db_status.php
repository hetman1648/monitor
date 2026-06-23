<?php
/*
	Poll the progress of a restore job started by restore_db_backup.php.
	@param: job   (the hex job id)
	Returns: { ok, state, percent, total, done, rate, eta, message }
	  state: running | done | error | stopped
*/

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");
include ("./svn_backups_support.php");

header("Content-Type: application/json");

$job = GetParam("job");
$dir = svn_backup_job_dir($job);
if ($dir === null || !is_dir($dir)) {
	echo json_encode(array("ok" => false, "error" => "Unknown restore job."));
	exit;
}

$status = json_decode(@file_get_contents($dir . "/status.json"), true);
if (!is_array($status)) $status = array();
$total   = isset($status["total"]) ? (int)$status["total"] : 0;
$started = isset($status["started"]) ? (int)$status["started"] : 0;
$testdb  = isset($status["testdb"]) ? $status["testdb"] : "";

// Latest percentage emitted by pv (one integer per line).
$percent = 0;
$pf = $dir . "/progress.pct";
if (is_file($pf)) {
	$raw = @file_get_contents($pf);
	if (strlen($raw)) {
		$parts = preg_split('/[\r\n]+/', trim($raw));
		$last = end($parts);
		if (is_numeric($last)) $percent = max(0, min(100, (int)$last));
	}
}

// Terminal state?
$state = isset($status["state"]) ? $status["state"] : "running";
if (is_file($dir . "/rc")) {
	$rc = (int)trim(@file_get_contents($dir . "/rc"));
	if ($state !== "stopped") $state = ($rc === 0) ? "done" : "error";
	if ($state === "done") $percent = 100;
}

$message = "";
if ($state === "error") {
	$err = trim(@file_get_contents($dir . "/import.err"));
	$message = $err !== "" ? substr($err, -300) : "Restore failed.";
} else if ($state === "done") {
	$message = "Restored into " . $testdb . " on " . DATABASE_HOST . ".";
} else if ($state === "stopped") {
	$message = "Restore cancelled.";
}

// Derive bytes done / rate / ETA from percent + elapsed (we only know the compressed total).
$elapsed = $started ? max(0, time() - $started) : 0;
$done = $total ? (int)round($total * $percent / 100) : 0;
$rate = ($elapsed > 0 && $done > 0) ? (int)round($done / $elapsed) : 0;
$eta  = ($state === "running" && $percent > 0 && $percent < 100 && $elapsed > 0)
	? (int)round($elapsed * (100 - $percent) / $percent) : 0;

echo json_encode(array(
	"ok" => true, "state" => $state, "percent" => $percent,
	"total" => $total, "done" => $done, "rate" => $rate, "eta" => $eta,
	"elapsed" => $elapsed, "testdb" => $testdb, "message" => $message,
));
