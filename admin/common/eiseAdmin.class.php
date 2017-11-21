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
                    , "stbl_entity"
                    , "stbl_role_action"
                    , "stbl_status"
                    , "stbl_status_attribute"
                    , "stbl_framework_version"
                    , "stbl_uom"
                );

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

    $oSQL = $this->oSQL;
    $dbName = $this->getDBName();

    $sqlTab = "SHOW TABLES FROM `".$dbName."`";
    $rsTab = $oSQL->do_query($sqlTab);
    
    $arrFlags = Array();

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
            $strRet .= '<li id="ent-'.$dbName."-".$rwEnt['entID']. '"><a href="entity_form.php?dbName='.urlencode($dbName).'&entID='.urlencode($rwEnt['entID']). '"><i class="fa fa-cog"></i> '
                .$rwEnt['entTitle'.$intra->local]
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

    if ($arrFlags["hasMultiLang"]){
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


    $strRet .= '
      <li>
        <a href="#">
          <i class="fa fa-dashboard"></i> <span>Dashboard</span> <i class="fa fa-angle-left pull-right"></i>
        </a>
        <ul class="sidebar-submenu">
          <li><a href="../../index.html"><i class="fa fa-circle-o"></i> Dashboard v1</a></li>
          <li><a href="../../index2.html"><i class="fa fa-circle-o"></i> Dashboard v2</a></li>
        </ul>
      </li>
      <li>
        <a href="#">
          <i class="fa fa-files-o"></i>
          <span>Layout Options</span>
          <span class="label label-primary pull-right">4</span>
        </a>
        <ul class="sidebar-submenu" style="display: none;">
          <li><a href="top-nav.html"><i class="fa fa-circle-o"></i> Top Navigation</a></li>
          <li><a href="boxed.html"><i class="fa fa-circle-o"></i> Boxed</a></li>
          <li><a href="fixed.html"><i class="fa fa-circle-o"></i> Fixed</a></li>
          <li class=""><a href="collapsed-sidebar.html"><i class="fa fa-circle-o"></i> Collapsed Sidebar</a>
          </li>
        </ul>
      </li>
      <li>
        <a href="../widgets.html">
          <i class="fa fa-th"></i> <span>Widgets</span>
          <small class="label pull-right label-info">new</small>
        </a>
      </li>
      <li>
        <a href="#">
          <i class="fa fa-pie-chart"></i>
          <span>Charts</span>
          <i class="fa fa-angle-left pull-right"></i>
        </a>
        <ul class="sidebar-submenu">
          <li><a href="../charts/chartjs.html"><i class="fa fa-circle-o"></i> ChartJS</a></li>
          <li><a href="../charts/morris.html"><i class="fa fa-circle-o"></i> Morris</a></li>
          <li><a href="../charts/flot.html"><i class="fa fa-circle-o"></i> Flot</a></li>
          <li><a href="../charts/inline.html"><i class="fa fa-circle-o"></i> Inline charts</a></li>
        </ul>
      </li>
      <li class="active">
        <a href="#">
          <i class="fa fa-laptop"></i>
          <span>UI Elements</span>
          <i class="fa fa-angle-left pull-right"></i>
        </a>
        <ul class="sidebar-submenu">
          <li><a href="../UI/general.html"><i class="fa fa-circle-o"></i> General</a></li>
          <li><a href="../UI/icons.html"><i class="fa fa-circle-o"></i> Icons</a></li>
          <li><a href="../UI/buttons.html"><i class="fa fa-circle-o"></i> Buttons</a></li>
          <li><a href="../UI/sliders.html"><i class="fa fa-circle-o"></i> Sliders</a></li>
          <li><a href="../UI/timeline.html"><i class="fa fa-circle-o"></i> Timeline</a></li>
          <li><a href="../UI/modals.html"><i class="fa fa-circle-o"></i> Modals</a></li>
        </ul>
      </li>
      <li>
        <a href="#">
          <i class="fa fa-edit"></i> <span>Forms</span>
          <i class="fa fa-angle-left pull-right"></i>
        </a>
        <ul class="sidebar-submenu">
          <li><a href="../forms/general.html"><i class="fa fa-circle-o"></i> General Elements</a></li>
          <li><a href="../forms/advanced.html"><i class="fa fa-circle-o"></i> Advanced Elements</a></li>
          <li><a href="../forms/editors.html"><i class="fa fa-circle-o"></i> Editors</a></li>
        </ul>
      </li>
      <li class="active">
        <a href="#">
          <i class="fa fa-table"></i> <span>Tables</span>
          <i class="fa fa-angle-left pull-right"></i>
        </a>
        <ul class="sidebar-submenu">
          <li><a href="../tables/simple.html"><i class="fa fa-circle-o"></i> Simple tables</a></li>
          <li><a href="../tables/data.html"><i class="fa fa-circle-o"></i> Data tables</a></li>
        </ul>
      </li>
      <li>
        <a href="../calendar.html">
          <i class="fa fa-calendar"></i> <span>Calendar</span>
          <small class="label pull-right label-danger">3</small>
        </a>
      </li>
      <li>
        <a href="../mailbox/mailbox.html">
          <i class="fa fa-envelope"></i> <span>Mailbox</span>
          <small class="label pull-right label-warning">12</small>
        </a>
      </li>
      <li>
        <a href="#">
          <i class="fa fa-folder"></i> <span>Examples</span>
          <i class="fa fa-angle-left pull-right"></i>
        </a>
        <ul class="sidebar-submenu">
          <li><a href="../examples/invoice.html"><i class="fa fa-circle-o"></i> Invoice</a></li>
          <li><a href="../examples/profile.html"><i class="fa fa-circle-o"></i> Profile</a></li>
          <li><a href="../examples/login.html"><i class="fa fa-circle-o"></i> Login</a></li>
          <li><a href="../examples/register.html"><i class="fa fa-circle-o"></i> Register</a></li>
          <li><a href="../examples/lockscreen.html"><i class="fa fa-circle-o"></i> Lockscreen</a></li>
          <li><a href="../examples/404.html"><i class="fa fa-circle-o"></i> 404 Error</a></li>
          <li><a href="../examples/500.html"><i class="fa fa-circle-o"></i> 500 Error</a></li>
          <li><a href="../examples/blank.html"><i class="fa fa-circle-o"></i> Blank Page</a></li>
          <li><a href="../examples/pace.html"><i class="fa fa-circle-o"></i> Pace Page</a></li>
        </ul>
      </li>
      <li>
        <a href="#">
          <i class="fa fa-share"></i> <span>Multilevel</span>
          <i class="fa fa-angle-left pull-right"></i>
        </a>
        <ul class="sidebar-submenu">
          <li><a href="#"><i class="fa fa-circle-o"></i> Level One</a></li>
          <li>
            <a href="#"><i class="fa fa-circle-o"></i> Level One <i class="fa fa-angle-left pull-right"></i></a>
            <ul class="sidebar-submenu">
              <li><a href="#"><i class="fa fa-circle-o"></i> Level Two</a></li>
              <li>
                <a href="#"><i class="fa fa-circle-o"></i> Level Two <i class="fa fa-angle-left pull-right"></i></a>
                <ul class="sidebar-submenu">
                  <li><a href="#"><i class="fa fa-circle-o"></i> Level Three</a></li>
                  <li><a href="#"><i class="fa fa-circle-o"></i> Level Three</a></li>
                </ul>
              </li>
            </ul>
          </li>
          <li><a href="#"><i class="fa fa-circle-o"></i> Level One</a></li>
        </ul>
      </li>
      <li><a href="../../documentation/index.html"><i class="fa fa-book"></i> <span>Documentation</span></a></li>
      <li class="sidebar-header">LABELS</li>
      <li><a href="#"><i class="fa fa-circle-o text-red"></i> <span>Important</span></a></li>
      <li><a href="#"><i class="fa fa-circle-o text-yellow"></i> <span>Warning</span></a></li>
      <li><a href="#"><i class="fa fa-circle-o text-aqua"></i> <span>Information</span></a></li>
    </ul>';

    return $strRet;

    $strRet = '<ul class="sidebar-menu ei-menu">'."\r\n";

    //$strRet .= '<li class="sidebar-header"><span class="fa fa-bars sidebar-toggle"> </span>
    //    <span>'.$this->translate('Menu').'</span></li>';

    $strRet .= '<li id="menu_root" class="active"><a href="database_form.php?dbName='.urlencode($dbName).'"><i class="fa fa-database"></i><strong>'.$dbName.'</strong></a>'."\r\n";

    $sqlTab = "SHOW TABLES FROM `".$dbName."`";
    $rsTab = $oSQL->do_query($sqlTab);
    
    $arrFlags = Array();

    $strRet .= '<ul class="sidebar-submenu">';

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

    if ($arrFlags["hasEntity"]){

        $strRet .= '<li id="'. 'ent-'.$dbName. '" class="open"><a href="entity_list.php"><span class="bold">'.$this->translate('Entities').'</span></a>'."\r\n";

        $strRet .= '<ul class="sidebar-submenu">'."\r\n";
    
        $sqlEnt = "SELECT * FROM `{$dbName}`.`stbl_entity`";
        $rsEnt = $oSQL->do_query($sqlEnt);
        while ($rwEnt = $oSQL->fetch_array($rsEnt)){
            $strRet .= '<li id="ent-'.$dbName."-".$rwEnt['entID']. '"><a href="entity_form.php?dbName='.urlencode($dbName).'&entID='.urlencode($rwEnt['entID']). '"><span class="bold">'
                .$rwEnt['entTitle'.$intra->local]
                .'</span></a>'."\r\n";
        }

        $strRet .= "</ul>\r\n</li>\r\n";

    }

    if ($arrFlags["hasPages"]){
        $strRet .= '<li id="pag-'.$dbName.'"><a href="page_list.php?dbName='.urlencode($dbName).'"><span class="bold">'.$this->translate('Pages').'</span></a></li>'."\r\n";
        $strRet .= '<li id="rol-'.$dbName.'"><a href="role_form.php?dbName='.urlencode($dbName).'"><span class="bold">'.$this->translate('Roles').'</span></a></li>'."\r\n";
        $strRet .= '<li id="prg-'.$dbName.'"><a href="matrix_form.php?dbName='.urlencode($dbName).'"><span class="bold">'.$this->translate('Page-role matrix').'</span></a></li>'."\r\n";
    }
    if ($arrFlags["hasMultiLang"]){
        $strRet .= '<li id="str-'.$dbName.'"><a href="translation_form.php"><span class="bold">'.$this->translate('Translation table').'</span></a></li>'."\r\n";
    }

    foreach ($arrTbl as $tableName) {
        $strRet .= '<li id="'.$dbName.'-'.$tableName.'"><a href="table_form.php?dbName='.urlencode($dbName).'&tblName='.urlencode($tableName).'"><span>'.$tableName.'</span></a></li>'."\r\n";
    }

    $strRet .= "</ul>\r\n</li>\r\n</ul>";
    
    return $strRet;

}

function getDBName(){

    if($_POST['dbName']){
        $_SESSION['DBNAME'] = $_POST['dbName'];
    } 
    if($_GET['dbName']){
        $_SESSION['DBNAME'] = $_GET['dbName'];
    } 

    $dbName = ($_SESSION['DBNAME'] 
        ? $_SESSION['DBNAME'] 
        : ($_COOKIE['dbName']
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
    while($rwFields = $oSQL->f($rsFields)){
        $arrFields[$rwFields['Field']]=$rwFields;
        $strFields .= ($strFields=='' ? '' : ', ')."`{$rwFields['Field']}`";
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
                           . ' (`' . $strFields . '`) VALUES';
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

    $sqlTable = "SELECT * FROM `{$tableName}`";
    $result = $oSQL->q($sqlTable);
    while ($row = $oSQL->f($result)) {
        $current_row++;
        foreach ($row as $j=>$value) {

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
            /*
            $insert_line = $schema_insert;
            for ($i = 0; $i < $fields_cnt; $i++) {
                if (0 == $i) {
                    $insert_line .= ' ';
                }
                if ($i > 0) {
                    // avoid EOL blank
                    $insert_line .= ',';
                }
                $insert_line .= $field_set[$i] . ' = ' . $values[$i];
            }

            $insert_line .= ' WHERE ' . PMA_getUniqueCondition($result, $fields_cnt, $fields_meta, $row);
            */
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
