SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `stbl_bookmark`;
CREATE TABLE `stbl_bookmark` (
  `bkmID` int(10) NOT NULL AUTO_INCREMENT,
  `bkmUserID` varchar(50) NOT NULL,
  `bkmEntityID` varchar(20) NOT NULL,
  `bkmEntityItemID` varchar(50) NOT NULL,
  `bkmFlagDeleted` tinyint(4) NOT NULL DEFAULT '0',
  `bkmInsertBy` varchar(50) DEFAULT NULL,
  `bkmInsertDate` datetime DEFAULT NULL,
  PRIMARY KEY (`bkmID`),
  UNIQUE KEY `bkmUserID_bkmEntityID_bkmEntityItemID` (`bkmUserID`,`bkmEntityID`,`bkmEntityItemID`),
  KEY `IX_bkmUserID` (`bkmUserID`),
  KEY `IX_bkmEntityID` (`bkmEntityID`),
  KEY `IX_bkmEntityItemID` (`bkmEntityItemID`),
  KEY `IX_bkmInsertBy` (`bkmInsertBy`),
  KEY `IX_bkmInsertDate` (`bkmInsertDate`)
) ENGINE=InnoDB AUTO_INCREMENT=1000 DEFAULT CHARSET=utf8;