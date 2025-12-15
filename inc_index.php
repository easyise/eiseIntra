<?php 
if (isset($_GET["pane"]) && $_GET["pane"])
   $paneSrc = $_GET["pane"];
else 
   $paneSrc = (isset($defaultPaneSrc) ? $defaultPaneSrc : $intra->conf['defaultPage']) ;

$intra->requireComponent('simpleTree');
$intra->conf['flagDontGetMenu'] = true;
$intra->conf['MenuTarget'] = 'pane';

include('inc_top.php');
?>
<script type="text/javascript">
$(document).ready(function(){

	if(window!=window.top){

		$('#header, #toc, #pane').remove();
		window.setTimeout(function(){

			window.location.href = "<?php echo $paneSrc ?>";

		}, 3000) 

	}

})
</script>
<iframe id="pane" name="pane" src="<?php echo $paneSrc ; ?>" frameborder=0></iframe>
<?php
include('inc_bottom.php');