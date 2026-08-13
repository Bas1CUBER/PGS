// Delete deliverable
document.querySelectorAll('.deleteBtn').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        Swal.fire({
            title: 'Delete this record?',
            text: 'This will permanently delete the row from the database.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it'
        }).then((result) => {
            if (!result.isConfirmed) return;

            const formData = new FormData();
            formData.append('id', id);

            fetch('delete_deliverables.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Deleted!', 'Record deleted successfully.', 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error', data.error || 'Delete failed.', 'error');
                }
            })
            .catch(() => {
                Swal.fire('Error', 'Something went wrong while deleting.', 'error');
            });
        });
    });
});
