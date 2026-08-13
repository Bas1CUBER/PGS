<?php
require_once __DIR__ . '/src/bootstrap.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? null, ['admin','employee','focal'], true)) {
  header("Location: " . BASE_URL . "/login");
  exit();
}
$role = $_SESSION['role'] ?? null;

$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) { @mkdir($dataDir, 0755, true); }
$jsonFile = $dataDir . '/charter_statements.json';

function default_statements() {
  return [
    'vision' => 'A prime TRC in Northern Luzon providing collaborative healthcare for substance dependents and the vulnerables by 2029.',
    'mission' => 'We Transform and Reach Communities towards a sustainable and inclusive treatment and rehabilitative care.',
    'core_values' => ['Compassion','Rectitude','Teamwork']
  ];
}

function load_statements($file) {
  if (!is_file($file)) return default_statements();
  $raw = @file_get_contents($file);
  $data = json_decode($raw, true);
  if (!$data) return default_statements();
  $data['core_values'] = array_values(array_filter(array_map('trim', $data['core_values'] ?? []), fn($v)=>$v!==''));
  return $data + default_statements();
}

function save_statements($file, $data) {
  return @file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) !== false;
}

$savedMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    $_SESSION['flash_error'] = 'Invalid or expired form token. Please try again.';
    header("Location: " . BASE_URL . "/about_charter_statements");
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'admin') {
  $action = $_POST['action'] ?? '';
  if ($action === 'save_charter') {
    $vision = trim($_POST['vision'] ?? '');
    $mission = trim($_POST['mission'] ?? '');
    $coreValuesRaw = trim($_POST['core_values'] ?? '');
    $coreValues = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $coreValuesRaw)), fn($v)=>$v!==''));
    $data = [
      'vision' => $vision ?: default_statements()['vision'],
      'mission' => $mission ?: default_statements()['mission'],
      'core_values' => !empty($coreValues) ? $coreValues : default_statements()['core_values']
    ];
    if (save_statements($jsonFile, $data)) {
      $savedMsg = 'success:Charter statements updated.';
    } else {
      $savedMsg = 'error:Failed to save. Check file permissions.';
    }
  }
}

$statements = load_statements($jsonFile);

function core_value_icon($value) {
  $v = strtolower(trim((string)$value));
  if (strpos($v, 'compassion') !== false) return 'hand-heart';
  if (strpos($v, 'rectitude') !== false) return 'scale';
  if (strpos($v, 'teamwork') !== false || strpos($v, 'team work') !== false) return 'users';
  return 'star';
}
?>

<?php
$pageTitle = 'About - Charter Statements';

$pageStyles = <<<'CSS'
    :root {
      --pgs-gold: #d4a843;
      --pgs-gold-light: #e8c76a;
      --pgs-gold-dark: #b8922e;
      --pgs-blue: #0b4aa2;
      --pgs-blue-dark: #083a7f;
    }
    
    body {
      background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
      color:#2c3e50;
    }
    
    .hero-section {
      background: linear-gradient(135deg, var(--pgs-blue) 0%, var(--pgs-blue-dark) 50%, #041d42 100%);
      padding: 160px 0 100px;
      position: relative;
      overflow: visible;
      min-height: 320px;
    }
    
    .hero-section::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="40" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="2"/></svg>') repeat;
      background-size: 60px;
      opacity: 0.3;
    }
    
    .hero-content {
      position: relative;
      z-index: 2;
    }
    
    .hero-title {
      font-size: 3rem;
      font-weight: 800;
      color: #fff;
      text-shadow: 0 4px 15px rgba(0,0,0,0.5), 0 0 30px rgba(0,0,0,0.3);
      letter-spacing: 0.08em;
      margin-bottom: 0.8rem;
    }
    
    .hero-subtitle {
      font-size: 1.3rem;
      color: #fff;
      font-weight: 600;
      text-shadow: 0 2px 10px rgba(0,0,0,0.3);
    }
    
    .hero-lamp {
      position: absolute;
      right: 8%;
      bottom: 50px;
      z-index: 1;
    }
    
    .hero-lamp img {
      width: 160px;
      height: 250px;
      filter: drop-shadow(0 8px 25px rgba(0,0,0,0.4));
    }
    
    .content-section {
      margin-top: -40px;
      position: relative;
      z-index: 10;
    }
    
    .main-card {
      border: none;
      border-radius: 20px;
      background: #fff;
      box-shadow: 0 20px 60px rgba(0,0,0,0.15);
      overflow: hidden;
    }
    
    .card-header-custom {
      background: linear-gradient(135deg, var(--pgs-blue) 0%, var(--pgs-blue-dark) 100%);
      color: #fff;
      padding: 25px 30px;
      font-weight: 800;
      font-size: 1.5rem;
      letter-spacing: 0.05em;
      text-align: center;
      position: relative;
    }
    
    .card-header-custom::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, var(--pgs-gold), var(--pgs-gold-light), var(--pgs-gold));
    }
    
    .statement-card {
      border-radius: 20px;
      background: #ffffff;
      box-shadow: 0 10px 40px rgba(0,0,0,0.1);
      overflow: hidden;
      height: 100%;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      border: 1px solid rgba(212, 168, 67, 0.2);
    }
    
    .statement-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 20px 50px rgba(0,0,0,0.15);
    }
    
    .statement-header {
      background: linear-gradient(135deg, var(--pgs-gold) 0%, var(--pgs-gold-dark) 100%);
      color: #fff;
      padding: 20px 25px;
      font-weight: 800;
      letter-spacing: 0.08em;
      font-size: 1.3rem;
      text-align: center;
      text-shadow: 0 2px 4px rgba(0,0,0,0.2);
      position: relative;
    }
    
    .statement-header::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 3px;
      background: rgba(255,255,255,0.3);
    }
    
    .statement-header i {
      margin-right: 10px;
      opacity: 0.9;
    }
    
    .statement-body {
      padding: 30px;
      min-height: 280px;
      display: flex;
      align-items: flex-start;
      background: linear-gradient(180deg, #fff 0%, #fafafa 100%);
    }
    
    .lead-text {
      font-size: 1.2rem;
      line-height: 2;
      color: #2d3748;
      font-weight: 500;
    }
    
    .value-badge {
      display: flex;
      justify-content: center;
      align-items: center;
      background: rgba(212, 168, 67, 0.2);
      color: #6b5200;
      border: 1px solid rgba(212, 168, 67, 0.25);
      padding: 18px 30px;
      border-radius: 15px;
      margin: 12px 0;
      font-weight: 700;
      font-size: 1.4rem;
      box-shadow: 0 2px 8px rgba(212, 168, 67, 0.12);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
      width: 100%;
    }
    
    .value-badge:hover {
      transform: scale(1.02);
      box-shadow: 0 4px 14px rgba(212, 168, 67, 0.18);
    }
    
    .value-badge i {
      margin-right: 12px;
      font-size: 1.3rem;
    }
    
    .form-control {
      font-size: 1.05rem;
      border: 2px solid #e9ecef;
      border-radius: 12px;
      padding: 12px 16px;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    
    .form-control:focus {
      border-color: var(--pgs-gold);
      box-shadow: 0 0 0 3px rgba(212, 168, 67, 0.2);
    }
    
    .btn-gold {
      background: linear-gradient(135deg, var(--pgs-gold) 0%, var(--pgs-gold-dark) 100%);
      border: none;
      color: #fff;
      font-weight: 600;
      padding: 10px 24px;
      border-radius: 10px;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .btn-gold:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(212, 168, 67, 0.4);
      color: #fff;
    }
    
    .btn-outline-gold {
      border: 2px solid var(--pgs-gold);
      color: var(--pgs-gold-dark);
      font-weight: 600;
      padding: 10px 24px;
      border-radius: 10px;
      background: transparent;
      transition: all 0.2s ease;
    }
    
    .btn-outline-gold:hover {
      background: var(--pgs-gold);
      color: var(--pgs-gold-dark);
    }
    
    @media (max-width: 991px) {
      .statement-body { min-height: auto; }
      .lead-text { font-size: 1.1rem; }
      .hero-title { font-size: 2rem; }
      .hero-section { padding: 120px 0 80px; min-height: 250px; }
      .hero-lamp { display: none; }
    }
    
    html, body {
      height: auto;
      min-height: 100vh;
    }
    
    body {
      display: flex;
      flex-direction: column;
    }
    
    .page-wrapper {
      flex: 1;
    }
    
    .form-note { color:#6c757d; font-size:.9rem; }
    
    .decorative-line {
      width: 80px;
      height: 4px;
      background: linear-gradient(90deg, var(--pgs-gold), var(--pgs-gold-light));
      border-radius: 2px;
      margin: 0 auto 20px;
    }
CSS;

?>
<!DOCTYPE html>
<html lang="en">
<?php require PGS_TEMPLATES . '/head.php'; ?>
<body>
  <?php include PGS_TEMPLATES . '/navbar.php'; ?>
  <div class="page-wrapper">

<!-- Hero Section -->
  <div class="hero-section">
    <div class="container">
      <div class="hero-content text-center">
        <h1 class="hero-title">Charter Statements</h1>
        <p class="hero-subtitle">Our Vision, Mission & Core Values</p>
        <div class="decorative-line"></div>
      </div>
    </div>
    <div class="hero-lamp">
      <img src="img/lamp.png" alt="PGS Lamp">
    </div>
  </div>
  
  <!-- Content Section -->
  <div class="container content-section">
    <div class="main-card">
        <?php if (!empty($savedMsg)):
          $isErr = str_starts_with($savedMsg, 'error:');
          $text = substr($savedMsg, strpos($savedMsg, ':')+1);
        ?>
          <div class="alert <?= $isErr ? 'alert-danger' : 'alert-success' ?>"><?= h($text) ?></div>
        <?php endif; ?>
        <div class="p-4">
        <?php if ($role === 'admin'): ?>
          <div class="text-end mb-4">
            <button class="btn btn-gold" id="editToggle"><i data-lucide="pencil" class="me-2"></i>Edit Statements</button>
          </div>
        <?php endif; ?>
        <div class="row g-4" id="viewMode">
          <div class="col-lg-4">
            <div class="statement-card">
              <div class="statement-header"><i data-lucide="eye"></i>Vision</div>
              <div class="statement-body">
                <p class="lead-text mb-0"><?= nl2br(h($statements['vision'] ?? '')) ?></p>
              </div>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="statement-card">
              <div class="statement-header"><i data-lucide="target"></i>Mission</div>
              <div class="statement-body">
                <p class="lead-text mb-0"><?= nl2br(h($statements['mission'] ?? '')) ?></p>
              </div>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="statement-card">
              <div class="statement-header"><i data-lucide="heart"></i>Core Values</div>
              <div class="statement-body flex-column">
                <?php foreach ($statements['core_values'] as $v): ?>
                  <div class="value-badge"><i data-lucide="<?= h(core_value_icon($v)) ?>"></i><?= h($v) ?></div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
        <?php if ($role === 'admin'): ?>
        <form id="editForm" method="POST" class="row g-4" style="display:none;">
            <?= csrf_field() ?>
          <input type="hidden" name="action" value="save_charter">
          <div class="col-lg-4">
            <div class="statement-card">
              <div class="statement-header"><i data-lucide="eye"></i>Vision</div>
              <div class="statement-body">
                <textarea name="vision" class="form-control" rows="6"><?= h($statements['vision']) ?></textarea>
              </div>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="statement-card">
              <div class="statement-header"><i data-lucide="target"></i>Mission</div>
              <div class="statement-body">
                <textarea name="mission" class="form-control" rows="6"><?= h($statements['mission']) ?></textarea>
              </div>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="statement-card">
              <div class="statement-header"><i data-lucide="heart"></i>Core Values</div>
              <div class="statement-body">
                <textarea name="core_values" class="form-control" rows="6"><?=
                  h(implode(PHP_EOL, $statements['core_values']))
                ?></textarea>
                <div class="form-note mt-2">Enter one value per line.</div>
              </div>
            </div>
          </div>
          <div class="col-12 d-flex justify-content-end gap-2 mt-4">
            <button type="button" class="btn btn-outline-gold" id="cancelBtn"><i data-lucide="x" class="me-2"></i>Cancel</button>
            <button type="submit" class="btn btn-gold"><i data-lucide="save" class="me-2"></i>Save Changes</button>
          </div>
        </form>
        <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
<?php if ($role === 'admin'): ?>
  <script src="<?= asset('js/pages/about_charter_statements_1.js') ?>"></script>
  <?php endif; ?>
  </div>
  <?php include PGS_TEMPLATES . '/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?php if (!empty($pageScripts)): ?><?= $pageScripts ?><?php endif; ?>
</body>
</html>
<?php

