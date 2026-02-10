<?php
include "common/auth.php";

set_time_limit(1200);
ob_start();
ob_implicit_flush(true);
$DataAction = isset($_POST["DataAction"]) ? $_POST["DataAction"] : $_GET["DataAction"];
$dbName = $_GET["dbName"];
$oSQL->select_db($dbName);


function collectPKs($table, $where){
    GLOBAL $oSQL, $intra;

    $ti = $intra->getTableInfo($oSQL->dbname, $table);

    $pks = [];

    $sql = "SELECT ".implode(', ', $ti['PK'])." FROM {$table} WHERE {$where}";

    $rs = $oSQL->q($sql);
    while ($rw = $oSQL->f($rs)) {
        $pks[] = (count($ti['PK'])==1 ? $rw[$ti['PK'][0]] : "'".implode("','", $rw)."'");
        // $pks[] = $rw[$ti['PK'][0]];
    }

    return $pks;

}

switch($DataAction) {

case 'deexcelize_getCreate':

    $datatype = 'varchar(256) NOT NULL DEFAULT \'\'';
    $nCols = 64;
    
    include_once(commonStuffAbsolutePath.'eiseXLSX/eiseXLSX.php');

    try {
        $xlsx = new eiseXLSX($_FILES['excel']['tmp_name']);
    } catch(eiseXLSX_Exception $e) {
        die("ERROR: ".$e->getMessage());
    }

    $fields = '';
    for ($i=1; $i <= $nCols; $i++) { 
        $d = $xlsx->data("R1C{$i}");
        if(trim($d)!=''){
            $fields .= ($fields ? "\n\t, " : '').$_POST['prfx'].ucfirst(trim($d))." {$datatype}";
        } else 
            break;
    }

    $sql = "CREATE TABLE {$_POST['table']}(\n{$fields}\n)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    die($sql);

case 'deexcelize':
    
    include_once(commonStuffAbsolutePath.'eiseXLSX/eiseXLSX.php');

    /*
    row id by row number SQL
    UPDATE tbl_profit_share_mapping psm INNER JOIN (SELECT t.*, 
       @rownum := @rownum + 1 AS psmID_
    FROM tbl_profit_share_mapping t, 
           (SELECT @rownum := 0) r) tt ON tt.psmJobID=psm.psmJobID AND tt.psmSupInvoiceID=psm.psmSupInvoiceID AND tt.psmCode=psm.psmCode
    SET psm.psmID=psmID_ ;
    */

    try {
        $xlsx = new eiseXLSX($_FILES['excel']['tmp_name']);
        $oSQL->q("DROP TABLE IF EXISTS {$_POST['table']}");
        $oSQL->q($_POST['tableCreate']);
        $fields = $oSQL->ff($oSQL->q("SELECT * FROM {$_POST['table']} LIMIT 0,1"));
        $nRows = $xlsx->getRowCount();
        for ($i=2; $i <= $nRows ; $i++) { 
            $nField = 1;
            $vals = '';
            foreach($fields as $field){
                $vals .= ($vals ? ', ' : '').$oSQL->e($xlsx->data("R{$i}C{$nField}"));
                $nField+=1;
            }
            $sql = "INSERT INTO {$_POST['table']} VALUES ({$vals})";
            $oSQL->q($sql);
        }
    } catch(eiseXLSX_Exception $e) {
        die("ERROR: ".$e->getMessage());
    }



    die();

case 'dump':
    
    header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past
    
    
    
    $dontDump = ['stbl_framework_version', 'stbl_uom'];

    if($_GET['what']=='entity'){

        $entID = $_GET['entID'];

        $actIDs = collectPKs('stbl_action', "actEntityID='{$entID}'");

        $strTables = '';

        foreach(eiseAdmin::$arrEntityTables as $table){

            $ti = $intra->getTableInfo($oSQL->dbname, $table);    
            if(!$ti){
                $strTables .= "\n\n/***** Table {$table} not found *****/\n\n";
                continue;
            }


            if(in_array($table, $dontDump)){
                continue;
            }

            $ids = [];

            if($table=='stbl_entity'){

                $where = "entID='{$entID}'";

            } else {
                if (in_array($ti['prefix'].'EntityID', array_keys($ti['columns']))) {
                    $where = "{$ti['prefix']}EntityID='{$entID}'";
                } else {
                    if (preg_match('/^stbl_status/', $table)) {
                        // code...
                    } elseif (preg_match('/^stbl_action/', $table)
                        || preg_match('/^stbl_role_action/', $table)) {
                        $where = "{$ti['prefix']}ActionID IN (".implode(', ', $actIDs).")";
                    }
                }
            }

            $strTables .= ($strTables ? "\n\n\n" : '')."/* Dump for table {$table}  */\n\n";

            $ids = collectPKs($table, $where);
            if(!$ids) {
                $strTables .= "\n\n/* WARNING: table {$table} is empty */\n\n";
            } else {

                $strTables .= "DELETE FROM {$table} WHERE {$where};\n\n";
                $strTables .= $intra->dumpTables([$table], ['rows'=>$ids,
                            'sql_type'=>'INSERT',
                            'sql_columns'=>True,
                            'columns' => (isset($_GET['extra']) && $_GET['extra']=='withActualFields' ? eiseAdmin::$arrEntitiesFields[$table] : null),
                            'DropCreate'=>False ]
                        );
                         
            }

        }

        



    } else {

        $arrOptions= Array();

        switch ($_GET['what']) {
            case 'security':
                $arrTablesToDump = eiseAdmin::$arrMenuTables;
                break;
            case 'entity':
            case 'entities':
                $arrTablesToDump = eiseAdmin::$arrEntityTables;
                break;
            case 'tables':
            case 'rows':
                $arrTablesToDump = explode('|', $_GET['strTables']);
                if($_GET['what']=='rows'){
                    $arrOptions['rows'] = explode('|', $_GET['rows']);
                    $arrOptions['sql_type'] = 'UPDATE';
                    $arrOptions['DropCreate'] = False;
                }
                break;
            default:
                break;
        }

        if(!empty($_GET['flagNoData'])) $arrOptions['flagNoData'] = true;
        $strTables = $intra->dumpTables($arrTablesToDump, $arrOptions);

    }

    

    if (!empty($_GET['flagDownloadAsDBSV'])){
        $sqlDBSV = "SHOW TABLES FROM `$dbName` LIKE 'stbl_version'";
        if ($oSQL->d($sqlDBSV)=='stbl_version'){
            $sqlVer = 'SELECT MAX(verNumber)+1 FROM stbl_version';
            $verNumber = $oSQL->d($sqlVer);
        }
        $fileName = (!empty($verNumber) ? sprintf('%03d', $verNumber) : 'dump').'_'.
            ($_GET['what']=='tables' ? implode('-', $arrTablesToDump) : $_GET['what']).'.sql';
        header('Content-type: application/octet-stream;');
        header("Content-Disposition: attachment;filename={$fileName}");
        echo $strTables;
    } else {
        
        header("Content-Type: text/plain; charset=UTF-8");
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");

        echo $strTables;

    }
    die();


case "convert":
    
    echo "<pre>";
    $sqlDB = "SHOW TABLE STATUS FROM $dbName";
    $rsDB = $oSQL->do_query($sqlDB);
    $oSQL->dbname = $dbName;
    
    while($rwDB = $oSQL->fetch_array($rsDB))
       if ($rwDB['Comment']!="VIEW") {
          $sql = Array();
          $arrKeys = Array();
          $arrColToModify = Array();
          
          echo "Converting table ".$rwDB['Name']."\r\n";
          
          $arrTable = getTableInfo($dbName, $rwDB['Name']);
          $tblName = $rwDB['Name'];
          for ($i=0;$i<count($arrTable['columns']);$i++)
             if ($arrTable['columns'][$i]['DataType']=="text"){
                $arrCol['colName'] = $arrTable['columns'][$i]['Field'];
                $arrCol['sql_modback'] = "ALTER TABLE `".$tblName."` MODIFY `".$arrCol['colName']."` ".$arrTable['columns'][$i]['Type']." ".
                   ($arrTable['columns'][$i]['Null']=="NO" 
                     ? " NOT NULL ".(!preg_match("/TEXT/i", $arrTable['columns'][$i]['Type'])
                        ? "DEFAULT '".$arrTable['columns'][$i]['Default']."'"
                        : "")
                     : "NULL DEFAULT NULL");
                     
                $arrColToModify[] = $arrCol;
             }
          
          for ($i=0;$i<count($arrTable['keys']);$i++)
             if ($arrTable['keys'][$i]['Key_name']!="PRIMARY"){
                $arrKeys[] = $arrTable['keys'][$i]['Key_name'];
            }
           
          $arrKeys = array_unique($arrKeys);
          
          
          
          $sql[] = "ALTER TABLE $tblName CONVERT TO CHARACTER SET latin1";
          foreach($arrKeys as $key=>$value){
             $sql[] = "ALTER TABLE $tblName DROP INDEX ".$value;
          }
          
          //if ($tblName=="stbl_page_role")
          //print_r($arrKeys);
          
          
          for ($i=0;$i<count($arrColToModify);$i++){
            $sql[] = "ALTER TABLE `".$tblName."` MODIFY `".$arrColToModify[$i]['colName']."` LONGBLOB";
          }
          
          $sql[] = "ALTER TABLE $tblName CONVERT TO CHARACTER SET utf8";
          
          for ($i=0;$i<count($arrColToModify);$i++){
            $sql[] = $arrColToModify[$i]['sql_modback'];
          }
          
          //re-creating keys
          $arrCT = $oSQL->fetch_array($oSQL->do_query("SHOW CREATE TABLE $tblName"));
          $arrCTStr = preg_split('/[\r\n]/', $arrCT['Create Table']);
          for($i=0;$i<count($arrCTStr);$i++)
             if (preg_match("/KEY/", $arrCTStr[$i]) && !preg_match("/PRIMARY KEY/", $arrCTStr[$i])){
               $sql[] = "ALTER TABLE $tblName ADD ".trim(preg_replace("/,$/", "", $arrCTStr[$i]));
             }
          
          for($i=0;$i<count($sql);$i++){
             echo "     running ".$sql[$i]."\r\n";
             $oSQL->do_query($sql[$i]);
          }
          echo "\r\n";
       }
    
        echo "</pre>";
    
        break;


    case 'getDBSVdelta':
        
        // obtain updated but not versioned scripts
        $sqlVER = "SELECT * FROM stbl_version WHERE verFlagVersioned=0 AND LENGTH(verDesc)>0 AND verNumber>1 ORDER BY verNumber";
        $rsVER = $oSQL->q($sqlVER);
        if($oSQL->n($rsVER)==0){
            SetCookie("UserMessage", "No unversioned DBSV scripts found to download your delta");
            header("Location: database_form.php?dbName=$dbName");
            die();
        }

        if( ini_get('zlib.output_compression') ) { 
            ini_set('zlib.output_compression', 'Off'); 
        }

        header('Pragma: public'); 
        header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");                  // Date in the past    
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); 
        header('Cache-Control: no-store, no-cache, must-revalidate');     // HTTP/1.1 
        header('Cache-Control: pre-check=0, post-check=0, max-age=0');    // HTTP/1.1 
        header("Pragma: no-cache"); 
        header("Expires: 0"); 
        header('Content-Transfer-Encoding: none'); 

        // create archive
        include 'common/zipfile.class.php';

        $zip = new zipfile();
        $verMin = 1000; $verMax = '1';
        while ($rwVER = $oSQL->f($rsVER)) {
            $verMin = min($verMin, ($rwVER['verNumber']));
            $verMax = max($verMax, ($rwVER['verNumber']));
            $fileName = sprintf('%03d', $rwVER['verNumber']).'-'.substr($rwVER['verDesc'], 0, 25).'.sql';
            $zip->addFile(
                $rwVER['verDesc']
                , $fileName);
        }
        $fileName = ($verMin!=$verMax ? sprintf('%03d', $verMin).'-' : '') . sprintf('%03d', $verMax) . '-SQL_scripts.zip';
        header("Content-Type: application/zip");
        header('Content-Disposition: attachment; filename="' . $fileName . '"');

        // output zip file
        echo $zip->file();
        die();

    case 'removeDBSVdelta':
        // obtain updated but not versioned scripts
        $oSQL->q("START TRANSACTION");
/*        
        $sqlVER = "DELETE FROM stbl_version WHERE verFlagVersioned=0 AND LENGTH(verDesc)>0 AND verNumber>1 ORDER BY verNumber";
*/
        $sqlVER = "UPDATE stbl_version SET verFlagVersioned=1 WHERE verFlagVersioned=0 AND LENGTH(verDesc)>0 AND verNumber>1 ORDER BY verNumber";
        $oSQL->q($sqlVER);
        $oSQL->q("COMMIT");
        SetCookie("UserMessage", "Unversioned DBSV scripts deleted: ".$oSQL->a());
        header("Location: database_form.php?dbName=$dbName");
        die();

case 'applyIntra':
    
    $intra->batchStart(array('autolinefeed'=>true));

    $intra->batchEcho("Applying eiseIntra core data tables...");

    include_once ( eiseIntraAbsolutePath."inc_dbsv.php" );

    if(!$_POST['password'] || ($_POST['password']!==$_POST['password1']))
        die('Error: admin password not set');

    $dbsv = new eiseDBSV(array('intra' => $intra
            , 'dbsvPath'=>eiseIntraAbsolutePath.".SQL"
            , 'DBNAME' => $dbName));
    $frameworkDBVersion = $dbsv->getNewVersion();

    //$oSQL->startProfiling();

    $dbsv->parse_mysql_dump(eiseIntraAbsolutePath.".SQL/init.sql");

    $sqlUsr = "INSERT INTO stbl_user SET
        usrID = 'admin'
        , usrName = 'The Admin'
        , usrNameLocal = 'Администратор'
        , usrAuthMethod = 'DB'
        , usrPass = ".$oSQL->e($intra->password_hash($_POST['password']))."
        , usrInsertBy = 'admin', usrInsertDate = NOW(), usrEditBy = 'admin', usrEditDate = NOW()";
    $oSQL->q($sqlUsr);

    //$oSQL->showProfileInfo();

    $intra->batchEcho("All done!");

    die();

case "create":

if ($_POST["dbName_new"]!=""){

    header("Content-Type: text/plain; charset=UTF-8");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");

    include_once ( eiseIntraAbsolutePath."inc_dbsv.php" );

    $dbsv = new eiseDBSV(array('intra' => $intra
            , 'dbsvPath'=>eiseIntraAbsolutePath.".SQL"
            , 'DBNAME' => 'mysql'));
    $frameworkDBVersion = $dbsv->getNewVersion();
   
    echo "#Database initial script for framework version ".$frameworkDBVersion."\r\n";
   
   
   //create new database
    $sqlDB = "CREATE DATABASE `".$_POST["dbName_new"]."` /*!40100 CHARACTER SET utf8 COLLATE utf8_general_ci */";
    if ($_POST["flagRun"]){
        $oSQL->do_query($sqlDB);
        $oSQL->select_db($_POST["dbName_new"]);
    }

    $oSQL->startProfiling();

    $dbsv->parse_mysql_dump(eiseIntraAbsolutePath.".SQL/init.sql");

    $oSQL->showProfileInfo();

    die();
    
}

break;



case "upgrade":

    include eiseIntraAbsolutePath."inc_entity_item.php";
    include eiseIntraAbsolutePath."inc_dbsv.php";

    set_time_limit(0);

    //$oSQL->startProfiling();
    for ($i = 0; $i < ob_get_level(); $i++) { ob_end_flush(); }
    ob_implicit_flush(1);
    echo str_repeat(" ", 4096)."<pre>"; ob_flush();flush();
    
    $dbsv = new eiseDBSV(array('intra' => $intra
            , 'dbsvPath'=>eiseIntraAbsolutePath.".SQL"
            , 'DBNAME' => $dbName));
    
    $dbsv->ExecuteDBSVFramework($dbName);    
    
    die();

}

?>