<?php 
$arrCSS[] = imagesRelativePath."sprites/sprite.css";
$arrJS[] = eiseIntraRelativePath."intra.js";
$arrCSS[] = eiseIntraRelativePath."intra.css";
$arrCSS[] = commonStuffRelativePath."screen.css";

 ?><!DOCTYPE html>
<html>
<head>

<title><?php echo $intra->arrUsrData["pagTitle{$intra->local}"]; ?></title>

<?php
$intra->loadJS();
$intra->loadCSS();
?>

<script>
$(document).ready(function(){  
    
    eiseIntraAdjustFrameContent();
    MsgShow();	

    $('#menubar a.confirm').click(function(event){
        
        if (!confirm('<?php echo addslashes($intra->translate('Are you sure you want to execute')); ?> "'+$(this).text()+'"?')){
            event.preventDefault();
            return false;
        } else {
            return true;
        }

    });
    
});
</script>

<?php
echo "\t".$strHead."\r\n";
?>

</head>
<body><input type="hidden" id="eiseIntraConf" value="<?php  echo htmlspecialchars(json_encode($intra->conf)) ; ?>"><?php 
if (isset($_COOKIE["UserMessage"])) {
   $strUserMessage = $_COOKIE["UserMessage"];
   SetCookie("UserMessage", "");
}
 ?><div style="display:none;" id="sysmsg"<?php  
     echo (preg_match("/^ERROR/", $strUserMessage) ? " class='error'" : "") ; 
   ?>><?php  
     echo $strUserMessage ; 
     ?></div>

<?php 
if (!$flagNoMenu) {
 ?>
<div class="menubar" id="menubar">
<?php
    for ($i=0;$i<count($arrActions);$i++) {
            echo "<div class=\"menubutton\">";
			$strClass = ($arrActions[$i]['class'] != "" ? " class='ss_sprite ".$arrActions[$i]['class']."'" : "");
            $strTarget = (isset($arrActions[$i]["target"]) ? " target=\"{$arrActions[$i]["target"]}\"" : "");
            $isJS = preg_match("/javascript\:(.+)$/", $arrActions[$i]['action'], $arrJSAction);
            if (!$isJS){
                 echo "<a href=\"".$arrActions[$i]['action']."\"{$strClass}{$strTarget}>{$arrActions[$i]["title"]}</a>\r\n";
            } else {
                 echo "<a href=\"".$arrActions[$i]['action']."\" onclick=\"".$arrJSAction[1]."; return false;\"{$strClass}>{$arrActions[$i]["title"]}</a>\r\n";
            }
            echo "</div>";
    }
?>
<div class="menubutton float_right"><a target=_top href='index.php?pane=<?php  echo urlencode($_SERVER["REQUEST_URI"]) ; ?>'
class='ss_sprite ss_link'><?php  echo $intra->translate("Link") ; ?></a></div>
</div>
<?php 
}
?>
<div id="frameContent">