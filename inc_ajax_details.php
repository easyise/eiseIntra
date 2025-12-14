<?php 
// to be included into ajax_actiondetails.php (ajax_details.php for older versions)
if ($_SERVER["REQUEST_METHOD"]=="POST")
  $arrIn = $_POST;
else 
  $arrIn = $_GET;
  
$DataAction = $arrIn[$intra->conf['dataReadKey']];

switch ($DataAction){
    case 'getSessionData':
        $intra->json('ok', '', array('session'=>$_SESSION, 'conf'=>$intra->conf, 'arrUsrData'=>$intra->arrUsrData));

    case 'getCurrentUserInfo':
        echo $intra->getCurrentUserInfo();
        die();

    case 'getMenu':
        header("Content-Type: text/html; charset=UTF-8");
        echo $intra->menu();
        die();

    case 'getTopLevelMenu':
        if(!empty($eiseIntraTopLevelMenu))
            echo $intra->topLevelMenu($eiseIntraTopLevelMenu);
        die();

    case 'getBookmarks':

        include eiseIntraAbsolutePath."inc_entity_item_form.php";
        try {
            eiseEntityItemForm::getBookmarks();  
        } catch (Exception $e){}
        die();

    case 'getMessages':
        include eiseIntraAbsolutePath."inc_entity_item_form.php";
        try {
            $o = new eiseEntityItemForm($oSQL, $intra, $arrIn['entID'], $arrIn['entItemID']);    
            $arrMsg = $o->getMessages();

            $intra->json('ok', $intra->translate('Messages: ').count($arrMsg), $arrMsg);

        } catch (Exception $e){
            $intra->json('error', $e->getMessage());
        }

    case 'getFiles':
        include eiseIntraAbsolutePath."inc_entity_item_form.php";
        try {
            $o = new eiseEntityItemForm($oSQL, $intra, $arrIn['entID'], $arrIn['entItemID']);    
            $arrFIL = $o->getFiles();

            $intra->json('ok', $intra->translate('Files: ').count($arrFIL), $arrFIL);

        } catch (Exception $e){
            $intra->json('error', $e->getMessage());
        }

    case 'getActionLog':
        
        include eiseIntraAbsolutePath."inc_item_traceable.php";
        try {
            $o = new eiseItemTraceable($arrIn['entItemID'], ['entID'=>$arrIn['entID']]);    
            $arrACL = $o->getActionLog();

            $intra->json('ok', $intra->translate('Events: ').count($arrACL), $arrACL);

        } catch (Exception $e){
            $intra->json('error', $e->getMessage());
        }
        
    case 'getChecklist':
        
        include eiseIntraAbsolutePath."inc_item_traceable.php";
        try {
            $o = new eiseItemTraceable($arrIn['entItemID'], ['entID'=>$arrIn['entID']]);    
            $arrACL = $o->getChecklist();

            $intra->json('ok', $intra->translate('Events: ').count($arrACL), $arrACL);

        } catch (Exception $e){
            $intra->json('error', $e->getMessage());
        }

    case 'getActionLog_':
        
        include eiseIntraAbsolutePath."inc_entity_item_form.php";
        try {
            $o = new eiseEntityItemForm($oSQL, $intra, $arrIn['entID'], $arrIn['entItemID']);    
            $arrACL = $o->getActionLog();

            $intra->json('ok', $intra->translate('Events: ').count($arrACL), $arrACL);

        } catch (Exception $e){
            $intra->json('error', $e->getMessage());
        }

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