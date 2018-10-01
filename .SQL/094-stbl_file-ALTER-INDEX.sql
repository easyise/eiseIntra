ALTER TABLE `stbl_file`
	DROP INDEX `filEntityID`,
	ADD INDEX `filEntityID` (`filEntityItemID`);