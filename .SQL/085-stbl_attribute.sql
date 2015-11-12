UPDATE stbl_attribute SET 
	atrTitle = IFNULL(atrTitle, '')
	, atrTitleLocal = IFNULL(atrTitleLocal, '')
	, atrShortTitle = IFNULL(atrShortTitle, '')
	, atrShortTitleLocal = IFNULL(atrShortTitleLocal, '');

ALTER TABLE stbl_attribute CHANGE COLUMN atrTitle atrTitle varchar(255) NOT NULL DEFAULT '' AFTER atrEntityID;

ALTER TABLE stbl_attribute CHANGE COLUMN atrTitleLocal atrTitleLocal varchar(255) NOT NULL DEFAULT '' AFTER atrTitle;

ALTER TABLE stbl_attribute CHANGE COLUMN atrShortTitle atrShortTitle varchar(255) NOT NULL DEFAULT '' AFTER atrTitleLocal;

ALTER TABLE stbl_attribute CHANGE COLUMN atrShortTitleLocal atrShortTitleLocal varchar(255) NOT NULL DEFAULT '' AFTER atrShortTitle;