<?php

error_reporting(7);
$stpExtendedLog = false;

define ('eiseIntraRelativePath', '/'.ltrim(str_replace(
    str_replace(DIRECTORY_SEPARATOR, '/', $_SERVER['DOCUMENT_ROOT'])
    , ''
    , str_replace(DIRECTORY_SEPARATOR, '/', dirname(__FILE__)))
    .'/'
    , '/')
);

define ('eiseIntraAbsolutePath', dirname(__FILE__).DIRECTORY_SEPARATOR);
define ('eiseIntraLibAbsolutePath', dirname(__FILE__).DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR);

define ('commonStuffRelativePath', dirname(eiseIntraRelativePath).'/');
define ('eiseIntraLibRelativePath', eiseIntraRelativePath.'lib/');

define ('eiseIntraJSPath',  eiseIntraRelativePath.'js/');
define ('eiseIntraCSSPath',  eiseIntraRelativePath.'css/');

define ('eiseIntraCSSTheme', 'default');

define ('commonStuffAbsolutePath', dirname(eiseIntraAbsolutePath).DIRECTORY_SEPARATOR);

define ('jQueryPath', eiseIntraLibRelativePath.'jquery/');
define ('jQueryUIPath', eiseIntraLibRelativePath.'jquery-ui/');
define ('BootstrapPath', eiseIntraLibRelativePath.'bootstrap/dist/');
define ('LessPHPPath', eiseIntraLibAbsolutePath.'less.php'.DIRECTORY_SEPARATOR);

define ('jQueryUITheme','redmond');
define ('imagesRelativePath', commonStuffRelativePath.'images/');

if(eiseIntraCookiePath=='')
	define ('eiseIntraCookiePath', '/');
if(eiseIntraCookieExpire=='')
	define ('eiseIntraCookieExpire', time()+60*60*24*30); // 30 days

define ('eiseIntraUserMessageCookieName', 'UserMessage');

$eiseIntraConf = array(
	'localLanguage' => 'ru'
	, 'localCountry' => 'RU'
	, 'localCurrency' => 'RUB'
	, 'strFrontEndSuffix' => '.min'
);

/*
Constants for development environment
*/
$eiseIntraConf = array_merge($eiseIntraConf, array(
	'strSubTitle' => 'DEVELOPMENT'
	, 'strSubTitleLocal' => 'Версия разработчика'
	, 'flagCollectKeys' => true
	, 'flagBuildLess' => true
	, 'strFrontEndSuffix' => ''
	));
/*
/Constants for development environment
*/

?>