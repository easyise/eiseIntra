<?php
//$_DEBUG = true;
include "common/auth.php";

$oSQL->startProfiling();

$DataAction = $_GET["DataAction"] ? $_GET["DataAction"] : $_POST["DataAction"];
$pagID = $_GET["pagID"] ? $_GET["pagID"] : $_POST["pagID"];
$pos = $_GET["pos"] ? $_GET["pos"] : $_POST["pos"];
$pagParentID = $_GET["pagParentID"] ? $_GET["pagParentID"] : $_POST["pagParentID"];


$dbName = $_GET["dbName"] ? $_GET["dbName"] : $_POST["dbName"];
$oSQL->select_db($dbName);

$intra->requireComponent('grid');

$grid = new easyGrid($oSQL
					, "page_role"
                    , Array(
                            'rowNum' =>40
                            , 'flagEditable'=> true
                            , 'flagKeepLastRow' => false
                            , 'arrPermissions' => Array('FlagWrite'=> true)
                            , 'strTable' => "stbl_page_role"
                            , 'strPrefix' => "pgr"
                            , 'flagStandAlone' => false
                            )
                    );
                        
$grid->Columns[]=Array(
	'field'=>"pgrID"
	,'type'=>'row_id'
);
$grid->Columns[] = Array(
	'title'=>'Role'
	, 'field'=>'pgrRoleID'
	, 'type'=>'combobox'
	, 'sql'=>"SELECT rolID as optValue, CONCAT(rolID,' | ',rolTitle$strLocal) as optText FROM stbl_role"
	, 'disabled'=>true
    , 'mandatory' => true
    , 'width' => '50%'
);
$grid->Columns[]=Array(
	'title'=>"Read"
	,'field'=>"pgrFlagRead"
	,'type'=>'checkbox'
  , 'width' => '10%'
);
$grid->Columns[]=Array(
	'title'=>"Write"
	,'field'=>"pgrFlagWrite"
	,'type'=>'checkbox'
  , 'width' => '10%'
);
$grid->Columns[]=Array(
	'title'=>"Insert"
	,'field'=>"pgrFlagCreate"
	,'type'=>'checkbox'
  , 'width' => '10%'
);
$grid->Columns[]=Array(
	'title'=>"Update"
	,'field'=>"pgrFlagUpdate"
	,'type'=>'checkbox'
  , 'width' => '10%'
);
$grid->Columns[]=Array(
	'title'=>"Delete"
	,'field'=>"pgrFlagDelete"
	,'type'=>'checkbox'
  , 'width' => '10%'
);

$sql = "SELECT stbl_page_role.* 
FROM stbl_role LEFT OUTER JOIN stbl_page_role ON rolID=pgrRoleID
WHERE pgrPageID='$pagID'";
$rs = $oSQL->do_query($sql);
while ($rw = $oSQL->fetch_array($rs)) $grid->Rows[] = $rw;
$oSQL->free_result($rs);

/*------------------------------------------- Page tree function------------------------------------------------------*/
function RecalculatePageTree($oSQL, $pagParentID, &$iCounter){
    GLOBAL $dbName;
    
    if (is_null($pagParentID)) {
        $rsPAG = $oSQL->do_query("SELECT pagID, pagTitle, pagIdxLeft, pagIdxRight FROM stbl_page WHERE pagParentID IS NULL ORDER BY pagIdxLeft");
    } else {
        $rsPAG = $oSQL->do_query("SELECT pagID, pagTitle, pagIdxLeft, pagIdxRight FROM stbl_page WHERE pagParentID =".$pagParentID." ORDER BY pagIdxLeft");
    }

  while ($rwPAG = $oSQL->fetch_array($rsPAG)){
    $pagID = $rwPAG["pagID"];
    $pagIdxLeft = $iCounter+1;
    $iCounter = $pagIdxLeft;
    $sqlUpdate = "UPDATE stbl_page SET pagIdxLeft=".$pagIdxLeft." WHERE pagID = ".$pagID;
    $oSQL->do_query($sqlUpdate);

    RecalculatePageTree($oSQL, $pagID, $iCounter);

    $pagIdxRight = $iCounter+1;
    $iCounter = $pagIdxRight;
    $sqlUpdate = "UPDATE stbl_page SET pagIdxRight=".$pagIdxRight." WHERE pagID = ".$pagID;
    $oSQL->do_query($sqlUpdate);

  }
}




if (isset($DataAction)){
    switch($DataAction){
      case "update":
        $rsPage = $oSQL->do_query("SELECT * FROM stbl_page WHERE pagID='{$_POST["pagID"]}'");
        $ffPage = $oSQL->ff($rsPage);
        $rwPage = $oSQL->fetch_array($rsPage);
        $sql = Array();
           //echo $pagFlagShowInMenu;
        if (!$pagID) {
           $sql[] = "START TRANSACTION;";
		   /* acknowledging right from parent */
		   //$sql[] = "DECLARE @pagIdxLeft,@pagIdxRight, @pagID;\r";
           $sql[] = "SELECT @pagIdxLeft := pagIdxRight, @pagIdxRight := pagIdxRight+1 FROM stbl_page WHERE pagID=$pagParentID;\r";
		   
		   /* first updating all lefts and rights */
           $sql[] = "UPDATE stbl_page SET pagIdxLeft=pagIdxLeft+2 WHERE pagIdxLeft > @pagIdxRight;\r";
           $sql[] = "UPDATE stbl_page SET pagIdxRight=pagIdxRight+2 WHERE pagIdxRight >= @pagIdxRight;\r";
		   /* then inserting needed record */
           $sql[] = "INSERT INTO stbl_page SET 
             pagParentID = {$pagParentID}
             , pagFile = ".$oSQL->escape_string($_POST["pagFile"])."
             , pagTitle = ".$oSQL->escape_string($_POST["pagTitle"])."
			 , pagTitleLocal = ".$oSQL->escape_string($_POST["pagTitleLocal"])."
             , pagFlagShowInMenu = ".($_POST["pagFlagShowInMenu"]=="on" ? "1" : "0")."
			 , pagFlagSystem = ".($_POST["pagFlagSystem"]=="on" ? "1" : "0")."
       , pagFlagHierarchy = ".($_POST["pagFlagHierarchy"]=="on" ? "1" : "0")."
			 , pagFlagShowMyItems = ".($_POST["pagFlagShowMyItems"]=="on" ? "1" : "0")."
			 , pagTable = ".$oSQL->escape_string($_POST["pagTable"])."
			 , pagEntityID = ".$oSQL->escape_string($_POST["pagEntityID"])."
             , pagIdxLeft = @pagIdxLeft
             , pagIdxRight = @pagIdxRight
             ".(isset($ffPage['pagMenuItemClass']) ? ', pagMenuItemClass='.$oSQL->e($_POST['pagMenuItemClass']) : '')."
             , pagInsertBy = '$usrID'
             , pagInsertDate = NOW()
             , pagEditBy = '$usrID'
             , pagEditDate = NOW()
             ;\r";
			
			$sql[] = "SELECT @pagID := LAST_INSERT_ID();\r";
			
			$sql[] = "INSERT INTO stbl_page_role(
           pgrPageID
           , pgrRoleID
           , pgrFlagRead
		   , pgrFlagCreate
		   , pgrFlagUpdate
		   , pgrFlagDelete
           , pgrFlagWrite
           , pgrInsertBy
           , pgrInsertDate
           , pgrEditBy
           , pgrEditDate
           ) SELECT
            @pagID as pgrPageID
           , rolID as pgrRoleID
           , 0 as pgrFlagRead
		   , 0 as pgrFlagCreate
		   , 0 as prgFlagUpdate
		   , 0 as pgrFlagDelete
           , 0 as pgrFlagWrite
           , '$usrID' as pgrInsertBy
           , NOW()
           , '$usrID' as pgrEditBy
           , NOW()
           FROM stbl_role;\r";			
			
			$sql[] = "update `stbl_page_role`
							set pgrFlagRead = 1
							,pgrFlagCreate = 0
							,pgrFlagUpdate = 0
							,pgrFlagDelete = 0
							,pgrFlagWrite = 0
							where pgrRoleID = 'Admin'
                            AND pgrPageID=@pagID;";
							
			$sql[] = "COMMIT;\r";
//           echo $sqlInsertNode."<br>";
			/*echo "<pre>";
			for($i=0;$i<count($sqlInsertNode);$i++)	echo $sqlInsertNode[$i];
			echo "</pre>";
			die();*/
			
			for($i=0;$i<count($sqlInsertNode);$i++)	$oSQL->do_query($sqlInsertNode[$i]);
                
        } else {

           /* updating rubric as itself */
           $sqlUpdateNode = "UPDATE stbl_page
               SET pagParentID='{$_POST["pagParentID"]}'
               , pagFile=".$oSQL->escape_string($_POST["pagFile"])."
               , pagTitle=".$oSQL->escape_string($_POST["pagTitle"])."
			   , pagTitleLocal=".$oSQL->escape_string($_POST["pagTitleLocal"]);
			
			if ($pagTable && $pagPrefix) {
				if($tableSuccess=createTable($oSQL, $pagTable, $pagPrefix)) {
					$sqlUpdateNode .= ",pagTable = ".$oSQL->escape_string($pagTable);
					$sqlUpdateNode .= ",pagPrefix = ".$oSQL->escape_string($pagPrefix);
				}
			}
			
            $sqlUpdateNode .= ", pagFlagShowInMenu=".($_POST["pagFlagShowInMenu"]=="on" ? "1" : "0")."
				, pagFlagSystem = ".($_POST["pagFlagSystem"]=="on" ? "1" : "0")."
        , pagFlagHierarchy = ".($_POST["pagFlagHierarchy"]=="on" ? "1" : "0")."
				, pagFlagShowMyItems = ".($_POST["pagFlagShowMyItems"]=="on" ? "1" : "0")."
                , pagTable = ".$oSQL->escape_string($_POST["pagTable"])."
                , pagEntityID = ".$oSQL->escape_string($_POST["pagEntityID"])."
                ".(isset($ffPage['pagMenuItemClass']) ? ', pagMenuItemClass='.$oSQL->e($_POST['pagMenuItemClass']) : '')."
               , pagEditBy='$usrID'
               , pagEditDate=CURRENT_DATE()
               WHERE pagID=".$_POST["pagID"];
            $sql[] = $sqlUpdateNode;      
        }
        
       /*
          echo "<pre>";
          print_r($_POST);
          print_r($sql);
          echo "</pre>";
          die();
        //*/
        for ($i=0;$i<count($sql);$i++) {
              $oSQL->do_query($sql[$i]);
              if (preg_match("/^INSERT INTO stbl_page\(/", $sql[$i]))
                  $pagID = $oSQL->insert_id();              
        }
       
        $grid->Update();
        //die();
        
        if ($_POST["pagParentID"]!=$rwPage["pagParentID"]) {
              $iCounter = 0;
              RecalculatePageTree($oSQL, NULL, $iCounter);
        }
        /*
        $oSQL->showProfileInfo();
        die();
        */
		/*	
		if ($pagTable && $pagPrefix) 
           createTable($oSQL, $pagTable, $pagPrefix, true, $pagFlagHierarchy, $pagFlagSystem);
	    createScript($pagTable, $pagPrefix );
        */
        //if ($_POST["pagFile"])
        //    createPage($_POST["pagFile"]);
        
		SetCookie("UserMessage", "Page is changed successfully");
        header("Location: ".$_SERVER["PHP_SELF"]."?dbName=$dbName&pagID=$pagID");
        die();

        break;
      case "move":
			
            // 1. determinig kids from new parent
            $sqlKids  = "SELECT pagID, pagIdxLeft
               FROM stbl_page 
               WHERE pagParentID='".$_GET["pagParentID"]."'
               ORDER BY pagIdxLeft";
            $rsKids = $oSQL->do_query($sqlKids);
            $arrKids = Array();
            while ($rwKids = $oSQL->fetch_array($rsKids)){
               $arrKids[] = $rwKids;
            }
            
            //print_r($_GET);
            //print_r($arrKids);
            
            // 2. determining idxLeft for predecessor of newcomer
            if (count($arrKids) > 0) {
                
                switch ($_GET["pos"]){
                    case "0":  // beginning of the list
                       $pagIdxLeft_new = $arrKids[0]["pagIdxLeft"]-1;
                       break;
                    case (count($arrKids)-1): // end
                       $pagIdxLeft_new = $arrKids[count($arrKids)-1]["pagIdxLeft"]+1;
                       break;
                    default:
                       $pagIdxLeft_new = $arrKids[$_GET["pos"]-1]["pagIdxLeft"]+1;
                       break;
                }
            } else 
                $pagIdxLeft_pred = 0;
            
            // 3. updating newcomer
            $sqlUpd = "UPDATE stbl_page SET 
                pagParentID='".$_GET["pagParentID"]."'
                , pagIdxLeft='".(int)($pagIdxLeft_new)."'\r\n". // it is finely suitable, page will be sorted as intended
                "WHERE pagID='".$_GET["pagID"]."'";
            $oSQL->do_query($sqlUpd);
            
            echo $sqlUpd;
            
            // 4. do the recalculate
            $iCounter = 0;
            RecalculatePageTree($oSQL, NULL, $iCounter);
            
           break;
        case "repair":
           $iCounter = 0;
           RecalculatePageTree($oSQL, NULL, $iCounter);

           SetCookie("UserMessage", "Page tree is successfully repaired");
           header("Location: page_list.php?dbName={$dbName}");
           die();
           break;

        case "delete":
           /* determining left and right for the node we deleting */
           $rsPAG = $oSQL->do_query("SELECT pagIdxLeft, pagIdxRight FROM stbl_page WHERE pagID=".$pagID);
           $rwPag = $oSQL->fetch_array($rsPAG);
           $pagIdxLeft = $rwPag["pagIdxLeft"];
           $pagIdxRight = $rwPag["pagIdxRight"];

           $sqlDelete = "DELETE FROM stbl_page WHERE pagIdxLeft>=".$pagIdxLeft." AND pagIdxRight<=".$pagIdxRight;
           $oSQL->do_query($sqlDelete);

           $iCounter = 0;
           RecalculatePageTree($oSQL, NULL, $iCounter);

           SetCookie("UserMessage", "Page is successfully deleted.");

           header("Location: page_list.php?dbName=$dbName");

           break;
    }
    die;
}

$sqlPAG = "SELECT * FROM stbl_page WHERE pagID='$pagID'";
$rsPAG = $oSQL->do_query($sqlPAG);
$ffPage = $oSQL->ff($rsPAG);
$rwPAG = $oSQL->fetch_array($rsPAG);

if (isset($_GET["pagID"])){
$arrActions[]= Array ("title" => "New item below"
	   , "action" => "page_form.php?dbName=$dbName&pagParentID=".urlencode($_GET["pagID"])
	   , "class"=> "ss_arrow_down"
	);
$arrActions[]= Array ("title" => "New sibling"
	   , "action" => "page_form.php?dbName=$dbName&pagParentID=".urlencode($rwPAG["pagParentID"])
	   , "class"=> "ss_arrow_right"
	);
}


if (!isset($_GET["pagID"]) && isset($_GET["pagParentID"]))
   $rwPAG["pagParentID"] = $_GET["pagParentID"];

include eiseIntraAbsolutePath."inc_top.php";
?>

<style type="text/css">
#flds-general, #flds-privileges {
    display: inline-block;
    vertical-align: top;
}

#flds-general {
    width: 37%;
}

#flds-privileges {
  width: 61%;
}

#div-buttons {
  text-align: center;
}
</style>

<script>
$(document).ready(function(){  
    var $frm = $('form#pageForm');
	$('.eiseGrid').eiseGrid();
    $('#btn-del').click(function(){
        if(deletePage()){
            $frm.find('input,textarea,select').filter('[required]:visible').each(function(){
                $(this).removeAttr('required');
            });
            $frm.submit();
        }
    })
});
</script>


<form id="pageForm" name="pageForm" class="eiseIntraForm" method="POST" action="<?php echo $_SERVER["PHP_SELF"] ; ?>">
<input type="hidden" name="DataAction" id="DataAction" value="update">
<input type="hidden" name="pagID" value="<?php echo $rwPAG["pagID"] ; ?>">
<input type="hidden" name="pagOldParentID" value="<?php echo $rwPAG["pagParentID"] ; ?>">

<fieldset id="flds-general"><legend><?php echo $intra->translate('Page').': '.$rwPAG["pagTitle{$intra->local}"]; ?></legend>
<?php 
echo $intra->field('Title', 'pagTitle', $rwPAG["pagTitle"]);

echo $intra->field('Наименование', 'pagTitleLocal', $rwPAG["pagTitleLocal"]);

echo (isset($ffPage['pagMenuItemClass']) ? $intra->field('Menu Item Class', 'pagMenuItemClass', $rwPAG["pagMenuItemClass"]) : '');

$sqlPages = "SELECT PG1.pagID as optValue
        , PG1.pagTitle as optText
        , COUNT(PG2.pagID) as optLevelInside
FROM stbl_page PG1
        INNER JOIN stbl_page PG2 ON PG2.pagIdxLeft<=PG1.pagIdxLeft AND PG2.pagIdxRight>=PG1.pagIdxRight
GROUP BY
PG1.pagID
, PG1.pagTitle
, PG1.pagIdxLeft
ORDER BY PG1.pagIdxLeft";
    $rsPages = $oSQL->q($sqlPages);
while($rw = $oSQL->f($rsPages)){
    $arrOptions[$rw["optValue"]] = $rw["optText"];
    $arrIndent[$rw["optValue"]] = $rw["optLevelInside"];
}

echo $intra->field($intra->translate('Parent'), 'pagParentID', $rwPAG["pagParentID"], array('type'=>'select', 'source'=>$arrOptions, 'indent'=>$arrIndent));

echo $intra->field($intra->translate('PHP script'), 'pagFile', $rwPAG["pagFile"]);

echo $intra->field($intra->translate('Show in menu'), 'pagFlagShowInMenu', $rwPAG["pagFlagShowInMenu"], array('type'=>'boolean'));

echo $intra->field($intra->translate('Entity'), 'pagEntityID', $rwPAG["pagEntityID"], array('type'=>'text'));

echo $intra->field($intra->translate('Show "My Items"'), 'pagFlagShowMyItems', $rwPAG["pagFlagShowMyItems"], array('type'=>'boolean'));

 ?>
<div id="div-buttons">
<input type="submit" value="Save" class="eiseIntraSubmit">
<?php 
if ($pagID) {
 ?>
<input type="submit" value="Delete" id="btn-del">
<?php 
}
 ?>
</div>
</fieldset>

<fieldset id="flds-privileges"><legend>Privileges</legend>
<div><?php $grid->Execute(); ?></div></fieldset>


</form>



</div>

<script>
function CheckForm(){
   return true;
}

function deletePage(){
  if (confirm("Are you ready you want to delete this page?")){
      $('#DataAction').val('delete');
      return true;
  }
}
</script>

<?php
include eiseIntraAbsolutePath."inc_bottom.php";
?>