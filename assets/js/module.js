document.addEventListener('DOMContentLoaded', function() {
  const role = window.PGS_MODULE?.role || '';
  const categories = window.PGS_MODULE?.categories || [];
  const years = window.PGS_MODULE?.years || [];
  const progressYear = document.getElementById('progressYear');
  const ptYear = document.getElementById('ptYear');
  const tbody = document.getElementById('progressBody');
  const monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  const apiUrl = window.PGS_MODULE?.apiUrl || location.href;

  function statusBadge(s) {
    if (s === 'Accomplished') return '<span class="badge bg-success">Accomplished</span>';
    if (s === 'Ongoing') return '<span class="badge bg-warning text-dark">Ongoing</span>';
    if (s === 'Not Accomplished/Started') return '<span class="badge bg-danger">Not Accomplished/Started</span>';
    return '<span class="text-muted">&mdash;</span>';
  }

  async function loadProgress(year) {
    ptYear.textContent = year;
    tbody.innerHTML = '';
    const fd = new FormData(); fd.append('action','get_progress'); fd.append('year', String(year));
    const r = await fetch(apiUrl, { method:'POST', body:fd });
    let j = null; try { j = await r.json(); } catch(e) {}
    const prog = (j && j.ok) ? j.progress : {};
    for (const cat of categories) {
      const tr = document.createElement('tr');
      tr.innerHTML = '<td class="fw-semibold">' + cat + '</td>' + Array.from({length:12}, (_,i)=>{
        const cellData = prog[cat]?.[i+1] || null;
        const desc = cellData?.description ? '<div class="small">' + cellData.description.replace(/</g,'&lt;') + '</div>' : '';
        const label = cellData ? statusBadge(cellData.status) : '<span class="text-muted">&mdash;</span>';
        const delBtn = (role === 'admin' && cellData) ? '<button type="button" class="btn btn-sm btn-outline-danger ms-2 del-cell" data-cat="' + cat + '" data-month="' + (i+1) + '" data-year="' + year + '" title="Delete"><i data-lucide="trash-2"></i></button>' : '';
        return '<td class="text-center" data-cat="' + cat + '" data-month="' + (i+1) + '">' + desc + label + delBtn + '</td>';
      }).join('');
      tbody.appendChild(tr);
    }
    if (role !== 'admin') {
      tbody.querySelectorAll('td[data-month]').forEach(td => {
        td.style.cursor = 'pointer';
        td.addEventListener('click', async () => {
          const cat = td.getAttribute('data-cat');
          const month = parseInt(td.getAttribute('data-month'),10);
          const { value: status } = await Swal.fire({
            title: 'Update Status (' + cat + ' - ' + monthNames[month-1] + ' ' + year + ')',
            input: 'select',
            inputOptions: {
              'Not Accomplished/Started':'Not Accomplished/Started',
              'Ongoing':'Ongoing',
              'Accomplished':'Accomplished'
            },
            inputPlaceholder: 'Choose status',
            showCancelButton: true
          });
          if (!status) return;
          let remarks = null;
          if (status !== 'Accomplished') {
            const rmk = await Swal.fire({
              title: 'Add remarks/progress',
              input: 'textarea',
              inputPlaceholder: 'Write progress/remarks',
              inputAttributes: { 'aria-label': 'Remarks' },
              showCancelButton: true
            });
            if (!rmk.isConfirmed) return;
            remarks = rmk.value || '';
          }
          const fd2 = new FormData();
          fd2.append('action','save_progress');
          fd2.append('category', cat);
          fd2.append('year', String(year));
          fd2.append('month', String(month));
          fd2.append('status', status);
          fd2.append('remarks', remarks ?? '');
          const resp = await fetch(apiUrl, { method:'POST', body:fd2 });
          let jj=null; try{ jj=await resp.json(); }catch(e){}
          if (jj && jj.ok) {
            td.innerHTML = td.innerHTML.replace(/<span[^>]*>.*?<\/span>|<span class="text-muted">.*?<\/span>/, statusBadge(status));
            await Swal.fire({ icon:'success', title:'Saved', timer:900, showConfirmButton:false });
          } else {
            await Swal.fire({ icon:'error', title:'Failed', text:(jj && jj.msg) || 'Unable to save' });
          }
        });
      });
    } else {
      tbody.querySelectorAll('.del-cell').forEach(btn=>{
        btn.addEventListener('click', async (e)=>{
          e.stopPropagation();
          const cat = btn.getAttribute('data-cat');
          const month = parseInt(btn.getAttribute('data-month'),10);
          const yr = parseInt(btn.getAttribute('data-year'),10);
          const confirm = await Swal.fire({ title:'Delete Entry', text:'Delete ' + cat + ' - ' + monthNames[month-1] + ' ' + yr + '?', icon:'warning', showCancelButton:true });
          if (!confirm.isConfirmed) return;
          const fd = new FormData(); fd.append('action','delete_progress_cell'); fd.append('category',cat); fd.append('year', String(yr)); fd.append('month', String(month));
          const resp = await fetch(apiUrl, { method:'POST', body: fd });
          let jj=null; try{ jj=await resp.json(); }catch(e){}
          if (jj && jj.ok) { await Swal.fire({ icon:'success', title:'Deleted', timer:900, showConfirmButton:false }); loadProgress(year); }
          else { await Swal.fire({ icon:'error', title:'Failed', text:(jj && jj.msg) || 'Unable to delete' }); }
        });
      });
      tbody.querySelectorAll('td[data-month]').forEach(td => {
        td.addEventListener('click', async () => {
          const cat = td.getAttribute('data-cat');
          const month = parseInt(td.getAttribute('data-month'),10);
          const cell = prog[cat]?.[month] || null;
          await Swal.fire({
            title: cat + ' - ' + monthNames[month-1] + ' ' + yr,
            html: '<div>' + (cell?.description ? '<div class="mb-2"><strong>Description:</strong><br>' + cell.description.replace(/</g,'&lt;') + '</div>' : '') + '<div><strong>Status:</strong> ' + (cell ? statusBadge(cell.status) : 'No status') + '</div><hr><div><strong>Remarks:</strong><br>' + (cell?.remarks ? cell.remarks.replace(/</g,'&lt;') : '&mdash;') + '</div>'
          });
        });
      });
    }
  }

  const defaultYear = progressYear?.value || (years.slice(-1)[0] ?? '');
  if (defaultYear && progressYear) progressYear.value = defaultYear;
  if (defaultYear) loadProgress(defaultYear);
  if (progressYear) progressYear.addEventListener('change', () => loadProgress(progressYear.value));

  const toggleBtn = document.querySelector('[data-bs-target="#progressCollapse"]');
  const collapseEl = document.getElementById('progressCollapse');
  if (toggleBtn && collapseEl) {
    collapseEl.addEventListener('shown.bs.collapse', () => { toggleBtn.textContent = 'Hide'; });
    collapseEl.addEventListener('hidden.bs.collapse', () => { toggleBtn.textContent = 'Show'; });
  }
});

(function(){
  const categories = window.PGS_MODULE?.categories || [];
  const monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  const apiUrl = window.PGS_MODULE?.apiUrl || location.href;
  const dashboardYear = document.getElementById('dashboardYear');
  const progressYear = document.getElementById('progressYear');
  const tooltip = document.getElementById('dashboardTooltip');
  let statusData = { accomplished: { count: 0, items: [] }, ongoing: { count: 0, items: [] }, notAccomplished: { count: 0, items: [] } };
  let statusChart = null;

  function initChart() {
    const ctx = document.getElementById('statusChart');
    if (!ctx) return;
    statusChart = new Chart(ctx.getContext('2d'), {
      type: 'doughnut',
      data: {
        labels: ['Accomplished', 'Ongoing', 'Not Accomplished/Started'],
        datasets: [{
          data: [0, 0, 0],
          backgroundColor: ['#198754', '#ffc107', '#dc3545'],
          borderColor: ['#ffffff', '#ffffff', '#ffffff'],
          borderWidth: 3, hoverOffset: 8
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false, cutout: '60%',
        plugins: {
          legend: { display: false },
          tooltip: {
            enabled: true,
            callbacks: {
              label: function(context) {
                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                const value = context.raw;
                const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                return context.label + ': ' + value + ' (' + percentage + '%)';
              }
            }
          }
        },
        animation: { animateRotate: true, animateScale: true }
      }
    });
  }

  async function updateDashboard(year) {
    const fd = new FormData();
    fd.append('action', 'get_progress');
    fd.append('year', String(year));
    const r = await fetch(apiUrl, { method: 'POST', body: fd });
    let j = null;
    try { j = await r.json(); } catch(e) {}
    const prog = (j && j.ok) ? j.progress : {};
    statusData = { accomplished: { count: 0, items: [] }, ongoing: { count: 0, items: [] }, notAccomplished: { count: 0, items: [] } };
    for (const cat of categories) {
      for (let month = 1; month <= 12; month++) {
        const cell = prog[cat]?.[month];
        if (cell && cell.status) {
          const item = { category: cat, month: month, monthName: monthNames[month - 1], description: cell.description || '' };
          if (cell.status === 'Accomplished') { statusData.accomplished.count++; statusData.accomplished.items.push(item); }
          else if (cell.status === 'Ongoing') { statusData.ongoing.count++; statusData.ongoing.items.push(item); }
          else if (cell.status === 'Not Accomplished/Started') { statusData.notAccomplished.count++; statusData.notAccomplished.items.push(item); }
        }
      }
    }
    const el1 = document.getElementById('countAccomplished');
    const el2 = document.getElementById('countOngoing');
    const el3 = document.getElementById('countNotAccomplished');
    const el4 = document.getElementById('totalEntries');
    if (el1) el1.textContent = statusData.accomplished.count;
    if (el2) el2.textContent = statusData.ongoing.count;
    if (el3) el3.textContent = statusData.notAccomplished.count;
    const total = statusData.accomplished.count + statusData.ongoing.count + statusData.notAccomplished.count;
    if (el4) el4.textContent = total;
    if (statusChart) {
      statusChart.data.datasets[0].data = [statusData.accomplished.count, statusData.ongoing.count, statusData.notAccomplished.count];
      statusChart.update('active');
    }
  }

  function showTooltip(e, statusType) {
    if (!tooltip) return;
    const items = statusData[statusType].items;
    if (items.length === 0) { tooltip.classList.remove('visible'); return; }
    let label = statusType.charAt(0).toUpperCase() + statusType.slice(1).replace('notAccomplished', 'Not Accomplished/Started');
    let html = '<strong>' + label + '</strong><hr style="margin: 0.25rem 0;">';
    items.slice(0, 5).forEach(item => {
      html += '<div style="margin-bottom: 0.25rem;"><strong>' + item.monthName + '</strong>: ' + item.category;
      if (item.description) html += '<br><em style="font-size: 0.75rem; opacity: 0.8;">' + item.description.substring(0, 50) + (item.description.length > 50 ? '...' : '') + '</em>';
      html += '</div>';
    });
    if (items.length > 5) html += '<div style="margin-top: 0.25rem; opacity: 0.7;">...and ' + (items.length - 5) + ' more</div>';
    tooltip.innerHTML = html;
    tooltip.style.left = (e.pageX + 10) + 'px';
    tooltip.style.top = (e.pageY + 10) + 'px';
    tooltip.classList.add('visible');
  }

  function hideTooltip() { if (tooltip) tooltip.classList.remove('visible'); }

  function attachTooltip(id, type) {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('mouseenter', (e) => showTooltip(e, type));
    el.addEventListener('mouseleave', hideTooltip);
    el.addEventListener('mousemove', (e) => {
      if (tooltip && tooltip.classList.contains('visible')) {
        tooltip.style.left = (e.pageX + 10) + 'px';
        tooltip.style.top = (e.pageY + 10) + 'px';
      }
    });
  }
  attachTooltip('statusAccomplished', 'accomplished');
  attachTooltip('statusOngoing', 'ongoing');
  attachTooltip('statusNotAccomplished', 'notAccomplished');

  if (dashboardYear && progressYear) {
    dashboardYear.addEventListener('change', () => {
      progressYear.value = dashboardYear.value;
      progressYear.dispatchEvent(new Event('change'));
      updateDashboard(dashboardYear.value);
    });
    progressYear.addEventListener('change', () => {
      dashboardYear.value = progressYear.value;
      updateDashboard(progressYear.value);
    });
    initChart();
    const defYr = dashboardYear.value || progressYear.value || (years.slice(-1)[0] ?? '');
    if (defYr) { dashboardYear.value = defYr; progressYear.value = defYr; }
    updateDashboard(defYr);
  }
})();

document.addEventListener('DOMContentLoaded', function() {
  const deleteRowForm = document.getElementById('deleteRowForm');
  if (deleteRowForm) {
    deleteRowForm.addEventListener('submit', function(e) {
      e.preventDefault();
      const selectedRow = document.getElementById('rowToDelete').value;
      if (!selectedRow) {
        Swal.fire({ icon: 'warning', title: 'No Key Area Selected', text: 'Please select a key area to delete.' });
        return;
      }
      Swal.fire({
        title: 'Are you sure?',
        text: 'You are about to delete the key area "' + selectedRow + '". This action cannot be undone.',
        icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete it!', cancelButtonText: 'Cancel', reverseButtons: true
      }).then((result) => {
        if (result.isConfirmed) {
          const formData = new FormData();
          formData.append('action', 'delete_row');
          formData.append('category', selectedRow);
          fetch(apiUrl, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
              if (data.status === 'success') {
                const deleteModal = bootstrap.Modal.getInstance(document.getElementById('deleteRowModal'));
                if (deleteModal) deleteModal.hide();
                Swal.fire({ icon: 'success', title: 'Deleted!', text: 'Key Area "' + selectedRow + '" has been successfully deleted.', timer: 2000, showConfirmButton: false }).then(() => { location.reload(); });
              } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Something went wrong.' });
              }
            })
            .catch(err => { console.error('Error:', err); Swal.fire({ icon: 'error', title: 'Request Failed', text: 'An error occurred while deleting the row.' }); });
        }
      });
    });
  }

  const addCategoryBtn = document.getElementById('addCategoryBtn');
  const categoriesContainer = document.getElementById('categoriesContainer');
  const addYearForm = document.getElementById('addYearForm');
  const saveButton = document.getElementById('saveButton');
  const addYearModal = document.getElementById('addYearModal');
  if (addCategoryBtn && categoriesContainer) {
    addCategoryBtn.addEventListener('click', function() {
      const categoryId = 'category_' + new Date().getTime();
      const categoryHtml = '<div class="mb-3 border-bottom pb-3" id="' + categoryId + '"><label class="form-label fw-semibold">Category Name</label><input type="text" name="categories[' + categoryId + '][category]" class="form-control" placeholder="Enter Category Name" required><label class="form-label fw-semibold mt-3">Category Descriptions</label><div id="descriptions_' + categoryId + '"><textarea name="categories[' + categoryId + '][descriptions][]" class="form-control" placeholder="Enter description for this category" rows="3"></textarea></div><button type="button" class="btn btn-outline-success mt-2 addDescBtn" data-id="' + categoryId + '"><i data-lucide="plus" class="me-1"></i> Add Another Description</button><button type="button" class="btn btn-outline-danger mt-2 removeCategoryBtn">Remove Category</button></div>';
      categoriesContainer.insertAdjacentHTML('beforeend', categoryHtml);
      const descBtn = categoriesContainer.querySelector('.addDescBtn');
      if (descBtn) {
        descBtn.addEventListener('click', function() {
          const id = this.getAttribute('data-id');
          document.getElementById('descriptions_' + id).insertAdjacentHTML('beforeend', '<textarea name="categories[' + id + '][descriptions][]" class="form-control mt-2" placeholder="Enter another description for this category" rows="3"></textarea>');
        });
      }
      categoriesContainer.querySelectorAll('.removeCategoryBtn').forEach(function(btn) {
        btn.addEventListener('click', function() { this.closest('.mb-3').remove(); toggleSaveButton(); });
      });
      toggleSaveButton();
    });
    function toggleSaveButton() {
      if (saveButton) saveButton.disabled = !categoriesContainer.querySelector('input[name*="category"]');
    }
  }

  if (addYearForm) {
    addYearForm.addEventListener('submit', function(e) {
      e.preventDefault();
      const formData = new FormData(this);
      fetch(addYearForm.action, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
          if (data.status === 'success') {
            Swal.fire({ icon: 'success', title: 'Year Added!', text: 'Data for year ' + data.year + ' has been successfully added.', timer: 2000, showConfirmButton: false }).then(() => { location.reload(); });
          } else {
            Swal.fire({ icon: 'error', title: 'Submission Failed', text: data.message || 'An error occurred. Please try again.' });
          }
        })
        .catch(error => { console.error('Error:', error); Swal.fire({ icon: 'error', title: 'Request Failed', text: 'Something went wrong during submission.' }); });
    });
  }

  const deleteYearForm = document.getElementById('deleteYearForm');
  if (deleteYearForm) {
    deleteYearForm.addEventListener('submit', function(e) {
      e.preventDefault();
      const selectedYear = document.getElementById('yearToDelete').value;
      if (!selectedYear) { Swal.fire({ icon: 'warning', title: 'No Year Selected', text: 'Please select a year to delete.' }); return; }
      Swal.fire({
        title: 'Are you sure?', text: 'You are about to delete all data for year ' + selectedYear + '. This action cannot be undone.',
        icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete it!', cancelButtonText: 'Cancel', reverseButtons: true
      }).then((result) => {
        if (result.isConfirmed) {
          const formData = new FormData();
          formData.append('year', selectedYear);
          fetch(deleteYearForm.action, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
              if (data.status === 'success') {
                const deleteModal = bootstrap.Modal.getInstance(document.getElementById('deleteYearModal'));
                if (deleteModal) deleteModal.hide();
                Swal.fire({ icon: 'success', title: 'Deleted!', text: 'Year ' + selectedYear + ' has been successfully deleted.', timer: 2000, showConfirmButton: false }).then(() => { location.reload(); });
              } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Something went wrong.' });
              }
            })
            .catch(err => { console.error('Error:', err); Swal.fire({ icon: 'error', title: 'Request Failed', text: 'An error occurred while deleting the year.' }); });
        }
      });
    });
  }

  const yearDropdown = document.getElementById('yearDropdown');
  const editYearBtn = document.getElementById('editYearBtn');
  if (yearDropdown && editYearBtn) {
    (function initDefaultYear() {
      const options = Array.from(yearDropdown.options).filter(o => o.value && o.value.trim() !== '');
      if (options.length) { yearDropdown.value = options[options.length - 1].value; editYearBtn.disabled = false; }
    })();
    yearDropdown.addEventListener('change', function() { editYearBtn.disabled = !yearDropdown.value; });

    editYearBtn.addEventListener('click', function() {
      const year = yearDropdown.value;
      fetch(yearDropdown.getAttribute('data-edit-url') + '?year=' + year)
        .then(response => response.json())
        .then(data => {
          if (data.status === 'success') {
            const editYearForm = document.getElementById('editYearForm');
            const editCategoriesContainer = document.getElementById('editCategoriesContainer');
            const editYearModal = new bootstrap.Modal(document.getElementById('editYearModal'));
            editYearForm.reset();
            editCategoriesContainer.innerHTML = '';
            document.getElementById('editYear').value = data.year;
            for (let categoryName in data.categories) {
              appendCategoryBlock(categoryName, data.categories[categoryName]);
            }
            editYearModal.show();
          } else {
            Swal.fire({ icon: 'error', title: 'Failed to Load Data', text: data.message || 'No data found for this year.' });
          }
        })
        .catch(err => { console.error('Error fetching year data:', err); Swal.fire({ icon: 'error', title: 'Fetch Error', text: 'Could not retrieve data for editing.' }); });
    });
  }

  function appendCategoryBlock(categoryName, descriptions) {
    const container = document.getElementById('editCategoriesContainer');
    if (!container) return;
    const categoryId = categoryName.replace(/\s+/g, '_').toLowerCase() + '_' + Math.random().toString(36).substring(7);
    let descFields = '';
    (descriptions || []).forEach(function(desc) {
      descFields += '<textarea name="categories[' + categoryId + '][descriptions][]" class="form-control mt-2" placeholder="Enter description for this category" rows="3">' + desc + '</textarea>';
    });
    const html = '<div class="mb-3 border-bottom pb-3" id="' + categoryId + '" data-original-category="' + categoryName.replace(/"/g,'&quot;') + '">' +
      '<label class="form-label fw-semibold">Category Name</label>' +
      '<input type="text" name="categories[' + categoryId + '][category]" class="form-control" placeholder="Enter Category Name" required value="' + categoryName.replace(/"/g,'&quot;') + '">' +
      '<label class="form-label fw-semibold mt-3">Category Descriptions</label>' +
      '<div id="descriptions_' + categoryId + '">' + descFields + '</div>' +
      '<button type="button" class="btn btn-outline-success mt-2 editAddDescBtn" data-id="' + categoryId + '"><i data-lucide="plus" class="me-1"></i> Add Another Description</button>' +
      '<button type="button" class="btn btn-outline-danger mt-2 removeCategoryBtn">Remove Category</button></div>';
    container.insertAdjacentHTML('beforeend', html);
  }

  const editCategoriesContainer = document.getElementById('editCategoriesContainer');
  if (editCategoriesContainer) {
    editCategoriesContainer.addEventListener('click', function(e) {
      if (e.target.classList.contains('editAddDescBtn')) {
        const id = e.target.getAttribute('data-id');
        document.getElementById('descriptions_' + id).insertAdjacentHTML('beforeend', '<textarea name="categories[' + id + '][descriptions][]" class="form-control mt-2" placeholder="Enter another description" rows="3"></textarea>');
      }
      if (e.target.classList.contains('removeCategoryBtn')) {
        e.target.closest('.mb-3').remove();
      }
    });
  }

  const editYearForm = document.getElementById('editYearForm');
  if (editYearForm) {
    editYearForm.addEventListener('submit', function(event) {
      event.preventDefault();
      const formData = new FormData();
      formData.append('year', document.getElementById('editYear').value);
      document.querySelectorAll('#editCategoriesContainer .mb-3').forEach(function(categoryBlock) {
        const originalCategory = categoryBlock.dataset.originalCategory;
        const categoryInput = categoryBlock.querySelector('input[name*="[category]"]');
        const descriptions = Array.from(categoryBlock.querySelectorAll('textarea[name*="[descriptions]"]')).map(function(ta) { return ta.value; });
        formData.append('categories[' + originalCategory + '][category]', categoryInput.value);
        descriptions.forEach(function(desc) { formData.append('categories[' + originalCategory + '][descriptions][]', desc); });
      });
      fetch(editYearForm.action, { method: 'POST', body: formData })
        .then(response => response.text())
        .then(function(data) {
          if (data.includes('Year updated successfully')) {
            Swal.fire({ icon: 'success', title: 'Success', text: 'The year and categories have been updated successfully!' }).then(() => { location.reload(); });
          } else {
            Swal.fire({ icon: 'error', title: 'Error', text: data });
          }
        })
        .catch(function(err) { console.error('Error:', err); Swal.fire({ icon: 'error', title: 'Error', text: 'Something went wrong while submitting.' }); });
    });
  }

  const addRowForm = document.getElementById('addRowForm');
  if (addRowForm) {
    addRowForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      fd.append('action','save_progress');
      if (!fd.has('remarks')) fd.append('remarks','');
      const r = await fetch(apiUrl, { method:'POST', body:fd });
      let j = null; try { j = await r.json(); } catch(e) {}
      if (j && j.ok) {
        const m = bootstrap.Modal.getInstance(document.getElementById('addRowModal')); if (m) m.hide();
        await Swal.fire({ icon:'success', title:'Row Added', timer:1200, showConfirmButton:false });
        location.reload();
      } else {
        await Swal.fire({ icon:'error', title:'Failed', text:(j && j.msg) || 'Unable to add row' });
      }
    });
  }

  if (window.PGS_MODULE?.role === 'admin') {
    document.querySelectorAll('.btn-approve').forEach(function(btn) {
      btn.addEventListener('click', async function() {
        const id = btn.getAttribute('data-id');
        const fd = new FormData(); fd.append('action','approve_pending'); fd.append('id', id);
        const r = await fetch(apiUrl, { method:'POST', body: fd });
        let j=null; try{ j=await r.json(); }catch(e){}
        if (j && j.ok) { await Swal.fire({ icon:'success', title:'Approved', timer:1000, showConfirmButton:false }); location.reload(); }
        else { await Swal.fire({ icon:'error', title:'Failed', text:(j && j.msg) || 'Unable to approve' }); }
      });
    });
    document.querySelectorAll('.btn-reject').forEach(function(btn) {
      btn.addEventListener('click', async function() {
        const id = btn.getAttribute('data-id');
        const fd = new FormData(); fd.append('action','reject_pending'); fd.append('id', id);
        const r = await fetch(apiUrl, { method:'POST', body: fd });
        let j=null; try{ j=await r.json(); }catch(e){}
        if (j && j.ok) { await Swal.fire({ icon:'success', title:'Rejected', timer:1000, showConfirmButton:false }); location.reload(); }
        else { await Swal.fire({ icon:'error', title:'Failed', text:(j && j.msg) || 'Unable to reject' }); }
      });
    });
  }
});
