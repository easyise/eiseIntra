<?php
include_once 'inc_item_traceable.php';

class eiseActionMatrix {

public $mtx = array();
public $mtxFields = array();
public $mtxDataFields = array();
public $mtxDataAttrs = array();
public $mtxByAction = array();
public $mtxByStatusArrive = array();
public $mtxByStatusDepart = array();
public $roles = array();

public function __construct($ent){

    GLOBAL $intra;

    $intra->cancelDataAction();

    if(is_object($ent)){
        $this->ent = $ent;
        $this->intra = $ent->intra;
        $this->oSQL = $ent->oSQL;
        $this->conf=  $ent->conf;
    } else {
        $this->ent = new eiseItemTraceable(null, array('entID'=>$ent, 'flagDontCacheConfig'=>true));
        $this->intra = $this->ent->intra;
        $this->oSQL = $this->ent->oSQL;
        $this->conf = $this->ent->conf;
    }

    $this->mtxFields = array('mtxActionID', 'mtxRoleID', 'mtxComment');
    foreach ($this->conf['ATR'] as $atr) {
    	if($atr['atrMatrix']){
    		$this->mtxFields[] = preg_replace('/^'.preg_quote($this->conf['entPrefix'], '/').'/', 'mtx', $atr['atrID']);
    		$this->mtxDataFields[] = preg_replace('/^'.preg_quote($this->conf['entPrefix'], '/').'/', 'mtx', $atr['atrID']);
    		$this->mtxDataAttrs[] = $atr['atrID'];
    	}
    }

    if($this->ent->conf['entMatrix']){
        $this->mtx = json_decode($this->ent->conf['entMatrix'], true);
    } elseif($this->oSQL->d("SHOW TABLES LIKE '{$this->ent->conf['entTable']}_matrix'")) {
        $sqlMtx = "SELECT * FROM {$this->ent->conf['entTable']}_matrix";
        $rsMtx = $this->oSQL->q($sqlMtx);
        while ($rwMtx = $this->oSQL->f($rsMtx)) {
            $rwMtx['mtxActionID'] = ($rwMtx['mtxActionID'] ? $rwMtx['mtxActionID'] : $rwMtx['mtxEventID']);
            $this->mtx[] = $rwMtx;
        }
    } else {
        $sqlRoleAction = "SELECT * FROM stbl_role_action 
            LEFT OUTER JOIN stbl_action ON actID=rlaActionID
            LEFT OUTER JOIN svw_role ON rolID=rlaRoleID
            WHERE actEntityID='{$rwEnt['entID']}'";
    }

    /* svw_role */
    if(!$this->oSQL->d("SHOW TABLES LIKE 'svw_role'")){
	    $this->oSQL->q("DROP VIEW IF EXISTS svw_role");
		$this->oSQL->q("CREATE VIEW svw_role AS 
			SELECT rolID AS optValue
					, rolFlagDeleted AS optFlagDeleted
					, cast(concat(rolID, ' - ', rolTitleLocal) as char charset utf8) collate utf8_general_ci AS optTextLocal
					, cast(concat(rolID, ' - ', rolTitle) as char charset utf8) collate utf8_general_ci AS optText
					, rolID
					, cast(concat(rolID, ' - ', rolTitle) as char charset utf8) collate utf8_general_ci AS rolTitle
					, cast(concat(rolID, ' - ', rolTitleLocal) as char charset utf8) collate utf8_general_ci AS rolTitleLocal
					, rolFlagDeleted
			FROM stbl_role");	
    }
    

    $rsRoles = $this->oSQL->q("SELECT * FROM svw_role WHERE rolFlagDeleted=0");
    while ($rwRol = $this->oSQL->f($rsRoles)) {
    	$this->roles[$rwRol['rolID']] = $rwRol['rolTitle'.$this->intra->local];
    }

    foreach ($this->mtx as $rwMTX) {
    	$this->mtxByAction[$rwMTX['mtxActionID']][] = $rwMTX;
    }

    foreach ($this->mtxByAction as $actID => $rwMTX) {
    	usort($this->mtxByAction[$actID], array(get_class($this), '_cmp_rwMTX'));
    }

    include_once eiseIntraAbsolutePath.'/grid/inc_eiseGrid.php';

    $gridMTX = new eiseGrid($this->oSQL
        , 'mtx'
        , array('arrPermissions' => Array('FlagWrite'=>$this->intra->arrUsrData['FlagWrite'])
                , 'controlBarButtons' => 'add|moveup|movedown|delete'
                )
        );

	$gridMTX->Columns[] = array('title' => '##',
		'field' => 'mtxOrder',
		'type' => 'order');
	$gridMTX->Columns[] = array('title' => __('Role'),
		'field' => 'mtxRoleID',
		'type' => 'combobox',
		'source' => 'svw_role',
		'defaultText' => __('--please set')
		);

	foreach ($this->mtxDataAttrs as $ix=>$field) {
		$atr = $this->conf['ATR'][$field];
		$col = array('title'=>$atr['atrTitle'.$this->intra->local].', '.preg_replace('/[^\<\>\=]/', '', $atr['atrMatrix'])
			, 'field'=>$this->mtxDataFields[$ix]
			, 'type'=>$atr['atrType']);
		if(in_array($atr['atrType'], array('combobox', 'select', 'ajax_dropdown'))){
			$col['source'] = $atr["atrDataSource"];
			$col['source_prefix'] = $atr["atrProgrammerReserved"];
			$col['defaultText'] = '%';
		}
		$gridMTX->Columns[] = $col;
	}

	$this->gridMTX = $gridMTX;

    // die('<pre>'.var_export($this->mtxDataFields, true));

}

function _cmp_rwMTX($a, $b){
	return ($a['mtxRoleID'] > $b['mtxRoleID'] ? 1 : -1);
}

function actionGrid($actID){
	$html = '';

	$rows = array();
	
	$gridMTX = $this->gridMTX;

	foreach ((array)$this->mtxByAction[$actID] as $rwMTX) {
		$gridMTX->Rows[] = $rwMTX;
	}

	$html .= $gridMTX->get_html();

	return $html;
}

function saveActionGrid($actID, $nd){

	$oSQL = $this->oSQL;
	$intra = $this->intra;

	$mtx = $this->mtx;

	foreach ($mtx as $ix=>$rwMTX) {
		if($rwMTX['mtxActionID']==$actID)
			unset($mtx[$ix]);
	}

	$action_matrix = $this->gridMTX->json($newData, array('flagDontEncode'=>True));
	foreach ($action_matrix as $ix => $rwMTX) {
		$action_matrix[$ix]['mtxActionID'] = $actID;
	}
	$mtx = array_merge($mtx, $action_matrix);

	$sqlMTX = "UPDATE stbl_entity SET entMatrix = ".$oSQL->e(json_encode($mtx))." WHERE entID=".$oSQL->e($this->conf['entID']);
	$oSQL->q($sqlMTX);

}

function table(){

	$oSQL = $this->oSQL;
	$intra = $this->intra;

	$html = '';

	$dstID = array();
	$dstTitle = array();

	if(isset($this->conf['STA'][0]) && $this->conf['STA'][0]['staID']!=='0'){
	// if($this->conf['STA'][0]['staID']!=='0'){
		$dstID[] = 0;
		$dstTitle[] = $strLocal ? "Новый" : "New";
	}
	
	foreach ($this->conf['STA'] as $rw) {
		if($rw['staFlagDeleted'])
			continue;
		$dstID[] = $rw["staID"];
		$dstTitle[] = $rw["staTitle{$intra->local}"];
	}
	$strTableID = md5(time());

	// die('<pre>'.var_export($this->conf['STA'], true));

	ob_start();
	?>
<table id="<?php echo $strTableID ?>" class="auth_matrix">
	<thead>
	<tr><th>Status</th>
<?php
for ($j=0; $j<count($dstID); $j++){
?>
<th><?php echo $dstID[$j],': ',$dstTitle[$j];?></th>
<?php
}
?>
	</tr>
	</thead>
	</tbody>
<?php
		for ($i=0; $i<count($dstID); $i++){
			?><tr>
				<th><?php echo $dstID[$i],': ',$dstTitle[$i];?></th><?php
				for ($j=0; $j<count($dstID); $j++){
					?><td<?php echo ($i==$j? " class=\"auth-matrix-same\"" : "");?>><?php
					$acts = array();
					foreach ($this->conf['ACT'] as $act) {
						if($act['actOldStatusID'][0]===null)
							continue;
						foreach ($act['actOldStatusID'] as $action) {
							if($action==$dstID[$i] && ($act['actNewStatusID'][0]==$dstID[$j] || ($act['actNewStatusID'][0]===null && $i==$j))) {
							$acts[] = $act;
							break;
						}
						}
						
					}
					
					if (count($acts)>0){
						?>
						<ul class='actions'>
						<?php
						foreach($acts as $rw){
								$iconClass = (preg_match('/^fa\-/', trim($rw['actButtonClass'])) ? 'fa ' : (preg_match('/^ss\_/', trim($rw['actButtonClass'])) ? 'ss_sprite ' : '')).$rw['actButtonClass'].' action-icon';
								?><li><i class="<?php echo $iconClass;?>"> </i><?php echo $rw['actFlagSystem']?'<i>':'';?><a 
								title='<?php echo $rw["actDescription$intra->local"];?>' href="action_form.php?actID=<?php echo $rw['actID'];?>"><?php 
									echo ($rw['actSQL']?'<strong>':'').$rw['actID'].': '.$rw["actTitle{$intra->local}"],($rw['actSQL']?'</strong><sup>SQL</sup>':'');?><?php echo $rw['actFlagSystem']?'</i>':'';?></a>
								<?php echo ($rw["actDescription$intra->local"] ? "<div><small>".$rw["actDescription$intra->local"]."</small></div>" : '');?>
								<ul class="roles">
									<?php
									foreach ((array)$this->mtxByAction[$rw['actID']] as $rwMTX) {
										$conditions = '';
										foreach ($this->mtxDataFields as $ix=>$field) {
											if($rwMTX[$field]!==null && $rwMTX[$field]!=='' && $rwMTX[$field]!='%'){
											// if(true){
												$atr = $this->conf['ATR'][$this->mtxDataAttrs[$ix]];
												$cndts = preg_replace('/[^\<\>\=]/', '', $atr['atrMatrix']);
												$val = (in_array($atr['atrType'], array('select', 'combobox', 'ajax_dropdown'))
													? '"'.$this->ent->getDropDownText($atr, $rwMTX[$field]).'"'
													: $rwMTX[$field]
													);
												$conditions .= ($conditions ? ', ' : '').$atr['atrTitle'.$intra->local].' '.$cndts.' '.$val;
											}
										}
										echo "<li>{$this->roles[$rwMTX['mtxRoleID']]} ({$rwMTX['mtxRoleID']})".($conditions ? ": {$conditions}" : '')."</li>\n";
									}
									?>
								</ul>
								</li>
						<?php
						}
						?>
						</ul>
						<?php
					} else {
						echo "&nbsp;";
					}
					?></td>
					<?php
				}
			?>
			</tr>
			<?php
		}
?>
</tbody>
</table>
<button onclick="SelectContent('<?php echo $strTableID;?>');">Copy table</button>
<?php	

	$html = ob_get_clean();

	return $html;
}

}