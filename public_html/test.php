<?php

$test = file_get_contents("http://www.acotis.co.uk/robots.txt");
var_dump($test);

$aaa = strpos($robots,"User-agent");
var_dump($aaa);
?>