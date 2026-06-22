# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Sayu's internal **Monitor** system — a long-lived (originally ~2004) PHP/MySQL intranet for task tracking, time reporting, vacations/holidays, productivity reports, and helpdesk/Trello sync. Hosted at `monitor.sayu.co.uk`. Roughly 150 top-level PHP scripts under `public_html/`.

No build system, no dependency manager (no `composer.json` / `package.json`), no test suite. Edits are made to files that the webserver serves directly.

## Running / deploying

- The site is served live from `public_html/` on the host (Apache + mod_rewrite + Basic Auth). HTTPS is forced by `public_html/.htaccess`.
- There is no dev server command, no build step, no asset bundler. To "run" a change, save the file and load the URL — PHP picks it up.
- Access is gated by HTTP Basic Auth + an IP allowlist in `public_html/.htaccess` (`AuthUserFile /etc/.htpasswd`, user `mextern`). New developer IPs need to be added there to load anything.
- The additional working directory `/home/vhosts/monitor.sayu.co.uk` is the same tree as the primary working directory `/mnt/drive2/vhosts/monitor.sayu.co.uk` (bind-mount / symlink) — edits in either are equivalent.

## Architecture

### Bootstrap chain

Almost every entrypoint starts with:

```php
include("./includes/common.php");
```

`includes/common.php` is the spine — it pulls in `db_mysql.inc` (custom `DB_Sql` class, predates PDO), `db_connect.php` (DB credentials), `common_functions.php`, `tasks_functions.php`, defines privilege/status constants, instantiates a global `$db`, sets `$sAppPath = "./templates"`, and calls `session_start()`. After include, pages typically call `CheckSecurity(1)` which redirects unauthenticated users to `login.php`.

Because `common.php` already creates `$db`, individual scripts use that global directly (`global $db; $db->query(...)`). Some scripts create a second `$db2` when they need a parallel cursor.

### Three DBs

`includes/db_connect.php` defines credentials for three databases used from this app:

- `monitor` (primary) — tasks, projects, users, vacations, lunches, holidays, inventory, etc.
- `sayu` (`SAYU_*` constants) — shared Sayu-wide data.
- `c2s` on a remote host (`VIART_COM_*` constants) — used by Viart-related integrations.

To talk to a non-default DB, instantiate another `DB_Sql` and assign `Database/User/Password/Host` from the appropriate constants — see `index.php` for an example.

### Helper layer (`public_html/includes/`)

- `common_functions.php` — the kitchen-sink helpers: `GetParam` / `GetSessionParam` / `SetSessionParam`, `ToSQL($value, $type)` (the only safe way to interpolate into SQL — always use it), `CheckSecurity($level)`, `to_hours` / `Hours2HoursMins`, `array2js`, `send_enotification`, `ensure_utf8`, etc.
- `tasks_functions.php` — the task domain layer: `add_task`, `update_task`, `start_task` / `stop_task` / `close_task`, `task_add_hours`, `add_task_message`, `attach_files`, plus Trello/helpdesk sync (`sendTaskUpdate2Trello`, `sync_helpdesk_after_monitor_task_created`) and the dashboard block renderers (`show_today_tasks`, `show_active_bugs`, `GetTasksList`, ...).
- `modern_header.php` — header/nav for the modern UI; pages include it after `common.php`.
- `template.php` — custom `iTemplate` engine. Templates live in `public_html/templates/*.html` (one per page, e.g. `create_task.html`, `ajax_responder.html`). Pattern: `$t = new iTemplate($sAppPath); $t->set_file("main", "foo.html"); $t->parse(...); $t->p(...)`.
- `date_functions.php`, `productivity_functions.php`, `service_functions.php`, `viart_support.php` — domain-specific helpers.
- `Lite.php`, `PEAR.php`, `components/PEAR/`, `lib/nusoap/` — vendored legacy libraries; do not edit.

### Privileges and statuses

Constants defined in `common.php`:

- `PRIV_DEVELOPER=1`, `PRIV_TESTER=2`, `PRIV_ARCHITECT=3`, `PRIV_PM=4`, `PRIV_PARTNER=5`, `PRIV_ADMIN=6`, `PRIV_CUSTOMER=9`.
- Task statuses `STATUS_IN_PROGRESS=1` … `STATUS_READY_TO_DOCUMENT=14`.
- Per-page permissions live in the `lookup_users_privileges` table (columns like `PERM_CLOSE_TASKS`, `PERM_VIEW_ALL_TASKS`); they are loaded into `$_SESSION["session_perms"]` at login and checked via `has_permission($perm)`.

### AJAX

`public_html/ajax_responder.php` is the central JSON/HTML dispatcher: a big `switch (GetParam("action"))` that routes to `ajax_*` functions (kanban moves, message posting, vacation flows, client/domain search, quick-add task, etc.). New AJAX endpoints go here as a new `case` + a new `ajax_*` function. Some endpoints additionally validate `HTTP_X_API_KEY` against `API_KEY_TEAM_HOURS` (env `MONITOR_API_KEY`, with a hard-coded fallback).

### Cron / background

Scripts invoked by system cron (not via the web) include `cron_reminders.php`, `cron_add_to_holiday.php`, `cron_make_projects_required.php`, and the `autostop_task*.php` family that closes/stops still-running tasks. They follow the same bootstrap (`includes/common.php`) but should not rely on a session.

### Templates and assets

- HTML templates: `public_html/templates/*.html` (legacy `{var}`-style placeholders consumed by `iTemplate`).
- Per-page CSS: `public_html/site.css`, `public_html/styles/`.
- JS: `public_html/scripts/` (jQuery 1.2.6, Prototype, plus page-specific scripts like `create_task.js`, `timedoctor-trello-compat.js`).
- User uploads: `public_html/attachments/`, `public_html/temp_attachments/`, `public_html/documents/` (all gitignored).

## Conventions to follow when editing

- **SQL injection is the #1 risk.** Always wrap dynamic values with `ToSQL($value, $type)` (types: `integer` / `Number` / `string` / `date` / etc.) instead of string concatenation or `addslashes`. Many older scripts do it wrong; do not copy those patterns into new code.
- **Use the global `$db`** (and `$db2` if you need a second cursor) instead of opening new connections. After a query, advance with `$db->next_record()` and read fields with `$db->f("col")`.
- **Reading request input goes through `GetParam("name")`**, not `$_GET`/`$_POST` directly — it normalizes across sources and strips magic-quotes artifacts.
- **Auth gate**: every web-facing PHP page should call `CheckSecurity($level)` after including `common.php`. Cron scripts skip this.
- **HTML escaping**: use `ToHTML($value)` for text rendered into HTML and `escape_task_title_for_js()` for task titles inlined into JS strings.
- **Encoding**: legacy rows can be Windows-1252. Run text through `ensure_utf8()` before rendering when in doubt.
- Editing existing pages in place is the norm; do not introduce a framework, a router, Composer, or a build step without checking with the user first.

## Git

`main` is the working branch (deploys live). Recent history is short, dated commits (e.g. `ver 7may 26`, `17mar2026 ver`) — match the style: lower-case, terse, present-tense.

The `secure_html/`, `tmp/`, `_copy/`, `backup/`, large `*.log`, and `monfiles.tgz` directories/files in the tree are operational, not source — leave them alone.
