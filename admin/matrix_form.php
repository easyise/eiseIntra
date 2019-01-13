<?php
include 'common/auth.php';

$intra->requireComponent('batch','grid');

$DataAction  = (isset($_POST['DataAction']) ? $_POST['DataAction'] : $_GET['DataAction'] );

$gridPGR = new eiseGrid($oSQL
        , 'pgr'
        , array('arrPermissions' => Array('FlagWrite'=>$intra->arrUsrData['FlagWrite'])
                , 'strTable' => 'stbl_page_role'
                , 'strPrefix' => 'pgr'
                )
        );

$gridPGR->Columns[]  = Array(
            'type' => 'row_id'
            , 'field' => 'pgrID'
        );
$gridPGR->Columns[] = Array(
        'title' => $intra->translate("Page")
        , 'field' => "pagFullTitle"
        , 'type' => "text"
        , 'href' => 'page_form.php?dbName='.$dbName.'&pagID=[pgrPageID]'
        , 'static' => true
        , 'width' => '100%'
);
$gridPGR->Columns[] = Array(
        'field' => "pgrRoleID"
);
$gridPGR->Columns[] = Array(
        'field' => "pgrPageID"
);
$gridPGR->Columns[] = Array(
        'title' => $intra->translate("Read")
        , 'field' => "pgrFlagRead"
        , 'type' => "checkbox"
        , 'width' => '40px'
        , 'headerClickable' => true
);
$gridPGR->Columns[] = Array(
        'title' => $intra->translate("Write")
        , 'field' => "pgrFlagWrite"
        , 'type' => "checkbox"
        , 'width' => '40px'
        , 'headerClickable' => true
);
$gridPGR->Columns[] = Array(
        'title' => $intra->translate("Ins")
        , 'field' => "pgrFlagCreate"
        , 'type' => "checkbox"
        , 'headerClickable' => true
);
$gridPGR->Columns[] = Array(
        'title' => $intra->translate("Upd")
        , 'field' => "pgrFlagUpdate"
        , 'type' => "checkbox"
        , 'headerClickable' => true
);
$gridPGR->Columns[] = Array(
        'title' => $intra->translate("Del")
        , 'field' => "pgrFlagDelete"
        , 'type' => "checkbox"
        , 'headerClickable' => true
);

switch($DataAction){
    case "update":
        $gridPGR->Update();
        $intra->redirect("Data is updated", $_SERVER["PHP_SELF"]);
    default:
        break;
}


$rolID = (isset($_GET['rolID']) ? $_GET['rolID'] : $_COOKIE['rolID']);

$rolID = $oSQL->d('SELECT rolID FROM stbl_role WHERE rolID='.$oSQL->e($rolID));

if(isset($_GET['rolID']) && $rolID)
    setcookie('rolID', $rolID, 0, $_SERVER['PHP_SELF']);

if(!$rolID){
    $rolID = 'admin';
}

$arrActions[]= Array ('title' => $intra->translate('Save')
       , 'action' => "#save"
       , 'class'=> 'ss_disk'
    );
$arrActions[]= Array ("title" => "Get dump"
     , "action" => "javascript:$(this).eiseIntraBatch('database_act.php?DataAction=dump&what=security&dbName={$dbName}&flagDonwloadAsDBSV=0')"
     , "class"=> "ss_cog_edit"
  );
$arrActions[]= Array ("title" => "Download dump"
       , "action" => "database_act.php?DataAction=dump&what=security&dbName={$dbName}&flagDonwloadAsDBSV=1"
       , "class"=> "ss_cog_go"
    );

$intra->conf['flagBatchNoAutoclose'] = true;

include eiseIntraAbsolutePath.'inc_top.php';
?>
<style type="text/css">
#rolID {
    width: auto;
    display: inline;
    clear: none;
    font-size: 15px;
}
td.pgr-pagFullTitle > div {
    white-space: pre;
}
</style>

<script>
$(document).ready(function(){  
    $('.eiseGrid').eiseGrid();
    $('#rolID').change(function(){
        location.href='matrix_form.php?rolID='+encodeURIComponent($(this).val());
    });
    $('a[href=#save]').click(function(){
        $('.eiseGrid').eiseGrid('save');
        return false;
    })
});
</script>

<div class="eiseIntraForm">
<fieldset><legend><?php echo $intra->translate('Permissions for role').': '.$intra->field(null, 'rolID', $rolID, array('type' => "select"
        , 'source' => 'stbl_role', 'prefix'=>'rol')
); ?></legend>
<?php
$sqlPGR = "SELECT PG1.pagID
        , PG1.pagParentID
        , PG1.pagTitle
        , PG1.pagFile
        , PG1.pagIdxLeft
        , PG1.pagIdxRight
        , PG1.pagFlagShowInMenu
        , COUNT(PG2.pagID) as iLevelInside
        , PGR.pgrID
        , PGR.pgrPageID
        , PGR.pgrRoleID
        , PGR.pgrFlagRead
        , PGR.pgrFlagWrite
        , PGR.pgrFlagCreate
        , PGR.pgrFlagUpdate
        , PGR.pgrFlagDelete
        , ROL.rolID
        , ROL.rolTitle
FROM stbl_page PG1
        INNER JOIN stbl_page PG2 ON PG2.pagIdxLeft<=PG1.pagIdxLeft AND PG2.pagIdxRight>=PG1.pagIdxRight
        LEFT OUTER JOIN stbl_page_role PGR INNER JOIN stbl_role ROL ON PGR.pgrRoleID=ROL.rolID
        ON PG1.pagID = PGR.pgrPageID AND pgrRoleID=".$oSQL->e($rolID)."
GROUP BY 
PG1.pagID
        , PG1.pagParentID
        , PG1.pagTitle
        , PG1.pagFile
        , PG1.pagIdxLeft
        , PG1.pagIdxRight
        , PG1.pagFlagShowInMenu
        , PGR.pgrID
        , PGR.pgrPageID
        , PGR.pgrRoleID
        , PGR.pgrFlagRead
        , PGR.pgrFlagWrite
        , PGR.pgrFlagCreate
        , PGR.pgrFlagUpdate
        , PGR.pgrFlagDelete
        , ROL.rolID
        , ROL.rolTitle
ORDER BY PG1.pagIdxLeft";
$rsPGR = $oSQL->do_query($sqlPGR);
while ($rwPGR = $oSQL->fetch_array($rsPGR)){
    $rwPGR['pagFullTitle'] = str_repeat('    ', $rwPGR['iLevelInside']).($rwPGR['pagTitle'.$intra->local] ? $rwPGR['pagTitle'.$intra->local] : $rwPGR['pagTitle'])
        .($rwPGR['pagTitle'.$intra->local] && $rwPGR['pagFile'] ? " ({$rwPGR['pagFile']})" : $rwPGR['pagFile']);
    $gridPGR->Rows[] = $rwPGR;
}

$gridPGR->Execute();
?>
</fieldset>

</div><?php
include eiseIntraAbsolutePath.'inc_bottom.php';