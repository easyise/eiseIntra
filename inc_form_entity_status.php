<?php 
include commonStuffAbsolutePath.'eiseGrid/inc_eiseGrid.php';
$arrJS[] = commonStuffRelativePath.'eiseGrid/eiseGrid.js';
$arrCSS[] = commonStuffRelativePath.'eiseGrid/eiseGrid.css';

$DataAction  = (isset($_POST['DataAction']) ? $_POST['DataAction'] : $_GET['DataAction'] );

$dbName =(isset($_POST["dbName"]) ? $_POST["dbName"] : $_GET["dbName"]);
$oSQL->select_db($dbName);


$staID = (isset($_POST["staID"]) ? $_POST["staID"] : $_GET["staID"]);
$entID = (isset($_POST["entID"]) ? $_POST["entID"] : $_GET["entID"]);

$sqlSta = "SELECT * FROM stbl_status 
  INNER JOIN stbl_entity ON staEntityID=entID
  WHERE staID='$staID' AND staEntityID='$entID'";
$rsSta = $oSQL->do_query($sqlSta);
$rwSta = $oSQL->fetch_array($rsSta);
  
  

switch($DataAction){
    case "update":
       
       $sql[] = "UPDATE stbl_status SET
            staTitle = ".$oSQL->escape_string($_POST['staTitle'])."
            , staTitleLocal = ".$oSQL->escape_string($_POST['staTitleLocal'])."
            , staTrackPrecision = ".$oSQL->escape_string($_POST['staTrackPrecision'])."
            , staFlagCanUpdate = '".($_POST['staFlagCanUpdate']=='on' ? 1 : 0)."'
            , staFlagCanDelete = '".($_POST['staFlagCanDelete']=='on' ? 1 : 0)."'
			, staFlagDeleted = '".($_POST['staFlagDeleted']=='on' ? 1 : 0)."'
            , staEditBy = '$usrID', staEditDate = NOW()
            WHERE staID = '{$staID}'AND staEntityID = '{$entID}'";
       
	   if ($_POST['staFlagDeleted']=='on'){
	      $sql[] = "UPDATE stbl_action SET actFlagDeleted=1 
		     WHERE actEntityID='{$entID}' 
			 AND (actOldStatusID='".$_POST["staID"]."' OR actNewStatusID='".$_POST["staID"]."')";
	   }
	   
       $sql[] = "DELETE FROM stbl_status_attribute WHERE satStatusID='$staID' AND satEntityID='$entID'";
       
       for ($i=1; $i< count($_POST["atrID"]); $i++){
            $sql[] = "INSERT INTO stbl_status_attribute (
                satStatusID
                , satEntityID
                , satAttributeID
                , satFlagEditable
                , satFlagShowInForm
                , satFlagShowInList
                , satFlagTrackOnArrival
                , satInsertBy, satInsertDate, satEditBy, satEditDate
                ) VALUES (
                '$staID'
                , '$entID'
                , '".$_POST["atrID"][$i]."'
                , '".(int)$_POST["satFlagEditable"][$i]."'
                , '".(int)$_POST["satFlagShowInForm"][$i]."'
                , '".(int)$_POST["satFlagShowInList"][$i]."'
                , '".(int)$_POST["satFlagTrackOnArrival"][$i]."'
                , '$usrID', NOW(), '$usrID', NOW())";
          }
       
       /*
       echo "<pre>";
       print_r($sql);
       print_r($_POST);
       echo "</pre>";
       die();
//     */  
       
       for($i=0;$i<count($sql);$i++)
          $oSQL->do_query($sql[$i]);
          
       SetCookie("UserMessage", $entID." ".$intra->translate("is updated"));
       header("Location: ".$_SERVER["PHP_SELF"]."?dbName=$dbName&staID=$staID&entID=".urlencode($entID));
       
       die();
        break;
    default:
        break;
}


$arrActions[]= Array ('title' => $rwSta["entTitle"]
	   , 'action' => "entity_form.php?dbName=$dbName&entID=".$rwSta["entID"]
	   , 'class'=> 'ss_arrow_left'
	);
include eiseIntraAbsolutePath."inc-frame_top.php";
?>
<script>
$(document).ready(function(){  
	easyGridInitialize();
    
    $("th.sat_satFlagShowInForm, th.sat_satFlagEditable, th.sat_satFlagShowInList").css("cursor", "pointer");
    
    $("th.sat_satFlagShowInForm").click(function(){
        
        eiseGrid_find('sat').tbody.find("tr:not(.eg_template)").each(function(){
            $(this).find('input[name^=satFlagShowInForm_chk]').change().click();
        });
        
    })
    $("th.sat_satFlagEditable").click(function(){
        
        eiseGrid_find('sat').tbody.find("tr:not(.eg_template)").each(function(){
            $(this).find('input[name^=satFlagEditable_chk]').change().click();
        });
        
    })
    $("th.sat_satFlagShowInList").click(function(){
        
        eiseGrid_find('sat').tbody.find("tr:not(.eg_template)").each(function(){
            //$(this).find('input[name="satFlagShowInList[]"]').val(1);
            $(this).find('input[name^=satFlagShowInList_chk]').change().click();
        });
        
    })
    
});
</script>

<h1>Status: <?php  echo $rwSta["staTitle"] ; ?></h1>


<div class="panel">

<form action="<?php  echo $_SERVER["PHP_SELF"] ; ?>" method="POST">
<input type="hidden" name="DataAction" value="update">
<input type="hidden" name="dbName" value="<?php  echo $dbName ; ?>">
<input type="hidden" name="staID" value="<?php  echo $staID ; ?>">
<input type="hidden" name="entID" value="<?php  echo $rwSta["staEntityID"] ; ?>">

<table width="100%">

<tr>
<td width="50%">

<table width="100%">
<tr>
<td class="field_title">Title:</td>
<td><?php  echo $intra->showTextBox("staTitle", $rwSta["staTitle"]) ; ?></td>
</tr>
<tr>
<td class="field_title">Title Local:</td>
<td><?php  echo $intra->showTextBox("staTitleLocal", $rwSta["staTitleLocal"]) ; ?></td>
</tr>
<tr>
<td class="field_title">Precision:</td>
<td><?php  echo $intra->showCombo("staTrackPrecision", $rwSta["staTrackPrecision"], Array("date"=>"Date", "datetime"=>"Date+Time")) ; ?></td>
</tr>

<tr>
<td class="field_title">Update Allowed?</td>
<td><?php  echo $intra->showCheckBox("staFlagCanUpdate", $rwSta["staFlagCanUpdate"]) ; ?></td>
</tr>

<tr>
<td class="field_title">Delete allowed?</td>
<td><?php  echo $intra->showCheckBox("staFlagCanDelete", $rwSta["staFlagCanDelete"]) ; ?></td>
</tr>

<tr><td colspan="2"><hr></td></tr>

<tr>
<td class="field_title">Deleted?</td>
<td><?php  echo $intra->showCheckBox("staFlagDeleted", $rwSta["staFlagDeleted"]) ; ?></td>
</tr>

<tr>
<td class="field_title">Actions:</td>
<td>
<li><b>Leading to "<?php  echo $rwSta["staTitle"] ; ?>":</b></li>
<ul>
<?php 
$sqlAct = "SELECT DISTINCT actID, actTitle, actTitleLocal, actFlagDeleted FROM stbl_action_status INNER JOIN stbl_action ON actID=atsActionID
    WHERE atsNewStatusID='{$rwSta["staID"]}' AND actEntityID='{$entID}'
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
  ?>"><?php  echo $rwAct["actTitle"].($rwAct['actFlagDeleted'] ? " (deleted)" : "") ; ?></a></li>
<?php
}
 ?>
</ul>
<li><b>Leading from "<?php  echo $rwSta["staTitle"] ; ?>":</b></li>
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
  ?>"><?php  echo $rwAct["actTitle"].($rwAct['actFlagDeleted'] ? " (deleted)" : "") ; ?></a></li>
<?php
}
 ?>
</ul>

</td>
</tr>



</table>

</td>

<td width="50%">
<?php

$gridSAT = new easyGrid($oSQL
        ,'sat'
        , Array(
                'rowNum' =>40
                , 'arrPermissions' => Array('FlagWrite'=>true)
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
        'title' => "Attribute"
        , 'field' => "atrTitle{$intra->local}"
        , 'type' => "text"
        , 'disabled' => true
        , 'width' => "100%"
);

$gridSAT->Columns[] = Array(
        'title' => "Track?"
        , 'field' => "satFlagTrackOnArrival"
        , 'type' => "checkbox"
);

$gridSAT->Columns[] = Array(
        'title' => "In Form?"
        , 'field' => "satFlagShowInForm"
        , 'type' => "checkbox"
);
$gridSAT->Columns[] = Array(
        'title' => "Allowed"
        , 'field' => "satFlagEditable"
        , 'type' => "checkbox"
);
$gridSAT->Columns[] = Array(
        'title' => "In List?"
        , 'field' => "satFlagShowInList"
        , 'type' => "checkbox"
);


$sqlSAT = "SELECT * FROM stbl_attribute 
LEFT OUTER JOIN stbl_status_attribute ON atrID=satAttributeID AND satStatusID='$staID' AND satEntityID='$entID'
WHERE atrEntityID='$entID'
ORDER BY atrOrder";
$rsSAT = $oSQL->do_query($sqlSAT);
while ($rwSAT = $oSQL->fetch_array($rsSAT)){
    $rwSAT["satAllowed"] = ($rwSAT["satID"] ? "1" : "0");
    $gridSAT->Rows[] = $rwSAT;
}

$gridSAT->Execute();
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