<?php
/*
	run this file from AJAX call to update the site with a working copy of repository
	@param: repository
*/

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");
include_once ("./svn_repo_support.php");

$repository = GetParam("repository");
if (!strlen($repository)) die ("Please specify SVN repository");
$command = "index.php?action=checkout&username=".$svn_login."&password=".$svn_password."&repository=".$repository;
$res = get_page($svn_path. $command);

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