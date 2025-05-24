<?php
require_once '../../config/database.php';
require_once '../../config/session.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Csak POST kérések engedélyezettek', 405);
}

$displayName = trim($_POST['display_name'] ?? '');
$avatarColor = $_POST['avatar_color'] ?? '#58a6ff';

if (empty($displayName)) {
    sendError('A megjelenő név megadása kötelező');
}

try {
    $db = Database::getInstance();
    $user = getCurrentUser();
    $avatarPath = $user['avatar_path'];
    $avatarType = $user['avatar_type'];
    
    // Avatár feltöltés kezelése
    if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../img/avatars/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $file = $_FILES['avatar_file'];
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($fileExt, $allowedExts) && $file['size'] <= 2 * 1024 * 1024) {
            // Régi fájl törlése
            if ($avatarPath && file_exists('../../' . $avatarPath)) {
                unlink('../../' . $avatarPath);
            }
            
            $fileName = $user['username'] . '_' . time() . '.' . $fileExt;
            $filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                $avatarPath = 'img/avatars/' . $fileName;
                $avatarType = 'custom';
            }
        }
    }
    
    // Profil frissítése
    $stmt = $db->prepare("
        UPDATE users 
        SET display_name = ?, avatar_type = ?, avatar_path = ?, avatar_color = ?
        WHERE id = ?
    ");
    $stmt->execute([$displayName, $avatarType, $avatarPath, $avatarColor, $user['id']]);
    
    // Frissített felhasználó adatok
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $updatedUser = $stmt->fetch();
    
    sendSuccess('Profil sikeresen frissítve', $updatedUser);
    
} catch (Exception $e) {
    sendError('Profil frissítési hiba: ' . $e->getMessage(), 500);
}
?>