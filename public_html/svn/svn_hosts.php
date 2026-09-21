<?php
/*
	Where each SVN site is actually hosted, for the host-specific tools:
	  - error.log + PHP error log   (get_logs.php)
	  - file diffs  (get_file_diff.php)
	  - cron jobs   (get_cron.php / cron_manage.php)

	SVN status / update / backups are NOT affected — they keep using the web1 gateway,
	which already works for every site.

	Any repository NOT listed in svn_site_host_map() uses the default behaviour
	(web1 SVN gateway / local working copy). Listed repositories are reached over SSH on
	their own server as ssh_user (the monitor user's key is authorised there); since the
	site/cron/log/working-copy belong to a per-site system user, we use `sudo` (tema has
	passwordless sudo on these hosts) to read logs / run `svn diff` / read crontabs.

	Path patterns use {repo} = the repository (domain) name.
*/

// repository (domain) => server key
function svn_site_host_map() {
	return array(
		'hotel-buyer-store.co.uk'       => 'rss',
		'officesupplystore.co.uk'       => 'rss',
		'caresupplystore.co.uk'         => 'rss',
		'restaurantsupplystore.co.uk'   => 'rss',
		'restaurantstore.co.uk'         => 'rss',

		'puregusto.co.uk'               => 'puregusto',
		'dev.puregusto.co.uk'           => 'puregusto',
		'coffeesupplies.co.uk'          => 'puregusto',

		'rubberduckbathrooms.co.uk'     => 'rubberduck',
		'dev.rubberduckbathrooms.co.uk' => 'rubberduck',

		'richdiamonds.com'              => 'web2',
		'watchcentre.com'               => 'web2',
		'tressoro.com'                  => 'web2',
	);
}

// server key => connection + on-server path patterns ({repo} is substituted).
//   wc_base     : parent dir of each site's working copy (wc_base/{repo}); '' = unknown
//   log_path    : the site's error log
//   public_base : optional. The real public base URL for the sites on this server when it is NOT the
//                 site's own domain (e.g. served via a userdir path). Used by the client file API for
//                 base_url instead of following the domain. Omitted => the domain is the public URL.
function svn_host_servers() {
	return array(
		'rss' => array(
			'ssh_host' => 'rss.sayu.co.uk',  'ssh_user' => 'tema',
			'wc_base'  => '/mnt/drive2/vhosts',
			'log_path' => '/var/log/vhosts/{repo}/nginx_error.log',
			// Where PHP errors actually land: nginx proxies to Apache, which hands PHP to FPM via
			// proxy_fcgi, so PHP's messages come back as Apache "AH01071: Got error 'PHP message: ...'"
			// lines in the SSL vhost's error log. They never reach nginx_error.log.
			'php_log_path' => '/var/log/vhosts/{repo}/ssl_error.log',
		),
		'puregusto' => array(
			'ssh_host' => 'puregusto.co.uk', 'ssh_user' => 'tema',
			'wc_base'  => '/var/vhosts',
			'log_path' => '/var/log/vhosts/{repo}/nginx_error.log',
		),
		'rubberduck' => array(
			'ssh_host' => 'rubberduckbathrooms.co.uk', 'ssh_user' => 'tema',
			'wc_base'  => '/var/www',
			'log_path' => '/var/log/apache2/{repo}_error.log',
		),
		'web2' => array(
			'ssh_host' => 'web2.sayu.co.uk', 'ssh_user' => 'tema',
			'wc_base'  => '/var/vhosts',
			'log_path' => '/var/log/vhosts/{repo}/error.log',
			// These sites are reached publicly via web2's userdir path, not their own domain, so the
			// client file API's base_url uses this pattern (files land in {wc_base}/{repo}/public_html).
			'public_base' => 'https://web2.sayu.co.uk/~{repo}/',
		),
	);
}

// repository => crontab owner (per-site system user). '' / missing = not yet confirmed.
function svn_site_cron_users() {
	return array(
		'hotel-buyer-store.co.uk'       => 'hbstore',
		'officesupplystore.co.uk'       => 'offstore',
		'caresupplystore.co.uk'         => 'csupstore',
		'restaurantsupplystore.co.uk'   => 'rsstore',
		'restaurantstore.co.uk'         => 'rstore',
		'puregusto.co.uk'               => 'puregusto',
		'coffeesupplies.co.uk'          => 'coffeesupp',
		'dev.puregusto.co.uk'           => '',          // TODO: confirm crontab owner
		'rubberduckbathrooms.co.uk'     => 'rubber',
		'dev.rubberduckbathrooms.co.uk' => 'rubber',
		'richdiamonds.com'              => 'richdiamonds',
		'watchcentre.com'               => 'watchcentre',
		'tressoro.com'                  => 'tressoro',
	);
}

/** Resolve a repo to its host server config (with 'key'), or null if it uses the default gateway. */
function svn_host_for($repo) {
	$map = svn_site_host_map();
	if (!isset($map[$repo])) return null;
	$servers = svn_host_servers();
	$key = $map[$repo];
	if (!isset($servers[$key]) || $servers[$key]['ssh_host'] === '') return null;
	$s = $servers[$key];
	$s['key'] = $key;
	return $s;
}

/** Resolved error-log path for a hosted repo, or '' if not hosted/known. */
function svn_host_log_path($repo) {
	$h = svn_host_for($repo);
	if (!$h || $h['log_path'] === '') return '';
	return str_replace('{repo}', $repo, $h['log_path']);
}

/** Resolved log path where a hosted repo's PHP errors land (php_log_path), or '' if not configured. */
function svn_host_php_log_path($repo) {
	$h = svn_host_for($repo);
	if (!$h || empty($h['php_log_path'])) return '';
	return str_replace('{repo}', $repo, $h['php_log_path']);
}

/** Resolved working-copy dir for a hosted repo, or '' if not hosted/known. */
function svn_host_wc_dir($repo) {
	$h = svn_host_for($repo);
	if (!$h || $h['wc_base'] === '') return '';
	return rtrim($h['wc_base'], '/') . '/' . $repo;
}

/** Crontab owner for a hosted repo, or '' if not hosted/confirmed. */
function svn_host_cron_user($repo) {
	if (!svn_host_for($repo)) return '';
	$u = svn_site_cron_users();
	return isset($u[$repo]) ? $u[$repo] : '';
}

/**
 * Given an absolute file path (as seen in an error log), find which mapped site it belongs
 * to by matching the working-copy dir prefix. Returns array(host, repo, rel) or null.
 * The repo name in the path disambiguates hosts that share a base dir (e.g. /mnt/drive2/vhosts).
 */
function svn_host_for_path($path) {
	$path = (string) $path;
	if ($path === '' || strpos($path, "\0") !== false || strpos($path, '..') !== false) return null;
	foreach (svn_site_host_map() as $repo => $key) {
		$wc = svn_host_wc_dir($repo);
		if ($wc === '') continue;
		if (strpos($path, $wc . '/') === 0) {
			return array('host' => svn_host_for($repo), 'repo' => $repo, 'rel' => substr($path, strlen($wc) + 1));
		}
	}
	return null;
}

/**
 * Shared "common" repositories that are checked out on MULTIPLE servers besides web1
 * (e.g. the Common8 shared library) and must be fanned out to each on deploy. web1 is
 * always updated via the normal gateway; this lists the ADDITIONAL off-web1 working copies.
 * Returns array of array('key'=>server key in svn_host_servers(), 'wc'=>absolute WC path).
 *
 * NB: the Common8 WC lives at /var/vhosts/Common8 on every dedicated box (NOT under the
 * per-server wc_base), so the path is given explicitly. rubberduck has no Common8 WC.
 * Common (the older library) is only checked out on rss besides web1.
 */
function svn_shared_repo_deploys($repo) {
	$map = array(
		'Common8' => array(
			array('key' => 'rss',       'wc' => '/var/vhosts/Common8'),
			array('key' => 'puregusto', 'wc' => '/var/vhosts/Common8'),
			array('key' => 'web2',      'wc' => '/var/vhosts/Common8'),
		),
		'Common' => array(
			array('key' => 'rss',       'wc' => '/var/vhosts/Common'),
		),
	);
	return isset($map[$repo]) ? $map[$repo] : array();
}

/**
 * Shell script (run on the dedicated server, via svn_host_ssh) that updates one shared-repo working
 * copy without ever merging into a file that was edited on that server.
 *
 * Every box carries its own uncommitted EnvironmentBaseParams.php (the hostname -> PRODUCTION branch
 * that tells the shared library it's live). svn would happily merge an incoming change into such a
 * file, and a conflict under --non-interactive is postponed: the markers land in live PHP and every
 * site on the box goes down. So `svn status -u` runs first, and if any locally changed or conflicted
 * path also has an incoming change the server is SKIPPED and the paths are named instead. A local edit
 * that is already byte-identical to HEAD (a fix hand-applied on the box and then committed) is no
 * clash: svn records it as merged, so it doesn't hold the update back.
 *
 * A WC still pointing at svn://localhost (no svnserve on the dedicated boxes, so every update failed)
 * is relocated to web1's public svnserve first; relocate only rewrites the stored URL.
 */
function svn_shared_update_sh($wc, $auth) {
	return 'cd ' . escapeshellarg($wc) . ' || exit 1; export LC_ALL=en_US.UTF-8; '
		. 'root=$(sudo svn info --show-item repos-root-url . 2>&1) || { echo "$root" | head -n 1; exit 1; }; '
		. 'case "$root" in svn://localhost/*) '
		.   'sudo svn relocate svn://localhost/ svn://web1.sayu.co.uk/ ' . $auth . ' 2>&1 | head -n 2 && '
		.   'echo "Relocated $root -> svn://web1.sayu.co.uk/${root#svn://localhost/}";; esac; '
		. 'st=$(sudo svn status -u ' . $auth . ' 2>&1) || { echo "$st" | grep "^svn:" | head -n 2; exit 1; }; '
		// col 1 = local state (M/C/R/D/!/~ = edited, conflicted, replaced, deleted, missing, obstructed),
		// col 9 = '*' incoming; the path follows the working revision
		. 'clash=""; while IFS= read -r p; do [ -n "$p" ] || continue; '
		.   'if [ -f "$p" ] && [ "$(sudo svn cat -r HEAD ' . $auth . ' -- "$p" 2>/dev/null | md5sum)" = "$(sudo cat -- "$p" | md5sum)" ]; then continue; fi; '
		.   'clash="${clash:+$clash, }$p"; '
		. 'done <<EOF' . "\n"
		. '$(printf "%s\n" "$st" | awk \'substr($0,1,1) ~ /[MCRD!~]/ && substr($0,9,1) == "*" { l = $0; sub(/^.{9} *[0-9-]* +/, "", l); print l }\')' . "\n"
		. 'EOF' . "\n"
		. 'if [ -n "$clash" ]; then echo "SKIPPED - incoming changes to files edited on this server, resolve by hand: $clash"; exit 0; fi; '
		. 'sudo svn update --force ' . $auth . ' 2>&1';
}

/** SSH command prefix for a resolved host config (uses the monitor user's key). */
function svn_host_ssh($host) {
	$key   = "/mnt/drive2/vhosts/monitor.sayu.co.uk/.ssh/id_ed25519";
	$known = "/mnt/drive2/vhosts/monitor.sayu.co.uk/.ssh/known_hosts";
	return "ssh -i " . escapeshellarg($key)
		. " -o BatchMode=yes -o ConnectTimeout=15 -o StrictHostKeyChecking=yes"
		. " -o UserKnownHostsFile=" . escapeshellarg($known)
		. " " . escapeshellarg($host['ssh_user'] . "@" . $host['ssh_host']);
}
