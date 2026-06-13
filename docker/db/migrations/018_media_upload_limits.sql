BEGIN;

ALTER TABLE media_files
    DROP CONSTRAINT IF EXISTS media_files_size_bytes_check;

ALTER TABLE media_files
    ADD CONSTRAINT media_files_size_bytes_check
    CHECK (size_bytes > 0 AND size_bytes <= 524288000);

INSERT INTO schema_migrations (version, name)
VALUES ('018', 'media_upload_limits')
ON CONFLICT (version) DO NOTHING;

COMMIT;
