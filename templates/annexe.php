<?php
session_start();

// Optional: restrict access if not logged in or not admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'employee','focal'])) {
    header("Location: login.php");
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

    <!-- Bootstrap & Font Awesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

    <style>
        html, body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background-color: #ffffff;
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

        .iframe-container {
            position: relative;
            width: 100%;
            padding-bottom: 56.25%;
            height: 0;
            overflow: hidden;
        }

        .iframe-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
        }
    </style>
</head>

<body>

<?php include 'navbar.php'; ?>

<div class="page-wrapper container my-5 d-flex flex-column align-items-center text-center">

    <!-- Responsive iframe -->
    <div class="mb-3 text-center text-secondary fst-italic" style="font-weight: 500;">
        <i data-lucide="alert-circle" class="me-2"></i>This table is read-only
    </div>

    <div class="iframe-container mb-4">
        <iframe src="/PGS/forms/Annex E.html"></iframe>
    </div>

    <!-- Download Button (MOVED BELOW THE FORM) -->
    <a href="/PGS/forms/Annex E.xlsx"
       download
       class="btn btn-success btn-sm d-inline-flex align-items-center gap-2 my-3"
       style="width: fit-content;">
        <i data-lucide="file-spreadsheet"></i>
        <i data-lucide="download"></i>
        <span>Download Annex E</span>
    </a>

</div>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
