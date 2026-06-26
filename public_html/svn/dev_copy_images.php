<?php
/*
	AJAX (JSON): progress of the (asynchronous) dsid image copy for a dev copy.

	The image sync is an rsync the dsid daemon runs on slayer, independent of the dev_copy job
	(which returns as soon as it has *requested* the copy). This reports whether that rsync is
	still running for the repo and how far along it is, by comparing the destination image tree
	size on slayer with the source tree size on web1.

	@param repository
	Returns { ok, running, src_bytes, dest_bytes, pct, has_images }
*/

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");
include ("./svn_backups_support.php");

header("Content-Type: application/json");
function dci_json($a) { echo json_encode($a); exit; }

$SSH_KEY     = "/mnt/drive2/vhosts/monitor.sayu.co.uk/.ssh/id_ed25519";
$SSH_KNOWN   = "/mnt/drive2/vhosts/monitor.sayu.co.uk/.ssh/known_hosts";
$SLAYER_HOST = "slayer.sayu.co.uk";
$SLAYER_PORT = "2222";

$repository = trim((string) GetParam("repository"));
if ($repository === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $repository) || strpos($repository, '..') !== false) {
	dci_json(array("ok" => false, "error" => "Invalid repository."));
}

$uid = (int) GetSessionParam("UserID");
$login = '';
$db->query("SELECT svn_login FROM users WHERE user_id=" . $uid);
if ($db->next_record()) { $login = trim($db->f("svn_login")); }
if ($login === '' || !preg_match('/^[A-Za-z0-9_.-]+$/', $login)) {
	dci_json(array("ok" => false, "error" => "Your Developer Settings are incomplete."));
}

// Source image tree on web1 (this host). du over a large tree is costly, and the source barely
// changes, so cache its size briefly.
$src_dir = "/mnt/drive2/vhosts/" . $repository . "/public_html/images";
$has_images = is_dir($src_dir);
$src_bytes = -1;
$cache = svn_backup_job_base() . "/imgsrc-" . md5($repository) . ".json";
$c = @json_decode((string) @file_get_contents($cache), true);
if (is_array($c) && isset($c["t"], $c["bytes"]) && (time() - (int) $c["t"] < 3600)) {
	$src_bytes = (int) $c["bytes"];
} else if ($has_images) {
	$o = array();
	@exec("du -sb " . escapeshellarg($src_dir) . " 2>/dev/null", $o);
	if (count($o) && preg_match('/^(\d+)/', $o[0], $m)) {
		$src_bytes = (int) $m[1];
		@file_put_contents($cache, json_encode(array("t" => time(), "bytes" => $src_bytes)));
	}
}

// Destination size + whether an rsync for this repo's images is still running — one SSH round-trip.
$dest_dir = "/home/staff/" . $login . "/projects/" . $repository . "/public_html/images";
$needle   = "/" . $repository . "/public_html/images";
$SLAYER = "ssh -i " . escapeshellarg($SSH_KEY) . " -p " . (int) $SLAYER_PORT
	. " -o BatchMode=yes -o ConnectTimeout=15 -o StrictHostKeyChecking=yes -o UserKnownHostsFile=" . escapeshellarg($SSH_KNOWN)
	. " " . escapeshellarg($login . "@" . $SLAYER_HOST);
$remote = 'r=$(ps -eo args 2>/dev/null | grep -F ' . escapeshellarg($needle) . ' | grep -i rsync | grep -v grep | wc -l); '
	. 'b=$(sudo du -sb ' . escapeshellarg($dest_dir) . ' 2>/dev/null | cut -f1); echo "${r:-0}|${b:-}"';
$out = array();
@exec($SLAYER . " " . escapeshellarg($remote) . " 2>/dev/null", $out);

$running = false; $dest_bytes = -1;
if (count($out)) {
	$p = explode("|", trim($out[0]));
	$running = ((int) $p[0]) > 0;
	if (isset($p[1]) && ctype_digit($p[1])) $dest_bytes = (int) $p[1];
}

$pct = null;
if ($src_bytes > 0 && $dest_bytes >= 0) { $pct = max(0, min(100, (int) round($dest_bytes * 100 / $src_bytes))); }

dci_json(array("ok" => true, "running" => $running, "src_bytes" => $src_bytes,
	"dest_bytes" => $dest_bytes, "pct" => $pct, "has_images" => $has_images));
