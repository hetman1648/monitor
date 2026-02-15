<?php

	include_once("./includes/date_functions.php");
	include_once("./includes/common.php");

    CheckSecurity(2);

	$t = new iTemplate($sAppPath);
	$t->set_file("main", "view_warnings.html");
    include("./filter.php");

	$db2 = new DB_Sql;
	$db2->Database = DATABASE_NAME;
	$db2->User     = DATABASE_USER;
	$db2->Password = DATABASE_PASSWORD;
	$db2->Host     = DATABASE_HOST;

	$db3 = new DB_Sql;
	$db3->Database = DATABASE_NAME;
	$db3->User     = DATABASE_USER;
	$db3->Password = DATABASE_PASSWORD;
	$db3->Host     = DATABASE_HOST;

	$db4 = new DB_Sql;
	$db4->Database = DATABASE_NAME;
	$db4->User     = DATABASE_USER;
	$db4->Password = DATABASE_PASSWORD;
	$db4->Host     = DATABASE_HOST;


	$sql = " SELECT CONCAT(u.first_name, ' ', u.last_name) AS user_name, ";
	$sql .= " COUNT(w.warning_id) AS warn_quant ";
	$sql .= " FROM warnings w, users u";
	$sql .= " WHERE w.user_id=u.user_id ".$sqlteam;
	if ($person_selected) $sql .= " AND u.user_id=" . ToSQL($person_selected, "integer");
		if ($sdt) $sql .= " AND w.date_added>='$sd' ";
		if ($edt) $sql .= " AND w.date_added<='$ed' ";
	$sql .= "GROUP BY w.user_id ORDER BY u.first_name, u.last_name";
	$db->query($sql);

	if ($db->next_record()) {
	  	$t->set_var("no_records", "");
		do {
			$user_name = $db->f("user_name");
			$warn_quant = $db->f("warn_quant");
			$t->set_var("user_name", $user_name);
			$t->set_var("warn_quant", $warn_quant);
			$t->parse("records", true);
		} while ($db->next_record());
	} else {
		$t->set_var("records_header", "");
		$t->set_var("user_stats", "");
		$t->set_var("records", "");
		$t->parse("no_records", false);
	}
	$t->parse("result", false);
/*
	$sql2 = "SELECT user_id, CONCAT(u.first_name, ' ', u.last_name) AS user_name FROM users u".$sqlteam;
	$db2->query($sql2);
			if ($db2->next_record()){
			  //$t->set_var("no_records", "");
				do
				{
				  	$user_name = $db2->f("user_name");
  				//	echo "111"; exit;
					$sql3 = "SELECT * FROM warnings WHERE user_id = ".$db2->Record["user_id"];
  					$db3->query($sql3);
  					if ($db3->next_record()){
  					//  $t->set_var("no_warnings", "");
					do {
    				$date_added = norm_sql_date($db3->f("date_added"));
					$notes = $db3->f("description");
					//$creator = $db3->f("admin_user_id");
					$sql4 = "SELECT CONCAT(first_name, ' ', last_name) AS user_name FROM users WHERE user_id = ".$db3->Record["admin_user_id"];
					$db4->query($sql4);
					$db4->next_record();
					$creator = $db4->f("user_name");

					$t->set_var("user_name", $user_name);
					$t->set_var("date_added", $date_added);
					$t->set_var("notes", $notes);
					$t->set_var("creator", $creator);

  				 }
  				 while ($db3->next_record());
  				}


   				}
		  		while ($db2->next_record());
		  		$t->parse("warnings_td", true);
	}
	else {
	  			$t->set_var("user_name", "");
				$t->set_var("date_added", "");
				$t->set_var("notes", "");
				$t->set_var("creator", "");
	  			//$t->set_var("warnings_td", "");
	  			//$t->parse("no_warnings", false);
				}

	*/

	$sql = "SELECT w.*";
	$sql .= " FROM warnings w, users u";
	$sql .= " WHERE w.user_id=u.user_id ".$sqlteam;
	if ($person_selected) $sql .= " AND u.user_id=" . ToSQL($person_selected, "integer");
	if ($sdt) $sql .= " AND w.date_added>='$sd' ";
	if ($edt) $sql .= " AND w.date_added<='$ed' ";
	$sql .= "ORDER BY u.first_name, u.last_name, w.date_added";

	$db2->query($sql);

	if ($db2->next_record()) {
	 	$t->parse("warnings_header", true);
	 	$t->set_var("no_warnings", '');
	 	$user_name2 = 'Harry Potter';
		do {
			$sql3 = "SELECT CONCAT(first_name, ' ', last_name) AS user_name FROM users WHERE user_id = " . (int)$db2->Record["admin_user_id"];
			$db3->query($sql3);
			$db3->next_record();
			$creator = $db3->f("user_name");
			$t->set_var("creator", $creator);

			$sql3 = "SELECT CONCAT(first_name, ' ', last_name) AS user_name FROM users WHERE user_id = " . (int)$db2->Record["user_id"];
			$db3->query($sql3);
			$db3->next_record();
			$user_name = $db3->f("user_name");
            if (($user_name2 == $user_name)) {
            	$t->set_var("user_name", '');
            	$t->set_var("shift", '');
            } else {
				$t->set_var("user_name", $user_name);
				$t->set_var("shift", '<tr style="background-color:#EEEEEE;height:5 px"><td colspan=7><font size="1"></font></td></tr>');
			}
			
			if ($user_name2 == 'Harry Potter') $t->set_var("shift", '');

			$date_added = norm_sql_date($db2->f("date_added"));
			$t->set_var("date_added", $date_added);

			$notes = $db2->f("description");
			$t->set_var("notes", $notes);

			$warning_id = $db2->f("warning_id");
			$t->set_var("warning_id", $warning_id);

			$t->parse("warnings_td", true);

   			$user_name2 = $user_name;

		} while ($db2->next_record());
	} else {
	  	$t->set_var("warnings_td", "");
		$t->set_var("warnings_header", "");
		$t->parse("no_warnings");
	}

    $t->set_var("action", 'view_warnings.php');
	$t->pparse("main");

?>