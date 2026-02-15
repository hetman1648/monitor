<?php

include ("./includes/common.php");
include ("./includes/date_functions.php");

$start_date = GetParam("start_date");
$end_date = GetParam("end_date");
$work_days = 0;
$holidays_total = 0;

$sql = "SELECT COUNT(holiday_id) AS holidays_total FROM holiday_template WHERE holiday_date>='".$start_date."' AND holiday_date<='".$end_date."'";
$db->query($sql);  
  if ($db->next_record()) {
    $holidays_total += $db->Record["holidays_total"];
   }
   
$sql = "SELECT COUNT(holiday_id) AS holidays_total FROM national_holidays WHERE holiday_date>='".$start_date."' AND holiday_date<='".$end_date."'";
$db->query($sql);  
  if ($db->next_record()) {
  	$holidays_total += $db->Record["holidays_total"];
   }
 
do
 {

 	$date_array  = explode("-",$start_date);
 	$date_info = getdate(mktime(0,0,0,$date_array[1],$date_array[2],$date_array[0]));

 	if ($date_info['wday']!=0 && $date_info['wday']!=6) 
 	{
 		$work_days++;
 	}
 		
 	$start_date = date('Y-m-d',mktime(0,0,0,$date_array[1],$date_array[2]+1,$date_array[0]));
 	
 }
while ($start_date<=$end_date);

$work_days = $work_days - $holidays_total;
//echo $work_days. "-" . $holidays_total;
echo $work_days;



 
?>