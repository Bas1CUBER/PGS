<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_page_access('scorecard');
?>
<!DOCTYPE html>
<html lang="en">
<?php $pageTitle = 'Employee Engagement Rating (Research)'; ?>
<?php $pageStyles = '<link rel="stylesheet" href="' . asset('css/pages/research_roadmap_employee_engagement.css') . '">';
?>
<?php require PGS_TEMPLATES . '/head_module.php'; ?>
<body>
    <?php include PGS_TEMPLATES . '/navbar.php'; ?>
    <div class="container" pt-110>
        <div class="card shadow-sm">
            <div class="card-header">Employee Engagement Rating</div>
            <div class="card-body">
                <p>Content will be added later.</p>
            </div>
        </div>
    </div>
    <?php include PGS_TEMPLATES . '/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
