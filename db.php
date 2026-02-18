<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): mysqli
{
    static $conn = null;
    if ($conn instanceof mysqli) {
        return $conn;
    }

    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_errno) {
        http_response_code(500);
        exit('Error de base de datos');
    }

    $conn->set_charset('utf8mb4');

    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS certificados (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(190) NOT NULL,
  nombre_clean VARCHAR(190) NOT NULL,
  ci VARCHAR(64) NOT NULL,
  ci_clean VARCHAR(64) NOT NULL,
  pdf_path VARCHAR(255) NOT NULL,
  original_filename VARCHAR(255) NOT NULL,
  uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ci_clean (ci_clean),
  KEY idx_nombre_clean (nombre_clean),
  KEY idx_uploaded_at (uploaded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

    $conn->query($sql);

    $sqlUsers = <<<SQL
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(80) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','visitante') NOT NULL DEFAULT 'visitante',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_username (username),
  KEY idx_role (role),
  KEY idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

    $conn->query($sqlUsers);
    $conn->query("ALTER TABLE users MODIFY role ENUM('admin','staff','visitante') NOT NULL DEFAULT 'visitante'");
    $conn->query("UPDATE users SET role = 'visitante' WHERE role = 'staff'");
    $conn->query("ALTER TABLE users MODIFY role ENUM('admin','visitante') NOT NULL DEFAULT 'visitante'");

    $sqlQuotes = <<<SQL
CREATE TABLE IF NOT EXISTS cotizaciones (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  empresa VARCHAR(190) NOT NULL,
  cargo VARCHAR(190) NOT NULL DEFAULT '',
  nombre VARCHAR(190) NOT NULL,
  correo VARCHAR(190) NOT NULL DEFAULT '',
  telefono VARCHAR(64) NOT NULL DEFAULT '',
  conductores INT UNSIGNED NULL,
  curso VARCHAR(190) NOT NULL DEFAULT '',
  ciudad VARCHAR(120) NOT NULL DEFAULT '',
  mensaje TEXT NOT NULL,
  status ENUM('new','seen') NOT NULL DEFAULT 'new',
  ip VARCHAR(45) NOT NULL DEFAULT '',
  user_agent VARCHAR(255) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  seen_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_status_created (status, created_at),
  KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

    $conn->query($sqlQuotes);

    $sqlContacts = <<<SQL
CREATE TABLE IF NOT EXISTS contactos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  correo VARCHAR(190) NOT NULL,
  ip VARCHAR(45) NOT NULL DEFAULT '',
  user_agent VARCHAR(255) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_correo_created (correo, created_at),
  KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

    $conn->query($sqlContacts);

    $seedUser = 'JHON025';
    $seedPassword = 'ADMIN';
    $seedRole = 'admin';
    $seedActive = 1;
    $check = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    if ($check) {
        $check->bind_param('s', $seedUser);
        $check->execute();
        $row = $check->get_result()->fetch_assoc();
        $check->close();

        if (!is_array($row)) {
            $hash = password_hash($seedPassword, PASSWORD_DEFAULT);
            $ins = $conn->prepare('INSERT INTO users (username, password_hash, role, is_active) VALUES (?, ?, ?, ?)');
            if ($ins) {
                $ins->bind_param('sssi', $seedUser, $hash, $seedRole, $seedActive);
                $ins->execute();
                $ins->close();
            }
        }
    }

    return $conn;
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function normalize_name(string $value): string
{
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    $value = mb_strtolower($value, 'UTF-8');
    $trans = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if ($trans !== false) {
        $value = $trans;
    }
    $value = preg_replace('/[^a-z0-9\s]+/i', ' ', $value) ?? '';
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    return $value;
}

function normalize_ci(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/u', '', $value) ?? '';
    $value = preg_replace('/[^a-z0-9]+/i', '', $value) ?? '';
    return strtoupper($value);
}
