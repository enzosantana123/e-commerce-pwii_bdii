<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/conexao.php';
atualizarTipoUsuario($pdo);
exigirLogin();
$id=(int)$_SESSION['usuario_id'];
$erro='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    validarCsrf();
    if (($_POST['confirmar'] ?? '') !== 'sim') { $erro='Confirme a exclusão permanente.'; }
    else {
        try {
            $pdo->beginTransaction();
            $stmt=$pdo->prepare('SELECT senha_usuario FROM usuario WHERE id_usuario=? FOR UPDATE');
            $stmt->execute([$id]); $hash=$stmt->fetchColumn();
            if (!$hash || !password_verify($_POST['senha_atual'] ?? '',$hash)) throw new RuntimeException('Senha atual incorreta.');
            $pdo->prepare('DELETE FROM usuario WHERE id_usuario=?')->execute([$id]);
            $pdo->commit();
            require __DIR__.'/logout.php';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $erro=$e instanceof PDOException ? 'Não foi possível excluir sua conta.' : $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Excluir conta | Chronnus</title><link rel="icon" href="imgs/favicon.png"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&family=Orbitron:wght@500;700&display=swap" rel="stylesheet"><?php include __DIR__.'/includes/estilo-auth.php'; ?></head><body><main class="auth-wrap"><a class="voltar" href="usuario.php">← Minha conta</a><section class="auth-card"><img class="logo" src="imgs/logo.png" alt="Chronnus"><h1>Deseja mesmo excluir sua conta?</h1><p class="subtitulo">Esta ação é irreversível. Seu cadastro, endereços, pedidos e produtos vinculados serão excluídos. Isso também pode remover itens dos históricos de outros compradores e vendedores. Os totais de pedidos de outros compradores podem continuar registrados sem os itens excluídos.</p><?php if ($erro): ?><div class="mensagem"><?= h($erro) ?></div><?php endif; ?><form method="POST"><input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><div class="campo"><label for="senha_atual">Confirme sua senha atual</label><input id="senha_atual" name="senha_atual" type="password" required autocomplete="current-password"></div><label style="display:flex;gap:10px;align-items:center;margin:15px 0"><input style="width:auto" type="checkbox" name="confirmar" value="sim" required> Entendo as consequências e desejo excluir minha conta permanentemente.</label><button class="botao" style="background:#ffb0a8;color:#411414" type="submit">Excluir conta permanentemente</button></form><p class="alternativa"><a href="usuario.php">Cancelar</a></p></section></main></body></html>
