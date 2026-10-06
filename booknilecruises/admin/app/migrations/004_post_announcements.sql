ALTER TABLE posts ADD COLUMN announced_at DATETIME NULL;
UPDATE posts SET announced_at = NOW() WHERE status = 'published' AND published_at <= NOW();
