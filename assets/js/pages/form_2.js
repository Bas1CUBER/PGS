document.getElementById('deliverableForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = document.getElementById('deliverableForm');
    const formData = new FormData(form);
    fetch('insert.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Deliverable added successfully!',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });

                form.reset();
                const modal = bootstrap.Modal.getInstance(document.getElementById('addFormModal'));
                if (modal) {
                    modal.hide();
                }
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: result.message || 'Failed to insert record.'
                });
            }
        })
        .catch(err => {
            Swal.fire({
                icon: 'error',
                title: 'Server Error',
                text: 'Something went wrong with the server.'
            });
        });
});
