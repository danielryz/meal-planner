ALTER TABLE users
    ADD COLUMN IF NOT EXISTS oauth_provider    VARCHAR(32),
    ADD COLUMN IF NOT EXISTS oauth_provider_id VARCHAR(255);

ALTER TABLE users
    ALTER COLUMN password_hash DROP NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS users_oauth_unique_idx
    ON users (oauth_provider, oauth_provider_id)
    WHERE oauth_provider IS NOT NULL;
