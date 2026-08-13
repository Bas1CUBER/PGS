document.querySelectorAll('.roadmap-status-select').forEach(select => {
        select.setAttribute('data-original', select.value);
        select.addEventListener('change', function() {
            const id = this.dataset.id;
            const status = this.value;
            Swal.fire({
                title: 'Update Status',
                text: 'Change status to ' + status + '?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, update',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (!result.isConfirmed) {
                    this.value = this.getAttribute('data-original');
                    return;
                }
                fetch('communication_plan_roadmap_update_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id, status: status, _token: 'PGS.page.csrf' })
                }).then(r => r.json()).then(data => {
                    if (data && data.success) {
                        this.setAttribute('data-original', status);
                        Swal.fire({ title: 'Success', text: 'Status updated', icon: 'success', timer: 1200, showConfirmButton: false });
                    } else {
                        Swal.fire('Error', (data && data.error) || 'Failed to update status', 'error');
                        this.value = this.getAttribute('data-original');
                    }
                }).catch(() => {
                    Swal.fire('Error', 'Failed to update status', 'error');
                    this.value = this.getAttribute('data-original');
                });
            });
        });
    });
