BEGIN;

CREATE TABLE IF NOT EXISTS media_files (
    id BIGSERIAL PRIMARY KEY,
    public_id UUID NOT NULL UNIQUE,
    owner_user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    original_filename VARCHAR(255) NOT NULL,
    stored_path VARCHAR(500) NOT NULL UNIQUE,
    mime_type VARCHAR(120) NOT NULL,
    media_type VARCHAR(16) NOT NULL CHECK (media_type IN ('image', 'video')),
    purpose VARCHAR(32) NOT NULL CHECK (purpose IN ('profile_avatar', 'recipe_photo', 'recipe_video')),
    size_bytes BIGINT NOT NULL CHECK (size_bytes > 0 AND size_bytes <= 524288000),
    width INTEGER CHECK (width IS NULL OR width > 0),
    height INTEGER CHECK (height IS NULL OR height > 0),
    duration_seconds INTEGER CHECK (duration_seconds IS NULL OR duration_seconds > 0),
    checksum_sha256 CHAR(64),
    is_public BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMPTZ,
    CONSTRAINT media_files_purpose_type_check CHECK (
        (purpose IN ('profile_avatar', 'recipe_photo') AND media_type = 'image')
        OR (purpose = 'recipe_video' AND media_type = 'video')
    ),
    CONSTRAINT media_files_video_duration_check CHECK (
        media_type <> 'video'
        OR duration_seconds IS NULL
        OR duration_seconds > 0
    )
);

CREATE TABLE IF NOT EXISTS user_profile_avatars (
    user_id BIGINT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    media_file_id BIGINT NOT NULL UNIQUE REFERENCES media_files(id) ON DELETE RESTRICT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS recipe_media (
    id BIGSERIAL PRIMARY KEY,
    recipe_id BIGINT NOT NULL REFERENCES recipes(id) ON DELETE CASCADE,
    media_file_id BIGINT NOT NULL UNIQUE REFERENCES media_files(id) ON DELETE RESTRICT,
    media_role VARCHAR(32) NOT NULL CHECK (media_role IN ('main_image', 'gallery_image', 'video')),
    position SMALLINT NOT NULL DEFAULT 1 CHECK (position > 0),
    alt_text VARCHAR(255),
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (recipe_id, media_role, position)
);

CREATE INDEX IF NOT EXISTS media_files_owner_user_id_idx ON media_files(owner_user_id);
CREATE INDEX IF NOT EXISTS media_files_media_type_idx ON media_files(media_type);
CREATE INDEX IF NOT EXISTS media_files_purpose_idx ON media_files(purpose);
CREATE INDEX IF NOT EXISTS media_files_deleted_at_idx ON media_files(deleted_at);
CREATE INDEX IF NOT EXISTS user_profile_avatars_media_file_id_idx ON user_profile_avatars(media_file_id);
CREATE INDEX IF NOT EXISTS recipe_media_recipe_id_idx ON recipe_media(recipe_id);
CREATE INDEX IF NOT EXISTS recipe_media_media_file_id_idx ON recipe_media(media_file_id);

CREATE UNIQUE INDEX IF NOT EXISTS recipe_media_one_main_image_idx
    ON recipe_media(recipe_id)
    WHERE media_role = 'main_image';

CREATE UNIQUE INDEX IF NOT EXISTS recipe_media_one_video_idx
    ON recipe_media(recipe_id)
    WHERE media_role = 'video';

INSERT INTO schema_migrations (version, name)
VALUES ('003', 'media_metadata_schema')
ON CONFLICT (version) DO NOTHING;

COMMIT;
