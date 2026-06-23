<?php
/*
	run this file from AJAX call to list the available DB backups for a repository.
	Proxies the SVN gateway action "shdbbackup" (show DB backups) and returns JSON.
	@param: repository
	Uses $svn_login / $svn_password / $svn_path from auth.php.
*/

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");
include ("./svn_backups_support.php");

header("Content-Type: application/json");

$repository = GetParam("repository");
if (!strlen($repository)) {
	echo json_encode(array("ok" => false, "error" => "Please specify the SVN repository."));
	exit;
}

$r = svn_list_db_backups($svn_path, $svn_login, $svn_password, $repository);
if (!$r["ok"]) {
	echo json_encode(array("ok" => false, "error" => $r["error"], "repository" => $repository));
	exit;
}

// Look up each file's size on the backup server (one SSH round-trip).
$names = array();
foreach ($r["backups"] as $b) { $names[] = $b["file"]; }
$sizes = svn_backup_file_sizes($names);
foreach ($r["backups"] as &$b) { $b["size"] = isset($sizes[$b["file"]]) ? $sizes[$b["file"]] : 0; }
unset($b);

echo json_encode(array(
	"ok"         => true,
	"repository" => $repository,
	"testdb"     => svn_backup_testdb_name($repository),
	"count"      => count($r["backups"]),
	"backups"    => $r["backups"],
));
