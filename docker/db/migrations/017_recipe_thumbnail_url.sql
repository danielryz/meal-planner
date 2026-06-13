BEGIN;

ALTER TABLE recipes ADD COLUMN IF NOT EXISTS thumbnail_url TEXT;

INSERT INTO schema_migrations (version, name)
VALUES ('017', 'recipe_thumbnail_url')
ON CONFLICT (version) DO NOTHING;

COMMIT;
