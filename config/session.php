<?php
// config/session.php
session_start();

class Session {
    
    public static function init() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }
    
    public static function get($key) {
        return $_SESSION[$key] ?? null;
    }
    
    public static function destroy() {
        session_destroy();
        $_SESSION = array();
    }
    
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public static function redirectIfNotLoggedIn($redirectTo = 'login.php') {
        if (!self::isLoggedIn()) {
            header("Location: $redirectTo");
            exit();
        }
    }
    
    public static function getCurrentUserRole() {
        return $_SESSION['role'] ?? null;
    }
    
    public static function checkRoleAccess($allowedRoles) {
        if (!self::isLoggedIn() || !in_array(self::getCurrentUserRole(), $allowedRoles)) {
            header("Location: dashboard.php");
            exit();
        }
    }
    
    public static function getUserID() {
        return $_SESSION['user_id'] ?? null;
    }
    
    public static function getUserFullName() {
        return $_SESSION['first_name'] . ' ' . $_SESSION['last_name'] ?? '';
    }
}
?>