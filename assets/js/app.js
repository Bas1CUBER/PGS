/**
 * PGS shared application JS.
 * Loaded via templates/navbar.php after the window.PGS config bootstrap.
 * Handles: lucide icons, navbar submenus, access gating, notifications,
 * deadline countdown banner, and flash-message auto-dismiss.
 */
(function () {
  'use strict';

  // ---------- Lucide icons ----------
  function initIcons() {
    if (window.lucide && typeof lucide.createIcons === 'function') {
      lucide.createIcons();
    }
  }

  // ---------- Navbar submenu behavior ----------
  function initSubmenus() {
    // (Bootstrap 5 handles dropdowns natively; placeholder kept for future nested menus)
  }

  // ---------- Notification system ----------
  function initNotifications() {
    var baseUrl = (window.PGS && window.PGS.baseUrl) || '';
    var badge = document.querySelector('.notification-badge');
    var notifList = document.getElementById('notificationList');
    var markAllReadBtn = document.getElementById('markAllReadBtn');
    var notifDropdown = document.getElementById('notificationDropdown');

    if (!badge || !notifList) return;

    function getTypeIcon(type) {
      var icons = {
        'upload': '<i data-lucide="upload"></i>',
        'approved': '<i data-lucide="check"></i>',
        'returned': '<i data-lucide="undo-2"></i>',
        'edit': '<i data-lucide="pencil"></i>'
      };
      return icons[type] || '<i data-lucide="bell"></i>';
    }

    function updateBadge(count) {
      if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.style.display = 'block';
      } else {
        badge.style.display = 'none';
      }
    }

    function escapeHtml(text) {
      var div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    function groupLabel(createdAt) {
      if (!createdAt) return 'Earlier';
      var d = new Date(createdAt.replace(' ', 'T'));
      var now = new Date();
      var startToday = new Date(now.getFullYear(), now.getMonth(), now.getDate());
      var startYesterday = new Date(startToday);
      startYesterday.setDate(startYesterday.getDate() - 1);
      if (d >= startToday) return 'Today';
      if (d >= startYesterday) return 'Yesterday';
      return 'Earlier';
    }

    function renderSkeleton() {
      var html = '';
      for (var i = 0; i < 3; i++) {
        html += '<div class="notification-skeleton"><span class="sk-avatar"></span>' +
          '<span class="sk-lines"><span class="sk-line"></span><span class="sk-line short"></span></span></div>';
      }
      notifList.innerHTML = html;
    }

    function renderEmpty() {
      notifList.innerHTML = '<div class="notification-empty">' +
        '<i data-lucide="bell-off" width="2em" height="2em" class="mb-2"></i>' +
        '<p class="notification-empty-title mb-1">You\'re all caught up</p>' +
        '<p class="notification-empty-sub mb-0">New notifications will show up here as they arrive.</p></div>';
      if (window.lucide) lucide.createIcons();
    }

    function updateUnreadPill(count) {
      var pill = document.getElementById('unreadPill');
      if (!pill) return;
      if (count > 0) {
        pill.textContent = count === 1 ? '1 unread' : count + ' unread';
        pill.style.display = 'inline-flex';
      } else {
        pill.style.display = 'none';
      }
    }

    function renderNotifications(notifications) {
      if (!notifications.length) {
        renderEmpty();
        updateUnreadPill(0);
        return;
      }

      var groups = { 'Today': [], 'Yesterday': [], 'Earlier': [] };
      notifications.forEach(function (n) {
        groups[groupLabel(n.created_at)].push(n);
      });

      var html = '';
      ['Today', 'Yesterday', 'Earlier'].forEach(function (g) {
        if (!groups[g].length) return;
        html += '<div class="notification-group-label">' + g + '</div>';
        groups[g].forEach(function (n) {
          var unreadClass = n.is_read ? '' : 'unread';
          html += '<div class="notification-item ' + unreadClass + '" data-id="' + n.id + '" role="button" tabindex="0" aria-label="' + (n.title || 'Notification') + '">' +
            '<div class="notification-type-icon ' + n.type + ' me-3">' + getTypeIcon(n.type) + '</div>' +
            '<div class="notification-content">' +
            '<div class="notification-title">' + escapeHtml(n.title) + '</div>' +
            '<div class="notification-message">' + escapeHtml(n.message) + '</div>' +
            '<div class="notification-meta">' +
            '<span class="notification-time">' + n.time_ago + '</span>' +
            (n.related_type_display ? '<span class="notification-tag">' + escapeHtml(n.related_type_display) + '</span>' : '') +
            '</div>' +
            '</div>' +
            '<span class="notification-unread-dot" aria-hidden="true"></span>' +
            '<button type="button" class="notification-mark-read" data-id="' + n.id + '" title="Mark as read" aria-label="Mark as read"><i data-lucide="check"></i></button>' +
            '</div>';
        });
      });
      notifList.innerHTML = html;
      if (window.lucide) lucide.createIcons();

      var unreadCount = notifications.filter(function (n) { return !n.is_read; }).length;
      updateBadge(unreadCount);
      updateUnreadPill(unreadCount);

      notifList.querySelectorAll('.notification-item').forEach(function (item) {
        function mark() {
          var id = item.dataset.id;
          markAsRead(id);
          item.classList.remove('unread');
          var remaining = notifList.querySelectorAll('.notification-item.unread').length;
          updateBadge(remaining);
          updateUnreadPill(remaining);
        }
        item.addEventListener('click', function (e) {
          if (e.target.closest('.notification-mark-read')) return;
          mark();
        });
        item.addEventListener('keydown', function (e) {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            mark();
          }
        });
        var markBtn = item.querySelector('.notification-mark-read');
        if (markBtn) {
          markBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            mark();
          });
        }
      });
    }

    function fetchUnreadCount() {
      fetch(baseUrl + '/notifications_api?action=get_unread_count')
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.ok) updateBadge(data.count);
        })
        .catch(function () {});
    }

    function fetchNotifications() {
      renderSkeleton();
      fetch(baseUrl + '/notifications_api?action=get_notifications')
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.ok) {
            renderNotifications(data.notifications);
            updateBadge(data.unread_count);
          } else {
            renderEmpty();
          }
        })
        .catch(function () { renderEmpty(); });
    }

    function markAsRead(id) {
      var fd = new FormData();
      fd.append('action', 'mark_read');
      fd.append('id', id);
      fetch(baseUrl + '/notifications_api', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.ok) fetchUnreadCount();
        })
        .catch(function () {});
    }

    function markAllRead() {
      var fd = new FormData();
      fd.append('action', 'mark_all_read');
      fetch(baseUrl + '/notifications_api', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.ok) {
            fetchNotifications();
            fetchUnreadCount();
          }
        })
        .catch(function () {});
    }

    if (markAllReadBtn) {
      markAllReadBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        markAllRead();
      });
    }

    if (notifDropdown) {
      notifDropdown.addEventListener('shown.bs.dropdown', function () {
        fetchNotifications();
      });
    }

    fetchUnreadCount();
    setInterval(fetchUnreadCount, 30000);
  }

  // ---------- Deadline countdown banner ----------
  function initDeadlineBanner() {
    var banner = document.getElementById('deadlineBanner');
    var countdownEl = document.getElementById('deadlineCountdown');
    if (!banner || !countdownEl) return;

    var rem = parseInt(banner.getAttribute('data-remaining') || '0', 10) || 0;

    function padBottom() {
      var bh = banner ? banner.offsetHeight : 32;
      document.body.style.paddingBottom = (bh + 8) + 'px';
    }

    function fmt(s) {
      var d = Math.floor(s / 86400);
      s %= 86400;
      var h = Math.floor(s / 3600);
      s %= 3600;
      var m = Math.floor(s / 60);
      var sec = s % 60;
      var parts = [];
      if (d) parts.push(d + 'd');
      parts.push(String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':' + String(sec).padStart(2, '0'));
      return parts.join(' ');
    }

    function onExpire() {
      banner.classList.add('deadline-expired');
      banner.innerHTML = 'Submission window has closed. You can browse pages but actions are disabled.';
      disableInteractions();
    }

    function disableInteractions() {
      var sel = 'button, .btn, a.btn, input:not([type=hidden]), select, textarea, [contenteditable=true]';
      document.querySelectorAll(sel).forEach(function (el) {
        try { el.setAttribute('disabled', 'disabled'); } catch (e) {}
        el.classList.add('disabled');
        el.style.pointerEvents = 'none';
      });
      document.querySelectorAll('form').forEach(function (f) {
        f.addEventListener('submit', function (e) {
          e.preventDefault();
          alert('Submission window has closed.');
        });
      });
    }

    padBottom();
    window.addEventListener('resize', padBottom);

    function render() { countdownEl.textContent = fmt(rem); }
    function tick() {
      if (rem > 0) {
        rem--;
        render();
        if (rem === 0) onExpire();
      }
    }

    if (rem <= 0) {
      onExpire();
    } else {
      render();
      setInterval(tick, 1000);
    }
  }

  // ---------- Flash message auto-dismiss ----------
  function initFlashToasts() {
    document.querySelectorAll('.flash-toast').forEach(function (el) {
      var ms = parseInt(el.getAttribute('data-duration') || '6000', 10);
      setTimeout(function () {
        if (el && el.parentNode) el.parentNode.removeChild(el);
      }, ms);
    });
  }

  // ---------- Boot ----------
  document.addEventListener('DOMContentLoaded', function () {
    initIcons();
    initSubmenus();
    initNotifications();
    initDeadlineBanner();
    initFlashToasts();
  });
})();
