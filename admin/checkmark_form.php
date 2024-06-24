<?php
include 'common/auth.php';

$intra->requireComponent('grid');

class cChecklist extends eiseItem {

function __construct($chkID = null, $conf=array('name'=>'checklist'
    , 'table'=>'stbl_checklist'
    , 'form'=>'checkmark_form.php'
    , 'prefix'=>'chk')){   

    parent::__construct($chkID, $conf);   

    $this->conf['title'] = 'Checkmark';
    $this->conf['titleLocal'] = __('Checkmark');

    $this->initGrid();

}

function getData($pk = null){

    $intra = $this->intra;$oSQL = $this->oSQL;

    parent::getData($pk);

    if(!$pk) return;

    // put your extra code here
    $this->item['ent'] = $oSQL->f("SELECT * FROM stbl_entity WHERE entID='{$this->item['chkEntityID']}'");

    $this->mtx = json_decode($this->item['chkMatrix'], true);

    return $this->item;

}

function initGrid(){

    $oSQL = $this->oSQL;
    $intra = $this->intra;

    $entID = $this->item['ent']['entID'];

    $arrAttr = [];
    $mtxFields = [];
    $mtxDataFields = [];
    $mtxDataAttrs = [];

    $sqlATR = "SELECT * FROM stbl_attribute WHERE atrEntityID='{$entID}' ORDER BY atrOrder";
    $rsATR = $oSQL->q($sqlATR);
    while ($atr = $oSQL->f($rsATR)) {
        $arrAttr[$atr['atrID']] = $atr;
        if($atr['atrMatrix']){
            $mtxFields[] = preg_replace('/^'.preg_quote($chk->item['ent']['entPrefix'], '/').'/', 'mtx', $atr['atrID']);
            $mtxDataFields[] = preg_replace('/^'.preg_quote($chk->item['ent']['entPrefix'], '/').'/', 'mtx', $atr['atrID']);
            $mtxDataAttrs[] = $atr['atrID'];
        }
    }

    $this->gridMTX = new eiseGrid($oSQL
        , 'mtx'
        , array('arrPermissions' => Array('FlagWrite'=>$intra->arrUsrData['FlagWrite'])
                , 'controlBarButtons' => 'add|moveup|movedown|delete'
                )
        );

    $this->gridMTX->Columns[] = array('title' => '##',
        'field' => 'mtxOrder',
        'type' => 'order');


    foreach ($mtxDataAttrs as $ix=>$field) {
        $atr = $arrAttr[$field];
        $col = array('title'=>$atr['atrTitle'.$intra->local].', '.preg_replace('/[^\<\>\=]/', '', $atr['atrMatrix'])
            , 'field'=>$mtxDataFields[$ix]
            , 'type'=>$atr['atrType']);
        if(in_array($atr['atrType'], array('combobox', 'select', 'ajax_dropdown'))){
            $col['source'] = $atr["atrDataSource"];
            $col['source_prefix'] = $atr["atrProgrammerReserved"];
            $col['defaultText'] = '%';
        }
        $this->gridMTX->Columns[] = $col;
    }

    $this->gridMTX->Columns[] = array('title' => __('Rule'),
        'field' => 'mtxRule',
        'type' => 'combobox',
        'mandatory' => true,
        'source' => array('1'=>__('Require'), '2'=>__('Disable'))
    );

}

public function update($nd){

    $nd['chkMatrix'] = $this->gridMTX->json();

    $this->updateTable($nd);

    parent::update($nd);

}

}

$chk = new cChecklist($_POST['chkID'] ? $_POST['chkID'] : $_GET['chkID']);

$entID = $chk->item['ent']['entID'];

$intra->dataRead(array(), $chk);

$intra->dataAction(array('update', 'delete'), $chk, $_POST);

$arrActions[]= Array ('title' => __('Back to "%s"', $chk->item['ent']['entTitle'.$intra->local.'Mul'])
       , 'action' => 'entity_form.php?entID='.urlencode($entID)
       , 'class'=> 'ss_arrow_left'
    );

include eiseIntraAbsolutePath.'inc_top.php';

list($arrStatuses_dropdown, $_) = $intra->getStatuses_dropdown($entID);
$arrActions_dropdown = $intra->getActions_dropdown($entID);

$chk->gridMTX->Rows = $chk->mtx;

$fields = $intra->field(__('Title'), 'chkTitleLocal', $chk->item["chkTitleLocal"])
    .$intra->field(__('Title (Eng.)'), 'chkTitle', $chk->item["chkTitle"])
    .$intra->field(__('Target Status'), 'chkTargetStatusID', $chk->item["chkTargetStatusID"], array('type'=>'select', 'source'=>$arrStatuses_dropdown))
    .$intra->field(__('Status Clears checkmark'), 'chkClearStatusID', $chk->item["chkClearStatusID"], array('type'=>'select', 'source'=>$arrStatuses_dropdown))
    .$intra->field(__('Action Sets checkmark'), 'chkSetActionID', $chk->item["chkSetActionID"], array('type'=>'select', 'source'=>$arrActions_dropdown))
    .$intra->field(__('Action Clears checkmark'), 'chkClearActionID', $chk->item["chkClearActionID"], array('type'=>'select', 'source'=>$arrActions_dropdown))   
    .$intra->field(__('Checkmark matrix'), null, $chk->gridMTX->get_html())
    .$intra->field(__('Deleted?'), 'chkFlagDeleted', $chk->item["chkFlagDeleted"], array('type'=>'boolean'))
;

$fields = $intra->fieldset(__('"%s": "%s"', $chk->item['ent']['entTitle'.$intra->local.'Mul'], $chk->item["chkTitle{$intra->local}"]), $fields.
            $intra->field(' ', null, $chk->getButtons() )
            );

echo $chk->form($fields);

?>
<script type="text/javascript">
$(document).ready(function(){
    $('.eiseGrid').eiseGrid();
})


</script>

<?php

include eiseIntraAbsolutePath.'inc_bottom.php';