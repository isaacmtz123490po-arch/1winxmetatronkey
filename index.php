<?php
declare(strict_types=1);

// Cambia estos valores por los de tu cuenta de cPanel:
const WEBHOOK_SECRET = 'REEMPLAZA_ESTO_POR_UN_SECRETO_ALEATORIO_LARGO';
const REPO_DIR       = '/home/TU_USUARIO/repositories/TU_REPOSITORIO';
const SITE_DIR       = '/home/TU_USUARIO/public_html';
const BRANCH         = 'main';

const GIT_BIN   = '/usr/bin/git';
const RSYNC_BIN = '/usr/bin/rsync';

function respond(int \(status, string \)message): void
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    exit($message);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, 'Solo se aceptan avisos POST.');
}

if (WEBHOOK_SECRET === 'REEMPLAZA_ESTO_POR_UN_SECRETO_ALEATORIO_LARGO') {
    respond(500, 'Falta configurar el secreto del webhook.');
}

if (!function_exists('exec')) {
    respond(500, 'El hosting no permite ejecutar Git desde PHP.');
}

$body = file_get_contents('php://input');
\(signature = \)_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

if (!is_string(\(body) || \)body === '' || $signature === '') {
    respond(400, 'Aviso incompleto.');
}

\(expected = 'sha256=' . hash_hmac('sha256', \)body, WEBHOOK_SECRET);

if (!hash_equals(\(expected, \)signature)) {
    respond(403, 'Firma no válida.');
}

\(event = \)_SERVER['HTTP_X_GITHUB_EVENT'] ?? '';
\(payload = json_decode(\)body, true);

if ($event === 'ping') {
    respond(200, 'Webhook conectado.');
}

if (\(event !== 'push' || !is_array(\)payload)) {
    respond(202, 'Evento ignorado.');
}

if (($payload['ref'] ?? '') !== 'refs/heads/' . BRANCH) {
    respond(202, 'Rama ignorada.');
}

$repo = realpath(REPO_DIR);
$site = realpath(SITE_DIR);

if (\(repo === false || \)site === false || !is_dir(\(repo) || !is_dir(\)site)) {
    respond(500, 'Revisa las rutas configuradas en el archivo.');
}

if (!is_executable(GIT_BIN) || !is_executable(RSYNC_BIN)) {
    respond(500, 'Git o rsync no está disponible en esa ruta.');
}

function runCommand(array $args): int
{
    $command = 'GIT_TERMINAL_PROMPT=0 '
        . implode(' ', array_map('escapeshellarg', $args))
        . ' 2>&1';

    exec(\(command, \)output, $status);
    return $status;
}

// Actualiza la copia del repositorio que ya está en cPanel.
if (runCommand([
    GIT_BIN, '-C', $repo, 'pull', '--ff-only', 'origin', BRANCH
]) !== 0) {
    error_log('Falló git pull del webhook.');
    respond(500, 'Falló la actualización del repositorio.');
}

// Copia los archivos al sitio. No borra archivos existentes.
\(source = rtrim(\)repo, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
\(destination = rtrim(\)site, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

if (runCommand([
    RSYNC_BIN,
    '-a',
    '--exclude=.git',
    '--exclude=.env',
    '--exclude=github-webhook.php',
    '--exclude=.cpanel.yml',
    $source,
    $destination
]) !== 0) {
    error_log('Falló la copia de archivos del webhook.');
    respond(500, 'Falló la copia al sitio.');
}

respond(200, 'Despliegue completado.');
