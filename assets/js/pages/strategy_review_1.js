document.querySelectorAll('.status-select').forEach(select => {
            select.addEventListener('change', function() {
                const id = this.dataset.id;
                const status = this.value;
                
                Swal.fire({
                    title: 'Update Status',
                    text: `Change status to ${status}?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, update',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        fetch('strategy_review_update_status.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                id: id,
                                status: status,
                                _token: 'PGS.page.csrf'
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    title: 'Success!',
                                    text: 'Status updated successfully',
                                    icon: 'success',
                                    timer: 1500,
                                    showConfirmButton: false
                                });
                            } else {
                                Swal.fire('Error', data.error || 'Failed to update status', 'error');
                                this.value = this.getAttribute('data-original') || 'Pending';
                            }
                        })
                        .catch(error => {
                            Swal.fire('Error', 'Failed to update status', 'error');
                            this.value = this.getAttribute('data-original') || 'Pending';
                        });
                    } else {
                        this.value = this.getAttribute('data-original') || 'Pending';
                    }
                });
            });
            
            select.setAttribute('data-original', select.value);
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
                    fetch('strategy_review.php', { method: 'POST', body: fd })
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
