<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/conexao.php';

// Consome primeiro a confirmação armazenada na sessão. O redirecionamento
// após a compra não leva um id de produto, portanto precisa ser tratado antes.
$idPedidoCriado = $_SESSION['pedido_criado'] ?? null;
unset($_SESSION['pedido_criado']);
$pedidoConcluido = $idPedidoCriado !== null;

$idProduto = filter_var($_GET['id'] ?? $_POST['id_produto'] ?? null, FILTER_VALIDATE_INT);
if (!$pedidoConcluido && (!$idProduto || $idProduto < 1)) {
    header('Location: index.php');
    exit;
}

$erro = '';
$produto = null;
$precoOriginal = 0.0;
$desconto = 0.0;
$precoUnitario = 0.0;
$imagem = 'imgs/pc.png';

if (!$pedidoConcluido) {
    if (!usuarioLogado()) {
        header('Location: login.php?redirect=' . rawurlencode('compra.php?id=' . $idProduto));
        exit;
    }

    $stmtProduto = $pdo->prepare(
        'SELECT p.id_produ, p.nome_produ, p.desc_produ, p.imagem_produ, p.preco_produ, p.desconto_produ,
                c.nome_categoria
         FROM produto p INNER JOIN categoria c ON c.id_categoria = p.id_categoria
         WHERE p.id_produ = ? LIMIT 1'
    );
    $stmtProduto->execute([$idProduto]);
    $produto = $stmtProduto->fetch();
    if (!$produto) {
        http_response_code(404);
        exit('Produto não encontrado.');
    }

    $precoOriginal = (float)$produto['preco_produ'];
    $desconto = (float)$produto['desconto_produ'];
    $precoUnitario = round($precoOriginal * (1 - $desconto / 100), 2);
    $imagem = $produto['imagem_produ'] ?: 'imgs/pc.png';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
    $quantidade = filter_var($_POST['quantidade'] ?? 1, FILTER_VALIDATE_INT);
    if (!$quantidade || $quantidade < 1 || $quantidade > 20) {
        $erro = 'Escolha uma quantidade entre 1 e 20 unidades.';
    } elseif (tipoUsuario() !== 'cliente') {
        $erro = 'Para comprar, entre com uma conta de cliente. Você pode usar sua conta de cliente ou criar uma.';
    } else {
        try {
            $stmtCliente = $pdo->prepare('SELECT id_clien FROM cliente WHERE id_usuario = ? LIMIT 1');
            $stmtCliente->execute([(int)$_SESSION['usuario_id']]);
            $cliente = $stmtCliente->fetch();
            if (!$cliente) {
                throw new RuntimeException('Não encontramos seu perfil de cliente. Entre novamente na sua conta.');
            }

            $pdo->beginTransaction();
            // Reconsulta o preço no banco durante a finalização para não confiar em valores enviados pelo navegador.
            $stmtAtual = $pdo->prepare('SELECT preco_produ, desconto_produ FROM produto WHERE id_produ = ? FOR UPDATE');
            $stmtAtual->execute([$idProduto]);
            $atual = $stmtAtual->fetch();
            if (!$atual) {
                throw new RuntimeException('Este produto não está mais disponível.');
            }
            $precoBanco = (float)$atual['preco_produ'];
            $descontoBanco = (float)$atual['desconto_produ'];
            $precoBancoFinal = round($precoBanco * (1 - $descontoBanco / 100), 2);
            $total = round($precoBancoFinal * $quantidade, 2);

            $stmtPedido = $pdo->prepare(
                "INSERT INTO pedido (id_clien, data_pedido, valor_total, status) VALUES (?, NOW(), ?, 'pendente')"
            );
            $stmtPedido->execute([(int)$cliente['id_clien'], $total]);
            $idPedidoCriado = (int)$pdo->lastInsertId();

            $stmtItem = $pdo->prepare(
                'INSERT INTO item_pedido (id_pedido, id_produ, quantidade, preco_unitario, desconto)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmtItem->execute([$idPedidoCriado, $idProduto, $quantidade, $precoBanco, $descontoBanco]);
            $pdo->commit();
            $_SESSION['pedido_criado'] = $idPedidoCriado;
            header('Location: compra.php?pedido=concluido');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $erro = $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível registrar seu pedido. Tente novamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/png" href="imgs/favicon.png">
  <title><?= $pedidoConcluido ? 'Pedido realizado' : 'Finalizar compra' ?> | Chronnus</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;700&family=Orbitron:wght@400;500;700;800&display=swap" rel="stylesheet">
  <style>
    :root{--wine-dark:#2d334a;--wine-main:#293740;--gold-accent:#33ebaa;--gold-bright:#3bffb9;--bg-body:#011815;--white:#fff;--text-main:#F8F0E3;--text-muted:#D9C5C5;--line:rgba(55,212,173,.2);--shadow:0 18px 50px rgba(0,0,0,.4)}
    *{box-sizing:border-box}body{margin:0;min-height:100vh;background:var(--bg-body);color:var(--text-main);font-family:Inter, sans-serif}a{color:inherit;text-decoration:none}.top{height:76px;background:linear-gradient(100deg,var(--wine-dark),var(--wine-main) 60%,#44816d);border-bottom:1px solid var(--line);display:flex;align-items:center;padding:0 max(5%,calc((100% - 1200px)/2));}.top img{max-height:52px;max-width:220px;object-fit:contain}.wrap{width:min(1100px,92%);margin:48px auto}.back{color:var(--gold-bright);font-size:14px}.title{font-family:Orbitron,sans-serif;font-size:clamp(25px,4vw,38px);margin:22px 0 8px}.sub{color:var(--text-muted);margin:0 0 28px}.layout{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(280px,.7fr);gap:24px;align-items:start}.panel{background:linear-gradient(145deg,rgba(45,51,74,.96),rgba(17,39,38,.96));border:1px solid var(--line);border-radius:20px;padding:26px;box-shadow:var(--shadow)}.product{display:grid;grid-template-columns:minmax(180px, .85fr) minmax(0,1.15fr);gap:24px;align-items:center}.photo{min-height:240px;border-radius:15px;background:rgba(1,24,21,.55);display:flex;align-items:center;justify-content:center;padding:16px}.photo img{width:100%;height:230px;object-fit:contain}.category{font-size:11px;letter-spacing:2px;color:var(--gold-bright);font-weight:700}.name{font-family:Orbitron,sans-serif;font-size:clamp(19px,2.6vw,26px);margin:10px 0}.desc{color:var(--text-muted);line-height:1.7;font-size:14px}.old{display:block;text-decoration:line-through;color:var(--text-muted);font-size:13px;margin-top:20px}.price{font-family:Orbitron,sans-serif;font-size:28px;color:var(--gold-bright);font-weight:800}.discount{display:inline-block;color:var(--gold-bright);border:1px solid var(--line);border-radius:20px;padding:5px 10px;font-size:12px;margin-left:8px}.summary h2{font-family:Orbitron,sans-serif;font-size:19px;margin:0 0 20px}.line{display:flex;justify-content:space-between;gap:12px;color:var(--text-muted);font-size:14px;margin:14px 0}.total{border-top:1px solid var(--line);padding-top:18px;margin-top:20px;color:var(--text-main);font-weight:700}.total strong{color:var(--gold-bright);font-family:Orbitron,sans-serif;font-size:21px}.field{display:block;margin:22px 0 8px;font-size:13px;color:var(--text-muted)}input{width:100%;background:#071f1c;border:1px solid var(--line);border-radius:9px;color:white;padding:13px;font:inherit}.btn{width:100%;border:0;border-radius:10px;padding:15px;background:var(--gold-accent);color:#102d28;font-weight:800;cursor:pointer;margin-top:18px}.btn:hover{background:var(--gold-bright)}.error{background:rgba(180,50,50,.15);border:1px solid rgba(255,100,100,.4);padding:13px;border-radius:10px;color:#ffd1d1;margin-bottom:18px}.success{text-align:center}.success .check{font-size:48px;color:var(--gold-bright)}.success h1{font-family:Orbitron,sans-serif}.success p{color:var(--text-muted);line-height:1.7}.success .order{color:var(--gold-bright);font-weight:800;font-size:20px}@media(max-width:760px){.wrap{margin:28px auto}.layout{grid-template-columns:1fr}.product{grid-template-columns:1fr}.photo{min-height:190px}.photo img{height:190px}.panel{padding:20px}.top{height:66px}}
  </style>
</head>
<body>
<header class="top"><a href="index.php"><img src="imgs/logo.png" alt="Chronnus"></a></header>
<main class="wrap">
<?php if ($pedidoConcluido): ?>
  <section class="panel success">
    <div class="check">✓</div><h1>Pedido registrado!</h1>
    <p>Seu pedido foi salvo no banco de dados com status <strong>pendente</strong>. Esta etapa registra a compra no sistema; o pagamento ainda não foi processado.</p>
    <div class="order">Pedido #<?= (int)$idPedidoCriado ?></div>
    <p>Você pode acompanhar a evolução do pedido quando a área de pedidos estiver disponível.</p>
    <a class="btn" style="display:block" href="index.php">Voltar para a loja</a>
  </section>
<?php else: ?>
  <a class="back" href="index.php">← Voltar para a loja</a>
  <h1 class="title">Finalizar compra</h1><p class="sub">Confira os detalhes do produto antes de registrar seu pedido.</p>
  <?php if ($erro): ?><div class="error"><?= h($erro) ?></div><?php endif; ?>
  <?php if (tipoUsuario() !== 'cliente'): ?><div class="error">Você está conectado como vendedor. Para comprar, entre com uma conta de cliente.</div><?php endif; ?>
  <div class="layout">
    <section class="panel product">
      <div class="photo"><img src="<?= h($imagem) ?>" alt="<?= h($produto['nome_produ']) ?>" onerror="this.src='imgs/pc.png'"></div>
      <div><span class="category"><?= h($produto['nome_categoria']) ?></span><h2 class="name"><?= h($produto['nome_produ']) ?></h2><p class="desc"><?= h($produto['desc_produ'] ?? '') ?></p>
        <?php if ($desconto > 0): ?><span class="old">De R$ <?= number_format($precoOriginal,2,',','.') ?></span><?php endif; ?>
        <span class="price">R$ <?= number_format($precoUnitario,2,',','.') ?></span>
        <?php if ($desconto > 0): ?><span class="discount">-<?= number_format($desconto,0,',','.') ?>%</span><?php endif; ?>
      </div>
    </section>
    <aside class="panel summary"><h2>Resumo do pedido</h2>
      <div class="line"><span>Preço unitário</span><strong>R$ <?= number_format($precoUnitario,2,',','.') ?></strong></div>
      <form method="post" action="compra.php?id=<?= (int)$idProduto ?>">
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="id_produto" value="<?= (int)$idProduto ?>">
        <label class="field" for="quantidade">Quantidade (máximo 20)</label><input id="quantidade" name="quantidade" type="number" min="1" max="20" value="<?= h($_POST['quantidade'] ?? '1') ?>" required>
        <div class="line total"><span>Total estimado</span><strong id="total">R$ <?= number_format($precoUnitario,2,',','.') ?></strong></div>
        <button class="btn" type="submit" <?= tipoUsuario() !== 'cliente' ? 'disabled style="opacity:.45;cursor:not-allowed"' : '' ?>>Confirmar pedido</button>
      </form>
      <p class="desc" style="font-size:11px;margin-top:15px">Ao confirmar, o pedido será gravado no banco de dados. O pagamento não é processado nesta versão.</p>
    </aside>
  </div>
  <script>
    const quantidade=document.getElementById('quantidade');
    const total=document.getElementById('total');
    const unitario=<?= json_encode($precoUnitario, JSON_UNESCAPED_UNICODE) ?>;
    const moeda=new Intl.NumberFormat('pt-BR',{style:'currency',currency:'BRL'});
    function atualizarTotal(){let q=Math.max(1,Math.min(20,parseInt(quantidade.value||'1',10)));total.textContent=moeda.format(unitario*q)}
    quantidade.addEventListener('input',atualizarTotal);
  </script>
<?php endif; ?>
</main>
</body>
</html>
