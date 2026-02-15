<?php
	include_once("./includes/constants.php");
	include_once("./includes/cvs_functions.php");
	include_once("./includes/common_functions.php");
	include_once("./includes/var_definition.php");

	$output  = get_param("output");
	$modules = VA_CVS::get_cvs_modules_list();
	
	if ($output == "json") {
		if (function_exists("json_encode")) {
			echo json_encode($modules);
		} else {
			include_once("./includes/json.php");
			$json = new Services_JSON();
			echo $json->encode($modules);
		}
		exit;
	}
?>