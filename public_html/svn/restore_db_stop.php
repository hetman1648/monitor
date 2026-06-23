<?php
/*
	Cancel a running restore job: kill its process group, mark it stopped, and drop
	the half-imported test DB so nothing misleading is left behind.
	@param: job   (the hex job id)
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
$testdb = isset($status["testdb"]) ? $status["testdb"] : "";

// Kill the whole process group that run.sh recorded for itself (ssh+pv+bunzip2+mysql).
$pgid = (int)trim(@file_get_contents($dir . "/pgid"));
if ($pgid > 1) {
	exec("pkill -TERM -g " . $pgid . " 2>/dev/null");
	usleep(300000);
	exec("pkill -KILL -g " . $pgid . " 2>/dev/null");
}

// Mark stopped (status + rc, so the poller settles on a terminal state).
$status["state"] = "stopped";
@file_put_contents($dir . "/status.json", json_encode($status));
if (!is_file($dir . "/rc")) @file_put_contents($dir . "/rc", "143");

// Drop the partially-restored test DB (only ever a test_* name).
if ($testdb !== "" && strpos($testdb, "test_") === 0 && preg_match('/^test_[a-z0-9_]+$/', $testdb)) {
	putenv("MYSQL_PWD=" . DATABASE_PASSWORD);
	$auth = "--host=" . escapeshellarg(DATABASE_HOST) . " --user=" . escapeshellarg(DATABASE_USER);
	exec("mysql $auth -e " . escapeshellarg("DROP DATABASE IF EXISTS `$testdb`;") . " 2>/dev/null");
}

echo json_encode(array("ok" => true, "state" => "stopped", "testdb" => $testdb));
