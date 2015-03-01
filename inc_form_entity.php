<?php 
include commonStuffAbsolutePath.'eiseGrid/inc_eiseGrid.php';
$arrJS[] = commonStuffRelativePath.'eiseGrid/eiseGrid.js';
$arrCSS[] = commonStuffRelativePath.'eiseGrid/eiseGrid.css';

$dbName=(isset($_POST["dbName"]) ? $_POST["dbName"] : $_GET["dbName"]);
$oSQL->select_db($dbName);

$oSQL->dbName = $dbName;
//$_DEBUG = true;

$DataAction = isset($_POST["DataAction"]) ? $_POST["DataAction"] : $_GET["DataAction"];

$entID = ($_POST["entID"] ? $_POST["entID"] : $_GET["entID"]);

$sqlEnt = "SELECT * FROM stbl_entity WHERE entID='".$entID."'";
$rsEnt = $oSQL->do_query($sqlEnt);
$rwEnt = $oSQL->fetch_array($rsEnt);

$gridSTA = new eiseGrid($oSQL
        ,'sta'
        , Array(
                'arrPermissions' => Array('FlagWrite'=>true)
                , 'strTable' => 'stbl_status'
                , 'strPrefix' => 'sta'
                , 'flagStandAlone' => true
                , 'showControlBar' => true
                , 'controlBarButtons' => 'add'
                )
        );

$gridSTA->Columns[]  = Array(
            'type' => 'row_id'
            , 'field' => 'staID_id'
        );
$gridSTA->Columns[] = Array(
        'title' => $intra->translate("ID")
        , 'field' => "staID"
        , 'mandatory' => true
        , 'type' => "text"
);
$gridSTA->Columns[] = Array(
        'title' => ""
        , 'field' => "staEntityID"
        , 'default' => $entID
        , 'type' => "text"
);
$gridSTA->Columns[] = Array(
        'title' => $intra->translate("Title")
        , 'field' => "staTitle{$strLocal}"
        , 'width' => "50%"
        , 'type' => "text"
        , 'href' => "status_form.php?dbName=$dbName&staID=[staID]&entID=$entID"
);
$gridSTA->Columns[] = Array(
        'title' => $intra->translate("Upd")
        , 'field' => "staFlagCanUpdate"
        , 'type' => "checkbox"
);
$gridSTA->Columns[] = Array(
        'title' => $intra->translate("Del")
        , 'field' => "staFlagCanDelete"
        , 'type' => "checkbox"
);
$gridSTA->Columns[] = Array(
        'title' => "X"
        , 'field' => "staFlagDeleted"
        , 'type' => "checkbox"
		, 'disabled' => true
);



$gridATR = new eiseGrid($oSQL
        ,'atr'
        , Array(
                'arrPermissions' => Array('FlagWrite'=>true, 'FlagDelete'=>(bool)$easyAdmin)
                , 'strTable' => 'stbl_attribute'
                , 'strPrefix' => 'atr'
                , 'flagStandAlone' => true
                , 'showControlBar' => true
                , 'controlBarButtons' => 'add|moveup|movedown'
                )
        );

$gridATR->Columns[]  = Array(
            'type' => 'row_id'
            , 'field' => 'atrID_id'
        );
$gridATR->Columns[] = Array(
        'title' => ""
        , 'field' => "atrEntityID"
        , 'default' => $entID
        , 'type' => "text"
);
$gridATR->Columns[] = Array(
        'title' => $intra->translate("Ord")
        , 'field' => "atrOrder"
        , 'type' => "order"
);
$gridATR->Columns[] = Array(
        'title' => $intra->translate("Field")
        , 'field' => "atrID"
        , 'type' => "text"
        , 'width' => "110px"
        , 'mandatory' => $easyAdmin
        , 'disabled' => !$easyAdmin
);

if($easyAdmin){
    $gridATR->Columns[] = Array(
            'title' => $intra->translate("Title")
            , 'field' => "atrTitle{$strLocal}"
            , 'type' => "text"
            , 'width' => "30%"
            , 'href' => "attribute_form.php?dbName=$dbName&atrID=[atrID]&atrEntityID=$entID"
    );
$gridATR->Columns[] = Array(
        'title' => $intra->translate("Short Title")
        , 'field' => "atrShortTitle{$strLocal}"
        , 'type' => "text"
);
} else {

    $gridATR->Columns[] = Array(
            'title' => $intra->translate("Title")
            , 'field' => "atrTitle"
            , 'type' => "text"
            , 'width' => "15%"
            , 'mandatory' => true
    );
    $gridATR->Columns[] = Array(
            'title' => $intra->translate("Title (Loc)")
            , 'field' => "atrTitleLocal"
            , 'type' => "text"
            , 'width' => "15%"
    );
    $gridATR->Columns[] = Array(
        'title' => $intra->translate("Short Title")
        , 'field' => "atrShortTitle"
        , 'type' => "text"
    );
    $gridATR->Columns[] = Array(
        'title' => $intra->translate("Short Title (Loc)")
        , 'field' => "atrShortTitleLocal"
        , 'type' => "text"
    );

}

$arrAvailableTypes = array();
foreach ($intra->arrAttributeTypes as $type => $dataType) {
    if (!$easyAdmin && $dataType=='FK')
        continue;
    $arrAvailableTypes[$type]=$intra->translate($type);
}
$gridATR->Columns[] = Array(
        'title' => $intra->translate("Type")
        , 'field' => "atrType"
        , 'type' => "combobox"
        , 'arrValues' => $arrAvailableTypes
        , 'defaultText' => '-'
        , 'default' => "text"
        , "width" => "40px"
);
$gridATR->Columns[] = Array(
        'title' => $intra->translate("UOM")
        , 'field' => "atrUOMTypeID"
        , 'type' => "combobox"
        , 'defaultText' => "-"
        , 'sql' => "SELECT uomTitle{$strLocal} as optText, uomID as optValue
            FROM stbl_uom
            WHERE uomType=''
            ORDER BY uomOrder"
);

if ($easyAdmin) {
    $gridATR->Columns[] = Array(
        'title' => $intra->translate("Def")
        , 'field' => "atrDefault"
        , 'type' => "text"
    );
    $gridATR->Columns[] = Array(
            'title' => $intra->translate("IfNull")
            , 'field' => "atrTextIfNull"
            , 'type' => "text"
    );
    $gridATR->Columns[] = Array(
            'title' => $intra->translate("Classes")
            , 'field' => "atrClasses"
            , 'type' => "text"
    );
    $gridATR->Columns[] = Array(
            'title' => $intra->translate("Prg")
            , 'field' => "atrProgrammerReserved"
            , 'type' => "text"
    );
    $gridATR->Columns[] = Array(
            'title' => $intra->translate("CheckMask")
            , 'field' => "atrCheckMask"
            , 'type' => "text"
    );
    $gridATR->Columns[] = Array(
            'title' => $intra->translate("Data Source")
            , 'field' => "atrDataSource"
            , 'type' => "text"
    );
}

$gridATR->Columns[] = Array(
        'title' => $intra->translate("NoList")
        , 'field' => "atrFlagHideOnLists"
        , 'type' => "checkbox"
);
$gridATR->Columns[] = Array(
        'title' => $intra->translate("Del")
        , 'field' => "atrFlagDeleted"
        , 'type' => "checkbox"
);



$grdMX = new eiseGrid($oSQL
					, "act"
                    , Array(
                            'arrPermissions' => Array('FlagWrite'=>true)
                            , 'strTable' => 'stbl_action'
                            , 'showControlBar' => true
                            , 'controlBarButtons' => 'add|moveup|movedown'
                            )
                    );
/*
  :actID,
  :actEntity,
  :actOldStatusID,
  :actNewStatusID,
  :actTitle,
  :actTitleLocal,
  :actTitlePast,
  :actTitlePastLocal,
  :actDescription,
  :actDescriptionLocal,
  :actFlagDeleted,
  :actPriority,
  :actFlagComment,
  :actInsertBy,
  :actInsertDate,
  :actEditBy,
  :actEditDate
*/
$grdMX->Columns[] = Array(
   'field' => "actID"
   , 'type' => "row_id"
);

$sqlStaCmb = "SELECT staID as optValue, staTitle as optText FROM stbl_status WHERE staEntityID='$entID'";

$grdMX->Columns[] = Array(
   'title' => $intra->translate("Old Status")
   , 'field' => 'actOldStatusIDs'
   , 'type' => "text"
   , 'disabled' => true
);

$grdMX->Columns[] = Array(
   'title'=>$intra->translate("Title")
   , 'field' => "actTitle"
   , 'href' => "action_form.php?dbName=$dbName&actID=[actID]"
   , 'type' => "text"
   , 'mandatory' => true
   , 'width' => "50%"
);

/*
$grdMX->Columns[] = Array(
   'title'=>"Title Local"
   , 'field' => "actTitleLocal"
   , 'type' => "text"
   , 'width' => "25%"
);
*/

$grdMX->Columns[] = Array(
        'title' => ""
        , 'field' => "actEntityID"
        , 'default' => $entID
        , 'type' => "text"
);

$grdMX->Columns[] = Array(
   'title' => $intra->translate("New Status")
   , 'field' => 'actNewStatusIDs'
   , 'type' => "text"
   , 'disabled' => true
);
$grdMX->Columns[] = Array(
         'title'=>$intra->translate("Autocmplt?")
         , 'field'=>'actFlagAutocomplete'
         , 'type'=>"checkbox"
      );
$grdMX->Columns[] = Array(
         'title'=>"ETA/ETD?"
         , 'field'=>'actFlagHasEstimates'
         , 'type'=>"checkbox"
      );
$grdMX->Columns[] = Array(
         'title'=>"ATD=ATA?"
         , 'field'=>'actFlagDepartureEqArrival'
         , 'type'=>"checkbox"
      );
$grdMX->Columns[] = Array(
   'title'=>$intra->translate("Ord")
   , 'field'=>"actPriority"
   , 'type'=>"text"
   , 'width'=>"30"
);
$sqlRol = "SELECT * FROM stbl_role";
$rsRol = $oSQL->do_query($sqlRol);
while ($rwRol = $oSQL->fetch_array($rsRol)){
   $fld = "rlaID_".$rwRol["rolID"];
   $grdMX->Columns[] = Array(
         'title'=>$rwRol["rolID"]
         , 'field'=>$fld
         , 'type'=>"checkbox"
      );
   $tbl = "RLA_".$rwRol["rolID"];
   $roleFields .= ", (CASE WHEN $tbl.rlaID IS NULL THEN 0 ELSE 1 END) as ".$fld;
   $roleJoins .=" LEFT OUTER JOIN stbl_role_action $tbl ON $tbl.rlaActionID=actID AND $tbl.rlaRoleID='".$rwRol["rolID"]."'";
}

$grdMX->Columns[] = Array(
   'title'=>$intra->translate("Title Past tense")
   , 'field' => "actTitlePast"
   , 'type' => "text"
   , 'width' => "50%"
);
/*
$grdMX->Columns[] = Array(
   'title'=>"Title Past tense local"
   , 'field' => "actTitlePastLocal"
   , 'type' => "text"
   , 'width' => "25%"
);
*/
$grdMX->Columns[] = Array(
   'title'=>$intra->translate("Cmnt?")
   , 'field'=>"actFlagComment"
   , 'type'=>"checkbox"
   , 'width'=>"16"
);
$grdMX->Columns[] = Array(
   'title'=>"X"
   , 'field'=>"actFlagDeleted"
   , 'type'=>"checkbox"
   , 'width'=>"16"
);




switch ($DataAction){
    case "update":
        $sql = Array();
        
        $oSQL->q("START TRANSACTION");

        $gridSTA->Update();
        $arrSTAToDelete = explode("|", $_POST["inp_sta_deleted"]);
        for($i=0;$i<count($arrSTAToDelete);$i++)
           if ($arrSTAToDelete[$i]!=""){
              $arrSta = explode("##", $arrSTAToDelete[$i]);
              $staID = $arrSta[0];
              $sqlACT = "SELECT atsID FROM stbl_action_status 
			  	INNER JOIN stbl_action ON actID=atsActionID
				WHERE (atsNewStatusID='".$staID."' OR atsOldStatusID='".$staID."') AND actEntityID='$entID'";
              //echo $sqlACT; die();
              $rsActToDel = $oSQL->do_query($sqlACT);
              while ($rwATS = $oSQL->fetch_array($rsActToDel)){
				  $sqlATS = "DELETE FROM tbl_action_status WHERE atsID='{$rwATS['atsID']}'";
				  $oSQL->q($sqlATS);
			  }
              $sql[] = "DELETE FROM stbl_status_attribute WHERE satStatusID='".$staID."' AND satEntityID='$entID'";
           }
        
        if (!$easyAdmin){
            
            include_once eiseIntraAbsolutePath.'/inc_entity.php';
            $ent = new eiseEntity($oSQL, $intra, $entID);

            $atrTableName = $ent->rwEnt['entTable'];
            $arrTableInfo = $intra->getTableInfo('', $atrTableName);

            $arrSQLAlter = array();
            $lastAddedColumn = '';

            foreach ($_POST['inp_atr_updated'] as $ix => $value) {
                if (!$value) //if not updated, skip it
                    continue;
                if ($_POST['atrID_id'][$ix]=='') { // if new one

                    $atrID = $entID.preg_replace('/([^a-z0-9]+)/i', '', $_POST['atrTitle'][$ix]);
                    $_POST['atrID'][$ix] = $atrID;
                    $sqlA = $ent->getEntityTableALTER($atrID, $_POST['atrType'][$ix], 'add');
                    if ($sqlA){
                        $arrSQLAlter = array_merge($arrSQLAlter, $sqlA);
                        $lastAddedColumn = $atrID;
                    }

                } else { // if old one change its type

                    $newIntraDataType = $intra->arrAttributeTypes[$_POST['atrType'][$ix]];
                    $oldIntraDataType = $arrTableInfo['columns'][$_POST['atrID'][$ix]]['DataType'];

                    if ($newIntraDataType!=$oldIntraDataType){
                        $sqlA = $ent->getEntityTableALTER($_POST['atrID'][$ix], $_POST['atrType'][$ix], 'change');
                        if ($sqlA)
                            $arrSQLAlter = array_merge($arrSQLAlter, $sqlA);
                    }

                }
            }

            if ($lastAddedColumn!=''){
                $strCodeMaster .= "CHANGE ".$entID."InsertBy ".$entID."InsertBy VARCHAR(255) NULL DEFAULT NULL  AFTER {$lastAddedColumn}";
                $strCodeMaster .= "\r\n, CHANGE ".$entID."InsertDate ".$entID."InsertDate DATETIME NULL DEFAULT NULL AFTER ".$entID."InsertBy";
                $strCodeMaster .= "\r\n, CHANGE ".$entID."EditBy ".$entID."EditBy VARCHAR(255) NULL DEFAULT NULL AFTER ".$entID."InsertDate";
                $strCodeMaster .= "\r\n, CHANGE ".$entID."EditDate ".$entID."EditDate DATETIME NULL DEFAULT NULL AFTER ".$entID."EditBy";
                
                $strCodeLog .= "CHANGE l".$entID."InsertBy l".$entID."InsertBy VARCHAR(255) NULL DEFAULT NULL  AFTER l{$lastAddedColumn}";
                $strCodeLog .= "\r\n, CHANGE l".$entID."InsertDate l".$entID."InsertDate DATETIME NULL DEFAULT NULL AFTER l".$entID."InsertBy";
                $strCodeLog .= "\r\n, CHANGE l".$entID."EditBy l".$entID."EditBy VARCHAR(255) NULL DEFAULT NULL AFTER l".$entID."InsertDate ";
                $strCodeLog .= "\r\n, CHANGE l".$entID."EditDate l".$entID."EditDate DATETIME NULL DEFAULT NULL AFTER l".$entID."EditBy ";
                
                $arrSQLAlter[] = "ALTER TABLE ".$rwEnt["entTable"]."\r\n".$strCodeMaster.";";
                $arrSQLAlter[] = "ALTER TABLE ".$rwEnt["entTable"]."_log\r\n".$strCodeLog.";";
            }

        }

        $gridATR->Update($_POST);
        if(!$easyAdmin && count($arrSQLAlter)>0){
            foreach ($arrSQLAlter as $sql_) {
                $oSQL->q($sql_);
            }
            $sqlVer = "INSERT INTO stbl_version (
                    verNumber
                    , `verDesc`
                    , `verDate`
                ) VALUES (
                    ".$oSQL->d('SELECT MAX(verNumber)+1 FROM stbl_version')."
                    , ".$oSQL->e(implode(";\r\n", $arrSQLAlter))."
                    ,  NOW());";
            $oSQL->q($sqlVer);
        }
        
        //update action matrix
        $arrToDelete = explode("|", $_POST["inp_act_deleted"]);
        for($i=0;$i<count($arrToDelete);$i++)
           if ($arrToDelete[$i]!=""){
              $sql[] = "DELETE FROM stbl_action WHERE actID='".$arrToDelete[$i]."'";
              $sql[] = "DELETE FROM stbl_role_action WHERE rlaActionID='".$arrToDelete[$i]."'";
              $sql[] = "DELETE FROM stbl_action_attribute WHERE aatActionID='".$arrToDelete[$i]."'";
           }
        
        $sqlRol = "SELECT * FROM stbl_role WHERE rolID<>1";
        $rsRol = $oSQL->do_query($sqlRol);
        while ($rwRol = $oSQL->fetch_array($rsRol))
            $arrRolIDs[] = $rwRol["rolID"];
        
        for ($i=0;$i<count($_POST["actID"]);$i++)
           if ($_POST["actTitle"][$i]!=""){
              if ($_POST["actID"][$i]==""){
                 $sql[] = "INSERT INTO `stbl_action`
                    (
                      `actEntityID`,
                      `actTitle{$strLocal}`,
                      `actTitlePast{$strLocal}`,
                      `actDescription`,
                      `actDescriptionLocal`,
                      `actFlagDeleted`,
                      `actPriority`,
                      `actFlagComment`,
                      actFlagHasEstimates,
                      actFlagDepartureEqArrival,
                      `actInsertBy`,`actInsertDate`,`actEditBy`,`actEditDate`
                    ) VALUE (
                      '$entID',
                      ".$oSQL->escape_string($_POST["actTitle"][$i]).",
                      ".$oSQL->escape_string($_POST["actTitleLocal"][$i]).",
                      ".$oSQL->escape_string($_POST["actTitlePast"][$i]).",
                      ".$oSQL->escape_string($_POST["actTitlePastLocal"][$i]).",
                      ".(int)($_POST["actFlagDeleted"][$i]).",
                      ".(int)($_POST["actPriority"][$i]=="" ? "0" : $_POST["actPriority"][$i]).",
                      ".(int)($_POST["actFlagComment"][$i]).",
                      ".(int)($_POST["actFlagHasEstimates"][$i]).",
                      ".(int)($_POST["actFlagDepartureEqArrival"][$i]).",
                      '$usrID', NOW(), '$usrID', NOW()
                    );";
                 $sql[] = "SET @actID=LAST_INSERT_ID()";
              } else {
                 $sql[] = "UPDATE stbl_action SET 
                      actTitle{$strLocal}=".$oSQL->escape_string($_POST["actTitle"][$i]).",
                      actTitlePast{$strLocal}=".$oSQL->escape_string($_POST["actTitlePast"][$i]).",
                      actFlagDeleted=".($_POST["actFlagDeleted"][$i]).",
                      actPriority=".($_POST["actPriority"][$i]=="" ? "0" : $_POST["actPriority"][$i]).",
                      actFlagComment=".($_POST["actFlagComment"][$i]).",
                      actFlagHasEstimates=".($_POST["actFlagHasEstimates"][$i]).",
                      actFlagDepartureEqArrival=".($_POST["actFlagDepartureEqArrival"][$i]).",
                      actEditBy='$usrID', actEditDate=NOW()
                     WHERE actID='".$_POST["actID"][$i]."'";
                 $sql[] = "SET @actID='".$_POST["actID"][$i]."'";
              }
              
              $sql[] = "DELETE FROM stbl_role_action WHERE rlaActionID=@actID";
              for ($j=0;$j<count($arrRolIDs);$j++){
                 if ($_POST["rlaID_".$arrRolIDs[$j]][$i]=="1"){
                   $sql[] = "INSERT INTO stbl_role_action (
                      rlaRoleID
                      , rlaActionID
                      )VALUES(
                      '".$arrRolIDs[$j]."'
                      , @actID
                      )";
                 }
              }
           }
        
       /*
        echo "<pre>";
        print_r($_POST);
        print_r($sql);
        echo "</pre>";
        die();
//        */
        
        for ($i=0; $i<count($sql); $i++)
             $rs = $oSQL->do_query($sql[$i]);
            
        $oSQL->q("COMMIT");

       SetCookie("UserMessage", $entID."  updated");
       header("Location: ".$_SERVER["PHP_SELF"]."?dbName=$dbName&entID=".urlencode($entID));
    
        
        die();
        break;
    default:
        break;
}

if ($easyAdmin){
    $arrActions[]= Array ("title" => "Database"
	   , "action" => "database_form.php?dbName=$dbName"
	   , "class"=> "ss_arrow_left"
	);
    $arrActions[]= Array ("title" => "Entity-related tables"
	   , "action" => "codegen_form.php?entID=$entID&dbName=$dbName&tblName=".$rwEnt["entTable"]."&toGen=EntTables"
	   , "class"=> "ss_table_multiple"
	);
    $arrActions[]= Array ("title" => "Entity Report"
	   , "action" => "codegen_form.php?entID=$entID&dbName=$dbName&tblName=".$rwEnt["entTable"]."&toGen=EntityReport"
	   , "class"=> "ss_page_white_word"
	);
	/*
    $arrActions[]= Array ("title" => "Check Status Log"
	   , "action" => "codegen_form.php?entID=$entID&dbName=$dbName&tblName=".$rwEnt["entTable"]."&toGen=StatusLogCheck"
	   , "class"=> "script"
	);
	*/
}

include eiseIntraAbsolutePath."inc-frame_top.php";
?>

<style>
.field_title_top {
    float: left;
    padding-right: 5px;
    padding-top: 3px;
}

.eg_controlbar {
    margin: 0;
}
</style>

<script>
$(document).ready(function(){  
	easyGridInitialize();
});
</script>

<form action="<?php  echo $_SERVER["PHP_SELF"] ; ?>" method="POST" class="eiseIntraForm">
<input type="hidden" name="DataAction" value="update">
<input type="hidden" name="dbName" value="<?php  echo $dbName ; ?>">
<input type="hidden" name="entID" value="<?php  echo $entID ; ?>">

<fieldset>
<legend><?php  echo $rwEnt["entTitle{$intra->local}"] ; ?></legend>
<table width="100%" style="display:inline;">

<tr>
<td width="30%"><div class="field_title_top"><?php echo $intra->translate("Statuses") ?>:</div>
<?php 
$sqlSta = "SELECT * FROM stbl_status WHERE staEntityID='".$entID."' ORDER BY staID";
$rsSta = $oSQL->do_query($sqlSta);
while($rwSta = $oSQL->fetch_array($rsSta)){
   $rwSta['staID_id'] = $rwSta['staID']."##".$entID;
   $gridSTA->Rows[] = $rwSta;
}
$gridSTA->Execute();
 ?>

</td>


<td width="70%">
<div class="field_title_top"><?php echo $intra->translate("Attributes") ?>:</div>
<?php 
$sqlATR = "SELECT * FROM stbl_attribute WHERE atrEntityID='$entID' ORDER BY atrOrder";
$rsATR = $oSQL->do_query($sqlATR);
while ($rwATR = $oSQL->fetch_array($rsATR)){
    $rwATR['atrID_id'] = $rwATR['atrID']."##".$entID;
    $gridATR->Rows[] = $rwATR;
}

$gridATR->Execute();
 ?>
</td>

</tr>

<tr>
<td colspan="2"><div class="field_title_top"><?php echo $intra->translate('Action matrix') ?>:</div>
<?php 
$sqlAct = "SELECT actID
    , actTitle{$strLocal} as actTitle
    , actTitlePast{$strLocal} as actTitlePast
    , actPriority
    , actFlagComment
    , actFlagDeleted
	, actFlagAutocomplete
  , actFlagHasEstimates
	, actFlagDepartureEqArrival
    ".$roleFields."
 , (SELECT GROUP_CONCAT(DISTINCT staTitle SEPARATOR ', ') FROM stbl_action_status INNER JOIN stbl_status ON staEntityID='$entID' AND staID=atsOldStatusID WHERE atsActionID=actID) as actOldStatusIDs
 , (SELECT GROUP_CONCAT(DISTINCT staTitle SEPARATOR ', ') FROM stbl_action_status INNER JOIN stbl_status ON staEntityID='$entID' AND staID=atsNewStatusID WHERE atsActionID=actID) as actNewStatusIDs
 , (SELECT MIN(staID) FROM stbl_action_status INNER JOIN stbl_status ON staEntityID='$entID' AND staID=atsOldStatusID WHERE atsActionID=actID) as minStaID
 FROM stbl_action ".$roleJoins." 
 WHERE actEntityID='$entID' ORDER BY minStaID, actPriority";
$rsAct = $oSQL->do_query($sqlAct);
while ($rwAct = $oSQL->fetch_array($rsAct)){
   $grdMX->Rows[] = $rwAct;
}

$grdMX->Execute();
 ?>
</td>
</tr>

<tr>
<td colspan="2" align="center">
<input type="submit" class="eiseIntraSubmit" value="<?php echo $intra->translate('Save') ?>" onclick="return checkForm();" style="margin:20px;">
</td>
<tr>
</table>
</fieldset>

</form>

<script>
function checkForm(){
   return true;
}
</script>

<?php
include eiseIntraAbsolutePath."inc-frame_bottom.php";
 ?>