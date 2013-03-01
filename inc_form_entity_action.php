<?php 
include commonStuffAbsolutePath.'eiseGrid/inc_eiseGrid.php';
$arrJS[] = commonStuffRelativePath.'eiseGrid/eiseGrid.js';
$arrCSS[] = commonStuffRelativePath.'eiseGrid/eiseGrid.css';

$DataAction  = (isset($_POST['DataAction']) ? $_POST['DataAction'] : $_GET['DataAction'] );

$oSQL->dbname=(isset($_POST["dbName"]) ? $_POST["dbName"] : $_GET["dbName"]);
$dbName = $oSQL->dbname;


$actID = (isset($_POST["actID"]) ? $_POST["actID"] : $_GET["actID"]);

$sqlAct = "SELECT * FROM stbl_action 
  INNER JOIN stbl_entity ON actEntityID=entID
  WHERE actID='$actID'";
$rsAct = $oSQL->do_query($sqlAct);
$rwAct = $oSQL->fetch_array($rsAct);
  
$gridATS = new easyGrid($oSQL
        ,'ats'
        , Array(
                'rowNum' =>40
                , 'arrPermissions' => Array('FlagWrite'=>true)
                , 'strTable' => 'stbl_action_status'
                , 'strPrefix' => 'ats'
                , 'flagStandAlone' => true
                )
        );

$gridATS->Columns[]  = Array(
            'type' => 'row_id'
            , 'field' => 'atsID'
        );
        
$gridATS->Columns[] = Array(
        'title' => ""
        , 'field' => "atsActionID"
        , 'default' => $actID
        , 'type' => "text"
);
        
$gridATS->Columns[] = Array(
        'title' => "Old Status"
        , 'field' => "atsOldStatusID"
        , 'type' => "combobox"
        , 'sql' => "SELECT staID as optValue, staTitle as optText FROM stbl_status WHERE staEntityID='".$rwAct["actEntityID"]."'"
        , "defaultText" => "any"
        , "mandatory" => true
);

$gridATS->Columns[] = Array(
        'title' => "New Status"
        , 'field' => "atsNewStatusID"
        , 'type' => "combobox"
        , 'sql' => "SELECT staID as optValue, staTitle as optText FROM stbl_status WHERE staEntityID='".$rwAct["actEntityID"]."'"
        , "defaultText" => "any"
);

  
$gridAAT = new easyGrid($oSQL
        ,'aat'
        , Array(
                'rowNum' =>40
                , 'arrPermissions' => Array('FlagWrite'=>true)
                , 'strTable' => 'stbl_action_attribute'
                , 'strPrefix' => 'aat'
                , 'flagStandAlone' => true
                , 'flagNoDelete' => true
                )
        );

$gridAAT->Columns[]  = Array(
            'type' => 'row_id'
            , 'field' => 'aatID'
        );
$gridAAT->Columns[] = Array(
        'title' => ""
        , 'field' => "aatActionID"
        , 'type' => "text"
);

$gridAAT->Columns[] = Array(
        'title' => ""
        , 'field' => "atrID"
        , 'type' => "text"
);

$gridAAT->Columns[] = Array(
        'title' => "Attribute"
        , 'field' => "atrTitle"
        , 'type' => "text"
        , 'disabled' => true
        , 'width' => "100%"
);

$gridAAT->Columns[] = Array(
        'title' => "Track?"
        , 'field' => "aatFlagToTrack"
        , 'type' => "checkbox"
);

$gridAAT->Columns[] = Array(
        'title' => "Mandatory?"
        , 'field' => "aatFlagMandatory"
        , 'type' => "checkbox"
);


$gridAAT->Columns[] = Array(
        'title' => "ToChange?"
        , 'field' => "aatFlagToChange"
        , 'type' => "checkbox"
);

$gridAAT->Columns[] = Array(
        'title' => "EmptyOnInsert"
        , 'field' => "aatFlagEmptyOnInsert"
        , 'type' => "checkbox"
);

$gridAAT->Columns[] = Array(
        'title' => "Timestamp?"
        , 'field' => "aatFlagTimestamp"
        , 'type' => "combobox"
        , "defaultText" => "-"
        , 'arrValues' => Array("ETD"=>"ETD"
            , "ETA"=>"ETA"
            , "ATD"=>"ATD"
            , "ATA"=>"ATA")
);
switch($DataAction){
    case "clone":
        
        $oSQL->startProfiling();
        $oSQL->q("START TRANSACTION");
        
        $actID_src = $_GET["actID"];
        
        $sqlACT = "SELECT * FROM stbl_action WHERE actID=".$oSQL->e($actID_src);
        $rsACT = $oSQL->q($sqlACT);
        $rwACT = $oSQL->f($rsACT);
                
        $sqlNCopy = "SELECT COUNT(*) FROM stbl_action WHERE actTitle LIKE ".$oSQL->e($rwACT["actTitle"], "for_search");
        $nCopy = $oSQL->d($sqlNCopy);
        
        $sqlCopyAct = "INSERT INTO stbl_action (
            actEntityID
            , actOldStatusID
            , actNewStatusID
            , actTitle
            , actTitleLocal
            , actTitlePast
            , actTitlePastLocal
            , actDescription
            , actDescriptionLocal
            , actFlagDeleted
            , actPriority
            , actFlagComment
            , actShowConditions
            , actFlagHasEstimates
            , actFlagDatetime
            , actFlagAutocomplete
            , actDepartureDescr
            , actArrivalDescr
            , actFlagInterruptStatusStay
            , actInsertBy, actInsertDate, actEditBy, actEditDate
            ) SELECT 
            actEntityID
            , actOldStatusID
            , actNewStatusID
            , CONCAT(actTitle, ".$oSQL->e(" - copy {$nCopy}").")
            , CONCAT(actTitleLocal, ".$oSQL->e(" - copy {$nCopy}").")
            , CONCAT(actTitlePast, ".$oSQL->e(" - copy {$nCopy}").")
            , CONCAT(actTitlePastLocal, ".$oSQL->e(" - copy {$nCopy}").")
            , actDescription
            , actDescriptionLocal
            , actFlagDeleted
            , actPriority+1
            , actFlagComment
            , actShowConditions
            , actFlagHasEstimates
            , actFlagDatetime
            , actFlagAutocomplete
            , actDepartureDescr
            , actArrivalDescr
            , actFlagInterruptStatusStay
            , '{$intra->usrID}', NOW(), '{$intra->usrID}', NOW()
            FROM stbl_action WHERE actID=".$oSQL->e($actID_src);
        $oSQL->q($sqlCopyAct);
        $actID = $oSQL->i();
        
        //stbl_action_status
        $sqlCopyATS = "INSERT INTO stbl_action_status (
            atsActionID
            , atsOldStatusID
            , atsNewStatusID
            , atsInsertBy, atsInsertDate, atsEditBy, atsEditDate
            ) SELECT
            ".$oSQL->e($actID)." as atsActionID
            , atsOldStatusID
            , atsNewStatusID
            , '{$intra->usrID}', NOW(), '{$intra->usrID}', NOW()
            FROM stbl_action_status WHERE atsActionID=".$oSQL->e($actID_src);
        $oSQL->q($sqlCopyATS);
        
        //stbl_action_attribute
        $sqlCopyAAT = "INSERT INTO stbl_action_attribute (
            aatActionID
            , aatAttributeID
            , aatFlagMandatory
            , aatFlagToChange
            , aatFlagToAdd
            , aatFlagToPush
            , aatFlagEmptyOnInsert
            , aatFlagTimestamp
            , aatInsertBy, aatInsertDate, aatEditBy, aatEditDate
            ) SELECT
            ".$oSQL->e($actID)." as aatActionID
            , aatAttributeID
            , aatFlagMandatory
            , aatFlagToChange
            , aatFlagToAdd
            , aatFlagToPush
            , aatFlagEmptyOnInsert
            , aatFlagTimestamp
            , '{$intra->usrID}', NOW(), '{$intra->usrID}', NOW()
            FROM stbl_action_attribute WHERE aatActionID=".$oSQL->e($actID_src);
        $oSQL->q($sqlCopyAAT);
        
        //stbl_role_action
        $sqlCopyRLA = "INSERT INTO stbl_role_action (
            rlaRoleID
            , rlaActionID
            ) SELECT
            rlaRoleID
            , ".$oSQL->e($actID)." as rlaActionID
            FROM stbl_role_action WHERE rlaActionID=".$oSQL->e($actID_src);
        $oSQL->q($sqlCopyRLA);
        
        $oSQL->q("COMMIT");
        
        SetCookie("UserMessage", "Action ".$intra->translate("cloned"));
        header("Location: ".$_SERVER["PHP_SELF"]."?dbName=$dbName&actID=$actID");
        
        die();
    
    case "update":
        
        $sql[] = "UPDATE stbl_action SET
            actTitle = ".$oSQL->escape_string($_POST['actTitle'])."
            , actTitleLocal = ".$oSQL->escape_string($_POST['actTitleLocal'])."
            , actTitlePast = ".$oSQL->escape_string($_POST['actTitlePast'])."
            , actTitlePastLocal = ".$oSQL->escape_string($_POST['actTitlePastLocal'])."
            , actDescription = ".$oSQL->escape_string($_POST['actDescription'])."
            , actDescriptionLocal = ".$oSQL->escape_string($_POST['actDescriptionLocal'])."
            , actFlagDeleted = '".(integer)$_POST['actFlagDeleted']."'
            , actFlagComment = '".($_POST['actFlagComment']=='on' ? 1 : 0)."'
            , actShowConditions = ".$oSQL->escape_string($_POST['actShowConditions'])."
            , actFlagHasEstimates = '".($_POST['actFlagHasEstimates']=='on' ? 1 : 0)."'
            , actFlagAutocomplete = '".($_POST['actFlagAutocomplete']=='on' ? 1 : 0)."'
            , actFlagInterruptStatusStay = '".($_POST['actFlagInterruptStatusStay']=='on' ? 1 : 0)."'
            , actEditBy = '$usrID', actEditDate = NOW()
            WHERE actID = '".$_POST['actID']."'";
       
       $gridATS->Update();
       
       $sql[] = "DELETE FROM stbl_action_attribute WHERE aatActionID='$actID'";
       
       for ($i=0; $i< count($_POST["atrID"]); $i++)
          if ($_POST["aatFlagToTrack"][$i]){
             $sql[] = "INSERT INTO stbl_action_attribute (
                 aatActionID
                , aatAttributeID
                , aatFlagMandatory
                , aatFlagToChange
                , aatFlagEmptyOnInsert
                , aatFlagTimestamp
                , aatInsertBy, aatInsertDate, aatEditBy, aatEditDate
                ) VALUES (
                '{$actID}'
                , '".$_POST["atrID"][$i]."'
                , '".(integer)$_POST["aatFlagMandatory"][$i]."'
                , '".(integer)$_POST["aatFlagToChange"][$i]."'
                , '".(integer)$_POST["aatFlagEmptyOnInsert"][$i]."'
                , ".$oSQL->escape_string($_POST["aatFlagTimestamp"][$i])."
                , '$usrID', NOW(), '$usrID', NOW())";
                
          }
      
      $sql[] = "DELETE FROM stbl_role_action WHERE rlaActionID='".$_POST['actID']."'";
      
      $sqlROL = "SELECT * FROM stbl_role";
      $rsROL = $oSQL->do_query($sqlROL);
      while ($rwROL = $oSQL->fetch_array($rsROL)){
           if($_POST["RLA_{$rwROL["rolID"]}"]=="on")
            $sql[] = "INSERT INTO stbl_role_action (
                rlaRoleID
                , rlaActionID
                ) VALUES (
                '{$rwROL["rolID"]}'
                , '{$_POST['actID']}');";
      }
    /*
        echo "<pre>";
        print_r($sql);
        print_r($_POST);
        echo "</pre>";
        die();
     //*/
     for($i=0;$i<count($sql);$i++)
          $oSQL->do_query($sql[$i]);
          
       SetCookie("UserMessage", "Action ".$intra->translate("is updated"));
       header("Location: ".$_SERVER["PHP_SELF"]."?dbName=$dbName&actID=$actID");
       
     
        break;
    default:
        break;
}


$arrActions[]= Array ('title' => $rwAct["entTitle"]
	   , 'action' => "entity_form.php?dbName=$dbName&entID=".$rwAct["entID"]
	   , 'class'=> 'ss_arrow_left'
	);

if ($easyAdmin){
    $arrActions[]= Array ("title" => "Clone"
	   , "action" => "action_form.php?DataAction=clone&dbName={$dbName}&actID=".urlencode($actID)
	   , "class"=> "ss_page_copy"
	);
}
include('../common/eiseIntra/inc-frame_top.php');
?>
<script>
$(document).ready(function(){  
	easyGridInitialize();
});
</script>

<h1><?php  echo $rwAct["actTitle"] ; ?></h1>


<div class="panel">
<form action="<?php  echo $_SERVER["PHP_SELF"] ; ?>" method="POST">
<input type="hidden" name="DataAction" value="update">
<input type="hidden" name="dbName" value="<?php  echo $dbName ; ?>">
<input type="hidden" name="actID" value="<?php  echo $actID ; ?>">

<table width="100%">

<tr>
<td width="50%">

<table width="100%">
<tr>
<td class="field_title">Title:</td>
<td><?php  echo $intra->showTextBox("actTitle", $rwAct["actTitle"]) ; ?></td>
</tr>
<tr>
<td class="field_title">Title Local:</td>
<td><?php  echo $intra->showTextBox("actTitleLocal", $rwAct["actTitleLocal"]) ; ?></td>
</tr>
<tr>
<td class="field_title">Status shift:<br>
<a href="#" onclick="easyGridAddRow('ats');">Add &gt;&gt;</a></td>
<td><?php  
$sqlATS = "SELECT * FROM stbl_action_status WHERE atsActionID='{$rwAct["actID"]}'";
$rsATS = $oSQL->do_query($sqlATS);
while ($rwATS = $oSQL->fetch_array($rsATS)){
   $gridATS->Rows[] = $rwATS;
}
$gridATS->Execute();
?>
</tr>

<tr>
<td class="field_title">Title Past Tense:</td>
<td><?php  echo $intra->showTextBox("actTitlePast", $rwAct["actTitlePast"]) ; ?></td>
</tr>
<tr>
<td class="field_title">Title Past Tense Local:</td>
<td><?php  echo $intra->showTextBox("actTitlePastLocal", $rwAct["actTitlePastLocal"]) ; ?></td>
</tr>
<tr>
<td class="field_title">Description:</td>
<td><?php  echo $intra->showTextArea("actDescription", $rwAct["actDescription"]) ; ?></td>
</tr>
<tr>
<td class="field_title">Description Local:</td>
<td><?php  echo $intra->showTextArea("actDescriptionLocal", $rwAct["actDescriptionLocal"]) ; ?></td>
</tr>

<tr>
<td class="field_title">Can be run by:</td>
<td><?php  
$sqlRol = "SELECT * FROM stbl_role LEFT OUTER JOIN stbl_role_action ON rlaActionID=$actID AND rolID=rlaRoleID";
$rsRol = $oSQL->do_query($sqlRol);
while ($rwRol = $oSQL->fetch_array($rsRol)){
   ?>
   <input type="checkbox" id="RLA_<?php  echo $rwRol["rolID"] ; ?>" 
   style="width:auto;"
   name="RLA_<?php  echo $rwRol["rolID"] ; ?>"<?php  echo ($rwRol["rlaID"] ? " checked" : "") ; ?>>
   <label for="RLA_<?php  echo $rwRol["rolID"] ; ?>"><?php  echo $rwRol["rolID"] ; ?></label><br>
   <?php
} 
?></td>
</tr>

<tr>
<td class="field_title">Require Comment:</td>
<td><?php  echo $intra->showCheckBox("actFlagComment", $rwAct["actFlagComment"]) ; ?></td>
</tr>
<tr><td colspan=2><hr></td></tr>
<tr>
<td class="field_title">Autocomplete?</td>
<td><?php  echo $intra->showCheckBox("actFlagAutocomplete", $rwAct["actFlagAutocomplete"]) ; ?></td>
</tr>
<tr>
<td class="field_title">Has Estimates?</td>
<td><?php  echo $intra->showCheckBox("actFlagHasEstimates", $rwAct["actFlagHasEstimates"]) ; ?></td>
</tr>
<tr>
<td class="field_title">Interrupt Status Stay?</td>
<td><?php  echo $intra->showCheckBox("actFlagInterruptStatusStay", $rwAct["actFlagInterruptStatusStay"]) ; ?></td>
</tr>
<tr><td colspan=2><hr></td></tr>
<tr>
<td class="field_title">Deleted?</td>
<td><?php  echo $intra->showCheckBox("actFlagDeleted", $rwAct["actFlagDeleted"]) ; ?></td>
</tr>

</table>

</td>

<td width="50%">
<?php
$sqlAAT = "SELECT *
, CASE WHEN aatID IS NULL THEN 0 ELSE 1 END as aatFlagToTrack
FROM stbl_attribute LEFT OUTER JOIN stbl_action_attribute ON atrID=aatAttributeID AND aatActionID='$actID'
WHERE atrEntityID='".$rwAct["actEntityID"]."'
ORDER BY atrOrder";
$rsAAT = $oSQL->do_query($sqlAAT);
while ($rwAAT = $oSQL->fetch_array($rsAAT)){
    $gridAAT->Rows[] = $rwAAT;
}

$gridAAT->Execute();
?>
</td>
</tr>
<tr>
<td colspan="2" align="center">
<input type="submit" value="Save">
</td>
</tr>
</table>
</form>


<?php
include eiseIntraAbsolutePath."inc-frame_bottom.php";
 ?>