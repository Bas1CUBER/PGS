document.querySelectorAll('.uploadBtn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('upload_id').value = this.getAttribute('data-id');
        document.getElementById('mov_file').value = '';
        const modal = new bootstrap.Modal(document.getElementById('uploadModal'));
        modal.show();
    });
});

document.getElementById('uploadForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch('employee_upload.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            Swal.fire('Uploaded!', 'File uploaded successfully.', 'success').then(() => {
                location.reload();
            });
        } else {
            Swal.fire('Error', data.message || 'Upload failed.', 'error');
        }
    })
    .catch(() => {
        Swal.fire('Error', 'Something went wrong while uploading.', 'error');
    });
});
