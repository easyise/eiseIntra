<?php
include_once "inc_entity.php";

define("MAX_STL_LENGTH", 256);

class eiseEntityItem extends eiseEntity {

public $oSQL;
public $entID;
public $entItemID;

public $item;

public $arrTimestamp = Array('ETD', 'ETA', 'ATD', 'ATA');
public $arrActionPhase = Array('0' => "Planned"
, '1' => "Started"
, '2' => "Complete"
, '3' => "Cancelled");

protected $arrAction = Array(); // current action data
protected $arrNewData = Array(); // new data, it could be $_POST

protected $defaultDataToObtain = array('Text', 'ACL', 'STL', 'comments', 'files', 'messages');

function __construct( $oSQL, $intra, $entID, $entItemID, $conf = array() ){
    
    $confDefault = array('flagArchive'=>false
        , 'flagCreateEmptyObject' => false);

    $confToMerge = is_array($conf) ? $conf : array('flagArchive'=>$conf);

    $conf = array_merge($confDefault, $confToMerge);

    parent::__construct($oSQL, $intra, $entID);
    
    $this->entItemID = $entItemID;
    
    $this->flagArchive = $conf['flagArchive'];
    
    if ($entItemID || $conf['flagCreateEmptyObject']) {

        if($entItemID){
            if (!$flagArchive){
                $this->getEntityItemData();
            } else  {
                $this->getEntityItemDataFromArchive();
            }
            
            $this->staID = $this->item[$entID."StatusID"];

        } else {
            $this->staID = null;
        }
    } else 
        throw new Exception ("Entity ID not set");
    
    
    
}

function refresh($toRefresh=array('Master')){

    $this->item = array();
    $this->arrAction = array();

    if(!$this->flagArchive){
        $this->getEntityItemAllData($toRefresh);
    } else {
        $this->getEntityItemDataFromArchive();
        if(count($toRefresh)>1){
            unset($toRefresh[array_search('Master', $toRefresh)]); //prevent double-retrieval of information
            $this->getEntityItemAllData($toRefresh);
        }
    }

    return $this->item;

}

function getEntityItemData(){
    
    $oSQL = $this->oSQL;
    $entID = $this->entID;
    $entItemID = $this->entItemID;

    $sqlEnt = "SELECT * 
            FROM {$this->conf['entTable']}
            LEFT OUTER JOIN stbl_status STA ON STA.staID={$entID}StatusID AND staEntityID='$entID'
            LEFT OUTER JOIN stbl_action_log ACL ON {$entID}ActionLogID=ACL.aclGUID
            WHERE {$entID}ID=".$oSQL->e($entItemID);

    $rsEnt = $oSQL->q($sqlEnt);
    if ($oSQL->n($rsEnt)==0)
        throw new Exception("Entity Item not found".": ".$entID."/".$entItemID);
    
    $rwItem = $oSQL->f($rsEnt);

    $this->item = array_merge(
        (is_array($this->item) ? $this->item : array())
        , $rwItem);
    
    return $rwItem;
    
}

function getEntityItemDataFromArchive(){
    
    $oSQL = $this->oSQL;
    $entID = $this->entID;
    $entItemID = $this->entItemID;

    $oSQL_arch = $this->intra->getArchiveSQLObject();
    
    $sqlEnt = "SELECT * 
            FROM {$this->conf['entTable']}
            WHERE {$entID}ID=".$oSQL->e($entItemID);
    $rsEnt = $oSQL_arch->q($sqlEnt);
    if ($oSQL_arch->n($rsEnt)==0)
        throw new Exception("Entity Item not found in the Archive database {$oSQL_arch->dbname}".": ".$entID."/".$entItemID);
    
    $this->item = $oSQL_arch->fetch_array($rsEnt);

    return $this->item;
    
}


function getEntityItemAllData($toRetrieve = null){
    
    if(!$this->entItemID)
        return array();

    if($toRetrieve===null)
        $toRetrieve = $this->defaultDataToObtain;

    if ($this->flagArchive) {
        $arrData = json_decode($this->item["{$this->entID}Data"], true);
        $this->item = array_merge($this->item, $arrData);
        return $this->item;
    }
    
    //   - Master table is $this->item
    // attributes and combobox values
    if($this->item["{$this->entID}StatusID"]!==null)
        $this->staID = (int)$this->item["{$this->entID}StatusID"];

    if(in_array('Master', $toRetrieve))
        $this->getEntityItemData();

    if(in_array('Text', $toRetrieve))    
        foreach($this->conf["ATR"] as $atrID=>$rwATR){
            
            if (in_array($rwATR["atrType"], Array("combobox", "ajax_dropdown"))){
                $this->item[$rwATR["atrID"]."_text"] = !isset($this->item[$rwATR["atrID"]."_text"]) 
                    ? $this->getDropDownText($rwATR, $this->item[$rwATR["atrID"]])
                    : $this->item[$rwATR["atrID"]."_text"];
            }

        }
    
    // collect incomplete/cancelled actions
    if(in_array('ACL', $toRetrieve)) {
        $this->item["ACL"]  = Array();
        $sqlACL = "SELECT * FROM stbl_action_log 
                WHERE aclEntityItemID='{$this->entItemID}'
                AND aclActionPhase < 2 
                ORDER BY aclInsertDate DESC, aclOldStatusID DESC";
        $rsACL = $this->oSQL->do_query($sqlACL);
        while($rwACL = $this->oSQL->fetch_array($rsACL)){
            $this->item["ACL"][$rwACL["aclGUID"]] = $this->getActionData($rwACL["aclGUID"]);
        }    
        $this->item["ACL_Cancelled"]  = Array();
        $sqlACL = "SELECT * FROM stbl_action_log 
                WHERE aclEntityItemID='{$this->entItemID}'
                AND aclActionPhase > 2 
                ORDER BY aclInsertDate DESC, aclOldStatusID DESC";
        $rsACL = $this->oSQL->do_query($sqlACL);
        while($rwACL = $this->oSQL->fetch_array($rsACL)){
            $this->item["ACL_Cancelled"][$rwACL["aclGUID"]] = $this->getActionData($rwACL["aclGUID"]);
        }    
    }
    
    
    // collect status log and nested actions
    if(in_array('STL', $toRetrieve)){
        $this->item["STL"] = Array();
        $this->getStatusData(null);
    }
    
    
    //comments
    if(in_array('comments', $toRetrieve)){
        $this->item["comments"] = Array();
        $sqlSCM = "SELECT * 
        FROM stbl_comments 
        WHERE scmEntityItemID='{$this->entItemID}' ORDER BY scmInsertDate DESC";
        $rsSCM = $this->oSQL->do_query($sqlSCM);
        while ($rwSCM = $this->oSQL->f($rsSCM)){
            $this->item["comments"][$rwSCM["scmGUID"]] = $rwSCM;
        }
    }
    
    //files
    if(in_array('files', $toRetrieve)){
        $this->item["files"] = Array();
        $sqlFile = "SELECT * FROM stbl_file WHERE filEntityID='{$this->entID}' AND filEntityItemID='{$this->entItemID}'
        ORDER BY filInsertDate DESC";
        $rsFile = $this->oSQL->do_query($sqlFile);
        while ($rwFIL = $this->oSQL->f($rsFile)){
            $this->item["files"][$rwFIL["filGUID"]] = $rwFIL;
        }
    }
    
    
    //message
    if(in_array('message', $toRetrieve)){
        $this->item["messages"] = Array();//not yet
    }
    
    
    //echo "<pre>";
    //print_r($this->item);
    //die();

    
    return $this->item;
    
}

function delete(){
    
    $oSQL = $this->oSQL;
    
    if ($this->flagArchive){
        $oSQL_arch = $this->intra->getArchiveSQLObject();
        $oSQL_arch->q("DELETE FROM {$this->conf["entTable"]} WHERE {$this->entID}ID=".$oSQL->e($this->entItemID));
        return;
    }
    
    
    $rwEnt = $this->item;
    $entItemID = $this->entItemID;
    $entID = $this->entID;
    
    $sqlDel[] = "DELETE {$this->conf["entTable"]}_log, stbl_action_log FROM {$this->conf["entTable"]}_log INNER JOIN stbl_action_log
        ON aclGUID=l{$entID}GUID WHERE aclEntityItemID='{$entItemID}'";
    $sqlDel[] = "DELETE {$this->conf["entTable"]}_log, stbl_status_log FROM {$this->conf["entTable"]}_log INNER JOIN stbl_status_log
        ON stlGUID=l{$entID}GUID WHERE stlEntityItemID='{$entItemID}'";
    $sqlDel[] = "DELETE FROM stbl_action_log WHERE aclEntityItemID='{$this->entItemID}'";
    $sqlDel[] = "DELETE FROM stbl_status_log WHERE stlEntityItemID='{$this->entItemID}'";
    // comments
    $sqlDel[] = "DELETE FROM stbl_comments WHERE scmEntityItemID=".$oSQL->e($this->entItemID);
    // files
    $sqlDel[] = "DELETE FROM stbl_file WHERE filEntityItemID=".$oSQL->e($this->entItemID);
    // master
    $sqlDel[] = "DELETE FROM {$this->conf["entTable"]} WHERE {$entID}ID='{$this->entItemID}'";
    
    for ($i=0;$i<count($sqlDel);$i++)
        $oSQL->do_query($sqlDel[$i]);
    
}

// 'Creates' existing entity item
public function create(){

    $this->doCreate();

}

// updates master table & action log
// doesn't re-read entity item data from the database
public function update($arrNewData, $flagUpdateMultiple = false, $flagFullEdit = false){
    
    $this->updateMasterTable($arrNewData, $flagUpdateMultiple, $flagFullEdit);
    $this->updateActionLog();
    
    // if no action set with arrNewData we record an update
    if (!isset($arrNewData["actID"]) && !isset($arrNewData["aclGUID"])){
        $this->doUpdate(); // record update operation to the log
    }
    
}

// executes action over entity item
public function doAction($arrNewData = null){
    
    if ($arrNewData!==null){
        $this->refresh(array('Master', 'ACL'));
        $this->arrNewData = $arrNewData;
    }
    
    if (count($this->arrAction)==0){
        $this->prepareActions();
    }

    // proceed with the action
    if ($this->arrAction["actFlagAutocomplete"] && $this->arrAction["aclGUID"]==""){
        
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
    
    $aclGUID = $this->arrAction["aclGUID"];
    
    return $aclGUID;
}

// backward-compatibility 
public function doSimpleAction($arrNewData){
    
    $aclGUID = $this->doAction($arrNewData);
    
    $this->refresh();
    
    return $aclGUID;
    
}

// updates Master Table, Action Log, and add/start/finish/cancel action mentioned in arrNewData[aclToDo]
// returns aclGUID of performed action
public function doFullAction($arrNewData = Array()){
    
    if (count($arrNewData)>0)
        $this->update($arrNewData);
    
    $aclGUID = $this->doAction();
    
    $this->refresh();
    
    return $aclGUID;
    
}

public function doCreate(){
    
    $oSQL = $this->oSQL;

    // check existance
    if($oSQL->d('SELECT COUNT(*) FROM stbl_action_log WHERE aclEntityItemID='.$oSQL->e($this->entItemID).' AND aclActionID=1'))
        throw new Exception('Create action already exists in log for '.$this->entItemID);

    $this->doSimpleAction(Array(  // Create
        "actID"=> 1
    ));

}

public function doUpdate($flagForce = false){

    if(!($this->conf['flagDontLogUpdates'] || $flagForce))
        $this->doSimpleAction(Array(  // Create
            "actID"=> 2
        ));

}

public function doDelete(){

    $this->doSimpleAction(Array(  // Delete
        "actID"=> 3
    ));

}

// adds record to stbl_action_log, {entTable}_log with initial data from Master Table
// returns aclGUID
public function addAction($arrAction = null){
    
    $usrID = $this->intra->usrID;
    $oSQL = $this->oSQL;
    
    if ($arrAction!==null){
        $this->arrAction = $arrAction;
    }
    
    // 1. obtaining aclGUID
    $this->arrAction["aclGUID"] = $oSQL->d("SELECT UUID()");
     
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
              , ".($this->item["{$this->entID}StatusActionLogID"]!="" ? "'".$this->item["{$this->entID}StatusActionLogID"]."'" : "NULL")." as aclPredID
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
            FROM {$this->conf["entTable"]} WHERE {$this->entID}ID='{$this->entItemID}'";
     
    $oSQL->do_query($sqlInsACL);  

    // 3. insert ATV
	// generate script that copy data from the master table
	$arrFields = Array();
    foreach( (array)$this->arrAction["aatFlagToTrack"] as $atrID => $rwAAT ){
        
        // define attributes for timestamp
        if ($rwAAT["aatFlagTimestamp"]) {
            $this->arrAction["acl".$rwAAT["aatFlagTimestamp"]."_attr"] = ($rwAAT["aatFlagEmptyOnInsert"] ? "NULL" : $atrID);
        }
        
		$arrFields["l".$atrID] = ($rwAAT["aatFlagEmptyOnInsert"] 
            ? "NULL" 
            : ($rwAAT['aatFlagUserStamp'] 
                ? $oSQL->e($this->intra->usrID)
                : $atrID)
            );
		
    }
	

    
    if (count($arrFields)!=0){
    
        $sqlInsATV = "INSERT INTO {$this->conf["entTable"]}_log (
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
            FROM {$this->conf["entTable"]} WHERE {$this->entID}ID='{$this->entItemID}'
            ";
            
        $oSQL->do_query($sqlInsATV);
    }
    
    $this->onActionPlan($this->arrAction['actID'], $this->arrAction['aclOldStatusID'], $this->arrAction['aclNewStatusID']);

    return $this->arrAction["aclGUID"];
    
}

public function findAction($aclOldStatusID, $aclNewStatusID, $aclActionID, $aclActionPhase = null, $date=null){
    
    $arrACL = Array();
    
    $sqlACL = "SELECT * FROM stbl_action_log 
        LEFT OUTER JOIN {$this->conf["entTable"]}_log ON aclGUID=l{$this->entID}GUID
        WHERE aclEntityItemID='{$this->entItemID}'
        ".($aclOldStatusID!==null 
            ? " AND aclOldStatusID".(is_array($aclOldStatusID)
                ?  " IN (".implode(", ", $aclOldStatusID).")"
                : "=".(int)$aclOldStatusID
            ) 
            : "").
        ($aclNewStatusID!==null ? " AND aclNewStatusID".(is_array($aclNewStatusID)
                ?  " IN (".implode(", ", $aclNewStatusID).")"
                : "=".(int)$aclNewStatusID
            ) 
            : "").
        ($aclActionID!==null 
            ? " AND aclActionID".(is_array($aclActionID) 
                ?  " IN (".implode(", ", $aclActionID).")"
                : "=".(int)$aclActionID)
            : "").
        ($aclActionPhase!==null 
            ? " AND aclActionPhase".(is_array($aclActionPhase) 
                ?  " IN (".implode(", ", $aclActionPhase).")"
                : "=".(int)$aclActionPhase)
            : "").
        ($date!==null 
            ? " AND IFNULL(aclATD, aclATA) BETWEEN '".$this->intra->getDateTimeByOperationTime($date, (isset($this->intra->conf['stpOperationDayStart']) ? $this->intra->conf['stpOperationDayStart'] : '00:00'))."'".
                " AND '".$this->intra->getDateTimeByOperationTime($date, (isset($this->intra->conf['stpOperationDayEnd']) ? $this->intra->conf['stpOperationDayEnd'] : '23:59:59'))."'"
            : "")."
        ORDER BY aclInsertDate DESC
        ";
       
    $rsACL = $this->oSQL->q($sqlACL);

    while($rwACL = $this->oSQL->f($rsACL)){
        $arrACL[] = $rwACL;
    }
    return $arrACL;
}

function finishAction(){
    $usrID = $this->intra->usrID;
    $oSQL = $this->oSQL;
       
    if ($this->arrAction["aclActionPhase"]=="0"){
        $this->onActionStart($this->arrAction['actID'], $this->arrAction['aclOldStatusID'], $this->arrAction['aclNewStatusID']);
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
        $sqlUpdEntTable = "UPDATE {$this->conf["entTable"]} SET
            {$this->entID}ActionLogID='{$this->arrAction["aclGUID"]}'
            , {$this->entID}EditBy='{$this->intra->usrID}', {$this->entID}EditDate=NOW()";

        // update tracked attributes
        foreach( (array)$this->arrAction["aatFlagToTrack"] as $atrID=>$xx ){
            $sqlUpdEntTable .= "\r\n, {$atrID} = (SELECT l{$atrID} FROM {$this->conf["entTable"]}_log WHERE l{$this->entID}GUID='{$this->arrAction["aclGUID"]}')";
        }

        // update userstamps
        foreach ( (array)$this->arrAction["aatFlagUserStamp"] as $atrID => $xx ) {
            if(array_key_exists($atrID, (array)$this->arrAction["aatFlagToTrack"]))
                continue;
            $sqlUpdEntTable .= "\r\n, {$atrID} = ".$oSQL->e($this->intra->usrID);
        }

        $sqlUpdEntTable .= "\r\n";    
        $sqlUpdEntTable .= "WHERE {$this->entID}ID='{$this->entItemID}'";
        $oSQL->do_query($sqlUpdEntTable);
    }
    
    // update master table by attrbutes
    if (count($this->arrAction["aatFlagToTrack"])>0){
        $sqlUpdMaster = "UPDATE {$this->conf["entTable"]} SET 
            {$this->entID}EditBy='{$this->intra->usrID}', {$this->entID}EditDate=NOW()";
        
        $sqlUpdMaster .= "\r\nWHERE {$this->entID}ID='{$this->entItemID}'";
        $oSQL->do_query($sqlUpdMaster);
    }
    
    $this->onActionFinish($this->arrAction['actID'], $this->arrAction['aclOldStatusID'], $this->arrAction['aclNewStatusID']);

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
        
        $arrSAT = $this->conf['STA'][$this->arrAction['aclNewStatusID']]['satFlagTrackOnArrival'];
        if (count($arrSAT)>0){
            $sqlSAT = "INSERT INTO {$this->conf["entTable"]}_log (
                l{$this->entID}GUID
                , l{$this->entID}EditBy , l{$this->entID}EditDate, l{$this->entID}InsertBy, l{$this->entID}InsertDate
                ";
            foreach($arrSAT as $atrID=>$x)
                $sqlSAT .= ", l{$atrID}";
            $sqlSAT .= ") SELECT 
                '$stlGUID' as l{$this->entID}GUID
                , '{$this->intra->usrID}' as l{$this->entID}EditBy , NOW() as l{$this->entID}EditDate, '{$this->intra->usrID}' as {$this->entID}InsertBy, NOW() as {$this->entID}InsertDate
                ";
            foreach($arrSAT as $atrID=>$ix)
                $sqlSAT .= ", {$atrID}";
            $sqlSAT .= " FROM {$this->conf["entTable"]} WHERE {$this->entID}ID='{$this->entItemID}'";
            $sql[] = $sqlSAT;
        }
        
        // after action is done, we update entity table with last status action log id
        $sql[] = "UPDATE {$this->conf["entTable"]} SET
            {$this->entID}ActionLogID='{$this->arrAction["aclGUID"]}'
            , {$this->entID}StatusActionLogID='{$this->arrAction["aclGUID"]}'
            , {$this->entID}StatusID='{$this->arrAction["aclNewStatusID"]}'
            , {$this->entID}EditBy='{$this->intra->usrID}', {$this->entID}EditDate=NOW()
            WHERE {$this->entID}ID='{$this->entItemID}'";
        
        for($i=0;$i<count($sql);$i++){
            $oSQL->do_query($sql[$i]);
        }

        $this->onStatusArrival($this->arrAction['aclNewStatusID']);

    }
    
}

public function prepareActions(){
    
    $oSQL = $this->oSQL;
    $this->arrAction = Array();
    
    if (empty($this->arrNewData["aclGUID"]) &&  empty($this->arrNewData["actID"])){
        //throw new Exception('Neither Action ID nor Action Log GUID specified');
        $this->arrNewData["actID"] = 2;
    }
    
    if(!$this->item['ACL']){
        $this->getEntityItemAllData(array('ACL'));
    }

    //collect coming action
    if ($this->arrNewData["aclGUID"]){
        if(!$this->arrNewData['isUndo'])
            $rwACT = $this->item['ACL'][$this->arrNewData["aclGUID"]]; //if ACL GUID specified, we try to locate action in ACL
        else {
            $rwACT = $this->getActionData($this->arrNewData["aclGUID"]);
        }

    } else {
        $rwACT = $this->conf['ACT'][$this->arrNewData["actID"]]; // else we retrive action data from ACT associative array member
    }

    if (!$rwACT){
        throw new Exception("Action not found for ID/GUID {$this->arrNewData["actID"]}/{$this->arrNewData["aclGUID"]}");
    }
    
    $aclOldStatusID = isset($this->arrNewData["aclOldStatusID"]) 
        ? $this->arrNewData["aclOldStatusID"] 
        : ($this->arrNewData["aclGUID"] 
            ? $rwACT["aclOldStatusID"] 
            : $this->item["staID"]);
    $aclNewStatusID = isset($this->arrNewData["aclNewStatusID"]) ? $this->arrNewData["aclNewStatusID"] : $rwACT["aclNewStatusID"];
    $this->arrAction["aclComments"] = $this->arrNewData["aclComments"];

    if(!$rwACT['actEntityID']){
        switch($rwACT['actID']){
            case 1:
                if($aclOldStatusID>0)
                    throw new Exception('Item is already created');
                break;
            case 2:
                if(!$this->conf['STA'][$aclOldStatusID]['staFlagCanUpdate'] && !$this->flagFullEdit)
                    throw new Exception('Update is not allowed');
                break;
            case 3:
                if(!$this->conf['STA'][$aclOldStatusID]['staFlagCanDelete'])
                    throw new Exception('Delete is not allowed');
                break;
            default:
                break;
        }
    } else {
        if(!in_array($aclOldStatusID, $rwACT['actOldStatusID'])){
            debug_print_backtrace();
            throw new Exception("Action {$rwACT['actID']} cannot be run for origin status ".$aclOldStatusID);
        }
        if(!in_array($aclNewStatusID, $rwACT['actNewStatusID'])){
            throw new Exception("Action {$rwACT['actID']} cannot be run for destination status ".$aclOldStatusID);
        }
    }

    $this->arrAction['aclOldStatusID'] = $aclOldStatusID;
    $this->arrAction['aclNewStatusID'] = $aclNewStatusID;
    
    $this->arrAction = array_merge($rwACT, $this->arrAction);

    if($timestamp = $this->arrNewData['aclETD'])
        $this->arrAction['aclETD_attr'] = $this->intra->datePHP2SQL($timestamp);
    if($timestamp = $this->arrNewData['aclETA'])
        $this->arrAction['aclETA_attr'] = $this->intra->datePHP2SQL($timestamp);
    if($timestamp = $this->arrNewData['aclATD'])
        $this->arrAction['aclATD_attr'] = $this->intra->datePHP2SQL($timestamp);
    if($timestamp = $this->arrNewData['aclATA'])
        $this->arrAction['aclATA_attr'] = $this->intra->datePHP2SQL($timestamp);

    if (is_array($this->arrAction["aatFlagToTrack"]))
        foreach($this->arrAction["aatFlagToTrack"] as $atrID=>$options){
            // define attributes for timestamp
            $arrTS = $this->conf['ACT'][$rwACT['actID']]['aatFlagTimestamp'];
            if (in_array($atrID, $arrTS)) {
                $this->arrAction["acl".array_search($atrID, $arrTS)."_attr"] = ($options["aatFlagEmptyOnInsert"] ? "NULL" : $atrID);
            }
        }

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



function updateMasterTable($arrNewData = Array(), $flagUpdateMultiple = false, $flagFullEditMode = false){
    
    if (count($arrNewData)>0)
        $this->arrNewData = $arrNewData;
    
    if (count($this->arrNewData)==0){
        throw new Exception($intra->translate("New data set is empty for {$this->entID}/{$this->entItemID}"));
    }
    
    $oSQL = $this->oSQL;
    
    $entID = $this->entID;
    $entItemID = $this->entItemID;
    $rwEnt = $this->item;
    
    $intra = $this->intra;
    
    
    // 1. update table by visible/editable attributes list   
    $atrToUpd = Array();
    $strFieldList = "";

    $arrATRToLoop = ($flagFullEditMode 
        ? $this->conf['ATR']
        : (is_array($this->conf['STA'][$this->item['staID']]['satFlagEditable']) 
            ? $this->conf['STA'][$this->item['staID']]['satFlagEditable']
            : array()
            )
        );
    
    foreach ($arrATRToLoop as $atrID=>$FlagWrite){

        $rwSAT = $this->conf['ATR'][$atrID];

        if(!$rwSAT)
            continue;

        if ($rwSAT['atrFlagDeleted'] && !$flagFullEditMode)
            continue;
        
        if ((!$FlagWrite && !$flagFullEditMode)                                                      // not editable
            || ($arrNewData[$atrID]=="" && $flagUpdateMultiple)       // empty on multiple updates
            || !isset($arrNewData[$rwSAT["atrID"]]))                           // not set
        continue;
        
        $toEval = "\"".str_replace("\$_POST", "\$this->arrNewData", $intra->getSQLValue(Array('Field'=>$rwSAT['atrID'], 'DataType'=>$rwSAT['atrType'])))."\"";
        eval("\$newValue = ".$toEval.";");
        
        if ($newValue!=$rwEnt[$rwSAT["atrID"]]){
            $strFieldList .= "\r\n, `{$rwSAT["atrID"]}`={$newValue}";
        }
        
        if ($rwSAT["atrUOMTypeID"]){
            $strFieldList .= ", {$rwSAT["atrID"]}_uomID=".($this->arrNewData["{$rwSAT["atrID"]}_uomID"]
                ? $oSQL->e($this->arrNewData["{$rwSAT["atrID"]}_uomID"])
                : $oSQL->e($oSQL->d("SELECT uomID FROM stbl_uom WHERE uomType='{$rwSAT['atrUOMTypeID']}' AND uomRateToDefault=1.0 LIMIT 0,1"))
                );
        }
    }
    
    $sqlUpdateTable = "UPDATE {$this->conf["entTable"]} SET
        {$entID}EditDate=NOW(), {$entID}EditBy='{$this->intra->usrID}'
        {$strFieldList}
        WHERE {$entID}ID='{$entItemID}'";
    $oSQL->do_query($sqlUpdateTable);
    
}

function updateActionLog($arrNewData = Array()){
    
    if (count($arrNewData)>0)
        $this->arrNewData = $arrNewData;
    
    if (count($this->arrNewData)==0){
        throw new Exception($intra->translate("New data set is empty for {$this->entID}/{$this->entItemID}"));
    }
    
    if (count($this->arrAction)==0){
        $this->prepareActions();
    }

    foreach($this->item['ACL'] as $aclGUID => $arrACL){
        $this->updateActionLogItem($aclGUID, $arrACL);
    }

}

function updateActionLogItem($aclGUID, $arrACL = null){
    
    $oSQL = $this->oSQL;
    $intra = $this->intra;
    
    $strEntityLogFldToSet = "";
    $tsfieldsToSet = "";
    
    if ($arrACL===null){
        $arrACL = $this->getActionData($aclGUID);
    }
    
    foreach ($arrACL["AAT"] as $atrID => $arrAAT){
        
        $newValue = null;
        $strACLInputID = $atrID."_".$aclGUID;

        if($arrAAT['aatFlagUserStamp']){
            $strEntityLogFldToSet .= ", l{$atrID} = ".$oSQL->e($this->intra->usrID);
            continue;
        }
        
        // if we have it in arrNewData: atrID_aclGUID
        if (isset($this->arrNewData[$strACLInputID])   ) {
            $toEval = "\"".str_replace("\$_POST", "\$this->arrNewData"
                , $this->intra->getSQLValue(Array('Field'=>$strACLInputID, 'DataType'=>$this->conf['ATR'][$atrID]["atrType"])))."\"";
            eval("\$newValue = ".$toEval.";");
        }

        if ($key = array_search($atrID, $arrACL["aatFlagTimestamp"])){

            $strTimeStampInputID = "acl".$key."_".$aclGUID;

            // if attribute is a timestamp:
            if(isset($this->arrNewData[$strTimeStampInputID])){ // if we timestamp in arrNewData, we update attribute field in log table
                $toEval = "\"".str_replace("\$_POST", "\$this->arrNewData"
                    , $this->intra->getSQLValue(Array('Field'=>$strTimeStampInputID, 'DataType'=>(!$arrACL['actTrackPrecision'] ? 'datetime' : $arrACL['actTrackPrecision']))))."\"";
                eval("\$newValue = ".$toEval.";");
            } else { // if there's no timestamp, we try to update ACL timestamp basing on 
                if ($newValue!==null){
                    $tsfieldsToSet .= "\r\n, acl{$key}={$newValue}";
                    if($arrACL["actFlagDepartureEqArrival"] && $key=="ATA"){
                        $tsfieldsToSet .= "\r\n, aclATD={$newValue}";
                    }
                }
            }
        }
        
        if ($newValue!==null){
            $strEntityLogFldToSet .= ", l{$atrID} = {$newValue}";
        }
        
    }
    $sqlToTrack = "UPDATE {$this->conf["entTable"]}_log SET 
                l{$this->entID}EditBy='{$this->intra->usrID}', l{$this->entID}EditDate=NOW()
                {$strEntityLogFldToSet}
            WHERE l{$this->entID}GUID='{$aclGUID}'";
    $this->oSQL->q($sqlToTrack);    
        
        
    foreach($this->arrTimestamp as $ts){
        //echo "acl".$ts."_".$aclGUID." ";
        if (isset($this->arrNewData["acl".$ts."_".$aclGUID])){
            $toEval = "\"".str_replace("\$_POST", "\$this->arrNewData"
                        , $this->intra->getSQLValue(Array('Field'=>"acl".$ts."_".$aclGUID, 'DataType'=>(!$arrACL['actTrackPrecision'] ? 'datetime' : $arrACL['actTrackPrecision']))))."\"";
                    eval("\$newValue = ".$toEval.";");
            $tsfieldsToSet .= "\r\n, acl{$ts}={$newValue}";
        }
    }
    
    $sqlToTrack = "UPDATE stbl_action_log SET aclEditBy='{$this->intra->usrID}'
       , aclEditDate=NOW()
       {$tsfieldsToSet}
       WHERE aclGUID='{$aclGUID}'";
    $this->oSQL->q($sqlToTrack);

    
}

function updateStatusLogItem($stlGUID, $arrSTL = null){
    
    $oSQL = $this->oSQL;
    $intra = $this->intra;
    
    if($arrSTL===null){
        foreach($entItem->rwEnt["STL"] as $stlGUID_ => $rwSTL){
            if ($stlGUID_==$stlGUID){
                $arrSTL = $rwSTL;
                break;
            }
        }
    }

    $newATA = $intra->datetimePHP2SQL($this->arrNewData['stlATA_'.$stlGUID]); 
    $newATD = $intra->datetimePHP2SQL($this->arrNewData['stlATD_'.$stlGUID]); 
    if ($intra->unq($newATA)!==$arrSTL['stlATA'] || $intra->unq($newATD)!==$arrSTL['stlATD']){

        $sqlSTL = "UPDATE stbl_status_log SET
                stlATA =  {$newATA}
                , stlATD =  {$newATD}
                , stlEditBy =  '{$this->intra->usrID}', stlEditDate = NOW()
            WHERE `stlGUID` = ".$oSQL->e($stlGUID);
        $this->oSQL->q($sqlSTL);

    }

    $strFieldsToUpdate = '';
    if(isset($arrSTL['SAT']))
    foreach($arrSTL['SAT'] as $atrID=>$rwSAT){
        $newValue = null;
        $inputName = $atrID.'_'.$stlGUID;
        if (isset($this->arrNewData[$inputName])){
            $toEval = "\"".str_replace("\$_POST", "\$this->arrNewData"
                    , $this->intra->getSQLValue(Array('Field'=>$inputName, 'DataType'=>$rwSAT['atrType'])))."\"";
            eval("\$newValue = ".$toEval.";");
            if($intra->unq($newValue)==$rwSAT['value']) $newValue = null;
        }
        if ($newValue!==null){
            $strFieldsToUpdate .= ", l{$atrID} = {$newValue}";
        }
    }

    if($strFieldsToUpdate){
        $sqlLog = "UPDATE {$this->conf['entTable']}_log SET 
            l{$this->entID}EditBy='{$this->intra->usrID}', l{$this->entID}EditDate=NOW()
                {$strFieldsToUpdate}
            WHERE l{$this->entID}GUID='{$stlGUID}'";
        $this->oSQL->q($sqlLog);
    }
    
}

function checkMandatoryFields(){
    
    $oSQL = $this->oSQL;
    
    $entID = $this->entID;
    $entItemID = $this->entItemID;
    $entTable = $this->conf["entTable"];
    $rwEnt = $this->item;
    $flagAutocomplete = $this->arrAction["actFlagAutocomplete"];
    $aclGUID = $this->arrAction["aclGUID"];

    if(is_array($this->arrAction["aatFlagMandatory"]))
        foreach($this->arrAction["aatFlagMandatory"] as $atrID => $rwATR){
                
            $oldValue = $this->item[$atrID];
            
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
                $sqlCheckChanges = "SELECT 
                    CASE WHEN IFNULL(l{$atrID}, '')=".$oSQL->escape_string($oldValue)." THEN 0 ELSE 1 END as changedOK 
                    FROM {$entTable}_log
                    WHERE l{$entID}GUID='{$aclGUID}'";
            }
            
            if (!$oSQL->get_data($oSQL->do_query($sqlCheckMandatory))){
                throw new Exception("Mandatory field '{$this->conf['ATR'][$atrID]["atrTitle"]}' is not set for {$entItemID}");
                die();
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
    
	$entID = $this->conf["entID"];
    $entItemID = $this->item[$entID."ID"];
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
    
    $sqlUpdACL = "UPDATE stbl_action_log SET 
        aclActionPhase=1
        , aclStartBy='{$this->intra->usrID}', aclStartDate=NOW()
        , aclEditBy='{$this->intra->usrID}', aclEditDate=NOW()
    WHERE aclGUID='{$this->arrAction["aclGUID"]}'";
    $oSQL->do_query($sqlUpdACL);
    
    $sqlUpdEntTable = "UPDATE {$this->conf["entTable"]} SET
            {$entID}ActionLogID='{$this->arrAction["aclGUID"]}'
            , {$entID}EditBy='{$this->intra->usrID}', {$entID}EditDate=NOW()
            WHERE {$entID}ID='{$entItemID}'";
    $oSQL->do_query($sqlUpdEntTable);

    $this->onActionStart($this->arrAction['actID'], $this->arrAction['aclOldStatusID'], $this->arrAction['aclNewStatusID']);

    if (((string)$this->arrAction["aclOldStatusID"]!=(string)$this->arrAction["aclNewStatusID"] 
          && (string)$this->arrAction["aclNewStatusID"]!=""
        )
        || $this->arrAction["actFlagInterruptStatusStay"]){

        $this->onStatusDeparture($this->arrAction['aclOldStatusID']);

    }
    
}


function cancelAction(){

    $usrID = $this->intra->usrID;

    $entID = $this->entID;
    $entItemID = $this->entItemID;

    if (count($this->arrAction)==0){
        $this->prepareActions();
    }
    
    if ($this->arrAction["aclActionPhase"]>0){

        //get last stl for action
        $stlToDelete = $this->oSQL->get_data($this->oSQL->do_query("SELECT stlGUID FROM stbl_status_log WHERE stlEntityID='{$this->entID}' 
            AND stlEntityItemID='{$this->entItemID}' 
            AND stlArrivalActionID='{$this->arrAction["aclGUID"]}'"));

        //delete traced attributes for the STL
        $this->oSQL->q("DELETE FROM {$this->conf["entTable"]}_log WHERE l{$this->entID}GUID='{$stlToDelete}'");

        // delete status log entry, if any
        $this->oSQL->q("DELETE FROM stbl_status_log WHERE stlGUID='{$stlToDelete}'");

        
        if ($this->arrAction['aclOldStatusID']!==null && $this->arrAction['aclOldStatusID']!=='0'){ // if old status wasn't draft or nowhere

            //get full previous stl
            $sqlSTLLast = "SELECT * FROM stbl_status_log WHERE stlEntityID='{$this->entID}' 
                AND stlEntityItemID='{$this->entItemID}' 
                AND stlDepartureActionID='{$this->arrAction["aclGUID"]}'";
            $rwSTLLast = $this->oSQL->fetch_array($this->oSQL->do_query($sqlSTLLast));

            // update departure action for previous status log entry
            $this->oSQL->q("UPDATE stbl_status_log SET stlATD=NULL, stlDepartureActionID=NULL WHERE stlGUID='{$rwSTLLast["stlGUID"]}'");

        }

        if (empty($this->arrNewData["isUndo"])) {
            if ($this->arrAction["aclActionPhase"]>=2){ // if action is already finish and it's not UNDO, it's cancel
                throw new Exception('Action cannot be cancelled, it is already complete');
            }
            //cancel the action
            $this->oSQL->q("UPDATE stbl_action_log SET aclActionPhase=3
                , aclEditBy='{$this->intra->usrID}'
                , aclEditDate = NOW()
                WHERE aclGUID='{$this->arrAction["aclGUID"]}'");
            
            $this->onActionCancel($this->arrAction['actID'], $this->arrAction['aclOldStatusID'], $this->arrAction['aclNewStatusID']);

        } else {
            //delete the action
            $this->oSQL->q("DELETE FROM {$this->conf["entTable"]}_log 
                WHERE l{$this->entID}GUID='{$this->arrAction["aclGUID"]}'");
                
            $this->oSQL->q("DELETE FROM stbl_action_log
                WHERE aclGUID='{$this->arrAction["aclGUID"]}'");

            $this->onActionUndo($this->arrAction['actID'], $this->arrAction['aclOldStatusID'], $this->arrAction['aclNewStatusID']);
        }

        // update entity table
        $this->oSQL->q("UPDATE {$this->conf["entTable"]} SET
            {$this->entID}ActionLogID=(SELECT aclGUID FROM stbl_action_log INNER JOIN stbl_action ON aclActionID=actID AND actEntityID='{$this->entID}' 
                  WHERE aclEntityItemID='{$entItemID}' AND aclActionID<>2 AND aclActionPhase=2 
                  ORDER BY aclATA DESC LIMIT 0,1)
            ".($stlToDelete != ""
                ? " , {$this->entID}StatusActionLogID = ".($this->arrAction['aclOldStatusID']==='0' 
                        ? "(SELECT aclGUID FROM stbl_action_log WHERE aclActionID=1 AND aclEntityItemID=".$this->oSQL->e($entItemID).")"
                        : ($rwSTLLast['stlGUID'] ? $this->oSQL->e($rwSTLLast['stlGUID']) : 'NULL') )."
                    , {$this->entID}StatusID = ".($this->arrAction['aclOldStatusID']===null ? 'NULL' : (int)$this->arrAction['aclOldStatusID'])
                : ""
            )."
            , {$this->entID}EditBy='{$this->intra->usrID}', {$this->entID}EditDate=NOW()
            WHERE {$this->entID}ID='{$entItemID}'");

    } else {
        $this->oSQL->q("DELETE FROM {$this->conf["entTable"]}_log 
           WHERE l{$this->entID}GUID='{$this->arrAction["aclGUID"]}'");
        //we delete action itself
        $this->oSQL->q("DELETE FROM stbl_action_log WHERE aclGUID='{$this->arrAction["aclGUID"]}'");
    }

}

/**
 * Function to obtain user ID who run action $actID last time.
 * 
 * @return user ID that could be found in stbl_user. If action not found, it returns NULL
 *
 * @package eiseIntra
 */
function whoRunAction($actID){
    foreach($this->item['STL'] as $stlGUID=>$arrSTL){
        if($arrSTL['stlArrivalAction']['aclActionID']==$actID){
            return $arrSTL['stlArrivalAction']['aclFinishBy'];
        }
    }
    return null;
}

/**
 * Function to obtain user ID who last time lead the item to the status specified in $staID.
 * 
 * @return user ID that could be found in stbl_user. If status not found, it returns NULL
 *
 * @package eiseIntra
 */
function whoLeadToStatus($staID){
    foreach($this->item['STL'] as $stlGUID=>$arrSTL){
        if($arrSTL['stlStatusID']==$staID){
            return $arrSTL['stlInsertBy'];
        }
    }
    return null;
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
static function addComment($arrCommentData){
    
    GLOBAL $intra;
    $oSQL = $intra->oSQL;
    $usrID = $intra->usrID;

    $oSQL->do_query("SET @scmGUID=UUID()");
    
    $sqlIns = "INSERT INTO stbl_comments (
         scmGUID
         , scmEntityItemID
         , scmAttachmentID
         , scmContent
         , scmInsertBy, scmInsertDate, scmEditBy, scmEditDate
         ) VALUES (
         @scmGUID
         , ".($arrCommentData['scmEntityItemID']!="" ? "'".$arrCommentData['scmEntityItemID']."'" : "NULL")."
         , ".($arrCommentData['scmAttachmentID']!="" ? "'".$arrCommentData['scmAttachmentID']."'" : "NULL")."
         , ".$oSQL->escape_string($arrCommentData['scmContent'])."
         , '{$intra->usrID}', NOW(), '{$intra->usrID}', NOW());";
    $oSQL->do_query($sqlIns);
     
    $scmGUID = $oSQL->get_data($oSQL->do_query("SELECT @scmGUID as scmGUID"));
    
    return $scmGUID; 

}

static function updateComments($DataAction){

GLOBAL $intra;

$oSQL = $intra->oSQL;
$usrID = $intra->usrID;

switch ($DataAction) {
    case "delete_comment":
       $oSQL->do_query("DELETE FROM stbl_comments WHERE scmGUID='{$_GET["scmGUID"]}'");
        header("Content-Type: application/json; charset=UTF-8");
        echo json_encode(Array("Comment deleted"));
       die();
       break;
    case "add_comment":
        
        self::addComment($_GET);
       
        $arrData = Array("scmGUID"=>$scmGUID
            , "user"=>$intra->getUserData($intra->usrID).' '.$intra->translate('at').' '
                .date("d.m.Y H:i"));
        
        echo json_encode($arrData);
       
        die();
}
}

/***********************************************************************************/
/* File Attachment Routines                                                        */
/***********************************************************************************/
static function updateFiles($DataAction){
    
    GLOBAL $intra;
    
    $oSQL = $intra->oSQL;
    
    $usrID = $intra->usrID;
    $arrSetup = $intra->conf;
    
    $da = isset($_POST["DataAction"]) ? $_POST["DataAction"] : $_GET["DataAction"];
    
switch ($da) {
    case "deleteFile":
        $oSQL->q("START TRANSACTION");
        $rwFile = $oSQL->fetch_array($oSQL->do_query("SELECT * FROM stbl_file WHERE filGUID='{$_GET["filGUID"]}'"));

        $filesPath = self::checkFilePath($arrSetup["stpFilesPath"]);

        unlink($filesPath.$rwFile["filNamePhysical"]);
        $oSQL->do_query("DELETE FROM stbl_file WHERE filGUID='{$_GET["filGUID"]}'");
        $oSQL->q("COMMIT");
        
        SetCookie("UserMessage", "File deleted");
        header("Location: ".$_GET["referer"]);
        die();
        break;
    case "attachFile":
        
        $entID = $_POST["entID_Attach"];
        
        $oSQL->q("START TRANSACTION");
        
        $fileGUID = $oSQL->d("SELECT UUID() as GUID");
        $filename = Date("Y/m/").$fileGUID.".att";
                            
        //saving the file
        $filesPath = self::checkFilePath($arrSetup["stpFilesPath"]);

        if(!file_exists($filesPath.Date("Y/m")))
            mkdir($filesPath.Date("Y/m"), 0777, true);
        
        copy($_FILES["attachment"]["tmp_name"], $filesPath.$filename);
        
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
        
        $oSQL->q($sqlFileInsert);
        
        $oSQL->q("COMMIT");
        
        SetCookie("UserMessage", $intra->translate("File uploaded"));
        header("Location: ".$_SERVER["PHP_SELF"]."?{$entID}ID=".urlencode($_POST["entItemID_Attach"]));
        die();
    default: break;
}

}

static function checkFilePath($filesPath){
    if(!$filesPath)
        throw new Exception('File path not set');

    if($filesPath[strlen($arrSetup['stpFilesPath'])-1]!=DIRECTORY_SEPARATOR)
        $filesPath=$filesPath.DIRECTORY_SEPARATOR;

    if(!is_dir($filesPath))
        throw new Exception('File path '.$filesPath.' is not a directory');

    return $filesPath;
}

static function getFile($filGUID, $filePathVar = 'stpFilesPath'){

    GLOBAL $intra;
    $oSQL = $intra->oSQL;

    $sqlFile = "SELECT * FROM stbl_file WHERE filGUID=".$oSQL->e($filGUID);
    $rsFile = $oSQL->do_query($sqlFile);

    if ($oSQL->n($rsFile)==0)
        throw new Exception('File '.$filGUID.' not found');

    $rwFile = $oSQL->fetch_array($rsFile);

    $filesPath = self::checkFilePath($intra->conf[$filePathVar]);

    header("Content-Type: ".$rwFile["filContentType"]);
    if(headers_sent())
        $this->Error('Some data has already been output, can\'t send file');
    header("Content-Length: ".$rwFile["filLength"]);
    header('Content-Disposition: inline; filename='.urlencode($rwFile["filName"]) );
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    ini_set('zlib.output_compression','0');

    $fh = fopen($filesPath.$rwFile["filNamePhysical"], "rb");
    echo fread($fh, $rwFile["filLength"]);
    fclose($fh);

}

/***********************************************************************************/
/* Message Routines                                                                */
/***********************************************************************************/
static function updateMessages($newData){

    GLOBAL $intra;

    $oSQL = $intra->oSQL;

    $da = $newData["DataAction"];

    switch($da){
        case 'messageSend':
            $sqlMsg = "INSERT INTO stbl_message SET
                msgEntityID = ".($newData['entID']!="" ? $oSQL->e($newData['entID']) : "NULL")."
                , msgEntityItemID = ".($newData['entItemID']!="" ? $oSQL->e($newData['entItemID']) : "NULL")."
                , msgFromUserID = '$intra->usrID'
                , msgToUserID = ".($newData['msgToUserID']!="" ? $oSQL->e($newData['msgToUserID']) : "NULL")."
                , msgCCUserID = ".($newData['msgCCUserID']!="" ? $oSQL->e($newData['msgCCUserID']) : "NULL")."
                , msgSubject = ".$oSQL->e($newData['msgSubject'])."
                , msgText = ".$oSQL->e($newData['msgText'])."
                , msgSendDate = NULL
                , msgReadDate = NULL
                , msgFlagDeleted = 0
                , msgInsertBy = '$intra->usrID', msgInsertDate = NOW(), msgEditBy = '$intra->usrID', msgEditDate = NOW()";

            $oSQL->q($sqlMsg);
            
            if($newData["entItemID"]!='' && $newData['entID']!='')
                $intra->redirect($intra->translate('Message sent'), $_SERVER["PHP_SELF"]."?{$newData['entID']}ID=".urlencode($newData["entItemID"]));
            break;

        case 'messageReply':
            die();
        case 'messageReplyAll':
            die();
    }

}

static function sendMessages($conf){
    
    GLOBAL $intra;

    $oSQL = $intra->oSQL;

    // scan tablefor unsent messages
    $sqlMsg = "SELECT * 
        FROM stbl_message 
            LEFT OUTER JOIN stbl_entity ON msgEntityID=entID
        WHERE msgSendDate IS NULL ORDER BY msgInsertDate DESC";
    $rsMsg = $oSQL->q($sqlMsg);

    include_once('../common/eiseMail/inc_eisemail.php');

    $sender  = new eiseMail($conf);

    while($rwMsg = $oSQL->f($rsMsg)){

        $rwUsr_From = $intra->getUserData_All($rwMsg['msgFromUserID'], 'all');
        $rwUsr_To = $intra->getUserData_All($rwMsg['msgToUserID'], 'all');
        $rwUsr_CC = $intra->getUserData_All($rwMsg['msgCCUserID'], 'all');

        $rwMsg['system'] = $conf['system'];

        if($rwMsg['msgEntityItemID']!='' && $rwMsg['msgEntityID']!=''){

            $rwMsg[self::getItemIDField($rwMsg)] = $rwMsg['msgEntityItemID'];

            $rwMsg['entItemFormHref'] = eiseIntra::getFullHREF(self::getFormURL($rwMsg, $rwMsg));    

        }

        $msg = array('mail_from'=> ($rwUsr_From['usrName'] ? "\"".$rwUsr_From['usrName']."\"  <".$rwUsr_From['usrEmail'].">" : '')
            , 'rcpt_to' => ($rwUsr_To['usrName'] ? "\"".$rwUsr_To['usrName']."\"  <".$rwUsr_To['usrEmail'].">" : '')
            , 'Subject' => $rwMsg['msgSubject']
            , 'Text' => $rwMsg['msgText']
            );
        if ($rwMsg['msgCCUserID'])
            $msg['CC'] = "\"".$rwUsr_CC['usrName']."\"  <".$rwUsr_CC['usrEmail'].">";

        $msg = array_merge($msg, $rwMsg);

        $sender->addMessage($msg);

    }

    try {
        $arrMessages = $sender->send();
    } catch (eiseMailException $e){
        $strError = $e->getMessage();
        $arrMessages = $e->getMessages();
    }


    foreach($arrMessages as $msg){
        $sqlMarkSent = "UPDATE stbl_message SET msgSendDate=".($msg['send_time'] ? "'".date('Y-m-d H:i:s', $msg['send_time'])."'" : 'NULL' )."
            , msgStatus=".($msg['error'] ? $oSQL->e($msg['error']) : $oSQL->e('Sent'))."
            , msgEditDate=NOW()
            , msgEditBy='{$intra->usrID}'
            WHERE msgID=".$oSQL->e($msg['msgID']);
        $oSQL->q($sqlMarkSent);

    }

    if($strError)
        throw new Exception($strError);

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
    $strData = json_encode($this->item);
	
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
    
	$sqlIns = "INSERT IGNORE INTO `{$this->conf["entTable"]}` (
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
		".$oSQL->e($this->item[$this->entID."ID"])."
		, ".(int)($this->item[$this->entID."StatusID"])."
		, ".$oSQL->e($this->item["staTitle"])."
		, ".$oSQL->e($this->item["staTitleLocal"])."
		, ".$oSQL->e($oSQL->d("SELECT aclATA FROM stbl_action_log WHERE aclGUID=".$oSQL->e($this->item["{$this->entID}StatusActionLogID"])))."
		, ".$oSQL->e($strData)."
		, '{$intra->usrID}', NOW(), '{$intra->usrID}', NOW()";
	foreach ($this->arrATR as $atrID => $rwATR){
		switch ($rwATR["atrType"]){
			case "combobox":
			case "ajax_dropdown":
				$val = $oSQL->e($this->item[$atrID."_text"]);
				break;
			case "number":
			case "numeric":
			case "date":
			case "datetime":
				$val = ($this->item[$atrID]!="" ? $oSQL->e($this->item[$atrID]) : "NULL");
				break;
			case "boolean":
				$val = (int)$this->item[$atrID];
				break;
			default:
				$val = $oSQL->e($this->item[$atrID]);
				break;
		}
		$sqlIns .= "\r\n, {$val}";
	}
	$sqlIns .= ")";
	
	$oSQL_arch->q($sqlIns);
	
	//echo "<pre>";
	//echo "{$sqlIns}";
	//print_r($this->item);    
	
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
    $this->arrMaster = $intra->getTableInfo($oSQL->dbname, $this->conf["entTable"]);
    
    $strFields = "";
    $strValues = "";
    foreach($this->arrMaster['columns'] as $ix =>  $col){
        $strFields .= ($strFields!="" ? "\r\n, " : "")."`{$col['Field']}`";
        $strValues .= ($strValues!="" ? "\r\n, " : "").(!isset($this->item[$col['Field']]) || is_null($this->item[$col['Field']]) 
            ? "NULL"
            : $oSQL->e($this->item[$col['Field']]));
    }
    $sqlIns = "INSERT IGNORE INTO {$this->conf['entTable']} ({$strFields}
        ) VALUES ( {$strValues} )";
    $oSQL->q($sqlIns);
    
    // restore action log
    foreach($this->item["ACL"] as $rwAct){
        $this->restoreAction($rwAct);
    }
    
    foreach($this->item["STL"] as $rwSTL){
        $this->restoreStatus($rwSTL);
    }
    
    
    // restore comments
    foreach($this->item["comments"] as $rwComment){
        $this->restoreComment($rwComment);
    }
    
    // restore files
    foreach($this->item["files"] as $rwFile){
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
        
    $oSQL->q("INSERT IGNORE INTO {$this->conf["entTable"]}_log (l{$this->entID}GUID) VALUES (".$oSQL->e($logGUID).")");
    
    $sqlFields = "";
    foreach($arrATR as $rwATR){
        if (in_array($rwATR["atrID"], $this->arrMaster["columns_index"]))
            $strFields .= ($strFields=="" ? "" : "\r\n, ")."l{$rwATR["atrID"]} = ".(is_null($rwATR['value']) ? "NULL" : $oSQL->e($rwATR['value']));
    }
    
    $sqlUpd = "UPDATE {$this->conf["entTable"]}_log SET {$strFields}
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
    
    $sqlACT = "SELECT ACL.*
       FROM stbl_action_log ACL
       WHERE aclGUID='{$aclGUID}'";
	
	$rwACT = $oSQL->fetch_array($oSQL->do_query($sqlACT));

    $rwACT = @array_merge($this->conf['ACT'][$rwACT['aclActionID']], $rwACT);
    $rwACT = @array_merge($rwACT, array(
        'staID_Old' => $this->conf['STA'][$rwACT['aclOldStatusID']]['staID']
        , 'staTitle_Old' => $this->conf['STA'][$rwACT['aclOldStatusID']]['staTitle']
        , 'staTitleLocal_Old' => $this->conf['STA'][$rwACT['aclOldStatusID']]['staTitleLocal']
        ));
    $rwACT = @array_merge($rwACT, array(
        'staID_New' => $this->conf['STA'][$rwACT['aclNewStatusID']]['staID']
        , 'staTitle_New' => $this->conf['STA'][$rwACT['aclNewStatusID']]['staTitle']
        , 'staTitleLocal_New' => $this->conf['STA'][$rwACT['aclNewStatusID']]['staTitleLocal']
        ));
    
	$arrRet = $rwACT;
	
	// linked attributes
	$arrAAT = $this->conf['ACT'][$rwACT["actID"]]['aatFlagToTrack'];

    if(count($arrAAT)>0){
        $sqlLOG = "SELECT * FROM {$this->conf["entTable"]}_log WHERE l{$entID}GUID='{$rwACT["aclGUID"]}'";
        $rsLOG = $oSQL->do_query($sqlLOG);
            $rwLOG = $oSQL->fetch_array($rsLOG);
            
        foreach($arrAAT as $atrID=>$options){
            $arrATR = $this->conf['ATR'][$atrID];
            $arrVal = Array("value" => $rwLOG["l".$arrATR["atrID"]]);
            if (in_array($arrATR["atrType"], Array("combobox", "ajax_dropdown")))
                $arrVal["text"] = $this->getDropDownText($arrATR, $rwLOG["l".$arrATR["atrID"]]);
            $arrRet["AAT"][$atrID] = $arrVal;
        }
    }
    
	return $arrRet;
    
}

function getStatusData($stlDepartureActionID){
	
    static $nIterations;

    $nIterations = ($stlDepartureActionID===null ? 0 : $nIterations);

	$oSQL = $this->oSQL;
    $entID = $this->entID;
	
	$arrRet = Array();
	
	$sqlSTL = "SELECT STL.*
            , STL_PREVS.stlDepartureActionID AS stlDepartureActionID_prevs
        FROM stbl_status_log STL
        LEFT OUTER JOIN stbl_status_log STL_PREVS 
            ON STL.stlEntityItemID=STL_PREVS.stlEntityItemID 
                AND STL.stlEntityID=STL_PREVS.stlEntityID 
                AND STL.stlArrivalActionID=STL_PREVS.stlDepartureActionID
		WHERE STL.stlEntityItemID=".$oSQL->e($this->entItemID)." AND STL.stlEntityID=".$oSQL->e($this->entID)."
            AND IFNULL(STL.stlArrivalActionID, '')<>IFNULL(STL.stlDepartureActionID,'')
            AND STL.stlDepartureActionID ".($stlDepartureActionID===null ? "IS NULL" : "='{$stlDepartureActionID}'");
	$rsSTL = $oSQL->do_query($sqlSTL);
	if ($oSQL->n($rsSTL) == 0) return Array();
	
	$rwSTL = $oSQL->f($rsSTL);

    $rwSTL = @array_merge($this->conf['STA'][$rwSTL['stlStatusID']],  $rwSTL);
		
	$arrRet = $rwSTL;
	
	$stlATD = ($rwSTL["stlATD"]=="" ? date("Y-m-d") : $rwSTL["stlATD"]);   
	$sqlNAct = "SELECT aclGUID FROM stbl_action_log 
	   WHERE (DATE(aclATA) BETWEEN DATE('{$rwSTL["stlATA"]}') AND DATE('{$stlATD}'))
		 AND aclOldStatusID='{$rwSTL["stlStatusID"]}'
		 AND aclOldStatusID=aclNewStatusID
	   AND aclActionPhase=2
	   AND aclActionID<>2
	   AND aclEntityItemID='{$this->entItemID}'
	   ORDER BY aclInsertDate DESC";
	//echo "<pre>".$sqlNAct."</pre>";
	$rsNAct = $oSQL->do_query($sqlNAct);
	while ($rwNAct = $oSQL->fetch_array($rsNAct)){
		$arrRet["ACL"][$rwNAct["aclGUID"]] = $this->getActionData($rwNAct["aclGUID"]);
	}
	
	$sqlLOG = "SELECT * FROM {$this->conf["entTable"]}_log WHERE l{$entID}GUID='{$rwSTL["stlGUID"]}'";
	$rsLOG = $oSQL->do_query($sqlLOG);
	if ($oSQL->n($rsLOG) > 0){
		$rwLOG = $oSQL->fetch_array($rsLOG);
        $arrTrackOnArrival = $this->conf['STA'][$rwSTL["staID"]]['satFlagTrackOnArrival'];
        if(is_array($arrTrackOnArrival))
    		foreach($arrTrackOnArrival as $atrID){

                $arrATR = $this->conf['ATR'][$atrID];
                
    			$arrVal = Array("value" => $rwLOG["l{$atrID}"]);
    			if (in_array($arrATR["atrType"], Array("combobox", "ajax_dropdown")))
    				$arrVal["text"] = $this->getDropDownText($arrATR, $rwLOG["l{$atrID}"]);
    			$arrRet["SAT"][$atrID] = $arrVal;
    		}
	}
	
	$arrRet["stlArrivalAction"] = $this->getActionData($rwSTL["stlArrivalActionID"]);
		
	$this->item["STL"][$rwSTL["stlGUID"]] = $arrRet;

    $nIterations++;

    if ($arrRet['stlDepartureActionID_prevs'] && $nIterations<MAX_STL_LENGTH){
        $this->getStatusData($arrRet['stlDepartureActionID_prevs']);
    }
	
}

/***************************************************************/
// event handling prototypes
/***************************************************************/
function onActionPlan($actID, $oldStatusID, $newStatusID){
    //parent::onActionPlan($actID, $oldStatusID, $newStatusID);
}
function onActionStart($actID, $oldStatusID, $newStatusID){
    
    //parent::onActionStart($actID, $oldStatusID, $newStatusID);
    
    if ($actID<=4) 
        return true;
    
    if ($oldStatusID!=$this->item['staID'])
        throw new Exception("Action {$this->arrAction["actTitle"]} cannot be started for {$entItemID} because of its status ({$this->item[$entID."StatusID"]})");
    
    $this->onStatusDeparture($oldStatusID);

    return true;
}
function onActionFinish($actID, $oldStatusID, $newStatusID){

    $this->checkTimeLine();

}
function onActionCancel($actID, $oldStatusID, $newStatusID){
    //parent::onActionCancel($actID, $oldStatusID, $newStatusID);
}
function onActionUndo($actID, $oldStatusID, $newStatusID){
    //parent::onActionUndo($actID, $oldStatusID, $newStatusID);
}


function onStatusArrival($staID){
    //parent::onStatusArrival($staID);
}
function onStatusDeparture($staID){
    //parent::onStatusDeparture($staID);
}

}

?>