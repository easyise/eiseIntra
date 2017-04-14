ALTER TABLE stbl_message CHANGE COLUMN msgSubject msgSubject varchar(512) NOT NULL DEFAULT '' AFTER msgCCUserID;

ALTER TABLE stbl_message CHANGE COLUMN msgStatus msgStatus varchar(512) NULL  AFTER msgText;