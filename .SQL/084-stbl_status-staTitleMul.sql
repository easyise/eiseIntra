ALTER TABLE stbl_status ADD COLUMN staTitleMul varchar(255) NOT NULL DEFAULT '' AFTER staTitle;

ALTER TABLE stbl_status ADD COLUMN staTitleLocalMul varchar(255) NOT NULL DEFAULT '' AFTER staTitleLocal;