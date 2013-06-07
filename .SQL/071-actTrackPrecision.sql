ALTER TABLE `stbl_action`
	ADD COLUMN `actTrackPrecision` ENUM('date','datetime') NOT NULL DEFAULT 'datetime' AFTER `actFlagInterruptStatusStay`;