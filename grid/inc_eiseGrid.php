<?php
/**
 * eiseGrid PHP class
 * 
 * PHP backend for eiseGrid library that displays data grid, handles client side operations (data input, calculation and validation) and data update on the server side.
 * 
 * @package eiseIntra
 * @subpackage eiseGrid
 *
 * @author Ilya Eliseev (ie@e-ise.com)
 * @copyright (c) 2006-2015 Ilya S. Eliseev
 *
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 *
 * @version 2.5beta
 */
class eiseGrid {

static $defaultWidthsByType = array(
        'numeric' => '60px'
        , 'number' => '60px'
        , 'integer' => '60px'
        , 'real' => '80px'
        , 'money' => '80px'

        , 'date' => '80px'
        , 'datetime' => '120px'

        , 'boolean' => '30px'
        , 'checkbox' => '30px'
        
        , 'order' => '25px'

        , 'text' => '100px'
        , 'combobox' => '100px'
        , 'select' => '100px'
    );

/**
 * array of columns. can be associative or indexed.
 */
public $Columns = array();


/**
 * Associative array of fields. Key is field name, value is array similar to $Columns
 */
protected $__fields = array();

/**
 * Rowspan. Default 1. To be updated on initialization, after field number calculation for each column
 */
protected $__rowspan = 1;


/**
 * array of rows. each row is accociative array of fieldName=>fieldValue
 */
public $Rows = array();

function __construct($oSQL
    , $strName
    , $arrConfig
    ){
    
    GLOBAL $intra;

    $this->conf = Array(                    //defaults for eiseGrid
        'titleDel' => "Del" // column title for Del
        , "titleAdd" => "Add >>" // column title for Add
        //, 'controlBarButtons' => 'add|insert|moveup|movedown|delete|save'
        , 'extraInputs' => Array("DataAction"=>"update")
        , 'urlToSubmit' => $_SERVER["PHP_SELF"]
        , 'dateFormat' => (isset($intra->conf['dateFormat']) ? $intra->conf['dateFormat'] : "d.m.Y")  
        , 'timeFormat' => (isset($intra->conf['timeFormat']) ? $intra->conf['timeFormat'] : "H:i") 
        , 'decimalPlaces' => "2"
        , 'decimalSeparator' => (isset($intra->conf['decimalSeparator']) ? $intra->conf['decimalSeparator'] : ".")
        , 'thousandsSeparator' => (isset($intra->conf['thousandsSeparator']) ? $intra->conf['thousandsSeparator'] : ",")
        , 'totalsTitle' => 'Totals'
        , 'noRowsTitle' => 'Nothing found'
        , 'spinnerTitle' => 'Loading...'
        , 'arrPermissions' => Array("FlagWrite" => true)
        , 'Tabs3DCookieName' => $strName.'_tabs3d'

        , 'eiseIntraRelativePath' => eiseIntraRelativePath

    );
    
    $this->oSQL = $oSQL;

    $this->conf = array_merge($this->conf, $arrConfig);
    $this->name = $strName;
    $this->permissions = $this->conf["arrPermissions"];
    $this->intra = ($this->conf['intra'] ? $this->conf['intra'] : $intra);
    
    //backward-compatibility staff
    $this->permissions["FlagDelete"] = (isset($this->conf['flagNoDelete']) ? !$this->conf['flagNoDelete'] : $this->permissions["FlagDelete"]);
    $this->permissions["FlagWrite"] = (isset($this->conf['flagDisabled']) ? !$this->conf['flagDisabled'] : $this->permissions["FlagWrite"]);
    
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
 */
function addColumn($arrCol){

    if(!$arrCol['field'])
        throw new Exception("No field specified");
        

    if($arrCol['fieldInsertBefore'] || $arrCol['fieldInsertAfter']){

        $Columns_new = array();
        $flagInserted = false;

        foreach($this->Columns as $ix=>$col){

            if($col['field']==$arrCol['fieldInsertBefore']){
                $Columns_new[$arrCol['field']] = $arrCol;
                $flagInserted = true;
            }

            $Columns_new[$col['field']] = $col;

            if($col['field']==$arrCol['fieldInsertAfter']){
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
 * This function changes column property to defined values and returns its previous value.
 */
function setColumnProperty($field, $property, $value){
    $retVal = null;
    foreach($this->Columns as &$col){
        if($col['field']==$field){
            $retVal = $col[$property];
            $col[$property] = $value;
            break;
        }
    }
    return $retVal;
}

/**
 * This function does main job: it generates HTML for the whole eiseGrid.
 *
 */
function get_html($allowEdit=true){

    GLOBAL $_DEBUG;
    GLOBAL $strLocal;

    $intra = $this->intra;
   
    $oSQL=$intra->oSQL;
   
    $strRet = '<div class="eiseGrid'.($this->conf['class'] ? ' '.$this->conf['class'] : '').'" id="'.$this->name.'">'."\r\n";
    
    if (!$allowEdit)
        $this->permissions["FlagWrite"] = false;
    
    if ($this->permissions["FlagWrite"]  && !empty($this->conf['controlBarButtons'])){
        
        $arrButtons = explode("|", $this->conf['controlBarButtons']);
        
        $strControlBar = "<div class=\"eg-controlbar\">";
        
        foreach ($arrButtons as $btn){
            $strControlBar .= "<button class=\"eg-button eg-button-{$btn}\" type=\"button\"><i></i></button>\r\n";
        }
        
        $strControlBar .= "</div>";
        
        $strRet .= $strControlBar;
        
    }
    
    $htmlTabs = '';
    if(count($this->Tabs3D)>0){
        $htmlTabs .= "<div id=\"{$this->name}-tabs3d\">\r\n";
        $htmlTabs .= "<ul>\r\n";
        foreach($this->Tabs3D as $ix=>$tab){
            $htmlTabs .= "<li><a href=\"#{$this->name}-tabs3d-{$tab['ID']}\">{$tab['title']}</a></li>\r\n"; 
        }
        $htmlTabs .= "</ul>\r\n";
        foreach($this->Tabs3D as $ix=>$tab){
            $htmlTabs .= "<div id=\"{$this->name}-tabs3d_{$tab['ID']}\" class=\"eg_pseudotabs\"></div>\r\n"; 
        }
    }

    /**
     * <THEAD>
     */
    $strHead .= "<tr>\r\n";

    $this->visibleColumns = Array();
    $this->hiddenInputs = Array();
    
    $this->headerColumns = array();
    $this->arrSpans = array();

    $strCols = '';

    $nColNumber = 0;

    $spannedColumns = array();

    foreach ($this->Columns  as $ix=>$col){
        
        if ((int)$this->permissions["FlagWrite"]==0){
            $this->Columns[$ix]['static'] = true;
        }
        if ($col['class'])
            $this->Columns[$ix]['staticClass'] = ' '.preg_replace("/\[.+?\]/", "", $col['class']);
        
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
            
            if ((isset($this->Columns[$ix+1])
                && $this->Columns[$ix+1]['title']!=$col['title']) || !isset($this->Columns[$ix+1])){
                
                $strHead .= "\t<th".
                            ($col["style"]!="" 
                            ? " style=\"{$col['style']}\""
                            : "").
                        " class=\"{$this->name}-{$spannedColumns[0]['fields'][0]['field']}"
                            .($col['mandatory'] 
                                ? " eg-mandatory" 
                                : "").$this->Columns[$ix]['staticClass']."\"";
                       
                        
                if (count($spannedColumns)>1) {
                    $this->arrSpans[$spannedColumns[0]['field']]=count($spannedColumns);
                    $strHead .= ' colspan="'.count($spannedColumns).'"';
                }

                $strHead .=  '>'
                        .($nColNumber==0 && $this->permissions["FlagWrite"] && !($this->permissions["FlagDelete"]===false)
                            ? '<input type="hidden" id="inp_'.$this->name.'_deleted" name="inp_'.$this->name.'_deleted" value="">'
                            : '')
                        .'<span>'.htmlspecialchars($col["title"]).'</span>'
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
                    $w = ($fld['width'] 
                            ? $fld['width'].(preg_match('/^[0-9]+$/', $fld['width']) ? 'px' : '')
                            : self::$defaultWidthsByType[($fld['type'] ? $fld['type'] : 'text')]
                        );
                    if($w){
                        $this->arrWidth[$key] = $w;
                        break;
                    }
                }
                if(!$this->arrWidth[$key]) 
                    $this->arrWidth[$key] = self::$defaultWidthsByType['text'] ;
            }

            $strCols .= "<col class=\"{$this->name}-{$key}\" style=\"width:{$this->arrWidth[$key]}\">\n";

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

                if(is_array($fld['source'])){
                    $ds = $fld['source'];
                } else {
                    $ds = @json_decode($fld['source'], true);
                    if(!$ds){
                        @eval('$ds = '.$fld['source']);
                        if(!$ds){
                            if(!preg_match('/^select\s+/i', $fld['source'])){
                                $aDS = explode('|', $fld['source']);
                                $ds = $aDS[0];
                                $fld['source_prefix'] = ($fld['source_prefix'] 
                                    ? $fld['source_prefix']
                                    : $aDS[1]);            
                            } else {
                                $ds = $fld['source'];
                                $flagIsSQL = true;
                            }
                            
                        }
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
                            $rs = $this->getDataFromCommonViews($oSQL, "", "", $fld['source'], $fld['source_prefix']);
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
        .($htmlTabs ? '<tr class="eg_tabs"><td colspan="'.count($this->visibleColumns).'">'.$htmlTabs.'</td></td></tr>' : '')
        .$strHead
        ."</thead>\r\n";

    $this->hiddenInputs = array_merge(
        Array($inpRowID['field'] => $inpRowID
            , "inp_{$this->name}_updated" => Array(
                    'field' => "inp_{$this->name}_updated"
                )
           )
        , $this->hiddenInputs
    );
    
    // no rows and spinner rows
    $strRet .= "<tbody class=\"eg-no-rows\"><tr><td colspan=\"".count($this->visibleColumns)."\">{$this->conf['noRowsTitle']}</td></tr></tbody>\r\n";
    $strRet .= "<tbody class=\"eg-spinner\"><tr><td colspan=\"".count($this->visibleColumns)."\">{$this->conf['spinnerTitle']}</td></tr></tbody>\r\n";
    

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
    $strFooter .= "<tfoot>";
    $strFooter .= "<tr>";
    
    $iColspan = 0;
    $iTotalsCol = 0;
    foreach($this->visibleColumns as $field => $col){
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
        $arrConfig['fields'][$fieldName] = Array('type'=>$field['type'], 'title'=>$field['title']);
        if ($field['mandatory']){
            $arrConfig['fields'][$fieldName]['mandatory'] = $field['mandatory'];
        }
        if ($field['totals']){
            $arrConfig['fields'][$fieldName]['totals'] = $field['totals'];
        }
        if ($field['decimalPlaces']){
            $arrConfig['fields'][$fieldName]['decimalPlaces'] = $field['decimalPlaces'];
        }
        if ($field['static']===true){
            $arrConfig['fields'][$fieldName]['static'] = true;
        }
        if ($field['disabled']===true){
            $arrConfig['fields'][$fieldName]['disabled'] = true;
        }
    }

    $jsonConfig = json_encode(array_merge($arrConfig, array('widths'=>$this->arrWidth
        , 'spans' => $this->arrSpans)
    ));
    $strRet .= "<input type=\"hidden\" id=\"inp_".$this->name."_config\" value=\"".htmlspecialchars($jsonConfig)."\">";
    
    if(count($this->Tabs3D)>0){
        $strRet .= "</div>\r\n";        
    }

    
    $strRet .= "</div>\r\n";
    
    return $strRet;

}

/**
 * This function echoes eiseGrid HTML
 */
function Execute($allowEdit=true) {
    
    echo $this->get_html($allowEdit);
    
}

/**
 * 
 */  
protected function __getRow($iRow, $row = null){

    $html = '<tbody class="'.(
            $iRow===null
            ? 'eg-template'
            : 'eg-data'.($row['__rowClass'] ? ' '.$row['__rowClass'] : '')
            ).'">'."\r\n";

    for ($iSubRow = 0; $iSubRow < $this->__rowspan; $iSubRow++){

        $html .= '<tr>'."\r\n";

        foreach($this->visibleColumns as $ixCol=>$col){
            $html .= ($col['fields'][$iSubRow]
                ? $this->__paintCell($col['fields'][$iSubRow], $ixCol, $iRow)
                : '<td>&nbsp;</td>'."\r\n"
                );
        }

        $html .= '</tr>'."\r\n";
    }

    $html .= '</tbody>'."\r\n";

    return $html;

}

protected function __paintCell($col, $ixCol, $ixRow, $rowID=""){
    
    $field = ($col['type']=="del" ? "del" : $col["field"]);
    $row = $this->Rows[$ixRow];
    $row[$field] = $val = ($ixRow===null ? $col['default'] : $row[$field]);
    $cell = $col;
    
    $arrSuffix = array();
    if (count($this->Tabs3D)>0 && ($ixRow===null || is_array($val))) {
        foreach($this->Tabs3D as $ix=>$tab){
            $arrSuffix[] = $tab['ID'];
        }
    } else {
        $arrSuffix = array(0);
    }
    
    if ($ixRow===null){ //for template row: all calcualted class are grounded, static/disabled set to 0, href grounded
        $cell['class'] = $cell['staticClass'];
        $cell['static'] = (is_string($cell['static']) ? 0 : $cell['static']);
        $cell['disabled'] = (is_string($cell['disabled']) ? 0 : $cell['disabled']) ;
        $cell['href'] = "" ;
    } else // calculate row-dependent options: class, static/disabled, or href 
        foreach($this->Rows[$ixRow] as $rowKey=>$rowValue){
            $cell['class'] = str_replace("[{$rowKey}]", $rowValue, $cell['class']);
            $cell['static'] = (is_string($cell['static']) ? str_replace("[{$rowKey}]", $rowValue, $cell['static']) : $cell['static']);
            $cell['disabled'] = (is_string($cell['disabled']) ? str_replace("[{$rowKey}]", $rowValue, $cell['disabled']) : $cell['disabled']) ;
            if ($cell['source'])
                $cell['source'] = (is_string($cell['source']) ? str_replace("[{$rowKey}]", $rowValue, $cell['source']) : $cell['source']) ;
            if ($cell['extra'])
                $cell['extra'] = (is_string($cell['extra']) ? str_replace("[{$rowKey}]", $rowValue, $cell['extra']) : $cell['extra']) ;
            if ($cell['placeholder'])
                $cell['placeholder'] = (is_string($cell['placeholder']) ? str_replace("[{$rowKey}]", $rowValue, $cell['placeholder']) : $cell['placeholder']) ;
            if ($cell['href']){
                $cell['href'] = (strpos($cell['href'], "[{$rowKey}]")!==null // if argument exists in HRef
                    ? ($val==''||$rowValue==''
                        ? $cell['href'] 
                        : str_replace("[{$rowKey}]"
                            , (strpos($cell['href'], "[{$rowKey}]")===0 
                                ? $rowValue // avoid urlencode() for first argument
                                : urlencode($rowValue)), $cell['href']))
                    : $cell['href']
                );
                $cell['target'] = str_replace("[{$rowKey}]", $rowValue, $cell['target']);
            }
        }
    
    if ((int)$cell['disabled'])
        $cell['class'] .= " eg_disabled";
    
    $class = "eg-".$col['type'].($cell['class'] != "" ? " ".$cell['class'] : '');
    
    $strCell = "";
    $strCell .= "\t<td class=\"{$this->name}-{$ixCol} {$class}\"".(
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
            $strCell .= "\r\n\t\t<input type=\"hidden\" name=\"{$hidden_field}[]\" value=\"".
                htmlspecialchars($ixRow===null 
                    ? $hidden_col["default"] 
                    : $this->Rows[$ixRow][$hidden_field]).
                "\">";
        }
        
    }

    // for 3d grid roll thru suffixes array
    $nIteration = 0;
    foreach($arrSuffix as $suffix){

        $_val = ($suffix ? $val[$suffix] : $val);
        $_field = ($suffix ? $field."[{$suffix}]" : $field);
        $_textfield = ($suffix ? $field."_text[{$suffix}]" : $field.'_text');
        $_checkfield = ($suffix ? $field."_chk[{$suffix}]" : $field.'_chk');
        $classStr = ($suffix ? "eg_3d eg_3d_{$suffix}" : '');
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
                default:
                    break;
            }
        }
        
        //if cell is disabled, static, or there's a HREF, we make hidden input and text value
        if ((int)$cell['static'] || (int)$cell['disabled'] || $cell['href']!=""){
            
            $aopen = "";$aclose = "";
            if ($cell['href']!=""){
                preg_match('/^(\s*)/', $_val, $m);
                $_val = trim($_val);
                $aopen = $m[1]."<a href=\"{$cell['href']}\"".($cell['target'] ? " target=\"{$cell['target']}\"" : '').">";
                $aclose = "</a>";
            }
            
            $strCell .= "<input type=\"hidden\" name=\"{$_field}[]\" value=\"".htmlspecialchars($_val)."\">";
            switch($col['type']){
                case "boolean":
                case "checkbox":
                    $strCell .= "<input{$classAttr} type=\"checkbox\" name=\"{$_checkfield}[]\"".($_val==true ? " checked" : "")." disabled>";
                    break;
                case "combobox":
                case "ajax_dropdown":
                    $strCell .= "<div{$classAttr}>".$aopen.htmlspecialchars($this->getSelectValue($cell, $row, $suffix)).$aclose."</div>";
                    break;
                case "textarea":
                    $strCell .= "<div{$classAttr}>".$aopen.str_replace("\r\n", "<br>", htmlspecialchars($_val)).$aclose."</div>";
                    break;
                case "html":
                    $strCell .= "<div{$classAttr}>".$aopen.$_val.$aclose."</div>";
                    break;
                default:
                    $strCell .= "<div{$classAttr}>".$aopen.htmlspecialchars($_val).$aclose."</div>";
                break;
            }
            
        } else { //display input and stuff
        
            switch($col['type']){
                case "order":
                    $strCell .= "<input type=\"hidden\" name=\"{$_field}[]\" value=\"".htmlspecialchars($_val).
                        "\"><div{$classAttr}><span>".htmlspecialchars($_val)."</span>.</div>";
                    break;
                case "textarea":
                    $strCell .= "<input type=\"hidden\" name=\"{$_field}[]\" value=\"".htmlspecialchars($_val)."\">";
                    $strCell .= "<div contenteditable='true' class=\"eg_editor {$classStr}\">".str_replace("\r\n", "<br>", htmlspecialchars($_val))."</div>";
                    break;
                case "boolean":
                case "checkbox":
                    $strCell .= "<input type=\"hidden\" name=\"{$_field}[]\" value=\"".htmlspecialchars($_val)."\">";
                    $strCell .= "<input{$classAttr} type=\"checkbox\" name=\"{$_checkfield}[]\"".($_val==true ? " checked" : "").">";
                    break;
                case "combobox":
                 case "select":
                    $strCell .= "<input type=\"hidden\" name=\"{$_field}[]\" value=\"".htmlspecialchars($_val)."\">";
                    $strCell .= "<input{$classAttr} type=\"text\" name=\"{$_textfield}[]\" value=\"".htmlspecialchars($this->getSelectValue($cell, $row, $suffix))."\">";
                    if ($ixRow===null && $nIteration==0){ //paint floating select
                        $strCell .= "<select id=\"select-{$col['field']}\" class=\"eg-floating-select\">\r\n";
                        $strCell .= (isset($cell['defaultText']) ? "\t<option value=\"\">{$cell['defaultText']}\r\n" : "");
                        
                        foreach($cell['source'] as $key => $_value){

                            if (is_array($_value)){ // if there's an optgoup
                                $strCell .= '<optgroup label="'.(isset($cell['optgroups']) ? $cell['optgroups'][$key] : $key).'">';
                                foreach($_value as $optVal=>$optText){
                                    $strCell .= "<option value='$optVal'".((string)$optVal==(string)$strValue ? " SELECTED " : "").">".str_repeat('&nbsp;',5*$cell["indent"][$key]).htmlspecialchars($optText)."</option>\r\n";
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
                        'showDeleted' => ($cell['showDeleted'] ? 1 : 0),
                        'extra' => (string)$cell['extra']
                        );
                    $strCell .= "<input{$classAttr} type=\"text\" name=\"{$_textfield}[]\""
                        .' data-source="'.htmlspecialchars( json_encode($arrSource) ).'"'
                        ." autocomplete=\"off\""
                        .($cell['placeholder'] ? ' placeholder="'.htmlspecialchars($cell['placeholder']).'"' : '')
                        //.($cell['extra'] ? ' extra="'.htmlspecialchars($cell['extra']).'"' : '')
                        ." value=\"".htmlspecialchars($this->getSelectValue($cell, $row, $suffix))."\">";
                case "del":
                    break;

                case "text":
                default:
                    $strCell .= "<input{$classAttr} type=\"text\" name=\"{$_field}[]\" value=\"".htmlspecialchars($_val)."\""
                            .($cell['placeholder'] ? ' placeholder="'.htmlspecialchars($cell['placeholder']).'"' : '')
                            .">";
                    break;
            }
            
            $nIteration++;

        }
            
    }
    
    
    $strCell .= "</td>\r\n";
    return $strCell;
}

function getSelectValue($cell, $row, $suffix=''){
    
    $oSQL = $this->oSQL;

    $_val = ($suffix ? $row[$cell['field']][$suffix] : $row[$cell['field']]);
    $_text = ($suffix ? $row[$cell['field'].'_text'][$suffix] : $row[$cell['field'].'_text']);
    
    if ($_val==""){
        return $cell['defaultText'];
    }

    if ($_text != ""){
        return $_text;
    }
    if ( is_array($cell['source']) ){
        foreach($cell['source'] as $key=>$value){
            if(is_array($value)){
                foreach($value as $subkey=>$subval){
                    if($subkey==$_val)
                        return $subval;
                }
            } else
                if($key==$_val)
                    return $value;
        }
        return $cell['defaultText'];
    } else {
        if ($cell['source']!=''){
            $rs = $this->getDataFromCommonViews($this->oSQL, $_val, "", $cell['source'], $cell['source_prefix']);
            $rw = $oSQL->fetch_array($rs);
            return $rw["optText"];
        } else {
            return $cell['defaultText'];
        }
    }
}

function getDataFromCommonViews($oSQL, $strValue, $strText, $strTable, $strPrefix){

    GLOBAL $strLocal;
    
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

function datePHP2SQL($dtVar, $valueIfEmpty="NULL"){
// потом сделаю
}


function Update($arrNewData = array(), $conf = array()){
    
    GLOBAL $usrID;

    $defaultConf = array('flagOnDuplicateKeyUpdate'=>false);

    $conf = array_merge($defaultConf, $conf);

    if (count($arrNewData)==0){
        $arrNewData = $_POST;        
    }

    $sql = Array();
    
    $oSQL=$this->oSQL;
    $tblName = $this->conf['strTable'];
    
    $arrTable = $this->getTableInfo($oSQL->dbname, $tblName);
    
    $arrFields = Array();
    $arrValues = Array();
    $arrFieldsValues = Array();
    
    switch($arrTable['PKtype']){
       case "auto_increment":
          break;
       case "GUID":
          $arrFields[] = $arrTable['PK'][0];
          $arrValues[] = "UUID()";
          break;
       default:
          break;
    }
    
    //defining PK field on grid
    foreach($this->Columns as $i=>$col){
        if ($this->Columns[$i]['type']=="row_id") 
            $pkColName = $this->Columns[$i]['field'];
        
        if ($col['mandatory'])
            $mndFieldName = $col["field"];
        
        foreach($arrTable["columns"] as $j=>$tCol) 
            if ((!$col['disabled'] || ($col['disabled'] && in_array($col['field'], $arrTable['PK'])))
                && !($col['type']=="row_id" && !$conf['flagOnDuplicateKeyUpdate'])
                && $col['field'] == $arrTable["columns"][$j]["Field"]
                ){
                $arrFields[] = $col['field'];
                $arrTable["columns"][$j]['DataType'] = ($col['type']=="combobox" || $col['type']=="ajax_dropdown" 
                    ? "combobox" 
                    : $arrTable["columns"][$j]['DataType']);
                $arrValues[] = $this->getSQLValue($arrTable["columns"][$j], true);
                if($col['type']!="row_id")
                    $arrFieldsValues[] = $col['field'] ." = ".$this->getSQLValue($arrTable["columns"][$j], true);
            }
    }
    
    if (!$mndFieldName)
        $mndFieldName = $pkColName;
    
    if ($arrTable['hasActivityStamp']){
        $arrFields[] = $arrTable['prefix']."InsertBy";
        $arrFields[] = $arrTable['prefix']."InsertDate";
        $arrFields[] = $arrTable['prefix']."EditBy";
        $arrFields[] = $arrTable['prefix']."EditDate";
        $arrValues[] = "'\$usrID'";
        $arrValues[] = "NOW()";
        $arrValues[] = "'\$usrID'";
        $arrValues[] = "NOW()";
        $arrFieldsValues[] = $arrTable['prefix']."EditBy = '\$usrID'";
        $arrFieldsValues[] = $arrTable['prefix']."EditDate = NOW()";
    }
    
    //deleted items
    $strDeleted = $arrNewData["inp_".$this->name."_deleted"];
    //echo $strDeleted;
    $arrToDelete = explode("|", $strDeleted);
    
    for ($i=0;$i<count($arrToDelete);$i++)
        if ($arrToDelete[$i]!="") {
            $sql[] = "DELETE FROM $tblName WHERE ".$this->getMultiPKCondition($arrTable['PK'], $arrToDelete[$i]);
        }
    
    // running thru updated
    for($i=1;$i<count($arrNewData[$pkColName]);$i++)
        if ($arrNewData["inp_".$this->name."_updated"][$i] && $arrNewData[$mndFieldName][$i]!=""){

            eval ("\$sqlIns = \"INSERT INTO $tblName (\r\n".
                     "              ".implode("\r\n              , ", $arrFields)."\r\n".
                     "           ) VALUES (\r\n".
                     "              ".implode("\r\n              , ", $arrValues)."
                  )".($conf['flagOnDuplicateKeyUpdate']
                      ? "\r\nON DUPLICATE KEY UPDATE\r\n".implode("\r\n                  , ", $arrFieldsValues)
                      : '')
                  ."\";");

            eval ("\$sqlUpd = \"UPDATE $tblName SET
                  ".implode("\r\n                  , ", $arrFieldsValues)."\r\n".
                  "           WHERE ".$this->getMultiPKCondition($arrTable['PK'], $arrNewData[$pkColName][$i])."\";");

            if ($arrNewData[$pkColName][$i]=="" || $conf['flagOnDuplicateKeyUpdate']) { //if inserted
                $sql[] = $sqlIns;
            } else { //if updated
                $sql[] = $sqlUpd;
            }
        }
    
    for ($i=0;$i<count($sql);$i++){
        $oSQL->do_query($sql[$i]);
    }
    
    return true;
}


function getSQLValue($col){
    $strValue = "";
    
    $strPost = "\$_POST['".$col["Field"]."'][\$i]";
    
    switch($col["DataType"]){
      case "integer":
        $strValue = "\".(integer)$strPost.\"";
        break;
      case "order":
        $strValue = "'\".($strPost=='' ? \$i : $strPost).\"'";
        break;
      case "real":
      case "numeric":
      case "number":
      case "money":
        $strValue = "\".(double)str_replace('{$this->conf['decimalSeparator']}', '.', str_replace('{$this->conf['thousandsSeparator']}', '', $strPost)).\"";
        break;
      case "boolean":
      case "checkbox":
        $strValue = "\".(integer)\$_POST['".$col["Field"]."'][\$i].\"";
        break;      
      case "binary":
        $strValue = "\".mysql_real_escape_string(\$".$col["Field"].").\"";
        break;
      case "datetime":
      case "date":
        $strValue = "\".DatePHP2SQL($strPost).\"";
        break;
      case "activity_stamp":
        if (preg_match("/By$/i", $col["Field"]))
           $strValue .= "'\$usrID'";
        if (preg_match("/Date$/i", $col["Field"]))
           $strValue .= "NOW()";
        break;
      case "FK":
      case "combobox":
      case "ajax_dropdown":
       $strValue = "\".($strPost!=\"\" ? \"'\".$strPost.\"'\" : \"NULL\").\"";
        break;
      case "PK":
      case "text":
      case "varchar":
      default:
        $strValue = "\".\$oSQL->e($strPost).\"";
        break;
    }
    //echo "<pre>";
    //echo $strValue;
    //print_r($col);
    
    return $strValue;
}

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

private function getTableInfo($dbName, $tableName){
    GLOBAL $intra;
    
    if (is_object($intra))
        return $intra->getTableInfo($dbName, $tableName);
    else 
        if (function_exists('getTableInfo')){
            return getTableInfo($dbName, $tableName);
        }
        
        throw new Exception('Unable to retrieve table information using "getTableInfo()" function');
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

class easyGrid extends eiseGrid{}
?>