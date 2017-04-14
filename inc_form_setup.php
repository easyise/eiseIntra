<?php
switch ($_POST["DataAction"]) {

  case "update":
      $rsSTP = $oSQL->do_query("SELECT * FROM stbl_setup");
      while ($rwSTP = $oSQL->fetch_array($rsSTP)) {
          if (($_POST[$rwSTP["stpVarName"]]!=$rwSTP["stpCharValue"])&&($rwSTP["stpFlagReadOnly"]!="y")){
              $sqlUpdSetupRow = "UPDATE stbl_setup SET stpCharValue=".$oSQL->escape_string($_POST[$rwSTP["stpVarName"]]).
                                " WHERE stpVarName = '".$rwSTP["stpVarName"]."'";
              $oSQL->do_query($sqlUpdSetupRow);
          }
          if(!is_null($rwSTP["stpCharValueLocal"]) && $_POST[$rwSTP["stpVarName"]."_local"]!=$rwSTP["stpCharValueLocal"]){
              $sqlUpdSetupRow = "UPDATE stbl_setup SET stpCharValueLocal=".$oSQL->escape_string($_POST[$rwSTP["stpVarName"]."_local"]).
                              " WHERE stpVarName = '".$rwSTP["stpVarName"]."'";
              $oSQL->do_query($sqlUpdSetupRow);
          }
      }
       
      $intra->redirect($intra->translate("Settings are saved"), $_SERVER["PHP_SELF"]);

}

$arrJS[] = jQueryUIPath.'/jquery-ui.min.js';
$arrCSS[] = jQueryUIPath.'/jquery-ui.min.css';

include eiseIntraAbsolutePath."inc{$intra->conf['frame']}_top.php";
?>


<form action="<?php echo $_SERVER["PHP_SELF"]; ?>" method="POST" class="eiseIntraForm">
<input type="hidden" name="DataAction" value="update">

<fieldset><legend><?php  echo $intra->translate("System settings"); ; ?></legend>

<?php
$rsSTP = $oSQL->do_query("SELECT * FROM stbl_setup ORDER BY stpFlagReadOnly ASC, stpNGroup ASC, stpID ASC");
   
while ($rwSTP = $oSQL->fetch_array($rsSTP)) {

    $arrConf = Array("FlagWrite"=>(!$rwSTP["stpFlagReadOnly"] && (bool)$intra->arrUsrData["FlagWrite"]));

    $title = $rwSTP["stpTitle{$intra->local}"] ? $rwSTP["stpTitle{$intra->local}"] : $rwSTP["stpTitle"];

    $type = $rwSTP["stpCharType"]=='varchar' ? 'text' : $rwSTP["stpCharType"]; //backward comaptibility

    if($type=='textarea'){
        $arrConf['strAttrib'] = 'rows=\"4\"';
    }

    echo $intra->field($title
        , $rwSTP["stpVarName"]
        , $rwSTP["stpCharValue"]
        , array_merge($arrConf
            , array(
                'type' => $rwSTP["stpCharType"]
                , 'source'=>$rwSTP['stpDataSource']
                )
            ) 
    );

    if($rwSTP["stpCharValueLocal"]!==null){
        echo $intra->field($title.' ('.$intra->conf['language'].')'
            , $rwSTP["stpVarName"].'_local'
            , $rwSTP["stpCharValueLocal"]
            , array_merge($arrConf
                , array(
                    'type' => $rwSTP["stpCharType"]
                    , 'source'=>$rwSTP['stpDataSource'])
                ) 
        );    
    }

    if($rwSTP['stpCharType']==='password'){
        echo $intra->field(null
                , $rwSTP["stpVarName"].'_old'
                , $rwSTP["stpCharValue"]
                , array('type'=>'hidden')
                );
    }

}
?>
<script language="JavaScript">

var checkPassword = function($passInp, $frm){

    var $oldPass = $('#'+$passInp[0].id+'_old');

    if($passInp.val()!=$oldPass.val()){
        var fldPassRepeat = $passInp[0].id+'_repeat'
        $frm.eiseIntraForm('createDialog', {title: 'Repeat password'
            , class: 'ei-password-confirm'
            , fields: [{title: $passInp.parents('div.eiseIntraField').find('label').text()
                , type: 'password'
                , name: fldPassRepeat
                }]
            , onsubmit: function(values, $dlg){
                if($passInp.val()!=values[fldPassRepeat]['v']){
                    alert('Passwords doesn\'t match');
                    return false;
                }
                $oldPass.val($passInp.val());
                $dlg.dialog('close').remove();
            }
        }
        );
        return false;
    }

    return true;

}

$(window).load(function(){
    var $frm = $('.eiseIntraForm')
        .eiseIntraForm();

    $frm.find('input[type=password]').blur(function(){
        
        checkPassword($(this), $frm);

    });

    $frm.submit(function(){

        var flagCanSubmit = true;

        $frm.find('input[type=password]').each(function(){
            if(!checkPassword($(this), $frm)){
                flagCanSubmit = false;
                return false; //break
            }
        })

        console.log('qq', flagCanSubmit)

        return flagCanSubmit;
    })
})


function SubmitForm(){
   return true;
}
</script>

<?php if ($intra->arrUsrData["FlagWrite"]) { ?>  
<div><label>&nbsp;</label><input type="submit" value="Save" onClick="return SubmitForm();"></div>
<?php } ?>

</fieldset>
</form>

<?php 
include eiseIntraAbsolutePath."inc{$intra->conf['frame']}_bottom.php"; 