<?php
/*
	START a DB-backup restore as a background job, then return a job id the UI polls.

	Flow:
	  1. validate that `file` is one of the names shdbbackup currently lists for `repository`;
	  2. stat the dump size on the backup server (for the progress bar);
	  3. drop & recreate test_<repo> on hosting-db;
	  4. launch a detached job: ssh-stream the dump -> pv (progress) -> bunzip2 -> mysql import.

	Progress is polled via restore_db_status.php; a run can be cancelled via restore_db_stop.php.

	@param: repository
	@param: file       (must be one of the filenames returned by shdbbackup for this repo)
*/

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");
include ("./svn_backups_support.php");

header("Content-Type: application/json");

// SSH access to the backup server (key, host, dump dir) lives in svn_backups_support.php.

function rb_fail($msg, $extra = array()) {
	echo json_encode(array_merge(array("ok" => false, "error" => $msg), $extra));
	exit;
}

$repository = GetParam("repository");
$file       = GetParam("file");

if (!strlen($repository)) rb_fail("Please specify the SVN repository.");
if (!strlen($file))       rb_fail("No backup file specified.");

// 1. Validate the requested file against the real list for this repo - the only
//    filenames we act on are ones shdbbackup just listed for this exact repository.
$list = svn_list_db_backups($svn_path, $svn_login, $svn_password, $repository);
if (!$list["ok"]) rb_fail($list["error"], array("repository" => $repository));
$valid = false;
foreach ($list["backups"] as $b) { if ($b["file"] === $file) { $valid = true; break; } }
if (!$valid) rb_fail("That backup is not in the current list for this site - reload and try again.", array("repository" => $repository));

// Belt and braces: even a listed name must be a plain backup filename.
if (strpos($file, '/') !== false || strpos($file, '..') !== false
	|| !preg_match('/^[A-Za-z0-9._-]+\.(dump|sql)(\.(bz2|gz))?$/', $file)) {
	rb_fail("Unexpected backup file name.", array("repository" => $repository));
}

// Target DB name, with a hard guard so we can only ever touch test_* databases.
$testdb = svn_backup_testdb_name($repository);
if (strpos($testdb, 'test_') !== 0 || !preg_match('/^test_[a-z0-9_]+$/', $testdb)) {
	rb_fail("Refusing to restore into a non-test database.", array("repository" => $repository));
}

$ssh_base = svn_backup_ssh_base();
$remote_path = rtrim(svn_backup_remote_dir(), '/') . '/' . $file;

// 2. Size of the (compressed) dump, for the progress bar / ETA.
$size_out = array(); $size_rc = 0;
exec($ssh_base . " " . escapeshellarg("stat -c %s " . escapeshellarg($remote_path)) . " 2>&1", $size_out, $size_rc);
$total = ($size_rc === 0 && isset($size_out[0]) && ctype_digit(trim($size_out[0]))) ? (int)trim($size_out[0]) : 0;
if ($total <= 0) rb_fail("Could not read the backup size on the server (" . trim(implode(' ', $size_out)) . ").", array("repository" => $repository));

// 3. Drop & recreate the test DB (fast, foreground).
putenv("MYSQL_PWD=" . DATABASE_PASSWORD);
$mysql_auth = "--host=" . escapeshellarg(DATABASE_HOST) . " --user=" . escapeshellarg(DATABASE_USER);
$sql = "DROP DATABASE IF EXISTS `$testdb`; CREATE DATABASE `$testdb` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
$o1 = array(); $rc1 = 0;
exec("mysql $mysql_auth -e " . escapeshellarg($sql) . " 2>&1", $o1, $rc1);
if ($rc1 !== 0) rb_fail("Could not create $testdb on " . DATABASE_HOST . ": " . trim(implode(' ', $o1)), array("repository" => $repository, "testdb" => $testdb));

// 4. Create the job dir and the runner script, then launch it detached.
svn_backup_prune_jobs();
$job = bin2hex(openssl_random_pseudo_bytes(8));
$dir = svn_backup_job_dir($job);
if ($dir === null || !@mkdir($dir, 0700, true)) rb_fail("Could not create the job directory.", array("repository" => $repository));

$status = array(
	"state" => "running", "repository" => $repository, "file" => $file,
	"testdb" => $testdb, "total" => $total, "started" => time(),
);
@file_put_contents($dir . "/status.json", json_encode($status));

// pv -n writes one integer percentage per line to stderr (here captured to progress.pct).
// pipefail makes $? reflect a failure anywhere in the pipe (ssh / decompress / mysql).
$decomp = (substr($file, -4) === '.bz2') ? 'bunzip2' : ((substr($file, -3) === '.gz') ? 'gunzip' : 'cat');
$pwd_q  = "'" . str_replace("'", "'\\''", DATABASE_PASSWORD) . "'";
// Strip DEFINER=`u`@`h` clauses so triggers/views/routines restore on a server where
// those accounts don't exist (the backtick+@ form never occurs in real row data).
$strip  = 'sed -E \'s/DEFINER=`[^`]+`@`[^`]+` ?//g\'';
$run  = "#!/bin/bash\n";
$run .= "set -o pipefail\n";
$run .= "echo \$\$ > " . escapeshellarg($dir . "/pgid") . "\n";
$run .= "export MYSQL_PWD=" . $pwd_q . "\n";
$run .= $ssh_base . " " . escapeshellarg("cat " . escapeshellarg($remote_path))
	. " | pv -n -s " . (int)$total . " 2> " . escapeshellarg($dir . "/progress.pct")
	. " | " . $decomp
	. " | " . $strip
	. " | mysql " . $mysql_auth . " --one-database " . escapeshellarg($testdb)
	. " 2> " . escapeshellarg($dir . "/import.err") . "\n";
$run .= "rc=\$?\n";
$run .= "echo \$rc > " . escapeshellarg($dir . "/rc") . "\n";
@file_put_contents($dir . "/run.sh", $run);

// setsid -> the job gets its own session/process group, so stop can kill the whole pipe.
exec("setsid bash " . escapeshellarg($dir . "/run.sh") . " >/dev/null 2>&1 < /dev/null &");

echo json_encode(array(
	"ok" => true, "job" => $job, "repository" => $repository,
	"testdb" => $testdb, "file" => $file, "total" => $total,
));
