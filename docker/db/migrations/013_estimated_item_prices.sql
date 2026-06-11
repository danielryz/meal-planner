ALTER TABLE recipe_ingredients
    ADD COLUMN IF NOT EXISTS estimated_price_cents INTEGER NOT NULL DEFAULT 0;

ALTER TABLE grocery_items
    ADD COLUMN IF NOT EXISTS estimated_price_cents INTEGER NOT NULL DEFAULT 0;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'recipe_ingredients_estimated_price_check'
    ) THEN
        ALTER TABLE recipe_ingredients
            ADD CONSTRAINT recipe_ingredients_estimated_price_check CHECK (estimated_price_cents >= 0);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'grocery_items_estimated_price_check'
    ) THEN
        ALTER TABLE grocery_items
            ADD CONSTRAINT grocery_items_estimated_price_check CHECK (estimated_price_cents >= 0);
    END IF;
END $$;

INSERT INTO schema_migrations (version, name)
VALUES ('013', 'estimated_item_prices')
ON CONFLICT (version) DO NOTHING;
