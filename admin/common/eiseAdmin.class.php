<?php 

class eiseAdmin extends eiseIntra {

public static $arrMenuTables = Array(
                "stbl_page_role"
                , "stbl_page"
                , "stbl_role"
                );

public static $arrEntityTables = Array(
                    "stbl_action"
                    , "stbl_action_status"
                    , "stbl_action_attribute"
                    , "stbl_attribute"
                    , 'stbl_checklist'
                    , "stbl_entity"
                    , "stbl_role_action"
                    , "stbl_status"
                    , "stbl_status_attribute"
                    , "stbl_framework_version"
                    , "stbl_uom"
                );

/**
 * Below are the fields of entities tables as per eiseIntra framework version 115
 */
public static $arrEntitiesFields = [
    'stbl_action' => [
            'actID',
            'actEntityID',
            'actOldStatusID',
            'actNewStatusID',
            'actTrackPrecision',
            'actTitle',
            'actTitleLocal',
            'actTitlePast',
            'actTitlePastLocal',
            'actButtonClass',
            'actDescription',
            'actDescriptionLocal',
            'actFlagDeleted',
            'actPriority',
            'actFlagComment',
            'actFlagSystem',
            'actShowConditions',
            'actFlagHasEstimates',
            'actFlagDepartureEqArrival',
            'actFlagAutocomplete',
            'actFlagNot4Editor',
            'actFlagNot4Creator',
            'actFlagMultiple',
            'actDepartureDescr',
            'actArrivalDescr',
            'actFlagInterruptStatusStay',
            'actInsertBy',
            'actInsertDate',
            'actEditBy',
            'actEditDate',
        ],
    'stbl_action_status' => [
            'atsID',
            'atsActionID',
            'atsOrder',
            'atsOldStatusID',
            'atsNewStatusID',
            'atsInsertBy',
            'atsInsertDate',
            'atsEditBy',
            'atsEditDate',
        ],
    'stbl_action_attribute' => [
            'aatID',
            'aatActionID',
            'aatAttributeID',
            'aatFlagToTrack',
            'aatFlagMandatory',
            'aatFlagToChange',
            'aatFlagToAdd',
            'aatFlagToPush',
            'aatFlagEmptyOnInsert',
            'aatFlagUserStamp',
            'aatFlagTimestamp',
            'aatInsertBy',
            'aatInsertDate',
            'aatEditBy',
            'aatEditDate',
            'aatTemp',
        ],
    'stbl_attribute' => [
            'atrID',
            'atrEntityID',
            'atrTitle',
            'atrTitleLocal',
            'atrShortTitle',
            'atrShortTitleLocal',
            'atrFlagNoField',
            'atrType',
            'atrUOMTypeID',
            'atrOrder',
            'atrClasses',
            'atrDefault',
            'atrTextIfNull',
            'atrProgrammerReserved',
            'atrCheckMask',
            'atrDataSource',
            'atrHref',
            'atrFlagHideOnLists',
            'atrMatrix',
            'atrFlagDeleted',
            'atrInsertBy',
            'atrInsertDate',
            'atrEditBy',
            'atrEditDate',
        ],
    'stbl_entity' => [
            'entID',
            'entTitle',
            'entTitleMul',
            'entTitleLocal',
            'entMatrix',
            'entTitleLocalMul',
            'entTitleLocalGen',
            'entTitleLocalDat',
            'entTitleLocalAcc',
            'entTitleLocalIns',
            'entTitleLocalAbl',
            'entTable',
            'entPrefix',
            'entManagementRoles',
        ],
    'stbl_role_action' => [
            'rlaID',
            'rlaRoleID',
            'rlaActionID',
        ],
    'stbl_status' => [
            'staID',
            'staEntityID',
            'staTrackPrecision',
            'staTitle',
            'staTitleMul',
            'staTitleMulLocal',
            'staTitleLocal',
            'staFlagCanUpdate',
            'staFlagCanDelete',
            'staMenuItemClass',
            'staDescription',
            'staDescriptionLocal',
            'staFlagDeleted',
            'staInsertBy',
            'staInsertDate',
            'staEditBy',
            'staEditDate',
        ],
    'stbl_status_attribute' => [
            'satID',
            'satStatusID',
            'satEntityID',
            'satAttributeID',
            'satFlagEditable',
            'satFlagShowInForm',
            'satFlagShowInList',
            'satFlagTrackOnArrival',
            'satInsertBy',
            'satInsertDate',
            'satEditBy',
            'satEditDate',
            'satTemp',
        ],
    'stbl_framework_version' => [
            'fvrNumber',
            'fvrDesc',
            'fvrDate',
        ],
    'stbl_uom' => [
            'uomID',
            'uomType',
            'uomTitleLocal',
            'uomTitle',
            'uomRateToDefault',
            'uomOrder',
            'uomFlagDefault',
            'uomFlagDeleted',
            'uomCode1C',
            'uomInsertBy',
            'uomInsertDate',
            'uomEditBy',
            'uomEditDate',
        ],
];

function __construct($options){
    parent::__construct(null, $options);
}

function topLevelMenu($arrItems = null, $options = null){

    $oSQL = $this->oSQL;

    $sqlDB = "SHOW DATABASES";

    $rsDB = $oSQL->do_query($sqlDB);

    $items = array();

    while($rwDB = $oSQL->f($rsDB)){
        $items[$rwDB['Database']] = $rwDB['Database'];
    }

    return parent::topLevelMenu($items);

}

function menu($target = null){

    GLOBAL $intra;
    
    $oSQL = $this->oSQL;
    $dbName = $this->getDBName();

    $sqlTab = "SHOW TABLES FROM `".$dbName."`";
    $rsTab = $oSQL->do_query($sqlTab);
    
    $arrFlags = Array();

    $arrTbl = [];

    while ($rwTab = $oSQL->fa($rsTab)) {

        $tableName = $rwTab[0];
        
        if ($tableName=="stbl_page") $arrFlags["hasPages"] = true;
        if ($tableName=="stbl_role") $arrFlags["hasRoles"] = true;
        if ($tableName=="stbl_entity") $arrFlags["hasEntity"] = true;
        if ($tableName=="stbl_translation") $arrFlags["hasMultiLang"] = true;

        if($this->conf['hideSTBLs'] && preg_match('/^(stbl_|svw_)/i', $tableName))
            continue;

        $arrTbl[$tableName] = $tableName;
    
    }

    $strRet = '<ul class="sidebar-menu ei-menu">';

    $strRet .= '<li class="sidebar-header"><i class="fa fa-database"> </i> <span>'.$dbName.' <a href="database_form.php?dbName='.urlencode($dbName).'"><i class="fa fa-angle-double-right"> </i></a></span></li>';

    if ($arrFlags["hasEntity"]){

        $strRet .= '<li id="'. 'ent-'.$dbName. '" class="open active"><a href="#"><i class="fa fa-cogs"></i> <span>'.$this->translate('Entities').'</span> <i class="fa fa-angle-left pull-right"></i></a>'."\n";

        $strRet .= '<ul class="sidebar-submenu">'."\n";
        
        $strRet .= '<li><a href="entity_list.php"><i class="fa fa-cogs"></i> '.$this->translate('All Entities').'</a></li>'."\n";

        $sqlEnt = "SELECT * FROM `{$dbName}`.`stbl_entity`";
        $rsEnt = $oSQL->do_query($sqlEnt);
        while ($rwEnt = $oSQL->fetch_array($rsEnt)){
            $local = (isset($intra->local)) ? $intra->local : '';
            $strRet .= '<li id="ent-'.$dbName."-".$rwEnt['entID']. '"><a href="entity_form.php?dbName='.urlencode($dbName).'&entID='.urlencode($rwEnt['entID']). '"><i class="fa fa-cog"></i> '
                .$rwEnt['entTitle'.$local]
                .'</a>'."\n";
        }

        $strRet .= "</ul>\n</li>\n";

    }

    

    if ($arrFlags["hasPages"]){

        $strRet .= '<li class="open active"><a href="#"><i class="fa fa-list-alt"></i> <span>'.$this->translate('Menu / Permissions').'</span> <i class="fa fa-angle-left pull-right"></i></a>'."\n";

        $strRet .= '<ul class="sidebar-submenu">'."\n";

        $strRet .= '<li id="pag-'.$dbName.'"><a href="page_list.php?dbName='.urlencode($dbName).'"><i class="fa fa-sitemap"></i> '.$this->translate('Site map').'</a></li>'."\n";
        $strRet .= '<li id="rol-'.$dbName.'"><a href="role_form.php?dbName='.urlencode($dbName).'"><i class="fa fa-users"></i> '.$this->translate('Roles').'</a></li>'."\n";
        $strRet .= '<li id="prg-'.$dbName.'"><a href="matrix_form.php?dbName='.urlencode($dbName).'"><i class="fa fa-th"></i> '.$this->translate('Page-role matrix').'</a></li>'."\n";

        $strRet .= "</ul>\n</li>\n";
    }

    if (isset($arrFlags["hasMultiLang"]) && $arrFlags["hasMultiLang"]){
        $strRet .= '<li id="str-'.$dbName.'"><a href="translation_form.php"><i class="fa fa-language"></i> '.$this->translate('Translation table').'</a></li>'."\r\n";
    }

    if(count($arrTbl)>0){

        $strRet .= '<li class="open active"><a href="#"><i class="fa fa-th"></i> <span>'.$this->translate('Tables').'</span> <i class="fa fa-angle-left pull-right"></i></a>'."\n";

        $strRet .= '<ul class="sidebar-submenu">'."\n";

        $strRet .= '<li><a href="database_form.php?dbName='.urlencode($dbName).'"><i class="fa fa-database"></i> '.$this->translate('All Tables').'</a></li>'."\n";

        foreach ($arrTbl as $tableName) {
            $strRet .= '<li id="'.$dbName.'-'.$tableName.'"><a href="table_form.php?dbName='.urlencode($dbName).'&tblName='.urlencode($tableName).'"><i class="fa fa-table"></i> <span>'.$tableName.'</span></a></li>'."\r\n";
        }  

        $strRet .= "</ul>\n</li>\n";
    }

    return $strRet;
    
}

function getStatuses_dropdown($entID){

    GLOBAL $oSQL, $intra;

    $arrStatus = [];
    $arrStatusFull = [];
    if($oSQL->d("SHOW TABLES LIKE 'stbl_status'")){
        $sqlSta = "SELECT * FROM stbl_status WHERE staEntityID='".$entID."' ORDER BY staID";
        $rsSta = $oSQL->do_query($sqlSta);
        while($rwSta = $oSQL->fetch_array($rsSta)){
            $arrStatus[$rwSta['staID']] = $rwSta['staTitle'.$intra->local];
            $arrStatusFull[] = $rwSta;
        }
    }

    return [$arrStatus, $arrStatusFull];

}

function getActions_dropdown($entID){

    GLOBAL $oSQL, $intra;

    $arrActions_dropdown = [];
    if($oSQL->d("SHOW TABLES LIKE 'stbl_action'")){
        $sqlSta = "SELECT * FROM stbl_action WHERE actEntityID='".$entID."' ORDER BY actTitle{$intra->local}";
        $rsAct = $oSQL->do_query($sqlSta);
        while($rwAct = $oSQL->fetch_array($rsAct)){
            $arrActions_dropdown[$rwAct['actID']] = $rwAct['actTitle'.$intra->local];
        }
    }

    return $arrActions_dropdown;
    
}
function getAttributes_dropdown($entID){

    GLOBAL $oSQL, $intra;

    $arrActions_dropdown = [];
    if($oSQL->d("SHOW TABLES LIKE 'stbl_attribute'")){
        $sql = "SELECT * FROM stbl_attribute WHERE atrEntityID='".$entID."' ORDER BY atrOrder";
        $rs = $oSQL->do_query($sql);
        while($rw = $oSQL->fetch_array($rs)){
            $arrActions_dropdown[$rw['atrID']] = $rw['atrTitle'.$intra->local]." ({$rw['atrID']})";
        }
    }

    return $arrActions_dropdown;
    
}

function getDBName(){

    global $_SESSION;

    if(isset($_POST['dbName']) && $_POST['dbName']){
        $_SESSION['DBNAME'] = $_POST['dbName'];
    } 
    if(isset($_GET['dbName']) && $_GET['dbName']){
        $_SESSION['DBNAME'] = $_GET['dbName'];
    } 

    $dbName = (isset($_SESSION['DBNAME']) && $_SESSION['DBNAME'] 
        ? $_SESSION['DBNAME'] 
        : (isset($_COOKIE['dbName']) && $_COOKIE['dbName']
            ? $_COOKIE['dbName']
            : 'mysql')
        );

    SetCookie("dbName", $dbName, $this->conf['cookieExpire'], $this->conf['cookiePath']);

    $this->conf['selItemTopLevelMenu'] = $dbName;

    return $dbName;

}

/*  common funcitons for various scripts of eiseAdmin */
/* funciton dumps tables specified in $arrTables according to $arrOptions */

function dumpTables($arrTables, $arrOptions=Array()){

    $oSQL = $this->oSQL;

    $arrDefaultOptions = array('DropCreate' => true
        , 'crlf'=>"\n"
        , 'sql_type'=>'INSERT'
        , );

    $arrOptions = array_merge($arrDefaultOptions, $arrOptions);

    $strDump = '';

    foreach($arrTables as $ix=>$value){

        if (is_integer($ix)) {
            $tableName = $value;
            $tableOptions = Array();
        } else {
            $tableName = $ix;
            $tableOptions = $value;
        }

        $strDump .= $this->dumpTable($tableName, array_merge($arrOptions, $tableOptions));

    }

    return $strDump;

}


function dumpTable ($tableName, $tableOptions){

    $oSQL = $this->oSQL;
    $flagView = false;

    $tableOptions = array_merge(array(
        'DropCreate' => true
        , 'crlf'=>"\n"
        , 'sql_type'=>'INSERT'
        , 'sql_delayed'=>false
        , 'sql_ignore'=>false
        , 'sql_columns'=>false
        , 'flagNoData'=>false
        , 'rows'=>null
        ), $tableOptions);
    
    $tableInfo = $oSQL->getTableInfo($tableName);

    if(!$tableInfo) {
        return "\n/* Warning: table '{$tableName}' could not be dumped because it doesn't seem to exist */\n";
    }
    

    $crlf = $tableOptions['crlf'];
    $strDump = '';

    // recognize is it table or view or hell knows what it is
    $sqlKind = "SHOW FULL TABLES LIKE '{$tableName}'";
    $rsKind = $oSQL->q($sqlKind);

    $rwKind = $oSQL->f($rsKind);
    switch($rwKind['Table_type']){

        case "VIEW":
            $flagView = true;
            $objKind = 'VIEW';
            break;
        case "BASE TABLE":
            $objKind = 'TABLE';
            break;
        default:
            throw new Exception("Unknown object type/unknown database object: {$tableName}", 1);
            
    }

    if (!$flagView)
        $strDump .= $crlf."SET FOREIGN_KEY_CHECKS=0;{$crlf}";
    
    // if DropCreate - add DROP... CREATE statements;
    if ($tableOptions['DropCreate']){

        $strDump .= $crlf."DROP {$objKind} IF EXISTS `{$tableName}`;".$crlf;

        $sqlGetCreate = "SHOW CREATE {$objKind} `{$tableName}`";
        $fieldCreate = "Create ".ucfirst(strtolower($objKind));

        $rsCreate = $oSQL->q($sqlGetCreate);
        $rwCreate = $oSQL->f($rsCreate);

        $strCreate = $rwCreate[$fieldCreate];

        if ($flagView){
            // wash out damn alogorithm and definer
            $strCreate = preg_replace('/(ALGORITHM\=UNDEFINED\s+DEFINER=[\S]+\s+SQL\s+SECURITY\s+DEFINER)/','',$strCreate);
        }

        $strDump .= $strCreate.';'.$crlf;

    }

    // if there's a view - nothing to dump
    if ($flagView || $tableOptions['flagNoData'])
        return $strDump;


    // otherwise dump all data in table---------------------- 
    // THANKS PMA TEAM for the code -------------------------
    // slightly updated by ISE at 2013

    $arrFields = array();$strFields = '';
    $sqlFields = "SHOW FIELDS FROM `{$tableName}`";
    $rsFields = $oSQL->q($sqlFields);
    while($rwField = $oSQL->f($rsFields)){

        $columns = (!empty($tableOptions['columns']) && is_array($tableOptions['columns']) 
            ? $tableOptions['columns']
            : Array());
        if( !$columns || ($columns && in_array($rwField['Field'], $columns)) ) {
            $arrFields[$rwField['Field']] = $rwField;
            $strFields .= ($strFields=='' ? '' : ', ')."`{$rwField['Field']}`";  
        }
        
    }


    if (!$tableOptions['DropCreate'] 
        && $tableOptions['sql_type'] == 'UPDATE') {
        // update
        $schema_insert  = 'UPDATE ';
        if ($tableOptions['sql_ignore']) {
            $schema_insert .= 'IGNORE ';
        }
        $schema_insert .= "`{$tableName}` SET";
    } else {
        // insert or replace
        if ($tableOptions['sql_type'] == 'REPLACE') {
            $sql_command    = 'REPLACE';
        } else {
            $sql_command    = 'INSERT';
        }

        // delayed inserts?
        if ($tableOptions['sql_delayed']) {
            $insert_delayed = ' DELAYED';
        } else {
            $insert_delayed = '';
        }

        // insert ignore?
        if ($tableOptions['sql_type'] == 'INSERT' && (!$tableOptions['DropCreate'] && isset($GLOBALS['sql_ignore']))) {
            $insert_delayed .= ' IGNORE';
        }

        // scheme for inserting fields
        if ($tableOptions['sql_columns']) {
            $schema_insert = $sql_command . $insert_delayed ." INTO `{$tableName}`"
                           . ' (' . $strFields . ') VALUES';
        } else {
            $schema_insert = $sql_command . $insert_delayed ." INTO `{$tableName}`"
                           . ' VALUES';
        }
    }

    $search       = array("\x00", "\x0a", "\x0d", "\x1a"); //\x08\\x09, not required
    $replace      = array('\0', '\n', '\r', '\Z');
    $current_row  = 0;
    $query_size   = 0;
    if (!$tableOptions['DropCreate'] 
        && $tableOptions['sql_type'] == 'UPDATE') {
        $separator    = ';';
    } else {
        $separator    = ',';
        $schema_insert .= $crlf;
    }

    $where = '';
    if ($tableOptions['rows'] ){
        if(@count($tableInfo['PK']) == 1){
            $where = "WHERE {$tableInfo['PK'][0]} IN ('".implode("', '", $tableOptions['rows'])."')";
        } else {
            $where = "WHERE (".implode(', ', $tableInfo['PK']).") IN ((".implode('), (', $tableOptions['rows'])."))";
        }
    }

    $sqlTable = "SELECT * FROM `{$tableName}` {$where}";
    $result = $oSQL->q($sqlTable);
    while ($row = $oSQL->f($result)) {
        $current_row++;
        $values = [];
        foreach ($row as $j=>$value) {

            if(!in_array($j, array_keys($arrFields)))
                continue;

            $rwCol = $arrFields[$j];

            // NULL
            if (!isset($row[$j]) || is_null($row[$j])) {
                $values[]     = 'NULL';
            // a number
            } elseif (preg_match("/int/i", $rwCol["Type"])
                || preg_match("/float/i", $rwCol["Type"])
                || preg_match("/double/i", $rwCol["Type"])
                || preg_match("/decimal/i", $rwCol["Type"])
                || preg_match("/bit/i", $rwCol["Type"])
                ) {
                $values[] = $row[$j];

            // a BLOB
            } elseif (preg_match("/binary/i", $rwCol["Type"])
                || preg_match("/blob/i", $rwCol["Type"])
                || preg_match("/text/i", $rwCol["Type"])) {
                
                // empty blobs need to be different, but '0' is also empty :-(
                if (empty($row[$j]) && $row[$j] != '0') {
                    $values[] = '\'\'';
                } else {
                    $values[] = '0x' . bin2hex($row[$j]);
                }
            // something else -> treat as a string
            } else {
                $values[] = '\'' . str_replace($search, $replace, self::PMA_sqlAddslashes($row[$j])) . '\'';
            } // end if
        } // end foreach

        // should we make update?
        if (!$tableOptions['DropCreate'] 
        && $tableOptions['sql_type'] == 'UPDATE') {

            $insert_line = $schema_insert;
            $fields = '';
            $i = 0;
            $pk_vals = [];
            foreach ($tableInfo['columns_index'] as $field) {
                if(in_array($field, $tableInfo['PK'])){
                    $pk_vals[$field] = $values[$i];
                    $i++;
                    continue;
                }

                $fields .= (!$fields ? ' ' : ', ') . $field . ' = ' . $values[$i];
                $i++;
            }
            $where = '';
            foreach ($pk_vals as $field => $value) {
                $where .= ($where ? ' AND ' : '')."{$field}={$value}";
            }

            $insert_line .= $fields." WHERE {$where}";
            
        } else {

            // Extended inserts case
            //if (isset($GLOBALS['sql_extended'])) {
            if (true) {
                if ($current_row == 1) {
                    $insert_line  = $schema_insert . '(' . implode(', ', $values) . ')';
                } else {
                    $insert_line  = '(' . implode(', ', $values) . ')';
                    if (isset($tableOptions['sql_max_query_size']) 
                            && $tableOptions['sql_max_query_size'] > 0 && $query_size + strlen($insert_line) > $tableOptions['sql_max_query_size']) {
                        
                        $strDump .= ';' . $crlf;
                        
                        $query_size = 0;
                        $current_row = 1;
                        $insert_line = $schema_insert . $insert_line;
                    }
                }
                $query_size += strlen($insert_line);
            }
            // Other inserts case
            else {
                $insert_line      = $schema_insert . '(' . implode(', ', $values) . ')';
            }
        }
        unset($values);

        $strDump .= ($current_row == 1 ? '' : $separator . $crlf) . $insert_line;

    } // end while
    if ($current_row > 0) {
        $strDump .= ';' . $crlf;
    }

    if (!$flagView)
        $strDump .= "SET FOREIGN_KEY_CHECKS=1;{$crlf}";

    return $strDump;

}


function getTableHash($tableName){

    $oSQL = $this->oSQL;

    $sqlTable = "SHOW COLUMNS FROM {$tableName}";
    $rsTable = $oSQL->q($sqlTable);
    while($rwTable = $oSQL->f($rsTable)){$arrCols[] = $rwTable['Field'];}

    $sqlMD5 = "SELECT MD5( GROUP_CONCAT( CONCAT_WS('#',".implode(',', $arrCols).") SEPARATOR '##' ) ) FROM {$tableName}";
    $md5 = $oSQL->d($sqlMD5);

    return $md5;

}


function PMA_sqlAddslashes($a_string = '', $is_like = false, $crlf = false, $php_code = false)
{
    if ($is_like) {
        $a_string = str_replace('\\', '\\\\\\\\', $a_string);
    } else {
        $a_string = str_replace('\\', '\\\\', $a_string);
    }

    if ($crlf) {
        $a_string = str_replace("\n", '\n', $a_string);
        $a_string = str_replace("\r", '\r', $a_string);
        $a_string = str_replace("\t", '\t', $a_string);
    }

    if ($php_code) {
        $a_string = str_replace('\'', '\\\'', $a_string);
    } else {
        $a_string = str_replace('\'', '\'\'', $a_string);
    }

    return $a_string;
} // end of the 'PMA_sqlAddslashes()' function

}
