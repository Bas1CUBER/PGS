document.addEventListener('DOMContentLoaded', function(){
            var img = document.getElementById('osmImage');
            if (img) {
                img.addEventListener('click', function(){
                    var modalEl = document.getElementById('zoomModal');
                    var modal = new bootstrap.Modal(modalEl);
                    modal.show();
                });
            }
            var editBtn = document.getElementById('editBtn');
            var editForm = document.getElementById('editForm');
            var cancelBtn = document.getElementById('cancelEditBtn');
            if (editBtn && editForm) {
                editBtn.addEventListener('click', function(){
                    var isHidden = window.getComputedStyle(editForm).display === 'none';
                    editForm.style.display = isHidden ? 'block' : 'none';
                });
            }
            if (cancelBtn && editForm) {
                cancelBtn.addEventListener('click', function(){
                    editForm.style.display = 'none';
                });
            }
            var imageInput = document.getElementById('imageInput');
            var previewWrap = document.getElementById('previewWrap');
            var previewImg = document.getElementById('previewImg');
            if (imageInput && previewImg) {
                imageInput.addEventListener('change', function(){
                    var file = this.files[0];
                    if (file) {
                        var reader = new FileReader();
                        reader.onload = function(e) {
                            previewImg.src = e.target.result;
                            previewWrap.style.display = 'block';
                        };
                        reader.readAsDataURL(file);
                    } else {
                        previewWrap.style.display = 'none';
                    }
                });
            }
if ($uploadMsg) {
            if (editForm) editForm.style.display = 'block';
}
        });
