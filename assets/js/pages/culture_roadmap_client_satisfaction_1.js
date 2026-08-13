const YEAR = PGS.page.year;
    const ROLE = PGS.page.role;
    // Helpers for XLSX export
    function sanitizeSheetName(name) {
      return name.replace(/[\\/*?:\[\]]/g, '').substring(0,31) || 'Sheet';
    }
    function cloneTableForExport(tableEl, tableKey) {
      const clone = tableEl.cloneNode(true);
      // Replace inputs with numeric values; scale 0..1 to 0..100 only for Table 1
      clone.querySelectorAll('input.cs-input').forEach(inp => {
        const td = inp.closest('td');
        let v = inp.value.trim();
        if (v === '') { td.textContent = ''; return; }
        let num = parseFloat(v);
        if (!Number.isFinite(num)) { td.textContent = v; return; }
        // Only scale for Table 1 (percentage table)
        if (tableKey == '1' && num > 0 && num <= 1) num = num * 100;
        num = Math.round(num * 1000) / 1000;
        td.textContent = String(num);
      });
      // Remove percent suffix visuals
      clone.querySelectorAll('.input-group-text, .percent-suffix').forEach(el => el.remove());
      // Remove Actions column if present
      const thead = clone.querySelector('thead');
      let actionIndex = -1;
      if (thead) {
        const ths = Array.from(thead.querySelectorAll('th'));
        actionIndex = ths.findIndex(th => th.textContent.trim().toLowerCase() === 'actions');
      }
      if (actionIndex >= 0) {
        clone.querySelectorAll('tr').forEach(tr => {
          const cells = tr.querySelectorAll('th, td');
          if (cells[actionIndex]) cells[actionIndex].remove();
        });
      }
      return clone;
    }
    function exportSingleTableXLSX(tableKey, title) {
      try {
        const wrap = document.getElementById('wrap_' + tableKey);
        if (!wrap) { Swal.fire({icon:'error', title:'Export failed', text:'Table not found'}); return; }
        const table = wrap.querySelector('table');
        if (!table) { Swal.fire({icon:'error', title:'Export failed', text:'Table not found'}); return; }
        const clean = cloneTableForExport(table, tableKey);
        const ws = XLSX.utils.table_to_sheet(clean, { raw: true });
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, sanitizeSheetName(title));
        const fname = `Client_Satisfaction_${YEAR}_${tableKey}.xlsx`;
        XLSX.writeFile(wb, fname);
      } catch (e) {
        Swal.fire({icon:'error', title:'Export error', text:'Unable to export .xlsx'});
      }
    }
    // Save on change
    document.querySelectorAll('.cs-input').forEach(inp => {
      inp.addEventListener('change', async (e) => {
        const el = e.currentTarget;
        const payload = new FormData();
        payload.append('action','save_cell');
        payload.append('table_key', el.dataset.table);
        payload.append('division_key', el.dataset.division);
        payload.append('month', el.dataset.month);
        payload.append('value', el.value);
        try {
          const res = await fetch(location.href, { method:'POST', body: payload });
          const j = await res.json();
          if (!j.ok) {
            Swal.fire({ icon:'error', title:'Save failed', text:j.msg || 'Please try again' });
          }
        } catch (err) {
          Swal.fire({ icon:'error', title:'Network error', text:'Please try again' });
        }
      });
    });
    // Lock / Unlock
    document.querySelectorAll('.btn-lock').forEach(btn => {
      btn.addEventListener('click', async (e) => {
        const el = e.currentTarget;
        const lock = el.dataset.lock;
        const fd = new FormData();
        fd.append('action','set_lock');
        fd.append('table_key', el.dataset.table);
        fd.append('division_key', el.dataset.division);
        fd.append('lock', lock);
        try {
          const res = await fetch(location.href, { method:'POST', body: fd });
          const j = await res.json();
          if (j.ok) {
            location.reload();
          } else {
            Swal.fire({ icon:'error', title:'Action failed', text:j.msg || 'Please try again' });
          }
        } catch (err) {
          Swal.fire({ icon:'error', title:'Network error', text:'Please try again' });
        }
      });
    });
    // Clear
    document.querySelectorAll('.btn-clear').forEach(btn => {
      btn.addEventListener('click', async (e) => {
        const el = e.currentTarget;
        const confirm = await Swal.fire({
          icon:'warning',
          title:'Clear this row?',
          text:'All month values will be removed for PGS.page.year.',
          showCancelButton:true,
          confirmButtonText:'Yes, clear'
        });
        if (!confirm.isConfirmed) return;
        const fd = new FormData();
        fd.append('action','clear_row');
        fd.append('table_key', el.dataset.table);
        fd.append('division_key', el.dataset.division);
        try {
          const res = await fetch(location.href, { method:'POST', body: fd });
          const j = await res.json();
          if (j.ok) {
            location.reload();
          } else {
            Swal.fire({ icon:'error', title:'Action failed', text:j.msg || 'Please try again' });
          }
        } catch (err) {
          Swal.fire({ icon:'error', title:'Network error', text:'Please try again' });
        }
      });
    });
    // Export per-table
    document.querySelectorAll('.btn-export-table').forEach(btn => {
      btn.addEventListener('click', (e) => {
        const key = e.currentTarget.dataset.tablekey;
        const title = e.currentTarget.dataset.title || ('Table ' + key);
        exportSingleTableXLSX(key, title);
      });
    });
    // Charts using saved data
    const chartTableLabels = PGS.page.chartTableLabels;
    const chartTableData = PGS.page.chartTableData;
    const chartMonthLabels = PGS.page.chartMonthLabels;
    const chartMonthData = PGS.page.chartMonthData;
    const chartTrendLabels = PGS.page.chartTrendLabels;
    const chartTrendActualData = PGS.page.chartTrendActualData;
    const chartTrendTargetData = PGS.page.chartTrendTargetData;
    const annualAvg = PGS.page.annualAvg;
    const annualFilled = PGS.page.annualCount;
    const annualPossible = PGS.page.annualPossible;
    const monthlyAvg = PGS.page.monthlyAvg;
    const monthlyFilled = PGS.page.monthlyCount;
    const monthlyPossible = PGS.page.monthlyPossible;

    const hasTableData = chartTableData.some(v => Number(v) > 0);
    const hasTrendActualData = chartTrendActualData.some(v => Number(v) > 0);
    
    // Updated formal color palette: lighter, professional tones
    const FORMAL_COLORS = [
      '#4a90e2', // Soft Blue
      '#50e3c2', // Teal/Mint
      '#b8e986', // Light Green
      '#f5a623', // Muted Orange
      '#f8e71c', // Soft Yellow
      '#bd10e0', // Soft Purple
      '#9013fe', // Deep Purple
      '#417505', // Forest Green
      '#d0021b', // Muted Red
      '#9b9b9b'  // Grey
    ];
    // Helper to get color for n items
    const palette = (n) => Array.from({length: n}, (_, i) => FORMAL_COLORS[i % FORMAL_COLORS.length]);

    const ctxA = document.getElementById('chartA').getContext('2d');
    new Chart(ctxA, {
      type:'pie',
      data:{
        labels: ['Satisfied', 'Remaining'],
        datasets:[{
          data: [annualAvg, Math.max(0, 100 - annualAvg)],
          backgroundColor: [FORMAL_COLORS[0], '#e9ecef'], // Soft Blue vs Light Grey
          borderWidth: 0
        }]
      },
      options:{
        responsive: true,
        maintainAspectRatio: false,
        plugins:{
          legend:{ display:true, position:'right' },
          tooltip: {
            callbacks: {
              label: (ctx) => `${ctx.label}: ${Number(ctx.parsed).toFixed(2)}%`
            }
          }
        }
      }
    });
    if (hasTableData) {
      const noDataA = document.getElementById('noDataA');
      if (noDataA) noDataA.style.display = 'none';
    }

    const ctxB = document.getElementById('chartB').getContext('2d');
    new Chart(ctxB, {
      type:'line',
      data:{
        labels: chartTrendLabels,
        datasets:[
          {
            label: 'Actual (%)',
            data: chartTrendActualData,
            borderColor: FORMAL_COLORS[1],
            backgroundColor: FORMAL_COLORS[1],
            tension: 0.25,
            borderWidth: 3,
            pointRadius: 4,
            pointHoverRadius: 5
          },
          {
            label: 'Target (%)',
            data: chartTrendTargetData,
            borderColor: FORMAL_COLORS[3],
            backgroundColor: FORMAL_COLORS[3],
            tension: 0,
            borderDash: [6, 6],
            borderWidth: 2,
            pointRadius: 3,
            pointHoverRadius: 4
          }
        ]
      },
      options:{
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            min: 0,
            max: 100,
            ticks: {
              callback: (v) => `${Number(v).toFixed(0)}%`
            }
          }
        },
        plugins:{
          legend:{ display:true, position:'bottom' },
          tooltip: {
            callbacks: {
              label: (ctx) => `${ctx.dataset.label}: ${Number(ctx.parsed.y).toFixed(2)}%`
            }
          }
        }
      }
    });

    if (hasTrendActualData) {
      const noDataB = document.getElementById('noDataB');
      if (noDataB) noDataB.style.display = 'none';
    }
