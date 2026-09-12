-- Minimal fixture data for tests/smoke.sh - just enough for the smoke-
-- tested pages (categories, addon list, browse, addon detail, contributor
-- page) to have something real to render instead of every list being
-- empty. Not meant to resemble the real dataset's scale or content.

INSERT INTO users (id, provider, uid, name, login, avatar_url, admin, super_admin, created_at, updated_at) VALUES
  (1, 'github', '1001', 'Test Owner', 'smoketest-owner', 'https://avatars.githubusercontent.com/u/1?v=4', 0, 0, NOW(), NOW()),
  (2, 'github', '1002', 'Test Admin', 'smoketest-admin', 'https://avatars.githubusercontent.com/u/2?v=4', 1, 1, NOW(), NOW());

INSERT INTO categories (id, name, created_at, updated_at) VALUES
  (1, 'Graphics', NOW(), NOW()),
  (2, 'Sound', NOW(), NOW());

-- columns, one per line, so the value count under each is easy to verify:
--   id, name, full_name, description, type, user_id,
--   stargazers_count, forks_count, example_count, has_makefile,
--   has_correct_folder_structure, has_thumbnail, archived, has_releases,
--   pushed_at, created_at, updated_at, of_version, of_version_curated
INSERT INTO repos (
  id, name, full_name, description, type, user_id,
  stargazers_count, forks_count, example_count, has_makefile,
  has_correct_folder_structure, has_thumbnail, archived, has_releases,
  pushed_at, created_at, updated_at, of_version, of_version_curated
) VALUES
  (1, 'ofxSmokeTest', 'smoketest-owner/ofxSmokeTest', 'A fixture addon used only by the smoke test suite.', 'Addon', 1,
   5, 1, 1, 1,
   1, 0, 0, 0,
   '2024-01-01 00:00:00', '2023-01-01 00:00:00', '2024-01-01 00:00:00', '0.12', 1),
  (2, 'ofxSmokeUnsorted', 'smoketest-owner/ofxSmokeUnsorted', 'An unsorted fixture repo.', 'Unsorted', 1,
   0, 0, 0, 0,
   0, 0, 0, 0,
   '2024-01-01 00:00:00', '2023-01-01 00:00:00', '2024-01-01 00:00:00', NULL, 0),
  (3, 'ofxSmokeBanned', 'smoketest-owner/ofxSmokeBanned', 'A banned fixture repo.', 'NonAddon', 1,
   0, 0, 0, 0,
   0, 0, 0, 0,
   '2024-01-01 00:00:00', '2023-01-01 00:00:00', '2024-01-01 00:00:00', NULL, 0);

INSERT INTO categorizations (category_id, repo_id, created_at, updated_at, featured) VALUES
  (1, 1, NOW(), NOW(), 0);
