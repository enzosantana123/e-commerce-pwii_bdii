<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/conexao.php';
atualizarTipoUsuario($pdo);

if (!isset($_SESSION['carrinho']) || !is_array($_SESSION['carrinho'])) {
    $_SESSION['carrinho'] = [];
}

function moedaCarrinho(float $valor): string
{
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

function obterItensCarrinho(PDO $pdo, array $carrinho): array
{
    if (!$carrinho) return [];
    $ids = array_values(array_filter(array_map('intval', array_keys($carrinho)), static fn($id) => $id > 0));
    if (!$ids) return [];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT p.id_produ, p.nome_produ, p.desc_produ, p.imagem_produ, p.preco_produ, p.desconto_produ,
                c.nome_categoria
         FROM produto p
         INNER JOIN categoria c ON c.id_categoria = p.id_categoria
         WHERE p.id_produ IN ($placeholders)
         ORDER BY FIELD(p.id_produ, " . implode(',', array_map('intval', $ids)) . ')'
    );
    $stmt->execute($ids);
    $itens = [];
    foreach ($stmt->fetchAll() as $produto) {
        $id = (int)$produto['id_produ'];
        $quantidade = max(1, min(20, (int)($carrinho[$id] ?? $carrinho[(string)$id] ?? 1)));
        $preco = (float)$produto['preco_produ'];
        $desconto = max(0, min(100, (float)$produto['desconto_produ']));
        $produto['quantidade_carrinho'] = $quantidade;
        $produto['preco_unitario_final'] = round($preco * (1 - $desconto / 100), 2);
        $produto['subtotal_carrinho'] = round($produto['preco_unitario_final'] * $quantidade, 2);
        // Mantém a imagem cadastrada para cada produto, inclusive uploads e URLs.
        $imagem = trim(str_replace('\\', '/', (string)($produto['imagem_produ'] ?? '')));
        if ($imagem !== '' && preg_match('~^(https?://|data:)~i', $imagem)) {
            $produto['imagem_produ'] = $imagem;
        } else {
            $imagem = preg_replace('~^(\\./)+~', '', $imagem);
            $imagem = ltrim($imagem, '/');

            // Alguns registros antigos podem conter prefixos relativos extras.
            $candidatos = array_unique(array_filter([
                $imagem,
                preg_replace('~^(\\.\\./)+~', '', $imagem)
            ]));
            $imagemEncontrada = null;
            foreach ($candidatos as $candidato) {
                if ($candidato !== '' && is_file(__DIR__ . '/' . $candidato)) {
                    $imagemEncontrada = $candidato;
                    break;
                }
            }

            if ($imagemEncontrada !== null) {
                $produto['imagem_produ'] = $imagemEncontrada;
            } else {
                // O fallback respeita a categoria: VARIADOS não deve receber a foto de PC.
                $categoria = strtoupper(trim((string)($produto['nome_categoria'] ?? '')));
                $produto['imagem_produ'] = $categoria === 'VARIADOS'
                    ? 'imgs/mouse-pad-gamer.png'
                    : 'imgs/pc.png';
            }
        }
        $itens[] = $produto;
    }
    return $itens;
}

$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
    $acao = (string)($_POST['acao'] ?? '');

    if ($acao === 'adicionar') {
        $idProduto = filter_var($_POST['id_produto'] ?? null, FILTER_VALIDATE_INT);
        $quantidade = filter_var($_POST['quantidade'] ?? 1, FILTER_VALIDATE_INT);
        if (!$idProduto || $idProduto < 1 || !$quantidade || $quantidade < 1 || $quantidade > 20) {
            $erro = 'Não foi possível adicionar este produto. Confira a quantidade.';
        } else {
            $stmt = $pdo->prepare('SELECT id_produ FROM produto WHERE id_produ = ? LIMIT 1');
            $stmt->execute([$idProduto]);
            if (!$stmt->fetchColumn()) {
                $erro = 'Este produto não está mais disponível.';
            } else {
                $atual = (int)($_SESSION['carrinho'][$idProduto] ?? 0);
                $_SESSION['carrinho'][$idProduto] = min(20, $atual + $quantidade);
                $mensagem = 'Produto adicionado ao carrinho.';
            }
        }
    } elseif ($acao === 'atualizar_item') {
        $idProduto = filter_var($_POST['id_produto'] ?? null, FILTER_VALIDATE_INT);
        $quantidade = filter_var($_POST['quantidade'] ?? null, FILTER_VALIDATE_INT);
        if ($idProduto && isset($_SESSION['carrinho'][$idProduto])) {
            if (!$quantidade || $quantidade < 1) {
                unset($_SESSION['carrinho'][$idProduto]);
            } else {
                $_SESSION['carrinho'][$idProduto] = min(20, $quantidade);
            }
            $mensagem = 'Quantidade atualizada.';
        }
    } elseif ($acao === 'remover') {
        $idProduto = filter_var($_POST['id_produto'] ?? null, FILTER_VALIDATE_INT);
        if ($idProduto) unset($_SESSION['carrinho'][$idProduto]);
        $mensagem = 'Produto removido do carrinho.';
    } elseif ($acao === 'finalizar') {
        if (!usuarioLogado()) {
            header('Location: login.php?redirect=' . rawurlencode('carrinho.php'));
            exit;
        }
        if (!in_array(tipoUsuario(), ['cliente', 'vendedor'], true)) {
            $erro = 'Esta conta não pode realizar compras.';
        } elseif (empty($_SESSION['carrinho'])) {
            $erro = 'Seu carrinho está vazio.';
        } else {
            try {
                $pdo->beginTransaction();
                $stmtConta = $pdo->prepare('SELECT * FROM usuario WHERE id_usuario = ? FOR UPDATE');
                $stmtConta->execute([(int)$_SESSION['usuario_id']]);
                $conta = $stmtConta->fetch();
                if (!$conta || !in_array($conta['tipo_usuario'], ['cliente', 'vendedor'], true)) {
                    throw new RuntimeException('Esta conta não pode realizar compras.');
                }

                $stmtCliente = $pdo->prepare('SELECT id_clien FROM cliente WHERE id_usuario = ? LIMIT 1');
                $stmtCliente->execute([(int)$conta['id_usuario']]);
                $cliente = $stmtCliente->fetch();
                if (!$cliente) {
                    $stmtPerfil = $pdo->prepare(
                        'INSERT INTO cliente (nome_clien,email_clien,telefone_clien,cpf_clien,senha_clien,id_usuario)
                         VALUES (?,?,?,?,?,?)'
                    );
                    $stmtPerfil->execute([
                        $conta['nome_usuario'], $conta['email_usuario'], $conta['telefone_usuario'],
                        $conta['cpf_usuario'], $conta['senha_usuario'], $conta['id_usuario']
                    ]);
                    $cliente = ['id_clien' => (int)$pdo->lastInsertId()];
                }

                $itensPedido = obterItensCarrinho($pdo, $_SESSION['carrinho']);
                if (!$itensPedido) throw new RuntimeException('Os produtos do carrinho não estão mais disponíveis.');

                // Bloqueia e recalcula cada preço no servidor antes de registrar o pedido.
                $stmtPrecoAtual = $pdo->prepare('SELECT preco_produ, desconto_produ FROM produto WHERE id_produ = ? FOR UPDATE');
                $total = 0.0;
                foreach ($itensPedido as &$itemPedido) {
                    $stmtPrecoAtual->execute([(int)$itemPedido['id_produ']]);
                    $precoAtual = $stmtPrecoAtual->fetch();
                    if (!$precoAtual) throw new RuntimeException('Um dos produtos do carrinho não está mais disponível.');
                    $itemPedido['preco_produ'] = (float)$precoAtual['preco_produ'];
                    $itemPedido['desconto_produ'] = (float)$precoAtual['desconto_produ'];
                    $precoFinalAtual = round($itemPedido['preco_produ'] * (1 - $itemPedido['desconto_produ'] / 100), 2);
                    $itemPedido['subtotal_carrinho'] = round($precoFinalAtual * (int)$itemPedido['quantidade_carrinho'], 2);
                    $total += (float)$itemPedido['subtotal_carrinho'];
                }
                unset($itemPedido);
                $total = round($total, 2);
                $stmtPedido = $pdo->prepare(
                    "INSERT INTO pedido (id_clien, data_pedido, valor_total, status) VALUES (?, NOW(), ?, 'pendente')"
                );
                $stmtPedido->execute([(int)$cliente['id_clien'], $total]);
                $idPedido = (int)$pdo->lastInsertId();

                $stmtItem = $pdo->prepare(
                    'INSERT INTO item_pedido (id_pedido, id_produ, quantidade, preco_unitario, desconto)
                     VALUES (?, ?, ?, ?, ?)'
                );
                foreach ($itensPedido as $item) {
                    $stmtItem->execute([
                        $idPedido,
                        (int)$item['id_produ'],
                        (int)$item['quantidade_carrinho'],
                        (float)$item['preco_produ'],
                        (float)$item['desconto_produ']
                    ]);
                }

                $pdo->commit();
                $_SESSION['carrinho'] = [];
                $_SESSION['pedido_criado'] = $idPedido;
                header('Location: compra.php?pedido=concluido');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $erro = $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'Não foi possível finalizar o pedido. Tente novamente.';
            }
        }
    }
}

$itens = obterItensCarrinho($pdo, $_SESSION['carrinho']);
$idsAtivos = array_map(static fn($item) => (int)$item['id_produ'], $itens);
foreach (array_keys($_SESSION['carrinho']) as $id) {
    if (!in_array((int)$id, $idsAtivos, true)) unset($_SESSION['carrinho'][$id]);
}
$totalCarrinho = array_sum(array_column($itens, 'subtotal_carrinho'));
$unidadesCarrinho = array_sum(array_column($itens, 'quantidade_carrinho'));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Meu carrinho | Chronnus</title><link rel="icon" href="imgs/favicon.png">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&family=Orbitron:wght@500;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#011815;--panel:#293740;--panel2:#102c29;--green:#33ebaa;--bright:#3bffb9;--text:#f8f0e3;--muted:#d9c5c5;--line:rgba(55,212,173,.22)}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:15px Inter,Arial,sans-serif}a{color:inherit;text-decoration:none}
header{background:linear-gradient(100deg,#2d334a,#293740,#44816d);border-bottom:1px solid var(--line)}nav{max-width:1250px;margin:auto;padding:14px 24px;display:flex;align-items:center;justify-content:space-between;gap:15px}nav img{height:46px;max-width:210px;object-fit:contain}
main{max-width:1250px;margin:36px auto;padding:0 24px 55px}.eyebrow{color:var(--bright);font-size:11px;letter-spacing:2px;text-transform:uppercase}h1,h2{font-family:Orbitron,sans-serif}h1{font-size:clamp(25px,4vw,37px);margin:9px 0} .sub{color:var(--muted);margin:0 0 25px}.layout{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:22px;align-items:start}.panel{background:linear-gradient(145deg,#2d334a,#172e2c);border:1px solid var(--line);border-radius:18px;padding:22px}.item{display:grid;grid-template-columns:110px minmax(0,1fr) auto;gap:18px;align-items:center;padding:16px 0;border-bottom:1px solid var(--line)}.item:first-child{padding-top:0}.item:last-child{border-bottom:0;padding-bottom:0}.photo{width:110px;height:100px;background:#071f1c;border-radius:12px;padding:8px}.photo img{width:100%;height:100%;object-fit:contain}.category{font-size:10px;color:var(--bright);letter-spacing:1px}.item h2{font:700 15px Inter,sans-serif;margin:5px 0}.desc{font-size:12px;color:var(--muted);line-height:1.5;margin:0 0 7px}.price{color:var(--bright);font-weight:800}.qty{display:flex;align-items:center;gap:8px;margin-top:9px}.qty input{width:72px;padding:8px;background:#071f1c;border:1px solid var(--line);border-radius:8px;color:white}.qty{margin-top:9px}.qty button{padding:0}.remove{background:none;border:0;color:#ffb0a8;text-decoration:underline;cursor:pointer;font-size:12px}.summary h2{font-size:17px;margin:0 0 18px;color:var(--bright)}.line{display:flex;justify-content:space-between;gap:12px;color:var(--muted);font-size:13px;margin:14px 0}.total{padding-top:16px;border-top:1px solid var(--line);font-weight:800;color:var(--text)}.total strong{color:var(--bright);font:700 20px Orbitron,sans-serif}.btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:9px;padding:12px 16px;background:var(--green);color:#102d28;font-weight:800;cursor:pointer}.btn:hover{background:var(--bright)}.btn-secondary{border:1px solid var(--green);background:transparent;color:var(--bright)}.btn-block{width:100%;margin-top:14px}.notice{padding:12px 14px;border-radius:10px;margin:0 0 16px;background:rgba(51,235,170,.1);border:1px solid var(--line);color:var(--bright)}.error{padding:12px 14px;border-radius:10px;margin:0 0 16px;background:rgba(180,50,50,.15);border:1px solid rgba(255,100,100,.4);color:#ffd1d1}.empty{text-align:center;padding:45px 20px}.empty h2{color:var(--bright)}.empty p{color:var(--muted)}
@media(max-width:850px){.layout{grid-template-columns:1fr}}@media(max-width:600px){main{padding:0 15px 35px;margin-top:24px}.panel{padding:16px}.item{grid-template-columns:82px minmax(0,1fr);gap:12px}.photo{width:82px;height:82px}.item-side{grid-column:2}.qty{flex-wrap:wrap}}
</style></head><body>
<header><nav><a href="index.php"><img src="imgs/logo.png" alt="Chronnus"></a><a class="btn btn-secondary" href="index.php">Continuar comprando</a></nav></header>
<main><span class="eyebrow">Chronnus Store</span><h1>Meu carrinho</h1><p class="sub"><?= $unidadesCarrinho ?> unidade(s) no carrinho</p>
<?php if ($mensagem): ?><p class="notice"><?= h($mensagem) ?></p><?php endif; ?>
<?php if ($erro): ?><p class="error"><?= h($erro) ?></p><?php endif; ?>
<?php if (!$itens): ?>
<section class="panel empty"><h2>Seu carrinho está vazio</h2><p>Explore as categorias e adicione os produtos que deseja comprar.</p><a class="btn" href="index.php#categorias">Ver categorias</a></section>
<?php else: ?>
<div class="layout"><section class="panel">
<?php foreach ($itens as $item): ?>
<article class="item"><div class="photo"><img src="<?= h($item['imagem_produ']) ?>" alt="<?= h($item['nome_produ']) ?>"></div>
<div><span class="category"><?= h($item['nome_categoria']) ?></span><h2><?= h($item['nome_produ']) ?></h2><p class="desc"><?= h($item['desc_produ'] ?? '') ?></p><span class="price"><?= moedaCarrinho((float)$item['preco_unitario_final']) ?> / unidade</span>
<form method="post" action="carrinho.php" class="qty"><input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="acao" value="atualizar_item"><input type="hidden" name="id_produto" value="<?= (int)$item['id_produ'] ?>"><label for="q-<?= (int)$item['id_produ'] ?>">Qtd.</label><input id="q-<?= (int)$item['id_produ'] ?>" type="number" name="quantidade" min="1" max="20" value="<?= (int)$item['quantidade_carrinho'] ?>"><button class="remove" type="submit" style="color:var(--bright)">Atualizar</button></form></div>
<div class="item-side"><strong class="price"><?= moedaCarrinho((float)$item['subtotal_carrinho']) ?></strong><form method="post" action="carrinho.php" style="margin-top:10px"><input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="acao" value="remover"><input type="hidden" name="id_produto" value="<?= (int)$item['id_produ'] ?>"><button class="remove" type="submit">Remover</button></form></div></article>
<?php endforeach; ?>
</section>
<aside class="panel summary"><h2>Resumo do pedido</h2><div class="line"><span>Produtos</span><strong><?= count($itens) ?></strong></div><div class="line"><span>Unidades</span><strong><?= $unidadesCarrinho ?></strong></div><div class="line total"><span>Total estimado</span><strong><?= moedaCarrinho((float)$totalCarrinho) ?></strong></div>
<form method="post" action="carrinho.php"><input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="acao" value="finalizar"><button class="btn btn-block" type="submit">Finalizar pedido</button></form>
<p class="desc" style="margin-top:13px">O pedido será registrado como pendente. O pagamento não é processado nesta versão.</p></aside></div>
<?php endif; ?></main></body></html>
