<?php
//this file is run from AJAX call from projects.php

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");


//arrays
$users_names = array(); //all active users names
$users = array();       //users to allocate resources
$other_tasks = array(); //all active tasks that not needed to be allocated: done,on hold, etc
//$slots = array("user_id_5" => array("27 Aug 2013 9:00" => task_id_456,"27 Aug 2013 10:00" => task_id_3356));
$slots = array();
$tasks = array(); //all active tasks
$tasks_projects = array(); //tasks assigned to certain projects
$tasks_completion = array(); //tasks completion dates (estimated)
$tasks_counter = array(); //stores all tasks # (internal report IDs)


$sql = "SELECT * FROM projects_statuses";
$projects_statuses = array();
$db->query($sql); 
while ($db->next_record()) {
    $projects_statuses[$db->f("project_status_id")] = $db->f("status_desc");
}

$sql = "SELECT * FROM lookup_tasks_statuses";
$tasks_statuses = array();
$db->query($sql); 
while ($db->next_record()) {
    $tasks_statuses[$db->f("status_id")] = $db->f("status_desc");
}

//national holidays
$national_holidays = array();
$sql = "SELECT * FROM national_holidays WHERE holiday_date >= DATE_FORMAT(NOW(),'%Y-%m-%d')";
$db->query($sql);
while($db->next_record()) {
    $national_holidays[strtotime($db->f("holiday_date")." 09:00")] = $db->f("holiday_title");
}

//peoples holidays
$holidays = array();
$sql = "SELECT * FROM days_off WHERE end_date >= DATE_FORMAT(NOW(),'%Y-%m-%d')";
$db->query($sql);
while($db->next_record()) {
    $start_date = strtotime($db->f("start_date") ." 09:00");
    $end_date   = strtotime($db->f("end_date")   ." 09:00");
    $days_diff  = floor(($end_date - $start_date)/(60 * 60 * 24));
    for($i =0; $i <= $days_diff; $i++) {
        $holidays[$db->f("user_id")][] = $start_date + $i * 60 * 60 * 24;
    } 
}

//1. get all available resources (people who perform tasks)
$sql = "SELECT user_id,first_name,last_name,web_clients_resource FROM users WHERE  is_deleted IS NULL";
$db->query($sql);
while($db->next_record()) {
    if ($db->f("web_clients_resource")){ 
        $users[$db->f("user_id")] = 8 * $db->f("web_clients_resource") / 100; //8 hours per day is 100%
    }
    $users_names[$db->f("user_id")] = $db->f("first_name") . " " . $db->f("last_name");
}

//2. prepare the list of time slots
// for each user we assume 8 hours per day x 5 days a week
// - minus users holidays booked
// - minus national holidays
$calendar_days = 30; //TODO: GET parameter to pass
for ($c = 0; $c < $calendar_days; $c++) {
    $dta = strtotime(date("Y-m-d",time()) . " 09:00") + ($c * 24 * 60 * 60); // we assume that the first slot is for 9:00
    //echo "dta:".date("c",$dta)."\n";
    
    //we need to find out how many slots are left today
    $slots_left_today = 8;
    if ($c ==0) {
        $now          = strtotime(date("Y-m-d H:00",time()));
        $end_of_today = strtotime(date("Y-m-d",time()) . " 17:00") ;
        $slots_left_today = ($end_of_today - $now) / 60 / 60; 
        if ($slots_left_today < 0) $slots_left_today = 0;
        //echo "slots_left_today:$slots_left_today<hr>\n";
    }

    foreach ($users as $user_id => $allocation) {
    	//echo $user_id."\n";
        for ($i =0; $i < $allocation; $i++){
            if ($c ==0) { //first day of calendar is today
                if ($i >= $slots_left_today) break; //if it's tonight then no slots left - skip to tomorrow   
            }
            $time_slot = $dta + $i * 60 * 60;
            if (!(date("N",$time_slot) >= 6)) { //removing weekends from available slots
                //check if it's not a national holiday
                if (!isset($national_holidays[$time_slot])) {  
                    //check for users holidays
                    $holidays_count = 0;
                    if (isset($holidays[$user_id]) && is_array($holidays[$user_id])) {
                        foreach ($holidays[$user_id] as $holiday_timestamp) {
                            if ($holiday_timestamp == $time_slot) $holidays_count++;
                        }
                    }
                    if (!$holidays_count) {
                        //$slots[$user_id][date("c", $time_slot)] = 0;
                        $slots[$user_id][$time_slot] = 0;
                    } else break;
                } else break;
            }
        }
        
    }
    
}

//3. get all their tasks for specific user
//for each user
// how many hours we have to allocate?
// $allocate = $estimate - $hours_already_spent (based on % completion)
//go through slots- finding first empty slots for specific user
// check if this task is dependent? if yes - then we have to find out if the parent task is going to be done by this date
// if not - then add the task to array of $not_allocated_tasks
// when found populate slots with task_id

// LINKED TASKS (dependencies)
// - we store all tasks in array
// - then each task has a flag is_allocated=false by default
// - when we allocate tasks we set this flag to is_allocated = true
// - when the task is dependent on completion of another task we check if the predecessor
//   task has been estimated as completed - if not -then we skip the task 
// - 

if (isset($_SESSION["ses_hide_done_tasks"])) $hide_done_tasks = $_SESSION["ses_hide_done_tasks"];
$sql = "SELECT task_id,project_id, dependent_id, completion, task_status_id,task_title, estimated_hours, responsible_user_id,priority_id,task_type_id, ";
$sql.="  actual_hours, IF( task_status_id =1, actual_hours +( UNIX_TIMESTAMP( NOW( ) ) - UNIX_TIMESTAMP( started_time ) )/3600.0000 , actual_hours ) AS actual_hours_live ";
$sql.=" FROM tasks ";
$sql.= " WHERE responsible_user_id in (".join(",",array_keys($users)).") AND is_closed=0 AND task_type_id!=3";
if ($hide_done_tasks) $sql .= " AND task_status_id!=4 ";
$sql.= " ORDER BY responsible_user_id,priority_id";
$db->query($sql);
while ($db->next_record()) {
    $user_id           = $db->f("responsible_user_id");
    $task_id           = $db->f("task_id");
    $project_id        = $db->f("project_id");
    $hours_to_allocate = $db->f("estimated_hours"); 
    $actual_hours      = $db->f("actual_hours");
    $actual_hours_live = $db->f("actual_hours_live");

    if ($actual_hours_live) $actual_hours = $actual_hours_live;

    //echo "task_id: $task_id, user_id:$user_id hours_to_allocate:$hours_to_allocate<br>";
    if ($hours_to_allocate <=0 ) $hours_to_allocate = 8; //by default we assume that the task would take 1 day
    if (($hours_to_allocate > 0) && ($hours_to_allocate < 1)) $hours_to_allocate = 1; //if task takes less than an hour we assume it's still 1 hour to be displayed

    $tasks[$task_id] = array("task_title"          => $db->f("task_title"),
                             "task_status"         => $db->f("task_status_id"),
                             "estimate"            => $hours_to_allocate,
                             "responsible_user_id" => $user_id,
                             "project_id"          => $project_id,
                             "is_allocated"        => false,
                             "dependent_id"        => $db->f("dependent_id"),
                             "completion"          => $db->f("completion"),
                             "end_date"            => 0, //we calculate the end_date when allocate tasks
                             "actual_hours"        => $actual_hours
    );
}

//print_r($tasks);
$allocate_left = sizeof($tasks);
//echo "allocate_left:$allocate_left<br>";
$c = 0;
while ($allocate_left) {
    $c++;
    foreach ($tasks as $task_id => $task) {
        $user_id            = $task["responsible_user_id"];
        $project_id         = $task["project_id"];
        $completion         = $task["completion"]; //completion %
        $hours_to_allocate  = $task["estimate"]; //TODO: $allocate = $estimate - $hours_already_spent (based on % completion)
        $dependent_id       = $task["dependent_id"];
        $dependent_end_date = 0;
        $is_allocated       = $task["is_allocated"];


        if ($is_allocated) continue; //skip the task if it has been already allocated

        if ($dependent_id && isset($tasks[$dependent_id])) {
            //check if predessor is already allocated
            if ($tasks[$dependent_id]["is_allocated"]) {
                $dependent_end_date = $tasks[$dependent_id]["end_date"];
               
                //echo "dependent_end_date:$dependent_end_date";
                // echo "task_id = $task_id<br>\n";
                //echo "dependent_id = $dependent_id <br>\n";
                //echo $dependent_id . " end date " . date("Y-m-d H:i:s", $dependent_end_date)."<br><hr>\n";
                 //print_r($tasks);
                
                
            } else continue; //skip the allocation of this task: the dependent has to be allocated first
        }   

        //echo "task_id: $task_id, user_id:$user_id hours_to_allocate:$hours_to_allocate<br>";
        if ($hours_to_allocate <=0 ) $hours_to_allocate = 8; //by default we assume that the task would take 1 day
        if ($completion)             {
        	//echo "c:$c<br>";
        	//echo "task_id:$task_id<br>";
        	//echo "completion: $completion<br>";        	
        	//echo "hours_to_allocate before:$hours_to_allocate<br>";
        	
            $hours_to_allocate = $hours_to_allocate - round($hours_to_allocate * $completion / 100);
            //echo "hours_to_allocate after:$hours_to_allocate<hr>";

            if (($hours_to_allocate > 0) && ($hours_to_allocate < 1)) $hours_to_allocate = 1;
            if ($hours_to_allocate <= 0) {
	            $tasks_completion[$task_id]      = strtotime(date("Y-m-d H:00",time())); //now
                $tasks[$task_id]["end_date"]     = strtotime(date("Y-m-d H:00",time())); //now
                $tasks[$task_id]["is_allocated"] = true;
                $allocate_left--;
                //echo "ALLOCATION: allocate_left:$allocate_left<br>\n";
                continue; //skip slots allocation: the task is 100% complete
            }
            
        }

        //finding the first available slot
        foreach($slots[$user_id] as $slot_time => $slot_task_assigned) {
            if (!$slot_task_assigned) {
                //found empty slot
                //check if dependent end date is equels or higher then this slot
                if (!$dependent_end_date || ($slot_time > $dependent_end_date)) {
                    $slots[$user_id][$slot_time] = $task_id;
                    $hours_to_allocate--;
                    //if ($task_id == 47137) echo $hours_to_allocate."<br>";
                }
                //echo "hours_to_allocate:$hours_to_allocate user_id:$user_id \n";
            }
            if (!$hours_to_allocate) {
                $tasks_completion[$task_id]      = $slot_time + 60 * 59; //the last minute in the hour
                $tasks[$task_id]["end_date"]     = $slot_time + 60 * 59;
                $tasks[$task_id]["is_allocated"] = true;
                $allocate_left--;
                break; //we've allocated all hours
            }
        }
        //if there are no slots available - then skip the task and mark the task as "allocated"
        if ($hours_to_allocate) {
            $tasks_completion[$task_id]      = $slot_time + 60 * 59; //the last minute in the hour
            $tasks[$task_id]["end_date"]     = $slot_time + 60 * 59;
			$tasks[$task_id]["is_allocated"] = true;
            $allocate_left--;
        }
    }
    if ($c >= 100) {
        //print_r($tasks);
        die("internal error: infinite loop");
    } 
} //end of while allocate_left

//print_r($tasks);
//print_r($slots);
//print_r($tasks_completion);
//exit;

//sorting tasks by completion dates
asort($tasks_completion);
foreach ($tasks_completion as $task_id => $end_date) {
    $tasks_projects[$tasks[$task_id]["project_id"]][] = $task_id;
}

//4. getting projects list for Sayu Web clients parent_id=79
$sql = "SELECT project_id FROM projects WHERE is_closed=0 and parent_project_id=79 ORDER BY project_title";
$db->query($sql);
while ($db->next_record()) {
    $project_id         = $db->f("project_id");
    $project_completion = 0;
    //find out project end date
    if (isset($tasks_projects[$project_id])) {
        $last_task_id       = end($tasks_projects[$project_id]);
        $project_completion = $tasks_completion[$last_task_id];
    }
    $projects[$project_id] = $project_completion;
}
// print_r($projects);

//5. adding other tasks that don't need to be allocated: done, on hold, new tasks, etc
$sql = "SELECT task_id,project_id, dependent_id, completion, task_status_id,task_title, estimated_hours, responsible_user_id,priority_id,task_type_id FROM tasks ";
$sql.= " WHERE task_id NOT IN (".join(",",array_keys($tasks_completion)).") AND is_closed=0 AND task_type_id!=3 ";
$sql.= " AND project_id IN (".join(",",array_keys($projects)). ") ";
if ($hide_done_tasks) $sql .= " AND task_status_id!=4 ";
$sql.= " ORDER BY responsible_user_id,priority_id";
$db->query($sql);
while ($db->next_record()) {
    $user_id = $db->f("responsible_user_id");
    $task_id = $db->f("task_id");
    $project_id = $db->f("project_id");
  
    $tasks[$task_id] = array("task_title"          => $db->f("task_title"),
                             "task_status"         => $db->f("task_status_id"),
                             "estimate"            => $db->f("estimated_hours"),
                             "responsible_user_id" => $user_id,
                             "project_id"          => $project_id,
                             "is_allocated"        => false,
                             "completion"          => $db->f("completion"),
                             "dependent_id"        => $db->f("dependent_id")
    );    
    $tasks_projects[$project_id][] = $task_id;
}

//saving $tasks_projects in session - it will be read by get_task_info.php
//it's needed to pass tasks sort order - when linking tasks we'll be using it
$_SESSION['session_tasks_projects'] = json_encode($tasks_projects);

//-- filling in tasks_counter array for displaying 
$sql = "SELECT project_id FROM projects WHERE is_closed=0 and parent_project_id=79 ORDER BY project_title";
$db->query($sql);
$task_count = 0;
while ($db->next_record()) {
    if (isset($tasks_projects[$db->f("project_id")])) {
            foreach ($tasks_projects[$db->f("project_id")] as $task_id) {                
                $task_count++;
                $tasks_counter[$task_id] = $task_count;
            }
    }
}


//6. displaying projects list for Sayu Web clients parent_id=79
$sql = "SELECT project_id,project_title, total_time,responsible_user_id,project_status_id FROM projects WHERE is_closed=0 and parent_project_id=79 ORDER BY project_title";
$db->query($sql);
$task_count       = 0;
$weeks_to_display = 2;

if (isset($_SESSION["ses_weeks_to_display"]) && $_SESSION["ses_weeks_to_display"]) $weeks_to_display = $_SESSION["ses_weeks_to_display"];
$days_to_display = $weeks_to_display * 7; //TODO: parameter sent via GET

//print_r($slots);

?>

        <table class="table " id="tableHeader">
          <thead >
          <tr>
            <th colspan=3># Project Name / Task Name</th>
            <th title='Estimated Time Needed and Estimated Project Completion Date '>Est.</th>
            <th title='Actual Time Spent'>Act.</th>
            <th>Status</th>
            <th>%</th>
            <th>Responsible</th>
            <th><i class="icon-circle-arrow-right"></i></th>
            <?php 
                $cur_day   = date("j");
                $print_day = $cur_day;
                for ($i = $cur_day; $i<= $cur_day+$days_to_display; $i++) {
                    if ($print_day > 31) $print_day = 1;
                    // echo "<th></th>\n";
                    echo "<th class='calendarCell'><small>$print_day</small></th>\n";
                    $print_day++;
                }
            ?>
          </tr>
          </thead>
      </table>
      
      <div  id="divTable">
        <table class="table" id="tableBody">
          <tbody >
<?php 
    $row_count = 0;
    while ($db->next_record()) {
        $row_count++;
        $project_id = $db->f("project_id");
        $project_total_time = getHoursFormat($db->f("total_time"));
        $project_completion = "";
        $project_total_cost = ""; //$db->f("total_time") * 25;
        // $project_total_cost = $project_total_cost ? number_format($project_total_cost,2) : "";
        // if ($projects[$project_id]) $project_completion =  date($report_date_format,$projects[$project_id]);
?>    

          <tr class="info">
            <td class="projectCell" id="prj<?php echo $project_id; ?>" colspan=3>&nbsp; <?php echo $db->f("project_title"); ?></td>
            <td class="text-warning" title="Estimated project completion date is <?php echo $project_completion; ?>"><?php echo $project_completion; ?></td>
            <td class="small" title="Project total cost: &pound;<?php echo $project_total_cost; ?>"><?php echo $project_total_time; ?></td>
            <td class="projectCell" id="prj<?php echo $project_id; ?>Status"><?php echo $projects_statuses[$db->f("project_status_id")] ?></td>
            <td></td>
            <td class="userCell" id="usr<?php echo $row_count."_".$db->f("responsible_user_id"); ?>"><?php echo $users_names[$db->f("responsible_user_id")]; ?></td>
            <td></td>
             <?php
             // function to display cells in the calendar
             //@param $days_to_display - how many days to be displayed starting from today
             //@param $is_allo
                $print_day = $cur_day;
                for ($i = $cur_day; $i<= $cur_day+$days_to_display; $i++) {
                    if ($print_day > 31) $print_day = 1;
                    echo "<td style='width:25px'></td>\n";
                    $print_day++;
                }
            ?>
          </tr>
          <?php 
         if (isset($tasks_projects[$db->f("project_id")])) {
            foreach ($tasks_projects[$db->f("project_id")] as $task_id) {                
                $task_count++;
                $row_count++;

                $user_id      = $tasks[$task_id]["responsible_user_id"];
                $status_id    = $tasks[$task_id]["task_status"];
                $task_title   = $tasks[$task_id]["task_title"];
                $is_allocated = $tasks[$task_id]["is_allocated"];
                $dependent_id = $tasks[$task_id]["dependent_id"];
                $actual_hours = $tasks[$task_id]["actual_hours"];

                if (strlen($task_title) > 44) $task_title = trim(substr($task_title, 0,44)) ."..";
                
                $completion = $tasks[$task_id]["completion"];
               
                $estimate               = $tasks[$task_id]["estimate"];
                $estimated_slotes       = $tasks[$task_id]["estimate"];
                $estimated_percent_done = 0;
                if ($completion) $estimated_percent_done = $completion;

                if (!$completion) $completion = "0";
                $completion .= "%";

                 if(!($estimate * 1)) {
                    if ($is_allocated) {
                        $estimate         = "1 day?";
                        $estimated_slotes = 8;
                    }
                    else $estimate = "";
                }
                else $estimate = getHoursFormat($estimate);

                $task_tooltip = ""; 
                if (isset($tasks_completion[$task_id])) $task_tooltip = "Est. completion time is ". date("D jS F",$tasks_completion[$task_id]);
                
                $tr_css_class = "";
                if (!$is_allocated) $tr_css_class = "taskNotAllocated";
                
          ?>
          <!-- TASKS -->
          <tr id="tsk<?php echo $task_id; ?>CloseRow" class="<?php echo $tr_css_class; ?>">
            <td></td>
            <td colspan=2 class="taskRow" id="tsk<?php echo $task_id; ?>">&nbsp; <?php echo $task_count; ?> &nbsp; 
                <a href="../edit_task.php?task_id=<?php echo $task_id; ?>" data-toggle="tooltip" title="<?php echo $task_tooltip; ?>"><?php echo $task_title; ?></a>
                <a href="#" id="tsk<?php echo $task_id; ?>Close" class="lnkClose" title ="Close '<?php echo $task_title; ?>'"><i class="icon-minus-sign" ></i></a>
            </td>
            <td class="taskEstimate" id="est<?php echo $task_id; ?>">
                <span id="est<?php echo $task_id; ?>Label"><?php echo $estimate; ?></span>
            </td>
            <td><?php echo getHoursFormat($actual_hours, false); ?></td>
            <td class="taskStatus" id="status<?php echo $task_id; ?>"><?php echo ucwords($tasks_statuses[$status_id]); ?></td>
            <td><?php echo $completion; ?></td>
            <td class="userCell" id="usr<?php echo $row_count."_".$user_id; ?>"><?php echo $users_names[$user_id]; ?></td>
            <!-- dependencies -->
            <td class="dependancyCell" id="dep<?php echo $task_id; ?>">
                <?php if ($dependent_id) { ?>
                    <i class="icon-circle-arrow-right"></i> <?php echo $tasks_counter[$dependent_id]; ?>
                <? } else { ?>
                <a href="#" id="dep<?php echo $task_id; ?>Icon" class="lnkDependancy" title ="Make task completion '<?php echo $task_title; ?>' dependent on some other task ">
                    <i class="icon-arrow-right" ></i>
                </a>
                <? } ?>
            </td>
            <?php
                $cur_timestamp = strtotime(date("Y-m-d",time()) . " 09:00");  // we assume that the first slot is for 9:00

                $print_day     = $cur_day;
                $cur_month     = date("n");
                $cur_year      = date("Y");
                $user_slots    = array();
                if (isset($slots[$user_id]) && is_array($slots[$user_id])){
                    $user_slots = $slots[$user_id];
                }
                for ($i = 0; $i <= $days_to_display; $i++) {
                    //find out the cell's date
                    $cell_timestamp = $cur_timestamp + $i * 60 * 60 * 24; //+1 day
                    $date_displayed = date("D, jS M", $cell_timestamp);

                    //check if it's a weekend
                     $css_class     = "taskUsual"; $css_title = "";
                     if (date('N', $cell_timestamp) >=6) {
                        $css_class = "taskHoliday";
                        $css_title = "weekend";
                     }
                     //check if it's a national holiday
                    if (isset($national_holidays[$cell_timestamp])) {
                        $css_class = "taskHoliday";
                        $css_title = "national holiday: ".$national_holidays[$cell_timestamp];
                    }

                    //check for users holidays
                    $holidays_count = 0;
                   if (isset($holidays[$user_id]) && is_array($holidays[$user_id])) {
                        foreach ($holidays[$user_id] as $holiday_timestamp) {
                            if ($holiday_timestamp == $cell_timestamp) $holidays_count++;
                        }
                    } 
                    if($holidays_count) {
                        $css_class = "taskUserHoliday";
                        $css_title = $users_names[$user_id]. " vacation";
                    }



                    //go through time slots for this user to find out if the cell is busy
                    $slots_count = 0;
                    foreach ($user_slots as $slot_timestamp => $slot_task_id) {
                        if ($slot_task_id == $task_id) {
                            if (($slot_timestamp >= $cell_timestamp) && 
                                ($slot_timestamp <= ($cell_timestamp + 60 * 60 * 8))) {
                                    $slots_count++;
                            } 
                        }
                    }
                    if ($print_day > 31) $print_day = 1;
                    if ($slots_count){
                        $estimated_percent = 0;
                        if ($estimated_slotes) {
                    	   $estimated_percent      = $slots_count / $estimated_slotes * 100;
                        }
                    	$estimated_percent_done += $estimated_percent;
                    	if ($estimated_percent_done >=97) $estimated_percent_done  = 100;

                        //echo "<td class='progress progress-striped' data-toggle='tooltip' title='$slots_count hrs'>";
                        //echo "  <div class='bar' style='width: 100%'></div>";
                        //echo "</td>";
                        echo "<td class='taskBusy' data-toggle='tooltip' title='$date_displayed: $slots_count hrs (".round($estimated_percent_done)."%)'></td>";
                    } else echo "<td class='".$css_class."' data-toggle='tooltip' title='".$date_displayed." ".$css_title."'></td>\n";
                    $print_day++;
                }
            ?>       
          </tr>          
<?php 
        }
      }
    } ?>          
          </tbody>
        </table>
    </div>