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

$pageStyles = '<link rel="stylesheet" href="' . asset('css/pages/impact_indicator.css') . '">';

?>
<!DOCTYPE html>
<html lang="en">
<?php require PGS_TEMPLATES . '/head.php'; ?>
<body>
  <?php include PGS_TEMPLATES . '/navbar.php'; ?>
  <div class="page-wrapper">

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<div class="container my-5" pt-70>
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
    <?php $pgsPage = ['yearLabels' => $yearLabels, 'avgSeries' => $avgSeries, 'selectedSeries' => $selectedSeries, 'sumSeries' => $sumSeries]; ?><script>window.PGS.page = <?= json_encode($pgsPage) ?>;</script><script src="<?= asset('js/pages/impact_indicator_1.js') ?>"></script>
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
  <?= csrf_field() ?>
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
                    <button type="submit" class="btn" header-green>Save</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="addYearModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form id="addYearForm" class="modal-content">
  <?= csrf_field() ?>
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
                    <button type="submit" class="btn" header-green>Save</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="deleteYearModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form id="deleteYearForm" class="modal-content">
  <?= csrf_field() ?>
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
  <?= csrf_field() ?>
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
  <?= csrf_field() ?>
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
                    <button type="submit" class="btn" header-green>Save</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

    <?php if (($_SESSION['role'] ?? null) === 'admin'): ?>
        <?php $pgsPage = ['years' => $years]; ?><script>window.PGS.page = <?= json_encode($pgsPage) ?>;</script><script src="<?= asset('js/pages/impact_indicator_2.js') ?>"></script>
    <?php endif; ?>
  </div>
  <?php include PGS_TEMPLATES . '/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?php if (!empty($pageScripts)): ?><?= $pageScripts ?><?php endif; ?>
</body>
</html>
<?php

