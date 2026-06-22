<?php

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");

// mail("artem.birzul@gmail.com","Settings saved","SaveD!");
$operation  = GetParam("operation");
$weeks_to_display = GetParam("weeks_to_display");
$show_done_tasks  = GetParam("show_done_tasks");

$weeks_to_display = preg_replace("/[^0-9]/","",$weeks_to_display);


if ($operation == "save_settings" ) {
	if ($show_done_tasks == "false") {
		$_SESSION["ses_hide_done_tasks"] = true;
	} else {
		if (isset($_SESSION["ses_hide_done_tasks"])) unset($_SESSION["ses_hide_done_tasks"]);
	}
	$_SESSION["ses_weeks_to_display"] = $weeks_to_display;

}
?>
Settings have been saved: Weeks to display: <code><?php echo $weeks_to_display; ?></code>