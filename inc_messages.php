<?php
namespace eiseIntra;

require_once(dirname(__FILE__).'/inc_item.php');

define('MESSAGE_P_CLEANUP', 0.1); // probability of cleanup routine to be triggered on each sendMessages() function call.
define('MESSAGE_DAYS_CLEANUP', 7); // number of days after which unsent messages will be cleaned up from queue.

class Messages {

public $item;
public $intra;
public $oSQL;
public $conf;
public $id;

static $defaultConf = array(
    'mail_send_batchsize' => 10,
    'mail_send_authenticate' => 'email'    , // options: 'email', 'onbehalf', 'serviceaccount'
);

public function __construct($item){

    $this->item = $item;

    $this->intra = $this->item->intra;
    $this->oSQL = $this->item->oSQL;
    $conf = self::$defaultConf;
    $conf['mail_send_authenticate'] =(defined('eiseIntraMailSendAuthenticate') 
        ? eiseIntraMailSendAuthenticate 
        : self::$defaultConf['mail_send_authenticate']
        );
    $this->conf = array_merge($conf, $this->item->conf);
    $this->id = $this->item->id;


}

function formMessages(){

    $oldFlagWrite = $this->intra->arrUsrData['FlagWrite'];
    $this->intra->arrUsrData['FlagWrite'] = true;

    $entID = ($this->conf['entID'] ? $this->conf['entID'] : $this->conf['prefix']);

    $strRes = '<div id="ei_messages" class="eif-messages-dialog" title="'.$this->intra->translate('Messages').'">'."\n";

    $strRes .= '<div class="eiseIntraMessage eif_template eif_evenodd">'."\n";
    $strRes .= '<div class="eif_msgInsertDate"></div>';
    $strRes .= '<div class="eiseIntraMessageField eif-field"><label>'.$this->intra->translate('From').':</label><span class="eif_msgFrom"></span></div>';
    $strRes .= '<div class="eiseIntraMessageField eif-field"><label>'.$this->intra->translate('To').':</label><span class="eif_msgTo"></span></div>';
    $strRes .= '<div class="eiseIntraMessageField eif-field eif_invisible"><label>'.$this->intra->translate('CC').':</label><span class="eif_msgCC"></span></div>';
    $strRes .= '<div class="eiseIntraMessageField eif-field eif_invisible"><label>'.$this->intra->translate('Subject').':</label><span class="eif_msgSubject"></span></div>';
    $strRes .= '<pre class="eif_msgText"></div>';
    $strRes .= '</pre>'."\n";

    $strRes .= '<div class="eif_notfound">';
    $strRes .= '<td colspan=3>'.$this->intra->translate('No Messages Found').'</td>';
    $strRes .= '</div>';

    $strRes .= '<div class="eif_spinner">';
    $strRes .= '</div>';
    
    $strRes .= '<div class="eiseIntraMessageButtons"><input type="button" id="msgNew" value="'.$this->intra->translate('New Message').'">';
    $strRes .= '</div>';
        
    $strRes .= "</div>\r\n";

    $strRes .= '<form id="ei_message_form" class="eif-message-form" title="'.$this->intra->translate('New Message').'" class="eiseIntraForm" method="POST">'."\n";
    $strRes .= '<input type="hidden" name="DataAction" id="DataAction_attach" value="sendMessage">'."\r\n";
    $strRes .= '<input type="hidden" name="entID" id="entID_Message" value="'.$entID.'">'."\r\n";
    $strRes .= '<input type="hidden" name="entItemID" id="entItemID_Message" value="'.$this->id.'">'."\r\n";
    $strRes .= '<div class="eiseIntraMessageField eif-field"><label>'.$this->intra->translate('To').':</label>'
        .$this->intra->showAjaxDropdown('msgToUserID', '', array('required'=>true, 'source'=>'svw_user')).'</div>';
    $strRes .= '<div class="eiseIntraMessageField eif-field"><label>'.$this->intra->translate('CC').':</label>'
        .$this->intra->showAjaxDropdown('msgCCUserID', '', array('source'=>'svw_user')).'</div>';
    $strRes .= '<div class="eiseIntraMessageField eif-field"><label>'.$this->intra->translate('Subject').':</label>'.$this->intra->showTextBox('msgSubject', '').'</div>';
    $strRes .= '<div class="eiseIntraMessageBody">'.$this->intra->showTextArea('msgText', '').'</div>';
    $strRes .= '<div class="eiseIntraMessageButtons"><input type="submit" id="msgPost" value="'.$this->intra->translate('Send').'">
        <input type="button" id="msgClose" value="'.$this->intra->translate('Close').'">
        </div>';
    $strRes .= "</form>\r\n";

	// DISABLED because mail sending is on crontab
    // if( $this->intra->conf['flagRunMessageSend'] && file_exists(dirname($_SERVER['SCRIPT_FILENAME']).DIRECTORY_SEPARATOR.'bat_messagesend.php') ){
    // 	$strRes .= "\n".'<script type="text/javascript">$(document).ready(function(){ $.get("bat_messagesend.php?nc="+Math.random()*1000); });</script>'."\n";
    // }

    $this->intra->arrUsrData['FlagWrite'] = $oldFlagWrite;

    return $strRes;

}

/**
 * This function obtains message list for current entity item - just an array of records from stbl_message.
 * 
 * @return string - JSON with message list.
 *
 * @category Messages
 */
public function getMessages(){

    $oSQL = $this->oSQL;
    $entID = ($this->conf['entID'] ? $this->conf['entID'] : $this->conf['prefix']);
    $intra = $this->intra;

    $fields = $oSQL->ff('SELECT * FROM stbl_message WHERE 1=0');

    $sqlMsg = "SELECT *
    , (SELECT optText FROM svw_user WHERE optValue=msgFromUserID) as msgFrom
    , (SELECT optText FROM svw_user WHERE optValue=msgToUserID) as msgTo
    , (SELECT optText FROM svw_user WHERE optValue=msgCCUserID) as msgCC
     FROM stbl_message 
     WHERE msgEntityID='$entID' AND msgEntityItemID='{$this->id}'
     ".($fields['msgFlagBroadcast'] 
     	? "AND msgFlagBroadcast=0" 
     	: '')."
    ORDER BY msgInsertDate DESC";
    $rsMsg = $oSQL->q($sqlMsg);

    return $intra->result2JSON($rsMsg);

}

/**
 * This function does not actually send a message, it just adds a record to stbl_message (message queue). Then this table is being scanned with [eiseItem::sendMessages()](#eiseitem-sendmessages) and any unsent messages will be physically sent and marked as 'sent' afterwards.
 * 
 * @param array $nd - message data, it can be a copy of $_POST array.
 * 
 * @category Messages
 */
public function sendMessage($nd){

	$oSQL = $this->oSQL;
	$entID = ($this->conf['entID'] ? $this->conf['entID'] : $this->conf['prefix']);
	$intra = $this->intra;

    $oSQL->startProfiling();

	try {
        self::checkMessageQueueExists($oSQL);    
    } catch (\Exception $e) {
        $intra->redirect( 'ERROR: '.$e->getMessage(), $this->conf['form'].'?'.$this->item->getURI() );
    }

    $fields = $oSQL->ff('SELECT * FROM stbl_message_queue WHERE 1=0');
    if($fields['msgPassword']){
        list($login, $password) = $this->intra->decodeAuthstring($_SESSION['authstring']);
    }

    $metadata = array('title'=>$this->conf['title'.$intra->local]
        , 'number'=>$this->item->id
        , 'id'=>$this->item->id
    	, 'href'=>\eiseIntra::getFullHREF($this->conf['form'].'?'.$this->item->getURI())
    	);
    if(isset($nd['msgMetadata'])){
    	$metadata = array_merge(
    		$metadata, 
    		(is_array($nd['msgMetadata']) 
    			?  $nd['msgMetadata']
    			: (array)json_decode($nd['msgMetadata'], true)
    			)
    		);
    }

    $sqlMsg = "INSERT INTO stbl_message_queue SET
        msgEntityID = ".$oSQL->e($entID)."
        , msgEntityItemID = ".(!$nd['entItemID'] ? $oSQL->e($this->item->id) : $oSQL->e($nd['entItemID']))."
        , msgFromUserID = '$intra->usrID'
        , msgToUserID = ".($nd['msgToUserID']!="" ? $oSQL->e($nd['msgToUserID']) : "NULL")."
        , msgCCUserID = ".($nd['msgCCUserID']!="" ? $oSQL->e($nd['msgCCUserID']) : "NULL")."\n"
        .(isset($fields['msgToUserEmail']) && !empty($nd['msgToUserEmail']) ? ", msgToUserEmail=".$oSQL->e($nd['msgToUserEmail']) : '')."\n"
        .(isset($fields['msgCCUserEmail']) && !empty($nd['msgCCUserEmail']) ? ", msgCCUserEmail=".$oSQL->e($nd['msgCCUserEmail']) : '')."
        , msgSubject = ".$oSQL->e($nd['msgSubject'])."
        , msgText = ".$oSQL->e($nd['msgText'])
        .(isset($fields['msgPassword']) && $this->conf['mail_send_authenticate'] == 'email' ? ", msgPassword=".$oSQL->e($intra->encrypt($password)) : '')."\n"
        .(isset($fields['msgFlagBroadcast']) && isset($nd['msgFlagBroadcast']) ? ", msgFlagBroadcast=".(int)($nd['msgFlagBroadcast']) : '')."\n"
        .(isset($fields['msgGUID']) 
        	? ", msgGUID=".(!empty($nd['msgGUID'])
	        	? $oSQL->e($nd['msgGUID']) 
	        	: "UUID()"
                ) 
        	: ''
            )."\n"
        ."
        , msgMetadata = ".$oSQL->e(json_encode($metadata, true))."
        , msgSendDate = NULL
        , msgReadDate = NULL
        , msgFlagDeleted = 0
        , msgInsertBy = '$intra->usrID', msgInsertDate = NOW(), msgEditBy = '$intra->usrID', msgEditDate = NOW()";
    $oSQL->q($sqlMsg);

    // $oSQL->showProfileInfo();
    // die();

	$intra->redirect($intra->translate('Message sent'), $this->conf['form'].'?'.$this->item->getURI());

}

/**
 * This function scans stbl_message and sends any unsent message. It uses eiseMail library for send routines.
 * 
 * @param array $conf - an array with various send options:
 *  - 'authenticate' ['email', 'onbehalf'] - when 'email', it uses sender's email to authenticate on SMTP server. When 'onbehalf' - it uses `$conf['login']` and `$conf['password']` for SMTP authentication. In other cases it uses 'usrID' and 'msgPassword' for authentication.
 * 
 * 
 */
static function sendMessages($conf){
    
    GLOBAL $intra;

    $oSQL = $intra->oSQL;

    $oSQL->startProfiling();

    $oSQL->q('START TRANSACTION');

    self::checkMessageQueueExists();

    self::cleanupMessageQueue($oSQL);

    $oSQL->q('COMMIT');

    include_once(commonStuffAbsolutePath.'/eiseMail/inc_eisemail.php');

    $conf = array_merge(self::$defaultConf, $conf);
    
    $limits = (($conf['authenticate']=='email') ? 'LIMIT 1' : "LIMIT {$conf['mail_send_batchsize']}");
    $oSQL->q("START TRANSACTION");
    $sqlMsg = "SELECT * FROM stbl_message_queue ORDER BY msgInsertDate {$limits} FOR UPDATE";
	$rsMsg = $oSQL->q($sqlMsg);
    
    $rwMsg = $oSQL->f($rsMsg);
    if($oSQL->n($rsMsg)==0){
        $oSQL->q("COMMIT");
        echo "No messages to send.\n";
        if($conf['verbose']){
            $oSQL->showProfileInfo();
        }
        return; // nothing to send
    }
    	

    // 1. dealing with authentication basing on FROM and $conf
    $rwUsr_From = $intra->getUserData_All($rwMsg['msgFromUserID'], 'all');
    $arrAuth = array();
    switch($conf['authenticate']){
        case 'email':
            $arrAuth['login'] = $rwUsr_From['usrEmail'];
            break;
        case 'onbehalf':
        case 'serviceaccount':
            $arrAuth['login'] = $conf['login'];
            $arrAuth['password'] = $intra->decrypt($conf['password']);
            break;
        default:
            $arrAuth['login'] = $rwUsr_From['usrID'] ;
            break;
        
    }

    // 2. creating a sender
    $sender  = new \eiseMail(array_merge($conf, $arrAuth));

    do {
        // re-fetching FROM user data for each message, because it can be different for different messages
        $rwUsr_From = $intra->getUserData_All($rwMsg['msgFromUserID'], 'all');

        // 3. dealing with to/cc
    	$rwUsr_To = $intra->getUserData_All($rwMsg['msgToUserID'], 'all');
    	if (!empty($rwMsg['msgCCUserID']))
    	    $rwUsr_CC = $intra->getUserData_All($rwMsg['msgCCUserID'], 'all');

    	// 4. merging metadata into message
    	$rwMsg['system'] = $conf['system'];
    	$metadata = json_decode(isset($rwMsg['msgMetadata']) ? $rwMsg['msgMetadata'] : '', true);
    	if($metadata && is_array($metadata)){
    		$rwMsg = array_merge($rwMsg, $metadata);
    	}


    	// 5. Dealing with Names
    	$msg = [];
    	$msg['From'] = ($rwUsr_From['usrName'] ? "\"".$rwUsr_From['usrName']."\"  <".$rwUsr_From['usrEmail'].">" : $rwUsr_From['usrEmail']);
        $rwMsg['msgFromUserEmail'] = $rwUsr_From['usrEmail'];
    	$msg['To'] = (!empty($rwMsg['msgToUserEmail'])
           	? (!empty($rwMsg['msgToUserName']) ? "\"".$rwMsg['msgToUserName']."\" <".$rwMsg['msgToUserEmail'].">" : $rwMsg['msgToUserEmail'])
           	: (!empty($rwUsr_To['usrName']) ? "\"".$rwUsr_To['usrName']."\" <".$rwUsr_To['usrEmail'].">" : ''));
        $rwMsg['msgToUserEmail'] = (!empty($rwMsg['msgToUserEmail']) 
            ? $rwMsg['msgToUserEmail'] 
            : (!empty($rwUsr_To['usrEmail']) ? $rwUsr_To['usrEmail'] : '')
            );

		if(isset($conf['Content-Type']) && $conf['Content-Type'] == 'text/html'){
		    $msg['Text'] = nl2br($rwMsg['msgText']); 
		}else{
			$msg['Text'] = $rwMsg['msgText'];
		}

        if (!empty($rwMsg['msgCCUserID'])){
            $msg['Cc'] = "\"".$rwUsr_CC['usrName']."\" <".$rwUsr_CC['usrEmail'].">";
            $msg['msgCCUserEmail'] = $rwUsr_CC['usrEmail'];
        }

        $msg = array_merge($msg, $rwMsg);

        if($conf['authenticate']=='email'){ 
	        if(!empty($rwMsg['msgPassword']))
	            $sender->conf['password'] = $intra->decrypt($rwMsg['msgPassword']);
    	}

        if(empty($msg['msgFromUserEmail'])){
            $oSQL->q("UPDATE stbl_message_queue SET 
                msgStatus = 'No From email'
                WHERE msgID={$msg['msgID']}");
            continue;
        }
        if(empty($msg['msgToUserEmail'])){
            // echo htmlspecialchars(var_export($msg, true));
            $oSQL->q("UPDATE stbl_message_queue SET 
                msgStatus = 'No To email'
                WHERE msgID={$msg['msgID']}");
            $sqlMove = "INSERT INTO stbl_message SELECT * FROM stbl_message_queue WHERE stbl_message_queue.msgID={$msg['msgID']}";
            $oSQL->q($sqlMove);
            $oSQL->q("DELETE FROM stbl_message_queue WHERE msgID={$msg['msgID']}");
            continue;
        }

    	// 6. Add message to send queue
        $sender->addMessage($msg);    

    } while ($rwMsg = $oSQL->f($rsMsg));

    // 7. Trying to send
    try {

        // 7.1 if no password - we throw an exceptyon
        if(!empty($conf['authenticate']) && (empty($sender->conf['password']) && empty($sender->conf['xoauth2_token']))) {
            throw new \Exception('NO PASSWORD');
        }

        // 7.2 do the SEND
        $sender->send();
        
    } catch (\Exception $e){

        $err = "Send failure: ".$e->getMessage();
        foreach($sender->arrMessages as $ix => $msg){
            if(empty($msg['send_time']))
                $sender->arrMessages[$ix]['error'] = $err;
        }

    }

    // 8. Updating message queue
    foreach($sender->arrMessages as $ix => $msg){
        if(empty($msg['error']) && !empty($msg['send_time'])){
            // successfully sent
            $sqlSent = "UPDATE stbl_message_queue SET 
                msgSendDate = NOW()
                , msgStatus = 'Sent'
                WHERE msgID={$msg['msgID']}";
            $oSQL->q($sqlSent);
            // moving to stbl_message
            $sqlMove = "INSERT INTO stbl_message SELECT * FROM stbl_message_queue WHERE stbl_message_queue.msgID={$msg['msgID']}";
            $oSQL->q($sqlMove);
            // deleting from queue
            $oSQL->q("DELETE FROM stbl_message_queue WHERE msgID={$msg['msgID']}");
        } else {
            $sqlUpdate = "UPDATE stbl_message_queue SET 
                msgStatus = ".$oSQL->e(!empty($msg['error']) ? $msg['error'] : 'Unknown send error')."
                WHERE msgID={$msg['msgID']}";
            $oSQL->q($sqlUpdate);
        }
    }

    $oSQL->q("COMMIT");

    if($conf['verbose']){
        $oSQL->showProfileInfo();
    }

}

/**
 * @ignore
 */
public static function checkMessageQueueExists($oSQL=null){

    GLOBAL $intra;
    if($oSQL===null)
        $oSQL = $intra->oSQL;

    $rs = $oSQL->q("SHOW TABLES LIKE 'stbl_message_queue'");
    if($oSQL->n($rs)==0){

        $rwCreate = $oSQL->f("SHOW CREATE TABLE stbl_message");
        $sqlCreate = $rwCreate['Create Table'];

        $sqlCreate = preg_replace('/stbl_message/', 'stbl_message_queue', $sqlCreate);
        $sqlCreate = preg_replace('/,[\s]+KEY/', "\n -- , KEY", $sqlCreate);

        try {
            $oSQL->q("DROP TABLE IF EXISTS stbl_message_queue");
            $oSQL->q($sqlCreate);
        } catch (\Exception $e) {

            $dbname = $oSQL->d('SELECT DATABASE()');
            $error = $e->getMessage();
            throw new \Exception("Unable to create message queue. Please check CREATE TABLE permissions of user '{$oSQL->dbuser}' on '{$dbname}'. Error: {$error}");
            
        }
    }

}

public static function cleanupMessageQueue($oSQL=null){

    GLOBAL $intra;
    if($oSQL===null)
        $oSQL = $intra->oSQL;

    $days = MESSAGE_DAYS_CLEANUP;

    if(mt_rand(0, 100)/100 < MESSAGE_P_CLEANUP){
        // it deletes unsent messages older than 1 day to stbl_message with appropriate status and then deletes them from queue. This is a safety measure to prevent queue from being clogged with unsendable messages (e.g. due to wrong email address or SMTP issues).
        $sqlDelete = "DELETE FROM stbl_message_queue WHERE msgInsertDate < DATE_SUB(NOW(), INTERVAL {$days} DAY)
                AND (msgStatus IS NULL OR msgStatus != 'Sent')";
        $oSQL->q($sqlDelete);
    }
    
}

}