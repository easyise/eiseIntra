<?php
header("Content-Type: text/html; charset=UTF-8");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
ob_start();

include "version.php";

include ("../inc_intra.php");
include ("config.php");
include 'eiseAdmin.class.php';

$authmethod = "mysql";

$intra = new eiseAdmin(array('version'=>$version, 'hideSTBLs'=>true));

$dbName = $intra->getDBName();

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

        $intra->oSQL = new eiseSQL( $_SESSION["DBHOST"], $intra->usrID, $_SESSION["DBPASS"], 'mysql' );
        $intra->oSQL->connect();

        if(!$intra->oSQL->selectDB($dbName)){
            $intra->redirect('ERROR: Database "'.$dbName.'" doesn\'t exist', 'database_form.php?dbName=mysql');
            die();
        }
        
    } catch(Exception $e) {
        
        header("Location: login.php?error=".urlencode($e->getMessage()));
        die();
        
    }
    
}
$oSQL = $intra->oSQL;

$intra->arrUsrData["FlagWrite"] = true;

$intra->requireComponent('jquery-ui');

$intra->checkLanguage();
if ($intra->local)
    @include "lang.php";
    
$arrCSS[] = 'eiseAdmin.css';
$arrJS[] = 'eiseAdmin.js';

$strLocal = $intra->local; //backward-compatibility stuff
?>