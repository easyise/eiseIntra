<?php
include "common/auth.php";
include "common/common.php";

set_time_limit(1200);
ob_start();
ob_implicit_flush(true);
$DataAction = isset($_POST["DataAction"]) ? $_POST["DataAction"] : $_GET["DataAction"];
$dbName = $_GET["dbName"];
$oSQL->select_db($dbName);

switch($DataAction) {

case 'dump':
    
    header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past
    
    
    switch ($_GET['what']) {
        case 'security':
            $arrTablesToDump = $arrMenuTables;
            break;
        case 'entities':
            $arrTablesToDump = $arrEntityTables;
            break;
        case 'tables':
            $arrTablesToDump = explode('|', $_GET['strTables']);
            break;
        default:
            break;
    }

    $arrOptions= Array();
    if($_GET['flagNoData']) $arrOptions['flagNoData'] = true;

    $strTables = dumpTables($oSQL, $arrTablesToDump, $arrOptions);

    if ($_GET['flagDonwloadAsDBSV']){
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
        $arrActions[]= Array ("title" => "Back to form"
           , "action" => ($_SERVER['HTTP_REFERER'] ? $_SERVER['HTTP_REFERER'] : "database_form.php?dbName=".urlencode($dbName))
           , "class" => "ss_arrow_left"
        );  
        include(eiseIntraAbsolutePath.'inc-frame_top.php');
        ?>

<textarea style="position:absolute;width:90%;height:90%;"><?php echo $strTables ?></textarea>

        <?php
        include(eiseIntraAbsolutePath.'inc-frame_bottom.php');

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

case "create":

if ($_POST["dbName_key"]==""){

   echo "<pre>";   
   
   //print_r($_POST);
   
   include_once ( eiseIntraAbsolutePath."inc_dbsv.php" );
    $dbsv = new eiseDBSV(array('intra' => $intra
            , 'dbsvPath'=>eiseIntraAbsolutePath.".SQL"
            , 'DBNAME' => 'mysql'));
   $frameworkDBVersion = $dbsv->getNewVersion();
   
   echo "Database initial script for framework version ".$frameworkDBVersion."\r\n";
   
   
   //create new database
    $sqlDB = "CREATE DATABASE `".$_POST["dbName"]."` /*!40100 CHARACTER SET utf8 COLLATE utf8_general_ci */";
    if ($_POST["flagRun"])
        $oSQL->do_query($sqlDB);
   
   
   
   $sqlTable['CREATE TABLE stbl_version'] = "
CREATE TABLE `stbl_version` (
    `verNumber` INT UNSIGNED NOT NULL,
    `verDesc` TEXT NULL,
    `verFlagVersioned` TINYINT(4) NOT NULL DEFAULT '0',
    `verDate` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`verNumber`)
)
COMMENT='Version information for the system';
   ";
   
   $sqlTable['INSERT INTO stbl_version'] = "INSERT INTO stbl_version (
    verNumber
    , `verDesc`
    , `verFlagVersioned`
    , `verDate`
) VALUES (
    1, 'Version 001', 1
    ,  NOW());";

   $sqlTable['CREATE TABLE stbl_framework_version'] = "
CREATE TABLE `stbl_framework_version` (
  `fvrNumber` int unsigned NOT NULL,
  `fvrDesc` text,
  `fvrDate` datetime DEFAULT NULL,
  PRIMARY KEY (`fvrNumber`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Version history for the framework';
   ";
   
   $sqlTable['INSERT INTO stbl_framework_version'] = "INSERT INTO stbl_framework_version (
    `fvrNumber`
    , `fvrDesc`
    , `fvrDate`
) VALUES (
    {$frameworkDBVersion}
    , 'Version {$frameworkDBVersion}'
    ,  NOW());";
   
   $sqlTable['CREATE TABLE stbl_setup'] = "CREATE TABLE `stbl_setup` (
    `stpID` INT(11) NOT NULL AUTO_INCREMENT,
    `stpVarName` VARCHAR(255) NULL DEFAULT NULL,
    `stpCharType` VARCHAR(20) NULL DEFAULT NULL,
    `stpDataSource` varchar(50) NULL,
    `stpCharValue` VARCHAR(1024) NULL DEFAULT NULL,
    `stpCharValueLocal` VARCHAR(1024) NULL DEFAULT NULL,
    `stpFlagReadOnly` TINYINT(4) NULL DEFAULT NULL,
    `stpNGroup` INT(11) NULL DEFAULT NULL,
    `stpTitle` VARCHAR(256) NULL DEFAULT NULL,
    `stpTitleLocal` VARCHAR(256) NULL DEFAULT NULL,
    `stpInsertBy` VARCHAR(50) NULL DEFAULT NULL,
    `stpInsertDate` DATETIME NULL DEFAULT NULL,
    `stpEditBy` VARCHAR(50) NULL DEFAULT NULL,
    `stpEditDate` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`stpID`),
    KEY `IX_stpVarName` (`stpVarName`)
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB COMMENT='System settings';";

if ($_POST["hasPages"]=="on") {

   $sqlTable['CREATE TABLE `stbl_page`'] = "CREATE TABLE `stbl_page` (
    `pagID` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `pagParentID` INT(11) UNSIGNED NULL DEFAULT NULL,
    `pagTitle` VARCHAR(255) NULL DEFAULT NULL,
    `pagTitleLocal` VARCHAR(255) NULL DEFAULT NULL,
    `pagIdxLeft` INT(11) UNSIGNED NULL DEFAULT NULL,
    `pagIdxRight` INT(11) UNSIGNED NULL DEFAULT NULL,
    `pagFlagShowInMenu` TINYINT(4) UNSIGNED NULL DEFAULT NULL,
    `pagFile` VARCHAR(255) NULL DEFAULT NULL,
    `pagTable` VARCHAR(20) NULL DEFAULT NULL,
    `pagEntityID` VARCHAR(3) NULL DEFAULT NULL,
    `pagFlagSystem` TINYINT(4) UNSIGNED NULL DEFAULT NULL,
    `pagFlagHierarchy` TINYINT(4) UNSIGNED NULL DEFAULT NULL,
    `pagInsertBy` VARCHAR(30) NULL DEFAULT NULL,
    `pagInsertDate` DATETIME NULL DEFAULT NULL,
    `pagEditBy` VARCHAR(30) NULL DEFAULT NULL,
    `pagEditDate` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`pagID`),
    INDEX `pagIdxLeft` (`pagIdxLeft`),
    INDEX `pagIdxRight` (`pagIdxRight`)
)
COMMENT='The table defines the list and the structure of all scripts'
COLLATE='utf8_general_ci'
ENGINE=InnoDB;";

   $sqlTable['CREATE TABLE `stbl_role` '] = "
CREATE TABLE `stbl_role` (
    `rolID` VARCHAR(10) NOT NULL DEFAULT '',
    `rolTitle` VARCHAR(255) NULL DEFAULT NULL,
    `rolTitleLocal` VARCHAR(255) NULL DEFAULT NULL,
    `rolFlagDefault` TINYINT(4) NULL DEFAULT '0',
    `rolFlagDeleted` TINYINT(4) NULL DEFAULT '0',
    `rolInsertBy` VARCHAR(30) NULL DEFAULT NULL,
    `rolInsertDate` DATETIME NULL DEFAULT NULL,
    `rolEditBy` VARCHAR(30) NULL DEFAULT NULL,
    `rolEditDate` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`rolID`),
    UNIQUE INDEX `rolTitle` (`rolTitle`)
)
COMMENT='This table defines roles in the application' COLLATE='utf8_general_ci' ENGINE=InnoDB;";
   
   $sqlTable['CREATE TABLE `stbl_page_role`'] = "
CREATE TABLE `stbl_page_role` (
    `pgrID` INT(11) NOT NULL AUTO_INCREMENT,
    `pgrPageID` INT(11) UNSIGNED NULL DEFAULT NULL,
    `pgrRoleID` VARCHAR(10) NOT NULL DEFAULT '',
    `pgrFlagRead` TINYINT(4) NULL DEFAULT NULL,
    `pgrFlagCreate` TINYINT(4) NULL DEFAULT NULL,
    `pgrFlagUpdate` TINYINT(4) NULL DEFAULT NULL,
    `pgrFlagDelete` TINYINT(4) NULL DEFAULT NULL,
    `pgrFlagWrite` TINYINT(4) NULL DEFAULT NULL,
    `pgrInsertBy` VARCHAR(30) NULL DEFAULT NULL,
    `pgrInsertDate` DATETIME NULL DEFAULT NULL,
    `pgrEditBy` VARCHAR(30) NULL DEFAULT NULL,
    `pgrEditDate` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`pgrID`),
    INDEX `IX_pgrPageID` (`pgrPageID`),
    INDEX `IX_pgrRoleID` (`pgrRoleID`),
    CONSTRAINT `FK_Page` FOREIGN KEY (`pgrPageID`) REFERENCES `stbl_page` (`pagID`) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `FK_Role` FOREIGN KEY (`pgrRoleID`) REFERENCES `stbl_role` (`rolID`) ON UPDATE CASCADE ON DELETE CASCADE
)
COMMENT='Authorization table, assigns script rights to users'
COLLATE='utf8_general_ci'
ENGINE=InnoDB;
   ";
   
   $sqlTable['CREATE TABLE `stbl_user`'] = "
CREATE TABLE `stbl_user` (
    `usrID` VARCHAR(255) NOT NULL DEFAULT '',
    `usrName` VARCHAR(255) NULL DEFAULT NULL,
    `usrNameLocal` VARCHAR(255) NULL DEFAULT NULL,
    `usrAuthMethod` VARCHAR(255) NULL DEFAULT 'DB',
    `usrPass` VARCHAR(32) NULL DEFAULT NULL,
    `usrFlagLocal` TINYINT(4) NULL DEFAULT '0',
    `usrPhone` VARCHAR(30) NULL DEFAULT NULL,
    `usrEmail` VARCHAR(255) NULL DEFAULT NULL,
    `usrEmployeeID` INT(11) NULL DEFAULT NULL COMMENT 'Employee from tbl_employee',
    `usrFlagDeleted` TINYINT(4) NULL DEFAULT NULL,
    `usrInsertBy` VARCHAR(50) NULL DEFAULT NULL,
    `usrInsertDate` DATETIME NULL DEFAULT NULL,
    `usrEditBy` VARCHAR(50) NULL DEFAULT NULL,
    `usrEditDate` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`usrID`),
    INDEX `IX_Auth` (`usrID`, `usrPass`)
)
COMMENT='User authentication table'
COLLATE='utf8_general_ci'
ENGINE=InnoDB;
   ";
    
    $sqlTable['CREATE TABLE `stbl_role_user`'] = "CREATE TABLE `stbl_role_user` (
    `rluID` INT(11) NOT NULL AUTO_INCREMENT,
    `rluUserID` VARCHAR(255) NULL DEFAULT NULL,
    `rluRoleID` VARCHAR(10) NOT NULL DEFAULT '',
    `rluInsertBy` VARCHAR(30) NULL DEFAULT NULL,
    `rluInsertDate` DATETIME NULL DEFAULT NULL,
    `rluEditBy` VARCHAR(30) NULL DEFAULT NULL,
    `rluEditDate` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`rluID`),
    UNIQUE INDEX `rluRoleUser` (`rluRoleID`, `rluUserID`),
    INDEX `FK_rluUserID` (`rluUserID`),
    CONSTRAINT `FK_rluRoleID` FOREIGN KEY (`rluRoleID`) REFERENCES `stbl_role` (`rolID`) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `FK_rluUserID` FOREIGN KEY (`rluUserID`) REFERENCES `stbl_user` (`usrID`) ON UPDATE CASCADE ON DELETE CASCADE
)
COMMENT='Assigns users to respective roles in the application'
COLLATE='utf8_general_ci'
ENGINE=InnoDB;";
    
   $sqlTable['CREATE TABLE `stbl_user_log`'] = "
CREATE TABLE `stbl_user_log` (
  `uslID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uslUsrID` varchar(50) DEFAULT NULL,
  uslTicket varchar(255) DEFAULT NULL,
  `uslAuthCode` varchar(20) DEFAULT NULL,
  `uslAuthMessage` varchar(254) DEFAULT NULL,
  `uslPageName` varchar(255) DEFAULT NULL,
  `uslProtocol` varchar(255) DEFAULT NULL,
  `uslMethod` varchar(20) DEFAULT NULL,
  `uslGET` varchar(255) DEFAULT NULL,
  `uslPOST` varchar(1024) DEFAULT NULL,
  `uslCookies` varchar(1024) DEFAULT NULL,
  `uslAuthType` varchar(20) DEFAULT NULL,
  `uslRemoteIP` varchar(15) DEFAULT NULL,
  `uslUserAgent` varchar(255) DEFAULT NULL,
  `uslTime` datetime DEFAULT NULL,
  PRIMARY KEY (`uslID`),
  KEY `IX_logTicket` (uslTicket)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT 'User activity log';
   ";
 
//data for stbl_page_role
    $sqlTable['INSERT INTO `stbl_page`'] = "
INSERT INTO `stbl_page` (`pagID`, `pagParentID`, `pagTitle`, `pagTitleLocal`, `pagIdxLeft`, `pagIdxRight`, `pagFlagShowInMenu`, `pagFile`, `pagTable`, `pagEntityID`, `pagFlagSystem`, `pagFlagHierarchy`, `pagInsertBy`, `pagInsertDate`, `pagEditBy`, `pagEditDate`) VALUES
    (1, NULL, 'index.php', 'index.php', 1, 28, 0, '/index.php', NULL, NULL, NULL, NULL, NULL, '2011-11-19 17:57:54', NULL, '2011-11-19 17:57:56'),
    (4, 1, 'About', 'О системе', 24, 25, 1, '/about.php', '', '', 0, 0, '', '2011-11-21 00:59:51', '', '2013-06-18 00:00:00'),
    (5, 1, 'Settings', 'Настройки', 2, 15, 1, '', '', '', 0, 0, '', '2011-11-21 15:23:20', '', '2011-11-21 00:00:00'),
    (6, 5, 'Security', 'Безопасность', 3, 12, 1, '', '', '', 0, 0, '', '2011-11-21 15:25:01', '', '2013-06-18 00:00:00'),
    (7, 6, 'Users', 'Пользователи', 4, 7, 1, '/users_list.php', '', '', 0, 0, '', '2011-11-21 15:25:47', '', '2013-06-18 00:00:00'),
    (8, 6, 'Access Control', 'Доступ', 8, 9, 1, '/role_form.php', '', '', 0, 0, '', '2011-11-21 15:32:01', '', '2011-12-23 00:00:00'),
    (17, 1, 'ajax_details.php', 'ajax_details.php', 16, 17, 0, '/ajax_details.php', '', '', 0, 0, '', '2011-12-06 03:01:44', '', '2013-06-18 00:00:00'),
    (22, 6, 'Change Password', 'Смена пароля', 10, 11, 1, '/password_form.php', '', '', 0, 0, '', '2011-12-16 17:52:33', '', '2011-12-22 00:00:00'),
    (23, 5, 'System setup', 'Системные настройки', 13, 14, 1, '/setup_form.php', '', '', 0, 0, '', '2011-12-16 17:53:15', '', '2013-06-18 00:00:00'),
    (26, 7, 'User form', 'Пользователь', 5, 6, 0, '/user_form.php', '', '', 0, 0, '', '2011-12-22 00:04:49', '', '2013-06-18 00:00:00'),
    (29, 1, 'ajax_dropdownlist.php', 'ajax_dropdownlist.php', 18, 19, 0, '/ajax_dropdownlist.php', '', '', 0, 0, '', '2012-02-15 23:57:22', '', '2013-06-18 00:00:00'),
    (30, 1, 'entityitem_form.php', 'entityitem_form.php', 20, 21, 0, '/entityitem_form.php', '', '', 0, 0, '', '2012-02-16 00:01:41', '', '2013-06-18 00:00:00'),
    (31, 1, '/popup_file.php', '/popup_file.php', 26, 27, 0, '/popup_file.php', '', '', 0, 0, '', '2012-07-19 02:52:03', '', '2013-06-18 00:00:00');
    ";
    
     $sqlTable['INSERT INTO `stbl_role`'] = "
INSERT INTO `stbl_role` (`rolID`, `rolTitle`, `rolTitleLocal`, `rolFlagDefault`, `rolFlagDeleted`, `rolInsertBy`, `rolInsertDate`, `rolEditBy`, `rolEditDate`) VALUES
    ('Everyone', 'Everyone', 'Пользователи', 1, 0, 'admin', NOW(), 'admin', NOW()),
    ('admin', 'Administrators', 'Администратор', 0, 0, 'admin', NOW(), 'admin', NOW());
";
    
    $sqlTable['INSERT INTO `stbl_page_role`'] = "
INSERT INTO `stbl_page_role` (`pgrID`, `pgrPageID`, `pgrRoleID`, `pgrFlagRead`, `pgrFlagCreate`, `pgrFlagUpdate`, `pgrFlagDelete`, `pgrFlagWrite`, `pgrInsertBy`, `pgrInsertDate`, `pgrEditBy`, `pgrEditDate`) VALUES
    (78, 1, 'Everyone', 1, 0, 0, 0, 0, '', NULL, '', NULL),
    (80, 4, 'Everyone', 1, 0, 0, 0, 0, '', NULL, '', NULL),
    (81, 5, 'Everyone', 1, 0, 0, 0, 0, '', NULL, '', NULL),
    (82, 6, 'Everyone', 1, 0, 0, 0, 0, '', NULL, '', NULL),
    (83, 7, 'Everyone', 0, 0, 0, 0, 0, '', NULL, '', NULL),
    (84, 8, 'Everyone', 0, 0, 0, 0, 0, '', NULL, '', NULL),
    (85, 17, 'Everyone', 1, 0, 0, 0, 0, '', NULL, '', NULL),
    (87, 22, 'Everyone', 1, 0, 0, 0, 1, '', NULL, '', NULL),
    (88, 23, 'Everyone', 1, 0, 0, 0, 0, '', NULL, '', NULL),
    (89, 26, 'Everyone', 0, 0, 0, 0, 0, '', NULL, '', NULL),
    (90, 1, 'admin', 1, 0, 0, 0, 0, '', NULL, '', NULL),
    (92, 4, 'admin', 1, 0, 0, 0, 0, '', NULL, '', NULL),
    (93, 5, 'admin', 1, 0, 0, 0, 0, '', NULL, '', NULL),
    (94, 6, 'admin', 1, 0, 0, 0, 0, '', NULL, '', NULL),
    (95, 7, 'admin', 1, 0, 0, 0, 1, '', NULL, '', NULL),
    (96, 8, 'admin', 1, 0, 0, 0, 1, '', NULL, '', NULL),
    (97, 17, 'admin', 1, 0, 0, 0, 0, '', NULL, '', NULL),
    (99, 22, 'admin', 1, 0, 0, 0, 0, '', NULL, '', NULL),
    (100, 23, 'admin', 1, 0, 0, 0, 1, '', NULL, '', NULL),
    (101, 26, 'admin', 1, 0, 0, 0, 1, '', NULL, '', NULL),
    (104, 29, 'admin', 1, 0, 0, 0, 0, '', NULL, '', NULL),
    (105, 29, 'Everyone', 1, 0, 0, 0, 0, '', NULL, '', NULL),
    (107, 30, 'admin', 1, 0, 0, 0, 1, '', NULL, '', NULL),
    (108, 30, 'Everyone', 1, 0, 0, 0, 0, '', NULL, '', NULL),
    (109, 31, 'admin', 1, 0, 0, 0, 0, '', NULL, '', NULL),
    (110, 31, 'Everyone', 1, 0, 0, 0, 0, '', NULL, '', NULL);
    ";
    
    $sqlTable['INSERT INTO `stbl_user`'] = "
INSERT INTO `stbl_user` (`usrID`, `usrName`, `usrNameLocal`, `usrAuthMethod`, `usrPass`, `usrFlagLocal`, `usrPhone`, `usrEmail`, `usrEmployeeID`, `usrFlagDeleted`, `usrInsertBy`, `usrInsertDate`, `usrEditBy`, `usrEditDate`) VALUES
    ('admin', 'Admin', 'Администратор', 'DB', '".md5($_POST["usrPass"])."', 0, '', '', NULL, 0, 'admin', NOW(), 'admin', NOW());
    ";
    
    $sqlTable['INSERT INTO stbl_role_user'] = "
INSERT INTO stbl_role_user (
rluUserID
, rluRoleID
, rluInsertBy, rluInsertDate, rluEditBy, rluEditDate
) VALUES (
'admin'
, 'admin'
, 'admin',NOW(),'admin',NOW());
    ";
    
    $sqlTable['CREATE VIEW svw_user'] = "CREATE VIEW svw_user AS
SELECT
usrID as optValue
, usrFlagDeleted as optFlagDeleted
, CAST(CONCAT(usrNameLocal, ' (', usrID ,')') AS CHAR CHARACTER SET 'utf8') COLLATE 'utf8_general_ci' as optTextLocal
, CAST(CONCAT(usrName, ' (', usrID ,')') AS CHAR CHARACTER SET 'utf8') COLLATE 'utf8_general_ci' as optText
FROM stbl_user;";
    
    $sqlTable['CREATE TABLE `stbl_translation`'] = "CREATE TABLE `stbl_translation` (
    `strKey` VARCHAR(255) NOT NULL,
    `strValue` VARCHAR(255) NOT NULL DEFAULT '',
    PRIMARY KEY (`strKey`)
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB;";
}



   
if ($_POST["hasEntity"]=="on") {
//if (false) {
   
   $sqlTable['CREATE TABLE stbl_entity'] = "
CREATE TABLE `stbl_entity` (
    `entID` VARCHAR(20) NOT NULL,
    `entTitle` VARCHAR(255) NOT NULL DEFAULT '',
    `entTitleMul` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Prural',
    `entTitleLocal` VARCHAR(255) NULL DEFAULT NULL,
    `entTitleLocalMul` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Prural in local',
    `entTitleLocalGen` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Genitive (Roditelny) in local',
    `entTitleLocalDat` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Dative (Datelny) in local',
    `entTitleLocalAcc` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Accusative (Vinitelny) in local',
    `entTitleLocalIns` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Instrumental case (Tvoritelny) in local',
    `entTitleLocalAbl` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Prepositional case (Predlozhny) in local',
    `entTable` VARCHAR(20) NULL DEFAULT NULL,
    `entPrefix` VARCHAR(3) NULL DEFAULT NULL,
    PRIMARY KEY (`entID`)
)
COMMENT='Defines entities'
COLLATE='utf8_general_ci'
   ";
   
   $sqlTable['CREATE TABLE stbl_action'] = "
CREATE TABLE `stbl_action` (
  `actID` int(11) NOT NULL AUTO_INCREMENT,
  `actEntityID` varchar(20) DEFAULT NULL,
  `actTrackPrecision` enum('date','datetime') NOT NULL DEFAULT 'datetime',
  `actTitle` varchar(255) NOT NULL DEFAULT '',
  `actTitleLocal` varchar(255) NOT NULL DEFAULT '',
  `actTitlePast` varchar(255) DEFAULT NULL,
  `actTitlePastLocal` varchar(255) DEFAULT NULL,
  `actDescription` text NOT NULL,
  `actDescriptionLocal` text NOT NULL,
  `actFlagDeleted` int(11) NOT NULL DEFAULT '0',
  `actPriority` int(11) NOT NULL DEFAULT '0',
  `actFlagComment` int(11) NOT NULL DEFAULT '0',
  `actShowConditions` varchar(255) NOT NULL DEFAULT '',
  `actFlagHasEstimates` tinyint(4) NOT NULL DEFAULT '0',
  `actFlagDepartureEqArrival` tinyint(4) NOT NULL DEFAULT '0',
  `actFlagAutocomplete` tinyint(4) NOT NULL DEFAULT '1',
  `actDepartureDescr` varchar(255) DEFAULT NULL,
  `actArrivalDescr` varchar(255) DEFAULT NULL,
  `actFlagInterruptStatusStay` tinyint(4) NOT NULL DEFAULT '0',
  `actInsertBy` varchar(255) DEFAULT NULL,
  `actInsertDate` datetime DEFAULT NULL,
  `actEditBy` varchar(20) DEFAULT NULL,
  `actEditDate` datetime DEFAULT NULL,
  PRIMARY KEY (`actID`),
  KEY `IX_actEntityID` (`actEntityID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Defines actions for entities';;
   ";
   
   $sqlTable['CREATE TABLE `stbl_action_status`'] = "CREATE TABLE `stbl_action_status` (
    `atsID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `atsActionID` INT(10) NULL DEFAULT NULL,
    `atsOldStatusID` INT(10) NULL DEFAULT NULL,
    `atsNewStatusID` INT(10) NULL DEFAULT NULL,
    `atsInsertBy` VARCHAR(50) NULL DEFAULT NULL,
    `atsInsertDate` DATETIME NULL DEFAULT NULL,
    `atsEditBy` VARCHAR(50) NULL DEFAULT NULL,
    `atsEditDate` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`atsID`)
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB";
   
   $sqlTable['CREATE TABLE stbl_role_action'] = "
CREATE TABLE `stbl_role_action` (
    `rlaID` INT(11) NOT NULL AUTO_INCREMENT,
    `rlaRoleID` VARCHAR(10) NOT NULL,
    `rlaActionID` INT(11) NOT NULL,
    PRIMARY KEY (`rlaID`),
    INDEX `IX_rlaRoleID` (`rlaRoleID`),
    INDEX `IX_rlaActionID` (`rlaActionID`)
)
COMMENT='Allow or disallow action for entity'
COLLATE='utf8_general_ci'
ENGINE=InnoDB;
    ";
    
    $sqlTable['CREATE TABLE stbl_action_log'] = "
CREATE TABLE `stbl_action_log` (
    `aclGUID` CHAR(36) NOT NULL DEFAULT '',
    `aclActionID` INT(11) NOT NULL,
    `aclEntityItemID` VARCHAR(36) NOT NULL,
    `aclOldStatusID` INT(11) NULL DEFAULT NULL,
    `aclNewStatusID` INT(11) NULL DEFAULT NULL,
    `aclPredID` CHAR(36) NULL DEFAULT NULL,
    `aclActionPhase` TINYINT(4) NOT NULL DEFAULT '0',
    `aclPrecision` ENUM('date','datetime') NOT NULL DEFAULT 'datetime',
    `aclETD` DATETIME NULL DEFAULT NULL,
    `aclETA` DATETIME NULL DEFAULT NULL,
    `aclATD` DATETIME NULL DEFAULT NULL,
    `aclATA` DATETIME NULL DEFAULT NULL,
    `aclComments` TEXT NULL,
    `aclStartBy` VARCHAR(255) NULL DEFAULT NULL,
    `aclStartDate` DATETIME NULL DEFAULT NULL,
    `aclFinishBy` VARCHAR(255) NULL DEFAULT NULL,
    `aclFinishDate` DATETIME NULL DEFAULT NULL,
    `aclCancelBy` VARCHAR(255) NULL DEFAULT NULL,
    `aclCancelDate` DATETIME NULL DEFAULT NULL,
    `aclInsertBy` VARCHAR(255) NOT NULL DEFAULT '',
    `aclInsertDate` DATETIME NOT NULL,
    `aclEditBy` VARCHAR(255) NOT NULL DEFAULT '',
    `aclEditDate` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`aclGUID`),
    INDEX `IX_aclActionID` (`aclActionID`),
    INDEX `aclEntityItemID` (`aclEntityItemID`),
    INDEX `IX_oldStatusID` (`aclOldStatusID`),
    INDEX `IX_newStatusID` (`aclNewStatusID`),
    INDEX `IX_aclATA` (`aclATA`),
    INDEX `IX_aclATD` (`aclATD`)
)
COMMENT='Stores action history on entity items'
COLLATE='utf8_general_ci'
ENGINE=InnoDB;
    ";
    
    $sqlTable['CREATE stbl_status'] = "
CREATE TABLE `stbl_status` (
  `staID` int(11) NOT NULL,
  `staEntityID` varchar(20) NOT NULL DEFAULT '',
  `staTrackPrecision` enum('date','datetime') NOT NULL DEFAULT 'datetime',
  `staTitle` varchar(255) NOT NULL DEFAULT '',
  `staTitleMul` varchar(255) NOT NULL DEFAULT '',
  `staTitleLocal` varchar(255) NOT NULL DEFAULT '',
  `staTitleLocalMul` varchar(255) NOT NULL DEFAULT '',
  `staFlagCanUpdate` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'Defines can the entity be updated in current status',
  `staFlagCanDelete` tinyint(4) NOT NULL DEFAULT '0',
  `staFlagDeleted` tinyint(4) NOT NULL DEFAULT '0',
  `staInsertBy` varchar(255) NOT NULL DEFAULT '',
  `staInsertDate` datetime DEFAULT NULL,
  `staEditBy` varchar(255) NOT NULL DEFAULT '',
  `staEditDate` datetime DEFAULT NULL,
  PRIMARY KEY (`staEntityID`,`staID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Defines entity statuses';
    ";
    
    $sqlTable['CREATE TABLE stbl_attribute'] = "
CREATE TABLE `stbl_attribute` (
  `atrID` varchar(255) NOT NULL COMMENT 'Attribute ID (equal to field name in the Master table)',
  `atrEntityID` varchar(20) NOT NULL DEFAULT '' COMMENT 'Entity ID',
  `atrTitle` varchar(255) NOT NULL DEFAULT '',
  `atrTitleLocal` varchar(255) NOT NULL DEFAULT '',
  `atrShortTitle` varchar(255) NOT NULL DEFAULT '',
  `atrShortTitleLocal` varchar(255) NOT NULL DEFAULT '',
  `atrFlagNoField` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'True when there''s no field for this attribute in Master table',
  `atrType` varchar(20) DEFAULT NULL COMMENT 'Type (see Intra types)',
  `atrUOMTypeID` varchar(10) DEFAULT NULL COMMENT 'UOM type (FK to stbl_uom)',
  `atrOrder` int(11) DEFAULT '10' COMMENT 'Defines order how this attribute appears on screen',
  `atrClasses` varchar(255) NOT NULL DEFAULT '' COMMENT 'CSS classes',
  `atrDefault` varchar(255) NOT NULL DEFAULT '' COMMENT 'Default Value',
  `atrTextIfNull` varchar(255) NOT NULL DEFAULT '' COMMENT 'Text to dispay wgen value not set',
  `atrProgrammerReserved` text COMMENT 'Reserved for programmer',
  `atrCheckMask` varchar(255) NOT NULL DEFAULT '' COMMENT 'Regular expression for data validation',
  `atrDataSource` varchar(255) NOT NULL DEFAULT '' COMMENT 'Data source (table, view or array)',
  `atrFlagHideOnLists` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'True when this attribute not shown on lists',
  `atrFlagDeleted` tinyint(11) NOT NULL DEFAULT '0' COMMENT 'True when attribute no longer used',
  `atrInsertBy` varchar(50) DEFAULT NULL,
  `atrInsertDate` datetime DEFAULT NULL,
  `atrEditBy` varchar(50) DEFAULT NULL,
  `atrEditDate` datetime DEFAULT NULL,
  PRIMARY KEY (`atrID`,`atrEntityID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Defines entity attributes';
    ";
       
    $sqlTable['CREATE stbl_action_attribute'] = "
CREATE TABLE `stbl_action_attribute` (
  `aatID` int(11) NOT NULL AUTO_INCREMENT,
  `aatActionID` int(11) NOT NULL,
  `aatAttributeID` varchar(50) NOT NULL,
  `aatFlagToTrack` tinyint(4) NOT NULL DEFAULT '0',
  `aatFlagMandatory` tinyint(4) NOT NULL DEFAULT '0',
  `aatFlagToChange` tinyint(4) NOT NULL DEFAULT '0',
  `aatFlagToAdd` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'Defines should this attribute be added or updated if it already exists on given action',
  `aatFlagToPush` tinyint(4) NOT NULL DEFAULT '0',
  `aatFlagEmptyOnInsert` tinyint(4) NOT NULL DEFAULT '0',
  `aatFlagUserStamp` tinyint(4) NOT NULL DEFAULT '0',
  `aatFlagTimestamp` varchar(50) NOT NULL DEFAULT '',
  `aatInsertBy` varchar(255) NOT NULL DEFAULT '',
  `aatInsertDate` datetime DEFAULT NULL,
  `aatEditBy` varchar(255) NOT NULL DEFAULT '',
  `aatEditDate` datetime DEFAULT NULL,
  `aatTemp` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`aatID`),
  KEY `IX_aatActionID` (`aatActionID`),
  KEY `IX_aatAttributeID` (`aatAttributeID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Defines the attributes to be set when the action is executed';
    ";
    
   $sqlTable['CREATE stbl_status_attribute'] = "
CREATE TABLE `stbl_status_attribute` (
    `satID` INT(11) NOT NULL AUTO_INCREMENT,
    `satStatusID` VARCHAR(255) NOT NULL,
    `satEntityID` VARCHAR(255) NOT NULL,
    `satAttributeID` VARCHAR(255) NOT NULL,
    `satFlagEditable` TINYINT(4) NOT NULL DEFAULT '1',
    `satFlagShowInForm` TINYINT(4) NOT NULL DEFAULT '1',
    `satFlagShowInList` TINYINT(4) NOT NULL DEFAULT '0',
    `satFlagTrackOnArrival` TINYINT(4) NOT NULL DEFAULT '0',
    `satInsertBy` VARCHAR(50) NULL DEFAULT NULL,
    `satInsertDate` DATETIME NULL DEFAULT NULL,
    `satEditBy` VARCHAR(50) NULL DEFAULT NULL,
    `satEditDate` DATETIME NULL DEFAULT NULL,
    `satTemp` VARCHAR(255) NULL DEFAULT NULL,
    PRIMARY KEY (`satID`),
    INDEX `IX_satStatusID_satEntityID_satAttributeID` (`satStatusID`, `satEntityID`, `satAttributeID`)
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB;
   ";
    
    $sqlTable['CREATE TABLE `stbl_status_log`'] = "CREATE TABLE `stbl_status_log` (
    `stlGUID` VARCHAR(36) NOT NULL,
    `stlEntityID` VARCHAR(50) NOT NULL,
    `stlEntityItemID` VARCHAR(255) NOT NULL,
    `stlStatusID` INT(11) NULL DEFAULT NULL,
    `stlArrivalActionID` VARCHAR(36) NULL DEFAULT NULL,
    `stlDepartureActionID` VARCHAR(36) NULL DEFAULT NULL,
    `stlTitle` VARCHAR(255) NOT NULL DEFAULT '',
    `stlTitleLocal` VARCHAR(255) NOT NULL DEFAULT '',
    `stlPrecision` ENUM('date','datetime') NOT NULL DEFAULT 'datetime',
    `stlATA` DATETIME NULL DEFAULT NULL,
    `stlATD` DATETIME NULL DEFAULT NULL,
    `stlInsertBy` VARCHAR(50) NULL DEFAULT NULL,
    `stlInsertDate` DATETIME NULL DEFAULT NULL,
    `stlEditBy` VARCHAR(50) NULL DEFAULT NULL,
    `stlEditDate` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`stlGUID`),
    INDEX `IX_stl_1` (`stlEntityID`, `stlStatusID`),
    INDEX `IX_stlATA` (`stlATA`),
    INDEX `IX_stlATD` (`stlATD`),
    INDEX `IX_stl` (`stlEntityItemID`)
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB;
";
    
    $sqlTable['INSERT stbl_action'] = "
INSERT INTO `stbl_action` (`actID`, `actEntityID`, `actTrackPrecision`, `actTitle`, `actTitleLocal`, `actTitlePast`, `actTitlePastLocal`, `actDescription`, `actDescriptionLocal`, `actFlagDeleted`, `actPriority`, `actFlagComment`, `actShowConditions`, `actFlagHasEstimates`, `actFlagDepartureEqArrival`, `actFlagAutocomplete`, `actDepartureDescr`, `actArrivalDescr`, `actFlagInterruptStatusStay`, `actInsertBy`, `actInsertDate`, `actEditBy`, `actEditDate`) VALUES
    (1, NULL, 'datetime', 'Create', 'Создать', 'Created', 'Создан', 'create new', 'create new', 0, 0, 0, '', 0, 0, 1, NULL, NULL, 0, 'admin', NOW(), 'admin', NOW()),
    (2, NULL, 'datetime', 'Update', 'Обновить данные', 'Updated', 'Данные обновлены', 'update existing', 'update existing', 0, 0, 0, '', 0, 0, 1, NULL, NULL, 0, 'admin', NOW(), 'admin', NOW()),
    (3, NULL, 'datetime', 'Delete', 'Удалить', 'Deleted', 'Удалено', 'delete existing', 'delete existing', 0, -1, 0, '', 0, 0, 1, NULL, NULL, 0, 'admin', NOW(), 'admin', NOW()),
    (4, NULL, 'datetime', 'Superaction', 'Superaction', 'Superaction', 'Superaction', '', '', 0, 0, 1, '', 0, 0, 0, '', '', 0, 'admin', NOW(), 'admin', NOW());
";
    
    $sqlTable['INSERT stbl_action_status'] = "
INSERT INTO `stbl_action_status` (`atsID`, `atsActionID`, `atsOldStatusID`, `atsNewStatusID`, `atsInsertBy`, `atsInsertDate`, `atsEditBy`, `atsEditDate`)
VALUES
    (1, 1, NULL, 0, NULL, NULL, NULL, NULL),
    (2, 2, NULL, NULL, NULL, NULL, NULL, NULL),
    (3, 3, 0, NULL, NULL, NULL, NULL, NULL);

    ";

    $sqlTable['CREATE TABLE `stbl_uom`'] = "CREATE TABLE `stbl_uom` (
    `uomID` VARCHAR(10) NOT NULL,
    `uomType` VARCHAR(255) NOT NULL DEFAULT '',
    `uomTitleLocal` VARCHAR(255) NOT NULL DEFAULT '',
    `uomTitle` VARCHAR(255) NOT NULL DEFAULT '',
    `uomRateToDefault` DECIMAL(12,4) NULL DEFAULT '1.0000',
    `uomOrder` INT(11) NULL DEFAULT NULL,
    `uomFlagDefault` TINYINT(4) NOT NULL DEFAULT '0',
    `uomFlagDeleted` TINYINT(4) NOT NULL DEFAULT '0',
    `uomCode1C` INT(11) NULL DEFAULT NULL,
    `uomInsertBy` VARCHAR(50) NULL DEFAULT NULL,
    `uomInsertDate` DATETIME NULL DEFAULT NULL,
    `uomEditBy` VARCHAR(50) NULL DEFAULT NULL,
    `uomEditDate` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`uomType`, `uomID`)
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB;";
    
    $sqlTable['CREATE VIEW svw_action_attribute'] = "CREATE VIEW svw_action_attribute AS
SELECT * 
FROM stbl_action_attribute 
INNER JOIN stbl_action ON aatActionID=actID
INNER JOIN stbl_attribute ON aatAttributeID=atrID AND actEntityID=atrEntityID;";
    
    $sqlTable['CREATE TABLE `stbl_comments`'] = "CREATE TABLE `stbl_comments` (
    `scmGUID` VARCHAR(50) NOT NULL,
    `scmEntityItemID` VARCHAR(50) NULL DEFAULT NULL,
    `scmAttachmentID` VARCHAR(50) NULL DEFAULT NULL,
    `scmActionLogID` VARCHAR(50) NULL DEFAULT NULL,
    `scmContent` TEXT NULL,
    `scmInsertBy` VARCHAR(50) NULL DEFAULT NULL,
    `scmInsertDate` DATETIME NULL DEFAULT NULL,
    `scmEditBy` VARCHAR(50) NULL DEFAULT NULL,
    `scmEditDate` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`scmGUID`),
    INDEX `IX_scmEntityItemID` (`scmEntityItemID`)
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB;";

    $sqlTable['CREATE TABLE `stbl_file`'] = "CREATE TABLE `stbl_file` (
    `filGUID` VARCHAR(36) NOT NULL,
    `filEntityID` VARCHAR(50) NULL DEFAULT NULL,
    `filEntityItemID` VARCHAR(255) NULL DEFAULT NULL,
    `filName` VARCHAR(255) NOT NULL DEFAULT '',
    `filNamePhysical` VARCHAR(255) NOT NULL DEFAULT '',
    `filLength` INT(10) UNSIGNED NOT NULL DEFAULT '0',
    `filContentType` VARCHAR(255) NOT NULL DEFAULT '',
    `filInsertBy` VARCHAR(255) NULL DEFAULT NULL,
    `filInsertDate` DATETIME NULL DEFAULT NULL,
    `filEditBy` VARCHAR(255) NULL DEFAULT NULL,
    `filEditDate` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`filGUID`),
    INDEX `filEntityID` (`filEntityID`, `filEntityItemID`)
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB;";

$sqlTable['CREATE TABLE `stbl_message`'] = "CREATE TABLE `stbl_message` (
  `msgID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `msgEntityID` varchar(50) DEFAULT NULL,
  `msgEntityItemID` varchar(50) DEFAULT NULL,
  `msgFromUserID` varchar(50) DEFAULT NULL,
  `msgToUserID` varchar(50) DEFAULT NULL,
  `msgCCUserID` varchar(50) DEFAULT NULL,
  `msgSubject` varchar(255) NOT NULL DEFAULT '',
  `msgText` text,
  `msgStatus` varchar(50) DEFAULT NULL,
  `msgSendDate` datetime DEFAULT NULL,
  `msgReadDate` datetime DEFAULT NULL,
  `msgFlagDeleted` tinyint(4) NOT NULL DEFAULT '0',
  `msgInsertBy` varchar(50) DEFAULT NULL,
  `msgInsertDate` datetime DEFAULT NULL,
  `msgEditBy` varchar(50) DEFAULT NULL,
  `msgEditDate` datetime DEFAULT NULL,
  PRIMARY KEY (`msgID`)
) ENGINE=InnoDB AUTO_INCREMENT=100100 DEFAULT CHARSET=utf8;";
    
    $sqlTable['INSERT INTO `stbl_uom`'] = "
INSERT INTO `stbl_uom` (`uomID`, `uomType`, `uomTitleLocal`, `uomTitle`, `uomRateToDefault`, `uomOrder`, `uomFlagDefault`, `uomFlagDeleted`, `uomCode1C`, `uomInsertBy`, `uomInsertDate`, `uomEditBy`, `uomEditDate`) VALUES
    ('dst', '', 'расстояние', 'distance', NULL, 4, 0, 0, NULL, NULL, NULL, NULL, NULL),
    ('len', '', 'длина', 'length', NULL, 5, 0, 0, NULL, NULL, NULL, NULL, NULL),
    ('loc', '', 'местоположение', 'location', NULL, 9, 0, 0, NULL, NULL, NULL, NULL, NULL),
    ('qty', '', 'количество', 'quantity', NULL, 3, 0, 0, NULL, NULL, NULL, NULL, NULL),
    ('spd', '', 'скорость', 'speed', NULL, 6, 0, 0, NULL, NULL, NULL, NULL, NULL),
    ('squ', '', 'площадь', 'square', NULL, 7, 0, 0, NULL, NULL, NULL, NULL, NULL),
    ('tmp', '', 'время', 'time', NULL, 8, 0, 0, NULL, NULL, NULL, NULL, NULL),
    ('vol', '', 'объем', 'volume', NULL, 2, 0, 0, NULL, NULL, NULL, NULL, NULL),
    ('wgt', '', 'вес', 'weight', NULL, 1, 0, 0, NULL, NULL, NULL, NULL, NULL),
    ('km', 'dst', 'км', 'km', 1.0000, 1, 1, 0, NULL, NULL, NULL, NULL, NULL),
    ('mi', 'dst', 'миль', 'miles', 1.6093, 2, 0, 0, NULL, NULL, NULL, NULL, NULL),
    ('NM', 'dst', 'м.миль', 'NM', 1.8520, 3, 0, 0, NULL, NULL, NULL, NULL, NULL),
    ('cm', 'len', 'cм', 'cm', 0.0100, 3, 0, 0, NULL, NULL, NULL, NULL, NULL),
    ('ft', 'len', 'футов', 'feet', 0.3048, 2, 0, 0, NULL, NULL, NULL, NULL, NULL),
    ('in', 'len', 'дюймов', 'inch', 0.0254, 4, 0, 0, NULL, NULL, NULL, NULL, NULL),
    ('m', 'len', 'м', 'm', 1.0000, 1, 1, 0, NULL, NULL, NULL, NULL, NULL),
    ('mm', 'len', 'мм', 'mm', 0.0010, 5, 0, 0, NULL, NULL, NULL, NULL, NULL),
    ('cnt', 'qty', 'контейнеров', 'containers', 1.0000, 3, 0, 0, NULL, NULL, NULL, NULL, NULL),
    ('crt', 'qty', 'коробок', 'cartons', 1.0000, 5, 0, 0, NULL, NULL, NULL, NULL, NULL),
    ('pal', 'qty', 'паллет', 'palletes', 1.0000, 2, 0, 0, NULL, NULL, NULL, NULL, NULL),
    ('pck', 'qty', 'упаковок', 'packs', 1.0000, 4, 0, 0, NULL, NULL, NULL, NULL, NULL),
    ('pcs', 'qty', 'шт', 'pcs', 1.0000, 1, 1, 0, NULL, NULL, NULL, NULL, NULL),
    ('kmh', 'spd', 'км/ч', 'km/h', 1.0000, 1, 1, 0, NULL, NULL, NULL, NULL, NULL),
    ('knots', 'spd', 'узлов', 'knots', 1.8520, 3, 0, 0, NULL, NULL, NULL, NULL, NULL),
    ('mph', 'spd', 'миль/ч', 'mph', 1.6093, 2, 0, 0, NULL, NULL, NULL, NULL, NULL),
    ('ms', 'spd', 'м/с', 'm/s', 3.6000, 4, 0, 0, NULL, NULL, NULL, NULL, NULL),
    ('hec', 'squ', 'га', 'hectare', 10000.0000, 2, 0, 0, NULL, NULL, NULL, NULL, NULL),
    ('km2', 'squ', 'км2', 'km2', 1000000.0000, 3, 0, 0, NULL, NULL, NULL, NULL, NULL),
    ('m2', 'squ', 'м2', 'm2', 1.0000, 1, 1, 0, NULL, NULL, NULL, NULL, NULL),
    ('day', 'tmp', 'дней', 'days', 86400.0000, 2, 0, 0, NULL, NULL, NULL, NULL, NULL),
    ('hrs', 'tmp', 'часов', 'hours', 3600.0000, 1, 0, 0, NULL, NULL, NULL, NULL, NULL),
    ('min', 'tmp', 'минут', 'min', 60.0000, 3, 0, 0, NULL, NULL, NULL, NULL, NULL),
    ('sec', 'tmp', 'секунд', 'sec', 1.0000, 4, 1, 0, NULL, NULL, NULL, NULL, NULL),
    ('l', 'vol', 'л', 'l', 0.0010, 2, 0, 0, NULL, NULL, NULL, NULL, NULL),
    ('m3', 'vol', 'м3', 'm3', 1.0000, 1, 1, 0, NULL, NULL, NULL, NULL, NULL),
    ('g', 'wgt', 'г', 'g', 0.0010, 4, 0, 0, NULL, NULL, NULL, NULL, NULL),
    ('kg', 'wgt', 'кг', 'kg', 1.0000, 1, 1, 0, NULL, NULL, NULL, NULL, NULL),
    ('lbs', 'wgt', 'фунтов', 'lbs', 0.4536, 3, 0, 0, NULL, NULL, NULL, NULL, NULL),
    ('t', 'wgt', 'т', 't', 1000.0000, 2, 0, 0, NULL, NULL, NULL, NULL, NULL);
        ";
    
    }
    
    $ii = 1;
    if ($_POST['flagRun']){
        $oSQL->select_db($_POST["dbName"]);
    }
    $nScripts = count($sqlTable);
    foreach($sqlTable as $action=>$sql){
        echo "-- Script $ii of $nScripts:\r\n".trim($sql)."\r\n\r\n";ob_flush();
        if ($_POST["flagRun"]){
            $oSQL->q($sql);
        }
        $ii++;
    }
    
    echo "</pre>";

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