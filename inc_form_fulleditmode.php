<?php
include eiseIntraAbsolutePath."inc_entity_item_form.php";
$arrJS[] = eiseIntraRelativePath."action.js";

$arrJS[] = jQueryUIRelativePath."js/jquery-ui-1.8.16.custom.min.js";
$arrCSS[] = jQueryUIRelativePath."css/redmond/jquery-ui-1.8.16.custom.css";

include("../common/eiseGrid/inc_eiseGrid.php");
$arrJS[] = '../common/eiseGrid/eiseGrid.js';
$arrCSS[] = '../common/eiseGrid/eiseGrid.css';


$entID = (isset($_POST["entID"]) ? $_POST["entID"] : $_GET["entID"]) ;
$entItemID = (isset($_POST["ID"]) ? $_POST["ID"] : $_GET["ID"]) ;

$DataAction  = (isset($_POST['DataAction']) ? $_POST['DataAction'] : $_GET['DataAction'] );

try {
    
    $entItem = new eiseEntityItemForm($oSQL, $intra, $entID, $entItemID);
    
} catch(Exception $e){
    $ent = new eiseEntity($oSQL, $intra, $entID);
    SetCookie("UserMessage", "ERROR:".$e->getMessage());
    header("Location: {$ent->rwEnt["entScriptPrefix"]}_list.php");
    die();
}


switch ($DataAction){
    case "update":
        
        // here we'll run 'Update' action with all data update
        $_POST["actID"] = 2;
        $_POST["aclComments"] = "Full Edit Mode update";
        
        $oSQL->do_query("START TRANSACTION");
        
        $entItem->updateMasterTable($_POST, false, true);
        
        foreach($entItem->rwEnt["ACL"] as $aclGUID=>$arrACL){
            $entItem->updateActionLogItem($aclGUID, $arrACL);
        }
        
        foreach($entItem->rwEnt["STL"] as $stlGUID => $rwSTL){
            
            $entItem->updateStatusLogItem($stlGUID, $rwSTL);

            if (isset($rwSTL["ACL"]))
            foreach ($rwSTL["ACL"] as $aclGUID=>$rwNAct){
               $entItem->updateActionLogItem($aclGUID, $rwNAct);
            }
            
            $entItem->updateActionLogItem($rwSTL["stlArrivalAction"]["aclGUID"], $rwSTL["stlArrivalAction"]);
            
        }

        $entItem->prepareActions();
        $entItem->addAction();
        $entItem->finishAction();
        
        $oSQL->do_query("COMMIT");
        
        SetCookie("UserMessage", "{$entItem->rwEnt["entTitle"]} updated");
        header("Location: ".$_SERVER["PHP_SELF"]."?ID=".urlencode($entItemID)."&entID={$entID}");
        die();
        
	case "undo";
        
        foreach($entItem->rwEnt["ACL"] as $aclGUID=>$arrACL){
            if ($arrACL["aclActionPhase"] < 2){
                SetCookie("UserMessage", "ERROR: ".$entItem->rwEnt["entTitle{$intra->local}"]." ".$intra->translate('has incomplete actions. Cancel them first.'));
                header("Location: ".$_SERVER["PHP_SELF"]."?ID=".urlencode($entItemID)."&entID={$entID}");
                die();
            }
        }
        
        //$oSQL->startProfiling();
        $oSQL->do_query("START TRANSACTION");
        
        // gathering action data we'd like to cancel
        $aclGUID = $entItem->rwEnt[$entID."ActionLogID"]; //last action ID stamped at rwEnt
        
        // compose pseudo-post array
        $arrPseudoPOST = Array('aclGUID'=>$aclGUID // action log GUID
            , 'aclToDo'=>'cancel' // what do we do with this action
            , 'isUndo'=>true); // flags that action was 'undoed'
        
        $entItem->doFullAction($arrPseudoPOST);
        
        $oSQL->do_query("COMMIT");
        SetCookie("UserMessage", "{$entItem->rwEnt["entTitle"]} was rolled back to its previous state");
        header("Location: ".$_SERVER["PHP_SELF"]."?ID=".urlencode($entItemID)."&entID={$entID}");
        //$oSQL->showProfileInfo();
        
        die();
        
    default:
        break;
}
    

$arrActions[]= Array ("title" => $intra->translate("Normal edit mode")
       , "action" => "index.php?pane={$entItem->rwEnt["entScriptPrefix"]}_form.php?{$entID}ID=".urlencode($entItemID)
       , "class"=> "ss_arrow_left"
       , "target" => "_top"
    );

    
if ($intra->arrUsrData['FlagWrite']) {
$arrActions[]= Array ("title" => $intra->translate("Undo last Action")
      // , "action" => $_SERVER["PHP_SELF"]."?ID=".urlencode($entItemID)."&entID={$entID}&DataAction=undo"
       , "action" => "javascript:confirmUndo()"
       , "class"=> "ss_arrow_undo"
    );
$arrActions[]= Array ("title" => $intra->translate("Update")." ".$entItem->rwEnt["entTitle{$intra->local}"]
       , "action" => 'javascript:save()'
       , "class"=> "ss_accept save_button"
    );
}
include eiseIntraAbsolutePath."inc-frame_top.php";
?>

<style>
input, select {
width: 150px;
}
.intraFieldTitle {
width:100px;
}

#btnSubmit {
width: 60%
}

.intraFieldValue {
white-space: nowrap;
padding-left: 100px;
}

.save_button {
    font-weight: bold;
}
</style>

<script>
$(document).ready(function(){  
	$('.eiseIntraForm').eiseIntraForm();
});

function confirmUndo(){
    if (confirm($('#undoWarning').val())){
        location.href = location.href+'&DataAction=undo';
    }
}
function save(){
    $('.eiseIntraForm').submit();
}
</script>

<form action="<?php  echo $_SERVER["PHP_SELF"] ; ?>" method="POST" id="entForm" class="eiseIntraForm">
<input type="hidden" name="DataAction" id="DataAction" value="update">
<input type="hidden" name="entID" id="entID" value="<?php  echo $entID ; ?>">
<input type="hidden" name="ID" id="ID" value="<?php  echo $entItem->rwEnt["{$entID}ID"] ; ?>">
<input type="hidden" id="undoWarning" value="<?php  echo $intra->translate('WARNING: Undo will erase all data related to last action. Are you sure?') ; ?>">

<div class="panel">
<table width="100%">

<tr>
<td width="50%">
<h1><?php  echo $entItem->rwEnt["entTitle{$intra->local}"].": ".$entItemID ; ?></h1>
<?php 
$entItem->showEntityItemFields(Array("flagFullEdit" => true));
 ?>
</td>
<td width="50%">

<fieldset><legend>Activity Log & Tracked Data</legend>
<!--
<div class="intraFormRow tr0">
<div class="intraFieldTitle">Current Status:</div><div class="intraFieldValue"><?php 
    $rsCMB = $oSQL->do_query("SELECT * FROM stbl_status WHERE staEntityID=".$oSQL->e($entID)." ORDER BY staID");
    while($rwCMB = $oSQL->fetch_array($rsCMB))
        $arrOptions[$rwCMB["staID"]]=$rwCMB["staTitle"];
    echo $intra->showCombo($entID."StatusID", $entItem->rwEnt[$entID."StatusID"], $arrOptions);
 ?></div></div>
 
 <hr>
 -->

<?php
foreach($entItem->rwEnt["STL"] as $stlGUID => $rwSTL){
    ?>
    <div class="eiseIntraLogStatus">
    <div class="eiseIntraLogTitle"><span class="eiseIntra_stlTitle"><?php echo ($rwSTL["stlTitle{$intra->local}"]!="" 
        ? $rwSTL["stlTitle{$intra->local}"]
        : $rwSTL["staTitle"]); ?></span>
<?php 
    echo $intra->showTextBox("stlATA_{$rwSTL['stlGUID']}"
        , $rwSTL['stlPrecision']=='date' ? $intra->dateSQL2PHP($rwSTL["stlATA"]) : $intra->datetimeSQL2PHP($rwSTL["stlATA"])
        , Array('class'=>'eiseIntra_stlATA', 'type'=>$rwSTL['stlPrecision']));
    echo $intra->showTextBox("stlATD_{$rwSTL['stlGUID']}"
        , $rwSTL['stlPrecision']=='date' ? $intra->dateSQL2PHP($rwSTL["stlATD"]) : $intra->datetimeSQL2PHP($rwSTL["stlATD"])
        , Array('class'=>'eiseIntra_stlATD', 'type'=>$rwSTL['stlPrecision']));
?>
            
    </div>
    <div class="eiseIntraLogData">
    <?php
    if (isset($rwSTL["ACL"]))
    foreach ($rwSTL["ACL"] as $rwNAct){
       $rwNAct['aclFlagEditable']=true;
       $entItem->showActionInfo($rwNAct);
    }
    
    // linked attributes
    if (isset($rwSTL["SAT"]))
    foreach($rwSTL["SAT"] as $atrID => $rwATV){
        $rwATV["satFlagEditable"] = true;
        ?>
        <div class="eiseIntraField"><label><?php  echo $rwATV["atrTitle{$intra->local}"] ; ?>:</label>
        <?php 
           echo $entItem->showAttributeValue($rwATV, "_".$rwSTL["stlGUID"]); ?>&nbsp;
        </div>
        <?php
    }
    
    ?>    
    </div>
    </div>
    
    <?php
    $rwSTL['stlArrivalAction']['aclFlagEditable'] = true;
    $entItem->showActionInfo($rwSTL["stlArrivalAction"]);
}
 ?>
</fieldset>

 <fieldset class="eiseIntraActions eiseIntraSubForm"><legend><?php  echo $intra->translate("Incomplete/Cancelled Actions") ; ?></legend>
    <?php 
    foreach ($entItem->rwEnt["ACL"] as $ix=>$rwACL){
        
        $rwACL["actTitle{$intra->local}"] .= ' *'.eiseEntity::getActionPhaseTitle($rwACL["aclActionPhase"]).'* ';
        $rwACL["aclFlagEditable"] = true;
            
        $entItem->showActionInfo($rwACL, $actionCallBack);
                
    }
?>
</fieldset>


</td>
</tr>

</table>
</div>



<?php
include("../common/inc-frame_bottom.php");
?>