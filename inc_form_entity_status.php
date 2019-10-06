<?php 
$intra->requireComponent('jquery-ui', 'grid');

$DataAction  = (isset($_POST['DataAction']) ? $_POST['DataAction'] : $_GET['DataAction'] );

$staID = (isset($_POST["staID"]) ? $_POST["staID"] : $_GET["staID"]);
$entID = (isset($_POST["entID"]) ? $_POST["entID"] : $_GET["entID"]);

$sqlSta = "SELECT * FROM stbl_status 
  INNER JOIN stbl_entity ON staEntityID=entID
  WHERE staID='$staID' AND staEntityID='$entID'";
$rsSta = $oSQL->do_query($sqlSta);
$rwSta = $oSQL->fetch_array($rsSta);
$ffSta = $oSQL->ff($rsSta);  
  

switch($DataAction){
    case "update":

        $oSQL->q('START TRANSACTION');
        $oSQL->startProfiling();
       
        $sqlUpd = "UPDATE stbl_status SET
            staTitle = ".$oSQL->escape_string($_POST['staTitle'])."
            , staTitleLocal = ".$oSQL->escape_string($_POST['staTitleLocal'])."
            , staTrackPrecision = ".$oSQL->escape_string($_POST['staTrackPrecision'])."
            , staFlagCanUpdate = '".($_POST['staFlagCanUpdate']=='on' ? 1 : 0)."'
            , staFlagCanDelete = '".($_POST['staFlagCanDelete']=='on' ? 1 : 0)."'
            ".(isset($ffSta['staMenuItemClass']) ? ', staMenuItemClass='.$oSQL->e($_POST['staMenuItemClass']) : '')."
			  , staFlagDeleted = '".($_POST['staFlagDeleted']=='on' ? 1 : 0)."'
            , staEditBy = '$usrID', staEditDate = NOW()
            WHERE staID = '{$staID}'AND staEntityID = '{$entID}'";

        $oSQL->q($sqlUpd);
       
        if ($_POST['staFlagDeleted']=='on'){
	        $sqlAct = "UPDATE stbl_action INNER JOIN stbl_action_status ON atsActionID=actID SET actFlagDeleted=1 
                WHERE actEntityID='{$entID}' 
                AND (atsOldStatusID='".$_POST["staID"]."' OR atsNewStatusID='".$_POST["staID"]."')";
            $oSQL->q($sqlAct);
	    }

	           
        for ($i=1; $i< count($_POST["atrID"]); $i++){

            if(!$_POST['atrID'][$i])
                continue;

            $atrID = $oSQL->unq($oSQL->e($_POST['atrID'][$i]));
            $creterio = "satAttributeID='{$atrID}', satStatusID={$staID}, satEntityID='{$entID}'";
            $sqlExists = "SELECT satID FROM stbl_status_attribute WHERE ".preg_replace('/\, /', ' AND ', $creterio);

            $fields = "
                satEditBy='{$intra->usrID}', satEditDate=NOW()
                , satFlagEditable=".(int)$_POST["satFlagEditable"][$i]."
                , satFlagShowInForm=".(int)$_POST["satFlagShowInForm"][$i]."
                , satFlagShowInList=".(int)$_POST["satFlagShowInList"][$i]."
                , satFlagTrackOnArrival=".(int)$_POST["satFlagTrackOnArrival"][$i]
                ;

            if( !($satID = $oSQL->d($sqlExists)) ){
                $sqlSAT = "INSERT INTO stbl_status_attribute SET {$fields}, {$creterio}, satInsertBy='{$intra->usrID}', satInsertDate=NOW()";
            } else {
                $sqlSAT = "UPDATE stbl_status_attribute SET {$fields} WHERE satID={$satID}";
            }

            $oSQL->q($sqlSAT);
            
        }
        
        $oSQL->q('COMMIT');
        
        $intra->redirect($entID." ".$intra->translate("is updated"), $_SERVER["PHP_SELF"]."?dbName=$dbName&staID=$staID&entID=".urlencode($entID));

    default:
        break;
}


$arrActions[]= Array ('title' => $rwSta["entTitle"]
	   , 'action' => "entity_form.php?dbName=$dbName&entID=".$rwSta["entID"]
	   , 'class'=> 'ss_arrow_left'
	);
include eiseIntraAbsolutePath."inc_top.php";
?>
<script>
$(document).ready(function(){  
	
    $('.eiseGrid').eiseGrid();
    
    $("th.sat-satFlagShowInForm, th.sat-satFlagEditable, th.sat-satFlagShowInList")
        .css("cursor", "pointer")
        .click(function(){
            var tdClass = ($(this).attr('class').split(/\s+/)[0]),
                $grid = $(this).parents('.eiseGrid').first(),
                $checkBoxes = $grid.find('.eg-data .'+tdClass+' input[type=checkbox]');
            $checkBoxes.each(function(){
                this.click();
            })
        });
    
    
    
});
</script>

<form action="<?php  echo $_SERVER["PHP_SELF"] ; ?>" method="POST" class="eiseIntraForm eif-form">
<input type="hidden" name="DataAction" value="update">
<input type="hidden" name="dbName" value="<?php  echo $dbName ; ?>">
<input type="hidden" name="staID" value="<?php  echo $staID ; ?>">
<input type="hidden" name="entID" value="<?php  echo $rwSta["staEntityID"] ; ?>">

<fieldset>
<legend>Status: <?php  echo $rwSta["staTitle{$intra->local}"] ; ?></legend>


<table width="100%">

<tr>
<td width="50%">

<div class="eiseIntraField"><label><?php echo $intra->translate("Title") ?>:</label>
<?php  echo $intra->showTextBox("staTitle", $rwSta["staTitle"]) ; ?>
</div>

<div class="eiseIntraField"><label><?php echo $intra->translate("Title Local") ?>:</label>
<?php  echo $intra->showTextBox("staTitleLocal", $rwSta["staTitleLocal"]) ; ?>
</div>

<?php echo (isset($ffSta['staMenuItemClass']) 
    ? $intra->field('Menu item class', 'staMenuItemClass', $rwSta['staMenuItemClass']) 
    : ''); ?>

<div class="eiseIntraField"><label><?php echo $intra->translate("Precision") ?>:</label>
<?php  echo $intra->showCombo("staTrackPrecision", $rwSta["staTrackPrecision"], Array("date"=>$intra->translate("Date")
    , "datetime"=>$intra->translate("Date+Time"))) ; ?>
</div>

<div class="eiseIntraField"><label><?php echo $intra->translate("Update Allowed?") ?>:</label>
<?php  echo $intra->showCheckBox("staFlagCanUpdate", $rwSta["staFlagCanUpdate"]) ; ?>
</div>

<div class="eiseIntraField"><label><?php echo $intra->translate("Delete allowed?") ?>:</label>
<?php  echo $intra->showCheckBox("staFlagCanDelete", $rwSta["staFlagCanDelete"]) ; ?>
</div>

<hr>

<div class="eiseIntraField"><label><?php echo $intra->translate("Deleted?") ?>:</label>
<?php  echo $intra->showCheckBox("staFlagDeleted", $rwSta["staFlagDeleted"]) ; ?>
</div>

<div class="eiseIntraField"><label><?php echo $intra->translate("Actions") ?>:</label>
<div class="eiseIntraValue">
<ul>
<li><b><?php echo $intra->translate('Leading to') ?> "<?php  echo $rwSta["staTitle"] ; ?>":</b></li>
<ul>
<?php 
$sqlAct = "SELECT DISTINCT actID, actTitle, actTitleLocal, actFlagDeleted FROM stbl_action_status INNER JOIN stbl_action ON actID=atsActionID
    WHERE atsNewStatusID='{$rwSta["staID"]}' AND actEntityID='{$entID}'
    ORDER BY actFlagDeleted";
$rsAct = $oSQL->do_query($sqlAct);
if ($oSQL->n($rsAct)==0){echo " - ".$intra->translate('Nothing found')." - ";}
while ($rwAct=$oSQL->fetch_array($rsAct)) {
?>
<li><a href="action_form.php?entID=<?php  
  echo $entID ; 
  ?>&actID=<?php  
  echo $rwAct["actID"] ; 
  ?>&dbName=<?php 
  echo $dbName ; 
  ?>"><?php  echo $rwAct["actTitle{$intra->local}"].($rwAct['actFlagDeleted'] ? " (deleted)" : "") ; ?></a></li>
<?php
}
 ?>
</ul>
<li><b><?php echo $intra->translate('Leading from') ?> "<?php  echo $rwSta["staTitle"] ; ?>":</b></li>
<ul>
<?php 
$sqlAct = "SELECT DISTINCT actID, actTitle, actTitleLocal, actFlagDeleted FROM stbl_action_status INNER JOIN stbl_action ON actID=atsActionID
    WHERE atsOldStatusID='{$rwSta["staID"]}' AND actEntityID='{$entID}'
    ORDER BY actFlagDeleted";
$rsAct = $oSQL->do_query($sqlAct);
while ($rwAct=$oSQL->fetch_array($rsAct)) {
?>
<li><a href="action_form.php?entID=<?php  
  echo $entID ; 
  ?>&actID=<?php  
  echo $rwAct["actID"] ; 
  ?>&dbName=<?php 
  echo $dbName ; 
  ?>"><?php  echo $rwAct["actTitle{$intra->local}"].($rwAct['actFlagDeleted'] ? " (deleted)" : "") ; ?></a></li>
<?php
}
 ?>
</ul>
</ul>

</div>
</div>

</td>

<td width="50%">
<?php

$gridSAT = new easyGrid($oSQL
        ,'sat'
        , Array(
                'rowNum' =>40
                , 'arrPermissions' => Array('FlagWrite'=>$intra->arrUsrData["FlagWrite"])
                , 'strTable' => 'stbl_action_attribute'
                , 'strPrefix' => 'aat'
                , 'flagStandAlone' => true
                , 'flagNoDelete' => true
                )
        );

$gridSAT->Columns[]  = Array(
            'type' => 'row_id'
            , 'field' => 'atrID'
        );
$gridSAT->Columns[] = Array(
        'title' => ""
        , 'field' => "satStatusID"
        , 'type' => "text"
        , 'default' => $staID
);

$gridSAT->Columns[] = Array(
        'title' => ""
        , 'field' => "satEntityID"
        , 'type' => "text"
        , 'default' => $entID
);

$gridSAT->Columns[] = Array(
        'title' => ""
        , 'field' => "satAttibuteID"
        , 'type' => "text"
);

$gridSAT->Columns[] = Array(
        'title' => $intra->translate("Field")
        , 'field' => "atrID_"
        , 'type' => "text"
        , 'static' => true
);

$gridSAT->Columns[] = Array(
        'title' => $intra->translate("Attribute")
        , 'field' => "atrTitle{$intra->local}"
        , 'type' => "text"
        , 'disabled' => true
        , 'width' => "100%"
);

$gridSAT->Columns[] = Array(
        'title' => $intra->translate("Type")
        , 'field' => "atrType"
        , 'type' => "text"
        , 'static' => true
);


$gridSAT->Columns[] = Array(
        'title' => $intra->translate("Track?")
        , 'field' => "satFlagTrackOnArrival"
        , 'type' => "checkbox"
);

$gridSAT->Columns[] = Array(
        'title' => $intra->translate("In Form?")
        , 'field' => "satFlagShowInForm"
        , 'type' => "checkbox"
);
$gridSAT->Columns[] = Array(
        'title' => $intra->translate("Allowed?")
        , 'field' => "satFlagEditable"
        , 'type' => "checkbox"
);
$gridSAT->Columns[] = Array(
        'title' => $intra->translate("In List?")
        , 'field' => "satFlagShowInList"
        , 'type' => "checkbox"
);


$sqlSAT = "SELECT * FROM stbl_attribute 
LEFT OUTER JOIN stbl_status_attribute ON atrID=satAttributeID AND satStatusID='$staID' AND satEntityID='$entID'
WHERE atrEntityID='$entID'
ORDER BY atrOrder";
$rsSAT = $oSQL->do_query($sqlSAT);
while ($rwSAT = $oSQL->fetch_array($rsSAT)){
    $rwSAT['atrID_'] = $rwSAT['atrID'];
    $rwSAT["satAllowed"] = ($rwSAT["satID"] ? "1" : "0");
    $gridSAT->Rows[] = $rwSAT;
}

$gridSAT->Execute();
?>
</td>
</tr>
<?php 
if ($intra->arrUsrData["FlagWrite"]){ ?>
<tr>
<td colspan="2" align="center">
<input type="submit" value="<?php echo $intra->translate('Save') ?>" class="eiseIntraSubmit">
</td>
</tr>
<?php 
} ?>
</table>

</fieldset>

</form>


<?php
include eiseIntraAbsolutePath."inc_bottom.php";
 ?>