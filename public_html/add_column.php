<?php

include("./includes/common.php");
include("./includes/date_functions.php");
/*$sql = "CREATE TABLE `holidays` (                
                    `user_id` int(11),  
                    `date_added` date default NULL,
                    `days_number` int(11) default NULL,
                    `manager_added_id` int(11)default NULL,
                    `notes` varchar(255) default NULL                
                  ) ";
  */                
//$db->query("DELETE FROM tasks WHERE task_id = 7743");
//$sql= "UPDATE tasks SET responsible_user_id='20' WHERE (responsible_user_id='40' OR responsible_user_id='36') AND is_closed=0";
$sql = "ALTER TABLE tasks MODIFY planed_date date";
$db->query($sql);

?>
