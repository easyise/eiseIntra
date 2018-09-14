ALTER TABLE stbl_action_log ADD COLUMN aclItemBefore text NULL COMMENT 'JSON with item before action execute' AFTER aclATA
, ADD COLUMN aclItemAfter text NULL COMMENT 'JSON with item after action execute' AFTER aclItemBefore
, ADD COLUMN aclItemTraced text NULL COMMENT 'JSON with traced attributes' AFTER aclItemAfter
, ADD COLUMN aclItemDiff text NULL COMMENT 'JSON with diff' AFTER aclItemTraced
;