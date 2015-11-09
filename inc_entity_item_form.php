<?php
/* visualization functions */
include_once "inc_entity_item.php";

class eiseEntityItemForm extends eiseEntityItem {

function __construct($oSQL, $intra, $entID, $entItemID, $conf = array()){
    
    parent::__construct($oSQL, $intra, $entID, $entItemID, $conf);
    
    $this->getEntityItemAllData();
    
}

function form($arrConfig=Array()){

    $arrDefaultConf = array('flagHideDraftStatusStay'=>true);
    $arrConfig = array_merge($arrDefaultConf, $arrConfig);

    $entItemID = $this->entItemID;

    $oSQL = $this->oSQL;
    $entID = $this->entID;
    $item = $this->item;

    if (!$this->flagArchive){
?>
<form action="<?php  echo $_SERVER["PHP_SELF"] ; ?>" method="POST" id="entForm" class="eiseIntraForm">
<input type="hidden" name="DataAction" id="DataAction" value="update">
<input type="hidden" name="entID" id="entID" value="<?php  echo $entID ; ?>">
<input type="hidden" name="<?php echo "{$entID}"; ?>ID" id="<?php echo "{$entID}"; ?>ID" value="<?php  echo $item["{$entID}ID"] ; ?>">
<input type="hidden" name="aclOldStatusID" id="aclOldStatusID" value="<?php  echo $item["{$entID}StatusID"] ; ?>">
<input type="hidden" name="aclNewStatusID" id="aclNewStatusID" value="">
<input type="hidden" name="actID" id="actID" value="">
<input type="hidden" name="aclGUID" id="aclGUID" value="">
<input type="hidden" name="aclToDo" id="aclToDo" value="">
<input type="hidden" name="aclComments" id="aclComments" value="">
<?php 
    }
 ?>


<table width="100%">
<tbody>
<tr>
<td width="50%">

<h1><?php  echo $item["entTitle{$this->intra->local}"]." ".$item["{$entID}ID"] ; ?>
<?php  echo ($this->flagArchive ? " (".$this->intra->translate("Archive").")" : "") ; ?></h1>

<?php 
$this->showEntityItemFields( array_merge($arrConfig, Array('showComments'=>true)));

$this->showFiles();
?>
</td>
<td width="50%">
<?php 
echo $this->showActions($arrConfig["actionCallBack"]);
 ?>


<?php 
$this->showActivityLog($arrConfig)
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
 </tbody>
</table>

</form>

<script>
$(document).ready(function(){ 
    $('.eiseIntraForm').
        eiseIntraForm().
        eiseIntraEntityItemForm({flagUpdateMultiple: false}).
        submit(function(event) {
            var $form = $(this);
            $form.eiseIntraEntityItemForm("checkAction", function(){
                if ($form.eiseIntraForm("validate")){
                    window.setTimeout(function(){$form.find('input[type="submit"], input[type="button"]').each(function(){this.disabled = true;})}, 1);
                    $form[0].submit();
                } else {
                    $form.eiseIntraEntityItemForm("reset");
                }
            })
        
            return false;
        
        });
});
</script>
<?php
    //echo "<pre>";
    //print_r($this->item);

}



function showActions($actionCallBack=""){
    $oSQL = $this->oSQL;
    $item = $this->item;
    
    if (!$this->intra->arrUsrData["FlagWrite"]
        || (count($this->conf['STA'][$this->staID]['ACT'])==0 && count($this->item["ACL"])==0)
        ) return;
    
    ?>
    <fieldset class="eiseIntraActions eiseIntraSubForm"><legend><?php  echo $this->intra->translate("Actions") ; ?></legend>
    <?php 
    $entID = $this->entID;
    $entItemID = $this->entItemID;
    $staID = $this->staID;
    
    $ii = 0;
    foreach ($this->item["ACL"] as $ix=>$rwACL){

        $this->showActionInfo($rwACL, $actionCallBack);
        $staID = ($ii==0 ? $rwACL["aclNewStatusID"] : $staID);
        $ii++;
                
    }
    
    if (!$this->flagArchive){
        
        echo $this->showActionRadios();
        
        if ($this->intra->arrUsrData["FlagWrite"]){
            echo "<div align=\"center\"><input class=\"eiseIntraSubmit\" id=\"btnsubmit\" type=\"submit\" value=\"".$this->intra->translate("Run")."\"></div>";
        }
        
    }
    ?>
    </fieldset>
    <?php
}


function showEntityItemFields($arrConfig = Array()){
    
    $strLocal = $this->intra->local;
    
    $oSQL = $this->oSQL;
    $item = $this->item;
    
    $entID = $this->entID;
    $entItemID = $this->entItemID;
    
    if(empty($this->conf["ATR"])){
        throw new Exception("Attribute set not found");
    }
    
    echo "<fieldset class=\"eiseIntraMainForm\"><legend>".($arrConfig['title'] 
        ? $arrConfig['title'] 
        : $this->intra->translate("Data"))."</legend>\r\n";

    echo $this->getFieldsHTML($arrConfig, $this);
    
    if ($this->entItemID){
        if ($arrConfig["showComments"]){
            // Comments
            $this->showCommentsField();
        }
        
    }

    echo "</fieldset>\r\n\r\n";
    
}

function showFieldset($title, $id, $arrAtr, $strExtraField=''){
    $oSQL = $this->intra->oSQL;
    $intra = $this->intra;
?>
<fieldset id="<?php echo $id ?>"><legend><?php echo $title; ?></legend>
<?php 
echo $strExtraField;

if (count($arrAtr)==0)
    $arrAtr = array_keys($this->conf['ATR']);

foreach($arrAtr as $atr){

    $strFields .= $this->field($atr);
    
}

echo $strFields;

 ?>
</fieldset>
<?php

}

/**
 * 
 */
function field( $atr, $arrConf = array() ){

    $intra = $this->intra;

    if ($atr=='__comments'){
        return $this->showComments();
    }

    if(!isset($this->conf['STA'][(int)$this->staID]['satFlagShowInForm'][$atr]))
        return '';

    $rwAtr = $this->conf['ATR'][$atr];

    $arrConf = array_merge(
        array('type'=>$rwAtr['atrType']
            , 'text' => $this->item[$rwAtr["atrID"]."_text"]
            , 'source' => $rwAtr['atrDataSource']
            , 'source_prefix' => $rwAtr['atrProgrammerReserved']
            , 'FlagWrite' => $this->conf['STA'][(int)$this->staID]['satFlagShowInForm'][$atr]
            , 'UOM' => $rwAtr['atrUOMTypeID']
            , 'textIfNull' => $intra->translate($rwAtr['atrTextIfNull'])
            )
        , $arrConf
        );

    return $this->intra->field($rwAtr["atrTitle{$intra->local}"], $rwAtr['atrID'], $this->item[$rwAtr["atrID"]], $arrConf);

}


function showActivityLog($arrConfig=array()){

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


foreach($this->item["STL"] as $stlGUID => $rwSTL){

    if ($arrConfig['flagHideDraftStatusStay'] && $rwSTL['stlStatusID']==='0')
        continue;
    ?>
    <div class="eiseIntraLogStatus">
    <div class="eiseIntraLogTitle"><span class="eiseIntra_stlTitle"><?php echo ($rwSTL["stlTitle{$this->intra->local}"]!="" 
        ? $rwSTL["stlTitle{$this->intra->local}"]
        : $rwSTL["staTitle"]); ?></span>
        
        <span class="eiseIntra_stlATA"><?php echo $intra->dateSQL2PHP($rwSTL["stlATA"], $rwSTL["staTrackPrecision"]); ?></span>
        <span class="eiseIntra_stlATD"><?php echo ( $rwSTL["stlATD"] ? $intra->DateSQL2PHP($rwSTL["stlATD"], $rwSTL["staTrackPrecision"]) : $intra->translate("current time")); ?></span>
            
    </div>
    <div class="eiseIntraLogData">
    <?php
    if (isset($rwSTL["ACL"]))
    foreach ($rwSTL["ACL"] as $rwNAct){
       $this->showActionInfo($rwNAct);
    }
    
    // linked attributes
    if (isset($rwSTL["SAT"]))
    foreach($rwSTL["SAT"] as $atrID => $rwATV){
        $rwATV = array_merge($this->conf['ATR'][$atrID], $rwATV);
        $rwATV["FlagWrite"] = false;
        ?>
        <div class="eiseIntraField"><label><?php  echo $rwATV["atrTitle{$this->intra->local}"] ; ?>:</label>
        <?php 
           echo $this->showAttributeValue($rwATV, "_".$rwSTL["stlGUID"]); ?>&nbsp;
        </div>
        <?php
    }
    
    ?>    
    </div>
    </div>
    
    <?php
    $this->showActionInfo($rwSTL["stlArrivalAction"]);
}
 ?>
</fieldset>
&nbsp;
<?php  
if (count($this->item['ACL_Cancelled']) > 0 ) {
?>
<fieldset><legend><?php  echo $this->intra->translate("Cancelled actions") ; ?></legend>
<?php 
$ii = 0;
foreach ($this->item["ACL_Cancelled"] as $rwACL) {
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
    <div class="eiseIntraLogAction">
    <div class="eiseIntraLogTitle" id="aclTitle_<?php  echo $rwACT["aclGUID"] ; ?>" title="Last edited: <?php  echo htmlspecialchars($rwACT["aclEditBy"]."@".Date("d.m.Y H:i", strtotime($rwACT["aclEditDate"]))).
            "\r\n / ".$rwACT["aclEntityItemID"]."|".$rwACT["aclGUID"]; ?>"><?php 
    echo 
        ($rwACT["aclActionPhase"]==2 // if action is complete, show past tense
            ? $rwACT["actTitlePast{$strLocal}"]
            : $rwACT["actTitle{$strLocal}"]).
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
    <div class="eiseIntraLogData">
<?php 
    // timestamps
    foreach($rwACT['aatFlagTimestamp'] as $ts=>$field)
        if (strpos($field, 'acl')===0){
        ?>
<div class="eiseIntraField">
    <label><?php echo $ts ?>:</label><?php 
        echo $this->showAttributeValue(Array("atrID"=>$field
            , "value" => $rwACT["acl{$ts}"]
            , "atrType"=>$rwACT["actTrackPrecision"]
            , "FlagWrite"=>$flagEditable
            , "aatFlagMandatory"=>true
            ), "_".$rwACT["aclGUID"]);
             ?>&nbsp;
    </div>
        <?php
    }

    // linked attributes
    if (isset($rwACT["AAT"]))
    foreach($rwACT["AAT"] as $ix => $rwATV){
        $rwATV = array_merge($this->conf['ATR'][$ix], $rwATV);
        $rwATV["FlagWrite"] = $flagEditable;
        ?>
        <div class="eiseIntraField"><label><?php  
            echo $rwATV["atrTitle{$this->intra->local}"] ; 
            ?>:</label><?php 
            echo $this->showAttributeValue($rwATV, "_".$rwACT["aclGUID"]); ?>
        </div>
        <?php
    }
    
    if ($rwACT['aclComments']){
        ?>
        <div class="eiseIntraField"><label><?php  
            echo $this->intra->translate('Comments');
            ?>:</label><div class="eiseIntraValue"><i><?php echo $rwACT['aclComments'] ?></i></div>
        </div>
        <?php
    }


    eval($actionCallBack.";");
    
    ?>
    
    </div>
    <?php 
    if ($rwACT["aclActionPhase"]<2 && !$this->flagArchive){
        ?><div align="center"><?php
        
        if ($rwACT["aclActionPhase"]=="0"){
            ?><input name="start_<?php  echo $aclGUID ; ?>" id="start_<?php  echo $rwACT["aclGUID"] ; ?>" 
            type="button" value="Start" class="eiseIntraActionButton">
            <?php
        }
        if ($rwACT["aclActionPhase"]=="1"){
            ?><input name="finish_<?php  echo $aclGUID ; ?>" id="finish_<?php  echo $rwACT["aclGUID"] ; ?>" 
            type="button" value="Finish" class="eiseIntraActionButton">
            <?php
        }
        ?><input name="cancel_<?php  echo $aclGUID ; ?>" id="cancel_<?php  echo $rwACT["aclGUID"] ; ?>" 
        type="button" value="Cancel" class="eiseIntraActionButton"></div>
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
            , "aatFlagMandatory"=>true
            , "value" => $value)
          , $suffix);
}

function showActivityLog_simple($arrConfig=Array()) {

$strLoc = $this->intra->local;

$arrStatus = $this->getActionLog($arrConfig);


$strRes = "<fieldset><legend>Status".($arrConfig['staTitle'] ? ": ".$arrConfig['staTitle'] : "")."</legend>";
$strRes .= "<div style=\"max-height:100px; overflow-y: auto;\">\r\n";
$strRes .= "<table width='100%' class='eiseIntraHistoryTable'>\r\n";

for ($i=0;$i<count($arrStatus);$i++){
    $strRes .= "<tr class='tr".($i % 2)."' valign='top'>\r\n";
    if ($arrStatus[$i]["aclActionPhase"]==2) {
        $strRes .= "<td nowrap><b>".$arrStatus[$i]["actTitlePast"]."</b></td>\r\n";
    } else {
        $strRes .= "<td nowrap>Started &quot;<b>".$arrStatus[$i]["actTitle"]."</b>&quot;</td>\r\n";
    }
    $strRes .= "<td nowrap>".($arrStatus[$i]["aclEditBy"] ? $this->intra->getUserData($arrStatus[$i]["aclEditBy"]) : '')."</td>";
    $strRes .= "<td nowrap>".date("d.m.Y H:i", strtotime($arrStatus[$i]["aclEditDate"]))."</td>";
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

function getActionLog($arrConfig = array()){
    
    $arrACL = array();

    $sql = "SELECT stbl_action_log.*
        , stbl_action.*
        , stbl_user.*
        , STA_OLD.staTitle{$this->intra->local} as staTitle_old
        , STA_NEW.staTitle{$this->intra->local} as staTitle_new
      FROM stbl_action_log
        INNER JOIN stbl_action ON actID= aclActionID
        LEFT OUTER JOIN stbl_status STA_OLD ON STA_OLD.staID=aclOldStatusID AND STA_OLD.staEntityID=actEntityID
        LEFT OUTER JOIN stbl_status STA_NEW ON STA_NEW.staID=aclOldStatusID AND STA_NEW.staEntityID=actEntityID
        LEFT OUTER JOIN stbl_user ON usrID=aclInsertBy
      WHERE aclEntityItemID='".$this->entItemID."'".
      (!$arrConfig['flagIncludeUpdate'] ? " AND aclActionID<>2" : "")
      ."
      ORDER BY aclInsertDate DESC, aclNewStatusID DESC";
    $rs = $this->oSQL->do_query($sql);
    while ($rw = $this->oSQL->fetch_array($rs)) {
        if(!$rw['usrID']) $rw['usrName'] = $rw['aclInsertBy'];
        $acl = array(
            'alGUID' => $rw['aclGUID']
            , 'actID' => $rw['actID']
            , 'aclActionPhase' => $rw['aclActionPhase']
            , 'aclOldStatusID' => $rw['aclOldStatusID']
            , 'aclNewStatusID' => $rw['aclNewStatusID']
            , 'actTitle' => $rw['actTitle'.$this->intra->local]
            , 'actTitlePast' => $rw['actTitlePast'.$this->intra->local]
            , 'aclComments' => $rw['aclComments']
            , 'aclEditBy' => $this->intra->translate('by ').($this->intra->local ? ($rw['usrNameLocal'] ? $rw['usrNameLocal'] : $rw['usrName']) : $rw['usrName'])
            , 'aclEditDate' => date("{$this->intra->conf['dateFormat']} {$this->intra->conf['timeFormat']}"
                , strtotime($rw["aclEditDate"]))
            , 'aclATA' => date("{$this->intra->conf['dateFormat']}"
                    .(strtotime($rw["aclATA"])!=strtotime(date('Y-m-d', strtotime($rw["aclATA"]))) ? " {$this->intra->conf['timeFormat']}" : '')
                , strtotime($rw["aclATA"]))
            );
        $arrACL[] = $acl;  
    }
        
    $this->oSQL->free_result($rs);

    return $arrACL;

}



function showActionLog_skeleton(){

    $strRes = "<div id=\"eiseIntraActionLog\" title=\"".$this->intra->translate('Action Log')."\">\r\n";
    $strRes .= "<table class='eiseIntraActionLogTable'>\r\n";
    $strRes .= "<tbody class=\"eif_ActionLog\">";
    
    $strRes .= "<tr class=\"eif_template eif_evenodd\">\r\n";
    $strRes .= "<td class=\"eif_actTitlePast\"></td>\r\n";
    $strRes .= "<td class=\"eif_aclEditBy\"></td>";
    $strRes .= "<td class=\"eif_aclATA\" style=\"text-align:right;\"></td>";
    $strRes .= "</tr>";
    
    $strRes .= "<tr class=\"eif_template eif_evenodd eif_invisible\">";
    $strRes .= "<td class=\"eif_commentsTitle\">".$this->intra->translate("Comments").":</td>\r\n";
    $strRes .= "<td colspan='2' class=\"eif_aclComments\"></td>";
    $strRes .= "</tr>";

    $strRes .= "<tr class=\"eif_notfound\">";
    $strRes .= "<td colspan='3'>".$this->intra->translate("No Events Found")."</td>";
    $strRes .= "</tr>";

    $strRes .= "<tr class=\"eif_spinner\">";
    $strRes .= "<td colspan='3'></td>";
    $strRes .= "</tr>";
        
    $strRes .= "</tbody>";
    $strRes .= "</table>\r\n";
    $strRes .= "</div>\r\n";

    return $strRes;

}

/***********************************************************************************/
/* Comments Routines                                                               */
/***********************************************************************************/
function showComments(){
    $oSQL = $this->oSQL;
    $intra = $this->intra;   

    $strComments = '';

    $strComments .= '<div class="eiseIntraField">'."\r\n";

    $strComments .= '<label>'.$this->intra->translate("Comments").':</label>'."\r\n";
    $strComments .= '<div class="eiseIntraValue">'."\r\n";

    if ($this->intra->arrUsrData["FlagWrite"] && !$this->flagArchive){
        $strComments .= '<textarea class="eiseIntraComment"></textarea>'."\r\n";
    }
    foreach ($this->item["comments"] as $ix => $rwSCM){
        $strComments .= '<div id="scm_'.$rwSCM["scmGUID"].'" class="eiseIntraComment'.($intra->usrID==$rwSCM["scmInsertBy"] ? " eiseIntraComment_removable" : "").'">'."\r\n";
        $strComments .= '<div class="eiseIntraComment_userstamp">'.$intra->getUserData($rwSCM["scmInsertBy"]).' '.$intra->translate('at').' '.$intra->dateSQL2PHP($rwSCM["scmInsertDate"], "d.m.Y H:i")."\r\n";
        $strComments .= '</div>'."\r\n";
        $strComments .= '<div>'.str_replace("\n", "<br>", htmlspecialchars($rwSCM["scmContent"] )).'</div>'."\r\n";
        $strComments .= '</div>'."\r\n";
        
    }
    $strComments .= '</div>'."\r\n";


    $strComments .= '<div class="eiseIntraComment_contols">'."\r\n";
    $strComments .= '<input type="button" class="eiseIntraComment_add ss_sprite ss_add">'."\r\n";
    $strComments .= '<input type="button" class="eiseIntraComment_remove ss_sprite ss_delete">'."\r\n";
    $strComments .= '</div>'."\r\n";

    $strComments .= '</div>'."\r\n";


    return $strComments;
}


// old version
function showCommentsField(){
    $oSQL = $this->oSQL;
    $intra = $this->intra;   
    ?>
<div class="eiseIntraField">
<label><?php echo $this->intra->translate("Comments"); ?>:</label>
<div class="eiseIntraValue">
<?php 
    if ($this->intra->arrUsrData["FlagWrite"] && !$this->flagArchive){?>
<textarea class="eiseIntraComment"></textarea>
<?php
    }
    foreach ($this->item["comments"] as $ix => $rwSCM){
?>
<div id="scm_<?php  echo $rwSCM["scmGUID"] ; ?>" class="eiseIntraComment<?php echo ($intra->usrID==$rwSCM["scmInsertBy"] ? " eiseIntraComment_removable" : "") ?>">
<div class="eiseIntraComment_userstamp"><?php  echo $intra->getUserData($rwSCM["scmInsertBy"]).' '.$intra->translate('at').' '.$intra->dateSQL2PHP($rwSCM["scmInsertDate"], "d.m.Y H:i");
 ?></div>
<div><?php  echo str_replace("\n", "<br>", htmlspecialchars($rwSCM["scmContent"] )); ?></div>
</div>
<?php
    }
?>
</div>
<?php
?>

<div class="eiseIntraComment_contols">
<input type="button" class="eiseIntraComment_add ss_sprite ss_add">
<input type="button" class="eiseIntraComment_remove ss_sprite ss_delete">
</div>

</div>
<?php
}


/***********************************************************************************/
/* File Attachment Routines                                                        */
/***********************************************************************************/
function showFileAttachDiv(){
    $entID = $this->entID;
    $entItemID = $this->entItemID;

?>
<div id="divAttach" style="display:none;">
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
<input type="submit" value="Upload" id="btnUpload" style="width: 180px; font-weight:bold;" /><input 
    type="button" value="Cancel" onclick="$('#divAttach').dialog('close');" style="width:80px;" />
</form>
</div>

<?php
}

function showFiles(){

$entID = $this->entID;
$entItemID = $this->entItemID;
$intra = $this->intra;

$arrFil = $this->getFiles();

if (count($arrFil) > 0) {
?>
<fieldset><legend><?php  echo $this->intra->translate("Files") ; ?></legend>
<div style="max-height:100px; overflow-y: auto;">
<table width="100%" class="eiseIntraHistoryTable">
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
foreach($arrFil as $ix=>$rwFile){
    ?>
<tr class="tr<?php  echo $i%2 ; ?>">
<td width="100%"><a href="<?php  echo $rwFile["filName"]['h'] ; ?>" target="_blank"><?php  echo $rwFile["filName"]['v'] ; ?></a></td>
<td><?php  echo $rwFile["filEditBy"] ; ?></td>
<td><?php  echo $rwFile["filEditDate"] ; ?></td>
<td class="eiseIntra_unattach" id="fil_<?php  echo $rwFile["filGUID"] ; ?>" title="Delete">&nbsp;X&nbsp;</td>
</tr>    
    <?php
    $i++;
}
 ?>
 </tbody>
</table>
</div>
</fieldset>
<?php

}

}

public function getFiles(){

    $oSQL = $this->oSQL;
    $entID = $this->entID;
    $entItemID = $this->entItemID;
    $intra = $this->intra;

    $sqlFile = "SELECT * FROM stbl_file WHERE filEntityID='$entID' AND filEntityItemID='{$entItemID}'
    ORDER BY filInsertDate DESC";
    $rsFile = $oSQL->do_query($sqlFile);

    $arrFIL = array();

    $rs = $this->oSQL->do_query($sqlFile);
    while ($rw = $this->oSQL->fetch_array($rs)) {
        if(!$rw['usrID']) $rw['usrName'] = $rw['filInsertBy'];
        $fil = array(
            'filGUID' => $rw['filGUID']
            , 'filName' => array(
                    'h'=>"popup_file.php?filGUID=".urlencode($rw["filGUID"])
                    , 'v'=>$rw['filName']
                    )
            , 'filContentType' => $rw['filContentType']
            , 'filLength' => $rw['filLength']
            , 'filEditBy' => $this->intra->translate('by ').$this->intra->getUserData($rw['filInsertBy'])
            , 'filEditDate' => date("{$this->intra->conf['dateFormat']} {$this->intra->conf['timeFormat']}"
                , strtotime($rw["filInsertDate"]))
            );
        $arrFIL[] = $fil;  
    }
        
    $this->oSQL->free_result($rs);

    return $arrFIL;

}

function showFileAttachForm(){
    $entID = $this->entID;
    $entItemID = $this->entItemID;

    $strDiv = '';
    $strDiv .= '<form id="eif_frmAttach" action="'.$_SERVER["PHP_SELF"].'" method="POST" enctype="multipart/form-data" onsubmit="
       if (document.getElementById(\'eif_attachment\').value==\'\'){
          alert (\'File is not specified.\');
          document.getElementById(\'eif_attachment\').focus();
          return false;
       }
       var btnUpl = document.getElementById(\'eif_btnUpload\');
       btnUpl.value = \'Loading...\';
       btnUpl.disabled = true;
       return true;

    ">'."\r\n";
    $strDiv .= '<input type="hidden" name="DataAction" id="DataAction_attach" value="attachFile">'."\r\n";
    $strDiv .= '<input type="hidden" name="entID_Attach" id="entItemID_Attach" value="'.$this->entID.'">'."\r\n";
    $strDiv .= '<input type="hidden" name="entItemID_Attach" id="entItemID_Attach" value="'.$entItemID.'">'."\r\n";
    //$strDiv .= '<label>'.$this->intra->translate('Choose file').': </label>'."\r\n";
    $strDiv .= '<input type="file" id="eif_attachment" name="attachment" title="'.$this->intra->translate('Choose file').'">'."\r\n";
    $strDiv .= '<input type="submit" value="Upload" id="eif_btnUpload">'."\r\n";
    $strDiv .= '</form>'."\r\n";

    return $strDiv;
}

function showFileList_skeleton(){

    $strRes = "<div id=\"eiseIntraFileList\" title=\"".$this->intra->translate('Files')."\">\r\n";
    
    if ($this->intra->arrUsrData['FlagWrite']){
        $strRes .= $this->showFileAttachForm();
    }

    $strRes .= "<table class=\"eiseIntraFileListTable\">\r\n";
    $strRes .= "<thead>\r\n";
    $strRes .= "<tr>\r\n";
    $strRes .= "<th>".$this->intra->translate('File')."</th>\r\n";
    $strRes .= "<th colspan=\"2\">".$this->intra->translate('Uploaded')."</th>\r\n";
    $strRes .= "<th class=\"eif_filUnattach\">&nbsp;</th>\r\n";
    $strRes .= "</tr>\r\n";
    $strRes .= "</thead>\r\n";


    $strRes .= "<tbody class=\"eif_FileList\">";

    $strRes .= "<tr class=\"eif_template eif_evenodd\">\r\n";
    $strRes .= "<td><a href=\"\" class=\"eif_filName\" target=_blank></a></td>\r\n";
    $strRes .= "<td class=\"eif_filEditBy\"></td>";
    $strRes .= "<td class=\"eif_filEditDate\"></td>";
    $strRes .= "<td class=\"eif_filUnattach\"><input type=\"hidden\" class=\"eif_filGUID\"> X </td>";
    $strRes .= "</tr>";

    $strRes .= "<tr class=\"eif_notfound\">";
    $strRes .= "<td colspan=3>".$this->intra->translate("No Files Attached")."</td>";
    $strRes .= "</tr>";

    $strRes .= "<tr class=\"eif_spinner\">";
    $strRes .= "<td colspan=3></td>";
    $strRes .= "</tr>";
        
    $strRes .= "</tbody>";
    $strRes .= "</table>\r\n";
    $strRes .= "</div>\r\n";

    return $strRes;

}

function showMessages_skeleton(){

    $oldFlagWrite = $this->intra->arrUsrData['FlagWrite'];
    $this->intra->arrUsrData['FlagWrite'] = true;

    $strRes = '<div id="eiseIntraMessages" title="'.$this->intra->translate('Messages').'">'."\n";

    $strRes .= '<div class="eiseIntraMessage eif_template eif_evenodd">'."\n";
    $strRes .= '<div class="eif_msgInsertDate"></div>';
    $strRes .= '<div class="eiseIntraMessageField"><label>'.$this->intra->translate('From').':</label><span class="eif_msgFrom"></span></div>';
    $strRes .= '<div class="eiseIntraMessageField"><label>'.$this->intra->translate('To').':</label><span class="eif_msgTo"></span></div>';
    $strRes .= '<div class="eiseIntraMessageField eif_invisible"><label>'.$this->intra->translate('CC').':</label><span class="eif_msgCC"></span></div>';
    $strRes .= '<div class="eiseIntraMessageField eif_invisible"><label>'.$this->intra->translate('Subject').':</label><span class="eif_msgSubject"></span></div>';
    $strRes .= '<pre class="eif_msgText"></div>';
    $strRes .= '</pre>'."\n";

    $strRes .= '<div class="eif_notfound">';
    $strRes .= '<td colspan=3>'.$this->intra->translate('No Messages Found').'</td>';
    $strRes .= '</div>';

    $strRes .= '<div class="eif_spinner">';
    $strRes .= '</div>';
    
    $strRes .= '<div class="eiseIntraMessageButtons"><input type="button" id="msgNew" value="'.$this->intra->translate('New Message').'">';
    $strRes .= '</div>';
        
    $strRes .= "</div>\r\n";

    $strRes .= '<form id="eiseIntraMessageForm" title="'.$this->intra->translate('New Message').'" class="eiseIntraForm" method="POST">'."\n";
    $strRes .= '<input type="hidden" name="DataAction" id="DataAction_attach" value="messageSend">'."\r\n";
    $strRes .= '<input type="hidden" name="entID" id="entID_Message" value="'.$this->entID.'">'."\r\n";
    $strRes .= '<input type="hidden" name="entItemID" id="entItemID_Message" value="'.$this->entItemID.'">'."\r\n";
    $strRes .= '<div class="eiseIntraMessageField"><label>'.$this->intra->translate('To').':</label>'
        .$this->intra->showAjaxDropdown('msgToUserID', '', array('required'=>true, 'strTable'=>'svw_user')).'</div>';
    $strRes .= '<div class="eiseIntraMessageField"><label>'.$this->intra->translate('CC').':</label>'
        .$this->intra->showAjaxDropdown('msgCCUserID', '', array('strTable'=>'svw_user')).'</div>';
    $strRes .= '<div class="eiseIntraMessageField"><label>'.$this->intra->translate('Subject').':</label>'.$this->intra->showTextBox('msgSubject', '').'</div>';
    $strRes .= '<div class="eiseIntraMessageBody">'.$this->intra->showTextArea('msgText', '').'</div>';
    $strRes .= '<div class="eiseIntraMessageButtons"><input type="submit" id="msgPost" value="'.$this->intra->translate('Send').'">
        <input type="button" id="msgClose" value="'.$this->intra->translate('Close').'">
        </div>';
    $strRes .= "</form>\r\n";

    $this->intra->arrUsrData['FlagWrite'] = $oldFlagWrite;

    return $strRes;

}

public function getMessages(){

    $oSQL = $this->oSQL;
    $entID = $this->entID;
    $entItemID = $this->entItemID;
    $intra = $this->intra;

    $sqlMsg = "SELECT *
    , (SELECT optText FROM svw_user WHERE optValue=msgFromUserID) as msgFrom
    , (SELECT optText FROM svw_user WHERE optValue=msgToUserID) as msgTo
    , (SELECT optText FROM svw_user WHERE optValue=msgCCUserID) as msgCC
     FROM stbl_message WHERE msgEntityID='$entID' AND msgEntityItemID='{$entItemID}'
    ORDER BY msgInsertDate DESC";
    $rsMsg = $oSQL->q($sqlMsg);

    return $intra->result2JSON($rsMsg);


    $arrMsg = array();
    while ($rw = $oSQL->f($rsMsg)) {
        
        $arrMsg[] = $rw;  
    }
        
    $this->oSQL->free_result($rsMsg);

    return $arrMsg;

}

}
?>