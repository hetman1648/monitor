<?php
include("./includes/common.php");

CheckSecurity(10);

	$err	= "";
	$enable	= "DISABLED";
	$url	= $_SERVER["HTTP_REFERER"];

	$operation = GetParam("operation");
	$title	   = GetParam("property_name");
	$desc	   = GetParam("property_desc");
	$value	   = GetParam("property_value");
	$idinv	   = GetParam("inventory_title");
	$id		   = GetParam("property_id");
	$f_invent  = GetParam("filterclause");


	if ($operation=="add" && $id=="-1")//add new office
	{
		if (!$title) { $err.= "<b>Property name</b> is required<br>";}
		if (!$value) { $err.= "<b>Property value</b> is required<br>";}

		if (!$err){
			$sql="insert into inventory_properties(inventory_property_name,
												   inventory_property_desc,
												   inventory_property_value,
												   inventory_id)
										    values(".ToSQL($title,"string").",
												   ".ToSQL($desc,"string").",
												   ".ToSQL($value,"string").",
												   ".ToSQL($idinv,"integer").")";
			$db->query($sql,__FILE__,__LINE__);
			unset($title);
			unset($desc);
			unset($value);
			unset($idinv);
			header("Location: inventory_properties.php");
			exit();
		}
	}

	if ($operation=="edit" && $id<>"-1")//edit old office
	{
		if (!$title) { $err.= "<b>Property name</b> is required<br>";}
		if (!$value) { $err.= "<b>Property value</b> is required<br>";}

		if (!$err){
			$sql="update inventory_properties set inventory_property_name=". ToSQL($title,"string").
										 	  ", inventory_property_desc=".  ToSQL($desc,"string").
										 	  ", inventory_property_value=". ToSQL($value,"string").
										 	  ", inventory_id=".			 ToSQL($idinv,"integer").
		                     " where inventory_property_id=".ToSQL($id,"integer");
			$db->query($sql,__FILE__,__LINE__);
			unset($title);
			unset($desc);
			unset($value);
			unset($idinv);
			header("Location: inventory_properties.php");
			exit();
		}
	}

	if ($operation=="delete" && $id<>"-1")//edit old office
	{
		$sql="delete from inventory_properties where inventory_property_id=".ToSQL($id,"integer");
		$db->query($sql,__FILE__,__LINE__);
		header("Location: inventory_properties.php");
		exit();
	}

	if ($operation=="view" && $id<>"-1")//edit old office
	{	$operation = "edit";
		$enable	   = "";	if ($id)
		{		$sql="select * from inventory_properties where inventory_property_id=".ToSQL($id,"integer");
			$db->query($sql,__FILE__,__LINE__);
	        $db->next_record();

			$title	 = $db->Record["inventory_property_name"];
			$desc	 = $db->Record["inventory_property_desc"];
			$value	 = $db->Record["inventory_property_value"];
			$idinv	 = $db->Record["inventory_id"];
		}
	}

	if (GetParam("submit") == "Add")//new office
	{
		$operation = "add";
		$title	   = "";
		$address   = "";
		$value	   = "";
		$idinv	   = $f_invent;//-1;
		$id		   = -1;
	}


    $T = new iTemplate("./templates",array("page"=>"inventory_property.html"));
	if ($err) $T->set_var("err", $err);else $T->set_var("error", "");//$T->set_var("err",$err);

	$T->set_var("operation", $operation);
	$T->set_var("enable",$enable);

	$T->set_var(array(
					  "edit_property_title"			=> htmlspecialchars($title),
					  "edit_type_property_desc"		=> htmlspecialchars($desc),
					  "edit_type_property_value"	=> htmlspecialchars($value),
					  "property_id"					=> ((!$id)?-1:$id),
					  "urlback"						=> $url
					  ));

	$T->set_var("edit_inventory_title", Get_Options("inventory",
													"inventory_id",
													"inventory_title",
													"inventory_title",
													$idinv
													));

	$T->pparse("page");

?>