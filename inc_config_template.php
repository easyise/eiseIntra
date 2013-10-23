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

define ("commonStuffAbsolutePath", dirname(eiseIntraAbsolutePath).DIRECTORY_SEPARATOR);

define ("jQueryRelativePath", commonStuffRelativePath."jquery/");
define ("jQueryUIRelativePath", jQueryRelativePath."ui/");
define ("jQueryUITheme","redmond");
define ("imagesRelativePath", commonStuffRelativePath."images/");

define ("eiseIntraCookiePath", "/");
define ("eiseIntraCookieExpire", time()+60*60*24*30); // 30 days

// replace values below for LDAP authentication
$ldap_server = "2.12.85.06";
$ldap_domain = "e-ise.com";
$ldap_dn = "DC=ru,DC=e-ise,DC=com";
$ldap_anonymous_login = "anon"."@".$ldap_domain;
$ldap_anonymous_password = "anonpass";

define("prgDT", "/([0-9]{1,2})[\.\-\/]([0-9]{1,2})[\.\-\/]([0-9]{4})/i");
$prgDT = prgDT;
define("prgReplaceTo","\\3-\\2-\\1");

$strSubTitle = "DEVELOPMENT";
$strSubTitleLocal = "Версия для разработки";

$arrJS[] = jQueryRelativePath."jquery-1.6.1.min.js";

// update these values according your localizaion
$localLanguage = "ru";
$localCountry = "RUS";
$localCurrency = "RUB";
?>