<?php

include_once(commonStuffAbsolutePath."eiseGrid2/inc_eiseGrid.php");
$arrJS[] = commonStuffRelativePath.'eiseGrid2/eiseGrid.jQuery.js';
$arrCSS[] = commonStuffRelativePath.'eiseGrid2/themes/default/screen.css';


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

$arrJS[] = jQueryUIRelativePath."js/jquery-ui-1.8.16.custom.min.js";
$arrCSS[] = jQueryUIRelativePath."css/redmond/jquery-ui-1.8.16.custom.css";

include "inc-frame_top.php";


?>
<script>
$(document).ready(function(){  
    var oldTop = 0;
    var nRows = 0;
    var ixInRow = 0;
    var fldsTotalH = 0;

    var arrMaxRowHeights = [];
	var gridsPerRow = [];

    $('.eiseGrid').eiseGrid();


    $('.gridRLU').each(function(){
        var $flds = $(this).parents('fieldset').first();

        var top = Math.round( $flds.offset().top - parseFloat($flds.css('margin-top').replace('px', '')) );
        var h = $flds.outerHeight(true);

        if(oldTop!=top){
            nRows++;
            ixInRow = 0;
        }

        arrMaxRowHeights[nRows] = (arrMaxRowHeights[nRows] > h ? arrMaxRowHeights[nRows] : h);

        if(!gridsPerRow[nRows]){
            gridsPerRow[nRows] = [];
        }
        gridsPerRow[nRows][ixInRow] = $flds;

        ixInRow++;

        oldTop = top;

    });

    for(var i=0;i<arrMaxRowHeights.length;i++)
        fldsTotalH+=arrMaxRowHeights[i];

    var heightDelta = Math.ceil($(document).height()-$(window).height());
    var viewPortHeight = $(window).height();
    var otherStuffHeight = Math.ceil($(document).height()-fldsTotalH);

    var rowH = Math.ceil( ( viewPortHeight - otherStuffHeight ) / arrMaxRowHeights.length );

    for(var y=0;y<gridsPerRow.length;y++){
        var row = gridsPerRow[y];
        for(var x=0;x<row.length;x++){

            var $flds = row[x];

            var newH = (arrMaxRowHeights[y] > rowH
                ? rowH
                : arrMaxRowHeights[y])
                - parseFloat($flds.css('margin-top').replace('px', ''));

            $flds.height(newH);

            $flds.find('.eiseGrid').eiseGrid('height', newH);

        }
    }

});
</script>


<style>
fieldset {
    display: inline-block !important;
    vertical-align: top;
    width: 31.5%;
    min-width: 300px;
}
</style>

<form action="<?php  echo $_SERVER["PHP_SELF"] ; ?>" method="POST" class="eiseIntraForm">
<input type="hidden" name="DataAction" value="update">
<input type="hidden" name="prjID" value="<?php  echo $prjID ; ?>">
<div>
<?php

$sqlRoles = "SELECT * FROM stbl_role";
$rsRol = $oSQL->do_query($sqlRoles);

$nRole = 0;
while ($rwRol = $oSQL->fetch_array($rsRol)) {
    if ($rwRol["rolFlagDefault"]==1) continue;
 ?>
<fieldset><?php  if ($nRole==0) echo '<legend>'.$intra->arrUsrData["pagTitle{$intra->local}"].'</legend>' ; ?>
<?php 

$grid = new eiseGrid($oSQL
					, "role_mems_".$rwRol["rolID"]
                    , Array(
                            'flagEditable'=> true
                            , 'arrPermissions' => array_merge($intra->arrUsrData, 
                                ($rwRol["rolID"]=="Admin"&& $intra->usrID!="admin" ? Array("FlagWrite"=>false) : Array())
                                )
                             , 'controlBarButtons' => 'add|delete'
                             , 'class' => 'gridRLU'
                            )
                    );
$grid->Columns[]=Array(
	'field'=>"rluID_".$rwRol["rolID"]
	,'type'=>'row_id'
);
$grid->Columns[] = Array(
	'title'=> $rwRol["rolTitle{$intra->local}"]
	,'field'=>'rluUserID_'.$rwRol["rolID"]
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
</fieldset>
<?php 
    $nRole++;
}
 ?>
</div>


<?php 
if ($intra->arrUsrData["FlagWrite"]){
?>
<div style="text-align:center;"><input type="submit" value="Save" style="margin: 10px auto;width: 300px;"></div>
<?php
}
 ?>
</form>


<?php
include "inc-frame_bottom.php";
?>