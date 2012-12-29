<?php

class eiseEntity {

public $oSQL;
public $entID;
public $rwEnt = Array();
public $intra;

public $rwSta;

private $eiseListPath = "../common/eiseList";
private $eiseGridPath = "../common/eiseGrid";

function __construct ($oSQL, $intra, $entID) {
    
    $this->oSQL = $oSQL;
    $this->intra = $intra;
    
    if (!$entID)  throw new Exception ("Entity ID not set");
    
    $sqlEnt = "SELECT * FROM stbl_entity WHERE entID=".$oSQL->e($entID);
    $rsEnt = $oSQL->q($sqlEnt);
    if ($oSQL->n($rsEnt)==0){
        throw new Exception("Entity '{$entID}' not found");
    }
    
    $this->rwEnt = $oSQL->f($rsEnt);
    $this->rwEnt["entScriptPrefix"] = str_replace("tbl_", "", $this->rwEnt["entTable"]);
    $this->entID = $entID;
    
}

function getList($arrAdditionalCols = Array(), $arrExcludeCols = Array()){
    
    GLOBAL $staID;
    
    $oSQL = $this->oSQL;
    $entID = $this->entID;
    
    $this->intra->arrUsrData = $this->intra->arrUsrData;
    $rwEnt = $this->rwEnt;
    $strLocal = $this->intra->local;
    
    GLOBAL $arrJS, $arrCSS;
    $arrJS[] = "{$eiseListPath}/eiseList.js";
    $arrCSS[] = "{$eiseListPath}/eiseList.css";
    include_once("{$eiseListPath}/inc_eiseList.php");
    
    $listName = $entID;

    $staID = isset($_GET[$entID."_staID"]) ? $_GET[$entID."_staID"] : $_COOKIE[$entID."_staID"];
    if (isset($_GET[$entID."_staID"]))
        SetCookie($entID."_staID", $_GET[$entID."_staID"]);

    if ($staID!=""){
        $sqlSta = "SELECT * FROM stbl_status WHERE staID='".$staID."' AND staEntityID='$entID'";
        $rsSta = $oSQL->do_query($sqlSta);
        $rwSta = $oSQL->fetch_array($rsSta);
        $this->rwSta = $rwSta;
    }

    $lst = new eiseList($oSQL, $listName, Array('title'=>$this->rwEnt["entTitle{$strLocal}"]));

    $lst->cookieName = $listName.$staID;
    $lst->cookieExpire = time()+60*60*24*30;

    $lst->Columns[] = array('title' => ""
            , 'field' => $entID."ID"
            , 'PK' => true
            );

    $lst->Columns[] = array('title' => "##"
            , 'field' => "phpLNums"
            , 'type' => "num"
            );

    if ($staID!="" && $this->intra->arrUsrData["FlagWrite"] && !in_array("ID_to_proceed", $arrExcludeCols)){
     $lst->Columns[] = array('title' => "sel"
                 , 'field' => "ID_to_proceed"
                 , 'sql' => $entID."ID"
                 , "checkbox" => true
                 );   
    }
         
    $lst->Columns[] = array('title' => "Number"
            , 'type'=>"text"
            , 'field' => $entID."Number"
            , 'sql' => $entID."ID"
            , 'filter' => $entID."ID"
            , 'order_field' => $entID."Number"
            , 'width' => "100%"
            , 'href'=> $rwEnt["entScriptPrefix"]."_form.php?".$entID."ID=[".$entID."ID]"
            );
    if (!in_array("staTitle", $arrExcludeCols))
    $lst->Columns[] = array('title' => "Status"
            , 'type'=>"combobox"
            , 'source'=>"SELECT staID AS optValue, staTitle{$strLocal} AS optText FROM stbl_status WHERE staEntityID='$entID'"
            , 'defaultText' => "All"
            , 'field' => "staTitle{$strLocal}"
            , 'filter' => "staID"
            , 'order_field' => "staID"
            , 'width' => "100px"
            , 'nowrap' => true
            );
    if (!in_array("aclATA", $arrExcludeCols))
    $lst->Columns[] = array('title' => "ATA"
            , 'type'=>"date"
            , 'field' => "aclATA"
            , 'sql' => "IFNULL(SAC.aclATA, SAC.aclInsertDate)"
            , 'filter' => "aclATA"
            , 'order_field' => "aclATA"
            );
    if (!in_array("actTitle", $arrExcludeCols))
    $lst->Columns[] = array('title' => "Action"
            , 'type'=>"text"
            , 'field' => "actTitle{$strLocal}"
            , 'sql' => "CASE WHEN LAC.aclActionPhase=1 THEN CONCAT('Started \"', actTitle, '\"') ELSE actTitlePast END"
            , 'filter' => "actTitlePast{$strLocal}"
            , 'order_field' => "actTitlePast{$strLocal}"
            , 'nowrap' => true
            );
            
    $sqlAtr = "SELECT * 
    FROM stbl_attribute 
    ".
    ($staID!="" 
      ? "INNER JOIN stbl_status_attribute ON satStatusID='".$staID."' AND satAttributeID=atrID AND satEntityID=atrEntityID" 
      : "")
    ."
    WHERE atrEntityID='$entID' ORDER BY atrOrder ASC";
    $rsAtr = $oSQL->do_query($sqlAtr);
    $strFrom = "";
    while ($rwAtr = $oSQL->fetch_array($rsAtr)){
        
        if ($rwAtr["atrID"]==$entID."ID")
            continue;
        
        $arrAtr[] = $rwAtr;
        
        if ($staID=="" || $rwAtr["satFlagShowInList"]) {
           
           if ($rwAtr['atrFlagNoField']){
                $sqlForAtr = "SELECT atvValue FROM stbl_attribute_value WHERE atvAttributeID='".$rwAtr['atrID']."' AND atvEntityItemID=".$entID."ID ORDER BY atvEditDate DESC LIMIT 0,1";
                $arr = array('title' => ($rwAtr["atrTitle{$strLocal}"]!="" ? $rwAtr["atrTitle{$strLocal}"] : $rwAtr['atrTitle'])
                    , 'type'=>($rwAtr['atrType']!="" ? $rwAtr['atrType'] : "text")
                    , 'field' => "atr_".$rwAtr['atrID']
                    , 'sql' => $sqlForAtr
                    , 'filter' => "atr_".$rwAtr['atrID']
                    , 'order_field' => "atr_".$rwAtr['atrID']
                    );   
             
           } else {
            $arr = array('title' => ($rwAtr["atrTitle{$strLocal}"]!="" ? $rwAtr["atrTitle{$strLocal}"] : $rwAtr['atrTitle'])
                , 'type'=>($rwAtr['atrType']!="" ? $rwAtr['atrType'] : "text")
                , 'field' => $rwAtr['atrID']
                , 'filter' => $rwAtr['atrID']
                , 'order_field' => $rwAtr['atrID']
                );   
           }
           $arr['nowrap'] = true;
           
           if ($rwAtr['atrType']=="combobox" || $rwAtr['atrType']=="ajax_dropdown")
           if (!preg_match("/^Array/i", $rwAtr['atrProgrammerReserved']))
           { /*
             $strFrom .= "\r\n".GetJoinSentenceByCBSource($rwAtr['atrProgrammerReserved'], $rwAtr['atrID'], $strText, $strValue);
             $arr['source'] = $rwAtr['atrProgrammerReserved'];
             $arr['sql'] = $strText;
             $arr['nowrap'] = true;
             //*/
             $arr['source'] = $rwAtr['atrDataSource'];
             $arr['source_prefix'] = (strlen($rwAtr['atrProgrammerReserved'])==3 ? $rwAtr['atrProgrammerReserved'] : "");
             $arr['defaultText'] = $rwAtr['atrDefault'];
           } else 
             $arr['type'] = "text";
           
           $lst->Columns[] = $arr;
           
            // check column-after
            for ($ii=0;$ii<count($arrAdditionalCols);$ii++){
                if ($arrAdditionalCols[$ii]['columnAfter']==$rwAtr['atrID']){
                    $lst->Columns[] = $arrAdditionalCols[$ii];
                    
                    while(isset($arrAdditionalCols[$ii+1]) && $arrAdditionalCols[$ii+1]['columnAfter']==""){
                        $ii++;
                        $lst->Columns[] = $arrAdditionalCols[$ii];
                    }
                }
                
            }
           
        }
        

        
    }
    if (!in_array("Comments", $arrExcludeCols))        
    $lst->Columns[] = array('title' => "Comments"
        , 'type'=>"text"
        , 'field' => "Comments"
		, 'sql' => "SELECT LEFT(scmContent, 50) FROM stbl_comments WHERE scmEntityItemID={$entID}ID ORDER BY scmEditDate DESC LIMIT 0,1"
        , 'filter' => "Comments"
        , 'order_field' => "Comments"
        , 'limitOutput' => 49
        );
     
    $lst->Columns[] = array('title' => "Updated"
            , 'type'=>"date"
            , 'field' => $entID."EditDate"
            , 'filter' => $entID."EditDate"
            , 'order_field' => $entID."EditDate"
            );
    
    $sqlFrom = "{$rwEnt["entTable"]} INNER JOIN stbl_status ON {$entID}StatusID=staID AND staEntityID='{$entID}'
    LEFT OUTER JOIN stbl_action_log LAC
    INNER JOIN stbl_action ON LAC.aclActionID=actID 
    ON {$entID}ActionLogID=LAC.aclGUID
    LEFT OUTER JOIN stbl_action_log SAC ON {$entID}StatusActionLogID=SAC.aclGUID
    ".$strFrom;
    
    $lst->sqlFrom = $sqlFrom;
    $lst->sqlWhere = $sqlWhere;
    
    return $lst;
}

function newItemID($prefix, $datefmt="ym", $numlength=5){
    
    $oSQL = $this->oSQL;
    
    $sqlNumber = "INSERT INTO {$this->rwEnt["entTable"]}_number (n{$this->entID}InsertDate) VALUES (NOW())";
    $oSQL->q($sqlNumber);
    $number = $oSQL->i();
    
    $strID = "{$prefix}".date($datefmt).substr(sprintf("%0{$numlength}d", $number), -1*$numlength);
    
    return $strID;
    
}

function newItem($entItemID){
    
    $oSQL = $this->oSQL;
    
    $sqlIns = "INSERT IGNORE INTO {$this->rwEnt["entTable"]} (
        {$this->rwEnt["entID"]}ID
        , {$this->rwEnt["entID"]}StatusID
        , {$this->rwEnt["entID"]}InsertBy, {$this->rwEnt["entID"]}InsertDate, {$this->rwEnt["entID"]}EditBy, {$this->rwEnt["entID"]}EditDate
        ) VALUES (
        ".$oSQL->e($entItemID)."
        , NULL
        , '{$this->intra->usrID}', NOW(), '{$this->intra->usrID}', NOW());";
      
    $oSQL->do_query($sqlIns);
    
    
    $item = new eiseEntityItem($this->oSQL, $this->intra, $this->entID, $entItemID);
    $item->doSimpleAction(Array(  // Create
        "actID"=>"1"
    ));
    
    return $item;
    
}

function checkArchiveTable (){
    
    $oSQL = $this->oSQL;
    $intra = $this->intra;
    
    if (!isset($intra->$oSQL_arch)){
        $oSQL_arch = $intra->getArchiveSQLObject();
    } else {
        $oSQL_arch = $intra->oSQL_arch;
    }
    
    $intra_arch = new eiseIntra($oSQL_arch);
    
    // 1. check table structure in archive database by attributes
    // get archive table info
    $arrTable = Array("columns" => Array());
	if (!$oSQL_arch->d("SHOW TABLES LIKE '{$this->rwEnt["entTable"]}'")){
        $sqlCreate = "CREATE TABLE `{$this->rwEnt["entTable"]}` (
          `{$this->entID}ID` VARCHAR(50) NOT NULL
            , `{$this->entID}StatusID` INT(11) NOT NULL DEFAULT 0
            , `{$this->entID}StatusTitle` VARCHAR(256) NOT NULL DEFAULT ''
            , `{$this->entID}StatusTitleLocal` VARCHAR(256) NOT NULL DEFAULT ''
            , `{$this->entID}StatusATA` DATETIME NULL DEFAULT NULL
            , `{$this->entID}Data` LONGBLOB NULL DEFAULT NULL
            , `{$this->entID}InsertBy` varchar(50) DEFAULT NULL
            , `{$this->entID}InsertDate` datetime DEFAULT NULL
            , `{$this->entID}EditBy` varchar(50) DEFAULT NULL
            , `{$this->entID}EditDate` datetime DEFAULT NULL
            , PRIMARY KEY (`{$this->entID}ID`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8;\r\n";
        $oSQL_arch->q($sqlCreate);
    }
	$arrTable = $intra_arch->getTableInfo($intra->conf["stpArchiveDB"], $this->rwEnt["entTable"]);
	
    //make fields array for possible table update
    $arrCol = $arrTable["columns_index"];
    
    // get attributes
    $sqlATR = "SELECT * FROM stbl_attribute WHERE atrEntityID='{$this->entID}' ORDER BY atrOrder";
    $rsATR = $oSQL->do_query($sqlATR);
    
    $predField = "{$this->entID}StatusATA";
    
    while ($rwATR = $oSQL->fetch_array($rsATR)) 
        if (!in_array($rwATR["atrID"], $arrCol)){
            switch ($rwATR["atrType"]){
                case "boolean":
                    $strType = "INT";
                    break;
                case "numeric":
                    $strType = "DECIMAL";    
                    break;
                case "date":
                case "datetime":
                    $strType = $rwATR["atrType"];
                    break;
                case "textarea":
                    $strType = "LONGTEXT";
                    break;
                case "combobox":
                case "ajax_dropdown":
                    $strType = "VARCHAR(512)"; // we'll store them as text in archive
                    break;
                case "varchar":
                case "text":
                default:                    
                    $strType = "VARCHAR(512)";
                    break;
            }
            $strFields .= "\r\n".($strFields!="" ? ", " : "")."ADD COLUMN`{$rwATR["atrID"]}` {$strType} DEFAULT NULL COMMENT ".$oSQL->escape_string($rwATR["atrTitle"])." AFTER {$predField}";
            $predField = "{$rwATR["atrID"]}";
        }
    
    $sqlArchTable = "ALTER TABLE {$this->rwEnt["entTable"]}{$strFields}";
    $oSQL_arch->q($sqlArchTable);
	
	$this->oSQL_arch = $oSQL_arch;
	
}

}

?>