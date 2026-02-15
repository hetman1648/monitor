<?php
include("./includes/date_functions.php");
include("./includes/common.php");

CheckSecurity(1);

$db_sub = new DB_Sql();
$db_sub->Database = DATABASE_NAME;
$db_sub->User     = DATABASE_USER;
$db_sub->Password = DATABASE_PASSWORD;
$db_sub->Host     = DATABASE_HOST;
$db_users = new DB_Sql();
$db_users->Database = DATABASE_NAME;
$db_users->User     = DATABASE_USER;
$db_users->Password = DATABASE_PASSWORD;
$db_users->Host     = DATABASE_HOST;

$project_selected = (int)GetParam("project_selected");
$period_selected  = GetParam("period_selected");
$person_selected  = (int)GetParam("person_selected");
$action           = GetParam("action");
$start_date       = GetParam("start_date");
$end_date         = GetParam("end_date");
$submit           = GetParam("submit");
$projects         = GetParam("projects");
$sub_projects     = GetParam("sub_projects");
$team             = GetParam("team");
$multiplier       = GetParam("multiplier");

$vs = ""; $as = ""; $ys = "";
switch (strtolower($team)) {
    case "all":    $sqlteam=""; $as="selected"; break;
    case "viart":  $sqlteam=" AND u.is_viart=1 "; $vs="selected"; break;
    case "yoonoo": $sqlteam=" AND u.is_viart=0 "; $ys="selected"; break;
    default:       $sqlteam=" AND u.is_viart=1 "; $vs="selected"; $team="viart";
}

if (floatval($multiplier)==0 || floatval($multiplier)<=0) $multiplier = 1;

$sqlproject = "";
if ($projects > 0) $sqlproject .= " AND pp.project_id=" . ToSQL($projects, "integer");
$sqlsub_project = "";
if ($sub_projects > 0) $sqlsub_project .= "AND p.project_id=" . ToSQL($sub_projects, "integer");

// Load project/sub-project options
$filter_project = GetOptions("projects", "project_id", "project_title", $projects, "WHERE parent_project_id IS NULL AND is_closed=0");
$filter_sub_project = "";
if ($projects > 0) {
    $filter_sub_project = GetOptions("projects", "project_id", "project_title", $sub_projects, "WHERE parent_project_id=" . ToSQL($projects, "integer"));
}

// Load sub-projects for JS dynamic dropdown
$sub_projects_js = array();
$sql = " SELECT parent_project_id, project_id, project_title FROM projects WHERE parent_project_id IS NOT NULL AND is_closed=0 ORDER BY parent_project_id, project_title";
$db->query($sql, __FILE__, __LINE__);
while ($db->next_record()) {
    $pid = $db->f("parent_project_id");
    if (!isset($sub_projects_js[$pid])) $sub_projects_js[$pid] = array();
    $sub_projects_js[$pid][] = array('id' => $db->f("project_id"), 'title' => $db->f("project_title"));
}

// Period calculation
$period_options = array(
    'none' => '-- Custom --',
    'this_week' => 'This week',
    'last_week' => 'Last 7 days',
    'prev_week' => 'Previous week',
    'this_month' => 'This month',
    'last_month' => 'Last 30 days',
    'prev_month' => 'Previous month',
    'this_year' => 'This year'
);

$current_date = va_time();
$cyear = $current_date[0]; $cmonth = $current_date[1]; $cday = $current_date[2];

$date_periods = array(
    'this_week' => array(
        date("Y-m-d", mktime(0,0,0,$cmonth,$cday-date("w")+1,$cyear)),
        date("Y-m-d", mktime(0,0,0,$cmonth,$cday,$cyear))
    ),
    'last_week' => array(
        date("Y-m-d", mktime(0,0,0,$cmonth,$cday-6,$cyear)),
        date("Y-m-d", mktime(0,0,0,$cmonth,$cday,$cyear))
    ),
    'prev_week' => array(
        date("Y-m-d", mktime(0,0,0,$cmonth,$cday-date("w")-6,$cyear)),
        date("Y-m-d", mktime(0,0,0,$cmonth,$cday-date("w"),$cyear))
    ),
    'this_month' => array(
        date("Y-m-d", mktime(0,0,0,$cmonth,1,$cyear)),
        date("Y-m-d", mktime(0,0,0,$cmonth,$cday,$cyear))
    ),
    'last_month' => array(
        date("Y-m-d", mktime(0,0,0,$cmonth,$cday-30,$cyear)),
        date("Y-m-d", mktime(0,0,0,$cmonth,$cday,$cyear))
    ),
    'prev_month' => array(
        date("Y-m-d", mktime(0,0,0,$cmonth-1,1,$cyear)),
        date("Y-m-t", mktime(0,0,0,$cmonth-1,1,$cyear))
    ),
    'this_year' => array(
        date("Y-m-d", mktime(0,0,0,1,1,$cyear)),
        date("Y-m-d", mktime(0,0,0,$cmonth,$cday,$cyear))
    ),
);

if (!$period_selected) $period_selected = "this_week";

if (!$start_date && !$end_date) {
    if (isset($date_periods[$period_selected])) {
        $start_date = $date_periods[$period_selected][0];
        $end_date = $date_periods[$period_selected][1];
    }
}

$sdt = ""; $edt = "";
if ($start_date) {
    $sd_ar = parse_date(array("YYYY", "-", "MM", "-", "DD"), $start_date, "Start Date");
    $sd_ts = mktime(0, 0, 0, $sd_ar[1], $sd_ar[2], $sd_ar[0]);
    $start_date = @date("Y-m-d", $sd_ts);
    $sdt = @date("Y-m-d 00:00:00", $sd_ts);
}
if ($end_date) {
    $ed_ar = parse_date(array("YYYY", "-", "MM", "-", "DD"), $end_date, "End Date");
    $ed_ts = mktime(0, 0, 0, $ed_ar[1], $ed_ar[2], $ed_ar[0]);
    $end_date = @date("Y-m-d", $ed_ts);
    $edt = @date("Y-m-d 23:59:59", $ed_ts);
}

// --- Helper functions ---
function reaction_output($reaction_hours) {
    if ($reaction_hours > 0) {
        if ($reaction_hours <= 24) return Hours2HoursMins($reaction_hours);
        else return sprintf("%3.1f days", $reaction_hours / 24);
    }
    return "";
}

function userlist_modern($sqlfromwhere, $project_id, $start_date, $end_date, $period_selected, $alllink = false, $team = "viart") {
    global $multiplier, $projects, $sub_projects;
    $db_u = new DB_Sql();
    $db_u->Database = DATABASE_NAME;
    $db_u->User     = DATABASE_USER;
    $db_u->Password = DATABASE_PASSWORD;
    $db_u->Host     = DATABASE_HOST;

    $users = array();
    $sql_users = " SELECT DISTINCT(u.user_id), first_name AS user_name " . $sqlfromwhere;
    $sql_users .= " AND p.project_id=" . $project_id . " ORDER BY user_name";
    $db_u->query($sql_users);
    while ($db_u->next_record()) {
        $users[] = array(
            'user_id' => $db_u->f("user_id"),
            'user_name' => $db_u->f("user_name")
        );
    }
    return $users;
}

// --- Main data queries ---
$main_projects = array();
$dev_records = array();
$person_projects = array();
$task_records = array();
$total_hours = 0; $total_tasks = 0; $total_working_days = 0;
$project_report_title = "";
$user_name = "";
$has_main_data = false;
$has_dev_data = false;
$has_person_data = false;
$has_tasks_data = false;
$person_total_hours = 0; $person_total_tasks = 0;
$person_av_time_per_task = 0; $person_working_days_total = 0;

if ($submit && $start_date && $end_date) {
    // Get user name for person
    $sql = "SELECT CONCAT(first_name,' ',last_name) AS user_name FROM users WHERE user_id=" . $person_selected;
    $db->query($sql);
    $db->next_record();
    $user_name = $db->f("user_name");

    // Total hours/tasks
    $sql = "SELECT SUM(tr.spent_hours) AS count_hours, COUNT(DISTINCT tr.task_id) AS count_tasks, ";
    $sql .= "COUNT(DISTINCT(YEAR(tr.started_date)*372 + MONTH(tr.started_date)*31+DAYOFMONTH(tr.started_date))) AS working_days ";
    $sqlfrom = " FROM projects p,time_report tr,tasks t, users u ";
    $sqlwhere = " WHERE t.project_id = p.project_id AND t.task_id=tr.task_id AND tr.user_id = u.user_id $sqlteam ";
    $trlimits = "";
    if ($sdt) $trlimits .= " AND tr.started_date>='$sdt' ";
    if ($edt) $trlimits .= " AND tr.started_date<='$edt' ";
    $sqlwhere .= $trlimits;
    $sqlfromwhere = $sqlfrom . $sqlwhere;
    $sqlfwjoin = " FROM projects p LEFT JOIN projects subp ON (subp.parent_project_id=p.project_id OR subp.project_id=p.project_id),
                        time_report tr, tasks t, users u
                   WHERE t.project_id=subp.project_id AND t.task_id=tr.task_id AND tr.user_id = u.user_id" . $sqlteam . $trlimits;

    $sql .= $sqlfromwhere;
    $db->query($sql);
    if ($db->next_record()) {
        $total_hours = $db->Record["count_hours"];
        $total_tasks = $db->Record["count_tasks"];
        $total_working_days = $db->Record["working_days"];
    }

    // Working days per project
    $wd = array();
    $sql = " SELECT t.project_id AS proj_id, COUNT(DISTINCT(YEAR(tr.started_date)*372 + MONTH(tr.started_date)*31+DAYOFMONTH(tr.started_date))) AS working_days";
    $sql .= " FROM time_report tr, tasks t, users u, (projects pp LEFT JOIN projects p ON (p.parent_project_id=pp.project_id OR p.project_id=pp.project_id))";
    $sql .= " WHERE t.task_id=tr.task_id AND t.project_id=p.project_id AND tr.user_id=u.user_id " . $sqlteam . $trlimits;
    $sql .= (strlen($sqlsub_project) ? $sqlsub_project : $sqlproject);
    $sql .= " GROUP BY t.project_id ORDER BY t.project_id ";
    $db->query($sql);
    while ($db->next_record()) { $wd[(int)$db->Record["proj_id"]] = (int)$db->Record["working_days"]; }

    // Working days per person per project
    $wdp = array();
    $sql = "SELECT p.project_id AS proj_id, tr.user_id AS usr_id, ";
    $sql .= "COUNT(DISTINCT(YEAR(tr.started_date)*372 + MONTH(tr.started_date)*31+DAYOFMONTH(tr.started_date))) AS working_days ";
    $sql .= "FROM time_report tr, tasks t, users u, projects pp LEFT JOIN projects p ON (p.parent_project_id=pp.project_id OR p.project_id=pp.project_id) ";
    $sql .= "WHERE t.task_id=tr.task_id AND t.project_id=p.project_id AND tr.user_id=u.user_id " . $sqlteam . $trlimits;
    $sql .= " GROUP BY p.project_id, tr.user_id ";
    $db->query($sql);
    while ($db->next_record()) { $wdp[(int)$db->Record["proj_id"]][(int)$db->Record["usr_id"]] = (int)$db->Record["working_days"]; }

    // Total working days per person
    $wdu = array();
    $sql = " SELECT tr.user_id AS usr_id, COUNT(DISTINCT(YEAR(tr.started_date)*372 + MONTH(tr.started_date)*31+DAYOFMONTH(tr.started_date))) AS working_days";
    $sql .= " FROM time_report tr, tasks t, users u, projects pp LEFT JOIN projects p ON (p.parent_project_id=pp.project_id OR p.project_id=pp.project_id) ";
    $sql .= " WHERE t.task_id=tr.task_id AND t.project_id=p.project_id AND tr.user_id=u.user_id" . $sqlteam . $trlimits;
    $sql .= " GROUP BY tr.user_id ";
    $db->query($sql);
    while ($db->next_record()) { $wdu[(int)$db->Record["usr_id"]] = (int)$db->Record["working_days"]; }

    // Reaction times per project
    $rh = array();
    $sql = "SELECT pid, AVG(reaction_hours) AS a_reaction_hours FROM ( ";
    $sql .= "SELECT p.project_id AS pid, (UNIX_TIMESTAMP(MAX(tr.report_date))-UNIX_TIMESTAMP(t.creation_date))/3600 AS reaction_hours ";
    $sql .= "FROM time_report tr, tasks t, users u, projects p LEFT JOIN projects subp ON (subp.parent_project_id = p.project_id OR subp.project_id=p.project_id) ";
    $sql .= "WHERE t.task_id = tr.task_id AND t.project_id = subp.project_id AND tr.user_id = u.user_id " . $sqlteam . $trlimits;
    $sql .= " AND t.task_status_id =4 GROUP BY p.project_id, tr.task_id ) AS tx GROUP BY pid";
    $db->query($sql);
    while ($db->next_record()) { $rh[(int)$db->Record["pid"]] = (float)$db->Record["a_reaction_hours"]; }

    // Reaction times per project per person
    $rhp = array();
    $sql = "SELECT pid, uid, AVG(reaction_hours) AS a_u_reaction_hours FROM ( ";
    $sql .= "SELECT p.project_id AS pid, u.user_id AS uid, (UNIX_TIMESTAMP(MAX(tr.report_date))-UNIX_TIMESTAMP(t.creation_date))/3600 AS reaction_hours ";
    $sql .= "FROM time_report tr, tasks t, users u, projects p LEFT JOIN projects subp ON (subp.parent_project_id = p.project_id OR subp.project_id=p.project_id) ";
    $sql .= "WHERE t.task_id = tr.task_id AND t.project_id = subp.project_id AND tr.user_id = u.user_id " . $sqlteam . $trlimits;
    $sql .= " AND t.task_status_id =4 GROUP BY pid, tr.task_id, u.user_id ) AS tx GROUP BY uid,pid";
    $db->query($sql);
    while ($db->next_record()) { $rhp[(int)$db->Record["pid"]][(int)$db->Record["uid"]] = (float)$db->Record["a_u_reaction_hours"]; }

    // === MAIN PROJECTS QUERY ===
    $sql = " SELECT pp.project_id, pp.project_title, IF (p.parent_project_id, p.parent_project_id, p.project_id) AS groupparent,";
    $sql .= " SUM(tr.spent_hours) AS count_hours, COUNT(DISTINCT tr.task_id) AS count_tasks ";
    $sql .= " FROM projects p, time_report tr, tasks t, users u, projects pp ";
    $sql .= " WHERE t.project_id = p.project_id AND IF (p.parent_project_id, p.parent_project_id, p.project_id)=pp.project_id ";
    $sql .= " AND tr.user_id=u.user_id " . $sqlteam . " AND t.task_id = tr.task_id " . $sqlproject . $trlimits;
    $sql .= " GROUP BY groupparent ORDER BY count_hours DESC";
    $db->query($sql);

    if ($db->num_rows()) {
        $has_main_data = true;
        while ($db->next_record()) {
            $pid = $db->f("project_id");
            $ptitle = $db->f("project_title");
            $ch = $db->f("count_hours");
            $ct = $db->f("count_tasks");

            if ($pid == $project_selected) $project_report_title = $ptitle;

            $proj = array(
                'project_id' => $pid, 'project_title' => $ptitle,
                'count_hours' => $ch, 'count_tasks' => $ct,
                'is_selected' => ($pid == $project_selected),
                'hours_pct' => ($total_hours > 0.0001) ? sprintf("%3.1f", $ch / $total_hours * 100) : "0.0",
                'time_per_task' => $ct > 0 ? Hours2HoursMins($ch / $ct) : "",
                'working_days' => isset($wd[$pid]) ? $wd[$pid] : 0,
                'hours_per_day' => (isset($wd[$pid]) && $wd[$pid] > 0) ? Hours2HoursMins($ch / $wd[$pid]) : "",
                'tasks_per_day' => (isset($wd[$pid]) && $wd[$pid] > 0) ? sprintf("%3.1f", $ct / $wd[$pid]) : "0.0",
                'reaction_time' => isset($rh[$pid]) ? reaction_output($rh[$pid]) : "",
                'users_list' => userlist_modern($sqlfwjoin, $pid, $start_date, $end_date, $period_selected, true, $team),
                'sub_projects' => array()
            );

            // Sub-projects
            $sqlsub = " SELECT p.project_id, p.project_title, SUM(tr.spent_hours) AS count_hours, COUNT(DISTINCT tr.task_id) AS count_tasks ";
            $sqlsub .= " FROM projects p, time_report tr, tasks t, users u WHERE t.project_id = p.project_id AND tr.task_id=t.task_id " . $trlimits;
            $sqlsub .= " AND tr.user_id=u.user_id " . $sqlteam . " AND p.parent_project_id=" . $pid . $sqlsub_project;
            $sqlsub .= " GROUP BY p.project_id ORDER BY count_hours DESC";
            $db_sub->query($sqlsub);
            while ($db_sub->next_record()) {
                $spid = $db_sub->f("project_id");
                $sptitle = $db_sub->f("project_title");
                $sch = $db_sub->f("count_hours");
                $sct = $db_sub->f("count_tasks");
                if ($spid == $project_selected) $project_report_title = $sptitle;
                $proj['sub_projects'][] = array(
                    'project_id' => $spid, 'project_title' => $sptitle,
                    'count_hours' => $sch, 'count_tasks' => $sct,
                    'is_selected' => ($spid == $project_selected),
                    'hours_pct' => ($total_hours > 0.0001) ? sprintf("%3.1f", $sch / $total_hours * 100) : "0.0",
                    'time_per_task' => $sct > 0 ? Hours2HoursMins($sch / $sct) : "",
                    'working_days' => isset($wd[$spid]) ? $wd[$spid] : 0,
                    'hours_per_day' => (isset($wd[$spid]) && $wd[$spid] > 0) ? Hours2HoursMins($sch / $wd[$spid]) : "",
                    'tasks_per_day' => (isset($wd[$spid]) && $wd[$spid] > 0) ? sprintf("%3.1f", $sct / $wd[$spid]) : "0.0",
                    'reaction_time' => isset($rh[$spid]) ? reaction_output($rh[$spid]) : "",
                    'users_list' => userlist_modern($sqlfwjoin, $spid, $start_date, $end_date, $period_selected, true, $team)
                );
            }
            $main_projects[] = $proj;
        }
    }

    // === DEVELOPERS REPORT ===
    if (isset($person_selected) && isset($project_selected) && $project_selected > 0 && is_int($project_selected) && ($action == "dev" || $action == "devtasks")) {
        $sql = "SELECT u.user_id AS uid, CONCAT(first_name,' ',last_name) AS user_name, SUM(tr.spent_hours) AS count_hours, COUNT(DISTINCT(tasks.task_id)) AS count_tasks ";
        $sql .= "FROM users u, tasks, time_report AS tr, projects AS p LEFT JOIN projects AS subp ON (subp.parent_project_id=p.project_id OR subp.project_id=p.project_id) ";
        $sql .= "WHERE p.project_id=$project_selected AND tr.user_id=u.user_id AND tasks.task_id=tr.task_id ";
        $sql .= "AND subp.project_id=tasks.project_id " . $sqlteam . $trlimits;
        $sql .= " GROUP BY u.user_id ORDER BY user_name ASC";
        $db->query($sql);
        if ($db->num_rows()) {
            $has_dev_data = true;
            while ($db->next_record()) {
                $duid = $db->f("uid");
                $dch = $db->f("count_hours");
                $dct = $db->f("count_tasks");
                $dwdval = isset($wdp[$project_selected][$duid]) ? $wdp[$project_selected][$duid] : 0;
                $dev_records[] = array(
                    'user_id' => $duid, 'user_name' => $db->f("user_name"),
                    'count_hours' => $dch, 'count_tasks' => $dct,
                    'time_per_task' => $dct > 0 ? Hours2HoursMins($dch / $dct) : "",
                    'working_days' => $dwdval,
                    'hours_per_day' => $dwdval > 0 ? Hours2HoursMins($dch / $dwdval) : "",
                    'tasks_per_day' => $dwdval > 0 ? sprintf("%3.1f", $dct / $dwdval) : "",
                    'reaction_time' => isset($rhp[$project_selected][$duid]) ? reaction_output($rhp[$project_selected][$duid]) : "",
                    'is_selected' => ($person_selected == $duid)
                );
            }
        }
    }

    // === PERSON PROJECTS REPORT ===
    $ps = (isset($person_selected) && $person_selected > 0 && is_int($person_selected));
    if ($ps && $action == "projects") {
        $sql = "SELECT SUM(tr.spent_hours) AS count_hours, COUNT(DISTINCT tr.task_id) AS count_tasks ";
        $sql .= " FROM projects p, time_report tr, tasks t WHERE t.project_id = p.project_id AND t.task_id=tr.task_id AND tr.user_id = $person_selected " . $trlimits;
        $db->query($sql);
        if ($db->next_record()) {
            $person_total_hours = $db->Record["count_hours"];
            $person_total_tasks = $db->Record["count_tasks"];
            $person_av_time_per_task = $person_total_tasks > 0 ? $person_total_hours / $person_total_tasks : 0;
        }
        $person_working_days_total = isset($wdu[$person_selected]) ? $wdu[$person_selected] : 0;

        $sql = " SELECT pp.project_id, pp.project_title, IF (p.parent_project_id, p.parent_project_id, p.project_id) AS groupparent,";
        $sql .= " SUM(tr.spent_hours) AS count_hours, COUNT(DISTINCT tr.task_id) AS count_tasks ";
        $sql .= " FROM projects p, time_report tr, tasks t, projects pp ";
        $sql .= " WHERE t.project_id = p.project_id AND t.task_id = tr.task_id " . $trlimits;
        $sql .= " AND IF(p.parent_project_id, p.parent_project_id, p.project_id)=pp.project_id AND tr.user_id = $person_selected";
        $sql .= " GROUP BY groupparent ORDER BY count_hours DESC";
        $db->query($sql);
        if ($db->num_rows()) {
            $has_person_data = true;
            while ($db->next_record()) {
                $ppid = $db->f("project_id");
                $pch = $db->f("count_hours");
                $pct = $db->f("count_tasks");
                $pwdval = isset($wdp[$ppid][$person_selected]) ? $wdp[$ppid][$person_selected] : 0;
                $pproj = array(
                    'project_id' => $ppid, 'project_title' => $db->f("project_title"),
                    'count_hours' => $pch, 'count_tasks' => $pct,
                    'is_selected' => ($ppid == $project_selected),
                    'hours_pct' => ($person_total_hours > 0.0001) ? sprintf("%3.1f", $pch / $person_total_hours * 100) : "0.0",
                    'time_per_task' => $pct > 0 ? Hours2HoursMins($pch / $pct) : "",
                    'working_days' => $pwdval,
                    'hours_per_day' => $pwdval > 0 ? Hours2HoursMins($pch / $pwdval) : "",
                    'tasks_per_day' => $pwdval > 0 ? sprintf("%3.1f", $pct / $pwdval) : "0.0",
                    'reaction_time' => isset($rhp[$ppid][$person_selected]) ? reaction_output($rhp[$ppid][$person_selected]) : "",
                    'sub_projects' => array()
                );
                // Sub-projects for person
                $sqlsub = " SELECT p.project_id, p.project_title, SUM(tr.spent_hours) AS count_hours, COUNT(DISTINCT tr.task_id) AS count_tasks ";
                $sqlsub .= $sqlfrom . " " . $sqlwhere . " AND p.parent_project_id=$ppid AND tr.user_id = $person_selected";
                $sqlsub .= " GROUP BY p.project_id ORDER BY count_hours DESC";
                $db_sub->query($sqlsub);
                while ($db_sub->next_record()) {
                    $spid = $db_sub->f("project_id");
                    $sch = $db_sub->f("count_hours");
                    $sct = $db_sub->f("count_tasks");
                    $swdval = isset($wdp[$spid][$person_selected]) ? $wdp[$spid][$person_selected] : 0;
                    $pproj['sub_projects'][] = array(
                        'project_id' => $spid, 'project_title' => $db_sub->f("project_title"),
                        'count_hours' => $sch, 'count_tasks' => $sct,
                        'is_selected' => ($spid == $project_selected),
                        'hours_pct' => ($person_total_hours > 0.0001) ? sprintf("%3.1f", $sch / $person_total_hours * 100) : "0.0",
                        'time_per_task' => $sct > 0 ? Hours2HoursMins($sch / $sct) : "",
                        'working_days' => $swdval,
                        'hours_per_day' => $swdval > 0 ? Hours2HoursMins($sch / $swdval) : "",
                        'tasks_per_day' => $swdval > 0 ? sprintf("%3.1f", $sct / $swdval) : "0.0",
                        'reaction_time' => isset($rhp[$spid][$person_selected]) ? reaction_output($rhp[$spid][$person_selected]) : ""
                    );
                }
                $person_projects[] = $pproj;
            }
        }
    }

    // === TASKS REPORT ===
    if (isset($project_selected) && $project_selected > 0 && is_int($project_selected) && ($action == "tasks" || $action == "projects" || $action == "devtasks")) {
        $sql = " SELECT t.task_id AS task_identifier, t.task_title, SUM(tr.spent_hours) AS count_hours, lts.status_desc AS status_description, first_name ";
        $sql .= " FROM (projects p LEFT JOIN projects subp ON (subp.parent_project_id=p.project_id)) ";
        $sql .= " INNER JOIN tasks t ON (t.project_id = p.project_id) ";
        $sql .= " INNER JOIN time_report tr ON (t.task_id = tr.task_id " . $trlimits;
        if ($ps) $sql .= " AND tr.user_id=$person_selected ";
        $sql .= " ) INNER JOIN users u ON (tr.user_id=u.user_id " . $sqlteam . ") ";
        $sql .= " INNER JOIN lookup_tasks_statuses lts ON (lts.status_id = t.task_status_id) ";
        $sql .= " WHERE p.project_id=" . $project_selected;
        $sql .= " GROUP BY t.task_id,p.project_id ORDER BY count_hours DESC";
        $db->query($sql);
        if ($db->num_rows()) {
            $has_tasks_data = true;
            while ($db->next_record()) {
                $tid = $db->f("task_identifier");
                // Get developers for this task
                $sql_u = "SELECT u.first_name AS user_name" . $sqlfrom . " " . $sqlwhere . " AND tr.task_id=" . $tid . " GROUP BY u.user_id ORDER BY user_name";
                $db_users->query($sql_u);
                $devs = array();
                while ($db_users->next_record()) { $devs[] = $db_users->f("user_name"); }
                $task_records[] = array(
                    'task_id' => $tid, 'task_title' => $db->f("task_title"),
                    'count_hours' => $db->f("count_hours"), 'status' => $db->f("status_description"),
                    'developers' => $devs
                );
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects Report - Sayu Monitor</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; color: #2d3748; font-size: 14px; }
        .container { max-width: 1500px; margin: 0 auto; padding: 24px; }
        .page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; flex-wrap: wrap; gap: 16px; }
        .page-title { font-size: 1.5rem; font-weight: 700; color: #2d3748; }
        .page-subtitle { color: #718096; font-size: 0.85rem; margin-top: 4px; }
        .filter-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); padding: 20px; margin-bottom: 20px; }
        .filter-form { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 4px; }
        .filter-group label { font-size: 0.75rem; font-weight: 600; color: #718096; text-transform: uppercase; letter-spacing: 0.5px; }
        .filter-group select, .filter-group input[type="text"] { padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.85rem; font-family: inherit; background: #fff; min-width: 140px; }
        .filter-group select:focus, .filter-group input:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }
        .filter-actions { display: flex; gap: 8px; align-items: flex-end; }
        .btn { padding: 8px 16px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; cursor: pointer; border: none; font-family: inherit; transition: all 0.2s; }
        .btn-primary { background: #667eea; color: #fff; }
        .btn-primary:hover { background: #5a67d8; }
        .btn-secondary { background: #e2e8f0; color: #4a5568; }
        .btn-secondary:hover { background: #cbd5e0; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); overflow: hidden; margin-bottom: 20px; }
        .card-header { padding: 14px 20px; background: #f8f9fa; border-bottom: 1px solid #e2e8f0; }
        .card-title { font-weight: 600; font-size: 1rem; color: #2d3748; }
        .card-subtitle { font-size: 0.82rem; color: #718096; margin-top: 2px; }
        .scroll-table { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { padding: 8px 10px; text-align: center; font-weight: 600; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.3px; color: #718096; background: #f8f9fa; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
        .data-table th:first-child { text-align: left; padding-left: 16px; }
        .data-table td { padding: 7px 10px; border-bottom: 1px solid #f1f5f9; font-size: 0.82rem; text-align: center; white-space: nowrap; }
        .data-table td:first-child { text-align: left; padding-left: 16px; }
        .data-table td a { color: #667eea; text-decoration: none; }
        .data-table td a:hover { text-decoration: underline; }
        .data-table tr:hover td { background: #f8f9fc; }
        .row-selected td { background: #fffff0 !important; }
        .row-sub td:first-child { padding-left: 32px !important; }
        .total-row td { font-weight: 700; background: #f0f4ff; border-top: 2px solid #667eea; }
        .devs-cell { font-size: 0.78rem; color: #4a5568; text-align: left !important; white-space: normal !important; }
        .empty-state { text-align: center; padding: 40px; color: #718096; }
        @media (max-width: 768px) {
            .container { padding: 12px; }
            .filter-form { flex-direction: column; }
            .filter-group select, .filter-group input { min-width: 100%; }
            .page-title { font-size: 1.2rem; }
        }
    </style>
</head>
<body>
    <?php include("./includes/modern_header.php"); ?>

    <div class="container">
        <div class="page-header">
            <div>
                <h1 class="page-title">Projects Report</h1>
                <?php if ($submit && $start_date && $end_date): ?>
                <p class="page-subtitle"><?php echo htmlspecialchars($start_date); ?> &mdash; <?php echo htmlspecialchars($end_date); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="filter-card">
            <form name="frmFilter" action="projects_report.php" method="GET">
                <div class="filter-form">
                    <div class="filter-group">
                        <label>Period</label>
                        <select name="period_selected" id="periodSelect" onchange="selectPeriod()">
                            <?php foreach ($period_options as $val => $desc): ?>
                            <option value="<?php echo $val; ?>"<?php echo ($val == $period_selected) ? ' selected' : ''; ?>><?php echo $desc; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Start Date</label>
                        <input type="text" name="start_date" id="startDate" value="<?php echo htmlspecialchars($start_date); ?>" placeholder="YYYY-MM-DD">
                    </div>
                    <div class="filter-group">
                        <label>End Date</label>
                        <input type="text" name="end_date" id="endDate" value="<?php echo htmlspecialchars($end_date); ?>" placeholder="YYYY-MM-DD">
                    </div>
                    <div class="filter-group">
                        <label>Multiplier</label>
                        <input type="text" name="multiplier" value="<?php echo htmlspecialchars($multiplier); ?>" style="min-width:80px">
                    </div>
                    <div class="filter-group">
                        <label>Team</label>
                        <select name="team">
                            <option value="all" <?php echo $as; ?>>All teams</option>
                            <option value="viart" <?php echo $vs; ?>>Sayu Ukraine</option>
                            <option value="yoonoo" <?php echo $ys; ?>>Sayu UK</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Project</label>
                        <select name="projects" id="projectSelect" onchange="updateSubProjects()">
                            <option value="-1">&nbsp;</option>
                            <?php echo $filter_project; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Sub-Project</label>
                        <select name="sub_projects" id="subProjectSelect">
                            <option value="-1">&nbsp;</option>
                            <?php echo $filter_sub_project; ?>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" name="submit" value="1" class="btn btn-primary">Filter</button>
                        <button type="button" class="btn btn-secondary" onclick="window.location='projects_report.php'">Clear</button>
                    </div>
                </div>
            </form>
        </div>

        <?php if ($submit && $start_date && $end_date): ?>

        <!-- Main Projects Report -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">Projects Report</div>
                <div class="card-subtitle"><?php echo htmlspecialchars($start_date); ?> to <?php echo htmlspecialchars($end_date); ?></div>
            </div>
            <?php if ($has_main_data): ?>
            <div class="scroll-table">
                <table class="data-table">
                    <thead><tr>
                        <th>Project</th><th>Hours</th><th>M</th><th>%</th><th>Tasks</th><th>Per task</th><th>Work days</th><th>Hrs/day</th><th>Tasks/day</th><th>Reaction</th><th>Developers</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($main_projects as $p):
                        $link = "projects_report.php?start_date=" . urlencode($start_date) . "&end_date=" . urlencode($end_date) . "&period_selected=" . urlencode($period_selected) . "&project_selected=" . $p['project_id'] . "&person_selected=all&team=" . urlencode($team) . "&multiplier=" . urlencode($multiplier) . "&projects=" . urlencode($projects) . "&sub_projects=" . urlencode($sub_projects) . "&action=dev&submit=1";
                        $tasks_link = "projects_report.php?start_date=" . urlencode($start_date) . "&end_date=" . urlencode($end_date) . "&period_selected=" . urlencode($period_selected) . "&project_selected=" . $p['project_id'] . "&action=tasks&team=" . urlencode($team) . "&multiplier=" . urlencode($multiplier) . "&submit=1";
                    ?>
                    <tr<?php echo $p['is_selected'] ? ' class="row-selected"' : ''; ?>>
                        <td><a href="<?php echo $link; ?>"><?php echo htmlspecialchars($p['project_title']); ?></a></td>
                        <td><?php echo Hours2HoursMins($p['count_hours']); ?></td>
                        <td><?php echo number_format($p['count_hours'] * $multiplier, 2); ?></td>
                        <td><?php echo $p['hours_pct']; ?>%</td>
                        <td><a href="<?php echo $tasks_link; ?>"><?php echo $p['count_tasks']; ?></a></td>
                        <td><?php echo $p['time_per_task']; ?></td>
                        <td><?php echo $p['working_days'] ?: ''; ?></td>
                        <td><?php echo $p['hours_per_day']; ?></td>
                        <td><?php echo $p['tasks_per_day']; ?></td>
                        <td><?php echo $p['reaction_time']; ?></td>
                        <td class="devs-cell"><?php
                            $ulinks = array();
                            foreach ($p['users_list'] as $u) {
                                $ulink = "projects_report.php?start_date=" . urlencode($start_date) . "&end_date=" . urlencode($end_date) . "&period_selected=" . urlencode($period_selected) . "&project_selected=" . $p['project_id'] . "&person_selected=" . $u['user_id'] . "&projects=" . urlencode($projects) . "&sub_projects=" . urlencode($sub_projects) . "&action=projects&team=" . urlencode($team) . "&multiplier=" . urlencode($multiplier) . "&submit=1";
                                $ulinks[] = '<a href="' . $ulink . '">' . htmlspecialchars($u['user_name']) . '</a>';
                            }
                            echo implode(', ', $ulinks);
                        ?></td>
                    </tr>
                    <?php foreach ($p['sub_projects'] as $sp):
                        $slink = "projects_report.php?start_date=" . urlencode($start_date) . "&end_date=" . urlencode($end_date) . "&period_selected=" . urlencode($period_selected) . "&project_selected=" . $sp['project_id'] . "&person_selected=all&team=" . urlencode($team) . "&multiplier=" . urlencode($multiplier) . "&projects=" . urlencode($projects) . "&sub_projects=" . urlencode($sub_projects) . "&action=dev&submit=1";
                        $stlink = "projects_report.php?start_date=" . urlencode($start_date) . "&end_date=" . urlencode($end_date) . "&period_selected=" . urlencode($period_selected) . "&project_selected=" . $sp['project_id'] . "&action=tasks&team=" . urlencode($team) . "&multiplier=" . urlencode($multiplier) . "&submit=1";
                    ?>
                    <tr class="row-sub<?php echo $sp['is_selected'] ? ' row-selected' : ''; ?>">
                        <td><a href="<?php echo $slink; ?>"><?php echo htmlspecialchars($sp['project_title']); ?></a></td>
                        <td><?php echo Hours2HoursMins($sp['count_hours']); ?></td>
                        <td><?php echo number_format($sp['count_hours'] * $multiplier, 2); ?></td>
                        <td><?php echo $sp['hours_pct']; ?>%</td>
                        <td><a href="<?php echo $stlink; ?>"><?php echo $sp['count_tasks']; ?></a></td>
                        <td><?php echo $sp['time_per_task']; ?></td>
                        <td><?php echo $sp['working_days'] ?: ''; ?></td>
                        <td><?php echo $sp['hours_per_day']; ?></td>
                        <td><?php echo $sp['tasks_per_day']; ?></td>
                        <td><?php echo $sp['reaction_time']; ?></td>
                        <td class="devs-cell"><?php
                            $sulinks = array();
                            foreach ($sp['users_list'] as $u) {
                                $ulink = "projects_report.php?start_date=" . urlencode($start_date) . "&end_date=" . urlencode($end_date) . "&period_selected=" . urlencode($period_selected) . "&project_selected=" . $sp['project_id'] . "&person_selected=" . $u['user_id'] . "&projects=" . urlencode($projects) . "&sub_projects=" . urlencode($sub_projects) . "&action=projects&team=" . urlencode($team) . "&multiplier=" . urlencode($multiplier) . "&submit=1";
                                $sulinks[] = '<a href="' . $ulink . '">' . htmlspecialchars($u['user_name']) . '</a>';
                            }
                            echo implode(', ', $sulinks);
                        ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <td>Total</td>
                        <td><?php echo Hours2HoursMins($total_hours); ?></td>
                        <td><?php echo number_format($total_hours * $multiplier, 2); ?></td>
                        <td></td>
                        <td><?php echo $total_tasks; ?></td>
                        <td><?php echo $total_tasks > 0 ? Hours2HoursMins($total_hours / $total_tasks) : ''; ?></td>
                        <td><?php echo $total_working_days; ?></td>
                        <td><?php echo $total_working_days > 0 ? Hours2HoursMins($total_hours / $total_working_days) : ''; ?></td>
                        <td><?php echo $total_working_days > 0 ? sprintf("%3.1f", $total_tasks / $total_working_days) : ''; ?></td>
                        <td></td><td></td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state"><p>No data for this period</p></div>
            <?php endif; ?>
        </div>

        <?php if ($has_dev_data): ?>
        <div class="card">
            <div class="card-header"><div class="card-title">Developers Report for <?php echo htmlspecialchars($project_report_title); ?></div></div>
            <div class="scroll-table">
                <table class="data-table">
                    <thead><tr><th>Person</th><th>Hours</th><th>M</th><th>Tasks</th><th>Per task</th><th>Work days</th><th>Hrs/day</th><th>Tasks/day</th><th>Reaction</th></tr></thead>
                    <tbody>
                    <?php foreach ($dev_records as $d):
                        $dtlink = "projects_report.php?start_date=" . urlencode($start_date) . "&end_date=" . urlencode($end_date) . "&period_selected=" . urlencode($period_selected) . "&project_selected=" . $project_selected . "&person_selected=" . $d['user_id'] . "&team=" . urlencode($team) . "&multiplier=" . urlencode($multiplier) . "&action=devtasks&submit=1";
                    ?>
                    <tr<?php echo $d['is_selected'] ? ' class="row-selected"' : ''; ?>>
                        <td><?php echo htmlspecialchars($d['user_name']); ?></td>
                        <td><?php echo Hours2HoursMins($d['count_hours']); ?></td>
                        <td><?php echo number_format($d['count_hours'] * $multiplier, 2); ?></td>
                        <td><a href="<?php echo $dtlink; ?>"><?php echo $d['count_tasks']; ?></a></td>
                        <td><?php echo $d['time_per_task']; ?></td>
                        <td><?php echo $d['working_days']; ?></td>
                        <td><?php echo $d['hours_per_day']; ?></td>
                        <td><?php echo $d['tasks_per_day']; ?></td>
                        <td><?php echo $d['reaction_time']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($has_person_data): ?>
        <div class="card">
            <div class="card-header"><div class="card-title">Projects Report for <?php echo htmlspecialchars($user_name); ?></div></div>
            <div class="scroll-table">
                <table class="data-table">
                    <thead><tr><th>Project</th><th>Hours</th><th>M</th><th>%</th><th>Tasks</th><th>Per task</th><th>Work days</th><th>Hrs/day</th><th>Tasks/day</th><th>Reaction</th></tr></thead>
                    <tbody>
                    <?php foreach ($person_projects as $pp):
                        $pplink = "projects_report.php?start_date=" . urlencode($start_date) . "&end_date=" . urlencode($end_date) . "&period_selected=" . urlencode($period_selected) . "&project_selected=" . $pp['project_id'] . "&person_selected=" . $person_selected . "&multiplier=" . urlencode($multiplier) . "&team=" . urlencode($team) . "&action=projects&submit=1";
                    ?>
                    <tr<?php echo $pp['is_selected'] ? ' class="row-selected"' : ''; ?>>
                        <td><?php echo htmlspecialchars($pp['project_title']); ?></td>
                        <td><?php echo Hours2HoursMins($pp['count_hours']); ?></td>
                        <td><?php echo number_format($pp['count_hours'] * $multiplier, 2); ?></td>
                        <td><?php echo $pp['hours_pct']; ?>%</td>
                        <td><a href="<?php echo $pplink; ?>"><?php echo $pp['count_tasks']; ?></a></td>
                        <td><?php echo $pp['time_per_task']; ?></td>
                        <td><?php echo $pp['working_days']; ?></td>
                        <td><?php echo $pp['hours_per_day']; ?></td>
                        <td><?php echo $pp['tasks_per_day']; ?></td>
                        <td><?php echo $pp['reaction_time']; ?></td>
                    </tr>
                    <?php foreach ($pp['sub_projects'] as $sp): ?>
                    <tr class="row-sub<?php echo $sp['is_selected'] ? ' row-selected' : ''; ?>">
                        <td><?php echo htmlspecialchars($sp['project_title']); ?></td>
                        <td><?php echo Hours2HoursMins($sp['count_hours']); ?></td>
                        <td><?php echo number_format($sp['count_hours'] * $multiplier, 2); ?></td>
                        <td><?php echo $sp['hours_pct']; ?>%</td>
                        <td><?php echo $sp['count_tasks']; ?></td>
                        <td><?php echo $sp['time_per_task']; ?></td>
                        <td><?php echo $sp['working_days']; ?></td>
                        <td><?php echo $sp['hours_per_day']; ?></td>
                        <td><?php echo $sp['tasks_per_day']; ?></td>
                        <td><?php echo $sp['reaction_time']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <td>Total</td>
                        <td><?php echo Hours2HoursMins($person_total_hours); ?></td>
                        <td><?php echo number_format($person_total_hours * $multiplier, 2); ?></td>
                        <td></td>
                        <td><?php echo $person_total_tasks; ?></td>
                        <td><?php echo Hours2HoursMins($person_av_time_per_task); ?></td>
                        <td><?php echo $person_working_days_total; ?></td>
                        <td><?php echo $person_working_days_total ? Hours2HoursMins($person_total_hours / $person_working_days_total) : ''; ?></td>
                        <td><?php echo $person_working_days_total ? sprintf("%3.1f", $person_total_tasks / $person_working_days_total) : ''; ?></td>
                        <td></td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($has_tasks_data): ?>
        <div class="card">
            <div class="card-header"><div class="card-title">Tasks Report for <?php echo htmlspecialchars($project_report_title); ?><?php echo $ps ? ': ' . htmlspecialchars($user_name) : ''; ?></div></div>
            <div class="scroll-table">
                <table class="data-table">
                    <thead><tr><th>Task</th><th>Hours</th><th>M</th><th>Status</th><th>Developers</th></tr></thead>
                    <tbody>
                    <?php foreach ($task_records as $tr): ?>
                    <tr>
                        <td><a href="edit_task.php?task_id=<?php echo $tr['task_id']; ?>"><?php echo htmlspecialchars($tr['task_title']); ?></a></td>
                        <td><?php echo Hours2HoursMins($tr['count_hours']); ?></td>
                        <td><?php echo number_format($tr['count_hours'] * $multiplier, 2); ?></td>
                        <td><?php echo htmlspecialchars($tr['status']); ?></td>
                        <td class="devs-cell"><?php echo htmlspecialchars(implode(', ', $tr['developers'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php endif; // submit ?>
    </div>

    <script>
    // Period dates for JS
    var datePeriods = <?php echo json_encode($date_periods); ?>;
    function selectPeriod() {
        var val = document.getElementById('periodSelect').value;
        if (datePeriods[val]) {
            document.getElementById('startDate').value = datePeriods[val][0];
            document.getElementById('endDate').value = datePeriods[val][1];
        }
    }

    // Sub-projects dynamic dropdown
    var subProjectsData = <?php echo json_encode($sub_projects_js); ?>;
    function updateSubProjects() {
        var pid = document.getElementById('projectSelect').value;
        var sel = document.getElementById('subProjectSelect');
        sel.innerHTML = '<option value="-1">&nbsp;</option>';
        if (subProjectsData[pid]) {
            subProjectsData[pid].forEach(function(sp) {
                var opt = document.createElement('option');
                opt.value = sp.id;
                opt.textContent = sp.title;
                sel.appendChild(opt);
            });
        }
    }
    </script>
</body>
</html>
