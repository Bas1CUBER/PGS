(function(){
      var toggle = document.getElementById('editToggle');
      var editForm = document.getElementById('editForm');
      var viewMode = document.getElementById('viewMode');
      var cancelBtn = document.getElementById('cancelBtn');
      if (toggle) {
        toggle.addEventListener('click', function(){
          editForm.style.display = 'flex';
          viewMode.style.display = 'none';
        });
      }
      if (cancelBtn) {
        cancelBtn.addEventListener('click', function(){
          editForm.style.display = 'none';
          viewMode.style.display = 'flex';
        });
      }
    })();
