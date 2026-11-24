<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/conexao.php';

$erro = '';
$tipo = $_POST['tipo'] ?? ($_GET['tipo'] ?? 'cliente');
$tipo = in_array($tipo, ['cliente', 'vendedor'], true) ? $tipo : 'cliente';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();

    $nome = trim($_POST['nome'] ?? '');
    $emailDigitado = trim($_POST['email'] ?? '');
    $email = function_exists('mb_strtolower') ? mb_strtolower($emailDigitado, 'UTF-8') : strtolower($emailDigitado);
    $telefone = preg_replace('/\D+/', '', $_POST['telefone'] ?? '');
    $cpf = preg_replace('/\D+/', '', $_POST['cpf'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $confirmacao = $_POST['confirmacao'] ?? '';

    $tamanhoNome = function_exists('mb_strlen') ? mb_strlen($nome, 'UTF-8') : strlen($nome);
    if ($nome === '' || $tamanhoNome > 80 || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($email) > 50 || strlen($telefone) < 10 || strlen($telefone) > 15 || strlen($cpf) !== 11
        || strlen($senha) < 8) {
        $erro = 'Preencha os campos corretamente. A senha deve ter pelo menos 8 caracteres.';
    } elseif ($senha !== $confirmacao) {
        $erro = 'As senhas não coincidem.';
    } else {
        try {
            $verifica = $pdo->prepare(
                'SELECT id_usuario FROM usuario WHERE email_usuario = ? OR telefone_usuario = ? OR cpf_usuario = ? LIMIT 1'
            );
            $verifica->execute([$email, $telefone, $cpf]);

            $perfilExistente = $pdo->prepare(
                'SELECT email_clien AS email FROM cliente WHERE email_clien = ? OR telefone_clien = ? OR cpf_clien = ?
                 UNION ALL
                 SELECT email_vende AS email FROM vendedor WHERE email_vende = ? OR telefone_vende = ? OR cpf_vende = ?'
            );
            $perfilExistente->execute([$email, $telefone, $cpf, $email, $telefone, $cpf]);

            if ($verifica->fetch() || $perfilExistente->fetch()) {
                $erro = 'Já existe um cadastro com este e-mail, telefone ou CPF. Entre na sua conta para continuar.';
            } else {
                $hash = password_hash($senha, PASSWORD_DEFAULT);
                $pdo->beginTransaction();

                $stmtUsuario = $pdo->prepare(
                    'INSERT INTO usuario (nome_usuario, email_usuario, telefone_usuario, cpf_usuario, senha_usuario, tipo_usuario)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmtUsuario->execute([$nome, $email, $telefone, $cpf, $hash, $tipo]);
                $idUsuario = (int)$pdo->lastInsertId();

                if ($tipo === 'vendedor') {
                    $stmtPerfil = $pdo->prepare(
                        'INSERT INTO vendedor (nome_vende, email_vende, telefone_vende, cpf_vende, id_usuario)
                         VALUES (?, ?, ?, ?, ?)'
                    );
                } else {
                    $stmtPerfil = $pdo->prepare(
                        'INSERT INTO cliente (nome_clien, email_clien, telefone_clien, cpf_clien, senha_clien, id_usuario)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    );
                }

                $dadosPerfil = [$nome, $email, $telefone, $cpf];
                if ($tipo === 'cliente') {
                    $dadosPerfil[] = $hash;
                }
                $dadosPerfil[] = $idUsuario;
                $stmtPerfil->execute($dadosPerfil);

                $pdo->commit();

                session_regenerate_id(true);
                $_SESSION['usuario_id'] = $idUsuario;
                $_SESSION['usuario_nome'] = $nome;
                $_SESSION['usuario_tipo'] = $tipo;
                header('Location: ' . ($tipo === 'vendedor' ? 'vendedor/index.php' : 'index.php'));
                exit;
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erro = 'Não foi possível concluir o cadastro. Confira se a atualização do banco foi executada.';
        }
    }
}

$ehVendedor = $tipo === 'vendedor';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <link rel="icon" type="image/png" href="imgs/favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $ehVendedor ? 'Cadastro de vendedor' : 'Criar conta' ?> | Chronnus</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&family=Orbitron:wght@500;700&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/includes/estilo-auth.php'; ?>
    <style>
        .alternar-tipo{display:block;width:100%;margin-top:12px;padding:11px 14px;border:1px solid var(--verde);border-radius:9px;background:transparent;color:var(--verde-claro);font-weight:700;cursor:pointer}
        .alternar-tipo:hover{background:rgba(51,235,170,.1)}
        .tipo-aviso{text-align:center;color:var(--muted);font-size:12px;margin:0 0 18px}
    </style>
</head>
<body>
<main class="auth-wrap">
    <a class="voltar" href="index.php">← Voltar para a loja</a>
    <section class="auth-card">
        <img class="logo" src="imgs/logo.png" alt="Chronnus">
        <h1 id="titulo-cadastro"><?= $ehVendedor ? 'Quero vender' : 'Criar conta' ?></h1>
        <p class="subtitulo" id="subtitulo-cadastro"><?= $ehVendedor ? 'Crie sua conta de vendedor e publique seus produtos.' : 'Cadastre-se para fazer parte da Chronnus.' ?></p>
        <?php if ($erro): ?><div class="mensagem"><?= h($erro) ?></div><?php endif; ?>

        <form method="POST" action="cadastro.php" autocomplete="on">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="tipo" id="tipo-cadastro" value="<?= h($tipo) ?>">

            <div class="campo">
                <label for="nome">Nome completo</label>
                <input id="nome" name="nome" maxlength="80" required autocomplete="name" value="<?= h($_POST['nome'] ?? '') ?>">
            </div>
            <div class="campo">
                <label for="email">E-mail</label>
                <input id="email" name="email" type="email" maxlength="50" required autocomplete="email" value="<?= h($_POST['email'] ?? '') ?>">
            </div>
            <div class="grade">
                <div class="campo">
                    <label for="telefone">Telefone</label>
                    <input id="telefone" name="telefone" type="tel" maxlength="15" placeholder="11999999999" required autocomplete="tel" value="<?= h($_POST['telefone'] ?? '') ?>">
                </div>
                <div class="campo">
                    <label for="cpf">CPF (somente números)</label>
                    <input id="cpf" name="cpf" inputmode="numeric" maxlength="11" placeholder="00000000000" required value="<?= h($_POST['cpf'] ?? '') ?>">
                </div>
            </div>
            <div class="campo">
                <label for="senha">Senha</label>
                <input id="senha" name="senha" type="password" minlength="8" required autocomplete="new-password">
            </div>
            <div class="campo">
                <label for="confirmacao">Confirmar senha</label>
                <input id="confirmacao" name="confirmacao" type="password" minlength="8" required autocomplete="new-password">
            </div>
            <button class="botao" type="submit" id="botao-cadastro"><?= $ehVendedor ? 'Criar conta de vendedor' : 'Criar minha conta' ?></button>
        </form>

        <button class="alternar-tipo" type="button" id="alternar-tipo">
            <?= $ehVendedor ? 'Quero criar uma conta de cliente' : 'Quero vender na Chronnus' ?>
        </button>
        <p class="alternativa">Já tem uma conta? <a href="login.php">Entrar</a></p>
    </section>
</main>
<script>
(() => {
    const tipo = document.getElementById('tipo-cadastro');
    const titulo = document.getElementById('titulo-cadastro');
    const subtitulo = document.getElementById('subtitulo-cadastro');
    const botao = document.getElementById('botao-cadastro');
    const alternar = document.getElementById('alternar-tipo');

    alternar.addEventListener('click', () => {
        const vaiSerVendedor = tipo.value !== 'vendedor';
        tipo.value = vaiSerVendedor ? 'vendedor' : 'cliente';
        titulo.textContent = vaiSerVendedor ? 'Quero vender' : 'Criar conta';
        subtitulo.textContent = vaiSerVendedor
            ? 'Crie sua conta de vendedor e publique seus produtos.'
            : 'Cadastre-se para fazer parte da Chronnus.';
        botao.textContent = vaiSerVendedor ? 'Criar conta de vendedor' : 'Criar minha conta';
        alternar.textContent = vaiSerVendedor
            ? 'Quero criar uma conta de cliente'
            : 'Quero vender na Chronnus';
    });
})();
</script>
</body>
</html>
