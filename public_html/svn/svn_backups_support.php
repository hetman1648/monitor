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
