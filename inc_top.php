<?php 
$intra->requireComponent('simpleTree');
 ?><!DOCTYPE html>
<html>
<head>
	<meta http-equiv="X-UA-Compatible" content="IE=edge"/>
    <title><?php echo ($title ? $title : $intra->arrUsrData["pagTitle{$intra->local}"]); ?></title>
<?php 
$intra->loadCSS();
$intra->loadJS();

$paneClass = "ei-pane{$intra->conf['frame']}".($flagNoHeader ? ' no-header' : '');
$conf_to_set = array_merge($intra->conf, [ 'FlagWrite'=>(int)$intra->arrUsrData['FlagWrite'] ]  );
 ?>
</head>
<body data-conf="<?php  echo htmlspecialchars(json_encode( $conf_to_set )) ; ?>" data-message="<?php  echo htmlspecialchars($intra->getUserMessage()) ; ?>" class="<?php echo eiseIntra::getSlug(); ?>">  

<?php if (!$flagNoHeader): ?>
    <div id="header" class="ei-header" role="nav">
        <div class="ei-app-title"><span class="sidebar-toggle fa fa-bars"></span><a href="<?php echo dirname($_SERVER['PHP_SELF']) ?>"><?php echo $title; ?></a></div>
        <div class="ei-top-level-menu-container"> </div>
        
        <?php if ($warning): ?>
            <div class="ei-app-warning"><?php echo $warning; ?></div>
        <?php endif ?>
        <div class="ei-header-languages">
        <?php 
        $langSelURI = $_SERVER['PHP_SELF'].'?'.($_SERVER['QUERY_STRING'] ? $_SERVER['QUERY_STRING'].'&' : '');

        echo '<a class="language-selector lang-en'.($intra->local=="" ? " sel" : "").'" href="'.$langSelURI.'local=off">ENG</a>'."\r\n";
        echo '<a class="language-selector lang-'.$localLanguage.($intra->local=="Local" ? " sel" : "").'" href="'.$langSelURI.'local=on">'.$localCountry.'</a>'."\r\n";
        ?>
        </div>
        <div class="ei-login-info"><?php 

        $usrName = ($authmethod=="mysql" ? $oSQL->dbuser : $intra->arrUsrData["usrName{$intra->local}"]);
        $usrDescr = ($authmethod=="mysql" ? '@'.$oSQL->dbhost : $intra->arrUsrData["usrDescription{$intra->local}"]);

        if($intra->arrUsrData['usrIcon'])
            echo '<div class="ei-user-icon"><img src="'.$intra->arrUsrData['usrIcon'].'"></div>'."\n";
        
        echo '<div class="ei-user-name">'.$usrName.'</div>'."\n";
        echo '<div class="ei-user-description">'.$usrDescr.'</div>'."\n";

        echo $intra->getCurrentUserInfo();
            
        ?></div>
        <?php if ($extraHTML): ?>
        <div class="ei-extra-html"><?php echo $extraHTML ?></div>
        <?php endif ?>
    </div>
    <div id="toc" class="ei-sidebar-menu" role="nav">
        <div class="ei-logo-container">
            <div class="ei-logo-bg"></div>
            <i class="fa fa-angle-double-left sidebar-toggle"> </i>
            <a href="index.php" target="_top" class="ei-logo"><?php echo ($intra->conf["stpCompanyName"]
                ? $intra->conf["stpCompanyName"]
                : $title) ?></a>
            <i class="fa fa-eye sidebar-pin"> </i>
        </div>


    <div class="ei-sidebar-menu-content">
    <?php 
    if($intra->conf['flagDontGetMenu'])
        echo $intra->menu($intra->conf['MenuTarget']);
    ?>

    </div>

    </div>
<?php endif ?>

<div class="<?php echo $paneClass; ?> content">
<?php 
if (!$flagNoMenu) {
    echo $intra->actionMenu($arrActions, false);
}
