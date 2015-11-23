<?php
$sqlDB = "SHOW DATABASES";

$rsDB = $oSQL->do_query($sqlDB);
?>

<ul class="simpleTree">
<li id="" class="root"><span><a target='pane' href="server_form.php"><strong><?php  echo $oSQL->dbhost ; ?></strong></a></span>
<ul>
<?php
while($rwDB = $oSQL->fetch_array($rsDB)){
?>
<li id="db|<?php  echo $rwDB["Database"] ; ?>"><span id="db_<?php  echo $rwDB["Database"] ; ?>"><a 
   target='pane' href="database_form.php?dbName=<?php  echo  $rwDB["Database"]; ?>"><?php  echo $rwDB["Database"] ; ?></a></span>


<?php 
$sqlTab = "SHOW TABLES FROM `".$rwDB["Database"]."`";
$rsTab = $oSQL->do_query($sqlTab);
?>
<ul>
<?php
$arrFlags = Array();
while ($rwTab = $oSQL->fa($rsTab)) {
    
    if ($rwTab[0]=="stbl_page") $arrFlags["hasPages"] = true;
    if ($rwTab[0]=="stbl_role") $arrFlags["hasRoles"] = true;
    if ($rwTab[0]=="stbl_entity") $arrFlags["hasEntity"] = true;
    if ($rwTab[0]=="stbl_translation") $arrFlags["hasMultiLang"] = true;

    if($intra->conf['hideSTBLs'] && preg_match('/^(stbl_|svw_)/i', $rwTab[0]))
        continue;
?><li id="<?php  echo $rwDB["Database"]."|".$rwTab[0] ; ?>"><a target='pane' 
      href="table_form.php?dbName=<?php  echo $rwDB["Database"] ; ?>&tblName=<?php  echo $rwTab[0] ; ?>"><span><?php echo($rwTab[0]) ?></span></a></li>
<?php
}

if ($arrFlags["hasEntity"]){
?>
<li id="<?php  echo "ent|".$rwDB["Database"] ; ?>"><span><b>Entities</b></span>
<ul>
<?php 
//$oSQL->do_query("USE `".$rwDB["Database"]."`");
$sqlEnt = "SELECT * FROM `".$rwDB["Database"]."`.`stbl_entity`";
$rsEnt = $oSQL->do_query($sqlEnt);
while ($rwEnt = $oSQL->fetch_array($rsEnt)){
?>
<li id='<?php  echo $rwDB["Database"]."_".$rwEnt['entID'] ; ?>'><a target='pane' 
       href='entity_form.php?dbName=<?php  echo $rwDB["Database"] ; ?>&entID=<?php  echo $rwEnt['entID'] ; ?>'><span><?php 
           echo $rwEnt['entTitle'];
       ?></span></a>
<?php
}
?>
</ul>
</li>
<?php
}

if ($arrFlags["hasPages"]){
?>
<li id="<?php  echo "pag|".$rwDB["Database"] ; ?>"><a target='pane'
    href='page_list.php?dbName=<?php  echo $rwDB["Database"] ; ?>'><span><b>Pages</b></span></a></li>
<li id="<?php  echo "pag|".$rwDB["Database"] ; ?>"><a target='pane'
    href='role_form.php?dbName=<?php  echo $rwDB["Database"] ; ?>'><span><b>Roles</b></span></a></li>
<li id="<?php  echo "pgr|".$rwDB["Database"] ; ?>"><a target='pane'
    href='matrix_form.php?dbName=<?php  echo $rwDB["Database"] ; ?>'><span><b>Page-role matrix</b></span></a></li>
<?php 
}
if ($arrFlags["hasMultiLang"]){
?>
<li id="<?php  echo "str|".$rwDB["Database"] ; ?>"><a target='pane'
    href='translation_form.php?dbName=<?php  echo $rwDB["Database"] ; ?>'><span><b>Translation table</b></span></a></li>
<?php
}
 ?>

</ul>
</li>
<?php
}
?>
</ul>
</li>

</ul>
<div>&nbsp;</div>