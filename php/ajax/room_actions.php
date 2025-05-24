<?php
// Minden output buffer törlése
while (ob_get_level()) {
    ob_end_clean();
}

// Hibakeresés
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Csak JSON output
header('Content-Type: application/json; charset=utf-8');

try {
    // Include fájlok
    $basePath = dirname(__FILE__) . '/../../';
    require_once $basePath . 'config/database.php';
    require_once $basePath . 'config/session.php';
    require_once $basePath . 'includes/functions.php';
    
    // Bejelentkezés ellenőrzése
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Bejelentkezés szükséges']);
        exit;
    }
    
    $user = getCurrentUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Felhasználó nem található']);
        exit;
    }
    
    // Request adatok
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'POST') {
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['success' => false, 'message' => 'Érvénytelen JSON adat: ' . json_last_error_msg()]);
            exit;
        }
        
        $action = $input['action'] ?? '';
    } else {
        $action = $_GET['action'] ?? '';
        $input = $_GET;
    }
    
    if (empty($action)) {
        echo json_encode(['success' => false, 'message' => 'Művelet nem megadva']);
        exit;
    }
    
    $db = Database::getInstance();
    
    // Műveletek
    switch ($action) {
        case 'create':
            createRoom($db, $user, $input);
            break;
            
        case 'join':
            joinRoom($db, $user, $input);
            break;
            
        case 'leave':
            leaveRoom($db, $user);
            break;
            
        case 'info':
            getRoomInfo($db, $user);
            break;
            
        case 'list':
            getActiveRoomsList($db);
            break;
            
        case 'toggle_mode':
            toggleGameMode($db, $user, $input);
            break;
            
        case 'kick':
            kickPlayer($db, $user, $input);
            break;
            
        case 'start_game':
            startGame($db, $user);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ismeretlen művelet: ' . $action]);
            exit;
    }
    
} catch (Exception $e) {
    error_log("Room action error: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine());
    echo json_encode(['success' => false, 'message' => 'Szerver hiba: ' . $e->getMessage()]);
    exit;
}

function createRoom($db, $user, $input) {
    // EGYSZERŰSÍTETT - automatikus értékek
    $roomName = $input['room_name'] ?? ($user['display_name'] . ' szobája');
    $maxPlayers = intval($input['max_players'] ?? 8);
    
    // Alapértelmezett értékek biztosítása
    if (empty($roomName)) {
        $roomName = 'Szoba #' . time();
    }
    
    if ($maxPlayers < 2 || $maxPlayers > 20) {
        $maxPlayers = 8; // Alapértelmezett
    }
    
    // Ellenőrizzük, hogy nincs-e már szobában
    $stmt = $db->prepare("SELECT room_id FROM room_players WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Már egy szobában vagy']);
        exit;
    }
    
    // Egyedi kód generálása
    $attempts = 0;
    do {
        $roomCode = generateRoomCode();
        $stmt = $db->prepare("SELECT COUNT(*) FROM rooms WHERE room_code = ? AND is_active = 1");
        $stmt->execute([$roomCode]);
        $exists = $stmt->fetchColumn() > 0;
        $attempts++;
    } while ($exists && $attempts < 10);
    
    if ($exists) {
        echo json_encode(['success' => false, 'message' => 'Nem sikerült egyedi szoba kódot generálni']);
        exit;
    }
    
    try {
        $db->connection->beginTransaction();
        
        // Szoba létrehozása
        $stmt = $db->prepare("INSERT INTO rooms (room_code, admin_id, name, max_players) VALUES (?, ?, ?, ?)");
        $stmt->execute([$roomCode, $user['id'], $roomName, $maxPlayers]);
        $roomId = $db->connection->lastInsertId();
        
        // Admin hozzáadása
        $stmt = $db->prepare("INSERT INTO room_players (room_id, user_id) VALUES (?, ?)");
        $stmt->execute([$roomId, $user['id']]);
        
        // Játékmódok engedélyezése (csak ha vannak)
        $stmt = $db->prepare("SELECT id FROM game_modes");
        $stmt->execute();
        $gameModes = $stmt->fetchAll();
        
        if (count($gameModes) > 0) {
            foreach ($gameModes as $mode) {
                $stmt = $db->prepare("INSERT INTO room_game_modes (room_id, game_mode_id, is_enabled) VALUES (?, ?, 1)");
                $stmt->execute([$roomId, $mode['id']]);
            }
        }
        
        $db->connection->commit();
        
        // Szoba adatok
        $stmt = $db->prepare("SELECT * FROM rooms WHERE id = ?");
        $stmt->execute([$roomId]);
        $room = $stmt->fetch();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Szoba sikeresen létrehozva',
            'data' => $room
        ]);
        
    } catch (Exception $e) {
        $db->connection->rollBack();
        throw $e;
    }
}

function joinRoom($db, $user, $input) {
    $roomCode = strtoupper(trim($input['room_code'] ?? ''));
    
    if (empty($roomCode)) {
        echo json_encode(['success' => false, 'message' => 'Szoba kód megadása kötelező']);
        exit;
    }
    
    // Ellenőrizzük, hogy nincs-e már szobában
    $stmt = $db->prepare("SELECT room_id FROM room_players WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Már egy szobában vagy']);
        exit;
    }
    
    // Szoba keresése
    $stmt = $db->prepare("
        SELECT r.*, COUNT(rp.user_id) as player_count 
        FROM rooms r 
        LEFT JOIN room_players rp ON r.id = rp.room_id 
        WHERE r.room_code = ? AND r.is_active = 1 AND r.is_game_started = 0
        GROUP BY r.id
    ");
    $stmt->execute([$roomCode]);
    $room = $stmt->fetch();
    
    if (!$room) {
        echo json_encode(['success' => false, 'message' => 'Szoba nem található vagy már elindult']);
        exit;
    }
    
    if ($room['player_count'] >= $room['max_players']) {
        echo json_encode(['success' => false, 'message' => 'A szoba megtelt']);
        exit;
    }
    
    // Csatlakozás
    $stmt = $db->prepare("INSERT INTO room_players (room_id, user_id) VALUES (?, ?)");
    $stmt->execute([$room['id'], $user['id']]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Sikeresen csatlakoztál a szobához',
        'data' => $room
    ]);
}

function leaveRoom($db, $user) {
    // Jelenlegi szoba keresése
    $stmt = $db->prepare("
        SELECT r.*, rp.* FROM room_players rp
        JOIN rooms r ON rp.room_id = r.id
        WHERE rp.user_id = ?
    ");
    $stmt->execute([$user['id']]);
    $roomPlayer = $stmt->fetch();
    
    if (!$roomPlayer) {
        echo json_encode(['success' => false, 'message' => 'Nem vagy szobában']);
        exit;
    }
    
    $db->connection->beginTransaction();
    
    try {
        // Játékos eltávolítása
        $stmt = $db->prepare("DELETE FROM room_players WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        
        // Ha admin volt, akkor új admin kijelölése vagy szoba törlése
        if ($roomPlayer['admin_id'] == $user['id']) {
            $stmt = $db->prepare("
                SELECT user_id FROM room_players 
                WHERE room_id = ? 
                ORDER BY joined_at ASC 
                LIMIT 1
            ");
            $stmt->execute([$roomPlayer['room_id']]);
            $newAdmin = $stmt->fetch();
            
            if ($newAdmin) {
                $stmt = $db->prepare("UPDATE rooms SET admin_id = ? WHERE id = ?");
                $stmt->execute([$newAdmin['user_id'], $roomPlayer['room_id']]);
            } else {
                // Nincs több játékos, szoba törlése
                $stmt = $db->prepare("UPDATE rooms SET is_active = 0 WHERE id = ?");
                $stmt->execute([$roomPlayer['room_id']]);
            }
        }
        
        $db->connection->commit();
        echo json_encode(['success' => true, 'message' => 'Elhagytad a szobát']);
        
    } catch (Exception $e) {
        $db->connection->rollBack();
        throw $e;
    }
}

function getRoomInfo($db, $user) {
    // Jelenlegi szoba keresése
    $stmt = $db->prepare("
        SELECT r.* FROM room_players rp
        JOIN rooms r ON rp.room_id = r.id
        WHERE rp.user_id = ? AND r.is_active = 1
    ");
    $stmt->execute([$user['id']]);
    $room = $stmt->fetch();
    
    if (!$room) {
        echo json_encode(['success' => false, 'message' => 'Nem vagy aktív szobában']);
        exit;
    }
    
    // Játékosok lekérése
    $players = getRoomPlayers($room['id']);
    
    // Játékmódok lekérése
    $stmt = $db->prepare("
        SELECT gm.*, COALESCE(rgm.is_enabled, 0) as is_enabled
        FROM game_modes gm
        LEFT JOIN room_game_modes rgm ON gm.id = rgm.game_mode_id AND rgm.room_id = ?
        ORDER BY gm.id
    ");
    $stmt->execute([$room['id']]);
    $gameModes = $stmt->fetchAll();
    
    // Saját statisztikák
    $stmt = $db->prepare("SELECT * FROM room_players WHERE room_id = ? AND user_id = ?");
    $stmt->execute([$room['id'], $user['id']]);
    $myStats = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'message' => 'Szoba információ betöltve',
        'data' => [
            'room' => $room,
            'players' => $players,
            'gameModes' => $gameModes,
            'myStats' => $myStats
        ]
    ]);
}

function getActiveRoomsList($db) {
    try {
        $stmt = $db->prepare("
            SELECT r.*, u.display_name as admin_name, COUNT(rp.user_id) as player_count
            FROM rooms r 
            JOIN users u ON r.admin_id = u.id 
            LEFT JOIN room_players rp ON r.id = rp.room_id
            WHERE r.is_active = 1 AND r.is_game_started = 0
            GROUP BY r.id 
            ORDER BY r.created_at DESC
        ");
        $stmt->execute();
        $rooms = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'message' => 'Aktív szobák betöltve',
            'data' => $rooms
        ]);
        
    } catch (Exception $e) {
        throw $e;
    }
}

function toggleGameMode($db, $user, $input) {
    $gameModeId = intval($input['game_mode_id'] ?? 0);
    $enabled = (bool)($input['enabled'] ?? false);
    
    // Ellenőrizzük, hogy admin-e
    $stmt = $db->prepare("
        SELECT r.id FROM rooms r
        JOIN room_players rp ON r.id = rp.room_id
        WHERE rp.user_id = ? AND r.admin_id = ? AND r.is_active = 1
    ");
    $stmt->execute([$user['id'], $user['id']]);
    $room = $stmt->fetch();
    
    if (!$room) {
        echo json_encode(['success' => false, 'message' => 'Nincs jogosultságod a játékmódok módosításához']);
        exit;
    }
    
    // Játékmód állapot frissítése
    $stmt = $db->prepare("
        INSERT INTO room_game_modes (room_id, game_mode_id, is_enabled) 
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled)
    ");
    $stmt->execute([$room['id'], $gameModeId, $enabled ? 1 : 0]);
    
    echo json_encode(['success' => true, 'message' => 'Játékmód állapot frissítve']);
}

function kickPlayer($db, $user, $input) {
    $targetUserId = intval($input['user_id'] ?? 0);
    
    // Ellenőrizzük, hogy admin-e
    $stmt = $db->prepare("
        SELECT r.id FROM rooms r
        JOIN room_players rp ON r.id = rp.room_id
        WHERE rp.user_id = ? AND r.admin_id = ? AND r.is_active = 1
    ");
    $stmt->execute([$user['id'], $user['id']]);
    $room = $stmt->fetch();
    
    if (!$room) {
        echo json_encode(['success' => false, 'message' => 'Nincs jogosultságod játékosok kirúgásához']);
        exit;
    }
    
    if ($targetUserId == $user['id']) {
        echo json_encode(['success' => false, 'message' => 'Magadat nem rúghatod ki']);
        exit;
    }
    
    // Játékos eltávolítása
    $stmt = $db->prepare("DELETE FROM room_players WHERE room_id = ? AND user_id = ?");
    $stmt->execute([$room['id'], $targetUserId]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Játékos kirúgva']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Játékos nem található']);
    }
}

function startGame($db, $user) {
    // Ellenőrizzük, hogy admin-e
    $stmt = $db->prepare("
        SELECT r.*, COUNT(rp.user_id) as player_count
        FROM rooms r
        JOIN room_players rp ON r.id = rp.room_id
        WHERE r.admin_id = ? AND r.is_active = 1 AND r.is_game_started = 0
        GROUP BY r.id
    ");
    $stmt->execute([$user['id']]);
    $room = $stmt->fetch();
    
    if (!$room) {
        echo json_encode(['success' => false, 'message' => 'Nincs jogosultságod a játék indításához']);
        exit;
    }
    
    if ($room['player_count'] < 2) {
        echo json_encode(['success' => false, 'message' => 'Legalább 2 játékos szükséges a játék indításához']);
        exit;
    }
    
    // Engedélyezett játékmódok ellenőrzése
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM room_game_modes 
        WHERE room_id = ? AND is_enabled = 1
    ");
    $stmt->execute([$room['id']]);
    $enabledModes = $stmt->fetchColumn();
    
    if ($enabledModes === 0) {
        echo json_encode(['success' => false, 'message' => 'Legalább egy játékmód engedélyezése szükséges']);
        exit;
    }
    
    $db->connection->beginTransaction();
    
    try {
        // Játék rekord létrehozása
        $stmt = $db->prepare("INSERT INTO games (room_id) VALUES (?)");
        $stmt->execute([$room['id']]);
        
        // Szoba állapot frissítése
        $stmt = $db->prepare("UPDATE rooms SET is_game_started = 1 WHERE id = ?");
        $stmt->execute([$room['id']]);
        
        $db->connection->commit();
        echo json_encode(['success' => true, 'message' => 'Játék elindítva']);
        
    } catch (Exception $e) {
        $db->connection->rollBack();
        throw $e;
    }
}
?>