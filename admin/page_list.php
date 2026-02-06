<?php
//$_DEBUG = true;
include "common/auth.php";

//-----------------------for debug
//$rolID = 2;
$DataAction = (!empty($_POST["DataAction"]) ? $_POST["DataAction"] 
    : (!empty($_GET["DataAction"]) 
        ? $_GET["DataAction"] 
        : '')
    );

switch ($DataAction){

   case "update":

       $sqlPages = "SELECT pgrID FROM stbl_page_role WHERE pgrRoleID=$rolID";
       $rsPGR = $oSQL->do_query($sqlPages);

       while ($rwPGR = $oSQL->fetch_array($rsPGR)){
          if ($_POST["read".$rwPGR["pgrID"]]=="on")
              $flagRead = 1;
          else
              $flagRead = 0;
          if ($_POST["write".$rwPGR["pgrID"]]=="on")
              $flagWrite = 1;
          else
              $flagWrite = 0;

//          echo "<pre>";
//          print_r($_POST);
//          echo "</pre>";

          $sqlUpdate = "UPDATE stbl_page_role
            SET pgrFlagRead=$flagRead
            , pgrFlagWrite=$flagWrite
            , pgrEditBy=N'$usrID'
            , pgrEditDate=CURRENT_DATE()
            WHERE pgrID=".$rwPGR["pgrID"];

//            echo $sqlUpdate;

            $oSQL->do_query($sqlUpdate);
       }

       $intra->redirect("Page/role matrix is updated", $_SERVER['PHP_SELF']."?dbName=$dbName&rolID=$rolID");

       die();
       break;
   default:
       break;
}


$arrActions[]= Array ("title" => "New script"
	   , "action" => "page_form.php?dbName=$dbName"
	   , "class"=> "ss_add"
	);

$arrActions[]= Array ("title" => "Repair"
     , "action" => "page_form.php?dbName=$dbName&DataAction=repair"
     , "class"=> "ss_wrench"
);  

$arrActions[]= Array ("title" => "Get dump"
     , "action" => "javascript:$(this).eiseIntraBatch('database_act.php?DataAction=dump&what=security&dbName={$dbName}&flagDonwloadAsDBSV=0')"
     , "class"=> "ss_cog_edit"
);
$arrActions[]= Array ("title" => "Download dump"
	   , "action" => "database_act.php?DataAction=dump&what=security&dbName={$dbName}&flagDonwloadAsDBSV=1"
	   , "class"=> "ss_cog_go"
);

$intra->requireComponent('simpleTree', 'batch');
$intra->conf['flagBatchNoAutoclose'] = true;

include eiseIntraAbsolutePath."inc_top.php";

?>

<h1>Page matrix for <?php echo ucfirst($intra->getDBName()); ?></h1>


<?php

$sqlPages = "SELECT PG1.pagID
    , PG1.pagParentID
    , PG1.pagTitle
	, PG1.pagTitleLocal
    , PG1.pagFile
    , PG1.pagIdxLeft
    , PG1.pagIdxRight
    , PG1.pagFlagShowInMenu
    , COUNT(DISTINCT PG2.pagID) as iLevelInside
    FROM stbl_page PG1
    INNER JOIN stbl_page PG2 ON PG2.pagIdxLeft<=PG1.pagIdxLeft AND PG2.pagIdxRight>=PG1.pagIdxRight
    GROUP BY PG1.pagID
    , PG1.pagParentID
    , PG1.pagTitle
	, PG1.pagTitleLocal
    , PG1.pagFile
    , PG1.pagIdxLeft
    , PG1.pagIdxRight
    , PG1.pagFlagShowInMenu
    ORDER BY PG1.pagIdxLeft";

$rsPages = $oSQL->do_query($sqlPages);

?>

<style type="text/css">
.simpleTree .trigger {
    width: 33px;
}
</style>

<form id="matrixForm" name="matrixForm" action="<?php echo $_SERVER["PHP_SELF"] ; ?>" method="POST">
<input type="hidden" name="DataAction" value="update">
<input type="hidden" id="dbName" name="dbName" value="<?php echo $dbName ; ?>">

<?php
	$iLevelInstide_old = 1;
?>
<ul class="simpleTree" id="tree">
<?php
   	
while ($rw = $oSQL->fetch_array($rsPages)){
		for ($i=$iLevelInstide_old; $i>$rw["iLevelInside"]; $i--)
           echo "</ul>\r\n\r\n";
		
        for ($i=$iLevelInstide_old; $i<$rw["iLevelInside"]; $i++)
           echo "<ul>\r\n";
           
        echo "<li";
              
		if ($rw["pagID"]==1) {
				echo " class=\"root\"";
		} else if ($rw["pagParentID"]==1){
				echo " class=\"open\"";
		}
		echo " id=\"".$rw["pagID"]."\">";
        echo "<span>".($rw["pagFlagShowInMenu"]==1? "<strong>" : "").
                  ($rw["pagTitle{$intra->local}"]!="" ? $rw["pagTitle{$intra->local}"] : $rw["pagTitle"]).
                  ($rw["pagFlagShowInMenu"]==1? "</strong>" : "").($rw["pagFile"]!="" ? " (".$rw["pagFile"].")" : "")."</span>";
        echo "\r\n";
        
        $iLevelInstide_old = $rw["iLevelInside"];     
}        
?>
</ul>
</form>



<?php
include eiseIntraAbsolutePath."inc_bottom.php";
?>