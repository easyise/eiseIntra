<?php 
// to be included into ajax_actiondetails.php (ajax_details.php for older versions)
if ($_SERVER["REQUEST_METHOD"]=="POST")
  $arrIn = $_POST;
else 
  $arrIn = $_GET;
  
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

try {
    $sql = "SELECT * FROM `svw_action_attribute` WHERE actID=".$oSQL->e($rwAct["actID"]);
    $rs = $oSQL->do_query($sql);
} catch(Exception $e){
    echo json_encode(Array("ERROR"=>$e->getMessage));
    die();
}    
$arrAAT = Array();
while ($rw=$oSQL->fetch_array($rs)){
   $arrAAT[] = $rw;
}
echo json_encode(Array("act"=>$rwAct, "aat"=>$arrAAT));
?>