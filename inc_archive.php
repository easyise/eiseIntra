<?php
class eiseIntraArchive {
	
	public static function checkArchiveTable ( $oSQL_arch, $ent ){
	    
	    GLOBAL $oSQL, $intra;
	    
	    // 1. check table structure in archive database by attributes
	    // get archive table info
	    $arrTable = Array("columns" => Array());
		if (!$oSQL_arch->d("SHOW TABLES LIKE '{$ent->conf["entTable"]}'")){
	        $sqlCreate = "CREATE TABLE `{$ent->conf['entTable']}` (
	          `{$ent->conf['entID']}ID` VARCHAR(50) NOT NULL
	            , `{$ent->conf['entID']}StatusID` INT(11) NOT NULL DEFAULT 0
	            , `{$ent->conf['entID']}StatusTitle` VARCHAR(256) NOT NULL DEFAULT ''
	            , `{$ent->conf['entID']}StatusTitleLocal` VARCHAR(256) NOT NULL DEFAULT ''
	            , `{$ent->conf['entID']}StatusATA` DATETIME NULL DEFAULT NULL
	            , `{$ent->conf['entID']}Data` LONGBLOB NULL DEFAULT NULL
	            , `{$ent->conf['entID']}InsertBy` varchar(50) DEFAULT NULL
	            , `{$ent->conf['entID']}InsertDate` datetime DEFAULT NULL
	            , `{$ent->conf['entID']}EditBy` varchar(50) DEFAULT NULL
	            , `{$ent->conf['entID']}EditDate` datetime DEFAULT NULL
	            , PRIMARY KEY (`{$ent->conf['entID']}ID`)
	            ) ENGINE=MyISAM DEFAULT CHARSET=utf8;\r\n";
	        $oSQL_arch->q($sqlCreate);
	    }
		$arrTable = $oSQL_arch->getTableInfo($ent->conf["entTable"]);
		
	    //make fields array for possible table update
	    $arrCol = $arrTable["columns_index"];
	    
	    $predField = "{$ent->conf['entID']}StatusATA";
	    
	    foreach ($ent->conf['ATR'] as $atrID => $rwATR) {
	    	if (!in_array($rwATR["atrID"], $arrCol)){
	            switch ($rwATR["atrType"]){
	                case "boolean":
	                    $strType = "INT";
	                    break;
	                case "numeric":
	                    $strType = "DECIMAL";    
	                    break;
	                case "date":
	                case "datetime":
	                    $strType = $rwATR["atrType"];
	                    break;
	                case "textarea":
	                    $strType = "LONGTEXT";
	                    break;
	                case "combobox":
	                case "ajax_dropdown":
	                    $strType = "VARCHAR(512)"; // we'll store them as text in archive
	                    break;
	                case "varchar":
	                case "text":
	                default:                    
	                    $strType = "VARCHAR(512)";
	                    break;
	            }
	            $strFields .= "\r\n".($strFields!="" ? ", " : "")."ADD COLUMN`{$rwATR["atrID"]}` {$strType} DEFAULT NULL COMMENT ".$oSQL->escape_string($rwATR["atrTitle"])." AFTER {$predField}";
	            $predField = "{$rwATR["atrID"]}";
	        }
	    }
	    
	    $sqlArchTable = "ALTER TABLE {$ent->conf["entTable"]}{$strFields}";
	    $oSQL_arch->q($sqlArchTable);
		
	}

	public static function ArchiveItemTraceable($oSQL_arch, $item){

	    // 1. collect data from tables into assoc array
		$item->intra->local = ""; //important! We backup only english titles

		$item->conf['flagForceDelete'] = true;
		$item->conf['flagDeleteLogs'] = true;
		$item->conf['flagNoDeleteTransation'] = true;

	    $item->getAllData();
		
	    // 2. compose XML
	    $strData = json_encode($item->item);
		
	    // 3. insert into archive
		// compose SQL
		$sqlIns = "INSERT IGNORE INTO `{$item->conf['entTable']}` (
	          `{$item->conf['PK']}`
	            , `{$item->conf['entPrefix']}StatusID`
	            , `{$item->conf['entPrefix']}StatusTitle`
	            , `{$item->conf['entPrefix']}StatusTitleLocal`
	            , `{$item->conf['entPrefix']}StatusATA`
	            , `{$item->conf['entPrefix']}Data`
	            , `{$item->conf['entPrefix']}InsertBy`, `{$item->conf['entPrefix']}InsertDate`, `{$item->conf['entPrefix']}EditBy`, `{$item->conf['entPrefix']}EditDate`";
	    foreach ($item->conf['ATR'] as $atrID => $rwATR){
	    	if($atrID==$item->conf['PK'])
				continue;
			$sqlIns .= "\r\n, `{$atrID}`";
		}

		$sqlIns .= ") VALUES (
			".$oSQL_arch->e($item->item[$item->conf['PK']])."
			, ".(int)($item->item[$item->conf['entPrefix']."StatusID"])."
			, ".$oSQL_arch->e($item->conf['STA'][(int)$item->staID]["staTitle"])."
			, ".$oSQL_arch->e($item->conf['STA'][(int)$item->staID]["staTitleLocal"])."
			, ".$oSQL_arch->e($item->oSQL->d("SELECT aclATA FROM stbl_action_log WHERE aclGUID=".$oSQL_arch->e($item->item["{$item->conf['entPrefix']}StatusActionLogID"])))."
			, ".$oSQL_arch->e($strData)."
			, '{$intra->usrID}', NOW(), '{$intra->usrID}', NOW()";
		foreach ($item->conf['ATR'] as $atrID => $rwATR){
			if($atrID==$item->conf['PK'])
				continue;
			switch ($rwATR["atrType"]){
				case "combobox":
				case "ajax_dropdown":
					$val = $oSQL_arch->e($item->item[$atrID."_text"]);
					break;
				case "number":
				case "numeric":
				case "date":
				case "datetime":
					$val = ($item->item[$atrID]!="" ? $oSQL_arch->e($item->item[$atrID]) : "NULL");
					break;
				case "boolean":
					$val = (int)$item->item[$atrID];
					break;
				default:
					$val = $oSQL_arch->e($item->item[$atrID]);
					break;
			}
			$sqlIns .= "\r\n, {$val}";
		}
		$sqlIns .= ")";
		
		// echo $sqlIns;
		// die(var_export($item->item, true));

		$oSQL_arch->q($sqlIns);
		
		//echo "<pre>";
		//echo "{$sqlIns}";
		//print_r($this->item);    
		
		// 4. backup extra tables
	    foreach((array)$arrExtraTables as $table=>$arrTable)
	        $intra->archiveTable($table, $arrTable["criteria"], $arrTable["nodelete"]);
		
	    // 5. delete entity item
	    $item->delete();

	}

	public static function RestoreItemTraceable($oSQL_arch, $table, $ids, $class=null){

		GLOBAL $intra;

		$intra->cancelDataAction($_GET);

		$oSQL = $intra->oSQL;

		if(!is_array($ids))
			$ids = array($ids);

		$tableInfo = $oSQL_arch->getTableInfo($table);

		$rwEnt = $oSQL->f("SELECT * FROM stbl_entity WHERE entTable=".$oSQL->e($table));

        if(!$rwEnt['entID'])
            throw new Exception("Entity not found for table: {$table}");

		foreach ($ids as $id) {
			if(!$id)
				continue;
			$rw = $oSQL_arch->f("SELECT * FROM `{$table}` WHERE {$tableInfo['PK'][0]}=".$oSQL->e($id));
			$data = json_decode($rw["{$tableInfo['prefix']}Data"], true);
			$data = array_merge($data, array('conf'=>$rwEnt));
			$ent = new $class(null, $rwEnt);
			$item = $ent->restore($data);
			$url = eiseIntra::getFullHREF($item->conf['form'].'?'.$item->getURI());
			$intra->batchEcho("Item restored: <a target=_blank href=\"{$url}\">{$item->id}</a>");
		}

	}

}