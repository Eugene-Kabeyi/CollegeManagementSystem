<?php
// Extract view data
$userName = $viewData['userName'] ?? 'User';
$userRole = $viewData['userRole'] ?? 'User';
$successMessage = $viewData['successMessage'] ?? '';
$errorMessage = $viewData['errorMessage'] ?? '';
$csrfToken = $viewData['csrfToken'] ?? '';
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Format role for display
$displayRole = ucfirst($userRole);
?>
<div class="row-fluid">
    <div class="col-lg-4 col-md-offset-4">
        <div id="Login">
            <div class="v-center">
                <div class="content">
                    <div class="table-cell1">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h3 class="panel-title">
                                    <i class="fas fa-key"></i> Change Password
                                </h3>
                            </div>
                            <div class="panel-body">
                                <h6>
                                    <b>Hello <?php echo htmlspecialchars($userName); ?>!</b> 
                                    <small class="text-muted">(<?php echo htmlspecialchars($displayRole); ?>)</small>
                                    <br>Change your password below.
                                </h6>

                                <?php if (!empty($successMessage)): ?>
                                    <div class="alert alert-success" role="alert">
                                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMessage); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($errorMessage)): ?>
                                    <div class="alert alert-danger" role="alert">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <?php echo htmlspecialchars($errorMessage); ?>
                                    </div>
                                <?php endif; ?>

                                <form action="change_password.php" method="post"
                                    enctype="application/x-www-form-urlencoded" id="changePasswordForm">
                                    <input type="hidden" name="csrf_token"
                                        value="<?php echo htmlspecialchars($csrfToken); ?>">

                                    <div class="form-group">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <input type="password" name="current_password" id="current_password"
                                            class="form-control" placeholder="Enter current password" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" name="new_password" id="new_password"
                                            class="form-control" placeholder="Enter new password" required>

                                        <!-- Password Requirements -->
                                        <div class="password-requirements mt-2">
                                            <small class="form-text text-muted">Password must meet the following
                                                requirements:</small>
                                            <ul class="list-unstyled mt-1" id="passwordRequirements">
                                                <li id="reqLength" class="text-danger">
                                                    <i class="fas fa-times-circle"></i> At least 8 characters
                                                </li>
                                                <li id="reqUppercase" class="text-danger">
                                                    <i class="fas fa-times-circle"></i> At least one uppercase letter
                                                </li>
                                                <li id="reqLowercase" class="text-danger">
                                                    <i class="fas fa-times-circle"></i> At least one lowercase letter
                                                </li>
                                                <li id="reqNumber" class="text-danger">
                                                    <i class="fas fa-times-circle"></i> At least one number
                                                </li>
                                                <li id="reqSpecial" class="text-danger">
                                                    <i class="fas fa-times-circle"></i> At least one special character
                                                </li>
                                            </ul>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" name="confirm_password" id="confirm_password"
                                            class="form-control" placeholder="Confirm new password" required>
                                        <div id="passwordMatch" class="mt-1">
                                            <small id="matchText" class="form-text text-muted">Passwords must
                                                match</small>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <button class="btn btn-info btn-block" type="submit" name="change_password"
                                            id="submitButton" disabled>
                                            <i class="fas fa-sync-alt"></i> Change Password
                                        </button>
                                    </div>

                                    <div class="form-group text-center">
                                        <button type="button" onclick="goBack()"
                                            class="btn btn-outline-secondary btn-sm">
                                            <i class="fas fa-arrow-left"></i> Back to Previous Page
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Keep the same CSS and JavaScript as before -->
<style>
    .password-requirements ul li {
        transition: all 0.3s ease;
        font-size: 0.85rem;
        margin-bottom: 2px;
    }
    .password-requirements ul li i {
        margin-right: 5px;
    }
    .text-danger { color: #dc3545 !important; }
    .text-success { color: #28a745 !important; }
    .text-warning { color: #ffc107 !important; }
    .password-strength-meter {
        height: 5px;
        border-radius: 3px;
        margin-top: 5px;
        transition: all 0.3s ease;
    }
    .strength-weak { background-color: #dc3545; width: 25%; }
    .strength-fair { background-color: #ffc107; width: 50%; }
    .strength-good { background-color: #17a2b8; width: 75%; }
    .strength-strong { background-color: #28a745; width: 100%; }
</style>

<script>
    // Keep the same JavaScript as before
    function goBack() {
        if (document.referrer && document.referrer.includes(window.location.hostname)) {
            window.history.back();
        } else {
            window.location.href = 'index.php';
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        // ... (keep all the same JavaScript code from previous version)
        const form = document.getElementById('changePasswordForm');
        const currentPassword = document.getElementById('current_password');
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        const submitButton = document.getElementById('submitButton');

        // Password requirement elements
        const reqLength = document.getElementById('reqLength');
        const reqUppercase = document.getElementById('reqUppercase');
        const reqLowercase = document.getElementById('reqLowercase');
        const reqNumber = document.getElementById('reqNumber');
        const reqSpecial = document.getElementById('reqSpecial');
        const matchText = document.getElementById('matchText');

        // Password validation functions
        function validatePassword(password) {
            const validations = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)
            };
            return validations;
        }

        function updateRequirementUI(requirement, isValid) {
            const icon = requirement.querySelector('i');
            if (isValid) {
                requirement.classList.remove('text-danger');
                requirement.classList.add('text-success');
                icon.classList.remove('fa-times-circle');
                icon.classList.add('fa-check-circle');
            } else {
                requirement.classList.remove('text-success');
                requirement.classList.add('text-danger');
                icon.classList.remove('fa-check-circle');
                icon.classList.add('fa-times-circle');
            }
        }

        function updatePasswordMatchUI(isMatch) {
            if (isMatch && newPassword.value.length > 0) {
                matchText.textContent = "Passwords match!";
                matchText.classList.remove('text-muted', 'text-danger');
                matchText.classList.add('text-success');
                confirmPassword.style.borderColor = '#28a745';
            } else if (confirmPassword.value.length > 0) {
                matchText.textContent = "Passwords do not match!";
                matchText.classList.remove('text-muted', 'text-success');
                matchText.classList.add('text-danger');
                confirmPassword.style.borderColor = '#dc3545';
            } else {
                matchText.textContent = "Passwords must match";
                matchText.classList.remove('text-danger', 'text-success');
                matchText.classList.add('text-muted');
                confirmPassword.style.borderColor = '';
            }
        }

        function updateSubmitButton() {
            const validations = validatePassword(newPassword.value);
            const allValid = Object.values(validations).every(v => v);
            const passwordsMatch = newPassword.value === confirmPassword.value;
            const currentPasswordFilled = currentPassword.value.length > 0;

            if (allValid && passwordsMatch && currentPasswordFilled) {
                submitButton.disabled = false;
                submitButton.classList.remove('btn-secondary');
                submitButton.classList.add('btn-info');
            } else {
                submitButton.disabled = true;
                submitButton.classList.remove('btn-info');
                submitButton.classList.add('btn-secondary');
            }
        }

        // Real-time validation for new password
        newPassword.addEventListener('input', function () {
            const password = newPassword.value;
            const validations = validatePassword(password);

            updateRequirementUI(reqLength, validations.length);
            updateRequirementUI(reqUppercase, validations.uppercase);
            updateRequirementUI(reqLowercase, validations.lowercase);
            updateRequirementUI(reqNumber, validations.number);
            updateRequirementUI(reqSpecial, validations.special);

            updatePasswordMatchUI(password === confirmPassword.value);
            updateSubmitButton();
            updatePasswordStrength(password);
        });

        // Real-time validation for confirm password
        confirmPassword.addEventListener('input', function () {
            updatePasswordMatchUI(newPassword.value === confirmPassword.value);
            updateSubmitButton();
        });

        // Real-time validation for current password
        currentPassword.addEventListener('input', function () {
            updateSubmitButton();
        });

        // Password strength indicator
        function updatePasswordStrength(password) {
            let strengthMeter = document.getElementById('passwordStrengthMeter');
            if (!strengthMeter) {
                strengthMeter = document.createElement('div');
                strengthMeter.id = 'passwordStrengthMeter';
                strengthMeter.className = 'password-strength-meter';
                newPassword.parentNode.appendChild(strengthMeter);
            }

            const validations = validatePassword(password);
            const validCount = Object.values(validations).filter(v => v).length;

            strengthMeter.className = 'password-strength-meter';

            if (password.length === 0) {
                strengthMeter.style.display = 'none';
            } else {
                strengthMeter.style.display = 'block';

                if (validCount <= 2) {
                    strengthMeter.classList.add('strength-weak');
                } else if (validCount === 3) {
                    strengthMeter.classList.add('strength-fair');
                } else if (validCount === 4) {
                    strengthMeter.classList.add('strength-good');
                } else {
                    strengthMeter.classList.add('strength-strong');
                }
            }
        }

        // Form submission validation
        form.addEventListener('submit', function (e) {
            const validations = validatePassword(newPassword.value);
            const allValid = Object.values(validations).every(v => v);
            const passwordsMatch = newPassword.value === confirmPassword.value;

            if (!allValid) {
                e.preventDefault();
                alert('Please ensure your new password meets all requirements.');
                newPassword.focus();
                return;
            }

            if (!passwordsMatch) {
                e.preventDefault();
                alert('New passwords do not match. Please make sure both fields are identical.');
                confirmPassword.focus();
                return;
            }

            if (newPassword.value.length < 8) {
                e.preventDefault();
                alert('New password must be at least 8 characters long.');
                newPassword.focus();
                return;
            }
        });

        // Initialize UI state
        updateSubmitButton();
    });
</script>