<?php 

$arrMenuTables = Array(
                "stbl_page_role"
                , "stbl_page"
                , "stbl_role"
                );

$arrEntityTables = Array(
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


/*  common funcitons for various scripts of eiseAdmin */
/* funciton dumps tables specified in $arrTables according to $arrOptions */

function dumpTables($oSQL, $arrTables, $arrOptions=Array()){

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

        $strDump .= dumpTable($oSQL, $tableName, array_merge($arrOptions, $tableOptions));

    }

    return $strDump;

}


function dumpTable ($oSQL, $tableName, $tableOptions){

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
                $values[] = '\'' . str_replace($search, $replace, PMA_sqlAddslashes($row[$j])) . '\'';
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


function getTableHash($oSQL, $tableName){

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


/* $Id: zip.lib.php,v 2.4 2004/11/03 13:56:52 garvinhicking Exp $ */
// vim: expandtab sw=4 ts=4 sts=4:
/**
* Zip file creation class.
* Makes zip files.
*
* Based on :
*
*  <a href="http://www.zend.com/codex.php?id=535&single=1
" title="http://www.zend.com/codex.php?id=535&single=1
" rel="nofollow">http://www.zend.com/codex.php?id=535&single=1
</a> *  By Eric Mueller <eric@themepark.com>
*
*  <a href="http://www.zend.com/codex.php?id=470&single=1
" title="http://www.zend.com/codex.php?id=470&single=1
" rel="nofollow">http://www.zend.com/codex.php?id=470&single=1
</a> *  by Denis125 <webmaster@atlant.ru>
*
*  a patch from Peter Listiak <mlady@users.sourceforge.net> for last modified
*  date and time of the compressed file
*
* Official ZIP file format: <a href="http://www.pkware.com/appnote.txt
" title="http://www.pkware.com/appnote.txt
" rel="nofollow">http://www.pkware.com/appnote.txt
</a> *
* @access  public
*/
class zipfile
{
    /**
     * Array to store compressed data
     *
     * @var  array    $datasec
     */
    var $datasec      = array();

    /**
     * Central directory
     *
     * @var  array    $ctrl_dir
     */
    var $ctrl_dir     = array();

    /**
     * End of central directory record
     *
     * @var  string   $eof_ctrl_dir
     */
    var $eof_ctrl_dir = "\x50\x4b\x05\x06\x00\x00\x00\x00";

    /**
     * Last offset position
     *
     * @var  integer  $old_offset
     */
    var $old_offset   = 0;


    /**
     * Converts an Unix timestamp to a four byte DOS date and time format (date
     * in high two bytes, time in low two bytes allowing magnitude comparison).
     *
     * @param  integer  the current Unix timestamp
     *
     * @return integer  the current date in a four byte DOS format
     *
     * @access private
     */
    function unix2DosTime($unixtime = 0) {
        $timearray = ($unixtime == 0) ? getdate() : getdate($unixtime);

        if ($timearray['year'] < 1980) {
            $timearray['year']    = 1980;
            $timearray['mon']     = 1;
            $timearray['mday']    = 1;
            $timearray['hours']   = 0;
            $timearray['minutes'] = 0;
            $timearray['seconds'] = 0;
        } // end if

        return (($timearray['year'] - 1980) << 25) | ($timearray['mon'] << 21) | ($timearray['mday'] << 16) |
                ($timearray['hours'] << 11) | ($timearray['minutes'] << 5) | ($timearray['seconds'] >> 1);
    } // end of the 'unix2DosTime()' method


    /**
     * Adds "file" to archive
     *
     * @param  string   file contents
     * @param  string   name of the file in the archive (may contains the path)
     * @param  integer  the current timestamp
     *
     * @access public
     */
    function addFile($data, $name, $time = 0)
    {
        $name     = str_replace('\\', '/', $name);

        $dtime    = dechex($this->unix2DosTime($time));
        $hexdtime = '\x' . $dtime[6] . $dtime[7]
                  . '\x' . $dtime[4] . $dtime[5]
                  . '\x' . $dtime[2] . $dtime[3]
                  . '\x' . $dtime[0] . $dtime[1];
        eval('$hexdtime = "' . $hexdtime . '";');

        $fr   = "\x50\x4b\x03\x04";
        $fr   .= "\x14\x00";            // ver needed to extract
        $fr   .= "\x00\x00";            // gen purpose bit flag
        $fr   .= "\x08\x00";            // compression method
        $fr   .= $hexdtime;             // last mod time and date

        // "local file header" segment
        $unc_len = strlen($data);
        $crc     = crc32($data);
        $zdata   = gzcompress($data);
        $zdata   = substr(substr($zdata, 0, strlen($zdata) - 4), 2); // fix crc bug
        $c_len   = strlen($zdata);
        $fr      .= pack('V', $crc);             // crc32
        $fr      .= pack('V', $c_len);           // compressed filesize
        $fr      .= pack('V', $unc_len);         // uncompressed filesize
        $fr      .= pack('v', strlen($name));    // length of filename
        $fr      .= pack('v', 0);                // extra field length
        $fr      .= $name;

        // "file data" segment
        $fr .= $zdata;

        // "data descriptor" segment (optional but necessary if archive is not
        // served as file)
        // nijel(2004-10-19): this seems not to be needed at all and causes
        // problems in some cases (bug #1037737)
        //$fr .= pack('V', $crc);                 // crc32
        //$fr .= pack('V', $c_len);               // compressed filesize
        //$fr .= pack('V', $unc_len);             // uncompressed filesize

        // add this entry to array
        $this -> datasec[] = $fr;

        // now add to central directory record
        $cdrec = "\x50\x4b\x01\x02";
        $cdrec .= "\x00\x00";                // version made by
        $cdrec .= "\x14\x00";                // version needed to extract
        $cdrec .= "\x00\x00";                // gen purpose bit flag
        $cdrec .= "\x08\x00";                // compression method
        $cdrec .= $hexdtime;                 // last mod time & date
        $cdrec .= pack('V', $crc);           // crc32
        $cdrec .= pack('V', $c_len);         // compressed filesize
        $cdrec .= pack('V', $unc_len);       // uncompressed filesize
        $cdrec .= pack('v', strlen($name) ); // length of filename
        $cdrec .= pack('v', 0 );             // extra field length
        $cdrec .= pack('v', 0 );             // file comment length
        $cdrec .= pack('v', 0 );             // disk number start
        $cdrec .= pack('v', 0 );             // internal file attributes
        $cdrec .= pack('V', 32 );            // external file attributes - 'archive' bit set

        $cdrec .= pack('V', $this -> old_offset ); // relative offset of local header
        $this -> old_offset += strlen($fr);

        $cdrec .= $name;

        // optional extra field, file comment goes here
        // save to central directory
        $this -> ctrl_dir[] = $cdrec;
    } // end of the 'addFile()' method


    /**
     * Dumps out file
     *
     * @return  string  the zipped file
     *
     * @access public
     */
    function file()
    {
        $data    = implode('', $this -> datasec);
        $ctrldir = implode('', $this -> ctrl_dir);

        return
            $data .
            $ctrldir .
            $this -> eof_ctrl_dir .
            pack('v', sizeof($this -> ctrl_dir)) .  // total # of entries "on this disk"
            pack('v', sizeof($this -> ctrl_dir)) .  // total # of entries overall
            pack('V', strlen($ctrldir)) .           // size of central dir
            pack('V', strlen($data)) .              // offset to start of central dir
            "\x00\x00";                             // .zip file comment length
    } // end of the 'file()' method

} // end of the 'zipfile' class