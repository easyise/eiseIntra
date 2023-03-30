<?php
include 'common/auth.php';

$atrID  = (isset($_POST['atrID']) ? $_POST['atrID'] : $_GET['atrID'] );
$atrEntityID  = (isset($_POST['atrEntityID']) ? $_POST['atrEntityID'] : $_GET['atrEntityID'] );
$DataAction  = (isset($_POST['DataAction']) ? $_POST['DataAction'] : $_GET['DataAction'] );

$intra->requireComponent('grid');



if($intra->arrUsrData['FlagWrite']){

switch($DataAction){
    case 'update':
        
        $oSQL->q('START TRANSACTION');
        $oSQL->startProfiling();

        if ($atrID=="") {
            $sqlIns = "INSERT INTO stbl_attribute (
                    atrID
                    , atrEntityID
                    , `atrTitle`
                    , `atrTitleLocal`
                    , `atrShortTitle`
                    , `atrShortTitleLocal`
                    , `atrType`
                    , `atrOrder`
                    , `atrClasses`
                    , `atrDefault`
                    , `atrTextIfNull`
                    , `atrProgrammerReserved`
                    , `atrCheckMask`
                    , `atrDataSource`
                    , `atrFlagHideOnLists`
                    , `atrFlagDeleted`
                    , `atrInsertBy`, `atrInsertDate`, `atrEditBy`, `atrEditDate`
                ) VALUES (
                    '$atrID'
                    , '$atrEntityID'
                    , ".$oSQL->escape_string($_POST['atrTitle'])."
                    , ".$oSQL->escape_string($_POST['atrTitleLocal'])."
                    , ".$oSQL->escape_string($_POST['atrShortTitle'])."
                    , ".$oSQL->escape_string($_POST['atrShortTitleLocal'])."
                    , ".$oSQL->escape_string($_POST['atrType'])."
                    , '".(integer)$_POST['atrOrder']."'
                    , ".$oSQL->escape_string($_POST['atrClasses'])."
                    , ".$oSQL->escape_string($_POST['atrDefault'])."
                    , ".$oSQL->escape_string($_POST['atrTextIfNull'])."
                    , ".$oSQL->escape_string($_POST['atrProgrammerReserved'])."
                    , ".$oSQL->escape_string($_POST['atrCheckMask'])."
                    , ".$oSQL->escape_string($_POST['atrDataSource'])."
                    , '".($_POST['atrFlagHideOnLists']=='on' ? 1 : 0)."'
                    , '".($_POST['atrFlagDeleted']=='on' ? 1 : 0)."'
                    , '$intra->usrID', NOW(), '$intra->usrID', NOW());";
            $oSQL->q($sqlIns);
        } else {
            $sqlUpd = "UPDATE stbl_attribute SET
                    atrID = '".$_POST['atrID']."'
                    , atrEntityID = '".$_POST['atrEntityID']."'
                    , atrTitle = ".$oSQL->escape_string($_POST['atrTitle'])."
                    , atrTitleLocal = ".$oSQL->escape_string($_POST['atrTitleLocal'])."
                    , atrShortTitle = ".$oSQL->escape_string($_POST['atrShortTitle'])."
                    , atrShortTitleLocal = ".$oSQL->escape_string($_POST['atrShortTitleLocal'])."
                    , atrType = ".$oSQL->escape_string($_POST['atrType'])."
                    , atrOrder = '".(integer)$_POST['atrOrder']."'
                    #, atrClasses = ".$oSQL->escape_string($_POST['atrClasses'])."
                    , atrDefault = ".$oSQL->escape_string($_POST['atrDefault'])."
                    , atrTextIfNull = ".$oSQL->escape_string($_POST['atrTextIfNull'])."
                    , atrProgrammerReserved = ".$oSQL->escape_string($_POST['atrProgrammerReserved'])."
                    , atrCheckMask = ".$oSQL->escape_string($_POST['atrCheckMask'])."
                    , atrDataSource = ".$oSQL->escape_string($_POST['atrDataSource'])."
                    , atrHref = ".$oSQL->escape_string($_POST['atrHref'])."
                    , atrFlagHideOnLists = '".($_POST['atrFlagHideOnLists']=='on' ? 1 : 0)."'
                    , atrFlagDeleted = '".($_POST['atrFlagDeleted']=='on' ? 1 : 0)."'
                    , atrEditBy = '$intra->usrID', atrEditDate = NOW()
                WHERE `atrID` = ".$oSQL->e($atrID)." AND `atrEntityID` = ".$oSQL->e($atrEntityID)."";
            $oSQL->q($sqlUpd);
        }

        foreach ($_POST['inp_sat_updated'] as $ix => $updated) {
            if(!$updated)
                continue;

            $sqlFields = "satFlagEditable = ".(int)($_POST['satFlagEditable'][$ix])."
                , satFlagShowInForm = ".(int)($_POST['satFlagShowInForm'][$ix])."
                , satFlagShowInList = ".(int)($_POST['satFlagShowInList'][$ix])."
                , satFlagTrackOnArrival = ".(int)($_POST['satFlagTrackOnArrival'][$ix])."
                , satEditBy = '$intra->usrID', satEditDate = NOW()
                ";
            $sqlUpd = "UPDATE stbl_status_attribute SET {$sqlFields} 
                WHERE satStatusID=".$oSQL->e($_POST['staID'][$ix])."
                    AND satEntityID = ".$oSQL->e($atrEntityID)."
                    AND satAttributeID = ".$oSQL->e($atrID);
            $oSQL->q($sqlUpd);        

            if($oSQL->a()==0){
                $sqlIns = "INSERT INTO stbl_status_attribute SET {$sqlFields}
                    , satStatusID=".$oSQL->e($_POST['staID'][$ix])."
                    , satEntityID = ".$oSQL->e($atrEntityID)."
                    , satAttributeID = ".$oSQL->e($atrID)."
                    , satInsertBy = '$intra->usrID', satInsertDate = NOW()";
                $oSQL->q($sqlIns);
            }
        }
/*
        echo '<pre>';
        print_r($_POST);
        $oSQL->showProfileInfo();
        die();
        */
        
        $oSQL->q('COMMIT');
        
        
        SetCookie("UserMessage", "Data is updated");
        header("Location: ".$_SERVER["PHP_SELF"]."?atrID=".urlencode($atrID)."&atrEntityID=".urlencode($atrEntityID)."&dbName=".$oSQL->dbname);
        die();
        
    case 'delete':

        $oSQL->q('START TRANSACTION');
        $sqlDel = "DELETE FROM `stbl_attribute` WHERE `atrID` = ".$oSQL->e($atrID)." AND `atrEntityID` = ".$oSQL->e($atrEntityID)."";
        $oSQL->q($sqlDel);
        $oSQL->q('COMMIT');
        SetCookie("UserMessage", "Data is deleted");
        header("Location: ".preg_replace('/form\.php$/', 'list.php', $_SERVER["PHP_SELF"]));
        die();
        
    default:
        break;
}
}

$sqlATR = "SELECT * FROM `stbl_attribute` 
    INNER JOIN stbl_entity ON atrEntityID=entID
    WHERE `atrID` = ".$oSQL->e($atrID)." AND `atrEntityID` = ".$oSQL->e($atrEntityID)."";
$rsATR = $oSQL->do_query($sqlATR);
$rwATR = $oSQL->fetch_array($rsATR);

$arrActions[]= Array ('title' => $intra->translate('Back to %s', $rwATR['entTitle'])
	   , 'action' => "entity_form.php?entID={$atrEntityID}&dbName={$dbName}"
	   , 'class'=> 'ss_arrow_left'
	);

include eiseIntraAbsolutePath.'inc_top.php';
?>

<form action="<?php  echo $_SERVER["PHP_SELF"] ; ?>" method="POST" class="eiseIntraForm eif-form" id="frmAtr">
<input type="hidden" name="atrID" value="<?php  echo htmlspecialchars($atrID) ; ?>">
<input type="hidden" name="atrEntityID" value="<?php  echo htmlspecialchars($atrEntityID) ; ?>">
<input type="hidden" name="DataAction" value="update">

<fieldset class="eiseIntraMainForm" id="fldsMain"><legend>Attribute <?php echo ($rwATR["atrTitle"] ? '"'.$rwATR["atrTitle"].'"' : '')." ({$rwATR['atrID']})" ?> </legend>

<div class="eiseIntraField">
<label><?php echo $intra->translate("Title in English"); ?>:</label><?php
 echo $intra->showTextBox("atrTitle", $rwATR["atrTitle"], Array('type'=>'text'));?></div>

<div class="eiseIntraField">
<label><?php echo $intra->translate("Title in local language"); ?>:</label><?php
 echo $intra->showTextBox("atrTitleLocal", $rwATR["atrTitleLocal"], Array('type'=>'text'));?></div>

<div class="eiseIntraField">
<label><?php echo $intra->translate("Short title (for lists, in English)"); ?>:</label><?php
 echo $intra->showTextBox("atrShortTitle", $rwATR["atrShortTitle"], Array('type'=>'text'));?></div>

<div class="eiseIntraField">
<label><?php echo $intra->translate("Short title (for lists, in local language)"); ?>:</label><?php
 echo $intra->showTextBox("atrShortTitleLocal", $rwATR["atrShortTitleLocal"], Array('type'=>'text'));?></div>

<div class="eiseIntraField">
<label><?php echo $intra->translate("True when there's no field for this attribute in Master table"); ?>:</label><?php
 echo $intra->showCheckBox("atrFlagNoField", $rwATR["atrFlagNoField"]);?></div>

<div class="eiseIntraField">
<label><?php echo $intra->translate("Type (see Intra types)"); ?>:</label><?php
 echo $intra->showTextBox("atrType", $rwATR["atrType"], Array('type'=>'text'));?></div>

<div class="eiseIntraField">
<label><?php echo $intra->translate("Defines order how this attribute appears on screen"); ?>:</label><?php
 echo $intra->showTextBox("atrOrder", $rwATR["atrOrder"], Array('type'=>'number'));?></div>

<div class="eiseIntraField">
<label><?php echo $intra->translate("CSS classes"); ?>:</label><?php
 echo $intra->showTextBox("atrClasses", $rwATR["atrClasses"], Array('type'=>'text'));?></div>

<div class="eiseIntraField">
<label><?php echo $intra->translate("Default Value"); ?>:</label><?php
 echo $intra->showTextBox("atrDefault", $rwATR["atrDefault"], Array('type'=>'text'));?></div>

<div class="eiseIntraField">
<label><?php echo $intra->translate("Text to dispay wgen value not set"); ?>:</label><?php
 echo $intra->showTextBox("atrTextIfNull", $rwATR["atrTextIfNull"], Array('type'=>'text'));?></div>

<div class="eiseIntraField">
<label><?php echo $intra->translate("Reserved for programmer"); ?>:</label><?php
 echo $intra->showTextBox("atrProgrammerReserved", $rwATR["atrProgrammerReserved"], Array('type'=>'text'));?></div>

<div class="eiseIntraField">
<label><?php echo $intra->translate("Regular expression for data validation"); ?>:</label><?php
 echo $intra->showTextBox("atrCheckMask", $rwATR["atrCheckMask"], Array('type'=>'text'));?></div>

<div class="eiseIntraField">
<label><?php echo $intra->translate("Data source (table, view or array)"); ?>:</label><?php
 echo $intra->showTextBox("atrDataSource", $rwATR["atrDataSource"], Array('type'=>'text'));?></div>

<div class="eiseIntraField">
<label><?php echo $intra->translate("Href"); ?>:</label><?php
 echo $intra->showTextBox("atrHref", $rwATR["atrHref"], Array('type'=>'text'));?></div>

<div class="eiseIntraField">
<label><?php echo $intra->translate("True when this attribute not shown on lists"); ?>:</label><?php
 echo $intra->showCheckBox("atrFlagHideOnLists", $rwATR["atrFlagHideOnLists"]);?></div>

<div class="eiseIntraField">
<label><?php echo $intra->translate("True when attribute no longer used"); ?>:</label><?php
 echo $intra->showCheckBox("atrFlagDeleted", $rwATR["atrFlagDeleted"]);?></div>

<div class="eiseIntraField">

<?php 
if ($intra->arrUsrData["FlagWrite"]) {
 ?>
<label>&nbsp;</label><div class="eiseIntraValue"><input class="eiseIntraSubmit" type="Submit" value="Update">
<?php 
if ($atrID!="" && $rwATR["atrDeleteDate"]==""){
?>
<input type="Submit" value="Delete" class="eiseIntraDelete">
<?php  
  }
}
?></div>

</div>

</fieldset>

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
            , 'field' => 'staID'
        );
$gridSAT->Columns[] = Array(
        'title' => ""
        , 'field' => "satAttributeID"
        , 'type' => "text"
        , 'default' => $atrID
);

$gridSAT->Columns[] = Array(
        'title' => ""
        , 'field' => "satEntityID"
        , 'type' => "text"
        , 'default' => $atrEntityID
);

$gridSAT->Columns[] = Array(
        'title' => $intra->translate("ID")
        , 'field' => "staID_"
        , 'type' => "text"
        , 'disabled' => true
        , 'width' => "40px"
);
$gridSAT->Columns[] = Array(
        'title' => $intra->translate("Title")
        , 'field' => "staTitle{$intra->local}"
        , 'type' => "text"
        , 'disabled' => true
        , 'width' => "100%"
);

$gridSAT->Columns[] = Array(
        'title' => $intra->translate("Track?")
        , 'field' => "satFlagTrackOnArrival"
        , 'type' => "checkbox"
        , 'width' => '40px'
);

$gridSAT->Columns[] = Array(
        'title' => $intra->translate("In Form?")
        , 'field' => "satFlagShowInForm"
        , 'type' => "checkbox"
        , 'width' => '40px'
);
$gridSAT->Columns[] = Array(
        'title' => $intra->translate("Allowed?")
        , 'field' => "satFlagEditable"
        , 'type' => "checkbox"
        , 'width' => '40px'
);
$gridSAT->Columns[] = Array(
        'title' => $intra->translate("In List?")
        , 'field' => "satFlagShowInList"
        , 'type' => "checkbox"
        , 'width' => '40px'
);


$sqlSAT = "SELECT * FROM stbl_status 
    LEFT OUTER JOIN stbl_status_attribute ON staID=satStatusID AND satAttributeID='$atrID' AND satEntityID='$atrEntityID'
    WHERE staEntityID='$atrEntityID'
    ORDER BY staID";
$rsSAT = $oSQL->do_query($sqlSAT);
while ($rwSAT = $oSQL->fetch_array($rsSAT)){
    $rwSAT["staID_"] = $rwSAT["staID"];
    $rwSAT["satAllowed"] = ($rwSAT["satID"] ? "1" : "0");
    $gridSAT->Rows[] = $rwSAT;
}

echo $intra->fieldset($intra->translate('Status visibility'), $gridSAT->get_html(), array('id'=>'fldsAttr'));


 ?>

</form>
<script>
$(document).ready(function(){
    eiseIntraInitializeForm();
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
<style type="text/css">
#frmAtr fieldset {
    display: inline-block;
    vertical-align: top;
}
#fldsMain {
    width: 62%;
}
#fldsAttr {
    width: 36%;
}
</style>
<?php
include eiseIntraAbsolutePath.'inc_bottom.php';
?>