// Open edit modal and populate fields
document.querySelectorAll('.editBtn').forEach(btn => {
    btn.addEventListener('click', function() {
        const data = JSON.parse(this.getAttribute('data-info'));

        document.getElementById('edit_id').value = data.id;
        document.getElementById('edit_title').value = data.title;
        document.getElementById('edit_focal_person').value = data.focal_person;
        document.getElementById('edit_division').value = data.division;
        document.getElementById('edit_form_type').value = data.form_type;
        document.getElementById('edit_target_date').value = data.target_date;
        document.getElementById('edit_status').value = data.status;
        document.getElementById('edit_actual_date').value = data.actual_date || '';

        const currentMOVDiv = document.getElementById('current_mov_file');
        if (data.mov_file) {
            currentMOVDiv.innerHTML = `
                <p class="mb-0">Current File: 
                    <a href="uploads/${data.mov_file}" target="_blank">${data.mov_file}</a>
                </p>
            `;
        } else {
            currentMOVDiv.innerHTML = '<p class="text-muted mb-0">No file uploaded.</p>';
        }

        const editModal = new bootstrap.Modal(document.getElementById('editFormModal'));
        editModal.show();
    });
});

// Handle form submission
document.getElementById('editDeliverableForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = this;
    const formData = new FormData(form);

    fetch('update.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                Swal.fire('Updated!', 'Deliverable updated successfully.', 'success').then(() => {
                    location.reload();
                });
            } else {
                Swal.fire('Error', data.message || 'Update failed.', 'error');
            }
        })
        .catch(err => {
            Swal.fire('Error', 'Something went wrong while updating.', 'error');
        });
});
