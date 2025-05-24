-- Adatbázis létrehozása
CREATE DATABASE IF NOT EXISTS drinking_game CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE drinking_game;

-- Felhasználók tábla
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    avatar_type ENUM('default', 'custom') DEFAULT 'default',
    avatar_path VARCHAR(255) DEFAULT NULL,
    avatar_color VARCHAR(7) DEFAULT '#58a6ff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Szobák tábla
CREATE TABLE rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_code VARCHAR(10) UNIQUE NOT NULL,
    admin_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    max_players INT DEFAULT 8,
    is_active BOOLEAN DEFAULT TRUE,
    is_game_started BOOLEAN DEFAULT FALSE,
    current_card_id INT DEFAULT NULL,
    current_player_turn INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Szoba játékosok
CREATE TABLE room_players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    user_id INT NOT NULL,
    points INT DEFAULT 0,
    drinks INT DEFAULT 0,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_room_player (room_id, user_id)
);

-- Játékmódok
CREATE TABLE game_modes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    difficulty_points INT DEFAULT 1
);

-- Szoba engedélyezett játékmódok
CREATE TABLE room_game_modes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    game_mode_id INT NOT NULL,
    is_enabled BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (game_mode_id) REFERENCES game_modes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_room_mode (room_id, game_mode_id)
);

-- Kártyák
CREATE TABLE cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_mode_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    difficulty INT DEFAULT 1,
    points INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_mode_id) REFERENCES game_modes(id) ON DELETE CASCADE
);

-- Játék history
CREATE TABLE games (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    winner_id INT DEFAULT NULL,
    total_cards_played INT DEFAULT 0,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (winner_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Játékos statisztikák
CREATE TABLE player_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    games_played INT DEFAULT 0,
    games_won INT DEFAULT 0,
    total_points INT DEFAULT 0,
    total_drinks INT DEFAULT 0,
    max_points_in_round INT DEFAULT 0,
    cards_completed INT DEFAULT 0,
    cards_failed INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Kártya statisztikák
CREATE TABLE card_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    card_id INT NOT NULL,
    times_drawn INT DEFAULT 0,
    times_completed INT DEFAULT 0,
    times_failed INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_card (user_id, card_id)
);

-- Játék akciók (bomba továbbadás, szavazások stb.)
CREATE TABLE game_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    card_id INT NOT NULL,
    user_id INT NOT NULL,
    action_type ENUM('vote', 'answer', 'pass', 'complete', 'fail') NOT NULL,
    action_data JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Játékmódok beszúrása
INSERT INTO game_modes (name, display_name, description, difficulty_points) VALUES
('truth_or_dare', 'Felelsz vagy mersz', 'Válaszd ki, hogy teljesíted-e a feladatot', 2),
('most_likely', 'Kire a legvalószínűbb', 'Szavazz arra, kire igaz leginkább az állítás', 3),
('never_have_i', 'Én még soha', 'Ha csináltad már, igyál!', 1),
('two_truths_lie', '2 igazság 1 hazugság', 'Találd ki, melyik az álhír!', 5),
('trivia', 'Trivia', 'Válaszolj a kérdésre!', 4),
('hot_potato', 'Bomba továbbadás', 'Add tovább, mielőtt felrobban!', 3),
('taboo', 'Taboo', 'Magyarázd el a szót tilalmas kifejezések nélkül!', 4);

-- Kártyák beszúrása
-- Felelsz vagy mersz kártyák
INSERT INTO cards (game_mode_id, title, content, difficulty, points) VALUES
(1, 'Tánc kihívás', 'Táncolj 30 másodpercig a kedvenc zenédre!', 1, 2),
(1, 'Utánzás mester', 'Utánozz egy híresség hangját 1 percig!', 2, 2),
(1, 'Vicces poén', 'Mondj el egy viccet, amitől legalább 2 játékos nevet!', 2, 2),
(1, 'Kihívás fogadás', 'Csinálj 10 fekvőtámaszt!', 1, 2),
(1, 'Karaoke sztár', 'Énekelj el egy dalt teljes erőből!', 2, 3);

-- Kire a legvalószínűbb kártyák
INSERT INTO cards (game_mode_id, title, content, difficulty, points) VALUES
(2, 'Kalandvágyó', 'Kire a legvalószínűbb, hogy bungee jumpingot próbálna ki?', 1, 3),
(2, 'Party állat', 'Kire a legvalószínűbb, hogy hajnal 6-ig bulizna?', 1, 3),
(2, 'Gourmet chef', 'Kire a legvalószínűbb, hogy profi konyhafőnök lenne?', 2, 3),
(2, 'Technológiai guru', 'Kire a legvalószínűbb, hogy a legújabb gadgeteket birtokolja?', 2, 3),
(2, 'Sportoló típus', 'Kire a legvalószínűbb, hogy olimpiai bajnok lenne?', 1, 3);

-- Én még soha kártyák
INSERT INTO cards (game_mode_id, title, content, difficulty, points) VALUES
(3, 'Utazási kaland', 'Én még soha nem repültem külföldre.', 1, 1),
(3, 'Extrém sport', 'Én még soha nem próbáltam extrémsportot.', 2, 1),
(3, 'Gasztronómiai felfedezés', 'Én még soha nem ettem egzotikus ételt.', 1, 1),
(3, 'Éjszakai kaland', 'Én még soha nem maradtam fenn egész éjjel.', 1, 1),
(3, 'Kreatív hobby', 'Én még soha nem festettem vagy rajzoltam művészi célból.', 2, 2);

-- 2 igazság 1 hazugság kártyák
INSERT INTO cards (game_mode_id, title, content, difficulty, points) VALUES
(4, 'Személyes titkok', 'Mesélj 3 dolgot magadról: 2 igaz, 1 hamis!', 3, 5),
(4, 'Gyerekkori emlékek', 'Mondj 3 gyerekkori történetet: 2 igaz, 1 kitalált!', 3, 5),
(4, 'Hobbik és érdeklődés', 'Sorolj fel 3 hobbit: 2 amit csinálsz, 1 amit nem!', 2, 5),
(4, 'Utazási élmények', '3 hely ahol jártál (vagy nem): 2 igaz, 1 kitalált!', 3, 5),
(4, 'Képességek és talentumok', '3 dolog amit tudsz csinálni: 2 igaz, 1 hamis!', 2, 5);

-- Trivia kártyák
INSERT INTO cards (game_mode_id, title, content, difficulty, points) VALUES
(5, 'Földrajz', 'Melyik a világ legnagyobb óceánja?', 2, 4),
(5, 'Történelem', 'Melyik évben ért véget a második világháború?', 2, 4),
(5, 'Tudomány', 'Mi a víz vegyjele?', 1, 3),
(5, 'Sport', 'Hány játékos van egy kosárlabda csapatban a pályán?', 2, 4),
(5, 'Művészet', 'Ki festette a Mona Lisát?', 1, 3);

-- Bomba továbbadás kártyák
INSERT INTO cards (game_mode_id, title, content, difficulty, points) VALUES
(6, 'Időzített bomba', 'A bomba 10-30 másodperc múlva robban! Add tovább gyorsan!', 1, 3),
(6, 'Gyors döntés', 'Gyorsan add tovább a bombát, mielőtt felrobban!', 1, 3),
(6, 'Szerencse próba', 'Ez a bomba hamarosan felrobban! Továbbadás!', 1, 3),
(6, 'Veszélyes teher', 'A bomba ketyeg... Add tovább másnak!', 1, 3),
(6, 'Forró krumpli', 'Ez a bomba nagyon instabil! Gyorsan tovább!', 1, 3);

-- Taboo kártyák
INSERT INTO cards (game_mode_id, title, content, difficulty, points) VALUES
(7, 'Állat - Kutya', 'Magyarázd el ezt: KUTYA\nTilalmas szavak: ugat, házőrző, hűséges, állat', 2, 4),
(7, 'Étel - Pizza', 'Magyarázd el ezt: PIZZA\nTilalmas szavak: olasz, sajt, tészta, kerek', 2, 4),
(7, 'Tárgy - Telefon', 'Magyarázd el ezt: TELEFON\nTilalmas szavak: hív, mobil, beszél, kommunikáció', 3, 4),
(7, 'Tevékenység - Úszás', 'Magyarázd el ezt: ÚSZÁS\nTilalmas szavak: víz, medence, sport, úszik', 2, 4),
(7, 'Hely - Iskola', 'Magyarázd el ezt: ISKOLA\nTilalmas szavak: tanul, diák, tanár, oktatás', 2, 4);

-- Alapértelmezett admin felhasználó
INSERT INTO users (username, display_name, avatar_type, avatar_color) VALUES
('admin', 'Admin', 'default', '#2ea043');

-- Alapértelmezett statisztikák
INSERT INTO player_stats (user_id) VALUES (1);
