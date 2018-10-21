<?php
/**
 * This class is a shell for single table entry.
 * It has few basic properties that define title, table(s), fields, etc.
 */
class eiseItem {
	
public $conf = array(
	'title' => 'The Item'
	, 'titleLocal' => 'Штуковина'
	, 'name' => 'item'
	, 'prefix' => 'itm'
	, 'table' => 'tbl_item'
	, 'form' => 'item_form.php'
	, 'list' => 'item_list.php'
	, 'flagFormShowAllFields' => false
	);

/**
 * Basic array with item data.
 */
public $item = array();

/**
 * Historical item data obtained on initialization, before any changes made to the object.
 */
public $item_before = array();

/**
 * The array with the table information.
 */
public $table = null;

public function __construct($id = null,  $conf = array() ){

	GLOBAL $intra, $oSQL;

	if(!$conf['name']){
		throw new Exception("Item with no name cannot be created", 1);
	}

	$this->intra = (isset($conf['intra']) ? $conf['intra'] : $intra);
	$this->oSQL = (isset($conf['sql']) 
		? $conf['sql'] 
		: (isset($oSQL) 
			? $oSQL
			: $intra->oSQL
			)
		);

	$this->conf['table'] = isset($conf['table']) ? $conf['table'] : 'tbl_'.$conf['name'];
	$this->conf['form'] = isset($conf['form']) ? $conf['table'] : $conf['name'].'_form.php';
	$this->conf['list'] = isset($conf['list']) ? $conf['list'] : $conf['name'].'_list.php';
	$this->conf['title'] = isset($conf['title']) ? $conf['title'] 
		: ($intra->arrUsrData["pagTitle"]
			? $intra->arrUsrData["pagTitle"]
			: $this->conf['title']) ;
	$this->conf['titleLocal'] = isset($conf['titleLocal']) ? $conf['titleLocal'] 
		: ($intra->arrUsrData["pagTitleLocal"]
			? $intra->arrUsrData["pagTitleLocal"]
			: $this->conf['titleLocal']) ;

	$this->conf = array_merge($this->conf, $conf);

	$this->table = $this->oSQL->getTableInfo($this->conf['table']);

	$this->id = ( $id===null ? $this->getIDFromQueryString() : $id);

	$this->getData($this->id);

}

/**
 * This function gets PK (primary key) values from GET or POST query strings.
 */
public function getIDFromQueryString(){

	$arrIDs = array();

	foreach ($this->table['PK'] as $pk) {
		$arrIDs[$pk] = (isset($_POST[$pk]) ? $_POST[$pk] : $_GET[$pk]);
	}

	$this->id = ( count($this->table['PK'])==1 ? reset($arrIDs) : $arrIDs );

	return $this->id;

}

/**
 * This function returns SQL search condition basing on primary keys. 
 */
public function getSQLWhere($pkValue = null){

	if(!$pkValue)
		$pkValue = $this->id;

	if(count(array($pkValue))!=count($this->table['PK']) || !$pkValue)
		throw new Exception("Primary key error", 500);

	if( count($this->table['PK'])==1 && !is_array($pkValue) )
		$pkValue = array($this->table['PK'][0]=>$pkValue);

	$strPKCond = '';
	$strPKURI = '';

	foreach ($this->table['PK'] as $pk) {
	    $strPKCond .= ($strPKCond!='' ? ' AND ' : '')."`{$pk}` = ".(
	            in_array($this->table['columns'][$pk]["PKDataType"], Array("integer", "boolean"))
	            ? (int)$pkValue[$pk]
	            : $this->oSQL->e($pkValue[$pk])
	        );
	}

	return $strPKCond;

}


/**
 * This function returns URI for a form basing on primary keys. 
 */
public function getURI( $pkValue = null ){

	if(func_num_args()==0)
		$pkValue = $this->id;

	if(count(array($pkValue))!=count($this->table['PK']))
		throw new Exception("Primary key error", 500);

	if( count($this->table['PK'])==1 && !is_array($pkValue) )
		$pkValue = array($this->table['PK'][0]=>$pkValue);

	$strPKURI = '';

	foreach ($this->table['PK'] as $pk) {
	    $strPKURI = ($strPKURI!='' ? '&' : '').urlencode($pk).'='.(!preg_match('/^\[/', $pkValue[$pk]) // if there's []-value passed, it should return value intact, no urlencoding
	    ? urlencode($pkValue[$pk])
	    : $pkValue[$pk]);
	}

	return $strPKURI;

}


/**
 * Reads record from database table $conf['table'] associated with current $pk
 */
public function getData($pk = null){

	$oSQL = $this->oSQL;

	$id = ($pk ? $pk : $this->id);

	if(!$id)
		return;

	$where = $this->getSQLWhere($id);

	if($this->id)
		$this->sqlWhere = $where;

	$sql = "SELECT * FROM {$this->conf['table']} WHERE {$where}";
	$rs = $oSQL->q($sql);
	if($oSQL->n($rs)==0)
		throw new Exception("Item not found, requested ID: ".var_export($id, true), 404);

	$rw = $oSQL->f($rs);

	if($this->id){
		$this->item_before = $rw;
		$this->item = $rw;
	}

	return $rw;

}

/**
 * Returns form HTML. By default it contains DataAction and Primary Keys inputs
 */
public function form( $fields = null, $conf = array() ){

	$fields = $this->getPKFields().($fields 
		? $fields 
		: $this->intra->fieldset($this->intra->arrUsrData['pagTitle'.$this->intra->local], 
			$this->getFields().
			$this->intra->field(' ', null, $this->getButtons() )
			)
		);

	$conf = array_merge( array('id'=>$this->table['prefix'], 'flagAddJavaScript'=>True), $conf);

	return $this->intra->form($this->conf['form'], 'update', $fields, 'POST', $conf);

}

/**
 * Returns hidden PK fields HTML
 */
public function getPKFields(){
	$fields = '';
	foreach ($this->table['PK'] as $pk) {
		$fields .= ($fields ? "\n" : '').$this->intra->field(null, $pk, $this->item[$pk], array('type'=>'hidden'));
	}
	return $fields;
}

/**
 * Returns fields HTML
 */
public function getFields($aFields = null){
	$aToGet = ($aFields ? $aFields : ($this->conf['flagFormShowAllFields'] ? $this->table['columns_index'] : array()));
	$html = '';
	foreach($aToGet as $field){
		$col = $this->table['columns'][$field];
		$title = ($col['title'.$this->intra->local]
			? $col['title'.$this->intra->local]
			: ($col['title'] ? $col['title'] : $this->intra->translate($col['Comment']))
			);
		$conf = array_merge($col, array('type'=>(!$title ? 'hidden' : $col['DataType'])));
		if($conf['type']==='FK'){
			$conf['type'] = 'combobox';
			if($col['ref_table']){
				try {
					$tableRef = $this->oSQL->getTableInfo($col['ref_table']);	
				} catch (Exception $e) {}
			
				if($tableRef && $tableRef['prefix'] && $tableRef['prefix']!='opt'){
					$conf['source'] = $col['ref_table'];
					$conf['source_prefix'] = $tableRef['prefix'];
					$conf['type'] = 'ajax_dropdown';
				} else {
					$conf['type'] = 'combobox';
					$conf['defaultText'] = 'THIS IS DEFAULT OPTION SET';
					$conf['source'] = array('XX'=>'PLEASE REPLACE IT');
				}
			}
		}
		$html .= $this->intra->field($title, $field, $this->item[$field], $conf);
	}
	return $html;
}

/**
 * Returns HTML for buttons (submit, delete)
 */
public function getButtons(){

	return $this->intra->showButton('btnSubmit', $this->intra->translate('Update'), array('type'=>'submit')).
		$this->intra->showButton('btnDelete', $this->intra->translate('Delete'), array('type'=>'delete'));
		 
}

/**
 * To be triggered on DataAction=update or REST POST/PUT query
 */
public function insert($newData){

	$intra = $this->intra;

	$this->redirectTo = $this->conf['form'].'?'.$this->getURI();
	$this->msgToUser = $intra->translate('%s is added', $this->conf['title'.$intra->local]);

}

/**
 * To be triggered on DataAction=update or REST POST/PUT query
 */
public function update($newData){

	$intra = $this->intra;

	$this->redirectTo = $this->conf['form'].'?'.$this->getURI();
	$this->msgToUser = $intra->translate('%s is updated', $this->conf['title'.$intra->local]);

}

/**
 * To be triggered by default on DataAction=delete or REST DELETE query
 */
public function delete(){

	$intra = $this->intra;

	$sql = "DELETE FROM {$this->conf['table']} WHERE {$this->sqlWhere}";
	$this->oSQL->q($sql);

	$this->redirectTo = $this->conf['list'];
	$this->msgToUser = $intra->translate('%s is deleted', $this->conf['title'.$intra->local]);
}

/**
 * This function transforms data from the input array into SQL ans saves it. Also it calculates delta and returns it.
 */
public function updateTable($nd){

	$sqlFields = '';

	$values = array();
	$nd_src = $nd;

	// 1. convert all data from user locale to SQL locale
	// missing fields in $nd will be skipped
	foreach ($nd as $field => $value) {
		if(!isset($this->table['columns_index'][$field]))
			unset($nd[$field]);
	}

	$nd_sql = $this->intra->arrPHP2SQL($nd, $this->table['columns_types']);

	foreach($nd_sql as $field=>$value){
		if(in_array($field, $this->table['PK']))
			continue;
		if( $value === null ){
			$sqlFields .= "\n, {$field}=NULL";
			continue;
		}
		switch($this->table['columns_types'][$field]){
			case 'real':
				$sqlFields .= "\n, {$field}=".(double)$value;
				break;
			case 'integer':
				$sqlFields .= "\n, {$field}=".(integer)$value;
				break;
			default:
				$sqlFields .= "\n, {$field}=".$this->oSQL->e($value);
				break;
		}
	}

	$sql = "UPDATE {$this->conf['table']} SET ".($this->table['hasActivityStamp']
		? " {$this->conf['prefix']}EditBy='{$this->intra->usrID}', {$this->conf['prefix']}EditDate=NOW() {$sqlFields}"
		: ltrim($sqlFields, ' ,'))
		."\n WHERE ".$this->getSQLWhere();
	
	$this->oSQL->q($sql);
}

//////////////////////////////////
// File routines
//////////////////////////////////

/**
 * @category Files
 */
public function attachFile($nd){
    
    $entID = ( $this->conf['entID'] ? $this->conf['entID'] : $this->conf['prefix'] );
    $oSQL = $this->oSQL;

    $err = '';

    try {
        $filesPath = self::checkFilePath($this->intra->conf["stpFilesPath"]);
    } catch (Exception $e) {
        $error = $this->intra->translate("ERROR: file upload error: %s", $e->getMessage());
    }

    $guids = array();
    if($error==''){

        foreach($_FILES['attachment']['error'] as $ix => $err){
            if($err!=0) 
                continue;

            $f = array(
                'name'=> $_FILES['attachment']['name'][$ix]
                , 'type' => $_FILES['attachment']['type'][$ix]
                , 'size' => $_FILES['attachment']['size'][$ix]
                , 'tmp_name' =>  $_FILES['attachment']['tmp_name'][$ix]
                );

            $filGUID = $oSQL->d("SELECT UUID() as GUID");
            $filename = Date("Y/m/").$filGUID.".att";
                                
            if(!file_exists($filesPath.Date("Y/m")))
                mkdir($filesPath.Date("Y/m"), 0777, true);
            
            copy($f["tmp_name"], $filesPath.$filename);
            
            //making the record in the database
            $sqlFileInsert = "INSERT INTO stbl_file SET
                filGUID = '{$filGUID}'
                , filEntityID = '{$entID}'
                , filEntityItemID = '{$this->id}'
                , filName = '{$f["name"]}'
                , filNamePhysical = '{$filename}'
                , filLength = '{$f["size"]}'
                , filContentType = '{$f["type"]}'
                , filInsertBy='{$this->intra->usrID}', filInsertDate=NOW()
                , filEditBy='{$this->intra->usrID}', filEditDate=NOW() ";
            
            $oSQL->q($sqlFileInsert);
            
            $guids[] = $filGUID;
        }
    }
    
    $this->redirectTo = $this->conf['form'].'?'.$this->getURI();
    $this->msgToUser = ($error 
        ? $error 
        : (count($guids) ? '' : 'ERROR: ').$this->intra->translate("Files uploaded: %s ", count($guids)));

    $files = $this->getFiles(array('selectedGUIDs'=>$guids));

    return $files;

}

/**
 * This function obtains file list for current entity item
 *
 * @category Files
 */
public function getFiles($opts = array()){

	if(!$this->id)
		throw new Exception($this->intra->translate("Unable to get files for no item"), 404);

    $oSQL = $this->oSQL;
    $entID = ($this->conf['entID'] ? $this->conf['entID'] : $this->conf['prefix']);
    $entItemID = $this->id;
    $intra = $this->intra;

    $sqlFile = "SELECT *".
    	($opts['selectedGUIDs']
    		? ", CASE WHEN filGUID IN ('".implode("', '", (array)$opts['selectedGUIDs'])."') THEN 1 ELSE 0 END as selectedGUID"
    		: '')
    	." FROM stbl_file WHERE filEntityID='$entID' AND filEntityItemID='{$entItemID}'
    	ORDER BY filInsertDate DESC";
    $rsFile = $oSQL->do_query($sqlFile);

    $arrFIL = array();

    $rs = $this->oSQL->do_query($sqlFile);

    return $this->intra->result2JSON($rs, array_merge(array('arrHref'=>array('filName'=>$this->conf['form'].'?'.$this->intra->conf['dataReadKey'].'=getFile&filGUID=[filGUID]')), $opts) );
        
}

/**
 * @category Files
 */
public static function checkFilePath($filesPath){
    if(!$filesPath)
        throw new Exception('File path not set');

    if($filesPath[strlen($arrSetup['stpFilesPath'])-1]!=DIRECTORY_SEPARATOR)
        $filesPath=$filesPath.DIRECTORY_SEPARATOR;

    if(!is_dir($filesPath))
        throw new Exception('File path '.$filesPath.' is not a directory');

    return $filesPath;
}

public function getFile($q, $filePathVar = 'stpFilesPath'){

    $intra = $this->intra;
    $oSQL = $this->oSQL;

    if(!$q['filGUID'])
    	throw new Exception($this->intra->translate('File ID not set'));

    $sqlFile = "SELECT * FROM stbl_file WHERE filGUID=".$oSQL->e($q['filGUID']);
    $rsFile = $oSQL->do_query($sqlFile);

    if ($oSQL->n($rsFile)==0)
        throw new Exception($this->intra->translate('File %s not found', $q['filGUID']));

    $rwFile = $oSQL->fetch_array($rsFile);

    if(file_exists($rwFile["filNamePhysical"]))
        $fullFilePath = $rwFile["filNamePhysical"];
    else {
        $filesPath = self::checkFilePath($intra->conf[$filePathVar]);
        $fullFilePath = $filesPath.$rwFile["filNamePhysical"];
    }
        
    $intra->file($rwFile["filName"], $rwFile["filContentType"], $fullFilePath);

}

function formFiles(){

    $strRes = "<div id=\"ei_files\" class=\"eif-file-dialog\" title=\"".$this->intra->translate('Files')."\">\r\n";
    
    if ($this->intra->arrUsrData['FlagWrite']){
        $strRes .= $this->formFileAttach();
    }

    $strRes .= "<table class=\"eiseIntraFileListTable\">\r\n";
    $strRes .= "<thead>\r\n";
    $strRes .= "<tr>\r\n";
    $strRes .= "<th>".$this->intra->translate('File')."</th>\r\n";
    $strRes .= "<th colspan=\"2\">".$this->intra->translate('Uploaded')."</th>\r\n";
    $strRes .= "<th class=\"eif_filUnattach\">&nbsp;</th>\r\n";
    $strRes .= "</tr>\r\n";
    $strRes .= "</thead>\r\n";


    $strRes .= "<tbody class=\"eif_FileList\">";

    $strRes .= "<tr class=\"eif_template eif_evenodd\">\r\n";
    $strRes .= "<td><a href=\"\" class=\"eif_filName\" target=_blank></a></td>\r\n";
    $strRes .= "<td class=\"eif_filEditBy\"></td>";
    $strRes .= "<td class=\"eif_filEditDate\"></td>";
    $strRes .= "<td class=\"eif_filUnattach\"><input type=\"hidden\" class=\"eif_filGUID\"> X </td>";
    $strRes .= "</tr>";

    $strRes .= "<tr class=\"eif_notfound\">";
    $strRes .= "<td colspan=3>".$this->intra->translate("No Files Attached")."</td>";
    $strRes .= "</tr>";

    $strRes .= "<tr class=\"eif_spinner\">";
    $strRes .= "<td colspan=3></td>";
    $strRes .= "</tr>";
        
    $strRes .= "</tbody>";
    $strRes .= "</table>\r\n";
    $strRes .= "</div>\r\n";

    return $strRes;

}

function formFileAttach(){
    $entID = ($this->conf['entID'] ? $this->conf['entID'] : $this->conf['prefix']);
    $entItemID = $this->id;

    $strDiv = '';
    $strDiv .= '<form id="eif_frmAttach" action="'.$_SERVER["PHP_SELF"].'" method="POST" enctype="multipart/form-data" onsubmit="
       if (document.getElementById(\'eif_attachment\').value==\'\'){
          alert (\'File is not specified.\');
          document.getElementById(\'eif_attachment\').focus();
          return false;
       }
       var btnUpl = document.getElementById(\'eif_btnUpload\');
       btnUpl.value = \'Loading...\';
       btnUpl.disabled = true;
       return true;

    ">'."\r\n";
    $strDiv .= '<input type="hidden" name="DataAction" id="DataAction_attach" value="attachFile">'."\r\n";
    $strDiv .= '<input type="hidden" name="entID_Attach" id="entItemID_Attach" value="'.$this->entID.'">'."\r\n";
    $strDiv .= '<input type="hidden" name="entItemID_Attach" id="entItemID_Attach" value="'.$entItemID.'">'."\r\n";
    //$strDiv .= '<label>'.$this->intra->translate('Choose file').': </label>'."\r\n";
    $strDiv .= '<div class="eif-file-dropzone"><div class="eif-file-dropzone-title">'.$this->intra->translate('Drop files here or click to choose').'<i> </i></div><i class="eif-file-dropzone-spinner"> </i></div>';
    $strDiv .= '<input type="file" id="eif_attachment" class="eif-attachment" name="attachment[]" multiple style="display: none;">'."\r\n";
    $strDiv .= '<input type="submit" value="Upload" id="eif_btnUpload">'."\r\n";
    $strDiv .= '</form>'."\r\n";

    return $strDiv;
}

function formMessages(){

    $oldFlagWrite = $this->intra->arrUsrData['FlagWrite'];
    $this->intra->arrUsrData['FlagWrite'] = true;

    $entID = ($this->conf['entID'] ? $this->conf['entID'] : $this->conf['prefix']);

    $strRes = '<div id="ei_messages" title="'.$this->intra->translate('Messages').'">'."\n";

    $strRes .= '<div class="eiseIntraMessage eif_template eif_evenodd">'."\n";
    $strRes .= '<div class="eif_msgInsertDate"></div>';
    $strRes .= '<div class="eiseIntraMessageField"><label>'.$this->intra->translate('From').':</label><span class="eif_msgFrom"></span></div>';
    $strRes .= '<div class="eiseIntraMessageField"><label>'.$this->intra->translate('To').':</label><span class="eif_msgTo"></span></div>';
    $strRes .= '<div class="eiseIntraMessageField eif_invisible"><label>'.$this->intra->translate('CC').':</label><span class="eif_msgCC"></span></div>';
    $strRes .= '<div class="eiseIntraMessageField eif_invisible"><label>'.$this->intra->translate('Subject').':</label><span class="eif_msgSubject"></span></div>';
    $strRes .= '<pre class="eif_msgText"></div>';
    $strRes .= '</pre>'."\n";

    $strRes .= '<div class="eif_notfound">';
    $strRes .= '<td colspan=3>'.$this->intra->translate('No Messages Found').'</td>';
    $strRes .= '</div>';

    $strRes .= '<div class="eif_spinner">';
    $strRes .= '</div>';
    
    $strRes .= '<div class="eiseIntraMessageButtons"><input type="button" id="msgNew" value="'.$this->intra->translate('New Message').'">';
    $strRes .= '</div>';
        
    $strRes .= "</div>\r\n";

    $strRes .= '<form id="ei_message_form" title="'.$this->intra->translate('New Message').'" class="eiseIntraForm" method="POST">'."\n";
    $strRes .= '<input type="hidden" name="DataAction" id="DataAction_attach" value="sendMessage">'."\r\n";
    $strRes .= '<input type="hidden" name="entID" id="entID_Message" value="'.$entID.'">'."\r\n";
    $strRes .= '<input type="hidden" name="entItemID" id="entItemID_Message" value="'.$this->id.'">'."\r\n";
    $strRes .= '<div class="eiseIntraMessageField"><label>'.$this->intra->translate('To').':</label>'
        .$this->intra->showAjaxDropdown('msgToUserID', '', array('required'=>true, 'strTable'=>'svw_user')).'</div>';
    $strRes .= '<div class="eiseIntraMessageField"><label>'.$this->intra->translate('CC').':</label>'
        .$this->intra->showAjaxDropdown('msgCCUserID', '', array('strTable'=>'svw_user')).'</div>';
    $strRes .= '<div class="eiseIntraMessageField"><label>'.$this->intra->translate('Subject').':</label>'.$this->intra->showTextBox('msgSubject', '').'</div>';
    $strRes .= '<div class="eiseIntraMessageBody">'.$this->intra->showTextArea('msgText', '').'</div>';
    $strRes .= '<div class="eiseIntraMessageButtons"><input type="submit" id="msgPost" value="'.$this->intra->translate('Send').'">
        <input type="button" id="msgClose" value="'.$this->intra->translate('Close').'">
        </div>';
    $strRes .= "</form>\r\n";

    $this->intra->arrUsrData['FlagWrite'] = $oldFlagWrite;

    return $strRes;

}

public function getMessages(){

    $oSQL = $this->oSQL;
    $entID = ($this->conf['entID'] ? $this->conf['entID'] : $this->conf['prefix']);
    $intra = $this->intra;

    $sqlMsg = "SELECT *
    , (SELECT optText FROM svw_user WHERE optValue=msgFromUserID) as msgFrom
    , (SELECT optText FROM svw_user WHERE optValue=msgToUserID) as msgTo
    , (SELECT optText FROM svw_user WHERE optValue=msgCCUserID) as msgCC
     FROM stbl_message WHERE msgEntityID='$entID' AND msgEntityItemID='{$this->id}'
    ORDER BY msgInsertDate DESC";
    $rsMsg = $oSQL->q($sqlMsg);

    return $intra->result2JSON($rsMsg);

}

public function sendMessage($nd){

	$oSQL = $this->oSQL;
	$entID = ($this->conf['entID'] ? $this->conf['entID'] : $this->conf['prefix']);
	$intra = $this->intra;

    $fields = $oSQL->ff('SELECT * FROM stbl_message WHERE 1=0');
    if($fields['msgPassword']){
        list($login, $password) = $this->intra->decodeAuthstring($_SESSION['authstring']);
    }
    $sqlMsg = "INSERT INTO stbl_message SET
        msgEntityID = ".$oSQL->e($entID)."
        , msgEntityItemID = ".($nd['entItemID']!="" ? $oSQL->e($this->id) : "NULL")."
        , msgFromUserID = '$intra->usrID'
        , msgToUserID = ".($nd['msgToUserID']!="" ? $oSQL->e($nd['msgToUserID']) : "NULL")."
        , msgCCUserID = ".($nd['msgCCUserID']!="" ? $oSQL->e($nd['msgCCUserID']) : "NULL")."
        , msgSubject = ".$oSQL->e($nd['msgSubject'])."
        , msgText = ".$oSQL->e($nd['msgText']).
        ($fields['msgPassword'] ? ", msgPassword=".$oSQL->e($intra->encrypt($password)) : '')
        ."
        , msgSendDate = NULL
        , msgReadDate = NULL
        , msgFlagDeleted = 0
        , msgInsertBy = '$intra->usrID', msgInsertDate = NOW(), msgEditBy = '$intra->usrID', msgEditDate = NOW()";
    $oSQL->q($sqlMsg);

    $this->redirectTo = $this->conf['form'].'?'.$this->getURI();
	$this->msgToUser = $intra->translate('Message sent');

}

}