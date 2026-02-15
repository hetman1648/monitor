<?php
	include("./includes/common.php");
	CheckSecurity(1);
	$edit=GetEditPerm();
	
	$datestr = GetParam("menu_date");
	$qty_first   = GetParam("first_course_qty");
	$qty_second  = GetParam("second_course_qty");
	$qty_garnish = GetParam("garnish_qty");
	$qty_salad   = GetParam("salad_qty");
	$user_id = GetParam("user_id") ? GetParam("user_id") : GetSessionParam("UserID");
	
	$order_id=0;
	$db->query("SELECT id_menu, menu_date, DATE_FORMAT( NOW( ) , '%Y-%m-%d' ) FROM lunches_menu ORDER BY menu_date DESC LIMIT 1 ");
	if($db->next_record())
	{
		$max_menu_id=$db->f("id_menu");
		$max_menu_date=$db->f("menu_date");			
	}
	
	if (strlen($datestr)==10)
	{
	  $sql = "SELECT id_menu FROM lunches_menu WHERE menu_date='".addslashes($datestr)."'";	  
	  $db->query($sql);
	  if ($db->next_record()) $menu_id = $db->f("id_menu");
	}
	if (!isset($menu_id)) $menu_id=$max_menu_id;

	if ($menu_id==$max_menu_id && (($max_menu_date==date("Y-m-d") && date("G")<13) || ($max_menu_date>date("Y-m-d")) )) $order_time=true; else $order_time=false;
	
 	if ($menu_id>0)
 	{
	  $sql = "SELECT id_order FROM lunches_orders WHERE id_menu=".ToSQL($menu_id,"integer")." AND user_id=".ToSQL($user_id,"integer");
	  $db->query($sql);
	  if ($db->next_record()) $order_id = $db->f("id_order");
	   	
	  if (strlen($datestr)==10) $err = checkfields(); else $err="";
	  if ($menu_id>0 && strlen(GetParam("first_course_qty")) || strlen(GetParam("second_course_qty"))
	  		 || strlen(GetParam("garnish_qty")) || strlen(GetParam("salad_qty")))

		if($order_id>0)
		{
			$sql =" UPDATE lunches_orders SET user_id=".ToSQL($user_id,"integer").", order_time=NOW(), id_menu=".ToSQL($menu_id,"integer")." , ";
			$sql.=" first_course_qty = ".(int)$qty_first.", second_course_qty = ".(int)$qty_second.", ";
			$sql.=" garnish_qty = ".(int)$qty_garnish.", salad_qty = ".(int)$qty_salad;
			$sql.=" WHERE id_order=".ToSQL($order_id,"integer")." AND user_id=".ToSQL($user_id,"integer");
			$db->query($sql);						
		}
		else 
		{
			$sql ="INSERT INTO lunches_orders (user_id, order_time, id_menu, first_course_qty, second_course_qty, garnish_qty, salad_qty) ";
			$sql.="VALUES (".ToSQL($user_id,"integer").", NOW(), ".ToSQL($menu_id,"integer").", ";
			$sql.= (int)$qty_first." , ".(int)$qty_second." , ".(int)$qty_garnish." , ".(int)$qty_salad.")";
			$db->query($sql);
			$order_id=true;
		}	
	  
	}
	$T = new iTemplate("./templates",array("page"=>"lunches_print.html"));

	FormShow($user_id, $menu_id, $order_id, $err, $edit);

  	$T->pparse("page");

function FormShow($user_id, $menu_id, $order_exists, $error_message, $edit_perms=true)
{
	global $T;
	global $db;	
	global $order_time;

	if ($edit_perms || ($user_id==GetSessionParam("UserID") && $order_time  )) $edit_order=true; else $edit_order=false;

	if ($edit_order) $T->set_var("readonly",""); else $T->set_var("readonly","readonly");
	
	$T->set_var("error_message",$error_message);
	
	if ($menu_id>0)
	{
		$db->query("SELECT CONCAT(first_name, ' ', last_name) AS user_name FROM users WHERE user_id=".ToSQL($user_id,"integer"));
		$db->next_record();
		$T->set_var("user_name",$db->Record["user_name"]);

		$db->query("SELECT * FROM lunches_menu WHERE id_menu=".$menu_id);
		$db->next_record();
		$rec=$db->Record;
		
		$T->set_var("date_title",$rec["menu_date"]);
		$T->set_var("first_course_title",$rec["first_course_title"]);
		$T->set_var("second_course_title",$rec["second_course_title"]);
		$T->set_var("garnish_title",$rec["garnish_title"]);
		$T->set_var("salad_title",$rec["salad_title"]);
		
		$T->set_var("first_course_price",(float)$rec["first_course_price"]);
		$T->set_var("second_course_price",(float)$rec["second_course_price"]);
		$T->set_var("garnish_price",(float)$rec["garnish_price"]);
		$T->set_var("salad_price",(float)$rec["salad_price"]);
		
		$T->set_var("calendar_link","");
		$num_courses=0;
		if (strlen($rec["first_course_title"]))	$num_courses++; else {$T->set_var("col_first","");	$T->set_var("u_col_first","");	$T->set_var("t_col_first","");}
		if (strlen($rec["second_course_title"]))$num_courses++; else {$T->set_var("col_second","");	$T->set_var("u_col_second","");	$T->set_var("t_col_second","");}
		if (strlen($rec["garnish_title"]))	$num_courses++; else {$T->set_var("col_garnish","");	$T->set_var("u_col_garnish","");$T->set_var("t_col_garnish","");}
		if (strlen($rec["salad_title"]))	$num_courses++; else {$T->set_var("col_salad","");	$T->set_var("u_col_salad","");	$T->set_var("t_col_salad","");}
		$T->set_var("num_courses",(int)$num_courses);
		$T->set_var("num_courses_plus_one",(int)$num_courses+1);
//		$T->parse("col_first",false);	$T->parse("col_second",false);	$T->parse("col_garnish",false);	$T->parse("col_salad",false);
		$T->parse("couses_header",false);

		//orders for each user;
		$sql = "SELECT CONCAT(first_name,' ', last_name) AS user_name, first_course_qty, second_course_qty, garnish_qty, salad_qty, ";
		$sql.=" first_course_price, second_course_price, garnish_price, salad_price ";
		$sql.=" FROM users, lunches_orders, lunches_menu WHERE users.user_id=lunches_orders.user_id AND lunches_orders.id_menu=lunches_menu.id_menu ";
		$sql.=" AND lunches_menu.id_menu=".ToSQL($menu_id,"integer")." ORDER BY user_name";
		
		$db->query($sql);
		if ($db->num_rows())
		{
			$total_orders=$db->num_rows();
			while ($db->next_record())  
			{
				$T->set_var("order_user",$db->f("user_name"));
				$T->set_var("order_qty_first",$db->f("first_course_qty")>0 ? $db->f("first_course_qty") : "&nbsp;");
			//	$T->parse("col_first",false);
				$T->set_var("order_qty_second",$db->f("second_course_qty")>0 ? $db->f("second_course_qty") : "&nbsp;");
				$T->set_var("order_qty_garnish",$db->f("garnish_qty")>0 ? $db->f("garnish_qty") : "&nbsp;");
				$T->set_var("order_qty_salad",$db->f("salad_qty")>0 ? $db->f("salad_qty") : "&nbsp;");
				$T->set_var("order_total_cost",sprintf("%2.2f",$db->f("first_course_qty")*$db->f("first_course_price")+
					$db->f("second_course_qty")*$db->f("second_course_price")+$db->f("garnish_qty")*$db->f("garnish_price")+
					$db->f("salad_qty")*$db->f("salad_price")  ));
				$T->parse("users_orders",true)	;
			}
		
			//total orders
			$sql = "SELECT SUM(first_course_qty) AS s1, SUM(second_course_qty) AS s2, SUM(garnish_qty) AS sg, SUM(salad_qty) AS ss, ";
			$sql.=" first_course_price, second_course_price, garnish_price, salad_price ";
			$sql.=" FROM lunches_orders, lunches_menu WHERE lunches_orders.id_menu=lunches_menu.id_menu ";
			$sql.=" AND lunches_menu.id_menu=".ToSQL($menu_id,"integer")." GROUP BY lunches_menu.id_menu";
			
			$db->query($sql);
			$db->next_record();
						
			$T->set_var("ttl_order_users_count", (int)$total_orders);
			$T->set_var("ttl_order_qty_first", $db->f("s1"));
			$T->set_var("ttl_order_qty_second",$db->f("s2"));
			$T->set_var("ttl_order_qty_garnish",$db->f("sg"));
			$T->set_var("ttl_order_qty_salad",$db->f("ss"));
			$T->set_var("ttl_order_cost",sprintf("%2.2f",$db->f("s1")*$db->f("first_course_price")+
					$db->f("s2")*$db->f("second_course_price")+$db->f("sg")*$db->f("garnish_price")+
					$db->f("ss")*$db->f("salad_price")));
		   $T->parse("table_day_orders",false);
		} else {
		   $T->set_var("table_day_orders","");
		}

		$sql ="SELECT first_course_qty, second_course_qty, garnish_qty, salad_qty, first_course_price*first_course_qty AS fc_p, second_course_price*second_course_qty AS sc_p, ";
		$sql.=" garnish_price*garnish_qty AS g_p, salad_price*salad_qty AS s_p FROM lunches_orders o, lunches_menu m WHERE o.id_menu=m.id_menu ";
		$sql.=" AND m.id_menu=$menu_id AND o.user_id=".ToSQL($user_id,"integer");
		$db->query($sql);
		$db->next_record();
		$rec2=$db->Record;

		$T->set_var("user_first_course_qty",(int)$rec2["first_course_qty"]);
		$T->set_var("user_second_course_qty",(int)$rec2["second_course_qty"]);
		$T->set_var("user_garnish_qty",(int)$rec2["garnish_qty"]);
		$T->set_var("user_salad_qty",(int)$rec2["salad_qty"]);
		
		$T->set_var("user_first_course_price",sprintf("%2.2f",(float)$rec2["fc_p"]));
		$T->set_var("user_second_course_price",sprintf("%2.2f",(float)$rec2["sc_p"]));
		$T->set_var("user_garnish_price",sprintf("%2.2f",(float)$rec2["g_p"]));
		$T->set_var("user_salad_price",sprintf("%2.2f",(float)$rec2["s_p"]));

		if ($edit_order) $T->set_var("disabled",""); else $T->set_var("disabled","disabled");

		if (!strlen($rec["first_course_title"])) $T->set_var("tr_first","");	else $T->parse("tr_first",false);
		if (!strlen($rec["second_course_title"])) $T->set_var("tr_second","");	else $T->parse("tr_second",false);
		if (!strlen($rec["garnish_title"])) $T->set_var("tr_garnish","");	else $T->parse("tr_garnish",false);
		if (!strlen($rec["salad_title"])) $T->set_var("tr_salad","");		else $T->parse("tr_salad",false);
				
		if ($order_exists) $T->set_var("submit_title","Update Order"); else $T->set_var("submit_title","Order");

		if ($edit_order) $T->parse("submit_order",false); else {$T->set_var("submit_order",""); }
		
	
		$T->parse("table_user_order",false);
	} else {
		$T->set_var("table_day_orders","");
		$T->set_var("table_user_order","");
		header("location: ".str_replace('#','',$_SERVER["HTTP_REFERER"]));

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