<?php
// Sessão única para todas as páginas autenticadas.
if (session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

function h($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validarCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Solicitação inválida. Atualize a página e tente novamente.');
    }
}

function usuarioLogado(): bool
{
    return !empty($_SESSION['usuario_id']);
}

function caminhoBaseAplicacao(): string
{
    $diretorio = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    if (substr($diretorio, -9) === '/vendedor') {
        $diretorio = substr($diretorio, 0, -9);
    }
    return rtrim($diretorio, '/');
}

function exigirLogin(): void
{
    if (!usuarioLogado()) {
        header('Location: ' . caminhoBaseAplicacao() . '/login.php');
        exit;
    }
}

function atualizarTipoUsuario(PDO $pdo): void
{
    if (!usuarioLogado()) return;
    $stmt = $pdo->prepare('SELECT tipo_usuario FROM usuario WHERE id_usuario = ?');
    $stmt->execute([(int)$_SESSION['usuario_id']]);
    $tipo = $stmt->fetchColumn();
    if (!$tipo) { $_SESSION = []; exigirLogin(); }
    $_SESSION['usuario_tipo'] = $tipo;
}

function exigirVendedor(): void
{
    exigirLogin();
    if (($_SESSION['usuario_tipo'] ?? '') !== 'vendedor') {
        header('Location: ' . caminhoBaseAplicacao() . '/index.php');
        exit;
    }
}

function nomeUsuario(): string
{
    return (string)($_SESSION['usuario_nome'] ?? '');
}

function tipoUsuario(): string
{
    return (string)($_SESSION['usuario_tipo'] ?? '');
}
