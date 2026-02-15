#!/usr/local/bin/php4 -q
<?php
	set_time_limit(300);
	define("DEBUG_TIME",0);
	define("MONTHDAY",2592000);
	
	include_once("./db_mysql.inc");
  	include_once("./includes/db_connect.php");
  	include_once("./includes/common_functions.php");
	
	$advanced_url = "http://www.viart.com/support_reports.php";
	//$advanced_url = "http://localhost/viartcom/support_reports.php";
	
	$db = new DB_Sql();
	$db->Database = DATABASE_NAME;
	$db->User     = DATABASE_USER;
	$db->Password = DATABASE_PASSWORD;
	$db->Host     = DATABASE_HOST;
	
	/**/
	//$db->query("DELETE FROM support_import");
	//$db->query("DELETE FROM support_reports");
	/**/
	$initial = getmicrotime();
	$file_log = "./import_for_sanuch.log";
	if (file_exists($file_log)) { @unlink($file_log);}
	
	show_microtime("Start script");
	$support_users = array();
	$support_users_ids = array();
	$sql  = " SELECT user_id, helpdesk_user_id, CONCAT(first_name,' ',last_name) as user_name FROM users WHERE is_viart_support=1 AND is_deleted IS NULL";
	$db->query($sql);
	while($db->next_record()) {
		$user_id = $db->f("user_id");
		$helpdesk_user_id = $db->f("helpdesk_user_id");
		$user_name = $db->f("user_name");
		$support_users[$user_id] = $helpdesk_user_id;
		$support_users_ids[$helpdesk_user_id] = $user_id;
	}
	show_microtime("Get user_id");
	
	if (sizeof($support_users_ids)) {
		$last_update_unix = mktime(0,0,0,1,1,2008);
		$last_update_id = 0;
		$sql  = " SELECT import_id, DATE_FORMAT(update_date,'%Y-%m-%d %H:%i:%s') as last_update, UNIX_TIMESTAMP(update_date) as last_update_unix ";
		$sql .= " FROM support_import ";
		//$sql .= " GROUP BY update_date";
		$sql .= " ORDER BY update_date DESC LIMIT 0,1";
		$db->query($sql);
		if ($db->next_record()) {
			$last_update_unix = $db->f("last_update_unix");
			$last_update_id	= $db->f("import_id");
		}
		$last_update = date("Y-m-d H:i:s",$last_update_unix);
		$now_unix = mktime();
		$date_diff = datediff($last_update_unix,$now_unix);
		$diff_month = $date_diff["month"];
		$month = date("n");
		$year =  date("Y");
		$users = array();
		show_microtime("Different  month (" . $diff_month . ")");
		if ($diff_month != 0) {
			while ($diff_month >= 0) {
				$new_date_unix = mktime(0,0,1,$month - $diff_month,1,$year);
				$new_date_start_unix = $new_date_unix - MONTHDAY;//mktime(0,0,1,$month - $diff_month - 1,1,$year);
				$new_date_last_day = date("t", $new_date_start_unix);
				$new_date_finish_unix = mktime(0,0,1,$month - $diff_month,$new_date_last_day,$year);
				$new_date = date("Y-m-d H:i:s", $new_date_start_unix);
				$sql  = " SELECT user_id, DATE_FORMAT( `report_date` , '%m%d' ) AS days , UNIX_TIMESTAMP(MAX( report_date )) as time_off ";
				$sql .= " FROM time_report ";
				$sql .= " WHERE (UNIX_TIMESTAMP(report_date) BETWEEN " . $new_date_start_unix . " AND " . $new_date_finish_unix . ") ";
				//$sql .= "	ANd DATE_FORMAT(report_date,'%Y-%m') = DATE_FORMAT('" . $new_date . "','%Y-%m')";
				if (sizeof($support_users_ids) == 1) {
					list($key, $helpdesk_user_id) = each($support_users_ids);
					$sql .= " AND user_id = " . ToSQL($helpdesk_user_id,"number");
				} else if (sizeof($support_users_ids)>1) {
					$sql .= " AND user_id in (" . implode(",",$support_users_ids) . ") ";
				}
				$sql .= "GROUP BY user_id, days";
				$db->query($sql);
				if ($db->num_rows()) {
					while($db->next_record()) {
						$user_id = $db->f("user_id");
						$days = $db->f("days");
						$time_off = $db->f("time_off");
						$helpdesk_user_id = $support_users[$user_id];
						$users[$helpdesk_user_id][$days] = $time_off;
					}
				}
				
				//var_dump($new_date);echo"\r\n";
				show_microtime("Call import_data for date " . date("Y-m-d",$new_date_unix));
				import_data($new_date_unix);
				
				$users = array();
				$diff_month--;
			}
		} else {
			$last_month = date("n",$last_update_unix);
			$last_year = date("Y",$last_update_unix);
			$last_update_unix_start = mktime(0,0,1,$last_month-1,1,$last_year);
			$last_update_unix_finish = $last_update_unix;
			$sql  = " SELECT user_id, DATE_FORMAT( `report_date` , '%m%d' ) AS days , UNIX_TIMESTAMP(MAX( report_date )) as time_off ";
			$sql .= " FROM time_report ";
			$sql .= " WHERE UNIX_TIMESTAMP(report_date) >= " . $last_update_unix_start;// . " AND UNIX_TIMESTAMP(report_date) <= " . $last_update_unix_finish . " ";
			if (sizeof($support_users_ids) == 1) {
				list($key, $helpdesk_user_id) = each($support_users_ids);
				$sql .= " AND user_id = " . ToSQL($helpdesk_user_id,"number");
			} else if (sizeof($support_users_ids)>1) {
				$sql .= " AND user_id in (" . implode(",",$support_users_ids) . ") ";
			}
			$sql .= "GROUP BY user_id, days";
			$db->query($sql);
			if ($db->num_rows()) {
				while($db->next_record()) {
					$user_id = $db->f("user_id");
					$days = $db->f("days");
					$time_off = $db->f("time_off");
					$helpdesk_user_id = $support_users[$user_id];
					$users[$helpdesk_user_id][$days] = $time_off;
				}
			}
			show_microtime("Call import_data for date " . date("Y-m-d",$last_update_unix));
			import_data($last_update_unix);
		}
	}
	
	exit(0);
	/*
	*	Function
	*/
	function datetime2array($datetime)
	{
		$day = ""; $month = ""; $year = "";
		$hour = ""; $minut = ""; $second = "";
		
		$datetime_arr = explode(" ",$datetime);
		if (is_array($datetime_arr) && sizeof($datetime_arr)==2) {
			$date = $datetime_arr[0];
			$time = $datetime_arr[1];
			
			$date_arr = explode("-",$date);
			if (is_array($date_arr) && sizeof($date_arr)==3) {
				$year	= $date_arr[0];
				$month	= $date_arr[1];
				$day	= $date_arr[2];
			}
			$time_arr = explode(":",$time);
			if (is_array($time_arr) && sizeof($time_arr)==3) {
				$hour	= $time_arr[0];
				$minut	= $time_arr[1];
				$second	= $time_arr[2];
			}
			
		} else {
			$date_arr = explode("-",$datetime);
			if (is_array($date_arr) && sizeof($date_arr)==3) {
				$year	= $date_arr[0];
				$month	= $date_arr[1];
				$day	= $date_arr[2];
			} else {
				$time_arr = explode(":",$datetime);
				if (is_array($time_arr) && sizeof($time_arr)==3) {
					$hour	= $time_arr[0];
					$minut	= $time_arr[1];
					$second	= $time_arr[2];
				}
			}
		}
		return array($year,$month,$day,$hour,$minut,$second);
	}
	
	function datediff($first=0,$second=0,$diff=0)
	{
		$result = array();
		$result_time = -1;
		if (strlen($first) && strlen($second) && $first>0 && $second>0) {
			$result_time = abs($first - $second);
		} else if (strlen($diff) && $diff != 0) {
			$result_time = $diff;
		}
		if ($result_time>-1) {
			//difference in seconds 
			$result["s"] = $result_time;
			//difference in minutes
			$result["m"] = floor($result_time/60);
			 //difference in hours
			$result["h"] = floor($result_time/3600);
			//difference in days 
			$result["d"] = floor($result_time/86400);
			//difference in weeks
			$result["w"] = floor($result_time/604800);
			if ($first>0 && $second>0) {
				//difference in years 
				$result["y"] = abs(date("Y",$first)-date("Y",$second));
				//difference in months 
				$monthBegin = (date("Y",$first)*12)+date("n",$first);
				$monthEnd = (date("Y",$second)*12)+date("n",$second);
				$result["month"] = $monthEnd - $monthBegin;
			}
		}
		
		return $result;
	}
	
	function import_data($last_update)
	{
		global $db, $users,$last_update_id,$advanced_url,$support_users_ids;
		
		if (sizeof($users) && $last_update) {
			$return_value = "";
			
			$ch = curl_init();
			show_microtime("Create  curl");
			if ($ch) {
				$post_params  = "users_support_days=" . serialize($users);
				$post_params .= "&selected_date=" . $last_update;
				$post_params .= "&local=" . date("Z");

				curl_setopt($ch, CURLOPT_URL, $advanced_url);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
				curl_setopt($ch, CURLOPT_HEADER, 0);
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $post_params);
				//curl_setopt($ch, CURLOPT_RETURNTRANSFER, 0);
				curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_TIMEOUT,60);

				$return_value = curl_exec($ch);
				curl_close($ch);
				
				//var_dump(1);echo"\r\n";
				/**/
				if (strlen($return_value)) {
					show_microtime("Return request for date " . date("Y-m-d",$last_update));
					$report_array = array();
					$report_array = @unserialize($return_value);
					if (is_array($report_array) && sizeof($report_array)) {
						if (!$last_update_id) {
							$sql = "INSERT INTO support_import SET update_date = '" . date("Y-m-d H:i:s",$last_update) ."' ";
							$db->query($sql);
							$sql = "SELECT LAST_INSERT_ID() as last_id;";
							$db->query($sql);
							$db->next_record();
							$last_update_id = $db->f("last_id");
						} else {
							$sql  = " SELECT * FROM support_import ";
							$sql .= " WHERE DATE_FORMAT(update_date,'%m') = DATE_FORMAT('".date("Y-m-d H:i:s",$last_update)."','%m') ";
							$sql .= " 	AND DATE_FORMAT(update_date,'%Y') = DATE_FORMAT('".date("Y-m-d H:i:s",$last_update)."','%Y') ";
							$db->query($sql);
							if ($db->next_record()) {
								$sql = "UPDATE support_import SET update_date = '" . date("Y-m-d H:i:s",$last_update) ."' WHERE import_id = " . ToSQL($last_update_id,"number");
								$db->query($sql);
								$sql = "DELETE FROM support_reports WHERE import_id = " . ToSQL($last_update_id,"number");
								$db->query($sql);
							} else {
								$sql = "INSERT INTO support_import SET update_date = '" . date("Y-m-d H:i:s",$last_update) ."' ";
								$db->query($sql);
								$sql = "SELECT LAST_INSERT_ID() as last_id;";
								$db->query($sql);
								$db->next_record();
								$last_update_id = $db->f("last_id");
							}
						}
						foreach ($report_array as $helpdesk_user_id => $reports) {
							if (!isset($support_users_ids[$helpdesk_user_id])) {
								//var_dump($report_array);echo"\r\n";exit;
							} else {
								$user_id = $support_users_ids[$helpdesk_user_id];
								foreach($reports as $type => $count) {
									if (!is_numeric($type)) {
										switch ($type) {
											case "feedback_rating" : $type = 998; break;
											case "reassign" : $type = 997; break;
										}
									}
									$type_id = $type + 1;
									$sql  = " INSERT INTO support_reports ";
									$sql .= " SET user_id = " . ToSQL($user_id,"number");
									$sql .= ", type_bonus_id = " . ToSQL($type_id,"number");
									$sql .= ", count_bonus = " . ToSQL($count,"number",false);
									$sql .= ", import_id = " . ToSQL($last_update_id,"number",false);
									$db->query($sql);
								}
							}
						}
					} else {
						var_dump($return_value);echo"\r\n";exit;
					}
				}
				/**/

			} else { //error
			}
		}
		return true;
	}
	
	function getmicrotime()
	{ 
    	list($usec, $sec) = explode(" ",microtime()); 
    	return ((float)$usec + (float)$sec); 
    } 
	
	function show_microtime($event)
	{
		global $initial,$file_log;
		if (DEBUG_TIME) {
			$from_start = ($initial ? (getmicrotime() - $initial) : 0);
			$fp = @fopen($file_log,"a");
		    if ($fp) {
				fwrite($fp,number_format($from_start,4).": ".$event."\n");	
				fclose($fp);
			} else {
				echo number_format($from_start,4).": ".$event."\n";
			}
		}
	}
?>