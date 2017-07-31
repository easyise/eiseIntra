<?php
include 'common/auth.php';

$intra->requireComponent('grid');

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
        , 'href' => "entity_form.php?entID=[entID]"
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
        , 'href' => "entity_form.php?entID=[entID]"
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

$gridENT->redirectTo = $_SERVER['PHP_SELF'];
$gridENT->msgToUser = $intra->translate('Entities are updated');

$intra->dataAction('update', $gridENT);


$arrActions[]= Array ('title' => 'Add Row'
	   , 'action' => "#add"
	   , 'class'=> 'ss_add'
	);
    
include eiseIntraAbsolutePath."inc_top.php";
?>
<script>
$(document).ready(function(){  
	$('.eiseGrid').eiseGrid();
    $('a[href=#add]').click(function(){
        $('#ent').eiseGrid('addRow');
        return false;
    })
});
</script>


<form action="<?php  echo $_SERVER["PHP_SELF"] ; ?>" method="POST" class="eiseIntraForm">
<input type="hidden" name="DataAction" value="update">
<input type="hidden" name="dbName" value="<?php  echo $oSQL->dbname ; ?>">

<fieldset><legend><?php echo $intra->translate('Entities') ?></legend>
<?php
$sqlENT = "SELECT * FROM stbl_entity";
$rsENT = $oSQL->do_query($sqlENT);
while ($rwENT = $oSQL->fetch_array($rsENT)){
    $rwENT['entID_id'] = $rwENT['entID'];
    $gridENT->Rows[] = $rwENT;
}

$gridENT->Execute();
?>
<div style="text-align: center;"><input type="submit" value="Save" class="eiseIntraSubmit"></div>

</fieldset>

</form>
<?php
include eiseIntraAbsolutePath."inc_bottom.php";
?>
