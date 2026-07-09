<?php
/*
	AJAX (JSON): health / technical params of the server(s) that host a site (domain).

	Every site sits on two boxes:
	  - web  : the box that serves the files. web1 = this monitor host (metrics read locally,
	           no root needed); off-web1 sites (svn_hosts.php map) are read over SSH as tema.
	           Reports OS, kernel, uptime, load, cores, live CPU%, memory, swap, disks.
	  - db   : where the site's database lives.
	           * web1 sites use the shared "hosting-db" server — queried through the app's own
	             monitor connection (version + global status); OS-level metrics aren't reachable.
	           * off-web1 sites have a local MySQL/MariaDB — read via `sudo mysql` on that box,
	             so we also get the schema count and total data size.

	All remote/local shell runs pipe a base64 script into bash (avoids quoting issues), same
	pattern as dev_copy_info.php.

	@param repository   the domain (repo name)

	Returns { ok, repository, web:{...}, db:{...} }
*/

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");
include ("./svn_hosts.php");

header('Content-Type: application/json; charset=utf-8');
function sh_json($a) { echo json_encode($a); exit; }

$repository = trim((string) GetParam("repository"));
if ($repository === '' || !preg_match('/^[A-Za-z0-9.\-]+$/', $repository) || strpos($repository, '..') !== false) {
	sh_json(array('ok' => false, 'error' => 'Invalid repository.'));
}

// ---- the OS metrics collector (prints tagged lines). No root required. ----
$sh_web = <<<'SH'
export LC_ALL=C
echo "HOST $(hostname)"
echo "KERNEL $(uname -r)"
( . /etc/os-release 2>/dev/null; echo "OS ${PRETTY_NAME}" )
echo "UPTIME $(cut -d. -f1 /proc/uptime 2>/dev/null)"
read la1 la5 la15 rest < /proc/loadavg 2>/dev/null; echo "LOAD $la1 $la5 $la15"
echo "CORES $(nproc 2>/dev/null)"
read cpu u nq s id io ir sq st g gn < /proc/stat 2>/dev/null
t1=$((u+nq+s+id+io+ir+sq)); i1=$((id+io))
sleep 0.3
read cpu u nq s id io ir sq st g gn < /proc/stat 2>/dev/null
t2=$((u+nq+s+id+io+ir+sq)); i2=$((id+io))
dt=$((t2-t1)); di=$((i2-i1))
[ "$dt" -gt 0 ] && echo "CPU $(( (100*(dt-di))/dt ))"
awk '/^MemTotal:|^MemAvailable:|^SwapTotal:|^SwapFree:/{print "MEMINFO " $1 " " $2}' /proc/meminfo 2>/dev/null
df -P -x tmpfs -x devtmpfs -x squashfs -x overlay 2>/dev/null | awk 'NR>1 && $2+0>0 {print "DISK " $6 " " $2 " " $3 " " $4}'
SH;

// ---- local MySQL stats via sudo (off-web1 boxes only). ----
$sh_db = <<<'SH'
if command -v mysql >/dev/null 2>&1; then
  echo "DBVERSION $(sudo -n mysql -N -B -e 'SELECT VERSION()' 2>/dev/null)"
  sudo -n mysql -N -B -e "SHOW GLOBAL STATUS WHERE Variable_name IN ('Uptime','Threads_connected','Threads_running','Questions','Slow_queries','Connections')" 2>/dev/null | awk '{print "DBSTAT " $1 " " $2}'
  sudo -n mysql -N -B -e "SHOW GLOBAL VARIABLES WHERE Variable_name='max_connections'" 2>/dev/null | awk '{print "DBSTAT " $1 " " $2}'
  sudo -n mysql -N -B -e "SELECT COUNT(*) FROM information_schema.schemata" 2>/dev/null | awk '{print "DBSCHEMAS " $1}'
  sudo -n mysql -N -B -e "SELECT IFNULL(ROUND(SUM(data_length+index_length)/1048576),0) FROM information_schema.tables" 2>/dev/null | awk '{print "DBSIZE " $1}'
fi
SH;

function sh_run_b64($prefix, $script) {
	$cmd = $prefix . ' ' . escapeshellarg('echo ' . escapeshellarg(base64_encode($script)) . ' | base64 -d | bash');
	$out = array(); $rc = 0;
	@exec($cmd . ' 2>/dev/null', $out, $rc);
	return $out;
}

$host = svn_host_for($repository); // non-null => off-web1
$out = array();
if ($host) {
	// One SSH round-trip: web metrics + local DB metrics.
	$out = sh_run_b64(svn_host_ssh($host), $sh_web . "\n" . $sh_db);
} else {
	// web1 = this host: run the metrics locally through bash.
	$cmd = 'echo ' . escapeshellarg(base64_encode($sh_web)) . ' | base64 -d | bash';
	@exec($cmd . ' 2>/dev/null', $out, $rc);
}

// ---- parse tagged lines ----
// NB: this file runs in global scope, where $db is the app's monitor DB connection — keep it
// untouched (we query it below for the shared DB card) and collect the result into $dbinfo.
$web    = array('disks' => array());
$dbinfo = array();
foreach ($out as $line) {
	$line = trim($line);
	if ($line === '') continue;
	$sp = strpos($line, ' ');
	$tag = $sp === false ? $line : substr($line, 0, $sp);
	$val = $sp === false ? '' : substr($line, $sp + 1);
	switch ($tag) {
		case 'HOST':   $web['hostname'] = $val; break;
		case 'KERNEL': $web['kernel'] = $val; break;
		case 'OS':     $web['os'] = $val; break;
		case 'UPTIME': $web['uptime_s'] = (int) $val; break;
		case 'CORES':  $web['cores'] = (int) $val; break;
		case 'CPU':    $web['cpu_pct'] = (int) $val; break;
		case 'LOAD':   $web['load'] = array_map('floatval', preg_split('/\s+/', trim($val))); break;
		case 'MEMINFO':
			$p = preg_split('/\s+/', trim($val));
			if (count($p) >= 2) {
				$k = rtrim($p[0], ':'); $kb = (int) $p[1];
				if ($k === 'MemTotal')     $web['mem_total_kb'] = $kb;
				elseif ($k === 'MemAvailable') $web['mem_avail_kb'] = $kb;
				elseif ($k === 'SwapTotal') $web['swap_total_kb'] = $kb;
				elseif ($k === 'SwapFree')  $web['swap_free_kb'] = $kb;
			}
			break;
		case 'DISK':
			$p = preg_split('/\s+/', trim($val));
			if (count($p) >= 4) $web['disks'][] = array('mount' => $p[0], 'total_kb' => (int) $p[1], 'used_kb' => (int) $p[2], 'avail_kb' => (int) $p[3]);
			break;
		case 'DBVERSION': if ($val !== '') $dbinfo['version'] = $val; break;
		case 'DBSCHEMAS': $dbinfo['schemas'] = (int) $val; break;
		case 'DBSIZE':    $dbinfo['size_mb'] = (int) $val; break;
		case 'DBSTAT':
			$p = preg_split('/\s+/', trim($val));
			if (count($p) >= 2) $dbinfo[strtolower($p[0])] = $p[1];
			break;
	}
}

if ($host) {
	$web['name'] = $host['ssh_host'];
	$web['is_local'] = false;
	$dbinfo['host'] = $host['ssh_host'];
	$dbinfo['kind'] = 'local';
	if (empty($dbinfo['version'])) $dbinfo['note'] = 'Local database not reachable via sudo on this server.';
} else {
	$web['name'] = 'web1 (' . (isset($web['hostname']) ? $web['hostname'] : 'this host') . ')';
	$web['is_local'] = true;
	// Shared DB server ("hosting-db"): OS metrics aren't reachable, but we can read live server
	// status through the app's own monitor connection ($db, already pointed at hosting-db).
	$dbinfo = array('host' => DATABASE_HOST, 'kind' => 'shared',
		'note' => 'Shared hosting DB — OS-level metrics and the full schema list aren\'t exposed; live server status shown.');
	if (isset($db) && is_object($db)) {
		if ($db->query("SELECT VERSION() AS v") && $db->next_record()) $dbinfo['version'] = $db->f("v");
		if ($db->query("SHOW GLOBAL STATUS WHERE Variable_name IN ('Uptime','Threads_connected','Threads_running','Questions','Slow_queries','Connections')")) {
			while ($db->next_record()) $dbinfo[strtolower($db->f("Variable_name"))] = $db->f("Value");
		}
		if ($db->query("SHOW GLOBAL VARIABLES WHERE Variable_name='max_connections'")) {
			while ($db->next_record()) $dbinfo['max_connections'] = $db->f("Value");
		}
	}
}

if (function_exists('ensure_utf8')) {
	foreach (array('hostname','os','kernel','name') as $k) if (isset($web[$k])) $web[$k] = ensure_utf8($web[$k]);
	foreach (array('version','host','note') as $k) if (isset($dbinfo[$k])) $dbinfo[$k] = ensure_utf8($dbinfo[$k]);
}

sh_json(array('ok' => true, 'repository' => $repository, 'web' => $web, 'db' => $dbinfo));
