<?php
require_once __DIR__ . '/src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    $_SESSION['flash_error'] = 'Invalid or expired form token. Please try again.';
    header("Location: " . BASE_URL . "/login");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST['token'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    if ($new !== $confirm) {
        exit("Passwords do not match.");
    }

    $hashed = password_hash($new, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL WHERE reset_token = ?");
    $stmt->bind_param("ss", $hashed, $token);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo "Password has been reset. You can now <a href='login.html'>login</a>.";
    } else {
        echo "Invalid or expired token.";
    }
    exit();
}

// Show reset form if token is present
$token = $_GET['token'] ?? '';
$pageTitle = 'Reset Password';
$pageStyles = '';
?>
<!DOCTYPE html>
<html lang="en">
<?php require PGS_TEMPLATES . '/head.php'; ?>
<body>
  <?php include PGS_TEMPLATES . '/navbar.php'; ?>
  <div class="page-wrapper">

    <form method="POST" class="border p-4 rounded shadow" style="width: 350px;">
        <?= csrf_field() ?>
        <h4 class="mb-3 text-center">Reset Password</h4>
        <input type="hidden" name="token" value="<?= h($token) ?>">
        <div class="mb-3">
            <label>New Password</label>
            <input type="password" class="form-control" name="new_password" required>
        </div>
        <div class="mb-3">
            <label>Confirm Password</label>
            <input type="password" class="form-control" name="confirm_password" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Reset</button>
    </form>
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

