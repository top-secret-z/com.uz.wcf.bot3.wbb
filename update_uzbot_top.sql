ALTER TABLE wcf1_uzbot_top ADD post        INT(10) DEFAULT NULL;

ALTER TABLE wcf1_uzbot_top ADD FOREIGN KEY (post) REFERENCES wcf1_user (userID) ON DELETE SET NULL;
