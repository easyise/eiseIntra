<?php
class eiseAction {

public static $ts = array('ETD', 'ATD', 'ETA', 'ATA');

public function __construct($item, $arrAct, $options = array()){

	$this->item = $item;
	$this->oSQL = $item->oSQL;
	$this->intra = $item->intra;
	$this->entID = $item->conf['entID'];

    $this->flagIsManagement = ( count(array_intersect($this->intra->arrUsrData['roleIDs'], explode(',', $this->item->conf['entManagementRoles'])  ) ) > 0 );

    $nd = $arrAct;
	
	if(!$arrAct['actID'] && !$arrAct['aclGUID'])
		$arrAct['actID'] = 2;

    $types = array();
    foreach(self::$ts as $_ts)
        $types['acl'.$_ts] = 'datetime';
    $types = array_merge($types, $this->item->conf['attr_types']);

    if(!$options['flagDoNotRefresh'])
        $this->item->getAllData(array('Master', 'Text','ACL'));

	if($arrAct['aclGUID']){
        $this->arrAction = $this->item->item['ACL'][$arrAct['aclGUID']];
        if(!$this->arrAction){
            throw new Exception("Action {$arrAct['aclGUID']}/{$arrAct['actID']} not found", 1);
        }
        $toTrace = array();
        foreach($nd as $field=>$value){
            if(strpos($field, '_'.$arrAct['aclGUID'])===False)
                continue;
            $toTrace[str_replace('_'.$arrAct['aclGUID'], '', $field)] = $value;
        }
        $this->conf = $item->conf['ACT'][$this->arrAction['aclActionID']];
        $this->arrAction = array_merge(
            $this->conf,
            $this->arrAction,
            $this->intra->arrPHP2SQL($toTrace, $types),
            array('aclToDo'=> $nd['aclToDo'])
        );

        $this->arrAction = array_merge($this->arrAction, $this->getTraceData());


	} else {
		$this->conf = $item->conf['ACT'][(string)$arrAct['actID']];
        $nd_SQL = $this->intra->arrPHP2SQL($nd, $types);
        $this->arrAction = array_merge($this->conf, $nd_SQL);
	}

    switch($this->conf['actID']){
        case 0:
        case 3:
            $this->arrAction['aclOldStatusID'] = $this->arrAction['actOldStatusID'][0];
            $this->arrAction['aclNewStatusID'] = $this->arrAction['actNewStatusID'][0];
            $this->arrAction['RLA'] = ($this->intra->arrUsrData['FlagWrite'] || $this->intra->arrUsrData['FlagDelete'] ? array($item->conf['RoleDefault']) : array());
            break;
        case 2:
            $this->arrAction['aclOldStatusID'] = $item->staID;
            $this->arrAction['aclNewStatusID'] = $item->staID;
            $this->arrAction['RLA'] = ($this->intra->arrUsrData['FlagWrite'] || $this->intra->arrUsrData['FlagUpdate'] ? array($item->conf['RoleDefault']) : array());
            break;
        case 4: 
            if(!$this->flagIsManagement){
                throw new Exception("Only management ({$this->item->conf['entManagementRoles']}) can run the Superaction");
            }
            $this->arrAction['aclOldStatusID'] = $item->staID;
            $this->arrAction['aclNewStatusID'] = $arrAct['aclNewStatusID'];
            $this->arrAction['aclComments'] = __("Status '%s' set by %s@%s:"
                , $this->item->oSQL->d("SELECT staTitle{$this->intra->local} FROM stbl_status WHERE staID=".$this->item->oSQL->e($arrAct['aclNewStatusID']))
                , $this->intra->arrUsrData['usrID']
                , $this->intra->datetimeSQL2PHP(date('Y-m-d H:i:s')))
                ."<br>\n"
                .$arrAct['aclComments'];
            break;
        default:
            if($this->arrAction['actNewStatusID'][0]===null){ // if we move to the same status
                $this->arrAction['aclNewStatusID'] = $item->staID;
                // die('<pre>'.var_export($this->arrAction, true));
            }
            break;
    }

	if(!$this->conf && !$arrAct['aclGUID'])
		throw new Exception("Action is not defined properly. Debug info: ".var_dump($arrAct, true));

}

public function execute(){

    // proceed with the action
    if ($this->arrAction["actFlagAutocomplete"] && !$this->arrAction["aclGUID"]){

        $this->add();
        $this->validate();
        $this->finish();
        
    } else {
        if ($this->arrAction["aclGUID"]==""){
            
            $this->add();
            
        } else {
            switch ($this->arrAction["aclToDo"]) {
                case "finish":
                	$this->validate();
                    $this->finish();
                    break;
                case "start":
                    $this->start();
                    break;
                case "cancel":
                    $this->cancel();
                    break;
            }
        }
        
    }
    
    $aclGUID = $this->arrAction["aclGUID"];
    
    return $aclGUID;

}

public function update($nd = null){

    $aToUpdate_old = (array)@json_decode($this->arrAction['aclItemTraced'], true);
    $aToUpdate = array();

    foreach(array_merge(array_keys((array)$this->conf['aatFlagToTrack']), array('aclATA','aclATD','aclETA','aclETD')) as $atrID){
        $nd_key = $atrID.'_'.$this->arrAction['aclGUID'];
        if(isset($this->arrAction[$nd_key])){
            $aToUpdate[$atrID] = $this->arrAction[$nd_key];
        }
        if(isset($nd[$nd_key])){
            $aToUpdate[$atrID] = $nd[$nd_key];
        } elseif (isset($nd[$atrID])) {
            $aToUpdate[$atrID] = $nd[$atrID];
        }
        if (in_array($this->item->conf['ATR'][$atrID]['atrType'], array('boolean', 'checkbox'))) { // booleans are not transferrable via HTTP
            $aToUpdate[$atrID] = (isset($aToUpdate[$atrID]) ? $aToUpdate[$atrID] : '0');
        }
    }

    $aToUpdate = array_merge($aToUpdate_old, $aToUpdate);

    $timestamps = $this->getTimeStamps($aToUpdate, 'ACL');
    $aclComments = (isset($this->arrAction['aclComments_'.$this->arrAction['aclGUID']])
        ? $this->arrAction['aclComments_'.$this->arrAction['aclGUID']]
        : (isset($nd['aclComments_'.$this->arrAction['aclGUID']])
            ? $nd['aclComments_'.$this->arrAction['aclGUID']]
            : (isset($nd['aclComments'])
                ? $nd['aclComments']
                : null)
            )
        );

    $traced = $this->intra->arrPHP2SQL($aToUpdate, $this->item->conf['attr_types']);

    $sqlACL = "UPDATE stbl_action_log SET # eiseAction::update() {$this->arrAction['actTitle']}
        aclEditDate=NOW(), aclEditBy='{$this->intra->usrID}' 
        ".(count($aToUpdate) ? ", aclItemTraced=".$this->oSQL->e(json_encode($traced)) : '')."
        {$timestamps}
        , aclComments = ".($aclComments ? $this->oSQL->e($aclComments) : 'aclComments')."
    WHERE aclGUID='{$this->arrAction['aclGUID']}'";

    $this->oSQL->q($sqlACL);

    $sqlSTL = "UPDATE stbl_status_log LEFT OUTER JOIN stbl_action_log ON stlEntityItemID=aclEntityItemID AND aclGUID=stlArrivalActionID # eiseAction::update() {$this->arrAction['actTitle']}
        SET stlATA=aclATA
            , stlEditDate=NOW()
            , stlEditBy = '{$this->item->intra->usrID}'
        WHERE aclEntityItemID='{$this->item->id}' AND stlArrivalActionID='{$this->arrAction['aclGUID']}'";
    $this->oSQL->q($sqlSTL);

}

public function add(){

    // 0. Trigger beforeActionPlan hook
    $this->item->beforeActionPlan($this->arrAction['actID'], $this->arrAction['aclOldStatusID'], $this->arrAction['aclNewStatusID']);

    // 1. obtaining aclGUID
    $this->arrAction["aclGUID"] = ($this->arrAction["aclGUID"] ? $this->arrAction["aclGUID"] : $this->oSQL->d("SELECT UUID() # add action {$this->arrAction['actTitle']}"));

    $item_before = self::itemCleanUp($this->item->item_before, $this->item->conf['prefix']);
    
    $aToTrace = array();

    foreach((array)$this->conf['aatFlagToTrack'] as $field=>$props){

        $aToTrace[$field] = (isset($this->item->item[$field]) && !$props['aatFlagEmptyOnInsert']
            ? $this->item->item[$field]
            : null
            );

        $aToTrace[$field] = ($this->arrAction[$field]!==null
            ? $this->arrAction[$field]
            : $aToTrace[$field]
            );
    }

    $timestamps = $this->getTimeStamps($aToTrace, 'ACL');

    // 2. insert new ACL
    $sqlInsACL = "INSERT INTO stbl_action_log SET # add action {$this->arrAction['actTitle']}
        aclGUID = '{$this->arrAction["aclGUID"]}'
        , aclActionID = ".(int)$this->arrAction["actID"]."
        , aclEntityItemID = '".$this->item->id."'
        , aclOldStatusID = ".($this->arrAction['aclOldStatusID']===null ? "NULL"  : "'".(string)$this->arrAction['aclOldStatusID']."'")."
        , aclNewStatusID = ".($this->arrAction['aclNewStatusID']===null ? "NULL"  : "'".(string)$this->arrAction['aclNewStatusID']."'")."
            , aclActionPhase = 0
            {$timestamps}
            , aclItemBefore = ".$this->oSQL->e(json_encode($item_before))."
            , aclItemDiff = NULL
            , aclItemAfter = NULL
            , aclItemTraced = ".$this->oSQL->e(json_encode($aToTrace))."
        , aclComments = ".($this->arrAction['aclComments'] ? $this->oSQL->e($this->arrAction['aclComments']) : 'NULL')."
        , aclInsertBy = '{$this->intra->usrID}', aclInsertDate = NOW(), aclEditBy='{$this->intra->usrID}', aclEditDate=NOW()";
     
    $this->oSQL->q($sqlInsACL);  

    $this->arrAction = array_merge($this->arrAction, $this->oSQL->f("SELECT * FROM stbl_action_log WHERE aclGUID='{$this->arrAction['aclGUID']}'"));
    
    // 3. Trigger onActionPlan hook
    $this->item->onActionPlan($this->arrAction['actID'], $this->arrAction['aclOldStatusID'], $this->arrAction['aclNewStatusID']);

    $this->arrAction['aclActionPhase'] = 0;

    return $this->arrAction["aclGUID"];

}

function start(){
    
    $this->arrAction['aclOldStatusID'] = $this->item->staID;

    if (!in_array($this->item->staID,  $this->conf['actOldStatusID']))
        throw new Exception("Action {$this->conf["actTitle"]} cannot be started for {$this->id} because of its status ({$this->item->staID})");

    if (($oldStatusID!==$newStatusID 
          && $newStatusID!==""
        )
        || $this->conf["actFlagInterruptStatusStay"]){

        $this->onStatusDeparture($this->arrAction['aclOldStatusID']);

    }

    $this->item->onActionStart($this->arrAction['actID'], $this->arrAction['aclOldStatusID'], $this->arrAction['aclNewStatusID']);

    $item_before = $this->item->item_before;
    foreach ($item_before as $key => $value) {
        if(is_array($value))
            unset($item_before[$key]);
    }

    $sqlUpdACL = "UPDATE stbl_action_log SET 
        aclActionPhase=1
        , aclOldStatusID = '{$this->item->staID}'
        , aclItemBefore = ".$this->oSQL->e( json_encode( self::itemCleanUp($this->item->item_before, $this->item->conf['prefix']) ) )."
        , aclStartBy='{$this->intra->usrID}', aclStartDate=NOW()
        , aclEditBy='{$this->intra->usrID}', aclEditDate=NOW()
    WHERE aclGUID='{$this->arrAction['aclGUID']}'";
    $this->oSQL->do_query($sqlUpdACL);
    
    $sqlUpdEntTable = "UPDATE {$this->item->conf["table"]} SET
            {$this->item->conf['prefix']}ActionLogID='{$this->arrAction['aclGUID']}'
            , {$this->item->conf['prefix']}EditBy='{$this->intra->usrID}', {$this->item->conf['prefix']}EditDate=NOW()
            WHERE {$this->item->conf['PK']}='{$entItemID}'";
    $this->oSQL->do_query($sqlUpdEntTable);

}

public function validate(){

	$aclOldStatusID = (array_key_exists("aclOldStatusID", $this->arrAction) 
	    ? $this->arrAction["aclOldStatusID"] 
	    : $this->item->item["{$this->item->conf['prefix']}StatusID"]
	    );

	if($this->arrAction['actID']<=4){
	    switch( $this->conf['actID'] ){
	        case 1:
	            if($aclOldStatusID>0)
	                throw new Exception('Item is already created');
	            break;
	        case 2:
	            if(!$this->item->conf['STA'][$aclOldStatusID]['staFlagCanUpdate'] && !$this->flagFullEdit)
	                throw new Exception('Update is not allowed');
	            break;
	        case 3:
	            if(!$this->item->conf['STA'][$aclOldStatusID]['staFlagCanDelete'])
	                throw new Exception('Delete is not allowed');
	            break;
	        default:
	            break;
	    }
	} else {

        
        if( !$this->flagIsManagement ) 
            if(!in_array($this->item->item["{$this->item->conf['prefix']}StatusID"], $this->conf['actOldStatusID'])){
                throw new Exception($this->intra->translate("%s %s: Action \"%s\" could not run in status \"%s\""
                    , $this->item->conf['entTitle'.$this->intra->local] 
                    , $this->item->id
                    , ( $this->conf['actTitle'.$this->intra->local] ? $this->conf['actTitle'.$this->intra->local] : $this->conf['actTitle'])
                    , $this->item->conf['STA'][$this->item->item["{$this->item->conf['prefix']}StatusID"]]['staTitle'.$this->intra->local]));
    	    }
	    if($this->conf['actNewStatusID'][0] && $this->arrAction["aclNewStatusID"] != $this->conf['actNewStatusID'][0]){
	        throw new Exception($this->intra->translate("%s %s: Action \"%s\" could not run for destination status \"%s\""
                , $this->item->conf['entTitle'.$this->intra->local] 
                , $this->item->id
                , ( $this->conf['actTitle'.$this->intra->local] ? $this->conf['actTitle'.$this->intra->local] : $this->conf['actTitle'])
                , $this->item->conf['STA'][(string)$this->arrAction["aclNewStatusID"]]['staTitle'.$this->intra->local]));
	    }
	}

    // mandatory items check
    $aMissingFields = array();
    foreach ((array)$this->arrAction['aatFlagMandatory'] as $atrID => $props) {
        $v = ($this->arrAction[$atrID]
                ? $this->arrAction[$atrID]
                : ($this->item->item[$atrID])
                ); 
        if(!$v || (is_numeric($v) && (double)$v===0.0 )){
            $aMissingFields[] = $this->item->conf['ATR'][$atrID]['atrTitle'.$this->intra->local]." ({$atrID})";
        }
    }
    if(count($aMissingFields)){
        throw new Exception($this->intra->translate("Some fields are missing:\n%s", implode(",\n\t", $aMissingFields)));
    }
}

public function finish(){

	if (!$this->arrAction["aclActionPhase"]){
        $this->item->onActionStart($this->arrAction['actID'], $this->arrAction['aclOldStatusID'], $this->arrAction['aclNewStatusID']);
    }

    $this->checkTimeLine();
    $this->checkMandatoryFields();
    $this->checkPermissions();

    $item_before = self::itemCleanUp($this->item->item_before, $this->item->conf['prefix']);

    $timestamps = $this->getTimeStamps();
    $aTraced = $this->getTraceData();

    if ($this->conf["actID"]!="2") {

        $tracedFields = $this->intra->getSQLFields($this->item->table, $aTraced);
        $userstamps = $this->getUserStamps();

        $sqlMaster = "UPDATE {$this->item->conf['table']} LEFT OUTER JOIN stbl_action_log ON aclGUID='{$this->arrAction['aclGUID']}' SET
            {$this->item->conf['prefix']}ActionLogID=aclGUID
            {$tracedFields}
            {$userstamps}
            {$timestamps}
            , {$this->item->conf['prefix']}EditBy='{$this->intra->usrID}', {$this->item->conf['prefix']}EditDate=NOW()
        WHERE ".$this->item->getSQLWhere();

        $this->oSQL->q($sqlMaster);

    }

    $this->item->getAllData(array('Master'));

    $item_after = self::itemCleanUp($this->item->item, $this->item->conf['prefix']);

    $item_diff = array_diff($item_before, $item_after);

    // update started action as completed
    $sqlUpdACL = "UPDATE stbl_action_log SET
        aclActionPhase = 2
        , aclItemAfter = ".$this->oSQL->e(json_encode($item_after))."
        , aclItemDiff = ".$this->oSQL->e(json_encode($item_diff))."
        ".($this->conf["actID"]!="2" && count($aTraced)>0
            ? ", aclItemTraced=".$this->oSQL->e(json_encode($aTraced))
            : ', aclItemTraced=NULL')."
        ".($this->arrAction['aclComments']
            ? ", aclComments=".$this->oSQL->e($this->arrAction['aclComments'])
            : '')."
        , aclStartBy=IFNULL(aclStartBy, '{$this->intra->usrID}'), aclStartDate=IFNULL(aclStartDate,NOW())
        , aclFinishBy=IFNULL(aclFinishBy, '{$this->intra->usrID}'), aclFinishDate=IFNULL(aclFinishDate, NOW())
        , aclEditDate=NOW(), aclEditBy='{$this->intra->usrID}'
        WHERE aclGUID='".$this->arrAction["aclGUID"]."'";

    $this->oSQL->q($sqlUpdACL);

    // if status is changed or action requires status stay interruption, we insert status log entry and update master table
    if (($this->arrAction["aclOldStatusID"]!==$this->arrAction["aclNewStatusID"]
          && (string)$this->arrAction["aclNewStatusID"]!=''
        )
        || $this->conf["actFlagInterruptStatusStay"]){

        $sql = Array();
        $stlGUID = $this->oSQL->get_data($this->oSQL->do_query("SELECT UUID()"));
        
        $sql[] = "UPDATE stbl_status_log SET
        stlATD=(SELECT IFNULL(aclATD, aclATA) FROM stbl_action_log WHERE aclGUID='{$this->arrAction["aclGUID"]}')
        , stlDepartureActionID='{$this->arrAction["aclGUID"]}'
        , stlEditBy='{$this->intra->usrID}', stlEditDate=NOW()
        WHERE stlEntityItemID='{$this->item->id}' AND stlATD IS NULL";

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
            , '{$this->item->entID}' as stlEntityID
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
            INNER JOIN stbl_status ON aclNewStatusID=staID AND staEntityID='{$this->item->conf['entID']}'
            WHERE aclGUID='{$this->arrAction['aclGUID']}'";
        
        $arrSAT = $this->conf['STA'][$this->arrAction['aclNewStatusID']]['satFlagTrackOnArrival'];
        if (count($arrSAT)>0){
            
        }
        
        // after action is done, we update entity table with last status action log id
        $sql[] = "UPDATE {$this->item->conf["entTable"]} SET
            {$this->item->conf['prefix']}ActionLogID='{$this->arrAction["aclGUID"]}'
            , {$this->item->conf['prefix']}StatusActionLogID='{$this->arrAction["aclGUID"]}'
            , {$this->item->conf['prefix']}StatusID=".(int)$this->arrAction["aclNewStatusID"]."
            , {$this->item->conf['prefix']}EditBy='{$this->intra->usrID}', {$this->item->conf['prefix']}EditDate=NOW()
            WHERE {$this->item->table['PK'][0]}='{$this->item->id}'";
        
        for($i=0;$i<count($sql);$i++){
            $this->oSQL->do_query($sql[$i]);
        }

        $this->item->onStatusArrival($this->arrAction['aclNewStatusID']);

    }
    
    $this->item->onActionFinish($this->arrAction['actID'], $this->arrAction['aclOldStatusID'], $this->arrAction['aclNewStatusID']);

}

function cancel(){

    $oSQL = $this->oSQL;
    
    $entID = $this->item->conf["entID"];
    $entItemID = $this->item->id;
    $aclGUID = $this->arrAction["aclGUID"];

    $this->item->onActionCancel($this->arrAction['aclActionID'], $this->arrAction['aclOldStatusID'], $this->arrAction['aclNewStatusID']);
    
    if($this->arrAction['aclActionPhase']==0){
        $oSQL->q("DELETE FROM stbl_action_log WHERE aclGUID='{$this->arrAction['aclGUID']}'");
    } else {
        $oSQL->q("UPDATE stbl_action_log SET
            aclActionPhase=3
            , aclCancelBy='{$this->intra->usrID}'
            , aclCancelDate=NOW()
            WHERE aclGUID='{$this->arrAction['aclGUID']}'");
    }

}


function checkTimeLine(){
	
    $oSQL = $this->oSQL;
    
	$entID = $this->item->conf["entID"];
    $entItemID = $this->item->id;
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
        $ata = $oSQL->d("SELECT aclATA FROM stbl_action_log WHERE aclGUID='{$aclGUID}'");
        list($maxATA, $actTitle, $aclGUID) = $oSQL->fa($oSQL->q("SELECT aclATA, actTitle{$this->intra->local}, aclGUID
            FROM stbl_action_log LEFT OUTER JOIN stbl_action ON aclActionID=actID
            WHERE aclEntityItemID='{$entItemID}' AND aclActionPhase=2 AND aclActionID>2
            ORDER BY aclATA DESC LIMIT 0,1"));
		throw new Exception("ATA ({$ata}) for execueted action cannot be in the past for {$this->item->conf['entTitle']}:{$entItemID}, last action {$actTitle}: {$maxATA}");
	}
	
	$sqlATAATD = "SELECT 
		CASE WHEN DATEDIFF (aclATA, IFNULL(aclATD, aclATA)) < 0 THEN 0 ELSE 1 END as ATAnotLessThanATD
		FROM stbl_action_log 
		WHERE aclGUID='{$aclGUID}'
		";
	if (!$oSQL->get_data($oSQL->do_query($sqlMaxATA))) {
		throw new Exception("ATA for execueted action cannot be less than ATD");
	}

	return true;
}

function checkMandatoryFields(){
    
    $oSQL = $this->oSQL;
    
    $entID = $this->item->conf['entID'];
    $entItemID = $this->item->id;
    $entTable = $this->item->conf["entTable"];
    $rwEnt = $this->item->item;
    $flagAutocomplete = $this->arrAction["actFlagAutocomplete"];
    $aclGUID = $this->arrAction["aclGUID"];

    $aMandatoryFails = array();
    $aChangedFails = array();

    foreach((array)$this->arrAction["aatFlagMandatory"] as $atrID => $rwATR){
            
        $oldValue = $this->item->item[$atrID];
        
        if ($this->arrAction["aclGUID"]==""){
            /*
            $flagIsMissingOnForm = (int)($this->arrAction['actFlagAutocomplete'] && !isset($this->arrNewData[$atrID]));
            $sqlCheckMandatory = "SELECT 
                CASE WHEN IFNULL({$atrID}, '')='' 
                AND NOT ".(int)$flagIsMissingOnForm." 
                THEN 0 ELSE 1 END as mandatoryOK 
                FROM {$entTable} WHERE {$this->entItemIDField}='{$entItemID}'";
            $sqlCheckChanges = "SELECT 
                CASE WHEN IFNULL({$atrID}, '')='{$rwEnt[$atrID]}' THEN 0 ELSE 1 END as changedOK 
                FROM {$entTable} WHERE {$this->entItemIDField}='{$entItemID}'";
                */
        } else {

            if($rwATR['aatFlagMandatory'] && !$this->arrAction[$atrID])
                $aMandatoryFails[] = $atrID;
            
            if($rwATR['aatFlagToChange'] && $this->arrAction[$atrID]!=$this->item->item[$atrID])
                $aChangedFails[] = $atrID;
            
        }
        
    }

    if (count($aMandatoryFails)){
        $strFields = '';
        foreach($aMandatoryFails as $field)
            $strFields .= ($strFields ? ', ' : '').$this->item->conf['ATR'][$field]["atrTitle{$this->intra->local}"];
        throw new Exception($this->intra->translate("These fields are required: %s for %s", $strFields, $this->item->id));
    } 
    
    if (count($aChangedFails)){
        $strFields = '';
        foreach($aChangedFails as $field)
            $strFields .= ($strFields ? ', ' : '').$this->item->conf['ATR'][$field]["atrTitle{$this->intra->local}"];
        throw new Exception($this->intra->translate("These fields should be changed: %s for %s", $strFields, $this->item->id));
    }

    if($this->conf['actFlagComment'] && !$this->arrAction['aclComments'])
        throw new Exception($this->intra->translate("Action '%s' requires a comment", $this->conf['actTitle'.$this->intra->local]));
    
}

public function checkPermissions(){

    if($this->arrAction['actFlagSystem']){ // system actions has no UI but it can be invoked in code
        return;
    }

    $rwAct = $this->arrAction;
    $aUserRoles = array_merge(array($this->item->conf['RoleDefault']), $this->intra->arrUsrData['roleIDs']);
    if(count(array_intersect($aUserRoles, $rwAct['RLA']))==0)
        throw new Exception($this->intra->translate("%s: not authorized because not member of (%s)",$this->arrAction['actTitle'.$this->intra->local], implode(', ', $rwAct['RLA'])) );
    $reason = '';
    if(count($this->item->checkDisabledRoleMembership($this->intra->usrID, $rwAct, $reason)) > 0)
         throw new Exception($this->intra->translate("Not authorized as %s", $reason));
}

public function getTimeStamps($nd = null, $flag='all', &$tsValues = array()){

    $sql = '';

    $a = array_merge($this->arrAction, (array)@json_decode($this->arrAction['aclItemTraced'], true), (array)$nd);

    $valNull = 'NOW()';

    foreach(self::$ts as $ts){
        $val_ts = $a[$this->conf['aatFlagTimestamp'][$ts]];
        $tsValues[$ts] = ($this->conf['actTrackPrecision']==='datetime'
                ? $this->intra->datetimePHP2SQL($val_ts, $valNull)
                : $this->intra->datePHP2SQL($val_ts, $valNull)
                );
    }

    // to prevent ATD, ET* from becomimg NOW()
    $aclATA = $tsValues['ATA'];
    foreach ($tsValues as $ts => $val) {
        $tsValues[$ts] = ( $tsValues[$ts]==$valNull ? $aclATA : $tsValues[$ts] );
    }

    if(!$this->conf['actFlagHasEstimates']){
        $tsValues['ETD'] = $tsValues['ATD'];
        $tsValues['ETA'] = $tsValues['ATA'];
    }

    if(!$this->conf['actFlagHasDeparture']){
        $tsValues['ETD'] = $tsValues['ETA'];
        $tsValues['ATD'] = $tsValues['ATA'];   
    }

    if(strtotime($this->item->oSQL->unq($tsValues['ATA'])) < strtotime($this->item->oSQL->unq($tsValues['ATD']))){
        $tsValues['ATA'] = $tsValues['ATD'];
    }
    if(strtotime($this->item->oSQL->unq($tsValues['ETA'])) < strtotime($this->item->oSQL->unq($tsValues['ETD']))){
        $tsValues['ETA'] = $tsValues['ETD'];
    }
    

    foreach($tsValues as $ts=>$value){
        $sql .= "\n, acl{$ts} = {$value}"; //.' /*'.var_export($this->conf['aatFlagTimestamp'], true).' -- '.var_export($s, true).'*/';
        if ( $flag=='all' && in_array($ts, array_keys($this->conf['aatFlagTimestamp'])) ) {
            $sql .= "\n, {$this->conf['aatFlagTimestamp'][$ts]}={$value}";
        }
    }

    
    // echo('<pre>'.var_export($sql, true));
    // echo('<pre>'.var_export( $flag=='all' , true));

    return $sql;

}

public function getUserStamps(){
    $sql = '';
    foreach ( (array)$this->conf["aatFlagUserStamp"] as $atrID => $xx ) {
        if(array_key_exists($atrID, (array)$this->arrAction["aatFlagToTrack"]))
            continue;
        $sql .= "\n, {$atrID} = ".$oSQL->e($this->intra->usrID);
    }
    return $sql;
}

public function getTraceData(){

    $aRet = array();
    $aTraced = @json_decode($this->arrAction['aclItemTraced'], true);

    foreach((array)$this->conf['aatFlagToTrack'] as $field=>$props){
        $aRet[$field] = (isset($this->arrAction[$field]) ? $this->arrAction[$field] : $aTraced[$field]);
    }

    $aRet_SQL = $this->intra->arrPHP2SQL($aRet, $this->item->table['columns_types']);

    return $aRet_SQL;

}




static function itemCleanUp($item, $prefix = ''){

    foreach ($item as $key => $value) {
        if(is_array($value))
            unset($item[$key]);
    }

    unset($item[$prefix.'EditBy']);
    unset($item[$prefix.'EditDate']);
    unset($item[$prefix.'InsertBy']);
    unset($item[$prefix.'InsertDate']);

    unset($item[$prefix.'StatusID']);
    unset($item[$prefix.'StatusActionLogID']);
    unset($item[$prefix.'ActionLogID']);

	return $item;
}

}