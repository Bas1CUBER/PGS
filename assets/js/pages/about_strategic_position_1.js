document.addEventListener('DOMContentLoaded', function(){
  var editBtn = document.getElementById('editBtn');
  var editForm = document.getElementById('editForm');
  var cancelBtn = document.getElementById('cancelEditBtn');
  var imageInput = document.getElementById('imageInput');
  var previewImg = document.getElementById('previewImg');
  var previewWrap = document.getElementById('previewWrap');
  if (editBtn && editForm) {
    editBtn.addEventListener('click', function(){ editForm.style.display = editForm.style.display === 'block' ? 'none' : 'block'; });
  }
  if (cancelBtn && editForm) {
    cancelBtn.addEventListener('click', function(){ editForm.style.display = 'none'; previewWrap.style.display='none'; imageInput.value=''; });
  }
  if (imageInput) {
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
});
