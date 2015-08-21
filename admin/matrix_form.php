<?php
include "common/auth.php";

$oSQL->dbname=(isset($_POST["dbName"]) ? $_POST["dbName"] : $_GET["dbName"]);
$oSQL->select_db($oSQL->dbname);
$dbName = $oSQL->dbname;

$rolID = (isset($_POST["rolID"]) ? $_POST["rolID"] : $_GET["rolID"]);

switch ($_POST["DataAction"]){
   
   case "update":
       
       $sqlPages = "SELECT pgrID, pagID FROM stbl_page LEFT OUTER JOIN stbl_page_role ON pagID=pgrPageID AND pgrRoleID='$rolID'";
       $rsPGR = $oSQL->do_query($sqlPages);
       
       while ($rwPGR = $oSQL->fetch_array($rsPGR)){
          if ($_POST["read".$rwPGR["pagID"]]=="on")
              $flagRead = 1;
          else 
              $flagRead = 0;
          if ($_POST["write".$rwPGR["pagID"]]=="on")
              $flagWrite = 1;
          else 
              $flagWrite = 0;
          
//          echo "<pre>";
//          print_r($_POST);
//          echo "</pre>";
          if ($rwPGR["pgrID"]==""){
              $sqlUpdate = "INSERT INTO stbl_page_role (
                    pgrPageID
                    , pgrRoleID
                    , pgrFlagRead
                    , pgrFlagWrite
                    , pgrInsertBy, pgrInsertDate, pgrEditBy, pgrEditDate
                    , pgrFlagCreate
                    , pgrFlagUpdate
                    , pgrFlagDelete
                    ) VALUES (
                    '{$rwPGR["pagID"]}'
                    , '{$_POST["rolID"]}'
                    , $flagRead
                    , $flagWrite
                    , '{$usrID}', NOW(), '{$usrID}', NOW()
                    , 0
                    , 0
                    , 0);";
          } else {
              $sqlUpdate = "UPDATE stbl_page_role
                SET pgrFlagRead=$flagRead
                , pgrFlagWrite=$flagWrite
                , pgrEditBy='$usrID'
                , pgrEditDate=NOW()
                WHERE pgrID=".$rwPGR["pgrID"];
          }
          
            $oSQL->do_query($sqlUpdate);
       }
       
       SetCookie("UserMessage", "Matrix is updated");
       header("Location: {$_SERVER['PHP_SELF']}?dbName=$$oSQL->dbname&rolID=$rolID");
       
       die();
       break;
   default:
       break;
}

include eiseIntraAbsolutePath."inc-frame_top.php";

?>

<h1><?php echo $dbName; ?> security matrix</h1>

<script>
function SwitchRole(selObj){
   var val = selObj.options[selObj.selectedIndex].value;
   
   location.href="<?php echo $PHP_SELF ; ?>?dbName=<?php echo $dbName ; ?>&rolID="+val;
}
</script>

Select role:

<select name="rolID" onchange="SwitchRole(this);">
<option value="">-- page list --
<?php 
$sqlROL = "SELECT * FROM stbl_role";
$rsROL = $oSQL->do_query($sqlROL);

while($rwROL = $oSQL->fetch_array($rsROL)){
 ?>
<option value="<?php echo $rwROL["rolID"] ; ?>"<?php if ($rolID==$rwROL["rolID"]) echo " selected"?>><?php echo $rwROL["rolTitle"] ; ?>
<?php
}
?>
</select>

<?php

$sqlPages = "SELECT PG1.pagID
        , PG1.pagParentID
        , PG1.pagTitle
        , PG1.pagFile
        , PG1.pagIdxLeft
        , PG1.pagIdxRight
        , PG1.pagFlagShowInMenu
        , COUNT(PG2.pagID) as iLevelInside";
if ($rolID) $sqlPages.= "
        , PGR.pgrID
        , PGR.pgrFlagRead
        , PGR.pgrFlagWrite
        , ROL.rolID
        , ROL.rolTitle";
$sqlPages .= "
FROM stbl_page PG1
        INNER JOIN stbl_page PG2 ON PG2.pagIdxLeft<=PG1.pagIdxLeft AND PG2.pagIdxRight>=PG1.pagIdxRight";
if ($rolID) $sqlPages.= "
        LEFT OUTER JOIN stbl_page_role PGR INNER JOIN stbl_role ROL ON PGR.pgrRoleID=ROL.rolID
        ON PG1.pagID = PGR.pgrPageID AND pgrRoleID='$rolID'";
$sqlPages.= "
GROUP BY 
PG1.pagID
        , PG1.pagParentID
        , PG1.pagTitle
        , PG1.pagFile
        , PG1.pagIdxLeft
        , PG1.pagIdxRight
        , PG1.pagFlagShowInMenu";
if ($rolID) $sqlPages.= "
        , PGR.pgrID
        , PGR.pgrFlagRead
        , PGR.pgrFlagWrite
        , ROL.rolID
        , ROL.rolTitle";
$sqlPages.= "
ORDER BY PG1.pagIdxLeft
";
/*
echo "<pre>";
echo $sqlPages;
echo "</pre>";
//*/
$rsPages = $oSQL->do_query($sqlPages);

?>
<div class="panel">
<form action="<?php echo $PHP_SELF ; ?>" method="POST">
<input type="hidden" name="DataAction" value="update">
<input type="hidden" name="dbName" value="<?php echo $dbName ; ?>">
<input type="hidden" name="rolID" value="<?php echo $rolID ; ?>">
<table bgcolor="#000000" cellspacing="1" cellpadding="3" border="1" width="100%">

<tr class="th" style="background-color: #eee">
<td>##</td>
<td width="100%">Page title</td>
<?php if ($rolID) { ?>
<td>Read</td>
<td>Write</td>
<?php } ?>
<td>Actions</td>
</tr>

<?php
$iCounter = 1;
while ($rwPag = $oSQL->fetch_array($rsPages)) {
     ?>
<tr class="tr<?php echo $iCounter % 2 ; ?>" valign="top">
<td>&nbsp;<?php echo $iCounter ; ?>&nbsp;</td>
<td width="100%">

<table cellspacing="0" cellapadding="0" border="0">
<tr>
	<td><img src="../common/images/spacer.gif" width="<?php echo 20*((int)$rwPag["iLevelInside"]-1) ; ?>" height="1">&nbsp;</td>
	<td width="100%">
<?php 
if ($rwPag["pagFlagShowInMenu"]) echo "<b>";

echo $rwPag["pagTitle"] ; 

if ($rwPag["pagFlagShowInMenu"]) echo "</b>";
?><br>
<span style="font-size:9px;"><?php echo $rwPag["pagFile"] ; ?>
<?php if (!$rolID){ ?>
<br>
[ 
<a href="page_form.php?dbName=<?php echo $dbName ; ?>&pagParentID=<?php echo $rwPag["pagID"] ; ?>">Add page below</a> 
] [ 
<a href="page_form.php?dbName=<?php echo $dbName ; ?>&pagID=<?php echo $rwPag["pagID"] ; ?>&pagParentID=<?php echo $rwPag["pagParentID"] ; ?>&DataAction=move&direction=up">Move up</a> 
] [ 
<a href="page_form.php?dbName=<?php echo $dbName ; ?>&pagID=<?php echo $rwPag["pagID"] ; ?>&pagParentID=<?php echo $rwPag["pagParentID"] ; ?>&DataAction=move&direction=down">Move down</a> 
]
<?php } ?></span><br>
</td>  
    
</tr>
</table>


</td>
<?php if ($rolID) { ?>
<td align="center"><input type="checkbox" name="read<?php echo $rwPag["pagID"] ; ?>" style="width:auto;"<?php 
if ($rwPag["pgrFlagRead"]) echo "checked";
?>></td>
<td align="center"><input type="checkbox" name="write<?php echo $rwPag["pagID"] ; ?>" style="width:auto;"<?php 
if ($rwPag["pgrFlagWrite"]) echo "checked";
?>></td>
<?php } ?>
<td>[<a href="page_form.php?dbName=<?php echo $dbName ; ?>&pagID=<?php echo $rwPag["pagID"] ; ?>">edit</a>]</td>
</tr>
     <?php
     $iCounter++;
}
?>
</table>
<?php if ($rolID) { ?>
<br>
<input type="submit" value="Set security settings..."><br>

<?php } ?>
</form>
</div>


<?php
include eiseIntraAbsolutePath."inc-frame_bottom.php";
?>