<?php
/**
 * This class handles actions on traceable items. Actions can be performed both by users and programmatically. Actions are defined in stbl_action table and linked to statuses in stbl_status table. Action can change status of the item, update some fields and trace item data defined by item configuration. 
 * 
 * Object created as class instance defines single action to be performed on the item. Action is executed by execute() method. Execution means that action is added to action log (stbl_action_log) and, if required, item data is updated in the master table. If action is not set to autocomplete, it should be started and finished separately.
 * 
 * Actions can be planned for forecasting purposes without execution. Planned actions are stored in stbl_action_log table with action phase set to 0. Planned actions can be started and finished later.
 * 
 * System can trace ETA/ETD/ATA/ATD timestamps for both actions, depending on action configuration options. These timestamps can be set programmatically or as actual timestamps when start or finish actions.
 */
class eiseAction {

public $item;
public $oSQL;
public $intra;
public $entID;
public $flagIsManagement = false;

public static $ts = array('ETD', 'ATD', 'ETA', 'ATA');

/**
 * @var array $conf Associative array defining action configuration as obtained from stbl_action table.
 * Might contain ```aclGUID``` key if action has been obtained from stbl_action_log table. 
 */
public $arrAction = array();

/**
 * Constructor of eiseAction class
 * 
 * @param eiseItemTraceable $item Instance of eiseItemTraceable class the action is performed on
 * @param array $arrAct Associative array defining action to be performed. Should contain at least 'actID' or 'aclGUID' keys
 * @param array $options Associative array of options.
 * 
 * @category Events and Actions
 */
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

    if(!isset($options['flagDoNotRefresh']) || !$options['flagDoNotRefresh'])
        $this->item->getAllData(array('Master', 'Text','ACL'));

	if(isset($arrAct['aclGUID']) && $arrAct['aclGUID']){
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
            $this->arrAction['aclOldStatusID'] = isset($this->arrAction['actOldStatusID'][0]) ? $this->arrAction['actOldStatusID'][0] : null;
            $this->arrAction['aclNewStatusID'] = isset($this->arrAction['actNewStatusID'][0]) ? $this->arrAction['actNewStatusID'][0] : null;
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

/**
 * This function "executes" the action. If action is set to autocomplete, it is added to action log and finished in one go. If not, it is just added to action log and should be started and finished later.
 * 
 * @return string Returns aclGUID of the action just executed
 * 
 * @category Events and Actions
 */
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

/**
 * This function updates action log record with new data. It can be used to update traced fields, comments and timestamps.
 * 
 * @param array $nd Associative array of fields to update. Should contain keys like 'aclETA', 'aclETD', 'aclATA', 'aclATD' or any other fields defined as traceable in item configuration.
 * 
 * @return void
 * 
 * @category Events and Actions
 */
public function update($nd = null){

    $aToUpdate_old = (array)@json_decode($this->arrAction['aclItemTraced'], true);
    $aToUpdate = array();

    foreach(array_merge(array_keys((array)(isset($this->conf['aatFlagToTrack']) ? $this->conf['aatFlagToTrack'] : array())), array('aclATA','aclATD','aclETA','aclETD')) as $atrID){
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

/**
 * Function adds new action to action log. ```aclActionPhase``` is set to 0 (planned). Action should be started and finished later unless it's set to autocomplete.
 * Function also updates ```$arrAction``` property of the class instance with data just inserted to action log.
 * Being executed it triggers ```beforeActionPlan``` and ```onActionPlan``` hooks of the item.
 * 
 * @return string Returns aclGUID of the action just added
 *
 * @category Events and Actions
 */
public function add(){

    // 0. Trigger beforeActionPlan hook
    $this->item->beforeActionPlan($this->arrAction['actID'], $this->arrAction['aclOldStatusID'], $this->arrAction['aclNewStatusID']);

    // 1. obtaining aclGUID
    $this->arrAction["aclGUID"] = ($this->arrAction["aclGUID"] ? $this->arrAction["aclGUID"] : $this->oSQL->d("SELECT UUID() # add action {$this->arrAction['actTitle']}"));

    $item_before = self::itemCleanUp($this->item->item_before, $this->item->conf['prefix']);
    
    $aToTrace = array();

    $flagsToTrack = !empty($this->conf['aatFlagToTrack'])
        ? (array)$this->conf['aatFlagToTrack']
        : [];
    foreach ($flagsToTrack as $field => $props) {

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

/**
 * Function starts the action previously added to action log. ```aclActionPhase``` is set to 1 (started).
 * Being executed it triggers ```onActionStart``` hook of the item.
 * 
 * @category Events and Actions
 */
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

/**
 * This function validates if action can be performed. It does not check if user has permissions to run the action, this should be done separately.
 * 
 * It checks following:
 * - if action is create, update or delete, it checks if item is in correct status and if user has permissions to perform such action
 * - it checks if item is in correct status to run the action
 * - it checks mandatory fields defined for the action. 
 * 
 * In case of any problem, it throws an Exception with description of the problem.
 * 
 * @category Events and Actions
 */
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
            if(!(in_array($this->item->item["{$this->item->conf['prefix']}StatusID"], $this->conf['actOldStatusID'])
                    || in_array(null, $this->conf['actOldStatusID'])
                )
                ){
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
    $mandatory = !empty($this->arrAction['aatFlagMandatory'])
    ? (array)$this->arrAction['aatFlagMandatory']
    : [];
    foreach ($mandatory as $atrID => $props) {
        $v = ($this->arrAction[$atrID]
                ? $this->arrAction[$atrID]
                : ($this->item->item[$atrID])
                ); 

        $flagBoolean = in_array($this->item->conf['ATR'][$atrID]['atrType'], array('boolean', 'checkbox'));
        if(!$flagBoolean){
            if(!$v || (is_numeric($v) && (double)$v===0.0 )){
                $aMissingFields[] = $this->item->conf['ATR'][$atrID]['atrTitle'.$this->intra->local]." ({$atrID})";
            }
        }
    }
    if(count($aMissingFields)){
        throw new Exception($this->intra->translate("Some fields are missing:\n%s", implode(",\n\t", $aMissingFields)));
    }
}

/**
 * This function finishes the action previously started. ```aclActionPhase``` is set to 2 (completed). If action changes status, status log entry is added and master table is updated.
 * Being executed it triggers ```onActionFinish``` hook of the item.
 * 
 * @return void
 * 
 * @category Events and Actions
 */
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
            , {$this->item->table['prefix']}EditBy='{$this->intra->usrID}', {$this->item->table['prefix']}EditDate=NOW()
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
          && $this->item->processCheckmarks($this->arrAction)
          && (string)$this->arrAction["aclNewStatusID"]!=''
        )
        || (isset($this->conf["actFlagInterruptStatusStay"]) ? $this->conf["actFlagInterruptStatusStay"] : false)){

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
        
        
        // after action is done, we update entity table with last status action log id
        $sql[] = "UPDATE {$this->item->conf["entTable"]} SET
            {$this->item->conf['prefix']}ActionLogID='{$this->arrAction["aclGUID"]}'
            , {$this->item->conf['prefix']}StatusActionLogID='{$this->arrAction["aclGUID"]}'
            , {$this->item->conf['prefix']}StatusID=".(int)$this->arrAction["aclNewStatusID"]."
            , {$this->item->table['prefix']}EditBy='{$this->intra->usrID}', {$this->item->table['prefix']}EditDate=NOW()
            WHERE {$this->item->table['PK'][0]}='{$this->item->id}'";
        
        for($i=0;$i<count($sql);$i++){
            $this->oSQL->do_query($sql[$i]);
        }

        $this->item->onStatusArrival($this->arrAction['aclNewStatusID']);

    }
    
    $this->item->onActionFinish($this->arrAction['actID'], $this->arrAction['aclOldStatusID'], $this->arrAction['aclNewStatusID']);

}

/**
 * This function cancels the action previously added to action log. If action was in phase 0 (planned), it is deleted from action log. If it was started (phase 1), it is marked as cancelled (phase 3).
 * 
 * @return void
 * 
 * @category Events and Actions
 */
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

/**
 * This function checks if action timeline is correct, i.e. ATA is not less than previous ATA and not less than ATD.
 * 
 * In case of any problem, it throws an Exception with description of the problem.
 * 
 * @category Events and Actions
 */
function checkTimeLine(){
	
    $oSQL = $this->oSQL;
    
	$entID = $this->item->conf["entID"];
    $entItemID = $this->item->id;
	$aclGUID = $this->arrAction["aclGUID"];
	
	if ($this->arrAction["actID"]=="2")
		return true;
		
	
	$sqlMaxATA = "SELECT 
		CASE WHEN DATEDIFF(
			(SELECT aclATA FROM stbl_action_log WHERE aclGUID='{$aclGUID}' )
			, MAX(aclATA)
			) < 0 THEN 0 ELSE 1 END as ATAnotLessThanPrevious
	FROM stbl_action_log 
        INNER JOIN stbl_action ON aclActionID=actID AND actEntityID='{$entID}'
	WHERE aclEntityItemID='{$entItemID}' AND aclActionPhase=2 AND aclActionID>2";
	if (!$oSQL->get_data($oSQL->do_query($sqlMaxATA))) {
        $ata = $oSQL->d("SELECT aclATA FROM stbl_action_log WHERE aclGUID='{$aclGUID}'");
        $sqlMaxATA = "SELECT aclATA, actTitle{$this->intra->local}, aclGUID
            FROM stbl_action_log INNER JOIN stbl_action ON aclActionID=actID AND actEntityID='{$entID}'
            WHERE aclEntityItemID='{$entItemID}' AND aclActionPhase=2 AND aclActionID>2
            ORDER BY aclATA DESC LIMIT 0,1";
        echo $sqlMaxATA;
        list($maxATA, $actTitle, $aclGUID) = $oSQL->fa($oSQL->q($sqlMaxATA));
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

/**
 * This function checks if all mandatory fields defined for the action are filled and if all fields defined as "to change" are actually changed.
 * 
 * In case of any problem, it throws an Exception with description of the problem.
 * 
 * @category Events and Actions
 */
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

    $mandatory = !empty($this->arrAction['aatFlagMandatory'])
    ? (array)$this->arrAction['aatFlagMandatory']
    : [];

    foreach ($mandatory as $atrID => $rwATR) {
            
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

    if((isset($this->conf['actFlagComment']) ? $this->conf['actFlagComment'] : false) && !$this->arrAction['aclComments'])
        throw new Exception($this->intra->translate("Action '%s' requires a comment", $this->conf['actTitle'.$this->intra->local]));
    
}

/**
 * This function checks if user has permissions to run the action.
 * 
 * It checks following:
 * - if user is member of at least one role defined for the action
 * - if user is not member of any role defined as disabled for the action
 * 
 * In case of any problem, it throws an Exception with description of the problem.
 * 
 * @category Events and Actions
 */
public function checkPermissions(){

    if(isset($this->arrAction['actFlagSystem']) && $this->arrAction['actFlagSystem']){ // system actions has no UI but it can be invoked in code
        return;
    }

    $rwAct = $this->arrAction;
    $aUserRoles = array_merge(array($this->item->conf['RoleDefault']), $this->intra->arrUsrData['roleIDs']);
    if(count(array_intersect($aUserRoles, $rwAct['RLA']))==0)
        throw new Exception($this->intra->translate("%s: user %s not authorized because not member of (%s)",$this->arrAction['actTitle'.$this->intra->local], $this->intra->usrID, implode(', ', $rwAct['RLA'])) );
    $reason = '';
    if(count($this->item->checkDisabledRoleMembership($this->intra->usrID, $rwAct, $reason)) > 0)
         throw new Exception($this->intra->translate("Not authorized as %s", $reason));
}

/**
 * @ignore
 */
public function getTimeStamps($nd = null, $flag='all', &$tsValues = array()){

    $sql = '';

    $a = array_merge($this->arrAction, (array)@json_decode($this->arrAction['aclItemTraced'], true), (array)$nd);

    $valNull = 'NOW()';

    foreach(self::$ts as $ts){
        $val_ts = (isset($this->conf['aatFlagTimestamp'][$ts]) && isset($a[$this->conf['aatFlagTimestamp'][$ts]]) ? $a[$this->conf['aatFlagTimestamp'][$ts]] : null);
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

    if(!isset($this->conf['actFlagHasEstimates']) || !$this->conf['actFlagHasEstimates']){
        $tsValues['ETD'] = $tsValues['ATD'];
        $tsValues['ETA'] = $tsValues['ATA'];
    }

    if(!isset($this->conf['actFlagHasDeparture']) || !$this->conf['actFlagHasDeparture']){
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

/**
 * @ignore
 */
public function getUserStamps(){
    $sql = '';
    foreach ( (array)(isset($this->conf["aatFlagUserStamp"]) ? $this->conf["aatFlagUserStamp"] : array()) as $atrID => $xx ) {
        if(array_key_exists($atrID, (array)$this->arrAction["aatFlagToTrack"]))
            continue;
        $sql .= "\n, {$atrID} = ".$this->oSQL->e($this->intra->usrID);
    }
    return $sql;
}

/**
 * @ignore
 */
public function getTraceData(){

    $aRet = array();
    $aTraced = @json_decode($this->arrAction['aclItemTraced'], true);

    foreach((array)(isset($this->conf['aatFlagToTrack']) ? $this->conf['aatFlagToTrack'] : array()) as $field=>$props){
        $aRet[$field] = (isset($this->arrAction[$field]) ? $this->arrAction[$field] : $aTraced[$field]);
    }

    $aRet_SQL = $this->intra->arrPHP2SQL($aRet, $this->item->table['columns_types']);

    return $aRet_SQL;

}

/**
 * @ignore
 */
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