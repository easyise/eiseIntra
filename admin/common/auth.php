<?php
header("Content-Type: text/html; charset=UTF-8");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
ob_start();

include "version.php";

include ("config.php");

include (eiseIntraPath."inc_intra.php");

$intra = new eiseIntra( null, array('version'=>$version, 'hideSTBLs'=>true) );
$intra->session_initialize();

if (!$flagNoAuth){

    if (!$intra->usrID){

        header("Location: login.php");
        die();
        
    }

    if (!$_SESSION["DBHOST"] && !$_SESSION["DBPASS"]){
        header("Location: login.php?error=".urlencode("Database not specified"));
        die();
    }

    try {

        $intra->oSQL = new eiseSQL($_SESSION["DBHOST"], $intra->usrID, $_SESSION["DBPASS"], (!$_SESSION["DBNAME"] ? 'mysql' : $_SESSION["DBNAME"]));
        $intra->oSQL->connect();
        
    } catch(Exception $e) {
        
        header("Location: login.php?error=".urlencode($e->getMessage()));
        die();
        
    }
    
}
$oSQL = $intra->oSQL;

$intra->arrUsrData["FlagWrite"] = true;

$intra->checkLanguage();
if ($intra->local)
    @include "lang.php";
    
$arrCSS[] = 'style.css';
$arrJS[] = 'eiseAdmin.js';

$strLocal = $intra->local; //backward-compatibility stuff
?>