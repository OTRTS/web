<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$currentUser = require_login();
$isAdmin = is_array($currentUser) && ($currentUser['role'] ?? '') === 'admin';

if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf']) || $_SESSION['csrf'] === '') {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = (string)$_SESSION['csrf'];

$scriptName = isset($_SERVER['SCRIPT_NAME']) ? (string)$_SERVER['SCRIPT_NAME'] : '';
$baseUrlPath = str_replace('\\', '/', dirname($scriptName));
if ($baseUrlPath === '/' || $baseUrlPath === '\\') {
    $baseUrlPath = '';
}
$baseUrlPath = rtrim($baseUrlPath, '/');

$conn = db();

$normalizePdfUrl = static function ($value) use ($baseUrlPath): string {
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

$certFsPath = static function (string $pdfPath): ?string {
    $p = trim($pdfPath);
    if ($p === '') {
        return null;
    }
    $urlPath = parse_url($p, PHP_URL_PATH);
    if (!is_string($urlPath) || $urlPath === '') {
        $urlPath = $p;
    }
    $urlPath = str_replace('\\', '/', $urlPath);
    if (str_starts_with($urlPath, './')) {
        $urlPath = substr($urlPath, 1);
    }
    if (!str_starts_with($urlPath, '/')) {
        $urlPath = '/' . $urlPath;
    }
    $pos = strpos($urlPath, '/Certificados/');
    if ($pos === false) {
        return null;
    }
    $base = basename(substr($urlPath, $pos));
    if ($base === '') {
        return null;
    }
    return __DIR__ . DIRECTORY_SEPARATOR . 'Certificados' . DIRECTORY_SEPARATOR . $base;
};

$redirect = static function (array $params = []): void {
    $base = 'ver-certificados.php';
    if ($params) {
        $base .= '?' . http_build_query($params);
    }
    header('Location: ' . $base);
    exit;
};

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isAdmin) {
        $redirect(['status' => 'error', 'msg' => 'Acceso denegado.']);
    }

    $token = isset($_POST['csrf']) ? (string)$_POST['csrf'] : '';
    if (!hash_equals($csrf, $token)) {
        $redirect(['status' => 'error', 'msg' => 'Sesión expirada. Intenta de nuevo.']);
    }

    $action = isset($_POST['action']) ? (string)$_POST['action'] : '';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($action === 'delete') {
        if ($id <= 0) {
            $redirect(['status' => 'error', 'msg' => 'ID inválido.']);
        }

        $stmt = $conn->prepare('SELECT pdf_path FROM certificados WHERE id = ? LIMIT 1');
        if (!$stmt) {
            $redirect(['status' => 'error', 'msg' => 'No se pudo preparar la consulta.']);
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!is_array($row)) {
            $redirect(['status' => 'error', 'msg' => 'Registro no encontrado.']);
        }

        $pdfPath = isset($row['pdf_path']) ? (string)$row['pdf_path'] : '';

        $del = $conn->prepare('DELETE FROM certificados WHERE id = ?');
        if (!$del) {
            $redirect(['status' => 'error', 'msg' => 'No se pudo preparar el borrado.']);
        }
        $del->bind_param('i', $id);
        $ok = $del->execute();
        $del->close();

        if (!$ok) {
            $redirect(['status' => 'error', 'msg' => 'No se pudo eliminar el registro.']);
        }

        $fs = $certFsPath($normalizePdfUrl($pdfPath));
        if (is_string($fs) && is_file($fs)) {
            @unlink($fs);
        }

        $redirect(['status' => 'success', 'msg' => 'Registro eliminado.']);
    }

    if ($action === 'update') {
        $nombre = isset($_POST['nombre']) ? trim((string)$_POST['nombre']) : '';
        $ci = isset($_POST['ci']) ? trim((string)$_POST['ci']) : '';

        if ($id <= 0 || $nombre === '' || $ci === '') {
            $redirect(['status' => 'error', 'msg' => 'Completa los campos.']);
        }

        $stmt = $conn->prepare('SELECT pdf_path FROM certificados WHERE id = ? LIMIT 1');
        if (!$stmt) {
            $redirect(['status' => 'error', 'msg' => 'No se pudo preparar la consulta.']);
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!is_array($row)) {
            $redirect(['status' => 'error', 'msg' => 'Registro no encontrado.']);
        }

        $oldPdfPath = isset($row['pdf_path']) ? (string)$row['pdf_path'] : '';

        $newPdfPath = '';
        $newOrigName = '';
        $newFsPath = '';

        $hasFile = isset($_FILES['pdf']) && is_array($_FILES['pdf']) && isset($_FILES['pdf']['error']) && (int)$_FILES['pdf']['error'] !== UPLOAD_ERR_NO_FILE;
        if ($hasFile) {
            $file = $_FILES['pdf'];
            $err = (int)$file['error'];
            if ($err !== UPLOAD_ERR_OK) {
                $redirect(['status' => 'error', 'msg' => 'No pudimos subir el archivo.']);
            }

            $tmp = (string)($file['tmp_name'] ?? '');
            $size = (int)($file['size'] ?? 0);
            $orig = trim((string)($file['name'] ?? 'certificado.pdf'));

            if ($size <= 0 || $size > 15 * 1024 * 1024) {
                $redirect(['status' => 'error', 'msg' => 'El PDF debe pesar hasta 15MB.']);
            }

            $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            $mimeOk = false;
            $mime = '';

            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo) {
                    $mime = (string)finfo_file($finfo, $tmp);
                    finfo_close($finfo);
                }
            }

            if ($mime === 'application/pdf' || $mime === 'application/x-pdf' || $mime === 'application/octet-stream') {
                $mimeOk = true;
            }

            if ($ext !== 'pdf' || !$mimeOk) {
                $redirect(['status' => 'error', 'msg' => 'El archivo debe ser un PDF válido.']);
            }

            $dirFs = __DIR__ . DIRECTORY_SEPARATOR . 'Certificados';
            if (!is_dir($dirFs)) {
                @mkdir($dirFs, 0775, true);
            }
            if (!is_dir($dirFs)) {
                $redirect(['status' => 'error', 'msg' => 'No se pudo crear la carpeta Certificados.']);
            }

            $nameClean = normalize_name($nombre);
            $slug = preg_replace('/\s+/', '-', $nameClean) ?? '';
            $slug = trim($slug, '-');
            if ($slug === '') {
                $slug = 'certificado';
            }

            $ciClean = normalize_ci($ci);
            $rand = bin2hex(random_bytes(4));
            $stamp = date('Ymd_His');
            $base = substr($slug, 0, 60);
            $filename = $base . '_' . substr($ciClean, 0, 24) . '_' . $stamp . '_' . $rand . '.pdf';
            $destFs = $dirFs . DIRECTORY_SEPARATOR . $filename;

            if (!move_uploaded_file($tmp, $destFs)) {
                $redirect(['status' => 'error', 'msg' => 'No se pudo guardar el PDF.']);
            }

            $newPdfPath = '/Certificados/' . $filename;
            $newOrigName = $orig;
            $newFsPath = $destFs;
        }

        $nombreClean = normalize_name($nombre);
        $ciClean2 = normalize_ci($ci);

        if ($newPdfPath !== '') {
            $upd = $conn->prepare('UPDATE certificados SET nombre = ?, nombre_clean = ?, ci = ?, ci_clean = ?, pdf_path = ?, original_filename = ? WHERE id = ?');
            if (!$upd) {
                if ($newFsPath !== '' && is_file($newFsPath)) {
                    @unlink($newFsPath);
                }
                $redirect(['status' => 'error', 'msg' => 'No se pudo preparar la actualización.']);
            }
            $upd->bind_param('ssssssi', $nombre, $nombreClean, $ci, $ciClean2, $newPdfPath, $newOrigName, $id);
        } else {
            $upd = $conn->prepare('UPDATE certificados SET nombre = ?, nombre_clean = ?, ci = ?, ci_clean = ? WHERE id = ?');
            if (!$upd) {
                $redirect(['status' => 'error', 'msg' => 'No se pudo preparar la actualización.']);
            }
            $upd->bind_param('ssssi', $nombre, $nombreClean, $ci, $ciClean2, $id);
        }

        $ok = $upd->execute();
        $upd->close();

        if (!$ok) {
            if ($newFsPath !== '' && is_file($newFsPath)) {
                @unlink($newFsPath);
            }
            $redirect(['status' => 'error', 'msg' => 'No se pudo guardar en base de datos.']);
        }

        if ($newPdfPath !== '') {
            $oldFs = $certFsPath($normalizePdfUrl($oldPdfPath));
            if (is_string($oldFs) && is_file($oldFs)) {
                @unlink($oldFs);
            }
        }

        $redirect(['status' => 'success', 'msg' => 'Cambios guardados.', 'edit' => $id]);
    }

    $redirect(['status' => 'error', 'msg' => 'Acción no válida.']);
}

if (isset($_GET['status'], $_GET['msg'])) {
    $message = trim((string)$_GET['msg']);
    $status = (string)$_GET['status'];
    if ($status === 'success') {
        $messageType = 'success';
    } elseif ($status === 'error') {
        $messageType = 'error';
    }
}

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$qCi = $q !== '' ? normalize_ci($q) : '';
$qName = $q !== '' ? normalize_name($q) : '';

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editRow = null;
if ($editId > 0) {
    $stmt = $conn->prepare('SELECT id, nombre, ci, pdf_path, original_filename, uploaded_at FROM certificados WHERE id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $editId);
        $stmt->execute();
        $editRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

if ($q !== '') {
    if ($qCi !== '') {
        $stmt = $conn->prepare('SELECT id, nombre, ci, pdf_path, original_filename, uploaded_at FROM certificados WHERE ci_clean = ? ORDER BY uploaded_at DESC, id DESC LIMIT 500');
        $stmt->bind_param('s', $qCi);
    } else {
        $like = '%' . $qName . '%';
        $stmt = $conn->prepare('SELECT id, nombre, ci, pdf_path, original_filename, uploaded_at FROM certificados WHERE nombre_clean LIKE ? ORDER BY uploaded_at DESC, id DESC LIMIT 500');
        $stmt->bind_param('s', $like);
    }
} else {
    $stmt = $conn->prepare('SELECT id, nombre, ci, pdf_path, original_filename, uploaded_at FROM certificados ORDER BY uploaded_at DESC, id DESC LIMIT 500');
}

$rows = [];
if ($stmt) {
    $execOk = $stmt->execute();
    if (!$execOk && $message === '') {
        $message = 'Aún no existe la tabla "certificados" o no se pudo consultar. Abre una vez Registrar certificado para que se cree automáticamente, o créala desde phpMyAdmin.';
        $messageType = 'error';
    }
    $res = $stmt->get_result();
    if ($res) {
        $rows = $res->fetch_all(MYSQLI_ASSOC) ?: [];
    }
    $stmt->close();
}
?>
<!doctype html>
<html lang="es">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="theme-color" content="#0b2a6f" />
    <title>Ver certificados | On The Road To Safety</title>
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
            <?php if ($isAdmin): ?>
              <a class="nav-admin" href="./registrar-certificado.php">
                <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M7 4h10a2 2 0 0 1 2 2v14H5V6a2 2 0 0 1 2-2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                  <path d="M12 8v8M8 12h8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                </svg>
                Registrar certificado
              </a>
            <?php endif; ?>
            <a href="./Certificados/">
              <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M7 4h10a2 2 0 0 1 2 2v14H5V6a2 2 0 0 1 2-2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                <path d="M9 8h6M9 12h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
              </svg>
              Certificados
            </a>
            <a class="nav-admin" href="./ver-certificados.php">
              <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M7 4h10a2 2 0 0 1 2 2v14H5V6a2 2 0 0 1 2-2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                <path d="M8 9h8M8 13h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
              </svg>
              Ver certificados
            </a>
            <?php if ($isAdmin): ?>
              <a class="nav-admin" href="./cotizaciones.php">
                <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M18 8a6 6 0 1 0-12 0c0 7-3 7-3 7h18s-3 0-3-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                  <path d="M13.73 21a2 2 0 0 1-3.46 0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                </svg>
                Cotizaciones
                <span class="nav-badge js-cotizaciones-badge" hidden>0</span>
              </a>
            <?php endif; ?>
            <a href="./index.html#cotizacion">
              <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M7 7h10v14H7z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                <path d="M9 3h6v4H9zM9 11h6M9 15h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
              </svg>
              Cotización
            </a>
            <a class="nav-cta" href="#contacto">
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
            <a href="./logout.php">
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
              Cerrar sesión
            </a>
            <a href="./index.html#cursos">
              <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 19.5V6a2 2 0 0 1 2-2h12v17H6a2 2 0 0 0-2 2v-.5z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                <path d="M8 8h7M8 12h7M8 16h7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
              </svg>
              Cursos
            </a>
            <?php if ($isAdmin): ?>
              <a class="nav-admin" href="./registrar-certificado.php">
                <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M7 4h10a2 2 0 0 1 2 2v14H5V6a2 2 0 0 1 2-2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                  <path d="M12 8v8M8 12h8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                </svg>
                Registrar certificado
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
            <?php endif; ?>
            <a href="./Certificados/">
              <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M7 4h10a2 2 0 0 1 2 2v14H5V6a2 2 0 0 1 2-2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                <path d="M9 8h6M9 12h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
              </svg>
              Certificados
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
            <a class="nav-more-cta" href="#contacto">
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
      <section class="section section-soft">
        <div class="container">
          <div class="page-crumb reveal" data-reveal="left">
            <a href="./Certificados/">Panel</a>
            <span class="crumb-sep" aria-hidden="true">›</span>
            <span>Ver certificados</span>
          </div>

          <div class="page-head reveal" data-reveal="left">
            <h1 class="page-title">Ver certificados</h1>
            <p class="page-subtitle">Lista de registros guardados en MySQL (máximo 500 resultados).</p>
          </div>

          <div class="card reveal" data-reveal="left">
            <form class="form" method="get" autocomplete="off">
              <div class="form-row">
                <div class="field">
                  <label for="q">Buscar (nombre o CI)</label>
                  <input id="q" name="q" type="text" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej: Juan Pérez o 123456" />
                </div>
                <div class="field">
                  <label>&nbsp;</label>
                  <div class="form-actions">
                    <button class="btn btn-primary" type="submit">Buscar</button>
                    <a class="btn btn-ghost" href="./ver-certificados.php">Limpiar</a>
                  </div>
                </div>
              </div>
            </form>

            <?php if ($message !== ''): ?>
              <p class="form-note" aria-live="polite">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
              </p>
            <?php endif; ?>
          </div>

          <?php if ($isAdmin && is_array($editRow)): ?>
            <div class="card reveal" data-reveal="left" style="margin-top:16px">
              <h3 style="margin:0 0 10px">Editar registro #<?= (int)$editRow['id'] ?></h3>
              <form class="form" method="post" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>" />
                <input type="hidden" name="action" value="update" />
                <input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>" />

                <div class="form-row">
                  <div class="field">
                    <label for="edit-nombre">Nombre</label>
                    <input id="edit-nombre" name="nombre" type="text" required value="<?= htmlspecialchars((string)($editRow['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
                  </div>
                  <div class="field">
                    <label for="edit-ci">CI</label>
                    <input id="edit-ci" name="ci" type="text" required value="<?= htmlspecialchars((string)($editRow['ci'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
                  </div>
                </div>

                <div class="field">
                  <label for="edit-pdf">Reemplazar PDF (opcional)</label>
                  <input id="edit-pdf" name="pdf" type="file" accept="application/pdf" />
                </div>

                <div class="form-actions form-actions-lg">
                  <button class="btn btn-primary" type="submit">Guardar cambios</button>
                  <a class="btn btn-link" href="./ver-certificados.php">Cancelar</a>
                </div>
              </form>
            </div>
          <?php endif; ?>

          <div class="card reveal" data-reveal="left" style="margin-top:16px">
            <div class="table-wrap">
              <table class="data-table" role="table">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>CI</th>
                    <th>PDF</th>
                    <th>Original</th>
                    <th>Fecha</th>
                    <th>Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!$rows): ?>
                    <tr>
                      <td colspan="7">
                        No hay registros todavía.
                        <?php if ($isAdmin): ?>
                          Registra uno en <a href="./registrar-certificado.php">Registrar certificado</a> y vuelve aquí para verlo en la tabla.
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                      <?php
                      $rid = isset($r['id']) ? (int)$r['id'] : 0;
                      $pdfUrl = isset($r['pdf_path']) ? $normalizePdfUrl((string)$r['pdf_path']) : '';
                      ?>
                      <tr>
                        <td><?= $rid ?></td>
                        <td><?= htmlspecialchars((string)($r['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string)($r['ci'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                          <?php if ($pdfUrl !== ''): ?>
                            <a class="btn btn-ghost btn-sm" href="<?= htmlspecialchars($pdfUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noreferrer">Ver PDF</a>
                          <?php else: ?>
                            —
                          <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars((string)($r['original_filename'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string)($r['uploaded_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                          <?php if ($isAdmin): ?>
                            <div class="data-actions">
                              <a class="btn btn-ghost btn-sm" href="./ver-certificados.php?edit=<?= $rid ?>">Editar</a>
                              <form method="post" onsubmit="return confirm('¿Eliminar este registro?');">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>" />
                                <input type="hidden" name="action" value="delete" />
                                <input type="hidden" name="id" value="<?= $rid ?>" />
                                <button class="btn btn-sm btn-danger" type="submit">Eliminar</button>
                              </form>
                            </div>
                          <?php else: ?>
                            —
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </section>
    </main>

    <section class="section section-dark" id="contacto">
      <div class="container contact-cta">
        <div class="contact-copy reveal" data-reveal="left">
          <h2>¿Listo para asegurar el futuro de tu transporte?</h2>
          <p>Agenda una llamada y recibe una propuesta alineada a tu operación.</p>
        </div>
        <form class="mini-form reveal" data-reveal="right" id="contact-form">
          <label class="sr-only" for="contact-email">Correo</label>
          <input id="contact-email" type="email" required placeholder="tu@empresa.com" />
          <button class="btn btn-primary" type="submit">Contactar ahora</button>
        </form>
      </div>
    </section>

    <footer class="site-footer">
      <div class="container footer-grid">
        <div class="footer-brand">
          <img class="footer-logo" src="./assets/img/logo.png" alt="On The Road To Safety" />
          <p class="muted">
            Capacitación profesional y gestión de seguridad vial para empresas. Resultados medibles, enfoque práctico y mejora continua.
          </p>
          <div class="social-links" aria-label="Redes sociales">
            <a
              class="social-link"
              href="https://www.facebook.com/OnTheRoadToSafety"
              target="_blank"
              rel="noreferrer"
              aria-label="Facebook"
            >
              <svg class="social-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path
                  fill="currentColor"
                  d="M13.5 22v-8h2.7l.4-3h-3.1V9.1c0-.9.3-1.6 1.6-1.6h1.7V4.8c-.3 0-1.3-.1-2.5-.1-2.5 0-4.2 1.5-4.2 4.3V11H7.4v3h2.7v8h3.4Z"
                />
              </svg>
            </a>
            <a
              class="social-link"
              href="https://www.tiktok.com/@otrts7"
              target="_blank"
              rel="noreferrer"
              aria-label="TikTok"
            >
              <svg class="social-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path
                  fill="currentColor"
                  d="M16.8 4.6c1 1.2 2.3 2 3.8 2.2v3.2c-1.8-.1-3.4-.7-4.8-1.7v7.1c0 3.6-2.9 6.6-6.6 6.6-3.6 0-6.6-2.9-6.6-6.6s2.9-6.6 6.6-6.6c.4 0 .8 0 1.2.1v3.4c-.4-.2-.8-.3-1.2-.3-1.8 0-3.2 1.4-3.2 3.2s1.4 3.2 3.2 3.2 3.2-1.4 3.2-3.2V2h3.4c.1.9.4 1.8 1 2.6Z"
                />
              </svg>
            </a>
            <a
              class="social-link"
              href="https://www.instagram.com/otrts.bo/"
              target="_blank"
              rel="noreferrer"
              aria-label="Instagram"
            >
              <svg class="social-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path
                  fill="currentColor"
                  d="M12 7.3A4.7 4.7 0 1 0 16.7 12 4.7 4.7 0 0 0 12 7.3Zm0 7.7A3 3 0 1 1 15 12a3 3 0 0 1-3 3Zm5.9-7.9a1.1 1.1 0 1 1-1.1-1.1 1.1 1.1 0 0 1 1.1 1.1ZM20 12c0-1.6 0-3.2-.1-4.8a4.8 4.8 0 0 0-2.7-3.4C15.9 3.2 14.3 3.2 12 3.2s-3.9 0-5.2.6A4.8 4.8 0 0 0 4.1 7.2C4 8.8 4 10.4 4 12s0 3.2.1 4.8a4.8 4.8 0 0 0 2.7 3.4c1.3.6 2.9.6 5.2.6s3.9 0 5.2-.6a4.8 4.8 0 0 0 2.7-3.4c.1-1.6.1-3.2.1-4.8Zm-1.7 4.7a3.1 3.1 0 0 1-1.8 2.4c-1 .4-2.4.4-4.5.4s-3.5 0-4.5-.4a3.1 3.1 0 0 1-1.8-2.4c-.1-1.5-.1-3.1-.1-4.7s0-3.2.1-4.7a3.1 3.1 0 0 1 1.8-2.4c1-.4 2.4-.4 4.5-.4s3.5 0 4.5.4a3.1 3.1 0 0 1 1.8 2.4c.1 1.5.1 3.1.1 4.7s0 3.2-.1 4.7Z"
                />
              </svg>
            </a>
          </div>
        </div>

        <div class="footer-links">
          <h3>Secciones</h3>
          <a href="./index.html#quienes-somos">Quiénes somos</a>
          <a href="./index.html#cursos">Cursos</a>
          <?php if ($isAdmin): ?>
            <a href="./registrar-certificado.php">Registrar certificado</a>
            <a href="./usuarios.php">Usuarios</a>
          <?php endif; ?>
          <a href="./Certificados/">Certificados</a>
          <a class="nav-admin" href="./ver-certificados.php">Ver certificados</a>
          <a href="./index.html#cotizacion">Cotización</a>
        </div>

        <div class="footer-links">
          <h3>Contacto</h3>
          <a href="#contacto">Formulario</a>
        </div>
      </div>
      <div class="container footer-bottom">
        <p class="muted">© <span id="year"></span> On The Road To Safety. Todos los derechos reservados.</p>
      </div>
    </footer>

    <div class="toast" id="toast" role="status" aria-live="polite" aria-atomic="true"></div>
    <script src="./script.js?v=20260216-5"></script>
  </body>
</html>
