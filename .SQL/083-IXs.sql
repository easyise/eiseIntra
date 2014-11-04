ALTER TABLE `stbl_action_attribute`
	ALTER `aatActionID` DROP DEFAULT,
	ALTER `aatAttributeID` DROP DEFAULT;
ALTER TABLE `stbl_action_attribute`
	CHANGE COLUMN `aatActionID` `aatActionID` INT(11) NOT NULL AFTER `aatID`,
	CHANGE COLUMN `aatAttributeID` `aatAttributeID` VARCHAR(50) NOT NULL AFTER `aatActionID`,
	ADD INDEX `IX_aatActionID` (`aatActionID`),
	ADD INDEX `IX_aatAttributeID` (`aatAttributeID`);
	
	
ALTER TABLE `stbl_action`
	ADD INDEX `IX_actEntityID` (`actEntityID`);