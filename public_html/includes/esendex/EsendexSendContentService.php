<?php
/*
Name:			EsendexSendContentService.php
Description:	Esendex SendContentService Web Service PHP Wrapper
Documentation: 	https://www.esendex.com/secure/messenger/soap/SendContentServiceNoHeader.asmx

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

class EsendexSendContentService
{
	var $username;
	var $password;
	var $account;

	function EsendexSendContentService($username, $password, $account, $secure = false)
	{
		$this->username = $username;
		$this->password = $password;
		$this->account = $account;
		
		//suppress warnings from nusoap
		error_reporting(error_reporting() & ~E_NOTICE);

		//set URI of send content service WSDL
		if ($secure === true)
		{
			define('SEND_CONTENT_SERVICE_WSDL', 'https://www.esendex.com/secure/messenger/soap/SendContentServiceNoHeader.asmx?wsdl');		
		}
		else
		{	
			define('SEND_CONTENT_SERVICE_WSDL', 'http://www.esendex.com/secure/messenger/soap/SendContentServiceNoHeader.asmx?wsdl');		
		}
	}
	
	function SendWAPPushFull($originator, $recipient, $href, $text, $validityPeriod)
	{
		$soapclient = new soapclient(SEND_CONTENT_SERVICE_WSDL, true);

		$soap_proxy = $soapclient->getProxy();

		$parameters['username'] = $this->username;
		$parameters['password'] = $this->password;
		$parameters['account'] = $this->account;
		$parameters['originator'] = $originator;
		$parameters['recipient'] = $recipient;
		$parameters['href'] = $href;
		$parameters['text'] = $text;
		$parameters['validityperiod'] = $validityPeriod;
		
		$result = $soap_proxy->SendWAPPushFull($parameters);    				

		unset($soapclient);
		unset($soap_proxy);

		return $result;	
	}	
	
	function SendWAPPush($recipient, $href, $text)
	{
		$soapclient = new soapclient(SEND_CONTENT_SERVICE_WSDL, true);

		$soap_proxy = $soapclient->getProxy();

		$parameters['username'] = $this->username;
		$parameters['password'] = $this->password;
		$parameters['account'] = $this->account;
		$parameters['recipient'] = $recipient;
		$parameters['href'] = $href;
		$parameters['text'] = $text;
		
		$result = $soap_proxy->SendWAPPush($parameters);    				

		unset($soapclient);
		unset($soap_proxy);

		return $result;	
	}
}
?>