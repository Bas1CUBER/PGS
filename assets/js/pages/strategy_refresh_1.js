document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const title = formData.get('title');
            const file = formData.get('file');
            
            if (!title || !file) {
                Swal.fire('Error', 'Please fill in all fields', 'error');
                return;
            }
            
            const maxSize = 10 * 1024 * 1024;
            if (file.size > maxSize) {
                Swal.fire('Error', 'File size must be less than 10MB', 'error');
                return;
            }
            
            const uploadProgress = document.getElementById('uploadProgress');
            const progressBar = uploadProgress.querySelector('.progress-bar');
            uploadProgress.style.display = 'block';
            
            fetch('strategy_refresh_upload.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                uploadProgress.style.display = 'none';
                
                if (data.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: 'STRATEGY REFRESH Document uploaded successfully',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    
                    this.reset();
                    bootstrap.Modal.getInstance(document.getElementById('uploadModal')).hide();
                    
                    setTimeout(() => location.reload(), 1500);
                } else {
                    Swal.fire('Error', data.error || 'Upload failed', 'error');
                }
            })
            .catch(error => {
                uploadProgress.style.display = 'none';
                Swal.fire('Error', 'Upload failed', 'error');
            });
            
            let progress = 0;
            const interval = setInterval(() => {
                progress += 10;
                progressBar.style.width = progress + '%';
                if (progress >= 90) clearInterval(interval);
            }, 200);
        });
        document.querySelectorAll('.btn-delete').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                if (!id) return;
                Swal.fire({
                    title: 'Delete Document',
                    text: 'Are you sure you want to delete this document?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, delete',
                    cancelButtonText: 'Cancel'
                }).then(result => {
                    if (!result.isConfirmed) return;
                    const fd = new FormData();
                    fd.append('_token','PGS.page.csrf');
                    fd.append('action', 'delete_upload');
                    fd.append('id', id);
                    fetch('strategy_refresh.php', { method: 'POST', body: fd })
                        .then(r => r.json()).then(d => {
                            if (d && d.success) {
                                Swal.fire({ title: 'Deleted', icon: 'success', timer: 1200, showConfirmButton: false });
                                setTimeout(() => location.reload(), 1000);
                            } else {
                                Swal.fire('Error', d && d.error ? d.error : 'Delete failed', 'error');
                            }
                        }).catch(() => Swal.fire('Error', 'Delete failed', 'error'));
                });
            });
        });
