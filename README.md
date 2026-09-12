# ofxAddonsSite

The site behind [ofxaddons.danoli3.com](https://ofxaddons.danoli3.com) - a directory of [openFrameworks](https://openframeworks.cc) addons. Plain PHP, no framework, PDO/MySQL, jQuery for the bits that need it client-side.

Addon data itself is crawled separately by [danoli3/ofxAddons](https://github.com/danoli3/ofxAddons), which publishes a daily Github Release; this site just consumes it (via webhook, with a scheduled fallback pull) and lets people categorize, moderate, and self-manage their own listings from there.

## Layout

```
index.php              front controller (real webserver rewrites into this - see .htaccess)
router.php             front controller for `php -S` (local dev / tests/smoke.sh - no rewrite rules of its own)
.htaccess               rewrites everything without a matching file to index.php
app/
  env.php, db.php        .env loader, PDO singleton
  auth.php                Github OAuth (manual curl, no Composer deps), session helpers
  sync.php                applies a crawl snapshot into repos/users
  ai.php                  README fetch + OpenAI description generation
  audit.php               admin action logging
  security_ban.php        per-IP auto-ban (scanner signatures, repeated auth failures)
  thumbnail_scan.php      detects an unreplaced ofxAddonTemplate placeholder thumbnail
  routes.php              route table
  controllers/            categories, addons, unsorted, contributors, admin, my_addons, webhooks, session
  views/                  PHP templates
  assets/                 css/js/img - self-contained, no build step
cron/
  sync_from_release.php   fallback: pulls the latest crawl release directly, in case a webhook call ever fails
tests/
  schema.sql, seed.sql    DB schema (dumped from production) + fixture data for local dev and CI
  smoke.sh                HTTP-level smoke tests - see .github/workflows/smoke-tests.yml
```

## Running it

Needs PHP with `pdo_mysql` and `curl`, and a MySQL database matching `tests/schema.sql` (the `repos`/`users`/`categories`/`categorizations`/`admin_logs`/`ai_triage_queue`/`ai_triage_denials`/`security_bans`/`security_failures` tables - that file is dumped from production, so it's the actual source of truth, not a guess). Config comes from a `.env` file at the repo root (never committed):

```
DB_HOST=...
DB_NAME=...
DB_USERNAME=...
DB_PASSWORD=...
GITHUB_CLIENT_ID=...       # Github OAuth App, for admin/owner login
GITHUB_CLIENT_SECRET=...
GITHUB_TOKEN=...           # personal access token, used for README fetches
OPENAI_API_KEY=...         # optional - powers the "Generate description" button
SYNC_SECRET=...            # shared secret the crawler repo's webhook authenticates with
AI_TRIAGE_API_KEY=...      # bearer key for the local-model /api/triage/* endpoints
```

Point a webserver at this directory (or `php -S localhost:8080 router.php` locally - that router script already exists at the repo root), load `tests/schema.sql`, and it should just run - no Composer, no build step, no asset pipeline.

## Tests

`tests/smoke.sh` boots the app against a real (throwaway) MySQL database seeded from `tests/schema.sql` + `tests/seed.sql` and hits a curated list of routes, asserting both the status code and that each request completes within a time budget - see `.github/workflows/smoke-tests.yml`, which runs it on every push/PR. The timing check matters as much as the status code: a couple of real bugs here were pages that hung (an unbounded live-Github-call-per-repo loop) rather than ones that 500'd, which a status-code-only check wouldn't have caught.

Run it locally:

```
mysql -uroot -e "CREATE DATABASE ofxaddons_test"
mysql -uroot ofxaddons_test < tests/schema.sql
mysql -uroot ofxaddons_test < tests/seed.sql
# .env pointed at ofxaddons_test
php -S 127.0.0.1:8080 router.php &
BASE_URL=http://127.0.0.1:8080 tests/smoke.sh
```

## Who can do what

- Anyone can browse.
- Any Github login gets a session and access to `/my/addons` - repos they actually own (matched by Github account id, not just login name) can be categorized, described, hidden from public listings, or given a custom thumbnail/GIF.
- `admin`-flagged accounts additionally get `/admin/repos` (categorize the full queue, ban false positives, bulk import/export) and `/admin/log`.
