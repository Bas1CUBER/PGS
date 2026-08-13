const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('fileInput');
    const uploadProgress = document.getElementById('uploadProgress');
    uploadArea.addEventListener('click', () => fileInput.click());
    uploadArea.addEventListener('dragover', (e) => { e.preventDefault(); uploadArea.classList.add('dragover'); });
    uploadArea.addEventListener('dragleave', () => { uploadArea.classList.remove('dragover'); });
    uploadArea.addEventListener('drop', (e) => { e.preventDefault(); uploadArea.classList.remove('dragover'); handleFiles(e.dataTransfer.files); });
    fileInput.addEventListener('change', (e) => handleFiles(e.target.files));
    function handleFiles(files) {
        if (files.length === 0) return;
        const file = files[0];
        const maxSize = 10 * 1024 * 1024;
        const allowed = ['application/pdf','image/jpeg','image/jpg','image/png'];
        if (!allowed.includes(file.type)) { Swal.fire('Error','Please upload a PDF, JPG, or PNG','error'); return; }
        if (file.size > maxSize) { Swal.fire('Error','File size must be less than 10MB','error'); return; }
        uploadFile(file);
    }
    function uploadFile(file) {
        const formData = new FormData();
        formData.append('_token','PGS.page.csrf');
        formData.append('file', file);
        uploadProgress.style.display = 'block';
        const progressBar = uploadProgress.querySelector('.progress-bar');
        fetch('communication_plan_upload.php', { method: 'POST', body: formData })
            .then(r => r.json()).then(data => {
                uploadProgress.style.display = 'none';
                fileInput.value = '';
                if (data.success) {
                    Swal.fire({ title: 'Success', text: 'Your document has been uploaded', icon: 'success', timer: 2000, showConfirmButton: false });
                    setTimeout(() => location.reload(), 1500);
                } else {
                    Swal.fire('Error', data.error || 'Upload failed', 'error');
                }
            }).catch(() => {
                uploadProgress.style.display = 'none';
                Swal.fire('Error','Upload failed','error');
            });
        let progress = 0;
        const interval = setInterval(() => {
            progress += 10;
            progressBar.style.width = progress + '%';
            if (progress >= 90) clearInterval(interval);
        }, 200);
    }
