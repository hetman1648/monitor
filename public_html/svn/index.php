<?php

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");

// Getting available repositories list from the SVN gateway
$path = "https://web1.sayu.co.uk/svn/";
$command = "index.php?action=show&username=" . $svn_login . "&password=" . $svn_password;
$res = get_page($path . $command);

$monitor_svn_repository = GetParam("repository");
if (!$monitor_svn_repository && isset($_COOKIE["monitor_svn_repository"])) {
    $monitor_svn_repository = $_COOKIE["monitor_svn_repository"];
}

$repositories_typehead = "";
if (strpos($res, '+OK Repositories list') !== false) {
    $lines = explode("+OK Repositories list: ", $res);
    if (sizeof($lines) > 1) {
        $repositories_list = explode("\n", $lines[1]);
        foreach ($repositories_list as $repository) {
            if (strlen(trim($repository))) {
                if (strlen($repositories_typehead)) $repositories_typehead .= ",";
                $repositories_typehead .= json_encode(trim($repository));
            }
        }
    } else {
        die("No repositories available");
    }
} else {
    die("ERROR: Can't get a repository list: " . htmlspecialchars($res));
}

$user_name = GetSessionParam("UserName");

// The current user's dev subdomain (<subdomain>.sayuconnect.com) — used to build a direct link
// to their dev copy of a site in the Dev copy popup.
$dev_subdomain = '';
$db->query("SELECT svn_subdomain FROM users WHERE user_id=" . (int) $user_id);
if ($db->next_record()) $dev_subdomain = trim((string) $db->f("svn_subdomain"));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>SVN Updater — Sayu Monitor</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Hanken+Grotesk:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600&display=swap"/>
<style>
/* Match the rest of Monitor so the shared header (modern_header.php) renders in DM Sans */
body{ font-family:'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
:root{
  /* light theme (matches the site's default light mode) */
  --bg:#eef1f6; --bg-2:#e6ebf2; --panel:#ffffff; --card:#ffffff; --card-2:#f6f8fb;
  --raise:#f1f4f9; --raise-2:#e7ecf3; --line:rgba(15,23,42,.10); --line-strong:rgba(15,23,42,.16);
  --ink:#1f2733; --ink-soft:#3b4452; --muted:#64748b; --muted-2:#94a3b8;
  --acc-a:#5566d6; --acc-b:#9a6fa6; --acc-solid:#5d6fd6;
  --ok:#2f9e6b; --ok-bg:rgba(47,158,107,.14); --warn:#bf8420; --warn-bg:rgba(191,132,32,.16);
  --err:#cf4f6b; --err-bg:rgba(207,79,107,.14); --info:#2f86b8; --addv:#6b5fc4;
  --fill:rgba(15,23,42,.03); --fill-2:rgba(15,23,42,.05);
  --hover:rgba(15,23,42,.05); --hover-2:rgba(15,23,42,.08); --row-tint:rgba(15,23,42,.025);
  --r-lg:16px; --r-md:11px; --r-sm:8px;
  --shadow:0 18px 50px rgba(20,30,50,.16); --shadow-sm:0 2px 8px rgba(20,30,50,.08);
}
html.dark-mode{
  /* dark theme (the imported design) */
  --bg:#16202e; --bg-2:#1a2636; --panel:#16202d; --card:#141d29; --card-2:#1a2433;
  --raise:#1f2a3a; --raise-2:#283649; --line:rgba(255,255,255,.07); --line-strong:rgba(255,255,255,.12);
  --ink:#eef2f7; --ink-soft:#c4ccd8; --muted:#8b97a8; --muted-2:#5f6c7e;
  --acc-a:#4f63cf; --acc-b:#9a6fa6; --acc-solid:#5d6fd6;
  --ok:#44b27c; --ok-bg:rgba(68,178,124,.14); --warn:#e0a93b; --warn-bg:rgba(224,169,59,.14);
  --err:#e2657f; --err-bg:rgba(226,101,127,.14); --info:#58a9d6; --addv:#bcb1f0;
  --fill:rgba(255,255,255,.03); --fill-2:rgba(255,255,255,.06);
  --hover:rgba(255,255,255,.06); --hover-2:rgba(255,255,255,.09); --row-tint:rgba(255,255,255,.03);
  --shadow:0 18px 50px rgba(0,0,0,.40); --shadow-sm:0 2px 8px rgba(0,0,0,.25);
}
#svnApp{
  font-family:'Hanken Grotesk', system-ui, sans-serif;
  background:
     radial-gradient(1200px 540px at 78% -8%, rgba(108,92,200,.10), transparent 60%),
     radial-gradient(1000px 520px at 6% 0%, rgba(40,90,150,.10), transparent 55%),
     var(--bg);
  color:var(--ink); -webkit-font-smoothing:antialiased; min-height:100vh;
}
#svnApp *{ box-sizing:border-box; }
#svnApp button{ font-family:inherit; cursor:pointer; border:none; background:none; color:inherit; }
#svnApp input{ font-family:inherit; }
#svnApp ::selection{ background:rgba(93,111,214,.4); color:#fff; }
#svnApp .mono{ font-family:'JetBrains Mono', ui-monospace, monospace; }
#svnApp *::-webkit-scrollbar{ width:11px; height:11px; }
#svnApp *::-webkit-scrollbar-thumb{ background:rgba(255,255,255,.10); border-radius:8px; border:3px solid transparent; background-clip:padding-box; }
#svnApp *::-webkit-scrollbar-thumb:hover{ background:rgba(255,255,255,.18); background-clip:padding-box; }

/* page shell */
#svnApp .page{ max-width:1320px; margin:0 auto; padding:34px 30px 140px; }
#svnApp .page-head{ display:flex; align-items:flex-end; justify-content:space-between; gap:24px; margin-bottom:24px; flex-wrap:wrap; }
#svnApp .page-head h1{ font-size:36px; font-weight:800; letter-spacing:-1.2px; margin:0 0 6px; }
#svnApp .page-head .lede{ font-size:16px; color:var(--muted); margin:0; }
#svnApp .head-actions{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; }

#svnApp .btn{ display:inline-flex; align-items:center; justify-content:center; gap:8px; border-radius:10px; font-weight:700; font-size:14px; padding:10px 16px; transition:.15s; white-space:nowrap; color:var(--ink-soft); border:1px solid var(--line-strong); background:var(--fill); }
#svnApp .btn:hover{ background:var(--hover-2); color:var(--ink); border-color:rgba(255,255,255,.2); }
#svnApp .btn.tiny{ padding:7px 11px; font-size:13px; border-radius:8px; }
#svnApp .btn.ghost{ background:transparent; border-color:transparent; color:var(--muted); }
#svnApp .btn.ghost:hover{ background:var(--hover); color:var(--ink); }
#svnApp .btn.solid{ background:var(--acc-solid); border-color:transparent; color:#fff; }
#svnApp .btn.solid:hover{ filter:brightness(1.08); }
#svnApp .btn.grad{ background:linear-gradient(100deg,var(--acc-a),var(--acc-b)); border-color:transparent; color:#fff; }
#svnApp .btn.grad:hover{ filter:brightness(1.07); }
#svnApp .btn.danger{ color:#e2657f; }
#svnApp .btn.danger:hover{ background:var(--err-bg); border-color:rgba(226,101,127,.4); color:#f0889d; }
#svnApp .btn[disabled]{ opacity:.5; cursor:not-allowed; }

#svnApp .card{ background:var(--card); border:1px solid var(--line); border-radius:var(--r-lg); box-shadow:var(--shadow-sm); overflow:hidden; }
#svnApp .card-head{ display:flex; align-items:center; gap:12px; padding:16px 20px; border-bottom:1px solid var(--line); background:var(--fill); }
#svnApp .card-head h2{ font-size:16px; font-weight:800; letter-spacing:.2px; margin:0; }
#svnApp .card-head .ch-sub{ font-size:13px; color:var(--muted); }
#svnApp .card-head .spacer{ margin-left:auto; }

#svnApp .control{ margin-bottom:22px; overflow:visible; position:relative; z-index:5; }
#svnApp .control .card-head{ flex-wrap:wrap; row-gap:10px; }
#svnApp .ctrl-label{ display:block; font-size:12px; font-weight:800; letter-spacing:.8px; color:var(--muted-2); text-transform:uppercase; margin-bottom:11px; }
#svnApp .ctrl-finder{ padding:18px 20px; min-width:0; position:relative; }
#svnApp .recents{ display:flex; gap:6px; flex-wrap:wrap; margin-top:10px; align-items:center; }
#svnApp .recents .recents-label{ font-size:12px; color:var(--muted-2); margin-right:2px; }
#svnApp .ctrl-match{ margin-top:14px; display:flex; flex-direction:column; gap:9px; }

#svnApp .grp-dd{ position:relative; }
#svnApp .grp-dd-trigger{ display:flex; align-items:center; gap:9px; padding:9px 12px; border-radius:10px; border:1px solid var(--line-strong); background:var(--raise); font-weight:700; font-size:14px; color:var(--ink-soft); transition:.14s; }
#svnApp .grp-dd-trigger:hover{ background:var(--hover-2); color:var(--ink); }
#svnApp .grp-dd-trigger.open{ border-color:var(--acc-solid); color:var(--ink); }
#svnApp .grp-dd-trigger > svg:first-child{ color:var(--muted); }
#svnApp .grp-dd-trigger .ddt-name{ max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
#svnApp .grp-dd-trigger .ddt-count{ font-size:12px; font-weight:700; color:var(--muted-2); background:var(--hover); border-radius:20px; padding:2px 8px; }
#svnApp .grp-dd-trigger .ddt-chev{ color:var(--muted); transition:transform .18s; }
#svnApp .grp-dd-trigger.open .ddt-chev{ transform:rotate(180deg); }
#svnApp .grp-dd-backdrop{ position:fixed; inset:0; z-index:44; }
#svnApp .grp-dd-menu{ position:absolute; top:calc(100% + 8px); right:0; width:312px; max-width:88vw; background:var(--card-2); border:1px solid var(--line-strong); border-radius:13px; box-shadow:var(--shadow); padding:8px; z-index:45; display:flex; flex-direction:column; gap:2px; max-height:70vh; overflow:auto; }
#svnApp .ddm-label{ font-size:11px; font-weight:800; letter-spacing:1px; text-transform:uppercase; color:var(--muted-2); padding:6px 10px 8px; }
#svnApp .ddm-empty{ padding:4px 10px 10px; font-size:12.5px; color:var(--muted-2); line-height:1.4; }

#svnApp .input{ display:flex; align-items:center; gap:10px; background:var(--raise); border:1px solid var(--line-strong); border-radius:10px; padding:11px 14px; color:var(--ink); transition:.15s; }
#svnApp .input:focus-within{ border-color:var(--acc-solid); box-shadow:0 0 0 3px rgba(93,111,214,.18); }
#svnApp .input svg{ color:var(--muted); flex:none; }
#svnApp .input input{ flex:1; border:none; outline:none; background:none; color:var(--ink); font-size:15px; min-width:0; }
#svnApp .input input::placeholder{ color:var(--muted-2); }
#svnApp .quick-path{ display:flex; align-items:center; gap:8px; font-size:13px; color:var(--muted); flex-wrap:wrap; }
#svnApp .quick-path .mono{ color:#9fb0c4; }
#svnApp .copy-btn{ width:30px; height:30px; border-radius:7px; border:1px solid var(--line-strong); display:flex; align-items:center; justify-content:center; color:var(--muted); flex:none; }
#svnApp .copy-btn:hover{ background:var(--hover-2); color:var(--ink); }

/* finder typeahead dropdown */
#svnApp .finder-dd{ position:absolute; left:20px; right:20px; margin-top:6px; background:var(--card-2); border:1px solid var(--line-strong); border-radius:11px; box-shadow:var(--shadow); max-height:320px; overflow:auto; z-index:30; display:none; padding:6px; }
#svnApp .finder-dd.show{ display:block; }
#svnApp .finder-opt{ padding:9px 12px; border-radius:8px; font-size:14px; color:var(--ink-soft); cursor:pointer; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
#svnApp .finder-opt:hover, #svnApp .finder-opt.active{ background:var(--acc-solid); color:#fff; }

/* groups menu items */
#svnApp .grp{ display:flex; align-items:center; gap:11px; width:100%; padding:10px 12px; border-radius:9px; text-align:left; transition:.13s; color:var(--ink-soft); }
#svnApp .grp:hover{ background:var(--hover); }
#svnApp .grp.on{ background:rgba(93,111,214,.16); color:var(--ink); }
#svnApp .grp.on .grp-ico{ color:var(--info); }
#svnApp .grp-ico{ color:var(--muted); flex:none; }
#svnApp .grp-name{ font-size:14.5px; font-weight:700; flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
#svnApp .grp-count{ font-size:12px; font-weight:700; color:var(--muted-2); background:var(--hover); border-radius:20px; padding:2px 8px; }
#svnApp .grp.on .grp-count{ background:rgba(255,255,255,.12); color:#dfe5ee; }
#svnApp .grp-del{ opacity:0; color:var(--muted-2); width:22px; height:22px; border-radius:6px; display:flex; align-items:center; justify-content:center; flex:none; }
#svnApp .grp:hover .grp-del{ opacity:1; }
#svnApp .grp-del:hover{ background:var(--err-bg); color:var(--err); }
#svnApp .rail-sep{ height:1px; background:var(--line); margin:8px 4px; }
#svnApp .grp-new{ width:100%; justify-content:flex-start; margin-top:10px; }

#svnApp .main .card-head{ flex-wrap:wrap; row-gap:12px; }
#svnApp .toolbar{ display:flex; align-items:center; gap:10px; flex:1; flex-wrap:wrap; }
#svnApp .search-wrap{ flex:1; min-width:180px; max-width:340px; }
#svnApp .chk{ appearance:none; -webkit-appearance:none; width:19px; height:19px; border-radius:6px; flex:none; border:1.8px solid var(--muted-2); background:var(--raise); transition:.13s; position:relative; cursor:pointer; }
#svnApp .chk:hover{ border-color:var(--info); }
#svnApp .chk:checked{ background:var(--acc-solid); border-color:var(--acc-solid); }
#svnApp .chk:checked::after{ content:''; position:absolute; left:5px; top:1px; width:5px; height:10px; border:solid #fff; border-width:0 2.2px 2.2px 0; transform:rotate(42deg); }
#svnApp .chk.partial{ background:var(--acc-solid); border-color:var(--acc-solid); }
#svnApp .chk.partial::after{ content:''; position:absolute; left:3.5px; top:7.5px; width:9px; height:0; border-top:2.2px solid #fff; transform:none; border-right:none; }
#svnApp .chk[disabled]{ opacity:.4; cursor:not-allowed; }

#svnApp .fchips{ display:flex; gap:6px; }
#svnApp .fchip{ padding:7px 12px; border-radius:8px; font-size:13px; font-weight:700; color:var(--muted); border:1px solid transparent; }
#svnApp .fchip:hover{ background:var(--hover); color:var(--ink-soft); }
#svnApp .fchip.on{ background:var(--hover-2); color:var(--ink); border-color:var(--line-strong); }

#svnApp .vtoggle{ display:flex; background:var(--raise); border:1px solid var(--line-strong); border-radius:9px; padding:3px; gap:2px; }
#svnApp .vtoggle button{ width:36px; height:30px; border-radius:6px; display:flex; align-items:center; justify-content:center; color:var(--muted); }
#svnApp .vtoggle button.on{ background:var(--card-2); color:var(--ink); box-shadow:var(--shadow-sm); }

#svnApp .scanbar{ display:flex; align-items:center; gap:12px; padding:10px 20px; border-bottom:1px solid var(--line); background:rgba(88,169,214,.06); font-size:13px; color:var(--ink-soft); }
#svnApp .scanbar .spin{ width:14px; height:14px; }

/* unified changes table */
#svnApp .ctable{ width:100%; border-collapse:collapse; }
#svnApp .ctable-wrap{ overflow-x:auto; }
#svnApp .ctable thead th{ text-align:left; font-size:11px; font-weight:800; letter-spacing:.7px; text-transform:uppercase; color:var(--muted-2); padding:12px 16px; border-bottom:1px solid var(--line); white-space:nowrap; background:var(--fill); }
#svnApp .ctable .col-chk{ width:42px; padding-left:18px; padding-right:0; }
#svnApp .ctable .col-diff{ text-align:right; }
#svnApp .ctable td{ vertical-align:middle; }
#svnApp .site-row > td{ background:var(--row-tint); border-top:1px solid var(--line-strong); }
#svnApp .site-row:first-child > td{ border-top:none; }
#svnApp .site-row.sel > td{ background:rgba(93,111,214,.12); }
#svnApp .site-row .col-chk{ padding:0 0 0 18px; }
#svnApp .site-head-cell{ padding:0 16px 0 0; }
#svnApp .site-head{ display:flex; align-items:center; gap:12px; padding:12px 0; flex-wrap:wrap; }
#svnApp.density-compact .site-head{ padding:8px 0; }
#svnApp .site-collapse{ width:24px; height:24px; border-radius:6px; display:flex; align-items:center; justify-content:center; color:var(--muted-2); flex:none; }
#svnApp .site-collapse:hover{ background:var(--hover-2); color:var(--ink-soft); }
#svnApp .site-collapse:disabled{ opacity:.3; cursor:default; }
#svnApp .site-collapse svg{ transition:transform .18s; }
#svnApp .site-head .host{ font-size:15.5px; font-weight:700; color:var(--ink); }
#svnApp.density-compact .site-head .host{ font-size:14.5px; }
#svnApp .site-meta{ font-size:12px; color:var(--muted); display:flex; align-items:center; gap:7px; white-space:nowrap; }
#svnApp .site-meta .mdot{ width:3px; height:3px; border-radius:50%; background:var(--muted-2); flex:none; }
#svnApp .sh-spacer{ flex:1; min-width:8px; }
#svnApp .file-count{ font-size:12px; font-weight:700; color:var(--muted-2); white-space:nowrap; }
#svnApp .site-btn{ width:28px; height:28px; border-radius:7px; display:flex; align-items:center; justify-content:center; color:var(--muted-2); flex:none; }
#svnApp .site-btn:hover{ background:var(--hover-2); color:var(--ink-soft); }
#svnApp .site-btn.on{ background:rgba(93,111,214,.18); color:var(--ink); }
#svnApp a.site-btn{ text-decoration:none; }
#svnApp .site-admin:hover{ background:rgba(93,111,214,.18); color:var(--info); }

/* popovers */
#svnApp .pop-backdrop{ position:fixed; inset:0; z-index:54; }
#svnApp .pop{ position:fixed; width:262px; max-width:88vw; background:var(--card-2); border:1px solid var(--line-strong); border-radius:12px; box-shadow:var(--shadow); padding:8px; z-index:55; display:flex; flex-direction:column; gap:2px; max-height:70vh; overflow:auto; }
#svnApp .pop-title{ font-size:11px; font-weight:800; letter-spacing:.6px; text-transform:uppercase; color:var(--muted-2); padding:6px 10px 9px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
#svnApp .pop-title b{ color:var(--ink-soft); }
#svnApp .pop-item{ display:flex; align-items:center; gap:11px; padding:9px 10px; border-radius:8px; text-align:left; color:var(--ink-soft); transition:.12s; }
#svnApp .pop-item:hover{ background:var(--hover); }
#svnApp .pop-item.on{ color:var(--ink); }
#svnApp .pop-check{ width:19px; height:19px; border-radius:6px; border:1.7px solid var(--muted-2); display:flex; align-items:center; justify-content:center; color:#fff; flex:none; transition:.12s; }
#svnApp .pop-item.on .pop-check{ background:var(--acc-solid); border-color:var(--acc-solid); }
#svnApp .pop-name{ flex:1; font-size:14px; font-weight:600; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
/* domain right-click context menu */
#svnApp .svnctx{ position:fixed; min-width:212px; max-width:92vw; background:var(--card-2); border:1px solid var(--line-strong); border-radius:12px; box-shadow:var(--shadow); padding:6px; z-index:60; display:flex; flex-direction:column; gap:1px; }
#svnApp .svnctx-head{ font-size:12px; color:var(--ink-soft); padding:6px 10px 7px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; border-bottom:1px solid var(--line); margin-bottom:3px; }
#svnApp .svnctx-sec{ font-size:10px; font-weight:800; letter-spacing:.6px; text-transform:uppercase; color:var(--muted-2); padding:7px 10px 3px; }
#svnApp .svnctx-item{ display:flex; align-items:center; gap:10px; padding:8px 10px; border-radius:8px; text-align:left; color:var(--ink-soft); font-size:13.5px; font-weight:600; transition:.12s; cursor:pointer; }
#svnApp .svnctx-item:hover{ background:var(--hover); color:var(--ink); }
#svnApp .svnctx-ic{ color:var(--muted); flex:none; display:inline-flex; }
#svnApp .svnctx-item .svnctx-sub{ margin-left:auto; font-size:11px; font-weight:500; color:var(--muted-2); max-width:120px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
#svnApp .svnctx-pending{ pointer-events:none; opacity:.6; }
#svnApp .svnctx-pending .svnctx-ic{ color:var(--muted-2); }
#svnApp .svnctx-disabled{ pointer-events:none; opacity:.4; }
#svnApp .host{ cursor:context-menu; }
#svnApp .svnctx-toast{ position:fixed; z-index:61; background:var(--ink); color:var(--card); font-size:12.5px; font-weight:700; padding:7px 12px; border-radius:8px; box-shadow:var(--shadow); pointer-events:none; opacity:0; transition:opacity .12s; }
#svnApp .svnctx-toast.show{ opacity:.96; }

#svnApp .sbadge{ display:inline-flex; align-items:center; gap:7px; font-size:12px; font-weight:800; letter-spacing:.2px; padding:5px 11px; border-radius:20px; white-space:nowrap; }
#svnApp .sbadge .sd{ width:7px; height:7px; border-radius:50%; }
#svnApp .sbadge.update{ background:var(--warn-bg); color:var(--warn); } #svnApp .sbadge.update .sd{ background:var(--warn); }
#svnApp .sbadge.current{ background:var(--ok-bg); color:var(--ok); } #svnApp .sbadge.current .sd{ background:var(--ok); }
#svnApp .sbadge.error{ background:var(--err-bg); color:var(--err); } #svnApp .sbadge.error .sd{ background:var(--err); }
#svnApp .sbadge.idle{ background:var(--hover); color:var(--muted); } #svnApp .sbadge.idle .sd{ background:var(--muted-2); }
#svnApp .sbadge.scanning{ background:rgba(88,169,214,.14); color:var(--info); }

#svnApp .behind{ font-size:13px; font-weight:700; color:var(--warn); display:flex; align-items:center; gap:6px; white-space:nowrap; }
#svnApp .behind .rev{ color:var(--muted-2); font-weight:600; }
#svnApp .behind.none{ color:var(--muted-2); }

#svnApp .file-row td{ border-bottom:1px solid var(--line); font-size:13.5px; padding:9px 16px; }
#svnApp.density-compact .file-row td{ padding:6px 16px; }
#svnApp .file-row:hover td{ background:var(--hover); }
#svnApp .file-row .fp-dir{ color:var(--muted); font-size:12.5px; white-space:nowrap; }
#svnApp .file-row .fp-name{ color:var(--ink); font-weight:600; }
#svnApp .file-row .file-open{ background:none; border:0; padding:0; margin:0; font:inherit; color:var(--ink); font-weight:600; cursor:pointer; text-align:left; }
#svnApp .file-row .file-open:hover{ color:var(--info); text-decoration:underline; }
#svnApp .file-row .file-copy{ background:none; border:0; padding:2px; margin-left:7px; color:var(--muted-2); cursor:pointer; vertical-align:middle; opacity:0; transition:opacity .12s, color .12s; }
#svnApp .file-row:hover .file-copy{ opacity:1; }
#svnApp .file-row .file-copy:hover{ color:var(--ink); }
#svnApp .file-row .file-copy.ok{ color:var(--ok); opacity:1; }
#svnApp .file-row .rev{ color:var(--muted); font-size:13px; }
#svnApp .fstat{ display:inline-flex; align-items:center; font-size:10.5px; font-weight:800; letter-spacing:.5px; text-transform:uppercase; padding:5px 11px; border-radius:20px; white-space:nowrap; }
#svnApp .fstat.not-on-server,#svnApp .fstat.to-add{ background:rgba(124,108,214,.2); color:var(--addv); }
#svnApp .fstat.modified{ background:var(--warn-bg); color:var(--warn); }
#svnApp .fstat.to-delete,#svnApp .fstat.conflict,#svnApp .fstat.missing{ background:var(--err-bg); color:var(--err); }
#svnApp .fstat.not-in-svn,#svnApp .fstat.locked,#svnApp .fstat.replaced,#svnApp .fstat.type-change,#svnApp .fstat.default{ background:var(--hover-2); color:var(--ink-soft); }
#svnApp .vdiff{ color:var(--info); font-weight:700; font-size:13.5px; white-space:nowrap; }
#svnApp .vdiff:hover{ color:#7cc3ea; text-decoration:underline; }

#svnApp .note-row td{ padding:0 16px 12px; }
#svnApp .note-row.first-note td{ padding-top:4px; }
#svnApp .up-note{ font-size:13px; color:var(--muted-2); font-style:italic; }
#svnApp .scan-note{ font-size:13px; color:var(--muted); display:flex; align-items:center; gap:10px; }
#svnApp .err-note{ display:flex; align-items:center; gap:10px; padding:12px 14px; border-radius:10px; background:var(--err-bg); border:1px solid rgba(226,101,127,.25); color:var(--err); font-size:13.5px; font-weight:600; }

/* cards */
#svnApp .cards{ padding:18px; display:grid; grid-template-columns:repeat(auto-fill,minmax(258px,1fr)); gap:14px; }
#svnApp .scard{ background:var(--card-2); border:1px solid var(--line); border-radius:var(--r-md); padding:16px; transition:.15s; cursor:pointer; position:relative; }
#svnApp .scard:hover{ border-color:var(--line-strong); transform:translateY(-2px); }
#svnApp .scard.sel{ border-color:var(--acc-solid); box-shadow:0 0 0 3px rgba(93,111,214,.18); }
#svnApp .scard-top{ display:flex; align-items:flex-start; justify-content:space-between; gap:10px; margin-bottom:14px; }
#svnApp .scard .host{ font-size:15px; font-weight:700; color:var(--ink); word-break:break-all; line-height:1.25; }
#svnApp .scard .meta{ font-size:12px; color:var(--muted); margin-top:4px; }
#svnApp .scard-foot{ display:flex; align-items:center; justify-content:space-between; margin-top:14px; padding-top:13px; border-top:1px solid var(--line); }

/* apply bar */
#svnApp .applybar{ position:fixed; left:50%; bottom:26px; transform:translateX(-50%) translateY(160%); width:min(860px,calc(100vw - 48px)); z-index:50; display:flex; align-items:center; gap:16px; padding:13px 16px 13px 22px; background:rgba(23,32,46,.88); backdrop-filter:blur(18px) saturate(1.4); border:1px solid rgba(255,255,255,.14); border-radius:16px; box-shadow:0 24px 60px rgba(0,0,0,.55); transition:transform .32s cubic-bezier(.34,1.4,.5,1), opacity .25s; opacity:0; }
#svnApp .applybar.show{ transform:translateX(-50%) translateY(0); opacity:1; }
#svnApp .ab-count{ font-size:15px; font-weight:800; }
#svnApp .ab-count b{ color:#fff; } #svnApp .ab-count span{ color:var(--muted); font-weight:600; }
#svnApp .ab-sub{ font-size:12.5px; color:var(--warn); font-weight:700; margin-top:2px; }
#svnApp .ab-grow{ flex:1; }
#svnApp .ab-actions{ display:flex; align-items:center; gap:9px; flex-wrap:wrap; }

/* modal */
#svnApp .scrim{ position:fixed; inset:0; background:rgba(8,12,18,.66); backdrop-filter:blur(4px); z-index:60; display:flex; align-items:center; justify-content:center; padding:24px; }
#svnApp .scrim2{ z-index:70; }
#svnApp .scrim3{ z-index:80; }
#svnApp .confirm-modal{ width:min(440px,100%); }
#svnApp .confirm-body{ font-size:14px; color:var(--ink-soft); line-height:1.55; }
#svnApp .confirm-body .mono{ color:var(--ink); }
#svnApp .btn.ui-confirm-danger{ background:var(--err); color:#fff; }
#svnApp .btn.ui-confirm-danger:hover{ filter:brightness(1.08); }
#svnApp .modal{ width:min(560px,100%); max-height:86vh; display:flex; flex-direction:column; background:var(--card); border:1px solid var(--line-strong); border-radius:18px; box-shadow:var(--shadow); overflow:hidden; }
#svnApp .modal.wide{ width:min(820px,100%); }
#svnApp .modal-head{ padding:20px 22px; border-bottom:1px solid var(--line); display:flex; align-items:flex-start; gap:14px; }
#svnApp .modal-head .mh-ico{ width:42px; height:42px; border-radius:11px; display:flex; align-items:center; justify-content:center; flex:none; background:linear-gradient(135deg,rgba(79,99,207,.25),rgba(154,111,166,.25)); color:#b9b0ea; }
#svnApp .modal-head h3{ font-size:19px; font-weight:800; margin:0 0 3px; }
#svnApp .modal-head p{ font-size:13.5px; color:var(--muted); margin:0; line-height:1.4; }
#svnApp .modal-head .mh-x{ margin-left:auto; color:var(--muted); width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; flex:none; }
#svnApp .modal-head .mh-x:hover{ background:var(--hover-2); color:var(--ink); }
#svnApp .modal-body{ padding:14px 22px; overflow-y:auto; flex:1; }
#svnApp .modal-foot{ padding:16px 22px; border-top:1px solid var(--line); display:flex; align-items:center; gap:10px; }
#svnApp .modal-foot .mf-grow{ flex:1; font-size:13px; color:var(--muted); }

#svnApp .prow{ display:flex; align-items:center; gap:13px; padding:12px 2px; border-bottom:1px solid var(--line); }
#svnApp .prow:last-child{ border-bottom:none; }
#svnApp .prow .phost{ flex:1; min-width:0; }
#svnApp .prow .phost .h{ font-size:14.5px; font-weight:700; }
#svnApp .prow .phost .s{ font-size:12px; color:var(--muted); margin-top:1px; }
#svnApp .pstate{ display:flex; align-items:center; gap:8px; font-size:12.5px; font-weight:800; white-space:nowrap; }
#svnApp .pstate.queued{ color:var(--muted-2); }
#svnApp .pstate.updating{ color:var(--info); }
#svnApp .pstate.done{ color:var(--ok); }
#svnApp .pstate.failed{ color:var(--err); }
#svnApp .spin{ width:15px; height:15px; border-radius:50%; border:2px solid rgba(255,255,255,.18); border-top-color:var(--info); animation:svnspin .7s linear infinite; display:inline-block; }
@keyframes svnspin{ to{ transform:rotate(360deg); } }
#svnApp .pcheck{ width:18px; height:18px; border-radius:50%; background:var(--ok-bg); color:var(--ok); display:flex; align-items:center; justify-content:center; }
#svnApp .pfail{ width:18px; height:18px; border-radius:50%; background:var(--err-bg); color:var(--err); display:flex; align-items:center; justify-content:center; }

#svnApp .field-label{ font-size:12px; font-weight:800; letter-spacing:.7px; color:var(--muted-2); text-transform:uppercase; margin:6px 0 8px; }
#svnApp .preview-chips{ display:flex; flex-wrap:wrap; gap:7px; margin-top:14px; }
#svnApp .svn-ta{ width:100%; box-sizing:border-box; resize:vertical; min-height:120px; background:var(--raise); border:1px solid var(--line-strong); border-radius:10px; padding:11px 14px; color:var(--ink); font:13px/1.55 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; outline:none; transition:.15s; }
#svnApp .svn-ta:focus{ border-color:var(--acc-solid); box-shadow:0 0 0 3px rgba(93,111,214,.18); }
#svnApp .svn-ta::placeholder{ color:var(--muted-2); }
#svnApp .glist-preview{ margin-top:14px; }
#svnApp .glist-sum{ display:flex; flex-wrap:wrap; gap:8px; align-items:center; font-size:12.5px; margin-bottom:10px; }
#svnApp .glist-pill{ font-weight:700; border-radius:20px; padding:3px 10px; }
#svnApp .glist-pill.ok{ color:var(--ok); background:var(--ok-bg); }
#svnApp .glist-pill.warn{ color:var(--warn); background:var(--warn-bg); }
#svnApp .glist-rows{ display:flex; flex-direction:column; gap:4px; max-height:230px; overflow:auto; }
#svnApp .glist-row{ display:flex; align-items:center; gap:9px; font-size:13px; padding:5px 9px; border-radius:8px; background:var(--raise); border:1px solid var(--line); }
#svnApp .glist-row .gl-dom{ color:var(--ink); min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
#svnApp .glist-row .gl-arrow{ color:var(--muted-2); flex:none; }
#svnApp .glist-row .gl-repo{ color:var(--muted); min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-family:ui-monospace,Menlo,Consolas,monospace; font-size:12px; }
#svnApp .glist-row .gl-tag{ margin-left:auto; flex:none; font-size:11px; font-weight:800; letter-spacing:.4px; text-transform:uppercase; padding:2px 8px; border-radius:6px; }
#svnApp .glist-row.miss{ border-color:var(--warn-bg); }
#svnApp .glist-row.miss .gl-dom{ color:var(--warn); }
#svnApp .glist-row .gl-tag.ok{ color:var(--ok); background:var(--ok-bg); }
#svnApp .glist-row .gl-tag.miss{ color:var(--warn); background:var(--warn-bg); }
#svnApp .glist-row .gl-tag.fuzzy{ color:var(--muted); background:var(--hover); }
#svnApp .gcrit-site{ border:1px solid var(--line-strong); border-radius:12px; margin-top:12px; overflow:hidden; }
#svnApp .gcrit-site-head{ display:flex; align-items:center; gap:10px; padding:10px 13px; background:var(--raise); border-bottom:1px solid var(--line); }
#svnApp .gcrit-dot{ width:8px; height:8px; border-radius:50%; flex:none; }
#svnApp .gcrit-dot.warn{ background:var(--warn); }
#svnApp .gcrit-name{ font-size:13.5px; font-weight:700; color:var(--ink); min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
#svnApp .gcrit-badge{ font-size:11.5px; font-weight:800; border-radius:6px; padding:2px 8px; flex:none; }
#svnApp .gcrit-badge.warn{ color:var(--warn); background:var(--warn-bg); }
#svnApp .gcrit-site-head .gcrit-open{ margin-left:auto; flex:none; }
#svnApp .gcrit-site .log-list{ padding:4px 10px 8px; }
#svnApp .gcrit-line{ display:flex; align-items:center; gap:8px; font-size:13px; padding:9px 12px; margin-top:10px; border-radius:9px; }
#svnApp .gcrit-line.ok{ color:var(--ok); background:var(--ok-bg); }
#svnApp .gcrit-line.err{ color:var(--warn); background:var(--warn-bg); }
#svnApp .gcrit-line.muted{ color:var(--muted); }
#svnApp .gcrit-line svg{ flex:none; }
#svnApp .dc-status{ border:1px solid var(--line-strong); border-radius:11px; padding:11px 13px; margin:0 0 14px; background:var(--raise); }
#svnApp .dcs-head{ display:flex; align-items:center; gap:10px; margin-bottom:6px; }
#svnApp .dcs-title{ font-size:12px; font-weight:800; letter-spacing:.6px; text-transform:uppercase; color:var(--muted-2); }
#svnApp .dcs-open{ margin-left:auto; display:inline-flex; align-items:center; gap:6px; font-size:12.5px; font-weight:700; color:var(--acc-solid); }
#svnApp .dcs-open:hover{ text-decoration:underline; }
#svnApp .dcs-open svg{ flex:none; }
#svnApp .dcs-empty{ font-size:13px; color:var(--muted); display:flex; align-items:center; gap:8px; padding:3px 0; }
#svnApp .dcs-row{ display:flex; align-items:center; gap:9px; font-size:13px; padding:6px 0; border-top:1px solid var(--line); }
#svnApp .dcs-row:first-of-type{ border-top:none; }
#svnApp .dcs-ic{ color:var(--muted-2); flex:none; display:inline-flex; }
#svnApp .dcs-label{ color:var(--muted); min-width:74px; flex:none; }
#svnApp .dcs-val{ color:var(--ink-soft); min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
#svnApp .dcs-none{ color:var(--muted-2); }
#svnApp .mail-list{ display:flex; flex-direction:column; gap:5px; }
#svnApp .mail-row{ width:100%; text-align:left; cursor:pointer; display:flex; align-items:center; gap:12px; padding:9px 12px; background:var(--card-2); border:1px solid var(--line); border-radius:9px; font-size:13px; transition:.12s; }
#svnApp .mail-row:hover{ border-color:var(--acc-solid); background:var(--hover-2); }
/* Servers (host health) */
#svnApp .svh-cards{ display:grid; grid-template-columns:1fr 1fr; gap:14px; }
@media (max-width:720px){ #svnApp .svh-cards{ grid-template-columns:1fr; } }
#svnApp .svh-card{ border:1px solid var(--line); border-radius:var(--r-md); background:var(--card-2); padding:14px 15px; min-width:0; }
#svnApp .svh-card-head{ display:flex; align-items:center; gap:8px; color:var(--ink); }
#svnApp .svh-card-head svg{ color:var(--muted); flex:none; }
#svnApp .svh-card-title{ font-weight:700; font-size:14px; }
#svnApp .svh-host{ font-size:12px; color:var(--ink-soft); margin-left:auto; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
#svnApp .svh-tag{ flex:none; font-size:10px; font-weight:800; letter-spacing:.4px; text-transform:uppercase; padding:2px 6px; border-radius:6px; background:var(--raise-2); color:var(--muted); }
#svnApp .svh-tag.ssh{ background:rgba(93,111,214,.16); color:var(--acc-solid); }
#svnApp .svh-meta{ font-size:11.5px; color:var(--muted); margin:5px 0 11px; word-break:break-word; }
#svnApp .svh-stats{ display:flex; flex-direction:column; gap:11px; }
#svnApp .svh-stat{ min-width:0; }
#svnApp .svh-stat-lbl{ font-size:10.5px; font-weight:800; letter-spacing:.5px; text-transform:uppercase; color:var(--muted-2); margin-bottom:3px; }
#svnApp .svh-stat-val{ font-size:15px; color:var(--ink); font-variant-numeric:tabular-nums; }
#svnApp .svh-of{ color:var(--muted-2); font-size:.85em; font-weight:400; }
#svnApp .svh-stat-sub{ font-size:11px; color:var(--muted); margin-top:2px; }
#svnApp .svh-hint{ display:block; font-size:11px; color:var(--muted); margin-top:3px; }
#svnApp .svh-bar{ height:6px; border-radius:4px; background:var(--raise-2); overflow:hidden; margin:5px 0 1px; }
#svnApp .svh-bar-fill{ height:100%; border-radius:4px; background:var(--ok); transition:width .3s; }
#svnApp .svh-bar-fill.warn{ background:var(--warn); }
#svnApp .svh-bar-fill.crit{ background:var(--err); }
#svnApp .svh-disks{ margin-top:12px; }
#svnApp .svh-sub{ font-size:10.5px; font-weight:800; letter-spacing:.5px; text-transform:uppercase; color:var(--muted-2); margin-bottom:7px; }
#svnApp .svh-disk{ margin-bottom:9px; }
#svnApp .svh-disk-head{ display:flex; align-items:baseline; justify-content:space-between; gap:10px; font-size:12px; color:var(--ink-soft); }
#svnApp .svh-disk-num{ color:var(--muted); font-variant-numeric:tabular-nums; white-space:nowrap; }
#svnApp .svh-note{ margin-top:11px; font-size:11.5px; color:var(--muted); border-top:1px solid var(--line); padding-top:9px; }
#svnApp .me-grid{ display:grid; grid-template-columns:1fr 1fr; gap:8px 22px; margin-bottom:6px; }
#svnApp .me-field{ display:flex; flex-direction:column; gap:2px; min-width:0; }
#svnApp .me-lbl{ font-size:11px; font-weight:800; letter-spacing:.5px; text-transform:uppercase; color:var(--muted-2); }
#svnApp .me-val{ font-size:13px; color:var(--ink); word-break:break-word; }
#svnApp .me-sub{ font-size:12px; font-weight:800; letter-spacing:.6px; text-transform:uppercase; color:var(--muted-2); margin:14px 0 8px; }
#svnApp .me-recips{ display:flex; flex-direction:column; gap:7px; }
#svnApp .me-recip{ display:grid; grid-template-columns:auto 1fr; gap:4px 10px; align-items:center; padding:9px 11px; background:var(--card-2); border:1px solid var(--line); border-radius:9px; }
#svnApp .me-recip .mail-status{ grid-row:1; }
#svnApp .me-to{ font-size:13px; color:var(--ink); min-width:0; overflow:hidden; text-overflow:ellipsis; word-break:break-all; }
#svnApp .me-rmeta{ grid-column:1 / -1; display:flex; flex-wrap:wrap; gap:10px; font-size:11.5px; color:var(--muted); }
#svnApp .me-detail{ grid-column:1 / -1; font-size:11.5px; color:var(--muted-2); word-break:break-word; }
#svnApp .mail-when{ color:var(--muted); font-family:ui-monospace,Menlo,Consolas,monospace; font-size:12px; white-space:nowrap; flex:none; min-width:96px; }
#svnApp .mail-addr{ display:inline-flex; align-items:center; gap:5px; color:var(--ink); min-width:0; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
#svnApp .mail-addr svg{ color:var(--muted-2); flex:none; }
#svnApp .mail-from{ color:var(--muted); font-size:12px; min-width:0; max-width:38%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; flex:none; }
#svnApp .mail-status{ flex:none; font-size:11px; font-weight:800; letter-spacing:.4px; text-transform:uppercase; padding:2px 8px; border-radius:6px; }
#svnApp .mail-status.ok{ color:var(--ok); background:var(--ok-bg); }
#svnApp .mail-status.warn{ color:var(--warn); background:var(--warn-bg); }
#svnApp .mail-status.err{ color:var(--err); background:var(--err-bg); }
#svnApp .mail-status.muted{ color:var(--muted); background:var(--fill-2); }
#svnApp .pchip{ font-size:12.5px; font-weight:600; color:var(--ink-soft); background:var(--raise); border:1px solid var(--line); border-radius:20px; padding:5px 11px; cursor:pointer; }
#svnApp .pchip:hover{ border-color:var(--line-strong); color:var(--ink); }

#svnApp .summary{ display:flex; gap:14px; padding:16px; border-radius:12px; margin-bottom:6px; }
#svnApp .summary.ok{ background:var(--ok-bg); border:1px solid rgba(68,178,124,.3); }
#svnApp .summary.mixed{ background:var(--warn-bg); border:1px solid rgba(224,169,59,.3); }
#svnApp .summary .s-big{ font-size:26px; font-weight:900; line-height:1; }
#svnApp .summary .s-lbl{ font-size:12.5px; color:var(--muted); margin-top:4px; }

#svnApp .empty{ padding:60px 24px; text-align:center; color:var(--muted-2); }
#svnApp .empty .e-ico{ margin-bottom:14px; opacity:.5; }
#svnApp .empty .e-t{ font-size:16px; font-weight:700; color:var(--muted); }

/* diff viewer */
#svnApp .diff-meta{ display:flex; align-items:center; gap:12px; margin:4px 0 12px; }
#svnApp .diff-meta .diff-add{ color:var(--ok); font-weight:800; font-size:13px; }
#svnApp .diff-meta .diff-del{ color:var(--err); font-weight:800; font-size:13px; }
#svnApp .diffwrap{ background:#0e1620; border:1px solid var(--line); border-radius:10px; overflow:auto; max-height:58vh; padding:8px 0; }
#svnApp .dline{ display:flex; padding:1px 14px; font-family:'JetBrains Mono',ui-monospace,monospace; font-size:12.5px; line-height:1.7; white-space:pre; }
#svnApp .dline .dtext{ white-space:pre-wrap; word-break:break-word; }
#svnApp .dline.add{ background:rgba(68,178,124,.13); color:#9fe0bd; }
#svnApp .dline.del{ background:rgba(226,101,127,.13); color:#f0a9b8; }
#svnApp .dline.ctx{ color:var(--muted); }
#svnApp .dline.hunk{ color:#8b9be8; background:rgba(123,140,224,.08); font-weight:600; }
#svnApp .dline.file-old{ color:#ff7b72; }
#svnApp .dline.file-new{ color:#7ee787; }
#svnApp .dline.index{ color:#d2a8ff; font-weight:600; }
#svnApp .dline.meta{ color:var(--muted-2); font-style:italic; }

/* generic info modal body (history/logs/cron tables reuse) */
#svnApp .info-pre{ background:#0e1620; border:1px solid var(--line); border-radius:10px; padding:14px; font-family:'JetBrains Mono',ui-monospace,monospace; font-size:12.5px; line-height:1.6; color:var(--ink-soft); white-space:pre-wrap; word-break:break-word; max-height:60vh; overflow:auto; }
/* cron entries */
#svnApp .cron-head{ display:flex; align-items:center; gap:10px; margin-bottom:10px; }
#svnApp .cron-head .ct{ font-size:12px; font-weight:700; color:var(--muted); }
#svnApp .cron-head .spacer{ margin-left:auto; }
#svnApp .cron-list{ display:flex; flex-direction:column; gap:8px; }
#svnApp .cron-item{ display:flex; gap:14px; align-items:flex-start; padding:11px 13px; background:var(--card-2); border:1px solid var(--line); border-radius:10px; }
#svnApp .cron-when{ flex:none; width:160px; }
#svnApp .cron-human{ font-size:13.5px; font-weight:700; color:var(--ink); display:flex; align-items:center; gap:7px; }
#svnApp .cron-human svg{ color:var(--info); flex:none; }
#svnApp .cron-expr{ font-size:12px; color:var(--muted); margin-top:3px; }
#svnApp .cron-cmd{ flex:1; min-width:0; font-size:12.5px; color:var(--ink-soft); white-space:pre-wrap; word-break:break-word; line-height:1.5; }
#svnApp .cron-copy{ flex:none; width:30px; height:30px; border-radius:8px; border:1px solid var(--line-strong); display:flex; align-items:center; justify-content:center; color:var(--muted); }
#svnApp .cron-copy:hover{ background:var(--hover-2); color:var(--ink); }
#svnApp .cron-copy.ok{ color:var(--ok); border-color:rgba(68,178,124,.4); }
#svnApp .cron-env-title{ font-size:11px; font-weight:800; letter-spacing:.6px; text-transform:uppercase; color:var(--muted-2); margin:16px 0 7px; }
#svnApp .cron-row-actions{ display:flex; align-items:center; gap:6px; flex:none; }
#svnApp .cron-icon-btn{ width:30px; height:30px; border-radius:8px; border:1px solid var(--line-strong); display:flex; align-items:center; justify-content:center; color:var(--muted); }
#svnApp .cron-icon-btn:hover{ background:var(--hover-2); color:var(--ink); }
#svnApp .cron-icon-btn.cron-run-row{ color:var(--ok); border-color:var(--ok); }
#svnApp .cron-icon-btn.cron-run-row:hover{ background:rgba(74,193,128,.14); color:var(--ok); }
#svnApp .cron-icon-btn.cron-run-row[disabled]{ opacity:.5; cursor:default; }
#svnApp .cron-output{ margin:-2px 0 2px; background:#0c1116; border:1px solid var(--line-strong); border-radius:10px; overflow:hidden; }
#svnApp .cron-output-head{ display:flex; align-items:center; gap:10px; padding:8px 12px; border-bottom:1px solid var(--line); background:var(--fill); font-size:12.5px; }
#svnApp .cron-output-head .co-user{ font-weight:700; color:var(--ink); }
#svnApp .cron-output-head .co-cmd{ font-family:'JetBrains Mono',monospace; font-size:11.5px; color:var(--muted); min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; flex:1; }
#svnApp .cron-output-head .co-rc{ font-size:11px; font-weight:800; padding:3px 9px; border-radius:20px; white-space:nowrap; }
#svnApp .cron-output-head .co-rc.ok{ background:var(--ok-bg,rgba(74,193,128,.18)); color:var(--ok); }
#svnApp .cron-output-head .co-rc.bad{ background:var(--err-bg); color:var(--err); }
#svnApp .cron-output-head .co-copy,#svnApp .cron-output-head .co-x{ color:var(--muted); display:flex; cursor:pointer; }
#svnApp .cron-output-head .co-copy:hover,#svnApp .cron-output-head .co-x:hover{ color:var(--ink); }
#svnApp .cron-output-head .co-copy.ok{ color:var(--ok); }
#svnApp .cron-output pre{ margin:0; padding:11px 13px; max-height:340px; overflow:auto; font-family:'JetBrains Mono',monospace; font-size:12px; line-height:1.55; color:#cdd6e0; white-space:pre-wrap; word-break:break-word; }
#svnApp .cron-output pre.empty{ color:var(--muted-2); font-style:italic; }
#svnApp .cron-del-row:hover{ color:var(--err); border-color:rgba(226,101,127,.4); background:var(--err-bg); }
#svnApp .cron-item.editing{ align-items:center; }
#svnApp .cron-edit-fields, #svnApp .cron-add-fields{ display:flex; gap:8px; flex:1; min-width:0; flex-wrap:wrap; align-items:center; }
#svnApp .cron-in{ background:var(--raise); border:1px solid var(--line-strong); border-radius:8px; padding:8px 10px; color:var(--ink); font-family:'JetBrains Mono',ui-monospace,monospace; font-size:12.5px; outline:none; }
#svnApp .cron-in:focus{ border-color:var(--acc-solid); box-shadow:0 0 0 3px rgba(93,111,214,.18); }
#svnApp .cron-edit-fields .cron-in:first-child, #svnApp .cron-add-fields .cron-in:first-child{ width:150px; flex:none; }
#svnApp .cron-in-cmd{ flex:1; min-width:160px; }
#svnApp .cron-add{ margin-top:16px; padding:14px; border:1px dashed var(--line-strong); border-radius:10px; }
#svnApp .cron-add-title{ display:flex; align-items:center; gap:6px; font-size:12px; font-weight:800; letter-spacing:.4px; text-transform:uppercase; color:var(--muted-2); margin-bottom:10px; }
#svnApp .cron-add-hint{ font-size:12px; color:var(--muted-2); margin-top:9px; }
/* error log */
#svnApp .log-list{ display:flex; flex-direction:column; gap:7px; }
#svnApp .log-item{ display:flex; gap:14px; align-items:flex-start; padding:11px 13px; background:var(--card-2); border:1px solid var(--line); border-radius:10px; }
#svnApp .log-main{ flex:1; min-width:0; }
#svnApp .log-msg{ display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
#svnApp .log-text{ font-size:13.5px; color:var(--ink); font-weight:600; }
#svnApp .log-loc{ font-size:12px; color:var(--muted); margin-top:4px; word-break:break-all; }
#svnApp .log-url{ display:inline-flex; align-items:center; gap:5px; max-width:100%; margin-top:4px; font-size:11.5px; color:var(--info); text-decoration:none; }
#svnApp .log-url span{ overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
#svnApp .log-url svg{ flex:none; opacity:.8; }
#svnApp a.log-url:hover span{ text-decoration:underline; }
#svnApp .log-meta{ flex:none; display:flex; flex-direction:column; align-items:flex-end; gap:4px; }
#svnApp .log-copy{ color:var(--muted-2); width:26px; height:26px; border-radius:6px; display:inline-flex; align-items:center; justify-content:center; flex:none; }
#svnApp .log-copy:hover{ background:var(--hover); color:var(--ink); }
#svnApp .log-copied{ color:#9fe0bd; background:var(--ok-bg); }
#svnApp .log-count{ font-size:12px; font-weight:800; color:var(--ink-soft); background:var(--fill-2); border-radius:20px; padding:2px 9px; }
#svnApp .log-when{ font-size:11.5px; color:var(--muted-2); white-space:nowrap; }
#svnApp .logsev{ font-size:10.5px; font-weight:800; letter-spacing:.5px; text-transform:uppercase; padding:4px 9px; border-radius:20px; white-space:nowrap; }
#svnApp .logsev.notice{ background:rgba(88,169,214,.14); color:var(--info); }
#svnApp .logsev.warning{ background:var(--warn-bg); color:var(--warn); }
#svnApp .logsev.fatal,#svnApp .logsev.parse{ background:var(--err-bg); color:var(--err); }
#svnApp .logsev.deprecated,#svnApp .logsev.strict{ background:var(--fill-2); color:var(--muted); }
#svnApp .logsev.database{ background:rgba(154,111,166,.16); color:var(--addv); }
#svnApp .logsev.other{ background:var(--fill-2); color:var(--muted); }
#svnApp .logf-bar{ display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin:2px 0 12px; }
#svnApp .logf-sevs{ display:flex; flex-wrap:wrap; gap:7px; }
#svnApp .logf-chip{ display:inline-flex; align-items:center; gap:6px; cursor:pointer; border:1px solid transparent; transition:.13s; }
#svnApp .logf-chip .logf-n{ font-size:10px; font-weight:700; background:var(--hover); border-radius:10px; padding:0 5px; }
#svnApp .logf-chip.off{ opacity:.42; filter:grayscale(.55); }
#svnApp .logf-chip.on{ box-shadow:0 0 0 1px currentColor inset; }
#svnApp .logf-none{ font-size:12px; color:var(--muted-2); }
#svnApp .logw-seg{ display:inline-flex; margin-left:auto; border:1px solid var(--line-strong); border-radius:9px; overflow:hidden; }
#svnApp .logw-btn{ font-size:12px; font-weight:700; color:var(--muted); padding:6px 11px; border-right:1px solid var(--line); background:transparent; transition:.13s; white-space:nowrap; }
#svnApp .logw-btn:last-child{ border-right:none; }
#svnApp .logw-btn:hover{ background:var(--hover); color:var(--ink); }
#svnApp .logw-btn.on{ background:var(--acc-solid); color:#fff; }
#svnApp .log-open{ display:inline-flex; align-items:center; gap:6px; font-size:12px; color:var(--info); margin-top:4px; padding:2px 6px; border-radius:6px; border:1px solid transparent; cursor:pointer; max-width:100%; }
#svnApp .log-open:hover{ background:var(--hover); border-color:var(--line-strong); color:var(--info); text-decoration:underline; }
#svnApp .log-open svg{ flex:none; opacity:.8; }
/* source viewer */
#svnApp .codewrap{ position:relative; max-height:62vh; overflow:auto; background:#0e1620; border:1px solid var(--line); border-radius:10px; padding:6px 0; }
#svnApp .codeline{ display:flex; font-family:'JetBrains Mono',ui-monospace,monospace; font-size:12.5px; line-height:1.65; }
#svnApp .codeline .ln{ flex:none; width:54px; text-align:right; padding:0 12px 0 6px; color:var(--muted-2); background:rgba(255,255,255,.03); user-select:none; position:sticky; left:0; }
#svnApp .codeline .lc{ padding:0 14px; white-space:pre; color:#c9d4e2; }
#svnApp .codeline.hl{ background:rgba(224,169,59,.16); }
#svnApp .codeline.hl .ln{ color:#f0bd63; background:rgba(224,169,59,.22); font-weight:700; }
#svnApp .codeline.hl .lc{ color:#fff; }
/* history */
#svnApp .hist-list{ display:flex; flex-direction:column; gap:8px; }
#svnApp .hist-item{ display:flex; gap:14px; align-items:flex-start; padding:12px 13px; background:var(--card-2); border:1px solid var(--line); border-radius:10px; }
#svnApp .hist-rev{ flex:none; width:98px; display:flex; flex-direction:column; gap:5px; }
#svnApp .hist-rev-badge{ font-size:12.5px; font-weight:800; color:var(--ink); }
#svnApp .hist-ago{ font-size:11.5px; color:var(--muted-2); }
#svnApp .hist-main{ flex:1; min-width:0; }
#svnApp .hist-msg{ font-size:13.5px; color:var(--ink); font-weight:600; white-space:pre-wrap; word-break:break-word; line-height:1.45; }
#svnApp .hist-msg.hist-nomsg{ font-weight:500; color:var(--muted); font-style:italic; }
#svnApp .hist-meta{ display:flex; flex-wrap:wrap; align-items:center; gap:6px 14px; margin-top:6px; font-size:12px; color:var(--muted); }
#svnApp .hist-by, #svnApp .hist-deploy{ display:inline-flex; align-items:center; gap:5px; }
#svnApp .hist-by svg{ color:var(--muted-2); }
#svnApp .hist-deploy{ color:var(--ok); font-weight:600; }
#svnApp .hist-undeployed{ color:var(--muted-2); font-style:italic; }
#svnApp .hist-files-toggle{ display:inline-flex; align-items:center; gap:6px; margin-top:9px; font-size:12px; font-weight:700; color:var(--info); padding:4px 9px; border-radius:7px; border:1px solid var(--line-strong); background:var(--fill); }
#svnApp .hist-files-toggle:hover{ background:var(--hover); }
#svnApp .hist-files{ margin-top:9px; display:flex; flex-direction:column; gap:5px; }
#svnApp .hist-file{ display:flex; align-items:center; gap:9px; font-size:12.5px; }
#svnApp .hist-file .fstat{ min-width:22px; justify-content:center; padding:3px 6px; }
#svnApp .hist-file-open{ color:var(--info); text-align:left; word-break:break-all; }
#svnApp .hist-file-open:hover{ text-decoration:underline; }
#svnApp .hist-file-del{ color:var(--muted); text-decoration:line-through; word-break:break-all; }
#svnApp .hist-actions{ flex:none; }
@media (max-width:600px){ #svnApp .hist-item{ flex-wrap:wrap; } #svnApp .hist-rev{ width:auto; flex-direction:row; gap:10px; align-items:center; } }
@media (max-width:600px){ #svnApp .cron-item{ flex-direction:column; gap:7px; align-items:stretch; } #svnApp .cron-when{ width:auto; } #svnApp .cron-edit-fields .cron-in:first-child, #svnApp .cron-add-fields .cron-in:first-child{ width:100%; } }
#svnApp .svn-modal-message{ padding:14px 16px; border-radius:10px; background:rgba(88,169,214,.12); color:#bfe0f2; font-size:14px; }
#svnApp .svn-modal-message--warn{ background:var(--warn-bg); color:var(--warn); }
#svnApp .svn-modal-message--ok{ background:var(--ok-bg); color:var(--ok); }
#svnApp .hist-actions{ display:flex; gap:6px; align-items:center; }
#svnApp .hist-revert{ color:var(--muted); }
#svnApp .hist-revert:hover{ color:var(--err); border-color:var(--err-bg); }
#svnApp .svn-modal-table{ width:100%; border-collapse:collapse; font-size:13.5px; }
#svnApp .svn-modal-table th{ text-align:left; padding:10px 12px; font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:var(--muted-2); border-bottom:1px solid var(--line-strong); }
#svnApp .svn-modal-table td{ padding:9px 12px; border-bottom:1px solid var(--line); color:var(--ink-soft); vertical-align:top; }
#svnApp .svn-history-group-cell{ display:flex; justify-content:space-between; align-items:center; gap:16px; background:var(--fill-2); padding:9px 12px; border-bottom:1px solid var(--line-strong); }
#svnApp .svn-history-group-name{ font-weight:700; color:var(--ink); }
#svnApp .svn-history-group-meta{ font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--muted-2); }
#svnApp .svn-history-rev{ font-weight:700; color:var(--ink-soft); white-space:nowrap; }
#svnApp .svn-history-date{ color:var(--muted); white-space:nowrap; }
#svnApp .svn-history-comment{ color:var(--ink-soft); }
#svnApp .checkbox-group{ display:flex; align-items:center; gap:10px; margin-bottom:12px; }
#svnApp .checkbox-group input[type=checkbox]{ width:18px; height:18px; accent-color:var(--acc-solid); }
#svnApp .alert{ padding:12px 14px; border-radius:10px; margin-top:14px; display:none; font-size:13.5px; }
#svnApp .alert.show{ display:block; }
#svnApp .alert-success{ background:var(--ok-bg); color:#9fe0bd; }
#svnApp .alert-error{ background:var(--err-bg); color:#f0a9b8; }
#svnApp .bk-head{ display:flex; align-items:center; gap:8px; font-size:13px; font-weight:600; color:var(--ink-soft); margin-bottom:6px; }
#svnApp .bk-head svg{ color:var(--muted); }
#svnApp .bk-count{ margin-left:2px; font-size:11px; font-weight:600; color:var(--acc-solid); background:rgba(93,111,214,.14); border-radius:9px; padding:1px 7px; }
#svnApp .bk-target{ font-size:12px; color:var(--muted); margin-bottom:11px; }
#svnApp .bk-copy{ background:var(--hover); border:1px solid var(--line); padding:1px 7px; border-radius:6px; cursor:pointer; white-space:nowrap; transition:.12s; }
#svnApp .bk-copy svg{ vertical-align:-1px; opacity:.7; }
#svnApp .bk-copy:hover{ background:var(--hover-2); border-color:var(--acc-solid); color:var(--acc-solid); }
#svnApp .bk-copy:hover svg{ opacity:1; }
#svnApp .bk-copied{ background:var(--ok-bg); border-color:transparent; color:#9fe0bd; }
#svnApp .bk-rows{ max-height:300px; overflow:auto; border:1px solid var(--line); border-radius:var(--r-md); background:var(--raise); }
#svnApp .bk-row{ padding:8px 12px; border-bottom:1px solid var(--line); font-size:13px; }
#svnApp .bk-row:last-child{ border-bottom:0; }
#svnApp .bk-row--latest{ background:rgba(93,111,214,.07); }
#svnApp .bk-line{ display:flex; align-items:center; justify-content:space-between; gap:12px; }
#svnApp .bk-file{ color:var(--ink); flex:1 1 auto; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
#svnApp .bk-meta{ display:flex; align-items:center; gap:10px; flex:none; }
#svnApp .bk-size{ font-size:11.5px; color:var(--muted-2); white-space:nowrap; font-variant-numeric:tabular-nums; }
#svnApp .bk-date{ font-size:11.5px; color:var(--muted-2); white-space:nowrap; }
#svnApp .bk-row--latest .bk-date{ color:var(--acc-solid); font-weight:600; }
#svnApp .bk-restore[disabled]{ opacity:.55; cursor:default; }
#svnApp .bk-note{ margin-top:8px; font-size:12px; border-radius:8px; padding:6px 9px; }
#svnApp .bk-note--ok{ background:var(--ok-bg); color:#9fe0bd; }
#svnApp .bk-note--err{ background:var(--err-bg); color:#f0a9b8; word-break:break-word; }
#svnApp .bk-prog{ margin-top:9px; }
#svnApp .bk-bar{ height:7px; border-radius:5px; background:var(--hover); overflow:hidden; }
#svnApp .bk-bar-fill{ height:100%; width:0; border-radius:5px; background:linear-gradient(90deg,var(--acc-a),var(--acc-b)); transition:width .45s ease; }
#svnApp .bk-prog-foot{ display:flex; align-items:center; justify-content:space-between; gap:10px; margin-top:7px; }
#svnApp .bk-prog-stat{ font-size:11.5px; color:var(--muted); font-variant-numeric:tabular-nums; }
#svnApp .bk-stop{ color:var(--err); border-color:var(--err); flex:none; }
#svnApp .bk-stop:hover{ background:var(--err-bg); }
#svnApp .dc-log{ max-height:240px; overflow:auto; background:var(--raise); border:1px solid var(--line); border-radius:var(--r-md); padding:10px 12px; font-family:var(--mono,monospace); font-size:12px; line-height:1.5; color:var(--ink-soft); white-space:pre-wrap; word-break:break-word; }
#svnApp .dc-img-head{ display:flex; align-items:center; justify-content:space-between; font-size:13px; font-weight:700; color:var(--ink); margin-bottom:6px; }
#svnApp .dc-img-head .dc-img-pct{ font-variant-numeric:tabular-nums; color:var(--info); }
#svnApp .dc-bar{ height:9px; border-radius:6px; background:var(--hover-2); overflow:hidden; }
#svnApp .dc-bar-fill{ height:100%; width:0; border-radius:6px; background:linear-gradient(90deg,var(--acc-solid,#4aa3e0),var(--info,#5cc)); transition:width .5s ease; }
#svnApp .dc-bar-fill.indet{ width:35% !important; animation:dcIndet 1.2s ease-in-out infinite; }
@keyframes dcIndet{ 0%{margin-left:-35%} 100%{margin-left:100%} }
#svnApp .dc-img-sub{ margin-top:6px; font-size:11.5px; color:var(--muted-2); }
#svnApp .dc-img-note{ color:var(--muted-2); font-style:italic; }
#svnApp .ddb-crumb{ font-size:13px; margin-bottom:12px; }
#svnApp .ddb-cr{ color:var(--acc-solid); text-decoration:none; } #svnApp .ddb-cr:hover{ text-decoration:underline; }
#svnApp .ddb-cr-sep{ color:var(--muted-2); margin:0 7px; } #svnApp .ddb-cr-cur{ color:var(--ink); font-weight:700; }
#svnApp .ddb-filter{ width:100%; box-sizing:border-box; padding:8px 11px; margin-bottom:10px; border:1px solid var(--line-strong); border-radius:8px; background:var(--raise); color:var(--ink); font-size:13px; }
#svnApp .ddb-filter:focus{ outline:none; border-color:var(--acc-solid); }
#svnApp .ddb-scroll{ max-height:52vh; overflow:auto; border:1px solid var(--line); border-radius:var(--r-md); }
#svnApp .ddb-table{ width:100%; border-collapse:collapse; font-size:13px; }
#svnApp .ddb-table th{ position:sticky; top:0; background:var(--fill); text-align:left; font-size:11px; font-weight:800; letter-spacing:.5px; text-transform:uppercase; color:var(--muted-2); padding:9px 12px; border-bottom:1px solid var(--line); white-space:nowrap; z-index:1; }
#svnApp .ddb-table td{ padding:8px 12px; border-bottom:1px solid var(--line); color:var(--ink-soft); }
#svnApp .ddb-num{ text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap; }
#svnApp .ddb-row{ cursor:pointer; } #svnApp .ddb-row:hover td{ background:var(--hover); color:var(--ink); }
#svnApp .ddb-null{ color:var(--muted-2); font-style:italic; }
#svnApp .ddb-rows{ max-height:48vh; }
#svnApp .ddb-data td{ white-space:nowrap; max-width:340px; overflow:hidden; text-overflow:ellipsis; }
#svnApp .ddb-pager{ display:flex; align-items:center; gap:12px; margin-bottom:10px; }
#svnApp .ddb-pageinfo{ font-size:12px; color:var(--muted); font-variant-numeric:tabular-nums; }
</style>
</head>
<body>
<?php $root_path = "../"; include("../includes/modern_header.php"); ?>

<div id="svnApp">
  <div class="page">
    <div class="page-head">
      <div>
        <h1>SVN Updater</h1>
        <p class="lede">Review incoming changes and deploy from SVN to live — across many sites at once.</p>
      </div>
      <div class="head-actions">
        <button class="btn" id="btnHistory" data-info="history">History</button>
        <button class="btn" id="btnLog" data-info="log">Error Log</button>
        <button class="btn" id="btnCritical">Critical Errors</button>
        <button class="btn" id="btnMail" data-info="mail">Mail Log</button>
        <button class="btn" id="btnCron" data-info="cron">Cron Jobs</button>
        <button class="btn" id="btnServers">Servers</button>
        <button class="btn" id="btnBackups">Backups</button>
        <button class="btn" id="btnDevCopy">Dev copy</button>
        <button class="btn" id="btnDevDbs">Dev DBs</button>
      </div>
    </div>

    <!-- control panel -->
    <div class="card control">
      <div class="card-head">
        <span id="repoIcon"></span>
        <h2>Repository</h2>
        <span class="ch-sub">Find one site, or pick a saved group to work across many</span>
        <div class="spacer"></div>
        <div class="grp-dd" id="grpDd">
          <button class="grp-dd-trigger" id="grpTrigger">
            <span id="grpTriggerFolder"></span>
            <span class="ddt-name" id="grpTriggerName">All sites</span>
            <span class="ddt-count" id="grpTriggerCount">0</span>
            <span class="ddt-chev" id="grpTriggerChev"></span>
          </button>
        </div>
      </div>
      <div class="ctrl-finder">
        <label class="ctrl-label">Find a repository</label>
        <div class="input">
          <span id="finderSearchIcon"></span>
          <input type="text" id="finderInput" autocomplete="off" placeholder="Start typing a site address…" value="<?php echo htmlspecialchars($monitor_svn_repository, ENT_QUOTES, 'UTF-8'); ?>"/>
        </div>
        <div class="finder-dd" id="finderDd"></div>
        <div class="recents" id="recentsWrap" style="display:none;">
          <span class="recents-label">Recent:</span>
          <span id="recentsList"></span>
        </div>
        <div class="ctrl-match" id="finderMatch" style="display:none;"></div>
      </div>
    </div>

    <!-- main -->
    <div class="card main">
      <div class="card-head" id="mainHead">
        <div class="toolbar">
          <input type="checkbox" class="chk" id="selAll" title="Select all updatable visible"/>
          <div class="search-wrap">
            <div class="input" style="padding:8px 12px;">
              <span id="filterSearchIcon"></span>
              <input type="text" id="filterInput" placeholder="Filter sites…" style="font-size:14px;"/>
            </div>
          </div>
          <div class="fchips" id="filterChips"></div>
        </div>
        <div class="spacer"></div>
        <button class="btn tiny" id="collapseAll" title="Collapse all" style="display:none;"></button>
        <div class="vtoggle" id="viewToggle">
          <button data-view="list" class="on" title="List"></button>
          <button data-view="cards" title="Cards"></button>
        </div>
      </div>
      <div class="scanbar" id="scanBar" style="display:none;"></div>
      <div id="tableHost"></div>
    </div>
  </div>

  <!-- apply bar -->
  <div class="applybar" id="applyBar">
    <div>
      <div class="ab-count"><b id="abCount">0</b> <span id="abCountLbl">sites selected</span></div>
      <div class="ab-sub" id="abSub"></div>
    </div>
    <div class="ab-grow"></div>
    <div class="ab-actions">
      <button class="btn ghost" id="abClear">Clear</button>
      <button class="btn" id="abSave"><span class="i-folderPlus"></span> Save as group</button>
      <button class="btn grad" id="abUpdate"><span class="i-refresh"></span> Review &amp; update</button>
    </div>
  </div>

  <!-- modal host -->
  <div id="modalHost"></div>
  <!-- confirm host (layers above modalHost) -->
  <div id="confirmHost"></div>
  <!-- source-view host (layers above modalHost so closing it returns to the log) -->
  <div id="sourceHost"></div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
(function(){
"use strict";

// ---------------- icons ----------------
var ICONS = {
  search:'M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14ZM20 20l-3.2-3.2',
  refresh:'M3.5 9a8.5 8.5 0 0 1 14.5-4.5M20.5 4v4.5H16M20.5 15a8.5 8.5 0 0 1-14.5 4.5M3.5 20v-4.5H8',
  clock:'M12 7v5l3.5 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
  file:'M6 3h7l5 5v13H6V3ZM13 3v5h5',
  alert:'M12 9v4M12 17h.01M10.3 4l-8 14a2 2 0 0 0 1.7 3h16a2 2 0 0 0 1.7-3l-8-14a2 2 0 0 0-3.4 0Z',
  calendar:'M3 9h18M7 3v3M17 3v3M5 5h14v16H5V5Z',
  copy:'M9 9h10v10H9zM5 15V5h10',
  check:'M5 12l5 5L20 6',
  checkSm:'M4 10l4 4 8-9',
  chevron:'M6 9l6 6 6-6',
  chevronR:'M9 6l6 6-6 6',
  plus:'M12 5v14M5 12h14',
  mail:'M3 5h18v14H3zM3 6l9 7 9-7',
  folder:'M3 6h6l2 2h10v11H3V6Z',
  folderPlus:'M3 6h6l2 2h10v11H3V6ZM12 11v5M9.5 13.5h5',
  grid:'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z',
  list:'M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01',
  x:'M6 6l12 12M18 6L6 18',
  up:'M12 19V5M5 12l7-7 7 7',
  bolt:'M13 2 4 14h7l-1 8 9-12h-7l1-8Z',
  user:'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4 21a8 8 0 0 1 16 0',
  history:'M3 3v6h6M3.5 9a9 9 0 1 1-1 5M12 7v5l4 2',
  undo:'M9 14L4 9l5-5M4 9h10a6 6 0 0 1 0 12H7',
  dots:'M5 12h.01M12 12h.01M19 12h.01',
  branch:'M6 4v12M6 20a2 2 0 1 0 0-4 2 2 0 0 0 0 4ZM6 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4ZM18 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm0 0v3a4 4 0 0 1-4 4H8',
  tools:'M14.7 6.3a4 4 0 0 0-5.4 5.4l-6 6L5 19.7l6-6a4 4 0 0 0 5.4-5.4l-2.3 2.3-2-2 2.3-2.3Z',
  pencil:'M4 20h4L18.5 9.5a2.1 2.1 0 0 0-3-3L5 17v3ZM13.5 6.5l3 3',
  login:'M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3',
  database:'M12 3c4.4 0 8 1.3 8 3s-3.6 3-8 3-8-1.3-8-3 3.6-3 8-3ZM4 6v12c0 1.7 3.6 3 8 3s8-1.3 8-3V6M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3',
  link:'M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1.5 1.5M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1.5-1.5',
  play:'M7 5l12 7-12 7z',
  terminal:'M4 5h16v14H4zM7 9l3 2.5L7 14M12.5 14.5H16',
  server:'M4 4h16v6H4zM4 14h16v6H4zM7.5 7h.01M7.5 17h.01M12 7h4M12 17h4',
  cpu:'M9 3v2M15 3v2M9 19v2M15 19v2M3 9h2M3 15h2M19 9h2M19 15h2M6 6h12v12H6zM10 10h4v4h-4z',
  gauge:'M12 13l4-4M7 18a7 7 0 1 1 10 0M12 3v0'
};
function icon(name, s, w, style){
  s = s || 18; w = w || 1.8; style = style || '';
  var d = ICONS[name] || '';
  var paths = d.split('M').filter(Boolean).map(function(seg){ return '<path d="M'+seg+'"/>'; }).join('');
  return '<svg width="'+s+'" height="'+s+'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="'+w+'" stroke-linecap="round" stroke-linejoin="round" style="'+style+'">'+paths+'</svg>';
}
function esc(s){ return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// ---------------- state ----------------
var REPOS = [<?php echo $repositories_typehead; ?>];
var DEV_SUBDOMAIN = <?php echo json_encode($dev_subdomain); ?>;
var STATE = {
  groups: [],
  activeGroup: '__none',    // '__none' | '__all' | '__one:<repo>' | groupId(number as string)
  sites: {},                // repo -> {repository,status,behind,headRev,lastBy,lastAt,errorMsg,files,scanState}
  sel: {},                  // repo -> true
  collapsed: {},            // repo -> true (collapsed)
  view: 'list',
  filter: 'all',
  query: ''
};
var RECENT_KEY = 'svn_recent_repos', MAX_RECENT = 8;
var LAST_SCOPE_KEY = 'svn_last_scope';
function saveScope(id){ try{ if(id && id!=='__none') localStorage.setItem(LAST_SCOPE_KEY, String(id)); }catch(e){} }
function getSavedScope(){ try{ return localStorage.getItem(LAST_SCOPE_KEY)||''; }catch(e){ return ''; } }
// URL hash <-> scope:  #d-<domain> (site), #g-<id> (group), #all (all sites)
function scopeToHash(scope){
  if(!scope || scope==='__none') return '';
  if(scope==='__all') return 'all';
  if(scope.indexOf('__one:')===0) return 'd-'+scope.slice(6);
  return 'g-'+scope;
}
function hashToScope(h){
  h = decodeURIComponent((h||'').replace(/^#/,''));
  h = h.split('~')[0]; // ignore overlay markers (~bk= / ~info=) when resolving scope
  if(h==='') return '';
  if(h==='all') return '__all';
  if(h.indexOf('d-')===0){ var r=h.slice(2); return REPOS.indexOf(r)!==-1 ? '__one:'+r : ''; }
  if(h.indexOf('g-')===0){ var id=h.slice(2); return (/^\d+$/.test(id) && groupById(id)) ? id : ''; }
  return '';
}
// The repo whose Backups modal the hash says is open (e.g. "...~bk=watches.co.uk"), or ''.
function hashBackups(h){
  h = (h||'').replace(/^#/,'');
  var i = h.indexOf('~bk=');
  if(i < 0) return '';
  var r = decodeURIComponent(h.slice(i + 4).split('~')[0]);
  return REPOS.indexOf(r) !== -1 ? r : '';
}
// The info modal the hash says is open (e.g. "...~info=log:watches.co.uk") -> {kind,repo} or null.
function hashInfo(h){
  h = (h||'').replace(/^#/,'');
  var i = h.indexOf('~info=');
  if(i < 0) return null;
  var v = decodeURIComponent(h.slice(i + 6).split('~')[0]);
  var c = v.indexOf(':');
  if(c < 0) return null;
  var kind = v.slice(0, c), repo = v.slice(c + 1);
  if(['history','log','critical','cron','mail'].indexOf(kind) === -1) return null;
  if(REPOS.indexOf(repo) === -1) return null;
  return { kind: kind, repo: repo };
}
// The repo whose Dev copy popup the hash says is open (e.g. "...~dc=watches.co.uk"), or ''.
function hashDevCopy(h){
  h = (h||'').replace(/^#/,'');
  var i = h.indexOf('~dc=');
  if(i < 0) return '';
  var r = decodeURIComponent(h.slice(i + 4).split('~')[0]);
  return REPOS.indexOf(r) !== -1 ? r : '';
}
// Whether the hash flags the group critical-errors view open (e.g. "g-12~crit").
function hashCritical(h){ return (h||'').indexOf('~crit') !== -1; }
// Reconcile the open overlay modal (backups / info / dev copy / group-critical) with target markers. Avoids reload loops.
function reconcileOverlay(bk, info, dc, crit){
  if(bk){ if(BK_OPEN_REPO !== bk) openBackups(bk); }
  else if(info){ if(!INFO_OPEN || INFO_OPEN.kind !== info.kind || INFO_OPEN.repo !== info.repo) openInfo(info.kind, info.repo); }
  else if(dc){ if(DC_OPEN_REPO !== dc) openDevCopy(dc); }
  else if(crit){ if(!GCRIT || GCRIT.scope !== String(STATE.activeGroup)) openGroupCritical(true); }
  else { if(BK_OPEN_REPO || INFO_OPEN || DC_OPEN_REPO || GCRIT) closeModal(); }
}
var SVN_PATH_PREFIX = 'svn://web1.sayu.co.uk/mnt/drive2/webclients/';

function ensureSite(repo){
  if(!STATE.sites[repo]){
    STATE.sites[repo] = { repository:repo, status:'', behind:0, headRev:'', lastBy:'', lastAt:'', errorMsg:'', files:[], scanState:'idle' };
  }
  return STATE.sites[repo];
}

// ---------------- recents ----------------
function getRecents(){ try{ var r=localStorage.getItem(RECENT_KEY); return r?JSON.parse(r):[]; }catch(e){ return []; } }
function saveRecent(repo){ repo=(repo||'').trim(); if(!repo) return; var l=getRecents().filter(function(r){return r!==repo;}); l.unshift(repo); l=l.slice(0,MAX_RECENT); try{ localStorage.setItem(RECENT_KEY, JSON.stringify(l)); }catch(e){} renderRecents(); }
function renderRecents(){
  var l=getRecents(), $w=$('#recentsWrap'), $c=$('#recentsList');
  if(!l.length){ $w.hide(); return; }
  $c.empty();
  l.forEach(function(r){ $('<button type="button" class="pchip"></button>').text(r).attr('data-recent', r).appendTo($c); });
  $w.show();
}

// ---------------- scope ----------------
function groupById(id){ for(var i=0;i<STATE.groups.length;i++){ if(String(STATE.groups[i].id)===String(id)) return STATE.groups[i]; } return null; }
function scopedRepos(){
  var ag = STATE.activeGroup;
  if(ag==='__none') return [];
  if(ag==='__all') return REPOS.slice();
  if(ag.indexOf('__one:')===0) return [ag.slice(6)];
  var g = groupById(ag);
  return g ? g.siteIds.slice() : [];
}
function activeScopeName(){
  var ag = STATE.activeGroup;
  if(ag==='__all') return 'All sites';
  if(ag.indexOf('__one:')===0) return ag.slice(6);
  var g = groupById(ag); return g ? g.name : 'Select group';
}

// ---------------- scan queue ----------------
var scanQueue = [], scanning = 0, SCAN_CONCURRENCY = 4, autoSelectUpdates = false;
// Set when the user picks "Review & update" from the finder: once this repo finishes
// scanning, jump straight into the update-review modal (if it has pending changes).
var pendingReviewRepo = null;
function maybeOpenPendingReview(repo){
  if(pendingReviewRepo !== repo) return;
  var s = STATE.sites[repo];
  if(!s || s.scanState==='scanning' || s.scanState==='queued') return; // still working
  pendingReviewRepo = null;
  if(s.scanState==='done' && s.status==='update'){ STATE.sel[repo]=true; renderApplyBar(); openConfirmUpdate(); }
}
function enqueueScan(repos, force){
  repos.forEach(function(r){
    var s = ensureSite(r);
    if(force || s.scanState==='idle' || s.scanState==='error'){
      s.scanState='queued';
      if(scanQueue.indexOf(r)===-1) scanQueue.push(r);
    }
  });
  pumpScan(); renderScanBar();
}
function pumpScan(){
  while(scanning < SCAN_CONCURRENCY && scanQueue.length){
    var r = scanQueue.shift(); var s = ensureSite(r);
    if(s.scanState!=='queued'){ continue; }
    s.scanState='scanning'; scanning++;
    (function(repo, site){
      $.post('svn_site_status.php', {repository:repo}, function(data){
        scanning--;
        if(data && data.ok){
          site.status=data.status; site.behind=data.behind; site.headRev=data.headRev;
          site.lastBy=data.lastBy; site.lastAt=data.lastAt; site.errorMsg=data.errorMsg||'';
          site.files=data.files||[]; site.clientId=data.clientId||0; site.adminUrl=data.adminUrl||''; site.scanState='done';
          // Only auto-select if the repo is still in the current scope: a scan enqueued under a
          // previous (multi-site) scope can finish after the user has navigated to a single site,
          // and selecting it here would inflate the apply-bar count with an off-screen straggler.
          if(autoSelectUpdates && site.status==='update' && scopedRepos().indexOf(repo)!==-1){ STATE.sel[repo]=true; }
        } else {
          site.status='error'; site.errorMsg=(data&&data.error)||'Scan failed'; site.scanState='error';
        }
        renderTable(); renderApplyBar(); renderScanBar();
        if(pendingReviewRepo) maybeOpenPendingReview(pendingReviewRepo);
        pumpScan();
      }, 'json').fail(function(){
        scanning--; site.status='error'; site.errorMsg='Request failed'; site.scanState='error';
        renderTable(); renderApplyBar(); renderScanBar();
        if(pendingReviewRepo) maybeOpenPendingReview(pendingReviewRepo);
        pumpScan();
      });
    })(r, s);
  }
  renderScanBar();
}
function stopScan(){ scanQueue.forEach(function(r){ var s=STATE.sites[r]; if(s&&s.scanState==='queued') s.scanState='idle'; }); scanQueue=[]; renderScanBar(); }
function pendingScanCount(){ return scanQueue.length + scanning; }

function renderScanBar(){
  var n = pendingScanCount();
  var $b = $('#scanBar');
  if(n>0){
    $b.html('<span class="spin"></span> Scanning sites… <b>'+n+'</b> remaining'
      + '<div class="spacer" style="margin-left:auto"></div>'
      + '<button class="btn tiny ghost" id="scanStop">Stop</button>').show();
  } else { $b.hide().empty(); }
}

// ---------------- groups (server) ----------------
function loadGroups(cb){
  $.post('svn_groups.php', {action:'list'}, function(d){
    if(d&&d.ok){ STATE.groups=d.groups||[]; }
    renderGroupTrigger();
    if(cb) cb();
  }, 'json');
}
function groupsAction(params, cb){
  $.post('svn_groups.php', params, function(d){
    if(d&&d.ok){ STATE.groups=d.groups||[]; renderGroupTrigger(); if(cb) cb(d); }
    else { alert((d&&d.error)||'Group action failed.'); }
  }, 'json');
}

function renderGroupTrigger(){
  var ag = STATE.activeGroup, name, count;
  if(ag==='__none'){ name='Choose a group'; count=''; }
  else if(ag==='__all'){ name='All sites'; count=REPOS.length; }
  else if(ag.indexOf('__one:')===0){ name=ag.slice(6); count=1; }
  else { var g=groupById(ag); name = g?g.name:'Select group'; count = g?g.siteIds.length:0; }
  $('#grpTriggerName').text(name);
  $('#grpTriggerCount').text(count).css('display', count===''?'none':'');
}

function openGroupMenu(){
  closeAllPopovers();
  var html = '<div class="grp-dd-backdrop" data-close-pop="1"></div><div class="grp-dd-menu">';
  html += '<div class="ddm-label">Scope</div>';
  html += grpItem('__all', icon('grid',17), 'All sites', REPOS.length, false);
  html += '<div class="rail-sep"></div><div class="ddm-label">Saved groups</div>';
  if(!STATE.groups.length){ html += '<div class="ddm-empty">No saved groups yet. Select sites in the list and save them as a group.</div>'; }
  STATE.groups.forEach(function(g){
    html += grpItem(String(g.id), icon('folder',17), g.name, g.siteIds.length, true);
  });
  html += '<div class="rail-sep"></div>';
  html += '<button class="btn ghost grp-new" id="grpNewBtn">'+icon('folderPlus',17)+' New group from selection</button>';
  html += '<button class="btn ghost grp-new" id="grpNewListBtn">'+icon('folderPlus',17)+' New group from a list</button>';
  html += '</div>';
  $('#grpDd').append(html);
  $('#grpTrigger').addClass('open');
}
function grpItem(id, ic, name, count, deletable){
  var on = String(STATE.activeGroup)===String(id);
  var del = deletable ? '<span class="grp-del" data-del-group="'+esc(id)+'" title="Delete group">'+icon('x',13)+'</span>' : '';
  return '<button class="grp'+(on?' on':'')+'" data-pick-group="'+esc(id)+'">'
    + '<span class="grp-ico">'+ic+'</span>'
    + '<span class="grp-name">'+esc(name)+'</span>'
    + '<span class="grp-count">'+count+'</span>'+del+'</button>';
}
function closeGroupMenu(){ $('#grpDd .grp-dd-backdrop, #grpDd .grp-dd-menu').remove(); $('#grpTrigger').removeClass('open'); }

function pickScope(id, opts){
  opts = opts || {};
  STATE.activeGroup = String(id);
  saveScope(STATE.activeGroup);
  var _nh = scopeToHash(STATE.activeGroup);
  if(((location.hash||'').replace(/^#/,'')) !== _nh){ location.hash = _nh; }
  STATE.sel = {};
  STATE.collapsed = {};
  // A group / all-sites scope is multi-site: clear the single-site finder match so its
  // stale "Review & update" button (for the previously found site, e.g. the one pre-filled
  // on load) doesn't linger and update the wrong repo. The bottom apply bar is the review
  // control for multi-site scopes.
  if(String(id).indexOf('__one:')!==0){ $('#finderInput').val(''); $('#finderMatch').hide().empty(); }
  renderGroupTrigger();
  var repos = scopedRepos();
  // auto-scan for groups & single site; manual for very large "all sites"
  if(id==='__all'){
    if(REPOS.length > 40){
      autoSelectUpdates = false;
      renderTable(); renderApplyBar();
      // do not auto-scan hundreds; user scans via the banner
      return;
    }
  }
  autoSelectUpdates = true;
  enqueueScan(repos, false);
  // enqueueScan only (re)scans idle/error sites, so any group site that was already scanned
  // (e.g. during a prior "all sites" pass) would never get auto-selected — leaving the apply
  // bar empty even though it has updates. Select those already-known updatable sites now.
  repos.forEach(function(r){ var s=STATE.sites[r]; if(s && s.scanState==='done' && s.status==='update') STATE.sel[r]=true; });
  renderTable(); renderApplyBar();
}

// ---------------- finder ----------------
function renderFinderMatch(){
  var q = $('#finderInput').val().trim();
  var $m = $('#finderMatch');
  if(!q){ $m.hide().empty(); return; }
  var exact = null, partial = null, ql = q.toLowerCase();
  for(var i=0;i<REPOS.length;i++){ if(REPOS[i]===q){ exact=REPOS[i]; break; } }
  if(!exact){ for(var j=0;j<REPOS.length;j++){ if(REPOS[j].toLowerCase().indexOf(ql)!==-1){ partial=REPOS[j]; break; } } }
  var match = exact || partial;
  if(!match){ $m.hide().empty(); return; }
  var path = SVN_PATH_PREFIX + match;
  var html = '<div class="quick-path"><span style="color:var(--muted-2);font-weight:700">Path</span>'
    + '<span class="mono" style="font-size:12px">'+esc(path)+'</span>'
    + '<button class="copy-btn" id="finderCopy" data-copy="'+esc(path)+'" title="Copy">'+icon('copy',14)+'</button></div>'
    + reviewBtnHtml(match);
  $m.html(html).show();
}
// The big "Review & update" button reflects the matched site's scan state — same gating
// idea as the apply-bar button: disabled (with a spinner) while changes load, enabled
// only once there are updates to apply.
function reviewBtnHtml(repo){
  var s = STATE.sites[repo], st = s ? s.scanState : 'idle';
  var attrs = 'class="btn grad" id="finderReview" data-repo="'+esc(repo)+'"';
  if(st==='queued' || st==='scanning') return '<button '+attrs+' disabled><span class="spin"></span> Loading changes…</button>';
  if(s && st==='done' && s.status==='update')  return '<button '+attrs+'>'+icon('refresh',16)+' Review &amp; update'+(s.behind?' ('+s.behind+')':'')+'</button>';
  if(s && st==='done' && s.status==='current') return '<button '+attrs+' disabled>'+icon('check',16)+' Up to date</button>';
  if(s && st==='error')                        return '<button '+attrs+' disabled>'+icon('alert',16)+' Scan failed</button>';
  return '<button '+attrs+'>'+icon('refresh',16)+' Review &amp; update</button>'; // idle/not scanned: kicks off the scan
}
function refreshFinderReview(){
  var $b = $('#finderReview'); if(!$b.length) return;
  $b.replaceWith(reviewBtnHtml($b.attr('data-repo')));
}

// ---------------- main render ----------------
var FILTERS = [['all','All'],['update','Updates'],['current','Current'],['error','Errors']];
function renderFilterChips(){
  var html = FILTERS.map(function(f){ return '<button class="fchip'+(STATE.filter===f[0]?' on':'')+'" data-filter="'+f[0]+'">'+f[1]+'</button>'; }).join('');
  $('#filterChips').html(html);
}

function visibleRepos(){
  var repos = scopedRepos();
  var out = [];
  for(var i=0;i<repos.length;i++){
    var r = repos[i], s = STATE.sites[r];
    if(STATE.query && r.toLowerCase().indexOf(STATE.query.toLowerCase())===-1) continue;
    if(STATE.filter!=='all'){
      if(!s || s.scanState!=='done' && !(STATE.filter==='error' && s && s.status==='error')) continue;
      if(s.status!==STATE.filter) continue;
    }
    out.push(r);
  }
  return out;
}

function statusBadge(s){
  if(!s || s.scanState==='idle' || s.scanState==='queued') return '<span class="sbadge idle"><span class="sd"></span>Not scanned</span>';
  if(s.scanState==='scanning') return '<span class="sbadge scanning"><span class="spin"></span>Scanning…</span>';
  var map = { update:'Update available', current:'Up to date', error:'Error' };
  var cls = s.status||'idle';
  return '<span class="sbadge '+cls+'"><span class="sd"></span>'+(map[s.status]||'Unknown')+'</span>';
}

function sitesWithFiles(){ return visibleRepos().filter(function(r){ var s=STATE.sites[r]; return s && (s.files||[]).length>0; }); }
function updateCollapseAllBtn(){
  var $b=$('#collapseAll'), sw=sitesWithFiles();
  if(STATE.view!=='list' || sw.length===0){ $b.hide(); return; }
  var allCol = sw.every(function(r){ return STATE.collapsed[r]; });
  $b.show().attr('data-mode', allCol?'expand':'collapse').attr('title', allCol?'Expand all':'Collapse all')
    .html(icon('chevron',16,1.8, allCol?'':'transform:rotate(180deg)'));
}
function renderTable(){
  $('#mainHead').toggle(scopedRepos().length>0);
  updateCollapseAllBtn();
  if(STATE.view==='cards'){ renderCards(); return; }
  var repos = visibleRepos();
  if(!repos.length){
    $('#tableHost').html('<div class="empty"><div class="e-ico">'+icon('search',34)+'</div><div class="e-t">'+emptyMsg()+'</div></div>');
    return;
  }
  var rows = '';
  repos.forEach(function(r){
    var s = ensureSite(r);
    var files = s.files||[];
    var hasFiles = files.length>0;
    var isCol = !!STATE.collapsed[r];
    var selectable = s.scanState==='done' && s.status==='update';
    var seld = !!STATE.sel[r];
    rows += '<tr class="site-row'+(seld?' sel':'')+'" data-repo="'+esc(r)+'">'
      + '<td class="col-chk"><input type="checkbox" class="chk row-chk" '+(seld?'checked':'')+' '+(selectable?'':'disabled')+' data-repo="'+esc(r)+'" title="'+(selectable?'Select for update':'Nothing to update')+'"></td>'
      + '<td colspan="5" class="site-head-cell"><div class="site-head">'
        + '<button class="site-collapse" data-collapse="'+esc(r)+'" '+(hasFiles?'':'disabled')+' title="'+(isCol?'Show files':'Hide files')+'">'+icon('chevron',16,1.8,(isCol?'transform:rotate(-90deg)':''))+'</button>'
        + '<span class="host">'+esc(r)+'</span>'
        + statusBadge(s)
        + (s.scanState==='done'&&s.status==='update' ? '<span class="behind">'+icon('up',13)+s.behind+' change'+(s.behind!==1?'s':'')+(s.headRev?' <span class="rev mono">r'+esc(s.headRev)+'</span>':'')+'</span>' : '')
        + (s.lastBy||s.lastAt ? '<span class="site-meta">'+(s.lastBy?icon('user',12)+' '+esc(s.lastBy):'')+(s.lastBy&&s.lastAt?' <span class="mdot"></span> ':'')+(s.lastAt?icon('clock',12)+' '+esc(s.lastAt):'')+'</span>' : '')
        + '<span class="sh-spacer"></span>'
        + '<span class="file-count">'+(hasFiles?(files.length+' file'+(files.length!==1?'s':'')):(s.scanState==='done'?(s.status==='current'?'Up to date':'—'):''))+'</span>'
        + (s.adminUrl ? '<a class="site-btn site-admin" href="'+esc(s.adminUrl)+'" target="_blank" rel="noopener" title="Admin login ('+esc(r)+')">'+icon('login',15)+'</a>'
            : (s.clientId ? '<a class="site-btn site-admin" href="../create_client.php?client_id='+esc(s.clientId)+'" target="_blank" rel="noopener" title="Open client record">'+icon('login',15)+'</a>' : ''))
        + '<button class="site-btn" data-refresh="'+esc(r)+'" title="Re-scan this site">'+icon('refresh',15)+'</button>'
        + '<button class="site-btn" data-actions="'+esc(r)+'" title="Site actions">'+icon('dots',16)+'</button>'
        + '<button class="site-btn" data-addgroup="'+esc(r)+'" title="Add to group">'+icon('folderPlus',16)+'</button>'
      + '</div></td></tr>';
    if(!isCol && s.scanState==='error'){
      rows += '<tr class="note-row first-note"><td class="col-chk"></td><td colspan="5"><div class="err-note">'+icon('alert',16)+' '+esc(s.errorMsg||'Error')+'</div></td></tr>';
    }
    if(!isCol && hasFiles){
      files.forEach(function(f){
        var dir = (f.file_path||'public_html/'); if(dir && dir.indexOf('public_html')!==0){ dir = 'public_html/'+dir; }
        rows += '<tr class="file-row">'
          + '<td class="col-chk"></td>'
          + '<td class="fp-dir mono">'+esc(dir)+'</td>'
          + '<td class="fp-name"><button class="file-open" data-open-repo="'+esc(r)+'" data-open-file="'+esc(f.rel_path||((f.file_path||'')+f.file_name))+'" title="Open '+esc(f.file_name)+'">'+esc(f.file_name)+'</button>'
            + '<button class="file-copy" data-copy="'+esc(dir+f.file_name)+'" title="Copy file path">'+icon('copy',13)+'</button></td>'
          + '<td><span class="fstat '+esc(f.status_badge||'default')+'"'+(f.status_tip?' title="'+esc(f.status_tip)+'"':'')+'>'+esc(f.status||'')+'</span></td>'
          + '<td class="rev mono">'+esc(f.version||'')+'</td>'
          + '<td class="col-diff"><button class="vdiff" data-diff-repo="'+esc(r)+'" data-diff-file="'+esc(f.rel_path||((f.file_path||'')+f.file_name))+'">View diff</button></td>'
        + '</tr>';
      });
    }
    if(!isCol && !hasFiles && s.scanState==='done' && s.status==='current'){
      rows += '<tr class="note-row first-note"><td class="col-chk"></td><td colspan="5"><span class="up-note">Working copy is up to date — nothing to deploy.</span></td></tr>';
    }
    if(!isCol && (s.scanState==='idle'||s.scanState==='queued')){
      rows += '<tr class="note-row first-note"><td class="col-chk"></td><td colspan="5"><span class="scan-note">'+(s.scanState==='queued'?'<span class="spin"></span> Queued for scan…':('Not scanned yet. <button class="btn tiny" data-refresh="'+esc(r)+'">Scan this site</button>'))+'</span></td></tr>';
    }
  });

  var banner = '';
  if(STATE.activeGroup==='__all' && REPOS.length>40){
    var unscanned = scopedRepos().filter(function(r){ var s=STATE.sites[r]; return !s || (s.scanState!=='done'&&s.scanState!=='scanning'&&s.scanState!=='queued'); }).length;
    if(unscanned>0) banner = '<div class="scanbar" style="display:flex"><span>'+REPOS.length+' sites. Scanning every repository is slow — scan a saved group instead, or </span><button class="btn tiny" id="scanAllBtn">Scan all visible</button></div>';
  }

  var html = banner
    + '<div class="ctable-wrap"><table class="ctable"><thead><tr>'
    + '<th class="col-chk"></th><th>File path</th><th>File name</th><th>Status</th><th>Revision</th><th class="col-diff">Diff</th>'
    + '</tr></thead><tbody>'+rows+'</tbody></table></div>';
  $('#tableHost').html(html);
  syncSelAll();
}

function emptyMsg(){
  if(STATE.activeGroup==='__none') return 'Find a site above, or pick a saved group to get started.';
  if(STATE.filter!=='all') return 'No '+STATE.filter+' sites match these filters.';
  if(STATE.activeGroup.indexOf('__one:')===0) return 'No matching site.';
  if(STATE.activeGroup!=='__all' && !scopedRepos().length) return 'This group has no sites yet. Add sites with the folder + button on a row.';
  return 'No sites match these filters.';
}

function renderCards(){
  var repos = visibleRepos();
  if(!repos.length){ $('#tableHost').html('<div class="empty"><div class="e-ico">'+icon('grid',34)+'</div><div class="e-t">'+emptyMsg()+'</div></div>'); return; }
  var cards = repos.map(function(r){
    var s = ensureSite(r); var seld = !!STATE.sel[r]; var selectable = s.scanState==='done'&&s.status==='update';
    return '<div class="scard'+(seld?' sel':'')+'" data-cardrepo="'+esc(r)+'">'
      + '<div class="scard-top"><div><div class="host">'+esc(r)+'</div>'
        + '<div class="meta">'+(s.lastBy?esc(s.lastBy):'')+(s.lastBy&&s.lastAt?' · ':'')+(s.lastAt?esc(s.lastAt):'')+'</div></div>'
        + '<input type="checkbox" class="chk row-chk" '+(seld?'checked':'')+' '+(selectable?'':'disabled')+' data-repo="'+esc(r)+'"></div>'
      + statusBadge(s)
      + '<div class="scard-foot">'
        + (s.scanState==='done'&&s.status==='update' ? '<span class="behind">'+icon('up',14)+s.behind+' behind</span>' : '<span class="behind none">'+(s.status==='error'?'Needs attention':(s.scanState==='done'?'Up to date':'Not scanned'))+'</span>')
        + '<button class="btn ghost tiny" data-refresh="'+esc(r)+'">Scan '+icon('refresh',13)+'</button>'
      + '</div></div>';
  }).join('');
  $('#tableHost').html('<div class="cards">'+cards+'</div>');
  syncSelAll();
}

// ---------------- selection / apply bar ----------------
// Selections are scoped to the active group/site: an entry left in STATE.sel for a repo outside
// the current scope (e.g. a late scan callback) is never counted in the apply bar nor updated.
function selRepos(){ var sc=scopedRepos(); return Object.keys(STATE.sel).filter(function(r){ return STATE.sel[r] && sc.indexOf(r)!==-1; }); }
function selUpdatable(){ return selRepos().filter(function(r){ var s=STATE.sites[r]; return s&&s.status==='update'; }); }
function renderApplyBar(){
  var sel = selRepos(), upd = selUpdatable();
  $('#abCount').text(sel.length);
  $('#abCountLbl').text('site'+(sel.length!==1?'s':'')+' selected');
  if(upd.length){
    var total = upd.reduce(function(a,r){ return a + (STATE.sites[r].behind||0); }, 0);
    $('#abSub').css('color','').text(upd.length+' with updates · '+total+' changes total');
  } else {
    $('#abSub').css('color','var(--muted)').text(sel.length?'None of the selected sites need updating':'');
  }
  $('#abUpdate').prop('disabled', upd.length===0).html(icon('refresh',16)+' Review &amp; update'+(upd.length?' ('+upd.length+')':''));
  $('#applyBar').toggleClass('show', sel.length>0);
  refreshFinderReview();
}
function syncSelAll(){
  var vis = visibleRepos().filter(function(r){ var s=STATE.sites[r]; return s&&s.scanState==='done'&&s.status==='update'; });
  var allSel = vis.length>0 && vis.every(function(r){ return STATE.sel[r]; });
  var someSel = vis.some(function(r){ return STATE.sel[r]; });
  var $a = $('#selAll');
  $a.prop('checked', allSel).prop('disabled', vis.length===0);
  $a.toggleClass('partial', !allSel && someSel);
}

// ---------------- popovers ----------------
function closeAllPopovers(){ $('#svnApp .pop-backdrop, #svnApp .pop').remove(); $('#svnApp .site-btn.on').removeClass('on'); if(typeof closeCtxMenu==='function') closeCtxMenu(); closeGroupMenu(); $('#finderDd').removeClass('show'); }

function openAddGroupPop(repo, anchor){
  closeAllPopovers();
  var rect = anchor.getBoundingClientRect();
  var top = rect.bottom+6, right = Math.max(12, window.innerWidth-rect.right);
  var html = '<div class="pop-backdrop" data-close-pop="1"></div><div class="pop" style="top:'+top+'px;right:'+right+'px">';
  html += '<div class="pop-title">Add <b>'+esc(repo)+'</b> to group</div>';
  if(!STATE.groups.length){ html += '<div class="ddm-empty">No groups yet — create one below.</div>'; }
  STATE.groups.forEach(function(g){
    var inG = g.siteIds.indexOf(repo)!==-1;
    html += '<button class="pop-item'+(inG?' on':'')+'" data-toggle-group="'+g.id+'" data-repo="'+esc(repo)+'">'
      + '<span class="pop-check">'+(inG?icon('checkSm',13,2.4):'')+'</span>'
      + '<span class="pop-name">'+esc(g.name)+'</span><span class="grp-count">'+g.siteIds.length+'</span></button>';
  });
  html += '<div class="rail-sep"></div><button class="btn ghost grp-new" data-newgroup-repo="'+esc(repo)+'">'+icon('folderPlus',16)+' New group with this site</button>';
  html += '</div>';
  $('#svnApp').append(html);
}

function openActionsPop(repo, anchor){
  closeAllPopovers();
  var rect = anchor.getBoundingClientRect();
  var top = rect.bottom+6, right = Math.max(12, window.innerWidth-rect.right);
  var items = [
    ['history','History', icon('history',16)],
    ['log','Error log', icon('file',16)],
    ['critical','Critical errors', icon('alert',16)],
    ['cron','Cron jobs', icon('calendar',16)],
    ['servers','Servers', icon('server',16)],
    ['backups','Backups', icon('database',16)],
    ['devcopy','Dev copy', icon('copy',16)],
    ['devtools','Dev tools', icon('tools',16)]
  ];
  var html = '<div class="pop-backdrop" data-close-pop="1"></div><div class="pop" style="top:'+top+'px;right:'+right+'px">';
  html += '<div class="pop-title"><b>'+esc(repo)+'</b></div>';
  items.forEach(function(it){
    html += '<button class="pop-item" data-siteaction="'+it[0]+'" data-repo="'+esc(repo)+'"><span style="color:var(--muted)">'+it[2]+'</span><span class="pop-name">'+it[1]+'</span></button>';
  });
  html += '</div>';
  $('#svnApp').append(html);
}

// ---------------- domain right-click context menu ----------------
var CTX_ADMIN = {};   // repo -> adminUrl ('' once resolved with no admin)
function domainFullUrl(repo){ return 'https://'+repo+'/'; }
function knownAdminUrl(repo){
  if(typeof STATE!=='undefined' && STATE.sites[repo] && STATE.sites[repo].adminUrl) return STATE.sites[repo].adminUrl;
  return CTX_ADMIN.hasOwnProperty(repo) ? CTX_ADMIN[repo] : null;   // null = not resolved yet
}
function adminPathFromUrl(u){
  if(!u) return '';
  var p='';
  try{ p = new URL(u).pathname || ''; }
  catch(e){ p = String(u).replace(/^https?:\/\/[^\/]+/i,'').replace(/[?#].*$/,''); }
  return p.replace(/admin_login\.php$/i,'');   // -> /ADM-XXX/
}
function closeCtxMenu(){ $('#svnApp .svnctx').remove(); $(document).off('.svnctx'); $(window).off('.svnctx'); }
function ctxToast(x, y, msg){
  $('#svnApp .svnctx-toast').remove();
  var $t=$('<div class="svnctx-toast">'+esc(msg)+'</div>').appendTo('#svnApp').css({left:x+'px', top:(y+2)+'px'});
  requestAnimationFrame(function(){ $t.addClass('show'); });
  setTimeout(function(){ $t.removeClass('show'); setTimeout(function(){ $t.remove(); }, 200); }, 1100);
}
function ctxCopy(text, x, y, label){ copyText(text, function(){}); ctxToast(x, y, 'Copied '+label); }
function openDomainCtx(repo, x, y){
  closeCtxMenu(); closeAllPopovers();
  var admin = knownAdminUrl(repo);              // string | null(unresolved)
  var pending = (admin===null);
  function item(action, label, ic, sub, cls){
    return '<button class="svnctx-item'+(cls||'')+'" data-ctx="'+action+'"><span class="svnctx-ic">'+icon(ic,15)+'</span><span>'+esc(label)+'</span>'
      + (sub?'<span class="svnctx-sub mono">'+esc(sub)+'</span>':'')+'</button>';
  }
  var adminCls = pending ? ' svnctx-pending' : (admin ? '' : ' svnctx-disabled');
  var html = '<div class="svnctx" data-ctxrepo="'+esc(repo)+'">'
    + '<div class="svnctx-head mono">'+esc(repo)+'</div>'
    + '<div class="svnctx-sec">Copy</div>'
    + item('copy-domain','Domain','copy')
    + item('copy-url','Full URL','copy')
    + item('copy-admin','Admin path','copy', pending?'…':(admin?adminPathFromUrl(admin):'none'), adminCls)
    + '<div class="svnctx-sec">Open</div>'
    + item('open-url','URL','link')
    + item('open-admin','Admin','login', '', adminCls)
    + '</div>';
  var $m = $(html).appendTo('#svnApp');
  $m.css({left:x+'px', top:y+'px'});
  // keep on-screen
  var w=$m.outerWidth(), h=$m.outerHeight();
  if(x+w > window.innerWidth-8)  $m.css('left', Math.max(8, window.innerWidth - w - 8));
  if(y+h > window.innerHeight-8) $m.css('top',  Math.max(8, window.innerHeight - h - 8));
  // lazily resolve admin for not-yet-scanned sites
  if(pending){
    $.post('site_admin.php', {repository:repo}, function(d){
      var u = (d&&d.ok) ? (d.adminUrl||'') : '';
      CTX_ADMIN[repo] = u;
      var $cur = $('#svnApp .svnctx[data-ctxrepo="'+cssEsc(repo)+'"]'); if(!$cur.length) return;
      $cur.find('[data-ctx=copy-admin]').removeClass('svnctx-pending').addClass(u?'':'svnctx-disabled').find('.svnctx-sub').text(u?adminPathFromUrl(u):'none');
      $cur.find('[data-ctx=open-admin]').removeClass('svnctx-pending').addClass(u?'':'svnctx-disabled');
    }, 'json').fail(function(){ CTX_ADMIN[repo]=''; var $cur=$('#svnApp .svnctx[data-ctxrepo="'+cssEsc(repo)+'"]'); $cur.find('.svnctx-pending').removeClass('svnctx-pending').addClass('svnctx-disabled'); $cur.find('[data-ctx=copy-admin] .svnctx-sub').text('none'); });
  }
  setTimeout(function(){
    $(document).on('mousedown.svnctx', function(e){ if(!$(e.target).closest('.svnctx').length) closeCtxMenu(); });
    $(document).on('contextmenu.svnctx', function(e){ if(!$(e.target).closest('.svnctx,.host').length) closeCtxMenu(); });
    $(document).on('keydown.svnctx', function(e){ if(e.key==='Escape') closeCtxMenu(); });
    $(window).on('scroll.svnctx resize.svnctx', function(){ closeCtxMenu(); });
  }, 0);
}

// ---------------- modals ----------------
function modal(html){ $('#modalHost').html('<div class="scrim" data-scrim="1">'+html+'</div>'); }
function closeModal(){ $(document).off('keydown.updDone'); if(typeof bkStopPoll==='function'){ bkStopPoll(); BK_JOB=null; } if(typeof dcStopPoll==='function'){ dcStopPoll(); DC_JOB=null; } if(typeof dcStopImgPoll==='function'){ dcStopImgPoll(); } BK_OPEN_REPO=null; INFO_OPEN=null; DC_OPEN_REPO=null; GCRIT=null; var h=(location.hash||'').replace(/^#/,''); var base=h.split('~')[0]; if(base!==h){ location.hash = base; } if(typeof closeSource==='function') closeSource(); $('#modalHost').empty(); }

// Styled confirm dialog (replaces window.confirm); layers above any open modal.
var UICONFIRM_CB = null;
function uiConfirm(opts, onYes){
  opts = opts || {};
  UICONFIRM_CB = (typeof onYes === 'function') ? onYes : null;
  var yesCls = opts.danger ? 'btn solid ui-confirm-danger' : 'btn grad';
  var body = opts.bodyHtml ? opts.bodyHtml : esc(opts.body || '');
  $('#confirmHost').html('<div class="scrim scrim2" data-confirm-scrim="1"><div class="modal confirm-modal">'
    + '<div class="modal-head"><div class="mh-ico">'+icon(opts.icon || 'alert', 21)+'</div>'
    + '<div style="min-width:0"><h3>'+esc(opts.title || 'Are you sure?')+'</h3></div>'
    + '<button class="mh-x" data-close-confirm="1">'+icon('x',17)+'</button></div>'
    + '<div class="modal-body confirm-body">'+body+'</div>'
    + '<div class="modal-foot"><div class="mf-grow">'+esc(opts.foot || '')+'</div>'
    + '<button class="btn ghost" data-close-confirm="1">'+esc(opts.cancelLabel || 'Cancel')+'</button>'
    + '<button class="'+yesCls+'" id="uiConfirmYes">'+esc(opts.confirmLabel || 'Confirm')+'</button></div></div></div>');
}
function closeConfirm(){ UICONFIRM_CB = null; $('#confirmHost').empty(); }
function confirmOpen(){ return $('#confirmHost').children().length > 0; }

function openConfirmUpdate(){
  var upd = selUpdatable();
  var skip = selRepos().filter(function(r){ var s=STATE.sites[r]; return s&&s.status==='error'; });
  if(!upd.length){ return; }
  var rows = upd.map(function(r){ var s=STATE.sites[r];
    return '<div class="prow">'+icon('branch',16,1.8,'color:var(--muted)')+'<div class="phost"><div class="h">'+esc(r)+'</div><div class="s">'+s.behind+' change'+(s.behind!==1?'s':'')+(s.headRev?' · r'+esc(s.headRev):'')+'</div></div></div>';
  }).join('');
  var skipHtml = skip.length ? '<div style="margin-top:14px;padding:12px 14px;border-radius:10px;background:var(--err-bg);color:var(--err);font-size:13px"><b>'+skip.length+' site'+(skip.length>1?'s':'')+' skipped</b> — '+esc(skip.join(', '))+' '+(skip.length>1?'have':'has')+' errors and must be resolved first.</div>' : '';
  modal('<div class="modal"><div class="modal-head"><div class="mh-ico">'+icon('refresh',21)+'</div>'
    + '<div><h3>Apply updates to '+upd.length+' site'+(upd.length!==1?'s':'')+'?</h3><p>Each working copy will be updated from its SVN repository to the latest revision on the live server.</p></div>'
    + '<button class="mh-x" data-close-modal="1">'+icon('x',17)+'</button></div>'
    + '<div class="modal-body">'+rows+skipHtml+'</div>'
    + '<div class="modal-foot"><div class="mf-grow">This cannot be undone from here.</div>'
    + '<button class="btn ghost" data-close-modal="1">Cancel</button>'
    + '<button class="btn grad" id="confirmApply">'+icon('refresh',16)+' Apply '+upd.length+' update'+(upd.length!==1?'s':'')+'</button></div></div>');
}

function runBatchUpdate(repos){
  var states = {}; repos.forEach(function(r){ states[r]='queued'; });
  function rowHtml(r){
    var st = states[r], s = STATE.sites[r];
    var right = '';
    if(st==='queued') right = '<span class="pstate queued">'+icon('clock',14)+' Queued</span>';
    else if(st==='updating') right = '<span class="pstate updating"><span class="spin"></span> Updating</span>';
    else if(st==='done') right = '<span class="pstate done"><span class="pcheck">'+icon('checkSm',12,2.4)+'</span> Done</span>';
    else if(st==='failed') right = '<span class="pstate failed"><span class="pfail">'+icon('x',11,2.4)+'</span> Failed</span>';
    return '<div class="prow" data-prow="'+esc(r)+'"><div class="phost"><div class="h">'+esc(r)+'</div><div class="s mono">'+(s.headRev?'→ r'+esc(s.headRev):'')+'</div></div>'+right+'</div>';
  }
  function render(done){
    var vals = Object.keys(states).map(function(k){return states[k];});
    var ok = vals.filter(function(v){return v==='done';}).length;
    var fail = vals.filter(function(v){return v==='failed';}).length;
    var head = done
      ? '<div class="mh-ico">'+icon('check',22)+'</div><div><h3>'+(fail?'Finished with errors':'All updates applied')+'</h3><p>'+ok+' succeeded'+(fail?' · '+fail+' failed':'')+'</p></div>'
      : '<div class="mh-ico"><span class="spin"></span></div><div><h3>Applying updates…</h3><p>Deploying to '+repos.length+' site'+(repos.length!==1?'s':'')+' in sequence.</p></div>';
    var summary = done ? '<div class="summary '+(fail?'mixed':'ok')+'"><div style="text-align:center;min-width:64px"><div class="s-big" style="color:var(--ok)">'+ok+'</div><div class="s-lbl">succeeded</div></div>'
      + (fail?'<div style="text-align:center;min-width:64px"><div class="s-big" style="color:var(--err)">'+fail+'</div><div class="s-lbl">failed</div></div>':'')
      + '<div style="flex:1;display:flex;align-items:center;font-size:13.5px;color:var(--ink-soft);line-height:1.4">'+(fail?'Failed sites kept their previous revision — open their error log and retry.':'Every selected site is now live on the latest revision.')+'</div></div>' : '';
    modal('<div class="modal"><div class="modal-head">'+head+(done?'<button class="mh-x" data-close-modal="1">'+icon('x',17)+'</button>':'')+'</div>'
      + '<div class="modal-body">'+summary+repos.map(rowHtml).join('')+'</div>'
      + '<div class="modal-foot"><div class="mf-grow"></div><button class="btn solid" id="updDoneBtn" '+(done?'':'disabled')+' data-close-modal="1">'+(done?'Done':'Please wait…')+'</button></div></div>');
    if(done){
      // Finished: let ENTER dismiss the popup — focus the Done button (native Enter=click) and
      // add a one-shot Enter handler in case focus drifts off it.
      setTimeout(function(){ $('#updDoneBtn').focus(); }, 0);
      $(document).off('keydown.updDone').on('keydown.updDone', function(e){
        if(e.key==='Enter'){ e.preventDefault(); $(document).off('keydown.updDone'); closeModal(); }
      });
    }
  }
  render(false);
  var i = 0;
  function step(){
    if(i>=repos.length){ render(true); enqueueScan(repos, true); return; }
    var r = repos[i]; states[r]='updating'; render(false);
    $.post('update_repository.php', {repository:r}, function(){
      states[r]='done'; i++; setTimeout(step, 150);
    }).fail(function(){ states[r]='failed'; i++; setTimeout(step, 150); });
  }
  setTimeout(step, 250);
}

function openSaveGroup(presetRepos){
  var repos = presetRepos || selRepos();
  if(!repos.length){ alert('Select one or more sites in the list first, then save them as a group.'); return; }
  var chips = repos.map(function(r){ return '<span class="pchip" style="cursor:default">'+esc(r)+'</span>'; }).join('');
  modal('<div class="modal"><div class="modal-head"><div class="mh-ico">'+icon('folderPlus',21)+'</div>'
    + '<div><h3>Save as group</h3><p>Group '+repos.length+' site'+(repos.length!==1?'s':'')+' so you can re-select them in one click later. Groups are shared with everyone.</p></div>'
    + '<button class="mh-x" data-close-modal="1">'+icon('x',17)+'</button></div>'
    + '<div class="modal-body"><div class="field-label">Group name</div>'
    + '<div class="input">'+icon('folder',16)+'<input type="text" id="grpNameInput" placeholder="e.g. Watch brands" autofocus></div>'
    + '<div class="preview-chips">'+chips+'</div></div>'
    + '<div class="modal-foot"><div class="mf-grow"></div><button class="btn ghost" data-close-modal="1">Cancel</button>'
    + '<button class="btn solid" id="saveGroupBtn">'+icon('check',16)+' Save group</button></div></div>');
  $('#grpNameInput').data('repos', repos).focus();
}

// ---- new group from a pasted list of domains ----
// Normalise a pasted line to a bare host: drop protocol, path, leading www., trailing punctuation.
function glNormDomain(s){
  s = String(s).trim().toLowerCase();
  if(!s) return '';
  s = s.replace(/^[a-z][a-z0-9+.-]*:\/\//,''); // scheme://
  s = s.replace(/[\/?#].*$/,'');               // path/query/fragment
  s = s.replace(/^www\./,'');
  s = s.replace(/^[\s,;>*\-]+|[\s,;.]+$/g,'');
  return s;
}
// Resolve a normalised domain to a real repository name, or null. Returns {repo, how}.
function glMatchRepo(dom){
  if(!dom) return null;
  for(var i=0;i<REPOS.length;i++){ if(REPOS[i].toLowerCase()===dom) return {repo:REPOS[i], how:'exact'}; }
  for(var i=0;i<REPOS.length;i++){ if(REPOS[i].toLowerCase().replace(/^www\./,'')===dom) return {repo:REPOS[i], how:'exact'}; }
  var hits=[];
  for(var i=0;i<REPOS.length;i++){ var rl=REPOS[i].toLowerCase(); if(rl.indexOf(dom)!==-1 || dom.indexOf(rl)!==-1) hits.push(REPOS[i]); }
  if(hits.length===1) return {repo:hits[0], how:'fuzzy'};
  return null;
}
// Parse the textarea into de-duplicated, matched rows (order preserved).
function glParseList(text){
  var rows=[], seenRepo={}, seenDom={};
  String(text||'').split(/[\r\n]+/).forEach(function(line){
    var dom = glNormDomain(line);
    if(!dom || seenDom[dom]) return;
    seenDom[dom]=true;
    var m = glMatchRepo(dom);
    if(m && seenRepo[m.repo]) return; // two lines resolving to the same repo
    if(m) seenRepo[m.repo]=true;
    rows.push({ domain:dom, repo:m?m.repo:null, how:m?m.how:null });
  });
  return rows;
}
function renderGroupListPreview(){
  var rows = glParseList($('#grpListInput').val());
  var matched = rows.filter(function(r){ return r.repo; });
  var missing = rows.filter(function(r){ return !r.repo; });
  $('#grpListInput').data('repos', matched.map(function(r){ return r.repo; }));
  var $p = $('#grpListPreview');
  if(!rows.length){ $p.empty(); return; }
  var sum = '<div class="glist-sum"><span class="glist-pill ok">'+matched.length+' matched</span>'
    + (missing.length ? '<span class="glist-pill warn">'+missing.length+' not found</span>' : '')
    + '</div>';
  var body = rows.map(function(r){
    if(r.repo){
      var tag = r.how==='fuzzy' ? '<span class="gl-tag fuzzy">~ matched</span>' : '<span class="gl-tag ok">matched</span>';
      var repoTxt = (r.how==='fuzzy' || r.repo.toLowerCase()!==r.domain) ? '<span class="gl-arrow">'+icon('chevronR',13)+'</span><span class="gl-repo">'+esc(r.repo)+'</span>' : '';
      return '<div class="glist-row"><span class="gl-dom">'+esc(r.domain)+'</span>'+repoTxt+tag+'</div>';
    }
    return '<div class="glist-row miss"><span class="gl-dom">'+esc(r.domain)+'</span><span class="gl-tag miss">no repo</span></div>';
  }).join('');
  $p.html(sum + '<div class="glist-rows">'+body+'</div>');
}
function openGroupFromList(){
  modal('<div class="modal"><div class="modal-head"><div class="mh-ico">'+icon('folderPlus',21)+'</div>'
    + '<div><h3>New group from a list</h3><p>Paste one domain per line. Each is matched to an SVN repository, then saved as a shared group.</p></div>'
    + '<button class="mh-x" data-close-modal="1">'+icon('x',17)+'</button></div>'
    + '<div class="modal-body">'
    + '<div class="field-label">Group name</div>'
    + '<div class="input">'+icon('folder',16)+'<input type="text" id="grpNameInput" placeholder="e.g. Golf brands"></div>'
    + '<div class="field-label" style="margin-top:14px">Sites — one domain per line</div>'
    + '<textarea id="grpListInput" class="svn-ta" rows="8" placeholder="brownhills.co.uk&#10;completegolfer.co.uk&#10;discountgolfstore.co.uk"></textarea>'
    + '<div id="grpListPreview" class="glist-preview"></div>'
    + '</div>'
    + '<div class="modal-foot"><div class="mf-grow"></div><button class="btn ghost" data-close-modal="1">Cancel</button>'
    + '<button class="btn solid" id="saveListGroupBtn">'+icon('check',16)+' Create group</button></div></div>');
  $('#grpNameInput').focus();
}
function doSaveListGroup(){
  var name=$('#grpNameInput').val().trim();
  var repos=$('#grpListInput').data('repos')||[];
  if(!name){ $('#grpNameInput').focus(); return; }
  if(!repos.length){ alert('None of the pasted domains matched a repository. Check the spelling, or remove lines marked "no repo".'); return; }
  groupsAction({action:'create', name:name, repositories:repos}, function(d){ closeModal(); if(d.newId){ pickScope(String(d.newId), {}); } });
}

// diff modal
function diffLineClass(line){
  if(/^Index: /.test(line)) return 'index';
  if(/^={7,}$/.test(line)) return 'meta';
  if(/^--- /.test(line)) return 'file-old';
  if(/^\+\+\+ /.test(line)) return 'file-new';
  if(/^@@/.test(line)) return 'hunk';
  if(/^\\/.test(line) || /^Binary files /.test(line)) return 'meta';
  if(line.charAt(0)==='+') return 'add';
  if(line.charAt(0)==='-') return 'del';
  if(line.charAt(0)===' ') return 'ctx';
  return 'meta';
}
function looksUnified(t){ return /(^|\n)--- |\n\+\+\+ |(^|\n)@@ /.test(String(t)); }
function diffToHtml(body){
  body = body || '(No diff output.)';
  if(!looksUnified(body)) return '<div class="info-pre">'+esc(body)+'</div>';
  var lines = String(body).split(/\r?\n/);
  var adds = lines.filter(function(l){return l.charAt(0)==='+'&&l.indexOf('+++')!==0;}).length;
  var dels = lines.filter(function(l){return l.charAt(0)==='-'&&l.indexOf('---')!==0;}).length;
  var html = '<div class="diff-meta">'+(adds?'<span class="diff-add">+'+adds+'</span>':'')+(dels?'<span class="diff-del">−'+dels+'</span>':'')+'</div><div class="diffwrap">';
  html += lines.map(function(l){ return '<div class="dline '+diffLineClass(l)+'"><span class="dtext">'+esc(l)+'</span></div>'; }).join('');
  return html+'</div>';
}
function diffModal(titleMain, titleSub, footNote){
  modal('<div class="modal wide"><div class="modal-head"><div class="mh-ico">'+icon('file',21)+'</div>'
    + '<div style="min-width:0"><h3 style="word-break:break-all">'+esc(titleMain)+'</h3><p class="mono" style="font-size:12.5px">'+esc(titleSub)+'</p></div>'
    + '<button class="mh-x" data-close-modal="1">'+icon('x',17)+'</button></div>'
    + '<div class="modal-body"><div id="diffBody"><span class="spin"></span> Loading…</div></div>'
    + '<div class="modal-foot"><div class="mf-grow">'+esc(footNote||'')+'</div><button class="btn solid" data-close-modal="1">Close</button></div></div>');
}
function diffFetch(url, params){
  $.post(url, params, function(data){
    if(data && data.ok) $('#diffBody').html(diffToHtml(data.diff));
    else $('#diffBody').html('<div class="svn-modal-message svn-modal-message--warn">'+esc((data&&data.error)||'Could not load diff.')+'</div>');
  }, 'json').fail(function(xhr){
    var msg='Request failed.'; if(xhr.responseText){ try{ var j=JSON.parse(xhr.responseText); if(j&&j.error) msg=j.error; }catch(e){ msg=xhr.responseText.slice(0,400); } }
    $('#diffBody').html('<div class="svn-modal-message svn-modal-message--warn">'+esc(msg)+'</div>');
  });
}
function openDiff(repo, file){
  diffModal(file.split('/').pop(), file, 'Read-only preview of the incoming change.');
  diffFetch('get_file_diff.php', {repository:repo, file:String(file)});
}
function openRevDiff(repo, rev){
  diffModal('Revision r'+rev, repo+' · r'+rev, 'Changes introduced in r'+rev+'.');
  diffFetch('rev_diff.php', {repository:repo, rev:String(rev)});
}

// info modals (history / logs / cron / dev tools)
// ---- cron rendering ----
function cronHuman(expr){
  var p = expr.split(/\s+/);
  if(p[0] && p[0].charAt(0)==='@'){
    var sp={'@reboot':'At startup','@yearly':'Yearly','@annually':'Yearly','@monthly':'Monthly','@weekly':'Weekly','@daily':'Daily','@midnight':'Daily at midnight','@hourly':'Hourly'};
    return sp[p[0]]||'';
  }
  if(p.length<5) return '';
  var min=p[0],hr=p[1],dom=p[2],mon=p[3],dow=p[4];
  var DOW=['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
  var MON=['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  function num(s){ return /^\d+$/.test(s); }
  function two(n){ n=parseInt(n,10); return (n<10?'0':'')+n; }
  var at = (num(hr)&&num(min)) ? 'at '+two(hr)+':'+two(min) : '';
  if(/^\*\/\d+$/.test(min) && hr==='*'&&dom==='*'&&mon==='*'&&dow==='*') return 'Every '+min.slice(2)+' min';
  if(min==='*'&&hr==='*'&&dom==='*'&&mon==='*'&&dow==='*') return 'Every minute';
  if(num(min)&&hr==='*'&&dom==='*'&&mon==='*'&&dow==='*') return 'Hourly at :'+two(min);
  if(/^\*\/\d+$/.test(hr)&&dom==='*'&&mon==='*'&&dow==='*'&&num(min)) return 'Every '+hr.slice(2)+'h at :'+two(min);
  if(dom==='*'&&mon==='*'&&dow!=='*'&&num(min)){
    var days=dow.split(',').map(function(d){ return num(d)?(DOW[parseInt(d,10)%7]||d):d; }).join(', ');
    return 'Every '+days+(at?' '+at:'');
  }
  if(dom!=='*'&&mon==='*'&&dow==='*'&&num(dom)&&at) return 'Monthly on day '+dom+' '+at;
  if(dom!=='*'&&mon!=='*'&&dow==='*'&&num(dom)&&num(mon)&&at) return (MON[parseInt(mon,10)]||mon)+' '+dom+' '+at;
  if(dom==='*'&&mon==='*'&&dow==='*'&&at) return 'Daily '+at;
  return '';
}
// ---- editable cron model ----
var CRON = null; // { repo, user, lines:[ {type,raw,expr,cmd,_editing} ] }
function cronParse(text){
  var lines = String(text==null?'':text).replace(/\r/g,'').split('\n');
  while(lines.length && lines[lines.length-1].trim()==='') lines.pop();
  return lines.map(function(raw){
    var line = raw.trim();
    if(line===''){ return {type:'blank', raw:''}; }
    if(line.charAt(0)==='#'){ return {type:'comment', raw:raw}; }
    if(line.charAt(0)==='@'){ var m=line.match(/^(@\S+)\s+(.+)$/); if(m) return {type:'cron', raw:raw, expr:m[1], cmd:m[2]}; return {type:'other', raw:raw}; }
    if(/^[A-Za-z_][A-Za-z0-9_]*\s*=/.test(line) && line.split(/\s+/).length<6){ return {type:'env', raw:raw}; }
    var parts = line.split(/\s+/);
    if(parts.length>=6){ return {type:'cron', raw:raw, expr:parts.slice(0,5).join(' '), cmd:parts.slice(5).join(' ')}; }
    return {type:'other', raw:raw};
  });
}
function cronBuild(){
  return CRON.lines.map(function(l){ return l.type==='cron' ? (l.expr+' '+l.cmd).trim() : l.raw; }).join('\n');
}
function cronValidExpr(e){
  e=(e||'').trim(); if(e==='') return false;
  if(e.charAt(0)==='@') return /^@(reboot|yearly|annually|monthly|weekly|daily|midnight|hourly)$/.test(e);
  return e.split(/\s+/).length===5;
}
function cronRowHtml(l,i){
  if(l._editing){
    return '<div class="cron-item editing" data-ci="'+i+'">'
      + '<div class="cron-edit-fields"><input class="cron-in" data-cef="expr" value="'+esc(l.expr)+'" placeholder="* * * * *"/>'
      + '<input class="cron-in cron-in-cmd" data-cef="cmd" value="'+esc(l.cmd)+'" placeholder="command"/></div>'
      + '<div class="cron-row-actions"><button class="btn tiny solid cron-save-row" data-ci="'+i+'">Save</button>'
      + '<button class="btn tiny ghost cron-cancel-row" data-ci="'+i+'">Cancel</button></div></div>';
  }
  var human = cronHuman(l.expr);
  return '<div class="cron-item" data-ci="'+i+'">'
    + '<div class="cron-when">'+(human?'<div class="cron-human">'+icon('clock',13)+esc(human)+'</div>':'')+'<div class="cron-expr mono">'+esc(l.expr)+'</div></div>'
    + '<div class="cron-cmd mono">'+esc(l.cmd)+'</div>'
    + '<div class="cron-row-actions">'
      + '<button class="cron-icon-btn cron-run-row" data-ci="'+i+'" title="Run now (as user '+esc(CRON.user)+')">'+icon('play',13)+'</button>'
      + '<button class="cron-icon-btn cron-copy" data-copy="'+esc(l.expr+' '+l.cmd)+'" title="Copy">'+icon('copy',14)+'</button>'
      + '<button class="cron-icon-btn cron-edit-row" data-ci="'+i+'" title="Edit">'+icon('pencil',14)+'</button>'
      + '<button class="cron-icon-btn cron-del-row" data-ci="'+i+'" title="Delete">'+icon('x',14)+'</button>'
    + '</div></div>';
}
function renderCronEditor(){
  if(!CRON){ return; }
  var cronRows = []; CRON.lines.forEach(function(l,i){ if(l.type==='cron') cronRows.push({l:l,i:i}); });
  var envs = CRON.lines.filter(function(l){ return l.type==='env'; });
  var allText = cronRows.map(function(o){ return o.l.expr+' '+o.l.cmd; }).join('\n');
  var html = '<div class="cron-head"><span class="ct">'+cronRows.length+' cron job'+(cronRows.length!==1?'s':'')+'</span>'
    + '<span class="ct" style="color:var(--muted-2)">· user '+esc(CRON.user)+'</span><span class="spacer"></span>'
    + (cronRows.length?'<button class="btn tiny cron-copy-all" data-copy="'+esc(allText)+'">'+icon('copy',14)+' Copy all</button>':'')
    + '</div>';
  html += '<div class="cron-list">';
  if(!cronRows.length) html += '<div class="svn-modal-message" style="margin:0">No cron jobs installed for this user. Add one below.</div>';
  cronRows.forEach(function(o){ html += cronRowHtml(o.l, o.i); });
  html += '</div>';
  html += '<div class="cron-add"><div class="cron-add-title">'+icon('plus',13)+' Add a cron job</div>'
    + '<div class="cron-add-fields"><input class="cron-in" id="cronAddExpr" placeholder="30 14 * * *"/>'
    + '<input class="cron-in cron-in-cmd" id="cronAddCmd" placeholder="/usr/bin/php /mnt/drive2/vhosts/'+esc(CRON.repo)+'/cron/script.php"/>'
    + '<button class="btn solid tiny" id="cronAddBtn">'+icon('plus',14)+' Add</button></div>'
    + '<div class="cron-add-hint" id="cronAddHint">Schedule = 5 fields (min hour day month weekday) or @daily / @hourly / @reboot.</div></div>';
  if(envs.length){ html += '<div class="cron-env-title">Environment (preserved)</div><div class="info-pre">'+esc(envs.map(function(e){return e.raw;}).join('\n'))+'</div>'; }
  $('#infoBody').html(html);
}
function cronCommit(){
  $('#infoBody').css('opacity','.55');
  $.post('cron_manage.php', {action:'save', repository:CRON.repo, crontab:cronBuild()}, function(d){
    $('#infoBody').css('opacity','');
    if(d&&d.ok){ if(d.user) CRON.user=d.user; CRON.lines=cronParse(d.crontab); renderCronEditor(); }
    else { alert((d&&d.error)||'Could not save crontab.'); renderCronEditor(); }
  }, 'json').fail(function(){ $('#infoBody').css('opacity',''); alert('Save request failed.'); });
}
// Run a cron line's command now (as the crontab's user) and show the output inline beneath
// the row. The panel is transient — it's dropped on the next editor re-render.
function cronRun(i, $btn){
  var l = CRON && CRON.lines[i]; if(!l || l.type!=='cron') return;
  var cmd = (l.cmd||'').trim(); if(!cmd) return;
  var $row = $('.cron-item[data-ci="'+i+'"]'); if(!$row.length) return;
  $row.next('.cron-output').remove();
  var $panel = $('<div class="cron-output"><div class="cron-output-head">'
    + '<span class="co-user">'+icon('terminal',13)+' '+esc(CRON.user)+'</span>'
    + '<span class="co-cmd">'+esc(cmd)+'</span>'
    + '<span class="co-rc" style="display:none"></span>'
    + '<span class="co-copy" title="Copy output" style="display:none">'+icon('copy',13)+'</span>'
    + '<span class="co-x" title="Close">'+icon('x',14)+'</span></div>'
    + '<pre class="empty"><span class="spin"></span> Running…</pre></div>');
  $row.after($panel);
  $btn.prop('disabled', true);
  $.post('cron_run.php', {action:'run', repository:CRON.repo, command:cmd}, function(d){
    $btn.prop('disabled', false);
    var $pre = $panel.find('pre'), $rc = $panel.find('.co-rc');
    if(!d || !d.ok){ $pre.removeClass('empty').text((d&&d.error)||'Run failed.'); $rc.show().removeClass('ok').addClass('bad').text('error'); return; }
    if(d.user) $panel.find('.co-user').html(icon('terminal',13)+' '+esc(d.user));
    var out = (d.output||'');
    if(out.replace(/\s+/g,'')===''){ $pre.addClass('empty').text(d.timedout?'(timed out after 120s — no output)':'(no output)'); }
    else { $pre.removeClass('empty').text(out); $panel.find('.co-copy').data('out', out).show(); }
    var rc = d.rc||0;
    $rc.show().removeClass('ok bad').addClass((rc===0&&!d.timedout)?'ok':'bad').text(d.timedout?'timed out':('exit '+rc));
  }, 'json').fail(function(){ $btn.prop('disabled', false); $panel.find('pre').removeClass('empty').text('Request failed.'); });
}

// ---- error log rendering (grouped) ----
var LOG = { txt:'', mode:'grouped' };
function logBase(p){ var i=p.lastIndexOf('/'); return i>=0?p.slice(i+1):p; }
function logSevClass(s){ s=(s||'').toLowerCase();
  if(s.indexOf('fatal')===0) return 'fatal'; if(s.indexOf('parse')===0) return 'parse';
  if(s.indexOf('warning')===0) return 'warning'; if(s.indexOf('notice')===0) return 'notice';
  if(s.indexOf('deprecated')===0) return 'deprecated'; if(s.indexOf('strict')===0) return 'strict'; return 'notice'; }
function logSevWeight(s){ return ({fatal:4,parse:4,warning:2,notice:1,deprecated:0,strict:0})[logSevClass(s)]||1; }
function logApacheTime(tm){ var m=(tm||'').match(/^\w+\s+(\w+)\s+(\d+)\s+(\d+):(\d+)/); return m?(m[2]+' '+m[1]+' '+m[3]+':'+m[4]):''; }
var LOG_MONTHS={Jan:0,Feb:1,Mar:2,Apr:3,May:4,Jun:5,Jul:6,Aug:7,Sep:8,Oct:9,Nov:10,Dec:11};
// Parse an apache-style "[Sun Jul 05 18:18:15.28 2026]" prefix into epoch ms (0 if unparseable).
function logApacheEpoch(line){
  var m=String(line).match(/^\[\w+\s+(\w+)\s+(\d+)\s+(\d+):(\d+):(\d+)(?:\.\d+)?\s+(\d+)\]/);
  if(!m || !(m[1] in LOG_MONTHS)) return 0;
  var d=new Date(+m[6], LOG_MONTHS[m[1]], +m[2], +m[3], +m[4], +m[5]);
  var t=d.getTime();
  return isNaN(t)?0:t;
}
function logExtractPhp(line){
  var out=[], s=line.replace(/\\n/g,'\n').replace(/PHP message:\s*/g,'\n');
  s.split('\n').forEach(function(p){
    var m=p.match(/PHP (Notice|Warning|Fatal error|Parse error|Deprecated|Strict Standards):\s*(.*)$/);
    if(!m) return;
    var body=m[2].replace(/'\s*$/,'').trim();
    var fm=body.match(/^([\s\S]*?) in (\/\S+?) on line (\d+)/);
    if(fm){ out.push({sev:m[1], text:fm[1].trim(), file:fm[2], line:fm[3]}); return; }
    // Uncaught Error / exception form: "... in /path/file.php:300"
    var fc=body.match(/^([\s\S]*?) in (\/[^\s:]+):(\d+)/);
    if(fc){ out.push({sev:m[1], text:fc[1].trim(), file:fc[2], line:fc[3]}); return; }
    out.push({sev:m[1], text:body, file:'', line:''});
  });
  return out;
}
// The page/URL where the error occurred, parsed from the log line (nginx request:/host:, apache referer:).
function logExtractUrl(line){
  var rq = line.match(/request: "(?:[A-Z]+\s+)?([^" ]+?)(?:\s+HTTP[^"]*)?"/);
  if(rq){
    var p = rq[1];
    var hm = line.match(/(?:host|server):\s*"?([a-z0-9.\-]+)/i);
    if(p.charAt(0)==='/' && hm) return 'https://'+hm[1]+p;
    return p;
  }
  var ref = line.match(/referer:\s*(\S+)/i);
  if(ref){ var u=ref[1].replace(/[,'"]+$/,''); if(/^https?:\/\//.test(u)) return u; }
  return '';
}
function logUrlShort(u){ var m=String(u).match(/^https?:\/\/[^\/]+(\/.*)?$/); return (m && m[1]) ? m[1] : u; }
// The page/script that was executing, from a PHP stack trace (the frame before "{main}").
function logExtractScript(line){
  var s=String(line).replace(/\\n/g,'\n');
  var m=s.match(/#\d+\s+(\/\S+?)\((\d+)\):[^\n]*\n#\d+\s+\{main\}/);
  if(!m) m=s.match(/#\d+\s+(\/\S+?)\((\d+)\):/); // fallback: first frame
  return m ? {file:m[1], line:m[2]} : null;
}
function logUrlHtml(url){
  if(!url) return '';
  var clickable = /^https?:\/\//.test(url);
  var inner = icon('link',12)+'<span>'+esc(logUrlShort(url))+'</span>';
  return clickable
    ? '<a class="log-url" href="'+esc(url)+'" target="_blank" rel="noopener" title="'+esc(url)+'">'+inner+'</a>'
    : '<span class="log-url" title="'+esc(url)+'">'+inner+'</span>';
}
function logCopyText(g){
  var s = g.sev ? (g.sev+': '+(g.text||'')) : (g.text||'');
  if(g.file) s += ' in '+g.file+(g.line?':'+g.line:'');
  if(g.via && (!g.file || g.via.file!==g.file)) s += '\nPage: '+g.via.file+':'+g.via.line;
  if(g.url)  s += '\nURL: '+g.url;
  if(g.last) s += '\nLast: '+g.last;
  if(g.count>1) s += '\nCount: '+g.count;
  return s;
}
function logCopyHtml(g){
  return '<button class="log-copy" data-copy="'+esc(logCopyText(g))+'" title="Copy error">'+icon('copy',13)+'</button>';
}
function logHead(uniq,total){
  return '<div class="cron-head">'
    + (LOG.mode==='raw' ? '<span class="ct">Raw log</span>' : '<span class="ct">'+uniq+' unique · '+total+' total</span>')
    + '<span class="spacer"></span>'
    + '<button class="btn tiny" id="logCopyAll">'+icon('copy',13)+' Copy all</button>'
    + '<button class="btn tiny" id="logToggle">'+(LOG.mode==='raw'?'Grouped view':'Raw view')+'</button></div>';
}
// Parse raw error-log text into de-duplicated, severity-sorted groups (+ generic lines).
function parseLogGroups(txt){
  var lines=String(txt||'').replace(/\r/g,'').split('\n').filter(function(l){return l.trim()!=='';});
  var groups={}, order=[], generic={}, gorder=[], total=0;
  lines.forEach(function(line){
    var when=logApacheTime((line.match(/^\[([^\]]+)\]/)||[])[1]||'');
    var ts=logApacheEpoch(line);
    var url=logExtractUrl(line);
    var via=logExtractScript(line);
    var msgs=logExtractPhp(line);
    if(msgs.length){
      msgs.forEach(function(m){ total++;
        var key=m.sev+'|'+m.text+'|'+m.file+'|'+m.line;
        if(!groups[key]){ groups[key]={sev:m.sev,text:m.text,file:m.file,line:m.line,count:0,last:when,ts:ts,url:url,via:via}; order.push(key); }
        groups[key].count++; if(when) groups[key].last=when; if(ts>groups[key].ts) groups[key].ts=ts; if(url) groups[key].url=url; if(via) groups[key].via=via;
      });
    } else {
      var rest=line.replace(/^(\[[^\]]*\]\s*)+/,'').trim() || line.trim(); total++;
      if(!generic[rest]){ generic[rest]={text:rest,count:0,last:when,ts:ts,url:url}; gorder.push(rest); }
      generic[rest].count++; if(when) generic[rest].last=when; if(ts>generic[rest].ts) generic[rest].ts=ts; if(url) generic[rest].url=url;
    }
  });
  order.sort(function(a,b){ var w=logSevWeight(groups[b].sev)-logSevWeight(groups[a].sev); return w!==0?w:groups[b].count-groups[a].count; });
  return {groups:groups, order:order, generic:generic, gorder:gorder, total:total, uniq:order.length+gorder.length};
}
// Render parsed log groups into the grouped list markup (shared by single-site & group views).
// Severity/type category for a parsed group — drives the filter chips.
function logGroupCat(g){
  if(g.sev){
    var c=logSevClass(g.sev);
    if(c==='fatal'||c==='parse') return 'fatal';
    if(c==='warning') return 'warning';
    if(c==='notice') return 'notice';
    return 'deprecated'; // deprecated / strict
  }
  if(/database error|invalid sql|sql syntax|\bmysqli?\b|\bSQL\b/i.test(g.text||'')) return 'database';
  return 'other';
}
// Flatten parsed groups into display order with a category attached to each.
function logGroupsFlat(p){
  var arr=[];
  p.order.forEach(function(k){ var g=p.groups[k]; arr.push({g:g, cat:logGroupCat(g), kind:'php'}); });
  p.gorder.forEach(function(k){ var g=p.generic[k]; arr.push({g:g, cat:logGroupCat(g), kind:'generic'}); });
  return arr;
}
function logItemHtml(it){
  var g=it.g;
  if(it.kind==='php'){
    return '<div class="log-item"><div class="log-main"><div class="log-msg"><span class="logsev '+logSevClass(g.sev)+'">'+esc(g.sev)+'</span><span class="log-text">'+esc(g.text)+'</span></div>'
      + (g.file?'<button class="log-loc log-open mono" data-file="'+esc(g.file)+'" data-line="'+esc(g.line||'')+'" title="Open '+esc(g.file)+(g.line?' at line '+esc(g.line):'')+'">'+icon('file',12)+esc(logBase(g.file))+(g.line?':'+esc(g.line):'')+'</button>':'')
      + (g.via && (!g.file || g.via.file!==g.file) ? '<button class="log-loc log-open mono" data-file="'+esc(g.via.file)+'" data-line="'+esc(g.via.line)+'" title="Open page '+esc(g.via.file)+' at line '+esc(g.via.line)+'">'+icon('file',12)+'page: '+esc(logBase(g.via.file))+':'+esc(g.via.line)+'</button>' : '')
      + logUrlHtml(g.url)
      + '</div><div class="log-meta">'+logCopyHtml(g)+(g.count>1?'<span class="log-count">×'+g.count+'</span>':'')+(g.last?'<span class="log-when">'+esc(g.last)+'</span>':'')+'</div></div>';
  }
  return '<div class="log-item"><div class="log-main"><div class="log-msg"><span class="logsev '+it.cat+'">'+(it.cat==='database'?'DB':'error')+'</span><div class="log-text mono" style="font-weight:500;white-space:pre-wrap;word-break:break-word">'+esc(g.text)+'</div></div>'+logUrlHtml(g.url)+'</div>'
    + '<div class="log-meta">'+logCopyHtml(g)+(g.count>1?'<span class="log-count">×'+g.count+'</span>':'')+(g.last?'<span class="log-when">'+esc(g.last)+'</span>':'')+'</div></div>';
}
function logListFromFlat(arr){
  return '<div class="log-list">'+arr.map(logItemHtml).join('')+'</div>';
}
// True when a get_logs.php response is actual log content (not an empty/status/error message).
function logLooksReal(t){ t=(t||'').trim(); if(t==='') return false; return !/^(no critical errors|the (error )?log is empty|error log location|ERROR:|could not read)/i.test(t); }

// ---- log filters (severity + recency), shared by the single-site log and the group critical view ----
var LOG_CATS=[
  {key:'fatal',      label:'Fatal'},
  {key:'database',   label:'Database'},
  {key:'warning',    label:'Warning'},
  {key:'notice',     label:'Notice'},
  {key:'deprecated', label:'Deprecated'},
  {key:'other',      label:'Other'}
];
var LOG_WINDOWS=[ {min:0,label:'All time'}, {min:1440,label:'24h'}, {min:120,label:'2h'}, {min:30,label:'30m'} ];
var LOG_FILTER={}, LOG_WINDOW=0;   // LOG_FILTER: cat->bool (absent = shown); LOG_WINDOW: minutes, 0 = all
function logFilterReset(){ LOG_FILTER={}; LOG_WINDOW=0; }
function logCatOn(cat){ return LOG_FILTER[cat]!==false; }
// Keep only groups newer than the active window (groups with no timestamp are dropped when a window is set).
function logApplyWindow(flat){
  if(!LOG_WINDOW) return flat;
  var cutoff=(new Date()).getTime() - LOG_WINDOW*60000;
  return flat.filter(function(it){ return it.g.ts && it.g.ts>=cutoff; });
}
function logCatCounts(flat){ var c={}; flat.forEach(function(it){ c[it.cat]=(c[it.cat]||0)+1; }); return c; }
function logFilterBarHtml(counts){
  var chips=LOG_CATS.filter(function(c){ return counts[c.key]; }).map(function(c){
    return '<button class="logf-chip logsev '+c.key+(logCatOn(c.key)?' on':' off')+'" data-logf="'+c.key+'">'+esc(c.label)+'<span class="logf-n">'+counts[c.key]+'</span></button>';
  }).join('');
  var wins=LOG_WINDOWS.map(function(w){
    return '<button class="logw-btn'+(LOG_WINDOW===w.min?' on':'')+'" data-logw="'+w.min+'">'+esc(w.label)+'</button>';
  }).join('');
  return '<div class="logf-bar"><div class="logf-sevs">'+(chips||'<span class="logf-none">no matching severities</span>')+'</div>'
    + '<div class="logw-seg">'+wins+'</div></div>';
}
function renderLog(){
  var txt=LOG.txt||'', trimmed=txt.trim();
  if(trimmed===''){ $('#infoBody').html('<div class="svn-modal-message">The log is empty.</div>'); return; }
  if(trimmed.charAt(0)!=='[' && trimmed.length<200 && !/PHP (Notice|Warning|Fatal|Parse|Deprecated)/.test(trimmed)){
    $('#infoBody').html('<div class="svn-modal-message">'+esc(trimmed)+'</div>'); return;
  }
  if(LOG.mode==='raw'){ $('#infoBody').html(logHead(0,0)+'<div class="info-pre">'+esc(txt)+'</div>'); return; }
  var p=parseLogGroups(txt);
  var flat=logGroupsFlat(p);
  var windowed=logApplyWindow(flat);
  var counts=logCatCounts(windowed);
  var shown=windowed.filter(function(it){ return logCatOn(it.cat); });
  LOG._shown = shown;   // stash for "Copy all"
  var body = shown.length ? logListFromFlat(shown)
    : '<div class="svn-modal-message">No entries match the current filters.</div>';
  $('#infoBody').html(logHead(p.uniq, p.total)+logFilterBarHtml(counts)+body);
}

// ---- group critical errors (aggregate across every site in the active scope) ----
var GCRIT=null;
// Classify a get_logs.php (critical-mode) response for one site.
function critClassify(txt){
  var t=String(txt||'').trim();
  if(t==='') return {state:'clean'};
  if(/^(no critical errors|the (error )?log is empty|error log location not configured)/i.test(t)) return {state:'clean'};
  if(/^ERROR:|^could not read/i.test(t)) return {state:'error', msg:t};
  var p=parseLogGroups(t);
  if(p.uniq===0) return {state:'clean'};
  return {state:'errors', parsed:p, flat:logGroupsFlat(p), count:p.uniq, total:p.total};
}
function renderGroupCritical(){
  if(!GCRIT) return;
  var $b=$('#gcritBody'); if(!$b.length) return;
  var res=GCRIT.results, withErr=[], clean=[], errored=[], loading=[];
  GCRIT.repos.forEach(function(r){ var x=res[r]||{state:'loading'};
    if(x.state==='errors') withErr.push(r);
    else if(x.state==='clean') clean.push(r);
    else if(x.state==='error') errored.push(r);
    else loading.push(r);
  });
  withErr.sort(function(a,b){ return (res[b].count||0)-(res[a].count||0); });
  var scanning=GCRIT.done<GCRIT.total;
  // Aggregate counts (after the recency window) across every site with errors -> drives the filter chips.
  var counts={}, siteShown={};
  withErr.forEach(function(r){ var w=logApplyWindow(res[r].flat); var c=logCatCounts(w); for(var k in c){ counts[k]=(counts[k]||0)+c[k]; }
    siteShown[r]=w.filter(function(it){ return logCatOn(it.cat); }); });
  var visSites=withErr.filter(function(r){ return siteShown[r].length; });
  var totalShown=0; visSites.forEach(function(r){ totalShown+=siteShown[r].length; });
  // Stash what's currently visible so "Copy all" copies exactly the filtered list on screen.
  GCRIT._copy = visSites.map(function(r){ return {repo:r, items:siteShown[r]}; });
  var html='<div class="cron-head">'
    + '<span class="ct">'+(scanning?('<span class="spin"></span> Scanning '+GCRIT.done+'/'+GCRIT.total+' sites'):(GCRIT.total+' site'+(GCRIT.total!==1?'s':'')+' checked'))+'</span>'
    + '<span class="spacer"></span>'
    + (visSites.length?'<button class="btn tiny" id="gcritCopyAll">'+icon('copy',13)+' Copy all ('+totalShown+')</button>':'')
    + '<span class="ct">'+(withErr.length?('<b style="color:var(--warn)">'+withErr.length+'</b> with critical errors'):(scanning?'':'<span style="color:var(--ok)">no critical errors</span>'))+'</span></div>';
  if(withErr.length) html+=logFilterBarHtml(counts);
  visSites.forEach(function(r){ var x=res[r], shown=siteShown[r];
    html+='<div class="gcrit-site"><div class="gcrit-site-head"><span class="gcrit-dot warn"></span>'
      + '<span class="gcrit-name mono">'+esc(r)+'</span>'
      + '<span class="gcrit-badge warn">'+shown.length+' shown'+(x.count>shown.length?' / '+x.count:'')+'</span>'
      + '<button class="btn tiny ghost gcrit-open" data-crit-repo="'+esc(r)+'">Open site log</button></div>'
      + logListFromFlat(shown) + '</div>';
  });
  if(withErr.length && !visSites.length) html+='<div class="gcrit-line muted"><span>No errors match the current filters.</span></div>';
  if(errored.length){ html+='<div class="gcrit-line err">'+icon('alert',14)+'<span>Could not read: '+errored.map(function(r){return esc(r);}).join(', ')+'</span></div>'; }
  if(loading.length){ html+='<div class="gcrit-line muted"><span class="spin"></span><span>Checking '+loading.length+' more site'+(loading.length!==1?'s':'')+'…</span></div>'; }
  if(clean.length){ html+='<div class="gcrit-line ok">'+icon('check',14)+'<span>'+clean.length+' site'+(clean.length!==1?'s':'')+' with no critical errors</span></div>'; }
  $b.html(html);
}
// Assemble the whole visible critical list (all sites, current filters) as plain text for the clipboard.
function copyGroupCriticalText(){
  if(!GCRIT || !GCRIT._copy || !GCRIT._copy.length) return '';
  return GCRIT._copy.map(function(s){
    return '### ' + s.repo + ' (' + s.items.length + ') ###\n' + s.items.map(function(it){ return logCopyText(it.g); }).join('\n\n');
  }).join('\n\n');
}
function openGroupCritical(fromHash){
  var ag=STATE.activeGroup;
  if(ag && ag.indexOf('__one:')===0){ openInfo('critical', ag.slice(6)); return; }   // single site -> existing view
  var repos=scopedRepos();
  if(!repos.length){ alert('Choose a group (or a scope with sites) first.'); return; }
  if(!fromHash && repos.length>30 && !confirm('This scope has '+repos.length+' sites. Reading critical errors for all of them can take a while. Continue?')) return;
  BK_OPEN_REPO=null; INFO_OPEN=null; DC_OPEN_REPO=null; if(typeof dcStopImgPoll==='function'){ dcStopImgPoll(); dcStopPoll(); }
  logFilterReset();
  // Persist in the hash only for saved groups (numeric id) — not the heavy "all sites" scope.
  if(/^\d+$/.test(String(ag))){
    var nh=scopeToHash(ag)+'~crit';
    if(((location.hash||'').replace(/^#/,''))!==nh){ location.hash=nh; }
  }
  modal('<div class="modal wide"><div class="modal-head"><div class="mh-ico">'+icon('alert',21)+'</div>'
    + '<div style="min-width:0"><h3>Critical errors</h3><p>Fatal / parse / critical errors across <span class="mono">'+esc(activeScopeName())+'</span></p></div>'
    + '<button class="mh-x" data-close-modal="1">'+icon('x',17)+'</button></div>'
    + '<div class="modal-body"><div id="gcritBody"><span class="spin"></span> Loading…</div></div>'
    + '<div class="modal-foot"><div class="mf-grow"></div><button class="btn ghost" id="gcritReload">Re-scan</button><button class="btn solid" data-close-modal="1">Close</button></div></div>');
  GCRIT={scope:String(ag), repos:repos.slice(), results:{}, done:0, total:repos.length};
  renderGroupCritical();
  var idx=0, running=0, CONC=4;
  (function pump(){
    while(running<CONC && idx<GCRIT.repos.length){
      var repo=GCRIT.repos[idx++]; running++;
      GCRIT.results[repo]={state:'loading'};
      (function(repo){
        $.post('get_logs.php', {repository:repo}, function(txt){ if(GCRIT) GCRIT.results[repo]=critClassify(txt); })
          .fail(function(){ if(GCRIT) GCRIT.results[repo]={state:'error', msg:'Request failed.'}; })
          .always(function(){ if(!GCRIT) return; running--; GCRIT.done++; renderGroupCritical(); pump(); });
      })(repo);
    }
  })();
}

function closeSource(){ $('#sourceHost').empty(); }
function openSource(file, line, repo){
  line = line||0;
  // Rendered in its own host layered above the log modal, so closing it returns to the log.
  $('#sourceHost').html('<div class="scrim scrim3" data-source-scrim="1"><div class="modal wide"><div class="modal-head"><div class="mh-ico">'+icon('file',21)+'</div>'
    + '<div style="min-width:0"><h3 style="word-break:break-all">'+esc(file.split('/').pop())+(line?'  :'+line:'')+'</h3><p class="mono" style="font-size:12px">'+esc(file)+'</p></div>'
    + '<button class="mh-x" data-close-source="1">'+icon('x',17)+'</button></div>'
    + '<div class="modal-body"><div id="srcBody"><span class="spin"></span> Loading…</div></div>'
    + '<div class="modal-foot"><div class="mf-grow" id="srcFoot"></div><button class="btn solid" data-close-source="1">Close</button></div></div></div>');
  // From the SVN update list `file` is a repo-relative path (+repo); view_source.php resolves
  // it to the working copy on the right host. Elsewhere (logs/history) `file` is absolute.
  $.post('view_source.php', repo ? {repository:repo, file:file, line:line} : {file:file, line:line}, function(d){
    if(d&&d.ok){
      var rows=String(d.content).split('\n'), start=d.start||1, target=d.line||0, html='<div class="codewrap" id="codewrap">';
      rows.forEach(function(t,idx){ var ln=start+idx; html+='<div class="codeline'+(ln===target?' hl':'')+'" data-ln="'+ln+'"><span class="ln">'+ln+'</span><span class="lc">'+esc(t)+'</span></div>'; });
      html+='</div>';
      $('#srcBody').html(html);
      $('#srcFoot').text('Lines '+start+'–'+(start+rows.length-1)+' of '+d.total);
      var el=document.querySelector('#codewrap .codeline.hl'), w=document.getElementById('codewrap');
      if(el&&w){ w.scrollTop = Math.max(0, el.offsetTop - w.clientHeight/2 + el.offsetHeight); }
    } else { $('#srcBody').html('<div class="svn-modal-message svn-modal-message--warn">'+esc((d&&d.error)||'Could not open file.')+'</div>'); }
  }, 'json').fail(function(){ $('#srcBody').html('<div class="svn-modal-message svn-modal-message--warn">Request failed.</div>'); });
}

// ---- history rendering ----
var HIST = { repo:'', mode:'', rows:[] };
function histActionClass(a){ a=(a||'').toUpperCase(); return a==='A'?'to-add':a==='D'?'to-delete':a==='R'?'replaced':a==='M'?'modified':'default'; }
function histFilesHtml(files){
  return (files||[]).map(function(f){
    var abs='/home/vhosts/'+HIST.repo+'/'+f.path, clickable=(f.action||'').toUpperCase()!=='D';
    return '<div class="hist-file"><span class="fstat '+histActionClass(f.action)+'">'+esc((f.action||'?').charAt(0))+'</span>'
      + (clickable?'<button class="hist-file-open mono" data-file="'+esc(abs)+'" title="Open '+esc(abs)+'">'+esc(f.path)+'</button>'
                  :'<span class="mono hist-file-del">'+esc(f.path)+'</span>')
      + '</div>';
  }).join('');
}
function renderHistory(data, repo){
  HIST = { repo:repo, mode:data.mode, rows:data.rows||[] };
  if(!HIST.rows.length){ $('#infoBody').html('<div class="svn-modal-message">No history for '+esc(repo)+'.</div>'); return; }
  var commits = data.mode==='commits';
  var html = '<div class="cron-head"><span class="ct">'+HIST.rows.length+(commits?' recent commit'+(HIST.rows.length!==1?'s':''):' deploy'+(HIST.rows.length!==1?'s':''))+'</span>'
    + (commits?'':'<span class="ct" style="color:var(--muted-2)"> · deploy history</span>')+'</div><div class="hist-list">';
  HIST.rows.forEach(function(r,idx){
    var deployed = r.deployed_by
      ? '<span class="hist-deploy" title="'+esc(r.deployed_at)+'">'+icon('up',12)+'Deployed by '+esc(r.deployed_by)+(r.deployed_ago?' · '+esc(r.deployed_ago):'')+'</span>'
      : (commits?'<span class="hist-undeployed">Not recorded as deployed</span>':'');
    html += '<div class="hist-item">'
      + '<div class="hist-rev">'+(r.revision?'<span class="hist-rev-badge mono">r'+esc(r.revision)+'</span>':'')+'<span class="hist-ago" title="'+esc(r.date_display)+'">'+esc(r.ago||r.date_display)+'</span></div>'
      + '<div class="hist-main">'
        + (r.message?'<div class="hist-msg">'+esc(r.message)+'</div>':'<div class="hist-msg hist-nomsg">(no commit message)</div>')
        + '<div class="hist-meta">'+(r.author?'<span class="hist-by">'+icon('user',12)+esc(r.author)+'</span>':'')+deployed+'</div>'
        + (r.file_count?'<button class="hist-files-toggle" data-hi="'+idx+'">'+icon('file',12)+r.file_count+' file'+(r.file_count!==1?'s':'')+' changed</button><div class="hist-files" id="histFiles'+idx+'" style="display:none"></div>':'')
      + '</div>'
      + '<div class="hist-actions">'+(commits&&r.revision?'<button class="btn tiny hist-diff" data-rev="'+esc(r.revision)+'">View diff</button>':'')
        +(r.revision?'<button class="btn tiny ghost hist-revert" data-rev="'+esc(r.revision)+'" title="Roll the live site back to r'+esc(r.revision)+'">'+icon('undo',13)+'Revert to r'+esc(r.revision)+'</button>':'')+'</div>'
    + '</div>';
  });
  $('#infoBody').html(html+'</div>');
}
// Roll the live working copy back to a specific revision (svn update -r REV as the site user).
function doRevert(repo, rev){
  if(!repo || !rev) return;
  uiConfirm({icon:'undo', danger:true, title:'Revert '+repo+' to r'+rev+'?', confirmLabel:'Revert to r'+rev,
    bodyHtml:'Runs <span class="mono">svn update -r '+esc(rev)+'</span> on the <b>live</b> working copy — the site’s files roll back to revision '+esc(rev)+'. Newer commits stay in SVN and can be re-deployed afterwards.'},
    function(){
      var $btns=$('.hist-revert[data-rev="'+rev+'"]'); $btns.prop('disabled',true);
      $('#sourceHost').html('<div class="scrim scrim3" data-source-scrim="1"><div class="modal wide"><div class="modal-head"><div class="mh-ico">'+icon('undo',21)+'</div>'
        + '<div style="min-width:0"><h3>Revert to r'+esc(rev)+'</h3><p class="mono" style="font-size:12px">'+esc(repo)+'</p></div>'
        + '<button class="mh-x" data-close-source="1">'+icon('x',17)+'</button></div>'
        + '<div class="modal-body"><div id="revBody"><span class="spin"></span> Running svn update -r '+esc(rev)+' on the live site…</div></div>'
        + '<div class="modal-foot"><div class="mf-grow" id="revFoot"></div><button class="btn solid" data-close-source="1">Close</button></div></div></div>');
      $.post('revert_repository.php', {repository:repo, revision:rev}, function(d){
        $btns.prop('disabled',false);
        var msg = (d&&d.ok)
          ? '<div class="svn-modal-message svn-modal-message--ok">Reverted — live site now at r'+esc(d.updatedTo||rev)+'.</div>'
          : '<div class="svn-modal-message svn-modal-message--warn">'+esc((d&&d.error)||'Revert failed.')+'</div>';
        var out = (d&&d.output) ? '<div class="info-pre mono" style="white-space:pre-wrap;word-break:break-word;margin-top:10px">'+esc(d.output)+'</div>' : '';
        $('#revBody').html(msg+out);
        if(d&&d.ok){ $('#revFoot').text('Deploy log updated'); $.post('history.php', {repository:repo}, function(h){ if(h&&h.ok) renderHistory(h, repo); }, 'json'); }
      }, 'json').fail(function(){ $btns.prop('disabled',false); $('#revBody').html('<div class="svn-modal-message svn-modal-message--warn">Request failed.</div>'); });
    });
}

function openInfo(kind, repo){
  if(!repo){ alert('Pick a site first (use a site’s ⋯ menu, or select a single site).'); return; }
  INFO_OPEN = { kind: kind, repo: repo }; BK_OPEN_REPO = null; DC_OPEN_REPO = null; GCRIT = null; if(typeof dcStopImgPoll==='function'){ dcStopImgPoll(); dcStopPoll(); }
  if(kind==='log' || kind==='critical') logFilterReset();
  // Reflect the open info modal in the URL so a refresh reopens it.
  var nh = scopeToHash(STATE.activeGroup) + '~info=' + kind + ':' + repo;
  if(((location.hash||'').replace(/^#/,'')) !== nh){ location.hash = nh; }
  var titles = { history:['History','Recent commits & deploys'], log:['Error log','Recent errors + latest critical from error.log'], critical:['Critical errors','Critical errors in error.log'], cron:['Cron jobs','Cron jobs for this repository'], mail:['Mail log','Recent mail sent from this domain'] };
  var t = titles[kind];
  modal('<div class="modal wide"><div class="modal-head"><div class="mh-ico">'+icon(kind==='cron'?'calendar':(kind==='history'?'history':(kind==='mail'?'mail':'file')),21)+'</div>'
    + '<div style="min-width:0"><h3>'+t[0]+'</h3><p>'+t[1]+' · <span class="mono">'+esc(repo)+'</span></p></div>'
    + '<button class="mh-x" data-close-modal="1">'+icon('x',17)+'</button></div>'
    + '<div class="modal-body"><div id="infoBody"><span class="spin"></span> Loading…</div></div>'
    + '<div class="modal-foot"><div class="mf-grow"></div><button class="btn solid" data-close-modal="1">Close</button></div></div>');
  if(kind==='history'){
    $.post('history.php', {repository:repo}, function(d){
      if(d&&d.ok){ renderHistory(d, repo); }
      else { $('#infoBody').html('<div class="svn-modal-message svn-modal-message--warn">'+esc((d&&d.error)||'Could not load history.')+'</div>'); }
    }, 'json');
  } else if(kind==='cron'){
    $.post('cron_manage.php', {action:'get', repository:repo}, function(d){
      if(d&&d.ok){ CRON={repo:repo, user:d.user||'', lines:cronParse(d.crontab||'')}; renderCronEditor(); }
      else { $('#infoBody').html('<div class="svn-modal-message svn-modal-message--warn">'+esc((d&&d.error)||'Could not read crontab.')+'</div>'); }
    }, 'json');
  } else if(kind==='log'){
    // The plain error log is a short recent tail (the gateway caps the payload at ~16KB), so a fatal
    // from even 30 min ago can be pushed out by per-request noise. Also pull the critical grep (fatals
    // across the whole log) and merge, so recent fatals always show. The grouped parser de-dups overlap.
    var lg={};
    function logMerge(){
      if(lg.tail===undefined || lg.crit===undefined) return;
      var real=[]; if(logLooksReal(lg.crit)) real.push(lg.crit); if(logLooksReal(lg.tail)) real.push(lg.tail);
      LOG={txt: real.length ? real.join('\n') : (lg.tail||lg.crit||''), mode:'grouped'}; renderLog();
    }
    $.post('get_logs.php', {repository:repo, last_50_errors:1}, function(t){ lg.tail=t||''; logMerge(); }).fail(function(){ lg.tail=''; logMerge(); });
    $.post('get_logs.php', {repository:repo}, function(t){ lg.crit=t||''; logMerge(); }).fail(function(){ lg.crit=''; logMerge(); });
  } else if(kind==='critical'){
    $.post('get_logs.php', {repository:repo}, function(txt){ LOG={txt:txt,mode:'grouped'}; renderLog(); });
  } else if(kind==='mail'){
    $.post('mail_log.php', {repository:repo, limit:100}, function(d){
      if(d&&d.ok){ renderMailLog(d, repo); }
      else { $('#infoBody').html('<div class="svn-modal-message svn-modal-message--warn">'+esc((d&&d.error)||'Could not read the mail log.')+'</div>'); }
    }, 'json').fail(function(){ $('#infoBody').html('<div class="svn-modal-message svn-modal-message--warn">Request failed.</div>'); });
  }
}
// ---- mail log rendering ----
var MAIL_REPO='';
function mailStatusClass(s){ s=(s||'').toLowerCase(); if(s==='sent') return 'ok'; if(s==='bounced'||s==='expired') return 'err'; if(s==='deferred') return 'warn'; return 'muted'; }
function renderMailLog(d, repo){
  MAIL_REPO=repo;
  if(d.note){ $('#infoBody').html('<div class="svn-modal-message">'+esc(d.note)+'</div>'); return; }
  var rows=d.rows||[];
  if(!rows.length){ $('#infoBody').html('<div class="svn-modal-message">No mail found for '+esc(repo)+' in the current mail log.</div>'); return; }
  var src = (d.source && d.source!=='web1') ? (' · <span style="color:var(--muted-2)">via '+esc(d.source)+'</span>') : '';
  var head='<div class="cron-head"><span class="ct">'+rows.length+' recent deliver'+(rows.length!==1?'ies':'y')+' from <span class="mono">'+esc(repo)+'</span>'+src+'</span><span class="spacer"></span><span class="ct" style="color:var(--muted-2)">click a row for details</span></div>';
  var body=rows.map(function(r){
    return '<button type="button" class="mail-row" data-mailqid="'+esc(r.qid)+'" title="View log entry ('+esc(r.qid)+')"><span class="mail-when">'+esc(r.time)+'</span>'
      + '<span class="mail-addr mail-to">'+icon('chevronR',12)+esc(r.to)+'</span>'
      + '<span class="mail-from mono" title="from '+esc(r.from)+'">'+esc(r.from)+'</span>'
      + '<span class="mail-status '+mailStatusClass(r.status)+'">'+esc(r.status)+'</span></button>';
  }).join('');
  $('#infoBody').html(head+'<div class="mail-list">'+body+'</div>');
}
// ---- single mail entry (parsed + raw), layered above the list ----
var MAIL_ENTRY={raw:'', mode:'parsed'};
function mailParseEntry(raw){
  var lines=String(raw||'').split('\n').filter(function(l){return l.trim()!=='';});
  var info={from:'',size:'',nrcpt:'',msgid:'',dkim:'',client:'',uid:'',first:'',last:'',removed:false,recips:[]};
  lines.forEach(function(l){
    var ts=(l.match(/^(\w{3}\s+\d+\s+[\d:]+)/)||[])[1]||'';
    if(ts){ if(!info.first) info.first=ts; info.last=ts; }
    var m;
    if(m=l.match(/\bfrom=<([^>]*)>/)) info.from=m[1];
    if(m=l.match(/\bsize=(\d+)/)) info.size=m[1];
    if(m=l.match(/\bnrcpt=(\d+)/)) info.nrcpt=m[1];
    if(m=l.match(/message-id=<([^>]*)>/)) info.msgid=m[1];
    if(m=l.match(/DKIM-Signature.*?\bd=([A-Za-z0-9.\-]+)/)) info.dkim=m[1];
    if(m=l.match(/\bclient=([^,\s]+)/)) info.client=m[1];
    if(m=l.match(/\buid=(\d+)/)) info.uid=m[1];
    if(/:\s*removed\s*$/.test(l)) info.removed=true;
    if(/\bto=<[^>]*>/.test(l)){
      info.recips.push({
        to:(l.match(/\bto=<([^>]*)>/)||[])[1]||'',
        relay:(l.match(/\brelay=([^,]+)/)||[])[1]||'',
        delay:(l.match(/\bdelay=([^,]+)/)||[])[1]||'',
        dsn:(l.match(/\bdsn=([^,]+)/)||[])[1]||'',
        status:(l.match(/\bstatus=(\w+)/)||[])[1]||'',
        detail:(l.match(/status=\w+\s+\(([\s\S]*)\)\s*$/)||[])[1]||'',
        time:ts
      });
    }
  });
  return info;
}
function mailEntryBody(){
  if(MAIL_ENTRY.mode==='raw'){
    return '<div class="info-pre mono" style="white-space:pre-wrap;word-break:break-word">'+esc(MAIL_ENTRY.raw||'(no log lines)')+'</div>';
  }
  var i=mailParseEntry(MAIL_ENTRY.raw);
  function field(lbl,val,mono){ if(!val) return ''; return '<div class="me-field"><span class="me-lbl">'+lbl+'</span><span class="me-val'+(mono?' mono':'')+'">'+esc(val)+'</span></div>'; }
  var when = i.first + (i.last && i.last!==i.first ? ' → '+i.last : '');
  var meta = '<div class="me-grid">'
    + field('Sent', when, false)
    + field('From', i.from, true)
    + field('Size', i.size?(Math.round(i.size/1024*10)/10+' KB ('+i.size+' bytes)'):'', false)
    + field('Recipients', i.nrcpt, false)
    + field('DKIM domain', i.dkim, true)
    + field('Origin', i.client, true)
    + field('Message-ID', i.msgid, true)
    + '</div>';
  var recs = i.recips.length ? ('<div class="me-sub">Deliveries</div><div class="me-recips">'+i.recips.map(function(r){
      return '<div class="me-recip"><span class="mail-status '+mailStatusClass(r.status)+'">'+esc(r.status||'?')+'</span>'
        + '<span class="me-to">'+esc(r.to)+'</span>'
        + '<div class="me-rmeta">'+(r.relay?'<span title="relay">'+esc(r.relay)+'</span>':'')+(r.dsn?'<span>dsn '+esc(r.dsn)+'</span>':'')+(r.delay?'<span>'+esc(r.delay)+'s</span>':'')+'</div>'
        + (r.detail?'<div class="me-detail mono">'+esc(r.detail)+'</div>':'')+'</div>';
    }).join('')+'</div>') : '';
  return meta + recs;
}
function renderMailEntry(){
  $('#meBody').html(mailEntryBody());
  $('#meToggle').text(MAIL_ENTRY.mode==='raw'?'Parsed view':'Raw view');
}
function openMailEntry(repo, qid){
  MAIL_ENTRY={raw:'', mode:'parsed'};
  $('#sourceHost').html('<div class="scrim scrim3" data-source-scrim="1"><div class="modal wide"><div class="modal-head"><div class="mh-ico">'+icon('mail',21)+'</div>'
    + '<div style="min-width:0"><h3>Mail entry</h3><p class="mono" style="font-size:12px">'+esc(repo)+' · '+esc(qid)+'</p></div>'
    + '<button class="mh-x" data-close-source="1">'+icon('x',17)+'</button></div>'
    + '<div class="modal-body"><div class="cron-head"><span class="ct">postfix queue-id <span class="mono">'+esc(qid)+'</span></span><span class="spacer"></span>'
    +   '<button class="btn tiny" id="meToggle">Raw view</button><button class="btn tiny" id="meCopy">'+icon('copy',13)+' Copy raw</button></div>'
    + '<div id="meBody"><span class="spin"></span> Loading…</div></div>'
    + '<div class="modal-foot"><div class="mf-grow"></div><button class="btn solid" data-close-source="1">Close</button></div></div></div>');
  $.post('mail_log.php', {repository:repo, action:'raw', qid:qid}, function(d){
    if(d&&d.ok){ MAIL_ENTRY.raw=d.raw||''; renderMailEntry(); }
    else { $('#meBody').html('<div class="svn-modal-message svn-modal-message--warn">'+esc((d&&d.error)||'Could not read the mail entry.')+'</div>'); }
  }, 'json').fail(function(){ $('#meBody').html('<div class="svn-modal-message svn-modal-message--warn">Request failed.</div>'); });
}
function openDevTools(repo){
  if(!repo){ alert('Pick a site first.'); return; }
  modal('<div class="modal"><div class="modal-head"><div class="mh-ico">'+icon('tools',21)+'</div>'
    + '<div><h3>Dev tools</h3><p>Download recent database &amp; images from hosting to the dev server · <span class="mono">'+esc(repo)+'</span></p></div>'
    + '<button class="mh-x" data-close-modal="1">'+icon('x',17)+'</button></div>'
    + '<div class="modal-body"><p style="color:var(--muted);font-size:13px;margin:0 0 14px">Note: make an SVN checkout to the dev server before downloading.</p>'
    + '<div class="checkbox-group"><input type="checkbox" id="dlDB" checked><label for="dlDB">Download project database (<span id="dlDBSize">…</span>)</label></div>'
    + '<div class="checkbox-group"><input type="checkbox" id="dlImg" checked><label for="dlImg">Download images folder (<span id="dlImgSize">…</span>)</label></div>'
    + '<div class="alert" id="dlAlert"></div></div>'
    + '<div class="modal-foot"><div class="mf-grow"></div><button class="btn solid" id="dlStart" data-repo="'+esc(repo)+'">Start download</button></div></div>');
  $.post('hosting_get_sizes.php', {project:repo}, function(xml){
    try{ var r=JSON.parse(xml); $('#dlDBSize').text(r.db_size||'N/A'); $('#dlImgSize').text(r.images_size||'N/A'); }
    catch(e){ $('#dlDBSize').text('N/A'); $('#dlImgSize').text('N/A'); }
  });
}
// Dev copy: set up a full working copy of a site on the dev server (slayer) under the
// developer's own account — files (svn checkout), database (from nightly backup) and images.
var DC_JOB=null, DC_POLL=null, DC_OPEN_REPO=null, DC_IMG_POLL=null, DC_IMG_SEEN=false, DC_IMG_GRACE=0;
function dcStopPoll(){ if(DC_POLL){ clearInterval(DC_POLL); DC_POLL=null; } }
function dcStopImgPoll(){ if(DC_IMG_POLL){ clearInterval(DC_IMG_POLL); DC_IMG_POLL=null; } }
function dcBytes(n){ n=+n||0; if(n>=1073741824) return (n/1073741824).toFixed(1)+' GB'; if(n>=1048576) return (n/1048576).toFixed(0)+' MB'; if(n>=1024) return (n/1024).toFixed(0)+' KB'; return n+' B'; }
// Disable the "Copy images" box (and uncheck it) while a copy is running, so we never kick off a 2nd.
function dcSetImgRunning(running){
  var $c=$('#dcImg'); if(!$c.length) return;
  if(running){ $c.prop('checked',false).prop('disabled',true); $('#dcImgNote').text(' — already copying'); }
  else { $c.prop('disabled',false); $('#dcImgNote').text(''); }
}
// fresh=true when we just kicked off a copy: give dsid a grace window to spawn its rsync before
// we'd conclude "not running" and stop watching.
function dcStartImgPoll(repo, fresh){
  dcStopImgPoll(); DC_IMG_SEEN=false; DC_IMG_GRACE = fresh ? (Date.now()+35000) : 0;
  dciPoll(repo); DC_IMG_POLL=setInterval(function(){ dciPoll(repo); }, 4000);
}
var DC_IMG_BUSY=false;
function dciPoll(repo){
  if(DC_OPEN_REPO!==repo){ dcStopImgPoll(); return; }
  if(DC_IMG_BUSY) return; // a measurement (du on slayer) is still in flight — don't stack
  DC_IMG_BUSY=true;
  $.post('dev_copy_images.php', {repository:repo}, function(d){
    DC_IMG_BUSY=false;
    if(!d || !d.ok || DC_OPEN_REPO!==repo) return;
    var $w=$('#dcImgWrap'), pct=(d.pct!=null?d.pct:null);
    if(d.running){
      DC_IMG_SEEN=true; $w.show(); dcSetImgRunning(true);
      $('#dcImgFill').toggleClass('indet', pct==null).css('width', (pct!=null?pct:0)+'%');
      $('#dcImgPct').text(pct!=null ? pct+'%' : 'copying…');
      $('#dcImgSub').text(dcBytes(d.dest_bytes>0?d.dest_bytes:0)+(d.src_bytes>0?(' of '+dcBytes(d.src_bytes)):'')+' · copying…');
    } else {
      dcSetImgRunning(false);
      if(DC_IMG_SEEN || (pct!=null && pct>=99 && d.dest_bytes>0)){
        // finished
        $w.show(); $('#dcImgFill').removeClass('indet').css('width','100%');
        $('#dcImgPct').text('100%'); $('#dcImgSub').text('Images copied · '+dcBytes(d.dest_bytes));
        dcStopImgPoll();
      } else if(DC_IMG_GRACE && Date.now()<DC_IMG_GRACE){
        // just started — waiting for the rsync to appear
        $w.show(); $('#dcImgFill').addClass('indet'); $('#dcImgPct').text('starting…'); $('#dcImgSub').text('Requesting image copy…');
      } else {
        $w.hide(); dcStopImgPoll();
      }
    }
  }, 'json').fail(function(){ DC_IMG_BUSY=false; });
}
function openDevCopy(repo){
  if(!repo){ alert('Pick a site first (use a site’s ⋯ menu, or select a single site).'); return; }
  dcStopPoll(); dcStopImgPoll(); DC_JOB=null;
  DC_OPEN_REPO = repo; BK_OPEN_REPO = null; INFO_OPEN = null;
  // Persist the open popup in the URL so it survives a page refresh.
  var nh = scopeToHash(STATE.activeGroup) + '~dc=' + encodeURIComponent(repo);
  if(((location.hash||'').replace(/^#/,'')) !== nh){ location.hash = nh; }
  modal('<div class="modal"><div class="modal-head"><div class="mh-ico">'+icon('copy',21)+'</div>'
    + '<div style="min-width:0"><h3>Dev copy</h3><p>Set up a dev copy on slayer under your account · <span class="mono">'+esc(repo)+'</span></p></div>'
    + '<button class="mh-x" data-close-modal="1">'+icon('x',17)+'</button></div>'
    + '<div class="modal-body">'
    + '<p style="color:var(--muted);font-size:13px;margin:0 0 14px">Creates <span class="mono">~/projects/'+esc(repo)+'</span> on slayer, served at your <span class="mono">…sayuconnect.com</span> subdomain, with a <span class="mono">&lt;you&gt;_'+esc(repo.split(".")[0])+'</span> database.</p>'
    + '<div class="dc-status" id="dcStatus"></div>'
    + '<div class="checkbox-group"><input type="checkbox" id="dcFiles" checked><label for="dcFiles">Copy files (svn checkout)</label></div>'
    + '<div class="checkbox-group"><input type="checkbox" id="dcDB" checked><label for="dcDB">Copy database (latest nightly backup)</label></div>'
    + '<div class="checkbox-group"><input type="checkbox" id="dcImg" checked><label for="dcImg">Copy images<span class="dc-img-note" id="dcImgNote"></span></label></div>'
    + '<div class="checkbox-group"><input type="checkbox" id="dcPhp8"><label for="dcPhp8">PHP 8 site (use ab8.sayuconnect.com)</label></div>'
    + '<div id="dcImgWrap" style="display:none;margin-top:14px"><div class="dc-img-head"><span>'+icon('copy',14)+' Images</span><span class="dc-img-pct" id="dcImgPct"></span></div>'
    + '<div class="dc-bar"><div class="dc-bar-fill" id="dcImgFill"></div></div><div class="dc-img-sub" id="dcImgSub"></div></div>'
    + '<div id="dcProg" style="display:none;margin-top:14px"><div class="dc-log" id="dcLog"></div></div>'
    + '<div class="alert" id="dcAlert"></div></div>'
    + '<div class="modal-foot"><div class="mf-grow" id="dcFoot"></div>'
    + '<button type="button" class="btn ghost" id="dcStopBtn" style="display:none">Stop</button>'
    + '<button class="btn solid" id="dcStart" data-repo="'+esc(repo)+'">Start dev copy</button></div></div>');
  dcRestoreState(repo);
  dcLoadStatus(repo);
}
// On open (incl. after a page refresh): resume a still-running dev-copy job and reflect any
// in-progress image copy (progress bar + the "Copy images" box disabled so we don't start a 2nd).
function dcRestoreState(repo){
  $.post('dev_copy_status.php', {repository:repo}, function(d){
    if(d && d.ok && d.job && d.state==='running' && DC_OPEN_REPO===repo){
      DC_JOB=d.job; $('#dcProg').show();
      if(d.log!=null){ var $l=$('#dcLog'); $l.text(d.log); $l.scrollTop($l[0].scrollHeight); }
      if(d.target){ $('#dcFoot').html('<span class="mono" style="font-size:12px">'+esc(d.target)+'</span>'); }
      $('#dcStart').hide(); $('#dcStopBtn').show();
      dcStopPoll(); dcPoll(); DC_POLL=setInterval(dcPoll, 1500);
    }
  }, 'json');
  dcStartImgPoll(repo, false);
}
// ---- dev-copy status panel (link to the dev site + files/db/images summary) ----
function devSiteUrl(repo, php8){ if(!DEV_SUBDOMAIN) return ''; return 'https://'+DEV_SUBDOMAIN+(php8?'8':'')+'.sayuconnect.com/'+repo+'/'; }
function dcRelTime(sec){
  if(!sec) return '';
  var d=(new Date()).getTime()/1000 - sec; if(d<0) d=0;
  if(d<90) return 'just now';
  var m=Math.round(d/60); if(m<60) return m+' min ago';
  var h=Math.round(d/3600); if(h<48) return h+'h ago';
  var days=Math.round(d/86400); if(days<14) return days+'d ago';
  return Math.round(days/7)+'w ago';
}
function dcDbEpoch(s){ var m=String(s||'').match(/^(\d+)-(\d+)-(\d+)[ T](\d+):(\d+):(\d+)/); if(!m) return 0; var d=new Date(+m[1],+m[2]-1,+m[3],+m[4],+m[5],+m[6]); var t=d.getTime(); return isNaN(t)?0:Math.floor(t/1000); }
function devAdminUrl(repo, php8, adminPath, adminQuery){ var base=devSiteUrl(repo, php8); return (base && adminPath) ? (base+adminPath+'/admin_login.php'+(adminQuery||'')) : ''; }
function dcDevLinkHtml(repo, adminPath, adminQuery){
  var php8=$('#dcPhp8').is(':checked'), url=devSiteUrl(repo, php8);
  if(!url) return '<span class="dcs-none">no dev subdomain on your account</span>';
  var h='<a class="dcs-open dcs-site" href="'+esc(url)+'" target="_blank" rel="noopener">'+icon('link',13)+'Open dev site</a>';
  var au=devAdminUrl(repo, php8, adminPath, adminQuery);
  if(au){ h+='<a class="dcs-open dcs-admin" href="'+esc(au)+'" target="_blank" rel="noopener">'+icon('login',13)+'Open admin</a>'; }
  return h;
}
function dcStatusRow(ic, label, val){ return '<div class="dcs-row"><span class="dcs-ic">'+icon(ic,15)+'</span><span class="dcs-label">'+label+'</span><span class="dcs-val">'+val+'</span></div>'; }
function dcRenderStatus(d, repo){
  var $s=$('#dcStatus'); if(!$s.length) return;
  var ap=(d&&d.admin_path)||'', aq=(d&&d.admin_query)||'';
  $s.data('adminPath', ap).data('adminQuery', aq);   // stash so the PHP 8 toggle can rebuild the admin href
  var head='<div class="dcs-head"><span class="dcs-title">Current dev copy</span>'+dcDevLinkHtml(repo, ap, aq)+'</div>';
  if(!d || !d.ok){ $s.html(head+'<div class="dcs-empty">Could not read dev-copy status.</div>'); return; }
  if(!d.exists){ $s.html(head+'<div class="dcs-empty">No dev copy on slayer yet — start one below.</div>'); return; }
  var filesVal=(d.rev?'<b>r'+esc(d.rev)+'</b>':'—')+(d.files_mtime?' · updated '+esc(dcRelTime(d.files_mtime)):'');
  var dbVal;
  if(d.db_name){
    var imp=dcDbEpoch(d.db_create), upd=dcDbEpoch(d.db_update);
    dbVal='<span class="mono">'+esc(d.db_name)+'</span>'+(d.db_bytes>=0?' · '+esc(dcBytes(d.db_bytes)):'')
      + (imp?' · imported '+esc(dcRelTime(imp)):'')
      + (upd && Math.abs(upd-imp)>120?' · changed '+esc(dcRelTime(upd)):'');
  } else { dbVal='<span class="dcs-none">not imported</span>'; }
  var imgVal=d.img_present ? ((d.img_count>=0?esc(d.img_count)+' item'+(d.img_count!==1?'s':''):'present')+(d.img_mtime?' · updated '+esc(dcRelTime(d.img_mtime)):''))
    : '<span class="dcs-none">none</span>';
  $s.html(head + dcStatusRow('branch','Files',filesVal) + dcStatusRow('database','Database',dbVal) + dcStatusRow('copy','Images',imgVal));
}
function dcLoadStatus(repo){
  var $s=$('#dcStatus'); if(!$s.length) return;
  $s.html('<div class="dcs-head"><span class="dcs-title">Current dev copy</span>'+dcDevLinkHtml(repo)+'</div><div class="dcs-empty"><span class="spin"></span> Checking dev copy…</div>');
  $.post('dev_copy_info.php', {repository:repo}, function(d){ if(DC_OPEN_REPO===repo) dcRenderStatus(d, repo); }, 'json')
    .fail(function(){ if(DC_OPEN_REPO===repo) dcRenderStatus(null, repo); });
}
function dcFinish(d){
  dcStopPoll();
  var st=(d&&d.state)||'error';
  $('#dcStopBtn').hide(); $('#dcStart').prop('disabled',false).show().text('Start dev copy');
  var $a=$('#dcAlert').removeClass('alert-success alert-error');
  if(st==='done'){ $a.addClass('alert-success').html((d&&d.url)?('Done. <a href="'+esc(d.url)+'" target="_blank" rel="noopener">'+esc(d.url)+'</a>'):'Dev copy complete.').addClass('show'); if(DC_OPEN_REPO) dcLoadStatus(DC_OPEN_REPO); }
  else if(st==='stopped'){ $a.addClass('alert-error').text('Dev copy cancelled.').addClass('show'); }
  else { $a.addClass('alert-error').text((d&&d.message)||'Dev copy failed.').addClass('show'); }
  DC_JOB=null;
}
function dcPoll(){
  if(!DC_JOB) return;
  $.post('dev_copy_status.php', {job:DC_JOB}, function(d){
    if(!d || !d.ok || !DC_JOB) return;
    if(d.log!=null){ var $l=$('#dcLog'); $l.text(d.log); $l.scrollTop($l[0].scrollHeight); }
    if(d.state!=='running') dcFinish(d);
  }, 'json');
}

// ---- Dev DBs browser (read-only, over the sayu-slayer tunnel) ----
function ddbBytes(n){ n=+n||0; if(n>=1073741824) return (n/1073741824).toFixed(1)+' GB'; if(n>=1048576) return (n/1048576).toFixed(1)+' MB'; if(n>=1024) return (n/1024).toFixed(0)+' KB'; return n+' B'; }
function openDevDbs(){
  modal('<div class="modal wide"><div class="modal-head"><div class="mh-ico">'+icon('database',21)+'</div>'
    + '<div style="min-width:0"><h3>Dev DBs</h3><p>Browse the dev databases on slayer (read-only)</p></div>'
    + '<button class="mh-x" data-close-modal="1">'+icon('x',17)+'</button></div>'
    + '<div class="modal-body"><div id="ddbBody"><span class="spin"></span> Loading…</div></div>'
    + '<div class="modal-foot"><div class="mf-grow" id="ddbFoot"></div><button class="btn solid" data-close-modal="1">Close</button></div></div>');
  ddbLoadDbs();
}
function ddbCrumb(parts){
  return '<div class="ddb-crumb">'+parts.map(function(p,i){
    var last=i===parts.length-1;
    return last ? '<span class="ddb-cr-cur">'+esc(p.label)+'</span>'
      : '<a href="#" class="ddb-cr" data-ddb-nav="'+esc(p.nav)+'" data-db="'+esc(p.db||'')+'">'+esc(p.label)+'</a><span class="ddb-cr-sep">/</span>';
  }).join('')+'</div>';
}
function ddbLoadDbs(){
  $('#ddbFoot').text('');
  $.post('dev_db.php', {action:'dbs'}, function(d){
    if(!d||!d.ok){ $('#ddbBody').html('<div class="svn-modal-message svn-modal-message--warn">'+esc((d&&d.error)||'Could not load databases.')+'</div>'); return; }
    var rows=d.dbs.map(function(x){
      return '<tr class="ddb-row" data-ddb-open="db" data-db="'+esc(x.name)+'"><td class="mono">'+esc(x.name)+'</td><td class="ddb-num">'+x.tables+'</td><td class="ddb-num">'+esc(ddbBytes(x.bytes))+'</td></tr>';
    }).join('');
    $('#ddbBody').html(ddbCrumb([{label:'Dev DBs'}])
      + '<input class="ddb-filter" id="ddbFilter" placeholder="Filter databases…" autocomplete="off">'
      + '<div class="ddb-scroll"><table class="ddb-table"><thead><tr><th>Database</th><th class="ddb-num">Tables</th><th class="ddb-num">Size</th></tr></thead><tbody id="ddbList">'+rows+'</tbody></table></div>');
    $('#ddbFoot').text(d.dbs.length+' databases');
    $('#ddbFilter').focus();
  }, 'json');
}
function ddbLoadTables(db){
  $('#ddbBody').html('<span class="spin"></span> Loading '+esc(db)+'…');
  $.post('dev_db.php', {action:'tables', db:db}, function(d){
    if(!d||!d.ok){ $('#ddbBody').html('<div class="svn-modal-message svn-modal-message--warn">'+esc((d&&d.error)||'Could not load tables.')+'</div>'); return; }
    var rows=d.tables.map(function(x){
      return '<tr class="ddb-row" data-ddb-open="table" data-db="'+esc(db)+'" data-table="'+esc(x.name)+'"><td class="mono">'+esc(x.name)+'</td><td class="ddb-num">'+(+x.rows).toLocaleString()+'</td><td class="ddb-num">'+esc(ddbBytes(x.bytes))+'</td><td>'+esc(x.engine||'')+'</td></tr>';
    }).join('');
    $('#ddbBody').html(ddbCrumb([{label:'Dev DBs',nav:'dbs'},{label:db}])
      + '<input class="ddb-filter" id="ddbFilter" placeholder="Filter tables…" autocomplete="off">'
      + '<div class="ddb-scroll"><table class="ddb-table"><thead><tr><th>Table</th><th class="ddb-num">Rows</th><th class="ddb-num">Size</th><th>Engine</th></tr></thead><tbody id="ddbList">'+(rows||'<tr><td colspan="4" style="color:var(--muted)">No tables.</td></tr>')+'</tbody></table></div>');
    $('#ddbFoot').text(d.tables.length+' tables in '+db);
    $('#ddbFilter').focus();
  }, 'json');
}
function ddbLoadRows(db, table, page){
  $('#ddbBody').html('<span class="spin"></span> Loading '+esc(table)+'…');
  $.post('dev_db.php', {action:'rows', db:db, table:table, page:page}, function(d){
    if(!d||!d.ok){ $('#ddbBody').html('<div class="svn-modal-message svn-modal-message--warn">'+esc((d&&d.error)||'Could not load rows.')+'</div>'); return; }
    var head='<tr>'+d.columns.map(function(c){ return '<th>'+esc(c)+'</th>'; }).join('')+'</tr>';
    var body=d.rows.map(function(r){ return '<tr>'+r.map(function(c){ return c===null?'<td class="ddb-null">NULL</td>':'<td>'+esc(c)+'</td>'; }).join('')+'</tr>'; }).join('');
    if(!d.rows.length) body='<tr><td colspan="'+Math.max(1,d.columns.length)+'" style="color:var(--muted)">No rows on this page.</td></tr>';
    var from=d.page*d.per, hasNext=d.rows.length===d.per;
    var pager='<div class="ddb-pager"><button class="btn tiny" id="ddbPrev"'+(d.page<=0?' disabled':'')+'>‹ Prev</button>'
      + '<span class="ddb-pageinfo">rows '+(d.rows.length?(from+1):0)+'–'+(from+d.rows.length)+(d.approx?' of ~'+(+d.approx).toLocaleString():'')+'</span>'
      + '<button class="btn tiny" id="ddbNext"'+(hasNext?'':' disabled')+'>Next ›</button></div>';
    $('#ddbBody').html(ddbCrumb([{label:'Dev DBs',nav:'dbs'},{label:db,nav:'tables',db:db},{label:table}])
      + pager + '<div class="ddb-scroll ddb-rows"><table class="ddb-table ddb-data"><thead>'+head+'</thead><tbody>'+body+'</tbody></table></div>');
    DDB={db:db, table:table, page:d.page};
    $('#ddbFoot').text(db+'.'+table);
  }, 'json');
}
var DDB={};
// Backups modal: list the available DB backups for a site.
function openBackups(repo){
  if(!repo){ alert('Pick a site first (use a site’s ⋯ menu, or select a single site).'); return; }
  BK_OPEN_REPO = repo; INFO_OPEN = null; DC_OPEN_REPO = null; if(typeof dcStopImgPoll==='function'){ dcStopImgPoll(); dcStopPoll(); }
  // Reflect the open backups view in the URL so a refresh reopens it.
  var nh = scopeToHash(STATE.activeGroup) + '~bk=' + encodeURIComponent(repo);
  if(((location.hash||'').replace(/^#/,'')) !== nh){ location.hash = nh; }
  modal('<div class="modal"><div class="modal-head"><div class="mh-ico">'+icon('database',21)+'</div>'
    + '<div style="min-width:0"><h3>Backups</h3><p>Available database backups · <span class="mono">'+esc(repo)+'</span></p></div>'
    + '<button class="mh-x" data-close-modal="1">'+icon('x',17)+'</button></div>'
    + '<div class="modal-body"><div class="bk-head">'+icon('database',15)+'<span>Available DB backups</span><span class="bk-count" id="bkCount"></span></div>'
    + '<div class="bk-target" id="bkTarget"></div>'
    + '<div id="bkList"><span class="spin"></span> Loading backups…</div></div>'
    + '<div class="modal-foot"><div class="mf-grow"></div><button class="btn solid" data-close-modal="1">Close</button></div></div>');
  $.post('get_db_backups.php', {repository:repo}, function(d){ renderBackups(d); }, 'json')
    .fail(function(){ $('#bkCount').text(''); $('#bkList').html('<div class="svn-modal-message svn-modal-message--warn">Could not load backups.</div>'); });
}
// Render the list of available DB backups.
function backupAgo(dateStr){
  if(!dateStr) return '';
  var p = dateStr.split('-'); if(p.length!==3) return '';
  var d = new Date(+p[0], +p[1]-1, +p[2]);
  var today = new Date(); today.setHours(0,0,0,0);
  var days = Math.round((today - d)/86400000);
  if(days<=0) return 'today';
  if(days===1) return 'yesterday';
  if(days<7) return days+' days ago';
  if(days<14) return '1 week ago';
  return Math.floor(days/7)+' weeks ago';
}
function bkCopyText(t){
  if(navigator.clipboard && navigator.clipboard.writeText){ navigator.clipboard.writeText(t); return; }
  var ta=document.createElement('textarea'); ta.value=t; ta.style.position='fixed'; ta.style.opacity='0';
  document.body.appendChild(ta); ta.focus(); ta.select();
  try{ document.execCommand('copy'); }catch(e){}
  document.body.removeChild(ta);
}
function bkBytes(n){ n=+n||0; if(n>=1048576) return (n/1048576).toFixed(1)+' MB'; if(n>=1024) return (n/1024).toFixed(0)+' KB'; return n+' B'; }
function bkDur(s){ s=Math.round(+s||0); if(s>=60){ var m=Math.floor(s/60); return m+'m '+(s%60)+'s'; } return s+'s'; }

var BK = {repo:'', testdb:''};
var BK_JOB=null, BK_POLL=null, BK_OPEN_REPO=null, INFO_OPEN=null;
function bkStopPoll(){ if(BK_POLL){ clearInterval(BK_POLL); BK_POLL=null; } }
function renderBackups(d){
  if(!d || !d.ok){ $('#bkCount').text(''); $('#bkTarget').text(''); $('#bkList').html('<div class="svn-modal-message svn-modal-message--warn">'+esc((d&&d.error)||'Could not load backups.')+'</div>'); return; }
  var list = d.backups||[];
  BK = {repo:d.repository||'', testdb:d.testdb||''};
  $('#bkCount').text(list.length ? list.length : '');
  if(BK.testdb){ $('#bkTarget').html('Restore unpacks into test DB <span class="bk-copy mono" data-copy="'+esc(BK.testdb)+'" title="Click to copy">'+esc(BK.testdb)+' '+icon('copy',11)+'</span> — dropped &amp; recreated each time.'); }
  if(!list.length){ $('#bkList').html('<div class="svn-modal-message">No backups available for this site.</div>'); return; }
  var html = list.map(function(b, i){
    var ago = backupAgo(b.date);
    return '<div class="bk-row'+(i===0?' bk-row--latest':'')+'">'
      + '<div class="bk-line">'
      +   '<span class="bk-file mono">'+esc(b.file)+'</span>'
      +   '<span class="bk-meta">'
      +     (b.size ? '<span class="bk-size">'+esc(bkBytes(b.size))+'</span>' : '')
      +     (b.date ? '<span class="bk-date" title="'+esc(b.date)+'">'+(i===0?'latest · ':'')+esc(ago||b.date)+'</span>' : '')
      +     '<button type="button" class="btn tiny bk-restore" data-file="'+esc(b.file)+'">Restore</button>'
      +   '</span>'
      + '</div>'
      + '</div>';
  }).join('');
  $('#bkList').html('<div class="bk-rows">'+html+'</div>');
}

// ---------------- Servers (host health) ----------------
function svhBytesKb(kb){ kb=+kb||0; var b=kb*1024; if(b>=1099511627776) return (b/1099511627776).toFixed(1)+' TB'; if(b>=1073741824) return (b/1073741824).toFixed(1)+' GB'; if(b>=1048576) return (b/1048576).toFixed(0)+' MB'; return (b/1024).toFixed(0)+' KB'; }
function svhMb(mb){ mb=+mb||0; if(mb>=1048576) return (mb/1048576).toFixed(1)+' TB'; if(mb>=1024) return (mb/1024).toFixed(1)+' GB'; return mb+' MB'; }
function svhDur(s){ s=Math.round(+s||0); var d=Math.floor(s/86400); s-=d*86400; var h=Math.floor(s/3600); s-=h*3600; var m=Math.floor(s/60); if(d>0) return d+'d '+h+'h'; if(h>0) return h+'h '+m+'m'; return m+'m'; }
function svhNum(n){ n=+n||0; if(n>=1e9) return (n/1e9).toFixed(1)+'B'; if(n>=1e6) return (n/1e6).toFixed(1)+'M'; if(n>=1e3) return (n/1e3).toFixed(1)+'k'; return String(n); }
function svhBar(pct, warn){ pct=Math.max(0,Math.min(100,Math.round(pct))); var cls = pct>=(warn||90)?'crit':(pct>=(warn?warn-15:75)?'warn':'ok'); return '<div class="svh-bar"><div class="svh-bar-fill '+cls+'" style="width:'+pct+'%"></div></div>'; }
function svhStat(lbl, val, sub){ return '<div class="svh-stat"><div class="svh-stat-lbl">'+esc(lbl)+'</div><div class="svh-stat-val">'+val+'</div>'+(sub?'<div class="svh-stat-sub">'+sub+'</div>':'')+'</div>'; }
function svhWebCard(w){
  if(!w || !w.hostname && !w.name){ return '<div class="svh-card"><div class="svn-modal-message svn-modal-message--warn">Could not read server metrics.</div></div>'; }
  var stats='';
  // CPU
  if(w.cpu_pct!=null){ stats += svhStat('CPU', esc(w.cpu_pct)+'%', svhBar(w.cpu_pct,90) + '<span class="svh-hint">'+esc(w.cores||'?')+' cores'+(w.load?' · load '+w.load.map(function(x){return x.toFixed(2);}).join(' / '):'')+'</span>'); }
  else if(w.load){ var lpct=w.cores?(w.load[0]/w.cores*100):0; stats += svhStat('Load', w.load.map(function(x){return x.toFixed(2);}).join(' / '), svhBar(lpct,100)+'<span class="svh-hint">'+esc(w.cores||'?')+' cores</span>'); }
  // Memory
  if(w.mem_total_kb){ var used=w.mem_total_kb-(w.mem_avail_kb||0); var mp=w.mem_total_kb?used/w.mem_total_kb*100:0;
    stats += svhStat('Memory', svhBytesKb(used)+' <span class="svh-of">/ '+svhBytesKb(w.mem_total_kb)+'</span>', svhBar(mp,90)+'<span class="svh-hint">'+svhBytesKb(w.mem_avail_kb||0)+' available</span>'); }
  // Swap
  if(w.swap_total_kb){ var su=w.swap_total_kb-(w.swap_free_kb||0); var sp=w.swap_total_kb?su/w.swap_total_kb*100:0;
    stats += svhStat('Swap', svhBytesKb(su)+' <span class="svh-of">/ '+svhBytesKb(w.swap_total_kb)+'</span>', svhBar(sp,80)); }
  var disks=(w.disks||[]).map(function(d){ var dp=d.total_kb?d.used_kb/d.total_kb*100:0;
    return '<div class="svh-disk"><div class="svh-disk-head"><span class="mono">'+esc(d.mount)+'</span><span class="svh-disk-num">'+svhBytesKb(d.avail_kb)+' free <span class="svh-of">/ '+svhBytesKb(d.total_kb)+'</span></span></div>'+svhBar(dp,90)+'</div>';
  }).join('');
  var meta=[];
  if(w.os) meta.push(esc(w.os));
  if(w.kernel) meta.push('kernel '+esc(w.kernel));
  if(w.uptime_s!=null) meta.push('up '+esc(svhDur(w.uptime_s)));
  return '<div class="svh-card">'
    + '<div class="svh-card-head">'+icon('server',17)+'<span class="svh-card-title">Web server</span>'
    +   '<span class="svh-host mono">'+esc(w.hostname||w.name)+'</span>'+(w.is_local?'<span class="svh-tag">this host</span>':'<span class="svh-tag ssh">ssh</span>')+'</div>'
    + (meta.length?'<div class="svh-meta">'+meta.join(' · ')+'</div>':'')
    + '<div class="svh-stats">'+(stats||'<span class="svh-hint">no metrics</span>')+'</div>'
    + (disks?'<div class="svh-disks"><div class="svh-sub">Disks</div>'+disks+'</div>':'')
    + '</div>';
}
function svhDbCard(db){
  if(!db){ return ''; }
  var stats='';
  if(db.uptime!=null) stats += svhStat('DB uptime', esc(svhDur(db.uptime)));
  if(db.threads_connected!=null) stats += svhStat('Connections', esc(db.threads_connected)+(db.max_connections?' <span class="svh-of">/ '+esc(db.max_connections)+'</span>':''), db.threads_running!=null?('<span class="svh-hint">'+esc(db.threads_running)+' running now</span>'):'');
  if(db.questions!=null) stats += svhStat('Queries', esc(svhNum(db.questions)), db.slow_queries!=null?('<span class="svh-hint">'+svhNum(db.slow_queries)+' slow</span>'):'');
  if(db.schemas!=null) stats += svhStat('Databases', esc(db.schemas), db.size_mb!=null?('<span class="svh-hint">'+svhMb(db.size_mb)+' total</span>'):'');
  else if(db.size_mb!=null) stats += svhStat('Data size', svhMb(db.size_mb));
  var tag = db.kind==='shared' ? '<span class="svh-tag">shared</span>' : '<span class="svh-tag ssh">local</span>';
  return '<div class="svh-card">'
    + '<div class="svh-card-head">'+icon('database',17)+'<span class="svh-card-title">Database server</span>'
    +   '<span class="svh-host mono">'+esc(db.host||'')+'</span>'+tag+'</div>'
    + (db.version?'<div class="svh-meta">'+esc(db.version)+'</div>':'')
    + '<div class="svh-stats">'+(stats||'<span class="svh-hint">no status available</span>')+'</div>'
    + (db.note?'<div class="svh-note">'+esc(db.note)+'</div>':'')
    + '</div>';
}
function renderServers(d, repo){
  if(!d || !d.ok){ $('#svhBody').html('<div class="svn-modal-message svn-modal-message--warn">'+esc((d&&d.error)||'Could not read server health.')+'</div>'); return; }
  $('#svhBody').html('<div class="svh-cards">'+svhWebCard(d.web)+svhDbCard(d.db)+'</div>');
}
function openServers(repo){
  if(!repo){ alert('Pick a site first (use a site’s ⋯ menu, or select a single site).'); return; }
  modal('<div class="modal wide"><div class="modal-head"><div class="mh-ico">'+icon('server',21)+'</div>'
    + '<div style="min-width:0"><h3>Servers</h3><p>Health &amp; technical params of the hosting for · <span class="mono">'+esc(repo)+'</span></p></div>'
    + '<button class="mh-x" data-close-modal="1">'+icon('x',17)+'</button></div>'
    + '<div class="modal-body"><div id="svhBody"><span class="spin"></span> Reading server metrics…</div></div>'
    + '<div class="modal-foot"><button class="btn" id="svhRefresh">'+icon('refresh',14)+' Refresh</button><div class="mf-grow"></div><button class="btn solid" data-close-modal="1">Close</button></div></div>');
  var load=function(){ $('#svhBody').html('<span class="spin"></span> Reading server metrics…');
    $.post('server_health.php', {repository:repo}, function(d){ renderServers(d, repo); }, 'json')
      .fail(function(){ $('#svhBody').html('<div class="svn-modal-message svn-modal-message--warn">Request failed.</div>'); }); };
  $(document).off('click.svhRefresh').on('click.svhRefresh', '#svhRefresh', load);
  load();
}

function focusedRepoForGlobal(){
  var sel = selRepos();
  if(sel.length===1) return sel[0];
  if(STATE.activeGroup.indexOf('__one:')===0) return STATE.activeGroup.slice(6);
  var fv = $('#finderInput').val().trim();
  if(fv && REPOS.indexOf(fv)!==-1) return fv;
  return null;
}

// ---------------- events ----------------
$(function(){
  // static icons
  $('#repoIcon').html(icon('branch',17,1.8,'color:var(--info)'));
  $('#grpTriggerFolder').html(icon('folder',16));
  $('#grpTriggerChev').html(icon('chevron',15));
  $('#finderSearchIcon').html(icon('search',17));
  $('#filterSearchIcon').html(icon('search',16));
  $('#viewToggle button[data-view=list]').html(icon('list',16));
  $('#viewToggle button[data-view=cards]').html(icon('grid',16));
  $('#abSave').html(icon('folderPlus',16)+' Save as group');
  $('#abUpdate').html(icon('refresh',16)+' Review &amp; update');
  renderFilterChips();
  renderRecents();

  // Back/forward & manual hash edits -> switch scope and/or overlay (backups / info).
  $(window).on('hashchange', function(){
    var bk = hashBackups(location.hash);
    var info = hashInfo(location.hash);
    var dc = hashDevCopy(location.hash);
    var crit = hashCritical(location.hash);
    var sc = hashToScope(location.hash);
    if(sc && String(sc)!==String(STATE.activeGroup)){
      if(sc==='__all') pickScope('__all', {});
      else if(sc.indexOf('__one:')===0) openSingle(sc.slice(6));
      else pickScope(sc, {});
    }
    reconcileOverlay(bk, info, dc, crit);
  });

  loadGroups(function(){
    // Capture the overlay markers from the hash before scope-restore rewrites it.
    var bk0 = hashBackups(location.hash);
    var info0 = hashInfo(location.hash);
    var dc0 = hashDevCopy(location.hash);
    var crit0 = hashCritical(location.hash);
    (function restoreScope(){
      // 1) Restore scope from the URL hash if present and valid.
      var hsc = hashToScope(location.hash);
      if(hsc==='__all'){ pickScope('__all', {}); return; }
      if(hsc.indexOf('__one:')===0){ openSingle(hsc.slice(6)); return; }
      if(hsc && /^\d+$/.test(hsc)){ pickScope(hsc, {}); return; }
      // 2) Otherwise restore the last-used scope (group / single site / all sites) from localStorage.
      var saved = getSavedScope();
      if(saved==='__all'){ pickScope('__all', {}); return; }
      if(saved.indexOf('__one:')===0){ var sr=saved.slice(6); if(REPOS.indexOf(sr)!==-1){ openSingle(sr); return; } }
      if(saved && /^\d+$/.test(saved) && groupById(saved)){ pickScope(saved, {}); return; }
      // Fallback: auto-open the most recent site, else the remembered repo from the finder box.
      var initial = '';
      var rec = getRecents();
      if(rec.length && REPOS.indexOf(rec[0])!==-1) initial = rec[0];
      if(!initial){ var fv=$('#finderInput').val().trim(); if(fv && REPOS.indexOf(fv)!==-1) initial = fv; }
      if(initial){ openSingle(initial); }
      else { renderGroupTrigger(); renderTable(); renderApplyBar(); }
    })();
    if(bk0 || info0 || dc0 || crit0) reconcileOverlay(bk0, info0, dc0, crit0);
    // Autofocus the finder with its value pre-selected, so the first keystroke replaces it.
    if(!bk0 && !info0 && !dc0 && !crit0){ var $fi=$('#finderInput'); if($fi.val()){ $fi.focus(); try{ $fi[0].select(); }catch(e){} } }
  });

  // group dropdown
  $('#grpTrigger').on('click', function(e){ e.stopPropagation(); if($('#grpDd .grp-dd-menu').length){ closeGroupMenu(); } else { openGroupMenu(); } });
  $(document).on('click', '[data-pick-group]', function(e){ e.stopPropagation(); var id=$(this).attr('data-pick-group'); closeGroupMenu(); pickScope(id, {}); });
  $(document).on('click', '[data-del-group]', function(e){ e.stopPropagation(); var id=$(this).attr('data-del-group'); uiConfirm({icon:'folder', title:'Delete this group?', body:'Sites are not affected — only the saved group is removed.', confirmLabel:'Delete', danger:true}, function(){ groupsAction({action:'delete', id:id}, function(){ if(String(STATE.activeGroup)===String(id)){ pickScope('__all',{}); } openGroupMenu(); }); }); });
  $(document).on('click', '#grpNewBtn', function(e){ e.stopPropagation(); closeGroupMenu(); openSaveGroup(); });
  $(document).on('click', '#grpNewListBtn', function(e){ e.stopPropagation(); closeGroupMenu(); openGroupFromList(); });
  $(document).on('input', '#grpListInput', function(){ renderGroupListPreview(); });
  $(document).on('click', '#saveListGroupBtn', function(){ doSaveListGroup(); });

  // finder
  // Clicking (or tabbing) into the finder selects its contents, so the prefilled site name is
  // replaced by the first keystroke. setTimeout defers past the click that would otherwise
  // deselect the text right after focus.
  $('#finderInput').on('focus', function(){ var el=this; setTimeout(function(){ try{ el.select(); }catch(e){} }, 0); });
  $('#finderInput').on('input', function(){
    var val=$(this).val().toLowerCase(), $dd=$('#finderDd');
    renderFinderMatch();
    if(val.length>0){
      var m = REPOS.filter(function(r){ return r.toLowerCase().indexOf(val)!==-1; }).slice(0,10);
      if(m.length){ $dd.html(m.map(function(x){ return '<div class="finder-opt" data-finder-pick="'+esc(x)+'">'+esc(x)+'</div>'; }).join('')).addClass('show'); }
      else $dd.removeClass('show');
    } else $dd.removeClass('show');
  });
  $(document).on('click', '[data-finder-pick]', function(){ var v=$(this).attr('data-finder-pick'); $('#finderDd').removeClass('show'); if(v){ openSingle(v); } });
  // Mouse hover takes over the keyboard highlight so the two never show at once.
  $(document).on('mouseenter', '#finderDd .finder-opt', function(){ $('#finderDd .finder-opt').removeClass('active'); $(this).addClass('active'); });
  $('#finderInput').on('keydown', function(e){
    var $dd=$('#finderDd'), open=$dd.hasClass('show'), $opts=$dd.find('.finder-opt');
    if((e.which===40||e.which===38) && open && $opts.length){ // arrow down / up
      e.preventDefault();
      var idx=$opts.index($opts.filter('.active'));
      if(e.which===40) idx=(idx+1)%$opts.length; else idx=(idx<=0?$opts.length-1:idx-1);
      $opts.removeClass('active').eq(idx).addClass('active');
      var el=$opts.get(idx); if(el&&el.scrollIntoView) el.scrollIntoView({block:'nearest'});
      return;
    }
    if(e.which===13){ // enter: open highlighted match, else whatever is typed
      e.preventDefault();
      var $act=$opts.filter('.active');
      var v=($act.length ? $act.attr('data-finder-pick') : $(this).val().trim());
      $dd.removeClass('show');
      if(v){ openSingle(v); }
      return;
    }
    if(e.which===27 && open){ e.preventDefault(); $dd.removeClass('show'); return; } // esc closes dropdown
  });
  $(document).on('click', '#finderReview', function(){ var r=$(this).attr('data-repo'); $('#finderDd').removeClass('show'); reviewSingle(r); });
  function reviewSingle(repo){ pendingReviewRepo=repo; openSingle(repo); var s=STATE.sites[repo]; if(s && (s.scanState==='done'||s.scanState==='error')) maybeOpenPendingReview(repo); }
  $(document).on('click', '#finderCopy', function(){ var $b=$(this); copyText($b.attr('data-copy'), function(){ $b.html(icon('check',14)); setTimeout(function(){ $b.html(icon('copy',14)); },1200); }); });
  $(document).on('click', '[data-recent]', function(){ openSingle($(this).attr('data-recent')); });
  function openSingle(repo){ $('#finderInput').val(repo); renderFinderMatch(); saveRecent(repo); pickScope('__one:'+repo, {}); }

  // filter / search / view
  $(document).on('click', '#filterChips .fchip', function(){ STATE.filter=$(this).attr('data-filter'); renderFilterChips(); renderTable(); });
  $('#filterInput').on('input', function(){ STATE.query=$(this).val(); renderTable(); renderApplyBar(); });
  $('#viewToggle button').on('click', function(){ STATE.view=$(this).attr('data-view'); $('#viewToggle button').removeClass('on'); $(this).addClass('on'); renderTable(); });
  $(document).on('click', '#collapseAll', function(){
    var sw=sitesWithFiles(), collapse=$(this).attr('data-mode')!=='expand';
    sw.forEach(function(r){ if(collapse) STATE.collapsed[r]=true; else delete STATE.collapsed[r]; });
    renderTable();
  });

  // selection
  $(document).on('change', '.row-chk', function(e){ e.stopPropagation(); var r=$(this).attr('data-repo'); if(this.checked) STATE.sel[r]=true; else delete STATE.sel[r]; renderTable(); renderApplyBar(); });
  $(document).on('click', '[data-cardrepo]', function(e){ if($(e.target).is('input,button') || $(e.target).closest('button').length) return; var r=$(this).attr('data-cardrepo'); var s=STATE.sites[r]; if(s&&s.status==='update'){ if(STATE.sel[r]) delete STATE.sel[r]; else STATE.sel[r]=true; renderTable(); renderApplyBar(); } });
  $('#selAll').on('change', function(){
    var vis = visibleRepos().filter(function(r){ var s=STATE.sites[r]; return s&&s.scanState==='done'&&s.status==='update'; });
    if(this.checked) vis.forEach(function(r){ STATE.sel[r]=true; });
    else vis.forEach(function(r){ delete STATE.sel[r]; });
    renderTable(); renderApplyBar();
  });

  // collapse / refresh / actions / addgroup
  $(document).on('click', '[data-collapse]', function(){ var r=$(this).attr('data-collapse'); if(STATE.collapsed[r]) delete STATE.collapsed[r]; else STATE.collapsed[r]=true; renderTable(); });
  $(document).on('click', '[data-refresh]', function(e){ e.stopPropagation(); enqueueScan([$(this).attr('data-refresh')], true); });
  $(document).on('click', '#scanAllBtn, #scanBar #scanStop', function(){ if(this.id==='scanStop'){ stopScan(); } else { autoSelectUpdates=false; enqueueScan(visibleRepos(), false); } });
  $(document).on('click', '[data-actions]', function(e){ e.stopPropagation(); $(this).addClass('on'); openActionsPop($(this).attr('data-actions'), this); });
  $(document).on('click', '[data-addgroup]', function(e){ e.stopPropagation(); $(this).addClass('on'); openAddGroupPop($(this).attr('data-addgroup'), this); });

  // popover actions
  $(document).on('click', '[data-toggle-group]', function(e){ e.stopPropagation(); var gid=$(this).attr('data-toggle-group'), repo=$(this).attr('data-repo'); var g=groupById(gid); var inG=g&&g.siteIds.indexOf(repo)!==-1; groupsAction({action: inG?'remove_site':'add_site', id:gid, repository:repo}, function(){ openAddGroupPop(repo, document.querySelector('[data-addgroup="'+cssEsc(repo)+'"]')||document.body); }); });
  $(document).on('click', '[data-newgroup-repo]', function(e){ e.stopPropagation(); var repo=$(this).attr('data-newgroup-repo'); closeAllPopovers(); openSaveGroup([repo]); });
  $(document).on('click', '[data-siteaction]', function(e){ e.stopPropagation(); var a=$(this).attr('data-siteaction'), repo=$(this).attr('data-repo'); closeAllPopovers(); if(a==='devtools') openDevTools(repo); else if(a==='backups') openBackups(repo); else if(a==='servers') openServers(repo); else if(a==='devcopy') openDevCopy(repo); else openInfo(a, repo); });

  // domain right-click context menu
  $(document).on('contextmenu', '#svnApp .host', function(e){
    var $rr = $(this).closest('[data-repo],[data-cardrepo]');
    var repo = $rr.attr('data-repo') || $rr.attr('data-cardrepo') || $(this).text().trim();
    if(!repo || REPOS.indexOf(repo)===-1) return;   // fall back to the browser menu if not a known repo
    e.preventDefault();
    openDomainCtx(repo, e.clientX, e.clientY);
  });
  $(document).on('click', '#svnApp .svnctx-item', function(e){
    e.stopPropagation();
    if($(this).hasClass('svnctx-pending') || $(this).hasClass('svnctx-disabled')) return;
    var act=$(this).attr('data-ctx'); var repo=$(this).closest('.svnctx').attr('data-ctxrepo');
    var admin=knownAdminUrl(repo)||''; var x=e.clientX, y=e.clientY;
    if(act==='copy-domain')      ctxCopy(repo, x, y, 'domain');
    else if(act==='copy-url')    ctxCopy(domainFullUrl(repo), x, y, 'full URL');
    else if(act==='copy-admin'){ if(!admin) return; ctxCopy(adminPathFromUrl(admin), x, y, 'admin path'); }
    else if(act==='open-url')    window.open(domainFullUrl(repo), '_blank', 'noopener');
    else if(act==='open-admin'){ if(!admin) return; window.open(admin, '_blank', 'noopener'); }
    closeCtxMenu();
  });

  // diff
  $(document).on('click', '.vdiff', function(){ openDiff($(this).attr('data-diff-repo'), $(this).attr('data-diff-file')); });
  $(document).on('click', '.file-open', function(){ openSource($(this).attr('data-open-file'), 0, $(this).attr('data-open-repo')); });
  $(document).on('click', '.file-copy', function(){ var $b=$(this); copyText($b.attr('data-copy')||'', function(){ $b.addClass('ok').html(icon('check',13)); setTimeout(function(){ $b.removeClass('ok').html(icon('copy',13)); }, 1200); }); });

  // run a cron line now / close its output panel
  $(document).on('click', '.cron-run-row', function(){ cronRun(parseInt($(this).attr('data-ci'),10), $(this)); });
  $(document).on('click', '.cron-output .co-x', function(){ $(this).closest('.cron-output').remove(); });
  $(document).on('click', '.cron-output .co-copy', function(){ var $b=$(this); copyText($b.data('out')||'', function(){ $b.addClass('ok').html(icon('check',13)); setTimeout(function(){ $b.removeClass('ok').html(icon('copy',13)); }, 1200); }); });

  // copy cron line / all
  $(document).on('click', '.cron-copy, .cron-copy-all', function(){
    var $b=$(this), all=$b.hasClass('cron-copy-all'), orig=$b.html();
    copyText($b.attr('data-copy')||'', function(){
      $b.addClass('ok').html(all ? icon('check',14)+' Copied' : icon('check',14));
      setTimeout(function(){ $b.removeClass('ok').html(orig); }, 1300);
    });
  });

  // cron editing (structured rows)
  $(document).on('click', '.cron-edit-row', function(){ var i=+$(this).attr('data-ci'); if(CRON&&CRON.lines[i]){ CRON.lines[i]._editing=true; renderCronEditor(); } });
  $(document).on('click', '.cron-cancel-row', function(){ var i=+$(this).attr('data-ci'); if(CRON&&CRON.lines[i]){ delete CRON.lines[i]._editing; renderCronEditor(); } });
  $(document).on('click', '.cron-save-row', function(){
    var i=+$(this).attr('data-ci'), $row=$(this).closest('.cron-item');
    var expr=$row.find('[data-cef=expr]').val().trim(), cmd=$row.find('[data-cef=cmd]').val().trim();
    if(!cronValidExpr(expr)){ alert('Schedule must be 5 fields (e.g. 30 14 * * *) or @daily / @hourly / @reboot.'); return; }
    if(!cmd){ alert('Command is required.'); return; }
    CRON.lines[i].expr=expr; CRON.lines[i].cmd=cmd; delete CRON.lines[i]._editing; cronCommit();
  });
  $(document).on('click', '.cron-del-row', function(){
    var i=+$(this).attr('data-ci'); if(!CRON||!CRON.lines[i]) return;
    uiConfirm({icon:'calendar', title:'Delete this cron job?', bodyHtml:'<span class="mono">'+esc(CRON.lines[i].expr+' '+CRON.lines[i].cmd)+'</span>', confirmLabel:'Delete', danger:true},
      function(){ if(CRON&&CRON.lines[i]){ CRON.lines.splice(i,1); cronCommit(); } });
  });
  $(document).on('click', '#cronAddBtn', function(){
    var expr=$('#cronAddExpr').val().trim(), cmd=$('#cronAddCmd').val().trim(), $h=$('#cronAddHint');
    if(!cronValidExpr(expr)){ $h.css('color','var(--err)').text('Schedule must be 5 fields (e.g. 30 14 * * *) or @daily / @hourly / @reboot.'); return; }
    if(!cmd){ $h.css('color','var(--err)').text('Command is required.'); return; }
    CRON.lines.push({type:'cron', expr:expr, cmd:cmd, raw:expr+' '+cmd}); cronCommit();
  });
  $(document).on('keydown', '#cronAddCmd, #cronAddExpr', function(e){ if(e.which===13){ e.preventDefault(); $('#cronAddBtn').click(); } });

  // error log grouped/raw toggle
  $(document).on('click', '#logToggle', function(){ LOG.mode = LOG.mode==='raw'?'grouped':'raw'; renderLog(); });
  // copy-all: single-site log (grouped -> formatted entries; raw -> the raw text) and group-critical
  function copyBtnDone($b){ var h=$b.html(); $b.html(icon('check',13)+' Copied'); setTimeout(function(){ $b.html(h); }, 1200); }
  $(document).on('click', '#logCopyAll', function(){
    var t = (LOG.mode==='raw') ? (LOG.txt||'') : (LOG._shown||[]).map(function(it){ return logCopyText(it.g); }).join('\n\n');
    if(!t) return; bkCopyText(t); copyBtnDone($(this));
  });
  $(document).on('click', '#gcritCopyAll', function(){ var t=copyGroupCriticalText(); if(!t) return; bkCopyText(t); copyBtnDone($(this)); });
  // log filters: severity chips + recency window (shared by single-site log & group critical view)
  $(document).on('click', '.logf-chip', function(){ var k=$(this).attr('data-logf'); LOG_FILTER[k]=(LOG_FILTER[k]===false); if(GCRIT) renderGroupCritical(); else renderLog(); });
  $(document).on('click', '.logw-btn', function(){ LOG_WINDOW=parseInt($(this).attr('data-logw'),10)||0; if(GCRIT) renderGroupCritical(); else renderLog(); });
  // open referenced source file at the reported line
  $(document).on('click', '.log-open', function(){ openSource($(this).attr('data-file'), parseInt($(this).attr('data-line'),10)||0); });
  $(document).on('click', '.log-copy', function(){
    var $b=$(this); bkCopyText($b.attr('data-copy'));
    if($b.data('busy')) return;
    var h=$b.html(); $b.data('busy',1).addClass('log-copied').html(icon('check',13));
    setTimeout(function(){ $b.html(h).removeClass('log-copied').removeData('busy'); }, 1100);
  });

  // history: expand changed files / view revision diff / open file
  $(document).on('click', '.hist-files-toggle', function(){
    var idx=+$(this).attr('data-hi'), $f=$('#histFiles'+idx);
    if($f.is(':visible')){ $f.slideUp(120); }
    else { if(!$f.data('filled')){ $f.html(histFilesHtml((HIST.rows[idx]||{}).files)).data('filled',1); } $f.slideDown(120); }
  });
  $(document).on('click', '.hist-diff', function(){ openRevDiff(HIST.repo, $(this).attr('data-rev')); });
  $(document).on('click', '.hist-revert', function(){ doRevert(HIST.repo, $(this).attr('data-rev')); });
  $(document).on('click', '.hist-file-open', function(){ openSource($(this).attr('data-file'), 0); });

  // apply bar
  $('#abClear').on('click', function(){ STATE.sel={}; renderTable(); renderApplyBar(); });
  $('#abSave').on('click', function(){ openSaveGroup(); });
  $('#abUpdate').on('click', function(){ openConfirmUpdate(); });
  $(document).on('click', '#confirmApply', function(){ var upd=selUpdatable(); runBatchUpdate(upd); });

  // save group submit
  $(document).on('click', '#saveGroupBtn', function(){ doSaveGroup(); });
  $(document).on('keypress', '#grpNameInput', function(e){ if(e.which===13) doSaveGroup(); });
  function doSaveGroup(){ var name=$('#grpNameInput').val().trim(); var repos=$('#grpNameInput').data('repos')||[]; if(!name){ $('#grpNameInput').focus(); return; } groupsAction({action:'create', name:name, repositories:repos}, function(d){ closeModal(); if(d.newId){ pickScope(String(d.newId), {}); } }); }

  // global head buttons
  $('#btnHistory,#btnLog,#btnCron,#btnMail').on('click', function(){ openInfo($(this).attr('data-info'), focusedRepoForGlobal()); });
  // Critical errors: aggregate across the whole group/all scope, or fall back to the single focused site.
  $('#btnCritical').on('click', function(){
    var ag=STATE.activeGroup;
    if(ag && ag!=='__none' && ag.indexOf('__one:')!==0){ openGroupCritical(); return; }
    var repo=focusedRepoForGlobal();
    if(repo){ openInfo('critical', repo); return; }
    alert('Choose a group (or a single site) to view critical errors.');
  });
  $(document).on('click', '.gcrit-open', function(){ openInfo('critical', $(this).attr('data-crit-repo')); });
  $(document).on('click', '#gcritReload', function(){ openGroupCritical(true); });
  $('#btnServers').on('click', function(){ openServers(focusedRepoForGlobal()); });
  $('#btnBackups').on('click', function(){ openBackups(focusedRepoForGlobal()); });
  $('#btnDevCopy').on('click', function(){ openDevCopy(focusedRepoForGlobal()); });
  $('#btnDevDbs').on('click', function(){ openDevDbs(); });

  // restore a backup into the per-site test DB (background job + live progress)
  function bkFinish(d){
    bkStopPoll();
    if(!BK_JOB) return;
    var $row=BK_JOB.$row, $btn=BK_JOB.$btn, st=(d&&d.state)||'error';
    $row.find('.bk-prog').remove();
    var cls = st==='done' ? 'bk-note--ok' : 'bk-note--err';
    var msg = esc((d&&d.message) || (st==='done'?'Restore complete.':st==='stopped'?'Restore cancelled.':'Restore failed.'));
    $row.append('<div class="bk-note '+cls+'">'+msg+'</div>');
    BK_JOB=null;
    if($btn) $btn.text('Restore');
    $('.bk-restore').prop('disabled',false).text('Restore').show();
  }
  function bkPoll(){
    if(!BK_JOB) return;
    $.post('restore_db_status.php', {job:BK_JOB.job}, function(d){
      if(!d || !d.ok || !BK_JOB) return;
      BK_JOB.$row.find('.bk-bar-fill').css('width', (d.percent||0)+'%');
      if(d.state==='running'){
        var p=[ (d.percent||0)+'%' ];
        if(d.total) p.push(bkBytes(d.done)+' / '+bkBytes(d.total));
        if(d.rate)  p.push(bkBytes(d.rate)+'/s');
        if(d.eta)   p.push('~'+bkDur(d.eta)+' left');
        BK_JOB.$row.find('.bk-prog-stat').text(p.join('  ·  '));
      } else {
        bkFinish(d);
      }
    }, 'json');
  }
  function bkStartRestore($btn, file, repo){
    var $row=$btn.closest('.bk-row'); $row.find('.bk-note').remove();
    $('.bk-restore').prop('disabled',true); $btn.html('<span class="spin"></span> Starting…');
    $.post('restore_db_backup.php', {repository:repo, file:file}, function(d){
      if(!d || !d.ok){
        $('.bk-restore').prop('disabled',false); $btn.text('Restore');
        $row.append('<div class="bk-note bk-note--err">'+esc((d&&d.error)||'Could not start restore.')+'</div>');
        return;
      }
      $btn.hide();
      $row.append('<div class="bk-prog"><div class="bk-bar"><div class="bk-bar-fill"></div></div>'
        + '<div class="bk-prog-foot"><span class="bk-prog-stat">Starting…</span>'
        + '<button type="button" class="btn tiny bk-stop">Stop</button></div></div>');
      BK_JOB={ job:d.job, total:d.total, $row:$row, $btn:$btn };
      bkPoll();
      BK_POLL=setInterval(bkPoll, 1000);
    }, 'json').fail(function(){
      $('.bk-restore').prop('disabled',false); $btn.text('Restore');
      $row.append('<div class="bk-note bk-note--err">Could not start restore.</div>');
    });
  }
  $(document).on('click', '.bk-restore', function(){
    var $btn=$(this), file=$btn.attr('data-file'), repo=BK.repo, testdb=BK.testdb;
    if(!repo || !file) return;
    if(BK_JOB){ uiConfirm({icon:'database', title:'Restore already running', body:'A restore is already in progress. Wait for it to finish, or stop it first.', confirmLabel:'OK', cancelLabel:'Dismiss'}); return; }
    uiConfirm({
      icon:'database',
      title:'Restore this backup?',
      bodyHtml:'Unpack <span class="mono">'+esc(file)+'</span> into the test database <span class="mono">'+esc(testdb)+'</span>.'
        + '<br><br>This <b>drops and recreates</b> <span class="mono">'+esc(testdb)+'</span> — anything currently in it is lost. The live site is not affected.',
      confirmLabel:'Restore',
      danger:true,
      foot:'Imports into a test DB only.'
    }, function(){ bkStartRestore($btn, file, repo); });
  });
  $(document).on('click', '.bk-stop', function(){
    if(!BK_JOB) return;
    $(this).prop('disabled',true).text('Stopping…');
    $.post('restore_db_stop.php', {job:BK_JOB.job}, function(){}, 'json'); // poller settles on 'stopped'
  });
  // click the test-DB chip to copy its name
  $(document).on('click', '.bk-copy', function(){
    var $b=$(this); bkCopyText($b.attr('data-copy'));
    if($b.data('busy')) return;
    var html=$b.html(); $b.data('busy',1).addClass('bk-copied').html('Copied '+icon('check',12));
    setTimeout(function(){ $b.html(html).removeClass('bk-copied').removeData('busy'); }, 1200);
  });

  // dev tools download
  $(document).on('click', '#dlStart', function(){
    var repo=$(this).attr('data-repo'); var isDb=$('#dlDB').is(':checked'), isImg=$('#dlImg').is(':checked');
    var $a=$('#dlAlert').removeClass('show alert-success alert-error'); $(this).prop('disabled',true).html('<span class="spin"></span> Downloading…');
    var self=this;
    $.get('hosting_download.php', {project:repo, is_db:isDb, is_images:isImg}, function(xml){
      $a.addClass(xml.substring(0,4)==='-ERR'?'alert-error':'alert-success').html(esc(xml)).addClass('show');
      $(self).prop('disabled',false).text('Start download');
    });
  });

  // dev copy (background job on slayer)
  $(document).on('click', '#dcStart', function(){
    if(DC_JOB) return;
    var repo=$(this).attr('data-repo');
    var files=$('#dcFiles').is(':checked'), db=$('#dcDB').is(':checked'), img=$('#dcImg').is(':checked'), php8=$('#dcPhp8').is(':checked');
    if(!files && !db && !img){ $('#dcAlert').addClass('alert-error show').text('Pick at least one of files / database / images.'); return; }
    $('#dcAlert').removeClass('show alert-success alert-error');
    $(this).prop('disabled',true).html('<span class="spin"></span> Starting…');
    $('#dcProg').show(); $('#dcLog').text('Starting…');
    $.post('dev_copy.php', {repository:repo, files:files?1:0, db:db?1:0, images:img?1:0, php8:php8?1:0}, function(d){
      if(!d || !d.ok){
        $('#dcStart').prop('disabled',false).text('Start dev copy');
        $('#dcAlert').addClass('alert-error show').text((d&&d.error)||'Could not start dev copy.');
        $('#dcProg').hide(); return;
      }
      DC_JOB=d.job; $('#dcStart').hide(); $('#dcStopBtn').show();
      if(d.target){ $('#dcFoot').html('<span class="mono" style="font-size:12px">'+esc(d.target)+'</span>'); }
      dcPoll(); DC_POLL=setInterval(dcPoll, 1500);
      if(img){ dcStartImgPoll(repo, true); } // watch the async dsid image copy
    }, 'json').fail(function(){
      $('#dcStart').prop('disabled',false).text('Start dev copy');
      $('#dcAlert').addClass('alert-error show').text('Could not start dev copy.'); $('#dcProg').hide();
    });
  });
  $(document).on('click', '#dcStopBtn', function(){
    if(!DC_JOB) return; $(this).prop('disabled',true).text('Stopping…');
    $.post('dev_copy_stop.php', {job:DC_JOB}, function(){}, 'json');
  });
  // Keep the "Open dev site" / "Open admin" links in step with the PHP 8 toggle (-> <subdomain>8.sayuconnect.com).
  $(document).on('change', '#dcPhp8', function(){
    if(!DC_OPEN_REPO) return;
    var php8=$(this).is(':checked'), $s=$('#dcStatus');
    $s.find('.dcs-site').attr('href', devSiteUrl(DC_OPEN_REPO, php8));
    var au=devAdminUrl(DC_OPEN_REPO, php8, $s.data('adminPath'), $s.data('adminQuery'));
    if(au) $s.find('.dcs-admin').attr('href', au);
  });

  // dev DBs browser
  $(document).on('click', '[data-ddb-open=db]', function(){ ddbLoadTables($(this).attr('data-db')); });
  $(document).on('click', '[data-ddb-open=table]', function(){ ddbLoadRows($(this).attr('data-db'), $(this).attr('data-table'), 0); });
  $(document).on('click', '[data-ddb-nav]', function(e){ e.preventDefault(); var n=$(this).attr('data-ddb-nav'); if(n==='dbs') ddbLoadDbs(); else if(n==='tables') ddbLoadTables($(this).attr('data-db')); });
  $(document).on('click', '#ddbPrev', function(){ if(DDB.table && DDB.page>0) ddbLoadRows(DDB.db, DDB.table, DDB.page-1); });
  $(document).on('click', '#ddbNext', function(){ if(DDB.table) ddbLoadRows(DDB.db, DDB.table, DDB.page+1); });
  $(document).on('input', '#ddbFilter', function(){
    var q=$(this).val().toLowerCase();
    $('#ddbList tr').each(function(){ var t=$(this).find('td:first').text().toLowerCase(); $(this).toggle(t.indexOf(q)!==-1); });
  });

  // close handlers
  $(document).on('click', '[data-close-pop]', function(){ closeAllPopovers(); });
  $(document).on('click', '[data-close-modal]', function(){ closeModal(); });
  $(document).on('click', '[data-scrim]', function(e){ if(e.target===this) closeModal(); });
  // styled confirm dialog
  $(document).on('click', '[data-close-confirm]', function(){ closeConfirm(); });
  $(document).on('click', '[data-confirm-scrim]', function(e){ if(e.target===this) closeConfirm(); });
  $(document).on('click', '#uiConfirmYes', function(){ var cb=UICONFIRM_CB; closeConfirm(); if(cb) cb(); });
  // source viewer (layered above the log modal)
  $(document).on('click', '[data-close-source]', function(){ closeSource(); });
  // mail log: open a message's log entry; toggle parsed/raw; copy raw
  $(document).on('click', '[data-mailqid]', function(){ openMailEntry(MAIL_REPO, $(this).attr('data-mailqid')); });
  $(document).on('click', '#meToggle', function(){ MAIL_ENTRY.mode = MAIL_ENTRY.mode==='raw'?'parsed':'raw'; renderMailEntry(); });
  $(document).on('click', '#meCopy', function(){ var $b=$(this); bkCopyText(MAIL_ENTRY.raw||''); var h=$b.html(); $b.html(icon('check',13)+' Copied'); setTimeout(function(){ $b.html(h); }, 1200); });
  $(document).on('click', '[data-source-scrim]', function(e){ if(e.target===this) closeSource(); });
  $(document).on('click', function(e){
    if(!$(e.target).closest('#finderInput,#finderDd').length) $('#finderDd').removeClass('show');
    if(!$(e.target).closest('#grpDd').length) closeGroupMenu();
    if(!$(e.target).closest('.pop,.site-btn').length) closeAllPopovers();
  });
  $(document).on('keydown', function(e){ if(e.key==='Escape'){ if($('#sourceHost').children().length){ closeSource(); return; } if(confirmOpen()){ closeConfirm(); return; } closeAllPopovers(); closeModal(); } });
});

function copyText(text, done){
  if(navigator.clipboard && navigator.clipboard.writeText){ navigator.clipboard.writeText(text).then(done).catch(function(){}); }
  else { var ta=document.createElement('textarea'); ta.value=text; document.body.appendChild(ta); ta.select(); try{ if(document.execCommand('copy')) done(); }finally{ document.body.removeChild(ta); } }
}
function cssEsc(s){ return String(s).replace(/["\\]/g,'\\$&'); }

})();
</script>
</body>
</html>
