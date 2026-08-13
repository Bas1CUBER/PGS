<?php
require_once __DIR__ . '/src/bootstrap.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? null, ['admin', 'employee', 'focal'], true)) {
    header("Location: " . BASE_URL . "/login");
    exit();
}

require_page_access('scorecard');

$tablesOk = false;
try {
    $need = ['impact_scorecard_measures', 'impact_scorecard_years', 'impact_scorecard_values'];
    $ok = true;
    foreach ($need as $t) {
        $res = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($t) . "'");
        if (!$res || $res->num_rows === 0) {
            $ok = false;
            break;
        }
    }
    $tablesOk = $ok;
} catch (Throwable $e) {
    $tablesOk = false;
}

$years = [];
$measures = [];
$valuesMap = []; // [measure_id][year_id] => value
if ($tablesOk) {
    $yq = $conn->query("SELECT id, year FROM impact_scorecard_years ORDER BY sort_order ASC, year ASC");
    while ($yq && ($r = $yq->fetch_assoc())) {
        $years[] = $r;
    }

    $mq = $conn->query("SELECT id, impact, measure, bl FROM impact_scorecard_measures ORDER BY sort_order ASC, id ASC");
    while ($mq && ($r = $mq->fetch_assoc())) {
        $measures[] = $r;
    }

    $vq = $conn->query("SELECT measure_id, year_id, value FROM impact_scorecard_values");
    while ($vq && ($r = $vq->fetch_assoc())) {
        $mid = (int)$r['measure_id'];
        $yid = (int)$r['year_id'];
        if (!isset($valuesMap[$mid])) {
            $valuesMap[$mid] = [];
        }
        $valuesMap[$mid][$yid] = $r['value'];
    }
}

$pageStyles = '<style>
    html,
    body {
        font-family: \'Inter\', \'Segoe UI\', sans-serif;
        background-color: #f5f7fa;
        color: #2c3e50;
        height: 100%;
        margin: 0;
        padding-top: 20px;
    }

    .page-wrapper {
        display: flex;
        flex-direction: column;
        min-height: 100vh;
    }

    main {
        flex: 1;
    }

    .card {
        border: none;
        border-radius: 1rem;
        background-color: #ffffff;
    }

    .card-body {
        padding: 2rem;
    }

    .scorecard-title {
        background: #0b4aa2;
        color: #fff;
        text-align: center;
        font-weight: 700;
        letter-spacing: 0.04em;
        padding: 14px 16px;
        border-radius: 1rem 1rem 0 0;
    }

    .scorecard-table thead th {
        background-color: #f0f2f5;
        color: #34495e;
        font-weight: 600;
    }

    .scorecard-table td,
    .scorecard-table th {
        vertical-align: middle;
        padding: 1rem;
        border-color: #e9ecef;
    }
    .chart-box { height: 220px; }
    .chart-box canvas { width: 100% !important; height: 100% !important; }
</style>';

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($pageTitle ?? 'PGS — TRC DOH') ?></title>
  <link rel="icon" href="<?= BASE_URL ?>/assets/img/logo.png" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css?v=3">
  <?php if (!empty($pageStyles)): ?><?php if (str_starts_with(trim($pageStyles), '<')): ?><?= $pageStyles ?><?php else: ?><style><?= $pageStyles ?></style><?php endif; ?><?php endif; ?>
</head>
<body>
  <?php include PGS_TEMPLATES . '/navbar.php'; ?>
  <div class="page-wrapper">

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<div class="container my-5" style="padding-top: 70px;">
    <?php if ($tablesOk): ?>
    <?php
        $yearIds = array_map(function($y){ return (int)$y['id']; }, $years);
        $yearLabels = array_map(function($y){ return (string)$y['year']; }, $years);
        $sumByYear = array_fill_keys($yearIds, 0.0);
        $countNumericByYear = array_fill_keys($yearIds, 0);
        $countNonEmptyByYear = array_fill_keys($yearIds, 0);
        foreach ($valuesMap as $mid => $rowVals) {
            foreach ($rowVals as $yid => $val) {
                $yid = (int)$yid;
                if (!in_array($yid, $yearIds, true)) continue;
                $raw = trim((string)$val);
                if ($raw !== '') {
                    $countNonEmptyByYear[$yid]++;
                    $num = $raw;
                    if (substr($num, -1) === '%') $num = substr($num, 0, -1);
                    if (is_numeric($num)) {
                        $f = (float)$num;
                        $sumByYear[$yid] += $f;
                        $countNumericByYear[$yid]++;
                    }
                }
            }
        }
        $avgByYear = [];
        foreach ($yearIds as $yid) {
            $avgByYear[$yid] = $countNumericByYear[$yid] > 0 ? ($sumByYear[$yid] / $countNumericByYear[$yid]) : 0;
        }
        $avgSeries = [];
        $sumSeries = [];
        $countSeries = [];
        // Build series for "Access to all level of Care" (best-effort match)
        $targetId = null;
        foreach ($measures as $m) {
            $hay = strtolower(($m['measure'] ?? '') . ' ' . ($m['impact'] ?? ''));
            if (strpos($hay, 'access') !== false && strpos($hay, 'level') !== false && strpos($hay, 'care') !== false) {
                $targetId = (int)$m['id'];
                break;
            }
        }
        if ($targetId === null && !empty($measures)) {
            $targetId = (int)$measures[0]['id'];
        }
        $selectedSeries = [];
        foreach ($yearIds as $yid) {
            $avgSeries[] = round($avgByYear[$yid], 2);
            $sumSeries[] = round($sumByYear[$yid], 2);
            $countSeries[] = (int)$countNonEmptyByYear[$yid];
            $rawVal = '';
            if ($targetId !== null && isset($valuesMap[$targetId]) && isset($valuesMap[$targetId][$yid])) {
                $rawVal = (string)$valuesMap[$targetId][$yid];
            }
            $num = trim($rawVal);
            if ($num !== '' && substr($num, -1) === '%') {
                $num = substr($num, 0, -1);
            }
            $selectedSeries[] = is_numeric($num) ? (float)$num : 0;
        }
    ?>
    <div class="card shadow-sm mb-4">
        <div class="scorecard-title">SCORECARD DASHBOARD</div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-12 col-lg-4">
                    <div class="border rounded p-3 chart-box">
                        <h6 class="fw-bold mb-2">Average Value by Year</h6>
                        <canvas id="chartAvg" class="chart-canvas"></canvas>
                    </div>
                </div>
                <div class="col-12 col-lg-4">
                    <div class="border rounded p-3 chart-box">
                        <h6 class="fw-bold mb-2">Access to All Level of Care (Pie)</h6>
                        <canvas id="chartCount" class="chart-canvas"></canvas>
                    </div>
                </div>
                <div class="col-12 col-lg-4">
                    <div class="border rounded p-3 chart-box">
                        <h6 class="fw-bold mb-2">Sum of Values by Year</h6>
                        <canvas id="chartSum" class="chart-canvas"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        (function(){
            var labels = <?= json_encode($yearLabels) ?>;
            var avg = <?= json_encode($avgSeries) ?>;
            var special = <?= json_encode($selectedSeries) ?>;
            var sum = <?= json_encode($sumSeries) ?>;
            var primary = '#196a6b';
            var accent = '#f59e0b';
            var gray = '#64748b';
            new Chart(document.getElementById('chartAvg'), {
                type: 'line',
                data: { labels: labels, datasets: [{ label: 'Average', data: avg, borderColor: primary, backgroundColor: 'rgba(25,106,107,0.2)', tension: 0.3, fill: true }] },
                options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
            });
            new Chart(document.getElementById('chartCount'), {
                type: 'pie',
                data: { 
                    labels: labels, 
                    datasets: [{ 
                        label: 'Access to All Level of Care', 
                        data: special, 
                        backgroundColor: labels.map(function(_, i){
                            var palette = ['#196a6b','#f59e0b','#64748b','#10b981','#ef4444','#8b5cf6','#22c55e','#0ea5e9','#f97316','#e11d48'];
                            return palette[i % palette.length];
                        })
                    }] 
                },
                options: { maintainAspectRatio: false, plugins: { legend: { display: true, position: 'bottom' } } }
            });
            new Chart(document.getElementById('chartSum'), {
                type: 'bar',
                data: { labels: labels, datasets: [{ label: 'Sum', data: sum, backgroundColor: gray }] },
                options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
            });
        })();
    </script>
    <?php endif; ?>
    <div class="card shadow-sm mb-5">
        <div class="scorecard-title">IMPACT SCORECARD</div>
        <div class="card-body">

            <?php if (!$tablesOk): ?>
                <div class="alert alert-warning mb-0">
                    Impact Scorecard tables not found. Please import <strong>import_planning_sql/planning_impact_scorecard_dynamic.sql</strong> into your <strong>planning</strong> database.
                </div>
            <?php else: ?>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-semibold mb-0">Impact Indicators by Year</h5>

                    <?php if (($_SESSION['role'] ?? null) === 'admin'): ?>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm" data-bs-toggle="modal" data-bs-target="#addImpactModal" style="background-color: #0b4aa2; color: #ffffff; border: none;">
                                <i data-lucide="plus-circle" class="me-1"></i> Add Impact
                            </button>
                            <button class="btn btn-sm" data-bs-toggle="modal" data-bs-target="#addYearModal" style="background-color: #0b4aa2; color: #ffffff; border: none;">
                                <i data-lucide="plus-circle" class="me-1"></i> Add Year
                            </button>
                            <button class="btn btn-sm" data-bs-toggle="modal" data-bs-target="#deleteYearModal" style="background-color: #dc3545; color: #ffffff; border: none;">
                                <i data-lucide="trash-2" class="me-1"></i> Delete Year
                            </button>
                            <button class="btn btn-sm" data-bs-toggle="modal" data-bs-target="#deleteImpactModal" style="background-color: #dc3545; color: #ffffff; border: none;">
                                <i data-lucide="trash-2" class="me-1"></i> Delete Impact
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered scorecard-table align-middle mb-0">
                        <thead class="text-center">
                            <tr>
                                <th style="min-width: 220px;">Impact</th>
                                <th style="min-width: 320px;">Measure</th>
                                <th style="min-width: 110px;">BL</th>
                                <?php foreach ($years as $y): ?>
                                    <th style="min-width: 110px;"><?= h($y['year']) ?></th>
                                <?php endforeach; ?>
                                <?php if (($_SESSION['role'] ?? null) === 'admin'): ?>
                                    <th style="min-width: 90px;">Action</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($measures as $m): ?>
                                <?php
                                    $mid = (int)$m['id'];
                                    $rowVals = $valuesMap[$mid] ?? [];
                                    $rowValsJson = htmlspecialchars(json_encode($rowVals), ENT_QUOTES);
                                ?>
                                <tr>
                                    <td><?= h($m['impact']) ?></td>
                                    <td><?= h($m['measure']) ?></td>
                                    <td class="text-center"><?= h($m['bl'] ?? '') ?></td>
                                    <?php foreach ($years as $y): ?>
                                        <?php $yid = (int)$y['id']; ?>
                                        <td class="text-center"><?= h($rowVals[$yid] ?? '') ?></td>
                                    <?php endforeach; ?>
                                    <?php if (($_SESSION['role'] ?? null) === 'admin'): ?>
                                        <td class="text-center">
                                            <button
                                                type="button"
                                                class="btn btn-sm"
                                                style="background-color: #fcd34d; color: #1e293b; border: none;"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editModal"
                                                data-id="<?= (int)$m['id'] ?>"
                                                data-impact="<?= h($m['impact']) ?>"
                                                data-measure="<?= h($m['measure']) ?>"
                                                data-bl="<?= h($m['bl'] ?? '') ?>"
                                                data-values="<?= $rowValsJson ?>"
                                            >
                                                Edit
                                            </button>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (($_SESSION['role'] ?? null) === 'admin'): ?>
    <div class="modal fade" id="addImpactModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form id="addImpactForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Impact</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Impact</label>
                        <input type="text" class="form-control" name="impact" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Measure</label>
                        <input type="text" class="form-control" name="measure" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">BL</label>
                        <input type="text" class="form-control" name="bl">
                    </div>

                    <div class="alert alert-info">
                        Enter values for each existing year.
                    </div>

                    <div class="row">
                        <?php foreach ($years as $y): ?>
                            <div class="col-md-4 mb-3">
                                <label class="form-label"><?= h($y['year']) ?></label>
                                <input type="text" class="form-control" name="values[<?= (int)$y['id'] ?>]" placeholder="Value">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn" style="background-color: #196a6b; color: #fff; border: none;">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="addYearModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form id="addYearForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Year</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Year</label>
                        <input type="number" class="form-control" name="year" min="1900" max="3000" required>
                    </div>

                    <div class="alert alert-info">
                        Enter values for each measure under the new year column.
                    </div>

                    <?php foreach ($measures as $m): ?>
                        <div class="mb-3">
                            <label class="form-label"><?= h($m['measure']) ?></label>
                            <input type="text" class="form-control" name="values[<?= (int)$m['id'] ?>]" placeholder="Value">
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn" style="background-color: #196a6b; color: #fff; border: none;">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="deleteYearModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form id="deleteYearForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Year</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        Deleting a year removes the entire column and all values for that year. This action cannot be undone.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Select Year to Delete</label>
                        <select class="form-select" name="year_id" required>
                            <option value="">Choose...</option>
                            <?php foreach ($years as $y): ?>
                                <option value="<?= (int)$y['id'] ?>"><?= h($y['year']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="deleteImpactModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form id="deleteImpactForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Impact</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        Deleting an impact removes the entire row and all values across years. This action cannot be undone.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Select Impact to Delete</label>
                        <select class="form-select" name="id" required>
                            <option value="">Choose...</option>
                            <?php foreach ($measures as $m): ?>
                                <option value="<?= (int)$m['id'] ?>">
                                    <?= h($m['measure']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form id="editForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Impact Scorecard Row</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="row_id">
                    <div class="mb-3">
                        <label class="form-label">Impact</label>
                        <input type="text" class="form-control" name="impact" id="row_impact" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Measure</label>
                        <input type="text" class="form-control" name="measure" id="row_measure" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">BL</label>
                        <input type="text" class="form-control" name="bl" id="row_bl">
                    </div>

                    <div class="row">
                        <?php foreach ($years as $y): ?>
                            <div class="col-md-4 mb-3">
                                <label class="form-label"><?= h($y['year']) ?></label>
                                <input type="text" class="form-control" name="values[<?= (int)$y['id'] ?>]" id="value_year_<?= (int)$y['id'] ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn" style="background-color: #196a6b; color: #fff; border: none;">Save</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

    <?php if (($_SESSION['role'] ?? null) === 'admin'): ?>
        <script>
            document.getElementById('addImpactForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const fd = new FormData(this);

                fetch('impact_indicator_add_impact.php', {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Saved', 'Impact row added successfully.', 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Error', data.error || 'Failed to add impact row', 'error');
                    }
                })
                .catch(() => Swal.fire('Error', 'Request failed', 'error'));
            });

            document.getElementById('addYearForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const fd = new FormData(this);

                fetch('impact_indicator_add_year.php', {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Saved', 'Year added successfully.', 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Error', data.error || 'Failed to add year', 'error');
                    }
                })
                .catch(() => Swal.fire('Error', 'Request failed', 'error'));
            });

            document.getElementById('deleteYearForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const fd = new FormData(this);
                const yearId = fd.get('year_id');
                if (!yearId) { Swal.fire('Error','Please select a year','error'); return; }
                Swal.fire({
                    title: 'Delete year?',
                    text: 'This will permanently remove the selected year and all its values.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Delete',
                    confirmButtonColor: '#dc3545'
                }).then(res => {
                    if (!res.isConfirmed) return;
                    fetch('impact_indicator_delete_year.php', {
                        method: 'POST',
                        body: fd
                    }).then(r => r.json()).then(data => {
                        if (data && data.success) {
                            Swal.fire('Deleted','Year removed','success').then(()=>location.reload());
                        } else {
                            Swal.fire('Error', (data && data.error) || 'Failed to delete year', 'error');
                        }
                    }).catch(()=> Swal.fire('Error','Request failed','error'));
                });
            });

            document.getElementById('deleteImpactForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const fd = new FormData(this);
                const id = fd.get('id');
                if (!id) { Swal.fire('Error','Please select an impact','error'); return; }
                Swal.fire({
                    title: 'Delete impact?',
                    text: 'This will permanently remove the selected impact and all its values.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Delete',
                    confirmButtonColor: '#dc3545'
                }).then(res => {
                    if (!res.isConfirmed) return;
                    fetch('impact_indicator_delete_impact.php', {
                        method: 'POST',
                        body: fd
                    }).then(r => r.json()).then(data => {
                        if (data && data.success) {
                            Swal.fire('Deleted','Impact removed','success').then(()=>location.reload());
                        } else {
                            Swal.fire('Error', (data && data.error) || 'Failed to delete impact', 'error');
                        }
                    }).catch(()=> Swal.fire('Error','Request failed','error'));
                });
            });

            const editModal = document.getElementById('editModal');
            editModal.addEventListener('show.bs.modal', function(event) {
                const btn = event.relatedTarget;
                document.getElementById('row_id').value = btn.getAttribute('data-id');
                document.getElementById('row_impact').value = btn.getAttribute('data-impact') || '';
                document.getElementById('row_measure').value = btn.getAttribute('data-measure') || '';
                document.getElementById('row_bl').value = btn.getAttribute('data-bl') || '';

                let values = {};
                try {
                    values = JSON.parse(btn.getAttribute('data-values') || '{}');
                } catch (e) {
                    values = {};
                }

                <?php foreach ($years as $y): ?>
                    (function() {
                        const input = document.getElementById('value_year_<?= (int)$y['id'] ?>');
                        if (!input) return;
                        const key = String(<?= (int)$y['id'] ?>);
                        input.value = (values && Object.prototype.hasOwnProperty.call(values, key)) ? (values[key] ?? '') : '';
                    })();
                <?php endforeach; ?>
            });

            document.getElementById('editForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const fd = new FormData(this);

                fetch('impact_indicator_update.php', {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Saved', 'Impact Scorecard updated.', 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Error', data.error || 'Update failed', 'error');
                    }
                })
                .catch(() => Swal.fire('Error', 'Request failed', 'error'));
            });
        </script>
    <?php endif; ?>
  </div>
  <?php include PGS_TEMPLATES . '/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?php if (!empty($pageScripts)): ?><?= $pageScripts ?><?php endif; ?>
</body>
</html>
<?php

