DROP VIEW IF EXISTS svw_user;
CREATE VIEW svw_user AS
SELECT
usrID as optValue
, usrFlagDeleted as optFlagDeleted
, CAST(CONCAT(usrNameLocal, ' (', usrID ,')') AS CHAR CHARACTER SET 'utf8') COLLATE 'utf8_general_ci' as optTextLocal
, CAST(CONCAT(usrName, ' (', usrID ,')') AS CHAR CHARACTER SET 'utf8') COLLATE 'utf8_general_ci' as optText
FROM stbl_user;