<?php
/*
	AJAX (JSON): progress of the (asynchronous) dsid image copy for a dev copy.

	The image sync is an rsync the dsid daemon runs on slayer, independent of the dev_copy job
	(which returns as soon as it has *requested* the copy). This reports whether that rsync is
	still running for the repo and how far along it is, by comparing the destination image tree
	size on slayer with the source tree size on web1.

	A dev copy whose images folder is a bind mount of the shared image store has nothing to
	copy and nothing measurable (walking one runs for minutes), so it reports mount=1 and the
	poller stops watching.

	@param repository
	Returns { ok, running, mount, src_bytes, dest_bytes, pct, has_images }
*/

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");
include ("./svn_backups_support.php");
include_once ("./svn_hosts.php");

// Released before the ssh round-trips below, which nothing after this point needs it for:
// the session file lock is exclusive, so holding it would serialise this poll against every
// other AJAX call the dev-copy popup makes. $_SESSION stays readable.
session_write_close();

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

$has_images = (svn_host_for($repository) !== null) || is_dir("/mnt/drive2/vhosts/" . $repository . "/public_html/images");

// Destination size + whether an rsync for this repo's images is still running — one SSH round-trip.
// The size goes through the shared probe: mount-aware (a bind mount of the shared store is not a
// copy in progress and must not be walked) and bounded by `timeout`, so this poll can never run
// past the pool's request_terminate_timeout and hand the page a 502 instead of JSON.
$dest_dir = "/home/staff/" . $login . "/projects/" . $repository . "/public_html/images";
$needle   = "/" . $repository . "/public_html/images";
$SLAYER = "ssh -i " . escapeshellarg($SSH_KEY) . " -p " . (int) $SLAYER_PORT
	. " -o BatchMode=yes -o ConnectTimeout=15 -o StrictHostKeyChecking=yes -o UserKnownHostsFile=" . escapeshellarg($SSH_KNOWN)
	. " " . escapeshellarg($login . "@" . $SLAYER_HOST);
$remote = 'echo "running=$(ps -eo args 2>/dev/null | grep -F ' . escapeshellarg($needle) . ' | grep -i rsync | grep -v grep | wc -l)"' . "\n"
	. svn_images_probe_sh(escapeshellarg($dest_dir), 'sudo');
$out = array();
@exec($SLAYER . " " . escapeshellarg("echo " . base64_encode($remote) . " | base64 -d | bash") . " 2>/dev/null", $out);

$kv = array();
foreach ($out as $line) {
	$p = strpos($line, '=');
	if ($p === false) continue;
	$kv[substr($line, 0, $p)] = trim(substr($line, $p + 1));
}

$running    = (isset($kv['running']) && (int) $kv['running'] > 0);
$mount      = (isset($kv['img_mount']) && $kv['img_mount'] === '1');
$dest_bytes = (isset($kv['img_bytes']) && ctype_digit($kv['img_bytes'])) ? (int) $kv['img_bytes'] : -1;

// Source image tree on whichever server hosts the site, cached by the helper since du over a
// large tree is costly and the source barely changes. Only worth asking for once we know there
// is a destination size to measure it against - a mount has nothing to make progress towards,
// and the live tree behind it is the very one we just declined to walk.
$src_bytes = ($mount || $dest_bytes < 0) ? -1 : svn_site_images_bytes($repository);

$pct = null;
if ($src_bytes > 0 && $dest_bytes >= 0) { $pct = max(0, min(100, (int) round($dest_bytes * 100 / $src_bytes))); }

dci_json(array("ok" => true, "running" => $running, "mount" => $mount, "src_bytes" => $src_bytes,
	"dest_bytes" => $dest_bytes, "pct" => $pct, "has_images" => $has_images));
