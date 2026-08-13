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
