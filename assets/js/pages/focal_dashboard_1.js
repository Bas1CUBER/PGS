function viewNotice(title, description, image, video) {
      document.getElementById('modalTitle').innerText = title;
      let modalBody = document.getElementById('modalBody');
      let html = `<p class="text-secondary">${description}</p>`;
      if (image) {
        if (image.toLowerCase().endsWith('.pdf')) {
          html += `<a href="${image}" target="_blank" class="btn btn-primary mt-2"><i data-lucide="file-text" class="me-1"></i> View PDF Document</a>`;
        } else {
          html += `<img src="${image}" alt="Notice Image">`;
        }
      }
      if (video) {
        html += `<p>Video Link: <a href="${video}" target="_blank">${video}</a></p>`;
      }
      modalBody.innerHTML = html;
      let noticeModal = new bootstrap.Modal(document.getElementById('noticeModal'));
      noticeModal.show();
    }
