<?php 

class eiseGrid_codegen {

static public function code($arrConfig){

    $defaultConfig = array(
            'flagIntra' => true
            , 'tableName' => ''
            , 'arrTable' => array()
            , 'gridName' => 'mygrid' // if $arrTable['prefix'] - it is ignored
            , 'eiseGridRelativePath' => '' //non-effective if flagIntra
            , 'jQueryUITheme' => 'ui-lightness' //non-effective if flagIntra
        );

    $conf = array_merge($defaultConfig, $arrConfig);
    
    $arrTable = $conf['arrTable'];
    $gridName = (isset($arrTable['prefix']) ? $arrTable['prefix'] : str_replace(' ', '', $conf['gridName']));
    $tblName = $conf['tableName'];

    $PK = ($arrTable['PKtype']=="user_defined" ? $arrTable['PK'][0]."_id" : $arrTable['PK'][0]);

    $strCode = "<?php\r\n";
    if ($conf['flagIntra']) {
        
        $strCode .= "include 'common/auth.php';\r\n\r\n";
        
        $strCode .= 'include commonStuffAbsolutePath.\'eiseGrid2/inc_eiseGrid.php\';

$arrJS[] = commonStuffRelativePath.\'eiseGrid2/eiseGrid.jQuery.js\';
$arrCSS[] = commonStuffRelativePath.\'eiseGrid2/themes/default/screen.css\';

$arrJS[] = jQueryUIRelativePath.\'js/jquery-ui-1.8.16.custom.min.js\';
$arrCSS[] = jQueryUIRelativePath.\'css/\'.jQueryUITheme.\'/jquery-ui-1.8.16.custom.css\';

';
    } else {
        $strCode .= "<?php\r\n\r\n";
    }

    $strCode .= "\$DataAction  = (isset(\$_POST['DataAction']) ? \$_POST['DataAction'] : \$_GET['DataAction'] );\r\n\r\n";

    $strCode .= "\$grid".strtoupper($gridName)." = new eiseGrid(".($conf['flagIntra'] ? '$oSQL' : 'null')."
        , '".$gridName."'
        , array('arrPermissions' => Array('FlagWrite'=>".($conf['flagIntra'] ? "\$intra->arrUsrData['FlagWrite'])" : "true")."
                ".($tblName ? ", 'strTable' => '".$tblName."'
                , 'strPrefix' => '".$gridName."'" : '')."
                , 'controlBarButtons' => 'add|moveup|movedown|delete|save'
                )
        );\r\n\r\n";
        
    $strCode .= "\$grid".strtoupper($gridName)."->Columns[]  = Array(
            'type' => 'row_id'
            , 'field' => '".$PK."'
        );\r\n";
        
    foreach($arrTable['columns'] as $col){
        if ($col["DataType"]=="binary")
           continue;
        if ($col["Extra"]=="auto_increment")
           continue;
        if ($col["DataType"]=="activity_stamp" && $col["Field"]!=$gridName."EditDate")
           continue;
           
        $strCode .= "\$grid".strtoupper($gridName)."->Columns[] = Array(\r\n";    
       
        $strCode .= "        'title' => ".($conf['flagIntra']
            ? "\$intra->translate(\"".($col["Comment"]!="" ? $col["Comment"] : $col["Field"])."\")"
            : $col["Title"])."\r\n";
        $strCode .= "        , 'field' => \"".$col["Field"]."\"\r\n";
        $strCode .= "        , 'type' => \"";
        switch($col["DataType"]){
            case "text":
            case "FK":
            case "PK":
            default:
                $strCode .= "text";
                break;
            case "integer":
            case "real":
                $strCode .= "numeric";
                break;
            case "boolean":
                $strCode .= "checkbox";
                break;
            case "date":
            case "datetime":
                $strCode .= $col["DataType"];
                break;
            case "activity_type":
                $strCode .= "datetime";
                break;
        }
        $strCode .= "\"\r\n";
        $strCode .= ($col["Field"]==$gridName."EditDate" || $col["DataType"]=="PK"
                                                                    ? "        , 'disabled' => true\r\n" : "");
        $strCode .= ($col["Field"]==$gridName."TitleLocal" ? "        , 'width'=>'50%', 'mandatory' => true\r\n" : "");
        $strCode .= ($col["Field"]==$gridName."Title"      ? "        , 'width'=>'50%', 'mandatory' => true\r\n" : "");
        $strCode .= ");\r\n";
       
    }
        
        $strCode .= "\r\nswitch(\$DataAction){
    case \"update\":
        ";
    if ($conf['tableName']){
        $strCode .= "\$grid".strtoupper($gridName)."->Update();
        ";
    } else {
        $strCode .= "
        foreach (\$_POST['inp_{$gridName}_updated'] as \$ix => \$flagUpdated) {
            if(!\$flagUpdated) continue;
            if (\$_POST['myGridID'][\$ix]===''){
                // insert a record
            } else {
                //update a record where row ID = \$_POST['{$PK}'][\$ix]
            }
        }

        ";
    }
    $strCode .= ($conf['flagIntra'] 
            ? "\$intra->redirect(\"Data is updated\", \$_SERVER[\"PHP_SELF\"]);"
            : 'header("Location: {$_SERVER[\'PHP_SELF\']}"); die();'
            )."
    default:
        break;
}";
        
    if (!$conf['flagIntra']){
        $strCode .= '?><!DOCTYPE html>
<html>
<head>
    <title></title>
    <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
    <meta name="author" content="eiseGrid code generator">
    <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.6.1/jquery.min.js"></script>
    <script src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/jquery-ui.min.js"></script>
    <script src="'.$conf['eiseGridRelativePath'].'eiseGrid.jQuery.js"></script>
    <link rel="STYLESHEET" type="text/css" href="'.$conf['eiseGridRelativePath'].'eiseGrid.css" media="screen">
    <link rel="STYLESHEET" type="text/css" href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/'.$conf['jQueryUITheme'].'/jquery-ui.css" media="screen">
</head>
<body>
';
    } else {
        $strCode .= "
include eiseIntraAbsolutePath.'inc-frame_top.php';
?>";
    } 

    $strCode .= "
<script>
\$(document).ready(function(){  
    \$('.eiseGrid').eiseGrid();
});
</script>
<div class=\"eiseIntraForm\">
<fieldset><legend><?php echo \$intra->arrUsrData[\"pagTitle{\$intra->local}\"]; ?></legend>
<?php
\$sql".strtoupper($gridName)." = \"SELECT * FROM ".$tblName."\";
\$rs".strtoupper($gridName)." = \$oSQL->do_query(\$sql".strtoupper($gridName).");
while (\$rw".strtoupper($gridName)." = \$oSQL->fetch_array(\$rs".strtoupper($gridName).")){
    //".($arrTable['PKtype']=="user_defined" 
       ? "\$rw".strtoupper($gridName)."['".$arrTable['PK'][0]."_id'] = \$rw".strtoupper($gridName)."['".$arrTable['PK'][0]."'];" 
       : $arrTable['PK'][0])."
    \$grid".strtoupper($gridName)."->Rows[] = \$rw".strtoupper($gridName).";
}

\$grid".strtoupper($gridName)."->Execute();
?>
</fieldset>
</div>";

    if (!$conf['flagIntra']){
        $strCode .= '</body>
</html>';
    } else {
        $strCode .= '<?php
include eiseIntraAbsolutePath.\'inc-frame_bottom.php\';
?>';
    
    }

    return $strCode;

}


}

 ?>