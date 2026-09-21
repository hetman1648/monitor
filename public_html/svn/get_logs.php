<?php
/*
	run this file from AJAX call to get error logs for the specified repository
	@param: repository
	@param: last_50_errors  (1 = last 50 errors, empty = critical errors)
	Returns plain text (rendered in the SVN Updater modal).
*/

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");
include ("./svn_hosts.php");

$repository = GetParam("repository");
$last_50    = GetParam("last_50_errors");
if (!strlen($repository)) die("Please specify SVN repository");

// Sites hosted off web1 keep their error log on their own server — read it over SSH.
$host = svn_host_for($repository);
if ($host) {
	$log = svn_host_log_path($repository);
	if ($log === '') { echo "Error log location not configured for this site yet."; exit; }
	$ssh = svn_host_ssh($host);
	$php_log = svn_host_php_log_path($repository);
	// last 50: tail the log; critical: pull fatal/critical lines. tema uses sudo on these hosts.
	// The log PHP errors land in (php_log_path, Apache's ssl_error.log on rss) is read too, since they
	// never reach nginx's log. Each file gets its own tail so a noisy one can't crowd out the other; a
	// missing file is simply skipped. That log can run to several GB a day (officesupplystore), so only
	// its last 50MB is searched.
	if ($last_50) {
		$remote = "sudo tail -n 50 " . escapeshellarg($log);
		if ($php_log !== '') $remote .= "; sudo tail -n 50 " . escapeshellarg($php_log) . " 2>/dev/null";
		$empty  = "The error log is empty.";
	} else {
		// nginx tags its own severities as [crit]/[alert]/[emerg] (not the word "critical"), and a dead
		// PHP backend shows up only as an upstream connect/timeout error. The TLS handshake/read failures
		// nginx logs at [crit] are just scanners and broken clients, so they're dropped.
		$remote = "sudo grep -aiE 'fatal|critical|\\[(crit|alert|emerg)\\]|PHP (Fatal|Parse)|connect\\(\\) failed|upstream timed out|no live upstreams|failed to make connection to backend' " . escapeshellarg($log)
			. " | grep -avE 'SSL_(do_handshake|read|write|shutdown)\\(\\) failed' | tail -n 50";
		if ($php_log !== '') {
			// same bar as web1's daemon minus its catch-all ':error': PHP fatals/parse errors, ViArt DB
			// errors, and Apache's own crit/alert/emerg
			$remote .= "; sudo tail -c 50000000 " . escapeshellarg($php_log) . " 2>/dev/null"
				. " | grep -aE 'PHP (Fatal|Parse) error|Database error|:(crit|alert|emerg)\\]' | tail -n 50";
		}
		$empty  = "No critical errors found.";
	}
	$out = array(); $rc = 0;
	exec($ssh . " " . escapeshellarg($remote) . " 2>/dev/null", $out, $rc);
	$text = trim(implode("\n", $out));
	echo $text !== '' ? $text : $empty;
	exit;
}

$path = "https://web1.sayu.co.uk/svn/";
$action    = $last_50 ? 'shlasterr' : 'shcriterr';
$ok_string = $last_50 ? 'Server response is: +OK Last Errors' : 'Server response is: +OK Fatal Errors:';
$empty_msg = $last_50 ? 'The error log is empty.' : 'No critical errors found.';

$command = "index.php?action=" . $action . "&repository=" . $repository . "&username=" . $svn_login . "&password=" . $svn_password;
$res = get_page($path . $command);

if (strpos($res, $ok_string) !== false) {
	$parts = explode($ok_string, $res);
	$log = isset($parts[1]) ? trim($parts[1]) : '';
	$decoded = trim((string) base64_decode($log));
	echo $decoded !== '' ? $decoded : $empty_msg;
	exit;
}

// Empty / nothing-to-show responses from the gateway
if (stripos($res, '-ERR') !== false || stripos($res, 'is empty') !== false || stripos($res, 'No critical errors') !== false) {
	echo $empty_msg;
	exit;
}

// Anything else is a real failure — surface the gateway message in plain text.
$plain = trim(strip_tags(str_replace(array('<br>', '<br/>', '<br />'), "\n", $res)));
echo "ERROR: could not read the log from the SVN gateway.\n" . $plain;
exit;
