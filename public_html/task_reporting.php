<?
  /*
    action: no 			   - show blank form going to insert
            no,but id is specified - show filled form going to update
            insert
            update
            delete
  */
  include("./includes/common.php");

  if (getsessionparam("privilege_id") == 9) {
	header("Location: index.php");
	exit;
	}

  //-- proccessing actions
  if ($action)
  {
    $err = check_validation();

    if (!$err)
    {
      $recording_date=to_mysql_date($report_year,$report_month,$report_day);
      switch ($action)
      {
        case "insert":
          $sql = "INSERT INTO task_recordings (".
		"user_id,".
		"task_id,".
		"recording_date,".
               "task_status_id,".
               "completion,".
               "report,".
               "hours_spent) VALUES (".
               ToSQL($UserID,"Number").",".
               ToSQL($task_id,"Number").",".
               "'$recording_date',".
     	       ToSQL($task_status_id,"Number").",".
               ToSQL($completion,"Number").",".
               "'$report',".
               ToSQL($hours_spent,"Number").")";

           $db->query($sql,__FILE__,__LINE__);
           
           update_task($task_id, array("completion"=>$completion));

           $sql = "SELECT * FROM tasks WHERE task_id=".$task_id;
           $db->query($sql,__FILE__,__LINE__);
           $tags=array();
           if ($db->next_record())
           {
             $tags = $db->Record;
           }

           if ($completion==100)
           {
             send_enotification(MSG_TASK_COMPLETED,$tags);
           }

          break;
        case "update":
          $sql = "UPDATE task_recordings SET ".
		"user_id=".ToSQL($UserID,"Number").",".
		"task_id=".ToSQL($task_id,"Number").",".
		"recording_date="."'$recording_date',".
               "task_status_id=".ToSQL($task_status_id,"Number").",".
               "completion=".ToSQL($completion,"Number").",".
               "report='$report',".
               "hours_spent=".  ToSQL($hours_spent,"Number").
               " WHERE recording_id=".ToSQL($recording_id,"Number");
            $db->query($sql,__FILE__,__LINE__);

          break;
        case "delete":
          $sql = "DELETE FROM task_recordings WHERE recording_id=".ToSQL($recording_id,"Number");
          $db->query($sql,__FILE__,__LINE__);
          break;
      }

      header("Location: edit_task.php?task_id=".$task_id);
    }
  }

  $T = new iTemplate("./templates",array("page"=>"task_reporting.html"));

  //-- prepare form to show
  $task_select_sql = "SELECT * ,".
	"DATE_FORMAT(tasks.planed_date,'%D %b %y') as date_to_complete ".
	"FROM tasks ".
	"LEFT JOIN projects ON tasks.project_id=projects.project_id ".
        "LEFT JOIN lookup_tasks_statuses ON lookup_tasks_statuses.status_id=tasks.task_status_id ".
  	"LEFT JOIN users ON users.user_id=tasks.responsible_user_id ".
        "LEFT JOIN lookup_task_types ON tasks.task_type_id=lookup_task_types.type_id ".
  	"LEFT JOIN lookup_priorities ON lookup_priorities.priority_id=tasks.priority_id ".
        "WHERE tasks.task_id=".$task_id;

  if ($action) //-- action: update or insert but error occur
  {
    //-- task details
    $db->query($task_select_sql,__FILE__,__LINE__);
    if ($db->next_record())
      $T->set_var($db->Record);

    //-- user entered data
    $T->set_var($HTTP_POST_VARS);
    $T->set_var("report_month", get_month_options($report_month));

    //-- depending on action hide buttons
    if ($action=="insert")
      $T->set_var("FormEdit","");
    else
      $T->set_var("FormInsert","");

  }
  else //-- first showing - going to update or insert
  {
    if ($recording_id) //-- show blank form - going to insert
    {
      //-- extract recording information
      $sql="SELECT * FROM task_recordings WHERE recording_id=$recording_id";
      $db->query($sql,__FILE__,__LINE__);
      if ($db->next_record())
      {
        $reporting_data = $db->Record;
      }

      //-- extract task information
      if($reporting_data["task_id"])
      {
        $db->query($task_select_sql.$reporting_data["task_id"],__FILE__,__LINE__);
        if ($db->next_record())
        {
          $T->set_var($db->Record);
        }
      }
      //-- setting report data
      $T->set_var($reporting_data);
      //-- report date
      $report_date = date_to_array($reporting_data["recording_date"]);
      $T->set_var(array(
        		"report_day"  	=> $report_date["DAY"],
                        "report_month" 	=> get_month_options($report_date["MONTH"]),
                        "report_year"  	=> $report_date["YEAR"],
                        "FormInsert"	=> ""
                    ));
    }
    else  //-- show filled form - going to update
    {
      if ($task_id)
      {
        //-- extract task information
        $db->query($task_select_sql,__FILE__,__LINE__);
        if ($db->next_record())
        {
          $T->set_var($db->Record);
        }
        //-- default values for the report field
        $cur_date = getdate(time());
        $T->set_var(array(
        		"report_day"  	=> $cur_date["mday"],
                        "report_month" 	=> get_month_options($cur_date["mon"]),
                        "report_year"  	=> substr($cur_date["year"],2,2),
                        "FormEdit"	=> ""
                    ));
      }
      else
      {
        header("index.php");
      }
    }
  }

  //-- show form
  if ($err)
  {
    $T->set_var("sFormErr",$err);
    $T->parse("FormError");
  }
  else
    $T->set_var("FormError","");

  //-- final parsing
  $T->set_var($T->get_undefined("page"));	//-- clear report fields
  $T->pparse("page");

//-- validations
  function check_validation()
  {
    $sRes = "";

    if(!strlen(GetParam("report_day")) || !strlen(GetParam("report_month")) || !strlen(GetParam("report_year")))
      $sRes .= "The value in field <font color=\"red\"><b>Report date</b></font> is required.<br>";

    if(!is_number(GetParam("report_day")) || !is_number(GetParam("report_month")) || !is_number(GetParam("report_year")))
      $sRes .= "The value in field <font color=\"red\"><b>Report date</b></font> is incorrect.<br>";

    if(!is_number(GetParam("completion")))
      $sRes .= "The value in field <font color=\"red\"><b>Completition</b></font> is incorrect.<br>";

    if(!is_number(GetParam("hours_spent")))
      $sRes .= "The value in field <font color=\"red\"><b>Hours spent</b></font> is incorrect.<br>";

    return $sRes;
  }
?>
