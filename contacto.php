<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

$conn = db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Método no permitido.'], 405);
}

$correo = isset($_POST['correo']) ? trim((string)$_POST['correo']) : '';
if ($correo === '' || mb_strlen($correo, 'UTF-8') > 190) {
    json_response(['ok' => false, 'message' => 'Correo inválido.'], 400);
}
if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    json_response(['ok' => false, 'message' => 'Correo inválido.'], 400);
}

$ip = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '';
if (strlen($ip) > 45) {
    $ip = substr($ip, 0, 45);
}
$ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string)$_SERVER['HTTP_USER_AGENT'] : '';
if (mb_strlen($ua, 'UTF-8') > 255) {
    $ua = mb_substr($ua, 0, 255, 'UTF-8');
}

$stmt = $conn->prepare('INSERT INTO contactos (correo, ip, user_agent) VALUES (?, ?, ?)');
if (!$stmt) {
    json_response(['ok' => false, 'message' => 'No se pudo guardar.'], 500);
}
$stmt->bind_param('sss', $correo, $ip, $ua);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    json_response(['ok' => false, 'message' => 'No se pudo guardar.'], 500);
}

json_response(['ok' => true], 200);

