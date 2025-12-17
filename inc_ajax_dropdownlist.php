<?php
$q = isset($_GET["q"]) ? $_GET["q"] : "";
$table = isset($_GET["table"]) ? $_GET["table"] : "";
$prefix = isset($_GET["prefix"]) ? $_GET["prefix"] : "";
$flagShowDeleted = isset($_GET["d"]) ? (bool)$_GET["d"] : false;
$extra = isset($_GET["e"]) ? $_GET["e"] : "";

$rs = $intra->getDataFromCommonViews(null, $q, $table, $prefix, $flagShowDeleted, $extra);

$aOut = array();
while ($rw=$intra->oSQL->fetch_array($rs)){
   $aOut[] = $rw;
}
$intra->json('ok', '', $aOut);