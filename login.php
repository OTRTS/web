<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

auth_start();

$wantsStatus = isset($_GET['status']) && (string)$_GET['status'] === '1';
if ($wantsStatus) {
    $u = auth_user();
    if (!is_array($u)) {
        json_response(['loggedIn' => false], 200);
    }
    json_response(
        [
            'loggedIn' => true,
            'role' => (string)($u['role'] ?? ''),
            'username' => (string)($u['username'] ?? ''),
        ],
        200
    );
}

$current = auth_user();
if (is_array($current)) {
    header('Location: ./ver-certificados.php');
    exit;
}

$redirectRaw = isset($_GET['redirect']) ? (string)$_GET['redirect'] : '';
$redirectPath = safe_redirect_path($redirectRaw);

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['usuario']) ? trim((string)$_POST['usuario']) : '';
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';

    if ($username === '' || $password === '') {
        $message = 'Completa usuario y contraseña.';
        $messageType = 'error';
    } else {
        $conn = db();
        $stmt = $conn->prepare('SELECT id, username, password_hash, role, is_active FROM users WHERE username = ? LIMIT 1');
        if (!$stmt) {
            $message = 'No se pudo iniciar sesión.';
            $messageType = 'error';
        } else {
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();

            $hash = is_array($row) ? (string)($row['password_hash'] ?? '') : '';
            $role = is_array($row) ? (string)($row['role'] ?? '') : '';
            $isActive = is_array($row) ? (int)($row['is_active'] ?? 0) : 0;
            if ($role === 'staff') {
                $role = 'visitante';
            }

            if (!is_array($row) || $hash === '' || $isActive !== 1 || !password_verify($password, $hash)) {
                $message = 'Usuario o contraseña incorrectos.';
                $messageType = 'error';
            } else {
                auth_login((int)$row['id'], (string)$row['username'], $role);
                if ($redirectPath !== '') {
                    header('Location: ' . $redirectPath);
                    exit;
                }
                header('Location: ./ver-certificados.php');
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
    <title>Iniciar sesión | On The Road To Safety</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="./styles.css?v=20260216-12" />
  </head>
  <body>
    <a class="skip-link" href="#contenido">Saltar al contenido</a>

    <header class="site-header" id="inicio">
      <div class="container header-inner">
        <a class="brand" href="./index.html" aria-label="On The Road To Safety">
          <img class="brand-logo" src="./assets/img/logo.png" alt="On The Road To Safety" />
        </a>

        <div class="nav-area">
          <nav class="site-nav-primary" aria-label="Navegación principal">
            <a href="./index.html#inicio">
              <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
              </svg>
              Inicio
            </a>
            <a href="./index.html#cursos">
              <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 19.5V6a2 2 0 0 1 2-2h12v17H6a2 2 0 0 0-2 2v-.5z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                <path d="M8 8h7M8 12h7M8 16h7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
              </svg>
              Cursos
            </a>
            <a href="./Certificados/">
              <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M7 4h10a2 2 0 0 1 2 2v14H5V6a2 2 0 0 1 2-2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                <path d="M9 8h6M9 12h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
              </svg>
              Certificados
            </a>
            <a href="./index.html#cotizacion">
              <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M7 7h10v14H7z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                <path d="M9 3h6v4H9zM9 11h6M9 15h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
              </svg>
              Cotización
            </a>
            <a class="nav-cta" href="./index.html#contacto">
              <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M21 8v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                <path d="m21 8-9 6L3 8" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
              </svg>
              Contacto
            </a>
          </nav>

          <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav-more" aria-label="Abrir menú">
            <svg class="nav-toggle-icon" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M4 7h16M4 12h16M4 17h16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </svg>
          </button>

          <div class="nav-more" id="site-nav-more" role="dialog" aria-label="Menú" hidden>
            <a href="./login.php">
              <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M17 8V7a5 5 0 0 0-10 0v1" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                <path
                  d="M7 11h10a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-6a2 2 0 0 1 2-2Z"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linejoin="round"
                />
              </svg>
              Iniciar sesión
            </a>
            <a class="nav-admin" href="./ver-certificados.php">
              <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M7 4h10a2 2 0 0 1 2 2v14H5V6a2 2 0 0 1 2-2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                <path d="M8 9h8M8 13h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
              </svg>
              Ver certificados
            </a>
            <a href="./index.html#cotizacion">
              <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M7 7h10v14H7z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                <path d="M9 3h6v4H9zM9 11h6M9 15h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
              </svg>
              Cotización
            </a>
            <a class="nav-more-cta" href="./index.html#contacto">
              <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M21 8v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                <path d="m21 8-9 6L3 8" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
              </svg>
              Contacto
            </a>
          </div>
        </div>
      </div>
    </header>

    <main id="contenido">
      <section class="section section-soft auth-section">
        <div class="container auth-container">
          <div class="card auth-card">
            <a class="auth-back" href="#" aria-label="Volver atrás" onclick="history.back(); return false;">
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
            <h1 class="auth-title">Iniciar sesión</h1>

            <form class="form auth-form" method="post" action="./login.php<?= $redirectPath !== '' ? ('?redirect=' . rawurlencode($redirectPath)) : '' ?>">
              <div class="field">
                <label for="usuario">Usuario</label>
                <input id="usuario" name="usuario" type="text" autocomplete="username" required placeholder="Tu usuario" />
              </div>

              <div class="field">
                <label for="password">Contraseña</label>
                <input
                  id="password"
                  name="password"
                  type="password"
                  autocomplete="current-password"
                  required
                  placeholder="Tu contraseña"
                />
              </div>

              <div class="form-actions auth-actions">
                <button class="btn btn-primary" type="submit">Ingresar</button>
                <a class="btn-link auth-home-link" href="./index.html">Volver al inicio</a>
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
    <script src="./script.js?v=20260216-5"></script>
  </body>
</html>
