<?php
include_once "inc_item.php";
include_once "inc_action.php";

define('MAX_STL_LENGTH', 256);

/**
 * This class incapsulates base functionality of single entity item.
 * 
 * @package eiseIntra
 * @uses eiseItem
 * @uses eiseAction
 */
class eiseItemTraceable extends eiseItem {

const sessKeyPrefix = 'ent:';
const statusField = 'StatusID';

/**
 * This property is used to store the entity properties of the item. It is being filled in ```init()``` method.
 * 
 * @category Configuration
 */
public $ent = array();


/**
 * ```$staID``` is the current status ID of the item. It is set after the item is created or loaded.
 * 
 * @category Events and Actions
 */
public $staID = null;

/**
 * This property is used to store the current action that is being performed on the item. It is set when the item is updated, deleted or any other action is performed.
 * 
 * @category Events and Actions
 */
public $currentAction = null;

public $extraActions = array();

/**
 * This property is used to specify which data should be obtained when the item is loaded. It is used in the ```getAllData()``` method to specify which data should be retrieved from the database.
 * 
 * Default values are: 
 * - 'Text' - text representation of reference data to be obtained from reference tables, 
 * - 'ACL' - action log, 
 * - 'STL' - status log,
 * - 'files' - files related to the item,
 * - 'messages' - messages related to the item.
 * 
 * 
 * @category Data Handling
 */
protected $defaultDataToObtain = array('Text', 'ACL', 'STL', 'files', 'messages');

protected $to_restore = [['key'=>'ACL', 'table'=>'stbl_action_log', 'PK'=>'aclGUID', 'title'=>'Action log']
        , ['key'=>'STL', 'table'=>'stbl_status_log', 'PK'=>'stlGUID', 'title'=>'Status log']
        , ['key'=>'files', 'table'=>'stbl_file', 'PK'=>'filGUID', 'title'=>'Files log']
        , ['key'=>'messages', 'table'=>'stbl_message', 'PK'=>'msgID', 'title'=>'Messages log']];

/** 
 * @ignore
*/
static function getPrefixExtra($prgRcv){
    $extra = '';
    $source_prefix = '';
    if(($prgRcv && strlen($prgRcv)<=3) || preg_match('/^([a-z0-9]{0,3})\|/', $prgRcv)){
        if(preg_match('/^([a-z0-9]{0,3})\|(.*)$/i', $prgRcv, $arrMatch)){
            $source_prefix = $arrMatch[1];
            if(trim($arrMatch[2]))
                $extra = $arrMatch[2];
        } else 
            $source_prefix = $prgRcv;
    }
    return [$source_prefix, $extra];
}

public $flagArchive = false; // if set to true, the item is archived: all data is obtained from archive table.

/**
 * @ignore
 */
public $entID = null; // entity ID of the item that this object will represent. Legacy property, use $conf['entID'] instead.

/**
 * This property is used to store the configuration of the item object. It is set in the constructor and can be overridden by passing a configuration array to the constructor.
 * 
 * @category Configuration
 */
private $conf_default = array(
    'flagDontCacheConfig' => false, // if set to true, the configuration will not be cached in the session
    'aExcludeReads' => array(), // array of read methods to exclude from the item object
    'aExcludeActions' => array(), // array of action methods to exclude from the item object
    'entID' => null, // entity ID of the item that this object will represent
    'entTable' => null, // entity table name, e.g. 'stbl_entity_item'
    'entTitle' => null, // entity title, e.g. 'Entity Item'
    'entTitleLocal' => null, // entity title in local language, e.g. 'Entity Item Local'
    'entTitleMul' => null, // entity title for multiple items, e.g. 'Entity Items'
    'entTitleLocalMul' => null, // entity title in local language for multiple items, e.g. 'Entity Items Local'
    'entPrefix' => null, // entity prefix, e.g. 'ent'

    'radios' => array(), // array of radio buttons to be shown in the item form

    'CHK' => array(), // checklists data
);

private $_aUser_Role_Tier;

/**
 * Array of virtual role members - key is role ID, value is array of user IDs. Can be used to cache virtual role members.
 * 
 * @category Events and Actions
 */
public $virtualRoleMembers = array();

/**
 * This constructor initializes the item object. If ```$id``` is not set, this will create an empty object with functionality that can be used to obtain list, craet new item etc.
 * 
 * The constructor requires ```$entID``` to be set in the configuration array. This is the entity ID of the item that this object will represent. Entity item configuration is obtained from the database and merged with the configuration array passed to the constructor.
 * 
 * Also this constructor defines Intra's DataAction and DataRead methods for the item object, so that it can be used to perform actions and read data from the database and pass it to the user.
 * 
 * @param mixed $id Item ID. If not set, this will create an empty item object.
 * @param array $conf Configuration array. If not set, this will use default configuration.
 * 
 * @category Configuration
 */
public function __construct($id = null,  $conf = array() ){
    
    GLOBAL $intra, $oSQL, $arrJS;

    $arrJS[] = eiseIntraJSPath."action.js";

    $this->conf = array_merge($this->conf, $this->conf_default, $conf);

    if(!$this->conf['entID'])
        throw new Exception ("Entity ID not set");

    $this->entID = $this->conf['entID'];

    $this->intra = (isset($conf['intra']) && $conf['intra'] !== ''  ? $conf['intra'] : $intra);
    $this->oSQL = (isset($conf['sql']) && $conf['sql'] !== '' ? $conf['sql'] : $oSQL);

    $this->init();

    $this->conf['title'] = (isset($conf['title']) && $conf['title']!=='' ? $conf['title'] : $this->conf['entTitle']);
    $this->conf['titleLocal'] = (isset($conf['titleLocal']) && $conf['titleLocal']!==''  ? $conf['titleLocal'] : $this->conf['entTitle'.$this->intra->local]);

    $this->conf['titleMul'] = (isset($this->conf['entTitleMul']) && $this->conf['entTitleMul']!==''  ? $this->conf['entTitleMul'] : $this->conf['title']);
    $this->conf['titleLocalMul'] = (isset($this->conf['entTitleLocalMul']) && $this->conf['entTitleLocalMul']!==''  ? $this->conf['entTitleLocalMul'] : $this->conf['titleLocal']);

    $this->conf['name'] = (isset($conf['name']) && $conf['name']!=='' ? $conf['name'] : (preg_replace('/^(tbl_|vw_)/', '', $this->conf['entTable'])));
    $this->conf['prefix'] = (isset($conf['prefix']) && $conf['prefix']!=='' ? $conf['prefix'] : ($this->conf['entPrefix']
        ? $this->conf['entPrefix']
        : $this->conf['entID'])
    );
    $this->conf['table'] = (isset($conf['table']) && $conf['table']!=='' ? $conf['table'] : $this->conf['entTable']);
    $this->conf['form'] = (isset($conf['form']) && $conf['form']!=='' ? $conf['form'] : $this->conf['name'].'_form.php');
    $this->conf['list'] = (isset($conf['list']) && $conf['list']!=='' ? $conf['list'] : $this->conf['name'].'_list.php');
    $this->conf['statusField'] = $this->conf['prefix'].self::statusField;
    $this->conf['flagFormShowAllFields'] = false;
    $this->conf['flagDeleteLogs'] = true;

    parent::__construct($id, $this->conf);

    if($this->id){
        $this->item_before = $this->item; 
        $this->staID = isset($this->item[$this->conf['statusField']]) ? $this->item[$this->conf['statusField']] : 0;
        foreach ((array)(isset($this->conf['RolesVirtual']) ? $this->conf['RolesVirtual'] : array()) as $ix => $rwRole) {
            if (isset($rwRole['rolID'])) {
                $roleMembers = $this->getVirtualRoleMembers($rwRole['rolID']);
                if(in_array(strtoupper($this->intra->usrID), array_keys($roleMembers))){
                    $this->intra->arrUsrData['roles'][] = isset($rwRole['rolTitle'.$this->intra->local]) ? $rwRole['rolTitle'.$this->intra->local] : '';
                    $this->intra->arrUsrData['roleIDs'][] = $rwRole['rolID'];
                } else {
                    $ix = array_search( $rwRole['rolID'], (array)$this->intra->arrUsrData['roleIDs'] );
                    if($ix!==false){
                        unset($this->intra->arrUsrData['roles'][$ix]);
                        unset($this->intra->arrUsrData['roleIDs'][$ix]);
                    }
                }
                $this->virtualRoleMembers[$rwRole['rolID']] = $roleMembers;
            }
        }
        $this->intra->arrUsrData['roles'] = array_values(is_array($this->intra->arrUsrData['roles']) ? $this->intra->arrUsrData['roles'] : []);
        $this->intra->arrUsrData['roleIDs'] = array_values(is_array($this->intra->arrUsrData['roleIDs']) ? $this->intra->arrUsrData['roleIDs'] : []);
        $this->RLAByMatrix();
    }

    $this->conf['flagSuperuser'] = 
        ($this->conf['entManagementRoles'] && !empty($this->intra->arrUsrData['roleIDs'])
        ? (bool)count(array_intersect($this->intra->arrUsrData['roleIDs']
                , explode(',', $this->conf['entManagementRoles'])))
        : false);

    ;

    $this->conf['attr_types'] = array_merge($this->table['columns_types'], (array)(isset($this->conf['attr_types']) ? $this->conf['attr_types'] : array()));

    $a_reads = array_diff(['getActionLog', 'getChecklist', 'getActionDetails', 'getFiles', 'getFile', 'getMessages','sendMessage']
        , $this->conf['aExcludeReads']);
    $a_actions = array_diff(['insert', 'update', 'updateMultiple', 'delete', 'attachFile', 'deleteFile'], $this->conf['aExcludeActions']);

    $this->intra->dataRead($a_reads, $this);
    $this->intra->dataAction($a_actions , $this);

}

/**
 * @ignore
 */
function checkForCheckboxes(&$nd){
    foreach ($this->conf['ATR'] as $atrID => $props) {
        if( in_array($props['atrType'], ['boolean', 'checkbox']) && !isset($nd[$atrID]) )
            $nd[$atrID] = 0;
    }
}

/**
 * This method does the same as original method from ```eiseItem``` class: it updated the item in the database.
 * 
 * In addition to that, it updates the master table, updates unfinished actions, updates roles virtual and performs the action.
 * 
 * ```$nd``` is normally the ```$_POST``` array or it could be artificially created array with data to update the item with. It may contain the following sections:
 *  - item data to update, e.g. 'itmTitle', 'itmComments', 'itmBillingDate', etc.
 *  - action data to perform, e.g. 'actID', 'aclNewStatusID', 'aclATA', 'aclComments' etc.
 *  - action-related data that comes with action. It comes with GUID-prefixed keys, e.g. '00418f83-2cc1-11ec-b619-000d3ad81bf0_itmBillingDate'. GUIDs are Action Log IDs from ```stbl_action_log``` table.
 * 
 * @param array $nd Array of data to update the item with. 
 * 
 * @category Data Handling
 * @category Events and Actions
 */
public function update($nd){

    parent::update($nd);

// $this->oSQL->startProfiling();

    $this->oSQL->q('START TRANSACTION');
    // 1. update master table
    $nd_ = $nd;
    $atrs = array_keys($this->conf['ATR']);
    $editable = (array)$this->conf['STA'][$this->staID]['satFlagEditable'];

    $this->checkForCheckboxes($nd_);

    foreach ($nd_ as $key => $value) {
        if(in_array($key, $atrs) && !in_array($key, $editable))
            unset($nd_[$key]);
    }

    $this->updateTable($nd_);
    $this->updateUnfinishedActions($nd);
    $this->updateRolesVirtual();
    $this->oSQL->q('COMMIT');

    $this->oSQL->q('START TRANSACTION');
    // 2. do the action
    $this->doAction(new eiseAction($this, $nd));

// $this->oSQL->showProfileInfo();
// die();
    
    $this->oSQL->q('COMMIT');

}

/**
 * Function ```updateTable``` updates the master table with data from the array ```$nd```. It also converts some attributes to foreign keys if they are of type 'ajax_dropdown', 'combobox' or 'radio'.
 * 
 * @category Data Handling
 * 
 */
public function updateTable($nd, $flagDontConvertToSQL = false){

    foreach ($this->conf['ATR'] as $atrID => $props) {
        if(in_array($props['atrType'], ['ajax_dropdown', 'combobox', 'radio'])){
            if( in_array($atrID, array_keys($nd) ) )
                foreach($this->table['columns'] as $field=>$field_props){
                    if($atrID==$field){
                        $this->table['columns'][$field]['DataType'] = 'FK';
                        $this->table['columns_types'][$field] = 'FK';
                    }
                }
        }
        
    }

    parent::updateTable($nd, $flagDontConvertToSQL);

}


/**
 * This method is called when the item is updated with full edit form. It updates the master table and updates unfinished actions.
 * 
 * @param array $nd Array of data to update the item with.
 * 
 * @category Data Handling 
 */
public function updateFullEdit($nd){

    $this->oSQL->q('START TRANSACTION');
    // 1. update master table

    $this->checkForCheckboxes($nd);

    $this->updateTable($nd);
    if (isset($this->item['ACL'])) {
        foreach($this->item['ACL'] as $aclGUID=>$rwACL){
            if(isset($rwACL['aclActionPhase']) && isset($rwACL['aclActionID']) && $rwACL['aclActionPhase']==2 && $rwACL['aclActionID']>4)
                $this->updateAction($rwACL, $nd);

        }
    }
    $this->oSQL->q('COMMIT');

    parent::update($nd);

}

/**
 * **Suparaction** is a special action that allows to put the item into any state. It is used for administrative purposes, e.g. to change the status of the item, add comments, etc.
 * 
 * @category Events and Actions
 */
public function superaction($nd){

    $oSQL = $this->oSQL;
    $oSQL->q('START TRANSACTION');
    $oSQL->startProfiling();

    try {
        $act = new eiseAction($this, array('actID'=>4
                , 'aclNewStatusID'=>isset($nd['aclNewStatusID']) ? $nd['aclNewStatusID'] : null
                , 'aclATA'=>isset($nd['aclATA']) ? $nd['aclATA'] : null
                , 'aclComments'=>isset($nd['aclComments']) ? $nd['aclComments'] : null));
        $act->execute();
    } catch (Exception $e) {
        $oSQL->q('ROLLBACK');
        throw $e;
    }


    $oSQL->q('COMMIT');
    parent::update($nd);

    // echo('<pre>'.var_export($nd, true));

    // $oSQL->showProfileInfo();
    // $oSQL->q('ROLLBACK');
    // die();
    
}

/**
 * This method undoes the last action performed on the item. It is used to revert the item to the state before the last action was performed.
 * 
 * It removes the last action from the action log, updates the item data with the data from the last action, and updates the status of the item to the status before the last action.
 * 
 * Record items from status log are also removed, so that the item is in the state before the last action was performed.
 *  
 * @param array $nd Array of data to update the item with. It is not used in this method, but it is required for compatibility with other methods.
 * 
 * @category Events and Actions
 */
public function undo($nd){

    $this->oSQL->q('START TRANSACTION');

    // 1. pick last non-edit action and collect all edit actions for removal
    $aUpdates = array();
    $acl_undo = null;
    $acl_prev = null;
    if (isset($this->item['ACL'])) {
        foreach($this->item['ACL'] as $acl){
            if(isset($acl['aclActionPhase']) && isset($acl['aclActionID']) && ($acl['aclActionPhase']!=2 || in_array($acl['aclActionID'], [1,2,3,4])))
                continue;

            if(isset($acl['aclActionID']) && in_array($acl['aclActionID'], [2])) {
                if(!$acl_undo && isset($acl['aclGUID']))
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
    }

    $acl_guid = isset($acl_undo['aclGUID']) ? $acl_undo['aclGUID'] : null;
    $this->currentAction = new eiseAction($this, array('aclGUID'=>$acl_guid));

    // 2. Pop previous action
    $acl_prev_guid = isset($acl_prev["aclGUID"]) ? $acl_prev["aclGUID"] : '';
    $acl_undo_old_status_id = isset($acl_undo["aclOldStatusID"]) ? $acl_undo["aclOldStatusID"] : 0;
    $sqlPop = "UPDATE {$this->conf["entTable"]} SET
            {$this->conf['prefix']}ActionLogID='{$acl_prev_guid}'
            , {$this->conf['prefix']}StatusActionLogID='{$acl_prev_guid}'
            , {$this->conf['prefix']}StatusID=".(int)$acl_undo_old_status_id."
            , {$this->conf['prefix']}EditBy='{$this->intra->usrID}', {$this->conf['prefix']}EditDate=NOW()
            WHERE {$this->table['PK'][0]}='{$this->id}'";
    $this->oSQL->q($sqlPop);


    // 2. update item data with itemBefore
    $itemBefore = array();
    if (isset($acl_undo['aclItemBefore'])) {
        $itemBefore = @json_decode( $acl_undo['aclItemBefore'], true );
    }
    if ( is_array($itemBefore) && count($itemBefore) ){
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

    // die('<pre>'.var_export($aUpdates, true));

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

/**
 * @ignore
 */
public function remove_stl($data){

    $this->redirectTo = $_SERVER['PHP_SELF'].'?entID='.$this->entID."&ID=".urlencode($this->id);

    $stl_remove = null;
    foreach ($this->item['STL'] as $stlGUID => $rwSTL) {
        if($stlGUID==$data['stlGUID']){
            $stl_remove = $rwSTL;
        }
    }

    $this->oSQL->q('START TRANSACTION');

    if(!$stl_remove['stlATD'])
         throw new Exception("Please use UNDO to remove status {$stl_remove['stlTitle']}", 1);
    
    // remove status log entry
    $sqlDelStl = "DELETE FROM stbl_status_log WHERE stlGUID='{$stl_remove['stlGUID']}'";
    $this->oSQL->q($sqlDelStl);

    // remove action log 
    $sqlDelStl = "DELETE FROM stbl_action_log WHERE aclGUID='{$stl_remove['stlArrivalActionID']}'";
    $this->oSQL->q($sqlDelStl);

    $this->msgToUser = $this->intra->translate("Status {$stl_remove['stlTitle']} is removed");

    $this->oSQL->q('COMMIT');

}

/**
 * @ignore
 */
public function remove_acl($data){

    $this->redirectTo = $_SERVER['PHP_SELF'].'?entID='.$this->entID."&ID=".urlencode($this->id);

    $stl_remove = null;
    $acl_remove = null;
    foreach ($this->item['STL'] as $stlGUID => $rwSTL) {
        if($rwSTL['stlArrivalActionID']==$data['aclGUID']){
            $stl_remove = $rwSTL;
            break;
        }
    }

    foreach ($this->item['ACL'] as $aclGUID => $rwACL) {
        if ($aclGUID == $data['aclGUID']) {
            $acl_remove = $rwACL;
            break;
        }
    }

    if($stl_remove && !$stl_remove['stlATD'])
        throw new Exception("Please use UNDO to remove action log entry \"{$this->conf['ACT'][$acl_remove['aclActionID']]['actTitlePast']}\"", 1);

    $this->oSQL->q('START TRANSACTION');

    // remove status log entry
    $sqlDelStl = "DELETE FROM stbl_status_log WHERE stlGUID='{$stl_remove['stlGUID']}'";
    $this->oSQL->q($sqlDelStl);

    // remove action log 
    $sqlDelStl = "DELETE FROM stbl_action_log WHERE aclGUID='{$acl_remove['aclGUID']}'";
    $this->oSQL->q($sqlDelStl);

    $this->msgToUser = $this->intra->translate("Action \"{$this->conf['ACT'][$acl_remove['aclActionID']]['actTitlePast']}\" is removed");

    $this->oSQL->q('COMMIT');

}

/**
 * This method updates multiple items of given class in the database when user submits multi-edit form on item list. Non-filled fields are ignored, so that only fields that are filled in the form are updated.
 * 
 * After item update it just dies, so this function should be called from the batch processing script.
 * 
 * @param array $nd Array of data to update the items with. It should contain the primary key list of the items to update in the format 'ID1|ID2|ID3', where ID1, ID2, ID3 are the IDs of the items to update.
 * 
 * @uses eiseIntraBatch
 * 
 * @category Data Handling
 */
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
        try {
            $o = new $class($id, $this->conf);    
        } catch (Exception $e) {
            die($e->getMessage());
        }
        
        $this->intra->batchEcho("Updating {$id}...", '');
        try {
            $o->update($nd);    
            $this->intra->batchEcho(" done!");
        } catch (Exception $e) {
            $this->intra->batchEcho('ERROR: '.$e->getMessage());
        }
        
    }
    $this->intra->batchEcho("All done!");
    die();    
}

/**
 * Method ```delete()``` deletes the item from the database. It also deletes all related action and status logs, if configured to do so.
 * 
 * Normally it triggers for 'delete' Data Action when user clicks 'Delete' button on the item form. It is also can be called from the batch processing script when user selects 'Delete' action on the selected item list. 
 * 
 * If you need to run multiple deletes in a single transaction, you should set ```$this->conf['flagNoDeleteTransation']``` to ```true``` before calling this method. This will prevent the method from starting a new transaction and will allow you to run multiple deletes in a single transaction and roll it back completely when something went wrong.
 * 
 * If you need to delete other item-related data, you should do it in the ```delete()``` method of the item class derived from the ```eiseItemTraceable```. 
 * 
 * @category Data Handling
 * 
 */
public function delete(){

    if(!$this->conf['flagNoDeleteTransation'])
        $this->oSQL->q("START TRANSACTION");

    if( !$this->conf['STA'][$this->staID]['staFlagCanDelete'] && !$this->conf['flagForceDelete']){
        throw new Exception($this->intra->translate('Unable to delete "%s"', $this->conf['title'.$this->intra->local]));
    }
    if($this->conf['flagDeleteLogs']){
        $aclGUIDs = "'".implode("', '", array_keys($this->item['ACL']))."'";
        if($this->conf['logTable'])
            $this->oSQL->q("DELETE FROM {$this->conf['logTable']} WHERE l{$this->table['prefix']}GUID 
                IN ( {$aclGUIDs} )");
        $this->oSQL->q("DELETE FROM stbl_action_log WHERE aclEntityItemID=".$this->oSQL->e($this->id));
        $this->oSQL->q("DELETE FROM stbl_status_log WHERE stlEntityItemID=".$this->oSQL->e($this->id));
    }
    parent::delete();

    if(!$this->conf['flagNoDeleteTransation'])
        $this->oSQL->q("COMMIT");

}

/**
 * This function initializes item configuration and entity properties. Being called for the first time, it reads entity configuration from the database and fills in the item properties with it. It also reads roles, attributes, actions and status information for the entity.
 * 
 * After the configuration is read, it is stored in the session for later use. If the configuration is already stored in the session, it is read from there.
 * 
 * For debug purposes you can comment line ```if($_SESSION[$sessKey] && !$this->conf['flagDontCacheConfig']){``` and uncomment line ```if(false){```. This will force the configuration to be read from the database every time the item is created, instead of reading it from the session. This is useful for debugging purposes, but it will slow down the application significantly.
 * 
 * @category Configuration
 */
private function init(){

    $sessKey = self::sessKeyPrefix.
        (isset($this->intra->conf['systemID']) && $this->intra->conf['systemID']!=='' ? $this->intra->conf['systemID'].':' : '')
        .$this->entID;

    if(isset($_SESSION[$sessKey]) && !$this->conf['flagDontCacheConfig']){
    // if(false){
        $this->conf = array_merge($this->conf, $_SESSION[$sessKey]);
        return $this->conf;
    }

    $oSQL = $this->oSQL;

    // read entity information
    $this->ent = $oSQL->f("SELECT * FROM stbl_entity WHERE entID=".$oSQL->e($this->entID));
    if (!$this->ent){
        throw new Exception("Entity '{$this->entID}' not found");
    }
    
    // roles
    $this->conf['Roles'] = array();
    $this->conf['RolesVirtual'] = array();
    $rsROL = $oSQL->q("SELECT * FROM stbl_role WHERE rolFlagDeleted=0 ORDER BY rolTitle{$this->intra->local} DESC");
    while ($rwROL = $oSQL->f($rsROL)) {
        $this->conf['Roles'][$rwROL['rolID']] = $rwROL;
        if($rwROL['rolFlagDefault'])
            $this->conf['RoleDefault'] = $rwROL['rolID'];
        if($rwROL['rolFlagVirtual'])
            $this->conf['RolesVirtual'][] = $rwROL;
    }

    if(!$this->conf['RoleDefault'])
        $this->conf['RoleDefault'] = '__ROLE_DEFAULT';

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
        // izintra compat fix
        // if(isset($rwAtr['atrPrefix']) && in_array($rwAtr['atrType'], array('combobox', 'ajax_dropdown') )) {
        //     $rwAtr['atrDataSource'] = $rwAtr['atrProgrammerReserved'];
        //     $rwAtr['atrProgrammerReserved'] = ($rwAtr['atrPrefix'] ? $rwAtr['atrPrefix'] : null);
        // }
        $this->conf['ATR'][$rwAtr['atrID']] = $rwAtr;
        $this->conf['attr_types'][$rwAtr['atrID']] = $rwAtr['atrType'];
    }

    // read action_attribute
    $this->conf['ACT'] = array();
    $this->conf['STA'] = array();

    $acts = array();

    $sqlAAt = "SELECT stbl_action.*
        ".($oSQL->d("SHOW TABLES LIKE 'stbl_role_action'")
            ? ", (SELECT GROUP_CONCAT(rlaRoleID) FROM stbl_role_action WHERE rlaActionID=actID) as actRoles"
            : '')
        ."
        , stbl_action_attribute.* FROM stbl_action
        LEFT OUTER JOIN stbl_action_attribute 
            INNER JOIN stbl_attribute ON atrID=aatAttributeID AND atrFlagDeleted=0
        ON actID=aatActionID AND actFlagDeleted=0
        WHERE actEntityID=".$oSQL->e($this->entID)." OR actEntityID IS NULL
        ORDER BY atrOrder";
    $rsAAt = $oSQL->q($sqlAAt);

    $acts = array();

    $acts[] = array (
            'actID' => '1',
            'actOldStatusID' => array ( NULL ),
            'actNewStatusID' => array ( '0' ),
            'actTrackPrecision' => 'datetime',
            'actTitle' => 'Create',
            'actTitleLocal' => $this->intra->translate('Create'),
            'actTitlePast' => 'Created',
            'actTitlePastLocal' => $this->intra->translate('Created'),
            'actButtonClass' => 'ss_add',
            'actDescription' => 'create new',
            'actDescriptionLocal' => $this->intra->translate('Create new'),
            'actFlagAutocomplete' => '1',
            'actRoles' => $this->conf['RoleDefault'],
            );
    $acts[] = array (
            'actID' => '2',
            'actOldStatusID' => array ( NULL ),
            'actNewStatusID' => array ( NULL ),
            'actTrackPrecision' => 'datetime',
            'actTitle' => 'Update',
            'actTitleLocal' => $this->intra->translate('Update'),
            'actTitlePast' => 'Updated',
            'actTitlePastLocal' => $this->intra->translate('Updated'),
            'actButtonClass' => 'ss_disk',
            'actDescription' => 'save data',
            'actDescriptionLocal' => $this->intra->translate('save data'),
            'actFlagAutocomplete' => '1',
            );
    $acts[] = array (
            'actID' => '3',
            'actOldStatusID' => array ( '0' ),
            'actNewStatusID' => array ( NULL ),
            'actTrackPrecision' => 'datetime',
            'actTitle' => 'Delete',
            'actTitleLocal' => $this->intra->translate('Delete'),
            'actTitlePast' => 'Deleted',
            'actTitlePastLocal' => $this->intra->translate('Deleted'),
            'actButtonClass' => 'ss_cancel',
            'actDescription' => 'delete',
            'actDescriptionLocal' => $this->intra->translate('delete'),
            'actFlagAutocomplete' => '1',
            'actFlagMultiple' => '1',
            'actPriority' => -100
            );
    $acts[] = array (
            'actID' => '4',
            'actOldStatusID' => array ( '0' ),
            'actNewStatusID' => array ( NULL ),
            'actTrackPrecision' => 'datetime',
            'actTitle' => 'Superaction',
            'actTitleLocal' => $this->intra->translate('Superaction'),
            'actTitlePast' => 'Administered',
            'actTitlePastLocal' => $this->intra->translate('Administered'),
            'actButtonClass' => 'ss_exclamation',
            'actDescription' => 'put it to any state',
            'actDescriptionLocal' => $this->intra->translate('put it to any state'),
            'actFlagAutocomplete' => '1',
            'actRoles' => $this->conf['entManagementRoles'] ,
            );

    while($rwAAt = $oSQL->f($rsAAt)){ 
        if(isset($rwAAt['actFlagDepartureEqArrival']) && !isset($rwAAt['actFlagHasDeparture'])){ $rwAAt['actFlagHasDeparture'] = !$rwAAt['actFlagDepartureEqArrival']; }
        if(isset($rwAAt['actFlagMultistage']) && !isset($rwAAt['actFlagAutocomplete'])){ $rwAAt['actFlagMultistage'] = !$rwAAt['actFlagAutocomplete']; }
        if(!$rwAAt['actTitleLocal']) $rwAAt['actTitleLocal'] = $rwAAt['actTitle'];
        if(!$rwAAt['actTitlePast']) $rwAAt['actTitlePast'] = $rwAAt['actTitle'];
        if(!$rwAAt['actTitlePastLocal']) $rwAAt['actTitlePastLocal'] = $rwAAt['actTitlePast'];
        $acts[] = $rwAAt; 
    }

    foreach ($acts as $rwAAt) {

        if(!isset($this->conf['ACT'][(string)$rwAAt['actID']])){
            $arrAct = array();
            foreach($rwAAt as $key=>$val){
                if(strpos($key, 'act')===0)
                    $arrAct[$key] = $val;
            }
            
            $this->conf['ACT'][$rwAAt['actID']] = array_merge($arrAct, array('RLA'=>(isset($arrAct['actRoles']) &&  $arrAct['actRoles'] 
                ? preg_split('/[,;\s]+/', $arrAct['actRoles']) 
                : array())));
            $this->conf['ACT'][$rwAAt['actID']]['actOldStatusID'] = array();
            $this->conf['ACT'][$rwAAt['actID']]['actNewStatusID'] = array();

            $ts = array('ATA'=>'aclATA', 'ATD'=>'aclATD', 'ETA'=>'aclETA', 'ETD'=>'aclETD');
            if ( !(isset($rwAAt["actFlagHasEstimates"]) && $rwAAt["actFlagHasEstimates"]) ) {unset($ts["ETA"]);unset($ts["ETD"]);}
            if ( !(isset($rwAAt["actFlagHasDeparture"]) && $rwAAt["actFlagHasDeparture"]) ) {unset($ts["ATD"]);unset($ts["ETD"]);}
            $this->conf['ACT'][$rwAAt['actID']]['aatFlagTimestamp'] = $ts;

        } 
        if(isset($rwAAt['aatFlagToTrack']) && $rwAAt['aatFlagToTrack'])
            $this->conf['ACT'][$rwAAt['actID']]['aatFlagToTrack'][$rwAAt['aatAttributeID']] = array('aatFlagEmptyOnInsert'=>(int)$rwAAt['aatFlagEmptyOnInsert']
                , 'aatFlagToChange'=>(int)$rwAAt['aatFlagToChange']
                , 'aatFlagTimestamp'=>$rwAAt['aatFlagTimestamp']
                , 'aatFlagUserStamp'=>$rwAAt['aatFlagUserStamp']
                );
        if(isset($rwAAt['aatFlagMandatory']) && $rwAAt['aatFlagMandatory'])
            $this->conf['ACT'][$rwAAt['actID']]['aatFlagMandatory'][$rwAAt['aatAttributeID']] = array('aatFlagEmptyOnInsert'=>(int)$rwAAt['aatFlagEmptyOnInsert']
                , 'aatFlagToChange'=>(int)$rwAAt['aatFlagToChange']);
        if(isset($rwAAt['aatFlagTimestamp']) && $rwAAt['aatFlagTimestamp']){
            if (isset($this->conf['ACT'][$rwAAt['actID']]['aatFlagTimestamp'][$rwAAt['aatFlagTimestamp']]))
                $this->conf['ACT'][$rwAAt['actID']]['aatFlagTimestamp'][$rwAAt['aatFlagTimestamp']] = $rwAAt['aatAttributeID'];
        }
        if(isset($rwAAt['aatFlagUserStamp']) && $rwAAt['aatFlagUserStamp']){
            $this->conf['ACT'][$rwAAt['actID']]['aatFlagUserStamp'][$rwAAt['aatAttributeID']] = $rwAAt['aatAttributeID'];
        }
            
    }

    // read status_attribute
    $this->conf['STA'] = array();
    $sqlSat = "SELECT stbl_status.*,stbl_status_attribute.*  
        FROM stbl_status_attribute 
                RIGHT OUTER JOIN stbl_status ON staID=satStatusID AND satEntityID=staEntityID AND staFlagDeleted=0
                LEFT OUTER JOIN stbl_attribute ON atrID=satAttributeID AND atrEntityID=satEntityID
        WHERE staEntityID=".$oSQL->e($this->entID)."
            AND IFNULL(atrFlagDeleted,0)=0
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
                    $arrActUpd = $this->conf['ACT'][2];
                    $arrActUpd['RLA'] = array($this->conf['RoleDefault']);
                }     
                $this->conf['STA'][$rwSat['staID']]['ACT'][2] = array_merge($arrActUpd, array('actOldStatusID'=>array($rwSat['staID']), 'actNewStatusID'=>array($rwSat['staID'])));
            }
            if($arrSta['staFlagCanDelete']){
                if(!isset($arrActDel)){
                    $arrActDel = $this->conf['ACT'][3];
                    $arrActDel['RLA'] = array($this->conf['RoleDefault']);
                }
                $this->conf['STA'][$rwSat['staID']]['ACT'][3] = array_merge($arrActDel, array('actOldStatusID'=>array($rwSat['staID']), 'actNewStatusID'=>array( null )));
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

    $arrActDel = isset($arrActDel) ? $arrActDel : null;
    
    // read action-status
    $sqlATS = "SELECT atsOldStatusID
        , atsNewStatusID
        , atsActionID
        FROM stbl_action_status
        INNER JOIN stbl_action ON actID=atsActionID AND actFlagDeleted=0
        LEFT OUTER JOIN stbl_status ORIG ON ORIG.staID=atsOldStatusID AND ORIG.staEntityID='{$this->entID}'
        LEFT OUTER JOIN stbl_status DEST ON DEST.staID=atsNewStatusID AND DEST.staEntityID='{$this->entID}'
        WHERE (actEntityID='{$this->entID}' 
            AND IFNULL(ORIG.staFlagDeleted,0)=0
            AND IFNULL(DEST.staFlagDeleted,0)=0
            ) OR actEntityID IS NULL
        ORDER BY atsOldStatusID, actPriority DESC, actNewStatusID";
    $rsATS = $oSQL->q($sqlATS);
    while($rwATS = $oSQL->f($rsATS)){
        $this->conf['ACT'][$rwATS['atsActionID']]['aclOldStatusID'] = (isset($this->conf['ACT'][$rwATS['atsActionID']]['aclOldStatusID']) ? $this->conf['ACT'][$rwATS['atsActionID']]['aclOldStatusID'] : $rwATS['atsOldStatusID']);
        $this->conf['ACT'][$rwATS['atsActionID']]['aclNewStatusID'] = (isset($this->conf['ACT'][$rwATS['atsActionID']]['aclNewStatusID']) ? $this->conf['ACT'][$rwATS['atsActionID']]['aclNewStatusID'] : $rwATS['atsNewStatusID']);
        if($rwATS['atsActionID'] > 5){
            $this->conf['ACT'][$rwATS['atsActionID']]['actOldStatusID'][] = $rwATS['atsOldStatusID'];
            $this->conf['ACT'][$rwATS['atsActionID']]['actNewStatusID'][] = $rwATS['atsNewStatusID'];    
        }
        if($rwATS['atsOldStatusID']!==null){
            $this->conf['ACT'][$rwATS['atsActionID']]['actOldStatusID'][] = $rwATS['atsOldStatusID'];
            $this->conf['ACT'][$rwATS['atsActionID']]['actNewStatusID'][] = $rwATS['atsNewStatusID'];
            $this->conf['STA'][$rwATS['atsOldStatusID']]['ACT'][$rwATS['atsActionID']] = &$this->conf['ACT'][$rwATS['atsActionID']];
        }
        

        if($rwATS['atsOldStatusID'] && isset($this->conf['STA'][$rwATS['atsOldStatusID']]['ACT'][3]) && $this->conf['STA'][$rwATS['atsOldStatusID']]['ACT'][3]){
            unset($this->conf['STA'][$rwATS['atsOldStatusID']]['ACT'][3]);
            $this->conf['STA'][$rwATS['atsOldStatusID']]['ACT'][3] = $arrActDel;
        }
    }

    
    foreach ($this->conf['ACT'] as &$act) {
        $act['actOldStatusID'] = array_values(array_unique($act['actOldStatusID']));
        $act['actNewStatusID'] = array_values(array_unique($act['actNewStatusID']));
    }

    // matrix
    if($this->conf['entMatrix']){
        foreach((array)json_decode($this->conf['entMatrix'], true) as $mtx){
            $this->conf['ACT'][$mtx['mtxActionID']]['MTX'][] = $mtx;
        }    
    }

    $aAtrMTX = array();
    foreach ($this->conf['ATR'] as $atrID => $atr) {
        if($atr['atrMatrix'])
            $aAtrMTX[$atrID] = preg_replace('/[^\<\>\=]/', '', $atr['atrMatrix']);
    }
    $this->conf['AtrMTX'] = $aAtrMTX;


    foreach ($this->conf['STA'] as &$sta) {
        if(isset($sta['ACT']) && $sta['ACT'])
            usort($sta['ACT'], array($this, '_sort_STA_ACT'));
    }

    if($oSQL->d("SHOW TABLES LIKE 'stbl_checklist'")) {

        $sqlCHK = "SELECT * FROM stbl_checklist WHERE chkEntityID=".$oSQL->e($this->entID)." ORDER BY chkTargetStatusID, chkID";
        $rsCHK = $oSQL->do_query($sqlCHK);
        while($rwCHK = $oSQL->fetch_array($rsCHK)){
           $this->conf['CHK'][] = $rwCHK;
           $this->conf['STA'][$rwCHK['chkTargetStatusID']]['chk'][] = $rwCHK;
        }

    }


    $_SESSION[$sessKey] = $this->conf;

    return $this->conf;

}

/**
 * @ignore
 */
function _sort_STA_ACT($a, $b){
    return (isset($b['actNewStatusID'][0]) ?  $b['actNewStatusID'][0] : 0)
        - (isset($a['actNewStatusID'][0]) ? $a['actNewStatusID'][0] : 0);
}

/**
 * This function calculates the roles that are allowed to perform actions on the item based on the matrix defined in the entity configuration. It iterates through each action and checks the matrix conditions against the item's attributes. If the conditions are met, it allows role members to run the action.
 * 
 * The matrix is defined in ```entMarix``` field of ```stbl_entity``` table. This is JSON string which contains conditions for some special attributes of the item, and the roles that are allowed to perform the action if the conditions are met.
 * 
 * These values are to be set for each action on eiseAdmin's action configuration form.
 * 
 * Matrix is loaded upon item initialization and then cached in user session.
 * 
 * @category Events and Actions
 */
public function RLAByMatrix(){

    if(!$this->conf['entMatrix'])
        return;

    $aAtrMTX = isset($this->conf['AtrMTX']) ? $this->conf['AtrMTX'] : array();

    foreach ($this->conf['STA'] as $staID => $rwSTA) {
        $rwSTA['ACT'] = isset($rwSTA['ACT']) ? $rwSTA['ACT'] : array();
        foreach ($rwSTA['ACT'] as $actID => $act) {
            if( !(isset($act['MTX']) && $act['MTX']) )
                continue;
            $rla = array();
            $rla_tiers = array();
            foreach ($act['MTX'] as $mtx) {
                $conditionWorked = array();
                $tierConditionWorked = array();
                foreach ((array)$aAtrMTX as $atrID => $condition) {
                    $mtxKey = preg_replace('/^'.preg_quote($this->conf['entPrefix'], '/').'/', 'mtx', $atrID);
                    $valueMTX = isset($mtx[$mtxKey]) ? $mtx[$mtxKey] : null;
                    $valueItem = $this->item[$atrID];
                    $condition = ($condition=='=' ? '==' : $condition);
                    $toEval = "\$conditionWorked[\$atrID] = (int)(\$valueItem {$condition} \$valueMTX);";
                    $tierConditionWorked[$atrID] = 0;
                    if($valueMTX===null || $valueMTX==='' || $valueMTX==='%'){
                        $conditionWorked[$atrID] = 1;
                        continue;
                    }
                    eval($toEval);
                    if($conditionWorked[$atrID])
                        $tierConditionWorked[$atrID] = 1;
                }
                if(count($conditionWorked)>0 && array_sum($conditionWorked)/count($conditionWorked)===1){
                    $mtxRoleID = isset($mtx['mtxRoleID']) ? $mtx['mtxRoleID'] : '';
                    $rla[] = $mtxRoleID;
                    $nTier = count($aAtrMTX) - array_sum($tierConditionWorked);
                    $rla_tiers[$mtxRoleID] = isset($rla_tiers[$mtxRoleID])
                        ? ( $nTier < $rla_tiers[$mtxRoleID] ? $nTier : $rla_tiers[$mtxRoleID] )
                        : $nTier;
                }
            } 
            $this->conf['STA'][$staID]['ACT'][$actID]['RLA'] = array_values(array_unique($rla)); 
            $this->conf['STA'][$staID]['ACT'][$actID]['RLA_tiers'] = $rla_tiers; 
            asort($this->conf['STA'][$staID]['ACT'][$actID]['RLA_tiers']);
        }
    }
    // echo 'rla by action';
    // die( '<pre>'.var_export($this->conf['ACT'][42199], true).'</pre>' );
}

/**
 * 
 * This function returns a list of items based on the entity configuration and the current status ID. It uses the intra component to create a list object and adds columns to it based on the entity's attributes, actions, and status.
 * 
 * @category Data Display
 * @uses eiseList
 * 
 * @return eiseList
 */
public function getList($arrAdditionalCols = Array(), $arrExcludeCols = Array()){

    $oSQL = $this->oSQL;
    $entID = $this->entID;

    $intra = $this->intra;
    $intra->requireComponent('list', 'batch');

    $conf = $this->conf;
    $strLocal = $this->intra->local;

    $prfx = $this->conf['entPrefix'];
    $listName = $prfx;
    
    $this->staID = (!isset($_GET[$prfx."_staID"]) || $_GET[$prfx."_staID"]==='' || (isset($_GET['DataAction']) && $_GET['DataAction']==='json') 
        ? null 
        : (isset($_GET[$prfx."_staID"]) ? $_GET[$prfx."_staID"] : null));
    // $this->staID = ($_GET[$prfx."_staID"]==='' ? null : $_GET[$prfx."_staID"]);

    $hasBookmarks = (boolean)$oSQL->d("SHOW TABLES LIKE 'stbl_bookmark'");

    $conf4list = $this->conf;

    unset($conf4list['ATR']);
    unset($conf4list['STA']);
    unset($conf4list['ACT']);

    $staTitle = isset($this->conf['STA'][$this->staID]["staTitle{$strLocal}Mul"])
        ? $this->conf['STA'][$this->staID]["staTitle{$strLocal}Mul"]
        : (isset($this->conf['STA'][$this->staID]["staTitle{$strLocal}"]) ? $this->conf['STA'][$this->staID]["staTitle{$strLocal}"] : '');
    $conf4list = array_merge($conf4list,
        Array('title'=>$this->conf["title{$strLocal}Mul"].(
                $this->staID!==null
                ? ': '.$staTitle
                : '')
            ,  "intra" => $this->intra
            , "cookieName" => $listName.$this->staID.(isset($_GET["{$listName}_{$listName}FlagMyItems"]) && $_GET["{$listName}_{$listName}FlagMyItems"]==="1" ? 'MyItems' : '')
            , "cookieExpire" => time()+60*60*24*30
                , 'defaultOrderBy'=>"{$prfx}EditDate"
                , 'defaultSortOrder'=>"DESC"
                , 'sqlFrom' => "{$this->conf["entTable"]} LEFT OUTER JOIN stbl_status ON {$prfx}StatusID=staID AND staEntityID='{$entID}'".
                    ($hasBookmarks ? " LEFT OUTER JOIN stbl_bookmark ON bkmEntityID='{$entID}' AND bkmEntityItemID={$prfx}ID" : '').
                    ((!in_array("actTitle", $arrExcludeCols) && !in_array("staTitle", $arrExcludeCols))
                        ? " LEFT OUTER JOIN stbl_action_log LAC
                        INNER JOIN stbl_action ON LAC.aclActionID=actID 
                        ON {$prfx}ActionLogID=LAC.aclGUID
                        LEFT OUTER JOIN stbl_action_log SAC ON {$prfx}StatusActionLogID=SAC.aclGUID"
                        : "")
        ));

    $listClass = isset($conf4list['listClass']) && $conf4list['listClass'] ? $conf4list['listClass'] : 'eiseList';

    $lst = new $listClass($oSQL, $listName, $conf4list);

    $lst->addColumn(array('title' => ""
            , 'field' => $prfx."ID"
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
            , 'sql'=> "bkmUserID='{$intra->usrID}' OR {$prfx}InsertBy='{$intra->usrID}'"
            )
        );
    }

    if ( $this->intra->arrUsrData["FlagWrite"] && !in_array("ID_to_proceed", $arrExcludeCols) ){
        $lst->addColumn(array('title' => "sel"
                 , 'field' => "ID_to_proceed"
                 , 'sql' => $prfx."ID"
                 , "checkbox" => true
                 )
        );   
    }
         
    $lst->addColumn(array('title' => $intra->translate("Number")
            , 'type'=>"text"
            , 'field' => $prfx."Number"
            , 'sql' => $prfx."ID"
            , 'filter' => $prfx."ID"
            , 'order_field' => $prfx."Number"
            , 'href'=> $conf["form"]."?".$this->getURI('['.$this->table['PK'][0].']')
            )
        );
    if($this->staID===null){
        if (!in_array("staTitle", $arrExcludeCols))
            $lst->addColumn(array('title' => $intra->translate("Status")
                , 'type' => "combobox"
                , 'source'=>"SELECT staID AS optValue, staTitle{$intra->local} AS optText, staTitle{$intra->local} AS optTextLocal, staFlagDeleted as optFlagDeleted FROM stbl_status WHERE staEntityID='$entID'"
                , 'defaultText' => "All"
                , 'field' => "staTitle{$intra->local}"
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

    if (!in_array("actTitle", $arrExcludeCols))
        $lst->addColumn(array('title' => "Action"
            , 'type'=>"text"
            , 'field' => "actTitle{$intra->local}"
            , 'sql' => "CASE WHEN LAC.aclActionPhase=1 THEN CONCAT('Started \"', actTitle, '\"') ELSE actTitlePast END"
            , 'filter' => "actTitle{$intra->local}"
            , 'order_field' => "actTitlePast{$intra->local}"
            , 'nowrap' => true
            )
        );
            
    
    $strFrom = "";
    
    $iStartAddCol = 0;
    
    if(is_array($arrAdditionalCols)){
            for ($ii=$iStartAddCol; $ii<count($arrAdditionalCols); $ii++) {
                    if($arrAdditionalCols[$iStartAddCol]['columnAfter']!='')
                break;
            $lst->Columns[] = $arrAdditionalCols[$iStartAddCol];
            $iStartAddCol=$ii;
        }
    }

    foreach($this->conf['ATR'] as $atrID=>$rwAtr){
        
        if ($rwAtr["atrID"]==$prfx."ID") // ID field to skip
            continue;

        if ($rwAtr["atrFlagHideOnLists"]) // if column should be hidden, skip
            continue;

        if(!empty($this->staID) && isset($conf['STA'][$this->staID]['satFlagShowInList']) && !in_array($rwAtr['atrID'], (array)$conf['STA'][$this->staID]['satFlagShowInList'])) // id statusID field is set and atrribute is not set for show, skip
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
            list($arr['source_prefix'], $arr['extra']) = self::getPrefixExtra($rwAtr["atrProgrammerReserved"]);
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
    if(is_array($arrAdditionalCols)){
        for ($ii=$iStartAddCol; $ii<count($arrAdditionalCols); $ii++) {
            if ($arrAdditionalCols[$ii]['columnAfter']==$rwAtr['atrID']){
                $lst->Columns[] = $arrAdditionalCols[$ii];
                
                while(isset($arrAdditionalCols[$ii+1]) && $arrAdditionalCols[$ii+1]['columnAfter']==""){
                    $ii++;
                    $lst->Columns[] = $arrAdditionalCols[$ii];
                }
            }
        }
    }
  
    
    $commentField = $this->conf['entPrefix']."Comments";
    if ( !in_array($commentField , $arrExcludeCols)
        && in_array($commentField, array_keys($this->table['columns']))
        && !$lst->hasColumn($commentField) )        
        $lst->Columns[] = array('title' => $this->intra->translate("Comments")
            , 'type'=>"text"
            , 'field' => $commentField
            , 'filter' => $commentField
            , 'order_field' => $commentField
            , 'limitOutput' => 49
            );

    if (!in_array($prfx."EditDate", $arrExcludeCols))  
        $lst->Columns[] = array('title' => "Updated"
                , 'type'=>"date"
                , 'field' => $prfx."EditDate"
                , 'filter' => $prfx."EditDate"
                , 'order_field' => $prfx."EditDate"
                );
    
    return $lst;

}

/**
 * This is placeholder function for obtaining new item ID. It is used in the ```newItem()``` method to set the ID of the new item being created.
 * 
 * @return string|int  - new item ID to be used as the primary key of the new item record in the database.
 * 
 * @param array $data - data array that can be used to generate the new item ID.
 * 
 * @category Data Handling
 * 
 */
public function getNewItemID($data = array()){
    return null;
}

/**
 * This function creates a new item in the database based on the provided data array. It generates a new item ID, prepares the SQL fields for insertion, and executes the SQL query to insert the new item into the database. After the insertion, it appends action log entry for the "Create" action (actID=1).
 * 
 * @category Data Handling
 */
public function newItem($nd = array()){

    $newID = $this->getNewItemID($nd);

    $nd_sql = $this->intra->arrPHP2SQL($nd, $this->table['columns_types']);

    $sqlFields = $this->intra->getSQLFields($this->table, $nd_sql);

    $sql = "INSERT INTO {$this->conf['table']} SET 
        {$this->conf['prefix']}InsertBy=".$this->oSQL->e($this->intra->usrID)."
        , {$this->conf['prefix']}InsertDate=NOW()
        {$sqlFields}
        ".($newID ? ", {$this->table['PK'][0]} = ".$this->oSQL->e($newID) : '');

    $this->oSQL->q($sql);

    $this->id = ($newID ? $newID : $this->oSQL->i());

    $this->doAction(new eiseAction($this, array('actID'=>1)));

}

/**
 * This function inserts a new item into the database within a transaction. It starts a transaction, calls the `newItem()` method to create the item, updates the virtual roles associated with the item, and then commits the transaction. After that, it calls the parent `insert()` method to perform any additional actions defined in the parent class.
 * 
 * @param array $nd - data array for the new item to be inserted.
 * 
 * @category Data Handling
 */
public function insert($nd){

    $this->oSQL->q('START TRANSACTION');

    $this->newItem($nd);

    $this->updateRolesVirtual();

    $this->oSQL->q('COMMIT');

    parent::insert($nd);

}

/**
 * This function executes the provided action object, which is an instance of `eiseAction`. It sets the current action to the provided action object, executes it, and then unsets the current action. After executing the main action, it also executes any extra actions that have been added to the `extraActions` array.
 * 
 * @param eiseAction $oAct - The action object to be executed.
 * 
 * @uses eiseAction
 * 
 * @category Events and Actions
 */
public function doAction($oAct){
    $this->currentAction = $oAct;
    $oAct->execute();
    unset($this->currentAction);
    // do extra actions
    foreach ($this->extraActions as $act) {
        try{
            $act->checkPermissions();
        } catch (Exception $e){
            if($act->arrAction['flagSkipIfNoPermissions'])
                continue;
            else
                throw $e;   
        }
        $this->currentAction = $act;
        $act->execute();
        unset($this->currentAction);
    }
}

/**
 * This function updates the roles associated with the item based on the virtual roles defined in the entity configuration. It deletes existing role-item-user associations for the item and then inserts new associations for each virtual role member.
 * 
 * This function is called after item update in order to re-assign virtual roles to users related to the item.
 * 
 * @category Events and Actions
 * 
 */
public function updateRolesVirtual(){
    
    $oSQL = $this->oSQL;
    $intra = $this->intra;

    if(!count($this->conf['RolesVirtual']))
        return;

    $oSQL->q("DELETE FROM stbl_role_item_user WHERE riuEntityItemID='{$this->id}'");
    $sqlInsRows = '';
    foreach ($this->conf['RolesVirtual'] as $ix => $rwRole) {
        $roleMembers = $this->getVirtualRoleMembers($rwRole['rolID']);
        foreach($roleMembers as $usrID=>$data){
            $sqlInsRows .= ($sqlInsRows ? "\n, " : '')."('{$rwRole['rolID']}', '{$this->conf['entID']}', '{$this->id}', '{$usrID}', ".($data ? $oSQL->e($data) : 'NULL').", '{$intra->usrID}', NOW())";
        }
    }
    if($sqlInsRows){
       $sqlIns = "INSERT INTO stbl_role_item_user (
           riuRoleID
           , riuEntityID
           , riuEntityItemID
           , riuUserID
           , riuOriginRoleID
           , riuInsertBy, riuInsertDate
       ) VALUES {$sqlInsRows}";
       $oSQL->q($sqlIns); 
    }

}

/**
 * This function returns user list for virtual role members in a dictionary-like array of ```usrID=>null```.
 * 
 * This is also the placeholder for the virtual roles that are defined in the entity configuration for other entities derived from this class. So when you are about to override this function be sure to call the parent function first to get the default virtual role members.
 * 
 * Default roles are:
 * - __CREATOR - the user who created the item
 * - __EDITOR - the user who last edited the item
 * 
 * @param string $rolID - the role ID for which to get the members.
 * 
 * @return array
 * 
 * @category Events and Actions 
 */
public function getVirtualRoleMembers($rolID){
    
    switch ($rolID) {

        case '__CREATOR':
            return array(strtoupper($this->item[$this->conf['prefix'].'InsertBy'])=>null);
            break;

        case '__EDITOR':
            $statusActionLogID = $this->item[$this->conf['prefix'].'StatusActionLogID'];
            $lastEditor = null;
            if ($statusActionLogID && isset($this->item['ACL'][$statusActionLogID])) {
                $lastEditor = $this->item['ACL'][$statusActionLogID]['aclInsertBy'];
            }
            return ($lastEditor ? array(strtoupper($lastEditor)=>null) : array());
            break;

        default:
            return array();
    }

}

/**
 * This function updates unfinished actions for the item based on the provided data array. It iterates through the ACL (Action Control List) of the item and updates each action that is not yet completed (i.e., has an action phase less than 2).
 * 
 * @param array|null $nd - The data array to update the actions with. If null, it uses the `$_POST` data.
 * 
 * @category Events and Actions
 * @category Data Handling
 */
public function updateUnfinishedActions($nd = null){

    if($nd===null)
        $nd = $_POST;

    foreach((array)$this->item['ACL'] as $aclGUID=>$rwACL){
        if ($rwACL["aclActionPhase"]>=2)
            continue;

        $this->updateAction($rwACL, $nd);

    }

}

/**
 * This function updates a specific action data in the Action Log (ACL) of the item. It creates a new `eiseAction` object with the provided ACL data and the additional data from the `$nd` array, and then calls the `update()` method on that action object to perform the update.
 * 
 * @param array $rwACL - The ACL data for the action to be updated.
 * @param array $nd - Additional data to be used for updating the action.
 * 
 * @category Events and Actions
 * @category Data Handling
 */
public function updateAction($rwACL, $nd){

    if(isset($nd['aclGUID']) && $nd['aclGUID']==='')
        unset($nd['aclGUID']);
    $act = new eiseAction($this, array_merge($rwACL, $nd));
    $act->update($nd);

}

/**
 * This function retrieves the data for the item based on its ID. It first calls the parent `getData()` method to obtain the basic data, then it removes the 'Master' entry from the `defaultDataToObtain` array if it exists. Finally, it calls the `getAllData()` method to retrieve all additional data and returns the item data.
 * 
 * Retrieved data is stored in the `$this->item` property, which is an associative array containing all relevant information about the item. See the `getAllData()` method for details on what data is retrieved.
 * 
 * @param int|null $id - The ID of the item to retrieve data for. If null, it uses the current item's ID.
 * 
 * @return array - The item data with all retrieved information.
 * 
 * @category Data Handling
 */
public function getData($id = null){

    parent::getData($id);

    if(array_search('Master', $this->defaultDataToObtain)!==false)
        unset($this->defaultDataToObtain[array_search('Master', $this->defaultDataToObtain)]);

    $this->getAllData();

    return $this->item;
}

/**
 * This function retrieves all data related to the item based on the provided parameters. It retrieves the necessary data based on the specified parameters, and returns the complete item data including attributes, status, action log, checklists, status log, comments, files and messages.
 * 
 * It can retrieve specific data based on the `$toRetrieve` parameter, or if it is null, it retrieves all default data defined in the `defaultDataToObtain` property. The function also handles archived items by decoding the JSON data stored in the ```{$this->entPrefix}Data``` field.
 * 
 * Data obtained is to be stored in the following fields of the `$this->item` associative array:
 * - item fields (e.g., `{$this->entPrefix}ID`, `{$this->entPrefix}Number`, etc.) will be stored as is
 * - text representations of reference data will be stored in fields with `_text` suffix (e.g., `{$this->entPrefix}StatusID_text`, `atrID_text` etc)
 * - 'ACL' will contain action log as associative array with action GUIDs as keys and action data as values
 * - 'STL' will contain status log as associative array with status log GUIDs as keys and status log data as values
 * - 'comments' will contain comments as associative array with comment GUIDs as keys and comment data as values
 * - 'files' will contain files as associative array with file GUIDs as keys and file data as values
 * - 'messages' will contain messages as associative array with message GUIDs as keys and message data as values
 * 
 * @param array|string|null $toRetrieve - The data to retrieve. If null, it retrieves all default data.
 * 
 * @return array - The complete item data.
 * 
 * @category Data Handling
 */
function getAllData($toRetrieve = null){

    if(!$this->id)
        return array();

    $toRetrieve = ($toRetrieve!==null && !is_array($toRetrieve)) ? array($toRetrieve) : $toRetrieve;

    if($toRetrieve===null)
        $toRetrieve = $this->defaultDataToObtain;

    if ($this->flagArchive) {
        $arrData = json_decode($this->item["{$this->entID}Data"], true);
        $this->item = array_merge($this->item, $arrData);
        return $this->item;
    }

    //   - Master table is $this->item
    // attributes and combobox values
    $fld = $this->conf['entPrefix']."StatusID";
    if(isset($this->item[$fld]) && $this->item[$fld] !== null) 
        $this->staID = (int)$this->item[$fld];

    if(in_array('Master', $toRetrieve))
        $this->getData();

    if(in_array('Text', $toRetrieve)){  
        $sid = isset($this->item[$fld]) ? (int)$this->item[$fld] : 0;
        $tKey = "staTitle{$this->intra->local}";
        $this->item[$fld."_text"] = (isset($this->conf['STA'][$sid][$tKey]) ? $this->conf['STA'][$sid][$tKey] : '');
        foreach($this->conf["ATR"] as $atrID=>$rwATR){
            if (in_array($rwATR["atrType"], Array("combobox", "ajax_dropdown"))){
                $this->item[$rwATR["atrID"]."_text"] = !isset($this->item[$rwATR["atrID"]."_text"]) 
                    ? $this->getDropDownText($rwATR, $this->item[$rwATR["atrID"]])
                    : $this->item[$rwATR["atrID"]."_text"];
            }

        }
    }
    
    // collect incomplete/cancelled actions
    if(in_array('ACL', $toRetrieve) || in_array('STL', $toRetrieve)) {
        $this->item["ACL"]  = Array();
        $sqlACL = "SELECT * FROM stbl_action_log 
                INNER JOIN stbl_action ON actID= aclActionID AND (actEntityID='{$this->conf['entID']}' OR actID IN (1,2,3,4))
                WHERE aclEntityItemID='{$this->id}'
                ORDER BY aclActionPhase, IFNULL(aclATA, NOW()) DESC, aclNewStatusID DESC";
        $rsACL = $this->oSQL->do_query($sqlACL);
        while($rwACL = $this->oSQL->fetch_array($rsACL)){
            if($rwACL['aclActionPhase']<=2)
                $this->item["ACL"][$rwACL["aclGUID"]] = $this->getActionData($rwACL["aclGUID"], $rwACL);
            else 
                $this->item["ACL_Cancelled"][$rwACL["aclGUID"]] = $this->getActionData($rwACL["aclGUID"], $rwACL);
        } 

    }

    // collect checklist
    $this->collectChecklist();

    
    // collect status log and nested actions
    if(in_array('STL', $toRetrieve)){
        $this->item["STL"] = Array();
        $sqlSTL = "SELECT * 
            , CASE WHEN stlStatusID=0 THEN 1 ELSE 0 END as flagDraft
            FROM stbl_status_log 
            WHERE stlEntityID='{$this->entID}' 
            AND stlEntityItemID=".$this->oSQL->e($this->id)."
            ORDER BY flagDraft, IFNULL(DATE(stlATA), NOW()) DESC, stlInsertDate DESC";
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

/**
 * An alias for the `getAllData()` method. 
 * 
 * @category Data Handling
 * 
 */
public function refresh(){
    $this->getAllData();
}

/**
 * This function creates a backup of the current item data in JSON format. It retrieves all data using the `getAllData()` method, prepares the data for backup, and then either returns the JSON string or sends it as a downloadable file based on the `$q['asFile']` parameter.
 *
 * @category Backup and Restore 
 */
 public function backup($q){

    $this->getAllData();

    $to_backup = $this->item;
    $conf = $this->conf;
    unset($conf['Roles']);
    unset($conf['RolesVirtual']);
    unset($conf['RoleDefault']);
    unset($conf['ATR']);
    unset($conf['attr_types']);
    unset($conf['ACT']);
    unset($conf['STA']);

    $to_backup['conf'] = $conf;
    $json = json_encode($to_backup);

    if($q['asFile']){
        header('Pragma: public'); 
        header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");                  // Date in the past    
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); 
        header('Cache-Control: no-store, no-cache, must-revalidate');     // HTTP/1.1 
        header('Cache-Control: pre-check=0, post-check=0, max-age=0');    // HTTP/1.1 
        header("Pragma: no-cache"); 
        header("Expires: 0"); 
        header('Content-Transfer-Encoding: none'); 
        header("Content-Type: application/json");
        header("Content-Disposition: attachment; filename*=UTF-8''".rawurlencode($this->id.'-'.date('Ymd').'.json') );
        echo $json;
        die();
    }

    return $json;

}

/**
 * Function ```restore()``` restores an item from a backup file or a JSON string. It starts a transaction, reads the backup data, creates or updates the item in the database, and restores related data such as attributes, status, and action log. It also handles batch processing if specified. 
 * 
 * @param mixed $arg - The backup data to restore. It can be a JSON string or an uploaded file.
 * 
 * @category Backup and Restore
 */
public function restore($arg){

    $intra = $this->intra;
    $oSQL = $this->oSQL;
    
    $oSQL->q('START TRANSACTION');
    $oSQL->startProfiling();

    if( is_uploaded_file($_FILES['backup_file']['tmp_name']) ) {
        $json = json_decode(file_get_contents($_FILES['backup_file']['tmp_name']), true);
        if(!$json){
            throw new Exception("Bad file - no data found", 1);
        }
        if($_GET['fromBatch'])
            $intra->batchStart();
    } else {
        $json = $arg;
    }

    $ent = $oSQL->f('SELECT * FROM stbl_entity WHERE entID='.$oSQL->e($json['conf']['entID']));
    if(!$ent)
        throw new Exception("Entity ID not found for '{$json['conf']['entID']}'", 1);

    $class = get_class($this);
    
    try{
        $itm = new $class($json[$ent['entPrefix'].'ID'], $ent);
        if($_GET['fromBatch'])
            $intra->batchEcho("Item {$itm->id} has been found");

    } catch (Exception $e){
        try {
            $sqlIns = "INSERT INTO {$ent['entTable']} SET {$ent['entPrefix']}ID=".$oSQL->e($json[$ent['entPrefix'].'ID']);
            $oSQL->q($sqlIns);
            $itm = new $class($json[$ent['entPrefix'].'ID'], $ent);
            if($_GET['fromBatch'])
                $intra->batchEcho("Item {$itm->id} has been created");
        } catch (Exception $e) {
            $err = "Unable to create item of '{$ent['entTitle']}' with ID '{$json[$ent['entPrefix'].'ID']}'";
            if($_GET['fromBatch']){
                $intra->batchEcho("ERROR: {$err}");
                die();
            }
            throw new Exception($err, 1);
        }
    }
    
    $sqlFields = preg_replace('/^[\s\,]+/', '', $this->intra->getSQLFields($itm->table, $json));
    $oSQL->q("UPDATE {$ent['entTable']} SET {$sqlFields} WHERE {$itm->conf['PK']}='{$itm->id}'");

    foreach($this->to_restore as $aRestore){
        $tableInfo = $oSQL->getTableInfo($aRestore['table']);
        if(!$json[$aRestore['key']])
            continue;
        if($_GET['fromBatch'])
            $intra->batchEcho("Restoring {$aRestore['title']}...");
        foreach ($json[$aRestore['key']] as $key => $values) {
            $sqlFields = $aRestore['PK'].' = '.$oSQL->e($key).$this->intra->getSQLFields($tableInfo, $values);
            $sql = "INSERT INTO {$aRestore['table']} SET {$sqlFields} 
                ON DUPLICATE KEY UPDATE {$sqlFields}";
            $oSQL->q($sql);
        }
    }

    // $oSQL->showProfileInfo();
    // $oSQL->q('ROLLBACK');
    // die('<pre>'.var_export($itm->id, true));

    $oSQL->q('COMMIT');

    if($_GET['fromBatch']){
        $intra->batchEcho("All done!");
        die();
    }

    return $itm;

}

/**
 * This function is called before action is "planned", i.e. record is added to the Action Log. 
 * It is usable to modify action data before it is occured in the database.
 *
 * @category Events and Actions
 *
 * @param string $actID - action ID
 * @param int $oldStatusID - status ID to be moved from
 * @param int $newStatusID - destintation status ID 
 */
function beforeActionPlan($actID, $oldStatusID, $newStatusID){
    //parent::onActionPlan($actID, $oldStatusID, $newStatusID);
}
/**
 * This function is called after action is "planned", i.e. record is added to the Action Log. 
 * In case when something went wrong it should throw an exception.
 *
 * @category Events and Actions
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
 * @category Events and Actions
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
 * @category Events and Actions
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
 * @category Events and Actions
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
 * @category Events and Actions
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
 * @category Events and Actions
 *
 * @param string $staID - status ID
 */
public function onStatusArrival($staID){}

/**
 * This function is called when item departs from given status.
 * In case when something went wrong it should throw an exception.
 *
 * @category Events and Actions
 *
 * @param string $staID - status ID
 */
public function onStatusDeparture($staID){}

/**
 * This function processes checkmarks for the item based on the provided action data. It checks if the item has any checkmarks defined, and if so, it counts how many checkmarks are required and how many are completed. If a checkmark is not completed but is set to be checked by the action, it updates the checkmark in the database.
 * 
 * @param array $arrAction - The action data containing the new status ID and action ID.
 * 
 * @category Events and Actions
 */
public function processCheckmarks($arrAction){

    if(!isset($this->item['CHK']) || !$this->item['CHK'])
        return true;

    $required = [];
    $checkmarks_required = 0;
    $checkmarks_completed = 0;
    foreach ($this->item['CHK'] as $rwCHK) {
        if($rwCHK['chkTargetStatusID']==$arrAction["aclNewStatusID"]){
            $checkmarks_required += 1;
            if($rwCHK['checked']){
                $checkmarks_completed += 1;
            } else {
                if($rwCHK['chkSetActionID']==$arrAction['actID']){

                    $value = ($this->table['columns'][$rwCHK['chkAttributeID']]['DataType']=='boolean'
                        ? 1
                        : $arrAction['aclGUID']);

                    $sqlCHK = "UPDATE {$this->conf['table']} SET {$rwCHK['chkAttributeID']}=".$this->oSQL->e($value)."
                        WHERE {$this->conf['PK']}='{$this->id}'";
                    $this->oSQL->q($sqlCHK);
                    $checkmarks_completed += 1;

                }
            }

        }
        
    }

    // die('<pre>'.var_export($this->table, true));
    return $checkmarks_required && ($checkmarks_required == $checkmarks_completed);

}

/**
 * This function generates the HTML form for the item, including hidden fields for the entity ID, old and new status IDs, action ID, ACL GUID, ToDo, and comments. It also includes the status field and the fields defined in the entity configuration.
 * 
 * The form can be customized with additional configuration options such as whether to add JavaScript, show messages, or include files.
 * 
 * @param string $fields - The fields to include in the form. If empty, it will use the default fields.
 * @param array $arrConfig - Additional configuration options for the form.
 * 
 * @return string - The generated HTML form.
 * 
 * @category Data Display
 */
public function form($fields = '',  $arrConfig=Array()){

    $defaultConf = array('flagAddJavaScript'=>True,
        'flagMessages'=>false,
        'flagFiles'=>false,
    );

    $arrConfig = array_merge($defaultConf, $arrConfig);

    $hiddens = '';
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
        $fields = $this->getStatusField()
            .$this->getFields();
        $this->intra->arrUsrData['FlagWrite'] = $oldFW;
    }

    $flagAddJavaScript = $arrConfig['flagAddJavaScript'];

    $arrConfig['flagAddJavaScript'] = False;

    $form = parent::form($hiddens.$fields, $arrConfig)
        .($flagAddJavaScript
            ? "<script>$(document).ready(function(){ $('#{$this->table['prefix']}').eiseIntraForm().eiseIntraEntityItemForm();})</script>"
            : '');

    if($arrConfig['flagMessages'])
        $form .= $this->formMessages();

    if($arrConfig['flagFiles'])
        $form .= $this->formFiles();

    $this->intra->arrUsrData['FlagWrite'] = $oldFW;

    return $form;

}

/**
 * This function generates the HTML form to be used for multiple item editing, including fields for setting data and action buttons. It uses the `getFields()` method to retrieve the fields and displays them in a fieldset. If there are radio buttons defined in the configuration, it also includes a fieldset for actions with a submit button.
 * 
 * @return string - The generated HTML form for listing multiple items.
 * 
 * @category Data Display
 */
public function form4list(){

    $this->conf['flagShowOnlyEditable'] = true;
    $htmlFields = $this->getFields();

    $htmlFields = $this->intra->fieldset($this->intra->translate('Set Data'), $htmlFields.(empty($this->conf['radios'])
            ? $this->intra->field(' ', null, $this->showActionButtons()) 
            : '')
        , array('class'=>'eiseIntraMainForm')).
        ($this->conf['radios'] ? $this->intra->fieldset($this->intra->translate('Action'), $this->showActionRadios()
            .'<div class="eif-actionButtons for-radios">'.$this->intra->showButton('btnSubmit', $this->intra->translate('Run')
                , array('type'=>'submit')).'</div>'
            , array('class'=>'eiseIntraActions')) : '');

    return eiseItemTraceable::form($htmlFields, array('class'=>'ei-form-multiple eiseIntraMultiple'));

}

/**
 * This function generates the HTML for the status field, which displays the current status of the item. Normally it is being shown in upper left corner of the form It can be configured to be clickable (with AJAX load of item action log) or non-clickable based on the provided configuration options.
 * 
 * @param array $conf - Configuration options for the status field. It can include 'clickable' to determine if the status should be clickable.
 * 
 * @return string - The generated HTML for the status field.
 * 
 * @category Data Display
 */
public function getStatusField($conf=[]){

    $defaultConf = ['clickable'=>True];
    $conf = array_merge($defaultConf, $conf);

    return (isset($this->conf['flagNoStatusField'] ) && $this->conf['flagNoStatusField'] 
        ? ''
        : '<div class="statusTitle">'.$this->intra->translate('Status').': <span class="eif_curStatusTitle'
                .($conf['clickable'] ? ' clickable' : ' non-clickable')
                .'"><u>'.$this->conf['STA'][$this->staID]["staTitle{$this->intra->local}"].'</u></span></div>'
        );

}

/**
 * This function generates the HTML skeleton for the Action Log, which is a table that displays the action log entries for the item. It includes a template row for displaying action titles, finish by information, and action times. It also includes a row for displaying traced data and comments, as well as a spinner and a message for when no events are found.
 * 
 * @param array $conf - Configuration options for the Action Log skeleton. It can include 'class' and 'id' to customize the HTML attributes.
 * 
 * @return string - The generated HTML skeleton for the Action Log.
 * 
 * @category Data Display
 */
public function getActionLogSkeleton($conf=[]){

    $defaultConf = ['class'=>'',
            'id'=>'eiseIntraActionLog'];

    $conf = array_merge($defaultConf, $conf);

    return '<div id="'.$conf['id'].'" class="eif-action-log'.($conf['class'] ? " {$conf['class']}" : '').'" title="'.__('Action Log').'">'."\n"
            ."<table class='eiseIntraActionLogTable'>\r\n"."<tbody class=\"eif_ActionLog\">"
            ."<tr class=\"eif_template eif_evenodd\">\r\n"
            ."<td class=\"eif_actTitlePast\"></td>\r\n"
            ."<td class=\"eif_aclFinishBy\"></td>"
            ."<td class=\"eif_aclATA\" style=\"text-align:right;\"></td>"
            ."</tr>"
            ."<tr class=\"eif_template eif_evenodd eif_tr_traced eif_invisible\">"
            ."<td class=\"eif_commentsTitle\">".__("Traced data").":<div class=\"eif_aclNFieldsToTrack\" style='display:none'/></td>\r\n"
            ."<td colspan='2' class=\"eif_aclTracedHTML\" data-hidden_title=\"".__("click")."\"></td>"
            ."</tr>"
            ."<tr class=\"eif_template eif_evenodd eif_invisible\">"
            ."<td class=\"eif_commentsTitle\">".__("Comments").":</td>\r\n"
            ."<td colspan='2' class=\"eif_aclComments\"></td>"
            ."</tr>"
            ."<tr class=\"eif_notfound\" style=\"display: none;\">"
            ."<td colspan='3'>".__("No Events Found")."</td>"
            ."</tr>"
            ."<tr class=\"eif_spinner\">"
            ."<td colspan='3'></td>"
            ."</tr>"
            ."</tbody></table>\r\n"
            ."</div>\r\n";

}

/**
 * This function generates the HTML skeleton for the Checklist, which is a table that displays checklist items for the item. It includes a template row for displaying checkmarks, titles, and descriptions. It also includes a row for displaying a message when no events are found and a spinner for loading.
 * 
 * @param array $conf - Configuration options for the Checklist skeleton. It can include 'class' and 'id' to customize the HTML attributes.
 * 
 * @return string - The generated HTML skeleton for the Checklist.
 * 
 * @category Data Display
 */
public function getChecklistSkeleton($conf=[]){

    $defaultConf = ['class'=>'',
            'id'=>'eiseIntraChecklist'];

    $conf = array_merge($defaultConf, $conf);

    return '<div id="'.$conf['id'].'" class="eif-checklist'.($conf['class'] ? " {$conf['class']}" : '').'" title="'.__('Checklist').'">'."\n"
            ."<table>\r\n"."<tbody>"
            ."<tr class=\"eif_template eif_evenodd\">\r\n"
            ."<td class=\"eif-checkmark\" rowspan=\"2\"><i> </i></td>\r\n"
            ."<td class=\"eif-title\"></td>"
            ."<td class=\"eif-aclATA\" style=\"text-align:right;\" rowspan=\"2\"></td>"
            ."</tr>"
            ."<tr class=\"eif_template eif_evenodd description\">\r\n"
            ."<td class=\"eif-description\"></td>"
            ."</tr>"
            ."<tr class=\"eif_notfound\" style=\"display: none;\">"
            ."<td colspan='3'>".__("No Events Found")."</td>"
            ."</tr>"
            ."<tr class=\"eif_spinner\">"
            ."<td colspan='3'></td>"
            ."</tr>"
            ."</tbody></table>\r\n"
            ."</div>\r\n";

}

/**
 * This function retrieves the action log for the item based on the provided query parameters. It checks if the ACL data is already loaded, and if not, it retrieves all ACL data. It then processes each action log entry, filtering out certain actions based on the query parameters, and formats the data into an array of action log entries.
 * 
 * The function returns an array of action log entries, each containing information such as action ID, old and new status IDs, action titles, comments, and timestamps. If the action log entry has traced data, it also includes the traced HTML representation of the data. Array keys are just sequential numbers, starting from 0, and the array is ordered by the action log entry date in descending order.
 * 
 * It can return action log in reverse order if the query parameter `order` is set to `reverse`. It also filters out actions that are not relevant based on the `flagFull` query parameter, which determines whether to include all actions or skip 'Edit/Update' actions (actID=2).
 * 
 * Array to be returned is normally used as JSON data for asynchronous loading of the Action Log in the UI.
 * 
 * @param array $q - Query parameters to filter the action log.
 * 
 * @return array - The formatted action log entries.
 * 
 * @category Data Display
 */
public function getActionLog($q){

    if(!$this->item['ACL'])
        $this->getAllData('ACL');

    $aRet = array();$aActionIDs = array();

    $arrACL = (array)$this->item['ACL'];

    if($q['order']='reverse'){
        $arrACL = array_reverse($arrACL);
    }
    foreach ($arrACL as $aclGUID => $acl) {


        if($acl['aclActionID']==2 && !isset($q['flagFull']))
            continue;

        if($acl['aclActionPhase']>2)
            continue;

        $act = $this->conf['ACT'][$acl['aclActionID']];
        $sta_old = $acl['aclOldStatusID'] ? $this->conf['STA'][$acl['aclOldStatusID']] : null;
        $sta_new = $acl['aclNewStatusID'] ? $this->conf['STA'][$acl['aclNewStatusID']] : null;

        $rw = array('aclGUID' => $acl['aclGUID']
            , 'actID' => $acl['aclActionID']
            , 'aclActionPhase' => $acl['aclActionPhase']
            , 'aclOldStatusID' => $acl['aclOldStatusID']
            , 'aclOldStatusID_text' => $sta_old ? $sta_old['staTitle'.$this->intra->local] : ''
            , 'aclNewStatusID' => $acl['aclNewStatusID']
            , 'aclNewStatusID_text' => $sta_new ? $sta_new['staTitle'.$this->intra->local] : ''
            , 'actTitle' => (isset($act['actTitle'.$this->intra->local]) ? $act['actTitle'.$this->intra->local] : '')
            , 'actTitlePast' => (isset($act['actTitlePast'.$this->intra->local]) ? $act['actTitlePast'.$this->intra->local] : '')
            , 'aclComments' => $acl['aclComments']
            , 'aclFinishBy' => $this->intra->translate('by %s', $this->intra->getUserData($acl['aclFinishBy']))
            , 'aclEditBy' => $this->intra->translate('by %s', $this->intra->getUserData($acl['aclEditBy']))
            , 'aclEditDate' => $this->intra->datetimeSQL2PHP($acl["aclEditDate"])
            , 'aclATA' => date("{$this->intra->conf['dateFormat']}"
                    .(strtotime($acl["aclATA"])!=strtotime(date('Y-m-d', strtotime($acl["aclATA"]))) ? " {$this->intra->conf['timeFormat']}" : '')
                , strtotime($acl["aclATA"]))
            );
        $aclFieldsToTrack = (!empty($this->conf['ACT'][$acl['actID']]['aatFlagToTrack']) 
            ? array_keys((array)$this->conf['ACT'][$acl['actID']]['aatFlagToTrack'])
            : array()
        );
        if($acl['aclItemTraced'] && count($aclFieldsToTrack)){
            $tracedHTML = $this->getAttributeFields($aclFieldsToTrack, $this->getTracedData($acl)
                , array('suffix'=>'_'.$acl['aclGUID'], 'FlagWrite'=>false, 'flagNonEmpty'=>true, 'flagNoInputName'=>true)
                );   
            $rw['aclTracedHTML'] = '<div class="eif-traced-data">'.$tracedHTML.'</div>';
            // $rw['aclTracedHTML'] = '<div class="eif-traced-data">'.htmlspecialchars(var_export($acl['aclItemTraced'], true)).'</div>';
            $rw['aclNFieldsToTrack'] = count($aclFieldsToTrack);
        }
        $aActionIDs[] = $acl['actID'];
        $aRet[] = $rw;

    }

    if(!in_array(1, $aActionIDs)){
        $aCreate = array('aclGUID' => null
                    , 'actID' => 1
                    , 'aclActionPhase' => 2
                    , 'aclOldStatusID' => null
                    , 'aclOldStatusID_text' => ''
                    , 'aclNewStatusID' => $acl['aclNewStatusID']
                    , 'aclNewStatusID_text' => (isset($this->conf['STA'][0]) ? $this->conf['STA'][0]['staTitle'.$this->intra->local] : '')
                    , 'actTitle' => $this->intra->translate('Create')
                    , 'actTitlePast' => $this->intra->translate('Created')
                    , 'aclComments' => $acl['aclComments']
                    , 'aclFinishBy' => $this->intra->translate('%s by %s', ucfirst($acl['actTitlePast'.$this->intra->local]), $this->intra->getUserData($this->item[$this->conf['prefix'].'InsertBy']))
                    , 'aclEditBy' => $this->intra->translate('%s by %s', ucfirst($acl['actTitlePast'.$this->intra->local]), $this->intra->getUserData($this->item[$this->conf['prefix'].'InsertBy']))
                    , 'aclEditDate' => $this->intra->datetimeSQL2PHP($this->item[$this->conf['prefix'].'InsertDate'])
                    , 'aclATA' => $this->intra->datetimeSQL2PHP($this->item[$this->conf['prefix'].'InsertDate'])
                    );

        $aRet = ($q['order']=='reverse' ? array_merge([$aCreate], $aRet) : array_merge($aRet, [$aCreate]));

    }

    return $aRet;
}

/** 
 * This function collects the checklist items for the item based on the defined checkmarks in the configuration. It evaluates each checkmark against the item's attributes and determines if it matches the conditions defined in the checkmark matrix. It returns an array of matching checkmarks and unnecessary checkmarks that do not apply.
 * 
 * @return array|null - An array of matching checkmarks or null if no checkmarks are defined.
 * 
 * @category Data Display
 * @category Checklists
 * 
 * @ignore
 */
public function collectChecklist(){

    if(!$this->conf['CHK'])
        return null;

    $this->item['CHK'] = [];
    $this->item['CHK_ACT_unnecesary'] = [];


    $matching = [];
    $unnecesary = [];
    
    foreach ($this->conf['CHK'] as $rwCHK) {
        $matchScore = 0;
        foreach((array)json_decode($rwCHK['chkMatrix'], true) as $mtx) {
            $conditionWorked = array();
            $tierConditionWorked = array();
            foreach ($this->conf['AtrMTX'] as $atrID => $condition) {
                $mtxKey = preg_replace('/^'.preg_quote($this->conf['entPrefix'], '/').'/', 'mtx', $atrID);
                $valueMTX = isset($mtx[$mtxKey]) ? $mtx[$mtxKey] : null;
                $valueItem = $this->item[$atrID];
                $condition = ($condition=='=' ? '==' : $condition);
                $toEval = "\$conditionWorked[\$atrID] = (int)(\$valueItem {$condition} \$valueMTX);";
                $tierConditionWorked[$atrID] = 0;
                if($valueMTX===null || $valueMTX==='' || $valueMTX==='%'){
                    $conditionWorked[$atrID] = 1;
                    continue;
                }
                eval($toEval);
                if($conditionWorked[$atrID])
                    $tierConditionWorked[$atrID] = 1;
            }
            // if no match, skip this position
            if(min($conditionWorked)==0)
                continue;

            // if match with 'Disable', drop all rules, this checkpoint is not applicable
            if($mtx['mtxRule']==2 && array_sum($tierConditionWorked)>0){
                $matchScore = 0;
                break;
            }

            $matchScore += 1;
            // echo '<pre>'.var_export($conditionWorked, true);    
            // echo '<pre>'.var_export($tierConditionWorked, true);    
        }

        if($matchScore){

            $rwCHK['checked'] = (bool)(isset($this->item[$rwCHK['chkAttributeID']]) ? $this->item[$rwCHK['chkAttributeID']] : false);
            $matching[] = $rwCHK;

        } else {

            $unnecesary[] = $rwCHK['chkSetActionID'];

        }
        
        
    }

    $this->item['CHK'] = $matching;
    $this->item['CHK_ACT_unnecesary'] = $unnecesary;

    return $matching;

}

/**
 * This function retrieves the checklist items for the item, formatting them into an array of checklists with titles, descriptions, and status. It checks if each checklist item is checked or not and provides a description if it requires action. The function returns an array of formatted checklist items.
 * 
 * @return array - An array of checklist items with titles, descriptions, and status.
 * 
 * @category Checklists
 * @category Data Display
 * 
 * @ignore
 */
public function getChecklist(){

    $aRet = [];

    // die('<pre>'.var_export($this->conf['CHK'], true));

    foreach ($this->item['CHK'] as $rwCHK) {
        if(!$rwCHK['checked']){
            $descr = __('Action required: %s', (isset($this->conf['ACT'][$rwCHK['chkSetActionID']]["actTitle{$this->intra->local}"]) ? $this->conf['ACT'][$rwCHK['chkSetActionID']]["actTitle{$this->intra->local}"] : ''));
        } else {
            $descr = '';
        }
        $chk = array(
                'rowClass' => array('v'=>($rwCHK['checked'] ? 'checked' : '')),
                'title' => (isset($rwCHK["chkTitle{$this->intra->local}"]) ? $rwCHK["chkTitle{$this->intra->local}"] : ''),
                'description' => $descr,
                'aclATA' => '',

        );
        $aRet[] = $chk;
    }

    return $aRet;

}

/**
 * This function retrieves the fields for the item based on the provided configuration and status ID. It checks if the fields should be shown based on the configuration and status, and returns an array of fields that can be used in a form or display.
 * 
 * @param array|null $aFields - An optional array of fields to retrieve. If null, it will use the default fields based on the configuration.
 * 
 * @return array - An array of fields to be displayed or used in a form.
 * 
 * @category Data Display
 */
public function getFields($aFields = null){

    $aToGet = ($aFields!==null 
        ? $aFields 
        : ($this->conf['flagFormShowAllFields'] 
            ? array_keys($this->conf['ATR']) 
            : ($this->staID!==null
                ? (isset($this->conf['flagShowOnlyEditable']) && $this->conf['flagShowOnlyEditable'] 
                    ? (isset($this->conf['STA'][$this->staID]['satFlagEditable']) ? $this->conf['STA'][$this->staID]['satFlagEditable'] : array())
                    : (isset($this->conf['STA'][$this->staID]['satFlagShowInForm']) ? $this->conf['STA'][$this->staID]['satFlagShowInForm'] : array())
                    )
                : array())
            )
        );

    $allowEdit = (($this->staID!==null 
            ? (isset($this->conf['STA'][$this->staID]['staFlagCanUpdate']) ? $this->conf['STA'][$this->staID]['staFlagCanUpdate'] : true)
            : true ) 
        && $this->intra->arrUsrData['FlagWrite']);

    return $this->getAttributeFields($aToGet, $this->item, array('FlagWrite'=>$allowEdit));

}

/**
 * This function generates the HTML for the attribute fields of the item based on the provided fields and configuration. It checks if the item has the specified fields and generates the corresponding HTML input elements for each field, including options for comboboxes, selects, and AJAX dropdowns. It also handles attributes such as href, suffix, and write permissions.
 * 
 * @param array $fields - An array of fields to generate HTML for.
 * @param array|null $item - The item data to use for generating the fields. If null, it will use the current item.
 * @param array $conf - Configuration options for generating the fields, such as forceFlagWrite and suffix.
 * 
 * @return string - The generated HTML for the attribute fields.
 * 
 * @category Data Display
 */
function getAttributeFields($fields, $item = null, $conf = array()){

    $html = '';

    if($item===null)
        $item = $this->item;

    if(is_array($fields))
    foreach($fields as $field){
        $atr = (isset($this->conf['ATR'][$field]) ? $this->conf['ATR'][$field] : array());

        if(isset($conf['flagNonEmpty']) && $conf['flagNonEmpty'] && !$item[$field])
            continue;

        $options = array('type'=>$atr['atrType']
            , 'FlagWrite'=>(isset($conf['forceFlagWrite']) && $conf['forceFlagWrite'] 
                ? $conf['FlagWrite']
                : (isset($this->conf['STA'][$this->staID]['satFlagEditable']) && in_array($field, (array)$this->conf['STA'][$this->staID]['satFlagEditable']) && $conf['FlagWrite']) )
            );

        if(in_array($atr['atrType'], array('combobox', 'select', 'ajax_dropdown')) ) { 
                
                if (preg_match("/^(svw_|vw_|tbl_|\[|\{)/", $atr["atrDataSource"]) ) {
                    $options['source'] = $atr["atrDataSource"];
                } else if (preg_match("/^Array/i", $atr["atrProgrammerReserved"])){
                    eval ("\$options['source']={$atr["atrProgrammerReserved"]};");
                }
                list($options['source_prefix'], $options['extra']) = self::getPrefixExtra($atr["atrProgrammerReserved"]);
                    
                $options['defaultText'] = (isset($this->conf['ATR'][$field]['atrTextIfNull']) && $this->conf['ATR'][$field]['atrTextIfNull'] 
                    ? $this->conf['ATR'][$field]['atrTextIfNull']
                    : '-');
                
        }
        if($atr['atrHref'])
            $options['href'] = $atr['atrHref'];
        if(isset($conf['suffix']) && $conf['suffix'])
            $options['field_suffix'] = $conf['suffix'];
        if(isset($conf['flagNoInputName']) && $conf['flagNoInputName'] && !$options['FlagWrite']){
            $options['no_input_name'] = true;
        }

        $html .= $this->intra->field((isset($atr["atrTitle{$this->intra->local}"]) ? $atr["atrTitle{$this->intra->local}"] : ''), $field, $item, $options);
    }

    return $html;

}


/**
 * This function generates an array of action buttons based on the current status ID and user permissions. It retrieves the actions defined for the current status, checks if the user has permission to perform each action, and formats the actions into an array of buttons with titles, actions, IDs, datasets, and classes.
 * 
 * @return array - An array of action buttons with titles, actions, IDs, datasets, and classes.
 * 
 * @category Data Display
 * @category Events and Actions
 */
public function arrActionButtons(){

    $oSQL = $this->oSQL;
    $strLocal = $this->intra->local;

    $arrActions = array();
    
    if (!$this->intra->arrUsrData["FlagWrite"])
        return;

    if($this->staID!==null){

        $arrActions_ = (isset($this->conf['STA'][$this->staID]['ACT']) ? (array)$this->conf['STA'][$this->staID]['ACT'] : array());

        usort($arrActions_, function ($act1, $act2) {
            $priority1 = isset($act1['actPriority']) ? $act1['actPriority'] : 0;
            $priority2 = isset($act2['actPriority']) ? $act2['actPriority'] : 0;
            if ($priority1 == $priority2) {
                return 0;
            }
            return ($priority1 > $priority2) ? -1 : 1;
        });

        foreach($arrActions_ as $rwAct){

            if((isset($rwAct['actFlagSystem']) && $rwAct['actFlagSystem']) 
                || ($this->conf['CHK'] && in_array($rwAct['actID'], (array)$this->item['CHK_ACT_unnecesary'])))
                continue;

            if ($this->id) {
                try {
                    $act = new eiseAction($this, $rwAct, array('flagDoNotRefresh'=>true));
                    $act->checkPermissions();
                } catch (Exception $e) {
                    continue;
                }
            }

            $str_actNewStatusID = (isset($rwAct["actNewStatusID"][0]) ? $rwAct["actNewStatusID"][0] : '');

            $title = ($rwAct["actTitle{$this->intra->local}"] ? $rwAct["actTitle{$this->intra->local}"] : $rwAct["actTitle"]) ;
              
            $strID = "btn_".$rwAct["actID"]."_".
                  $this->staID."_".
                  $str_actNewStatusID;

            $escalated = false;
            if(isset($rwAct['RLA_tiers']) && $rwAct['RLA_tiers']){
                $aUserTiers = array();
                $suitableRoles = array_values(array_intersect($rwAct['RLA'], $this->intra->arrUsrData['roleIDs']));
                foreach ($suitableRoles as $rol) { 
                    if (isset($rwAct['RLA_tiers'][$rol])) {
                        $aUserTiers[$rol] = $rwAct['RLA_tiers'][$rol]; 
                    }
                }    
                if (!empty($rwAct['RLA_tiers']) && !empty($aUserTiers)) {
                    $escalated = (int)(min($rwAct['RLA_tiers']) < min($aUserTiers));
                }
            }

            $arrActions[] = Array ("title" => $title #.(int)$escalated.'<pre>'.var_export($rwAct['RLA_tiers'], true)."\n".var_export($this->intra->arrUsrData['roleIDs'], true)."\n".var_export($aUserTiers, true).'</pre>'
                   , "action" => "#ei_action"
                   , 'id' => $strID
                   , "dataset" => array("action"=>array('actID'=>$rwAct["actID"]
                        , 'aclOldStatusID' => $this->staID
                        , 'aclNewStatusID' => $str_actNewStatusID
                        , 'escalated' => (int)$escalated
                        )
                   )
                   , "class" => "{$rwAct["actButtonClass"]} "
                );
                  
        }
    } else {
        $arrActions[] = Array ("title" => __("Create")
               , "action" => "#ei_action"
               , 'id' => "btn_1__0"
               , "dataset" => array("action"=>array('actID'=>1
                    , 'aclOldStatusID' => null
                    , 'aclNewStatusID' => 0)
               )
               , "class" => "ss_add "
            );
    }


   
   return $arrActions;
}

/**
 * This function generates the HTML for the action buttons based on the actions defined in the configuration. It iterates through the array of action buttons, creating a button for each action with its title, ID, and dataset. The buttons are wrapped in a div with a class for styling and are returned as a string.
 * 
 * @return string - The generated HTML for the action buttons.
 * 
 * @category Data Display
 * @category Events and Actions
 */
public function showActionButtons(){

    $ret = '';
    $actions = $this->arrActionButtons();

    $ret .= '<div class="eif-actionButtons">'."\n";
    foreach ((array)$actions as $key => $act) {
        $ret .= $this->intra->showButton($act['id'], $act['title'], array('class'=>($act['dataset']['action']['actID']==3 ? 'eif-btn-delete eiseIntraDelete' : 'eif-btn-submit eiseIntraActionSubmit'), 'dataset'=>$act['dataset']));
    }
    $ret .= '</div>';

    return $ret;
   
}

/**
 * Alias for the ```showActionButtons()``` method
 * 
 * @category Data Display
 */
public function getActionButtons(){  return $this->showActionButtons(); }

/** 
 * This function generates the HTML for the radio buttons based on the actions defined in the configuration. It iterates through the actions for the current status, checking user permissions and generating radio buttons for each action. The radio buttons include attributes for the old and new status IDs, and they are formatted with labels that include titles and status changes.
 * 
 * @return string - The generated HTML for the action radio buttons.
 * 
 * @category Data Display
 */
function showActionRadios(){

    $strOut = '';
    
    if (!$this->intra->arrUsrData["FlagWrite"])
        return;

    $aclOldStatusID = $this->staID;

    if(isset($this->conf['STA'][$this->staID]['ACT']) && is_array($this->conf['STA'][$this->staID]['ACT']))
        foreach($this->conf['STA'][$this->staID]['ACT'] as $rwAct){
            
            if($rwAct['actFlagDeleted'])
                continue;

            $aUserRoles = array_merge(array($this->conf['RoleDefault']), $this->intra->arrUsrData['roleIDs']);
            if(count(array_intersect($aUserRoles, $rwAct['RLA']))==0)
                continue;

            $aclNewStatusID = (isset($rwAct["actNewStatusID"][0]) ? $rwAct["actNewStatusID"][0] : null);

            $arrRepeat = Array(($rwAct["actFlagAutocomplete"] ? "1" : "0") => (!$rwAct["actFlagAutocomplete"] ? $this->intra->translate("Plan") : ""));
            
            foreach($arrRepeat as $key => $value){
                $title = (in_array($rwAct["actID"], array(2, 3))
                   ? " - ".$rwAct["actTitle{$this->intra->local}"]." - "
                   : $rwAct["actTitle{$this->intra->local}"].
                      ($rwAct["actOldStatusID"]!=$rwAct["actNewStatusID"]
                      ?  " (".$this->conf['STA'][$rwAct["actOldStatusID"][0]]["staTitle{$this->intra->local}"]
                        ." > ".$this->conf['STA'][(isset($rwAct["actNewStatusID"][0]) ? $rwAct["actNewStatusID"][0] : null)]["staTitle{$this->intra->local}"].")"
                      :  "")
                );
              
                $strID = "rad_".$rwAct["actID"]."_"
                  .$aclOldStatusID."_"
                  .$aclNewStatusID;

                $strOut .= "<input type='radio' name='actRadio' id='$strID' value='".$rwAct["actID"]."' class='eiseIntraRadio'".
                    " orig=\"{$aclOldStatusID}\" dest=\"{$aclNewStatusID}\"".
                    ($rwAct["actID"] == 2 || ($key=="1" && count($arrRepeat)>1) ? " checked": "")
                     .(!$rwAct["actFlagAutocomplete"] ? " autocomplete=\"false\"" : "")." /><label for='$strID' class='eiseIntraRadio'>".($value!="" ? "$value \"" : "")
                     .$title
                     .($value!="" ? "\"" : "")."</label><br />\r\n";
              
            }
        }

   return $strOut;

}

/**
 * This function generates the HTML for the status log of the item, displaying the status entries with their titles, timestamps, and associated actions. It iterates through the status log entries, formatting each entry with its title, timestamps, and any associated actions. The function also handles the visibility of draft statuses based on the provided configuration.
 * 
 * @param array $conf - Configuration options for displaying the status log, such as hiding draft statuses and enabling full edit mode.
 * 
 * @return string - The generated HTML for the status log.
 * 
 * @category Data Display
 */
public function showStatusLog($conf = array()){

    $html = '<div class="eif-stl">'."\n";

    foreach((array)$this->item["STL"] as $stlGUID => $rwSTL){

        if (isset($conf['flagHideDraftStatusStay']) && $conf['flagHideDraftStatusStay'] && isset($rwSTL['stlStatusID']) && $rwSTL['stlStatusID']==='0')
            continue;

        if (!isset($rwSTL['staID']) ) {
           $rwSTL['staID'] = $rwSTL['stlStatusID'];
        }
        $rwSTA = $this->conf['STA'][$rwSTL['staID']];

        $htmlRemove = (isset($conf['flagFullEdit']) && $conf['flagFullEdit'] ? '('.$rwSTL['staID'].') &nbsp;<a href="#remove_stl" class="remove">[x]</a>' : '');
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
                        .'</span>'.$htmlRemove.'</div>'."\n";

        $html .= $this->intra->field(($rwSTL["stlTitle{$this->intra->local}"]!="" 
                ? $rwSTL["stlTitle{$this->intra->local}"]
                : $rwSTL["staTitle"]), null, null
            , array('fieldClass'=>'eif-stl-title'
                , 'title' => $rwSTL['stlGUID']
                , 'dataset' => array('guid'=>$stlGUID)
                , 'extraHTML'=> $htmlTiming));

    
        $html .= '<div class="eif-acl">'."\n";
        $stlATD = ($rwSTL['stlATD'] 
            ? ($rwSTA["staTrackPrecision"] == 'datetime'
                ? strtotime($rwSTL['stlATD'])
                : floor(strtotime($rwSTL['stlATD'].'+1 day') / (60 * 60 * 24)) * (60 * 60 * 24)
                )
            : time()
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
            // $html .= $rwACL['aclActionID'].': '.$stlATA.' <= '.$aclATA.' && '.$aclATA.' <= '.$stlATD.'<br>';
            // $html .= '<pre>'.date('d.m.Y H:i:s', $stlATA).' <= '.date('d.m.Y H:i:s', $aclATA)
            //         .' && '.date('d.m.Y H:i:s', $aclATA).' <= '.date('d.m.Y H:i:s', $stlATD).' '.$rwSTL['stlATD'].'</pre>';
            $html .= $this->showActionInfo($aclGUID, $conf);
        }
        $html .= '</div>'."\n";
        
    }

    $html .= '</div>'."\n";

    return $html;
}

/**
 * This function generates the HTML for unfinished actions based on the ACL data of the item. It iterates through the ACL entries, checking if the action phase is less than 2 (indicating it is unfinished). For each unfinished action, it displays the action information and provides buttons to start, finish, or cancel the action if the user has write permissions.
 * 
 * @return string - The generated HTML for unfinished actions.
 * 
 * @category Data Display
 */
function showUnfinishedActions(){

    $html = '';

    foreach((array)$this->item['ACL'] as $aclGUID=>$rwACL){
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

            if ($rwACL["aclActionPhase"]=="0" && $act['actFlagHasDeparture']){
                $html .= $this->intra->showButton("start_{$aclGUID}", $this->intra->translate("Start"), array('class'=>"eiseIntraActionButton"));
            }
            if ($rwACL["aclActionPhase"]=="1" || !$act['actFlagHasDeparture']){
                $html .= $this->intra->showButton("finish_{$aclGUID}", $this->intra->translate("Finish"), array('class'=>"eiseIntraActionButton"));
            }
            $html .= $this->intra->showButton("cancel_{$aclGUID}", $this->intra->translate("Cancel"), array('class'=>"eiseIntraActionButton"));

            $html .= "</div>\n";

        }
        
    }

    return $html;

}

/** 
 * This function generates the HTML for displaying action information based on the provided ACL GUID and configuration options. It retrieves the action details from the ACL and ACT arrays, formats the action title, timing, and attributes, and returns the generated HTML. It also handles full edit mode and additional callbacks if specified in the configuration.
 * 
 * @param string $aclGUID - The GUID of the ACL entry to display.
 * @param array $conf - Configuration options for displaying the action information, such as forceFlagWrite and flagFullEdit.
 * 
 * @return string - The generated HTML for the action information.
 * 
 * @category Data Display
 */
function showActionInfo($aclGUID, $conf = array()){

        $defaultConf = array('forceFlagWrite'=>false);

        $conf = array_merge($defaultConf, $conf);

        $rwACL = (array)$this->item['ACL'][$aclGUID];
        $rwACT = (array)$this->conf['ACT'][$rwACL['aclActionID']];

        $html = '';

        $htmlRemove = ($conf['flagFullEdit'] ? '&nbsp;<a href="#remove_acl" class="remove">[x]</a>' : '');

        $htmlTiming = '<div class="dates">'.$this->intra->showDatesPeriod($rwACL['aclATD'], $rwACL['aclATA'], $rwACT['actTrackPrecision']).$htmlRemove.'</div>'."\n";

        $actTitle = ($rwACL['aclActionPhase']==2 
            ? ($rwACT["actTitlePast{$this->intra->local}"]!="" 
                ? $rwACT["actTitlePast{$this->intra->local}"]
                : $rwACT["actTitlePast"])
            : ($rwACT["actTitle{$this->intra->local}"]!="" 
                ? $rwACT["actTitle{$this->intra->local}"]
                : $rwACT["actTitle"])
            )
            .($conf['flagFullEdit'] ?  ' ('.$rwACL['aclActionID'].')' : '');

        $fieldTitle = ($rwACL['aclFinishBy'] 
            ? $rwACL['aclFinishBy'] 
            : ($rwACL['aclStartBy']
                ? $rwACL['aclStartBy']
                : $rwACL['aclInsertBy'])
            )
            .'@'.$rwACL['aclEditDate'].' '.$aclGUID;


        $html .= $this->intra->field($actTitle, null, null, array('fieldClass'=>'eif-acl-title'
                , 'extraHTML'=> $htmlTiming
                , 'dataset' => array('guid'=>$aclGUID)
                , 'title'=>$fieldTitle)
        );

        $traced = $this->getTracedData(array_merge($rwACL, $rwACT));

        if($conf['flagFullEdit']){
            if(isset($rwACT['actFlagHasEstimates']) && $rwACT['actFlagHasEstimates']){
                if(isset($rwACT['actFlagHasDeparture']) && $rwACT['actFlagHasDeparture'])
                    $html .= ( !isset($rwACT['aatFlagTimestamp']) || !isset($rwACT['aatFlagTimestamp']['ETD']) || $rwACT['aatFlagTimestamp']['ETD']=='aclETD' ? $this->intra->field('ETD', 'aclETD_'.$aclGUID, isset($rwACL['aclETD']) ? $rwACL['aclETD'] : '', array('type'=>$rwACT['actTrackPrecision'])) : '');
                $html .= ( !isset($rwACT['aatFlagTimestamp']) || !isset($rwACT['aatFlagTimestamp']['ETA']) || $rwACT['aatFlagTimestamp']['ETA']=='aclETA' ? $this->intra->field('ETA', 'aclETA_'.$aclGUID, isset($rwACL['aclETA']) ? $rwACL['aclETA'] : '', array('type'=>$rwACT['actTrackPrecision'])) : '');
            } 
            if(isset($rwACT['actFlagHasDeparture']) && $rwACT['actFlagHasDeparture'])
                $html .= ( !isset($rwACT['aatFlagTimestamp']) || !isset($rwACT['aatFlagTimestamp']['ATD']) || $rwACT['aatFlagTimestamp']['ATD']=='aclATD'  ? $this->intra->field('ATD', 'aclATD_'.$aclGUID, isset($rwACL['aclATD']) ? $rwACL['aclATD'] : '', array('type'=>$rwACT['actTrackPrecision'])) : '');
            $html .= ( !isset($rwACT['aatFlagTimestamp']) || !isset($rwACT['aatFlagTimestamp']['ATA']) || $rwACT['aatFlagTimestamp']['ATA']=='aclATA' ? $this->intra->field('ATA', 'aclATA_'.$aclGUID, isset($rwACL['aclATA']) ? $rwACL['aclATA'] : '', array('type'=>$rwACT['actTrackPrecision'])) : '');
        }


        $html .= $this->getAttributeFields(array_keys(isset($rwACT['aatFlagToTrack']) ? (array)$rwACT['aatFlagToTrack'] : array()), $traced
            , array_merge($conf, array('suffix'=>'_'.$aclGUID))
            );

        $html .= ((isset($rwACT['actFlagComment']) && $rwACT['actFlagComment']) || (isset($rwACL['aclComments']) && $rwACL['aclComments']) || $conf['flagFullEdit']
            ? $this->intra->field($this->intra->translate('Comments'), 'aclComments_'.$aclGUID, isset($rwACL['aclComments']) ? $rwACL['aclComments'] : '')
            : '');
        
        if(isset($conf['actionCallBack']) && $conf['actionCallBack']){
            $html .= eval($conf['actionCallBack'].";");
        }

        return $html;

}

/**
 * This function retrieves the traced data for an ACL item. It checks if the traced data is already stored in the ACL item, and if not, it queries the log table to retrieve the tracked fields based on the configuration. It returns an associative array of traced data, including any dropdown text for fields that are of type combobox, select, or ajax_dropdown. 
 * 
 * @param array $rwACL - The ACL item data containing the GUID and tracked fields.
 * 
 * @return array - An associative array of traced data for the ACL item, including field values and dropdown text if applicable.
 * 
 * @category Data Display
 */
public function getTracedData($rwACL){

    if( isset($rwACL['aclItemTraced']) && $rwACL['aclItemTraced'] )
        return json_decode($rwACL['aclItemTraced'],true);

    $aRet = [];

    if(!empty($rwACL['aatFlagToTrack']) && isset($this->conf['logTable']) && $this->conf['logTable']){
        $sqlLog = "SELECT * FROM {$this->conf['logTable']} WHERE l{$this->table['prefix']}GUID='{$rwACL['aclGUID']}'";
        $rwLog = $this->oSQL->f($sqlLog);
        foreach ((array)$rwACL['aatFlagToTrack'] as $field=>$stuff){
            if (isset($this->conf['ATR'][$field])) {
                $rwATR = $this->conf['ATR'][$field];
                $aRet[$field] = isset($rwLog["l{$field}"]) ? $rwLog["l{$field}"] : null;
                if (isset($rwATR["atrType"]) && in_array($rwATR["atrType"], Array("combobox", 'select', "ajax_dropdown"))){
                    $atrID = isset($rwATR["atrID"]) ? $rwATR["atrID"] : '';
                    $aRet[$atrID."_text"] = $this->getDropDownText($rwATR, $aRet[$field]);
                }
            }
        }
    }

    return $aRet;

}

/**
 * This function retrieves the details of an action based on the provided query parameters. It checks if either an action ID or an ACL GUID is provided, retrieves the corresponding action and ACL data, and returns them in a JSON response along with the attribute definitions.
 * 
 * @param array $q - Query parameters containing either 'actID' or 'aclGUID'.
 * 
 * @throws Exception - If neither action ID nor ACL GUID is provided, or if the provided ACL GUID is invalid.
 * 
 * @return void - It throws a JSON response with action and ACL details.
 * 
 * @category Data Display
 * @category Events and Actions
 */
function getActionDetails($q){

    $arrRet = Array();
          
    if (!isset($q['actID']) && !isset($q['aclGUID'])){
        throw new Exception("Action details cannot be resolved: nether action not action log IDs provided", 1);
    }

    if(isset($q['aclGUID']) && $q['aclGUID']!=''){
        $acl = $this->item['ACL'][$q['aclGUID']];
        if(!$acl)
            throw new Exception("Action details cannot be resolved: action log ID provided is wrong", 1);
        $act = $this->conf['ACT'][$acl['actID']];
    } else {
        $acl = $act = (isset($q['actID']) ? $this->conf['ACT'][$q['actID']] : array());
    }

    $this->intra->json('ok', '', Array("acl"=>$acl 
        , 'act'=>$act
        , 'atr'=>$this->conf['ATR']
        ));
}

/**
 * This function retrieves the action data based on the provided ACL GUID and an optional ACL record. It fetches the action log entry, merges it with the configuration data for statuses, and returns the action data including old and new status information.
 * 
 * @param string $aclGUID - The GUID of the ACL entry to retrieve action data for.
 * @param array|null $rwACL - An optional array containing the ACL record. If not provided, it will fetch the ACL data from the database.   
 * 
 * @return array - An associative array containing the action data, including old and new status information.
 * 
 * @category Data Display
 * @category Events and Actions
 */
public function getActionData($aclGUID, $rwACL=null){
    
    $oSQL = $this->oSQL;
    $entID = $this->entID;
    
    $arrRet = Array();
    
    if (!$aclGUID) return;
    
    if(is_array($rwACL)){
        $rwACT = $rwACL;
    } else {
        $sqlACT = "SELECT ACL.*
           FROM stbl_action_log ACL
           WHERE aclGUID='{$aclGUID}'";
        
        $rwACT = $oSQL->fetch_array($oSQL->do_query($sqlACT));
    }
    $rwACT['actID'] = $rwACT['aclActionID'];

    //$rwACT = @array_merge($this->conf['ACT'][$rwACT['aclActionID']], $rwACT);
    $staID_Old = isset($rwACT['aclOldStatusID']) && isset($this->conf['STA'][$rwACT['aclOldStatusID']]) ? $this->conf['STA'][$rwACT['aclOldStatusID']]['staID'] : null;
    $staTitle_Old = isset($rwACT['aclOldStatusID']) && isset($this->conf['STA'][$rwACT['aclOldStatusID']]) ? $this->conf['STA'][$rwACT['aclOldStatusID']]['staTitle'] : '';
    $staTitleLocal_Old = isset($rwACT['aclOldStatusID']) && isset($this->conf['STA'][$rwACT['aclOldStatusID']]) ? $this->conf['STA'][$rwACT['aclOldStatusID']]['staTitleLocal'] : '';
    $rwACT = @array_merge($rwACT, array(
        'staID_Old' => $staID_Old
        , 'staTitle_Old' => $staTitle_Old
        , 'staTitleLocal_Old' => $staTitleLocal_Old
        ));
    $staID_New = isset($rwACT['aclNewStatusID']) && isset($this->conf['STA'][$rwACT['aclNewStatusID']]) ? $this->conf['STA'][$rwACT['aclNewStatusID']]['staID'] : null;
    $staTitle_New = isset($rwACT['aclNewStatusID']) && isset($this->conf['STA'][$rwACT['aclNewStatusID']]) ? $this->conf['STA'][$rwACT['aclNewStatusID']]['staTitle'] : '';
    $staTitleLocal_New = isset($rwACT['aclNewStatusID']) && isset($this->conf['STA'][$rwACT['aclNewStatusID']]) ? $this->conf['STA'][$rwACT['aclNewStatusID']]['staTitleLocal'] : '';
    $rwACT = @array_merge($rwACT, array(
        'staID_New' => $staID_New
        , 'staTitle_New' => $staTitle_New
        , 'staTitleLocal_New' => $staTitleLocal_New
        ));
    
    return $rwACT;
    
}

/**
 * This function retrieves the text for a dropdown field based on the provided attribute array and value. It checks if the attribute type is a combobox and retrieves the options either from the programmer reserved data or from the data source. If the value exists in the options, it returns the corresponding text; otherwise, it returns a default text if specified.
 * 
 * Data source can be a PHP array defined as ```Array()```, a JSON string, or a database object like table or view. The fields where it could be stored are:
 * - `atrProgrammerReserved` - a PHP array defined as ```Array()``` (legacy)
 * - `atrDataSource` - a JSON string or a PHP array defined as ```Array()``` or name of database table or view
 * 
 * In case when it is table or view, the function will retrieve the text from the common views using the `getDataFromCommonViews` method. If you need to specify a prefix or extra parameters, you can use the `atrProgrammerReserved` field to define them, pipe-delimited, e.g. 'prx|123'
 * 
 * @param array $arrATR - The attribute array containing the type, data source, and programmer reserved data.
 * @param mixed $value - The value for which to retrieve the dropdown text. 
 * 
 * @return string|null - The text corresponding to the value in the dropdown options, or a default text if the value is not found.
 * 
 * @category Data Display
 */
public function getDropDownText($arrATR, $value){

    $strRet = null;

    $arrOptions = array();

    if ( ($arrATR["atrType"] == "combobox") && $arrATR["atrDataSource"]=='' && preg_match('/^Array\(/i', $arrATR["atrProgrammerReserved"]) ) {
        eval( '$arrOptions = '.$arrATR["atrProgrammerReserved"].';' );
        $strRet = ($arrOptions[$value]!=''
            ? $arrOptions[$value]
            : $arrATR["atrTextIfNull"]);
    } elseif ($arrATR["atrType"] == "combobox" && preg_match('/^Array\(/i', $arrATR["atrDataSource"]) ) {
        eval( '$arrOptions = '.$arrATR["atrDataSource"].';' );
        $strRet = (isset($arrOptions[$value]) && $arrOptions[$value]!=''
            ? $arrOptions[$value]
            : $arrATR["atrTextIfNull"]);
    } elseif ($arrATR["atrType"] == "combobox" && ($arrOptions = @json_decode($arrATR["atrDataSource"], true)) ) {
        $strRet = (isset($arrOptions[$value]) && $arrOptions[$value]!=''
            ? $arrOptions[$value]
            : $arrATR["atrTextIfNull"]);
    } else {
        list($prefix, $extra) = self::getPrefixExtra($arrATR["atrProgrammerReserved"]);
        $strRet = ($value != "" && $arrATR["atrDataSource"]
            ? $this->oSQL->d($this->intra->getDataFromCommonViews($value, null, $arrATR["atrDataSource"], $prefix, true, $extra))
            : $arrATR["atrTextIfNull"]
        );
    }

    return $strRet;
}

/**
 * This function retrieves the "Who's Next" status information for the current status ID as a handle for Data Read event 'get_whos_next'. It calls the `getWhosNextStatus` method to get the next status information and formats it into an HTML structure. The function returns a div containing the "Who's Next" status information.
 * 
 * @return string - The generated HTML for the "Who's Next" status information.
 * 
 * @category Data Display
 * @category Events and Actions
 */
public function get_whos_next($q){

    $html = $this->getWhosNextStatus($this->staID, 1); // 1. display current status information
    return '<div class="ei-whos-next">'.$html.'</div>';

}

/**
 * This function retrieves the next bigger status ID based on the current status ID. It checks the actions defined for the current status and finds the next status that is greater than the current one, returning the maximum status ID if no larger status is found.
 * 
 * @param int $staID - The ID of the current status for which to find the next bigger status.
 * 
 * @return int - The ID of the next bigger status, or the maximum status ID if no larger status is found.
 * 
 * @category Data Display
 * @category Events and Actions
 */
public function getNextBiggerStatus($staID){

    $sta = $this->conf['STA'][$staID];

    $nextBiggerStatus = max(array_keys($this->conf['STA']));
    foreach ((array)$sta['ACT'] as $act){
        // echo '<pre>'.var_export($act['RLA'], true);
        if((isset($act['actNewStatusID'][0]) ? $act['actNewStatusID'][0] : null)===null 
            || (isset($act['actNewStatusID'][0]) ? $act['actNewStatusID'][0] : null)==$staID 
            || count($act['RLA'])==0
            || (isset($act['actFlagSystem']) && $act['actFlagSystem']))
            continue;
        if((isset($act['actNewStatusID'][0]) ? $act['actNewStatusID'][0] : null)>$staID && (isset($act['actNewStatusID'][0]) ? $act['actNewStatusID'][0] : null) < $nextBiggerStatus){
            $nextBiggerStatus = $act['actNewStatusID'][0];   
        }
    }

    return $nextBiggerStatus;

}

/**
 * This function generates the HTML for the "Who's Next" status based on the provided status ID and counter. 
 * 
 * It goes status by status in upcoming order, displaying the status title, description, and available actions. It also lists the users and roles associated with each action, highlighting default actions and disabled roles.
 * 
 * @param int $staID - The ID of the status for which to generate the "Who's Next" information.
 * @param int $counter - The counter for the tier level of the status. Tier = 1 means direct actors, tier = 2 means actors of the next escalation level, e.g management.
 * 
 * @return string - The generated HTML for the "Who's Next" status information, including status title, description, actions, and associated users and roles.
 * 
 * @category Data Display
 * @category Events and Actions
 * 
 */
public function getWhosNextStatus($staID, $counter){
    
    $html = '';
    $sta = $this->conf['STA'][$staID];

    $html .= '<div class="whos-next-status tier-'.$counter.'">';
    $html .= '<div class="status-title"><span class="counter">'.$counter.'</span><span class="title">'.$sta['staTitle'.$this->intra->local].'</span></div>';

    
    $nextBiggerStatus = $this->getNextBiggerStatus($staID);
    
    $defaultActID = null;
    $actNext = [];
    $htmlNext = '';
    foreach ((array)$sta['ACT'] as $act){
        if(count($act['RLA'])==0)
            continue;
        if((isset($act['actNewStatusID'][0]) ? $act['actNewStatusID'][0] : null)==$nextBiggerStatus){
            $defaultActID = ($defaultActID === null ? $act['actID'] : $defaultActID);
            $actNext[] = $act;
        }
    }
    
    $html .= '<div class="status-description">'.$sta['staDescription'.$this->intra->local].'</div>';

    $html .= '<ul class="actions">';
    // $html .= '<pre>'.var_export((array)$sta['ACT'], true).'</pre>';
    $htmlNext = '';
    foreach ((array)$sta['ACT'] as $act) {
        if((isset($act['actNewStatusID'][0]) ? $act['actNewStatusID'][0] : null)==$staID)
            continue;
        // if($act['actFlagSystem'])
        //     continue;
        if($this->conf['CHK'] && isset($this->item['CHK_ACT_unnecesary']) && in_array($act['actID'], (array)$this->item['CHK_ACT_unnecesary']))
            continue;
        if(count($act['RLA'])==0 && !$act['actFlagSystem'])
            continue;
        $classes = ($defaultActID==$act['actID'] ? ' default' : '');
        $iconClass = (preg_match('/^fa\-/', trim($act['actButtonClass'])) ? 'fa ' : (preg_match('/^ss\_/', trim($act['actButtonClass'])) ? 'ss_sprite ' : '')).$act['actButtonClass'];
        $html .= '<li class="ei-whosnext-action '.$classes.'" data-act-title="'.htmlspecialchars($act["actTitle{$this->intra->local}"]).'"><i class="'.$iconClass.'"> </i>'.$act["actTitle{$this->intra->local}"];
        if($act["actDescription{$this->intra->local}"]){
            $html .= '<small>'.$act["actDescription{$this->intra->local}"].'</small>';
        }
        if( !(isset($act['actFlagSystem']) && $act['actFlagSystem']) ){

            $html .= '<ul class="users-roles">';
            $aUsers = array();
            $this->_aUser_Role_Tier = array();
            $aRoles = array();
            $aVirtualRoles = array();
            $aVirtualRoleMembers = array();
            // $html .= '<pre>'.var_export($act['RLA'], true).'</pre>';
            foreach ($act['RLA'] as $rolID) {
                $aRoles[] = $rolID;
                if($rolID==$this->conf['RoleDefault']){
                    $html .= '<li class="users"><span class="user-info default">'.$this->intra->translate('Any user').'</span>';
                    $aRoles = array( $rolID );
                    continue;
                }
                elseif ($this->conf['Roles'][$rolID]['rolFlagVirtual']){
                    $aVMMembers = $this->getVirtualRoleMembers($rolID);
                    foreach ($aVMMembers as $usrID => $originRole) {
                        $aUsers[] = strtoupper($usrID); 
                        $aVirtualRoles[$rolID][] = $originRole;
                        $aVirtualRoleMembers[$rolID][] = $usrID;
                        $role_tier = isset($act['RLA_tiers'][$rolID]) ? $act['RLA_tiers'][$rolID] : 0;
                        $user_role_tier = isset($this->_aUser_Role_Tier[$usrID][$rolID]) ? $this->_aUser_Role_Tier[$usrID][$rolID] : 0;
                        $this->_aUser_Role_Tier[$usrID][$rolID] = $role_tier > $user_role_tier 
                            ? $role_tier
                            : $user_role_tier ;
                    }
                } else {
                    $_users = (array)$this->intra->getRoleUsers($rolID);
                    foreach ($_users as $usrID) {
                        $role_tier = isset($act['RLA_tiers'][$rolID]) ? $act['RLA_tiers'][$rolID] : 0;
                        $user_role_tier = isset($this->_aUser_Role_Tier[$usrID][$rolID]) ? $this->_aUser_Role_Tier[$usrID][$rolID] : 0;
                        $this->_aUser_Role_Tier[$usrID][$rolID] = $role_tier > $user_role_tier 
                            ? $role_tier
                            : $user_role_tier ;
                    }
                    $aUsers = array_merge( $aUsers,  $_users);
                }
            }

            if($aRoles[0]!=$this->conf['RoleDefault']){

                $aUsers = array_values(array_unique($aUsers));
                usort($aUsers, array($this, '_sort_User_Role_Tier'));
                $html .= '<li class="users">';
                $htmlUserList = '';
                foreach ($aUsers as $ix=>$usrID) {
                    $class = '';
                    if($counter==1){
                        $aDRoles = $this->checkDisabledRoleMembership($usrID, $act);
                        foreach($aDRoles as $rolID_)
                            $class .= ' disabled-'.strtolower(preg_replace('/^\_*/', '', $rolID_));
                          
                    }
                    $class = 'user-info'.($class ? ' disabled' : '').$class;
                    $class .= isset($this->conf['flagWhosNextUserClickable']) && $this->conf['flagWhosNextUserClickable'] && $counter==1
                        ? ' clickable'
                        : '';
                    $htmlUserList .= '<span class="'.$class.'" data-usrid="'.htmlspecialchars($usrID).'">'.$this->intra->getUserData($usrID).($ix==count($aUsers)-1 ? '' : ', ').'</span>';
                }
                $html .= $htmlUserList;
                $html .= '<li class="roles">';
                $htmlRoleList = '';
                foreach ((array)$act['RLA_tiers'] as $rolID => $tier) {
                    if(!$this->conf['Roles'][$rolID]['rolFlagVirtual']) {
                        $htmlRoleList .= ($htmlRoleList ? ', </span>' : '').'<span class="role-info">'.$this->conf['Roles'][$rolID]['rolTitle'.$this->intra->local];
                    } else {
                        $rolIDs_original = isset($aVirtualRoles[$rolID]) ? array_unique((array)$aVirtualRoles[$rolID]) : array();
                        foreach ($rolIDs_original as $rolID_original) {
                            $htmlRoleList .= ($htmlRoleList ? ', </span>' : '').'<span class="role-info">'
                                .($rolID_original 
                                    ? $this->conf['Roles'][$rolID_original]['rolTitle'.$this->intra->local].' ('.$this->conf['Roles'][$rolID]['rolTitle'.$this->intra->local].')'
                                    : $this->conf['Roles'][$rolID]['rolTitle'.$this->intra->local]
                                    );
                        }
                    }
                }
                $html .= $htmlRoleList.($htmlRoleList ? '</span>' : '');

            }
            $html .= '</ul>';

        } else {
            $html .= '<ul class="users-roles"><li class="system">'.__("System action").'</li></ul>';
        }
        
        if($defaultActID && $defaultActID==$act['actID']){
            $htmlNext = $this->getWhosNextStatus($nextBiggerStatus, $counter+1);
        }

    }
    
    $html .= '</ul>';
    $html .= '</div> <!-- class="whos-next-status" -->';
    $html .= $htmlNext;

    return $html;

}

/**
 * @ignore
 */
private function _sort_User_Role_Tier($a, $b){
    $tierA = isset($this->_aUser_Role_Tier[$a]) ? max($this->_aUser_Role_Tier[$a]) : 0;
    $tierB = isset($this->_aUser_Role_Tier[$b]) ? max($this->_aUser_Role_Tier[$b]) : 0;
    $tierDiff = $tierA - $tierB;
    return ($tierDiff 
        ? $tierDiff
        : ($a > $b ? 1 : -1)
        );
}

/**
 * @ignore
 */
public function checkDisabledRoleMembership($usrID, $act, &$reason = ''){
    $aRet = array();
    $rolemembership = '';
    foreach(array('editor', 'creator') as $rrr){
        $rolID = '__'.strtoupper($rrr);
        $flagKey = 'actFlagNot4'.ucfirst($rrr);
        if(isset($act[$flagKey]) && $act[$flagKey] && in_array($usrID, array_keys($this->getVirtualRoleMembers($rolID))) ){
            $aRet[] = $rolID;   
            $rolemembership .= ($rolemembership ? ', ' : '').$this->conf['Roles'][$rolID]['rolTitle'.$this->intra->local];
        }
    }  
    $reason = $rolemembership;
    return $aRet;
}

}

