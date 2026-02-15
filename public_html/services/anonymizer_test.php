<?php

	$is_admin_path = true;
	
	$root_folder_path = (isset($is_admin_path) && $is_admin_path) ? "../" : "./";
	
	include_once($root_folder_path . "includes/lib/nusoap/nusoap.php");
	
	$client = new soapclient('http://www.sayu.co.uk/services/AnonymizerService?wsdl', true); 
	//$client = new soapclient('http://localhost/sayu/services/anonymizer_service.php?wsdl', true); 
	
	$err = $client->getError(); 
	
	if (strlen($err))
	{
		var_dump($err);echo"<br>\n\r";
		var_dump(__LINE__);echo"<br>\n\r";
		return;
	}
	
	$is_active = 1;
	$country_code = array("US");
	
	$result = $client->call(
			'getAnonymizers',
			array('country_code' => $country_code)
	);
	//'is_active' => $is_active, 'country_code' => $country_code
	
	if ($client->fault)
	{
		var_dump("Error:\n\r" . $result);echo"<br>\n\r";
		var_dump(__LINE__);echo"<br>\n\r";exit;
	}
	else
	{
		$err = $client->getError();
		
		if (strlen($err))
		{
			var_dump($err);echo"<br>\n\r";
			var_dump($result);echo"<br>\n\r";
			var_dump(__LINE__);echo"<br>\n\r";
			return;
		}
		
		var_dump($result);echo"<br>\n\r";
		var_dump(__LINE__);echo"<br>\n\r";exit;
	}
	/**/
?>