BEGIN;

INSERT INTO grocery_item_categories (code, label, sort_order)
VALUES
    ('vegetables', 'Warzywa i przetwory', 1),
    ('fruit',      'Owoce',               2),
    ('meat_fish',  'Mięso i ryby',        3),
    ('dairy',      'Nabiał',              4),
    ('grains',     'Pieczywo i zboża',    5),
    ('spices',     'Przyprawy i sosy',    6),
    ('other',      'Inne',                7)
ON CONFLICT (code) DO UPDATE
    SET label      = EXCLUDED.label,
        sort_order = EXCLUDED.sort_order;

INSERT INTO schema_migrations (version, name)
VALUES ('008', 'grocery_categories_seed')
ON CONFLICT (version) DO NOTHING;

COMMIT;
