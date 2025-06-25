<?php
class Database {
    private $pdo;
    
    public function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host=".DB_HOST.";dbname=".DB_NAME, 
                DB_USER, 
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    public function getUserByUsername($username) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch();
    }
    
    public function getUserByEmail($email) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }
    
    public function getUserById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function updateUser2FASecret($userId, $secret) {
        $stmt = $this->pdo->prepare("UPDATE users SET two_factor_secret = ? WHERE id = ?");
        return $stmt->execute([$secret, $userId]);
    }
    
    public function updateUserBackupCodes($userId, $codes) {
        $serializedCodes = json_encode($codes);
        $stmt = $this->pdo->prepare("UPDATE users SET backup_codes = ? WHERE id = ?");
        return $stmt->execute([$serializedCodes, $userId]);
    }
    
    public function updateLastLogin($userId) {
        $stmt = $this->pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        return $stmt->execute([$userId]);
    }
    
    public function logActivity($userId, $action, $description, $ipAddress, $userAgent) {
        $stmt = $this->pdo->prepare("
            INSERT INTO activity_logs 
            (user_id, action, description, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$userId, $action, $description, $ipAddress, $userAgent]);
    }
    //Forgot-password 
//     public function getUserByEmail($email) {
//     $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
//     $stmt->execute([$email]);
//     return $stmt->fetch(PDO::FETCH_ASSOC);
// }

public function storePasswordResetToken($userId, $token, $expires) {
    // First delete any existing tokens for this user
    $this->pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")
              ->execute([$userId]);
    
    // Insert new token
    $stmt = $this->pdo->prepare("
        INSERT INTO password_resets (user_id, token, expires_at) 
        VALUES (?, ?, ?)
    ");
    return $stmt->execute([$userId, $token, date('Y-m-d H:i:s', $expires)]);
}

public function validatePasswordResetToken($token) {
    $stmt = $this->pdo->prepare("
        SELECT pr.*, u.email 
        FROM password_resets pr
        JOIN users u ON pr.user_id = u.id
        WHERE pr.token = ? AND pr.expires_at > NOW()
    ");
    $stmt->execute([$token]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

public function deletePasswordResetToken($token) {
    return $this->pdo->prepare("DELETE FROM password_resets WHERE token = ?")
                     ->execute([$token]);
}

//Reset-Password Feature 
    public function updateUserPassword($userId, $hashedPassword) {
    $stmt = $this->pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    return $stmt->execute([$hashedPassword, $userId]);
}

public function invalidateAllUserTokens($userId) {
    return $this->pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")
                    ->execute([$userId]);
}

    // Additional utility methods
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    public function commit() {
        return $this->pdo->commit();
    }
    
    public function rollBack() {
        return $this->pdo->rollBack();
    }
    
    // Generic query method for complex queries
    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    public function logFailedAttempt($ip, $username) {
    $stmt = $this->pdo->prepare("
        INSERT INTO login_attempts (ip_address, username)
        VALUES (?, ?)
    ");
    $stmt->execute([$ip, $username]);
}
}