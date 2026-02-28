<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$apiKey = '';
if (defined('GEMINI_API_KEY')) {
    $apiKey = (string) GEMINI_API_KEY;
}
if ($apiKey === '') {
    $apiKey = (string) getenv('GEMINI_API_KEY');
}
if ($apiKey === '') {
    $msg = mb_substr($message ?? '', 0, 260, 'UTF-8');
    $msg = trim($msg) !== '' ? $msg : 'tu consulta';
    $fallback = "Voy a responder sin conectarme al motor de IA.\n\nLo que me comentas es: \"{$msg}\".\n\nPuedo orientarte sobre cursos, metodología, certificados o cotización y darte ideas para reducir riesgos en ruta, organizar la capacitación y obtener evidencias. Cuéntame un poco más qué necesitas lograr.";
    json_response([
        'ok' => true,
        'text' => $fallback,
        'model' => null,
        'degraded' => true,
        'error' => 'missing_api_key',
    ], 200);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw === false ? '' : $raw, true);
if (!is_array($data)) {
    json_response(['ok' => false, 'error' => 'invalid_json'], 400);
}

$message = trim((string) ($data['message'] ?? ''));
if ($message === '' || mb_strlen($message, 'UTF-8') > 3000) {
    json_response(['ok' => false, 'error' => 'invalid_message'], 400);
}

$history = $data['history'] ?? [];
if (!is_array($history)) {
    $history = [];
}

$system = (string) ($data['system'] ?? '');
$systemText = trim($system) !== '' ? trim($system) : 'Eres un asistente virtual en español para el sitio web "On The Road To Safety". Responde con claridad y de forma breve.';

$contents = [];
foreach (array_slice($history, -20) as $item) {
    if (!is_array($item)) {
        continue;
    }
    $role = (string) ($item['role'] ?? '');
    $text = trim((string) ($item['content'] ?? ''));
    if ($text === '') {
        continue;
    }
    if ($role === 'assistant') {
        $contents[] = ['role' => 'model', 'parts' => [['text' => $text]]];
        continue;
    }
    if ($role === 'user') {
        $contents[] = ['role' => 'user', 'parts' => [['text' => $text]]];
        continue;
    }
}

$contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];

$models = ['gemini-3-flash-preview', 'gemini-1.5-flash', 'gemini-1.5-flash-8b'];
$lastError = ['type' => 'unknown', 'message' => ''];

foreach ($models as $model) {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($apiKey);

    $payload = [
        'systemInstruction' => [
            'parts' => [
                ['text' => $systemText],
            ],
        ],
        'contents' => $contents,
        'generationConfig' => [
            'temperature' => 0.4,
            'maxOutputTokens' => 480,
        ],
    ];

    $ch = curl_init($url);
    if ($ch === false) {
        json_response(['ok' => false, 'error' => 'curl_init_failed'], 500);
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        // En local (XAMPP) a veces falla SSL sin certificado configurado
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $resp = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) {
        $lastError = ['type' => 'request_failed', 'message' => $curlErr ?: ''];
        continue;
    }

    $json = json_decode($resp, true);
    if (!is_array($json)) {
        $lastError = ['type' => 'invalid_model_response', 'message' => ''];
        continue;
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $msg = '';
        if (isset($json['error']['message']) && is_string($json['error']['message'])) {
            $msg = $json['error']['message'];
        }
        $lastError = ['type' => 'model_error', 'message' => $msg];
        continue;
    }

    $text = '';
    $candidate = $json['candidates'][0]['content']['parts'] ?? null;
    if (is_array($candidate)) {
        foreach ($candidate as $part) {
            if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
                $text .= $part['text'];
            }
        }
    }
    $text = trim($text);

    if ($text === '') {
        $lastError = ['type' => 'empty_response', 'message' => ''];
        continue;
    }

    json_response(['ok' => true, 'text' => $text, 'model' => $model], 200);
}

// Modo degradado: si el modelo falla, devolvemos un mensaje útil sin romper el front
$details = '';
if (is_array($lastError)) {
    $t = isset($lastError['type']) ? (string)$lastError['type'] : '';
    $m = isset($lastError['message']) ? (string)$lastError['message'] : '';
    $parts = [];
    if ($t !== '') $parts[] = $t;
    if ($m !== '') $parts[] = $m;
    $details = implode(' - ', $parts);
}

$msg = mb_substr($message, 0, 260, 'UTF-8');
if (trim($msg) === '') {
    $msg = 'tu consulta';
}
$fallback = "Voy a responder sin conectarme al motor de IA.\n\nMe comentas: \"{$msg}\".\n\nPuedo ayudarte con cursos, metodología, certificados o cotización, y darte recomendaciones para mejorar seguridad vial, reducir incidentes y organizar la capacitación. Si buscas precios/horarios, te puedo guiar para gestionarlo por WhatsApp.";

json_response([
    'ok' => true,
    'text' => $fallback,
    'model' => null,
    'degraded' => true,
    'error' => $details,
], 200);
