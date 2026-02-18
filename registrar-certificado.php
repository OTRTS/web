<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$wantsJson = isset($_GET['json']) && (string)$_GET['json'] === '1';

$current = auth_user();
if (!is_array($current)) {
    if ($wantsJson) {
        json_response(['ok' => false, 'message' => 'Debes iniciar sesión.'], 401);
    }
    $uri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '/registrar-certificado.php';
    $redirect = safe_redirect_path($uri);
    $qs = $redirect !== '' ? ('?redirect=' . rawurlencode($redirect)) : '';
    header('Location: ./login.php' . $qs);
    exit;
}
if (($current['role'] ?? '') !== 'admin') {
    if ($wantsJson) {
        json_response(['ok' => false, 'message' => 'Acceso denegado.'], 403);
    }
    http_response_code(403);
    exit('Acceso denegado');
}

$scriptName = isset($_SERVER['SCRIPT_NAME']) ? (string)$_SERVER['SCRIPT_NAME'] : '';
$baseUrlPath = str_replace('\\', '/', dirname($scriptName));
if ($baseUrlPath === '/' || $baseUrlPath === '\\') {
    $baseUrlPath = '';
}
$baseUrlPath = rtrim($baseUrlPath, '/');

$message = '';
$messageType = '';
$savedPdfUrl = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = isset($_POST['nombre']) ? trim((string)$_POST['nombre']) : '';
    $ci = isset($_POST['ci']) ? trim((string)$_POST['ci']) : '';

    if ($nombre === '' || $ci === '') {
        $message = 'Completa nombre y carnet/licencia.';
        $messageType = 'error';
    } elseif (!isset($_FILES['pdf']) || !is_array($_FILES['pdf'])) {
        $message = 'Sube el PDF del certificado.';
        $messageType = 'error';
    } else {
        $file = $_FILES['pdf'];
        $err = isset($file['error']) ? (int)$file['error'] : UPLOAD_ERR_NO_FILE;

        if ($err !== UPLOAD_ERR_OK) {
            $message = 'No pudimos subir el archivo. Intenta de nuevo.';
            $messageType = 'error';
        } else {
            $tmp = (string)($file['tmp_name'] ?? '');
            $size = (int)($file['size'] ?? 0);
            $orig = trim((string)($file['name'] ?? 'certificado.pdf'));

            if ($size <= 0 || $size > 15 * 1024 * 1024) {
                $message = 'El PDF debe pesar hasta 15MB.';
                $messageType = 'error';
            } else {
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
                    $message = 'El archivo debe ser un PDF válido.';
                    $messageType = 'error';
                } else {
                    $dirFs = __DIR__ . DIRECTORY_SEPARATOR . 'Certificados';
                    if (!is_dir($dirFs)) {
                        @mkdir($dirFs, 0775, true);
                    }

                    if (!is_dir($dirFs)) {
                        $message = 'No se pudo crear la carpeta Certificados.';
                        $messageType = 'error';
                    } else {
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
                        $webPath = ($baseUrlPath !== '' ? $baseUrlPath : '') . '/Certificados/' . $filename;

                        if (!move_uploaded_file($tmp, $destFs)) {
                            $message = 'No se pudo guardar el PDF.';
                            $messageType = 'error';
                        } else {
                            $savedPdfUrl = $webPath;
                            $conn = db();
                            $stmt = $conn->prepare('INSERT INTO certificados (nombre, nombre_clean, ci, ci_clean, pdf_path, original_filename) VALUES (?, ?, ?, ?, ?, ?)');
                            if (!$stmt) {
                                @unlink($destFs);
                                $message = 'Error guardando en base de datos.';
                                $messageType = 'error';
                            } else {
                                $nombreClean = normalize_name($nombre);
                                $ciClean2 = normalize_ci($ci);
                                $stmt->bind_param('ssssss', $nombre, $nombreClean, $ci, $ciClean2, $webPath, $orig);
                                $ok = $stmt->execute();
                                $stmt->close();

                                if (!$ok) {
                                    @unlink($destFs);
                                    $message = 'Error guardando en base de datos.';
                                    $messageType = 'error';
                                } else {
                                    $message = 'Certificado registrado correctamente.';
                                    $messageType = 'success';
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

if ($wantsJson) {
    $ok = $messageType === 'success';
    $status = $ok ? 200 : 400;
    json_response(
        [
            'ok' => $ok,
            'message' => $message !== '' ? $message : ($ok ? 'OK' : 'Error'),
            'pdf_url' => $ok ? $savedPdfUrl : '',
        ],
        $status
    );
}

?>
<!doctype html>
<html lang="es">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="theme-color" content="#0b2a6f" />
    <title>Registrar certificado | On The Road To Safety</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="./styles.css?v=20260216-14" />
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
                <path
                  d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1z"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linejoin="round"
                />
              </svg>
              Inicio
            </a>
            <a href="./index.html#cursos">
              <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path
                  d="M4 19.5V6a2 2 0 0 1 2-2h12v17H6a2 2 0 0 0-2 2v-.5z"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linejoin="round"
                />
                <path d="M8 8h7M8 12h7M8 16h7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
              </svg>
              Cursos
            </a>
            <a class="nav-admin" href="./registrar-certificado.php">
              <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path
                  d="M7 4h10a2 2 0 0 1 2 2v14H5V6a2 2 0 0 1 2-2z"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linejoin="round"
                />
                <path d="M12 8v8M8 12h8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
              </svg>
              Registrar certificado
            </a>
            <a href="./Certificados/">
              <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path
                  d="M7 4h10a2 2 0 0 1 2 2v14H5V6a2 2 0 0 1 2-2z"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linejoin="round"
                />
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
            <a class="nav-admin" href="./cotizaciones.php">
              <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M18 8a6 6 0 1 0-12 0c0 7-3 7-3 7h18s-3 0-3-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                <path d="M13.73 21a2 2 0 0 1-3.46 0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
              </svg>
              Cotizaciones
              <span class="nav-badge js-cotizaciones-badge" hidden>0</span>
            </a>
            <a href="./index.html#cotizacion">
              <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path
                  d="M7 7h10v14H7z"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linejoin="round"
                />
                <path d="M9 3h6v4H9zM9 11h6M9 15h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
              </svg>
              Cotización
            </a>
            <a class="nav-cta" href="#contacto">
              <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path
                  d="M21 8v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linejoin="round"
                />
                <path
                  d="m21 8-9 6L3 8"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linejoin="round"
                />
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
                <path
                  d="M17 8V7a5 5 0 0 0-10 0v1"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                />
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
            <a href="./index.html#quienes-somos">
              <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path
                  d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                />
                <path
                  d="M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                />
                <path
                  d="M22 21v-2a4 4 0 0 0-3-3.87"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                />
                <path
                  d="M16 3.13a4 4 0 0 1 0 7.75"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                />
              </svg>
              Quiénes somos
            </a>
            <a href="./index.html#metodologia">
              <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path
                  d="M12 20a8 8 0 1 0-8-8 8 8 0 0 0 8 8z"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                />
                <path d="M12 6v6l3 2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
              </svg>
              Metodología
            </a>
            <a href="./index.html#tips">
              <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path
                  d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linejoin="round"
                />
                <path d="M8 9h8M8 13h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
              </svg>
              Tips
            </a>

            <a href="./index.html#cursos">
              <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path
                  d="M4 19.5V6a2 2 0 0 1 2-2h12v17H6a2 2 0 0 0-2 2v-.5z"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linejoin="round"
                />
                <path d="M8 8h7M8 12h7M8 16h7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
              </svg>
              Cursos
            </a>
            <a class="nav-admin" href="./registrar-certificado.php">
              <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path
                  d="M7 4h10a2 2 0 0 1 2 2v14H5V6a2 2 0 0 1 2-2z"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linejoin="round"
                />
                <path d="M12 8v8M8 12h8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
              </svg>
              Registrar certificado
            </a>
            <a href="./Certificados/">
              <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path
                  d="M7 4h10a2 2 0 0 1 2 2v14H5V6a2 2 0 0 1 2-2z"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linejoin="round"
                />
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
            <a href="./index.html#cotizacion">
              <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path
                  d="M7 7h10v14H7z"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linejoin="round"
                />
                <path d="M9 3h6v4H9zM9 11h6M9 15h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
              </svg>
              Cotización
            </a>
            <a class="nav-more-cta" href="#contacto">
              <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path
                  d="M21 8v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linejoin="round"
                />
                <path
                  d="m21 8-9 6L3 8"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linejoin="round"
                />
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
            <span>Certificados</span>
          </div>

          <div class="page-head reveal" data-reveal="left">
            <h1 class="page-title">Registrar Certificado de Capacitación</h1>
            <p class="page-subtitle">Sube el documento PDF para que el certificado esté disponible inmediatamente en el buscador por nombre o identificación.</p>
          </div>

          <form class="form card form-lg reveal" data-reveal="left" method="post" enctype="multipart/form-data" autocomplete="off">
            <div class="form-row">
              <div class="field">
                <label for="nombre">Nombre Completo</label>
                <input
                  id="nombre"
                  name="nombre"
                  type="text"
                  placeholder="Ej: Juan Pérez"
                  required
                  value="<?= htmlspecialchars(isset($_POST['nombre']) ? (string)$_POST['nombre'] : '', ENT_QUOTES, 'UTF-8') ?>"
                />
              </div>
              <div class="field">
                <label for="ci">Cédula o Licencia</label>
                <input
                  id="ci"
                  name="ci"
                  type="text"
                  placeholder="Número de identificación"
                  required
                  value="<?= htmlspecialchars(isset($_POST['ci']) ? (string)$_POST['ci'] : '', ENT_QUOTES, 'UTF-8') ?>"
                />
              </div>
            </div>

            <div class="field">
              <label for="pdf">Carga de Certificado (PDF)</label>
              <input class="file-input" id="pdf" name="pdf" type="file" accept="application/pdf" required />
              <div class="dropzone" id="pdf-dropzone" role="button" tabindex="0" aria-controls="pdf">
                <div class="dropzone-icon" aria-hidden="true">
                  <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M12 16V8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                    <path d="m8.5 11.5 3.5-3.5 3.5 3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    <path d="M20 16.5a4.5 4.5 0 0 0-2.2-8.4A6 6 0 0 0 6 9.5 3.5 3.5 0 0 0 6.5 16.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                  </svg>
                </div>
                <p class="dropzone-text">
                  <span class="dropzone-action">Seleccionar Archivo</span>
                  <span class="dropzone-muted">o arrastra y suelta aquí</span>
                </p>
                <p class="dropzone-hint">Únicamente archivos PDF hasta 10MB</p>
              </div>
              <p class="dropzone-file" id="pdf-file-name" aria-live="polite" hidden></p>
            </div>

            <div class="form-actions form-actions-lg">
              <button class="btn btn-primary" type="submit">
                <span class="btn-icon-badge" aria-hidden="true">
                  <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M20 6 9 17l-5-5" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                  </svg>
                </span>
                Registrar Certificado
              </button>
              <a class="btn btn-link" href="./Certificados/">Cancelar</a>
            </div>
            <?php if ($message !== ''): ?>
              <p class="form-note" aria-live="polite">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                <?php if ($messageType === 'success'): ?>
                  <?php if ($savedPdfUrl !== ''): ?>
                    <span> </span><a href="<?= htmlspecialchars($savedPdfUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noreferrer">Ver PDF</a>
                  <?php endif; ?>
                  <span> </span><a href="./Certificados/">Ir a buscar</a>
                <?php endif; ?>
              </p>
            <?php endif; ?>
          </form>

          <div class="grid-3 info-grid">
            <article class="info-card info-blue reveal" data-reveal="left">
              <div class="info-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                  <path d="M12 22a10 10 0 1 0-10-10 10 10 0 0 0 10 10Z" fill="none" stroke="currentColor" stroke-width="2" />
                  <path d="M12 16v-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                  <path d="M12 8h.01" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" />
                </svg>
              </div>
              <h3>Procesamiento Inmediato</h3>
              <p>Los certificados subidos aparecen en tiempo real en la base de datos.</p>
            </article>

            <article class="info-card info-green reveal" data-reveal="up">
              <div class="info-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                  <path d="M12 2 20 6v6c0 5-3.5 9.5-8 10-4.5-.5-8-5-8-10V6l8-4Z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                  <path d="M9 12l2 2 4-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </div>
              <h3>Almacenamiento Seguro</h3>
              <p>Toda la documentación se guarda y se protege con medidas de seguridad.</p>
            </article>

            <article class="info-card info-amber reveal" data-reveal="right">
              <div class="info-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                  <path d="M12 22a10 10 0 1 0-10-10 10 10 0 0 0 10 10Z" fill="none" stroke="currentColor" stroke-width="2" />
                  <path d="M9.5 9.5a2.5 2.5 0 0 1 5 0c0 2-2.5 2-2.5 4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                  <path d="M12 17h.01" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" />
                </svg>
              </div>
              <h3>¿Necesitas Ayuda?</h3>
              <p>Contacta a soporte si tienes problemas con la carga de archivos.</p>
            </article>
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
            Capacitación profesional y gestión de seguridad vial para empresas. Resultados medibles, enfoque práctico y mejora
            continua.
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
          <a href="./Certificados/">Certificados</a>
          <a href="./index.html#metodologia">Metodología</a>
          <a href="./index.html#cotizacion">Cotización</a>
        </div>

        <div class="footer-links">
          <h3>Contacto</h3>
          <a href="mailto:cotizaciones@ontheroadtosafety.com">cotizaciones@ontheroadtosafety.com</a>
          <a href="tel:+59177787803">+591 77787803</a>
          <a href="#inicio">Volver arriba</a>
        </div>
      </div>
      <div class="container footer-bottom">
        <p class="muted">© <span id="year"></span> On The Road To Safety. Todos los derechos reservados.</p>
      </div>
    </footer>

    <button class="float-cta" type="button" id="float-cta" aria-label="Contactar por WhatsApp" aria-expanded="false">
      <svg class="wa-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path fill="currentColor" d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
      </svg>
    </button>

    <div class="wa-modal" id="wa-modal" hidden>
      <div class="wa-dialog" role="dialog" aria-modal="true" aria-labelledby="wa-title" aria-describedby="wa-desc">
        <h3 id="wa-title">¿Abrir WhatsApp?</h3>
        <p class="muted" id="wa-desc">¿Estás seguro de comunicarte con On The Road To Safety?</p>
        <div class="wa-actions">
          <button class="btn btn-ghost" type="button" id="wa-cancel">Cancelar</button>
          <button class="btn btn-primary" type="button" id="wa-confirm">Confirmar</button>
        </div>
      </div>
    </div>

    <div class="toast" id="toast" role="status" aria-live="polite" aria-atomic="true"></div>
    <script src="./script.js?v=20260216-5"></script>
  </body>
</html>
