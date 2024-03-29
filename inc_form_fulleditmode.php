<?php
include_once eiseIntraAbsolutePath."inc_item_traceable.php";

$intra->requireComponent('batch');

///*
try {
    $item = new eiseItemTraceable( $_GET['ID'], array('entID'=>($_POST['entID'] ? $_POST['entID'] : $_GET['entID'])) );
    $intra->arrUsrData['FlagWrite'] = ($item->conf['entManagementRoles'] ? $item->conf['flagSuperuser'] : $intra->arrUsrData['FlagWrite']);
    if(!$intra->arrUsrData['FlagWrite'])
        throw new Exception("Full Edit Mode is not allowed for user {$intra->usrID}".($item->conf['entManagementRoles'] ? ", ask someone from roles {$item->conf['entManagementRoles']}" : ''), 1);
        
    $item->conf['logTable'] = $oSQL->d("SHOW TABLES LIKE '{$item->conf['table']}_log'") ;
} catch (Exception $e) {
    $intra->redirect('ERROR: '.$e->getMessage(), $intra->backref(false));
}

$intra->dataAction('updateFullEdit', $item);
$intra->dataAction('superaction', $item);
$intra->dataAction('undo', $item);
$intra->dataAction('undoEdit', $item);
$intra->dataAction('remove_acl', $item);
$intra->dataAction('remove_stl', $item);
$intra->dataAction('backup', $item);
$intra->dataAction('restore', $item);

$arrActions[]= Array ("title" => $intra->translate("Normal Edit Mode")
               , "action" => $item->conf['form'].'?'.$item->getURI()
               , "class"=> "ss_arrow_left"
            ); 

if ($intra->arrUsrData['FlagWrite']) {
    $arrActions[]= Array ("title" => $intra->translate("Undo Last Action")
          // , "action" => $_SERVER["PHP_SELF"]."?ID=".urlencode($entItemID)."&entID={$entID}&DataAction=undo"
           , "action" => "javascript:confirmUndo()"
           , "class"=> "ss_arrow_undo"
        ); 

    $arrActions[]= Array ("title" => $intra->translate("Superaction!")
       , "action" => '#superaction'
       , "class"=> "ss_lightning_go bold"
    );

    $arrActions[]= Array ("title" => $intra->translate("Backup")
       , "action" => '#backup'
       , "class"=> "ss_script"
    );

    $arrActions[]= Array ("title" => $intra->translate("Restore")
       , "action" => '#restore'
       , "class"=> "ss_script"
    );

    $arrActions[]= Array ("title" => $intra->translate("Save")." ".$entItem->conf["entTitle{$intra->local}"]
       , "action" => 'javascript:save()'
       , "class"=> "ss_disk save_button bold"
    );

    
}




include eiseIntraAbsolutePath."inc_top.php";

$aFields = array();

foreach ($item->conf['ATR'] as $atrID => $arrAtr) {
    if( in_array($atrID, $item->table['columns_index']) && !in_array($item->table['columns_dict'][$atrID]['DataType'], ['activity_stamp', 'PK']) ){
        $aFields[] = $atrID;
    } 
}

$htmlAttrs = $item->getAttributeFields($aFields, null, array('FlagWrite'=>$intra->arrUsrData['FlagWrite'], 'forceFlagWrite'=>true));

$fldsMain = $intra->fieldset( "{$item->conf['entTitle'.$intra->local]} {$item->id}", $htmlAttrs, array('class'=>'half_screen') );
$fldsActivity = $intra->fieldset( $intra->translate('Activity log') 
    , $item->showStatusLog(array('FlagWrite'=>$intra->arrUsrData['FlagWrite'], 'forceFlagWrite'=>true, 'flagFullEdit'=>true)) 
    , array('class'=>'half_screen') );

$aStatuses = array();
foreach($item->conf['STA'] as $val=>$props){
    if($props['staFlagDeleted'])
        continue;
    $aStatuses[] = array('v'=>$val, 't'=>$props['staTitle'.$intra->local]);
}
echo $item->form(
        $intra->field(null, "undoWarning", $intra->translate('WARNING: Undo will erase all data related to last action. Are you sure?'), array('type'=>'hidden'))."\n".
        $intra->field(null, "aclOldStatusID_text", $item->conf['STA'][$item->staID]['staTitle'.$intra->local], array('type'=>'hidden'))."\n".
        $intra->field(null, "aStatuses", json_encode($aStatuses), array('type'=>'hidden'))."\n".
        $fldsMain."\n".
        $fldsActivity
        , array('action'=>$_SERVER['PHP_SELF'], 'DataAction'=>'updateFullEdit')
    );

?>
<style type="text/css">
a.remove {
    text-decoration: none;
}
a.remove:hover {
    font-weight: bold;
}
a.remove:visited {
    color: inherit;
}
</style>
<script>
$(document).ready(function(){
    $('a[href="#backup"]').click(function(){
        location.href = location.pathname+location.search+'&DataAction=backup&asFile=true';
        return false;
    })
    $('a[href="#restore"]').click(function(){
        $(this).eiseIntraForm('upload2batch', {
            'DataAction': 'restore',
            'fileFieldName': 'backup_file',
            'allowedExts': 'json',
            'action': location.pathname+location.search+'&fromBatch=true'
        });
        return false;
    })
    $('a[href="#superaction"]').click(function(){

        var aStatuses = JSON.parse($('#aStatuses').val());

        $(this).eiseIntraForm('createDialog', {
            title: $(this).text()
            , action: location.href
            , method: 'POST'
            , width: '400px'
            , fields: [
                {name: 'DataAction'
                    , type: 'hidden'
                    , value: 'superaction'}
                , {name: 'entID', type: 'hidden', value: $('#entID').val()}
                , {name: 'aclOldStatusID'
                    , type: 'hidden'
                    , value: $('#aclOldStatusID').val()}
                , {title: 'Current Status'
                    , name: 'aclOldStatusID_text'
                    , type: 'text'
                    , value: $('#aclOldStatusID_text').val()
                    }
                , {title: 'New Status'
                    , name: 'aclNewStatusID'
                    , type: 'combobox'
                    , defaultText: '- pls select'
                    , options: aStatuses
                    }
                , {title: 'Arrival Time'
                    , name: 'aclATA'
                    , type: 'datetime'
                    , required: true
                    , value: $('body').eiseIntra('formatDate', (new Date(Date.now() - ((new Date()).getTimezoneOffset() * 60000))).toISOString(), 'datetime')
                    }
                , {title: 'Comment'
                    , name: 'aclComments'
                    , type: 'textarea'
                    , required: true
                    }
                    ]
            , onsubmit: function(){
                if(!$(this).find('select[name="aclNewStatusID"]').val() 
                    || !$(this).find('input[name="aclATA"]').val() 
                    || !$(this).find('[name="aclComments"]').val() 
                    ){
                    alert("New Status, Arrival Time and Comments should be specified.");
                    return false;
                }
                return ($(this).eiseIntraForm('validate'));

            }
        })
        return false;
    });
    $('a[href="#remove_stl"], a[href="#remove_acl"]').click(function(){
        var initiator = this,
            $initiator = $(this),
            DataAction = $initiator.attr('href').replace('#', ''),
            acl_stl = DataAction.replace('remove_', ''),
            $parent = $initiator.parents('.eif-field'),
            guid = $parent[0].dataset['guid'],
            href = location.pathname+location.search+'&DataAction='+DataAction+'&'+acl_stl+'GUID='+guid;

            if(confirm("Are you sure you'd like to remove \""+$parent.find('label').text()+"\"?\n"+$parent.attr('title')))
                location.href = href;

        return false;

    });
})

function confirmUndo(){
    if (confirm($('#undoWarning').val())){
        location.href = location.pathname+location.search+'&DataAction=undo';
    }
}
function save(){
    $('.eiseIntraForm')[0].submit();
}
</script>

<?php

include eiseIntraAbsolutePath."inc_bottom.php";

die();
//*/


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
    header("Location: {$ent->conf["entScriptPrefix"]}_list.php");
    die();
}


switch ($DataAction){
    case "update":
        
        // here we'll run 'Update' action with all data update
        $_POST["actID"] = 2;
        $_POST["aclComments"] = "Full Edit Mode update";
        
        $oSQL->do_query("START TRANSACTION");
        
        $entItem->updateMasterTable($_POST, false, true);
        
        foreach($entItem->item["ACL"] as $aclGUID=>$arrACL){
            $entItem->updateActionLogItem($aclGUID, $arrACL);
        }
        
        foreach($entItem->item["STL"] as $stlGUID => $rwSTL){
            
            $entItem->updateStatusLogItem($stlGUID, $rwSTL);

            if (isset($rwSTL["ACL"]))
            foreach ($rwSTL["ACL"] as $aclGUID=>$rwNAct){
               $entItem->updateActionLogItem($aclGUID, $rwNAct);
            }
            
            $entItem->updateActionLogItem($rwSTL["stlArrivalAction"]["aclGUID"], $rwSTL["stlArrivalAction"]);
            
        }

        $entItem->flagFullEdit = true;

        $entItem->prepareActions();
        $entItem->addAction();
        $entItem->finishAction();
        
        $oSQL->do_query("COMMIT");
        
        SetCookie("UserMessage", "{$entItem->conf["entTitle"]} updated");
        header("Location: ".$_SERVER["PHP_SELF"]."?ID=".urlencode($entItemID)."&entID={$entID}");
        die();
        
	case "undo";
        
        foreach($entItem->item["ACL"] as $aclGUID=>$arrACL){
            if ($arrACL["aclActionPhase"] < 2){
                SetCookie("UserMessage", "ERROR: ".$entItem->conf["entTitle{$intra->local}"]." ".$intra->translate('has incomplete actions. Cancel them first.'));
                header("Location: ".$_SERVER["PHP_SELF"]."?ID=".urlencode($entItemID)."&entID={$entID}");
                die();
            }
        }
        
        //$oSQL->startProfiling();
        $oSQL->do_query("START TRANSACTION");
        
        // gathering action data we'd like to cancel
        $aclGUID = $entItem->item[$entID."ActionLogID"]; //last action ID stamped at item
        
        // compose pseudo-post array
        $arrPseudoPOST = Array('aclGUID'=>$aclGUID // action log GUID
            , 'aclToDo'=>'cancel' // what do we do with this action
            , 'isUndo'=>true); // flags that action was 'undoed'
        
        $entItem->doFullAction($arrPseudoPOST);
        
        $oSQL->do_query("COMMIT");
        SetCookie("UserMessage", "{$entItem->conf["entTitle"]} was rolled back to its previous state");
        header("Location: ".$_SERVER["PHP_SELF"]."?ID=".urlencode($entItemID)."&entID={$entID}");
        //$oSQL->showProfileInfo();
        
        die();
        
    default:
        break;
}
    

$arrActions[]= Array ("title" => $intra->translate("Normal edit mode")
       , "action" => "index.php?pane={$entItem->conf["entScriptPrefix"]}_form.php?{$entID}ID=".urlencode($entItemID)
       , "class"=> "ss_arrow_left"
       , "target" => "_top"
    );

    
if ($intra->arrUsrData['FlagWrite']) {
$arrActions[]= Array ("title" => $intra->translate("Undo last Action")
      // , "action" => $_SERVER["PHP_SELF"]."?ID=".urlencode($entItemID)."&entID={$entID}&DataAction=undo"
       , "action" => "javascript:confirmUndo()"
       , "class"=> "ss_arrow_undo"
    );
$arrActions[]= Array ("title" => $intra->translate("Update")." ".$entItem->conf["entTitle{$intra->local}"]
       , "action" => 'javascript:save()'
       , "class"=> "ss_accept save_button"
    );
}

$intra->arrUsrData["pagTitle{$intra->local}"] = $entItem->conf["entTitle{$intra->local}"].' '.$entItemID.': '.$intra->translate('Full edit mode');

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
<input type="hidden" name="ID" id="ID" value="<?php  echo $entItem->item["{$entID}ID"] ; ?>">
<input type="hidden" id="undoWarning" value="<?php  echo $intra->translate('WARNING: Undo will erase all data related to last action. Are you sure?') ; ?>">

<div class="panel">
<table width="100%">

<tr>
<td width="50%">
<h1><?php  echo $entItem->conf["entTitle{$intra->local}"].": ".$entItemID ; ?></h1>
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
    echo $intra->showCombo($entID."StatusID", $entItem->item[$entID."StatusID"], $arrOptions);
 ?></div></div>
 
 <hr>
 -->

<?php
foreach($entItem->item["STL"] as $stlGUID => $rwSTL){
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

        $rwATV = array_merge($entItem->conf['ATR'][$atrID], $rwATV);
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
    foreach ($entItem->item["ACL_Cancelled"] as $ix=>$rwACL){
        
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