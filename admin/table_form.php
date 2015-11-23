<?php 
include "common/auth.php";

$oSQL->select_db($_GET["dbName"]);

$pane = (isset($_GET["pane"]) 
    ? $_GET["pane"] 
    : (isset($_COOKIE['pane']) 
        ? $_COOKIE['pane'] : "Structure"));

SetCookie("pane", $pane);
        
$dbName = $_GET["dbName"];
$tblName = $_GET["tblName"];

$arrJS[] = jQueryUIRelativePath."js/jquery-ui-1.8.16.custom.min.js";
$arrCSS[] = jQueryUIRelativePath."css/redmond/jquery-ui-1.8.16.custom.css";

$arrTable = $intra->getTableInfo($dbName, $tblName);

switch($pane){
  case "Structure":
        include commonStuffAbsolutePath.'eiseGrid2/inc_eiseGrid.php';
        $arrJS[] = commonStuffRelativePath.'eiseGrid2/eiseGrid.jQuery.js';
        $arrCSS[] = commonStuffRelativePath.'eiseGrid2/themes/default/screen.css';
        
        $grid = new easyGrid($oSQL
                            ,"tbl"
                            , Array(
                                    'arrPermissions' => Array('FlagWrite' => (bool)($arrTable['type']!='view'))
                                    , 'flagStandAlone' => false
                                    , 'showControlBar' => true
                                    , 'controlBarButtons' => 'insert|moveup|movedown|delete'
                                    )
                            );
        $grid->Columns[]=Array(
            'field'=>"Field_id"
            ,'type'=>'row_id'
        );
        $grid->Columns[] = Array(
           'field'=>"Field"
           , 'title'=>"Field"
           , 'type' => "text"
           , 'width' => '38%'
        );
        $grid->Columns[] = Array(
           'field'=>"Type"
           , 'title'=>"DB Type"
            , 'type' => "text"
        );

        $grid->Columns[] = Array(
           'field'=>"DataType"
            , 'title'=>"Data Type"
            , 'type' => "text"
            , 'width' => '80px'
            , 'disabled' => true
        );

        $grid->Columns[] = Array(
           'field'=>"Key"
           , 'title'=>"Key"
           , 'type' => "text"
        );

        $grid->Columns[] = Array(
           'field'=>"Null"
           , 'title'=>"Null"
           , 'type' => "text"
           , 'width' => '40px'
        );

        $grid->Columns[] = Array(
           'field'=>"Default"
           , 'title'=>"Default"
           , 'type' => "text"
        );

        $grid->Columns[] = Array(
           'field'=>"Extra"
           , 'title'=>"Extra"
           , 'type' => "text"
           , 'disabled' => true
        );
        $grid->Columns[] = Array(
           'field'=>"Comments"
           , 'title'=>"Comments"
           , 'type' => "text"
           , 'width' => '62%'
        );

        foreach($arrTable['columns'] as $i=>$col){
          $col['Field_id'] = $col['Field'];
          $grid->Rows[] = $col;
        }
     break;
case "Data":
        
        $arrJS[] = commonStuffRelativePath."eiseList/eiseList.js";
        $arrCSS[] = commonStuffRelativePath."eiseList/themes/default/screen.css";
        include_once(commonStuffAbsolutePath."eiseList/inc_eiseList.php");
        //echo "<pre>";
        //echo implode(", ", $arrTable["PK"]);
        //print_r($arrTable);
        //die();
        $lst = new eiseList($oSQL, ($arrTable["prefix"] ? $arrTable["prefix"] : "lst"), Array('title'=>"Table {$tblName} @ {$dbName}"
        , 'sqlFrom' => "{$tblName}"
        , 'defaultOrderBy'=>"pk"
        , 'defaultSortOrder'=>"ASC"
        , 'cacheSQL'=>false
        , 'intra' => $intra));
        
        $colPK = array('title' => ""
        , 'field' => 'pk'
        
        , 'PK' => true
        );
        
        $lst->Columns[] = array('title' => ""
        , 'field' => 'pk'
        , 'sql' => (count($arrTable["PK"])>1 
            ? "CONCAT(".implode(",", $arrTable["PK"]).")"
            : ($arrTable["PK"][0]
                ? $arrTable["PK"][0]
                : $arrTable['columns'][0]["Field"]
                )
            )
        , 'PK' => true
        );
        
        $lst->Columns[] = array('title' => "##"
        , 'field' => "phpLNums"
        , 'type' => "num"
        );
        
        $i=0;
        foreach($arrTable['columns'] as $col){
           $arrCol = Array();
           $arrCol['title'] = $col["Field"];
           $arrCol['field'] = $col["Field"];
           $arrCol['filter'] = $col["Field"];
           $arrCol['order_field'] = $col["Field"];
           switch($col["DataType"]){
               case "FK":
               case "PK":
               case "activity_stamp":
                  $arrCol['type'] = "text";
                  break;
               case "binary":
                  $arrCol['type'] = "binary";
                  $arrCol['href'] = "popup_binary.php?dbName=$dbName&tblName=$tblName&field=".$col["Field"]."&pk=[".$arrTable['PK'][0]."]";
                  break;
               default:
                    $arrCol['type'] = $col["DataType"];
           }
           $lst->Columns[] = $arrCol;
           $i++;
        }
        
        $lst->handleDataRequest();
        
    break;
} 


$arrActions[]= Array ("title" => "Back to DB"
	   , "action" => "database_form.php?dbName=$dbName"
	   , "class"=> "ss_arrow_left"
	);
if($arrTable['type']!='view'){
    $arrActions[]= Array ("title" => "INSERT...SELECT"
           , "action" => "codegen_form.php?dbName=$dbName&tblName=$tblName&toGen=INSERT%20SELECT"
           , "class"=> "ss_script"
        );
    $arrActions[]= Array ("title" => "INSERT SIMPLE"
           , "action" => "codegen_form.php?dbName=$dbName&tblName=$tblName&toGen=INSERT"
           , "class"=> "ss_script"
        );
    $arrActions[]= Array ("title" => "UPDATE"
           , "action" => "codegen_form.php?dbName=$dbName&tblName=$tblName&toGen=UPDATE"
           , "class"=> "ss_script"
        );    
}
$arrActions[]= Array ("title" => "eiseGrid"
	   , "action" => "codegen_form.php?dbName=$dbName&tblName=$tblName&toGen=easyGrid"
	   , "class"=> "ss_script"
	);
$arrActions[]= Array ("title" => "phpList"
	   , "action" => "codegen_form.php?dbName=$dbName&tblName=$tblName&toGen=phpList"
	   , "class"=> "ss_script"
	);
$arrActions[]= Array ("title" => "eiseList"
	   , "action" => "codegen_form.php?dbName=$dbName&tblName=$tblName&toGen=eiseList"
	   , "class"=> "ss_script"
	);

if($arrTable['type']!='view'){
    $arrActions[]= Array ("title" => "Form"
    	   , "action" => "codegen_form.php?dbName=$dbName&tblName=$tblName&toGen=Form"
    	   , "class"=> "ss_script"
    	);
}
$arrActions[]= Array ("title" => "Description"
	   , "action" => "codegen_form.php?dbName=$dbName&tblName=$tblName&toGen=table_Description"
	   , "class"=> "ss_script"
	);
    
include eiseIntraAbsolutePath."inc-frame_top.php";
?>



<script>
$(document).ready(function() {
    $( "#tabs" ).tabs(
        {selected: <?php  echo ($pane=="Structure" ? "0" : "1") ; ?>
        ,  select: function(event, ui) { 
            //alert(ui.panel.id);
            switch(ui.panel.id){
                case "tabs-0":
                    location.href="<?php  echo $_SERVER['PHP_SELF']."?dbName=$dbName&tblName=$tblName&pane=Structure" ; ?>";
                    break;
                case "tabs-1":
                    location.href="<?php  echo $_SERVER['PHP_SELF']."?dbName=$dbName&tblName=$tblName&pane=Data" ; ?>";
                    break;
            }
        } });

    if(typeof($().eiseGrid)=='function'){
        $('.eiseGrid').eiseGrid();

        $('.eiseGrid').eiseGrid('change', 'Field, Type, Default, Null, Comments', function(){tableGridChanged()})
        $('.eiseGrid').eiseGrid('delete', function(){tableGridChanged()});

        $('#generateALTER').click(function(){generateALTER()})
    }

});
</script>


<div id="tabs">
<ul>
<li><a href="#tabs-0">Structure</a></li>
<li><a href="#tabs-1">Data</a></li>
</ul>
<div id="tabs-0">
<div id="tableName">
<h1>Table <?php echo $tblName; ?> @ <?php echo $dbName; ?></h1><input id="generateALTER" type="button" value="<?php echo $intra->translate('Generate').' ALTER TABLE'; ?>" disabled>
<input type="hidden" id="tblName" value="<?php echo $tblName ?>">
</div>
<?php 
if ($pane=="Structure"){
    $grid->Execute();
    echo '<div id="textarea_source"> <textarea id=""></textarea> </div>';
}
?>
</div>
<div id="tabs-1">
<?php 
if ($pane=="Data"){
    $lst->show();
}
 ?>
</div>
</div>


<style>
<?php 
if($arrTable['type']!='view'):
 ?>
th.tbl_Field {
    text-align:right;
}
<?php 
endif;
 ?>
#tableName {
    clear: both;
    line-height: 2.4em;
    overflow: hidden;
    vertical-align: bottom;
}
#tableName h1 {
    float: left;
}
#tableName input {
    margin: 0 10px;
    vertical-align: bottom;
}
#textarea_source textarea{
    width: 100%;
    min-height: 20em;
    font-family: Courier, "Courier New", monospace;
}

#textarea_source {
    display: none;
}

</style>
<?php
include eiseIntraAbsolutePath."inc-frame_bottom.php";
?>