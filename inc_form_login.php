<?php
$DataAction = isset($_POST["DataAction"]) ? $_POST["DataAction"] : $_GET["DataAction"];

$intra = new eiseIntra($oSQL, Array('version'=>$version));

switch ($DataAction){
    case "login":
        
        list($login, $password) = $intra->decodeAuthString($_POST["authstring"]);

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
        
        if ($intra->Authenticate($login, $password, $strError, (isset($authmethod) ? $authmethod : "LDAP"))){
            
            $intra->session_initialize();
            
            $_SESSION["last_login_time"] = Date("Y-m-d H:i:s");
            if($authmethod=="mysql"){
                $_SESSION["usrID"] = $login;
                $_SESSION["DBHOST"] = $intra->oSQL->dbhost;
                $_SESSION["DBPASS"] = $intra->oSQL->dbpass;
                $_SESSION["DBNAME"] = $intra->oSQL->dbname;
            } else {
                $_SESSION["usrID"] = strtoupper($login);
            }
            SetCookie("last_succesfull_usrID", $login, eiseIntraCookieExpire, eiseIntraCookiePath);
            header("HTTP/1.0 403 Access denied"); 
            header ("Location: ".(isset($_COOKIE["PageNoAuth"]) ? $_COOKIE["PageNoAuth"] : "index.php"));
            die();
        } else {
            header("HTTP/1.0 403 Access denied"); 
            SetCookie("last_succesfull_usrID", "", eiseIntraCookieExpire, eiseIntraCookiePath);
            header ("Location: login.php?error=".$strError);
            die();
        }
        break;
   case "logout":
      session_start();
      session_unset();
      session_destroy();
      header ("Location: login.php");
      die();
      break;

}

session_start();
session_unset();
session_destroy();
?><!DOCTYPE html>
<html>
<head>

<meta http-equiv="X-UA-Compatible" content="IE=edge"/>

<title><?php  echo $title ; ?></title>

<?php
$intra->loadJS();
$intra->loadCSS();
?>
</head>
<body>

<?php
$arrUsr = split("[\\]", $AUTH_USER);
$usrID = strtoupper($arrUsr[count($arrUsr)-1]);

if ($strMode == "LDAP"){
	$ldap_conn = ldap_connect($ldap_server);
	$binding = @ldap_bind($ldap_conn, $ldap_anonymous_login, $ldap_anonymous_pass);
}
 ?>

<script>
$(document).ready(function(){  
   
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