/* Shared core UI helpers. */
(function (window, document) {
  'use strict';
  const App = window.App = window.App || {};

  App.toast = function (message, type) {
    const variant = type === 'error' ? 'danger' : (type || 'success');
    let container = document.querySelector('.global-toast-container');
    if (!container) { container = document.createElement('div'); container.className = 'global-toast-container toast-container position-fixed top-0 end-0 p-3'; container.style.zIndex = '1090'; document.body.appendChild(container); }
    const toast = document.createElement('div'); toast.className = 'toast text-bg-' + variant + ' border-0'; toast.setAttribute('role', 'status');
    toast.innerHTML = '<div class="d-flex"><div class="toast-body"></div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>';
    toast.querySelector('.toast-body').textContent = message; container.appendChild(toast);
    const instance = bootstrap.Toast.getOrCreateInstance(toast, { delay: 4000 }); toast.addEventListener('hidden.bs.toast', function () { toast.remove(); }); instance.show();
  };

  App.btnLoading = function (button, loading) {
    if (!button) return;
    if (loading === false) { button.disabled = false; if (button.dataset.originalHtml) button.innerHTML = button.dataset.originalHtml; return; }
    if (!button.dataset.originalHtml) button.dataset.originalHtml = button.innerHTML;
    button.disabled = true; button.innerHTML = '<span class="spinner-border global-submit-spinner" aria-hidden="true"></span><span>Processing...</span>';
  };

  App.chart = function (canvas, config) {
    if (!canvas || !window.Chart) return null;
    const existing = Chart.getChart(canvas); if (existing) existing.destroy();
    return new Chart(canvas, config);
  };

  document.addEventListener('DOMContentLoaded', function () {
(function () {
  const relativeApiUrl = (window.location.pathname.includes('/admin/') || window.location.pathname.includes('/tenant/'))
    ? '../api/get_unread_notifications.php'
    : 'api/get_unread_notifications.php';

  const tabs = ['tenants', 'payments', 'maintenance', 'notifications'];

  const badgeSelectors = {
    tenants: '[data-badge-type="badge-tenants"], #nav-badge-tenants',
    payments: '[data-badge-type="badge-payments"], #nav-badge-payments',
    maintenance: '[data-badge-type="badge-maintenance"], #nav-badge-maintenance',
    notifications: '[data-badge-type="badge-notifications"], #nav-badge-notifications'
  };

  const dotSelector = '[data-badge-type="dot-notifications"], #nav-dot-notifications';

  const getBadgeElements = function (tab) {
    return Array.from(document.querySelectorAll(badgeSelectors[tab]));
  };

  const getNotificationDots = function () {
    return Array.from(document.querySelectorAll(dotSelector));
  };

  const hideIndicator = function (tab) {
    try {
      getBadgeElements(tab).forEach(function (badge) {
        badge.textContent = '0';
        badge.classList.add('hidden');
      });
      if (tab === 'notifications') {
        getNotificationDots().forEach(function (dot) { dot.classList.add('hidden'); });
      }
    } catch (error) {
      console.warn('Notification indicator update skipped:', error);
    }
  };

  const markTabSeen = function (tab) {
    try {
      localStorage.setItem('last_seen_' + tab, String(Date.now()));
    } catch (error) {
      /* Storage disabled fallback */
    }
    hideIndicator(tab);
  };

  const pathTabs = {
    '/admin/tenants.php': 'tenants',
    '/admin/tenant-status.php': 'tenants',
    '/admin/checkinout.php': 'tenants',
    '/admin/payments.php': 'payments',
    '/tenant/payments.php': 'payments',
    '/admin/maintenance.php': 'maintenance',
    '/tenant/maintenance.php': 'maintenance',
    '/admin/notifications.php': 'notifications',
    '/tenant/notifications.php': 'notifications'
  };

  // Bind click handlers to sidebar links so badges clear on click
  document.querySelectorAll('.sidebar a[href], .sidebar .nav-link[href]').forEach(function (link) {
    if (!link) return;
    try {
      const linkPath = new URL(link.href, window.location.href).pathname;
      const tab = Object.keys(pathTabs).find(function (path) { return linkPath.endsWith(path); });
      if (tab) {
        link.addEventListener('click', function () {
          markTabSeen(pathTabs[tab]);
        });
      }
    } catch (error) {
      console.warn('Sidebar link tracking skipped:', error);
    }
  });

  // Construct timestamp query string
  const query = tabs.map(function (tab) {
    let value = '';
    try { value = localStorage.getItem('last_seen_' + tab) || ''; } catch (e) { value = ''; }
    return value ? 'last_seen_' + tab + '=' + encodeURIComponent(value) : '';
  }).filter(Boolean).join('&');

  // Fetch updated counts
  fetch(relativeApiUrl + (query ? '?' + query : ''), {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
    .then(function (response) { return response.json(); })
    .then(function (data) {
      if (!data || data.status !== 'success' || !data.counts) return;
      const counts = data.counts || {};

      tabs.forEach(function (tab) {
        const badges = getBadgeElements(tab);
        const dots = tab === 'notifications' ? getNotificationDots() : [];
        const count = Number(counts[tab]) || 0;

        if (tab === 'notifications') {
          const alertCount = Number(counts.notifications) || 0;
          if (alertCount > 0) {
            badges.forEach(function (badge) {
              badge.textContent = alertCount > 99 ? '99+' : String(alertCount);
              badge.classList.remove('hidden');
            });
            dots.forEach(function (dot) { dot.classList.add('hidden'); });
          } else {
            dots.forEach(function (dot) {
              dot.classList.toggle('hidden', Number(counts.announcements) <= 0);
            });
            badges.forEach(function (badge) { badge.classList.add('hidden'); });
          }
        } else {
          badges.forEach(function (badge) {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.classList.toggle('hidden', count === 0);
          });
        }
      });
    })
    .catch(function (err) {
      console.warn('Failed to update notification indicators:', err);
    });
})();
    document.querySelectorAll('.alert:not([data-app-alert])').forEach(function (alert) {
      alert.dataset.appAlert = 'true'; window.setTimeout(function () { bootstrap.Alert.getOrCreateInstance(alert).close(); }, 4000);
    });
    document.addEventListener('submit', function (event) {
      if (event.defaultPrevented) return;
      const form = event.target; const button = form instanceof HTMLFormElement && form.querySelector('button[type="submit"], button:not([type])');
      if (!button || form.dataset.appLoading) return;
      form.dataset.appLoading = 'true'; App.btnLoading(button);
    });
    document.querySelectorAll('.btn-print, [data-action="print"]').forEach(function (button) { button.addEventListener('click', function (event) { event.preventDefault(); window.print(); }); });
  });
})(window, document);
