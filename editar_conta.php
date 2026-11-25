<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/conexao.php';
atualizarTipoUsuario($pdo);
exigirLogin();
$id = (int)$_SESSION['usuario_id'];
$stmt = $pdo->prepare('SELECT * FROM usuario WHERE id_usuario = ?');
$stmt->execute([$id]);
$conta = $stmt->fetch();
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
    $nome = trim($_POST['nome'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $telefone = preg_replace('/\D+/', '', $_POST['telefone'] ?? '');
    $cpf = preg_replace('/\D+/', '', $_POST['cpf'] ?? '');
    $senha = $_POST['nova_senha'] ?? '';
    $atual = $_POST['senha_atual'] ?? '';
    $confirmacao = $_POST['confirmacao'] ?? '';
    if (!$nome || (function_exists('mb_strlen') ? mb_strlen($nome) : strlen($nome)) > 80 || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>50 || strlen($telefone)<10 || strlen($telefone)>15 || strlen($cpf)!==11 || ($senha !== '' && strlen($senha)<8) || $senha !== $confirmacao) {
        $erro = 'Confira os dados e a confirmação da nova senha (mínimo de 8 caracteres).';
    } else {
        try {
            $pdo->beginTransaction();
            $lock = $pdo->prepare('SELECT senha_usuario FROM usuario WHERE id_usuario = ? FOR UPDATE');
            $lock->execute([$id]);
            $hashAtual = $lock->fetchColumn();
            if (!$hashAtual || !password_verify($atual,$hashAtual)) throw new RuntimeException('Senha atual incorreta.');
            $hash = $senha !== '' ? password_hash($senha,PASSWORD_DEFAULT) : $hashAtual;
            foreach (['cliente'=>'clien','vendedor'=>'vende'] as $tabela=>$sufixo) {
                $check = $pdo->prepare("SELECT 1 FROM $tabela WHERE (id_usuario IS NULL OR id_usuario <> ?) AND (email_$sufixo = ? OR telefone_$sufixo = ? OR cpf_$sufixo = ?) LIMIT 1");
                $check->execute([$id,$email,$telefone,$cpf]);
                if ($check->fetchColumn()) throw new RuntimeException('E-mail, telefone ou CPF já utilizado em outro cadastro.');
            }
            $pdo->prepare('UPDATE usuario SET nome_usuario=?,email_usuario=?,telefone_usuario=?,cpf_usuario=?,senha_usuario=? WHERE id_usuario=?')->execute([$nome,$email,$telefone,$cpf,$hash,$id]);
            $pdo->prepare('UPDATE cliente SET nome_clien=?,email_clien=?,telefone_clien=?,cpf_clien=?,senha_clien=? WHERE id_usuario=?')->execute([$nome,$email,$telefone,$cpf,$hash,$id]);
            $pdo->prepare('UPDATE vendedor SET nome_vende=?,email_vende=?,telefone_vende=?,cpf_vende=? WHERE id_usuario=?')->execute([$nome,$email,$telefone,$cpf,$id]);
            $pdo->commit();
            $_SESSION['usuario_nome']=$nome;
            session_regenerate_id(true);
            header('Location: editar_conta.php?salvo=1'); exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $erro = $e instanceof PDOException ? 'Não foi possível salvar. Confira se os dados já estão em uso.' : ''.$e->getMessage();
        }
    }
}
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Atualizar conta | Chronnus</title><link rel="icon" href="imgs/favicon.png"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&family=Orbitron:wght@500;700&display=swap" rel="stylesheet"><?php include __DIR__.'/includes/estilo-auth.php'; ?></head><body><main class="auth-wrap"><a class="voltar" href="usuario.php">← Minha conta</a><section class="auth-card"><img class="logo" src="imgs/logo.png" alt="Chronnus"><h1>Atualizar meus dados</h1><p class="subtitulo">Mantenha seus dados atualizados.</p>
<?php if ($erro): ?><div class="mensagem"><?= h($erro) ?></div><?php elseif (isset($_GET['salvo'])): ?><p role="status" style="color:#33ebaa">Dados atualizados com sucesso!</p><?php endif; ?>
<form method="POST" action="editar_conta.php"><input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
<?php foreach (['nome'=>['Nome completo','text',80],'email'=>['E-mail','email',50],'telefone'=>['Telefone','tel',15],'cpf'=>['CPF','text',11]] as $campo=>$info): ?><div class="campo"><label for="<?= $campo ?>"><?= $info[0] ?></label><input id="<?= $campo ?>" name="<?= $campo ?>" type="<?= $info[1] ?>" maxlength="<?= $info[2] ?>" required value="<?= h($_POST[$campo] ?? $conta[$campo.'_usuario']) ?>"></div><?php endforeach; ?>
<div class="campo"><label for="nova_senha">Nova senha (opcional)</label><input id="nova_senha" name="nova_senha" type="password" minlength="8" autocomplete="new-password"></div><div class="campo"><label for="confirmacao">Confirmar nova senha</label><input id="confirmacao" name="confirmacao" type="password" autocomplete="new-password"></div><div class="campo"><label for="senha_atual">Senha atual (obrigatória)</label><input id="senha_atual" name="senha_atual" type="password" required autocomplete="current-password"></div><button class="botao" type="submit">Salvar alterações</button></form></section></main></body></html>
