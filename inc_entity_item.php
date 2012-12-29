<?php
include_once "inc_entity.php";

class eiseEntityItem extends eiseEntity {

public $oSQL;
public $entID;
public $entItemID;
public $rwEnt = Array();

public $arrTimestamp = Array('ETD', 'ETA', 'ATD', 'ATA');
public $arrActionPhase = Array('0' => "Planned"
, '1' => "Started"
, '2' => "Complete"
, '3' => "Cancelled");

private $arrAction = Array(); // current action data
private $arrACL = Array(); // incomplete action data
private $arrNewData = Array(); // new data, it could be $_POST

function __construct($oSQL, $intra, $entID, $entItemID, $flagArchive = false){
    
    parent::__construct($oSQL, $intra, $entID);
    
    $this->entItemID = $entItemID;
    
    $this->flagArchive = $flagArchive;
    
    if (!$entID)  throw new Exception ("Entity ID not set");
    
    if (!$flagArchive){
        $this->getEntityItemData();
    } else  {
        $this->getEntityItemDataFromArchive();
    }
    
}

function getEntityItemData(){
    
    $oSQL = $this->oSQL;
    $entID = $this->entID;
    $entItemID = $this->entItemID;
    
    $sqlEnt = "SELECT * 
            FROM {$this->rwEnt['entTable']}
            LEFT OUTER JOIN stbl_status ON staID={$entID}StatusID AND staEntityID='$entID'
            LEFT OUTER JOIN stbl_action_log ON {$entID}ActionLogID=aclGUID
            WHERE {$entID}ID='{$entItemID}'";
    $rsEnt = $oSQL->q($sqlEnt);
    if ($oSQL->n($rsEnt)==0)
        throw new Exception("Entity Item not found".": ".$entID."/".$entItemID);
    
    $rwEnt = $oSQL->fetch_array($rsEnt);
    
    $this->rwEnt = array_merge($rwEnt, $this->rwEnt);
    
}

function getEntityItemDataFromArchive(){
    
    $oSQL = $this->oSQL;
    $entID = $this->entID;
    $entItemID = $this->entItemID;
    
    $oSQL_arch = $this->intra->getArchiveSQLObject();
    
    $sqlEnt = "SELECT * 
            FROM {$this->rwEnt['entTable']}
            WHERE {$entID}ID='{$entItemID}'";
    $rsEnt = $oSQL_arch->q($sqlEnt);
    if ($oSQL_arch->n($rsEnt)==0)
        throw new Exception("Entity Item not found in the Archive database {$oSQL_arch->dbname}".": ".$entID."/".$entItemID);
    
    $rwEnt = $oSQL_arch->fetch_array($rsEnt);
    
    $this->rwEnt = array_merge($rwEnt, $this->rwEnt);
    
}

function delete(){
    
    $oSQL = $this->oSQL;
    
    if ($this->flagArchive){
        $oSQL_arch = $this->intra->getArchiveSQLObject();
        $oSQL_arch->q("DELETE FROM {$this->rwEnt["entTable"]} WHERE {$this->entID}ID=".$oSQL->e($this->entItemID));
        return;
    }
    
    
    $rwEnt = $this->rwEnt;
    $entItemID = $this->entItemID;
    $entID = $this->entID;
    
    $sqlDel[] = "DELETE {$rwEnt["entTable"]}_log, stbl_action_log FROM {$rwEnt["entTable"]}_log INNER JOIN stbl_action_log
        ON aclGUID=l{$entID}GUID WHERE aclEntityItemID='{$entItemID}'";
    $sqlDel[] = "DELETE {$rwEnt["entTable"]}_log, stbl_status_log FROM {$rwEnt["entTable"]}_log INNER JOIN stbl_status_log
        ON stlGUID=l{$entID}GUID WHERE stlEntityItemID='{$entItemID}'";
    //$sqlDel[] = "DELETE FROM stbl_action_log WHERE aclEntityItemID='{$entItemID}'";
    //$sqlDel[] = "DELETE FROM {$rwEnt["entTable"]}_log WHERE l{$entID}GUID IN (SELECT stlGUID FROM stbl_status_log WHERE stlEntityItemID='{$entItemID}')";
    //$sqlDel[] = "DELETE FROM stbl_status_log WHERE stlEntityItemID='{$entItemID}'";
    // comments
    $sqlDel[] = "DELETE FROM stbl_comments WHERE scmEntityItemID=".$oSQL->e($this->entItemID);
    // files
    $sqlDel[] = "DELETE FROM stbl_file WHERE filEntityItemID=".$oSQL->e($this->entItemID);
    // master
    $sqlDel[] = "DELETE FROM {$rwEnt["entTable"]} WHERE {$entID}ID='{$entItemID}'";
    
    for ($i=0;$i<count($sqlDel);$i++)
        $oSQL->do_query($sqlDel[$i]);
    
}

public function doSimpleAction($arrNewData){

    $this->arrNewData = $arrNewData;
    $this->collectActionData();
    $this->addAction();
    $this->finishAction();
    
}


public function doFullAction($arrNewData = Array()){
    
    if (count($arrNewData)>0){
        $this->updateMasterTable($arrNewData);
        $this->updateActionLog();
    }
    
    if (count($this->arrAction)==0){
        $this->collectActionData();
    }
    
    // proceed the action
    if ($this->arrAction["actFlagAutocomplete"]){
        
        $this->checkMandatoryFields();
        $this->addAction();
        $this->checkTimeLine();
        $this->finishAction();
        
    } else {
        if ($this->arrAction["aclGUID"]==""){
            
            $this->addAction();
            
        } else {
            switch ($this->arrNewData["aclToDo"]) {
                case "finish":
                    $this->checkMandatoryFields();
                    $this->checkTimeLine();
                    $this->finishAction();
                    break;
                case "start":
                    $this->startAction();
                    break;
                case "cancel":
                    $this->cancelAction();
                    break;
            }
        }
        
    }

}


public function addAction(){
    
    $usrID = $this->intra->usrID;
    $oSQL = $this->oSQL;
    
    // 1. obtaining aclGUID
    $this->arrAction["aclGUID"] = $oSQL->get_data($oSQL->do_query("SELECT UUID()"));
     
    // 2. insert new ACL
    $sqlInsACL = "INSERT INTO `stbl_action_log`
               (
              `aclGUID`
              , aclPredID
              , `aclActionID`
              , `aclEntityItemID`
              , aclOldStatusID
              , aclNewStatusID
                , aclActionPhase
                , aclETD
                , aclETA
                , aclATD
                , aclATA
              , `aclComments`
              , `aclInsertBy`, `aclInsertDate`, `aclEditBy`, `aclEditDate`
              ) SELECT
              '{$this->arrAction["aclGUID"]}' as aclGUID
              , ".($this->rwEnt["{$this->entID}StatusActionLogID"]!="" ? "'".$this->rwEnt["{$this->entID}StatusActionLogID"]."'" : "NULL")." as aclPredID
              , ".(int)$this->arrAction["actID"]."
              , '".$this->entItemID."'
              , ".((string)$this->arrAction['aclOldStatusID']=="" ? "NULL"  : "'".(string)$this->arrAction['aclOldStatusID']."'")."
              , ".((string)$this->arrAction['aclNewStatusID']=="" ? "NULL"  : "'".(string)$this->arrAction['aclNewStatusID']."'")."
                , '0'
                , ".(isset($this->arrAction["aclETD_attr"]) ? $this->arrAction["aclETD_attr"] : "NULL")."
                , ".(isset($this->arrAction["aclETA_attr"]) ? $this->arrAction["aclETA_attr"] : "NULL")."
                , ".(isset($this->arrAction["aclATD_attr"]) ? $this->arrAction["aclATD_attr"] : "NULL")."
                , ".(isset($this->arrAction["aclATA_attr"]) ? $this->arrAction["aclATA_attr"] : "NULL")."
                , ".$oSQL->escape_string($this->arrAction["aclComments"])."
              , '{$this->intra->usrID}', NOW(), '{$this->intra->usrID}', NOW()
            FROM {$this->rwEnt["entTable"]} WHERE {$this->entID}ID='{$this->entItemID}'";
     
    $oSQL->do_query($sqlInsACL);  
    
    // 3. insert ATV
	// generate script that copy data from the master table
	$arrFields = Array();
    foreach($this->arrAction["AAT"] as $atrID => $rwAAT){
        
        // define attributes for timestamp
        if ($rwAAT["aatFlagTimestamp"]) {
            $this->arrAction["acl".$rwAAT["aatFlagTimestamp"]."_attr"] = ($rwAAT["aatFlagEmptyOnInsert"] ? "NULL" : $atrID);
        }
        
		$arrFields["l".$atrID] = ($rwAAT["aatFlagEmptyOnInsert"] ? "NULL" : $atrID);
		
    }
	
    
    if (count($arrFields)!=0){
    
        $sqlInsATV = "INSERT INTO {$this->rwEnt["entTable"]}_log (
                l{$this->entID}GUID
                ";
            
        foreach ($arrFields as $field => $value) 
            $sqlInsATV .= ", ".$field;
            
            
        $sqlInsATV .= ", l{$this->entID}InsertBy, l{$this->entID}InsertDate, l{$this->entID}EditBy, l{$this->entID}EditDate
                ) SELECT
                '{$this->arrAction["aclGUID"]}'AS atvGUID
               ";
            
        foreach ($arrFields as $field => $value) 
            $sqlInsATV .= ", ".$value;
            
        $sqlInsATV .= "       
                , '{$usrID}' AS atvInsertBy, NOW() AS atvInsertDate, '{$usrID}' AS atvEditBy, NOW() AS atvEditDate
            FROM {$this->rwEnt["entTable"]} WHERE {$this->entID}ID='{$this->entItemID}'
            ";
            
        $oSQL->do_query($sqlInsATV);
    }
    
}

function finishAction(){
    $usrID = $this->intra->usrID;
    $oSQL = $this->oSQL;
       
    if ($this->arrAction["aclActionPhase"]=="0")
        if (!$this->checkCanStart()){
            throw new Exception("Action '{$this->arrAction["actTitle"]}' cannot be started for {$this->entItemID} because of its status ({$this->arrAction["rwEnt"][$this->entID."StatusID"]})");
            return false;
        }
    
    // update started action as completed
    $sqlUpdACL = "UPDATE stbl_action_log SET
        aclActionPhase = 2
        , aclATA= IFNULL(aclATA, NOW())
        , aclStartBy=IFNULL(aclStartBy, '{$this->intra->usrID}'), aclStartDate=IFNULL(aclStartDate,NOW())
        , aclFinishBy=IFNULL(aclFinishBy, '{$this->intra->usrID}'), aclFinishDate=IFNULL(aclFinishDate, NOW())
        , aclEditDate=NOW(), aclEditBy='{$this->intra->usrID}'
        WHERE aclGUID='".$this->arrAction["aclGUID"]."'";
              
    $oSQL->do_query($sqlUpdACL);
    
    if ($this->arrAction["actID"]!="2") {
        $sqlUpdEntTable = "UPDATE {$this->rwEnt["entTable"]} SET
            {$this->entID}ActionLogID='{$this->arrAction["aclGUID"]}'
            , {$this->entID}EditBy='{$this->intra->usrID}', {$this->entID}EditDate=NOW()
            WHERE {$this->entID}ID='{$this->entItemID}'";
        $oSQL->do_query($sqlUpdEntTable);
    }
    
    // update master table by attrbutes, if actFlagAutocomplete is not set
    if (!$this->arrAction["actFlagAutocomplete"]){
        
        if (count($this->arrAction["AAT"])>0){
            $sqlUpdMaster = "UPDATE {$this->rwEnt["entTable"]} SET 
                {$this->entID}EditBy='{$this->intra->usrID}', {$this->entID}EditDate=NOW()";
            foreach($this->arrAction["AAT"] as $atrID=>$xx){
                $sqlUpdMaster .= "\r\n, {$atrID} = (SELECT l{$atrID} FROM {$this->rwEnt["entTable"]}_log WHERE l{$this->entID}GUID='{$this->arrAction["aclGUID"]}')";
            }
            $sqlUpdMaster .= "\r\nWHERE {$this->entID}ID='{$this->entItemID}'";
            $oSQL->do_query($sqlUpdMaster);
        }
    }
    
    //echo "<pre>";
    //print_r($this->arrNewData);
    //print_r($this->arrAction);
    
    // if status is changed or action requires status stay interruption, we insert status log entry and update master table
    if (((string)$this->arrAction["aclOldStatusID"]!=(string)$this->arrAction["aclNewStatusID"] 
          && (string)$this->arrAction["aclNewStatusID"]!=""
        )
        || $this->arrAction["actFlagInterruptStatusStay"]){
        
        $sql = Array();
        $stlGUID = $oSQL->get_data($oSQL->do_query("SELECT UUID()"));
        
        $sql[] = "UPDATE stbl_status_log SET
        stlATD=(SELECT IFNULL(aclATD, aclATA) FROM stbl_action_log WHERE aclGUID='{$this->arrAction["aclGUID"]}')
        , stlDepartureActionID='{$this->arrAction["aclGUID"]}'
        , stlEditBy='{$this->intra->usrID}', stlEditDate=NOW()
        WHERE stlEntityItemID='{$this->entItemID}' AND stlATD IS NULL";
        
        $sql[] = "INSERT INTO stbl_status_log (
            stlGUID
            , stlEntityID
            , stlEntityItemID
            , stlStatusID
            , stlArrivalActionID
            , stlDepartureActionID
            , stlTitle
            , stlTitleLocal
            , stlATA
            , stlATD
            , stlInsertBy, stlInsertDate, stlEditBy, stlEditDate
            ) SELECT
            '$stlGUID' as stlGUID
            , '{$this->entID}' as stlEntityID
            , aclEntityItemID
            , aclNewStatusID as stlStatusID
            , aclGUID as stlArrivalActionID
            , NULL as stlDepartureActionID
            , staTitle as stlTitle
            , staTitleLocal as stlTitleLocal
            , aclATA as stlATA
            , NULL as stlATD
            , '{$this->intra->usrID}' as stlInsertBy, NOW() as stlInsertDate, '{$this->intra->usrID}' as stlEditBy, NOW() as stlEditDate
            FROM stbl_action_log 
            INNER JOIN stbl_action ON actID=aclActionID
            INNER JOIN stbl_status ON aclNewStatusID=staID AND staEntityID='{$this->entID}'
            WHERE aclGUID='{$this->arrAction["aclGUID"]}'";
        
        $sqlSAT = "SELECT * FROM stbl_status_attribute 
            WHERE satStatusID='{$this->arrAction["aclNewStatusID"]}' AND satEntityID='{$this->entID}' AND satFlagTrackOnArrival=1";
        $rsSAT = $oSQL->do_query($sqlSAT);
        
        $arrSAT = Array();
        while ($rwSAT = $oSQL->fetch_array($rsSAT)){
            $arrSAT[] = $rwSAT['satAttributeID'];
        }
        
        if (count($arrSAT)>0){
            $sqlSAT = "INSERT INTO {$this->rwEnt["entTable"]}_log (
                l{$this->entID}GUID
                , l{$this->entID}EditBy , l{$this->entID}EditDate, l{$this->entID}InsertBy, l{$this->entID}InsertDate
                ";
            foreach($arrSAT as $ix => $atrID)
                $sqlSAT .= ", l{$atrID}";
            $sqlSAT .= ") SELECT 
                '$stlGUID' as l{$this->entID}GUID
                , '{$this->intra->usrID}' as l{$this->entID}EditBy , NOW() as l{$this->entID}EditDate, '{$this->intra->usrID}' as {$this->entID}InsertBy, NOW() as {$this->entID}InsertDate
                ";
            foreach($arrSAT as $ix => $atrID)
                $sqlSAT .= ", {$atrID}";
            $sqlSAT .= " FROM {$this->rwEnt["entTable"]} WHERE {$this->entID}ID='{$this->entItemID}'";
            $sql[] = $sqlSAT;
        }
        
        // after action is done, we update entity table with last status action log id
        $sql[] = "UPDATE {$this->rwEnt["entTable"]} SET
            {$this->entID}ActionLogID='{$this->arrAction["aclGUID"]}'
            , {$this->entID}StatusActionLogID='{$this->arrAction["aclGUID"]}'
            , {$this->entID}StatusID='{$this->arrAction["aclNewStatusID"]}'
            , {$this->entID}EditBy='{$this->intra->usrID}', {$this->entID}EditDate=NOW()
            WHERE {$this->entID}ID='{$this->entItemID}'";
        
        for($i=0;$i<count($sql);$i++){
            $oSQL->do_query($sql[$i]);
        }
        
    }
}

private function collectActionData(){
    
    $oSQL = $this->oSQL;
    $this->arrAction = Array();
    $this->arrACL = Array();
    
    // collect all incomplete actions
    $sqlACL = "SELECT * FROM stbl_action_log 
        INNER JOIN stbl_action ON aclActionID=actID
        WHERE aclEntityItemID='{$this->entItemID}' 
		AND aclActionPhase<2 
		ORDER BY aclInsertDate DESC";
    $rsACL = $oSQL->do_query($sqlACL);
    
    while ($rwACL = $oSQL->fetch_array($rsACL)){
        $this->arrACL[$rwACL["aclGUID"]] = $rwACL;
        
        $this->arrACL[$rwACL["aclGUID"]]["AAT"] = $this->getActionAttribute($rwACL["aclActionID"]);
        
        //getting ATV
        $sqlATV = "SELECT * FROM {$this->rwEnt["entTable"]}_log WHERE l{$this->entID}GUID='{$rwACL["aclGUID"]}'";
        $rsATV = $oSQL->do_query($sqlATV);
        $rwATV = $oSQL->fetch_array($rsATV);
        foreach($this->arrACL[$rwACL["aclGUID"]]["AAT"] as $atrID => $arr_dummy){
            $this->arrACL[$rwACL["aclGUID"]]["ATV"][$atrID] = $rwATV["l{$atrID}"];
        }
        
        $oSQL->free_result($rsATV);
        
    }
    
    //collect coming action
    if ($this->arrNewData["aclGUID"]){
        $sqlACT = "SELECT * FROM stbl_action_log 
            INNER JOIN stbl_action ON aclActionID=actID
            WHERE aclGUID='{$this->arrNewData["aclGUID"]}'";
    } else {
        $sqlACT = "SELECT *,
            (SELECT atsNewStatusID FROM stbl_action_status WHERE atsActionID=actID 
                ORDER BY atsNewStatusID LIMIT 0,1) as aclNewStatusID
            , ".($this->rwEnt["{$this->entID}StatusID"]=="" ? "NULL" : (int)$this->rwEnt["{$this->entID}StatusID"])." as aclOldStatusID
            FROM stbl_action 
            WHERE actID='{$this->arrNewData["actID"]}'";
    }
    $rsACT = $oSQL->do_query($sqlACT);
    $rwACT = $oSQL->fetch_array($rsACT);
    
    $this->arrAction["AAT"] = $this->getActionAttribute($rwACT["actID"]);
    
    $this->arrAction["aclOldStatusID"] = isset($this->arrNewData["aclOldStatusID"]) ? $this->arrNewData["aclOldStatusID"] : $this->rwEnt["{$this->entID}StatusID"];
    $this->arrAction["aclNewStatusID"] = isset($this->arrNewData["aclNewStatusID"]) ? $this->arrNewData["aclNewStatusID"] : $rwACT["aclNewStatusID"];
    $this->arrAction["aclComments"] = $this->arrNewData["aclComments"];
    
    $this->arrAction = array_merge($this->arrAction, $rwACT);
    
    foreach($this->arrAction["AAT"] as $atrID => $rwAAT){
        // define attributes for timestamp
        if ($rwAAT["aatFlagTimestamp"]) {
            $this->arrAction["acl".$rwAAT["aatFlagTimestamp"]."_attr"] = ($rwAAT["aatFlagEmptyOnInsert"] ? "NULL" : $atrID);
        }
    }
    
}


private function getActionAttribute($actID){
    
    $oSQL = $this->oSQL;
    
    $arrToRet = Array();
    
    //getting AAT
    $sqlAAT = "SELECT 
    atrID
	, aatAttributeID
    , atrTitle
    , atrTitleLocal
    , atrDataSource
	, atrDefault
    , aatFlagMandatory
    , aatFlagToChange
    , aatFlagEmptyOnInsert
    , aatFlagTimestamp
    , atrType
    FROM stbl_action_attribute 
    INNER JOIN stbl_action ON aatActionID=actID
    INNER JOIN stbl_attribute ON atrEntityID=actEntityID AND aatAttributeID=atrID
    WHERE aatActionID='{$actID}'";
    $rsAAT = $oSQL->do_query($sqlAAT);
    while ($rwAAT = $oSQL->fetch_array($rsAAT)){
        $arrToRet[$rwAAT["aatAttributeID"]]=$rwAAT;
    }
    $oSQL->free_result($rsAAT);
    
    return $arrToRet;
}

private function getStatusAttribute($staID){
    
    $oSQL = $this->oSQL;
    
    $arrToRet = Array();
    
    //getting AAT
    $sqlSAT = "SELECT 
    atrID
	, satAttributeID
    , atrTitle
    , atrTitleLocal
	, atrDataSource
	, atrDefault
	, satAttributeID
	, satFlagEditable
	, satFlagShowInForm
	, satFlagShowInList
	, satFlagTrackOnArrival
    , atrType
    FROM stbl_status_attribute 
    INNER JOIN stbl_status ON satStatusID=staID AND staEntityID=satEntityID
    INNER JOIN stbl_attribute ON atrEntityID=satEntityID AND satAttributeID=atrID
    WHERE satStatusID='{$staID}' AND satEntityID='{$this->entID}'";
    $rsSAT = $oSQL->do_query($sqlSAT);
    while ($rwSAT = $oSQL->fetch_array($rsSAT)){
        $arrToRet[$rwSAT["satAttributeID"]]=$rwSAT;
    }
    $oSQL->free_result($rsSAT);
    
    return $arrToRet;
}

private function checkCanStart(){
    if ($this->arrAction["actID"]<=4) 
        return true;
    
    if ($this->arrAction["aclOldStatusID"]!=$this->rwEnt[$this->entID."StatusID"])
        return false;
    
    return true;
}

public function attachFile($fileNameOriginal, $fileContents, $fileMIME="Application/binary"){
    
    $usrID = $this->intra->usrID;
    $arrSetup = $this->intra->conf;
    
    $oSQL = $this->oSQL;
    
    $sqlGUID = "SELECT UUID() as GUID";     
    $fileGUID = $oSQL->get_data($oSQL->do_query($sqlGUID));
    $filename = Date("Y/m/").$fileGUID.".att";
        
    //saving the file
    if(!file_exists($arrSetup["stpFilesPath"].Date("Y/m")))
        mkdir($arrSetup["stpFilesPath"].Date("Y/m"), "0777", true);
    //echo $arrSetup["stpFilesPath"].$filename;
    $fh = fopen($arrSetup["stpFilesPath"].$filename, "w");
    fwrite($fh, $fileContents, strlen($fileContents));
    fclose($fh);
    
    //making the record in the database
    $sqlFileInsert = "
        INSERT INTO stbl_file (
        filGUID
        , filEntityID
        , filEntityItemID
        , filName
        , filNamePhysical
        , filLength
        , filContentType
        , filInsertBy, filInsertDate, filEditBy, filEditDate
        ) VALUES (
        '".$fileGUID."'
        , '{$this->entID}'
        , '{$this->entItemID}'
        , '{$fileNameOriginal}'
        , '$filename'
        , '".strlen($fileContents)."'
        , '{$fileMIME}'
        , '{$this->intra->usrID}', NOW(), '{$this->intra->usrID}', NOW());
    ";
 
    $oSQL->do_query($sqlFileInsert);

}



function updateMasterTable($arrNewData = Array()){
    
    if (count($arrNewData)>0)
        $this->arrNewData = $arrNewData;
    
    if (count($this->arrNewData)==0){
        throw new Exception($intra->translate("New data set is empty for {$this->entID}/{$this->entItemID}"));
    }
    
    $oSQL = $this->oSQL;
    
    $entID = $this->entID;
    $entItemID = $this->entItemID;
    $rwEnt = $this->rwEnt;
    
    $intra = $this->intra;
    
    
    // 1. update table by visible/editable attributes list
    $sqlSAT = "SELECT * 
       FROM stbl_attribute
       INNER JOIN stbl_status_attribute ON satStatusID='".$rwEnt[$entID."StatusID"]."' AND satAttributeID=atrID AND satEntityID='$entID'
       WHERE atrEntityID='$entID'
       ORDER BY atrOrder ASC";
    $rsSAT = $oSQL->do_query($sqlSAT);
    
    $atrToUpd = Array();
    $strFieldList = "";
    $flagUpdateMultiple = $arrAction["flagUpdateMultiple"];
    
    while ($rwSAT = $oSQL->fetch_array($rsSAT)){
        
        if (!$rwSAT["satFlagEditable"]                                                      // not editable
            || ($arrNewData[$rwSAT["atrID"]]=="" && $flagUpdateMultiple)       // empty on multiple updates
            || !isset($arrNewData[$rwSAT["atrID"]]))                           // not set
        continue;
        
        $toEval = "\"".str_replace("\$_POST", "\$this->arrNewData", $intra->getSQLValue(Array('Field'=>$rwSAT['atrID'], 'DataType'=>$rwSAT['atrType'])))."\"";
        eval("\$newValue = ".$toEval.";");
        
        if ($newValue!=$rwEnt[$rwSAT["atrID"]]){
            $strFieldList .= "\r\n, `{$rwSAT["atrID"]}`={$newValue}";
        }
        
        if ($rwSAT["atrUOMTypeID"]){
            $strFieldList .= ", {$rwSAT["atrID"]}_uomID=".$oSQL->e($this->arrNewData["{$rwSAT["atrID"]}_uomID"]);
        }
    }
    
    $sqlUpdateTable = "UPDATE {$rwEnt["entTable"]} SET
        {$entID}EditDate=NOW(), {$entID}EditBy='{$this->intra->usrID}'
        {$strFieldList}
        WHERE {$entID}ID='{$entItemID}'";
    $oSQL->do_query($sqlUpdateTable);
    
}

function updateActionLog($arrNewData = Array()){
    
    $oSQL = $this->oSQL;
    
    if (count($arrNewData)>0)
        $this->arrNewData = $arrNewData;
    
    if (count($this->arrNewData)==0){
        throw new Exception($intra->translate("New data set is empty for {$this->entID}/{$this->entItemID}"));
    }
    
    $usrID = $this->intra->usrID;
    $arrTimestamp = $this->arrTimestamp;
    
    if (count($this->arrAction)==0){
        $this->collectActionData();
    } else {
        $arrAction = $this->arrAction;
    }
    
    $arrAction = $this->arrAction;
    $entID = $this->entID;
    
    $intra = $this->intra;
    
    $sqlToTrack = Array();
    
    foreach($this->arrACL as $aclGUID => $arrACL){
        
        $strEntityLogFldToSet = "";
        foreach ($arrACL["AAT"] as $atrID => $arrAAT){
            
            $newValue = null;
            $strACLInputID = $atrID."_".$aclGUID;
            $strTimeStampInputID = "acl".$arrAAT["aatFlagTimestamp"]."_".$aclGUID;
            
            // if we have it in arrNewData: atrID_aclGUID
            if (isset($this->arrNewData[$strACLInputID])   ) {
                $toEval = "\"".str_replace("\$_POST", "\$this->arrNewData"
                    , $intra->getSQLValue(Array('Field'=>$strACLInputID, 'DataType'=>$arrAAT["atrType"])))."\"";
                eval("\$newValue = ".$toEval.";");
            } else {
                
                // if have in arrNewData timestamp fields: aclATA_aclGUID
                if ($arrAAT["aatFlagTimestamp"]){
                    if(isset($this->arrNewData[$strTimeStampInputID])){
                        $toEval = "\"".str_replace("\$_POST", "\$this->arrNewData"
                            , $intra->getSQLValue(Array('Field'=>$strTimeStampInputID, 'DataType'=>"datetime")))."\"";
                        eval("\$newValue = ".$toEval.";");
                    }
                }
                
            }
            
            if ($newValue!=null){
                $strEntityLogFldToSet .= ", l{$atrID} = {$newValue}";
            }
            
            
        }
        $sqlToTrack[] = "UPDATE {$this->rwEnt["entTable"]}_log SET 
                    l{$entID}EditBy='{$this->intra->usrID}', l{$entID}EditDate=NOW()
                    {$strEntityLogFldToSet}
                WHERE l{$entID}GUID='{$aclGUID}'";
        
        
        $tsfieldsToSet = "";
        foreach($arrTimestamp as $ts){
            //echo "acl".$ts."_".$aclGUID." ";
            if (isset($this->arrNewData["acl".$ts."_".$aclGUID])){
                $toEval = "\"".str_replace("\$_POST", "\$this->arrNewData"
                            , $intra->getSQLValue(Array('Field'=>"acl".$ts."_".$aclGUID, 'DataType'=>"datetime")))."\"";
                        eval("\$newValue = ".$toEval.";");
                $tsfieldsToSet .= "\r\n, acl{$ts}={$newValue}";
            }
        }
        
        $sqlToTrack[] = "UPDATE stbl_action_log SET aclEditBy='{$this->intra->usrID}'
           , aclEditDate=NOW()
           {$tsfieldsToSet}
           WHERE aclGUID='{$aclGUID}'";
        
    }
    
    for($i=0;$i<count($sqlToTrack);$i++){
        $oSQL->do_query($sqlToTrack[$i]);
    }

}

function checkMandatoryFields(){
    
    $oSQL = $this->oSQL;
    
    $entID = $this->rwEnt["entID"];
    $entItemID = $this->rwEnt[$entID."ID"];
    $entTable = $this->rwEnt["entTable"];
    $rwEnt = $this->rwEnt;
    $flagAutocomplete = $this->arrAction["actFlagAutocomplete"];
    $aclGUID = $this->arrAction["aclGUID"];
    
    
    foreach($this->arrAction["AAT"] as $atrID => $rwATR)
    if ($rwATR["aatFlagMandatory"] || $rwATR["aatFlagToChange"]){
        
        if ($this->arrAction["aclGUID"]==""){
            $sqlCheckMandatory = "SELECT 
                CASE WHEN IFNULL({$atrID}, '')='' THEN 0 ELSE 1 END as mandatoryOK 
                FROM {$entTable} WHERE {$entID}ID='{$entItemID}'";
            $sqlCheckChanges = "SELECT 
                CASE WHEN IFNULL({$atrID}, '')='{$rwEnt[$atrID]}' THEN 0 ELSE 1 END as changedOK 
                FROM {$entTable} WHERE {$entID}ID='{$entItemID}'";;
        } else {
            
            $sqlCheckMandatory = "SELECT 
                CASE WHEN IFNULL(l{$atrID}, '')='' THEN 0 ELSE 1 END as mandatoryOK 
                FROM {$entTable}_log 
                WHERE l{$entID}GUID='{$aclGUID}'";
            //$oldValue = $this->arrAction["ACL"][$aclGUID]["ATV"][$atrID];
            $oldValue = $rwEnt[$atrID];
            $sqlCheckChanges = "SELECT 
                CASE WHEN IFNULL(l{$atrID}, '')=".$oSQL->escape_string($oldValue)." THEN 0 ELSE 1 END as changedOK 
                FROM {$entTable}_log
                WHERE l{$entID}GUID='{$aclGUID}'";
        }
        
        if ($rwATR["aatFlagMandatory"]){
            if (!$oSQL->get_data($oSQL->do_query($sqlCheckMandatory))){
                throw new Exception("Mandatory field '{$rwATR["atrTitle"]}' is not set for {$entItemID}");
                die();
            } 
        }
        
        if ($rwATR["aatFlagToChange"]){
            if (!$oSQL->get_data($oSQL->do_query($sqlCheckChanges))){
                throw new Exception("Field value for '{$rwATR["atrTitle"]}' cannot be '{$oldValue}', it should be changed for {$entItemID}");
                die();
            } 
        }
        
    }
    
}

function checkTimeLine(){
	
    $oSQL = $this->oSQL;
    
	$entID = $this->rwEnt["entID"];
    $entItemID = $this->rwEnt[$entID."ID"];
	$aclGUID = $this->arrAction["aclGUID"];
	
	if ($this->arrAction["actID"]=="2")
		return true;
		
	
	$sqlMaxATA = "SELECT 
		CASE WHEN DATEDIFF(
			(SELECT aclATA FROM stbl_action_log WHERE aclGUID='{$aclGUID}')
			, MAX(aclATA)
			) < 0 THEN 0 ELSE 1 END as ATAnotLessThanPrevious
	FROM stbl_action_log 
	WHERE aclEntityItemID='{$entItemID}' AND aclActionPhase=2 AND aclActionID>2";
	if (!$oSQL->get_data($oSQL->do_query($sqlMaxATA))) {
		throw new Exception("ATA for execueted action cannot be in the past for {$entItemID}");
		die();
	}
	
	$sqlATAATD = "SELECT 
		CASE WHEN DATEDIFF (aclATA, IFNULL(aclATD, aclATA)) < 0 THEN 0 ELSE 1 END as ATAnotLessThanATD
		FROM stbl_action_log 
		WHERE aclGUID='{$aclGUID}'
		";
	if (!$oSQL->get_data($oSQL->do_query($sqlMaxATA))) {
		throw new Exception("ATA for execueted action cannot be less than ATD");
		die();
	}
	
	return true;
}


function startAction(){
    
    $oSQL = $this->oSQL;
    
    $usrID = $this->intra->usrID;
    $entID = $this->entID;
    $entItemID = $this->entItemID;
    
    
    if (!$this->checkCanStart()){
        throw new Exception("Action {$this->arrAction["actTitle"]} cannot be started for {$entItemID} because of its status ({$this->rwEnt[$entID."StatusID"]})");
    }
    
    $sqlUpdACL = "UPDATE stbl_action_log SET 
        aclActionPhase=1
        , aclStartBy='{$this->intra->usrID}', aclStartDate=NOW()
        , aclEditBy='{$this->intra->usrID}', aclEditDate=NOW()
    WHERE aclGUID='{$this->arrAction["aclGUID"]}'";
    $oSQL->do_query($sqlUpdACL);
    
    $sqlUpdEntTable = "UPDATE {$this->rwEnt["entTable"]} SET
            {$entID}ActionLogID='{$this->arrAction["aclGUID"]}'
            , {$entID}EditBy='{$this->intra->usrID}', {$entID}EditDate=NOW()
            WHERE {$entID}ID='{$entItemID}'";
    $oSQL->do_query($sqlUpdEntTable);
    
}


function cancelAction($oSQL, $arrAction){

    $usrID = $this->intra->usrID;

    $entID = $arrAction["rwEnt"]["entID"];
    $entItemID = $arrAction["rwEnt"][$entID."ID"];

    if (!isset($arrAction["aclGUID"])){
        collectActionData($oSQL,$arrAction);
    }

    if ($arrAction["aclActionPhase"]>0){
        //get last stl for action
        $stlToDelete = $oSQL->get_data($oSQL->do_query("SELECT stlGUID FROM stbl_status_log WHERE stlEntityID='{$arrAction["entID"]}' 
            AND stlEntityItemID='{$arrAction["entItemID"]}' 
            AND stlArrivalActionID='{$arrAction["aclGUID"]}'"));
        //get full previous stl
        $rwSTLLast = $oSQL->fetch_array($oSQL->do_query("SELECT * FROM stbl_status_log WHERE stlEntityID='{$arrAction["entID"]}' 
            AND stlEntityItemID='{$arrAction["entItemID"]}' 
            AND stlDepartureActionID='{$arrAction["aclGUID"]}'"));

        //delete traced attributes for the STL
        $sql[] = "DELETE FROM {$arrAction["rwEnt"]["entTable"]}_log WHERE l{$entID}GUID='{$stlToDelete}'";

        // delete status log entry, if any
        $sql[] = "DELETE FROM stbl_status_log WHERE stlGUID='{$stlToDelete}'";

        // update departure action for previous status log entry
        $sql[] = "UPDATE stbl_status_log SET stlATD=NULL, stlDepartureActionID=NULL WHERE stlGUID='{$rwSTLLast["stlGUID"]}'";

        if (empty($arrAction["arrNewData"]["isUndo"])) {
            //cancel the action
            $sql[] = "UPDATE stbl_action_log SET aclActionPhase=3
                , aclEditBy='{$this->intra->usrID}'
                , aclEditDate = NOW()
                WHERE aclGUID='{$arrAction["aclGUID"]}'";
        } else {
            //delete the action
            $sql[] = "DELETE FROM {$arrAction["rwEnt"]["entTable"]}_log 
                WHERE l{$entID}GUID='{$arrAction["aclGUID"]}'";
                
            $sql[] = "DELETE FROM stbl_action_log
                WHERE aclGUID='{$arrAction["aclGUID"]}'";
        }

        // update entity table
        $sql[] = "UPDATE {$arrAction["rwEnt"]["entTable"]} SET
            {$entID}ActionLogID=(SELECT aclGUID FROM stbl_action_log INNER JOIN stbl_action ON aclActionID=actID AND actEntityID='{$entID}' 
                  WHERE aclEntityItemID='{$entItemID}' AND aclActionID<>2 AND aclActionPhase=2 
                  ORDER BY aclATA DESC LIMIT 0,1)
            ".($stlToDelete != ""
                ? " , {$entID}StatusActionLogID = '{$rwSTLLast["stlGUID"]}'
                    , {$entID}StatusID = '{$rwSTLLast["stlStatusID"]}'"
                : ""
            )."
            , {$entID}EditBy='{$this->intra->usrID}', {$entID}EditDate=NOW()
            WHERE {$entID}ID='{$entItemID}'";

    } else {
        $sql[] = "DELETE FROM {$arrAction["rwEnt"]["entTable"]}_log 
           WHERE l{$entID}GUID='{$arrAction["aclGUID"]}'";
        //we delete action itself
        $sql[] = "DELETE FROM stbl_action_log WHERE aclGUID='{$arrAction["aclGUID"]}'";
    }

    for ($i=0;$i<count($sql);$i++) {
        $oSQL->do_query($sql[$i]);
    }

}


function GetJoinSentenceByCBSource($sqlSentence, $entField, &$strText, &$strValue){
   $prgValue = "/(SELECT|,)\s+(\S+) as optValue/i";
   $prgText = "/(SELECT|,)\s+(.+) as optText/i";
   $prgTable = "/FROM ([\S]+)/i";
   
   
   preg_match($prgValue, $sqlSentence, $arrValue);
   preg_match($prgText, str_replace($arrValue[0], "", $sqlSentence), $arrText);
   preg_match($prgTable, $sqlSentence, $arrTable);
   
   $strValue = $arrValue[2];
   $strText = $arrText[2];
   $strTable = $arrTable[1];
   
   $strFrom = "LEFT OUTER JOIN $strTable ON $entField=$strValue";
   
   return $strFrom;
}



/***********************************************************************************/
/* Comments Routines                                                               */
/***********************************************************************************/
function updateComments($DataAction){

GLOBAL $intra;

$oSQL = $intra->oSQL;
$usrID = $intra->usrID;

switch ($DataAction) {
    case "delete_comment":
       $oSQL->do_query("DELETE FROM stbl_comments WHERE scmGUID='{$_POST["scmGUID"]}'");
        header("Content-Type: text/xml; charset=UTF-8");
        echo "<?xml version=\"1.0\"?>\r\n";
        echo "<document><message>Comment is sucessfully deleted</message></document>";
       die();
       break;
    case "add_comment":
       
       $oSQL->do_query("SET @scmGUID=UUID()");
       
       $sqlIns = "INSERT INTO stbl_comments (
            scmGUID
            , scmEntityItemID
            , scmAttachmentID
            , scmContent
            , scmInsertBy, scmInsertDate, scmEditBy, scmEditDate
            ) VALUES (
            @scmGUID
            , ".($_GET['scmEntityItemID']!="" ? "'".$_GET['scmEntityItemID']."'" : "NULL")."
            , ".($_GET['scmAttachmentID']!="" ? "'".$_GET['scmAttachmentID']."'" : "NULL")."
            , ".$oSQL->escape_string($_GET['scmContent'])."
            , '{$intra->usrID}', NOW(), '{$intra->usrID}', NOW());";
        $oSQL->do_query($sqlIns);
        
        $scmGUID = $oSQL->get_data($oSQL->do_query("SELECT @scmGUID as scmGUID"));
        
        $arrData = Array("scmGUID"=>$scmGUID, "user"=>$intra->arrUsrData["usrName{$intra->local}"]);
        
        echo json_encode($arrData);
       
        die();
}
}

/***********************************************************************************/
/* File Attachment Routines                                                        */
/***********************************************************************************/
function updateFiles($DataAction){
    
    GLOBAL $intra;
    
    $oSQL = $intra->oSQL;
    
    $usrID = $intra->usrID;
    $arrSetup = $intra->conf;

switch ($DataAction) {
    case "deleteFile":
        $rwFile = $oSQL->fetch_array($oSQL->do_query("SELECT * FROM stbl_file WHERE filGUID='{$_GET["filGUID"]}}'"));
        unlink($arrSetup["stpFilesPath"].$rwFile["filNamePhysical"]);
        $oSQL->do_query("DELETE FROM stbl_file WHERE filGUID='{$_GET["filGUID"]}'");
        SetCookie("UserMessage", "File deleted");
        header("Location: ".$_GET["referer"]);
        die();
        break;
    case "attachFile":
        
        $entID = $_POST["entID_Attach"];
        
        $sqlGUID = "SELECT UUID() as GUID";     
        $fileGUID = $oSQL->get_data($oSQL->do_query($sqlGUID));
        $filename = Date("Y/m/").$fileGUID.".att";
        
        //saving the file
        if(!file_exists($arrSetup["stpFilesPath"].Date("Y/m")))
            mkdir($arrSetup["stpFilesPath"].Date("Y/m"), "0777", true);
        //echo $arrSetup["stpFilesPath"].$filename;
        copy($_FILES["attachment"]["tmp_name"], $arrSetup["stpFilesPath"].$filename);
        
        //making the record in the database
        $sqlFileInsert = "
            INSERT INTO stbl_file (
            filGUID
            , filEntityID
            , filEntityItemID
            , filName
            , filNamePhysical
            , filLength
            , filContentType
            , filInsertBy, filInsertDate, filEditBy, filEditDate
            ) VALUES (
            '".$fileGUID."'
            , '$entID'
            , '{$_POST["entItemID_Attach"]}'
            , '{$_FILES["attachment"]["name"]}'
            , '$filename'
            , '{$_FILES["attachment"]["size"]}'
            , '{$_FILES["attachment"]["type"]}'
            , '{$intra->usrID}', NOW(), '{$intra->usrID}', NOW());
        ";
        
        /*
        echo "<pre>";
        print_r($_POST);
        print_r($_FILES);
        echo $sqlFileInsert;
        echo "</pre>";
        die();
        //*/
        
        //$oSQL->do_query($sqlFileInsert);
        
        //echo "Okay";
        $oSQL->do_query($sqlFileInsert);
        SetCookie("UserMessage", $intra->translate("File uploaded"));
        header("Location: ".$_SERVER["PHP_SELF"]."?{$entID}ID=".urlencode($_POST["entItemID_Attach"]));
        die();
    default: break;
}


}



/***********************************************************************************/
/* Archive/Restore Routines                                                        */
/***********************************************************************************/
function archive($arrExtraTables = Array()) {
    
    $oSQL = $this->oSQL;
    $intra = $this->intra;
    
    if (!isset($intra->$oSQL_arch)){
        $oSQL_arch = $intra->getArchiveSQLObject();
    } else {
        $oSQL_arch = $intra->oSQL_arch;
    }
	
    // 1. collect data from tables into assoc array
	$this->intra->local = ""; //important! We backup only english titles
    $this->getEntityItemAllData();
	
    // 2. compose XML
    $strData = json_encode($this->rwEnt);
	
    // 3. insert into archive
	// compose SQL
	// get attributes
	if (!isset($this->arrATR)){
		$sqlATR = "SELECT * FROM stbl_attribute WHERE atrEntityID='{$this->entID}' ORDER BY atrOrder";
		$rsATR = $oSQL->do_query($sqlATR);
		while ($rwATR = $oSQL->fetch_array($rsATR)) {
			$this->arrATR[$rwATR["atrID"]] = $rwATR;
		}
	}
    
	$sqlIns = "INSERT IGNORE INTO `{$this->rwEnt["entTable"]}` (
          `{$this->entID}ID`
            , `{$this->entID}StatusID`
            , `{$this->entID}StatusTitle`
            , `{$this->entID}StatusTitleLocal`
            , `{$this->entID}StatusATA`
            , `{$this->entID}Data`
            , `{$this->entID}InsertBy`, `{$this->entID}InsertDate`, `{$this->entID}EditBy`, `{$this->entID}EditDate`";
    foreach ($this->arrATR as $atrID => $rwATR){
		$sqlIns .= "\r\n, `{$atrID}`";
	}
	$sqlIns .= ") VALUES (
		".$oSQL->e($this->rwEnt[$this->entID."ID"])."
		, ".(int)($this->rwEnt[$this->entID."StatusID"])."
		, ".$oSQL->e($this->rwEnt["staTitle"])."
		, ".$oSQL->e($this->rwEnt["staTitleLocal"])."
		, ".$oSQL->e($oSQL->d("SELECT aclATA FROM stbl_action_log WHERE aclGUID=".$oSQL->e($this->rwEnt["{$this->entID}StatusActionLogID"])))."
		, ".$oSQL->e($strData)."
		, '{$intra->usrID}', NOW(), '{$intra->usrID}', NOW()";
	foreach ($this->arrATR as $atrID => $rwATR){
		switch ($rwATR["atrType"]){
			case "combobox":
			case "ajax_dropdown":
				$val = $oSQL->e($this->rwEnt[$atrID."_Text"]);
				break;
			case "number":
			case "numeric":
			case "date":
			case "datetime":
				$val = ($this->rwEnt[$atrID]!="" ? $oSQL->e($this->rwEnt[$atrID]) : "NULL");
				break;
			case "boolean":
				$val = (int)$this->rwEnt[$atrID];
				break;
			default:
				$val = $oSQL->e($this->rwEnt[$atrID]);
				break;
		}
		$sqlIns .= "\r\n, {$val}";
	}
	$sqlIns .= ")";
	
	$oSQL_arch->q($sqlIns);
	
	//echo "<pre>";
	//echo "{$sqlIns}";
	//print_r($this->rwEnt);    
	
	// 4. backup extra tables
    foreach($arrExtraTables as $table=>$arrTable)
        $intra->archiveTable($table, $arrTable["criteria"], $arrTable["nodelete"]);
	
    // 5. delete entity item
    $this->delete();
    
}

function restore($arrExtraTables = Array()) {
	
    $oSQL = $this->oSQL;
    $intra = $this->intra;
    
    if (!isset($intra->$oSQL_arch)){
        $oSQL_arch = $intra->getArchiveSQLObject();
    } else {
        $oSQL_arch = $intra->oSQL_arch;
    }
    
    $this->getEntityItemAllData();
    
    // restore master
    // - check master compliance
    $this->arrMaster = $intra->getTableInfo($oSQL->dbname, $this->rwEnt["entTable"]);
    
    $strFields = "";
    $strValues = "";
    foreach($this->arrMaster['columns'] as $ix =>  $col){
        $strFields .= ($strFields!="" ? "\r\n, " : "")."`{$col['Field']}`";
        $strValues .= ($strValues!="" ? "\r\n, " : "").(!isset($this->rwEnt[$col['Field']]) || is_null($this->rwEnt[$col['Field']]) 
            ? "NULL"
            : $oSQL->e($this->rwEnt[$col['Field']]));
    }
    $sqlIns = "INSERT IGNORE INTO {$this->rwEnt['entTable']} ({$strFields}
        ) VALUES ( {$strValues} )";
    $oSQL->q($sqlIns);
    
    // restore action log
    foreach($this->rwEnt["ACL"] as $rwAct){
        $this->restoreAction($rwAct);
    }
    
    foreach($this->rwEnt["STL"] as $rwSTL){
        $this->restoreStatus($rwSTL);
    }
    
    
    // restore comments
    foreach($this->rwEnt["comments"] as $rwComment){
        $this->restoreComment($rwComment);
    }
    
    // restore files
    foreach($this->rwEnt["files"] as $rwFile){
        $this->restoreFile($rwFile);
    }
    
    //restore extras
    foreach($arrExtraTables as $table=>$arrTable)
        $intra->restoreTable($table, $arrTable["criteria"], $arrTable["nodelete"]);
    
    
    // delete
    $this->delete();
        
    $this->flagArchive = false;
}

function restoreAction($rwACL){
    
    $oSQL = $this->oSQL;
    
    $sqlIns = "INSERT IGNORE INTO stbl_action_log (
        aclGUID
        , aclActionID
        , aclEntityItemID
        , aclOldStatusID
        , aclNewStatusID
        , aclPredID
        , aclFlagIncomplete
        , aclActionPhase
        , aclETD
        , aclETA
        , aclATD
        , aclATA
        , aclComments
        , aclStartBy
        , aclStartDate
        , aclFinishBy
        , aclFinishDate
        , aclCancelBy
        , aclCancelDate
        , aclInsertBy, aclInsertDate, aclEditBy, aclEditDate
        ) VALUES (
        ".$oSQL->e($rwACL["aclGUID"])."
        , ".$oSQL->e($rwACL["aclActionID"])." #aclActionID
        , ".$oSQL->e($rwACL["aclEntityItemID"])." #aclEntityItemID
        , ".(is_null($rwACL["aclOldStatusID"]) ? "NULL" : $oSQL->e($rwACL["aclOldStatusID"]))." #aclOldStatusID
        , ".(is_null($rwACL["aclNewStatusID"]) ? "NULL" : $oSQL->e($rwACL["aclNewStatusID"]))." #aclNewStatusID
        , ".(is_null($rwACL["aclPredID"]) ? "NULL" : $oSQL->e($rwACL["aclPredID"]))." #aclPredID
        , ".(is_null($rwACL["aclFlagIncomplete"]) ? "NULL" : $oSQL->e($rwACL["aclFlagIncomplete"]))." #aclFlagIncomplete
        , ".(is_null($rwACL["aclActionPhase"]) ? "NULL" : $oSQL->e($rwACL["aclActionPhase"]))." #aclActionPhase
        , ".(is_null($rwACL["aclETD"]) ? "NULL" : $oSQL->e($rwACL["aclETD"]))." #aclETD
        , ".(is_null($rwACL["aclETA"]) ? "NULL" : $oSQL->e($rwACL["aclETA"]))." #aclETA
        , ".(is_null($rwACL["aclATD"]) ? "NULL" : $oSQL->e($rwACL["aclATD"]))." #aclATD
        , ".(is_null($rwACL["aclATA"]) ? "NULL" : $oSQL->e($rwACL["aclATA"]))." #aclATA
        , ".$oSQL->e($rwACL["aclComments"])." #aclComments
        , ".(is_null($rwACL["aclStartBy"]) ? "NULL" : $oSQL->e($rwACL["aclStartBy"]))." #aclStartBy
        , ".(is_null($rwACL["aclStartDate"]) ? "NULL" : $oSQL->e($rwACL["aclStartDate"]))." #aclStartDate
        , ".(is_null($rwACL["aclFinishBy"]) ? "NULL" : $oSQL->e($rwACL["aclFinishBy"]))." #aclFinishBy
        , ".(is_null($rwACL["aclFinishDate"]) ? "NULL" : $oSQL->e($rwACL["aclFinishDate"]))." #aclFinishDate
        , ".(is_null($rwACL["aclCancelBy"]) ? "NULL" : $oSQL->e($rwACL["aclCancelBy"]))." #aclCancelBy
        , ".(is_null($rwACL["aclCancelDate"]) ? "NULL" : $oSQL->e($rwACL["aclCancelDate"]))." #aclCancelDate
        , ".$oSQL->e($rwACL["aclInsertBy"])." #aclInsertBy
        , ".$oSQL->e($rwACL["aclInsertDate"])." #aclInsertDate
        , ".$oSQL->e($rwACL["aclEditBy"])." #aclEditBy
        , ".$oSQL->e($rwACL["aclEditDate"])." #aclEditDate
        )";
    $oSQL->q($sqlIns);
    
    $this->restoreTrackedAttributes($rwACL["aclGUID"], $rwACL["AAT"]);
    
}

function restoreStatus($rwSTL){
    
    $oSQL = $this->oSQL;
    
    // restore arrival action
    $this->restoreAction($rwSTL["stlArrivalAction"]);
    
    // restore nested actions
    if (isset($rwSTL["ACL"]))
    foreach($rwSTL["ACL"] as $rwAct){
        $this->restoreAction($rwAct);
    }
    
    // restore STL
    $sqlIns = "INSERT IGNORE INTO stbl_status_log (
        stlGUID
        , stlEntityID
        , stlEntityItemID
        , stlStatusID
        , stlArrivalActionID
        , stlDepartureActionID
        , stlTitle
        , stlTitleLocal
        , stlATA
        , stlATD
        , stlInsertBy, stlInsertDate, stlEditBy, stlEditDate
        ) VALUES (
        ".$oSQL->e($rwSTL["stlGUID"])."
        , ".$oSQL->e($rwSTL["stlEntityID"])."
        , ".$oSQL->e($rwSTL["stlEntityItemID"])."
        , ".$oSQL->e($rwSTL["stlStatusID"])."
        , ".(is_null($rwSTL["stlArrivalActionID"]) ? "NULL" : $oSQL->e($rwSTL["stlArrivalActionID"]))."
        , ".(is_null($rwSTL["stlDepartureActionID"]) ? "NULL" : $oSQL->e($rwSTL["stlDepartureActionID"]))."
        , ".$oSQL->e($rwSTL["stlTitle"])."
        , ".$oSQL->e($rwSTL["stlTitleLocal"])."
        , ".(is_null($rwSTL["stlATA"]) ? "NULL" : $oSQL->e($rwSTL["stlATA"]))."
        , ".(is_null($rwSTL["stlATD"]) ? "NULL" : $oSQL->e($rwSTL["stlATD"]))."
        , ".$oSQL->e($rwSTL["stlInsertBy"]).", ".$oSQL->e($rwSTL["stlInsertDate"]).", ".$oSQL->e($rwSTL["stlEditBy"]).", ".$oSQL->e($rwSTL["stlEditDate"]).")";
    $oSQL->q($sqlIns);
    
    // restore attributes
    $this->restoreTrackedAttributes($rwSTL["stlGUID"], $rwSTL["SAT"]);
   
}

function restoreTrackedAttributes($logGUID, $arrATR){
    
    $oSQL = $this->oSQL;
    
    if (count($arrATR)==0)  
        return;
        
    $oSQL->q("INSERT IGNORE INTO {$this->rwEnt["entTable"]}_log (l{$this->entID}GUID) VALUES (".$oSQL->e($logGUID).")");
    
    $sqlFields = "";
    foreach($arrATR as $rwATR){
        if (in_array($rwATR["atrID"], $this->arrMaster["columns_index"]))
            $strFields .= ($strFields=="" ? "" : "\r\n, ")."l{$rwATR["atrID"]} = ".(is_null($rwATR['value']) ? "NULL" : $oSQL->e($rwATR['value']));
    }
    
    $sqlUpd = "UPDATE {$this->rwEnt["entTable"]}_log SET {$strFields}
        WHERE l{$this->entID}GUID=".$oSQL->e($logGUID);
    $oSQL->q($sqlUpd);
    
}

function restoreComment($rwSCM){
    $oSQL = $this->oSQL;
    $sqlIns = "INSERT IGNORE INTO stbl_comments (
        scmGUID
        , scmEntityItemID
        , scmAttachmentID
        , scmActionLogID
        , scmContent
        , scmInsertBy, scmInsertDate, scmEditBy, scmEditDate
        ) VALUES (
        ".$oSQL->e($rwSCM["scmGUID"])."
        , ".$oSQL->e($rwSCM["scmEntityItemID"])."
        , ".(is_null($rwSCM["scmAttachmentID"]) ? "NULL" : $oSQL->e($rwSCM["scmAttachmentID"]))."
        , ".(is_null($rwSCM["scmActionLogID"]) ? "NULL" : $oSQL->e($rwSCM["scmActionLogID"]))."
        , ".(is_null($rwSCM["scmContent"]) ? "NULL" : $oSQL->e($rwSCM["scmContent"]))."
        , ".$oSQL->e($rwSCM["scmInsertBy"])."
        , ".(is_null($rwSCM["scmContent"]) ? "NULL" : $oSQL->e($rwSCM["scmInsertDate"]))."
        , ".$oSQL->e($rwSCM["scmEditBy"])."
        , ".(is_null($rwSCM["scmContent"]) ? "NULL" : $oSQL->e($rwSCM["scmEditDate"])).
        ")";
    $oSQL->q($sqlIns);
}

function restoreFiles($rwFil){
    $oSQL = $this->oSQL;
    $sqlIns = "IINSERT IGNORE INTO stbl_file (
        filGUID
        , filEntityID
        , filEntityItemID
        , filName
        , filNamePhysical
        , filLength
        , filContentType
        , filInsertBy, filInsertDate, filEditBy, filEditDate
        ) VALUES (
        ".$oSQL->e($rwFil["filGUID"])."
        , ".$oSQL->e($rwFil["filEntityID"])."
        , ".$oSQL->e($rwFil["filEntityItemID"])."
        , ".$oSQL->e($rwFil["filName"])."
        , ".$oSQL->e($rwFil["filNamePhysical"])."
        , ".$oSQL->e($rwFil["filLength"])."
        , ".$oSQL->e($rwFil["filContentType"])."
        , ".$oSQL->e($rwFil["filInsertBy"]).", ".$oSQL->e($rwFil["filInsertDate"]).", ".$oSQL->e($rwFil["filEditBy"]).", ".$oSQL->e($rwFil["filEditDate"]).")";
    $oSQL->q($sqlIns);
}

function getActionData($aclGUID){
	
    $oSQL = $this->oSQL;
    $entID = $this->entID;
	
	$arrRet = Array();
	
    if (!$aclGUID) return;
    
    $sqlACT = "SELECT ACL.*, ACT.*
       , STA_OLD.staID as staID_Old
       , STA_OLD.staTitle as staTitle_Old
       , STA_OLD.staTitleLocal as staTitleLocal_Old
       , STA_NEW.staID as staID_New
       , STA_NEW.staTitle as staTitle_New
       , STA_NEW.staTitleLocal as staTitleLocal_New
       FROM stbl_action_log ACL
       INNER JOIN stbl_action ACT ON aclActionID=actID
       LEFT OUTER JOIN stbl_status STA_OLD ON aclOldStatusID=STA_OLD.staID AND STA_OLD.staEntityID=actEntityID
       LEFT OUTER JOIN stbl_status STA_NEW ON aclNewStatusID=STA_NEW.staID AND STA_NEW.staEntityID=actEntityID
       WHERE aclGUID='{$aclGUID}'";
	
	$rwACT = $oSQL->fetch_array($oSQL->do_query($sqlACT));
    
	$arrRet = $rwACT;
	
	// linked attributes
	if (!isset($this->arrAAT[$rwACT["actID"]]))
		$this->arrAAT[$rwACT["actID"]] = $this->getActionAttribute($rwACT["actID"]);
	
    $sqlLOG = "SELECT * FROM {$this->rwEnt["entTable"]}_log WHERE l{$entID}GUID='{$rwACT["aclGUID"]}'";
    $rsLOG = $oSQL->do_query($sqlLOG);
	if ($oSQL->n($rsLOG) > 0){
	    $rwLOG = $oSQL->fetch_array($rsLOG);
	    
		foreach($this->arrAAT[$rwACT["actID"]] as $atrID => $arrATR){
			$arrVal = Array("value" => $rwLOG["l".$arrATR["atrID"]]);
			if (in_array($arrATR["atrType"], Array("combobox", "ajax_dropdown")))
				$arrVal["text"] = ($rwLOG["l".$arrATR["atrID"]] != ""
					? $oSQL->d($this->intra->getDataFromCommonViews($rwLOG["l".$arrATR["atrID"]], null, $arrATR["atrDataSource"], null, true))
					: $arrATR["atrDefault"]
				);
			$arrRet["AAT"][$atrID] = array_merge($arrATR, $arrVal);
		}
    }
	return $arrRet;
    
}

function getStatusData($stlGUID){
	
	$oSQL = $this->oSQL;
    $entID = $this->entID;
	
	$arrRet = Array();
	
    if (!$stlGUID) return Array();
	
	$sqlSTL = "
		SELECT STL.* , STA.*
		FROM stbl_status_log STL
		INNER JOIN stbl_status STA ON staEntityID=stlEntityID AND staID=stlStatusID
		WHERE stlGUID=".$oSQL->e($stlGUID);
	$rsSTL = $oSQL->do_query($sqlSTL);
	if ($oSQL->n($rsSTL) == 0) return Array();
	
	$rwSTL = $oSQL->fetch_array($rsSTL);
		
	$arrRet = $rwSTL;
	
	$stlATD = ($rwSTL["stlATD"]=="" ? date("Y-m-d") : $rwSTL["stlATD"]);   
	$sqlNAct = "SELECT aclGUID FROM stbl_action_log 
	   INNER JOIN stbl_action ON aclActionID=actID
	   WHERE (DATE(aclATA) BETWEEN DATE('{$rwSTL["stlATA"]}') AND DATE('{$stlATD}'))
		 AND aclOldStatusID='{$rwSTL["stlStatusID"]}'
		 AND (aclOldStatusID=aclNewStatusID AND actFlagInterruptStatusStay=0)
	   AND aclActionPhase=2
	   AND aclActionID<>2
	   AND aclEntityItemID='{$this->entItemID}'
	   ORDER BY aclInsertDate DESC";
	//echo "<pre>".$sqlNAct."</pre>";
	$rsNAct = $oSQL->do_query($sqlNAct);
	while ($rwNAct = $oSQL->fetch_array($rsNAct)){
		$arrRet["ACL"][$rwNAct["aclGUID"]] = $this->getActionData($rwNAct["aclGUID"]);
	}
	
	// linked attributes
	if (!isset($this->arrSAT[$rwSTL["staID"]]))
		$this->arrSAT[$rwSTL["staID"]] = $this->getStatusAttribute($rwSTL["staID"]);
	
	$sqlLOG = "SELECT * FROM {$this->rwEnt["entTable"]}_log WHERE l{$entID}GUID='{$rwSTL["stlGUID"]}'";
	$rsLOG = $oSQL->do_query($sqlLOG);
	if ($oSQL->n($rsLOG) > 0){
		$rwLOG = $oSQL->fetch_array($rsLOG);
		foreach($this->arrSAT[$rwSTL["staID"]] as $atrID => $arrATR){
            
            if (!$arrATR["satFlagTrackOnArrival"]) continue;
            
			$arrVal = Array("value" => $rwLOG["l".$arrATR["atrID"]]);
			if (in_array($arrATR["atrType"], Array("combobox", "ajax_dropdown")))
				$arrVal["text"] = ($rwLOG["l".$arrATR["atrID"]] != ""
					? $oSQL->d($this->intra->getDataFromCommonViews($rwLOG["l".$arrATR["atrID"]], null, $arrATR["atrDataSource"], null, true))
					: $arrATR["atrDefault"]
				);
			$arrRet["SAT"][$atrID] = array_merge($arrATR, $arrVal);
		}
	}
	
	$arrRet["stlArrivalAction"] = $this->getActionData($rwSTL["stlArrivalActionID"]);
		
	return $arrRet;
	
}

function getEntityItemAllData(){
	
    if ($this->flagArchive) {
        $arrData = json_decode($this->rwEnt["{$this->entID}Data"], true);
        $this->rwEnt = array_merge($this->rwEnt, $arrData);
        return $this->rwEnt;
    }
    
	//   - Master table is $this->rwEnt
	// attributes and combobox values
	$sqlAtr = "SELECT * 
       FROM stbl_attribute 
       LEFT OUTER JOIN stbl_status_attribute ON satStatusID='".$this->rwEnt["{$this->entID}StatusID"]."' AND satAttributeID=atrID AND satEntityID='{$this->entID}'
       WHERE atrEntityID='{$this->entID}'
       ORDER BY atrOrder ASC";
    $rsAtr = $this->oSQL->do_query($sqlAtr);
	while ($rwATR = $this->oSQL->f($rsAtr)){
		$this->rwEnt["ATR"][$rwATR["atrID"]] = $rwATR;
		if (in_array($rwATR["atrType"], Array("combobox", "ajax_dropdown")))
				$this->rwEnt[$rwATR["atrID"]."_Text"] = ($this->rwEnt[$rwATR["atrID"]] != ""
					? $this->oSQL->d($this->intra->getDataFromCommonViews($this->rwEnt[$rwATR["atrID"]], null, $rwATR["atrDataSource"], null, true))
					: $rwATR["atrDefault"]
				);
	}
	
	// collect incomplete/cancelled actions
	$this->rwEnt["ACL"]  = Array();
	$sqlACL = "SELECT * FROM stbl_action_log 
            WHERE aclEntityItemID='{$this->entItemID}'
            AND aclActionPhase <> 2 
            ORDER BY aclInsertDate DESC";
	$rsACL = $this->oSQL->do_query($sqlACL);
	while($rwACL = $this->oSQL->fetch_array($rsACL)){
		$this->rwEnt["ACL"][$rwACL["aclGUID"]] = $this->getActionData($rwACL["aclGUID"]);
	}
	
    // collect status log and nested actions
    $this->rwEnt["STL"] = Array();
	$sqlSTL = "SELECT stlGUID
		FROM stbl_status_log 
		WHERE stlEntityItemID='{$this->entItemID}' AND stlEntityID='{$this->entID}'
		ORDER BY DATE(stlATA) DESC
			, stlInsertDate DESC
			, stlArrivalActionID DESC
		";
	$rsSTL = $this->oSQL->q($sqlSTL);
	while ($rwSTL = $this->oSQL->fetch_array($rsSTL)){
		$this->rwEnt["STL"][$rwSTL["stlGUID"]] = $this->getStatusData($rwSTL["stlGUID"]);
	}
	
	//comments
	$this->rwEnt["comments"] = Array();
	$sqlSCM = "SELECT * 
	FROM stbl_comments 
	WHERE scmEntityItemID='{$this->entItemID}' ORDER BY scmInsertDate DESC";
	$rsSCM = $this->oSQL->do_query($sqlSCM);
	while ($rwSCM = $this->oSQL->f($rsSCM)){
		$this->rwEnt["comments"]["scmGUID"] = $rwSCM;
	}
	
	//files
	$this->rwEnt["files"] = Array();
	$sqlFile = "SELECT * FROM stbl_file WHERE filEntityID='{$this->entID}' AND filEntityItemID='{$this->entItemID}'
	ORDER BY filInsertDate DESC";
	$rsFile = $this->oSQL->do_query($sqlFile);
	while ($rwFIL = $this->oSQL->f($rsFile)){
		$this->rwEnt["files"]["filGUID"] = $rwFIL;
	}
	
	//message
	$this->rwEnt["messages"] = Array();//not yet
	
    //echo "<pre>";
    //print_r($this->rwEnt);
    //die();
    
	return $this->rwEnt;
	
}

}

?>