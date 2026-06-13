-- ============================================================
-- MealPlanner — skonsolidowany schemat bazy danych PostgreSQL
-- Wygenerowany z migracji 001–005
-- ============================================================

-- -------------------------------------------------------
-- Tabela migracji (init.sql)
-- -------------------------------------------------------

CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(64) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    applied_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- -------------------------------------------------------
-- 001 — Core Schema (użytkownicy, role, preferencje)
-- -------------------------------------------------------

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

-- -------------------------------------------------------
-- 002 — Recipe Domain Schema (przepisy, składniki, recenzje)
-- -------------------------------------------------------

CREATE TABLE IF NOT EXISTS recipe_categories (
    id SMALLSERIAL PRIMARY KEY,
    code VARCHAR(64) NOT NULL UNIQUE,
    label VARCHAR(80) NOT NULL,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS recipe_tags (
    id SMALLSERIAL PRIMARY KEY,
    code VARCHAR(64) NOT NULL UNIQUE,
    label VARCHAR(80) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS recipes (
    id BIGSERIAL PRIMARY KEY,
    author_user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    category_id SMALLINT REFERENCES recipe_categories(id) ON DELETE SET NULL ON UPDATE CASCADE,
    title VARCHAR(160) NOT NULL,
    slug VARCHAR(180) NOT NULL,
    description TEXT NOT NULL,
    difficulty VARCHAR(24) NOT NULL DEFAULT 'easy',
    prep_time_minutes SMALLINT NOT NULL,
    servings SMALLINT NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    visibility VARCHAR(16) NOT NULL DEFAULT 'private',
    rejection_reason TEXT,
    change_request_note TEXT,
    submitted_at TIMESTAMPTZ,
    approved_at TIMESTAMPTZ,
    published_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT recipes_title_length_check CHECK (char_length(title) >= 3),
    CONSTRAINT recipes_description_length_check CHECK (char_length(description) >= 20),
    CONSTRAINT recipes_difficulty_check CHECK (difficulty IN ('easy', 'medium', 'advanced')),
    CONSTRAINT recipes_prep_time_check CHECK (prep_time_minutes BETWEEN 1 AND 1440),
    CONSTRAINT recipes_servings_check CHECK (servings BETWEEN 1 AND 24),
    CONSTRAINT recipes_status_check CHECK (
        status IN ('draft', 'submitted', 'changes_requested', 'approved', 'rejected')
    ),
    CONSTRAINT recipes_visibility_check CHECK (visibility IN ('private', 'public')),
    CONSTRAINT recipes_public_visibility_requires_approval_check CHECK (
        visibility = 'private' OR status = 'approved'
    )
);

CREATE UNIQUE INDEX IF NOT EXISTS recipes_slug_unique_idx ON recipes (lower(slug));
CREATE INDEX IF NOT EXISTS recipes_author_user_id_idx ON recipes (author_user_id);
CREATE INDEX IF NOT EXISTS recipes_category_id_idx ON recipes (category_id);
CREATE INDEX IF NOT EXISTS recipes_status_idx ON recipes (status);
CREATE INDEX IF NOT EXISTS recipes_visibility_idx ON recipes (visibility);
CREATE INDEX IF NOT EXISTS recipes_published_at_idx ON recipes (published_at);

CREATE TABLE IF NOT EXISTS recipe_nutrition (
    recipe_id BIGINT PRIMARY KEY REFERENCES recipes(id) ON DELETE CASCADE,
    calories SMALLINT,
    protein_grams NUMERIC(6, 2),
    fat_grams NUMERIC(6, 2),
    carbohydrates_grams NUMERIC(6, 2),
    fiber_grams NUMERIC(6, 2),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT recipe_nutrition_calories_check CHECK (calories IS NULL OR calories >= 0),
    CONSTRAINT recipe_nutrition_protein_check CHECK (protein_grams IS NULL OR protein_grams >= 0),
    CONSTRAINT recipe_nutrition_fat_check CHECK (fat_grams IS NULL OR fat_grams >= 0),
    CONSTRAINT recipe_nutrition_carbohydrates_check CHECK (carbohydrates_grams IS NULL OR carbohydrates_grams >= 0),
    CONSTRAINT recipe_nutrition_fiber_check CHECK (fiber_grams IS NULL OR fiber_grams >= 0)
);

CREATE TABLE IF NOT EXISTS recipe_ingredients (
    id BIGSERIAL PRIMARY KEY,
    recipe_id BIGINT NOT NULL REFERENCES recipes(id) ON DELETE CASCADE,
    position SMALLINT NOT NULL,
    name VARCHAR(160) NOT NULL,
    amount VARCHAR(80) NOT NULL,
    estimated_price_cents INTEGER NOT NULL DEFAULT 0,
    note VARCHAR(160),
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT recipe_ingredients_position_check CHECK (position > 0),
    CONSTRAINT recipe_ingredients_name_length_check CHECK (char_length(name) >= 2),
    CONSTRAINT recipe_ingredients_amount_length_check CHECK (char_length(amount) >= 1),
    CONSTRAINT recipe_ingredients_estimated_price_check CHECK (estimated_price_cents >= 0),
    UNIQUE (recipe_id, position)
);

CREATE INDEX IF NOT EXISTS recipe_ingredients_recipe_id_idx ON recipe_ingredients (recipe_id);

CREATE TABLE IF NOT EXISTS recipe_steps (
    id BIGSERIAL PRIMARY KEY,
    recipe_id BIGINT NOT NULL REFERENCES recipes(id) ON DELETE CASCADE,
    position SMALLINT NOT NULL,
    instruction TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT recipe_steps_position_check CHECK (position > 0),
    CONSTRAINT recipe_steps_instruction_length_check CHECK (char_length(instruction) >= 5),
    UNIQUE (recipe_id, position)
);

CREATE INDEX IF NOT EXISTS recipe_steps_recipe_id_idx ON recipe_steps (recipe_id);

CREATE TABLE IF NOT EXISTS recipe_tag_assignments (
    recipe_id BIGINT NOT NULL REFERENCES recipes(id) ON DELETE CASCADE,
    tag_id SMALLINT NOT NULL REFERENCES recipe_tags(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (recipe_id, tag_id)
);

CREATE TABLE IF NOT EXISTS recipe_diet_types (
    recipe_id BIGINT NOT NULL REFERENCES recipes(id) ON DELETE CASCADE,
    diet_type_id SMALLINT NOT NULL REFERENCES diet_types(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (recipe_id, diet_type_id)
);

CREATE TABLE IF NOT EXISTS recipe_allergy_types (
    recipe_id BIGINT NOT NULL REFERENCES recipes(id) ON DELETE CASCADE,
    allergy_type_id SMALLINT NOT NULL REFERENCES allergy_types(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (recipe_id, allergy_type_id)
);

CREATE TABLE IF NOT EXISTS favorite_recipes (
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    recipe_id BIGINT NOT NULL REFERENCES recipes(id) ON DELETE CASCADE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, recipe_id)
);

CREATE INDEX IF NOT EXISTS favorite_recipes_recipe_id_idx ON favorite_recipes (recipe_id);

CREATE TABLE IF NOT EXISTS recipe_publication_reviews (
    id BIGSERIAL PRIMARY KEY,
    recipe_id BIGINT NOT NULL REFERENCES recipes(id) ON DELETE CASCADE,
    reviewer_user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    action VARCHAR(32) NOT NULL,
    reason TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT recipe_publication_reviews_action_check CHECK (
        action IN ('submitted', 'approved', 'rejected', 'changes_requested')
    ),
    CONSTRAINT recipe_publication_reviews_reason_required_check CHECK (
        action IN ('submitted', 'approved') OR reason IS NOT NULL
    )
);

CREATE INDEX IF NOT EXISTS recipe_publication_reviews_recipe_id_idx ON recipe_publication_reviews (recipe_id);
CREATE INDEX IF NOT EXISTS recipe_publication_reviews_reviewer_user_id_idx ON recipe_publication_reviews (reviewer_user_id);
CREATE INDEX IF NOT EXISTS recipe_publication_reviews_action_idx ON recipe_publication_reviews (action);

-- -------------------------------------------------------
-- 003 — Media Metadata Schema (pliki, avatary, zdjęcia)
-- -------------------------------------------------------

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

-- -------------------------------------------------------
-- 004 — Planning & Grocery Schema (planer, lista zakupów)
-- -------------------------------------------------------

CREATE TABLE IF NOT EXISTS meal_plans (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name VARCHAR(120) NOT NULL,
    week_start_date DATE NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT meal_plans_name_length_check CHECK (char_length(name) >= 3),
    CONSTRAINT meal_plans_week_start_date_check CHECK (
        extract(isodow FROM week_start_date) = 1
    ),
    CONSTRAINT meal_plans_status_check CHECK (status IN ('draft', 'active', 'archived')),
    UNIQUE (user_id, week_start_date)
);

CREATE INDEX IF NOT EXISTS meal_plans_user_id_idx ON meal_plans(user_id);
CREATE INDEX IF NOT EXISTS meal_plans_week_start_date_idx ON meal_plans(week_start_date);
CREATE INDEX IF NOT EXISTS meal_plans_status_idx ON meal_plans(status);

CREATE TABLE IF NOT EXISTS meal_plan_days (
    id BIGSERIAL PRIMARY KEY,
    meal_plan_id BIGINT NOT NULL REFERENCES meal_plans(id) ON DELETE CASCADE,
    planned_date DATE NOT NULL,
    day_note VARCHAR(255),
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (meal_plan_id, planned_date)
);

CREATE INDEX IF NOT EXISTS meal_plan_days_meal_plan_id_idx ON meal_plan_days(meal_plan_id);
CREATE INDEX IF NOT EXISTS meal_plan_days_planned_date_idx ON meal_plan_days(planned_date);

CREATE TABLE IF NOT EXISTS meal_slots (
    id BIGSERIAL PRIMARY KEY,
    meal_plan_day_id BIGINT NOT NULL REFERENCES meal_plan_days(id) ON DELETE CASCADE,
    slot_type VARCHAR(32) NOT NULL,
    position SMALLINT NOT NULL DEFAULT 1,
    slot_note VARCHAR(255),
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT meal_slots_slot_type_check CHECK (
        slot_type IN ('breakfast', 'second_breakfast', 'lunch', 'dinner', 'supper', 'snack')
    ),
    CONSTRAINT meal_slots_position_check CHECK (position > 0),
    UNIQUE (meal_plan_day_id, slot_type, position)
);

CREATE INDEX IF NOT EXISTS meal_slots_meal_plan_day_id_idx ON meal_slots(meal_plan_day_id);
CREATE INDEX IF NOT EXISTS meal_slots_slot_type_idx ON meal_slots(slot_type);

CREATE TABLE IF NOT EXISTS meal_slot_recipes (
    meal_slot_id BIGINT NOT NULL REFERENCES meal_slots(id) ON DELETE CASCADE,
    recipe_id BIGINT NOT NULL REFERENCES recipes(id) ON DELETE RESTRICT,
    servings SMALLINT NOT NULL DEFAULT 1,
    position SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT meal_slot_recipes_servings_check CHECK (servings BETWEEN 1 AND 24),
    CONSTRAINT meal_slot_recipes_position_check CHECK (position > 0),
    PRIMARY KEY (meal_slot_id, recipe_id),
    UNIQUE (meal_slot_id, position)
);

CREATE INDEX IF NOT EXISTS meal_slot_recipes_recipe_id_idx ON meal_slot_recipes(recipe_id);

CREATE TABLE IF NOT EXISTS grocery_lists (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    meal_plan_id BIGINT REFERENCES meal_plans(id) ON DELETE SET NULL,
    title VARCHAR(120) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT grocery_lists_title_length_check CHECK (char_length(title) >= 3),
    CONSTRAINT grocery_lists_status_check CHECK (status IN ('active', 'completed', 'archived'))
);

CREATE INDEX IF NOT EXISTS grocery_lists_user_id_idx ON grocery_lists(user_id);
CREATE INDEX IF NOT EXISTS grocery_lists_meal_plan_id_idx ON grocery_lists(meal_plan_id);
CREATE INDEX IF NOT EXISTS grocery_lists_status_idx ON grocery_lists(status);

CREATE TABLE IF NOT EXISTS grocery_item_categories (
    id SMALLSERIAL PRIMARY KEY,
    code VARCHAR(64) NOT NULL UNIQUE,
    label VARCHAR(80) NOT NULL,
    sort_order SMALLINT NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS grocery_items (
    id BIGSERIAL PRIMARY KEY,
    grocery_list_id BIGINT NOT NULL REFERENCES grocery_lists(id) ON DELETE CASCADE,
    category_id SMALLINT REFERENCES grocery_item_categories(id) ON DELETE SET NULL ON UPDATE CASCADE,
    source_recipe_id BIGINT REFERENCES recipes(id) ON DELETE SET NULL,
    source_meal_slot_id BIGINT REFERENCES meal_slots(id) ON DELETE SET NULL,
    name VARCHAR(160) NOT NULL,
    quantity VARCHAR(80),
    estimated_price_cents INTEGER NOT NULL DEFAULT 0,
    note VARCHAR(160),
    is_checked BOOLEAN NOT NULL DEFAULT FALSE,
    position SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT grocery_items_name_length_check CHECK (char_length(name) >= 2),
    CONSTRAINT grocery_items_estimated_price_check CHECK (estimated_price_cents >= 0),
    CONSTRAINT grocery_items_position_check CHECK (position > 0),
    UNIQUE (grocery_list_id, position)
);

CREATE INDEX IF NOT EXISTS grocery_items_grocery_list_id_idx ON grocery_items(grocery_list_id);
CREATE INDEX IF NOT EXISTS grocery_items_category_id_idx ON grocery_items(category_id);
CREATE INDEX IF NOT EXISTS grocery_items_source_recipe_id_idx ON grocery_items(source_recipe_id);
CREATE INDEX IF NOT EXISTS grocery_items_source_meal_slot_id_idx ON grocery_items(source_meal_slot_id);
CREATE INDEX IF NOT EXISTS grocery_items_is_checked_idx ON grocery_items(is_checked);

-- -------------------------------------------------------
-- 005 — Views, Functions, Triggers, Seed
-- -------------------------------------------------------

CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS set_users_updated_at ON users;
CREATE TRIGGER set_users_updated_at
BEFORE UPDATE ON users FOR EACH ROW EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS set_user_profiles_updated_at ON user_profiles;
CREATE TRIGGER set_user_profiles_updated_at
BEFORE UPDATE ON user_profiles FOR EACH ROW EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS set_user_account_settings_updated_at ON user_account_settings;
CREATE TRIGGER set_user_account_settings_updated_at
BEFORE UPDATE ON user_account_settings FOR EACH ROW EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS set_user_notification_preferences_updated_at ON user_notification_preferences;
CREATE TRIGGER set_user_notification_preferences_updated_at
BEFORE UPDATE ON user_notification_preferences FOR EACH ROW EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS set_user_food_preferences_updated_at ON user_food_preferences;
CREATE TRIGGER set_user_food_preferences_updated_at
BEFORE UPDATE ON user_food_preferences FOR EACH ROW EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS set_recipes_updated_at ON recipes;
CREATE TRIGGER set_recipes_updated_at
BEFORE UPDATE ON recipes FOR EACH ROW EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS set_recipe_nutrition_updated_at ON recipe_nutrition;
CREATE TRIGGER set_recipe_nutrition_updated_at
BEFORE UPDATE ON recipe_nutrition FOR EACH ROW EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS set_media_files_updated_at ON media_files;
CREATE TRIGGER set_media_files_updated_at
BEFORE UPDATE ON media_files FOR EACH ROW EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS set_meal_plans_updated_at ON meal_plans;
CREATE TRIGGER set_meal_plans_updated_at
BEFORE UPDATE ON meal_plans FOR EACH ROW EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS set_meal_plan_days_updated_at ON meal_plan_days;
CREATE TRIGGER set_meal_plan_days_updated_at
BEFORE UPDATE ON meal_plan_days FOR EACH ROW EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS set_meal_slots_updated_at ON meal_slots;
CREATE TRIGGER set_meal_slots_updated_at
BEFORE UPDATE ON meal_slots FOR EACH ROW EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS set_grocery_lists_updated_at ON grocery_lists;
CREATE TRIGGER set_grocery_lists_updated_at
BEFORE UPDATE ON grocery_lists FOR EACH ROW EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS set_grocery_items_updated_at ON grocery_items;
CREATE TRIGGER set_grocery_items_updated_at
BEFORE UPDATE ON grocery_items FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE OR REPLACE VIEW v_public_recipes_with_author AS
SELECT
    r.id,
    r.title,
    r.slug,
    r.description,
    r.difficulty,
    r.prep_time_minutes,
    r.servings,
    r.published_at,
    rc.code AS category_code,
    rc.label AS category_label,
    u.id AS author_user_id,
    u.username AS author_username,
    up.display_name AS author_display_name,
    rn.calories,
    rn.protein_grams,
    rn.fat_grams,
    rn.carbohydrates_grams,
    mf.public_id AS main_image_public_id,
    mf.stored_path AS main_image_path
FROM recipes r
JOIN users u ON u.id = r.author_user_id
JOIN user_profiles up ON up.user_id = u.id
LEFT JOIN recipe_categories rc ON rc.id = r.category_id
LEFT JOIN recipe_nutrition rn ON rn.recipe_id = r.id
LEFT JOIN recipe_media rm ON rm.recipe_id = r.id AND rm.media_role = 'main_image'
LEFT JOIN media_files mf ON mf.id = rm.media_file_id AND mf.deleted_at IS NULL
WHERE r.status = 'approved'
  AND r.visibility = 'public'
  AND u.is_active = TRUE;

CREATE OR REPLACE VIEW v_user_recipe_status_summary AS
SELECT
    u.id AS user_id,
    u.email,
    u.username,
    up.display_name,
    COUNT(r.id) AS total_recipes,
    COUNT(r.id) FILTER (WHERE r.status = 'draft') AS draft_recipes,
    COUNT(r.id) FILTER (WHERE r.status = 'submitted') AS submitted_recipes,
    COUNT(r.id) FILTER (WHERE r.status = 'changes_requested') AS changes_requested_recipes,
    COUNT(r.id) FILTER (WHERE r.status = 'approved') AS approved_recipes,
    COUNT(r.id) FILTER (WHERE r.status = 'rejected') AS rejected_recipes,
    COUNT(r.id) FILTER (WHERE r.visibility = 'public') AS public_recipes
FROM users u
LEFT JOIN user_profiles up ON up.user_id = u.id
LEFT JOIN recipes r ON r.author_user_id = u.id
GROUP BY u.id, u.email, u.username, up.display_name;

CREATE OR REPLACE VIEW v_user_meal_plan_overview AS
SELECT
    mp.id AS meal_plan_id,
    mp.user_id,
    u.username,
    mp.name,
    mp.week_start_date,
    mp.status,
    COUNT(DISTINCT mpd.id) AS planned_days,
    COUNT(DISTINCT ms.id) AS meal_slots,
    COUNT(DISTINCT msr.recipe_id) AS planned_recipes,
    gl.id AS grocery_list_id,
    gl.title AS grocery_list_title,
    COUNT(DISTINCT gi.id) AS grocery_items
FROM meal_plans mp
JOIN users u ON u.id = mp.user_id
LEFT JOIN meal_plan_days mpd ON mpd.meal_plan_id = mp.id
LEFT JOIN meal_slots ms ON ms.meal_plan_day_id = mpd.id
LEFT JOIN meal_slot_recipes msr ON msr.meal_slot_id = ms.id
LEFT JOIN grocery_lists gl ON gl.meal_plan_id = mp.id
LEFT JOIN grocery_items gi ON gi.grocery_list_id = gl.id
GROUP BY mp.id, mp.user_id, u.username, mp.name, mp.week_start_date, mp.status, gl.id, gl.title;
