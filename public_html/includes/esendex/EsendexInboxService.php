<?php
/*
Name:			EsendexInboxService.php
Description:	Esendex InboxService Web Service PHP Wrapper
Documentation: 	https://www.esendex.com/secure/messenger/soap/InboxServiceNoHeader.asmx

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

class EsendexInboxService
{
	var $username;
	var $password;
	var $account;

	function EsendexInboxService($username, $password, $account, $secure = false)
	{
		$this->username = $username;
		$this->password = $password;
		$this->account = $account;
		
		//suppress warnings from nusoap
		error_reporting(error_reporting() & ~E_NOTICE);

		//set URI of inbox service WSDL
		if ($secure === true)
		{
			define('INBOX_SERVICE_WSDL', 'https://www.esendex.com/secure/messenger/soap/InboxServiceNoHeader.asmx?wsdl');				
		}
		else
		{			
			define('INBOX_SERVICE_WSDL', 'http://www.esendex.com/secure/messenger/soap/InboxServiceNoHeader.asmx?wsdl');	
		}
	}
	
	function GetMessages()
	{
		$soapclient = new soapclient(INBOX_SERVICE_WSDL, true);

		$soap_proxy = $soapclient->getProxy();

		$parameters['username'] = $this->username;
		$parameters['password'] = $this->password;
		$parameters['account'] = $this->account;
		
		$result = $soap_proxy->GetMessages($parameters);    				

		unset($soapclient);
		unset($soap_proxy);

		return $result;
	}
	
	function DeleteMessage($messageID)
	{
		$soapclient = new soapclient(INBOX_SERVICE_WSDL, true);

		$soap_proxy = $soapclient->getProxy();

		$parameters['username'] = $this->username;
		$parameters['password'] = $this->password;
		$parameters['account'] = $this->account;
		$parameters['id'] = $messageID;
		
		$result = $soap_proxy->DeleteMessage($parameters);    				

		unset($soapclient);
		unset($soap_proxy);

		return $result;	
	}
}

?>
