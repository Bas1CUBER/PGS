const filterSelect = document.getElementById('filterYearSelect');
            const tableBody = document.getElementById('tableBody');

            filterSelect.addEventListener('change', () => {
                const year = filterSelect.value;
                Array.from(tableBody.rows).forEach(row => {
                    const dateText = row.cells[3].textContent;
                    const rowYear = new Date(dateText).getFullYear().toString();
                    row.style.display = (year === "" || rowYear === year) ? "" : "none";
                });
            });

            document.getElementById('addDataForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                fetch('insert.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: 'Deliverable added successfully.',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message || 'An unknown error occurred.',
                            });
                        }
                    });
            });

            document.querySelectorAll('.delete-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.dataset.id;
                    fetch('delete_deliverables.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'id=' + id
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Deleted!',
                                    text: 'Deliverable deleted.',
                                    timer: 1500,
                                    showConfirmButton: false
                                }).then(() => location.reload());
                            } else {
                                Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'Delete failed.' });
                            }
                        });
                });
            });
