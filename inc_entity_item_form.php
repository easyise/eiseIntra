<?php
/* visualization functions */
include_once "inc_entity_item.php";

class eiseEntityItemForm extends eiseEntityItem {

function __construct($oSQL, $intra, $entID, $entItemID, $flagArchive = false){
    
    parent::__construct($oSQL, $intra, $entID, $entItemID, $flagArchive);
    
    $this->getEntityItemAllData();
    
}

function form($arrConfig=Array()){

$entItemID = $rwEnt[$entID."ID"];

$oSQL = $this->oSQL;
$entID = $this->entID;
$rwEnt = $this->rwEnt;

if (!$this->flagArchive){
?>
<form action="<?php  echo $_SERVER["PHP_SELF"] ; ?>" method="POST" id="entForm">
<input type="hidden" name="DataAction" id="DataAction" value="update">
<input type="hidden" name="entID" id="entID" value="<?php  echo $entID ; ?>">
<input type="hidden" name="<?php echo "{$entID}"; ?>ID" id="<?php echo "{$entID}"; ?>ID" value="<?php  echo $rwEnt["{$entID}ID"] ; ?>">
<input type="hidden" name="aclOldStatusID" id="aclOldStatusID" value="<?php  echo $rwEnt["{$entID}StatusID"] ; ?>">
<input type="hidden" name="aclNewStatusID" id="aclNewStatusID" value="">
<input type="hidden" name="actID" id="actID" value="">
<input type="hidden" name="aclGUID" id="aclGUID" value="">
<input type="hidden" name="aclToDo" id="aclToDo" value="">
<input type="hidden" name="actComments" id="actComments" value="">
<?php 
}
 ?>


<div class="panel">
<table width="100%">

<tr>
<td width="50%">

<h1><?php  echo $rwEnt["entTitle{$this->intra->local}"]." ".$rwEnt["{$entID}ID"] ; ?>
<?php  echo ($this->flagArchive ? " (".$this->intra->translate("Archive").")" : "") ; ?></h1>

<?php 
$this->showEntityItemFields( Array('showComments'=>true));

$this->showFiles();
?>
</td>
<td width="50%">
<?php 
echo $this->showActions($arrConfig["actionCallBack"]);
 ?>


<?php 
$this->showActivityLog($arrConfig["actionCallBack"])
 ?>


 
</td>
</tr>
<?php 
if ($arrConfig["extraFieldset"]!=""){
?>
<tr><td colspan="2"><?php eval($arrConfig["extraFieldset"]); ?></td></tr>
<?php
}
 ?>
</table>
</div>

</form>

<script>
$(document).ready(function(){ 
    intraInitializeForm();
    intraInitializeEntityForm();
    intializeComments('<?php  echo $entID ; ?>');
});
</script>
<?php
    //echo "<pre>";
    //print_r($this->rwEnt);

}



function showActions($actionCallBack=""){
    $oSQL = $this->oSQL;
    $rwEnt = $this->rwEnt;
    ?>
    <fieldset><legend><?php  echo $this->intra->translate("Actions") ; ?></legend>
    <?php 
    
    $entID = $rwEnt["entID"];
    $entItemID = $rwEnt[$rwEnt["entID"]."ID"];
    
    $staID = $rwEnt[$entID."StatusID"];
    
    if ($entItemID) {
        $ii = 0;
        foreach ($this->rwEnt["ACL"] as $ix=>$rwACL){
            
            if ($rwACL["aclActionPhase"] > 2) continue; //skip cancelled
            
            $this->showActionInfo($rwACL, $actionCallBack);
            $staID = ($ii==0 ? $rwACL["aclNewStatusID"] : $staID);
            $ii++;
                
        }
    }
    
    if (!$flagDontShowOtherActions && !$this->flagArchive){
        echo $this->showActionRadios($rwEnt["entID"], $staID, Array(
            "staFlagCanUpdate"=>$this->intra->arrUsrData["FlagWrite"] && $rwEnt["staFlagCanUpdate"]  && !$this->flagArchive
            , "staFlagCanDelete"=>$rwEnt["staFlagCanDelete"]
            )
        );
    }
    if ($this->intra->arrUsrData["FlagWrite"] && !$this->flagArchive){
            
        echo "<div align=\"center\"><input id=\"btnsubmit\" type=\"submit\" value=\"".$this->intra->translate("Run")."\"></div>";
        
    }
    ?>
    </fieldset>
    <?php
}


function showEntityItemFields($arrConfig){
    
    $strLocal = $this->intra->local;
    
    $oSQL = $this->oSQL;
    $rwEnt = $this->rwEnt;
    
    $entID = $this->entID;
    $entItemID = $rwEnt[$entID."ID"];
    
    if(empty($this->rwEnt["ATR"])){
        throw new Exception("Attribute set not found");
    }
    
    echo "<fieldset class=\"intra\"><legend>".$this->intra->translate("Data")."</legend>\r\n";
    echo "<div class=\"intraForm\">\r\n";

    
    foreach($this->rwEnt["ATR"] as $field => $rwAtr){
        
        if (!$this->flagArchive) {
            if (!$this->intra->arrUsrData["FlagWrite"]) $rwAtr['satFlagEditable'] = false;
            
            if ($arrConfig['flagShowOnlyEditable'] && !$rwAtr['satFlagEditable'])
                continue;
        } else {
            $rwAtr['satFlagEditable'] = false;
        }
        
        echo "<div class=\"intraField tr".( $i++ % 2 )."\">\r\n";
        echo "<label id=\"title_{$rwAtr["atrID"]}\">{$rwAtr["atrTitle$strLocal"]}:</label>";
        
        $rwAtr["value"] = $rwEnt[$rwAtr["atrID"]];
        $rwAtr["text"] = $rwEnt[$rwAtr["atrID"]."_Text"];
        echo $this->showAttributeValue($rwAtr, "");

        echo "</div>\r\n\r\n";
            
    }
    
    if ($rwEnt[$entID."ID"]){
        if ($arrConfig["showComments"]){
            // Comments
            echo "<div class=\"intraField tr".( $i % 2 )."\">\r\n";
            echo "<label>".$this->intra->translate("Comments").":</label>";
            echo "<div class=\"intraFieldValue\">";
            
            $this->showCommentsField();
            
            echo "&nbsp;</div>";
            echo "</div>\r\n\r\n";
        }
        
        if ($arrConfig["showFiles"]){
            // Files
            $i++;
            echo "<div class=\"intraFormRow tr".( $i % 2 )."\">\r\n";
            echo "<div class=\"intraFieldTitle\">Files:</div>";
            echo "<div class=\"intraFieldValue\">";
            $sqlFiles = "SELECT * FROM stbl_file WHERE filEntityItemID = '{$rwEnt["shpID"]}'";
            $rsFiles = $oSQL->do_query($sqlFiles);
            while ($rwFiles = $oSQL->fetch_array($rsFiles)){
                echo
                    "<a href='popup_file.php?filGUID="
                    . $rwFiles["filGUID"] . "' target=_blank>"
                    . $rwFiles["filName"] . "</a><br>";
            }
             
            echo "</div></div>\r\n\r\n";
        }
    }

    echo "</div>\r\n</fieldset>\r\n\r\n";
    
}


function showActivityLog(){
    $strLocal = $this->intra->local;
    $oSQL = $this->oSQL;
    $entID = $this->entID;
    $entItemID = $this->entItemID;
    
    $intra = $this->intra;
?>
<fieldset><legend><?php  echo $this->intra->translate("Activity Log") ; ?></legend>
<?php 
// показываем статусы вместе с stlArrivalAction
// потом внутри статуса показываем действия, выполненные во время стояния в статусе


foreach($this->rwEnt["STL"] as $stlGUID => $rwSTL){
    ?>
    <div class="intraLogStatus">
    <div class="intraLogTitle"><?php echo ($rwSTL["stlTitle".$this->intra->local]!="" 
        ? "{$rwSTL["stlTitle".$this->intra->local]}"
        : "{$rwSTL["staTitle".$this->intra->local]}"); ?></div>
    <div class="intraLogData">
    
    <div class="intraField">
    <label>ATD:</label><span><?php echo $intra->DateSQL2PHP($rwSTL["stlATD"]); ?>&nbsp;</span>
    </div>
    <?php
    if (isset($rwSTL["ACL"]))
    foreach ($rwSTL["ACL"] as $rwNAct){
       $this->showActionInfo($rwNAct);
    }
    
    // linked attributes
    if (isset($rwSTL["SAT"]))
    foreach($rwSTL["SAT"] as $atrID => $rwATV){
        $rwATV["satFlagEditable"] = false;
        ?>
        <div class="intraField"><label><?php  echo $rwATV["atrTitle{$this->intra->local}"] ; ?>:</label>
        <?php 
           echo $this->showAttributeValue($rwATV, "_".$rwSTL["stlGUID"]); ?>&nbsp;
        </div>
        <?php
    }
    
    ?>
    
    <div class="intraField">
    <label>ATA:</label><span><?php echo $intra->dateSQL2PHP($rwSTL["stlATA"]); ?></span>
    </div>
    
    </div>
    </div>
    
    <?php
    $this->showActionInfo($rwSTL["stlArrivalAction"]);
}
 ?>
</fieldset>
&nbsp;
<?php  
$nCancelled = 0;
foreach ($this->rwEnt["ACL"] as $rwACL) {
    if ($rwACL["aclActionPhase"]==3) $nCancelled++;
}


if ($nCancelled > 0 ) {
?>
<fieldset><legend><?php  echo $this->intra->translate("Cancelled actions") ; ?></legend>
<?php 
$ii = 0;
foreach ($this->rwEnt["ACL"] as $rwACL) {
   if ($rwACL["aclActionPhase"]!=3) continue;
    $this->showActionInfo($rwACL, $actionCallBack);
    $staID = ($ii==0 ? $rwACL["aclNewStatusID"] : $staID);
    $ii++;
}
?>
</fieldset>
<?php
}


}



function showActionInfo($rwACT, $actionCallBack=""){
    
    $entID = $this->entID;
    $strLocal = $this->intra->local;
    
    $flagAlwaysShow = ($rwACT["aclActionPhase"]<2 ? true : false);
    $flagEditable = ($rwACT["aclActionPhase"]<2 && $this->intra->arrUsrData["FlagWrite"]==true && !$this->flagArchive);
    ?>
    <div class="intraLogAction">
    <div class="intraLogTitle" id="aclTitle_<?php  echo $rwACT["aclGUID"] ; ?>" title="Last edited: <?php  echo htmlspecialchars($rwACT["aclEditBy"]."@".Date("d.m.Y H:i", strtotime($rwACT["aclEditDate"]))).
            "\r\n / ".$rwACT["aclEntityItemID"]."|".$rwACT["aclGUID"]; ?>"><?php 
    echo $rwACT["actTitlePast{$strLocal}"].
       ($rwACT["staID_Old"]!=$rwACT["staID_New"] 
         ? " (".$rwACT["staTitle{$strLocal}_Old"]." &gt; ".$rwACT["staTitle{$strLocal}_New"].")"
         : ""
    );
    echo ($this->flagArchive 
        ? ($rwACT["aclActionPhase"]==3
            ? " (".$this->intra->translate("cancelled").")"
            : ($rwACT["aclActionPhase"]<2 
                ? " (".$this->intra->translate("incomplete").")"
                : "")
            )
        : ""
    );?></div>
    <div class="intraLogData">
    
    <div class="intraField">
    <label>ATA<?php echo ($flagEditable ? "*" : ""); ?>:</label><?php 
        echo $this->showTimestampField("aclATA", $flagEditable, $rwACT["aclATA"], "_".$rwACT["aclGUID"]); ?>&nbsp;
    </div>
    <?php 
    if ($flagAlwaysShow || !($rwACT["aclATD"]=="" || $rwACT["aclATD"]==$rwACT["aclATA"])){
    ?>
    <div class="intraField"><label>ATD<?php echo ($flagEditable ? "*" : ""); ?>:</label><?php 
        echo $this->showTimestampField("aclATD", $flagEditable, $rwACT["aclATD"], "_".$rwACT["aclGUID"]);
         ?>
    </div>
    <?php
    }
    
    //ETA && ETD
    
    if ($rwACT["actFlagHasEstimates"] && $rwACT["actID"] > 3){
    ?>
    <div class="intraField intraEstimates"><label>ETA<?php echo ($flagEditable ? "*" : ""); ?>:</label><?php 
       echo $this->showTimestampField("aclETA", $flagEditable, $rwACT["aclETA"], "_".$rwACT["aclGUID"]); ?>
    </div>
    <div class="intraField intraEstimates"><label>ETD<?php echo ($flagEditable ? "*" : ""); ?>:</label><?php 
       echo $this->showTimestampField("aclETD", $flagEditable, $rwACT["aclETD"], "_".$rwACT["aclGUID"]); ?>
    </div>
    <?php
    }
    ///*
    // linked attributes
    if (isset($rwACT["AAT"]))
    foreach($rwACT["AAT"] as $ix => $rwATV){
        $rwATV["satFlagEditable"] = $flagEditable;
        ?>
        <div class="intraField"><label><?php  
            echo $rwATV["atrTitle{$this->intra->local}"].($rwATV["aatFlagMandatory"] && $rwATV["satFlagEditable"] ? "*" : "") ; 
            ?>:</label><?php 
            echo $this->showAttributeValue($rwATV, "_".$rwACT["aclGUID"]); ?>
        </div>
        <?php
    }
    //*/
    
    
    eval($actionCallBack.";");
    
    ?>
    
    </div>
    <?php 
    if ($rwACT["aclActionPhase"]<2 && !$this->flagArchive){
        ?><div align="center"><?php
        
        if ($rwACT["aclActionPhase"]=="0"){
            ?><input name="start_<?php  echo $aclGUID ; ?>" id="start_<?php  echo $aclGUID ; ?>" 
            type="button" value="Start" class="intraActionButton">
            <?php
        }
        if ($rwACT["aclActionPhase"]=="1"){
            ?><input name="finish_<?php  echo $aclGUID ; ?>" id="finish_<?php  echo $aclGUID ; ?>" 
            type="button" value="Finish" class="intraActionButton">
            <?php
        }
        ?><input name="cancel_<?php  echo $aclGUID ; ?>" id="cancel_<?php  echo $aclGUID ; ?>" 
        type="button" value="Cancel" class="intraActionButton"></div>
        <?php
    }
    ?>
    </div>
    <?php
    
    
}



function showTimestampField($atrName, $flagEditable, $value, $suffix){
   return $this->showAttributeValue(Array("atrID"=>$atrName
            , "atrType"=>"datetime"
            , "satFlagEditable"=>$flagEditable
            , "value" => $value)
          , $suffix);
}


function showAttributeValue($rwAtr, $suffix = ""){
    
    $strRet = "";
    
    $value = $rwAtr["value"];
    $text = $rwAtr["text"];
    
    $oSQL = $this->oSQL;
    $intra = $this->intra;
    
    $inputName = $rwAtr["atrID"].$suffix;
    $arrInpConfig = Array();
    if(!$rwAtr['satFlagEditable'])
        $arrInpConfig["FlagWrite"] = false;
    
    switch ($rwAtr['atrType']){
       case "datetime":
         $dtVal = $intra->datetimeSQL2PHP($value);
       case "date":
         $dtVal = $dtVal ? $dtVal : $intra->dateSQL2PHP($value);
         $strRet = $intra->showTextBox($inputName, $dtVal, 
            array_merge($arrInpConfig, Array("strAttrib" => " old_val=\"".htmlspecialchars($dtVal)."\" class=\"intra_{$rwAtr['atrType']}\""))); 
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
                array_merge($arrInpConfig, Array("strAttrib" => " old_val=\"".htmlspecialchars($value)."\"".$strDisabled, $rwAtr["atrDefault"])));
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
    
    echo $strRet;
    
}



function showActionRadios($strEntity, $statusID, $arrConfig){
   
   GLOBAL $flagActNoDefaults;
   
   $oSQL = $this->oSQL;
   $strLocal = $this->local;

   $strRoleList = implode("', '", $this->intra->arrUsrData['roleIDs']);
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
             , STA_OLD.staTitle as staTitle_Old
             , STA_NEW.staTitle AS staTitle_New
             , STA_OLD.staTitleLocal as staTitleLocal_Old
             , STA_NEW.staTitleLocal as staTitleLocal_New
          FROM stbl_action_status
          INNER JOIN stbl_action ON atsActionID=actID
           LEFT OUTER JOIN stbl_status STA_OLD ON atsOldStatusID=STA_OLD.staID AND actEntityID=STA_OLD.staEntityID
           LEFT OUTER JOIN stbl_status STA_NEW ON atsNewStatusID=STA_NEW.staID AND actEntityID=STA_NEW.staEntityID
          LEFT JOIN stbl_role_action 
            INNER JOIN stbl_role ON rlaRoleID=rolID
            ON actID=rlaActionID
          LEFT OUTER JOIN stbl_entity ON entID='".$strEntity."'
          WHERE (actEntityID='".$strEntity."'".($flagActNoDefaults ? "" : " OR actEntityID IS NULL").") 
             AND (atsOldStatusID='".$statusID."' OR atsOldStatusID IS NULL) 
             AND ".($arrConfig["aclIncompleteActionID"]=="" 
                    ? "actID NOT IN (1".
                        (!$arrConfig["staFlagCanUpdate"] ? ", 2" : "").
                        (!$arrConfig["staFlagCanDelete"] ? ", 3" : "").")"
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
          LEFT OUTER JOIN stbl_entity ON entID='".$strEntity."'
          WHERE actID=1";
    }
    //echo "<pre>".$sqlAct."</pre>";
    $rsAct = $oSQL->do_query($sqlAct);
    while ($rwAct = $oSQL->fetch_array($rsAct)){
      
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

            $strOut .= "<input type='radio' name='actRadio' id='$strID' value='".$rwAct["actID"]."' style='width:auto;' onclick='actionChecked(this)'".
                ($rwAct["actID"] == 2 || ($key=="1" && count($arrRepeat)>1) ? " checked": "").
                ($rwAct["actID"] != 2 && $arrConfig["flagFullEdit"] ? " disabled" : "")
                 .(!$rwAct["actFlagAutocomplete"] ? " autocomplete=\"false\"" : "")." /><label for='$strID'>".($value!="" ? "$value \"" : "")
                 .$title
                 .($value!="" ? "\"" : "")."</label><br />\r\n";
              
          
          
      }
   }
   
   return $strOut;
}

function showActivityLog_simple($oSQL, $strGUID, $arrConfig=Array()) {

if (!$strGUID) 
    return;

$sql = "SELECT *
  FROM stbl_action_log
  INNER JOIN stbl_action ON actID= aclActionID
  LEFT OUTER JOIN stbl_user ON usrID=aclInsertBy
  WHERE aclEntityItemID='".$strGUID."'".
  ($arrConfig['flagNoUpdate'] ? " AND aclActionID<>2" : "")
  ."
  ORDER BY aclInsertDate DESC, actNewStatusID DESC";
$rs = $oSQL->do_query($sql);
while ($rw = $oSQL->fetch_array($rs)) 
    $arrStatus[] = $rw;
    
$oSQL->free_result($rs);

$strRes = "<fieldset><legend>Status".($arrConfig['staTitle'] ? ": ".$arrConfig['staTitle'] : "")."</legend>";
$strRes .= "<div style=\"height:100px; overflow-y: auto;\">\r\n";
$strRes .= "<table width='100%' class='intraHistoryTable'>\r\n";

for ($i=0;$i<count($arrStatus);$i++){
    $strRes .= "<tr class='tr".($i % 2)."' valign='top'>\r\n";
    if (!$arrStatus[$i]["aclFlagIncomplete"]) {
        $strRes .= "<td nowrap><b>".$arrStatus[$i]["actTitlePast$strLoc"]."</b></td>\r\n";
    } else {
        $strRes .= "<td nowrap>Started &quot;<b>".$arrStatus[$i]["actTitle$strLoc"]."</b>&quot;</td>\r\n";
    }
    $strRes .= "<td nowrap>by ".($arrStatus[$i]["usrName"] ? $arrStatus[$i]["usrName"] : $arrStatus[$i]["aclInsertBy"])."</td>";
    $strRes .= "<td nowrap>at ".date("d.m.Y H:i", strtotime($arrStatus[$i]["aclInsertDate"]))."</td>";
    $strRes .= "</tr>";
    
    if ($arrStatus[$i]["aclComments"]) {
        $strRes .= "<tr class='tr".($i % 2)."'>";
        $strRes .= "<td nowrap style=\"text-align:right;\"><b>Comments:</b></td>\r\n";
        $strRes .= "<td colspan='2' nowrap><i>".$arrStatus[$i]["aclComments"]."</i><br />&nbsp;</td>";
        $strRes .= "</tr>";
    }
    
}
    $strRes .= "</table>\r\n";
    $strRes .= "</div>\r\n";
    $strRes .= "</fieldset>\r\n\r\n";

return $strRes;
    
}


/***********************************************************************************/
/* Comments Routines                                                               */
/***********************************************************************************/
function showCommentsField(){
    $oSQL = $this->oSQL;
    $rwEntity = $this->rwEnt;
    $intra = $this->intra;
    $usrID = $intra->usrID;
?>
<div id="intra_comment" class="intra_comment" contentEditable="true"></div>
<?php 
foreach ($this->rwEnt["comments"] as $ix => $rwSCM){
?>
<div id="scm_<?php  echo $rwSCM["scmGUID"] ; ?>" class="intra_comment"<?php 
echo ($usrID==$rwSCM["scmInsertBy"] ? " onclick=\"showCommentDelete(this)\"" : "")
 ?>>
<div class="intra_comment_userstamp"><?php  echo ($intra->getUserData($rwSCM["scmInsertBy"]) ); ?> at <?php 
echo $intra->dateSQL2PHP($rwSCM["scmInsertDate"], "d.m.Y H:i");
 ?></div>
<div><?php  echo str_replace("\n", "<br>", $rwSCM["scmContent"] ); ?></div>
</div>
<?php
}
 ?>
<span id="intra_comment_contols">
<input type="button" class="ss_sprite ss_add" id="intra_comment_add">
</span>
<input type="button" class="ss_sprite ss_delete" id="intra_comment_delete">
<?php
}


/***********************************************************************************/
/* File Attachment Routines                                                        */
/***********************************************************************************/
function showFileAttachDiv(){
    $entID = $this->entID;
    $entItemID = $this->entItemID;
?>
<div id="divAttach" style="position:absolute;visibility:hidden;">
<form action="<?php echo $_SERVER["PHP_SELF"]; ?>" method="POST" enctype="multipart/form-data" onsubmit="
   if (document.getElementById('attachment').value==''){
      alert ('File is not specified.');
      document.getElementById('attachment').focus();
      return false;
   }
   var btnUpl = document.getElementById('btnUpload');
   btnUpl.value = 'Loading...';
   btnUpl.disabled = true;
   return true;

">
<input type="hidden" name="DataAction" id="DataAction" value="attachFile" />
<input type="hidden" name="entID_Attach" id="entItemID_Attach" value="<?php  echo $entID ; ?>" />
<input type="hidden" name="entItemID_Attach" id="entItemID_Attach" value="<?php  echo $entItemID ; ?>" />
<span class="field_title_top">Choose file</span>:<br />
<input type="file" id="attachment" name="attachment" ><br />
<input type="submit" value="Upload" id="btnUpload" style="width: 200px; font-weight:bold;" /><input type="button" value="Cancel" onclick="document.getElementById('mwClose').click();" style="width:100px;" />
</form>
</div>

<?php
}

function showFiles(){

$oSQL = $this->oSQL;
$entID = $this->entID;
$entItemID = $this->entItemID;
$intra = $this->intra;

$sqlFile = "SELECT * FROM stbl_file WHERE filEntityID='$entID' AND filEntityItemID='{$entItemID}'
ORDER BY filInsertDate DESC";
$rsFile = $oSQL->do_query($sqlFile);

if ($oSQL->num_rows($rsFile) > 0) {
?>
<fieldset><legend><?php  echo $this->intra->translate("Files") ; ?></legend>
<table width="100%" class="intraHistoryTable">
<thead>
<tr>
<th>File</th>
<th colspan="2">Uploaded</th>
<th>&nbsp;</th>
</th>
</thead>
<tbody>
<?php 

$i =0;
while($rwFile = $oSQL->fetch_array($rsFile)){
    ?>
<tr class="tr<?php  echo $i%2 ; ?>">
<td width="100%"><a href="popup_file.php?filGUID=<?php  echo $rwFile["filGUID"] ; ?>" target="_blank"><?php  echo $rwFile["filName"] ; ?></a></td>
<td><?php  echo $intra->getUserData($rwFile["filEditBy"]) ; ?></td>
<td><?php  echo $intra->datetimeSQL2PHP($rwFile["filEditDate"]) ; ?></td>
<td class="unattach" id="fil_<?php  echo $rwFile["filGUID"] ; ?>" title="Delete">&nbsp;X&nbsp;</td>
</tr>    
    <?php
    $i++;
}
 ?>
 </tbody>
</table>
</fieldset>
<?php

}

}

}

/*
class eiseEntity {

function showFormForList($oSQL, $entID, $staID){
	
	$rwEntity = $oSQL->fetch_array($oSQL->do_query("SELECT * FROM stbl_entity WHERE entID='$entID'"));
	$rwEntity["entScriptPrefix"] = str_replace("tbl_", "", $rwEntity["entTable"]);
?>
<div id="div_form" class="panel">

<h2>Assign attributes to selected:</h2>
<form id="form_actions" action="<?php  echo $rwEntity["entScriptPrefix"] ; ?>_form.php" method="POST" id="entForm" name="entForm">
<input type="hidden" name="DataAction" id="DataAction" value="update">
<input type="hidden" id="<?php  echo $entID ; ?>ID" name="<?php  echo $entID ; ?>ID" value="">
<input type="hidden" id="aclOldStatusID" name="aclOldStatusID" value="<?php  echo $staID ; ?>">
<input type="hidden" id="aclNewStatusID" name="aclNewStatusID" value="">
<input type="hidden" id="actID" name="actID" value="2">
<input type="hidden" id="aclToDo" name="aclToDo" value="">
<input type="hidden" id="actComments" name="actComments" value="">

<table width="100%">
<tr><td width="70%">
<?php 
$rwEnt = Array("entID" => $entID
, $entID."StatusID" => $staID);
showEntityItemFields($oSQL, $rwEnt, Array('flagShowOnlyEditable'=>true));
 ?>
</td>
<td width="30%"><?php 
   echo showActions($oSQL, array_merge($rwEnt, Array(
      "staFlagCanUpdate"=>$this->intra->arrUsrData["FlagWrite"]
      , "staFlagCanDelete"=>false
      , "flag_aatFlagToAdd"=>true)));
      
 ?>
</td>
</tr>
</table>
</form>
</div>
<?php 
}

}
*/
?>