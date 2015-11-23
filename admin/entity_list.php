<?php
include 'common/auth.php';

$DataAction  = (isset($_POST['DataAction']) ? $_POST['DataAction'] : $_GET['DataAction'] );

include commonStuffAbsolutePath.'eiseGrid2/inc_eiseGrid.php';
$arrJS[] = commonStuffRelativePath.'eiseGrid2/eiseGrid.jQuery.js';
$arrCSS[] = commonStuffRelativePath.'eiseGrid2/themes/default/screen.css';

$oSQL->dbname=(isset($_POST["dbName"]) ? $_POST["dbName"] : $_GET["dbName"]);
$oSQL->select_db($oSQL->dbname);
$dbName = $oSQL->dbname;

$gridENT = new easyGrid($oSQL
        ,'ent'
        , Array(
                'rowNum' =>40
                , 'arrPermissions' => Array('FlagWrite'=>true)
                , 'strTable' => 'stbl_entity'
                , 'strPrefix' => 'ent'
                , 'flagStandAlone' => true
                )
        );

$gridENT->Columns[]  = Array(
        'type' => 'row_id'
        , 'field' => 'entID_id'
        );
        
$gridENT->Columns[] = Array(
        'title' => "entID"
        , 'field' => "entID"
        , 'type' => "text"
        , 'mandatory' => true
//        , 'disabled' => true
//        , 'href' => "entity_form.php?dbName=$dbName&entID=[entID]"
);        
/*
$gridENT->Columns[] = Array(
        'title' => "entID"
        , 'field' => "entID"
        , 'type' => "text"
        , 'href' => "entity_form.php?dbName=$dbName&entID=[entID]"
);
*/
$gridENT->Columns[] = Array(
        'title' => "Title"
        , 'field' => "entTitle"
        , 'type' => "text"
);$gridENT->Columns[] = Array(
        'title' => "Title (Mul)"
        , 'field' => "entTitleMul"
        , 'type' => "text"
);
$gridENT->Columns[] = Array(
        'title' => "Title (Local)"
        , 'field' => "entTitleLocal"
        , 'type' => "text"
);
$gridENT->Columns[] = Array(
        'title' => "Title (Local: Mul)"
        , 'field' => "entTitleLocalMul"
        , 'type' => "text"
);
$gridENT->Columns[] = Array(
        'title' => "Title (Local: Gen)"
        , 'field' => "entTitleLocalGen"
        , 'type' => "text"
);
$gridENT->Columns[] = Array(
        'title' => "Title (Local: Dat)"
        , 'field' => "entTitleLocalDat"
        , 'type' => "text"
);
$gridENT->Columns[] = Array(
        'title' => "Title (Local: Acc)"
        , 'field' => "entTitleLocalAcc"
        , 'type' => "text"
);
$gridENT->Columns[] = Array(
        'title' => "Title (Local: Ins)"
        , 'field' => "entTitleLocalIns"
        , 'type' => "text"
);
$gridENT->Columns[] = Array(
        'title' => "Title (Local: Abl)"
        , 'field' => "entTitleLocalAbl"
        , 'type' => "text"
);
$gridENT->Columns[] = Array(
        'title' => "entTable"
        , 'field' => "entTable"
        , 'type' => "text"
);

switch($DataAction){
    case "update":
        $gridENT->Update();
        //die();
        header("Location: ".$_SERVER["PHP_SELF"]."?dbName=$dbName");
        break;
    default:
        break;
}


$arrActions[]= Array ('title' => 'Add Row'
	   , 'action' => "#add"
	   , 'class'=> 'ss_add'
	);
    
include eiseIntraAbsolutePath."inc-frame_top.php";
?>
<script>
$(document).ready(function(){  
	$('.eiseGrid').eiseGrid();
    $('a[href=#add]').click(function(){
        $('#ent').eiseGrid('addRow');
    })
});
</script>

<h1>Entities</h1>

<form action="<?php  echo $_SERVER["PHP_SELF"] ; ?>" method="POST">
<input type="hidden" name="DataAction" value="update">
<input type="hidden" name="dbName" value="<?php  echo $dbName ; ?>">

<div class="panel">
<table width="100%">
<tr>
<td>

<?php
$sqlENT = "SELECT * FROM stbl_entity";
$rsENT = $oSQL->do_query($sqlENT);
while ($rwENT = $oSQL->fetch_array($rsENT)){
    $rwENT['entID_id'] = $rwENT['entID'];
    $gridENT->Rows[] = $rwENT;
}

$gridENT->Execute();
?>
</td>
</tr>
<tr>
<td align="center"><input type="submit" value="Save"></td>
</tr>
</table>
</div>

</form>
<?php
include eiseIntraAbsolutePath."inc-frame_bottom.php";
?>
