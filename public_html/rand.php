<?php
function make_seed()
{
    list($usec, $sec) = explode(' ', microtime());
    return (float) $sec + ((float) $usec * 100000);
}
	srand(make_seed());

	echo "<H1>Кубик 1 :    <font color='red'>" . rand(1,6) . "</font></H1>";
	
	srand(make_seed());
	echo "<H1>Кубик 2 :    <font color='red'>" . rand(1,6) . "</font></H1>";
?>