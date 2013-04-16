DROP VIEW IF EXISTS svw_action_attribute;

CREATE VIEW svw_action_attribute AS
SELECT * 
FROM stbl_action_attribute 
INNER JOIN stbl_action ON aatActionID=actID
INNER JOIN stbl_attribute ON aatAttributeID=atrID AND actEntityID=atrEntityID;