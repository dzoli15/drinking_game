<?php
require_once '../../config/database.php';
require_once '../../config/session.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Csak POST kérések engedélyezettek', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$username = trim($input['username'] ?? '');

if (empty($username)) {
    sendError('Felhasználónév megadása kötelező');
}

try {
    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendError('Ismeretlen felhasználónév');
    }
    
    // Bejelentkezés
    $_SESSION['user_id'] = $user['id'];
    
    // Utolsó aktivitás frissítése
    $stmt = $db->prepare("UPDATE users SET last_active = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    sendSuccess('Sikeres bejelentkezés', $user);
    
} catch (Exception $e) {
    sendError('Bejelentkezési hiba: ' . $e->getMessage(), 500);
}
?>