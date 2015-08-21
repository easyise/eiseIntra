<?php
include 'common/auth.php';

$atrID  = (isset($_POST['atrID']) ? $_POST['atrID'] : $_GET['atrID'] );
$atrEntityID  = (isset($_POST['atrEntityID']) ? $_POST['atrEntityID'] : $_GET['atrEntityID'] );
$DataAction  = (isset($_POST['DataAction']) ? $_POST['DataAction'] : $_GET['DataAction'] );

$oSQL->dbname = isset($_POST["dbName"]) ? $_POST["dbName"] : $_GET["dbName"];
$oSQL->select_db($oSQL->dbname);

if($intra->arrUsrData['FlagWrite']){

switch($DataAction){
    case 'update':
        
        $oSQL->q('START TRANSACTION');
        
        if ($atrID=="") {
            $sqlIns = "INSERT INTO stbl_attribute (
                    atrID
                    , atrEntityID
                    , `atrTitle`
                    , `atrTitleLocal`
                    , `atrShortTitle`
                    , `atrShortTitleLocal`
                    , `atrFlagNoField`
                    , `atrType`
                    , `atrUOMTypeID`
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
                    , '".($_POST['atrFlagNoField']=='on' ? 1 : 0)."'
                    , ".$oSQL->escape_string($_POST['atrType'])."
                    , ".($_POST['atrUOMTypeID']!="" ? "'".$_POST['atrUOMTypeID']."'" : "NULL")."
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
                    , atrFlagNoField = '".($_POST['atrFlagNoField']=='on' ? 1 : 0)."'
                    , atrType = ".$oSQL->escape_string($_POST['atrType'])."
                    , atrUOMTypeID = ".($_POST['atrUOMTypeID']!="" ? "'".$_POST['atrUOMTypeID']."'" : "NULL")."
                    , atrOrder = '".(integer)$_POST['atrOrder']."'
                    , atrClasses = ".$oSQL->escape_string($_POST['atrClasses'])."
                    , atrDefault = ".$oSQL->escape_string($_POST['atrDefault'])."
                    , atrTextIfNull = ".$oSQL->escape_string($_POST['atrTextIfNull'])."
                    , atrProgrammerReserved = ".$oSQL->escape_string($_POST['atrProgrammerReserved'])."
                    , atrCheckMask = ".$oSQL->escape_string($_POST['atrCheckMask'])."
                    , atrDataSource = ".$oSQL->escape_string($_POST['atrDataSource'])."
                    , atrFlagHideOnLists = '".($_POST['atrFlagHideOnLists']=='on' ? 1 : 0)."'
                    , atrFlagDeleted = '".($_POST['atrFlagDeleted']=='on' ? 1 : 0)."'
                    , atrEditBy = '$intra->usrID', atrEditDate = NOW()
                WHERE `atrID` = ".$oSQL->e($atrID)." AND `atrEntityID` = ".$oSQL->e($atrEntityID)."";
            $oSQL->q($sqlUpd);
        }
        
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

$sqlATR = "SELECT * FROM `stbl_attribute` WHERE `atrID` = ".$oSQL->e($atrID)." AND `atrEntityID` = ".$oSQL->e($atrEntityID)."";
$rsATR = $oSQL->do_query($sqlATR);
$rwATR = $oSQL->fetch_array($rsATR);

$arrActions[]= Array ('title' => 'Back to list'
	   , 'action' => "entity_form.php?entID={$atrEntityID}&dbName={$oSQL->dbname}"
	   , 'class'=> 'ss_arrow_left'
	);
$arrJS[] = jQueryUIRelativePath.'js/jquery-ui-1.8.16.custom.min.js';
$arrCSS[] = jQueryUIRelativePath.'css/'.jQueryUITheme.'/jquery-ui-1.8.16.custom.css';
include eiseIntraAbsolutePath.'inc-frame_top.php';
?>

<form action="<?php  echo $_SERVER["PHP_SELF"] ; ?>" method="POST" class="eiseIntraForm">
<input type="hidden" name="atrID" value="<?php  echo htmlspecialchars($atrID) ; ?>">
<input type="hidden" name="atrEntityID" value="<?php  echo htmlspecialchars($atrEntityID) ; ?>">
<input type="hidden" name="dbName" value="<?php  echo htmlspecialchars($oSQL->dbname) ; ?>">
<input type="hidden" name="DataAction" value="update">

<fieldset class="eiseIntraMainForm"><legend>Attribute</legend>

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
<label><?php echo $intra->translate("UOM type (FK to stbl_uom)"); ?>:</label><?php
$sql = "SELECT uomTitle{$strLocal} as optText, uomID as optValue
            FROM stbl_uom
            WHERE uomType=''
            ORDER BY uomOrder";
$rs = $oSQL->q($sql);
while($rw = $oSQL->f($rs)){ $arrOptions[$rw['optValue']] = $rw['optText']; }
echo $intra->showCombo("atrUOMTypeID", $rwATR["atrUOMTypeID"], $arrOptions
                   , Array('strZeroOptnText'=>$intra->translate('-- please select')));
?></div>

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
</form>
<script>
$(document).ready(function(){
    eiseIntraInitializeForm();
});
</script>
<?php
include eiseIntraAbsolutePath.'inc-frame_bottom.php';
?>