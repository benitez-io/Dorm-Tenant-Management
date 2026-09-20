/**
 * assets/js/main.js
 * Small, framework-free interactivity shared by every page.
 */
function formatRelativeTime(dateString) {
  if (!dateString) return 'Just now';

  const past = new Date(dateString).getTime();
  if (Number.isNaN(past)) return 'Just now';

  const diffInSeconds = Math.max(0, Math.floor((Date.now() - past) / 1000));
  if (diffInSeconds < 30) return 'Just now';
  if (diffInSeconds < 60) return diffInSeconds + 's ago';

  const diffInMinutes = Math.floor(diffInSeconds / 60);
  if (diffInMinutes < 60) return diffInMinutes + 'm ago';

  const diffInHours = Math.floor(diffInMinutes / 60);
  if (diffInHours < 24) return diffInHours + 'h ago';

  const diffInDays = Math.floor(diffInHours / 24);
  return diffInDays + 'd ago';
}

function updateRelativeTimes() {
  document.querySelectorAll('.js-relative-time[data-relative-time]').forEach(function (element) {
    element.textContent = formatRelativeTime(element.dataset.relativeTime);
  });
}

document.addEventListener('DOMContentLoaded', function () {
  updateRelativeTimes();
  window.setInterval(updateRelativeTimes, 10000);

  const authCanvas = document.getElementById('auth-bg-canvas');
  if (authCanvas && authCanvas.closest('.auth-page')) {
    const context = authCanvas.getContext('2d');
    let width = authCanvas.width = window.innerWidth;
    let height = authCanvas.height = window.innerHeight;
    const particles = Array.from({ length: 30 }, function () {
      return {
        x: Math.random() * width,
        y: Math.random() * height,
        radius: Math.random() * 3 + 1,
        vx: (Math.random() - .5) * .5,
        vy: (Math.random() - .5) * .5,
        alpha: Math.random() * .5 + .2
      };
    });
    window.addEventListener('resize', function () {
      width = authCanvas.width = window.innerWidth;
      height = authCanvas.height = window.innerHeight;
    });
    function animateAuthBackground() {
      context.clearRect(0, 0, width, height);
      particles.forEach(function (particle) {
        particle.x += particle.vx;
        particle.y += particle.vy;
        if (particle.x < 0) particle.x = width;
        if (particle.x > width) particle.x = 0;
        if (particle.y < 0) particle.y = height;
        if (particle.y > height) particle.y = 0;
        context.beginPath();
        context.arc(particle.x, particle.y, particle.radius, 0, Math.PI * 2);
        context.fillStyle = 'rgba(255, 255, 255, ' + particle.alpha + ')';
        context.fill();
      });
      window.requestAnimationFrame(animateAuthBackground);
    }
    animateAuthBackground();
  }

  function formatPhoneNumber(value) {
    const digits = value.replace(/\D/g, '').slice(0, 12);
    if (digits.startsWith('63')) {
      let result = '+' + digits.slice(0, 2);
      if (digits.length > 2) result += ' ' + digits.slice(2, 5);
      if (digits.length > 5) result += ' ' + digits.slice(5, 8);
      if (digits.length > 8) result += ' ' + digits.slice(8, 12);
      return result;
    }
    let result = digits.slice(0, 4);
    if (digits.length > 4) result += ' ' + digits.slice(4, 7);
    if (digits.length > 7) result += ' ' + digits.slice(7, 11);
    return result;
  }

  const phoneInput = document.getElementById('phoneNumber');
  if (phoneInput) {
    phoneInput.addEventListener('input', function (event) {
      event.target.value = formatPhoneNumber(event.target.value);
    });
  }

  function getPasswordCriteria(value) {
    return {
      length: value.length >= 8,
      uppercase: /[A-Z]/.test(value),
      lowercase: /[a-z]/.test(value),
      number: /[0-9]/.test(value),
      special: /[^A-Za-z0-9]/.test(value)
    };
  }

  function getPasswordStrength(score) {
    if (score <= 0) return { label: 'Very Weak', color: '#dc3545' };
    if (score === 1) return { label: 'Weak', color: '#f97316' };
    if (score === 2) return { label: 'Fair', color: '#f59e0b' };
    if (score === 3) return { label: 'Good', color: '#22c55e' };
    return { label: 'Strong', color: '#16a34a' };
  }

  function updatePasswordStrengthMeter(input) {
    const container = document.querySelector('[data-strength-for="' + input.id + '"]');
    if (!container) return;

    const criteria = getPasswordCriteria(input.value);
    const strengthScore = Object.keys(criteria).filter(function (key) { return criteria[key]; }).length;
    const strength = getPasswordStrength(strengthScore);
    const segments = container.querySelectorAll('.strength-segment');
    const label = container.querySelector('.strength-label');
    const count = container.querySelector('.strength-count');
    const checklistItems = container.closest('form') ? container.closest('form').querySelectorAll('.pass-check-item[data-requirement]') : document.querySelectorAll('.pass-check-item[data-requirement]');

    segments.forEach(function (segment, index) {
      const active = index < strengthScore;
      segment.style.backgroundColor = active ? strength.color : '#e2e8f0';
      segment.style.borderColor = active ? strength.color : 'rgba(148, 163, 184, 0.15)';
      segment.style.opacity = active ? '1' : '0.8';
    });

    if (label) {
      label.textContent = strength.label;
      label.style.color = strength.color;
    }
    if (count) {
      count.textContent = strengthScore + '/5 requirements met';
      count.style.color = strengthScore >= 4 ? '#1e8a4c' : '#64748b';
    }

    checklistItems.forEach(function (item) {
      const key = item.dataset.requirement;
      const passed = Boolean(criteria[key]);
      const icon = item.querySelector('i');
      item.classList.toggle('is-met', passed);
      item.classList.toggle('is-missing', !passed);
      if (icon) {
        icon.className = passed ? 'bi bi-check-lg' : 'bi bi-x-lg';
      }
    });
  }

  const registerForm = document.getElementById('registerForm');
  if (registerForm) {
    registerForm.addEventListener('submit', function (event) {
      const requiredInputs = registerForm.querySelectorAll('[required]');
      let isValid = true;
      requiredInputs.forEach(function (input) {
        const filled = input.value.trim() !== '' && input.checkValidity();
        input.classList.toggle('is-invalid', !filled);
        if (!filled) isValid = false;
      });

      const password = document.getElementById('regPassword') || document.getElementById('password');
      const confirmPassword = document.getElementById('regConfirmPassword') || document.getElementById('confirm_password');
      if (password && confirmPassword) {
        const criteria = getPasswordCriteria(password.value);
        const meetsAllRules = Object.keys(criteria).every(function (key) { return criteria[key]; });
        if (!meetsAllRules || password.value !== confirmPassword.value) isValid = false;
      }

      if (!isValid) {
        event.preventDefault();
        const notice = document.getElementById('formErrorNotice');
        if (notice) {
          notice.textContent = password && confirmPassword && password.value && confirmPassword.value && password.value !== confirmPassword.value ? 'Password and Confirm Password do not match.' : 'Please fill in all required details correctly before creating your account.';
          notice.classList.remove('d-none');
        }
      }
    });
  }

  document.querySelectorAll('.js-toggle-pwd').forEach(function (button) {
    button.addEventListener('click', function () {
      const input = document.getElementById(button.dataset.target);
      const icon = button.querySelector('i');
      if (!input) return;
      const isPassword = input.type === 'password';
      input.type = isPassword ? 'text' : 'password';
      if (icon) icon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
      button.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
    });
  });

  document.querySelectorAll('.js-password-strength').forEach(function (input) {
    updatePasswordStrengthMeter(input);
    input.addEventListener('input', function () {
      updatePasswordStrengthMeter(input);
    });
  });

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
    if (input.closest('.input-field-wrapper')) return;
    if (input.closest('.input-group') && input.parentElement.querySelector('.toggle-password, .btn-toggle-eye, .pw-toggle, .js-toggle-pwd, .js-toggle-password-btn')) return;
    if (input.parentElement && input.parentElement.querySelector('.toggle-password, .btn-toggle-eye, .pw-toggle, .js-toggle-pwd, .js-toggle-password-btn')) return;
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
    toggle.className = 'pw-toggle js-toggle-password-btn';
    toggle.setAttribute('aria-label', 'Show password');
    toggle.innerHTML = '<i class="bi bi-eye-slash js-password-icon"></i>';
    toggle.addEventListener('click', function () {
      const showing = input.type === 'text';
      input.type = showing ? 'password' : 'text';
      toggle.innerHTML = showing ? '<i class="bi bi-eye-slash js-password-icon"></i>' : '<i class="bi bi-eye js-password-icon"></i>';
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
