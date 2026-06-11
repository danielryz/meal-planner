BEGIN;

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

INSERT INTO schema_migrations (version, name)
VALUES ('002', 'recipe_domain_schema')
ON CONFLICT (version) DO NOTHING;

COMMIT;
