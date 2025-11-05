<?php
session_start();
include_once(__DIR__ . '/config.php'); // config.php must define $conn as PDO
include_once(__DIR__ . '/mfa_helper.php'); // Include MFA helper

error_log("=== LOGIN FORM DEBUG ===");
error_log("Session ID: " . session_id());
error_log("Session CSRF Token: " . ($_SESSION['csrf_token'] ?? 'NOT SET'));
error_log("Posted CSRF Token: " . ($_POST['csrf_token'] ?? 'NOT POSTED'));

// CSRF Token Validation
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
    error_log("CSRF FAIL: Missing tokens");
    unset($_SESSION['csrf_token']);
    header('Location: index.php?login-error=csrf');
    exit;
}

if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    error_log("CSRF FAIL: Token mismatch");
    error_log("Session: " . $_SESSION['csrf_token']);
    error_log("Posted: " . $_POST['csrf_token']);
    unset($_SESSION['csrf_token']);
    header('Location: index.php?login-error=csrf');
    exit;
}

// CSRF validation successful - regenerate token
error_log("CSRF SUCCESS: Tokens match");
$old_token = $_SESSION['csrf_token'];
unset($_SESSION['csrf_token']);
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
error_log("CSRF Token regenerated: " . $old_token . " -> " . $_SESSION['csrf_token']);

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

// Function to validate email format
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Function to sanitize input
function sanitizeInput($data) {
    return trim(htmlspecialchars(strip_tags($data)));
}

// Main login processing
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['login-submit'])) {
    header('Location: index.php');
    exit;
}


// Sanitize inputs
$LoginEmail = sanitizeInput($_POST['login-email']);
$LoginPassword = trim($_POST['login-password']);
$LoginRole = sanitizeInput($_POST['login-role']);

// Validate inputs
if (empty($LoginEmail) || empty($LoginPassword) || empty($LoginRole)) {
    header('Location: index.php?login-error=empty');
    exit;
}

if (!isValidEmail($LoginEmail)) {
    header('Location: index.php?login-error=email');
    exit;
}

$allowedRoles = [
    'admin' => 'login_admin',
    'staff' => 'login_staff', 
    'student' => 'login_student',
    'principal' => 'login_college_admin',
    'manager' => 'login_college_admin'
];

if (!isset($allowedRoles[$LoginRole])) {
    writeLoginLog($conn, $LoginEmail, 'invalid_role', 'Failed - Invalid Role');
    header('Location: index.php?login-error=role');
    exit;
}

$LoginTable = $allowedRoles[$LoginRole];
$hashedPassword = md5($LoginPassword); // Using MD5 to match your existing system

try {
    // Prepare query based on role
    if ($LoginRole === 'principal' || $LoginRole === 'manager') {
        $query = "SELECT * FROM `$LoginTable` WHERE email = ? AND role = ?";
        $params = [$LoginEmail, $LoginRole];
    } else {
        $query = "SELECT * FROM `$LoginTable` WHERE email = ?";
        $params = [$LoginEmail];
    }
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        writeLoginLog($conn, $LoginEmail, $LoginRole, 'Failed - User Not Found');
        header('Location: index.php?login-error=user');
        exit;
    }

    // Verify password - using MD5 to match your existing system
    if ($user['password'] !== $hashedPassword) {
        $userid = $user['userid'] ?? $user['email'];
        writeLoginLog($conn, $userid, $LoginRole, 'Failed - Wrong Password');
        header('Location: index.php?login-error=password');
        exit;
    }

    // Successful password verification - now initiate MFA
    $userid = $user['userid'] ?? $user['email'];
    $actualRole = $user['role'] ?? $LoginRole;
    
    // Generate and store MFA code
    $mfa_code = MFAHelper::generateMFACode();
    MFAHelper::storeMFACode($userid, $mfa_code);

    // Store user data temporarily for MFA verification
    $_SESSION['mfa_pending_user'] = [
        'userid' => $userid,
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $actualRole,
        'login_time' => time()
    ];

    // Add role-specific data to pending session
    switch ($actualRole) {
        case 'staff':
        case 'hod':
            $_SESSION['mfa_pending_user']['type'] = $user['type'] ?? '';
            $_SESSION['mfa_pending_user']['department'] = $user['department'] ?? '';
            $_SESSION['mfa_pending_user']['designation'] = $user['designation'] ?? '';
            break;
            
        case 'student':
            $_SESSION['mfa_pending_user']['department'] = $user['department'] ?? '';
            $_SESSION['mfa_pending_user']['admission_number'] = $user['admission_number'] ?? '';
            $_SESSION['mfa_pending_user']['year_of_admission'] = $user['year_of_admission'] ?? '';
            break;
            
        case 'principal':
        case 'manager':
            $_SESSION['mfa_pending_user']['phone'] = $user['phone'] ?? '';
            break;
            
        case 'admin':
            $_SESSION['mfa_pending_user']['phone'] = $user['phone'] ?? '';
            break;
    }

    // Log MFA initiation
    writeLoginLog($conn, $userid, $actualRole, 'Success - MFA Initiated');

    // Send MFA code via email
    if (MFAHelper::sendMFACode($user['email'], $mfa_code)) {
        header('Location: mfa_verify.php');
    } else {
        // If email fails, log the code for debugging (remove in production)
        error_log("MFA Code for {$user['email']}: {$mfa_code}");
        header('Location: mfa_verify.php');
    }
    exit;

} catch (PDOException $e) {
    error_log("Login database error: " . $e->getMessage());
    writeLoginLog($conn, $LoginEmail, $LoginRole, 'Failed - Database Error');
    header('Location: index.php?login-error=database');
    exit;
}
?>