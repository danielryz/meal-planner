BEGIN;

CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS set_users_updated_at ON users;
CREATE TRIGGER set_users_updated_at
BEFORE UPDATE ON users
FOR EACH ROW
EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS set_user_profiles_updated_at ON user_profiles;
CREATE TRIGGER set_user_profiles_updated_at
BEFORE UPDATE ON user_profiles
FOR EACH ROW
EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS set_user_account_settings_updated_at ON user_account_settings;
CREATE TRIGGER set_user_account_settings_updated_at
BEFORE UPDATE ON user_account_settings
FOR EACH ROW
EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS set_user_notification_preferences_updated_at ON user_notification_preferences;
CREATE TRIGGER set_user_notification_preferences_updated_at
BEFORE UPDATE ON user_notification_preferences
FOR EACH ROW
EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS set_user_food_preferences_updated_at ON user_food_preferences;
CREATE TRIGGER set_user_food_preferences_updated_at
BEFORE UPDATE ON user_food_preferences
FOR EACH ROW
EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS set_recipes_updated_at ON recipes;
CREATE TRIGGER set_recipes_updated_at
BEFORE UPDATE ON recipes
FOR EACH ROW
EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS set_recipe_nutrition_updated_at ON recipe_nutrition;
CREATE TRIGGER set_recipe_nutrition_updated_at
BEFORE UPDATE ON recipe_nutrition
FOR EACH ROW
EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS set_media_files_updated_at ON media_files;
CREATE TRIGGER set_media_files_updated_at
BEFORE UPDATE ON media_files
FOR EACH ROW
EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS set_meal_plans_updated_at ON meal_plans;
CREATE TRIGGER set_meal_plans_updated_at
BEFORE UPDATE ON meal_plans
FOR EACH ROW
EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS set_meal_plan_days_updated_at ON meal_plan_days;
CREATE TRIGGER set_meal_plan_days_updated_at
BEFORE UPDATE ON meal_plan_days
FOR EACH ROW
EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS set_meal_slots_updated_at ON meal_slots;
CREATE TRIGGER set_meal_slots_updated_at
BEFORE UPDATE ON meal_slots
FOR EACH ROW
EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS set_grocery_lists_updated_at ON grocery_lists;
CREATE TRIGGER set_grocery_lists_updated_at
BEFORE UPDATE ON grocery_lists
FOR EACH ROW
EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS set_grocery_items_updated_at ON grocery_items;
CREATE TRIGGER set_grocery_items_updated_at
BEFORE UPDATE ON grocery_items
FOR EACH ROW
EXECUTE FUNCTION set_updated_at();

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

INSERT INTO users (role_id, email, username, password_hash, email_verified_at)
SELECT r.id, 'owner@example.com', 'owner_demo', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC.0iChnySgMnEp8wqC.', CURRENT_TIMESTAMP
FROM roles r
WHERE r.name = 'owner'
  AND NOT EXISTS (SELECT 1 FROM users WHERE lower(email) = lower('owner@example.com'));

INSERT INTO users (role_id, email, username, password_hash, email_verified_at)
SELECT r.id, 'employee@example.com', 'employee_demo', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC.0iChnySgMnEp8wqC.', CURRENT_TIMESTAMP
FROM roles r
WHERE r.name = 'employee'
  AND NOT EXISTS (SELECT 1 FROM users WHERE lower(email) = lower('employee@example.com'));

INSERT INTO users (role_id, email, username, password_hash, email_verified_at)
SELECT r.id, 'user@example.com', 'user_demo', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC.0iChnySgMnEp8wqC.', CURRENT_TIMESTAMP
FROM roles r
WHERE r.name = 'user'
  AND NOT EXISTS (SELECT 1 FROM users WHERE lower(email) = lower('user@example.com'));

INSERT INTO user_profiles (user_id, display_name, avatar_initials, bio, is_public)
SELECT id, 'Wlasciciel MealPlanner', 'WM', 'Konto demo do moderacji przepisow.', TRUE
FROM users
WHERE email = 'owner@example.com'
ON CONFLICT (user_id) DO UPDATE
SET display_name = EXCLUDED.display_name,
    avatar_initials = EXCLUDED.avatar_initials,
    bio = EXCLUDED.bio,
    is_public = EXCLUDED.is_public;

INSERT INTO user_profiles (user_id, display_name, avatar_initials, bio, is_public)
SELECT id, 'Pracownik MealPlanner', 'PM', 'Konto demo do obslugi kolejki publikacji.', TRUE
FROM users
WHERE email = 'employee@example.com'
ON CONFLICT (user_id) DO UPDATE
SET display_name = EXCLUDED.display_name,
    avatar_initials = EXCLUDED.avatar_initials,
    bio = EXCLUDED.bio,
    is_public = EXCLUDED.is_public;

INSERT INTO user_profiles (user_id, display_name, avatar_initials, bio, is_public)
SELECT id, 'Uzytkownik Demo', 'UD', 'Konto demo do planowania posilkow.', TRUE
FROM users
WHERE email = 'user@example.com'
ON CONFLICT (user_id) DO UPDATE
SET display_name = EXCLUDED.display_name,
    avatar_initials = EXCLUDED.avatar_initials,
    bio = EXCLUDED.bio,
    is_public = EXCLUDED.is_public;

INSERT INTO user_account_settings (user_id, password_changed_at)
SELECT id, CURRENT_TIMESTAMP
FROM users
WHERE email IN ('owner@example.com', 'employee@example.com', 'user@example.com')
ON CONFLICT (user_id) DO NOTHING;

INSERT INTO user_notification_preferences (user_id)
SELECT id
FROM users
WHERE email IN ('owner@example.com', 'employee@example.com', 'user@example.com')
ON CONFLICT (user_id) DO NOTHING;

INSERT INTO user_food_preferences (user_id, diet_type_id, default_servings, meals_per_day, weekly_budget_cents)
SELECT u.id, dt.id, 2, 4, 25000
FROM users u
LEFT JOIN diet_types dt ON dt.code = 'standard'
WHERE u.email = 'user@example.com'
ON CONFLICT (user_id) DO UPDATE
SET diet_type_id = EXCLUDED.diet_type_id,
    default_servings = EXCLUDED.default_servings,
    meals_per_day = EXCLUDED.meals_per_day,
    weekly_budget_cents = EXCLUDED.weekly_budget_cents;

INSERT INTO recipes (
    author_user_id,
    category_id,
    title,
    slug,
    description,
    difficulty,
    prep_time_minutes,
    servings,
    status,
    visibility,
    submitted_at,
    approved_at,
    published_at
)
SELECT
    u.id,
    rc.id,
    'Makaron z warzywami',
    'makaron-z-warzywami',
    'Prosty obiad demo z makaronem, sezonowymi warzywami i lekkim sosem.',
    'easy',
    25,
    2,
    'approved',
    'public',
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
FROM users u
LEFT JOIN recipe_categories rc ON rc.code = 'dinner'
WHERE u.email = 'user@example.com'
  AND NOT EXISTS (SELECT 1 FROM recipes WHERE lower(slug) = lower('makaron-z-warzywami'));

INSERT INTO recipe_nutrition (recipe_id, calories, protein_grams, fat_grams, carbohydrates_grams, fiber_grams)
SELECT id, 520, 18.00, 15.00, 72.00, 9.00
FROM recipes
WHERE slug = 'makaron-z-warzywami'
ON CONFLICT (recipe_id) DO UPDATE
SET calories = EXCLUDED.calories,
    protein_grams = EXCLUDED.protein_grams,
    fat_grams = EXCLUDED.fat_grams,
    carbohydrates_grams = EXCLUDED.carbohydrates_grams,
    fiber_grams = EXCLUDED.fiber_grams;

INSERT INTO recipe_ingredients (recipe_id, position, name, amount, note)
SELECT id, 1, 'Makaron pelnoziarnisty', '160 g', NULL
FROM recipes
WHERE slug = 'makaron-z-warzywami'
ON CONFLICT (recipe_id, position) DO UPDATE
SET name = EXCLUDED.name,
    amount = EXCLUDED.amount,
    note = EXCLUDED.note;

INSERT INTO recipe_ingredients (recipe_id, position, name, amount, note)
SELECT id, 2, 'Cukinia', '1 sztuka', 'Pokrojona w polplastry'
FROM recipes
WHERE slug = 'makaron-z-warzywami'
ON CONFLICT (recipe_id, position) DO UPDATE
SET name = EXCLUDED.name,
    amount = EXCLUDED.amount,
    note = EXCLUDED.note;

INSERT INTO recipe_ingredients (recipe_id, position, name, amount, note)
SELECT id, 3, 'Sos pomidorowy', '200 ml', NULL
FROM recipes
WHERE slug = 'makaron-z-warzywami'
ON CONFLICT (recipe_id, position) DO UPDATE
SET name = EXCLUDED.name,
    amount = EXCLUDED.amount,
    note = EXCLUDED.note;

INSERT INTO recipe_steps (recipe_id, position, instruction)
SELECT id, 1, 'Ugotuj makaron zgodnie z instrukcja na opakowaniu.'
FROM recipes
WHERE slug = 'makaron-z-warzywami'
ON CONFLICT (recipe_id, position) DO UPDATE
SET instruction = EXCLUDED.instruction;

INSERT INTO recipe_steps (recipe_id, position, instruction)
SELECT id, 2, 'Podsmaz warzywa, dodaj sos pomidorowy i dopraw do smaku.'
FROM recipes
WHERE slug = 'makaron-z-warzywami'
ON CONFLICT (recipe_id, position) DO UPDATE
SET instruction = EXCLUDED.instruction;

INSERT INTO recipe_steps (recipe_id, position, instruction)
SELECT id, 3, 'Polacz makaron z sosem i podaj od razu po przygotowaniu.'
FROM recipes
WHERE slug = 'makaron-z-warzywami'
ON CONFLICT (recipe_id, position) DO UPDATE
SET instruction = EXCLUDED.instruction;

INSERT INTO recipe_tag_assignments (recipe_id, tag_id)
SELECT r.id, rt.id
FROM recipes r
JOIN recipe_tags rt ON rt.code IN ('quick', 'budget')
WHERE r.slug = 'makaron-z-warzywami'
ON CONFLICT (recipe_id, tag_id) DO NOTHING;

INSERT INTO recipe_diet_types (recipe_id, diet_type_id)
SELECT r.id, dt.id
FROM recipes r
JOIN diet_types dt ON dt.code = 'vegetarian'
WHERE r.slug = 'makaron-z-warzywami'
ON CONFLICT (recipe_id, diet_type_id) DO NOTHING;

INSERT INTO meal_plans (user_id, name, week_start_date, status)
SELECT id, 'Plan demo', DATE '2026-06-01', 'active'
FROM users
WHERE email = 'user@example.com'
ON CONFLICT (user_id, week_start_date) DO UPDATE
SET name = EXCLUDED.name,
    status = EXCLUDED.status;

INSERT INTO meal_plan_days (meal_plan_id, planned_date, day_note)
SELECT id, DATE '2026-06-01', 'Start tygodnia'
FROM meal_plans
WHERE name = 'Plan demo'
ON CONFLICT (meal_plan_id, planned_date) DO UPDATE
SET day_note = EXCLUDED.day_note;

INSERT INTO meal_slots (meal_plan_day_id, slot_type, position, slot_note)
SELECT id, 'dinner', 1, 'Obiad po pracy'
FROM meal_plan_days
WHERE planned_date = DATE '2026-06-01'
ON CONFLICT (meal_plan_day_id, slot_type, position) DO UPDATE
SET slot_note = EXCLUDED.slot_note;

INSERT INTO meal_slot_recipes (meal_slot_id, recipe_id, servings, position)
SELECT ms.id, r.id, 2, 1
FROM meal_slots ms
JOIN meal_plan_days mpd ON mpd.id = ms.meal_plan_day_id
JOIN meal_plans mp ON mp.id = mpd.meal_plan_id
JOIN recipes r ON r.slug = 'makaron-z-warzywami'
WHERE mp.name = 'Plan demo'
  AND ms.slot_type = 'dinner'
ON CONFLICT (meal_slot_id, recipe_id) DO UPDATE
SET servings = EXCLUDED.servings,
    position = EXCLUDED.position;

INSERT INTO grocery_lists (user_id, meal_plan_id, title, status)
SELECT u.id, mp.id, 'Lista zakupow demo', 'active'
FROM users u
JOIN meal_plans mp ON mp.user_id = u.id AND mp.name = 'Plan demo'
WHERE u.email = 'user@example.com'
  AND NOT EXISTS (
      SELECT 1
      FROM grocery_lists gl
      WHERE gl.user_id = u.id
        AND gl.title = 'Lista zakupow demo'
  );

INSERT INTO grocery_items (grocery_list_id, category_id, source_recipe_id, source_meal_slot_id, name, quantity, position)
SELECT gl.id, gic.id, r.id, ms.id, 'Makaron pelnoziarnisty', '160 g', 1
FROM grocery_lists gl
JOIN users u ON u.id = gl.user_id
JOIN recipes r ON r.slug = 'makaron-z-warzywami'
JOIN meal_plans mp ON mp.id = gl.meal_plan_id
JOIN meal_plan_days mpd ON mpd.meal_plan_id = mp.id AND mpd.planned_date = DATE '2026-06-01'
JOIN meal_slots ms ON ms.meal_plan_day_id = mpd.id AND ms.slot_type = 'dinner'
LEFT JOIN grocery_item_categories gic ON gic.code = 'grains'
WHERE u.email = 'user@example.com'
  AND gl.title = 'Lista zakupow demo'
ON CONFLICT (grocery_list_id, position) DO UPDATE
SET category_id = EXCLUDED.category_id,
    source_recipe_id = EXCLUDED.source_recipe_id,
    source_meal_slot_id = EXCLUDED.source_meal_slot_id,
    name = EXCLUDED.name,
    quantity = EXCLUDED.quantity;

INSERT INTO grocery_items (grocery_list_id, category_id, source_recipe_id, source_meal_slot_id, name, quantity, position)
SELECT gl.id, gic.id, r.id, ms.id, 'Cukinia', '1 sztuka', 2
FROM grocery_lists gl
JOIN users u ON u.id = gl.user_id
JOIN recipes r ON r.slug = 'makaron-z-warzywami'
JOIN meal_plans mp ON mp.id = gl.meal_plan_id
JOIN meal_plan_days mpd ON mpd.meal_plan_id = mp.id AND mpd.planned_date = DATE '2026-06-01'
JOIN meal_slots ms ON ms.meal_plan_day_id = mpd.id AND ms.slot_type = 'dinner'
LEFT JOIN grocery_item_categories gic ON gic.code = 'vegetables'
WHERE u.email = 'user@example.com'
  AND gl.title = 'Lista zakupow demo'
ON CONFLICT (grocery_list_id, position) DO UPDATE
SET category_id = EXCLUDED.category_id,
    source_recipe_id = EXCLUDED.source_recipe_id,
    source_meal_slot_id = EXCLUDED.source_meal_slot_id,
    name = EXCLUDED.name,
    quantity = EXCLUDED.quantity;

INSERT INTO schema_migrations (version, name)
VALUES ('005', 'views_functions_triggers_seed')
ON CONFLICT (version) DO NOTHING;

COMMIT;
