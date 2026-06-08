BEGIN;

CREATE TABLE IF NOT EXISTS roles (
    id SMALLSERIAL PRIMARY KEY,
    name VARCHAR(32) NOT NULL UNIQUE,
    label VARCHAR(64) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT roles_name_check CHECK (name IN ('admin', 'owner', 'employee', 'user'))
);

CREATE TABLE IF NOT EXISTS users (
    id BIGSERIAL PRIMARY KEY,
    role_id SMALLINT NOT NULL REFERENCES roles(id) ON UPDATE CASCADE,
    email VARCHAR(255) NOT NULL,
    username VARCHAR(64) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    email_verified_at TIMESTAMPTZ,
    last_login_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT users_email_format_check CHECK (email ~* '^[^@\s]+@[^@\s]+\.[^@\s]+$'),
    CONSTRAINT users_username_length_check CHECK (char_length(username) >= 3),
    CONSTRAINT users_password_hash_length_check CHECK (char_length(password_hash) >= 60)
);

CREATE UNIQUE INDEX IF NOT EXISTS users_email_unique_idx ON users (lower(email));
CREATE UNIQUE INDEX IF NOT EXISTS users_username_unique_idx ON users (lower(username));
CREATE INDEX IF NOT EXISTS users_role_id_idx ON users (role_id);
CREATE INDEX IF NOT EXISTS users_is_active_idx ON users (is_active);

CREATE TABLE IF NOT EXISTS user_profiles (
    user_id BIGINT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    display_name VARCHAR(120) NOT NULL,
    avatar_initials VARCHAR(4),
    bio TEXT,
    is_public BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT user_profiles_display_name_length_check CHECK (char_length(display_name) >= 2)
);

CREATE TABLE IF NOT EXISTS user_account_settings (
    user_id BIGINT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    password_changed_at TIMESTAMPTZ,
    pending_email VARCHAR(255),
    pending_email_token_hash VARCHAR(255),
    pending_email_requested_at TIMESTAMPTZ,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT user_account_settings_pending_email_format_check CHECK (
        pending_email IS NULL OR pending_email ~* '^[^@\s]+@[^@\s]+\.[^@\s]+$'
    )
);

CREATE TABLE IF NOT EXISTS user_notification_preferences (
    user_id BIGINT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    meal_reminders_email BOOLEAN NOT NULL DEFAULT TRUE,
    grocery_reminders_email BOOLEAN NOT NULL DEFAULT TRUE,
    recipe_review_app BOOLEAN NOT NULL DEFAULT TRUE,
    team_activity_app BOOLEAN NOT NULL DEFAULT FALSE,
    account_security_email BOOLEAN NOT NULL DEFAULT TRUE,
    quiet_hours_start TIME NOT NULL DEFAULT '22:00',
    quiet_hours_end TIME NOT NULL DEFAULT '07:00',
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS diet_types (
    id SMALLSERIAL PRIMARY KEY,
    code VARCHAR(64) NOT NULL UNIQUE,
    label VARCHAR(80) NOT NULL
);

CREATE TABLE IF NOT EXISTS allergy_types (
    id SMALLSERIAL PRIMARY KEY,
    code VARCHAR(64) NOT NULL UNIQUE,
    label VARCHAR(80) NOT NULL
);

CREATE TABLE IF NOT EXISTS cuisine_types (
    id SMALLSERIAL PRIMARY KEY,
    code VARCHAR(64) NOT NULL UNIQUE,
    label VARCHAR(80) NOT NULL
);

CREATE TABLE IF NOT EXISTS user_food_preferences (
    user_id BIGINT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    diet_type_id SMALLINT REFERENCES diet_types(id) ON UPDATE CASCADE,
    default_servings SMALLINT NOT NULL DEFAULT 2,
    meals_per_day SMALLINT NOT NULL DEFAULT 3,
    weekly_budget_cents INTEGER,
    disliked_ingredients TEXT,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT user_food_preferences_default_servings_check CHECK (default_servings BETWEEN 1 AND 12),
    CONSTRAINT user_food_preferences_meals_per_day_check CHECK (meals_per_day BETWEEN 1 AND 8),
    CONSTRAINT user_food_preferences_weekly_budget_check CHECK (
        weekly_budget_cents IS NULL OR weekly_budget_cents >= 0
    )
);

CREATE TABLE IF NOT EXISTS user_allergy_preferences (
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    allergy_type_id SMALLINT NOT NULL REFERENCES allergy_types(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, allergy_type_id)
);

CREATE TABLE IF NOT EXISTS user_cuisine_preferences (
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    cuisine_type_id SMALLINT NOT NULL REFERENCES cuisine_types(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, cuisine_type_id)
);

CREATE TABLE IF NOT EXISTS user_activity_events (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    event_type VARCHAR(80) NOT NULL,
    ip_address INET,
    user_agent TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS user_activity_events_user_id_idx ON user_activity_events (user_id);
CREATE INDEX IF NOT EXISTS user_activity_events_event_type_idx ON user_activity_events (event_type);

INSERT INTO schema_migrations (version, name)
VALUES ('001', 'core_schema')
ON CONFLICT (version) DO NOTHING;

COMMIT;
