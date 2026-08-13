<?php
require_once __DIR__ . '/src/bootstrap.php';

// Restrict access to admin only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . BASE_URL . "/login");
    exit();
}

// Fetch notices
$sql = "SELECT notice_id, title, description, image, video, created_at 
        FROM notices 
        ORDER BY created_at DESC 
        LIMIT 6";

$result = $conn->query($sql);

$notices = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $notices[] = $row;
    }
}
$conn->close();

$pageStyles = '<link rel="stylesheet" href="' . asset('css/pages/notice.css') . '">';

$pageScripts = <<<'EOSCRIPT'
<script>
document.getElementById('videoFile').addEventListener('change', function(e) {
    const preview = document.getElementById('videoPreview');
    if (this.files.length) {
        preview.src = URL.createObjectURL(this.files[0]);
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }
});

document.getElementById('noticeForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch("add_notice.php", {
        method: "POST",
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('noticeAlert').classList.remove('d-none');
            setTimeout(() => location.reload(), 1200);
        } else {
            alert(data.message);
        }
    })
    .catch(err => {
        console.error(err);
        alert("Submission failed");
    });
});
</script>
EOSCRIPT;

?>
<!DOCTYPE html>
<html lang="en">
<?php require PGS_TEMPLATES . '/head.php'; ?>
<body>
  <?php include PGS_TEMPLATES . '/navbar.php'; ?>
  <div class="page-wrapper">

<div class="content">
<div class="container">

<div class="text-center mb-5">
    <h2 class="fw-bold">&#x1F4E2; Notice Board</h2>
    <p class="text-muted">Stay informed with the latest announcements.</p>
</div>

<div id="noticeAlert" class="alert alert-success d-none">
    &#x2705; Notice has been successfully submitted!
</div>

<div class="card shadow-sm mb-5">
<div class="card-body">
<form id="noticeForm" method="POST" enctype="multipart/form-data">
    <?= csrf_field() ?>
<div class="mb-3">
    <label class="form-label">Title</label>
    <input type="text" class="form-control" name="title" required>
</div>

<div class="mb-3">
    <label class="form-label">Description</label>
    <textarea class="form-control" name="description" rows="4" required></textarea>
</div>

<div class="mb-3">
    <label class="form-label">Upload Photo</label>
    <input type="file" class="form-control" name="image" accept="image/*">
</div>

<hr>

<div class="mb-3">
    <label class="form-label">&#x1F3AC; Upload Video (MP4)</label>
    <input type="file" class="form-control" name="video_file" accept="video/mp4" id="videoFile">
    <video class="video-preview" id="videoPreview" controls></video>
</div>

<div class="text-center mt-4">
    <button type="submit" class="btn btn-save px-4">
        <i data-lucide="send"></i> Submit
    </button>
</div>

</form>
</div>
</div>

<h4 class="fw-bold mb-4"> Recent Notices</h4>

<ul class="list-group">
<?php foreach ($notices as $notice): ?>
<li class="list-group-item shadow-sm mb-3 notice-card p-4">
<h5 class="fw-bold"><?= h($notice['title']) ?></h5>
<small class="text-muted"><?= date("F j, Y, g:i a", strtotime($notice['created_at'])) ?></small>
<p class="mt-2"><?= nl2br(h($notice['description'] ?? '')) ?></p>

<?php if ($notice['image'] || $notice['video']): ?>
<div class="notice-media">
<?php if ($notice['image']): ?>
<img src="<?= h($notice['image']) ?>" alt="Notice Image">
<?php endif; ?>

<?php if ($notice['video']): ?>
<video controls>
<source src="<?= h($notice['video']) ?>" type="video/mp4">
</video>
<?php endif; ?>
</div>
<?php endif; ?>

</li>
<?php endforeach; ?>
</ul>

</div>
</div>
  </div>
  <?php include PGS_TEMPLATES . '/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?php if (!empty($pageScripts)): ?><?= $pageScripts ?><?php endif; ?>
</body>
</html>
<?php

