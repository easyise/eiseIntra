<?php
header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past
header("Content-type: application/json"); // JSON

$q = $_GET["q"];
$table = $_GET["table"];
$prefix = $_GET["prefix"];
$flagShowDeleted = (bool)$_GET["d"];
$extra = $_GET["e"];

$rs = $intra->getDataFromCommonViews(null, $q, $table, $prefix, $flagShowDeleted, $extra);

$strOutput = "";
while ($rw=$oSQL->fetch_array($rs)){
   $strOutput.= ($strOutput!="" ? ",\r\n" : "").json_encode($rw);
}
echo "{\"data\":[".$strOutput."]}";
?>