ALTER TABLE `stbl_status_log`
	ADD COLUMN `stlPrecision` ENUM('date','datetime') NOT NULL DEFAULT 'datetime' AFTER `stlTitleLocal`;