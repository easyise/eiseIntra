<?php 
$intra->requireComponent('simpleTree');
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
<body data-conf="<?php  echo htmlspecialchars(json_encode($intra->conf)) ; ?>" data-message="<?php  echo htmlspecialchars($intra->getUserMessage()) ; ?>" class="<?php echo eiseIntra::getSlug(); ?>">  

<div id="header" class="ei-header" role="nav">
	<a href="index.php" target="_top"><div id="corner_logo" class="ei-logo"><?php echo ($intra->conf["stpCompanyName"]
        ? $intra->conf["stpCompanyName"]
        : $title) ?></div></a>
    <div class="ei-top-level-menu-container"> </div>
	<div id="app_title" class="ei-app-title"><?php echo $title; ?></div>
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
    <?php 
    $langSelURI = $_SERVER['PHP_SELF'].'?'.($_SERVER['QUERY_STRING'] ? $_SERVER['QUERY_STRING'].'&' : '');

    echo '<a class="language-selector lang-en'.($intra->local=="" ? " sel" : "").'" href="'.$langSelURI.'local=off">ENG</a>'."\r\n";
    echo '<a class="language-selector lang-'.$localLanguage.($intra->local=="Local" ? " sel" : "").'" href="'.$langSelURI.'local=on">'.$localCountry.'</a>'."\r\n";
    ?>
	</div>
</div>
<div id="toc" class="ei-sidebar-menu" role="nav">

<div class="ei-sidebar-menu-content">
<?php 
//echo $intra->menu();
?>

</div>

</div>
<div class="ei-pane content">
<?php 
if (!$flagNoMenu) {
    echo $intra->actionMenu($arrActions, false);
}