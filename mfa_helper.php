<?php
// mfa_helper.php
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
}
?>