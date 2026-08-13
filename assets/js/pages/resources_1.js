document.addEventListener('DOMContentLoaded', function () {
        var editModal = document.getElementById('editModal');
        editModal && editModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            document.getElementById('edit-id').value = button.getAttribute('data-id');
            document.getElementById('edit-title').value = button.getAttribute('data-title');
        });
        var viewerModal = document.getElementById('viewerModal');
        viewerModal && viewerModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var filename = button.getAttribute('data-filename');
            var title = button.getAttribute('data-title');
            document.getElementById('viewer-title').textContent = title;
            document.getElementById('viewer-frame').src = 'uploads/resources/' + filename + '#view=FitH';
        });
    });
