<?php

	include_once("./includes/common.php");

	CheckSecurity(1);
	
	$month = intval(GetParam("month"));
	$year = intval(GetParam("year"));
	$subsidy = intval(GetParam("subsidy"));
	//echo "Subsidy:".$subsidy;
	
	if ($month < 1 || $month > 12) $month = date("n");
	if ($year <= 0) $year = date("Y");

	$sql = "SELECT CONCAT(first_name, ' ', last_name) AS user_name, users.user_id, DAYOFMONTH(menu_date) AS menu_day, ";
	$sql.= "first_course_qty * first_course_price + second_course_qty * second_course_price + garnish_qty * garnish_price + salad_qty * salad_price + dessert_qty * dessert_price AS du ";
	$sql.= "FROM users NATURAL JOIN lunches_orders NATURAL JOIN lunches_menu WHERE MONTH(menu_date)=$month AND YEAR(menu_date)=$year ORDER BY user_name, menu_day";

	$db->query($sql);
	$e = array();
	$e_u = array();
	$e_d = array();
	$e_u_t = array();
	$e_d_t = array();
	while ($db->next_record())
	{
		$user_id=(int)$db->f("user_id");
		$day = (int)$db->f("menu_day");
		$e_u[$user_id]=$db->f("user_name");
		$e_d[$day]=$day;
		$e[$user_id][$day]=$db->f("du");		
	}

	$sql = "SELECT CONCAT(first_name, ' ', last_name) AS user_name, users.user_id ".
		"FROM users NATURAL JOIN lunches_allocated_people WHERE view=1 AND (is_deleted IS NULL OR is_deleted=0) ORDER BY user_name ASC ";
	$db->query($sql);
	while ($db->next_record())
	{
		$user_id=(int)$db->f("user_id");
		$e_u[$user_id]=$db->f("user_name");
	}

		
	sort($e_d);
	$daycount = sizeof($e_d);
	
	$T = new iTemplate("./templates", array("page" => "lunches_statistics.html"));

	if ($subsidy == 0) $subsidy=2000;
	$T->set_var("headcolspan", $daycount+3);
	$T->set_var("subsidy", $subsidy);
	
	$this_year = intval(date('Y'));
	for ($j=$this_year-1; $j<=$this_year+1; $j++) { 
		$T->set_var("this_year", $j);
		$T->parse("year_block", true);	
	}
	
	foreach ($e_d as $day)
	{
		$T->set_var("dayofmonth", $day);
		$T->set_var("menu_date", $year . "-" . ($month < 10 ? "0" . $month : $month) . "-" . ($day < 10 ? "0" . $day : $day));
		$T->parse("days_header", true);
		foreach ($e_u as $id=>$name)
		{
			$e_d_t[$day]+=$e[$id][$day];
			$e_u_t[$id]+=$e[$id][$day];
		}
	}
	
	$ttl = 0;
	foreach ($e_d as $day)
	{
		$T->set_var("day_total_price",sprintf("%4.2f",$e_d_t[$day]));
		$ttl+=$e_d_t[$day];
		$T->parse("days_footer",true);
	}
	$T->set_var("ttl",sprintf("%4.2f",$ttl));
	
	if ($ttl!=0)
	$discount_per = $subsidy/$ttl;
	//echo $discount_per;
	$T->set_var("discount", number_format($discount_per*100, 0, ".", ","));
	
	$total_price_discount = 0;
	foreach ($e_u as $id => $name)
	{
		$T->set_var("user_name", $name);
		$T->set_var("user_id", $id);
		$T->set_var("days_records", "");
		foreach ($e_d as $day)
		{
			$ec = 0;
			if (isset($e[$id][$day]))
			{	    	
				if ($e[$id][$day]<=4.5) $ec = 1;
				elseif ($e[$id][$day]<=7.5) $ec = 2;
				elseif ($e[$id][$day]<=10.5) $ec = 3;
				elseif ($e[$id][$day]>10.5) $ec = 4;
			}
			$T->set_var("ec", $ec);
			$T->set_var("menu_date", $year . "-" . ($month < 10 ? "0" . $month : $month) . "-" . ($day < 10 ? "0" . $day : $day));
			$T->parse("days_records", true);	    
		}
		$T->set_var("user_total_price", sprintf("%4.2f", $e_u_t[$id]));
		$price_discount = $e_u_t[$id]-($e_u_t[$id]*$discount_per);
		$total_price_discount += $price_discount;
	//	echo "<br>".$e_u_t[$id]-($e_u_t[$id]*$discount_per)."<br>";
		$T->set_var("price_discount", sprintf("%4.2f", $price_discount));
		$T->parse("people_records", true);
	}
	
	$T->set_var("total_price_discount", sprintf("%4.2f", $total_price_discount));
	if (sizeof($e) > 0)
	{
		$T->parse("records",false);
		$T->set_var("no_records","");
	} else {
		$T->set_var("records","");
		$T->parse("no_records",false);
	}	

	$T->set_var("month",$month);
	$T->set_var("year",$year);
	
  	$T->pparse("page");

?>