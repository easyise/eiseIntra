<?php
include 'common/auth.php';

$usrID_  = (isset($_POST['usrID']) ? $_POST['usrID'] : $_GET['usrID'] );
$DataAction  = (isset($_POST['DataAction']) ? $_POST['DataAction'] : $_GET['DataAction'] );


switch($DataAction){
    case "update":
        if ($usrID_=="") {
            $sql[] = "INSERT INTO stbl_user (
                usrID
                , usrName
                , usrNameLocal
                , usrAuthMethod
                , usrPass
                , usrFlagLocal
                , usrPhone
                , usrEmail
                , usrFlagDeleted
                , usrInsertBy, usrInsertDate, usrEditBy, usrEditDate
                ) VALUES (
                '{$_POST["usrID_"]}'
                , ".$oSQL->escape_string($_POST['usrName'])."
                , ".$oSQL->escape_string($_POST['usrNameLocal'])."
                , 'DB'
                , ".($_POST['usrPassword1']!="" ? $oSQL->escape_string(md5($_POST['usrPassword1'])) : "NULL")."
                , '".($_POST['usrFlagLocal']=='on' ? 1 : 0)."'
                , ".$oSQL->escape_string($_POST['usrPhone'])."
                , ".$oSQL->escape_string($_POST['usrEmail'])."
                , '".($_POST['usrFlagDeleted']=='on' ? 1 : 0)."'
                , '$usrID', NOW(), '$usrID', NOW());";
            $usrID_=$_POST["usrID_"];
        } else {
            $sql[] = "UPDATE stbl_user SET
                usrName = ".$oSQL->escape_string($_POST['usrName'])."
                , usrNameLocal = ".$oSQL->escape_string($_POST['usrNameLocal'])."
                , usrPass = ".($_POST['usrPassword1']!="" ? $oSQL->escape_string(md5($_POST['usrPassword1'])) : "usrPass")."
                , usrFlagLocal = '".($_POST['usrFlagLocal']=='on' ? 1 : 0)."'
                , usrPhone = ".$oSQL->escape_string($_POST['usrPhone'])."
                , usrEmail = ".$oSQL->escape_string($_POST['usrEmail'])."
                , usrFlagDeleted = '".($_POST['usrFlagDeleted']=='on' ? 1 : 0)."'
                , usrEditBy = '$usrID', usrEditDate = NOW()
                WHERE usrID = '".$_POST['usrID']."'";
        }
        
        for($i=0;$i<count($sql);$i++){
           $oSQL->do_query($sql[$i]);
        //echo "<pre>{$sql[$i]}</pre>";
        }
        
        //die();
        
        SetCookie("UserMessage", "Data is updated");
        header("Location: ".$_SERVER["PHP_SELF"]."?usrID={$usrID_}");
        die();
        break;
    case "delete":
        $sqlDel = "DELETE FROM stbl_user WHERE usrID='{$_POST["usrID"]}'";
        $oSQL->do_query($sqlDel);
        SetCookie("UserMessage", "Data is deleted");
        header("Location: users_list.php");
        die();
        break;
    default:
        break;
}

$sqlUSR = "SELECT * FROM stbl_user WHERE usrID='$usrID_'";
$rsUSR = $oSQL->do_query($sqlUSR);
$rwUSR = $oSQL->fetch_array($rsUSR);

$arrActions[]= Array ('title' => 'Back to list'
	   , 'action' => "users_list.php"
	   , 'class'=> 'ss_arrow_left'
	);
$arrJS[] = "../common/easyCal/easyCal.js";
include eiseIntraAbsolutePath."inc-frame_top.php";
?>

<h1><?php  echo $arrUsrData["pagTitle$strLocal"] ; ?></h1>

<div class="panel">
<table width="100%">
<form action="<?php  echo $_SERVER["PHP_SELF"] ; ?>" method="POST">
<input type="hidden" id="usrID" name="usrID" value="<?php  echo htmlspecialchars($rwUSR['usrID']) ; ?>">
<input type="hidden" id="DataAction" name="DataAction" value="update">
<tr><td width="70%">

<table width="100%">

<?php 

if ($rwUSR['usrID']=="") {
 ?>

<tr>
<td class="field_title"><?php  echo getTranslation("Login") ; ?>:</td>
<td><?php
 echo ShowTextBox("usrID_", $rwUSR["usrID"], " style='width: 100%;'");?></td>
</tr>
<?php 
}
 ?>

<tr>
<td class="field_title"><?php  echo getTranslation("Name") ; ?>:</td>
<td><?php
 echo ShowTextBox("usrName", $rwUSR["usrName"], " style='width: 100%;'");?></td>
</tr>

<tr>
<td class="field_title"><?php  echo getTranslation("Name (Local)") ; ?>:</td>
<td><?php
 echo ShowTextBox("usrNameLocal", $rwUSR["usrNameLocal"], " style='width: 100%;'");?></td>
</tr>

<tr>
<td class="field_title"><?php  echo getTranslation("Password") ; ?>:</td>
<td><input type="password" id="usrPassword1" name="usrPassword1" value="" style="width: auto;"></td>
</tr>

<tr>
<td class="field_title"><?php  echo getTranslation("Repeat Password") ; ?>:</td>
<td><input type="password" id="usrPassword2" name="usrPassword2" value="" style="width: auto;"></td>
</tr>

<tr>
<td class="field_title"><?php  echo getTranslation("Default Language") ; ?>:</td>
<td><?php
 echo ShowCheckBox("usrFlagLocal", $rwUSR["usrFlagLocal"]);?></td>
</tr>

<tr>
<td class="field_title"><?php  echo getTranslation("Phone") ; ?>:</td>
<td><?php
 echo ShowTextBox("usrPhone", $rwUSR["usrPhone"], " style='width: 100%;'");?></td>
</tr>

<tr>
<td class="field_title"><?php  echo getTranslation("Email") ; ?>:</td>
<td><?php
 echo ShowTextBox("usrEmail", $rwUSR["usrEmail"], " style='width: 100%;'");?></td>
</tr>

<tr>
<td class="field_title"><?php  echo getTranslation("Deleted") ; ?>:</td>
<td><?php
 echo ShowCheckBox("usrFlagDeleted", $rwUSR["usrFlagDeleted"]);?></td>
</tr>


</table>

<td width="30%"></td>
</tr>
<?php 
if ($arrUsrData["FlagWrite"]) {
 ?>
<tr><td><div align="center"><input type="Submit" value="Update" onclick="return checkForm();">
<?php 
if ($usrID_!="" && $rwUSR["usrDeleteDate"]==""){
?>
<input type="Submit" value="Delete" onclick="return confirmDelete()" style="width:auto;">
<?php  
  }
?>
</div></td></tr>
<?php 
}
 ?>
</table>
<script>
function confirmDelete(){
   if(confirm("Are you sure you'd like to delete?")){
      document.getElementById("DataAction").value="delete";
      return true;
   }
   return false;
}

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
