<?php

	include("./includes/common.php");
	include("./includes/date_functions.php");

	if (getsessionparam("privilege_id") == 9) {
		header("Location: index.php");
		exit;
	}

	$T = new iTemplate("./templates", array("page"=>"view_releases.html"));
	$T2 = new iTemplate("./templates", array("page"=>"releases_table_view.html"));

	CheckSecurity(1);

	//$= GetParam("");//
	$year_select	= GetParam("year_select");//$_POST["year_select"]
	$month_select	= GetParam("month_select");//$_POST["month_select"]
	$release	= GetParam("release");//$_POST["release"]
	$task_type	= GetParam("task_type");//$_POST["task_type"]
	$operation	= GetParam("operation");//$_POST["operation"]
	$view_mode	= GetParam("view_mode");//$_POST["view_mode"]
	$project_id	= GetParam("project_id");
	$rsearch	= GetParam("rsearch");

	$dicUsers = "";
	$search = "";

	// SECOND DATABASE OBJECT
	$db2 = new DB_Sql;
	$db2->Database = DATABASE_NAME;
	$db2->User     = DATABASE_USER;
	$db2->Password = DATABASE_PASSWORD;
	$db2->Host     = DATABASE_HOST;

	// THIRD DATABASE OBJECT
	$db3 = new DB_Sql;
	$db3->Database = DATABASE_NAME;
	$db3->User     = DATABASE_USER;
	$db3->Password = DATABASE_PASSWORD;
	$db3->Host     = DATABASE_HOST;

	if (($year_select != "Select Year") and (($year_select != ""))) {
		//$search .= " AND tasks.creation_date >= '".$year_select."-01-01' AND tasks.creation_date <= '".$year_select."-12-31'";
		$search .= " AND (tasks.creation_date BETWEEN DATE('".$year_select."-01-01') AND DATE('".$year_select."-12-31'))";
	}
	if (($month_select != "") and ($month_select != "Select Month")) {
		foreach ($month as $key => $value) {
			if ($month_select == $value) { $mn = $key;}
		}
		$search .= " AND MONTH(tasks.creation_date) <= ".$mn." AND MONTH(tasks.planed_date) >= ".$mn;
	}

	if (($release != "") and ($release != "All Releases")) {
		$sql = "SELECT * FROM project_releases WHERE project_id = ".ToSQL($project_id,"integer");
		$db->query($sql,__FILE__,__LINE__);
		while ($db->next_record()) {
			if ($db->Record["title"] == $release) {
				$release_id = $db->Record["release_id"];
				$rsearch .= " AND release_id = ".ToSQL($release_id,"integer");
			}
		}
	}
	if (($task_type != "") and ($task_type != "All Tasks")) {
		$search .= " AND tasks.is_wish = 1";
	}
	////////////////////////////////////////////////////////////////////////////////////////////////////
	if ($operation == "save_priorities") {
		$post_array = array();
		if (isset($HTTP_POST_VARS)) {
			$post_array = $HTTP_POST_VARS;
		} elseif(isset($_POST)) {
			$post_array = $_POST;
		}
		
		$tasks_priorities = array();
		foreach($post_array as $var_name=>$var_value) {
			$parts = split("_",$var_name);
			if ($parts[0] == "priority" && $parts[1]) {
				$task_id  = $parts[1];
        		$priority = $var_value;
        		set_task_priority($task_id, $priority);
				$tasks_priorities[] = $task_id;
        	}
		}

		header("Location: view_releases.php?project_id=".$project_id);
		exit;
	}
	////////////////////////////////////////////////////////////////////////////////////////////////////
	$is_err = false;
	if (($view_mode == "Stat View") and
		(($month_select == "Select Month") or
		 ($month_select == "") or
		 ($year_select == "Select Year") or
		 ($year_select == ""))) {
			$view_mode = "Tasks View";
			$is_err = true;
	}

	if ($view_mode == "Stat View") {
		foreach ($month as $key => $value) {
			if ($month_select == $value) { $mn = $key;}
		}

		$dim = date("t", mktime(0, 0, 0, $mn, 1, $year_select));

		$sql = "SELECT * FROM project_releases WHERE project_id = ".ToSQL($project_id,"integer");
		$db->query($sql);
		while ($db->next_record()) {
			$rel_options .= "<option>".$db->Record["title"]."</option>";
		}

		$T2->set_var("rel_options", $rel_options);
		$T2->parse("filter", false);

	  	$sql = "SELECT project_title FROM projects WHERE project_id=".ToSQL($project_id,"integer");
		$db->query($sql);
		$db->next_record();
		$T2->set_var("proj_name", $db->Record["project_title"]);
		$T2->parse("project_name", false);

		$sql = "SELECT * FROM project_releases WHERE project_id = ".ToSQL($project_id,"integer").$rsearch
		." ORDER BY due_date DESC";
		$db->query($sql);

		if ($db->next_record()) {
			do {
				$sql2 = "SELECT * FROM tasks LEFT JOIN users ON tasks.responsible_user_id ="
				." users.user_id WHERE tasks.is_wish <> 0 AND tasks.release_id = "
				.$db->Record["release_id"].$search." ORDER BY priority_id";
				$db2->query($sql2);
				$dicUsers .= "dicUsers[".$db->Record["release_id"]."] = ";
				$T2->not_parsed["release_tasks"] = true;

				// VIEW TASKS
				$T2->set_var("release_id", $db->Record["release_id"]);


				while ($db2->next_record()) {
					$T2->set_var("task_id_tit", "<A HREF=create_reltask.php?task_id="
					.$db2->Record["task_id"].">".$db2->Record["task_title"]."</A>");

					$td = "";
					$tdr = "";

					for ($i = 1; $i <= $dim; $i++) {

					  	if ($i < 10) $cdate = $year_select."-".$mn."-0".$i;
					  	else $cdate = $year_select."-".$mn."-".$i;
					  	$rty = "";

					  	if (
						  ($cdate >= substr($db2->Record["creation_date"], 0, 10))
						  and
						  ($cdate <= substr($db2->Record["planed_date"], 0, 10))
						  ) $rty = "<img src=\"images/cs.gif\">";
						$td .= "<TD>".$rty."</TD>";
					}
					for ($i = 1; $i <= $dim; $i++)
						if ($i >= 10) $tdr .= "<TD class=\"ColumnTD\">".$i."</TD>";
						else $tdr .= "<TD class=\"ColumnTD\">0".$i."</TD>";

					$T2->set_var("day_number", $dim + 1);
					$T2->set_var("day_numbers", $tdr);
					$T2->set_var("days", $td);
					$T2->parse("release_tasks", true);

				}
				if (($db->Record["due_date"] == NULL) ||
				($db->Record["due_date"] == "0000-00-00"))
				$T2->set_var("release_tit_dat", $db->Record["title"]." (No due date)");
				else $T2->set_var("release_tit_dat", $db->Record["title"]." - ".$tst." ("
				.$db->Record["due_date"].")");

				// "ADD TASK" IN BOTTOM OF EACH
				$T2->set_var("task_id_tit", "<A style=\"text-decoration: underline;\" HREF"
				."=create_reltask.php?release_id=".$db->Record["release_id"].">Add task</A>");

				$T2->set_var("day_number", $dim + 1);
				$T2->set_var("days", "");

				$T2->parse("release_tasks", true);
				$T2->parse("releases", true);
			}
			while ($db->next_record());
		} else foreach ($T2->vars as $key => $value) $T2->vars[$key] = "";

		$T2->set_var("proid", $project_id);
		$T2->parse("pro_id", false);
		$T2->pparse("page");

	} else {
	  	if ($is_err) $T->set_var("error", "<font color=\"red\">Error! Select year and month for Stat View!</font>");
	  		else $T->set_var("error", "");

	  	//$sql = "SELECT * FROM project_releases WHERE project_id = ".ToSQL($project_id,"integer");
		//$db->query($sql);
		//while ($db->next_record()) {
		//	$rel_options .= "<option>".$db->Record["title"]."</option>";
		//}
        $rel_options = GetOptions("project_releases", "release_id", "title", "", "WHERE project_id = ".ToSQL($project_id,"integer"));
		$T->set_var("rel_options", $rel_options);
		$T->parse("filter", false);
	  	$sql = "SELECT project_title FROM projects WHERE project_id=".ToSQL($project_id,"integer");
		$db->query($sql);
		$db->next_record();
		$T->set_var("proj_name", $db->Record["project_title"]);
		$T->parse("project_name", false);

		$sql = "SELECT *
				FROM project_releases
				WHERE project_id = ".ToSQL($project_id,"integer").$rsearch."
				ORDER BY due_date DESC";
		$db->query($sql);

		if ($db->next_record()) {
			do {
				$sql2 = "SELECT *
						FROM	tasks
								LEFT JOIN users ON tasks.responsible_user_id=users.user_id
						WHERE	tasks.is_wish <> 0
								AND tasks.release_id = ".$db->Record["release_id"].$search."
						ORDER BY priority_id";
				$db2->query($sql2);
				$dicUsers .= "dicUsers[".$db->Record["release_id"]."] = ";
				$T->not_parsed["release_tasks"] = true;

				// VIEW TASKS
				$i = 0;
				$T->set_var("release_id", $db->Record["release_id"]);
				while ($db2->next_record()) {
					$i++;
					$T->set_var("id", $i);
					$T->set_var("task_id_tit", "<A HREF=create_reltask.php?task_id="
					.$db2->Record["task_id"].">".$db2->Record["task_title"]."</A>");
					$T->set_var("task_priority", $db2->Record["priority_id"]);
					$T->set_var("pri_c", "<a href=\"javascript:changePriority("
					.$i.",'down',".$db2->Record["release_id"].")\"><img width=\"16\""
					." height=\"16\" border=\"0\" src=\"images/move_down.gif\"></a>"
					."<a href=\"javascript:changePriority(".$i.",'up',"
					.$db2->Record["release_id"].");\"><img width=\"16\" height=\"16\" "
					."border=\"0\"src=\"images/move_up.gif\"></a>");

					if (substr($db2->Record["creation_date"], 0, 10) <> "0000-00-00") {
						$norm_date = norm_sql_date($db2->Record["creation_date"]);
						$T->set_var("t_due_date", $norm_date);
					}
					else $T->set_var("t_due_date", "No due date");

					if (substr($db2->Record["planed_date"], 0, 10) <> "0000-00-00") {
						$norm_date = norm_sql_date($db2->Record["planed_date"]);
						$T->set_var("s_date", $norm_date);
					}
					else $T->set_var("t_due_date", "No due date");

					$T->set_var("estim", $db2->Record["estimated_hours"]);

					if ($db2->Record["first_name"] <> "") $T->set_var("user",
					$db2->Record["first_name"]." ".$db2->Record["last_name"]);
					else $T->set_var("user", "No user asigned");
					$T->set_var("tid", "TaskNew");
					$T->set_var("task_id", $db2->Record["task_id"]);
					$T->set_var("mov_to_mon", "<a href=create_task.php?task_id="
					.$db2->Record["task_id"].">Move to monitor</a>");
					$T->parse("release_tasks", true);
				}

				$dicUsers .= $i."; ";
				$sql3 = "SELECT * FROM lookup_release_types";
				$db3->query($sql3);

				while ($db3->next_record())
					if ($db3->Record["type_id"] == $db->Record["release_type_id"])
						$tst = $db3->Record["type_desc"];

				// IS DUE DATE OR NO
				if (($db->Record["due_date"] == NULL) ||
				($db->Record["due_date"] == "0000-00-00"))
				$T->set_var("release_tit_dat", $db->Record["title"]." (No due date)");
				else $T->set_var("release_tit_dat", $db->Record["title"]." - ".$tst." ("
				.$db->Record["due_date"].")");

				// "ADD TASK" IN BOTTOM OF EACH
				$T->set_var("task_id_tit", "<A style=\"text-decoration: underline;\" HREF"
				."=create_reltask.php?release_id=".$db->Record["release_id"].">Add task</A>");
				$T->set_var("task_priority", "");
				$T->set_var("pri_c", "");
				$T->set_var("t_due_date", "");
				$T->set_var("id", "");
				$T->set_var("user", "");
				$T->set_var("task_id", "");
				$T->set_var("tid", "");
				$T->set_var("mov_to_mon", "");
				$T->set_var("s_date", "");
				$T->set_var("estim", "");
				$T->parse("release_tasks", true);
				$T->parse("releases", true);
			}
			while ($db->next_record());
		} else foreach ($T->vars as $key => $value) $T->vars[$key] = "";

		$T->set_var("dicUsers", $dicUsers);
		$T->set_var("proid", $project_id);
		$T->parse("pro_id", false);
		$T->pparse("page");
	}
	////////////////////////////////////////////////////////////////////////////////////
?>