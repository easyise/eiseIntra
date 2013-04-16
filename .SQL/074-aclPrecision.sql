ALTER TABLE `stbl_action_log`
	ADD COLUMN `aclPrecision` ENUM('date','datetime') NOT NULL DEFAULT 'datetime' AFTER `aclActionPhase`;