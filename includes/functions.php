<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

// Avatar színek
function getRandomAvatarColor() {
    $colors = [
        '#2ea043', '#58a6ff', '#f85149', '#ff7b00', 
        '#bf8700', '#8b5cf6', '#ec4899', '#06b6d4'
    ];
    return $colors[array_rand($colors)];
}

// Szoba kód generálás
function generateRoomCode() {
    return strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6));
}

// Automatikus szoba név generálás
function generateRoomName($userName) {
    $adjectives = [
        'Vicces', 'Bolond', 'Izgalmas', 'Laza', 'Durvás', 'Szuper', 'Menő', 'Őrült',
        'Fergeteges', 'Zseniális', 'Fantasztikus', 'Elképesztő', 'Szörnyű', 'Brutális'
    ];
    
    $nouns = [
        'Parti', 'Bugi', 'Móka', 'Szórakozás', 'Heccparty', 'Ivászat', 'Dorbézolás',
        'Bulizás', 'Felszabadulás', 'Őrjöngés', 'Szórakozás', 'Mulattság', 'Vigalom'
    ];
    
    $adjective = $adjectives[array_rand($adjectives)];
    $noun = $nouns[array_rand($nouns)];
    
    return "{$userName} {$adjective} {$noun}ja";
}

// Felhasználó avatar HTML
function getUserAvatarHtml($user, $size = 36) {
    $initial = strtoupper(substr($user['display_name'], 0, 1));
    
    if ($user['avatar_type'] === 'custom' && !empty($user['avatar_path'])) {
        return "<div class='avatar-display' style='width: {$size}px; height: {$size}px;'>
                    <img src='{$user['avatar_path']}' alt='Avatar'>
                </div>";
    } else {
        return "<div class='avatar-display' style='width: {$size}px; height: {$size}px; background-color: {$user['avatar_color']};'>
                    {$initial}
                </div>";
    }
}

// Szoba játékosok lekérése
function getRoomPlayers($roomId) {
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT rp.*, u.username, u.display_name, u.avatar_type, u.avatar_path, u.avatar_color,
                   r.admin_id = u.id as is_admin
            FROM room_players rp 
            JOIN users u ON rp.user_id = u.id 
            JOIN rooms r ON rp.room_id = r.id
            WHERE rp.room_id = ? 
            ORDER BY rp.joined_at ASC
        ");
        $stmt->execute([$roomId]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("getRoomPlayers error: " . $e->getMessage());
        return [];
    }
}

// Aktív szobák lekérése
function getActiveRooms() {
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT r.*, u.display_name as admin_name,
                   COUNT(rp.user_id) as player_count
            FROM rooms r 
            JOIN users u ON r.admin_id = u.id 
            LEFT JOIN room_players rp ON r.id = rp.room_id
            WHERE r.is_active = 1 AND r.is_game_started = 0
            GROUP BY r.id 
            ORDER BY r.created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("getActiveRooms error: " . $e->getMessage());
        return [];
    }
}

// Felhasználó statisztikák
function getUserStats($userId) {
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM player_stats WHERE user_id = ?");
        $stmt->execute([$userId]);
        $stats = $stmt->fetch();
        
        if (!$stats) {
            // Létrehozzuk a statisztikákat, ha még nem léteznek
            $stmt = $db->prepare("INSERT INTO player_stats (user_id) VALUES (?)");
            $stmt->execute([$userId]);
            return getUserStats($userId);
        }
        
        return $stats;
    } catch (Exception $e) {
        error_log("getUserStats error: " . $e->getMessage());
        return null;
    }
}

// JSON válasz küldése
function sendJsonResponse($data) {
    // Mindenképpen JSON választ küldünk
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Hibaüzenet küldése
function sendError($message, $code = 400) {
    http_response_code($code);
    sendJsonResponse(['success' => false, 'message' => $message]);
}

// Sikeres válasz küldése
function sendSuccess($message, $data = []) {
    sendJsonResponse(['success' => true, 'message' => $message, 'data' => $data]);
}
?>
