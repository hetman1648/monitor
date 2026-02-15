<?php

	// validation messages
	define("INCORRECT_DATE_MESSAGE",  "<b>{field_name}</b> has incorrect date value. Use calendar dates.");
	define("INCORRECT_MASK_MESSAGE",  "<b>{field_name}</b> didn't match with mask. Use following '<b>{field_mask}</b>'");

	// Date indexes
	define("YEAR",        0);
	define("MONTH",       1);
	define("DAY",         2);
	define("HOUR",        3);
	define("MINUTE",      4);
	define("SECOND",      5);
	define("SHORTYEAR",   6);
	define("FULLMONTH",   7);
	define("SHORTMONTH",  8);
	define("AMPMHOUR",    9);
	define("AMPM",       10);
	define("GMT",        11);

	$months = array(
		array(1,  "January"),  
		array(2,  "February"), 
		array(3,  "March"),    
		array(4,  "April"),    
		array(5,  "May"),      
		array(6,  "June"),     
		array(7,  "July"),     
		array(8,  "August"),   
		array(9,  "September"),
		array(10, "October"),  
		array(11, "November"), 
		array(12, "December")
	);

	$short_months = array(
		array(1,  "Jan"),
		array(2,  "Feb"),
		array(3,  "Mar"),
		array(4,  "Apr"),
		array(5,  "May"),
		array(6,  "Jun"),
		array(7,  "Jul"),
		array(8,  "Aug"),
		array(9,  "Sep"),
		array(10, "Oct"),
		array(11, "Nov"),
		array(12, "Dec")
	);

	$weekdays = array(
		array(1, "Sunday"),   
		array(2, "Monday"),   
		array(3, "Tuesday"),  
		array(4, "Wednesday"),
		array(5, "Thursday"), 
		array(6, "Friday"),   
		array(7, "Saturday")
	);

	$short_weekdays = array(
		array(1, "Sun"),
		array(2, "Mon"),
		array(3, "Tue"),
		array(4, "Wed"),
		array(5, "Thu"),
		array(6, "Fri"),
		array(7, "Sat")
	);
	
	function norm_sql_date($sql_date) /* by Roman Nastenko (roman@viart.com.ua)
	                                     Returns "human-oriented" format date from SQL format date
                                        Example: "10th Apr 2006" from "2006-04-10" */
   {
      global $short_months;      
      $day = substr($sql_date, 8, 2);
      if (substr($day, 0, 1) == 0) $day = substr($day, 1, 1);      
      if ($day == 1) $day .= "st";
      elseif ($day == 2) $day .= "nd";
      elseif ($day == 3) $day .= "rd";
      else $day .= "th";      
      $search_month = substr($sql_date, 5, 2);
      if (substr($search_month, 0, 1) == 0) $search_month = substr($search_month, 1, 1);   
      for ($i = 0; $i <= 11; $i++) {
        if ($short_months[$i][0] == $search_month) $month = $short_months[$i][1];
      }      
      $year = substr($sql_date, 0, 4);      
      return $day." ".$month." ".$year;
   }
	
	function va_time($timestamp = "")
	{
		if(!$timestamp) { $timestamp = time(); }
		return array(date("Y", $timestamp), date("m", $timestamp), date("d", $timestamp), date("H", $timestamp), date("i", $timestamp), date("s", $timestamp));
	}

	function get_ampmhour($date_array)
	{
		$hour = intval($date_array[HOUR]);
		if($hour > 12)
			$hour -= 12;
		else if($hour == 0)
			$hour = 12;
		if(strlen($hour) == 1) $hour = "0" . $hour;
		return $hour;
	}

	function get_ampm($date_array)
	{
		$hour = intval($date_array[HOUR]);
		if($hour >= 12 && $hour <= 23)
			$ampm = "PM";
		else
			$ampm = "AM";

		return $ampm;
	}

	function set_hour($date_array)
	{
		if(isset($date_array[AMPMHOUR]) && isset($date_array[AMPM]))
			if(strtoupper($date_array[AMPM]) == "AM" && $date_array[AMPMHOUR] == 12)
				$date_array[HOUR] = 0;
			else if(strtoupper($date_array[AMPM]) == "PM" && $date_array[AMPMHOUR] != 12)
				$date_array[HOUR] = 12 + intval($date_array[AMPMHOUR]);
			else
				$date_array[HOUR] = $date_array[AMPMHOUR];
		else if(isset($date_array[AMPMHOUR]))
			$date_array[HOUR] = $date_array[AMPMHOUR];
		else
			$date_array[HOUR] = "00";

		return $date_array;
	}

	function set_month($date_array)
	{
		global $months;
		global $short_months;

		if(isset($date_array[FULLMONTH]))
			$date_array[MONTH] = get_array_id($date_array[FULLMONTH], $months);
		else if(isset($date_array[SHORTMONTH]))
			$date_array[MONTH] = get_array_id($date_array[SHORTMONTH], $short_months);
		else
			$date_array[MONTH] = "01";

		return $date_array;
	}

	function set_year($date_array)
	{
		if(isset($date_array[SHORTYEAR]))
			if($date_array[SHORTYEAR] >= 70 && $date_array[SHORTYEAR] <= 99)
				$date_array[YEAR] = "19" . $date_array[SHORTYEAR];
			else
				$date_array[YEAR] = "20" . $date_array[SHORTYEAR];
		else 
			$date_array[YEAR] = "1970";

		return $date_array;
	}


	function va_date($mask = "", $date = "")
	{
		global $months;
		global $short_months;
	
		$formated_date = "";

		if(!is_array($date)) $date = va_time();
		if(is_array($mask))
		{
	    for($i = 0; $i < sizeof($mask); $i++)
  	  {
        switch ($mask[$i])
        {
					case "YYYY":
						$formated_date .= $date[YEAR]; break;
					case "YY":
						$formated_date .= substr($date[YEAR], 2); break;
					case "MMMM":
						$formated_date .= $months[intval($date[MONTH]) - 1][1]; break;
					case "MMM":
						$formated_date .= $short_months[intval($date[MONTH]) - 1][1]; break;
					case "MM":
						$formated_date .= (strlen($date[MONTH]) == 2) ? $date[MONTH] : "0" . $date[MONTH]; break;
					case "M":
						$formated_date .= intval($date[MONTH]); break;
					case "DD":
						$formated_date .= (strlen($date[DAY]) == 2) ? $date[DAY] : "0" . $date[DAY]; break;
					case "D":
						$formated_date .= intval($date[DAY]); break;
					case "HH":
						$formated_date .= (strlen($date[HOUR]) == 2) ? $date[HOUR] : "0" . $date[HOUR]; break;
					case "H":
						$formated_date .= intval($date[HOUR]); break;
					case "hh":
						$formated_date .= (get_ampmhour($date) == 2) ? get_ampmhour($date) : "0" . get_ampmhour($date); break;
					case "h":
						$formated_date .= intval(get_ampmhour($date)); break;
					case "mm":
						$formated_date .= (strlen($date[MINUTE]) == 2) ? $date[MINUTE] : "0" . $date[MINUTE]; break;
					case "m":
						$formated_date .= intval($date[MINUTE]); break;
					case "ss":
						$formated_date .= (strlen($date[SECOND]) == 2) ? $date[SECOND] : "0" . $date[SECOND]; break;
					case "s":
						$formated_date .= intval($date[SECOND]); break;
					case "AM":
						$formated_date .= get_ampm($date); break;
					case "am":
						$formated_date .= strtolower(get_ampm($date)); break;
					case "GMT":
						$formated_date .= isset($date[GMT]) ? $date[GMT] : ""; break;
          default:

						$formated_date .= $mask[$i];
				}
			}
		}
		else
		{
			$formated_date = $date[YEAR]."-".$date[MONTH]."-".$date[DAY]." ".$date[HOUR].":".$date[MINUTE].":".$date[SECOND];
		}
		return $formated_date;
	}


	function parse_date($mask = "", $date_string = "", $control_name = "")
	{
		global $months;
		global $short_months;
		global $weekdays;
		global $short_weekdays;
		global $datetime_edit_format;

		if(is_array($date_string) || !strlen($date_string))
			return $date_string;

		$date_string = trim($date_string);

		if(!is_array($mask))
			$mask = $datetime_edit_format;

		$reg_exp = "";
		$reg_exps = array(
				"YYYY" => "(\d{4})", "YY" => "(\d{2})",
				"MMMM" => build_regexp($months), "MMM" => build_regexp($short_months),
				"WWWW" => build_regexp($weekdays), "WWW" => build_regexp($short_weekdays),
				"MM" => "(\d{2})", "M" => "(\d{1,2})",
				"DD" => "(\d{2})", "D" => "(\d{1,2})",
				"HH" => "(\d{2})", "H" => "(\d{1,2})",
				"hh" => "(\d{2})", "h" => "(\d{1,2})",
				"mm" => "(\d{2})", "m" => "(\d{1,2})",
				"ss" => "(\d{2})", "s" => "(\d{1,2})",
				"AM" => "(AM|PM)", "am" => "(am|pm)",
				"GMT" => "([\+\-]\d{2,4})"
			);
		$indexes = array(
				"YYYY" => YEAR, "YY" => SHORTYEAR,
				"MMMM" => FULLMONTH, "MMM" => SHORTMONTH,
				"MM" => MONTH, "M" => MONTH,
				"DD" => DAY, "D" => DAY,
				"HH" => HOUR, "H" => HOUR,
				"hh" => AMPMHOUR, "h" => AMPMHOUR,
				"mm" => MINUTE, "m" => MINUTE,
				"ss" => SECOND, "s" => SECOND,
				"AM" => AMPM, "am" => AMPM,
				"GMT" => GMT
			);
		$matches_indexes = array();
		$matches_number = 0;
    for($i = 0; $i < sizeof($mask); $i++)
 	  {
			if(isset($reg_exps[$mask[$i]])) {
				$matches_number++;
				$reg_exp .= $reg_exps[$mask[$i]];
				$matches_indexes[$matches_number] = isset($indexes[$mask[$i]]) ? $indexes[$mask[$i]] : "";
			} else {
				$reg_exp .= prepare_regexp($mask[$i]);
			}
		}
		$reg_exp = str_replace(" ", "\\s+", $reg_exp);
		$reg_exp = "/^" . $reg_exp . "\$/i";
		if(preg_match($reg_exp, $date_string, $matches))
		{
			for($i = 1; $i <= $matches_number; $i++)
				$date_value[$matches_indexes[$i]] = $matches[$i];
			if(!isset($date_value[YEAR]))
				$date_value = set_year($date_value);
			if(!isset($date_value[MONTH]))
				$date_value = set_month($date_value);
			if(!isset($date_value[DAY]))
				$date_value[DAY] = "01";
			if(!isset($date_value[HOUR]))
				$date_value = set_hour($date_value);
			if(!isset($date_value[MINUTE]))
				$date_value[MINUTE] = "00";
			if(!isset($date_value[SECOND]))
				$date_value[SECOND] = "00";

			if(checkdate($date_value[MONTH], $date_value[DAY], $date_value[YEAR])) {
				$result = $date_value;
			} else if ($date_value[MONTH] != 0 && $date_value[DAY] != 0 && $date_value[YEAR] != 0) {
				if (!strlen($control_name)) { $control_name = $date_string; }
				$result = str_replace("{field_name}", $control_name, INCORRECT_DATE_MESSAGE);
			}
		}
		else
		{
			if (!strlen($control_name)) { $control_name = $date_string; }
			$result = str_replace("{field_name}", $control_name, INCORRECT_MASK_MESSAGE);
			$result = str_replace("{field_mask}", join("", $mask), $result);
		}

		return $result;
	}	

	function prepare_regexp($regexp)
	{
		$escape_symbols = array("\\","/","^","\$",".","[","]","|","(",")","?","*","+","-","{","}");
		for($i = 0; $i < sizeof($escape_symbols); $i++)
			$regexp = str_replace($escape_symbols[$i], "\\" . $escape_symbols[$i], $regexp);
		return $regexp;
	}

	function build_regexp($dates_array)
	{
		$reg_exp = "";
		for($i = 0; $i < sizeof($dates_array); $i++)
		{
			if($i != 0) $reg_exp .= "|";
			$reg_exp .= $dates_array[$i][1];
		}
		$reg_exp = "(" . $reg_exp . ")";
		return $reg_exp;
	}

?>