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
