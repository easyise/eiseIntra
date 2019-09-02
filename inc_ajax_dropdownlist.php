<?php
$q = $_GET["q"];
$table = $_GET["table"];
$prefix = $_GET["prefix"];
$flagShowDeleted = (bool)$_GET["d"];
$extra = $_GET["e"];

$rs = $intra->getDataFromCommonViews(null, $q, $table, $prefix, $flagShowDeleted, $extra);

$aOut = array();
while ($rw=$intra->oSQL->fetch_array($rs)){
   $aOut[] = $rw;
}
$intra->json('ok', '', $aOut);