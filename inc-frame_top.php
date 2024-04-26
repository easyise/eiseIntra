<!DOCTYPE html>
<html>
<head>
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title><?php echo ($title ? $title : $intra->arrUsrData["pagTitle{$intra->local}"]); ?></title>

<?php
$intra->loadJS();
$intra->loadCSS();
?>


<?php
echo "\t".$strHead."\r\n";
?>

<link rel="icon" href="./favicon.ico" type="image/x-icon" />
<link rel="shortcut icon" href="./favicon.ico" type="image/x-icon" />

</head>
<body data-conf="<?php  echo htmlspecialchars(json_encode($intra->conf)) ; ?>" data-message="<?php echo htmlspecialchars( $intra->getUserMessage() ) ?>" class="<?php echo eiseIntra::getSlug(); ?>"><input 
	type="hidden" id="eiseIntraConf" value="<?php  echo htmlspecialchars(json_encode($intra->conf)) ; ?>">

<?php 
if (!$flagNoMenu) {
    echo $intra->actionMenu($arrActions, true);
}
?>
<div id="frameContent" class="ei-pane-framed">