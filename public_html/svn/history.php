<?php
/*
	AJAX (JSON): deploy + commit history for a repository, for the SVN Updater.
	Primary view: recent SVN commits (author, date, message, changed files) read from the
	local FSFS repo via file://, annotated with deploy info from the svn_updates table.
	Fallback (no local repo): deploy history from svn_updates only.

	@param repository
*/

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");
include_once ("./svn_repo_support.php");

header('Content-Type: application/json; charset=utf-8');
function hist_json($a) { echo json_encode($a); exit; }

$repository = trim((string) GetParam("repository"));
if ($repository === '') {
	hist_json(array('ok' => false, 'error' => 'Please specify SVN repository.'));
}

function hist_fmt($raw) {
	$raw = trim((string) $raw);
	if ($raw === '') return '';
	$t = strtotime($raw);
	return $t === false ? $raw : date('j M Y, H:i', $t);
}

// users for deployer names
$users = array();
$db->query("SELECT user_id,first_name,last_name FROM users");
while ($db->next_record()) {
	$users[(int) $db->f("user_id")] = trim($db->f("first_name") . " " . $db->f("last_name"));
}

// deploys for this repo, plus a list for the no-local-repo fallback view.
$has_rev = svn_updates_has_revision_columns($db);
$deploys = array();      // ascending by time: [ ['at'=>raw,'at_ts'=>int|null,'by'=>name,'rev'=>int|null], ... ]
$deploy_list = array();  // descending (fallback view)
$cols = $has_rev ? "date_added,user_id,revision,commit_message" : "date_added,user_id";
$db->query("SELECT $cols FROM svn_updates WHERE repository=" . ToSQL($repository, "text") . " ORDER BY date_added DESC LIMIT 200");
while ($db->next_record()) {
	$uid = (int) $db->f("user_id");
	$name = isset($users[$uid]) ? $users[$uid] : ('User #' . $uid);
	$at = $db->f("date_added");
	$rev = $has_rev ? trim((string) $db->f("revision")) : '';
	$msg = $has_rev ? trim((string) $db->f("commit_message")) : '';
	$ts = strtotime((string) $at);
	$deploys[] = array('at' => $at, 'at_ts' => ($ts === false ? null : $ts), 'by' => $name,
		'rev' => ($rev !== '' && ctype_digit($rev) ? (int) $rev : null));
	if (count($deploy_list) < 50) {
		$deploy_list[] = array('revision' => $rev, 'by' => $name, 'at' => $at, 'message' => $msg);
	}
}
$deploys = array_reverse($deploys); // oldest-first: pick the FIRST deploy that shipped a commit

// The earliest deploy that included a commit. By revision when both are known (deploy revision >=
// commit revision); otherwise by time (the deploy ran at/after the commit). The time path covers
// legacy deploy rows recorded before the revision column existed, and any deploy where the gateway
// didn't report a revision — so history isn't left showing "Not recorded as deployed" for everything.
function hist_deploy_for_commit($deploys, $commit_rev, $commit_ts) {
	$crev = is_numeric($commit_rev) ? (int) $commit_rev : null;
	foreach ($deploys as $d) {
		$covers = false;
		if ($d['rev'] !== null && $crev !== null) $covers = ($d['rev'] >= $crev);
		else if ($d['at_ts'] !== null && $commit_ts !== false && $commit_ts !== null) $covers = ($d['at_ts'] >= $commit_ts);
		if ($covers) return $d;
	}
	return null;
}

$repo_fs = svn_repo_fs_path($repository);
$commits = $repo_fs !== '' ? svn_repo_recent_commits($repo_fs, 50) : array();

if (count($commits)) {
	$rows = array();
	foreach ($commits as $c) {
		$rev = $c['revision'];
		$dep = hist_deploy_for_commit($deploys, $rev, strtotime((string) $c['date']));
		$rows[] = array(
			'revision'         => $rev,
			'author'           => $c['author'],
			'date'             => $c['date'],
			'date_display'     => hist_fmt($c['date']),
			'ago'              => svn_relative_time($c['date']),
			'message'          => ensure_utf8($c['msg']),
			'files'            => $c['files'],
			'file_count'       => count($c['files']),
			'deployed_by'      => $dep ? $dep['by'] : '',
			'deployed_ago'     => $dep ? svn_relative_time($dep['at']) : '',
			'deployed_at'      => $dep ? hist_fmt($dep['at']) : '',
		);
	}
	hist_json(array('ok' => true, 'mode' => 'commits', 'repository' => $repository, 'rows' => $rows));
}

// Fallback: deploy history only
$rows = array();
foreach ($deploy_list as $d) {
	$rev = ($d['revision'] !== '' && ctype_digit($d['revision'])) ? $d['revision'] : '';
	$rows[] = array(
		'revision'     => $rev,
		'author'       => '',
		'date'         => $d['at'],
		'date_display' => hist_fmt($d['at']),
		'ago'          => svn_relative_time($d['at']),
		'message'      => ensure_utf8($d['message']),
		'files'        => array(),
		'file_count'   => 0,
		'deployed_by'  => $d['by'],
		'deployed_ago' => svn_relative_time($d['at']),
		'deployed_at'  => hist_fmt($d['at']),
	);
}
hist_json(array('ok' => true, 'mode' => 'deploys', 'repository' => $repository, 'rows' => $rows));
