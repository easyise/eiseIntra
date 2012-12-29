<?php 
/*
class DBSV
DataBase Schema Version tool
requires inc_*sql.php

takes content of given directory with SQL version files 
and run it sequentially, starting from a script number greated than one 
from current system DBSV version

Connection *SQL user shoudl have enough privileges to modify schema in current database.
*/

class eiseDBSV {

function __construct($oSQL, $strDir){
    $this->oSQL = $oSQL;
    $this->strDir = $strDir;
}


function Execute(){
    
    ob_implicit_flush(true);
    
    $oSQL = $this->oSQL;
    
    $sqlVer = "SELECT MAX(verNumber) as verNumber FROM stbl_version";
    $rsVer = $oSQL->do_query($sqlVer);
    if (!$rsVer) {
        $oSQL->do_query("CREATE TABLE stbl_version (
              verNumber int
              , verDate datetime
           )
           ");
        $verNumber = 0;
    } else 
       $verNumber = $oSQL->get_data($rsVer, 0, "verNumber");
       
    echo "Current DB version number is #".sprintf("%03d",$verNumber)."\r\n";
    
    if (!file_exists($this->strDir)){
        echo "ERROR: Directory '{$this->strDir}' doesn't exist";
        return;
    }
    
    $dh  = opendir($this->strDir);
        
    $arrFiles = Array();
        
    while (false !== ($filename = readdir($dh))) {
        if (preg_match("/^([0-9]{3})[\-\_ ].+(\.sql)/",$filename, $arrMatch)){
            $arrFiles[(integer)$arrMatch[1]] = $filename;
        }
    }

    ksort($arrFiles);
    end($arrFiles);
    $newVerNo = key($arrFiles);
    echo "New version number is going to be #".sprintf("%03d",$newVerNo)."\r\n";
    if ($newVerNo<=$verNumber) {
        echo "ERROR: Nowhere to update. Currenct DB version is bigger than this update.\r\n\r\n";
        return;
    }

    chdir($this->strDir);
    for ($i=($verNumber+1);$i<=$newVerNo;$i++){
        if (!isset($arrFiles[$i])){
            echo "ERROR: Cannot get SQL script for version #$i.";
            return;
        }
        $fh = fopen($arrFiles[$i], "r");
        $oSQL->do_query("START TRANSACTION");
        $this->parse_mysql_dump($arrFiles[$i]);
        $oSQL->do_query("INSERT INTO stbl_version (verNumber, verDate, verDesc) VALUES ($i, NOW(), '')");
        $oSQL->do_query("COMMIT");
        echo "Version is now #".sprintf("%03d",$i)."\r\n";
        fclose($fh);
    }
    echo "Execution complete";
}

private function parse_mysql_dump($url){

    $oSQL = $this->oSQL;

    $file_content = file($url);
    $query = "";
	$delimiter = ";";
    foreach($file_content as $sql_line){
        if (!preg_match("/^\#/", $sql_line) )
		    $query .= $sql_line;
		if (preg_match("/^CREATE FUNCTION/i", $sql_line) )
		    $delimiter = ";;";
        if(preg_match("/$delimiter\s*$/", $sql_line)){
		  //echo "begin:".$query.":end\r\n";
          $oSQL->do_query($query);
          $query = "";
		  $delimiter = ";";
        }
    }
}


}

?>