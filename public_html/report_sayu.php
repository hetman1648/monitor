<?php

include("./includes/common.php");
include("./includes/date_functions.php");

$DEBUG = false;
$intranet = 0;

if ($intranet) {
	$attachments_path = dirname(__FILE__) . "/temp_attachments/";
} else {
	$attachments_path = "/var/www/monitor/temp_attachments/";
}
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

$today_date = date('Y-m-d');

//************* HEALTH CHECKS ********************//
$checks_done = array();
$checks_notdone = array();

$str2remove_regexps = array(
	"Health Check Required \-"
	, "Health Check\:"
);

/// selection of all done health checks
$sql = " SELECT DISTINCT(t.task_id), t.task_title";
$sql .= " FROM time_report tr, tasks t ";
$sql .= " WHERE t.task_id=tr.task_id AND DATE_FORMAT(tr.report_date, '%Y-%m-%d')='$today_date' AND project_id=38";
$sql .= " AND (t.responsible_user_id!=27 AND t.responsible_user_id!=37 AND t.responsible_user_id!=48 AND t.responsible_user_id!=49 AND t.responsible_user_id!=46)";
$sql .= " AND (tr.user_id=27 OR tr.user_id=37 OR tr.user_id=48 OR tr.user_id=49 OR tr.user_id=46)";
$db->query($sql);

if ($db->next_record())
{
	$i = 0;
	do
	{
		$checks_done[$i] = ltrimstr($db->f("task_title"), $str2remove_regexps);
/*		
		if (strstr($db->f("task_title"),"Health Check Required"))
		{
			$check_name_arr = explode (" ", $db->f("task_title"));
			$check_name ="";
			for ($j = 4; $j < sizeof($check_name_arr); $j++)
			{
				$check_name .= $check_name_arr[$j];
				$check_name .=	"  ";
			}
			$checks_done[$i] = $check_name; //$db->f("task_title");
		}
		else {
			$checks_done[$i] = $db->f("task_title");
		}
//*/		
		$i++;
	}
	while ($db->next_record());
}

///selection of all not done health checks
$sql = "SELECT task_id,task_title FROM tasks WHERE project_id=38 ";
$sql .= " AND (responsible_user_id='27'";
$sql .= " OR responsible_user_id = '37' OR responsible_user_id = '48' OR responsible_user_id = '49' OR responsible_user_id = '46')";
$sql .= "AND task_status_id!='4' AND is_closed='0'";
$db->query($sql);

if ($db->next_record())
{
	$i = 0;
	do
	{
		$checks_notdone[$i] = ltrimstr($db->f("task_title"), $str2remove_regexps);
/*		
		if (strstr($db->f("task_title"),"Health Check Required"))
		{
			$check_name_arr = explode (" ",$db->f("task_title"));
			$check_name = "";
			for ($j = 4; $j < sizeof($check_name_arr); $j++)
			{
				$check_name .= $check_name_arr[$j];
				$check_name .=	"  ";
			}
			$checks_notdone[$i] = $check_name; //$db->f("task_title");
		} else {
			$checks_done[$i] = $db->f("task_title");
		}
//*/		
		$i++;
	}
	while ($db->next_record());
}

//total amount of done health checks
/*
$sql = " SELECT COUNT(DISTINCT(tr.task_id)) AS total_checks";
$sql .= " FROM time_report tr, tasks t ";
$sql .= " WHERE t.task_id=tr.task_id AND DATE_FORMAT(tr.report_date, '%Y-%m-%d')='$today_date' AND project_id=38";
$sql .= " AND (t.responsible_user_id!=27 AND t.responsible_user_id!=37 AND t.responsible_user_id!=48 AND t.responsible_user_id!=49 AND t.responsible_user_id!=46)";
$sql .= " AND (tr.user_id=27 OR tr.user_id=37 OR tr.user_id=48 OR tr.user_id=49 OR tr.user_id=46)";
$db->query($sql);

if ($db->next_record())
{
	//	echo $db->f("total_trials")."<br>";
	$total_checks_done = $db->f("total_checks");
}
//*/
$total_checks_done = count($checks_done);


////total amount of  not done health checks
/*
$sql = "SELECT COUNT(task_id) AS total_checks FROM tasks WHERE project_id=38 ";
$sql .= " AND (responsible_user_id='27'";
$sql .= " OR responsible_user_id = '37' OR responsible_user_id = '48' OR responsible_user_id = '49' OR responsible_user_id = '46')";
$sql .= " AND is_closed='0' AND task_status_id!='4'";
$db->query($sql);
if ($db->next_record())
{
	//  echo $db->f("total_trials")."<br>";
	$total_checks_notdone = $db->f("total_checks");
}
//*/
$total_checks_notdone = count($checks_notdone);

//writing to csv

$csv = "Health Checks,Done, To be done\n";
//echo sizeof($trials_notdone);
$checks_length = max(sizeof($checks_notdone),sizeof($checks_done));
//echo $trials_length;
for ($i=0; $i<$checks_length; $i++)
{
	$done = isset($checks_done[$i])?$checks_done[$i]:"";
	$notdone = isset($checks_notdone[$i])?$checks_notdone[$i]:"";
	$csv .= "\"\",\"".$done."\",\"".$notdone."\"\n";
}
$csv .= "Total,".$total_checks_done.",".$total_checks_notdone."\n\n";

//************* TRIALS ********************//
$trials_done = array();
$trials_notdone = array();

$str2remove_regexps = array(
	"Trial\:"
);

/// selection of all done trials
/*$sql = "SELECT task_id,task_title FROM tasks WHERE DATE_FORMAT(started_time, '%Y-%m-%d')='2007-02-28' AND project_id=39 ";
$sql .= " AND ((responsible_user_id='27'";
$sql .= " OR responsible_user_id = '37' OR responsible_user_id = '48' OR responsible_user_id = '49' OR responsible_user_id = '46')";
$sql .= " AND task_status_id='4') OR (task_status_id='9')";
$db->query($sql);
if ($db->next_record())
{
$i=0;
do
{
echo "ID".$db->f("task_id")."<br> ";
$trials_done[$i] =  $db->f("task_id");
$i++;
}
while ($db->next_record());
}
*/
$sql = " SELECT DISTINCT(t.task_id), t.task_title";
$sql .= " FROM time_report tr, tasks t ";
$sql .= " WHERE t.task_id=tr.task_id AND DATE_FORMAT(tr.report_date, '%Y-%m-%d')='$today_date' AND project_id=39";
$sql .= " AND (t.responsible_user_id!=27 AND t.responsible_user_id!=37 AND t.responsible_user_id!=48 AND t.responsible_user_id!=49 AND t.responsible_user_id!=46)";
$sql .= " AND (tr.user_id=27 OR tr.user_id=37 OR tr.user_id=48 OR tr.user_id=49 OR tr.user_id=46)";
$db->query($sql);

if ($db->next_record())
{
	$i=0;
	do
	{
		//echo "ID".$db->f("task_title")."<br> ";
		$trials_done[$i] = ltrimstr($db->f("task_title"), $str2remove_regexps);
		//$trials_done[$i] =  $db->f("task_title");
		$i++;
	}
	while ($db->next_record());
}

///selection of all not done trials
$sql = "SELECT task_id,task_title FROM tasks WHERE project_id=39 ";
$sql .= " AND (responsible_user_id='27'";
$sql .= " OR responsible_user_id = '37' OR responsible_user_id = '48' OR responsible_user_id = '49' OR responsible_user_id = '46')";
$sql .= "AND task_status_id!='4' AND is_closed='0'";
$db->query($sql);

if ($db->next_record())
{
	$i=0;
	do
	{
		//	echo "ID".$db->f("task_id")."<br> ";
		//$trials_notdone[$i] =  $db->f("task_title");
		$trials_notdone[$i] = ltrimstr($db->f("task_title"), $str2remove_regexps);
		$i++;
	}
	while ($db->next_record());
}

//total amount of done trials
/*
$sql = "SELECT COUNT(task_id) AS total_trials  FROM tasks WHERE DATE_FORMAT(started_time, '%Y-%m-%d')='2007-02-28'";
$sql .= " AND project_id=39 AND (responsible_user_id='27'";
$sql .= " OR responsible_user_id = '37' OR responsible_user_id = '48' OR responsible_user_id = '49' OR responsible_user_id = '46')";
$sql .= " AND (task_status_id=4 OR task_status_id=9)";
$db->query($sql);
if ($db->next_record())
{
echo $db->f("total_trials")."<br>";
$total_trials_done = $db->f("total_trials");
}
*/
/*
$sql = " SELECT COUNT(DISTINCT(tr.task_id)) AS total_trials";
$sql .= " FROM time_report tr, tasks t ";
$sql .= " WHERE t.task_id=tr.task_id AND DATE_FORMAT(tr.report_date, '%Y-%m-%d')='$today_date' AND project_id=39";
$sql .= " AND (t.responsible_user_id!=27 AND t.responsible_user_id!=37 AND t.responsible_user_id!=48 AND t.responsible_user_id!=49 AND t.responsible_user_id!=46)";
$sql .= " AND (tr.user_id=27 OR tr.user_id=37 OR tr.user_id=48 OR tr.user_id=49 OR tr.user_id=46)";
$db->query($sql);

if ($db->next_record())
{
	echo $db->f("total_trials")."<br>";
	$total_trials_done = $db->f("total_trials");
}
//*/
$total_trials_done = count($trials_done);

////total amount of  not done trials
/*
$sql = "SELECT COUNT(task_id) AS total_trials FROM tasks WHERE project_id=39 ";
$sql .= " AND (responsible_user_id='27'";
$sql .= " OR responsible_user_id = '37' OR responsible_user_id = '48' OR responsible_user_id = '49' OR responsible_user_id = '46')";
$sql .= " AND is_closed='0' AND task_status_id!='4'";
$db->query($sql);
if ($db->next_record())
{
	//  echo $db->f("total_trials")."<br>";
	$total_trials_notdone = $db->f("total_trials");
}
//*/
$total_trials_notdone = count($trials_notdone);

//writing to csv

$csv .= "\nTrials,Done, To be done\n";
//echo sizeof($trials_notdone);
$trials_length = max(sizeof($trials_notdone),sizeof($trials_done));
//echo $trials_length;
for ($i=0; $i<$trials_length; $i++)
{
	$done = isset($trials_done[$i])?$trials_done[$i]:"";
	$notdone = isset($trials_notdone[$i])?$trials_notdone[$i]:"";
	$csv .= "\"\",\"".$done."\",\"".$notdone."\"\n";
}
$csv .= "Total,".$total_trials_done.",".$total_trials_notdone."\n\n";

//************* FULL ACCOUNT ********************//
$account_done = array();
$account_notdone = array();
/// selection of all done full account
$sql = " SELECT DISTINCT(t.task_id), t.task_title";
$sql .= " FROM time_report tr, tasks t ";
$sql .= " WHERE t.task_id=tr.task_id AND DATE_FORMAT(tr.report_date, '%Y-%m-%d')='$today_date' AND project_id=53";
$sql .= " AND (t.responsible_user_id!=27 AND t.responsible_user_id!=37 AND t.responsible_user_id!=48 AND t.responsible_user_id!=49 AND t.responsible_user_id!=46)";
$sql .= " AND (tr.user_id=27 OR tr.user_id=37 OR tr.user_id=48 OR tr.user_id=49 OR tr.user_id=46)";
$db->query($sql);

if ($db->next_record())
{
	$i=0;
	do
	{
		//	echo "ID".$db->f("task_id")."<br> ";
		$account_done[$i] =  $db->f("task_title");
		$i++;
	}
	while ($db->next_record());
}

///selection of all not done full account
$sql = "SELECT task_id,task_title FROM tasks WHERE project_id=53 ";
$sql .= " AND (responsible_user_id='27'";
$sql .= " OR responsible_user_id = '37' OR responsible_user_id = '48' OR responsible_user_id = '49' OR responsible_user_id = '46')";
$sql .= "AND task_status_id!='4' AND is_closed='0'";
$db->query($sql);

if ($db->next_record())
{
	$i=0;
	do
	{
		//	echo "ID".$db->f("task_id")."<br> ";
		$account_notdone[$i] =  $db->f("task_title");
		$i++;
	}
	while ($db->next_record());
}
/*
//total amount of done full account
$sql = " SELECT COUNT(DISTINCT(tr.task_id)) AS total_account";
$sql .= " FROM time_report tr, tasks t ";
$sql .= " WHERE t.task_id=tr.task_id AND DATE_FORMAT(tr.report_date, '%Y-%m-%d')='$today_date' AND project_id=53 ";
$sql .= " AND (t.responsible_user_id!=27 AND t.responsible_user_id!=37 AND t.responsible_user_id!=48 AND t.responsible_user_id!=49 AND t.responsible_user_id!=46)";
$sql .= " AND (tr.user_id=27 OR tr.user_id=37 OR tr.user_id=48 OR tr.user_id=49 OR tr.user_id=46)";
$db->query($sql);

if ($db->next_record())
{
	//	echo $db->f("total_trials")."<br>";
	$total_account_done = $db->f("total_account");
}
//*/
$total_account_done = count($account_done);
/*
////total amount of  not done full account
$sql = "SELECT COUNT(task_id) AS total_account FROM tasks WHERE project_id=53 ";
$sql .= " AND (responsible_user_id='27'";
$sql .= " OR responsible_user_id = '37' OR responsible_user_id = '48' OR responsible_user_id = '49' OR responsible_user_id = '46')";
$sql .= " AND is_closed='0' AND task_status_id!='4'";
$db->query($sql);
if ($db->next_record())
{
	//  echo $db->f("total_trials")."<br>";
	$total_account_notdone = $db->f("total_account");
}
//var_dump($trials_notdone);
//*/
$total_account_notdone = count($account_notdone);

//writing to csv

$csv .= "\nFull Account,Done, To be done\n";
//echo sizeof($trials_notdone);
$account_length = max(sizeof($account_notdone),sizeof($account_done));
//echo $trials_length;
for ($i=0; $i<$account_length; $i++)
{
	$done = isset($account_done[$i])?$account_done[$i]:"";
	$notdone = isset($account_notdone[$i])?$account_notdone[$i]:"";
	$csv .= "\"\",\"".$done."\",\"".$notdone."\"\n";
}
$csv .= "Total,".$total_account_done.",".$total_account_notdone."\n\n";


//************* PCW ********************//
$pcw_done = array();
$pcw_notdone = array();
/// selection of all done full pcw
$sql = " SELECT DISTINCT(t.task_title)";
		$sql .= " FROM time_report tr, tasks t ";
		$sql .= " WHERE t.task_id=tr.task_id AND DATE_FORMAT(tr.report_date, '%Y-%m-%d')='$today_date' AND project_id=59";
		$sql .= " AND (t.responsible_user_id!=27 AND t.responsible_user_id!=37 AND t.responsible_user_id!=48 AND t.responsible_user_id!=49 AND t.responsible_user_id!=46)";
		$sql .= " AND (tr.user_id=27 OR tr.user_id=37 OR tr.user_id=48 OR tr.user_id=49 OR tr.user_id=46)";
		$db->query($sql);
	
		if ($db->next_record())
		{
		  $i=0;
  			do
  			{				
			  //	echo "ID".$db->f("task_id")."<br> ";
			  	$pcw_done[$i] =  $db->f("task_title");
				$i++;
  			}
  while ($db->next_record()); 
		}
	
///selection of all not done full pcw
$sql = "SELECT task_id,task_title FROM tasks WHERE project_id=59 ";
$sql .= " AND (responsible_user_id='27'";
$sql .= " OR responsible_user_id = '37' OR responsible_user_id = '48' OR responsible_user_id = '49' OR responsible_user_id = '46')";
$sql .= "AND task_status_id!='4' AND is_closed='0'";
$db->query($sql);
if ($db->next_record())
{
  $i=0;
  do
  {
//	echo "ID".$db->f("task_id")."<br> ";
	$pcw_notdone[$i] =  $db->f("task_title");
	$i++;
  }
  while ($db->next_record()); 
}

//total amount of done full pcw
		$sql = " SELECT COUNT(DISTINCT(tr.task_id)) AS total_pcw";
		$sql .= " FROM time_report tr, tasks t ";
		$sql .= " WHERE t.task_id=tr.task_id AND DATE_FORMAT(tr.report_date, '%Y-%m-%d')='$today_date' AND project_id=59 ";
		$sql .= " AND (t.responsible_user_id!=27 AND t.responsible_user_id!=37 AND t.responsible_user_id!=48 AND t.responsible_user_id!=49 AND t.responsible_user_id!=46)";
		$sql .= " AND (tr.user_id=27 OR tr.user_id=37 OR tr.user_id=48 OR tr.user_id=49 OR tr.user_id=46)";
		$db->query($sql);
	
		if ($db->next_record())
		{
		  //	echo $db->f("total_trials")."<br>";
  			$total_pcw_done = $db->f("total_pcw");
		}

////total amount of  not done full pcw
$sql = "SELECT COUNT(task_id) AS total_pcw FROM tasks WHERE project_id=59 ";
$sql .= " AND (responsible_user_id='27'";
$sql .= " OR responsible_user_id = '37' OR responsible_user_id = '48' OR responsible_user_id = '49' OR responsible_user_id = '46')";
$sql .= " AND is_closed='0' AND task_status_id!='4'";
$db->query($sql);
if ($db->next_record())
{
//  echo $db->f("total_trials")."<br>";
  $total_pcw_notdone = $db->f("total_pcw"); 
}
//var_dump($trials_notdone);

//writing to csv

$csv .= "\nPaying client work,Done, To be done\n";
//echo sizeof($trials_notdone);
$pcw_length = max(sizeof($pcw_notdone),sizeof($pcw_done));
//echo $trials_length;
for ($i=0; $i<$pcw_length; $i++)
{
  $csv .= ",\"".$pcw_done[$i]."\",\"".$pcw_notdone[$i]."\"\n";
}
$csv .= "Total,".$total_pcw_done.",".$total_pcw_notdone."\n\n";
//********************** END PCW ********************//





//************** CSV *****************//
/*		$filename = "tasks_" . $today_date . ".csv";
header("Content-Type: application/x-ms-download");
header("Content-Length: " . strlen($csv));
header("Content-Disposition: attachment; filename=" . $filename);
header("Content-Transfer-Encoding: binary");
header("Cache-Control: Public");
header("Expires: 0");
echo $csv;
exit;*/
$filename = "tasks_" .$today_date. ".csv";
$file = fopen($attachments_path . $filename, 'w');
fwrite($file,$csv);
fclose($file);
///////////////////////////////////////

$mail_from = "webmaster@sayu.co.uk";
if ($DEBUG) {
	$mail_to = "victor@viart.com, kateryna@viart.com.ua";
} else {
	$mail_to = "tony.marshall@sayu.co.uk, simon.pitts@yoonoo.co.uk, catherine.hinchcliffe@sayu.co.uk";
}
$mail_subject = "SAYU report for  ".$today_date;
$user_message = "Here is your report. Enjoy! :)\n";
$filename = "sayu_reports_for_".$today_date.".csv";
$filepath = $attachments_path . "tasks_".$today_date.".csv";
$attachments[] = array($filename, $filepath);
if (va_mail($mail_to, $mail_subject, $user_message, "From: webmaster@sayu.co.uk","", $attachments))
{
	unlink( $attachments_path . "tasks_".$today_date.".csv");
}

function va_mail($mail_to, $mail_subject, $mail_body, $mail_headers = "", $mail_type = "", $attachments = "")
{
	if (!$mail_headers) {
		$mail_headers = "MIME-Version: 1.0";
	}
	$eol = get_eol();
	if (is_array($attachments) && sizeof($attachments) > 0) {
		$boundary = "--va_". md5(va_timestamp()) . "_" . va_timestamp();
		$mail_headers .= $eol . "Content-Type: multipart/mixed; boundary=\"" . $boundary . "\"";

		$original_body = $mail_body;
		$mail_body  = "This is a multi-part message in MIME format." . $eol . $eol;
		$mail_body .= "--" . $boundary . $eol;
		if ($mail_type) {
			$mail_body .= "Content-Type: text/html;" . $eol;
		} else {
			$mail_body .= "Content-Type: text/plain;" . $eol;
		}
		$mail_body .= "\tcharset=\"" . CHARSET . "\"". $eol;
		$mail_body .= "Content-Transfer-Encoding: 7bit" . $eol;
		$mail_body .= $eol;
		$mail_body .= $original_body;
		$mail_body .= $eol . $eol;

		for ($at = 0; $at < sizeof($attachments); $at++) {
			$attachment_info = $attachments[$at];
			$filename = ""; $filepath = "";
			if (is_array($attachment_info)) {
				if (sizeof($attachment_info) == 1) {
					$filepath = $attachment_info[0];
				} else if (sizeof($attachment_info) > 1) {
					$filename = $attachment_info[0];
					$filepath = $attachment_info[1];
				}
			} else {
				$filepath = $attachment_info;
			}
			if (@file_exists($filepath) && !@is_dir($filepath)) {
				if (!$filename) {
					$filename = basename($filepath);
				}
				// read entire file into filebody
				$fp = fopen($filepath, "rb");
				$filebody = fread($fp, filesize($filepath));
				fclose($fp);
				$file_base64 = chunk_split(base64_encode($filebody));

				$mail_body .= "--" . $boundary . $eol;
				if (preg_match("/\.gif$/", $filename)) {
					$mail_body .= "Content-Type: image/gif;" . $eol;
				} else {
					$mail_body .= "Content-Type: application/octet-stream;" . $eol;
				}
				$mail_body .= "\tname=\"" . $filename . "\"" . $eol;
				$mail_body .= "Content-Transfer-Encoding: base64" . $eol;
				$mail_body .= "Content-Disposition: attachment;" . $eol;
				$mail_body .= "\tfilename=\"" . $filename . "\"" . $eol;
				$mail_body .= $eol;
				$mail_body .= $file_base64;
				$mail_body .= $eol . $eol;
			}
		}
		// end multipart message
		$mail_body .= "--" . $boundary . "--" . $eol;
		$mail_body .= $eol;
	} else {
		$mail_headers .= ($mail_type) ? $eol . "Content-Type: text/html" : $eol . "Content-Type: text/plain";
	}

	return mail($mail_to, $mail_subject, $mail_body, $mail_headers);
}

function va_timestamp($date_array = "")
{
	global $va_time_shift;
	if (is_array($date_array)) {
		$timestamp = mktime ($date_array[HOUR], $date_array[MINUTE], $date_array[SECOND], $date_array[MONTH], $date_array[DAY], $date_array[YEAR]);
	}	else {
		$time_shift = (isset($va_time_shift)) ? $va_time_shift : 0;
		$timestamp = time() + $time_shift;
	}
	return $timestamp;
}

function get_eol()
{
	if (strtoupper(substr(PHP_OS,0,3)=='WIN')) {
		$eol = "\r\n";
	} else if (strtoupper(substr(PHP_OS,0,3)=='MAC')) {
		$eol = "\r";
	} else {
		$eol = "\n";
	}
	return $eol;
}

/**
 * Execute regexps to remove substrings
 *
 * @param string $str
 * @param array $patters regexps
 * @return string
 */
function ltrimstr($str, $patters) {
	if ($str != "" && is_array($patters)) {
		foreach ($patters as $patter) {
			$regexp = "/^" . $patter . "/";
			if (preg_match($regexp, $str, $matches)) {
				return trim(preg_replace($regexp, "", $str));
			}
		}
	}
	return trim($str);
}


?>