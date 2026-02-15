<?php

$root_inc_path = "../";
include ("../includes/common.php");

//TODO: authetification
$operation  = GetParam("operation");
$keyword    = GetParam("search_keyword");
$project_id = GetParam("project_id");
$user       = GetParam("user");
$domain     = GetParam("domain");
$sort_order = GetParam("sort_order");
$tasks          = array(); //array with all tasks we've found
$project_tasks  = array();
$project_title  = $project_id;
$user_id        = 0;
$limit          = 700; // limiting the number of tasks returned from DB
$sort_direction = GetParam("sort_direction");
if ($sort_direction) $_SESSION["session_sort_direction"] = $sort_direction;
if (!$sort_direction) $sort_direction = "DESC";
$sort_direction = ($sort_direction == "DESC") ? "DESC" : "ASC";

//echo $sort_order."<br>";
$sorters = array("t.is_closed ASC,t.creation_date"       => "Date Created",
	             "t.is_closed"           => "Is Closed",
	             "user_name"             => "Assigned To",
	             "t.task_title"          => "Task Name"
	);

$cur_sort_order = "t.is_closed ASC,t.creation_date";
if (isset($sorters[$sort_order])) $cur_sort_order = $sort_order;
//echo $cur_sort_order."<br>";


if ($project_id || strlen($keyword) || $user || $domain) {
	if ($project_id) {
		if (!is_numeric($project_id)) {
			$sql = "SELECT project_id FROM projects WHERE project_title='".addslashes($project_id)."'";
			$db->query($sql);
			$project_id = $db->next_record() ? $db->f("project_id") : "";
		}
	}

	if ($user) {
		if (!is_numeric($user)) {
			$first_name = "";
			$last_name  = $user;
			$parts = explode(" ",$user);
			if(is_array($parts)) {
				$first_name = $parts[0];
				$last_name =  (sizeof($parts) > 1) ? $parts[1] : ""; 				
			};

			$sql = "SELECT user_id FROM users WHERE first_name='".addslashes($first_name)."'";
			$sql.= " AND last_name='".addslashes($last_name)."'";
			//echo $sql;
			$db->query($sql);
			$user_id = $db->next_record() ? $db->f("user_id") : 0;
		}
	}

	// $domain_id = 0;
	// if ($domain) {
	// 	$sql = "SELECT domain_id FROM tasks_domains WHERE domain_url='".addslashes($domain)."'";
	// 	// echo $sql."<hr>";
	// 	$db->query($sql);
	// 	$domain_id = $db->next_record() ? $db->f("domain_id") : 0;
	// }

	//1. find projects
	if ($project_id && is_numeric($project_id)) {
		$sql = "SELECT task_id FROM tasks WHERE project_id = $project_id LIMIT $limit";
		$db->query($sql);
		while($db->next_record()) {
			$project_tasks[] = $db->f("task_id");
		}
	}
	// print_r($project_tasks);

	// 2. search in all tasks (or tasks specified in #1) for task_title, task_description
	$sql = "SELECT task_id FROM tasks WHERE 1=1 ";
	if (strlen($keyword))       $sql.= " AND (task_title LIKE '%$keyword%' OR task_desc LIKE '%$keyword%') ";
	if (sizeof($project_tasks)) $sql.= " AND task_id IN (". join(",",$project_tasks). ")";
	if ($user_id)               $sql.= " AND responsible_user_id=".$user_id;
	if ($domain)                $sql.= " AND task_domain_url='" . addslashes($domain). "'";
	$db->query($sql);
	// echo $sql;	
    // exit;
	$c = 0;
	while($db->next_record()) {
		//if (strlen(trim($db->f("task_id")))) 
		$tasks[] = $db->f("task_id");
		$c++;
		//if ($c >= $limit) break;
	}
	//print_r($tasks);

	// 3. search in all messages (or in messages of tasks specified in #1) 
	if ($keyword) {
		$sql = "SELECT identity_id FROM messages WHERE 1=1 ";
		if (strlen($keyword))       $sql.= " AND message LIKE '%$keyword%' ";
		if (sizeof($project_tasks)) $sql.= " AND identity_id IN (". join(",",$project_tasks). ")";
		$db->query($sql);
		while($db->next_record()) {
			$tasks[] = $db->f("identity_id");
		}
	}

}
$tasks = array_unique($tasks);


if (!sizeof($tasks)) {
	?>
	<div class="bs-callout bs-callout-warning">
      <h4>No Results Found</h4>
      <p>You have searched for: <code>'<?php echo $keyword; ?></code>
      	We have found no results.
      	<p>Search has been performed in tasks names, tasks descriptions and tasks messages
	</p>
	</div>

	<?php	
	exit;
}

?>
<div class="bs-callout bs-callout-info">
      <h4>Search Results</h4>
      <p>You have searched for: 
      	<code>
      		<?php echo $project_title; ?>
      		<?php echo $user; ?>
      		<?php echo $keyword; ?>
      	</code>
      	We have found <code><?php echo sizeof($tasks); ?></code> search results.
      	<p>Search has been performed in tasks titles, tasks descriptions and tasks messages
</p>
</div>

<ul class="nav nav-pills">
        <!--<li class="active"><a href="#">Regular link</a></li>-->
        
        <li class="dropdown navbar-right">
          <a id="drop6" role="button" data-toggle="dropdown" href="#">Sorted By: '<?php echo $sorters[$cur_sort_order] . "' ".$sort_direction; ?>
           <b class="caret"></b></a>
          <ul id="menu3" class="dropdown-menu" role="menu" aria-labelledby="drop6">
          	<?php foreach($sorters as $sort_field => $sort_title) { ?>
            	<li role="presentation"><a role="menuitem" class="lnkSortOrder" tabindex="-1" href="#" id="so_<?php echo $sort_field?>"><?php echo $sort_title; ?></a></li>
            <?php } ?>
            <li role="presentation" class="divider"></li>
            <li role="presentation"><a role="menuitem" class="lnkSortDirection" tabindex="-1" href="#" >Sort <?php echo ($sort_direction == "ASC"?"Descending":"Ascending"); ?></a></li>
            
          </ul>
        </li>
      </ul>
<table class="table table-striped table-condensed">
<thead>
	<tr>
		<th>Task ID</th>
		<th>Project Name</th>
		<th>Task Name</th>
		<th>Assigned To</th>
		<th>Status</th>
		<th>%</th>
		<th>Hrs</th>
		<th>Created</th>
		<th>Closed?</th>

	</tr>

</thead>
<tbody>

<?php
	$sql = " SELECT p.project_title, t.task_id, t.completion, t.task_title, CONCAT(u.first_name, ' ', u.last_name) as user_name, ";
	$sql .= " t.priority_id, lts.status_desc, t.estimated_hours, t.actual_hours, t.creation_date, t.planed_date, ";
	$sql .= " t.is_closed, u.user_id, p.project_id, u.user_id, t.actual_hours, ";
	$sql .= " DATE_FORMAT(t.creation_date, '%d %b %Y') AS c_date, DATE_FORMAT(t.planed_date, '%d %b %Y') AS plan_date ";
	// $sql .= " DATE_FORMAT(t.creation_date, '%Y %m %d') AS datesort ";
	$sql .= " FROM tasks t, users u, projects p, lookup_tasks_statuses lts ";
	$sql .= " WHERE t.responsible_user_id=u.user_id AND t.project_id=p.project_id AND t.task_status_id=lts.status_id ";
	$sql .= " AND task_id IN (". join(",",$tasks). ")";	
	$sql .= " ORDER BY ".$cur_sort_order." ".$sort_direction;
	$sql .= " LIMIT $limit";

	//echo $sql;
	

$db->query($sql);
while ($db->next_record()) {
	$task_id       = $db->f("task_id");
	$project_id    = $db->f("project_id");
	$task_title    = $db->f("task_title");
	$project_title = $db->f("project_title");
	$status        = $db->f("status_desc");
	$actual_hours  = $db->f("actual_hours");
	$completion    = $db->f("completion");
	$user_name     = $db->f("user_name");
	$creation_date = $db->f("c_date");
	$is_closed     = $db->f("is_closed") ? "yes" : "no";
	$user_id       = $db->f("user_id");
	$hours         = to_hours($db->f("actual_hours"));
	if ($completion) $completion .= "%";
	$opacity       = "";
	if ($db->f("is_closed")) $opacity =  "style='opacity:0.5'";
?>
<?php
	echo "<tr $opacity>";
	echo "<td>$task_id</td>";
	echo "<td><a target='_blank' href='../edit_project.php?project_id=$project_id'>$project_title</a></td>";
	echo "<td><a target='_blank' href='../edit_task.php?task_id=$task_id'>$task_title</a></td>";
	echo "<td class='tdNowrap'><a target='_blank' href='../report_people.php?report_user_id=$user_id'>$user_name</a></td>";
	echo "<td>$status</td>";
	echo "<td>$completion</td>";
	echo "<td>$hours</td>";
	echo "<td class='tdNowrap'>$creation_date</td>";
	echo "<td>$is_closed</td>";
	echo "</tr>";


} 

?>
</tbody>
</table>