<?php

	include_once("./includes/common.php");
	include_once("./includes/date_functions.php");

	if (getsessionparam("privilege_id") == 9) {
		header("Location: index.php");
		exit;
	}

	CheckSecurity(1);

	$action		= GetParam("action");
	$user_id 	= GetParam("person_select");
	$note_id	= GetParam("note_id");
	$notes		= GetParam("notes");
	$note_date  = GetParam("note_date");

	$err		= "";

	$T = new iTemplate("./templates", array("page"=>"create_delay_note.html"));
	$T->set_var('tr_created_user_id', '');

	if ($action == "Cancel") {
		header("Location: view_vacations.php");
	}

	if ($action == "Delete Notification")
	{
		$sql = "DELETE FROM delay_notifications WHERE note_id = " . ToSQL($note_id, "INTEGER");
		$db->query($sql);
		header("Location: view_vacations.php");
	}

	if ($action == "Update Notification" || $action == "Add Notification")
	{
		$today = getdate();
		$sek_day = 24 * 60 * 60;

		$olddate = date("Y-m") . "-01"; //date("Y-m-d", (time()-(($today["wday"]-1) * $sek_day)));
		$nowdate = date("Y-m-d");
		/**/
		if (strlen($note_date) > 0) {
			list($note_year,$note_month,$note_day) = explode("-",$note_date);
			if (@checkdate($note_month,$note_day,$note_year)) {			
				$olddate = date("Y-m-d",mktime(0,0,0,$note_month,1,$note_year));
				$nowdate = date("Y-m-d",mktime(0,0,0,$note_month,$note_day,$note_year));
			}
		}
		else
		{
			$err .= "'Note Date is required\n";
		}
		
		if (strlen($notes) == 0)
		{
			$err .= "'Notes' is required\n";
		}
		/**/

		$sql  = " SELECT count(*) as total_delays FROM delay_notifications ";
		$sql .= " WHERE (date_added BETWEEN " . ToSQL($olddate,"string"). " AND " . ToSQL($nowdate,"string") . ") ";
		$sql .= "	 AND user_id = " . ToSQL($user_id, "INTEGER");
		
		$db->query($sql);
		$total_delays = 0;
		if ($db->next_record()) {
			$total_delays = $db->Record["total_delays"];
		}

		// use can have up to 5 delay notifications
		if ($total_delays < 5) {
		//if ($total_delays < 5 || $new_month) {
			if (strlen($err) == 0)
			{
				if ($action == "Add Notification") {
					$sql = "INSERT INTO delay_notifications (user_id, notes, note_date, date_added, created_user_id) ";
					$sql .= "VALUES (" . ToSQL($user_id, "INTEGER") . ", " . ToSQL($notes, "text") . ",  ";
					$sql .= ToSQL($note_date, "text") . ", ";
					$sql .= " DATE(NOW()), " . GetSessionParam("UserID") . ")";
				} else {
					$sql = "UPDATE delay_notifications SET user_id = " . ToSQL($user_id, "INTEGER");
					$sql .= ", notes = " . ToSQL($notes, "text");
					$sql .= ", note_date = " . ToSQL($note_date, "text");
					$sql .= ", date_added = NOW()";
					$sql .= " WHERE note_id = " . ToSQL($note_id, "INTEGER");
				}
				$db->query($sql);
				header("Location: view_vacations.php");
			}
		} else {
			$err .= "User already has 5 delay notifications";
		}
	}

	$person_option = '';
	if ($note_id != '' && intval($note_id) > 0)
	{
		$b_text = "Delete Notification";
		$e_text = "Update Notification";

		$sql = "SELECT dn.*, CONCAT(u.first_name, ' ', u.last_name) as user_name ";
		$sql .= " FROM delay_notifications dn, users u ";
		$sql .= " WHERE u.user_id = dn.created_user_id AND note_id =" . ToSQL($note_id, INTEGER);
		$db->query($sql);
		if ($db->next_record()) {
			$user_id = $db->Record['user_id'];

			$T->set_var("action_value", $e_text);
			$T->set_var("note_id", $note_id);
			$T->set_var("notes", $db->Record['notes']);
			$T->set_var('tr_created_user_id', '<tr><td class="FieldCaptionTD">Created Person</td><td class="DataTD">' . $db->Record['user_name'] . '</td></tr>');
			$T->set_var("title_o", $e_text);
			$T->set_var("delete_button", "<input type=\"submit\" name=\"action\" value=\"" . $b_text . "\">");
			$T->set_var("note_date", $db->Record["note_date"]);
			$T->set_var("createdate",$db->Record["date_added"]);

			$sql = "SELECT user_id, CONCAT(first_name, ' ', last_name) as user_name FROM users ";
			$sql .= " WHERE is_viart=1 AND (is_deleted IS NULL OR is_deleted!=1) ORDER BY user_name ASC ";
			$db->query($sql);
			while ($db->next_record()) {
				if ($user_id == $db->Record["user_id"]) {
					$person_option .= "<option value=" . $db->Record["user_id"] . " selected>" . $db->Record["user_name"] . "</option>\n";
				} else {
					$person_option .= "<option value=" . $db->Record["user_id"] . ">" . $db->Record["user_name"] . "</option>\n";
				}
			}
			$T->set_var("person_option", $person_option);
		}
	}
	else
	{
		$sql = "SELECT user_id, CONCAT(first_name, ' ', last_name) as user_name FROM users ";
		$sql .= " WHERE is_viart=1 AND (is_deleted IS NULL OR is_deleted!=1) ORDER BY user_name ASC ";
		$db->query($sql);

		while ($db->next_record()) {
			$person_option .= "<option value=" . $db->Record["user_id"];
			if (strlen($user_id) && $user_id == $db->Record["user_id"]) {
				$person_option .= " selected ";
			}
			$person_option .= ">" . $db->Record["user_name"] . "</option>\n";
		}

		$T->set_var("note_id", "");
		$T->set_var("person_option", $person_option);
		$T->set_var("note_date", (strlen($note_date)?$note_date:date("Y-m-d")));
		$T->set_var("notes", $notes);
		$period_t = "Vacation";
		$T->set_var("period_t", $period_t);
		$T->set_var("delete_button", "");
		$T->set_var("action_value", 'Add Notification');
		$T->set_var("title_o", "Add Notification");
	}

	if (strlen($err) > 0) {
		$T->set_var("err", nl2br($err));
	} else {
		$T->set_var("error", "");
	}
	$T->pparse("page", false);

?>