<?php
// Hibakeresés kikapcsolása produkcióban
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/database.php';
require_once '../../config/session.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Csak POST kérések engedélyezettek', 405);
}

$username = trim($_POST['username'] ?? '');
$displayName = trim($_POST['display_name'] ?? '');
$avatarType = $_POST['avatar_type'] ?? 'default';
$avatarColor = $_POST['avatar_color'] ?? '#58a6ff';

if (empty($username) || empty($displayName)) {
    sendError('Minden mező kitöltése kötelező');
}

// Felhasználónév validáció
if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
    sendError('A felhasználónév 3-20 karakter hosszú lehet, csak betűk, számok és alulvonás');
}

try {
    $db = Database::getInstance();
    
    // Felhasználónév foglaltság ellenőrzése
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() > 0) {
        sendError('Ez a felhasználónév már foglalt');
    }
    
    $avatarPath = null;
    
    // Avatár feltöltés kezelése
    if ($avatarType === 'custom' && isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../img/avatars/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $file = $_FILES['avatar_file'];
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($fileExt, $allowedExts) && $file['size'] <= 2 * 1024 * 1024) { // 2MB limit
            $fileName = $username . '_' . time() . '.' . $fileExt;
            $filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                $avatarPath = 'img/avatars/' . $fileName;
            }
        } else {
            sendError('Érvénytelen fájlformátum vagy túl nagy méret (max 2MB)');
        }
    }
    
    // Felhasználó létrehozása
    $stmt = $db->prepare("
        INSERT INTO users (username, display_name, avatar_type, avatar_path, avatar_color) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$username, $displayName, $avatarType, $avatarPath, $avatarColor]);
    
    $userId = $db->lastInsertId();
    
    // Alapértelmezett statisztikák létrehozása
    $stmt = $db->prepare("INSERT INTO player_stats (user_id) VALUES (?)");
    $stmt->execute([$userId]);
    
    // Bejelentkezés
    $_SESSION['user_id'] = $userId;
    
    // Felhasználó adatok lekérése
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    sendSuccess('Sikeres regisztráció', $user);
    
} catch (Exception $e) {
    sendError('Regisztrációs hiba: ' . $e->getMessage(), 500);
}
?>