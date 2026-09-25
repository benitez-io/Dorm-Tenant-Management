/* Password reset email, OTP autofill, resend, and cooldown controller. */
(function (window, document) {
  'use strict';

  const cooldownSeconds = 60;
  const resetStorageKeys = ['reset_email', 'latest_otp', 'otp_code', 'reset_otp', 'reset_otp_debug'];

  function clearResetStorage() {
    resetStorageKeys.forEach(function (key) {
      window.sessionStorage.removeItem(key);
      window.localStorage.removeItem(key);
    });
  }

  function showRequestStatus(message, type) {
    const status = document.getElementById('resetRequestStatus');
    if (!status) return;
    status.className = 'alert alert-' + (type === 'success' ? 'success' : 'danger');
    status.textContent = message;
  }

  async function requestOtp(endpoint, email, csrfToken) {
    const response = await fetch(endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({ email: email, csrf_token: csrfToken })
    });
    const payload = await response.json();

    if (!response.ok || !payload.success) {
      throw new Error(payload.message || 'We could not send a verification code.');
    }

    if (payload.debug_code) {
      window.sessionStorage.setItem('latest_otp', String(payload.debug_code));
    }
    return payload;
  }

  function initStepOne() {
    const form = document.getElementById('forgotPasswordForm');
    if (!form) return;

    form.addEventListener('submit', async function (event) {
      if (!form.checkValidity()) return;
      event.preventDefault();

      const email = form.querySelector('[name="email"]')?.value.trim() || '';
      const csrfToken = form.querySelector('[name="csrf_token"]')?.value || '';
      const submitButton = form.querySelector('[type="submit"]');
      const endpoint = form.dataset.endpoint || form.action;
      const redirectUrl = form.dataset.redirect;

      clearResetStorage();
      window.sessionStorage.setItem('reset_email', email);
      if (submitButton) submitButton.disabled = true;
      showRequestStatus('Sending your verification code...', 'success');

      try {
        const payload = await requestOtp(endpoint, email, csrfToken);
        showRequestStatus(payload.message, 'success');
        window.location.assign(redirectUrl + '?email=' + encodeURIComponent(email));
      } catch (error) {
        if (submitButton) submitButton.disabled = false;
        showRequestStatus(error.message, 'error');
      }
    });
  }

  function populateCode(code) {
    const digits = String(code || '').replace(/\D/g, '').slice(0, 6);
    const boxes = Array.from(document.querySelectorAll('.otp-box'));
    const singleField = document.getElementById('otp');

    if (boxes.length) {
      boxes.forEach(function (box, index) {
        box.value = digits[index] || '';
        box.dispatchEvent(new Event('input', { bubbles: true }));
        box.dispatchEvent(new Event('change', { bubbles: true }));
      });
      const focusTarget = boxes[Math.min(digits.length, boxes.length - 1)];
      if (focusTarget) focusTarget.focus();
      return;
    }

    if (singleField) {
      singleField.value = digits;
      singleField.dispatchEvent(new Event('input', { bubbles: true }));
      singleField.dispatchEvent(new Event('change', { bubbles: true }));
    }
  }

  function initAutofill() {
    const button = document.getElementById('autoFillBtn');
    if (!button) return;

    button.addEventListener('click', function () {
      const badge = document.getElementById('demoCodeBadge');
      const code = window.sessionStorage.getItem('latest_otp')
        || badge?.dataset.rawOtp
        || '';

      if (!code) {
        window.alert('No local test code is available. Check your email for the verification code.');
        return;
      }

      populateCode(code);
    });
  }

  function startCooldown(button) {
    let remaining = cooldownSeconds;
    const originalLabel = button.dataset.originalLabel || button.textContent.trim();
    button.dataset.originalLabel = originalLabel;
    button.disabled = true;
    button.textContent = 'Resend in ' + remaining + 's';

    const timer = window.setInterval(function () {
      remaining -= 1;
      if (remaining <= 0) {
        window.clearInterval(timer);
        button.disabled = false;
        button.textContent = originalLabel;
        delete button.dataset.cooldownTimer;
        return;
      }
      button.textContent = 'Resend in ' + remaining + 's';
    }, 1000);
    button.dataset.cooldownTimer = String(timer);
  }

  function cancelCooldown(button) {
    if (button.dataset.cooldownTimer) {
      window.clearInterval(Number(button.dataset.cooldownTimer));
      delete button.dataset.cooldownTimer;
    }
    button.disabled = false;
    button.textContent = button.dataset.originalLabel || 'Request a new one';
  }

  function initResend() {
    const button = document.getElementById('resendCodeBtn');
    if (!button) return;

    button.addEventListener('click', async function () {
      if (button.disabled) return;

      const email = window.sessionStorage.getItem('reset_email');
      if (!email) {
        window.location.assign(button.dataset.stepOne || '/dorm-tenant-system/auth/forgot_password.php');
        return;
      }

      const csrfToken = document.querySelector('[name="csrf_token"]')?.value || '';
      const endpoint = button.dataset.endpoint || '/dorm-tenant-system/api/resend_otp.php';
      startCooldown(button);

      try {
        await requestOtp(endpoint, email, csrfToken);
      } catch (error) {
        cancelCooldown(button);
        window.alert(error.message || 'We could not send a new code.');
      }
    });
  }

  function guardStepTwo() {
    if (!document.getElementById('resetForm')) return;
    if (!window.sessionStorage.getItem('reset_email')) {
      window.location.replace('/dorm-tenant-system/auth/forgot_password.php');
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    initStepOne();
    guardStepTwo();
    initAutofill();
    initResend();
  });
})(window, document);
