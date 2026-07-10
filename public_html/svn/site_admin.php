<?php
/*
	AJAX (JSON): resolve a site's admin auto-login URL + client id, WITHOUT running an svn scan.
	Used by the domain right-click context menu (Open → Admin / Copy → Admin Path) so it works
	even for sites that haven't been scanned yet. The full-scan svn_site_status.php returns the
	same adminUrl for scanned sites.

	@param repository   the domain (repo name)
	Returns { ok, repository, adminUrl, clientId }
*/

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");
include_once ("./svn_repo_support.php");

header('Content-Type: application/json; charset=utf-8');

$repository = trim((string) GetParam("repository"));
if ($repository === '' || !preg_match('/^[A-Za-z0-9.\-]+$/', $repository) || strpos($repository, '..') !== false) {
	echo json_encode(array('ok' => false, 'error' => 'Invalid repository.'));
	exit;
}

$admin = svn_site_admin($db, $repository);
echo json_encode(array(
	'ok'         => true,
	'repository' => $repository,
	'adminUrl'   => isset($admin['adminUrl']) ? $admin['adminUrl'] : '',
	'clientId'   => isset($admin['clientId']) ? (int) $admin['clientId'] : 0,
));
