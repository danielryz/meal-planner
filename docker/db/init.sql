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
\i /docker-entrypoint-initdb.d/migrations/006_fix_demo_user_password_hashes.sql
