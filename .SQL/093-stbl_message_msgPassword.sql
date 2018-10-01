ALTER TABLE `stbl_message`
	ADD COLUMN msgPassword varchar(255) NULL DEFAULT NULL COMMENT 'Encrypted SMTP Password';