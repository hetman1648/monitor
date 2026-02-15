<?php
/*
Name:			EsendexAccountEventHandler.php
Description:	SOAP account event handler for PHP
Documentation: 	https://www.esendex.com/secure/messenger/soap/AccountEventHandler.asmx

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

include_once ('nusoap.php');

//Create a SOAP server
$server = new soap_server();

//Register the names of the methods to expose
$server->register('MessageReceived');
$server->register('MessageEvent');
$server->register('MessageError');

//Stub for MessageReceived event
function MessageReceived($id, $originator, $recipient, $body, $type, $sentat, $receivedat)
{	
	/*
	 *	ADD CODE FOR EVENT HERE
	 */
}

//Stub for MessageEvent event
function MessageEvent($id, $eventtype, $occuredat)
{
	/*
	 *	ADD CODE FOR EVENT HERE
	 */
}

//Stub for MessageError event
function MessageError($id, $errortype, $occuredat, $detail)
{
	/*
	 *	ADD CODE FOR EVENT HERE
	 */
}

//Process the POST data to trigger the event
$server->service($HTTP_RAW_POST_DATA);

?>