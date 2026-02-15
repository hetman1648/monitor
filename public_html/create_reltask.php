<?php

	include("./includes/common.php");

	if (getsessionparam("privilege_id") == 9) {
		header("Location: index.php");
		exit;
	}

	CheckSecurity(1);

	// = GetParam("");//
	$cancelb	= GetParam("cancelb");
	$action		= GetParam("action");
	$release_id = GetParam("release_id");
	$task_id	= GetParam("task_id");
	$end_date	= GetParam("end_date")?GetParam("end_date"):"0000-00-00";
	$start_date	= GetParam("start_date")?GetParam("start_date"):"0000-00-00";
	$total_days	= GetParam("total_days")?GetParam("total_days"):"0";
	$task_desc	= GetParam("task_desc")?GetParam("task_desc"):"No description.";
	$task_title	= GetParam("task_title");
	$task_priority		= GetParam("priority");
	$selected_user		= GetParam("selected_user");
	$selected_release	= GetParam("selected_release");

	$checkfail = "";

	$T = new iTemplate("./templates", array("page"=>"create_reltask.html"));

	if (!$release_id) {
		if (!$task_id) {exit("Form vars lost!");}
		$sql = "SELECT * FROM tasks WHERE task_id = ".ToSQL($task_id,"integer");
		$db->query($sql,__FILE__,__LINE__);
		$db->next_record();
		$release_id = $db->Record["release_id"];
	}

	$sql = "SELECT * FROM project_releases WHERE release_id = ".ToSQL($release_id,"integer");
	$db->query($sql,__FILE__,__LINE__);
	$db->next_record();
	$project_id = $db->Record["project_id"];

	if ($cancelb == "Cancel") {
		header("Location: view_releases.php?project_id=" . $project_id);
	}

	if ($action) {
		$task_is_wish = 1; // Edition needed!!!
		if ($task_title == "") {
			$checkfail .= "The value in field <font color=\"red\"><b>Task Title</b></font> is required.<br>";
		}
		if (!$checkfail) {
			if ($action == "Add task") {
				// Adding new reltask SQL				
				$new_task_id = add_task($selected_user_id, $task_priority, 0, $project_id, 0,
						$task_title, $task_desc, $end_date, GetSessionParam('UserID'), $total_days, 1, false, $task_is_wish);
				update_task($new_task_id, array("release_id"=>$selected_release));
			} elseif ($action == "Edit task") {
				update_task($task_id, array("planed_date"=>$end_date, "estimated_hours"=>$total_days,
							"task_title"=>$task_title, "task_desc"=>$task_desc, "priority_id"=>$task_priority,
							"release_id"=>$selected_release, "is_wish"=>$task_is_wish, "responsible_user_id"=>$selected_user));
			}
			header("Location: view_releases.php?project_id=" . $project_id);
		}
	}

	if ($task_id) {
		//ToSQL( // ,"integer") // ,"string") // ,"date")
		$sql = "SELECT * FROM tasks WHERE task_id = ".ToSQL($task_id,"integer");
		$db->query($sql,__FILE__,__LINE__);
		$db->next_record();
        $butvalue	= "Edit task";
        $task_title	= $db->Record["task_title"];
        $start_date	= substr($db->Record["creation_date"], 0, 10);
        $end_date	= substr($db->Record["planed_date"], 0, 10);
        $total_days	= $db->Record["estimated_hours"];
        $priority	= $db->Record["priority_id"];
        $task_desc	= $db->Record["task_desc"];
        $setaction	= "create_reltask.php?task_id=" . $task_id;
        $user		= $db->Record["responsible_user_id"];

	} else {
		$butvalue	= "Add task";
		$setaction	= "create_reltask.php?release_id=" . $release_id;
		$priority	= "1";
		$user		= "";
		$task_desc	= "";
	}
	$T->set_var(array(
			"action" 	=> $setaction,
			"butvalue" 	=> $butvalue,
			"start_date"=> $start_date,
			"task_title"=> $task_title,
			"end_date" 	=> $end_date,
			"total_days"=> $total_days,
			"priority" 	=> $priority,
			"descr" 	=> $task_desc
	));

	$user_list = "<option value='0'>No user alocated</option>";
	$user_list .= GetOptions("users", "user_id", "CONCAT(first_name,' ',last_name)",$user,"");
	$release_list = GetOptions("project_releases", "release_id", "title", $release_id, "WHERE project_id=".ToSQL($project_id,"integer"));
	$T->set_var(array(	"release_options"	=> $release_list,
						"user_options"		=> $user_list
					));
	if ($checkfail) {
		$T->set_var("checkfail",$checkfail);
		$T->parse("error",false);
	} else {$T->set_var("error","");}

	$T->parse("set_action", false);
	$T->pparse("page", false);
	$T->parse("formtable",false);
?>