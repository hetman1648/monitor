<?php
/*
	AJAX (JSON): mail SENT FROM a site's domain, read from postfix logs via the monitor_maillog
	sudo wrapper (see /etc/sudoers.d/monitor_maillog on web1; deployed to off-web1 servers too).

	Most sites — including several off-web1 ones (puregusto, rubberduck, rss, coffeesupplies) —
	relay their outbound mail through web1, so we read web1's /var/log/mail.log first. If a site
	is hosted off-web1 and web1 has nothing for it, we fall back to reading that server's own log
	over SSH. Messages are matched to the domain by DKIM d=<domain> / message-id host.

	@param repository   the domain (repo name)
	@param action       list (default) | raw
	@param limit        list: messages to scan (default 100, max 300)
	@param qid          raw: the postfix queue-id to expand

	list -> { ok, domain, rows:[{time,qid,from,to,status}], count, source }   (rows newest-first)
	raw  -> { ok, domain, qid, raw }
*/

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");
include ("./svn_hosts.php");

header('Content-Type: application/json; charset=utf-8');
function ml_json($a) { echo json_encode($a); exit; }

define('ML_WRAP', '/usr/local/bin/monitor_maillog');

$repository = trim((string) GetParam("repository"));
if ($repository === '' || !preg_match('/^[A-Za-z0-9.\-]+$/', $repository) || strpos($repository, '..') !== false) {
	ml_json(array('ok' => false, 'error' => 'Invalid repository.'));
}
$action = (GetParam("action") === 'raw') ? 'raw' : 'list';

// Run the wrapper on web1 ($host === null) or on the site's own server ($host = svn_host config) via SSH.
// $argv is a list of already-validated tokens (mode, domain, limit/qid) — each is shell-escaped.
function ml_run($host, $argv) {
	$cmd = 'sudo -n ' . ML_WRAP;
	foreach ($argv as $a) $cmd .= ' ' . escapeshellarg($a);
	$out = array(); $rc = 0;
	if ($host) @exec(svn_host_ssh($host) . ' ' . escapeshellarg($cmd) . ' 2>/dev/null', $out, $rc);
	else       @exec($cmd . ' 2>/dev/null', $out, $rc);
	return $out;
}

// Postfix timestamps come in two shapes depending on the box's syslog: classic "Sep 22 12:44:37"
// (web1, rubberduck; local time, no year) and RFC3339 "2026-09-22T11:42:35.358817+00:00" (rss,
// puregusto, web2; web2 logs in UTC). Show both as "22 Sep 12:44:37" in UK time; a classic stamp
// with no year is taken as this year, or last year if that would put it in the future.
function ml_time($ts) {
	$ts = trim((string) $ts);
	if ($ts === '') return '';
	$tz = new DateTimeZone('Europe/London');
	try {
		if (preg_match('/^\d{4}-\d\d-\d\dT/', $ts)) {
			$d = new DateTime(preg_replace('/\.\d+/', '', $ts));
			$d->setTimezone($tz);
			return $d->format('j M H:i:s');
		}
		if (preg_match('/^(\w{3})\s+(\d+)\s+(\d\d:\d\d:\d\d)$/', $ts, $m)) {
			$now = new DateTime('now', $tz);
			$d = DateTime::createFromFormat('M j H:i:s Y', $m[1] . ' ' . $m[2] . ' ' . $m[3] . ' ' . $now->format('Y'), $tz);
			if ($d && $d > $now->modify('+1 day')) $d->modify('-1 year');
			if ($d) return $d->format('j M H:i:s');
		}
	} catch (Exception $e) {}
	return $ts;
}

$host = svn_host_for($repository); // non-null => off-web1

if ($action === 'raw') {
	$qid = trim((string) GetParam("qid"));
	if ($qid === '' || !preg_match('/^[A-Za-z0-9]{4,}$/', $qid)) ml_json(array('ok' => false, 'error' => 'Invalid message id.'));
	$out = ml_run(null, array('raw', $repository, $qid));                 // web1 first
	if (!count($out) && $host) $out = ml_run($host, array('raw', $repository, $qid)); // then its own server
	ml_json(array('ok' => true, 'domain' => $repository, 'qid' => $qid, 'raw' => ensure_utf8(implode("\n", $out))));
}

// action = list
$limit = (int) GetParam("limit");
if ($limit <= 0) $limit = 100;
if ($limit > 300) $limit = 300;

$source = 'web1';
$out = ml_run(null, array('list', $repository, (string) $limit));        // web1 first
if (!count($out) && $host) { $out = ml_run($host, array('list', $repository, (string) $limit)); $source = $host['ssh_host']; }

$rows = array();
foreach ($out as $line) {
	$p = explode("\t", $line);
	if (count($p) < 5) continue;
	$rows[] = array(
		'time'   => ensure_utf8(ml_time($p[0])),
		'qid'    => ensure_utf8($p[1]),
		'from'   => ensure_utf8($p[2]),
		'to'     => ensure_utf8($p[3]),
		'status' => ensure_utf8($p[4]),
	);
}
$rows = array_reverse($rows); // newest first
ml_json(array('ok' => true, 'domain' => $repository, 'rows' => $rows, 'count' => count($rows), 'source' => $source));
