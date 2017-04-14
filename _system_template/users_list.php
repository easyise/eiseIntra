<?php
include("common/auth.php");
$arrJS[] = commonStuffRelativePath."eiseList/eiseList.js";
$arrCSS[] = commonStuffRelativePath."eiseList/themes/navy/screen.css";
include_once(commonStuffAbsolutePath."eiseList/inc_eiseList.php");

$arrJS[] = jQueryUIRelativePath."js/jquery-ui-1.8.16.custom.min.js";
$arrCSS[] = jQueryUIRelativePath."css/redmond/jquery-ui-1.8.16.custom.css";

$listName = $listName ? $listName : "usr";
$lst = new eiseList($oSQL, $listName
, Array('title'=>$arrUsrData["pagTitle$strLocal"]
, 'sqlFrom' => "stbl_user"
, 'defaultOrderBy'=>"usrEditDate"
, 'defaultSortOrder'=>"DESC"
, 'intra' => $intra));

$lst->Columns[] = array('title' => ""
        , 'field' => 'usrID'
        , 'PK' => true
        );

$lst->Columns[] = array('title' => "##"
        , 'field' => "phpLNums"
        , 'type' => "num"
        );

$lst->Columns[] = array('title' => "Login"
        , 'type'=>"text"
        , 'field' => "usrID_"
        , 'sql' => "usrID"
        , 'filter' => "usrID"
        , 'order_field' => "usrID_"
        , 'href' => "user_form.php?usrID=[usrID]"
        );
$lst->Columns[] = array('title' => "Full Name"
        , 'type'=>"text"
        , 'field' => "usrName"
        , 'filter' => "usrName"
        , 'order_field' => "usrName"
        , 'href' => "user_form.php?usrID=[usrID]"
        , 'width' => "50%"
        );
$lst->Columns[] = array('title' => "Full Name (Local)"
        , 'type'=>"text"
        , 'field' => "usrNameLocal"
        , 'filter' => "usrNameLocal"
        , 'order_field' => "usrNameLocal"
        , 'href' => "user_form.php?usrID=[usrID]"
        , 'width' => "50%"
        );

$lst->Columns[] = array('title' => "usrPhone"
        , 'type'=>"text"
        , 'field' => "usrPhone"
        , 'filter' => "usrPhone"
        , 'order_field' => "usrPhone"
        );
$lst->Columns[] = array('title' => "usrEmail"
        , 'type'=>"text"
        , 'field' => "usrEmail"
        , 'filter' => "usrEmail"
        , 'order_field' => "usrEmail"
        );

$lst->Columns[] = array('title' => "Deleted"
        , 'type'=>"text"
        , 'field' => "usrFlagDeleted"
        , 'filter' => "usrFlagDeleted"
        , 'order_field' => "usrFlagDeleted"
        );
$lst->Columns[] = array('title' => "Updated"
        , 'type'=>"date"
        , 'field' => "usrEditDate"
        , 'filter' => "usrEditDate"
        , 'order_field' => "usrEditDate"
        );


$lst->handleDataRequest();

if ($arrUsrData['FlagWrite']){
    $arrActions[]= Array ('title' => "New"
       , 'action' => "user_form.php"
       , 'class' => "ss_add"
    );
}

include eiseIntraAbsolutePath."inc-frame_top.php";

$lst->show();

include eiseIntraAbsolutePath."inc-frame_bottom.php";
?>