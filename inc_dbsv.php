<?php 
/**
 * eiseIntra DBSV class - special class for DataBase Schema Version tool.
 *
 * Schema, i.e. table structure and routines, is kept in sequence of SQL scripts from initial system creation time. Each SQL script is a text file with set of SQL commands like CREATE, ALTER, CHANGE or DROP, to be applied to project database objects. File name has 3-digit prefix that defines its number in sequence, like 001, 002, 003 etc, no gaps. It's mandatory that script files are having '.sql' extension. DBSV executes them one by one and stores file number in special table named 'stbl_version' in project database. Current schema vesrion number is maximum script number stored in this table.
 * Directory with these SQL scripts is normally included in the same repository as PHP files. In order to keep them 'invisible' to the web server or php-fpm, it's strongly suggested to name this directory with period in the beginning of its name, like '.SQL' or '.DBSV' etc.
 *
 * Some important notice on modifying routines:
 *  Views: first DROP existing view, then CREATE. Avoid DEFINER and other junk.
 * @example DROP VIEW IF EXISTS `vw_my_view`; CREATE VIEW `vw_my_view` AS SELECT * FROM `tbl_my_table`;
 *  Functions: function update should be placed in two scripts: at first you DROP existing function, at second - you CREATE function and finish this script with double semicolon (important!).
 * @example 053-fn_xx-DROP.sql: DROP FUNCTION IF EXISTS `fn_xx`;
 * @example 054-fn_xx-CREATE.sql: CREATE FUNCTION `fn_xx` (`param` varchar(50)) RETURNS int {..function properties..} BEGIN {..function code..} END;;
 *  Stored Procedures: same as for functions - two scripts, one with DROP, second with CREATE and who semicolons in the end.
 *
 * These rules for Functions and Stored Procedures are mandatory because PHP cannot execute several semicolon-separated SQL scripts at one run. DBSV doesn't perform recursive analysis of SQL code and it just splits SQL script by ";[\r\n]" pattern (semicolon+new line symbol). It's not perfect but it works in 99.9% cases.
 * 
 * last update:
 * Support of multi-branch development with gaps in version numbering. If you wish to add a new feature to the project that updates schema and develop it in a side repository branch, you can make gaps in squence numbers. It will leave you a room for some urgent updates/fixes in master branch. In order to proceed with it, you need to add a special 'jump' script that will update stbl_version directly. Let say, that we need to jump from database schema version 095 to 120. Create a script named '096-new-feature-jump-to-120.sql' and fill it with the following content:
 * @example INSERT INTO stbl_version (verNumber, verDesc, verFlagVersioned, verDate) VALUES (119, 'jump to version 120', 1, NOW());
 *
 * As you can see, this script will shift your current schema version to 119. After execution it will be allowed to create scripts 120, 121, etc. Do not forget to put it into your new branch, forked from the current master. If master branch will be changed with schema update, simply rename this script with sequence number of new master version plus one. Fro example, if master changed to 98, rename your 'jump' to '099-new-feature-jump-to-120.sql'.
 *
 * If master schema version becomes changed as dramatically as it exceeds your gap estimation... There's the only way to handle it - icrease gap and manually rename SQL script files from you new feature branch.
 * 
 * Connection *SQL user should have enough privileges to modify schema in current database.
 *
 *
 * @package eiseIntra
 * @version 1.0.15
 *
 */
class eiseDBSV {

public $conf = array(
    'DBHOST' => 'localhost'
    , 'dbsvPath' => './.SQL'
    , 'flagDisableForeignKeyChecks' => true
    );

public $authorized = false;

private $intra;

function __construct($conf = array()){

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

            list($login, $password) = $this->intra->decodeAuthString($_POST['authstring'], true);

            if(!$this->intra->Authenticate($login, $password, 'mysql', array('flagNoSession'=>true))){
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
            if($this->conf['flagDisableForeignKeyChecks'])
                $oSQL->do_query('SET FOREIGN_KEY_CHECKS=0');
            $oSQL->do_query($query);
            if($this->conf['flagDisableForeignKeyChecks'])
                $oSQL->do_query('SET FOREIGN_KEY_CHECKS=1');
            $query = "";
		    $delimiter = ";";
        }
    }
}


}

?>