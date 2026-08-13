document.querySelectorAll('.dropdown-submenu').forEach(function(submenu) {
    submenu.addEventListener('mouseenter', function() {
        let dropdown = this.querySelector('.dropdown-menu');
        if (dropdown) dropdown.classList.add('show');
    });
    submenu.addEventListener('mouseleave', function() {
        let dropdown = this.querySelector('.dropdown-menu');
        if (dropdown) dropdown.classList.remove('show');
    });
});

// Auto-refresh after successful form submission
document.getElementById('performanceForm').addEventListener('submit', function(event) {
    event.preventDefault(); // Prevent default form submission
    const form = this;
    const formData = new FormData(form);

    fetch(form.action, {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            // Check if success message is present in the response
            if (data.includes('Data successfully saved!')) {
                // Delay refresh to allow user to see success message
                setTimeout(() => {
                    window.location.reload();
                }, 2000); // 2-second delay
            } else {
                // If there's an error, update the page without refreshing
                document.open();
                document.write(data);
                document.close();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            // Display error message if fetch fails
            const errorDiv = document.createElement('div');
            errorDiv.className = 'alert alert-danger';
            errorDiv.textContent = 'An error occurred while submitting the form.';
            form.parentElement.insertBefore(errorDiv, form);
        });
});
