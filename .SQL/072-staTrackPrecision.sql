ALTER TABLE `stbl_status`
	ADD COLUMN `staTrackPrecision` ENUM('date','datetime') NOT NULL DEFAULT 'datetime' AFTER `staEntityID`;