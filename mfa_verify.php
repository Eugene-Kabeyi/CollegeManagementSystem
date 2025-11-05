<?php
session_start();
include_once(__DIR__ . '/config.php');
include_once(__DIR__ . '/mfa_helper.php');

if (!isset($_SESSION['mfa_pending_user'])) {
    header('Location: index.php');
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify_code'])) {
        $code = trim($_POST['mfa_code'] ?? '');
        
        if (empty($code)) {
            $error = 'Please enter the verification code';
        } elseif (MFAHelper::validateMFACode($code)) {
            // MFA successful - complete login
            $_SESSION['UserAuthData'] = $_SESSION['mfa_pending_user'];
            unset($_SESSION['mfa_pending_user']);
            
            // Regenerate session ID for security
            session_regenerate_id(true);
            
            // Log successful login with MFA
            writeLoginLog($conn, $_SESSION['UserAuthData']['userid'], $_SESSION['UserAuthData']['role'], 'Success - MFA Verified');
            
            // Redirect to appropriate dashboard
            switch ($_SESSION['UserAuthData']['role']) {
                case 'student':
                    header('Location: student/student_dashboard.php');
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
        } else {
            $error = 'Invalid verification code. Please try again.';
        }
    }
    
    // Resend code functionality
    if (isset($_POST['resend_code'])) {
        $new_code = MFAHelper::generateMFACode();
        MFAHelper::storeMFACode($_SESSION['mfa_pending_user']['userid'], $new_code);
        MFAHelper::sendMFACode($email, $new_code);
        $error = 'New verification code sent to your email.';
    }
}
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
                                       title="Enter 6-digit code">
                                <div class="form-text text-center mt-2">
                                    <i class="fas fa-clock"></i> The code expires in 10 minutes
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" name="verify_code" class="btn btn-primary btn-lg">
                                    <i class="fas fa-check-circle"></i> Verify & Continue
                                </button>
                                
                                <button type="submit" name="resend_code" class="btn btn-outline-secondary btn-lg">
                                    <i class="fas fa-redo"></i> Resend Code
                                </button>
                            </div>
                        </form>
                        
                        <div class="text-center mt-4">
                            <a href="logout.php" class="text-muted text-decoration-none">
                                <i class="fas fa-times"></i> Cancel Login
                            </a>
                        </div>

                        <!-- Development Mode Display -->
                        <div class="alert alert-info mt-4 text-center">
                            <h6><i class="fas fa-code"></i> Development Mode</h6>
                            <p class="mb-1">Your verification code is:</p>
                            <div style="font-size: 28px; font-weight: bold; color: #007bff;">
                                <?php echo $_SESSION['mfa_code'] ?? 'Not generated'; ?>
                            </div>
                            <small class="text-muted">In production, this would be sent to your email</small>
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
                
                // Auto-submit when 6 digits are entered
                if (this.value.length === 6) {
                    form.submit();
                }
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