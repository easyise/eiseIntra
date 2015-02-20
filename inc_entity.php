<?php

class eiseEntity {

const sessKeyPrefix = 'ent:';

public $oSQL;
public $entID;

public $intra;

public $ent; // backward-compatibility

public $conf;

private $eiseListPath = "../common/eiseList";
private $eiseGridPath = "../common/eiseGrid";

function __construct ($oSQL, $intra, $entID) {
    
    $this->oSQL = $oSQL;
    $this->intra = $intra;
    
    if (!$entID)  throw new Exception ("Entity ID not set");
    
    $this->entID = $entID;

    $this->init();
    
}

private function init(){

    $sessKey = self::sessKeyPrefix.
        ($this->intra->conf['systemID'] ? $this->intra->conf['systemID'].':' : '')
        .$this->entID;

    if($_SESSION[$sessKey]){
        $this->conf = $_SESSION[$sessKey];
        return $this->conf;
    }

    $oSQL = $this->oSQL;

    // read entity information
    $sqlEnt = "SELECT *
        , (SELECT GROUP_CONCAT(rolID) FROM stbl_role) as roles
        FROM stbl_entity WHERE entID=".$oSQL->e($this->entID);
    $rsEnt = $oSQL->q($sqlEnt);
    if ($oSQL->n($rsEnt)==0){
        throw new Exception("Entity '{$entID}' not found");
    }


    
    $this->ent = $oSQL->f($rsEnt);
    
    $arrRoles = explode(',', $this->ent['roles']); unset($this->ent['roles']);

    $this->ent["entScriptPrefix"] = self::getScriptPrefix($this->ent);

    $this->conf = $this->ent;
    
    // read attributes
    $this->conf['ATR'] = array();
    $sqlAtr = "SELECT * 
        FROM stbl_attribute 
        WHERE atrEntityID=".$oSQL->e($this->entID)."
        ORDER BY atrOrder";
    $rsAtr = $oSQL->q($sqlAtr);
    while($rwAtr = $oSQL->f($rsAtr)){
        $this->conf['ATR'][$rwAtr['atrID']] = $rwAtr;
    }

    // read status_attribute
    $this->conf['STA'] = array();
    $sqlSat = "SELECT stbl_status.*,stbl_status_attribute.*  
            FROM stbl_status_attribute 
                INNER JOIN stbl_status ON staID=satStatusID AND satEntityID=staEntityID
                INNER JOIN stbl_attribute ON atrID=satAttributeID
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
            $this->conf['STA'][$rwSat['staID']]['satFlagShowInForm'][$rwSat['satAttributeID']] = (int)$rwSat['satFlagEditable'];
        if($rwSat['satFlagEditable'])
            $this->conf['STA'][$rwSat['staID']]['satFlagEditable'][$rwSat['satAttributeID']] = (int)$rwSat['satFlagEditable'];
        if($rwSat['satFlagShowInList'])
            $this->conf['STA'][$rwSat['staID']]['satFlagShowInList'][$rwSat['satAttributeID']] = $rwSat['satAttributeID'];
        if($rwSat['satFlagTrackOnArrival'])
            $this->conf['STA'][$rwSat['staID']]['satFlagTrackOnArrival'][$rwSat['satAttributeID']] = $rwSat['satAttributeID'];
          
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
                , 'aatFlagToChange'=>(int)$rwAAt['aatFlagToChange']);
        if($rwAAt['aatFlagMandatory'])
            $this->conf['ACT'][$rwAAt['actID']]['aatFlagMandatory'][$rwAAt['aatAttributeID']] = array('aatFlagEmptyOnInsert'=>(int)$rwAAt['aatFlagEmptyOnInsert']
                , 'aatFlagToChange'=>(int)$rwAAt['aatFlagToChange']);
        if($rwAAt['aatFlagTimestamp']){
            if (isset($this->conf['ACT'][$rwAAt['actID']]['aatFlagTimestamp'][$rwAAt['aatFlagTimestamp']]))
                $this->conf['ACT'][$rwAAt['actID']]['aatFlagTimestamp'][$rwAAt['aatFlagTimestamp']] = $rwAAt['aatAttributeID'];
        }
            
    }

    // read action-status
    $sqlATS = "SELECT atsOldStatusID
        , atsNewStatusID
        , atsActionID
        FROM stbl_action_status
        INNER JOIN stbl_action ON actID=atsActionID
        WHERE actEntityID=".$oSQL->e($this->entID)." OR actEntityID IS NULL
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

public static function getItemIDField($ent){
    return $ent['entID'].'ID';
}

public static function getScriptPrefix($ent){
    return str_replace("tbl_", "", $ent["entTable"]);
}

public static function getFormURL($conf, $item){
    $entItemIDField = self::getItemIDField($conf);
    $formURL = self::getScriptPrefix($conf).'_form.php?'.$entItemIDField.'='.urlencode($item[$entItemIDField]);
    return $formURL;
}

/* returns array with roles that current user belongs to, can be overriden with methods from successors */
public function getRoleList(){
    return $this->intra->arrUsrData['roleIDs'];
}

/* checks whether is status ID in cookies or not */
private function detectStatusID(){

    $this->staID = isset($_GET[$this->entID."_staID"]) ? $_GET[$this->entID."_staID"] : $_COOKIE[$this->entID."_staID"];
    if (isset($_GET[$this->entID."_staID"]))
        SetCookie($this->entID."_staID", $_GET[$this->entID."_staID"]);

}

/* reads data from stbl_status table */
protected function __collectDataStatus($staID = null){
    
    $rwSta = Array();
    $statusID = ($staID !== null ? $staID : $this->staID);
    if ((string)$statusID!=""){
        $sqlSta = "SELECT * FROM stbl_status WHERE staID=".$this->oSQL->e($statusID)." AND staEntityID=".$this->oSQL->e($this->entID);
        $rsSta = $this->oSQL->q($sqlSta);
        $rwSta = $this->oSQL->f($rsSta);
        $this->rwSta = ($staID === null ? $rwSta : $this->rwSta);
    }
    
    return $rwSta;
}

/* reads data from stbl_attribute table */
protected function __collectDataAttributes(){
    
    $this->arrAtr = Array();
    
    $sqlAtr = "SELECT * 
        FROM stbl_attribute 
        ".($this->staID!=="" 
            ? "LEFT OUTER JOIN stbl_status_attribute ON satStatusID=".$this->oSQL->e($this->staID)." AND satAttributeID=atrID AND satEntityID=atrEntityID" 
            : "")."
        WHERE atrEntityID=".$this->oSQL->e($this->entID)." AND atrFlagDeleted=0
        ORDER BY atrOrder ASC";
    $rsAtr = $this->oSQL->q($sqlAtr);
    while ($rwAtr = $this->oSQL->f($rsAtr)){
        $this->arrAtr[] = $rwAtr;
    }
    
}

/* reads actions that available in given/current status */
protected function collectDataActions($arrConfig = Array(), $staID = null){
    
    $arrAct = Array();
    
    $strRoleList = implode("', '", $this->getRoleList());
    
    if ($staID !== null){
        $rwSta = $this->collectDataStatus($staID);
        $statusID = $staID;
    } else {
        $rwSta = $this->rwSta;
        $statusID = $this->staID;
    }
    
    if ((string)$statusID!=""){
       $sqlAct = "SELECT 
              actID,
              actEntityID,
              atsOldStatusID,
              atsNewStatusID,
              actTitle,
              actTitleLocal,
              actTitlePast,
              actTitlePastLocal,
              actDescription,
              actDescriptionLocal,
              actFlagDeleted,
              actPriority,
              actFlagComment,
              actFlagAutocomplete,
                  entID,
                  entTitle,
                  entTitleLocal,
                  entTable,
                  entPrefix
             , ".$this->oSQL->e($rwSta["staTitle"])." as staTitle_Old
             , STA_NEW.staTitle AS staTitle_New
             , ".$this->oSQL->e($rwSta["staTitleLocal"])." as staTitleLocal_Old
             , STA_NEW.staTitleLocal as staTitleLocal_New
          FROM stbl_action_status
          INNER JOIN stbl_action ON atsActionID=actID
           LEFT OUTER JOIN stbl_status STA_NEW ON atsNewStatusID=STA_NEW.staID AND actEntityID=STA_NEW.staEntityID
          LEFT JOIN stbl_role_action 
            INNER JOIN stbl_role ON rlaRoleID=rolID
            ON actID=rlaActionID
          LEFT OUTER JOIN stbl_entity ON entID=".$this->oSQL->e($this->entID)."
          WHERE (actEntityID=".$this->oSQL->e($this->entID)."".($arrConfig["flagActNoDefaults"] ? "" : " OR actEntityID IS NULL").") 
             AND (atsOldStatusID='".$statusID."' OR atsOldStatusID IS NULL) 
             AND ".($arrConfig["aclIncompleteActionID"]=="" 
                    ? "actID NOT IN (1".
                        (!$rwSta["staFlagCanUpdate"] ? ", 2" : "").
                        (!$rwSta["staFlagCanDelete"] ? ", 3" : "").")"
                    : "actID='".$arrConfig["aclIncompleteActionID"]."'")."
             AND (actID BETWEEN 1 AND 3 OR (rlaRoleID IN ('".$strRoleList."') OR rolFlagDefault=1 ))
			 AND actFlagDeleted=0
          GROUP BY
            actID,
              actEntityID,
              atsOldStatusID,
              atsNewStatusID,
              actTitle,
              actTitleLocal,
              actTitlePast,
              actTitlePastLocal,
              actDescription,
              actDescriptionLocal,
              actFlagDeleted,
              actPriority,
              actFlagComment,
              actFlagAutocomplete,
                  entID,
                  entTitle,
                  entTitleLocal,
                  entTable,
                  entPrefix
           ORDER BY actPriority ASC, actID ASC";
    } else {
       $sqlAct = "SELECT * 
          FROM stbl_action 
          LEFT OUTER JOIN stbl_entity ON entID=".$this->oSQL->e($this->entID)."
          WHERE actID=1";
    }
    //echo "<pre>".$sqlAct."</pre>";
    $rsAct = $this->oSQL->do_query($sqlAct);
    while ($rwAct = $this->oSQL->fetch_array($rsAct)){
        $arrAct[] = $rwAct;
    }
    
    if ($staID===null)
        $this->arrAct = $arrAct;
    
    return $arrAct;
    
}

public function getList($arrAdditionalCols = Array(), $arrExcludeCols = Array()){
    
    $oSQL = $this->oSQL;
    $entID = $this->entID;
    
    $this->intra->arrUsrData = $this->intra->arrUsrData;
    $conf = $this->conf;
    $strLocal = $this->intra->local;
    
    $listName = $entID;
    
    $this->detectStatusID();
    
    $staID = $this->staID;

    $lst = new eiseList($oSQL, $listName, Array('title'=>$this->conf["entTitle{$strLocal}Mul"].($staID!=="" 
            ? ': '.$this->conf['STA'][$staID]["staTitle{$strLocal}"] 
            : '')
        ,  "intra" => $this->intra
        , "cookieName" => $listName.$staID
        , "cookieExpire" => time()+60*60*24*30
            , 'defaultOrderBy'=>"{$this->entID}EditDate"
            , 'defaultSortOrder'=>"DESC"
            , 'sqlFrom' => "{$this->conf["entTable"]} LEFT OUTER JOIN stbl_status ON {$entID}StatusID=staID AND staEntityID='{$entID}'".
                ((!in_array("actTitle", $arrExcludeCols) && !in_array("staTitle", $arrExcludeCols))
                    ? "LEFT OUTER JOIN stbl_action_log LAC
                    INNER JOIN stbl_action ON LAC.aclActionID=actID 
                    ON {$entID}ActionLogID=LAC.aclGUID
                    LEFT OUTER JOIN stbl_action_log SAC ON {$entID}StatusActionLogID=SAC.aclGUID"
                    : "")
        ));

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
            , 'href'=> $conf["entScriptPrefix"]."_form.php?".$entID."ID=[".$entID."ID]"
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
            , 'filter' => "actTitle{$strLocal}"
            , 'order_field' => "actTitlePast{$strLocal}"
            , 'nowrap' => true
            );
            
    
    $strFrom = "";
    
    $iStartAddCol = 0;
    
    for ($ii=$iStartAddCol;$ii<count($arrAdditionalCols);$ii++){
        if($arrAdditionalCols[$iStartAddCol]['columnAfter']!='')
            break;
        $lst->Columns[] = $arrAdditionalCols[$iStartAddCol];
        $iStartAddCol=$ii;
    }
    
    foreach($this->conf['ATR'] as $rwAtr){
        
        if ($rwAtr["atrID"]==$entID."ID") // ID field to skip
            continue;

        if ($rwAtr["atrFlagHideOnLists"]) // if column should be hidden, skip
            continue;

        if(!empty($this->staID) && !in_array($rwAtr['atrID'], $conf['STA'][$this->staID]['satFlagShowInList'])) // id statusID field is set and atrribute is not set for show, skip
            continue;
        

        if ($rwAtr['atrFlagNoField']){
            $sqlForAtr = "SELECT atvValue FROM stbl_attribute_value WHERE atvAttributeID='".$rwAtr['atrID']."' AND atvEntityItemID=".$entID."ID ORDER BY atvEditDate DESC LIMIT 0,1";
            $arr = array(
                'title' => (
                        $rwAtr["atrShortTitle{$strLocal}"]!="" 
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
                , 'field' => "atr_".$rwAtr['atrID']
                , 'sql' => $sqlForAtr
                , 'filter' => "atr_".$rwAtr['atrID']
                , 'order_field' => "atr_".$rwAtr['atrID']
                );   
         
        } else {
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
       
       $lst->Columns[] = $arr;
       
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

public function getFieldsHTML($arrConfig = Array()){

    if($arrConfig['flagFullEdit']){
        foreach($this->conf['ATR'] as $ix=>$rwAtr)
            $arrAtrToLoopThru[$ix] = true;
    } else {
        $staID = (isset($this->item) ? $this->item['staID'] : $arrConfig['staID']);
        $arrAtrToLoopThru = $this->conf['STA'][(int)$this->staID]['satFlagShowInForm'];
    }

    foreach($arrAtrToLoopThru as $atrID=>$FlagWrite){
        
        if (!$this->flagArchive) {
            
            if ($arrConfig['flagShowOnlyEditable'] && !$FlagWrite)
                continue;
                
        } else {
            $FlagWrite = true;
        }

        $rwAtr = $this->conf['ATR'][$atrID];

        if (!$this->flagArchive && $rwAtr['atrFlagDeleted'] && !$rwAtr)
            continue;
        
        $strFields .= ($strFields!="" ? "\r\n" : "");
        $strFields .= "<div class=\"eiseIntraField\" id=\"fld_{$atrID}\">";
        $strFields .= "<label id=\"title_{$atrID}\">".$rwAtr["atrTitle{$this->intra->local}"].":</label>";
        
        if ( isset($this->item) ){
            $rwAtr["value"] = $this->item[$rwAtr["atrID"]];
            $rwAtr["text"] = $this->item[$rwAtr["atrID"]."_Text"];
        }

        $rwAtr['FlagWrite'] = $FlagWrite;
        
        $strFields .=  $this->showAttributeValue($rwAtr, "");
        $strFields .= "</div>\r\n\r\n";
            
    }
    
    if (isset($arrConfig['extraFields']))
    foreach($arrConfig['extraFields'] as $field){
        
        $strFields .= ($strFields!="" ? "\r\n" : "");
        $strFields .= "<div class=\"eiseIntraField\" id=\"fld_{$field['id']}\">";
        $strFields .= "<label".($field['id'] ? " id=\"title_".$field['id']."\"" : '').">".$field['title'].":</label>";
        
        $strFields .=  '<div class="eiseIntraValue">'.$field['html'].'</div>';
        
        $strFields .= "</div>\r\n\r\n";
    }
    
    return $strFields;
    
}

function showAttributeValue($rwAtr, $suffix = ""){
    
    $strRet = "";
    
    $value = $rwAtr["value"];
    $text = $rwAtr["text"];
    
    $oSQL = $this->oSQL;
    $intra = $this->intra;
    
    $inputName = $rwAtr["atrID"].$suffix;
    $arrInpConfig = Array("FlagWrite"=>$this->intra->arrUsrData["FlagWrite"] &&  $rwAtr['FlagWrite']);

    if ($rwAtr['atrClasses']){
        $arrInpConfig['class'] = $rwAtr['classes'];
    }
    if ($rwAtr['atrUOMTypeID']){
        $arrInpConfig['class'] = ($arrInpConfig['class'] ? ' ' : '').'eiseIntra_hasUOM';
    }
    
    switch ($rwAtr['atrType']){
       case "datetime":
         $dtVal = $intra->datetimeSQL2PHP($value);
       case "date":
         $dtVal = $dtVal ? $dtVal : $intra->dateSQL2PHP($value);
         $strRet = $intra->showTextBox($inputName, $dtVal, 
            array_merge($arrInpConfig, Array("strAttrib" => " old_val=\"".htmlspecialchars($dtVal)."\""
                , "type"=>$rwAtr['atrType']
                )
            )); 
         break;
       case "combobox":
            if (!$arrInpConfig["FlagWrite"]){ // if read-only && text is set
                $arrOptions[$value]=$text;
            } else {
                if (preg_match("/^(vw|tbl)_/", $rwAtr["atrDataSource"])){
                    $rsCMB = $intra->getDataFromCommonViews(null, null, $rwAtr["atrDataSource"]
                        , (strlen($rwAtr["atrProgrammerReserved"])<=3 ? $rwAtr["atrProgrammerReserved"] : ""));
                    $arrOptions = Array();
                    while($rwCMB = $oSQL->fetch_array($rsCMB))
                        $arrOptions[$rwCMB["optValue"]]=$rwCMB["optText"];
                }
                if (preg_match("/^Array/i", $rwAtr["atrProgrammerReserved"])){
                    eval ("\$arrOptions={$rwAtr["atrProgrammerReserved"]};");
                }
            }
            $strRet = $intra->showCombo($inputName, $value, $arrOptions,
                array_merge($arrInpConfig, Array("strAttrib" => " old_val=\"".htmlspecialchars($value)."\"".$strDisabled, $rwAtr["atrDefault"]
                    , "strZeroOptnText"=>"-")));
            break;
       case "ajax_dropdown":
            if ($text != "" ) $arrInpConfig["strText"] = $text;
            $strRet = $intra->showAjaxDropdown($inputName, $value, 
                array_merge($arrInpConfig, 
                    Array("strTable" => $rwAtr["atrDataSource"]
                    , "strPrefix" => (preg_match("/^[a-z]{3}$/",$rwAtr["atrProgrammerReserved"]) ? $rwAtr["atrProgrammerReserved"] : ""))
                    ));
            break;
       case "boolean":
          $strRet = $intra->showCheckBox($inputName, $value,
            array_merge($arrInpConfig, Array("strAttrib" => " old_val=\"".htmlspecialchars($value)."\"")));
          break;
       case "textarea":
          $strRet = $intra->showTextArea($inputName, $value,
            array_merge($arrInpConfig, Array("strAttrib" => " old_val=\"".htmlspecialchars($value)."\"")));
          break;
       default:
          $strRet = $intra->showTextBox($inputName, $value, $arrInpConfig);
          break;
    }
    
    if ($rwAtr["atrUOMTypeID"]){
        $sqlUOM = "SELECT uomID as optValue, uomTitle{$strLocal} as optText FROM stbl_uom WHERE uomType='{$rwAtr["atrUOMTypeID"]}' ORDER BY uomOrder";
        $rsUOM = $oSQL->q($sqlUOM);
        while($rwUOM = $oSQL->f($rsUOM)) $arrOptions[$rwUOM["optValue"]]=$rwUOM["optText"];
        $strRet .= $intra->showCombo($rwAtr["atrID"]."_uomID", $this->conf[$rwAtr["atrID"]."_uomID"], $arrOptions
                , array_merge($arrInpConfig, Array("strAttrib" => " old_val=\"".$this->item[$rwAtr["atrID"]."_uomID"]."\""
                    , 'class'=>"eiseIntra_UOM")));
    }
    return $strRet;
    
}


function getFormForList($staID){
    
    $strActionControls = $this->showActionRadios();

    if($strActionControls == '')
        return;

?>
<form action="<?php  echo $this->conf["entScriptPrefix"] ; ?>_form.php" method="POST" id="entForm" class="eiseIntraForm eiseIntraMultiple" style="display:none;">
<input type="hidden" name="DataAction" id="DataAction" value="update">
<input type="hidden" name="entID" id="entID" value="<?php  echo $this->entID ; ?>">
<input type="hidden" name="<?php echo $this->entID; ?>ID" id="<?php echo "{$this->entID}"; ?>ID" value="">
<input type="hidden" name="aclOldStatusID" id="aclOldStatusID" value="">
<input type="hidden" name="aclNewStatusID" id="aclNewStatusID" value="">
<input type="hidden" name="actID" id="actID" value="">
<input type="hidden" name="aclToDo" id="aclToDo" value="">
<input type="hidden" name="aclComments" id="aclComments" value="">

<fieldset class="eiseIntraMainForm"><legend><?php echo $this->intra->translate("Set Data"); ?></legend>

<?php 
    echo $this->getFieldsHTML(Array("flagShowOnlyEditable"=>true));
 ?>
</fieldset>

<fieldset class="eiseIntraActions"><legend><?php echo $this->intra->translate("Action"); ?></legend>
<?php 
    if($strActionControls)
        echo "{$strActionControls}\r\n<div align=\"center\"><input class=\"eiseIntraSubmit\" id=\"btnsubmit\" type=\"submit\" value=\"".$this->intra->translate("Run")."\"></div>";
 ?>
</fieldset>

</form>
<script>
$(document).ready(function(){
    $('#entForm').
        eiseIntraForm().
        eiseIntraEntityItemForm({flagUpdateMultiple: true}).
        submit(function(event) {
            var $form = $(this);
            $form.eiseIntraEntityItemForm("checkAction", function(){
                if ($form.eiseIntraForm("validate")){
                    window.setTimeout(function(){$form.find('input[type="submit"], input[type="button"]').each(function(){this.disabled = true;})}, 1);
                    $form[0].submit();
                } else {
                    form.eiseIntraEntityItemForm("reset");
                }
            })
        
            return false;
        
        });
})

</script>
<?php
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

function showActionButtons(){
   
    $oSQL = $this->oSQL;
    $strLocal = $this->local;
    
    if (!$this->intra->arrUsrData["FlagWrite"])
        return;

    if(is_array($this->conf['STA'][$this->staID]['ACT']))
        foreach($this->conf['STA'][$this->staID]['ACT'] as $rwAct){
            
            if(count(array_intersect($this->intra->arrUsrData['roleIDs'], $rwAct['RLA']))==0)
                continue;

            $title = $rwAct["actTitle{$this->intra->local}"];
              
            $strID = "btn_".$rwAct["actID"]."_".
                  $rwAct["actOldStatusID"]."_".
                  $rwAct["actNewStatusID"];

            $strOut .= "<input type='".($rwAct['actID']==3 ? 'button' : 'submit')."' class=\"".($rwAct['actID']==3 ? ' eiseIntraDelete' : 'eiseIntraActionSubmit')."\" name='actButton' id='$strID' value='"
                    .htmlspecialchars($title)."'".
                    " act_id=\"{$rwAct["actID"]}\" orig=\"{$rwAct["actOldStatusID"]}\" dest=\"{$rwAct["actNewStatusID"]}\">";
              
        }

   
   return $strOut;
}


function newItemID($prefix, $datefmt="ym", $numlength=5){
    
    $oSQL = $this->oSQL;
    
    $sqlNumber = "INSERT INTO {$this->conf["entTable"]}_number (n{$this->entID}InsertDate) VALUES (NOW())";
    $oSQL->q($sqlNumber);
    $number = $oSQL->i();
    
    $oSQL->q("DELETE FROM {$this->conf["entTable"]}_number WHERE n{$this->entID}ID < {$number}");
    
    $strID = "{$prefix}".date($datefmt).substr(sprintf("%0{$numlength}d", $number), -1*$numlength);
    
    return $strID;
    
}

function newItem($entItemID){
    
    $oSQL = $this->oSQL;
    
    $sqlIns = "INSERT IGNORE INTO {$this->conf["entTable"]} (
        {$this->conf["entID"]}ID
        , {$this->conf["entID"]}StatusID
        , {$this->conf["entID"]}InsertBy, {$this->conf["entID"]}InsertDate, {$this->conf["entID"]}EditBy, {$this->conf["entID"]}EditDate
        ) VALUES (
        ".$oSQL->e($entItemID)."
        , NULL
        , '{$this->intra->usrID}', NOW(), '{$this->intra->usrID}', NOW());";
      
    $oSQL->do_query($sqlIns);
    
    
    $item = new eiseEntityItem($this->oSQL, $this->intra, $this->entID, $entItemID);

    $item->doCreate();
    
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
	if (!$oSQL_arch->d("SHOW TABLES LIKE '{$this->conf["entTable"]}'")){
        $sqlCreate = "CREATE TABLE `{$this->conf["entTable"]}` (
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
	$arrTable = $intra_arch->getTableInfo($intra->conf["stpArchiveDB"], $this->conf["entTable"]);
	
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
    
    $sqlArchTable = "ALTER TABLE {$this->conf["entTable"]}{$strFields}";
    $oSQL_arch->q($sqlArchTable);
	
	$this->oSQL_arch = $oSQL_arch;
	
}


function getActionPhaseTitle($phase){
    
    GLOBAL $intra;
    
    switch ($phase){
        case 0: 
            return $intra->translate("planned");
        case 1: 
            return $intra->translate("started");
        case 2: 
            return $intra->translate("finished");
        case 3: 
            return $intra->translate("cancelled");
            
    }
    
}

function getEntityTableALTER($atrID, $atrType, $action){

    $sqlRet = array();

    $strDBType = $this->oSQL->arrIntra2DBTypeMap[$atrType];
    if (!$strDBType)
        return false;

    switch ($action){
        case 'add':
            $sqlRet[] = "ALTER TABLE `{$this->conf['entTable']}` ADD COLUMN `{$atrID}` {$strDBType} NULL DEFAULT NULL";
            $sqlRet[] = "ALTER TABLE `{$this->conf['entTable']}_log` ADD COLUMN `l{$atrID}` {$strDBType} NULL DEFAULT NULL";
            break;
        case 'change':
            $sqlRet[] = "ALTER TABLE `{$this->conf['entTable']}` CHANGE COLUMN `{$atrID}` `{$atrID}` {$strDBType} NULL DEFAULT NULL";
            $sqlRet[] = "ALTER TABLE `{$this->conf['entTable']}_log` CHANGE COLUMN `l{$atrID}` `l{$atrID}` {$strDBType} NULL DEFAULT NULL";
            break;
        default:
            return false;
    }

    return $sqlRet;

}


function getDropDownText($arrATR, $value){

    $strRet = null;

    if ($arrATR["atrType"] == "combobox" && $arrATR["atrDataSource"]=='' && preg_match('/^Array\(/i', $arrATR["atrProgrammerReserved"])){
        eval( '$arrOptions = '.$arrATR["atrProgrammerReserved"].';' );
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

?>