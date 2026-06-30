<?php
/*
	Client File API — GET (or POST) /client_file_api/servers
	Authorization: Bearer {CLIENT_FILE_API_TOKEN}

	Returns the domain -> server map so Copilot knows which physical server each client site lives on
	(the same resolution /place uses). Sites listed in svn/svn_hosts.php live on their own server;
	everything else is served from web1 (the default), enumerated from /home/vhosts.

	Response:
	{
	  "ok": true,
	  "default_server": "web1",
	  "servers": [
	    { "key": "web1",      "default": true,  "ssh_host": "web1.sayu.co.uk", "domains": [ … ] },
	    { "key": "puregusto", "default": false, "ssh_host": "puregusto.co.uk", "wc_base": "/var/vhosts",
	      "domains": ["puregusto.co.uk","dev.puregusto.co.uk","coffeesupplies.co.uk"] },
	    …
	  ],
	  "domains": { "puregusto.co.uk": "puregusto", "completegolfer.co.uk": "web1", … }
	}

	Query param: web1=0  -> skip enumerating the (large) web1 site list; still lists the off-web1
	servers and their domains. Default is to include web1.
*/

require_once dirname(__FILE__) . '/cfa_common.php';

$method = cfa_g($_SERVER, 'REQUEST_METHOD', 'GET');
if ($method !== 'GET' && $method !== 'POST') cfa_fail('GET or POST only.', 405);
cfa_require_auth();

$include_web1 = (cfa_g($_GET, 'web1', '1') !== '0');

$map     = svn_site_host_map();    // domain => server key (off-web1 only)
$servers = svn_host_servers();     // server key => connection + path patterns

// off-web1 servers, each with the domains mapped to it
$out_servers = array();
$by_key = array();
foreach ($map as $domain => $key) {
	if (!isset($by_key[$key])) $by_key[$key] = array();
	$by_key[$key][] = $domain;
}
$domains = array();
foreach ($map as $domain => $key) { $domains[$domain] = $key; }

foreach ($by_key as $key => $doms) {
	sort($doms);
	$cfg = isset($servers[$key]) ? $servers[$key] : array();
	$out_servers[] = array(
		'key'      => $key,
		'default'  => false,
		'ssh_host' => cfa_g($cfg, 'ssh_host', ''),
		'wc_base'  => cfa_g($cfg, 'wc_base', ''),
		'domains'  => $doms,
	);
}

// web1 (default): every /home/vhosts/<domain>/public_html that isn't mapped off-web1.
$web1_domains = array();
if ($include_web1) {
	$dirs = @glob('/home/vhosts/*', GLOB_ONLYDIR);
	if (is_array($dirs)) {
		foreach ($dirs as $d) {
			$name = basename($d);
			if (strpos($name, '.') === false) continue;     // client domains contain a dot
			if (isset($map[$name])) continue;                // mapped => off-web1, skip
			if (!is_dir($d . '/public_html')) continue;       // must be a served site
			$web1_domains[$name] = true;
		}
	}
	$web1_domains = array_keys($web1_domains);
	sort($web1_domains);
	foreach ($web1_domains as $dn) { if (!isset($domains[$dn])) $domains[$dn] = 'web1'; }
}

// web1 entry first
array_unshift($out_servers, array(
	'key'      => 'web1',
	'default'  => true,
	'ssh_host' => 'web1.sayu.co.uk',
	'domains'  => $web1_domains,
));

ksort($domains);
cfa_out(array(
	'ok'             => true,
	'default_server' => 'web1',
	'servers'        => $out_servers,
	'domains'        => $domains,
));
