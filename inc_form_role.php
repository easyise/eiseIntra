<?php
if ($_POST["DataAction"]=="update"){
    
    $sqlRoles = "SELECT rolID FROM stbl_role";
    $rsRol = $oSQL->do_query($sqlRoles);
    while ($rwRol = $oSQL->fetch_array($rsRol)){
       $arrToDelete = explode("|", $_POST["inp_role_mems_".$rwRol["rolID"]."_deleted"]);
       for($i=0;$i<count($arrToDelete);$i++)
           if ($arrToDelete[$i]!="")
              $sql[] = "DELETE FROM stbl_role_user WHERE rluID='".$arrToDelete[$i]."'";
       
       for ($i=0;$i<count($_POST["rluUserID_".$rwRol["rolID"]]);$i++)
          if ($_POST["rluUserID_".$rwRol["rolID"]][$i]!="") {
             if (!$_POST["rluID_".$rwRol["rolID"]][$i]){
                $sql[] = "INSERT INTO `stbl_role_user`
                       (
                      `rluUserID`,
                      `rluRoleID`,
                      `rluInsertBy`,`rluInsertDate`,`rluEditBy`,`rluEditDate`
                       ) VALUE (
                      ".$oSQL->escape_string(strtoupper($_POST["rluUserID_".$rwRol["rolID"]][$i])).",
                      '".$rwRol["rolID"]."',
                      '$usrID', NOW(), '$usrID', NOW()
                       );";
             } else {
                $sql[] = "UPDATE `stbl_role_user`  
                         SET 
                          `rluUserID` = ".$oSQL->escape_string(strtoupper($_POST["rluUserID_".$rwRol["rolID"]][$i])).",
                          `rluRoleID` = '".$rwRol["rolID"]."',
                          `rluEditBy` = '$usrID', `rluEditDate` = NOW()
                         WHERE 
                          `rluID` = '".$_POST["rluID_".$rwRol["rolID"]][$i]."'";
             }
          }
    }
     
    for ($i=0; $i<count($sql);$i++)
        $oSQL->do_query($sql[$i]);
        
    if ($_DEBUG){
       echo "<pre>";
       print_r($_POST);
       print_r($sql);     
       echo "</pre>";
       die();       
    }  else {
       SetCookie("UserMessage", "Role members are succesfully updated");
       header("Location: role_form.php");
       die();
    }
}

include '../common/eiseGrid/inc_eiseGrid.php';
$arrJS[] = '../common/eiseGrid/eiseGrid.js';
$arrCSS[] = '../common/eiseGrid/eiseGrid.css';

$arrJS[] = "../common/jquery/jquery-autocomplete/lib/jquery.bgiframe.js";
$arrJS[] = "../common/jquery/jquery-autocomplete/jquery.autocomplete.js";
$arrJS[] = "../common/jquery/jquery-autocomplete/lib/jquery.ajaxQueue.js";
$arrCSS[] = "../common/jquery/jquery-autocomplete/jquery.autocomplete.css";

include "../common/inc-frame_top.php";


?>
<script>
$(document).ready(function(){  
	easyGridInitialize();
});
</script>


<h1><?php echo $intra->arrUsrData["pagTitle{$intra->local}"]; ; ?></h1>

<div class="panel">
<form action="<?php  echo $_SERVER["PHP_SELF"] ; ?>" method="POST">
<input type="hidden" name="DataAction" value="update">
<input type="hidden" name="prjID" value="<?php  echo $prjID ; ?>">
<table border=0>
<?php

$sqlRoles = "SELECT * FROM stbl_role";
$rsRol = $oSQL->do_query($sqlRoles);

while ($rwRol = $oSQL->fetch_array($rsRol)) {
    if ($rwRol["rolFlagDefault"]==1) continue;
 ?>
<tr>
<td class="field_title"><?php  echo $rwRol["rolTitle{$intra->local}"] ; ?>:<br>
</td>
<td><?php 

$grid = new easyGrid($oSQL
					, "role_mems_".$rwRol["rolID"]
                    , Array(
                            'flagEditable'=> true
                            , 'arrPermissions' => array_merge($intra->arrUsrData, 
                                ($rwRol["rolID"]=="Admin"&& $intra->usrID!="admin" ? Array("FlagWrite"=>false) : Array())
                                )
                            , 'width'=>"300"
                             , 'controlBarButtons' => 'add'
                            )
                    );
$grid->Columns[]=Array(
	'field'=>"rluID_".$rwRol["rolID"]
	,'type'=>'row_id'
);
$grid->Columns[] = Array(
	'title'=> $intra->translate('Users')
	,'field'=>'rluUserID_'.$rwRol["rolID"]
    , 'width' => "300px"
	,'type'=>'ajax_dropdown'
    , 'source'=>'svw_user'
);

$sql = "SELECT rluID AS rluID_".$rwRol["rolID"]."
, rluUserID AS rluUserID_".$rwRol["rolID"]." FROM stbl_role_user WHERE rluRoleID='".$rwRol["rolID"]."'";
$rsRlu = $oSQL->do_query($sql);
while ($rwRlu = $oSQL->fetch_array($rsRlu)){
   $grid->Rows[] = $rwRlu;
}


$grid->Execute();
 ?>
 <br>&nbsp;
 </td>
</tr>
<?php 
}
 ?>

<?php 
if ($intra->arrUsrData["FlagWrite"]){
?>
<tr><td colspan=2 style="text-align: center;"><input type="submit" value="Save"></tr>
<?php
}
 ?>
</table>
</form>

<?php
include "../common/inc_bottom.php";
?>