ALTER TABLE stbl_status 
	DROP COLUMN IF EXISTS staTitleLocalMul,
	ADD COLUMN IF NOT EXISTS staTitleMulLocal varchar(250) NOT NULL DEFAULT '' COMMENT 'Multiple (Local)' AFTER staTitleMul;