-- Schema for this app's own tables, dumped from production via
-- `SHOW CREATE TABLE` and trimmed of foreign-key constraints (irrelevant
-- to booting the app locally/in CI, and this file's job is standing up
-- something the code can run against, not mirroring prod's referential
-- integrity setup exactly).
--
-- The underlying MySQL database predates this PHP codebase - it still
-- carries a few Rails/ActiveRecord-era tables (migration_info, releases,
-- schema_migrations) from whatever the site ran on before, which nothing
-- in app/ ever queries. They're deliberately left out here; this file is
-- only the tables ofxAddonsSite's own code actually touches.
--
-- Used by:
--   - tests/smoke.sh (CI - see .github/workflows/smoke-tests.yml)
--   - your own local setup (see README's "Running it" section)
--
-- Keep this in sync by hand if you add/rename a column in production -
-- there's no migration tool in this repo, so this file only reflects
-- reality as of whenever someone last re-ran the SHOW CREATE TABLE dump
-- and updated it.

CREATE TABLE admin_logs (
  id INT NOT NULL AUTO_INCREMENT,
  user_id INT DEFAULT NULL,
  action VARCHAR(50) NOT NULL,
  repo_id INT DEFAULT NULL,
  details TEXT,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_created (created_at),
  KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
  id INT NOT NULL AUTO_INCREMENT,
  provider VARCHAR(255) NOT NULL,
  uid VARCHAR(255) DEFAULT NULL,
  name VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT NULL,
  updated_at DATETIME DEFAULT NULL,
  login VARCHAR(255) NOT NULL,
  avatar_url VARCHAR(255) DEFAULT NULL,
  location VARCHAR(255) DEFAULT NULL,
  admin TINYINT(1) DEFAULT 0,
  last_login_at DATETIME DEFAULT NULL,
  super_admin TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY index_users_on_provider_and_login (provider, login),
  UNIQUE KEY index_users_on_provider_and_avatar_url (provider, avatar_url),
  UNIQUE KEY index_users_on_provider_and_uid (provider, uid),
  KEY idx_users_last_login_at (last_login_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE categories (
  id INT NOT NULL AUTO_INCREMENT,
  name VARCHAR(50) NOT NULL,
  avatar_url TEXT,
  created_at DATETIME DEFAULT NULL,
  updated_at DATETIME DEFAULT NULL,
  categorizations_count INT DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE repos (
  id INT NOT NULL AUTO_INCREMENT,
  name TEXT,
  description TEXT,
  pushed_at DATETIME DEFAULT NULL,
  source TEXT,
  parent TEXT,
  full_name TEXT,
  most_recent_commit TEXT,
  issues TEXT,
  fork TINYINT(1) DEFAULT 0,
  example_count INT DEFAULT 0,
  has_makefile TINYINT(1) DEFAULT NULL,
  has_correct_folder_structure TINYINT(1) DEFAULT NULL,
  has_thumbnail TINYINT(1) DEFAULT NULL,
  user_id INT DEFAULT NULL,
  release_id INT DEFAULT NULL,
  type VARCHAR(255) NOT NULL DEFAULT 'Unsorted',
  stargazers_count INT DEFAULT 0,
  created_at DATETIME DEFAULT NULL,
  updated_at DATETIME DEFAULT NULL,
  forks_count INT DEFAULT 0,
  archived TINYINT(1) NOT NULL DEFAULT 0,
  has_releases TINYINT(1) NOT NULL DEFAULT 0,
  description_curated TINYINT(1) NOT NULL DEFAULT 0,
  description_generated TINYINT(1) NOT NULL DEFAULT 0,
  hidden_by_owner TINYINT(1) NOT NULL DEFAULT 0,
  thumbnail_url_override VARCHAR(500) DEFAULT NULL,
  ban_appealed TINYINT(1) NOT NULL DEFAULT 0,
  llm_description VARCHAR(350) DEFAULT NULL,
  llm_judgement JSON DEFAULT NULL,
  newer_forks JSON DEFAULT NULL,
  default_branch VARCHAR(100) DEFAULT NULL,
  ahead_branches JSON DEFAULT NULL,
  confirmed_fork_of INT DEFAULT NULL,
  fork_hidden_by_admin TINYINT(1) NOT NULL DEFAULT 0,
  confirmed_fork_stats JSON DEFAULT NULL,
  of_version VARCHAR(10) DEFAULT NULL,
  of_version_curated TINYINT(1) NOT NULL DEFAULT 0,
  confirmed_unique TINYINT(1) NOT NULL DEFAULT 0,
  ai_thumbnail_generated_at DATETIME DEFAULT NULL,
  categories_ai_curated TINYINT(1) NOT NULL DEFAULT 0,
  ai_triage_batched_at DATETIME DEFAULT NULL,
  security_flagged TINYINT(1) NOT NULL DEFAULT 0,
  security_flag_reason TEXT,
  security_flagged_at DATETIME DEFAULT NULL,
  ai_triage_notes TEXT,
  ai_triage_priority_at DATETIME DEFAULT NULL,
  thumbnail_is_generic TINYINT(1) NOT NULL DEFAULT 0,
  thumbnail_checked_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  KEY index_repos_full_name (full_name(191)),
  KEY repos_user_id_fk (user_id),
  KEY idx_repos_type_pushed_at (type, pushed_at),
  KEY idx_repos_type_updated_at (type, updated_at),
  KEY idx_repos_type_hidden (type, hidden_by_owner),
  KEY idx_repos_type_stars (type, stargazers_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE categorizations (
  id INT NOT NULL AUTO_INCREMENT,
  category_id INT NOT NULL,
  repo_id INT NOT NULL,
  created_at DATETIME DEFAULT NULL,
  updated_at DATETIME DEFAULT NULL,
  featured TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY index_categorizations_on_category_id (category_id),
  KEY index_categorizations_on_repo_id (repo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ai_triage_queue (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  repo_id INT UNSIGNED NOT NULL,
  full_name VARCHAR(255) NOT NULL,
  entry_json TEXT NOT NULL,
  submitted_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY repo_id (repo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ai_triage_denials (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  repo_id INT UNSIGNED DEFAULT NULL,
  full_name VARCHAR(255) NOT NULL,
  entry_json TEXT,
  reason TEXT,
  denied_by INT UNSIGNED DEFAULT NULL,
  denied_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_ai_triage_denials_full_name (full_name),
  KEY idx_ai_triage_denials_denied_at (denied_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE security_bans (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip VARCHAR(64) NOT NULL,
  reason VARCHAR(255) NOT NULL,
  banned_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_security_bans_ip (ip),
  KEY idx_security_bans_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE security_failures (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip VARCHAR(64) NOT NULL,
  kind VARCHAR(32) NOT NULL,
  occurred_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_security_failures_lookup (ip, kind, occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
