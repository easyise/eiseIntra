ALTER TABLE `stbl_attribute`
	ALTER `atrID` DROP DEFAULT;
ALTER TABLE `stbl_attribute`
	CHANGE COLUMN `atrID` `atrID` VARCHAR(255) NOT NULL COMMENT 'Attribute ID (equal to field name in the Master table)' FIRST,
	CHANGE COLUMN `atrEntityID` `atrEntityID` VARCHAR(20) NOT NULL DEFAULT '' COMMENT 'Entity ID' AFTER `atrID`,
	CHANGE COLUMN `atrTitle` `atrTitle` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Title in English' AFTER `atrEntityID`,
	CHANGE COLUMN `atrTitleLocal` `atrTitleLocal` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Title in local language' AFTER `atrTitle`,
	ADD COLUMN `atrShortTitle` VARCHAR(255) NOT NULL COMMENT 'Short title (for lists, in English)' AFTER `atrTitleLocal`,
	ADD COLUMN `atrShortTitleLocal` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Short title (for lists, in local language)' AFTER `atrShortTitle`,
	CHANGE COLUMN `atrFlagNoField` `atrFlagNoField` TINYINT(4) NOT NULL DEFAULT '0' COMMENT 'True when there\'s no field for this attribute in Master table' AFTER `atrShortTitleLocal`,
	CHANGE COLUMN `atrType` `atrType` VARCHAR(20) NULL DEFAULT NULL COMMENT 'Type (see Intra types)' AFTER `atrFlagNoField`,
	CHANGE COLUMN `atrUOMTypeID` `atrUOMTypeID` VARCHAR(10) NULL DEFAULT NULL COMMENT 'UOM type (FK to stbl_uom)' AFTER `atrType`,
	CHANGE COLUMN `atrProperties` `atrClasses` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'CSS classes' AFTER `atrOrder`,
	CHANGE COLUMN `atrDefault` `atrDefault` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Default Value' AFTER `atrClasses`,
	ADD COLUMN `atrTextIfNull` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Text to dispay wgen value not set' AFTER `atrDefault`,
	CHANGE COLUMN `atrProgrammerReserved` `atrProgrammerReserved` TEXT NULL COMMENT 'Reserved for programmer' AFTER `atrTextIfNull`,
	CHANGE COLUMN `atrCheckMask` `atrCheckMask` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Regular expression for data validation' AFTER `atrProgrammerReserved`,
	CHANGE COLUMN `atrDataSource` `atrDataSource` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Data source (table, view or array)' AFTER `atrCheckMask`,
	ADD COLUMN `atrFlagHideOnLists` TINYINT NOT NULL DEFAULT '0' COMMENT 'True when this attribute not shown on lists' AFTER `atrDataSource`,
	CHANGE COLUMN `atrFlagDeleted` `atrFlagDeleted` TINYINT(11) NOT NULL DEFAULT '0' COMMENT 'True when attribute no longer used' AFTER `atrFlagHideOnLists`,
	CHANGE COLUMN `atrInsertBy` `atrInsertBy` VARCHAR(50) NULL DEFAULT NULL AFTER `atrFlagDeleted`,
	CHANGE COLUMN `atrEditBy` `atrEditBy` VARCHAR(50) NULL DEFAULT NULL AFTER `atrInsertDate`,
	DROP COLUMN `atrFieldName`;
