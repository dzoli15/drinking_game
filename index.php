<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/functions.php';

$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ivós Játék</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Navigációs sáv -->
    <div class="navigation-bar">
        <label for="game-mode-select">Játék állapot:</label>
        <select id="game-mode-select" disabled>
            <option value="lobby">Lobby</option>
        </select>
    </div>

    <!-- Infobox üzenetek -->
    <div id="infobox-container"></div>

    <!-- Bejelentkezés/Regisztráció szekció -->
    <div id="login-register-section" class="page-section <?php echo !$user ? 'active-section' : ''; ?>">
        <main class="container">
            <div class="panel-container">
                <div class="panel-tabs">
                    <button class="tab-link active" data-tab="login">Bejelentkezés</button>
                    <button class="tab-link" data-tab="register">Regisztráció</button>
                </div>
                
                <!-- Bejelentkezés panel -->
                <div id="login-panel" class="panel active">
                    <form id="login-form">
                        <div class="form-group">
                            <label for="login-username">Felhasználónév</label>
                            <input type="text" id="login-username" name="username" class="form-input" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-full-width">Bejelentkezés</button>
                        <div id="login-error" class="error-message"></div>
                    </form>
                </div>
                
                <!-- Regisztráció panel -->
                <div id="register-panel" class="panel">
                    <form id="register-form">
                        <div class="form-group">
                            <label for="register-username">Felhasználónév</label>
                            <input type="text" id="register-username" name="username" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label for="register-display-name">Megjelenő név</label>
                            <input type="text" id="register-display-name" name="display_name" class="form-input" required>
                        </div>
                        
                        <div class="avatar-options">
                            <label>Avatár választás</label>
                            <div class="default-avatar-section">
                                <div id="default-avatar-preview" class="default-avatar-preview selected-avatar">A</div>
                                <input type="color" id="avatar-color-picker" value="#58a6ff">
                                <input type="hidden" id="selected-avatar-type" value="default">
                                <input type="hidden" id="selected-avatar-color" value="#58a6ff">
                            </div>
                            
                            <div class="form-group">
                                <label for="avatar-upload">Vagy tölts fel saját avatárt</label>
                                <input type="file" id="avatar-upload" accept="image/*" class="form-input">
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-full-width">Regisztráció</button>
                        <div id="register-error" class="error-message"></div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <!-- Lobby szekció -->
    <div id="lobby-section" class="page-section <?php echo $user ? 'active-section' : ''; ?>">
        <div class="app-header">
            <div class="user-info">
                <div id="user-avatar"><?php echo $user ? getUserAvatarHtml($user) : ''; ?></div>
                <span class="username-text" id="user-display-name"><?php echo $user ? htmlspecialchars($user['display_name']) : ''; ?></span>
                <button class="btn-icon" id="profile-settings-btn" title="Profil beállítások">
                    <i class="fas fa-cog"></i>
                </button>
            </div>
            <div class="header-actions">
                <button class="btn btn-secondary" id="rules-btn">Szabályzat</button>
                <button class="btn btn-danger" id="logout-btn">Kijelentkezés</button>
            </div>
        </div>
        
        <main class="container">
            <div class="lobby-main">
                <div class="lobby-controls">
                    <button class="btn btn-primary btn-full-width" id="create-room-btn">Szoba létrehozása</button>
                    <button class="btn btn-info btn-full-width" id="stats-btn">Statisztikáim</button>
                </div>
                
                <div id="active-rooms-container">
                    <h3>Aktív szobák</h3>
                    <div id="rooms-list"></div>
                </div>
            </div>
        </main>
    </div>

    <!-- Szoba szekció -->
    <div id="room-section" class="page-section">
        <div class="app-header">
            <div class="user-info">
                <div id="room-user-avatar"><?php echo $user ? getUserAvatarHtml($user) : ''; ?></div>
                <span class="username-text" id="room-user-display-name"><?php echo $user ? htmlspecialchars($user['display_name']) : ''; ?></span>
                <button class="btn-icon" id="room-profile-settings-btn" title="Profil beállítások">
                    <i class="fas fa-cog"></i>
                </button>
            </div>
            <div class="header-actions">
                <button class="btn btn-secondary" id="room-rules-btn">Szabályzat</button>
                <button class="btn btn-danger" id="leave-room-btn">Szoba elhagyása</button>
            </div>
        </div>
        
        <main class="container">
            <div class="room-main-container">
                <div class="room-info-bar">
                    <div class="room-code-display">
                        Szoba kód: <strong id="current-room-code"></strong>
                    </div>
                    <div class="game-stats-header">
                        <span>Pontjaim: <strong id="my-points">0</strong> pont</span>
                        <span class="stats-divider">|</span>
                        <span>Kortyaim: <strong id="my-drinks">0</strong> korty</span>
                        <button class="drink-icon" id="drink-btn" title="Iszom egyet">🍺</button>
                    </div>
                </div>
                
                <div class="room-layout">
                    <div class="game-modes-panel">
                        <h3>Játékmódok</h3>
                        <div class="game-mode-buttons" id="game-mode-buttons"></div>
                        <div class="admin-only-text" id="admin-only-notice" style="display: none;">
                            Csak a szoba admin tudja módosítani a játékmódokat.
                        </div>
                    </div>
                    
                    <div class="players-and-controls-panel">
                        <h3>Játékosok</h3>
                        <ul class="player-list-room" id="room-players-list"></ul>
                        <div class="admin-controls">
                            <button class="btn btn-success btn-full-width admin-only-item" id="start-game-btn">
                                Játék indítása
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Játék szekció -->
    <div id="game-section" class="page-section">
        <div class="app-header">
            <div class="user-info">
                <div id="game-user-avatar"><?php echo $user ? getUserAvatarHtml($user) : ''; ?></div>
                <span class="username-text" id="game-user-display-name"><?php echo $user ? htmlspecialchars($user['display_name']) : ''; ?></span>
            </div>
            <div class="game-stats-header">
                <span>Pontjaim: <strong id="game-my-points">0</strong> pont</span>
                <span class="stats-divider">|</span>
                <span>Kortyaim: <strong id="game-my-drinks">0</strong> korty</span>
                <button class="drink-icon" id="game-drink-btn" title="Iszom egyet">🍺</button>
            </div>
            <div class="header-actions">
                <button class="btn btn-danger admin-only-item" id="end-game-btn" style="display: none;">
                    Játék befejezése
                </button>
                <button class="btn btn-secondary" id="game-rules-btn">Szabályzat</button>
            </div>
        </div>
        
        <main class="container">
            <div class="game-main-container">
                <div class="card-area" id="card-area">
                    <h2 class="card-type" id="card-type">Kártya típus</h2>
                    <p class="card-text" id="card-text">Kártya szövege</p>
                    <div id="card-actions"></div>
                </div>
                
                <!-- Ranglista megjelenítés -->
                <div id="leaderboard-display" style="display: none;">
                    <div class="card-area">
                        <h2 class="card-type">Ranglista</h2>
                        <div id="leaderboard-content"></div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modalok -->
    
    <!-- Profil szerkesztő modal -->
    <div class="modal-overlay" id="profile-modal-overlay"></div>
    <div class="modal" id="profile-modal">
        <button class="close-modal-btn" id="close-profile-modal">&times;</button>
        <h2>Profil szerkesztése</h2>
        <form id="profile-form">
            <div class="form-group">
                <label>Jelenlegi avatár</label>
                <div class="avatar-display current-profile-avatar" id="current-profile-avatar"></div>
            </div>
            <div class="form-group">
                <label for="profile-display-name">Megjelenő név</label>
                <input type="text" id="profile-display-name" name="display_name" class="form-input" required>
            </div>
            <div class="form-group">
                <label for="profile-avatar-color">Avatár háttérszín</label>
                <input type="color" id="profile-avatar-color" name="avatar_color" class="form-input">
            </div>
            <div class="form-group">
                <label for="profile-avatar-upload">Új avatár feltöltése</label>
                <input type="file" id="profile-avatar-upload" accept="image/*" class="form-input">
            </div>
            <button type="submit" class="btn btn-primary btn-full-width">Mentés</button>
        </form>
    </div>

    <!-- Szabályzat modal -->
    <div class="modal-overlay" id="rules-modal-overlay"></div>
    <div class="modal" id="rules-modal" style="max-width: 800px;">
        <button class="close-modal-btn" id="close-rules-modal">&times;</button>
        <h2>Játékszabályzat</h2>
        <div id="rules-content">
            <h3>Felelsz vagy mersz</h3>
            <p>Válaszd ki, hogy teljesíted-e a feladatot. Ha igen, kapsz pontot. Ha nem, iszol és mínusz pontot kapsz.</p>
            
            <h3>Kire a legvalószínűbb</h3>
            <p>Minden játékos szavaz arra, kire igaz leginkább az állítás. A legtöbb szavazatot kapó játékos iszik.</p>
            
            <h3>Én még soha</h3>
            <p>Ha már csináltad, iszol. Ha még nem, pontot kapsz.</p>
            
            <h3>2 igazság 1 hazugság</h3>
            <p>Mondj 3 dolgot magadról: 2 igazat és 1 hamisat. A többiek találgatják, melyik a hazugság.</p>
            
            <h3>Trivia</h3>
            <p>Válaszolj helyesen a kérdésre pontért. Rossz válasz esetén iszol.</p>
            
            <h3>Bomba továbbadás</h3>
            <p>Add tovább a bombát másnak, mielőtt felrobban! Akinél felrobban, az iszik.</p>
            
            <h3>Taboo</h3>
            <p>Magyarázd el a szót a tilalmas kifejezések használata nélkül!</p>
        </div>
    </div>

    <!-- Statisztikák modal -->
    <div class="modal-overlay" id="stats-modal-overlay"></div>
    <div class="modal" id="stats-modal">
        <button class="close-modal-btn" id="close-stats-modal">&times;</button>
        <h2>Statisztikáim</h2>
        <div id="stats-content"></div>
    </div>

    <!-- Szoba létrehozás modal -->
    <div class="modal-overlay" id="create-room-modal-overlay"></div>
    <div class="modal" id="create-room-modal">
        <button class="close-modal-btn" id="close-create-room-modal">&times;</button>
        <h2>Új szoba létrehozása</h2>
        <form id="create-room-form">
            <div class="form-group">
                <label for="room-name">Szoba neve</label>
                <input type="text" id="room-name" name="room_name" class="form-input" required>
            </div>
            <div class="form-group">
                <label for="max-players">Maximum játékosok száma</label>
                <select id="max-players" name="max_players" class="form-input">
                    <option value="4">4 játékos</option>
                    <option value="6">6 játékos</option>
                    <option value="8" selected>8 játékos</option>
                    <option value="10">10 játékos</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-full-width">Szoba létrehozása</button>
        </form>
    </div>

    <script src="js/ajax.js"></script>
    <script src="js/main.js"></script>
    <script src="js/game.js"></script>
</body>
</html>