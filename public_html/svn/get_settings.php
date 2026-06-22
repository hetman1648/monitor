<?php 

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");

$done_tasks_style = "";
$weeks_to_display = "";
$show_done_tasks  = "checked";

if ((isset($_SESSION["ses_hide_done_tasks"]) && $_SESSION["ses_hide_done_tasks"])) $show_done_tasks  = "";
if (isset($_SESSION["ses_weeks_to_display"]) && $_SESSION["ses_weeks_to_display"]) $weeks_to_display = $_SESSION["ses_weeks_to_display"];
$weeks_to_display = str_replace(" weeks", "", $weeks_to_display);


if (!$weeks_to_display) $weeks_to_display = 2;

$weeks_to_display = $weeks_to_display . " weeks";
?>

<form class="form-horizontal" id="frmSettings">  
  <div class="control-group">
    <label class="control-label" for="inputWeeksToDisplay">Display calendar for next</label>
    <div class="controls">
      <input type="text" id="inputWeeksToDisplay" placeholder="2 weeks" value="<?php  echo $weeks_to_display; ?>">
    </div>
  </div>
  <div class="control-group">
    <div class="controls">
      <label class="checkbox">
        <input type="checkbox" id="chkShowDoneTasks" <?php echo $show_done_tasks;?>> Show 'Done' Tasks?
      </label>      
    </div>
  </div>
</form>
