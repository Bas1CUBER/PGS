document.getElementById('addUserForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('user_add.php', {
                method: 'POST',
                body: formData
            })
            .then(async r => {
                const text = await r.text();
                let data = null;
                try { data = JSON.parse(text); } catch (err) {
                    throw new Error(text || ('HTTP ' + r.status));
                }
                return data;
            })
            .then(data => {
                if (data && data.success) {
                    Swal.fire('Created', 'User added successfully', 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', (data && data.error) || 'Failed to add user', 'error');
                }
            })
            .catch((err) => Swal.fire('Error', (err && err.message) ? err.message : 'Request failed', 'error'));
        });

        document.querySelectorAll('.toggleBtn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const active = parseInt(this.getAttribute('data-active'), 10);
                const nextActive = active === 1 ? 0 : 1;

                Swal.fire({
                    title: nextActive === 0 ? 'Deactivate user?' : 'Activate user?',
                    text: nextActive === 0 ? 'Deactivated users cannot log in.' : 'User will be able to log in again.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes'
                }).then(res => {
                    if (!res.isConfirmed) return;
                    const fd = new FormData();
                    fd.append('id', id);
                    fd.append('is_active', String(nextActive));
                    fd.append('_token', PGS.csrf);
                    fetch('user_toggle.php', {
                        method: 'POST',
                        body: fd
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            Swal.fire('Error', data.error || 'Failed to update user', 'error');
                        }
                    })
                    .catch(() => Swal.fire('Error', 'Request failed', 'error'));
                });
            });
        });

        // Page Access handlers
        document.querySelectorAll('.accessBtn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const email = this.getAttribute('data-email');
                document.getElementById('accessUserId').value = id;
                document.getElementById('accessUserEmail').textContent = 'User ID: ' + email;
                fetch('user_access_get.php?id=' + encodeURIComponent(id))
                  .then(r => r.json())
                  .then(data => {
                      document.getElementById('accRoadmaps').checked = !!data.roadmaps;
                      document.getElementById('accScorecard').checked = !!data.scorecard;
                      document.getElementById('accPerformance').checked = !!data.performance_assessment;
                      document.getElementById('accCascading').checked = !!data.cascading;
                      document.getElementById('accGovernance').checked = !!data.governance;
                      const modalEl = document.getElementById('accessModal');
                      new bootstrap.Modal(modalEl).show();
                  })
                  .catch(() => Swal.fire('Error', 'Failed to load access settings', 'error'));
            });
        });

        document.getElementById('accessForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            // Unchecked checkboxes are not sent; set explicitly
            ['roadmaps','scorecard','performance_assessment','cascading','governance'].forEach(k => {
                fd.set(k, this.querySelector('[name="'+k+'"]').checked ? '1' : '0');
            });
            fetch('user_access_update.php', { method: 'POST', body: fd })
              .then(r => r.json())
              .then(data => {
                  if (data.success) {
                      Swal.fire('Saved', 'Access settings updated', 'success');
                      bootstrap.Modal.getInstance(document.getElementById('accessModal')).hide();
                  } else {
                      Swal.fire('Error', data.error || 'Failed to update access', 'error');
                  }
              })
              .catch(() => Swal.fire('Error', 'Request failed', 'error'));
        });

        document.querySelectorAll('.deleteBtn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const email = this.getAttribute('data-email');

                Swal.fire({
                    title: 'Delete user?',
                    text: 'This will permanently delete User ID ' + email,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    confirmButtonText: 'Delete'
                }).then(res => {
                    if (!res.isConfirmed) return;
                    const fd = new FormData();
                    fd.append('id', id);
                    fd.append('_token', PGS.csrf);
                    fetch('user_delete.php', {
                        method: 'POST',
                        body: fd
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Deleted', 'User deleted successfully', 'success').then(() => location.reload());
                        } else {
                            Swal.fire('Error', data.error || 'Failed to delete user', 'error');
                        }
                    })
                    .catch(() => Swal.fire('Error', 'Request failed', 'error'));
                });
            });
        });

        // Role change handlers
        document.querySelectorAll('.roleChangeBtn').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-id');
                const role = btn.getAttribute('data-role');
                const fd = new FormData();
                fd.append('id', id);
                fd.append('role', role);
                fd.append('_token', PGS.csrf);
                fetch('user_role_update.php', { method:'POST', body:fd })
                  .then(r => r.json())
                  .then(data => {
                    if (data && data.success) {
                        Swal.fire('Updated', 'User role changed to ' + role, 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Error', (data && data.error) || 'Failed to change role', 'error');
                    }
                  })
                  .catch(() => Swal.fire('Error', 'Request failed', 'error'));
            });
        });
        // Enhance UX: filter table rows
        (function(){
            const input = document.getElementById('umFilter');
            if (!input) return;
            input.addEventListener('input', function(){
                const q = this.value.toLowerCase();
                document.querySelectorAll('table tbody tr').forEach(tr => {
                    const text = tr.textContent.toLowerCase();
                    tr.style.display = text.includes(q) ? '' : 'none';
                });
            });
            // Enable tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.forEach(function (tooltipTriggerEl) {
              new bootstrap.Tooltip(tooltipTriggerEl);
            });
        })();

        // Edit handlers
        document.querySelectorAll('.editBtn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const email = this.getAttribute('data-email');
                const name = this.getAttribute('data-name') || '';
                const office = this.getAttribute('data-office') || '';
                document.getElementById('edit-id').value = id;
                document.getElementById('edit-email').textContent = 'User ID: ' + email;
                document.getElementById('edit-name').value = name;
                document.getElementById('edit-office').value = office;
                document.getElementById('edit-password').value = '';
                new bootstrap.Modal(document.getElementById('editUserModal')).show();
            });
        });

        document.getElementById('editUserForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            fetch('user_update.php', { method: 'POST', body: fd })
              .then(r => r.json())
              .then(data => {
                if (data && data.success) {
                    Swal.fire('Saved', 'User updated successfully', 'success').then(()=>location.reload());
                } else {
                    Swal.fire('Error', (data && data.error) || 'Failed to update user', 'error');
                }
              })
              .catch(()=> Swal.fire('Error','Request failed','error'));
        });

        // History toggle
        (function(){
            const btn = document.getElementById('historyToggleBtn');
            const wrapper = document.getElementById('historyWrapper');
            if (!btn || !wrapper) return;
            btn.addEventListener('click', function(){
                const isOpen = wrapper.classList.toggle('open');
                btn.classList.toggle('active', isOpen);
                btn.innerHTML = isOpen
                    ? '<i data-lucide="chevron-up" class="toggle-icon me-2"></i>Hide History'
                    : '<i data-lucide="chevron-down" class="toggle-icon me-2"></i>Show History';
            });
        })();

        // History detail click â€” open before/after modal
        (function(){
            document.querySelectorAll('.history-detail-link').forEach(el => {
                el.addEventListener('click', function(){
                    const details = this.getAttribute('data-details') || '';
                    const before = this.getAttribute('data-before') || '';
                    const action = this.getAttribute('data-action') || '';
                    const user = this.getAttribute('data-user') || '';
                    const date = this.getAttribute('data-date') || '';
                    const by = this.getAttribute('data-by') || '';

                    document.getElementById('hdModalUser').textContent = user;
                    document.getElementById('hdModalAction').innerHTML = '<span class="badge badge-action" style="font-size:.8rem;">' + action + '</span>';
                    document.getElementById('hdModalDate').textContent = date;
                    document.getElementById('hdModalAfter').textContent = details || 'â€”';
                    document.getElementById('hdModalBy').textContent = by || 'â€”';

                    const beforeSection = document.getElementById('hdBeforeSection');
                    if (before && before.trim() !== '') {
                        beforeSection.style.display = 'block';
                        document.getElementById('hdModalBefore').textContent = before;
                    } else {
                        beforeSection.style.display = 'none';
                    }

                    new bootstrap.Modal(document.getElementById('historyDetailModal')).show();
                });
            });
        })();
