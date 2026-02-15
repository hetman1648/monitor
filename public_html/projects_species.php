<?php
	include("./includes/common.php");
	CheckSecurity(1);

	$t = new iTemplate($sAppPath);
	$t->set_file("main","projects_species.html");

	$sql = "SELECT * FROM productivity_species";
	$sql .= " ORDER BY species";
	$db->query($sql);
	
	$species = array();
	while ($db->next_record()) {
		$species[] = $db->f("species_id");
	}
	
	if (GetParam("update") != '') {
		foreach ($species as $id) {
			$per_hour = (int) GetParam("per_hour_".$id);
			$privilege = (int) GetParam("privilege_".$id);
			
			$sql = "UPDATE productivity_species ";
			$sql .= " SET projects_price = ".ToSQL(GetParam("price_".$id), "number");
			$sql .= " ,per_hour = ".ToSQL($per_hour, "number");
			$sql .= " ,hour_privilege = ".ToSQL($privilege, "number");
			$sql .= " WHERE species_id = ".ToSQL($id, "integer");
			
			$db->query($sql);
		}
	}
	
	$sql = "SELECT * FROM productivity_species";
	$sql .= " ORDER BY species";
	$db->query($sql);
	$i=0;
	while ($db->next_record()) {
		$t->set_var("type_id", $db->f("species_id"));
		$t->set_var("type_name", $db->f("species"));
		$t->set_var("type_price", $db->f("projects_price"));
		$t->set_var("per_hour", $db->f("per_hour") ? "checked" : "");
		$t->set_var("privilege", $db->f("hour_privilege") ? "checked" : "");
		$t->set_var("colorrow", $i%2 ? "DataRow2" : "DataRow3");
		$i++;
		$t->parse("project");
	}
	
	$t->pparse("main");
?>