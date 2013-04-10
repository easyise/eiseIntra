DROP TABLE IF EXISTS stbl_translation;
CREATE TABLE `stbl_translation` (
	`strKey` VARCHAR(255) NOT NULL,
	`strValue` VARCHAR(255) NOT NULL DEFAULT '',
	PRIMARY KEY (`strKey`)
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB;