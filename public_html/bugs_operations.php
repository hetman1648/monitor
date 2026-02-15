<?php
include("./includes/date_functions.php");
include("./includes/common.php");

header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");  // disable IE caching
header("Last-Modified: " . gmdate( "D, d M Y H:i:s") . " GMT"); 
header("Cache-Control: no-cache, must-revalidate"); 
header("Pragma: no-cache");

$operation = GetParam("operation");
$user_id = GetParam("user_id");
$bug_id = GetParam("bug_id");

if ($operation == 'close' || $operation == 'ch_decline')
{
	$db->query('SELECT is_declined FROM bugs WHERE bug_id='.$bug_id);
	$db->next_record();
	$is = $db->Record['is_declined'];
	$sql =  'UPDATE bugs SET is_declined = '.(($is == '1') ? '0' : '1').', resolved_user_id= '.$user_id.', date_resolved = DATE(NOW()) WHERE bug_id = '.$bug_id;
}


if ($operation == 'ch_resolve')
{   $db->query('SELECT is_resolved FROM bugs WHERE bug_id='.$bug_id);
	$db->next_record();
	$is = $db->Record['is_resolved'];
	$sql =  'UPDATE bugs SET is_resolved = '.(($is == '1') ? '0' : '1').', resolved_user_id= '.$user_id.', date_resolved = DATE(NOW()) WHERE bug_id = '.$bug_id;
}

if ($operation == 'delete')
{
	$sql =  'DELETE FROM bugs WHERE bug_id = '.$bug_id;
}

$db->query($sql);  

$db->next_record();
echo 'ok';
?>