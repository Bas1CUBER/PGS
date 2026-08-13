<?php
require_once __DIR__ . '/src/bootstrap.php';

// Optional: restrict access if not logged in or not admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'employee','focal'])) {
    header("Location: " . BASE_URL . "/login");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin Dashboard - TRC Modern</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

    <?= page_or_bundle_css('css/pages/annex_jk.css') ?>
</head>

<body>
<?php include PGS_TEMPLATES . '/navbar.php'; ?>

<div class="page-wrapper container my-5 d-flex flex-column align-items-center text-center">

    <!-- Responsive iframe -->
    <div class="mb-3 text-center text-secondary fst-italic" fw-500>
        <i data-lucide="alert-circle" class="me-2"></i>This table is read-only
    </div>

    <div class="iframe-container mb-4">
        <iframe src="/PGS/forms/Annex K.html"></iframe>
    </div>

    <!-- Download Button (MOVED BELOW THE FORM) -->
    <a href="/PGS/forms/Annex K.xlsx"
       download
       class="btn btn-success btn-sm d-inline-flex align-items-center gap-2 my-3" w-fit>
        <i data-lucide="file-spreadsheet"></i>
        <i data-lucide="download"></i>
        <span>Download Annex K</span>
    </a>

</div>

<?php include PGS_TEMPLATES . '/footer.php'; ?>

<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

