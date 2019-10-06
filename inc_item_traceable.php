<?php
include_once "inc_item.php";
include_once "inc_action.php";

define('MAX_STL_LENGTH', 256);

class eiseItemTraceable extends eiseItem {

const sessKeyPrefix = 'ent:';
const statusField = 'StatusID';

public $extraActions = array();

protected $defaultDataToObtain = array('Text', 'ACL', 'STL', 'files', 'messages');
	
public function __construct($id = null,  $conf = array() ){

	GLOBAL $intra, $oSQL, $arrJS;

    $arrJS[] = eiseIntraJSPath."action.js";

	$this->conf = array_merge($this->conf, $conf);

	if(!$this->conf['entID'])
		throw new Exception ("Entity ID not set");

	$this->entID = $this->conf['entID'];

	$this->intra = ($conf['intra'] ? $conf['intra'] : $intra);
	$this->oSQL = ($conf['sql'] ? $conf['sql'] : $oSQL);

	$this->init();

	$this->conf['title'] = ($conf['title'] ? $conf['title'] : $this->conf['entTitle']);
	$this->conf['titleLocal'] = ($conf['titleLocal'] ? $conf['titleLocal'] : $this->conf['entTitle'.$this->intra->local]);
	$this->conf['name'] = ($conf['name'] ? $conf['name'] : (preg_replace('/^(tbl_|vw_)/', '', $this->conf['entTable'])));
	$this->conf['prefix'] = ($conf['prefix'] ? $conf['prefix'] : ($this->conf['entPrefix']
		? $this->conf['entPrefix']
		: $this->conf['entID'])
	);
	$this->conf['table'] = ($conf['table'] ? $conf['table'] : $this->conf['entTable']);
	$this->conf['form'] = ($conf['form'] ? $conf['form'] : $this->conf['name'].'_form.php');
	$this->conf['list'] = ($conf['list'] ? $conf['list'] : $this->conf['name'].'_list.php');
    $this->conf['statusField'] = $this->conf['prefix'].self::statusField;
    $this->conf['flagFormShowAllFields'] = false;
	$this->conf['flagDeleteLogs'] = true;

	parent::__construct($id, $this->conf);

    if($this->id){
        $this->item_before = $this->item; 
        $this->staID = $this->item[$this->conf['statusField']];
    }

    $this->conf['attr_types'] = array_merge($this->table['columns_types'], $this->conf['attr_types']);

	$this->intra->dataRead(array('getActionDetails', 'getFiles', 'getFile', 'getMessages','sendMessage'), $this);
	$this->intra->dataAction(array('insert', 'update', 'updateMultiple', 'delete', 'attachFile', 'deleteFile'), $this);

}

public function update($nd){

    parent::update($nd);

    $this->oSQL->q('START TRANSACTION');
    // 1. update master table
    $nd_ = $nd;
    foreach ($nd_ as $key => $value) {
        if(!in_array($key, $this->conf['STA'][$this->staID]['satFlagEditable']))
            unset($nd_[$key]);
    }
    $this->updateTable($nd_);
    $this->updateUnfinishedActions($nd);
    $this->oSQL->q('COMMIT');

    $this->oSQL->q('START TRANSACTION');
    // 2. do the action
    $this->doAction(new eiseAction($this, $nd));
    
    $this->oSQL->q('COMMIT');

}

public function updateFullEdit($nd){

    $this->oSQL->q('START TRANSACTION');
    // 1. update master table
    $this->updateTable($nd);
    foreach($this->item['ACL'] as $aclGUID=>$rwACL){
        
        $this->updateAction($rwACL, $nd);

    }
    $this->oSQL->q('COMMIT');

    parent::update($nd);

}

public function undo($nd){

    $this->oSQL->q('START TRANSACTION');

    // 1. pick last non-edit action and collect all edit actions for removal
    $aUpdates = array();
    $acl_undo = null;
    $acl_prev = null;
    foreach($this->item['ACL'] as $acl){
        if($acl['aclActionPhase']!=2)
            continue;

        if($acl['aclActionID']==2){
            if(!$acl_undo)
                $aUpdates[] = $acl['aclGUID'];
            continue;
        }
        if( !$acl_undo ){
            $acl_undo = $acl;    
        } else {
            $acl_prev = $acl;
            break;
        }
        
    }

    $this->currentAction = new eiseAction($this, array('aclGUID'=>$acl_undo['aclGUID']));

    // 2. Pop previous action
    $sqlPop = "UPDATE {$this->conf["entTable"]} SET
            {$this->conf['prefix']}ActionLogID='{$acl_prev["aclGUID"]}'
            , {$this->conf['prefix']}StatusActionLogID='{$acl_prev["aclGUID"]}'
            , {$this->conf['prefix']}StatusID=".(int)$acl_undo["aclOldStatusID"]."
            , {$this->conf['prefix']}EditBy='{$this->intra->usrID}', {$this->conf['prefix']}EditDate=NOW()
            WHERE {$this->table['PK'][0]}='{$this->id}'";
    $this->oSQL->q($sqlPop);


    // 2. update item data with itemBefore
    $itemBefore = @json_decode( $acl_undo['aclItemBefore'], true );
    if ( count($itemBefore) ){
        $this->updateTable( $itemBefore , true);
    }

    // 3. remove action log entry
    $sqlDel = "UPDATE stbl_action_log SET aclActionPhase = 3
        , aclEditBy='{$this->intra->usrID}', aclEditDate=NOW()  
        WHERE aclGUID=".$this->oSQL->e($acl_undo['aclGUID']);
    $this->oSQL->q($sqlDel);

    // 4. remove status log entry
    $sqlDelStl = "DELETE FROM stbl_status_log WHERE stlArrivalActionID='{$acl_undo['aclGUID']}' AND stlEntityItemID='{$this->id}'";
    $this->oSQL->q($sqlDelStl);

    // 4. remove all collected "update" actions
    if (count($aUpdates)){
        $strToDel = "'".implode("','", $aUpdates)."'";
        $this->oSQL->q("DELETE FROM stbl_action_log WHERE aclGUID IN ({$strToDel})");
    }

    // $this->oSQL->showProfileInfo();
    // die('<pre>'.var_export($this->item['ACL'], true));

    $this->oSQL->q('COMMIT');

    $this->msgToUser = $this->intra->translate('Action is undone');
    $this->redirectTo = $_SERVER['PHP_SELF'].'?entID='.$this->entID."&ID=".urlencode($this->id);

}

public function updateMultiple($nd){
    
    $this->intra->batchStart();

    $class = get_class($this);
    $ids = explode('|', $nd[$this->conf['PK'].'s']);

    $this->preventRecursiveHooks($nd);

    foreach($nd as $key=>$value){
        if(!$value)
            unset($nd[$key]);
    }

    foreach($ids as $id){
        $o = new $class($id);
        $this->intra->batchEcho("Updating {$id}...", '');
        $o->update($nd);
        $this->intra->batchEcho(" done!");
    }
    $this->intra->batchEcho("All done!");
    die();    
}

public function delete(){

    $this->oSQL->q('START TRANSACTION');
    if( !$this->conf['STA'][$this->staID]['staFlagCanDelete'] ){
        $this->msgToUser = $this->intra->translate('Unable to delete "%s"', $this->conf['title'.$this->intra->local]);
        $this->redirectTo = $this->conf['form'].'?'.$this->getURI();
        return; 
    }
    if($this->conf['flagDeleteLogs']){
        $this->oSQL->q("DELETE FROM stbl_action_log WHERE aclEntityItemID=".$this->oSQL->e($this->id));
        $this->oSQL->q("DELETE FROM stbl_status_log WHERE stlEntityItemID=".$this->oSQL->e($this->id));
    }
    parent::delete();
    $this->oSQL->q('COMMIT');

}

private function init(){

    $sessKey = self::sessKeyPrefix.
        ($this->intra->conf['systemID'] ? $this->intra->conf['systemID'].':' : '')
        .$this->entID;

    if($_SESSION[$sessKey]){
    //if(false){
        $this->conf = array_merge($this->conf, $_SESSION[$sessKey]);
        return $this->conf;
    }

    $oSQL = $this->oSQL;

    // read entity information
    $this->ent = $oSQL->f("SELECT *, (SELECT GROUP_CONCAT(rolID) FROM stbl_role) as roles FROM stbl_entity WHERE entID=".$oSQL->e($this->entID));
    if (!$this->ent){
        throw new Exception("Entity '{$this->entID}' not found");
    }
    
    $arrRoles = explode(',', $this->ent['roles']); unset($this->ent['roles']);

    $this->conf = array_merge($this->conf, $this->ent);
    
    // read attributes
    $this->conf['ATR'] = array();
    $this->conf['attr_types'] = array();
    $sqlAtr = "SELECT * 
        FROM stbl_attribute 
        WHERE atrEntityID=".$oSQL->e($this->entID)."
             AND atrFlagDeleted=0
        ORDER BY atrOrder";
    $rsAtr = $oSQL->q($sqlAtr);
    while($rwAtr = $oSQL->f($rsAtr)){
        $this->conf['ATR'][$rwAtr['atrID']] = $rwAtr;
        $this->conf['attr_types'][$rwAtr['atrID']] = $rwAtr['atrType'];
    }

    // read status_attribute
    $this->conf['STA'] = array();
    $sqlSat = "SELECT stbl_status.*,stbl_status_attribute.*  
            FROM stbl_status_attribute 
                RIGHT OUTER JOIN stbl_status ON staID=satStatusID AND satEntityID=staEntityID
                INNER JOIN stbl_attribute ON atrID=satAttributeID AND atrFlagDeleted=0
        WHERE staEntityID=".$oSQL->e($this->entID)."
        ORDER BY staID, atrOrder";
    $rsSat = $oSQL->q($sqlSat);
    while($rwSat = $oSQL->f($rsSat)){
        
        if(!isset($this->conf['STA'][$rwSat['staID']])){
            $arrSta = array();
            foreach($rwSat as $key=>$val){
                if(strpos($key, 'sta')===0)
                    $arrSta[$key] = $val;
            }
            $this->conf['STA'][$rwSat['staID']] = $arrSta;
            if($arrSta['staFlagCanUpdate']){
                if(!isset($arrActUpd)){
                    $arrActUpd = $oSQL->f($oSQL->q('SELECT * FROM stbl_action WHERE actID=2'));
                    $arrActUpd['RLA'] = $arrRoles;
                }                    
                $this->conf['STA'][$rwSat['staID']]['ACT'][2] = array_merge($arrActUpd, array('actOldStatusID'=>$rwSat['staID'], 'actNewStatusID'=>$rwSat['staID']));
            }
            if($arrSta['staFlagCanDelete']){
                if(!isset($arrActDel)){
                    $arrActDel = $oSQL->f($oSQL->q('SELECT * FROM stbl_action WHERE actID=3'));
                    $arrActDel['RLA'] = $arrRoles;
                }
                $this->conf['STA'][$rwSat['staID']]['ACT'][3] = array_merge($arrActDel, array('actOldStatusID'=>$rwSat['staID'], 'actNewStatusID'=>null));
            }
        } 

        if($rwSat['satFlagShowInForm'])
            $this->conf['STA'][$rwSat['staID']]['satFlagShowInForm'][] = $rwSat['satAttributeID'];
        if($rwSat['satFlagEditable'])
            $this->conf['STA'][$rwSat['staID']]['satFlagEditable'][] = $rwSat['satAttributeID'];
        if($rwSat['satFlagShowInList'])
            $this->conf['STA'][$rwSat['staID']]['satFlagShowInList'][] = $rwSat['satAttributeID'];
        if($rwSat['satFlagTrackOnArrival'])
            $this->conf['STA'][$rwSat['staID']]['satFlagTrackOnArrival'][] = $rwSat['satAttributeID'];
          
    }
    
    // read action_attribute
    $this->conf['ACT'] = array();
    $sqlAAt = "SELECT stbl_action.*
        , (SELECT GROUP_CONCAT(rlaRoleID) FROM stbl_role_action WHERE rlaActionID=actID) as actRoles
        , stbl_action_attribute.* FROM stbl_action
        LEFT OUTER JOIN stbl_action_attribute 
            INNER JOIN stbl_attribute ON atrID=aatAttributeID AND atrFlagDeleted=0
        ON actID=aatActionID AND actFlagDeleted=0
        WHERE actEntityID=".$oSQL->e($this->entID)." OR actEntityID IS NULL
        ORDER BY atrOrder";
    $rsAAt = $oSQL->q($sqlAAt);
    while($rwAAt = $oSQL->f($rsAAt)){
        if(!isset($this->conf['ACT'][$rwAAt['actID']])){
            $arrAct = array();
            foreach($rwAAt as $key=>$val){
                if(strpos($key, 'act')===0)
                    $arrAct[$key] = $val;
            }
            
            $this->conf['ACT'][$rwAAt['actID']] = array_merge($arrAct, array('RLA'=>explode(',', $arrAct['actRoles'])));
            $this->conf['ACT'][$rwAAt['actID']]['actOldStatusID'] = array();
            $this->conf['ACT'][$rwAAt['actID']]['actNewStatusID'] = array();

            $ts = array('ATA'=>'aclATA', 'ATD'=>'aclATD', 'ETA'=>'aclETA', 'ETD'=>'aclETD');
            if (!$rwAAt["actFlagHasEstimates"]) {unset($ts["ETA"]);unset($ts["ETD"]);}
            if ($rwAAt["actFlagDepartureEqArrival"]) {unset($ts["ATD"]);unset($ts["ETD"]);}
            $this->conf['ACT'][$rwAAt['actID']]['aatFlagTimestamp'] = $ts;

        } 
        if($rwAAt['aatFlagToTrack'])
            $this->conf['ACT'][$rwAAt['actID']]['aatFlagToTrack'][$rwAAt['aatAttributeID']] = array('aatFlagEmptyOnInsert'=>(int)$rwAAt['aatFlagEmptyOnInsert']
                , 'aatFlagToChange'=>(int)$rwAAt['aatFlagToChange']
                , 'aatFlagTimestamp'=>$rwAAt['aatFlagTimestamp']
                , 'aatFlagUserStamp'=>$rwAAt['aatFlagUserStamp']
                );
        if($rwAAt['aatFlagMandatory'])
            $this->conf['ACT'][$rwAAt['actID']]['aatFlagMandatory'][$rwAAt['aatAttributeID']] = array('aatFlagEmptyOnInsert'=>(int)$rwAAt['aatFlagEmptyOnInsert']
                , 'aatFlagToChange'=>(int)$rwAAt['aatFlagToChange']);
        if($rwAAt['aatFlagTimestamp']){
            if (isset($this->conf['ACT'][$rwAAt['actID']]['aatFlagTimestamp'][$rwAAt['aatFlagTimestamp']]))
                $this->conf['ACT'][$rwAAt['actID']]['aatFlagTimestamp'][$rwAAt['aatFlagTimestamp']] = $rwAAt['aatAttributeID'];
        }
        if($rwAAt['aatFlagUserStamp']){
            $this->conf['ACT'][$rwAAt['actID']]['aatFlagUserStamp'][$rwAAt['aatAttributeID']] = $rwAAt['aatAttributeID'];
        }
            
    }

    // read action-status
    $sqlATS = "SELECT atsOldStatusID
        , atsNewStatusID
        , atsActionID
        FROM stbl_action_status
        INNER JOIN stbl_action ON actID=atsActionID
        LEFT OUTER JOIN stbl_status ORIG ON ORIG.staID=atsOldStatusID AND ORIG.staEntityID='{$this->entID}'
        LEFT OUTER JOIN stbl_status DEST ON DEST.staID=atsNewStatusID AND DEST.staEntityID='{$this->entID}'
        WHERE (actEntityID='{$this->entID}' 
            AND IFNULL(ORIG.staFlagDeleted,0)=0
            AND IFNULL(DEST.staFlagDeleted,0)=0
            ) OR actEntityID IS NULL
        ORDER BY atsOldStatusID, actPriority";
    $rsATS = $oSQL->q($sqlATS);
    while($rwATS = $oSQL->f($rsATS)){
        $this->conf['ACT'][$rwATS['atsActionID']]['aclOldStatusID'] = (isset($this->conf['ACT'][$rwATS['atsActionID']]['aclOldStatusID']) ? $this->conf['ACT'][$rwATS['atsActionID']]['aclOldStatusID'] : $rwATS['atsOldStatusID']);
        $this->conf['ACT'][$rwATS['atsActionID']]['aclNewStatusID'] = (isset($this->conf['ACT'][$rwATS['atsActionID']]['aclNewStatusID']) ? $this->conf['ACT'][$rwATS['atsActionID']]['aclNewStatusID'] : $rwATS['atsNewStatusID']);
        $this->conf['ACT'][$rwATS['atsActionID']]['actOldStatusID'][] = $rwATS['atsOldStatusID'];
        $this->conf['ACT'][$rwATS['atsActionID']]['actNewStatusID'][] = $rwATS['atsNewStatusID'];
        if($rwATS['atsOldStatusID']!==null)
            $this->conf['STA'][$rwATS['atsOldStatusID']]['ACT'][$rwATS['atsActionID']] = array_merge(
                    $this->conf['ACT'][$rwATS['atsActionID']]
                    , array('actOldStatusID'=>$rwATS['atsOldStatusID']
                        , 'actNewStatusID'=>$rwATS['atsNewStatusID'])
                    );
        if($this->conf['STA'][$rwATS['atsOldStatusID']]['ACT'][3]){
            unset($this->conf['STA'][$rwATS['atsOldStatusID']]['ACT'][3]);
            $this->conf['STA'][$rwATS['atsOldStatusID']]['ACT'][3] = $arrActDel;
        }
    }

    $_SESSION[$sessKey] = $this->conf;

    return $this->conf;

}

public function getList($arrAdditionalCols = Array(), $arrExcludeCols = Array()){

    $oSQL = $this->oSQL;
    $entID = $this->entID;

    $intra = $this->intra;
    $intra->requireComponent('list', 'batch');

    $conf = $this->conf;
    $strLocal = $this->intra->local;

    $listName = $entID;
    
    $this->staID = ($_GET[$this->entID."_staID"]==='' ? null : $_GET[$this->entID."_staID"]);

    $hasBookmarks = (boolean)$oSQL->d("SHOW TABLES LIKE 'stbl_bookmark'");

    $conf4list = $this->conf;

    unset($conf4list['ATR']);
    unset($conf4list['STA']);
    unset($conf4list['ACT']);

    $conf4list = array_merge($conf4list,
        Array('title'=>$this->conf["title{$strLocal}"].(
                $this->staID!==null
                ? ': '.($this->conf['STA'][$this->staID]["staTitle{$strLocal}Mul"] ? $this->conf['STA'][$this->staID]["staTitle{$strLocal}Mul"] : $this->conf['STA'][$this->staID]["staTitle{$strLocal}"])
                : '')
            ,  "intra" => $this->intra
            , "cookieName" => $listName.$this->staID.($_GET["{$listName}_{$listName}FlagMyItems"]==="1" ? 'MyItems' : '')
            , "cookieExpire" => time()+60*60*24*30
                , 'defaultOrderBy'=>"{$this->entID}EditDate"
                , 'defaultSortOrder'=>"DESC"
                , 'sqlFrom' => "{$this->conf["entTable"]} LEFT OUTER JOIN stbl_status ON {$entID}StatusID=staID AND staEntityID='{$entID}'".
                    ($hasBookmarks ? " LEFT OUTER JOIN stbl_bookmark ON bkmEntityID='{$entID}' AND bkmEntityItemID={$entID}ID" : '').
                    ((!in_array("actTitle", $arrExcludeCols) && !in_array("staTitle", $arrExcludeCols))
                        ? " LEFT OUTER JOIN stbl_action_log LAC
                        INNER JOIN stbl_action ON LAC.aclActionID=actID 
                        ON {$entID}ActionLogID=LAC.aclGUID
                        LEFT OUTER JOIN stbl_action_log SAC ON {$entID}StatusActionLogID=SAC.aclGUID"
                        : "")
        ));

    $lst = new eiseList($oSQL, $listName, $conf4list);

    $lst->addColumn(array('title' => ""
            , 'field' => $entID."ID"
            , 'PK' => true
            )
    );

    $lst->addColumn(array('title' => "##"
            , 'field' => "phpLNums"
            , 'type' => "num"
            )
        );

    if($hasBookmarks){
        // we add hidden column
        $fieldMyItems = $lst->name."FlagMyItems";
        $lst->addColumn(array('field' => $fieldMyItems
            , 'filter' => $fieldMyItems
            , 'type' => 'boolean'
            , 'sql'=> "bkmUserID='{$intra->usrID}' OR {$entID}InsertBy='{$intra->usrID}'"
            )
        );
    }

    if ( $this->intra->arrUsrData["FlagWrite"] && !in_array("ID_to_proceed", $arrExcludeCols) ){
        $lst->addColumn(array('title' => "sel"
                 , 'field' => "ID_to_proceed"
                 , 'sql' => $entID."ID"
                 , "checkbox" => true
                 )
        );   
    }
         
    $lst->addColumn(array('title' => $intra->translate("Number")
            , 'type'=>"text"
            , 'field' => $entID."Number"
            , 'sql' => $entID."ID"
            , 'filter' => $entID."ID"
            , 'order_field' => $entID."Number"
            , 'href'=> $conf["form"]."?".$this->getURI('['.$this->table['PK'][0].']')
            )
        );
    if($this->staID===null){
	    if (!in_array("staTitle", $arrExcludeCols))
	        $lst->addColumn(array('title' => $intra->translate("Status")
	            , 'type'=>"combobox"
	            , 'source'=>"SELECT staID AS optValue, staTitle{$strLocal} AS optText, staTitle{$strLocal} AS optTextLocal, staFlagDeleted as optFlagDeleted FROM stbl_status WHERE staEntityID='$entID'"
	            , 'defaultText' => "All"
	            , 'field' => "staTitle{$strLocal}"
	            , 'filter' => "staID"
	            , 'order_field' => "staID"
	            , 'width' => "100px"
	            , 'nowrap' => true
	            )
	        );
	} else {
		$lst->addColumn(array('field' => "staID"
	            , 'filter' => "staID"
	            , 'order_field' => "staID"
	            )
	        );
	}

    if (!in_array("aclATA", $arrExcludeCols))
        $lst->addColumn(array('title' => "ATA"
            , 'type'=>"date"
            , 'field' => "aclATA"
            , 'sql' => "IFNULL(SAC.aclATA, SAC.aclInsertDate)"
            , 'filter' => "aclATA"
            , 'order_field' => "aclATA"
            )
        );
    if (!in_array("actTitle", $arrExcludeCols))
        $lst->addColumn(array('title' => "Action"
            , 'type'=>"text"
            , 'field' => "actTitle{$strLocal}"
            , 'sql' => "CASE WHEN LAC.aclActionPhase=1 THEN CONCAT('Started \"', actTitle, '\"') ELSE actTitlePast END"
            , 'filter' => "actTitle{$strLocal}"
            , 'order_field' => "actTitlePast{$strLocal}"
            , 'nowrap' => true
            )
        );
            
    
    $strFrom = "";
    
    $iStartAddCol = 0;
    
    for ($ii=$iStartAddCol;$ii<count($arrAdditionalCols);$ii++){
        if($arrAdditionalCols[$iStartAddCol]['columnAfter']!='')
            break;
        $lst->Columns[] = $arrAdditionalCols[$iStartAddCol];
        $iStartAddCol=$ii;
    }

    foreach($this->conf['ATR'] as $atrID=>$rwAtr){
        
        if ($rwAtr["atrID"]==$entID."ID") // ID field to skip
            continue;

        if ($rwAtr["atrFlagHideOnLists"]) // if column should be hidden, skip
            continue;

        if(!empty($this->staID) && !in_array($rwAtr['atrID'], (array)$conf['STA'][$this->staID]['satFlagShowInList'])) // id statusID field is set and atrribute is not set for show, skip
            continue;
        
        $arr = array('title' => 
            ($rwAtr["atrShortTitle{$strLocal}"]!="" 
                            ? $rwAtr["atrShortTitle{$strLocal}"] 
                            : ($rwAtr["atrTitle{$strLocal}"]!=""
                                ? $rwAtr["atrTitle{$strLocal}"]
                                : ($rwAtr['atrShortTitle']!=''
                                    ? $rwAtr['atrShortTitle']
                                    : $rwAtr['atrTitle']
                                    )
                                )
                            )
            , 'type'=>($rwAtr['atrType']!="" ? $rwAtr['atrType'] : "text")
            , 'field' => $rwAtr['atrID']
            , 'filter' => $rwAtr['atrID']
            , 'order_field' => $rwAtr['atrID']
        );   
        
        switch($rwAtr['atrType']){
            case 'date':
                $arr['width'] = '80px';
                break;
            case 'datetime':
                $arr['width'] = '120px';
                break;
            case 'boolean':
            case 'checkbox':
                $arr['width'] = '25px';
                break;
            case 'integer':
            case 'real':
                $arr['width'] = '60px';
                break;
            default:
                break;
        }

        $arr['nowrap'] = true;
       
        if ($rwAtr['atrType']=="combobox" || $rwAtr['atrType']=="ajax_dropdown")
        if (!preg_match("/^Array/i", $rwAtr['atrProgrammerReserved']))
        { 
            $arr['source'] = $rwAtr['atrDataSource'];
            $arr['source_prefix'] = (strlen($rwAtr['atrProgrammerReserved'])==3 ? $rwAtr['atrProgrammerReserved'] : "");
            $arr['defaultText'] = $rwAtr['atrDefault'];
        } else 
            $arr['type'] = "text";
       
        $lst->Columns[$atrID] = $arr;
       
    }

    $cols = array_keys($lst->Columns);
    foreach((array)$arrAdditionalCols as $col){
        $fld = $col['field'];
        $colAfter = $col['columnAfter'] ? $col['columnAfter'] : $fld;
        if(!$colAfter){
            $col['fieldInsertBefore'] = $cols[0];
        } else {
            $col['fieldInsertAfter'] = $colAfter;
        }
        $lst->addColumn($col);
    }

    // check column-after
    for ($ii=$iStartAddCol;$ii<count($arrAdditionalCols);$ii++){
        if ($arrAdditionalCols[$ii]['columnAfter']==$rwAtr['atrID']){
            $lst->Columns[] = $arrAdditionalCols[$ii];
            
            while(isset($arrAdditionalCols[$ii+1]) && $arrAdditionalCols[$ii+1]['columnAfter']==""){
                $ii++;
                $lst->Columns[] = $arrAdditionalCols[$ii];
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
    
    return $lst;

}

public function getNewItemID($data = array()){
    return null;
}

public function newItem($nd = array()){

    $newID = $this->getNewItemID($nd);
    $sql = "INSERT INTO {$this->conf['table']} SET 
        {$this->conf['prefix']}InsertBy=".$this->oSQL->e($this->intra->usrID)."
        , {$this->conf['prefix']}InsertDate=NOW()
        ".($newID ? ", {$this->table['PK'][0]} = ".$this->oSQL->e($newID) : '');

    $this->oSQL->q($sql);

    $this->id = ($newID ? $newID : $this->oSQL->i());

    $this->doAction(new eiseAction($this, array('actID'=>1)));

}

public function insert($nd){

    $this->oSQL->q('START TRANSACTION');

    $this->newItem();

    $this->oSQL->q('COMMIT');

    parent::insert($nd);

}

public function doAction($oAct){
    $this->currentAction = $oAct;
    $oAct->execute();
    unset($this->currentAction);
    // do extra actions
    foreach ($this->extraActions as $act) {
        $this->currentAction = $act;
        $act->execute();
        unset($this->currentAction);
    }
}

public function updateUnfinishedActions($nd = null){

    if($nd===null)
        $nd = $_POST;

    foreach((array)$this->item['ACL'] as $aclGUID=>$rwACL){
        if ($rwACL["aclActionPhase"]>=2)
            continue;

        $this->updateAction($rwACL, $nd);

    }

}

public function updateAction($rwACL, $nd){

    $act = new eiseAction($this, $rwACL);
    $act->update($nd);

}

public function getData($id = null){
    
    parent::getData($id);

    unset($this->defaultDataToObtain[array_search('Master', $this->defaultDataToObtain)]);

    $this->getAllData();

    return $this->item;
}

function getAllData($toRetrieve = null){
    
    if(!$this->id)
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
        parent::getData();

    if(in_array('Text', $toRetrieve))    
        foreach($this->conf["ATR"] as $atrID=>$rwATR){
            if (in_array($rwATR["atrType"], Array("combobox", "ajax_dropdown"))){
                $this->item[$rwATR["atrID"]."_text"] = !isset($this->item[$rwATR["atrID"]."_text"]) 
                    ? $this->getDropDownText($rwATR, $this->item[$rwATR["atrID"]])
                    : $this->item[$rwATR["atrID"]."_text"];
            }

        }
    
    // collect incomplete/cancelled actions
    if(in_array('ACL', $toRetrieve) || in_array('STL', $toRetrieve)) {
        $this->item["ACL"]  = Array();
        $sqlACL = "SELECT * FROM stbl_action_log 
                WHERE aclEntityItemID='{$this->id}'
                ORDER BY aclATA DESC, aclOldStatusID DESC";
        $rsACL = $this->oSQL->do_query($sqlACL);
        while($rwACL = $this->oSQL->fetch_array($rsACL)){
            if($rwACL['aclActionPhase']<=2)
                $this->item["ACL"][$rwACL["aclGUID"]] = $this->getActionData($rwACL["aclGUID"]);
            else 
                $this->item["ACL_Cancelled"][$rwACL["aclGUID"]] = $this->getActionData($rwACL["aclGUID"]);
        }    
    }    
    
    // collect status log and nested actions
    if(in_array('STL', $toRetrieve)){
        $this->item["STL"] = Array();
        $sqlSTL = "SELECT * 
            , CASE WHEN stlStatusID=0 THEN 1 ELSE 0 END as flagDraft
            FROM stbl_status_log 
            WHERE stlEntityID='{$this->entID}' 
            AND stlEntityItemID=".$this->oSQL->e($this->id)."
            ORDER BY flagDraft, IFNULL(stlATA, NOW()) DESC";
        $rsSTL = $this->oSQL->q($sqlSTL);
        while($rwSTL = $this->oSQL->f($rsSTL)){
            $this->item['STL'][$rwSTL['stlGUID']] = $rwSTL;
        }
    }
    
    //comments
    if(in_array('comments', $toRetrieve)){
        $this->item["comments"] = Array();
        $sqlSCM = "SELECT * 
        FROM stbl_comments 
        WHERE scmEntityItemID='{$this->id}' ORDER BY scmInsertDate DESC";
        $rsSCM = $this->oSQL->do_query($sqlSCM);
        while ($rwSCM = $this->oSQL->f($rsSCM)){
            $this->item["comments"][$rwSCM["scmGUID"]] = $rwSCM;
        }
    }
    
    //files
    if(in_array('files', $toRetrieve)){
        $this->item["files"] = Array();
        $sqlFile = "SELECT * FROM stbl_file WHERE filEntityID='{$this->entID}' AND filEntityItemID='{$this->id}'
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

public function refresh(){
    
}


/**
 * This function is called after action is "planned", i.e. record is added to the Action Log. 
 * In case when something went wrong it should throw an exception.
 *
 * @category Events
 *
 * @param string $actID - action ID
 * @param int $oldStatusID - status ID to be moved from
 * @param int $newStatusID - destintation status ID 
 */
function onActionPlan($actID, $oldStatusID, $newStatusID){
    //parent::onActionPlan($actID, $oldStatusID, $newStatusID);
}

/**
 * This function is called after action is "started", i.e. Action Log record has changed its aclActionPhase=1.
 * In case when something went wrong it should throw an exception.
 *
 * @category Events
 *
 * @param string $actID - action ID
 * @param int $oldStatusID - status ID to be moved from
 * @param int $newStatusID - destintation status ID 
 */
function onActionStart($actID, $oldStatusID, $newStatusID){}

/**
 * This function is called after action is "finished", i.e. Action Log record has changed its aclActionPhase=2.
 * In case when something went wrong it should throw an exception.
 *
 * @category Events
 *
 * @param string $actID - action ID
 * @param int $oldStatusID - status ID to be moved from
 * @param int $newStatusID - destintation status ID 
 */
public function onActionFinish($actID, $oldStatusID, $newStatusID){

    if($actID==3){
        $this->delete();
    }

}

/**
 * This function is called on event when action is "cancelled", i.e. Action Log record has changed its aclActionPhase=3.
 * In case when something went wrong it should throw an exception and cancellation will be prevented.
 *
 * @category Events
 *
 * @param string $actID - action ID
 * @param int $oldStatusID - status ID to be moved from
 * @param int $newStatusID - destintation status ID 
 */
public function onActionCancel($actID, $oldStatusID, $newStatusID){}

/**
 * This function is called when user would like to undo given action, before anything's restored.
 * In case when something went wrong it should throw an exception.
 *
 * @category Events
 *
 * @param string $actID - action ID
 * @param int $oldStatusID - status ID to be moved from
 * @param int $newStatusID - destintation status ID 
 */
public function onActionUndo($actID, $oldStatusID, $newStatusID){}

/**
 * This function is called when item arrives to given status.
 * In case when something went wrong it should throw an exception.
 *
 * @category Events
 *
 * @param string $staID - status ID
 */
public function onStatusArrival($staID){}

/**
 * This function is called when item departs from given status.
 * In case when something went wrong it should throw an exception.
 *
 * @category Events
 *
 * @param string $staID - status ID
 */
public function onStatusDeparture($staID){}


public function form($fields = '',  $arrConfig=Array()){

    $defaultConf = array('flagAddJavaScript'=>True);

    $arrConfig = array_merge($defaultConf, $arrConfig);

    $hiddens .= $this->intra->field(null, 'entID', $this->entID, array('type'=>'hidden'));
    $hiddens .= $this->intra->field(null, 'aclOldStatusID', $this->staID , array('type'=>'hidden'));
    $hiddens .= $this->intra->field(null, 'aclNewStatusID',  "" , array('type'=>'hidden'));
    $hiddens .= $this->intra->field(null, 'actID', "", array('type'=>'hidden'));
    $hiddens .= $this->intra->field(null, 'aclGUID',  "", array('type'=>'hidden'));
    $hiddens .= $this->intra->field(null, 'aclToDo',  "", array('type'=>'hidden'));
    $hiddens .= $this->intra->field(null, 'aclComments',  "", array('type'=>'hidden'));

    $oldFW = $this->intra->arrUsrData['FlagWrite'];
    if(!$fields){
        if(!$this->conf['STA'][$this->staID]['staFlagCanUpdate']){
            $this->intra->arrUsrData['FlagWrite'] = false;
        }
        $fields = $this->getFields();
        $this->intra->arrUsrData['FlagWrite'] = $oldFW;
    }

    $flagAddJavaScript = $arrConfig['flagAddJavaScript'];

    $arrConfig['flagAddJavaScript'] = False;

    $form = parent::form($hiddens.$fields, $arrConfig)
        .($flagAddJavaScript
            ? "<script>$(document).ready(function(){ $('#{$this->table['prefix']}').eiseIntraForm().eiseIntraEntityItemForm();})</script>"
            : '');


    $this->intra->arrUsrData['FlagWrite'] = $oldFW;

    return $form;

}

public function form4list(){

    $this->conf['flagShowOnlyEditable'] = true;
    $htmlFields = $this->getFields();

    $htmlFields = $this->intra->fieldset($this->intra->translate('Set Data'), $htmlFields.(!$this->conf['radios'] 
            ? $this->intra->field(' ', null, $this->showActionButtons()) 
            : '')
        , array('class'=>'eiseIntraMainForm')).
        ($this->conf['radios'] ? $this->intra->fieldset($this->intra->translate('Action'), $this->showActionRadios()
            .$this->intra->field(' ', null, $this->intra->showButton('btnSubmit', $this->intra->translate('Run'), array('type'=>'submit')) )
            , array('class'=>'eiseIntraActions')) : '');

    return eiseItemTraceable::form($htmlFields, array('class'=>'ei-form-multiple eiseIntraMultiple'));

}

public function getFields($aFields = null){

    $aToGet = ($aFields!==null 
        ? $aFields 
        : ($this->conf['flagFormShowAllFields'] 
            ? array_keys($this->conf['ATR']) 
            : ($this->staID!==null
                ? ($this->conf['flagShowOnlyEditable'] 
                    ? $this->conf['STA'][$this->staID]['satFlagEditable']
                    : $this->conf['STA'][$this->staID]['satFlagShowInForm']
                    )
                : array())
            )
        );

    $allowEdit = (($this->staID!==null 
            ? $this->conf['STA'][$this->staID]['staFlagCanUpdate']
            : true ) 
        && $this->intra->arrUsrData['FlagWrite']);

    return $this->getAttributeFields($aToGet, $this->item, array('FlagWrite'=>$allowEdit));

}

function getAttributeFields($fields, $item = null, $conf = array()){

    $html = '';

    if($item===null)
        $item = $this->item;

    foreach($fields as $field){
        $atr = $this->conf['ATR'][$field];

        $options = array('type'=>$atr['atrType']
            , 'FlagWrite'=>($conf['forceFlagWrite'] 
                ? $conf['FlagWrite']
                : (in_array($field, (array)$this->conf['STA'][$this->staID]['satFlagEditable']) && $conf['FlagWrite']) )
            );

        if(in_array($atr['atrType'], array('combobox', 'select', 'ajax_dropdown')) ) { 
            
                if (preg_match("/^(vw|tbl)_/", $atr["atrDataSource"]) ) {
                    $options['source'] = $atr["atrDataSource"];
                } else if (preg_match("/^Array/i", $atr["atrProgrammerReserved"])){
                    eval ("\$options['source']={$atr["atrProgrammerReserved"]};");
                }
                if(strlen($atr["atrProgrammerReserved"])<=3)
                    $options['source_prefix'] = $atr["atrProgrammerReserved"];
                $options['defaultText'] = '-';
                
        }

        $html .= $this->intra->field($atr["atrTitle{$this->intra->local}"], $field.$conf['suffix'], $item[$field], $options);
    }

    return $html;

}

public function arrActionButtons(){

    GLOBAL $arrActions;
   
    $oSQL = $this->oSQL;
    $strLocal = $this->local;
    
    if (!$this->intra->arrUsrData["FlagWrite"])
        return;

    if($this->staID!==null){
        foreach((array)$this->conf['STA'][$this->staID]['ACT'] as $rwAct){

            if(count(array_intersect($this->intra->arrUsrData['roleIDs'], (array)$rwAct['RLA']))==0)
                continue;

            $title = ($rwAct["actTitle{$this->intra->local}"] ? $rwAct["actTitle{$this->intra->local}"] : $rwAct["actTitle"]) ;
              
            $strID = "btn_".$rwAct["actID"]."_".
                  $rwAct["actOldStatusID"]."_".
                  $rwAct["actNewStatusID"];

            $arrActions[] = Array ("title" => $title
                   , "action" => "#ei_action"
                   , 'id' => $strID
                   , "dataset" => array("action"=>array('actID'=>$rwAct["actID"]
                        , 'aclOldStatusID' => $rwAct["actOldStatusID"]
                        , 'aclNewStatusID' => $rwAct["actNewStatusID"])
                   )
                   , "class" => "{$rwAct["actButtonClass"]} "
                );
                  
        }
    } else {
        $arrActions[] = Array ("title" => $title
               , "action" => "#ei_action"
               , 'id' => "btn_1__0"
               , "dataset" => array("action"=>array('actID'=>1
                    , 'aclOldStatusID' => null
                    , 'aclNewStatusID' => 0)
               )
               , "class" => "ss_add "
            );
    }


   
   return $strOut;
}

public function showActionButtons(){
   
    $oSQL = $this->oSQL;
    $strLocal = $this->local;
    
    if (!$this->intra->arrUsrData["FlagWrite"])
        return;

    if($this->staID!==null){
        if(is_array($this->conf['STA'][$this->staID]['ACT'])){
            foreach($this->conf['STA'][$this->staID]['ACT'] as $rwAct){

                if(count(array_intersect($this->intra->arrUsrData['roleIDs'], (array)$rwAct['RLA']))==0)
                    continue;

                $title = $rwAct["actTitle{$this->intra->local}"];
                  
                $strID = "btn_".$rwAct["actID"]."_".
                      $rwAct["actOldStatusID"]."_".
                      $rwAct["actNewStatusID"];

                $strOut .= "<input type='".($rwAct['actID']==3 ? 'button' : 'submit')."' class=\"".($rwAct['actID']==3 ? ' eiseIntraDelete' : 'eiseIntraActionSubmit')."\" name='actButton' id='$strID' value='"
                        .htmlspecialchars($title)."'".
                        " act_id=\"{$rwAct["actID"]}\" orig=\"{$rwAct["actOldStatusID"]}\" dest=\"{$rwAct["actNewStatusID"]}\">";
                  
            }
        }
    } else {
        $strOut .= '<input type="submit" class="eiseIntraActionSubmit" name="actButton\" id="btn_1__0" value="'
                        .$this->intra->translate('Create').'"'.
                        ' act_id="1" orig="" dest="0">';
    }


   
   return $strOut;
}

function showActionRadios(){
   
    $oSQL = $this->oSQL;
    $strLocal = $this->local;
    
    if (!$this->intra->arrUsrData["FlagWrite"])
        return;

    if(is_array($this->conf['STA'][$this->staID]['ACT']))
        foreach($this->conf['STA'][$this->staID]['ACT'] as $rwAct){
            
            if($rwAct['actFlagDeleted'])
                continue;

            if(count(array_intersect($this->intra->arrUsrData['roleIDs'], $rwAct['RLA']))==0)
                continue;

            $arrRepeat = Array(($rwAct["actFlagAutocomplete"] ? "1" : "0") => (!$rwAct["actFlagAutocomplete"] ? $this->intra->translate("Plan") : ""));
            
            foreach($arrRepeat as $key => $value){
                $title = (in_array($rwAct["actID"], array(2, 3))
                   ? " - ".$rwAct["actTitle{$this->intra->local}"]." - "
                   : $rwAct["actTitle{$this->intra->local}"].
                      ($rwAct["actOldStatusID"]!=$rwAct["actNewStatusID"]
                      ?  " (".$this->conf['STA'][$rwAct["actOldStatusID"]]["staTitle{$this->intra->local}"]
                        ." > ".$this->conf['STA'][$rwAct["actNewStatusID"]]["staTitle{$this->intra->local}"].")"
                      :  "")
                );
              
                $strID = "rad_".$rwAct["actID"]."_".
                  $rwAct["actOldStatusID"]."_".
                  $rwAct["actNewStatusID"];

                $strOut .= "<input type='radio' name='actRadio' id='$strID' value='".$rwAct["actID"]."' class='eiseIntraRadio'".
                    " orig=\"{$rwAct["actOldStatusID"]}\" dest=\"{$rwAct["actNewStatusID"]}\"".
                    ($rwAct["actID"] == 2 || ($key=="1" && count($arrRepeat)>1) ? " checked": "")
                     .(!$rwAct["actFlagAutocomplete"] ? " autocomplete=\"false\"" : "")." /><label for='$strID' class='eiseIntraRadio'>".($value!="" ? "$value \"" : "")
                     .$title
                     .($value!="" ? "\"" : "")."</label><br />\r\n";
              
            }
        }

   return $strOut;

}

function showStatusLog($conf = array()){

    $html = '<div class="eif-stl">'."\n";

    foreach((array)$this->item["STL"] as $stlGUID => $rwSTL){

        if ($arrConfig['flagHideDraftStatusStay'] && $rwSTL['stlStatusID']==='0')
            continue;

        $rwSTA = $this->conf['STA'][$rwSTL['staID']];

        $htmlTiming = '<div class="dates"><span class="eiseIntra_stlATA">'
                        .($rwSTA["staTrackPrecision"] == 'datetime' 
                            ? $this->intra->datetimeSQL2PHP($rwSTL["stlATA"])
                            : $this->intra->dateSQL2PHP($rwSTL["stlATA"])).'</span>'."\n"
                        .'<span class="eiseIntra_stlATD">'
                        .( $rwSTL["stlATD"] 
                            ? ($rwSTA["staTrackPrecision"] == 'datetime'
                                ? $this->intra->datetimeSQL2PHP($rwSTL["stlATD"]) 
                                : $this->intra->dateSQL2PHP($rwSTL["stlATD"])
                                )
                            : $this->intra->translate("current time"))
                        .'</span></div>'."\n";

        $html .= $this->intra->field(($rwSTL["stlTitle{$this->intra->local}"]!="" 
                ? $rwSTL["stlTitle{$this->intra->local}"]
                : $rwSTL["staTitle"]), null, null
            , array('fieldClass'=>'eif-stl-title'
                , 'extraHTML'=> $htmlTiming));

    
        $html .= '<div class="eif-acl">'."\n";
        $stlATD = ($rwSTL['stlATD'] 
            ? ($rwSTA["staTrackPrecision"] == 'datetime'
                ? strtotime($rwSTL['stlATD'])
                : floor(strtotime($rwSTL['stlATD'].'+1 day') / (60 * 60 * 24)) * (60 * 60 * 24)
                )
            : mktime()
            );
        $stlATA = strtotime($rwSTL["stlATA"]);
        $html .= $this->showActionInfo($rwSTL['stlArrivalActionID'], $conf);
        foreach( $this->item['ACL'] as $aclGUID=>$rwACL ){
            $aclATA = strtotime($rwACL['aclATA']);
            if($rwACL['aclGUID']===$rwSTL['stlArrivalActionID']
                || $rwACL['aclGUID']===$rwSTL['stlDepartureActionID']
                || $rwACL['aclActionID'] == 2
                || $rwACL['aclActionPhase'] < 2
                || !($rwACL['aclOldStatusID']==$rwSTL['stlStatusID'] && $rwACL['aclNewStatusID']==$rwSTL['stlStatusID'])
                || !($stlATA <= $aclATA
                    && $aclATA <= $stlATD)
                )
                continue;
            //$html .= $rwACL['aclActionID'].': '.$stlATA.' <= '.$aclATA.' && '.$aclATA.' <= '.$stlATD.'<br>';
            $html .= '<pre>'.date('d.m.Y H:i:s', $stlATA).' <= '.date('d.m.Y H:i:s', $aclATA)
                    .' && '.date('d.m.Y H:i:s', $aclATA).' <= '.date('d.m.Y H:i:s', $stlATD).' '.$rwSTL['stlATD'].'</pre>'.$this->showActionInfo($aclGUID, $conf);
        }
        $html .= '</div>'."\n";
        
    }

    $html .= '</div>'."\n";

    return $html;
}

function showUnfinishedActions(){

    $html = '';

    foreach($this->item['ACL'] as $aclGUID=>$rwACL){
        if ($rwACL["aclActionPhase"]>=2)
            continue;

        $act = $this->conf['ACT'][$rwACL['aclActionID']];

        $flagWrite = $this->intra->arrUsrData['FlagWrite'] 
            && (count(array_intersect($this->intra->arrUsrData['roleIDs']
                , (array)$act['RLA']))>0);

        $html .= $this->showActionInfo($aclGUID
            , array('FlagWrite' => $flagWrite
                , 'forceFlagWrite' => true )
            );

        
        if($flagWrite) {

            $html .= '<div align="center">'."\n";

            if ($rwACL["aclActionPhase"]=="0" && !$act['actFlagDepartureEqArrival']){
                $html .= $this->intra->showButton("start_{$aclGUID}", $this->intra->translate("Start"), array('class'=>"eiseIntraActionButton"));
            }
            if ($rwACL["aclActionPhase"]=="1" || $act['actFlagDepartureEqArrival']){
                $html .= $this->intra->showButton("finish_{$aclGUID}", $this->intra->translate("Finish"), array('class'=>"eiseIntraActionButton"));
            }
            $html .= $this->intra->showButton("cancel_{$aclGUID}", $this->intra->translate("Cancel"), array('class'=>"eiseIntraActionButton"));

            $html .= "</div>\n";

        }
        
    }

    return $html;

}

function showActionInfo($aclGUID, $conf = array()){

        $defaultConf = array('forceFlagWrite'=>false);

        $conf = array_merge($defaultConf, $conf);

        $rwACL = (array)$this->item['ACL'][$aclGUID];
        $rwACT = (array)$this->conf['ACT'][$rwACL['aclActionID']];

        $html = '';

        $htmlTiming = '<div class="dates">'.$this->intra->showDatesPeriod($rwACL['aclATD'], $rwACL['aclATA'], $rwACT['actTrackPrecision']).'</div>'."\n";

        $actTitle = ($rwACL['aclActionPhase']==2 
            ? ($rwACT["actTitlePast{$this->intra->local}"]!="" 
                ? $rwACT["actTitlePast{$this->intra->local}"]
                : $rwACT["actTitlePast"])
            : ($rwACT["actTitle{$this->intra->local}"]!="" 
                ? $rwACT["actTitle{$this->intra->local}"]
                : $rwACT["actTitle"])
            );

        $html .= $this->intra->field($actTitle, null, null, array('fieldClass'=>'eif-acl-title'
                , 'extraHTML'=> $htmlTiming));

        $traced = $this->getTracedData(array_merge($rwACL, $rwACT));

        $html .= $this->getAttributeFields(array_keys((array)$rwACT['aatFlagToTrack']), $traced
            , array_merge($conf, array('suffix'=>'_'.$aclGUID))
            );

        $html .= ($rwACT['aclComments'] ? $this->intra->field($this->intra->translate('Comments', null, $rwACT['aclComments'])) : '');

        $html .= eval($actionCallBack.";");

        return $html;

}

function getTracedData($rwACL){

    if( $rwACL['aclItemTraced'] )
        return json_decode($rwACL['aclItemTraced'],true);

    $aRet = [];

    if(count($rwACL['aatFlagToTrack']) && $this->conf['logTable']){
        $sqlLog = "SELECT * FROM {$this->conf['logTable']} WHERE l{$this->table['prefix']}GUID='{$rwACL['aclGUID']}'";
        $rwLog = $this->oSQL->f($sqlLog);
        foreach ((array)$rwACL['aatFlagToTrack'] as $field=>$stuff){
            $rwATR = $this->conf['ATR'][$field];
            $aRet[$field] = $rwLog["l{$field}"];
            if (in_array($rwATR["atrType"], Array("combobox", 'select', "ajax_dropdown"))){
                $aRet[$rwATR["atrID"]."_text"] = $this->getDropDownText($rwATR, $aRet[$field]);
            }
        }
    }

    return $aRet;

}


function getActionDetails($q){

    $arrRet = Array();
          
    if (!$q['actID'] && !$q['aclGUID']){
        throw new Exception("Action details cannot be resolved: nether action not action log IDs provided", 1);
    }

    if($q['aclGUID']){
        $acl = $this->item['ACL'][$q['aclGUID']];
        if(!$acl)
            throw new Exception("Action details cannot be resolved: action log ID provided is wrong", 1);
        $act = $this->conf['ACT'][$acl['actID']];
    } else {
        $acl = $act = $this->conf['ACT'][$q['actID']];
    }

    $this->intra->json('ok', '', Array("acl"=>$acl 
        , 'act'=>$act
        , 'atr'=>$this->conf['ATR']
        ));
}

static function updateFiles($DataAction){
    
    GLOBAL $intra;
    
    $oSQL = $intra->oSQL;
    
    $usrID = $intra->usrID;
    $arrSetup = $intra->conf;
    
    $da = isset($_POST["DataAction"]) ? $_POST["DataAction"] : $_GET["DataAction"];
    
switch ($da) {

    case "deleteFile":

        $oSQL->q("START TRANSACTION");
        $rwFile = $oSQL->f("SELECT * FROM stbl_file WHERE filGUID='{$_GET["filGUID"]}'");

        $filesPath = self::checkFilePath($arrSetup["stpFilesPath"]);

        @unlink($filesPath.$rwFile["filNamePhysical"]);

        $oSQL->do_query("DELETE FROM stbl_file WHERE filGUID='{$_GET["filGUID"]}'");
        $nFiles = 
        $oSQL->q("COMMIT");

        if($rwFile)
            try {
                $item = new eiseEntityItem($oSQL, $intra, $rwFile['filEntityID'], $rwFile['filEntityItemID']);
            } catch (Exception $e) {}

        $msg = $intra->translate("Deleted files: %s", $nFiles);

        if($_SERVER['HTTP_X_REQUESTED_WITH']=='XMLHttpRequest' ){
            $intra->json( 'ok', $msg, ($item ? $item->getFiles() : array()) );
        }

        $intra->redirect($msg, ($item ? self::getFormURL($item->conf, $item->item) : ($_GET["referer"] ? $_GET["referer"] : 'about.php') ));
        
    
    case "attachFile":
        
        $entID = $_POST["entID_Attach"];

        $err = '';
        /*
        print_r($_POST);
        print_r($_FILES);
        print_r($_SERVER);
        die();
        //*/

        try {
            $filesPath = self::checkFilePath($arrSetup["stpFilesPath"]);
        } catch (Exception $e) {
            $error = $intra->translate("ERROR: file upload error: %s", $e->getMessage());
        }

        try {
            $item = new eiseEntityItem( $oSQL, $intra, $entID, $_POST['entItemID_Attach'] );
        } catch (Exception $e) {}

        $nFiles = 0;
        if($error==''){

            foreach($_FILES['attachment']['error'] as $ix => $err){
                if($err!=0) 
                    continue;

                $f = array(
                    'name'=> $_FILES['attachment']['name'][$ix]
                    , 'type' => $_FILES['attachment']['type'][$ix]
                    , 'size' => $_FILES['attachment']['size'][$ix]
                    , 'tmp_name' =>  $_FILES['attachment']['tmp_name'][$ix]
                    );

                $oSQL->q("START TRANSACTION");
                
                $fileGUID = $oSQL->d("SELECT UUID() as GUID");
                $filename = Date("Y/m/").$fileGUID.".att";
                                    
                if(!file_exists($filesPath.Date("Y/m")))
                    mkdir($filesPath.Date("Y/m"), 0777, true);
                
                copy($f["tmp_name"], $filesPath.$filename);
                
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
                    , '{$entID}'
                    , '{$_POST['entItemID_Attach']}'
                    , '{$f["name"]}'
                    , '$filename'
                    , '{$f["size"]}'
                    , '{$f["type"]}'
                    , '{$intra->usrID}', NOW(), '{$intra->usrID}', NOW());
                ";
                
                $oSQL->q($sqlFileInsert);
                
                $oSQL->q("COMMIT");

                $nFiles++;
            }
        }
        

        $msg = ($error 
            ? $error 
            : ($nFiles ? '' : 'ERROR: ').$intra->translate("Files uploaded: %s ", $nFiles));
        
        if($_SERVER['HTTP_X_REQUESTED_WITH']=='XMLHttpRequest' ){
            $intra->json( ($error!='' ? 'error' : 'ok'), $msg, ($item ? $item->getFiles() : array()) );
        }

        $intra->redirect($msg, ($item 
            ? self::getFormURL($item->conf, $item->item) 
            : $_SERVER["PHP_SELF"]."?{$this->idField}=".urlencode($_POST["entItemID_Attach"] )
            )
        );

       
    default: break;
}

}

public function getActionData($aclGUID){
    
    $oSQL = $this->oSQL;
    $entID = $this->entID;
    
    $arrRet = Array();
    
    if (!$aclGUID) return;
    
    $sqlACT = "SELECT ACL.*
       FROM stbl_action_log ACL
       WHERE aclGUID='{$aclGUID}'";
    
    $rwACT = $oSQL->fetch_array($oSQL->do_query($sqlACT));
    $rwACT['actID'] = $rwACT['aclActionID'];

    //$rwACT = @array_merge($this->conf['ACT'][$rwACT['aclActionID']], $rwACT);
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
    
    return $arrRet;
    
}

public function getDropDownText($arrATR, $value){

    $strRet = null;

    if ( ($arrATR["atrType"] == "combobox") && $arrATR["atrDataSource"]=='' && preg_match('/^Array\(/i', $arrATR["atrProgrammerReserved"]) ) {
        eval( '$arrOptions = '.$arrATR["atrProgrammerReserved"].';' );
        $strRet = ($arrOptions[$value]!=''
            ? $arrOptions[$value]
            : $arrATR["atrTextIfNull"]);
    } elseif ($arrATR["atrType"] == "combobox" && preg_match('/^Array\(/i', $arrATR["atrDataSource"]) ) {
        eval( '$arrOptions = '.$arrATR["atrDataSource"].';' );
        $strRet = ($arrOptions[$value]!=''
            ? $arrOptions[$value]
            : $arrATR["atrTextIfNull"]);
    } elseif ($arrATR["atrType"] == "combobox" && ($arrOptions = @json_decode($arrATR["atrDataSource"], true)) ) {
        $strRet = ($arrOptions[$value]!=''
            ? $arrOptions[$value]
            : $arrATR["atrTextIfNull"]);
    } else {
        $strRet = ($value != ""
            ? $this->oSQL->d($this->intra->getDataFromCommonViews($value, null, $arrATR["atrDataSource"], $arrATR["atrProgrammerReserved"], true))
            : $arrATR["atrTextIfNull"]
        );
    }

    return $strRet;
}

}

