<?php
include "common/auth.php";

include eiseIntraAbsolutePath."inc_top.php";
?>
<h1><?php  echo $title ; ?></h1>
<p><?php if($author){
	echo "&copy;".date('Y')." {$author}.".($license ? " Licensed with {$license}. All rights reserved." : '')."<br>";
} 
?>
System version <?php  echo $intra->conf['version'] ; ?>/<?php 
$sql = "SELECT MAX(verNumber) as verNumber FROM stbl_version";
echo $oSQL->get_data($oSQL->do_query($sql));
 ?>.
</p>

<?php
echo "Made with <a href=\"https://github.com/easyise/eiseIntra/\" target=_blank>eiseIntra</a> &copy;2006-".date('Y')." Ilya S.Eliseev. Licensed with GPL v3. All rights reserved.";
include eiseIntraAbsolutePath."inc_bottom.php";
?>