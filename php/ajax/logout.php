<?php
require_once '../../config/session.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Csak POST kérések engedélyezettek', 405);
}

try {
    logout();
    sendSuccess('Sikeres kijelentkezés');
} catch (Exception $e) {
    sendError('Kijelentkezési hiba: ' . $e->getMessage(), 500);
}
?>