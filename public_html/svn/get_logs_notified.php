<?php
/*
	run this file from CRON job to get error critical logs after commit
*/

chdir(dirname(__FILE__));

$root_inc_path = "../";
include ("../includes/common.php");

function nl2br2($string) { 
    $replace_with = "</td></tr><tr><td>";
  $string = str_replace(array("\r\n", "\r", "\n"), $replace_with, $string); 
  return $string; 
} 

//initilizing variables
$repository        = "";
$path              = "https://web1.sayu.co.uk/svn/";
$start_date_time   = time();
$svn_update_id     = 0;
$svn_user_to_blame = 0; //user who did commit
$notify_user_name  = ""; // user to notify
$notify_user_email = "";
$svn_commit_date  = "";

//finding if there were any commits recently
$sql = "SELECT *, UNIX_TIMESTAMP(date_added) AS start_time FROM svn_updates WHERE checked_for_errors = 0";
$db->query($sql);
if ($db->next_record()) {
    $repository        = $db->f("repository");
    $svn_update_id     = $db->f("id");
    $svn_user_to_blame = $db->f("user_id");
    $svn_commit_date   = $db->f("start_time");

    //no need to check this commit next time
    $sql = "UPDATE svn_updates SET checked_for_errors=1 WHERE id=$svn_update_id";
    echo $sql. "\n";
    $db->query($sql);
} else die ("");


//$date = new DateTime("2017-12-31");
//echo $date->format("U");
// false
//$dd = $date->getTimestamp();
$start_date_time = $svn_commit_date;

//$repository = "vectis.co.uk";
//getting last critical errors from start_date_time
$command = "index.php?action=shcriterr&start_date_time=".$start_date_time."&repository=".$repository."&username=artem&password=111116";
//$command = "index.php?action=shcriterr&repository=".$repository."&username=artem&password=111116";
	
//echo $command."\n";
$log = "";
$res = get_page($path. $command); 

//echo "Result:$res \n";

$ok_string = 'Server response is: +OK Fatal Errors:';
if (strpos($res,$ok_string) !== false) {
    $lines = explode($ok_string, $res);
    if (isset($lines[1])) $log = $lines[1];

    if (!$log) die ("no errors");
    
    // we need to notify the user and us that there are critical errors on the website now
    $sql = "SELECT email, CONCAT(first_name,' ',last_name) AS user_name FROM users WHERE user_id= ".$svn_user_to_blame;
    $db->query($sql);
    if ($db->next_record()) {
        $notify_user_email = $db->f("email");
        $notify_user_name  = $db->f("user_name");
    }
    // prepare the text to be sent
    $headers = "From: noreply@sayu.co.uk\r\n";
    $headers .= "Reply-To: noreply@sayu.co.uk\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
    
//    $email_to = "artem.birzul@gmail.com, ravi.adloori@sayu.co.uk, kate@sayu.co.uk, sumnamelodiya@gmail.com, jarboy@gmail.com, " . $notify_user_email;
    $email_to = "artem.birzul@gmail.com, jarboy@gmail.com, " . $notify_user_email;
//    $email_to = "artem.birzul@gmail.com";

    $txt  = "<p>User <b>$notify_user_name</b> on $svn_commit_date commited changes to <b>$repository</b> SVN repository";
    $txt .= "<p>The following Critical Errors where found in the error log:";
    $txt .= "<p>";
    $txt .= base64_decode($log);

    mail( $email_to,"Critical Error(s) found after SVN commit by $notify_user_name",$txt, $headers);
    //} else die ("No commits today");

} else {
    //echo $res;
    $error_ok = 'ERR No critical errors found';
//Client-Server talking error: -ERR No logs found
    if (strpos($res,$error_ok) !== false) {
	   echo "OK - no errors";
    }  else {
        //mail("artem.birzul@gmail.com","SVN problem - get_logs_notified","nothing gets returned from Vlad");
    }
}



function get_page($url,$user_agent='Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)') 
{ 
    $options = array( 
        CURLOPT_RETURNTRANSFER => true,     // return web page 
        CURLOPT_HEADER         => false,    // return headers 
        CURLOPT_FOLLOWLOCATION => true,     // follow redirects 
        CURLOPT_ENCODING       => "",       // handle all encodings 
        CURLOPT_AUTOREFERER    => true,     // set referer on redirect 
        CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect 
        CURLOPT_TIMEOUT        => 120,      // timeout on response 
        CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects 
        CURLOPT_USERAGENT	   => $user_agent,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_URL            => $url
    ); 


	$ch = curl_init(); 
	curl_setopt_array( $ch, $options ); 
//	curl_setopt ($ch, CURLOPT_URL, $url); 
//	curl_setopt ($ch, CURLOPT_USERAGENT, $user_agent); 
//	curl_setopt ($ch, CURLOPT_HEADER, 0); 
//	curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1); 
//	curl_setopt ($ch, CURLOPT_REFERER, 'http://www.pcpropertymanager.com/wsnlinks/');
	$result = curl_exec ($ch); 
	$info = curl_getinfo($ch);
	/*	echo "<b>curl Error:</b>".curl_error($ch)."<hr>";
    	if (is_array($info)) {
    		print_r($info);
    	} else {
    		echo $info;
    	}
    	*/
	curl_close ($ch); 

	$msg = "URL:$url\n";
	$msg.= "Curl Error: curl_error($ch)\n";
	if (is_array($info)) {
		// $msg.= "info:".join("-",$info);
	} else {
		// $msg.= "info:".$info;
	}

	//mail("artem.birzul@gmail.com","test mobile check", $msg);
	
	return $result; 
} 

// function returns minutes, hours or days depending on number of hours
// @param: $hours (int)
// @param: is_prediction(bool) - if value is empty trying to predict
function getHoursFormat($hours, $is_prediction=true) {
    if ($hours < 1) {
    	$mins = number_format($hours *  60,0); 
      if ($mins == 0) {
          if ($is_prediction) {
            return "1 day?";
          } else {
            return "";
          }
      }
    	return $mins." mins";
    }
    if ($hours ==12) return "12 hrs";
    if ($hours >8) return number_format(round($hours / 8),0)." days";
    if ($hours ==8) return number_format(round($hours / 8),0)." day";
    if ($hours == 1) return "1 hr";
    return number_format($hours,0)." hrs"; 
}

function strToHours($subject) {
    $subject = str_replace(" ", "", $subject);
    $est_number = preg_replace("/[^0-9]/","",$subject);
        $est_words  = str_replace($est_number, "", $subject);
        $est_array = array("hr"   => 1,
                           "h"    => 1,
                           "hrs"  => 1,
                           "days" => 8,
                           "day"  => 8,
                           "day?" => 8,
                           "d"    => 8,
                           "ds"   => 8,
                           "m"    => 1/60,
                           "min"  => 1/60,
                           "mins" => 1/60,
                           "week" => 40,
                           "wk"   => 40,
                           ""     => 1
                           );
        if (isset($est_array[$est_words])) return $est_number * $est_array[$est_words]."<br>";
        else return 1 * $est_number;
}


?>
        