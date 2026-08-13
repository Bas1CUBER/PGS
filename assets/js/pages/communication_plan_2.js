document.querySelectorAll('.status-select').forEach(select => {
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
                if (result.isConfirmed) {
                    fetch('communication_plan_update_status.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: id, status: status, _token: 'PGS.page.csrf' })
                    }).then(r => r.json()).then(data => {
                        if (data.success) {
                            Swal.fire({ title: 'Success', text: 'Status updated', icon: 'success', timer: 1500, showConfirmButton: false });
                        } else {
                            Swal.fire('Error', data.error || 'Failed to update status', 'error');
                            this.value = this.getAttribute('data-original');
                        }
                    }).catch(() => {
                        Swal.fire('Error', 'Failed to update status', 'error');
                        this.value = this.getAttribute('data-original');
                    });
                } else {
                    this.value = this.getAttribute('data-original');
                }
            });
        });
    });
