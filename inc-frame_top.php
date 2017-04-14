<!DOCTYPE html>
<html>
<head>
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title><?php echo $intra->arrUsrData["pagTitle{$intra->local}"]; ?></title>

<?php
$intra->loadJS();
$intra->loadCSS();
?>


<?php
echo "\t".$strHead."\r\n";
?>

</head>
<body data-message="<?php echo htmlspecialchars( $intra->getUserMessage() ) ?>" class="<?php echo eiseIntra::getSlug(); ?>"><input type="hidden" id="eiseIntraConf" value="<?php  echo htmlspecialchars(json_encode($intra->conf)) ; ?>">

<?php 
if (!$flagNoMenu) {
    echo $intra->actionMenu($arrActions, true);
}
?>
<div id="frameContent">