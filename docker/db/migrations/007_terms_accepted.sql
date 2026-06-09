BEGIN;

ALTER TABLE users ADD COLUMN IF NOT EXISTS terms_accepted_at TIMESTAMPTZ;

INSERT INTO schema_migrations (version, name) VALUES
    ('007', 'terms_accepted')
ON CONFLICT (version) DO NOTHING;

COMMIT;
