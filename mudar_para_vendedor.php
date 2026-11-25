<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/conexao.php';
atualizarTipoUsuario($pdo);
exigirLogin();

$erro = '';
if (tipoUsuario() === 'vendedor') {
    header('Location: usuario.php');
    exit;
}
if (tipoUsuario() !== 'cliente') {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
    if (($_POST['confirmar'] ?? '') !== 'sim') { http_response_code(400); exit('Confirmação necessária.'); }
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'SELECT id_usuario, nome_usuario, email_usuario, telefone_usuario, cpf_usuario, tipo_usuario
             FROM usuario WHERE id_usuario = ? FOR UPDATE'
        );
        $stmt->execute([(int)$_SESSION['usuario_id']]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            throw new RuntimeException('Não foi possível localizar sua conta.');
        }
        if ($usuario['tipo_usuario'] !== 'cliente') {
            throw new RuntimeException('Esta conta não pode ser convertida.');
        }

        $perfil = $pdo->prepare('SELECT id_vende, id_usuario FROM vendedor WHERE email_vende = ? OR cpf_vende = ? LIMIT 1');
        $perfil->execute([$usuario['email_usuario'], $usuario['cpf_usuario']]);
        $vendedorExistente = $perfil->fetch();

        if ($vendedorExistente) {
            if (!empty($vendedorExistente['id_usuario']) && (int)$vendedorExistente['id_usuario'] !== (int)$usuario['id_usuario']) {
                throw new RuntimeException('Já existe um perfil de vendedor associado a estes dados.');
            }
            $atualizaPerfil = $pdo->prepare('UPDATE vendedor SET id_usuario = ? WHERE id_vende = ?');
            $atualizaPerfil->execute([(int)$usuario['id_usuario'], (int)$vendedorExistente['id_vende']]);
        } else {
            $inserePerfil = $pdo->prepare(
                'INSERT INTO vendedor (nome_vende, email_vende, telefone_vende, cpf_vende, id_usuario)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $inserePerfil->execute([
                $usuario['nome_usuario'], $usuario['email_usuario'], $usuario['telefone_usuario'],
                $usuario['cpf_usuario'], (int)$usuario['id_usuario']
            ]);
        }

        $atualizaUsuario = $pdo->prepare("UPDATE usuario SET tipo_usuario = 'vendedor' WHERE id_usuario = ?");
        $atualizaUsuario->execute([(int)$usuario['id_usuario']]);
        $pdo->commit();

        session_regenerate_id(true);
        $_SESSION['usuario_tipo'] = 'vendedor';
        header('Location: usuario.php');
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $erro = $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível alterar o tipo da conta. Verifique a atualização do banco.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <link rel="icon" type="image/png" href="imgs/favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quero vender | Chronnus</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&family=Orbitron:wght@500;700&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/includes/estilo-auth.php'; ?>
</head>
<body>
<main class="auth-wrap">
    <a class="voltar" href="index.php">← Voltar para a loja</a>
    <section class="auth-card">
        <img class="logo" src="imgs/logo.png" alt="Chronnus">
        <h1>Deseja mesmo se tornar um vendedor nesta conta?</h1>
        <p class="subtitulo">Sua conta de cliente será convertida em conta de vendedor. Seus dados e histórico de cliente serão mantidos. Você poderá cadastrar e vender produtos, além de continuar comprando normalmente.</p>
        <?php if ($erro): ?><div class="mensagem"><?= h($erro) ?></div><?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <button class="botao" type="submit" name="confirmar" value="sim">Sim, quero vender na Chronnus</button>
        </form>
        <p class="alternativa"><a href="usuario.php">Agora não</a></p>
    </section>
</main>
</body>
</html>
