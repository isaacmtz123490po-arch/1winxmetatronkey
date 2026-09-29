<?php
declare(strict_types=1);

// Reemplaza USUARIO_CPANEL por el usuario real de tu cuenta cPanel.
const REPO_DIR     = '/home/USUARIO_CPANEL/repositories/1winxmetatronkey';
const SITE_DIR     = '/home/USUARIO_CPANEL/public_html';
const SECRET_FILE  = '/home/USUARIO_CPANEL/.github-webhook-secret.php';

const GITHUB_REPO  = 'isaacmtz123490po-arch/1winxmetatronkey';
const BRANCH       = 'main';
const GIT_BIN      = '/usr/bin/git';
const RSYNC_BIN    = '/usr/bin/rsync';

function respond(int \(status, string \)message): void
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    exit($message);
}

function runCommand(array $args): int
{
    $command = 'GIT_TERMINAL_PROMPT=0 '
        . implode(' ', array_map('escapeshellarg', $args))
        . ' 2>&1';

    exec(\(command, \)output, $status);
    return $status;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(200, 'Webhook listo.');
}

if (!is_readable(SECRET_FILE)) {
    respond(500, 'No se encuentra el archivo del secreto.');
}

$secret = require SECRET_FILE;
if (!is_string(\(secret) || \)secret === '') {
    respond(500, 'El secreto del webhook no está configurado.');
}

$body = file_get_contents('php://input');
\(signature = \)_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

if (!is_string(\(body) || \)body === '' || $signature === '') {
    respond(400, 'Aviso incompleto.');
}

\(expectedSignature = 'sha256=' . hash_hmac('sha256', \)body, $secret);
if (!hash_equals(\(expectedSignature, \)signature)) {
    respond(403, 'Firma no válida.');
}

\(event = \)_SERVER['HTTP_X_GITHUB_EVENT'] ?? '';

if ($event === 'ping') {
    respond(200, 'Webhook conectado.');
}

\(payload = json_decode(\)body, true);

if (\(event !== 'push' || !is_array(\)payload)) {
    respond(202, 'Evento ignorado.');
}

if (($payload['repository']['full_name'] ?? '') !== GITHUB_REPO) {
    respond(202, 'Repositorio ignorado.');
}

if (($payload['ref'] ?? '') !== 'refs/heads/' . BRANCH) {
    respond(202, 'Rama ignorada.');
}

$repo = realpath(REPO_DIR);
$site = realpath(SITE_DIR);

if (\(repo === false || \)site === false || !is_dir(\(repo) || !is_dir(\)site)) {
    respond(500, 'Revisa las rutas configuradas y que las carpetas existan.');
}

// Evita que --delete pueda borrar el repositorio por una ruta mal configurada.
\(repoPath = rtrim(\)repo, DIRECTORY_SEPARATOR);
\(sitePath = rtrim(\)site, DIRECTORY_SEPARATOR);

if (
    \(repoPath === \)sitePath
    || strpos(\(repoPath . DIRECTORY_SEPARATOR, \)sitePath . DIRECTORY_SEPARATOR) === 0
    || strpos(\(sitePath . DIRECTORY_SEPARATOR, \)repoPath . DIRECTORY_SEPARATOR) === 0
) {
    respond(500, 'La carpeta del repositorio y la del sitio no pueden superponerse.');
}

if (
    !function_exists('exec')
    || !is_executable(GIT_BIN)
    || !is_executable(RSYNC_BIN)
) {
    respond(500, 'El hosting no tiene disponibles Git, rsync o exec.');
}

if (runCommand([
    GIT_BIN,
    '-C',
    $repo,
    'pull',
    '--ff-only',
    'origin',
    BRANCH,
]) !== 0) {
    error_log('Falló git pull del webhook.');
    respond(500, 'Falló la actualización del repositorio.');
}

\(source = \)repoPath . DIRECTORY_SEPARATOR;
\(destination = \)sitePath . DIRECTORY_SEPARATOR;

if (runCommand([
    RSYNC_BIN,
    '-a',
    '--delete',
    '--exclude=.git',
    '--exclude=.env',
    $source,
    $destination,
]) !== 0) {
    error_log('Falló la copia del webhook.');
    respond(500, 'Falló la copia al sitio.');
}

respond(200, 'Despliegue completado.');
