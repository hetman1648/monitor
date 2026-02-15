<?php

	include ("./includes/common.php");
	CheckSecurity(1);
	
	$user_id  = (int) GetParam('user_id');
	$now  = GetParam('now');
//echo $user_id;
	$all = array();
	$sql = " SELECT task_id, task_title, planed_date FROM tasks WHERE responsible_user_id = ".$user_id . " AND is_closed = 0 AND task_type_id <> 3";
	$db->query($sql);
	if ($db->next_record()) {
		$echo = "";
		do {
			$task_id = $db->f("task_id");
			$task_title = $db->f("task_title");
			$date = $db->f("planed_date");
			$date = split("-",$date);
			//echo $task_id . " - " . $task_title . " - " . $date[0]."-".$date[1]."-".$date[2] . "<br>";
			if ($date[0] == "0000" && $date[1] == "00" && $date[2] == "00") {
				$all[] = array($task_id,$task_title);
				
			} else {
				$dates[(int) $date[0]][(int) $date[1]][(int) $date[2]][] = array($task_id,$task_title);
			}
		} while ($db->next_record());
	}
	
	//$month[1] = "January"; $month[2] = "February"; $month[3] = "March"; $month[4] = "April"; $month[5] = "May"; $month[6] = "June";
	//$month[7] = "July"; $month[8] = "August"; $month[9] = "September"; $month[10] = "October"; $month[11] = "November"; $month[12] = "December";
	
	$m2  = (int) GetParam('m');
	$y2  = (int) GetParam('y');
	if ($m2 <= 0 || $m2 > 12) {$m2 = 12;}
	if ($y2 == 0) {$y2 = 2009;}
	
	$w2 = date("w", mktime(0, 0, 0, $m2, 1, $y2)); // day of week
	
	$m2_1 = $m2 - 1; // prev month
	if ($m2_1 == 0) {
		$y2_1 = $y2 - 1; // prev year
		$m2_1 = 12;
	} else {
		$y2_1 = $y2;
	}
	
	$d2 = days($m2,$y2); // count days in month
	$d2_1 = days($m2_1,$y2_1); // count days in month
	
	$echo_table3 = generate_table_header($y2,$m2);
	$d_temp = 0;
	if ($w2 != 0) {
		$d2_1_temp = $d2_1 - $w2; // start day from prev month
		for ($i = $d2_1_temp + 1; $i <= $d2_1; $i++) {
			$d_temp++;$echo_table3 .= "<td class=\"prev_month\">".$i."</td>";
		}
	}
	for ($i = 1; $i <= $d2; $i++) {
		$d_temp++;
		if ($d_temp > 7) { $echo_table3 .= "\n\t</tr>\n\t<tr>\n\t\t"; $d_temp = 1; }
		$echo_table3 .= generate_td($y2,$m2,$i);
	}
	$d_temp2 = $d_temp; $z = 0; // start day from next month
	while ($d_temp2 % 7 != 0) {$d_temp2++;$z++;$echo_table3 .= "<td class=\"next_month\">".$z."</td>";}
	$echo_table3 .= generate_table_footer();

	echo $echo_table3;
	
	function generate_table_header($y,$m) {
		$echo_table = "<table cellpadding=\"1\" cellspacing=\"1\" border=\"1\">";
		$echo_table.= "\n\t<tr>\n\t\t";
		$echo_table.= "<td width=\"40px\" class=\"days\">Su</td>";
		$echo_table.= "<td width=\"40px\" class=\"days\">Mo</td>";
		$echo_table.= "<td width=\"40px\" class=\"days\">Tu</td>";
		$echo_table.= "<td width=\"40px\" class=\"days\">We</td>";
		$echo_table.= "<td width=\"40px\" class=\"days\">Th</td>";
		$echo_table.= "<td width=\"40px\" class=\"days\">Fr</td>";
		$echo_table.= "<td width=\"40px\" class=\"days\">Sa</td>";
		$echo_table.= "\n\t</tr>";
		$echo_table.= "\n\t<tr>\n\t\t";
		return $echo_table;
	}
	
	function generate_td($y,$m,$i) {
		global $now;
		$tasks = generate_tasks_list($y,$m,$i);
		if (strlen($tasks)) {
			if ($y."-".$m."-".$i == $now) {
				$echo_table = "<td class=\"now\" ";
			} else {
				$echo_table = "<td class=\"now_month\" ";
			}
			$echo_table.= "onmouseover=\"universal_show_block('".$y.$m.$i."');\" ";
			$echo_table.= "onmousemove=\"universal_show_block('".$y.$m.$i."');\" ";
			$echo_table.= "onmouseout=\"universal_hide_block('".$y.$m.$i."');\">";
			$echo_table.= "<a href=\"#\" onClick=\"SelectDate('".$i."',".$m.",'".substr($y,2)."');\">".$i."</a>";
			$echo_table.= $tasks."</td>";
		} else {
			if ($y."-".$m."-".$i == $now) {
				$echo_table = "<td class=\"now\">";
			} else {
				$echo_table = "<td class=\"now_month\">";
			}
			$echo_table.= "<a href=\"#\" onClick=\"SelectDate('".$i."',".$m.",'".substr($y,2)."');\">".$i."</a>";
			$echo_table.= "</td>";
		}
		return $echo_table;
	}
	
	function generate_table_footer() {
		$echo_table = "\n\t</tr>";
		$echo_table.= "\n</table>\n";
		return $echo_table;
	}
	
	function generate_tasks_list($y,$m,$i) {
		global $dates, $all;
		$z = 0;
		//echo $y."-".$m."-".$i."---<br>";
		if (isset($dates[$y][$m][$i])) {
			$tasks = "";
			$tasks .= "<table>";
			for ($e = 0; $e < count($dates[$y][$m][$i]); $e++) {
				//if (strlen($tasks)) {$tasks .= "<br>\n";}
				$tasks .= "\n<tr><td valign=\"top\">" . $dates[$y][$m][$i][$e][0] . "</td>";
				$tasks .= "<td><a href=\"edit_task.php?task_id=".$dates[$y][$m][$i][$e][0]."\">" . $dates[$y][$m][$i][$e][1]."</a></td></tr>";
				$z++;
			}
			$tasks .= "\n</table>";
			/*if (count($all)) {
				foreach ($all as $value) {
					if (strlen($tasks)) {$tasks .= "<br>\n";}
					$tasks .= $value[0] . " - " . $value[1];
					$z++;
				}
			}*/
			$tasks = "(".$z.")<div id=\"".$y.$m.$i."\" style=\"width: 300px; align: justify; background-color: #FFFFFF; position: absolute; display: none;\">".$tasks."</div>";
		} else {
			$tasks = "";
			/*if (count($all)) {
				foreach ($all as $value) {
					if (strlen($tasks)) {$tasks .= "<br>\n";}
					$tasks .= $value[0] . " - " . $value[1];
					$z++;
				}
			}
			$tasks = "(".$z.")<div id=\"".$y.$m.$i."\" style=\"width: 300px; align: justify; background-color: #FFFFFF; position: absolute; display: none;\">".$tasks."</div>";*/
		}
		return $tasks;
	}
	
	function days($m,$y) {
		if ($m==1 || $m==3 || $m==5 || $m==7 || $m==8 || $m==10 || $m==12) {
			$days=31;
		} else if ($m==4 || $m==6 || $m==9 || $m==11) {
			$days=30;
		} else if ($m==2)  {
			if ($y % 4 == 0) {$days=29;} else {$days=28;}
		}
		return ($days);
	}

?>