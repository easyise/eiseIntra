<?php
/*-----------------------------Standard menu from stbl_page table---------------------------------*/	
$sql = "SELECT PG1.pagID
            , PG1.pagParentID
            , PG1.pagTitle{$intra->local}
            , PG1.pagFile
            , PG1.pagIdxLeft
            , PG1.pagIdxRight
            , PG1.pagFlagShowInMenu
            , PG1.pagEntityID
            , COUNT(DISTINCT PG2.pagID) as iLevelInside
            , (SELECT COUNT(*) FROM stbl_page CH 
                WHERE CH.pagParentID=PG1.pagID AND CH.pagFlagShowInMenu=1) as nChildren
            , MAX(PGR.pgrFlagRead) as FlagRead
            , MAX(PGR.pgrFlagWrite) as FlagWrite
    FROM stbl_page PG1
            INNER JOIN stbl_page PG2 ON PG2.pagIdxLeft<=PG1.pagIdxLeft AND PG2.pagIdxRight>=PG1.pagIdxRight
            INNER JOIN stbl_page PG3 ON PG3.pagIdxLeft BETWEEN PG1.pagIdxLeft AND PG1.pagIdxRight AND PG3.pagFlagShowInMenu=1
            INNER JOIN stbl_page_role PGR ON PG1.pagID = PGR.pgrPageID
            INNER JOIN stbl_role ROL ON PGR.pgrRoleID=ROL.rolID
            LEFT JOIN stbl_role_user RLU ON PGR.pgrRoleID=RLU.rluRoleID
    WHERE 
     (RLU.rluUserID='{$intra->usrID}' OR ROL.rolFlagDefault=1)
     AND PG1.pagFlagShowInMenu=1
    GROUP BY 
            PG1.pagID
            , PG1.pagParentID
            , PG1.pagTitle{$intra->local}
            , PG1.pagFile
            , PG1.pagIdxLeft
            , PG1.pagIdxRight
            , PG1.pagFlagShowInMenu
    HAVING (MAX(PGR.pgrFlagRead)=1 OR MAX(PGR.pgrFlagWrite)=1) 
    ORDER BY PG1.pagIdxLeft";
    
    $rs = $oSQL->do_query($sql);
	?>

    
<ul class="simpleTree">
<li id="menu_root" class="root"><span><strong>Menu</strong></span>
<?php
$strOutput .= "";	
$rw_old["iLevelInside"] = 1;

while ($rw = $oSQL->fetch_array($rs)){
    
    $rw["pagFile"] = preg_replace("/^\//", "", $rw["pagFile"]);
    
    $hrefSuffix = "";
    
    for ($i=$rw_old["iLevelInside"]; $i>$rw["iLevelInside"]; $i--)
       echo "</ul></li>\r\n\r\n";
    
    for ($i=$rw_old["iLevelInside"]; $i<$rw["iLevelInside"]; $i++)
       echo "<ul>";
    
    if (preg_match("/list\.php$/", $rw["pagFile"]) && $rw["pagEntityID"]!=""){
       $hrefSuffix = "?".$rw["pagEntityID"]."_staID=";
    }
    
    $flagIsEntity = ($rw["pagFile"]=="entity_form.php" && $rw["pagEntityID"]=="ent" ? true : false);
    
    echo "<li".($rw["pagParentID"]==1
             ? " class='open'"
             : "")." id='".$rw["pagID"]."'>".
      ($rw["pagFile"] && !$flagIsEntity && !($rw["pagFile"]=="entity_form.php" && $rw["pagEntityID"]=="ent")
        ? "<a target='pane' href='".$rw["pagFile"].$hrefSuffix."'>"
        : "")
      ."<span>".$rw["pagTitle{$intra->local}"]."</span>".
      ($rw["pagFile"] && !$flagIsEntity
        ? "</a>"
        : ""
        )
        .($rw["nChildren"]==0 && !($rw["pagFile"]=="entity_form.php" && $rw["pagEntityID"]=="ent") ? "</li>" : "")."\r\n";
   
   if ($hrefSuffix){
      $sqlSta = "SELECT * FROM stbl_status WHERE staEntityID='".$rw["pagEntityID"]."' AND staFlagDeleted=0";
      $rsSta = $oSQL->do_query($sqlSta);
      while ($rwSta = $oSQL->fetch_array($rsSta)){
         echo "<li id='".$rw["pagID"]."_".$rwSta["staID"]."'><a target='pane' href='".
            $rw["pagFile"]."?".$rw["pagEntityID"]."_staID=".$rwSta["staID"]."'>".$rwSta["staTitle{$intra->local}"]."</a>\r\n";
      }
   }
   
   if ($rw["pagFile"]=="entity_form.php" && $rw["pagEntityID"]=="ent"){
      echo "<ul>\r\n";
      $sqlEnt = "SELECT * FROM stbl_entity";
      $rsEnt = $oSQL->do_query($sqlEnt);
      while ($rwEnt = $oSQL->fetch_array($rsEnt)){
         echo "<li id='".$rw["pagID"]."_".$rwEnt["entID"]."'><a target='pane' href='".
            $rw["pagFile"]."?entID=".$rwEnt["entID"]."'>".$rwEnt["entTitle{$intra->local}"]."</a>\r\n";
      }
      echo "</ul></li>\r\n";
   }
   
   if ($rw["pagFile"]=="/toc_form.php"){
   /*
      echo "<ul>\r\n";
      echo "<li id='ldkfjsgfdj'><a target='pane' href='asdfasd'>Mumumu</a>\r\n";
      echo "</ul>\r\n";
      */
   }
   
   $rw_old = $rw;
}
for ($i=$rw_old["iLevelInside"]; $i>1; $i--)
   echo "</ul>\r\n\r\n";
/*============================= OUTPUT ==================================*/	

?>
</ul>