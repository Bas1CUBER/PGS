document.getElementById('addImpactForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const fd = new FormData(this);

                fetch('impact_indicator_add_impact.php', {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Saved', 'Impact row added successfully.', 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Error', data.error || 'Failed to add impact row', 'error');
                    }
                })
                .catch(() => Swal.fire('Error', 'Request failed', 'error'));
            });

            document.getElementById('addYearForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const fd = new FormData(this);

                fetch('impact_indicator_add_year.php', {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Saved', 'Year added successfully.', 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Error', data.error || 'Failed to add year', 'error');
                    }
                })
                .catch(() => Swal.fire('Error', 'Request failed', 'error'));
            });

            document.getElementById('deleteYearForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const fd = new FormData(this);
                const yearId = fd.get('year_id');
                if (!yearId) { Swal.fire('Error','Please select a year','error'); return; }
                Swal.fire({
                    title: 'Delete year?',
                    text: 'This will permanently remove the selected year and all its values.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Delete',
                    confirmButtonColor: '#dc3545'
                }).then(res => {
                    if (!res.isConfirmed) return;
                    fetch('impact_indicator_delete_year.php', {
                        method: 'POST',
                        body: fd
                    }).then(r => r.json()).then(data => {
                        if (data && data.success) {
                            Swal.fire('Deleted','Year removed','success').then(()=>location.reload());
                        } else {
                            Swal.fire('Error', (data && data.error) || 'Failed to delete year', 'error');
                        }
                    }).catch(()=> Swal.fire('Error','Request failed','error'));
                });
            });

            document.getElementById('deleteImpactForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const fd = new FormData(this);
                const id = fd.get('id');
                if (!id) { Swal.fire('Error','Please select an impact','error'); return; }
                Swal.fire({
                    title: 'Delete impact?',
                    text: 'This will permanently remove the selected impact and all its values.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Delete',
                    confirmButtonColor: '#dc3545'
                }).then(res => {
                    if (!res.isConfirmed) return;
                    fetch('impact_indicator_delete_impact.php', {
                        method: 'POST',
                        body: fd
                    }).then(r => r.json()).then(data => {
                        if (data && data.success) {
                            Swal.fire('Deleted','Impact removed','success').then(()=>location.reload());
                        } else {
                            Swal.fire('Error', (data && data.error) || 'Failed to delete impact', 'error');
                        }
                    }).catch(()=> Swal.fire('Error','Request failed','error'));
                });
            });

            const editModal = document.getElementById('editModal');
            editModal.addEventListener('show.bs.modal', function(event) {
                const btn = event.relatedTarget;
                document.getElementById('row_id').value = btn.getAttribute('data-id');
                document.getElementById('row_impact').value = btn.getAttribute('data-impact') || '';
                document.getElementById('row_measure').value = btn.getAttribute('data-measure') || '';
                document.getElementById('row_bl').value = btn.getAttribute('data-bl') || '';

                let values = {};
                try {
                    values = JSON.parse(btn.getAttribute('data-values') || '{}');
                } catch (e) {
                    values = {};
                }

                PGS.page.years.forEach(function(y) { 
                    (function() {
                        const input = document.getElementById('value_year_y.id');
                        if (!input) return;
                        const key = String(y.id);
                        input.value = (values && Object.prototype.hasOwnProperty.call(values, key)) ? (values[key] ?? '') : '';
                    })();
                });
            });

            document.getElementById('editForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const fd = new FormData(this);

                fetch('impact_indicator_update.php', {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Saved', 'Impact Scorecard updated.', 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Error', data.error || 'Update failed', 'error');
                    }
                })
                .catch(() => Swal.fire('Error', 'Request failed', 'error'));
            });
