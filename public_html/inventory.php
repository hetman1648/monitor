<?php
include("./includes/common.php");
include("./includes/date_functions.php");

CheckSecurity(1);

/*
if (!GetSessionParam("UserID")) {
	header("Location: login.php");
	exit();
}
*/


	$err   = "";
	$where = "";

	$id         = GetParam("inventory_id");
	$f_year	    = GetParam("year_selected");
	$f_office   = GetParam("office_selected");
	$f_username = GetParam("user_selected") ;
	$operation	= GetParam("operation");
	$sort		= GetParam("sort");
	$order		= GetParam("order");
	$page_num	= GetParam("page_num")?GetParam("page_num"):1;

	$records_per_page	= 25;
	$status				= "DISABLED";

	//echo "QUERY_STRING = ".$_SERVER['QUERY_STRING']."<br>";
	//var_dump($_SERVER);

	$where		.= " WHERE ";
	$filterurl	= "";
	if ($operation=="filter")
	{
		if ($f_year || $f_office || $f_username) {			$filterurl	.= "operation=filter";
			if ($f_year) { $where .= " DATE_FORMAT(inv.date_added,'%Y')=$f_year AND "; $filterurl .= "&year_selected=$f_year";}
			if ($f_office) {$where .= " ofc.office_id=".ToSQL($f_office,"integer")." AND "; $filterurl .= "&office_selected=$f_office";}
	    	if ($f_username) {$where .= " inus.user_id=$f_username AND "; $filterurl .= "&user_selected=$f_username";}
		}
	}
	$where .= " 1 ";

	if ($operation=="delete" && $id<>-1) {
		$sql = "DELETE FROM inventory WHERE inventory_id=".ToSQL($id,"integer");
		$db->query($sql);
		$sql = "DELETE FROM inventory_properties WHERE inventory_id=".ToSQL($id,"integer");
		$db->query($sql);
		$sql = "DELETE FROM inventory_users WHERE inventory_id=".ToSQL($id,"integer");
		$db->query($sql);
	}

	$T = new iTemplate("./templates",array("page"=>"inventory.html"));
	if ($err) $T->set_var("err", $err); else $T->set_var("error", "");//$T->set_var("err",$err);

    $sortstr = " GROUP BY user_name, inv.inventory_id ";

	$T->set_var("t_order","0");
	$T->set_var("d_order","0");
	$T->set_var("c_order","0");
	$T->set_var("o_order","0");
	$T->set_var("ad_order","0");
	$T->set_var("u_order","0");
	$T->set_var("g_order","0");

	if ($sort){		switch (strtolower($sort)) {			case "title":
							$sortstr 	.= " ORDER BY inv.inventory_title ";
							$T->set_var("t_order",(int)!$order);
							break;
			case "desc":
							$sortstr	.= " ORDER BY inv.inventory_desc ";
							$T->set_var("d_order",(int)!$order);
							break;
			case "code":
							$sortstr	.= " ORDER BY CAST(inv.inventory_code AS SIGNED) ";
							$T->set_var("c_order",(int)!$order);
							break;
			case "office":
							$sortstr	.= " ORDER BY ofc.office_title ";
							$T->set_var("o_order",(int)!$order);
							break;
			case "date":
							$sortstr	.= " ORDER BY inv_date ";
							$T->set_var("ad_order",(int)!$order);
							break;
			case "user":
							$sortstr	.= " ORDER BY user_name ";
							$T->set_var("u_order",(int)!$order);
							break;
			case "guarant":
							$sortstr	.= " ORDER BY guarantee_exist ";
							$T->set_var("g_order",(int)!$order);
							break;
			//default: $sortstr .= ""; $order = "";
		}

		if (is_numeric($order)) {
			switch ($order) {				case 0 : $sortstr .= " ASC "; break;
				case 1 : $sortstr .= " DESC "; break;
				default: $sortstr .= " ASC ";
			}
		} /*else {			if ($sortstr<>"") {
				$sortstr .= " ASC ";
			}
		}
		*/
	} else {		$sortstr .= " ORDER BY user_name ASC ";
		$T->set_var("u_order","1");	}

    //$T = new iTemplate("./templates",array("page"=>"inventory.html"));
	//if ($err) $T->set_var("err", $err); else $T->set_var("error", "");//$T->set_var("err",$err);

	$sql = "SELECT PERM_USER_PROFILE FROM lookup_users_privileges WHERE privilege_id = " . GetSessionParam("privilege_id");
	$db->query($sql);
	if ($db->next_record()) {
		$perm_user_profile = $db->f("PERM_USER_PROFILE");
	} else {
		exit($db->Error);
	}

	$colpan = 11;
	if (!$perm_user_profile) {
		$T->set_var("control", "");//exit("You don't have permission for this!");
		$colpan -= 3;
		$T->set_var("title_operation", "");
		$T->set_var("control_operation", "");
		$status = "DISABLED";
	}
	$T->set_var("colpan",$colpan);

	//-- user details

	$sql="SELECT inv.inventory_id as inv_id,
				 inv.inventory_title as inv_title,
				 inv.inventory_desc as inv_desc,
				 inv.inventory_code as inv_code,
				 inv.guarantee_exist as guarantee_exist,
				 ofc.office_title as off_title,
				 DATE_FORMAT(inv.date_added, '%d-%m-%Y') as inv_date,
				 concat(u.first_name,' ',u.last_name) as user_name
	        FROM inventory inv
	        	 LEFT JOIN inventory_users AS inus ON inus.inventory_id=inv.inventory_id
	             LEFT JOIN users AS u ON u.user_id=inus.user_id
	             LEFT JOIN offices AS ofc ON ofc.office_id=inv.office_id".$where.$sortstr;
	/*
	$sql="SELECT 	concat(u.first_name,' ',u.last_name) as user_name,
					inv.inventory_title as inv_title,
					inv.inventory_desc as inv_desc,
					inv.inventory_code as inv_code,
					inv.inventory_id as inv_id,
					ofc.office_title as off_title,
					DATE_FORMAT(inv.date_added, '%d-%m-%Y') as inv_date
			FROM	users AS u
					INNER JOIN inventory_users AS inus ON inus.user_id=u.user_id
					INNER JOIN inventory AS inv ON inv.inventory_id=inus.inventory_id
					INNER JOIN offices AS ofc ON ofc.office_id=inv.office_id ".$where.$sortstr;;
	*/
    //echo $sql."<br>";

    $db->query("SELECT COUNT(*) as count FROM (".$sql.") xxx");
    $db->next_record();
    $page_col	= (int)ceil($db->Record['count']/$records_per_page);
    parse_str($_SERVER['QUERY_STRING']."&".$filterurl, $QUERY_STRING);
    $query = "";
    if (is_array($QUERY_STRING)) {
    	$a = array_unique($QUERY_STRING);
    	foreach ($QUERY_STRING as $key => $val) { $query .= "&$key=$val";}
    }
    $T->set_var("query_string",$query);
    $T->set_var("pages_navigator", getPageLinks($page_col, $page_num, $query));
    $limit		= "LIMIT ".($page_num - 1)*$records_per_page.", ".$records_per_page;
    $sql		.= $limit;
	$db->query($sql);

    $a = 0;
    if ($db->nf()>0) {    	$user_name 		 = "";
    	$print_user_name = "";
        while ($db->next_record()) {        	if (htmlspecialchars($db->Record["user_name"])) {	        	if ($user_name == htmlspecialchars($db->Record["user_name"])) {$print_user_name = "";}
	        	 else {	        	 	$user_name = htmlspecialchars($db->Record["user_name"]);
	        	 	$print_user_name = "<b>".htmlspecialchars($db->Record["user_name"])."</b>";
	        	 }
	        } else {$print_user_name = "<i>unassigned</i>";}
			$T->set_var(array(
							  "colorrow"		=> (($a++)%2 == 1)?"DataRow2":"DataRow3",
							  "numeric"			=> ($a+($page_num-1)*$records_per_page),
							  "inventory_title"	=> htmlspecialchars($db->Record["inv_title"]),
							  "inventory_desc"	=> htmlspecialchars($db->Record["inv_desc"]),
							  "inventory_code"	=> htmlspecialchars($db->Record["inv_code"]),
							  "office_title"	=> htmlspecialchars($db->Record["off_title"]),
							  "user_name_inv"	=> $print_user_name,//htmlspecialchars($db->Record["user_name"]),
							  "inventory_id"	=> $db->Record["inv_id"],
							  "date_added"		=> $db->Record["inv_date"],
							  "checked"			=> ($db->Record["guarantee_exist"]?"checked":""),
							  "status"			=> $status,
							  "page_num"		=> $page_num));
	        $T->parse("inventory_orders",true);
		}
    }else {
		$T->set_var("inventory_orders","");
    }
    $sql = "select min(DATE_FORMAT(date_added,'%Y')) as mindate,
    			   max(DATE_FORMAT(date_added,'%Y')) as maxdate
    		from inventory";
    $db->query($sql);
    $db->next_record();
    $T->set_var("years", GetYearOptions($db->Record["mindate"],
    									$db->Record["maxdate"],
    									((!$f_year)?-1:$f_year)
    									));

    $T->set_var("offices", Get_Options("offices",
    								   "office_id",
    								   "office_title",
    								   "office_title",
    								   ((!$f_office)?-1:$f_office)
    								   ));

	$T->set_var("users", Get_Options("users WHERE is_viart=1 AND is_deleted IS NULL",
    								   "user_id",
    								   "concat(first_name,' ',last_name) as user_name",
    								   "user_name",
    								   ((!$f_username)?"":$f_username)
    								   ));

	//$T->set_var("privileges",get_options("lookup_users_privileges","privilege_id","privilege_desc",$privilege_group,""));
	/*
	$T->set_var("user_name",GetSessionParam("UserName"));
	$T->set_var("error", "");
	if ($user_id) {
		$T->set_var("params", "?user_id=" . $user_id);
	} else {
		$T->set_var("params", "");
	}
*/
	//show calendar (eq style.display)
	//$T->set_var("display_start_calendar","");
	$T->pparse("page");

function getPageLink($i, $query)
{
	global $_SERVER;
	global $page_num;


	//$query = $query//$_SERVER['QUERY_STRING'];
	$s = preg_replace('/&page_num=\d+/', '', $query);
	$s .= '&page_num='.$i;


	if ($i == $page_num) {
		return $i;
	} else {
		return "<a href='".$_SERVER["PHP_SELF"]."?" .$s. "'>" .$i. "</a>";
	}

}

function getPageLinks($page_col, $page_num, $query)
{
	if ($page_col == 1) return '';
	$html_result = '';

	if ($page_col < 20)
	{
		for ($i = 1; $i <= $page_col; $i++) {$html_result .= ' '.getPageLink($i, $query);}
		if (strlen($html_result)) { $html_result = "Page ".$html_result;}
		return $html_result;
	}
	if ($page_num <= 8)
	{
		for ($i = 1; $i <= max($page_num + 2, 5); $i++) $html_result .= ' '.getPageLink($i, $query);
		$html_result .= ' ... ';
		for ($i = $page_col - 4; $i <= $page_col; $i++) $html_result .= ' '.getPageLink($i, $query);
	}
	elseif (($page_num > 8) && ($page_num <= $page_col - 8))
	{
		for ($i = 1; $i <= 5; $i++) $html_result .= ' '.getPageLink($i, $query);
		$html_result .= ' ... ';
		for ($i = $page_num - 2; $i <= $page_num+2; $i++) $html_result .= ' '.getPageLink($i, $query);
		$html_result .= ' ... ';
		for ($i = $page_col-4; $i <= $page_col; $i++) $html_result .= ' '.getPageLink($i, $query);
	}
	elseif ($page_num > $page_col - 7)
	{
		for ($i = 1; $i <= 5; $i++) $html_result .= ' '.getPageLink($i);
		$html_result .= ' ... ';
		for ($i = $page_num - 2; $i <= $page_col; $i++) $html_result .= ' '.getPageLink($i, $query);
	}
    if (strlen($html_result)) { $html_result = "Page ".$html_result;}
	return $html_result;
}


?>