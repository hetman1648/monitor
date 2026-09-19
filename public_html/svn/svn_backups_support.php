<?php
/*
	Shared helpers for the DB-backup feature (listing + restore).
	Used by get_db_backups.php (list) and restore_db_backup.php (restore).
	Relies on $svn_path / $svn_login / $svn_password from auth.php and get_page() from auth.php.
*/

/**
 * Derive the per-site test database name from a repository, e.g.
 *   watches.co.uk     -> test_watches_co_uk
 *   richdiamonds.com  -> test_richdiamonds_com
 * Always lower-case, only [a-z0-9_], so it is safe as a DB identifier.
 */
function svn_backup_testdb_name($repository) {
	$r = strtolower((string)$repository);
	$r = preg_replace('/[^a-z0-9]+/', '_', $r);
	$r = trim($r, '_');
	return 'test_' . $r;
}

/** SSH command prefix for reaching the backup server (shared by listing + restore). */
function svn_backup_ssh_base() {
	$key   = "/mnt/drive2/vhosts/monitor.sayu.co.uk/.ssh/id_ed25519";
	$known = "/mnt/drive2/vhosts/monitor.sayu.co.uk/.ssh/known_hosts";
	return "ssh -i " . escapeshellarg($key)
		. " -o BatchMode=yes -o ConnectTimeout=15 -o StrictHostKeyChecking=yes"
		. " -o UserKnownHostsFile=" . escapeshellarg($known)
		. " " . escapeshellarg("tema@backup.sayu.co.uk");
}

/** Directory on the backup server holding the *.dump.bz2 files. */
function svn_backup_remote_dir() {
	return "/backup/dbs/daily";
}

/**
 * One SSH round-trip to stat a set of backup filenames.
 * Returns map of filename => size in bytes (missing/odd names are skipped).
 */
function svn_backup_file_sizes($files) {
	$sizes = array();
	$dir = svn_backup_remote_dir();
	$remote = "stat -c " . escapeshellarg('%s %n');
	$n = 0;
	foreach ($files as $f) {
		if (!preg_match('/^[A-Za-z0-9._-]+$/', $f)) continue; // skip anything not a plain filename
		$remote .= " " . escapeshellarg($dir . "/" . $f);
		$n++;
	}
	if ($n === 0) return $sizes;
	$out = array(); $rc = 0;
	exec(svn_backup_ssh_base() . " " . escapeshellarg($remote) . " 2>/dev/null", $out, $rc);
	foreach ($out as $line) {
		// "<bytes> <path>"
		if (preg_match('/^(\d+)\s+(.+)$/', trim($line), $m)) {
			$sizes[basename($m[2])] = (int)$m[1];
		}
	}
	return $sizes;
}

/** Base directory holding per-restore job state (outside the web root). */
function svn_backup_job_base() {
	return "/mnt/drive2/vhosts/monitor.sayu.co.uk/tmp/db_restore";
}

/**
 * Shell snippet describing an images tree, written so it can never walk a huge one.
 *
 * A dev copy's public_html/images is frequently a BIND MOUNT of the shared image store
 * rather than a copy of it (a dozen of them on slayer). du/find there traverse millions
 * of files - restaurantsupplystore.co.uk is 2.1M, ~13 minutes for the find alone - which
 * is far past the pool's 300s request_terminate_timeout, so the request dies and the page
 * only ever sees a 502. A mount is reported as a mount and left unmeasured; anything else
 * is measured under `timeout`, and an expiry reports nothing rather than holding on.
 *
 * $dir_expr must already be shell-quoted. Emits key=value lines: img_present, img_mtime,
 * then either img_mount=1, or img_bytes + img_count (both omitted if du ran out of time).
 * du printing only its final total is what makes emptiness the timeout signal; the find is
 * only reached once du has proved the tree is small enough to walk.
 */
function svn_images_probe_sh($dir_expr, $sudo = '', $seconds = 5) {
	$s = (int) $seconds;
	$pre = ($sudo !== '') ? (rtrim($sudo) . ' ') : '';
	return 'if [ -d ' . $dir_expr . ' ]; then' . "\n"
		. '  echo "img_present=1"' . "\n"
		. '  echo "img_mtime=$(stat -c %Y ' . $dir_expr . ' 2>/dev/null)"' . "\n"
		. '  if mountpoint -q ' . $dir_expr . ' 2>/dev/null; then' . "\n"
		. '    echo "img_mount=1"' . "\n"
		. '  else' . "\n"
		. '    IB=$(' . $pre . 'timeout ' . $s . ' du -sb ' . $dir_expr . ' 2>/dev/null | cut -f1)' . "\n"
		. '    if [ -n "$IB" ]; then' . "\n"
		. '      echo "img_bytes=$IB"' . "\n"
		. '      echo "img_count=$(' . $pre . 'timeout ' . ($s * 2) . ' find ' . $dir_expr . ' -type f 2>/dev/null | wc -l)"' . "\n"
		. '    fi' . "\n"
		. '  fi' . "\n"
		. 'else' . "\n"
		. '  echo "img_present=0"' . "\n"
		. 'fi';
}

/**
 * Size in bytes of a site's LIVE images tree, as the yardstick a dev copy's own images tree
 * is measured against. du over a large tree is costly and the source barely changes, so the
 * answer is cached for an hour beside the job state.
 *
 * The tree lives on whichever server hosts the site: for an off-web1 site (svn_site_host_map)
 * web1 holds at most an empty stub - restaurantsupplystore.co.uk is 8KB here and the real tree
 * is on rss - so sizing the local path made every such site's "of X live" figure and image-copy
 * percentage meaningless. Measure it on the site's own host instead.
 *
 * Returns -1 when the size is unknown: no images folder, a bind mount (nothing of our own to
 * measure), or du ran out of time. That answer is cached briefly too, so a slow tree doesn't
 * cost an ssh round-trip on every poll.
 */
function svn_site_images_bytes($repository) {
	$repository = (string) $repository;
	if (!preg_match('/^[A-Za-z0-9._-]+$/', $repository) || strpos($repository, '..') !== false) return -1;
	$cache = svn_backup_job_base() . "/imgsrc-" . md5($repository) . ".json";
	$c = @json_decode((string) @file_get_contents($cache), true);
	if (is_array($c) && isset($c["t"], $c["bytes"])) {
		$ttl = ((int) $c["bytes"] < 0) ? 300 : 3600;   // unknown: retry sooner, but not every poll
		if (time() - (int) $c["t"] < $ttl) return (int) $c["bytes"];
	}

	$host = function_exists('svn_host_for') ? svn_host_for($repository) : null;
	if ($host) {
		$src_dir = svn_host_wc_dir($repository) . "/public_html/images";
		if ($src_dir === "/public_html/images") return -1;      // host known but its wc_base isn't
		$probe = 'mountpoint -q ' . escapeshellarg($src_dir) . ' 2>/dev/null && exit 0; '
			. 'timeout 20 du -sb ' . escapeshellarg($src_dir) . ' 2>/dev/null';
		$cmd = svn_host_ssh($host) . " " . escapeshellarg($probe) . " 2>/dev/null";
	} else {
		$src_dir = "/mnt/drive2/vhosts/" . $repository . "/public_html/images";
		if (!is_dir($src_dir)) return -1;
		$cmd = "timeout 20 du -sb " . escapeshellarg($src_dir) . " 2>/dev/null";
	}

	$o = array();
	@exec($cmd, $o);
	$bytes = (count($o) && preg_match('/^(\d+)/', $o[0], $m)) ? (int) $m[1] : -1;
	@file_put_contents($cache, json_encode(array("t" => time(), "bytes" => $bytes)));
	return $bytes;
}

/**
 * Resolve (and validate) the directory for a restore job id.
 * Job ids are our own hex tokens, so anything else is rejected - no path tricks.
 * Returns the absolute path, or null if the id is malformed.
 */
function svn_backup_job_dir($job) {
	if (!preg_match('/^[a-f0-9]{8,32}$/', (string)$job)) return null;
	return svn_backup_job_base() . '/' . $job;
}

/** Best-effort removal of job dirs older than a day, so state doesn't pile up. */
function svn_backup_prune_jobs() {
	$base = svn_backup_job_base();
	$now = time();
	$dirs = @glob($base . '/*', GLOB_ONLYDIR);
	if (!is_array($dirs)) return;
	foreach ($dirs as $d) {
		if (($now - @filemtime($d)) > 86400) {
			foreach ((array)@glob($d . '/*') as $f) { @unlink($f); }
			@rmdir($d);
		}
	}
}

/**
 * Ask the SVN gateway for the list of available DB backups for a repository.
 * Returns array("ok"=>bool, "error"=>string, "backups"=>array of array("file","date")).
 * Newest first (by the YYYY-MM-DD embedded in each filename).
 */
function svn_list_db_backups($svn_path, $svn_login, $svn_password, $repository) {
	$command = "index.php?action=shdbbackup&username=" . urlencode($svn_login)
		. "&password=" . urlencode($svn_password)
		. "&repository=" . urlencode($repository);
	$res = get_page($svn_path . $command);

	if ($res === false || $res === null || !strlen(trim($res))) {
		return array("ok" => false, "error" => "No response from the backup server.", "backups" => array());
	}

	$marker = "DB backups list:";
	$pos = stripos($res, $marker);
	if ($pos === false) {
		$msg = trim(preg_replace('/\s+/', ' ', strip_tags($res)));
		return array("ok" => false, "error" => $msg !== '' ? $msg : "Could not read the backups list.", "backups" => array());
	}

	$list = substr($res, $pos + strlen($marker));
	$lines = preg_split('/[\r\n]+/', $list);
	$backups = array();
	foreach ($lines as $line) {
		$line = trim($line);
		if ($line === '') continue;
		if (!preg_match('/\.(dump|sql|gz|bz2|zip|tar)/i', $line)) continue;
		$date = '';
		if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $line, $m)) {
			$date = $m[1] . '-' . $m[2] . '-' . $m[3];
		}
		$backups[] = array("file" => $line, "date" => $date);
	}

	usort($backups, function ($a, $b) {
		if ($a["date"] !== $b["date"]) return strcmp($b["date"], $a["date"]);
		return strcmp($b["file"], $a["file"]);
	});

	return array("ok" => true, "error" => "", "backups" => $backups);
}
