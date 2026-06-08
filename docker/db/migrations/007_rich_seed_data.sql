BEGIN;

INSERT INTO schema_migrations (version, name)
VALUES ('007', 'rich_seed_data')
ON CONFLICT (version) DO NOTHING;

COMMIT;
