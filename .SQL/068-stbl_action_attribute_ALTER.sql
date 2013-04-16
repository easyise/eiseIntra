ALTER TABLE `stbl_action_attribute`
	ADD COLUMN `aatFlagToTrack` TINYINT NOT NULL DEFAULT '0' AFTER `aatAttributeID`;
UPDATE stbl_action_attribute SET aatFlagToTrack=1;