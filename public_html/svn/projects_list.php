<?php

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");


//arrays
$users_names = array(); //all active users names
$users = array();       //users to allocate resources
//$slots = array("user_id_5" => array("27 Aug 2013 9:00" => task_id_456,"27 Aug 2013 10:00" => task_id_3356));
$slots = array();
$tasks = array(); //all active tasks
$tasks_projects = array();

$sql = "SELECT * FROM projects_statuses";
$projects_statuses = array();
$db->query($sql); 
while ($db->next_record()) {
    $projects_statuses[$db->f("project_status_id")] = $db->f("status_desc");
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
    $dta = time() + ($c * 24 * 60 * 60);
    foreach ($users as $user_id => $allocation) {
        for ($i =0; $i < $allocation; $i++){
            $slots[$user_id][date('r',$dta + $i * 60 * 60)] = 0;
        }
        
    }
    
}

//3. get all their tasks for specific user
//for each user
// how many hours we have to allocate?
// $allocate = $estimate - $hours_already_spent (based on % completition)
//go through slots- finding first empty slots for specific user
// check if this task is dependent? if yes - then we have to find out if the parent task is going to be done by this date
// if not - then add the task to array of $not_allocated_tasks
// when found populate slots with task_id


$sql = "SELECT task_id,project_id, task_title, estimated_hours, responsible_user_id,priority_id,task_type_id FROM tasks WHERE responsible_user_id in (24,94,142) AND is_closed=0 AND task_type_id=1";
$sql.= " ORDER BY responsible_user_id,priority_id";
$db->query($sql);
while ($db->next_record()) {
    $user_id = $db->f("responsible_user_id");
    $task_id = $db->f("task_id");
    $hours_to_allocate = $db->f("estimated_hours"); //TODO: $allocate = $estimate - $hours_already_spent (based on % completition)
    if (!$hours_to_allocate) $hours_to_allocate = 8; //by default we assume that the task would take 1 day
    //finding the first available slot
    foreach($slots[$user_id] as $slot_time => $slot_task_assigned) {
        if (!$slot_task_assigned) {
            //found empty slot
            $slots[$user_id][$slot_time] = $task_id;
            $hours_to_allocate--;
            //echo "hours_to_allocate:$hours_to_allocate\n";
        }
        if (!$hours_to_allocate) break; //we've allocated all hours
    }

    $tasks[$task_id] = array("task_title"  => $db->f("task_title"),
                             "task_status" => $db->f("task_status_id"),
                             "estimate"    => $db->f("estimated_hours")
    );
    $tasks_projects[$db->f("project_id")][] = $task_id;
}


//4. getting projects list for Sayu Web clients parent_id=79
$sql = "SELECT project_id,project_title,responsible_user_id,project_status_id FROM projects WHERE is_closed=0 and parent_project_id=79 ORDER BY project_title";
$db->query($sql);

?>

        <table class="table table-hover">
          <thead>
          <tr>
            <th>#</th>
            <th colspan=2>Project Name / Task Name</th>
            <th>Est.</th>
            <th>Status</th>
            <th>%</th>
            <th>Responsible</th>
            <th>Dependant</th>
            <th>26</th>
            <th>27</th>
            <th>28</th>
            <th>29</th>
            <th>30</th>
            <th>31</th>
            <th>1</th>
            <th>2</th>
            <th>3</th>
            <th>4</th>
            <th>5</th>
            <th>6</th>
            <th>7</th>
            <th>8</th>
            <th>9</th>
            <th>10</th>
          </tr>
          </thead>
          <tbody>
<?php 
    while ($db->next_record()) {
?>    

          <tr class="info">
            <td class="text-info" colspan=3><?php echo $db->f("project_title"); ?></td>
            <td></td>
            <td><?php echo $projects_statuses[$db->f("project_status_id")] ?></td>
            <td></td>
            <td><?php echo $users_names[$db->f("responsible_user_id")]; ?></td>
            <td></td>
            <td class="projectBusy"></td>
            <td class="projectBusy"></td>
            <td class="projectBusy"></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
          </tr>
          <?php 
            foreach ($tasks_projects[$db->f("project_id")] as $task_id) {                
          ?>
          <tr>
            <td>1</td>
            <td colspan=2><?php echo $tasks[$task_id]["task_title"]; ?></td>
            <td><?php echo $tasks[$task_id]["estimate"]; ?></td>
            <td>Not Started</td>
            <td>20%</td>
            <td><?php echo $users_names[$tasks[$task_id]["responsible_user_id"]]; ?></td>
            <td></td>
            <td class="taskBusy"></td>
            <td class="taskBusy"></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>            
          </tr>          
<?php 
        }
    } ?>          
          </tbody>
        </table>