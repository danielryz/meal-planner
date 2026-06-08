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

INSERT INTO schema_migrations (version, name)
VALUES ('005', 'views_functions_triggers_seed')
ON CONFLICT (version) DO NOTHING;

COMMIT;
