<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

auth_logout();
header('Location: ./index.html');
exit;

