<?php 
if ($_GET["pane"])
   $paneSrc = $_GET["pane"];
else 
   $paneSrc = (isset($defaultPaneSrc) ? $defaultPaneSrc : "about.php") ;

$intra->requireComponent('simpleTree');
$intra->conf['flagDontGetMenu'] = true;
 ?><!DOCTYPE html>
<html>
<head>
	<meta http-equiv="X-UA-Compatible" content="IE=edge"/>
    <title><?php  echo $title ; ?></title>
<?php 
$intra->loadCSS();
$intra->loadJS();
 ?>
</head>
<body data-conf="<?php  echo htmlspecialchars(json_encode($intra->conf)) ; ?>">  

<div id="header" class="ei-header" role="nav">
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
<div id="toc" class="ei-sidebar-menu">
    <div class="ei-sidebar-menu-content">
<?php 
if (isset($toc_generator) && file_exists($toc_generator)){
    include ($toc_generator);
} else 
    echo $intra->menu('pane');
?>
    </div>
</div>
<div class="ei-pane content">
<iframe id="pane" name="pane" src="<?php echo $paneSrc ; ?>" frameborder=0></iframe>
</div>
<?php echo $extraHTML; ?>
</body>
</html>