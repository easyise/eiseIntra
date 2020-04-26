<?php
include 'common/auth.php';

$intra->requireComponent('grid');
include eiseIntraAbsolutePath.'inc_actionmatrix.php';

$entID = (isset($_POST["entID"]) ? $_POST["entID"] : $_GET["entID"]);

$mtx = new eiseActionMatrix($entID);

$arrActions[]= Array ('title' => $mtx->ent->conf['entTitle'.$intra->local]
	   , 'action' => "entity_form.php?dbName=$dbName&entID=".$mtx->conf['entID']
	   , 'class'=> 'ss_arrow_left'
	);

include eiseIntraAbsolutePath.'inc_top.php';

echo $intra->fieldset( $intra->translate('Matrix for %s', $mtx->ent->conf['entTitle'.$intra->local]) , $mtx->table() );

include eiseIntraAbsolutePath.'inc_bottom.php';