<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

auth_start();

$conn = db();
$count = 0;
$res = $conn->query('SELECT COUNT(*) AS c FROM users');
if ($res) {
    $row = $res->fetch_assoc();
    $count = (int)($row['c'] ?? 0);
    $res->free();
}

if ($count > 0) {
    http_response_code(404);
    exit('No disponible.');
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['usuario']) ? trim((string)$_POST['usuario']) : '';
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
    $password2 = isset($_POST['password2']) ? (string)$_POST['password2'] : '';

    if ($username === '' || $password === '' || $password2 === '') {
        $message = 'Completa todos los campos.';
        $messageType = 'error';
    } elseif ($password !== $password2) {
        $message = 'Las contraseñas no coinciden.';
        $messageType = 'error';
    } elseif (mb_strlen($password, 'UTF-8') < 8) {
        $message = 'La contraseña debe tener al menos 8 caracteres.';
        $messageType = 'error';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $role = 'admin';
        $active = 1;
        $stmt = $conn->prepare('INSERT INTO users (username, password_hash, role, is_active) VALUES (?, ?, ?, ?)');
        if (!$stmt) {
            $message = 'No se pudo crear el administrador.';
            $messageType = 'error';
        } else {
            $stmt->bind_param('sssi', $username, $hash, $role, $active);
            $ok = $stmt->execute();
            $stmt->close();
            if (!$ok) {
                $message = 'No se pudo crear el administrador.';
                $messageType = 'error';
            } else {
                header('Location: ./login.php');
                exit;
            }
        }
    }
}

?>
<!doctype html>
<html lang="es">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="theme-color" content="#0b2a6f" />
    <title>Crear administrador | On The Road To Safety</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="./styles.css?v=20260216-7" />
  </head>
  <body>
    <a class="skip-link" href="#contenido">Saltar al contenido</a>

    <main id="contenido">
      <section class="section section-soft auth-section">
        <div class="container auth-container">
          <div class="card auth-card">
            <a class="auth-back" href="./index.html" aria-label="Volver al inicio">
              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path
                  d="M15 18l-6-6 6-6"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                />
              </svg>
            </a>

            <img class="auth-logo" src="./assets/img/logo.png" alt="On The Road To Safety" />
            <h1 class="auth-title">Crear administrador</h1>

            <form class="form auth-form" method="post" action="./admin-setup.php">
              <div class="field">
                <label for="usuario">Usuario</label>
                <input id="usuario" name="usuario" type="text" autocomplete="username" required placeholder="admin" />
              </div>

              <div class="field">
                <label for="password">Contraseña</label>
                <input id="password" name="password" type="password" autocomplete="new-password" required placeholder="Mínimo 8 caracteres" />
              </div>

              <div class="field">
                <label for="password2">Repetir contraseña</label>
                <input
                  id="password2"
                  name="password2"
                  type="password"
                  autocomplete="new-password"
                  required
                  placeholder="Repite la contraseña"
                />
              </div>

              <div class="form-actions auth-actions">
                <button class="btn btn-primary" type="submit">Crear admin</button>
                <a class="btn btn-secondary" href="./index.html">Cancelar</a>
              </div>

              <?php if ($message !== ''): ?>
                <p class="form-note" role="status" aria-live="polite" data-type="<?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>">
                  <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                </p>
              <?php else: ?>
                <p class="form-note" aria-hidden="true"></p>
              <?php endif; ?>
            </form>
          </div>
        </div>
      </section>
    </main>
  </body>
</html>

