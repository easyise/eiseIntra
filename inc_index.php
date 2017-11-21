<?php 
if ($_GET["pane"])
   $paneSrc = $_GET["pane"];
else 
   $paneSrc = (isset($defaultPaneSrc) ? $defaultPaneSrc : "about.php") ;

$intra->requireComponent('simpleTree');
$intra->conf['flagDontGetMenu'] = true;
$intra->conf['MenuTarget'] = 'pane';

include('inc_top.php');
?>
<iframe id="pane" name="pane" src="<?php echo $paneSrc ; ?>" frameborder=0></iframe>
<?php
include('inc_bottom.php');