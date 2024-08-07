<?php 
include 'inc_actionmatrix.php';
$intra->requireComponent('jquery-ui', 'grid');

$DataAction  = (isset($_POST['DataAction']) ? $_POST['DataAction'] : $_GET['DataAction'] );

$actID = (isset($_POST["actID"]) ? $_POST["actID"] : $_GET["actID"]);

$sqlAct = "SELECT * FROM stbl_action 
  INNER JOIN stbl_entity ON actEntityID=entID
  WHERE actID='$actID'";
$rsAct = $oSQL->do_query($sqlAct);
$rwAct = $oSQL->fetch_array($rsAct);

$fields = $oSQL->ff("SELECT * FROM stbl_action WHERE 1=0");

$gridATS = new easyGrid($oSQL
        ,'ats'
        , Array(
                'rowNum' =>40
                , 'arrPermissions' => Array('FlagWrite'=>$intra->arrUsrData["FlagWrite"])
                , 'strTable' => 'stbl_action_status'
                , 'strPrefix' => 'ats'
                , 'flagStandAlone' => true
                , 'controlBarButtons' => 'add|moveup|movedown|delete'
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
        'title' => $intra->translate("##")
        , 'field' => "atsOrder"
        , 'type' => "order"
);        
$gridATS->Columns[] = Array(
        'title' => $intra->translate("Old Status")
        , 'field' => "atsOldStatusID"
        , 'type' => "combobox"
        , 'sql' => "SELECT staID as optValue, staTitle as optText FROM stbl_status WHERE staEntityID='".$rwAct["actEntityID"]."'"
        , "defaultText" => "any"
        , "mandatory" => true
        , 'width' => '100%'
);

$gridATS->Columns[] = Array(
        'title' => ''
        , 'field' => "atsNewStatusID"
);

  
$gridAAT = new easyGrid($oSQL
        ,'aat'
        , Array(
                'rowNum' =>40
                , 'arrPermissions' => Array('FlagWrite'=>$intra->arrUsrData["FlagWrite"])
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
        'title' => "Field"
        , 'field' => "atrID"
        , 'type' => "text"
        , 'static' => true
);

$gridAAT->Columns[] = Array(
        'title' => $intra->translate("Attribute")
        , 'field' => "atrTitle"
        , 'type' => "text"
        , 'static' => true
        , 'mandatory' =>  true
        , 'width' => "100%"
);
$gridAAT->Columns[] = Array(
        'title' => "Type"
        , 'field' => "atrType"
        , 'type' => "text"
        , 'static' => true
);
$gridAAT->Columns[] = Array(
        'title' => $intra->translate("Track?")
        , 'field' => "aatFlagToTrack"
        , 'type' => "checkbox"
        , 'filterable' => true
);

$gridAAT->Columns[] = Array(
        'title' => $intra->translate("Mandatory?")
        , 'field' => "aatFlagMandatory"
        , 'type' => "checkbox"
        , 'filterable' => true
);


$gridAAT->Columns[] = Array(
        'title' => $intra->translate("ToChange?")
        , 'field' => "aatFlagToChange"
        , 'type' => "checkbox"
        , 'filterable' => true
);

$gridAAT->Columns[] = Array(
        'title' => $intra->translate("EmptyOnInsert")
        , 'field' => "aatFlagEmptyOnInsert"
        , 'type' => "checkbox"
);
$gridAAT->Columns[] = Array(
        'title' => $intra->translate("UserStamp")
        , 'field' => "aatFlagUserStamp"
        , 'type' => "checkbox"
        , 'filterable' => true
);

$gridAAT->Columns[] = Array(
        'title' => $intra->translate("Timestamp?")
        , 'field' => "aatFlagTimestamp"
        , 'type' => "select"
        , "defaultText" => "-"
        , 'arrValues' => Array("ETD"=>"ETD"
            , "ETA"=>"ETA"
            , "ATD"=>"ATD"
            , "ATA"=>"ATA")
        , 'filterable' => true
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
            , actTrackPrecision
            , actFlagDeleted
            , actPriority
            , actFlagComment
            , actShowConditions
            , actFlagHasEstimates
            , actFlagAutocomplete
            "
            .(in_array('actFlagDepartureEqArrival', array_keys($fields)) ? ", actFlagDepartureEqArrival" : '')
            .(in_array('actFlagHasDeparture', array_keys($fields)) ? ", actFlagHasDeparture" : '')
            ."
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
            , actTrackPrecision
            , actFlagDeleted
            , actPriority+1
            , actFlagComment
            , actShowConditions
            , actFlagHasEstimates
            , actFlagAutocomplete
            "
            .(in_array('actFlagDepartureEqArrival', array_keys($fields)) ? ", actFlagDepartureEqArrival" : '')
            .(in_array('actFlagHasDeparture', array_keys($fields)) ? ", actFlagHasDeparture" : '')
            ."
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
            , aatFlagToTrack
            , aatFlagToAdd
            , aatFlagToPush
            , aatFlagEmptyOnInsert
            , aatFlagUserStamp
            , aatFlagTimestamp
            , aatInsertBy, aatInsertDate, aatEditBy, aatEditDate
            ) SELECT
            ".$oSQL->e($actID)." as aatActionID
            , aatAttributeID
            , aatFlagMandatory
            , aatFlagToChange
            , aatFlagToTrack
            , aatFlagToAdd
            , aatFlagToPush
            , aatFlagEmptyOnInsert
            , aatFlagUserStamp
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


        $oSQL->q("START TRANSACTION");

        $oSQL->startProfiling();
        
        $sqlUpd = "UPDATE stbl_action SET
            actTitle = ".$oSQL->escape_string($_POST['actTitle'])."
            , actTitleLocal = ".$oSQL->escape_string($_POST['actTitleLocal'])."
            , actButtonClass = ".$oSQL->escape_string($_POST['actButtonClass'])."
            , actPriority = ".(int)($_POST['actPriority'])."
            , actTitlePast = ".$oSQL->escape_string($_POST['actTitlePast'])."
            , actTitlePastLocal = ".$oSQL->escape_string($_POST['actTitlePastLocal'])."
            , actOldStatusID = ".(isset($_POST['atsOldStatusID'][1]) ? (int)$_POST['atsOldStatusID'][1] : 'NULL')."
            , actNewStatusID = ".($_POST['actNewStatusID']!=='' ? (int)($_POST['actNewStatusID']) : 'NULL')."
            , actDescription = ".$oSQL->escape_string($_POST['actDescription'])."
            , actDescriptionLocal = ".$oSQL->escape_string($_POST['actDescriptionLocal'])."
            , actFlagDeleted = '".(integer)$_POST['actFlagDeleted']."'
            , actFlagComment = '".($_POST['actFlagComment']=='on' ? 1 : 0)."'
            , actShowConditions = ".$oSQL->escape_string($_POST['actShowConditions'])."
            , actTrackPrecision = ".$oSQL->escape_string($_POST['actTrackPrecision'])."
            , actFlagHasEstimates = '".($_POST['actFlagHasEstimates']=='on' ? 1 : 0)."'
            , actFlagAutocomplete = '".($_POST['actFlagAutocomplete']=='on' ? 1 : 0)."'
            "
            .(in_array('actFlagDepartureEqArrival', array_keys($fields)) ? ", actFlagDepartureEqArrival = '".($_POST['actFlagDepartureEqArrival']=='on' ? 1 : 0)."'" : '')
            .(in_array('actFlagHasDeparture', array_keys($fields)) ? ", actFlagHasDeparture = '".($_POST['actFlagHasDeparture']=='on' ? 1 : 0)."'" : '')
            ."
            , actFlagInterruptStatusStay = '".($_POST['actFlagInterruptStatusStay']=='on' ? 1 : 0)."'
            , actFlagMultiple = '".($_POST['actFlagMultiple']=='on' ? 1 : 0)."'
            , actFlagNot4Editor = '".($_POST['actFlagNot4Editor']=='on' ? 1 : 0)."'
            , actFlagNot4Creator = '".($_POST['actFlagNot4Creator']=='on' ? 1 : 0)."'
            , actFlagSystem = '".($_POST['actFlagSystem']=='on' ? 1 : 0)."'
            , actFlagDeleted = '".($_POST['actFlagDeleted']=='on' ? 1 : 0)."'
            , actEditBy = '$usrID', actEditDate = NOW()
            WHERE actID = '".$_POST['actID']."'";
        $oSQL->q($sqlUpd);



        $mtx = new eiseActionMatrix($rwAct['entID']);
        $mtx->saveActionGrid($_POST['actID'], $_POST);

        if ($oSQL->f("SHOW TABLES LIKE 'stbl_action_status'")){
            $aToDel = explode('|', $_POST['inp_ats_deleted']);
            $strToDel = '';
            foreach ($aToDel as $atsID) {
                if($atsID)
                    $oSQL->q("DELETE FROM stbl_action_status WHERE atsID=".$oSQL->e($atsID));
            }
            foreach ($_POST['atsID'] as $i => $val) {
                if($i==0) continue;
                $fields = "atsOrder=".(int)$_POST['atsOrder'][$i]."
                    , atsActionID=".(int)$_POST['actID']."
                    , atsOldStatusID=".($_POST['atsOldStatusID'][$i]!=='' ? $oSQL->e($_POST['atsOldStatusID'][$i]) : 'NULL')."
                    , atsNewStatusID=".($_POST['actNewStatusID']!=='' ? $oSQL->e($_POST['actNewStatusID']) : 'NULL');
                if($_POST['atsID'][$i]){
                    $sql = "UPDATE stbl_action_status SET
                        {$fields}
                        WHERE atsID=".$oSQL->e($_POST['atsID'][$i]);
                } else {
                    $sql = "INSERT INTO stbl_action_status SET
                        {$fields}";

                }
                $oSQL->q($sql);
            }
        }
        unset($sql);
       
        $sql[] = "DELETE FROM stbl_action_attribute WHERE aatActionID='$actID'";
        for ($i=0; $i< count($_POST["atrID"]); $i++)
            if ($_POST["atrID"][$i]) {
             $sql[] = "INSERT INTO stbl_action_attribute (
                 aatActionID
                , aatAttributeID
                , aatFlagToTrack
                , aatFlagMandatory
                , aatFlagToChange
                , aatFlagEmptyOnInsert
                , aatFlagUserStamp
                , aatFlagTimestamp
                , aatInsertBy, aatInsertDate, aatEditBy, aatEditDate
                ) VALUES (
                '{$actID}'
                , '".$_POST["atrID"][$i]."'
                , '".(integer)$_POST["aatFlagToTrack"][$i]."'
                , '".(integer)$_POST["aatFlagMandatory"][$i]."'
                , '".(integer)$_POST["aatFlagToChange"][$i]."'
                , '".(integer)$_POST["aatFlagEmptyOnInsert"][$i]."'
                , '".(integer)$_POST["aatFlagUserStamp"][$i]."'
                , ".$oSQL->escape_string($_POST["aatFlagTimestamp"][$i])."
                , '$usrID', NOW(), '$usrID', NOW())";
                
        }
        

        if ($oSQL->f("SHOW TABLES LIKE 'stbl_role_action'")){
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
        
        $oSQL->q("COMMIT");
        
        SetCookie("UserMessage", "Action ".$intra->translate("is updated"));
        header("Location: ".$_SERVER["PHP_SELF"]."?dbName=$dbName&actID=$actID");
       
     
        break;

    case 'delete':

        $oSQL->q('START TRANSACTION');

        $oSQL->startProfiling();

        if ($oSQL->f("SHOW TABLES LIKE 'stbl_action_status'")){
            $oSQL->q('DELETE FROM stbl_action_status WHERE atsActionID='.$oSQL->e($actID)) ;
        }
        
        
        $oSQL->q("DELETE FROM stbl_action_attribute WHERE aatActionID=".$oSQL->e($actID));

        if ($oSQL->f("SHOW TABLES LIKE 'stbl_role_action'")){
            $oSQL->q("DELETE FROM stbl_role_action WHERE rlaActionID='".$_POST['actID']."'");
        }

        if($oSQL->d("SHOW TABLES LIKE '{$rwAct['entTable']}_matrix'")) {
            $oSQL->q("DELETE FROM {$rwAct['entTable']}_matrix WHERE mtxEventID=".$oSQL->e($actID));
        }

        $oSQL->q("DELETE FROM stbl_action WHERE actID=".$oSQL->e($actID));


        // $oSQL->showProfileInfo();

        $oSQL->q('COMMIT');

        SetCookie("UserMessage", "Action ".$intra->translate("is updated"));
        header("Location: entity_form.php?dbName=$dbName&entID={$rwAct['entID']}");
        
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
include eiseIntraAbsolutePath."inc_top.php";
?>
<script>
$(document).ready(function(){  
    $('.eiseGrid').eiseGrid();
    $('.eiseIntraDelete').click(function(){
        if(!confirm('Really delele?'))
            return false;
        $(':input[required]:visible').each(function(){
            $(this).removeAttr('required');
        });
        $('input[name="DataAction"]').val('delete');
        return true;
    })
});
</script>

<form action="<?php  echo $_SERVER["PHP_SELF"] ; ?>" method="POST" class="eiseIntraForm">
<input type="hidden" name="DataAction" value="update">
<input type="hidden" name="dbName" value="<?php  echo $dbName ; ?>">
<input type="hidden" name="actID" value="<?php  echo $actID ; ?>">

<fieldset><legend><?php  echo $rwAct["actTitle{$intra->local}"] ; ?></legend>

<table width="100%">

<tr>
<td width="50%">

<div class="eiseIntraField"><label><?php echo $intra->translate("Title") ?>:</label>
<?php  echo $intra->showTextBox("actTitle", $rwAct["actTitle"]) ; ?>
</div>

<div class="eiseIntraField"><label><?php echo $intra->translate("Title Local") ?>:</label>
<?php  echo $intra->showTextBox("actTitleLocal", $rwAct["actTitleLocal"]) ; ?>
</div>

<?php 
if ($oSQL->f("SHOW TABLES LIKE 'stbl_action_status'")){
    $sqlATS = "SELECT * FROM stbl_action_status WHERE atsActionID='{$rwAct["actID"]}'";
    $rsATS = $oSQL->do_query($sqlATS);
    while ($rwATS = $oSQL->fetch_array($rsATS)){
       $gridATS->Rows[] = $rwATS;
    }
    $fldOrigin = $intra->field($intra->translate("Origin statuses"), null, $gridATS->get_html());    
} else {
    $fldOrigin = $intra->field($intra->translate("Origin status"), 'actOldStatusID', $rwAct, array('type'=>'combobox', 'source'=>"SELECT staID as optValue, staTitle as optText FROM stbl_status WHERE staEntityID='".$rwAct["actEntityID"]."'", 'defaultText'=>'-'));
}

echo $fldOrigin; 
echo $intra->field($intra->translate("Dest status"), 'actNewStatusID', $rwAct, array('type'=>'combobox', 'source'=>"SELECT staID as optValue, staTitle as optText FROM stbl_status WHERE staEntityID='".$rwAct["actEntityID"]."'", 'defaultText'=>'same'));

$mtx = new eiseActionMatrix($rwAct['entID']);

echo $intra->field($intra->translate("Action Matrix"), null);

echo $mtx->actionGrid($rwAct['actID']);
?>


<div class="eiseIntraField"><label><?php echo $intra->translate("Title Past Tense") ?>:</label>
<?php  echo $intra->showTextBox("actTitlePast", $rwAct["actTitlePast"]) ; ?>
</div>

<div class="eiseIntraField"><label><?php echo $intra->translate("Title Past Tense Local") ?>:</label>
<?php  echo $intra->showTextBox("actTitlePastLocal", $rwAct["actTitlePastLocal"]) ; ?>
</div>

<div class="eiseIntraField"><label><?php echo $intra->translate("Description") ?>:</label>
<?php  echo $intra->showTextArea("actDescription", $rwAct["actDescription"]) ; ?>
</div>

<div class="eiseIntraField"><label><?php echo $intra->translate("Description Local") ?>:</label>
<?php  echo $intra->showTextArea("actDescriptionLocal", $rwAct["actDescriptionLocal"]) ; ?>
</div>


<?php if ($oSQL->f("SHOW TABLES LIKE 'stbl_role_action'")): ?>

<div class="eiseIntraField"><label><?php echo $intra->translate("Can be run by") ?>:</label>
<div class="eiseIntraValue">
<?php  
    $sqlRol = "SELECT * FROM stbl_role LEFT OUTER JOIN stbl_role_action ON rlaActionID=$actID AND rolID=rlaRoleID";
    $rsRol = $oSQL->do_query($sqlRol);
    while ($rwRol = $oSQL->fetch_array($rsRol)){
       ?>
       <input type="checkbox" id="RLA_<?php  echo $rwRol["rolID"] ; ?>" 
       style="width:auto;"
       name="RLA_<?php  echo $rwRol["rolID"] ; ?>"<?php  echo ($rwRol["rlaID"] ? " checked" : "") ; ?>>
       <label for="RLA_<?php  echo $rwRol["rolID"] ; ?>"><?php  echo $rwRol["rolTitle{$intra->local}"] ; ?></label><br>
       <?php
    } 

?>
</div>
</div>
<?php endif; ?>

<div class="eiseIntraField"><label><?php echo $intra->translate("Require Comment") ?>:</label>
<?php  echo $intra->showCheckBox("actFlagComment", $rwAct["actFlagComment"]) ; ?>
</div>

<hr>

<div class="eiseIntraField"><label><?php echo $intra->translate("Autocomplete?") ?>:</label>
<?php  echo $intra->showCheckBox("actFlagAutocomplete", $rwAct["actFlagAutocomplete"]) ; ?>
</div>

<?php if (in_array('actFlagDepartureEqArrival', array_keys($fields))): ?>
<div class="eiseIntraField"><label>ATD=ATA?:</label>
<?php  echo $intra->showCheckBox("actFlagDepartureEqArrival", $rwAct["actFlagDepartureEqArrival"]) ; ?>
</div>
<?php endif ?>

<?php if (in_array('actFlagHasDeparture', array_keys($fields))): ?>
<div class="eiseIntraField"><label>ATD(ETD)?:</label>
<?php  echo $intra->showCheckBox("actFlagHasDeparture", $rwAct["actFlagHasDeparture"]) ; ?>
</div>
<?php endif ?>

<div class="eiseIntraField"><label>ETA/ETD?:</label>
<?php  echo $intra->showCheckBox("actFlagHasEstimates", $rwAct["actFlagHasEstimates"]) ; ?>
</div>

<div class="eiseIntraField"><label><?php echo $intra->translate("Interrupt Status Stay?") ?>:</label>
<?php  echo $intra->showCheckBox("actFlagInterruptStatusStay", $rwAct["actFlagInterruptStatusStay"]) ; ?>
</div>

<div class="eiseIntraField"><label><?php echo $intra->translate("Precision") ?>:</label>
<?php  echo $intra->showCombo("actTrackPrecision", $rwAct["actTrackPrecision"], Array("date"=>$intra->translate("Date")
    , "datetime"=>$intra->translate("Date+Time"))) ; ?>
</div>

<?php 
echo $intra->field($intra->translate("Run multiple?"), 'actFlagMultiple', $rwAct, array('type'=>'checkbox'));
echo $intra->field($intra->translate("Not for creator?"), 'actFlagNot4Creator', $rwAct, array('type'=>'checkbox'));
echo $intra->field($intra->translate("Not for last editor?"), 'actFlagNot4Editor', $rwAct, array('type'=>'checkbox'));

 ?>
<hr>

<div class="eiseIntraField"><label><?php echo $intra->translate("CSS Class") ?>:</label>
<?php  echo $intra->showTextBox("actButtonClass", $rwAct["actButtonClass"]) ; ?>
</div>
<div class="eiseIntraField"><label><?php echo $intra->translate("Priority") ?>:</label>
<?php  echo $intra->showTextBox("actPriority", $rwAct["actPriority"]) ; ?>
</div>


<hr>


<div class="eiseIntraField"><label><?php echo $intra->translate("System?") ?>:</label>
<?php  echo $intra->showCheckBox("actFlagSystem", $rwAct["actFlagSystem"]) ; ?>
</div>
<div class="eiseIntraField"><label><?php echo $intra->translate("Deleted?") ?>:</label>
<?php  echo $intra->showCheckBox("actFlagDeleted", $rwAct["actFlagDeleted"]) ; ?>
</div>

</td>

<td width="50%">
<?php
$sqlAAT = "SELECT *
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
<input type="submit" value="<?php echo $intra->translate('Save') ?>" class="eiseIntraSubmit">
<input type="submit" value="<?php echo $intra->translate('Delete') ?>" class="eiseIntraDelete">
</td>
</tr>
</table>
</fieldset>
</form>


<?php
include eiseIntraAbsolutePath."inc_bottom.php";
 ?>