<?php
/**
 * eiseIntra main configuration file template.
 *
 * All these constants and variables can be overwritten with local application config.php data.
 *
 * @package eiseIntra
 */


/** Constant for realtive path to the eiseIntra root, to be used for JavaScript, CSS, images, etc */
define ("eiseIntraRelativePath", "/".ltrim(str_replace(
    str_replace(DIRECTORY_SEPARATOR, "/", $_SERVER["DOCUMENT_ROOT"])
    , ""
    , str_replace(DIRECTORY_SEPARATOR, "/", dirname(__FILE__)))
    ."/"
    , "/")
);

/** Absolute path to eiseIntra root, to be used in include(), require(), etc() */
define ("eiseIntraAbsolutePath", dirname(__FILE__).DIRECTORY_SEPARATOR);

/** Path to eiseIntra native JavaScript files */
define ("eiseIntraJSPath",  eiseIntraRelativePath.'js/');
/** Path to eiseIntra native CSS files */
define ("eiseIntraCSSPath",  eiseIntraRelativePath.'css/');
/** CSS theme subdirectory */
$eiseIntraCSSTheme = 'russysdev';


/** Relative path to eiseIntra 3rd party components */
define ("eiseIntraLibRelativePath",  eiseIntraRelativePath.'lib/');
/** Absolute path to eiseIntra 3rd party components */
define ("eiseIntraLibAbsolutePath",  eiseIntraAbsolutePath.'lib/');


/** jQuery path */
define ("jQueryPath", eiseIntraLibRelativePath."jquery/");
/** jQuery UI path */
define ("jQueryUIPath", eiseIntraLibRelativePath."jquery-ui/");


/** Cookie path for eiseIntra applications */
$eiseIntraCookiePath = '/'; // could be ("eiseIntraCookiePath", "/eiseAdmin/"); for eiseAdmin to allow its co-existance with other systems
/** Cookie expiration period */
$eiseIntraCookieExpire = time()+60*60*24*30; // 30 days

/** Cookie name for eiseIntra's user messages */
$eiseIntraUserMessageCookieName = 'UserMessage';


/**
 * LDAP configuration variables (not constants!)
 */
$ldap_server = "2.12.85.06";
$ldap_domain = "e-ise.com";
$ldap_dn = "DC=ru,DC=e-ise,DC=com";
$ldap_anonymous_login = "anon"."@".$ldap_domain;
$ldap_anonymous_password = "anonpass";

/**
 * Subtitle for current environment
 */
$strSubTitle = "DEVELOPMENT";
$strSubTitleLocal = "Версия для разработки";

/**
 * Localization variables (not constants!)
 */
$localLanguage = "ru"; // ISO-639-1 language code
$localCountry = "RUS"; // ISO3 country code
$localCurrency = "RUB"; // ISO local currency code

/** backward compatibility constants */
define ("commonStuffRelativePath", dirname(eiseIntraRelativePath)."/");
define ("commonStuffAbsolutePath", dirname(eiseIntraAbsolutePath).DIRECTORY_SEPARATOR);
define ("imagesRelativePath", commonStuffRelativePath."images/");
define("prgDT", "/([0-9]{1,2})[\.\-\/]([0-9]{1,2})[\.\-\/]([0-9]{4})/i");
$prgDT = prgDT;
define("prgReplaceTo","\\3-\\2-\\1");

//$eiseIntraFlagBuildLess = True;