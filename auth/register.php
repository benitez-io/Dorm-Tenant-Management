<?php
require_once __DIR__ . '/../config/app.php';

if (is_logged_in()) {
    redirect(current_role() === 'admin' ? '/admin/dashboard.php' : '/tenant/dashboard.php');
}

$errors = [];
$old = ['first_name' => '', 'last_name' => '', 'email' => '', 'contact_number' => '', 'age' => '', 'tenant_type' => 'Student', 'emergency_contact_name' => '', 'emergency_contact_phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $old['first_name'] = str_input($_POST, 'first_name');
    $old['last_name']  = str_input($_POST, 'last_name');
    $old['email']      = str_input($_POST, 'email');
    $old['contact_number'] = str_input($_POST, 'contact_number');
    $old['age']        = str_input($_POST, 'age');
    $old['tenant_type'] = in_array($_POST['tenant_type'] ?? '', ['Student', 'Employee'], true) ? $_POST['tenant_type'] : 'Student';
    $password          = str_input($_POST, 'password', '', false);
    $confirm           = $_POST['confirm_password'] ?? '';
    $old['emergency_contact_name'] = str_input($_POST, 'emergency_contact_name');
    $old['emergency_contact_phone'] = str_input($_POST, 'emergency_contact_phone');
    $cleanContactNumber = preg_replace('/\D+/', '', $old['contact_number']);
    $cleanEmergencyPhone = preg_replace('/\D+/', '', $old['emergency_contact_phone']);

    if ($old['first_name'] === '' || $old['last_name'] === '') {
        $errors[] = 'First and last name are required.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (($passwordError = password_policy_error($password)) !== null) {
      $errors[] = $passwordError;
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }
    if ($old['contact_number'] === '' || !preg_match('/^[0-9+() .-]{7,20}$/', $old['contact_number']) || strlen($cleanContactNumber) < 7) {
        $errors[] = 'Please enter a valid phone number.';
    }
    foreach (['emergency_contact_phone' => [$old['emergency_contact_phone'], $cleanEmergencyPhone]] as $label => [$phone, $digits]) {
      if ($phone !== '' && (!preg_match('/^[0-9+() .-]{7,20}$/', $phone) || strlen($digits) < 7)) {
        $errors[] = ucfirst(str_replace('_', ' ', $label)) . ' must be a valid phone number.';
      }
    }

    if (empty($errors)) {
        $db = get_db();
        $check = $db->prepare('SELECT user_id FROM users WHERE email = ?');
        $check->execute([$old['email']]);

        if ($check->fetch()) {
            $errors[] = 'An account with that email already exists. Try logging in instead.';
        } else {
            $db->beginTransaction();
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare(
                    'INSERT INTO users (first_name, last_name, email, password_hash, age, phone, role)
                     VALUES (?, ?, ?, ?, ?, ?, "tenant")'
                );
                $stmt->execute([
                    $old['first_name'],
                    $old['last_name'],
                    $old['email'],
                    $hash,
                    $old['age'] !== '' ? (int) $old['age'] : null,
                    $cleanContactNumber ?: null,
                ]);
                $userId = (int) $db->lastInsertId();

                $stmt2 = $db->prepare(
                    'INSERT INTO tenants (user_id, status, approval_status, tenant_type, contact_number, emergency_contact_name, emergency_contact_phone, emergency_contact, emergency_phone)
                     VALUES (?, "Pending", "Pending", ?, ?, ?, ?, ?, ?)'
                );
                 $stmt2->execute([$userId, $old['tenant_type'], $cleanContactNumber ?: null, $old['emergency_contact_name'] ?: null, $cleanEmergencyPhone ?: null, $old['emergency_contact_name'] ?: null, $cleanEmergencyPhone ?: null]);
                $tenantId = (int) $db->lastInsertId();

                log_activity($db, 'tenant_registered', $old['first_name'] . ' ' . $old['last_name'] . ' registered as a new tenant', $tenantId);

                $db->commit();
                flash('success', 'Account created! An admin needs to review and approve your application before your dashboard unlocks.');
                redirect('/auth/login.php');
            } catch (Throwable $e) {
              if ($db->inTransaction()) $db->rollBack();
              error_log('Tenant registration failed [' . get_class($e) . ']: ' . $e->getMessage());
                $errors[] = 'Something went wrong while creating your account. Please try again.';
            }
        }
    }
}

$pageTitle = 'Register';
include __DIR__ . '/../includes/header.php';
?>
<div class="auth-screen auth-page register-page">
  <canvas id="auth-bg-canvas" aria-hidden="true"></canvas>
  <div class="auth-panel">
    <div class="auth-panel-left auth-sidebar text-white p-4 d-flex flex-column justify-content-between position-relative overflow-hidden">
      <div class="sidebar-circle-accent"></div>
      <div class="position-relative z-1">
        <div class="sidebar-icon-wrapper mb-4 d-flex align-items-center justify-content-center rounded-4"><i class="bi bi-mortarboard-fill fs-3 text-white"></i></div>
        <h2 class="fw-bold fs-3 text-white mb-2">Dorm Tenant<br>Management System</h2>
        <p class="sidebar-description text-white-50 small mb-4">Create your account to access the Dorm Tenant Rental Management System.</p>
        <div class="d-flex flex-column gap-3 mt-4">
          <div class="sidebar-info-card p-3 rounded-4 d-flex align-items-center gap-3"><div class="sidebar-info-icon rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"><i class="bi bi-person fs-5"></i></div><div><h6 class="fw-bold mb-0 text-white fs-6">Personal Information</h6><span class="text-white-50 xs-text">Full name &amp; contact details</span></div></div>
          <div class="sidebar-info-card p-3 rounded-4 d-flex align-items-center gap-3"><div class="sidebar-info-icon rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"><i class="bi bi-at fs-5"></i></div><div><h6 class="fw-bold mb-0 text-white fs-6">Account Credentials</h6><span class="text-white-50 xs-text">Username &amp; secure password</span></div></div>
          <div class="sidebar-info-card p-3 rounded-4 d-flex align-items-center gap-3"><div class="sidebar-info-icon rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"><i class="bi bi-person-gear fs-5"></i></div><div><h6 class="fw-bold mb-0 text-white fs-6">Role Selection</h6><span class="text-white-50 xs-text">Admin or Tenant access</span></div></div>
        </div>
      </div>
      <div class="sidebar-footer compact-sidebar-footer mt-auto position-relative z-1">
        <hr class="sidebar-divider mb-3 opacity-25 border-white">
        <div class="login-redirect-card compact-login-card p-3 rounded-4 mb-3">
          <p class="small text-white-50 mb-2">Already have an account?</p>
          <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-light w-100 fw-bold text-maroon rounded-pill d-flex align-items-center justify-content-center gap-2 py-2 shadow-sm"><i class="bi bi-chevron-left"></i> Sign In</a>
        </div>
      </div>
    </div>
    <div class="auth-panel-right auth-content">
      <span class="auth-form-badge"><i class="bi bi-key-fill"></i> New Account Registration</span>
      <h2>Create Account</h2>
      <p class="text-muted">Sign up as a tenant. An admin will approve your application before you can log in.</p>

      <?php if ($errors): ?>
        <div class="alert alert-danger">
          <ul class="mb-0">
            <?php foreach ($errors as $err): ?><li><?= clean($err) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" id="registerForm" class="needs-validation" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="role" value="tenant">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">First Name <span class="text-danger">*</span></label>
            <input type="text" name="first_name" class="form-control" value="<?= clean($old['first_name']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Last Name <span class="text-danger">*</span></label>
            <input type="text" name="last_name" class="form-control" value="<?= clean($old['last_name']) ?>" required>
          </div>
        </div>
        <div class="mb-3 mt-3">
          <label class="form-label">Registered Email Address <span class="text-danger">*</span></label>
          <input type="email" name="email" class="form-control" value="<?= clean($old['email']) ?>" required>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Phone Number <span class="text-danger">*</span></label>
            <input type="tel" name="contact_number" id="phoneNumber" class="form-control" placeholder="+63 9XX XXX XXXX" pattern="[0-9+() .-]{7,20}" value="<?= clean($old['contact_number']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Age <span class="text-muted">(optional)</span></label>
            <input type="number" min="16" max="100" name="age" class="form-control" value="<?= clean($old['age']) ?>">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Tenant Type</label>
          <select name="tenant_type" class="form-select">
            <option value="Student" <?= $old['tenant_type'] === 'Student' ? 'selected' : '' ?>>Student</option>
            <option value="Employee" <?= $old['tenant_type'] === 'Employee' ? 'selected' : '' ?>>Employee</option>
          </select>
        </div>
        <div class="row g-3 mt-0">
          <div class="col-md-6">
            <label class="form-label">Password <span class="text-danger">*</span></label>
            <input type="password" name="password" id="regPassword" class="form-control js-password-strength" data-strength-for="regPassword" value="" autocomplete="new-password" minlength="8" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
            <input type="password" name="confirm_password" id="regConfirmPassword" class="form-control" value="" autocomplete="new-password" minlength="8" required>
          </div>
        </div>
        <div class="strength-meter-container mt-2 mb-3" data-strength-for="regPassword">
          <div class="password-strength-meter d-flex gap-1"><div class="strength-segment flex-fill rounded-pill"></div><div class="strength-segment flex-fill rounded-pill"></div><div class="strength-segment flex-fill rounded-pill"></div><div class="strength-segment flex-fill rounded-pill"></div><div class="strength-segment flex-fill rounded-pill"></div></div>
          <div class="d-flex justify-content-between align-items-center mt-1"><span class="strength-label small fw-bold text-danger">Very Weak</span><span class="strength-count small text-muted">0/5 requirements met</span></div>
        </div>
        <hr class="my-3">
        <p class="text-muted small mb-2">Emergency contact (optional, but recommended)</p>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Contact Name</label>
            <input type="text" name="emergency_contact_name" class="form-control" value="<?= clean($old['emergency_contact_name']) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Contact Phone</label>
            <input type="tel" name="emergency_contact_phone" class="form-control" pattern="[0-9+() .-]{7,20}" value="<?= clean($old['emergency_contact_phone']) ?>">
          </div>
        </div>
        <div class="policy-notice-box p-3 rounded-4 border d-flex align-items-start gap-2 my-3"><i class="bi bi-info-circle text-muted fs-5 flex-shrink-0 mt-1"></i><p class="text-secondary xs-text mb-0">By creating an account, you agree to the dorm management policies and confirm that the information provided is accurate.</p></div>
        <button type="submit" class="btn btn-maroon w-100 py-3 rounded-pill fw-bold text-white d-flex align-items-center justify-content-center gap-2 shadow-sm"><i class="bi bi-person-check fs-5"></i><span>Create Account</span><i class="bi bi-arrow-right fs-6"></i></button>
      </form>
    </div>
  </div>
  <footer class="auth-global-footer text-center"><p class="xxs-text text-white-50 mb-0">&copy; 2026 Teen T-ITans &middot; Dorm Tenant Management System</p></footer>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
