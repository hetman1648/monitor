<?php
	include_once("./includes/common.php");
	include_once("./includes/date_functions.php");

	CheckSecurity(1);

	$dbu = new DB_Sql();
	$dbu->Database = DATABASE_NAME;
	$dbu->User     = DATABASE_USER;
	$dbu->Password = DATABASE_PASSWORD;
	$dbu->Host     = DATABASE_HOST;

	$err   = "";
	$where = "";
	$action = "";

	$records_per_page	= 25;

	$operation	= GetParam("operation");
	$sort		= GetParam("sort");
	$order		= GetParam("order");
	$page_num	= GetParam("page_num")?GetParam("page_num"):1;
	$filterurl	= "";
	$showreport	= GetParam("showreport");
	$user		= GetParam("user");

	$T = new iTemplate("./templates",array("page"=>"managing_reports.html"));

	/**
	*	Filter. begin
	*/
	$period_selected	= GetParam("period_selected");
	$manager_selected	= GetParam("manager_selected");
	$start_date			= GetParam("start_date");
	$end_date			= GetParam("end_date");
	$person_selected	= GetParam("person_selected");
	$submit				= GetParam("submit");
	$team				= GetParam("team");

	$as="";$vs="";$ys="";

	switch (strtolower($team)){
		case "all":		$sqlteam = ""; $as = "selected"; break;
		case "viart":	$sqlteam = " AND u.is_viart=1 "; $vs = "selected"; break;
		case "yoonoo":	$sqlteam = " AND u.is_viart=0 "; $ys = "selected"; break;
		default:		$sqlteam = " AND u.is_viart=1 "; $vs = "selected"; $team = "viart";
	}

	if ($team) { $filterurl .= "&team=$team";}

	if ($period_selected) { $filterurl .= "&period_selected=$period_selected";}
	elseif (!$period_selected && !$submit) { $period_selected = "this_month";}

	$T->set_var("periods", GetPeriodOption($period_selected));
	//$T->set_var("period", $period);

	$T->set_var("aselected", $as);
	$T->set_var("vselected", $vs);
	$T->set_var("yselected", $ys);
	$T->set_var("team_selected", $team);

	$current_date = va_time();

	list($sdt,$edt)=get_start_end_period ($period_selected,$start_date,$end_date);
	$filterurl .= "period_selected=".$period_selected."&start_date=".$start_date."&end_date=".$end_date;
	$T->set_var("period_selected", $period_selected);
	$sqldate = " AND (DATE(mr.date_added) BETWEEN
						DATE_FORMAT('".$sdt."','%Y-%m-%d') AND
						DATE_FORMAT('".$edt."','%Y-%m-%d') ) ";



	$sqluser	= "";
	if ($person_selected) {
		$sqluser	.= " AND u.user_id=".ToSQL($person_selected,"integer")." ";
		$filterurl	.= "&person_selected=$person_selected";
	}
	$sqlmanager	= "";
	if ($manager_selected) {		$sqlmanager	.= " AND mu.user_id=".ToSQL($manager_selected,"integer")." ";
		$filterurl	.= "&manager_selected=$manager_selected";	}


    $where .= $sqlteam.$sqluser.$sqlmanager;

	$T->set_var("person_list", 	Get_Options("users u WHERE is_deleted IS NULL ".$sqlteam."AND (manager_id>0".($manager_selected?" AND manager_id=".ToSQL($manager_selected,"integer"):"").")",
											"user_id",
											"CONCAT(first_name,' ',last_name) as user_name",
											"user_name",
											($person_selected ? $person_selected:-1)
											));

	$T->set_var("manager_list",	Get_Options("users u WHERE is_deleted IS NULL AND privilege_id=4".$sqlteam,
											"user_id",
											"CONCAT(first_name,' ',last_name) as user_name",
											"user_name",
											($manager_selected ? $manager_selected:-1)
											));

	/**
	*        Filter. end
	*/
	/**
	*        Sort. start
	*/
	$sortstr = " GROUP BY user_name ";

	$T->set_var("u_order","0");
	$T->set_var("n_order","0");
	$T->set_var("m_order","0");
	$T->set_var("d_order","0");
	$T->set_var("mr_order","0");
	$T->set_var("er_order","0");

	if ($sort){
		switch (strtolower($sort)) {
			case "user":
							$sortstr 	.= " ORDER BY user_name ";
							$T->set_var("u_order",(int)!$order);
							break;
			case "note":
							$sortstr	.= " ORDER BY note ";
							$T->set_var("n_order",(int)!$order);
							break;
			case "manager":
							$sortstr	.= " ORDER BY manager ";
							$T->set_var("m_order",(int)!$order);
							break;
			case "date_added":
							$sortstr	.= " ORDER BY date_added ";
							$T->set_var("d_order",(int)!$order);
							break;
			case "morning":
							$sortstr	.= " ORDER BY morning_report ";
							$T->set_var("mr_order",(int)!$order);
							break;
			case "evening":
							$sortstr	.= " ORDER BY evening_report ";
							$T->set_var("er_order",(int)!$order);
							break;
			//default: $sortstr 	.= " ORDER BY user_name "; $T->set_var("u_order","1"); $order
		}

		if (is_numeric($order)) {
			switch ($order) {
				case 0 : $sortstr .= " ASC "; break;
				case 1 : $sortstr .= " DESC "; break;
				default: $sortstr .= " ASC ";
			}
		}
	} else {
		$sortstr .= " ORDER BY date_added DESC ";
		$T->set_var("d_order","0");
	}
	/**
	*        Sort. end
	*/


    $colspan = 6;
    if (GetSessionParam("privilege_id") != 4) {
    	$colspan--;
    	$T->set_var("control_view");
    }
    //else { $T->parse("control_view",false);}
    $T->set_var("colspan",$colspan);

    $sql = "SELECT PERM_USER_PROFILE FROM lookup_users_privileges WHERE privilege_id = " . GetSessionParam("privilege_id");
	$db->query($sql,__FILE__,__LINE__);
	if ($db->next_record()) {
		$perm_user_profile = $db->f("PERM_USER_PROFILE");
	} else {
		exit($db->Error);
	}

	$sql = "SELECT	mr.report_id AS report_id,
					u.user_id AS user_id,
					u.manager_id AS manager_id,
					IFNULL(mr.points,0) AS points,
					CONCAT(u.first_name,' ',u.last_name) AS user_name,
					CONCAT(mu.first_name,' ',mu.last_name) AS manager_name,
					IF(mr.morning_notes is not NULL,1,0) as morning_notes,
					IF(mr.evening_notes is not NULL,1,0) as evening_notes,
					DATE(mr.date_added) as date_added
			FROM	users AS u
					LEFT JOIN users AS mu ON (mu.user_id=u.manager_id)
					LEFT JOIN managing_reports AS mr ON (mr.user_id=u.user_id ".$sqldate.")
			WHERE	1
					AND u.manager_id > 0
					/*AND mu.manager_id<>0*/
					AND u.is_deleted is NULL".$where."
			ORDER BY manager_name, user_name";
	$db->query($sql,__FILE__,__LINE__);
	if ($db->num_rows()>0) {		$T->parse("header_report",false);
		$user_report = array();
		$index = 0;
		$manager_name = "";
		$user_name = "";
		if (GetSessionParam("privilege_id") == 4) { $manager_id = GetSessionParam("UserID");}
		else { $manager_id = -1;}
		while ($db->next_record()) {
			if ($user_name && $user_name != $db->Record["user_name"]){
				$index++;
				$user_name = $db->Record["user_name"];
				$user_report[$index]["report_morning"]	= 0;
				$user_report[$index]["report_evening"]	= 0;
				$user_report[$index]["report_points"]	= 0;
				$user_report[$index]["report_user_id"]	= $db->Record["user_id"];
				$user_report[$index]["report_user_name"]= $db->Record["user_name"];
				$user_report[$index]["report_manager_id"]	= $db->Record["manager_id"];
				$user_report[$index]["report_manager_name"]	= $db->Record["manager_name"];
			} elseif (!$user_name) {
				$user_name = $db->Record["user_name"];
				$user_report[$index]["report_morning"]	= 0;
				$user_report[$index]["report_evening"]	= 0;
				$user_report[$index]["report_points"]	= 0;
				$user_report[$index]["report_user_id"]	= $db->Record["user_id"];
				$user_report[$index]["report_user_name"]= $db->Record["user_name"];
				$user_report[$index]["report_manager_id"]	= $db->Record["manager_id"];
				$user_report[$index]["report_manager_name"]	= $db->Record["manager_name"];
			}
			if (!$user_report[$index]["report_manager_name"]) {
				$user_report[$index]["report_manager_name"] = "<i>no manager</i>";
				$user_report[$index]["report_manager_id"]	= 0;
			}
			$user_report[$index]["report_morning"] += $db->Record["morning_notes"];
			$user_report[$index]["report_evening"] += $db->Record["evening_notes"];
			$user_report[$index]["report_points"] += $db->Record["points"];		}
		//echo PrintArray($user_report)."<br>";
		for ($i=0; $i<sizeof($user_report); $i++) {			if ($manager_id == $user_report[$i]["report_manager_id"]) {
				$user_report[$i]["report_add_new"] = "<a href='managing_reports_edit.php?report_id=-1&user_name=".$user_report[$i]["report_user_id"]."'>Add</a>";
			} else { $user_report[$i]["report_add_new"] = "";}			if (!$manager_name || $manager_name != $user_report[$i]["report_manager_name"]) {
				$manager_name = $user_report[$i]["report_manager_name"];
			}
			elseif ($manager_name == $user_report[$i]["report_manager_name"]) {				$user_report[$i]["report_manager_name"] = "";			}
			if ($user_report[$i]["report_evening"]>0) {
				$user_report[$i]["report_points"] = number_format($user_report[$i]["report_points"] / $user_report[$i]["report_evening"], 2);
				$user_report[$i]["report_evening"] = "<a href='managing_reports.php?".$filterurl."&showreport=evening&user=".$user_report[$i]["report_user_id"]."'>".$user_report[$i]["report_evening"]."</a>";
			}
			if ($user_report[$i]["report_morning"]>0) {
				$user_report[$i]["report_morning"] = "<a href='managing_reports.php?".$filterurl."&showreport=morning&user=".$user_report[$i]["report_user_id"]."'>".$user_report[$i]["report_morning"]."</a>";
			}
			$user_report[$i]["report_user_name"] = "<a href='managing_reports.php?".$filterurl."&showreport=all&user=".$user_report[$i]["report_user_id"]."'>".$user_report[$i]["report_user_name"]."</a>";
			unset($user_report[$i]["report_manager_id"]);
			unset($user_report[$i]["report_user_id"]);
			$T->set_var($user_report[$i]);
			if (GetSessionParam("privilege_id") != 4) {	$T->set_var("control_add");}
			$T->parse("report",true);		}
		$T->set_var("no_report_records","");	} else {
    	$T->set_var("header_report","");
    	$T->set_var("report","");
    	$T->parse("no_report_records",false);
	}
    if ($showreport){
    	$points_array	= array(0 => "haven't done anything",
								1 => "done something",
								2 => "done most of the plan",
								3 => "done as estimated",
								4 => "done even more than estimated",
								5 => "done twice and more then estimated");
    	$reports = "";

    	$sql = "SELECT	mr.report_id AS report_id,
						mr.user_id AS user_id,
						mr.manager_id AS manager_id,
						mr.points AS points,
						CONCAT(u.first_name,' ',u.last_name) AS user_name,
						CONCAT(mu.first_name,' ',mu.last_name) AS manager_name,
						mr.morning_notes as morning_notes,
						mr.evening_notes as evening_notes,
						DATE(mr.date_added) as date_added
				FROM	managing_reports AS mr
						LEFT JOIN users AS u ON (u.user_id=mr.user_id)
						LEFT JOIN users AS mu ON (mu.user_id=mr.manager_id)
				WHERE	mr.user_id=".ToSQL($user,"integer").$sqldate;
		$sql .= " ORDER BY report_id DESC ";		
		$db->query($sql,__FILE__,__LINE__);

		$color = 0;
		if ($db->num_rows()>0){
	        while ($db->next_record()) {	        	$evening_notes = "";
	        	$morning_notes = "";	        	if ($showreport == "morning") { $morning_notes = $db->Record["morning_notes"];}
	    		elseif ($showreport == "evening") { $evening_notes = $db->Record["evening_notes"];}
	    		elseif ($showreport == "all") {	    			$morning_notes = $db->Record["morning_notes"];
	    			$evening_notes = $db->Record["evening_notes"];	    		}
	    		$user_name = $db->Record["user_name"];
	    		if (strlen($morning_notes)>33) { $morning_notes = substr($morning_notes,0,30)."...";}
	    		if (strlen($morning_notes)>0) { $morning_notes = "<a href='managing_reports_edit.php?report_id=".$db->Record["report_id"]."'>".$morning_notes."</a>";}
	    		if (strlen($evening_notes)>33) { $evening_notes = substr($evening_notes,0,30)."...";}
	    		if (strlen($evening_notes)>0) { $evening_notes = "<a href='managing_reports_edit.php?report_id=".$db->Record["report_id"]."'>".$evening_notes."</a>";}
	    		$T->set_var(array(	"colorclass"			=> (($color++)%2 == 1)?"DataRow2":"DataRow3",
	    							"view_morning_message"	=> $morning_notes,
	    							"view_evening_message"	=> $evening_notes,
						    		"view_points"			=> $points_array[$db->Record["points"]],
						    		"view_date_added"		=> $db->Record["date_added"]
						    		));

	    		if ($showreport == "morning") {
	    			if ($morning_notes) { $T->parse("view_reports_details",true);}
	    		}
	    		elseif ($showreport == "evening") {
	    			if ($evening_notes) { $T->parse("view_reports_details",true);}
	    		}
	    		elseif ($showreport == "all") { $T->parse("view_reports_details",true);}	        }
	        $T->set_var("no_view_reports_details","");
	        $T->set_var("view_user_name",$user_name);
	        $T->parse("view_user_report",false);
		} else {			$T->set_var("view_user_name",$user_name);
			$T->set_var("view_reports_details","");
			$T->parse("no_view_reports_details",false);
			$T->parse("view_user_report",false);		}
    }else { $T->set_var("view_user_report","");}

    $sql = "SELECT	u.user_id,
    				u.is_viart,
    				CONCAT(u.first_name,' ', u.last_name) as person,
    				u.manager_id,
    				u.privilege_id
    		FROM	users u
    		WHERE	u.is_deleted IS NULL
    		ORDER BY person";
	$db->query($sql,__FILE__,__LINE__);
	$id=0;
	while ($db->next_record()) {
		$T->set_var("ID",(int)$id++);
		$T->set_var("IDteam",(int)$db->f("is_viart"));
		$T->set_var("IDuser",(int)$db->f("user_id"));
		$T->set_var("user_name",$db->f("person"));
		if ($db->f("privilege_id") == 4) { $T->set_var("is_manager","1");}
		else { $T->set_var("is_manager","0");}
		$T->set_var("manager_user_id",$db->f("manager_id"));
		$T->parse("PeopleArray",true);
	}

	$T->set_var("error","");
	$T->set_var("action", 'managing_reports.php');

	$T->pparse("page");

/**
	Functions
*/
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

function GetPeriodOption($period_selected)
{
	//$period_option=array("1","2","3","4","5","6","7","8","9");
	$period_option=array("today","yesterday","this_week","last_week","prev_week","this_month","last_month","prev_month","this_year");
	$period_titles=array("Today","Yesterday","This week","Last week (7 days)","Previous week","This month","Last month (30 days)","Previous month","This year");

	$res_str = "";
	for ($i = 0; $i < sizeof($period_option); $i++)	{
		if ($period_selected == $period_option[$i]) $selected = "selected"; else $selected = "";
		$res_str .= "<option $selected value=\"".$period_option[$i]."\">".$period_titles[$i]."</option>\n";
	}
	return $res_str;
}

function get_start_end_period($period_selected,&$start_date,&$end_date)
{
	global $T;

	$current_date = va_time();
	$cyear = $current_date[0];
	$cmonth = $current_date[1];
	$cday = $current_date[2];

	$today_date = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday, $cyear));
	$T->set_var("today_date", $today_date);

	$yesterday_date = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday - 1, $cyear));
	$T->set_var("yesterday_date", $yesterday_date);

	$this_week_start_date = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday - date("w")+1, $cyear));
	$this_week_end_date   = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday, $cyear));
	$T->set_var("this_week_start", $this_week_start_date);
	$T->set_var("this_week_end",   $this_week_end_date);

	$last_week_start_date = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday - 6, $cyear));
	$last_week_end_date   = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday, $cyear));
	$T->set_var("last_week_start", $last_week_start_date);
	$T->set_var("last_week_end",   $last_week_end_date);

	$prev_week_start_date = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday - date("w")-6, $cyear));
	$prev_week_end_date   = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday - date("w"), $cyear));
	$T->set_var("prev_week_start", $prev_week_start_date);
	$T->set_var("prev_week_end",   $prev_week_end_date);

	$prev_month_start_date = date ("Y-m-d", mktime (0, 0, 0, $cmonth - 1, 1, $cyear));
	$prev_month_end_date   = date ("Y-m-t", mktime (0, 0, 0, $cmonth - 1, 1, $cyear));
	$T->set_var("prev_month_start", $prev_month_start_date);
	$T->set_var("prev_month_end",   $prev_month_end_date);

	$last_month_start_date = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday-30, $cyear));
	$last_month_end_date   = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday, $cyear));
	$T->set_var("last_month_start", $last_month_start_date);
	$T->set_var("last_month_end",   $last_month_end_date);

	$this_month_start_date = date ("Y-m-d", mktime (0, 0, 0, $cmonth, 1, $cyear));
	$this_month_end_date   = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday, $cyear));
	$T->set_var("this_month_start", $this_month_start_date);
	$T->set_var("this_month_end",   $this_month_end_date);

	$year_start_date = date ("Y-m-d", mktime (0, 0, 0, 1, 1, $cyear));
	$year_end_date   = date ("Y-m-d", mktime (0, 0, 0, $cmonth, $cday, $cyear));
	$T->set_var("this_year_start", $year_start_date);
	$T->set_var("this_year_end",   $year_end_date);

	if (!$period_selected) $period_selected="today";

	if (!$start_date && !$end_date) {
		switch ($period_selected) {
			case "today":
				$start_date = $today_date;
				$end_date = $today_date;
				break;
			case "yesterday":
				$start_date = $yesterday_date;
				$end_date = $yesterday_date;
				break;
			case "this_week":
				$start_date = $this_week_start_date;
				$end_date = $this_week_end_date;
				break;
			case "last_week":
				$start_date = $last_week_start_date;
				$end_date = $last_week_end_date;
				break;
			case "prev_week":
				$start_date = $prev_week_start_date;
				$end_date = $prev_week_end_date;
				break;
			case "this_month":
				$start_date = $this_month_start_date;
				$end_date = $this_month_end_date;
				break;
			case "last_month":
				$start_date = $last_month_start_date;
				$end_date = $last_month_end_date;
				break;
			case "prev_month":
				$start_date = $prev_month_start_date;
				$end_date = $prev_month_end_date;
				break;
			case "this_year":
				$start_date = $year_start_date;
				$end_date = $year_end_date;
				break;
		}
	}

	$sd = "";
	$ed = "";
	$sdt = "";
	$edt = "";
	if ($start_date) {
		$sd_ar = parse_date(array("YYYY", "-", "MM", "-", "DD"), $start_date, "Start Date");
		$sd_ts = mktime (0, 0, 0, $sd_ar[1], $sd_ar[2], $sd_ar[0]);
		$sdt_ts = mktime (0, 0, 0, $sd_ar[1], $sd_ar[2], $sd_ar[0]);
		$sd = @date("Y-m-d", $sd_ts);
		$sdt = @date("Y-m-d 00:00:00", $sd_ts);
	}
	if ($end_date) {
		$ed_ar = parse_date(array("YYYY", "-", "MM", "-", "DD"), $end_date, "End Date");
		$ed_ts = mktime (0, 0, 0, $ed_ar[1], $ed_ar[2], $ed_ar[0]);
		$ed = @date("Y-m-d", $ed_ts);
		$edt = @date("Y-m-d 23:59:59", $ed_ts);
 	}

 	$T->set_var("start_date", $sd);
	$T->set_var("end_date", $ed);
    /*
	$end_year  =@date("Y",$ed_ts);
	$start_year=@date("m",$ed_ts);
 	$T->set_var("current_year", $end_year);
	$T->set_var("current_month", $start_year);
    */
	return array($sdt,$edt);
}

?>