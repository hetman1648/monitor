<?php
include("./includes/common.php");

CheckSecurity(10);

	$err	= "";
	$enable	= "DISABLED";
	$url	= $HTTP_REFERER;

	$operation = GetParam("operation");
	$title	   = GetParam("type_title");
	$desc	   = GetParam("type_desc");
	$id		   = GetParam("type_id");

	$operation = GetParam("operation");


	if ($operation=="add" && $id=="-1")//add new office
	{
		if (!$title) { $err.= "<b>Type title</b> is required<br>";}
		if (!$desc)  { $err.= "<b>Type description</b> is required<br>";}

		if (!$err){
			$sql="insert into inventory_types(inventory_type_title,
											  inventory_type_desc)
						   values(".ToSQL($title,"string").",
						   		  ".ToSQL($desc,"string").")";
			$db->query($sql,__FILE__,__LINE__);
			unset($title);
			unset($desc);
			header("Location: inventory_types.php");
			exit();
		}
	}

	if ($operation=="edit" && $id<>"-1")//edit old office
	{
		if (!$title) { $err.= "<b>Type title</b> is required<br>";}
		if (!$desc)  { $err.= "<b>Type description</b> is required<br>";}

		if (!$err){
			$sql="update inventory_types set inventory_type_title=".ToSQL($title,"string").
										 ", inventory_type_desc=".  ToSQL($desc,"string").
		                     " where inventory_type_id=".ToSQL($id,"integer");
			$db->query($sql,__FILE__,__LINE__);
			unset($title);
			unset($desc);
			header("Location: inventory_types.php");
			exit();
		}
	}

	if ($operation=="delete" && $id<>"-1")//edit old office
	{
		$sql="delete from inventory_types where inventory_type_id=".ToSQL($id,"integer");
		$db->query($sql,__FILE__,__LINE__);
		header("Location: inventory_types.php");
		exit();
	}

	if ($operation=="view" && $id<>"-1")//edit old office
	{	$operation = "edit";
		$enable	   = "";	if ($id)
		{		$sql="select * from inventory_types where inventory_type_id=".ToSQL($id,"integer");
			$db->query($sql,__FILE__,__LINE__);
	        $db->next_record();

			$title	 = $db->Record["inventory_type_title"];
			$desc	 = $db->Record["inventory_type_desc"];
		}
	}

	if (GetParam("submit") == "Add")//new office
	{
		$operation = "add";
		$title	   = "";
		$address   = "";
		$id		   = -1;
	}


    $T = new iTemplate("./templates",array("page"=>"inventory_type.html"));
	if ($err) $T->set_var("err", $err);else $T->set_var("error", "");//$T->set_var("err",$err);

	$T->set_var("operation", $operation);
	$T->set_var("enable",$enable);

	$T->set_var(array(
					  "edit_type_title"	=> htmlspecialchars($title),
					  "edit_type_desc"  => htmlspecialchars($desc),
					  "type_id"			=> ((!$id)?-1:$id),
					  "urlback"			=> $url
					  ));

	$T->pparse("page");

?>