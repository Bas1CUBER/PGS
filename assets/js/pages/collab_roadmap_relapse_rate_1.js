function donut(el, rate, withLegend=false) {
      new Chart(document.getElementById(el), {
        type: 'doughnut',
        data: { labels: withLegend ? ['Relapse','Non-relapse'] : [], datasets: [{ data: [rate, Math.max(0, 100-rate)], backgroundColor: withLegend ? ['#dc3545','#77c281'] : ['#77c281','#e5efe6'], borderWidth: 0 }] },
        options: { responsive:true, plugins: { legend: { display: withLegend }, tooltip: { enabled: true } }, cutout: '65%' }
      });
    }
    donut('donutY2024', PGS.page.annualRates[2024] ?? 0);
    donut('donutY2025', PGS.page.annualRates[2025] ?? 0);
    donut('donutY2026', PGS.page.annualRates[2026] ?? 0);
    donut('donutY2027', PGS.page.annualRates[2027] ?? 0);
    donut('donutY2028', PGS.page.annualRates[2028] ?? 0);

      document.querySelectorAll('.js-summary-edit').forEach(btn => btn.addEventListener('click', (e) => {
      const year = e.currentTarget.getAttribute('data-year');
      const tr = e.currentTarget.closest('tr');
      const inputs = tr.querySelectorAll('.js-sum-inp');
      inputs.forEach(inp => inp.disabled = false);
      e.currentTarget.classList.add('d-none');
      tr.querySelector('.js-summary-save').classList.remove('d-none');
    }));
    document.querySelectorAll('.js-summary-save').forEach(btn => btn.addEventListener('click', async (e) => {
      const btn = e.currentTarget;
      const originalText = btn.textContent;
      btn.disabled = true;
      btn.textContent = 'Saving...';
      
      const year = btn.getAttribute('data-year');
      const tr = btn.closest('tr');
      const inputs = tr.querySelectorAll('.js-sum-inp');
      const fd = new FormData();
      fd.append('action', 'save_summary_row');
      fd.append('year', year);
      inputs.forEach(inp => fd.append(inp.getAttribute('data-field'), inp.value));
      
      try {
        const r = await fetch(location.href, { method:'POST', body:fd });
        let j = null;
        try { j = await r.json(); } catch(e) { console.error('Parse error:', e); }
        
        if (j && j.success) {
          location.reload(); 
        } else {
          btn.disabled = false;
          btn.textContent = originalText;
          await Swal.fire({ icon:'error', title:'Failed', text: j?.error || 'Unknown error' });
        }
      } catch (err) {
        btn.disabled = false;
        btn.textContent = originalText;
        console.error(err);
        await Swal.fire({ icon:'error', title:'Error', text: 'Network or server error' });
      }
    }));
    document.querySelectorAll('.js-summary-lock').forEach(btn => btn.addEventListener('click', async () => {
      const year = btn.getAttribute('data-year');
      const locked = btn.getAttribute('data-locked') === '1';
      const fd = new FormData();
      fd.append('action', 'lock_summary');
      fd.append('year', year);
      fd.append('row_locked', locked ? '0' : '1');
      const r = await fetch(location.href, { method:'POST', body:fd }); let j=null; try{ j=await r.json(); }catch(e){}
      if (j && j.success) location.reload(); else await Swal.fire({ icon:'error', title:'Failed' });
    }));
    document.querySelectorAll('.js-summary-del').forEach(btn => btn.addEventListener('click', async () => {
      const year = btn.getAttribute('data-year');
      const c = await Swal.fire({ icon:'warning', title:'Delete Row?', showCancelButton:true, confirmButtonText:'Delete' });
      if (!c.isConfirmed) return;
      const fd = new FormData();
      fd.append('action', 'delete_summary');
      fd.append('year', year);
      const r = await fetch(location.href, { method:'POST', body:fd }); let j=null; try{ j=await r.json(); }catch(e){}
      if (j && j.success) location.reload(); else await Swal.fire({ icon:'error', title:'Failed' });
    }));

    document.getElementById('addT1')?.addEventListener('click', () => { document.getElementById('t1_id').value=''; document.getElementById('t1_program').value=''; document.getElementById('t1_grads').value=''; new bootstrap.Modal(document.getElementById('t1Modal')).show(); });
    document.getElementById('addT2')?.addEventListener('click', () => { document.getElementById('t2_id').value=''; document.getElementById('t2_registry').value=''; document.getElementById('t2_program').value=''; document.getElementById('t2_date').value=''; new bootstrap.Modal(document.getElementById('t2Modal')).show(); });

    document.querySelectorAll('.edit-grad').forEach(btn => btn.addEventListener('click', () => {
      const tr = btn.closest('tr'); const cells = tr.querySelectorAll('td');
      document.getElementById('t1_id').value = btn.getAttribute('data-id');
      document.getElementById('t1_program').value = cells[0].textContent.trim();
      document.getElementById('t1_grads').value = cells[1].textContent.trim();
      new bootstrap.Modal(document.getElementById('t1Modal')).show();
    }));
    document.querySelectorAll('.edit-list').forEach(btn => btn.addEventListener('click', () => {
      const tr = btn.closest('tr'); const cells = tr.querySelectorAll('td');
      document.getElementById('t2_id').value = btn.getAttribute('data-id');
      document.getElementById('t2_registry').value = cells[0].textContent.trim();
      document.getElementById('t2_program').value = cells[1].textContent.trim();
      document.getElementById('t2_date').value = cells[2].textContent.trim();
      new bootstrap.Modal(document.getElementById('t2Modal')).show();
    }));
    document.querySelectorAll('.lock-grad').forEach(btn => btn.addEventListener('click', async () => {
      const id = btn.getAttribute('data-id'); const locked = btn.getAttribute('data-locked') === '1';
      const fd = new FormData(); fd.append('action','toggle_lock'); fd.append('table','grad'); fd.append('id', id); fd.append('row_locked', locked ? '0':'1');
      const r = await fetch(location.href, { method:'POST', body:fd }); let j=null; try{ j=await r.json(); }catch(e){}
      if (j && j.success) location.reload(); else await Swal.fire({ icon:'error', title:'Failed' });
    }));
    document.querySelectorAll('.lock-list').forEach(btn => btn.addEventListener('click', async () => {
      const id = btn.getAttribute('data-id'); const locked = btn.getAttribute('data-locked') === '1';
      const fd = new FormData(); fd.append('action','toggle_lock'); fd.append('table','list'); fd.append('id', id); fd.append('row_locked', locked ? '0':'1');
      const r = await fetch(location.href, { method:'POST', body:fd }); let j=null; try{ j=await r.json(); }catch(e){}
      if (j && j.success) location.reload(); else await Swal.fire({ icon:'error', title:'Failed' });
    }));
    document.querySelectorAll('.del-grad').forEach(btn => btn.addEventListener('click', async () => {
      const id = btn.getAttribute('data-id'); const c = await Swal.fire({ icon:'warning', title:'Delete Row?', showCancelButton:true, confirmButtonText:'Delete' }); if(!c.isConfirmed) return;
      const fd = new FormData(); fd.append('action','delete_row'); fd.append('table','grad'); fd.append('id', id);
      const r = await fetch(location.href, { method:'POST', body:fd }); let j=null; try{ j=await r.json(); }catch(e){}
      if (j && j.success) { await Swal.fire({ icon:'success', title:'Deleted', timer:1000, showConfirmButton:false }); location.reload(); } else await Swal.fire({ icon:'error', title:'Failed' });
    }));
    document.querySelectorAll('.del-list').forEach(btn => btn.addEventListener('click', async () => {
      const id = btn.getAttribute('data-id'); const c = await Swal.fire({ icon:'warning', title:'Delete Row?', showCancelButton:true, confirmButtonText:'Delete' }); if(!c.isConfirmed) return;
      const fd = new FormData(); fd.append('action','delete_row'); fd.append('table','list'); fd.append('id', id);
      const r = await fetch(location.href, { method:'POST', body:fd }); let j=null; try{ j=await r.json(); }catch(e){}
      if (j && j.success) { await Swal.fire({ icon:'success', title:'Deleted', timer:1000, showConfirmButton:false }); location.reload(); } else await Swal.fire({ icon:'error', title:'Failed' });
    }));

    document.getElementById('t1Form')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const id = document.getElementById('t1_id').value.trim();
      const fd = new FormData(e.target);
      fd.append('action', id ? 'edit_grad' : 'add_grad');
      const r = await fetch(location.href, { method:'POST', body:fd }); let j=null; try{ j=await r.json(); }catch(e){}
      if (j && j.success) { const m = bootstrap.Modal.getInstance(document.getElementById('t1Modal')); m?.hide(); await Swal.fire({ icon:'success', title:'Changes Saved', timer:1200, showConfirmButton:false }); location.reload(); } else await Swal.fire({ icon:'error', title:'Failed' });
    });
    document.getElementById('t2Form')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const id = document.getElementById('t2_id').value.trim();
      const reg = document.getElementById('t2_registry').value.trim();
      if (!/^\d{4}-\d{4}$/.test(reg)) { await Swal.fire({ icon:'error', title:'Invalid Registry No.', text:'Use YYYY-XXXX' }); return; }
      const fd = new FormData(e.target);
      fd.append('action', id ? 'edit_list' : 'add_list');
      const r = await fetch(location.href, { method:'POST', body:fd }); let j=null; try{ j=await r.json(); }catch(e){}
      if (j && j.success) { const m = bootstrap.Modal.getInstance(document.getElementById('t2Modal')); m?.hide(); await Swal.fire({ icon:'success', title:'Changes Saved', timer:1200, showConfirmButton:false }); location.reload(); } else await Swal.fire({ icon:'error', title:'Failed' });
    });
