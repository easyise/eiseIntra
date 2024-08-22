ALTER TABLE stbl_attribute 
	ADD COLUMN IF NOT EXISTS atrMatrix varchar(10) NULL  COMMENT 'Matrix comparison operator (<=, >=, =)' AFTER atrFlagHideOnLists;