<?php
	include("./includes/common.php");
	CheckSecurity(1);
	$edit=GetEditPerm();
	
	$datestr = GetParam("menu_date");
	$first_course_title = GetParam("first_course_title");
	$second_course_title = GetParam("second_course_title");
	$garnish_title = GetParam("garnish_title");
	$salad_title = GetParam("salad_title");
	
	$first_course_price = (float)str_replace(",",".",GetParam("first_course_price"));
	$second_course_price = (float)str_replace(",",".",GetParam("second_course_price"));
	$garnish_price = (float)str_replace(",",".",GetParam("garnish_price"));
	$salad_price = (float)str_replace(",",".",GetParam("salad_price"));

	$menu_id=0;
	if (strlen($datestr)==10)
	{
	  if (GetParam("action")=="delete")
	  {
	  	$db->query("DELETE FROM lunches_menu WHERE menu_date='".addslashes($datestr)."'");
//	  	header("location: lunches_edit_menu.php");
	  }
	  else
	  {
	  $sql="SELECT id_menu FROM lunches_menu WHERE menu_date='".addslashes($datestr)."'";
	  $db->query($sql);
	  if ($db->next_record()) $menu_id=$db->f("id_menu");
 	
	  $err = checkfields();
	  	  
	  if (strlen(GetParam("first_course_title"))>0 || strlen(GetParam("second_course_title"))>0)
	  {
		if($menu_id>0)
		{
			$sql =" UPDATE lunches_menu SET menu_date='".$datestr."' , ";
			$sql.=" first_course_title = ".ToSQL(GetParam("first_course_title"),"text").", first_course_price= ".(float)$first_course_price.", ";
			$sql.=" second_course_title = ".ToSQL(GetParam("second_course_title"),"text").", second_course_price= ".(float)$second_course_price.", ";
			$sql.=" garnish_title = ".ToSQL(GetParam("garnish_title"),"text").", garnish_price= ".(float)$garnish_price.", ";
			$sql.=" salad_title = ".ToSQL(GetParam("salad_title"),"text").", salad_price= ".(float)$salad_price;
			$sql.=" , is_blocked= ".(integer)GetParam("is_blocked");
			$sql.=" WHERE id_menu=".ToSQL($menu_id,"integer");
			//echo $sql;
			$db->query($sql);
		}
		else 
		{
			$sql ="INSERT INTO lunches_menu (menu_date, first_course_title, first_course_price, second_course_title, second_course_price, ";
			$sql.=" garnish_title, garnish_price, salad_title, salad_price, is_blocked) VALUES ('".$datestr."', ";
			$sql.= ToSQL(GetParam("first_course_title"),"text")." , ".(float)$first_course_price." , ";
			$sql.= ToSQL(GetParam("second_course_title"),"text")." , ".(float)$second_course_price." , ";
			$sql.= ToSQL(GetParam("garnish_title"),"text")." , ".(float)$garnish_price." , ";
			$sql.= ToSQL(GetParam("salad_title"),"text")." , ".(float)$salad_price." , ";
			$sql.= (integer)GetParam("is_blocked").") ";
			$db->query($sql);
			
			$db->query("SELECT LAST_INSERT_ID() FROM lunches_menu");
			$db->next_record();
			$menu_id=$db->f("0");
		}
	  } else $err="";
	  }
	}	
	$T = new iTemplate("./templates",array("page"=>"lunches_edit_menu.html"));

	FormShow($menu_id, $err, $edit);

  	$T->pparse("page");

function FormShow($menu_id, $error_message, $edit_perms=true)
{
	global $T;
	global $db;

	if (!$edit_perms) $T->set_var("readonly","readonly"); else $T->set_var("readonly","");
	
	$T->set_var("error_message",$error_message);
	
	if ($menu_id>0)
	{
		$db->query("SELECT * FROM lunches_menu WHERE id_menu=".$menu_id);
		$db->next_record();
		$rec=$db->Record;
		
		$T->set_var("date_title",$rec["menu_date"]);
		$T->set_var("disabled","");
		$T->set_var("first_course_title",$rec["first_course_title"]);
		$T->set_var("second_course_title",$rec["second_course_title"]);
		$T->set_var("garnish_title",$rec["garnish_title"]);
		$T->set_var("salad_title",$rec["salad_title"]);
		
		$T->set_var("first_course_price",$rec["first_course_price"]>0 ? $rec["first_course_price"] : "");
		$T->set_var("second_course_price",$rec["second_course_price"]>0 ? $rec["second_course_price"] : "");
		$T->set_var("garnish_price",$rec["garnish_price"]>0 ? $rec["garnish_price"] : "");
		$T->set_var("salad_price",$rec["salad_price"]>0 ? $rec["salad_price"] : "");
		
		if ($rec["is_blocked"]==1)
			$T->set_var("block_checked", "checked");
				else 
					$T->set_var("block_checked", "");
		
		$T->set_var("submit_title","Update");
	} else {
		$T->set_var("date_title",GetParam("menu_date"));
		$T->set_var("disabled","disabled");
		$T->set_var("first_course_title","");
		$T->set_var("second_course_title","");
		$T->set_var("garnish_title","");
		$T->set_var("salad_title","");
		
		$T->set_var("first_course_price","");
		$T->set_var("second_course_price","");
		$T->set_var("garnish_price","");
		$T->set_var("salad_price","");		
		$T->set_var("block_checked", "");

		$T->set_var("submit_title","Add");
	}  
	if (!$edit_perms) $T->set_var("tr_submit","");
	else
	{ 
	  if (!$menu_id) $T->set_var("delete_button","");
	  $T->parse("tr_submit",false);
	}  
}

function CheckFields()
{
	$err="";
	$date=GetParam("menu_date");
	$first_course_price = str_replace(",",".",GetParam("first_course_price"));
	$second_course_price = str_replace(",",".",GetParam("second_course_price"));
	$garnish_price = str_replace(",",".",GetParam("garnish_price"));
	$salad_price = str_replace(",",".",GetParam("salad_price"));
	
	if (strlen($date))
	{
	  list($year,$month,$day)=split("-",$date);
	  if ($year<2006 || $year>2010 || $month<=0 || $month>12 || $day<=0 || $day>date('t',mktime(0,0,0,$month,$day,$year)) ) $err.="incorrect date<BR>";
	} else $err.="date field is required<BR>";
	if (!strlen(GetParam("first_course_title")) && !strlen(GetParam("second_course_title")) &&
		!strlen(GetParam("garnish_title")) && !strlen(GetParam("salad_title"))) $err.="at least one course is required<BR>";
	if (strlen($first_course_price) && ($first_course_price<=0))  $err.="incorrect price format for first course<BR>";
	if (strlen($second_course_price) && ($second_course_price<=0))  $err.="incorrect price format for second course<BR>";
	if (strlen($garnish_price) && ($garnish_price<=0))  $err.="incorrect price format for garnish<BR>";
	if (strlen($salad_price) && ($salad_price<=0))  $err.="incorrect price format for salad<BR>";
	
	return $err;
}

function getEditPerm()
{
	global $db;
	$db->query("SELECT edit FROM lunches_allocated_people WHERE user_id=".ToSQL(GetSessionParam("UserID"),"integer"));
	$db->next_record();
	return $db->f("edit");
}
?>