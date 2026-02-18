<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$conn = db();

$isPublicPost = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['public']);
if ($isPublicPost) {
    $readStr = static function (string $key, int $maxLen): string {
        $value = isset($_POST[$key]) ? trim((string)$_POST[$key]) : '';
        if ($value === '') {
            return '';
        }
        if (mb_strlen($value, 'UTF-8') > $maxLen) {
            $value = mb_substr($value, 0, $maxLen, 'UTF-8');
        }
        return $value;
    };

    $honeypot = $readStr('website', 120);
    if ($honeypot !== '') {
        json_response(['ok' => true], 200);
    }

    $empresa = $readStr('empresa', 190);
    $cargo = $readStr('cargo', 190);
    $nombre = $readStr('nombre', 190);
    $correo = $readStr('correo', 190);
    $telefono = $readStr('telefono', 64);
    $curso = $readStr('curso', 190);
    $ciudad = $readStr('ciudad', 120);
    $mensaje = $readStr('mensaje', 4000);

    $conductores = null;
    if (isset($_POST['conductores'])) {
        $raw = trim((string)$_POST['conductores']);
        if ($raw !== '' && ctype_digit($raw)) {
            $n = (int)$raw;
            if ($n > 0) {
                $conductores = $n;
            }
        }
    }

    if ($empresa === '' || $nombre === '' || $correo === '') {
        json_response(['ok' => false, 'message' => 'Completa Empresa, Nombre y Correo.'], 400);
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

    $stmt = $conn->prepare('INSERT INTO cotizaciones (empresa, cargo, nombre, correo, telefono, conductores, curso, ciudad, mensaje, ip, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        json_response(['ok' => false, 'message' => 'No se pudo guardar.'], 500);
    }
    $stmt->bind_param(
        'sssssisssss',
        $empresa,
        $cargo,
        $nombre,
        $correo,
        $telefono,
        $conductores,
        $curso,
        $ciudad,
        $mensaje,
        $ip,
        $ua
    );
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        json_response(['ok' => false, 'message' => 'No se pudo guardar.'], 500);
    }

    json_response(['ok' => true], 200);
}

$currentUser = require_admin();

if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf']) || $_SESSION['csrf'] === '') {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = (string)$_SESSION['csrf'];

$redirect = static function (array $params = []): void {
    $base = 'cotizaciones.php';
    if ($params) {
        $base .= '?' . http_build_query($params);
    }
    header('Location: ' . $base);
    exit;
};

if (isset($_GET['count'])) {
    $row = $conn->query("SELECT COUNT(*) AS c FROM cotizaciones WHERE status = 'new'")->fetch_assoc();
    $count = is_array($row) && isset($row['c']) ? (int)$row['c'] : 0;
    json_response(['count' => $count], 200);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf']) ? (string)$_POST['csrf'] : '';
    if (!hash_equals($csrf, $token)) {
        $redirect(['status' => 'error', 'msg' => 'Sesión expirada. Intenta de nuevo.']);
    }

    $action = isset($_POST['action']) ? (string)$_POST['action'] : '';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($action === 'mark_seen') {
        if ($id <= 0) {
            $redirect(['status' => 'error', 'msg' => 'ID inválido.']);
        }
        $stmt = $conn->prepare("UPDATE cotizaciones SET status = 'seen', seen_at = NOW() WHERE id = ?");
        if (!$stmt) {
            $redirect(['status' => 'error', 'msg' => 'No se pudo actualizar.']);
        }
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            $redirect(['status' => 'error', 'msg' => 'No se pudo actualizar.']);
        }
        $redirect(['status' => 'success', 'msg' => 'Marcado como visto.']);
    }

    if ($action === 'mark_all_seen') {
        $ok = $conn->query("UPDATE cotizaciones SET status = 'seen', seen_at = NOW() WHERE status = 'new'");
        if (!$ok) {
            $redirect(['status' => 'error', 'msg' => 'No se pudo actualizar.']);
        }
        $redirect(['status' => 'success', 'msg' => 'Todo marcado como visto.']);
    }

    if ($action === 'delete') {
        if ($id <= 0) {
            $redirect(['status' => 'error', 'msg' => 'ID inválido.']);
        }
        $stmt = $conn->prepare('DELETE FROM cotizaciones WHERE id = ?');
        if (!$stmt) {
            $redirect(['status' => 'error', 'msg' => 'No se pudo eliminar.']);
        }
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            $redirect(['status' => 'error', 'msg' => 'No se pudo eliminar.']);
        }
        $redirect(['status' => 'success', 'msg' => 'Cotización eliminada.']);
    }
}

$message = '';
$messageType = '';
if (isset($_GET['status'], $_GET['msg']) && is_string($_GET['status']) && is_string($_GET['msg'])) {
    $messageType = $_GET['status'] === 'success' ? 'success' : 'error';
    $message = trim($_GET['msg']);
}

$rows = [];
$res = $conn->query("SELECT id, empresa, cargo, nombre, correo, telefono, conductores, curso, ciudad, mensaje, status, created_at, seen_at FROM cotizaciones ORDER BY (status = 'new') DESC, created_at DESC");
if ($res instanceof mysqli_result) {
    $rows = $res->fetch_all(MYSQLI_ASSOC);
}

?><!doctype html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Cotizaciones | On The Road To Safety</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800;900&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="./styles.css?v=20260216-6" />
    <link rel="icon" href="./favicon.ico" />
  </head>
  <body>
    <header class="site-header" id="inicio">
      <div class="container header-inner">
        <a class="brand" href="./index.html">
          <img class="brand-logo" src="./assets/img/logo.png" alt="On The Road To Safety" />
        </a>

        <div class="nav-area">
          <nav class="site-nav-primary nav-links">
            <a href="./index.html">
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
            <a class="nav-admin" href="./registrar-certificado.php">
              <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M7 4h10a2 2 0 0 1 2 2v14H5V6a2 2 0 0 1 2-2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                <path d="M12 8v8M8 12h8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
              </svg>
              Registrar certificado
            </a>
            <a class="nav-admin" href="./ver-certificados.php">
              <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M7 4h10a2 2 0 0 1 2 2v14H5V6a2 2 0 0 1 2-2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                <path d="M8 9h8M8 13h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
              </svg>
              Ver certificados
            </a>
            <a class="nav-admin" href="./usuarios.php">
              <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                <path d="M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                <path d="M22 21v-2a4 4 0 0 0-3-3.87" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                <path d="M16 3.13a4 4 0 0 1 0 7.75" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
              </svg>
              Usuarios
            </a>
            <a class="nav-admin" href="./cotizaciones.php">
              <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M18 8a6 6 0 1 0-12 0c0 7-3 7-3 7h18s-3 0-3-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                <path d="M13.73 21a2 2 0 0 1-3.46 0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
              </svg>
              Cotizaciones
              <span class="nav-badge js-cotizaciones-badge" hidden>0</span>
            </a>
          </nav>

          <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav-more" aria-label="Abrir menú">
            <svg class="nav-toggle-icon" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M4 7h16M4 12h16M4 17h16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </svg>
          </button>

          <div class="nav-more" id="site-nav-more" role="dialog" aria-label="Menú" hidden>
            <a href="./logout.php">
              <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M17 8V7a5 5 0 0 0-10 0v1" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                <path d="M7 11h10a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-6a2 2 0 0 1 2-2Z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
              </svg>
              Cerrar sesión
            </a>
            <a href="./index.html#cursos">
              <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 19.5V6a2 2 0 0 1 2-2h12v17H6a2 2 0 0 0-2 2v-.5z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                <path d="M8 8h7M8 12h7M8 16h7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
              </svg>
              Cursos
            </a>
            <a class="nav-admin" href="./registrar-certificado.php">
              <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M7 4h10a2 2 0 0 1 2 2v14H5V6a2 2 0 0 1 2-2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                <path d="M12 8v8M8 12h8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
              </svg>
              Registrar certificado
            </a>
            <a class="nav-admin" href="./ver-certificados.php">
              <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M7 4h10a2 2 0 0 1 2 2v14H5V6a2 2 0 0 1 2-2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                <path d="M8 9h8M8 13h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
              </svg>
              Ver certificados
            </a>
            <a class="nav-admin" href="./usuarios.php">
              <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                <path d="M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                <path d="M22 21v-2a4 4 0 0 0-3-3.87" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                <path d="M16 3.13a4 4 0 0 1 0 7.75" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
              </svg>
              Usuarios
            </a>
            <a class="nav-admin" href="./cotizaciones.php">
              <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M18 8a6 6 0 1 0-12 0c0 7-3 7-3 7h18s-3 0-3-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                <path d="M13.73 21a2 2 0 0 1-3.46 0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
              </svg>
              Cotizaciones
              <span class="nav-badge js-cotizaciones-badge" hidden>0</span>
            </a>
          </div>
        </div>
      </div>
    </header>

    <main id="contenido">
      <section class="section section-soft">
        <div class="container">
          <div class="page-crumb reveal" data-reveal="left">
            <a href="./Certificados/">Panel</a>
            <span class="crumb-sep" aria-hidden="true">›</span>
            <span>Cotizaciones</span>
          </div>

          <div class="page-head reveal" data-reveal="left">
            <h1 class="page-title">Cotizaciones</h1>
            <p class="muted">Usuario: <?= htmlspecialchars((string)($currentUser['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
          </div>

          <?php if ($message !== ''): ?>
            <div class="form-note" data-type="<?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>">
              <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
          <?php endif; ?>

          <div class="card reveal" data-reveal="left" style="padding:16px">
            <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>" />
              <input type="hidden" name="action" value="mark_all_seen" />
              <button class="btn btn-primary" type="submit">Marcar todo como visto</button>
            </form>
          </div>

          <div class="card reveal" data-reveal="left" style="margin-top:14px;padding:16px;overflow:auto">
            <?php if (!$rows): ?>
              <p class="muted" style="margin:0">No hay cotizaciones todavía.</p>
            <?php else: ?>
              <table class="data-table">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Empresa</th>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Teléfono</th>
                    <th>Curso</th>
                    <th>Ciudad</th>
                    <th>Unidades</th>
                    <th>Fecha</th>
                    <th>Estado</th>
                    <th>Mensaje</th>
                    <th>Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($rows as $r): ?>
                    <?php
                      $id = (int)($r['id'] ?? 0);
                      $status = (string)($r['status'] ?? '');
                      $isNew = $status === 'new';
                      $created = (string)($r['created_at'] ?? '');
                      $empresa = (string)($r['empresa'] ?? '');
                      $nombre = (string)($r['nombre'] ?? '');
                      $correo = (string)($r['correo'] ?? '');
                      $telefono = (string)($r['telefono'] ?? '');
                      $curso = (string)($r['curso'] ?? '');
                      $ciudad = (string)($r['ciudad'] ?? '');
                      $conductores = isset($r['conductores']) ? (string)$r['conductores'] : '';
                      $mensaje = (string)($r['mensaje'] ?? '');
                    ?>
                    <tr>
                      <td><?= $id ?></td>
                      <td><?= htmlspecialchars($empresa, ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= htmlspecialchars($correo, ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= htmlspecialchars($telefono, ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= htmlspecialchars($curso, ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= htmlspecialchars($ciudad, ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= htmlspecialchars($conductores, ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= htmlspecialchars($created, ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= $isNew ? 'Nueva' : 'Vista' ?></td>
                      <td>
                        <details>
                          <summary>Ver</summary>
                          <pre style="white-space:pre-wrap;margin:10px 0 0"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></pre>
                        </details>
                      </td>
                      <td>
                        <div class="data-actions">
                          <?php if ($isNew): ?>
                            <form method="post" style="display:inline-flex;gap:8px;align-items:center">
                              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>" />
                              <input type="hidden" name="action" value="mark_seen" />
                              <input type="hidden" name="id" value="<?= $id ?>" />
                              <button class="btn btn-sm btn-secondary" type="submit">Visto</button>
                            </form>
                          <?php endif; ?>
                          <form method="post" style="display:inline-flex;gap:8px;align-items:center" onsubmit="return confirm('¿Eliminar esta cotización?');">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>" />
                            <input type="hidden" name="action" value="delete" />
                            <input type="hidden" name="id" value="<?= $id ?>" />
                            <button class="btn btn-sm btn-ghost" type="submit">Eliminar</button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </div>
      </section>
    </main>

    <div class="toast" id="toast" role="status" aria-live="polite" aria-atomic="true"></div>
    <script src="./script.js?v=20260216-6"></script>
  </body>
</html>
