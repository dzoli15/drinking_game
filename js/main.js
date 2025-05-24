// Globális változók
let currentUser = null;
let currentRoom = null;
let gameState = null;
let roomUpdateInterval = null;
let gameUpdateInterval = null;

// DOM betöltés után
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

// Alkalmazás inicializálása
function initializeApp() {
    setupEventListeners();
    checkCurrentUser();
    
    // Ha van bejelentkezett felhasználó
    if (isUserLoggedIn()) {
        loadActiveRooms();
        startRoomPolling();
    }
}

// Event listener-ek beállítása
function setupEventListeners() {
    // Tab váltás (login/register)
    document.querySelectorAll('.tab-link').forEach(tab => {
        tab.addEventListener('click', (e) => {
            switchTab(e.target.dataset.tab);
        });
    });

    // Bejelentkezés
    document.getElementById('login-form').addEventListener('submit', handleLogin);
    
    // Regisztráció
    document.getElementById('register-form').addEventListener('submit', handleRegister);
    document.getElementById('register-display-name').addEventListener('input', updateAvatarPreview);
    document.getElementById('avatar-color-picker').addEventListener('change', updateAvatarPreview);
    document.getElementById('avatar-upload').addEventListener('change', handleAvatarUpload);

    // Lobby gombok
    document.getElementById('logout-btn').addEventListener('click', handleLogout);
    document.getElementById('profile-settings-btn').addEventListener('click', openProfileModal);
    document.getElementById('rules-btn').addEventListener('click', openRulesModal);
    document.getElementById('stats-btn').addEventListener('click', openStatsModal);
    document.getElementById('create-room-btn').addEventListener('click', openCreateRoomModal);

    // Szoba gombok
    document.getElementById('leave-room-btn').addEventListener('click', handleLeaveRoom);
    document.getElementById('room-profile-settings-btn').addEventListener('click', openProfileModal);
    document.getElementById('room-rules-btn').addEventListener('click', openRulesModal);
    document.getElementById('start-game-btn').addEventListener('click', handleStartGame);
    document.getElementById('drink-btn').addEventListener('click', handleDrinkAction);

    // Játék gombok
    document.getElementById('game-rules-btn').addEventListener('click', openRulesModal);
    document.getElementById('end-game-btn').addEventListener('click', handleEndGame);
    document.getElementById('game-drink-btn').addEventListener('click', handleGameDrinkAction);

    // Modal bezárások
    setupModalClosers();

    // Profil form
    document.getElementById('profile-form').addEventListener('submit', handleProfileUpdate);
    
    // Szoba létrehozás form
    document.getElementById('create-room-form').addEventListener('submit', handleCreateRoom);
}

// Modal bezáró gombok
function setupModalClosers() {
    const modals = [
        'profile-modal',
        'rules-modal', 
        'stats-modal',
        'create-room-modal'
    ];

    modals.forEach(modalId => {
        const overlay = document.getElementById(modalId + '-overlay');
        const closeBtn = document.getElementById('close-' + modalId.replace('-modal', '') + '-modal');
        
        if (overlay) {
            overlay.addEventListener('click', () => closeModal(modalId));
        }
        if (closeBtn) {
            closeBtn.addEventListener('click', () => closeModal(modalId));
        }
    });
}

// Felhasználó bejelentkezve van-e
function isUserLoggedIn() {
    return currentUser !== null;
}

// Jelenlegi felhasználó ellenőrzése
async function checkCurrentUser() {
    try {
        const response = await AjaxHelper.get('php/ajax/current_user.php');
        if (response.success && response.data) {
            currentUser = response.data;
            updateUserInterface();
        }
    } catch (error) {
        console.log('Nincs bejelentkezett felhasználó');
    }
}

// Felhasználói felület frissítése
function updateUserInterface() {
    if (currentUser) {
        // Avatár és név frissítése minden helyen
        const avatarElements = ['user-avatar', 'room-user-avatar', 'game-user-avatar'];
        const nameElements = ['user-display-name', 'room-user-display-name', 'game-user-display-name'];
        
        avatarElements.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.innerHTML = generateAvatarHtml(currentUser);
            }
        });
        
        nameElements.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = currentUser.display_name;
            }
        });

        showSection('lobby-section');
    } else {
        showSection('login-register-section');
    }
}

// Avatár HTML generálása
function generateAvatarHtml(user, size = 36) {
    const initial = user.display_name.charAt(0).toUpperCase();
    
    if (user.avatar_type === 'custom' && user.avatar_path) {
        return `<img src="${user.avatar_path}" alt="Avatar" style="width: ${size}px; height: ${size}px; object-fit: cover; border-radius: 50%;">`;
    } else {
        return `<div style="width: ${size}px; height: ${size}px; background-color: ${user.avatar_color}; color: white; display: flex; justify-content: center; align-items: center; font-size: ${Math.floor(size/2)}px; font-weight: bold; border-radius: 50%;">${initial}</div>`;
    }
}

// Szekció megjelenítése
function showSection(sectionId) {
    document.querySelectorAll('.page-section').forEach(section => {
        section.classList.remove('active-section');
    });
    document.getElementById(sectionId).classList.add('active-section');
    
    // Polling kezelése
    if (sectionId === 'lobby-section') {
        startRoomPolling();
        stopGamePolling();
    } else if (sectionId === 'room-section') {
        startRoomPolling();
        stopGamePolling();
    } else if (sectionId === 'game-section') {
        stopRoomPolling();
        startGamePolling();
    } else {
        stopRoomPolling();
        stopGamePolling();
    }
}

// Tab váltás
function switchTab(tabName) {
    document.querySelectorAll('.tab-link').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.panel').forEach(panel => {
        panel.classList.remove('active');
    });
    
    document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
    document.getElementById(`${tabName}-panel`).classList.add('active');
}

// Bejelentkezés kezelése
async function handleLogin(e) {
    e.preventDefault();
    
    const username = document.getElementById('login-username').value.trim();
    const errorDiv = document.getElementById('login-error');
    
    if (!username) {
        showError(errorDiv, 'Kérlek add meg a felhasználóneved!');
        return;
    }
    
    try {
        const response = await GameAPI.login(username);
        
        if (response.success) {
            currentUser = response.data;
            updateUserInterface();
            InfoBox.success('Sikeres bejelentkezés!');
        } else {
            showError(errorDiv, response.message);
        }
    } catch (error) {
        showError(errorDiv, error.message || 'Bejelentkezési hiba történt');
    }
}

// Regisztráció kezelése
async function handleRegister(e) {
    e.preventDefault();
    
    const formData = new FormData();
    const username = document.getElementById('register-username').value.trim();
    const displayName = document.getElementById('register-display-name').value.trim();
    const avatarType = document.getElementById('selected-avatar-type').value;
    const avatarColor = document.getElementById('selected-avatar-color').value;
    const avatarFile = document.getElementById('avatar-upload').files[0];
    const errorDiv = document.getElementById('register-error');
    
    if (!username || !displayName) {
        showError(errorDiv, 'Minden mező kitöltése kötelező!');
        return;
    }
    
    formData.append('username', username);
    formData.append('display_name', displayName);
    formData.append('avatar_type', avatarType);
    formData.append('avatar_color', avatarColor);
    
    if (avatarFile) {
        formData.append('avatar_file', avatarFile);
    }
    
    try {
        const response = await GameAPI.register(formData);
        
        if (response.success) {
            currentUser = response.data;
            updateUserInterface();
            InfoBox.success('Sikeres regisztráció!');
        } else {
            showError(errorDiv, response.message);
        }
    } catch (error) {
        showError(errorDiv, error.message || 'Regisztrációs hiba történt');
    }
}

// Kijelentkezés
async function handleLogout() {
    try {
        await GameAPI.logout();
        currentUser = null;
        currentRoom = null;
        gameState = null;
        stopRoomPolling();
        stopGamePolling();
        updateUserInterface();
        InfoBox.info('Sikeresen kijelentkeztél!');
    } catch (error) {
        InfoBox.error('Kijelentkezési hiba: ' + error.message);
    }
}

// Avatár előnézet frissítése
function updateAvatarPreview() {
    const displayName = document.getElementById('register-display-name').value.trim();
    const color = document.getElementById('avatar-color-picker').value;
    const preview = document.getElementById('default-avatar-preview');
    
    const initial = displayName ? displayName.charAt(0).toUpperCase() : 'A';
    preview.textContent = initial;
    preview.style.backgroundColor = color;
    
    document.getElementById('selected-avatar-color').value = color;
}

// Avatár feltöltés kezelése
function handleAvatarUpload(e) {
    const file = e.target.files[0];
    if (file) {
        document.getElementById('selected-avatar-type').value = 'custom';
        // Itt lehetne egy előnézetet is mutatni
    } else {
        document.getElementById('selected-avatar-type').value = 'default';
    }
}

// Hiba megjelenítése
function showError(errorDiv, message) {
    errorDiv.textContent = message;
    errorDiv.style.cssText = `
        color: var(--danger-color);
        margin-top: 10px;
        font-size: 14px;
        text-align: center;
    `;
}

// Modal megnyitása
function openModal(modalId) {
    const overlay = document.getElementById(modalId + '-overlay');
    const modal = document.getElementById(modalId);
    
    overlay.style.display = 'block';
    modal.style.display = 'block';
    
    setTimeout(() => {
        overlay.classList.add('active');
        modal.classList.add('active');
    }, 10);
}

// Modal bezárása
function closeModal(modalId) {
    const overlay = document.getElementById(modalId + '-overlay');
    const modal = document.getElementById(modalId);
    
    overlay.classList.remove('active');
    modal.classList.remove('active');
    
    setTimeout(() => {
        overlay.style.display = 'none';
        modal.style.display = 'none';
    }, 300);
}

// Profil modal megnyitása
async function openProfileModal() {
    try {
        const profileDisplayName = document.getElementById('profile-display-name');
        const profileAvatarColor = document.getElementById('profile-avatar-color');
        const currentProfileAvatar = document.getElementById('current-profile-avatar');
        
        profileDisplayName.value = currentUser.display_name;
        profileAvatarColor.value = currentUser.avatar_color;
        currentProfileAvatar.innerHTML = generateAvatarHtml(currentUser, 80);
        
        openModal('profile-modal');
    } catch (error) {
        InfoBox.error('Profil betöltési hiba: ' + error.message);
    }
}

// Szabályzat modal megnyitása
function openRulesModal() {
    openModal('rules-modal');
}

// Statisztikák modal megnyitása
async function openStatsModal() {
    try {
        const response = await GameAPI.getStats();
        if (response.success) {
            displayStats(response.data);
            openModal('stats-modal');
        }
    } catch (error) {
        InfoBox.error('Statisztikák betöltési hiba: ' + error.message);
    }
}

// Statisztikák megjelenítése
function displayStats(stats) {
    const content = document.getElementById('stats-content');
    const winRate = stats.games_played > 0 ? ((stats.games_won / stats.games_played) * 100).toFixed(1) : 0;
    
    content.innerHTML = `
        <p><strong>Játszott meccsek:</strong> ${stats.games_played}</p>
        <p><strong>Nyert meccsek:</strong> ${stats.games_won}</p>
        <p><strong>Nyerési arány:</strong> ${winRate}%</p>
        <p><strong>Összes pont:</strong> ${stats.total_points}</p>
        <p><strong>Legtöbb pont egy körben:</strong> ${stats.max_points_in_round}</p>
        <p><strong>Teljesített kártyák:</strong> ${stats.cards_completed}</p>
        <p><strong>Nem teljesített kártyák:</strong> ${stats.cards_failed}</p>
        <p><strong>Összes korty:</strong> ${stats.total_drinks}</p>
    `;
}

// Szoba létrehozás modal megnyitása
function openCreateRoomModal() {
    openModal('create-room-modal');
}

// Profil frissítése
async function handleProfileUpdate(e) {
    e.preventDefault();
    
    const formData = new FormData();
    const displayName = document.getElementById('profile-display-name').value.trim();
    const avatarColor = document.getElementById('profile-avatar-color').value;
    const avatarFile = document.getElementById('profile-avatar-upload').files[0];
    
    formData.append('display_name', displayName);
    formData.append('avatar_color', avatarColor);
    
    if (avatarFile) {
        formData.append('avatar_file', avatarFile);
    }
    
    try {
        const response = await GameAPI.updateProfile(formData);
        if (response.success) {
            currentUser = response.data;
            updateUserInterface();
            closeModal('profile-modal');
            InfoBox.success('Profil sikeresen frissítve!');
        }
    } catch (error) {
        InfoBox.error('Profil frissítési hiba: ' + error.message);
    }
}

// Szoba létrehozása
async function handleCreateRoom(e) {
    e.preventDefault();
    
    const roomName = document.getElementById('room-name').value.trim();
    const maxPlayers = parseInt(document.getElementById('max-players').value);
    
    console.log('Creating room:', { roomName, maxPlayers });
    
    if (!roomName) {
        InfoBox.error('Szoba név megadása kötelező!');
        return;
    }
    
    try {
        // Először teszteljük az egyszerű endpointot
        console.log('Testing simple endpoint...');
        const testResponse = await fetch('test_room_create.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ test: 'data' })
        });
        
        const testText = await testResponse.text();
        console.log('Test response:', testText);
        
        // Most próbáljuk a valódi kérést
        console.log('Making real room creation request...');
        const response = await GameAPI.createRoom({ 
            room_name: roomName, 
            max_players: maxPlayers 
        });
        
        console.log('Room creation response:', response);
        
        if (response.success) {
            currentRoom = response.data;
            closeModal('create-room-modal');
            showSection('room-section');
            loadRoomInfo();
            InfoBox.success('Szoba sikeresen létrehozva!');
        } else {
            InfoBox.error(response.message || 'Ismeretlen hiba');
        }
    } catch (error) {
        console.error('Room creation error:', error);
        InfoBox.error('Szoba létrehozási hiba: ' + error.message);
    }
}

// Aktív szobák betöltése
async function loadActiveRooms() {
    try {
        const response = await GameAPI.getActiveRooms();
        if (response.success) {
            displayActiveRooms(response.data);
        }
    } catch (error) {
        console.error('Aktív szobák betöltési hiba:', error);
    }
}

// Aktív szobák megjelenítése
function displayActiveRooms(rooms) {
    const roomsList = document.getElementById('rooms-list');
    
    if (rooms.length === 0) {
        roomsList.innerHTML = '<p style="text-align: center; color: var(--text-secondary-color);">Jelenleg nincsenek aktív szobák</p>';
        return;
    }
    
    roomsList.innerHTML = rooms.map(room => `
        <div class="room-item" style="background-color: var(--panel-bg-color); padding: 15px; margin-bottom: 10px; border-radius: var(--border-radius); border: 1px solid var(--border-color);">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h4 style="margin: 0 0 5px 0; color: var(--text-color);">${room.name}</h4>
                    <p style="margin: 0; color: var(--text-secondary-color); font-size: 0.9em;">
                        Admin: ${room.admin_name} | Játékosok: ${room.player_count}/${room.max_players}
                    </p>
                </div>
                <button class="btn btn-primary btn-small" onclick="joinRoom('${room.room_code}')">
                    Csatlakozás
                </button>
            </div>
        </div>
    `).join('');
}

// Szobához csatlakozás
async function joinRoom(roomCode) {
    try {
        const response = await GameAPI.joinRoom(roomCode);
        if (response.success) {
            currentRoom = response.data;
            showSection('room-section');
            loadRoomInfo();
            InfoBox.success('Sikeresen csatlakoztál a szobához!');
        }
    } catch (error) {
        InfoBox.error('Csatlakozási hiba: ' + error.message);
    }
}

// Szoba elhagyása
async function handleLeaveRoom() {
    try {
        const response = await GameAPI.leaveRoom();
        if (response.success) {
            currentRoom = null;
            showSection('lobby-section');
            loadActiveRooms();
            InfoBox.info('Elhagytad a szobát');
        }
    } catch (error) {
        InfoBox.error('Szoba elhagyási hiba: ' + error.message);
    }
}

// Szoba információk betöltése
async function loadRoomInfo() {
    try {
        const response = await GameAPI.getRoomInfo();
        if (response.success) {
            currentRoom = response.data.room;
            displayRoomInfo(response.data);
        }
    } catch (error) {
        console.error('Szoba információ betöltési hiba:', error);
    }
}

// Szoba információk megjelenítése
function displayRoomInfo(data) {
    const { room, players, gameModes, myStats } = data;
    
    // Szoba kód
    document.getElementById('current-room-code').textContent = room.room_code;
    
    // Saját statisztikák
    document.getElementById('my-points').textContent = myStats.points;
    document.getElementById('my-drinks').textContent = myStats.drinks;
    document.getElementById('game-my-points').textContent = myStats.points;
    document.getElementById('game-my-drinks').textContent = myStats.drinks;
    
    // Játékosok listája
    displayRoomPlayers(players, room.admin_id);
    
    // Játékmódok
    displayGameModes(gameModes, room.admin_id === currentUser.id);
    
    // Admin elemek megjelenítése/elrejtése
    const isAdmin = room.admin_id === currentUser.id;
    document.querySelectorAll('.admin-only-item').forEach(element => {
        element.style.display = isAdmin ? 'block' : 'none';
    });
    document.getElementById('admin-only-notice').style.display = isAdmin ? 'none' : 'block';
}

// Szoba játékosok megjelenítése
function displayRoomPlayers(players, adminId) {
    const playersList = document.getElementById('room-players-list');
    
    playersList.innerHTML = players.map(player => `
        <li class="player-item">
            <div class="player-info">
                <div class="player-avatar">${generateAvatarHtml(player, 32)}</div>
                <span class="player-name ${player.is_admin ? 'is-admin' : ''}">${player.display_name}</span>
                ${player.is_admin ? '<span style="color: var(--primary-color); font-size: 0.8em;">(Admin)</span>' : ''}
            </div>
            ${adminId === currentUser.id && !player.is_admin ? 
                `<button class="kick-player-btn" onclick="kickPlayer(${player.user_id})" title="Játékos kirúgása">&times;</button>` : ''
            }
        </li>
    `).join('');
}

// Játékmódok megjelenítése
function displayGameModes(gameModes, isAdmin) {
    const gameModesContainer = document.getElementById('game-mode-buttons');
    
    gameModesContainer.innerHTML = gameModes.map(mode => `
        <button class="game-mode-btn ${mode.is_enabled ? 'active' : ''}" 
                ${isAdmin ? '' : 'disabled'}
                onclick="${isAdmin ? `toggleGameMode(${mode.id}, ${!mode.is_enabled})` : ''}">
            ${mode.display_name}
        </button>
    `).join('');
}

// Játékmód váltása
async function toggleGameMode(gameModeId, enabled) {
    try {
        const response = await GameAPI.toggleGameMode(gameModeId, enabled);
        if (response.success) {
            loadRoomInfo(); // Frissítjük a szoba info-t
            InfoBox.info(`Játékmód ${enabled ? 'bekapcsolva' : 'kikapcsolva'}`);
        }
    } catch (error) {
        InfoBox.error('Játékmód váltási hiba: ' + error.message);
    }
}

// Játékos kirúgása
async function kickPlayer(userId) {
    try {
        const response = await GameAPI.kickPlayer(userId);
        if (response.success) {
            loadRoomInfo();
            InfoBox.warning('Játékos kirúgva');
        }
    } catch (error) {
        InfoBox.error('Kirúgási hiba: ' + error.message);
    }
}

// Játék indítása
async function handleStartGame() {
    try {
        const response = await GameAPI.startGame();
        if (response.success) {
            showSection('game-section');
            startGamePolling();
            InfoBox.success('Játék elindítva!');
        }
    } catch (error) {
        InfoBox.error('Játék indítási hiba: ' + error.message);
    }
}

// Ivás akció
async function handleDrinkAction() {
    try {
        const response = await GameAPI.drinkAction();
        if (response.success) {
            const drinksElement = document.getElementById('my-drinks');
            const currentDrinks = Math.max(0, parseInt(drinksElement.textContent) - 1);
            drinksElement.textContent = currentDrinks;
            document.getElementById('game-my-drinks').textContent = currentDrinks;
            InfoBox.info('Egészségére! 🍺');
        }
    } catch (error) {
        InfoBox.error('Ivás hiba: ' + error.message);
    }
}

// Játék ivás akció
async function handleGameDrinkAction() {
    await handleDrinkAction();
}

// Játék befejezése
async function handleEndGame() {
    if (confirm('Biztosan be szeretnéd fejezni a játékot?')) {
        try {
            const response = await GameAPI.endGame();
            if (response.success) {
                showSection('lobby-section');
                currentRoom = null;
                gameState = null;
                loadActiveRooms();
                InfoBox.info('Játék befejezve');
            }
        } catch (error) {
            InfoBox.error('Játék befejezési hiba: ' + error.message);
        }
    }
}

// Szoba polling indítása
function startRoomPolling() {
    if (roomUpdateInterval) return;
    
    roomUpdateInterval = setInterval(async () => {
        if (currentRoom && document.getElementById('room-section').classList.contains('active-section')) {
            await loadRoomInfo();
        }
        if (document.getElementById('lobby-section').classList.contains('active-section')) {
            await loadActiveRooms();
        }
    }, 2000);
}

// Szoba polling leállítása
function stopRoomPolling() {
    if (roomUpdateInterval) {
        clearInterval(roomUpdateInterval);
        roomUpdateInterval = null;
    }
}

// Játék polling indítása
function startGamePolling() {
    if (gameUpdateInterval) return;
    
    gameUpdateInterval = setInterval(async () => {
        if (document.getElementById('game-section').classList.contains('active-section')) {
            await updateGameState();
        }
    }, 1000);
}

// Játék polling leállítása
function stopGamePolling() {
    if (gameUpdateInterval) {
        clearInterval(gameUpdateInterval);
        gameUpdateInterval = null;
    }
}

// Játék állapot frissítése
async function updateGameState() {
    try {
        const response = await GameAPI.getGameState();
        if (response.success) {
            gameState = response.data;
            displayGameState(gameState);
        }
    } catch (error) {
        console.error('Játék állapot frissítési hiba:', error);
    }
}
