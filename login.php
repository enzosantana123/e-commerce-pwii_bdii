<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/conexao.php';

if (usuarioLogado()) {
    header('Location: ' . (tipoUsuario() === 'vendedor' ? 'vendedor/index.php' : 'index.php'));
    exit;
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
    $emailDigitado = trim($_POST['email'] ?? '');
    $email = function_exists('mb_strtolower') ? mb_strtolower($emailDigitado, 'UTF-8') : strtolower($emailDigitado);
    $senha = $_POST['senha'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $senha === '') {
        $erro = 'Informe um e-mail válido e sua senha.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT id_usuario, nome_usuario, email_usuario, senha_usuario, tipo_usuario
             FROM usuario WHERE email_usuario = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($senha, $usuario['senha_usuario'])) {
            session_regenerate_id(true);
            $_SESSION['usuario_id'] = (int)$usuario['id_usuario'];
            $_SESSION['usuario_nome'] = $usuario['nome_usuario'];
            $_SESSION['usuario_tipo'] = $usuario['tipo_usuario'];
            
            $destino = $_GET['redirect'] ?? '';
            if ($usuario['tipo_usuario'] === 'vendedor') {
                $destino = 'vendedor/index.php';
            } elseif (!preg_match('/^compra\.php\?id=\d+$/', $destino)) {
                $destino = 'index.php';
            }
            header('Location: ' . $destino);
            exit;
        }
        $erro = 'E-mail ou senha incorretos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <link rel="icon" type="image/png" href="imgs/favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar | Chronnus</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&family=Orbitron:wght@500;700&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/includes/estilo-auth.php'; ?>
</head>
<body>
<main class="auth-wrap">
    <a class="voltar" href="index.php">← Voltar para a loja</a>
    <section class="auth-card">
        <img class="logo" src="imgs/logo.png" alt="Chronnus">
        <h1>Bem-vindo de volta</h1>
        <p class="subtitulo">Entre na sua conta Chronnus.</p>
        <?php if ($erro): ?><div class="mensagem"><?= h($erro) ?></div><?php endif; ?>
        <form method="POST" action="login.php<?= !empty($_GET['redirect']) ? '?redirect=' . rawurlencode($_GET['redirect']) : '' ?>">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <div class="campo">
                <label for="email">E-mail</label>
                <input id="email" name="email" type="email" maxlength="50" required autocomplete="email" value="<?= h($_POST['email'] ?? '') ?>">
            </div>
            <div class="campo">
                <label for="senha">Senha</label>
                <input id="senha" name="senha" type="password" required autocomplete="current-password">
            </div>
            <button class="botao" type="submit">Entrar</button>
        </form>
        <p class="alternativa">Ainda não tem cadastro? <a href="cadastro.php">Criar conta</a></p>
    </section>
</main>
</body>
</html>
