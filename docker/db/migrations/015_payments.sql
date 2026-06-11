CREATE TABLE IF NOT EXISTS payments (
    id               BIGSERIAL PRIMARY KEY,
    user_id          BIGINT REFERENCES users(id) ON DELETE SET NULL,
    paynow_payment_id VARCHAR(64),
    external_id      VARCHAR(64) UNIQUE NOT NULL,
    amount_grosz     INT NOT NULL,
    description      VARCHAR(255) NOT NULL,
    buyer_email      VARCHAR(255),
    status           VARCHAR(32) NOT NULL DEFAULT 'NEW',
    created_at       TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS payments_paynow_id_idx ON payments (paynow_payment_id);
CREATE INDEX IF NOT EXISTS payments_status_idx ON payments (status);
CREATE INDEX IF NOT EXISTS payments_user_id_idx ON payments (user_id);

CREATE TRIGGER set_payments_updated_at
    BEFORE UPDATE ON payments
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
