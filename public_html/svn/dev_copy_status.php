<?php
/*
	Poll a dev-copy job started by dev_copy.php.
	@param job          a job id, OR
	@param repository   to find the most recent job for a repo (used to resume the popup after a
	                    page refresh, when the client-side job id has been lost)
	Returns { ok, job, state, log, url }  (state: running | done | error | stopped)
*/

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");
include ("./svn_backups_support.php");

header("Content-Type: application/json");

// Locate the newest dev-copy job dir for a repository (its status.json records the repository).
function dc_find_job_dir($repository) {
	if ($repository === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $repository)) return null;
	$base = svn_backup_job_base();
	$best = null; $best_t = -1;
	$dirs = @glob($base . '/*', GLOB_ONLYDIR);
	if (!is_array($dirs)) return null;
	foreach ($dirs as $d) {
		$st = @json_decode((string) @file_get_contents($d . '/status.json'), true);
		if (!is_array($st) || !isset($st['repository']) || $st['repository'] !== $repository) continue;
		// only dev-copy jobs carry a "url" key in status.json; DB-restore jobs don't
		if (!array_key_exists('url', $st)) continue;
		$t = isset($st['started']) ? (int) $st['started'] : (int) @filemtime($d);
		if ($t > $best_t) { $best_t = $t; $best = $d; }
	}
	return $best;
}

$dir = svn_backup_job_dir(GetParam("job"));
if (($dir === null || !is_dir($dir)) && trim((string) GetParam("repository")) !== '') {
	$dir = dc_find_job_dir(trim((string) GetParam("repository")));
}
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

echo json_encode(array("ok" => true, "job" => basename($dir), "state" => $state, "log" => $log, "url" => $url, "message" => $message));
