<?php
/***********************************************************************

eiseIntra core class

***********************************************************************/


include "inc_config.php";
include "inc_mysql.php";

class eiseIntra {

function __construct($oSQL, $conf = Array()){

    $this->conf = Array(                    //defaults for intra
        'dateFormat' => "d.m.Y" // 
        , 'timeFormat' => "H:i" // 
        , 'decimalPlaces' => "2"
        , 'decimalSeparator' => "."
        , 'thousandsSeparator' => ","
        , 'logofftimeout' => 360 //6 hours
        , 'addEiseIntraValueClass' => true
    );
    
    $this->conf = array_merge($this->conf, $conf);
    
    
    $arrFind = Array();
    $arrReplace = Array();
    $arrFind[] = '.'; $arrReplace[]='\\.';          
    $arrFind[] = '/'; $arrReplace[]='\\/';          
    $arrFind[] = 'd'; $arrReplace[]='([0-9]{1,2})'; 
    $arrFind[] = 'm'; $arrReplace[]='([0-9]{1,2})';
    $arrFind[] = 'Y'; $arrReplace[]='([0-9]{4})';
    $arrFind[] = 'y'; $arrReplace[]='([0-9]{1,2})';
    $this->conf['prgDate'] = str_replace($arrFind, $arrReplace, $this->conf['dateFormat']);
    $dfm  = preg_replace('/[^a-z]/i','', $this->conf['dateFormat']);
    $this->conf['prgDateReplaceTo'] = '\\'.(strpos($dfm, 'y')===false ? strpos($dfm, 'Y')+1 : strpos($dfm, 'y')+1).'-\\'.(strpos($dfm, 'm')+1).'-\\'.(strpos($dfm, 'd')+1);
    
    $arrFind = Array();
    $arrReplace = Array();            
    $arrFind[] = "."; $arrReplace[]="\\.";
    $arrFind[] = ":"; $arrReplace[]="\\:";
    $arrFind[] = "/"; $arrReplace[]="\\/";
    $arrFind[] = "H"; $arrReplace[]="([0-9]{1,2})";
    $arrFind[] = "h"; $arrReplace[]="([0-9]{1,2})";
    $arrFind[] = "i"; $arrReplace[]="([0-9]{1,2})";
    $arrFind[] = "s"; $arrReplace[]="([0-9]{1,2})";
    $this->conf["prgTime"] = str_replace($arrFind, $arrReplace, $this->conf["timeFormat"]);
    
    $this->oSQL = $oSQL;
    
}

/**********************************
   Authentication Routines
/**********************************/
function Authenticate($login, $password, &$strError, $method="LDAP"){
    
    $oSQL = $this->oSQL;
    
    switch($method) {
    case "LDAP":    
        GLOBAL $ldap_server;
        GLOBAL $ldap_domain;
        GLOBAL $ldap_dn;
        GLOBAL $ldap_conn;
        if (preg_match("/^([a-z0-9]+)[\/\\\]([a-z0-9]+)$/i", $login, $arrMatch)){
            $login = $arrMatch[2];
            $ldap_domain = strtolower($arrMatch[1].".abyrvalg.com");
        } else
            if (preg_match("/^([a-z0-9\_]+)[\@]([a-z0-9\.\-]+)$/i", $login, $arrMatch)){
                $login = $arrMatch[1];
                $ldap_domain = $arrMatch[2];
            }
            
        $ldap_conn = ldap_connect($ldap_server);
        $binding = @ldap_bind($ldap_conn, $ldap_anonymous_login, $ldap_anonymous_pass);
        
        if (!$binding){
            $strError = $this->translate("Connnection attempt to server failed")." ({$ldap_server})";
            $method = "database";
        } else {
            $ldap_login = $login."@".$ldap_domain;
            $ldap_pass = $password;
            ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);
            $binding = ldap_bind($ldap_conn, $ldap_login, $ldap_pass);

           if (!$binding){
                $strError = $this->translate("Bad password or user name");
                return false;
           } else
                return true;
        }
    case "mysql":
        $oSQL->dbhost = $_POST["host"];
        $oSQL->dbuser = $login;
        $oSQL->dbpass = $password;
        $oSQL->dbname = (!$_POST["database"] ? 'mysql' : $_POST["database"]) ;
        try {
            $oSQL->connect();
        } catch(Exception $e){
            $strError = $e->getMessage();
            return false;
        }
        return true;
    case "database":
    case "DB":
        if(!$oSQL->connect()){
            $strError = $this->translate("Unable to connect to database");
            return false;
        }
        $sqlAuth = "SELECT usrID FROM stbl_user WHERE usrID='{$login}' AND usrPass='".md5($password)."'";
        $rsAuth = $oSQL->do_query($sqlAuth);
        if ($oSQL->num_rows($rsAuth)==1)
            return true;
        else {
            $strError = $this->translate("Bad password or user name");
            return false;
        }
        break;
    }
    
}


function session_initialize(){
   session_set_cookie_params(0, eiseIntraCookiePath);
   session_start();
} 

function checkPermissions(){
   
   $oSQL = $this->oSQL;
   
   GLOBAL $strSubTitle;
   
   // checking user timeout
   if ($_SESSION["last_login_time"]!="" && $strSubTitle != "DEVELOPMENT" ){
      if (mktime() - strtotime($_SESSION["last_login_time"])>60*$this->conf['logofftimeout']) {
          header("HTTP/1.0 403 Access denied");
          $tt = Date("Y-m-d H:i:s", mktime())." - ".$_SESSION["last_login_time"];
          header ("Location: login.php?error=".urlencode($this->translate("Session timeout ($tt). Please re-login.")));
          die();
      }
   }
   
   //checking is user blocked or not?
   $rsUser = $oSQL->do_query("SELECT * FROM stbl_user WHERE usrID='".$_SESSION["usrID"]."'");
   $rwUser = $oSQL->fetch_array($rsUser);
   
   if (!$rwUser["usrID"]){
        header("HTTP/1.0 403 Access denied");
        header ("Location: login.php?error=".urlencode($this->translate("Your User ID doesnt exist in master database. Contact system administrator.")));
        die();
   }
   
   if ($rwUser["usrFlagDeleted"]){
        header("HTTP/1.0 403 Access denied");
        header ("Location: login.php?error=".urlencode($this->translate("Your User ID is blocked.")));
        die();
   }
   
   // checking script permissions
   $script_name = preg_replace("/^(\/[^\/]+)/", "", $_SERVER["PHP_SELF"]);
   $sqlCheckUser = "SELECT
             pagID
           , PAG.pagTitle
		   , PAG.pagTitleLocal
           , MAX(pgrFlagRead) as FlagRead
		   , MAX(pgrFlagCreate) as FlagCreate
		   , MAX(pgrFlagUpdate) as FlagUpdate
		   , MAX(pgrFlagDelete) as FlagDelete
           , MAX(pgrFlagWrite) as FlagWrite
           FROM stbl_page PAG
           INNER JOIN stbl_page_role PGR ON PAG.pagID=PGR.pgrPageID
           INNER JOIN stbl_role ROL ON PGR.pgrRoleID=ROL.rolID
           LEFT OUTER JOIN stbl_role_user RLU ON ROL.rolID=RLU.rluRoleID
           WHERE PAG.pagFile='$script_name'
               AND (
               (RLU.rluUserID='".strtoupper($_SESSION["usrID"])."' 	AND DATEDIFF(NOW(), rluInsertDate)>=0)
               OR
               ROL.rolFlagDefault=1
               )
           GROUP BY PAG.pagID, PAG.pagTitle;";
       //echo $sqlCheckUser;
    $rsChkPerms = $oSQL->do_query($sqlCheckUser);
    $rwPerms = $oSQL->fetch_array($rsChkPerms);
		
    if (!$rwPerms["FlagRead"]){
        header("HTTP/1.0 403 Access denied");
        $errortext = "".$_SERVER["PHP_SELF"].": ".$this->translate("access denied");
        SetCookie ("UserMessage", "ERROR: ".$errortext);
        header ("Location: ".(($_SERVER["HTTP_REFERER"]!="" && !strstr($_SERVER["HTTP_REFERER"], "login.php")) ? $_SERVER["HTTP_REFERER"] : "login.php?error=".urlencode($errortext)));
        die();
    } 
    
    
    $sqlRoles = "SELECT rolID, rolTitle$this->local
       FROM stbl_role ROL
       INNER JOIN stbl_role_user RLU ON RLU.rluRoleID=ROL.rolID
       WHERE RLU.rluUserID = '{$_SESSION["usrID"]}'	AND DATEDIFF(NOW(), rluInsertDate)>=0";
    $rsRoles = $oSQL->do_query($sqlRoles);
    $arrRoles = Array();
    $arrRoleIDs = Array();
    while ($rwRol = $oSQL->fetch_array($rsRoles)){
        $arrRoles[] = $rwRol["rolTitle$this->local"];
        $arrRoleIDs[] = $rwRol["rolID"];
    }
    $oSQL->free_result($rsRoles); 
     
    $this->arrUsrData = array_merge($rwUser, $rwPerms);
    $this->arrUsrData["roles"] = $arrRoles;
    $this->arrUsrData["roleIDs"] = $arrRoleIDs;
    $_SESSION["last_login_time"] = Date("Y-m-d H:i:s");
    
    $this->usrID = $this->arrUsrData["usrID"];
    return $this->arrUsrData;
     
}

function checkLanguage(){
    
    if(isset($_GET["local"])){
        switch ($_GET["local"]){
            case "on":
                SetCookie("l", "Local");
                break;
            case "off":
                SetCookie("l", "en");
                break;
            default:
                break;
        }
        header("Location: ".$_SERVER["PHP_SELF"]);
        die();
    } else 
    if (!isset($_COOKIE["l"]) && preg_match("/(ru|uk|be)/i", $_SERVER["HTTP_ACCEPT_LANGUAGE"])){
        SetCookie("l", "Local");
        $this->local = "Local";
    }

    $this->local = (isset($_COOKIE["l"]) 
        ? ( $_COOKIE["l"]=="en" 
            ? ""
            : "Local")
        : (isset($this->local) 
            ? $this->local 
            : ""));

}

function translate($key){
    
    if (!isset($this->lang[$key]) && $this->conf['collect_keys'] && $this->local){
        $this->addTranslationKey($key);
    }
    
    return isset($this->lang[$key]) ? $this->lang[$key] : $key;
}

function addTranslationKey($key){
    $oSQL = $this->oSQL;
    $sqlSTR = "INSERT IGNORE INTO stbl_translation (
        strKey
        , strValue
        ) VALUES (
        ".$oSQL->escape_string($key)."
        , '');";
    $oSQL->q($sqlSTR);
}


function readSettings(){
    
    $oSQL = $this->oSQL;
    
    /* инициализируем переменные из tbl_setup в массив ============================ BEGIN */
    
    $arrSetup = Array();
    
    $sqlSetup = "SELECT * FROM tbl_setup";

    $rsSetup = $oSQL->do_query($sqlSetup);

    while ($rwSetup = $oSQL->fetch_array($rsSetup)){

        switch ($rwSetup["stpCharType"]){
            case "varchar":
            case "text":
                $arrSetup[$rwSetup["stpVarName"]] = $rwSetup["stpCharValue"];
            break;
            case "numeric":
                if (preg_match("/^[0-9,\.]+$/",$rwSetup["stpCharValue"])) {
                    eval("\$arrSetup['".$rwSetup["stpVarName"]."'] = ".str_replace(",", ".", $rwSetup["stpCharValue"]).";\n");
                }
                break;
        case "boolean":
            switch ($rwSetup["stpCharValue"]){
                case "on":
                case "true":
                case "yes":
                case 1:
                    eval("\$arrSetup['".$rwSetup["stpVarName"]."'] = true; \n");
                    break;
                default:
                    eval("\$arrSetup['".$rwSetup["stpVarName"]."'] = false; \n");
                    break;
            }
            default: 
                $arrSetup[$rwSetup["stpVarName"]] = $rwSetup["stpCharValue"];
                break;
        }
    }
    /* инициализируем переменные из tbl_setup ============================ END */
    $this->conf = array_merge($this->conf, $arrSetup);
    
    return $arrSetup;
}


private $arrHTML5AllowedInputTypes = 
    Array("color", "date", "datetime", "datetime-local", "email", "month", "number", "range", "search", "tel", "time", "url", "week");

private function handleClass(&$arrConfig){

    $arrClass = Array();
    if ($this->conf['addEiseIntraValueClass'])
        $arrClass['eiseIntraValue'] = 'eiseIntraValue';
    
    // get contents of 'class' attribute in strAttrib
    $prgClass = "/\s+class=[\"\']([^\"\']+)[\"\']/i";
    $attribs = $arrConfig["strAttrib"];
    if (preg_match($prgClass, $attribs, $arrMatch)){
        $strClass = $arrMatch[1];
        $arrConfig["strAttrib"] = preg_replace($prgClass, "", $arrConfig["strAttrib"]);
    }
    
    // if we specify something in arrConfig, we add it to the string
    if (!is_array($arrConfig["class"])){
        $strClass = ($strClass!="" ? $strClass." " : "").$arrConfig["class"];
    } else {
        $strClass = ($strClass!="" ? $strClass." " : "").implode(" ",$arrConfig["class"]);
    }
    
    // split class sting into array
    $arrClassList = preg_split("/\s+/", $strClass); 
    // remove duplicates using unique key
    foreach($arrClassList as $class) 
        if($class!="")
            $arrClass[$class] =  $class;
    
    $arrConfig["class"] = $arrClass;
    return " class=\"".implode(" ", $arrClass)."\"";
}
    
function showTextBox($strName, $strValue, $arrConfig=Array()) {
    
    $flagWrite = isset($arrConfig["FlagWrite"]) ? $arrConfig["FlagWrite"] : $this->arrUsrData["FlagWrite"];
    
    $strClass = $this->handleClass($arrConfig);
    
    $strAttrib = $arrConfig["strAttrib"];
    if ($flagWrite){
        $type = (in_array($arrConfig['type'], $this->arrHTML5AllowedInputTypes) ? $arrConfig["type"] : 'text');
       $strRet = "<input type=\"{$type}\" name=\"{$strName}\" id=\"{$strName}\"".
            ($strAttrib ? " ".$strAttrib : "").
            ($strClass ? " ".$strClass : "").
            ($arrConfig["required"] ? " required=\"required\"" : "").
       " value=\"".htmlspecialchars($strValue)."\" />\r\n";
    } else {
        $strRet = "<div id=\"span_{$strName}\"".
        ($strAttrib ? " ".$strAttrib : "").$strClass.">".
        htmlspecialchars($strValue)."</div>\r\n".
        "<input type=\"hidden\" name=\"{$strName}\" id=\"{$strName}\"".
        " value=\"".htmlspecialchars($strValue)."\" />\r\n";
    }
    
   return $strRet;
}

function showTextArea($strName, $strValue, $arrConfig=Array()){
    $strAttrib = $arrConfig["strAttrib"];
    
    $flagWrite = isset($arrConfig["FlagWrite"]) ? $arrConfig["FlagWrite"] : $this->arrUsrData["FlagWrite"];
    
    $strClass = $this->handleClass($arrConfig);
    
    if ($flagWrite){
        $strRet .= "<textarea name=\"".$strName."\"";
        if($strAttrib) $strRet .= " ".$strAttrib;
        $strRet .= ($strClass ? " ".$strClass : "").
            ($arrConfig["required"] ? " required=\"required\"" : "").">";
        $strRet .= htmlspecialchars($strValue);
        $strRet .= "</textarea>";
    } else {
        $strRet = "<div id=\"span_{$strName}\"".
            ($strAttrib ? " ".$strAttrib : "").
            $strClass.">".
            htmlspecialchars($strValue)."</div>\r\n".
            "<input type=\"hidden\" name=\"{$strName}\" id=\"{$strName}\"".
            " value=\"".htmlspecialchars($strValue)."\" />\r\n";
    }
    return $strRet;        
    
}

function showCombo($strName, $strValue, $arrOptions, $arrConfig=Array()){
    
    $flagWrite = isset($arrConfig["FlagWrite"]) ? $arrConfig["FlagWrite"] : $this->arrUsrData["FlagWrite"];
    
    $retVal = "";
    
    $strClass = $this->handleClass($arrConfig);
    
    $strAttrib = $arrConfig["strAttrib"];
    if ($flagWrite){
        $retVal .= "<select id=\"".$strName."\" name=\"".$strName."\"".$strAttrib.
            ($strClass ? " ".$strClass : "").
            ($arrConfig["required"] ? " required=\"required\"" : "").">\r\n";
        if ($arrConfig["strZeroOptnText"]){
            $retVal .= "<option value=\"\">".htmlspecialchars($arrConfig["strZeroOptnText"])."</option>\r\n" ;
        }
        foreach ($arrOptions as $key => $value){
            $retVal .= "<option value='$key'".((string)$key==(string)$strValue ? " SELECTED " : "").">".str_repeat('&nbsp;',5*$arrConfig["indent"][$key])."{$value}</option>\r\n";
        }
        $retVal .= "</select>";
    } else {
        
        foreach ($arrOptions as $key => $value){
            if ((string)$key==(string)$strValue) {
               $valToShow = $value;
               $textToShow = $key;
               break;
            }
        }
        $valToShow=($valToShow!="" ? $valToShow : $arrConfig["strZeroOptnText"]);
        
        $retVal = "<div id=\"span_{$strName}\"{$strClass}>".htmlspecialchars($valToShow)."</div>\r\n".
        "<input type=\"hidden\" name=\"{$strName}\" id=\"{$strName}\"".
        " value=\"".htmlspecialchars($textToShow)."\" />\r\n";
        
    
    }
    return $retVal;
}

function showCheckBox($strName, $strValue, $arrConfig=Array()){
    
    $flagWrite = isset($arrConfig["FlagWrite"]) ? $arrConfig["FlagWrite"] : $this->arrUsrData["FlagWrite"];
    
    $strClass = $this->handleClass($arrConfig);
    
    $strAttrib = $arrConfig["strAttrib"];
    $retVal = "<input name=\"{$strName}\" id=\"{$strName}\" type=\"checkbox\"".
    ($strValue ? " checked=\"checked\" " : "").
    (!$flagWrite ? " readonly=\"readonly\"" : "").
    ($strAttrib!="" ? $strAttrib : " style='width:auto;'" ).">";

    return $retVal;
}

function showRadio($strRadioName, $strValue, $arrConfig){
    
    $flagWrite = isset($arrConfig["FlagWrite"]) ? $arrConfig["FlagWrite"] : $this->arrUsrData["FlagWrite"];
    
    $oSQL = $this->oSQL;
    
    $retVal = "";
    
    if ($arrConfig["strSQL"]){
        $rs = $oSQL->do_query($arrConfig["strSQL"]);
        while ($rw = $oSQL->fetch_array($rs)){
            $arrData[$rw["optValue"]] = $rw["optText"];
        }
    }
    
    $arrData = $arrConfig["arrOptions"];
    foreach($arrData as $value =>  $text){
        $inpID = $strRadioName."_".$value;
        $retVal .= "<input type=\"radio\" name=\"{$strRadioName}\" value=\"".htmlspecialchars($value)."\"";
        if ($strValue!="" && $value===$strValue)
            $retVal .= " checked";
        else if ($strValue=="" && $value==$arrConfig["strDefaultChecked"])
            $retVal .= " checked";
       $retVal .= " style=\"width:auto;\" id=\"{$inpID}\"".
        ($arrConfig["strAttrib"]!="" ? " ".$arrConfig["strAttrib"] : "").">".
        "<label for=\"{$inpID}\"".($arrConfig["strLabelAttrib"]!="" ? " ".$arrConfig["strLabelAttrib"] : "").">".
        htmlspecialchars($text)."</label><br>\r\n";
   }
   
   return $retVal;
   
}


function showAjaxDropdown($strFieldName, $strValue, $arrConfig) {
    
    $flagWrite = isset($arrConfig["FlagWrite"]) ? $arrConfig["FlagWrite"] : $this->arrUsrData["FlagWrite"];
    
    $oSQL = $this->oSQL;
    
    if ($strValue!="" && $arrConfig["strText"]==""){
        $rs = $this->getDataFromCommonViews($strValue, "", $arrConfig["strTable"], $arrConfig["strPrefix"]);
        $rw = $oSQL->fetch_array($rs);
        $arrConfig["strText"] = $rw["optText"];
    }
    
    $strOut = "";
    $strOut .= "<input type=\"hidden\" name=\"$strFieldName\" id=\"$strFieldName\" value=\"".htmlspecialchars($strValue)."\">\r\n";
    
    $strClass = $this->handleClass($arrConfig);
    
    if ($flagWrite){
        $strOut .= $this->showTextBox($strFieldName."_text", $arrConfig["strText"]
            , Array("FlagWrite"=>true
                , "strAttrib" => $arrConfig["strAttrib"]." src=\"{table:'{$arrConfig["strTable"]}', prefix:'{$arrConfig["strPrefix"]}'}\" autocomplete=\"off\""
                , "class" => array_merge($arrConfig["class"], Array("eiseIntra_ajax_dropdown"))));
    } else {
        $strOut .= "<div id=\"span_{$strFieldName}\"{$strClass}>".htmlspecialchars($arrConfig["strText"])."</div>\r\n";
    }
    
    return $strOut;
    
}


// Page-formatting routines
function loadJS(){
    GLOBAL $js_path, $arrJS;
 
        //-----------прицепляем содержимое массива  $arrJS
        for ($i=0;$i<count($arrJS);$i++){
           echo "<script type=\"text/javascript\" src=\"".$arrJS[$i]."\"></script>\r\n";
        }
        unset ($i);
        
//------------Прицепляет скрипты для конкретной страницы из js/*.js
		$arrScript = array_pop(explode("/",$_SERVER["PHP_SELF"]));
		$arrScript = explode(".",$arrScript);
		$strJS = (isset($js_path) ? $js_path : "js/").$arrScript[0].".js";
		if (file_exists( $strJS)) 
			echo "<script type=\"text/javascript\" src=\"{$strJS}\"></script>\r\n";
        
}

function loadCSS(){
    GLOBAL $arrCSS;
    
    for($i=0; $i<count($arrCSS); $i++ ){
        echo "<link rel=\"STYLESHEET\" type=\"text/css\" href=\"{$arrCSS[$i]}\" media=\"screen\">\r\n";
    }

}


/**********************************
   Database Routines
/**********************************/
// returns rs with data obtained from stadard views
function getTableInfo($dbName, $tblName){
    
    $oSQL = $this->oSQL;
    
    $sqlCols = "SHOW FULL COLUMNS FROM `".$tblName."`";
    $rsCols  = $oSQL->do_query($sqlCols);
    while ($rwCol = $oSQL->fetch_array($rsCols)){
        
        $strPrefix = (isset($strPrefix) && $strPrefix==substr($rwCol["Field"], 0, 3) 
            ? substr($rwCol["Field"], 0, 3)
            : (!isset($strPrefix) ? substr($rwCol["Field"], 0, 3) : "")
            );
        
        if (preg_match("/int/i", $rwCol["Type"]))
            $rwCol["DataType"] = "integer";
        
        if (preg_match("/float/i", $rwCol["Type"])
           || preg_match("/double/i", $rwCol["Type"])
           || preg_match("/decimal/i", $rwCol["Type"]))
            $rwCol["DataType"] = "real";
        
        if (preg_match("/tinyint/i", $rwCol["Type"])
            || preg_match("/bit/i", $rwCol["Type"]))
            $rwCol["DataType"] = "boolean";
        
        if (preg_match("/char/i", $rwCol["Type"])
           || preg_match("/text/i", $rwCol["Type"]))
            $rwCol["DataType"] = "text";
        
        if (preg_match("/binary/i", $rwCol["Type"])
           || preg_match("/blob/i", $rwCol["Type"]))
            $rwCol["DataType"] = "binary";
            
        if (preg_match("/date/i", $rwCol["Type"])
           || preg_match("/time/i", $rwCol["Type"]))
            $rwCol["DataType"] = "datetime";
            
        if (preg_match("/ID$/", $rwCol["Field"]) && $rwCol["Key"] != "PRI"){
            $rwCol["FKDataType"] = $rwCol["DataType"];
            $rwCol["DataType"] = "FK";
        }
        
        if ($rwCol["Key"] == "PRI" 
                || preg_match("/^$strPrefix(GU){0,1}ID$/i",$rwCol["Field"])
            ){
            $rwCol["PKDataType"] = $rwCol["DataType"];
            $rwCol["DataType"] = "PK";
        }
        
        if ($rwCol["Field"]==$strPrefix."InsertBy" 
          || $rwCol["Field"]==$strPrefix."InsertDate" 
          || $rwCol["Field"]==$strPrefix."EditBy" 
          || $rwCol["Field"]==$strPrefix."EditDate" ) {
            $rwCol["DataType"] = "activity_stamp"; 
            $arrTable['hasActivityStamp'] = true;
        }
        $arrCols[$rwCol["Field"]] = $rwCol;
        if ($rwCol["Key"] == "PRI"){
            $arrPK[] = $rwCol["Field"];
            if ($rwCol["Extra"]=="auto_increment")
                $pkType = "auto_increment";
            else 
                if (preg_match("/GUID$/", $rwCol["Field"]) && preg_match("/^(varchar)|(char)/", $rwCol["Type"]))
                    $pkType = "GUID";
                else 
                    $pkType = "user_defined";
        }
    }
    
    $sqlKeys = "SHOW KEYS FROM `".$tblName."`";
    $rsKeys  = $oSQL->do_query($sqlKeys);
    while ($rwKey = $oSQL->fetch_array($rsKeys)){
      $arrKeys[] = $rwKey;
    }
    
    //foreign key constraints
    $rwCreate = $oSQL->fetch_array($oSQL->do_query("SHOW CREATE TABLE `{$tblName}`"));
    $strCreate = $rwCreate["Create Table"];
    $arrCreate = explode("\n", $strCreate);$arrCreateLen = count($arrCreate);
    for($i=0;$i<$arrCreateLen;$i++){
        // CONSTRAINT `FK_vhcTypeID` FOREIGN KEY (`vhcTypeID`) REFERENCES `tbl_vehicle_type` (`vhtID`)
        if (preg_match("/^CONSTRAINT `([^`]+)` FOREIGN KEY \(`([^`]+)`\) REFERENCES `([^`]+)` \(`([^`]+)`\)/", trim($arrCreate[$i]), $arrConstraint)){
            foreach($arrCols as $idx=>$col){
                if ($col["Field"]==$arrConstraint[2]) { //if column equals to foreign key constraint
                    $arrCols[$idx]["DataType"]="FK";
                    $arrCols[$idx]["ref_table"] = $arrConstraint[3];
                    $arrCols[$idx]["ref_column"] = $arrConstraint[4];
                    break;
                }
            }
            /*
            echo "<pre>";
            print_r($arrConstraint);
            echo "</pre>";
            //*/
        }
    }
    
    $arrColsIX = Array();
    foreach($arrCols as $ix => $col){ $arrColsIX[$col["Field"]] = $col["Field"]; }
    
    $strPKVars = $strPKCond = $strPKURI = '';
    foreach($arrPK as $pk){
        $strPKVars .= "\${$pk}  = (isset(\$_POST['{$pk}']) ? \$_POST['{$pk}'] : \$_GET['{$pk}'] );\r\n";
        $strPKCond .= ($strPKCond!="" ? " AND " : "")."`{$pk}` = \".".(
                in_array($arrCols["DataType"], Array("integer", "boolean"))
                ? "(int)(\${$pk})"
                : "\$oSQL->e(\${$pk})"
            ).".\"";
        $strPKURI .= ($strPKURI!="" ? "&" : "")."{$pk}=\".urlencode(\${$pk}).\"";
    }
    
    $arrTable['columns'] = $arrCols;
    $arrTable['keys'] = $arrKeys;
    $arrTable['PK'] = $arrPK;
    $arrTable['PKtype'] = $pkType;
    $arrTable['prefix'] = $strPrefix;
    $arrTable['table'] = $tblName;
    $arrTable['columns_index'] = $arrColsIX;
    
    $arrTable["PKVars"] = $strPKVars;
    $arrTable["PKCond"] = $strPKCond;
    $arrTable["PKURI"] = $strPKURI;

    
    return $arrTable;
}


function getSQLValue($col, $flagForArray=false){
    $strValue = "";
    
    $strPost = "\$_POST['".$col["Field"]."']".($flagForArray ? "[\$i]" : "");
    
    if (preg_match("/norder$/i", $col["Field"]))
        $col["DataType"] = "nOrder";
    
    switch($col["DataType"]){
      case "integer":
        $strValue = "'\".(integer)$strPost.\"'";
        break;
      case "nOrder":
        $strValue = "'\".($strPost=='' ? \$i : $strPost).\"'";
        break;
      case "real":
      case "numeric":
        $strValue = "'\".(double)str_replace(',', '', $strPost).\"'";
        break;
      case "boolean":
        if (!$flagForArray)
           $strValue = "'\".($strPost=='on' ? 1 : 0).\"'";
        else
           $strValue = "'\".(integer)\$_POST['".$col["Field"]."'][\$i].\"'";
        break;
      case "text":
	  case "varchar":
        $strValue = "\".\$oSQL->escape_string($strPost).\"";
        break;
      case "binary":
        $strValue = "\".mysql_real_escape_string(\$".$col["Field"].").\"";
        break;
      case "datetime":
        $strValue = "\".\$intra->datetimePHP2SQL($strPost).\"";
        break;
      case "date":
        $strValue = "\".\$intra->datePHP2SQL($strPost).\"";
        break;
      case "activity_stamp":
        if (preg_match("/By$/i", $col["Field"]))
           $strValue .= "'\$intra->usrID'";
        if (preg_match("/Date$/i", $col["Field"]))
           $strValue .= "NOW()";
        break;
      case "FK":
      case "combobox":
       $strValue = "\".($strPost!=\"\" ? \"'\".$strPost.\"'\" : \"NULL\").\"";
        break;
      case "PK":
      default:
        $strValue = "'\".$strPost.\"'";
        break;
    }
    return $strValue;
}

function getMultiPKCondition($arrPK, $strValue){
    $arrValue = explode("##", $strValue);
    $sql_ = "";
    for($jj = 0; $jj < count($arrPK);$jj++)
        $sql_ .= ($sql_!="" ? " AND " : "").$arrPK[$jj]."='".$arrValue[$jj]."'";
    return $sql_;
}


function getDataFromCommonViews($strValue, $strText, $strTable, $strPrefix, $flagShowDeleted=false, $extra=''){
    
    $oSQL = $this->oSQL;

    if ($strPrefix!=""){
        $arrFields = Array(
            "idField" => "{$strPrefix}ID"
            , "textField" => "{$strPrefix}Title"
            , "textFieldLocal" => "{$strPrefix}TitleLocal"
            , "delField" => "{$strPrefix}FlagDeleted"
            );
    } else {
        $arrFields = Array(
            "idField" => "optValue"
            , "textField" => "optText"
            , "textFieldLocal" => "optTextLocal"
            , "delField" => "optFlagDeleted"
        );
    }    
    
    $sql = "SELECT `".$arrFields["textField{$this->local}"]."` as optText, `{$arrFields["idField"]}` as optValue
        FROM `{$strTable}`";
    
    if ($strValue!=""){ // key-based search
        $sql .= "\r\nWHERE `{$arrFields["idField"]}`=".$oSQL->escape_string($strValue);
    } else { //value-based search
        $strExtra = '';
        if ($extra!=''){
            $arrExtra = explode("|", $extra);
            foreach($arrExtra as $ix=>$ex){ $strExtra = ' AND extra'.($ix==0 ? '' : $ix).' = '.$oSQL->e($ex); }
        }
        $sql .= "\r\nWHERE 
        (`{$arrFields["textField"]}` LIKE ".$oSQL->escape_string($strText, "for_search")." COLLATE 'utf8_general_ci'
            OR `{$arrFields["textFieldLocal"]}` LIKE ".$oSQL->escape_string($strText, "for_search")." COLLATE 'utf8_general_ci'";

        $sql .=	")
		".($flagShowDeleted==false ? " AND IFNULL(`{$arrFields["delField"]}`, 0)=0" : "")
        .$strExtra;
    }
    $sql .= "\r\nLIMIT 0, 30";
    $rs = $oSQL->do_query($sql);
    
    return $rs;
}

function dateSQL2PHP($dtVar){
$result =  $dtVar ? date($this->conf["dateFormat"], strtotime($dtVar)) : "";
return $result ;
}

function datetimeSQL2PHP($dtVar){
$result =  $dtVar ? date($this->conf["dateFormat"]." ".$this->conf["timeFormat"], strtotime($dtVar)) : "";
return $result ;
}

function datePHP2SQL($dtVar, $valueIfEmpty="NULL"){
    $result =  (
        preg_match("/^".$this->conf["prgDate"]."$/", $dtVar) 
        ? "'".preg_replace("/".$this->conf["prgDate"]."/", $this->conf["prgDateReplaceTo"], $dtVar)."'" 
        : $valueIfEmpty 
        );
    return $result;
}
function datetimePHP2SQL($dtVar, $valueIfEmpty="NULL"){
    $prg = "/^".$this->conf["prgDate"]."( ".$this->conf["prgTime"]."){0,1}$/";
    echo $prg."\r\n";
    echo "/".$this->conf["prgDate"]."/", $this->conf["prgDateReplaceTo"]."\r\n";
    $result =  (
        preg_match($prg, $dtVar) 
        ? "'".preg_replace("/".$this->conf["prgDate"]."/", $this->conf["prgDateReplaceTo"], $dtVar)."'" 
        : $valueIfEmpty 
        );
    return $result;
}

function showDatesPeriod($trnStartDate, $trnEndDate){
    $strRet = (!empty($trnStartDate)
            ? $this->DateSQL2PHP($trnStartDate)
            : ""
            );
    $strRet .= ($strRet!="" && !empty($trnEndDate) && !($trnStartDate==$trnEndDate)
            ? " - "
            : "");
    $strRet .= (!empty($trnEndDate) && !($trnStartDate==$trnEndDate)
            ? $this->DateSQL2PHP($trnEndDate)
            : ""
            );
    
    return $strRet;
}

function getUserData($usrID){
    $oSQL = $this->oSQL;
    $rs = $this->getDataFromCommonViews($usrID, "", "svw_user", "");
    $rw = $oSQL->fetch_array($rs);
    
    return ($rw["optValue"]!="" 
        ? ($rw["optText{$this->local}"]==""
            ? $rw["optText"]
            : $rw["optText{$this->local}"])
         : $usrID);
}


/******************************************************************************/
/* ARCHIVE/RESTORE ROUTINES                                                   */
/******************************************************************************/

function getArchiveSQLObject(){
	
	if (!$this->conf["stpArchiveDB"])
        throw new Exception("Archive database name is not set. Contact system administrator.");
	
	//same server, different DBs
	$this->oSQL_arch = new sql($this->oSQL->dbhost, $this->oSQL->dbuser, $this->oSQL->dbpass, $this->conf["stpArchiveDB"], false, CP_UTF8);
    $this->oSQL_arch->connect();
	
	return $this->oSQL_arch;
	
}


function archiveTable($table, $criteria, $nodelete = false, $limit = ""){
	
	$oSQL = $this->oSQL;
	
	if (!isset($this->oSQL_arch))
		$this->getArvhiceSQLObject();
	
    $oSQL_arch = $this->oSQL_arch;
    $intra_arch = new eiseIntra($oSQL_arch);
    
	// 1. check table exists in archive DB
	if(!$oSQL_arch->d("SHOW TABLES LIKE ".$oSQL->e($table))){
		// if doesnt exists, we create it w/o indexes, on MyISAM engine
        $sqlGetCreate = "SHOW CREATE TABLE `{$table}`";
        $rsC = $oSQL->q($sqlGetCreate);
        $rwC = $oSQL->f($rsC);
        $sqlCR = $rwC["Create Table"];
        //skip INDEXes and FKs
        $arrS = preg_split("/(\r|\n|\r\n)/", $sqlCR);
        $sqlCR = "";
        foreach($arrS as $ix => $string){
            if (preg_match("/^(INDEX|KEY|CONSTRAINT)/", trim($string))){
                continue;
            }
            $string = preg_replace("/(ENGINE=InnoDB)/", "ENGINE=MyISAM", $string);
            $string = preg_match("/^PRIMARY/", trim($string)) ? preg_replace("/\,$/", "", trim($string)) : $string;
            $sqlCR .= ($sqlCR!="" ? "\r\n" : "").$string;
        }
        $oSQL_arch->q($sqlCR);
        
	}
    
    // if table exists, we check it for missing columns
    $arrTable = $this->getTableInfo($oSQL->dbname, $table);
    $arrTable_arch = $intra_arch->getTableInfo($oSQL_arch->dbname, $table);
    $arrCol_arch = Array();
    foreach($arrTable_arch["columns"] as $col) $arrCol_arch[] = $col["Field"];
    $strFields = "";
    foreach($arrTable["columns"] as $col){
        //if column is missing, we add column
        if (!in_array($col["Field"], $arrCol_arch)){
            $sqlAlter = "ALTER TABLE `{$table}` ADD COLUMN `{$col["Field"]}` {$col["Type"]} ".
                ($col["Null"]=="YES" ? "NULL" : "NOT NULL").
                " DEFAULT ".($col["Null"]=="YES" ? "NULL" : $oSQL->e($col["Default"]) );
            $oSQL_arch->q($sqlAlter);
        }
        
        $strFields .= ($strFields!="" ? "\r\n, " : "")."`{$col["Field"]}`";
        
    }
    
    // 2. insert-select to archive from origin
    // presume that origin and archive are on the same host, archive user can do SELECT from origin
    $sqlIns = "INSERT IGNORE INTO `{$table}` ({$strFields})
        SELECT {$strFields}
        FROM `{$oSQL->dbname}`.`{$table}`
        WHERE {$criteria}".
        ($limit!="" ? " LIMIT {$limit}" : "");
	$oSQL_arch->q($sqlIns);
    $nAffected = $oSQL->a();
    
    // 3. delete from the origin
    if (!$nodelete)
        $oSQL->q("DELETE FROM `{$table}` WHERE {$criteria}".($limit!="" ? " LIMIT {$limit}" : ""));
	
    return $nAffected;
}



}










?>