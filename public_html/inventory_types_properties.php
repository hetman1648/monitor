<?php
include("./includes/common.php");

CheckSecurity(10);

	$err	= "";
	$where	= "";

	$name   = GetParam("edit_inventory_types_property_name");
	$desc   = GetParam("edit_inventory_types_property_desc");
	$value  = GetParam("edit_inventory_types_property_value");
	$idtype = GetParam("edit_inventory_type_title");
	$id	    = GetParam("type_id");

	$operation	= GetParam("operation");

	$f_type = GetParam("type_selected");

	if ($operation=="filter")
	{	if($f_type){
			$where .= " WHERE ";
			if ($f_type) { $where .= "it.inventory_type_id=".ToSQL($f_type,"integer")." AND "; }
			$where .= " 1 ";
		}
		unset($name);
		unset($desc);
		unset($value);
		unset($idtype);
	}

	if ($id) {$f_type = $id;}

	if ($operation=="view" && $id<>"-1")
	{	//$id = GetParam("type_id");
		$where = "WHERE it.inventory_type_id=".ToSQL($id,"integer");
	}

    $T = new iTemplate("./templates",array("page"=>"inventory_types_properties.html"));
	if ($err) $T->set_var("err", $err); else $T->set_var("error", "");//$T->set_var("err",$err);

	$sql = "SELECT PERM_USER_PROFILE FROM lookup_users_privileges WHERE privilege_id = " . GetSessionParam("privilege_id");
	$db->query($sql,__FILE__,__LINE__);
	if ($db->next_record()) {
		$perm_user_profile = $db->f("PERM_USER_PROFILE");
	} else {
		exit($db->Error);
	}

	$colpan = 6;
	if (!$perm_user_profile) {
		$T->set_var("control", "");//exit("You don't have permission for this!");
		$colpan -= 2;
		$T->set_var("title_operation", "");
		$T->set_var("control_operation", "");
	}

	$T->set_var("colpan", $colpan);


	//-- type details
	$sql="SELECT * FROM inventory_types_properties tp
						LEFT JOIN inventory_types AS it ON tp.inventory_type_id=it.inventory_type_id ".$where;

	$db->query($sql,__FILE__,__LINE__);

	$a = 0;
	if ( $db->nf() > 0){
		while ($db->next_record()) {
			$T->set_var(array(
							  "colorrow"						=> (($a++)%2 == 1)?"DataRow2":"DataRow3",
							  "inventory_types_property_name"   => htmlspecialchars($db->Record["type_property_name"]),
							  "inventory_types_property_value"  => htmlspecialchars($db->Record["type_property_value_default"]),
							  "inventory_types_property_desc"   => htmlspecialchars($db->Record["type_property_desc"]),
							  "inventory_type_title"			=> htmlspecialchars(((strlen($db->Record["inventory_type_title"])>0)?$db->Record["inventory_type_title"]:"")),
							  "property_id"			  			=> $db->Record["type_property_id"]));
			$T->parse("types_property_orders",true);
		}
	}
	else {		$T->set_var("types_property_orders","");
	}

	$T->set_var("types",get_options("inventory_types",
									"inventory_type_id",
									"inventory_type_title",
									"inventory_type_title",
									((!$f_type)?-1:$f_type)
									));

	$T->set_var("edit_inventory_type_title", get_options("inventory_types",
	                                               		 "inventory_type_id",
	                                                	 "inventory_type_title",
	                                                	 "inventory_type_title",
	                                                	 @$idtype));

	$T->set_var("filterclause",((!$f_type)?-1:$f_type));
	/*
	$T->set_var(array("edit_inventory_types_property_name"   => @$name,
					  "edit_inventory_types_property_value"  => @$value,
					  "edit_inventory_types_property_desc"   => @$desc
					  ));
/*
	$T->set_var("user_name",GetSessionParam("UserName"));
	$T->set_var("error", "");
	if ($user_id) {
		$T->set_var("params", "?user_id=" . $user_id);
	} else {
		$T->set_var("params", "");
	}

	//show calendar (eq style.display)
	$T->set_var("display_start_calendar","");
*/
	$T->pparse("page");

?>