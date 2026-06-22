<?php
/*
	run this file from AJAX call to get list of all recently updated files in specified repository
	@param: repository
*/

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");

$repository = GetParam("repository");
if (!strlen($repository)) die ("<div class='alert alert-error'>Please enter the SVN repository name in the box above</div>");
$command = "index.php?action=showupdates&username=".$svn_login."&password=".$svn_password."&repository=".$repository;
$res = get_page($svn_path. $command); 

// Human-readable labels (SVN codes; working copy = the live site, "*" = out of date)
$statuses = array(
	"*" => "Out of date",
	"A" => "Added on live",
	"D" => "Removed on live",
	"M" => "Edited on live",
	"C" => "Conflict",
	"?" => "Not in SVN",
	"!" => "Missing on live",
	"L" => "Locked",
	"R" => "Replaced",
	"~" => "Type changed",
);

// Stable CSS suffixes for badge colours (see svn/index.php)
$svn_status_badge_slug = array(
	"*" => "not-on-server",
	"A" => "to-add",
	"D" => "to-delete",
	"M" => "modified",
	"C" => "conflict",
	"?" => "not-in-svn",
	"!" => "missing",
	"L" => "locked",
	"R" => "replaced",
	"~" => "type-change",
);

/**
 * Parse one line from SVN "show updates" output into [status, revision, full_path] or null to skip.
 */
function svn_parse_update_line($row) {
	$row = trim(preg_replace('/\s+/', ' ', $row));
	if ($row === '') {
		return null;
	}
	$skip_res = array(
		'/status against revision/i',
		'/^summary of conflicts/i',
		'/^text conflicts:/i',
		'/^tree conflicts:/i',
		'/^merged conflicts:/i',
		'/^resolved conflicts:/i',
		'/^conflict details/i',
		'/^subversion is /i',
		'/^-+$/',
		'/^\d+\s+text conflicts/i',
		'/^\d+\s+tree conflicts/i',
	);
	foreach ($skip_res as $re) {
		if (preg_match($re, $row)) {
			return null;
		}
	}
	$parts = explode(' ', $row);
	$n = count($parts);
	if ($n < 3) {
		return null;
	}

	// Find path: first segment containing '/'; else last segment if it looks like a filename
	$path_start = false;
	for ($i = 0; $i < $n; $i++) {
		if (strpos($parts[$i], '/') !== false) {
			$path_start = $i;
			break;
		}
	}
	if ($path_start === false) {
		$last = $parts[$n - 1];
		if (preg_match('/\.[a-z0-9]{1,8}$/i', $last)) {
			$path_start = $n - 1;
		}
	}
	if ($path_start === false || $path_start < 2) {
		return null;
	}

	$fullpath = implode(' ', array_slice($parts, $path_start));
	$path_ok = (strpos($fullpath, '/') !== false) || preg_match('/\.[a-z0-9]{1,8}$/i', $fullpath);
	if (!$path_ok) {
		return null;
	}

	$before = array_slice($parts, 0, $path_start);
	// SVN: status (column 1), then rev(s) / author; revision for display = last * or numeric before path
	$svn_first_letters = 'ACDMR?!L*~';
	$status_raw = $before[0];
	$c0 = strtoupper(substr(trim($status_raw), 0, 1));
	if (strpos($svn_first_letters, $c0) === false && isset($before[1])) {
		$status_raw = $before[1];
	}
	$status = strlen($status_raw) > 1 ? substr(trim($status_raw), 0, 1) : $status_raw;
	$rev = '-';
	for ($j = count($before) - 1; $j >= 1; $j--) {
		if ($before[$j] === '*' || ctype_digit($before[$j])) {
			$rev = $before[$j];
			break;
		}
	}
	return array($status, $rev, $fullpath);
}


if (strpos($res,'Server response is: +OK') !== false) {
	$lines = explode("Server response is: +OK Updates:", $res);

	$sql = "SELECT date_added,user_id FROM svn_updates WHERE repository='".$repository."' ORDER BY date_added DESC";
	//echo $sql;
	$db->query($sql);
	$last_update = ""; $last_user_id = 0;
	if ($db->next_record()) {
		$last_update  = $db->f("date_added");
		$last_user_id = $db->f("user_id");
	}
	if (strlen($last_update)) {
		$last_update = "Last update:".$last_update;
		if ($last_user_id) {
			$sql = "SELECT first_name,last_name FROM users WHERE user_id=".$last_user_id;
			$db->query($sql);
			if ($db->next_record()) {
				$last_update .= " by ".$db->f("first_name"). " ".$db->f("last_name");
			}
		}
		if ($last_update) {
			$last_update.= " <a href='#myModal' role='button' id='bntHistory' class='btn btn-mini btn-info' data-toggle='modal'>History</a>";
		}
	}
	$last_update .= "<button class='btn btn-mini btn-info' data-toggle='modalDevelopers' id='btnDevelopers'>Developers Tools</button>";

	//$last_update .= "<button class='btn btn-mini btn-info' data-toggle='modalLog' id='btnLog'>Error Logs</button>";
	$last_update.= " <a href='#modalLogs' role='button' id='btnLog' class='btn btn-mini btn-info' data-toggle='modal'>Last 50 Errors</a>";
	$last_update.= " <a href='#modalCryticalLogs' role='button' id='btnCryticalLog' class='btn btn-mini btn-info' data-toggle='modal'>Critical Errors</a>";
	$last_update.= " <a href='#modalCron' role='button' id='btnCron' class='btn btn-mini btn-info' data-toggle='modal'>Cron Jobs</a>";
		
	
	if (sizeof($lines) >1) {
	    $rows = explode("\n",$lines[1]);
	    if (GetParam("artem") == "test") {
	    	echo $lines[1];
	    	exit;
	    }
	    $valid_rows = array();
	    foreach ($rows as $row) {
	    	if (strpos($row, 'Status against revision') !== false) {
	    		continue;
	    	}
	    	$parsed = svn_parse_update_line($row);
	    	if ($parsed === null) {
	    		continue;
	    	}
	    	list ($status_code, $version, $fullpath) = $parsed;
	    	$fullpath = str_replace('\\', '/', $fullpath);
	    	$file_name = basename($fullpath);
	    	$dir = dirname($fullpath);
	    	if ($dir === '.' || $dir === '') {
	    		$file_path = '';
	    	} else {
	    		$file_path = $dir . '/';
	    	}
	    	$status_label = isset($statuses[$status_code]) ? $statuses[$status_code] : $status_code;
	    	$status_badge = isset($svn_status_badge_slug[$status_code]) ? $svn_status_badge_slug[$status_code] : 'default';
	    	$valid_rows[] = array(
	    		"status"       => $status_label,
	    		"status_badge" => $status_badge,
	    		"version"      => $version,
	    		"file_name"    => $file_name,
	    		"file_path"    => $file_path,
	    		"rel_path"     => $fullpath,
	    	);
	    }
	    setcookie("monitor_svn_repository" ,$repository, time()+60*60*24*180);
	    if (!sizeof($valid_rows)) die ("<div class='alert alert-info'>No files to update for <b>".$repository."</b> ".$last_update."</div>");
    } else die ("OK: no updates");
}
else die("ERROR:".$res);

?>
	<div class='alert alert-info'>
		<?php echo sizeof($valid_rows); ?> update<?php if (sizeof($valid_rows)>1) echo "s" ?> found. <? echo $last_update; ?>
	</div>
        <table class="table table-striped svn-files-table">
        	<thead>
        	<tr>
        		<th class="svn-th-sortable" data-sort="path" title="Click to sort">File Path</th>
        		<th class="svn-th-sortable" data-sort="name" title="Click to sort">File Name</th>
        		<th class="svn-th-sortable" data-sort="status" title="Click to sort">Status</th>
        		<th class="svn-th-sortable" data-sort="revision" title="Click to sort">Revision</th>
        		<th>Diff</th>
        	</tr>
        	</thead>
        	<tbody>
        	<?php foreach ($valid_rows as $fields) {
        		$rel = isset($fields["rel_path"]) ? $fields["rel_path"] : (($fields["file_path"] !== '' ? rtrim($fields["file_path"], '/') . '/' : '') . $fields["file_name"]);
        		$sort_path = strtolower($fields["file_path"]);
        		$sort_name = strtolower($fields["file_name"]);
        		$sort_status = $fields["status_badge"];
        		$ver = $fields["version"];
        		$sort_rev = (is_string($ver) || is_int($ver)) && ctype_digit((string)$ver) ? (int)$ver : 0;
        		?>
        	<tr data-sort-path="<?php echo htmlspecialchars($sort_path, ENT_QUOTES, 'UTF-8'); ?>" data-sort-name="<?php echo htmlspecialchars($sort_name, ENT_QUOTES, 'UTF-8'); ?>" data-sort-status="<?php echo htmlspecialchars($sort_status, ENT_QUOTES, 'UTF-8'); ?>" data-sort-rev="<?php echo (int)$sort_rev; ?>">
        		<td><? echo $fields["file_path"]; ?></td>
        		<td><? echo $fields["file_name"]; ?></td>
        		<td><span class="svn-status-badge svn-status--<?php echo htmlspecialchars($fields["status_badge"]); ?>"><?php echo htmlspecialchars($fields["status"]); ?></span></td>
        		<td><? echo $fields["version"]; ?></td>
        		<td class="svn-diff-cell"><button type="button" class="svn-diff-link" data-repo="<?php echo htmlspecialchars($repository, ENT_QUOTES, 'UTF-8'); ?>" data-file="<?php echo htmlspecialchars($rel, ENT_QUOTES, 'UTF-8'); ?>" data-path="<?php echo htmlspecialchars($fields["file_path"], ENT_QUOTES, 'UTF-8'); ?>" data-name="<?php echo htmlspecialchars($fields["file_name"], ENT_QUOTES, 'UTF-8'); ?>">View diff</button></td>
        	</tr>
        	<?php } ?>
        	</tbody>
        </table>
        <input type="hidden" id="hdnFilesNumber" value="<?php echo sizeof($valid_rows); ?>">