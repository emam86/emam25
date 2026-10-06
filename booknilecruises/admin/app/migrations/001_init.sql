-- Book Nile Cruises admin: full schema. MySQL 5.7+/MariaDB 10.3+, utf8mb4.

CREATE TABLE roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  permissions JSON NOT NULL,
  is_system TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role_id INT UNSIGNED NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  succeeded TINYINT(1) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_login_email_time (email, created_at),
  KEY idx_login_ip_time (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  actor VARCHAR(190) NOT NULL,
  action VARCHAR(60) NOT NULL,
  entity VARCHAR(40) NOT NULL,
  entity_id VARCHAR(64) NULL,
  summary VARCHAR(255) NOT NULL DEFAULT '',
  details JSON NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_time (created_at),
  KEY idx_audit_entity (entity, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE media (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  path VARCHAR(255) NOT NULL UNIQUE,          -- /images/2025/12/name.jpg
  width INT UNSIGNED NULL,
  height INT UNSIGNED NULL,
  mime VARCHAR(50) NULL,
  filesize INT UNSIGNED NULL,
  alt VARCHAR(255) NOT NULL DEFAULT '',
  sizes JSON NULL,                            -- [{"path":"/images/..-768x512.jpg","width":768,"height":512}]
  uploaded_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE terms (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  taxonomy ENUM('destination','activities','trip_types') NOT NULL,
  slug VARCHAR(190) NOT NULL,
  name VARCHAR(190) NOT NULL,
  parent_id INT UNSIGNED NULL,
  url VARCHAR(255) NOT NULL,
  description TEXT NULL,
  seo_title VARCHAR(255) NULL,
  seo_description VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_terms (taxonomy, slug),
  CONSTRAINT fk_terms_parent FOREIGN KEY (parent_id) REFERENCES terms(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE trips (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(190) NOT NULL UNIQUE,
  title VARCHAR(255) NOT NULL,
  status ENUM('draft','published') NOT NULL DEFAULT 'draft',
  excerpt TEXT NULL,
  code VARCHAR(40) NULL,
  price DECIMAL(10,2) NULL,
  sale_price DECIMAL(10,2) NULL,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  duration_days SMALLINT UNSIGNED NULL,
  duration_nights SMALLINT UNSIGNED NULL,
  min_pax SMALLINT UNSIGNED NULL,
  max_pax SMALLINT UNSIGNED NULL,
  overview_html MEDIUMTEXT NULL,
  highlights JSON NULL,                       -- ["…", "…"]
  itinerary JSON NULL,                        -- [{"title":"…","html":"…"}] (html empty = group heading)
  includes JSON NULL,
  excludes JSON NULL,
  faqs JSON NULL,                             -- [{"q":"…","a":"<p>…</p>"}]
  image_id INT UNSIGNED NULL,
  gallery JSON NULL,                          -- [media id, …]
  featured TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  seo_title VARCHAR(255) NULL,
  seo_description VARCHAR(500) NULL,
  noindex TINYINT(1) NOT NULL DEFAULT 0,
  legacy_id INT UNSIGNED NULL,                -- WordPress post id
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_trips_image FOREIGN KEY (image_id) REFERENCES media(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE trip_terms (
  trip_id INT UNSIGNED NOT NULL,
  term_id INT UNSIGNED NOT NULL,
  position SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (trip_id, term_id),
  CONSTRAINT fk_tt_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
  CONSTRAINT fk_tt_term FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE posts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(190) NOT NULL,
  url VARCHAR(255) NOT NULL UNIQUE,           -- /2026/09/13/slug/
  title VARCHAR(255) NOT NULL,
  status ENUM('draft','published') NOT NULL DEFAULT 'draft',
  excerpt TEXT NULL,
  content_html MEDIUMTEXT NOT NULL,
  image_id INT UNSIGNED NULL,
  seo_title VARCHAR(255) NULL,
  seo_description VARCHAR(500) NULL,
  noindex TINYINT(1) NOT NULL DEFAULT 0,
  source VARCHAR(40) NOT NULL DEFAULT 'admin', -- admin | api:<key name>
  published_at DATETIME NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_posts_image FOREIGN KEY (image_id) REFERENCES media(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE seo_overrides (
  path VARCHAR(255) NOT NULL PRIMARY KEY,     -- /about-us/
  title VARCHAR(255) NULL,
  description VARCHAR(500) NULL,
  noindex TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE redirects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  from_path VARCHAR(255) NOT NULL UNIQUE,
  to_path VARCHAR(255) NOT NULL,
  source ENUM('manual','auto') NOT NULL DEFAULT 'manual',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
  `key` VARCHAR(100) NOT NULL PRIMARY KEY,
  `value` TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE enquiries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(60) NULL,
  trip_id INT UNSIGNED NULL,
  trip_title VARCHAR(255) NULL,
  travel_date DATE NULL,
  adults SMALLINT UNSIGNED NULL,
  children SMALLINT UNSIGNED NULL,
  message TEXT NULL,
  page_url VARCHAR(255) NULL,
  channel VARCHAR(20) NOT NULL DEFAULT 'form', -- form | whatsapp | email
  status ENUM('new','contacted','booked','closed','spam') NOT NULL DEFAULT 'new',
  notes TEXT NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_enq_status (status, created_at),
  CONSTRAINT fk_enq_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE api_keys (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  key_prefix CHAR(8) NOT NULL,
  key_hash CHAR(64) NOT NULL UNIQUE,          -- sha256 of the full key
  scopes JSON NOT NULL,                       -- ["posts.write","trips.read",…]
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_used_at DATETIME NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE webhooks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  url VARCHAR(500) NOT NULL,
  events JSON NOT NULL,                       -- ["enquiry.created","site.published","post.published"]
  secret VARCHAR(64) NOT NULL,                -- HMAC-SHA256 signing secret
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_status VARCHAR(60) NULL,
  last_called_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE publish_jobs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  status ENUM('queued','running','succeeded','failed') NOT NULL DEFAULT 'queued',
  triggered_by VARCHAR(190) NOT NULL,
  note VARCHAR(255) NULL,
  run_url VARCHAR(500) NULL,
  message TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at DATETIME NULL,
  KEY idx_publish_time (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE seo_reports (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  issues JSON NOT NULL,
  error_count INT UNSIGNED NOT NULL DEFAULT 0,
  warning_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
