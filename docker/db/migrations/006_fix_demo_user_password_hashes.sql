BEGIN;

UPDATE users
SET password_hash = '$2y$10$SBSkT9SmrP1i.FMK7yJI3u/TIqBWa5uq0PHu2Edl5bAb3BHoz9QU6'
WHERE email IN ('owner@example.com', 'employee@example.com', 'user@example.com');

INSERT INTO schema_migrations (version, name)
VALUES ('006', 'fix_demo_user_password_hashes')
ON CONFLICT (version) DO NOTHING;

COMMIT;
