ALTER TABLE stbl_setup ADD COLUMN stpFlagOnDemand tinyint(4) NOT NULL DEFAULT '0' COMMENT 'These settings will not be loaded' AFTER stpFlagReadOnly;

ALTER TABLE stbl_setup ADD COLUMN stpFlagHiddenOnForm tinyint(4) NOT NULL DEFAULT '0' COMMENT 'These settings will be hidden on the form' AFTER stpFlagOnDemand;