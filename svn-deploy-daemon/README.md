# SVN deploy daemon + gateway

Reference copies of the two legacy components behind the Monitor **SVN Updater**'s
"Update / Deploy" action. These are **not** part of the Monitor PHP app and are not served — they
live on the servers below. They're checked in here so the source and its history are tracked.

> The **deployed files on the servers are the source of truth.** Secrets are redacted in these copies
> (e.g. the gateway's ftpdb password → `getenv('FTPDB_ROOT_PW')`). Edit on the server, then update the
> copy here.

## Deploy flow

```
Monitor svn/update_repository.php
  -> HTTPS GET https://web1.sayu.co.uk/svn/index.php?action=checkout&username=&password=&repository=
       (the gateway; served from /var/secure_www/svn/ on web1)
  -> looks up the site's server in ftpdb.repository_connections (web1-hosted -> local UNIX socket;
     off-web1 -> that server's IP:8998)
  -> talks to the SayuSvn daemon on that server, sends "Checkout"
  -> daemon runs `svn update` in the site's working copy, then chowns it back to the site user
```

## Files

| File | Deployed to | What |
|---|---|---|
| `gateway-index.php` | `/var/secure_www/svn/index.php` on **web1** | HTTP gateway; auth via `/etc/sayu-svn/.passwd`; routes actions to the local (`LOCAL`) or per-repo (`VARIABLE`) SayuSvn daemon. |
| `SayuSvn` | `/usr/local/sbin/SayuSvn` on **web1 + each off-web1 server** | The perl daemon (systemd unit `SayuSvn`, listens on a UNIX socket on web1, TCP `:8998` on off-web1 servers). Does the actual `svn update` + ownership fix. |

Per-server config (not committed — contains DB creds): `/etc/sayu-svn/sayu-svn.conf`
(`clients_root`, `svn_root`, `clients_db_*`, `passwd_file`, TCP `socket` port).
Off-web1 servers are mapped in Monitor at `public_html/svn/svn_hosts.php`.

## Fix history (2026-07)

`sub checkout` used to look up the site uid/gid via `DBI->connect("ftpdb","root","netD0wn")` to chown
the tree back to the site user after `svn update` (which runs as root). Those are web1's creds; on the
off-web1 servers that connect fails, so the handler died before replying `+OK` — the gateway (and the
Monitor UI) hung at "Updating", and updated files were left **root-owned**. Fixed by:

1. `svn update/info/status` now run with `--non-interactive --trust-server-cert --no-auth-cache`.
2. The DB-based chown was replaced with a DB-free chown to the working-copy directory's own owner:
   `my $owner = (getpwuid((stat($target))[4]))[0]; system("chown -R $owner:$owner $target &");`

Applied on **puregusto, web2, rss, rubberduck** (backup on each: `/usr/local/sbin/SayuSvn.bak-cfa`).

Reliable restart (the daemon doesn't check `bind()`, so a stop+pkill+start can silently fail to
listen): `systemctl reset-failed SayuSvn && systemctl start SayuSvn`, then verify `ss -ltn | grep 8998`.
