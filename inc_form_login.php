<?php
$DataAction = isset($_POST["DataAction"]) ? $_POST["DataAction"] : (isset($_GET["DataAction"]) ? $_GET["DataAction"] : '');

if(!isset($intra))
    $intra = new eiseIntra($oSQL, Array('version'=>$version));

$flagEiseAdmin = is_a($intra, 'eiseAdmin');

$flagShowHost = isset($flagShowHost) ? $flagShowHost : false;


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
            
            $intra->Authenticate( $login, $password, (isset($authmethod) ? $authmethod : "LDAP"), ($authmethod=="mysql" ? array('dbhost'=>$_POST['host']) : null) );
            $pageNoAuth = (isset($_COOKIE["PageNoAuth"]) 
                && parse_url($_COOKIE["PageNoAuth"], PHP_URL_PATH)!==$_SERVER['PHP_SELF'] 
                 ? $_COOKIE["PageNoAuth"] 
                 : "index.php");
            header ("Location: {$pageNoAuth}");
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
<body class="form-login" data-conf="<?php  echo htmlspecialchars(json_encode($intra->conf)) ; ?>">

<?php
if(isset($AUTH_USER)){
    $arrUsr = explode("[\\]", $AUTH_USER);
    $usrID = strtoupper($arrUsr[count($arrUsr)-1]);
}
if (isset($strMode) && $strMode == "LDAP"){
	$ldap_conn = ldap_connect($ldap_server);
	$binding = @ldap_bind($ldap_conn, $ldap_anonymous_login, $ldap_anonymous_pass);
}
 ?>

<script>
$(document).ready(function(){  

    $('body').eiseIntra('cleanStorage');

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

<h1>Welcome to <?php  echo $title ; ?></h1>

<?php 
if (isset($_GET["error"]) && $_GET['error']){
?>
<div class="eiseIntraError" style="text-align: center; margin-left: auto; margin-right: auto; margin-top: 30px; text-align: left; padding-left: 30px;">ERROR: <?php  echo $_GET["error"] ; ?></div>
<?php
}
 ?>
<form action="<?php echo $_SERVER["PHP_SELF"] ?>" id="loginform" method="POST" onsubmit="return LoginForm();" class="eiseIntraForm">
<input type="hidden" id="DataAction" name="DataAction" value="login">
<input type="hidden" id="authstring" name="authstring" value="">
<fieldset class="eiseIntraMainForm">

<?php 
if ($flagShowHost || $flagEiseAdmin) 
    echo $intra->field('Host', 'host', $flagEiseAdmin ? '' : 'localhost');

echo $intra->field('Login', 'login', $flagEiseAdmin ? 'root' : (isset($_COOKIE["last_succesfull_usrID"]) ? $_COOKIE["last_succesfull_usrID"] : ''), ['FlagWrite'=>true]);
echo $intra->field('Password', 'password', '', ['FlagWrite'=>true, 'type'=>'password']);

$binding = isset($binding) ? $binding : null;
$login_info = "Please enter your <strong>".($binding ? "Windows" : "database")."</strong> login/password.";

echo $intra->field('', null, $intra->showButton('btnsubmit'
        , $intra->translate("Login"), ['type'=>'submit', 'FlagWrite'=>true]).'<br>'.$login_info
        , ['id'=>'div-login'])

?>

</fieldset>
</form>
</div>

<?php  echo $extraBottomHTML; ?>

</body>
</html>