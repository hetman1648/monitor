<?php
/*
	Read-only browser for the dev databases on slayer, over the sayu-slayer:3311 tunnel
	(monitor MySQL creds). JSON dispatcher:
	  action=dbs                         -> list dev schemas (name, tables, bytes)
	  action=tables & db=                -> tables in a schema (name, rows, bytes, engine)
	  action=rows   & db= & table= & page-> a page of rows (columns + 100 rows)

	Strictly read-only: only SELECTs we build here run; db/table names are whitelisted against
	information_schema before being used as identifiers. No user-supplied SQL is executed.
*/

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");

header("Content-Type: application/json");
function ddb_json($a) { echo json_encode($a); exit; }

$SLAYER_DB_HOST = "sayu-slayer";
$SLAYER_DB_PORT = 3311;
$PAGE = 100;
$SYS = array("information_schema", "mysql", "performance_schema", "sys");

$m = @mysqli_connect($SLAYER_DB_HOST, DATABASE_USER, DATABASE_PASSWORD, "", $SLAYER_DB_PORT);
if (!$m) ddb_json(array("ok" => false, "error" => "Could not connect to the slayer database server."));
@mysqli_set_charset($m, "utf8mb4");

function ddb_q($m, $sql) { $r = mysqli_query($m, $sql); return $r; }
function ddb_esc_id($name) { return "`" . str_replace("`", "``", $name) . "`"; }
function ddb_sys_list() { global $SYS; return "'" . implode("','", $SYS) . "'"; }

/** Confirm a schema exists (and is not a system one); returns true/false. */
function ddb_schema_ok($m, $db) {
	global $SYS;
	if (in_array($db, $SYS, true)) return false;
	$st = mysqli_prepare($m, "SELECT 1 FROM information_schema.schemata WHERE schema_name=? LIMIT 1");
	mysqli_stmt_bind_param($st, "s", $db); mysqli_stmt_execute($st);
	mysqli_stmt_store_result($st); $ok = mysqli_stmt_num_rows($st) > 0; mysqli_stmt_close($st);
	return $ok;
}
function ddb_table_ok($m, $db, $table) {
	$st = mysqli_prepare($m, "SELECT 1 FROM information_schema.tables WHERE table_schema=? AND table_name=? LIMIT 1");
	mysqli_stmt_bind_param($st, "ss", $db, $table); mysqli_stmt_execute($st);
	mysqli_stmt_store_result($st); $ok = mysqli_stmt_num_rows($st) > 0; mysqli_stmt_close($st);
	return $ok;
}

$action = GetParam("action");

if ($action === "dbs") {
	$out = array();
	$r = ddb_q($m, "SELECT s.schema_name,
			(SELECT COUNT(*) FROM information_schema.tables t WHERE t.table_schema=s.schema_name) AS tables,
			(SELECT COALESCE(SUM(t.data_length+t.index_length),0) FROM information_schema.tables t WHERE t.table_schema=s.schema_name) AS bytes
		FROM information_schema.schemata s
		WHERE s.schema_name NOT IN (" . ddb_sys_list() . ")
		ORDER BY s.schema_name");
	while ($r && ($row = mysqli_fetch_assoc($r))) {
		$out[] = array("name" => $row["schema_name"], "tables" => (int)$row["tables"], "bytes" => (float)$row["bytes"]);
	}
	ddb_json(array("ok" => true, "dbs" => $out));
}

if ($action === "tables") {
	$db = (string) GetParam("db");
	if (!ddb_schema_ok($m, $db)) ddb_json(array("ok" => false, "error" => "Unknown database."));
	$out = array();
	$st = mysqli_prepare($m, "SELECT table_name, table_rows, data_length+index_length AS bytes, engine
		FROM information_schema.tables WHERE table_schema=? ORDER BY table_name");
	mysqli_stmt_bind_param($st, "s", $db); mysqli_stmt_execute($st);
	$res = mysqli_stmt_get_result($st);
	while ($row = mysqli_fetch_assoc($res)) {
		$out[] = array("name" => $row["table_name"], "rows" => (int)$row["table_rows"], "bytes" => (float)$row["bytes"], "engine" => $row["engine"]);
	}
	mysqli_stmt_close($st);
	ddb_json(array("ok" => true, "db" => $db, "tables" => $out));
}

if ($action === "rows") {
	$db = (string) GetParam("db");
	$table = (string) GetParam("table");
	$page = max(0, (int) GetParam("page"));
	if (!ddb_schema_ok($m, $db) || !ddb_table_ok($m, $db, $table)) ddb_json(array("ok" => false, "error" => "Unknown table."));

	$cols = array();
	$st = mysqli_prepare($m, "SELECT column_name FROM information_schema.columns WHERE table_schema=? AND table_name=? ORDER BY ordinal_position");
	mysqli_stmt_bind_param($st, "ss", $db, $table); mysqli_stmt_execute($st);
	$res = mysqli_stmt_get_result($st);
	while ($row = mysqli_fetch_row($res)) $cols[] = $row[0];
	mysqli_stmt_close($st);

	$approx = 0;
	$st2 = mysqli_prepare($m, "SELECT table_rows FROM information_schema.tables WHERE table_schema=? AND table_name=?");
	mysqli_stmt_bind_param($st2, "ss", $db, $table); mysqli_stmt_execute($st2);
	$rr = mysqli_stmt_get_result($st2); if ($x = mysqli_fetch_row($rr)) $approx = (int)$x[0]; mysqli_stmt_close($st2);

	$offset = $page * $PAGE;
	$sql = "SELECT * FROM " . ddb_esc_id($db) . "." . ddb_esc_id($table) . " LIMIT " . (int)$PAGE . " OFFSET " . (int)$offset;
	$r = ddb_q($m, $sql);
	if ($r === false) ddb_json(array("ok" => false, "error" => "Could not read rows: " . mysqli_error($m)));
	$rows = array();
	while ($row = mysqli_fetch_row($r)) {
		$cells = array();
		foreach ($row as $v) {
			if ($v === null) { $cells[] = null; }
			else { $v = (string)$v; if (strlen($v) > 300) $v = substr($v, 0, 300) . "…"; $cells[] = ensure_utf8($v); }
		}
		$rows[] = $cells;
	}
	ddb_json(array("ok" => true, "db" => $db, "table" => $table, "page" => $page, "per" => $PAGE, "approx" => $approx, "columns" => $cols, "rows" => $rows));
}

ddb_json(array("ok" => false, "error" => "Unknown action."));
