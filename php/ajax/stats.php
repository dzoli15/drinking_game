<?php
require_once '../../config/database.php';
require_once '../../config/session.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');
requireLogin();

try {
    $user = getCurrentUser();
    $db = Database::getInstance();
    
    // Alapvető statisztikák
    $stats = getUserStats($user['id']);
    
    // Kártya specifikus statisztikák
    $stmt = $db->prepare("
        SELECT 
            c.title,
            gm.display_name as game_mode_name,
            cs.times_drawn,
            cs.times_completed,
            cs.times_failed
        FROM card_stats cs
        JOIN cards c ON cs.card_id = c.id
        JOIN game_modes gm ON c.game_mode_id = gm.id
        WHERE cs.user_id = ? AND cs.times_drawn > 0
        ORDER BY cs.times_drawn DESC
        LIMIT 10
    ");
    $stmt->execute([$user['id']]);
    $cardStats = $stmt->fetchAll();
    
    $stats['card_stats'] = $cardStats;
    
    sendSuccess('Statisztikák betöltve', $stats);
    
} catch (Exception $e) {
    sendError('Statisztika betöltési hiba: ' . $e->getMessage(), 500);
}
?>