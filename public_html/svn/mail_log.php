<?php
/*
	AJAX (JSON): recent mail SENT FROM a site's domain, read from the mail server's postfix log.

	@param repository   (the domain — repo name)
	@param limit        (messages to scan for; default 100, max 300)

	web1 sites  -> the root sudo wrapper /usr/local/bin/monitor_maillog (reads /var/log/mail.log).
	off-web1    -> not wired yet (returns ok=true with a note); the site's mail lives on its own box.

	Returns { ok, domain, rows:[{time,from,to,status}], count, note? }
	Rows are newest-first. "domain-owned" messages are matched by DKIM d=<domain> / message-id host.
*/

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");
include ("./svn_hosts.php");

header('Content-Type: application/json; charset=utf-8');
function ml_json($a) { echo json_encode($a); exit; }

$repository = trim((string) GetParam("repository"));
if ($repository === '' || !preg_match('/^[A-Za-z0-9.\-]+$/', $repository) || strpos($repository, '..') !== false) {
	ml_json(array('ok' => false, 'error' => 'Invalid repository.'));
}
$limit = (int) GetParam("limit");
if ($limit <= 0) $limit = 100;
if ($limit > 300) $limit = 300;

$host = svn_host_for($repository);
if ($host) {
	// Site is on its own server; its outbound mail is logged there, not on web1. Not wired yet.
	ml_json(array('ok' => true, 'domain' => $repository, 'rows' => array(), 'count' => 0,
		'note' => 'Mail log for off-web1 sites is not available yet — this site sends mail from its own server.'));
}

// web1: the locked-down sudo reader.
$out = array(); $rc = 0;
@exec("sudo -n /usr/local/bin/monitor_maillog " . escapeshellarg($repository) . " " . (int) $limit . " 2>/dev/null", $out, $rc);

$rows = array();
foreach ($out as $line) {
	$p = explode("\t", $line);
	if (count($p) < 4) continue;
	$rows[] = array(
		'time'   => ensure_utf8($p[0]),
		'from'   => ensure_utf8($p[1]),
		'to'     => ensure_utf8($p[2]),
		'status' => ensure_utf8($p[3]),
	);
}
$rows = array_reverse($rows); // newest first

if (!count($rows) && $rc !== 0) {
	ml_json(array('ok' => false, 'error' => 'Could not read the mail log.'));
}
ml_json(array('ok' => true, 'domain' => $repository, 'rows' => $rows, 'count' => count($rows)));
