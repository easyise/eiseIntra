<?php
include 'common/auth.php';

$intra->requireComponent('grid');


if($_POST['DataAction']=='insert'){

    $oSQL->startProfiling();

    // 1. create entity table
    $oSQL->q("DROP TABLE IF EXISTS {$_POST['entTable']}");
    $entPrefix = $_POST['entPrefix'];
    $sqlCREATE = "CREATE TABLE {$_POST["entTable"]} (
        {$entPrefix}ID VARCHAR(50) NOT NULL,
        {$entPrefix}StatusID INT UNSIGNED NULL DEFAULT NULL,
        {$entPrefix}ActionID VARCHAR(50) NULL DEFAULT NULL,
        {$entPrefix}ActionLogID VARCHAR(50) NULL DEFAULT NULL,
        {$entPrefix}StatusActionLogID VARCHAR(36) NULL DEFAULT NULL,
        {$entPrefix}Title VARCHAR(255) NULL DEFAULT NULL,
        {$entPrefix}TitleLocal VARCHAR(255) NULL DEFAULT NULL,
        {$entPrefix}FlagDeleted TINYINT(4) NOT NULL DEFAULT 0,
        {$entPrefix}InsertBy VARCHAR(255) NULL DEFAULT NULL,
        {$entPrefix}InsertDate DATETIME NULL DEFAULT NULL,
        {$entPrefix}EditBy VARCHAR(255) NULL DEFAULT NULL,
        {$entPrefix}EditDate DATETIME NULL DEFAULT NULL,
        PRIMARY KEY (`{$entPrefix}ID`)
        )
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB";
    $oSQL->q($sqlCREATE);

    $oSQL->q("START TRANSACTION");

    $entID = $oSQL->d("SELECT MAX(entID) FROM stbl_entity")+1;

    # 2. create entity record
    $sqlEnt = "INSERT INTO stbl_entity SET
        entID = {$entID}
        , entTitle = ".$oSQL->e($_POST['entTitle'])."
        , entTitleMul = ".$oSQL->e($_POST['entTitleMul'])."
        , entTitleLocal = ".$oSQL->e($_POST['entTitleLocal'])."
        , entMatrix = ".$oSQL->e($_POST['entMatrix'])."
        , entTitleLocalMul = ".$oSQL->e($_POST['entTitleLocalMul'])."
        , entTitleLocalGen = ".$oSQL->e($_POST['entTitleLocalGen'])."
        , entTitleLocalDat = ".$oSQL->e($_POST['entTitleLocalDat'])."
        , entTitleLocalAcc = ".$oSQL->e($_POST['entTitleLocalAcc'])."
        , entTitleLocalIns = ".$oSQL->e($_POST['entTitleLocalIns'])."
        , entTitleLocalAbl = ".$oSQL->e($_POST['entTitleLocalAbl'])."
        , entTable = ".$oSQL->e($_POST['entTable'])."
        , entPrefix = ".$oSQL->e($_POST['entPrefix'])."
        , entManagementRoles = ".$oSQL->e($_POST['entManagementRoles']);
    $oSQL->q($sqlEnt);

    $entPrefix = $_POST['entPrefix'];


    # 3. create Draft and Deleted statuses
    $staIDs = [$entID.'00', $entID.'05'];
    $staTitles = ['Draft', 'Deleted'];
    $staFlagCanDelete = [1, 0];
    foreach($staIDs as $i=>$staID){
        $sqlSTA = "INSERT INTO stbl_status SET
            staID = '{$staID}'
            , staEntityID = '{$entID}'
            , staTrackPrecision = 'datetime'
            , staTitle = ".$oSQL->e($staTitles[$i])."
            , staTitleMul = ".$oSQL->e($staTitles[$i])."
            , staTitleLocal = ".$oSQL->e($staTitles[$i])."
            , staTitleMulLocal = ".$oSQL->e($staTitles[$i])."
            , staFlagCanUpdate = 1
            , staFlagCanDelete = {$staFlagCanDelete[$i]}
            , staInsertBy = 'admin', staInsertDate = NOW(), staEditBy = 'admin', staEditDate = NOW()";
        $oSQL->q($sqlSTA);    
    }

    # 4. Add default attributes
    $aFields = [$entPrefix.'Title', $entPrefix.'TitleLocal', $entPrefix.'InsertBy', $entPrefix.'InsertDate', ];
    $aTitles = ['Name (Eng)', 'Name', 'Author', 'Created at', ];
    $aDTypes = ['text', 'text', 'ajax_dropdown', 'datetime', ];
    $aDSources = ['', '', 'svw_user', '', ];
    $aEditable = [1,1,0,0];

    foreach ($aFields as $i => $field) {
        $sqlAttr = "INSERT INTO stbl_attribute SET
            atrID = '{$aFields[$i]}'
            , atrEntityID = '$entID'
            , atrTitle = ".$oSQL->e($aTitles[$i])."
            , atrTitleLocal = ".$oSQL->e($aTitles[$i])."
            , atrType = ".$oSQL->e($aDTypes[$i])."
            , atrOrder = '".($i+1)."'
            , atrDataSource = ".$oSQL->e($aDSources[$i])."
            , atrInsertBy = 'admin', atrInsertDate = NOW(), atrEditBy = 'admin', atrEditDate = NOW()";
        $oSQL->q($sqlAttr);

        foreach ($staIDs as $j => $staID) {
            $sqlSAT = "INSERT INTO stbl_status_attribute SET
                satStatusID = {$staID}
                , satEntityID = {$entID}
                , satAttributeID = '{$aFields[$i]}'
                , satFlagEditable = {$aEditable[$i]}
                , satFlagShowInForm = 1
                , satFlagShowInList = 1
                , satInsertBy = '$intra->usrID', satInsertDate = NOW(), satEditBy = '$intra->usrID', satEditDate = NOW()";
            $oSQL->q($sqlSAT);
        }
        

    }
    

    // $oSQL->showProfileInfo();
    // die();

    $oSQL->q("COMMIT");


    # 4. redirect to the entity form
    $intra->redirect("Entity {$_POST['entTitle']} successfully created", "entity_form.php?entID={$entID}");

}

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
    $('a[href="#add"]').click(function(){

        $form = $(this).eiseIntraForm('createDialog'
                ,   {
                    title: 'Entity data:'
                    , action: location.pathname
                    , method: 'POST'
                    , width: '450px'
                    , fields:[
                        { name: 'DataAction', type: 'hidden', value: 'insert' }
                        , { name: 'entPrefix',          title: 'Prefix', type: 'text', required: true }
                        , { name: 'entTable',           title: 'Table', type: 'text', required: true }
                        , { name: 'entTitle',           title: 'Title (Eng)', type: 'text', required: true }
                        , { name: 'entTitleMul',        title: 'Title Multiple (Eng)', type: 'text', required: true }
                        , { name: 'entTitleLocal',      title: 'Title', type: 'text', required: true }
                        , { name: 'entTitleLocalMul',   title: 'Title Multiple', type: 'text', required: true }
                        , { name: 'entTitleLocalGen',   title: 'Родительный пад.', type: 'text', required: true }
                        , { name: 'entTitleLocalDat',   title: 'Дательный пад.', type: 'text', required: true }
                        , { name: 'entTitleLocalAcc',   title: 'Винитрельный пад.', type: 'text', required: true }
                        , { name: 'entTitleLocalIns',   title: 'Творительный пад.', type: 'text', required: true }
                        , { name: 'entTitleLocalAbl',   title: 'Предложный пад.', type: 'text', required: true }
                        , { name: 'entManagementRoles', title: 'Roles', type: 'text', required: true }
                    ]
                    , onsubmit: function(){
                        grid.spinner();
                        __do_upload(new FormData(this));
                        return false;
                    }
                });

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
