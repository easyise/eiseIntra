<?php
$arrJS[] = eiseIntraRelativePath."intra.js";
$arrCSS[] = eiseIntraRelativePath."intra.css";
$arrCSS[] = commonStuffRelativePath."screen.css";

$DataAction = isset($_POST["DataAction"]) ? $_POST["DataAction"] : $_GET["DataAction"];

if (!isset($oSQL))
    $oSQL = new sql($DBHOST, $DBUSER, $DBPASS, $DBNAME, false, CP_UTF8);
if($authmethod!="mysql") $oSQL->connect();
$intra = new eiseIntra($oSQL);


switch ($DataAction){
    case "login":
       $auth_str = base64_decode($_POST["authstring"]);

       preg_match("/^([^\:]+)\:([\S ]+)$/i", $auth_str, $arrMatches);
       $arrLoginPassword = Array($arrMatches[1], $arrMatches[2]);
       
        $login = $arrMatches[1];
        $password = $arrMatches[2];
        $strError = "";
       
        if ($intra->Authenticate($login, $password, $strError, (isset($authmethod) ? $authmethod : "LDAP"))){
            $intra->session_initialize();
            
            $_SESSION["last_login_time"] = Date("Y-m-d H:i:s");
            if($authmethod=="mysql"){
                $_SESSION["usrID"] = $login;
                $_SESSION["DBHOST"] = $oSQL->dbhost;
                $_SESSION["DBPASS"] = $oSQL->dbpass;
                $_SESSION["DBNAME"] = $oSQL->dbname;
            } else {
                $_SESSION["usrID"] = strtoupper($login);
            }
            SetCookie("last_succesfull_usrID", $login, eiseIntraCookieExpire, eiseIntraCookiePath);
            header ("Location: ".(isset($_COOKIE["PageNoAuth"]) ? $_COOKIE["PageNoAuth"] : "index.php"));
        } else {
            SetCookie("last_succesfull_usrID", "", eiseIntraCookieExpire, eiseIntraCookiePath);
            header ("Location: login.php?error=".$strError);
        }
        die();
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
function base64ToAscii(c)
{
    var theChar = 0;
    if (0 <= c && c <= 25){
        theChar = String.fromCharCode(c + 65);
    } else if (26 <= c && c <= 51) {
        theChar = String.fromCharCode(c - 26 + 97);
    } else if (52 <= c && c <= 61) {
        theChar = String.fromCharCode(c - 52 + 48);
    } else if (c == 62) {
        theChar = '+';
    } else if( c == 63 ) {
        theChar = '/';
    } else {
        theChar = String.fromCharCode(0xFF);
    } 
	return (theChar);
}

function base64Encode(str) {
    var result = "";
    var i = 0;
    var sextet = 0;
    var leftovers = 0;
    var octet = 0;

    for (i=0; i < str.length; i++) {
         octet = str.charCodeAt(i);
         switch( i % 3 )
         {
         case 0:
                {
                    sextet = ( octet & 0xFC ) >> 2 ;
                    leftovers = octet & 0x03 ;
                    // sextet contains first character in quadruple
                    break;
                }
          case 1:
                {
                    sextet = ( leftovers << 4 ) | ( ( octet & 0xF0 ) >> 4 );
                    leftovers = octet & 0x0F ;
                    // sextet contains 2nd character in quadruple
                    break;
                }
          case 2:

                {

                    sextet = ( leftovers << 2 ) | ( ( octet & 0xC0 ) >> 6 ) ;
                    leftovers = ( octet & 0x3F ) ;
                    // sextet contains third character in quadruple
                    // leftovers contains fourth character in quadruple
                    break;
                }

         }
         result = result + base64ToAscii(sextet);
         // don't forget about the fourth character if it is there

         if( (i % 3) == 2 )
         {
               result = result + base64ToAscii(leftovers);
         }
    }

    // figure out what to do with leftovers and padding
    switch( str.length % 3 )
    {
    case 0:
        {
             // an even multiple of 3, nothing left to do
             break ;
        }

    case 1:
        {
            // one 6-bit chars plus 2 leftover bits
            leftovers =  leftovers << 4 ;
            result = result + base64ToAscii(leftovers);
            result = result + "==";
            break ;
        }

    case 2:
        {
            // two 6-bit chars plus 4 leftover bits
            leftovers = leftovers << 2 ;
            result = result + base64ToAscii(leftovers);
            result = result + "=";
            break ;
        }

    }

    return (result);

}

function LoginForm(){
    var frm = document.forms.loginform;

    var authinput=frm.authstring;

    var login = frm.login.value;
    var password = frm.password.value;
    
    var host = (frm.host!=null ? frm.host.value : "");
    
    var authstr = login+":"+password;

    if (login.match(/^[a-z0-9_\\\/\@\.\-]{1,50}$/i)==null){
      alert("You should specify your login name");
      frm.login.focus();
      return (false);
    }

    if (password.match(/^[\S ]+$/i)==null){
      alert("You should specify your password");
      frm.password.focus();
      return (false);
    }
    frm.login.value="";
    frm.password.value="";
    frm.btnsubmit.disabled=true;
	frm.btnsubmit.value="Logging on...";

    authstr = base64Encode(authstr);
    authinput.value=authstr;
  
    return (true);
}

$(document).ready(function(){  
   
   var host = document.getElementById("host");
   if(host!=null) {
       host.value="localhost";
   } 
      
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
<form action="<?php echo $_SERVER["PHP_SELF"] ?>" name="loginform" method="POST" onsubmit="return LoginForm();" class="eiseIntraForm">
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