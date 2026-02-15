<?php
/*
Name:			EsendexSendService.php
Description:	Esendex SendService Web Service PHP Wrapper
Documentation: 	https://www.esendex.com/secure/messenger/soap/SendServiceNoHeader.asmx

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

class EsendexSendService
{
	var $username;
	var $password;
	var $account;

	function EsendexSendService($username, $password, $account, $secure = false)
	{
		$this->username = $username;
		$this->password = $password;
		$this->account = $account;
		
		//suppress warnings from nusoap
		error_reporting(error_reporting() & ~E_NOTICE);

		//set URI of send service WSDL
		if ($secure === true)
		{ 	
			define('SEND_SERVICE_WSDL', 'https://www.esendex.com/secure/messenger/soap/SendServiceNoHeader.asmx?wsdl');				
		}
		else
		{
			define('SEND_SERVICE_WSDL', 'http://www.esendex.com/secure/messenger/soap/SendServiceNoHeader.asmx?wsdl');
		}
	}

	function SendMessageFull($originator, $recipient, $body, $type, $validityPeriod)
	{	

		$soapclient = new nusoapclient(SEND_SERVICE_WSDL, true);

		$soap_proxy = $soapclient->getProxy();

		$parameters['username'] = $this->username;
		$parameters['password'] = $this->password;
		$parameters['account'] = $this->account;
		$parameters['originator'] = $originator;
		$parameters['recipient'] = $recipient;    
		$parameters['body'] = $body;
		$parameters['type'] = $type;
		$parameters['validityperiod'] = $validityPeriod;

		$result = $soap_proxy->SendMessageFull($parameters);    

		unset($soapclient);
		unset($soap_proxy);

		return $result;
	}

	function GetMessageStatus($messageID)
	{
		$soapclient = new nusoapclient(SEND_SERVICE_WSDL, true);

		$soap_proxy = $soapclient->getProxy();

		$parameters['username'] = $this->username;
		$parameters['password'] = $this->password;
		$parameters['account'] = $this->account;
		$parameters['id'] = $messageID;

		$result = $soap_proxy->GetMessageStatus($parameters);    

		unset($soapclient);
		unset($soap_proxy);

		return $result;

	}

	function SendMessage($recipient, $body, $type)
	{ 
		$soapclient = new nusoapclient(SEND_SERVICE_WSDL, true);

		$soap_proxy = $soapclient->getProxy();

		$parameters['username'] = $this->username;
		$parameters['password'] = $this->password;
		$parameters['account'] = $this->account;
		$parameters['recipient'] = $recipient;
		$parameters['body'] = $body;
		$parameters['type'] = $type;

		$result = $soap_proxy->SendMessage($parameters);    

		unset($soapclient);
		unset($soap_proxy);

		return $result;
	}
}	
?>
