<?php
include("./includes/common.php");

CheckSecurity(10);

	$enable = "DISABLED";
	$err    = "";
	$url	= @$_SERVER['HTTP_REFERER'];

	$title	  = GetParam("inventory_title");
	$desc	  = GetParam("inventory_desc");
	$idtype   = GetParam("inventory_type_title");
	$code	  = GetParam("inventory_code");
	$date	  = GetParam("date_added");
	$idoffice = GetParam("office_title");
	$iduser   = GetParam("user_name");
	$id		  = GetParam("inventory_id");

	$guarantee	= GetParam("guarantee");
	$operation	= GetParam("operation");

	if ($operation=="add" && $id==-1)//new inventory
	 {
	    if (!$title) {$err .= "<b>Inventory title</b> is required<br>";}
	    if ($code) {	    	$sql = "SELECT inventory_id FROM inventory WHERE inventory_code=".ToSQL($code,"string")."";
	    	$db->query($sql,__FILE__,__LINE__);
	    	if ($db->num_rows()>0) {$err .= "<b>Inventory Code</b> exists in the database<br>";}
	    }

	    if (!$err){    	$sql="insert into 	inventory(inventory_title,
	 	                            		inventory_desc,
	 	                            		inventory_type_id,
	 	                            		inventory_code,
	 	                            		date_added,
	 	                            		office_id,
	 	                            		guarantee_exist)
	 	                  		 values(".ToSQL($title,"string").",
	 	                  		 		".ToSQL($desc,"string").",
	 	                    	   		".(($idtype>0)?$idtype:0).",
	 	                    	   		".ToSQL($code,"string").",
	 	                           		'".$date."',
	 	                           		".(($idoffice>0)?$idoffice:0).",
	 	                           		".($guarantee?1:0).")";
	 		$db->query($sql,__FILE__,__LINE__);
	 		$lastid=$db->last_id();
	        if ($iduser){        	$sql="insert ignore into inventory_users(inventory_id,
	 											  user_id)
	 									   values(".$lastid.",
	 									   		  ".ToSQL($iduser,"").")";
	 			$db->query($sql,__FILE__,__LINE__);
	        }

	        if ($idtype){        	$db_new = new DB_Sql();
				$db_new->Database = DATABASE_NAME;
				$db_new->User     = DATABASE_USER;
				$db_new->Password = DATABASE_PASSWORD;
				$db_new->Host     = DATABASE_HOST;
	        	$sql = "select type_property_id as _id,
	        				   type_property_name as _name,
	        				   type_property_desc as _desc,
	        				   type_property_value_default as _value
	        			from inventory_types_properties
	        			where inventory_type_id=".$idtype;
	        	$db->query($sql,__FILE__,__LINE__);

	        	while ($db->next_record()){
	        		$sql_new = "insert into inventory_properties(inventory_id,
	        													 type_property_id,
	        													 inventory_property_name,
	        													 inventory_property_value,
	        													 inventory_property_desc)
	        											  values(".$lastid.",
	        													 0,
	        													 '".$db->Record["_name"]."',
	        													 '".$db->Record["_value"]."',
	        													 '".$db->Record["_desc"]."')";
	        		$db_new->query($sql_new,__FILE__,__LINE__);
	        	}

	        	unset($db_new);
	        }

	 		header("Location: inventory.php");
	    }
	 }

	if ($operation=="update" && $id<>-1)//update edit inventory
	 { 	/*
	 	$title	  = GetParam("inventory_title");
	 	$desc	  = GetParam("inventory_desc");
	 	$idtype   = GetParam("inventory_type_title");
	 	$code	  = GetParam("inventory_code");
	 	$date	  = GetParam("date_added");
	 	$idoffice = GetParam("office_title");
	 	$username = GetParam("user_name");
	 	//$id		  = GetParam("inventory_id");
		*/
	 	if (!$title) {$err .= "<b>Inventory title</b> is required<br>";}
	 	if ($code) {
	    	$sql = "SELECT inventory_id FROM inventory WHERE inventory_code=".ToSQL($code,"string")."";
	    	$db->query($sql,__FILE__,__LINE__);
	    	if ($db->num_rows()>0) {	    		while ($db->next_record()) {
		    		if ($id <> $db->Record["inventory_id"]) {$err .= "<b>Inventory Code</b> exists in the database<br>";}
		    	}
		    }
	    }

	 	if (!$err){ 		$newtype = false;
	 		$sql = "select inventory_type_id from inventory where inventory_id=".ToSQL($id,"integer");
	 		$db->query($sql,__FILE__,__LINE__);
	 		$db->next_record();
	        if (!($db->Record["inventory_type_id"] == $idtype)) {$newtype = true;}

	 		$sql = "update inventory set inventory_title=".  ToSQL($title,"string").",
	 	                           	     inventory_desc=".   ToSQL($desc,"string").",
	 	                           	     inventory_type_id=".ToSQL($idtype,"integer").",
	 	                           	     inventory_code=".   ToSQL($code,"string").",
	 	                           	     date_added='".		$date."',
	 	                           	     office_id='".		$idoffice."',
	 	                           	     guarantee_exist=".	($guarantee?1:0)."
	 	                        where inventory_id=".ToSQL($id,"integer");
	 		$db->query($sql,__FILE__,__LINE__);

	        if (!$iduser) {
	        	$sql="delete from inventory_users where inventory_id=".ToSQL($id,"integer");
	        }
	        else {
	        	$sql="update inventory_users set user_id=".ToSQL($iduser,"integer")." where inventory_id=".ToSQL($id,"integer");
	        }

	        if ($idtype && $newtype){
	        	$db_new = new DB_Sql();
				$db_new->Database = DATABASE_NAME;
				$db_new->User     = DATABASE_USER;
				$db_new->Password = DATABASE_PASSWORD;
				$db_new->Host     = DATABASE_HOST;

	        	$sql = "select type_property_id as _id,
	        				   type_property_name as _name,
	        				   type_property_desc as _desc,
	        				   type_property_value_default as _value
	        			from inventory_types_properties
	        			where inventory_type_id=".ToSQL($idtype,"integer");
	        	$db->query($sql,__FILE__,__LINE__);

	        	while ($db->next_record()){
	        		$sql_new = "insert into inventory_properties(inventory_id,
	        													 type_property_id,
	        													 inventory_property_name,
	        													 inventory_property_value,
	        													 inventory_property_desc)
	        											  values(".ToSQL($id,"integer").",
	        													 0,
	        													 '".$db->Record["_name"]."',
	        													 '".$db->Record["_value"]."',
	        													 '".$db->Record["_desc"]."')";
	        		$db_new->query($sql_new,__FILE__,__LINE__);
	        	}

	        	unset($db_new);
	        }

	 		$db->query($sql,__FILE__,__LINE__);
	 	}

	 	header("Location: inventory.php");
	 }


	if ($operation=="edit" && $id<>-1)//load edit inventory
	 {
	    $enable = "";
	    //$id		= GetParam("inventory_id");

	    $sql="select *,
	    			 inv.inventory_id as inv_id,
	                 concat(u.first_name,' ',u.last_name) as user_name
		        from inventory inv
		        	 left join inventory_users as inus on inus.inventory_id=inv.inventory_id
		             left join users as u on u.user_id=inus.user_id
		             left join offices as ofc on ofc.office_id=inv.office_id
		       where inv.inventory_id=".ToSQL($id,"integer");
		$db->query($sql,__FILE__,__LINE__);
		$db->next_record();

		$idtype	  = $db->Record["inventory_type_id"];
		$idoffice = $db->Record["office_id"];
		$iduser	  = $db->Record["user_id"];
		$id		  = $db->Record["inv_id"];
		$title	  = $db->Record["inventory_title"];
		$desc	  = $db->Record["inventory_desc"];
		$code	  = $db->Record["inventory_code"];
		$date	  = $db->Record["date_added"];
		$guarantee	= $db->Record["guarantee_exist"];
	 }

	if ($operation=="delete" && $id<>-1)
	{

	 $sql = "delete from inventory where inventory_id=".ToSQL($id,"integer");
	 $db->query($sql,__FILE__,__LINE__);
	 $sql = "delete from inventory_properties where inventory_id=".ToSQL($id,"integer");
	 $db->query($sql,__FILE__,__LINE__);
	 $sql = "delete from inventory_users where inventory_id=".ToSQL($id,"integer");
	 $db->query($sql,__FILE__,__LINE__);

	 header("Location: inventory.php");
	 exit;
	}



    $T = new iTemplate("./templates",array("page"=>"inventory_item.html"));
    if ($err) $T->set_var("err", $err); else $T->set_var("error", "");//$T->set_var("err",$err);
	$T->set_var("enable",$enable);

	$sql = "SELECT PERM_USER_PROFILE FROM lookup_users_privileges WHERE privilege_id = " . GetSessionParam("privilege_id");
	$db->query($sql,__FILE__,__LINE__);
	if ($db->next_record()) {
		$perm_user_profile = $db->f("PERM_USER_PROFILE");
	} else {
		exit($db->Error);
	}

	if (!$perm_user_profile) {
		exit("You don't have permission for this!");
	}

	//-- user details
	$T->set_var(array(
		"inventory_title"	=> htmlspecialchars($title),//@$db->Record["inventory_title"],//@$inventory_title,
		"inventory_desc"	=> htmlspecialchars($desc),//@$db->Record["inventory_desc"],//@$inventory_desc,
		"inventory_code"	=> htmlspecialchars($code),//@$db->Record["inventory_code"],//@$inventory_code,
		"date_added"		=> $date,//@$db->Record["date_added"],
		"inventory_id"		=> ((!$id)?-1:$id),
		"urlback"			=> $url,
		"checked"			=> ($guarantee?"checked":"")//@$date_added
		));

	$T->set_var("inventory_type_title", get_options("inventory_types",
	                                                "inventory_type_id",
	                                                "inventory_type_title",
	                                                "inventory_type_title",
	                                                ((!$idtype)?-1:$idtype)
	                                                ));
	$T->set_var("user_name", get_options("users WHERE is_viart=1 AND is_deleted IS NULL",
										 "user_id",
										 "concat(first_name,' ',last_name) as user_name",
										 "user_name",
										 ((!$iduser)?-1:$iduser)
										 ));
	$T->set_var("office_title", get_options("offices",
											"office_id",
											"office_title",
											"office_title",
											((!$idoffice)?-1:$idoffice)
											));


	$T->pparse("page");
?>