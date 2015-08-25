<?php

error_reporting(7);
$stpExtendedLog = false;

define ("eiseIntraRelativePath", "/".ltrim(str_replace(
    str_replace(DIRECTORY_SEPARATOR, "/", $_SERVER["DOCUMENT_ROOT"])
    , ""
    , str_replace(DIRECTORY_SEPARATOR, "/", dirname(__FILE__)))
    ."/"
    , "/")
);

define ("eiseIntraAbsolutePath", dirname(__FILE__).DIRECTORY_SEPARATOR);
define ("commonStuffRelativePath", dirname(eiseIntraRelativePath)."/");

define ("eiseIntraJSPath",  eiseIntraRelativePath.'js/');
define ("eiseIntraCSSPath",  eiseIntraRelativePath.'css/');
define ("eiseIntraCSSTheme", 'bluewing');

define ("commonStuffAbsolutePath", dirname(eiseIntraAbsolutePath).DIRECTORY_SEPARATOR);

define ("jQueryPath", commonStuffRelativePath."jquery/");
define ("jQueryUIPath", jQueryPath."jquery-ui-1.11.4.custom/");

define ("jQueryUITheme","redmond");
define ("imagesRelativePath", commonStuffRelativePath."images/");

define ("eiseIntraCookiePath", "/"); // could be ("eiseIntraCookiePath", "/eiseAdmin/"); for eiseAdmin to allow its co-existance with other systems
define ("eiseIntraCookieExpire", time()+60*60*24*30); // 30 days

define ('eiseIntraUserMessageCookieName', 'UserMessage');

$ldap_server = "172.18.1.10";
$ldap_domain = "ylrus.com";
$ldap_dn = "OU=Offices,DC=ylrus,DC=com";
$ldap_anonymous_login = "svcCDBAdmin"."@".$ldap_domain;
$ldap_anonymous_password = "svcP@ssw0rd!";

define("prgDT", "/([0-9]{1,2})[\.\-\/]([0-9]{1,2})[\.\-\/]([0-9]{4})/i");
$prgDT = prgDT;
define("prgReplaceTo","\\3-\\2-\\1");

$strSubTitle = "STAGING";
$strSubTitleLocal = "Версия для отладки";

//$arrJS[] = "../common/jquery/jquery-1.2.6.js";

$localLanguage = "ru";
$localCountry = "RUS";
$localCurrency = "RUB";
?>
