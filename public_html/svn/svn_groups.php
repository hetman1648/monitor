<?php
/*
	AJAX (JSON): shared SVN site groups (visible to all users).
	@param action: list | create | rename | delete | add_site | remove_site | set_sites
	Tables: svn_groups, svn_group_sites  (see install_svn_groups.sql)
*/

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");

header('Content-Type: application/json; charset=utf-8');

function svn_groups_json($a) {
	echo json_encode($a);
	exit;
}

/** Return all groups (each with its repositories), newest first. */
function svn_groups_fetch_all(&$db) {
	$groups = array();
	$order = array();
	$db->query("SELECT id,name,created_by,date_added FROM svn_groups ORDER BY name ASC");
	while ($db->next_record()) {
		$id = (int) $db->f("id");
		$groups[$id] = array(
			'id'         => $id,
			'name'       => $db->f("name"),
			'created_by' => (int) $db->f("created_by"),
			'date_added' => $db->f("date_added"),
			'siteIds'    => array(),
		);
		$order[] = $id;
	}
	if (count($groups)) {
		$db->query("SELECT group_id,repository FROM svn_group_sites ORDER BY repository ASC");
		while ($db->next_record()) {
			$gid = (int) $db->f("group_id");
			if (isset($groups[$gid])) {
				$groups[$gid]['siteIds'][] = $db->f("repository");
			}
		}
	}
	$out = array();
	foreach ($order as $id) {
		$out[] = $groups[$id];
	}
	return $out;
}

$action = GetParam("action");

switch ($action) {

	case 'list':
		svn_groups_json(array('ok' => true, 'groups' => svn_groups_fetch_all($db)));
		break;

	case 'create': {
		$name = trim((string) GetParam("name"));
		if ($name === '') {
			svn_groups_json(array('ok' => false, 'error' => 'Please enter a group name.'));
		}
		$db->query("INSERT INTO svn_groups (name,created_by,date_added) VALUES ("
			. ToSQL($name, "text") . "," . (int) $user_id . ",NOW())");
		$gid = (int) $db->last_id();
		$repos = GetParam("repositories");
		if (is_array($repos)) {
			foreach ($repos as $r) {
				$r = trim((string) $r);
				if ($r !== '') {
					$db->query("INSERT IGNORE INTO svn_group_sites (group_id,repository) VALUES ("
						. $gid . "," . ToSQL($r, "text") . ")");
				}
			}
		}
		svn_groups_json(array('ok' => true, 'groups' => svn_groups_fetch_all($db), 'newId' => $gid));
		break;
	}

	case 'rename': {
		$id = (int) GetParam("id");
		$name = trim((string) GetParam("name"));
		if (!$id || $name === '') {
			svn_groups_json(array('ok' => false, 'error' => 'Missing group or name.'));
		}
		$db->query("UPDATE svn_groups SET name=" . ToSQL($name, "text") . " WHERE id=" . $id);
		svn_groups_json(array('ok' => true, 'groups' => svn_groups_fetch_all($db)));
		break;
	}

	case 'delete': {
		$id = (int) GetParam("id");
		if (!$id) {
			svn_groups_json(array('ok' => false, 'error' => 'Missing group.'));
		}
		$db->query("DELETE FROM svn_group_sites WHERE group_id=" . $id);
		$db->query("DELETE FROM svn_groups WHERE id=" . $id);
		svn_groups_json(array('ok' => true, 'groups' => svn_groups_fetch_all($db)));
		break;
	}

	case 'add_site': {
		$id = (int) GetParam("id");
		$repo = trim((string) GetParam("repository"));
		if (!$id || $repo === '') {
			svn_groups_json(array('ok' => false, 'error' => 'Missing group or site.'));
		}
		$db->query("INSERT IGNORE INTO svn_group_sites (group_id,repository) VALUES ("
			. $id . "," . ToSQL($repo, "text") . ")");
		svn_groups_json(array('ok' => true, 'groups' => svn_groups_fetch_all($db)));
		break;
	}

	case 'remove_site': {
		$id = (int) GetParam("id");
		$repo = trim((string) GetParam("repository"));
		if (!$id || $repo === '') {
			svn_groups_json(array('ok' => false, 'error' => 'Missing group or site.'));
		}
		$db->query("DELETE FROM svn_group_sites WHERE group_id=" . $id
			. " AND repository=" . ToSQL($repo, "text"));
		svn_groups_json(array('ok' => true, 'groups' => svn_groups_fetch_all($db)));
		break;
	}

	case 'set_sites': {
		$id = (int) GetParam("id");
		if (!$id) {
			svn_groups_json(array('ok' => false, 'error' => 'Missing group.'));
		}
		$db->query("DELETE FROM svn_group_sites WHERE group_id=" . $id);
		$repos = GetParam("repositories");
		if (is_array($repos)) {
			foreach ($repos as $r) {
				$r = trim((string) $r);
				if ($r !== '') {
					$db->query("INSERT IGNORE INTO svn_group_sites (group_id,repository) VALUES ("
						. $id . "," . ToSQL($r, "text") . ")");
				}
			}
		}
		svn_groups_json(array('ok' => true, 'groups' => svn_groups_fetch_all($db)));
		break;
	}

	default:
		svn_groups_json(array('ok' => false, 'error' => 'Unknown action.'));
}
