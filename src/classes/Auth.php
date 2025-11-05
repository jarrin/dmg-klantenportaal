<?php

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function login($email, $password) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ? AND active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Update last login
                $updateStmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $updateStmt->execute([$user['id']]);
                
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['logged_in'] = true;
                
                return true;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return false;
        }
    }
    
    public function logout() {
        session_unset();
        session_destroy();
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    public function isAdmin() {
        return $this->isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }
    
    public function isCustomer() {
        return $this->isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'customer';
    }
    
    public function getCurrentUserId() {
        return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: /index.php');
            exit;
        }
    }
    
    public function requireAdmin() {
        $this->requireLogin();
        if (!$this->isAdmin()) {
            header('Location: /views/customer/dashboard.php');
            exit;
        }
    }
    
    public function requireCustomer() {
        $this->requireLogin();
        if (!$this->isCustomer()) {
            header('Location: /views/admin/dashboard.php');
            exit;
        }
    }
    
    public function hashPassword($password) {
        return password_hash($password, HASH_ALGO, ['cost' => HASH_COST]);
    }
}
