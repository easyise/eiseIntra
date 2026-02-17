<?php
/**
 * # eiseList
 * 
 * Data listing and filtering class with support of autoloading on scroll event, Excel output, Ajax cell update, column chooser, tabs filter.
 * 
 *    Version 1.5 (4.5)
 * 
 * Formerly known as 'phpList'
 * (c)2005-2025 Eliseev Ilya https://russysdev.github.io/eiseIntra/
 * 
 * Authors: Ilya Eliseev, Pencho Belneiski, Dmitry Zakharov, Igor Zhuravlev
 * License: MIT
 * 
 * sponsored: Yusen Logistics Rus LLC
 * 
 */
class eiseList{

const DS = DIRECTORY_SEPARATOR;
const counterColumn = "phpLNums";
const cookieNameSuffix = "_LstParams";

/**
 * Configuration array with default settings. You can override them in constructor.
 * 
 * @category List Configuration
 */
public $conf = Array(
    'includePath' => '../'
    , 'dateFormat' => "d.m.Y" // 
    , 'timeFormat' => "H:i" // 
    , 'decimalPlaces' => "2"
    , 'decimalSeparator' => "."
    , 'thousandsSeparator' => ","
    , 'titleTotals' => 'Totals'
    , 'titlePleaseWait' => 'Please wait...'
    , 'titleNothingFound' => 'Nothing found'
    , 'titleERRORBadResponse' => 'ERROR: bad response'
    , 'titleTryReload' => 'try to reload this page'
    , 'titleFilterDateFrom' => 'Period Start'
    , 'titleFilterDateTill' => 'Period End'
    , 'titleFilterApply' => 'Apply'
    , 'titleFilterClear' => 'Clear'
    , 'titleFilterClose' => 'Close'
    , 'titleTabAny' => 'Any %s'
    
    , 'controlBarButtons' => 'btnSearch|btnFieldChooser|btnOpenInExcel|btnReset'

    , 'exactMatch' => false
    
    , 'dataSource' => "" //$_SERVER["PHP_SELF"]
    , 'strLocal' => ''
    , 'rowsFirstPage' => 100
    , 'rowsPerPage' => 100
    , 'maxRowsForSelection' => 5000
    , 'calcFoundRows' => true
    , 'cacheSQL' => true
    , 'doNotSubmitForm' => true

    , 'isNullFilterValue' => 'n/a'
    
    , 'cookieExpire' => 0 

    , 'hiddenColsExcel' =>  array() // array of columns to be hidden on Excel output
    , 'flagNoExcel' => false

    , 'debug' => false

    , 'tabsFilterColumn' => null // if set, list will try to breakdown data into tabs with titles from column source
);

private $oSQL, $intra;

private $arrHiddenCols = Array();
private $arrOrderByCols = Array();
private $arrCookieToSet = Array();
private $arrCookie = Array();
private $arrSession = Array();

/**
 * Default column settings
 * 
 * @category List Configuration
 */
private static $col_default = Array(
    'title' => ''
    , 'type' => 'text' // text, integer, date, datetime, select, combobox, ajax_dropdown, checkbox, row_id, order 
    , 'PK' => false // if true, column is a primary key, it will be used to update data
    , 'phpLNums' => null // if true, column will be rendered as row number, it will be used to update data
    , 'sql' => null // SQL expression, if empty, field is used
    , 'source' => null // source for select, combobox, ajax_dropdown
    , 'source_prefix' => null // source for select, combobox, ajax_dropdown, if empty, field is used 
    , 'order_field' => null // field name to order by, if empty, field is used
    , 'filter' => null
    , 'filterValue' => null // value to filter by, if empty, no filter is applied
    , 'exactMatch' => false // if true, filter will be exact match, otherwise LIKE '%filterValue%'
    , 'group' => null // field name to group by, if empty, no grouping is applied
    , 'aggregate' => null // aggregate function to apply, if empty, no aggregate is applied
    , 'flagNoExcel' => false // if true, column will not be shown in Excel output
    , 'checkbox' => false // if true, column will be rendered as checkbox
    , 'class' => '' // CSS class to apply to the column
    , 'width' => '' // CSS width to apply to the column
    , 'href' => null // if set, column will be rendered as link with this href
    , 'nourlencode' => false // if true, href will not be urlencoded
    , 'limitOutput' => 0 // if set, column will be limited to this number of characters
);

public $name;

public $strSQL, 
    $sqlPK,
    $sqlFields, $sqlFieldsAggregate, 
    $sqlFrom, $sqlFromAggregate, $fieldsForCount,
    $sqlWhere, 
    $sqlGroupBy, 
    $sqlHaving,
    $defaultOrderBy, $defaultSortOrder, $exactSQL, $strSQLAggregate, $orderBy;

public $sortOrder = 'ASC', $sortOrderAlt = 'DESC';

public $Tabs = array(); // array of tabs, each tab is an associative array with keys: 'title', 'filterValue', 'filterField', 'filterExactMatch'

public $Columns = Array(); // array of columns, each column is an associative array

public $nCols = 0;
public $cols = '';

public $iMaxRows = 0;

public $flagExcel = false; // if true, list will output data in Excel XML format
public $flagHasAggregate = false;
public $flagHasFilters = false;
public $flagFiltersSet = false;
public $flagGroupBy = false;

public $error = '';


/**
 * Class constructor. Intra object can be passed as part of $arrConfig array with 'intra' key to inherit some settings from eiseIntra.
 * 
 * @param object $oSQL SQL object
 * @param string $strName List name
 * @param array $arrConfig Configuration array (see [eiseList::$conf](#eiselist-conf) for possible settings)
 * 
 * @category List Configuration
 */
function __construct($oSQL, $strName, $arrConfig=Array()){
    
    $this->name = $strName;
    
    $this->oSQL = $oSQL;
    
    $this->sqlFrom = (isset($arrConfig["sqlFrom"]) ? $arrConfig["sqlFrom"] : null);unset($arrConfig["sqlFrom"]);
    $this->sqlWhere = (isset($arrConfig["sqlWhere"]) ? $arrConfig["sqlWhere"] : null);unset($arrConfig["sqlWhere"]);
    $this->defaultOrderBy = (isset($arrConfig["defaultOrderBy"]) ? $arrConfig["defaultOrderBy"] : null);unset($arrConfig["defaultOrderBy"]);
    $this->defaultSortOrder = (isset($arrConfig["defaultSortOrder"]) ? $arrConfig["defaultSortOrder"] : null);unset($arrConfig["defaultSortOrder"]);
    $this->exactSQL = (isset($arrConfig["exactSQL"]) ? $arrConfig["exactSQL"] : null);unset($arrConfig["exactSQL"]);
    
    //merge with settings come from eiseINTRA
    if (isset($arrConfig["intra"]) && is_object($arrConfig["intra"])){
        $intra = $arrConfig['intra'];
        $this->conf['dateFormat'] = $intra->conf['dateFormat'];
        $this->conf['timeFormat'] = $intra->conf['timeFormat'];
        $this->conf['decimalPlaces'] = $intra->conf['decimalPlaces'];
        $this->conf['decimalSeparator'] = $intra->conf['decimalSeparator'];
        $this->conf['thousandsSeparator'] = $intra->conf['thousandsSeparator'];
        $this->conf['strLocal'] = $intra->local;
        foreach($this->conf as $key=>&$val){
            if(strpos($key, 'title')===0)
                if(isset($intra->lang[$key])) $val=$intra->lang[$key];
        }
        $this->intra = $intra;
        unset($arrConfig["intra"]);

    }

    $this->conf = array_merge($this->conf, $arrConfig);
    
    // all recognizeable dates formats
    // zero - standard date
    $this->conf['dateRegExs'][0] = array(
        'rex' => "([0-9]{4})[\-]{0,1}([0-9]{1,2})[\-]{0,1}([0-9]{1,2})"
        , 'fmt' => "Y-m-d"
        );
    // 1: local date format
    $this->conf['dateRegExs'][1] = array(
        'fmt' => $this->conf['dateFormat']
        ); 

    foreach($this->conf['dateRegExs'] as &$arr){
        if(!isset($arr['rex']))
            $arr['rex'] = str_replace(
                Array(".", '-', '/', 'd', 'm', 'y', 'Y')
              , Array('\\.', '\\-', '\\/', '([0-9]{1,2})', '([0-9]{1,2})', '([0-9]{2,4})', '([0-9]{2,4})')
              , $arr['fmt']);
        $arr['rexTime'] = "([0-9]{1,2})[\-\:]{0,1}([0-9]{1,2})([\-\:]{0,1}([0-9]{1,2}))";
        $strNakedFmt = ' '.preg_replace('/[^dmyhisa]/i', '', $arr['fmt']);
        $arr['ixOf']['Y'] = stripos($strNakedFmt, 'Y');
        $arr['ixOf']['m'] = stripos($strNakedFmt, 'm');
        $arr['ixOf']['d'] = stripos($strNakedFmt, 'd');
    }

    $this->conf["dataSource"] = ($this->conf["dataSource"]!="" ? $this->conf["dataSource"] : $_SERVER["PHP_SELF"]);
    
    $this->conf["cookieName"] = (isset($this->conf["cookieName"]) ? $this->conf["cookieName"] : $this->name).self::cookieNameSuffix;
    $this->conf["cookieName_filters"] = (isset($this->conf["cookieName_filters"]) ? $this->conf["cookieName_filters"] : $this->conf["cookieName"]);

}

public function hasColumn($fieldName){
    if(empty($this->Columns))
        return false;
    foreach($this->Columns as $ix=>$col){
        if($col['field']==$fieldName)
            return true;
    }
    return false;
}

/**
 * This method adds columns to $Columns property. 
 * 
 * @param $arrCol - associative array with column properties. See description of $Columns property.
 * @param $arrCol['field'] - is mandatory
 * @param $arrCol['fieldInsertBefore'] - field name to insert before
 * @param $arrCol['fieldInsertAfter'] - field name to insert after
 *
 * @example     $list->addColumn(Array(
 *            'title' => "QQ"
 *            , 'field' => "jcnQQ"
 *            , 'fieldInsertBefore'=>'jcnContainerSize'
 *            , 'type' => "integer"
 *            , 'totals' => "sum"
 *            , 'width' => '30px'
 *    ));
 *
 * @category List Configuration
 */
public function addColumn($arrCol){

    if(!$arrCol['field'])
        throw new Exception("No field specified");
        
    if($this->hasColumn($arrCol['field']))
        return;

    $fieldInsertBefore = isset($arrCol['fieldInsertBefore']) ? $arrCol['fieldInsertBefore'] : null;
    $fieldInsertAfter = isset($arrCol['fieldInsertAfter']) ? $arrCol['fieldInsertAfter'] : null;

    if( $fieldInsertBefore || $fieldInsertAfter ){

        $Columns_new = array();
        $flagInserted = false;

        foreach($this->Columns as $ix=>$col){

            if($col['field']==$fieldInsertBefore){
                $Columns_new[$arrCol['field']] = $arrCol;
                $flagInserted = true;
            }

            $Columns_new[$col['field']] = $col;

            if($col['field']==$fieldInsertAfter){
                $Columns_new[$arrCol['field']] = $arrCol;
                $flagInserted = true;
            }

        }

        if(!$flagInserted)
            $Columns_new[$arrCol['field']] = $arrCol;

        $this->Columns = $Columns_new;

    } else {
        $this->Columns[$arrCol['field']] = $arrCol;
    }


}

/**
 * This method changes column property to defined values and returns its previous value.
 *
 * @param string $field - field name, 'field' property of column, the search key
 * @param string $property - property name to change
 * @param variant $value - value to be set. If NULL, $property become unset from this column
 *
 * @return variant - previous property value. If property ot column is not found , it returns NULL
 * 
 * @category List Configuration
 */
public function setColumnProperty($field, $property, $value){
    $retVal = null;
    foreach($this->Columns as $ix=>$col){
        if($col['field']==$field){
            $retVal = (isset($col[$property]) ? $col[$property] : null);
            if($value!==null)
                $this->Columns[$ix][$property] = $value;
            else 
                unset($this->Columns[$ix][$property]);
            break;
        }
    }
    return $retVal;
}

/**
 * This method filters columns according to supplied array and put it in specified order
 *
 * @param array $arrColFields - array of field names to be kept and their order
 * 
 * @category List Configuration
 */
public function setColumnOrder($arrColFields){

    $Columns_new = array();
/*
    echo '<pre>';
    print_r($this->Columns);
    die();
//*/
    // 1. put all no-title columns in front of associative array
    foreach($this->Columns as $col){
        if($col['title']=='')
            $Columns_new[$col['field']] = $col;
    }

    // 2. set columns in specified order
    foreach($arrColFields as $field){
        foreach($this->Columns as $col){
            if($field===$col['field']){
                $Columns_new[$field] = $col;
                break;
            }
        }
    }

    $this->Columns = $Columns_new;

}

/**
 * This function returns column array and updates the key it could be accessed from $lst->Columns list
 * 
 * @category List Configuration
 */
public function getColumn($field, &$key=''){
    if(isset($this->Columns[$field]) && $this->Columns[$field]){
        $key = $field;
        return $this->Columns[$field];
    }
    foreach($this->Columns as $ix=>$col){
        if(isset($col['field']) && $col['field']==$field){
            $key = $ix;
            return $col;
        }
    }
    return null;
}

/**
 * This function removes column by field name.
 * 
 * @category List Configuration
 */
public function removeColumn($field){
    foreach($this->Columns as $ix=>$col){
        if($col['field']==$field){
            unset($this->Columns[$ix]);
            return $col;
        }
    }
}

/**
 * This function handles data requests and returns them in requested format: JSON, Aggregate data JSON or Excel XML.
 * 
 * It caches SQL query and columns configuration for faster response on next requests.
 * 
 * @category List Data Handling
 */
public function handleDataRequest(){ // handle requests and return them with Ajax, Excel, XML, PDF, whatsoever user can ask

    $DataAction = isset($_POST["DataAction"]) ? $_POST["DataAction"] : (isset($_GET["DataAction"]) ? $_GET["DataAction"] : null);
    if (!$DataAction || $DataAction=='getPostRequest'){
        $this->cacheColumns();
        return;
    }
    
    $this->getCachedColumns();

    if($DataAction=='updateCell')
        $this->updateCell($_POST);
    
    $oSQL = $this->oSQL;
    $this->error = "";

    if (!$this->conf["cacheSQL"] || (isset($_GET["noCache"]) && $_GET['noCache']) || $DataAction=='getPostRequest'){

        $this->handleInput();
        $this->composeSQL();
        if ($this->conf["cacheSQL"]) {
            $this->cacheSQL();
        }

    } else {
        
        $this->getCachedSQL();
    }

    if($DataAction=='getPostRequest'){
        $this->conf['flagPostRequest'] = true;
        return;
    }
    
    $iOffset = isset($_GET["offset"]) ? (int)$_GET["offset"] : 0;
    
    $this->strSQL .= ($DataAction=="json" 
        ? "\n LIMIT {$iOffset}, ".($iOffset==0 
            ? $this->conf["rowsFirstPage"] 
            : (isset($_GET["recordCount"]) 
                ? (int)$_GET["recordCount"]
                : $this->conf['rowsPerPage']
                )
            )
        : "");
    
    if ($iOffset==0 && $this->conf["calcFoundRows"]) {
        $this->strSQL = str_replace("#SQL_CALC_FOUND_ROWS", "SQL_CALC_FOUND_ROWS", $this->strSQL);
    }
    
    if($DataAction!='get_aggregate'){
        try {

            $rsData = $oSQL->q($this->strSQL);

            $nTotalRows = $iOffset+$oSQL->n($rsData);
        } catch(Exception $e){
            $this->error = $e->getMessage();
        }
    }
    
    $iStart = $iOffset;
    switch($DataAction){
        case "json":
            
            header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
            header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past
            header('Content-Type: application/json');
            
            $arrRows = Array();
            
            if ($this->error){
                echo json_encode(Array("error"=>$this->error));
                die();
            }
            while ($rw = $oSQL->f($rsData)){
                $arrRows[] = $this->getRowArray($iStart++, $rw);
            }
            
            $arrRet = Array("rows"=>$arrRows);
            $arrRet['nTotalRows'] = isset($nTotalRows) ? (int)$nTotalRows : null;
            $arrRet['nRowsReturned'] = $oSQL->n($rsData);
            
            if ($this->conf['debug']){
                $arrDebug = Array(
                    "get" => $_GET
                    , "cookie" => $_COOKIE
                    , "session" => $_SESSION
                    , "columns"=> $this->Columns
                    , "sql" => $this->strSQL
                    , "sqlAggregate" => $this->strSQLAggregate
                    , 'conf' => $this->conf
                    );
                    
                $arrRet = array_merge((array)$arrDebug, $arrRet);
                
            }
            
            echo json_encode($arrRet);
            die();

        case 'get_aggregate':
            header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
            header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past
            header('Content-Type: application/json');

            try {
                $rsAggr = $oSQL->q($this->strSQLAggregate);
                $rwAgr = $oSQL->f($rsAggr);
            } catch (Exception $e) {
                echo json_encode(array('status'=>'error'
                    , 'message'=>$e->getMessage()
                    , 'error'=>true)
                );
                die();
            }
            
            $arrRet = array('status'=>'ok', 'data'=>$rwAgr);
            if ($this->conf['debug']) {
                $arrDebug = Array(
                    "get" => $_GET
                    , "cookie" => $_COOKIE
                    , "session" => $_SESSION
                    , "columns"=> $this->Columns
                    , "sql" => $this->strSQL
                    , "sqlAggregate" => $this->strSQLAggregate
                    , 'conf' => $this->conf
                    );

                $arrRet = array_merge((array)$arrDebug, $arrRet);
            }

            echo json_encode(  $arrRet );

            die();

        case "excelXML":
            
            set_time_limit(600);
            
            include_once (dirname(__FILE__). self::DS . "inc_excelXML.php");
            
            $xl = new excelXML();

            $cols = [];
            
            foreach($this->Columns as $col) {
                if (!isset($col['title']) || $col['title'] == ''
                    || in_array($col["field"], $this->arrHiddenCols)
                    || in_array($col['field'], $this->conf['hiddenColsExcel'])
                    || (isset($col['flagNoExcel']) && $col['flagNoExcel'])
                    || (isset($col['checkbox']) && $col['checkbox'])) {

                    $col['flagNoExcel'] = true;
                    continue;
                    
                }

                $cols[] = $col;
                
                $arrHeader[$col["field"]] = $col["title"];
                
            }
            $xl->addHeader($arrHeader);
            
            /**
             * for proper decimal formatting of formatData() method we change defaults:
             */
            $this->conf['decimalSeparator'] = '.';
            $this->conf['thousandsSeparator'] = '';


            $nRow = 0;
            while ($rw = $oSQL->f($rsData)){
                $nRow++;
                $rw[self::counterColumn] = $nRow;
                
                $arrRow = Array();
                foreach($cols as $ii=>$col) {
                    if (isset($col['flagNoExcel']) && $col['flagNoExcel']) {
                        continue;
                    }
                    $arrRow[$col["field"]] = $this->formatData($col, trim($rw[$col["field"]]), $rw);

                }
                $xl->addRow($arrRow);
            }
            
            $xl->Output($this->name);
            
            die();

        case 'fieldChooser2':

            GLOBAL $intra;

            $grid = new eiseGrid($oSQL
                    , 'fieldChooser2'
                    , array('controlBarButtons' => 'moveup|movedown|delete',
                        'arrPermissions'=>array('FlagWrite'=>true))
                    );

            $grid->Columns[] = Array(
                    'field' => "colID"
                    , 'type' => "row_id"
                    , 'mandatory' => true
                    , 'width' => '100%'
            );

            $grid->Columns[] = Array(
                    'title' => '##'
                    , 'field' => "colOrder"
                    , 'type' => "order"
            );
            
            $grid->Columns[] = Array(
                    'title' => $intra->translate("Title")
                    , 'field' => "colTitle"
                    , 'type' => "text"
                    , 'static' => true
                    , 'width' => '100%'
            );


            echo '<div><span class="ui-helper-hidden-accessible"><input type="text"/></span>'.
                $grid->get_html().'</div>';
                    
            
            die();
    }
    
}


/**
 * This function directly outputs list contents in HTML.
 * 
 * @category List Display
 */
public function show(){ // draws the wrapper

    $this->handleInput();

    // if ($this->conf['tabsFilterColumn'])
    //     $this->setColumnProperty($this->conf['tabsFilterColumn'], 'title', null);

    $col_order = [];
    foreach ($this->Columns as $ix => $col) {
        if(!isset($col['title']) || !$col['title'] || in_array($col["field"], $this->arrHiddenCols))
            continue;
        $col_order[] = $col['field'];
    }
    $this->conf['column_order'] = $col_order;

?>    
<div class="eiseList" id="<?php  echo $this->name ; ?>" data-conf="<?php  
    echo htmlspecialchars(json_encode($this->conf)) ; ?>">

<form action="<?php echo $_SERVER["PHP_SELF"]; ?>">
<input type="hidden" id="DataAction" name="DataAction" value="newsearch">
<input type="hidden" id="<?php echo $this->name."OB"; ?>" name="<?php echo $this->name."OB"; ?>" value="<?php echo htmlspecialchars($this->orderBy); ?>">
<input type="hidden" id="<?php echo $this->name."ASC_DESC"; ?>" name="<?php echo $this->name."ASC_DESC"; ?>" value="<?php echo htmlspecialchars($this->sortOrder); ?>">
<input type="hidden" id="<?php echo $this->name."HiddenCols"; ?>" name="<?php echo $this->name."HiddenCols"; ?>" value="<?php echo htmlspecialchars(implode(",", $this->arrHiddenCols)); ?>">
<?php 
// Igor: Create fields for initial GET string-------------------------------------------------------------
foreach ($_GET as $key => $value) {
    if ( !($key=="offset" || $key=="DataAction" || preg_match("/^".$this->name."/",$key)) && strlen($value)>0){
        echo "<input type=hidden id=\"".$key."\" name=\"".$key."\" value=\"".urlencode($value)."\">\r\n";
    }
}
foreach ($this->Columns as $col) {
    if ( (!isset($col['title']) || !$col['title']) && isset($col['filter']) && $col['filter']!=''){
        $filterValue = isset($col["filterValue"]) ? $col["filterValue"] : '';
        echo "<input type=hidden id=\"".$this->name.'_'.$col['filter']."\" name=\"".$this->name.'_'.$col['filter']."\" value=\"".urlencode($filterValue)."\" class=\"el-filter\">\r\n";
    }
}
 ?>

<div class="el-header">
<h1><?php echo $this->conf["title"]; ?>
<span class="el-foundRows el-span-foundRows"></span>
<?php if (isset($this->conf['subtitle']) && $this->conf['subtitle']): ?>
<small><?php echo $this->conf['subtitle'] ?></small>
<?php endif ?>
</h1>

<div class="el-controlBar">

<?php 
$arrButtons = explode("|", $this->conf['controlBarButtons']);

if (in_array('btnSearch', $arrButtons)){?><input type="submit" value="Search" id="btnSearch"><?php }
if (in_array('btnFieldChooser', $arrButtons)){?><input type="button" value="Choose fields" id="btnFieldChooser"><?php }
if (in_array('btnFieldChooser2', $arrButtons)){?><input type="button" value="Choose fields..." id="btnFieldChooser2"><?php }
if (in_array('btnOpenInExcel', $arrButtons) && !$this->conf["flagNoExcel"] ){ ?><input type="button" value="Open in Excel" id="btnOpenInExcel"><?php } 
if (in_array('btnReset', $arrButtons)) {?><input type="button" value="Reset" id="btnReset"><?php } ?>
</div>
</div>

<div class="el-table">
<table>
<?php  echo $this->showTableHeader(); ?>
<tbody>
<tr class="el-spinner"><td colspan="<?php  echo $this->nCols ; ?>"><div><?php  echo $this->conf["titlePleaseWait"] ; ?></div></td></tr>
<tr class="el-notfound"><td colspan="<?php  echo $this->nCols ; ?>"><div><?php  echo $this->conf["titleNothingFound"] ; ?></div></td></tr>
<tr class="el-template"><?php echo $this->showTemplateRow(); ?></tr>
</tbody>

<?php 
if ($this->flagHasAggregate):
 ?>
<tfoot style="display:none;">
<tr><?php  echo $this->showFooterRow() ; ?></tr>
</tfoot>
<?php 
endif;
 ?>
</table>
</div>

</form>

<div class="el-fieldChooser" style="display:none;"><?php  echo $this->showFieldChooser() ; ?></div>

<div class="el_debug"></div>
</div>

<?php
}

/**
 * Show the table header with column titles and filter inputs.
 *
 * @ignore
 */
private function showTableHeader(){
    
    $oSQL = $this->oSQL;

    $firstRow = '';
    $strOut = '';

    /* first and second rows - titles and filter inputs */
    foreach($this->Columns as $ix=>$col) {

        if (!isset($col["title"]) || $col["title"]=="" || in_array($col["field"], $this->arrHiddenCols)) {
            continue;
        }
        
        $strColClass = "{$this->name}_{$col["field"]}";

        $strClassList = (isset($col['order_field']) && $col['order_field']!='' ? "el-sortable" : '');

        $strArrowImg = "";
        if ($col['field']==$this->orderBy) {
            $strClassList .= ($strClassList!='' ? ' ' : '')."el-sorted-".strtolower($this->sortOrder);
        }
        
        $w = isset($col['width'])
            ? $col['width'].(preg_match('/^[0-9]+$/', $col['width']) ? 'px' : '')
            : '';
        
        $this->Columns[$ix]['width'] = $w;

        $strClassList .= ($strClassList!='' ? ' ' : '')
            .(isset($col['type']) && $col['type']
                ? 'el-'.$col['type']
                : (isset($col['checkbox']) && $col['checkbox'] 
                    ? 'el-checkbox'
                    : 'el-text')
                )
            .' '.$strColClass;
        
        /* TD for row title */
        $strTDHead = "<th";
        $strTDHead .= ($w!='' 
            ? " style=\"width: ".$w.(preg_match('/\%$/', $w) 
                ? ';min-width: 150px;'
                : '')
                .'"'
            : '');
        $strTDHead .= " class=\"{$strClassList}\"";
        $strTDHead .= " data-field=\"{$col['field']}\"";
        if(isset($col['aggregate']) && $col['aggregate']!=''){
            $strTDHead .= " data-aggregate=\"{$col['field']}\"";
            $this->flagHasAggregate = true;
        }
        
        $strTDHead .=  ">" ;

        $strTDHead .= '<div class="el-title">'.htmlspecialchars($col['title']).'</div>';
        

        /* TD for search input */
        $strTDFilter = "";
        if ($this->flagHasFilters) {
            $filterValue = (isset($col['filterValue']) ? $col['filterValue'] : '');
            $classTD = "{$this->name}_{$col['field']}".($filterValue != '' ? " el-filterset" : "");
            $strTDFilter .= "<div class=\"el-filter {$classTD}\">";
            if (isset($col['filter']) && $col['filter']) {
                switch (isset($col['type']) ? $col['type'] : '') {
                    case "combobox":
                        $arrCombo = $this->getComboboxSource($col);
                        $col['source_raw'] = $arrCombo;
                        $strTDFilter .= "<select id='cb_".$col["filter"]."' name='".$this->name."_".$col["filter"]."' class='el-filter'>\r\n";
                        $strTDFilter .= "<option value=''>\r\n";
                        foreach ($arrCombo as $value => $text) {
                            $selected = ($filterValue===(string)$value ? " selected" : "");
                            $strTDFilter .= "<option value=\"{$value}\"{$selected}>$text\r\n";
                        }
                        $strTDFilter .= "</select>\r\n";
                        break;
                    default:
                        $strFilterBoxHTML = '';
                        $strFilterClass = '';
                        if( (isset($col['filterType']) && $col['filterType']) || (isset($col['type']) && in_array($col['type'], array('date', 'datetime'))) ) {
                            $fltType = (isset($col['filterType']) ? $col['filterType'] : (isset($col['type']) ? $col['type'] : ''));
                            $strFilterClass = ' el_special_filter el_filter_'.$fltType;
                            switch($fltType){
                                case 'date':
                                case 'datetime':
                                    $strFilterBoxHTML = '<div class="el_dateFrom"><input type="text" class="el_input_'.$fltType.'" placeholder="'.htmlspecialchars($this->conf['titleFilterDateFrom']).'" tabindex=1></div>';
                                    $strFilterBoxHTML .= '<div class="el_dateTill"><input type="text" class="el_input_'.$fltType.'" placeholder="'.htmlspecialchars($this->conf['titleFilterDateTill']).'" tabindex=2></div>';
                                    break;
                                default: 
                                    $strFilterBoxHTML = '<div class="el_textarea"><textarea></textarea></div>';
                                    break;
                            }
                            $strFilterBoxHTML = '<div id="flt_'.$this->name.'_'.$col['filter'].'" class="el_div_filter_'.$fltType.' el_div_filter">'
                                .'<span class="ui-helper-hidden-accessible"><input type="text"></span>'
                                .$strFilterBoxHTML
                                .'<div class="el_filter_buttons">'
                                .'<input type="button" class="el_btn_filter_apply" value="'.htmlspecialchars($this->conf['titleFilterApply']).'" tabindex=3>'
                                .'<input type="button" class="el_btn_filter_clear" value="'.htmlspecialchars($this->conf['titleFilterClear']).'" tabindex=4>'
                                .'<input autofocus type="button" class="el_btn_filter_close" value="'.htmlspecialchars($this->conf['titleFilterClose']).'" tabindex=0>'
                                .'</div>'
                                .'</div>';
                        }
                        $strTDFilter .= '<input type=text name="'.$this->name.'_'.$col['filter'].'" class="el_filter'.$strFilterClass.'" value="'.$filterValue.'">';
                        $strTDFilter .= $strFilterBoxHTML;
                    break;
                }
            } elseif (isset($col['checkbox']) && $col['checkbox']) {
                $strTDFilter .= "<div align='center'><input type='checkbox' style='width:auto;'".
                    "class=\"sel_{$this->name}_all\" title='Select/unselect All'></div>";
            } else {
                $strTDFilter .= "&nbsp;";
            }
            
        }

        $strTDHead .= $strTDFilter;

        $strTDHead .= "</th>\r\n";
        
        $firstRow .= $strTDHead;

        $this->nCols++;
        
    }

    $htmlTabs = '';
    $this->breakDownByTabs();
    if (!empty($this->Tabs)) {
        $htmlTabs .= "<div id=\"{$this->name}_tabs\" class=\"el-tabs ui-tabs ui-widget ui-widget-content ui-corner-all\">\n";
        $htmlTabs .= "<ul class=\"ui-tabs-nav ui-helper-reset ui-helper-clearfix ui-widget-header ui-corner-all\">\n";
        $strPseudoTabs = '';
        foreach($this->Tabs as $ix=>$tab){

            $tabId = "{$this->name}_tabs_".urlencode(
                $tab['value'] === null
                ? $this->conf['isNullFilterValue']
                : $tab['value']
                )."|{$this->name}_{$tab['filter']}";
            $htmlTabs .= "<li class=\"ui-state-default ui-corner-top\"><a href=\"#{$tabId}\" class=\"ui-tabs-anchor\">{$tab['title']}</a></li>\r\n"; 
            $strPseudoTabs .=  "<div id=\"{$tabId}\" class=\"el_pseudotabs\"></div>\r\n"; 
        }
        $htmlTabs .= "</ul>\r\n";
        $htmlTabs .= $strPseudoTabs;
        $htmlTabs .= '</div>';

    }

    $htmlTabs = ($htmlTabs ? '<caption class="el-tr-tabs"><div class="el-tabs-container">'.$htmlTabs.'</div></caption>'."\n" : '');
    
    $strOut .= "\n"
        .$htmlTabs
        ."<thead>\n"
        ."<tr class=\"el-tr-titles\">{$firstRow}</tr>\n"
        ."</thead>\n";
    
    return $strOut;

}

/**
 * This function returns combobox source basnig for column passes as **$col** parameter
 * @param array $col List column with $col['source'] and $col['source_prefix'] specified
 * @return array of value=>text pairs to fill in the combobox
 * 
 * @ignore
 */
protected function getComboboxSource($col){

    $oSQL = $this->oSQL;
    $arrCombo = Array();
    if ( is_array($col['source']) || ($arrCombo=@json_decode($col['source'], true)) ) {
        $arrCombo = ( count($arrCombo)>0 ? $arrCombo : $col['source'] );
    } else {
        if(preg_match("/^(svw_|vw_|tbl_|stbl_)/", $col['source'])){

            if(!$this->intra) {
                include (preg_replace('/\/list$/', '', dirname(__FILE__))."/inc_config.php");
                include_once (preg_replace('/\/list$/', '', dirname(__FILE__))."/inc_intra.php");
                $this->intra = new eiseIntra($this->oSQL);
            }
            $rsCMB = $this->intra->getDataFromCommonViews(null, null, $col['source']
                , (isset($col["source_prefix"]) ? $col["source_prefix"] : null)
                , 1
                , (isset($col['extra']) ? (string)$col['extra'] : '')
                , true
                , $oSQL
                );    
            $a = array();
            while($rwCMB = $oSQL->f($rsCMB)){
                $a[$rwCMB["optValue"]]=$rwCMB["optText"];
            }

            return $a;

        } else {
            $sqlCombo = $col['source'];
        }
        //echo $col['title']."\r\n";
        $rsCombo = $oSQL->do_query($sqlCombo);
        while ($rwCombo = $oSQL->fetch_array($rsCombo)) {
            $localizedKey = "optText{$this->conf['strLocal']}";
            $arrCombo[$rwCombo['optValue']] = isset($rwCombo[$localizedKey]) ? $rwCombo[$localizedKey] : (isset($rwCombo['optText']) ? $rwCombo['optText'] : '');
        }
    }

    return $arrCombo;
}

/**
 * This function appends tabs to $list->Tabs array if there's required to break down list by tabs with setting $list->conf['tabsFilterColumn'] option. List developer can assign the name of list column to it and this function will query list table for quantitative breakdown on this field. This field should have 'combobox' property and $col['source_raw'] should be filled as associative array.
 * Tabs will be ordered according to this combobox order.
 * 
 * @ignore
 */
protected function breakDownByTabs(){

    $oSQL = $this->oSQL;

    if(!$this->conf['tabsFilterColumn'])
        return;

    $col = $this->getColumn($this->conf['tabsFilterColumn']);

    if(!$col)
        return;

    if(!isset($col['source_raw']) || !$col['source_raw']){
        $col['source_raw'] = $this->getComboboxSource($col);
    }

    $this->composeSQL();

    $filter = ($col['filter'] 
        ? $col['filter'] 
        : ($col['sql'] 
            ? $col['sql'] 
            : $col['field'])
        );

    $aBreakdown = array();
    $nullTab = array();
    $totalCount = 0;
    $where = ($this->sqlWhere ? "WHERE {$this->sqlWhere}" : '');
    $sqlTabs = "SELECT {$filter} AS optValue, COUNT(*) as optCount FROM {$this->sqlFrom} 
        {$where}
        GROUP BY {$filter}
        HAVING optCount>0";
    $rsTabs = $oSQL->q($sqlTabs);
    while ($rwTabs = $oSQL->f($rsTabs)) {
        $totalCount += $rwTabs['optCount'];
        if($rwTabs['optValue']===null){
            $nullTab = $rwTabs;
            continue;
        }
        $aBreakdown[] = $rwTabs;
    }
    
    if( $nullTab ){
        $this->Tabs[] = array(
                    'title' => ((isset($col['defaultText']) && $col['defaultText']) 
                        ? $col['defaultText']
                        : ($this->intra 
                            ? $this->intra->translate('- not set -')
                            : '- not set -' )
                        )." ({$nullTab['optCount']})"
                    , 'filter' => $filter
                    , 'value' => null
              );
    }
    foreach ($col['source_raw'] as $optValue => $optText) {
        foreach ($aBreakdown as $valcount) {
            if($valcount['optValue']==$optValue){
                $this->Tabs[] = array(
                    'title' => $optText." ({$valcount['optCount']})"
                    , 'filter' => $filter
                    , 'value' => $optValue
                );
                break;
            }
        }        
    }

    if($totalCount > 0){
        $this->Tabs[] = array(
                    'title' => ($this->intra 
                            ? $this->intra->translate($this->conf['titleTabAny'], (isset($col['title']) ? $col['title'] : ''))
                            : sprintf($this->conf['titleTabAny'], (isset($col['title']) ? $col['title'] : '')) 
                        )." ({$totalCount})"
                    , 'filter' => $filter
                    , 'value' => ''
              );
    }

}

private function showTemplateRow(){

    $strRow = "";
    
    foreach($this->Columns as $col){
        if (!isset($col["title"]) || !$col["title"] || in_array($col["field"], $this->arrHiddenCols))
            continue;
            
        $strRow.= "<td data-field=\"{$col["field"]}\" class=\"".
            (isset($col["type"]) && $col["type"]!="" 
                ? "el-".$col["type"] 
                : (isset($col['checkbox']) && $col['checkbox'] ? "el-checkbox" : "el-text")).
            (isset($col['editable']) && $this->intra && ($this->intra->arrUsrData['FlagWrite'] ||$this->intra->arrUsrData['FlagUpdate'])  ? ' el-editable' : '').
            " {$this->name}_{$col["field"]}".
            '"'.
            (isset($col['target']) ? ' target="'.$col['target'].'"' : '').
            '>'.(isset($col['checkbox']) && $col['checkbox']
                ? "<input type='checkbox' name='sel_{$this->name}[]' value='' id='sel_{$this->name}_{$col['field']}_'>" 
                : "")."</td>\r\n";
        
    }
    
    return $strRow;
    
}

private function showFooterRow(){
    return "<td>&nbsp;</td>";
}

private function showFieldChooser(){
    
    $strOut = "";
    
    $jj=1;
    
    $nElementsInColumn = $this->nCols/2;
    foreach($this->Columns as $cl){
        if ( !in_array($cl["title"], Array("", "##")) && isset($cl["href"]) && $cl["href"]=="" ){
            $id = "flc_".$this->name."_".$cl["field"]."";
            $strOut .= "<input type=\"checkbox\" name=\"{$id}\" id=\"{$id}\" style=\"width:auto;\"".
                (in_array($cl["field"], $this->arrHiddenCols) ? "" : " checked").">";
            $strOut .= "<label for=\"{$id}\">".$cl["title"]."</label><br>\r\n";
            if ($jj == floor($nElementsInColumn))
                $strOut .= "</td><td>\r\n";
            $jj++;
        }
    }

    $strOut = "<div><table><tbody><tr><td>{$strOut}</td></tr></tbody></table></div>";
    
    return $strOut;
}


/**
 * This method handles eiseList input: $_GET, $_COOKIE and session parameters.
 * What can be set to the list via input:
 * - hidden columns
 * - maximum row number to obtain during the query
 * - sort order field (list should have the column with 'order_field' parameter with this field name)
 * - sort order direction
 * - filter values
 *
 * The list gets search parameters from cookie or session, but if there's something set with $_GET, this data overrides cookie settings.
 * What parameters can be set with $_GET:
 * - ```<list name>HiddenCols``` - comma-separated list of hidden columns
 * - ```<list name>MaxRows``` - maximum row number to obtain during the query
 * - ```<list name>OB``` - field name to order by
 * - ```<list name>ASC_DESC``` - direction field, can be 'ASC' or 'DESC'
 * - ```<list name>_<field name>``` - filter value(s).
 *
 * Parameters that saved with cookies are stored as serialized array under key ```$this->conf['cookieName']```.
 * Cookie array member keys are: 
 * - HiddenCols - comma-separated string with columns names to be hidden
 * - MaxRows - maximum row number to obtain
 * - OB - order by field name
 * - ASC_DESC - ordering direction (ascending/descending)
 * - ```<filter values>``` - are stored under keys consists of ```$this->name.'_'.$col['filter']``` .
 *
 * Filter values for columns with  ```'filterType'=>'multiline'``` are stored into ```$_SESSION``` variable as array member under ```$this->conf['cookieName']``` key.
 * 
 * Hidden columns array, maximum row number, sort ordering parameters are being set to the corresponding list variables.
 * Filter values are assigned to $this->Columns array members as 'filterValue' array member.
 * Afterwards it sets cookie with specified parameters and saves correspoding data into the session.
 *
 * @category List Data Handling
 */ 
private function handleInput(){

    GLOBAL $_DEBUG;

    //$_DEBUG = true;

    $this->getCookie();
    $this->getSession();

    $hiddenCols = (isset($_GET[$this->name."HiddenCols"]) ? $_GET[$this->name."HiddenCols"] : (isset($this->arrCookie["HiddenCols"]) ? $this->arrCookie["HiddenCols"] : null));
    // print_r($hiddenCols);die();
    $this->arrHiddenCols = explode(",", $hiddenCols);
    $this->iMaxRows = (int)(isset($_GET[$this->name."MaxRows"])
           ? $_GET[$this->name."MaxRows"]
           : 0
        );
    $this->orderBy =  (isset($_GET[$this->name."OB"])
        ? $_GET[$this->name."OB"]
        : (isset($this->arrCookie['OB']) && $this->arrCookie['OB'] !== ""
            ? $this->arrCookie['OB']
            : $this->defaultOrderBy
            )
        );
    $this->sortOrder = (isset($_GET[$this->name."ASC_DESC"])
        ? $_GET[$this->name."ASC_DESC"]
        : (isset($this->arrCookie["ASC_DESC"]) && $this->arrCookie["ASC_DESC"] !== ""
            ? $this->arrCookie["ASC_DESC"]
            : ($this->defaultSortOrder=="" 
                ? (isset($this->Columns[$this->orderBy]) && is_array($this->Columns[$this->orderBy]) && isset($this->Columns[$this->orderBy]['type']) && in_array($this->Columns[$this->orderBy]['type'], array('date', 'datetime ')) 
                    ? 'DESC'
                    : 'ASC'
                    )
                : $this->defaultSortOrder )
            )
        );
    $this->sortOrderAlt = ($this->sortOrder=="ASC" ? "DESC" : "ASC");

    $this->arrCookieToSet["HiddenCols"] = $hiddenCols;
    $this->arrCookieToSet["OB"] = $this->orderBy;
    $this->arrCookieToSet["ASC_DESC"] = $this->sortOrder;

    $this->arrOrderByCols = Array();

    /* dealing with filters and order_field */
    $this->flagFiltersSet = $this->flagHasFilters = false;

    $arrCookieFilter = [];

    foreach ($this->Columns as $i=>$col){

        $col = array_merge(self::$col_default, $col);

        $this->arrOrderByCols[] = ($col["order_field"]!="" ? $col["order_field"] : $col["field"]);
        if ($col["filter"]) {

            foreach((array)$this->Tabs as $ix=>$tab){
                if($tab['filter']==$col['filter']){
                    $this->Columns[$i]['exactMatch'] = true;
                    $this->Columns[$i]['tabsFilter'] = true;
                    break;
                }
            }

            if (isset($col['field']) && $this->conf['tabsFilterColumn'] == $col['field']) {
                $this->Columns[$i]['exactMatch'] = true;
                $this->Columns[$i]['tabsFilter'] = true;
            }

            $filterValue = $this->getFilterValue($col['filter']);
            $exactMatch = (isset($col['exactMatch']) ? $col['exactMatch'] : false);
            $tabsFilter = (isset($col['tabsFilter']) ? $col['tabsFilter'] : false);
            if( $filterValue !=='' || ($exactMatch && !$tabsFilter) ){
                $this->Columns[$i]['filterValue'] = $filterValue;
                $this->flagFiltersSet = true;
                // $arrCookieFilter[$col["filter"]] = $filterValue;
            }

            $this->flagHasFilters = true;

        }

    }

    // die('<pre>'.var_export($arrCookieFilter, true));
    // die('<pre>'.var_export(array_merge($this->arrCookieToSet, $arrCookieFilter), true));

    $s_cookie_w_filter = serialize(array_merge($this->arrCookieToSet, $arrCookieFilter));

    SetCookie($this->conf["cookieName"], (strlen($s_cookie_w_filter) >= 4094 ? serialize($this->arrCookieToSet) : $s_cookie_w_filter), $this->conf["cookieExpire"], $_SERVER["PHP_SELF"]);

    if ($this->flagExcel)
        $this->iMaxRows = 0;
    
    $this->conf['calcFoundRows'] = ($this->conf['calcFoundRows']==="if_filters_set" 
        ? ($this->flagFiltersSet ? true : false)
        : $this->conf['calcFoundRows']);


}

/**
 * This function updates a particular field in eiseList with DataAction=updateCell. If there's no $intra and user isn't granted to update or write on the script below, it doesn't work.
 * Otherwise it updates a field in the table specified in sqlFrom basing on primary key value. AFter update it generates json with intra and dies.
 *
 * In currenct version it works only with string and int values. NULL cannot be transferred.
 *
 * @param array $newData - data necesasry fo update, as associative array:
 *      - pk (string) - primary key value
 *      - field (string) - field name
 *      - value (string) - field value
 * 
 * @category List Data Handling
 *
 */
public function updateCell($newData = null, $opts = array()){

    if( !($this->intra && ($this->intra->arrUsrData['FlagWrite'] ||$this->intra->arrUsrData['FlagUpdate'])) )
        return;

    $oSQL = $this->oSQL;

    $newData = ($newData
        ? $newData
        : ($_SERVER['REQUEST_METHOD']=='POST'
            ? $_POST
            : null)
        );
    if(!$newData)
        return;

    $pk = '';
    foreach ($this->Columns as $c) {
        if($c['PK'])
            $pk = $c['field'];
    }

    $ti = $oSQL->getTableInfo( $this->conf['tableToUpdate'] ? $this->conf['tableToUpdate'] : $this->sqlFrom );
    $pk_table = $ti['PK'][0];

    try {
        $sql = "UPDATE {$this->sqlFrom} SET `{$newData['field']}`=".$oSQL->e($newData['value'])." WHERE {$pk_table}=".$oSQL->e($newData['pk']);
        $oSQL->q($sql);
    } catch (Exception $e) {
        $this->intra->json('ERROR:', $e->getMessage(), array());
    }
    

    if(!$opts['noRedirect']){
        if($this->intra)
            $this->intra->json('ok', '', array());
        else 
            die();
    }
    
}

/**
 * This method returns $_GET parameter name for filter field.
 * @param string $field - field name
 * @return string
 * 
 * @ignore
 */
public function getFilterParameterName( $field ){
    return $this->name.'_'.$field;
}

/**
 * This function returns cookie array.
 * @return array of cookie data for given list. If there're no cookie set, it returns null.
 */
public function getCookie(){
    $this->arrCookie = isset($_COOKIE[$this->conf["cookieName"]]) 
        ? @unserialize($_COOKIE[$this->conf["cookieName"]]) 
        : null;
    if (is_array($this->arrCookie) && count($this->arrCookie) > 0) {
        return $this->arrCookie;
    } else {
        return null;
    }
}

/**
 * This function returns session array.
 * @return array of session data for given list. If there're nothing in session, it returns null.
 * @ignore
 */
public function getSession(){
    if (!isset($_SESSION[$this->conf["cookieName"]])) {
        return null;
    }

    $this->arrSession = $_SESSION[$this->conf["cookieName"]];
    if (is_array($this->arrSession) && count($this->arrSession) > 0) {
        return $this->arrSession;
    } else {
        return null;
    }
}

/**
 * This function obtains filter value for $field parameter from $_GET.
 * 
 * @param $field string - field name to get filter value for.
 * @return string - filter value. If filter's not set, it returns NULL.
 * 
 * @category List Data Handling
 */
public function getFilterValue( $field ){


    $filterValue = '';

    $strColInputName = $this->getFilterParameterName( $field );

    return (
        isset($_GET[$strColInputName]) 
            ? $_GET[$strColInputName] 
            : (isset($this->arrCookie[$field])
                ? $this->arrCookie[$field]
                : '' 
                )
            );

}

/**
 * This function composes SQL query for the list basing on list columns, filters, sorting and grouping settings.
 * 
 * @category List Data Handling
 * 
 */
private function composeSQL(){
    
    GLOBAL $_DEBUG, $intra;
    
    $this->flagGroupBy = false;
    $this->fieldsForCount = Array();

    $this->strSQL = '';
    $this->strSQLAggregate = '';

    $this->sqlFields = $this->sqlFieldsAggregate = '';
    $this->sqlFromAggregate = $this->sqlFrom;


    foreach ($this->Columns as $i => $col){

        $col = array_merge(self::$col_default, $col);

        if (!isset($col["field"]) || $col["field"]=="" || $col["field"]=="phpLNums") 
            continue;
            
        if ($col['PK']){ //if it is PK
            $this->sqlPK = $col['field'];
            $this->sqlFieldsAggregate .= ($this->sqlFieldsAggregate ? ', ' : '').' COUNT(*) as nTotalRows';

            //$this->sqlFieldsAggregate .= ($this->sqlFieldsAggregate ? "\r\n, " : '').$col['field'];
        }

        if($col['aggregate']) {
            $this->sqlFieldsAggregate .= ($this->sqlFieldsAggregate ? "\r\n, " : '').strtoupper($col['aggregate'])."(".($col['sql'] ? "({$col['sql']})" : $col['field']).") as {$col['field']}";
        }

        // SELECT
        $sqlTextField = "";
        if (isset($col['type']) && in_array($col["type"], ["select", "ajax_dropdown", "combobox"])){
            // if combobox or ajax_dropdown, we also compose _Text suffixed field
            if (!is_array($col['source'])){
                if (preg_match("/^(vw_|tbl_|stbl_|svw_)/", $col["source"])){

                    $f = $this->oSQL->ff("SELECT * FROM `{$col['source']}` WHERE 1=0");
                    $fields = array_keys($f);

                    $titleField = (in_array("{$col["source_prefix"]}Title", $fields) ? 'Title' : 'Name');

                    $col['textField'] = ($col["source_prefix"]!="" ? "{$col['source_prefix']}{$titleField}" : "optText");
                    $col['textField_intl'] = $col['textField'];
                    $col['textField'] .= ($this->conf['strLocal'] && in_array($col['textField'].$this->conf['strLocal'], $fields) ? $this->conf['strLocal'] : '');
                    $col['idField'] = ($col["source_prefix"]!="" ? $col["source_prefix"]."ID" : "optValue");
                    $col['tableAlias'] = "t_{$col['field']}";

                    $extraConditions = (isset($col['extra']) && $col['extra'] && in_array(($col["source_prefix"]!="" ? $col["source_prefix"] : "opt")."Extra", $fields)
                                ? " AND {$col['tableAlias']}.".($col["source_prefix"]!="" ? $col["source_prefix"] : "opt")."Extra=".$this->oSQL->e($col['extra'])
                                : ''
                            );

                    $sqlJoin = " LEFT OUTER JOIN {$col['source']} {$col['tableAlias']} ON {$col['field']}={$col['tableAlias']}.{$col['idField']} {$extraConditions}\r\n";

                    if(!($col["filterValue"]=="" && !($col['exactMatch'] && !$col['tabsFilter']) ) ){
                        $this->sqlFrom .= $sqlJoin;
                        $this->sqlFromAggregate.= $sqlJoin;
                        $sqlTextField = ($col['textField_intl']!=$col['textField'] && $col["type"]=="combobox"
                            ? "CASE WHEN IFNULL({$col['tableAlias']}.{$col['textField']}, '')='' THEN {$col['tableAlias']}.{$col['textField_intl']} ELSE {$col['tableAlias']}.{$col['textField_intl']} END"
                            : "{$col['tableAlias']}.{$col['textField']}" );
                    } else {
                        $text_field_full = ($col['textField_intl']!=$col['textField'] && $col["type"]=="combobox"
                            ? "CASE WHEN IFNULL({$col['textField']}, '')='' THEN {$col['textField_intl']} ELSE {$col['textField']} END"
                            : "{$col['textField']}"
                            );
                        $sqlTextField = "(SELECT {$text_field_full} FROM `{$col["source"]}` {$col['tableAlias']} WHERE {$col['idField']}="
                            .($col["sql"]!='' && $col['sql']!=$col['field'] 
                                ? "({$col['sql']})"
                                : $col['field']
                            )
                            .$extraConditions
                            .")";
                    }

                    if(isset($col['defaultText']) && $col['defaultText'])
                        $sqlTextField = "IFNULL({$sqlTextField}, ".$this->oSQL->e($col['defaultText']).")";
                    
                    $sqlTextField = $sqlTextField." as {$col["field"]}_Text";

                    $this->sqlFields .= ( $this->sqlFields!="" ? "\r\n, " : "").$sqlTextField;
                } elseif (preg_match("/^SELECT/", $col["source"] )) {
                    
                }
            }
        }

        $this->sqlFields .= ( $this->sqlFields!="" ? "\r\n, " : ""). // if 'sql' array member is set
            ($col["sql"]!="" && $col["sql"]!=$col["field"]
                ? "(".$col["sql"].") AS '{$col["field"]}'" 
                : "`{$col["field"]}`");

        // GROUP BY
        if ($col['group']!="") { // if we should group by this column and 'sql' is set, we group by 'sql'
            $this->sqlGroupBy .= ($this->sqlGroupBy!="" ? ", " : "").($col['sql']!="" ? $col['sql'] : $col['field']);
            $this->flagGroupBy = true;
        }

        //WHERE/HAVING
        if ($col['sqlSearchCondition'] = $this->getSearchCondition($col)) {// if we filter results by this column

            $col['sqlSearchCondition'] = "({$col['sqlSearchCondition']})";

            // HAVING - only for 
            //      non-grouped columns in aggregate queries 
            //      all other columns where we search by sql 'SELECT' subquery
            // WHERE - all the rest
            if (($this->flagGroupBy && !$col['group'])
                //|| ($col["sql"]!="" && $col['filter']==$col['field'] && $col['type']!='boolean')
                )
            {
                $this->sqlHaving = ($this->sqlHaving ? "(".$this->sqlHaving.") AND " : "").$col['sqlSearchCondition'];
                //$this->sqlFieldsAggregate .= ($this->sqlFieldsAggregate!='' ? "\r\n, " : '')
                //    .($col["sql"]!="" && $col["sql"]!=$col["field"] ? "(".$col["sql"].") AS " : "").$col["field"];

            } else {
               $this->sqlWhere = ($this->sqlWhere ? "(".$this->sqlWhere.") AND " : "").$col['sqlSearchCondition'];
            }

        }
      
    }
   
   // if an element not found in order_fields collection, we set orderBy field as PK
    if (!in_array($this->orderBy, $this->arrOrderByCols)){
        $this->orderBy = $this->sqlPK;
    }
    
    if ($this->flagExcel && count($_GET[$this->sqlPK])>0){ // if we pass some arguments for exact excel output
        $strExact = "";
        for ($i=0;$i<count($_GET[$this->sqlPK]);$i++){
            $strExact .= ($strExact!="" ? "," : "")." '".$_GET[$this->sqlPK][$i]."'";
        }
        $this->sqlWhere = $this->sqlPK." IN (".$strExact.")";
    }

    $this->strSQL = "FROM ".$this->sqlFrom.($this->sqlWhere!="" ? "
        WHERE ".$this->sqlWhere : "");

    $this->strSQLAggregate = "FROM ".$this->sqlFromAggregate.($this->sqlWhere!="" ? "
        WHERE ".$this->sqlWhere : "");

    if ($this->flagGroupBy){
        $this->strSQL .= "
            GROUP BY ".$this->sqlGroupBy;
        $this->strSQLAggregate .= "
            GROUP BY ".$this->sqlGroupBy;
    }
      
    $this->strSQL .= ($this->sqlHaving!="" ? "
        HAVING ".$this->sqlHaving : "");  
    $this->strSQLAggregate .= ($this->sqlHaving!="" ? "
        HAVING ".$this->sqlHaving : "");

    $this->strSQL = "SELECT\r\n".$this->sqlFields."
        ".$this->strSQL;
    $this->strSQLAggregate = "SELECT\r\n".$this->sqlFieldsAggregate."
        ".$this->strSQLAggregate;

    $this->strSQL .= "
        ORDER BY ".$this->orderBy." ".$this->sortOrder;

    // echo '<pre>'.$this->strSQL.'</pre>';
   
}

/**
 * This method returns SQL search expression for given column as string that looks like ```myColumn='my filter value'```.
 * In common case: ```<searchSubject> <searchOperator> <searchCriteria>```
 * 
 * ```<searchSubject>``` is column name or SQL expression that would be tested on match with supplied filter value.
 * 
 * ```<searchOperator>``` and ```<searchCriteria>``` are defined basing on column type, filter value and other factors.
 * 
 * For text, it searches for partial match by default (expression is ```myColumn LIKE '%my filter value%'```)
 * 
 * In case when column has 'exactMatch' property set to TRUE or filter value is encolsed into double or single quotes ("'" or """), it returns expression for direct match: ```myColumn='my filter value'```
 * 
 * For numeric values it allows to use comparison operators, like ```=```, ```,```, ```<```, ```>=``` or ```<=``` before the number in filter value.
 * Same is for date/datetime values. For these types it also allows logical "&" ("and")  operator.
 *
 * If filter value is empty, it returns empty string (only if ```exact match``` option is not set for this column).
 * If filter value matches matches isNullFilterValue from configuration we return ```<searchExpression> IS NULL```.
 *
 * @param array $col - column, a single member of eiseList::Columns property.
 *
 * @return string
 * 
 * @category List Data Handling
 * 
 */
private function getSearchCondition(&$col){
     
    $oSQL = $this->oSQL;

    $strCondition = '';

    // if filter value is empty and column is not 'exact match' filter
    if ( $col["filterValue"]=="" 
        && !($col['exactMatch'] && !$col['tabsFilter']) )
        return "";

    $col['searchExpression'] = ($col['filter'] == $col['field']
        ? ($col['sql'] ?  '('.$col['sql'].')' : $col['filter'])
        : $col['filter']
        );

    // if filter value matches isNullFilterValue from configuration we return '<searchExpression> IS NULL'
    if(strtoupper($col['filterValue']) === strtoupper($this->conf['isNullFilterValue'])){
        return "{$col['searchExpression']} IS NULL";
    }

    $strFlt = $col["filterValue"];

    if( preg_match("/^\={0,1}[\'\"']{2}$/", $strFlt, $arrMatchEmpty) ) { // e.g ='' or ="" or '' or ""
       return " {$col['searchExpression']} IS NULL".(in_array($col['type'], array('text', 'textarea')) ? "  OR {$col['searchExpression']}=''" : '');
    }
    if(in_array($strFlt, array('*', '%')))
        return $strCondition = "{$col['searchExpression']} IS NOT NULL".(in_array($col['type'], array('text', 'textarea')) ? "  AND {$col['searchExpression']}<>''" : '');   

    switch ($col['type']) {
        case "text":
        case "textarea":
            $col['exactMatch'] = ($col['exactMatch'] || $this->conf['exactMatch']);

            if ($col['exactMatch'] || preg_match("/^\={0,1}[\'\"'](.*)[\'\"']$/", $strFlt, $arrMatch)) {
                
                if($col['exactMatch']){

                    $strFlt = preg_replace('/[\*\%]+/', '%', $strFlt);
                    $strCondition = " {$col['searchExpression']} LIKE '".$oSQL->unq($oSQL->e($strFlt))."'";

                } else {

                    $strCondition = "( {$col['searchExpression']}=".$oSQL->e($arrMatch ? $arrMatch[1] : $strFlt) 
                        .($arrMatch && $arrMatch[1]==='' ? " OR {$col['searchExpression']} IS NULL" : '')
                        ." ) ";

                }
            
            } else {
                $prgList = "/\s*[,\:\|]\s*/";
                if (preg_match($prgList, $strFlt)){
                    $arrList = preg_split('/\s*[,\;\:\|]\s*/',$strFlt);
                    if( count($arrList) > 1)
                        $strCondition = " {$col['searchExpression']} IN ('".implode("', '", $arrList)."')";
                    else
                        $strCondition = " {$col['searchExpression']} LIKE ".$oSQL->escape_string($strFlt, "for_search");
                } elseif ($strFlt=='*' || $strFlt=='%'){
                    $strCondition = " {$col['searchExpression']} <> ''";
                } elseif ($strFlt=='='){
                    $strCondition = " {$col['searchExpression']} = '' OR {$col['searchExpression']} IS NULL";
                }else
                    $strCondition = " {$col['searchExpression']} LIKE ".$oSQL->escape_string($strFlt, "for_search");
            }
          break;
        case "numeric":
        case "integer":
        case "number":
        case "money":
        case "real":

            if (preg_match("/^([\<\>\=]{0,1})(\-){0,1}[0-9]+([\.][0-9]+){0,1}$/", $strFlt, $arrMatch)) {
                if ($arrMatch[1])
                    $strCondition = " ".$col['searchExpression'].$strFlt;
                else
                    $strCondition = " ".$col['searchExpression']." = ".$strFlt;
            } else
                 $strCondition = "";
            break;
        case 'combobox':
            $strCondition = " {$col['searchExpression']} = ".$oSQL->escape_string($strFlt)."";
            break;
        case 'ajax_dropdown':
            $strCondition = ($col['searchExpression']!=$col['field'] 
                    ? " {$col['searchExpression']} "
                    : ( $col['tableAlias'] && $col['textField']
                        ? " {$col['tableAlias']}.{$col['textField']}"
                        : " {$col['field']}_Text"
                        )
                    ).
                    " LIKE ".$oSQL->escape_string($strFlt, "for_search");
            break;
        case "date":
        case "datetime":
            
            $prgOperators = "(\|\|{0,1}|\&\&{0,1}|OR|AND){0,1}\s*(\>|\<|\=|\<\=|\>\=){0,1}\s*";

            foreach($this->conf['dateRegExs'] as $arrRex){

                $i = 0;
                $prgFull = '/'.$prgOperators.$arrRex['rex'].($col['type']=='datetime' 
                    ? '( '.$arrRex['rexTime'].'){0,1}'
                    : '')
                .'/';

                if (preg_match_all($prgFull, $strFlt, $arrMatch)) {

                    for ($i=0;$i<count($arrMatch[0]);$i++){
                        $cond = $i==0 ? ""
                            : (
                              (isset($arrMatch[1][$i]) && ($arrMatch[1][$i]=="&" || $arrMatch[1][$i]=="&&" || $arrMatch[1][$i]=="AND"))
                              ? "AND"
                              : "OR" );
                        $oper = (empty($arrMatch[2][$i]) ? "=" : $arrMatch[2][$i]);

                        $strCondition .= ($cond ? " ".$cond." " : "")."DATEDIFF(".$col['searchExpression'].", '".
                            (strlen($arrMatch[$arrRex['ixOf']['Y']+2][$i])==2 ? '20'.$arrMatch[$arrRex['ixOf']['Y']+2][$i] : $arrMatch[$arrRex['ixOf']['Y']+2][$i])
                            ."-".$arrMatch[$arrRex['ixOf']['m']+2][$i]
                            ."-".$arrMatch[$arrRex['ixOf']['d']+2][$i]
                            .(isset($arrMatch[6][$i]) ? $arrMatch[6][$i] : "") // time
                            ."')".$oper."0";
                    }
                }

                $strCondition = ($i>1 ? "(".$strCondition.")" : $strCondition);

            }
            
            break;
        case 'boolean':
            if($col['sql']!=''){
                $strCondition = ($strFlt==="0" ? "NOT ({$col['sql']})" : "{$col['sql']}");   
            }
            else 
                $strCondition = " ".$col['searchExpression']." = ".(int)($strFlt);
            break;
        default:
            $strCondition = " ".$col['searchExpression']." = ".$this->oSQL->escape_string($strFlt);
            break;
    }

    return $strCondition;

}

private function cacheColumns(){
    
    $_SESSION[$this->name."_columns"]=$this->Columns;
    
}
private function cacheSQL(){
    
    $_SESSION[$this->name."_sql"]=$this->strSQL;
    $_SESSION[$this->name."_sqlAggregate"]=$this->strSQLAggregate;
    $_SESSION[$this->name."_sqlPK"]=$this->sqlPK;
    
}

private function getCachedColumns(){
    
    $cols = (isset($_SESSION[$this->name."_columns"]) ? $_SESSION[$this->name."_columns"] : array());
    if(count((array)$cols)){
        $this->Columns = $cols;
    }
    
}

private function getCachedSQL(){
    
    $this->strSQL = $_SESSION[$this->name."_sql"];
    $this->strSQLAggregate = $_SESSION[$this->name."_sqlAggregate"];
    $this->sqlPK = $_SESSION[$this->name."_sqlPK"];
    
}

private function getRowArray($index, $rw){
    
    $arrRet = Array();
    $arrFields = Array();
    
    $iColCounter = 0;
    foreach($this->Columns as $i => $col){
        
        $arrField = Array();
        
        $valFormatted = "";
        $val = (isset($col['field']) && isset($rw[$col['field']])) ? $rw[$col['field']] : null;
        $class = "";
        $href = "";

        $col = array_merge(self::$col_default, $col);
        
        /* obtain calculated values for class and href */
        foreach($rw as $rowKey=>$rowValue){
            $col['class'] = str_replace("[{$rowKey}]", $rowValue, $col['class']);
            $col['href'] = str_replace("[{$rowKey}]", ($col['nourlencode'] ? $rowValue : rawurlencode($rowValue)), $col['href']) ;
        }
        
        if($col['class'])
            $arrField["c"] = $col['class'];
            
        if($col['href'])
            $arrField["h"] = (empty($val) ? "" : $col['href']);
        
        if (isset($col["field"]) && $col["field"]=="phpLNums")
            $val = ($index+1).".";
        
        /* formatting data */
        $valFormatted = $this->formatData($col, $val, $rw);
        
        $arrField["t"] = ($valFormatted!="" ? $valFormatted : $val); // we will always display text in here
        if (in_array($col["type"], Array("combobox", "ajax_dropdown")))
            $arrField["v"] = $val;

        $arrField['type'] = $col["type"];
            
        $arrFields[isset($col['field']) ? $col['field'] : ''] = $arrField;
        
    }
    
    $arrRet["PK"] = $rw[$this->sqlPK];
    if (isset($rw['__rowClass']) && $rw['__rowClass'] != "") {
        $arrRet["c"] = $rw['__rowClass'];
    }
    $arrRet['r'] = $arrFields;
    
    return $arrRet;
}

private function formatData($col, $val, $rw){
    switch (isset($col['type']) ? $col['type'] : 'text') {
            case "date":
                $val = $this->DateSQL2PHP($val, $this->conf['dateFormat']);
                break;
            case "datetime":
                $val = $this->DateSQL2PHP($val, $this->conf['dateFormat']." ".$this->conf['timeFormat']);
                break;
            case "time":
                $val = $this->DateSQL2PHP($val, $this->conf['timeFormat']);
                break;
                
            case "numeric":
            case "integer":
            case "number":
            case "money":
            case "float":
            case "double":
                $decimalPlaces = (in_array($col['type'], Array("numeric", "integer", "number"))
                    ? 0
                    : (isset($col['decimalPlaces']) ? $col['decimalPlaces'] : $this->conf['decimalPlaces'])
                    );
                $val = round((float)$val, (int)$decimalPlaces);
                $val = number_format((float)$val, (int)$decimalPlaces
                        , (isset($col['decimalSeparator']) ? $col['decimalSeparator'] : $this->conf['decimalSeparator'])
                        , (isset($col['thousandsSeparator']) ? $col['thousandsSeparator'] : $this->conf['thousandsSeparator']) );
                break;
            case "boolean":
                $val = (int)$val;
                break;
            case "combobox":         // return Text representation for FK-based columns
            case "ajax_dropdown":
                $val = (isset($rw[$col['field']."_Text"]) && $rw[$col['field']."_Text"]!=""
                    ? $rw[$col['field']."_Text"]
                    : (is_array($col['source']) 
                        ? (!empty($col['source'][$val]) ? $col['source'][$val] : $val)
                        : $val)
                    );
                break;
            case "text":
            default:
                mb_internal_encoding("UTF-8");
                $limitOutput = (isset($col["limitOutput"]) ? $col["limitOutput"] : 0);
                $val = ($limitOutput > 0 && mb_strlen($rw[$col['field']]) > $limitOutput) 
                    ? mb_substr($rw[$col['field']], 0, $limitOutput)."..." 
                    : $val;
                break;
        }
        return $val;
}

private function dateSQL2PHP($dtVar, $datFmt="d.m.Y H:i"){
    $result =  $dtVar ? date($datFmt, strtotime($dtVar)) : "";
    return($result);
}

}

/**
 * phpLister class extends eiseList class to provide backward compatibility with phpLister library.
 * It has the same constructor as phpLister class had and an Execute method that sets main SQL parameters.
 * 
 * @category List Backward Compatibility
 */
class phpLister extends eiseList{
    function __construct($name){
        GLOBAL $oSQL;
        parent::__construct($oSQL, $name);
    }
    function Execute($oSQL, $sqlFrom, $sqlWhere="", $strDefaultOrderBy="", $strDefaultSortOrder="ASC", $iMaxRows=20, $openInExcel=false){
        $this->sqlFrom = $sqlFrom;
        $this->sqlWhere = $sqlWhere;
        $this->defaultOrderBy = $strDefaultOrderBy;
        $this->defaultSortOrder = $strDefaultSortOrder;
    }
}

?>