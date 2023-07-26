ALTER TABLE stbl_status 
	ADD COLUMN IF NOT EXISTS staDescriptionLocal varchar(1024) NULL DEFAULT NULL COMMENT 'Description' AFTER staDescription;