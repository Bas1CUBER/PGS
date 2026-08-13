// Add row (employee/focal)
    const addForm = document.getElementById('formAdd');
    if (addForm) {
      addForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(addForm);
        fd.append('action','add_row');
        try {
          const r = await fetch(location.href, { method:'POST', body:fd });
          const j = await r.json();
          if (!j.ok) throw new Error(j.msg || 'Add failed');
          location.reload();
        } catch(err) {
          Swal.fire({icon:'error', title:'Add failed', text: err.message });
        }
      });
    }
    // Admin actions
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
    document.querySelectorAll('.js-edit').forEach(btn => {
      btn.addEventListener('click', async (e) => {
        const tr = e.currentTarget.closest('tr');
        const cells = tr.querySelectorAll('td');
        const payload = {
          id: tr.dataset.id,
          staff_name: cells[0].textContent.trim(),
          request_date: cells[1].textContent.trim(),
          request_time: cells[2].textContent.trim(),
          released_date: cells[3].textContent.trim(),
          released_time: cells[4].textContent.trim(),
          retrieval_time: cells[5].textContent.trim().replace(/[^0-9.]/g,''),
        };
        const html = `
          <div class="row g-2">
            <div class="col-md-4"><label class="form-label">Staff Name</label><input class="form-control form-control-sm" id="e_staff" value="${payload.staff_name}"></div>
            <div class="col-md-4"><label class="form-label">Request Date</label><input type="date" class="form-control form-control-sm" id="e_rqdate" value="${payload.request_date}"></div>
            <div class="col-md-4"><label class="form-label">Request Time</label><input type="time" step="1" class="form-control form-control-sm" id="e_rqtime" value="${payload.request_time}"></div>
            <div class="col-md-4"><label class="form-label">Released Date</label><input type="date" class="form-control form-control-sm" id="e_rldate" value="${payload.released_date}"></div>
            <div class="col-md-4"><label class="form-label">Released Time</label><input type="time" step="1" class="form-control form-control-sm" id="e_rltime" value="${payload.released_time}"></div>
            <div class="col-md-4"><label class="form-label">Retrieval Time (minutes)</label><input type="number" step="0.01" min="0" class="form-control form-control-sm" id="e_rt" value="${payload.retrieval_time}"></div>
          </div>
        `;
        const sw = await Swal.fire({ title:'Edit Row', html, focusConfirm:false, showCancelButton:true, confirmButtonText:'Save' });
        if (!sw.isConfirmed) return;
        const fd = new FormData();
        fd.append('action','edit_row');
        fd.append('id', payload.id);
        fd.append('staff_name', document.getElementById('e_staff').value);
        fd.append('request_date', document.getElementById('e_rqdate').value);
        fd.append('request_time', document.getElementById('e_rqtime').value);
        fd.append('released_date', document.getElementById('e_rldate').value);
        fd.append('released_time', document.getElementById('e_rltime').value);
        fd.append('retrieval_time', document.getElementById('e_rt').value);
        const r = await fetch(location.href, { method:'POST', body:fd });
        const j = await r.json();
        if (!j.ok) Swal.fire({icon:'error', title:'Save failed', text:j.msg || 'Please try again'}); else location.reload();
      });
    });
