BEGIN;

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
    note VARCHAR(160),
    is_checked BOOLEAN NOT NULL DEFAULT FALSE,
    position SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT grocery_items_name_length_check CHECK (char_length(name) >= 2),
    CONSTRAINT grocery_items_position_check CHECK (position > 0),
    UNIQUE (grocery_list_id, position)
);

CREATE INDEX IF NOT EXISTS grocery_items_grocery_list_id_idx ON grocery_items(grocery_list_id);
CREATE INDEX IF NOT EXISTS grocery_items_category_id_idx ON grocery_items(category_id);
CREATE INDEX IF NOT EXISTS grocery_items_source_recipe_id_idx ON grocery_items(source_recipe_id);
CREATE INDEX IF NOT EXISTS grocery_items_source_meal_slot_id_idx ON grocery_items(source_meal_slot_id);
CREATE INDEX IF NOT EXISTS grocery_items_is_checked_idx ON grocery_items(is_checked);

INSERT INTO grocery_item_categories (code, label, sort_order)
VALUES
    ('vegetables', 'Warzywa', 10),
    ('fruit', 'Owoce', 20),
    ('meat_fish', 'Mieso i ryby', 30),
    ('dairy', 'Nabial', 40),
    ('grains', 'Produkty sypkie', 50),
    ('spices', 'Przyprawy', 60),
    ('other', 'Inne', 100)
ON CONFLICT (code) DO UPDATE
SET label = EXCLUDED.label,
    sort_order = EXCLUDED.sort_order;

INSERT INTO schema_migrations (version, name)
VALUES ('004', 'planning_grocery_schema')
ON CONFLICT (version) DO NOTHING;

COMMIT;
