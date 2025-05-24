<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Bejelentkezés szükséges']);
        exit;
    }
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if (!$user) {
            // Ha a felhasználó nem található az adatbázisban, kijelentkeztetjük
            logout();
            return null;
        }
        
        return $user;
    } catch (Exception $e) {
        error_log("getCurrentUser error: " . $e->getMessage());
        return null;
    }
}

function logout() {
    if (session_status() !== PHP_SESSION_NONE) {
        session_destroy();
    }
    session_start();
}
?>
