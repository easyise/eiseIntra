<?php
/**
 *
 * eiseIntraData is the class that encapsulates data handling routines
 *
 * Data types definition and conversion
 * SQL <-> PHP output data conversions
 * SQL query result conversion to JSON or Array (result2JSON())
 * Reference table routines (getDataFromCommonViews())
 * Archive/Restore routines
 * etc
 *
 * @package eiseIntra
 * @version 2.0beta
 *
 */
class eiseIntraData {
	
public $conf;
public $oSQL;
public $arrUsrData;
public $local;
public $intra;
public $oSQL_arch;

public $arrAttributeTypes = array(
    "integer" => 'integer'
    , "real" => 'real'
    , "boolean" => 'checkbox'
    , "text" => 'text'
    , "textarea" => 'text'
//    , "binary" => 'file' #not supported yet for docflow apps
    , "date" => 'date'
    , "datetime" => 'datetime'
    , "time" => 'time'
    , "combobox" => 'FK'
    , "ajax_dropdown" => 'FK'
    );


/**
 * $arrBasicDataTypes is used to convert data from user input locale (e.g. en_US) into SQL-friendly values.
 * It provides unique match from any possible type name (values) into basic type (key) that data will be converted to.
 * This array is used in eiseIntraData::getBasicDataType() function.
 * @ignore
 */
public static $arrBasicDataTypes = array(
    'integer'=>array('integer', 'int', 'number', 'smallint', 'mediumint', 'bigint')
    , 'real' => array('real', 'double', 'money', 'decimal', 'float')
    , 'boolean' => array('boolean', 'checkbox', 'tinyint', 'bit')
    , 'text' => array('text', 'varchar', 'char', 'tinytext', 'text', 'mediumtext', 'longtext')
    , 'binary' => array('binary', 'file', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob')
    , 'date' => array('date'), 'time' => array('time'), 'datetime' => array('datetime')
    , 'timestamp' => array('timestamp')
    , 'FK' => array('FK', 'select', 'combobox', 'ajax_dropdown', 'typeahead', 'enum', 'set')
    , 'PK' => array('PK')
);

/**
 * This function returns basic data type for provided $type variable. It can be as any MySQL data type as input type used in eiseIntra.
 * 
 * @param string $type - input type parameter, e.g. 'select' or 'money' 
 *
 * @return string - basic type from keys of eiseIntraData::$arrBasicTypes. If basic type's not found it returns 'text'.
 */
static function getBasicDataType($type){
    foreach(self::$arrBasicDataTypes as $majorType=>$arrCompat){
        if(in_array($type, $arrCompat)){
            return $majorType;
        }
    }
    return 'text';
}


/**
 * $arrIntraDataTypes defines basic type set that is used for conversion of data obtained from the database into user-specific locale.
 */
public static $arrIntraDataTypes = array(
    'integer' => array('integer', 'int', 'number', 'smallint', 'mediumint', 'bigint')
    , 'real' => array('real', 'double', 'decimal', 'float')
    , 'money' => array('money')
    , 'boolean' => array('boolean', 'tinyint', 'bit')
    , 'text' => array('text', 'varchar', 'char', 'tinytext', 'text', 'mediumtext', 'longtext')
    , 'binary' => array('binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob')
    , 'date' => array('date'), 'time' => array('time'), 'datetime' => array('datetime')
    , 'timestamp' => array('timestamp')
    , 'FK' => array('int', 'integer', 'varchar', 'char', 'text')
    , 'PK' => array('int', 'integer', 'varchar', 'char', 'text')
    , 'activity_stamp' => array('datetime', 'timestamp')
);

function __construct($oSQL = null, $conf = null){
        
    $arrFind = Array();
    $arrReplace = Array();
    $arrFind[] = '.'; $arrReplace[]='\\.';          
    $arrFind[] = '/'; $arrReplace[]='\\/';          
    $arrFind[] = 'd'; $arrReplace[]='([0-9]{1,2})'; 
    $arrFind[] = 'm'; $arrReplace[]='([0-9]{1,2})';
    $arrFind[] = 'Y'; $arrReplace[]='([0-9]{4})';
    $arrFind[] = 'y'; $arrReplace[]='([0-9]{1,2})';
    $this->conf['prgDate'] = str_replace($arrFind, $arrReplace, $this->conf['dateFormat']);
    $dfm  = preg_replace('/[^a-z]/i','', $this->conf['dateFormat']);
    $this->conf['prgDateReplaceTo'] = '\\'.(strpos($dfm, 'y')===false ? strpos($dfm, 'Y')+1 : strpos($dfm, 'y')+1).'-\\'.(strpos($dfm, 'm')+1).'-\\'.(strpos($dfm, 'd')+1);
    
    $arrFind = Array();
    $arrReplace = Array();            
    $arrFind[] = "."; $arrReplace[]="\\.";
    $arrFind[] = ":"; $arrReplace[]="\\:";
    $arrFind[] = "/"; $arrReplace[]="\\/";
    $arrFind[] = "H"; $arrReplace[]="([0-9]{1,2})";
    $arrFind[] = "h"; $arrReplace[]="([0-9]{1,2})";
    $arrFind[] = "i"; $arrReplace[]="([0-9]{1,2})";
    $arrFind[] = "s"; $arrReplace[]="([0-9]{1,2})";
    $this->conf["prgTime"] = str_replace($arrFind, $arrReplace, $this->conf["timeFormat"]);
    
    $this->oSQL = $oSQL;
}

/**
 * This function returns Intra type from key set of $arrIntraDataTypes array above. It takes $type and $field name as parameters, and it can be as Intra types as SQL data types returned by fetch_fields() or getTableInfo() functions.
 *
 */
public static function getIntraDataType($type, $field = ''){

	$arrTypeMatch = array();

    foreach(self::$arrIntraDataTypes as $majorType=>$arrCompat){
        if(in_array($type, $arrCompat)){
            $arrTypeMatch[] = $majorType;
        }
    }

    // PK - if field matches Primary Key pattern: 2-4 lowercase letters in the beginning, GUID or ID in the end (e.g. 'exmID' )- it is supposed to be PK field
    if(in_array('PK', $arrTypeMatch) && preg_match('/^[a-z]{2,4}(GU){0,1}ID$/',$field)){
    	return 'PK';
    }

    // FK - if field matches Foreign Key pattern: field name ends with 'ID' (like 'exmSomethingID') - it is supposed to be FK
    if(in_array('FK', $arrTypeMatch) && preg_match("/ID$/", $field)){
    	return 'PK';
    }

    $mtch = $arrTypeMatch[0];



    return ($mtch ? $mtch : 'text');
}

/**
 * This function formats data for user-friendly output according to user data type provided in $type parameter.
 *
 * @category Data formatting
 *
 * @param string $type - data type, according to eiseIntra::$arrUserDataType
 * @param variant $value - data as it's been returned from the database or calculated in PHP
 * @param int $decPlaces - number of decimal places
 *
 * @return string
 */
public function formatByType2PHP($type, $value, $decPlaces = null){

    $retVal = null;

    if($value===null)
      	return null;

    switch($type){
        case 'real':
            $retVal = $this->decSQL2PHP( $value, ($decPlaces===null ? self::getDecimalPlaces($value) : $decPlaces) );
            break;
        case 'money':
            $retVal = $this->decSQL2PHP( $value, 2 );
            break;
        case 'integer':
        case 'boolean':
            $retVal = $this->decSQL2PHP( $value, 0 );
            break;
        case 'date':
            $retVal = $this->dateSQL2PHP($value);
            break;
        case 'datetime':
            $retVal = $this->datetimeSQL2PHP($value);
            break;
        case 'timestamp':
            $timestamp = strtotime($value);
            $retVal = ($timestamp !== false) ? $this->datetimeSQL2PHP(date('Y-m-d H:i:s', $timestamp)) : '';
            break;
        case 'time':
        default:
            $retVal = (string)$value;
            break;
    }
    
    return $retVal;
}

/**
 * This function formats data to SQL-query friendly value, not escaped, without quotes.
 *
 * @category Data formatting
 *
 * @param string $type - any data type supperted by SQL server
 * @param variant $value - value to be formatted
 * @param string $thisType - detected eiseIntra data type from [$arrIntraDataTypes](#eiseintradata_arrintradatatypes), this parameter is set by ref.
 *
 * @return string - The value formatted.
 */ 
public function formatByType2SQL($type, $value, &$thisType = ''){

    $retVal = null;

    $thisType = self::getBasicDataType($type);

    if($value===null && $thisType!=='boolean')
        return null;

    switch($thisType){
        case 'FK':
            $retVal = ($value=='' ? null : $value);
            break;
        case 'real':
        case 'integer':
            $retVal = ( is_numeric($value) ? (string)$this->decPHP2SQL( $value ) : 'null' );
            break;
        case 'boolean':
            $retVal = ($value=='on' ||  $value=='1' ? '1' : '0');
            break;
        case 'date':
            $retVal = ($value=='' ? null : $this->unq($this->datePHP2SQL($value)) );
            break;
        case 'datetime':
            $retVal = ($value=='' ? null : $this->unq($this->datetimePHP2SQL($value)) );
            break;
        case 'timestamp':
            $retVal = ($value=='' ? null : $this->unq($this->datetimePHP2SQL($value)) );
            break;
        case 'time':
        default:
            $retVal = (string)$value;
            break;
    }

    return $retVal;
}

/**
 * This function returns SQL result as JSON string or array, in format that can be understood by eiseIntra's JavaScript fill() methods. Output format is a bit more complex than just list of rows as dictionaries. It also contains some features that scipt interpret for data display:
 * - read/write permissions for given field in given tuple
 * - text representation for field if it's foregn key value with reference to some disctionary
 * - HREF for a field, if any.
 * For example, we have a following result: [{a: b, c: d, e: f, e_text: f_text}, {a: b1, c: d1, e: f1, e_text: f_text1}]. In the simiplest case, by default, it will be formatted in the following way: 
 * [{a: {v: b }, c: {v: d}, e: {v: f, t: f_text} }, {a: {v: b1 },{c: {v: d1}}, e: {v: f1, t: f_text1} }] - as you can see data value is places under "v" key, and text represntation that originally comes with "_text" suffix is placed under "t" key for field "e".
 * More through output cofiguration can be set with $arrConf parameter of this function.
 * 
 * @category Data Output
 *
 * @param resource $rs - SQL server resource handle
 * @param array $arrConf - confiration array. Here is detailed description of each feature:
 * - 'flagAllowDeny' (string) - when set to 'deny', 'arrPermittedFields' contains only editable fields, and vice-versa when it's set to 'allow' (default)
 * - 'arrPermittedFields' (array) - this function can add 'rw' property with values 'rw' or 'r' property for each field in a record and it will force JavaScript function fill() to make correcspoding fields editable or not. If this array is empty and 'flagAllowDeny' is set to 'allow' this property is omitted. For example, if `$arrConf['arrPermittedFields'] == ['c']` and `$arrConf['flagAllowDeny'] == 'allow'` 'c' field will be prenected in the following way: {c: {v: 'd', rw: 'r'}}.
 * - 'fields' (array) - the array with fields configuration data. Developer can customize output of decimal fields by setting 'decimalPlaces' or 'minDecimalPlaces' values. Also user can specify href 'href' asnd its 'target' for this fields. HREFs can be formed dynamically with data from the same record. To proceed with is you need to specify field name in square brackets. Example: if `$arrConf['fields']['c']['href'] == '/page.php?a=[a]' and $arrConf['fields']['c']['target'] == '_blank'` it will return `{c: {v: d, h: '/page.php?a=b', tr: '_blank'}}`
 * - 'flagEncode' (boolean) - When true, function returns JSON-encoded string, otherwise it returns an array.
 *
 * @return array (default) or string when $arrConf['flagEncode']==True
 *
 */
function result2JSON($rs, $arrConf = array()){
    $arrConf_default = array(
        'flagAllowDeny' => 'allow'
        , 'arrPermittedFields' => array() // if 'allow', it contains only closed fields and vice-versa
        , 'arrHref' => array()
        , 'fields' => array()
        , 'flagEncode' => false
        , 'flagSimple' => false
        );
    $arrConf = array_merge($arrConf_default, $arrConf);
    $arrRet = array();
    $oSQL = $this->oSQL;
    $arrFields = $oSQL->ff($rs);

    while ($rw = $oSQL->f($rs)){
        $arrRW = array();
        if(isset($arrConf['fieldPermittedFields']) && isset($rw[$arrConf['fieldPermittedFields']])){
            $arrPermittedFields = explode(',', $rw[$arrConf['fieldPermittedFields']]);
        } else {
            $arrPermittedFields = is_array($arrConf['arrPermittedFields']) ? $arrConf['arrPermittedFields'] : array();
        }

        foreach($rw as $key=>$value){

            $type = self::getIntraDataType( $arrFields[$key]['type'], $key );
            if( $type==='real' && is_numeric($value) ){

                $decPlaces = (isset($arrConf['fields'][$key]['decimalPlaces']) && is_numeric($arrConf['fields'][$key]['decimalPlaces'])
                    ? $arrConf['fields'][$key]['decimalPlaces']
                    : (isset($arrConf['fields'][$key]['minDecimalPlaces']) && is_numeric($arrConf['fields'][$key]['minDecimalPlaces'])
                        ? self::getDecimalPlaces($value, $arrConf['fields'][$key]['minDecimalPlaces'])
                        : (isset($arrFields[$key]['decimalPlaces']) && $arrFields[$key]['decimalPlaces']<6
                            ? $arrFields[$key]['decimalPlaces']
                            : $this->conf['decimalPlaces'])
                        )

                    );

            } else {
                $decPlaces = null;
            }

            $arrRW[$key]['v'] = ($arrConf['flagSimple'] ? $value : $this->formatByType2PHP($type, $value, $decPlaces));

            if (isset($rw[$key.'_text'])){
                $arrRW[$key]['t'] = $rw[$key.'_text'];
                unset($rw[$key.'_text']);
            }

            if (($arrConf['flagAllowDeny']=='allow' && in_array($key, $arrPermittedFields))
                || ($arrConf['flagAllowDeny']=='deny' && !in_array($key, $arrPermittedFields))
                || (isset($arrConf['fields'][$key]) && (!empty($arrConf['fields'][$key]['disabled']) || !empty($arrConf['fields'][$key]['static'])))
                || !$this->arrUsrData['FlagWrite']
                ){

                $arrRW[$key]['rw'] = 'r';

            }

            if ((isset($arrConf['arrHref'][$key]) && $arrConf['arrHref'][$key]) || (isset($arrConf['fields'][$key]['href']) && $arrConf['fields'][$key]['href'])) {
                $href = (isset($arrConf['arrHref'][$key]) ? $arrConf['arrHref'][$key] : $arrConf['fields'][$key]['href']);
                $target = isset($arrConf['fields'][$key]['target']) ? $arrConf['fields'][$key]['target'] : '';
                foreach ($rw as $kkey => $vvalue){
                    if (isset($href)) {
                        $href = str_replace("[".$kkey."]", (strpos($href, "[{$kkey}]")===0 
                                    ? $vvalue // avoid urlencode() for first argument
                                    : urlencode($vvalue)), $href);
                    }
                    if (isset($target)) {
                        $target = str_replace("[".$kkey."]", $vvalue, $target);
                    }
                }
                if (isset($href)) {
                    $arrRW[$key]['h'] = $href;
                }
                $arrRW[$key]['rw'] = 'r';
                if (isset($target) && $target) {
                    $arrRW[$key]['tr'] = $target;
                }
            }
        }
        $arrRW_ = $arrRW;
        foreach($arrRW_ as $key=>$v){
            if(isset($arrRW_[$key.'_text'])){
                unset($arrRW[$key.'_text']);
            }
        }

        if($arrConf['flagSimple']){
            $r = array();
            foreach($arrRW as $key => $value){
                $r[$key] = $value['v'];
                if(isset($value['t'])){
                    $r[$key.'_text'] = $value['t'];
                }
                if(isset($value['h'])){
                    $r[$key.'_href'] = $value['h'];
                }
                if(isset($value['tr'])){
                    $r[$key.'_href_target'] = $value['tr'];
                }
            }
            $arrRet[] = $r;
        } else {
            $arrRet[] = $arrRW;
        }
    }
    return ($arrConf['flagEncode'] ? json_encode($arrRet) : $arrRet);

}

/**
 * This function unquotes SQL value previously prepared to be added into SQL code by functions like $oSQL->e(). Same exists in eiseSQL class.
 *
 * @category Data formatting
 *
 * @param string $sqlReadyValue 
 * 
 * @return string $sqlReadyValue without quotes, or NULL if source string is 'NULL' (case-insensitive)
 */
function unq($sqlReadyValue){
    return (strtoupper($sqlReadyValue)=='NULL' ? null : trim($sqlReadyValue, "'"));
}


/**
 * eiseIntra::getDecimalPlaces() gets actual number of digits beyond decimal separator. It reads original float or string value with "." (period symbol) as delimiter and returns actual number of decimal places skipping end zeros.
 * 
 * 
 * @category Data formatting
 * 
 * @param string or float $val - origin number
 * 
 * @return int - number of decimals. If $val is not numberic (i.e. it doesn't fit is_numeric() PHP function) it returns NULL.
 */
public static function getDecimalPlaces($val, $minPlaces = 0){

    if(!is_numeric($val))
        return null;

    $a = explode('.', (string)$val);

    $actPlaces = (int)strlen(@rtrim($a[1], '0'));

    return ($actPlaces > $minPlaces ? $actPlaces : $minPlaces);

}

/**
 * This function converts decimal value from user input locale into SQL-friendly value.
 * If $val is empty string it returns $valueIfNull string or 'NULL' string.
 *
 * @category Data formatting
 *
 * @param string $val - user data.
 * 
 * @return variant - double value converted from original one or $valueIfNull if it's set or 'NULL' string otherwise.
 */
function decPHP2SQL($val, $valueIfNull=null){
    return ($val!=='' 
        ? (double)str_replace($this->conf['decimalSeparator'], '.', str_replace($this->conf['thousandsSeparator'], '', $val))
        : ($valueIfNull===null ? 'NULL' : $valueIfNull)
        );
}

/**
 * This function converts data fetched from SQL query to string, according to $intra locale settings.
 *
 * @category Data formatting
 * 
 * @param variant $val - Can be either integer, double or string (anyway it will be converted to 'double') as it's been obtained from SQL or calculated in PHP.
 * @param integer $decimalPlaces - if not set, $intra->conf['decimalPlaces'] value will be used.
 *
 * @return string decimal value.
 */
function decSQL2PHP($val, $decimalPlaces=null){
    $intra = $this;
    $decPlaces = ($decimalPlaces!==null ? $decimalPlaces : self::getDecimalPlaces($val));
    return (!is_null($val) 
            ? number_format((double)$val, (int)$decPlaces, $intra->conf['decimalSeparator'], $intra->conf['thousandsSeparator'])
            : '');
}

/**
 * This function converts date value as it's been fetched from SQL ('YYYY-MM-DD' or any strtotime()-parseable format) into string accoring to $intra locale settings ($intra->conf['dateFormat'] and $intra->conf['timeFormat']). If $precision is not 'date' (e.g. 'time' or 'datetime') it will also adds a time component.
 *
 * @category Data formatting
 * 
 * @param string $dtVar - Date/time value to be converted
 * @param string $precision - precision for date conversion, 'date' is default.
 *
 * @return string - converted date or date/time value
 */
function dateSQL2PHP($dtVar, $precision='date'){
$result =  $dtVar ? date($this->conf["dateFormat"].($precision!='date' ? " ".$this->conf["timeFormat"] : ''), strtotime($dtVar)) : "";
return $result ;
}

/**
 * This function converts date value as it's been fetched from SQL ('YYYY-MM-DD' or any strtotime()-parseable format) into string accoring to $intra locale settings ($intra->conf['dateFormat'] and $intra->conf['timeFormat']). 
 *
 * @category Data formatting
 * 
 * @param string $dtVar - Date/time value to be converted
 *
 * @return string - converted date/time value
 */
function datetimeSQL2PHP($dtVar){
$result =  $dtVar ? date($this->conf["dateFormat"]." ".$this->conf["timeFormat"], strtotime($dtVar)) : "";
return $result ;
}


/**
 * This function converts date value received from user input into SQL-friendly value, quoted with single quotes. If origin value is empty string it returns $valueIfEmpty parameter or 'NULL' if it's not set. Origin value is checked for compliance to date format using regular expression $intra->conf['prgDate']. Also $dtVar format accepts <input type="date"> output formatted as 'YYYY-MM-DD' string. If $dtVar format is wrong it returns $valueIfEmpty or 'NULL' string.
 *
 * @category Data formatting
 * 
 * @param string $dtVar - origin date value
 * @param variant $valueIfEmpty - value to be returned if $dtVar is empty or badly formatted.
 *
 * @return string - Converted value ready to be added to SQL query string.
 */
function datePHP2SQL($dtVar, $valueIfEmpty="NULL"){
    $result =  (
        preg_match("/^".$this->conf["prgDate"]."$/", $dtVar) 
        ? "'".preg_replace("/".$this->conf["prgDate"]."/", $this->conf["prgDateReplaceTo"], $dtVar)."'" 
        : (
            preg_match('/^[12][0-9]{3}\-[0-9]{2}\-[0-9]{2}([ T][0-9]{1,2}\:[0-9]{2}(\:[0-9]{2}){0,1}){0,1}$/', $dtVar, $m)
            ? "'".$dtVar."'"
            : $valueIfEmpty
        )
        );
    return $result;
}

/**
 * This function converts date/time value received from user input into SQL-friendly string, quoted with single quotes. If origin value is empty string it returns $valueIfEmpty parameter or 'NULL' if it's not set. Origin value is checked for compliance to date format using regular expression $intra->conf['prgDate'] and $intra->conf['prgTime']. Time part is optional. Function also accepts 'YYYY-MM-DD[ HH:MM:SS]' string. If $dtVar format is wrong it returns $valueIfEmpty or 'NULL' string.
 *
 * @category Data formatting
 * 
 * @param string $dtVar - origin date value
 * @param variant $valueIfEmpty - value to be returned if $dtVar is empty or badly formatted.
 *
 * @return string - Converted value ready to be added to SQL query string.
 */
function datetimePHP2SQL($dtVar, $valueIfEmpty="NULL"){
    $prg = "/^".$this->conf["prgDate"]."( ".$this->conf["prgTime"]."){0,1}$/";
    $result =  (
        preg_match($prg, $dtVar) 
        ? preg_replace("/".$this->conf["prgDate"]."/", $this->conf["prgDateReplaceTo"], $dtVar) 
        : (
            preg_match('/^[12][0-9]{3}\-[0-9]{2}-[0-9]{2}( [0-9]{1,2}\:[0-9]{2}(\:[0-9]{2}){0,1}){0,1}$/', $dtVar)
            ? $dtVar
            : null 
        )
        );

    return ($result!==null ? "'".date('Y-m-d H:i:s', strtotime($result))."'" : $valueIfEmpty);
}


/**
 * getTableInfo() funiction retrieves useful MySQL table information: in addition to MySQL's 'SHOW FULL COLUMNS ...' and 'SHOW KEYS FROM ...' it also returns some PHP code that could be added to URL string, SQL queries or evaluated. See description below. Currently it uses [eiseSQL::getTableInfo()](#eisesql_gettableinfo) function.
 * 
 * @param string $dbName - database name
 * @param string $tblName - table name
 *
 * @return array - see more in [eiseSQL::getTableInfo()](#eisesql_gettableinfo) function documentation
 * 
 */
function getTableInfo($dbName, $tblName){
    
    return $this->oSQL->getTableInfo($tblName, $dbName);
    
}

/**
 *  getSQLValue() function returns ready-to-eval PHP code to be used in SQL queries. Currently kept for backward compatibility.
 * 
 * @category Data formatting
 * 
 * @param array $col - array in the same format as it's been received from [eiseSQL::getTableInfo()](#eisesql_gettableinfo) function. 'Field' member is obilgatory.
 * @param boolean $flagForArray - when set to __true__, it uses not $_POST[$col['Field']] but $_POST[$col['Field']][$i]. It is useful when we need to dispatch data list.
 *
 * @return string PHP code that could be evaluated in SQL query.
 */
function getSQLValue($col, $flagForArray=false){
    $strValue = "";
    
    $strPost = "\$_POST['".$col["Field"]."']".($flagForArray ? "[\$i]" : "");
    
    if (preg_match("/norder$/i", $col["Field"]))
        $col["DataType"] = "nOrder";

    if (preg_match("/ID$/", $col["Field"]))
        $col["DataType"] = "FK";
    
    switch($col["DataType"]){
      case "integer":
        $strValue = "'\".(integer)\$intra->decPHP2SQL($strPost).\"'";
        break;
      case "nOrder":
        $strValue = "'\".($strPost=='' ? \$i : $strPost).\"'";
        break;
      case "real":
      case "numeric":
      case "number":
        $strValue = "\".(double)\$intra->decPHP2SQL($strPost).\"";
        break;
      case "boolean":
        if (!$flagForArray)
           $strValue = "'\".($strPost=='on' ? 1 : 0).\"'";
        else
           $strValue = "'\".(integer)\$_POST['".$col["Field"]."'][\$i].\"'";
        break;
      case "binary":
        $strValue = "\".\$oSQL->e(\$".$col["Field"].").\"";
        break;
      case "datetime":
        $strValue = "\".\$intra->datetimePHP2SQL($strPost).\"";
        break;
      case "date":
        $strValue = "\".\$intra->datePHP2SQL($strPost).\"";
        break;
      case "activity_stamp":
        if (preg_match("/By$/i", $col["Field"]))
           $strValue .= "'\$intra->usrID'";
        if (preg_match("/Date$/i", $col["Field"]))
           $strValue .= "NOW()";
        break;
      case "FK":
      case "combobox":
      case "ajax_dropdown":
        $strValue = "\".($strPost!=\"\" ? \$oSQL->e($strPost) : \"NULL\").\"";
        break;
      case "PK":
      case "text":
      case "varchar":
      default:
        $strValue = "\".\$oSQL->e($strPost).\"";
        break;
    }
    return $strValue;
}

/**
 * This tiny function composes WHERE SQL condition for multiple column primary key. It's assumed that column values are  delimited with double-hash ('##'). 
 * 
 * @category Database routines
 * 
 * @param array $arrPK - Primary key array, as returned by [eiseSQL::getTableInfo()](#eisesql_gettableinfo) function, in 'PK' array member. 
 * @param string $strValue - double key value
 */
function getMultiPKCondition($arrPK, $strValue){
    $arrValue = explode("##", $strValue);
    $sql_ = "";
    for($jj = 0; $jj < count($arrPK);$jj++)
        $sql_ .= ($sql_!="" ? " AND " : "").$arrPK[$jj]."=".$this->oSQL->e($arrValue[$jj])."";
    return $sql_;
}

/**
 * This function reads data from SQL views or tables that's used as foreign key references. This function is widely used in eiseIntra as the data source for <select> elements and AJAX autocomplete (ajax_dropdown) elements. It can retrieve single record or whole recordset that match some criteria. It returns a recordset of value-text pairs with 'optValue' field that correspond to values and 'optText' field that correspond to text. Also it returns 'optTextLocal' for text representation in local language and 'optFlagDeleted' with flag that shows whether record is disabled for use or not. 
 * 
 * @category Database routines
 * 
 * @param string $strValue - value to search for; when it's specified, the function searches for records by primary key.
 * @param string $strText - text to search for - when we try to find match by text with `LIKE %..%`, e.g. for AJAX autocomplete list.
 * @param string $strTable - table or view name
 * @param string $strPrefix - 3-4-letters table field prefix. When set, it expects $strTable to have columns named as <prefix>ID, <prefix>Title, <prefix>TitleLocal and <prefix>FlagDeleted. When this parameter is empty, it expects this view to have 'optValue', 'optText', 'optTextLocal' and 'optFlagDeleted' columns. Otherwise it throws an exception from MySQL side.
 * @param boolean $flagShowDeleted - when __true__, values are not filtered with '*FlagDeleted=0'
 * @param string $extra - some extra criteria, pipe('|')-delimited string. Table/view should contain fields named like 'extra', 'extra1', 'extra2'...
 * @param boolean $flagNoLimits - when __false__, it returns only first 30 matching records. Otherwise it reutrns all matched records.
 * 
 * @return resource with data obtained from the database 
 */
function getDataFromCommonViews($strValue, $strText, $strTable, $strPrefix, $flagShowDeleted=false, $extra='', $flagNoLimits=false
    , $oSQL = null){
    
    $oSQL = ($oSQL!==null ? $oSQL : $this->oSQL);

    static $tableFieldCache = [];  

    $cacheKey = $strPrefix.'|'.$strTable;

    if(isset($tableFieldCache[$cacheKey])){

        $arrFields = $tableFieldCache[$cacheKey];

    } else {

        $f = $oSQL->ff("SELECT * FROM `{$strTable}` WHERE 1=0");
        $fields = array_keys($f);

        $titleField = (in_array("{$strPrefix}Title", $fields) ? 'Title' : 'Name');

        if ($strPrefix!=""){
            $arrFields = Array(
                "idField" => "{$strPrefix}ID"
                , "textField" => "{$strPrefix}{$titleField}"
                , "textFieldLocal" => "{$strPrefix}{$titleField}Local"
                , "orderField" => "{$strPrefix}Order"
                , "delField" => "{$strPrefix}FlagDeleted"
                , "classField" => "{$strPrefix}Class"
                , "groupField" => "{$strPrefix}Group"
                , "groupFieldLocal" => "{$strPrefix}GroupLocal"
                , "extraField" => "{$strPrefix}Extra"
                , "dataField" => "{$strPrefix}Data"
                );
        } else {
            $arrFields = Array(
                "idField" => "optValue"
                , "textField" => "optText"
                , "textFieldLocal" => "optTextLocal"
                , "orderField" => "optOrder"
                , "delField" => "optFlagDeleted"
                , "classField" => "optClass"
                , "groupField" => "optGroup"
                , "groupFieldLocal" => "optGroupLocal"
                , "extraField" => "extra"
                , "dataField" => "data"
            );
        }  
        
        if(!in_array($arrFields['textFieldLocal'], $fields))
            $arrFields['textFieldLocal'] = $arrFields['textField'];
        foreach(array_keys($arrFields) as $af){
            if(!in_array($arrFields[$af], $fields))
                unset($arrFields[$af]);
        }

        $tableFieldCache[$cacheKey] = $arrFields;

    }

    $local = isset($this->local) ? $this->local : '';
    $sql = "SELECT ".($local
            ? "(CASE WHEN IFNULL(`".$arrFields["textField{$local}"]."`, '')='' 
                THEN `".$arrFields["textField"]."` 
                ELSE `".$arrFields["textField{$local}"]."`
                END)"
            : "`".$arrFields["textField"]."`"
            )." as optText
            , `{$arrFields["idField"]}` as optValue
            ".(isset($arrFields['classField']) 
                ? ", {$arrFields["classField"]} as optClass"
                : '')."
             ".(isset($arrFields['dataField']) 
                ? ", {$arrFields["dataField"]} as optData"
                : '')."
            ".(isset($arrFields['groupField'])
                ? (($local && isset($arrFields['groupFieldLocal']))
                    ? ", (CASE WHEN IFNULL(`".$arrFields["groupField{$local}"]."`, '')='' 
                        THEN `".$arrFields["groupField"]."` 
                        ELSE `".$arrFields["groupField{$local}"]."`
                        END)" 
                    : ", `".$arrFields['groupField']."`")
                    ." as optGroup"
                : '')." 
        FROM `{$strTable}`";

    $strExtra = '';
    if ($extra!=''){
        $arrExtra = explode("|", $extra);
        foreach($arrExtra as $ix=>$ex){ 
            $ex = trim($ex);
            $strExtra .= ($ex!='' && !preg_match('/\\[\w+\]/', $ex)
                ? ' AND '.$arrFields['extraField'].($ix==0 ? '' : $ix).' = '.$oSQL->e($ex) 
                : ''); 
        }
    }
    
    if ($strValue!=="" && $strValue!==null){ // key-based search
        $sql .= "\r\nWHERE `{$arrFields["idField"]}`=".$oSQL->escape_string($strValue).$strExtra;
    } else { //value-based search
        $arrVariations = eiseIntra::getKeyboardVariations($strText);
        $sqlVariations = '';
        
        foreach($arrVariations as $layout=>$variation){
            $sqlVariations.= ($sqlVariations=='' ? '' : "\r\nOR")
                ." `{$arrFields["textField"]}` LIKE ".$oSQL->escape_string($variation, "for_search")." COLLATE 'utf8_general_ci' "
                ." OR `{$arrFields["textFieldLocal"]}` LIKE ".$oSQL->escape_string($variation, "for_search")." COLLATE 'utf8_general_ci'";
        }

        $sql .= "\r\nWHERE (\r\n{$sqlVariations}\r\n)"
            .( ($flagShowDeleted===false && !empty($arrFields["delField"])) ? " AND IFNULL(`{$arrFields["delField"]}`, 0)=0" : "")
            .$strExtra;
        if($strPrefix)
            $sql .= "\r\nORDER BY `".(isset($arrFields['orderField']) && $arrFields['orderField'] ? $arrFields['orderField'] : (isset($arrFields["textField{$local}"]) ? $arrFields["textField{$local}"] : $arrFields["textField"]))."`";
        else if ( isset($arrFields['orderField']) && $arrFields['orderField'] ) 
            $sql .= "\r\nORDER BY `{$arrFields['orderField']}`";
    }
    if(!$flagNoLimits)
        $sql .= "\r\nLIMIT 0, 30";
    $rs = $oSQL->do_query($sql);
    
    return $rs;
}

/**
 * This function recursively converts data in associative array according to type definition supplied in $types parameter. Suitable for situations when you need locale-indepedent json.
 * 
 */
public function arrPHP2SQL($arrSrc, $types = array()){
    $aInt = array();
    $arrRet = array();
    foreach((array)$arrSrc as $key=>$value){
        $flagIsText = false;
        if(is_array($value)){
            $arrRet[$key] = $this->arrPHP2SQL($value, $types);
        } else {
            switch (isset($types[$key]) ? $types[$key] : 'text') {
                case 'date':
                    $arrRet[$key] = $this->oSQL->unq($this->datePHP2SQL($value));
                    break;
                case 'datetime':
                    $arrRet[$key] = $this->oSQL->unq($this->datetimePHP2SQL($value));
                    break;
                case 'time':
                    $arrRet[$key] = (preg_match('/^[0-9]{1,2}\:[0-9]{2}(\:[0-9]{2}){0,1}$/', $value) ? $value : null);
                    break;
                case 'integer':
                    $arrRet[$key] = $this->oSQL->unq(($value == null ? 'NULL' : (int)$value));
                    break;
                case 'real':
                    $arrRet[$key] = $this->oSQL->unq($this->decPHP2SQL($value));
                    break;
                case 'FK':
                case 'select':
                case 'combobox':
                case 'ajax_dropdown':
                    $arrRet[$key] = $this->oSQL->unq($value==='' ? 'NULL' : $value);
                    break;
                case 'boolean':
                case 'checkbox':
                    $arrRet[$key] = ( $value === 'on' ? 1 : (int)$value );
                    break;
                default:
                    $arrRet[$key] = $value;
                    break;
            }
            
        } 
    }
    return $arrRet;
}
/**
 * This function also recursively converts data in associative array according to type definition supplied in $types parameter. Suitable for situations when you need to convert data according to your user locale.
 * 
 */
public function arrSQL2PHP($arrSrc, $types = array()){
    $aInt = array();
    $arrRet = array();
    foreach($arrSrc as $key=>$value){
        $flagIsText = false;
        if(is_array($value)){
            $arrRet[$key] = $this->arrSQL2PHP($value, $types);
        } else {
            $type = isset($types[$key]) ? $types[$key] : '';
            switch ($type) {
                case 'date':
                    $arrRet[$key] = $this->dateSQL2PHP($value);
                    break;
                case 'datetime':
                    $arrRet[$key] = $this->datetimeSQL2PHP($value);
                    break;
                case 'integer':
                    $arrRet[$key] = ($value === null ? '' : (int)$value);
                    break;
                case 'real':
                    $arrRet[$key] = ($value === null ? '' : (isset($this->intra) ? $this->intra->decSQL2PHP($value) : $value));
                    break;
                default:
                    $arrRet[$key] = $value;
                    break;
            }
            
        } 
    }
    return $arrRet;
}

/**
 * This function returns SQL for field values. It can be used either in UPDATE or in INSERT ... SET queries.
 * 
 */
public function getSQLFields($tableInfo, $data){

    $sqlFields = '';

    foreach($data as $field=>$value){
        if(in_array($field, $tableInfo['PK']))
            if( !($tableInfo['PKtype']=='user_defined' && $value !== null) ){
                continue;
            } 

        if(!in_array($field, $tableInfo['columns_index']))
            continue;

        $col_type = isset($tableInfo['columns_types'][$field]) ? $tableInfo['columns_types'][$field] : 'text';

        if( $value === null || (in_array($col_type, ["FK", 'time', 'datetime', 'time']) && $value==='') ){
            if($tableInfo['columns_dict'][$field]['Null']==='YES')
                $sqlFields .= "\n, {$field}=NULL";
            continue;
        }
        switch($col_type){
            case 'real':
                $sqlFields .= "\n, {$field}=".(double)$value;
                break;
            case 'integer':
            case 'boolean':
                $sqlFields .= "\n, {$field}=".(integer)$value;
                break;
            default:
                $sqlFields .= "\n, {$field}=".$this->oSQL->e($value);
                break;
        }

    }

    return $sqlFields;

}

        


/******************************************************************************/
/* ARCHIVE/RESTORE ROUTINES                                                   */
/******************************************************************************/

function getArchiveSQLObject(){
    
    if (!$this->conf["stpArchiveDB"])
        throw new Exception("Archive database name is not set. Contact system administrator.");
    
    //same server, different DBs
    $this->oSQL_arch = new eiseSQL($this->oSQL->dbhost, $this->oSQL->dbuser, $this->oSQL->dbpass, $this->conf["stpArchiveDB"], false, CP_UTF8);
    $this->oSQL_arch->connect();
    
    return $this->oSQL_arch;
    
}


function archiveTable($table, $criteria, $nodelete = false, $limit = ""){
    
    $oSQL = $this->oSQL;
    
    if (!isset($this->oSQL_arch))
        $this->getArchiveSQLObject();
    
    $oSQL_arch = $this->oSQL_arch;
    $intra_arch = new eiseIntra($oSQL_arch);
    
    // 1. check table exists in archive DB
    if(!$oSQL_arch->d("SHOW TABLES LIKE ".$oSQL->e($table))){
        // if doesnt exists, we create it w/o indexes, on MyISAM engine
        $sqlGetCreate = "SHOW CREATE TABLE `{$table}`";
        $rsC = $oSQL->q($sqlGetCreate);
        $rwC = $oSQL->f($rsC);
        $sqlCR = $rwC["Create Table"];
        //skip INDEXes and FKs
        $arrS = preg_split("/(\r|\n|\r\n)/", $sqlCR);
        $sqlCR = "";
        foreach($arrS as $ix => $string){
            if (preg_match("/^(INDEX|KEY|CONSTRAINT)/", trim($string))){
                continue;
            }
            $string = preg_replace("/(ENGINE=InnoDB)/", "ENGINE=MyISAM", $string);
            $string = preg_match("/^PRIMARY/", trim($string)) ? preg_replace("/\,$/", "", trim($string)) : $string;
            $sqlCR .= ($sqlCR!="" ? "\r\n" : "").$string;
        }
        $oSQL_arch->q($sqlCR);
        
    }
    
    // if table exists, we check it for missing columns
    $arrTable = $this->getTableInfo($oSQL->dbname, $table);
    $arrTable_arch = $intra_arch->getTableInfo($oSQL_arch->dbname, $table);
    $arrCol_arch = Array();
    foreach($arrTable_arch["columns"] as $col) $arrCol_arch[] = $col["Field"];
    $strFields = "";
    foreach($arrTable["columns"] as $col){
        //if column is missing, we add column
        if (!in_array($col["Field"], $arrCol_arch)){
            $sqlAlter = "ALTER TABLE `{$table}` ADD COLUMN `{$col["Field"]}` {$col["Type"]} ".
                ($col["Null"]=="YES" ? "NULL" : "NOT NULL").
                " DEFAULT ".($col["Null"]=="YES" ? "NULL" : $oSQL->e($col["Default"]) );
            $oSQL_arch->q($sqlAlter);
        }
        
        $strFields .= ($strFields!="" ? "\r\n, " : "")."`{$col["Field"]}`";
        
    }
    
    // 2. insert-select to archive from origin
    // presume that origin and archive are on the same host, archive user can do SELECT from origin
    $sqlIns = "INSERT IGNORE INTO `{$table}` ({$strFields})
        SELECT {$strFields}
        FROM `{$oSQL->dbname}`.`{$table}`
        WHERE {$criteria}".
        ($limit!="" ? " LIMIT {$limit}" : "");
    $oSQL_arch->q($sqlIns);
    $nAffected = $oSQL->a();
    
    // 3. delete from the origin
    if (!$nodelete)
        $oSQL->q("DELETE FROM `{$table}` WHERE {$criteria}".($limit!="" ? " LIMIT {$limit}" : ""));
    
    return $nAffected;
}





}