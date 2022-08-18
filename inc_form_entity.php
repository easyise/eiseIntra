<?php 
// test
$intra->requireComponent('jquery-ui', 'grid');

$DataAction = isset($_POST["DataAction"]) ? $_POST["DataAction"] : $_GET["DataAction"];

$entID = ($_POST["entID"] ? $_POST["entID"] : $_GET["entID"]);

$sqlEnt = "SELECT * FROM stbl_entity WHERE entID='".$entID."'";
$rsEnt = $oSQL->do_query($sqlEnt);
$rwEnt = $oSQL->fetch_array($rsEnt);

$easyAdmin = ($authmethod==='mysql');
$flagEiseIntra = (boolean) $oSQL->d("SHOW TABLES LIKE 'stbl_action_status'");
$flagEiseIntra = true;

$gridSTA = new eiseGrid($oSQL
        ,'sta'
        , Array(
                'arrPermissions' => Array('FlagWrite'=>$flagEiseIntra)
                , 'strTable' => 'stbl_status'
                , 'strPrefix' => 'sta'
                , 'flagStandAlone' => true
                , 'showControlBar' => true
                , 'controlBarButtons' => 'add|delete'
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
        , 'width' => '40px'
        , 'type' => "text"
);
$gridSTA->Columns[] = Array(
        'title' => ""
        , 'field' => "staEntityID"
        , 'default' => $entID
        , 'type' => "text"
);
$gridSTA->Columns[] = Array(
        'title' => $intra->translate("Status Title")
        , 'field' => "staTitle{$strLocal}"
        , 'width' => "100%"
        , 'type' => "text"
        , 'href' => ($flagEiseIntra ? "status_form.php?dbName=$dbName&staID=[staID]&entID=$entID" : null)
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
                'arrPermissions' => Array('FlagWrite'=>$flagEiseIntra, 'FlagDelete'=>(bool)$easyAdmin)
                , 'strTable' => 'stbl_attribute'
                , 'strPrefix' => 'atr'
                , 'flagStandAlone' => true
                , 'showControlBar' => true
                , 'controlBarButtons' => 'add|moveup|movedown|delete'
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
        'field' => "atrID_old"
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
            , 'width' => "100%"
            , 'href' => ($flagEiseIntra ? "attribute_form.php?dbName=$dbName&atrID=[atrID]&atrEntityID=$entID" : null)
    );
if($flagEiseIntra)
    $gridATR->Columns[] = Array(
            'title' => $intra->translate("Short Title")
            , 'field' => "atrShortTitle{$strLocal}"
            , 'type' => "text"
            , 'width' => '60px'
    );
} else {

    $gridATR->Columns[] = Array(
            'title' => $intra->translate("Title")
            , 'field' => "atrTitle{$intra->local}"
            , 'type' => "text"
            , 'width' => "100%"
            , 'mandatory' => true
    );

    $gridATR->Columns[] = Array(
        'title' => $intra->translate("Short Title")
        , 'field' => "atrShortTitle{$intra->local}"
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
        , 'type' => ($flagEiseIntra ? "combobox" : 'text')
        , 'arrValues' => $arrAvailableTypes
        , 'defaultText' => '-'
        , 'default' => "text"
        , "width" => "100px"
);
if($oSQL->d("SHOW TABLES LIKE 'stbl_uom'"))
$gridATR->Columns[] = Array(
        'title' => $intra->translate("UOM")
        , 'field' => "atrUOMTypeID"
        , 'type' => "combobox"
        , 'defaultText' => "-"
        , 'sql' => "SELECT uomTitle{$strLocal} as optText, uomID as optValue
            FROM stbl_uom
            WHERE uomType=''
            ORDER BY uomOrder"
        , 'width' => '25px'
);

if ($easyAdmin) {
    $gridATR->Columns[] = Array(
        'title' => $intra->translate("Def")
        , 'field' => "atrDefault"
        , 'type' => "text"
        , 'width' => '80px'
    );
    /*
    $gridATR->Columns[] = Array(
            'title' => $intra->translate("IfNull")
            , 'field' => "atrTextIfNull"
            , 'type' => "text"
            , 'width' => '60px'
    );
    $gridATR->Columns[] = Array(
            'title' => $intra->translate("Classes")
            , 'field' => "atrClasses"
            , 'type' => "text"
            , 'width' => '40px'
    );
    */
    $gridATR->Columns[] = Array(
            'title' => $intra->translate("Prg")
            , 'field' => "atrProgrammerReserved"
            , 'type' => "text"
            , 'width' => '80px'
    );
    /*
    $gridATR->Columns[] = Array(
            'title' => $intra->translate("CheckMask")
            , 'field' => "atrCheckMask"
            , 'type' => "text"
            , 'width' => '40px'
    );
    */
    $gridATR->Columns[] = Array(
            'title' => $intra->translate("Data Source")
            , 'field' => "atrDataSource"
            , 'type' => "text"
            , 'width' => '80px'
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
                            'arrPermissions' => Array('FlagWrite'=>$flagEiseIntra)
                            , 'strTable' => 'stbl_action'
                            , 'showControlBar' => true
                            , 'controlBarButtons' => 'add|moveup|movedown|delete'
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
   'title' => 'ID'
   , 'field' => "actID_"
   , 'type' => "number"
   , 'width' => '30px'
   , 'static' => true
);

$grdMX->Columns[] = Array(
   'title'=>$intra->translate("Title")
   , 'field' => "actTitle"
   , 'href' => ($flagEiseIntra ? "action_form.php?dbName=$dbName&actID=[actID]" : null)
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

if($oSQL->d("SHOW TABLES LIKE 'stbl_role_action'")){
    $sqlRol = "SELECT * FROM stbl_role";
    $rsRol = $oSQL->do_query($sqlRol);
    if($oSQL->n($rsRol<=5)){
        while ($rwRol = $oSQL->fetch_array($rsRol)){
           $fld = "rlaID_".$rwRol["rolID"];
           $grdMX->Columns[] = Array(
                 'title'=>$rwRol["rolID"]
                 , 'field'=>$fld
                 , 'type'=>"checkbox"
              );
           $tbl = "RLA_".$rwRol["rolID"];
           $roleFields .= ", (SELECT (CASE WHEN rlaID IS NULL THEN 0 ELSE 1 END) FROM stbl_role_action WHERE rlaRoleID='{$rwRol["rolID"]}') as ".$fld;
        }
    }
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
   'title'=>$intra->translate("Cmnt")
   , 'field'=>"actFlagComment"
   , 'type'=>"checkbox"
   , 'width'=>"30px"
);
$grdMX->Columns[] = Array(
   'title'=>"X"
   , 'field'=>"actFlagDeleted"
   , 'type'=>"checkbox"
);




switch ($DataAction){
    case "update":
        $sql = Array();
        
        $oSQL->q("START TRANSACTION");

        $oSQL->startProfiling();

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
				    $sqlATS = "DELETE FROM stbl_action_status WHERE atsID='{$rwATS['atsID']}'";
				    $oSQL->q($sqlATS);
			    }
                $sql[] = "DELETE FROM stbl_status_attribute WHERE satStatusID='".$staID."' AND satEntityID='$entID'";
            }
        
        include_once eiseIntraAbsolutePath.'/inc_entity.php';
        $ent = new eiseEntity($oSQL, $intra, $entID);

        $atrTableName = $ent->conf['entTable'];
        try {
            $arrTableInfo = $intra->getTableInfo($intra->getDBName(), $atrTableName);    
        } catch (Exception $e) {
            
        }
        

        $arrSQLAlter = array();
        $lastAddedColumn = '';

        foreach ($_POST['inp_atr_updated'] as $ix => $value) {
            if (!$value) //if not updated, skip it
                continue;

            if ($_POST['atrID_id'][$ix]=='') { // if new one

                $atrID = (!$easyAdmin 
                    ? $entID.preg_replace('/([^a-z0-9]+)/i', '', $_POST['atrTitle'][$ix]) 
                    : $_POST['atrID'][$ix] 
                    );
                $_POST['atrID'][$ix] = $atrID;
                $sqlA = $ent->getEntityTableALTER($atrID, $_POST['atrType'][$ix], 'add');
                if ($sqlA){
                    $arrSQLAlter = array_merge($arrSQLAlter, $sqlA);
                    $lastAddedColumn = $atrID;
                }

            } else { // if old one change its type

                if ($_POST['atrID_old'][$ix]!=$_POST['atrID'][$ix]){
                    $newAtrID = trim($_POST['atrID'][$ix]);
                    $sqlUpdSTA = "UPDATE stbl_status_attribute SET satAttributeID=".$oSQL->e($newAtrID)." 
                        WHERE satAttributeID=".$oSQL->e($_POST['atrID_old'][$ix])."
                            AND satEntityID=".$oSQL->e($entID);
                    $oSQL->q($sqlUpdSTA);
                    $sqlUpdAAT = "UPDATE stbl_action_attribute INNER JOIN stbl_action ON aatActionID=actID SET aatAttributeID=".$oSQL->e($newAtrID)." 
                        WHERE aatAttributeID=".$oSQL->e($_POST['atrID_old'][$ix])."
                            AND actEntityID=".$oSQL->e($entID);
                    $oSQL->q($sqlUpdAAT);

                }

                $newIntraDataType = $intra->arrAttributeTypes[$_POST['atrType'][$ix]];
                $oldIntraDataType = $arrTableInfo['columns'][$_POST['atrID'][$ix]]['DataType'];

                if ($newIntraDataType!=$oldIntraDataType){
                    $sqlA = $ent->getEntityTableALTER($_POST['atrID'][$ix], $_POST['atrType'][$ix], 'change');
                    if ($sqlA)
                        $arrSQLAlter = array_merge($arrSQLAlter, $sqlA);
                }

            }
        }

        $gridATR->Update($_POST);

        $sqlFixSAT = "DELETE FROM stbl_status_attribute WHERE satAttributeID IN (SELECT * FROM (SELECT DISTINCT satAttributeID FROM stbl_status_attribute 
          INNER JOIN stbl_status ON satStatusID=staID AND staEntityID='{$entID}'
          LEFT OUTER JOIN stbl_attribute ON satAttributeID=atrID
          WHERE atrID IS NULL) Q )";
        $oSQL->q($sqlFixSAT);

        $sqlFixAAT = "DELETE FROM stbl_action_attribute WHERE aatAttributeID IN (SELECT *  FROM (SELECT DISTINCT aatAttributeID FROM stbl_action_attribute 
          INNER JOIN stbl_action ON aatActionID=actID AND actEntityID='shp'
          LEFT OUTER JOIN stbl_attribute ON aatAttributeID=atrID
          WHERE atrID IS NULL) Q)";
        $oSQL->q($sqlFixAAT);

        if (!$easyAdmin){

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
        
        /* update action matrix */
        $arrToDelete = explode("|", $_POST["inp_act_deleted"]);
        for($i=0;$i<count($arrToDelete);$i++)
           if ($arrToDelete[$i]!=""){
              $sql[] = "DELETE FROM stbl_action WHERE actID='".$arrToDelete[$i]."'";
              $sql[] = "DELETE FROM stbl_role_action WHERE rlaActionID='".$arrToDelete[$i]."'";
              $sql[] = "DELETE FROM stbl_action_attribute WHERE aatActionID='".$arrToDelete[$i]."'";
           }
        
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
                      '$intra->usrID', NOW(), '$intra->usrID', NOW()
                    );";
                 $sql[] = "SET @actID=LAST_INSERT_ID()";
              } else {
                 $sql[] = "UPDATE stbl_action SET 
                      actTitle{$strLocal}=".$oSQL->escape_string($_POST["actTitle"][$i]).",
                      actTitlePast{$strLocal}=".$oSQL->escape_string($_POST["actTitlePast"][$i]).",
                      actFlagDeleted=".($_POST["actFlagDeleted"][$i]).",
                      actPriority=".($_POST["actPriority"][$i]=="" ? "0" : $_POST["actPriority"][$i]).",
                      actFlagComment=".($_POST["actFlagComment"][$i]).",
                      actEditBy='$intra->usrID', actEditDate=NOW()
                     WHERE actID='".$_POST["actID"][$i]."'";
                 $sql[] = "SET @actID='".$_POST["actID"][$i]."'";
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

       // $intra->debug($_POST);
       // $oSQL->showProfileInfo();die();
            
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
    if($flagEiseIntra)
        $arrActions[]= Array ("title" => "Entity-related tables"
    	   , "action" => "codegen_form.php?entID=$entID&dbName=$dbName&tblName=".$rwEnt["entTable"]."&toGen=EntTables"
    	   , "class"=> "ss_table_multiple"
    	);
    $arrActions[]= Array ("title" => "Entity Report"
	   , "action" => "codegen_form.php?entID=$entID&dbName=$dbName&tblName=".$rwEnt["entTable"]."&toGen=EntityReport"
	   , "class"=> "ss_page_white_word"
	);
$arrActions[]= Array ("title" => "Action Matrix"
         , "action" => "actionmatrix_form.php?entID=$entID&dbName=$dbName"
         , "class"=> "ss_chart_organisation "
      );	
}

include eiseIntraAbsolutePath."inc_top.php";
?>

<style>
.field_title_top {
    float: left;
    padding-right: 5px;
    padding-top: 3px;
}

#flds-sta, #flds-atr {
    display: inline-block;
    vertical-align: top;
}
#flds-sta {
    width: 29%;
}
#flds-atr {
    width: 69%;
}

#atr .eg-controlbar {
    margin-left: 135px;
}

#act .eg-controlbar {
    margin-left: 135px;
}

#atr th.atr-atrTitle {
    text-align: right;
    padding-right: 5px;
}
#sta th.sta-staTitle {
    text-align: right;
    padding-right: 5px;
}

#div-button {
    text-align: center;
}
</style>

<script>
$(document).ready(function(){  
    $('.eiseGrid').eiseGrid();


    var delta = $(document).height()-$(window).height()
        , paneHeight = $('body').eiseIntra('getPaneHeight')
        , proportion = [62, 38]
        , btnH = $('#div-button').outerHeight(true)
        , h1 = (paneHeight-btnH-40)*(proportion[0]/100)
        , h2 = (paneHeight-btnH-40)*(proportion[1]/100);
    if(delta > 0 ){
        $('#sta').eiseGrid('height', h1)
        $('#atr').eiseGrid('height', h1)
        $('#act').eiseGrid('height', h2)
    }

    $('#frm-entity').submit(function(){
        var retval = $('.eiseGrid').eiseGrid('validate');
        /*
        $('input[name="atrID[]"]').each(function(){
            console.log($(this).val());
        })
        */
        return retval;
    })
});
</script>

<form action="<?php  echo $_SERVER["PHP_SELF"] ; ?>" method="POST" class="eiseIntraForm" id="frm-entity">
<input type="hidden" name="DataAction" value="update">
<input type="hidden" name="dbName" value="<?php  echo $dbName ; ?>">
<input type="hidden" name="entID" value="<?php  echo $entID ; ?>">

<fieldset id="flds-sta">
<legend><?php  echo $rwEnt["entTitle{$intra->local}"].' ('.$rwEnt['entID'].')' ; ?></legend>
<?php 

if($oSQL->d("SHOW TABLES LIKE 'stbl_status'")){
    $sqlSta = "SELECT * FROM stbl_status WHERE staEntityID='".$entID."' ORDER BY staID";
    $rsSta = $oSQL->do_query($sqlSta);
    while($rwSta = $oSQL->fetch_array($rsSta)){
       $rwSta['staID_id'] = $rwSta['staID']."##".$entID;
       $gridSTA->Rows[] = $rwSta;
    }
    $gridSTA->Execute();
}

 ?>
</fieldset>

<fieldset id="flds-atr">

<legend><?php echo $intra->translate("Attributes") ?>:</legend>
<?php 
$sqlATR = "SELECT * FROM stbl_attribute WHERE atrEntityID='$entID' ORDER BY atrOrder";
$rsATR = $oSQL->do_query($sqlATR);
while ($rwATR = $oSQL->fetch_array($rsATR)){
    $rwATR['atrID_id'] = $rwATR['atrID']."##".$entID;
    $rwATR['atrID_old'] = $rwATR['atrID'];
    $gridATR->Rows[] = $rwATR;
}

$gridATR->Execute();
 ?>

</fieldset>

<fieldset id="flds-act"><legend><?php echo $intra->translate('Actions') ?>:</legend>
<?php 

$flagActionStatus = $oSQL->d("SHOW TABLES LIKE 'stbl_action_status'");

$sqlAct = "SELECT actID
    , actTitle{$strLocal} as actTitle
    , actTitlePast{$strLocal} as actTitlePast
    , actFlagComment
    , actFlagDeleted
    ".$roleFields."
".(
$flagActionStatus
? "
  , actPriority
  , actFlagAutocomplete
  , actFlagHasEstimates
    , actFlagDepartureEqArrival
  , (SELECT GROUP_CONCAT(DISTINCT staTitle{$intra->local} ORDER BY staID SEPARATOR ', ') FROM stbl_action_status INNER JOIN stbl_status ON staEntityID='$entID' AND staID=atsOldStatusID WHERE atsActionID=actID) as actOldStatusIDs
  , (SELECT GROUP_CONCAT(DISTINCT staTitle{$intra->local} ORDER BY staID SEPARATOR ', ') FROM stbl_action_status INNER JOIN stbl_status ON staEntityID='$entID' AND staID=atsNewStatusID WHERE atsActionID=actID) as actNewStatusIDs
  , (SELECT MIN(staID) FROM stbl_action_status INNER JOIN stbl_status ON staEntityID='$entID' AND staID=atsOldStatusID WHERE atsActionID=actID) as minStaID
  "
: "
  , (SELECT staTitle FROM stbl_status WHERE staEntityID='$entID' AND staID=actOldStatusID) as actOldStatusIDs
  , (SELECT staTitle FROM stbl_status WHERE staEntityID='$entID' AND staID=actNewStatusID) as actNewStatusIDs
  , actOldStatusID as minStaID
  "
  )."
 FROM stbl_action ".$roleJoins." 
 WHERE actEntityID='$entID' ORDER BY actFlagDeleted, minStaID".($flagEiseIntra ? ', actPriority' : '');
$rsAct = $oSQL->do_query($sqlAct);
while ($rwAct = $oSQL->fetch_array($rsAct)){
    $rwAct['actID_'] = $rwAct['actID'];
    $grdMX->Rows[] = $rwAct;
}
$grdMX->Execute();
 ?>
</fieldset>

<?php if ($flagEiseIntra): ?>
<div id="div-button">
<input type="submit" class="eiseIntraSubmit" value="<?php echo $intra->translate('Save') ?>" style="margin:20px;">
</div>
<?php endif ?>

</form>

<?php
include eiseIntraAbsolutePath."inc_bottom.php";
 ?>