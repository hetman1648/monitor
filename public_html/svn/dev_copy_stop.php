<?php
/*
	Cancel a running dev-copy job: kill its process group and mark it stopped.
	@param job
*/

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");
include ("./svn_backups_support.php");

header("Content-Type: application/json");

$dir = svn_backup_job_dir(GetParam("job"));
if ($dir === null || !is_dir($dir)) { echo json_encode(array("ok" => false, "error" => "Unknown dev-copy job.")); exit; }

$pgid = (int) trim(@file_get_contents($dir . "/pgid"));
if ($pgid > 1) {
	exec("pkill -TERM -g " . $pgid . " 2>/dev/null");
	usleep(300000);
	exec("pkill -KILL -g " . $pgid . " 2>/dev/null");
}

$status = json_decode(@file_get_contents($dir . "/status.json"), true);
if (!is_array($status)) $status = array();
$status["state"] = "stopped";
@file_put_contents($dir . "/status.json", json_encode($status));
if (!is_file($dir . "/rc")) @file_put_contents($dir . "/rc", "143");

echo json_encode(array("ok" => true, "state" => "stopped"));
