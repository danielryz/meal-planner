BEGIN;

CREATE TABLE IF NOT EXISTS email_tokens (
    id         BIGSERIAL PRIMARY KEY,
    user_id    BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash VARCHAR(255) NOT NULL,
    type       VARCHAR(32) NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    used_at    TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT email_tokens_type_check CHECK (type IN ('activation', 'password_reset', 'email_change'))
);

CREATE UNIQUE INDEX IF NOT EXISTS email_tokens_token_hash_idx ON email_tokens (token_hash);
CREATE INDEX IF NOT EXISTS email_tokens_user_id_type_idx ON email_tokens (user_id, type);

INSERT INTO schema_migrations (version, name)
VALUES ('006', 'email_tokens')
ON CONFLICT (version) DO NOTHING;

COMMIT;
