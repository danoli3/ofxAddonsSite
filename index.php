<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/app/env.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/view.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/sync.php';
require_once __DIR__ . '/app/ai.php';
require_once __DIR__ . '/app/audit.php';
require_once __DIR__ . '/app/controllers/categories.php';
require_once __DIR__ . '/app/controllers/addons.php';
require_once __DIR__ . '/app/controllers/unsorted.php';
require_once __DIR__ . '/app/controllers/contributors.php';
require_once __DIR__ . '/app/controllers/pages.php';
require_once __DIR__ . '/app/controllers/session.php';
require_once __DIR__ . '/app/controllers/admin.php';
require_once __DIR__ . '/app/controllers/webhooks.php';
require_once __DIR__ . '/app/controllers/my_addons.php';
require_once __DIR__ . '/app/routes.php';

ofx_dispatch();
