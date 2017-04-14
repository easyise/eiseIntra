<?php 
include ("../common/eiseIntra/inc_intra.php");
include ("./common/config.php");
include eiseIntraAbsolutePath."inc_dbsv.php";

$dbsvPath = "./.SQL";

try {
	$dbsv = new eiseDBSV(array('DBNAME'=> $DBNAME
		, 'dbsvPath'=>$dbsvPath
		, 'authstring' => $_POST['authstring'])
	);
} catch(Exception $e){
	echo '<pre>';
	echo $e->getMessage();
	die();
}

if ( !$dbsv->authorized ){
	$dbsv->form();
}

$dbsv->Execute();

?>