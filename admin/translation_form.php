<?php
include 'common/auth.php';

$DataAction = $_GET["DataAction"] ? $_GET["DataAction"] : $_POST["DataAction"];
$dbName = $_GET["dbName"] ? $_GET["dbName"] : $_POST["dbName"];
$oSQL->dbname = $dbName;
$oSQL->select_db($dbName);

include commonStuffAbsolutePath.'eiseGrid2/inc_eiseGrid.php';
$arrJS[] = commonStuffRelativePath.'eiseGrid2/eiseGrid.jQuery.js';
$arrCSS[] = commonStuffRelativePath.'eiseGrid2/themes/default/screen.css';

$gridSTR = new easyGrid($oSQL
        ,'str'
        , Array(
                'rowNum' =>40
                , 'arrPermissions' => Array('FlagWrite'=>true)
                , 'strTable' => 'stbl_translation'
                , 'strPrefix' => 'str'
                , 'flagStandAlone' => true
                )
        );

$gridSTR->Columns[]  = Array(
            'type' => 'row_id'
            , 'field' => 'strKey'
        );
$gridSTR->Columns[] = Array(
        'title' => $intra->translate("Key")
        , 'field' => "strKey_val"
        , 'type' => "text"
        , 'disabled' => true
        , 'mandatory' => true
);
$gridSTR->Columns[] = Array(
        'title' => $intra->translate("Value")
        , 'field' => "strValue"
        , 'type' => "text"
);

switch($DataAction){
    case "update":
        //echo "<pre>";
        //$oSQL->startProfiling();
        $gridSTR->Update();
        //$oSQL->showProfileInfo();
        SetCookie("UserMessage", "Data is updated");
        header("Location: ".$_SERVER["PHP_SELF"]."?dbName={$dbName}");
        break;
    default:
        break;
}


$arrActions[]= Array ('title' => $intra->translate('Add Row')
	   , 'action' => "javascript:easyGridAddRow('str')"
	   , 'class'=> 'ss_add'
	);
if ($_GET["show"]!="code"){
$arrActions[]= Array ('title' => $intra->translate('Get lang.php')
	   , 'action' => "{$_SERVER["PHP_SELF"]}?show=code&dbName={$dbName}"
	   , 'class'=> 'ss_script'
	);
} else {
$arrActions[]= Array ('title' => $intra->translate('Edit grid')
	   , 'action' => "{$_SERVER["PHP_SELF"]}?dbName={$dbName}"
	   , 'class'=> 'ss_application_form'
	);
}
include eiseIntraAbsolutePath."inc-frame_top.php";
?>
<script>
$(document).ready(function(){  
    $('.eiseGrid').eiseGrid();
});
</script>
<h1>Translation table</h1>

<?php 
if ($_GET["show"]=="code"){
    $sqlSTR = "SELECT * FROM stbl_translation";
    $rsSTR = $oSQL->do_query($sqlSTR);
    $strCode = "\$intra->lang = Array(\r\n";
    $strArrayCode = "";
    while ($rwSTR = $oSQL->fetch_array($rsSTR)){
        if($rwSTR["strValue"])
            $strArrayCode .= ($strArrayCode != "" ? "\r\n, " : "")."\"".addslashes($rwSTR["strKey"])."\" => \"".addslashes($rwSTR["strValue"])."\"";
    }
    $strCode .= $strArrayCode."\r\n";
    $strCode .= ");";
?>
<textarea rows=100 style="width: 100%;"><?php  echo htmlspecialchars($strCode) ; ?></textarea>

<?php
} else {
 ?>

<div class="panel">
<table width="100%">
<form action="<?php  echo $_SERVER["PHP_SELF"] ; ?>" method="POST">
<input type="hidden" name="DataAction" value="update">
<input type="hidden" name="dbName" value="<?php  echo $dbName ; ?>">
<tr><td>
<?php
$sqlSTR = "SELECT * FROM stbl_translation";
$rsSTR = $oSQL->do_query($sqlSTR);
while ($rwSTR = $oSQL->fetch_array($rsSTR)){
    $rwSTR['strKey_val'] = $rwSTR['strKey'];
    $gridSTR->Rows[] = $rwSTR;
}

$gridSTR->Execute();
?>
</td></tr>
<tr><td><div align="center"><input type="Submit" value="Update"></div></td></tr>
</table>
<?php 
}
 ?>

<?php
include eiseIntraAbsolutePath."inc-frame_bottom.php";
?>
