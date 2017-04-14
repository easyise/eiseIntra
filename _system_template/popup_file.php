<?php 
include "common/auth.php";

$sqlFile = "SELECT * FROM stbl_file WHERE filGUID='".$_GET["filGUID"]."'";
$rsFile = $oSQL->do_query($sqlFile);
$rwFile = $oSQL->fetch_array($rsFile);

header("Content-Type: ".$rwFile["filContentType"]);
if(headers_sent())
    $this->Error('Some data has already been output, can\'t send file');
header("Content-Length: ".$rwFile["filLength"]);
header('Content-Disposition: inline; filename='.$rwFile["filName"]);
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
ini_set('zlib.output_compression','0');

$fh = fopen($arrSetup["stpFilesPath"].$rwFile["filNamePhysical"], "rb");
echo fread($fh, $rwFile["filLength"]);
fclose($fh);
?>