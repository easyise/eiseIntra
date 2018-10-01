ALTER TABLE `stbl_message`
	CHANGE COLUMN msgStatus msgStatus VARCHAR(255) NULL DEFAULT NULL COMMENT 'Status',
	ADD INDEX `IX_msgStatus` (`msgStatus`);