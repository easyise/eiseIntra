<?php
include ("common/auth.php");

$arrJS[] = "../common/jquery/jquery.simple.tree.js";
?>
<html>
<head>

<title><?php echo $arrUsrData["pagTitle$strLocal"]; ?></title>

<?php
loadJS();
?>

<link rel="STYLESHEET" type="text/css" href="../common/intranet.css" media="screen" />
<link rel="STYLESHEET" type="text/css" href="../common/intranet_print.css" media="print" />

<style>
body {
    background-color: #FFFFFF;
    background-image: url(../common/images/intra_logo.gif);
    background-attachment: fixed;
	background-repeat: no-repeat;
	background-position: 0% -61px;
    padding-left: 10px;
    padding-top: 10px;
}
</style>

</head>
<body>
<script>
var simpleTreeCollection;
$(document).ready(function(){
	simpleTreeCollection = $('.simpleTree').simpleTree({
		autoclose: false,
        drag:false,
		afterClick:function(node){
            var arrId = node.attr("id").split("|");
            switch(arrId[0]){
               case "ent":
                  var newHref = "entity_list.php?dbName="+arrId[1];
                  break;
               default:
                  var newHref = "database_form.php?dbName="+(node.attr("id"));
                  break;
            }
			window.parent.frames['pane'].location.href=newHref
		},
		afterDblClick:function(node){
			//alert("text-"+$('span:first',node).text());
		},
		afterMove:function(destination, source, pos){
			//alert("destination-"+$('span:first',destination).text()+" source-"+$('span:first',source).text()+" pos-"+pos);
            return false;
		},
		afterAjax:function()
		{
			//alert('Loaded');
		},
		animate:true
		,docToFolderConvert:true
		});
	
});
</script>

<?php
$sqlDB = "SHOW DATABASES";

$rsDB = $oSQL->do_query($sqlDB);
?>

<ul class="simpleTree" id="toc">
<li id="" class="root"><span><a target='pane' href="server_form.php"><strong><?php  echo $arrAuth["DBHOST"] ; ?></strong></a></span>
<ul>
<?php
while($rwDB = $oSQL->fetch_array($rsDB)){
?>
<li id="<?php  echo $rwDB["Database"] ; ?>"><span id="db_<?php  echo $rwDB["Database"] ; ?>"><a 
   target='pane' href="database_form.php?dbName=<?php  echo  $rwDB["Database"]; ?>"><?php  echo $rwDB["Database"] ; ?></a></span>


<?php 
$sqlTab = "SHOW TABLES FROM `".$rwDB["Database"]."`";
$rsTab = $oSQL->do_query($sqlTab);
?>
<ul>
<?php
$arrFlags = Array();
while ($rwTab = mysql_fetch_array($rsTab)) {
?>
   <li id="<?php  echo $rwDB["Database"]."|".$rwTab[0] ; ?>"><a target='pane' 
      href="table_form.php?dbName=<?php  echo $rwDB["Database"] ; ?>&tblName=<?php  echo $rwTab[0] ; ?>"><span><?php echo($rwTab[0]) ?></span></a>
<?php
   if ($rwTab[0]=="stbl_page") $arrFlags["hasPages"] = true;
   if ($rwTab[0]=="stbl_role") $arrFlags["hasRoles"] = true;
   if ($rwTab[0]=="stbl_entity") $arrFlags["hasEntity"] = true;
   if ($rwTab[0]=="stbl_translation") $arrFlags["hasMultiLang"] = true;

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
<?php
}

if ($arrFlags["hasPages"]){
?>
<li id="<?php  echo "pag|".$rwDB["Database"] ; ?>"><a target='pane'
    href='page_list.php?dbName=<?php  echo $rwDB["Database"] ; ?>'><span><b>Pages</b></span></a>
<li id="<?php  echo "pag|".$rwDB["Database"] ; ?>"><a target='pane'
    href='role_form.php?dbName=<?php  echo $rwDB["Database"] ; ?>'><span><b>Roles</b></span></a>
<li id="<?php  echo "pgr|".$rwDB["Database"] ; ?>"><a target='pane'
    href='matrix_form.php?dbName=<?php  echo $rwDB["Database"] ; ?>'><span><b>Page-role matrix</b></span></a>
<?php 
}
if ($arrFlags["hasMultiLang"]){
?>
<li id="<?php  echo "str|".$rwDB["Database"] ; ?>"><a target='pane'
    href='translation_form.php?dbName=<?php  echo $rwDB["Database"] ; ?>'><span><b>Translation table</b></span></a>
<?php
}
 ?>

    
<!--
    <ul>
    <li id="<?php  echo $rwDB["Database"]."_ent" ; ?>">Entities</li>
    </ul>

    <ul>
    <li id="<?php  echo $rwDB["Database"]."_rol" ; ?>">Roles</li>
    </ul>
-->
</ul>

<?php
}
?>
</ul>
</ul>
<div>&nbsp;</div>
</body>
</html>