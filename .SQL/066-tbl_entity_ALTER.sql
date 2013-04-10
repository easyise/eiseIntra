ALTER TABLE `stbl_entity`
	COMMENT='Defines entities',
	ADD COLUMN `entTitleMul` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Prural' AFTER `entTitle`,
	ADD COLUMN `entTitleLocalMul` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Prural in local' AFTER `entTitleLocal`,
	ADD COLUMN `entTitleLocalGen` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Genitive (Roditelny) in local' AFTER `entTitleLocalMul`,
	ADD COLUMN `entTitleLocalDat` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Dative (Datelny) in local' AFTER `entTitleLocalGen`,
	ADD COLUMN `entTitleLocalAcc` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Accusative (Vinitelny) in local' AFTER `entTitleLocalDat`,
	ADD COLUMN `entTitleLocalIns` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Instrumental case (Tvoritelny) in local' AFTER `entTitleLocalAcc`,
	ADD COLUMN `entTitleLocalAbl` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Prepositional case (Predlozhny) in local' AFTER `entTitleLocalIns`;
