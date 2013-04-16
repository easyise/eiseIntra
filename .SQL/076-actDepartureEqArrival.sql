ALTER TABLE `stbl_action`
	CHANGE COLUMN `actFlagDatetime` `actFlagDepartureEqArrival` TINYINT(4) NOT NULL DEFAULT '0' AFTER `actFlagHasEstimates`;