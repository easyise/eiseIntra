<?php 
/**
 * eiseIntraAnnounce class
 *
 * set of static functions to display announcements on the main system page.
 *
 *
 * @package eiseIntra
 *
 */

class eiseIntraAnnounce {

private static $arrDefaultConfig = array(	);

public static $actionsWrite = array('post'=>'addAnnounce', 'delete' => 'deleteAnnounce');
public static $actionsRead = array('get'=>'getAnnounces');

const nAnnouncesToGet = 25;

public static function announces($arrRolesPermitted, $arrConfig = array()){

	GLOBAL $intra;
	$oSQL = $intra->oSQL;

	?>
<style type="text/css">
.eiseIntraAnnounce .overflow_scroll {
	overflow-y: auto;
}
.eiseIntraAnnounce ul {
	display: table;
	width: 100%;
}
.eiseIntraAnnounce li {
	display: table-row;
}
.eiseIntraAnnounce li > * {
	display: table-cell;
}

.eiseIntraAnnounce form {
	width: 100%;
}
.eiseIntraAnnounce form > * {
	display: inline-block;
	vertical-align: top;
}

/**
 * decoration
 */

.eiseIntraAnnounce h1:first-letter, .eiseIntraAnnounce h2:first-letter, .eiseIntraAnnounce h3:first-letter, .eiseIntraAnnounce h4:first-letter {
	text-transform: uppercase;
}

.eiseIntraAnnounce ul {
	margin: 0;
}
.eiseIntraAnnounce .overflow_scroll {
	border-top: 1px solid #333;
	border-bottom: 1px solid #333;
}

.eiseIntraAnnounce form {
	padding: 8px;
	padding-top: 5px;
}
.eiseIntraAnnounce textarea {
	width: 74%;
	height: 2.3em;
	
}
.eiseIntraAnnounce input[type=submit] {
	width: 24%;
	background-color: rgb(217, 217, 217);
  	background-image: linear-gradient(rgb(248, 248, 248), rgb(217, 217, 217));
  	border: 2px outset rgb(192, 192, 192);
  	border-radius: 4px;
}

.ei_announce {
	overflow: hidden;
	padding: 2px 16px;
	position: relative;
}

.eif_annFrom {
	font-weight: bold;
	float: left;
}
.eif_annDate {
	color: #999;
	font-weight: bold;
	float: right;
}
.eif_annText {
	margin-top: 1.1em;
    padding: 3px 10px 1em;
    white-space: pre;
}
.ei_btnDeleteAnnounce {
	position: absolute;
	top: 1.2em;
	right: 16px;
	width: 22px !important;
	height: 22px !important;
	padding: 0 !important;
	display: none;
}
</style>
<div class="eiseIntraAnnounce">

<h3><?php echo $intra->translate('news') ?></h3>

<div class="overflow_scroll">
<ul>

<li class="eif_template eif_evenodd">
<div class="ei_announce">
	<input type="hidden" class="eif_annID" name="eif_annID">
	<div class="eif_annFrom">Ivan Ivanov</div>
	<div class="eif_annDate">18 Feb</div>
	<div class="eif_annText">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vivamus condimentum felis eu erat lobortis, porta tempus ex auctor. Nunc consectetur pretium volutpat.</div>
</div>
</li>

<li class="eif_notfound">
	<div>
<?php echo $intra->translate("no news today"); ?>
	</div>
</li>

<li class="eif_spinner" style="display: none;"></li>

</ul>
</div>
<?php 
	if($intra->arrUsrData['FlagWrite']){

		?>
<form action="<?php echo $_SERVER['PHP_SELF'] ?>" method="POST">
	<input type="hidden" name="DataAction" value="<?php echo self::$actionsWrite['post'] ?>">
	<textarea id="annText" name="annText"></textarea>
	<input type="submit" id="btnAnnounceSubmit" value="<?php echo $intra->translate('post') ?>">
</form>
		<?php

	}
 ?>
</div>
<script type="text/javascript">
function toggleDeleteButton($ann){

	var $btn = $ann.find('.ei_btnDeleteAnnounce');
	
	if($btn[0]){
		$btn.slideUp('slow').remove();
	} else {
		$btn = $('<input type="button">').addClass("ei_btnDeleteAnnounce").addClass("ss_sprite").addClass("ss_delete");
		$btn.appendTo($ann)
			.slideDown('slow')
			.click(function(){
				var annID = $ann.find('.eif_annID').val();
				if(confirm('Sure?')){
					$.getJSON(location.pathname+'?DataAction=deleteAnnounce&annID='+encodeURIComponent(annID)
						, function(data){
							if(data.status=='ok'){
								MsgShow(data.message);
								reloadAnnounces();
							} else 
								MsgShow('ERROR:'+data.message);
						})
				}
			});
	}
}
function reloadAnnounces(){
	
	var pathname = location.pathname;
	var $annList = $('.eiseIntraAnnounce ul');

	$annList.eiseIntraAJAX('fillTable', pathname+'?DataAction=getAnnounces', {afterFill: function(){
		
		if($('.eiseIntraAnnounce form')[0])
			$annList.find('.eif_loaded').each(function(){
				var $ann = $(this).find('.ei_announce');
				$ann.hover(function(){
					window.setTimeout(function(){
						toggleDeleteButton($ann);
					}, 100)
				})
			});

		adjustHeight();

	}});

}

function adjustHeight(){
	var wH = $(window).height();
	var dH = $(document).height();

	
	if(dH>wH){
		var oT = $('.eiseIntraAnnounce .overflow_scroll').offset().top;
		var formH = ($('.eiseIntraAnnounce form')[0] ? $('.eiseIntraAnnounce form').outerHeight(true) : 0);
		var newH = wH - formH - oT;
		$('.eiseIntraAnnounce .overflow_scroll').height(newH);
	}

}

$(window).load(function(){
	$('.eiseIntraAnnounce form').submit(function(){

		var text = $(this).find('textarea[name=annText]').val();

		if(text.length>255){
			alert ('You need to be more clever. Maximum size of text field is 255 symbols.');
			return false;
		}

		$.ajax({
            url     : $(this).attr('action'),
            type    : $(this).attr('method'),
            dataType: 'json',
            data    : $(this).serialize(),
            success : function( data ) {

                      	if(data.status=='ok'){
                      		MsgShow(data.message);
                      		reloadAnnounces();
                      	}
                      	else 
                      		MsgShow('ERROR:'+data.message);
                      },
            error   : function( xhr, err ) {
                        alert(err);     
                      }
        });    

        $(this).find('textarea[name=annText]').val('');

		return false;

	});

	reloadAnnounces();

})
</script>
	<?php
}

public static function dataAction($newData){

	GLOBAL $intra;
	$oSQL = $intra->oSQL;

	switch($newData['DataAction']){

		case 'addAnnounce':

			if(!$newData['annText'])
				$intra->json('error', $intra->translate('Empty announce'));

			$oSQL->q('START TRANSACTION');
			$sqlAnn = "INSERT INTO stbl_announce SET
			    annSubject = ".$oSQL->e($newData['annSubject'])."
			    , annText = ".$oSQL->e($newData['annText'])."
			    , annFlagDeleted = 0
			    , annInsertBy = '{$intra->usrID}', annInsertDate = NOW(), annEditBy = '{$intra->usrID}', annEditDate = NOW()";
			$oSQL->q($sqlAnn);
			$annID = $oSQL->i();
			$oSQL->q('COMMIT');

			$intra->json('ok', $intra->translate('Announce posted'), array('annID'=>$annID));

		case 'deleteAnnounce':

			$oSQL->q('START TRANSACTION');
			$sqlAnn = "DELETE FROM stbl_announce WHERE annID=".(int)$newData['annID'];
			$oSQL->q($sqlAnn);
			$oSQL->q('COMMIT');

			$intra->json('ok', $intra->translate('Announce is deleted'));

		case 'getAnnounces':
			
			$nAnnouncesToGet = (isset($newData['number']) ? $newData['number'] :  self::nAnnouncesToGet);
			$sqlAnn = "SELECT annID
					, annSubject
					, annText
					, annInsertDate as annDate
					, IFNULL(optText{$intra->local}, annInsertBy) as annFrom
				FROM stbl_announce 
					LEFT OUTER JOIN svw_user ON optValue=annInsertBy
				WHERE annFlagDeleted=0 ORDER BY annInsertDate DESC LIMIT 0,".(int)$nAnnouncesToGet;
			$rsAnn = $oSQL->q($sqlAnn);
			$arrAnn = $intra->result2JSON($rsAnn);

			$intra->json('ok', '', $arrAnn);

	}
}



}
 ?>