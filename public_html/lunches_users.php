<?php
	include("./includes/common.php");	
	CheckSecurity(1);
	
	$arr = $_POST;
	if (sizeof($arr))
	{
		$db->query("UPDATE lunches_allocated_people SET view=null, edit=null");
		$uq="";
		foreach ($arr as $key=>$value)
		{
		  list($field,$id)=split("_",$key);
		  if ($id>0 && ($field=="edit" || $field=="view"))
		  {
		    $db->query("UPDATE lunches_allocated_people SET ".$field."=1 WHERE user_id=".$id);		    
		  }

		}
	}

	$T = new iTemplate("./templates",array("page"=>"lunches_users.html"));

	$sql = "SELECT u.user_id, CONCAT(first_name,' ',last_name) AS user_name, view, edit FROM users u ";
	$sql.= "NATURAL JOIN lunches_allocated_people l WHERE u.is_deleted IS NULL ORDER BY edit DESC, view DESC, user_name ASC";
	
	$db->query($sql);
	while ($db->next_record())
	{
		$T->set_var("user_name",$db->f("user_name"));
		$T->set_var("id", $db->f("user_id"));
		$T->set_var("v_checked", $db->f("view") ? "checked" : "");
		$T->set_var("e_checked", $db->f("edit") ? "checked" : "");
		if (GetSessionParam("UserID")==$db->f("user_id") && $db->f("edit")) $submit_row=true;
		$T->parse("people_records",true);
	}
	if ($submit_row) $T->parse("submit_row",false); else $T->set_var("submit_row","");
	
	$T->pparse("page");
?>