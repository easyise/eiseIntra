<?php 
// to be included into ajax_actiondetails.php (ajax_details.php for older versions)
if ($_SERVER["REQUEST_METHOD"]=="POST")
  $arrIn = $_POST;
else 
  $arrIn = $_GET;
  
$DataAction = $arrIn["DataAction"];

switch ($DataAction){
    case 'getMessages':
        include eiseIntraAbsolutePath."inc_entity_item_form.php";
        try {
            $o = new eiseEntityItemForm($oSQL, $intra, $arrIn['entID'], $arrIn['entItemID']);    
            $arrFIL = $o->getMessages();
        } catch (Exception $e){
            echo json_encode(Array("ERROR"=>$e->getMessage()));
            die();
        }

        echo json_encode(array('data'=>$arrFIL));
        die();
    case 'getFiles':
        include eiseIntraAbsolutePath."inc_entity_item_form.php";
        try {
            $o = new eiseEntityItemForm($oSQL, $intra, $arrIn['entID'], $arrIn['entItemID']);    
            $arrFIL = $o->getFiles();
        } catch (Exception $e){
            echo json_encode(Array("ERROR"=>$e->getMessage()));
            die();
        }

        echo json_encode(array('data'=>$arrFIL));
        die();
    case 'getActionLog':
        
        include eiseIntraAbsolutePath."inc_entity_item_form.php";
        try {
            $o = new eiseEntityItemForm($oSQL, $intra, $arrIn['entID'], $arrIn['entItemID']);    
            $arrACL = $o->getActionLog();
        } catch (Exception $e){
            echo json_encode(Array("ERROR"=>$e->getMessage()));
            die();
        }

        echo json_encode(array('data'=>$arrACL));
        die();
        

    case 'getActionDetails':
    default: // default is for backward-compatibility

        include eiseIntraAbsolutePath."inc_entity.php";
        $arrRet = Array();
          
        // get action details
        if (isset($arrIn["actID"])){
            $sqlAct = "SELECT * FROM stbl_action WHERE actID=".$oSQL->e($arrIn["actID"]);
        } else 
            if (isset($arrIn["aclGUID"])){
                $sqlAct = "SELECT * FROM stbl_action_log INNER JOIN stbl_action ON aclActionID = actID WHERE aclGUID=".$oSQL->e($arrIn["aclGUID"]);
            }
            
        if (!$sqlAct){
            echo json_encode(Array("ERROR"=>"No criteria"));
            die();
        }

        try {
            $rsAct = $oSQL->q($sqlAct);
            $rwAct = $oSQL->f($rsAct);
        } catch(Exception $e){
            echo json_encode(Array("ERROR"=>$e->getMessage));
            die();
        }

        $entID = $rwAct['actEntityID'] ? $rwAct['actEntityID'] : $arrIn['entID'];
        $oEnt = new eiseEntity($oSQL, $intra, $entID);

        echo json_encode(Array("acl"=>$rwAct 
            , 'act'=>$oEnt->conf['ACT'][$rwAct['actID']]
            , 'atr'=>$oEnt->conf['ATR']
            ));
        die();

}
?>