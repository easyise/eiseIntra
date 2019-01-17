<?php
include 'common/auth.php';

$DataAction  = (isset($_POST['DataAction']) ? $_POST['DataAction'] : $_GET['DataAction'] );


if($_POST['DataAction']==="update"){
    
    $oSQL->q('START TRANSACTION');

    $rwUsr = $oSQL->f("SELECT * FROM stbl_user WHERE usrID='{$intra->usrID}'");

    if(!$intra->password_verify($_POST['usrPass_old'], $rwUsr['usrPass'])){
        $intra->redirect('ERROR: '.$intra->translate('Invalid password for user %s', $intra->usrID), $_SERVER['PHP_SELF']);
    }

    $sql = "UPDATE stbl_user SET
        usrPass = ".$oSQL->e($intra->password_hash($_POST['usrPass']))."
        , usrEditBy = '$usrID', usrEditDate = NOW()
        WHERE usrID = '".$intra->usrID."'";
    
    $oSQL->do_query($sql);

    $oSQL->q('COMMIT');
    
    $intra->redirect('ERROR: '.$intra->translate('Password for %s is updated', $intra->usrID), $_SERVER['PHP_SELF']);
    
}

include eiseIntraAbsolutePath."inc{$intra->conf['frame']}_top.php";

$fields = $intra->field(null, 'DataAction', 'update', array('type'=>'hidden'))
    .$intra->fieldset( $intra->arrUsrData['pagTitle'.$intra->local].' '.$intra->usrID, 
            $intra->field($intra->translate('Old Password'), 'usrPass_old', '', array('type'=>'password', 'required'=>true)).
            $intra->field($intra->translate('New Password'), 'usrPass', '', array('type'=>'password', 'required'=>true)).
            $intra->field($intra->translate('Confirm Password'), 'usrPass1', '', array('type'=>'password', 'required'=>true)).
            $intra->field(' ', null, $intra->showButton('btnSubmit', $intra->translate('Set Password'), array('type'=>'submit')))
            );

echo $intra->form($intra->conf['form'], 'update', $fields, 'POST', array('id'=>'password', 'flagAddJavaScript'=>True));

?>
<script type="text/javascript">
$(document).ready(function(){

    $('form#password').submit(function(){

        var $pass1 = $('#usrPass')
            , pass1 = $pass1.val()
            , pass2 = $('#usrPass1').val();

        if(!pass1){
            alert('Password is empty');
            $pass1.focus();
            return false;
        }

        if(pass1!=pass2){
            alert('Passwords doesn\'t match');
            $pass1.focus();
            return false;
        }

        return true;
    })
    
})
</script>
<style type="text/css">
form#password fieldset {
    width: 50%;
    min-width: 400px;
}
</style>
<?php

include eiseIntraAbsolutePath."inc{$intra->conf['frame']}_bottom.php";