(function(){
      const btn = document.querySelector('[data-bs-target="#approvalsCollapse"]');
      const collapseEl = document.getElementById('approvalsCollapse');
      if (!btn || !collapseEl) return;
      function update(){
        const visible = collapseEl.classList.contains('show');
        btn.innerHTML = visible ? '<i data-lucide="chevron-up" class="me-1"></i> Hide' : '<i data-lucide="chevron-down" class="me-1"></i> Show';
        btn.setAttribute('aria-expanded', visible ? 'true' : 'false');
      }
      collapseEl.addEventListener('shown.bs.collapse', update);
      collapseEl.addEventListener('hidden.bs.collapse', update);
      update();
    })();
