<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$current = require_admin();

auth_start();
if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf']) || $_SESSION['csrf'] === '') {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = (string)$_SESSION['csrf'];

$conn = db();

$message = '';
$messageType = '';

$postAction = isset($_POST['action']) ? (string)$_POST['action'] : '';
$postCsrf = isset($_POST['csrf']) ? (string)$_POST['csrf'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($postCsrf === '' || !hash_equals($csrf, $postCsrf)) {
        $message = 'Sesión inválida. Recarga la página.';
        $messageType = 'error';
    } elseif ($postAction === 'create') {
        $username = isset($_POST['username']) ? trim((string)$_POST['username']) : '';
        $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
        $role = isset($_POST['role']) ? (string)$_POST['role'] : 'visitante';
        $active = isset($_POST['is_active']) && (string)$_POST['is_active'] === '1' ? 1 : 0;

        if ($username === '' || $password === '') {
            $message = 'Completa usuario y contraseña.';
            $messageType = 'error';
        } elseif ($role !== 'admin' && $role !== 'visitante') {
            $message = 'Rol inválido.';
            $messageType = 'error';
        } elseif (mb_strlen($password, 'UTF-8') < 8) {
            $message = 'La contraseña debe tener al menos 8 caracteres.';
            $messageType = 'error';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('INSERT INTO users (username, password_hash, role, is_active) VALUES (?, ?, ?, ?)');
            if (!$stmt) {
                $message = 'No se pudo crear el usuario.';
                $messageType = 'error';
            } else {
                $stmt->bind_param('sssi', $username, $hash, $role, $active);
                $ok = $stmt->execute();
                $stmt->close();
                if (!$ok) {
                    $message = 'No se pudo crear el usuario (quizá ya existe).';
                    $messageType = 'error';
                } else {
                    $message = 'Usuario creado.';
                    $messageType = 'success';
                }
            }
        }
    } elseif ($postAction === 'update_username') {
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $username = isset($_POST['username']) ? trim((string)$_POST['username']) : '';

        if ($userId <= 0 || $username === '') {
            $message = 'Datos incompletos.';
            $messageType = 'error';
        } elseif (mb_strlen($username, 'UTF-8') > 80) {
            $message = 'El usuario es demasiado largo.';
            $messageType = 'error';
        } else {
            $stmt = $conn->prepare('UPDATE users SET username = ? WHERE id = ?');
            if (!$stmt) {
                $message = 'No se pudo actualizar.';
                $messageType = 'error';
            } else {
                $stmt->bind_param('si', $username, $userId);
                $ok = $stmt->execute();
                $stmt->close();
                if (!$ok) {
                    $message = 'No se pudo actualizar (quizá el usuario ya existe).';
                    $messageType = 'error';
                } else {
                    if ((int)$current['id'] === $userId) {
                        $_SESSION['username'] = $username;
                    }
                    $message = 'Usuario actualizado.';
                    $messageType = 'success';
                }
            }
        }
    } elseif ($postAction === 'reset_password') {
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $password = isset($_POST['password']) ? (string)$_POST['password'] : '';

        if ($userId <= 0 || $password === '') {
            $message = 'Datos incompletos.';
            $messageType = 'error';
        } elseif (mb_strlen($password, 'UTF-8') < 8) {
            $message = 'La contraseña debe tener al menos 8 caracteres.';
            $messageType = 'error';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            if (!$stmt) {
                $message = 'No se pudo actualizar.';
                $messageType = 'error';
            } else {
                $stmt->bind_param('si', $hash, $userId);
                $ok = $stmt->execute();
                $stmt->close();
                if (!$ok) {
                    $message = 'No se pudo actualizar.';
                    $messageType = 'error';
                } else {
                    $message = 'Contraseña actualizada.';
                    $messageType = 'success';
                }
            }
        }
    } elseif ($postAction === 'update_status') {
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $active = isset($_POST['is_active']) && (string)$_POST['is_active'] === '1' ? 1 : 0;

        if ($userId <= 0) {
            $message = 'Usuario inválido.';
            $messageType = 'error';
        } else {
            $stmt = $conn->prepare('UPDATE users SET is_active = ? WHERE id = ?');
            if (!$stmt) {
                $message = 'No se pudo actualizar.';
                $messageType = 'error';
            } else {
                $stmt->bind_param('ii', $active, $userId);
                $ok = $stmt->execute();
                $stmt->close();
                if (!$ok) {
                    $message = 'No se pudo actualizar.';
                    $messageType = 'error';
                } else {
                    $message = 'Estado actualizado.';
                    $messageType = 'success';
                }
            }
        }
    } elseif ($postAction === 'delete') {
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

        if ($userId <= 0) {
            $message = 'Usuario inválido.';
            $messageType = 'error';
        } elseif ((int)$current['id'] === $userId) {
            $message = 'No puedes eliminar tu propio usuario.';
            $messageType = 'error';
        } else {
            $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
            if (!$stmt) {
                $message = 'No se pudo eliminar.';
                $messageType = 'error';
            } else {
                $stmt->bind_param('i', $userId);
                $ok = $stmt->execute();
                $stmt->close();
                if (!$ok) {
                    $message = 'No se pudo eliminar.';
                    $messageType = 'error';
                } else {
                    $message = 'Usuario eliminado.';
                    $messageType = 'success';
                }
            }
        }
    }
}

$rows = [];
$res = $conn->query('SELECT id, username, role, is_active, created_at, updated_at FROM users ORDER BY role ASC, username ASC');
if ($res) {
    $rows = $res->fetch_all(MYSQLI_ASSOC) ?: [];
    $res->free();
}

?>
<!doctype html>
<html lang="es">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="theme-color" content="#0b2a6f" />
    <title>Usuarios | On The Road To Safety</title>
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
            <a class="nav-admin" href="./ver-certificados.php">Ver certificados</a>
            <a class="nav-admin" href="./cotizaciones.php">
              Cotizaciones
              <span class="nav-badge js-cotizaciones-badge" hidden>0</span>
            </a>
            <a class="nav-cta" href="./logout.php">Cerrar sesión</a>
          </nav>
        </div>
      </div>
    </header>

    <main id="contenido">
      <section class="section section-soft">
        <div class="container">
          <div class="page-head reveal" data-reveal="left">
            <h1 class="page-title">Usuarios</h1>
            <p class="page-subtitle">Gestión de acceso (admin y visitante).</p>
          </div>

          <div class="card reveal" data-reveal="left">
            <form class="form" method="post" autocomplete="off">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>" />
              <input type="hidden" name="action" value="create" />
              <div class="form-row">
                <div class="field">
                  <label for="username">Usuario</label>
                  <input id="username" name="username" type="text" required placeholder="usuario" />
                </div>
                <div class="field">
                  <label for="password">Contraseña</label>
                  <input id="password" name="password" type="password" required placeholder="Mínimo 8 caracteres" />
                </div>
              </div>
              <div class="form-row">
                <div class="field">
                  <label for="role">Rol</label>
                  <select id="role" name="role">
                    <option value="visitante" selected>visitante</option>
                    <option value="admin">admin</option>
                  </select>
                </div>
                <div class="field">
                  <label for="is_active">Activo</label>
                  <select id="is_active" name="is_active">
                    <option value="1" selected>Sí</option>
                    <option value="0">No</option>
                  </select>
                </div>
              </div>
              <div class="form-actions">
                <button class="btn btn-primary" type="submit">Crear usuario</button>
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

          <div class="card reveal" data-reveal="left" style="margin-top:16px">
            <div class="table-wrap">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Usuario</th>
                    <th>Rol</th>
                    <th>Activo</th>
                    <th>Creado</th>
                    <th>Actualizado</th>
                    <th>Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (count($rows) === 0): ?>
                    <tr>
                      <td colspan="7">No hay usuarios.</td>
                    </tr>
                  <?php endif; ?>

                  <?php foreach ($rows as $r): ?>
                    <?php
                      $id = (int)($r['id'] ?? 0);
                      $u = (string)($r['username'] ?? '');
                      $role = (string)($r['role'] ?? '');
                      $active = (int)($r['is_active'] ?? 0);
                      $created = (string)($r['created_at'] ?? '');
                      $updated = (string)($r['updated_at'] ?? '');
                    ?>
                    <tr>
                      <td><?= $id ?></td>
                      <td><?= htmlspecialchars($u, ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= $active === 1 ? 'Sí' : 'No' ?></td>
                      <td><?= htmlspecialchars($created, ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= htmlspecialchars($updated, ENT_QUOTES, 'UTF-8') ?></td>
                      <td>
                        <div class="data-actions">
                      <form method="post" style="display:inline-flex;gap:8px;align-items:center">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>" />
                        <input type="hidden" name="action" value="update_username" />
                        <input type="hidden" name="user_id" value="<?= $id ?>" />
                        <input class="btn btn-sm" type="text" name="username" value="<?= htmlspecialchars($u, ENT_QUOTES, 'UTF-8') ?>" required aria-label="Usuario" />
                        <button class="btn btn-sm btn-secondary" type="submit">Guardar</button>
                      </form>

                          <form method="post" style="display:inline-flex;gap:8px;align-items:center">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>" />
                            <input type="hidden" name="action" value="update_status" />
                            <input type="hidden" name="user_id" value="<?= $id ?>" />
                            <select class="btn btn-sm" name="is_active" aria-label="Estado">
                              <option value="1" <?= $active === 1 ? 'selected' : '' ?>>Activo</option>
                              <option value="0" <?= $active !== 1 ? 'selected' : '' ?>>Inactivo</option>
                            </select>
                            <button class="btn btn-sm btn-secondary" type="submit">Guardar</button>
                          </form>

                          <form method="post" style="display:inline-flex;gap:8px;align-items:center">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>" />
                            <input type="hidden" name="action" value="reset_password" />
                            <input type="hidden" name="user_id" value="<?= $id ?>" />
                            <input class="btn btn-sm" type="password" name="password" placeholder="Nueva contraseña" required />
                            <button class="btn btn-sm btn-secondary" type="submit">Cambiar</button>
                          </form>

                          <form method="post" onsubmit="return confirm('¿Eliminar este usuario?');" style="display:inline-flex">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>" />
                            <input type="hidden" name="action" value="delete" />
                            <input type="hidden" name="user_id" value="<?= $id ?>" />
                            <button class="btn btn-sm btn-danger" type="submit">Eliminar</button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </section>
    </main>
    <script src="./script.js?v=20260216-5"></script>
  </body>
</html>
