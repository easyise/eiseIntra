ALTER TABLE `stbl_status_log`
	DROP INDEX `IX_stl`,
	ADD INDEX `IX_stl` (`stlEntityItemID`);