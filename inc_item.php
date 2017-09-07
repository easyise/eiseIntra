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
	);

public $item = array();

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
public function getSQLWhere($pkValue){

	if(count(array($pkValue))!=count($this->table['PK']))
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
	    $strPKURI = ($strPKURI!='' ? '&' : '').urlencode($pk).'='.urlencode($pkValue[$pk]);
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
		throw new Exception("Item with not found, requested ID: ".var_export($id, true), 404);

	$rw = $oSQL->f($rs);

	if($this->id)
		$this->item = $rw;

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
public function getFields(){
	return '';
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
public function update($newData){

	$intra = $this->intra;

	$this->redirectTo = $this->conf['form'].'?'.$this->getURI();
	$this->msgToUser = $intra->translate('%s is updated', $this->conf['title'.$intra->local]);

}

/**
 * To be triggered by default on DataAction=delete or REST DELETE query
 */
public function delete(){

}

}