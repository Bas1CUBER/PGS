const canEdit = PGS.page.canEdit;
    const canInput = PGS.page.canInput;

    const chart = new Chart(document.getElementById('barChart'), {
      type: 'bar',
      data: {
        labels: PGS.page.chartYears,
        datasets: [{
          label: 'Employment Rate',
          data: PGS.page.chartCounts,
          backgroundColor: [
            '#0d6efd', // 2024 Blue
            '#dc3545', // 2025 Red
            '#198754', // 2026 Green
            '#ffc107', // 2027 Yellow
            '#6f42c1', // 2028 Violet
            '#d63384'  // 2029 Pink
          ],
          borderRadius: 6
        }]
      },
      options: {
        responsive: true,
        scales: { y: { beginAtZero: true, ticks: { callback: v => v + '%' } } },
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ctx.raw + '%' } } }
      }
    });

    document.getElementById('addRow1')?.addEventListener('click', () => {
      if (!canInput) return;
      document.getElementById('emp_id').value = '';
      ['emp_registry','emp_program','emp_entry_emp','emp_entry_occ','emp_after_emp','emp_after_occ','emp_remarks'].forEach(id => document.getElementById(id).value = '');
      new bootstrap.Modal(document.getElementById('empModal')).show();
    });
    document.getElementById('addRow2')?.addEventListener('click', () => {
      if (!canInput) return;
      document.getElementById('health_id').value = '';
      ['health_registry','health_program','overall_during','overall_after','physical_during','physical_after','mental_during','mental_after','social_during','social_after','environment_during','environment_after'].forEach(id => document.getElementById(id).value = '');
      new bootstrap.Modal(document.getElementById('healthModal')).show();
    });

    document.querySelectorAll('.edit-emp').forEach(btn => {
      btn.addEventListener('click', () => {
        const tr = btn.closest('tr'); const cells = tr.querySelectorAll('td');
        document.getElementById('emp_id').value = btn.getAttribute('data-id');
        document.getElementById('emp_registry').value = cells[0].textContent.trim();
        document.getElementById('emp_program').value = cells[1].textContent.trim();
        document.getElementById('emp_entry_emp').value = cells[3].textContent.trim();
        document.getElementById('emp_entry_occ').value = cells[4].textContent.trim();
        document.getElementById('emp_after_emp').value = cells[5].textContent.trim();
        document.getElementById('emp_after_occ').value = cells[6].textContent.trim();
        document.getElementById('emp_remarks').value = cells[7].textContent.trim();
        new bootstrap.Modal(document.getElementById('empModal')).show();
      });
    });
    document.querySelectorAll('.edit-health').forEach(btn => {
      btn.addEventListener('click', () => {
        const tr = btn.closest('tr'); const cells = tr.querySelectorAll('td');
        document.getElementById('health_id').value = btn.getAttribute('data-id');
        document.getElementById('health_registry').value = cells[0].textContent.trim();
        document.getElementById('health_program').value = cells[1].textContent.trim();
        document.getElementById('overall_during').value = cells[2].textContent.trim();
        document.getElementById('overall_after').value = cells[3].textContent.trim();
        document.getElementById('physical_during').value = cells[4].textContent.trim();
        document.getElementById('physical_after').value = cells[5].textContent.trim();
        document.getElementById('mental_during').value = cells[6].textContent.trim();
        document.getElementById('mental_after').value = cells[7].textContent.trim();
        document.getElementById('social_during').value = cells[8].textContent.trim();
        document.getElementById('social_after').value = cells[9].textContent.trim();
        document.getElementById('environment_during').value = cells[10].textContent.trim();
        document.getElementById('environment_after').value = cells[11].textContent.trim();
        new bootstrap.Modal(document.getElementById('healthModal')).show();
      });
    });
    document.querySelectorAll('.lock-emp').forEach(btn => {
      btn.addEventListener('click', async () => {
        const id = btn.getAttribute('data-id');
        const locked = btn.getAttribute('data-locked') === '1';
        const fd = new FormData(); fd.append('action','toggle_row_lock'); fd.append('table','employment'); fd.append('id', id); fd.append('row_locked', locked ? '0':'1');
        const r = await fetch(location.href, { method:'POST', body:fd }); const t = await r.text();
        try { const j = JSON.parse(t); if (j.success) location.reload(); else alert('Failed'); } catch(e) { alert('Failed'); }
      });
    });
    document.querySelectorAll('.lock-health').forEach(btn => {
      btn.addEventListener('click', async () => {
        const id = btn.getAttribute('data-id');
        const locked = btn.getAttribute('data-locked') === '1';
        const fd = new FormData(); fd.append('action','toggle_row_lock'); fd.append('table','health'); fd.append('id', id); fd.append('row_locked', locked ? '0':'1');
        const r = await fetch(location.href, { method:'POST', body:fd }); const t = await r.text();
        try { const j = JSON.parse(t); if (j.success) location.reload(); else alert('Failed'); } catch(e) { alert('Failed'); }
      });
    });

    document.getElementById('empForm')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const id = document.getElementById('emp_id').value.trim();
      const fd = new FormData(e.target);
      fd.append('action', id ? 'edit_employment' : 'add_employment');
      if (!id && !canInput) return;
      const reg = document.getElementById('emp_registry').value.trim();
      if (!/^\d{4}-\d{4}$/.test(reg)) { await Swal.fire({ icon:'error', title:'Invalid Registry No.', text:'Use format YYYY-XXXX (numbers only).' }); return; }
      const r = await fetch(location.href, { method:'POST', body:fd });
      let ok = r.ok, j = null;
      try { j = await r.json(); } catch(e) {}
      if (ok && j && j.success) {
        const modal = bootstrap.Modal.getInstance(document.getElementById('empModal')); modal?.hide();
        await Swal.fire({ icon:'success', title:'Changes Saved', text:'Employment tracker entry saved successfully.', timer:1500, showConfirmButton:false });
        location.reload();
      } else if (ok && !j) {
        const modal = bootstrap.Modal.getInstance(document.getElementById('empModal')); modal?.hide();
        await Swal.fire({ icon:'success', title:'Changes Saved', text:'Employment tracker entry saved successfully.', timer:1500, showConfirmButton:false });
        location.reload();
      } else {
        await Swal.fire({ icon:'error', title:'Failed', text:(j && j.error) ? j.error : 'Unable to save changes.' });
      }
    });
    document.getElementById('healthForm')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const id = document.getElementById('health_id').value.trim();
      const fd = new FormData(e.target);
      fd.append('action', id ? 'edit_health' : 'add_health');
      if (!id && !canInput) return;
      const reg = document.getElementById('health_registry').value.trim();
      if (!/^\d{4}-\d{4}$/.test(reg)) { await Swal.fire({ icon:'error', title:'Invalid Registry No.', text:'Use format YYYY-XXXX (numbers only).' }); return; }
      const r = await fetch(location.href, { method:'POST', body:fd });
      let ok = r.ok, j = null;
      try { j = await r.json(); } catch(e) {}
      if (ok && j && j.success) {
        const modal = bootstrap.Modal.getInstance(document.getElementById('healthModal')); modal?.hide();
        await Swal.fire({ icon:'success', title:'Changes Saved', text:'Health score entry saved successfully.', timer:1500, showConfirmButton:false });
        location.reload();
      } else if (ok && !j) {
        const modal = bootstrap.Modal.getInstance(document.getElementById('healthModal')); modal?.hide();
        await Swal.fire({ icon:'success', title:'Changes Saved', text:'Health score entry saved successfully.', timer:1500, showConfirmButton:false });
        location.reload();
      } else {
        await Swal.fire({ icon:'error', title:'Failed', text:(j && j.error) ? j.error : 'Unable to save changes.' });
      }
    });
    document.querySelectorAll('.delete-emp').forEach(btn => {
      btn.addEventListener('click', async () => {
        const id = btn.getAttribute('data-id');
        const c = await Swal.fire({ icon:'warning', title:'Delete Row?', text:'This action cannot be undone.', showCancelButton:true, confirmButtonText:'Delete' });
        if (!c.isConfirmed) return;
        const fd = new FormData(); fd.append('action','delete_employment'); fd.append('id', id);
        const r = await fetch(location.href, { method:'POST', body:fd }); let j = null; try { j = await r.json(); } catch(e) {}
        if (j && j.success) { await Swal.fire({ icon:'success', title:'Deleted', timer:1200, showConfirmButton:false }); location.reload(); }
        else { await Swal.fire({ icon:'error', title:'Failed to delete' }); }
      });
    });
    document.querySelectorAll('.delete-health').forEach(btn => {
      btn.addEventListener('click', async () => {
        const id = btn.getAttribute('data-id');
        const c = await Swal.fire({ icon:'warning', title:'Delete Row?', text:'This action cannot be undone.', showCancelButton:true, confirmButtonText:'Delete' });
        if (!c.isConfirmed) return;
        const fd = new FormData(); fd.append('action','delete_health'); fd.append('id', id);
        const r = await fetch(location.href, { method:'POST', body:fd }); let j = null; try { j = await r.json(); } catch(e) {}
        if (j && j.success) { await Swal.fire({ icon:'success', title:'Deleted', timer:1200, showConfirmButton:false }); location.reload(); }
        else { await Swal.fire({ icon:'error', title:'Failed to delete' }); }
      });
    });

    // Lock toggle (admin)
    document.getElementById('lockBtn')?.addEventListener('click', async () => {
      const locked = PGS.page.isLocked;
      const next = locked ? 0 : 1;
      const fd = new FormData();
      fd.append('action','toggle_lock');
      fd.append('is_locked', String(next));
      const r = await fetch(location.href, { method:'POST', body:fd });
      const t = await r.text();
      try {
        const j = JSON.parse(t);
        if (j.success) location.reload();
        else alert('Failed to toggle lock');
      } catch(e) {
        alert('Failed to toggle lock');
      }
    });
