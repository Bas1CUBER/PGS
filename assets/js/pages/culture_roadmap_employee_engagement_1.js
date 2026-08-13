const trendLabels = PGS.page.chartTrendLabels;
    const trendActual = PGS.page.chartTrendActualData;
    const trendTarget = PGS.page.chartTrendTargetData;

    new Chart(document.getElementById('engagementChart'), {
      type: 'line',
      data: {
        labels: trendLabels,
        datasets: [
          {
            label: 'Actual (%)',
            data: trendActual,
            borderColor: '#0d6efd',
            backgroundColor: '#0d6efd',
            borderWidth: 3,
            pointRadius: 4,
            pointHoverRadius: 5,
            tension: 0.25
          },
          {
            label: 'Target (%)',
            data: trendTarget,
            borderColor: '#198754',
            backgroundColor: '#198754',
            borderDash: [6, 6],
            borderWidth: 2,
            pointRadius: 3,
            pointHoverRadius: 4,
            tension: 0
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            min: 0,
            max: 100,
            ticks: { callback: (v)=> Number(v).toFixed(0)+'%' }
          }
        },
        plugins: {
          legend: { display: true, position: 'bottom' },
          tooltip: {
            callbacks: {
              label: (ctx) => `${ctx.dataset.label}: ${Number(ctx.parsed.y).toFixed(2)}%`
            }
          }
        }
      }
    });

    document.getElementById('addQBtn')?.addEventListener('click', () => {
      new bootstrap.Modal(document.getElementById('addQModal')).show();
    });
    document.getElementById('addQForm')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      fd.append('action', 'add_question');
      const r = await fetch(location.href, { method:'POST', body:fd });
      let j = null; try { j = await r.json(); } catch(e){}
      if (j && j.success) {
        await Swal.fire({ icon:'success', title:'Added', text:'New row added successfully.', timer:1200, showConfirmButton:false });
        location.reload();
      } else {
        await Swal.fire({ icon:'error', title:'Failed', text: j?.error || 'Unknown error' });
      }
    });

    document.querySelectorAll('.edit').forEach(btn => {
      btn.addEventListener('click', async () => {
        if (PGS.page.role !== 'admin') { return; }
        const sec = btn.getAttribute('data-sec');
        const q = btn.getAttribute('data-q');
        const { value: year } = await Swal.fire({
          title: 'Select Year',
          input: 'select',
          inputOptions: { '2025':'2025','2026':'2026','2027':'2027','2028':'2028' },
          inputValue: '2025',
          showCancelButton: true
        });
        if (year === undefined) return;
        const { value: val } = await Swal.fire({
          title: 'Enter percentage ('+year+')',
          input: 'text',
          inputAttributes: { placeholder: 'e.g., 82.50' },
          showCancelButton: true
        });
        if (val === undefined) return;
        const fd = new FormData(); fd.append('action','save_value'); fd.append('section_key',sec); fd.append('question_no',q); fd.append('year',year); fd.append('percent', val);
        const r = await fetch(location.href, { method:'POST', body:fd }); let j=null; try{ j=await r.json(); }catch(e){}
        if (j && j.success) { await Swal.fire({ icon:'success', title:'Saved', timer:1000, showConfirmButton:false }); location.reload(); } else await Swal.fire({ icon:'error', title:'Failed' });
      });
    });
    document.querySelectorAll('.delete-row').forEach(btn => {
      btn.addEventListener('click', async () => {
        if (PGS.page.role !== 'admin') { return; }
        const sec = btn.getAttribute('data-sec');
        const q = btn.getAttribute('data-q');
        
        const c = await Swal.fire({ 
          icon:'warning', 
          title:'Delete Row?', 
          text: 'This will delete the question and all associated data for all years. This action cannot be undone.',
          showCancelButton:true, 
          confirmButtonText:'Delete Row',
          confirmButtonColor: '#dc3545'
        }); 
        if (!c.isConfirmed) return;
        
        const fd = new FormData(); 
        fd.append('action','delete_row'); 
        fd.append('section_key',sec); 
        fd.append('question_no',q);
        
        const r = await fetch(location.href, { method:'POST', body:fd }); 
        let j=null; try{ j=await r.json(); }catch(e){}
        
        if (j && j.success) { 
          await Swal.fire({ icon:'success', title:'Deleted', timer:900, showConfirmButton:false }); 
          location.reload(); 
        } else { 
          await Swal.fire({ icon:'error', title:'Failed' }); 
        }
      });
    });
