<?php
require_once __DIR__ . '/../src/bootstrap.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? null, ['admin','employee','focal'], true)) {
  header("Location: " . BASE_URL . "/login");
  exit();
}
require_page_access('roadmaps');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    $_SESSION['flash_error'] = 'Invalid or expired form token. Please try again.';
    header("Location: " . BASE_URL . "/login");
    exit();
}
$role = $_SESSION['role'] ?? 'employee';
$userId = (int)($_SESSION['user_id'] ?? 0);

try {
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS engagement_values (
      id INT AUTO_INCREMENT PRIMARY KEY,
      section_key VARCHAR(8) NOT NULL, -- I, II, ... XI
      question_no INT NOT NULL,
      year INT NOT NULL DEFAULT 2025,
      percent DECIMAL(6,3) DEFAULT NULL,
      created_by INT NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY uniq_entry (section_key, question_no, year),
      CONSTRAINT fk_engagement_values_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
    )
  ");
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS engagement_questions (
      id INT AUTO_INCREMENT PRIMARY KEY,
      section_key VARCHAR(8) NOT NULL,
      question_no INT NOT NULL,
      question_text TEXT NOT NULL,
      created_by INT NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT fk_engagement_questions_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
    )
  ");
} catch (Throwable $e) {}

$sections = [
  'I. Work' => [
    1 => 'I like the kind of work I do.',
    2 => 'I feel a sense of accomplishment in the things I do at work.',
    3 => 'I feel completely involved in my work.',
    4 => 'I get excited about going to work.',
    5 => 'I am often so involved in my work that the day goes by very quickly.',
    6 => 'I am determined to give my best effort at work each day.',
    7 => 'I am completely focused on my job duties when I am at work.',
    8 => 'I understand how my job aligns with TRC\'s Vision',
    9 => 'Overall, I am satisfied with my work.'
  ],
  'II. Work Environment' => [
    1 => 'I have the resources, tools, computers, materials and information I need to perform my job effectively.',
    2 => 'My division/unit has a sufficient number of employees to handle the existing workload.',
    3 => 'I am generally able to balance my work and personal life.',
    4 => 'My organization has a safe work environment',
    5 => 'Overall, I am satisfied with the working environment.'
  ],
  'III. Decision Making' => [
    1 => 'I am given the opportunity/authority to make important decisions that affect my work.',
    2 => 'My supervisor seeks my suggestions/recommendations on how to improve our productivity.',
    3 => 'Employees in my division/unit are encouraged to exchange ideas related to their job.',
    4 => 'I am satisfied with my involvement in decisions that affect my work.',
    5 => 'Overall, I believe that top leaders of DOH are making the right decisions for the success of the organization.'
  ],
  'IV. Quality, Customer Service and Work Performance' => [
    1 => 'My supervisor provides a strong commitment to quality, excellence, and customer service.',
    2 => 'The employees in my division/bureau maintain high performance standards.',
    3 => 'My division/bureau almost always meets our deadlines and achieves our targets.',
    4 => 'Our organization provides services that meet or exceed client/customer expectations.',
    5 => 'I feel inspired to go the extra mile to help our organization succeed.',
    6 => 'I would want to be a client/customer of this organization.'
  ],
  'V. Innovation and Change' => [
    1 => 'I am encouraged to come up with innovative ideas on the job.',
    2 => 'Our organization encourages positive change and new ways of doing things.',
    3 => 'Employees in my organization willingly accept change.',
    4 => 'Management within my organization recognizes strong job performance.'
  ],
  'VI. Reward and Recognition' => [
    1 => 'I receive the recognition I deserve for my contributions.',
    2 => 'Employees in my organization are recognized as individuals.'
  ],
  'VII. My Supervisor' => [
    1 => 'My supervisor shows a genuine interest in the employees in my division/ bureau.',
    2 => 'My supervisor is actively involved in my development.',
    3 => 'My supervisor is fair and consistent in applying the rules to all employees.',
    4 => 'My supervisor keeps me informed about matters that affect me.',
    5 => 'My supervisor provides performance feedback that is fair and useful to me.',
    6 => 'My supervisor provides performance feedback that is timely and on a consistent basis.',
    7 => 'My supervisor promotes an atmosphere of teamwork.',
    8 => 'Overall, my supervisor does a good job.'
  ],
  'VIII. Development/Opportunity' => [
    1 => 'I receive adequate training to do my job effectively.',
    2 => 'I am aware of promotional opportunities in our organization and feel I have a chance for advancement, if I am adequate.',
    3 => 'Performance appraisals and discussion are used to encourage employees to develop their capabilities or help them build on their own strengths.',
    4 => 'I am pleased with the career advancement opportunities available to me.',
    5 => 'My organization is dedicated to my professional development.',
    6 => 'I am satisfied that I have the opportunities to apply my competencies, talents, and expertise.',
    7 => 'I am satisfied with the investment my organization makes in Learning and Development.'
  ],
  'IX. Communication' => [
    1 => 'In my division we communicate frequently and effectively.',
    2 => 'Communications between and among divisions occur on a regular and effective basis.',
    3 => 'Overall communication to employees is effective.',
    4 => 'Information provided by the management are straightforward and honest.',
    5 => 'Information I receive from my supervisor is straightforward and honest.',
    6 => 'A sincere effort is made to get the opinions and thinking of employees in our organization.',
    7 => 'Communication between managers and employees is good in my organization.'
  ],
  'X. Commitment to the Organization' => [
    1 => 'I am committed to working with the organization for the foreseeable future.',
    2 => 'I am proud to work for this organization.',
    3 => 'I am optimistic about the long-term success of our organization in achieving its vision.',
    4 => 'I recommend TRC-LU as a great place to work.',
    5 => 'If offered similar position and compensation at another organization, I would stay at TRC-LU.'
  ],
  'XI. Culture' => [
    1 => 'I have a clear understanding of our organization\'s Vision, Mission and Strategy.',
    2 => 'I believe their organization overall is heading the right direction.',
    3 => 'I have a clear understanding of how my office contributes to the organization\'s strategy.',
    4 => 'All employees are treated with respect in this organization, regardless of level or position.',
    5 => 'Employees with diverse backgrounds are treated with respect in this organization.',
    6 => 'Employees treat each other with respect.',
    7 => 'I am satisfied with the culture of my workplace.',
    8 => 'My peers in my organization take the initiative to help other employees when the need arises.',
    9 => 'There is good coordination between and among offices in my organization to work as a team to achieve our organization\'s targets/objectives.'
  ],
];

// Load custom questions
try {
  $custQ = $pdo->query("SELECT section_key, question_no, question_text FROM engagement_questions ORDER BY question_no");
  foreach ($custQ as $row) {
    $sKey = $row['section_key'];
    foreach ($sections as $fullTitle => $qs) {
      if (preg_replace('/^([IVX]+)\.?.*/', '$1', $fullTitle) === $sKey) {
        $sections[$fullTitle][(int)$row['question_no']] = $row['question_text'];
        break;
      }
    }
  }
} catch (Throwable $e) {}

$action = $_POST['action'] ?? null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action) {
  header("Content-Type: application/json");
  try {
    if ($role === 'admin' && $action === 'save_value') {
      $sec = trim($_POST['section_key'] ?? '');
      $qno = (int)($_POST['question_no'] ?? 0);
      $year = (int)($_POST['year'] ?? 2025);
      $percent = $_POST['percent'] !== '' ? number_format((float)$_POST['percent'], 3, '.', '') : null;
      $stmt = $pdo->prepare("INSERT INTO engagement_values (section_key,question_no,year,percent,created_by)
        VALUES (:s,:q,:y,:p,:uid)
        ON DUPLICATE KEY UPDATE percent=:p, updated_at=CURRENT_TIMESTAMP");
      $stmt->execute([':s'=>$sec, ':q'=>$qno, ':y'=>$year, ':p'=>$percent, ':uid'=>$userId]);
      // Notify all users
      $userInfo = getUserInfo($userId);
      $userIdent = formatUserIdentifier($userInfo);
      $notifMsg = "Admin " . $userIdent . " updated engagement value for Section " . $sec . " Q" . $qno . " (" . $year . ")";
      notifyAdmins('edit', 'Employee Engagement Updated', $notifMsg, null, 'employee_engagement');
      notifyFocals('edit', 'Employee Engagement Updated', $notifMsg, null, 'employee_engagement');
      echo json_encode(['success'=>true]); exit();
    }
    if ($role === 'admin' && $action === 'delete_value') {
      $sec = trim($_POST['section_key'] ?? '');
      $qno = (int)($_POST['question_no'] ?? 0);
      $year = (int)($_POST['year'] ?? 2025);
      $pdo->prepare("DELETE FROM engagement_values WHERE section_key=:s AND question_no=:q AND year=:y")
          ->execute([':s'=>$sec, ':q'=>$qno, ':y'=>$year]);
      // Notify all users
      $userInfo = getUserInfo($userId);
      $userIdent = formatUserIdentifier($userInfo);
      $notifMsg = "Admin " . $userIdent . " deleted engagement value for Section " . $sec . " Q" . $qno . " (" . $year . ")";
      notifyAdmins('edit', 'Employee Engagement Updated', $notifMsg, null, 'employee_engagement');
      notifyFocals('edit', 'Employee Engagement Updated', $notifMsg, null, 'employee_engagement');
      echo json_encode(['success'=>true]); exit();
    }
    if ($role === 'admin' && $action === 'delete_row') {
      $sec = trim($_POST['section_key'] ?? '');
      $qno = (int)($_POST['question_no'] ?? 0);
      
      // Delete from engagement_values (all years)
      $pdo->prepare("DELETE FROM engagement_values WHERE section_key=:s AND question_no=:q")
          ->execute([':s'=>$sec, ':q'=>$qno]);
          
      // Delete from engagement_questions if it exists (custom question)
      $pdo->prepare("DELETE FROM engagement_questions WHERE section_key=:s AND question_no=:q")
          ->execute([':s'=>$sec, ':q'=>$qno]);
          
      echo json_encode(['success'=>true]); exit();
    }
    if ($role === 'admin' && $action === 'add_question') {
      $secTitle = trim($_POST['section_title'] ?? '');
      $qText = trim($_POST['question_text'] ?? '');
      if (!$secTitle || !$qText) { echo json_encode(['success'=>false,'error'=>'Missing fields']); exit(); }

      $secKey = preg_replace('/^([IVX]+)\.?.*/', '$1', $secTitle);

      // Determine next question number
      $max = 0;
      if (isset($sections[$secTitle])) {
        $keys = array_keys($sections[$secTitle]);
        if ($keys) $max = max($keys);
      }
      $stmt = $pdo->prepare("SELECT MAX(question_no) FROM engagement_questions WHERE section_key = :k");
      $stmt->execute([':k'=>$secKey]);
      $dbMax = (int)$stmt->fetchColumn();
      if ($dbMax > $max) $max = $dbMax;

      $nextQ = $max + 1;

      $stmt = $pdo->prepare("INSERT INTO engagement_questions (section_key, question_no, question_text, created_by) VALUES (:k, :n, :t, :u)");
      $stmt->execute([':k'=>$secKey, ':n'=>$nextQ, ':t'=>$qText, ':u'=>$userId]);
      echo json_encode(['success'=>true]); exit();
    }
    echo json_encode(['success'=>false,'error'=>'Invalid action']);
  } catch (Throwable $e) {
    echo json_encode(['success'=>false,'error'=>'Server error']);
  }
  exit();
}

$vals = [];
$yearsWanted = [2025, 2026, 2027, 2028];
try {
  $in = implode(',', array_map('intval', $yearsWanted));
  $q = $pdo->query("SELECT section_key, question_no, year, percent FROM engagement_values WHERE year IN ($in)");
  foreach ($q as $row) {
    $y = (int)$row['year'];
    $sec = $row['section_key'];
    $qno = (int)$row['question_no'];
    $vals[$y][$sec][$qno] = $row['percent'] !== null ? (float)$row['percent'] : null;
  }
} catch (Throwable $e) {}

function avgSectionYear($key, $year, $vals, $sectionsMap) {
  if (!isset($sectionsMap[$key])) return 0.0;
  $sum = 0.0; $cnt = 0;
  foreach ($sectionsMap[$key]['questions'] as $qno => $_) {
    if (isset($vals[$year][$key][$qno]) && $vals[$year][$key][$qno] !== null) { $sum += (float)$vals[$year][$key][$qno]; $cnt++; }
  }
  return $cnt ? round($sum / $cnt, 2) : 0.0;
}

// Build a map with simple keys (I, II, ... XI) for storage/lookups
$simpleSections = [];
foreach ($sections as $full => $qs) {
  $key = preg_replace('/^([IVX]+)\.?.*/', '$1', $full);
  $simpleSections[$key] = ['title'=>$full, 'questions'=>$qs];
}
$sectionKeys = array_keys($simpleSections);

// Compute overall row values: average of section averages (I, II, III...XI) per year
$overallByYear = [];
foreach ($yearsWanted as $y) {
  $sum = 0.0;
  $cnt = 0;
  foreach ($sectionKeys as $k) {
    $sum += avgSectionYear($k, $y, $vals, $simpleSections);
    $cnt++;
  }
  $overallByYear[$y] = $cnt ? round($sum / $cnt, 2) : 0.0;
}

// Dashboard trend data
$chartTrendLabels = array_map('strval', $yearsWanted);
$chartTrendActualData = array_values(array_map(fn($y) => $overallByYear[$y] ?? 0.0, $yearsWanted));
$targetByYear = [2025 => 86.0, 2026 => 86.0, 2027 => 88.0, 2028 => 88.0];
$chartTrendTargetData = array_values(array_map(fn($y) => $targetByYear[$y] ?? 0.0, $yearsWanted));
?>
<!DOCTYPE html>
<html lang="en">
<?php $pageTitle = 'Employee engagement rating'; ?>
<?php $pageStyles = '<link rel="stylesheet" href="' . asset('css/pages/culture_roadmap_employee_engagement.css') . '">';
?>
<?php require PGS_TEMPLATES . '/head_module.php'; ?>
<body>
  <?php include PGS_TEMPLATES . '/navbar.php'; ?>
  <div class="container" pt-110>
    <div class="header-wrap">
      <img src="/PGS/img/employee_logo.png" alt="Employee Engagement" class="header-logo" onerror="this.style.display='none'">
      <div class="header-title">
        <h4>Employee engagement rating</h4>
        <small class="muted">Means of Verification: Employee Engagement Survey Result (<?= (int)$yearsWanted[0] ?>-<?= (int)$yearsWanted[count($yearsWanted)-1] ?>)</small>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header">
        Dashboard: Annual Trend (Actual vs Target)
      </div>
      <div class="card-body">
        <canvas id="engagementChart" height="220"></canvas>
      </div>
    </div>

    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Table: Employee Engagement Survey Percentage Result (Read-only for Employee/Focal)</span>
        <?php if ($role === 'admin'): ?>
          <button id="addQBtn" class="btn btn-sm btn-light text-primary fw-bold">
            <i data-lucide="plus-circle" class="me-1"></i>Add Row
          </button>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered align-middle">
            <thead>
              <tr>
                <th style="width:40px">#</th>
                <th>Item</th>
                <th class="w-120" class="text-center">2025 (%)</th>
                <th class="w-120" class="text-center">2026 (%)</th>
                <th class="w-120" class="text-center">2027 (%)</th>
                <th class="w-120" class="text-center">2028 (%)</th>
                <?php if ($role === 'admin'): ?><th style="width:200px" class="text-center">Actions</th><?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <tr class="section-row">
                <td class="text-center">—</td>
                <td><strong>Overall</strong></td>
                <td class="text-center"><?= number_format($overallByYear[2025] ?? 0, 2) ?></td>
                <td class="text-center"><?= number_format($overallByYear[2026] ?? 0, 2) ?></td>
                <td class="text-center"><?= number_format($overallByYear[2027] ?? 0, 2) ?></td>
                <td class="text-center"><?= number_format($overallByYear[2028] ?? 0, 2) ?></td>
                <?php if ($role === 'admin'): ?><td></td><?php endif; ?>
              </tr>
              <?php foreach ($simpleSections as $key => $obj): ?>
                <tr class="section-row">
                  <td class="text-center">—</td>
                  <td><strong><?= htmlspecialchars($obj['title']) ?></strong></td>
                  <td class="text-center"><?= number_format(avgSectionYear($key, 2025, $vals, $simpleSections), 2) ?></td>
                  <td class="text-center"><?= number_format(avgSectionYear($key, 2026, $vals, $simpleSections), 2) ?></td>
                  <td class="text-center"><?= number_format(avgSectionYear($key, 2027, $vals, $simpleSections), 2) ?></td>
                  <td class="text-center"><?= number_format(avgSectionYear($key, 2028, $vals, $simpleSections), 2) ?></td>
                  <?php if ($role === 'admin'): ?><td></td><?php endif; ?>
                </tr>
                <?php foreach ($obj['questions'] as $qno => $text): $v25 = $vals[2025][$key][$qno] ?? null; $v26 = $vals[2026][$key][$qno] ?? null; $v27 = $vals[2027][$key][$qno] ?? null; $v28 = $vals[2028][$key][$qno] ?? null; ?>
                  <tr>
                    <td class="text-center"><?= (int)$qno ?></td>
                    <td><?= htmlspecialchars($text) ?></td>
                    <td class="text-center"><?= $v25 !== null ? number_format($v25, 2) : '-' ?></td>
                    <td class="text-center"><?= $v26 !== null ? number_format($v26, 2) : '-' ?></td>
                    <td class="text-center"><?= $v27 !== null ? number_format($v27, 2) : '-' ?></td>
                    <td class="text-center"><?= $v28 !== null ? number_format($v28, 2) : '-' ?></td>
                    <?php if ($role === 'admin'): ?>
                      <td class="text-center">
                        <button class="btn btn-xs btn-outline-primary edit" data-sec="<?= htmlspecialchars($key) ?>" data-q="<?= (int)$qno ?>">Edit</button>
                        <button class="btn btn-xs btn-outline-danger delete-row" data-sec="<?= htmlspecialchars($key) ?>" data-q="<?= (int)$qno ?>">Delete</button>
                      </td>
                    <?php endif; ?>
                  </tr>
                <?php endforeach; ?>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="addQModal" tabindex="-1">
    <div class="modal-dialog">
      <form class="modal-content" id="addQForm">
        <div class="modal-header">
          <h5 class="modal-title">Add New Row</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Section</label>
            <select class="form-select" name="section_title" required>
              <option value="">Select Section</option>
              <?php foreach (array_keys($sections) as $t): ?>
                <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Sentence (Question)</label>
            <textarea class="form-control" name="question_text" rows="3" required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    const trendLabels = <?= json_encode($chartTrendLabels) ?>;
    const trendActual = <?= json_encode($chartTrendActualData) ?>;
    const trendTarget = <?= json_encode($chartTrendTargetData) ?>;

    new Chart(document.getElementById('engagementChart'), {
      type: 'line',
      data: {
        labels: trendLabels,
        datasets: [
          {
            label: 'Actual (%)',
            data: trendActual,
            borderColor: '#0d6efd',
            backgroundColor: '#0d6efd',
            borderWidth: 3,
            pointRadius: 4,
            pointHoverRadius: 5,
            tension: 0.25
          },
          {
            label: 'Target (%)',
            data: trendTarget,
            borderColor: '#198754',
            backgroundColor: '#198754',
            borderDash: [6, 6],
            borderWidth: 2,
            pointRadius: 3,
            pointHoverRadius: 4,
            tension: 0
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            min: 0,
            max: 100,
            ticks: { callback: (v)=> Number(v).toFixed(0)+'%' }
          }
        },
        plugins: {
          legend: { display: true, position: 'bottom' },
          tooltip: {
            callbacks: {
              label: (ctx) => `${ctx.dataset.label}: ${Number(ctx.parsed.y).toFixed(2)}%`
            }
          }
        }
      }
    });

    document.getElementById('addQBtn')?.addEventListener('click', () => {
      new bootstrap.Modal(document.getElementById('addQModal')).show();
    });
    document.getElementById('addQForm')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      fd.append('action', 'add_question');
      const r = await fetch(location.href, { method:'POST', body:fd });
      let j = null; try { j = await r.json(); } catch(e){}
      if (j && j.success) {
        await Swal.fire({ icon:'success', title:'Added', text:'New row added successfully.', timer:1200, showConfirmButton:false });
        location.reload();
      } else {
        await Swal.fire({ icon:'error', title:'Failed', text: j?.error || 'Unknown error' });
      }
    });

    document.querySelectorAll('.edit').forEach(btn => {
      btn.addEventListener('click', async () => {
        <?php if ($role !== 'admin'): ?>return;<?php endif; ?>
        const sec = btn.getAttribute('data-sec');
        const q = btn.getAttribute('data-q');
        const { value: year } = await Swal.fire({
          title: 'Select Year',
          input: 'select',
          inputOptions: { '2025':'2025','2026':'2026','2027':'2027','2028':'2028' },
          inputValue: '2025',
          showCancelButton: true
        });
        if (year === undefined) return;
        const { value: val } = await Swal.fire({
          title: 'Enter percentage ('+year+')',
          input: 'text',
          inputAttributes: { placeholder: 'e.g., 82.50' },
          showCancelButton: true
        });
        if (val === undefined) return;
        const fd = new FormData(); fd.append('action','save_value'); fd.append('section_key',sec); fd.append('question_no',q); fd.append('year',year); fd.append('percent', val);
        const r = await fetch(location.href, { method:'POST', body:fd }); let j=null; try{ j=await r.json(); }catch(e){}
        if (j && j.success) { await Swal.fire({ icon:'success', title:'Saved', timer:1000, showConfirmButton:false }); location.reload(); } else await Swal.fire({ icon:'error', title:'Failed' });
      });
    });
    document.querySelectorAll('.delete-row').forEach(btn => {
      btn.addEventListener('click', async () => {
        <?php if ($role !== 'admin'): ?>return;<?php endif; ?>
        const sec = btn.getAttribute('data-sec');
        const q = btn.getAttribute('data-q');
        
        const c = await Swal.fire({ 
          icon:'warning', 
          title:'Delete Row?', 
          text: 'This will delete the question and all associated data for all years. This action cannot be undone.',
          showCancelButton:true, 
          confirmButtonText:'Delete Row',
          confirmButtonColor: '#dc3545'
        }); 
        if (!c.isConfirmed) return;
        
        const fd = new FormData(); 
        fd.append('action','delete_row'); 
        fd.append('section_key',sec); 
        fd.append('question_no',q);
        
        const r = await fetch(location.href, { method:'POST', body:fd }); 
        let j=null; try{ j=await r.json(); }catch(e){}
        
        if (j && j.success) { 
          await Swal.fire({ icon:'success', title:'Deleted', timer:900, showConfirmButton:false }); 
          location.reload(); 
        } else { 
          await Swal.fire({ icon:'error', title:'Failed' }); 
        }
      });
    });
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
