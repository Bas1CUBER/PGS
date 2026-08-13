document.querySelectorAll('.upload-delete-form').forEach(form => {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            Swal.fire({
                title: 'Delete upload?',
                text: 'This will permanently delete the uploaded document.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Delete',
                confirmButtonColor: '#d33'
            }).then(r => {
                if (r.isConfirmed) form.submit();
            });
        });
    });
