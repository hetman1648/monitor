<?php
/*
	run this file from CRON job to get error logs for the specified repository
	@param: repository
	@param: last_50_errors
*/

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");

function nl2br2($string) { 
    $replace_with = "</td></tr><tr><td>";
  $string = str_replace(array("\r\n", "\r", "\n"), $replace_with, $string); 
  return $string; 
} 


$repository     = GetParam("repository");
$last_50_errors = GetParam("last_50_errors");
if (!strlen($repository)) die ("<div class='alert alert-error'>Please specify SVN repository</div>");



$path    = "https://web1.sayu.co.uk/svn/";

//last crytical errors by default
$command = "index.php?action=shcriterr&repository=".$repository."&username=".$svn_login."&password=".$svn_password;
if ($last_50_errors) $command = "index.php?action=shlasterr&repository=".$repository."&username=".$svn_login."&password=".$svn_password;

$log = "";
$res = get_page($path. $command); 

if ($last_50_errors) {
    //echo $res;
    $ok_string = 'Server response is: +OK Last Errors';
    if (strpos($res,$ok_string) !== false) {
	$lines = explode($ok_string, $res);
	if (isset($lines[1])) $log = $lines[1];
    }    
}
else {
$ok_string = 'Server response is: +OK Fatal Errors:';
if (strpos($res,$ok_string) !== false) {
    $lines = explode($ok_string, $res);
    if (isset($lines[1])) $log = $lines[1];
} else {
    //echo $res;
    $error_ok = 'ERR No critical errors found';
    if (strpos($res,$error_ok) !== false) {
	echo "<div class='alert alert-success'>OK: no critical errors found!</div>";
    }    else {
    die("ERROR: connecting to SVN");
}}
}
?>
        <table class="table table-striped">
        	<tr><td>
<?php
//echo "command:$command<hr>";
echo nl2br2(base64_decode($log));
echo "</table>";
exit;

?>
        <table class="table table-striped">
        	<tr>
        		<th>User</th>
        		<th>Date</th>
        	</tr>
        	<?php while ($db->next_record()) { ?>
        	<tr>
        		<td><? echo $users[$db->f("user_id")]; ?></td>
        		<td><? echo $db->f("date_added"); ?></td>
        	</tr>
        	<?php $c++; } ?>
        </table>

<?php        

	if (!$c) die ("<div class='alert alert-info'>No history for <b>".$repository."</b> </div>");

?>
        