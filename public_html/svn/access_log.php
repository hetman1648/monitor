<?php
/*
	AJAX (JSON): search a site's web access log (combined format) — last N requests, filtered.

	web1 sites are read locally from /mnt/drive2/logs/<repo>/: nginx_access.log since the site went
	behind nginx, {ssl_access,access}.log before it (svn_access_log_window); off-web1 sites
	over SSH (sudo) from their server's access_log_path (see svn_hosts.php). Rotated files (.1, .N.gz)
	are pulled in when the date range reaches back past the current file.

	@param repository   the domain (repo name)
	@param from, to     'YYYY-MM-DDTHH:MM' (UK time), optional. No 'from' = the current log only.
	@param ip           exact IP, or a prefix ending in '.', ':' or '*'  (66.249.  /  2a03:2880:*)
	@param url          substring of the request path + query (case-insensitive)
	@param text         substring of referer or user agent (case-insensitive)
	@param status       comma list of codes / classes, '!' to exclude: 404 | 4xx,5xx | !200,!304
	@param method       GET / POST / ...
	@param nobots       1 = drop crawler / script user agents
	@param nostatic     1 = drop images, css, js, fonts
	@param limit        rows to return (newest), 50..2000, default 300

	-> { ok, rows:[{t,time,ip,m,path,s,b,ref,ua}], total, scanned, first, last, statuses:{code:n},
	     ips:[[ip,n]], paths:[[path,n]], files:[...], note }            (rows newest-first)

	The whole match set is counted (total / statuses / top IPs / paths); only the newest `limit`
	rows are sent back.
*/

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");
include ("./svn_hosts.php");

header('Content-Type: application/json; charset=utf-8');
function al_json($a) { echo json_encode($a); exit; }

$repository = trim((string) GetParam("repository"));
if ($repository === '' || !preg_match('/^[A-Za-z0-9.\-]+$/', $repository) || strpos($repository, '..') !== false) {
	al_json(array('ok' => false, 'error' => 'Invalid repository.'));
}
// A wide range means several hundred MB of log over SSH: don't hold the session lock across it, or
// every other request from this browser queues behind the search.
session_write_close();

$tz = new DateTimeZone('Europe/London');
function al_epoch($s, $tz) {
	$s = trim((string) $s);
	if ($s === '') return 0;
	$d = DateTime::createFromFormat('Y-m-d\TH:i', substr($s, 0, 16), $tz);
	return $d ? $d->getTimestamp() : -1;
}
$from = al_epoch(GetParam("from"), $tz);
$to   = al_epoch(GetParam("to"), $tz);
if ($from < 0 || $to < 0) al_json(array('ok' => false, 'error' => 'Invalid date.'));
if ($from && $to && $to < $from) al_json(array('ok' => false, 'error' => '"To" is before "From".'));

$ip = trim((string) GetParam("ip"));
if ($ip !== '' && !preg_match('/^[0-9A-Fa-f:.]{1,45}\*?$/', $ip)) al_json(array('ok' => false, 'error' => 'IP can only be digits, dots / colons, with an optional trailing *.'));
$method = strtoupper(trim((string) GetParam("method")));
if ($method !== '' && !preg_match('/^[A-Z]{1,12}$/', $method)) al_json(array('ok' => false, 'error' => 'Invalid method.'));
$status = strtolower(preg_replace('/\s+/', '', (string) GetParam("status")));
if ($status !== '' && !preg_match('/^!?[1-5][0-9x]{2}(,!?[1-5][0-9x]{2})*$/', $status)) al_json(array('ok' => false, 'error' => 'Status: codes or classes separated by commas, e.g. 404 or 4xx,5xx or !200.'));
// free text: passed to awk through the environment (never parsed as a program or regex); control chars dropped
$url  = (string) substr(preg_replace('/[\x00-\x1f\x7f]/', '', (string) GetParam("url")), 0, 300);
$text = (string) substr(preg_replace('/[\x00-\x1f\x7f]/', '', (string) GetParam("text")), 0, 300);
$nobots   = GetParam("nobots") ? 1 : 0;
$nostatic = GetParam("nostatic") ? 1 : 0;
$limit = (int) GetParam("limit");
if ($limit <= 0) $limit = 300;
$limit = max(50, min(2000, $limit));

$bases = svn_access_log_paths($repository);
if (!$bases) al_json(array('ok' => false, 'error' => 'Access log location not configured for this site yet.'));
$host = svn_host_for($repository); // non-null => off-web1, over SSH
$sudo = $host ? 'sudo ' : '';

// Run a bash script here or on the site's server. stdout lines back.
function al_run($host, $script, &$rc) {
	$out = array(); $rc = 0;
	$cmd = 'bash -c ' . escapeshellarg($script);
	if ($host) exec(svn_host_ssh($host) . ' ' . escapeshellarg($cmd) . ' 2>/dev/null', $out, $rc);
	else       exec($cmd . ' 2>/dev/null', $out, $rc);
	return $out;
}

// 1) Which files: each base plus its rotations, with mtimes. File k (0 = current) holds the requests
//    between mtime(k+1) and mtime(k), so it's needed if it ends after 'from' and starts before 'to'.
$ls = '';
foreach ($bases as $b) {
	$ls .= $sudo . 'find ' . escapeshellarg(dirname($b)) . ' -maxdepth 1 -type f -name ' . escapeshellarg(basename($b) . '*') . " -printf '%T@ %s %p\\n'; ";
}
$rc = 0;
$listing = al_run($host, $ls, $rc);
$MAX_FILES = 12;
$note = '';
$plan = array(); // base => list of files, oldest first
$win = array();  // base => array(from, to) actually searched: the request's range cut to the log's own window
foreach ($bases as $b) {
	list($wf, $wt) = svn_access_log_window($repository, $b);
	$bf = max($from, $wf);
	$bt = ($to && $wt) ? min($to, $wt) : ($to ? $to : $wt);
	if ($bf && $bt && $bt < $bf) continue;           // the range lies wholly outside what this log covers
	$win[$b] = array($bf, $bt);
	$rot = array();
	foreach ($listing as $l) {
		$p = explode(' ', $l, 3);
		if (count($p) < 3 || strpos($p[2], $b) !== 0) continue;
		$suffix = (string) substr($p[2], strlen($b)); // PHP < 8: false, not '', at end of string
		if ($suffix === '') $k = 0;
		elseif (preg_match('/^\.(\d+)(\.gz)?$/', $suffix, $m)) $k = (int) $m[1];
		else continue;
		$rot[$k] = array('path' => $p[2], 'mtime' => (int) $p[0], 'size' => (float) $p[1]);
	}
	if (!isset($rot[0])) continue;
	ksort($rot);
	$pick = array();
	foreach ($rot as $k => $f) {
		$starts = isset($rot[$k + 1]) ? $rot[$k + 1]['mtime'] : 0;
		if (!$from) {
			// no range: the current file, plus the previous one if the current was only just rotated in
			if ($k === 0 || ($k === 1 && $rot[0]['size'] < 5000000)) $pick[] = $f['path'];
			continue;
		}
		if ($f['mtime'] < $from) break;              // this and every older file ended before 'from'
		if ($bt && $starts > $bt) continue;          // starts after 'to'
		$pick[] = $f['path'];
	}
	if (count($pick) > $MAX_FILES) {
		$pick = array_slice($pick, 0, $MAX_FILES);
		$note = 'Range trimmed to the newest ' . $MAX_FILES . ' log files — the oldest part of it was not searched.';
	}
	if ($pick) $plan[$b] = array_reverse($pick);    // oldest first, so the stream runs in time order
}
if (!$plan) al_json(array('ok' => true, 'rows' => array(), 'total' => 0, 'scanned' => 0, 'files' => array(), 'note' => 'No access log found for this site.'));

// 2) One pass per base: zcat -f (plain or gz) | optional grep pre-filter | awk (filter, count, keep
//    the newest `limit`). Filters travel in the environment so nothing user-typed is ever program text.
$AWK = <<<'AWK'
BEGIN{
  L=ENVIRON["F_LIMIT"]+0; if(L<1) L=300
  fip=ENVIRON["F_IP"]; ippre=0
  if(fip ~ /\*$/){ fip=substr(fip,1,length(fip)-1); ippre=1 } else if(fip ~ /[.:]$/) ippre=1
  furl=tolower(ENVIRON["F_URL"]); ftext=tolower(ENVIRON["F_TEXT"]); fmeth=ENVIRON["F_METHOD"]
  from=ENVIRON["F_FROM"]+0; to=ENVIRON["F_TO"]+0; nobots=ENVIRON["F_NOBOTS"]+0; nostatic=ENVIRON["F_NOSTATIC"]+0
  ns=(ENVIRON["F_STATUS"]=="")?0:split(ENVIRON["F_STATUS"], st, ",")
  split("Jan Feb Mar Apr May Jun Jul Aug Sep Oct Nov Dec", mn, " "); for(i=1;i<=12;i++) mon[mn[i]]=i
  n=0; scanned=0; first=0; last=0
}
{
  lb=index($0,"["); if(!lb) next
  ts=substr($0,lb+1,26)
  m=mon[substr(ts,4,3)]; if(!m) next
  d=substr(ts,1,2)+0; y=substr(ts,8,4)+0; hh=substr(ts,13,2)+0; mi=substr(ts,16,2)+0; ss=substr(ts,19,2)+0
  os=substr(ts,23,2)*3600+substr(ts,25,2)*60; if(substr(ts,22,1)=="-") os=-os
  yy=y-(m<=2); era=int(yy/400); yoe=yy-era*400
  doy=int((153*(m+(m>2?-3:9))+2)/5)+d-1; doe=yoe*365+int(yoe/4)-int(yoe/100)+doy
  e=(era*146097+doe-719468)*86400+hh*3600+mi*60+ss-os
  scanned++; if(!first) first=e; last=e
  if(from && e<from) next
  if(to && e>to) next
  ip=$1
  if(fip!=""){ if(ippre){ if(index(ip,fip)!=1) next } else if(ip!=fip) next }
  # apache/nginx write a quote inside a field as \" (attack payloads are full of them): park those
  # before splitting on quotes, put them back after
  ln=$0; gsub(/\\"/,"\001",ln)
  nq=split(ln,q,"\"")
  req=q[2]; split(q[3],sb," "); status=sb[1]; bytes=sb[2]
  ref=(nq>=4)?q[4]:""; ua=(nq>=6)?q[6]:""
  gsub(/\001/,"\\\"",req); gsub(/\001/,"\\\"",ref); gsub(/\001/,"\\\"",ua)
  sp=index(req," "); meth=sp?substr(req,1,sp-1):""; path=sp?substr(req,sp+1):req
  if(match(path,/ HTTP\/[0-9.]+$/)) path=substr(path,1,RSTART-1)
  if(fmeth!="" && meth!=fmeth) next
  if(furl!="" && index(tolower(path),furl)==0) next
  if(ftext!="" && index(tolower(ref "\t" ua),ftext)==0) next
  if(nobots && tolower(ua) ~ /bot|crawl|spider|slurp|facebookexternalhit|googleother|google-|mediapartners|preview|semrush|ahrefs|bytespider|dataforseo|headless|python|curl\/|wget|go-http|java\/|scrapy|okhttp|libwww|^-?$/) next
  pq=index(path,"?"); p0=pq?substr(path,1,pq-1):path
  if(nostatic && tolower(p0) ~ /\.(jpe?g|png|gif|webp|avif|svg|ico|css|js|map|woff2?|ttf|eot|otf|mp4|webm)$/) next
  if(ns){
    pos=0; hit=0
    for(i=1;i<=ns;i++){
      t=st[i]; neg=0; if(substr(t,1,1)=="!"){ neg=1; t=substr(t,2) }
      mt=(substr(t,2)=="xx") ? (substr(status,1,1)==substr(t,1,1)) : (status==t)
      if(neg){ if(mt) next } else { pos=1; if(mt) hit=1 }
    }
    if(pos && !hit) next
  }
  n++; cs[status]++; ci[ip]++; cp[p0]++
  gsub(/\t/," ",path); gsub(/\t/," ",ref); gsub(/\t/," ",ua)
  rb[n%L]=e "\t" ip "\t" meth "\t" path "\t" status "\t" bytes "\t" ref "\t" ua
}
END{
  print "#T\t" n "\t" scanned "\t" first "\t" last
  for(s in cs) print "#S\t" s "\t" cs[s]
  PROCINFO["sorted_in"]="@val_num_desc"
  k=0; for(x in ci){ if(++k>25) break; print "#I\t" x "\t" ci[x] }
  k=0; for(x in cp){ if(++k>25) break; print "#P\t" x "\t" cp[x] }
  for(i=(n>L?n-L+1:1);i<=n;i++) print rb[i%L]
}
AWK;

$env = array(
	'F_LIMIT' => $limit, 'F_IP' => $ip, 'F_URL' => $url, 'F_TEXT' => $text, 'F_METHOD' => $method,
	'F_STATUS' => $status, 'F_NOBOTS' => $nobots, 'F_NOSTATIC' => $nostatic,
);
$exports = '';
foreach ($env as $k => $v) $exports .= 'export ' . $k . '=' . escapeshellarg((string) $v) . '; ';
// cheap fixed-string pre-filter: grep skips most lines far faster than awk parses them
$pre = '';
if ($ip !== '')   $pre = ' | LC_ALL=C grep -aF -e "${F_IP%\*}"';
elseif ($url !== '') $pre = ' | LC_ALL=C grep -aiF -e "$F_URL"';

// Nothing to filter on: the newest `limit` lines of each current file are the answer, so tail them
// instead of parsing a few hundred MB (the counts then cover just those rows).
$quick = (!$from && !$to && $ip === '' && $url === '' && $text === '' && $method === '' && $status === '' && !$nobots && !$nostatic);
if ($quick) foreach ($plan as $b => $list) $plan[$b] = array($list[count($list) - 1]);

$TIMEOUT = 120;
$rows = array(); $statuses = array(); $ips = array(); $paths = array();
$total = 0; $scanned = 0; $first = 0; $last = 0; $files = array(); $timedout = false;
foreach ($plan as $b => $list) {
	$q = '';
	foreach ($list as $f) { $q .= ' ' . escapeshellarg($f); $files[] = $f; }
	$read = $quick ? $sudo . 'tail -n ' . $limit . ' --' . $q : $sudo . 'zcat -f --' . $q;
	$script = $exports . 'export F_FROM=' . (int) $win[$b][0] . ' F_TO=' . (int) $win[$b][1] . '; timeout ' . $TIMEOUT . ' ' . $read . $pre
		. ' | awk ' . escapeshellarg($AWK) . '; echo "#RC	${PIPESTATUS[0]}"';
	$rc = 0;
	$out = al_run($host, $script, $rc);
	foreach ($out as $line) {
		if ($line === '' ) continue;
		if ($line[0] === '#') {
			$p = explode("\t", $line);
			if ($p[0] === '#T') {
				$total += (int) $p[1]; $scanned += (int) $p[2];
				if ((int) $p[3] && (!$first || (int) $p[3] < $first)) $first = (int) $p[3];
				if ((int) $p[4] > $last) $last = (int) $p[4];
			}
			elseif ($p[0] === '#S' && count($p) >= 3) $statuses[$p[1]] = (isset($statuses[$p[1]]) ? $statuses[$p[1]] : 0) + (int) $p[2];
			elseif ($p[0] === '#I' && count($p) >= 3) $ips[$p[1]]   = (isset($ips[$p[1]]) ? $ips[$p[1]] : 0) + (int) $p[2];
			elseif ($p[0] === '#P' && count($p) >= 3) $paths[$p[1]] = (isset($paths[$p[1]]) ? $paths[$p[1]] : 0) + (int) $p[2];
			elseif ($p[0] === '#RC' && isset($p[1]) && (int) $p[1] === 124) $timedout = true;
			continue;
		}
		$p = explode("\t", $line);
		if (count($p) < 8) continue;
		$rows[] = $p;
	}
}
if ($timedout) $note = trim($note . ' The search hit the ' . $TIMEOUT . 's limit, so the newest part of the range may be missing — narrow the dates or add a filter.');

// merge streams (web1: https + http), newest first
usort($rows, function ($a, $b) { return (int) $b[0] - (int) $a[0]; });
$rows = array_slice($rows, 0, $limit);
$fmt = function ($e) use ($tz) { if (!$e) return ''; $d = new DateTime('@' . (int) $e); $d->setTimezone($tz); return $d->format('j M H:i:s'); };
$out_rows = array();
foreach ($rows as $p) {
	$out_rows[] = array(
		't' => (int) $p[0], 'time' => $fmt($p[0]), 'ip' => $p[1], 'm' => ensure_utf8($p[2]), 'path' => ensure_utf8($p[3]),
		's' => $p[4], 'b' => $p[5], 'ref' => ensure_utf8($p[6]), 'ua' => ensure_utf8($p[7]),
	);
}
if ($quick) {
	// each file's tail was counted; web1 merges two, so count again over just the rows being shown
	$total = count($out_rows); $scanned = $total; $statuses = array(); $ips = array(); $paths = array();
	foreach ($out_rows as $r) {
		$p0 = current(explode('?', $r['path'], 2));
		$statuses[$r['s']] = (isset($statuses[$r['s']]) ? $statuses[$r['s']] : 0) + 1;
		$ips[$r['ip']]     = (isset($ips[$r['ip']]) ? $ips[$r['ip']] : 0) + 1;
		$paths[$p0]        = (isset($paths[$p0]) ? $paths[$p0] : 0) + 1;
	}
	if ($out_rows) { $first = $out_rows[count($out_rows) - 1]['t']; $last = $out_rows[0]['t']; }
}
arsort($ips); arsort($paths); ksort($statuses);
$pairs = function ($a) { $r = array(); foreach (array_slice($a, 0, 25, true) as $k => $v) $r[] = array(ensure_utf8((string) $k), $v); return $r; };
al_json(array(
	'ok' => true, 'rows' => $out_rows, 'total' => $total, 'scanned' => $scanned,
	'first' => $fmt($first), 'last' => $fmt($last), 'statuses' => (object) $statuses,
	'ips' => $pairs($ips), 'paths' => $pairs($paths), 'files' => $files,
	'source' => $host ? $host['ssh_host'] : 'web1', 'note' => $note, 'quick' => $quick,
));
