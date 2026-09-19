/**
 * assets/js/main.js
 * Small, framework-free interactivity shared by every page.
 */
document.addEventListener('DOMContentLoaded', function () {
  // ---- Mobile sidebar toggle -----------------------------------------
  const menuToggle = document.getElementById('menuToggle');
  const sidebar = document.getElementById('sidebar');
  const backdrop = document.getElementById('sidebarBackdrop');

  if (menuToggle && sidebar && backdrop) {
    menuToggle.addEventListener('click', function () {
      sidebar.classList.toggle('open');
      backdrop.classList.toggle('show');
    });
    backdrop.addEventListener('click', function () {
      sidebar.classList.remove('open');
      backdrop.classList.remove('show');
    });
  }

  // ---- Auto-dismiss flash alerts after a few seconds -----------------
  document.querySelectorAll('.alert').forEach(function (alertEl) {
    setTimeout(function () {
      const alert = bootstrap.Alert.getOrCreateInstance(alertEl);
      alert.close();
    }, 6000);
  });

  // ---- Show/hide toggle on every password field ------------------------
  document.querySelectorAll('input[type="password"]').forEach(function (input) {
    let wrapper = input.parentElement;
    if (!wrapper.classList.contains('input-icon')) {
      wrapper = document.createElement('div');
      wrapper.className = 'input-icon';
      input.parentNode.insertBefore(wrapper, input);
      wrapper.appendChild(input);
    }

    input.classList.add('has-pw-toggle');

    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'pw-toggle';
    toggle.setAttribute('aria-label', 'Show password');
    toggle.innerHTML = '<i class="bi bi-eye"></i>';
    toggle.addEventListener('click', function () {
      const showing = input.type === 'text';
      input.type = showing ? 'password' : 'text';
      toggle.innerHTML = showing ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
      toggle.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
    });
    wrapper.appendChild(toggle);
  });

  // ---- Demo login quick-fill (login page only) ------------------------
  document.querySelectorAll('.demo-login-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const emailField = document.getElementById('loginEmail');
      const passField = document.getElementById('loginPassword');
      if (emailField && passField) {
        emailField.value = btn.dataset.email;
        passField.value = btn.dataset.password;
      }
    });
  });

  document.querySelectorAll('.module-tabs [data-scroll-target]').forEach(function (tab) {
    tab.addEventListener('click', function (event) {
      const target = document.getElementById(tab.dataset.scrollTarget);
      if (!target) return;

      event.preventDefault();
      const currentTab = document.querySelector('.module-tabs .module-tab.active');
      if (currentTab) currentTab.classList.remove('active');
      tab.classList.add('active');
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });

      const previousTarget = document.querySelector('.anchor-target.anchor-highlight');
      if (previousTarget) {
        previousTarget.classList.remove('anchor-highlight');
        const previousState = previousTarget.querySelector('.selection-state');
        if (previousState) previousState.hidden = true;
      }
      target.classList.add('anchor-highlight');
      const state = target.querySelector('.selection-state');
      if (state) state.hidden = false;
      if (tab.dataset.selectValue) {
        const option = target.querySelector('input[value="' + tab.dataset.selectValue + '"]');
        if (option) option.closest('.type-option').click();
      }
      window.history.replaceState(null, '', tab.getAttribute('href'));
    });
  });

  const initialHash = window.location.hash.slice(1);
  if (initialHash) {
    const initialTab = document.querySelector('.module-tabs [data-scroll-target="' + initialHash + '"]');
    if (initialTab) {
      const currentTab = document.querySelector('.module-tabs .module-tab.active');
      if (currentTab) currentTab.classList.remove('active');
      initialTab.classList.add('active');
    }
  }
});
