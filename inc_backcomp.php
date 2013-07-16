<?php
$arrUsrData = $intra->arrUsrData;
$usrID = $intra->usrID;
$strLocal = $intra->local;
$arrSetup = $intra->conf;
function getTranslation($arg){GLOBAL $intra; return $intra->translate($arg);}
function ShowTextBox($strName, $strValue, $strParams=""){
    GLOBAL $intra; 
    return $intra->showTextBox($strName, $strValue
    , ($strParams!="" ? Array("strAttrib"=>$strParams) : Array()));
}
function ShowTextArea($strName, $strValue, $strParams=""){
    GLOBAL $intra; 
    return $intra->showTextArea($strName, $strValue
    , ($strParams!="" ? Array("strAttrib"=>$strParams) : Array()));
}
function ShowCheckBox($strName, $strValue, $strParams=""){
    GLOBAL $intra; 
    return $intra->showCheckBox($strName, $strValue
    , ($strParams!="" ? Array("strAttrib"=>$strParams) : Array()));
}
function ShowCombo($strName, $strValue, $arrOptions, $strParams="", $strZeroOptnText=""){
    GLOBAL $intra;
    return $intra->showCombo($strName, $strValue, $arrOptions, 
        ($strParams!="" || $strZeroOptnText!="" ? Array("strAttrib"=>$strParams, "strZeroOptnText"=>$strZeroOptnText) : Array()));
}
function ShowAjaxDropdown($oSQL, $strFieldName, $strValue, $strText, $strTable, $strPrefix, $addParams="") {
    GLOBAL $intra;
    $arrOptions = Array("strText"=>$strText, "strTable"=>$strTable, "strPrefix"=>$strPrefix, "strAttrib"=>$addParams);
    return $intra->showAjaxDropdown($strFieldName, $strValue, $arrOptions);
}
function ShowSQLCombo($oSQL, $strName, $strValue, $sqlOptions, $strParams="", $strZeroOptnText=""){
    GLOBAL $intra;
    $rsOptions = $oSQL->q($sqlOptions);
    $arrOptions = Array();$arrIndent=Array();
    while($rw = $oSQL->f($rsOptions)){
        $arrOptions[$rw["optValue"]] = $rw["optText"];
        $arrIndent[$rw["optValue"]] = (int)$rw["optLevelInside"];
    }
    return $intra->showCombo($strName, $strValue, $arrOptions, 
        ($strParams!="" || $strZeroOptnText!="" ? Array("indent" => $arrIndent, "strAttrib"=>$strParams, "strZeroOptnText"=>$strZeroOptnText) : Array()));
}


function DateSQL2PHP($arg){GLOBAL $intra; return $intra->dateSQL2PHP($arg);}
function DatePHP2SQL($arg){GLOBAL $intra; return $intra->datePHP2SQL($arg);}
function ShowFieldTitle($arg){GLOBAL $intra; return $intra->translate($arg);}
function GetFieldTitle($arg){GLOBAL $intra; return $intra->translate($arg);}
function loadJS(){GLOBAL $intra; return $intra->loadJS();}
function getDataFromCommonViews($oSQL, $val, $text, $table, $prefix){GLOBAL $intra; return $intra->getDataFromCommonViews($val, $text, $table, $prefix);}
function GetRoleUsers($oSQL, $rolID){GLOBAL $intra; return $intra->getRoleUsers($rolID);}
?>