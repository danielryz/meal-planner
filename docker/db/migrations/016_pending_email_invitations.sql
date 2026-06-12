ALTER TABLE users
    ADD COLUMN IF NOT EXISTS pending_email VARCHAR(255);

CREATE TABLE IF NOT EXISTS user_invitations (
    id                  BIGSERIAL PRIMARY KEY,
    invited_by_user_id  BIGINT REFERENCES users(id) ON DELETE SET NULL,
    email               VARCHAR(255) NOT NULL,
    role                VARCHAR(32)  NOT NULL DEFAULT 'employee',
    token_hash          VARCHAR(255) NOT NULL UNIQUE,
    expires_at          TIMESTAMPTZ  NOT NULL,
    accepted_at         TIMESTAMPTZ,
    created_at          TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS user_invitations_token_hash_idx ON user_invitations (token_hash);
CREATE INDEX IF NOT EXISTS user_invitations_email_idx      ON user_invitations (email);

INSERT INTO schema_migrations (version, name)
VALUES ('016', 'pending_email_and_invitations')
ON CONFLICT (version) DO NOTHING;
