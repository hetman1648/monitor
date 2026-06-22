<?php
/*
	run this file on cron to get an email sent about all update operations for today
*/

// Change current folder for console script
chdir(dirname(__FILE__));
	
$root_inc_path = "../";
include ("../includes/common.php");
//include ("./auth.php");


/*	include_once(dirname(__FILE__) . "/../db_mysql.inc");
	include_once(dirname(__FILE__) . "/../includes/db_connect.php");
	include_once(dirname(__FILE__) . "/../includes/common_functions.php");

	$db = new DB_Sql;
	$db->Database = DATABASE_NAME;
	$db->User     = DATABASE_USER;
	$db->Password = DATABASE_PASSWORD;
	$db->Host     = DATABASE_HOST;
*/

	$users = array();
   	$sql = "SELECT user_id,first_name,last_name FROM users ";
   	$db->query($sql);
   	while ($db->next_record()) {
   		$users[$db->f("user_id")] = $db->f("first_name"). " ".$db->f("last_name");
   	}

	// all users after Sergiy (including him as well)
	$sql = "SELECT date_added,user_id,repository FROM svn_updates WHERE user_id>=170 AND DATE(date_added)= CURDATE() ORDER BY date_added DESC LIMIT 50";
	//echo $sql;
	$db->query($sql); $c = 0; $valid_rows = array();
	$last_user_id = 0;

	$txt  = <<<EOD




	<font style="font-family:Helvetica,Arial">
        <table class="table table-striped">
        	<tr>
        		<th>User</th>
        		<th>Date</th>
			<th>Repository</th>
        	</tr>
EOD;


	 while ($db->next_record()) { 
	    $row = "<tr>";
    	    $row.= "<td>"; 
	    if($last_user_id != $db->f("user_id")) {
		$row .=  $users[$db->f("user_id")]; 
		$last_user_id = $db->f("user_id");
	    }
	    $row .= "</td>";
	    $row .= "<td>" . $db->f("date_added") ."</td>";
	    $row .= "<td>" . $db->f("repository") ."</td>";
    	    $row.= "</tr>";
	    $c++;
	    $txt .= $row; 
	}
        $txt .= "</table>";
       
    echo "OK";

    $to = "artem.birzul@gmail.com, ravi.adloori@sayu.co.uk, mail@katheryne.net, sumnamelodiya@gmail.com, jarboy@gmail.com";
//    $to = "artem.birzul@gmail.com";
    

    $headers = "From: noreply@sayu.co.uk\r\n";
    $headers .= "Reply-To: noreply@sayu.co.uk\r\n";
//    $headers .= "CC: susan@example.com\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    if ($c) {

	mail($to ,"Today's SVN commits to watch:",$txt, $headers);
    } else die ("No commits today");
?>
        