<?php
$DataAction = isset($_POST["DataAction"]) ? $_POST["DataAction"] : $_GET["DataAction"];

if(!isset($intra))
    $intra = new eiseIntra($oSQL, Array('version'=>$version));

switch ($DataAction){
    case "login":
        
        list($login, $password) = $intra->decodeAuthString($_POST["authstring"], true);

        $strError = "";
        
        if($authmethod!="mysql"){
            try {
                if (!isset($oSQL))
                    $oSQL = new eiseSQL($DBHOST, $DBUSER, $DBPASS, $DBNAME, false, CP_UTF8);
                $oSQL->connect();
                $intra->oSQL = $oSQL;
            }catch (Exception $e){
                header("Location: {$_SERVER["PHP_SELF"]}?error=".urlencode($e->getMessage()));
            }
        }

        try {
            
            $intra->Authenticate( $login, $password, (isset($authmethod) ? $authmethod : "LDAP") );
            header ("Location: ".(isset($_COOKIE["PageNoAuth"]) ? $_COOKIE["PageNoAuth"] : "index.php"));
            die();

        } catch(eiseException $e){

            header ("Location: login.php?error=".$intra->translate( $e->getMessage()) );
            die();

        }        

    case "logout":

        $intra->logout();
        header ("Location: login.php");
        die();

}

$intra->logout();

?><!DOCTYPE html>
<html>
<head>

<meta http-equiv="X-UA-Compatible" content="IE=edge"/>

<title><?php  echo $title ; ?></title>

<?php
$intra->loadCSS();
$intra->loadJS();
?>
</head>
<body data-conf="<?php  echo htmlspecialchars(json_encode($intra->conf)) ; ?>">

<?php
$arrUsr = explode("[\\]", $AUTH_USER);
$usrID = strtoupper($arrUsr[count($arrUsr)-1]);

if ($strMode == "LDAP"){
	$ldap_conn = ldap_connect($ldap_server);
	$binding = @ldap_bind($ldap_conn, $ldap_anonymous_login, $ldap_anonymous_pass);
}
 ?>

<script>
$(window).load(function(){  
   
    $('body').eiseIntra('cleanStorage');

    var host = document.getElementById("host");
    if(host!=null) {
       host.value="localhost";
    } 
    
    $('#btnsubmit').removeAttr('disabled');

    $('#loginform').submit(function(){
        if(!$(this).eiseIntraForm('encodeAuthString'))
            return false;
        return true;
    });

    window.setTimeout(function(){
        document.getElementById("login").focus();
        document.getElementById("login").select();
    }, 1);
});

</script>

<div style="margin: 0 auto;width:33%">

<h1 style="text-align: center;">Welcome to <?php  echo $title ; ?></h1>

<?php 
if ($_GET["error"]){
?>
<div class="eiseIntraError" style="text-align: center;width: 66%;margin: 0 auto;">ERROR: <?php  echo $_GET["error"] ; ?></div>
<?php
}
 ?>
<form action="<?php echo $_SERVER["PHP_SELF"] ?>" id="loginform" method="POST" onsubmit="return LoginForm();" class="eiseIntraForm">
<input type="hidden" id="DataAction" name="DataAction" value="login">
<input type="hidden" id="authstring" name="authstring" value="">
<fieldset class="eiseIntraMainForm">

<?php 
if ($flagShowHost) {?>
<div>
   <label class="eiseIntraField">Host:</label>
   <input type="text" id="host" name="host" value="" class="eiseIntraValue">
</div>
<?php
}
?>
<div class="eiseIntraField">
	<label>Login:</label>
	<input type="text" id="login" name="login" value="<?php echo $_COOKIE["last_succesfull_usrID"] ; ?>" class="eiseIntraValue">
</div>

<div class="eiseIntraField">
	<label>Password:</label>
	<input type="password" id="password" name="password" value="" class="eiseIntraValue">
</div>

<div class="eiseIntraField">
	<label>&nbsp;</label>
	<input type="submit" id="btnsubmit" name="btnsubmit" class="eiseIntraSubmit" value="<?php  echo $intra->translate("Login") ; ?>">
</div>

<div><label>&nbsp;</label><div>Please enter your <strong><?php echo ($binding ? "Windows" : "database"); ?></strong> login/password.</div>
</div>

</fieldset>
</form>
</div>
</body>
</html>