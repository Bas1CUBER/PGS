<?php
if (!defined('BASE_URL')) define('BASE_URL', 'http://localhost:8080/PGS');
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'planning');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('UPLOAD_DIR')) define('UPLOAD_DIR', __DIR__ . '/uploads');
if (!defined('ITEMS_PER_PAGE')) define('ITEMS_PER_PAGE', 20);
// 'dev' = per-page CSS files (assets/css/app.css + pages/*.css)
// 'prod' = single built file (assets/css/all.css) — run `php build_css.php` first
if (!defined('PGS_CSS_MODE')) define('PGS_CSS_MODE', 'dev');
