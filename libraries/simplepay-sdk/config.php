<?php

$config = [
	'CURL' => true,					//use cURL or not
    'SANDBOX' => true,				//true: sandbox transaction, false: live transaction

    'IRN_BACK_URL' => $_SERVER['HTTP_HOST'] . '/irn.php',        //url of payment irn page
    'IDN_BACK_URL' => $_SERVER['HTTP_HOST'] . '/idn.php',        //url of payment idn page
    'IOS_BACK_URL' => $_SERVER['HTTP_HOST'] . '/ios.php',        //url of payment idn page

    'GET_DATA' => $_GET,
    'POST_DATA' => $_POST,
	'SERVER_DATA' => $_SERVER,

	'LOGGER' => true,
	'PROTOCOL' => 'http',

	'DEBUG_LIVEUPDATE_PAGE' => false,					//Debug message on demo LiveUpdate page (only for development purpose)
	'DEBUG_LIVEUPDATE' => false,						//LiveUpdate debug into log file
	'DEBUG_BACKREF' => false,							//BackRef debug into log file
	'DEBUG_IPN' => false,								//IPN debug into log file
	'DEBUG_IRN' => false,								//IRN debug into log file
	'DEBUG_IDN' => false,								//IDN debug into log file
	'DEBUG_IOS' => false,								//IOS debug into log file
	'DEBUG_ONECLICK' => false,							//OneClick debug into log file
];
