<?php
include "common/auth.php";

include eiseIntraAbsolutePath."inc-frame_top.php";
?>
<h1> Welcome to eiseAdmin@<?php  echo $oSQL->dbhost ; ?>!</h1>
<div>
Version <?php  echo $strVerNo  ; ?>
</div>

<?php
include eiseIntraAbsolutePath."inc-frame_bottom.php";
?>