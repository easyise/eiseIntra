<?php 
if ($_GET["pane"])
   $paneSrc = $_GET["pane"];
else 
   $paneSrc = (isset($defaultPaneSrc) ? $defaultPaneSrc : "about.php") ;

$arrJS[] = jQueryRelativePath."simpleTree/jquery.simple.tree.js";
$arrCSS[] = jQueryRelativePath."simpleTree/simpletree.css";

$arrJS[] = eiseIntraRelativePath."intra.js";
$arrCSS[] = eiseIntraRelativePath."intra.css";
$arrCSS[] = commonStuffRelativePath."screen.css";
   
 ?><!DOCTYPE html>
<html>
<head>
	<title><?php  echo $title ; ?></title>
<?php 
$intra->loadCSS();
$intra->loadJS();
 ?>
<script>
$(document).ready(function(){
    
    eiseIntraAdjustPane();
    
    $(window).resize(function(){
        eiseIntraAdjustPane();
    });
    
    $('.simpleTree').simpleTree({
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
			window.frames['pane'].location.href=newHref
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
</head>
<body>  

<table style="width:100%;height:100%;border-spacing: 0;border: 0;">
<tbody>
<tr>
<td colspan="2">
<div id="header">
	<a href="index.php" target="_top"><div id="corner_logo"><?php echo $intra->conf["stpCompanyName"]; ?></div></a>
	<div id="app_title"><?php echo $title; ?></div>
    <div id="login_info"><?php echo $intra->translate("You're logged in as"); ?> <?php 
    
    if ($authmethod=="mysql"){
        echo $oSQL->dbuser."@".$oSQL->dbhost;
    } else {
        echo $intra->arrUsrData["usrName{$intra->local}"] ;
        if (count($intra->arrUsrData["roles"]))
            echo "&nbsp;(".implode(", ",$intra->arrUsrData["roles"]).")";
    }?> <a href="login.php" target="_top"><?php  echo $intra->translate("Logout") ; ?></a></div>
    <div id='languages'>
    <a class="lang-en<?php echo ($intra->local=="" ? " sel" : ""); ?>" href="index.php?local=off">ENG</a><br>
    <a class="lang-<?php echo $localLanguage.($intra->local=="Local" ? " sel" : "") ?>" href="index.php?local=on"><?php  echo $localCountry ; ?></a>
	</div>
</div>
</td>
</tr>

<tr>
<td>
<div id="toc">

<?php 
if (isset($toc_generator) && file_exists($toc_generator)){
    include ($toc_generator);
} else 
    include ("inc_toc_generator.php");
?>

</div>
</td>
<td style="width:100%;padding: 0;border:0;margin:0;"><iframe id="pane" name="pane" src="<?php echo $paneSrc ; ?>" style="width:100%;height:100%;position:relative;border:0;" frameborder=0></iframe></td>
</tr>
</tbody>
</table>

</body>
</html>