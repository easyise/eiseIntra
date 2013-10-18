<?php
/* visualization functions */
include_once "inc_entity_item.php";

class eiseEntityItemForm extends eiseEntityItem {

function __construct($oSQL, $intra, $entID, $entItemID, $flagArchive = false){
    
    parent::__construct($oSQL, $intra, $entID, $entItemID, $flagArchive);
    
    $this->getEntityItemAllData();
    
}

function form($arrConfig=Array()){

    $arrDefaultConf = array('flagHideDraftStatusStay'=>true);
    $arrConfig = array_merge($arrDefaultConf, $arrConfig);

    $entItemID = $rwEnt[$entID."ID"];

    $oSQL = $this->oSQL;
    $entID = $this->entID;
    $rwEnt = $this->rwEnt;

    if (!$this->flagArchive){
?>
<form action="<?php  echo $_SERVER["PHP_SELF"] ; ?>" method="POST" id="entForm" class="eiseIntraForm">
<input type="hidden" name="DataAction" id="DataAction" value="update">
<input type="hidden" name="entID" id="entID" value="<?php  echo $entID ; ?>">
<input type="hidden" name="<?php echo "{$entID}"; ?>ID" id="<?php echo "{$entID}"; ?>ID" value="<?php  echo $rwEnt["{$entID}ID"] ; ?>">
<input type="hidden" name="aclOldStatusID" id="aclOldStatusID" value="<?php  echo $rwEnt["{$entID}StatusID"] ; ?>">
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

<h1><?php  echo $rwEnt["entTitle{$this->intra->local}"]." ".$rwEnt["{$entID}ID"] ; ?>
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
    //print_r($this->rwEnt);

}



function showActions($actionCallBack=""){
    $oSQL = $this->oSQL;
    $rwEnt = $this->rwEnt;
    
    if(empty($this->arrAct))
            $this->collectDataActions();
            
    if (!$this->intra->arrUsrData["FlagWrite"]
        || (count($this->arrAct)==0 && count($this->rwEnt["ACL"])==0)
        ) return;
    
    ?>
    <fieldset class="eiseIntraActions eiseIntraSubForm"><legend><?php  echo $this->intra->translate("Actions") ; ?></legend>
    <?php 
    $entID = $rwEnt["entID"];
    $entItemID = $rwEnt[$rwEnt["entID"]."ID"];
    
    $staID = $rwEnt[$entID."StatusID"];
    
    $ii = 0;
    foreach ($this->rwEnt["ACL"] as $ix=>$rwACL){
            
        if ($rwACL["aclActionPhase"] > 2) continue; //skip cancelled
            
        $this->showActionInfo($rwACL, $actionCallBack);
        $staID = ($ii==0 ? $rwACL["aclNewStatusID"] : $staID);
        $ii++;
                
    }
    
    if (!$this->flagArchive){
        
        echo $this->showActionRadios();
        
    }
    if ($this->intra->arrUsrData["FlagWrite"] && !$this->flagArchive){
            
        echo "<div align=\"center\"><input class=\"eiseIntraSubmit\" id=\"btnsubmit\" type=\"submit\" value=\"".$this->intra->translate("Run")."\"></div>";
        
    }
    ?>
    </fieldset>
    <?php
}


function showEntityItemFields($arrConfig = Array()){
    
    $strLocal = $this->intra->local;
    
    $oSQL = $this->oSQL;
    $rwEnt = $this->rwEnt;
    
    $entID = $this->entID;
    $entItemID = $rwEnt[$entID."ID"];
    
    if(empty($this->rwEnt["ATR"])){
        throw new Exception("Attribute set not found");
    }
    
    echo "<fieldset class=\"eiseIntraMainForm\"><legend>".($arrConfig['title'] 
        ? $arrConfig['title'] 
        : $this->intra->translate("Data"))."</legend>\r\n";

    echo $this->getFields($arrConfig, $this);
    
    if ($rwEnt[$entID."ID"]){
        if ($arrConfig["showComments"]){
            // Comments
            $this->showCommentsField();
        }
        
        if ($arrConfig["showFiles"]){
            // Files
            $i++;
            echo "<div class=\"intraFormRow tr".( $i % 2 )."\">\r\n";
            echo "<div class=\"eiseIntraFieldTitle\">Files:</div>";
            echo "<div class=\"eiseIntraFieldValue\">";
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

    echo "</fieldset>\r\n\r\n";
    
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


foreach($this->rwEnt["STL"] as $stlGUID => $rwSTL){
    if ($arrConfig['flagHideDraftStatusStay'] && $rwSTL['stlStatusID']==='0')
        continue;
    ?>
    <div class="eiseIntraLogStatus">
    <div class="eiseIntraLogTitle"><span class="eiseIntra_stlTitle"><?php echo ($rwSTL["stlTitle{$this->intra->local}"]!="" 
        ? $rwSTL["stlTitle{$this->intra->local}"]
        : $rwSTL["staTitle"]); ?></span>
        
        <span class="eiseIntra_stlATA"><?php echo $intra->dateSQL2PHP($rwSTL["stlATA"]); ?></span>
        <span class="eiseIntra_stlATD"><?php echo ( $rwSTL["stlATD"] ? $intra->DateSQL2PHP($rwSTL["stlATD"]) : $intra->translate("current time")); ?></span>
            
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
        $rwATV["satFlagEditable"] = false;
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
    $flagEditable = $rwACT['aclFlagEditable'] || ($rwACT["aclActionPhase"]<2 && $this->intra->arrUsrData["FlagWrite"]==true && !$this->flagArchive);
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
    $arrTS = $this->getActonTimestamps($rwACT);
    foreach($arrTS as $ts=>$data){
        if (is_array($data))
            continue;
        ?>
<div class="eiseIntraField">
    <label><?php echo $ts.($flagEditable ? "*" : ""); ?>:</label><?php 
        echo $this->showAttributeValue(Array("atrID"=>"acl{$ts}"
            , "value" => $rwACT["acl{$ts}"]
            , "atrType"=>$rwACT["actTrackPrecision"]
            , "satFlagEditable"=>$flagEditable
            , "aatFlagMandatory"=>true
            ), "_".$rwACT["aclGUID"]);
          //$this->showTimestampField("aclATA", $flagEditable, $rwACT["aclATA"], "_".$rwACT["aclGUID"]); ?>&nbsp;
    </div>
        <?php
    }
    ///*
    // linked attributes
    if (isset($rwACT["AAT"]))
    foreach($rwACT["AAT"] as $ix => $rwATV){
        $rwATV["satFlagEditable"] = $flagEditable;
        if (!$rwATV["aatFlagToTrack"])
            continue;
        //if ($rwATV["aatFlagTimestamp"]!="")
        //    continue;   
        ?>
        <div class="eiseIntraField"><label><?php  
            echo $rwATV["atrTitle{$this->intra->local}"].($rwATV["aatFlagMandatory"] && $rwATV["satFlagEditable"] ? "*" : "") ; 
            ?>:</label><?php 
            echo $this->showAttributeValue($rwATV, "_".$rwACT["aclGUID"]); ?>
        </div>
        <?php
    }
    //*/
    
    
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

$sql = "SELECT *
  FROM stbl_action_log
  INNER JOIN stbl_action ON actID= aclActionID
  WHERE aclEntityItemID='".$this->entItemID."'".
  ($arrConfig['flagNoUpdate'] ? " AND aclActionID<>2" : "")
  ."
  ORDER BY aclInsertDate DESC, actNewStatusID DESC";
$rs = $this->oSQL->do_query($sql);
while ($rw = $this->oSQL->fetch_array($rs)) 
    $arrStatus[] = $rw;
    
$this->oSQL->free_result($rs);

$strRes = "<fieldset><legend>Status".($arrConfig['staTitle'] ? ": ".$arrConfig['staTitle'] : "")."</legend>";
$strRes .= "<div style=\"max-height:100px; overflow-y: auto;\">\r\n";
$strRes .= "<table width='100%' class='eiseIntraHistoryTable'>\r\n";

for ($i=0;$i<count($arrStatus);$i++){
    $strRes .= "<tr class='tr".($i % 2)."' valign='top'>\r\n";
    if ($arrStatus[$i]["aclActionPhase"]==2) {
        $strRes .= "<td nowrap><b>".$arrStatus[$i]["actTitlePast$strLoc"]."</b></td>\r\n";
    } else {
        $strRes .= "<td nowrap>Started &quot;<b>".$arrStatus[$i]["actTitle$strLoc"]."</b>&quot;</td>\r\n";
    }
    $strRes .= "<td nowrap>".($arrStatus[$i]["aclEditBy"] ? "by ".$this->intra->getUserData($arrStatus[$i]["aclEditBy"]) : '')."</td>";
    $strRes .= "<td nowrap>at ".date("d.m.Y H:i", strtotime($arrStatus[$i]["aclEditDate"]))."</td>";
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
    ?>
<div class="eiseIntraField">
<label><?php echo $this->intra->translate("Comments"); ?>:</label>
<div class="eiseIntraValue">
<?php 
    if ($this->intra->arrUsrData["FlagWrite"] && !$this->flagArchive){?>
<textarea class="eiseIntraComment"></textarea>
<?php
    }
    foreach ($this->rwEnt["comments"] as $ix => $rwSCM){
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
while($rwFile = $oSQL->fetch_array($rsFile)){
    ?>
<tr class="tr<?php  echo $i%2 ; ?>">
<td width="100%"><a href="popup_file.php?filGUID=<?php  echo $rwFile["filGUID"] ; ?>" target="_blank"><?php  echo $rwFile["filName"] ; ?></a></td>
<td><?php  echo $intra->getUserData($rwFile["filEditBy"]) ; ?></td>
<td><?php  echo $intra->datetimeSQL2PHP($rwFile["filEditDate"]) ; ?></td>
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