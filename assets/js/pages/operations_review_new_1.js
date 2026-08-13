const uploadArea = document.getElementById('uploadArea');
  const fileInput = document.getElementById('fileInput');
  const uploadProgress = document.getElementById('uploadProgress');
  uploadArea.addEventListener('click', ()=> fileInput.click());
  uploadArea.addEventListener('dragover', (e)=> { e.preventDefault(); uploadArea.classList.add('dragover'); });
  uploadArea.addEventListener('dragleave', ()=> uploadArea.classList.remove('dragover'));
  uploadArea.addEventListener('drop', (e)=> { e.preventDefault(); uploadArea.classList.remove('dragover'); handleFiles(e.dataTransfer.files); });
  fileInput.addEventListener('change', (e)=> handleFiles(e.target.files));
  function handleFiles(files){
    if (!files || !files.length) return;
    const f = files[0];
    const allowed = ['application/pdf','image/jpeg','image/jpg','image/png'];
    if (!allowed.includes(f.type)) { alert('Please upload PDF, JPG or PNG'); return; }
    if (f.size > 10*1024*1024) { alert('Max size 10MB'); return; }
    const fd = new FormData(); fd.append('_token','PGS.page.csrf'); fd.append('file', f);
    uploadProgress.style.display = 'block';
    const bar = uploadProgress.querySelector('.progress-bar'); bar.style.width = '0%';
    fetch('operations_review_upload.php', { method:'POST', body: fd })
      .then(r=>r.json()).then(data=>{
        uploadProgress.style.display='none'; fileInput.value='';
        if (data && data.success) { location.reload(); }
        else { alert(data && data.error ? data.error : 'Upload failed'); }
      }).catch(()=>{ uploadProgress.style.display='none'; alert('Upload failed'); });
    let p=0; const iv=setInterval(()=>{ p+=10; bar.style.width=p+'%'; if (p>=90) clearInterval(iv); },200);
  }
