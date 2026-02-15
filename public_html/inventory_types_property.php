<?php
include("./includes/common.php");

CheckSecurity(10);

	$err	= "";
	$enable	= "DISABLED";
	$url	= $HTTP_REFERER;

	$operation = GetParam("operation");
	$title	   = GetParam("type_property_name");
	$desc	   = GetParam("type_property_desc");
	$value	   = GetParam("type_property_value");
	$idtype	   = GetParam("inventory_type_title");
	$id		   = GetParam("property_id");
	$f_type	   = GetParam("filterclause");

	$operation = GetParam("operation");


	if ($operation=="add" && $id=="-1")//add new office
	{
		if (!$title) { $err.= "<b>Type Property name</b> is required<br>";}

		if (!$err){
			$sql="insert into inventory_types_properties(type_property_name,
														 type_property_desc,
														 type_property_value_default,
														 inventory_type_id)
												  values(".ToSQL($title,"string").",
														 ".ToSQL($desc,"string").",
														 ".ToSQL($value,"string").",
														  ".ToSQL($idtype,"integer").")";
			$db->query($sql,__FILE__,__LINE__);
			unset($title);
			unset($desc);
			unset($value);
			unset($idtype);
			header("Location: inventory_types_properties.php");
			exit();
		}
	}

	if ($operation=="edit" && $id<>"-1")//edit old office
	{
		if (!$title) { $err.= "<b>Type Property name</b> is required<br>";}

		if (!$err){
			$sql="update inventory_types_properties set type_property_name=".		 ToSQL($title,"string").
										 			", type_property_desc=".		 ToSQL($desc,"string").
										 			", type_property_value_default=".ToSQL($value,"string").
										 			", inventory_type_id=".			 ToSQL($idtype,"integer").
		                     " where type_property_id=".ToSQL($id,"integer");
			$db->query($sql,__FILE__,__LINE__);
			unset($title);
			unset($desc);
			unset($value);
			unset($idtype);
			header("Location: inventory_types_properties.php");
			exit();
		}
	}

	if ($operation=="delete" && $id<>"-1")//edit old office
	{
		$sql="delete from inventory_types_properties where type_property_id=".ToSQL($id,"integer");
		$db->query($sql,__FILE__,__LINE__);
		header("Location: inventory_types_properties.php");
		exit();
	}

	if ($operation=="view" && $id<>"-1")//edit old office
	{	$operation = "edit";
		$enable	   = "";	if ($id)
		{		$sql="select * from inventory_types_properties where type_property_id=".ToSQL($id,"integer");
			$db->query($sql,__FILE__,__LINE__);
	        $db->next_record();

			$title	 = $db->Record["type_property_name"];
			$desc	 = $db->Record["type_property_desc"];
			$value	 = $db->Record["type_property_value_default"];
			$idtype	 = $db->Record["inventory_type_id"];
		}
	}

	if (GetParam("submit") == "Add")//new office
	{
		$operation = "add";
		$title	   = "";
		$address   = "";
		$value	   = "";
		$idtype	   = $f_type;//-1;
		$id		   = -1;
	}


    $T = new iTemplate("./templates",array("page"=>"inventory_types_property.html"));
	if ($err) $T->set_var("err", $err);else $T->set_var("error", "");//$T->set_var("err",$err);

	$T->set_var("operation", $operation);
	$T->set_var("enable",$enable);

	$T->set_var(array(
					  "edit_type_property_title"	=> htmlspecialchars($title),
					  "edit_type_property_desc"		=> htmlspecialchars($desc),
					  "edit_type_property_value"	=> htmlspecialchars($value),
					  "property_id"				  	=> ((!$id)?-1:$id),
					  "urlback"						=> $url
					  ));

	$T->set_var("edit_inventory_type_title", Get_Options("inventory_types",
														 "inventory_type_id",
														 "inventory_type_title",
														 "inventory_type_title",
														 $idtype
														));

	$T->pparse("page");

?>