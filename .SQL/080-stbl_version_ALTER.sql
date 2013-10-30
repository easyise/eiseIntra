ALTER TABLE `stbl_version`
	ADD COLUMN `verFlagVersioned` TINYINT(4) NOT NULL DEFAULT '0' AFTER `verDesc`;