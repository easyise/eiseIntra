<?php
/**
 *
 * eiseIntraData class that encapsulates data handling routines
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
	
public $arrAttributeTypes = array(
    "integer" => 'integer'
    , "real" => 'real'
    , "boolean" => 'checkbox'
    , "text" => 'text'
    , "textarea" => 'text'
//    , "binary" => 'file' #not supported yet for docflow apps
    , "date" => 'date'
    , "datetime" => 'datetime'
//    , "time" => 'time'
    , "combobox" => 'FK'
    , "ajax_dropdown" => 'FK'
    );


/**
 * $arrBasicDataTypes is used to convert data from user input locale (e.g. en_US) into SQL-friendly values.
 * It provides unique match from any possible type name (values) into basic type (key) that data will be converted to.
 * This array is used in eiseIntraData::getBasicDataType() function.
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
 * @param string $type - data type, according to eiseIntra::$arrUserDataType
 * @param variant $value - data as it's been returned from the database or calculated in PHP
 * @param int $decPlaces - number of decimal places
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
            $retVal = $this->datetimeSQL2PHP( date('Y-m-d H:i:s', strtotime($value)) );
            break;
        case 'time':
        default:
            $retVal = (string)$value;
            break;
    }
    
    return $retVal;
}

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


function result2JSON($rs, $arrConf = array()){
    $arrConf_default = array(
        'flagAllowDeny' => 'allow'
        , 'arrPermittedFields' => array() // if 'allow', it contains only closed fields and vice-versa
        , 'arrHref' => array()
        , 'fields' => array()
        , 'flagEncode' => false
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

                $decPlaces = (is_numeric($arrConf['fields'][$key]['decimalPlaces'])
                    ? $arrConf['fields'][$key]['decimalPlaces']
                    : (is_numeric($arrConf['fields'][$key]['minDecimalPlaces'])
                        ? self::getDecimalPlaces($value, $arrConf['fields'][$key]['minDecimalPlaces'])
                        : ($arrFields[$key]['decimalPlaces']<6
                            ? $arrFields[$key]['decimalPlaces']
                            : $intra->conf['decimalPlaces'])
                        )

                    );

            } else {
                $decPlaces = null;
            }

            $arrRW[$key]['v'] = $this->formatByType2PHP($type, $value, $decPlaces);

            if (isset($rw[$key.'_text'])){
                $arrRW[$key]['t'] = $rw[$key.'_text'];
                unset($rw[$key.'_text']);
            }

            if (($arrConf['flagAllowDeny']=='allow' && in_array($key, $arrPermittedFields))
                || ($arrConf['flagAllowDeny']=='deny' && !in_array($key, $arrPermittedFields))
                || $arrConf['fields'][$key]['disabled'] || $arrConf['fields'][$key]['static']
                || !$this->arrUsrData['FlagWrite']
                ){

                $arrRW[$key]['rw'] = 'r';

            }

            if (isset($arrConf['arrHref'][$key]) || $arrConf['fields'][$key]['href']){
                $href = ($arrConf['arrHref'][$key] ? $arrConf['arrHref'][$key] : $arrConf['fields'][$key]['href']);
                $target = $arrConf['fields'][$key]['target'];
                foreach ($rw as $kkey => $vvalue){
                    $href = str_replace("[".$kkey."]", (strpos($cell['href'], "[{$rowKey}]")==0 
                                ? $vvalue // avoid urlencode() for first argument
                                : urlencode($vvalue)), $href);
                    $target = str_replace("[".$kkey."]", $vvalue, $target);
                }
                $arrRW[$key]['h'] = $href;
                $arrRW[$key]['rw'] = 'r';
                if ($target) {
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

        $arrRet[] = $arrRW;
    }
    return ($arrConf['flagEncode'] ? json_encode($arrRet) : $arrRet);

}

/**
 * This function unquotes SQL value previously prepared to be added into SQL code by functions like $oSQL->e(). Same exists in eiseSQL class.
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
 * @param variant $val - Can be either integer, double or string (anyway it will be converted to 'double') as it's been obtained from SQL or calculated in PHP.
 * @param integer $decimalPlaces - if not set, $intra->conf['decimalPlaces'] value will be used.
 *
 * @return string decimal value.
 */
function decSQL2PHP($val, $decimalPlaces=null){
    $decPlaces = ($decimalPlaces!==null ? $decimalPlaces : self::getDecimalPlaces($val));
    return (!is_null($val) 
            ? number_format((double)$val, (int)$decimalPlaces, $intra->conf['decimalSeparator'], $intra->conf['thousandsSeparator'])
            : '');
}

/**
 * This function converts date value as it's been fetched from SQL ('YYYY-MM-DD' or any strtotime()-parseable format) into string accoring to $intra locale settings ($intra->conf['dateFormat'] and $intra->conf['timeFormat']). If $precision is not 'date' (e.g. 'time' or 'datetime') it will also adds a time component.
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
            preg_match('/^[12][0-9]{3}\-[0-9]{2}-[0-9]{2}([ T][0-9]{1,2}\:[0-9]{2}(\:[0-9]{2}){0,1}){0,1}$/', $dtVar)
            ? "'".$dtVar."'"
            : $valueIfEmpty 
        )
        );
    return $result;
}

/**
 * This function converts date/time value received from user input into SQL-friendly string, quoted with single quotes. If origin value is empty string it returns $valueIfEmpty parameter or 'NULL' if it's not set. Origin value is checked for compliance to date format using regular expression $intra->conf['prgDate'] and $intra->conf['prgTime']. Time part is optional. Function also accepts 'YYYY-MM-DD[ HH:MM:SS]' string. If $dtVar format is wrong it returns $valueIfEmpty or 'NULL' string.
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
 * Funiction retrieves MySQL table information with eiseIntra's semantics
 *
 */
function getTableInfo($dbName, $tblName){
    
    return $this->oSQL->getTableInfo($tblName, $dbName);
    
    $arrPK = Array();

    $rwTableStatus=$oSQL->f($oSQL->q("SHOW TABLE STATUS FROM $dbName LIKE '".$tblName."'"));
    if($rwTableStatus['Comment']=='VIEW' && $rwTableStatus['Engine']==null){
        $tableType = 'view';
    } else {
        $tableType = 'table';
    }

    
    $sqlCols = "SHOW FULL COLUMNS FROM `".$tblName."`";
    $rsCols  = $oSQL->do_query($sqlCols);
    $ii = 0;
    while ($rwCol = $oSQL->fetch_array($rsCols)){
        
        if ($ii==0)
            $firstCol = $rwCol["Field"];
        
        $strPrefix = (isset($strPrefix) && $strPrefix==substr($rwCol["Field"], 0, 3) 
            ? substr($rwCol["Field"], 0, 3)
            : (!isset($strPrefix) ? substr($rwCol["Field"], 0, 3) : "")
            );
        
        if (preg_match("/int/i", $rwCol["Type"]))
            $rwCol["DataType"] = "integer";
        
        if (preg_match("/float/i", $rwCol["Type"])
           || preg_match("/double/i", $rwCol["Type"])
           || preg_match("/decimal/i", $rwCol["Type"]))
            $rwCol["DataType"] = "real";
        
        if (preg_match("/tinyint/i", $rwCol["Type"])
            || preg_match("/bit/i", $rwCol["Type"]))
            $rwCol["DataType"] = "boolean";
        
        if (preg_match("/char/i", $rwCol["Type"])
           || preg_match("/text/i", $rwCol["Type"]))
            $rwCol["DataType"] = "text";
        
        if (preg_match("/binary/i", $rwCol["Type"])
           || preg_match("/blob/i", $rwCol["Type"]))
            $rwCol["DataType"] = "binary";
            
        if (preg_match("/date/i", $rwCol["Type"])
           || preg_match("/time/i", $rwCol["Type"]))
            $rwCol["DataType"] = $rwCol["Type"];
            
        if (preg_match("/ID$/", $rwCol["Field"]) && $rwCol["Key"] != "PRI"){
            $rwCol["FKDataType"] = $rwCol["DataType"];
            $rwCol["DataType"] = "FK";
        }
        
        if ($rwCol["Key"] == "PRI" 
                || preg_match("/^$strPrefix(GU){0,1}ID$/i",$rwCol["Field"])
            ){
            $rwCol["PKDataType"] = $rwCol["DataType"];
            $rwCol["DataType"] = "PK";
        }
        
        if ($rwCol["Field"]==$strPrefix."InsertBy" 
          || $rwCol["Field"]==$strPrefix."InsertDate" 
          || $rwCol["Field"]==$strPrefix."EditBy" 
          || $rwCol["Field"]==$strPrefix."EditDate" ) {
            $rwCol["DataType"] = "activity_stamp"; 
            $arrTable['hasActivityStamp'] = true;
        }
        $arrCols[$rwCol["Field"]] = $rwCol;
        if ($rwCol["Key"] == "PRI"){
            $arrPK[] = $rwCol["Field"];
            if ($rwCol["Extra"]=="auto_increment")
                $pkType = "auto_increment";
            else 
                if (preg_match("/GUID$/", $rwCol["Field"]) && preg_match("/^(varchar)|(char)/", $rwCol["Type"]))
                    $pkType = "GUID";
                else 
                    $pkType = "user_defined";
        }
        $ii++;
    }
    
    if (count($arrPK)==0)
        $arrPK[] = $arrCols[$firstCol]['Field'];
    
    $sqlKeys = "SHOW KEYS FROM `".$tblName."`";
    $rsKeys  = $oSQL->do_query($sqlKeys);
    while ($rwKey = $oSQL->fetch_array($rsKeys)){
      $arrKeys[] = $rwKey;
    }
    
    //foreign key constraints
    $rwCreate = $oSQL->fetch_array($oSQL->do_query("SHOW CREATE TABLE `{$tblName}`"));
    $strCreate = $rwCreate["Create Table"];
    $arrCreate = explode("\n", $strCreate);$arrCreateLen = count($arrCreate);
    for($i=0;$i<$arrCreateLen;$i++){
        // CONSTRAINT `FK_vhcTypeID` FOREIGN KEY (`vhcTypeID`) REFERENCES `tbl_vehicle_type` (`vhtID`)
        if (preg_match("/^CONSTRAINT `([^`]+)` FOREIGN KEY \(`([^`]+)`\) REFERENCES `([^`]+)` \(`([^`]+)`\)/", trim($arrCreate[$i]), $arrConstraint)){
            foreach($arrCols as $idx=>$col){
                if ($col["Field"]==$arrConstraint[2]) { //if column equals to foreign key constraint
                    $arrCols[$idx]["DataType"]="FK";
                    $arrCols[$idx]["ref_table"] = $arrConstraint[3];
                    $arrCols[$idx]["ref_column"] = $arrConstraint[4];
                    break;
                }
            }
            /*
            echo "<pre>";
            print_r($arrConstraint);
            echo "</pre>";
            //*/
        }
    }
    
    $arrColsIX = Array();
    foreach($arrCols as $ix => $col){ $arrColsIX[$col["Field"]] = $col["Field"]; }
    
    $strPKVars = $strPKCond = $strPKURI = '';
    foreach($arrPK as $pk){
        $strPKVars .= "\${$pk}  = (isset(\$_POST['{$pk}']) ? \$_POST['{$pk}'] : \$_GET['{$pk}'] );\r\n";
        $strPKCond .= ($strPKCond!="" ? " AND " : "")."`{$pk}` = \".".(
                in_array($arrCols["DataType"], Array("integer", "boolean"))
                ? "(int)(\${$pk})"
                : "\$oSQL->e(\${$pk})"
            ).".\"";
        $strPKURI .= ($strPKURI!="" ? "&" : "")."{$pk}=\".urlencode(\${$pk}).\"";
    }
    
    $arrTable['columns'] = $arrCols;
    $arrTable['keys'] = $arrKeys;
    $arrTable['PK'] = $arrPK;
    $arrTable['PKtype'] = $pkType;
    $arrTable['prefix'] = $strPrefix;
    $arrTable['table'] = $tblName;
    $arrTable['columns_index'] = $arrColsIX;
    
    $arrTable["PKVars"] = $strPKVars;
    $arrTable["PKCond"] = $strPKCond;
    $arrTable["PKURI"] = $strPKURI;

    $arrTable['type'] = $tableType;

    $arrTable['Comment'] = $rwTableStatus['Comment'];
    
    return $arrTable;
}


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

function getMultiPKCondition($arrPK, $strValue){
    $arrValue = explode("##", $strValue);
    $sql_ = "";
    for($jj = 0; $jj < count($arrPK);$jj++)
        $sql_ .= ($sql_!="" ? " AND " : "").$arrPK[$jj]."=".$this->oSQL->e($arrValue[$jj])."";
    return $sql_;
}


function getDataFromCommonViews($strValue, $strText, $strTable, $strPrefix, $flagShowDeleted=false, $extra='', $flagNoLimits=false){
    
    $oSQL = $this->oSQL;

    if ($strPrefix!=""){
        $arrFields = Array(
            "idField" => "{$strPrefix}ID"
            , "textField" => "{$strPrefix}Title"
            , "textFieldLocal" => "{$strPrefix}TitleLocal"
            , "delField" => "{$strPrefix}FlagDeleted"
            );
    } else {
        $arrFields = Array(
            "idField" => "optValue"
            , "textField" => "optText"
            , "textFieldLocal" => "optTextLocal"
            , "delField" => "optFlagDeleted"
        );
    }    
    
    $sql = "SELECT `".$arrFields["textField{$this->local}"]."` as optText, `{$arrFields["idField"]}` as optValue
        FROM `{$strTable}`";
    
    if ($strValue!=""){ // key-based search
        $sql .= "\r\nWHERE `{$arrFields["idField"]}`=".$oSQL->escape_string($strValue);
    } else { //value-based search
        $strExtra = '';
        if ($extra!=''){
            $arrExtra = explode("|", $extra);
            foreach($arrExtra as $ix=>$ex){ 
                $ex = trim($ex);
                $strExtra .= ($ex!='' 
                    ? ' AND extra'.($ix==0 ? '' : $ix).' = '.$oSQL->e($ex) 
                    : ''); 
            }
        }

        $arrVariations = eiseIntra::getKeyboardVariations($strText);
        $sqlVariations = '';
        
        foreach($arrVariations as $layout=>$variation){
            $sqlVariations.= ($sqlVariations=='' ? '' : "\r\nOR")
                ." `{$arrFields["textField"]}` LIKE ".$oSQL->escape_string($variation, "for_search")." COLLATE 'utf8_general_ci' "
                ." OR `{$arrFields["textFieldLocal"]}` LIKE ".$oSQL->escape_string($variation, "for_search")." COLLATE 'utf8_general_ci'";
        }

        $sql .= "\r\nWHERE (\r\n{$sqlVariations}\r\n)"
            .($flagShowDeleted==false ? " AND IFNULL(`{$arrFields["delField"]}`, 0)=0" : "")
            .$strExtra;
        $sql .= "\r\nORDER BY `".$arrFields["textField{$this->local}"]."`";
    }
    if(!$flagNoLimits)
        $sql .= "\r\nLIMIT 0, 30";
    $rs = $oSQL->do_query($sql);
    
    return $rs;
}

/******************************************************************************/
/* ARCHIVE/RESTORE ROUTINES                                                   */
/******************************************************************************/

function getArchiveSQLObject(){
    
    if (!$this->conf["stpArchiveDB"])
        throw new Exception("Archive database name is not set. Contact system administrator.");
    
    //same server, different DBs
    $this->oSQL_arch = new sql($this->oSQL->dbhost, $this->oSQL->dbuser, $this->oSQL->dbpass, $this->conf["stpArchiveDB"], false, CP_UTF8);
    $this->oSQL_arch->connect();
    
    return $this->oSQL_arch;
    
}


function archiveTable($table, $criteria, $nodelete = false, $limit = ""){
    
    $oSQL = $this->oSQL;
    
    if (!isset($this->oSQL_arch))
        $this->getArvhiceSQLObject();
    
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