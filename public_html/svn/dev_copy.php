<?php
/*
	START a "dev copy" of a site on the dev server (slayer) under the logged-in developer's
	own account, as a background job. The UI polls dev_copy_status.php; cancel via dev_copy_stop.php.

	Steps (any subset, chosen in the popup):
	  - files  : svn checkout/update  -> ~/projects/<repo>   (as the dev, over SSH to slayer)
	  - db     : create <login>_<slug> on slayer and import the latest nightly backup dump
	  - images : trigger the dsid daemon to copy the images folder

	Connection: slayer user = the developer's svn_login; Monitor's SSH key must be authorised
	on slayer for that account. DB dumps are streamed from backup.sayu.co.uk.

	@param repository
	@param files|db|images  (1/0)
	@param php8             (1/0 -> use <subdomain>8.sayuconnect.com)
*/

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");
include ("./svn_backups_support.php");

header("Content-Type: application/json");

function dc_fail($msg, $extra = array()) { echo json_encode(array_merge(array("ok" => false, "error" => $msg), $extra)); exit; }

// ---- dev server (slayer) config ----
$SLAYER_HOST = "slayer.sayu.co.uk";
$SLAYER_PORT = "2222";
$SSH_KEY     = "/mnt/drive2/vhosts/monitor.sayu.co.uk/.ssh/id_ed25519";
$SSH_KNOWN   = "/mnt/drive2/vhosts/monitor.sayu.co.uk/.ssh/known_hosts";
// -----------------------------------

$repository = GetParam("repository");
if (!strlen($repository) || strpos($repository, '/') !== false || strpos($repository, '..') !== false
	|| !preg_match('/^[A-Za-z0-9._-]+$/', $repository)) {
	dc_fail("Invalid repository.");
}
$want_files  = GetParam("files")  ? true : false;
$want_db     = GetParam("db")     ? true : false;
$want_images = GetParam("images") ? true : false;
$php8        = GetParam("php8")   ? true : false;
if (!$want_files && !$want_db && !$want_images) dc_fail("Nothing selected to copy.");

// Developer settings of the logged-in user (slayer user = svn_login).
$uid = (int) GetSessionParam("UserID");
$login = ''; $pass = ''; $subdomain = '';
$db->query("SELECT svn_login, svn_password, svn_subdomain FROM users WHERE user_id=" . $uid);
if ($db->next_record()) { $login = trim($db->f("svn_login")); $pass = $db->f("svn_password"); $subdomain = trim($db->f("svn_subdomain")); }
if ($login === '' || !preg_match('/^[A-Za-z0-9_.-]+$/', $login)) {
	dc_fail("Your Developer Settings are incomplete — set your SVN login (the slayer user) in your profile first.");
}

// Derived names.
$slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', explode('.', $repository, 2)[0]));
$slug = trim($slug, '_');
$dbname = $login . '_' . $slug;                 // e.g. artem_watches
if (!preg_match('/^[A-Za-z0-9_]+$/', $dbname)) dc_fail("Could not derive a safe database name.");
$proj = "/home/staff/" . $login . "/projects/" . $repository;
$devurl = $subdomain !== '' ? ('https://' . $subdomain . ($php8 ? '8' : '') . '.sayuconnect.com/' . $repository) : '';

// Latest nightly DB backup file for this repo (from the gateway listing).
$dumpfile = '';
if ($want_db) {
	$list = svn_list_db_backups($svn_path, $svn_login, $svn_password, $repository);
	if ($list["ok"] && count($list["backups"])) { $dumpfile = $list["backups"][0]["file"]; }
	if ($dumpfile === '' || !preg_match('/^[A-Za-z0-9._-]+\.(dump|sql)(\.(bz2|gz))?$/', $dumpfile)) {
		dc_fail("No nightly DB backup found for " . $repository . " to copy.");
	}
}

// ---- build the job ----
svn_backup_prune_jobs();
$job = bin2hex(openssl_random_pseudo_bytes(8));
$dir = svn_backup_job_dir($job);
if ($dir === null || !@mkdir($dir, 0700, true)) dc_fail("Could not create the job directory.");
@file_put_contents($dir . "/status.json", json_encode(array("state" => "running", "repository" => $repository, "url" => $devurl, "started" => time())));

$SLAYER = "ssh -i " . escapeshellarg($SSH_KEY)
	. " -p " . (int)$SLAYER_PORT
	. " -o BatchMode=yes -o ConnectTimeout=20 -o StrictHostKeyChecking=yes -o UserKnownHostsFile=" . escapeshellarg($SSH_KNOWN)
	. " " . escapeshellarg($login . "@" . $SLAYER_HOST);
$BACKUP = "ssh -i " . escapeshellarg($SSH_KEY)
	. " -o BatchMode=yes -o ConnectTimeout=15 -o StrictHostKeyChecking=yes -o UserKnownHostsFile=" . escapeshellarg($SSH_KNOWN)
	. " " . escapeshellarg("tema@backup.sayu.co.uk");

$svn_url   = "svn://web1.sayu.co.uk/mnt/drive2/webclients/" . $repository;
$proj_q    = escapeshellarg($proj);
$repo_q    = escapeshellarg($repository);
$prog      = escapeshellarg($dir . "/progress");

$run  = "#!/bin/bash\n";
$run .= "echo \$\$ > " . escapeshellarg($dir . "/pgid") . "\n";
$run .= "exec >> " . $prog . " 2>&1\n";
$run .= "ok=1\n";

if ($want_files) {
	// checkout if absent, otherwise update — runs as the dev on slayer using their SVN creds.
	$co = "if [ -d ~/projects/" . $repository . "/.svn ]; then svn update --non-interactive " . $proj_q
		. "; else mkdir -p ~/projects && svn checkout --non-interactive --username " . escapeshellarg($login)
		. " --password " . escapeshellarg($pass) . " " . escapeshellarg($svn_url) . " " . $proj_q . "; fi";
	$run .= "echo '>> Files: svn checkout/update ~/projects/" . $repository . "'\n";
	$run .= $SLAYER . " " . escapeshellarg($co) . " || { echo '!! files step failed'; ok=0; }\n";
}
if ($want_db) {
	$create = "mysql -e " . escapeshellarg("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
	$decomp = (substr($dumpfile, -3) === '.gz') ? 'gunzip' : 'bunzip2';
	$run .= "echo '>> Database: " . $dbname . " <- " . $dumpfile . "'\n";
	$run .= $SLAYER . " " . escapeshellarg($create) . " || { echo '!! create db failed'; ok=0; }\n";
	$run .= $BACKUP . " " . escapeshellarg("cat /backup/dbs/daily/" . $dumpfile)
		. " | " . $decomp . " | sed -E 's/DEFINER=`[^`]+`@`[^`]+` ?//g' | "
		. $SLAYER . " " . escapeshellarg("mysql --one-database " . $dbname) . " || { echo '!! db import failed'; ok=0; }\n";
}
if ($want_images) {
	$img_url = "https://dsid.sayuconnect.com/index.php?project=" . rawurlencode($repository)
		. "&user_name=" . rawurlencode($login) . "&password=" . rawurlencode($pass) . "&is_images=1&is_db=0";
	$run .= "echo '>> Images: requesting copy via dsid daemon'\n";
	$run .= "curl -s --max-time 600 " . escapeshellarg($img_url) . " || { echo '!! images request failed'; ok=0; }\n";
	$run .= "echo\n";
}
$run .= "echo \"== DONE (ok=\$ok) ==\"\n";
$run .= "echo \$([ \$ok = 1 ] && echo 0 || echo 1) > " . escapeshellarg($dir . "/rc") . "\n";
@file_put_contents($dir . "/run.sh", $run);

exec("setsid bash " . escapeshellarg($dir . "/run.sh") . " >/dev/null 2>&1 < /dev/null &");

echo json_encode(array(
	"ok" => true, "job" => $job, "repository" => $repository,
	"target" => trim(($devurl ? $devurl . "  ·  " : "") . $login . "@slayer:~/projects/" . $repository . ($want_db ? "  ·  db " . $dbname : "")),
	"url" => $devurl,
));
