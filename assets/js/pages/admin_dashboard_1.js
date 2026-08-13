window.PGS_CSRF = 'PGS.page.csrf';
    function viewNotice(title, description, image, video) {
      document.getElementById('modalTitle').innerText = title;
      let html = `<p class="text-secondary">${description.replace(/\n/g, "<br>")}</p>`;
      if (image) {
        if (image.toLowerCase().endsWith('.pdf')) {
          html += `<a href="${image}" target="_blank" class="btn btn-primary mt-2"><i data-lucide="file-text" class="me-1"></i> View PDF Document</a>`;
        } else {
          html += `<img src="${image}" alt="Notice Image">`;
        }
      }
      if (video) html += `<p>Video Link: <a href="${video}" target="_blank">${video}</a></p>`;
      document.getElementById('modalBody').innerHTML = html;

      let noticeModal = new bootstrap.Modal(document.getElementById('noticeModal'));
      noticeModal.show();
    }

    function deleteNotice(noticeId) {
      if (!Number.isInteger(noticeId) || noticeId <= 0) {
        alert('Invalid notice id');
        return;
      }

      const confirmed = confirm('Are you sure you want to delete this notice?');
      if (!confirmed) return;

      const formData = new FormData();
      formData.append('notice_id', String(noticeId));
      formData.append('_token', window.PGS_CSRF);

      fetch('delete_notice.php', {
        method: 'POST',
        body: formData
      })
        .then(res => res.json())
        .then(data => {
          if (data && data.success) {
            location.reload();
            return;
          }
          alert((data && data.message) ? data.message : 'Delete failed');
        })
        .catch(() => {
          alert('Delete failed');
        });
    }
