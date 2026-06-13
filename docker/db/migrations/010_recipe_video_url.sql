ALTER TABLE recipes ADD COLUMN IF NOT EXISTS video_url TEXT;

INSERT INTO schema_migrations (version, name)
VALUES ('010', 'recipe_video_url')
ON CONFLICT (version) DO NOTHING;
