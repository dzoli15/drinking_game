<?php
require_once '../../config/database.php';
require_once '../../config/session.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

try {
    $user = getCurrentUser();
    
    if ($user) {
        sendSuccess('Felhasználó betöltve', $user);
    } else {
        sendError('Nincs bejelentkezett felhasználó', 401);
    }
} catch (Exception $e) {
    sendError('Szerver hiba: ' . $e->getMessage(), 500);
}
?>
