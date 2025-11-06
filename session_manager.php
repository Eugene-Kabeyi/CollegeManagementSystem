<?php
class SessionManager {
    private static $timeoutMinutes = 30;
    
    public static function start($timeoutMinutes = 30) {
        session_start();
        self::$timeoutMinutes = $timeoutMinutes;
        self::checkTimeout();
    }
    
    public static function checkTimeout() {
        if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > self::$timeoutMinutes * 60)) {
            self::destroy();
            header('Location: index.php?timeout=1');
            exit();
        }
        $_SESSION['LAST_ACTIVITY'] = time();
    }
    
    public static function destroy() {
        session_unset();
        session_destroy();
    }
    
    public static function regenerate() {
        session_regenerate_id(true);
    }
    
    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }
    
    public static function get($key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }
    
    public static function remove($key) {
        unset($_SESSION[$key]);
    }
    
    public static function exists($key) {
        return isset($_SESSION[$key]);
    }
}
?>