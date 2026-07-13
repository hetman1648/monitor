<?php
/*
	run this file from AJAX call to update the site with a working copy of repository
	@param: repository
*/

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");
include_once ("./svn_repo_support.php");
include_once ("./svn_hosts.php");

$repository = GetParam("repository");
if (!strlen($repository)) die ("Please specify SVN repository");
$command = "index.php?action=checkout&username=".$svn_login."&password=".$svn_password."&repository=".$repository;
$res = get_page($svn_path. $command);

// Shared "common" repos (e.g. Common8) live on the dedicated servers too — fan the same
// deploy out to each so they don't lag behind web1. Each dedicated working copy pulls the
// new revision straight from web1's svnserve (svn://web1.sayu.co.uk/...); run as the WC owner
// (root) via the monitor's passwordless-sudo SSH, same channel as the other off-web1 tools.
$shared = svn_shared_repo_deploys($repository);
if ($shared && $svn_login !== '' && $svn_password !== '') {
	$auth = '--non-interactive --no-auth-cache --username ' . escapeshellarg($svn_login) . ' --password ' . escapeshellarg($svn_password);
	$servers = svn_host_servers();
	$res .= "\n\n--- Shared repository — updating dedicated servers ---";
	foreach ($shared as $s) {
		if (!isset($servers[$s['key']])) continue;
		$host = $servers[$s['key']];
		$label = $host['ssh_host'];
		$inner = 'cd ' . escapeshellarg($s['wc']) . ' && export LC_ALL=en_US.UTF-8 && '
			. 'sudo svn update --force ' . $auth . ' 2>&1';
		$out = array(); $rc = 0;
		@exec(svn_host_ssh($host) . ' ' . escapeshellarg($inner) . ' 2>&1', $out, $rc);
		$tail = trim(implode("\n", $out));
		if ($tail === '') $tail = ($rc === 0) ? 'no output' : 'no response (exit ' . (int) $rc . ')';
		// keep the response compact — the "Updated to revision N" / "At revision N" line is what matters
		if (preg_match('/(Updated to revision \d+\.|At revision \d+\.|svn:.*)/', $tail, $mm)) $tail = $mm[1];
		$res .= "\n" . $label . ": " . $tail;
	}
}

$rev = svn_parse_revision_from_gateway_response($res);
$msg = '';
$wc = svn_repo_wc_path($repository);
if ($rev !== '' && $wc !== '') {
	$msg = svn_wc_log_message_for_revision($wc, $rev);
}
if ($rev === '' && $wc !== '') {
	list($rev, $msg) = svn_wc_head_revision_and_message($wc);
}

$repo_sql = ToSQL($repository, "text");
if (svn_updates_has_revision_columns($db)) {
	$sql = "INSERT INTO svn_updates (user_id,date_added,repository,revision,commit_message) VALUES ("
		. (int) $user_id . ",NOW()," . $repo_sql . "," . ToSQL($rev, "text") . "," . ToSQL($msg, "text") . ")";
} else {
	$sql = "INSERT INTO svn_updates (user_id,date_added,repository) VALUES (" . (int) $user_id . ",NOW()," . $repo_sql . ")";
}
$db->query($sql);

echo $res;