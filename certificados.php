<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

$scriptName = isset($_SERVER['SCRIPT_NAME']) ? (string)$_SERVER['SCRIPT_NAME'] : '';
$baseUrlPath = str_replace('\\', '/', dirname($scriptName));
if ($baseUrlPath === '/' || $baseUrlPath === '\\') {
    $baseUrlPath = '';
}
$baseUrlPath = rtrim($baseUrlPath, '/');

$normalize_pdf_url = static function ($value) use ($baseUrlPath): string {
    $path = trim((string)$value);
    if ($path === '') {
        return '';
    }
    if (preg_match('~^https?://~i', $path)) {
        return $path;
    }
    $path = str_replace('\\', '/', $path);
    if (str_starts_with($path, './')) {
        $path = substr($path, 2);
    }
    if ($baseUrlPath !== '' && str_starts_with($path, $baseUrlPath . '/')) {
        return $path;
    }
    $path = ltrim($path, '/');
    if ($baseUrlPath !== '') {
        return $baseUrlPath . '/' . $path;
    }
    return '/' . $path;
};

$q = isset($_GET['q']) ? (string)$_GET['q'] : '';
$q = trim($q);

if ($q === '') {
    json_response(['ok' => false, 'message' => 'Escribe un nombre o carnet.'], 400);
}

$conn = db();
$ciClean = normalize_ci($q);
$nameClean = normalize_name($q);

if ($ciClean !== '') {
    $stmt = $conn->prepare('SELECT pdf_path, nombre, ci FROM certificados WHERE ci_clean = ? ORDER BY uploaded_at DESC, id DESC LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $ciClean);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        if (is_array($row) && isset($row['pdf_path'])) {
            json_response([
                'ok' => true,
                'url' => $normalize_pdf_url($row['pdf_path']),
                'nombre' => $row['nombre'] ?? '',
                'ci' => $row['ci'] ?? '',
            ]);
        }
    }
}

if ($nameClean === '') {
    json_response(['ok' => false, 'message' => 'No encontramos tu certificado.'], 404);
}

$like = '%' . $nameClean . '%';
$stmt = $conn->prepare('SELECT pdf_path, nombre, ci FROM certificados WHERE nombre_clean LIKE ? ORDER BY uploaded_at DESC, id DESC LIMIT 1');
if (!$stmt) {
    json_response(['ok' => false, 'message' => 'Error de búsqueda.'], 500);
}

$stmt->bind_param('s', $like);
$stmt->execute();
$result = $stmt->get_result();
$row = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!is_array($row) || !isset($row['pdf_path'])) {
    json_response(['ok' => false, 'message' => 'No encontramos tu certificado.'], 404);
}

json_response([
    'ok' => true,
    'url' => $normalize_pdf_url($row['pdf_path']),
    'nombre' => $row['nombre'] ?? '',
    'ci' => $row['ci'] ?? '',
]);
