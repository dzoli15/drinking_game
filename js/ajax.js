// AJAX segédfüggvények - debug verzió
class AjaxHelper {
    static async makeRequest(url, method = 'GET', data = null) {
        console.log('AJAX Request:', { url, method, data });
        
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            }
        };

        if (data && method !== 'GET') {
            if (data instanceof FormData) {
                delete options.headers['Content-Type'];
                options.body = data;
            } else {
                options.body = JSON.stringify(data);
            }
        }

        try {
            const response = await fetch(url, options);
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            
            const responseText = await response.text();
            console.log('Raw response:', responseText);
            
            let result;
            try {
                result = JSON.parse(responseText);
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.error('Response text:', responseText);
                throw new Error('Szerver válasz nem JSON formátumban érkezett');
            }
            
            if (!response.ok) {
                throw new Error(result.message || 'Hiba történt');
            }
            
            return result;
        } catch (error) {
            console.error('AJAX hiba:', error);
            throw error;
        }
    }

    static async get(url, params = {}) {
        const urlParams = new URLSearchParams(params);
        const fullUrl = urlParams.toString() ? `${url}?${urlParams}` : url;
        return this.makeRequest(fullUrl, 'GET');
    }

    static async post(url, data = {}) {
        return this.makeRequest(url, 'POST', data);
    }

    static async put(url, data = {}) {
        return this.makeRequest(url, 'PUT', data);
    }

    static async delete(url) {
        return this.makeRequest(url, 'DELETE');
    }
}

// Specifikus API hívások
class GameAPI {
    // Autentikáció
    static async login(username) {
        return AjaxHelper.post('php/ajax/login.php', { username });
    }

    static async register(formData) {
        return AjaxHelper.post('php/ajax/register.php', formData);
    }

    static async logout() {
        return AjaxHelper.post('php/ajax/logout.php');
    }

    // Profil
    static async updateProfile(formData) {
        return AjaxHelper.post('php/ajax/update_profile.php', formData);
    }

    static async getStats() {
        return AjaxHelper.get('php/ajax/stats.php');
    }

    // Szobák
    static async createRoom(roomData) {
        return AjaxHelper.post('php/ajax/room_actions.php', { 
            action: 'create', 
            ...roomData 
        });
    }

    static async joinRoom(roomCode) {
        return AjaxHelper.post('php/ajax/room_actions.php', { 
            action: 'join', 
            room_code: roomCode 
        });
    }

    static async leaveRoom() {
        return AjaxHelper.post('php/ajax/room_actions.php', { 
            action: 'leave' 
        });
    }

    static async getRoomInfo() {
        return AjaxHelper.get('php/ajax/room_actions.php', { action: 'info' });
    }

    static async getActiveRooms() {
        return AjaxHelper.get('php/ajax/room_actions.php', { action: 'list' });
    }

    static async toggleGameMode(gameModeId, enabled) {
        return AjaxHelper.post('php/ajax/room_actions.php', { 
            action: 'toggle_mode', 
            game_mode_id: gameModeId, 
            enabled 
        });
    }

    static async kickPlayer(userId) {
        return AjaxHelper.post('php/ajax/room_actions.php', { 
            action: 'kick', 
            user_id: userId 
        });
    }

    static async startGame() {
        return AjaxHelper.post('php/ajax/room_actions.php', { 
            action: 'start_game' 
        });
    }

    // Játék
    static async getGameState() {
        return AjaxHelper.get('php/ajax/game_actions.php', { action: 'state' });
    }

    static async cardAction(action, data = {}) {
        return AjaxHelper.post('php/ajax/game_actions.php', { 
            action, 
            ...data 
        });
    }

    static async drinkAction() {
        return AjaxHelper.post('php/ajax/game_actions.php', { 
            action: 'drink' 
        });
    }

    static async endGame() {
        return AjaxHelper.post('php/ajax/game_actions.php', { 
            action: 'end_game' 
        });
    }
}

// Infobox üzenetek (marad ugyanaz)
class InfoBox {
    static container = null;

    static init() {
        this.container = document.getElementById('infobox-container');
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.id = 'infobox-container';
            this.container.style.cssText = `
                position: fixed;
                top: 80px;
                right: 20px;
                z-index: 1010;
                max-width: 300px;
            `;
            document.body.appendChild(this.container);
        }
    }

    static show(message, type = 'info', duration = 4000) {
        this.init();

        const infoBox = document.createElement('div');
        infoBox.className = `infobox infobox-${type}`;
        infoBox.style.cssText = `
            background-color: var(--panel-bg-color);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 12px 16px;
            margin-bottom: 10px;
            box-shadow: var(--box-shadow-medium);
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease-out;
            color: var(--text-color);
            font-size: 14px;
            line-height: 1.4;
        `;

        // Típus szerinti színezés
        switch (type) {
            case 'success':
                infoBox.style.borderLeftColor = 'var(--primary-color)';
                infoBox.style.borderLeftWidth = '4px';
                break;
            case 'error':
                infoBox.style.borderLeftColor = 'var(--danger-color)';
                infoBox.style.borderLeftWidth = '4px';
                break;
            case 'warning':
                infoBox.style.borderLeftColor = '#ff7b00';
                infoBox.style.borderLeftWidth = '4px';
                break;
            default:
                infoBox.style.borderLeftColor = 'var(--accent-blue)';
                infoBox.style.borderLeftWidth = '4px';
        }

        infoBox.textContent = message;
        this.container.appendChild(infoBox);

        // Animáció befelé
        setTimeout(() => {
            infoBox.style.opacity = '1';
            infoBox.style.transform = 'translateX(0)';
        }, 10);

        // Automatikus eltűnés
        setTimeout(() => {
            infoBox.style.opacity = '0';
            infoBox.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (infoBox.parentNode) {
                    infoBox.parentNode.removeChild(infoBox);
                }
            }, 300);
        }, duration);
    }

    static success(message, duration = 4000) {
        this.show(message, 'success', duration);
    }

    static error(message, duration = 5000) {
        this.show(message, 'error', duration);
    }

    static warning(message, duration = 4500) {
        this.show(message, 'warning', duration);
    }

    static info(message, duration = 4000) {
        this.show(message, 'info', duration);
    }
}
