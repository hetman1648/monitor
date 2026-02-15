<?php
include("./includes/common.php");
include("./includes/date_functions.php");

CheckSecurity(1);

    $typeinventory = array_change_key_case(	array(	"user_name"		=> "user_name",
    												"cpu"			=> "cpu",
						    						"memory amount" => "memory",
						    						"video"			=> "video",
						    						"hdd"			=> "hdd",
						    						"monitor"		=> array("manufacturer"	=> "manufacturer", "size" => "size")),
						    				CASE_LOWER);

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

    $status	= "DISABLED";
	$records_per_page	= 25;

	//echo $_SERVER['QUERY_STRING']."<br>";

	//$where .= " WHERE ";
	if ($operation=="filter")
	{
		if ($f_year || $f_office || $f_username) {
			if ($f_year) { $where .= " DATE_FORMAT(inv.date_added,'%Y')=$f_year AND ";}
			if ($f_office) {$where .= " ofc.office_id=".ToSQL($f_office,"integer")." AND ";}
	    	if ($f_username) {$where .= " AND u.user_id=$f_username ";}
		}
	}
	$where .= " AND 1 ";

	if ($operation=="delete" && $id<>-1) {
		$sql = "DELETE FROM inventory WHERE inventory_id=".ToSQL($id,"integer");
		$db->query($sql,__FILE__,__LINE__);
		$sql = "DELETE FROM inventory_properties WHERE inventory_id=".ToSQL($id,"integer");
		$db->query($sql,__FILE__,__LINE__);
		$sql = "DELETE FROM inventory_users WHERE inventory_id=".ToSQL($id,"integer");
		$db->query($sql,__FILE__,__LINE__);
	}

	$T = new iTemplate("./templates",array("page"=>"inventory_report.html"));
	if ($err) $T->set_var("err", $err); else $T->set_var("error", "");//$T->set_var("err",$err);

    $sortstr = " GROUP BY user_name, inv.inventory_id ";

	$T->set_var("t_order","0");
	$T->set_var("d_order","0");
	$T->set_var("c_order","0");
	$T->set_var("o_order","0");
	$T->set_var("ad_order","0");
	$T->set_var("u_order","0");

	if ($sort){
		switch (strtolower($sort)) {
			case "cpu":
							$sortstr 	.= " ORDER BY inv.inventory_title ";
							$T->set_var("t_order",(int)!$order);
							break;
			case "hdd":
							$sortstr	.= " ORDER BY inv.inventory_desc ";
							$T->set_var("d_order",(int)!$order);
							break;
			case "memory":
							$sortstr	.= " ORDER BY CAST(inv.inventory_code AS SIGNED) ";
							$T->set_var("c_order",(int)!$order);
							break;
			case "video":
							$sortstr	.= " ORDER BY ofc.office_title ";
							$T->set_var("o_order",(int)!$order);
							break;
			case "monitor":
							$sortstr	.= " ORDER BY inv_date ";
							$T->set_var("ad_order",(int)!$order);
							break;
			case "user":
							$sortstr	.= " ORDER BY user_name ";
							$T->set_var("u_order",(int)!$order);
							break;
			//default: $sortstr .= ""; $order = "";
		}

		if (is_numeric($order)) {
			switch ($order) {
				case 0 : $sortstr .= " ASC "; break;
				case 1 : $sortstr .= " DESC "; break;
				default: $sortstr .= " ASC ";
			}
		} /*else {
			if ($sortstr<>"") {
				$sortstr .= " ASC ";
			}
		}
		*/
	} else {
		$sortstr .= " ORDER BY user_name ASC ";
		$T->set_var("u_order","1");
	}

    //$T = new iTemplate("./templates",array("page"=>"inventory.html"));
	//if ($err) $T->set_var("err", $err); else $T->set_var("error", "");//$T->set_var("err",$err);

	$sql = "SELECT PERM_USER_PROFILE FROM lookup_users_privileges WHERE privilege_id = " . GetSessionParam("privilege_id");
	$db->query($sql,__FILE__,__LINE__);
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
	}
	$T->set_var("colpan",$colpan);


    $sql = "SELECT u.user_id AS userid, CONCAT(u.first_name,' ',u.last_name) AS user_name FROM users AS u
    		WHERE u.user_id in (SELECT DISTINCT(invus.user_id) FROM inventory_users invus WHERE invus.user_id>-1)".$where."
    		ORDER BY user_name";

    $db->query("SELECT COUNT(*) as count FROM (".$sql.") xxx",__FILE__,__LINE__);
    $db->next_record();
    $page_col	= (int)ceil($db->Record['count']/$records_per_page);
    $T->set_var("pages_navigator", getPageLinks($page_col, $page_num, $_SERVER['QUERY_STRING']));
    $limit	= " LIMIT ".($page_num - 1)*$records_per_page.", ".$records_per_page;
    $sql	.= $limit;
	$db->query($sql,__FILE__,__LINE__);
	$num = 0;
	if ($db->nf()>0) {
		$dbinv = new DB_Sql();
		$dbinv->Database = DATABASE_NAME;
		$dbinv->User     = DATABASE_USER;
		$dbinv->Password = DATABASE_PASSWORD;
		$dbinv->Host     = DATABASE_HOST;

		while ($db->next_record()) {
			$inventorys = array("ID"		=> $db->Record["userid"],
								"user_name" => $db->Record["user_name"]);
			$sqlinv = "SELECT 	inv_prt.inventory_property_name AS name,
								inv_prt.inventory_property_value AS value
						FROM 	inventory_properties  AS inv_prt,
								inventory AS inv,
								inventory_users AS inv_us
						WHERE	inv.inventory_id=inv_prt.inventory_id
								AND inv_us.inventory_id=inv.inventory_id
								AND inv_us.user_id=".ToSQL($db->Record["userid"],"integer");
			$dbinv->query($sqlinv,__FILE__,__LINE__);
			while ($dbinv->next_record()) {
				$key = strtolower($dbinv->Record["name"]);
				$i=0;
				while (array_key_exists($key,$inventorys)) {
					$i++;
					$key = strtolower($dbinv->Record["name"]."_".($i<10?"0".$i:$i));
				}
				$inventorys = array_merge($inventorys, array($key => htmlspecialchars($dbinv->Record["value"])));
			}

			if (array_key_exists("manufacturer",$inventorys)){
				//echo $inventorys["manufacturer"]."<br>";
				$inventorys["monitor"]=$inventorys["manufacturer"]." ".(array_key_exists("size",$inventorys)?$inventorys["size"]:"");
			} else {$inventorys["monitor"]="";}

			foreach($inventorys as $key => $value){
				if (array_key_exists($key."_01",$inventorys)){
					$i=1;
					$newkey = $key."_01";
					while (array_key_exists($newkey,$inventorys)){
						if ($key<>"manufacturer"){
							$inventorys[$key] .="<br>".$inventorys[$newkey];
							unset($inventorys[$newkey]);
						} else {
							$inventorys["monitor"] .= "<br>".$inventorys["manufacturer"."_".($i<10?"0".$i:$i)]." ".$inventorys["size"."_".($i<10?"0".$i:$i)];
		    				unset($inventorys["manufacturer"."_".($i<10?"0".$i:$i)]);
							unset($inventorys["size"."_".($i<10?"0".$i:$i)]);
						}
						$i++;
						$newkey = $key."_".($i<10?"0".$i:$i);
					}
				}
			}
			if (isset($inventorys["memory amount"])) {
				$inventorys["memory"] = $inventorys["memory amount"];
			} else {
				$inventorys["memory"] = "";
			}
			$guarantee = "";//(SELECT SUM(i.guarantee_exist) FROM inventory AS i, inventory_users AS iu WHERE i.inventory_id=iu.inventory_id AND iu.user_id=".ToSQL($inventorys["ID"],"integer").") as sumguarante
			$sqlinv = "SELECT	inv.guarantee_exist,
								invt.inventory_type_title
						FROM	inventory AS inv
								LEFT JOIN inventory_types AS invt ON invt.inventory_type_id=inv.inventory_type_id,
								inventory_users AS invu
						WHERE	inv.inventory_id=invu.inventory_id
								AND invu.user_id=".ToSQL($inventorys["ID"],"integer");
			$dbinv->query($sqlinv,__FILE__,__LINE__);
			if ($dbinv->num_rows()>0) {
				$guarantee .= "<center>";
				while($dbinv->next_record()) {
					$guarantee .= "<input type=\"checkbox\" ";
					$guarantee .= ($dbinv->Record["guarantee_exist"]?"\"checked\" ":" ");
					$guarantee .= "alt=\"".$dbinv->Record["inventory_type_title"]."\" DISABLED>&nbsp;";
				}
				$guarantee .= "</center>";
			}
			unset($inventorys["memory amount"]);
			unset($inventorys["manufacturer"]);
			unset($inventorys["size"]);
			unset($inventorys["ID"]);
			$T->set_var(array(	"colorrow"	=> (($num++)%2 == 1)?"DataRow2":"DataRow3",
			   					"numeric"	=> ($num+($page_num-1)*$records_per_page),
			   					"guarantee"	=> $guarantee,
			   					"status"	=> $status,
			   					"page_num"	=> $page_num ));
			$T->set_var($inventorys);
			$T->set_var("control_operation","");
			$T->parse("inventory_orders",true);
			unset($inventorys);
		}// end while for users
		unset($dbinv);
	} else {
		$T->set_var("inventory_orders","");
	}

    $T->set_var("title_operation","");
    $T->set_var("colpan","8");
    $T->set_var("control","");

	/*
    $sql = "select min(DATE_FORMAT(date_added,'%Y')) as mindate,
    			   max(DATE_FORMAT(date_added,'%Y')) as maxdate
    		from inventory";
    $db->query($sql,__FILE__,__LINE__);
    $db->next_record();
    /*
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
    */
	$T->set_var("users", Get_Options("users WHERE is_viart=1 AND is_deleted IS NULL",
    								   "user_id",
    								   "concat(first_name,' ',last_name) as user_name",
    								   "user_name",
    								   ((!$f_username)?"":$f_username)
    								   ));


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

	if ($html_result<>'') {$html_result = "Page ".$html_result;}
	return $html_result;
}

?>