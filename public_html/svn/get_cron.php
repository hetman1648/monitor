<?php
/*
	run this file from AJAX call to get a list of Cron Jobs for the specified repository
	@param: repository
	Returns plain text (rendered in the SVN Updater modal).
*/

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");

$repository = GetParam("repository");
if (!strlen($repository)) die("Please specify SVN repository");

$path    = "https://web1.sayu.co.uk/svn/";
$command = "index.php?action=showcrons&repository=" . $repository . "&username=" . $svn_login . "&password=" . $svn_password;
$res = get_page($path . $command);

// Cron jobs present: "...Server response is: +OK Cron Jobs list:<base64>"
$ok_string = 'Server response is: +OK Cron Jobs list:';
if (strpos($res, $ok_string) !== false) {
	$lines = explode($ok_string, $res);
	$log = isset($lines[1]) ? trim($lines[1]) : '';
	$decoded = trim((string) base64_decode($log));
	echo $decoded !== '' ? $decoded : "No cron jobs installed for this repository.";
	exit;
}

// No cron jobs: gateway replies "+OK No Cron Jobs installed yet" (or "ERR No cron jobs found")
if (stripos($res, '+OK') !== false || stripos($res, 'No cron jobs') !== false) {
	echo "No cron jobs installed for this repository.";
	exit;
}

// Anything else is a real failure — surface the gateway message in plain text.
$plain = trim(strip_tags(str_replace(array('<br>', '<br/>', '<br />'), "\n", $res)));
echo "ERROR: could not read cron jobs from the SVN gateway.\n" . $plain;
exit;
