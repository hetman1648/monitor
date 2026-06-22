<?php

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");



$project_id      = GetParam("project_id");
//sometimes javascript IDs contain extra characters - we remove them to get project_id
$project_id = preg_replace("/[^0-9]/","",$project_id);
$xml_content = "";

if ($project_id && is_numeric($project_id)) {
	$fields = array("project_title"       => "",
		  			"project_status_id"   => "", 
		  			"total_time"          => "",
		  			"responsible_user_id" => ""
		  			);


	$sql = "SELECT ".join(",",array_keys($fields))." FROM projects WHERE project_id=".$project_id;
	$db->query($sql); 


	if ($db->next_record()) {
		foreach ($fields as $field_name => $field_value) {
			# code...
			$field_value = $db->f($field_name);
			$fields[$field_name] = $field_value;
		}
		
		$sql = "SELECT CONCAT(first_name, ' ',last_name) AS user_name, first_name, user_id FROM users ";
		$sql.= " WHERE is_deleted is null AND privilege_id=4 ORDER BY user_name";
		$users = array(); $users_first = array();
		$db->query($sql); 
		while ($db->next_record()) {
		    $users[$db->f("user_id")]       = $db->f("user_name");
		    $users_first[$db->f("user_id")] = $db->f("first_name");
		}
		$fields["users"] = json_encode($users);
		
		$sql = "SELECT * FROM projects_statuses ORDER BY status_desc ";
		$statuses = array();
		$db->query($sql); 
		while ($db->next_record()) {
		    $statuses[$db->f("project_status_id")] = $db->f("status_desc");
		}
		$fields["all_statuses"] = json_encode($statuses);

		$sql = "SELECT * FROM project_notes WHERE project_id = $project_id ORDER BY date_added DESC";
		$notes = array(); $notes_html = "";
		$db->query($sql); 
		$c = 0;
		while ($db->next_record()) {
		    /*$notes[$c] = array (
		    	"date_added" => date('D, jS M Y',strtotime($db->f("date_added"))),
		    	"user"       => $users_first[$db->f("user_id")],
		    	"note"       => $db->f("note"),
		    	"note_id"    => $db->f("note_id"),
		    	"sort_order" => $c
		    );
		    $c++;*/
            $notes_html.= "<tr>-";
            $notes_html.= "<td nowrap>" . date('D, jS M Y',strtotime($db->f("date_added"))) . "</td>";
            $notes_html.= "<td nowrap>" . $users_first[$db->f("user_id")]. "</td>";
            $notes_html.= "<td>"        . $db->f("note"). "</td>";
            $notes_html.= "</tr>";                 
		}
		//$fields["notes"] = json_encode($notes);
		$fields["notes"] = htmlentities($notes_html);


		foreach ($fields as $field_name => $field_value) {
			$xml_content .= "<".$field_name.">";
			$xml_content .= $field_value;
			$xml_content .= "</".$field_name.">\n";

		}

	}



}

header("Content-type: text/xml");
?>
<task>
	<?php echo $xml_content; ?>
</task>
