# ofxAddonsSite

The site behind [ofxaddons.danoli3.com](https://ofxaddons.danoli3.com) - a directory of [openFrameworks](https://openframeworks.cc) addons. Plain PHP, no framework, PDO/MySQL, jQuery for the bits that need it client-side.

Addon data itself is crawled separately by [danoli3/ofxAddons](https://github.com/danoli3/ofxAddons), which publishes a daily Github Release; this site just consumes it (via webhook, with a scheduled fallback pull) and lets people categorize, moderate, and self-manage their own listings from there.

## Layout

```
index.php              front controller / router
.htaccess               rewrites everything without a matching file to index.php
app/
  env.php, db.php        .env loader, PDO singleton
  auth.php                Github OAuth (manual curl, no Composer deps), session helpers
  sync.php                applies a crawl snapshot into repos/users
  ai.php                  README fetch + OpenAI description generation
  audit.php               admin action logging
  routes.php              route table
  controllers/            categories, addons, unsorted, contributors, admin, my_addons, webhooks, session
  views/                  PHP templates
  assets/                 css/js/img - self-contained, no build step
cron/
  sync_from_release.php   fallback: pulls the latest crawl release directly, in case a webhook call ever fails
```

## Running it

Needs PHP with `pdo_mysql` and `curl`, and a MySQL database matching the `repos`/`users`/`categories`/`categorizations`/`admin_logs` tables. Config comes from a `.env` file at the repo root (never committed):

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
```

Point a webserver at this directory (or `php -S localhost:8080 router.php` locally with a small router script that falls back to `index.php` for anything not an existing file), load the schema, and it should just run - no Composer, no build step, no asset pipeline.

## Who can do what

- Anyone can browse.
- Any Github login gets a session and access to `/my/addons` - repos they actually own (matched by Github account id, not just login name) can be categorized, described, hidden from public listings, or given a custom thumbnail/GIF.
- `admin`-flagged accounts additionally get `/admin/repos` (categorize the full queue, ban false positives, bulk import/export) and `/admin/log`.
