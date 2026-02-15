<?php
include("./includes/common.php");

$block = GetParam("block");

$sFileName = "sayu_blocks.php";
$sTemplateFileName = "sayu_blocks.html";
$T= new iTemplate($sAppPath,array("main"=>$sTemplateFileName));
$T->set_var("FileName", $sFileName);

switch ($block) {
	case "client_spent_hours":
		$client_id = GetParam("client_id");
		$content = client_spent_hours($client_id);
	echo $content;
	break;

	case "subprojects":
		$project_id = (int)GetParam("project_id");
		$subproject_id = (int)GetParam("subproject_id");
		$input_name = GetParam("input_name");
		$content = subprojects($input_name, $project_id, $subproject_id);
		header("Content-Type: text/xml");
	echo $content;
	break;
	
	default:
	break;
}

function client_spent_hours($client_id) {
	global $T, $db;
	
	$sql = "SELECT u.first_name, u.last_name, SUM(spent_hours) AS sum";
	$sql .= " FROM clients_spent_hours c JOIN users u ON u.user_id = c.user_id";
	$sql .= " WHERE client_id = ".ToSQL($client_id, "integer")." GROUP BY c.user_id";
	$sql .= " ORDER BY SUM(spent_hours) DESC";
	$db->query($sql);
	if ($db->next_record()) {
		$total = 0;
		
		do {
			$T->set_var("first_name", $db->Record["first_name"]);
			$T->set_var("last_name", $db->Record["last_name"]);
			$T->set_var("hours", to_hours($db->Record["sum"]));
			$T->parse("user");
			$total += $db->Record["sum"];
		} while ($db->next_record());
		
		$T->set_var("total", to_hours($total));
		$T->parse("client_spent_hours");
		return $T->get_var("client_spent_hours");
	} else {
		return "No information";
	}
}

function subprojects($input_name, $project_id, $subproject_id) {
	global $T, $db;
	
	$T->set_var("input_name", $input_name);
	if ($project_id != 0) {
		$sql = "SELECT * FROM projects WHERE parent_project_id=".ToSQL($project_id,"integer");
		//$db->query($sql);
		$T->set_var("subproject_list", Get_Options("projects WHERE parent_project_id=".ToSQL($project_id,"integer")." ORDER BY project_title",
										"project_id",
										"project_title",
										$subproject_id,
										"project_title"
										));
	} else {
		$T->set_var("subproject", "");
	}
	
	$T->rparse("subprojects", false, false);
	return $T->get_var("subprojects");
}


?>