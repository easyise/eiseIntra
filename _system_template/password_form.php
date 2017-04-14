<?php
include 'common/auth.php';

$DataAction  = (isset($_POST['DataAction']) ? $_POST['DataAction'] : $_GET['DataAction'] );


switch($DataAction){
    case "update":
        
        $sqlTryToAuth = "SELECT usrID FROM stbl_user WHERE usrID='$usrID' AND usrPass=".$oSQL->escape_string(md5($_POST['usrPass']))."";
        $rsAuth = $oSQL->do_query($sqlTryToAuth);
        if ($oSQL->num_rows($rsAuth)==0){
            SetCookie("UserMessage", "ERROR: Old password not valid");
            header("Location: ".$_SERVER["PHP_SELF"]);
            die();
        }
        
        
        $sql = "UPDATE stbl_user SET
            usrPass = ".($_POST['usrPassword1']!="" ? $oSQL->escape_string(md5($_POST['usrPassword1'])) : "usrPass")."
            , usrEditBy = '$usrID', usrEditDate = NOW()
            WHERE usrID = '".$_POST['usrID']."'";
        
        $oSQL->do_query($sql);
        
        //die();
        
        SetCookie("UserMessage", "Data is updated");
        header("Location: ".$_SERVER["PHP_SELF"]);
        die();
        break;
    default:
        break;
}

$sqlUSR = "SELECT * FROM stbl_user WHERE usrID='$usrID'";
$rsUSR = $oSQL->do_query($sqlUSR);
$rwUSR = $oSQL->fetch_array($rsUSR);

$arrJS[] = "../common/easyCal/easyCal.js";
include eiseIntraAbsolutePath."inc-frame_top.php";
?>

<h1><?php  echo $arrUsrData["pagTitle$strLocal"] ; ?></h1>

<div class="panel">
<table width="100%">
<form action="<?php  echo $_SERVER["PHP_SELF"] ; ?>" method="POST">
<input type="hidden" name="usrID" value="<?php  echo htmlspecialchars($rwUSR['usrID']) ; ?>">
<input type="hidden" name="DataAction" value="update">
<tr><td width="70%">

<table width="100%">


<tr>
<td class="field_title"><?php  echo getTranslation("Login") ; ?>:</td>
<td><?php
 echo ShowTextBox("usrID_", $rwUSR["usrID"], " style='width: 100%;' disabled='yes'");?></td>
</tr>

<tr>
<td class="field_title"><?php  echo getTranslation("Old Password") ; ?>:</td>
<td><input type="password" id="usrPass" name="usrPass" value="" style="width: auto;"></td>
</tr>

<tr>
<td class="field_title"><?php  echo getTranslation("Password") ; ?>:</td>
<td><input type="password" id="usrPassword1" name="usrPassword1" value="" style="width: auto;"></td>
</tr>

<tr>
<td class="field_title"><?php  echo getTranslation("Repeat Password") ; ?>:</td>
<td><input type="password" id="usrPassword2" name="usrPassword2" value="" style="width: auto;"></td>
</tr>

</table>

<td width="30%"></td>
</tr>
<?php 
if ($arrUsrData["FlagWrite"]) {
 ?>
<tr><td><div align="center"><input type="Submit" value="Update" onclick="return checkForm();">
<?php 
?>
</div></td></tr>
<?php 
}
 ?>
</table>
<script>

function checkForm(){
    var pass1 = document.getElementById("usrPassword1");
    var pass2 = document.getElementById("usrPassword2");
    if (pass1.value!="" && pass1.value!=pass2.value) {
        alert("Passwords doesn't match");
        return false;
    }
    return true;
}
</script>
<?php
include eiseIntraAbsolutePath."inc-frame_bottom.php";
?>
