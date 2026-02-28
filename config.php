<?php
declare(strict_types=1);

$local = __DIR__ . DIRECTORY_SEPARATOR . 'config.local.php';
if (is_file($local)) {
    require_once $local;
}

const DB_HOST = 'localhost';
const DB_NAME = 'ontheroadtosafety';
const DB_USER = 'root';
const DB_PASS = '';

if (!defined('GEMINI_API_KEY')) {
    $key = getenv('GEMINI_API_KEY');
    define('GEMINI_API_KEY', $key !== false ? (string) $key : '');
}
