<?php
	include_once("./includes/common.php");
	include_once ("EsendexSendService.php");
	
	$esendex_username     = "paul.keating@sayu.co.uk";//old:"james.brown@spotlightguides.co.uk";
	$esendex_password     = "NDX9606";//old:"NDX5433";
	$esendex_account      = "EX0038965";//old:"EX0001633";
	$esendex_send_service = new EsendexSendService($esendex_username, $esendex_password, $esendex_account);
	
	$message = GetParam("message");
	
	CheckSecurity(1);		
	if (getsessionparam("privilege_id") == 9){
		header("Location: index.php");
		exit;
	}
	
	$sTemplateFileName = "emergency.html";
	$t= new iTemplate($sAppPath, array("main" => $sTemplateFileName));
	$t->set_var("msg","");

	$sql = " SELECT * FROM users WHERE is_deleted IS NULL ORDER BY first_name, last_name";
	$db->query($sql);
	$count = 0;
	while ($db->next_record()) {
		$t->set_var("person",        $db->f("first_name") . ' ' . $db->f("last_name"));
		$t->set_var("day_phone",     $db->f("day_phone"));
		$t->set_var("evn_phone",     $db->f("evn_phone"));
		$t->set_var("cell_phone",    $db->f("cell_phone"));
		$t->set_var("email",         $db->f('email'));
		$t->set_var("msn_account",   $db->f("msn_account"));
		$t->set_var("sms_account",   $db->f("sms_account"));
		$skype = $db->f("skype_account");
		if ($skype) {
			$t->set_var("skype_account", $skype);
			$t->parse('skype');
		} else {
			$t->set_var('skype', '');
		}		
		$count++;
		$t->set_var('class', ($count % 2) ? "DataRow1" : "DataRow2");
		$t->parse("records", true);
	}


	//$esendex_send_service = false;
	if (isset($message) && $message){
		$all_emails = "";
		$sms_sent = 0;
		$subject = "ALARM!";
		$headers = "From: artem@viart.com.ua\r\nReply-To: artem@viart.com.ua\r\nReturn-Path: monitor@viart.com.ua";
		$sql  = " SELECT email, sms_account, cell_phone, is_send_sms, is_viart FROM users ";
		$sql .= " WHERE is_deleted IS NULL";
		$db->query($sql);
		echo $sql."<hr>";
		while ($db->next_record()) {
			//adding email to all_emails list
			$email    = $db->f("email");
			$is_viart = $db->f("is_viart");
			if (strlen($email) && $is_viart) {
				if (strlen($all_emails))
					$all_emails .= ", ";
				$all_emails .= $email;
			}

			// sending sms via email or via esendex
			$sms_email  = $db->f("sms_account");
			$cell_phone = $db->f("cell_phone");
			
			if ($db->f("is_send_sms") == "1") {
			echo "to sent:".$cell_phone."<hr>";

				if (strlen($cell_phone)) {
					/*if (!$esendex_send_service) {
						include_once("./includes/esendex/EsendexSendService.php");
						$esendex_send_service = new EsendexSendService($esendex_username, $esendex_password, $esendex_account);
					}*/
	
					$sms_message_id = $esendex_send_service->SendMessage($cell_phone, $subject . " " . $message, "Text");
					if ($sms_message_id) { $sms_sent++; }
				}
			} else {
		 		if (strlen($sms_email) && ereg("^[^@ ]+@[^@ ]+\.[^@ ]+$", $sms_email)) {
					mail($sms_email, $subject, stripslashes($message), $headers);
				}
		 	}
		}		
		mail($all_emails, $subject, stripslashes($message), $headers);		
		$t->set_var("msg", "<br>Your message was sent!<br>\n" . strval($sms_sent) . " message(s) were sent via Esendex.");
	}
	
	$t->parse("main", false);
	echo $t->p("main");

?>