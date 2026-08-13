const ALL_TITLES = PGS.page.titlesForJs;
const ALL_DATA   = PGS.page.titlesJson;

function itemsForTitle(tid) {
    const t = ALL_DATA.find(t => t.id == tid);
    return t ? t.items : [];
}

const addModal = new bootstrap.Modal(document.getElementById('addModal'));
document.getElementById('btnOpenAdd').addEventListener('click', () => {
    document.getElementById('addTitleSelect').value = '';
    document.getElementById('addNewTitle').value = '';
    document.getElementById('addSubLabel').value = '';
    document.getElementById('newTitleWrap').style.display = 'block';
    addModal.show();
});
document.getElementById('addTitleSelect').addEventListener('change', function() {
    document.getElementById('newTitleWrap').style.display = this.value ? 'none' : 'block';
});
document.getElementById('btnSaveAdd').addEventListener('click', async () => {
    const titleId  = document.getElementById('addTitleSelect').value;
    const newTitle = document.getElementById('addNewTitle').value.trim();
    const subLabel = document.getElementById('addSubLabel').value.trim();
    if (!subLabel) { Swal.fire({ icon: 'error', title: 'Missing field', text: 'Sub-item label is required.' }); return; }
    if (!titleId && !newTitle) { Swal.fire({ icon: 'error', title: 'Missing field', text: 'Select an existing title or enter a new one.' }); return; }

    const fd = new FormData();
    fd.append('_token','PGS.page.csrf');
    fd.append('action', 'add_item');
    fd.append('title_id', titleId || '0');
    fd.append('new_title', newTitle);
    fd.append('sub_label', subLabel);

    const btn = document.getElementById('btnSaveAdd');
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    const r = await fetch(location.href, { method: 'POST', body: fd });
    let j = null; try { j = await r.json(); } catch(e) {}
    btn.disabled = false; btn.innerHTML = '<i data-lucide="plus" class="me-1"></i>Add';

    if (j && j.success) {
        addModal.hide();
        await Swal.fire({ icon: 'success', title: 'Sub-item Added', timer: 1400, showConfirmButton: false });
        location.reload();
    } else {
        Swal.fire({ icon: 'error', title: 'Failed', text: j?.error || 'Unknown error' });
    }
});

const editModal = new bootstrap.Modal(document.getElementById('editModal'));
document.getElementById('btnOpenEdit').addEventListener('click', () => {
    const sel = document.getElementById('editTitleSelect');
    if (ALL_TITLES.length) { sel.value = ALL_TITLES[0].id; sel.dispatchEvent(new Event('change')); }
    document.getElementById('editLabelWrap').style.display = 'none';
    document.getElementById('btnSaveEdit').disabled = true;
    editModal.show();
});

document.getElementById('editTitleSelect').addEventListener('change', function() {
    const tid = this.value;
    const items = itemsForTitle(tid);
    const sel2 = document.getElementById('editItemSelect');
    sel2.innerHTML = items.length
        ? items.map(i => `<option value="${i.id}" data-label="${i.sub_label.replace(/"/g,'&quot;')}">${i.sub_label}</option>`).join('')
        : '<option value="">No sub-items</option>';
    document.getElementById('editLabelWrap').style.display = 'none';
    document.getElementById('btnSaveEdit').disabled = true;
    sel2.dispatchEvent(new Event('change'));
});

document.getElementById('editItemSelect').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    if (!opt || !opt.value) { document.getElementById('editLabelWrap').style.display = 'none'; document.getElementById('btnSaveEdit').disabled = true; return; }
    document.getElementById('editSubLabel').value = opt.dataset.label || opt.text;
    document.getElementById('editLabelWrap').style.display = 'block';
    document.getElementById('btnSaveEdit').disabled = false;
});

document.getElementById('btnSaveEdit').addEventListener('click', async () => {
    const titleId  = document.getElementById('editTitleSelect').value;
    const itemId   = document.getElementById('editItemSelect').value;
    const subLabel = document.getElementById('editSubLabel').value.trim();
    if (!itemId || !subLabel) { Swal.fire({ icon: 'error', title: 'Missing fields' }); return; }

    const fd = new FormData();
    fd.append('_token','PGS.page.csrf');
    fd.append('action', 'edit_item');
    fd.append('item_id', itemId);
    fd.append('title_id', titleId);
    fd.append('sub_label', subLabel);

    const btn = document.getElementById('btnSaveEdit');
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    const r = await fetch(location.href, { method: 'POST', body: fd });
    let j = null; try { j = await r.json(); } catch(e) {}
    btn.disabled = false; btn.innerHTML = '<i data-lucide="save" class="me-1"></i>Save';

    if (j && j.success) {
        editModal.hide();
        await Swal.fire({ icon: 'success', title: 'Sub-item Updated', timer: 1400, showConfirmButton: false });
        location.reload();
    } else {
        Swal.fire({ icon: 'error', title: 'Failed', text: j?.error || 'Unknown error' });
    }
});

document.querySelectorAll('.btn-del-item').forEach(btn => {
    btn.addEventListener('click', async () => {
        const id = btn.dataset.id;
        const label = btn.dataset.label;
        const c = await Swal.fire({
            icon: 'warning', title: 'Delete Sub-item?',
            html: `<span class="text-muted small">${label}</span><br>This will also delete all page content for this item.`,
            showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#dc3545'
        });
        if (!c.isConfirmed) return;
        const fd = new FormData(); fd.append('_token','PGS.page.csrf'); fd.append('action', 'delete_item'); fd.append('item_id', id);
        const r = await fetch(location.href, { method: 'POST', body: fd });
        let j = null; try { j = await r.json(); } catch(e) {}
        if (j && j.success) {
            await Swal.fire({ icon: 'success', title: 'Deleted', timer: 1200, showConfirmButton: false });
            location.reload();
        } else {
            Swal.fire({ icon: 'error', title: 'Failed', text: j?.error || 'Unknown error' });
        }
    });
});
