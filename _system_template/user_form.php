<?php
include 'common/auth.php';

$usrID_  = (isset($_POST['usrID']) ? $_POST['usrID'] : $_GET['usrID'] );
$DataAction  = (isset($_POST['DataAction']) ? $_POST['DataAction'] : $_GET['DataAction'] );

class cUser extends eiseItem {

static $defaultPass = 'default_password';

public function __construct($id = null, $conf = array()){

    $conf = array_merge($conf, array('name'=>'user', 'table'=>'stbl_user', 'flagFormShowAllFields'=> true));

    parent::__construct($id, $conf);

}


public function update($nd){

    $nd_sql = $this->intra->arrPHP2SQL($nd, $this->table['columns_types']);

    if($nd_sql['usrPass']!==cUser::$defaultPass)
        $nd_sql['usrPass'] = $this->intra->password_hash($nd_sql['usrPass']);

    $sqlFields = $this->intra->getSQLFields($this->table, $nd_sql);

    $this->oSQL->q('START TRANSACTION');

    if(!$this->id){
        $this->id = $nd[$this->table['PK'][0].'_'];
        $sql = "INSERT INTO stbl_user SET {$this->table['PK'][0]}=".$this->oSQL->e($this->id).", {$this->conf['prefix']}InsertBy='{$this->intra->usrID}', {$this->conf['prefix']}InsertDate=NOW() 
            , {$this->conf['prefix']}EditBy='{$this->intra->usrID}', {$this->conf['prefix']}EditDate=NOW()
            {$sqlFields}";
        $this->oSQL->q($sql);
        
    } else {
        $sql = "UPDATE stbl_user SET {$this->conf['prefix']}EditBy='{$this->intra->usrID}', {$this->conf['prefix']}EditDate=NOW() {$sqlFields} WHERE ".$this->getSQLWhere();
        $this->oSQL->q($sql);
    }

    $this->oSQL->q('COMMIT');

    parent::update($nd);

}

public function delete(){
    parent::delete();
}

public function form($fields=null, $conf=array()){

    $fields = (!$this->id ? $this->intra->field($this->intra->translate('User ID'), 'usrID_', '', array('required'=>True)) : '')
        .$this->intra->field(null, 'defaultPass', cUser::$defaultPass, array('type'=>'hidden'))
        .$this->intra->field($this->intra->translate('Name (Eng)'), 'usrName', $this->item["usrName"], array('required'=>True))
        .$this->intra->field($this->intra->translate('Name'), 'usrNameLocal', $this->item["usrNameLocal"], array('required'=>True))
        .$this->intra->field($this->intra->translate('Password'), 'usrPass', cUser::$defaultPass, array('type'=>'password', array('required'=>True)))
        .$this->intra->field($this->intra->translate('Confirm Password'), 'usrPass1', cUser::$defaultPass, array('type'=>'password', array('required'=>True)))
        .$this->intra->field($this->intra->translate('Phone Number'), 'usrPhone', $this->item["usrPhone"])
        .$this->intra->field($this->intra->translate('Email'), 'usrEmail', $this->item["usrEmail"])
        .$this->intra->field($this->intra->translate('Inactive?'), 'usrFlagDeleted', $this->item["usrFlagDeleted"], array('type'=>'boolean'));

    $fields = $this->intra->fieldset($this->intra->arrUsrData['pagTitle'.$this->intra->local].' '.strtoupper($this->id), $fields.
                $this->intra->field(' ', null, $this->getButtons() )
                );

    return parent::form($fields, $conf);

}

}

$usr = new cUser();

$intra->dataAction(array('insert', 'update', 'delete'), $usr, $_POST);

$arrActions[]= Array ('title' => $intra->translate('Back')
	   , 'action' => $usr->conf['list']
	   , 'class'=> 'ss_arrow_left'
	);

include eiseIntraAbsolutePath."inc{$intra->conf['frame']}_top.php";

echo $usr->form();

?>
<script type="text/javascript">
$(window).load(function(){

    $('#usr').submit( function(){

        var $pass1 = $('#usrPass')
            , pass1 = $pass1.val()
            , pass2 = $('#usrPass1').val()
            , defaultPass = $('#defaultPass').val()
            , usrID = $('#usrID').val();

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

        if(!usrID && pass1==defaultPass){
            alert('You should specify the password');
            $pass1.focus();
            return false;
        }

        return true;

    })

});
</script>
<?php

include eiseIntraAbsolutePath."inc{$intra->conf['frame']}_bottom.php";
