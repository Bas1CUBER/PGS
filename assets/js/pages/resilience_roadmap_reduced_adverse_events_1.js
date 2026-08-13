// Chart
  const labels = PGS.page.labels;
  const data2024 = PGS.page.d2024;
  const data2025 = PGS.page.d2025;
  const data2026 = PGS.page.d2026;
  const data2027 = PGS.page.d2027;
  if (labels.length > 0) {
    new Chart(document.getElementById('chart').getContext('2d'), {
      type: 'bar',
      data: {
        labels,
        datasets: [
          { label:'2024', data: data2024, backgroundColor: '#3b82f6' },
          { label:'2025', data: data2025, backgroundColor: '#ef4444' },
          { label:'2026', data: data2026, backgroundColor: '#22c55e' },
          { label:'2027', data: data2027, backgroundColor: '#f59e0b' },
        ]
      },
      options: {
        responsive: true,
        scales: { y: { beginAtZero:true, ticks:{ stepSize:1 } } }
      }
    });
  }

  // Main table
  document.querySelectorAll('.js-text').forEach(inp => {
    inp.addEventListener('change', async (e) => {
      const tr = e.currentTarget.closest('tr');
      const fd = new FormData();
      fd.append('action','save_cell');
      fd.append('id', tr.dataset.id);
      fd.append('field', e.currentTarget.dataset.field);
      fd.append('value', e.currentTarget.value);
      const r = await fetch(location.href, { method:'POST', body:fd });
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
      const r = await fetch(location.href, { method:'POST', body:fd });
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
      const r = await fetch(location.href, { method:'POST', body:fd });
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
      const r = await fetch(location.href, { method:'POST', body:fd });
      const j = await r.json();
      if (!j.ok) Swal.fire({icon:'error', title:'Delete failed', text:j.msg || 'Please try again'}); else tr.remove();
    });
  });

  // Add row
  const formAdd = document.getElementById('formAdd');
  if (formAdd) {
    formAdd.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(formAdd);
      fd.append('action','add_row');
      const r = await fetch(location.href, { method:'POST', body:fd });
      const j = await r.json();
      if (!j.ok) Swal.fire({icon:'error', title:'Add failed', text:j.msg || 'Please try again'}); else location.reload();
    });
  }

  // Notes table
  const formAddNote = document.getElementById('formAddNote');
  if (formAddNote) {
    formAddNote.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(formAddNote);
      fd.append('action','add_note');
      const r = await fetch(location.href, { method:'POST', body:fd });
      const j = await r.json();
      if (!j.ok) Swal.fire({icon:'error', title:'Add failed', text:j.msg || 'Please try again'}); else location.reload();
    });
  }
  document.querySelectorAll('.js-note-text').forEach(inp => {
    inp.addEventListener('change', async (e) => {
      const tr = e.currentTarget.closest('tr');
      const fd = new FormData();
      fd.append('action','save_note');
      fd.append('id', tr.dataset.id);
      fd.append('field', e.currentTarget.dataset.field);
      fd.append('value', e.currentTarget.value);
      const r = await fetch(location.href, { method:'POST', body:fd });
      const j = await r.json();
      if (!j.ok) Swal.fire({icon:'error', title:'Save failed', text:j.msg || 'Please try again'}); else location.reload();
    });
  });
  document.querySelectorAll('.js-note-num').forEach(inp => {
    inp.addEventListener('change', async (e) => {
      const tr = e.currentTarget.closest('tr');
      const fd = new FormData();
      fd.append('action','save_note');
      fd.append('id', tr.dataset.id);
      fd.append('field', e.currentTarget.dataset.field);
      fd.append('value', e.currentTarget.value);
      const r = await fetch(location.href, { method:'POST', body:fd });
      const j = await r.json();
      if (!j.ok) Swal.fire({icon:'error', title:'Save failed', text:j.msg || 'Please try again'}); else location.reload();
    });
  });
  document.querySelectorAll('.js-lock-note').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      const tr = e.currentTarget.closest('tr');
      const fd = new FormData();
      fd.append('action','set_lock_note');
      fd.append('id', tr.dataset.id);
      fd.append('locked', e.currentTarget.dataset.locked);
      const r = await fetch(location.href, { method:'POST', body:fd });
      const j = await r.json();
      if (!j.ok) Swal.fire({icon:'error', title:'Action failed', text:j.msg || 'Please try again'}); else location.reload();
    });
  });
  document.querySelectorAll('.js-del-note').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      const tr = e.currentTarget.closest('tr');
      const ok = await Swal.fire({icon:'warning', title:'Delete row?', showCancelButton:true, confirmButtonText:'Delete'});
      if (!ok.isConfirmed) return;
      const fd = new FormData();
      fd.append('action','delete_note');
      fd.append('id', tr.dataset.id);
      const r = await fetch(location.href, { method:'POST', body:fd });
      const j = await r.json();
      if (!j.ok) Swal.fire({icon:'error', title:'Delete failed', text:j.msg || 'Please try again'}); else tr.remove();
    });
  });
