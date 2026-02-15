<?php
	include("./includes/common.php");
	CheckSecurity(1);
	$T = new iTemplate($sAppPath);
	$T->set_file("main","view_sites_tags.html");

	$sql  = " SELECT t.title, COUNT(st.site_id) AS sites_count FROM (clients_tags t";
	$sql .= " INNER JOIN clients_sites_tags st ON st.tag_id=t.id)";
	$sql .= " GROUP BY t.id ";
	$db->query($sql);
	
	while ($db->next_record()) {
		$T->set_var("tag_title", $db->f("title"));
		$T->set_var("sites_count", $db->f("sites_count"));
		$T->parse("tag");
	}
	
	$T->pparse("main", false);
?>