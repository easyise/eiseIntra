<?php

class eiseEntity {

public $oSQL;
public $entID;
public $rwEnt = Array();
public $intra;

public $rwSta;
public $arrAtr;
public $arrAct;

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
protected function collectDataStatus($staID = null){
    
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
protected function collectDataAttributes(){
    
    $sqlAtr = "SELECT * 
        FROM stbl_attribute 
        ".($this->staID!=="" 
            ? "LEFT OUTER JOIN stbl_status_attribute ON satStatusID=".$this->oSQL->e($this->staID)." AND satAttributeID=atrID AND satEntityID=atrEntityID" 
            : "")."
        WHERE atrEntityID=".$this->oSQL->e($this->entID)." 
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

protected function getActionAttribute($actID){
    
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
    , aatFlagToTrack
    , aatFlagEmptyOnInsert
    , aatFlagTimestamp
    , atrType
    FROM stbl_action_attribute 
    INNER JOIN stbl_action ON aatActionID=actID
    INNER JOIN stbl_attribute ON atrEntityID=actEntityID AND aatAttributeID=atrID
    WHERE (aatFlagMandatory =1 OR aatFlagToChange=1 OR aatFlagToTrack=1 OR aatFlagEmptyOnInsert=1 OR aatFlagTimestamp=1) AND aatActionID='{$actID}'";
    $rsAAT = $oSQL->do_query($sqlAAT);
    while ($rwAAT = $oSQL->fetch_array($rsAAT)){
        $arrToRet[$rwAAT["aatAttributeID"]]=$rwAAT;
    }
    $oSQL->free_result($rsAAT);
    
    return $arrToRet;
}

protected function getActonTimestamps($rwACT){
    
    $arrTimestamps = Array(
        'ATA'=>'aclATA'
        , 'ATD'=>'aclATD'
        , 'ETA'=>'aclETA'
        , 'ETD'=>'aclETD'
        );
    
    // determine do we need to show estimates
    if (!$rwACT["actFlagHasEstimates"]) {unset($arrTimestamps["ETA"]);unset($arrTimestamps["ETD"]);}
    
    // determine do we need to show departure
    if ($rwACT["actFlagDepartureEqArrival"]) {unset($arrTimestamps["ATD"]);unset($arrTimestamps["ETD"]);}
        
    if (isset($rwACT["AAT"]))
        foreach($rwACT["AAT"] as $atrID=>$rwAAT){
            if (isset($arrTimestamps[$rwAAT["aatFlagTimestamp"]]))
                $arrTimestamps[$rwAAT["aatFlagTimestamp"]] = $rwAAT;
        }
    
    return $arrTimestamps;
}

protected function getStatusAttribute($staID){
    
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


protected function getLogID(){
    $this->oSQL->q("INSERT INTO stbl_log_id (lidInsertDate) VALUES (NOW())");
    $ret = $this->oSQL->i();
    $this->oSQL->q("DELETE FROM stbl_log_id");
    return $ret;
}

public function getList($arrAdditionalCols = Array(), $arrExcludeCols = Array()){
    
    $oSQL = $this->oSQL;
    $entID = $this->entID;
    
    $this->intra->arrUsrData = $this->intra->arrUsrData;
    $rwEnt = $this->rwEnt;
    $strLocal = $this->intra->local;
    
    $listName = $entID;
    
    $this->detectStatusID();
    $this->collectDataStatus();
    $this->collectDataAttributes();
    
    $staID = $this->staID;

    $lst = new eiseList($oSQL, $listName, Array('title'=>$this->rwEnt["entTitle{$strLocal}Mul"].($staID!=="" 
            ? ': '.$this->rwSta["staTitle{$strLocal}"] 
            : '')
        ,  "intra" => $this->intra
        , "cookieName" => $listName.$staID
        , "cookieExpire" => time()+60*60*24*30
            , 'defaultOrderBy'=>"{$this->entID}EditDate"
            , 'defaultSortOrder'=>"DESC"
            , 'sqlFrom' => "{$rwEnt["entTable"]} INNER JOIN stbl_status ON {$entID}StatusID=staID AND staEntityID='{$entID}'
                    LEFT OUTER JOIN stbl_action_log LAC
                    INNER JOIN stbl_action ON LAC.aclActionID=actID 
                    ON {$entID}ActionLogID=LAC.aclGUID
                    LEFT OUTER JOIN stbl_action_log SAC ON {$entID}StatusActionLogID=SAC.aclGUID"
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
            , 'filter' => "actTitle{$strLocal}"
            , 'order_field' => "actTitlePast{$strLocal}"
            , 'nowrap' => true
            );
            
    
    $strFrom = "";
    foreach($this->arrAtr as $rwAtr){
        
        if ($rwAtr["atrID"]==$entID."ID")
            continue;
        
        if (empty($this->staID) || $rwAtr["satFlagShowInList"]) {
           
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
            { 
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
    
    return $lst;
}

public function getFields($arrConfig = Array(), $oEntItem = null){
    
    $strFields = "";
    
    if ($oEntItem!==null){
        $this->staID = $oEntItem->staID;
    }
    
    if (empty($this->arrAtr)){
        $this->collectDataAttributes();
    }
    
    foreach($this->arrAtr as $rwAtr){
        if ($this->staID!=="" && !$rwAtr["satFlagShowInForm"])
            continue;
        
        if (!$this->flagArchive) {
            if (!$this->intra->arrUsrData["FlagWrite"]) $rwAtr['satFlagEditable'] = false;
            
            if ($arrConfig['flagShowOnlyEditable'] && !$rwAtr['satFlagEditable'])
                continue;
                
        } else {
            $rwAtr['satFlagEditable'] = false;
        }
        
        $strFields .= ($strFields!="" ? "\r\n" : "");
        $strFields .= "<div class=\"eiseIntraField\">";
        $strFields .= "<label id=\"title_{$rwAtr["atrID"]}\">".$rwAtr["atrTitle{$this->intra->local}"].":</label>";
        
        if ($oEntItem !== null){
            $rwAtr["value"] = $oEntItem->rwEnt[$rwAtr["atrID"]];
            $rwAtr["text"] = $oEntItem->rwEnt[$rwAtr["atrID"]."_Text"];
        }
        
        $strFields .=  $this->showAttributeValue($rwAtr, "");
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
    $arrInpConfig = Array("FlagWrite"=>$this->intra->arrUsrData["FlagWrite"]
        /*, "required" => (bool)($rwAtr["aatFlagMandatory"] && $rwAtr["satFlagEditable"])*/
        );
    if(!$rwAtr['satFlagEditable'])
        $arrInpConfig["FlagWrite"] = false;
    
    switch ($rwAtr['atrType']){
       case "datetime":
         $dtVal = $intra->datetimeSQL2PHP($value);
       case "date":
         $dtVal = $dtVal ? $dtVal : $intra->dateSQL2PHP($value);
         $strRet = $intra->showTextBox($inputName, $dtVal, 
            array_merge($arrInpConfig, Array("strAttrib" => " old_val=\"".htmlspecialchars($dtVal)."\""
                , "class"=>array_merge(Array("{$rwAtr['atrClasses']}", $arrInpConfig["class"] ))
                , "type"=>($rwAtr['atrType'] ? $rwAtr["atrType"] : "text")
                ))); 
         break;
       case "combobox":
            if (!$arrInpConfig["FlagWrite"]){ // if read-only && text is set
                $arrOptions[$value]=$text;
            } else {
                $src = $rwAtr["atrProgrammerReserved"];
                if (preg_match("/^(vw|tbl)_/", $rwAtr["atrDataSource"])){
                    $rsCMB = $intra->getDataFromCommonViews(null, null, $rwAtr["atrDataSource"]
                        , (strlen($rwAtr["atrProgrammerReserved"])<=3 ? $rwAtr["atrProgrammerReserved"] : ""));
                    $arrOptions = Array();
                    while($rwCMB = $oSQL->fetch_array($rsCMB))
                        $arrOptions[$rwCMB["optValue"]]=$rwCMB["optText"];
                }
                if (preg_match("/^Array/i", $src)){
                    eval ("\$arrOptions=$src;");
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
        $strRet .= $intra->showCombo($rwAtr["atrID"]."_uomID", $this->rwEnt[$rwAtr["atrID"]."_uomID"], $arrOptions
                , array_merge($arrInpConfig, Array("strAttrib" => " class=\"intra_uom\" old_val=\"".$this->rwEnt[$rwAtr["atrID"]."_uomID"]."\"")));
    }
    return $strRet;
    
}


function getFormForList($staID){

?>
<form action="<?php  echo $this->rwEnt["entScriptPrefix"] ; ?>_form.php" method="POST" id="entForm" class="eiseIntraForm eiseIntraMultiple" style="display:none;">
<input type="hidden" name="DataAction" id="DataAction" value="update">
<input type="hidden" name="entID" id="entID" value="<?php  echo $this->entID ; ?>">
<input type="hidden" name="<?php echo $this->entID; ?>ID" id="<?php echo "{$this->entID}"; ?>ID" value="">
<input type="hidden" name="aclOldStatusID" id="aclOldStatusID" value="">
<input type="hidden" name="aclNewStatusID" id="aclNewStatusID" value="">
<input type="hidden" name="actID" id="actID" value="">
<input type="hidden" name="aclToDo" id="aclToDo" value="">
<input type="hidden" name="actComments" id="actComments" value="">

<fieldset class="eiseIntraMainForm"><legend><?php echo $this->intra->translate("Set Data"); ?></legend>

<?php 
    echo $this->getFields(Array("flagShowOnlyEditable"=>true));
 ?>
</fieldset>

<fieldset class="eiseIntraActions"><legend><?php echo $this->intra->translate("Action"); ?></legend>
<?php 
    echo $this->showActionRadios();
    echo "<div align=\"center\"><input class=\"eiseIntraSubmit\" id=\"btnsubmit\" type=\"submit\" value=\"".$this->intra->translate("Run")."\"></div>";
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
    
    if(empty($this->arrAct))
        $this->collectDataActions();

            
    foreach($this->arrAct as $rwAct){
      
        $arrRepeat = Array(($rwAct["actFlagAutocomplete"] ? "1" : "0") => (!$rwAct["actFlagAutocomplete"] ? $this->intra->translate("Plan") : ""));
      
        foreach($arrRepeat as $key => $value){
            $title = ($rwAct["actID"] == 2 
               ? " - ".$this->intra->translate("update")." - "
               : $rwAct["actTitle{$this->intra->local}"].
                  ($rwAct["atsOldStatusID"]!=$rwAct["atsNewStatusID"]
                  ?  " (".$rwAct["staTitle{$this->intra->local}_Old"]." > ".$rwAct["staTitle{$this->intra->local}_New"].")"
                  :  "")
            );
          
            $strID = "rad_".$rwAct["actID"]."_".
              $rwAct["atsOldStatusID"]."_".
              $rwAct["atsNewStatusID"];

            $strOut .= "<input type='radio' name='actRadio' id='$strID' value='".$rwAct["actID"]."' class='eiseIntraRadio'".
                " orig=\"{$rwAct["atsOldStatusID"]}\" dest=\"{$rwAct["atsNewStatusID"]}\"".
                ($rwAct["actID"] == 2 || ($key=="1" && count($arrRepeat)>1) ? " checked": "")
                 .(!$rwAct["actFlagAutocomplete"] ? " autocomplete=\"false\"" : "")." /><label for='$strID' class='eiseIntraRadio'>".($value!="" ? "$value \"" : "")
                 .$title
                 .($value!="" ? "\"" : "")."</label><br />\r\n";
              
          
          
      }
   }
   
   return $strOut;
}


function newItemID($prefix, $datefmt="ym", $numlength=5){
    
    $oSQL = $this->oSQL;
    
    $sqlNumber = "INSERT INTO {$this->rwEnt["entTable"]}_number (n{$this->entID}InsertDate) VALUES (NOW())";
    $oSQL->q($sqlNumber);
    $number = $oSQL->i();
    
    $oSQL->q("DELETE FROM {$this->rwEnt["entTable"]}_number");
    
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

function upgrade_eiseIntra(){

echo "Updating entity {$this->rwEnt["entTitle"]}...\r\n";ob_flush();

// update tbl_ENT_number



/*
echo "Add extra columns to {$this->rwEnt["entTable"]}_log...";ob_flush();

$sqlAddCols = "ALTER TABLE `{$this->rwEnt["entTable"]}_log`
	ADD COLUMN `l{$this->entID}ID` BIGINT UNSIGNED NULL DEFAULT NULL FIRST,
	ADD COLUMN `l{$this->entID}ItemID` VARCHAR(50) NULL DEFAULT NULL AFTER `l{$this->entID}ID`";
$this->oSQL->q($sqlAddCols);
echo " done.\r\n";ob_flush();

echo "Update action and status log with sequential numbers...";ob_flush();
$sqlItems = "SELECT {$this->entID}ID FROM {$this->rwEnt["entTable"]} ORDER BY {$this->entID}InsertDate";
$rsItem = $this->oSQL->q($sqlItems);
echo "found ".$this->oSQL->n($rsItem)." items for ".$this->rwEnt["entTitle{$intra->local}"].":\r\n";
while ($rwItem = $this->oSQL->f($rsItem)){
    echo "Updating ".$rwItem["{$this->entID}ID"]."...";ob_flush();
    $timeStart = microtime(true);
    $this->oSQL->q("START TRANSACTION");
    
    $sqlItems = "SELECT aclGUID as guid, 'stbl_action_log' as tbl, 'acl' as prfx, aclInsertDate as dt 
            FROM stbl_action_log 
            WHERE aclEntityItemID='".$rwItem["{$this->entID}ID"]."'
        UNION
        SELECT stlGUID as guid, 'stbl_status_log' as tbl, 'stl' as prfx , stlInsertDate as dt 
            FROM stbl_status_log
            WHERE stlEntityItemID='".$rwItem["{$this->entID}ID"]."'
        ORDER BY dt, prfx";
    $rs = $this->oSQL->q($sqlItems);
    while($rw = $this->oSQL->f($rs)){
        $logID = $this->getLogID();
        $this->oSQL->q("UPDATE {$rw["tbl"]} SET {$rw["prfx"]}ID=".$logID." WHERE {$rw["prfx"]}GUID='{$rw["guid"]}'");
        $this->oSQL->q("UPDATE {$this->rwEnt["entTable"]}_log SET l{$this->entID}ID=".$logID."
            , l{$this->entID}ItemID='".$rwItem["{$this->entID}ID"]."'
            WHERE l{$this->entID}GUID='{$rw["guid"]}'");
    }    
    unset($item);
    $this->oSQL->q("COMMIT");
    
    //$this->oSQL->showProfileInfo();
    
    $timePeriod = number_format(microtime(true)-$timeStart, 6, ".", "");
    echo "Done in {$timePeriod}s\r\n";
    ob_flush();
}
echo "Finished updating log entries\r\n";ob_flush();

echo "Deleting log entries not linked to master...";ob_flush();
$sqlDelNulls = "DELETE FROM {$this->rwEnt["entTable"]}_log WHERE l{$this->entID}ID IS NULL";
$this->oSQL->q($sqlDelNulls);
echo "done\r\n";ob_flush();


echo "Add indexes to log table...";ob_flush();
$this->oSQL->q("ALTER TABLE `tbl_air_shipment_log`
	ALTER `lashID` DROP DEFAULT");
$this->oSQL->q("ALTER TABLE `tbl_air_shipment_log`
	CHANGE COLUMN `lashID` `lashID` BIGINT(20) UNSIGNED NOT NULL FIRST");
    
$this->oSQL->q("ALTER TABLE `{$this->rwEnt["entTable"]}_log`
    DROP PRIMARY KEY,
	ADD PRIMARY KEY (`l{$this->entID}ID`),
	ADD INDEX `IX_l{$this->entID}ItemID` (`l{$this->entID}ItemID`)");
*/

echo "done\r\n";ob_flush();

}


}

?>