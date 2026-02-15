<?php

include("./includes/common.php");
include("./includes/date_functions.php");	
$user_id= GetSessionParam("UserID");		
			
$approve = GetParam("approve");
//echo $approve; 
	if (($user_id == 3) && ($approve == 1))
	{
		$period_id = GetParam("period_id");

    
    //-- Send e-mail to the user who applied for a holiday
#    $sql = "SELECT  FROM users WHERE user_id=".
#    $db->query($sql);
#    if ($db->next_record()) {


        $to = $user_email;
	$subj = "Monitor: Your vacation has been approved! ";
        $message = "<html><head><title>Monitor: Your vacation has been APPROVED!</title></head>";
	$message .= "<body>Enjoy your holidays! Drink reasonably ;-) <br> Responsible drinking means that you never have to feel sorry for what has happened while you were drinking</body>";
        @mail($to, $subj, $message, $headers);



		$sql ="UPDATE days_off SET is_approved='1' WHERE period_id='$period_id'";
		$db->query($sql);   
		header ("Location: view_vacations.php");
	}
		else if (($user_id == 3) && ($decline == 1))
		{
			$period_id = GetParam("period_id");
			$sql ="UPDATE days_off SET is_declined='1' WHERE period_id='$period_id'";
			$db->query($sql);   
			header ("Location: view_vacations.php");
	  
		}
	
			else
			{
	  		header ("Location: index.php");
			} 
?>	
				  