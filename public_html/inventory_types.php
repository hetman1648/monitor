<?php
include("./includes/common.php");

CheckSecurity(10);

/*
if (!GetSessionParam("UserID")) {
	header("Location: login.php");
	exit();
}
*/


    $T = new iTemplate("./templates",array("page"=>"inventory_types.html"));


	$sql = "SELECT PERM_USER_PROFILE FROM lookup_users_privileges WHERE privilege_id = " . GetSessionParam("privilege_id");
	$db->query($sql,__FILE__,__LINE__);
	if ($db->next_record()) {
		$perm_user_profile = $db->f("PERM_USER_PROFILE");
	} else {
		exit($db->Error);
	}

	$colpan = 5;
	if (!$perm_user_profile) {
		$T->set_var("control", "");//exit("You don't have permission for this!");
		$colpan -= 3;
		$T->set_var("title_operation", "");
		$T->set_var("control_operation", "");
	}

	$T->set_var("colpan", $colpan);

	//-- type details

	$sql="select * from inventory_types";


	$db->query($sql,__FILE__,__LINE__);

	$a = 0;
	if ($db->nf()>0) {		while ($db->next_record()) {
			$T->set_var(array(
							  "colorrow"			 => (($a++)%2 == 1)?"DataRow2":"DataRow3",
							  "inventory_type_title" => htmlspecialchars($db->Record["inventory_type_title"]),//$title,//$db->Record["inventory_type_title"],
							  "inventory_type_desc"  => htmlspecialchars(substr($db->Record["inventory_type_desc"],0,50)),//$desc,//$db->Record["inventory_type_desc"],
							  "type_id"   			 => htmlspecialchars($db->Record["inventory_type_id"])//(!$id)?-1:$id//$db->Record["inventory_type_id"]
							  ));
			$T->parse("types_orders",true);
		}
	}else {
		$T->set_var("types_orders","");
	}

	$T->pparse("page");

?>