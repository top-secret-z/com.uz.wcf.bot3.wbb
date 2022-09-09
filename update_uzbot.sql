ALTER TABLE wcf1_uzbot ADD wbbPostData					TEXT;
ALTER TABLE wcf1_uzbot ADD wbbPostModerationData		TEXT;
ALTER TABLE wcf1_uzbot ADD wbbThreadData				TEXT;
ALTER TABLE wcf1_uzbot ADD wbbThreadModerationData		TEXT;
ALTER TABLE wcf1_uzbot ADD wbbThreadModificationData	TEXT;

ALTER TABLE wcf1_uzbot ADD postCountAction				VARCHAR(15),
ALTER TABLE wcf1_uzbot ADD threadID						INT(10);
ALTER TABLE wcf1_uzbot ADD topPosterCount				INT(10) DEFAULT 1;
ALTER TABLE wcf1_uzbot ADD topPosterInterval			TINYINT(1) DEFAULT 1;
ALTER TABLE wcf1_uzbot ADD topPosterNext				INT(10) DEFAULT 0;
ALTER TABLE wcf1_uzbot ADD threadNewBoardIDs			TEXT;
ALTER TABLE wcf1_uzbot ADD uzbotBoardIDs				TEXT;

ALTER TABLE wcf1_uzbot ADD postChangeUpdate				TINYINT(1) DEFAULT 1;
ALTER TABLE wcf1_uzbot ADD postChangeDelete				TINYINT(1) DEFAULT 1;

ALTER TABLE wcf1_uzbot ADD postIsOfficial				TINYINT(1) DEFAULT 0;
ALTER TABLE wcf1_uzbot ADD threadIsOfficial				TINYINT(1) DEFAULT 0;