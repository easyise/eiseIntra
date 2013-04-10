<?php

function parse_mysql_dump($url){
    $file_content = file($url);
    $query = "";
	$delimiter = ";";
    foreach($file_content as $sql_line){
        if (!preg_match("/^\#/", $sql_line) )
		    $query .= $sql_line;
		if (preg_match("/^CREATE FUNCTION/i", $sql_line) )
		    $delimiter = ";;";
        if(preg_match("/$delimiter\s*$/", $sql_line)){
		  //echo "--\r\n".$query."\r\n---";
          $result = mysql_query($query)or die('Error: '.mysql_error());
          $query = "";
		  $delimiter = ";";
        }
    }
}

echo "";

?>
/**************************************************************************/
/* PHP FRAMEWOR DBSV for MySQL                                            */
/* (c)2009 Ilya S. Eliseev, NYK logistics CIS                             */ 
/**************************************************************************/
<?php 
//print_r($argv);

for($i=0;$i<count($argv);$i++){
   //echo $argv[$i]."\r\n";
   switch($argv[$i]) {
      case "-dbsrv":
      case "-dbhost":
         $argArrConn = "dbhost";
         break;
      case "-dbname":
         $argArrConn = "dbname";
         break;
      case "-dbuser":
         $argArrConn = "dbuser";
         break;
      case "-dbpass":
         $argArrConn = "dbpass";
         break;
      default:
         $argArrConn = "";
         break;
   }
   if($argArrConn!=""){
      $i++;
      $arrConn[$argArrConn] = $argv[$i];
    }
}

$conn = @mysql_connect($arrConn["dbhost"], $arrConn["dbuser"], $arrConn["dbpass"]) 
       or die("Unable to connect to database server ".$arrConn["dbhost"].".");
@mysql_select_db($arrConn["dbname"]) 
       or die("Could not use database ".$arrConn["dbname"]);


$sqlVer = "SELECT MAX(fvrNumber) as fvrNumber FROM stbl_framework_version";
$rsVer = @mysql_query($sqlVer);
if (!$rsVer) {
    mysql_query("CREATE TABLE `stbl_framework_version` (
  `fvrNumber` int(11) NOT NULL AUTO_INCREMENT,
  `fvrDesc` text,
  `fvrDate` datetime DEFAULT NULL,
  PRIMARY KEY (`fvrNumber`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Version history for the framework';
       ");
    $verNumber = 0;
} else 
   $verNumber = mysql_result($rsVer, 0, "fvrNumber");
   
echo "Current DB version number is #".sprintf("%03d",$verNumber)."\r\n";

$dh  = opendir(".");
    
$arrFiles = Array();
    
while (false !== ($filename = readdir($dh))) {
   if (preg_match("/^([0-9]{3}).+(\.sql)/",$filename, $arrMatch)){
      $arrFiles[(integer)$arrMatch[1]] = $filename;
   }
}

ksort($arrFiles);
end($arrFiles);
$newVerNo = key($arrFiles);
echo "New version number is going to be #".sprintf("%03d",$newVerNo)."\r\n";
if ($newVerNo<=$verNumber) 
   die("Nowhere to update. Currenct DB version is bigger than this update.\r\n\r\n");


for ($i=($verNumber+1);$i<=$newVerNo;$i++){
   if (!isset($arrFiles[$i]))
       die("Cannot get SQL script for version #$i.");
   $fh = fopen($arrFiles[$i], "r");
   parse_mysql_dump($arrFiles[$i]);
   mysql_query("INSERT INTO stbl_framework_version (fvrNumber, fvrDate, fvrDesc) VALUES ($i, NOW(),'".
      mysql_escape_string(fread($fh, filesize($arrFiles[$i])))."')");
   echo "Version is now #".sprintf("%03d",$i)."\r\n";
   fclose($fh);
}

if (!$conn) {
 ?>
Usage:
php dbsv.php -dbsrv <dbhost> -dbname <dbname> [-dbuser <dbusr> -dbpass <dbpass>]
<?php
}
?>