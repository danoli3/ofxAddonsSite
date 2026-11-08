-- Run once on an existing database before deploying the AI-triage changes.
-- Fresh databases already have these columns in tests/schema.sql.
ALTER TABLE repos
  ADD COLUMN ai_triage_reviewed_at DATETIME DEFAULT NULL,
  ADD COLUMN ai_triage_attempts INT NOT NULL DEFAULT 0;
