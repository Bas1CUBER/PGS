const labels = PGS.page.chartLabels;
  const data2024 = PGS.page.c2024;
  const data2025 = PGS.page.c2025;
  const data2026 = PGS.page.c2026;
  const data2027 = PGS.page.c2027;
  function formatCompactValue(value) {
    const num = Number(value) || 0;
    const absNum = Math.abs(num);
    if (absNum >= 1000000) return (num / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
    if (absNum >= 1000) return (num / 1000).toFixed(1).replace(/\.0$/, '') + 'K';
    return num.toLocaleString();
  }
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
      plugins: [ChartDataLabels],
      options: {
        responsive: true,
        plugins: {
          tooltip: {
            callbacks: {
              label: (context) => `${context.dataset.label}: ${formatCompactValue(context.parsed.y)}`
            }
          },
          datalabels: {
            anchor: 'end',
            align: 'top',
            color: '#2c3e50',
            font: { weight: '600', size: 10 },
            formatter: (value) => formatCompactValue(value),
            display: (ctx) => ctx.dataset.data[ctx.dataIndex] > 0
          }
        },
        scales: {
          y: { display:false, beginAtZero:true, min:0, max:3000000, ticks:{ stepSize:500000 }, grid:{ display:false }, border:{ display:false } }
        }
      }
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
