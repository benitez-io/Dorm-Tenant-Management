/* Password reset flow: Step 1, OTP autofill, resend, and cooldown. */
(function (window, document) {
  'use strict';

  const cooldownSeconds = 60;
  const resetStorageKeys = ['reset_email', 'otp_code', 'reset_otp', 'reset_otp_debug'];

  function clearResetStorage() {
    resetStorageKeys.forEach(function (key) {
      window.sessionStorage.removeItem(key);
      window.localStorage.removeItem(key);
    });
  }

  function showStatus(message, type) {
    const status = document.getElementById('resetRequestStatus');
    if (!status) return;
    status.className = 'alert alert-' + (type === 'success' ? 'success' : 'danger');
    status.textContent = message;
  }

  function storeEmailAndRequestCode(form) {
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
      showStatus('Sending your verification code...', 'success');

      try {
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

        if (payload.otp) {
          window.sessionStorage.setItem('reset_otp', String(payload.otp));
        }
        showStatus(payload.message || 'Verification code sent successfully.', 'success');
        window.location.assign(redirectUrl + '?email=' + encodeURIComponent(email));
      } catch (error) {
        if (submitButton) submitButton.disabled = false;
        showStatus(error.message || 'We could not send a verification code.', 'error');
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
      const code = badge?.dataset.rawOtp
        || window.sessionStorage.getItem('reset_otp')
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
    document.querySelectorAll('#resendCodeBtn').forEach(function (button) {
      button.addEventListener('click', async function () {
        if (button.disabled) return;

        const email = window.sessionStorage.getItem('reset_email');
        if (!email) {
          window.location.assign(button.dataset.stepOne || '/dorm-tenant-system/auth/forgot_password.php');
          return;
        }

        const csrfToken = document.querySelector('[name="csrf_token"]')?.value || '';
        const endpoint = button.dataset.endpoint || '/dorm-tenant-system/api/send_otp.php';
        startCooldown(button);

        try {
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
            throw new Error(payload.message || 'We could not send a new code.');
          }

          if (payload.otp) {
            window.sessionStorage.setItem('reset_otp', String(payload.otp));
          }
          startCooldown(button);
        } catch (error) {
          cancelCooldown(button);
          window.alert(error.message || 'We could not send a new code.');
        }
      });
    });
  }

  function guardStepTwo() {
    if (!document.getElementById('resetForm')) return;
    if (!window.sessionStorage.getItem('reset_email')) {
      window.location.replace('/dorm-tenant-system/auth/forgot_password.php');
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    const forgotForm = document.getElementById('forgotPasswordForm');
    if (forgotForm) storeEmailAndRequestCode(forgotForm);

    guardStepTwo();
    initAutofill();
    initResend();
  });
})(window, document);
