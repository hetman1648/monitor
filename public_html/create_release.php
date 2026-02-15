<?php

include ("./includes/common.php");
include("./includes/date_functions.php");

if (getsessionparam("privilege_id") == 9) {
	header("Location: index.php");
	exit;
}

global $release_id;
global $project_id;

$cur_year = date("y");
$cur_mon = date("n");

$T = new iTemplate("./templates", array("page"=>"create_release.html"));

CheckSecurity(1);

// GET "PROJECT_ID" IF ISN'T SET
if (!isset($project_id))
   {
   $sql = "SELECT * FROM project_releases WHERE release_id=".$release_id;
   $db->query($sql);
   $db->next_record();
   $project_id = $db->Record["project_id"];
   }

// RETURN BACK IF PRESSED "CANCEL" BUTTON
if ($_POST["cancelb"] == "Cancel")
   {
   header("Location: view_releases.php?project_id=".$project_id);
   }

// DO IF PRESSED "ACTION" BUTTON
if (isset($_POST["action"]))
   {

   // CHECK INPUT FAIL
   $c_fail = false;
   
   if ($_POST["release_title"] == "")
      {
      $checkfail .= "The value in field <font color=\"red\"><b>Release Title</b></font> is required.<br>";
      $c_fail = true;
      }

   // GET MOUNTH NUMBER
   foreach ($short_months as $key)
      {
      if ($_POST["month"] == $key[1])
         {
         $mo = $key[0];
         }
      }
      
   $dateset = "20".$_POST["year"]."-".$mo."-".$_POST["day"];
   
   // ADD RELEASE
   if (($_POST["action"] == "Add Release") && !$c_fail)
      {
      
      // GET MAX ID OF RELEASE
      $sql = "SELECT MAX(release_id) AS rid FROM project_releases";
      $db->query($sql);
      $db->next_record();
      $numid = $db->Record["rid"] + 1;
      
      if ( ($_POST["day"] == "") || ($_POST["day"] == "00") || ($_POST["year"] == "") )
         {
         $due_date = "0000-00-00";
         }
         
      $sql = "SELECT * FROM lookup_release_types";
      $db->query($sql);

      while ($db->next_record()) {
         if ($db->Record["type_desc"] == $_POST["type_sel"]) {
            $new_rel_id = $db->Record["type_id"];
            }
         }
      
      $sql = "INSERT INTO project_releases (release_id, title, due_date, project_id, release_type_id) VALUES ('".$numid."', '".
      $_POST["release_title"]."', '".$dateset."', '".$project_id."', '".$new_rel_id."')";
      }
      

   // EDIT RELEASE
   if (($_POST["action"] == "Edit Release") && !$c_fail)
      {
      if ( ($_POST["day"] == "") || ($_POST["day"] == "00") || ($_POST["year"] == "") )
         {
         $due_date = "0000-00-00";
         }

      $sql = "SELECT * FROM lookup_release_types";
      $db->query($sql);

      while ($db->next_record()) {
         if ($db->Record["type_desc"] == $_POST["type_sel"]) {
            $new_rel_id = $db->Record["type_id"];
         }
      }
         
      $sql = "UPDATE project_releases SET due_date='".$dateset."', title='".$_POST["release_title"].
      "', release_type_id = '".$new_rel_id."' WHERE release_id = ".$release_id;
      }
      
   if (!$c_fail)
      {
      $db->query($sql);
      header("Location: view_releases.php?project_id=".$project_id);
      }
   }
   
// VIEW FORM

// IF EDIT PARAMETERS
if (isset($release_id))
   {
   $sql = "SELECT * FROM project_releases WHERE release_id=$release_id";
   $db->query($sql);
   $db->next_record();

   $T->set_var("DAY", substr($db->Record["due_date"], 8, 2));
   $T->set_var("YEAR", substr($db->Record["due_date"], 2, 2));
   $T->set_var("release_title", $db->Record["title"]);
   $T->set_var("action", "create_release.php?release_id=".$release_id);
   $sel_mon = substr($due_date, 5, 2);
   
   $T->set_var("butvalue", "Edit Release");
   $T->set_var("butaction", "create");
   
   $sql = "SELECT * FROM project_releases WHERE release_id = ".$release_id;
   $db->query($sql);
   $c_rel_t = $db->Record["release_type_id"];

   $sql = "SELECT * FROM lookup_release_types";
   $db->query($sql);

   while ($db->next_record())
      {
      if ($c_rel_t == $db->Record["type_id"])
         {
         $opt_lis .= "<option selected>".$db->Record["type_desc"]."</option>";
         }
      else
         {
         $opt_lis .= "<option>".$db->Record["type_desc"]."</option>";
         }
      }

   $T->set_var("type_opt", $opt_lis);
   }

// IF ADD NEW PARAMETERS
else
   {
   $T->set_var("action", "create_release.php?project_id=".$project_id);
   $sel_mon = $cur_mon;
   $T->set_var("YEAR", $cur_year);
   
   $T->set_var("DAY", "");
   $T->set_var("release_title", "");
   $T->set_var("butvalue", "Add Release");
   $T->set_var("butaction", "edit");
   
   $sql = "SELECT * FROM lookup_release_types";
   $db->query($sql);

   while ($db->next_record())
      {
      if ($c_rel_t == 1)
         {
         $opt_lis .= "<option selected>".$db->Record["type_desc"]."</option>";
         }
      else
         {
         $opt_lis .= "<option>".$db->Record["type_desc"]."</option>";
         }
      }

   $T->set_var("type_opt", $opt_lis);
   }
   
// SET MOUNTH SELECT OPTIONS
for ($i = 1; $i < 13; $i++)
   {
   if ($i == $sel_mon)
      {
      $issel = "selected";
      }
   else
      {
      $issel = "";
      }

   $month_option .= "<option ".$issel.">".$short_months[$i-1][1]."</option>";
   }

$T->set_var("MONTH", $month_option);
$T->set_var("checkfail", $checkfail);
$T->parse("set_action", false);
$T->pparse("page", false);

?>
