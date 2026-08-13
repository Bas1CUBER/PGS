const ITEM_ID  = PGS.page.itemId;
const IS_ADMIN = PGS.page.isAdmin;
const POST_URL = location.href.split('?')[0] + '?item_id=' + ITEM_ID;

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

const rowModal   = new bootstrap.Modal(document.getElementById('rowModal'));
const addColModal= new bootstrap.Modal(document.getElementById('addColModal'));

let rowState = { blockId:0, rowIndex:-1, cols:[], mode:'add' };
let addColBlockId = 0;

document.querySelectorAll('.btn-add-row').forEach(btn => {
    btn.addEventListener('click', () => {
        const cols = JSON.parse(btn.dataset.cols || '[]');
        rowState = { blockId: parseInt(btn.dataset.blockId), rowIndex: -1, cols, mode: 'add' };
        document.getElementById('rowModalTitle').innerHTML = '<i data-lucide="plus" class="me-2"></i>Add Row';
        document.getElementById('rowModalBody').innerHTML = buildRowForm(cols, []);
        document.getElementById('btnSaveRow').className = 'btn btn-success';
        document.getElementById('btnSaveRow').innerHTML  = '<i data-lucide="check" class="me-1"></i>Save';
        rowModal.show();
    });
});

document.querySelectorAll('.btn-edit-row').forEach(btn => {
    btn.addEventListener('click', () => {
        const cols  = JSON.parse(btn.dataset.cols  || '[]');
        const cells = JSON.parse(btn.dataset.cells || '[]');
        rowState = { blockId: parseInt(btn.dataset.blockId), rowIndex: parseInt(btn.dataset.row), cols, mode: 'edit' };
        document.getElementById('rowModalTitle').innerHTML = '<i data-lucide="pencil" class="me-2"></i>Edit Row';
        document.getElementById('rowModalBody').innerHTML = buildRowForm(cols, cells);
        document.getElementById('btnSaveRow').className = 'btn btn-primary';
        document.getElementById('btnSaveRow').innerHTML  = '<i data-lucide="save" class="me-1"></i>Save Changes';
        rowModal.show();
    });
});

function buildRowForm(cols, values) {
    if (!cols.length) return '<p class="text-muted">No columns defined on this table.</p>';
    return cols.map((col, i) => `
        <div class="mb-3">
            <label class="form-label fw-semibold">${escHtml(col)}</label>
            <input type="text" class="form-control" id="rowcell_${i}" value="${escHtml(values[i] ?? '')}" placeholder="${escHtml(col)}">
        </div>
    `).join('');
}

document.getElementById('btnSaveRow').addEventListener('click', async () => {
    const cells = rowState.cols.map((_, i) => document.getElementById(`rowcell_${i}`)?.value || '');
    const fd = new FormData();
    fd.append('_token','PGS.page.csrf');
    fd.append('action', rowState.mode === 'add' ? 'add_table_row' : 'edit_table_row');
    fd.append('block_id', rowState.blockId);
    fd.append('cells', JSON.stringify(cells));
    if (rowState.mode === 'edit') fd.append('row_index', rowState.rowIndex);

    const btn = document.getElementById('btnSaveRow');
    btn.disabled = true;
    const r = await fetch(POST_URL, { method:'POST', body:fd });
    let j = null; try { j = await r.json(); } catch(e) {}
    btn.disabled = false;

    if (j && j.success) {
        rowModal.hide();
        await Swal.fire({ icon:'success', title: rowState.mode === 'add' ? 'Row Added' : 'Row Updated', timer:1200, showConfirmButton:false });
        location.reload();
    } else {
        Swal.fire({ icon:'error', title:'Failed', text: j?.error || 'Unknown error' });
    }
});

document.querySelectorAll('.btn-add-col').forEach(btn => {
    btn.addEventListener('click', () => {
        addColBlockId = parseInt(btn.dataset.blockId);
        document.getElementById('newColName').value = '';
        addColModal.show();
        setTimeout(() => document.getElementById('newColName').focus(), 300);
    });
});

document.getElementById('btnSaveCol').addEventListener('click', async () => {
    const colName = document.getElementById('newColName').value.trim();
    if (!colName) { Swal.fire({ icon:'error', title:'Column name is required' }); return; }
    const fd = new FormData();
    fd.append('_token','PGS.page.csrf');
    fd.append('action', 'add_table_column');
    fd.append('block_id', addColBlockId);
    fd.append('col_name', colName);

    const btn = document.getElementById('btnSaveCol');
    btn.disabled = true;
    const r = await fetch(POST_URL, { method:'POST', body:fd });
    let j = null; try { j = await r.json(); } catch(e) {}
    btn.disabled = false;

    if (j && j.success) {
        addColModal.hide();
        await Swal.fire({ icon:'success', title:'Column Added', timer:1200, showConfirmButton:false });
        location.reload();
    } else {
        Swal.fire({ icon:'error', title:'Failed', text: j?.error || 'Unknown error' });
    }
});

document.querySelectorAll('.btn-del-row').forEach(btn => {
    btn.addEventListener('click', async () => {
        const c = await Swal.fire({ icon:'warning', title:'Delete row?', text:'This cannot be undone.', showCancelButton:true, confirmButtonColor:'#dc3545', confirmButtonText:'Delete' });
        if (!c.isConfirmed) return;
        const fd = new FormData();
        fd.append('_token','PGS.page.csrf');
        fd.append('action','delete_table_row');
        fd.append('block_id', btn.dataset.blockId);
        fd.append('row_index', btn.dataset.row);
        const r = await fetch(POST_URL, { method:'POST', body:fd });
        let j = null; try { j = await r.json(); } catch(e) {}
        if (j && j.success) { await Swal.fire({ icon:'success', title:'Row Deleted', timer:1000, showConfirmButton:false }); location.reload(); }
        else Swal.fire({ icon:'error', title:'Failed', text: j?.error||'Error' });
    });
});

document.querySelectorAll('.btn-lock-row').forEach(btn => {
    btn.addEventListener('click', async () => {
        const isLocked = btn.dataset.locked === '1';
        const newLock  = !isLocked;
        const fd = new FormData();
        fd.append('_token','PGS.page.csrf');
        fd.append('action', 'lock_table_row');
        fd.append('block_id', btn.dataset.blockId);
        fd.append('row_index', btn.dataset.row);
        fd.append('lock', newLock ? '1' : '0');
        const r = await fetch(POST_URL, { method:'POST', body:fd });
        let j = null; try { j = await r.json(); } catch(e) {}
        if (j && j.success) { location.reload(); }
        else Swal.fire({ icon:'error', title:'Failed' });
    });
});

if (PGS.page.isAdmin) { 
const addBlockModal  = new bootstrap.Modal(document.getElementById('addBlockModal'));
const editBlockModal = new bootstrap.Modal(document.getElementById('editBlockModal'));
let currentAddType = '';
let editBlockId = 0, editBlockType = '';

function formForType(type, prefill) {
    prefill = prefill || {};
    const icons = ['bar-chart-3','line-chart','pie-chart','users','check-circle','star','target','flag','trophy','file-text'];
    const iconOpts = icons.map(ic => `<option value="${ic}" ${prefill.icon===ic?'selected':''}>${ic.replace(/-/g,' ')}</option>`).join('');
    switch (type) {
        case 'heading': return `
            <div class="mb-3"><label class="form-label fw-semibold">Heading Text</label><input type="text" class="form-control" id="bf_text" value="${escHtml(prefill.text||'')}" placeholder="Enter heading text"></div>
            <div class="mb-3"><label class="form-label fw-semibold">Size</label>
            <select class="form-select" id="bf_level">
                <option value="3" ${prefill.level==3?'selected':''}>Large (H3)</option>
                <option value="4" ${!prefill.level||prefill.level==4?'selected':''}>Medium (H4)</option>
                <option value="5" ${prefill.level==5?'selected':''}>Small (H5)</option>
                <option value="6" ${prefill.level==6?'selected':''}>Extra Small (H6)</option>
            </select></div>`;
        case 'paragraph': return `<div class="mb-3"><label class="form-label fw-semibold">Text Content</label><textarea class="form-control" id="bf_text" rows="6" placeholder="Enter paragraph text...">${escHtml(prefill.text||'')}</textarea></div>`;
        case 'table': return `<div class="mb-3"><label class="form-label fw-semibold">Column Names</label><input type="text" class="form-control" id="bf_columns" value="${escHtml((prefill.columns||[]).join(', '))}" placeholder="e.g. Name, Date, Status, Remarks"><div class="form-text">Separate with commas. Add rows and columns after creating.</div></div>`;
        case 'dashboard_stat': return `
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label fw-semibold">Label</label><input type="text" class="form-control" id="bf_label" value="${escHtml(prefill.label||'')}" placeholder="e.g. Total Patients"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Value</label><input type="text" class="form-control" id="bf_value" value="${escHtml(prefill.value||'0')}" placeholder="e.g. 120 or 85%"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Color</label><input type="color" class="form-control form-control-color" id="bf_color" value="${prefill.color||'#0b4aa2'}"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Icon</label><select class="form-select" id="bf_icon">${iconOpts}</select></div>
                <div class="col-12"><div class="stat-card mt-2" id="statPreview" style="background:${prefill.color||'#0b4aa2'}">
                    <div class="stat-icon"><i data-lucide="${prefill.icon||'bar-chart-3'}"></i></div>
                    <div><div class="stat-val" id="prevVal">${escHtml(prefill.value||'0')}</div><div class="stat-label" id="prevLabel">${escHtml(prefill.label||'Stat')}</div></div>
                </div></div>
            </div>`;
        default: return '<p>Unknown type</p>';
    }
}

function collectForm(type) {
    switch (type) {
        case 'heading':   return { text: document.getElementById('bf_text')?.value||'', level: parseInt(document.getElementById('bf_level')?.value||'4') };
        case 'paragraph': return { text: document.getElementById('bf_text')?.value||'' };
        case 'table':     return { columns: (document.getElementById('bf_columns')?.value||'').split(',').map(s=>s.trim()).filter(Boolean), rows:[] };
        case 'dashboard_stat': return { label:document.getElementById('bf_label')?.value||'', value:document.getElementById('bf_value')?.value||'', color:document.getElementById('bf_color')?.value||'#0b4aa2', icon:document.getElementById('bf_icon')?.value||'bar-chart-3' };
    }
}

function setupStatPreview() {
    ['bf_color','bf_icon','bf_value','bf_label'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', () => {
            const prev = document.getElementById('statPreview');
            if (prev) prev.style.background = document.getElementById('bf_color')?.value||'#0b4aa2';
            const pi = prev?.querySelector('.stat-icon i');
            if (pi) { pi.setAttribute('data-lucide', document.getElementById('bf_icon')?.value||'bar-chart-3'); lucide.createIcons(); }
            const pv = document.getElementById('prevVal'); if(pv) pv.textContent = document.getElementById('bf_value')?.value||'0';
            const pl = document.getElementById('prevLabel'); if(pl) pl.textContent = document.getElementById('bf_label')?.value||'Stat';
        });
    });
}

function openAddBlock(type) {
    currentAddType = type;
    const names = {heading:'Heading',paragraph:'Paragraph',table:'Table',dashboard_stat:'Stat Card'};
    document.getElementById('addBlockTitle').innerHTML = `<i data-lucide="plus-circle" class="me-2"></i>Add ${names[type]||type}`;
    document.getElementById('addBlockBody').innerHTML = formForType(type, {});
    if (type === 'dashboard_stat') setupStatPreview();
    addBlockModal.show();
}

document.getElementById('btnSaveBlock').addEventListener('click', async () => {
    const content = collectForm(currentAddType);
    if (currentAddType === 'table' && !content.columns.length) { Swal.fire({icon:'error',title:'Need columns',text:'Enter at least one column name.'}); return; }
    const fd = new FormData(); fd.append('_token','PGS.page.csrf'); fd.append('action','add_block'); fd.append('block_type', currentAddType);
    Object.entries(content).forEach(([k,v]) => { if (!Array.isArray(v)) fd.append(k, v); });
    if (currentAddType === 'table') fd.append('columns', content.columns.join(','));
    const btn = document.getElementById('btnSaveBlock'); btn.disabled = true;
    const r = await fetch(POST_URL, {method:'POST',body:fd});
    let j=null; try{j=await r.json();}catch(e){}
    btn.disabled = false;
    if (j&&j.success) { addBlockModal.hide(); await Swal.fire({icon:'success',title:'Block Added',timer:1200,showConfirmButton:false}); location.reload(); }
    else Swal.fire({icon:'error',title:'Failed',text:j?.error||'Unknown error'});
});

document.querySelectorAll('.btn-edit-block').forEach(btn => {
    btn.addEventListener('click', () => {
        const card = btn.closest('[data-block-id]');
        editBlockId   = parseInt(card.dataset.blockId);
        editBlockType = card.dataset.blockType;
        const raw = card.dataset.content ? JSON.parse(card.dataset.content) : {};
        document.getElementById('editBlockBody').innerHTML = formForType(editBlockType, raw);
        if (editBlockType === 'dashboard_stat') setupStatPreview();
        editBlockModal.show();
    });
});

document.getElementById('btnUpdateBlock').addEventListener('click', async () => {
    const content = collectForm(editBlockType);
    const fd = new FormData(); fd.append('_token','PGS.page.csrf'); fd.append('action','update_block'); fd.append('block_id',editBlockId); fd.append('content', JSON.stringify(content));
    const btn = document.getElementById('btnUpdateBlock'); btn.disabled = true;
    const r = await fetch(POST_URL, {method:'POST',body:fd});
    let j=null; try{j=await r.json();}catch(e){}
    btn.disabled = false;
    if (j&&j.success) { editBlockModal.hide(); await Swal.fire({icon:'success',title:'Block Updated',timer:1200,showConfirmButton:false}); location.reload(); }
    else Swal.fire({icon:'error',title:'Failed',text:j?.error||'Unknown error'});
});

document.querySelectorAll('.btn-del-block').forEach(btn => {
    btn.addEventListener('click', async () => {
        const card = btn.closest('[data-block-id]');
        const id = parseInt(card.dataset.blockId);
        const c = await Swal.fire({icon:'warning',title:'Delete block?',text:'This cannot be undone.',showCancelButton:true,confirmButtonColor:'#dc3545',confirmButtonText:'Delete'});
        if (!c.isConfirmed) return;
        const fd = new FormData(); fd.append('_token','PGS.page.csrf'); fd.append('action','delete_block'); fd.append('block_id',id);
        const r = await fetch(POST_URL,{method:'POST',body:fd});
        let j=null; try{j=await r.json();}catch(e){}
        if (j&&j.success) { await Swal.fire({icon:'success',title:'Deleted',timer:1000,showConfirmButton:false}); location.reload(); }
        else Swal.fire({icon:'error',title:'Failed'});
    });
});

document.querySelectorAll('.btn-move').forEach(btn => {
    btn.addEventListener('click', async () => {
        const card = btn.closest('[data-block-id]');
        const fd = new FormData(); fd.append('_token','PGS.page.csrf'); fd.append('action','reorder_block'); fd.append('block_id',parseInt(card.dataset.blockId)); fd.append('direction',btn.dataset.dir);
        await fetch(POST_URL,{method:'POST',body:fd});
        location.reload();
    });
});
 }
