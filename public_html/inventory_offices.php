<?php
include("./includes/common.php");

CheckSecurity(10);

    $T = new iTemplate("./templates",array("page"=>"inventory_offices.html"));
	//if ($err) $T->set_var("err", $err);else $T->set_var("error", "");//$T->set_var("err",$err);



	//-- office details

	$sql = "SELECT PERM_USER_PROFILE FROM lookup_users_privileges WHERE privilege_id = " . GetSessionParam("privilege_id");
	$db->query($sql,__FILE__,__LINE__);
	if ($db->next_record()) {
		$perm_user_profile = $db->f("PERM_USER_PROFILE");
	} else {
		exit($db->Error);
	}

	$colpan = 4;
	if (!$perm_user_profile) {
		$T->set_var("control", "");//exit("You don't have permission for this!");
		$colpan -= 2;
		$T->set_var("title_operation", "");
		$T->set_var("control_operation", "");
	}

	$T->set_var("colpan", $colpan);

	$sql="select * from offices";

    $a = 0;
	$db->query($sql,__FILE__,__LINE__);
	if ($db->nf()>0){		while ($db->next_record()) {
			$T->set_var(array(
							  "colorrow"       => (($a++)%2 == 1)?"DataRow2":"DataRow3",
							  "office_title"   => htmlspecialchars($db->Record["office_title"]),
							  "office_address" => htmlspecialchars($db->Record["office_address"]),
							  "office_id"	   => $db->Record["office_id"]));
			$T->parse("office_orders",true);
		}
	}
	else {		$T->set_var("office_orders","");
	}


	$T->pparse("page");

?>