<?php
include("./includes/common.php");

CheckSecurity(10);

	$err	= "";
	$enable	= "DISABLED";
	$url	= $HTTP_REFERER;

	$operation = GetParam("operation");
	$title	   = GetParam("office_title");
	$address   = GetParam("office_address");
	$id		   = GetParam("office_id");

	$operation = GetParam("operation");


	if ($operation=="add" && $id=="-1")//add new office
	{
		/*
		$title	 = GetParam("edit_office_title");
		$address = GetParam("edit_office_address");
		*/

		if (!$title) { $err.= "<b>Office title</b> is required<br>";}
		if (!$address) { $err.= "<b>Office address</b> is required<br>";}

		if (!$err){
			$sql="insert into offices(office_title,
								  office_address)
						   values(".ToSQL($title,"string").",
						   		  ".ToSQL($address,"string").")";
			$db->query($sql,__FILE__,__LINE__);
			unset($title);
			unset($address);
			header("Location: inventory_offices.php");
			exit();
		}
	}

	if ($operation=="edit" && $id<>"-1")//edit old office
	{
		if (!$title) { $err.= "<b>Office title</b> is required<br>";}
		if (!$address) { $err.= "<b>Office address</b> is required<br>";}

		if (!$err){
			$sql="update offices set office_title=".ToSQL($title,"string").
		                     ", office_address=".  ToSQL($address,"string").
		                     " where office_id=".ToSQL($id,"integer");
			$db->query($sql,__FILE__,__LINE__);
			unset($title);
			unset($address);
			header("Location: inventory_offices.php");
			exit();
		}
	}

	if ($operation=="delete" && $id<>"-1")//edit old office
	{
		$sql="delete from offices where office_id=".ToSQL($id,"integer");
		$db->query($sql,__FILE__,__LINE__);
		header("Location: inventory_offices.php");
		exit();
	}

	if ($operation=="view" && $id<>"-1")//edit old office
	{	$operation = "edit";
		$enable	   = "";	if ($id)
		{		$sql="select * from offices where office_id=".ToSQL($id,"integer");
			$db->query($sql,__FILE__,__LINE__);
	        $db->next_record();

			$title	 = $db->Record["office_title"];
			$address = $db->Record["office_address"];
		}
	}

	if (GetParam("submit") == "Add")//new office
	{
		$operation = "add";
		$title	   = "";
		$address   = "";
		$id		   = -1;
	}


    $T = new iTemplate("./templates",array("page"=>"inventory_office.html"));
	if ($err) $T->set_var("err", $err);else $T->set_var("error", "");//$T->set_var("err",$err);

	$T->set_var("operation", $operation);
	$T->set_var("enable",$enable);

	$T->set_var(array(
					  "edit_office_title"	=> htmlspecialchars($title),
					  "edit_office_address" => htmlspecialchars($address),
					  "office_id"  		    => ((!$id)?-1:$id),
					  "urlback"				=> $url
					  ));

	$T->pparse("page");

?>