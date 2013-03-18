<?php 
/*
class DBSV
version 2.0 beta
DataBase Schema Version tool
requires inc_*sql.php

takes content of given directory with SQL version files 
and run it sequentially, starting from a script number greated than one 
from current system DBSV version

 update version 2.0:
supports multi-branch development with gaps in version numbering

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
    $newVer = $verNumber+1;
    foreach($arrFiles as $ver => $file){
        if ($ver < $newVer)
            continue;
            
        if (!isset($arrFiles[$newVer])){
            echo "ERROR: Cannot get SQL script for version #{$newVer}. ";
            $ver = $oSQL->d("SELECT MAX(verNumber) FROM stbl_version");
            echo "Current version is {$ver}.\r\n";
            return;
        }
        $oSQL->do_query("START TRANSACTION");
        $this->parse_mysql_dump($arrFiles[$newVer]);
        $oSQL->do_query("INSERT INTO stbl_version (verNumber, verDate, verDesc) VALUES ({$newVer}, NOW(), '')");
        $oSQL->do_query("COMMIT");
        $ver = $oSQL->d("SELECT MAX(verNumber) FROM stbl_version");
        echo "Version is now #".sprintf("%03d",$ver)."\r\n";
        $newVer = $ver+1;
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