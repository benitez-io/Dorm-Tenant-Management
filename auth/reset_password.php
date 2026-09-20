<?php
require_once __DIR__ . '/../config/app.php';

if (is_logged_in()) {
    redirect(current_role() === 'admin' ? '/admin/dashboard.php' : '/tenant/dashboard.php');
}

$errors = [];
$success = false;
$oldEmail = str_input($_GET, 'email');
$demoOtp = preg_replace('/\D+/', '', (string) ($_SESSION['demo_otp'] ?? '138651'));
if (strlen($demoOtp) !== 6) {
    $demoOtp = '138651';
}
$demoOtpDisplay = substr($demoOtp, 0, 3) . ' - ' . substr($demoOtp, 3, 3);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email           = str_input($_POST, 'email');
    $otp             = str_input($_POST, 'otp');
    $newPassword     = str_input($_POST, 'new_password', '', false);
    $confirmPassword = str_input($_POST, 'confirm_password', '', false);
    $oldEmail        = $email;

    $stmt = get_db()->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    $validCode = $user
        && $user['reset_otp'] !== null
        && hash_equals((string) $user['reset_otp'], $otp)
        && $user['reset_otp_expires'] !== null
        && strtotime($user['reset_otp_expires']) >= time();

    $pwdError = password_policy_error($newPassword);

    if (!$validCode) {
        $errors[] = 'That code is invalid or has expired. Please request a new one.';
    } elseif ($pwdError !== null) {
        $errors[] = $pwdError;
    } elseif ($newPassword !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    } else {
        get_db()->prepare('UPDATE users SET password_hash = ?, reset_otp = NULL, reset_otp_expires = NULL, password_changed_at = NOW() WHERE user_id = ?')
                ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $user['user_id']]);
        $success = true;
    }
}

$pageTitle = 'Reset Password';
include __DIR__ . '/../includes/header.php';
?>
<div class="auth-screen auth-page auth-recovery">
  <canvas id="auth-bg-canvas" aria-hidden="true"></canvas>
  <div class="auth-panel">
    <div class="auth-panel-left auth-sidebar register-style-sidebar">
      <div class="sidebar-circle-accent"></div>
      <div class="position-relative z-1">
        <div class="sidebar-icon-wrapper mb-3 d-flex align-items-center justify-content-center rounded-3"><i class="bi bi-mortarboard-fill fs-4 text-white"></i></div>
        <h2 class="fw-bold fs-4 text-white mb-1">Dorm Tenant<br>Management System</h2>
        <p class="sidebar-description text-white-50 xs-text mb-3">Secure Password Reset</p>
        <div class="sidebar-compact-list"><div class="sidebar-info-card"><div class="sidebar-info-icon"><i class="bi bi-key fs-6"></i></div><div><h6>Verification Code</h6><span>Confirm your email securely</span></div></div><div class="sidebar-info-card"><div class="sidebar-info-icon"><i class="bi bi-shield-check fs-6"></i></div><div><h6>New Password</h6><span>Protect your tenant account</span></div></div></div>
      </div>
      <div class="sidebar-footer compact-sidebar-footer position-relative z-1"><hr class="sidebar-divider mb-3 opacity-25 border-white"><div class="login-redirect-card compact-login-card"><p>Remembered your password?</p><a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-light"><i class="bi bi-chevron-left"></i> Sign In</a></div></div>
    </div>
    <div class="auth-panel-right auth-content">
      <?php if ($success): ?>
        <div class="recovery-success">
          <span class="recovery-icon"><i class="bi bi-check-lg"></i></span>
          <h2>Password Reset Successful</h2>
          <p class="text-muted">Your password has been updated. Sign in with your new credentials.</p>
          <div class="callout callout-success">
            <i class="bi bi-shield-check"></i>
            <div>For your security, you'll be signed out of any other active session the next time it loads a page.</div>
          </div>
          <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-maroon w-100">Return to Login</a>
        </div>
      <?php else: ?>
        <a href="<?= BASE_URL ?>/auth/forgot_password.php" class="recovery-back"><i class="bi bi-arrow-left"></i> Back</a>

        <div class="recovery-header">
          <span class="recovery-icon"><i class="bi bi-key-fill"></i></span>
          <div>
            <h2>Verify &amp; Reset</h2>
            <p class="recovery-subtitle">Enter the code sent to your email and set a new password.</p>
            <div class="step-caption">
              <span class="step-track"><span class="bar active"></span><span class="bar active"></span></span>
                Step 2 of 2 &middot; <span class="recovery-code-target"><?= clean($oldEmail) ?></span>
            </div>
          </div>
        </div>

        <?php if ($errors): ?>
          <div class="alert alert-danger">
            <?php foreach ($errors as $err): ?><div><?= clean($err) ?></div><?php endforeach; ?>
          </div>
        <?php endif; ?>

        <form method="post" class="needs-validation" novalidate id="resetForm">
          <?= csrf_field() ?>
          <input type="hidden" name="email" value="<?= clean($oldEmail) ?>">
          <input type="hidden" name="otp" id="otp">

          <div class="demo-otp-banner rounded-4 p-3 mb-4 d-flex align-items-center justify-content-between border border-warning-subtle shadow-sm">
            <div class="d-flex align-items-center gap-3">
              <div class="demo-icon-box rounded-3 d-flex align-items-center justify-content-center flex-shrink-0"><i class="bi bi-stars text-warning fs-5"></i></div>
              <div>
                <div class="fw-bold text-dark small mb-1">Verification Code Sent</div>
                <div class="d-flex align-items-center gap-2">
                  <span class="text-muted xs-text">Your code:</span>
                  <span id="demoCodeBadge" class="demo-code-pill fw-bold text-dark px-2.5 py-0.5 rounded-pill font-monospace fs-6" data-raw-otp="<?= htmlspecialchars($demoOtp, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($demoOtpDisplay) ?></span>
                </div>
              </div>
            </div>
            <button type="button" id="autoFillBtn" class="btn btn-autofill rounded-pill px-3 py-1.5 fw-bold btn-sm d-flex align-items-center gap-1.5 text-white shadow-sm flex-shrink-0"><i class="bi bi-clipboard-check"></i><span>Auto-fill</span></button>
          </div>
          <label class="form-label fw-bold">Verification Code <span class="text-danger">*</span></label>
          <p class="text-muted small mb-3">Enter the 6-digit code from your email</p>
          <div class="otp-boxes">
            <?php for ($i = 0; $i < 6; $i++): ?>
              <input type="text" class="otp-box otp-field form-control text-center fw-bold fs-4 rounded-3" id="otpBox<?= $i ?>" data-index="<?= $i ?>" inputmode="numeric" maxlength="1" autocomplete="one-time-code">
            <?php endfor; ?>
          </div>
          <div class="otp-dots-grid d-flex justify-content-between px-2 mt-2"><span class="dot-indicator active" data-dot="0"></span><span class="dot-indicator" data-dot="1"></span><span class="dot-indicator" data-dot="2"></span><span class="dot-indicator" data-dot="3"></span><span class="dot-indicator" data-dot="4"></span><span class="dot-indicator" data-dot="5"></span></div>
          <div class="d-flex align-items-center gap-2 mb-2"><div class="progress flex-grow-1 otp-progress"><div id="otpProgressBar" class="progress-bar bg-maroon" role="progressbar" style="width:0%"></div></div><span id="otpCountText" class="text-muted xxs-text font-monospace">0/6</span></div>
          <div class="otp-count d-none"><span id="otpCount">0</span>/6</div>

          <div class="resend-row">
            <span id="resendTimer">Resend code in <span id="resendSeconds">60</span>s</span>
            <button type="button" id="resendBtn" style="display:none" disabled>Resend Code</button>
          </div>

          <div class="callout callout-info">
            <i class="bi bi-info-circle-fill"></i>
            <div>Password requirements: at least 8 characters, containing uppercase, lowercase, a number and a special character (e.g. <code>Hrm@2026!</code>).</div>
          </div>

          <div class="password-field-wrap mb-3">
            <label class="form-label fw-bold text-dark small mb-1">Create New Password <span class="text-danger">*</span></label>
            <div class="input-group overflow-hidden rounded-3 border">
              <span class="input-group-text bg-light border-0 text-muted ps-3"><i class="bi bi-lock-fill"></i></span>
              <input type="password" id="newPassword" name="new_password" class="form-control border-0 bg-light shadow-none" placeholder="••••••••" value="" autocomplete="new-password" required>
              <button type="button" class="btn btn-light border-0 text-muted px-3 toggle-password" data-target="newPassword"><i class="bi bi-eye"></i></button>
            </div>

            <div class="strength-bar-container d-flex gap-1 mt-2">
              <div class="strength-segment flex-fill" id="seg1"></div>
              <div class="strength-segment flex-fill" id="seg2"></div>
              <div class="strength-segment flex-fill" id="seg3"></div>
              <div class="strength-segment flex-fill" id="seg4"></div>
              <div class="strength-segment flex-fill" id="seg5"></div>
            </div>
          </div>

          <div class="requirements-box p-3 rounded-3 bg-light mb-4">
            <div class="row g-2">
              <div class="col-6">
                <div class="req-item text-muted xxs-text d-flex align-items-center gap-2" id="req-length"><i class="bi bi-circle req-icon"></i><span>At least 8 characters</span></div>
                <div class="req-item text-muted xxs-text d-flex align-items-center gap-2 mt-2" id="req-lower"><i class="bi bi-circle req-icon"></i><span>Lowercase letter (a-z)</span></div>
                <div class="req-item text-muted xxs-text d-flex align-items-center gap-2 mt-2" id="req-special"><i class="bi bi-circle req-icon"></i><span>Special character (@!#…)</span></div>
              </div>
              <div class="col-6">
                <div class="req-item text-muted xxs-text d-flex align-items-center gap-2" id="req-upper"><i class="bi bi-circle req-icon"></i><span>Uppercase letter (A-Z)</span></div>
                <div class="req-item text-muted xxs-text d-flex align-items-center gap-2 mt-2" id="req-number"><i class="bi bi-circle req-icon"></i><span>Number (0-9)</span></div>
              </div>
            </div>
          </div>

          <div class="password-field-wrap mb-4">
            <label class="form-label fw-bold text-dark small mb-1">Confirm New Password <span class="text-danger">*</span></label>
            <div class="input-group overflow-hidden rounded-3 border">
              <span class="input-group-text bg-light border-0 text-muted ps-3"><i class="bi bi-lock-fill"></i></span>
              <input type="password" id="confirmPassword" name="confirm_password" class="form-control border-0 bg-light shadow-none" placeholder="••••••••" value="" autocomplete="new-password" required>
              <button type="button" class="btn btn-light border-0 text-muted px-3 toggle-password" data-target="confirmPassword"><i class="bi bi-eye"></i></button>
            </div>
          </div>

          <div class="status-indicators d-flex justify-content-center gap-4 mb-4 xxs-text">
            <span class="status-dot-item d-flex align-items-center gap-2 active-green" id="dot-otp"><span class="dot"></span><span>OTP Complete</span></span>
            <span class="status-dot-item d-flex align-items-center gap-2 text-muted" id="dot-valid"><span class="dot"></span><span>Password Valid</span></span>
            <span class="status-dot-item d-flex align-items-center gap-2 text-muted" id="dot-match"><span class="dot"></span><span>Passwords Match</span></span>
          </div>

          <button type="submit" id="resetSubmitBtn" class="btn btn-reset-submit w-100 py-2.5 rounded-pill fw-bold d-flex align-items-center justify-content-center gap-2" disabled>
            <i class="bi bi-shield-check"></i>
            <span>Reset Password &amp; Log In</span>
          </button>
        </form>
        <form method="post" action="<?= BASE_URL ?>/auth/forgot_password.php" id="resendForm" style="display:none">
          <?= csrf_field() ?>
          <input type="hidden" name="email" value="<?= clean($oldEmail) ?>">
        </form>
        <p class="text-center mt-3 mb-0">
          Didn't get a code? <a href="<?= BASE_URL ?>/auth/forgot_password.php">Request a new one</a>
        </p>
      <?php endif; ?>
    </div>
  </div>
  <footer class="auth-global-footer text-center"><p class="xxs-text text-white-50 mb-0">&copy; 2026 Teen T-ITans &middot; Dorm Tenant Management System</p></footer>
</div>

<?php
$extraScripts = <<<'HTML'
<script>
(function () {
  const otpBoxes = Array.from(document.querySelectorAll('.otp-box'));
  const otpHidden = document.getElementById('otp');
  const otpCount = document.getElementById('otpCount');
  const statusOtp = document.getElementById('statusOtp');
  if (!otpBoxes.length) return;

  function populateOtp(codeString) {
    const digits = (codeString || '').replace(/\D/g, '').slice(0, otpBoxes.length);
    otpBoxes.forEach((box, index) => {
      box.value = digits[index] || '';
    });
    if (otpBoxes[Math.min(digits.length, otpBoxes.length - 1)]) {
      otpBoxes[Math.min(digits.length, otpBoxes.length - 1)].focus();
    }
    syncOtp();
  }

  function updateSubmit() {
    const otpDone = otpHidden ? otpHidden.value.length === 6 : false;
    const otpDot = document.getElementById('dot-otp');
    if (otpDot) {
      otpDot.classList.toggle('active-green', otpDone);
      otpDot.classList.toggle('text-muted', !otpDone);
    }

    const submitBtn = document.getElementById('resetSubmitBtn');
    const newPass = document.getElementById('newPassword');
    const confirmPass = document.getElementById('confirmPassword');
    if (!submitBtn || !newPass || !confirmPass) return;

    const val = newPass.value;
    const confirmVal = confirmPass.value;
    const checks = {
      length: val.length >= 8,
      upper: /[A-Z]/.test(val),
      lower: /[a-z]/.test(val),
      number: /[0-9]/.test(val),
      special: /[^A-Za-z0-9]/.test(val)
    };

    const isPasswordValid = Object.values(checks).every(Boolean);
    const isMatch = isPasswordValid && val === confirmVal && confirmVal.length > 0;

    if (isPasswordValid && isMatch && otpDone) {
      submitBtn.removeAttribute('disabled');
      submitBtn.classList.add('btn-maroon-active');
    } else {
      submitBtn.setAttribute('disabled', 'true');
      submitBtn.classList.remove('btn-maroon-active');
    }
  }

  function syncOtp() {
    const value = otpBoxes.map(b => b.value).join('');
    const dots = document.querySelectorAll('.otp-dots-grid .dot-indicator');
    const progressBar = document.getElementById('otpProgressBar');
    const countText = document.getElementById('otpCountText');
    let activeIndex = Array.from(otpBoxes).findIndex(input => input === document.activeElement);
    if (activeIndex === -1) activeIndex = Math.min(value.length, otpBoxes.length - 1);

    otpHidden.value = value;
    if (otpCount) otpCount.textContent = value.length;
    if (countText) countText.textContent = value.length + '/6';
    if (progressBar) progressBar.style.width = (value.length / 6 * 100) + '%';

    let filledCount = 0;
    otpBoxes.forEach((box, index) => {
      const val = box.value.trim();
      if (val !== '') {
        box.classList.add('is-filled');
        filledCount++;
      } else {
        box.classList.remove('is-filled');
      }

      if (dots[index]) {
        dots[index].classList.remove('filled', 'active');
        if (index < filledCount) {
          dots[index].classList.add('filled');
        } else if (index === activeIndex || (index === filledCount && filledCount < 6)) {
          dots[index].classList.add('active');
        }
      }
    });

    if (statusOtp) statusOtp.classList.toggle('ok', value.length === 6);
    updateSubmit();
  }

  otpBoxes.forEach((box, i) => {
    box.addEventListener('input', function () {
      box.value = box.value.replace(/[^0-9]/g, '').slice(0, 1);
      if (box.value && i < otpBoxes.length - 1) otpBoxes[i + 1].focus();
      syncOtp();
    });
    box.addEventListener('focus', () => syncOtp());
    box.addEventListener('keydown', function (e) {
      if (e.key === 'Backspace' && !box.value && i > 0) {
        otpBoxes[i - 1].focus();
      }
    });
    box.addEventListener('paste', function (e) {
      const digits = (e.clipboardData.getData('text').match(/[0-9]/g) || []).slice(0, otpBoxes.length);
      if (!digits.length) return;
      e.preventDefault();
      digits.forEach((d, idx) => { if (otpBoxes[idx]) otpBoxes[idx].value = d; });
      const next = otpBoxes[Math.min(digits.length, otpBoxes.length - 1)];
      if (next) next.focus();
      syncOtp();
    });
  });

  const autoFillBtn = document.getElementById('autoFillBtn');
  const demoCodeBadge = document.getElementById('demoCodeBadge');
  if (autoFillBtn && demoCodeBadge) {
    autoFillBtn.addEventListener('click', function () {
      const digits = demoCodeBadge.getAttribute('data-raw-otp') || demoCodeBadge.textContent || '';
      populateOtp(digits);
    });
  }

  syncOtp();

  // ---- Resend cooldown (fixed 60s, purely a UI convenience) ----
  const resendTimer = document.getElementById('resendTimer');
  const resendSeconds = document.getElementById('resendSeconds');
  const resendBtn = document.getElementById('resendBtn');
  const resendForm = document.getElementById('resendForm');
  let remaining = 60;
  const tick = setInterval(function () {
    remaining -= 1;
    if (remaining <= 0) {
      clearInterval(tick);
      resendTimer.style.display = 'none';
      resendBtn.style.display = '';
      resendBtn.disabled = false;
    } else {
      resendSeconds.textContent = remaining;
    }
  }, 1000);
  resendBtn.addEventListener('click', function () {
    resendForm.submit();
  });

  // ---- Password strength + requirement checklist ----
  const newPass = document.getElementById('newPassword');
  const confirmPass = document.getElementById('confirmPassword');
  const submitBtn = document.getElementById('resetSubmitBtn');

  if (newPass && confirmPass && submitBtn) {
    const reqs = {
      length: document.getElementById('req-length'),
      upper: document.getElementById('req-upper'),
      lower: document.getElementById('req-lower'),
      number: document.getElementById('req-number'),
      special: document.getElementById('req-special')
    };

    const dotValid = document.getElementById('dot-valid');
    const dotMatch = document.getElementById('dot-match');
    const segments = ['seg1', 'seg2', 'seg3', 'seg4', 'seg5'].map(id => document.getElementById(id));

    document.querySelectorAll('.toggle-password').forEach((btn) => {
      btn.onclick = function (e) {
        e.preventDefault();
        const targetId = this.getAttribute('data-target');
        const input = document.getElementById(targetId);
        const icon = this.querySelector('i');

        if (!input || !icon) return;

        if (input.type === 'password') {
          input.type = 'text';
          icon.className = 'bi bi-eye-slash';
        } else {
          input.type = 'password';
          icon.className = 'bi bi-eye';
        }
      };
    });

    function validateForm() {
      const val = newPass.value;
      const confirmVal = confirmPass.value;

      const checks = {
        length: val.length >= 8,
        upper: /[A-Z]/.test(val),
        lower: /[a-z]/.test(val),
        number: /[0-9]/.test(val),
        special: /[^A-Za-z0-9]/.test(val)
      };

      let passedCount = 0;

      Object.keys(checks).forEach((key) => {
        const isPassed = checks[key];
        const el = reqs[key];
        if (!el) return;

        const icon = el.querySelector('.req-icon');

        if (isPassed) {
          passedCount++;
          el.classList.add('passed');
          icon.className = 'bi bi-check-circle-fill req-icon text-success';
        } else {
          el.classList.remove('passed');
          icon.className = 'bi bi-circle req-icon';
        }
      });

      segments.forEach((seg, idx) => {
        if (!seg) return;
        seg.style.backgroundColor = idx < passedCount ? (passedCount === 5 ? '#700000' : '#f59e0b') : '#e2e8f0';
      });

      const isPasswordValid = passedCount === 5;
      const isMatch = isPasswordValid && val === confirmVal && confirmVal.length > 0;
      const otpDone = otpHidden ? otpHidden.value.length === 6 : true;

      if (isPasswordValid) {
        dotValid.classList.add('active-green');
        dotValid.classList.remove('text-muted');
      } else {
        dotValid.classList.remove('active-green');
        dotValid.classList.add('text-muted');
      }

      if (isMatch) {
        dotMatch.classList.add('active-green');
        dotMatch.classList.remove('text-muted');
      } else {
        dotMatch.classList.remove('active-green');
        dotMatch.classList.add('text-muted');
      }

      updateSubmit();
    }

    newPass.addEventListener('input', validateForm);
    confirmPass.addEventListener('input', validateForm);
    validateForm();
  }
})();
</script>
HTML;
include __DIR__ . '/../includes/footer.php';
?>
