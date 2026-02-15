<?php

	include ("./includes/common.php");

	if (getsessionparam("privilege_id") == 9) {
		header("Location: index.php");
		exit;
	}

	$sFileName = "view_project.php";
	$sTemplateFileName = "view_project.html";



	$T= new iTemplate($sAppPath,array("main"=>$sTemplateFileName));


	$T->set_var("FileName", $sFileName);


	$sFormErr = "";

	$sAction = GetParam("FormAction");
	$sForm = GetParam("FormName");
	switch ($sForm) {
	  case "Form":
	    FormAction($sAction);
	  break;
	}Form_Show();

	$T->parse("main", false);
	echo $T->p("main");


//********************************************************************************



function FormAction($sAction)
{
  global $db;
  global $T;
  global $sAction;
  global $sForm;
  global $sFormErr;
  $sParams = "";
  $sActionFileName = "index.php";


  header("Location: " . $sActionFileName);
}

function Form_Show()
{
  global $db;
  global $T;
  global $sAction;
  global $sForm;
  global $sFormErr;


  $sWhere = "";

  $bPK = true;
	$fldproject_id = "";
	$fldparent_project_id = "";
	$fldproject_title = "";
	$fldproject_desc = "";
	$fldproject_status_id = "";
	$fldresponsible_user_id = "";
	$fldcreation_date = "";


  if ($sAction != "" && $sForm == "Form")
  {
    $fldproject_id = stripslashes(GetParam("project_id"));
    $pproject_id = GetParam("PK_project_id");
  }
  else
  {
    $pproject_id = GetParam("project_id");
  }


  if(strlen($pproject_id))
  {
    $sWhere .= "project_id=" . ToSQL($pproject_id, "Number");
    $T->set_var("PK_project_id", $pproject_id);
  }
  else
    $bPK = false;


  $sSQL = "select * from projects where " . $sWhere;

  if($bPK && $sAction != "insert")
  {
    $db->query($sSQL,__FILE__,__LINE__);
    $db->next_record();
    if($sAction == "")
    {

      $fldproject_id = GetValue($db, "project_id");
      $fldparent_project_id = GetValue($db, "parent_project_id");
      $fldproject_title = GetValue($db, "project_title");
      $fldproject_desc = GetValue($db, "project_desc");
      $fldproject_status_id = GetValue($db, "project_status_id");
      $fldresponsible_user_id = GetValue($db, "responsible_user_id");
      $fldcreation_date = GetValue($db, "creation_date");
    }
    else
    {
      $fldparent_project_id = GetValue($db, "parent_project_id");
      $fldproject_title = GetValue($db, "project_title");
      $fldproject_desc = GetValue($db, "project_desc");
      $fldproject_status_id = GetValue($db, "project_status_id");
      $fldresponsible_user_id = GetValue($db, "responsible_user_id");
      $fldcreation_date = GetValue($db, "creation_date");
    }
    $T->set_var("FormDelete", "");

    $T->set_var("FormUpdate", "");

    $T->set_var("FormInsert", "");
    $T->parse("FormEdit", false);

    $T->parse("FormCancel", false);
  }
  else
  {



    $T->set_var("FormEdit", "");

    $T->set_var("FormInsert", "");
    $T->parse("FormCancel", false);
  }

  $fldproject_status_id = DLookUp("projects_statuses", "status_desc", "project_status_id=" . ToSQL($fldproject_status_id, "Number"));
  $fldresponsible_user_id = DLookUp("users", "last_name", "user_id=" . ToSQL($fldresponsible_user_id, "Number"));

  $fldproject_desc=preg_replace("/\n/","<br>",$fldproject_desc);

    $T->set_var("project_id", ToHTML($fldproject_id));
    $T->set_var("parent_project_id", ToHTML($fldparent_project_id));
    $T->set_var("project_title", ToHTML($fldproject_title));
    $T->set_var("project_desc", $fldproject_desc);
    $T->set_var("project_status_id", ToHTML($fldproject_status_id));
    $T->set_var("responsible_user_id", ToHTML($fldresponsible_user_id));
    $T->set_var("creation_date", ToHTML($fldcreation_date));
  if($sFormErr == "")
    $T->set_var("FormError", "");
  else
  {
    $T->set_var("sFormErr", $sFormErr);
    $T->parse("FormError", false);
  }
  $T->parse("FormForm", false);

}

?>