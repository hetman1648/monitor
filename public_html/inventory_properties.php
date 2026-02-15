<?php
include("./includes/common.php");

CheckSecurity(10);

/*
if (!GetSessionParam("UserID")) {
	header("Location: login.php");
	exit();
}
*/


	$err   = "";
	$where = "";

	$id			= GetParam("inventory_id");
	$name		= GetParam("edit_inventory_property_name");
	$value		= GetParam("edit_inventory_property_value");
	$desc		= GetParam("edit_inventory_property_desc");
	$idinv		= GetParam("edit_inventory_title");
	$idtype		= GetParam("edit_type_property_name");
	$page_num	= GetParam("page_num")?GetParam("page_num"):1;

	$f_invent  = GetParam("inv_selected");

	$operation = GetParam("operation");

	$records_per_page	= 25;

	if ($operation=="filter")
	{	if ($f_invent){
			$where .= " WHERE ";
			if ($f_invent) {$where .= " inv.inventory_id=".ToSQL($f_invent,"integer")." AND ";}
	 		$where .= " 1 ";

	 		unset($name);
			unset($value);
			unset($desc);
			unset($idinv);
			unset($idtype);
		}
	}

	if ($id) {$f_invent = $id;}

	if ($operation=="view" && $id<>"-1")
	{
		//$id    = GetParam("property_id");
		$where = "WHERE inv.inventory_id=".ToSQL($id,"integer");
	}

    $T = new iTemplate("./templates",array("page"=>"inventory_properties.html"));

	$sql = "SELECT PERM_USER_PROFILE FROM lookup_users_privileges WHERE privilege_id = " . GetSessionParam("privilege_id");
	$db->query($sql,__FILE__,__LINE__);
	if ($db->next_record()) {
		$perm_user_profile = $db->f("PERM_USER_PROFILE");
	} else {
		exit($db->Error);
	}

	$colpan = 6;
	if (!$perm_user_profile) {
		$T->set_var("control", "");//exit("You don't have permission for this!");
		$colpan -= 2;
		$T->set_var("title_operation", "");
		$T->set_var("control_operation", "");
	}

	$T->set_var("colpan", $colpan);

	//-- type details

	$sql="SELECT    ip.inventory_property_id AS inventory_property_id,
					ip.inventory_property_name AS inventory_property_name,
					ip.inventory_property_value AS inventory_property_value,
					ip.inventory_property_desc AS inventory_property_desc,
					inv.inventory_title AS inventory_title,
					itp.type_property_name AS type_property_name
			FROM	inventory_properties ip
					LEFT JOIN inventory AS inv ON inv.inventory_id=ip.inventory_id
					LEFT JOIN inventory_types_properties AS itp ON itp.type_property_id=ip.type_property_id ".$where;

	$db->query("SELECT COUNT(*) as count FROM (".$sql.") xxx",__FILE__,__LINE__);
    $db->next_record();
    $page_col	= (int)ceil($db->Record['count']/$records_per_page);
    $T->set_var("pages_navigator", getPageLinks($page_col, $page_num, $_SERVER['QUERY_STRING']));

    $limit		= " LIMIT ".($page_num - 1)*$records_per_page.", ".$records_per_page;
    $sql		.= $limit;

	$a = 0;
	$db->query($sql,__FILE__,__LINE__);
	if ($db->nf()>0){		while ($db->next_record()) {
			$T->set_var(array(
						  "colorrow"				   => (($a++)%2 == 1)?"DataRow2":"DataRow3",
						  "inventory_property_name"    => htmlspecialchars($db->Record["inventory_property_name"]),//$name,//$db->Record["inventory_property_name"],
						  "inventory_property_value"   => htmlspecialchars($db->Record["inventory_property_value"]),//$value,//$db->Record["inventory_property_value"],
						  "inventory_property_desc"    => htmlspecialchars($db->Record["inventory_property_desc"]),//$desc,//$db->Record["inventory_property_desc"],
						  "inventory_title"			   => htmlspecialchars(((strlen($db->Record["inventory_title"])>0)?$db->Record["inventory_title"]:"")),
						  "type_property_name"         => htmlspecialchars(((strlen($db->Record["type_property_name"])>0)?$db->Record["type_property_name"]:"")),
						  "property_id"			  	   => $db->Record["inventory_property_id"]//$id,//$db->Record["inventory_property_id"]
						  ));
			$T->parse("property_orders",true);
		}
	}
	else{
		$T->set_var("property_orders","");
	}

    $T->set_var("inventories",get_options("inventory",
										  "inventory_id",
										  "inventory_title",
										  "inventory_title",
										  ((!$f_invent)?-1:$f_invent)
										  ));

    $T->set_var("filterclause",((!$f_invent)?-1:$f_invent));

	$T->pparse("page");

function getPageLink($i)
{
	global $_SERVER;
	global $page_num;


	$query = $_SERVER['QUERY_STRING'];
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
		for ($i = 1; $i <= $page_col; $i++) {$html_result .= ' '.getPageLink($i);}
		if (strlen($html_result)) { $html_result = "Page ".$html_result;}
		return $html_result;
	}
	if ($page_num <= 8)
	{
		for ($i = 1; $i <= max($page_num + 2, 5); $i++) $html_result .= ' '.getPageLink($i);
		$html_result .= ' ... ';
		for ($i = $page_col - 4; $i <= $page_col; $i++) $html_result .= ' '.getPageLink($i);
	}
	elseif (($page_num > 8) && ($page_num <= $page_col - 8))
	{
		for ($i = 1; $i <= 5; $i++) $html_result .= ' '.getPageLink($i);
		$html_result .= ' ... ';
		for ($i = $page_num - 2; $i <= $page_num+2; $i++) $html_result .= ' '.getPageLink($i);
		$html_result .= ' ... ';
		for ($i = $page_col-4; $i <= $page_col; $i++) $html_result .= ' '.getPageLink($i);
	}
	elseif ($page_num > $page_col - 7)
	{
		for ($i = 1; $i <= 5; $i++) $html_result .= ' '.getPageLink($i);
		$html_result .= ' ... ';
		for ($i = $page_num - 2; $i <= $page_col; $i++) $html_result .= ' '.getPageLink($i);
	}
    if (strlen($html_result)) { $html_result = "Page ".$html_result;}
	return $html_result;
}
?>