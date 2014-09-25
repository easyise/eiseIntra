<?php 
/*
class DBSV
version 3.0 beta
DataBase Schema Version tool
requires inc_intra.php

takes content of given directory with SQL version files 
and run it sequentially, starting from a script number greated than one 
from current system DBSV version

 update version 3.0:
supports multi-branch development with gaps in version numbering

Connection *SQL user shoudl have enough privileges to modify schema in current database.
*/

class eiseDBSV {

public $conf = array(
    'DBHOST' => 'localhost'
    , 'dbsvPath' => './.SQL'
    );

public $authorized = false;

private $intra;

function __construct($conf){

    $this->conf = array_merge($this->conf, $conf);

    if (!$this->conf['DBNAME']){
        throw new Exception('Database name not specified');
    }

    if($conf['intra']){
        
        $this->intra = $conf['intra'];
        $this->authorized = true;

    } else {
        
        $this->intra = new eiseIntra();

        if($conf['authstring']){

            list($login, $password) = $this->intra->decodeAuthString($_POST['authstring']);

            if(!$this->intra->Authenticate($login, $password, $strError, 'mysql')){
                throw new Exception("Unable to connect to server {$login}@{$_POST['host']}");
            }   
            
            $this->authorized = true;

        }

    }

    if ($this->authorized){
        $this->oSQL = $this->intra->oSQL;
        if (!$this->oSQL->selectDB($this->conf['DBNAME'])){
            throw new Exception('Unable to select database '.$this->conf['DBNAME']);
        }
    }

    if (!file_exists($this->conf['dbsvPath'])){
        throw new Exception( "ERROR: DBSV - Directory '{$this->conf['dbsvPath']}' doesn't exist" );
        return;
    }
}

function form(){
    $intra = $this->intra;
    ?>
<!DOCTYPE html>
<html>
<head>

<meta http-equiv="X-UA-Compatible" content="IE=edge"/>

<title>DBSV application</title>

<?php
$intra->loadJS();
$intra->loadCSS();
?>
</head>
<script>
$(document).ready(function(){  
    
    $('#btnsubmit').removeAttr('disabled');

    $('#loginform').submit(function(){
        if(!$(this).eiseIntraForm('encodeAuthString'))
            return false;
        return true;
    });
      
    window.setTimeout(function(){
        document.getElementById("login").focus();
        document.getElementById("login").select();
    }, 1);
});
</script>
<body>

<div style="margin: 0 auto;width:33%">

<h1 style="text-align: center;">Enter your <?php echo ucfirst($this->conf['DBNAME']) ?> database credentials</h1>

<form action="<?php echo $_SERVER["PHP_SELF"] ?>" id="loginform" method="POST" onsubmit="return LoginForm();" class="eiseIntraForm">
<input type="hidden" id="DataAction" name="DataAction" value="login">
<input type="hidden" id="authstring" name="authstring" value="">
<fieldset class="eiseIntraMainForm">

<div>
   <label class="eiseIntraField">Host:</label>
   <input type="text" id="host" name="host" value="<?php echo $this->conf['DBHOST'] ?>" class="eiseIntraValue">
</div>
<div class="eiseIntraField">
    <label>DB User:</label>
    <input type="text" id="login" name="login" value="" class="eiseIntraValue">
</div>

<div class="eiseIntraField">
    <label>DB Password:</label>
    <input type="password" id="password" name="password" value="" class="eiseIntraValue">
</div>

<div class="eiseIntraField">
    <label>&nbsp;</label>
    <input type="submit" id="btnsubmit" name="btnsubmit" class="eiseIntraSubmit" value="Roll DBSV scripts">
</div>

</fieldset>
</form>
</div>
</body>
</html><?php
    die();
}

function ExecuteDBSVFramework($dbName){
    
    $oSQL = $this->oSQL;
    
    $oSQL->startProfiling();
    
    $table = $oSQL->d("SHOW TABLES FROM `{$dbName}` LIKE 'stbl_framework_version'");
    if (!$table) {
        die("Framework DBSV roll-out not possible");
    }
    
    $verNumber = $oSQL->d("SELECT MAX(fvrNumber) FROM `{$dbName}`.stbl_framework_version");
    
    $verNumber = (!$verNumber ? $verNumber=59 : $verNumber);
    
    $oSQL->dbname = $dbName;

    $arrFiles = $this->getFilesArray();
    
    $newVerNo = $this->getNewVersion();

    echo "Current DB Framework Schema Version number is now #".sprintf("%03d",$verNumber)." of ".sprintf("%03d",$newVerNo)."\r\n";

    if ($newVerNo<=$verNumber) {
       die("Nowhere to update. Currenct DB framework version is bigger than this update.\r\n\r\n");ob_flush();
    } else {
       echo "New version number is going to be #".sprintf("%03d",$newVerNo)."\r\n";ob_flush();
    }

    for ($i=($verNumber+1);$i<=$newVerNo;$i++){
       if (!isset($arrFiles[$i]))
           die("Cannot get SQL script for version #$i.");
        $fileName = $this->conf['dbsvPath'].DIRECTORY_SEPARATOR.$arrFiles[$i];
        $fh = fopen($fileName, "r");
        ///*
        $this->parse_mysql_dump($fileName);
        $oSQL->q("INSERT INTO stbl_framework_version (fvrNumber, fvrDate, fvrDesc) VALUES ($i, NOW(),".
                $oSQL->e(fread($fh, filesize($fileName))).")");
       //*/
        echo "Version is now #".sprintf("%03d",$i)."\r\n";ob_flush();
        fclose($fh);
    }
    
}

private function getFilesArray(){

    $dh  = opendir($this->conf['dbsvPath']);
        
    $arrFiles = Array();
        
    while (false !== ($filename = readdir($dh))) {
       if (preg_match("/^([0-9]{3}).+(\.sql)/",$filename, $arrMatch)){
          $arrFiles[(integer)$arrMatch[1]] = $filename;
       }
    }
    closedir($dh);

    $this->arrFiles = $arrFiles;

    return $arrFiles;
}

public function getNewVersion(){

    if(empty($this->arrFiles)){
        $this->getFilesArray();
    }

    ksort($this->arrFiles);
    end($this->arrFiles);

    $newVerNo = key($this->arrFiles);

    return $newVerNo;
}

function Execute(){
    
    set_time_limit(600);

    $n = ob_get_level();
    for ($i=0; $i<$n; $i++) {ob_end_flush();}
    ob_implicit_flush(1);

    echo str_repeat(" ", 256);
    ob_flush();

?><!DOCTYPE html>
<html><head>
<title>DBSV SQL script application</title>
</head>
<body>
<pre>
/**************************************************************************/
/* PHP DBSV for MySQL                                                     */
/* (c)2008-2014 Ilya S. Eliseev                                           */ 
/**************************************************************************/
<?php
    $oSQL = $this->oSQL;
    
    $sqlVer = "SELECT MAX(verNumber) as verNumber FROM stbl_version";
    $rsVer = $oSQL->do_query($sqlVer);
    if (!$rsVer) {
        $oSQL->do_query("CREATE TABLE `stbl_version` (
              `verNumber` int(10) unsigned NOT NULL,
              `verDesc` text,
              `verFlagVersioned` tinyint(4) NOT NULL DEFAULT '0',
              `verDate` datetime DEFAULT NULL,
              PRIMARY KEY (`verNumber`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Version information for the system'
           ");
        $verNumber = 0;
    } else 
       $verNumber = $oSQL->get_data($rsVer, 0, "verNumber");
       
    echo "Current DB version number is #".sprintf("%03d",$verNumber)."\r\n";ob_flush();flush();
    
    /* Detect verFlagVersioned flag and non-versioned scripts */
    $hasFlagVersioned = (bool)$oSQL->n($oSQL->q("SHOW COLUMNS FROM stbl_version LIKE 'verFlagVersioned'"));
    if($hasFlagVersioned){

        // check non-versioned scripts
        $nNonVersioned = $oSQL->d("SELECT COUNT(*) FROM stbl_version WHERE LENGTH(verDesc)>0 AND verFlagVersioned=0 AND verNumber>1");
        if($nNonVersioned>0){

            echo "ERROR: Current DBSV version has non-versioned files. Get DBSV delta first from Database menu using eiseAdmin.\r\n\r\n";
            echo '</pre></body></html>';
            return;

        }

    }



    $arrFiles = $this->getFilesArray();
    
    $newVerNo = $this->getNewVersion();
    
    echo "New version number is going to be #".sprintf("%03d",$newVerNo)."\r\n";
    if ($newVerNo>$verNumber) {

        chdir($this->conf['dbsvPath']);
        $newVer = $verNumber+1;
        foreach($arrFiles as $ver => $file){
            if ($ver < $newVer)
                continue;
                
            if (!isset($arrFiles[$newVer])){
                echo "ERROR: Cannot get SQL script for version #{$newVer}. ";
                $ver = $oSQL->d("SELECT MAX(verNumber) FROM stbl_version");
                echo "Current version is {$ver}.\r\n";ob_flush();flush();
                return;
            }
            $oSQL->do_query("START TRANSACTION");
            $this->parse_mysql_dump($arrFiles[$newVer]);
            $oSQL->do_query("INSERT INTO stbl_version (verNumber, verDate, verFlagVersioned, verDesc) VALUES ({$newVer}, NOW(), 1, '')");
            $oSQL->do_query("COMMIT");
            $ver = $oSQL->d("SELECT MAX(verNumber) FROM stbl_version");
            echo "Version is now #".sprintf("%03d",$ver)."\r\n";ob_flush();flush();
            $newVer = $ver+1;
        }

        echo "Execution complete";

    } else {
        echo "Nowhere to update. Currenct DB version is bigger than this update.\r\n\r\n";
    }
    

    echo '</pre></body></html>';

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