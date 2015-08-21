<?php 
include "common/auth.php";

function explainQuery($db, $q){
	GLOBAL $intra, $oSQL;
	$oSQL->q('USE `'.$db.'`');
	if(!preg_match('/^select/i', $q))
		return (array('status'=>'error', 'message'=>'Unable to explain non-SELECT queries. Leave only SELECT part and click "Explain" button.'));

	try {
		$rsE = $oSQL->q('EXPLAIN EXTENDED '.$q);
	} catch(Exception $e){
		return (array('status'=>'error', 'message'=>$e->getMessage()));
	}
		

	$arrRet = array();

	while($rwE = $oSQL->f($rsE)){
		$arrRet[] = $rwE;
	}

	return array('status'=>'ok', 'data'=>$arrRet);
}


$DataAction = $_POST['DataAction'] ? $_POST['DataAction'] : $_GET['DataAction'];

switch($DataAction){
	case 'getProcInfo':

		header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
    	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past
    	header("Content-type: application/json"); // Date in the past

		$sqlQ = "SELECT * FROM INFORMATION_SCHEMA.PROCESSLIST WHERE ID=".$oSQL->e($_GET['procID']);
		$rsQ = $oSQL->q($sqlQ);
		$rwQ = $oSQL->f($rsQ);

		if ($oSQL->n($rsQ)==0 || !$rwQ['INFO']){
			echo json_encode(array('status'=>'error', 'message'=>'Process not found', 'code'=>'404'));die();
		}

		$arrExpl = @explainQuery($rwQ['DB'], $rwQ['INFO']);

		echo json_encode(array('DB'=>$rwQ['DB'], 'INFO'=>$rwQ['INFO'], 'explain'=>$arrExpl, 'sql'=>$sqlQ));

		die();

	case 'explainQuery':

		header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
    	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past
    	header("Content-type: application/json"); // Date in the past

		$arrExpl = @explainQuery($_GET['db'], $_GET['q']);

		echo json_encode($arrExpl);

		die();

	case 'killProc':

		die();

}

include commonStuffAbsolutePath.'eiseGrid2/inc_eiseGrid.php';

$arrJS[] = commonStuffRelativePath.'eiseGrid2/eiseGrid.jQuery.js';
$arrCSS[] = commonStuffRelativePath.'eiseGrid2/themes/default/screen.css';

$arrJS[] = jQueryUIRelativePath.'js/jquery-ui-1.8.16.custom.min.js';
$arrCSS[] = jQueryUIRelativePath.'css/'.jQueryUITheme.'/jquery-ui-1.8.16.custom.css';


$arrActions[]= Array ("title" => $intra->translate("New database")
	   , "action" => "database_form.php"
	   , "class"=> "ss_add"
	);
$arrActions[]= Array ("title" => $intra->translate("Refresh")
	   , "action" => "javascript:location.reload()"
	   , "class"=> "ss_arrow_refresh"
	);


$arrJS[] = 'server_form.js';

include eiseIntraAbsolutePath."inc-frame_top.php";
?>

<div class="eiseIntraForm" id="server_form">

<fieldset id="fldsStatus"><legend><?php echo $intra->translate('Welcome to').' '.$oSQL->dbhost.'!' ?></legend>

<?php 

$sqlStatus = "SHOW GLOBAL STATUS";
$rsStatus = $oSQL->q($sqlStatus);

while($rwSt = $oSQL->f($rsStatus)){
	$arrStatus[$rwSt['Variable_name']] = $rwSt['Value'];
	if(preg_match('/^Com\_/', $rwSt['Variable_name'])){
		$command = preg_replace('/^Com_/', '', $rwSt['Variable_name']);
		$arrComStats[$command] = $rwSt['Value'];
	}
		
}

arsort($arrComStats);

?>

<div class="eiseIntraField">
	<label><?php echo $intra->translate('Uptime') ?>:</label>
	<div class="eiseIntraValue"><?php echo date('d\d H\h i\m s\s', $arrStatus['Uptime']) ?></div>
</div>
<?php

$gridStatus = new eiseGrid($oSQL
        , 'sta'
        , array('arrPermissions' => Array('FlagWrite'=>false))
        );


$gridStatus->Columns[] = Array(
        'title' => $intra->translate("Command")
        , 'field' => "Command"
        , 'type' => "text"
);
$gridStatus->Columns[] = Array(
        'title' => $intra->translate("Count")
        , 'field' => "Count"
        , 'type' => "integer"
);


foreach($arrComStats as $com=>$count){
	if ($count==0)
		continue;
	$gridStatus->Rows[] = array('Command'=>$com, 'Count'=>$count);
}

$gridStatus->Execute();
 ?>

</fieldset>
<fieldset id="fldsProcs"><legend><?php echo $intra->translate('Processes') ?></legend><?php 

$sqlProcs = "SELECT * FROM INFORMATION_SCHEMA.PROCESSLIST ORDER BY COMMAND, TIME DESC";
$rsProcs = $oSQL->q($sqlProcs);

$gridProcs = new eiseGrid($oSQL
        , 'prc'
        , array('arrPermissions' => Array('FlagWrite'=>false))
        );

$gridProcs->Columns[] = Array(
        'title'=>'ID'
        , 'field'=>'ID'
        , 'width'=>'40px'
);
$gridProcs->Columns[] = Array(
        'title'=>'User'
        , 'field'=>'USER'
        , 'width'=>'60px'
);
$gridProcs->Columns[] = Array(
        'title'=>'Database'
        , 'field'=>'DB'
        , 'width'=>'60px'
);
$gridProcs->Columns[] = Array(
        'title'=>'Cmnd'
        , 'field'=>'COMMAND'
        , 'width'=>'40px'
);
$gridProcs->Columns[] = Array(
        'title'=>'Time'
        , 'field'=>'TIME'
        , 'type' => 'integer'
        , 'width'=>'60px'
);
$gridProcs->Columns[] = Array(
        'title'=>'State'
        , 'field'=>'STATE'
        , 'width'=>'60px'
);
$gridProcs->Columns[] = Array(
        'title'=>'Info'
        , 'field'=>'INFO'
        , 'width' => '100%'
);

while($rwProcs = $oSQL->f($rsProcs)){
	$gridProcs->Rows[] = $rwProcs;
}


$gridProcs->Execute();

 ?></fieldset>


</div>

<div id="query_explainer" class="eiseIntraForm">

<div class="eiseIntraField">
<label><?php echo $intra->translate('Query'); ?>:</label>
	<textarea rows=4 class="eiseIntraValue" id="Info"></textarea>
</div>
<div class="eiseIntraField">
<label><?php echo $intra->translate('DB'); ?>:</label>
	<div class="eiseIntraValue" id="DB"></div>
</div>
<div class="eiseIntraField">
<label><?php echo $intra->translate('Process ID'); ?>:</label>
	<div class="eiseIntraValue" id="ID"></div>
</div>
<div class="eiseIntraField">
<label><?php echo $intra->translate('Exec. time'); ?>:</label>
	<div class="eiseIntraValue" id="Time"></div>
</div>
	<?php 
$gridExplain = new eiseGrid($oSQL
        , 'expl'
        , array('arrPermissions' => Array('FlagWrite'=>false))
        );

$gridExplain->Columns[] = Array(
        'title'=>'id'
        , 'field'=>'id'
        , 'width'=>'40px'
);
$gridExplain->Columns[] = Array(
        'title'=>'select_type'
        , 'field'=>'select_type'
        , 'width'=>'60px'
);
$gridExplain->Columns[] = Array(
        'title'=>'table'
        , 'field'=>'table'
        , 'width'=>'120px'
);
$gridExplain->Columns[] = Array(
        'title'=>'type'
        , 'field'=>'type'
        , 'width'=>'40px'
);
$gridExplain->Columns[] = Array(
        'title'=>'possible_keys'
        , 'field'=>'possible_keys'
        , 'width'=>'40%'
);
$gridExplain->Columns[] = Array(
        'title'=>'key'
        , 'field'=>'key'
        , 'width'=>'30%'
);
$gridExplain->Columns[] = Array(
        'title'=>'key_len'
        , 'field'=>'key_len'
        , 'width'=>'40px'
);
$gridExplain->Columns[] = Array(
        'title'=>'ref'
        , 'field'=>'ref'
        , 'width'=>'40px'
);
$gridExplain->Columns[] = Array(
        'title'=>'rows'
        , 'field'=>'rows'
        , 'width'=>'40px'
);
$gridExplain->Columns[] = Array(
        'title'=>'filtered'
        , 'field'=>'filtered'
        , 'width'=>'40px'
);
$gridExplain->Columns[] = Array(
        'title'=>'Extra'
        , 'field'=>'Extra'
        , 'width'=>'30%'
);


$gridExplain->Execute();

?>
</div>


</div>

<?php
include eiseIntraAbsolutePath."inc-frame_bottom.php";
?>