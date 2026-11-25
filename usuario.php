<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/conexao.php';
atualizarTipoUsuario($pdo);
exigirLogin();
$idUsuario = (int)$_SESSION['usuario_id'];
$stmt = $pdo->prepare('SELECT nome_usuario, email_usuario, tipo_usuario FROM usuario WHERE id_usuario = ?');
$stmt->execute([$idUsuario]);
$conta = $stmt->fetch();
if (!$conta) { http_response_code(403); exit('Conta não encontrada.'); }
$ehVendedor = $conta['tipo_usuario'] === 'vendedor';
$_SESSION['usuario_tipo'] = $conta['tipo_usuario'];
$produtos = $vendas = $compras = [];
$pedidos = 0; $unidades = 0;
if ($ehVendedor) {
    $stmt = $pdo->prepare("SELECT p.id_produ, p.nome_produ, p.imagem_produ, p.preco_produ, p.desconto_produ,
        COUNT(DISTINCT CASE WHEN pe.status <> 'cancelado' THEN i.id_pedido END) AS compras,
        COALESCE(SUM(CASE WHEN pe.status <> 'cancelado' THEN i.quantidade ELSE 0 END),0) AS unidades
        FROM vendedor v JOIN produto p ON p.id_vende = v.id_vende
        LEFT JOIN item_pedido i ON i.id_produ = p.id_produ LEFT JOIN pedido pe ON pe.id_pedido = i.id_pedido
        WHERE v.id_usuario = ? GROUP BY p.id_produ,p.nome_produ,p.imagem_produ,p.preco_produ,p.desconto_produ ORDER BY p.id_produ DESC");
    $stmt->execute([$idUsuario]); $produtos = $stmt->fetchAll();
    $stmt = $pdo->prepare('SELECT pe.id_pedido, pe.data_pedido, pe.status, p.nome_produ, i.quantidade, i.preco_final, c.nome_clien
        FROM vendedor v JOIN produto p ON p.id_vende = v.id_vende JOIN item_pedido i ON i.id_produ = p.id_produ
        JOIN pedido pe ON pe.id_pedido = i.id_pedido JOIN cliente c ON c.id_clien = pe.id_clien
        WHERE v.id_usuario = ? ORDER BY pe.data_pedido DESC, pe.id_pedido DESC');
    $stmt->execute([$idUsuario]); $vendas = $stmt->fetchAll();
    $ids = [];
    foreach ($vendas as $venda) { if ($venda['status'] !== 'cancelado') { $ids[$venda['id_pedido']] = true; $unidades += (int)$venda['quantidade']; } }
    $pedidos = count($ids);
}
$stmt = $pdo->prepare('SELECT pe.id_pedido, pe.data_pedido, pe.status, pe.valor_total, p.nome_produ, i.quantidade, i.preco_final
    FROM cliente c JOIN pedido pe ON pe.id_clien = c.id_clien LEFT JOIN item_pedido i ON i.id_pedido = pe.id_pedido
    LEFT JOIN produto p ON p.id_produ = i.id_produ WHERE c.id_usuario = ? ORDER BY pe.data_pedido DESC, pe.id_pedido DESC');
$stmt->execute([$idUsuario]); $compras = $stmt->fetchAll();
$carrinhoUsuario = (isset($_SESSION['carrinho']) && is_array($_SESSION['carrinho'])) ? $_SESSION['carrinho'] : [];
$unidadesCarrinhoUsuario = array_sum(array_map(static fn($quantidade) => max(0, (int)$quantidade), $carrinhoUsuario));
function moedaConta($valor): string { return 'R$ ' . number_format((float)$valor, 2, ',', '.'); }
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Minha conta | Chronnus</title><link rel="icon" href="imgs/favicon.png"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&family=Orbitron:wght@500;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}body{margin:0;background:#011815;color:#f8f0e3;font:15px Inter,Arial,sans-serif}a{color:inherit;text-decoration:none}header{background:linear-gradient(100deg,#2d334a,#293740,#44816d);border-bottom:1px solid #37d4ad44}nav{max-width:1320px;margin:auto;padding:14px 24px;display:flex;align-items:center;justify-content:space-between;gap:15px;flex-wrap:wrap}nav img{height:48px;max-width:210px;object-fit:contain}nav div,.actions{display:flex;gap:12px;flex-wrap:wrap;align-items:center}main{max-width:1320px;margin:36px auto;padding:0 24px 50px}h1,h2{font-family:Orbitron,sans-serif}h1{font-size:clamp(23px,4vw,36px);margin:12px 0}h2{font-size:19px;margin:0 0 20px;color:#3bffb9}p{color:#d9c5c5;line-height:1.6}.tag{display:inline-block;border:1px solid #33ebaa66;color:#3bffb9;padding:6px 12px;border-radius:30px;font-size:12px;text-transform:uppercase}.btn{display:inline-block;background:#33ebaa;color:#102d28;border-radius:9px;padding:12px 17px;font-weight:700}.secondary{background:transparent;color:#3bffb9;border:1px solid #33ebaa66}.panel,.metric{background:linear-gradient(135deg,#2d334a,#293740);border:1px solid #37d4ad33;border-radius:18px;padding:24px;margin-top:24px}.metrics{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px}.metric span{display:block;color:#d9c5c5;margin-bottom:12px}.metric strong{font:700 30px Orbitron;color:#3bffb9}.products{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:16px}.product{background:#102c29;border:1px solid #37d4ad33;border-radius:12px;padding:16px;min-width:0}.product img{width:100%;height:150px;object-fit:contain}.product h3{font-size:16px;overflow-wrap:anywhere}.product strong{color:#3bffb9}.product p{font-size:13px}.scroll{overflow-x:auto}table{width:100%;border-collapse:collapse;min-width:650px}th,td{padding:13px 12px;text-align:left;border-bottom:1px solid #37d4ad26}th{color:#3bffb9;font-size:12px}td{font-size:14px}.status{font-weight:700}.status-pago{color:#3bffb9}.status-pendente{color:#f4d591}.status-cancelado{color:#ffb0a8}.muted{font-size:13px}.cart-summary{display:flex;align-items:center;justify-content:space-between;gap:18px;flex-wrap:wrap}.cart-summary strong{display:block;font:700 22px Orbitron;color:#3bffb9;margin-top:5px}.danger-actions{display:flex;gap:12px;flex-wrap:wrap;align-items:center}.danger-actions .btn{margin:0}.danger{background:transparent;color:#ffb0a8;border:1px solid #ffb0a8}.danger:hover{background:rgba(255,176,168,.1)}@media(max-width:600px){main{padding:0 15px 35px;margin-top:24px}.panel{padding:16px}nav{padding:12px 15px}.products{grid-template-columns:1fr}.danger-actions{flex-direction:column;align-items:stretch}.danger-actions .btn{width:100%;text-align:center}}
</style></head><body><header><nav><a href="index.php"><img src="imgs/logo.png" alt="Chronnus"></a><div><a class="btn secondary" href="index.php">Voltar à loja</a></div></nav></header><main>
<span class="tag"><?= $ehVendedor ? 'Conta de vendedor' : 'Conta de cliente' ?></span><h1>Olá, <?= h($conta['nome_usuario']) ?></h1><p><?= h($conta['email_usuario']) ?></p>
<section class="panel cart-summary"><div><span class="muted">Meu carrinho</span><strong><?= (int)$unidadesCarrinhoUsuario ?> unidade(s)</strong><p class="muted">Seus produtos selecionados nesta sessão.</p></div><a class="btn" href="carrinho.php">Ver meu carrinho</a></section>
<div class="actions"><?php if ($ehVendedor): ?><a class="btn" href="vendedor/index.php">Gerenciar e cadastrar produtos</a><?php else: ?><a class="btn secondary" href="mudar_para_vendedor.php">Quero me tornar vendedor</a><?php endif; ?><a class="btn" href="index.php#ofertas">Comprar produtos</a><a class="btn secondary" href="editar_conta.php">Atualizar meus dados</a></div>
<?php if ($ehVendedor): ?>
<div class="metrics"><div class="metric"><span>Meus produtos</span><strong><?= count($produtos) ?></strong></div><div class="metric"><span>Pedidos registrados</span><strong><?= $pedidos ?></strong></div><div class="metric"><span>Unidades em pedidos</span><strong><?= $unidades ?></strong></div></div><p class="muted">Os indicadores incluem pedidos pendentes e pagos, mas não cancelados. Não representam pagamentos confirmados.</p>
<section class="panel"><h2>Meus produtos</h2><?php if (!$produtos): ?><p>Nenhum produto cadastrado.</p><?php endif; ?><div class="products"><?php foreach ($produtos as $p): ?><article class="product"><img src="<?= h($p['imagem_produ'] ?: 'imgs/pc.png') ?>" alt="<?= h($p['nome_produ']) ?>"><h3><?= h($p['nome_produ']) ?></h3><strong><?= moedaConta(round((float)$p['preco_produ']*(1-(float)$p['desconto_produ']/100),2)) ?></strong><p><?= (int)$p['compras'] ?> pedidos · <?= (int)$p['unidades'] ?> unidades</p><a class="btn secondary" href="vendedor/index.php?editar=<?= (int)$p['id_produ'] ?>">Editar</a></article><?php endforeach; ?></div></section>
<section class="panel"><h2>Histórico de vendas</h2><?php if (!$vendas): ?><p>Nenhum pedido recebido até o momento.</p><?php else: ?><div class="scroll"><table><thead><tr><th>Pedido</th><th>Data</th><th>Cliente</th><th>Produto</th><th>Qtd.</th><th>Subtotal</th><th>Status</th></tr></thead><tbody><?php foreach ($vendas as $v): ?><tr><td>#<?= (int)$v['id_pedido'] ?></td><td><?= h(date('d/m/Y H:i',strtotime($v['data_pedido']))) ?></td><td><?= h($v['nome_clien']) ?></td><td><?= h($v['nome_produ']) ?></td><td><?= (int)$v['quantidade'] ?></td><td><?= moedaConta($v['preco_final']) ?></td><td class="status status-<?= h($v['status']) ?>"><?= h(ucfirst($v['status'])) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php endif; ?>
<section class="panel"><h2>Meu histórico de compras</h2><?php if (!$compras): ?><p>Você ainda não realizou nenhum pedido.</p><?php else: ?><div class="scroll"><table><thead><tr><th>Pedido</th><th>Data</th><th>Produto</th><th>Qtd.</th><th>Subtotal</th><th>Status</th></tr></thead><tbody><?php foreach ($compras as $c): ?><tr><td>#<?= (int)$c['id_pedido'] ?></td><td><?= h(date('d/m/Y H:i',strtotime($c['data_pedido']))) ?></td><td><?= h($c['nome_produ'] ?? 'Produto indisponível') ?></td><td><?= (int)$c['quantidade'] ?></td><td><?= moedaConta($c['preco_final'] ?? $c['valor_total']) ?></td><td class="status status-<?= h($c['status']) ?>"><?= h(ucfirst($c['status'])) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<section class="panel"><h2 style="color:#ffb0a8">Segurança da conta</h2><p>A exclusão é permanente. Confira os registros que serão afetados antes de confirmar.</p><div class="danger-actions"><a class="btn danger" href="excluir_conta.php">Excluir minha conta</a><a class="btn secondary" href="logout.php">Sair da conta</a></div></section>
</main></body></html>
