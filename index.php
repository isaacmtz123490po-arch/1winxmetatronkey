<?php
// ============================================================
// PHP Bridge: Conecta a GitHub → Descarga → Lee → Ejecuta
// + Muestra IP del servidor
// ============================================================

session_start();

// Obtener IP del servidor
$ip_servidor = $_SERVER['SERVER_ADDR'] ?? '0.0.0.0';
$protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$url_actual = $protocolo . "://" . $host . $_SERVER['PHP_SELF'];

// Carpeta temporal para guardar archivos descargados
$carpeta_temp = "github_temp/";
if (!file_exists($carpeta_temp)) {
    mkdir($carpeta_temp, 0755, true);
}

$resultado = null;
$error = null;

// ============================================
// FUNCIÓN: Descargar desde URL de GitHub
// ============================================
function descargarDesdeGitHub($url) {
    // Convertir URL de GitHub normal a raw si es necesario
    if (strpos($url, 'github.com') !== false && strpos($url, 'raw.githubusercontent.com') === false) {
        // https://github.com/usuario/repo/blob/main/archivo.php
        // → https://raw.githubusercontent.com/usuario/repo/main/archivo.php
        $url = str_replace('github.com', 'raw.githubusercontent.com', $url);
        $url = str_replace('/blob/', '/', $url);
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'PHP-GitHub-Bridge/1.0');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $contenido = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        return ['ok' => false, 'error' => 'Error cURL: ' . $curl_error];
    }
    
    if ($http_code !== 200) {
        return ['ok' => false, 'error' => "HTTP Error $http_code — Verifica que la URL sea pública y correcta"];
    }
    
    return ['ok' => true, 'contenido' => $contenido, 'url_final' => $url];
}

// ============================================
// PROCESAR ACCIONES
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? 'ver';
    $url_github = trim($_POST['url_github'] ?? '');
    
    if (empty($url_github)) {
        $error = "Por favor ingresa una URL de GitHub";
    } else {
        $descarga = descargarDesdeGitHub($url_github);
        
        if (!$descarga['ok']) {
            $error = $descarga['error'];
        } else {
            $contenido = $descarga['contenido'];
            $url_limpia = $descarga['url_final'];
            
            // Obtener nombre del archivo
            $partes_url = parse_url($url_limpia);
            $nombre_archivo = basename($partes_url['path']);
            if (empty($nombre_archivo)) $nombre_archivo = 'archivo_descargado';
            
            $ruta_guardado = $carpeta_temp . $nombre_archivo;
            
            switch ($accion) {
                // ============================================
                // ACCIÓN 1: Solo ver contenido
                // ============================================
                case 'ver':
                    $resultado = [
                        'accion' => 'ver',
                        'titulo' => '📄 Contenido del archivo',
                        'url' => $url_limpia,
                        'nombre' => $nombre_archivo,
                        'contenido' => $contenido,
                        'tamanio' => strlen($contenido)
                    ];
                    break;
                
                // ============================================
                // ACCIÓN 2: Descargar y guardar en el servidor
                // ============================================
                case 'guardar':
                    if (file_put_contents($ruta_guardado, $contenido)) {
                        $url_archivo_guardado = dirname($url_actual) . '/' . $ruta_guardado;
                        $resultado = [
                            'accion' => 'guardar',
                            'titulo' => '💾 Archivo guardado en el servidor',
                            'url' => $url_limpia,
                            'nombre' => $nombre_archivo,
                            'ruta_local' => realpath($ruta_guardado),
                            'url_publica' => $url_archivo_guardado,
                            'tamanio' => strlen($contenido)
                        ];
                    } else {
                        $error = "No se pudo guardar el archivo. Verifica permisos de escritura.";
                    }
                    break;
                
                // ============================================
                // ACCIÓN 3: Ejecutar (si es código PHP)
                // ============================================
                case 'ejecutar':
                    // ⚠️ ADVERTENCIA: Esto ejecuta código remoto — usar solo con archivos de confianza
                    $resultado = [
                        'accion' => 'ejecutar',
                        'titulo' => '⚡ Salida de la ejecución',
                        'url' => $url_limpia,
                        'nombre' => $nombre_archivo,
                        'tamanio' => strlen($contenido)
                    ];
                    
                    // Guardar temporalmente y ejecutar
                    $temp_file = $carpeta_temp . 'temp_' . uniqid() . '.php';
                    file_put_contents($temp_file, $contenido);
                    
                    ob_start();
                    try {
                        include $temp_file;
                        $salida = ob_get_clean();
                    } catch (Throwable $e) {
                        $salida = ob_get_clean();
                        $salida .= "\n\n❌ Error en ejecución: " . $e->getMessage();
                    }
                    
                    $resultado['salida'] = $salida;
                    @unlink($temp_file); // Limpiar
                    break;
                
                // ============================================
                // ACCIÓN 4: Guardar y ejecutar desde URL pública
                // ============================================
                case 'guardar_ejecutar':
                    if (file_put_contents($ruta_guardado, $contenido)) {
                        $url_archivo_guardado = dirname($url_actual) . '/' . $ruta_guardado;
                        
                        // Ejecutar localmente
                        ob_start();
                        try {
                            include $ruta_guardado;
                            $salida = ob_get_clean();
                        } catch (Throwable $e) {
                            $salida = ob_get_clean();
                            $salida .= "\n\n❌ Error: " . $e->getMessage();
                        }
                        
                        $resultado = [
                            'accion' => 'guardar_ejecutar',
                            'titulo' => '🚀 Guardado y ejecutado',
                            'url' => $url_limpia,
                            'nombre' => $nombre_archivo,
                            'ruta_local' => realpath($ruta_guardado),
                            'url_publica' => $url_archivo_guardado,
                            'salida' => $salida,
                            'tamanio' => strlen($contenido)
                        ];
                    } else {
                        $error = "No se pudo guardar el archivo.";
                    }
                    break;
            }
        }
    }
}

// Listar archivos guardados
$archivos_guardados = [];
if (is_dir($carpeta_temp)) {
    foreach (scandir($carpeta_temp) as $f) {
        if ($f !== '.' && $f !== '..' && is_file($carpeta_temp . $f)) {
            $archivos_guardados[] = [
                'nombre' => $f,
                'url' => dirname($url_actual) . '/' . $carpeta_temp . $f,
                'fecha' => date("d/m/Y H:i", filemtime($carpeta_temp . $f)),
                'tamanio' => round(filesize($carpeta_temp . $f) / 1024, 2) . ' KB'
            ];
        }
    }
    rsort($archivos_guardados);
}
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>GitHub Bridge → Descargar + Leer + Ejecutar</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#f0f2f5;color:#1a1a1a;padding:20px;line-height:1.6;}
.container{max-width:900px;margin:0 auto;}
.card{background:white;border-radius:16px;padding:28px;margin-bottom:20px;box-shadow:0 2px 12px rgba(0,0,0,.06);}
h1{font-size:1.4rem;margin-bottom:6px;color:#1a1a1a;}
h2{font-size:1.05rem;margin-bottom:14px;color:#333;padding-bottom:10px;border-bottom:2px solid #f0f0f0;}
.subtitle{color:#666;font-size:0.9rem;margin-bottom:20px;}

/* Info IP */
.ip-banner{background:linear-gradient(135deg,#c47046,#7ba8b8);color:white;border-radius:14px;padding:18px 22px;margin-bottom:24px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;}
.ip-banner .label{font-size:0.78rem;opacity:0.9;text-transform:uppercase;letter-spacing:0.5px;}
.ip-banner .value{font-size:1.3rem;font-weight:700;font-family:monospace;}
.ip-banner .small{font-size:0.8rem;opacity:0.85;}

/* Mensajes */
.msg{padding:12px 16px;border-radius:10px;margin-bottom:20px;font-size:0.9rem;}
.msg.ok{background:#d4edda;color:#155724;border:1px solid #c3e6cb;}
.msg.error{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}
.msg.warn{background:#fff3cd;color:#856404;border:1px solid #ffeaa7;}

/* Formulario */
.form-group{margin-bottom:14px;}
.form-group label{display:block;font-size:0.85rem;font-weight:600;color:#444;margin-bottom:6px;}
.form-group input[type="url"],
.form-group input[type="text"],
.form-group select,
.form-group textarea{
    width:100%;padding:12px 14px;border:2px solid #e8e8e8;border-radius:10px;
    font-size:0.9rem;font-family:inherit;outline:none;transition:border-color 0.2s;background:#fafafa;
}
.form-group input:focus,.form-group select:focus{border-color:#c47046;background:white;}
.acciones-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin-bottom:16px;}
.accion-option{
    padding:14px;border:2px solid #e8e8e8;border-radius:12px;cursor:pointer;
    transition:all 0.2s;background:#fafafa;
}
.accion-option:hover{border-color:#c47046;background:#fffaf5;}
.accion-option input{margin-right:8px;}
.accion-option .title{font-weight:600;font-size:0.9rem;color:#1a1a1a;}
.accion-option .desc{font-size:0.78rem;color:#888;margin-top:3px;}
.btn{display:inline-block;padding:13px 28px;background:#c47046;color:white;border:none;border-radius:10px;font-size:0.95rem;font-weight:600;cursor:pointer;transition:all 0.2s;font-family:inherit;width:100%;}
.btn:hover{background:#a85d38;}
.btn:active{transform:scale(0.98);}
.btn-small{padding:6px 12px;font-size:0.8rem;width:auto;}
.btn-sec{background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;}
.btn-sec:hover{background:#c8e6c9;}

/* Resultados */
.result-box{background:#fafafa;border:1px solid #eee;border-radius:12px;padding:16px;margin-bottom:12px;}
.result-box .r-title{font-weight:600;color:#1a1a1a;margin-bottom:10px;font-size:0.95rem;display:flex;align-items:center;gap:8px;}
.result-box .r-meta{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;margin-bottom:12px;font-size:0.8rem;}
.result-box .r-meta .meta-item{background:white;padding:8px 10px;border-radius:8px;border:1px solid #eee;}
.result-box .r-meta .meta-item .ml{color:#888;font-size:0.72rem;text-transform:uppercase;}
.result-box .r-meta .meta-item .mv{color:#333;font-weight:600;font-family:monospace;font-size:0.8rem;word-break:break-all;}
.result-box pre{background:#1e1e1e;color:#d4d4d4;padding:14px;border-radius:10px;overflow-x:auto;font-size:0.8rem;max-height:400px;white-space:pre-wrap;word-break:break-word;}
.result-box .output{background:#f0f8ff;border:1px solid #b3d9ff;border-radius:10px;padding:14px;font-size:0.85rem;white-space:pre-wrap;max-height:400px;overflow-y:auto;}
.result-box .actions{margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;}
.result-box a{color:#c47046;text-decoration:none;font-size:0.85rem;}
.result-box a:hover{text-decoration:underline;}

/* Lista archivos */
.file-item{display:flex;justify-content:space-between;align-items:center;padding:10px 12px;background:#fafafa;border:1px solid #eee;border-radius:10px;margin-bottom:8px;}
.file-item .fi-name{font-weight:500;font-size:0.9rem;}
.file-item .fi-meta{font-size:0.75rem;color:#888;}
.file-item .fi-actions{display:flex;gap:6px;}
.empty{text-align:center;padding:24px;color:#aaa;font-size:0.9rem;}

.url-ejemplos{background:#fffaf5;border:1px dashed #e0c9a8;border-radius:10px;padding:12px;margin-top:10px;font-size:0.8rem;color:#8a6d3b;}
.url-ejemplos code{background:#fff;padding:2px 6px;border-radius:4px;font-size:0.75rem;}
</style>
</head>
<body>
<div class="container">

<!-- Banner IP -->
<div class="ip-banner">
    <div>
        <div class="label">📍 IP de este servidor</div>
        <div class="value"><?= htmlspecialchars($ip_servidor) ?></div>
    </div>
    <div style="text-align:right;">
        <div class="small">Host: <strong><?= htmlspecialchars($host) ?></strong></div>
        <div class="small">Protocolo: <strong><?= strtoupper($protocolo) ?></strong></div>
    </div>
</div>

<div class="card">
    <h1>🔗 GitHub Bridge</h1>
    <p class="subtitle">Conecta una URL de GitHub → descarga → lee → ejecuta en este servidor</p>
    
    <?php if ($error): ?>
    <div class="msg error">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="form-group">
            <label>URL de GitHub (archivo raw o normal)</label>
            <input type="url" name="url_github" placeholder="https://github.com/usuario/repo/blob/main/archivo.php" required value="<?= htmlspecialchars($_POST['url_github'] ?? '') ?>">
            <div class="url-ejemplos">
                💡 Ejemplos válidos:<br>
                <code>https://raw.githubusercontent.com/user/repo/main/file.php</code><br>
                <code>https://github.com/user/repo/blob/main/file.php</code> (se convierte automáticamente)
            </div>
        </div>
        
        <div class="form-group">
            <label>¿Qué hacer con el archivo?</label>
            <div class="acciones-grid">
                <label class="accion-option">
                    <input type="radio" name="accion" value="ver" checked>
                    <div class="title">👁️ Solo ver contenido</div>
                    <div class="desc">Muestra el código sin ejecutar</div>
                </label>
                <label class="accion-option">
                    <input type="radio" name="accion" value="guardar">
                    <div class="title">💾 Guardar en servidor</div>
                    <div class="desc">Descarga y crea URL pública</div>
                </label>
                <label class="accion-option">
                    <input type="radio" name="accion" value="ejecutar">
                    <div class="title">⚡ Ejecutar código PHP</div>
                    <div class="desc">Corre el código y muestra salida</div>
                </label>
                <label class="accion-option">
                    <input type="radio" name="accion" value="guardar_ejecutar">
                    <div class="title">🚀 Guardar + Ejecutar</div>
                    <div class="desc">Guarda archivo y lo ejecuta</div>
                </label>
            </div>
        </div>
        
        <div class="msg warn">
            ⚠️ <strong>Seguridad:</strong> La opción "Ejecutar" corre código PHP remoto. Úsala SOLO con archivos de tu total confianza.
        </div>
        
        <button type="submit" class="btn">🔗 Conectar con GitHub</button>
    </form>
</div>

<?php if ($resultado): ?>
<div class="card">
    <h2><?= $resultado['titulo'] ?></h2>
    
    <div class="result-box">
        <div class="r-meta">
            <div class="meta-item">
                <div class="ml">Archivo</div>
                <div class="mv"><?= htmlspecialchars($resultado['nombre']) ?></div>
            </div>
            <div class="meta-item">
                <div class="ml">Tamaño</div>
                <div class="mv"><?= number_format($resultado['tamanio']) ?> bytes</div>
            </div>
            <div class="meta-item">
                <div class="ml">IP servidor</div>
                <div class="mv"><?= htmlspecialchars($ip_servidor) ?></div>
            </div>
        </div>
        
        <div class="r-meta" style="margin-bottom:14px;">
            <div class="meta-item" style="grid-column:1/-1;">
                <div class="ml">URL GitHub</div>
                <div class="mv" style="font-size:0.72rem;"><?= htmlspecialchars($resultado['url']) ?></div>
            </div>
        </div>
        
        <?php if ($resultado['accion'] === 'ver'): ?>
            <pre><?= htmlspecialchars($resultado['contenido']) ?></pre>
        <?php endif; ?>
        
        <?php if (isset($resultado['ruta_local'])): ?>
        <div class="r-meta" style="margin-bottom:14px;">
            <div class="meta-item" style="grid-column:1/-1;">
                <div class="ml">📁 Ruta en servidor</div>
                <div class="mv" style="font-size:0.75rem;"><?= htmlspecialchars($resultado['ruta_local']) ?></div>
            </div>
            <div class="meta-item" style="grid-column:1/-1;">
                <div class="ml">🌐 URL pública</div>
                <div class="mv" style="font-size:0.75rem;"><a href="<?= htmlspecialchars($resultado['url_publica']) ?>" target="_blank" style="color:#c47046;"><?= htmlspecialchars($resultado['url_publica']) ?></a></div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (isset($resultado['salida'])): ?>
            <div class="r-title">📤 Salida de ejecución:</div>
            <div class="output"><?= htmlspecialchars($resultado['salida']) ?></div>
        <?php endif; ?>
        
        <?php if (isset($resultado['url_publica'])): ?>
        <div class="actions">
            <button class="btn btn-small btn-sec" onclick="copiar('<?= htmlspecialchars($resultado['url_publica']) ?>', this)">📋 Copiar URL pública</button>
            <a href="<?= htmlspecialchars($resultado['url_publica']) ?>" target="_blank">🔗 Abrir en nueva pestaña</a>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Archivos guardados -->
<div class="card">
    <h2>📂 Archivos guardados en este servidor (<?= count($archivos_guardados) ?>)</h2>
    <?php if (empty($archivos_guardados)): ?>
        <div class="empty">No hay archivos guardados aún</div>
    <?php else: ?>
        <?php foreach ($archivos_guardados as $f): ?>
        <div class="file-item">
            <div>
                <div class="fi-name">📄 <?= htmlspecialchars($f['nombre']) ?></div>
                <div class="fi-meta"><?= $f['fecha'] ?> • <?= $f['tamanio'] ?></div>
            </div>
            <div class="fi-actions">
                <button class="btn btn-small btn-sec" onclick="copiar('<?= htmlspecialchars($f['url']) ?>', this)">📋</button>
                <a href="<?= htmlspecialchars($f['url']) ?>" target="_blank" class="btn btn-small">🔗</a>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</div>

<script>
function copiar(texto, btn) {
    navigator.clipboard.writeText(texto).then(() => {
        const original = btn.textContent;
        btn.textContent = '✅';
        setTimeout(() => btn.textContent = original, 1200);
    });
}
</script>

</body>
</html>
