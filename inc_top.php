<?php 
$intra->requireComponent('simpleTree');
 ?><!DOCTYPE html>
<html>
<head>
	<meta http-equiv="X-UA-Compatible" content="IE=edge"/>
    <title><?php echo (isset($pageTitle) && $pageTitle!=='' ? $pageTitle 
        : (isset($title) && $title!=='' ? $title : $intra->arrUsrData["pagTitle{$intra->local}"])
        ); ?></title>
<?php 
$intra->loadCSS();
$intra->loadJS();

$flagNoHeader = isset($flagNoHeader) ? $flagNoHeader : false;
$warning = isset($warning) ? $warning : '';
$flagNoMenu = isset($flagNoMenu) ? $flagNoMenu : false;
$extraHTML = isset($extraHTML) ? $extraHTML : '';

$arrActions = isset($arrActions) ? $arrActions : [];

$paneClass = "ei-pane{$intra->conf['frame']}".($flagNoHeader ? ' no-header' : '');
$conf_to_set = array_merge($intra->conf, [ 'FlagWrite'=>(int)($intra->arrUsrData['FlagWrite'] ?? 0)
    , 'usrID'=> ($intra instanceof eiseAdmin ? null : ($intra->arrUsrData['usrID'] ?? null))
    , 'roleIDs'=> ($intra instanceof eiseAdmin ? null : (is_array($intra->arrUsrData['roleIDs'] ?? null) ? implode(',', $intra->arrUsrData['roleIDs']) : ''))
 ]  );
 ?>

<link rel="icon" href="./favicon.ico" type="image/x-icon" />
<link rel="shortcut icon" href="./favicon.ico" type="image/x-icon" />

</head>
<body data-conf="<?php  echo htmlspecialchars(json_encode( $conf_to_set )) ; ?>" data-message="<?php  echo htmlspecialchars($intra->getUserMessage()) ; ?>" class="<?php echo eiseIntra::getSlug()
    .(isset($pageClass) && $pageClass ? ' '.$pageClass : ''); 
    ?>">  

<?php if (!$flagNoHeader): ?>
    <div id="header" class="ei-header" role="nav">
        <?php if ($eiseIntraCSSTheme!='aring'): ?>
        <div class="ei-app-title"><span class="sidebar-toggle fa fa-bars"></span><a href="<?php echo dirname($_SERVER['PHP_SELF']).'/' ?>"><?php echo $title; ?></a></div>
        <?php else: ?>
        <div class="ei-app-title"><span class="sidebar-toggle fa fa-bars"></span><a href="<?php echo dirname($_SERVER['PHP_SELF']).'/' ?>"><?php 
            $svgLogo = '<svg class="ei-title-logo" width="26" height="28" viewBox="120 110 252 272" role="img" aria-label="$1"><path fill="currentColor" d="m286.11,196.26c6.63-8.66,10.57-19.47,10.57-31.21,0-28.39-23.11-51.5-51.5-51.5s-51.5,23.11-51.5,51.5c0,12.44,4.42,23.86,11.79,32.76l-82.05,181.18h34.49l33.53-76.24c15.23,12.52,34.57,19.71,54.83,19.71s39.23-6.95,54.38-19.26l33.32,75.79h35.15l-83.01-182.73Zm-40.92-54.57c12.9,0,23.36,10.46,23.36,23.36s-10.46,23.36-23.36,23.36-23.36-10.46-23.36-23.36,10.46-23.36,23.36-23.36Zm1.07,152.61c-16.43,0-31.96-7.01-42.85-18.88l26.87-61.07c4.72,1.43,9.72,2.19,14.89,2.19,5.65,0,11.11-.91,16.21-2.61l27.3,61.99c-10.89,11.66-26.15,18.38-42.42,18.38Z"/></svg>';
            echo preg_replace('/([aAåÅаА])/u', $svgLogo, htmlspecialchars($title ?? ''), 1); 
        ?></a></div>
        <?php endif ?>
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

        $usrName = (isset($authmethod) && $authmethod=="mysql" ? $oSQL->dbuser : $intra->arrUsrData["usrName{$intra->local}"]);
        $usrDescr = (isset($authmethod) && $authmethod=="mysql" ? '@'.$oSQL->dbhost : (isset($intra->arrUsrData["usrDescription{$intra->local}"]) ? $intra->arrUsrData["usrDescription{$intra->local}"] : ''));

        if(isset($intra->arrUsrData['usrIcon']) && $intra->arrUsrData['usrIcon']!=='')
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
            <a href="index.php" target="_top" class="ei-logo"><?php echo (isset($intra->conf["stpCompanyName"]) && $intra->conf["stpCompanyName"]
                ? $intra->conf["stpCompanyName"]
                : $title) ?></a>
            <i class="fa fa-eye sidebar-pin"> </i>
        </div>


    <div class="ei-sidebar-menu-content">
    <?php 
    if(isset($intra->conf['flagDontGetMenu']) && $intra->conf['flagDontGetMenu']) 
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
