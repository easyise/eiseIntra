ALTER TABLE stbl_status 
	ADD COLUMN IF NOT EXISTS staDescription varchar(1024) NULL DEFAULT NULL COMMENT 'Description' AFTER staMenuItemClass;