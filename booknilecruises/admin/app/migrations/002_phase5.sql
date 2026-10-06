-- Serialize rolling rate limits and publish dispatches across PHP workers.
CREATE TABLE api_locks (
  name VARCHAR(100) PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE api_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bucket VARCHAR(100) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_api_bucket_time (bucket, created_at),
  KEY idx_api_request_time (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE posts MODIFY source VARCHAR(190) NOT NULL DEFAULT 'admin';

-- Preserve malformed optional beacon contacts without truncation.
ALTER TABLE enquiries MODIFY message MEDIUMTEXT NULL;
