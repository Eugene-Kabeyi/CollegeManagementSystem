<?php
session_start();
include_once('header.php');

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle error messages from login-form.php via GET
$errorMessage = '';

if (isset($_GET['login-error'])) {
    switch ($_GET['login-error']) {
        case 'user':
            $errorMessage = "User not found.";
            break;
        case 'password':
            $errorMessage = "Incorrect password.";
            break;
        case 'role':
            $errorMessage = "Please select a valid role.";
            break;
        case 'database':
            $errorMessage = "Database error. Please try again later.";
            break;
        case 'csrf':
            $errorMessage = "Security token invalid. Please try again.";
            break;
        case 'locked':
            $minutes = $_GET['minutes'] ?? 30;
            $errorMessage = "Your account has been locked due to too many failed attempts. Please try again in {$minutes} minutes.";
            break;
        case 'mfa_attempts':
            $errorMessage = "Maximum verification attempts reached. Your account has been locked.";
            break;
        default:
            $errorMessage = "Login failed. Please try again.";
            break;
    }
}
?>

<div class="row-fluid">
  <div class="col-lg-4 col-md-offset-4">
    <div id="Login">
      <div class="v-center">
        <div class="content">
          <div class="table-cell1">
            <div class="panel panel-default">
              <div class="panel-heading">
                <h3 class="panel-title">Sign in</h3>
              </div>
              <div class="panel-body">
                <h6><b>Welcome.</b> Sign in to get started!</h6>

                <?php if (!empty($errorMessage)): ?>
                  <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($errorMessage); ?>
                  </div>
                <?php endif; ?>

                <form action="login-form.php" method="post" enctype="application/x-www-form-urlencoded">
                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                  <div class="form-group">
                    <input type="email" name="login-email" class="form-control" placeholder="Enter Your E-Mail address" required>
                  </div>
                  <div class="form-group">
                    <input type="password" name="login-password" class="form-control" placeholder="Enter your password" required>
                  </div>
                  <div class="form-group">
                    <select class="form-control" name="login-role" required>
                      <option value="">Login as</option>
                      <option value="staff">Principal</option>
                      <option value="staff">Head of the Department</option>
                      <option value="staff">Staff</option>
                      <option value="student">Students</option>
                      <option value="admin">Admin</option>
                    </select>
                  </div>

                  <div class="form-group">
                    <button class="btn btn-info btn-block" type="submit" name="login-submit">Sign in</button>
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

<?php
include_once('footer.php');
?>