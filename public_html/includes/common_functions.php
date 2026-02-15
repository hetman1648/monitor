<?php
	function get_setting_value($settings_array, $setting_name, $default_value = "") {
		return (is_array($settings_array) && isset($settings_array[$setting_name]) && strlen($settings_array[$setting_name])) ? $settings_array[$setting_name] : $default_value;
	}
	
	
	function get_db_value($sql) {
		global $db;

		$db->query($sql);
		if ($db->next_record()) {
			return $db->f(0);
		} else  {
			return "";
		}
	}
	
	function has_permission($perm)/////?
	{
		global $_SESSION;//$session_perms;
		$privilege_id = GetSessionParam("privilege_id");

		return $_SESSION["session_perms"][$perm];
	}

	// email notifiactions
	function send_enotification($message_name, $tags)
	{
		global $db;
		$email_to = "";
		$subject = "";
		$message = "";

		$sql = "SELECT user_id, email, privilege_id FROM users ORDER BY privilege_id";
		$db->query($sql,__FILE__,__LINE__);
		$people = array();
		$privilege_people = array();
		$privilege_id = 0;

		// saving all users to array
		while($db->next_record())
		{
			if ($privilege_id != $db->f("privilege_id"))
			{
				$people[$privilege_id] = $privilege_people;
				$privilege_people = array();
				$privilege_id = $db->f("privilege_id");
			}
			$privilege_people[$db->f("user_id")] = $db->f("email");
		}
		$people[$privilege_id] = $privilege_people;


		// proccess each message type separately
		switch ($message_name)
		{
			// project created
			case MSG_PROJECT_CREATED:
				// sending emails only to PM
				$total_pm = sizeof($people[PRIV_PM]);
				$k = 1;
				foreach($people[PRIV_PM] as $key => $val)
				{
					$email_to .= $val;

					// add comma to email address
					if ($k != $total_pm) $email_to .= ",";
					$message_file = "project_created";
					$k++;
				}

				$subject = "New project -{PROJECT_TITLE}- was created";
				list($partner_id, $from) = each ($people[PRIV_PARTNER]);

				break;

			// task created
			case MSG_TASK_CREATED:
				// sending emails only to PM
				$email_to = implode(",", $people[PRIV_PM]);
				if ((GetSessionParam("privilege_id")==PRIV_PM) && ($tags["responsible_user_id"]))
				{
					if (strlen($email_to)) $email_to .= ",";
					$email_to .= "lpeter@mail-in.net";
				}								
				$message_file = "task_created";

				$subject = "{project_title}: '{task_title}' Monitor Task Updated for {responsible_user_name}";
				list($partner_id, $from) = each ($people[PRIV_PARTNER]);

				break;

			case MSG_TASK_COMPLETED:
				$message_file = "task_completed";
				$subject = "Task completed";
				$email_to = "enquiries@greyrock.co.uk";
				$from = "artem@viart.com.ua";
				$email_to = $from;

				break;

			case MSG_TASK_UPDATED:
				$message_file = "task_updated";
				$subject = "{project_title}: '{task_title}' Monitor Task Updated for {responsible_user_name}";
				$email_to = "";

				break;

			// message received
			case MSG_MESSAGE_RECEIVED:
				$subject="{project_title}: '{task_title}' Monitor Task Message Added for {responsible_user_name}";
				$message_file = "message_received";

				if ($tags["privilege_id"]==PRIV_PARTNER) // partner send message
				{
					$from = "enquiries@greyrock.co.uk";
					$email_to = "artem@viart.com.ua,rvitaliy@yahoo.com";
				}
				else
				{
					if ($tags["privilege_id"] == PRIV_PM || $tags["privilege_id"] == PRIV_ARCHITECT)
					{
						// things are become complicated...
						// 1) message to Partner
						if (isset($tags["created_privilege_id"]) && $tags["created_privilege_id"] == PRIV_PARTNER)
						{
							$email_to = "enquiries@greyrock.co.uk,artem@viart.com.ua";
							$from = "artem@viart.com.ua";
						}
						else // 2) message to Developer
						{
							$email_to = "artem@viart.com.ua,rvitaliy@yahoo.com";
							$from = "artem@viart.com.ua";
						}
					}
					else // send to PM
					{
						$from = "monitor@viart.com.ua";
						$email_to = "artem@viart.com.ua";
					}
				}

				break;
		} //case

		$message = join("", file("./templates/".$message_file.".txt"));

		// replace custom tags
		foreach ($tags as $tag_name=>$tag_value)
		{
			$tag_value = stripslashes($tag_value);
			$message = str_replace("{".$tag_name."}", $tag_value, $message);
			$subject = preg_replace("/\{".$tag_name."\}/", $tag_value, $subject);
		}
		$message = str_replace("\r\n", "", $message);
		$message = str_replace("\n", "", $message);

		// send message itself
		$from = "monitor@viart.com.ua";

		$r = GetParam("responsible_user_id");
		if ($r == '') {
			if (isset($tags['responsible_user_id'])) {
				$r = $tags['responsible_user_id'];
			}
			else {
				$r = 0;
			}
		}

		$sql = "SELECT privilege_id, email FROM users WHERE user_id = " . $r;
		$db->query($sql,__FILE__,__LINE__);
		$pid = 0;
		if ($db->next_record()) {
			$pid = $db->Record["privilege_id"];
			$email_to = $db->Record["email"];
		}

		$emails_copy = "";
		if ($tags["task_id"])
		{
			$project_id = 0;
			$sql = "SELECT p.project_id, p.emails_copy from tasks AS t, projects AS p WHERE t.project_id = p.project_id AND t.task_id = " . ToSQL($tags["task_id"], "integer");
			$db->query($sql,__FILE__,__LINE__);
			if ($db->next_record())
			{
				$project_id = $db->Record["project_id"];
				$emails_copy = $db->Record["emails_copy"];
			}
		}

		//UPDATE projects SET emails_copy='enquiries@greyrock.co.uk,james.brown@spotlightguides.co.uk,simon.pitts@spotlightguides.co.uk,chris.sutherland@spotlightguides.co.uk' WHERE project_id=25 or project_id=26;

		$emails_copy = str_replace($email_to, "", $emails_copy);
		$emails_copy = str_replace(",,", ",", $emails_copy);
		if (substr($emails_copy, 0, 1) == ",") $emails_copy = substr($emails_copy, 1);
		if (substr($emails_copy, -1) == ",") $emails_copy = substr($emails_copy, 0, -1);

		@mail($email_to, $subject, $message, "From: $from\nCc: $emails_copy\nReply-To: $from\nContent-Type:text/html; charset=iso-8859-1");

		if (($r==3) && $project_id != 25 && $project_id != 26) {
			@mail("5882458@sms.umc.com.ua",strip_tags($tags["PROJECT_TITLE"]) . " " . strip_tags($tags["TASK_TITLE"]) . " " . strip_tags($tags["message"]),"","From: $from");
		}
	}

	// convert MySQL date to user format
	function date_to_string($string_date)
	{
		global $month;
		$yyyy=substr($string_date,0,4);
		$mm=substr($string_date,5,2);
		$dd=substr($string_date,8,2);

		if ($month[$mm])
		return $dd." ".substr($month[$mm],0,3)." ".$yyyy;
		else
		return "";
	}

	// convert MySQL date to user format
	function date_to_array($string_date)
	{
		$res_array =  array();
		$res_array["YEAR"]=substr($string_date,2,2);
		$res_array["MONTH"]=substr($string_date,5,2);
		$res_array["DAY"]=substr($string_date,8,2);
		return $res_array;
	}


	// returns string that contains <option> tags with all months truncated to first 3 symbols
	// option with $cur_month is selected
	function get_month_options($cur_month)
	{
		global $month;
		$res_str="";
		//if ($cur_month != ""){
			foreach ($month as $key => $val) {
				if ($cur_month == $key) { $selected="selected";}
					else { $selected="";}
				$res_str.="<option $selected value=\"$key\">".substr($val,0,3)."</option>";
			}
		//}
		return $res_str;
	}

	// returns string that contains <option> tags with all months
	// option with $cur_month is selected
	function GetMonthOptions($cur_month)
	{
		global $month;

		$res_str = "";
		foreach ($month as $key => $val) {
			if ($cur_month == $key) { $selected = "selected";}
				else { $selected = "";}
			$res_str .= "<option $selected value=\"$key\">".$val."</option>";
		}
		return $res_str;
	}

	// returns string that contains <option> tags with years
	// option with $cur_year is selected
	function GetYearOptions($start_year, $end_year, $cur_year)
	{
		$res_str = "";
		for ($i = $start_year; $i <= $end_year; $i++)
		{
			if ($cur_year == $i) $selected = "selected"; else $selected = "";
			$res_str .= "<option $selected value=\"$i\">".$i."</option>";
		}
		return $res_str;
	}

	function GetPeriodOptions($period_selected)
	{
		$period_option=array("this_week","last_week","prev_week","this_month","last_month","prev_month","this_year");
		$period_titles=array("This week","Last week (7 days)","Previous week","This month","Last month (30 days)","Previous month","This year");

		$res_str = "";
		for ($i = 0; $i < sizeof($period_option); $i++)
		{
			if ($period_selected == $period_option[$i]) $selected = "selected"; else $selected = "";
			$res_str .= "<option $selected value=\"".$period_option[$i]."\">".$period_titles[$i]."</option>";
		}
		return $res_str;
	}

	function to_mysql_date($year,$month,$day)
	{		return "20".trim($year)."-".trim($month)."-".trim($day);		/*
		if (intval($day) == 0) {$day = "00";}
		 else {$day = trim($day);}
		if (intval($month) == 0) {$month = "00";}
		 else {$month = trim($month);}
		if (intval($year) == 0) {$year = "0000";}
		 else {
		 	if (strlen($year)==4) {$year = trim($year);}
		 	 else {$year = "20".trim($year);}
		 }
		return $year."-".$month."-".$day;
		*/
	}



	function ToHTML($strValue)
	{
		
		return htmlentities($strValue, ENT_COMPAT, 'ISO-8859-15');
		// return htmlentities($strValue);
		// return htmlspecialchars($strValue);
	}

	function ToURL($strValue)
	{
		return urlencode($strValue);
	}

	// strFieldName - name or number of field
	function GetValueHTML($db, $strFieldName)
	{
		global $db;
		return htmlspecialchars(GetValue($db, $strFieldName));
	}

	function GetValue($db, $strFieldName)
	{
		global $db;
		if(strlen($strFieldName))
		{
			return $db->f($strFieldName);
		}
		else
		return "";
	}

	function GetParam($ParamName)
	{
		global $HTTP_POST_VARS;
		global $HTTP_GET_VARS;
		global $_GET;
		global $_POST;

		$ParamValue = "";
		if(isset($HTTP_POST_VARS[$ParamName])) {
		$ParamValue = $HTTP_POST_VARS[$ParamName];
		}elseif(isset($HTTP_GET_VARS[$ParamName])) {
		$ParamValue = $HTTP_GET_VARS[$ParamName];
		}elseif(isset($_GET[$ParamName])) {
		$ParamValue = $_GET[$ParamName];
		}elseif(isset($_POST[$ParamName])) {
		$ParamValue = $_POST[$ParamName];
		}

		return $ParamValue;
	}

	function GetSessionParam($ParamName)
	{
		global $HTTP_POST_VARS;
		global $HTTP_GET_VARS;
		global ${$ParamName};

		$ParamValue = "";
		if(	!isset($HTTP_POST_VARS[$ParamName]) &&
			!isset($HTTP_GET_VARS[$ParamName]) &&
//			session_is_registered($ParamName)) {				$ParamValue = $_SESSION[$ParamName];
			isset($_SESSION[$ParamName])) {				$ParamValue = $_SESSION[$ParamName];
			}

		return $ParamValue;
	}

	function SetSessionParam($ParamName, $ParamValue)
	{
		//global $$ParamName;
//		if(session_is_registered($ParamName)) {
		if(isset($_SESSION[$ParamName])) {
			// session_unregister($ParamName);
			unset($_SESSION[$ParamName]);
		}
		$$ParamName = $ParamValue;
		$_SESSION[$ParamName] = $ParamValue;
		//session_register($ParamName);
	}

	function is_number($string_value)
	{
		if(is_numeric($string_value) || !strlen($string_value))
			return true;
		else
			return false;
	}

	function IsParam($ParamValue)
	{
		if($ParamValue)
			return 1;
		else
			return 0;
	}

	function ToSQL($value, $type, $use_null = true, $is_delimiters = true)
	{
		$type = strtolower($type);
		
		if ($value == "") {			
			if ($use_null) { 
				return "NULL";
			} elseif ($type == "number" || $type == "integer" || $type == "float") {
				$value = 0;
			}
		} elseif ($type == "number") {
			return doubleval($value);
		} elseif ($type == "integer") {
			return intval($value);
		} elseif ($type == "date") {
			if (is_array($value)) {
				$value = date("Y-m-d", mktime(0, 0, 0, $value["MONTH"], $value["DAY"], $value["YEAR"]));
			} else {
				if (ereg("([0-9]{4})(-|\\|\/){1}([0-9]{1,2})(-|\\|\/){1}([0-9]{1,2})", $value, $t)){
					if (checkdate($t[3],$t[5],$t[1])) { 
						$value = date("Y-m-d", mktime(0, 0, 0, $t[3], $t[5], $t[1]));
					} else { 
						$value = "0000-00-00";
					}
				} else { 
					$value = "0000-00-00";
				}
			}
			$value = "'" . $value . "'";
		} /*elseif ($type == "string") { 
			$value = addslashes($value);
			$value = "'" . $value . "'";
		}*/ else {
			if(get_magic_quotes_gpc() == 0) {
				$value = str_replace("'", "''", $value);
				$value = str_replace("\\", "\\\\", $value);
			} else {
				$value = str_replace("\\'", "''", $value);
				$value = str_replace("\\\"", "\"", $value);
			}
			if ($is_delimiters)
				$value = "'" . $value . "'";
		}

		return $value;
	}

	function DLookUp($Table, $fName, $sWhere)
	{
		$db_look = new DB_Sql();
		$db_look->Database = DATABASE_NAME;
		$db_look->User     = DATABASE_USER;
		$db_look->Password = DATABASE_PASSWORD;
		$db_look->Host     = DATABASE_HOST;

		$db_look->query("SELECT " . $fName . " FROM " . $Table . " WHERE " . $sWhere,__FILE__,__LINE__);
		if($db_look->next_record())
		return $db_look->f(0);
		else
		return "";
	}


	function getCheckBoxValue($sVal, $CheckedValue, $UnCheckedValue, $sType)
	{
		if(!strlen($sVal))
		return ToSQL($UnCheckedValue, $sType);
		else
		return ToSQL($CheckedValue, $sType);
	}

	function getValFromLOV($sVal, $aArr)
	{
		$sRes = "";

		if(sizeof($aArr) % 2 != 0)
		$array_length = sizeof($aArr) - 1;
		else
		$array_length = sizeof($aArr);
		reset($aArr);

		for($i = 0; $i < $array_length; $i = $i + 2)
		{
			if($sVal == $aArr[$i]) $sRes = $aArr[$i+1];
		}

		return $sRes;
	}

	function GetOptions($table, $field1, $field2, $selected_value, $where="")
	{
		//global $db;
		$db = new DB_Sql();
		$db->Database = DATABASE_NAME;
		$db->User     = DATABASE_USER;
		$db->Password = DATABASE_PASSWORD;
		$db->Host     = DATABASE_HOST;

		$list = "";
		$sql = " SELECT $field1, $field2 FROM $table ".$where;//." ORDER BY $field2";
		$s = "/( as ){1}/i";
		$matches = preg_split($s, $field2);
		if (count($matches)==2) { $field2 = $matches[1];}
		/*
		if (strpos(strtolower($field2)," as ")>0){
			$p=strpos(strtolower($field2)," as ");
			$field2=substr($field2,$p+4,strlen($field2));
		}
		*/
		$sql .= " ORDER BY $field2";
		$db->query($sql,__FILE__,__LINE__);
		while ($db->next_record()) {
			if ($db->f($field1) == $selected_value) $list .= "<OPTION selected value='".$db->f($field1)."'>".$db->f($field2);
			else $list .= "<OPTION value='".$db->f($field1)."'>".$db->f($field2);
		}
		return $list;
	}

	function Hours2HoursMins($fHours)
	{
		$fHours=Round($fHours*60)/60;
		$mins = sprintf("%02d", floor(($fHours - floor($fHours)) * 60));
		$fHours = floor($fHours) . ":$mins ";
		return $fHours;
	}

	function CheckSecurity($iLevel)
	{
		global $privilege_id;
		global $HTTP_COOKIE_VARS;
		global $db;
		global $perms;

//		if (!session_is_registered("privilege_id")) {
//		if (isset($_SESSION['privilege_id'])) {
		if (!isset($_SESSION["privilege_id"])) {
			// new correction
			if (isset($HTTP_COOKIE_VARS["monitor_login"]))
			{
				if ($HTTP_COOKIE_VARS["monitor_login"])
				{
					$login_array = explode("|",$HTTP_COOKIE_VARS["monitor_login"]);
					if ($login_array[0] && $login_array[1])
					{
						$remembered_login = true;
						$r_login    = $login_array[0];
						$r_password = $login_array[1];
					}
				}
				$db->query("SELECT * FROM users AS u,lookup_users_privileges AS p WHERE u.privilege_id=p.privilege_id AND login ='" . $r_login . "' AND password='" . $r_password . "' AND u.is_deleted IS NULL",__FILE__,__LINE__);
				if ($db->next_record())
				{
					foreach($perms AS $key=>$value)
					{
						$perms[$key] = $db->Record[$key];
					}

					SetSessionParam("UserID", $db->f("user_id"));
					SetSessionParam("privilege_id", $db->f("privilege_id"));
					SetSessionParam("UserName", $db->f("first_name")." ".$db->f("last_name"));
					SetSessionParam("session_perms", $perms);

					if ($remember_me) {
						setcookie("monitor_login", $sLogin . "|" . $sPassword, time()+3600*24*365);
					}
				}
				else
				{
					header("Location: login.php?querystring=" . ToURL(getenv("QUERY_STRING")) . "&ret_page=" . ToURL(getenv("REQUEST_URI")));
					exit;
				}
			}
			else
			{
				header("Location: login.php?querystring=" . ToURL(getenv("QUERY_STRING")) . "&ret_page=" . ToURL(getenv("REQUEST_URI")));
				exit;
			}
		}
		// end new correction

		elseif ($privilege_id < $iLevel) {			
			//header("Location: index.php");
			//exit(0);
		}
	}

	function to_hours($float_hours, $ifnobr=false)
	{
		if ($ifnobr) {
			$nobr ="<NOBR>";
			$nobrc="</NOBR>";
		} else {
			$nobr =""; $nobrc="";
		}
		if ($float_hours > 0.00001) {
			if ($float_hours > 10) {
				$float_hours = $nobr.(round($float_hours / 8)) . " days".$nobrc." ".$nobr."(".round($float_hours)." hr)".$nobrc;
			} else {
				$float_hours=Round($float_hours*60)/60;
				$mins = round(($float_hours - floor($float_hours)) * 60);
				if ($mins < 10) $mins = "0" . $mins;
				$float_hours = floor($float_hours) . ":$mins ";
			}
		} else {
			$float_hours = "";
		}

		return trim($float_hours);
	}

	/**
	 * Return string with javascript initialized array
	 *
	 * @param array $array php array
	 * @param string $name js array string
	 * @param boolean $with_initializing defines add var <variable name> = Object and ending ';'
	 * @param integer $level
	 * @return string
	 */
	function array2js($array, $name = "items", $with_initializing = true, $level = 1)
	{
		if (is_array($array)) {
			$name = str_replace(" ", "_", $name);
			$js_string = "";
			if ($with_initializing) {
				$js_string .= "var " . $name . " = Object();\n";
				$js_string .= $name . " = ";
			}
			$js_string .= "{";
			$arr_elem = array();
			$indent_begin = "";
			$indent_end = "";

			foreach ($array as $key => $element) {
				if (is_array($element)) {
					$element_str = array2js($element, $name, false, ++$level);
					$arr_elem[] = $indent_begin . '"' . $key . '": '.$element_str;
				} else {
					$arr_elem[] = $indent_begin . '"' . $key . '": "'.$element.'"';
				}

			}
			$js_string .= implode(", ", $arr_elem);
			$js_string .= $indent_end ."}";
			if ($with_initializing) {
				$js_string .= ";";
			}
		}

		return $js_string;
	}

	function CountTimeProjects($id = "")
	{
		global $db;
		
		$where = "";
		$projectid = 0;
		if ($id && is_numeric($id)) {
			$where = " WHERE t.task_id= ".ToSQL($id,"integer")." ";
		}

		//sanuch script START
	    $sql = " SELECT p.project_id as project, SUM(tr.spent_hours) as totaltime ";
	    $sql.= " FROM tasks t ";
	    $sql.= " INNER JOIN projects p ON (t.project_id = p.project_id) ";
	    $sql.= " INNER JOIN time_report tr ON (tr.task_id = t.task_id) ";
	    $sql.= $where;
	    $sql.= " GROUP BY p.project_id ";
	    
	    $queries = array();
		$db->query($sql,__FILE__,__LINE__);
		while ($db->next_record()) {
			if ($db->Record["totaltime"]) {
				$totaltime = $db->Record["totaltime"];
				$projectid = $db->Record["project"];
				$queries[] = "UPDATE projects set total_time=$totaltime WHERE project_id=$projectid";
			}
		}

		foreach($queries as $sql) {
			$db->query($sql);
		}
		
		if ($where && $projectid) {
			$where = " AND p2.project_id =".ToSQL($projectid,"integer")." ";
		}

		unset($queries);
	    $queries = array();
/*
		$sql = " SELECT  p1.project_id as project, SUM(p2.total_time) as totaltime ";
		$sql.= " FROM projects p1 ";
		$sql.= " LEFT JOIN projects as p2 ON(p2.parent_project_id=p1.project_id) ";
		if ($where) {
			$sql.= $where." AND p1.parent_project_id is NULL";
		} else {
			$sql.= " WHERE p1.parent_project_id is NULL";
		}
		$sql.= " GROUP BY p1.project_id";
		
		$db->query($sql,__FILE__,__LINE__);
		while ($db->next_record()) {			
			if ($db->Record["totaltime"]) {
				$totaltime = $db->Record["totaltime"];
				$projectid = $db->Record["project"];
				$queries[] = "UPDATE projects set total_time=total_time + $totaltime WHERE project_id=$projectid";				
			}
		}
*/
		foreach($queries as $sql) {
			$db->query($sql);
		}
		
		return true;
	}

	function ReferToSessionParam()
	{		global $HTTP_POST_VARS;
		global $HTTP_GET_VARS;
		global $_SERVER;

		$url = $_SERVER["HTTP_REFERER"]."?";
		foreach ($HTTP_POST_VARS as $key => $value){			$url .= $key."=".$value."&";		}
		foreach ($HTTP_GET_VARS as $key => $value){
			$url .= $key."=".$value."&";
		}
		$url .= "1=1";
		SetSessionParam("url",$url);
	}

	function message_die($msg_text = '', $msg_title = '', $err_line = '', $err_file = '', $sql = '')
	{		$msg_text = $msg_text."<br>In file ".$err_file."on ".$err_line." line.<br>SQL Query: ".$sql."<br>";		echo "<html>\n<body>\n" . $msg_title . "\n<br /><br />\n" . $msg_text . "</body>\n</html>";
		exit;
	}

	function PrintArray($arr, $level=0)
	{		$result = "";
		$probel = "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";		if (is_array($arr)) {			for ($i=0; $i<$level-1; $i++) { $result .= $probel;}
			$result .= "array(".count($arr).") {<br>\n\r";			foreach($arr as $key => $value) {				for ($i=0; $i<$level+1; $i++) { $result .= $probel;}
				if (is_numeric($key)) {
					$result .= "[".$key."] => ";
				} else {
					$result .= "[\"".$key."\"] => ";
				}
				if (is_array($arr[$key])) {
					$level++;
					$result .= PrintArray($arr[$key],$level);
					$level--;
				} else { $result .= "\"".$value."\"<br>\n\r";}			}
			for ($i=0; $i<$level; $i++) { $result .= $probel;}
			$result .= "}<br>\n\r";		}
		return $result;
	}

	function is_manager($user_id)
	{
      	global $db;
      	
		$is_manager = false;
      	$sql = " SELECT user_id FROM users WHERE user_id=".ToSQL($user_id, "Number");
      	$sql.= " AND privilege_id=4 AND (is_deleted=0 OR is_deleted IS NULL) AND is_viart=1 ";
      	$db->query($sql,__FILE__,__LINE__);
      	if($db->num_rows()) {
      		$is_manager = true;
      	}
		return $is_manager;
	}

function insert_responses($task_id, $assign_to_myself)
{
	global $db;
	$previous_responsible_user_id = 0;
	$creation_date = 0;
	$is_planned = 1;
	$sql = "SELECT responsible_user_id, creation_date, is_planned FROM tasks WHERE task_id=".ToSQL($task_id,"Number");
	$db->query($sql,__FILE__,__LINE__);
	if ($db->next_record()) {
		$previous_responsible_user_id = $db->f("responsible_user_id");
		$creation_date = $db->f("creation_date");
		$is_planned = $db->f("is_planned");
	}
	
	$user_id = GetSessionParam("UserID");
	$is_manager = is_manager($user_id);	
	
	if ($assign_to_myself!==false) {
		if ($assign_to_myself==1) {
			$assign_to_myself_sql = 1;
		} else {
			$assign_to_myself_sql = 0;
		}
	} else {
		$assign_to_myself_sql = "NULL";
	}

	if ($is_manager && GetSessionParam("UserID")==$previous_responsible_user_id && $is_planned!=1)
	{
		$last_modified = "NOW()";
		$sql = " SELECT IF ( IF (t.date_reassigned IS NOT NULL, t.date_reassigned, t.creation_date) > ";
		$sql.= " IF(MAX( m.message_date )>t.creation_date, MAX( m.message_date ), t.creation_date), ";
		$sql.= " IF(t.date_reassigned IS NOT NULL, t.date_reassigned, t.creation_date), ";
		$sql.= " IF(MAX( m.message_date )>t.creation_date, MAX( m.message_date ), t.creation_date )) AS last_modified ";
		$sql.= " FROM tasks t LEFT JOIN messages m ON (t.task_id=m.identity_id) ";
		$sql.= " WHERE t.task_id =".ToSQL($task_id,"Number")." GROUP BY t.task_id ";
		$db->query($sql);
		if ($db->next_record())	{
			$last_modified = $db->f("last_modified");
		}	
		
		$current_working_hours = 0;
		$sql = " SELECT TIMEDIFF(report_date, '".$last_modified."') ";
		$sql.= " FROM time_report tr ";
		$sql.= " WHERE '".$last_modified."'>tr.started_date ";
		$sql.= " AND '".$last_modified."'<tr.report_date ";
		$sql.= " AND tr.user_id=".ToSQL($user_id,"Number");
		$db->query($sql);
		if ($db->next_record())	{
			$current_working_hours = get_hours_from_timediff($db->f(0));
		}
				
		$time_report_working_hours = 0;
		$sql = " SELECT SUM(spent_hours) ";
		$sql.= " FROM time_report tr ";
		$sql.= " WHERE '".$last_modified."'<=tr.started_date ";
		$sql.= " AND NOW()>=tr.report_date AND tr.user_id=".ToSQL($user_id, "Number");
		$db->query($sql);
		if ($db->next_record())	{
			$time_report_working_hours = $db->f(0);
		}
	
		$response_time = 0;
		$sql = " SELECT TIMEDIFF(NOW(),IF('".$last_modified."'>started_time, '".$last_modified."', started_time)) ";
		$sql.= " FROM tasks ";
		$sql.= " WHERE task_status_id=1 AND is_closed=0 AND responsible_user_id=".ToSQL($user_id, "Number");
		$db->query($sql);
		if ($db->next_record()) {
			$response_time = get_hours_from_timediff($db->f(0));
		}
		
		$response_time += $time_report_working_hours + $current_working_hours;
			
		$sql = " INSERT INTO responses (date_added, manager_id, task_id, response_time, is_assigned_to_myself) ";
		$sql.= " VALUES (NOW(), ".ToSQL($user_id,"Number",false);
		$sql.= " ,".ToSQL($task_id,"Number",false).", ".ToSQL($response_time,"Number",false);
		$sql.= " , ".$assign_to_myself_sql.")";
		
		$db->query($sql);
	
	}		
}

function update_priorities($task_id)
{
	global $db;
	
	$is_update = false;
	$tasks_array_string = GetParam("tasksArray");
	if (strlen($tasks_array_string)) {
		$tasks_array = explode("|", $tasks_array_string);
		foreach($tasks_array as $task_string) {
			$task = explode(";",$task_string);			
			if (isset($task[1]) && isset($task[2])) {
				$priority_task_id = intval($task[1]);
				if ($priority_task_id==0) {
					$priority_task_id = $task_id;
				}
				$priority_id = intval($task[2]);
				$is_update = set_task_priority($priority_task_id, $priority_id, GetParam("priorities_changed"));
			}
		}
	}
}

function get_hours_from_timediff($string)
{
	$hours = 0;
	if (strlen($string)) {
	$time_array = explode(":",$string);
	if (isset($time_array[0])) {
		$hours = $time_array[0];
		if (isset($time_array[1])) {
			$hours += $time_array[1]/60;
			if (isset($time_array[2])) {
				$hours += $time_array[2]/3600;
			}
		}
	}
	}
	return $hours;	

}	

function get_set_string($set_array, $indexes)
{
	$string = "";
	foreach($indexes as $index=>$value) {
		if (isset($set_array[$index]) && $set_array[$index]!="") {
			if (strlen($string)) {
				$string.=",";
			}
			$string.=$index;
		}
	}
	return $string;
}

function get_set_array($set_string, $indexes)
{
	$array = array();
	
	$set_string_array = explode(",", $set_string);
	foreach($indexes as $index=>$value) {
		$found = false;
		foreach ($set_string_array as $string_index) {
			if ($string_index == $index) {
				$found = true;
			}
		}
		$array[$index] = $found;
	}	
	return $array;
}

function is_allowed($user_id, $author_user_id, $perm_array) {
	global $db;

	$sql = "SELECT a.user_id, a.office_id, a.manager_id, a.privilege_id FROM users a WHERE a.user_id=".ToSQL($author_user_id, "integer");
	$db->query($sql);
	if ($db->next_record()) {
		$a = $db->Record;
	}

	$sql = "SELECT u.user_id, u.office_id, u.manager_id, u.privilege_id FROM users u WHERE u.user_id=".ToSQL($user_id, "integer");
	$db->query($sql);
	if ($db->next_record()) {
		$u = $db->Record;
	}
	
	$is_allowed = false;
	if (isset($u) && is_array($perm_array)) {
		//all
		if (isset($perm_array["ALL"]) && $perm_array["ALL"]) {
			$is_allowed = true;
		} else {
			if (isset($perm_array["MANAGERS"]) && $perm_array["MANAGERS"]
				&& ($u["privilege_id"]==3 || $u["privilege_id"]==4 || $u["privilege_id"]==5)) {
				$is_allowed = true;
			}
			if (isset($a)) {
				if (isset($perm_array["TEAM"]) && $perm_array["TEAM"]
					&& ($author_user_id == $user_id || $author_user_id == $u["manager_id"] || $a["manager_id"] == $user_id
						|| ($a["manager_id"]==$u["manager_id"] && $a["manager_id"]>0)
					)) {
					$is_allowed = true;
				}
				if (isset($perm_array["OFFICE"]) && $perm_array["OFFICE"] && $a["office_id"]==$u["office_id"]) {
					$is_allowed = true;
				}
				if (isset($perm_array["OWNER"]) && $perm_array["OWNER"] && $author_user_id == $user_id) {
					$is_allowed = true;
				}
			}
		}
	}	
	return $is_allowed;	
}

function db_once_query($sql, $return = false)
{
	global $db;
	
	if (!$return) {
		return true;
	}
	$db->query($sql,__FILE__,__LINE__);
	$db->next_record();
	$isarray = is_array($db->Record);
	if ($isarray) {
		$values = array();
		$values[] = $db->f(0);
		while ($db->next_record()) {
			$values[] = $db->f(0);
		}
	} else {
		$values = $db->f(0);
	}
	
	return $values;	
}

function get_post_vars()
{
	global $HTTP_POST_VARS;
	
	$post_vars = array();
	if (isset($HTTP_POST_VARS)) {
		$post_vars = $HTTP_POST_VARS;
	} elseif (isset($_POST)) {
		$post_vars = $_POST;
	}	
	return $post_vars;
}

//-- count_level
function count_level($paragraph)
{
	$level = 0;
	$pos   = 0;
	$ch    = substr($paragraph,$pos,1);

	while($ch == ">")
	{
		$level++;
		$pos++;
		$ch = substr($paragraph,$pos,1);
	}

	return $level;
}

function get_end_tags($level_number)
{
	$tags="";
	for($i=1;$i<=$level_number;$i++)
	{
		$tags.="</div>\n";
	}
	return $tags;
}

function get_start_tags($start_level,$level_number)
{
	global $cur_message_colors;
	$tags="";

	for($i=$start_level;$i<$start_level+$level_number;$i++) {
		
		$k=$i+1;
		
		while($k>sizeof($cur_message_colors)) {
			$k-=sizeof($cur_message_colors);
		}

		if(array_key_exists($k,$cur_message_colors)){
		$tags.="<div style='".
                "color:".$cur_message_colors[$k].";".
                "margin-left:".(10)."pt".";".
                "padding-left:10pt;".
                "border-left-style:solid;".
                "border-left-width:thin;"."'>\n";
		}
	}
	return $tags;
}

/**
 * @param string $message
 * @param integer $message_id
 * @param string $identity_type
 * @return string
 */
function process_message($message, $message_id, $identity_type)
{
  	global $level_colors, $cur_message_colors, $path;
  	
	$db1 = new DB_Sql();
	$db1->Database = DATABASE_NAME;
	$db1->User     = DATABASE_USER;
	$db1->Password = DATABASE_PASSWORD;
	$db1->Host     = DATABASE_HOST;
  	
  	$message = preg_replace("/</","&lt;",$message);
  	$message = preg_replace("/!^>/","&gt;",$message);
  	$sql="SELECT * FROM attachments WHERE identity_type=".ToSQL($identity_type, "text")." AND identity_id=".$message_id;
  	$db1->query($sql);
  	while ($db1->next_record())
	{
 		$AbsoluteUri ='http'.'://'.$_SERVER["SERVER_NAME"].substr($_SERVER["REQUEST_URI"],0,strrpos(str_replace($_SERVER["QUERY_STRING"],"",$_SERVER["REQUEST_URI"]),'/')+1).$path;
 		$full_path = $AbsoluteUri;
		$mes_file = $db1->Record["file_name"];
		$cur_file = $full_path.strval($message_id)."_".$mes_file;

		if ($db1->Record["attachment_type"] == "image") {
			$message = str_replace("[".$mes_file."]","<img src='$cur_file' border=0>",$message);
		} else {
			$message = str_replace("[".$mes_file."]","<a href='$cur_file'>[$mes_file]</a>",$message);
		}
	}
	$msg_strings = split("\r\n",$message);
	$message     = "";
	$last_level = 0;

	//-- find the maximum level
	$max_level = 0;
	foreach ($msg_strings as $string)
	{
		if (is_array($string))
		{
	  		$string = $string[0];
	  	}
	 	$cur_level = count_level($string);
		if($cur_level > $max_level) $max_level = $cur_level;
	}

	//$cur_message_colors = array_slice($level_colors,8 - $max_level);
	if ($max_level <= 8) {
		$cur_message_colors = array_slice($level_colors,8 - $max_level);
	} else {
		$cur_message_colors = $level_colors;
	}

	$message.="<div style='color:".$cur_message_colors[0].";'>";

	//-- output each string
	foreach ($msg_strings as $string)
	{
		if (is_array($string)) {
			$string = $string[0];
		}
		$cur_level = count_level($string);
		if ($identity_type=="task") {
			$string = preg_replace("/>/","",$string);
		} else {
			$string = preg_replace("/^>+/","",$string);
		}
		if (!trim($string)) $string="&nbsp;";
		$level_diff = $last_level-$cur_level;
		if($level_diff>0) {
			$string = get_end_tags($level_diff).$string;
		} elseif ($level_diff<0) {
			$string = get_start_tags($last_level,$cur_level-$last_level).$string;
		} else {
			$string = "<br>".$string;
		}
	    $last_level = $cur_level;
		$message.=$string;
	}
  	$message.=get_end_tags($last_level)."</div>";
  	return $message;
}

function get_options($table, $field1, $field2, $selected_value, $caption, $list_initial = "")
{
	global $search_title, $t_params;

	$dbx = new DB_Sql();

	$dbx->Database = DATABASE_NAME;
	$dbx->User     = DATABASE_USER;
	$dbx->Password = DATABASE_PASSWORD;
	$dbx->Host     = DATABASE_HOST;
	
	$list = $list_initial;
	$sql = "SELECT $field1,$field2 FROM $table";
	$dbx->query($sql);
	while ($dbx->next_record())
	{
		if ($dbx->f(0) == $selected_value)
		{
			$list .= "<OPTION selected value='".$dbx->f(0)."'>".$dbx->f(1)."</OPTION>";
			$search_title .= $caption . "-&lt;" . $dbx->f(1) . "&gt; ";
		} else {
			$list .= "<OPTION value='".$dbx->f(0)."'>".$dbx->f(1)."</OPTION>";
		}
	}
	if ($selected_value)
	{
		if ($t_params) {
			$t_params .= "&";
		}
		$t_params .= $field1."=".$selected_value;
	}
	return $list;
}

function get_select_options($table, $value_field, $desc_field, $value_selected, $where_case, $order_by_fields, 
						$template_value="", $template_desc="", $template_option="", $initial_data=false, $additional_fields = array())
{
	global $db, $T;
	
	$T->set_var($template_option, "");
	
	if ($initial_data && is_array($initial_data) && sizeof($initial_data)) {
		foreach ($initial_data as $value=>$desc)
		{
	    	if (strlen($template_value)) {
				$T->set_var($template_value, $value);
	    	}
	    	if (strlen($template_desc)) {
				$T->set_var($template_desc, $desc);
	    	}
	    	$T->set_var($template_option."_value", $value);
    		$T->set_var($template_option."_description", $desc);
    		foreach($additional_fields as $additional_field) {
    			$T->set_var($additional_field, "");
    		}
			$T->parse($template_option, true);
		}
	}

	$additional_fields_str = implode(",", $additional_fields);
	if (strlen($additional_fields_str)) {
		$additional_fields_str = ", ".$additional_fields_str;
	}
	
	$sql = "SELECT ".$value_field.",".$desc_field.$additional_fields_str." FROM ".$table." ".$where_case." ORDER BY ".$order_by_fields;	
	$if_alias = strrpos($desc_field, " ");
	if ($if_alias) {
		$desc_field = substr($desc_field, $if_alias+1);
	}
	
	$db->query($sql);
	if ($db->num_rows()) {
		$T->set_var($template_option."_disabled", "");
    	while($db->next_record())
		{
    		if (strlen($template_value)) {
				$T->set_var($template_value, $db->f($value_field));
    		}
    		if (strlen($template_desc)) {
	    		$T->set_var($template_desc, $db->f($desc_field));
    		}
    		$T->set_var($template_option."_value", $db->f($value_field));
    		$T->set_var($template_option."_description", $db->f($desc_field));
    	
    		if($db->f($value_field) == $value_selected) {
    			$T->set_var("Selected", "SELECTED");
    			$T->set_var($template_option."_selected", "SELECTED");
    		} else {
    			$T->set_var("Selected", "");
    			$T->set_var($template_option."_selected", "");
    		}
    		
    		foreach($additional_fields as $additional_field) {
    			$T->set_var($additional_field, $db->f($additional_field));
    		}
    		$T->parse($template_option, true);
    	}
	} else {
		$T->set_var($template_option."_disabled", "disabled");
	}
}

function get_select_options_array($array, $key_selected, $template_option="", $additional_fields = array())
{
	global $db, $T;
	
	$T->set_var($template_option, "");
	$T->set_var($template_option."_disabled", "");
	
	foreach($array as $row) {
   		$T->set_var($template_option."_value", $row["key"]);
   		$T->set_var($template_option."_description", $row["value"]);
    	
   		if($row["key"] == $key_selected) {
   			$T->set_var("Selected", "SELECTED");
   			$T->set_var($template_option."_selected", "SELECTED");
   		} else {
   			$T->set_var("Selected", "");
   			$T->set_var($template_option."_selected", "");
   		}
    		
   		foreach($row as $field=>$field_value) {
   			if ($field!="key" && $field!="value") {
   				$T->set_var($field, $field_value);
   			}
   		}
   		$T->parse($template_option, true);
   	}
}

function count_project_time($project_id)
{
	global $db;

	if ($project_id>0) {
		$sql  = " SELECT SUM(tr.spent_hours) as totaltime ";
		$sql .= " FROM	(((projects pp";
		$sql .= "		LEFT JOIN projects p ON (p.parent_project_id = pp.project_id))";
		$sql .= "		INNER JOIN tasks t ON (t.project_id = pp.project_id OR t.project_id = p.project_id))";
		$sql .= "		INNER JOIN time_report tr ON (tr.task_id = t.task_id))";
		$sql .= " WHERE pp.project_id = " . ToSQL($project_id,"integer",false);
		$sql .= " GROUP BY pp.project_id";
		$db->query($sql);
		
		if ($db->next_record()) {
			$totaltime = $db->f(0);
			$sql  = " UPDATE projects set total_time=" . ToSQL($totaltime,"float");
			$sql .= " WHERE project_id=" . ToSQL($project_id,"integer",false);
			$db->query($sql);
		}			
	}
}

function get_users_list($project_id, $task_id, $block_name, $value_field, $desc_field, $selected_value=false) {
	global $db, $T;
	
	$session_user_id = GetSessionParam("UserID");
	$sql = "SELECT privilege_id, manager_id, is_viart FROM users WHERE user_id=".ToSQL($session_user_id, "integer");
	$db->query($sql);
	if ($db->next_record()) {
		$manager_id = $db->f("manager_id");
		if ($db->f("privilege_id")>=3 && $db->f("privilege_id")<=5 && $manager_id<=0) {
			$is_manager = true;
		} else {
			$is_manager = false;
		}
		$is_viart = $db->f("is_viart");
		// for sayu -> show viart managers and all sayu people
		// for viart managers -> show managers
		// for viart people -> show at least their manager
		if (!$is_viart) {
			//$where_sql = "((privilege_id>=3 AND privilege_id<=5) OR is_viart=0) ";
			$where_sql = "(is_viart=0) ";
		} elseif ($is_manager) {
			//$where_sql = "(privilege_id>=3 AND privilege_id<=5) ";
			$where_sql = "(user_id=".ToSQL($session_user_id, "integer").")";
		} else {
			$where_sql = "(user_id=".ToSQL($session_user_id, "integer").")";
		}
		
		// get assigned to the task
		$sql = "SELECT responsible_user_id, created_person_id FROM tasks WHERE task_id=".ToSQL($task_id, "integer");
		$db->query($sql);
		if ($db->next_record()) {
			$where_sql .= " OR (user_id IN (".ToSQL($db->f("responsible_user_id"), "integer", false).",".ToSQL($db->f("created_person_id"), "integer", false).")) ";
		}
		
		//get assigned to the project		
		if ($project_id) {
			$users_projects_string = "";
			$sql = "SELECT user_id FROM users_projects WHERE project_id=".ToSQL($project_id, "integer");
			$db->query($sql);
			while ($db->next_record()) {
				if ($users_projects_string) {
					$users_projects_string .= ",";
				}
				$users_projects_string .= $db->f("user_id");
			}
			if ($users_projects_string) {
				$where_sql.= " OR (user_id IN (".$users_projects_string.")) ";
			}
		}
		
		$sql = " SELECT user_id, CONCAT(first_name,' ',last_name) AS user_name FROM users ";
		$sql.= " WHERE is_deleted IS NULL AND (".$where_sql.") ORDER BY user_name ";
		$db->query($sql);

		while($db->next_record()) {
			$T->set_var($value_field, $db->f(0));
			$T->set_var($desc_field, $db->f(1));
			if($db->f(0) == $selected_value && $selected_value!==false) {
				$T->set_var("Selected", "SELECTED");
			} else {
				$T->set_var("Selected", "");
			}
			$T->parse($block_name, true);
		}
	} else {
		$T->set_var($block_name, "");
	}
}

function get_users_projects()
{
	global $db, $T;
	
	$session_user_id = GetSessionParam("UserID");
	$sql = "SELECT privilege_id, manager_id, is_viart FROM users WHERE user_id=".ToSQL($session_user_id, "integer");
	$db->query($sql);
	if ($db->next_record()) {
		$manager_id = $db->f("manager_id");
		if ($db->f("privilege_id")>=3 && $db->f("privilege_id")<=5 && $manager_id<=0) {
			$is_manager = true;
		} else {
			$is_manager = false;
		}
		$is_viart = $db->f("is_viart");
		// for sayu -> show viart managers and all sayu people
		// for viart managers -> show managers
		// for viart people -> show at least their manager
		if (!$is_viart) {
			//$where_sql = "((u.privilege_id>=3 AND u.privilege_id<=5) OR u.is_viart=0) ";
			$where_sql = "(u.is_viart=0) ";
		} elseif ($is_manager) {
			//$where_sql = "(u.privilege_id>=3 AND u.privilege_id<=5) ";
			$where_sql = "(u.user_id=".ToSQL($session_user_id, "integer").")";
		} else {
			$where_sql = "(u.user_id=".ToSQL($session_user_id, "integer").")";
		}
		$sql = " SELECT u.user_id, CONCAT(u.first_name,' ',u.last_name) AS user_name FROM users u WHERE u.is_deleted IS NULL AND (".$where_sql.") ";
		$db->query($sql);
		
		$constant_users = array();
		$project_users = array();
		
		$constant_users[0] = "-please select-";
		
		while ($db->next_record()) {
			$constant_users[$db->f("user_id")] = $db->f("user_name");
		}
		
		$sql = " SELECT p.project_id, u.user_id, CONCAT(u.first_name,' ',u.last_name) AS user_name ";
		$sql.= " FROM projects p ";
		$sql.= " LEFT JOIN users_projects up ON (up.project_id = p.project_id) ";
		$sql.= " LEFT JOIN users u ON (up.user_id = u.user_id AND u.is_deleted IS NULL AND !(".$where_sql.")) ";
		$sql.= " WHERE p.is_closed=0 AND u.is_deleted IS NULL GROUP BY p.project_id, u.user_id ORDER BY p.project_id, user_name ";
		$db->query($sql);
		while($db->next_record()) {
			$user_id = $db->f("user_id");
			$project_id = $db->f("project_id");
			$user_name = $db->f("user_name");
			if ($user_id) {
				$project_users[$project_id][$user_id] = $user_name;
			}
			foreach($constant_users as $constant_user_id=>$constant_user_name) {
				$project_users[$project_id][$constant_user_id] = $constant_user_name;
			}
		}
		
		foreach($project_users as $project_id=>$project) {
			asort($project);
			$i=0;
			$T->set_var("usersProject", "");
			$T->set_var("projectID", $project_id);
			$T->parse("usersProject",false);
			
			foreach($project as $user_id=>$user_name) {
				$i++;
				$T->parse("usersProject",false);
				$T->set_var("userID", $user_id);
	  			$T->set_var("userName", $user_name);
  				
	  			$T->set_var("I", $i);
	  			if ($i>1) {
	  				$T->set_var("usersProject", "");
	  			}
	  			$T->parse("usersProjectArray",true);
			}			
		}
	} else {
		
	}	
}

function tojavascript($string)
{
	return str_replace(array("\n","\r","\t")," ", $string);
}
?>