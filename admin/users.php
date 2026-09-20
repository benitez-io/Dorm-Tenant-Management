<?php
require_once __DIR__ . '/../config/app.php';
require_role('admin');

$db = get_db();

// ---- Handle actions -------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $first = str_input($_POST, 'first_name');
        $last  = str_input($_POST, 'last_name');
        $email = str_input($_POST, 'email');
        $password = str_input($_POST, 'password', '', false);
        $confirmPassword = str_input($_POST, 'confirm_password', '', false);
        $requestedRole = $_POST['role'] ?? '';
        $role = $requestedRole === 'admin' ? 'admin' : 'tenant';
        $contactNumber = str_input($_POST, 'contact_number');
        $emergencyName = str_input($_POST, 'emergency_contact_name');
        $emergencyPhone = str_input($_POST, 'emergency_contact_phone');

        if ($first === '' || $last === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($requestedRole, ['admin', 'maintenance_staff', 'tenant'], true) || password_policy_error($password) !== null || $password !== $confirmPassword || $contactNumber === '' || !preg_match('/^[0-9+() .-]{7,20}$/', $contactNumber) || ($emergencyPhone !== '' && !preg_match('/^[0-9+() .-]{7,20}$/', $emergencyPhone))) {
            flash('error', 'Please fill every field correctly (password needs at least 8 characters).');
        } else {
            $check = $db->prepare('SELECT user_id FROM users WHERE email = ?');
            $check->execute([$email]);
            if ($check->fetch()) {
                flash('error', 'That email is already registered.');
            } else {
                 $db->prepare('INSERT INTO users (first_name, last_name, email, password_hash, phone, role) VALUES (?, ?, ?, ?, ?, ?)')
                   ->execute([$first, $last, $email, password_hash($password, PASSWORD_DEFAULT), preg_replace('/\D+/', '', $contactNumber), $role]);
                 $newId = (int) $db->lastInsertId();
                 if ($role === 'tenant') {
                     $cleanPhone = preg_replace('/\D+/', '', $contactNumber);
                     $cleanEmergencyPhone = preg_replace('/\D+/', '', $emergencyPhone);
                     $db->prepare('INSERT INTO tenants (user_id, status, approval_status, contact_number, emergency_contact_name, emergency_contact_phone, emergency_contact, emergency_phone) VALUES (?, "Pending", "Pending", ?, ?, ?, ?, ?)')
                       ->execute([$newId, $cleanPhone ?: null, $emergencyName ?: null, $cleanEmergencyPhone ?: null, $emergencyName ?: null, $cleanEmergencyPhone ?: null]);
                     log_activity($db, 'tenant_registered', $first . ' ' . $last . ' was registered by an admin', (int) $db->lastInsertId());
                 }
                flash('success', 'Account created for ' . $first . ' ' . $last . '.');
            }
        }
    }

    redirect('/admin/users.php');
}

$pageTitle = 'Register Account';
include __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/module_tabs.php';
require_once __DIR__ . '/../includes/page_header.php';
render_page_header('bi-person-fill', 'User Management', 'Register accounts, manage login credentials, and assign roles.');
render_module_tabs([
  ['key' => 'register', 'label' => 'Register Account', 'href' => '/admin/users.php'],
  ['key' => 'credentials', 'label' => 'Login Credentials', 'href' => '/admin/credentials.php'],
  ['key' => 'manage', 'label' => 'Manage Tenants', 'href' => '/admin/manage-tenants.php'],
], 'register'); ?>

<div class="panel">
  <form method="post" id="addUserForm" class="needs-validation" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="registration-columns">
      <section class="registration-card">
        <div class="registration-card-heading"><span class="registration-icon"><i class="bi bi-person-plus"></i></span><h5>User Information</h5></div>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">First Name <span class="text-danger">*</span></label><input class="form-control" name="first_name" placeholder="Enter first name" required></div>
          <div class="col-md-6"><label class="form-label">Last Name <span class="text-danger">*</span></label><input class="form-control" name="last_name" placeholder="Enter last name" required></div>
        </div>
        <div class="mb-3 mt-3"><label class="form-label">Email Address <span class="text-danger">*</span></label><input type="email" class="form-control" name="email" placeholder="user@email.com" required></div>
        <div class="mb-3"><label class="form-label">Phone Number <span class="text-danger">*</span></label><input type="tel" class="form-control" name="contact_number" id="adminPhoneNumber" pattern="[0-9+() .-]{7,20}" placeholder="+63 XXX XXX XXXX" required></div>
        <div class="row g-2 mb-3">
          <div class="col-md-6"><label class="form-label">Password <span class="text-danger">*</span></label><div class="input-field-wrapper position-relative"><i class="bi bi-lock field-icon-left"></i><input type="password" id="userPassword" name="password" class="form-control custom-input px-5 js-password-strength" data-strength-for="userPassword" placeholder="Enter password" autocomplete="new-password" minlength="8" required><button type="button" class="btn-toggle-eye js-toggle-pwd" data-target="userPassword" aria-label="Toggle password visibility"><i class="bi bi-eye"></i></button></div></div>
          <div class="col-md-6"><label class="form-label">Confirm Password <span class="text-danger">*</span></label><div class="input-field-wrapper position-relative"><i class="bi bi-lock field-icon-left"></i><input type="password" id="userConfirmPassword" name="confirm_password" class="form-control custom-input px-5" placeholder="Re-enter password" autocomplete="new-password" minlength="8" required><button type="button" class="btn-toggle-eye js-toggle-pwd" data-target="userConfirmPassword" aria-label="Toggle password visibility"><i class="bi bi-eye"></i></button></div></div>
        </div>
        <div class="strength-meter-container mb-3" data-strength-for="userPassword"><div class="password-strength-meter d-flex gap-1"><div class="strength-segment flex-fill rounded-pill"></div><div class="strength-segment flex-fill rounded-pill"></div><div class="strength-segment flex-fill rounded-pill"></div><div class="strength-segment flex-fill rounded-pill"></div><div class="strength-segment flex-fill rounded-pill"></div></div><div class="d-flex justify-content-between mt-1"><span class="strength-label small fw-bold text-danger">Very Weak</span><span class="strength-count small text-muted">0/5 requirements met</span></div></div>
        <div class="mb-3"><label class="form-label">User Category</label><select name="category" class="form-select"><option value="" disabled selected>Select category...</option><option value="student">Student / Resident</option><option value="staff">Administrative Staff</option></select></div>
        <div class="mb-3"><label class="form-label">Emergency Contact Name</label><input class="form-control" name="emergency_contact_name" placeholder="Full name"></div>
        <div class="mb-3"><label class="form-label">Emergency Contact Phone</label><input type="tel" class="form-control" name="emergency_contact_phone" pattern="[0-9+() .-]{7,20}" placeholder="+63 XXX XXX XXXX"></div>
        <button class="btn btn-maroon w-100 rounded-pill fw-bold">Save User</button>
      </section>
      <section class="registration-card role-selection-card">
        <div class="registration-card-heading"><span class="registration-icon"><i class="bi bi-shield-check"></i></span><h5>Identify Role</h5></div>
        <p class="text-muted small mb-4">Select the appropriate role for this user account.</p>
        <div class="role-selection-group">
          <input type="radio" name="role" id="admin_role_admin" value="admin" class="visually-hidden" required><label for="admin_role_admin" class="role-option-card"><span class="custom-radio-dot"></span><span class="role-icon-box"><i class="bi bi-shield-lock"></i></span><span><strong>Administrator</strong><small>Full access to system management features.</small></span></label>
          <input type="radio" name="role" id="admin_role_staff" value="maintenance_staff" class="visually-hidden"><label for="admin_role_staff" class="role-option-card"><span class="custom-radio-dot"></span><span class="role-icon-box"><i class="bi bi-wrench"></i></span><span><strong>Maintenance Staff</strong><small>Access for maintenance tasks and requests.</small></span></label>
          <input type="radio" name="role" id="admin_role_tenant" value="tenant" class="visually-hidden" checked><label for="admin_role_tenant" class="role-option-card"><span class="custom-radio-dot"></span><span class="role-icon-box"><i class="bi bi-person"></i></span><span><strong>Tenant</strong><small>Portal access for rooms, payments, and requests.</small></span></label>
        </div>
      </section>
    </div>
  </form>
</div>

<?php
include __DIR__ . '/../includes/footer.php';
?>
