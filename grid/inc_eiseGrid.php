<?php
/**
 * # eiseGrid
 * 
 * **eiseGrid** PHP class is the backend for eiseGrid library that displays data grid, handles client side operations (data input, calculation and validation) and data update on the server side.
 * Grid is configured on PHP side and rendered as HTML+JavaScript. Data is submitted to server as form data POST and can be obtained via JSON or directly  posted into database.
 * 
 * eiseGrid integrates tightly with eiseIntra framework, but can be used as standalone component as well.
 * 
 * @package eiseIntra
 * @subpackage eiseGrid
 *
 * @author Ilya Eliseev (ie@e-ise.com)
 * @copyright (c) 2006-2025 Ilya S. Eliseev
 *
 * @license MIT License
 *
 * @version 2.5beta
 */


/**
 * eiseGrid class
 *
 * This class is responsible for managing the data grid, including
 * rendering, data manipulation, and interaction with the backend.
 */
class eiseGrid {

/**
 * Default widths for column types
 * 
 * @category Grid Configuration
 */
static $defaultWidthsByType = array(
        'numeric' => '60px'
        , 'number' => '60px'
        , 'integer' => '60px'
        , 'real' => '80px'
        , 'money' => '80px'

        , 'date' => '90px'
        , 'datetime' => '120px'
        , 'time' => '40px'

        , 'boolean' => '30px'
        , 'checkbox' => '30px'
        
        , 'order' => '25px'

        , 'text' => '100px'
        , 'combobox' => '100px'
        , 'select' => '100px'
    );

/**
 * Default config of eiseGrid
 * 
 * @category Grid Configuration
 */
static $defaultConf = Array(                    
        'titleDel' => "Del" // column title for Del
        , "titleAdd" => "Add >>" // column title for Add
        , 'controlBarButtons' => ''
        //, 'controlBarButtons' => 'add|insert|moveup|movedown|delete|excel|save'
        , 'extraInputs' => Array("DataAction"=>"update")
        , 'urlToSubmit' => ''
        , 'dateFormat' => "d.m.Y"
        , 'timeFormat' => "H:i" 
        , 'decimalPlaces' => "2"
        , 'decimalSeparator' => "."
        , 'thousandsSeparator' => ","
        , 'totalsTitle' => 'Totals'
        , 'noRowsTitle' => 'Nothing found'
        , 'spinnerTitle' => 'Loading...'
        , 'dropHereTitle' => 'Drop it here'
        , 'arrPermissions' => Array("FlagWrite" => true)
        , 'Tabs3DCookieName' => '%s_tabs3d'

        , 'class' => ''

        , 'eiseIntraRelativePath' => eiseIntraRelativePath

        , 'excelSheetName' => 'Sheet 1'
        , 'excelFileName' => 'table.xls'

        , 'colors' => array('#d79695', '#bd5050', '#fdc138', '#feff48', '#94d05e', '#928958', '#26afec', '#588cd0', '#b2a1c5', '#95cddb')

        , 'intra' => null // intra object, if not set, will be taken from GLOBALS

    );

/** 
 * This array defines default properties of each column.
 * 
 * @category Grid Configuration
 * 
*/
public $col_default = array(
    'field' => '', // field name, must be set
    'fields' => null, // array of fields, if set, will be used for colspan purposes
    'title' => '', // column title
    'type' => 'text', // column type
    'style' => '', // column style
    'class' => '', // column class
    'mandatory' => false, // is column mandatory
    'sortable' => false, // is column sortable
    'filterable' => false, // is column filterable
    'headerClickable' => false, // is column header clickable
    'readonly' => false, // is column read-only
    'disabled' => false, // is column disabled
    'static' => false, // is column static, i.e. not editable
    'default' => null, // default value for the field

    'flagDontUpdateRow' => false, // if true, the row will not be updated on change of this field

    'totals' => null, // totals for the column, can be 'sum', 'count', 'avg', 'min', 'max'

    'decimalPlaces' => null, // number of decimal places for the column, if applicable

    'source' => null, // source for the column, can be array of options, SQL query or string with options
    'source_prefix' => null, // prefix for the source, if applicable

    'href' => null, // link for the column, if applicable
    'target' => null, // target for the link, if applicable

    'format' => null, // date-time format for the column, if applicable
);


/**
 * array of columns. can be associative or indexed.
 * 
 * @category Grid Configuration
 */
public $Columns = array();

public $visibleColumns = array(); // array of visible columns, indexed by field name

public $headerColumns = array(); // array of header columns, indexed by field name

public $hiddenInputs = array(),
    $permissions = array(),
    $arrWidth = array(),
    $Tabs3D = array(),
    $arrSpans = array(), // array of spans for the header columns, indexed by field name
    $newData_transposed = array(); // array of transposed data for updates

/**
 * Configuration of eiseGrid instance (see [eiseGrid::$defaultConf](#eisegrid-defaultconf) for possible settings)
 * 
 * @category Grid Configuration
 */
public $conf = array();

public $intra, $oSQL;

public $name = ''; // name of the grid, used for HTML id and class attributes

// Associative array of fields. Key is field name, value is array similar to $Columns
protected $__fields = array(); 

// Rowspan. Default 1. To be updated on initialization, after field number calculation for each column
protected $__rowspan = 1; 


/**
 * array of rows. each row is accociative array of fieldName=>fieldValue
 * 
 * @category Grid Data
 */
public $Rows = array();

/**
 * Grid constructor. 
 * 
 * @param object $oSQL - eiseSQL object
 * @param string $strName - grid name
 * @param array $arrConfig - grid configuration overrides. See description of static property [eiseGrid::$defaultConf](#eisegrid-defaultconf).
 * 
 * @category Grid Configuration
 */
function __construct($oSQL
    , $strName
    , $arrConfig = array()
    ){
    
    GLOBAL $intra;

    foreach(array('dateFormat', 'timeFormat', 'decimalSeparator', 'thousandsSeparator') as $f)
        $arrConfig[$f] = (isset($arrConfig[$f]) 
            ? $arrConfig[$f] 
            : ($intra && isset($intra->conf[$f]) && $intra->conf[$f] 
                ? $intra->conf[$f] 
                : self::$defaultConf[$f]
                )
            );

    $arrConfig['urlToSubmit'] = (isset($arrConfig['urlToSubmit']) ? $arrConfig['urlToSubmit'] : $_SERVER["PHP_SELF"]);
    $arrConfig['excelFileName'] = (isset($arrConfig['excelFileName']) ? $arrConfig['excelFileName'] : pathinfo($_SERVER["PHP_SELF"], PATHINFO_FILENAME).'.xls');
    $arrConfig['Tabs3DCookieName_src'] = isset($arrConfig['Tabs3DCookieName']) ? $arrConfig['Tabs3DCookieName'] : self::$defaultConf['Tabs3DCookieName'];
    $arrConfig['Tabs3DCookieName'] = sprintf(isset($arrConfig['Tabs3DCookieName']) ? $arrConfig['Tabs3DCookieName'] : self::$defaultConf['Tabs3DCookieName'], $strName);

    $this->oSQL = $oSQL;

    $this->conf = array_merge(self::$defaultConf, $arrConfig);

    $this->name = $strName;
    $this->permissions = (isset($arrConfig["arrPermissions"]) 
        ? (is_array($arrConfig["arrPermissions"]) ? $arrConfig["arrPermissions"] : array())
        : ( isset($intra->arrUsrData['FlagWrite'])
            ? array('FlagWrite'=>$intra->arrUsrData['FlagWrite'], 
                'FlagDelete'=>$intra->arrUsrData['FlagWrite'],
                )
            : (is_array(self::$defaultConf['arrPermissions']) ? self::$defaultConf['arrPermissions'] : array())) 
        );
    $this->intra = ($this->conf['intra'] ? $this->conf['intra'] : $intra);
    if($this->conf['intra'])
        unset($this->conf['intra']);

    //backward-compatibility staff
    if (is_array($this->permissions)) {
        $this->permissions["FlagWrite"] = (isset($this->conf['flagDisabled']) ? !$this->conf['flagDisabled'] : (isset($this->permissions["FlagWrite"]) ? $this->permissions["FlagWrite"] : true));
        $this->permissions["FlagDelete"] = (isset($this->conf['flagNoDelete']) 
            ? !$this->conf['flagNoDelete'] 
            : (isset($this->permissions["FlagDelete"])
                ? $this->permissions["FlagDelete"]
                : (isset($this->permissions["FlagWrite"]) ? $this->permissions["FlagWrite"] : true))
        );
    } else {
        // Initialize with default values if permissions is not an array
        $this->permissions = array(
            "FlagWrite" => !(isset($this->conf['flagDisabled']) ? $this->conf['flagDisabled'] : false),
            "FlagDelete" => !(isset($this->conf['flagNoDelete']) ? $this->conf['flagNoDelete'] : false)
        );
    }
}

/**
 * This method renames Grid: it sets $grid->name and other attributes.
 *
 * @param string $newName - new grid name
 *
 * @return string - old name
 * 
 * @category Grid Configuration
 */
function rename($newName){

    $oldName = $this->name;
    $this->name = $newName;
    $this->conf['Tabs3DCookieName'] = sprintf($this->conf['Tabs3DCookieName_src'], $newName);
    
    return $oldName;

}

/**
 * This function adds columns to $Columns property. 
 * 
 * @param $arrCol - associative array with column properties. See description of $Columns property.
 * @param $arrCol['field'] - is mandatory
 * @param $arrCol['fieldInsertBefore'] - field name to insert before
 * @param $arrCol['fieldInsertAfter'] - field name to insert after
 *
 * @example     $gridJCN->addColumn(Array(
 *            'title' => "QQ"
 *            , 'field' => "jcnQQ"
 *            , 'fieldInsertBefore'=>'jcnContainerSize'
 *            , 'type' => "integer"
 *            , 'totals' => "sum"
 *            , 'width' => '30px'
 *    ));
 * 
 * @category Grid Configuration
 *
 */
function addColumn($arrCol){

    if(!$arrCol['field'])
        throw new Exception("No field specified");
        

    if((isset($arrCol['fieldInsertBefore']) && $arrCol['fieldInsertBefore']) || (isset($arrCol['fieldInsertAfter']) && $arrCol['fieldInsertAfter'])){

        $Columns_new = array();
        $flagInserted = false;

        foreach($this->Columns as $ix=>$col){

            if(isset($arrCol['fieldInsertBefore']) && $col['field']==$arrCol['fieldInsertBefore']){
                $Columns_new[$arrCol['field']] = $arrCol;
                $flagInserted = true;
            }

            $Columns_new[$col['field']] = $col;

            if(isset($arrCol['fieldInsertAfter']) && $col['field']==$arrCol['fieldInsertAfter']){
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
 * This function removes columns from $Columns list. 
 * 
 * @param $field - field name to be removed.
 *
 * @example     $gridJCN->removeColumn('qq');
 * 
 * @category Grid Configuration
 *
 */
function removeColumn($field){

    $Columns_new = array();
    
    foreach($this->Columns as $ix=>$col){

        if($col['field'] != $field){
            $Columns_new[] = $col;
        }

    }

    $this->Columns = $Columns_new;

}

/**
 * This function changes column property to defined values and returns its previous value.
 * 
 * @param string $field - field name
 * @param string $property - property name
 * @param mixed $value - new property value
 *
 * @return mixed - old property value
 * 
 * @category Grid Configuration
 */
function setColumnProperty($field, $property, $value){
    $retVal = null;
    foreach($this->Columns as &$col){
        if($col['field']==$field){
            $retVal = isset($col[$property]) ? $col[$property] : null;
            $col[$property] = $value;
            break;
        }
    }
    return $retVal;
}

/**
 * This function does main job: it generates HTML for the whole eiseGrid.
 *
 * @category Grid Display
 */
function get_html($allowEdit=true){

    GLOBAL $_DEBUG;
    GLOBAL $strLocal;

    $intra = $this->intra;
      
    $strRet = '<div class="eiseGrid'.($this->conf['class'] ? ' '.$this->conf['class'] : '').'" id="'.$this->name.'" data-config="##GRID_CONFIG##">'."\r\n";
    
    if (!$allowEdit)
        $this->permissions["FlagWrite"] = false;

    foreach ($this->Columns as $col) {
        $col = array_merge($this->col_default, $col);
        if($col['title'] && $col['filterable']===true){
            if(strpos($this->conf['controlBarButtons'], 'filter')===false)
                $this->conf['controlBarButtons'] .= ($this->conf['controlBarButtons'] ? '|' : '').'filter';
        }
        if(!$col['title'] && $col['type']=='color'){
            if(strpos($this->conf['controlBarButtons'], 'palette')===false)
                $this->conf['controlBarButtons'] .= ($this->conf['controlBarButtons'] ? '|' : '').'palette';
        }
    }
    
    $aControlBarButtons = explode('|', $this->conf['controlBarButtons']);
    if (($this->permissions["FlagWrite"]  && count($aControlBarButtons)>0) || count(array_intersect(array('excel', 'refresh', 'filter'), $aControlBarButtons))>0 ){

        $strControlBar = "<div class=\"eg-controlbar\">";
        
        foreach ($aControlBarButtons as $btn){
            if($btn)
                $strControlBar .= "<button class=\"eg-button eg-button-{$btn}\" type=\"button\"><i></i></button>";
        }
        
        $strControlBar .= "</div>";
        
        $strRet .= $strControlBar;
        
    }
    
    $htmlTabs = '';
    if(!empty($this->Tabs3D)){
        $htmlTabs .= "<div id=\"{$this->name}-tabs3d\">\r\n";
        $htmlTabs .= "<ul>\r\n";
        foreach($this->Tabs3D as $ix=>$tab){
            $htmlTabs .= "<li><a href=\"#{$this->name}-tabs3d-{$tab['ID']}\">{$tab['title']}</a></li>\r\n"; 
        }
        $htmlTabs .= "</ul>\r\n";
        foreach($this->Tabs3D as $ix=>$tab){
            $htmlTabs .= "<div id=\"{$this->name}-tabs3d-{$tab['ID']}\" class=\"eg-pseudotabs\"></div>\r\n"; 
        }
        $htmlTabs .= "</div>\r\n";
    }

    $strHead = "<tr>\r\n";

    $this->visibleColumns = Array();
    $this->hiddenInputs = Array();
    
    $this->headerColumns = array();
    $this->arrSpans = array();

    $strCols = '';

    $nColNumber = 0;

    $spannedColumns = array();

    foreach ($this->Columns  as $ix=>$col){

        $col = array_merge($this->col_default, $col);
        
        if ((int)$this->permissions["FlagWrite"]==0){
            $this->Columns[$ix]['static'] = true;
        }
        if ($col['class']){
            $this->Columns[$ix]['staticClass'] = ' '.preg_replace("/\[.+?\]/", "", $col['class']);
        } else {
            $this->Columns[$ix]['staticClass'] = '';
        }
        
        if ($col["title"]){

            $this->Columns[$ix]['style'] = $col['style'];

            if(is_array($col['fields'])){
                foreach ($col['fields'] as $fldName => $fld) {
                    if(!$fld['field'])
                        continue;
                    $this->__fields[$fld['field']] = $fld;
            //        $this->__fields[$fld['field']]['column'] = &$this->Columns[$ix];
                    $this->Columns[$ix]['fields'][] = &$this->__fields[$fld['field']];
                }
                $this->__rowspan = (count($col['fields'])>$this->__rowspan ? count($col['fields']) : $this->__rowspan);
            } else {
                $this->__fields[$col['field']] = $col;
                $this->Columns[$ix]['fields'][0] = &$this->__fields[$col['field']];
            }

            $key = $this->Columns[$ix]['fields'][0]['field']; // key is the is the field name of the first field
            
            $spannedColumns[] = $this->Columns[$ix];                
            
            $keys = array_keys($this->Columns);
            $pos = array_search($ix, $keys, true);

            $nextPos = $pos !== false ? $pos + 1 : null;

            $hasNext = ($nextPos !== null && isset($keys[$nextPos]));

            $nextCol = $hasNext ? $this->Columns[$keys[$nextPos]] : null;

            $nextTitle = (isset($nextCol['title']) && $nextCol['title']) ? $nextCol['title'] : '';

            if (($hasNext && $nextTitle != $col['title'])|| !$hasNext) {
                
                $strHead .= "\t<th".
                            " data-field=\"{$col['field']}\"".
                            ($col["style"]!="" 
                            ? " style=\"{$col['style']}\""
                            : "").
                        " class=\"{$this->name}-".(isset($spannedColumns[0]['fields'][0]['field']) ? $spannedColumns[0]['fields'][0]['field'] : "")
                            .($col['mandatory'] 
                                ? " eg-mandatory" 
                                : "")
                            .($col['headerClickable'] 
                                ? " eg-clickable" 
                                : "")
                            .($col['sortable'] 
                                ? " eg-sortable" 
                                : "")
                            .($col['filterable'] 
                                ? " eg-filterable" 
                                : "").$this->Columns[$ix]['staticClass']."\"";
                       
                if (count($spannedColumns)>1) {
                    $this->arrSpans[(isset($spannedColumns[0]['field']) ? $spannedColumns[0]['field'] : '')]=count($spannedColumns);
                    $strHead .= ' colspan="'.count($spannedColumns).'"';
                }

                $strHead .=  '>'
                        .($nColNumber==0 && $this->permissions["FlagWrite"] && !($this->permissions["FlagDelete"]===false)
                            ? '<input type="hidden" id="inp_'.$this->name.'_deleted" name="inp_'.$this->name.'_deleted" value="">'
                            : '')
                        .'<span>'.htmlspecialchars(isset($col["title"]) ? $col["title"] : '').'</span>'
                        ."</th>\r\n";

                $this->headerColumns[$col['field']] = array_merge($col, array('spannedColumns'=>$spannedColumns));
                $spannedColumns = array();
            }

            $this->visibleColumns[$key] = &$this->Columns[$ix];

            if (isset($col['width'])){
                $this->arrWidth[$key] = $col['width'].(preg_match('/^[0-9]+$/', $col['width']) ? 'px' : '');
            } else {
                $maxW = 0;
                foreach($this->Columns[$ix]['fields'] as $fldName=>$fld){
                    $w = (isset($fld['width']) && $fld['width']
                            ? $fld['width'].(preg_match('/^[0-9]+$/', $fld['width']) ? 'px' : '')
                            : ( (isset($fld['type']) && isset(self::$defaultWidthsByType[$fld['type']])) ? self::$defaultWidthsByType[$fld['type']] : self::$defaultWidthsByType['text'] )
                        );
                    if($w){
                        $this->arrWidth[$key] = $w;
                        break;
                    }
                }
                if(!isset($this->arrWidth[$key]) || !$this->arrWidth[$key]) 
                    $this->arrWidth[$key] = self::$defaultWidthsByType['text'] ;
            }

            $strCols .= "<col class=\"{$this->name}-{$key}\" data-field=\"{$key}\" style=\"width:{$this->arrWidth[$key]}\">\n";

            $nColNumber++;
            
        } else {
            $this->__fields[$col['field']] = $col;
            if ($col['type']!='row_id')
                $this->hiddenInputs[$col["field"]] = &$this->Columns[$ix];
        }
        
        if ($col['type']=='row_id') {
            $inpRowID = $col;
        }
    }

    $strRet .= "<table class=\"eg-table eg-container".($this->__rowspan > 1 ? ' multiple-lines' : ' single-line')."\">\r\n";

    foreach($this->__fields as &$fld){
        switch($fld['type']){
            case "select":
            case "combobox":
            case "ajax_dropdown":

                $fld['source'] = self::confVariations($fld, array('options', 'source', 'arrValues', 'sql'));
                $fld['source_prefix'] = self::confVariations($fld, array('source_prefix', 'prefix'));

                if($fld['type']=='ajax_dropdown')
                    break;

                $flagIsSQL = false;
                $ds = null;
                if(is_array($fld['source'])){
                    $ds = $fld['source'];
                } elseif (is_string($fld['source'])) {
                    $decoded_json = json_decode($fld['source'], true);

                    if (is_array($decoded_json)) {
                        $ds = $decoded_json;
                    } else {
                        if (preg_match('/^select\s+/i', $fld['source'])) {
                            $ds = $fld['source'];
                            $flagIsSQL = true;
                        } else {
                            $parts = explode('|', $fld['source'], 2);
                            $ds = $parts[0];
                        if (!isset($fld['source_prefix'])) {
                            $fld['source_prefix'] = isset($parts[1]) ? $parts[1] : null;
                        }                        }
                    }
                }

                $fld['source'] = ($ds ? $ds : array());
                $opts = array();

                if (is_array($fld['source'])){
                    $opts = $fld['source'];
                } else {
                    $oSQL = $this->oSQL;
                    if ($flagIsSQL){
                        $rs = $oSQL->do_query($fld['source']);
                    } else 
                        if ($fld['source']){
                            $parts = explode('|', $fld['source_prefix']);
                            $prefix = isset($parts[0]) ? $parts[0] : null;
                            $extra = isset($parts[1]) ? $parts[1] : null;
                            $rs = $this->getDataFromCommonViews($oSQL, "", "", $fld['source'], $prefix, 0, $extra);
                        }
                    if (is_resource($rs) // for mysql_query() function
                        || is_object($rs) // for mysqli::query() function
                        )
                        while ($rw = $oSQL->fetch_array($rs)){
                            if($rw['optValue']!='')
                                $opts[(string)$rw['optValue']] = $rw['optText'];
                        }  

                }

                $fld['source'] = $opts;  

                break;
            default:
                break;
        }
    }

    $strHead .= "</tr>\r\n";

    $strRet .= '<colgroup>'.$strCols.'</colgroup>'."\r\n";

    $strRet .= '<thead>'
        .($htmlTabs ? '<tr class="eg_tabs"><td colspan="'.count($this->visibleColumns).'">'.$htmlTabs.'</td></tr>' : '')
        .$strHead
        ."</thead>\r\n";

    $this->hiddenInputs = array_merge(
        (isset($inpRowID) ? Array($inpRowID['field'] => $inpRowID) : Array())
        , Array("inp_{$this->name}_updated" => Array(
                'field' => "inp_{$this->name}_updated"
            ))
        , $this->hiddenInputs
    );    
    // no rows and spinner rows
    $strRet .= "<tbody class=\"eg-no-rows\"><tr><td colspan=\"".count($this->visibleColumns)."\">{$this->conf['noRowsTitle']}</td></tr></tbody>\r\n";
    $strRet .= "<tbody class=\"eg-spinner\"><tr><td colspan=\"".count($this->visibleColumns)."\">{$this->conf['spinnerTitle']}</td></tr></tbody>\r\n";
    $strRet .= "<tbody class=\"eg-drop-here\"><tr><td colspan=\"".count($this->visibleColumns)."\">{$this->conf['dropHereTitle']}</td></tr></tbody>\r\n";
    

    // template row
    $strRet .= $this->__getRow(null);

    foreach((array)$this->Rows  as $iRow=>$row){
        $strRet .= $this->__getRow($iRow, $row);
    }

    /*
    // template row
    $strRet .= "<tr class=\"eg_template\">\r\n";
    $iCol = 0;

    foreach($this->visibleColumns as $field=>$col){
        $strRet .= $this->paintCell($col, $iCol, null);
        $iCol++;
    }
    $strRet .= "</tr>\r\n";
    
    //other rows
    if (count($this->Rows)>0)
        foreach($this->Rows as $iRow=>$row){
            $iCol = 0;
            $strRet .= "<tr class=\"eg_data".($row['__rowClass'] ? ' '.$row['__rowClass'] : '')."\">";
            foreach($this->visibleColumns as $field=>$col){
                $strRet .= $this->paintCell($col, $iCol, $iRow);
                $iCol++;
            }
            $strRet .= "</tr>\r\n";
        }
    */

    //if there's any totals
    $strFooter = "<tfoot>";
    $strFooter .= "<tr>";
    
    $iColspan = 0;
    $iTotalsCol = 0;
    foreach($this->visibleColumns as $field => $col){
        $col = array_merge($this->col_default, $col);
        if ($col['totals']){
            if ($iColspan>0){
                $strFooter .= "\t<td class=\"eg-totals-caption\"".($iColspan>1 ? " colspan=\"{$iColspan}\"" : "").">".
                    ($iTotalsCol==0 ? $this->conf['totalsTitle'].":" : "")."</td>\r\n";
                
            }
            $colClass = ($col['class'] ? ' '.preg_replace('/(\s+\[\S+\]\s+)/i', '', $col['class']) : '');
            $strFooter .= "<td class=\"{$this->name}-{$field} eg-{$col['type']}{$colClass}\"><div></div></td>";
            $iTotalsCol++;
            $iColspan = 0;
            continue;
        }
        $iColspan++;
    }
    
    if ($iColspan>0){
       $strFooter .= "\t<td class=\"eg_totals_caption\"".($iColspan>1 ? " colspan=\"{$iColspan}\"" : "")."></td>\r\n"; 
    }
    
    $strFooter .= "</tr>";
    $strFooter .= "</tfoot>";
    
    if ($iTotalsCol!=0){
        $strRet .= $strFooter;
    }
    
    $strRet .= "</table>\r\n";
    
    $arrConfig = $this->conf;
    foreach($this->__fields as $fieldName=>$field){
        $arrConfig['fieldIndex'][] = $fieldName;
        $arrConfig['fields'][$fieldName] = Array('type'=>$field['type'], 'title'=>$field['title']);
        if (isset($field['mandatory']) && $field['mandatory']){
            $arrConfig['fields'][$fieldName]['mandatory'] = $field['mandatory'];
        }
        if (isset($field['href']) && $field['href']){
            $arrConfig['fields'][$fieldName]['href'] = $field['href'];
            if (isset($field['target']) && $field['target']){
                $arrConfig['fields'][$fieldName]['target'] = $field['target'];
            }
        }
        if (isset($field['totals']) && $field['totals']){
            $arrConfig['fields'][$fieldName]['totals'] = $field['totals'];
        }
        if (isset($field['decimalPlaces']) && $field['decimalPlaces']){
            $arrConfig['fields'][$fieldName]['decimalPlaces'] = $field['decimalPlaces'];
        }
        if (isset($field['static']) && $field['static']===true){
            $arrConfig['fields'][$fieldName]['static'] = true;
        }
        if (isset($field['disabled']) && $field['disabled']===true){
            $arrConfig['fields'][$fieldName]['disabled'] = true;
        }
        if (isset($field['source']) && is_array($field['source'])){
            $arrConfig['fields'][$fieldName]['source'] = $field['source'];
        }
        
        if (isset($field['headerClickable']) && $field['headerClickable']){
            $arrConfig['fields'][$fieldName]['headerClickable'] = true;
        }
        if (isset($field['flagDontUpdateRow']) && $field['flagDontUpdateRow']){
            $arrConfig['fields'][$fieldName]['flagDontUpdateRow'] = true;
        }
        if (isset($field['sortable']) && $field['sortable']===true){
            $arrConfig['fields'][$fieldName]['sortable'] = true;
        }
        if (isset($field['filterable']) && $field['filterable']===true){
            $arrConfig['fields'][$fieldName]['filterable'] = true;
        }
    }

    $jsonConfig = json_encode(array_merge($arrConfig, array('widths'=>$this->arrWidth
        , 'spans' => $this->arrSpans)
    ));

    $strRet = str_replace('##GRID_CONFIG##', htmlspecialchars($jsonConfig), $strRet);

    #$strRet .= "<input type=\"hidden\" id=\"inp_".$this->name."_config\" value=\"".htmlspecialchars($jsonConfig)."\">";
    
    $strRet .= "</div>\r\n";
    
    return $strRet;

}

/**
 * This function echoes eiseGrid HTML
 * 
 * @category Grid Display
 * @category Grid Backward Compatibility
 */
function Execute($allowEdit=true) {
    
    echo $this->get_html($allowEdit);
    
}

/**
 * @ignore
 */  
protected function __getRow($iRow, $row = null){

    $html = '<tbody class="'.(
            $iRow===null
            ? 'eg-template'
            : 'eg-data'.(isset($row['__rowClass']) ? ' '.$row['__rowClass'] : '').(
                (isset($row['__rowDisabled']) ? $row['__rowDisabled'] : '')
                ? ' eg-row-disabled'
                : ''
                )
            ).'">'."\r\n";

    for ($iSubRow = 0; $iSubRow < $this->__rowspan; $iSubRow++){

        $html .= '<tr>'."\r\n";

        foreach($this->visibleColumns as $ixCol=>$col){
            if($row && isset($row['__rowDisabled']) && $row['__rowDisabled'])
                $col['fields'][$iSubRow]['disabled'] = true;
            $html .= (isset($col['fields'][$iSubRow]) && $col['fields'][$iSubRow]
                ? $this->__paintCell($col['fields'][$iSubRow], $ixCol, $iRow)
                : '<td>&nbsp;</td>'."\r\n"
                );
        }

        $html .= '</tr>'."\r\n";
    }

    $html .= '</tbody>'."\r\n";

    return $html;

}

/**
 * @ignore
 */
protected function __paintCell($col, $ixCol, $ixRow, $rowID=""){
    
    $field = ($col['type']=="del" ? "del" : $col["field"]);
    $row = ($ixRow!==null && isset($this->Rows[$ixRow]) ? $this->Rows[$ixRow] : array());
    
    $row[$field] = $val = ($ixRow===null 
        ? (isset($col['default']) ? $col['default'] : null)
        : (isset($row[$field]) ? $row[$field] : null)
    );

    $col = array_merge($this->col_default, $col);

    $cell = $col;
    
    $arrSuffix = array();
    if (!empty($this->Tabs3D) && ($ixRow===null || is_array($val))) {
        foreach($this->Tabs3D as $ix=>$tab){
            $arrSuffix[] = $tab['ID'];
        }
    } else {
        $arrSuffix = array(0);
    }

    if(!$this->permissions['FlagWrite'])
        $cell['static'] = True;

    
    if ($ixRow===null){ //for template row: all calcualted class are grounded, static/disabled set to 0, href grounded
        $cell['class'] = trim( $this->visibleColumns[$ixCol]['staticClass'] );
        $cell['static'] = (is_string($cell['static']) ? 0 : $cell['static']);
        $cell['readonly'] = (is_string($cell['readonly']) ? 0 : $cell['readonly']);
        $cell['disabled'] = (is_string($cell['disabled']) ? 0 : $cell['disabled']) ;
        $cell['href'] = "" ;
    } else // calculate row-dependent options: class, static/disabled, or href 
        foreach(array('class', 'static', 'readonly', 'source', 'extra', 'placeholder', 'href', 'disabled') as $prop){
            foreach($this->Rows[$ixRow] as $rowKey=>$rowValue){
                if(!isset($cell[$prop]) || is_array($rowValue) || is_object($rowValue))
                    continue;

                if($prop=='href'){
                    $cell['href'] = (strpos($cell['href'], "[{$rowKey}]")!==false // if argument exists in HRef
                        ? ($val==''||$rowValue==''
                            ? '' 
                            : str_replace("[{$rowKey}]"
                                , (strpos($cell['href'], "[{$rowKey}]")===0 
                                    ? $rowValue // avoid urlencode() for first argument
                                    : urlencode($rowValue)), $cell['href']))
                        : $cell['href']
                    );
                    $cell['target'] = str_replace("[{$rowKey}]", $rowValue, $cell['target']);
                } else 
                    $cell[$prop] = (is_string($cell[$prop]) ? str_replace("[{$rowKey}]", $rowValue, $cell[$prop]) : $cell[$prop]);
                
            }
        }
        
    
    if ((int)$cell['disabled'])
        $cell['class'] .= " eg_disabled";
    
    $class = "eg-".($col['type']!=='button' ? $col['type'] : 'input-button').($cell['class'] != "" ? " ".$cell['class'] : '');
    
    $strCell = "";
    $strCell .= "\t<td class=\"{$this->name}-{$ixCol} {$class}\"".
        " data-field=\"{$cell['field']}\"".
        (
            $cell["style"]!="" 
            ? " style=\"{$cell["style"]}\""
            : "").">";

    // hidden inputs are to be repeated once
    $ixField = array_search($field, array_keys($this->visibleColumns));
    //echo '<pre>';
    //echo $ixCol.' '.$ixField;
    //echo '</pre>';

    if ($ixField===0){
        if (is_array($this->hiddenInputs))
        foreach($this->hiddenInputs as $hidden_field=>$hidden_col){
            $hidden_col = array_merge($this->col_default, (array)$hidden_col);
            $strCell .= "\r\n\t\t<input type=\"hidden\" name=\"{$hidden_field}[]\" value=\"".
                htmlspecialchars($ixRow===null 
                    ? $hidden_col["default"] 
                    : (isset($this->Rows[$ixRow][$hidden_field]) ? $this->Rows[$ixRow][$hidden_field] : '')
                    ).
                "\">";
        }
        
    }

    // for 3d grid roll thru suffixes array
    $nIteration = 0;
    foreach($arrSuffix as $suffix){

        $_val = ($suffix && isset($val[$suffix]) ? $val[$suffix] : $val);
        $_field = ($suffix ? $field."[{$suffix}]" : $field);
        $_textfield = ($suffix ? $field."_text[{$suffix}]" : $field.'_text');
        $_checkfield = ($suffix ? $field."_chk[{$suffix}]" : $field.'_chk');
        $classStr = ($suffix ? "eg-3d eg-3d-{$suffix}" : '');
        $classAttr = ($suffix ? ' class="'.$classStr.'"' : '');

        //pre-format value
        if ($_val!==null){
            switch($cell['type']){
                case "date":
                    $_val = $this->DateSQL2PHP( $_val, ($col['format'] ? $col['format'] : $this->conf['dateFormat']) );
                    break;
                case "datetime":
                    $_val = $this->DateSQL2PHP( $_val
                        , ($col['format'] ? $col['format'] : ($this->conf['dateFormat']." ".$this->conf['timeFormat'])) );
                    break;
                case "time":
                    $_val = $this->DateSQL2PHP( $_val
                        , ($col['format'] ? $col['format'] : $this->conf['timeFormat']) );
                    break;
                case "order":
                    $_val = ($ixRow+1);
                    break;
                case "money":
                case "float":
                case "double":
                case "real":
                case "numeric":
                case "number":
                case "integer":
                    $cell['decimalPlaces'] = isset($cell['decimalPlaces']) 
                        ? $cell['decimalPlaces'] 
                        : (in_array($cell['type'], Array('numeric','number','integer')) 
                            ? 0
                            : $this->conf['decimalPlaces']);
                    $_val = round($_val, $cell['decimalPlaces']);
                    $_val = number_format($_val, $cell['decimalPlaces'], $this->conf['decimalSeparator'], $this->conf['thousandsSeparator']);
                    break;
                default:
                    break;
            }
        } else {
            switch($cell['type']){
                case "order":
                    $_val = ($ixRow+1);
                    break;
                case "button":
                    $_val = $col['default'];
                    break;
                default:
                    break;
            }
        }
        
        //if cell is disabled, static, or there's a HREF, we make hidden input and text value
        if ((int)$cell['static'] || (int)$cell['disabled'] || ($cell['href']!='' && $_val!==null)){
            
            $aopen = "";$aclose = "";
            if ($cell['href']!=""){
                preg_match('/^(\s*)/', $_val, $m);
                $_val = trim($_val);
                $hrefRemovable = ( !($cell['static'] || $cell['readonly'] || $cell['disabled']) ? ' class="eg-href-removable"' : '');
                $aopen = $m[1]."<a href=\"{$cell['href']}\"".($cell['target'] ? " target=\"{$cell['target']}\"" : '')."{$hrefRemovable}>";
                $aclose = "</a>";
            }
            
            $strCell .= ($col['type']!='button' 
                ? "<input type=\"hidden\" name=\"{$_field}[]\" value=\"".htmlspecialchars($_val)."\">"
                : '');
            switch($col['type']){
                case 'button':
                    break;
                case "boolean":
                case "checkbox":
                    $strCell .= "<input{$classAttr} type=\"checkbox\" name=\"{$_checkfield}[]\"".($_val==true ? " checked" : "")." disabled>";
                    break;
                case "combobox":
                case "ajax_dropdown":
                    $_text = $this->getSelectValue($cell, $row, $suffix);
                    $strCell .= "<div{$classAttr}>".$aopen.@htmlspecialchars($_text).$aclose."</div>";
                    $strCell .= "<input type=\"hidden\" name=\"{$_textfield}[]\" value=\"".@htmlspecialchars($_text)."\">";
                    break;
                case "textarea":
                    $strCell .= "<div class=\"eg-editor {$classStr}\">".$aopen.str_replace("\r\n", "<br>", htmlspecialchars($_val)).$aclose."</div>";
                    break;
                case "html":
                    $strCell .= "<div{$classAttr}>".$aopen.$_val.$aclose."</div>";
                    break;
                default:
                    $strCell .= "<div{$classAttr}>".$aopen.htmlspecialchars($_val).$aclose."</div>";
                break;
            }
            
        } else { //display input and stuff
            
            $noAutoComplete = false;
            switch($col['type']){
                case 'button':
                    $strCell .= "<button name=\"{$_field}[]\">".htmlspecialchars($_val).'</button>';
                    break;
                case "order":
                    $strCell .= "<input type=\"hidden\" name=\"{$_field}[]\" value=\"".htmlspecialchars($_val).
                        "\"><div{$classAttr}><span>".htmlspecialchars($_val)."</span>.</div>";
                    break;
                case "textarea":
                    $strCell .= "<input type=\"hidden\" name=\"{$_field}[]\" value=\"".htmlspecialchars($_val)."\">";
                    $strCell .= "<div contenteditable='true' class=\"eg-editor {$classStr}\">".str_replace("\r\n", "<br>", htmlspecialchars($_val))."</div>";
                    break;
                case "boolean":
                case "checkbox":
                    $strCell .= "<input type=\"hidden\" name=\"{$_field}[]\" value=\"".@htmlspecialchars($_val)."\">";
                    $strCell .= "<input{$classAttr} type=\"checkbox\" name=\"{$_checkfield}[]\"".($_val==true ? " checked" : "").">";
                    break;
                case "combobox":
                case "select":
                    $strCell .= "<input type=\"hidden\" name=\"{$_field}[]\" value=\"".@htmlspecialchars($_val)."\">";
                    $strCell .= "<input{$classAttr} type=\"text\" name=\"{$_textfield}[]\" value=\"".@htmlspecialchars($this->getSelectValue($cell, $row, $suffix))."\">";
                    if ($ixRow===null && $nIteration==0){ //paint floating select
                        $strCell .= "<select id=\"select-{$col['field']}\" class=\"eg-floating-select\">\r\n";
                        $strCell .= (isset($cell['defaultText']) ? "\t<option value=\"\">{$cell['defaultText']}\r\n" : "");
                        
                        foreach($cell['source'] as $key => $_value){

                            if (is_array($_value)){ // if there's an optgoup
                                $strCell .= '<optgroup label="'.(isset($cell['optgroups']) ? $cell['optgroups'][$key] : $key).'">';
                                foreach($_value as $optVal=>$optText){
                                    $strCell .= "<option value='$optVal'".((string)$optVal==(string)(isset($strValue) ? $strValue : '') ? " SELECTED " : "").">".str_repeat('&nbsp;',5*(isset($cell["indent"][$key]) ? $cell["indent"][$key] : 0)).htmlspecialchars($optText)."</option>\r\n";
                                }
                                $strCell .= '</optgroup>';
                            } else
                                $strCell .= "\t<option value=\"".htmlspecialchars($key)."\">".htmlspecialchars($_value)."\r\n";
                        }
                        $strCell .= "</select>\r\n";
                    }
                    break;

                case "ajax_dropdown":
                    $strCell .= "<input type=\"hidden\" name=\"{$_field}[]\" value=\"".htmlspecialchars($_val)."\">";
                    $arrSource = array(
                        'table' => $cell['source'],
                        'prefix' => $cell['source_prefix'],
                        'showDeleted' => (isset($cell['showDeleted']) && $cell['showDeleted'] ? 1 : 0),
                        'extra' => (isset($cell['extra']) ? (string)$cell['extra'] : '')
                        );
                    if(isset($cell['threshold'])) $arrSource['threshold'] = (int)$cell['threshold'];
                    $strCell .= "<input{$classAttr} type=\"text\" name=\"{$_textfield}[]\""
                        .' data-source="'.htmlspecialchars( json_encode($arrSource) ).'"'
                        ." autocomplete=\"off\""
                        .(isset($cell['placeholder']) && $cell['placeholder'] ? ' placeholder="'.htmlspecialchars($cell['placeholder']).'"' : '')
                        //.($cell['extra'] ? ' extra="'.htmlspecialchars($cell['extra']).'"' : '')
                        ." value=\"".htmlspecialchars($this->getSelectValue($cell, $row, $suffix))."\">";
                case "del":
                    break;

                case "date":
                case "datetime":
                case "money":
                case "float":
                case "double":
                case "real":
                case "numeric":
                case "number":
                case "integer":
                    $noAutoComplete = true;
                case "text":
                default:
                    $strCell .= "<input{$classAttr} type=\"text\" name=\"{$_field}[]\" value=\"".htmlspecialchars($_val)."\""
                            .($noAutoComplete || (isset($cell['noAutoComplete']) && $cell['noAutoComplete']) ? " autocomplete=\"off\"" : '')
                            .(isset($cell['readonly']) && $cell['readonly'] ? " readonly=\"true\"" : '')
                            .(isset($cell['maxlength']) && $cell['maxlength'] ? " maxlength=\"{$cell['maxlength']}\"" : '')
                            .(isset($cell['placeholder']) && $cell['placeholder'] ? ' placeholder="'.htmlspecialchars($cell['placeholder']).'"' : '')
                            .">";
                    break;
            }
            
            $nIteration++;

        }
            
    }
    
    
    $strCell .= "</td>\r\n";
    return $strCell;
}

/**
 * @ignore
 */
function getSelectValue($cell, $row, $suffix=''){
    
    $oSQL = $this->oSQL;

    $_val = ($suffix ? (isset($row[$cell['field']][$suffix]) ? $row[$cell['field']][$suffix] : null) : (isset($row[$cell['field']]) ? $row[$cell['field']] : null));
    $_text = ($suffix ? (isset($row[$cell['field'].'_text'][$suffix]) ? $row[$cell['field'].'_text'][$suffix] : null) : (isset($row[$cell['field'].'_text']) ? $row[$cell['field'].'_text'] : null));
    
    if ( $_val==='' || $_val===null ){
        return isset($cell['defaultText']) ? $cell['defaultText'] : '';
    }

    if ( $_text ){
        return $_text;
    } 

    $ret = '';

    if ( is_array($cell['source']) ){

        foreach($cell['source'] as $key=>$value){
            if(is_array($value)){
                foreach($value as $subkey=>$subval){
                    if($subkey==$_val){
                        $ret = $subval;
                        break;
                    }
                }
                if($ret)
                    break;
            } else
                if($key==$_val){
                    $ret = $value;
                    break;
                }
        }

    } else {
        
        if ($cell['source']!=''){
            $parts = explode('|', $cell['source_prefix']);
            $prefix = isset($parts[0]) ? $parts[0] : null;
            $extra = isset($parts[1]) ? $parts[1] : null;
            $rs = $this->getDataFromCommonViews($this->oSQL, $_val, "", $cell['source'], $prefix, 1, $extra);
            $rw = $oSQL->fetch_array($rs);
            $ret = $rw["optText"];
        }

    }

    return ( $ret ? $ret : $_val );
}

function getDataFromCommonViews($oSQL, $strValue, $strText, $strTable, $strPrefix, $flagShowDeleted=false, $extra='', $flagNoLimits=true){

    GLOBAL $strLocal;

    if(is_a($this->intra, 'eiseIntra'))
        return $this->intra->getDataFromCommonViews($strValue, $strText, $strTable, $strPrefix, $flagShowDeleted, $extra, $flagNoLimits);
    
    //if (function_exists("getDataFromCommonViews")) // normally defined in common.php
    //    return (getDataFromCommonViews($oSQL, $strValue, $strText, $strTable, $strPrefix));
    
    if ($strPrefix!=""){
        $arrFields = Array(
            "idField" => "{$strPrefix}ID"
            , "textField" => "{$strPrefix}Title"
            , "textFieldLocal" => "{$strPrefix}TitleLocal"
            , "delField" => "{$strPrefix}FlagDeleted"
            );
        if($oSQL->d('SHOW COLUMNS FROM '.$strTable.' LIKE '.$oSQL->e($strPrefix.'Order')))
            $arrFields = array_merge($arrFields, array('orderField'=>$strPrefix.'Order'));
    } else {
        $arrFields = Array(
            "idField" => "optValue"
            , "textField" => "optText"
            , "textFieldLocal" => "optTextLocal"
            , "delField" => "optFlagDeleted"
        );
    }    
    
    $sql = "SELECT `{$arrFields["idField"]}` as optValue, `".$arrFields["textField{$strLocal}"]."` as optText
        FROM `{$strTable}`";
    
    if ($strValue!=""){ // key-based search
        $sql .= "\r\nWHERE `{$arrFields["idField"]}`=".$oSQL->escape_string($strValue);
    } else { //value-based search
        $sql .= "\r\nWHERE ";
        if($strText != ''){
            $sql .= "(`{$arrFields["textField"]}` LIKE ".$oSQL->escape_string($strText, "for_search")." COLLATE 'utf8_general_ci'
                OR `{$arrFields["textFieldLocal"]}` LIKE ".$oSQL->escape_string($strText, "for_search")." COLLATE 'utf8_general_ci'";
            $sql .= ") AND ";
        }
        
        $sql .= "`{$arrFields["delField"]}`<>1";
        $sql .= ($arrFields['orderField'] ? "\r\nORDER BY `{$arrFields['orderField']}`" : '');
    }
    $sql .= "\r\nLIMIT 0, 100";
    
    $rs = $oSQL->do_query($sql);
    
    return $rs;
}


function dateSQL2PHP($dtVar, $datFmt="d.m.Y H:i"){
GLOBAL $dbType;
$result =  $dtVar ? date($datFmt, strtotime($dtVar)) : "";
$result = preg_replace("/( 00\:00(\:00){0,1})/", "", $result);
return($result);
}

/**
 * This function updates data in the database basing on user eiseGrid input
 * 
 * @category Grid Data Handling
 * 
 * @param array $newData Optional. Array of new data to be used instead of $_POST
 * @param array $conf Optional. Configuration array. Supported options:
 *  - flagOnDuplicateKeyUpdate - boolean, default false. If true, INSERT statements will have ON DUPLICATE KEY UPDATE clause
 */
function Update($newData = null, $conf = array()){

    GLOBAL $usrID, $intra;
    $oSQL = $this->oSQL;
    $defaultConf = array('flagOnDuplicateKeyUpdate'=>false);
    $conf = array_merge($defaultConf, $conf);
    $row_id = $this->getPK();

//    $oSQL->startProfiling();

    if (!$newData) {
        $newData = $_POST;
        $flagPOST = True;
    }

    $arrTable = $oSQL->getTableInfo($this->conf['strTable']);
    $extraFieldsIns = array();
    $extraFieldsUpd = array();
    if ($arrTable['hasActivityStamp']){
        $extraFieldsIns[$arrTable['prefix']."InsertBy"] = $oSQL->e($intra->usrID);
        $extraFieldsIns[$arrTable['prefix']."InsertDate"] = 'NOW()';
        $extraFieldsUpd[$arrTable['prefix']."EditBy"] = $oSQL->e($intra->usrID);
        $extraFieldsUpd[$arrTable['prefix']."EditDate"] = 'NOW()';
    }

    if( $arrTable['PKtype']=='GUID' && !isset( $newData[$arrTable['PK'][0]]) )
        $extraFieldsIns[$arrTable['PK'][0]] = 'UUID()';

    foreach (explode("|", $newData["inp_".$this->name."_deleted"]) as $idToDelete)
        if ($idToDelete!="") {
            $oSQL->q("DELETE FROM {$this->conf['strTable']} WHERE ".$this->getMultiPKCondition($arrTable['PK'], $idToDelete));
        }

    $this->newData_transposed = $this->json($newData, array('flagDontEncode'=>True));

    foreach ($this->newData_transposed as $ix => $row) {

        if( ($flagPOST || $conf['flagOnlyUpdated']) && !$newData["inp_{$this->name}_updated"][$ix+1]){
            continue;
        }

        $sqlWhere = $this->getMultiPKCondition($arrTable['PK'], $row[$row_id]);

        if($row[$row_id]){
            $sqlExists = "SELECT COUNT(*) FROM {$this->conf['strTable']} WHERE {$sqlWhere}";
            if($oSQL->d($sqlExists)>0)
                $toDo = "update";
            else 
                $toDo = "insert";
        } else {
            $toDo = "insert";
        }

        // unset non-present values
        foreach ($row as $field => $value) {
            if(!isset($arrTable['columns_index'][$field]) || !isset($arrTable['columns_types'][$field]))
                unset($row[$field]);
        }
        // get basic sql
        $sqlFields_base = ltrim($intra->getSQLFields($arrTable, $row), "\n, ");

        if($toDo == 'insert'){
            $sql =  "INSERT INTO {$this->conf['strTable']} SET {$sqlFields_base}";
            foreach (array_merge($extraFieldsIns, $extraFieldsUpd) as $field => $value) {
                if(!isset($arrTable['columns_index'][$field]))
                    continue;
                $sql .= "\n, {$field}={$value}";
            }
        } else {
            $sql =  "UPDATE {$this->conf['strTable']} SET {$sqlFields_base}";
            foreach ($extraFieldsUpd as $field => $value) {
                if(!isset($arrTable['columns_index'][$field]))
                    continue;
                $sql .= "\n, {$field}={$value}";
            }
            $sql .= "\nWHERE {$sqlWhere}";
        }

        $oSQL->q($sql);
        if($toDo=='insert'){
            $this->newData_transposed[$ix][$row_id] = ($row[$row_id] ? $row[$row_id] : $oSQL->i());
        }

    }

//    $oSQL->showProfileInfo();
//    die('<pre>'.var_export($arrTable, true));

}

/**
 * This function returns JSON-encoded data from eiseGrid input
 * 
 * @category Grid Data Handling
 * 
 * @param array $newData Optional. Array of new data to be used instead of $_POST
 * @param array $conf Optional. Configuration array. Supported options:
 *       - flagDontEncode - boolean, default false. If true, function returns array instead of JSON-encoded string
 * 
 * @return JSON-encoded string or array of data
 */
function json( $newData = null, $conf = array() ){

    GLOBAL $intra;

    $defaultConf = array('flagDontEncode'=>false);
    $conf = array_merge($defaultConf, $conf);

    if(!$newData)
        $newData = $_POST;

    $pkColName = $this->getPK();

    $aRet = array();

    $arrTabKeys = array();
    foreach($this->Tabs3D as $tab){
        $arrTabKeys[] = $tab['ID'];
    }

    if(empty($arrTabKeys)){
        $arrTabKeys[] = '';
    }

    
    for($i=1;$i<count((array)$newData[$pkColName]);$i++){

        $a = array();
        foreach($this->Columns as $col){

            foreach($arrTabKeys as $tabKey){

                if(!$tabKey){
                    $data = (isset($newData[$col['field']][$i]) ? $newData[$col['field']][$i] : null);
                    $text = (isset($newData[$col['field'].'_text'][$i]) ? $newData[$col['field'].'_text'][$i] : null);
                } else {
                    $data = (isset($newData[$col['field']][$tabKey][$i]) 
                        ? $newData[$col['field']][$tabKey][$i] 
                        : (isset($newData[$col['field']][$i]) ? $newData[$col['field']][$i] : null));
                    $text = (isset($newData[$col['field'].'_text'][$tabKey][$i]) 
                        ? $newData[$col['field'].'_text'][$tabKey][$i] 
                        : (isset($newData[$col['field'].'_text'][$i]) 
                            ? $newData[$col['field'].'_text'][$i] 
                            : null));
                }
                
                switch($col['type']){
                    case 'order':
                        $val = $i;
                        break;
                    case 'date':
                        $val = (isset($this->oSQL) ? $this->oSQL->unq($intra->datePHP2SQL($data)) : '');
                        break;
                    case 'datetime':
                        $val = (isset($this->oSQL) ? $this->oSQL->unq($intra->datetimePHP2SQL($data)) : '');
                        break;
                    case "integer":
                    case "real":
                    case "numeric":
                    case "number":
                    case "money":
                        $val = (isset($this->oSQL) ? $this->oSQL->unq($intra->decPHP2SQL($data)) : '');
                        break;
                    case 'combobox':
                    case 'select':
                    case 'ajax_dropdown':
                        $val = ($data!=='' ? $data : null);
                        $text = ($text!=='' ? $text : '');
                        break;
                    default: 
                        $val = $data;
                        break;
                }
                if(!$tabKey){
                    $a[$col['field']] = $val;
                    if($text)
                        $a[$col['field'].'_text'] = $text;
                } else {
                    $a[$col['field']][$tabKey] = $val;
                    if($text)
                        $a[$col['field'].'_text'][$tabKey] = $text;
                }
            }
        }
        $aRet[] = $a;
    }

    return ( $conf['flagDontEncode'] ? $aRet : json_encode($aRet) );
}

/**
 * This function returns row_id column name
 * 
 * @ignore
 */
function getPK(){

    $pkColName = "";
    foreach($this->Columns as $i=>$col){
        if ($this->Columns[$i]['type']=="row_id") {
            $pkColName = $this->Columns[$i]['field'];
            break;
        }
    }

    if(!$pkColName){
        foreach($this->Columns as $i=>$col){
            if ($this->Columns[$i]['type']=="order") {
                $ordColName = $this->Columns[$i]['field'];
                break;
            }
        }
        $pkColName = $ordColName;
    }

    return $pkColName;
}

/**
 * @ignore
 */
private function getMultiPKCondition($arrPK, $strValue){
    
    GLOBAL $intra;
    
    if (is_object($intra))
        return $intra->getMultiPKCondition($arrPK, $strValue);
    else 
        if (function_exists('getMultiPKCondition')){
            return getMultiPKCondition($arrPK, $strValue);
        }
        
    $arrValue = explode("##", $strValue);
    $sql_ = "";
    for($jj = 0; $jj < count($arrPK);$jj++)
        $sql_ .= ($sql_!="" ? " AND " : "").$arrPK[$jj]."='".$arrValue[$jj]."'";
    return $sql_;
    
}

private static function confVariations($conf, $variations){

    if(class_exists('eiseIntra'))
        return eiseIntra::confVariations($conf, $variations);

    $retVal = null;
    foreach($variations as $variant){
        if(isset($conf[$variant])){
            $retVal = $conf[$variant];
            break;
        }
    }
    return $retVal;
}

}

/**
 * @category Grid Backward Compatibility
 * 
 * @deprecated since version 3.7.0
 * 
 * Class easyGrid is kept for backward compatibility. Please use eiseGrid instead.
 */
class easyGrid extends eiseGrid{}
?>