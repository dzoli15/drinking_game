<?php
require_once '../../config/database.php';
require_once '../../config/session.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');
requireLogin();

$method = $_SERVER['REQUEST_METHOD'];
$input = null;

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
} else {
    $action = $_GET['action'] ?? '';
}

try {
    $db = Database::getInstance();
    $user = getCurrentUser();
    
    switch ($action) {
        case 'state':
            handleGetGameState($db, $user);
            break;
            
        case 'complete':
        case 'fail':
            handleCardCompletion($db, $user, $action);
            break;
            
        case 'vote':
            handleVote($db, $user, $input);
            break;
            
        case 'answer':
            handleAnswer($db, $user, $input);
            break;
            
        case 'submit_statements':
            handleSubmitStatements($db, $user, $input);
            break;
            
        case 'pass':
            handlePass($db, $user, $input);
            break;
            
        case 'drink':
            handleDrink($db, $user);
            break;
            
        case 'end_game':
            handleEndGame($db, $user);
            break;
            
        default:
            sendError('Ismeretlen játék művelet');
    }
    
} catch (Exception $e) {
    sendError('Játék művelet hiba: ' . $e->getMessage(), 500);
}

function getCurrentGameRoom($db, $user) {
    $stmt = $db->prepare("
        SELECT r.*, rp.points, rp.drinks 
        FROM room_players rp
        JOIN rooms r ON rp.room_id = r.id
        WHERE rp.user_id = ? AND r.is_active = 1 AND r.is_game_started = 1
    ");
    $stmt->execute([$user['id']]);
    return $stmt->fetch();
}

function getNextCard($db, $roomId) {
    // Okos keverés: minden típusból egyszer, mielőtt újra kezdjük
    $stmt = $db->prepare("
        SELECT c.*, gm.name as game_mode, gm.display_name as game_mode_name
        FROM cards c
        JOIN game_modes gm ON c.game_mode_id = gm.id
        JOIN room_game_modes rgm ON gm.id = rgm.game_mode_id
        WHERE rgm.room_id = ? AND rgm.is_enabled = 1
        AND NOT EXISTS (
            SELECT 1 FROM game_actions ga 
            WHERE ga.room_id = ? AND ga.card_id = c.id 
            AND ga.created_at > (
                SELECT COALESCE(MAX(created_at), '1970-01-01') 
                FROM game_actions 
                WHERE room_id = ? AND action_type = 'round_reset'
            )
        )
        ORDER BY RAND()
        LIMIT 1
    ");
    $stmt->execute([$roomId, $roomId, $roomId]);
    $card = $stmt->fetch();
    
    // Ha nincs több kártya, új kört kezdünk
    if (!$card) {
        $stmt = $db->prepare("
            INSERT INTO game_actions (room_id, card_id, user_id, action_type) 
            VALUES (?, 0, ?, 'round_reset')
        ");
        $stmt->execute([$roomId, 1]); // Dummy user ID
        
        return getNextCard($db, $roomId);
    }
    
    return $card;
}

function handleGetGameState($db, $user) {
    $room = getCurrentGameRoom($db, $user);
    
    if (!$room) {
        sendError('Nincs aktív játékban');
    }
    
    // Jelenlegi kártya
    $currentCard = null;
    if ($room['current_card_id']) {
        $stmt = $db->prepare("
            SELECT c.*, gm.name as game_mode, gm.display_name as game_mode_name
            FROM cards c
            JOIN game_modes gm ON c.game_mode_id = gm.id
            WHERE c.id = ?
        ");
        $stmt->execute([$room['current_card_id']]);
        $currentCard = $stmt->fetch();
    } else {
        // Új kártya húzása
        $currentCard = getNextCard($db, $room['id']);
        if ($currentCard) {
            $stmt = $db->prepare("UPDATE rooms SET current_card_id = ? WHERE id = ?");
            $stmt->execute([$currentCard['id'], $room['id']]);
        }
    }
    
    // Játékosok listája
    $stmt = $db->prepare("
        SELECT rp.*, u.display_name, u.avatar_type, u.avatar_path, u.avatar_color
        FROM room_players rp
        JOIN users u ON rp.user_id = u.id
        WHERE rp.room_id = ?
        ORDER BY rp.points DESC, rp.joined_at ASC
    ");
    $stmt->execute([$room['id']]);
    $players = $stmt->fetchAll();
    
    // Várakozás ellenőrzése
    $waitingForAction = checkIfWaitingForAction($db, $room['id'], $currentCard, $user['id']);
    
    // Ranglista timer ellenőrzése
    $showLeaderboard = false;
    $leaderboardTimer = 0;
    
    $gameState = [
        'room' => $room,
        'current_card' => $currentCard,
        'players' => $players,
        'my_stats' => [
            'points' => $room['points'],
            'drinks' => $room['drinks']
        ],
        'waiting_for_action' => $waitingForAction,
        'show_leaderboard' => $showLeaderboard,
        'leaderboard_timer' => $leaderboardTimer,
        'leaderboard' => $players
    ];
    
    sendSuccess('Játék állapot betöltve', $gameState);
}

function checkIfWaitingForAction($db, $roomId, $card, $userId) {
    if (!$card) return false;
    
    // Ellenőrizzük, hogy a felhasználó már cselekedett-e
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM game_actions 
        WHERE room_id = ? AND card_id = ? AND user_id = ? 
        AND action_type IN ('complete', 'fail', 'vote', 'answer', 'pass')
    ");
    $stmt->execute([$roomId, $card['id'], $userId]);
    
    return $stmt->fetchColumn() == 0;
}

function handleCardCompletion($db, $user, $action) {
    $room = getCurrentGameRoom($db, $user);
    if (!$room) sendError('Nincs aktív játékban');
    
    $currentCard = getCurrentCard($db, $room);
    if (!$currentCard) sendError('Nincs aktív kártya');
    
    $db->connection->beginTransaction();
    
    try {
        // Akció rögzítése
        $stmt = $db->prepare("
            INSERT INTO game_actions (room_id, card_id, user_id, action_type) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$room['id'], $currentCard['id'], $user['id'], $action]);
        
        // Pontok és kortyok számítása
        $points = 0;
        $drinks = 0;
        
        if ($action === 'complete') {
            $points = $currentCard['points'];
        } else {
            $points = -$currentCard['points'];
            $drinks = $currentCard['difficulty'];
        }
        
        // Játékos statisztikák frissítése
        $stmt = $db->prepare("
            UPDATE room_players 
            SET points = points + ?, drinks = drinks + ?
            WHERE room_id = ? AND user_id = ?
        ");
        $stmt->execute([$points, $drinks, $room['id'], $user['id']]);
        
        // Kártya statisztikák frissítése
        updateCardStats($db, $user['id'], $currentCard['id'], $action);
        
        // Következő kártyára lépés
        advanceToNextCard($db, $room['id']);
        
        $db->connection->commit();
        sendSuccess('Kártya feldolgozva');
        
    } catch (Exception $e) {
        $db->connection->rollBack();
        throw $e;
    }
}

function handleVote($db, $user, $input) {
    $room = getCurrentGameRoom($db, $user);
    if (!$room) sendError('Nincs aktív játékban');
    
    $currentCard = getCurrentCard($db, $room);
    if (!$currentCard) sendError('Nincs aktív kártya');
    
    $voteData = [];
    
    if (isset($input['target_player_id'])) {
        $voteData['target_player_id'] = $input['target_player_id'];
    }
    
    if (isset($input['statement_index'])) {
        $voteData['statement_index'] = $input['statement_index'];
    }
    
    // Szavazat rögzítése
    $stmt = $db->prepare("
        INSERT INTO game_actions (room_id, card_id, user_id, action_type, action_data) 
        VALUES (?, ?, ?, 'vote', ?)
    ");
    $stmt->execute([$room['id'], $currentCard['id'], $user['id'], json_encode($voteData)]);
    
    // Ellenőrizzük, hogy minden játékos szavazott-e
    checkAllPlayersVoted($db, $room, $currentCard);
    
    sendSuccess('Szavazat rögzítve');
}

function handleAnswer($db, $user, $input) {
    $room = getCurrentGameRoom($db, $user);
    if (!$room) sendError('Nincs aktív játékban');
    
    $currentCard = getCurrentCard($db, $room);
    if (!$currentCard) sendError('Nincs aktív kártya');
    
    $answer = trim($input['answer'] ?? '');
    if (empty($answer)) sendError('Válasz megadása kötelező');
    
    // Válasz rögzítése
    $stmt = $db->prepare("
        INSERT INTO game_actions (room_id, card_id, user_id, action_type, action_data) 
        VALUES (?, ?, ?, 'answer', ?)
    ");
    $stmt->execute([$room['id'], $currentCard['id'], $user['id'], json_encode(['answer' => $answer])]);
    
    sendSuccess('Válasz rögzítve');
}

function handleSubmitStatements($db, $user, $input) {
    $room = getCurrentGameRoom($db, $user);
    if (!$room) sendError('Nincs aktív játékban');
    
    $currentCard = getCurrentCard($db, $room);
    if (!$currentCard) sendError('Nincs aktív kártya');
    
    $statements = $input['statements'] ?? [];
    if (count($statements) !== 3) sendError('Pontosan 3 állítás szükséges');
    
    // Állítások rögzítése
    $stmt = $db->prepare("
        INSERT INTO game_actions (room_id, card_id, user_id, action_type, action_data) 
        VALUES (?, ?, ?, 'submit_statements', ?)
    ");
    $stmt->execute([$room['id'], $currentCard['id'], $user['id'], json_encode(['statements' => $statements])]);
    
    sendSuccess('Állítások rögzítve');
}

function handlePass($db, $user, $input) {
    $room = getCurrentGameRoom($db, $user);
    if (!$room) sendError('Nincs aktív játékban');
    
    $currentCard = getCurrentCard($db, $room);
    if (!$currentCard) sendError('Nincs aktív kártya');
    
    $targetPlayerId = intval($input['target_player_id'] ?? 0);
    
    // Bomba továbbadás logika
    if ($currentCard['game_mode'] === 'hot_potato') {
        $exploded = (rand(1, 10) <= 3); // 30% esély a felrobbanásra
        
        if ($exploded) {
            // Felrobbant - a jelenlegi játékos iszik
            $stmt = $db->prepare("
                UPDATE room_players 
                SET drinks = drinks + ?, points = points - ?
                WHERE room_id = ? AND user_id = ?
            ");
            $stmt->execute([2, 1, $room['id'], $user['id']]);
            
            advanceToNextCard($db, $room['id']);
            sendSuccess('A bomba felrobbant! Iszol 2-t!');
        } else {
            // Továbbadás
            $stmt = $db->prepare("
                INSERT INTO game_actions (room_id, card_id, user_id, action_type, action_data) 
                VALUES (?, ?, ?, 'pass', ?)
            ");
            $stmt->execute([$room['id'], $currentCard['id'], $user['id'], json_encode(['target_player_id' => $targetPlayerId])]);
            
            sendSuccess('Bomba továbbadva');
        }
    }
}

function handleDrink($db, $user) {
    $room = getCurrentGameRoom($db, $user);
    if (!$room) sendError('Nincs aktív játékban');
    
    if ($room['drinks'] > 0) {
        $stmt = $db->prepare("
            UPDATE room_players 
            SET drinks = GREATEST(drinks - 1, 0)
            WHERE room_id = ? AND user_id = ?
        ");
        $stmt->execute([$room['id'], $user['id']]);
    }
    
    sendSuccess('Korty levonva');
}

function handleEndGame($db, $user) {
    // Admin ellenőrzés
    $stmt = $db->prepare("
        SELECT r.id FROM rooms r
        WHERE r.admin_id = ? AND r.is_active = 1 AND r.is_game_started = 1
    ");
    $stmt->execute([$user['id']]);
    $room = $stmt->fetch();
    
    if (!$room) {
        sendError('Nincs jogosultságod a játék befejezéséhez');
    }
    
    $db->connection->beginTransaction();
    
    try {
        // Játék befejezése
        $stmt = $db->prepare("
            UPDATE games 
            SET ended_at = NOW() 
            WHERE room_id = ? AND ended_at IS NULL
        ");
        $stmt->execute([$room['id']]);
        
        // Szoba állapot visszaállítása
        $stmt = $db->prepare("
            UPDATE rooms 
            SET is_game_started = 0, current_card_id = NULL 
            WHERE id = ?
        ");
        $stmt->execute([$room['id']]);
        
        // Játékos statisztikák frissítése
        updatePlayerStats($db, $room['id']);
        
        $db->connection->commit();
        sendSuccess('Játék befejezve');
        
    } catch (Exception $e) {
        $db->connection->rollBack();
        throw $e;
    }
}

function getCurrentCard($db, $room) {
    if (!$room['current_card_id']) return null;
    
    $stmt = $db->prepare("
        SELECT c.*, gm.name as game_mode, gm.display_name as game_mode_name
        FROM cards c
        JOIN game_modes gm ON c.game_mode_id = gm.id
        WHERE c.id = ?
    ");
    $stmt->execute([$room['current_card_id']]);
    return $stmt->fetch();
}

function advanceToNextCard($db, $roomId) {
    $stmt = $db->prepare("UPDATE rooms SET current_card_id = NULL WHERE id = ?");
    $stmt->execute([$roomId]);
}

function updateCardStats($db, $userId, $cardId, $action) {
    $completed = ($action === 'complete') ? 1 : 0;
    $failed = ($action === 'fail') ? 1 : 0;
    
    $stmt = $db->prepare("
        INSERT INTO card_stats (user_id, card_id, times_drawn, times_completed, times_failed) 
        VALUES (?, ?, 1, ?, ?)
        ON DUPLICATE KEY UPDATE 
            times_drawn = times_drawn + 1,
            times_completed = times_completed + VALUES(times_completed),
            times_failed = times_failed + VALUES(times_failed)
    ");
    $stmt->execute([$userId, $cardId, $completed, $failed]);
}

function checkAllPlayersVoted($db, $room, $card) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM room_players WHERE room_id = ?");
    $stmt->execute([$room['id']]);
    $totalPlayers = $stmt->fetchColumn();
    
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM game_actions 
        WHERE room_id = ? AND card_id = ? AND action_type = 'vote'
    ");
    $stmt->execute([$room['id'], $card['id']]);
    $voteCount = $stmt->fetchColumn();
    
    if ($voteCount >= $totalPlayers) {
        advanceToNextCard($db, $room['id']);
    }
}

function updatePlayerStats($db, $roomId) {
    // Játékos statisztikák frissítése a játék végén
    $stmt = $db->prepare("
        SELECT rp.user_id, rp.points, rp.drinks,
               ROW_NUMBER() OVER (ORDER BY rp.points DESC) as final_rank
        FROM room_players rp
        WHERE rp.room_id = ?
    ");
    $stmt->execute([$roomId]);
    $players = $stmt->fetchAll();
    
    foreach ($players as $player) {
        $isWinner = ($player['final_rank'] == 1);
        
        $stmt = $db->prepare("
            UPDATE player_stats 
            SET games_played = games_played + 1,
                games_won = games_won + ?,
                total_points = total_points + ?,
                total_drinks = total_drinks + ?,
                max_points_in_round = GREATEST(max_points_in_round, ?)
            WHERE user_id = ?
        ");
        $stmt->execute([
            $isWinner ? 1 : 0,
            $player['points'],
            $player['drinks'],
            $player['points'],
            $player['user_id']
        ]);
    }
}
?>