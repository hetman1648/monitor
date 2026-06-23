<?php
/*
	Poll a dev-copy job started by dev_copy.php.
	@param job
	Returns { ok, state, log, url }  (state: running | done | error | stopped)
*/

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");
include ("./svn_backups_support.php");

header("Content-Type: application/json");

$dir = svn_backup_job_dir(GetParam("job"));
if ($dir === null || !is_dir($dir)) { echo json_encode(array("ok" => false, "error" => "Unknown dev-copy job.")); exit; }

$status = json_decode(@file_get_contents($dir . "/status.json"), true);
if (!is_array($status)) $status = array();
$state = isset($status["state"]) ? $status["state"] : "running";
$url   = isset($status["url"]) ? $status["url"] : "";

$log = is_file($dir . "/progress") ? (string) @file_get_contents($dir . "/progress") : "";
if (strlen($log) > 20000) $log = "…" . substr($log, -20000); // cap

if (is_file($dir . "/rc")) {
	$rc = (int) trim(@file_get_contents($dir . "/rc"));
	if ($state !== "stopped") $state = ($rc === 0) ? "done" : "error";
}
$message = "";
if ($state === "error") {
	// surface the last "!!" failure line if present
	if (preg_match_all('/^!!.*$/m', $log, $mm) && count($mm[0])) $message = trim(end($mm[0]));
	if ($message === "") $message = "Dev copy failed — see the log.";
}

echo json_encode(array("ok" => true, "state" => $state, "log" => $log, "url" => $url, "message" => $message));
