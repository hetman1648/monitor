<?
/*
include auth file for common 
*/

date_default_timezone_set("Europe/London");

$user_id = GetSessionParam("UserID");
if($user_id == "") {
	header("Location:../login.php");
	exit;
}

$hide_done_tasks    = (isset($_SESSION["hide_done_tasks"]) && $_SESSION["hide_done_tasks"]) ;
$report_date_format = "jS M"; 

$svn_login    = "";
$svn_password = "";
$current_user_first_name = "";
$sql  = "SELECT svn_login,svn_password,first_name FROM users WHERE user_id=$user_id";
$db->query($sql);
if ($db->next_record()) {
	$svn_login    = $db->f("svn_login");
	$svn_password = $db->f("svn_password");
	$current_user_first_name   = $db->f("first_name");
}

if (!$svn_login || !$svn_password) die("To access this module please ask Vlad to issue SVN login/password for you");

$svn_path    = "https://web1.sayu.co.uk/svn/";

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