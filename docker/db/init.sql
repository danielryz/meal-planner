CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(64) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    applied_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

\i /docker-entrypoint-initdb.d/migrations/001_core_schema.sql
\i /docker-entrypoint-initdb.d/migrations/002_recipe_domain_schema.sql
\i /docker-entrypoint-initdb.d/migrations/003_media_metadata_schema.sql
\i /docker-entrypoint-initdb.d/migrations/004_planning_grocery_schema.sql
\i /docker-entrypoint-initdb.d/migrations/005_views_functions_triggers_seed.sql
\i /docker-entrypoint-initdb.d/migrations/006_email_tokens.sql
\i /docker-entrypoint-initdb.d/migrations/007_terms_accepted.sql
\i /docker-entrypoint-initdb.d/migrations/008_grocery_categories_seed.sql
\i /docker-entrypoint-initdb.d/migrations/009_meal_plan_budget.sql
\i /docker-entrypoint-initdb.d/migrations/010_recipe_video_url.sql
