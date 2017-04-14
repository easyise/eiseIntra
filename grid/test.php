<?php 
if ($_POST["DataAction"]=="update"){
    echo "<pre>";
    print_r($_POST);
    die();
}
 ?>
<!DOCTYPE html>
<html>
<head>

<title>eiseGrid test</title>

<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="author" content="Ilya S. Eliseev e-ise.com">

<script type="text/javascript" src="/common/jquery/jquery-1.6.1.min.js"></script>
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/jquery-ui.min.js"></script>
<script type="text/javascript" src="eiseGrid.jQuery.js"></script>
<link rel="STYLESHEET" type="text/css" href="eiseGrid.css" media="screen">
<link rel="STYLESHEET" type="text/css" href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/ui-lightness/jquery-ui.css" media="screen">
<style>
html, body {
 font-size: 11px;
}
</style>
</head>
<body>

<h1>eiseGrid test page</h1>

<script>
$(document).ready(function(){
    $('.eiseGrid').eiseGrid();
});
</script>

<?php 
include "inc_eiseGrid.php";

$gridTest = new eiseGrid($oSQL
        ,'tst'
        , Array(
                'arrPermissions' => Array('FlagWrite'=>true // allows anything
                    , 'FlagInsert'=>true //if false, row insert not allowed
                    , 'FlagUpdate'=>true //if false, rows are locked for editing
                    , 'FlagDelete'=>true //if false, no deletion allowed
                    )
                , 'strTable' => 'tbl_nomination'
                , 'strPrefix' => 'nmn'
                , 'flagStandAlone' => true
                , 'showControlBar' => true
                )
        );


$gridTest->Columns[]  = Array( // row ID, mandatory field for any grid
            'type' => 'row_id'
            , 'field' => 'tstID'
        );
$gridTest->Columns[] = Array( // hidden column, ideal for foreign key reference to upper tables
        'title' => "" 
        , 'field' => "tstForeignKeyID"
        , 'default' => "testFK"
        
);

$gridTest->Columns[] = Array( // order field
        'title' => "№"
        , 'field' => "tstOrderNo"
        , 'type' => "order"
);

$gridTest->Columns[] = Array( // text field
        'title' => "Text Field"
        , 'field' => "tstText"
        , 'type' => "text"
        , 'width' => "20%"
        , 'mandatory' => true
        , 'href' => "test_href.php?href1=[href_1]&href2=[href_2]"
);
$gridTest->Columns[] = Array( // text field
        'title' => "Static Field"
        , 'field' => "tstTextStatic"
        , 'type' => "text"
        , 'width' => "20%"
        , 'static' => true
);
$gridTest->Columns[] = Array( // text field
        'title' => "Textarea Field"
        , 'field' => "tstTextArea"
        , 'type' => "textarea"
        , 'width' => "500px"
        , 'mandatory' => true
);
$gridTest->Columns[] = Array(
        'title' => "Numeric field"
        , 'field' => "tstNumeric"
        , 'decimalPlaces' => 2
        , 'type' => "numeric"
        , 'totals' => 'sum'
);
$gridTest->Columns[] = Array(
        'title' => "Date Field"
        , 'field' => "tstDate"
        , 'type' => "date"
);
$gridTest->Columns[] = Array(
        'title' => "Date Time Field"
        , 'field' => "tstDateTime"
        , 'type' => "datetime"
);
$gridTest->Columns[] = Array(
        'title' => "Checkbox field"
        , 'field' => "tstCheckBox"
        , 'type' => "checkbox"
);
$gridTest->Columns[] = Array( // use this only for 20 values maxinum (or 100 if you're crazy. use ajax_dropdown for more values.
        'title' => "Combobox field"
        , 'field' => "tstCombobox"
        , 'type' => "combobox"
        , 'arrValues' => Array(
           '0' => 'Value for 0'
           , '1' => 'Value for 1'
           , '2' => 'Value for 2'
           , '3' => 'Value for 3'
           , '4' => 'Value for 4'
        )
        , 'defaultText' => 'Please select'
);
$gridTest->Columns[] = Array(
        'title' => "Another totals"
        , 'field' => "tstAJAX"
        , 'type' => "integer"
        , 'totals' => "avg"
);

/*
echo "<pre>";
for ($i=0;$i<5;$i++){
echo "\$gridTest->Rows[] = Array(\r\n";
foreach($gridTest->Columns as $ix=>$col){
    $data = "";
    switch($col['type']){
        case "date":
            $data = "2012-05-".($i+1);
            break;
        case "datetime":
            $data .= "2012-05-".($i+1)." ".($i+1).":00:00";
            break;
        case "text":
        case "textarea":
            $data = "Test data ".($i+1);
            break;
        case "checkbox":
            $data = (int)$i%2;
            break;
        default:
            $data = $i;
            break;
    }
    
    echo "\t".($ix!=0 ? ", " : "")."'{$col["field"]}'=>'{$data}'\r\n";
        
}
echo ");\r\n";
}
echo "</pre>";
//*/

$gridTest->Rows[] = Array(
	'tstID'=>'0'
	, 'tstForeignKeyID'=>'0'
	, 'tstOrderNo'=>'0'
	, 'tstText'=>'Test data 1'
	, 'tstTextStatic'=>'Test data 1'
	, 'tstTextArea'=>'Test data 1'
	, 'tstNumeric'=>'0'
	, 'tstDate'=>'2012-05-1'
	, 'tstDateTime'=>'2012-05-1 1:00:00'
	, 'tstCheckBox'=>'0'
	, 'tstCombobox'=>'0'
	, 'tstAJAX'=>'0'
	, 'href_1'=>'0'
	, 'href_2'=>'0'
	, 'static_1'=>'0'
	, 'static_2'=>'0'
);
$gridTest->Rows[] = Array(
	'tstID'=>'1'
	, 'tstForeignKeyID'=>'1'
	, 'tstOrderNo'=>'1'
	, 'tstText'=>'Test data 2'
	, 'tstTextStatic'=>'Test data 2'
	, 'tstTextArea'=>'Test data 2'
	, 'tstNumeric'=>'1.5'
	, 'tstDate'=>'2012-05-2'
	, 'tstDateTime'=>'2012-05-2 2:00:00'
	, 'tstCheckBox'=>'1'
	, 'tstCombobox'=>'1'
	, 'tstAJAX'=>'1'
	, 'href_1'=>'1'
	, 'href_2'=>'1'
	, 'static_1'=>'1'
	, 'static_2'=>'1'
);
$gridTest->Rows[] = Array(
	'tstID'=>'2'
	, 'tstForeignKeyID'=>'2'
	, 'tstOrderNo'=>'2'
	, 'tstText'=>'Test data 3'
	, 'tstTextStatic'=>'Test data 3'
	, 'tstTextArea'=>'Test data 3'
	, 'tstNumeric'=>'2'
	, 'tstDate'=>'2012-05-3'
	, 'tstDateTime'=>'2012-05-3 3:00:00'
	, 'tstCheckBox'=>'0'
	, 'tstCombobox'=>'2'
	, 'tstAJAX'=>'2'
	, 'href_1'=>'2'
	, 'href_2'=>'2'
	, 'static_1'=>'0'
	, 'static_2'=>'2'
);
$gridTest->Rows[] = Array(
	'tstID'=>'3'
	, 'tstForeignKeyID'=>'3'
	, 'tstOrderNo'=>'3'
	, 'tstText'=>'Test data 4'
	, 'tstTextStatic'=>'Test data 4'
	, 'tstTextArea'=>'Test data 4'
	, 'tstNumeric'=>'3'
	, 'tstDate'=>'2012-05-4'
	, 'tstDateTime'=>'2012-05-4 4:00:00'
	, 'tstCheckBox'=>'1'
	, 'tstCombobox'=>'3'
	, 'tstAJAX'=>'3'
	, 'href_1'=>'3'
	, 'href_2'=>'3'
	, 'static_1'=>'3'
	, 'static_2'=>'3'
);
$gridTest->Rows[] = Array(
	'tstID'=>'4'
	, 'tstForeignKeyID'=>'4'
	, 'tstOrderNo'=>'4'
	, 'tstText'=>'Test data 5'
	, 'tstTextStatic'=>'Test data 5'
	, 'tstTextArea'=>'Test data 5'
	, 'tstNumeric'=>'4'
	, 'tstDate'=>'2012-05-5'
	, 'tstDateTime'=>'2012-05-5 5:00:00'
	, 'tstCheckBox'=>'0'
	, 'tstCombobox'=>'4'
	, 'tstAJAX'=>'4'
	, 'href_1'=>'4'
	, 'href_2'=>'4'
	, 'static_1'=>'4'
	, 'static_2'=>'4'
);

$gridTest->Execute();
 ?>
</body>
</html>