const ROLE = PGS.page.role;
  // Table 1 is static; counts derive from Table 2. No inline edits.

  // Table 2 add
  const formAdd = document.getElementById('formAdd');
  if (formAdd) {
    formAdd.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(formAdd);
      fd.append('action','add_table2');
      const r = await fetch(location.href, {method:'POST', body:fd});
      const j = await r.json();
      if (!j.ok) {
        Swal.fire({icon:'error', title:'Add failed', text:j.msg || 'Please try again'});
      } else {
        location.reload();
      }
    });
  }
  // Table 1 add
  const formAddT1 = document.getElementById('formAddT1');
  if (formAddT1) {
    formAddT1.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(formAddT1);
      fd.append('action','add_table1');
      const r = await fetch(location.href, {method:'POST', body:fd});
      const j = await r.json();
      if (!j.ok) {
        Swal.fire({icon:'error', title:'Add row failed', text:j.msg || 'Please try again'});
      } else {
        location.reload();
      }
    });
  }
  // Focal add/delete Table 1
  const formFocalAddT1 = document.getElementById('formFocalAddT1');
  if (formFocalAddT1) {
    formFocalAddT1.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(formFocalAddT1);
      fd.append('action','focal_add_table1');
      const r = await fetch(location.href, {method:'POST', body:fd});
      const j = await r.json();
      if (!j.ok) {
        Swal.fire({icon:'error', title:'Add row failed', text:j.msg || 'Please try again'});
      } else {
        location.reload();
      }
    });
  }
  const formFocalDeleteT1 = document.getElementById('formFocalDeleteT1');
  if (formFocalDeleteT1) {
    formFocalDeleteT1.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(formFocalDeleteT1);
      fd.append('action','focal_delete_table1_person');
      const ok = await Swal.fire({icon:'warning', title:'Delete row?', text:'This will delete the selected personnel row.', showCancelButton:true, confirmButtonText:'Delete'});
      if (!ok.isConfirmed) return;
      const r = await fetch(location.href, {method:'POST', body:fd});
      const j = await r.json();
      if (!j.ok) {
        Swal.fire({icon:'error', title:'Delete failed', text:j.msg || 'Please try again'});
      } else {
        location.reload();
      }
    });
  }
  const formAdminDeleteT1 = document.getElementById('formAdminDeleteT1');
  if (formAdminDeleteT1) {
    formAdminDeleteT1.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(formAdminDeleteT1);
      fd.append('action','admin_delete_table1_person');
      const ok = await Swal.fire({icon:'warning', title:'Delete row?', text:'This will delete the selected personnel row.', showCancelButton:true, confirmButtonText:'Delete'});
      if (!ok.isConfirmed) return;
      const r = await fetch(location.href, {method:'POST', body:fd});
      const j = await r.json();
      if (!j.ok) {
        Swal.fire({icon:'error', title:'Delete failed', text:j.msg || 'Please try again'});
      } else {
        location.reload();
      }
    });
  }
  // Admin actions for table 2
  document.querySelectorAll('.js-t2').forEach(inp => {
    inp.addEventListener('change', async (e) => {
      const tr = e.currentTarget.closest('tr');
      const payload = new FormData();
      payload.append('action','save_table2');
      payload.append('id', tr.dataset.id);
      payload.append('field', e.currentTarget.dataset.field);
      payload.append('value', e.currentTarget.value);
      const r = await fetch(location.href, {method:'POST', body:payload});
      const j = await r.json();
      if (!j.ok) Swal.fire({icon:'error', title:'Save failed', text:j.msg || 'Please try again'});
    });
  });
  document.querySelectorAll('.js-lock').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      const tr = e.currentTarget.closest('tr');
      const locked = e.currentTarget.dataset.locked;
      const fd = new FormData();
      fd.append('action','set_lock_table2');
      fd.append('id', tr.dataset.id);
      fd.append('locked', locked);
      const r = await fetch(location.href, {method:'POST', body:fd});
      const j = await r.json();
      if (!j.ok) Swal.fire({icon:'error', title:'Action failed', text:j.msg || 'Please try again'});
      else location.reload();
    });
  });
  document.querySelectorAll('.js-del').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      const tr = e.currentTarget.closest('tr');
      const ok = await Swal.fire({icon:'warning', title:'Delete row?', showCancelButton:true, confirmButtonText:'Delete'});
      if (!ok.isConfirmed) return;
      const fd = new FormData();
      fd.append('action','delete_table2');
      fd.append('id', tr.dataset.id);
      const r = await fetch(location.href, {method:'POST', body:fd});
      const j = await r.json();
      if (!j.ok) Swal.fire({icon:'error', title:'Delete failed', text:j.msg || 'Please try again'});
      else tr.remove();
    });
  });

  // Edit modal
  const editModalEl = document.getElementById('editModal');
  let editModal;
  if (editModalEl) {
    editModal = new bootstrap.Modal(editModalEl);
    document.querySelectorAll('.js-edit').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.dataset.id;
        const serial = btn.dataset.serial || '';
        const name = btn.dataset.name || '';
        const participants = btn.dataset.participants || '';
        const date = btn.dataset.date || '';
        const form = document.getElementById('formEdit');
        form.querySelector('[name="id"]').value = id;
        form.querySelector('[name="serial_no"]').value = serial;
        form.querySelector('[name="name"]').value = name;
        form.querySelector('[name="participants"]').value = participants;
        form.querySelector('[name="date_label"]').value = date;
        editModal.show();
      });
    });
    const btnSaveEdit = document.getElementById('btnSaveEdit');
    if (btnSaveEdit) {
      btnSaveEdit.addEventListener('click', async () => {
        const form = document.getElementById('formEdit');
        const fd = new FormData(form);
        fd.append('action','edit_table2');
        const r = await fetch(location.href, {method:'POST', body:fd});
        const j = await r.json();
        if (!j.ok) {
          Swal.fire({icon:'error', title:'Save failed', text:j.msg || 'Please try again'});
        } else {
          location.reload();
        }
      });
    }
  }

  // Charts
  const years = PGS.page.years;
  const trainings = PGS.page.trainingsByYear;
  const hasB = trainings.some(v => Number(v) > 0);

  const ctxB = document.getElementById('chartB').getContext('2d');
  new Chart(ctxB, {
    type:'line',
    data:{
      labels: years,
      datasets:[{
        label: 'Trainings',
        data: trainings,
        tension: .3,
        borderColor: '#0b4aa2',
        backgroundColor: 'rgba(11,74,162,.15)',
        pointRadius: 4,
        pointBackgroundColor: '#0b4aa2',
      }]
    },
    options:{
      plugins:{ legend:{ display:false } },
      scales:{ y:{ beginAtZero:true, ticks:{ stepSize: 1 } } }
    }
  });
  if (!hasB) document.getElementById('noDataB').style.display = 'block'; else document.getElementById('noDataB').style.display='none';
