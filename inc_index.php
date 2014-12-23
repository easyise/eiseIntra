<?php 
if ($_GET["pane"])
   $paneSrc = $_GET["pane"];
else 
   $paneSrc = (isset($defaultPaneSrc) ? $defaultPaneSrc : "about.php") ;

$arrJS[] = jQueryRelativePath."simpleTree/jquery.simple.tree.js";
$arrCSS[] = jQueryRelativePath."simpleTree/simpletree.css";
   
 ?><!DOCTYPE html>
<html>
<head>
	<meta http-equiv="X-UA-Compatible" content="IE=edge"/>
    <title><?php  echo $title ; ?></title>
<?php 
$intra->loadCSS();
$intra->loadJS();
 ?>
<script>
$(document).ready(function(){
    
    $('.simpleTree').simpleTree({
		autoclose: false,
        drag:false,
		afterClick:function(node){
            var arrId = node.attr("id").split("|");
            var newHref;
            switch(arrId[0]){
               case "ent":
                    newHref = "entity_list.php?dbName="+encodeURIComponent(arrId[1]);
                    break;
                case 'db':
                    newHref = "database_form.php?dbName="+encodeURIComponent(arrId[1]);
                    break;
                default:
                  break;
            }
            if (newHref)
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
    
    layout = new eiseIntraLayout({menushown: true});
    
    $(window).resize(function(){
        layout.adjustPane();
    });
    
    $(window).resize();
    
});
</script>
</head>
<body>  

<div id="header">
	<a href="index.php" target="_top"><div id="corner_logo"><?php echo $intra->conf["stpCompanyName"]; ?></div></a>
	<div id="app_title"><?php echo $title; ?></div>
    <?php if ($warning): ?>
        <div id="app_warning"><?php echo $warning; ?></div>
    <?php endif ?>
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
<div id="toc">

<?php 
if (isset($toc_generator) && file_exists($toc_generator)){
    include ($toc_generator);
} else 
    include ("inc_toc_generator.php");
?>

</div>
<iframe id="pane" name="pane" src="<?php echo $paneSrc ; ?>" style="position:fixed;" frameborder=0></iframe>
<?php echo $extraHTML; ?>
</body>
</html>