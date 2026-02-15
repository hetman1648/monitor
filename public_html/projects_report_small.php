<?php
	include("./includes/common.php");
	
	$t = new iTemplate($sAppPath);
	$t->set_file("main", "projects_report_small.html");
	
	CheckSecurity(1);

	$year_selected   = GetParam("year_selected");
	$month_selected  = GetParam("month_selected");
	$person_selected = GetParam("person_selected");
	$submit = GetParam("submit");
	if (!$year_selected)  $year_selected = date("Y");
	if (!$month_selected) $month_selected = date("m");
	$team = GetParam("team");

	$as="";$vs="";$ys="";
	switch (strtolower($team))
	{
	  case "all":	$sqlteam=""; $as="selected"; break;
	  case "viart":	$sqlteam=" AND u.is_viart=1 "; $vs="selected"; break;
	  case "yoonoo":$sqlteam=" AND u.is_viart=0 "; $ys="selected"; break;
	  default:	$sqlteam=" AND u.is_viart=1 "; $vs="selected"; $team="viart";
	}
	
	$t->set_var("months", GetMonthOptions($month_selected));
	$t->set_var("years", GetYearOptions(2004, date("Y"), $year_selected));
	$t->set_var("aselected",$as); $t->set_var("vselected",$vs); $t->set_var("yselected",$ys); $t->set_var("team_selected",$team);
	$t->set_var("person_selected",$person_selected ? $person_selected : "0");	
	$sql  = " SELECT user_id, is_viart, CONCAT(first_name,' ', last_name) as person ";
	$sql .= " FROM users u ";
	$sql .= " WHERE is_deleted IS NULL ORDER BY person";
	$db->query($sql,__FILE__,__LINE__);
	$id=0; $people = "";
	while ($db->next_record()) {
		$t->set_var("ID",(int)$id++);
		$t->set_var("IDteam",(int)$db->f("is_viart"));
		$t->set_var("IDuser",(int)$db->f("user_id"));
		$t->set_var("user_name",$db->f("person"));
		$t->parse("PeopleArray",true);
	}
	$t->set_var("people", $people);
	
	
	
	
	if (strlen($submit)) {
		$sql  = " SELECT tr.user_id, p.project_id, p.project_title,";
		$sql .= " CONCAT(u.first_name,' ', u.last_name) as person, ";
		$sql .= " SUM(tr.spent_hours) AS sum_hours ";
		$sql .= " FROM (((tasks t ";
		$sql .= " INNER JOIN time_report tr ON tr.task_id=t.task_id)";
		$sql .= " INNER JOIN projects p ON t.project_id = p.project_id) ";
		$sql .= " INNER JOIN users u ON u.user_id = tr.user_id) ";
		$sql .= " WHERE tr.spent_hours>0 " . $sqlteam;
		if ($year_selected)   $sql .= " AND YEAR(tr.started_date)='$year_selected' ";
		if ($month_selected)  $sql .= " AND MONTH(tr.started_date)='$month_selected' ";
		if ($person_selected) $sql .= " AND tr.user_id=$person_selected ";
		$sql .= " GROUP BY tr.user_id, p.project_id ";
		$sql .= " ORDER BY tr.user_id, sum_hours DESC";
		$db->query($sql);
		$prev_user_id = 0;
		if($db->next_record()) {
			do {
				$user_id = $db->f("user_id");
				if ($prev_user_id != $user_id) {
					if ($prev_user_id) {
						$t->parse("user_block");
						$t->set_var("project_row", "");				
					}
					$t->set_var("user_id", $user_id);
					$t->set_var("user", $db->f("person"));
					$prev_user_id = $user_id;
				}
				
				$t->set_var("project_id", $db->f("project_id"));
				$t->set_var("project_title", $db->f("project_title"));
				$t->set_var("sum_hours", to_hours($db->f("sum_hours")));
				$t->parse("project_row");
			} while($db->next_record());
			$t->parse("user_block");
			$t->parse("records");
		} else {
			$t->set_var("records", "");
		}		
	} else {
		$t->set_var("records", "");
	}
	
	$t->pparse("main");
?>