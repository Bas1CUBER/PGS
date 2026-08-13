document.querySelectorAll('.status-select').forEach(sel=>{
    sel.setAttribute('data-original', sel.value);
    sel.addEventListener('change', function(){
      const id = this.dataset.id; const status = this.value;
      fetch('operations_review_update_status.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ id:id, status: status, _token: 'PGS.page.csrf' })
      }).then(r=>r.json()).then(d=>{
        if (!(d && d.success)) { alert(d && d.error? d.error : 'Update failed'); this.value=this.getAttribute('data-original')||'Pending'; }
        else { this.setAttribute('data-original', status); }
      }).catch(()=>{ alert('Update failed'); this.value=this.getAttribute('data-original')||'Pending'; });
    });
  });
  document.querySelectorAll('.btn-delete').forEach(btn=>{
    btn.addEventListener('click', function(){
      const id = this.getAttribute('data-id');
      if (!id) return;
      if (!confirm('Delete this document?')) return;
      const fd = new FormData();
      fd.append('_token','PGS.page.csrf');
      fd.append('action', 'delete_upload');
      fd.append('id', id);
      fetch('operations_review_new.php', { method:'POST', body: fd })
        .then(r=>r.json()).then(d=>{
          if (d && d.success) { location.reload(); }
          else { alert(d && d.error ? d.error : 'Delete failed'); }
        }).catch(()=> alert('Delete failed'));
    });
  });
