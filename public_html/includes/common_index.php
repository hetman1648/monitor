<?
function Tasks_Show()
{
  global $T;
  global $db;
  global $sTasksErr;
  $sWhere = "";
  $sOrder = "";
  $HasParam = false;

  $T->set_var("TransitParams", "task_status_id=" . ToURL(stripslashes(GetParam("task_status_id"))) . "&project_id=" . ToURL(stripslashes(GetParam("project_id"))) . "&priority_id=" . ToURL(stripslashes(GetParam("priority_id"))) . "&task_type_id=" . ToURL(stripslashes(GetParam("task_type_id"))) . "&");
  //"TransitParams", "project_id=" . ToURL(stripslashes(GetParam("project_id"))) . "&"); 

  $ppriority_id = GetParam("priority_id");
  if(is_number($ppriority_id) && strlen($ppriority_id))
    $ppriority_id = round($ppriority_id);
  else 
    $ppriority_id = "";
  if(strlen($ppriority_id)) 
  {
    $HasParam = true;
    $sWhere .= "tasks.priority_id=" . $ppriority_id;
  }
  $pproject_id = GetParam("project_id");
  if(is_number($pproject_id) && strlen($pproject_id))
    $pproject_id = round($pproject_id);
  else 
    $pproject_id = "";
  if(strlen($pproject_id)) 
  {
    if ($sWhere) $sWhere .= " and ";
    $HasParam = true;
    $sWhere .= "tasks.project_id=" . $pproject_id;
  }
  $ptask_status_id = GetParam("task_status_id");
  if(is_number($ptask_status_id) && strlen($ptask_status_id))
    $ptask_status_id = round($ptask_status_id);
  else 
    $ptask_status_id = "1";

  if($ptask_status_id)
  {
    if ($sWhere != "") $sWhere .= " and ";
    $HasParam = true;
    $sWhere .= "tasks.task_status_id=" . $ptask_status_id;
  }
  $ptask_type_id = GetParam("task_type_id");
  if(is_number($ptask_type_id) && strlen($ptask_type_id))
    $ptask_type_id = round($ptask_type_id);
  else 
    $ptask_type_id = "";
  if(strlen($ptask_type_id)) 
  {
    if ($sWhere != "") $sWhere .= " and ";
    $HasParam = true;
    $sWhere .= "tasks.task_type_id=" . $ptask_type_id;
  }

  //-- for people with privileges lower then Architect search is predefined
  $tmp_priv_id = GetSessionParam("privilege_id");
  if ($tmp_priv_id < PRIV_ARCHITECT)
  {
    if ($sWhere != "") $sWhere .= " and ";
    $HasParam = true;
    $sWhere .= "tasks.responsible_user_id=" . GetSessionParam("UserID");
  }

  if ($tmp_priv_id==PRIV_PARTNER)
  {
    if ($sWhere != "") $sWhere .= " and ";
    $HasParam = true;
    $sWhere .= "tasks.created_person_id=" . GetSessionParam("UserID");
  }
  /*
  if($HasParam)
  {
    $sWhere = " AND (" . $sWhere . ")";
  }
  */

  $sDirection = "";
  $sSortParams = "";
  
  $iSort = GetParam("FormTasks_Sorting");
  $iSorted = GetParam("FormTasks_Sorted");
  if(!$iSort)
  {
    $T->set_var("Form_Sorting", "");
    $iSort="1";
  }
  if($iSort == $iSorted)
  {
    $T->set_var("Form_Sorting", "");
    $sDirection = " DESC";
    $sSortParams = "FormTasks_Sorting=" . $iSort . "&FormTasks_Sorted=" . $iSort . "&";
  }
  else
  {
    $T->set_var("Form_Sorting", $iSort);
    $sDirection = " ASC";
    $sSortParams = "FormTasks_Sorting=" . $iSort . "&FormTasks_Sorted=" . "&";
  }
  
  if ($iSort == 1) $sOrder = " order by projects.project_title" . $sDirection;
  if ($iSort == 2) $sOrder = " order by tasks.task_title" . $sDirection;
  if ($iSort == 3) $sOrder = " order by lookup_tasks_statuses.status_desc" . $sDirection;
  if ($iSort == 4) $sOrder = " order by users.first_name" . $sDirection;
  if ($iSort == 5) $sOrder = " order by tasks.creation_date" . $sDirection;
  if ($iSort == 6) $sOrder = " order by tasks.planed_date" . $sDirection;
  if ($iSort == 7) $sOrder = " order by lookup_priorities.priority_desc" . $sDirection;
  if ($sOrder) $sOrder .=  " , tasks.task_id DESC";
  
  if ($sWhere) $sWhere=" WHERE ".$sWhere;

  $sSQL = "select tasks.creation_date as tasks_creation_date, " . 
    "tasks.planed_date as tasks_planed_date, " . 
    "tasks.priority_id as tasks_priority_id, " . 
    "tasks.project_id as tasks_project_id, " . 
    "tasks.responsible_user_id as tasks_responsible_user_id, " . 
    "tasks.task_id as tasks_task_id, " . 
    "tasks.task_status_id as tasks_task_status_id, " . 
    "tasks.task_title as tasks_task_title, " . 
    "tasks.task_type_id as tasks_task_type_id, " . 
    "projects.project_id as projects_project_id, " . 
    "projects.project_title as projects_project_title, " . 
    "lookup_tasks_statuses.status_id as lookup_tasks_statuses_status_id, " . 
    "lookup_tasks_statuses.status_desc as lookup_tasks_statuses_status_desc, " . 
    "users.user_id as users_user_id, " . 
    "users.first_name as users_first_name, " . 
    "lookup_priorities.priority_id as lookup_priorities_priority_id, " . 
    "lookup_priorities.priority_desc as lookup_priorities_priority_desc " . 
    " from tasks LEFT JOIN projects ON projects.project_id=tasks.project_id ".
    " LEFT JOIN lookup_tasks_statuses ON lookup_tasks_statuses.status_id=tasks.task_status_id ".
    " LEFT JOIN users ON users.user_id=tasks.responsible_user_id ".
    " LEFT JOIN lookup_priorities ON lookup_priorities.priority_id=tasks.priority_id ".
     $sWhere  . $sOrder;

  
  $T->set_var("FormAction", "edit_task.php");
  $T->set_var("SortParams", $sSortParams);
  $iPage = GetParam("FormTasks_Page");
  if(!strlen($iPage)) $iPage = 1;
  $RecordsPerPage = 20;
  $db->query($sSQL);
  if($db->num_rows() == 0 || ($iPage - 1)*$RecordsPerPage >= $db->num_rows())
  {
    $T->set_var("DListTasks", "");
    $T->parse("TasksNoRecords", false);
    
    $T->set_var("TasksScroller", "");
    $T->parse("FormTasks", false);
    return;
  }


  $db->seek(($iPage - 1)*$RecordsPerPage);
  $iCounter = 0;
  $old_project="";
  $statuses_classes = array("","InProgress","OnHold","Rejected","Done");

  while($db->next_record()  && $iCounter < $RecordsPerPage)
  {
    
    $fldproject_id = GetValue($db, "projects_project_title");
    if ($old_project!=$fldproject_id)
    {
      $old_project=$fldproject_id;
    }
    else
    {
      $fldproject_id="";
    }

    $fldtask_title = GetValue($db, "tasks_task_title");
    $fldtask_status_id = GetValue($db, "lookup_tasks_statuses_status_desc");
    $status_id = GetValue($db, "tasks_task_status_id");
    $T->set_var("STATUS",$statuses_classes[$status_id]);

    $fldresponsible_user_id = GetValue($db, "users_first_name");
    $fldcreation_date = GetValue($db, "tasks_creation_date");
    $fldplaned_date = GetValue($db, "tasks_planed_date");
    $fldtask_id = GetValue($db, "tasks_task_id");
    $fldpriority_id = GetValue($db, "lookup_priorities_priority_desc");

    $T->set_var("project_id", ToHTML($fldproject_id));
    $T->set_var("task_title", ToHTML($fldtask_title));
    $T->set_var("task_title_URLLink", "edit_task.php");
    $T->set_var("Prm_task_id", ToURL(GetValue($db, "tasks_task_id")));
    $T->set_var("task_status_id", ToHTML($fldtask_status_id));
    $T->set_var("responsible_user_id", ToHTML($fldresponsible_user_id));
    $T->set_var("creation_date", ToHTML(date_to_string($fldcreation_date)));
    $T->set_var("planed_date", ToHTML(date_to_string($fldplaned_date)));
    $T->set_var("task_id", ToHTML($fldtask_id));
    $T->set_var("priority_id", ToHTML($fldpriority_id));
    $T->parse("DListTasks", true);
    
    $iCounter++;
  }
  

  if($iPage*$RecordsPerPage >= $db->num_rows() && $iPage == 1)
    $T->set_var("TasksScroller", "");
  else
  {
    if($iPage*$RecordsPerPage >= $db->num_rows())
      $T->set_var("TasksScrollerNextSwitch", "_");
    else
    {
      $T->set_var("NextPage", ($iPage + 1));
      $T->set_var("TasksScrollerNextSwitch", "");
    }

    if($iPage == 1)
      $T->set_var("TasksScrollerPrevSwitch", "_");
    else
    {
      $T->set_var("PrevPage", ($iPage - 1));
      $T->set_var("TasksScrollerPrevSwitch", "");
    }
    $T->set_var("TasksCurrentPage", $iPage);
    $T->parse("TasksScroller", false);
  }
  $T->set_var("TasksNoRecords", "");
  $T->parse("FormTasks", false);
}


function search_Show()
{
  global $db;
  global $T;
  $T->set_var("ActionPage", "index.php");
  
  $fldproject_id = stripslashes(GetParam("project_id"));
  $fldtask_status_id = stripslashes(GetParam("task_status_id"));
  if (!strlen($fldtask_status_id)) $fldtask_status_id=1;

  $fldresponsible_user_id = stripslashes(GetParam("responsible_user_id"));
  $fldpriority_id = stripslashes(GetParam("priority_id"));
  $fldtask_type_id = stripslashes(GetParam("task_type_id"));
    $T->set_var("LBproject_id", "");
    $T->set_var("ID", "");
    $T->set_var("Value", "All");
    $T->parse("LBproject_id", true);
    $dbproject_id = new DB_Sql();
    $dbproject_id->Database = DATABASE_NAME;
    $dbproject_id->User     = DATABASE_USER;
    $dbproject_id->Password = DATABASE_PASSWORD;
    $dbproject_id->Host     = DATABASE_HOST;

    
    $dbproject_id->query("select project_id, project_title from projects order by 2");
    while($dbproject_id->next_record())
    {
      $T->set_var("ID", $dbproject_id->f(0));
      $T->set_var("Value", $dbproject_id->f(1));
      if($dbproject_id->f(0) == $fldproject_id)
        $T->set_var("Selected", "SELECTED" );
      else 
        $T->set_var("Selected", "");
      $T->parse("LBproject_id", true);
    }
    
    $T->set_var("LBtask_status_id", "");
    $T->set_var("ID", "0");
    $T->set_var("Value", "All");
    $T->parse("LBtask_status_id", true);
    $dbtask_status_id = new DB_Sql();
    $dbtask_status_id->Database = DATABASE_NAME;
    $dbtask_status_id->User     = DATABASE_USER;
    $dbtask_status_id->Password = DATABASE_PASSWORD;
    $dbtask_status_id->Host     = DATABASE_HOST;

    
    $dbtask_status_id->query("select status_id, status_desc from lookup_tasks_statuses order by 2");
    while($dbtask_status_id->next_record())
    {
      $T->set_var("ID", $dbtask_status_id->f(0));
      $T->set_var("Value", $dbtask_status_id->f(1));
      if($dbtask_status_id->f(0) == $fldtask_status_id)
        $T->set_var("Selected", "SELECTED" );
      else 
        $T->set_var("Selected", "");
      $T->parse("LBtask_status_id", true);
    }
    
    $T->set_var("LBresponsible_user_id", "");
    $T->set_var("ID", "");
    $T->set_var("Value", "All");
    $T->parse("LBresponsible_user_id", true);
    $dbresponsible_user_id = new DB_Sql();
    $dbresponsible_user_id->Database = DATABASE_NAME;
    $dbresponsible_user_id->User     = DATABASE_USER;
    $dbresponsible_user_id->Password = DATABASE_PASSWORD;
    $dbresponsible_user_id->Host     = DATABASE_HOST;

    
    $dbresponsible_user_id->query("select user_id, last_name from users order by 2");
    while($dbresponsible_user_id->next_record())
    {
      $T->set_var("ID", $dbresponsible_user_id->f(0));
      $T->set_var("Value", $dbresponsible_user_id->f(1));
      if($dbresponsible_user_id->f(0) == $fldresponsible_user_id)
        $T->set_var("Selected", "SELECTED" );
      else 
        $T->set_var("Selected", "");
      $T->parse("LBresponsible_user_id", true);
    }
    
    $T->set_var("LBpriority_id", "");
    $T->set_var("ID", "");
    $T->set_var("Value", "All");
    $T->parse("LBpriority_id", true);
    $dbpriority_id = new DB_Sql();
    $dbpriority_id->Database = DATABASE_NAME;
    $dbpriority_id->User     = DATABASE_USER;
    $dbpriority_id->Password = DATABASE_PASSWORD;
    $dbpriority_id->Host     = DATABASE_HOST;

    
    $dbpriority_id->query("select priority_id, priority_desc from lookup_priorities order by 2");
    while($dbpriority_id->next_record())
    {
      $T->set_var("ID", $dbpriority_id->f(0));
      $T->set_var("Value", $dbpriority_id->f(1));
      if($dbpriority_id->f(0) == $fldpriority_id)
        $T->set_var("Selected", "SELECTED" );
      else 
        $T->set_var("Selected", "");
      $T->parse("LBpriority_id", true);
    }
    
    $T->set_var("LBtask_type_id", "");
    $T->set_var("ID", "");
    $T->set_var("Value", "All");
    $T->parse("LBtask_type_id", true);
    $dbtask_type_id = new DB_Sql();
    $dbtask_type_id->Database = DATABASE_NAME;
    $dbtask_type_id->User     = DATABASE_USER;
    $dbtask_type_id->Password = DATABASE_PASSWORD;
    $dbtask_type_id->Host     = DATABASE_HOST;

    
    $dbtask_type_id->query("select type_id, type_desc from lookup_task_types order by 2");
    while($dbtask_type_id->next_record())
    {
      $T->set_var("ID", $dbtask_type_id->f(0));
      $T->set_var("Value", $dbtask_type_id->f(1));
      if($dbtask_type_id->f(0) == $fldtask_type_id)
        $T->set_var("Selected", "SELECTED" );
      else 
        $T->set_var("Selected", "");
      $T->parse("LBtask_type_id", true);
    }
    
  $T->parse("Formsearch", false);
}

?>