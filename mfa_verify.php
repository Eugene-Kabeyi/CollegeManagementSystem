<?php
session_start();
include_once(__DIR__ . '/config.php');
include_once(__DIR__ . '/mfa_helper.php');

// EXTENSIVE DEBUGGING
error_log("=== MFA_VERIFY START ===");
error_log("Session ID: " . session_id());
error_log("All session data: " . print_r($_SESSION, true));

// Check if user has pending MFA verification
if (!isset($_SESSION['mfa_pending_user'])) {
    error_log("MFA FAIL: No pending user - redirecting to index");
    header('Location: index.php');
    exit();
}

// Check if account is locked before proceeding
$lockout_status = MFAHelper::getLockoutStatus(
    $conn, 
    $_SESSION['mfa_pending_user']['userid'], 
    $_SESSION['mfa_pending_user']['email'], 
    $_SESSION['mfa_pending_user']['role']
);

if ($lockout_status['locked']) {
    error_log("MFA BLOCKED: Account is locked - redirecting to index");
    header('Location: index.php?login-error=locked&minutes=' . $lockout_status['minutes_remaining']);
    exit();
}

// Check if MFA code exists
if (!isset($_SESSION['mfa_code'])) {
    error_log("MFA FAIL: No MFA code in session");
    header('Location: index.php?login-error=mfa_expired');
    exit();
}

if (!isset($_SESSION['mfa_expires'])) {
    error_log("MFA FAIL: No expiration time in session");
    header('Location: index.php?login-error=mfa_expired');
    exit();
}

$error = '';
$email = $_SESSION['mfa_pending_user']['email'];

// Function to get client IP address
function getClientIP() {
    $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// Function to get browser information
function getBrowserInfo() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $browser = "Unknown";
    $os = "Unknown";

    // Browser detection
    $browser_patterns = [
        '/MSIE|Trident/i' => 'Internet Explorer',
        '/Firefox/i' => 'Mozilla Firefox',
        '/Chrome/i' => 'Google Chrome',
        '/Safari/i' => 'Apple Safari',
        '/Opera|OPR/i' => 'Opera',
        '/Edge/i' => 'Microsoft Edge',
        '/Netscape/i' => 'Netscape'
    ];

    foreach ($browser_patterns as $pattern => $name) {
        if (preg_match($pattern, $user_agent)) {
            $browser = $name;
            break;
        }
    }

    // OS detection
    $os_patterns = [
        '/Windows NT 10/i' => 'Windows 10',
        '/Windows NT 6.3/i' => 'Windows 8.1',
        '/Windows NT 6.2/i' => 'Windows 8',
        '/Windows NT 6.1/i' => 'Windows 7',
        '/Windows NT 6.0/i' => 'Windows Vista',
        '/Windows NT 5.2/i' => 'Windows Server 2003',
        '/Windows NT 5.1/i' => 'Windows XP',
        '/Windows NT 5.0/i' => 'Windows 2000',
        '/Windows|Win32/i' => 'Windows',
        '/Macintosh|Mac OS X/i' => 'Mac OS',
        '/Linux/i' => 'Linux',
        '/Android/i' => 'Android',
        '/iOS|iPhone|iPad/i' => 'iOS',
        '/Unix/i' => 'Unix'
    ];

    foreach ($os_patterns as $pattern => $name) {
        if (preg_match($pattern, $user_agent)) {
            $os = $name;
            break;
        }
    }

    return [
        'browser' => $browser,
        'os' => $os
    ];
}

// Function to write login log
function writeLoginLog($conn, $userid, $userrole, $status) {
    try {
        $ip = getClientIP();
        $browserInfo = getBrowserInfo();
        
        $stmt = $conn->prepare("INSERT INTO `log_login` 
            (ip, os, browser, userid, userrole, timestamp) 
            VALUES (:ip, :os, :browser, :userid, :userrole, NOW())");
        
        $stmt->execute([
            ':ip' => $ip,
            ':os' => $browserInfo['os'],
            ':browser' => $browserInfo['browser'],
            ':userid' => $userid,
            ':userrole' => $userrole
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Login log error: " . $e->getMessage());
        return false;
    }
}

// Function to redirect to appropriate dashboard
function redirectToDashboard($role) {
    error_log("SUCCESS: Redirecting to dashboard for role: " . $role);
    
    switch ($role) {
        case 'student':
            header('Location: student_dashboard.php');
            break;
        case 'staff':
        case 'hod':
            header('Location: staff/staff_dashboard.php');
            break;
        case 'principal':
        case 'manager':
            header('Location: college_admin_dashboard.php');
            break;
        case 'admin':
            header('Location: dashboard.php');
            break;
        default:
            header('Location: dashboard.php');
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify_code'])) {
        $code = trim($_POST['mfa_code'] ?? '');
        
        error_log("=== MFA VERIFICATION ATTEMPT ===");
        error_log("Entered code: '" . $code . "'");
        error_log("Session code: '" . ($_SESSION['mfa_code'] ?? 'NOT SET') . "'");
        error_log("Session expires: " . ($_SESSION['mfa_expires'] ?? 'NOT SET'));
        error_log("Current time: " . time());
        error_log("Time remaining: " . (($_SESSION['mfa_expires'] ?? 0) - time()) . " seconds");
        error_log("Attempts: " . ($_SESSION['mfa_attempts'] ?? '0'));
        
        // Check if account is locked before processing
        $lockout_status = MFAHelper::getLockoutStatus(
            $conn, 
            $_SESSION['mfa_pending_user']['userid'], 
            $_SESSION['mfa_pending_user']['email'], 
            $_SESSION['mfa_pending_user']['role']
        );
        
        if ($lockout_status['locked']) {
            $error = 'Your account has been locked due to too many failed attempts. Please try again in ' . $lockout_status['minutes_remaining'] . ' minutes.';
            error_log("MFA BLOCKED: Account is locked");
        } elseif (empty($code)) {
            $error = 'Please enter the verification code';
            error_log("MFA FAIL: Empty code");
        } else {
            // SIMPLIFIED VALIDATION - Remove all complex checks
            $session_code = $_SESSION['mfa_code'] ?? '';
            
            error_log("Simple validation:");
            error_log("Code match: " . ($code === $session_code ? 'YES' : 'NO'));
            
            // SIMPLE VALIDATION - Only check if codes match
            if ($code === $session_code) {
                error_log("SIMPLE VALIDATION SUCCESS - Codes match!");
                
                // Unlock account if it was previously locked (successful login)
                MFAHelper::unlockAccount(
                    $conn, 
                    $_SESSION['mfa_pending_user']['userid'], 
                    $_SESSION['mfa_pending_user']['email'], 
                    $_SESSION['mfa_pending_user']['role']
                );
                
                // MFA successful - complete login
                $_SESSION['UserAuthData'] = $_SESSION['mfa_pending_user'];
                
                // Clear all MFA session data
                unset($_SESSION['mfa_pending_user']);
                unset($_SESSION['mfa_code']);
                unset($_SESSION['mfa_expires']);
                unset($_SESSION['mfa_attempts']);
                unset($_SESSION['mfa_user_id']);
                
                // Regenerate session ID for security
                session_regenerate_id(true);
                
                // Log successful login with MFA
                writeLoginLog($conn, $_SESSION['UserAuthData']['userid'], $_SESSION['UserAuthData']['role'], 'Success - MFA Verified');
                
                error_log("Login completed for user: " . $_SESSION['UserAuthData']['email']);
                error_log("Session after cleanup: " . print_r($_SESSION, true));
                
                // Redirect to appropriate dashboard
                redirectToDashboard($_SESSION['UserAuthData']['role']);
                
            } else {
                // Increment attempts
                $current_attempts = $_SESSION['mfa_attempts'] ?? 0;
                $_SESSION['mfa_attempts'] = $current_attempts + 1;
                
                $error = 'Invalid verification code. Please try again.';
                error_log("SIMPLE VALIDATION FAILED - Codes don't match");
                error_log("Entered: '$code' vs Session: '$session_code'");
                error_log("Attempt: " . $_SESSION['mfa_attempts'] . "/3");
                
                // Check if max attempts reached
                if ($_SESSION['mfa_attempts'] >= 3) {
                    error_log("MFA FAIL: Max attempts reached - Locking account");
                    
                    // Record account lockout
                    MFAHelper::recordLockout(
                        $conn, 
                        $_SESSION['mfa_pending_user']['userid'], 
                        $_SESSION['mfa_pending_user']['email'], 
                        $_SESSION['mfa_pending_user']['role'],
                        'MFA Failed - 3 attempts'
                    );
                    
                    $error = 'Maximum verification attempts reached. Your account has been locked for 30 minutes.';
                    session_destroy();
                    header('Location: index.php?login-error=locked&minutes=30');
                    exit();
                }
            }
        }
    }
    
    // Resend code functionality
    if (isset($_POST['resend_code'])) {
        // Check if account is locked before resending
        $lockout_status = MFAHelper::getLockoutStatus(
            $conn, 
            $_SESSION['mfa_pending_user']['userid'], 
            $_SESSION['mfa_pending_user']['email'], 
            $_SESSION['mfa_pending_user']['role']
        );
        
        if ($lockout_status['locked']) {
            $error = 'Your account is locked. Cannot resend code.';
        } else {
            error_log("Resending MFA code to: " . $email);
            $new_code = MFAHelper::generateMFACode();
            MFAHelper::storeMFACode($_SESSION['mfa_pending_user']['userid'], $new_code);
            MFAHelper::sendMFACode($email, $new_code);
            $error = 'New verification code sent to your email.';
            error_log("New code generated: " . $new_code);
        }
    }
}

// Calculate remaining time
$remaining_time = $_SESSION['mfa_expires'] - time();
$minutes_remaining = max(0, floor($remaining_time / 60));
$seconds_remaining = max(0, $remaining_time % 60);

// Check lockout status for display
$lockout_status = MFAHelper::getLockoutStatus(
    $conn, 
    $_SESSION['mfa_pending_user']['userid'], 
    $_SESSION['mfa_pending_user']['email'], 
    $_SESSION['mfa_pending_user']['role']
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Login - CEA System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .card-header {
            border-radius: 15px 15px 0 0 !important;
            background: linear-gradient(135deg, #007bff, #0056b3);
        }
        .code-input {
            font-size: 24px;
            letter-spacing: 8px;
            font-weight: bold;
            text-align: center;
            border: 2px solid #007bff;
            border-radius: 10px;
            padding: 15px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
        }
        .btn-outline-secondary {
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
        }
        .btn-danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
        }
        .timer {
            font-size: 14px;
            font-weight: bold;
            color: #dc3545;
        }
        .attempts-warning {
            font-size: 14px;
            color: #ffc107;
            font-weight: bold;
        }
        .lockout-warning {
            font-size: 14px;
            color: #dc3545;
            font-weight: bold;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 15px;
        }
        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
            margin-top: 10px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card">
                    <div class="card-header text-center text-white py-4">
                        <h4><i class="fas fa-shield-alt fa-2x mb-3"></i></h4>
                        <h3 class="mb-0">Verify Your Identity</h3>
                    </div>
                    <div class="card-body p-5">
                        <p class="text-center text-muted mb-4">We sent a verification code to:</p>
                        <p class="text-center fw-bold text-primary fs-5 mb-4"><?php echo htmlspecialchars($email); ?></p>
                        
                        <!-- Lockout Warning -->
                        <?php if ($lockout_status['locked']): ?>
                            <div class="lockout-warning text-center">
                                <i class="fas fa-ban"></i>
                                <strong>Account Locked</strong><br>
                                Your account has been locked due to too many failed attempts.<br>
                                Please try again in <?php echo $lockout_status['minutes_remaining']; ?> minutes.
                            </div>
                        <?php endif; ?>
                        
                        <!-- Time Remaining -->
                        <div class="text-center mb-3">
                            <span class="timer">
                                <i class="fas fa-clock"></i> 
                                Time remaining: <?php echo $minutes_remaining; ?>:<?php echo sprintf('%02d', $seconds_remaining); ?>
                            </span>
                        </div>
                        
                        <!-- Attempts Warning -->
                        <?php if (($_SESSION['mfa_attempts'] ?? 0) > 0): ?>
                            <div class="text-center attempts-warning mb-3">
                                <i class="fas fa-exclamation-triangle"></i>
                                Attempts: <?php echo $_SESSION['mfa_attempts'] ?? 0; ?>/3
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" id="mfaForm">
                            <div class="mb-4">
                                <label for="mfa_code" class="form-label fw-semibold">Enter 6-digit verification code:</label>
                                <input type="text" 
                                       id="mfa_code" 
                                       name="mfa_code" 
                                       class="form-control code-input" 
                                       maxlength="6" 
                                       required 
                                       autofocus
                                       pattern="[0-9]{6}"
                                       placeholder="000000"
                                       title="Enter 6-digit code"
                                       <?php echo $lockout_status['locked'] ? 'disabled' : ''; ?>>
                                <div class="form-text text-center mt-2">
                                    <i class="fas fa-key"></i> Enter the 6-digit code from your email
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" name="verify_code" class="btn btn-primary btn-lg" <?php echo $lockout_status['locked'] ? 'disabled' : ''; ?>>
                                    <i class="fas fa-check-circle"></i> Verify & Continue
                                </button>
                                
                                <button type="submit" name="resend_code" class="btn btn-outline-secondary btn-lg" <?php echo $lockout_status['locked'] ? 'disabled' : ''; ?>>
                                    <i class="fas fa-redo"></i> Resend Code
                                </button>
                                
                                <?php if ($lockout_status['locked']): ?>
                                    <button type="button" class="btn btn-danger btn-lg" disabled>
                                        <i class="fas fa-ban"></i> Account Locked
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                        
                        <div class="text-center mt-4">
                            <a href="logout.php" class="text-muted text-decoration-none">
                                <i class="fas fa-times"></i> Cancel Login
                            </a>
                        </div>

                        <!-- Debug Information -->
                        <div class="debug-info">
                            <h6><i class="fas fa-bug"></i> Debug Information</h6>
                            <p><strong>Session Code:</strong> <?php echo $_SESSION['mfa_code'] ?? 'Not set'; ?></p>
                            <p><strong>Expires:</strong> <?php echo $_SESSION['mfa_expires'] ?? 'Not set'; ?> (Current: <?php echo time(); ?>)</p>
                            <p><strong>Time Remaining:</strong> <?php echo $remaining_time; ?> seconds</p>
                            <p><strong>Attempts:</strong> <?php echo $_SESSION['mfa_attempts'] ?? 0; ?>/3</p>
                            <p><strong>User ID:</strong> <?php echo $_SESSION['mfa_pending_user']['userid'] ?? 'Not set'; ?></p>
                            <p><strong>Lockout Status:</strong> <?php echo $lockout_status['locked'] ? 'LOCKED (' . $lockout_status['minutes_remaining'] . ' minutes remaining)' : 'Not Locked'; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mfaInput = document.getElementById('mfa_code');
            const form = document.getElementById('mfaForm');
            
            // Auto-format code input (numbers only)
            mfaInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9]/g, '');
            });
            
            // Prevent form submission if less than 6 digits
            form.addEventListener('submit', function(e) {
                if (mfaInput.value.length !== 6) {
                    e.preventDefault();
                    alert('Please enter a complete 6-digit code.');
                    mfaInput.focus();
                }
            });
            
            // Focus on input field
            mfaInput.focus();
        });
    </script>
</body>
</html>