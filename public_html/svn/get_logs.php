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

$repository = GetParam("repository");
$last_50    = GetParam("last_50_errors");
if (!strlen($repository)) die("Please specify SVN repository");

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
