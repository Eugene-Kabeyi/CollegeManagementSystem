<?php
// mfa_helper.php
date_default_timezone_set('Africa/Nairobi');
class MFAHelper {
    
    public static function generateMFACode($length = 6) {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= random_int(0, 9);
        }
        return $code;
    }
    
    public static function storeMFACode($user_id, $code) {
        $_SESSION['mfa_code'] = $code;
        $_SESSION['mfa_user_id'] = $user_id;
        $_SESSION['mfa_expires'] = time() + 600; // 10 minutes expiration
        $_SESSION['mfa_attempts'] = 0;
    }
    
    public static function validateMFACode($code) {
        if (!isset($_SESSION['mfa_code']) || 
            !isset($_SESSION['mfa_expires']) ||
            time() > $_SESSION['mfa_expires']) {
            return false;
        }
        
        // Check attempts
        $_SESSION['mfa_attempts'] = ($_SESSION['mfa_attempts'] ?? 0) + 1;
        if ($_SESSION['mfa_attempts'] > 3) {
            self::clearMFASession();
            return false;
        }
        
        if ($_SESSION['mfa_code'] === $code) {
            self::clearMFASession();
            return true;
        }
        
        return false;
    }
    
    public static function clearMFASession() {
        unset($_SESSION['mfa_code']);
        unset($_SESSION['mfa_expires']);
        unset($_SESSION['mfa_attempts']);
        unset($_SESSION['mfa_user_id']);
    }
    
    public static function isMFARequired() {
        return isset($_SESSION['mfa_user_id']);
    }
    
    public static function sendMFACode($email, $code) {
        $from_email = "genedyce238@gmail.com";
        
        $subject = "Your CEA Login Verification Code";
        $message = "
        <html>
        <head>
            <title>Login Verification Code</title>
        </head>
        <body>
            <h2>CEA Login Verification</h2>
            <p>Your verification code is: <strong>{$code}</strong></p>
            <p>This code will expire in 10 minutes.</p>
            <p>If you didn't request this code, please ignore this email.</p>
        </body>
        </html>
        ";
        
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: CEA System <{$from_email}>" . "\r\n";
        $headers .= "Reply-To: no-reply@yourcollege.edu" . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        // Debug: Log everything before sending
        error_log("=== MFA EMAIL DEBUG ===");
        error_log("To: $email");
        error_log("From: $from_email");
        error_log("Code: $code");
        error_log("Headers: " . $headers);
        
        $result = mail($email, $subject, $message, $headers);
        
        // Log the result with more details
        if ($result) {
            error_log("✅ MFA email sent to: $email");
            error_log("Last error: " . (error_get_last()['message'] ?? 'No error'));
        } else {
            error_log("❌ Failed to send MFA email to: $email");
            error_log("Last error: " . (error_get_last()['message'] ?? 'Unknown error'));
        }
        
        return $result;
    }
    
    // NEW: Check if account is locked out
public static function isAccountLocked($conn, $userid, $email, $role) {
    try {
        $stmt = $conn->prepare("
            SELECT * FROM `account_lockout` 
            WHERE userid = ? AND email = ? AND role = ? 
            AND (unlock_time IS NULL OR unlock_time > NOW())
            ORDER BY lockout_time DESC 
            LIMIT 1
        ");
        $stmt->execute([$userid, $email, $role]);
        $lockout = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If we found a lockout record but unlock_time is in the past, it's not locked
        if ($lockout && !empty($lockout['unlock_time'])) {
            $unlock_timestamp = strtotime($lockout['unlock_time']);
            $current_timestamp = time();
            
            if ($unlock_timestamp <= $current_timestamp) {
                // Auto-unlock since unlock_time has passed
                self::unlockAccount($conn, $userid, $email, $role);
                return false;
            }
        }
        
        return $lockout ? $lockout : false;
        
    } catch (PDOException $e) {
        error_log("Account lockout check error: " . $e->getMessage());
        return false;
    }
}
    
    // NEW: Record account lockoutpublic static function recordLockout($conn, $userid, $email, $role, $reason = 'MFA Failed Attempts') {
  public static function recordLockout($conn, $userid, $email, $role, $reason = 'MFA Failed Attempts') {
    try {
        // Debug current timezone
        $current_timezone = date_default_timezone_get();
        error_log("Current PHP timezone: " . $current_timezone);
        
        // Set to EAT if not already
        if ($current_timezone !== 'Africa/Nairobi') {
            date_default_timezone_set('Africa/Nairobi');
            error_log("Changed timezone to: Africa/Nairobi");
        }
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        
        // Calculate times
        $current_timestamp = time();
        $unlock_timestamp = $current_timestamp + (30 * 60);
        
        $lockout_time = date('Y-m-d H:i:s', $current_timestamp);
        $unlock_time = date('Y-m-d H:i:s', $unlock_timestamp);
        
        error_log("=== TIME CALCULATION WITH TIMEZONE ===");
        error_log("Server timezone: " . date_default_timezone_get());
        error_log("Current timestamp: " . $current_timestamp);
        error_log("Unlock timestamp: " . $unlock_timestamp);
        error_log("Lockout time: " . $lockout_time);
        error_log("Unlock time: " . $unlock_time);
        error_log("Expected: Current + 30 minutes");
        
        // Rest of the function...
        $existing = self::isAccountLocked($conn, $userid, $email, $role);
        
        if ($existing) {
            $stmt = $conn->prepare("
                UPDATE `account_lockout` 
                SET attempts = attempts + 1,
                    lockout_time = ?,
                    unlock_time = ?,
                    lockout_reason = ?,
                    ip_address = ?
                WHERE id = ?
            ");
            $stmt->execute([$lockout_time, $unlock_time, $reason, $ip, $existing['id']]);
        } else {
            $stmt = $conn->prepare("
                INSERT INTO `account_lockout` 
                (userid, email, role, lockout_time, unlock_time, lockout_reason, ip_address, attempts) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([$userid, $email, $role, $lockout_time, $unlock_time, $reason, $ip]);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Account lockout recording error: " . $e->getMessage());
        return false;
    }
}
    
    // NEW: Unlock account
    public static function unlockAccount($conn, $userid, $email, $role) {
        try {
            $stmt = $conn->prepare("
                UPDATE `account_lockout` 
                SET unlock_time = NOW() 
                WHERE userid = ? AND email = ? AND role = ? AND unlock_time IS NULL
            ");
            $stmt->execute([$userid, $email, $role]);
            
            error_log("Account unlocked for: $email ($userid)");
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Account unlock error: " . $e->getMessage());
            return false;
        }
    }
    
    // NEW: Get lockout status with time remaining
   public static function getLockoutStatus($conn, $userid, $email, $role) {
    $lockout = self::isAccountLocked($conn, $userid, $email, $role);
    
    if (!$lockout) {
        return ['locked' => false];
    }
    
    // Use the stored unlock_time if available
    if (!empty($lockout['unlock_time'])) {
        $unlock_time = strtotime($lockout['unlock_time']);
        $time_remaining = $unlock_time - time();
    } else {
        // Fallback: calculate from lockout_time (30 minutes)
        $lockout_time = strtotime($lockout['lockout_time']);
        $unlock_time = $lockout_time + (30 * 60);
        $time_remaining = $unlock_time - time();
    }
    
    return [
        'locked' => true,
        'lockout_time' => $lockout['lockout_time'],
        'unlock_time' => !empty($lockout['unlock_time']) ? $lockout['unlock_time'] : date('Y-m-d H:i:s', $unlock_time),
        'time_remaining' => $time_remaining,
        'minutes_remaining' => ceil($time_remaining / 60),
        'reason' => $lockout['lockout_reason'],
        'attempts' => $lockout['attempts']
    ];
}
}
?>