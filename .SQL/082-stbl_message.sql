DROP TABLE IF EXISTS stbl_message;
CREATE TABLE `stbl_message` (
  `msgID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `msgEntityID` varchar(50) DEFAULT NULL,
  `msgEntityItemID` varchar(50) DEFAULT NULL,
  `msgFromUserID` varchar(50) DEFAULT NULL,
  `msgToUserID` varchar(50) DEFAULT NULL,
  `msgCCUserID` varchar(50) DEFAULT NULL,
  `msgSubject` varchar(255) NOT NULL DEFAULT '',
  `msgText` text,
  `msgStatus` varchar(50) DEFAULT NULL,
  `msgSendDate` datetime DEFAULT NULL,
  `msgReadDate` datetime DEFAULT NULL,
  `msgFlagDeleted` tinyint(4) NOT NULL DEFAULT '0',
  `msgInsertBy` varchar(50) DEFAULT NULL,
  `msgInsertDate` datetime DEFAULT NULL,
  `msgEditBy` varchar(50) DEFAULT NULL,
  `msgEditDate` datetime DEFAULT NULL,
  PRIMARY KEY (`msgID`)
) ENGINE=InnoDB AUTO_INCREMENT=100100 DEFAULT CHARSET=utf8;