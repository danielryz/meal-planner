INSERT INTO diet_types (code, label) VALUES
  ('standard',    'Bez ograniczeń'),
  ('vegetarian',  'Wegetariańska'),
  ('vegan',       'Wegańska'),
  ('pescatarian', 'Peskatariańska'),
  ('keto',        'Ketogeniczna'),
  ('paleo',       'Paleo'),
  ('gluten_free', 'Bezglutenowa'),
  ('lactose_free','Bez laktozy')
ON CONFLICT (code) DO NOTHING;

INSERT INTO allergy_types (code, label) VALUES
  ('gluten',      'Gluten'),
  ('dairy',       'Nabiał'),
  ('eggs',        'Jajka'),
  ('nuts',        'Orzechy'),
  ('peanuts',     'Orzeszki ziemne'),
  ('shellfish',   'Skorupiaki'),
  ('fish',        'Ryby'),
  ('soy',         'Soja'),
  ('sesame',      'Sezam')
ON CONFLICT (code) DO NOTHING;

INSERT INTO schema_migrations (version, name)
VALUES ('011', 'preference_types_seed')
ON CONFLICT (version) DO NOTHING;
