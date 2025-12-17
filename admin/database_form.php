<?php 
include "common/auth.php";

if(isset($_GET[$intra->conf['dataActionKey']]) && $_GET[$intra->conf['dataActionKey']]=='new')
    unset($dbName);

$intra->requireComponent(array('grid', 'batch'));

$grid = new eiseGrid($oSQL
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
    , 'headerClickable' => true
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
    
    if(isset($arrFlags["hasIntraDBSV"]) && $arrFlags["hasIntraDBSV"]){
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
$arrActions[]= Array ("title" => "De-Excelize"
       , "action" => "#deexcelize"
       , "class" => "ss_page_excel"
    );

if(!$arrFlags['hasPages']){
        $arrActions[]= Array ("title" => "Apply eiseIntra"
         , "action" => "javascript:applyIntra.call(this)"
         , "class" => "ss_script_add"
      );
}

if (isset($eiseIntraVersion) && $eiseIntraVersion < 900){
    $arrActions[]= Array ("title" => "Upgrade eiseIntra"
	   , "action" => "database_act.php?DataAction=upgrade&dbName=".urlencode($dbName)
	   , "class" => "ss_wrench_orange "
	);
}   
$arrActions[]= Array ("title" => "Dump Entities"
	   , "action" => "javascript:dumpSelectedTables('{$dbName}', 'entities')"
	   , "class" => "ss_cog_go  "
	); 
$arrActions[]= Array ("title" => "Dump Entity Fields"
       , "action" => "#entities_fields"
       , "class" => "ss_table_go  "
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


include eiseIntraAbsolutePath."inc_top.php";
?>
<style>
.eiseIntraField label {
    width: 34%;
}
.eiseIntraField .eiseIntraValue {
    width: 62%;
}
</style>

<form action="database_act.php" method="POST" class="eiseIntraForm" id="frm-create-database">
<fieldset class="eiseIntraMainForm"><legend><?php echo ($dbName!="" ? "Database $dbName" : $intra->translate("New Database") ); ?></legend>
<table width="100%">
<input type="hidden" name="DataAction" value="create">
<tr>
<td width="40%">
<div class="eiseIntraField">
<label><?php  echo $intra->translate('Name') ; ?>:</label>
<?php echo $intra->showTextBox('dbName_new', $dbName, (array('FlagWrite' => !(bool)$dbName))) ?>
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

    $strEntityHashes = '';
    foreach(eiseAdmin::$arrEntityTables as $tableName){
        if (!in_array($tableName, $arrTables))
          continue;
        $strEntityHashes .= $intra->getTableHash($tableName);
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
if (isset($arrFlags["hasIntraDBSV"]) && $arrFlags["hasIntraDBSV"]) { ?>
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
<td style="text-align:center;"><input value="Save" type="submit" class="btn-save"></td>
</tr>
<?php
}
?>


</table>
</fieldset>
</form>
<script>
$(document).ready(function(){

    $('.eiseGrid').eiseGrid();

    var fnSubmit = function(ev){
        $(this).eiseIntraBatch('submit', {
            timeoutTillAutoClose: null
            , flagAutoReload: false
            , onclose: function(){
                $('#frm-create-database').submit(fnSubmit);
            }
            , title: $('#frm-create-database legend').first().text()
        });
        ev.stopPropagation();
        return false;
    }

    $('#frm-create-database').submit(fnSubmit)

    $('a[href="#entities_fields"]').click(function(){

        $('body').eiseIntraBatch({url: 'codegen_form.php'+location.search+'&toGen=entities_fields', title: 'Dump', timeoutTillAutoClose: null, flagAutoReload: false})
        return false;


    })

    $('a[href="#deexcelize"]').click(function(){

        var initiator = this,
            $initiator = $(this),
        $dialog = $(this).eiseIntraForm('createDialog', {
            title: $initiator.text()
            , method: 'POST'
            , action: 'database_act.php'+location.search+'&nocache=true'
            , fields: [{
                type: 'hidden'
                , name: 'DataAction'
                , value: 'deexcelize'
            },{
                type: 'file'
                , name: 'excel'
                , title: 'XLSX file'
            },{
                type: 'text'
                , name: 'table'
                , title: 'Table name'
            },{
                type: 'text'
                , name: 'prfx'
                , title: 'Table prefix'
            },{
                type: 'textarea'
                , name: 'tableCreate'
                , title: 'Table Create'
            },
            ] 
            , oncreate: function(){
                var $dlg = $(this);
                $dlg.find('input[name="excel"]').change(function(){
                    var filename = $(this).val(),
                        aFile = filename.split('.'),
                        ext = aFile[aFile.length - 1],
                        tableBase = aFile[0].replace(/[\-\s]/gi, '')
                        table = 'tbl_'+tableBase+'_upload',
                        prfx = aFile[0].replace(/[aeiou0-9]/gi, '').substr(0,3)+'u';
                    if(ext.toLowerCase()!='xlsx'){
                        alert("This is not XLSX file")
                        return false;
                    }
                    
                    $dlg.find('input[name="table"]').val(table);
                    $dlg.find('input[name="prfx"]').val(prfx);
                    

                })
            }
            , onsubmit: function(){
                    if(!$dialog.find('[name="tableCreate"]').val()){
                        $dialog.find('input[name="DataAction"]').val('deexcelize_getCreate')
                        $.post({url: 'database_act.php',
                                data: new FormData($dialog[0]),
                                success: function(nd){
                                    $dialog.find('[name="tableCreate"]').val(nd);
                                },
                                // Options to tell jQuery not to process data or worry about the content-type
                                cache: false,
                                contentType: false,
                                processData: false
                            }, 'html');
                        return false;
                    } else {
                        $dialog.find('input[name="DataAction"]').val('deexcelize');
                        $.post({url: 'database_act.php',
                                data: new FormData($dialog[0]),
                                success: function(nd){
                                    alert("We're done")
                                    //$dialog.dialog('close').remove();
                                },
                                // Options to tell jQuery not to process data or worry about the content-type
                                cache: false,
                                contentType: false,
                                processData: false
                            }, 'html');
                    }
                    return false;
              }

        });

        return false;

    })

});

function CreateNewTable(){
 var tbl = prompt('Please enter table name:', 'tbl_');
 if (tbl!=null && tbl!="tbl_" && tbl!=""){
    location.href="codegen_form.php?toGen=newtable&dbName=<?php  echo $dbName ; ?>&tblName="+tbl;
 }
}


var applyIntra = function(){
    var initiator = this,
      $initiator = $(this),
    $dialog = $(this).eiseIntraForm('createDialog', {
      title: $initiator.text()
      , method: 'POST'
      , action: 'database_act.php'+location.search+'&nocache=true'
      , fields: [{
        type: 'hidden'
        , name: 'DataAction'
        , value: 'applyIntra'
      },{
        type: 'password'
        , name: 'password'
        , title: 'Admin password'
      },{
        type: 'password'
        , name: 'password1'
        , title: 'Pls confirm'
      },{
        type: 'checkbox'
        , name: 'flagGetSQL'
        , title: 'Only get SQL'
      },
      ] 
      , onsubmit: function(){
            if($dialog.find('input[name="password"]').val() != $dialog.find('input[name="password1"]').val()){
                $dialog.find('input[name="password"]').focus();
                alert("Passwords doesn't match");
                return false;
            }
            window.setTimeout(function(){$dialog.find('input[type="submit"], input[type="button"], button').each(function(){this.disabled = true;})}, 1);
            $dialog.eiseIntraBatch('submit', {
                timeoutTillAutoClose: null
                , flagAutoReload: true
                , title: $initiator.text()
            })
            return false;
      }

    });
}

</script>



<?php

include eiseIntraAbsolutePath."inc_bottom.php";
?>