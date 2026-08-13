<?php
require_once __DIR__ . '/src/bootstrap.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? null) !== 'admin') {
  header("Location: " . BASE_URL . "/login");
  exit();
}

$dbHostDisplay = isset($host) ? (string)$host : 'localhost';
$dbNameDisplay = isset($dbname) ? (string)$dbname : 'planning';

function find_mysqldump_path(): ?string {
  $candidates = [
    'C:\\xampp\\mysql\\bin\\mysqldump.exe',
    'C:\\xampp\\mysql\\bin\\mysqldump',
    'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
    'mysqldump'
  ];
  foreach ($candidates as $p) {
    if ($p === 'mysqldump') return $p;
    if (is_file($p)) return $p;
  }
  return null;
}

function write_mysql_defaults_file(string $host, string $user, string $pass): string {
  $tmp = tempnam(sys_get_temp_dir(), 'pgs_mysql_');
  $ini = "[client]\n" .
    "host={$host}\n" .
    "user={$user}\n" .
    "password={$pass}\n";
  file_put_contents($tmp, $ini);
  return $tmp;
}

function stream_file_download(string $filePath, string $downloadName): void {
  header('Content-Type: application/sql; charset=utf-8');
  header('Content-Disposition: attachment; filename="' . $downloadName . '"');
  header('Content-Length: ' . filesize($filePath));
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
  readfile($filePath);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    http_response_code(403);
    echo "Invalid or expired form token.";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'backup') {
  $mysqldump = find_mysqldump_path();
  if (!$mysqldump) {
    http_response_code(500);
    echo "mysqldump not found.";
    exit();
  }

  $dbHost = isset($host) ? (string)$host : 'localhost';
  $dbName = isset($dbname) ? (string)$dbname : 'planning';
  $dbUser = isset($username) ? (string)$username : 'root';
  $dbPass = isset($password) ? (string)$password : '';

  $defaultsFile = null;
  $dumpPath = null;
  try {
    $defaultsFile = write_mysql_defaults_file($dbHost, $dbUser, $dbPass);
    $dumpPath = tempnam(sys_get_temp_dir(), 'pgs_dump_');
    if (substr($dumpPath, -4) !== '.sql') {
      $dumpPathSql = $dumpPath . '.sql';
      @unlink($dumpPath);
      $dumpPath = $dumpPathSql;
    }

    $cmd = [];
    $cmd[] = escapeshellarg($mysqldump);
    $cmd[] = '--defaults-extra-file=' . escapeshellarg($defaultsFile);
    $cmd[] = '--single-transaction';
    $cmd[] = '--routines';
    $cmd[] = '--triggers';
    $cmd[] = '--events';
    $cmd[] = '--default-character-set=utf8mb4';
    $cmd[] = '--databases';
    $cmd[] = escapeshellarg($dbName);
    $cmd[] = '--result-file=' . escapeshellarg($dumpPath);
    $command = implode(' ', $cmd);

    $output = [];
    $exitCode = 0;
    exec($command . ' 2>&1', $output, $exitCode);
    if ($exitCode !== 0 || !is_file($dumpPath) || filesize($dumpPath) === 0) {
      http_response_code(500);
      echo "Backup failed.\n" . implode("\n", $output);
      exit();
    }

    $downloadName = $dbName . '_backup_' . date('Y-m-d_His') . '.sql';
    stream_file_download($dumpPath, $downloadName);
    exit();
  } finally {
    if ($defaultsFile && is_file($defaultsFile)) {
      @unlink($defaultsFile);
    }
    if ($dumpPath && is_file($dumpPath)) {
      @unlink($dumpPath);
    }
  }
}
$pageTitle = 'Backup and Restore';
$pageStyles = <<<'STYLES'
body { background-color: #f5f7fa; color: #2c3e50; }
.card { border: none; border-radius: 1rem; box-shadow: 0 6px 18px rgba(0,0,0,.06); }
.card-header { background: #0b4aa2; color: #fff; border-radius: 1rem 1rem 0 0; font-weight: 700; letter-spacing: .04em; }
.btn-primary-custom { background-color: #0b4aa2; border-color: #0b4aa2; color: #fff; }
.btn-primary-custom:hover { background-color: #083a7f; border-color: #083a7f; color: #fff; }
.page-hero { border-radius: 1rem; padding: 22px 22px; background: linear-gradient(135deg, #083a7f 0%, #0b4aa2 45%, #1b7fd6 100%); color: #fff; box-shadow: 0 10px 24px rgba(11,74,162,.18); }
.page-hero h1 { font-size: 1.35rem; font-weight: 800; letter-spacing: .02em; margin: 0 0 6px; }
.page-hero p { margin: 0; opacity: .92; }
.chip { display: inline-flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 999px; background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.22); }
.mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
pre { background: #0b1220; color: #e5e7eb; padding: 12px 14px; border-radius: 12px; margin: 0; white-space: pre-wrap; word-break: break-word; }
STYLES;
?>
<!DOCTYPE html>
<html lang="en">
<?php require PGS_TEMPLATES . '/head.php'; ?>
<body>
  <?php include PGS_TEMPLATES . '/navbar.php'; ?>
  <div class="page-wrapper">

  <main class="container flex-grow-1" pt-110>
    <div class="page-hero mb-4">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
          <h1>Backup and Restore</h1>
          <p>Create a database backup and follow the guide to restore when needed.</p>
        </div>
        <div class="chip">
          <span class="fw-semibold">Database</span>
          <span class="mono"><?= h($dbNameDisplay) ?></span>
          <span class="opacity-75">(@ <?= h($dbHostDisplay) ?>)</span>
        </div>
      </div>
    </div>

    <div class="row g-4">
      <div class="col-12 col-lg-5">
        <div class="card h-100">
          <div class="card-header">Backup</div>
          <div class="card-body">
            <p class="mb-3">
              Download a full SQL backup of the database. Store it in a safe place.
            </p>
            <div class="alert alert-info mb-3">
              Backup uses <span class="mono">mysqldump</span>. Ensure MySQL is running in XAMPP.
            </div>
            <form method="POST" class="d-grid gap-2">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="backup">
              <button type="submit" class="btn btn-primary-custom btn-lg">Backup Now</button>
            </form>
          </div>
        </div>
      </div>

      <div class="col-12 col-lg-7">
        <div class="card">
          <div class="card-header">Restore Guide</div>
          <div class="card-body">
            <div class="alert alert-warning">
              Restoring will overwrite data. Always create a fresh backup before restoring.
            </div>

            <div class="accordion" id="restoreAccordion">
              <div class="accordion-item">
                <h2 class="accordion-header" id="headingPhpMyAdmin">
                  <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePhpMyAdmin" aria-expanded="true" aria-controls="collapsePhpMyAdmin">
                    Restore using phpMyAdmin (Recommended)
                  </button>
                </h2>
                <div id="collapsePhpMyAdmin" class="accordion-collapse collapse show" aria-labelledby="headingPhpMyAdmin" data-bs-parent="#restoreAccordion">
                  <div class="accordion-body">
                    <ol class="mb-0">
                      <li>Open phpMyAdmin: <span class="mono">http://localhost/phpmyadmin</span></li>
                      <li>Select the database: <span class="mono"><?= h($dbNameDisplay) ?></span></li>
                      <li>Go to the <span class="fw-semibold">Import</span> tab</li>
                      <li>Choose the downloaded <span class="mono">.sql</span> file</li>
                      <li>Click <span class="fw-semibold">Go</span> to start the import</li>
                    </ol>
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h2 class="accordion-header" id="headingCli">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCli" aria-expanded="false" aria-controls="collapseCli">
                    Restore using Command Line (Advanced)
                  </button>
                </h2>
                <div id="collapseCli" class="accordion-collapse collapse" aria-labelledby="headingCli" data-bs-parent="#restoreAccordion">
                  <div class="accordion-body">
                    <p class="mb-2">Open Command Prompt / PowerShell and run:</p>
                    <pre><span class="mono">C:\xampp\mysql\bin\mysql.exe -u root <?= DB_PASS === '' ? '' : '-p' ?> <?= h($dbNameDisplay) ?> &lt; C:\path\to\your_backup.sql</span></pre>
                    <div class="mt-3 small text-muted">
                      If your MySQL password is empty (default XAMPP), omit <span class="mono">-p</span>.
                    </div>
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h2 class="accordion-header" id="headingNotes">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseNotes" aria-expanded="false" aria-controls="collapseNotes">
                    Notes / Troubleshooting
                  </button>
                </h2>
                <div id="collapseNotes" class="accordion-collapse collapse" aria-labelledby="headingNotes" data-bs-parent="#restoreAccordion">
                  <div class="accordion-body">
                    <ul class="mb-0">
                      <li>If the backup fails, make sure <span class="mono">mysqldump</span> exists in <span class="mono">C:\xampp\mysql\bin</span>.</li>
                      <li>Ensure MySQL is running in XAMPP Control Panel.</li>
                      <li>Large databases may take time to export/import.</li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
<?php
$pageScripts = '';
?>
  </div>
  <?php include PGS_TEMPLATES . '/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?php if (!empty($pageScripts)): ?><?= $pageScripts ?><?php endif; ?>
</body>
</html>
<?php

