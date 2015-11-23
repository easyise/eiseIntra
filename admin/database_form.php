<?php 
include "common/auth.php";
include "common/common.php";

$dbName = (isset($_POST["dbName"]) ? $_POST["dbName"] : $_GET["dbName"]);
$oSQL->select_db($dbName);

include commonStuffAbsolutePath.'eiseGrid2/inc_eiseGrid.php';
$arrJS[] = commonStuffRelativePath.'eiseGrid2/eiseGrid.jQuery.js';
$arrCSS[] = commonStuffRelativePath.'eiseGrid2/themes/default/screen.css';

$grid = new easyGrid($oSQL
                    ,"tbl"
                    , Array(
                            'arrPermissions' => Array('FlagWrite' => true)
                            , 'flagStandAlone' => false
                            )
                    );

$grid->Columns[]=Array(
	'field'=>"Name"
	,'type'=>'row_id'
);
$grid->Columns[]=Array(
    'field'=>"chk"
    , 'title' => "chk"
    , 'type' => "checkbox"
    , 'width' => '20px'
);
$grid->Columns[]=Array(
	'field'=>"Name"
    , 'title' => "Name"
	, 'type' => "text"
    , 'href' => "table_form.php?dbName=$dbName&tblName=[Name]"
    , 'width' => '30%'
);
$grid->Columns[] = Array(
   'title' => "Rows"
   , 'field' => "Rows"
   , 'type' => "numeric"
   , 'disabled'=>true
);
$grid->Columns[] = Array(
   'title' => "Data Size"
   , 'field' => "Data_length"
   , 'type' => "numeric"
   , 'disabled'=>true
);

$grid->Columns[] = Array(
   'title' => "Index Size"
   , 'field' => "Index_length"
   , 'type' => "numeric"
   , 'disabled'=>true
);

$grid->Columns[] = Array(
   'title' => "Created"
   , 'field' => "Create_time"
   , 'type' => "datetime" 
   , 'disabled'=>true  
);

$grid->Columns[] = Array(
   'title' => "Updated"
   , 'field' => "Update_time"
   , 'type' => "datetime"  
   , 'disabled'=>true 
);

$grid->Columns[] = Array(
   'title' => "Collation"
   , 'field' => "Collation"
   , 'type' => "text"
   , 'disabled'=>true
);

$grid->Columns[] = Array(
   'title' => "Engine"
   , 'field' => "Engine"
   , 'type' => "text"
   , 'disabled'=>true
);

$grid->Columns[] = Array(
   'title' => "Comment"
   , 'field' => "Comment"
   , 'type' => "text"
   , 'width' => '70%'
   , 'disabled'=>true
);

$arrFlags = Array();
if ($dbName!="") {
    $sqlDB = "SHOW TABLE STATUS FROM `$dbName`";
    $rsDB = $oSQL->do_query($sqlDB);

    $arrTables = array();
    while($rwDB = $oSQL->fetch_array($rsDB)){
        $grid->Rows[] = $rwDB;
        //print_r($rwDB);
        if ($rwDB["Name"]=="stbl_page") $arrFlags["hasPages"] = true;
        if ($rwDB["Name"]=="stbl_framework_version") $arrFlags["hasIntraDBSV"] = true;
        if ($rwDB["Name"]=="stbl_version") $arrFlags["hasDBSV"] = true;
        if ($rwDB["Name"]=="stbl_entity") {
            $arrFlags["hasEntity"] = true;
        }
        $arrTables[] = $rwDB['Name'];
    }
    
    if($arrFlags["hasIntraDBSV"]){
        $eiseIntraVersion = (int)$oSQL->d("SELECT MAX(fvrNumber) FROM `{$dbName}`.stbl_framework_version");
        include_once ( eiseIntraAbsolutePath."inc_dbsv.php" );
        $dbsv = new eiseDBSV(array('intra' => $intra
            , 'dbsvPath'=>eiseIntraAbsolutePath.".SQL"
            , 'DBNAME' => $dbName)
        );
        $eiseIntraVersionAvailable = $dbsv->getNewVersion();
    }
    if($arrFlags["hasDBSV"])
        $eiseDBSVersion = (int)$oSQL->d("SELECT MAX(verNumber) FROM `{$dbName}`.stbl_version");
    
    
$arrActions[]= Array ("title" => "Create table"
	   , "action" => "javascript:CreateNewTable();"
	   , "class" => "ss_add"
	);

if (isset($eiseIntraVersion) && $eiseIntraVersion < 100){
    $arrActions[]= Array ("title" => "Upgrade eiseIntra"
	   , "action" => "database_act.php?DataAction=upgrade&dbName=".urlencode($dbName)
	   , "class" => "ss_wrench_orange "
	);
}   
$arrActions[]= Array ("title" => "Dump Entities"
	   , "action" => "javascript:dumpSelectedTables('{$dbName}', 'entities')"
	   , "class" => "ss_cog_go  "
	);   
$arrActions[]= Array ("title" => "Dump Menu"
	   , "action" => "javascript:dumpSelectedTables('{$dbName}', 'security')"
	   , "class" => "ss_cog_go  "
	);  
$arrActions[]= Array ("title" => "Dump Selected Tables"
       , "action" => "javascript:dumpSelectedTables('{$dbName}')"
       , "class" => "ss_cog_go  "
    );  
if ($eiseDBSVersion){
    $arrActions[]= Array ("title" => "Get DBSV delta"
       , "action" => "database_act.php?DataAction=getDBSVdelta&dbName={$dbName}"
       , "class" => "ss_cog_add"
    ); 
    $arrActions[]= Array ("title" => "Remove DBSV delta"
       , "action" => "database_act.php?DataAction=removeDBSVdelta&dbName={$dbName}"
       , "class" => "ss_cog_delete confirm"
    ); 
}
    
}


include eiseIntraAbsolutePath."inc-frame_top.php";
?>
<style>
.eiseIntraField label {
    width: 34%;
}
.eiseIntraField .eiseIntraValue {
    width: 62%;
}
</style>

<form action="database_act.php" method="POST" class="eiseIntraForm">
<fieldset class="eiseIntraMainForm"><legend><?php echo ($dbName!="" ? "Database $dbName" : "New Database"); ?></legend>
<table width="100%">
<input type="hidden" name="dbName_key" value="<?php  echo $dbName ; ?>">
<input type="hidden" name="DataAction" value="create">
<tr>
<td width="40%">
<div class="eiseIntraField">
<label><?php  echo $intra->translate('Name') ; ?>:</label>
<?php echo $intra->showTextBox('dbName', $dbName, (array('FlagWrite' => !(bool)$dbName))) ?>
</div>

<div class="eiseIntraField">
<input type="checkbox" name="hasPages" id="hasPages"<?php  echo ($arrFlags["hasPages"] ? " checked" : ""); ?> style="width:auto;"><label for="hasPages">Has Pages</label><br>
</div>

<div class="eiseIntraField">
<input type="checkbox" name="hasEntity" id="hasEntity"<?php  echo ($arrFlags["hasEntity"] ? " checked" : ""); ?> style="width:auto;"><label for="hasEntity">Has Entities</label><br>
</div>
<?php 
if ($arrFlags["hasEntity"]){

    $oSQL->q('SET SESSION group_concat_max_len = 1000000');

    foreach($arrEntityTables as $tableName){
        if (!in_array($tableName, $arrTables))
          continue;
        $strEntityHashes .= getTableHash($oSQL, $tableName);
    }
    ?>
<div class="eiseIntraField">
<label><?php  echo $intra->translate('Entity Tables Hash') ; ?>:</label>
<div class="eiseIntraValue"><?php echo md5($strEntityHashes) ?></div>
</div>
    <?php

}
 ?>

</td>
<td width="40%">

<?php 
if ($arrFlags["hasDBSV"]) {
 ?>
<div class="eiseIntraField">
<label><?php  echo $intra->translate('Schema Version') ; ?>:</label>
<div class="eiseIntraValue"><?php echo $eiseDBSVersion ?></div>
</div>
<?php 
}
if ($arrFlags["hasIntraDBSV"]) {
 ?>
<div class="eiseIntraField">
<label><?php  echo $intra->translate('Framework Schema Version') ; ?>:</label>
<div class="eiseIntraValue"><?php echo $eiseIntraVersion .'/'.$eiseIntraVersionAvailable.(
    $eiseIntraVersion!=$eiseIntraVersionAvailable
    ? '(upgrade required)'
    : ''
); ?></div>
</div>
<?php 
}
 ?>
</td>
<td width="20%">

<?php 
if ($dbName){
 ?>
<div class="eiseIntraField">
<label>Dump options</label>
<div class="eiseIntraValue">
<input type="checkbox" id="flagNoData">No data<br>
<input type="checkbox" id="flagDonwloadAsDBSV">Download as DBSV script<br>
</div>
</div>
<?php 
}
 ?>

</td>
</tr>
<?php
if ($dbName!="") {
?>
<tr>
<td colspan=3>
<?php
$grid->Execute();
?>
</td>
<tr>
<?php
} else {
?>
<tr>
<td colspan=3>
<span class="field_title_top">Admin's password:</span>
<input type="password" name="usrPass" value=""><br>

<input type="checkbox" name="flagRun" id="flagRun" style="width:auto;"><label for="flagRun">Run query?</label><br>
</td>
</tr>
<tr>
<td style="text-align:center;"><input value="Save" type="submit" onclick="return confirm('Are you sure you\'d like to create the database?')"></td>
</tr>
<?php
}
?>


</table>
</fieldset>
</form>
<script>
$(window).load(function(){

    $('.eiseGrid').eiseGrid();

});

function CreateNewTable(){
 var tbl = prompt('Please enter table name:', 'tbl_');
 if (tbl!=null && tbl!="tbl_" && tbl!=""){
    location.href="codegen_form.php?toGen=newtable&dbName=<?php  echo $dbName ; ?>&tblName="+tbl;
 }
}

function dumpSelectedTables(dbName, what){

    if (typeof(what)=='undefined')
        what = 'tables';

    var strTablesToDump = '';
    if (what=='tables'){
        $("input[name='chk_chk[]']").each(function(){
            if (this.checked){
                strTablesToDump += (strTablesToDump!='' ? '|' : '')+$(this).parent().find('input[name="Name[]"]').val();
            }
        });

        if (strTablesToDump==''){
            alert('Nothing\'s selected');
            return;
        }
    }

    var strURL = "database_act.php?DataAction=dump&what="+what+"&dbName="+dbName+
        (what=='tables' ? "&strTables="+encodeURIComponent(strTablesToDump) : '')+
        ($('#flagNoData')[0].checked ? '&flagNoData=1' : '')+
        ($('#flagDonwloadAsDBSV')[0].checked ? '&flagDonwloadAsDBSV=1' : '');
    location.href = strURL;

}

</script>



<?php

include eiseIntraAbsolutePath."inc-frame_bottom.php";
?>