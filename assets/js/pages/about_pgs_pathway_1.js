document.addEventListener('DOMContentLoaded', function(){
      var panels = PGS.page.panels;
      var viewModal = document.getElementById('viewModal');
      viewModal.addEventListener('show.bs.modal', function (e) {
        var idx = e.relatedTarget.getAttribute('data-index');
        var p = panels[idx];
        document.getElementById('viewTitle').textContent = p.title || ('Panel ' + (parseInt(idx)+1));
        document.getElementById('viewStatus').textContent = p.status || '';
        var cont = document.getElementById('viewContent');
        cont.innerHTML = '';
        if (p.type === 'image' && p.image) {
          var img = document.createElement('img');
          img.src = 'img/' + encodeURIComponent(p.image) + '?v=' + Date.now();
          img.style.maxWidth = '100%';
          img.style.maxHeight = '70vh';
          cont.appendChild(img);
        } else if (p.type === 'text' && p.text) {
          var div = document.createElement('div');
          div.style.fontSize = '1.15rem';
          div.style.lineHeight = '1.75';
          div.textContent = p.text;
          cont.appendChild(div);
        } else {
          cont.textContent = 'No content';
        }
      });
      if (PGS.page.role === 'admin') { 
      var editModal = document.getElementById('editModal');
      var typeSel = document.getElementById('edit-type');
      var textWrap = document.getElementById('text-wrap');
      var imageWrap = document.getElementById('image-wrap');
      typeSel.addEventListener('change', function(){
        var v = this.value;
        textWrap.style.display = v==='text' ? 'block' : 'none';
        imageWrap.style.display = v==='image' ? 'block' : 'none';
      });
      editModal.addEventListener('show.bs.modal', function (e) {
        var idx = e.relatedTarget.getAttribute('data-index');
        var p = panels[idx];
        document.getElementById('edit-index').value = idx;
        document.getElementById('edit-title').value = p.title || '';
        document.getElementById('edit-status').value = p.status || '';
        document.getElementById('edit-text').value = p.type==='text' ? (p.text||'') : '';
        typeSel.value = p.type==='image' ? 'image' : 'text';
        typeSel.dispatchEvent(new Event('change'));
      });
       }
    });
