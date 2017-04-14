<?php
include "common/auth.php";

include eiseIntraAbsolutePath."inc-frame_top.php";
?>
<h1> Welcome to <?php  echo $title ; ?>!</h1>
<p>
Version <?php  echo $intra->conf['version'] ; ?>/<?php 
$sql = "SELECT MAX(verNumber) as verNumber FROM stbl_version";
echo $oSQL->get_data($oSQL->do_query($sql));
 ?>.
</p>

<?php
include eiseIntraAbsolutePath."inc-frame_bottom.php";
?>