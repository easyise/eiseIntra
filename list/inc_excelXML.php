<?php
/**
 * Simple excel generating from PHP5
 *
 * @package Utilities
 * @license http://www.opensource.org/licenses/mit-license.php
 * @author Oliver Schwarz <oliver.schwarz@gmail.com>
 * @version 1.0
 */

/**
 * Generating excel documents on-the-fly from PHP5
 * 
 * Uses the excel XML-specification to generate a native
 * XML document, readable/processable by excel.
 * 
 * @package Utilities
 * @subpackage Excel
 * @author Oliver Schwarz <oliver.schwarz@vaicon.de>
 * @version 1.1
 * 
 * @todo Issue #4: Internet Explorer 7 does not work well with the given header
 * @todo Add option to give out first line as header (bold text)
 * @todo Add option to give out last line as footer (bold text)
 * @todo Add option to write to file
 *
 * modified 2013 by Ilya Eliseev for eiseList
 * class renamed to excelXML
 */
class excelXML
{
    
    const DS = DIRECTORY_SEPARATOR;
    
    /**
     * Header (of document)
     * @var string
     */
    /*private $header = "<?xml version=\"1.0\" encoding=\"%s\"?\>\n<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\" xmlns:x=\"urn:schemas-microsoft-com:office:excel\" xmlns:ss=\"urn:schemas-microsoft-com:office:spreadsheet\" xmlns:html=\"http://www.w3.org/TR/REC-html40\">";*/
    private $header = "<?xml version=\"1.0\" encoding=\"%s\"?>\n<?mso-application progid=\"Excel.Sheet\"?>\n<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\" xmlns:o=\"urn:schemas-microsoft-com:office:office\" xmlns:x=\"urn:schemas-microsoft-com:office:excel\" xmlns:ss=\"urn:schemas-microsoft-com:office:spreadsheet\" xmlns:html=\"http://www.w3.org/TR/REC-html40\">";

    /**
     * Styles (of document)
     * @var string
     */
    private $styles = "<Styles>".
        "<Style ss:ID=\"Hdr\"><Font ss:Bold=\"1\"/></Style>"
        .'<Style ss:ID="s22"><NumberFormat ss:Format="Short Date"/></Style>'
        // .'<Style ss:ID="s23"><NumberFormat ss:Format="[$-419]d\ mmm\ yy;@"/>/Style>'
        ."</Styles>";

    /**
     * Header row style
     * @var string
     */
    private $hstyle = "ss:StyleID=\"Hdr\"";

    /**
     * Footer (of document)
     * @var string
     */
    private $footer = "</Workbook>";

    /**
     * Lines to output in the excel document
     * @var array
     */
    private $lines = array();

    /**
     * Used encoding
     * @var string
     */
    private $sEncoding;
    
    /**
     * Convert variable types
     * @var boolean
     */
    private $bConvertTypes;
    
    /**
     * Style first row as header
     * @var boolean
     */
    private $bStyleHeader;
    
    /**
     * Worksheet title
     * @var string
     */
    private $sWorksheetTitle;

    /**
     * Constructor
     * 
     * The constructor allows the setting of some additional
     * parameters so that the library may be configured to
     * one's needs.
     * 
     * On converting types:
     * When set to true, the library tries to identify the type of
     * the variable value and set the field specification for Excel
     * accordingly. Be careful with article numbers or postcodes
     * starting with a '0' (zero)!
     * 
     * @param string $sEncoding Encoding to be used (defaults to UTF-8)
     * @param boolean $bConvertTypes Convert variables to field specification
     * @param boolean $bStyleHeader Style first row as header
     * @param string $sWorksheetTitle Title for the worksheet
     */
    public function __construct($sEncoding = 'UTF-8', $bConvertTypes = true, $sWorksheetTitle = 'Table1')
    {
        $this->bConvertTypes = $bConvertTypes;
        $this->bStyleHeader = $bStyleHeader;
        $this->setEncoding($sEncoding);
        $this->setWorksheetTitle($sWorksheetTitle);
    }
    
    /**
     * Set encoding
     * @param string Encoding type to set
     */
    public function setEncoding($sEncoding)
    {
        $this->sEncoding = $sEncoding;
    }

    /**
     * Set worksheet title
     * 
     * Strips out not allowed characters and trims the
     * title to a maximum length of 31.
     * 
     * @param string $title Title for worksheet
     */
    public function setWorksheetTitle ($title)
    {
            $title = preg_replace ("/[\\\|:|\/|\?|\*|\[|\]\']/", "", $title);
            $title = substr ($title, 0, 31);
            $this->sWorksheetTitle = $title;
    }

    /**
     * Add row
     * 
     * Adds a single row to the document. If set to true, self::bConvertTypes
     * checks the type of variable and returns the specific field settings
     * for the cell.
     * 
     * @param array $array One-dimensional array with row content
     */
    public function addRow($array, $types = null) {
        $cells = "";

        foreach ($array as $k => $v) {
            $type = 'String';
            $style = '';
            if ($this->bConvertTypes === true){
                if($types){
                    $srcType = strtolower($types[$k]);
                    switch ($srcType) {
                        case 'date':
                        case 'datetime':
                            if($v){
                                $v = date('Y-m-d\TH:i:s.000', strtotime($v));
                                $style = ' ss:StyleID="s22"';
                                $type = "DateTime";
                            }                            
                            break;
                        case 'real':
                        case 'int':
                        case 'integer':
                        case 'number':
                        case 'money':
                        case 'decimal':
                            $type = 'Number';
                            break;
                        default:
                            break;
                    }
                } else {
                    if( (is_numeric($v) && strlen($v)<16)) {
                        $type = 'Number';
                    }
                }
                
            }
            $v = htmlentities($v, ENT_COMPAT | ENT_XML1 | ENT_SUBSTITUTE, $this->sEncoding);
            $cells .= "<Cell{$style}><Data ss:Type=\"$type\">" . $v . "</Data></Cell>\n"; 
        }

        $this->lines[] = "<Row>\n" . $cells . "</Row>\n";
    }

    /**
     * Add an array to the document
     * @param array $array One-dimensional array with header content
     */
    public function addHeader($array) {
        $cells = "";

        foreach ($array as $k => $v) {
            $type = 'String';
            $cells .= "<Cell><Data ss:Type=\"$type\">" . $v . "</Data></Cell>\n"; 
        }

        $this->lines = array("<Row " . $this->hstyle . ">\n" . $cells . "</Row>\n") + $this->lines;
        $this->bStyleHeader = true;
    }

    /**
     * Add header row to the document
     * @param array 2-dimensional array
     */
    public function addArray ($array) {
        foreach ($array as $k => $v) {
            $this->addRow($v);
        }
    }


    /**
     * Generate the excel file
     * @param string $filename Name of excel file to generate (...xls)
     */
    public function generateXML ($filename = 'excel-export') {
        // correct/validate filename
        $filename = preg_replace('/[^aA-zZ0-9\_\-]/', '', $filename);
    
        // deliver header (as recommended in php manual)
        header("Content-Type: application/vnd.ms-excel; charset=" . $this->sEncoding);
        header("Content-Disposition: attachment; filename=\"" . $filename . ".xls\"");
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");

        // print out document to the browser
        // need to use stripslashes for the damn ">"
        echo stripslashes (sprintf($this->header, $this->sEncoding));
        if ($this->bStyleHeader === true) {
            echo "\n" . $this->styles;
        }
        echo "\n<Worksheet ss:Name=\"" . $this->sWorksheetTitle . "\">\n<Table>\n";
        foreach ($this->lines as $line) {
            echo $line;
        }

        echo "</Table>\n</Worksheet>\n";
        echo $this->footer;
    }
    
public function Output($fileName = "", $dest = "D") {
    
    $strOut = stripslashes (sprintf($this->header, $this->sEncoding));
    if ($this->bStyleHeader === true) {
        $strOut .= "\n" . $this->styles;
    }
    $strOut .= "\n<Worksheet ss:Name=\"" . $this->sWorksheetTitle . "\">\n<Table>\n";
    foreach ($this->lines as $line) {
        $strOut .= $line;
    }
    
    $strOut .= "</Table>\n</Worksheet>\n";
    $strOut .= $this->footer;
    
    switch ($dest){
        case "I":
        case "D":
            
            if( ini_get('zlib.output_compression') ) { 
                ini_set('zlib.output_compression', 'Off'); 
            }

            // http://ca.php.net/manual/en/function.header.php#76749
            header('Pragma: public'); 
            header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");                  // Date in the past    
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); 
            header('Cache-Control: no-store, no-cache, must-revalidate');     // HTTP/1.1 
            header('Cache-Control: pre-check=0, post-check=0, max-age=0');    // HTTP/1.1 
            header("Pragma: no-cache"); 
            header("Expires: 0"); 
            header('Content-Transfer-Encoding: none'); 
            header('Content-Type: application/vnd.ms-excel;');                 // This should work for IE & Opera 
//            header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
            header("Content-type: application/x-msexcel");                    // This should work for the rest 
//            header("Content-type: text/xml");                    // This should work for the rest 
            if ($dest=="I"){
                header('Content-Disposition: inline"');
            }
            if ($dest=="D"){
                header('Content-Disposition: attachment; filename="' . basename($fileName) . '.xls"');
            }
            echo $strOut;
            die();
        case "S":
            return $strOut;
        case "F": // not supported 
        default:
            break;
    }
    
}
    
    
    

}

?>