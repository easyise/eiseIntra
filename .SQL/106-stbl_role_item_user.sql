DROP TABLE IF EXISTS `stbl_role_item_user`;
CREATE TABLE `stbl_role_item_user` (
  `riuID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `riuRoleID` varchar(10) NOT NULL COMMENT 'Role',
  `riuEntityID` varchar(20) NOT NULL COMMENT 'Entity',
  `riuEntityItemID` varchar(50) NOT NULL COMMENT 'Item ID',
  `riuUserID` varchar(50) NOT NULL COMMENT 'UserID',
  `riuOriginRoleID` varchar(50) DEFAULT NULL COMMENT 'Origin Role',
  `riuInsertBy` varchar(50) DEFAULT NULL,
  `riuInsertDate` datetime DEFAULT NULL,
  PRIMARY KEY (`riuID`),
  KEY `IX_riuEntityItemID` (`riuEntityItemID`),
  KEY `IX_riuUserID` (`riuUserID`),
  KEY `IX_riuOriginRoleID` (`riuOriginRoleID`)
) ENGINE=InnoDB AUTO_INCREMENT=100000 DEFAULT CHARSET=utf8 COMMENT='Virtual roles assignment';