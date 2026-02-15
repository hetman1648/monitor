#!/usr/local/bin/php -q
<?php

include_once("./includes/db_connect.php");
include_once("./includes/common_functions.php");
include_once("./includes/date_functions.php");
include_once("./db_mysql.inc");

$db = new DB_Sql();
$db->Database = DATABASE_NAME;
$db->User     = DATABASE_USER;
$db->Password = DATABASE_PASSWORD;
$db->Host     = DATABASE_HOST;

$mailto		 = "sanuch@viart.com.ua";
$mailto		.= ",artem@viart.com.ua";
$mailfrom	= "monitor@viart.com.ua";
$subject	= "Daily reports";
$headers	= "";
$message	= "";
$style = array();
$style["HeaderTD"] = "background-color: navy; text-align: center; border-style: outset; border-width: 1; font-size: 12pt; color: #FFFFFF; font-weight: bold;";
$style["DataTD"] = "background-color: #f6f2f2; border-style: inset; border-width: 0; font-size: 10pt; padding: 0.05em 0.6em; font-size: 11pt;";
$style["CaptionTD"] = "background-color: #D0D0D0; border-style: inset; border-width: 0; font-size: 12pt; color: #000000; font-weight: bold";

$sqldate = " AND DATE(mr.date_added)='".date("Y-m-d",mktime(0,0,0,date("m"),date("d"),date("Y")))."'  ";
$where = " AND u.manager_id<>-1  AND u.is_viart=1 AND NOT mr.morning_notes IS NULL AND u.is_deleted is NULL ";
$sql = "SELECT	mr.report_id AS report_id,
				u.user_id AS user_id,
				u.manager_id AS manager_id,
				CONCAT(u.first_name,' ',u.last_name) AS user_name,
				CONCAT(mu.first_name,' ',mu.last_name) AS manager_name,
				mr.morning_notes AS morning_notes,
				mr.evening_notes AS evening_notes,
				mr.points AS points,
				DATE(mr.date_added) as date_added
		FROM	users u
				LEFT JOIN users mu ON (mu.user_id=u.manager_id)
				LEFT JOIN managing_reports mr ON (mr.user_id=u.user_id ".$sqldate.")
		WHERE	1 ".$where."
		ORDER BY manager_name, user_name";
$db->query($sql,__FILE__,__LINE__);
if ($db->num_rows()>0) {	$daily = array();
	$index = 0;
	$max_manager = 0;
	$max_user = 0;
	$manager_name = "";
	$user_name = "";	while ($db->next_record()) {		$daily[$index]["manager_name"]	= $db->Record["manager_name"];
		$daily[$index]["user_name"]		= $db->Record["user_name"];
		$daily[$index]["morning_notes"]	= $db->Record["morning_notes"];
		$daily[$index]["morning_notes"]	= str_replace("http://www.viart.com.ua/monitor/"," ",$daily[$index]["morning_notes"]);
		$daily[$index]["morning_notes"]	= str_replace("viart.com.ua/monitor/"," ",$daily[$index]["morning_notes"]);
		$daily[$index]["morning_notes"]	= nl2br($daily[$index]["morning_notes"]);
		//$daily[$index]["morning_notes"]	= wordwrap(nl2br($daily[$index]["morning_notes"]),40,"<br>&nbsp;\n\t");
		$daily[$index]["evening_notes"]	= $db->Record["evening_notes"];
		$daily[$index]["evening_notes"]	= str_replace("http://www.viart.com.ua/monitor/"," ",$daily[$index]["evening_notes"]);
		$daily[$index]["evening_notes"]	= str_replace("viart.com.ua/monitor/"," ",$daily[$index]["evening_notes"]);
		$daily[$index]["evening_notes"]	= nl2br($daily[$index]["evening_notes"]);
		//$daily[$index]["evening_notes"]	= wordwrap(nl2br($daily[$index]["evening_notes"]),40,"<br>&nbsp;\n\t");
		$daily[$index]["points"]		= $db->Record["points"];
        if (strlen($daily[$index]["manager_name"]) > $max_manager) {        	$max_manager = strlen($daily[$index]["manager_name"]);
        }
        if (strlen($daily[$index]["user_name"]) > $max_user) {
        	$max_user = strlen($daily[$index]["user_name"]);
        }
        if ((strlen($daily[$index]["morning_notes"]) == 0) && (strlen($daily[$index]["evening_notes"]) == 0)) {        	$index--;
        }
		$index++;	}

    $message = "<html><head><title>Daily reports</title></head><body><table border='0'>";
    $message .= "<tr><td colspan='5' style='".$style["HeaderTD"]."'>Daily report today on ".date("jS F Y",mktime(0,0,0,date("m"),date("d"),date("Y")))."</td>";
    $message .= "<tr><td style='".$style["CaptionTD"]."' align='center'>Manager</td>
    				<td style='".$style["CaptionTD"]."' align='center'>User</td>
    				<td style='".$style["CaptionTD"]."' align='center'>Morning Reports</td>
    				<td style='".$style["CaptionTD"]."' align='center'>Evening Reports</td>
    				<td style='".$style["CaptionTD"]."' align='center'>Points</td></tr>";
	for ($i=0; $i<sizeof($daily); $i++) {		$line = "<tr valign='top'><td style='".$style["DataTD"]."' nowrap>";		if (($manager_name == "") || ($manager_name != $daily[$i]["manager_name"])) {			$manager_name = $daily[$i]["manager_name"];
			$line .= $manager_name;
		} else {
			$line .= "&nbsp;";		}
		$line .= "</td>";
		$line .= "<td style='".$style["DataTD"]."'>".$daily[$i]["user_name"]."</td>";
		$line .= "<td style='".$style["DataTD"]."'>".$daily[$i]["morning_notes"]."</td>";
		$line .= "<td style='".$style["DataTD"]."'>".$daily[$i]["evening_notes"]."</td>";
		$line .= "<td style='".$style["DataTD"]."' align='center'>".$daily[$i]["points"]."</td>";
        $line .= "</tr>";
		$message .= $line;	}
	$message .= "<tr style='".$style["CaptionTD"]."'><td colspan='5'>&nbsp;</td></tr>";
	$message .= "</table></body></html>";}
else {	$message = "Daily reports are not in place!\r\n";}
$headers = 'From: '.$mailfrom."\r\n";
$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";

@mail($mailto,$subject,$message,$headers);
exit();
?>