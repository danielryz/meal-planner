BEGIN;

ALTER TABLE meal_plans
    ADD COLUMN IF NOT EXISTS weekly_budget INTEGER NOT NULL DEFAULT 0;

INSERT INTO schema_migrations (version, name)
VALUES ('009', 'meal_plan_budget')
ON CONFLICT (version) DO NOTHING;

COMMIT;
