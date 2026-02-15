<?php
/*
Name:			EsendexAccountService.php
Description:	Esendex AccountService Web Service PHP Wrapper
Documentation: 	http://www.esendex.com/secure/messenger/soap/AccountServiceNoHeader.asmx

Copyright (c) 2004 Esendex

Using NuSOAP - Web Services Toolkit for PHP
Copyright (c) 2002 NuSphere Corporation

NuSoap available from: http://sourceforge.net/projects/nusoap

If you have any questions or comments, please contact:

support@esendex.com
http://www.esendex.com/support

Esendex
http://www.esendex.com

*/

include_once('nusoap.php');

class EsendexAccountService
{	
	var $username;
	var $password;
	var $account;

	function EsendexAccountService($username, $password, $account, $secure = false)
	{
		$this->username = $username;
		$this->password = $password;
		$this->account = $account;
		
		//suppress warnings from nusoap
		error_reporting(error_reporting() & ~E_NOTICE);

		//set URI of account service WSDL
		if ($secure === true)
		{
			define('ACCOUNT_SERVICE_WSDL', 'https://www.esendex.com/secure/messenger/soap/AccountServiceNoHeader.asmx?wsdl');		
		}
		else
		{
			define('ACCOUNT_SERVICE_WSDL', 'http://www.esendex.com/secure/messenger/soap/AccountServiceNoHeader.asmx?wsdl');		
		}
	}
	
	function GetMessageLimit()
	{
		$soapclient = new soapclient(ACCOUNT_SERVICE_WSDL, true);

		$soap_proxy = $soapclient->getProxy();

		$parameters['username'] = $this->username;
		$parameters['password'] = $this->password;
		$parameters['account'] = $this->account;
		
		$result = $soap_proxy->GetMessageLimit($parameters);    				

		unset($soapclient);
		unset($soap_proxy);

		return $result;
	}
}

?>