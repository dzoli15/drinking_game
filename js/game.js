// Játék állapot megjelenítése
function displayGameState(state) {
    if (!state || !state.current_card) {
        return;
    }

    const { current_card, my_stats, players, waiting_for_action } = state;
    
    // Kártya megjelenítése
    document.getElementById('card-type').textContent = current_card.game_mode_name;
    document.getElementById('card-text').textContent = current_card.content;
    
    // Statisztikák frissítése
    document.getElementById('game-my-points').textContent = my_stats.points;
    document.getElementById('game-my-drinks').textContent = my_stats.drinks;
    
    // Kártya akciók megjelenítése
    displayCardActions(current_card, waiting_for_action);
    
    // Ranglista kezelése
    if (state.show_leaderboard) {
        showLeaderboard(state.leaderboard, state.leaderboard_timer);
    } else {
        hideLeaderboard();
    }
}

// Kártya akciók megjelenítése
function displayCardActions(card, waitingForAction) {
    const actionsContainer = document.getElementById('card-actions');
    
    if (!waitingForAction) {
        actionsContainer.innerHTML = '<p style="color: var(--text-secondary-color);">Várakozás a többi játékosra...</p>';
        return;
    }
    
    switch (card.game_mode) {
        case 'truth_or_dare':
            actionsContainer.innerHTML = `
                <div style="display: flex; gap: 15px; justify-content: center; margin-top: 20px;">
                    <button class="btn btn-success" onclick="cardAction('complete')">Megcsináltam</button>
                    <button class="btn btn-danger" onclick="cardAction('fail')">Nem csináltam meg</button>
                </div>
            `;
            break;
            
        case 'most_likely':
            actionsContainer.innerHTML = generateMostLikelyActions(card.players);
            break;
            
        case 'never_have_i':
            actionsContainer.innerHTML = `
                <div style="display: flex; gap: 15px; justify-content: center; margin-top: 20px;">
                    <button class="btn btn-success" onclick="cardAction('complete')">Én már igen</button>
                    <button class="btn btn-danger" onclick="cardAction('fail')">Én még nem</button>
                </div>
            `;
            break;
            
        case 'two_truths_lie':
            if (card.is_my_turn) {
                actionsContainer.innerHTML = generateTwoTruthsLieInput();
            } else if (card.statements) {
                actionsContainer.innerHTML = generateTwoTruthsLieVoting(card.statements);
            } else {
                actionsContainer.innerHTML = '<p>Várakozás a játékos állításaira...</p>';
            }
            break;
            
        case 'trivia':
            actionsContainer.innerHTML = `
                <div style="margin-top: 20px;">
                    <input type="text" id="trivia-answer" class="form-input" placeholder="Válaszod..." style="margin-bottom: 15px;">
                    <button class="btn btn-primary btn-full-width" onclick="submitTriviaAnswer()">Válasz beküldése</button>
                </div>
            `;
            break;
            
        case 'hot_potato':
            actionsContainer.innerHTML = generateHotPotatoActions(card.players, card.timer);
            break;
            
        case 'taboo':
            actionsContainer.innerHTML = `
                <div style="display: flex; gap: 15px; justify-content: center; margin-top: 20px;">
                    <button class="btn btn-success" onclick="cardAction('complete')">Sikerült</button>
                    <button class="btn btn-danger" onclick="cardAction('fail')">Nem sikerült</button>
                </div>
            `;
            break;
            
        default:
            actionsContainer.innerHTML = '';
    }
}

// "Kire a legvalószínűbb" akciók generálása
function generateMostLikelyActions(players) {
    return `
        <div style="margin-top: 20px;">
            <select id="most-likely-select" class="form-input" style="margin-bottom: 15px;">
                <option value="">Válassz játékost...</option>
                ${players.map(player => `<option value="${player.id}">${player.display_name}</option>`).join('')}
            </select>
            <button class="btn btn-primary btn-full-width" onclick="submitMostLikelyVote()">Szavazom</button>
        </div>
    `;
}

// "2 igazság 1 hazugság" input generálása
function generateTwoTruthsLieInput() {
    return `
        <div style="margin-top: 20px;">
            <div class="form-group">
                <label>1. Igazság:</label>
                <input type="text" id="truth1" class="form-input" placeholder="Első igaz állítás...">
            </div>
            <div class="form-group">
                <label>2. Igazság:</label>
                <input type="text" id="truth2" class="form-input" placeholder="Második igaz állítás...">
            </div>
            <div class="form-group">
                <label>Hazugság:</label>
                <input type="text" id="lie" class="form-input" placeholder="Hamis állítás...">
            </div>
            <button class="btn btn-primary btn-full-width" onclick="submitTwoTruthsLie()">Beküldés</button>
        </div>
    `;
}

// "2 igazság 1 hazugság" szavazás generálása
function generateTwoTruthsLieVoting(statements) {
    return `
        <div style="margin-top: 20px;">
            <p style="margin-bottom: 15px; font-weight: bold;">Melyik a hazugság?</p>
            ${statements.map((statement, index) => `
                <div style="margin-bottom: 10px; display: flex; align-items: center; gap: 10px;">
                    <button class="btn btn-secondary" onclick="voteTwoTruthsLie(${index})" style="flex: 1; text-align: left;">
                        ${index + 1}. ${statement}
                    </button>
                </div>
            `).join('')}
        </div>
    `;
}

// "Bomba továbbadás" akciók generálása
function generateHotPotatoActions(players, timer) {
    return `
        <div style="margin-top: 20px; text-align: center;">
            <div style="font-size: 2em; color: var(--danger-color); margin-bottom: 15px;">
                ⏰ ${timer}s
            </div>
            <select id="hot-potato-select" class="form-input" style="margin-bottom: 15px;">
                <option value="">Válassz játékost...</option>
                ${players.map(player => `<option value="${player.id}">${player.display_name}</option>`).join('')}
            </select>
            <button class="btn btn-danger btn-full-width" onclick="passBomb()">Bomba továbbadása</button>
        </div>
    `;
}

// Kártya akció végrehajtása
async function cardAction(action, data = {}) {
    try {
        const response = await GameAPI.cardAction(action, data);
        if (response.success) {
            updateGameState();
        }
    } catch (error) {
        InfoBox.error('Akció hiba: ' + error.message);
    }
}

// "Kire a legvalószínűbb" szavazás
async function submitMostLikelyVote() {
    const selectedPlayer = document.getElementById('most-likely-select').value;
    if (!selectedPlayer) {
        InfoBox.warning('Válassz egy játékost!');
        return;
    }
    
    await cardAction('vote', { target_player_id: selectedPlayer });
}

// Trivia válasz beküldése
async function submitTriviaAnswer() {
    const answer = document.getElementById('trivia-answer').value.trim();
    if (!answer) {
        InfoBox.warning('Add meg a válaszod!');
        return;
    }
    
    await cardAction('answer', { answer: answer });
}

// "2 igazság 1 hazugság" beküldése
async function submitTwoTruthsLie() {
    const truth1 = document.getElementById('truth1').value.trim();
    const truth2 = document.getElementById('truth2').value.trim();
    const lie = document.getElementById('lie').value.trim();
    
    if (!truth1 || !truth2 || !lie) {
        InfoBox.warning('Minden mezőt töltsd ki!');
        return;
    }
    
    await cardAction('submit_statements', { 
        statements: [truth1, truth2, lie] 
    });
}

// "2 igazság 1 hazugság" szavazás
async function voteTwoTruthsLie(statementIndex) {
    await cardAction('vote', { statement_index: statementIndex });
}

// Bomba továbbadása
async function passBomb() {
    const selectedPlayer = document.getElementById('hot-potato-select').value;
    if (!selectedPlayer) {
        InfoBox.warning('Válassz egy játékost!');
        return;
    }
    
    await cardAction('pass', { target_player_id: selectedPlayer });
}

// Ranglista megjelenítése
function showLeaderboard(leaderboard, timer) {
    const leaderboardDisplay = document.getElementById('leaderboard-display');
    const cardArea = document.getElementById('card-area');
    const leaderboardContent = document.getElementById('leaderboard-content');
    
    cardArea.style.display = 'none';
    leaderboardDisplay.style.display = 'block';
    
    leaderboardContent.innerHTML = `
        <div style="margin-bottom: 20px;">
            <p style="color: var(--text-secondary-color);">Következő kártya: ${timer}s</p>
        </div>
        <div style="display: flex; flex-direction: column; gap: 10px;">
            ${leaderboard.map((player, index) => `
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px; background-color: var(--input-bg-color); border-radius: var(--border-radius);">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-weight: bold; color: ${index === 0 ? 'var(--primary-color)' : 'var(--text-color)'};">
                            ${index + 1}.
                        </span>
                        <div style="width: 24px; height: 24px;">
                            ${generateAvatarHtml(player, 24)}
                        </div>
                        <span>${player.display_name}</span>
                    </div>
                    <span style="font-weight: bold; color: var(--accent-blue);">
                        ${player.points} pont
                    </span>
                </div>
            `).join('')}
        </div>
    `;
}

// Ranglista elrejtése
function hideLeaderboard() {
    const leaderboardDisplay = document.getElementById('leaderboard-display');
    const cardArea = document.getElementById('card-area');
    
    leaderboardDisplay.style.display = 'none';
    cardArea.style.display = 'flex';
}
