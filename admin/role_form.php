<?php
include "common/auth.php" ;

$intra->requireComponent('grid');

$oSQL->dbname=(isset($_POST["dbName"]) ? $_POST["dbName"] : $_GET["dbName"]);
$oSQL->select_db($oSQL->dbname);
$dbName = $oSQL->dbname;

//$_DEBUG = true;

$DataAction = isset($_POST["DataAction"]) ? $_POST["DataAction"] 
    : (isset($_GET["DataAction"]) ? $_GET["DataAction"] : "");

$gridROL = new eiseGrid($oSQL
        ,'rol'
        , Array(
                'arrPermissions' => Array('FlagWrite'=>true)
                , 'strTable' => 'stbl_role'
                , 'strPrefix' => 'rol'
                , 'flagStandAlone' => true
                , 'controlBarButtons' => 'add|delete|excel|save'
                , 'extraInputs' => Array("DataAction"=>"update", 'dbName'=>$dbName)
                )
        );

$gridROL->Columns[]  = Array(
            'type' => 'row_id'
            , 'field' => 'rolID_id'
        );
$gridROL->Columns[]  = Array(
            'type' => 'order'
            , 'field' => 'rol_order'
            , 'title' => '#'
        );
$gridROL->Columns[] = Array(
        'title' => "ID"
        , 'field' => "rolID"
        , 'mandatory' => true
        , 'type' => "text"
        , 'width' => '150px'
);
$gridROL->Columns[] = Array(
        'title' => "Title (Loc)"
        , 'field' => "rolTitleLocal"
        , 'type' => "text"
        , 'width' => "300px"
        , 'filterable' => true
        );
$gridROL->Columns[] = Array(
        'title' => "Title"
        , 'field' => "rolTitle"
        , 'type' => "text"
        , 'width' => "300px"
        , 'filterable' => true
);$gridROL->Columns[] = Array(
        'title' => "all"
        , 'field' => "rolFlagDefault"
        , 'type' => "checkbox"
        , 'width' => '30px'
        , 'filterable' => true
);
$gridROL->Columns[] = Array(
        'title' => "virt"
        , 'field' => "rolFlagVirtual"
        , 'type' => "checkbox"
        , 'width' => '30px'
        , 'filterable' => true
);
$gridROL->Columns[] = Array(
        'title' => "Members"
        , 'field' => "rolMembers"
        , 'type' => "text"
        , 'disabled' => "[rolFlagDefault]"
        , 'width' => "100%"
        , 'filterable' => true
);
$gridROL->Columns[] = Array(
        'title' => "del?"
        , 'field' => "rolFlagDeleted"
        , 'type' => "checkbox"
        , 'width' => '30px'
        , 'filterable' => true
);


switch($DataAction){
    case "update":
        
        $oSQL->q('START TRANSACTION');

        $gridROL->Update();
        
        //determining newly created roles
        for ($i=1;$i<count($_POST["rolID_id"]);$i++){
            if ($_POST["rolID_id"][$i]==''){
                $sql[] = "INSERT INTO stbl_page_role (
                    pgrPageID
                    , pgrRoleID
                    , pgrFlagRead
                    , pgrFlagWrite
                    , pgrInsertBy, pgrInsertDate, pgrEditBy, pgrEditDate
                    , pgrFlagCreate
                    , pgrFlagUpdate
                    , pgrFlagDelete
                    ) SELECT 
                    pagID
                    , ".$oSQL->escape_string($_POST["rolID"][$i])." as pgrRoleID
                    , 0 as pgrFlagRead
                    , 0 as pgrFlagWrite
                    , '$usrID' AS pgrInsertBy, NOW() AS pgrInsertDate, '$intra->usrID' AS pgrEditBy, NOW() AS pgrEditDate
                    , 0 AS pgrFlagCreate
                    , 0 AS pgrFlagUpdate
                    , 0 AS pgrFlagDelete
                    FROM stbl_page";
                /*    
                $sql[] = "INSERT INTO stbl_role_action (
                        rlaRoleID
                        , rlaActionID
                        ) SELECT 
                        ".$oSQL->escape_string($_POST["rolID"][$i])." AS rlaRoleID
                        , actID AS rlaActionID
                        FROM stbl_action";       
                */
            }
        }
                
        //detrminig deleted roles
        $arrRolToDel = explode("|", $_POST["inp_rol_deleted"]);
        for($i=0;$i<count($arrRolToDel);$i++)
            if ($arrRolToDel[$i]!=""){
               $sql[] = "DELETE FROM stbl_role_action WHERE rlaRoleID='".$arrRolToDel[$i]."'";
               $sql[] = "DELETE FROM stbl_page_role WHERE pgrRoleID='".$arrRolToDel[$i]."'";
            }
        
        //updating members
        for ($i=0;$i<count($_POST["rolID_id"]);$i++)
        if (!in_array($_POST["rolID_id"][$i], $arrRolToDel) && $_POST["inp_rol_updated"][$i]=="1")
        {
            $arrUsr = preg_split("/\s*[,\;\|]\s*/", $_POST["rolMembers"][$i]);
            $sql[] = "DELETE FROM stbl_role_user WHERE rluRoleID='".$_POST["rolID_id"][$i]."'";
            foreach ($arrUsr as $val)
               if ($val!="")
                   $sql[] = "INSERT INTO stbl_role_user(
                        rluUserID
                        , rluRoleID
                        , rluInsertBy, rluInsertDate, rluEditBy, rluEditDate
                        ) VALUES (
                        ".$oSQL->escape_string($val)."
                        , '".$_POST["rolID_id"][$i]."'
                        , '$usrID', NOW(), '$usrID', NOW());";
        }
        
        for($i=0;$i<count($sql);$i++)
            $oSQL->do_query($sql[$i]);

        /*
        echo "<pre>";
        print_r($_POST);
        print_r($sql);
        echo "</pre>";
        die();
        //*/

        $oSQL->q('COMMIT');

        $intra->redirect("Role information updated", $_SERVER["PHP_SELF"]."?dbName=$dbName");
        break;
    default:
        break;
}

$arrActions = [array(
    'title' => __("Roles Report"),
    'action' => '#roles_report',
    'class' => 'fa-file-text-o',
)];

include eiseIntraAbsolutePath."inc_top.php";
?>

<style type="text/css">
th.rol_rolID {
    padding-right: 10px;
    text-align: right;
}

.role-report {
    width: 100%;
    height: 400px;
}
</style>

<script>
$(document).ready(function(){  
	var $grid = $('.eiseGrid').eiseGrid();

    $('a[href="#roles_report"]').click(function(){
        var text = '# Roles Report\n\n',
            $initiator = $(this);
        $grid.find('tr:visible').each(function(){
            var $tr = $(this);
            var rolID = $tr.find('td.rol-rolID input').val();
            var rolTitle = $tr.find('td.rol-rolTitle input').val();
            var rolTitleLocal = $tr.find('td.rol-rolTitleLocal input').val();

            var rolMembersText = $tr.find('td.rol-rolMembers input').val();
            var rolMembers = rolMembersText ? rolMembersText.split(',').map(function(el){return el.trim();}).filter(function(el){return el!='';}) : [];

            var rolNameAD = 'prg-CommonDB-'+rolID;
            
            text += 'Role: '+rolID+' ('+rolTitleLocal+')\n';
            text += 'AD Group: '+rolNameAD+'\n';
            for (var i=0;i<rolMembers.length;i++){
                text += '- '+rolMembers[i]+'\n';
            }
            text += '\n';
            text += '---\n\n';
        });

         $initiator.eiseIntraForm('createDialog', {
            title: $initiator.text()
            , width: '800px'
            , fields: [
                {title: ''
                    , name: 'role_report'
                    , type: 'textarea'
                    , class: 'role-report'
                    , value: text
                    }
            ]
            , onsubmit: function(){
                $(this).dialog('close');
                return false;
            }
        })
        

        return false;
    });
});
</script>


<?php 
$sqlROL = "SELECT ROL.*
, GROUP_CONCAT(rluUserID SEPARATOR ', ') as rolMembers
FROM stbl_role ROL
LEFT OUTER JOIN stbl_role_user ON rolID=rluRoleID
GROUP BY rolID, rolTitle, rolTitleLocal";
$rsROL = $oSQL->do_query($sqlROL);
while ($rwROL = $oSQL->fetch_array($rsROL)){
    $rwROL['rolID_id'] = $rwROL['rolID'];
    $gridROL->Rows[] = $rwROL;
}

$gridHTML = $gridROL->get_html();

echo $intra->fieldset(__("Roles"), $gridHTML, Array('width'=>'100%'));

?>

<?php
include eiseIntraAbsolutePath."inc_bottom.php";
?>