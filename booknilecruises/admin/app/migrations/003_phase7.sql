-- Track category changes for IndexNow submissions.
ALTER TABLE terms ADD updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
