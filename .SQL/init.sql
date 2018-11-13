
SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `stbl_action`;
CREATE TABLE `stbl_action` (
  `actID` int(11) NOT NULL AUTO_INCREMENT,
  `actEntityID` varchar(20) DEFAULT NULL,
  `actTrackPrecision` enum('date','datetime') NOT NULL DEFAULT 'datetime',
  `actTitle` varchar(255) NOT NULL DEFAULT '',
  `actTitleLocal` varchar(255) NOT NULL DEFAULT '',
  `actTitlePast` varchar(255) DEFAULT NULL,
  `actTitlePastLocal` varchar(255) DEFAULT NULL,
  `actButtonClass` varchar(255) DEFAULT NULL COMMENT 'Button CSS class, eg "ss_wrench" or "fa-file-o"',
  `actDescription` text NOT NULL,
  `actDescriptionLocal` text NOT NULL,
  `actFlagDeleted` int(11) NOT NULL DEFAULT '0',
  `actPriority` int(11) NOT NULL DEFAULT '0',
  `actFlagComment` int(11) NOT NULL DEFAULT '0',
  `actShowConditions` varchar(255) NOT NULL DEFAULT '',
  `actFlagHasEstimates` tinyint(4) NOT NULL DEFAULT '0',
  `actFlagDepartureEqArrival` tinyint(4) NOT NULL DEFAULT '0',
  `actFlagAutocomplete` tinyint(4) NOT NULL DEFAULT '1',
  `actDepartureDescr` varchar(255) DEFAULT NULL,
  `actArrivalDescr` varchar(255) DEFAULT NULL,
  `actFlagInterruptStatusStay` tinyint(4) NOT NULL DEFAULT '0',
  `actInsertBy` varchar(255) DEFAULT NULL,
  `actInsertDate` datetime DEFAULT NULL,
  `actEditBy` varchar(20) DEFAULT NULL,
  `actEditDate` datetime DEFAULT NULL,
  PRIMARY KEY (`actID`),
  KEY `IX_actEntityID` (`actEntityID`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8 COMMENT='Defines actions for entities';
INSERT INTO `stbl_action` VALUES
(1, NULL, 'datetime', 'Create', 'Создать', 'Created', 'Создан', NULL, 0x637265617465206e6577, 0x637265617465206e6577, 0, 0, 0, '', 0, 0, 1, NULL, NULL, 0, 'admin', '2018-11-13 22:10:31', 'admin', '2018-11-13 22:10:31'),
(2, NULL, 'datetime', 'Save', 'Сохранить данные', 'Saved', 'Данные сохранены', NULL, 0x757064617465206578697374696e67, 0x757064617465206578697374696e67, 0, 0, 0, '', 0, 0, 1, NULL, NULL, 0, 'admin', '2018-11-13 22:10:31', 'admin', '2018-11-13 22:10:31'),
(3, NULL, 'datetime', 'Delete', 'Удалить', 'Deleted', 'Удалено', NULL, 0x64656c657465206578697374696e67, 0x64656c657465206578697374696e67, 0, -1, 0, '', 0, 0, 1, NULL, NULL, 0, 'admin', '2018-11-13 22:10:31', 'admin', '2018-11-13 22:10:31'),
(4, NULL, 'datetime', 'Superaction', 'Superaction', 'Superaction', 'Superaction', NULL, '', '', 0, 0, 1, '', 0, 0, 0, '', '', 0, 'admin', '2018-11-13 22:10:31', 'admin', '2018-11-13 22:10:31');
SET FOREIGN_KEY_CHECKS=1;

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `stbl_action_attribute`;
CREATE TABLE `stbl_action_attribute` (
  `aatID` int(11) NOT NULL AUTO_INCREMENT,
  `aatActionID` int(11) NOT NULL,
  `aatAttributeID` varchar(50) NOT NULL,
  `aatFlagToTrack` tinyint(4) NOT NULL DEFAULT '0',
  `aatFlagMandatory` tinyint(4) NOT NULL DEFAULT '0',
  `aatFlagToChange` tinyint(4) NOT NULL DEFAULT '0',
  `aatFlagToAdd` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'Defines should this attribute be added or updated if it already exists on given action',
  `aatFlagToPush` tinyint(4) NOT NULL DEFAULT '0',
  `aatFlagEmptyOnInsert` tinyint(4) NOT NULL DEFAULT '0',
  `aatFlagUserStamp` tinyint(4) NOT NULL DEFAULT '0',
  `aatFlagTimestamp` varchar(50) NOT NULL DEFAULT '',
  `aatInsertBy` varchar(255) NOT NULL DEFAULT '',
  `aatInsertDate` datetime DEFAULT NULL,
  `aatEditBy` varchar(255) NOT NULL DEFAULT '',
  `aatEditDate` datetime DEFAULT NULL,
  `aatTemp` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`aatID`),
  KEY `IX_aatActionID` (`aatActionID`),
  KEY `IX_aatAttributeID` (`aatAttributeID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Defines the attributes to be set when the action is executed';
SET FOREIGN_KEY_CHECKS=1;

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `stbl_action_log`;
CREATE TABLE `stbl_action_log` (
  `aclGUID` char(36) NOT NULL DEFAULT '',
  `aclActionID` int(11) NOT NULL,
  `aclEntityItemID` varchar(36) NOT NULL,
  `aclOldStatusID` int(11) DEFAULT NULL,
  `aclNewStatusID` int(11) DEFAULT NULL,
  `aclPredID` char(36) DEFAULT NULL,
  `aclActionPhase` tinyint(4) NOT NULL DEFAULT '0',
  `aclPrecision` enum('date','datetime') NOT NULL DEFAULT 'datetime',
  `aclETD` datetime DEFAULT NULL,
  `aclETA` datetime DEFAULT NULL,
  `aclATD` datetime DEFAULT NULL,
  `aclATA` datetime DEFAULT NULL,
  `aclItemBefore` text COMMENT 'JSON with item before action execute',
  `aclItemAfter` text COMMENT 'JSON with item after action execute',
  `aclItemTraced` text COMMENT 'JSON with traced attributes',
  `aclItemDiff` text COMMENT 'JSON with diff',
  `aclComments` text,
  `aclStartBy` varchar(255) DEFAULT NULL,
  `aclStartDate` datetime DEFAULT NULL,
  `aclFinishBy` varchar(255) DEFAULT NULL,
  `aclFinishDate` datetime DEFAULT NULL,
  `aclCancelBy` varchar(255) DEFAULT NULL,
  `aclCancelDate` datetime DEFAULT NULL,
  `aclInsertBy` varchar(255) NOT NULL DEFAULT '',
  `aclInsertDate` datetime NOT NULL,
  `aclEditBy` varchar(255) NOT NULL DEFAULT '',
  `aclEditDate` datetime DEFAULT NULL,
  PRIMARY KEY (`aclGUID`),
  KEY `IX_aclActionID` (`aclActionID`),
  KEY `aclEntityItemID` (`aclEntityItemID`),
  KEY `IX_oldStatusID` (`aclOldStatusID`),
  KEY `IX_newStatusID` (`aclNewStatusID`),
  KEY `IX_aclATA` (`aclATA`),
  KEY `IX_aclATD` (`aclATD`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Stores action history on entity items';
SET FOREIGN_KEY_CHECKS=1;

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `stbl_action_status`;
CREATE TABLE `stbl_action_status` (
  `atsID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `atsActionID` int(10) DEFAULT NULL,
  `atsOldStatusID` int(10) DEFAULT NULL,
  `atsNewStatusID` int(10) DEFAULT NULL,
  `atsInsertBy` varchar(50) DEFAULT NULL,
  `atsInsertDate` datetime DEFAULT NULL,
  `atsEditBy` varchar(50) DEFAULT NULL,
  `atsEditDate` datetime DEFAULT NULL,
  PRIMARY KEY (`atsID`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;
INSERT INTO `stbl_action_status` VALUES
(1, 1, NULL, 0, NULL, NULL, NULL, NULL),
(2, 2, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 3, 0, NULL, NULL, NULL, NULL, NULL);
SET FOREIGN_KEY_CHECKS=1;

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `stbl_attribute`;
CREATE TABLE `stbl_attribute` (
  `atrID` varchar(255) NOT NULL COMMENT 'Attribute ID (equal to field name in the Master table)',
  `atrEntityID` varchar(20) NOT NULL DEFAULT '' COMMENT 'Entity ID',
  `atrTitle` varchar(255) NOT NULL DEFAULT '',
  `atrTitleLocal` varchar(255) NOT NULL DEFAULT '',
  `atrShortTitle` varchar(255) NOT NULL DEFAULT '',
  `atrShortTitleLocal` varchar(255) NOT NULL DEFAULT '',
  `atrFlagNoField` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'True when there''s no field for this attribute in Master table',
  `atrType` varchar(20) DEFAULT NULL COMMENT 'Type (see Intra types)',
  `atrUOMTypeID` varchar(10) DEFAULT NULL COMMENT 'UOM type (FK to stbl_uom)',
  `atrOrder` int(11) DEFAULT '10' COMMENT 'Defines order how this attribute appears on screen',
  `atrClasses` varchar(255) NOT NULL DEFAULT '' COMMENT 'CSS classes',
  `atrDefault` varchar(255) NOT NULL DEFAULT '' COMMENT 'Default Value',
  `atrTextIfNull` varchar(255) NOT NULL DEFAULT '' COMMENT 'Text to dispay wgen value not set',
  `atrProgrammerReserved` text COMMENT 'Reserved for programmer',
  `atrCheckMask` varchar(255) NOT NULL DEFAULT '' COMMENT 'Regular expression for data validation',
  `atrDataSource` varchar(255) NOT NULL DEFAULT '' COMMENT 'Data source (table, view or array)',
  `atrFlagHideOnLists` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'True when this attribute not shown on lists',
  `atrFlagDeleted` tinyint(11) NOT NULL DEFAULT '0' COMMENT 'True when attribute no longer used',
  `atrInsertBy` varchar(50) DEFAULT NULL,
  `atrInsertDate` datetime DEFAULT NULL,
  `atrEditBy` varchar(50) DEFAULT NULL,
  `atrEditDate` datetime DEFAULT NULL,
  PRIMARY KEY (`atrID`,`atrEntityID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Defines entity attributes';
SET FOREIGN_KEY_CHECKS=1;

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
SET FOREIGN_KEY_CHECKS=1;

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `stbl_comments`;
CREATE TABLE `stbl_comments` (
  `scmGUID` varchar(50) NOT NULL,
  `scmEntityItemID` varchar(50) DEFAULT NULL,
  `scmAttachmentID` varchar(50) DEFAULT NULL,
  `scmActionLogID` varchar(50) DEFAULT NULL,
  `scmContent` text,
  `scmInsertBy` varchar(50) DEFAULT NULL,
  `scmInsertDate` datetime DEFAULT NULL,
  `scmEditBy` varchar(50) DEFAULT NULL,
  `scmEditDate` datetime DEFAULT NULL,
  PRIMARY KEY (`scmGUID`),
  KEY `IX_scmEntityItemID` (`scmEntityItemID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SET FOREIGN_KEY_CHECKS=1;

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `stbl_entity`;
CREATE TABLE `stbl_entity` (
  `entID` varchar(20) NOT NULL,
  `entTitle` varchar(255) NOT NULL DEFAULT '',
  `entTitleMul` varchar(255) NOT NULL DEFAULT '' COMMENT 'Prural',
  `entTitleLocal` varchar(255) DEFAULT NULL,
  `entTitleLocalMul` varchar(255) DEFAULT NULL COMMENT 'Prural in local',
  `entTitleLocalGen` varchar(255) DEFAULT NULL COMMENT 'Genitive (Roditelny) in local',
  `entTitleLocalDat` varchar(255) DEFAULT NULL COMMENT 'Dative (Datelny) in local',
  `entTitleLocalAcc` varchar(255) DEFAULT NULL COMMENT 'Accusative (Vinitelny) in local',
  `entTitleLocalIns` varchar(255) DEFAULT NULL COMMENT 'Instrumental case (Tvoritelny) in local',
  `entTitleLocalAbl` varchar(255) DEFAULT NULL COMMENT 'Prepositional case (Predlozhny) in local',
  `entTable` varchar(20) DEFAULT NULL,
  `entPrefix` varchar(3) DEFAULT NULL,
  PRIMARY KEY (`entID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Defines entities';
SET FOREIGN_KEY_CHECKS=1;

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `stbl_file`;
CREATE TABLE `stbl_file` (
  `filGUID` varchar(36) NOT NULL,
  `filEntityID` varchar(50) DEFAULT NULL,
  `filEntityItemID` varchar(255) DEFAULT NULL,
  `filName` varchar(255) NOT NULL DEFAULT '',
  `filNamePhysical` varchar(255) NOT NULL DEFAULT '',
  `filLength` int(10) unsigned NOT NULL DEFAULT '0',
  `filContentType` varchar(255) NOT NULL DEFAULT '',
  `filInsertBy` varchar(255) DEFAULT NULL,
  `filInsertDate` datetime DEFAULT NULL,
  `filEditBy` varchar(255) DEFAULT NULL,
  `filEditDate` datetime DEFAULT NULL,
  PRIMARY KEY (`filGUID`),
  KEY `filEntityID` (`filEntityItemID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SET FOREIGN_KEY_CHECKS=1;

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `stbl_framework_version`;
CREATE TABLE `stbl_framework_version` (
  `fvrNumber` int(10) unsigned NOT NULL,
  `fvrDesc` text,
  `fvrDate` datetime DEFAULT NULL,
  PRIMARY KEY (`fvrNumber`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Version history for the framework';
INSERT INTO `stbl_framework_version` VALUES
(91, 0x56657273696f6e203931, '2018-11-13 22:10:30'),
(92, 0x414c544552205441424c4520607374626c5f6d657373616765600a094348414e474520434f4c554d4e206d7367537461747573206d736753746174757320564152434841522832353529204e554c4c2044454641554c54204e554c4c20434f4d4d454e542027537461747573272c0a0941444420494e444558206049585f6d7367537461747573602028606d736753746174757360293b, '2018-11-13 22:16:14'),
(93, 0x414c544552205441424c4520607374626c5f6d657373616765600a0941444420434f4c554d4e206d736750617373776f726420766172636861722832353529204e554c4c2044454641554c54204e554c4c20434f4d4d454e542027456e6372797074656420534d54502050617373776f7264273b, '2018-11-13 22:16:14'),
(94, 0x414c544552205441424c4520607374626c5f66696c65600a0944524f5020494e444558206066696c456e746974794944602c0a0941444420494e444558206066696c456e7469747949446020286066696c456e746974794974656d494460293b, '2018-11-13 22:16:14'),
(95, 0x414c544552205441424c45207374626c5f73657475702041444420434f4c554d4e20737470466c61674f6e44656d616e642074696e79696e74283429204e4f54204e554c4c2044454641554c542027302720434f4d4d454e54202754686573652073657474696e67732077696c6c206e6f74206265206c6f616465642720414654455220737470466c6167526561644f6e6c793b0a0a414c544552205441424c45207374626c5f73657475702041444420434f4c554d4e20737470466c616748696464656e4f6e466f726d2074696e79696e74283429204e4f54204e554c4c2044454641554c542027302720434f4d4d454e54202754686573652073657474696e67732077696c6c2062652068696464656e206f6e2074686520666f726d2720414654455220737470466c61674f6e44656d616e643b, '2018-11-13 22:16:14'),
(96, 0x414c544552205441424c45207374626c5f726f6c652041444420434f4c554d4e20726f6c466c61675669727475616c2074696e79696e74283429204e4f54204e554c4c2044454641554c542027302720414654455220726f6c466c616744656661756c743b, '2018-11-13 22:16:14'),
(97, 0x414c544552205441424c45207374626c5f616374696f6e5f6c6f672041444420434f4c554d4e2061636c4974656d4265666f72652074657874204e554c4c20434f4d4d454e5420274a534f4e2077697468206974656d206265666f726520616374696f6e2065786563757465272041465445522061636c4154410a2c2041444420434f4c554d4e2061636c4974656d41667465722074657874204e554c4c20434f4d4d454e5420274a534f4e2077697468206974656d20616674657220616374696f6e2065786563757465272041465445522061636c4974656d4265666f72650a2c2041444420434f4c554d4e2061636c4974656d5472616365642074657874204e554c4c20434f4d4d454e5420274a534f4e2077697468207472616365642061747472696275746573272041465445522061636c4974656d41667465720a2c2041444420434f4c554d4e2061636c4974656d446966662074657874204e554c4c20434f4d4d454e5420274a534f4e20776974682064696666272041465445522061636c4974656d5472616365640a3b, '2018-11-13 22:16:14'),
(98, 0x414c544552205441424c45207374626c5f616374696f6e2041444420434f4c554d4e20616374427574746f6e436c61737320766172636861722832353529204e554c4c2020434f4d4d454e542027427574746f6e2043535320636c6173732c206567202273735f7772656e636822206f72202266612d66696c652d6f2227204146544552206163745469746c65506173744c6f63616c3b, '2018-11-13 22:16:14');
SET FOREIGN_KEY_CHECKS=1;

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `stbl_message`;
CREATE TABLE `stbl_message` (
  `msgID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `msgEntityID` varchar(50) DEFAULT NULL,
  `msgEntityItemID` varchar(50) DEFAULT NULL,
  `msgFromUserID` varchar(50) DEFAULT NULL,
  `msgToUserID` varchar(50) DEFAULT NULL,
  `msgCCUserID` varchar(50) DEFAULT NULL,
  `msgSubject` varchar(255) NOT NULL DEFAULT '',
  `msgText` text,
  `msgStatus` varchar(255) DEFAULT NULL COMMENT 'Status',
  `msgSendDate` datetime DEFAULT NULL,
  `msgReadDate` datetime DEFAULT NULL,
  `msgFlagDeleted` tinyint(4) NOT NULL DEFAULT '0',
  `msgInsertBy` varchar(50) DEFAULT NULL,
  `msgInsertDate` datetime DEFAULT NULL,
  `msgEditBy` varchar(50) DEFAULT NULL,
  `msgEditDate` datetime DEFAULT NULL,
  `msgPassword` varchar(255) DEFAULT NULL COMMENT 'Encrypted SMTP Password',
  PRIMARY KEY (`msgID`),
  KEY `IX_msgStatus` (`msgStatus`)
) ENGINE=InnoDB AUTO_INCREMENT=100100 DEFAULT CHARSET=utf8;
SET FOREIGN_KEY_CHECKS=1;

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `stbl_page`;
CREATE TABLE `stbl_page` (
  `pagID` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `pagParentID` int(11) unsigned DEFAULT NULL,
  `pagTitle` varchar(255) DEFAULT NULL,
  `pagTitleLocal` varchar(255) DEFAULT NULL,
  `pagIdxLeft` int(11) unsigned DEFAULT NULL,
  `pagIdxRight` int(11) unsigned DEFAULT NULL,
  `pagFlagShowInMenu` tinyint(4) unsigned DEFAULT NULL,
  `pagFile` varchar(255) DEFAULT NULL,
  `pagTable` varchar(20) DEFAULT NULL,
  `pagEntityID` varchar(3) DEFAULT NULL,
  `pagFlagShowMyItems` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'Displays My Items menu item when checked and entity set',
  `pagFlagSystem` tinyint(4) NOT NULL DEFAULT '0',
  `pagFlagHierarchy` tinyint(4) NOT NULL DEFAULT '0',
  `pagMenuItemClass` varchar(250) NOT NULL DEFAULT '' COMMENT 'Menu item icon class, eg "fa fa-database"',
  `pagInsertBy` varchar(30) DEFAULT NULL,
  `pagInsertDate` datetime DEFAULT NULL,
  `pagEditBy` varchar(30) DEFAULT NULL,
  `pagEditDate` datetime DEFAULT NULL,
  PRIMARY KEY (`pagID`),
  KEY `pagIdxLeft` (`pagIdxLeft`),
  KEY `pagIdxRight` (`pagIdxRight`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8 COMMENT='The table defines the list and the structure of all scripts';
INSERT INTO `stbl_page` VALUES
(1, NULL, 'index.php', 'index.php', 1, 28, 0, '/index.php', NULL, NULL, 0, 0, 0, '', NULL, NULL, NULL, NULL),
(4, 1, 'About', 'О системе', 24, 25, 1, '/about.php', '', '', 0, 0, 0, '', NULL, NULL, NULL, NULL),
(5, 1, 'Settings', 'Настройки', 2, 15, 1, '', '', '', 0, 0, 0, '', NULL, NULL, NULL, NULL),
(6, 5, 'Security', 'Безопасность', 3, 12, 1, '', '', '', 0, 0, 0, '', NULL, NULL, NULL, NULL),
(7, 6, 'Users', 'Пользователи', 4, 7, 1, '/users_list.php', '', '', 0, 0, 0, '', NULL, NULL, NULL, NULL),
(8, 6, 'Access Control', 'Доступ', 8, 9, 1, '/role_form.php', '', '', 0, 0, 0, '', NULL, NULL, NULL, NULL),
(17, 1, 'ajax_details.php', 'ajax_details.php', 16, 17, 0, '/ajax_details.php', '', '', 0, 0, 0, '', NULL, NULL, NULL, NULL),
(22, 6, 'Change Password', 'Смена пароля', 10, 11, 1, '/password_form.php', '', '', 0, 0, 0, '', NULL, NULL, NULL, NULL),
(23, 5, 'System setup', 'Системные настройки', 13, 14, 1, '/setup_form.php', '', '', 0, 0, 0, '', NULL, NULL, NULL, NULL),
(26, 7, 'User form', 'Пользователь', 5, 6, 0, '/user_form.php', '', '', 0, 0, 0, '', NULL, NULL, NULL, NULL),
(29, 1, 'ajax_dropdownlist.php', 'ajax_dropdownlist.php', 18, 19, 0, '/ajax_dropdownlist.php', '', '', 0, 0, 0, '', NULL, NULL, NULL, NULL),
(30, 1, 'entityitem_form.php', 'entityitem_form.php', 20, 21, 0, '/entityitem_form.php', '', '', 0, 0, 0, '', NULL, NULL, NULL, NULL),
(31, 1, '/popup_file.php', '/popup_file.php', 26, 27, 0, '/popup_file.php', '', '', 0, 0, 0, '', NULL, NULL, NULL, NULL);
SET FOREIGN_KEY_CHECKS=1;

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `stbl_page_role`;
CREATE TABLE `stbl_page_role` (
  `pgrID` int(11) NOT NULL AUTO_INCREMENT,
  `pgrPageID` int(11) unsigned DEFAULT NULL,
  `pgrRoleID` varchar(10) NOT NULL DEFAULT '',
  `pgrFlagRead` tinyint(4) DEFAULT NULL,
  `pgrFlagCreate` tinyint(4) DEFAULT NULL,
  `pgrFlagUpdate` tinyint(4) DEFAULT NULL,
  `pgrFlagDelete` tinyint(4) DEFAULT NULL,
  `pgrFlagWrite` tinyint(4) DEFAULT NULL,
  `pgrInsertBy` varchar(30) DEFAULT NULL,
  `pgrInsertDate` datetime DEFAULT NULL,
  `pgrEditBy` varchar(30) DEFAULT NULL,
  `pgrEditDate` datetime DEFAULT NULL,
  PRIMARY KEY (`pgrID`),
  KEY `IX_pgrPageID` (`pgrPageID`),
  KEY `IX_pgrRoleID` (`pgrRoleID`),
  CONSTRAINT `FK_Page` FOREIGN KEY (`pgrPageID`) REFERENCES `stbl_page` (`pagID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK_Role` FOREIGN KEY (`pgrRoleID`) REFERENCES `stbl_role` (`rolID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=111 DEFAULT CHARSET=utf8 COMMENT='Authorization table, assigns script rights to users';
INSERT INTO `stbl_page_role` VALUES
(78, 1, 'Everyone', 1, 0, 0, 0, 0, '', NULL, '', NULL),
(80, 4, 'Everyone', 1, 0, 0, 0, 0, '', NULL, '', NULL),
(81, 5, 'Everyone', 1, 0, 0, 0, 0, '', NULL, '', NULL),
(82, 6, 'Everyone', 1, 0, 0, 0, 0, '', NULL, '', NULL),
(83, 7, 'Everyone', 0, 0, 0, 0, 0, '', NULL, '', NULL),
(84, 8, 'Everyone', 0, 0, 0, 0, 0, '', NULL, '', NULL),
(85, 17, 'Everyone', 1, 0, 0, 0, 0, '', NULL, '', NULL),
(87, 22, 'Everyone', 1, 0, 0, 0, 1, '', NULL, '', NULL),
(88, 23, 'Everyone', 1, 0, 0, 0, 0, '', NULL, '', NULL),
(89, 26, 'Everyone', 0, 0, 0, 0, 0, '', NULL, '', NULL),
(90, 1, 'admin', 1, 0, 0, 0, 0, '', NULL, '', NULL),
(92, 4, 'admin', 1, 0, 0, 0, 0, '', NULL, '', NULL),
(93, 5, 'admin', 1, 0, 0, 0, 0, '', NULL, '', NULL),
(94, 6, 'admin', 1, 0, 0, 0, 0, '', NULL, '', NULL),
(95, 7, 'admin', 1, 0, 0, 0, 1, '', NULL, '', NULL),
(96, 8, 'admin', 1, 0, 0, 0, 1, '', NULL, '', NULL),
(97, 17, 'admin', 1, 0, 0, 0, 0, '', NULL, '', NULL),
(99, 22, 'admin', 1, 0, 0, 0, 0, '', NULL, '', NULL),
(100, 23, 'admin', 1, 0, 0, 0, 1, '', NULL, '', NULL),
(101, 26, 'admin', 1, 0, 0, 0, 1, '', NULL, '', NULL),
(104, 29, 'admin', 1, 0, 0, 0, 0, '', NULL, '', NULL),
(105, 29, 'Everyone', 1, 0, 0, 0, 0, '', NULL, '', NULL),
(107, 30, 'admin', 1, 0, 0, 0, 1, '', NULL, '', NULL),
(108, 30, 'Everyone', 1, 0, 0, 0, 0, '', NULL, '', NULL),
(109, 31, 'admin', 1, 0, 0, 0, 0, '', NULL, '', NULL),
(110, 31, 'Everyone', 1, 0, 0, 0, 0, '', NULL, '', NULL);
SET FOREIGN_KEY_CHECKS=1;

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `stbl_role`;
CREATE TABLE `stbl_role` (
  `rolID` varchar(10) NOT NULL DEFAULT '',
  `rolTitle` varchar(255) DEFAULT NULL,
  `rolTitleLocal` varchar(255) DEFAULT NULL,
  `rolFlagDefault` tinyint(4) DEFAULT '0',
  `rolFlagVirtual` tinyint(4) NOT NULL DEFAULT '0',
  `rolFlagDeleted` tinyint(4) DEFAULT '0',
  `rolInsertBy` varchar(30) DEFAULT NULL,
  `rolInsertDate` datetime DEFAULT NULL,
  `rolEditBy` varchar(30) DEFAULT NULL,
  `rolEditDate` datetime DEFAULT NULL,
  PRIMARY KEY (`rolID`),
  UNIQUE KEY `rolTitle` (`rolTitle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='This table defines roles in the application';
INSERT INTO `stbl_role` VALUES
('admin', 'Administrators', 'Администратор', 0, 0, 0, 'admin', '2018-11-13 22:10:31', 'admin', '2018-11-13 22:10:31'),
('Everyone', 'Everyone', 'Пользователи', 1, 0, 0, 'admin', '2018-11-13 22:10:31', 'admin', '2018-11-13 22:10:31');
SET FOREIGN_KEY_CHECKS=1;

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `stbl_role_action`;
CREATE TABLE `stbl_role_action` (
  `rlaID` int(11) NOT NULL AUTO_INCREMENT,
  `rlaRoleID` varchar(10) NOT NULL,
  `rlaActionID` int(11) NOT NULL,
  PRIMARY KEY (`rlaID`),
  KEY `IX_rlaRoleID` (`rlaRoleID`),
  KEY `IX_rlaActionID` (`rlaActionID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Allow or disallow action for entity';
SET FOREIGN_KEY_CHECKS=1;

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `stbl_role_user`;
CREATE TABLE `stbl_role_user` (
  `rluID` int(11) NOT NULL AUTO_INCREMENT,
  `rluUserID` varchar(255) DEFAULT NULL,
  `rluRoleID` varchar(10) NOT NULL DEFAULT '',
  `rluInsertBy` varchar(30) DEFAULT NULL,
  `rluInsertDate` datetime DEFAULT NULL,
  `rluEditBy` varchar(30) DEFAULT NULL,
  `rluEditDate` datetime DEFAULT NULL,
  PRIMARY KEY (`rluID`),
  UNIQUE KEY `rluRoleUser` (`rluRoleID`,`rluUserID`),
  KEY `FK_rluUserID` (`rluUserID`),
  CONSTRAINT `FK_rluRoleID` FOREIGN KEY (`rluRoleID`) REFERENCES `stbl_role` (`rolID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK_rluUserID` FOREIGN KEY (`rluUserID`) REFERENCES `stbl_user` (`usrID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COMMENT='Assigns users to respective roles in the application';
INSERT INTO `stbl_role_user` VALUES
(1, 'admin', 'admin', 'admin', '2018-11-13 22:10:31', 'admin', '2018-11-13 22:10:31');
SET FOREIGN_KEY_CHECKS=1;

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `stbl_setup`;
CREATE TABLE `stbl_setup` (
  `stpID` int(11) NOT NULL AUTO_INCREMENT,
  `stpVarName` varchar(255) DEFAULT NULL,
  `stpCharType` varchar(20) DEFAULT NULL,
  `stpDataSource` varchar(50) DEFAULT NULL,
  `stpCharValue` varchar(1024) DEFAULT NULL,
  `stpCharValueLocal` varchar(1024) DEFAULT NULL,
  `stpFlagReadOnly` tinyint(4) DEFAULT NULL,
  `stpFlagOnDemand` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'These settings will not be loaded',
  `stpFlagHiddenOnForm` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'These settings will be hidden on the form',
  `stpNGroup` int(11) DEFAULT NULL,
  `stpTitle` varchar(256) DEFAULT NULL,
  `stpTitleLocal` varchar(256) DEFAULT NULL,
  `stpInsertBy` varchar(50) DEFAULT NULL,
  `stpInsertDate` datetime DEFAULT NULL,
  `stpEditBy` varchar(50) DEFAULT NULL,
  `stpEditDate` datetime DEFAULT NULL,
  PRIMARY KEY (`stpID`),
  KEY `IX_stpVarName` (`stpVarName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='System settings';
SET FOREIGN_KEY_CHECKS=1;

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `stbl_status`;
CREATE TABLE `stbl_status` (
  `staID` int(11) NOT NULL,
  `staEntityID` varchar(20) NOT NULL DEFAULT '',
  `staTrackPrecision` enum('date','datetime') NOT NULL DEFAULT 'datetime',
  `staTitle` varchar(255) NOT NULL DEFAULT '',
  `staTitleMul` varchar(255) NOT NULL DEFAULT '',
  `staTitleLocal` varchar(255) NOT NULL DEFAULT '',
  `staTitleLocalMul` varchar(255) NOT NULL DEFAULT '',
  `staFlagCanUpdate` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'Defines can the entity be updated in current status',
  `staFlagCanDelete` tinyint(4) NOT NULL DEFAULT '0',
  `staMenuItemClass` varchar(250) NOT NULL DEFAULT '' COMMENT 'Menu item icon class, eg "fa fa-database"',
  `staFlagDeleted` tinyint(4) NOT NULL DEFAULT '0',
  `staInsertBy` varchar(255) NOT NULL DEFAULT '',
  `staInsertDate` datetime DEFAULT NULL,
  `staEditBy` varchar(255) NOT NULL DEFAULT '',
  `staEditDate` datetime DEFAULT NULL,
  PRIMARY KEY (`staEntityID`,`staID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Defines entity statuses';
SET FOREIGN_KEY_CHECKS=1;

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `stbl_status_attribute`;
CREATE TABLE `stbl_status_attribute` (
  `satID` int(11) NOT NULL AUTO_INCREMENT,
  `satStatusID` varchar(255) NOT NULL,
  `satEntityID` varchar(255) NOT NULL,
  `satAttributeID` varchar(255) NOT NULL,
  `satFlagEditable` tinyint(4) NOT NULL DEFAULT '1',
  `satFlagShowInForm` tinyint(4) NOT NULL DEFAULT '1',
  `satFlagShowInList` tinyint(4) NOT NULL DEFAULT '0',
  `satFlagTrackOnArrival` tinyint(4) NOT NULL DEFAULT '0',
  `satInsertBy` varchar(50) DEFAULT NULL,
  `satInsertDate` datetime DEFAULT NULL,
  `satEditBy` varchar(50) DEFAULT NULL,
  `satEditDate` datetime DEFAULT NULL,
  `satTemp` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`satID`),
  KEY `IX_satStatusID_satEntityID_satAttributeID` (`satStatusID`,`satEntityID`,`satAttributeID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SET FOREIGN_KEY_CHECKS=1;

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `stbl_status_log`;
CREATE TABLE `stbl_status_log` (
  `stlGUID` varchar(36) NOT NULL,
  `stlEntityID` varchar(50) NOT NULL,
  `stlEntityItemID` varchar(255) NOT NULL,
  `stlStatusID` int(11) DEFAULT NULL,
  `stlArrivalActionID` varchar(36) DEFAULT NULL,
  `stlDepartureActionID` varchar(36) DEFAULT NULL,
  `stlTitle` varchar(255) NOT NULL DEFAULT '',
  `stlTitleLocal` varchar(255) NOT NULL DEFAULT '',
  `stlPrecision` enum('date','datetime') NOT NULL DEFAULT 'datetime',
  `stlATA` datetime DEFAULT NULL,
  `stlATD` datetime DEFAULT NULL,
  `stlInsertBy` varchar(50) DEFAULT NULL,
  `stlInsertDate` datetime DEFAULT NULL,
  `stlEditBy` varchar(50) DEFAULT NULL,
  `stlEditDate` datetime DEFAULT NULL,
  PRIMARY KEY (`stlGUID`),
  KEY `IX_stl_1` (`stlEntityID`,`stlStatusID`),
  KEY `IX_stlATA` (`stlATA`),
  KEY `IX_stlATD` (`stlATD`),
  KEY `IX_stl` (`stlEntityItemID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SET FOREIGN_KEY_CHECKS=1;

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `stbl_translation`;
CREATE TABLE `stbl_translation` (
  `strKey` varchar(255) NOT NULL,
  `strValue` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`strKey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SET FOREIGN_KEY_CHECKS=1;

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `stbl_uom`;
CREATE TABLE `stbl_uom` (
  `uomID` varchar(10) NOT NULL,
  `uomType` varchar(255) NOT NULL DEFAULT '',
  `uomTitleLocal` varchar(255) NOT NULL DEFAULT '',
  `uomTitle` varchar(255) NOT NULL DEFAULT '',
  `uomRateToDefault` decimal(12,4) DEFAULT '1.0000',
  `uomOrder` int(11) DEFAULT NULL,
  `uomFlagDefault` tinyint(4) NOT NULL DEFAULT '0',
  `uomFlagDeleted` tinyint(4) NOT NULL DEFAULT '0',
  `uomCode1C` int(11) DEFAULT NULL,
  `uomInsertBy` varchar(50) DEFAULT NULL,
  `uomInsertDate` datetime DEFAULT NULL,
  `uomEditBy` varchar(50) DEFAULT NULL,
  `uomEditDate` datetime DEFAULT NULL,
  PRIMARY KEY (`uomType`,`uomID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
INSERT INTO `stbl_uom` VALUES
('dst', '', 'расстояние', 'distance', NULL, 4, 0, 0, NULL, NULL, NULL, NULL, NULL),
('len', '', 'длина', 'length', NULL, 5, 0, 0, NULL, NULL, NULL, NULL, NULL),
('loc', '', 'местоположение', 'location', NULL, 9, 0, 0, NULL, NULL, NULL, NULL, NULL),
('qty', '', 'количество', 'quantity', NULL, 3, 0, 0, NULL, NULL, NULL, NULL, NULL),
('spd', '', 'скорость', 'speed', NULL, 6, 0, 0, NULL, NULL, NULL, NULL, NULL),
('squ', '', 'площадь', 'square', NULL, 7, 0, 0, NULL, NULL, NULL, NULL, NULL),
('tmp', '', 'время', 'time', NULL, 8, 0, 0, NULL, NULL, NULL, NULL, NULL),
('vol', '', 'объем', 'volume', NULL, 2, 0, 0, NULL, NULL, NULL, NULL, NULL),
('wgt', '', 'вес', 'weight', NULL, 1, 0, 0, NULL, NULL, NULL, NULL, NULL),
('km', 'dst', 'км', 'km', 1.0000, 1, 1, 0, NULL, NULL, NULL, NULL, NULL),
('mi', 'dst', 'миль', 'miles', 1.6093, 2, 0, 0, NULL, NULL, NULL, NULL, NULL),
('NM', 'dst', 'м.миль', 'NM', 1.8520, 3, 0, 0, NULL, NULL, NULL, NULL, NULL),
('cm', 'len', 'cм', 'cm', 0.0100, 3, 0, 0, NULL, NULL, NULL, NULL, NULL),
('ft', 'len', 'футов', 'feet', 0.3048, 2, 0, 0, NULL, NULL, NULL, NULL, NULL),
('in', 'len', 'дюймов', 'inch', 0.0254, 4, 0, 0, NULL, NULL, NULL, NULL, NULL),
('m', 'len', 'м', 'm', 1.0000, 1, 1, 0, NULL, NULL, NULL, NULL, NULL),
('mm', 'len', 'мм', 'mm', 0.0010, 5, 0, 0, NULL, NULL, NULL, NULL, NULL),
('cnt', 'qty', 'контейнеров', 'containers', 1.0000, 3, 0, 0, NULL, NULL, NULL, NULL, NULL),
('crt', 'qty', 'коробок', 'cartons', 1.0000, 5, 0, 0, NULL, NULL, NULL, NULL, NULL),
('pal', 'qty', 'паллет', 'palletes', 1.0000, 2, 0, 0, NULL, NULL, NULL, NULL, NULL),
('pck', 'qty', 'упаковок', 'packs', 1.0000, 4, 0, 0, NULL, NULL, NULL, NULL, NULL),
('pcs', 'qty', 'шт', 'pcs', 1.0000, 1, 1, 0, NULL, NULL, NULL, NULL, NULL),
('kmh', 'spd', 'км/ч', 'km/h', 1.0000, 1, 1, 0, NULL, NULL, NULL, NULL, NULL),
('knots', 'spd', 'узлов', 'knots', 1.8520, 3, 0, 0, NULL, NULL, NULL, NULL, NULL),
('mph', 'spd', 'миль/ч', 'mph', 1.6093, 2, 0, 0, NULL, NULL, NULL, NULL, NULL),
('ms', 'spd', 'м/с', 'm/s', 3.6000, 4, 0, 0, NULL, NULL, NULL, NULL, NULL),
('hec', 'squ', 'га', 'hectare', 10000.0000, 2, 0, 0, NULL, NULL, NULL, NULL, NULL),
('km2', 'squ', 'км2', 'km2', 1000000.0000, 3, 0, 0, NULL, NULL, NULL, NULL, NULL),
('m2', 'squ', 'м2', 'm2', 1.0000, 1, 1, 0, NULL, NULL, NULL, NULL, NULL),
('day', 'tmp', 'дней', 'days', 86400.0000, 2, 0, 0, NULL, NULL, NULL, NULL, NULL),
('hrs', 'tmp', 'часов', 'hours', 3600.0000, 1, 0, 0, NULL, NULL, NULL, NULL, NULL),
('min', 'tmp', 'минут', 'min', 60.0000, 3, 0, 0, NULL, NULL, NULL, NULL, NULL),
('sec', 'tmp', 'секунд', 'sec', 1.0000, 4, 1, 0, NULL, NULL, NULL, NULL, NULL),
('l', 'vol', 'л', 'l', 0.0010, 2, 0, 0, NULL, NULL, NULL, NULL, NULL),
('m3', 'vol', 'м3', 'm3', 1.0000, 1, 1, 0, NULL, NULL, NULL, NULL, NULL),
('g', 'wgt', 'г', 'g', 0.0010, 4, 0, 0, NULL, NULL, NULL, NULL, NULL),
('kg', 'wgt', 'кг', 'kg', 1.0000, 1, 1, 0, NULL, NULL, NULL, NULL, NULL),
('lbs', 'wgt', 'фунтов', 'lbs', 0.4536, 3, 0, 0, NULL, NULL, NULL, NULL, NULL),
('t', 'wgt', 'т', 't', 1000.0000, 2, 0, 0, NULL, NULL, NULL, NULL, NULL);
SET FOREIGN_KEY_CHECKS=1;

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `stbl_user`;
CREATE TABLE `stbl_user` (
  `usrID` varchar(255) NOT NULL DEFAULT '',
  `usrName` varchar(255) DEFAULT NULL,
  `usrNameLocal` varchar(255) DEFAULT NULL,
  `usrAuthMethod` varchar(255) DEFAULT 'DB',
  `usrPass` varchar(32) DEFAULT NULL,
  `usrFlagLocal` tinyint(4) DEFAULT '0',
  `usrPhone` varchar(30) DEFAULT NULL,
  `usrEmail` varchar(255) DEFAULT NULL,
  `usrEmployeeID` int(11) DEFAULT NULL COMMENT 'Employee from tbl_employee',
  `usrFlagDeleted` tinyint(4) DEFAULT NULL,
  `usrInsertBy` varchar(50) DEFAULT NULL,
  `usrInsertDate` datetime DEFAULT NULL,
  `usrEditBy` varchar(50) DEFAULT NULL,
  `usrEditDate` datetime DEFAULT NULL,
  PRIMARY KEY (`usrID`),
  KEY `IX_Auth` (`usrID`,`usrPass`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='User authentication table';
INSERT INTO `stbl_user` VALUES
('admin', 'Admin', 'Администратор', 'DB', 'fc36d155acdc86b93cfb4c93dc35fbe5', 0, '', '', NULL, 0, 'admin', '2018-11-13 22:10:31', 'admin', '2018-11-13 22:10:31');
SET FOREIGN_KEY_CHECKS=1;

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `stbl_user_log`;
CREATE TABLE `stbl_user_log` (
  `uslID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uslUsrID` varchar(50) DEFAULT NULL,
  `uslTicket` varchar(255) DEFAULT NULL,
  `uslAuthCode` varchar(20) DEFAULT NULL,
  `uslAuthMessage` varchar(254) DEFAULT NULL,
  `uslPageName` varchar(255) DEFAULT NULL,
  `uslProtocol` varchar(255) DEFAULT NULL,
  `uslMethod` varchar(20) DEFAULT NULL,
  `uslGET` varchar(255) DEFAULT NULL,
  `uslPOST` varchar(1024) DEFAULT NULL,
  `uslCookies` varchar(1024) DEFAULT NULL,
  `uslAuthType` varchar(20) DEFAULT NULL,
  `uslRemoteIP` varchar(15) DEFAULT NULL,
  `uslUserAgent` varchar(255) DEFAULT NULL,
  `uslTime` datetime DEFAULT NULL,
  PRIMARY KEY (`uslID`),
  KEY `IX_logTicket` (`uslTicket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='User activity log';
SET FOREIGN_KEY_CHECKS=1;

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `stbl_version`;
CREATE TABLE `stbl_version` (
  `verNumber` int(10) unsigned NOT NULL,
  `verDesc` text,
  `verFlagVersioned` tinyint(4) NOT NULL DEFAULT '0',
  `verDate` datetime DEFAULT NULL,
  PRIMARY KEY (`verNumber`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Version information for the system';
INSERT INTO `stbl_version` VALUES
(1, 0x56657273696f6e20303031, 1, '2018-11-13 22:10:30');
SET FOREIGN_KEY_CHECKS=1;

DROP VIEW IF EXISTS `svw_action_attribute`;
CREATE  VIEW `svw_action_attribute` AS select `stbl_action_attribute`.`aatID` AS `aatID`,`stbl_action_attribute`.`aatActionID` AS `aatActionID`,`stbl_action_attribute`.`aatAttributeID` AS `aatAttributeID`,`stbl_action_attribute`.`aatFlagToTrack` AS `aatFlagToTrack`,`stbl_action_attribute`.`aatFlagMandatory` AS `aatFlagMandatory`,`stbl_action_attribute`.`aatFlagToChange` AS `aatFlagToChange`,`stbl_action_attribute`.`aatFlagToAdd` AS `aatFlagToAdd`,`stbl_action_attribute`.`aatFlagToPush` AS `aatFlagToPush`,`stbl_action_attribute`.`aatFlagEmptyOnInsert` AS `aatFlagEmptyOnInsert`,`stbl_action_attribute`.`aatFlagUserStamp` AS `aatFlagUserStamp`,`stbl_action_attribute`.`aatFlagTimestamp` AS `aatFlagTimestamp`,`stbl_action_attribute`.`aatInsertBy` AS `aatInsertBy`,`stbl_action_attribute`.`aatInsertDate` AS `aatInsertDate`,`stbl_action_attribute`.`aatEditBy` AS `aatEditBy`,`stbl_action_attribute`.`aatEditDate` AS `aatEditDate`,`stbl_action_attribute`.`aatTemp` AS `aatTemp`,`stbl_action`.`actID` AS `actID`,`stbl_action`.`actEntityID` AS `actEntityID`,`stbl_action`.`actTrackPrecision` AS `actTrackPrecision`,`stbl_action`.`actTitle` AS `actTitle`,`stbl_action`.`actTitleLocal` AS `actTitleLocal`,`stbl_action`.`actTitlePast` AS `actTitlePast`,`stbl_action`.`actTitlePastLocal` AS `actTitlePastLocal`,`stbl_action`.`actDescription` AS `actDescription`,`stbl_action`.`actDescriptionLocal` AS `actDescriptionLocal`,`stbl_action`.`actFlagDeleted` AS `actFlagDeleted`,`stbl_action`.`actPriority` AS `actPriority`,`stbl_action`.`actFlagComment` AS `actFlagComment`,`stbl_action`.`actShowConditions` AS `actShowConditions`,`stbl_action`.`actFlagHasEstimates` AS `actFlagHasEstimates`,`stbl_action`.`actFlagDepartureEqArrival` AS `actFlagDepartureEqArrival`,`stbl_action`.`actFlagAutocomplete` AS `actFlagAutocomplete`,`stbl_action`.`actDepartureDescr` AS `actDepartureDescr`,`stbl_action`.`actArrivalDescr` AS `actArrivalDescr`,`stbl_action`.`actFlagInterruptStatusStay` AS `actFlagInterruptStatusStay`,`stbl_action`.`actInsertBy` AS `actInsertBy`,`stbl_action`.`actInsertDate` AS `actInsertDate`,`stbl_action`.`actEditBy` AS `actEditBy`,`stbl_action`.`actEditDate` AS `actEditDate`,`stbl_attribute`.`atrID` AS `atrID`,`stbl_attribute`.`atrEntityID` AS `atrEntityID`,`stbl_attribute`.`atrTitle` AS `atrTitle`,`stbl_attribute`.`atrTitleLocal` AS `atrTitleLocal`,`stbl_attribute`.`atrShortTitle` AS `atrShortTitle`,`stbl_attribute`.`atrShortTitleLocal` AS `atrShortTitleLocal`,`stbl_attribute`.`atrFlagNoField` AS `atrFlagNoField`,`stbl_attribute`.`atrType` AS `atrType`,`stbl_attribute`.`atrUOMTypeID` AS `atrUOMTypeID`,`stbl_attribute`.`atrOrder` AS `atrOrder`,`stbl_attribute`.`atrClasses` AS `atrClasses`,`stbl_attribute`.`atrDefault` AS `atrDefault`,`stbl_attribute`.`atrTextIfNull` AS `atrTextIfNull`,`stbl_attribute`.`atrProgrammerReserved` AS `atrProgrammerReserved`,`stbl_attribute`.`atrCheckMask` AS `atrCheckMask`,`stbl_attribute`.`atrDataSource` AS `atrDataSource`,`stbl_attribute`.`atrFlagHideOnLists` AS `atrFlagHideOnLists`,`stbl_attribute`.`atrFlagDeleted` AS `atrFlagDeleted`,`stbl_attribute`.`atrInsertBy` AS `atrInsertBy`,`stbl_attribute`.`atrInsertDate` AS `atrInsertDate`,`stbl_attribute`.`atrEditBy` AS `atrEditBy`,`stbl_attribute`.`atrEditDate` AS `atrEditDate` from ((`stbl_action_attribute` join `stbl_action` on((`stbl_action_attribute`.`aatActionID` = `stbl_action`.`actID`))) join `stbl_attribute` on(((`stbl_action_attribute`.`aatAttributeID` = `stbl_attribute`.`atrID`) and (`stbl_action`.`actEntityID` = `stbl_attribute`.`atrEntityID`))));

DROP VIEW IF EXISTS `svw_user`;
CREATE  VIEW `svw_user` AS select `stbl_user`.`usrID` AS `optValue`,`stbl_user`.`usrFlagDeleted` AS `optFlagDeleted`,(cast(concat(`stbl_user`.`usrNameLocal`,' (',`stbl_user`.`usrID`,')') as char charset utf8) collate utf8_general_ci) AS `optTextLocal`,(cast(concat(`stbl_user`.`usrName`,' (',`stbl_user`.`usrID`,')') as char charset utf8) collate utf8_general_ci) AS `optText` from `stbl_user`;
