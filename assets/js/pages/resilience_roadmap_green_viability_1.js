const addForm = document.getElementById('formAdd');
  if (addForm) {
    addForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(addForm);
      fd.append('action','add_row');
      const r = await fetch(location.href, {method:'POST', body:fd});
      const j = await r.json();
      if (!j.ok) Swal.fire({icon:'error', title:'Add failed', text:j.msg || 'Please try again'}); else location.reload();
    });
  }
  document.querySelectorAll('.js-text').forEach(inp => {
    inp.addEventListener('change', async (e) => {
      const tr = e.currentTarget.closest('tr');
      const fd = new FormData();
      fd.append('action','save_cell');
      fd.append('id', tr.dataset.id);
      fd.append('field', e.currentTarget.dataset.field);
      fd.append('value', e.currentTarget.value);
      const r = await fetch(location.href, {method:'POST', body:fd});
      const j = await r.json();
      if (!j.ok) Swal.fire({icon:'error', title:'Save failed', text:j.msg || 'Please try again'}); else location.reload();
    });
  });
  document.querySelectorAll('.js-num').forEach(inp => {
    inp.addEventListener('change', async (e) => {
      const tr = e.currentTarget.closest('tr');
      const fd = new FormData();
      fd.append('action','save_cell');
      fd.append('id', tr.dataset.id);
      fd.append('field', e.currentTarget.dataset.field);
      fd.append('value', e.currentTarget.value);
      const r = await fetch(location.href, {method:'POST', body:fd});
      const j = await r.json();
      if (!j.ok) Swal.fire({icon:'error', title:'Save failed', text:j.msg || 'Please try again'}); else location.reload();
    });
  });
  document.querySelectorAll('.js-lock').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      const tr = e.currentTarget.closest('tr');
      const fd = new FormData();
      fd.append('action','set_lock');
      fd.append('id', tr.dataset.id);
      fd.append('locked', e.currentTarget.dataset.locked);
      const r = await fetch(location.href, {method:'POST', body:fd});
      const j = await r.json();
      if (!j.ok) Swal.fire({icon:'error', title:'Action failed', text:j.msg || 'Please try again'}); else location.reload();
    });
  });
  document.querySelectorAll('.js-del').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      const tr = e.currentTarget.closest('tr');
      const ok = await Swal.fire({icon:'warning', title:'Delete row?', showCancelButton:true, confirmButtonText:'Delete'});
      if (!ok.isConfirmed) return;
      const fd = new FormData();
      fd.append('action','delete_row');
      fd.append('id', tr.dataset.id);
      const r = await fetch(location.href, {method:'POST', body:fd});
      const j = await r.json();
      if (!j.ok) Swal.fire({icon:'error', title:'Delete failed', text:j.msg || 'Please try again'}); else tr.remove();
    });
  });
