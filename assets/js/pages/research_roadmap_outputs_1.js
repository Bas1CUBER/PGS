const canInput = PGS.page.canInput;
    document.getElementById('addBtn')?.addEventListener('click', () => {
      if (!canInput) return;
      document.getElementById('f_id').value = '';
      ['f_no','f_title','f_topic','f_year'].forEach(id => document.getElementById(id).value = '');
      document.getElementById('f_phase').value = 'Planning';
      if (PGS.page.role === 'admin') { document.getElementById('f_outcome').value = ''; }
      new bootstrap.Modal(document.getElementById('editModal')).show();
    });
    document.querySelectorAll('.edit-row').forEach(btn => btn.addEventListener('click', () => {
      const tr = btn.closest('tr'); const cells = tr.querySelectorAll('td');
      document.getElementById('f_id').value = btn.getAttribute('data-id');
      document.getElementById('f_no').value = cells[0].textContent.trim();
      document.getElementById('f_title').value = cells[1].textContent.trim();
      document.getElementById('f_topic').value = cells[2].textContent.trim();
      document.getElementById('f_year').value = cells[3].textContent.trim();
      document.getElementById('f_phase').value = cells[4].textContent.trim();
      if (PGS.page.role === 'admin') { document.getElementById('f_outcome').value = cells[5].textContent.trim(); }
      new bootstrap.Modal(document.getElementById('editModal')).show();
    }));
    document.querySelectorAll('.lock-row').forEach(btn => btn.addEventListener('click', async () => {
      const id = btn.getAttribute('data-id'); const locked = btn.getAttribute('data-locked') === '1';
      const fd = new FormData(); fd.append('action','toggle_lock'); fd.append('id', id); fd.append('row_locked', locked ? '0':'1');
      const r = await fetch(location.href, { method:'POST', body:fd }); let j=null; try{ j=await r.json(); }catch(e){}
      if (j && j.success) location.reload(); else await Swal.fire({ icon:'error', title:'Failed' });
    }));
    document.querySelectorAll('.del-row').forEach(btn => btn.addEventListener('click', async () => {
      const id = btn.getAttribute('data-id'); const c = await Swal.fire({ icon:'warning', title:'Delete Row?', showCancelButton:true, confirmButtonText:'Delete' }); if(!c.isConfirmed) return;
      const fd = new FormData(); fd.append('action','delete_output'); fd.append('id', id);
      const r = await fetch(location.href, { method:'POST', body:fd }); let j=null; try{ j=await r.json(); }catch(e){}
      if (j && j.success) { await Swal.fire({ icon:'success', title:'Deleted', timer:1000, showConfirmButton:false }); location.reload(); } else await Swal.fire({ icon:'error', title:'Failed' });
    }));
    document.getElementById('editForm')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const id = document.getElementById('f_id').value.trim();
      const fd = new FormData(e.target);
      fd.append('action', id ? 'edit_output' : 'add_output');
      const r = await fetch(location.href, { method:'POST', body:fd }); let j=null; try{ j=await r.json(); }catch(e){}
      if (j && j.success) { const m = bootstrap.Modal.getInstance(document.getElementById('editModal')); m?.hide(); await Swal.fire({ icon:'success', title:'Changes Saved', timer:1200, showConfirmButton:false }); location.reload(); } else await Swal.fire({ icon:'error', title:'Failed' });
    });
