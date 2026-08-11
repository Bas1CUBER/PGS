<?php
/**
 * Master layout template.
 * Variables: $pageTitle, $pageStyles (optional <style>/<link>), $pageScripts (optional <script>), $content (HTML body).
 */
?><!DOCTYPE html>
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
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
  <?php
    // Pages pass either raw CSS or already-wrapped <style>/<link> snippets.
    // Wrap raw CSS so the browser applies it instead of rendering it as text.
    $inlineStyles = trim((string)($pageStyles ?? ''));
    if ($inlineStyles !== '' && !preg_match('/^<(style|link)\b/i', $inlineStyles)) {
        $inlineStyles = "<style>\n" . $inlineStyles . "\n</style>";
    }
  ?>
  <?= $inlineStyles ?>
</head>
<body>
  <?php include PGS_TEMPLATES . '/navbar.php'; ?>
  <div class="page-wrapper">
    <?= $content ?? '' ?>
  </div>
  <?php include PGS_TEMPLATES . '/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?= $pageScripts ?? '' ?>
</body>
</html>
