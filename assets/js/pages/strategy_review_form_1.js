function generatePDF() {
    const form = document.getElementById('strategyReviewForm');
    
    // Validate form
    if (!form.checkValidity()) {
        Swal.fire('Error', 'Please fill in all required fields', 'error');
        return;
    }

    // Collect form data
    const formData = new FormData(form);
    const data = {};
    formData.forEach((value, key) => {
        data[key] = value;
    });

    // Show loading
    Swal.fire({
        title: 'Generating PDF...',
        text: 'Please wait while we generate your PDF',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Send to server
    fetch('strategy_review_generate_pdf.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        Swal.close();
        if (result.success) {
            Swal.fire({
                title: 'Success!',
                text: 'PDF generated successfully',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire('Error', result.error || 'Failed to generate PDF', 'error');
        }
    })
    .catch(error => {
        Swal.close();
        Swal.fire('Error', 'Failed to generate PDF', 'error');
    });
}

function saveDraft() {
    const form = document.getElementById('strategyReviewForm');
    
    // Collect form data
    const formData = new FormData(form);
    const data = {};
    formData.forEach((value, key) => {
        data[key] = value;
    });

    // Show loading
    Swal.fire({
        title: 'Saving Draft...',
        text: 'Please wait',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Send to server
    fetch('strategy_review_save_draft.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        Swal.close();
        if (result.success) {
            Swal.fire({
                title: 'Success!',
                text: 'Draft saved successfully',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
        } else {
            Swal.fire('Error', result.error || 'Failed to save draft', 'error');
        }
    })
    .catch(error => {
        Swal.close();
        Swal.fire('Error', 'Failed to save draft', 'error');
    });
}

// Set today's date as default
document.getElementById('review_date').valueAsDate = new Date();
