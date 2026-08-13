(function(){
  const CSRF_TOKEN = '{$csrf}';
  function p(params) {
    params.set('_token', CSRF_TOKEN);
    return params;
  }
  const table = document.getElementById('matrixTable');
  table.addEventListener('focusout', function(e){
    const td = e.target;
    if (td && td.tagName === 'TD' && td.hasAttribute('contenteditable') && td.getAttribute('contenteditable') === 'true') {
      const row = td.getAttribute('data-row');
      const col = td.getAttribute('data-col');
      const value = td.textContent.trim();
      fetch('about_user_access.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: p(new URLSearchParams({ action:'edit_cell', row: row, col: col, value: value }))
      }).then(async r=>{
        let data=null; try { data = await r.json(); } catch(e){}
        if (!(data && data.ok)) {
          alert((data && data.error) ? data.error : 'Save failed');
        }
      }).catch(()=>alert('Save failed'));
    }
  });
  document.getElementById('addRowBtn')?.addEventListener('click', function(){
    fetch('about_user_access.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: p(new URLSearchParams({ action:'add_row' }))
    }).then(async r=>{
      let d=null; try { d = await r.json(); } catch(e){}
      if (d && d.ok) location.reload(); else alert((d && d.error) || 'Add row failed');
    }).catch(()=>alert('Add row failed'));
  });
  document.getElementById('addColBtn')?.addEventListener('click', function(){
    const label = prompt('Enter new column label:');
    if (!label) return;
    fetch('about_user_access.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: p(new URLSearchParams({ action:'add_column', label: label }))
    }).then(async r=>{
      let d=null; try { d = await r.json(); } catch(e){}
      if (d && d.ok) location.reload(); else alert((d && d.error) || 'Add column failed');
    }).catch(()=>alert('Add column failed'));
  });
})();
